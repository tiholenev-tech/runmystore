<?php
/**
 * settings.php — Account / printer / theme settings (S82.SHELL)
 * Replaces the old settings.html stub. Auth-protected, uses unified shell.
 */
require_once __DIR__ . '/config/database.php';
session_start();
if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)($_SESSION['user_id'] ?? 0);

$tenant = DB::run("SELECT name, email, plan, language, currency, onboarding_done FROM tenants WHERE id = ?", [$tenant_id])->fetch();
$user   = $user_id > 0 ? DB::run("SELECT name, email, role FROM users WHERE id = ?", [$user_id])->fetch() : null;

$plan_label = strtoupper($tenant['plan'] ?? 'free');
?>
<!doctype html>
<html lang="<?= htmlspecialchars($tenant['language'] ?? 'bg') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Настройки — RunMyStore.ai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/theme.css?v=<?= @filemtime(__DIR__.'/css/theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/css/shell.css?v=<?= @filemtime(__DIR__.'/css/shell.css') ?: 1 ?>">
<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg-main);color:var(--text-primary);font-family:'Montserrat',Inter,system-ui,sans-serif;min-height:100dvh}
.page-wrap{max-width:480px;margin:0 auto;padding:0 12px}
.section-title{font-size:11px;font-weight:800;color:var(--text-secondary);letter-spacing:.10em;text-transform:uppercase;margin:18px 4px 8px}
.card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:14px 16px;margin-bottom:10px;box-shadow:var(--shadow-card)}
.row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-subtle)}
.row:last-child{border-bottom:none}
.row-label{font-size:13px;font-weight:600;color:var(--text-primary)}
.row-sub{font-size:11px;color:var(--text-muted);margin-top:2px}
.row-val{font-size:13px;color:var(--text-secondary);font-weight:600}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border-radius:10px;border:1px solid var(--border-subtle);background:var(--bg-elevated);color:var(--text-primary);font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;font-family:inherit}
.btn:hover{border-color:var(--border-glow);color:var(--indigo-300)}
.btn.danger{border-color:rgba(239,68,68,.4);color:#fca5a5;background:rgba(239,68,68,.08)}
.btn.danger:hover{background:rgba(239,68,68,.15)}
.placeholder{padding:24px 8px;text-align:center;color:var(--text-muted);font-size:12px;line-height:1.5}
.placeholder svg{width:36px;height:36px;stroke:currentColor;stroke-width:1.5;fill:none;stroke-linecap:round;stroke-linejoin:round;opacity:.55;margin-bottom:8px}
</style>
</head>
<body class="has-rms-shell">

<?php include __DIR__ . '/partials/header.php'; ?>

<div class="page-wrap">
    <div class="section-title">Профил</div>
    <div class="card">
        <div class="row">
            <div>
                <div class="row-label"><?= htmlspecialchars($tenant['name'] ?? '—') ?></div>
                <div class="row-sub"><?= htmlspecialchars($tenant['email'] ?? '') ?></div>
            </div>
            <span class="rms-plan-badge <?= htmlspecialchars(strtolower($tenant['plan'] ?? 'free')) ?>"><?= htmlspecialchars($plan_label) ?></span>
        </div>
        <?php if ($user): ?>
        <div class="row">
            <div>
                <div class="row-label">Потребител: <?= htmlspecialchars($user['name'] ?? '—') ?></div>
                <div class="row-sub">роля: <?= htmlspecialchars($user['role'] ?? 'owner') ?></div>
            </div>
        </div>
        <?php endif; ?>
        <div class="row">
            <div>
                <div class="row-label">Език</div>
                <div class="row-sub">валута: <?= htmlspecialchars($tenant['currency'] ?? 'EUR') ?></div>
            </div>
            <div class="row-val"><?= htmlspecialchars(strtoupper($tenant['language'] ?? 'bg')) ?></div>
        </div>
    </div>

    <div class="section-title">Тема</div>
    <div class="card">
        <div class="row">
            <div>
                <div class="row-label">Светла / тъмна</div>
                <div class="row-sub">Изборът се запомня на това устройство</div>
            </div>
            <button class="btn" type="button" onclick="rmsToggleTheme()">Смени</button>
        </div>
    </div>

    <div class="section-title">Принтер</div>
    <div class="card">
        <div class="row">
            <div>
                <div class="row-label">Bluetooth принтер</div>
                <div class="row-sub">DTM-5811 — TSPL термо</div>
            </div>
            <a href="printer-setup.php" class="btn">Сдвояване</a>
        </div>
    </div>

    <div class="section-title">AI</div>
    <div class="card">
        <div class="row">
            <div>
                <div class="row-label">AI image credits</div>
                <div class="row-sub">FREE 0/ден · START 3/ден · PRO 10/ден</div>
            </div>
            <div class="row-val" id="aiCreditsRemaining">—</div>
        </div>
    </div>

    <div class="section-title">Сигурност</div>
    <div class="card">
        <div class="placeholder">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <div>Биометрия + PIN — S82.5 (планирано).</div>
        </div>
    </div>

    <div class="section-title">Сесия</div>
    <div class="card">
        <div class="row">
            <div>
                <div class="row-label">Излез от профила</div>
                <div class="row-sub">Ще трябва да влезеш отново</div>
            </div>
            <a href="logout.php" class="btn danger">Изход →</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/chat-input-bar.php'; ?>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
<?php include __DIR__ . '/partials/shell-scripts.php'; ?>
</body>
</html>
