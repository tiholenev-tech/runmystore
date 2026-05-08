<?php
/**
 * admin/stress-board.php — STRESS система live дъска (Етап 2 от STRESS_BUILD_PLAN.md).
 *
 * Owner-only. Чете live DB и показва:
 *   - Активни tenants (включително STRESS Lab)
 *   - Insights generated/shown днес
 *   - Action rate, dismiss rate
 *   - Fact verifier rejects (когато колоната съществува)
 *   - Errors последните 24h
 *   - Slow queries >2s (от error_log таблица или PHP errlog)
 *   - DB latency snapshot (ad-hoc SELECT 1 timing)
 *
 * Read-only: НИКАКВИ INSERT/UPDATE/DELETE.
 *
 * Linked: STRESS_BUILD_PLAN.md ред 140-167 (Етап 2 — admin/stress-board.php).
 */
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$user_id   = (int)$_SESSION['user_id'];
$tenant_id = (int)$_SESSION['tenant_id'];
$pdo       = DB::get();

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND tenant_id = ?");
$stmt->execute([$user_id, $tenant_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['role'] !== 'owner') {
    header('Location: ../chat.php');
    exit;
}

/**
 * SCHEMA-DEFENSIVE helper. Връща true ако таблицата съществува.
 */
function table_exists(PDO $pdo, string $t): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $st->execute([$t]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function column_exists(PDO $pdo, string $t, string $c): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $st->execute([$t, $c]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) { return false; }
}

// ───────────────────────────────────────────────────────────────────
// (a) Активни tenants
// ───────────────────────────────────────────────────────────────────
$tenants = [];
try {
    $sql = "SELECT id, name, email, plan, "
         . (column_exists($pdo, 'tenants', 'mode') ? "mode" : "'-' AS mode") . ", "
         . (column_exists($pdo, 'tenants', 'created_at') ? "created_at" : "NULL AS created_at")
         . " FROM tenants ORDER BY id";
    $tenants = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $tenants_error = $e->getMessage();
}

// ───────────────────────────────────────────────────────────────────
// (b) Insights днес — generated / shown / actioned / dismissed
// ───────────────────────────────────────────────────────────────────
$insights = ['generated' => 0, 'shown' => 0, 'actioned' => 0, 'dismissed' => 0];
$insights_by_tenant = [];
if (table_exists($pdo, 'ai_insights')) {
    try {
        $stmt = $pdo->query("
            SELECT
                tenant_id,
                COUNT(*) AS generated,
                SUM(CASE WHEN status = 'live' THEN 1 ELSE 0 END) AS shown,
                SUM(CASE WHEN status = 'actioned' THEN 1 ELSE 0 END) AS actioned,
                SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) AS dismissed
            FROM ai_insights
            WHERE created_at >= CURDATE()
            GROUP BY tenant_id
            ORDER BY generated DESC
        ");
        foreach ($stmt as $row) {
            $insights_by_tenant[] = $row;
            $insights['generated'] += (int) $row['generated'];
            $insights['shown']     += (int) $row['shown'];
            $insights['actioned']  += (int) $row['actioned'];
            $insights['dismissed'] += (int) $row['dismissed'];
        }
    } catch (Throwable $e) { $insights_error = $e->getMessage(); }
}

$action_rate   = $insights['shown'] > 0 ? round($insights['actioned']  / $insights['shown'] * 100, 1) : 0.0;
$dismiss_rate  = $insights['shown'] > 0 ? round($insights['dismissed'] / $insights['shown'] * 100, 1) : 0.0;

// ───────────────────────────────────────────────────────────────────
// (c) Fact verifier rejects (когато колоната съществува)
// ───────────────────────────────────────────────────────────────────
$fact_rejects = null;
if (table_exists($pdo, 'ai_insights') && column_exists($pdo, 'ai_insights', 'fact_verifier_status')) {
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) AS cnt
            FROM ai_insights
            WHERE created_at >= NOW() - INTERVAL 24 HOUR
              AND fact_verifier_status = 'rejected'
        ");
        $fact_rejects = (int) $stmt->fetchColumn();
    } catch (Throwable $e) { /* ignore */ }
}

