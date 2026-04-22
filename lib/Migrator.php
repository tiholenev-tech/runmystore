<?php
require_once __DIR__ . '/../config/database.php';

class Migrator {
    private string $dir;
    public function __construct(?string $dir = null) {
        $this->dir = rtrim($dir ?? __DIR__ . '/../migrations', '/');
    }
    public function up(): array {
        $applied = [];
        foreach ($this->getPending() as $m) {
            $sql = file_get_contents($m['file_up']);
            $checksum = hash('sha256', $sql);
            $down = file_exists($m['file_down']) ? file_get_contents($m['file_down']) : null;
            $start = microtime(true);
            try {
                foreach ($this->splitStatements($sql) as $stmt) {
                    try { DB::exec($stmt); }
                    catch (PDOException $e) {
                        if (!$this->idempotentSkip($e)) throw $e;
                        fwrite(STDERR, "  skipped (idempotent): {$e->getMessage()}\n");
                    }
                }
                $elapsed = (int) ((microtime(true) - $start) * 1000);
                DB::run("INSERT INTO schema_migrations
                  (version, name, checksum, applied_at, applied_by, execution_time_ms, rollback_sql)
                  VALUES (?, ?, ?, NOW(), ?, ?, ?)",
                    [$m['version'], $m['name'], $checksum, $this->who(), $elapsed, $down]);
                $applied[] = "{$m['version']} - {$m['name']} ({$elapsed}ms)";
            } catch (Throwable $e) {
                throw new RuntimeException("Migration {$m['version']} failed: " . $e->getMessage(), 0, $e);
            }
        }
        return $applied;
    }
    public function down(string $version): void {
        $row = DB::run("SELECT * FROM schema_migrations WHERE version=?", [$version])->fetch();
        if (!$row) throw new RuntimeException("Migration $version not applied.");
        if (empty($row['rollback_sql'])) throw new RuntimeException("Migration $version has no rollback SQL.");
        foreach ($this->splitStatements($row['rollback_sql']) as $stmt) {
            try { DB::exec($stmt); }
            catch (PDOException $e) {
                if (!$this->idempotentSkip($e)) throw $e;
                fwrite(STDERR, "  rollback skipped: {$e->getMessage()}\n");
            }
        }
        DB::run("DELETE FROM schema_migrations WHERE version=?", [$version]);
    }
    public function status(): array {
        $applied = [];
        foreach (DB::run("SELECT version, name, applied_at, checksum FROM schema_migrations ORDER BY version")->fetchAll() as $r) {
            $applied[$r['version']] = $r;
        }
        $rows = []; $known = [];
        foreach ($this->scanFiles() as $f) {
            $known[$f['version']] = true;
            $tampered = false;
            if (isset($applied[$f['version']])) {
                $sql = file_get_contents($f['file_up']);
                if (hash('sha256', $sql) !== $applied[$f['version']]['checksum']) $tampered = true;
            }
            $rows[] = [
                'version' => $f['version'], 'name' => $f['name'],
                'status' => isset($applied[$f['version']]) ? ($tampered ? 'TAMPERED' : 'applied') : 'pending',
                'applied_at' => $applied[$f['version']]['applied_at'] ?? null,
            ];
        }
        foreach ($applied as $v => $r) {
            if (!isset($known[$v])) {
                $rows[] = ['version' => $v, 'name' => $r['name'], 'status' => 'MISSING_FILE', 'applied_at' => $r['applied_at']];
            }
        }
        return $rows;
    }
    private function getPending(): array {
        $applied = [];
        foreach (DB::run("SELECT version, checksum FROM schema_migrations")->fetchAll() as $r) {
            $applied[$r['version']] = $r['checksum'];
        }
        $pending = [];
        foreach ($this->scanFiles() as $f) {
            if (isset($applied[$f['version']])) {
                $sql = file_get_contents($f['file_up']);
                if (hash('sha256', $sql) !== $applied[$f['version']]) {
                    throw new RuntimeException("Migration {$f['version']} tampered (checksum mismatch).");
                }
                continue;
            }
            $pending[] = $f;
        }
        return $pending;
    }
    private function scanFiles(): array {
        $files = glob($this->dir . '/*.up.sql') ?: [];
        sort($files);
        $out = [];
        foreach ($files as $up) {
            $base = basename($up, '.up.sql');
            if (!preg_match('/^(\d{8}_\d{3})_(.+)$/', $base, $m)) continue;
            $out[] = ['version' => $m[1], 'name' => $m[2], 'file_up' => $up,
                      'file_down' => $this->dir . '/' . $base . '.down.sql'];
        }
        return $out;
    }
    private function splitStatements(string $sql): array {
        $sql = preg_replace('!/\*.*?\*/!s', '', $sql);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        return array_values(array_filter(array_map('trim', explode(';', $sql))));
    }
    private function idempotentSkip(PDOException $e): bool {
        $needles = ['Duplicate column name', 'Duplicate key name', 'already exists',
                    'Multiple primary key', "Can't DROP"];
        foreach ($needles as $n) if (stripos($e->getMessage(), $n) !== false) return true;
        return false;
    }
    private function who(): string {
        $u = $_ENV['USER'] ?? (function_exists('posix_geteuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? 'cli') : 'cli');
        return substr($u . '@' . gethostname(), 0, 100);
    }
}
