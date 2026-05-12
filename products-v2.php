<?php
/**
 * products-v2.php — NEW design (P15 simple + P2v2 detailed)
 * S141 REBUILD: започваме от P15/P2v2 макетите, инжектираме PHP данни блок по блок.
 * НЕ заменя products.php — съществува паралелно за безопасно тестване.
 * SWAP в края когато визията е готова.
 *
 * Готови блокове:
 *   ⏳ Auth + tenant + store
 *   ⏳ Simple mode home (P15)
 *   ⏳ Detailed mode home (P2v2 tabs)
 *   ⏳ AJAX endpoints (proxied to products.php?)
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'config/helpers.php';

$user_id    = (int)$_SESSION['user_id'];
$tenant_id  = (int)$_SESSION['tenant_id'];
$store_id   = (int)($_SESSION['store_id'] ?? 0);
$user_role  = $_SESSION['role'] ?? 'seller';

// Mode override (?mode=simple|detailed)
$mode_override = $_GET['mode'] ?? null;
$is_simple_view = ($mode_override === 'simple') || (!$mode_override && $user_role === 'seller');

// Store switch via GET
if (!empty($_GET['store'])) {
    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
        [(int)$_GET['store'], $tenant_id])->fetch();
    if ($chk) { $_SESSION['store_id'] = (int)$_GET['store']; $store_id = (int)$_GET['store']; }
    $redirect_to = $is_simple_view ? 'products-v2.php?mode=simple' : 'products-v2.php?mode=detailed';
    header('Location: ' . $redirect_to); exit;
}
if (!$store_id) {
    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
    if ($first) { $store_id = (int)$first['id']; $_SESSION['store_id'] = $store_id; }
}

// Tenant + store
$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
$cs = htmlspecialchars($tenant['currency'] ?? '€');
$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
$store_name = $store['name'] ?? 'Магазин';
$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);

// Counters for simple home alarms (СВЪРШИЛИ + ЗАСТОЯЛИ 60+)
$out_of_stock = (int)DB::run(
    'SELECT COUNT(DISTINCT p.id) FROM products p
     LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=?
     WHERE p.tenant_id=? AND p.is_active=1 AND COALESCE(i.quantity,0)<=0',
    [$store_id, $tenant_id]
)->fetchColumn();

$stale_60d = (int)DB::run(
    "SELECT COUNT(DISTINCT p.id) FROM products p
     LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=?
     WHERE p.tenant_id=? AND p.is_active=1 AND COALESCE(i.quantity,0)>0
     AND NOT EXISTS (
         SELECT 1 FROM sale_items si JOIN sales s ON s.id=si.sale_id
         WHERE si.product_id=p.id AND s.store_id=?
         AND s.created_at > DATE_SUB(NOW(), INTERVAL 60 DAY)
         AND s.status!='canceled'
     )",
    [$store_id, $tenant_id, $store_id]
)->fetchColumn();

// Total products in this store
$total_products = (int)DB::run(
    'SELECT COUNT(DISTINCT p.id) FROM products p
     LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=?
     WHERE p.tenant_id=? AND p.is_active=1',
    [$store_id, $tenant_id]
)->fetchColumn();

?>
<!DOCTYPE html>
<html lang="bg" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Стоката · RunMyStore.AI</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<script>(function(){try{var s=localStorage.getItem('rms_theme')||'light';document.documentElement.setAttribute('data-theme',s);}catch(_){document.documentElement.setAttribute('data-theme','light');}})();</script>

<style>
/* P10 — life-board.php (lesny mode) — преструктурирана версия
   Промени спрямо production:
   1. 4 ops buttons → ГОРЕ (преди Life Board) с info бутончета
   2. AI Studio row → под ops
   3. AI Help card → НОВА (q-magic, с примерни въпроси + видео placeholder)
   4. AI Brain pill → ПРЕМАХНАТА (дублира chat input bar)
   5. Life Board header + cards → отдолу (collapsed by default) */

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

/* ═══ CANONICAL HEADER ═══ */
.rms-header {
  position: sticky; top: 0; z-index: 50;
  height: 56px; padding: 0 16px;
  display: flex; align-items: center; gap: 8px;
  border-bottom: 1px solid var(--border-color);
  padding-top: env(safe-area-inset-top, 0);
}
[data-theme="light"] .rms-header,
:root:not([data-theme]) .rms-header { background: var(--bg-main); box-shadow: 0 4px 12px rgba(163,177,198,0.15); }
[data-theme="dark"] .rms-header { background: hsl(220 25% 4.8% / 0.85); backdrop-filter: blur(16px); }
.rms-brand {
  position: relative; font-size: 17px; letter-spacing: -0.01em;
  display: inline-flex; align-items: baseline; gap: 0;
  filter: drop-shadow(0 0 12px hsl(var(--hue1) 70% 50% / 0.4));
  }
.rms-brand .brand-1 {
  font-weight: 900;
  background: linear-gradient(90deg, hsl(var(--hue1) 80% 60%), hsl(var(--hue2) 80% 60%), hsl(var(--hue3) 70% 55%), hsl(var(--hue2) 80% 60%), hsl(var(--hue1) 80% 60%));
  background-size: 200% auto;
  animation: rmsBrandShimmer 4s linear infinite;
  -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
  }
