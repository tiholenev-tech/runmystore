<?php
/**
 * life-board.php — Лесен режим (S82.VISUAL)
 *
 * Compact 2-column dashboard + collapsible Life Board cards + 4 big
 * operational glass buttons. Companion to chat.php (Подробен режим).
 *
 * Visual: simple-mode-GLASS.html mockup (Tihol-approved).
 * Auth + tenant load: same pattern as chat.php.
 *
 * NO new endpoints, NO new DB queries — only copies of existing
 * patterns from chat.php (today's revenue, today's weather, AI Studio
 * pending count, getInsightsForModule for the 4 mini cards).
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// S136.PARTIALS_STANDARD — life-board IS the simple-mode home page. Setting
// the flag on every load lets partials/header.php render a back-arrow on any
// detailed-mode page the user navigates to from here. Cleared only by logout
// (session_destroy) or an explicit "switch to extended mode" toggle (TBD).
$_SESSION['mode'] = 'simple';

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
$cmp_class = $cmp_pct > 0 ? '' : ($cmp_pct < 0 ? 'neg' : 'zero');
$cmp_sign  = $cmp_pct > 0 ? '+' : '';

// ─── Today weather (mini) — copy of chat.php pattern ───
$weather_today = null;
try {
    $weather_today = DB::run(
        'SELECT temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date=CURDATE() LIMIT 1',
        [$store_id])->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

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
    'loss'       => ['q'=>'q1', 'emoji'=>'🔴', 'name'=>'Какво губиш'],
    'loss_cause' => ['q'=>'q2', 'emoji'=>'🟣', 'name'=>'От какво губиш'],
    'gain'       => ['q'=>'q3', 'emoji'=>'🟢', 'name'=>'Какво печелиш'],
    'gain_cause' => ['q'=>'q4', 'emoji'=>'💎', 'name'=>'От какво печелиш'],
    'order'      => ['q'=>'q5', 'emoji'=>'🟡', 'name'=>'Поръчай'],
    'anti_order' => ['q'=>'q6', 'emoji'=>'⚪', 'name'=>'НЕ поръчвай'],
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
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/theme.css?v=<?= @filemtime(__DIR__.'/css/theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/css/shell.css?v=<?= @filemtime(__DIR__.'/css/shell.css') ?: 1 ?>">
<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>
<style>
/* ════════════════════════════════════════════════════════════════════
   life-board.php — DESIGN_SYSTEM v4.0 BICHROMATIC
   Light default + Dark SACRED Neon Glass
   ════════════════════════════════════════════════════════════════════ */

/* ═══ CONTINUITY TOKENS (същи в двата режима) ═══ */
:root {
  --hue1: 255;
  --hue2: 222;
  --hue3: 180;
  --radius: 22px;
  --radius-sm: 14px;
  --radius-pill: 999px;
  --radius-icon: 50%;
  --border: 1px;
  --ease: cubic-bezier(0.5, 1, 0.89, 1);
  --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
  --dur-fast: 150ms;
  --dur: 250ms;
  --dur-slow: 350ms;
  --press: 0.97;
  --font: 'Montserrat', sans-serif;
  --font-mono: 'DM Mono', ui-monospace, monospace;
}

/* ═══ LIGHT THEME (default) ═══ */
:root:not([data-theme]),
:root[data-theme="light"] {
  --bg-main: #e0e5ec;
  --surface: #e0e5ec;
  --surface-2: #d1d9e6;
  --border-color: transparent;
  --text: #2d3748;
  --text-muted: #64748b;
  --text-faint: #94a3b8;
  --shadow-light: #ffffff;
  --shadow-dark: #a3b1c6;
  --neu-d: 8px;
  --neu-b: 16px;
  --neu-d-s: 4px;
  --neu-b-s: 8px;
  --shadow-card:
    var(--neu-d) var(--neu-d) var(--neu-b) var(--shadow-dark),
    calc(var(--neu-d) * -1) calc(var(--neu-d) * -1) var(--neu-b) var(--shadow-light);
  --shadow-card-sm:
    var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark),
    calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);
  --shadow-pressed:
    inset var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark),
    inset calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);
  --accent: oklch(0.62 0.22 285);
  --accent-2: oklch(0.65 0.25 305);
  --accent-3: oklch(0.78 0.18 195);
  --q1-loss: oklch(0.65 0.22 25);
  --q2-why-loss: oklch(0.65 0.25 305);
  --q3-gain: oklch(0.68 0.18 155);
  --q4-why-gain: oklch(0.72 0.18 195);
  --q5-order: oklch(0.72 0.18 70);
  --q6-no-order: oklch(0.62 0.05 220);
  --success: oklch(0.68 0.18 155);
  --warning: oklch(0.72 0.18 70);
  --danger: oklch(0.65 0.22 25);
  --aurora-blend: multiply;
  --aurora-opacity: 0.35;
}

