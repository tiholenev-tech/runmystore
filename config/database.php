<?php
class DB {
    private static ?PDO $instance = null;

    private static array $config = [
        'host'    => 'localhost',
        'dbname'  => 'runmystore',
        'user'    => 'runmystore',
        'pass'    => '***REMOVED_DB_PASSWORD***',
        'charset' => 'utf8mb4',
    ];

    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::$config['host'],
                self::$config['dbname'],
                self::$config['charset']
            );
            self::$instance = new PDO($dsn, self::$config['user'], self::$config['pass'], [
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
     * Usage:
     *   $sale_id = DB::tx(function() use ($data) {
     *       $id = Sales::create($data);
     *       Inventory::decrement(...);
     *       return $id;
     *   });
     *
     * Limitations (S79):
     *   - No nested transactions (PDO native — second beginTransaction throws)
     *     → SAVEPOINT support идва в S80
     *   - No deadlock retry → S80
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
