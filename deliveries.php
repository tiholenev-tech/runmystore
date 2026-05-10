<?php
/**
 * deliveries.php — S140.DELIVERIES Phase A skeleton
 *
 * Dual-mode hub за модул „Доставки".
 *  • Прост (simple) режим — еталон mockups/P14_deliveries.html (P10 canon).
 *  • Разширен (detailed) режим — еталон mockups/P14b_deliveries_detailed_v5_BG.html
 *    (1:1 шапка от P11_detailed_mode.html).
 *
 * Phase A = skeleton + DB read.
 *  – Хедър ред 1 (canonical 7 елемента) идва от partials/header.php.
 *  – Subbar (ред 2) се рендерира inline тук, по LAYOUT_SHELL_LAW v1.0:
 *      [магазин чип] [ДОСТАВКИ breadcrumb] [mode toggle] (+амбер „Продажба" pill в лесен).
 *  – Долна навигация (partials/bottom-nav.php) — САМО в разширен режим;
 *    в лесен режим я няма в DOM-а, по §2.2 от закона.
 *  – DB read: deliveries (+supplier_name, item_count) + KPI агрегати.
 *  – Без OCR / voice flow / receive sheet — те са за Phase B (delivery.php).
 *
 * Контракт със сесията: $_SESSION['mode'] ∈ {'simple', unset=detailed}.
 * Mode toggle: ?mode=simple | ?mode=detailed → редактира сесията и redirect.
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)($_SESSION['store_id'] ?? 0);

// ── Mode toggle (записва/изтрива $_SESSION['mode']) ──
if (isset($_GET['mode'])) {
    if ($_GET['mode'] === 'simple') {
        $_SESSION['mode'] = 'simple';
    } else {
        unset($_SESSION['mode']);
    }
    header('Location: deliveries.php');
    exit;
}

// ── Store switch (?store=N) ──
if (!empty($_GET['store'])) {
    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
        [(int)$_GET['store'], $tenant_id])->fetch();
    if ($chk) {
        $_SESSION['store_id'] = (int)$_GET['store'];
    }
    header('Location: deliveries.php');
    exit;
}

// Fallback: pick first store if missing
if (!$store_id) {
    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1',
        [$tenant_id])->fetch();
    if ($first) {
        $store_id = (int)$first['id'];
        $_SESSION['store_id'] = $store_id;
    }
}

// ── Tenant + user + store ──
$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
$user   = DB::run(
    'SELECT name, role FROM users WHERE id=? AND tenant_id=? AND is_active=1 LIMIT 1',
    [$user_id, $tenant_id]
)->fetch();
if (!$user) { header('Location: logout.php'); exit; }

$role     = $user['role'] ?? 'seller';
$lang     = $tenant['language'] ?? 'bg';
$currency = $tenant['currency']  ?? '€';

$store      = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
    [$store_id, $tenant_id])->fetch();
$store_name = $store['name'] ?? 'Магазин';

// Mode resolution — sellers са pинни в лесен; иначе сесията решава.
$mode = ($role === 'seller')
    ? 'simple'
    : ((($_SESSION['mode'] ?? '') === 'simple') ? 'simple' : 'detailed');

// ── KPI: лесен режим (днес + закъснели) ──
$kpi_today_count = (int)DB::run(
    "SELECT COUNT(*) FROM deliveries
       WHERE tenant_id=? AND store_id=?
         AND DATE(created_at) = CURDATE()
         AND status NOT IN ('voided','superseded')",
    [$tenant_id, $store_id]
)->fetchColumn();

$kpi_today_total = (float)DB::run(
    "SELECT COALESCE(SUM(total),0) FROM deliveries
       WHERE tenant_id=? AND store_id=?
         AND DATE(created_at) = CURDATE()
         AND status NOT IN ('voided','superseded')",
    [$tenant_id, $store_id]
)->fetchColumn();

$kpi_today_suppliers = (int)DB::run(
    "SELECT COUNT(DISTINCT supplier_id) FROM deliveries
       WHERE tenant_id=? AND store_id=?
         AND DATE(created_at) = CURDATE()
         AND supplier_id IS NOT NULL
         AND status NOT IN ('voided','superseded')",
    [$tenant_id, $store_id]
)->fetchColumn();

// „Закъснели" = чакаща/изпратена/в преглед, без delivered_at, по-стара от 1 ден.
$late_rows = DB::run(
    "SELECT d.id, COALESCE(s.name,'Без доставчик') AS supplier_name
       FROM deliveries d
       LEFT JOIN suppliers s ON s.id = d.supplier_id
      WHERE d.tenant_id=? AND d.store_id=?
        AND d.status IN ('draft','pending','reviewing')
        AND d.delivered_at IS NULL
        AND d.created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
      ORDER BY d.created_at ASC
      LIMIT 5",
    [$tenant_id, $store_id]
)->fetchAll();
$kpi_late_count = count($late_rows);
$kpi_late_names = array_slice(array_map(static fn($r) => $r['supplier_name'], $late_rows), 0, 2);

// ── KPI: разширен режим (7д / 30д / 90д) ──
$kpi_periods = [];
foreach ([7, 30, 90] as $win) {
    $row = DB::run(
        "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total
           FROM deliveries
          WHERE tenant_id=? AND store_id=?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND status NOT IN ('voided','superseded')",
        [$tenant_id, $store_id, $win]
    )->fetch();
    $kpi_periods[$win] = [
        'cnt'   => (int)($row['cnt']   ?? 0),
        'total' => (float)($row['total'] ?? 0),
    ];
}

// ── Status filter (URL param `status`) ──
$status_filter = $_GET['status'] ?? 'all';
$status_map = [
    'all'      => null,
    'draft'    => "d.status = 'draft'",
    'sent'     => "d.status = 'pending'",
    'pending'  => "d.status = 'reviewing'",
    'received' => "d.status = 'committed'",
    'late'     => "d.status IN ('draft','pending','reviewing')
                    AND d.delivered_at IS NULL
                    AND d.created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)",
];
$status_clause = $status_map[$status_filter] ?? null;

// ── Status counts (за бейджовете на табовете) ──
$status_counts = [
    'all'      => 0, 'draft' => 0, 'sent' => 0,
    'pending'  => 0, 'received' => 0, 'late' => 0,
];
$status_counts['all'] = (int)DB::run(
    "SELECT COUNT(*) FROM deliveries
       WHERE tenant_id=? AND store_id=? AND status NOT IN ('voided','superseded')",
    [$tenant_id, $store_id]
)->fetchColumn();
foreach (['draft' => 'draft', 'sent' => 'pending', 'pending' => 'reviewing', 'received' => 'committed'] as $key => $db_status) {
    $status_counts[$key] = (int)DB::run(
        "SELECT COUNT(*) FROM deliveries WHERE tenant_id=? AND store_id=? AND status=?",
        [$tenant_id, $store_id, $db_status]
    )->fetchColumn();
}
$status_counts['late'] = (int)DB::run(
    "SELECT COUNT(*) FROM deliveries
       WHERE tenant_id=? AND store_id=?
         AND status IN ('draft','pending','reviewing')
         AND delivered_at IS NULL
         AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)",
    [$tenant_id, $store_id]
)->fetchColumn();

// ── Главен списък: последни доставки (с филтър) ──
$list_sql = "
    SELECT d.id, d.number, d.invoice_number, d.total, d.currency_code,
           d.status, d.payment_status, d.payment_due_date,
           d.delivered_at, d.created_at, d.has_mismatch,
           COALESCE(s.name, 'Без доставчик') AS supplier_name,
           (SELECT COUNT(*) FROM delivery_items di WHERE di.delivery_id = d.id) AS item_count,
           DATEDIFF(NOW(), d.created_at) AS age_days
      FROM deliveries d
      LEFT JOIN suppliers s ON s.id = d.supplier_id
     WHERE d.tenant_id = ? AND d.store_id = ?
       AND d.status NOT IN ('voided','superseded')
";
if ($status_clause) {
    $list_sql .= " AND ($status_clause) ";
}
$list_sql .= " ORDER BY d.created_at DESC LIMIT 30";

$deliveries = DB::run($list_sql, [$tenant_id, $store_id])->fetchAll();

// ── AI insights (best-effort — таблицата може да я няма на dev среда) ──
$insights = [];
try {
    $insights = DB::run(
        "SELECT * FROM ai_insights
          WHERE tenant_id = ? AND module IN ('warehouse','deliveries','home')
            AND (expires_at IS NULL OR expires_at > NOW())
          ORDER BY FIELD(urgency,'critical','warning','info','passive'), created_at DESC
          LIMIT 6",
        [$tenant_id]
    )->fetchAll();
} catch (Throwable $e) {
    $insights = [];
}

// ── Helper: status → human label + tag class ──
function rmsDeliveryStatusTag(array $d): array {
    if ($d['status'] === 'committed') {
        return ['cls' => 'received', 'label' => 'ПОЛУЧЕНА'];
    }
    if ($d['status'] === 'draft') {
        return ['cls' => 'pending', 'label' => 'ЧЕРНОВА'];
    }
    if ($d['status'] === 'pending') {
        return ['cls' => 'pending', 'label' => 'ИЗПРАТЕНА'];
    }
    if ($d['status'] === 'reviewing') {
        return ['cls' => 'pending', 'label' => 'ЧАКАЩА'];
    }
    return ['cls' => 'pending', 'label' => mb_strtoupper((string)$d['status'])];
}

// Helper: SVG icon by status (възпроизвежда orb-овете от P14b мокъп).
function rmsDeliveryStatusIcon(string $cls): string {
    if ($cls === 'received') {
        return '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
    }
    return '<svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Доставки · RunMyStore.AI</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<script>(function(){try{var s=localStorage.getItem('rms_theme')||'light';document.documentElement.setAttribute('data-theme',s);}catch(_){document.documentElement.setAttribute('data-theme','light');}})();</script>

<style>
/* P14 / P14b — 1:1 от mockups (виж docs/LAYOUT_SHELL_LAW.md §1, §3). */
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{min-height:100%}
body{font-family:'Montserrat',sans-serif;overflow-x:hidden}
button,input,a,select,textarea{font-family:inherit;color:inherit}
button{background:none;border:none;cursor:pointer}
a{text-decoration:none}

