<?php
/**
 * life-board.php — Лесен режим (S132 PILOT — P10 rewrite)
 *
 * Layout (P10):
 *   1. top-row : Днес + днешно време
 *   2. ops-section : 4 op-btn (Продай / Стоката / Доставка / Поръчка)
 *      — всеки с info popover
 *   3. studio-row : AI Studio
 *   4. wfc : 14-дневна прогноза + AI препоръки
 *   5. help-card : AI помага (chips + видео placeholder)
 *   6. lb-cards : Life Board (collapsed по подразбиране)
 *   7. chat-input-bar (sticky, идва от partial)
 *
 * Auth + tenant load: same pattern as chat.php.
 * NEW BEHAVIOR: $_SESSION['ui_mode'] bootstrap + ?from=lifeboard suffix
 * за всички outbound линкове (за render guard в destination модулите).
 */
session_start();

// ─── NEW BEHAVIOR (S132): UI mode bootstrap ───
if (!isset($_SESSION['ui_mode'])) {
    $role = $_SESSION['user_role'] ?? 'seller';
    $_SESSION['ui_mode'] = ($role === 'seller') ? 'simple' : 'detailed';
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)($_SESSION['store_id'] ?? 0);
$role      = $_SESSION['role'] ?? 'seller';

if (!empty($_GET['store'])) {
    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
        [(int)$_GET['store'], $tenant_id])->fetch();
    if ($chk) { $_SESSION['store_id'] = (int)$_GET['store']; }
    header('Location: life-board.php'); exit;
}

if (!$store_id) {
    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
    if ($first) { $store_id = (int)$first['id']; $_SESSION['store_id'] = $store_id; }
}

$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
$cs = htmlspecialchars($tenant['currency'] ?? '€');
$plan = effectivePlan($tenant);

$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
$store_name = $store['name'] ?? 'Магазин';
$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);

autoGeolocateStore($store_id);

// ─── TODAY revenue (mini) — copy of chat.php pattern ───
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$rev_today = (float)DB::run(
    'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=? AND status!="canceled"',
    [$tenant_id, $store_id, $today])->fetchColumn();
$rev_yesterday = (float)DB::run(
    'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=? AND status!="canceled"',
    [$tenant_id, $store_id, $yesterday])->fetchColumn();
$cnt_today = (int)DB::run(
    'SELECT COUNT(*) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=? AND status!="canceled"',
    [$tenant_id, $store_id, $today])->fetchColumn();
$profit_today = 0;
if ($role === 'owner' && $cnt_today > 0) {
    $profit_today = (float)DB::run(
        'SELECT COALESCE(SUM(si.quantity*(si.unit_price - COALESCE(si.cost_price,0))),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)=? AND s.status!="canceled"',
        [$tenant_id, $store_id, $today])->fetchColumn();
}
$cmp_pct = $rev_yesterday > 0
    ? (int)round(($rev_today - $rev_yesterday) / $rev_yesterday * 100)
    : ($rev_today > 0 ? 100 : 0);
$cmp_class = $cmp_pct > 0 ? 'pos' : ($cmp_pct < 0 ? 'neg' : 'zero');
$cmp_sign  = $cmp_pct > 0 ? '+' : '';

// ─── Today weather (mini) — copy of chat.php pattern ───
$weather_today = null;
try {
    $weather_today = DB::run(
        'SELECT temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date=CURDATE() LIMIT 1',
        [$store_id])->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ─── 14-day weather forecast (S132 P10 — WFC card) ───
$weather_14 = [];
try {
    $weather_14 = DB::run(
        'SELECT forecast_date, temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date >= CURDATE() ORDER BY forecast_date ASC LIMIT 14',
        [$store_id])->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $weather_14 = []; }

function lbWmoSvg($code) {
    if ($code <= 3) return '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>';
    if ($code <= 48) return '<path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z"/>';
    return '<path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z"/><line x1="8" y1="24" x2="10" y2="18"/><line x1="12" y1="24" x2="14" y2="18"/><line x1="16" y1="24" x2="18" y2="18"/>';
}
function lbWmoText($code) {
    if ($code <= 3) return 'Слънчево';
    if ($code <= 48) return 'Облачно';
    if ($code <= 57) return 'Ръми';
    if ($code <= 67) return 'Дъжд';
    if ($code <= 77) return 'Сняг';
    if ($code <= 82) return 'Порой';
    return 'Буря';
}

// ─── P10 helpers (presentation only) ───
function lbWmoClass(int $code): string {
    if ($code <= 1)  return 'sunny';
    if ($code <= 3)  return 'partly';
    if ($code <= 48) return 'cloudy';
    if ($code <= 67) return 'rain';
    if ($code <= 77) return 'rain';
    return 'storm';
}
function lbWmoDayIcon(int $code): string {
    switch (lbWmoClass($code)) {
        case 'sunny':
            return '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>';
        case 'partly':
            return '<svg viewBox="0 0 24 24"><path d="M22 14a4 4 0 00-7.5-2 5.5 5.5 0 00-10 1A4 4 0 005 21h13a4 4 0 004-4z"/><circle cx="6" cy="6" r="2"/></svg>';
        case 'cloudy':
            return '<svg viewBox="0 0 24 24"><path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z"/></svg>';
        case 'rain':
            return '<svg viewBox="0 0 24 24"><path d="M20 16.2A4.5 4.5 0 0017.5 8h-1.8A7 7 0 104 14.9"/><line x1="8" y1="19" x2="8" y2="21"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="16" y1="19" x2="16" y2="21"/></svg>';
        case 'storm':
        default:
            return '<svg viewBox="0 0 24 24"><path d="M19 16.9A5 5 0 0018 7h-1.26a8 8 0 10-11.62 9"/><polyline points="13 11 9 17 15 17 11 23"/></svg>';
    }
}
function lbDayName(string $date_str, bool $today_first = false): string {
    $names = ['Нд','Пн','Вт','Ср','Чт','Пт','Сб'];
    if ($today_first && $date_str === date('Y-m-d')) return 'Днес';
    return $names[(int)date('w', strtotime($date_str))];
}
// Append ?from=lifeboard (or & if URL already has ?)
function lbWith(?string $url): string {
    if ($url === null || $url === '') return '';
    if (strpos($url, 'from=lifeboard') !== false) return $url;
    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    return $url . $sep . 'from=lifeboard';
}

// ─── AI Studio pending count — copy of chat.php pattern ───
$ai_studio_count = 0;
try {
    $ai_studio_count = (int)DB::run(
        "SELECT COUNT(*) FROM products
         WHERE tenant_id = ? AND is_active = 1 AND parent_id IS NULL
         AND ((image_url IS NULL OR image_url = '' OR image_url LIKE 'data:%')
              OR (description IS NULL OR description = ''))",
        [$tenant_id]
    )->fetchColumn();
} catch (Throwable $e) { $ai_studio_count = 0; }

// ─── Insights → pick 4 cards (loss-heavy, per loss aversion) ───
$insights = [];
try {
    if (planAtLeast($plan, 'pro')) {
        $insights = getInsightsForModule($tenant_id, $store_id, $user_id, 'home', $plan, $role);
    }
} catch (Exception $e) { $insights = []; }

$by_fq = ['loss'=>[], 'gain'=>[], 'order'=>[], 'loss_cause'=>[], 'gain_cause'=>[], 'anti_order'=>[]];
foreach ($insights as $idx => $ins) {
    $fq = $ins['fundamental_question'] ?? '';
    if (isset($by_fq[$fq])) {
        $ins['_idx'] = $idx;
        $by_fq[$fq][] = $ins;
    }
}

$picked = [];
foreach (array_slice($by_fq['loss'], 0, 2) as $ins) { $picked[] = ['fq'=>'loss', 'ins'=>$ins, 'expanded'=>false]; }
if (!empty($by_fq['gain']))  { $picked[] = ['fq'=>'gain',  'ins'=>$by_fq['gain'][0],  'expanded'=>true]; }
if (!empty($by_fq['order'])) { $picked[] = ['fq'=>'order', 'ins'=>$by_fq['order'][0], 'expanded'=>false]; }
if (count($picked) < 4 && !empty($by_fq['loss_cause'])) { $picked[] = ['fq'=>'loss_cause', 'ins'=>$by_fq['loss_cause'][0], 'expanded'=>false]; }
if (count($picked) < 4 && !empty($by_fq['anti_order']))  { $picked[] = ['fq'=>'anti_order',  'ins'=>$by_fq['anti_order'][0],  'expanded'=>false]; }
$picked = array_slice($picked, 0, 4);
$total_insights = count($insights);
$remaining_after_picked = max(0, $total_insights - count($picked));

$fq_meta = [
    'loss'       => ['q'=>'q1', 'emoji'=>'🔴', 'name'=>'ГУБИШ'],
    'loss_cause' => ['q'=>'q2', 'emoji'=>'🟣', 'name'=>'ОТ КАКВО'],
    'gain'       => ['q'=>'q3', 'emoji'=>'🟢', 'name'=>'ПЕЧЕЛИШ'],
    'gain_cause' => ['q'=>'q4', 'emoji'=>'💎', 'name'=>'ОТ КАКВО ПЕЧЕЛИШ'],
    'order'      => ['q'=>'q5', 'emoji'=>'🟡', 'name'=>'ПОРЪЧАЙ'],
    'anti_order' => ['q'=>'q6', 'emoji'=>'⚪', 'name'=>'НЕ ПОРЪЧВАЙ'],
];

// SVG icons per fundamental_question (P10 style — stroked emoji-orb)
$fq_svg = [
    'q1' => '<svg viewBox="0 0 24 24"><polygon points="10.29 3.86 1.82 18 22.18 18 13.71 3.86 10.29 3.86"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    'q2' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    'q3' => '<svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
    'q4' => '<svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
    'q5' => '<svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
    'q6' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
];

// Local copy of chat.php insightAction() — same logic, no new endpoints
function lbInsightAction(array $ins): array {
    if (!empty($ins['action_label'])) {
        return [
            'label' => $ins['action_label'],
            'type'  => $ins['action_type'] ?? 'chat',
            'url'   => $ins['action_url'] ?? null,
        ];
    }
    $fq = $ins['fundamental_question'] ?? '';
    $tid = $ins['topic_id'] ?? '';
    switch ($fq) {
        case 'loss':
            if (str_contains($tid, 'zero') || str_contains($tid, 'stock') || str_contains($tid, 'below_min') || str_contains($tid, 'running_out'))
                return ['label' => 'Поръчай', 'type' => 'deeplink', 'url' => 'products.php?filter=running_out'];
            return ['label' => 'Виж', 'type' => 'deeplink', 'url' => 'products.php'];
        case 'loss_cause':
            return ['label' => 'Виж причината', 'type' => 'chat', 'url' => null];
        case 'gain':
            return ['label' => 'Поръчай още', 'type' => 'deeplink', 'url' => 'products.php?filter=top_profit'];
        case 'gain_cause':
            return ['label' => 'Повече данни', 'type' => 'chat', 'url' => null];
        case 'order':
            return ['label' => 'Поръчай', 'type' => 'deeplink', 'url' => 'products.php?filter=running_out'];
        case 'anti_order':
            return ['label' => 'Намали', 'type' => 'deeplink', 'url' => 'products.php?filter=zombie'];
    }
    return ['label' => 'Разкажи повече', 'type' => 'chat', 'url' => null];
}

// Operational targets — fall back to products.php when module is missing
$has_orders     = file_exists(__DIR__ . '/orders.php');
$has_deliveries = file_exists(__DIR__ . '/deliveries.php');
$op_orders_url     = $has_orders ? '/orders.php' : '/products.php';
$op_deliveries_url = $has_deliveries ? '/deliveries.php' : '/products.php';

?><!DOCTYPE html>
<html lang="bg" data-theme="<?= htmlspecialchars($_COOKIE['rms_theme'] ?? 'light') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Лесен режим — RunMyStore.ai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/theme.css?v=<?= @filemtime(__DIR__.'/css/theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/css/shell.css?v=<?= @filemtime(__DIR__.'/css/shell.css') ?: 1 ?>">
<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>
<style>
/* ════════════════════════════════════════════════════════════════════
   life-board.php — P10 LESNY MODE — DESIGN_SYSTEM v4.0 BICHROMATIC
   Light Neumorphism default + Dark SACRED Neon Glass
   ════════════════════════════════════════════════════════════════════ */

* { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
html, body { min-height: 100%; }
body { font-family: 'Montserrat', sans-serif; overflow-x: hidden; }
button, input, a { font-family: inherit; color: inherit; }
button { background: none; border: none; cursor: pointer; }
a { text-decoration: none; }

:root {
  --hue1: 255; --hue2: 222; --hue3: 180;
  --radius: 22px; --radius-sm: 14px; --radius-pill: 999px; --radius-icon: 50%;
  --border: 1px;
  --ease: cubic-bezier(0.5, 1, 0.89, 1);
  --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
  --dur-fast: 150ms; --dur: 250ms;
  --press: 0.97;
  --font: 'Montserrat', sans-serif;
  --font-mono: 'DM Mono', ui-monospace, monospace;
}

:root:not([data-theme]),
:root[data-theme="light"] {
  --bg-main: #e0e5ec; --surface: #e0e5ec; --surface-2: #d1d9e6;
  --border-color: transparent;
  --text: #2d3748; --text-muted: #64748b; --text-faint: #94a3b8;
  --shadow-light: #ffffff; --shadow-dark: #a3b1c6;
  --neu-d: 8px; --neu-b: 16px; --neu-d-s: 4px; --neu-b-s: 8px;
  --shadow-card: var(--neu-d) var(--neu-d) var(--neu-b) var(--shadow-dark), calc(var(--neu-d) * -1) calc(var(--neu-d) * -1) var(--neu-b) var(--shadow-light);
  --shadow-card-sm: var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark), calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);
  --shadow-pressed: inset var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark), inset calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);
  --accent: oklch(0.62 0.22 285); --accent-2: oklch(0.65 0.25 305); --accent-3: oklch(0.78 0.18 195);
  --magic: oklch(0.65 0.25 310);
  --success: oklch(0.68 0.18 155); --warning: oklch(0.72 0.18 70); --danger: oklch(0.65 0.22 25);
  --aurora-blend: multiply; --aurora-opacity: 0.32;
}
:root[data-theme="dark"] {
  --bg-main: #08090d; --surface: hsl(220, 25%, 4.8%); --surface-2: hsl(220, 25%, 8%);
  --border-color: hsl(var(--hue2), 12%, 20%);
  --text: #f1f5f9; --text-muted: rgba(255,255,255,0.6); --text-faint: rgba(255,255,255,0.4);
  --shadow-card: hsl(var(--hue2) 50% 2%) 0 10px 16px -8px, hsl(var(--hue2) 50% 4%) 0 20px 36px -14px;
  --shadow-card-sm: hsl(var(--hue2) 50% 2%) 0 4px 8px -2px;
  --shadow-pressed: inset 0 2px 4px hsl(var(--hue2) 50% 2%);
  --accent: hsl(var(--hue1), 80%, 65%); --accent-2: hsl(var(--hue2), 80%, 65%); --accent-3: hsl(var(--hue3), 70%, 55%);
  --magic: hsl(280, 70%, 65%);
  --success: hsl(145, 70%, 55%); --warning: hsl(38, 90%, 60%); --danger: hsl(0, 85%, 60%);
  --aurora-blend: plus-lighter; --aurora-opacity: 0.35;
}