/* ═══ DARK THEME (SACRED Neon Glass — preserved 1:1) ═══ */
:root[data-theme="dark"] {
  --bg-main: #08090d;
  --surface: hsl(220, 25%, 4.8%);
  --surface-2: hsl(220, 25%, 8%);
  --border-color: hsl(var(--hue2), 12%, 20%);
  --text: #f1f5f9;
  --text-muted: rgba(255, 255, 255, 0.6);
  --text-faint: rgba(255, 255, 255, 0.4);
  --shadow-card:
    hsl(var(--hue2) 50% 2%) 0 10px 16px -8px,
    hsl(var(--hue2) 50% 4%) 0 20px 36px -14px;
  --shadow-card-sm: hsl(var(--hue2) 50% 2%) 0 4px 8px -2px;
  --shadow-pressed: inset 0 2px 4px hsl(var(--hue2) 50% 2%);
  --accent: hsl(var(--hue1), 80%, 65%);
  --accent-2: hsl(var(--hue2), 80%, 65%);
  --accent-3: hsl(var(--hue3), 70%, 55%);
  --q1-loss: hsl(0, 85%, 60%);
  --q2-why-loss: hsl(280, 70%, 65%);
  --q3-gain: hsl(145, 70%, 55%);
  --q4-why-gain: hsl(175, 70%, 55%);
  --q5-order: hsl(38, 90%, 60%);
  --q6-no-order: hsl(220, 10%, 60%);
  --success: hsl(145, 70%, 55%);
  --warning: hsl(38, 90%, 60%);
  --danger: hsl(0, 85%, 60%);
  --aurora-blend: plus-lighter;
  --aurora-opacity: 0.35;
}

/* ═══ RESET ═══ */
*, *::before, *::after {
  margin: 0; padding: 0; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
}

html, body {
  font-family: var(--font);
  color: var(--text);
  background: var(--bg-main);
  min-height: 100vh;
  padding-top: 56px;
  padding-bottom: calc(64px + 24px + env(safe-area-inset-bottom, 0));
  /* no transition — prevents flicker */
}

[data-theme="dark"] body {
  background:
    radial-gradient(ellipse 800px 500px at 20% 10%,
      hsl(var(--hue1) 60% 35% / .22) 0%, transparent 60%),
    radial-gradient(ellipse 700px 500px at 85% 85%,
      hsl(var(--hue2) 60% 35% / .22) 0%, transparent 60%),
    linear-gradient(180deg, #0a0b14 0%, #050609 100%);
  background-attachment: fixed;
}

/* ═══ AURORA BACKGROUND (Effect #1) ═══ */
.aurora {
  position: fixed; inset: 0;
  overflow: hidden; pointer-events: none;
  z-index: 0;
}
.aurora-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(60px);
  opacity: var(--aurora-opacity);
  mix-blend-mode: var(--aurora-blend);
  animation: auroraDrift 20s ease-in-out infinite;
}
.aurora-blob:nth-child(1) {
  width: 280px; height: 280px;
  background: hsl(var(--hue1), 80%, 60%);
  top: -60px; left: -80px;
  animation-delay: 0s;
}
.aurora-blob:nth-child(2) {
  width: 240px; height: 240px;
  background: hsl(var(--hue3), 70%, 60%);
  top: 35%; right: -100px;
  animation-delay: 4s;
}
.aurora-blob:nth-child(3) {
  width: 200px; height: 200px;
  background: hsl(var(--hue2), 80%, 60%);
  bottom: 80px; left: -50px;
  animation-delay: 8s;
}

/* ═══ APP CONTAINER ═══ */
.app {
  position: relative;
  z-index: 1;
  max-width: 480px;
  margin: 0 auto;
}
.scroll {
  padding: 8px 16px 0;
}

/* ═══ MODE TOGGLE ROW ═══ */
.lb-mode-row {
  display: flex; justify-content: flex-end;
  padding: 8px 16px 12px;
  position: relative; z-index: 5;
}
.lb-mode-toggle {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 8px 14px;
  border-radius: var(--radius-pill);
  color: var(--text-muted);
  font-size: 11px; font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  border: 1px solid var(--border-color);
  transition: all var(--dur) var(--ease);
}
[data-theme="light"] .lb-mode-toggle,
:root:not([data-theme]) .lb-mode-toggle {
  background: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: none;
}
[data-theme="light"] .lb-mode-toggle:active,
:root:not([data-theme]) .lb-mode-toggle:active {
  box-shadow: var(--shadow-pressed);
}
[data-theme="dark"] .lb-mode-toggle {
  background: hsl(220 25% 8% / 0.7);
  backdrop-filter: blur(8px);
}
.lb-mode-toggle:hover { color: var(--accent); }
.lb-mode-toggle svg {
  width: 12px; height: 12px;
  stroke: currentColor; fill: none; stroke-width: 2.5;
}

/* ═══ TOP ROW (Днес + Времето) ═══ */
.top-row {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 12px;
  margin-bottom: 18px;
  position: relative;
}

/* ═══ GLASS COMPONENT (SACRED + Light convex) ═══ */
.glass {
  position: relative;
  border-radius: var(--radius);
  border: var(--border) solid var(--border-color);
  isolation: isolate;
}
.glass.sm { --radius: var(--radius-sm); }

.glass .shine, .glass .glow { --hue: var(--hue1); }
.glass .shine-bottom, .glass .glow-bottom {
  --hue: var(--hue2);
  --conic: 135deg;
}