.rms-brand .brand-2 { font-weight: 400; font-size: 14px; color: var(--text-muted); margin-left: 1px; opacity: 0.85; }
.rms-plan-badge {
  position: relative; padding: 5px 12px; border-radius: var(--radius-pill);
  font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.08em;
  text-transform: uppercase; color: var(--text); border: 1px solid var(--border-color); overflow: hidden;
}
[data-theme="light"] .rms-plan-badge,
:root:not([data-theme]) .rms-plan-badge { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .rms-plan-badge { background: hsl(220 25% 8% / 0.7); backdrop-filter: blur(8px); }
.rms-plan-badge::before {
  content: ''; position: absolute; inset: -1px; border-radius: inherit; padding: 1.5px;
  background: conic-gradient(from 0deg, hsl(var(--hue1) 80% 60%), hsl(var(--hue2) 80% 60%), hsl(var(--hue3) 70% 60%), hsl(var(--hue1) 80% 60%));
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor; mask-composite: exclude;
  animation: conicSpin 3s linear infinite; opacity: 0.6; pointer-events: none;
}
.rms-header-spacer { flex: 1; }
.rms-icon-btn {
  width: 40px; height: 40px; border-radius: var(--radius-icon);
  border: 1px solid var(--border-color); display: grid; place-items: center;
  background: var(--surface);
  transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
}
.rms-icon-btn svg { width: 18px; height: 18px; stroke: var(--text); fill: none; stroke-width: 2; }
.rms-icon-btn:active { transform: scale(var(--press)); }
[data-theme="light"] .rms-icon-btn,
:root:not([data-theme]) .rms-icon-btn { box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="light"] .rms-icon-btn:active,
:root:not([data-theme]) .rms-icon-btn:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .rms-icon-btn { background: hsl(220 25% 8% / 0.7); backdrop-filter: blur(8px); box-shadow: 0 4px 12px hsl(var(--hue2) 50% 4%); }

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
}
[data-theme="light"] .lb-mode-toggle,
:root:not([data-theme]) .lb-mode-toggle { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .lb-mode-toggle { background: hsl(220 25% 8% / 0.6); }
.lb-mode-toggle svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; }

/* ═══ APP ═══ */
.app { position: relative; z-index: 5; max-width: 480px; margin: 0 auto; padding: 8px 12px calc(168px + env(safe-area-inset-bottom, 0)); }

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
  backdrop-filter: blur(12px); box-shadow: var(--shadow-card);
}
[data-theme="dark"] .glass .shine { pointer-events: none; border-radius: 0; border-top-right-radius: inherit; border-bottom-left-radius: inherit; border: 1px solid transparent; width: 75%; aspect-ratio: 1; display: block; position: absolute; right: calc(var(--border) * -1); top: calc(var(--border) * -1); z-index: 1; background: conic-gradient(from var(--conic, -45deg) at center in oklch, transparent 12%, hsl(var(--hue), 80%, 60%), transparent 50%) border-box; mask: linear-gradient(transparent), linear-gradient(black); mask-clip: padding-box, border-box; mask-composite: subtract; }
[data-theme="dark"] .glass .shine.shine-bottom { right: auto; top: auto; left: calc(var(--border) * -1); bottom: calc(var(--border) * -1); }
[data-theme="dark"] .glass .glow { pointer-events: none; border-top-right-radius: calc(var(--radius) * 2.5); border-bottom-left-radius: calc(var(--radius) * 2.5); border: calc(var(--radius) * 1.25) solid transparent; inset: calc(var(--radius) * -2); width: 75%; aspect-ratio: 1; display: block; position: absolute; left: auto; bottom: auto; background: conic-gradient(from var(--conic, -45deg) at center in oklch, hsl(var(--hue), 80%, 60% / .5) 12%, transparent 50%); filter: blur(12px) saturate(1.25); mix-blend-mode: plus-lighter; z-index: 3; opacity: 0.6; }
[data-theme="dark"] .glass .glow.glow-bottom { inset: auto; left: calc(var(--radius) * -2); bottom: calc(var(--radius) * -2); }
/* hue overrides (production: q1=loss/red, q2=cause/violet, q3=gain/green, q4=cause/teal, q5=order/amber, q6=gray; qd=default, qw=weather) */
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
.cell-header-row { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.cell-label { font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted); }
.cell-numrow { display: flex; align-items: baseline; gap: 4px; margin-top: 6px; }
.cell-num { font-size: 24px; font-weight: 800; letter-spacing: -0.02em; line-height: 1; }
.cell-cur { font-family: var(--font-mono); font-size: 11px; font-weight: 700; color: var(--text-muted); }
.cell-pct { font-family: var(--font-mono); font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: var(--radius-pill); margin-left: auto; }
.cell-pct.pos { background: oklch(0.92 0.08 145 / 0.5); color: hsl(145 60% 35%); }
[data-theme="dark"] .cell-pct.pos { background: hsl(145 50% 12%); color: hsl(145 70% 65%); }
.cell-pct.neg { background: oklch(0.92 0.08 25 / 0.5); color: hsl(0 60% 45%); }
[data-theme="dark"] .cell-pct.neg { background: hsl(0 50% 12%); color: hsl(0 80% 70%); }
.cell-meta { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 4px; line-height: 1.2; }
.weather-cell-top { display: flex; align-items: baseline; gap: 6px; }
.weather-cell-icon svg { width: 22px; height: 22px; stroke: hsl(38 80% 50%); fill: hsl(38 80% 60%); stroke-width: 1.5; }
[data-theme="dark"] .weather-cell-icon svg { stroke: hsl(38 90% 60%); fill: hsl(38 80% 50%); }
.weather-cell-temp { font-size: 22px; font-weight: 800; letter-spacing: -0.02em; }
.weather-cell-cond { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-top: 3px; }

/* ═══ OPS GRID — 4 buttons (преместен ГОРЕ) ═══ */
.ops-section {
  margin-bottom: 12px;
  animation: fadeInUp 0.7s var(--ease-spring) both;
}
.ops-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.op-btn {
  position: relative;
  padding: 16px 12px;
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  text-align: center;
  transition: box-shadow var(--dur) var(--ease), transform var(--dur-fast) var(--ease);
}
.op-btn > * { position: relative; z-index: 5; }
.op-btn:active { transform: scale(0.98); }
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

/* INFO бутонче в горния десен ъгъл на op-btn */
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

/* ═══ AI STUDIO ROW (под ops) ═══ */
.studio-row { margin-bottom: 12px; animation: fadeInUp 0.8s var(--ease-spring) both; }
.studio-btn {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px;
  position: relative;
  transition: box-shadow var(--dur) var(--ease), transform var(--dur-fast) var(--ease);
}
.studio-btn > * { position: relative; z-index: 5; }
.studio-btn:active { transform: scale(0.99); }
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

