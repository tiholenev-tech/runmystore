<?php
/**
 * admin/insights-health.php — Insights distribution diagnostic dashboard (S91)
 *
 * Owner-only. Shows ai_insights module distribution + health % for the past 7 days
 * to surface routing issues (e.g. signals leaking out of life-board's home filter).
 */
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$user_id   = (int)$_SESSION['user_id'];
$tenant_id = (int)$_SESSION['tenant_id'];
$pdo       = DB::get();

$stmt = $pdo->prepare("
    SELECT u.role, t.lang
    FROM users u
    JOIN tenants t ON t.id = u.tenant_id
    WHERE u.id = ? AND u.tenant_id = ?
");
$stmt->execute([$user_id, $tenant_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['role'] !== 'owner') {
    header('Location: ../chat.php');
    exit;
}

// (a) Module distribution — last 7 days
$stmt = $pdo->prepare("
    SELECT module, COUNT(*) AS cnt
    FROM ai_insights
    WHERE tenant_id = ? AND created_at > NOW() - INTERVAL 7 DAY
    GROUP BY module
    ORDER BY cnt DESC
");
$stmt->execute([$tenant_id]);
$dist = $stmt->fetchAll(PDO::FETCH_ASSOC);

// (b) Top 5 hidden insight types (module != 'home')
$stmt = $pdo->prepare("
    SELECT topic_id, module, COUNT(*) AS cnt
    FROM ai_insights
    WHERE tenant_id = ? AND created_at > NOW() - INTERVAL 7 DAY AND module != 'home'
    GROUP BY topic_id, module
    ORDER BY cnt DESC
    LIMIT 5
");
$stmt->execute([$tenant_id]);
$hidden_top = $stmt->fetchAll(PDO::FETCH_ASSOC);

// (c) Totals
$total   = 0;
$visible = 0;
foreach ($dist as $row) {
    $cnt = (int)$row['cnt'];
    $total += $cnt;
    if ($row['module'] === 'home') $visible += $cnt;
}
$hidden = $total - $visible;
$hidden_pct = $total > 0 ? round($hidden / $total * 100, 1) : 0.0;

if ($hidden_pct >= 30)      { $status = 'CRITICAL'; $status_color = '#ef4444'; }
elseif ($hidden_pct >= 1)   { $status = 'WARNING';  $status_color = '#f59e0b'; }
else                        { $status = 'HEALTHY';  $status_color = '#22c55e'; }

$module_color = [
    'home'      => '#22c55e',
    'products'  => '#f59e0b',
    'warehouse' => '#3b82f6',
    'stats'     => '#a855f7',
    'sale'      => '#ec4899',
];
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Insights Health — Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
body{
    font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
    background:#0a0b14;color:#e2e8f0;
    min-height:100vh;padding:16px;line-height:1.4;
}
.wrap{max-width:480px;margin:0 auto}
h1{
    font-size:14px;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;
    color:#a5b4fc;margin-bottom:4px;
}
.sub{font-size:11px;color:rgba(226,232,240,0.55);margin-bottom:16px;font-weight:600}
.card{
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.08);
    border-radius:10px;padding:14px;margin-bottom:12px;
}
.card h2{
    font-size:10px;font-weight:800;letter-spacing:0.10em;text-transform:uppercase;
    color:rgba(226,232,240,0.55);margin-bottom:10px;
}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:7px 10px;text-align:left}
th{
    font-size:9px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;
    color:rgba(226,232,240,0.45);border-bottom:1px solid rgba(255,255,255,0.08);
}
td{border-bottom:1px solid rgba(255,255,255,0.05)}
tr:last-child td{border-bottom:none}
.module-pill{
    display:inline-block;padding:2px 8px;border-radius:100px;
    font-size:10px;font-weight:800;letter-spacing:0.04em;
    background:rgba(255,255,255,0.06);color:#fff;
}
.cnt{font-weight:800;font-variant-numeric:tabular-nums;text-align:right;color:#fff}
.status-row{
    display:flex;align-items:center;gap:10px;
    padding:14px;border-radius:10px;margin-bottom:12px;
    background:rgba(255,255,255,0.03);
    border:2px solid <?= $status_color ?>;
}
.status-dot{
    width:14px;height:14px;border-radius:50%;flex-shrink:0;
    background:<?= $status_color ?>;
    box-shadow:0 0 12px <?= $status_color ?>;
}
.status-label{
    font-size:13px;font-weight:900;letter-spacing:0.06em;
    color:<?= $status_color ?>;
}
.totals{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px}
.tot-cell{
    background:rgba(255,255,255,0.03);
    border:1px solid rgba(255,255,255,0.06);
    border-radius:10px;padding:10px;text-align:center;
}
.tot-lbl{font-size:9px;font-weight:800;letter-spacing:0.10em;text-transform:uppercase;color:rgba(226,232,240,0.45);margin-bottom:4px}
.tot-num{font-size:22px;font-weight:900;font-variant-numeric:tabular-nums;color:#fff;line-height:1}
.tot-pct{font-size:13px;color:rgba(226,232,240,0.65);font-weight:700;margin-top:4px}
.actions{display:flex;gap:8px;margin-top:18px}
.btn{
    flex:1;display:flex;align-items:center;justify-content:center;
    padding:10px 14px;border-radius:8px;
    background:rgba(165,180,252,0.10);border:1px solid rgba(165,180,252,0.30);
    color:#a5b4fc;font-size:12px;font-weight:800;letter-spacing:0.04em;
    text-decoration:none;cursor:pointer;font-family:inherit;
}
.btn:hover{background:rgba(165,180,252,0.18)}
.empty{
    text-align:center;padding:18px;color:rgba(226,232,240,0.45);
    font-size:12px;font-style:italic;
}
.note{font-size:10px;color:rgba(226,232,240,0.40);margin-top:14px;line-height:1.5}
</style>
</head>
<body>
<div class="wrap">
    <h1>INSIGHTS HEALTH — <?= date('d.m.Y') ?></h1>
    <div class="sub">Tenant ID: <?= $tenant_id ?> · Window: last 7 days</div>

    <div class="status-row">
        <div class="status-dot"></div>
        <div>
            <div class="status-label"><?= $status ?></div>
            <div class="sub" style="margin:0"><?= $hidden_pct ?>% от insights не са видими в life-board.</div>
        </div>
    </div>

    <div class="totals">
        <div class="tot-cell">
            <div class="tot-lbl">Total</div>
            <div class="tot-num"><?= $total ?></div>
        </div>
        <div class="tot-cell">
            <div class="tot-lbl">Visible</div>
            <div class="tot-num" style="color:#22c55e"><?= $visible ?></div>
            <div class="tot-pct"><?= $total > 0 ? round($visible / $total * 100, 1) : 0 ?>%</div>
        </div>
        <div class="tot-cell">
            <div class="tot-lbl">Hidden</div>
            <div class="tot-num" style="color:<?= $hidden > 0 ? '#ef4444' : '#fff' ?>"><?= $hidden ?></div>
            <div class="tot-pct"><?= $hidden_pct ?>%</div>
        </div>
    </div>

    <div class="card">
        <h2>Module distribution</h2>
        <?php if (empty($dist)): ?>
            <div class="empty">Няма insights за последните 7 дни.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Module</th><th style="text-align:right">Count</th><th style="text-align:right">%</th></tr>
            </thead>
            <tbody>
                <?php foreach ($dist as $row):
                    $m = (string)$row['module'];
                    $c = (int)$row['cnt'];
                    $pct = $total > 0 ? round($c / $total * 100, 1) : 0;
                    $bg = $module_color[$m] ?? '#64748b';
                ?>
                <tr>
                    <td><span class="module-pill" style="background:<?= $bg ?>;color:#fff"><?= htmlspecialchars($m) ?></span></td>
                    <td class="cnt"><?= $c ?></td>
                    <td class="cnt" style="color:rgba(226,232,240,0.55);font-weight:700"><?= $pct ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Top 5 hidden topic_ids (module &ne; home)</h2>
        <?php if (empty($hidden_top)): ?>
            <div class="empty">Няма скрити insights — всички routing-нати в 'home'.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Topic ID</th><th>Module</th><th style="text-align:right">Count</th></tr>
            </thead>
            <tbody>
                <?php foreach ($hidden_top as $row):
                    $bg = $module_color[$row['module']] ?? '#64748b';
                ?>
                <tr>
                    <td style="font-family:ui-monospace,monospace;font-size:11px"><?= htmlspecialchars((string)$row['topic_id']) ?></td>
                    <td><span class="module-pill" style="background:<?= $bg ?>;color:#fff"><?= htmlspecialchars((string)$row['module']) ?></span></td>
                    <td class="cnt"><?= (int)$row['cnt'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="actions">
        <a href="?" class="btn">Refresh</a>
        <a href="../chat.php" class="btn">← Back</a>
    </div>

    <div class="note">
        S91 fix applied to compute-insights.php: default module = 'home'.
        Cron-generated insights from now on will land in life-board.
        Existing rows untouched — see SESSION_S91_INSIGHTS_HANDOFF.md за migration question.
    </div>
</div>
</body>
</html>