/* LIGHT — Neumorphism convex */
[data-theme="light"] .glass,
:root:not([data-theme]) .glass {
  background: var(--surface);
  box-shadow: var(--shadow-card);
  border: none;
}
[data-theme="light"] .glass .shine,
[data-theme="light"] .glass .glow,
:root:not([data-theme]) .glass .shine,
:root:not([data-theme]) .glass .glow {
  display: none;
}

/* DARK — SACRED Neon Glass */
[data-theme="dark"] .glass {
  background:
    linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%),
    linear-gradient(45deg, hsl(var(--hue2) 50% 10% / .8), hsl(var(--hue2) 50% 10% / 0) 33%),
    linear-gradient(hsl(220 25% 4.8% / .78));
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow: var(--shadow-card);
}
[data-theme="dark"] .glass .shine {
  pointer-events: none;
  border-radius: 0;
  border-top-right-radius: inherit;
  border-bottom-left-radius: inherit;
  border: 1px solid transparent;
  width: 75%; aspect-ratio: 1;
  display: block; position: absolute;
  right: calc(var(--border) * -1);
  top: calc(var(--border) * -1);
  z-index: 1;
  background: conic-gradient(from var(--conic, -45deg) at center in oklch,
    transparent 12%,
    hsl(var(--hue), 80%, 60%),
    transparent 50%) border-box;
  mask: linear-gradient(transparent), linear-gradient(black);
  mask-clip: padding-box, border-box;
  mask-composite: subtract;
}
[data-theme="dark"] .glass .shine.shine-bottom {
  right: auto; top: auto;
  left: calc(var(--border) * -1);
  bottom: calc(var(--border) * -1);
}
[data-theme="dark"] .glass .glow {
  pointer-events: none;
  border-top-right-radius: calc(var(--radius) * 2.5);
  border-bottom-left-radius: calc(var(--radius) * 2.5);
  border: calc(var(--radius) * 1.25) solid transparent;
  inset: calc(var(--radius) * -2);
  width: 75%; aspect-ratio: 1;
  display: block; position: absolute;
  left: auto; bottom: auto;
  background: conic-gradient(from var(--conic, -45deg) at center in oklch,
    hsl(var(--hue), 80%, 60% / .5) 12%,
    transparent 50%);
  filter: blur(12px) saturate(1.25);
  mix-blend-mode: plus-lighter;
  z-index: 3;
  opacity: 0.6;
}
[data-theme="dark"] .glass .glow.glow-bottom {
  inset: auto;
  left: calc(var(--radius) * -2);
  bottom: calc(var(--radius) * -2);
}

/* ═══ CELL (Днес + Времето) ═══ */
.cell {
  padding: 14px;
  position: relative;
  animation: fadeInUp 0.6s var(--ease-spring) both;
  overflow: hidden;
}
.cell:nth-child(1) { animation-delay: 0.05s; }
.cell:nth-child(2) { animation-delay: 0.12s; }