:root{
  --hue1:255;--hue2:222;--hue3:180;
  --radius:22px;--radius-sm:14px;--radius-pill:999px;--radius-icon:50%;
  --border:1px;
  --ease:cubic-bezier(0.5,1,0.89,1);
  --ease-spring:cubic-bezier(0.34,1.56,0.64,1);
  --dur:250ms;--press:0.97;
  --font:'Montserrat',sans-serif;
  --font-mono:'DM Mono',ui-monospace,monospace;
  --z-aurora:0;--z-content:5;--z-shine:1;--z-glow:3;
}
:root:not([data-theme]),:root[data-theme="light"]{
  --bg-main:#e0e5ec;--surface:#e0e5ec;--surface-2:#d1d9e6;
  --border-color:transparent;
  --text:#2d3748;--text-muted:#64748b;--text-faint:#94a3b8;
  --shadow-light:#ffffff;--shadow-dark:#a3b1c6;
  --neu-d:8px;--neu-b:16px;--neu-d-s:4px;--neu-b-s:8px;
  --shadow-card:var(--neu-d) var(--neu-d) var(--neu-b) var(--shadow-dark),calc(var(--neu-d) * -1) calc(var(--neu-d) * -1) var(--neu-b) var(--shadow-light);
  --shadow-card-sm:var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark),calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);
  --shadow-pressed:inset var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark),inset calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);
  --accent:oklch(0.62 0.22 285);--accent-2:oklch(0.65 0.25 305);--accent-3:oklch(0.78 0.18 195);
  --magic:oklch(0.65 0.25 305);
  --aurora-blend:multiply;--aurora-opacity:0.32;
}
:root[data-theme="dark"]{
  --bg-main:#08090d;--surface:hsl(220,25%,4.8%);--surface-2:hsl(220,25%,8%);
  --border-color:hsl(var(--hue2),12%,20%);
  --text:#f1f5f9;--text-muted:rgba(255,255,255,0.6);--text-faint:rgba(255,255,255,0.4);
  --shadow-card:hsl(var(--hue2) 50% 2%) 0 10px 16px -8px,hsl(var(--hue2) 50% 4%) 0 20px 36px -14px;
  --shadow-card-sm:hsl(var(--hue2) 50% 2%) 0 4px 8px -2px;
  --shadow-pressed:inset 0 2px 4px hsl(var(--hue2) 50% 2%);
  --accent:hsl(var(--hue1),80%,65%);--accent-2:hsl(var(--hue2),80%,65%);--accent-3:hsl(var(--hue3),70%,55%);
  --magic:hsl(280,70%,65%);
  --aurora-blend:plus-lighter;--aurora-opacity:0.35;
}
:root:not([data-theme]) body,[data-theme="light"] body{background:var(--bg-main);color:var(--text)}
[data-theme="dark"] body{
  background:radial-gradient(ellipse 800px 500px at 20% 10%,hsl(var(--hue1) 60% 35% / .22) 0%,transparent 60%),radial-gradient(ellipse 700px 500px at 85% 85%,hsl(var(--hue2) 60% 35% / .22) 0%,transparent 60%),linear-gradient(180deg,#0a0b14 0%,#050609 100%);
  background-attachment:fixed;color:var(--text);
}

@keyframes auroraDrift{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(30px,-20px) scale(1.05)}66%{transform:translate(-20px,30px) scale(0.95)}}
@keyframes conicSpin{to{transform:rotate(360deg)}}
@keyframes orbSpin{to{transform:rotate(360deg)}}
@keyframes fadeInUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 hsl(0 70% 50% / 0.5)}50%{box-shadow:0 0 0 6px hsl(0 70% 50% / 0)}}
@keyframes rmsBrandShimmer{0%{background-position:0% center}100%{background-position:200% center}}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation:none !important;transition:none !important}}

.aurora{position:fixed;inset:0;overflow:hidden;pointer-events:none;z-index:var(--z-aurora)}
.aurora-blob{position:absolute;border-radius:50%;filter:blur(60px);opacity:var(--aurora-opacity);mix-blend-mode:var(--aurora-blend);animation:auroraDrift 20s ease-in-out infinite}
.aurora-blob:nth-child(1){width:280px;height:280px;background:hsl(var(--hue1),80%,60%);top:-60px;left:-80px}
.aurora-blob:nth-child(2){width:240px;height:240px;background:hsl(var(--hue3),70%,60%);top:35%;right:-100px;animation-delay:4s}
.aurora-blob:nth-child(3){width:200px;height:200px;background:hsl(var(--hue2),80%,60%);bottom:80px;left:-50px;animation-delay:8s}

