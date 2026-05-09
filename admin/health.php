<?php
/**
 * admin/health.php — Cron heartbeat endpoint + simple HTML status.
 *
 * Двойна цел:
 *   1. POST /admin/health.php?cron=NAME&status=OK     — cron-ове записват heartbeat
 *   2. GET  /admin/health.php                         — owner вижда статус на всички cron-ове
 *
 * Heartbeat табл: cron_heartbeats(cron_name PK, last_run_at, last_status, last_message, last_duration_ms).
 * Авто-създаване ако липсва.
 *
 * Auth:
 *   - GET — owner only (session)
 *   - POST — изисква HMAC token (CRON_HEALTH_TOKEN env). Cron скриптовете
 *            подават Authorization: Bearer <token>.
 *
 * Linked: STRESS_BUILD_PLAN.md ред 165 (admin/health.php).
 */
require_once __DIR__ . '/../config/database.php';
$pdo = DB::get();

// Auto-create heartbeat table on first call (idempotent)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS cron_heartbeats (
        cron_name VARCHAR(64) NOT NULL PRIMARY KEY,
        last_run_at DATETIME NOT NULL,
        last_status VARCHAR(32) NOT NULL DEFAULT 'OK',
        last_message TEXT NULL,
        last_duration_ms INT NULL,
        consecutive_failures INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ─── POST: записва heartbeat ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // HMAC token check
    $expected = getenv('CRON_HEALTH_TOKEN') ?: null;
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = '';
    if (str_starts_with($auth, 'Bearer ')) {
        $token = substr($auth, 7);
    }
    if (!$expected || !hash_equals($expected, $token)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'unauthorized']);
        exit;
    }

    $cron_name = trim((string) ($_POST['cron'] ?? ''));
    $status    = trim((string) ($_POST['status'] ?? 'OK'));
    $message   = (string) ($_POST['message'] ?? '');
    $duration  = isset($_POST['duration_ms']) ? (int) $_POST['duration_ms'] : null;

    if (!$cron_name || !preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $cron_name)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'invalid cron name']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO cron_heartbeats (cron_name, last_run_at, last_status, last_message, last_duration_ms, consecutive_failures)
            VALUES (?, NOW(), ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                last_run_at = NOW(),
                last_status = VALUES(last_status),
                last_message = VALUES(last_message),
                last_duration_ms = VALUES(last_duration_ms),
                consecutive_failures = CASE
                    WHEN VALUES(last_status) = 'OK' THEN 0
                    ELSE consecutive_failures + 1
                END
        ");
        $stmt->execute([$cron_name, $status, $message, $duration, $status === 'OK' ? 0 : 1]);
        echo json_encode(['ok' => true, 'cron' => $cron_name, 'status' => $status]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'db error: ' . $e->getMessage()]);
    }
    exit;
}