.cell-label {
  font-family: var(--font-mono);
  font-size: 10px; font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 6px;
  position: relative; z-index: 5;
}
.cell-numrow {
  display: flex; align-items: baseline; gap: 4px;
  position: relative; z-index: 5;
}
.cell-num {
  font-family: var(--font);
  font-size: 30px; font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1;
  background: linear-gradient(135deg, var(--text) 0%, var(--accent) 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
.cell-cur {
  font-size: 13px; font-weight: 600;
  color: var(--text-muted);
}
.cell-pct {
  margin-left: auto;
  padding: 3px 8px;
  border-radius: var(--radius-pill);
  font-family: var(--font-mono);
  font-size: 11px; font-weight: 700;
  color: var(--success);
  border: 1px solid hsl(145 70% 50% / 0.3);
  background: hsl(145 70% 50% / 0.1);
}
.cell-pct.neg, .cell-pct.cmp-neg {
  color: var(--danger);
  border-color: hsl(0 85% 50% / 0.3);
  background: hsl(0 85% 50% / 0.1);
}
.cell-pct.cmp-pos {
  color: var(--success);
  border-color: hsl(145 70% 50% / 0.3);
  background: hsl(145 70% 50% / 0.1);
}
.cell-meta {
  font-size: 11px;
  color: var(--text-muted);
  margin-top: 6px;
  font-weight: 500;
  position: relative; z-index: 5;
}

/* ═══ WEATHER CELL ═══ */
.weather-cell-top {
  display: flex; align-items: center; gap: 8px;
  margin-bottom: 4px;
  position: relative; z-index: 5;
}
.weather-cell-icon {
  width: 32px; height: 32px;
  border-radius: 50%;
  display: grid; place-items: center;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .weather-cell-icon,
:root:not([data-theme]) .weather-cell-icon {
  background: var(--surface);
  box-shadow: var(--shadow-pressed);
  border: none;
}
[data-theme="dark"] .weather-cell-icon {
  background: hsl(220 25% 8%);
}
.weather-cell-icon svg {
  width: 18px; height: 18px;
  stroke: var(--accent);
  fill: none; stroke-width: 2;
}
.weather-cell-temp {
  font-size: 26px; font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1;
}
.weather-cell-cond {
  font-size: 11px; font-weight: 600;
  color: var(--text-muted);
  margin-bottom: 4px;
  position: relative; z-index: 5;
}

/* ═══ LIFE BOARD HEADER ═══ */
.lb-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 4px 4px 14px;
  animation: fadeInUp 0.6s var(--ease-spring) 0.2s both;
  position: relative; z-index: 5;
}
.lb-title {
  display: flex; align-items: center; gap: 10px;
}
.lb-title-orb {
  width: 24px; height: 24px;
  border-radius: 50%;
  background: conic-gradient(from 0deg,
    hsl(var(--hue1) 80% 60%),
    hsl(var(--hue2) 80% 60%),
    hsl(var(--hue3) 70% 60%),
    hsl(var(--hue1) 80% 60%));
  position: relative;
  animation: orbSpin 5s linear infinite;
}
.lb-title-orb::before {
  content: '';
  position: absolute; inset: -6px;
  border-radius: 50%;
  background: inherit;
  filter: blur(8px);
  opacity: 0.55;
  z-index: -1;
}
.lb-title-orb::after {
  content: '';
  position: absolute; inset: 4px;
  border-radius: 50%;
  background: var(--bg-main);
}
.lb-title-text {
  font-size: 14px; font-weight: 800;
  letter-spacing: -0.01em;
  background: linear-gradient(135deg, var(--text), var(--accent));
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
.lb-count {
  font-family: var(--font-mono);
  font-size: 10px;
  color: var(--text-muted);
  font-weight: 700;
}

/* ═══ LB-CARD ═══ */
.lb-card {
  position: relative;
  padding: 14px;
  margin-bottom: 12px;
  cursor: pointer;
  isolation: isolate;
  animation: fadeInUp 0.6s var(--ease-spring) both;
  transition: box-shadow 0.3s ease;
}
.lb-card:nth-of-type(1) { animation-delay: 0.3s; }
.lb-card:nth-of-type(2) { animation-delay: 0.4s; }
.lb-card:nth-of-type(3) { animation-delay: 0.5s; }
.lb-card:nth-of-type(4) { animation-delay: 0.6s; }

/* Hue classes (legacy compatibility + Bible v4.0 mapping) */
.lb-card.q1, .lb-card.q-loss { --card-accent: var(--q1-loss); }
.lb-card.q2, .lb-card.q-magic { --card-accent: var(--q2-why-loss); }
.lb-card.q3, .lb-card.q-gain { --card-accent: var(--q3-gain); }
.lb-card.q4, .lb-card.q-jewelry { --card-accent: var(--q4-why-gain); }
.lb-card.q5, .lb-card.q-amber { --card-accent: var(--q5-order); }
.lb-card.q6, .lb-card.q-default { --card-accent: var(--q6-no-order); }

/* Cell hue aliases */
.cell.qd { --hue: var(--hue1); }
.cell.qw { --hue: var(--hue3); }

/* Effect #6: conic glow ring при expanded */
.lb-card.expanded::before {
  content: '';
  position: absolute; inset: -1px;
  border-radius: var(--radius);
  padding: 2px;
  background: conic-gradient(from 0deg,
    var(--card-accent, var(--accent)),
    transparent 60%,
    var(--card-accent, var(--accent)));
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
  animation: conicSpin 4s linear infinite;
  opacity: 0.55;
  pointer-events: none;
  z-index: 1;
}

.lb-collapsed {
  display: flex; align-items: center; gap: 12px;
  position: relative; z-index: 5;
}
.lb-emoji {
  width: 40px; height: 40px;
  border-radius: 50%;
  display: grid; place-items: center;
  font-size: 18px;
  flex-shrink: 0;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .lb-emoji,
:root:not([data-theme]) .lb-emoji {
  background: var(--surface);
  box-shadow: var(--shadow-pressed);
  border: none;
}
[data-theme="dark"] .lb-emoji {
  background: hsl(220 25% 4%);
}

.lb-collapsed-content {
  flex: 1;
  min-width: 0;
}
.lb-fq-tag-mini {
  display: inline-block;
  font-family: var(--font-mono);
  font-size: 9px; font-weight: 800;
  color: var(--card-accent, var(--text-muted));
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 2px;
}
.lb-collapsed-title {
  display: block;
  font-size: 13px; font-weight: 600;
  line-height: 1.3;
}

.lb-expand-btn {
  width: 28px; height: 28px;
  border-radius: 50%;
  border: 1px solid var(--border-color);
  display: grid; place-items: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: transform 0.3s ease, box-shadow var(--dur) var(--ease);
}
[data-theme="light"] .lb-expand-btn,
:root:not([data-theme]) .lb-expand-btn {
  background: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: none;
}
[data-theme="dark"] .lb-expand-btn {
  background: hsl(220 25% 8%);
}
.lb-expand-btn svg {
  width: 12px; height: 12px;
  stroke: var(--text-muted);
  fill: none; stroke-width: 2.5;
}
.lb-card.expanded .lb-expand-btn {
  transform: rotate(180deg);
}
[data-theme="light"] .lb-card.expanded .lb-expand-btn,
:root:not([data-theme]) .lb-card.expanded .lb-expand-btn {
  box-shadow: var(--shadow-pressed);
}
.lb-card.expanded .lb-expand-btn svg {
  stroke: var(--accent);
}

.lb-expanded {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease, padding-top 0.3s ease;
  position: relative; z-index: 5;
}
.lb-card.expanded .lb-expanded {
  max-height: 600px;
  padding-top: 12px;
}

.lb-body {
  font-size: 12px;
  line-height: 1.5;
  color: var(--text-muted);
  padding: 10px;
  border-radius: var(--radius-sm);
  margin-bottom: 12px;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .lb-body,
:root:not([data-theme]) .lb-body {
  background: var(--surface);
  box-shadow: var(--shadow-pressed);
  border: none;
}
[data-theme="dark"] .lb-body {
  background: hsl(220 25% 4%);
}

.lb-actions {
  display: flex; gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}
.lb-action {
  flex: 1; min-width: 60px;
  padding: 8px 12px;
  color: var(--text);
  border-radius: var(--radius-sm);
  font-family: var(--font);
  font-size: 11px; font-weight: 700;
  cursor: pointer;
  text-decoration: none;
  text-align: center;
  border: 1px solid var(--border-color);
  transition: all var(--dur) var(--ease);
}
[data-theme="light"] .lb-action,
:root:not([data-theme]) .lb-action {
  background: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: none;
}
[data-theme="light"] .lb-action:active,
:root:not([data-theme]) .lb-action:active {
  box-shadow: var(--shadow-pressed);
}
[data-theme="dark"] .lb-action {
  background: hsl(220 25% 8%);
}
.lb-action:hover { color: var(--accent); }

/* Effect #7: Conic shimmer на primary CTA */
.lb-action.primary, .lb-action.btn-primary {
  position: relative;
  background: linear-gradient(135deg,
    hsl(var(--hue1) 80% 55%),
    hsl(var(--hue2) 80% 55%));
  color: white;
  overflow: hidden;
  border: none;
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.4);
}
[data-theme="light"] .lb-action.primary,
[data-theme="light"] .lb-action.btn-primary,
:root:not([data-theme]) .lb-action.primary,
:root:not([data-theme]) .lb-action.btn-primary {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
}
.lb-action.primary::before, .lb-action.btn-primary::before {
  content: '';
  position: absolute; inset: 0;
  background: conic-gradient(from 0deg,
    transparent 70%,
    rgba(255,255,255,0.4) 85%,
    transparent 100%);
  animation: conicSpin 3s linear infinite;
}
.lb-action.primary > *, .lb-action.btn-primary > * {
  position: relative;
  z-index: 1;
}
.lb-action.primary:hover, .lb-action.btn-primary:hover {
  color: white;
}

.lb-feedback {
  display: flex; align-items: center; gap: 8px;
  padding-top: 8px;
  border-top: 1px solid var(--border-color);
}
[data-theme="light"] .lb-feedback,
:root:not([data-theme]) .lb-feedback {
  border-top: 1px solid rgba(163, 177, 198, 0.3);
}
.lb-fb-label {
  font-family: var(--font-mono);
  font-size: 10px; font-weight: 700;
  color: var(--text-muted);
}
.lb-fb-btn {
  width: 30px; height: 30px;
  border-radius: 50%;
  border: 1px solid var(--border-color);
  display: grid; place-items: center;
  font-size: 13px;
  cursor: pointer;
  transition: all var(--dur) var(--ease);
}
[data-theme="light"] .lb-fb-btn,
:root:not([data-theme]) .lb-fb-btn {
  background: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: none;
}
[data-theme="dark"] .lb-fb-btn {
  background: hsl(220 25% 8%);
}
.lb-fb-btn:active,
.lb-fb-btn.selected {
  transform: scale(0.92);
}
[data-theme="light"] .lb-fb-btn:active,
[data-theme="light"] .lb-fb-btn.selected,
:root:not([data-theme]) .lb-fb-btn:active,
:root:not([data-theme]) .lb-fb-btn.selected {
  box-shadow: var(--shadow-pressed);
}

/* ═══ SEE MORE ═══ */
.see-more-mini {
  text-align: center;
  padding: 8px 0 16px;
  position: relative; z-index: 5;
}
.see-more-mini a {
  color: var(--accent);
  font-size: 12px; font-weight: 700;
  text-decoration: none;
}
.see-more-mini a:hover {
  text-decoration: underline;
}

/* ═══ OPS GRID (4 големи бутона) ═══ */
.ops-section {
  padding-top: 12px;
  position: relative; z-index: 5;
}
.ops-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 14px;
}
.op-btn {
  position: relative;
  border-radius: var(--radius);
  padding: 18px 14px;
  aspect-ratio: 1.6 / 1;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 8px;
  text-decoration: none;
  color: var(--text);
  cursor: pointer;
  border: 1px solid var(--border-color);
  isolation: isolate;
  transition: all var(--dur) var(--ease);
  animation: fadeInUp 0.6s var(--ease-spring) both;
  overflow: hidden;
}
.op-btn:nth-child(1) { animation-delay: 0.7s; }
.op-btn:nth-child(2) { animation-delay: 0.78s; }
.op-btn:nth-child(3) { animation-delay: 0.86s; }
.op-btn:nth-child(4) { animation-delay: 0.94s; }

[data-theme="light"] .op-btn,
:root:not([data-theme]) .op-btn {
  background: var(--surface);
  box-shadow: var(--shadow-card);
  border: none;
}
[data-theme="dark"] .op-btn {
  background:
    linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%),
    linear-gradient(45deg, hsl(var(--hue2) 50% 10% / .8), hsl(var(--hue2) 50% 10% / 0) 33%),
    linear-gradient(hsl(220 25% 4.8% / .78));
  box-shadow: var(--shadow-card);
}

/* Effect #8: hover halo */
.op-btn::before {
  content: '';
  position: absolute;
  width: 80%; height: 80%;
  border-radius: 50%;
  background: radial-gradient(circle, hsl(var(--hue1) 80% 60%) 0%, transparent 70%);
  filter: blur(20px);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  mix-blend-mode: var(--aurora-blend);
}
.op-btn:hover::before {
  opacity: 0.4;
}
.op-btn:active {
  transform: scale(var(--press));
}
[data-theme="light"] .op-btn:active,
:root:not([data-theme]) .op-btn:active {
  box-shadow: var(--shadow-pressed);
}

.op-icon {
  width: 48px; height: 48px;
  border-radius: 50%;
  display: grid; place-items: center;
  position: relative; z-index: 1;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .op-icon,
:root:not([data-theme]) .op-icon {
  background: var(--surface);
  box-shadow:
    inset 6px 6px 12px var(--shadow-dark),
    inset -6px -6px 12px var(--shadow-light);
  border: none;
}
[data-theme="dark"] .op-icon {
  background: hsl(220 25% 4%);
}
.op-icon svg {
  width: 22px; height: 22px;
  stroke: var(--accent);
  fill: none; stroke-width: 2;
}
.op-label {
  font-size: 13px; font-weight: 800;
  letter-spacing: -0.01em;
  position: relative; z-index: 1;
}

/* ═══ AI BRAIN PILL (Effect #9) ═══ */
.ai-brain-pill {
  position: relative;
  background: linear-gradient(135deg,
    hsl(var(--hue1) 80% 55%),
    hsl(var(--hue2) 80% 55%));
  color: white;
  border-radius: var(--radius-sm);
  padding: 14px 16px;
  margin-bottom: 12px;
  display: flex; align-items: center; gap: 10px;
  box-shadow: 0 8px 24px hsl(var(--hue1) 80% 40% / 0.4);
  cursor: pointer;
  overflow: hidden;
  animation: fadeInUp 0.6s var(--ease-spring) 1.02s both;
}
[data-theme="light"] .ai-brain-pill,
:root:not([data-theme]) .ai-brain-pill {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: var(--shadow-card), 0 8px 24px oklch(0.62 0.22 285 / 0.3);
}
.ai-brain-pill::before {
  content: '';
  position: absolute; inset: 0;
  background: conic-gradient(from 0deg,
    transparent,
    rgba(255,255,255,0.25),
    transparent);
  animation: conicSpin 4s linear infinite;
}
.ai-brain-pill::after {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(105deg,
    transparent 30%,
    rgba(255,255,255,0.35) 50%,
    transparent 70%);
  animation: shimmerSlide 3.5s ease-in-out infinite;
}
.ai-brain-pill > * {
  position: relative;
  z-index: 1;
}
.ai-brain-pill svg {
  width: 20px; height: 20px;
  stroke: white;
  fill: none; stroke-width: 2;
  flex-shrink: 0;
}
.ai-brain-text { flex: 1; }
.ai-brain-label {
  font-family: var(--font-mono);
  font-size: 10px; font-weight: 800;
  opacity: 0.9;
  letter-spacing: 0.08em;
}
.ai-brain-msg {
  font-size: 13px; font-weight: 700;
  margin-top: 2px;
}

/* ═══ AI STUDIO BUTTON ═══ */
.studio-btn {
  position: relative;
  border-radius: var(--radius-sm);
  padding: 14px 16px;
  display: flex; align-items: center; gap: 12px;
  text-decoration: none;
  color: var(--text);
  cursor: pointer;
  border: 1px solid var(--border-color);
  isolation: isolate;
  animation: fadeInUp 0.6s var(--ease-spring) 1.1s both;
  transition: all var(--dur) var(--ease);
}
[data-theme="light"] .studio-btn,
:root:not([data-theme]) .studio-btn {
  background: var(--surface);
  box-shadow: var(--shadow-card);
  border: none;
}
[data-theme="dark"] .studio-btn {
  background:
    linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%),
    linear-gradient(hsl(220 25% 4.8% / .78));
  backdrop-filter: blur(12px);
  box-shadow: var(--shadow-card);
}
[data-theme="light"] .studio-btn:active,
:root:not([data-theme]) .studio-btn:active {
  box-shadow: var(--shadow-pressed);
}

.studio-icon {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg,
    hsl(var(--hue1) 80% 55%),
    hsl(var(--hue2) 80% 55%));
  display: grid; place-items: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 40% / 0.5);
}
[data-theme="light"] .studio-icon,
:root:not([data-theme]) .studio-icon {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
}
.studio-icon svg {
  width: 18px; height: 18px;
  stroke: white;
  fill: none; stroke-width: 2;
}
.studio-text { flex: 1; }
.studio-label {
  font-size: 13px; font-weight: 800;
}
.studio-sub {
  display: block;
  font-family: var(--font-mono);
  font-size: 10px; font-weight: 700;
  color: var(--text-muted);
  letter-spacing: 0.06em;
  margin-top: 1px;
}
.studio-badge {
  background: linear-gradient(135deg, var(--danger), hsl(0 85% 50%));
  color: white;
  width: 28px; height: 28px;
  border-radius: 50%;
  display: grid; place-items: center;
  font-family: var(--font-mono);
  font-size: 11px; font-weight: 800;
  box-shadow: 0 4px 10px hsl(0 85% 50% / 0.4);
}