/* ─── Header (canonical 7 елемента — markup идва от partials/header.php) ─── */
.rms-header{position:sticky;top:0;z-index:50;height:56px;padding:0 16px;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--border-color);padding-top:env(safe-area-inset-top,0)}
[data-theme="light"] .rms-header,:root:not([data-theme]) .rms-header{background:var(--bg-main);box-shadow:0 4px 12px rgba(163,177,198,0.15)}
[data-theme="dark"] .rms-header{background:hsl(220 25% 4.8% / 0.85);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
.rms-brand{position:relative;font-size:15px;font-weight:900;letter-spacing:0.10em;background:linear-gradient(90deg,hsl(var(--hue1) 80% 60%),hsl(var(--hue2) 80% 60%),hsl(var(--hue3) 70% 55%),hsl(var(--hue2) 80% 60%),hsl(var(--hue1) 80% 60%));background-size:200% auto;-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;animation:rmsBrandShimmer 4s linear infinite;filter:drop-shadow(0 0 12px hsl(var(--hue1) 70% 50% / 0.4))}
.rms-plan-badge{position:relative;padding:5px 12px;border-radius:var(--radius-pill);font-family:var(--font-mono);font-size:9px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--text);border:1px solid var(--border-color);overflow:hidden}
[data-theme="light"] .rms-plan-badge,:root:not([data-theme]) .rms-plan-badge{background:var(--surface);box-shadow:var(--shadow-card-sm);border:none}
[data-theme="dark"] .rms-plan-badge{background:hsl(220 25% 8% / 0.7);backdrop-filter:blur(8px)}
.rms-plan-badge::before{content:'';position:absolute;inset:-1px;border-radius:inherit;padding:1.5px;background:conic-gradient(from 0deg,hsl(var(--hue1) 80% 60%),hsl(var(--hue2) 80% 60%),hsl(var(--hue3) 70% 60%),hsl(var(--hue1) 80% 60%));-webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);-webkit-mask-composite:xor;mask-composite:exclude;animation:conicSpin 3s linear infinite;opacity:0.6;pointer-events:none}
.rms-header-spacer{flex:1}
.rms-icon-btn{width:40px;height:40px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0;border:1px solid var(--border-color);transition:box-shadow var(--dur) var(--ease),transform var(--dur) var(--ease)}
[data-theme="light"] .rms-icon-btn,:root:not([data-theme]) .rms-icon-btn{background:var(--surface);box-shadow:var(--shadow-card-sm);border:none}
[data-theme="light"] .rms-icon-btn:active,:root:not([data-theme]) .rms-icon-btn:active{box-shadow:var(--shadow-pressed)}
[data-theme="dark"] .rms-icon-btn{background:hsl(220 25% 8% / 0.7);backdrop-filter:blur(8px);box-shadow:0 4px 12px hsl(var(--hue2) 50% 4%)}
.rms-icon-btn:active{transform:scale(var(--press))}
.rms-icon-btn svg{width:18px;height:18px;stroke:var(--text);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.rms-back-btn{width:36px;height:36px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0}
[data-theme="light"] .rms-back-btn,:root:not([data-theme]) .rms-back-btn{background:var(--surface);box-shadow:var(--shadow-card-sm)}
[data-theme="dark"] .rms-back-btn{background:hsl(220 25% 8% / 0.7)}
.rms-back-btn svg{width:14px;height:14px;stroke:var(--text);fill:none;stroke-width:2.5}
.rms-logout-dd{position:absolute;right:12px;top:60px;padding:8px 14px;border-radius:var(--radius-sm);background:var(--surface);box-shadow:var(--shadow-card-sm);font-size:12px;font-weight:700;color:var(--text);z-index:60}

/* ─── Subbar (LAYOUT_SHELL_LAW §1.2) ─── */
.rms-subbar{display:flex;align-items:center;gap:8px;padding:8px 12px 0;position:relative;z-index:5;max-width:480px;margin:0 auto}
.rms-shop-chip{display:inline-flex;align-items:center;gap:4px;padding:6px 10px;border-radius:var(--radius-pill);font-family:var(--font-mono);font-size:10px;font-weight:800;letter-spacing:0.04em;color:var(--text);border:1px solid var(--border-color)}
[data-theme="light"] .rms-shop-chip,:root:not([data-theme]) .rms-shop-chip{background:var(--surface);box-shadow:var(--shadow-card-sm);border:none}
[data-theme="dark"] .rms-shop-chip{background:hsl(220 25% 8% / 0.6)}
.rms-shop-chip svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2}
.rms-breadcrumb{font-family:var(--font-mono);font-size:10px;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted)}
.rms-subbar-spacer{flex:1}
.rms-mode-toggle{display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border-radius:var(--radius-pill);font-family:var(--font-mono);font-size:10px;font-weight:700;letter-spacing:0.04em;color:var(--text-muted);border:1px solid var(--border-color)}
[data-theme="light"] .rms-mode-toggle,:root:not([data-theme]) .rms-mode-toggle{background:var(--surface);box-shadow:var(--shadow-card-sm);border:none}
[data-theme="dark"] .rms-mode-toggle{background:hsl(220 25% 8% / 0.6)}
.rms-mode-toggle svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2}
.rms-sale-pill{display:inline-flex;align-items:center;padding:8px 14px;border-radius:var(--radius-pill);font-family:var(--font);font-size:11px;font-weight:800;letter-spacing:0.04em;text-transform:uppercase;color:white;background:linear-gradient(135deg,hsl(38 80% 55%),hsl(28 75% 50%));box-shadow:0 4px 12px hsl(38 80% 50% / 0.4);transition:transform var(--dur) var(--ease)}
.rms-sale-pill:active{transform:scale(var(--press))}

/* ─── App container ─── */
.app{position:relative;z-index:var(--z-content);max-width:480px;margin:0 auto;padding:12px 12px calc(80px + env(safe-area-inset-bottom,0))}
.app.with-bottom-nav{padding-bottom:calc(92px + env(safe-area-inset-bottom,0))}