html, body { font-family: var(--font); color: var(--text); background: var(--bg-main); min-height: 100vh; }
:root:not([data-theme]) body, [data-theme="light"] body { background: var(--bg-main); color: var(--text); }
[data-theme="dark"] body {
  background:
    radial-gradient(ellipse 800px 500px at 20% 10%, hsl(var(--hue1) 60% 35% / .22) 0%, transparent 60%),
    radial-gradient(ellipse 700px 500px at 85% 85%, hsl(var(--hue2) 60% 35% / .22) 0%, transparent 60%),
    linear-gradient(180deg, #0a0b14 0%, #050609 100%);
  background-attachment: fixed; color: var(--text);
}

.aurora { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
.aurora-blob { position: absolute; border-radius: var(--radius-icon); filter: blur(60px); opacity: var(--aurora-opacity); mix-blend-mode: var(--aurora-blend); animation: auroraDrift 20s ease-in-out infinite; }
.aurora-blob:nth-child(1) { width: 280px; height: 280px; background: hsl(var(--hue1),80%,60%); top: -60px; left: -80px; }
.aurora-blob:nth-child(2) { width: 240px; height: 240px; background: hsl(var(--hue3),70%,60%); top: 35%; right: -100px; animation-delay: 4s; }
.aurora-blob:nth-child(3) { width: 200px; height: 200px; background: hsl(280,80%,55%); bottom: 80px; left: -50px; animation-delay: 8s; }

/* ═══ MODE TOGGLE ROW (Подробен →) ═══ */
.lb-mode-row {
  display: flex; justify-content: flex-end;
  padding: 8px 12px 0;
  position: relative; z-index: 5;
}
.lb-mode-toggle {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 6px 12px;
  border-radius: var(--radius-pill);
  font-family: var(--font-mono); font-size: 10px; font-weight: 700;
  letter-spacing: 0.04em; color: var(--text-muted);
  border: 1px solid var(--border-color);
  text-decoration: none;
  transition: color var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
}
[data-theme="light"] .lb-mode-toggle,
:root:not([data-theme]) .lb-mode-toggle { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .lb-mode-toggle { background: hsl(220 25% 8% / 0.6); }
.lb-mode-toggle:hover { color: var(--accent); }
.lb-mode-toggle svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; }

/* ═══ APP ═══ */
.app { position: relative; z-index: 5; max-width: 480px; margin: 0 auto; padding: 8px 12px calc(86px + env(safe-area-inset-bottom, 0)); }

/* ═══ GLASS BASE ═══ */
.glass { position: relative; border-radius: var(--radius); border: var(--border) solid var(--border-color); isolation: isolate; }
.glass.sm { border-radius: var(--radius-sm); }
.glass .shine, .glass .glow { --hue: var(--hue1); }
.glass .shine-bottom, .glass .glow-bottom { --hue: var(--hue2); --conic: 135deg; }
[data-theme="light"] .glass, :root:not([data-theme]) .glass { background: var(--surface); box-shadow: var(--shadow-card); border: none; }
[data-theme="light"] .glass .shine, [data-theme="light"] .glass .glow,
:root:not([data-theme]) .glass .shine, :root:not([data-theme]) .glass .glow { display: none; }
[data-theme="dark"] .glass {
  background: linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%), linear-gradient(45deg, hsl(var(--hue2) 50% 10% / .8), hsl(var(--hue2) 50% 10% / 0) 33%), linear-gradient(hsl(220 25% 4.8% / .78));
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); box-shadow: var(--shadow-card);
}
[data-theme="dark"] .glass .shine { pointer-events: none; border-radius: 0; border-top-right-radius: inherit; border-bottom-left-radius: inherit; border: 1px solid transparent; width: 75%; aspect-ratio: 1; display: block; position: absolute; right: calc(var(--border) * -1); top: calc(var(--border) * -1); z-index: 1; background: conic-gradient(from var(--conic, -45deg) at center in oklch, transparent 12%, hsl(var(--hue), 80%, 60%), transparent 50%) border-box; mask: linear-gradient(transparent), linear-gradient(black); mask-clip: padding-box, border-box; mask-composite: subtract; }
[data-theme="dark"] .glass .shine.shine-bottom { right: auto; top: auto; left: calc(var(--border) * -1); bottom: calc(var(--border) * -1); }
[data-theme="dark"] .glass .glow { pointer-events: none; border-top-right-radius: calc(var(--radius) * 2.5); border-bottom-left-radius: calc(var(--radius) * 2.5); border: calc(var(--radius) * 1.25) solid transparent; inset: calc(var(--radius) * -2); width: 75%; aspect-ratio: 1; display: block; position: absolute; left: auto; bottom: auto; background: conic-gradient(from var(--conic, -45deg) at center in oklch, hsl(var(--hue), 80%, 60% / .5) 12%, transparent 50%); filter: blur(12px) saturate(1.25); mix-blend-mode: plus-lighter; z-index: 3; opacity: 0.6; }
[data-theme="dark"] .glass .glow.glow-bottom { inset: auto; left: calc(var(--radius) * -2); bottom: calc(var(--radius) * -2); }
/* hue overrides */
[data-theme="dark"] .glass.q1 .shine, [data-theme="dark"] .glass.q1 .glow { --hue: 0; }
[data-theme="dark"] .glass.q1 .shine-bottom, [data-theme="dark"] .glass.q1 .glow-bottom { --hue: 15; }
[data-theme="dark"] .glass.q2 .shine, [data-theme="dark"] .glass.q2 .glow { --hue: 280; }
[data-theme="dark"] .glass.q2 .shine-bottom, [data-theme="dark"] .glass.q2 .glow-bottom { --hue: 305; }
[data-theme="dark"] .glass.q3 .shine, [data-theme="dark"] .glass.q3 .glow { --hue: 145; }
[data-theme="dark"] .glass.q3 .shine-bottom, [data-theme="dark"] .glass.q3 .glow-bottom { --hue: 165; }
[data-theme="dark"] .glass.q4 .shine, [data-theme="dark"] .glass.q4 .glow { --hue: 180; }
[data-theme="dark"] .glass.q4 .shine-bottom, [data-theme="dark"] .glass.q4 .glow-bottom { --hue: 195; }
[data-theme="dark"] .glass.q5 .shine, [data-theme="dark"] .glass.q5 .glow { --hue: 38; }
[data-theme="dark"] .glass.q5 .shine-bottom, [data-theme="dark"] .glass.q5 .glow-bottom { --hue: 28; }
[data-theme="dark"] .glass.qhelp .shine, [data-theme="dark"] .glass.qhelp .glow { --hue: 280; }
[data-theme="dark"] .glass.qhelp .shine-bottom, [data-theme="dark"] .glass.qhelp .glow-bottom { --hue: 310; }