/* ═══ ANIMATIONS ═══ */
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes orbSpin {
  to { transform: rotate(360deg); }
}
@keyframes conicSpin {
  to { transform: rotate(360deg); }
}
@keyframes shimmerSlide {
  0%, 100% { transform: translateX(-100%); }
  50%      { transform: translateX(100%); }
}
@keyframes auroraDrift {
  0%, 100% { transform: translate(0,0) scale(1); }
  33%      { transform: translate(30px,-25px) scale(1.1); }
  66%      { transform: translate(-25px,35px) scale(0.95); }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}

/* ═══ BACKWARD COMPATIBILITY (legacy s87v3-* класове остават rendered, без animation) ═══ */
.s87v3-pagein, .s87v3-stagger, .s87v3-tap, .s87v3-scroll-reveal {
  /* Тези стари класове остават в HTML но нямат специален CSS — animations идват от .lb-card / .cell / .op-btn nth-child rules */
}


/* S96 PATCH: Дълбочина на ops buttons */
[data-theme="light"] .op-btn,
:root:not([data-theme]) .op-btn {
  box-shadow:
    12px 12px 24px var(--shadow-dark),
    -12px -12px 24px var(--shadow-light) !important;
}
[data-theme="light"] .op-btn:active,
:root:not([data-theme]) .op-btn:active {
  box-shadow:
    inset 6px 6px 12px var(--shadow-dark),
    inset -6px -6px 12px var(--shadow-light) !important;
}


