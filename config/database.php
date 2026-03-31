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
}