/* ─── Glass base (sacred neon glass) ─── */
.glass{position:relative;border-radius:var(--radius);border:var(--border) solid var(--border-color);isolation:isolate}
.glass.sm{border-radius:var(--radius-sm)}
.glass .shine,.glass .glow{--hue:var(--hue1)}
.glass .shine-bottom,.glass .glow-bottom{--hue:var(--hue2);--conic:135deg}
[data-theme="light"] .glass,:root:not([data-theme]) .glass{background:var(--surface);box-shadow:var(--shadow-card);border:none}
:root:not([data-theme]) .glass .shine,:root:not([data-theme]) .glass .glow,
[data-theme="light"] .glass .shine,[data-theme="light"] .glass .glow{display:none}
[data-theme="dark"] .glass{background:linear-gradient(235deg,hsl(var(--hue1) 50% 10% / .8),hsl(var(--hue1) 50% 10% / 0) 33%),linear-gradient(45deg,hsl(var(--hue2) 50% 10% / .8),hsl(var(--hue2) 50% 10% / 0) 33%),linear-gradient(hsl(220 25% 4.8% / .78));backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);box-shadow:var(--shadow-card)}
[data-theme="dark"] .glass .shine{pointer-events:none;border-radius:0;border-top-right-radius:inherit;border-bottom-left-radius:inherit;border:1px solid transparent;width:75%;aspect-ratio:1;display:block;position:absolute;right:calc(var(--border) * -1);top:calc(var(--border) * -1);z-index:var(--z-shine);background:conic-gradient(from var(--conic,-45deg) at center in oklch,transparent 12%,hsl(var(--hue),80%,60%),transparent 50%) border-box;mask:linear-gradient(transparent),linear-gradient(black);mask-clip:padding-box,border-box;mask-composite:subtract}
[data-theme="dark"] .glass .shine.shine-bottom{right:auto;top:auto;left:calc(var(--border) * -1);bottom:calc(var(--border) * -1)}
[data-theme="dark"] .glass .glow{pointer-events:none;border-top-right-radius:calc(var(--radius) * 2.5);border-bottom-left-radius:calc(var(--radius) * 2.5);border:calc(var(--radius) * 1.25) solid transparent;inset:calc(var(--radius) * -2);width:75%;aspect-ratio:1;display:block;position:absolute;left:auto;bottom:auto;background:conic-gradient(from var(--conic,-45deg) at center in oklch,hsl(var(--hue),80%,60% / .5) 12%,transparent 50%);filter:blur(12px) saturate(1.25);mix-blend-mode:plus-lighter;z-index:var(--z-glow);opacity:0.6}
[data-theme="dark"] .glass .glow.glow-bottom{inset:auto;left:calc(var(--radius) * -2);bottom:calc(var(--radius) * -2)}
[data-theme="dark"] .lb-card .glow,[data-theme="dark"] .rich-card .glow{inset:calc(var(--radius-sm) * -1);opacity:0.35}
[data-theme="dark"] .lb-card .glow.glow-bottom,[data-theme="dark"] .rich-card .glow.glow-bottom{inset:auto;left:calc(var(--radius-sm) * -1);bottom:calc(var(--radius-sm) * -1)}
[data-theme="dark"] .lb-card .shine,[data-theme="dark"] .rich-card .shine{opacity:0.7}
.glass.q1{--hue:0}.glass.q2{--hue:280}.glass.q3{--hue:145}.glass.q4{--hue:175}.glass.q5{--hue:38}.glass.q6{--hue:220}.glass.qd{--hue:var(--hue1)}.glass.qm{--hue:305}

/* ─── Лесен режим: top-row (днес + закъснели) ─── */
.top-row{display:grid;grid-template-columns:1.4fr 1fr;gap:10px;margin-bottom:12px;animation:fadeInUp 0.6s var(--ease-spring) both}
.cell{padding:12px 14px}
.cell>*{position:relative;z-index:5}
.cell-header-row{display:flex;align-items:center;justify-content:space-between;gap:6px}
.cell-label{font-family:var(--font-mono);font-size:9px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-muted)}
.cell-numrow{display:flex;align-items:baseline;gap:4px;margin-top:6px}
.cell-num{font-size:24px;font-weight:800;letter-spacing:-0.02em;line-height:1}
.cell-cur{font-family:var(--font-mono);font-size:11px;font-weight:700;color:var(--text-muted)}
.cell-meta{font-size:11px;font-weight:600;color:var(--text-muted);margin-top:4px;line-height:1.2}
.cell.q1 .cell-num{color:hsl(0 70% 50%)}
[data-theme="dark"] .cell.q1 .cell-num{color:hsl(0 80% 70%)}