// ───────────────────────────────────────────────────────────────────
// (d) Errors последните 24h
// ───────────────────────────────────────────────────────────────────
$errors_24h = null;
if (table_exists($pdo, 'error_log')) {
    try {
        $errors_24h = (int) $pdo->query(
            "SELECT COUNT(*) FROM error_log WHERE created_at >= NOW() - INTERVAL 24 HOUR"
        )->fetchColumn();
    } catch (Throwable $e) { /* ignore */ }
}

// ───────────────────────────────────────────────────────────────────
// (e) Slow queries >2s (от slow_queries таблица ако има)
// ───────────────────────────────────────────────────────────────────
$slow_queries = [];
if (table_exists($pdo, 'slow_queries')) {
    try {
        $stmt = $pdo->query("
            SELECT query_text, duration_ms, occurred_at
            FROM slow_queries
            WHERE occurred_at >= NOW() - INTERVAL 24 HOUR AND duration_ms > 2000
            ORDER BY duration_ms DESC
            LIMIT 10
        ");
        $slow_queries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* ignore */ }
}

// ───────────────────────────────────────────────────────────────────
// (f) DB latency snapshot — ad-hoc SELECT 1 timing
// ───────────────────────────────────────────────────────────────────
$db_latency_ms = null;
try {
    $t0 = microtime(true);
    $pdo->query("SELECT 1")->fetch();
    $db_latency_ms = round((microtime(true) - $t0) * 1000, 2);
} catch (Throwable $e) { /* ignore */ }

// ───────────────────────────────────────────────────────────────────
// (g) STRESS Lab quick stats (ако съществува)
// ───────────────────────────────────────────────────────────────────
$stress_lab = null;
foreach ($tenants as $t) {
    if (strtolower($t['email'] ?? '') === 'stress@runmystore.ai') {
        $stress_lab = $t;
        break;
    }
}
$stress_stats = [];
if ($stress_lab) {
    $sid = (int) $stress_lab['id'];
    foreach (['products', 'sales', 'inventory', 'suppliers', 'stores', 'users'] as $table) {
        if (!table_exists($pdo, $table)) {
            $stress_stats[$table] = '—';
            continue;
        }
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE tenant_id = ?");
            $st->execute([$sid]);
            $stress_stats[$table] = (int) $st->fetchColumn();
        } catch (Throwable $e) {
            $stress_stats[$table] = '—';
        }
    }
}

// ───────────────────────────────────────────────────────────────────
// (h) Cron heartbeat (от admin/health.php DB запис)
// ───────────────────────────────────────────────────────────────────
$cron_heartbeats = [];
if (table_exists($pdo, 'cron_heartbeats')) {
    try {
        $stmt = $pdo->query("SELECT cron_name, last_run_at, last_status FROM cron_heartbeats ORDER BY cron_name");
        $cron_heartbeats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* ignore */ }
}