// ─── GET: owner-only HTML ───
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$user_id   = (int)$_SESSION['user_id'];
$tenant_id = (int)$_SESSION['tenant_id'];
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND tenant_id = ?");
$stmt->execute([$user_id, $tenant_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['role'] !== 'owner') {
    header('Location: ../chat.php'); exit;
}

$expected_crons = [
    'nightly_robot'      => ['hour' => 2,  'desc' => 'Стрес симулация (200-300 действия)'],
    'test_new_features'  => ['hour' => 3,  'desc' => 'Тест на новите commits'],
    'morning_summary'    => ['hour' => 6,  'desc' => 'Raw статистики + STRESS_BOARD update'],
    'code_analyzer'      => ['hour' => 6,  'desc' => 'MORNING_REPORT.md (06:30)'],
    'sanity_checker'     => ['hour' => 7,  'desc' => 'Balance validator (X-Y+Z)'],
];

$heartbeats = [];
try {
    $stmt = $pdo->query("SELECT * FROM cron_heartbeats");
    foreach ($stmt as $row) { $heartbeats[$row['cron_name']] = $row; }
} catch (Throwable $e) { /* ignore */ }

function status_for(array $expected, ?array $hb): array {
    if (!$hb) {
        return ['STALE', 'crit', 'никога не е стартирал'];
    }
    $age = time() - strtotime($hb['last_run_at']);
    if ($age > 90000) {  // > 25h
        return ['STALE', 'crit', 'не е стартирал >25h'];
    }
    if ($hb['last_status'] !== 'OK') {
        return [$hb['last_status'], 'warn', $hb['last_message'] ?: '—'];
    }
    return ['OK', 'ok', 'last run ' . round($age / 60) . ' min ago'];
}

?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cron Health — Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;padding:20px}
h1{color:#fff;margin-bottom:8px}
.sub{color:#94a3b8;margin-bottom:24px;font-size:13px}
.card{background:#1e293b;border-radius:8px;padding:16px;border:1px solid #334155;margin-bottom:12px}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{text-align:left;padding:10px;border-bottom:1px solid #334155}
th{color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
.pill{display:inline-block;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:600}
.pill.ok{background:#16a34a33;color:#22c55e}
.pill.warn{background:#d9770633;color:#f59e0b}
.pill.crit{background:#dc262633;color:#ef4444}
code{background:#0f172a;padding:2px 6px;border-radius:4px;font-size:12px}
.muted{color:#64748b;font-size:12px}
.row-crit{background:rgba(239,68,68,.08)}
.row-warn{background:rgba(245,158,11,.08)}
</style>
</head>
<body>
<h1>⏰ Cron Health</h1>
<div class="sub">
    Heartbeats от cron-овете на droplet · <?= date('Y-m-d H:i:s') ?>
</div>

<div class="card">
<table>
<thead>
<tr>
    <th>Cron</th><th>Очаквано</th><th>Last run</th><th>Duration</th><th>Status</th><th>Failures</th>
</tr>
</thead>
<tbody>
<?php foreach ($expected_crons as $name => $meta):
    $hb = $heartbeats[$name] ?? null;
    [$lbl, $cls, $msg] = status_for($meta, $hb);
    $row_class = $cls === 'crit' ? 'row-crit' : ($cls === 'warn' ? 'row-warn' : '');
?>
<tr class="<?= $row_class ?>">
    <td><strong><?= htmlspecialchars($name) ?></strong><br>
        <span class="muted"><?= htmlspecialchars($meta['desc']) ?></span></td>
    <td><?= sprintf('%02d:00', $meta['hour']) ?></td>
    <td class="muted"><?= $hb ? htmlspecialchars($hb['last_run_at']) : '—' ?></td>
    <td><?= $hb && $hb['last_duration_ms'] ? round($hb['last_duration_ms'] / 1000, 1) . 's' : '—' ?></td>
    <td><span class="pill <?= $cls ?>"><?= htmlspecialchars($lbl) ?></span><br>
        <span class="muted"><?= htmlspecialchars($msg) ?></span></td>
    <td><?= $hb ? (int)$hb['consecutive_failures'] : 0 ?></td>
</tr>
<?php endforeach; ?>

<?php
// Show heartbeats for ANY cron not in expected_crons (custom cron-ове)
foreach ($heartbeats as $name => $hb):
    if (isset($expected_crons[$name])) continue;
    [$lbl, $cls, $msg] = status_for(['hour' => '?', 'desc' => '(custom)'], $hb);
?>
<tr>
    <td><strong><?= htmlspecialchars($name) ?></strong><br><span class="muted">(custom)</span></td>
    <td>—</td>
    <td class="muted"><?= htmlspecialchars($hb['last_run_at']) ?></td>
    <td><?= $hb['last_duration_ms'] ? round($hb['last_duration_ms'] / 1000, 1) . 's' : '—' ?></td>
    <td><span class="pill <?= $cls ?>"><?= htmlspecialchars($lbl) ?></span><br>
        <span class="muted"><?= htmlspecialchars($msg) ?></span></td>
    <td><?= (int)$hb['consecutive_failures'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div class="muted" style="margin-top:24px">
    POST /admin/health.php — heartbeat endpoint. Изисква <code>Authorization: Bearer $CRON_HEALTH_TOKEN</code>.
    <br>Полета: <code>cron</code>, <code>status</code> (OK / FAIL / WARN), <code>message</code>, <code>duration_ms</code>.
</div>
</body>
</html>