/* Range tabs (3д / 7д / 14д) — segmented */
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
.wfc-tabs .wfc-tab.active, .wfc-tab.active {
  color: white;
  background: linear-gradient(135deg, hsl(195 70% 50%), hsl(38 80% 55%));
  box-shadow: 0 3px 10px hsl(195 70% 45% / 0.4);
}

/* Days strip — horizontal scroll */
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

.wfc-days .wfc-day:nth-child(n+8) { display: none; }
.wfc-days[data-range="14"] .wfc-day:nth-child(n+8) { display: flex; }

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
.wfc-day-rain svg { width: 8px; height: 8px; stroke: currentColor; fill: none; stroke-width: 2.5; }

/* AI recs section */
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
.wfc-rec-ic {
  width: 30px; height: 30px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
}
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

/* Source pill */
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

/* ═══ AI HELP CARD (нова, q-magic / qhelp) ═══ */
.help-card {
  padding: 14px;
  margin-bottom: 14px;
  animation: fadeInUp 0.9s var(--ease-spring) both;
}
.help-card > * { position: relative; z-index: 5; }
.help-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.help-head-ic {
  width: 36px; height: 36px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
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

.help-body {
  font-size: 12px; font-weight: 600; color: var(--text-muted);
  line-height: 1.5;
  margin-bottom: 10px;
}
.help-body b { color: var(--text); font-weight: 800; }

/* Quick-action chips (примерни въпроси) */
.help-chips-label {
  font-family: var(--font-mono); font-size: 9px; font-weight: 800;
  letter-spacing: 0.08em; text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 6px;
}
.help-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.help-chip {
  padding: 7px 12px;
  border-radius: var(--radius-pill);
  font-size: 11.5px; font-weight: 700;
  color: var(--text);
  border: 1px solid var(--border-color);
  display: inline-flex; align-items: center; gap: 5px;
  transition: box-shadow var(--dur) var(--ease);
}
[data-theme="light"] .help-chip, :root:not([data-theme]) .help-chip { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="light"] .help-chip:active, :root:not([data-theme]) .help-chip:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .help-chip { background: hsl(220 25% 8%); }
.help-chip-q {
  font-family: var(--font-mono); font-size: 10px; font-weight: 900;
  color: var(--magic); flex-shrink: 0;
}
[data-theme="dark"] .help-chip-q { color: hsl(280 70% 70%); }

/* Видео placeholder */
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
  display: grid; place-items: center;
  flex-shrink: 0;
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
  color: var(--magic);
  letter-spacing: 0.04em;
}
[data-theme="dark"] .help-link-row { color: hsl(280 70% 75%); }
.help-link-row svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2.5; }

/* ═══ LIFE BOARD HEADER + COLLAPSED CARDS (под Help card) ═══ */
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
  display: grid; place-items: center;
  flex-shrink: 0;
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
.lb-card.q5 .lb-emoji-orb { background: hsl(38 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-card.q5 .lb-emoji-orb { background: hsl(38 50% 12%); }
.lb-card.q5 .lb-emoji-orb svg { stroke: hsl(38 80% 50%); }
[data-theme="dark"] .lb-card.q5 .lb-emoji-orb svg { stroke: hsl(38 90% 65%); }

.lb-collapsed-content { flex: 1; min-width: 0; }
.lb-fq-tag-mini { display: block; font-family: var(--font-mono); font-size: 8.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted); }
.lb-collapsed-title { display: block; font-size: 12px; font-weight: 700; margin-top: 2px; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lb-expand-btn {
  width: 24px; height: 24px; border-radius: var(--radius-icon);
  display: grid; place-items: center; flex-shrink: 0;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .lb-expand-btn, :root:not([data-theme]) .lb-expand-btn { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .lb-expand-btn { background: hsl(220 25% 8%); }
.lb-expand-btn svg { width: 11px; height: 11px; stroke: var(--text-muted); fill: none; stroke-width: 2.5; }

.see-more-mini {
  text-align: center; margin: 8px 0 4px;
  font-family: var(--font-mono); font-size: 11px; font-weight: 700;
  color: var(--accent);
}
[data-theme="dark"] .see-more-mini { color: hsl(var(--hue1) 80% 70%); }


/* ═══ EXPAND/COLLAPSE ANIMATION (production parity) ═══ */
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

.lb-card .lb-expand-btn { transition: transform 0.3s ease, box-shadow var(--dur) var(--ease); }
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
[data-theme="light"] .lb-action.primary, :root:not([data-theme]) .lb-action.primary { background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.4); }

.lb-feedback {
  display: flex; align-items: center; gap: 6px;
  font-size: 11px; font-weight: 600;
  color: var(--text-muted);
}
.lb-fb-label { font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.lb-fb-btn {
  width: 30px; height: 30px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .lb-fb-btn, :root:not([data-theme]) .lb-fb-btn { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="light"] .lb-fb-btn:active, :root:not([data-theme]) .lb-fb-btn:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-fb-btn { background: hsl(220 25% 8%); }
.lb-fb-btn svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2; }
.lb-fb-btn.up { color: hsl(145 60% 45%); }
[data-theme="dark"] .lb-fb-btn.up { color: hsl(145 70% 65%); }
.lb-fb-btn.down { color: hsl(0 70% 50%); }
[data-theme="dark"] .lb-fb-btn.down { color: hsl(0 80% 70%); }
.lb-fb-btn.hmm { color: hsl(38 80% 50%); }
[data-theme="dark"] .lb-fb-btn.hmm { color: hsl(38 90% 65%); }

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
  display: grid; place-items: center;
  flex-shrink: 0;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .info-card-ic, :root:not([data-theme]) .info-card-ic { background: var(--surface); box-shadow: var(--shadow-pressed); border: none; }
[data-theme="dark"] .info-card-ic { background: hsl(220 25% 4%); }
.info-card-ic svg { width: 22px; height: 22px; fill: none; stroke-width: 2; }
.info-card-title { flex: 1; font-size: 16px; font-weight: 800; letter-spacing: -0.01em; }
.info-card-close {
  width: 32px; height: 32px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
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
}
[data-theme="dark"] .info-card-cta { background: linear-gradient(135deg, hsl(var(--hue1) 80% 55%), hsl(var(--hue2) 80% 55%)); }
.info-card-cta::before { content: ''; position: absolute; inset: 0; background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.35) 85%, transparent 100%); animation: conicSpin 3s linear infinite; }
.info-card-cta > * { position: relative; z-index: 1; }
.info-card-cta svg { width: 14px; height: 14px; stroke: white; fill: none; stroke-width: 2.5; }

/* ═══ CHAT INPUT BAR (sticky) ═══ */
.chat-input-bar {
  position: fixed; left: 12px; right: 12px;
  bottom: calc(64px + 24px + env(safe-area-inset-bottom, 0));
  z-index: 49;
  height: 50px; padding: 0 8px 0 16px;
  border-radius: var(--radius-pill);
  display: flex; align-items: center; gap: 8px;
  border: 1px solid var(--border-color);
  max-width: 456px; margin: 0 auto;
}
[data-theme="light"] .chat-input-bar, :root:not([data-theme]) .chat-input-bar { background: var(--surface); box-shadow: var(--shadow-card); border: none; }
[data-theme="dark"] .chat-input-bar { background: linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .85), hsl(var(--hue2) 50% 8% / .8)); backdrop-filter: blur(16px); box-shadow: 0 8px 24px hsl(220 50% 4% / 0.5); }