?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>STRESS Board — Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;padding:20px;min-height:100vh}
h1{font-size:24px;margin-bottom:8px;color:#fff}
.sub{color:#94a3b8;margin-bottom:20px;font-size:13px}
.grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));margin-bottom:24px}
.card{background:#1e293b;border-radius:8px;padding:16px;border:1px solid #334155}
.card h2{font-size:14px;color:#94a3b8;margin-bottom:10px;text-transform:uppercase;letter-spacing:.05em}
.metric{font-size:32px;font-weight:bold;color:#fff}
.metric.warn{color:#f59e0b}.metric.crit{color:#ef4444}.metric.ok{color:#22c55e}
.sub-metric{font-size:12px;color:#94a3b8;margin-top:4px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{text-align:left;padding:8px;border-bottom:1px solid #334155}
th{color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;font-size:11px}
.row-warn{background:rgba(245,158,11,.1)}
.row-crit{background:rgba(239,68,68,.1)}
.pill{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600}
.pill.ok{background:#16a34a33;color:#22c55e}.pill.warn{background:#d9770633;color:#f59e0b}.pill.crit{background:#dc262633;color:#ef4444}
.section-title{font-size:16px;color:#fff;margin:24px 0 12px;padding-bottom:8px;border-bottom:1px solid #334155}
code{background:#0f172a;padding:2px 6px;border-radius:4px;font-size:11px}
.muted{color:#64748b;font-size:12px}
.refresh{position:fixed;top:20px;right:20px;background:#3b82f6;color:#fff;padding:8px 14px;border-radius:6px;text-decoration:none;font-size:13px}
</style>
</head>
<body>
<a href="?refresh=1" class="refresh">⟳ Обнови</a>
<h1>📋 STRESS Board</h1>
<div class="sub">
    Live данни (read-only) · <?= date('Y-m-d H:i:s') ?> ·
    DB latency: <?= $db_latency_ms !== null ? "<strong>{$db_latency_ms} ms</strong>" : '—' ?>
</div>

<!-- Top metrics -->
<div class="grid">
    <div class="card">
        <h2>Активни tenants</h2>
        <div class="metric"><?= count($tenants) ?></div>
        <div class="sub-metric">включително STRESS Lab: <?= $stress_lab ? '✅' : '⬜' ?></div>
    </div>
    <div class="card">
        <h2>Insights днес</h2>
        <div class="metric"><?= number_format($insights['generated']) ?></div>
        <div class="sub-metric">shown: <?= $insights['shown'] ?> · actioned: <?= $insights['actioned'] ?></div>
    </div>
    <div class="card">
        <h2>Action rate</h2>
        <div class="metric <?= $action_rate >= 30 ? 'ok' : ($action_rate >= 10 ? 'warn' : 'crit') ?>">
            <?= $action_rate ?>%
        </div>
        <div class="sub-metric">dismiss: <?= $dismiss_rate ?>%</div>
    </div>
    <div class="card">
        <h2>Fact verifier rejects 24h</h2>
        <div class="metric <?= $fact_rejects === null ? '' : ($fact_rejects > 5 ? 'crit' : 'ok') ?>">
            <?= $fact_rejects === null ? '—' : $fact_rejects ?>
        </div>
        <div class="sub-metric"><?= $fact_rejects === null ? 'колоната не съществува (Phase 2)' : 'last 24h' ?></div>
    </div>
    <div class="card">
        <h2>Errors 24h</h2>
        <div class="metric <?= $errors_24h === null ? '' : ($errors_24h > 50 ? 'crit' : ($errors_24h > 10 ? 'warn' : 'ok')) ?>">
            <?= $errors_24h === null ? '—' : $errors_24h ?>
        </div>
        <div class="sub-metric"><?= $errors_24h === null ? 'error_log таблица липсва' : 'PHP errors last 24h' ?></div>
    </div>
    <div class="card">
        <h2>Slow queries 24h</h2>
        <div class="metric <?= count($slow_queries) > 5 ? 'crit' : (count($slow_queries) > 0 ? 'warn' : 'ok') ?>">
            <?= count($slow_queries) ?>
        </div>
        <div class="sub-metric">>2s execution time</div>
    </div>
</div>

<!-- Tenants -->
<div class="section-title">🏢 Tenants</div>
<div class="card">
<table>
<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Plan</th><th>Mode</th><th>Created</th></tr></thead>
<tbody>
<?php foreach ($tenants as $t):
    $is_stress = strtolower($t['email'] ?? '') === 'stress@runmystore.ai';
    $is_eni    = ((int)$t['id']) === 7;
?>
<tr<?= $is_stress ? ' class="row-warn"' : ($is_eni ? '' : '') ?>>
    <td>#<?= (int)$t['id'] ?></td>
    <td><strong><?= htmlspecialchars($t['name'] ?? '—') ?></strong>
        <?php if ($is_stress): ?> <span class="pill warn">LAB</span><?php endif; ?>
        <?php if ($is_eni): ?> <span class="pill ok">ENI</span><?php endif; ?>
    </td>
    <td><code><?= htmlspecialchars($t['email'] ?? '—') ?></code></td>
    <td><?= htmlspecialchars($t['plan'] ?? '—') ?></td>
    <td><?= htmlspecialchars($t['mode'] ?? '—') ?></td>
    <td class="muted"><?= htmlspecialchars($t['created_at'] ?? '—') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php if ($stress_lab): ?>
<!-- STRESS Lab quick stats -->
<div class="section-title">🧪 STRESS Lab Quick Stats</div>
<div class="card">
<table>
<thead><tr><th>Table</th><th>Rows</th></tr></thead>
<tbody>
<?php foreach ($stress_stats as $table => $cnt): ?>
<tr><td><code><?= $table ?></code></td><td><?= is_int($cnt) ? number_format($cnt) : $cnt ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<!-- Insights by tenant -->
<?php if (!empty($insights_by_tenant)): ?>
<div class="section-title">🤖 AI Insights днес — по tenant</div>
<div class="card">
<table>
<thead><tr><th>Tenant</th><th>Generated</th><th>Shown</th><th>Actioned</th><th>Dismissed</th></tr></thead>
<tbody>
<?php foreach ($insights_by_tenant as $r): ?>
<tr>
    <td>#<?= (int)$r['tenant_id'] ?></td>
    <td><?= number_format($r['generated']) ?></td>
    <td><?= number_format($r['shown']) ?></td>
    <td><?= number_format($r['actioned']) ?></td>
    <td><?= number_format($r['dismissed']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<!-- Slow queries -->
<?php if (!empty($slow_queries)): ?>
<div class="section-title">🐢 Slow queries (>2s, 24h)</div>
<div class="card">
<table>
<thead><tr><th>Query</th><th>Duration</th><th>When</th></tr></thead>
<tbody>
<?php foreach ($slow_queries as $q): ?>
<tr class="row-crit">
    <td><code><?= htmlspecialchars(substr($q['query_text'] ?? '', 0, 120)) ?>…</code></td>
    <td><strong><?= round($q['duration_ms'] / 1000, 2) ?>s</strong></td>
    <td class="muted"><?= htmlspecialchars($q['occurred_at']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<!-- Cron heartbeats -->
<?php if (!empty($cron_heartbeats)): ?>
<div class="section-title">⏰ Cron heartbeats</div>
<div class="card">
<table>
<thead><tr><th>Cron</th><th>Last run</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($cron_heartbeats as $c):
    $stale = strtotime($c['last_run_at']) < (time() - 90000);
    $cls   = $stale ? 'row-crit' : ($c['last_status'] !== 'OK' ? 'row-warn' : '');
?>
<tr class="<?= $cls ?>">
    <td><code><?= htmlspecialchars($c['cron_name']) ?></code></td>
    <td class="muted"><?= htmlspecialchars($c['last_run_at']) ?></td>
    <td>
        <?php if ($c['last_status'] === 'OK'): ?>
            <span class="pill ok">OK</span>
        <?php elseif ($stale): ?>
            <span class="pill crit">STALE</span>
        <?php else: ?>
            <span class="pill warn"><?= htmlspecialchars($c['last_status']) ?></span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="section-title">⏰ Cron heartbeats</div>
<div class="card muted">
    <p>Таблица <code>cron_heartbeats</code> не съществува или е празна.</p>
    <p>Виж <code>admin/health.php</code> за първа регистрация на cron.</p>
</div>
<?php endif; ?>

<div class="muted" style="margin-top:32px;text-align:center;">
    Read-only. Никакви mutации. Източник: <code>admin/stress-board.php</code> · Етап 2 / STRESS_BUILD_PLAN.md
</div>

</body>
</html>