/* ═══ TOP ROW (Днес + Времето) ═══ */
.top-row {
  display: grid; grid-template-columns: 1.4fr 1fr; gap: 10px;
  margin-bottom: 12px;
  animation: fadeInUp 0.6s var(--ease-spring) both;
}
.cell { padding: 12px 14px; }
.cell > * { position: relative; z-index: 5; }
.cell-header-row { display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap; min-width: 0; }
.cell-label { font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted); flex-shrink: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cell-numrow { display: flex; align-items: baseline; gap: 4px; margin-top: 6px; }
.cell-num { font-size: 24px; font-weight: 800; letter-spacing: -0.02em; line-height: 1; }
.cell-cur { font-family: var(--font-mono); font-size: 11px; font-weight: 700; color: var(--text-muted); }
.cell-pct { font-family: var(--font-mono); font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: var(--radius-pill); margin-left: auto; }
.cell-pct.pos { background: oklch(0.92 0.08 145 / 0.5); color: hsl(145 60% 35%); }
[data-theme="dark"] .cell-pct.pos { background: hsl(145 50% 12%); color: hsl(145 70% 65%); }
.cell-pct.neg { background: oklch(0.92 0.08 25 / 0.5); color: hsl(0 60% 45%); }
[data-theme="dark"] .cell-pct.neg { background: hsl(0 50% 12%); color: hsl(0 80% 70%); }
.cell-pct.zero { background: oklch(0.92 0.02 220 / 0.5); color: var(--text-muted); }
[data-theme="dark"] .cell-pct.zero { background: hsl(220 12% 12%); color: var(--text-muted); }
.cell-meta { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 4px; line-height: 1.2; }
.weather-cell-top { display: flex; align-items: baseline; gap: 6px; }
.weather-cell-icon svg { width: 22px; height: 22px; stroke: hsl(38 80% 50%); fill: hsl(38 80% 60%); stroke-width: 1.5; }
[data-theme="dark"] .weather-cell-icon svg { stroke: hsl(38 90% 60%); fill: hsl(38 80% 50%); }
.weather-cell-temp { font-size: 22px; font-weight: 800; letter-spacing: -0.02em; }
.weather-cell-cond { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-top: 3px; }

/* Store picker */
.lb-store-picker {
  font-family: var(--font-mono); font-size: 9px; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.04em;
  border-radius: var(--radius-pill);
  padding: 3px 18px 3px 8px;
  border: 1px solid var(--border-color);
  color: var(--text);
  cursor: pointer; outline: none; appearance: none; -webkit-appearance: none;
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23667' stroke-width='3'><polyline points='6 9 12 15 18 9'/></svg>");
  background-repeat: no-repeat; background-position: right 5px center; background-size: 8px;
  max-width: 105px; flex-shrink: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
[data-theme="light"] .lb-store-picker, :root:not([data-theme]) .lb-store-picker { background-color: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .lb-store-picker { background-color: hsl(220 25% 8%); }

/* ═══ OPS GRID (4 buttons) — ПРЕМЕСТЕН ГОРЕ ═══ */
.ops-section { margin-bottom: 12px; animation: fadeInUp 0.7s var(--ease-spring) both; }
.ops-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.op-btn {
  position: relative;
  padding: 16px 12px;
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  text-align: center; text-decoration: none; color: var(--text);
  transition: box-shadow var(--dur) var(--ease), transform var(--dur-fast) var(--ease);
}
.op-btn > * { position: relative; z-index: 5; }
.op-btn:active { transform: scale(0.98); }
[data-theme="light"] .op-btn:active, :root:not([data-theme]) .op-btn:active { box-shadow: var(--shadow-pressed); }
.op-icon {
  width: 44px; height: 44px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .op-icon, :root:not([data-theme]) .op-icon { background: var(--surface); box-shadow: var(--shadow-pressed); border: none; }
[data-theme="dark"] .op-icon { background: hsl(220 25% 4%); }
.op-icon svg { width: 22px; height: 22px; fill: none; stroke-width: 2; }
.op-btn.q3 .op-icon svg { stroke: hsl(145 60% 45%); }
[data-theme="dark"] .op-btn.q3 .op-icon svg { stroke: hsl(145 70% 65%); }
.op-btn.q5 .op-icon svg { stroke: hsl(38 80% 50%); }
[data-theme="dark"] .op-btn.q5 .op-icon svg { stroke: hsl(38 90% 65%); }
.op-btn.q2 .op-icon svg { stroke: hsl(280 60% 50%); }
[data-theme="dark"] .op-btn.q2 .op-icon svg { stroke: hsl(280 70% 70%); }
.op-btn.qd .op-icon svg { stroke: var(--accent); }
[data-theme="dark"] .op-btn.qd .op-icon svg { stroke: hsl(var(--hue1) 80% 70%); }
.op-label { font-size: 14px; font-weight: 800; letter-spacing: -0.005em; }

.op-info-btn {
  position: absolute;
  top: 8px; right: 8px;
  width: 22px; height: 22px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  z-index: 6;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .op-info-btn, :root:not([data-theme]) .op-info-btn { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="light"] .op-info-btn:active, :root:not([data-theme]) .op-info-btn:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .op-info-btn { background: hsl(220 25% 8%); }
.op-info-btn svg { width: 11px; height: 11px; stroke: var(--text-muted); fill: none; stroke-width: 2.5; }

/* ═══ AI STUDIO ROW ═══ */
.studio-row { margin-bottom: 12px; animation: fadeInUp 0.8s var(--ease-spring) both; }
.studio-btn {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px;
  position: relative;
  text-decoration: none; color: var(--text);
  transition: box-shadow var(--dur) var(--ease), transform var(--dur-fast) var(--ease);
}
.studio-btn > * { position: relative; z-index: 5; }
.studio-btn:active { transform: scale(0.99); }
[data-theme="light"] .studio-btn:active, :root:not([data-theme]) .studio-btn:active { box-shadow: var(--shadow-pressed); }
.studio-icon {
  width: 36px; height: 36px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow: 0 4px 12px hsl(280 70% 50% / 0.5);
  position: relative; overflow: hidden;
}
.studio-icon::before { content: ''; position: absolute; inset: 0; background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.4) 85%, transparent 100%); animation: conicSpin 4s linear infinite; }
.studio-icon svg { width: 18px; height: 18px; stroke: white; fill: none; stroke-width: 2; position: relative; z-index: 1; }
.studio-text { flex: 1; min-width: 0; }
.studio-label { display: block; font-size: 14px; font-weight: 800; letter-spacing: -0.01em; }
.studio-sub { display: block; font-family: var(--font-mono); font-size: 9.5px; font-weight: 700; color: var(--text-muted); letter-spacing: 0.06em; text-transform: uppercase; margin-top: 2px; }
.studio-badge {
  font-family: var(--font-mono); font-size: 10px; font-weight: 800;
  padding: 4px 10px;
  border-radius: var(--radius-pill);
  color: white;
  background: linear-gradient(135deg, hsl(0 75% 55%), hsl(15 75% 55%));
  box-shadow: 0 2px 8px hsl(0 70% 45% / 0.4);
  flex-shrink: 0;
}

/* ═══ WEATHER FORECAST CARD ═══ */
.wfc { padding: 14px; margin-bottom: 14px; animation: fadeInUp 0.85s var(--ease-spring) both; }
.wfc > * { position: relative; z-index: 5; }
.wfc-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.wfc-head-ic {
  width: 36px; height: 36px; border-radius: var(--radius-icon);
  display: grid; place-items: center; flex-shrink: 0;
  background: linear-gradient(135deg, hsl(195 75% 60%), hsl(38 85% 60%));
  box-shadow: 0 4px 12px hsl(195 70% 50% / 0.45);
  position: relative; overflow: hidden;
}
.wfc-head-ic::before { content: ''; position: absolute; inset: 0; background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.4) 85%, transparent 100%); animation: conicSpin 5s linear infinite; }
.wfc-head-ic svg { width: 18px; height: 18px; stroke: white; fill: none; stroke-width: 2; position: relative; z-index: 1; }
.wfc-head-text { flex: 1; min-width: 0; }
.wfc-title {
  font-size: 14px; font-weight: 800; letter-spacing: -0.01em;
  background: linear-gradient(135deg, var(--text), hsl(195 70% 50%));
  -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
}
.wfc-sub { font-family: var(--font-mono); font-size: 9px; font-weight: 700; color: var(--text-muted); letter-spacing: 0.06em; text-transform: uppercase; margin-top: 1px; }