/* S96 PATCH2: По-силен неон на ops в dark mode */
[data-theme="dark"] .op-btn {
  box-shadow:
    inset 0 1px 0 hsl(var(--hue1) 80% 60% / 0.15),
    0 0 24px hsl(var(--hue1) 80% 50% / 0.18),
    var(--shadow-card) !important;
}
[data-theme="dark"] .op-btn::after {
  content: '';
  position: absolute; inset: 0;
  border-radius: var(--radius);
  pointer-events: none;
  background: radial-gradient(ellipse at top,
    hsl(var(--hue1) 80% 60% / 0.08) 0%,
    transparent 60%);
  z-index: 0;
}
[data-theme="dark"] .op-icon {
  box-shadow:
    inset 0 0 12px hsl(var(--hue1) 80% 40% / 0.4),
    0 0 16px hsl(var(--hue1) 80% 50% / 0.3),
    inset 0 1px 0 hsl(var(--hue1) 80% 70% / 0.2);
}
[data-theme="dark"] .op-icon svg {
  filter: drop-shadow(0 0 6px hsl(var(--hue1) 80% 60% / 0.5));
}

/* Store picker styling */
.cell-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 6px;
  position: relative;
  z-index: 5;
}
.cell-header-row .cell-label {
  margin-bottom: 0;
}
.lb-store-picker {
  font-family: var(--font-mono);
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-radius: var(--radius-pill);
  padding: 4px 22px 4px 10px;
  border: 1px solid var(--border-color);
  color: var(--text);
  cursor: pointer;
  outline: none;
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23667' stroke-width='3'><polyline points='6 9 12 15 18 9'/></svg>");
  background-repeat: no-repeat;
  background-position: right 6px center;
  background-size: 10px;
}
[data-theme="light"] .lb-store-picker,
:root:not([data-theme]) .lb-store-picker {
  background-color: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: none;
}
[data-theme="dark"] .lb-store-picker {
  background-color: hsl(220 25% 8%);
}


