<?php
/**
 * S80.AB tests — DB::tx() deadlock retry + SAVEPOINT nesting
 *
 * Run: php tests/test_db_tx.php
 * Exit: 0 при всички PASS, 1 при failure
 *
 * Тестова таблица: _s80_test_log (DROP-ва се на старт и край).
 * НЕ пипа production tables.
 */

require_once __DIR__ . '/../config/database.php';

$pass = 0;
$fail = 0;
$pdo = DB::get();

// ───────── Setup: ефемерна таблица ─────────
$pdo->exec("DROP TABLE IF EXISTS _s80_test_log");
$pdo->exec("CREATE TABLE _s80_test_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tag VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

function assertOk(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) {
        echo "[PASS] $name\n";
        $pass++;
    } else {
        echo "[FAIL] $name" . ($detail ? " — $detail" : '') . "\n";
        $fail++;
    }
}

function rowCount(string $tag): int {
    $stmt = DB::run("SELECT COUNT(*) FROM _s80_test_log WHERE tag = ?", [$tag]);
    return (int)$stmt->fetchColumn();
}

function clearTable(): void {
    DB::get()->exec("TRUNCATE TABLE _s80_test_log");
}

// ════════════════════════════════════════════
// TEST 1 — Happy path outer
// ════════════════════════════════════════════
clearTable();
try {
    $result = DB::tx(function($pdo) {
        DB::run("INSERT INTO _s80_test_log (tag) VALUES (?)", ['t1']);
        return 'ok';
    });
    assertOk('Test 1: happy path outer', $result === 'ok' && rowCount('t1') === 1);
} catch (Throwable $e) {
    assertOk('Test 1: happy path outer', false, 'unexpected throw: ' . $e->getMessage());
}

// ════════════════════════════════════════════
// TEST 2 — Outer rollback on user exception
// ════════════════════════════════════════════
clearTable();
$threw = false;
try {
    DB::tx(function($pdo) {
        DB::run("INSERT INTO _s80_test_log (tag) VALUES (?)", ['t2']);
        throw new RuntimeException('user-triggered rollback');
    });
} catch (RuntimeException $e) {
    $threw = ($e->getMessage() === 'user-triggered rollback');
}
assertOk('Test 2: outer rollback on user exception', $threw && rowCount('t2') === 0,
    'threw=' . ($threw ? 'yes' : 'no') . ' rows=' . rowCount('t2'));

// ════════════════════════════════════════════
// TEST 3 — Nested success (outer + inner both insert)
// ════════════════════════════════════════════
clearTable();
try {
    DB::tx(function($pdo) {
        DB::run("INSERT INTO _s80_test_log (tag) VALUES (?)", ['t3-outer']);
        DB::tx(function($pdo) {
            DB::run("INSERT INTO _s80_test_log (tag) VALUES (?)", ['t3-inner']);
        });
    });
    assertOk('Test 3: nested success',
        rowCount('t3-outer') === 1 && rowCount('t3-inner') === 1,
        'outer=' . rowCount('t3-outer') . ' inner=' . rowCount('t3-inner'));
} catch (Throwable $e) {
    assertOk('Test 3: nested success', false, 'threw: ' . $e->getMessage());
}

// ════════════════════════════════════════════
// TEST 4 — Nested partial rollback (SAVEPOINT работи)
//   outer INSERT успява, inner INSERT throw-ва
//   → outer row остава, inner row се rollback-ва
// ════════════════════════════════════════════
clearTable();
try {
    DB::tx(function($pdo) {
        DB::run("INSERT INTO _s80_test_log (tag) VALUES (?)", ['t4-outer']);
        try {
            DB::tx(function($pdo) {
                DB::run("INSERT INTO _s80_test_log (tag) VALUES (?)", ['t4-inner']);
                throw new RuntimeException('inner fail');
            });
        } catch (RuntimeException $e) {
            // Catch инвера, за да продължи outer-ът
        }
    });
    assertOk('Test 4: nested partial rollback (SAVEPOINT)',
        rowCount('t4-outer') === 1 && rowCount('t4-inner') === 0,
        'outer=' . rowCount('t4-outer') . ' inner=' . rowCount('t4-inner'));
} catch (Throwable $e) {
    assertOk('Test 4: nested partial rollback (SAVEPOINT)', false, 'threw: ' . $e->getMessage());
}

// ════════════════════════════════════════════
// TEST 5 — Outer + nested both throw → нула rows
// ════════════════════════════════════════════
clearTable();
$threw = false;
try {
    DB::tx(function($pdo) {
        DB::run("INSERT INTO _s80_test_log (tag) VALUES (?)", ['t5-outer']);
        DB::tx(function($pdo) {
            DB::run("INSERT INTO _s80_test_log (tag) VALUES (?)", ['t5-inner']);
            throw new RuntimeException('inner fail');
        });
        // Не достига дотук — exception от inner propagate-ва
    });
} catch (RuntimeException $e) {
    $threw = true;
}
assertOk('Test 5: outer+nested both throw',
    $threw && rowCount('t5-outer') === 0 && rowCount('t5-inner') === 0,
    'threw=' . ($threw ? 'yes' : 'no') .
    ' outer=' . rowCount('t5-outer') . ' inner=' . rowCount('t5-inner'));

// ════════════════════════════════════════════
// TEST 6 — Non-retryable error (FK violation 1452) → no retry, exception веднага
// ════════════════════════════════════════════
// Setup: temp таблица с FK към несъществуващ ред
$pdo->exec("DROP TABLE IF EXISTS _s80_fk_child");
$pdo->exec("DROP TABLE IF EXISTS _s80_fk_parent");
$pdo->exec("CREATE TABLE _s80_fk_parent (id INT PRIMARY KEY) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE _s80_fk_child (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    FOREIGN KEY (parent_id) REFERENCES _s80_fk_parent(id)
) ENGINE=InnoDB");

$startTime = microtime(true);
$threw = false;
$retried = false;
try {
    DB::tx(function($pdo) {
        DB::run("INSERT INTO _s80_fk_child (parent_id) VALUES (?)", [99999]);
    });
} catch (RuntimeException $e) {
    $threw = true;
    // Ако имаше retry, time щеше да е > 50ms (поне 1 backoff)
    $elapsed = microtime(true) - $startTime;
    $retried = ($elapsed > 0.05);
}
assertOk('Test 6: non-retryable error (FK 1452) — no retry',
    $threw && !$retried,
    'threw=' . ($threw ? 'yes' : 'no') . ' retried=' . ($retried ? 'YES (BUG)' : 'no'));

// ───────── Cleanup ─────────
$pdo->exec("DROP TABLE IF EXISTS _s80_fk_child");
$pdo->exec("DROP TABLE IF EXISTS _s80_fk_parent");
$pdo->exec("DROP TABLE IF EXISTS _s80_test_log");

echo "\n";
echo "════════════════════════════════════════\n";
echo "  PASS: $pass   FAIL: $fail\n";
echo "════════════════════════════════════════\n";

exit($fail === 0 ? 0 : 1);