.wfc-tabs {
  display: flex; gap: 3px; padding: 3px;
  border-radius: var(--radius-pill);
  margin-bottom: 12px;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .wfc-tabs, :root:not([data-theme]) .wfc-tabs { background: var(--surface); box-shadow: var(--shadow-pressed); border: none; }
[data-theme="dark"] .wfc-tabs { background: hsl(220 25% 4%); }
.wfc-tab {
  flex: 1; height: 28px;
  border-radius: var(--radius-pill);
  display: inline-flex; align-items: center; justify-content: center;
  font-family: var(--font-mono); font-size: 10px; font-weight: 800;
  letter-spacing: 0.04em; color: var(--text-muted);
  transition: color var(--dur) var(--ease);
}
[data-range="3"] .wfc-tab[data-tab="3"],
[data-range="7"] .wfc-tab[data-tab="7"],
[data-range="14"] .wfc-tab[data-tab="14"] {
  color: white;
  background: linear-gradient(135deg, hsl(195 70% 50%), hsl(38 80% 55%));
  box-shadow: 0 3px 10px hsl(195 70% 45% / 0.4);
}

.wfc-days { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 8px; margin-bottom: 12px; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
.wfc-days::-webkit-scrollbar { display: none; }

.wfc-day {
  flex: 0 0 auto;
  width: 64px;
  padding: 10px 6px;
  border-radius: var(--radius-sm);
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  border: 1px solid var(--border-color);
  scroll-snap-align: start;
}
[data-theme="light"] .wfc-day, :root:not([data-theme]) .wfc-day { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .wfc-day { background: hsl(220 25% 6% / 0.6); }
.wfc-day.today { border: 1px solid hsl(195 70% 50% / 0.4); }
[data-theme="light"] .wfc-day.today, :root:not([data-theme]) .wfc-day.today { background: oklch(0.92 0.06 195); box-shadow: var(--shadow-pressed); border: none; }
[data-theme="dark"] .wfc-day.today { background: hsl(195 50% 12% / 0.5); border: 1px solid hsl(195 70% 40% / 0.5); }

[data-range="3"] .wfc-day:nth-child(n+4),
[data-range="7"] .wfc-day:nth-child(n+8) { display: none; }

.wfc-day-name { font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); }
.wfc-day-ic { width: 28px; height: 28px; display: grid; place-items: center; }
.wfc-day-ic svg { width: 22px; height: 22px; fill: none; stroke-width: 1.8; }
.wfc-day.sunny .wfc-day-ic svg { stroke: hsl(38 85% 50%); fill: hsl(38 85% 60%); }
[data-theme="dark"] .wfc-day.sunny .wfc-day-ic svg { stroke: hsl(38 90% 65%); fill: hsl(38 80% 55%); }
.wfc-day.partly .wfc-day-ic svg { stroke: hsl(220 30% 50%); fill: hsl(220 30% 70%); }
[data-theme="dark"] .wfc-day.partly .wfc-day-ic svg { stroke: hsl(220 20% 75%); fill: hsl(220 20% 55%); }
.wfc-day.cloudy .wfc-day-ic svg { stroke: hsl(220 15% 50%); fill: hsl(220 15% 70%); }
[data-theme="dark"] .wfc-day.cloudy .wfc-day-ic svg { stroke: hsl(220 10% 70%); fill: hsl(220 10% 50%); }
.wfc-day.rain .wfc-day-ic svg { stroke: hsl(210 75% 50%); fill: none; }
[data-theme="dark"] .wfc-day.rain .wfc-day-ic svg { stroke: hsl(210 80% 70%); }
.wfc-day.storm .wfc-day-ic svg { stroke: hsl(280 60% 50%); fill: none; }
[data-theme="dark"] .wfc-day.storm .wfc-day-ic svg { stroke: hsl(280 70% 70%); }

.wfc-day-temp { font-size: 14px; font-weight: 800; letter-spacing: -0.01em; }
.wfc-day-temp small { font-size: 10px; font-weight: 600; color: var(--text-muted); margin-left: 1px; }
.wfc-day-rain { font-family: var(--font-mono); font-size: 9px; font-weight: 700; color: hsl(210 70% 50%); display: flex; align-items: center; gap: 2px; }
[data-theme="dark"] .wfc-day-rain { color: hsl(210 80% 70%); }
.wfc-day-rain.dry { color: var(--text-faint); }

.wfc-recs-divider {
  display: flex; align-items: center; gap: 8px;
  margin: 4px 0 10px;
  font-family: var(--font-mono); font-size: 9px; font-weight: 800;
  letter-spacing: 0.08em; text-transform: uppercase;
  color: var(--magic);
}
[data-theme="dark"] .wfc-recs-divider { color: hsl(280 70% 75%); }
.wfc-recs-divider::before, .wfc-recs-divider::after {
  content: ''; flex: 1; height: 1px;
  background: linear-gradient(90deg, transparent, currentColor, transparent);
  opacity: 0.3;
}

.wfc-rec {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 10px 12px;
  margin-bottom: 6px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-color);
}
[data-theme="light"] .wfc-rec, :root:not([data-theme]) .wfc-rec { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .wfc-rec { background: hsl(220 25% 6% / 0.6); }
.wfc-rec-ic { width: 30px; height: 30px; border-radius: var(--radius-icon); display: grid; place-items: center; flex-shrink: 0; }
.wfc-rec-ic svg { width: 14px; height: 14px; fill: none; stroke-width: 2; }
.wfc-rec.window .wfc-rec-ic { background: hsl(330 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .wfc-rec.window .wfc-rec-ic { background: hsl(330 50% 12%); }
.wfc-rec.window .wfc-rec-ic svg { stroke: hsl(330 70% 50%); }
[data-theme="dark"] .wfc-rec.window .wfc-rec-ic svg { stroke: hsl(330 70% 70%); }
.wfc-rec.order .wfc-rec-ic { background: hsl(38 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .wfc-rec.order .wfc-rec-ic { background: hsl(38 50% 12%); }
.wfc-rec.order .wfc-rec-ic svg { stroke: hsl(38 80% 50%); }
[data-theme="dark"] .wfc-rec.order .wfc-rec-ic svg { stroke: hsl(38 90% 65%); }
.wfc-rec.transfer .wfc-rec-ic { background: hsl(195 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .wfc-rec.transfer .wfc-rec-ic { background: hsl(195 50% 12%); }
.wfc-rec.transfer .wfc-rec-ic svg { stroke: hsl(195 70% 50%); }
[data-theme="dark"] .wfc-rec.transfer .wfc-rec-ic svg { stroke: hsl(195 70% 70%); }
.wfc-rec-text { flex: 1; min-width: 0; }
.wfc-rec-label {
  display: block;
  font-family: var(--font-mono); font-size: 9px; font-weight: 800;
  letter-spacing: 0.08em; text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 2px;
}
.wfc-rec-body { font-size: 12px; font-weight: 600; line-height: 1.35; }
.wfc-rec-body b { font-weight: 800; }

.wfc-source {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 5px 10px;
  border-radius: var(--radius-pill);
  font-family: var(--font-mono); font-size: 8.5px; font-weight: 700;
  color: var(--text-muted);
  letter-spacing: 0.04em;
  margin-top: 8px;
}
[data-theme="light"] .wfc-source, :root:not([data-theme]) .wfc-source { background: var(--surface); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .wfc-source { background: hsl(220 25% 4%); }

/* ═══ AI HELP CARD ═══ */
.help-card { padding: 14px; margin-bottom: 14px; animation: fadeInUp 0.9s var(--ease-spring) both; }
.help-card > * { position: relative; z-index: 5; }
.help-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.help-head-ic {
  width: 36px; height: 36px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center; flex-shrink: 0;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow: 0 4px 12px hsl(280 70% 50% / 0.5);
  position: relative; overflow: hidden;
}
.help-head-ic::before { content: ''; position: absolute; inset: 0; background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.4) 85%, transparent 100%); animation: conicSpin 3s linear infinite; }
.help-head-ic svg { width: 18px; height: 18px; stroke: white; fill: none; stroke-width: 2; position: relative; z-index: 1; }
.help-head-text { flex: 1; min-width: 0; }
.help-title {
  font-size: 15px; font-weight: 800; letter-spacing: -0.01em;
  background: linear-gradient(135deg, var(--text), var(--magic));
  -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
}
.help-sub { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 2px; line-height: 1.3; }

.help-body { font-size: 12px; font-weight: 600; color: var(--text-muted); line-height: 1.5; margin-bottom: 10px; }
.help-body b { color: var(--text); font-weight: 800; }

.help-chips-label { font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
.help-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.help-chip {
  padding: 7px 12px;
  border-radius: var(--radius-pill);
  font-size: 11.5px; font-weight: 700;
  color: var(--text);
  border: 1px solid var(--border-color);
  display: inline-flex; align-items: center; gap: 5px;
  transition: box-shadow var(--dur) var(--ease);
  cursor: pointer;
}
[data-theme="light"] .help-chip, :root:not([data-theme]) .help-chip { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="light"] .help-chip:active, :root:not([data-theme]) .help-chip:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .help-chip { background: hsl(220 25% 8%); }
.help-chip-q { font-family: var(--font-mono); font-size: 10px; font-weight: 900; color: var(--magic); flex-shrink: 0; }
[data-theme="dark"] .help-chip-q { color: hsl(280 70% 70%); }

.help-video-ph {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  border: 1px dashed var(--border-color);
  margin-bottom: 8px;
}
[data-theme="light"] .help-video-ph, :root:not([data-theme]) .help-video-ph { background: var(--surface); box-shadow: var(--shadow-pressed); border: 1px dashed oklch(0.62 0.22 285 / 0.3); }
[data-theme="dark"] .help-video-ph { background: hsl(220 25% 4%); border: 1px dashed hsl(280 60% 35% / 0.4); }
.help-video-ic {
  width: 28px; height: 28px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center; flex-shrink: 0;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow: 0 2px 6px hsl(280 70% 50% / 0.4);
}
.help-video-ic svg { width: 12px; height: 12px; stroke: white; fill: white; stroke-width: 0; }
.help-video-text { flex: 1; min-width: 0; }
.help-video-title { font-size: 11.5px; font-weight: 700; }
.help-video-sub { font-family: var(--font-mono); font-size: 9.5px; font-weight: 600; color: var(--text-muted); margin-top: 1px; }

.help-link-row {
  display: flex; align-items: center; justify-content: center; gap: 4px;
  padding: 8px;
  font-family: var(--font-mono); font-size: 10.5px; font-weight: 700;
  color: var(--magic); letter-spacing: 0.04em;
  text-decoration: none;
}
[data-theme="dark"] .help-link-row { color: hsl(280 70% 75%); }
.help-link-row svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2.5; }

/* ═══ LIFE BOARD HEADER + COLLAPSED CARDS ═══ */
.lb-header {
  display: flex; align-items: center; justify-content: space-between;
  margin: 6px 4px 10px;
  position: relative; z-index: 5;
}
.lb-title { display: flex; align-items: center; gap: 8px; }
.lb-title-orb {
  width: 24px; height: 24px;
  border-radius: var(--radius-icon);
  background: conic-gradient(from 0deg, hsl(var(--hue1) 80% 60%), hsl(280 80% 60%), hsl(var(--hue3) 70% 60%), hsl(var(--hue1) 80% 60%));
  box-shadow: 0 0 12px hsl(var(--hue1) 80% 50% / 0.4);
  position: relative;
  animation: orbSpin 5s linear infinite;
}
.lb-title-orb::after { content: ''; position: absolute; inset: 4px; border-radius: var(--radius-icon); background: var(--bg-main); }
[data-theme="dark"] .lb-title-orb::after { background: #08090d; }
.lb-title-text { font-size: 13px; font-weight: 800; letter-spacing: -0.01em; }
.lb-count { font-family: var(--font-mono); font-size: 10px; font-weight: 700; color: var(--text-muted); }

.lb-card {
  padding: 12px 14px;
  margin-bottom: 8px;
  cursor: pointer;
  transition: box-shadow var(--dur) var(--ease);
}
.lb-card > * { position: relative; z-index: 5; }
.lb-collapsed { display: flex; align-items: center; gap: 10px; }
.lb-emoji-orb {
  width: 28px; height: 28px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center; flex-shrink: 0;
}
.lb-emoji-orb svg { width: 14px; height: 14px; fill: none; stroke-width: 2; }
.lb-card.q1 .lb-emoji-orb { background: hsl(0 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-card.q1 .lb-emoji-orb { background: hsl(0 50% 12%); }
.lb-card.q1 .lb-emoji-orb svg { stroke: hsl(0 70% 50%); }
[data-theme="dark"] .lb-card.q1 .lb-emoji-orb svg { stroke: hsl(0 80% 70%); }
.lb-card.q2 .lb-emoji-orb { background: hsl(280 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-card.q2 .lb-emoji-orb { background: hsl(280 50% 12%); }
.lb-card.q2 .lb-emoji-orb svg { stroke: hsl(280 70% 50%); }
[data-theme="dark"] .lb-card.q2 .lb-emoji-orb svg { stroke: hsl(280 70% 70%); }
.lb-card.q3 .lb-emoji-orb { background: hsl(145 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-card.q3 .lb-emoji-orb { background: hsl(145 50% 12%); }
.lb-card.q3 .lb-emoji-orb svg { stroke: hsl(145 60% 45%); }
[data-theme="dark"] .lb-card.q3 .lb-emoji-orb svg { stroke: hsl(145 70% 65%); }
.lb-card.q4 .lb-emoji-orb { background: hsl(180 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-card.q4 .lb-emoji-orb { background: hsl(180 50% 12%); }
.lb-card.q4 .lb-emoji-orb svg { stroke: hsl(180 70% 45%); }
[data-theme="dark"] .lb-card.q4 .lb-emoji-orb svg { stroke: hsl(180 70% 65%); }
.lb-card.q5 .lb-emoji-orb { background: hsl(38 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-card.q5 .lb-emoji-orb { background: hsl(38 50% 12%); }
.lb-card.q5 .lb-emoji-orb svg { stroke: hsl(38 80% 50%); }
[data-theme="dark"] .lb-card.q5 .lb-emoji-orb svg { stroke: hsl(38 90% 65%); }
.lb-card.q6 .lb-emoji-orb { background: hsl(220 10% 88%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-card.q6 .lb-emoji-orb { background: hsl(220 10% 14%); }
.lb-card.q6 .lb-emoji-orb svg { stroke: hsl(220 10% 50%); }
[data-theme="dark"] .lb-card.q6 .lb-emoji-orb svg { stroke: hsl(220 10% 65%); }

.lb-collapsed-content { flex: 1; min-width: 0; }
.lb-fq-tag-mini { display: block; font-family: var(--font-mono); font-size: 8.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted); }
.lb-collapsed-title { display: block; font-size: 12px; font-weight: 700; margin-top: 2px; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lb-expand-btn {
  width: 24px; height: 24px; border-radius: var(--radius-icon);
  display: grid; place-items: center; flex-shrink: 0;
  border: 1px solid var(--border-color);
  transition: transform 0.3s ease, box-shadow var(--dur) var(--ease);
}
[data-theme="light"] .lb-expand-btn, :root:not([data-theme]) .lb-expand-btn { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .lb-expand-btn { background: hsl(220 25% 8%); }
.lb-expand-btn svg { width: 11px; height: 11px; stroke: var(--text-muted); fill: none; stroke-width: 2.5; }

.see-more-mini {
  text-align: center; margin: 8px 0 4px;
  font-family: var(--font-mono); font-size: 11px; font-weight: 700;
  color: var(--accent);
}
.see-more-mini a { color: inherit; text-decoration: none; }
[data-theme="dark"] .see-more-mini { color: hsl(var(--hue1) 80% 70%); }

/* Expand/collapse animation */
.lb-card.expanded::before {
  content: '';
  position: absolute; inset: -1px;
  border-radius: var(--radius-sm);
  padding: 2px;
  background: conic-gradient(from 0deg, var(--accent), transparent 60%, var(--accent));
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor; mask-composite: exclude;
  animation: conicSpin 4s linear infinite;
  opacity: 0.55; pointer-events: none; z-index: 1;
}
.lb-card.q1.expanded::before { background: conic-gradient(from 0deg, hsl(0 70% 55%), transparent 60%, hsl(0 70% 55%)); }
.lb-card.q2.expanded::before { background: conic-gradient(from 0deg, hsl(280 70% 55%), transparent 60%, hsl(280 70% 55%)); }
.lb-card.q3.expanded::before { background: conic-gradient(from 0deg, hsl(145 60% 50%), transparent 60%, hsl(145 60% 50%)); }
.lb-card.q5.expanded::before { background: conic-gradient(from 0deg, hsl(38 80% 55%), transparent 60%, hsl(38 80% 55%)); }

.lb-expanded {
  max-height: 0; overflow: hidden;
  transition: max-height 0.35s ease, padding-top 0.35s ease;
  position: relative; z-index: 5;
}
.lb-card.expanded .lb-expanded { max-height: 600px; padding-top: 12px; }
.lb-card.expanded .lb-expand-btn { transform: rotate(180deg); }
[data-theme="light"] .lb-card.expanded .lb-expand-btn,
:root:not([data-theme]) .lb-card.expanded .lb-expand-btn { box-shadow: var(--shadow-pressed); }
.lb-card.expanded .lb-expand-btn svg { stroke: var(--accent); }

.lb-body {
  font-size: 12px; line-height: 1.5;
  color: var(--text-muted);
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  margin-bottom: 10px;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .lb-body, :root:not([data-theme]) .lb-body { background: var(--surface); box-shadow: var(--shadow-pressed); border: none; }
[data-theme="dark"] .lb-body { background: hsl(220 25% 4% / 0.6); }

.lb-actions { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
.lb-action {
  flex: 1; min-width: 0;
  height: 36px; padding: 0 12px;
  border-radius: var(--radius-sm);
  display: inline-flex; align-items: center; justify-content: center; gap: 4px;
  font-size: 11.5px; font-weight: 700;
  color: var(--text);
  border: 1px solid var(--border-color);
  white-space: nowrap;
  text-decoration: none;
  transition: box-shadow var(--dur) var(--ease);
}
[data-theme="light"] .lb-action, :root:not([data-theme]) .lb-action { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="light"] .lb-action:active, :root:not([data-theme]) .lb-action:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-action { background: hsl(220 25% 8%); }
.lb-action.primary {
  color: white; border: none;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.4);
}

.lb-feedback { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; color: var(--text-muted); }
.lb-fb-label { font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.lb-fb-btn {
  width: 30px; height: 30px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  border: 1px solid var(--border-color);
  font-size: 13px;
  cursor: pointer;
}
[data-theme="light"] .lb-fb-btn, :root:not([data-theme]) .lb-fb-btn { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="light"] .lb-fb-btn:active, :root:not([data-theme]) .lb-fb-btn:active, .lb-fb-btn.selected { box-shadow: var(--shadow-pressed); transform: scale(0.92); }
[data-theme="dark"] .lb-fb-btn { background: hsl(220 25% 8%); }

/* Empty state (no insights) */
.lb-empty { padding: 20px 14px; text-align: center; margin-bottom: 14px; }
.lb-empty > * { position: relative; z-index: 5; }
.lb-empty-icon { font-size: 36px; margin-bottom: 8px; }
.lb-empty-text { font-size: 14px; font-weight: 800; margin-bottom: 4px; }
.lb-empty-sub { font-size: 12px; font-weight: 600; color: var(--text-muted); }

/* ═══ INFO POPOVER OVERLAY ═══ */
.info-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.4);
  backdrop-filter: blur(8px);
  z-index: 100;
  display: none;
  align-items: center; justify-content: center;
  padding: 16px;
  animation: fadeIn 0.2s ease;
}
.info-overlay.active { display: flex; }
[data-theme="light"] .info-overlay, :root:not([data-theme]) .info-overlay { background: rgba(163,177,198,0.5); }

.info-card {
  width: 100%; max-width: 380px;
  border-radius: var(--radius);
  padding: 18px 16px;
  position: relative;
  border: 1px solid var(--border-color);
  animation: popUp 0.3s var(--ease-spring) both;
}
[data-theme="light"] .info-card, :root:not([data-theme]) .info-card { background: var(--surface); box-shadow: var(--shadow-card); border: none; }
[data-theme="dark"] .info-card { background: linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .9), hsl(var(--hue1) 50% 6% / .9)); backdrop-filter: blur(20px); box-shadow: 0 24px 48px hsl(220 50% 4% / 0.6); }

.info-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.info-card-ic {
  width: 44px; height: 44px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center; flex-shrink: 0;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .info-card-ic, :root:not([data-theme]) .info-card-ic { background: var(--surface); box-shadow: var(--shadow-pressed); border: none; }
[data-theme="dark"] .info-card-ic { background: hsl(220 25% 4%); }
.info-card-ic svg { width: 22px; height: 22px; fill: none; stroke-width: 2; }
.info-card-title { flex: 1; font-size: 16px; font-weight: 800; letter-spacing: -0.01em; }
.info-card-close {
  width: 32px; height: 32px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center; flex-shrink: 0;
}
[data-theme="light"] .info-card-close, :root:not([data-theme]) .info-card-close { background: var(--surface); box-shadow: var(--shadow-card-sm); }
[data-theme="dark"] .info-card-close { background: hsl(220 25% 8%); }
.info-card-close svg { width: 14px; height: 14px; stroke: var(--text); fill: none; stroke-width: 2.5; }

.info-card-body { font-size: 13px; font-weight: 600; color: var(--text-muted); line-height: 1.45; margin-bottom: 14px; }
.info-card-body b { color: var(--text); font-weight: 800; }

.info-card-voice {
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  margin-bottom: 14px;
}
[data-theme="light"] .info-card-voice, :root:not([data-theme]) .info-card-voice { background: var(--surface); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .info-card-voice { background: hsl(220 25% 4%); border: 1px solid hsl(280 50% 25% / 0.4); }
.info-card-voice-label { font-family: var(--font-mono); font-size: 8.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--magic); margin-bottom: 4px; display: flex; align-items: center; gap: 4px; }
[data-theme="dark"] .info-card-voice-label { color: hsl(280 70% 70%); }
.info-card-voice-label svg { width: 10px; height: 10px; stroke: currentColor; fill: none; stroke-width: 2; }
.info-card-voice-text { font-size: 12px; font-weight: 700; color: var(--text); font-style: italic; line-height: 1.4; }
.info-card-voice-text::before { content: '"'; opacity: 0.5; }
.info-card-voice-text::after { content: '"'; opacity: 0.5; }

.info-card-cta {
  width: 100%;
  height: 46px; padding: 0 14px;
  border-radius: var(--radius-sm);
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  font-size: 13px; font-weight: 800;
  color: white; border: none;
  position: relative; overflow: hidden;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 14px hsl(var(--hue1) 80% 50% / 0.4);
  text-decoration: none;
}
[data-theme="dark"] .info-card-cta { background: linear-gradient(135deg, hsl(var(--hue1) 80% 55%), hsl(var(--hue2) 80% 55%)); }
.info-card-cta::before { content: ''; position: absolute; inset: 0; background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.35) 85%, transparent 100%); animation: conicSpin 3s linear infinite; }
.info-card-cta > * { position: relative; z-index: 1; }
.info-card-cta svg { width: 14px; height: 14px; stroke: white; fill: none; stroke-width: 2.5; }

/* ═══ ANIMATIONS ═══ */
@keyframes orbSpin { to { transform: rotate(360deg); } }
@keyframes conicSpin { to { transform: rotate(360deg); } }
@keyframes auroraDrift { 0%,100% { transform: translate(0,0) scale(1); } 33% { transform: translate(30px,-25px) scale(1.1); } 66% { transform: translate(-25px,35px) scale(0.95); } }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes popUp { from { opacity: 0; transform: scale(0.9) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
}

/* Backwards-compatibility legacy s87v3-* classes (rendered, no special CSS needed) */
.s87v3-pagein, .s87v3-stagger, .s87v3-tap, .s87v3-scroll-reveal { /* placeholder */ }
</style>
</head>
<body>

<?php include __DIR__ . '/partials/header.php'; ?>

<div class="aurora" aria-hidden="true">
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
</div>

<!-- Mode toggle row (Подробен →) -->
<div class="lb-mode-row">
  <a href="<?= htmlspecialchars(lbWith('/chat.php')) ?>" class="lb-mode-toggle s87v3-tap" title="Подробен режим">
    <span>Подробен</span>
    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  </a>
</div>

<main class="app s87v3-pagein">

  <!-- ═══ TOP ROW (Днес + Времето) ═══ -->
  <div class="top-row">
    <div class="glass sm cell qd">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="cell-header-row">
        <div class="cell-label">Днес · <?= htmlspecialchars($store_name) ?></div>
        <?php if (!empty($all_stores) && count($all_stores) > 1): ?>
        <select class="lb-store-picker" onchange="location.href='?store='+this.value" aria-label="Магазин">
          <?php foreach ($all_stores as $st): ?>
          <option value="<?= (int)$st['id'] ?>" <?= $st['id']==$store_id?'selected':'' ?>><?= htmlspecialchars($st['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
      </div>
      <div class="cell-numrow">
        <span class="cell-num"><?= number_format(round($rev_today), 0, '.', ' ') ?></span>
        <span class="cell-cur"><?= $cs ?></span>
        <span class="cell-pct <?= $cmp_class ?>"><?= $cmp_sign . $cmp_pct ?>%</span>
      </div>
      <div class="cell-meta"><?= $cnt_today ?> продажби<?php if ($role === 'owner' && $profit_today > 0): ?> · <?= number_format(round($profit_today), 0, '.', ' ') ?> печалба<?php endif; ?></div>
    </div>

    <?php if ($weather_today): ?>
    <div class="glass sm cell qd">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="weather-cell-top">
        <span class="weather-cell-icon"><svg viewBox="0 0 24 24"><?= lbWmoSvg((int)$weather_today['weather_code']) ?></svg></span>
        <span class="weather-cell-temp"><?= round($weather_today['temp_max']) ?>°</span>
      </div>
      <div class="weather-cell-cond"><?= lbWmoText((int)$weather_today['weather_code']) ?></div>
      <div class="cell-meta"><?= round($weather_today['temp_min']) ?>°/<?= round($weather_today['temp_max']) ?>° · Дъжд <?= (int)$weather_today['precipitation_prob'] ?>%</div>
    </div>
    <?php else: ?>
    <div class="glass sm cell qd">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <div class="weather-cell-top">
        <span class="weather-cell-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/></svg></span>
        <span class="weather-cell-temp">—</span>
      </div>
      <div class="weather-cell-cond">Времето</div>
      <div class="cell-meta">—</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ═══ OPS GRID — 4 buttons (с info popover бутончета) ═══ -->
  <div class="ops-section">
    <div class="ops-grid">

      <a href="<?= htmlspecialchars(lbWith('/sale.php')) ?>" class="glass sm op-btn s87v3-tap q3">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <button type="button" class="op-info-btn" onclick="event.preventDefault();event.stopPropagation();openInfo('sell')" aria-label="Инфо">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        </button>
        <div class="op-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1.5"/><circle cx="18" cy="21" r="1.5"/><path d="M3 3h2l2.7 12.3a2 2 0 002 1.7h7.6a2 2 0 002-1.5L21 8H6"/></svg></div>
        <div class="op-label">Продай</div>
      </a>

      <a href="<?= htmlspecialchars(lbWith('/products.php')) ?>" class="glass sm op-btn s87v3-tap qd">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <button type="button" class="op-info-btn" onclick="event.preventDefault();event.stopPropagation();openInfo('inventory')" aria-label="Инфо">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        </button>
        <div class="op-icon"><svg viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg></div>
        <div class="op-label">Стоката</div>
      </a>

      <a href="<?= htmlspecialchars(lbWith($op_deliveries_url)) ?>" class="glass sm op-btn s87v3-tap q5">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <button type="button" class="op-info-btn" onclick="event.preventDefault();event.stopPropagation();openInfo('delivery')" aria-label="Инфо">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        </button>
        <div class="op-icon"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div>
        <div class="op-label">Доставка</div>
      </a>

      <a href="<?= htmlspecialchars(lbWith($op_orders_url)) ?>" class="glass sm op-btn s87v3-tap q2">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <button type="button" class="op-info-btn" onclick="event.preventDefault();event.stopPropagation();openInfo('order')" aria-label="Инфо">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        </button>
        <div class="op-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg></div>
        <div class="op-label">Поръчка</div>
      </a>

    </div>
  </div>

  <!-- ═══ AI STUDIO ROW ═══ -->
  <div class="studio-row">
    <a href="<?= htmlspecialchars(lbWith('/ai-studio.php')) ?>" class="glass sm studio-btn s87v3-tap" aria-label="AI Studio">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <span class="studio-icon">
        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      </span>
      <span class="studio-text">
        <span class="studio-label">AI Studio</span>
        <?php if ($ai_studio_count > 0): ?>
        <span class="studio-sub"><?= $ai_studio_count ?> ЧАКАТ</span>
        <?php else: ?>
        <span class="studio-sub">КАТАЛОГ &amp; СНИМКИ</span>
        <?php endif; ?>
      </span>
      <?php if ($ai_studio_count > 0): ?>
      <span class="studio-badge"><?= $ai_studio_count > 99 ? '99+' : $ai_studio_count ?></span>
      <?php endif; ?>
    </a>
  </div>

  <?php if (!empty($weather_14)): ?>
  <!-- ═══ WEATHER FORECAST CARD (14 дни + AI препоръки) ═══ -->
  <div class="glass sm wfc q4" data-range="3">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>

    <div class="wfc-head">
      <div class="wfc-head-ic">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
      </div>
      <div class="wfc-head-text">
        <div class="wfc-title">Прогноза за времето</div>
        <div class="wfc-sub">AI препоръки за седмицата</div>
      </div>
    </div>

    <div class="wfc-tabs">
      <button type="button" class="wfc-tab" data-tab="3" onclick="wfcSetRange('3')">3д</button>
      <button type="button" class="wfc-tab" data-tab="7" onclick="wfcSetRange('7')">7д</button>
      <button type="button" class="wfc-tab" data-tab="14" onclick="wfcSetRange('14')">14д</button>
    </div>

    <div class="wfc-days">
      <?php foreach ($weather_14 as $idx => $w):
        $is_today = ($w['forecast_date'] === date('Y-m-d'));
        $cls = lbWmoClass((int)$w['weather_code']);
        $rain_pct = (int)$w['precipitation_prob'];
        $rain_dry = $rain_pct < 30;
      ?>
      <div class="wfc-day<?= $is_today ? ' today' : '' ?> <?= $cls ?>">
        <div class="wfc-day-name"><?= $is_today ? 'Днес' : lbDayName($w['forecast_date']) ?></div>
        <div class="wfc-day-ic"><?= lbWmoDayIcon((int)$w['weather_code']) ?></div>
        <div class="wfc-day-temp"><?= round($w['temp_max']) ?>°<small>/<?= round($w['temp_min']) ?></small></div>
        <div class="wfc-day-rain<?= $rain_dry ? ' dry' : '' ?>"><?= $rain_pct ?>%</div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="wfc-recs-divider"><span>AI препоръки</span></div>

    <!-- AI recs: placeholder Bulgarian text — backend integration pending (S132 HANDOFF) -->
    <div class="wfc-rec window">
      <span class="wfc-rec-ic">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
      </span>
      <div class="wfc-rec-text">
        <span class="wfc-rec-label">Витрина</span>
        <div class="wfc-rec-body">Прогноза за следващите дни — AI ще предложи какво да изложиш.</div>
      </div>
    </div>

    <div class="wfc-rec order">
      <span class="wfc-rec-ic">
        <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.7l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.7l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.3 7 12 12 20.7 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg>
      </span>
      <div class="wfc-rec-text">
        <span class="wfc-rec-label">Поръчка</span>
        <div class="wfc-rec-body">AI ще препоръча какво да поръчаш според времето и продажбите.</div>
      </div>
    </div>

    <div class="wfc-rec transfer">
      <span class="wfc-rec-ic">
        <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
      </span>
      <div class="wfc-rec-text">
        <span class="wfc-rec-label">Прехвърли</span>
        <div class="wfc-rec-body">Между магазини — според локалното време.</div>
      </div>
    </div>

    <div class="wfc-source">
      <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12.01" y2="8"/><line x1="11" y1="12" x2="12" y2="16"/></svg>
      <span>OPEN-METEO · Обновено <?= date('H:i') ?></span>
    </div>
  </div>
  <?php endif; ?>

  <!-- ═══ AI HELP CARD ═══ -->
  <div class="glass sm help-card qhelp">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>

    <div class="help-head">
      <div class="help-head-ic">
        <svg viewBox="0 0 24 24"><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/><circle cx="12" cy="12" r="10"/></svg>
      </div>
      <div class="help-head-text">
        <div class="help-title">AI ти помага</div>
        <div class="help-sub">Питай за всичко — каталог, продажби, тенденции</div>
      </div>
    </div>

    <div class="help-body">
      Просто пиши или говори. <b>AI знае всичко за магазина ти</b>. Не се нуждаеш от меню — само попитай.
    </div>

    <div class="help-chips-label">Опитай да попиташ</div>
    <div class="help-chips">
      <button type="button" class="help-chip s87v3-tap" onclick="lbOpenChat(event,'Какво ми тежи на склада')"><span class="help-chip-q">?</span><span>Какво ми тежи на склада</span></button>
      <button type="button" class="help-chip s87v3-tap" onclick="lbOpenChat(event,'Кои са топ продавачи')"><span class="help-chip-q">?</span><span>Кои са топ продавачи</span></button>
      <button type="button" class="help-chip s87v3-tap" onclick="lbOpenChat(event,'Колко да поръчам')"><span class="help-chip-q">?</span><span>Колко да поръчам</span></button>
      <button type="button" class="help-chip s87v3-tap" onclick="lbOpenChat(event,'Защо приходите паднаха')"><span class="help-chip-q">?</span><span>Защо приходите паднаха</span></button>
      <button type="button" class="help-chip s87v3-tap" onclick="lbOpenChat(event,'Покажи ми Adidas 42')"><span class="help-chip-q">?</span><span>Покажи Adidas 42</span></button>
      <button type="button" class="help-chip s87v3-tap" onclick="lbOpenChat(event,'Какво продаваме днес')"><span class="help-chip-q">?</span><span>Какво продаваме днес</span></button>
    </div>

    <div class="help-video-ph">
      <span class="help-video-ic"><svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></span>
      <div class="help-video-text">
        <div class="help-video-title">Видео урок</div>
        <div class="help-video-sub">Скоро</div>
      </div>
    </div>

    <a href="<?= htmlspecialchars(lbWith('/chat.php')) ?>" class="help-link-row">
      <span>Виж всички възможности</span>
      <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <!-- ═══ LIFE BOARD HEADER + COLLAPSED CARDS ═══ -->
  <div class="lb-header">
    <div class="lb-title">
      <div class="lb-title-orb"></div>
      <span class="lb-title-text">Life Board</span>
    </div>
    <span class="lb-count"><?= count($picked) ?> неща · <?= date('H:i') ?></span>
  </div>

  <?php if (!empty($picked)): ?>
    <?php foreach ($picked as $p):
      $ins = $p['ins'];
      $meta = $fq_meta[$p['fq']] ?? ['q'=>'q3','emoji'=>'•','name'=>''];
      $action = lbInsightAction($ins);
      $title_js = htmlspecialchars(addslashes($ins['title']), ENT_QUOTES);
      $svg_html = $fq_svg[$meta['q']] ?? $fq_svg['q3'];
      $card_class = 'glass sm lb-card ' . $meta['q'] . ($p['expanded'] ? ' expanded' : '');
    ?>
    <div class="<?= $card_class ?>" data-topic="<?= htmlspecialchars($ins['topic_id'] ?? '', ENT_QUOTES) ?>">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
        <span class="lb-emoji-orb"><?= $svg_html ?></span>
        <div class="lb-collapsed-content">
          <span class="lb-fq-tag-mini"><?= htmlspecialchars($meta['name']) ?></span>
          <span class="lb-collapsed-title"><?= htmlspecialchars($ins['title']) ?></span>
        </div>
        <button type="button" class="lb-expand-btn s87v3-tap" aria-label="Разгъни"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
      </div>
      <div class="lb-expanded">
        <?php if (!empty($ins['detail_text'])): ?>
        <div class="lb-body"><?= htmlspecialchars($ins['detail_text']) ?></div>
        <?php endif; ?>
        <div class="lb-actions">
          <button type="button" class="lb-action s87v3-tap" onclick="lbOpenChat(event,'<?= $title_js ?>')">Защо?</button>
          <?php if ($action['type'] === 'deeplink' && $action['url']): ?>
          <a class="lb-action s87v3-tap" href="<?= htmlspecialchars(lbWith($action['url'])) ?>">Покажи</a>
          <a class="lb-action primary s87v3-tap" href="<?= htmlspecialchars(lbWith($action['url'])) ?>"><?= htmlspecialchars($action['label']) ?> →</a>
          <?php else: ?>
          <button type="button" class="lb-action s87v3-tap" onclick="lbOpenChat(event,'<?= $title_js ?>')">Покажи</button>
          <button type="button" class="lb-action primary s87v3-tap" onclick="lbOpenChat(event,'<?= $title_js ?>')"><?= htmlspecialchars($action['label']) ?> →</button>
          <?php endif; ?>
        </div>
        <div class="lb-feedback">
          <span class="lb-fb-label">Полезно?</span>
          <button type="button" class="lb-fb-btn s87v3-tap" data-fb="up" onclick="lbSelectFeedback(event,this)" aria-label="Полезно">👍</button>
          <button type="button" class="lb-fb-btn s87v3-tap" data-fb="down" onclick="lbSelectFeedback(event,this)" aria-label="Безполезно">👎</button>
          <button type="button" class="lb-fb-btn s87v3-tap" data-fb="hmm" onclick="lbSelectFeedback(event,this)" aria-label="Неясно">🤔</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if ($remaining_after_picked > 0): ?>
    <div class="see-more-mini"><a href="<?= htmlspecialchars(lbWith('/chat.php#all')) ?>">Виж всички <?= $total_insights ?> →</a></div>
    <?php endif; ?>
  <?php else: ?>
    <div class="glass sm lb-empty q3">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="lb-empty-icon">🌿</div>
      <div class="lb-empty-text">Всичко е тихо днес</div>
      <div class="lb-empty-sub">Няма нищо спешно. Действай отгоре.</div>
    </div>
  <?php endif; ?>

</main>

<!-- ═══ INFO POPOVER OVERLAY ═══ -->
<div class="info-overlay" id="infoOverlay" onclick="if(event.target===this)closeInfo()">
  <div class="info-card">
    <div class="info-card-head">
      <div class="info-card-ic" id="infoIc"></div>
      <div class="info-card-title" id="infoTitle"></div>
      <button type="button" class="info-card-close" onclick="closeInfo()" aria-label="Затвори">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="info-card-body" id="infoBody"></div>
    <div class="info-card-voice">
      <div class="info-card-voice-label">
        <svg viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0014 0v-2"/></svg>
        <span>Опитай с глас</span>
      </div>
      <div class="info-card-voice-text" id="infoVoice"></div>
    </div>
    <a class="info-card-cta" id="infoCta" href="#">
      <span id="infoCtaLabel"></span>
      <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
  </div>
</div>

<?php include __DIR__ . '/partials/chat-input-bar.php'; ?>
<?php include __DIR__ . '/partials/shell-scripts.php'; ?>

<script>
// ─── Info popover content per ops button (P10) ───
const INFO_DATA = {
  sell: {
    title: 'Продай',
    iconSvg: '<svg viewBox="0 0 24 24" style="stroke:hsl(145 60% 45%);"><circle cx="9" cy="21" r="1.5"/><circle cx="18" cy="21" r="1.5"/><path d="M3 3h2l2.7 12.3a2 2 0 002 1.7h7.6a2 2 0 002-1.5L21 8H6"/></svg>',
    body: 'Регистрирай <b>нова продажба</b>. Скенирай или избери продукти, въведи количества и цена, приеми плащане. AI може да попълни всичко по глас.',
    voice: 'Продай 2 черни тениски Nike размер L',
    cta: 'Отвори продажба',
    href: '<?= addslashes(lbWith('/sale.php')) ?>'
  },
  inventory: {
    title: 'Стоката',
    iconSvg: '<svg viewBox="0 0 24 24" style="stroke:hsl(255 70% 60%);"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
    body: 'Управлявай <b>артикулите</b> в магазина. Добавяй нови, редактирай цени, преглеждай наличности, печатай етикети. Може с глас или ръчно.',
    voice: 'Покажи Nike размер 42',
    cta: 'Отвори стоката',
    href: '<?= addslashes(lbWith('/products.php')) ?>'
  },
  delivery: {
    title: 'Доставка',
    iconSvg: '<svg viewBox="0 0 24 24" style="stroke:hsl(38 80% 50%);"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
    body: 'Получавай <b>нова стока</b>. Скенирай за бърз вход или снимай фактурата (AI чете 30 артикула за 30 секунди). Сравнява с поръчката.',
    voice: 'Снимай фактура от Иватекс',
    cta: 'Нова доставка',
    href: '<?= addslashes(lbWith($op_deliveries_url)) ?>'
  },
  order: {
    title: 'Поръчка',
    iconSvg: '<svg viewBox="0 0 24 24" style="stroke:hsl(280 60% 50%);"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>',
    body: 'Управлявай <b>поръчки към доставчици</b>. AI препоръчва какво да поръчаш базирано на продажбите и минимумите. Изпращай по email или WhatsApp.',
    voice: 'Какво да поръчам от Nike',
    cta: 'Нова поръчка',
    href: '<?= addslashes(lbWith($op_orders_url)) ?>'
  }
};

function openInfo(key) {
  const d = INFO_DATA[key];
  if (!d) return;
  document.getElementById('infoIc').innerHTML = d.iconSvg;
  document.getElementById('infoTitle').textContent = d.title;
  document.getElementById('infoBody').innerHTML = d.body;
  document.getElementById('infoVoice').textContent = d.voice;
  document.getElementById('infoCtaLabel').textContent = d.cta;
  document.getElementById('infoCta').setAttribute('href', d.href);
  document.getElementById('infoOverlay').classList.add('active');
}
function closeInfo() {
  document.getElementById('infoOverlay').classList.remove('active');
}

function wfcSetRange(r) {
  var card = document.querySelector('.wfc');
  if (card) card.setAttribute('data-range', r);
}

// ─── Toggle card (collapsed ↔ expanded) ───
function lbToggleCard(e, row){
    if (e.target.closest('.lb-fb-btn') || e.target.closest('.lb-action')) return;
    var card = row.closest('.lb-card');
    if (!card) return;
    card.classList.toggle('expanded');
    if (navigator.vibrate) navigator.vibrate(6);
}
function lbSelectFeedback(e, btn){
    e.stopPropagation();
    var card = btn.closest('.lb-card'); if (!card) return;
    card.querySelectorAll('.lb-fb-btn').forEach(function(b){ b.classList.remove('selected'); });
    btn.classList.add('selected');
    if (navigator.vibrate) navigator.vibrate(8);
}
// Лесен режим няма локален chat overlay — препращаме към chat.php с подсказан въпрос
function lbOpenChat(e, q){
    if (e) e.stopPropagation();
    try { sessionStorage.setItem('rms_pending_q', q); } catch(_) {}
    window.location.href = '/chat.php?from=lifeboard&q=' + encodeURIComponent(q);
}
// chat-input-bar.php calls rmsOpenChat(); on Лесен → just route to chat.php
window.rmsOpenChat = function(){ window.location.href = '/chat.php?from=lifeboard'; };

/* ───────────────────────────────────────────── */
/* S87.ANIMATIONS v3 — portable JS (idempotent)  */
/* ───────────────────────────────────────────── */
(function s87v3_init(){
    if (window.__s87v3_loaded) return;
    window.__s87v3_loaded = true;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reduced) {
        var headerEl = document.querySelector('.rms-header') || document.querySelector('.header');
        var lastScroll = 0;
        window.addEventListener('scroll', function(){
            var y = window.scrollY;
            if (headerEl) {
                if (y > 30 && lastScroll <= 30) headerEl.classList.add('scrolled');
                else if (y <= 30 && lastScroll > 30) headerEl.classList.remove('scrolled');
            }
            lastScroll = y;
        }, { passive: true });
    }
    if (!reduced && 'IntersectionObserver' in window) {
        var obs = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                if (entry.isIntersecting) {
                    entry.target.style.animation = 's87v3_scrollIn 0.7s cubic-bezier(0.34,1.8,0.64,1) both';
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
        var attach = function(){
            document.querySelectorAll('.s87v3-scroll-reveal').forEach(function(el){ obs.observe(el); });
        };
        if (document.readyState !== 'loading') attach();
        else document.addEventListener('DOMContentLoaded', attach);
    }
    if (!reduced) {
        var attachTap = function(){
            document.querySelectorAll('.s87v3-tap').forEach(function(el){
                if (el.__s87v3_tap) return;
                el.__s87v3_tap = true;
                var handler = function(){
                    el.classList.remove('s87v3-released');
                    void el.offsetWidth;
                    el.classList.add('s87v3-released');
                    setTimeout(function(){ el.classList.remove('s87v3-released'); }, 400);
                };
                el.addEventListener('touchend', handler, { passive: true });
                el.addEventListener('mouseup', handler);
            });
        };
        if (document.readyState !== 'loading') attachTap();
        else document.addEventListener('DOMContentLoaded', attachTap);
    }
})();
</script>
</body>
</html>