/* ─── Op-btn „Получи доставка" ─── */
.op-btn{position:relative;width:100%;padding:18px 16px;margin-bottom:10px;cursor:pointer;display:flex;align-items:center;gap:14px;text-align:left;isolation:isolate;animation:fadeInUp 0.5s var(--ease-spring) 0.05s both}
.op-btn>*{position:relative;z-index:5}
.op-btn-ic{width:56px;height:56px;border-radius:var(--radius-sm);display:grid;place-items:center;flex-shrink:0;background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 6px 18px hsl(var(--hue1) 80% 40% / 0.35)}
[data-theme="light"] .op-btn-ic,:root:not([data-theme]) .op-btn-ic{background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 6px 18px oklch(0.62 0.22 285 / 0.4)}
.op-btn-ic svg{width:28px;height:28px;stroke:white;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.op-btn-body{flex:1;min-width:0}
.op-btn-title{font-size:17px;font-weight:800;letter-spacing:-0.02em;color:var(--text);margin-bottom:4px}
.op-btn-sub{font-family:var(--font-mono);font-size:11px;font-weight:700;color:var(--accent);letter-spacing:0.04em;line-height:1.3;text-transform:uppercase}
[data-theme="dark"] .op-btn-sub{color:hsl(var(--hue1) 80% 70%)}

/* ─── KPI strip (разширен режим: 7д / 30д / 90д) ─── */
.kpi-strip{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:14px;animation:fadeInUp 0.6s var(--ease-spring) both}
.kpi-cell{padding:11px 10px}
.kpi-cell>*{position:relative;z-index:5}
.kpi-label{font-family:var(--font-mono);font-size:8.5px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px}
.kpi-num{font-size:18px;font-weight:800;letter-spacing:-0.02em;line-height:1}
.kpi-cur{font-family:var(--font-mono);font-size:9.5px;font-weight:700;color:var(--text-muted);margin-left:2px}
.kpi-meta{font-family:var(--font-mono);font-size:9px;font-weight:700;color:var(--text-muted);margin-top:3px;letter-spacing:0.04em}

/* ─── Status tabs (разширен) ─── */
.status-tabs{display:flex;gap:6px;padding:4px;border-radius:var(--radius-pill);margin-bottom:12px;overflow-x:auto}
[data-theme="light"] .status-tabs,:root:not([data-theme]) .status-tabs{background:var(--surface);box-shadow:var(--shadow-pressed)}
[data-theme="dark"] .status-tabs{background:hsl(220 25% 8%);border:1px solid hsl(var(--hue2) 12% 20%)}
.status-tab{flex:1;min-width:max-content;padding:8px 10px;border-radius:var(--radius-pill);font-size:10.5px;font-weight:800;letter-spacing:-0.01em;color:var(--text-muted);display:inline-flex;align-items:center;justify-content:center;gap:4px;cursor:pointer;transition:all var(--dur) var(--ease);white-space:nowrap}
.status-tab .count{font-family:var(--font-mono);font-size:9px;font-weight:800;padding:2px 5px;border-radius:var(--radius-pill);background:hsl(var(--hue2) 20% 80%);color:var(--text-muted)}
[data-theme="dark"] .status-tab .count{background:hsl(220 25% 14%)}
.status-tab.active{color:white;background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 4px 12px hsl(var(--hue1) 80% 50% / 0.35)}
.status-tab.active .count{background:rgba(255,255,255,0.25);color:white}

/* ─── AI signals (lb-card) ─── */
.lb-header{display:flex;align-items:center;justify-content:space-between;margin:14px 4px 10px;position:relative;z-index:5}
.lb-title{display:flex;align-items:center;gap:8px}
.lb-title-orb{width:24px;height:24px;border-radius:var(--radius-icon);background:conic-gradient(from 0deg,hsl(var(--hue1) 80% 60%),hsl(280 80% 60%),hsl(var(--hue3) 70% 60%),hsl(var(--hue1) 80% 60%));box-shadow:0 0 12px hsl(var(--hue1) 80% 50% / 0.4);position:relative;animation:orbSpin 5s linear infinite}
.lb-title-orb::after{content:'';position:absolute;inset:4px;border-radius:var(--radius-icon);background:var(--bg-main)}
[data-theme="dark"] .lb-title-orb::after{background:#08090d}
.lb-title-text{font-size:13px;font-weight:800;letter-spacing:-0.01em}
.lb-count{font-family:var(--font-mono);font-size:10px;font-weight:700;color:var(--text-muted)}
.lb-card{padding:12px 14px;margin-bottom:8px;cursor:pointer;transition:box-shadow var(--dur) var(--ease)}
.lb-card>*{position:relative;z-index:5}
.lb-collapsed{display:flex;align-items:center;gap:10px}
.lb-emoji-orb{width:28px;height:28px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0}
.lb-emoji-orb svg{width:14px;height:14px;fill:none;stroke-width:2}
.lb-card.q1 .lb-emoji-orb{background:hsl(0 50% 92%);box-shadow:var(--shadow-pressed)}
[data-theme="dark"] .lb-card.q1 .lb-emoji-orb{background:hsl(0 50% 12%)}
.lb-card.q1 .lb-emoji-orb svg{stroke:hsl(0 70% 50%)}
[data-theme="dark"] .lb-card.q1 .lb-emoji-orb svg{stroke:hsl(0 80% 70%)}
.lb-card.q2 .lb-emoji-orb{background:hsl(280 50% 92%);box-shadow:var(--shadow-pressed)}
[data-theme="dark"] .lb-card.q2 .lb-emoji-orb{background:hsl(280 50% 12%)}
.lb-card.q2 .lb-emoji-orb svg{stroke:hsl(280 70% 50%)}
[data-theme="dark"] .lb-card.q2 .lb-emoji-orb svg{stroke:hsl(280 70% 70%)}
.lb-card.q3 .lb-emoji-orb{background:hsl(145 50% 92%);box-shadow:var(--shadow-pressed)}
[data-theme="dark"] .lb-card.q3 .lb-emoji-orb{background:hsl(145 50% 12%)}
.lb-card.q3 .lb-emoji-orb svg{stroke:hsl(145 60% 45%)}
[data-theme="dark"] .lb-card.q3 .lb-emoji-orb svg{stroke:hsl(145 70% 65%)}
.lb-card.q5 .lb-emoji-orb{background:hsl(38 50% 92%);box-shadow:var(--shadow-pressed)}
[data-theme="dark"] .lb-card.q5 .lb-emoji-orb{background:hsl(38 50% 12%)}
.lb-card.q5 .lb-emoji-orb svg{stroke:hsl(38 80% 50%)}
[data-theme="dark"] .lb-card.q5 .lb-emoji-orb svg{stroke:hsl(38 90% 65%)}
.lb-card.q1.urgent .lb-emoji-orb{animation:pulse 1.8s ease-out infinite}
.lb-collapsed-content{flex:1;min-width:0}
.lb-fq-tag-mini{display:block;font-family:var(--font-mono);font-size:8.5px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-muted)}
.lb-collapsed-title{display:block;font-size:12px;font-weight:700;margin-top:2px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lb-expand-btn{width:24px;height:24px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0;border:1px solid var(--border-color);transition:transform 0.3s ease,box-shadow var(--dur) var(--ease)}
[data-theme="light"] .lb-expand-btn,:root:not([data-theme]) .lb-expand-btn{background:var(--surface);box-shadow:var(--shadow-card-sm);border:none}
[data-theme="dark"] .lb-expand-btn{background:hsl(220 25% 8%)}
.lb-expand-btn svg{width:11px;height:11px;stroke:var(--text-muted);fill:none;stroke-width:2.5}

/* ─── Rich card (разширен — delivery card с прогрес) ─── */
.rich-card{position:relative;padding:12px 14px;margin-bottom:10px;cursor:pointer;isolation:isolate;animation:fadeInUp 0.5s var(--ease-spring) both;text-decoration:none;color:inherit;display:block}
.rich-card>*{position:relative;z-index:5}
.rc-head{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.rc-orb{width:38px;height:38px;border-radius:var(--radius-sm);display:grid;place-items:center;flex-shrink:0;position:relative}
.rich-card.q1 .rc-orb{background:linear-gradient(135deg,hsl(0 75% 55%),hsl(0 80% 45%));box-shadow:0 4px 12px hsl(0 75% 50% / 0.4)}
.rich-card.q3 .rc-orb{background:linear-gradient(135deg,hsl(145 65% 50%),hsl(155 70% 45%));box-shadow:0 4px 12px hsl(145 65% 45% / 0.4)}
.rich-card.q5 .rc-orb{background:linear-gradient(135deg,hsl(38 88% 55%),hsl(28 90% 50%));box-shadow:0 4px 12px hsl(38 88% 50% / 0.4)}
.rich-card.qd .rc-orb{background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 4px 12px hsl(var(--hue1) 80% 40% / 0.4)}
.rc-orb svg{width:18px;height:18px;stroke:white;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.rich-card.q1.urgent .rc-orb::before{content:'';position:absolute;inset:-4px;border-radius:var(--radius-sm);border:2px solid hsl(0 75% 55%);opacity:0.6;animation:pulse 1.6s ease-out infinite}
.rc-head-text{flex:1;min-width:0}
.rc-id{font-family:var(--font-mono);font-size:9.5px;font-weight:800;letter-spacing:0.06em;color:var(--text-muted);margin-bottom:2px}
.rc-supplier{font-size:14px;font-weight:800;letter-spacing:-0.02em;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rc-arrow{width:24px;height:24px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0}
[data-theme="light"] .rc-arrow,:root:not([data-theme]) .rc-arrow{background:var(--surface);box-shadow:var(--shadow-card-sm)}
[data-theme="dark"] .rc-arrow{background:hsl(220 25% 8%)}
.rc-arrow svg{width:11px;height:11px;stroke:var(--text-muted);fill:none;stroke-width:2.5}
.rc-data{display:grid;grid-template-columns:1fr 1fr;gap:6px 12px;margin-bottom:10px}
.rc-data-item{display:flex;flex-direction:column;gap:1px}
.rc-data-label{font-family:var(--font-mono);font-size:8px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-faint)}
.rc-data-val{font-size:12px;font-weight:700;color:var(--text);letter-spacing:-0.01em}
.rc-data-val.late{color:hsl(0 70% 50%)}
[data-theme="dark"] .rc-data-val.late{color:hsl(0 80% 70%)}
.rc-data-val.amount{font-family:var(--font-mono);font-weight:800}
.rc-tags{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px}
.rc-tag{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:var(--radius-pill);font-family:var(--font-mono);font-size:9px;font-weight:800;letter-spacing:0.06em;text-transform:uppercase}
.rc-tag.pending{background:hsl(38 80% 90%);color:hsl(38 80% 35%)}
[data-theme="dark"] .rc-tag.pending{background:hsl(38 50% 18% / 0.6);color:hsl(38 90% 70%)}
.rc-tag.late{background:hsl(0 80% 92%);color:hsl(0 70% 40%)}
[data-theme="dark"] .rc-tag.late{background:hsl(0 50% 18% / 0.6);color:hsl(0 80% 70%)}
.rc-tag.received{background:hsl(145 60% 90%);color:hsl(145 70% 30%)}
[data-theme="dark"] .rc-tag.received{background:hsl(145 50% 18% / 0.6);color:hsl(145 70% 70%)}
.rc-progress{height:6px;border-radius:var(--radius-pill);overflow:hidden;margin-top:4px;position:relative}
[data-theme="light"] .rc-progress,:root:not([data-theme]) .rc-progress{background:var(--surface-2);box-shadow:var(--shadow-pressed)}
[data-theme="dark"] .rc-progress{background:hsl(220 25% 4%)}
.rc-progress-fill{position:absolute;top:0;bottom:0;left:0;border-radius:var(--radius-pill);background:linear-gradient(90deg,var(--accent),var(--accent-2));transition:width var(--dur) var(--ease)}
.rich-card.q1 .rc-progress-fill{background:linear-gradient(90deg,hsl(0 75% 55%),hsl(0 80% 45%))}
.rich-card.q3 .rc-progress-fill{background:linear-gradient(90deg,hsl(145 65% 50%),hsl(155 70% 45%))}
.rich-card.q5 .rc-progress-fill{background:linear-gradient(90deg,hsl(38 88% 55%),hsl(28 90% 50%))}

/* ─── Empty state ─── */
.empty-state{padding:24px;text-align:center;border-radius:var(--radius-sm);margin-bottom:12px}
[data-theme="light"] .empty-state,:root:not([data-theme]) .empty-state{background:var(--surface);box-shadow:var(--shadow-card-sm)}
[data-theme="dark"] .empty-state{background:hsl(220 25% 6%);border:1px solid hsl(var(--hue2) 12% 18%)}
.empty-title{font-size:13px;font-weight:800;color:var(--text);margin-bottom:6px}
.empty-sub{font-size:11px;font-weight:600;color:var(--text-muted);line-height:1.4}

/* ─── Section label ─── */
.sec-label{font-family:var(--font-mono);font-size:9.5px;font-weight:800;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-muted);margin:14px 4px 8px}

/* ─── Mode toggle row (вътрешен, само в разширен) ─── */
.lb-mode-row{display:flex;justify-content:flex-end;margin:0 0 12px;padding:0 4px}
.lb-mode-toggle{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--radius-pill);font-family:var(--font-mono);font-size:10px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);cursor:pointer}
[data-theme="light"] .lb-mode-toggle,:root:not([data-theme]) .lb-mode-toggle{background:var(--surface);box-shadow:var(--shadow-card-sm)}
[data-theme="dark"] .lb-mode-toggle{background:hsl(220 25% 8% / 0.7);backdrop-filter:blur(8px)}
.lb-mode-toggle svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.5}

