<?php
/**
 * S79.SECURITY — credentials се четат от /etc/runmystore/db.env
 * НИКОГА hardcoded стойности тук!
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
     * DOC_05 §7.2 — Transaction wrapper.
     * Wraps callable in BEGIN/COMMIT, ROLLBACK on Throwable.
     * Returns whatever the callback returns.
     *
     * Limitations (S79):
     *   - No nested transactions (PDO native — second beginTransaction throws)
     *     -> SAVEPOINT support идва в S80
     *   - No deadlock retry -> S80
     */
    public static function tx(callable $callback) {
        $pdo = self::get();
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