.chat-input-icon { width: 18px; height: 18px; flex-shrink: 0; display: grid; place-items: center; }
.chat-input-icon svg { width: 14px; height: 14px; stroke: var(--magic); fill: none; stroke-width: 2; }
.chat-input-text { flex: 1; min-width: 0; font-size: 13px; font-weight: 600; color: var(--text-faint); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-mic, .chat-send {
  width: 38px; height: 38px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
}
.chat-mic { background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%)); box-shadow: 0 4px 12px hsl(280 70% 50% / 0.5); }
.chat-mic svg { width: 14px; height: 14px; stroke: white; fill: none; stroke-width: 2; }
.chat-send { background: transparent; }
.chat-send svg { width: 18px; height: 18px; stroke: var(--magic); fill: none; stroke-width: 2; }



/* ═══ FILTER PILLS ═══ */
.fp-row { display: flex; gap: 6px; overflow-x: auto; padding: 0 4px 8px; margin-bottom: 8px; -webkit-overflow-scrolling: touch; scrollbar-width: none; position: relative; z-index: 5; }
.fp-row::-webkit-scrolllbar { display: none; }
.fp-pill {
  flex: 0 0 auto;
  height: 32px; padding: 0 14px;
  border-radius: var(--radius-pill);
  display: inline-flex; align-items: center; gap: 5px;
  font-family: var(--font-mono); font-size: 10px; font-weight: 800;
  letter-spacing: 0.04em; color: var(--text-muted);
  border: 1px solid var(--border-color);
  white-space: nowrap;
  transition: box-shadow var(--dur) var(--ease), color var(--dur) var(--ease);
}
[data-theme="light"] .fp-pill, :root:not([data-theme]) .fp-pill { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .fp-pill { background: hsl(220 25% 8%); }
.fp-pill svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2; }
.fp-pill.active {
  color: white; border: none;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.4);
}
.fp-count {
  font-size: 9px; padding: 1px 6px;
  border-radius: var(--radius-pill);
  background: rgba(255,255,255,0.18);
  border: 1px solid rgba(255,255,255,0.2);
}
.fp-pill:not(.active) .fp-count { background: var(--accent); color: white; border: none; }

/* ═══ BOTTOM NAV ═══ */
.rms-bottom-nav {
  position: fixed; left: 12px; right: 12px; bottom: 12px;
  z-index: 50; height: 64px;
  display: grid; grid-template-columns: repeat(4, 1fr);
  border-radius: var(--radius);
  border: 1px solid var(--border-color);
  padding-bottom: env(safe-area-inset-bottom, 0);
  max-width: 456px; margin: 0 auto;
}
[data-theme="light"] .rms-bottom-nav, :root:not([data-theme]) .rms-bottom-nav { background: var(--surface); box-shadow: var(--shadow-card); border: none; }
[data-theme="dark"] .rms-bottom-nav {
  background: linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%), linear-gradient(45deg, hsl(var(--hue2) 50% 10% / .8), hsl(var(--hue2) 50% 10% / 0) 33%), linear-gradient(hsl(220 25% 4.8% / .9));
  backdrop-filter: blur(12px); box-shadow: var(--shadow-card);
}
.rms-nav-tab {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 3px; color: var(--text-muted);
  font-size: 10px; font-weight: 700;
  position: relative;
  transition: color var(--dur) var(--ease);
}
.rms-nav-tab svg { width: 22px; height: 22px; stroke: currentColor; fill: none; stroke-width: 2; transition: transform var(--dur) var(--ease-spring); }
.rms-nav-tab:active svg { transform: scale(0.85); }
.rms-nav-tab.active { color: var(--accent); }
.rms-nav-tab.active::before { content: ''; position: absolute; top: 6px; left: 50%; transform: translateX(-50%); width: 32px; height: 4px; background: var(--accent); border-radius: var(--radius-pill); }
[data-theme="dark"] .rms-nav-tab.active::before { box-shadow: 0 0 12px var(--accent); }

/* anims */
@keyframes orbSpin { to { transform: rotate(360deg); } }
@keyframes conicSpin { to { transform: rotate(360deg); } }
@keyframes auroraDrift { 0%,100% { transform: translate(0,0) scale(1); } 33% { transform: translate(30px,-25px) scale(1.1); } 66% { transform: translate(-25px,35px) scale(0.95); } }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes popUp { from { opacity: 0; transform: scale(0.9) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
@keyframes rmsBrandShimmer { 0% { background-position: 0% center; } 100% { background-position: 200% center; } }
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; } }