/* ─── Bottom nav (P11 detailed canon) — само в разширен ─── */
.rms-bottom-nav{position:fixed;left:12px;right:12px;bottom:12px;z-index:50;height:64px;display:grid;grid-template-columns:repeat(4,1fr);border-radius:var(--radius);border:1px solid var(--border-color);padding-bottom:env(safe-area-inset-bottom,0);max-width:456px;margin:0 auto}
[data-theme="light"] .rms-bottom-nav,:root:not([data-theme]) .rms-bottom-nav{background:var(--surface);box-shadow:var(--shadow-card);border:none}
[data-theme="dark"] .rms-bottom-nav{background:linear-gradient(235deg,hsl(var(--hue1) 50% 10% / .8),hsl(var(--hue1) 50% 10% / 0) 33%),linear-gradient(45deg,hsl(var(--hue2) 50% 10% / .8),hsl(var(--hue2) 50% 10% / 0) 33%),linear-gradient(hsl(220 25% 4.8% / .9));backdrop-filter:blur(12px);box-shadow:var(--shadow-card)}
.rms-nav-tab{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;color:var(--text-muted);font-size:10px;font-weight:700;position:relative;transition:color var(--dur) var(--ease)}
.rms-nav-tab svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:2;transition:transform var(--dur) var(--ease-spring)}
.rms-nav-tab:active svg{transform:scale(0.85)}
.rms-nav-tab.active{color:var(--accent)}
.rms-nav-tab.active::before{content:'';position:absolute;top:6px;left:50%;transform:translateX(-50%);width:32px;height:4px;background:var(--accent);border-radius:var(--radius-pill)}
[data-theme="dark"] .rms-nav-tab.active::before{box-shadow:0 0 12px var(--accent)}

</style>
</head>
<body class="mode-<?= htmlspecialchars($mode) ?>">

<div class="aurora" aria-hidden="true">
  <div class="aurora-blob"></div><div class="aurora-blob"></div><div class="aurora-blob"></div>
</div>

<?php include __DIR__ . '/partials/header.php'; ?>

<!-- ═══ rms-subbar (LAYOUT_SHELL_LAW §1.2) ═══ -->
<div class="rms-subbar">
    <a class="rms-shop-chip" href="settings.php#stores" title="Превключи магазин">
        <svg viewBox="0 0 24 24"><path d="M3 9h18M3 9v11a1 1 0 001 1h16a1 1 0 001-1V9M3 9l3-6h12l3 6"/></svg>
        <span><?= htmlspecialchars(mb_strtoupper($store_name)) ?></span>
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </a>
    <span class="rms-breadcrumb">ДОСТАВКИ</span>
    <div class="rms-subbar-spacer"></div>
    <?php if ($mode === 'simple'): ?>
        <a class="rms-mode-toggle" href="?mode=detailed" title="Превключи към разширен режим">
            <span>Разширен</span>
            <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a class="rms-sale-pill" href="sale.php" title="Продажба">
            <span>Продажба</span>
        </a>
    <?php else: ?>
        <a class="rms-mode-toggle" href="?mode=simple" title="Превключи към лесен режим">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            <span>Лесен</span>
        </a>
    <?php endif; ?>
</div>

<main class="app<?= $mode === 'detailed' ? ' with-bottom-nav' : '' ?>">