/* S96 PATCH3: Store picker overflow fix */
.cell-header-row {
  flex-wrap: wrap;
  min-width: 0;
}
.cell-header-row .cell-label {
  flex-shrink: 1;
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.lb-store-picker {
  max-width: 110px;
  flex-shrink: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

</style>
</head>
<body>

<?php include __DIR__ . '/partials/header.php'; ?>

<!-- DESIGN_SYSTEM v4.0 Effect #1: Aurora background -->
<div class="aurora" aria-hidden="true">
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
</div>


<div class="lb-mode-row">
    <a href="/chat.php" class="lb-mode-toggle s87v3-tap" title="Подробен режим">
        Подробен <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
</div>

<div class="app s87v3-pagein">
  <div class="scroll s87v3-stagger">

    <!-- ─── Mini dashboard + mini weather ─── -->
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
      <div class="glass sm cell qw">
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
      <div class="glass sm cell qw">
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

    <!-- ─── Life Board mini header ─── -->
    <div class="lb-header">
      <div class="lb-title">
        <div class="lb-title-orb"></div>
        <span class="lb-title-text">Life Board</span>
      </div>
      <span class="lb-count"><?= count($picked) ?> неща · <?= date('H:i') ?></span>
    </div>

    <!-- ─── Collapsible cards ─── -->
    <?php if (!empty($picked)): ?>
        <?php foreach ($picked as $p):
            $ins = $p['ins'];
            $meta = $fq_meta[$p['fq']] ?? ['q'=>'q3','emoji'=>'•','name'=>''];
            $action = lbInsightAction($ins);
            $title_js = htmlspecialchars(addslashes($ins['title']), ENT_QUOTES);
            $card_class = 'glass sm lb-card ' . $meta['q'] . ($p['expanded'] ? ' expanded' : '');
        ?>
        <div class="<?= $card_class ?>" data-topic="<?= htmlspecialchars($ins['topic_id'] ?? '', ENT_QUOTES) ?>">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <span class="glow"></span><span class="glow glow-bottom"></span>
            <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
                <span class="lb-emoji"><?= $meta['emoji'] ?></span>
                <div class="lb-collapsed-content">
                    <span class="lb-fq-tag-mini"><?= $meta['name'] ?></span>
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
                    <a class="lb-action s87v3-tap" href="<?= htmlspecialchars($action['url']) ?>">Покажи</a>
                    <a class="lb-action primary s87v3-tap" href="<?= htmlspecialchars($action['url']) ?>"><?= htmlspecialchars($action['label']) ?> →</a>
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
        <div class="see-more-mini"><a href="/chat.php#all">Виж всички <?= $total_insights ?> →</a></div>
        <?php endif; ?>
    <?php else: ?>
        <div class="glass sm lb-empty q3">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <span class="glow"></span><span class="glow glow-bottom"></span>
            <div class="lb-empty-icon">🌿</div>
            <div class="lb-empty-text">Всичко е тихо днес</div>
            <div class="lb-empty-sub">Няма нищо спешно. Действай отдолу.</div>
        </div>
    <?php endif; ?>

    <!-- ─── 4 big operational glass buttons ─── -->
    <div class="ops-section">
      <div class="ops-grid">
        <a href="/sale.php" class="glass sm op-btn s87v3-tap q3">
          <span class="shine"></span><span class="shine shine-bottom"></span>
          <span class="glow"></span><span class="glow glow-bottom"></span>
          <div class="op-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1.5"/><circle cx="18" cy="21" r="1.5"/><path d="M3 3h2l2.7 12.3a2 2 0 002 1.7h7.6a2 2 0 002-1.5L21 8H6"/></svg></div>
          <div class="op-label">Продай</div>
        </a>
        <a href="/products.php" class="glass sm op-btn s87v3-tap qd">
          <span class="shine"></span><span class="shine shine-bottom"></span>
          <span class="glow"></span><span class="glow glow-bottom"></span>
          <div class="op-icon"><svg viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg></div>
          <div class="op-label">Стоката</div>
        </a>
        <a href="<?= htmlspecialchars($op_deliveries_url) ?>" class="glass sm op-btn s87v3-tap q5">
          <span class="shine"></span><span class="shine shine-bottom"></span>
          <span class="glow"></span><span class="glow glow-bottom"></span>
          <div class="op-icon"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div>
          <div class="op-label">Доставка</div>
        </a>
        <a href="<?= htmlspecialchars($op_orders_url) ?>" class="glass sm op-btn s87v3-tap q2">
          <span class="shine"></span><span class="shine shine-bottom"></span>
          <span class="glow"></span><span class="glow glow-bottom"></span>
          <div class="op-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg></div>
          <div class="op-label">Поръчка</div>
        </a>
      </div>
      <?php include __DIR__ . '/partials/ai-brain-pill.php'; ?>
      <div class="studio-row-bottom">
        <a href="/ai-studio.php" class="glass sm studio-btn s87v3-tap" aria-label="AI Studio">
          <span class="shine"></span><span class="shine shine-bottom"></span>
          <span class="glow"></span><span class="glow glow-bottom"></span>
          <span class="studio-icon"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
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
    </div>

  </div>
</div>

<?php include __DIR__ . '/partials/chat-input-bar.php'; ?>
<?php include __DIR__ . '/partials/shell-scripts.php'; ?>

<script>
// Toggle card (collapsed ↔ expanded)
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
    e.stopPropagation();
    try { sessionStorage.setItem('rms_pending_q', q); } catch(_) {}
    window.location.href = '/chat.php?q=' + encodeURIComponent(q);
}
// chat-input-bar.php calls rmsOpenChat(); on Лесен → just route to chat.php
window.rmsOpenChat = function(){ window.location.href = '/chat.php'; };

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