/* ═══ S82-DASH (production prozor за печалба) ═══ */
.s82-dash { padding: 14px 16px 12px; margin-bottom: 12px; position: relative; isolation: isolate; }
.s82-dash > * { position: relative; z-index: 5; }
.s82-dash-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; gap: 6px; }
.s82-dash-period-label { font-family: var(--font-mono); font-size: 9px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; }
.s82-dash-numrow { display: flex; align-items: baseline; gap: 6px; margin-bottom: 4px; flex-wrap: wrap; }
.s82-dash-num { font-size: 26px; font-weight: 900; letter-spacing: -0.02em; color: var(--text); font-variant-numeric: tabular-nums; line-height: 1; }
[data-theme="dark"] .s82-dash-num { color: hsl(var(--hue1) 70% 82%); text-shadow: 0 0 14px hsl(var(--hue1) 70% 50% / 0.35); }
.s82-dash-cur { font-size: 11px; color: var(--text-muted); font-weight: 700; }
.s82-dash-pct { font-size: 13px; font-weight: 800; color: hsl(145 65% 40%); }
[data-theme="dark"] .s82-dash-pct { color: #4ade80; text-shadow: 0 0 10px hsl(140 80% 50% / 0.45); }
.s82-dash-pct.neg { color: hsl(0 70% 50%); }
.s82-dash-meta { font-size: 11px; color: var(--text-muted); margin-bottom: 10px; font-weight: 600; line-height: 1.5; }
.s82-dash-pills { display: flex; gap: 4px; flex-wrap: wrap; align-items: center; }
.s82-dash-pill {
  font-family: var(--font-mono); font-size: 10px; padding: 6px 11px; min-height: 26px;
  border-radius: var(--radius-pill); cursor: pointer;
  letter-spacing: 0.04em; font-weight: 700;
  color: var(--text-muted); border: none;
  transition: box-shadow var(--dur) var(--ease), color var(--dur) var(--ease);
}
[data-theme="light"] .s82-dash-pill, :root:not([data-theme]) .s82-dash-pill {
  background: var(--surface); box-shadow: var(--shadow-card-sm);
}
[data-theme="dark"] .s82-dash-pill { background: rgba(255,255,255,0.025); border: 1px solid transparent; }
.s82-dash-pill.active {
  color: white;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.35);
}
[data-theme="dark"] .s82-dash-pill.active {
  background: hsl(var(--hue1) 70% 50% / 0.18);
  border-color: hsl(var(--hue1) 70% 55% / 0.35);
  color: hsl(var(--hue1) 75% 82%);
}
.s82-dash-divider { width: 1px; height: 16px; background: var(--text-faint); margin: 0 6px; align-self: center; opacity: 0.3; }

/* ═══ SUBBAR (Store + Where + Mode toggle, sticky под header) ═══ */
.rms-subbar {
  position: sticky; top: 56px; z-index: 49;
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; max-width: 480px; margin: 0 auto;
}
[data-theme="light"] .rms-subbar, :root:not([data-theme]) .rms-subbar { background: var(--bg-main); }
[data-theme="dark"] .rms-subbar {
  background: hsl(220 25% 4.8% / 0.85);
  backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
}
.rms-store-toggle {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 12px; border-radius: var(--radius-pill);
  font-size: 12px; font-weight: 800; letter-spacing: -0.01em;
  color: var(--text); cursor: pointer; border: none; outline: none; font-family: inherit;
  transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
}
[data-theme="light"] .rms-store-toggle, :root:not([data-theme]) .rms-store-toggle {
  background: var(--surface); box-shadow: var(--shadow-card-sm);
}
[data-theme="dark"] .rms-store-toggle {
  background: hsl(220 25% 8% / 0.7); backdrop-filter: blur(8px);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}
.rms-store-toggle svg { width: 13px; height: 13px; fill: none; stroke: var(--accent); stroke-width: 2; flex-shrink: 0; }
[data-theme="dark"] .rms-store-toggle svg { stroke: hsl(var(--hue1) 80% 75%); }
.rms-store-toggle .store-chev { width: 10px; height: 10px; stroke: var(--text-muted); }
.rms-store-toggle:active { transform: scale(var(--press)); }
.subbar-where {
  flex: 1; text-align: center;
  font-family: var(--font-mono); font-size: 10px; font-weight: 700;
  letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted);
}

/* AI: rotating orbits + diamond ping */
.rms-nav-tab[aria-label="AI"] svg .nav-orbit-1 { transform-origin: 12px 12px; animation: navOrbitSpin 8s linear infinite; }
.rms-nav-tab[aria-label="AI"] svg .nav-orbit-2 { transform-origin: 12px 12px; animation: navOrbitSpin 8s linear infinite reverse; }
.rms-nav-tab[aria-label="AI"] svg .nav-diamond { transform-origin: 12px 12px; animation: navBagPulse 2.5s ease-in-out infinite; }

/* Склад: floating boxes */
.rms-nav-tab[aria-label="Склад"] svg { animation: navBoxFloat 3s ease-in-out infinite; }

/* Статистики: drawing line + pulsing dots */
.rms-nav-tab[aria-label="Статистики"] svg .nav-stats-line {
  stroke-dasharray: 60;
  animation: navStatsDraw 2.5s ease-out infinite alternate;
}
.rms-nav-tab[aria-label="Статистики"] svg .nav-stats-dot {
  transform-origin: center; transform-box: fill-box;
  animation: navStatsDot 1.6s ease-in-out infinite;
}
.rms-nav-tab[aria-label="Статистики"] svg .nav-stats-dot:nth-child(2) { animation-delay: 0.2s; }
.rms-nav-tab[aria-label="Статистики"] svg .nav-stats-dot:nth-child(3) { animation-delay: 0.4s; }
.rms-nav-tab[aria-label="Статистики"] svg .nav-stats-dot:nth-child(4) { animation-delay: 0.6s; }
.rms-nav-tab[aria-label="Статистики"] svg .nav-stats-dot:nth-child(5) { animation-delay: 0.8s; }