<?php if ($mode === 'simple'): ?>
    <!-- ═══════════════════════════════════════════════════════════
         ЛЕСЕН РЕЖИМ (Пешо) — еталон mockups/P14_deliveries.html
         ═══════════════════════════════════════════════════════════ -->

    <!-- TOP ROW: Доставки днес + Закъснели -->
    <div class="top-row">
        <div class="glass sm cell qd">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <span class="glow"></span><span class="glow glow-bottom"></span>
            <div class="cell-header-row">
                <div class="cell-label">ДНЕС · <?= htmlspecialchars(mb_strtoupper($store_name)) ?></div>
            </div>
            <div class="cell-numrow">
                <span class="cell-num"><?= $kpi_today_count ?></span>
                <span class="cell-cur">бр</span>
            </div>
            <div class="cell-meta">
                <?= fmtMoney($kpi_today_total, $currency) ?>
                <?php if ($kpi_today_suppliers > 0): ?>
                    · <?= $kpi_today_suppliers ?> доставчик<?= $kpi_today_suppliers === 1 ? '' : 'а' ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass sm cell q1">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <span class="glow"></span><span class="glow glow-bottom"></span>
            <div class="cell-header-row">
                <div class="cell-label">ЗАКЪСНЕЛИ</div>
            </div>
            <div class="cell-numrow">
                <span class="cell-num"><?= $kpi_late_count ?></span>
                <span class="cell-cur">бр</span>
            </div>
            <div class="cell-meta">
                <?= $kpi_late_count > 0
                    ? htmlspecialchars(implode(' · ', $kpi_late_names))
                    : 'Няма закъснели' ?>
            </div>
        </div>
    </div>

    <!-- Голям бутон „Получи доставка" — Phase B ще отвори receive sheet -->
    <a class="glass op-btn qd" href="delivery.php?action=new">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <span class="op-btn-ic">
            <svg viewBox="0 0 24 24">
                <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/>
                <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>
                <line x1="12" y1="3" x2="12" y2="11"/>
                <polyline points="9 8 12 11 15 8"/>
            </svg>
        </span>
        <div class="op-btn-body">
            <div class="op-btn-title">Получи доставка</div>
            <div class="op-btn-sub">СНИМАЙ · КАЖИ · СКЕНИРАЙ</div>
        </div>
    </a>

    <!-- AI забелязва (от ai_insights, ако има) -->
    <?php if (!empty($insights)): ?>
        <div class="lb-header">
            <div class="lb-title">
                <div class="lb-title-orb"></div>
                <span class="lb-title-text">AI забелязва</span>
            </div>
            <span class="lb-count"><?= count($insights) ?> неща · <?= date('H:i') ?></span>
        </div>
        <?php foreach (array_slice($insights, 0, 4) as $ins):
            $urgency = $ins['urgency'] ?? 'info';
            $q = $urgency === 'critical' ? 'q1 urgent'
               : ($urgency === 'warning' ? 'q5'
               : ($urgency === 'info' ? 'q3' : 'q2'));
            $tag = $urgency === 'critical' ? 'СПЕШНО'
                 : ($urgency === 'warning' ? 'ВНИМАНИЕ'
                 : 'AI ЗАБЕЛЯЗВА');
        ?>
            <div class="glass sm lb-card <?= $q ?>">
                <span class="shine"></span><span class="shine shine-bottom"></span>
                <span class="glow"></span><span class="glow glow-bottom"></span>
                <div class="lb-collapsed">
                    <span class="lb-emoji-orb">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </span>
                    <div class="lb-collapsed-content">
                        <span class="lb-fq-tag-mini"><?= htmlspecialchars($tag) ?></span>
                        <span class="lb-collapsed-title"><?= htmlspecialchars((string)($ins['title'] ?? '')) ?></span>
                    </div>
                    <button class="lb-expand-btn" aria-label="Разгърни" type="button">
                        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Последни доставки (rich cards, max 5 в лесен) -->
    <div class="sec-label">Последни доставки</div>
    <?php if (empty($deliveries)): ?>
        <div class="empty-state">
            <div class="empty-title">Все още няма доставки</div>
            <div class="empty-sub">Натисни „Получи доставка" и снимай първата фактура.</div>
        </div>
    <?php else: ?>
        <?php foreach (array_slice($deliveries, 0, 5) as $d):
            $tag    = rmsDeliveryStatusTag($d);
            $is_late = $d['status'] !== 'committed' && empty($d['delivered_at']) && (int)$d['age_days'] >= 1;
            $q      = $is_late ? 'q1 urgent'
                    : ($tag['cls'] === 'received' ? 'q3'
                    : ($tag['cls'] === 'pending'  ? 'q5' : 'qd'));
            $tag_cls   = $is_late ? 'late' : $tag['cls'];
            $tag_label = $is_late ? 'ЗАКЪСНЯЛА ' . (int)$d['age_days'] . 'Д' : $tag['label'];
            $progress  = $d['status'] === 'committed' ? 100
                       : ($d['status'] === 'reviewing' ? 60
                       : ($d['status'] === 'pending' ? 30 : 10));
            $delivery_label = !empty($d['delivered_at'])
                ? 'Получена ' . date('d.m H:i', strtotime((string)$d['delivered_at']))
                : 'Очаквана ' . date('d.m', strtotime((string)$d['created_at']));
            $del_id = '#DEL-' . str_pad((string)$d['id'], 5, '0', STR_PAD_LEFT);
        ?>
            <a class="glass sm rich-card <?= $q ?>" href="delivery.php?id=<?= (int)$d['id'] ?>">
                <span class="shine"></span><span class="shine shine-bottom"></span>
                <span class="glow"></span><span class="glow glow-bottom"></span>
                <div class="rc-head">
                    <span class="rc-orb"><?= rmsDeliveryStatusIcon($tag['cls']) ?></span>
                    <div class="rc-head-text">
                        <div class="rc-id"><?= htmlspecialchars($del_id) ?></div>
                        <div class="rc-supplier"><?= htmlspecialchars($d['supplier_name']) ?></div>
                    </div>
                    <span class="rc-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></span>
                </div>
                <div class="rc-data">
                    <div class="rc-data-item">
                        <span class="rc-data-label">Артикули</span>
                        <span class="rc-data-val"><?= (int)$d['item_count'] ?> бр</span>
                    </div>
                    <div class="rc-data-item">
                        <span class="rc-data-label">Стойност</span>
                        <span class="rc-data-val amount"><?= fmtMoney((float)$d['total'], $currency) ?></span>
                    </div>
                </div>
                <div class="rc-tags">
                    <span class="rc-tag <?= $tag_cls ?>"><?= htmlspecialchars($tag_label) ?></span>
                </div>
                <div class="rc-progress"><div class="rc-progress-fill" style="width:<?= $progress ?>%"></div></div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

