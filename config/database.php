<?php
/**
 * S79.SECURITY — credentials се четат от /etc/runmystore/db.env
 * S80.A — DB::tx() deadlock retry (1213, 1205) с exponential backoff + jitter
 * S80.B — DB::tx() SAVEPOINT-based nested transactions
 *
 * НИКОГА hardcoded credentials тук!
 */
class DB {
    private static ?PDO $instance = null;
    private static ?array $config = null;

    private static function loadConfig(): array {
        if (self::$config !== null) return self::$config;

        $env_file = '/etc/runmystore/db.env';
        if (!file_exists($env_file)) {
            error_log('S79.SECURITY: DB config missing: ' . $env_file);
            die('Database configuration not found. Contact administrator.');
        }
        if (!is_readable($env_file)) {
            error_log('S79.SECURITY: DB config not readable (check ownership/permissions): ' . $env_file);
            die('Database configuration not readable. Contact administrator.');
        }

        $env = parse_ini_file($env_file);
        if ($env === false || !isset($env['DB_HOST'], $env['DB_NAME'], $env['DB_USER'], $env['DB_PASS'])) {
            error_log('S79.SECURITY: DB env file malformed: ' . $env_file);
            die('Database configuration invalid. Contact administrator.');
        }

        self::$config = [
            'host'    => $env['DB_HOST'],
            'dbname'  => $env['DB_NAME'],
            'user'    => $env['DB_USER'],
            'pass'    => $env['DB_PASS'],
            'charset' => 'utf8mb4',
        ];
        return self::$config;
    }

    public static function get(): PDO {
        if (self::$instance === null) {
            $cfg = self::loadConfig();
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['dbname'],
                $cfg['charset']
            );
            self::$instance = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }

    public static function run(string $sql, array $params = []): PDOStatement {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function lastInsertId(): string {
        return self::get()->lastInsertId();
    }

    public static function beginTransaction(): void {
        self::get()->beginTransaction();
    }

    public static function commit(): void {
        self::get()->commit();
    }

    public static function rollback(): void {
        self::get()->rollBack();
    }

    public static function exec(string $sql): int {
        return self::get()->exec($sql);
    }

    /**
     * S80.AB — Transaction wrapper с deadlock retry + SAVEPOINT nesting.
     *
     * OUTER call (no active transaction):
     *   - BEGIN -> callable($pdo) -> COMMIT
     *   - На errors 1213 (deadlock) или 1205 (lock wait timeout): ROLLBACK + retry
     *   - Backoff: exponential + jitter (~50ms / 150ms / 350ms)
     *   - След $maxRetries неуспешни опита: throw RuntimeException обвиващ PDOException
     *   - На non-retryable PDO error или Throwable: ROLLBACK + rethrow
     *
     * INNER call (active transaction съществува):
     *   - SAVEPOINT sp_<hex8> -> callable($pdo) -> RELEASE SAVEPOINT
     *   - На exception: ROLLBACK TO SAVEPOINT + rethrow
     *   - НЕ retry на inner level (deadlock убива outer transaction-а — outer-ът ще retry-не цялата операция)
     *
     * ⚠️ IDEMPOTENCY WARNING:
     *   Callable-ът може да бъде извикан НЯКОЛКО ПЪТИ при retry.
     *   НЕ слагай side effects извън DB вътре в DB::tx():
     *     - Stripe charge -> double-charge
     *     - HTTP API call -> double request
     *     - File write -> partial state
     *     - Email send -> spam
     *   Прави side effects СЛЕД като DB::tx() върне резултат.
     *
     * @param callable $fn Получава PDO instance: function(PDO $pdo): mixed
     * @param int $maxRetries Максимум retries на outer level (default 3)
     * @return mixed Каквото върне callable-ът
     * @throws RuntimeException При exhausted retries (с PDOException като previous)
     * @throws Throwable При non-retryable error (rethrown 1:1)
     */
    public static function tx(callable $fn, int $maxRetries = 3) {
        $pdo = self::get();

        // ─────────────────────────────────────────
        // INNER CASE — SAVEPOINT, no retry
        // ─────────────────────────────────────────
        if ($pdo->inTransaction()) {
            $sp = 'sp_' . bin2hex(random_bytes(4));
            $pdo->exec("SAVEPOINT $sp");
            try {
                $result = $fn($pdo);
                $pdo->exec("RELEASE SAVEPOINT $sp");
                return $result;
            } catch (Throwable $e) {
                try {
                    $pdo->exec("ROLLBACK TO SAVEPOINT $sp");
                } catch (Throwable $_) {
                    // SAVEPOINT може вече да не съществува ако outer transaction е killed
                    // Suppress secondary error, оригиналното $e е по-важно
                }
                throw $e;
            }
        }

        // ─────────────────────────────────────────
        // OUTER CASE — BEGIN/COMMIT с deadlock retry
        // ─────────────────────────────────────────
        $attempt = 0;
        $lastException = null;
        $tenantTag = $_SESSION['tenant_id'] ?? 'cli';

        while ($attempt <= $maxRetries) {
            try {
                $pdo->beginTransaction();
                $result = $fn($pdo);
                $pdo->commit();

                if ($attempt > 0) {
                    error_log(sprintf(
                        '[DB::tx] recovered after %d retries (tenant=%s)',
                        $attempt, $tenantTag
                    ));
                }
                return $result;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $code = $e->errorInfo[1] ?? null;
                $isRetryable = in_array($code, [1213, 1205], true);
                $lastException = $e;

                if (!$isRetryable || $attempt >= $maxRetries) {
                    throw new RuntimeException(
                        sprintf(
                            'DB::tx %s after %d attempts (last: code=%s msg=%s)',
                            $isRetryable ? 'exhausted retries' : 'failed (non-retryable)',
                            $attempt + 1,
                            $code ?? 'unknown',
                            $e->getMessage()
                        ),
                        0,
                        $e
                    );
                }

                // Exponential backoff + jitter
                // attempt=0 -> ~50-100ms, attempt=1 -> ~100-150ms, attempt=2 -> ~200-250ms
                $delay = ((1 << $attempt) * 50_000) + random_int(0, 50_000);
                error_log(sprintf(
                    '[DB::tx] retry %d/%d after %dms (code=%s tenant=%s)',
                    $attempt + 1, $maxRetries,
                    intdiv($delay, 1000),
                    $code, $tenantTag
                ));
                usleep($delay);
                $attempt++;

            } catch (Throwable $e) {
                // User code exception (RuntimeException, LogicException, etc.) — no retry
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }

        // Unreachable defensive fallback
        throw $lastException ?? new RuntimeException('DB::tx exhausted all retries (unreachable)');
    }
}