/* Продажби: bag pulse + bolt flash */
.rms-nav-tab[aria-label="Продажби"] svg { transform-origin: center; animation: navBagPulse 2.4s ease-in-out infinite; }
.rms-nav-tab[aria-label="Продажби"] svg .nav-bolt { animation: navBoltFlash 1.8s ease-in-out infinite; }

@media (prefers-reduced-motion: reduce) {
  .rms-nav-tab svg, .rms-nav-tab svg * { animation: none !important; }
}

/* ═══ BOTTOM NAV — circular orbs (като AI Studio) ═══ */
@keyframes navOrbBreath {
  0%, 100% { transform: scale(1); box-shadow: 0 4px 12px var(--orb-shadow, hsl(280 70% 50% / 0.4)); }
  50% { transform: scale(1.04); box-shadow: 0 6px 18px var(--orb-shadow, hsl(280 70% 50% / 0.55)); }
}
@keyframes navOrbShimmer {
  0% { background-position: 0% 50%; }
  100% { background-position: 200% 50%; }
}
@keyframes navOrbActiveSpin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
@keyframes chatMicRing {
  0% { transform: scale(1); opacity: 0.6; }
  100% { transform: scale(2.2); opacity: 0; }
}
@keyframes chatSendDrift {
  0%, 100% { transform: translateX(0); }
  50% { transform: translateX(2px); }
}

/* Override на rms-nav-tab — premestva svg vutrre v orb */
.rms-bottom-nav .rms-nav-tab { gap: 4px; padding: 6px 0; }
.rms-bottom-nav .rms-nav-tab .nav-orb {
  width: 32px; height: 32px;
  border-radius: 50%;
  display: grid; place-items: center;
  position: relative;
  background-size: 200% 200%;
  animation: navOrbBreath 3.2s ease-in-out infinite, navOrbShimmer 6s linear infinite;
  flex-shrink: 0;
}
.rms-bottom-nav .rms-nav-tab .nav-orb svg {
  width: 17px; height: 17px;
  stroke: white; fill: none;
  stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
  position: relative; z-index: 2;
}
.rms-bottom-nav .rms-nav-tab span:not(.nav-orb) {
  font-size: 10px; font-weight: 700; letter-spacing: 0.04em;
  color: var(--text-muted);
}

/* Active state: orb с conic glow ring */
.rms-bottom-nav .rms-nav-tab.active span:not(.nav-orb) {
  color: var(--text);
  font-weight: 800;
}
.rms-bottom-nav .rms-nav-tab.active .nav-orb::before {
  content: '';
  position: absolute; inset: -4px;
  border-radius: 50%;
  background: conic-gradient(from 0deg, transparent 70%, currentColor 90%, transparent 100%);
  animation: navOrbActiveSpin 3s linear infinite;
  opacity: 0.6; pointer-events: none;
  z-index: 1;
  -webkit-mask: radial-gradient(circle, transparent 60%, #000 70%);
  mask: radial-gradient(circle, transparent 60%, #000 70%);
}

/* Per-tab gradient + цвят */
.rms-bottom-nav .rms-nav-tab[aria-label="AI"] .nav-orb {
  background-image: linear-gradient(135deg, hsl(265 75% 55%), hsl(295 70% 55%), hsl(320 65% 55%), hsl(265 75% 55%));
  --orb-shadow: hsl(280 70% 50% / 0.45);
}
.rms-bottom-nav .rms-nav-tab[aria-label="AI"] { color: hsl(280 65% 55%); }
[data-theme="dark"] .rms-bottom-nav .rms-nav-tab[aria-label="AI"] { color: hsl(280 75% 75%); }

.rms-bottom-nav .rms-nav-tab[aria-label="Склад"] .nav-orb {
  background-image: linear-gradient(135deg, hsl(195 75% 50%), hsl(180 75% 50%), hsl(210 75% 55%), hsl(195 75% 50%));
  --orb-shadow: hsl(195 70% 50% / 0.45);
}
.rms-bottom-nav .rms-nav-tab[aria-label="Склад"] { color: hsl(195 65% 45%); }
[data-theme="dark"] .rms-bottom-nav .rms-nav-tab[aria-label="Склад"] { color: hsl(195 75% 70%); }

.rms-bottom-nav .rms-nav-tab[aria-label="Статистики"] .nav-orb {
  background-image: linear-gradient(135deg, hsl(145 65% 45%), hsl(165 65% 45%), hsl(125 65% 45%), hsl(145 65% 45%));
  --orb-shadow: hsl(145 60% 45% / 0.45);
}
.rms-bottom-nav .rms-nav-tab[aria-label="Статистики"] { color: hsl(145 60% 35%); }
[data-theme="dark"] .rms-bottom-nav .rms-nav-tab[aria-label="Статистики"] { color: hsl(145 70% 65%); }

.rms-bottom-nav .rms-nav-tab[aria-label="Продажби"] .nav-orb {
  background-image: linear-gradient(135deg, hsl(38 90% 55%), hsl(28 90% 55%), hsl(48 90% 55%), hsl(38 90% 55%));
  --orb-shadow: hsl(38 80% 50% / 0.45);
}
.rms-bottom-nav .rms-nav-tab[aria-label="Продажби"] { color: hsl(28 75% 45%); }
[data-theme="dark"] .rms-bottom-nav .rms-nav-tab[aria-label="Продажби"] { color: hsl(38 85% 65%); }

/* Stagger animation (всеки orb с леко различен phase, по-органично) */
.rms-bottom-nav .rms-nav-tab[aria-label="Склад"] .nav-orb { animation-delay: -0.8s, -1.5s; }
.rms-bottom-nav .rms-nav-tab[aria-label="Статистики"] .nav-orb { animation-delay: -1.6s, -3s; }
.rms-bottom-nav .rms-nav-tab[aria-label="Продажби"] .nav-orb { animation-delay: -2.4s, -4.5s; }

@media (prefers-reduced-motion: reduce) {
  .rms-bottom-nav .nav-orb, .rms-bottom-nav .nav-orb::before { animation: none !important; }
  .chat-mic::before, .chat-mic::after, .chat-send svg { animation: none !important; }
}

/* Chat input animations: pulsing rings around mic + send arrow drift */
.chat-mic { position: relative; }
.chat-mic::before, .chat-mic::after {
  content: ''; position: absolute; inset: 0;
  border-radius: 50%;
  border: 2px solid hsl(280 70% 55%);
  pointer-events: none;
  animation: chatMicRing 2s ease-out infinite;
}
.chat-mic::after { animation-delay: 1s; }
.chat-send svg { animation: chatSendDrift 1.8s ease-in-out infinite; }


/* Inner SVG animations за Статистики и Продажби (върху orb pattern) */
@keyframes navStatsLineDraw {
  0% { stroke-dashoffset: 60; opacity: 0.4; }
  60% { stroke-dashoffset: 0; opacity: 1; }
  100% { stroke-dashoffset: -60; opacity: 0.4; }
}
@keyframes navStatsDotPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.4); }
}
@keyframes navBoltZap {
  0%, 80%, 100% { opacity: 0.5; }
  10%, 30% { opacity: 1; }
  20% { opacity: 0.7; }
}
.rms-bottom-nav .rms-nav-tab[aria-label="Статистики"] .nav-orb svg .nav-stats-line {
  stroke-dasharray: 60;
  animation: navStatsLineDraw 3s ease-in-out infinite;
}
.rms-bottom-nav .rms-nav-tab[aria-label="Статистики"] .nav-orb svg .nav-stats-dot {
  transform-origin: center; transform-box: fill-box;
  animation: navStatsDotPulse 1.6s ease-in-out infinite;
}
.rms-bottom-nav .rms-nav-tab[aria-label="Статистики"] .nav-orb svg .nav-stats-dot:nth-of-type(2) { animation-delay: 0.2s; }
.rms-bottom-nav .rms-nav-tab[aria-label="Статистики"] .nav-orb svg .nav-stats-dot:nth-of-type(3) { animation-delay: 0.4s; }
.rms-bottom-nav .rms-nav-tab[aria-label="Статистики"] .nav-orb svg .nav-stats-dot:nth-of-type(4) { animation-delay: 0.6s; }
.rms-bottom-nav .rms-nav-tab[aria-label="Статистики"] .nav-orb svg .nav-stats-dot:nth-of-type(5) { animation-delay: 0.8s; }
.rms-bottom-nav .rms-nav-tab[aria-label="Продажби"] .nav-orb svg .nav-bolt {
  animation: navBoltZap 2.2s ease-in-out infinite;
}
@media (prefers-reduced-motion: reduce) {
  .nav-stats-line, .nav-stats-dot, .nav-bolt { animation: none !important; }
}