<?php else: /* detailed mode */ ?>
    <!-- ═══════════════════════════════════════════════════════════
         РАЗШИРЕН РЕЖИМ (Митко) — еталон mockups/P14b_deliveries_detailed_v5_BG.html
         ═══════════════════════════════════════════════════════════ -->

    <!-- KPI лента: 7д / 30д / 90д -->
    <div class="kpi-strip">
        <?php foreach ([7 => 'СЕДМИЦА', 30 => '30 ДНИ', 90 => '90 ДНИ'] as $win => $lbl):
            $row = $kpi_periods[$win];
            $q   = $win === 7 ? 'q3' : ($win === 30 ? 'q5' : 'qd');
        ?>
            <div class="glass sm kpi-cell <?= $q ?>">
                <span class="shine"></span><span class="shine shine-bottom"></span>
                <span class="glow"></span><span class="glow glow-bottom"></span>
                <div class="kpi-label"><?= htmlspecialchars($lbl) ?></div>
                <div>
                    <span class="kpi-num"><?= number_format($row['total'], 0, '.', ' ') ?></span>
                    <span class="kpi-cur"><?= htmlspecialchars($currency) ?></span>
                </div>
                <div class="kpi-meta"><?= $row['cnt'] ?> доставк<?= $row['cnt'] === 1 ? 'а' : 'и' ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Голям бутон „Получи доставка" -->
    <a class="glass op-btn qd" href="delivery.php?action=new">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <span class="op-btn-ic">
            <svg viewBox="0 0 24 24">
                <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/>
                <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>
                <line x1="12" y1="3" x2="12" y2="11"/>
                <polyline points="9 8 12 11 15 8"/>
            </svg>
        </span>
        <div class="op-btn-body">
            <div class="op-btn-title">Получи доставка</div>
            <div class="op-btn-sub">СНИМАЙ · КАЖИ · СКЕНИРАЙ</div>
        </div>
    </a>

    <!-- Status tabs (Всички / Чернови / Изпратени / Чакащи / Получени / Закъснели) -->
    <div class="status-tabs" role="tablist">
        <?php
        $tabs = [
            'all'      => 'Всички',
            'draft'    => 'Чернови',
            'sent'     => 'Изпратени',
            'pending'  => 'Чакащи',
            'received' => 'Получени',
            'late'     => 'Закъснели',
        ];
        foreach ($tabs as $key => $label):
            $active = $status_filter === $key;
        ?>
            <a class="status-tab<?= $active ? ' active' : '' ?>" href="?status=<?= htmlspecialchars($key) ?>" role="tab" aria-selected="<?= $active ? 'true' : 'false' ?>">
                <span><?= htmlspecialchars($label) ?></span>
                <span class="count"><?= (int)$status_counts[$key] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- AI забелязва -->
    <?php if (!empty($insights)): ?>
        <div class="lb-header">
            <div class="lb-title">
                <div class="lb-title-orb"></div>
                <span class="lb-title-text">AI забелязва</span>
            </div>
            <span class="lb-count"><?= count($insights) ?> неща · <?= date('H:i') ?></span>
        </div>
        <?php foreach (array_slice($insights, 0, 3) as $ins):
            $urgency = $ins['urgency'] ?? 'info';
            $q = $urgency === 'critical' ? 'q1 urgent'
               : ($urgency === 'warning' ? 'q5'
               : ($urgency === 'info' ? 'q3' : 'q2'));
            $tag = $urgency === 'critical' ? 'СПЕШНО'
                 : ($urgency === 'warning' ? 'ВНИМАНИЕ'
                 : 'AI ЗАБЕЛЯЗВА');
        ?>
            <div class="glass sm lb-card <?= $q ?>">
                <span class="shine"></span><span class="shine shine-bottom"></span>
                <span class="glow"></span><span class="glow glow-bottom"></span>
                <div class="lb-collapsed">
                    <span class="lb-emoji-orb">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </span>
                    <div class="lb-collapsed-content">
                        <span class="lb-fq-tag-mini"><?= htmlspecialchars($tag) ?></span>
                        <span class="lb-collapsed-title"><?= htmlspecialchars((string)($ins['title'] ?? '')) ?></span>
                    </div>
                    <button class="lb-expand-btn" aria-label="Разгърни" type="button">
                        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Списък: rich cards с прогрес бар -->
    <div class="sec-label">
        <?= $status_filter === 'all' ? 'Всички доставки' : ('Филтър: ' . htmlspecialchars($tabs[$status_filter] ?? '')) ?>
        · <?= count($deliveries) ?>
    </div>

    <?php if (empty($deliveries)): ?>
        <div class="empty-state">
            <div class="empty-title">Няма доставки в избрания филтър</div>
            <div class="empty-sub">Промени филтъра или натисни „Получи доставка".</div>
        </div>
    <?php else: ?>
        <?php foreach ($deliveries as $d):
            $tag    = rmsDeliveryStatusTag($d);
            $is_late = $d['status'] !== 'committed' && empty($d['delivered_at']) && (int)$d['age_days'] >= 1;
            $q      = $is_late ? 'q1 urgent'
                    : ($tag['cls'] === 'received' ? 'q3'
                    : ($tag['cls'] === 'pending'  ? 'q5' : 'qd'));
            $tag_cls   = $is_late ? 'late' : $tag['cls'];
            $tag_label = $is_late ? 'ЗАКЪСНЯЛА ' . (int)$d['age_days'] . 'Д' : $tag['label'];
            $progress  = $d['status'] === 'committed' ? 100
                       : ($d['status'] === 'reviewing' ? 60
                       : ($d['status'] === 'pending' ? 30 : 10));
            $del_id = '#DEL-' . str_pad((string)$d['id'], 5, '0', STR_PAD_LEFT);
            $expected_or_received = !empty($d['delivered_at'])
                ? date('d.m', strtotime((string)$d['delivered_at']))
                : date('d.m', strtotime((string)$d['created_at']));
            $exp_label = !empty($d['delivered_at']) ? 'Получена' : 'Създадена';
        ?>
            <a class="glass sm rich-card <?= $q ?>" href="delivery.php?id=<?= (int)$d['id'] ?>">
                <span class="shine"></span><span class="shine shine-bottom"></span>
                <span class="glow"></span><span class="glow glow-bottom"></span>
                <div class="rc-head">
                    <span class="rc-orb"><?= rmsDeliveryStatusIcon($tag['cls']) ?></span>
                    <div class="rc-head-text">
                        <div class="rc-id"><?= htmlspecialchars($del_id) ?><?= !empty($d['invoice_number']) ? ' · ' . htmlspecialchars((string)$d['invoice_number']) : '' ?></div>
                        <div class="rc-supplier"><?= htmlspecialchars($d['supplier_name']) ?></div>
                    </div>
                    <span class="rc-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></span>
                </div>
                <div class="rc-data">
                    <div class="rc-data-item">
                        <span class="rc-data-label">Артикули</span>
                        <span class="rc-data-val"><?= (int)$d['item_count'] ?> бр</span>
                    </div>
                    <div class="rc-data-item">
                        <span class="rc-data-label">Стойност</span>
                        <span class="rc-data-val amount"><?= fmtMoney((float)$d['total'], $currency) ?></span>
                    </div>
                    <div class="rc-data-item">
                        <span class="rc-data-label"><?= htmlspecialchars($exp_label) ?></span>
                        <span class="rc-data-val<?= $is_late ? ' late' : '' ?>">
                            <?= htmlspecialchars($expected_or_received) ?>
                            <?php if ($is_late): ?> · -<?= (int)$d['age_days'] ?>д<?php endif; ?>
                        </span>
                    </div>
                    <div class="rc-data-item">
                        <span class="rc-data-label">Плащане</span>
                        <span class="rc-data-val">
                            <?php
                            $pay = $d['payment_status'] ?? 'unpaid';
                            $pay_label = $pay === 'paid' ? 'платено'
                                       : ($pay === 'partially_paid' ? 'частично' : 'неплатено');
                            echo htmlspecialchars($pay_label);
                            ?>
                        </span>
                    </div>
                </div>
                <div class="rc-tags">
                    <span class="rc-tag <?= $tag_cls ?>"><?= htmlspecialchars($tag_label) ?></span>
                    <?php if (!empty($d['has_mismatch'])): ?>
                        <span class="rc-tag late">С РАЗЛИКА</span>
                    <?php endif; ?>
                </div>
                <div class="rc-progress"><div class="rc-progress-fill" style="width:<?= $progress ?>%"></div></div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; /* mode switch */ ?>

</main>

<?php if ($mode === 'detailed'): ?>
    <?php include __DIR__ . '/partials/bottom-nav.php'; ?>
<?php endif; ?>

<script>
(function(){
  // Theme toggle (rmsToggleTheme — извикано от header.php)
  function syncThemeIcons(){
    var t = document.documentElement.getAttribute('data-theme') || 'light';
    var sun  = document.getElementById('themeIconSun');
    var moon = document.getElementById('themeIconMoon');
    if (sun)  sun.style.display  = (t === 'dark') ? 'block' : 'none';
    if (moon) moon.style.display = (t === 'dark') ? 'none'  : 'block';
  }
  if (typeof window.rmsToggleTheme !== 'function') {
    window.rmsToggleTheme = function(){
      var cur = document.documentElement.getAttribute('data-theme') || 'light';
      var nxt = (cur === 'light') ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', nxt);
      try { localStorage.setItem('rms_theme', nxt); } catch(_){}
      syncThemeIcons();
      if (navigator.vibrate) navigator.vibrate(5);
    };
  }
  syncThemeIcons();

  // Print + logout placeholders (същински flow живее в Phase B / chat shell)
  if (typeof window.rmsOpenPrinter !== 'function') {
    window.rmsOpenPrinter = function(){};
  }
  if (typeof window.rmsToggleLogout !== 'function') {
    window.rmsToggleLogout = function(e){
      if (e) e.preventDefault();
      var dd = document.getElementById('logoutDrop');
      if (dd) dd.hidden = !dd.hidden;
    };
  }
})();
</script>

</body>
</html>
