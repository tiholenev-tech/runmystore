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
<html lang="bg">
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
:root{
    --hue1:255; --hue2:222;
    --border:1px; --border-color:hsl(var(--hue2),12%,20%);
    --radius:22px; --radius-sm:14px;
    --ease:cubic-bezier(0.5,1,0.89,1);
    --bg-main:#08090d;
    --text-primary:#f1f5f9;
    --text-secondary:rgba(255,255,255,.6);
    --text-muted:rgba(255,255,255,.4);
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{background:var(--bg-main);color:var(--text-primary);font-family:'Montserrat',Inter,system-ui,sans-serif;min-height:100vh;overflow-x:hidden;-webkit-user-select:none;user-select:none}
body{
    background:
        radial-gradient(ellipse 800px 500px at 20% 10%,hsl(var(--hue1) 60% 35% / .22) 0%,transparent 60%),
        radial-gradient(ellipse 700px 500px at 85% 85%,hsl(var(--hue2) 60% 35% / .22) 0%,transparent 60%),
        linear-gradient(180deg,#0a0b14 0%,#050609 100%);
    background-attachment:fixed;
    padding-bottom:calc(80px + env(safe-area-inset-bottom,0px));
    position:relative
}
body::before{
    content:'';position:fixed;inset:0;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
    opacity:.03;pointer-events:none;z-index:1;mix-blend-mode:overlay
}

/* GLASS — 1:1 production warehouse.html / chat.php */
.glass{
    position:relative;
    border-radius:var(--radius);
    border:var(--border) solid var(--border-color);
    background:
        linear-gradient(235deg,hsl(var(--hue1) 50% 10% / .8),hsl(var(--hue1) 50% 10% / 0) 33%),
        linear-gradient(45deg,hsl(var(--hue2) 50% 10% / .8),hsl(var(--hue2) 50% 10% / 0) 33%),
        linear-gradient(hsl(220deg 25% 4.8% / .78));
    backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
    box-shadow:hsl(var(--hue2) 50% 2%) 0 10px 16px -8px,hsl(var(--hue2) 50% 4%) 0 20px 36px -14px;
    isolation:isolate
}
.glass.sm{--radius:var(--radius-sm)}
.glass .shine,.glass .glow{--hue:var(--hue1)}
.glass .shine-bottom,.glass .glow-bottom{--hue:var(--hue2);--conic:135deg}
.glass .shine,.glass .shine::before,.glass .shine::after{
    pointer-events:none;border-radius:0;
    border-top-right-radius:inherit;border-bottom-left-radius:inherit;
    border:1px solid transparent;
    width:75%;aspect-ratio:1;display:block;position:absolute;
    right:calc(var(--border) * -1);top:calc(var(--border) * -1);
    left:auto;z-index:1;--start:12%;
    background:conic-gradient(from var(--conic,-45deg) at center in oklch,transparent var(--start,0%),hsl(var(--hue),var(--sat,80%),var(--lit,60%)),transparent var(--end,50%)) border-box;
    mask:linear-gradient(transparent),linear-gradient(black);
    mask-repeat:no-repeat;mask-clip:padding-box,border-box;mask-composite:subtract
}
.glass .shine::before,.glass .shine::after{content:"";width:auto;inset:-2px;mask:none}
.glass .shine::after{z-index:2;--start:17%;--end:33%;background:conic-gradient(from var(--conic,-45deg) at center in oklch,transparent var(--start,0%),hsl(var(--hue),var(--sat,80%),var(--lit,85%)),transparent var(--end,50%))}
.glass .shine-bottom{top:auto;bottom:calc(var(--border) * -1);left:calc(var(--border) * -1);right:auto}
.glass .glow{
    pointer-events:none;
    border-top-right-radius:calc(var(--radius) * 2.5);
    border-bottom-left-radius:calc(var(--radius) * 2.5);
    border:calc(var(--radius) * 1.25) solid transparent;
    inset:calc(var(--radius) * -2);
    width:75%;aspect-ratio:1;display:block;position:absolute;left:auto;bottom:auto;
    mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='240' height='240'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='3' seed='5'/%3E%3CfeColorMatrix values='0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    mask-mode:luminance;mask-size:29%;opacity:1;filter:blur(12px) saturate(1.25) brightness(0.5);
    mix-blend-mode:plus-lighter;z-index:3
}
.glass .glow.glow-bottom{inset:calc(var(--radius) * -2);top:auto;right:auto}
.glass .glow::before,.glass .glow::after{
    content:"";position:absolute;inset:0;
    border:inherit;border-radius:inherit;
    background:conic-gradient(from var(--conic,-45deg) at center in oklch,transparent var(--start,0%),hsl(var(--hue),var(--sat,95%),var(--lit,60%)),transparent var(--end,50%)) border-box;
    mask:linear-gradient(transparent),linear-gradient(black);
    mask-repeat:no-repeat;mask-clip:padding-box,border-box;mask-composite:subtract;
    filter:saturate(2) brightness(1)
}
.glass .glow::after{--lit:70%;--sat:100%;--start:15%;--end:35%;border-width:calc(var(--radius) * 1.75);border-radius:calc(var(--radius) * 2.75);inset:calc(var(--radius) * -.25);z-index:4;opacity:.75}

/* HUE VARIANTS */
.qd{--hue1:255;--hue2:222}
.qw{--hue1:200;--hue2:220}
.q1,.lb-card.q1{--hue1:0;--hue2:340}
.q2,.lb-card.q2{--hue1:280;--hue2:300}
.q3,.lb-card.q3{--hue1:140;--hue2:160}
.q4,.lb-card.q4{--hue1:175;--hue2:195}
.q5,.lb-card.q5{--hue1:38;--hue2:28}
.q6,.lb-card.q6{--hue1:220;--hue2:230}

.app{max-width:480px;margin:0 auto;width:100%;position:relative;z-index:2}
.scroll{padding:8px 12px 0}

/* ─── Mode toggle row (sits below production rms-header) ─── */
.lb-mode-row{
    display:flex;justify-content:flex-end;
    padding:6px 12px 0;
    max-width:480px;margin:0 auto;position:relative;z-index:3
}
.lb-mode-toggle{
    display:inline-flex;align-items:center;gap:4px;
    padding:6px 12px;border-radius:14px;
    height:30px;font-size:9.5px;font-weight:700;letter-spacing:.04em;
    color:hsl(var(--hue1) 70% 80%);
    background:rgba(99,102,241,.08);
    border:1px solid rgba(99,102,241,.18);
    text-decoration:none;font-family:inherit;cursor:pointer;
    box-shadow:0 0 8px hsl(var(--hue1) 60% 50% / .15)
}
.lb-mode-toggle svg{width:10px;height:10px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}

/* ─── TOP ROW (mini dash + mini weather) ─── */
.top-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:6px 0 12px}
.cell{padding:9px 12px}
.cell > *{position:relative;z-index:5}
.cell-label{font-size:7px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px}
.cell-numrow{display:flex;align-items:baseline;gap:3px;margin-bottom:2px;flex-wrap:wrap}
.cell-num{font-size:20px;font-weight:900;letter-spacing:-.4px;color:hsl(var(--hue1) 70% 82%);text-shadow:0 0 10px hsl(var(--hue1) 70% 50% / .4);font-variant-numeric:tabular-nums}
.cell-cur{font-size:8px;color:var(--text-muted);font-weight:700}
.cell-pct{font-size:11px;font-weight:800;color:#4ade80;margin-left:2px;text-shadow:0 0 6px hsl(140 80% 50% / .4)}
.cell-pct.neg{color:#ef4444;text-shadow:0 0 6px hsl(0 80% 50% / .35)}
.cell-pct.zero{color:#94a3b8;text-shadow:none}
.cell-meta{font-size:7.5px;color:var(--text-muted);font-weight:600;line-height:1.25}
.weather-cell-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:1px}
.weather-cell-icon svg{width:16px;height:16px;fill:none;stroke:hsl(200 70% 75%);stroke-width:1.5;filter:drop-shadow(0 0 4px hsl(200 70% 55% / .55));stroke-linecap:round;stroke-linejoin:round}
.weather-cell-temp{font-size:20px;font-weight:900;color:hsl(200 75% 88%);letter-spacing:-.3px;text-shadow:0 0 10px hsl(200 70% 55% / .4);font-variant-numeric:tabular-nums}
.weather-cell-cond{font-size:8.5px;font-weight:700;color:hsl(200 60% 78%)}

/* ─── Life Board mini header + cards ─── */
.lb-header{display:flex;align-items:center;justify-content:space-between;margin:6px 4px 6px}
.lb-title{display:flex;align-items:center;gap:5px}
.lb-title svg{width:11px;height:11px;fill:hsl(var(--hue1) 70% 75%);filter:drop-shadow(0 0 5px hsl(var(--hue1) 70% 50% / .55))}
.lb-title-text{font-size:9px;font-weight:900;letter-spacing:.4px;text-transform:uppercase;color:hsl(var(--hue1) 65% 80%);text-shadow:0 0 6px hsl(var(--hue1) 70% 55% / .35)}
.lb-count{font-size:7.5px;color:var(--text-muted);font-weight:700}

.lb-card{margin-bottom:7px}
.lb-card > *{position:relative;z-index:5}
.lb-collapsed{display:flex;align-items:center;gap:8px;padding:10px 12px;cursor:pointer;min-height:44px}
.lb-emoji{font-size:14px;flex-shrink:0;line-height:1;filter:drop-shadow(0 0 6px hsl(var(--hue1) 80% 55% / .55))}
.lb-collapsed-content{flex:1;min-width:0;display:flex;flex-direction:column;gap:1px}
.lb-fq-tag-mini{font-size:7px;font-weight:900;text-transform:uppercase;letter-spacing:.4px;color:hsl(var(--hue1) 70% 70%);line-height:1;text-shadow:0 0 4px hsl(var(--hue1) 70% 55% / .3)}
.lb-collapsed-title{font-size:11.5px;font-weight:800;line-height:1.25;letter-spacing:-.1px;color:hsl(var(--hue1) 92% 90%);text-shadow:0 0 6px hsl(var(--hue1) 80% 55% / .35);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lb-expand-btn{flex-shrink:0;width:28px;height:28px;min-width:28px;border-radius:8px;background:hsl(var(--hue1) 40% 18% / .65);border:1px solid hsl(var(--hue1) 50% 35% / .45);color:hsl(var(--hue1) 75% 78%);cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;padding:0}
.lb-expand-btn svg{width:12px;height:12px;stroke:currentColor;stroke-width:2.5;fill:none;transition:transform .25s var(--ease);stroke-linecap:round;stroke-linejoin:round}
.lb-card.expanded .lb-expand-btn svg{transform:rotate(180deg)}
.lb-expanded{max-height:0;overflow:hidden;transition:max-height .3s var(--ease),padding .3s var(--ease);padding:0 12px}
.lb-card.expanded .lb-expanded{max-height:360px;padding:0 12px 10px}
.lb-body{font-size:10.5px;color:var(--text-secondary);line-height:1.45;font-weight:500;margin-bottom:9px;padding-left:22px}
.lb-actions{display:flex;gap:5px;margin-bottom:7px;padding-left:22px;flex-wrap:wrap}
.lb-action{flex:1;min-width:64px;font-size:9.5px;font-weight:800;padding:8px 8px;min-height:32px;border-radius:8px;border:1px solid hsl(var(--hue1) 50% 30% / .55);background:hsl(var(--hue1) 45% 14% / .75);color:hsl(var(--hue1) 80% 80%);cursor:pointer;font-family:inherit;letter-spacing:.2px;transition:all .15s var(--ease);text-shadow:0 0 4px hsl(var(--hue1) 80% 55% / .25);text-decoration:none;text-align:center;display:inline-flex;align-items:center;justify-content:center}
.lb-action:hover,.lb-action:active{background:hsl(var(--hue1) 55% 20% / .85);border-color:hsl(var(--hue1) 65% 50% / .75)}
.lb-action.primary{background:linear-gradient(135deg,hsl(var(--hue1) 70% 38%),hsl(var(--hue1) 70% 25%));border-color:hsl(var(--hue1) 75% 60% / .85);color:hsl(var(--hue1) 95% 95%);box-shadow:0 0 10px hsl(var(--hue1) 75% 55% / .4),inset 0 1px 0 hsl(var(--hue1) 80% 70% / .3);text-shadow:0 0 6px hsl(var(--hue1) 90% 65% / .5)}
.lb-feedback{display:flex;gap:4px;align-items:center;padding-left:22px;flex-wrap:wrap}
.lb-fb-label{font-size:8px;color:var(--text-muted);font-weight:700;margin-right:5px;letter-spacing:.3px}
.lb-fb-btn{width:32px;height:32px;min-width:32px;border-radius:7px;border:1px solid rgba(255,255,255,.05);background:rgba(255,255,255,.025);font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:inherit;line-height:1;padding:0}
.lb-fb-btn:hover{background:rgba(255,255,255,.06);transform:scale(1.08)}
.lb-fb-btn.selected{background:hsl(var(--hue1) 50% 25% / .7);border-color:hsl(var(--hue1) 60% 50% / .55);box-shadow:0 0 6px hsl(var(--hue1) 60% 50% / .4)}

.see-more-mini{text-align:right;margin:4px 4px 0}
.see-more-mini a{font-size:9px;color:hsl(var(--hue1) 70% 75%);font-weight:800;text-decoration:none;cursor:pointer;text-shadow:0 0 5px hsl(var(--hue1) 70% 55% / .35);min-height:32px;display:inline-block;padding:6px 4px}

.lb-empty{padding:18px 14px;text-align:center;margin:8px 0}
.lb-empty > *{position:relative;z-index:5}
.lb-empty-icon{font-size:28px;margin-bottom:6px;filter:drop-shadow(0 0 12px hsl(140 70% 50% / .55))}
.lb-empty-text{font-size:11px;font-weight:800;color:hsl(140 75% 80%);margin-bottom:3px;text-shadow:0 0 8px hsl(140 70% 50% / .4)}
.lb-empty-sub{font-size:9px;color:var(--text-muted);font-weight:500;line-height:1.4}

/* ─── 4 BIG OPERATIONAL GLASS BUTTONS ─── */
.ops-section{padding:14px 0 8px}
.ops-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin-bottom:11px}
.op-btn{aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;cursor:pointer;font-family:inherit;text-decoration:none;color:inherit;background:transparent;padding:0;min-height:64px}
.op-btn > *{position:relative;z-index:5}
.op-icon{width:30px;height:30px;display:flex;align-items:center;justify-content:center}
.op-icon svg{width:26px;height:26px;fill:none;stroke:hsl(var(--hue1) 85% 82%);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 0 6px hsl(var(--hue1) 85% 60% / .7))}
.op-label{font-size:10px;font-weight:800;letter-spacing:.3px;text-align:center;color:hsl(var(--hue1) 92% 92%);text-shadow:0 0 6px hsl(var(--hue1) 85% 55% / .4)}

/* ─── AI Studio entry (qs magenta) ─── */
.studio-row-bottom{display:flex;justify-content:center;margin-top:11px}
.studio-btn{--hue1:310;--hue2:290;padding:10px 22px;display:inline-flex;align-items:center;gap:9px;cursor:pointer;text-decoration:none;color:inherit;font-family:inherit;position:relative;min-height:44px}
.studio-btn > *{position:relative;z-index:5}
.studio-icon{display:inline-flex}
.studio-icon svg{width:15px;height:15px;fill:hsl(310 90% 80%);stroke:hsl(310 90% 85%);stroke-width:1;filter:drop-shadow(0 0 8px hsl(310 90% 60% / .8))}
.studio-text{display:flex;flex-direction:column;align-items:flex-start;line-height:1.1}
.studio-label{font-size:11.5px;font-weight:900;letter-spacing:.3px;color:hsl(310 95% 92%);text-shadow:0 0 10px hsl(310 85% 60% / .55)}
.studio-sub{font-size:7.5px;color:hsl(310 65% 75%);font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-top:1px}
.studio-badge{position:absolute;top:-7px;right:-7px;min-width:20px;height:20px;padding:0 6px;border-radius:10px;background:linear-gradient(135deg,hsl(0 90% 60%),hsl(355 90% 55%));color:#fff;font-size:9px;font-weight:900;display:flex;align-items:center;justify-content:center;box-shadow:0 0 12px hsl(0 90% 55% / .8),inset 0 1px 0 rgba(255,255,255,.3);border:2px solid var(--bg-main);z-index:10}

@keyframes cardin{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

/* Hide bottom nav globally for Лесен режим (uses partials/chat-input-bar.php only) */
.rms-bottom-nav{display:none !important}

/* ───────────────────────────────────────────── */
/* S87.ANIMATIONS v3 — portable CORE block       */
/* DESIGN_SYSTEM § O.3-O.10 + O.14 + O.20        */
/* ───────────────────────────────────────────── */
@keyframes s87v3_pageIn{
    0%   { opacity:0; transform:translateY(40px) scale(0.92); filter:blur(8px); }
    60%  { opacity:1; filter:blur(0); }
    100% { opacity:1; transform:translateY(0) scale(1); filter:blur(0); }
}
.s87v3-pagein{animation:s87v3_pageIn 0.85s cubic-bezier(0.16,1,0.3,1) both}
@keyframes s87v3_cardin{
    0%   { opacity:0; transform:translateY(30px) scale(0.85); }
    70%  { transform:translateY(-4px) scale(1.02); }
    100% { opacity:1; transform:translateY(0) scale(1); }
}
.s87v3-stagger > *{opacity:0;animation:s87v3_cardin 0.95s cubic-bezier(0.34,1.8,0.64,1) both}
.s87v3-stagger > *:nth-child(1){animation-delay:0.30s}
.s87v3-stagger > *:nth-child(2){animation-delay:0.55s}
.s87v3-stagger > *:nth-child(3){animation-delay:0.80s}
.s87v3-stagger > *:nth-child(4){animation-delay:1.05s}
.s87v3-stagger > *:nth-child(5){animation-delay:1.30s}
.s87v3-stagger > *:nth-child(6){animation-delay:1.55s}
.s87v3-stagger > *:nth-child(7){animation-delay:1.80s}
.s87v3-stagger > *:nth-child(8){animation-delay:2.05s}
.s87v3-stagger > *:nth-child(n+9){animation-delay:2.30s}
@keyframes s87v3_springRelease{
    0%   { transform:scale(0.92); }
    50%  { transform:scale(1.06); }
    100% { transform:scale(1); }
}
.s87v3-tap{transition:transform 0.18s cubic-bezier(0.34,1.8,0.64,1)}
.s87v3-tap:active{transform:scale(0.92)}
.s87v3-tap.s87v3-released{animation:s87v3_springRelease 0.4s cubic-bezier(0.34,2.0,0.64,1)}
@keyframes s87v3_headerIn{
    0%   { opacity:0; transform:translateY(-30px); }
    100% { opacity:1; transform:translateY(0); }
}
.rms-header,.header{animation:s87v3_headerIn 0.7s cubic-bezier(0.16,1,0.3,1) 0s both;transition:backdrop-filter 0.3s,background 0.3s}
@keyframes s87v3_scrollIn{
    from { opacity:0; transform:translateY(40px) scale(0.95); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}
.s87v3-scroll-reveal{opacity:0}
.rms-header.scrolled,.header.scrolled{
    backdrop-filter:blur(20px) saturate(1.2);
    -webkit-backdrop-filter:blur(20px) saturate(1.2);
    background:linear-gradient(180deg,hsl(220 25% 6% / .95),hsl(220 25% 4% / .85));
}
@media (prefers-reduced-motion: reduce){
    .s87v3-pagein,.s87v3-stagger > *,
    .rms-header,.header,
    .s87v3-tap,.s87v3-tap.s87v3-released,
    .s87v3-scroll-reveal{
        opacity:1 !important;
        transform:none !important;
        filter:none !important;
        animation:none !important;
        transition:none !important;
    }
}
</style>
</head>
<body>

<?php include __DIR__ . '/partials/header.php'; ?>

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
        <div class="cell-label">Днес · <?= htmlspecialchars($store_name) ?></div>
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
        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
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