/* ═════════════════════════════════════════════════════════════
   S140 OVERRIDES — корекции спрямо стария макет (chat.php)
   ═════════════════════════════════════════════════════════════ */

/* Header override — компактни 22x22 иконки (като в P10 / life-board-v2) */
.rms-header .rms-icon-btn { width: 22px; height: 22px; padding: 0; }
.rms-header .rms-icon-btn svg { width: 11px; height: 11px; }
.rms-header .rms-header-icons { gap: 4px; }
.rms-header-spacer { flex: 1; }

/* Life Board card body — вдлъбнат "обяснителен прозорец" (като в стария макет) */
.lb-card .lb-body {
    padding: 14px 16px !important;
    border-radius: 14px !important;
    margin: 4px 0 14px !important;
    font-size: 13px !important;
    line-height: 1.55 !important;
    border: none !important;
}
[data-theme="light"] .lb-card .lb-body,
:root:not([data-theme]) .lb-card .lb-body {
    background: #d8dee8 !important;
    box-shadow: inset 3px 3px 6px rgba(163,177,198,0.55),
                inset -3px -3px 6px rgba(255,255,255,0.85) !important;
    color: #475569 !important;
}
[data-theme="dark"] .lb-card .lb-body {
    background: hsl(220 25% 3% / 0.65) !important;
    box-shadow: inset 0 2px 6px hsl(220 30% 1% / 0.6) !important;
    color: rgba(255,255,255,0.75) !important;
}
.lb-card .lb-body b {
    color: #1e1e2f;
    font-weight: 700;
}
[data-theme="dark"] .lb-card .lb-body b { color: #f1f5f9; }

</style>
</head><body>

<div class="aurora" aria-hidden="true">
  <div class="aurora-blob"></div><div class="aurora-blob"></div><div class="aurora-blob"></div>
</div>

<!-- ═══ HEADER (Тип Б — вътрешен модул, с камера) ═══ -->
<header class="rms-header">
  <a class="rms-brand" href="chat.php" title="Начало">
    <span class="brand-1">RunMyStore</span><span class="brand-2">.ai</span>
  </a>
  <span class="rms-plan-badge">PRO</span>
  <div class="rms-header-spacer"></div>
  <button class="rms-icon-btn" aria-label="Камера" onclick="openCamera()" title="Сканирай баркод">
    <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
  </button>
  <a class="rms-icon-btn" aria-label="Принтер" href="printer-setup.php">
    <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
  </a>
  <a class="rms-icon-btn" aria-label="Настройки" href="settings.php">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
  </a>
  <button class="rms-icon-btn" aria-label="Изход" onclick="if(confirm('Изход?'))location.href='logout.php'">
    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
  </button>
  <button class="rms-icon-btn" id="themeToggle" onclick="rmsToggleTheme()" aria-label="Тема">
    <svg id="themeIconSun" viewBox="0 0 24 24" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
    <svg id="themeIconMoon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
  </button>
</header>

<!-- ═══ SUBBAR — store toggle + СКЛАД label + mode toggle ═══ -->
<div class="rms-subbar">
  <?php if (count($all_stores) > 1): ?>
  <select class="rms-store-toggle" aria-label="Смени обект" onchange="location.href='?store='+this.value+'&mode='+(<?= $is_simple_view?'"simple"':'"detailed"' ?>)" style="-webkit-appearance:none;-moz-appearance:none;appearance:none;background-image:url(&quot;data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5'><polyline points='6 9 12 15 18 9'/></svg>&quot;);background-repeat:no-repeat;background-position:right 8px center;background-size:12px 12px;padding-right:28px;">
    <?php foreach ($all_stores as $st): ?>
    <option value="<?= (int)$st['id'] ?>" <?= $st['id']==$store_id?'selected':'' ?>><?= htmlspecialchars($st['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php else: ?>
  <button class="rms-store-toggle" aria-label="Обект" style="cursor:default" disabled>
    <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    <span class="store-name"><?= htmlspecialchars($store_name) ?></span>
  </button>
  <?php endif; ?>
  <span class="subbar-where">СКЛАД</span>
  <?php if ($is_simple_view): ?>
  <a class="lb-mode-toggle" href="?mode=detailed" title="Разширен режим">
    <span>Разширен</span>
    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  </a>
  <?php else: ?>
  <a class="lb-mode-toggle" href="?mode=simple" title="Лесен режим">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    <span>Лесен</span>
  </a>
  <?php endif; ?>
</div>

<main class="app">

<?php if ($is_simple_view): ?>
<!-- ═══════════════════════════════════════════════════════ -->
<!-- SIMPLE MODE (P15) — главна за Пешо                       -->
<!-- ═══════════════════════════════════════════════════════ -->
<!-- TODO STEP 2: P15 content тук -->
<div style="padding:40px 20px;text-align:center;color:var(--text-muted)">
  <h2 style="margin-bottom:16px">📦 Стоката · Лесен режим</h2>
  <p>P15 съдържание идва в Step 2.</p>
  <p style="margin-top:8px;font-size:11px">Тестов скелет — products-v2.php</p>
  <p style="margin-top:24px;font-size:11px">Свършили: <b><?= $out_of_stock ?></b> · Застояли 60+: <b><?= $stale_60d ?></b> · Общо: <b><?= $total_products ?></b></p>
</div>
<?php else: ?>
<!-- ═══════════════════════════════════════════════════════ -->
<!-- DETAILED MODE (P2v2 tabs) — главна за Митко              -->
<!-- ═══════════════════════════════════════════════════════ -->
<!-- TODO STEP 3: P2v2 tabs content -->
<div style="padding:40px 20px;text-align:center;color:var(--text-muted)">
  <h2 style="margin-bottom:16px">📊 Стоката · Разширен режим</h2>
  <p>P2v2 табове идват в Step 3.</p>
  <p style="margin-top:8px;font-size:11px">Тестов скелет — products-v2.php</p>
  <p style="margin-top:24px;font-size:11px">Свършили: <b><?= $out_of_stock ?></b> · Застояли 60+: <b><?= $stale_60d ?></b> · Общо: <b><?= $total_products ?></b></p>
</div>
<?php endif; ?>

</main>

<?php if ($is_simple_view): ?>
<!-- ═══ CHAT INPUT BAR — sticky отдолу (само в simple mode) ═══ -->
<div class="chat-input-bar" onclick="alert('Чат отворен (TODO)')" role="button" tabindex="0" style="cursor:pointer">
  <span class="chat-input-icon">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="3" y2="12"/><line x1="6" y1="9" x2="6" y2="15"/><line x1="9" y1="6" x2="9" y2="18"/><line x1="12" y1="9" x2="12" y2="15"/><line x1="15" y1="11" x2="15" y2="13"/></svg>
  </span>
  <span class="chat-input-text">Кажи или напиши...</span>
  <button class="chat-mic" aria-label="Глас">
    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0014 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
  </button>
  <button class="chat-send" aria-label="Изпрати">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
  </button>
</div>
<?php else: ?>
<!-- ═══ BOTTOM NAV — 4 orbs (само в detailed mode) ═══ -->
<nav class="rms-bottom-nav">
  <a href="chat.php" class="rms-nav-tab" aria-label="AI"><span class="nav-orb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 10v6m11-11h-6M7 12H1m17.07-7.07l-4.24 4.24M9.17 14.83l-4.24 4.24m0-13.14l4.24 4.24m5.66 5.66l4.24 4.24"/></svg></span><span>AI</span></a>
  <a href="warehouse.php" class="rms-nav-tab active" aria-label="Склад"><span class="nav-orb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg></span><span>Склад</span></a>
  <a href="stats.php" class="rms-nav-tab" aria-label="Справки"><span class="nav-orb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><span>Справки</span></a>
  <a href="sale.php" class="rms-nav-tab" aria-label="Продажба"><span class="nav-orb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span><span>Продажба</span></a>
</nav>
<?php endif; ?>

<script>
// Theme toggle
function rmsToggleTheme(){
  var cur = document.documentElement.getAttribute('data-theme') || 'dark';
  var next = cur === 'light' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', next);
  try{localStorage.setItem('rms_theme', next)}catch(_){}
  var sun = document.getElementById('themeIconSun'), moon = document.getElementById('themeIconMoon');
  if(sun && moon){ sun.style.display = next==='light' ? '' : 'none'; moon.style.display = next==='light' ? 'none' : ''; }
}
// Init theme icon state on load
(function(){
  var theme = document.documentElement.getAttribute('data-theme') || 'dark';
  var sun = document.getElementById('themeIconSun'), moon = document.getElementById('themeIconMoon');
  if(sun) sun.style.display = theme==='light' ? '' : 'none';
  if(moon) moon.style.display = theme==='light' ? 'none' : '';
})();
function openCamera(){ alert('Камера TODO'); }
</script>

</body>
</html>