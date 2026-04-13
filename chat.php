<?php
/**
 * chat.php — RunMyStore.ai Dashboard + AI Chat v7.0
 * S56 — FULL REWRITE per CHAT_PHP_SPEC_v7.md
 *
 * Затворен: Revenue карта + AI ТОЧНОСТ + AI Брифинг bubble + input бар
 * Отворен:  72% overlay, чист чат (WhatsApp стил), blur фон
 *
 * Закон №2: PHP смята, Gemini говори. Pills/Signals = чист PHP+SQL.
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)($_SESSION['store_id'] ?? 0);
$role      = $_SESSION['role'] ?? 'seller';
$user_name = $_SESSION['user_name'] ?? '';

// Store switch via GET
if (!empty($_GET['store'])) {
    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
        [(int)$_GET['store'], $tenant_id])->fetch();
    if ($chk) { $_SESSION['store_id'] = (int)$_GET['store']; }
    header('Location: chat.php'); exit;
}

// Fallback: pick first store
if (!$store_id) {
    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
    if ($first) { $store_id = (int)$first['id']; $_SESSION['store_id'] = $store_id; }
}

// ══════════════════════════════════════════════
// TENANT, PLAN, STORE
// ══════════════════════════════════════════════
$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
$cs = htmlspecialchars($tenant['currency'] ?? '€');
$plan = effectivePlan($tenant);

$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
$store_name = $store['name'] ?? 'Магазин';
$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);

// S56: silent geolocation from IP
autoGeolocateStore($store_id);

// WEATHER FORECAST
$weather_today = null;
$weather_week = [];
$weather_suggestion = '';
try {
    $weather_today = DB::run(
        'SELECT temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date=CURDATE() LIMIT 1',
        [$store_id])->fetch(PDO::FETCH_ASSOC);
    $weather_week = DB::run(
        'SELECT forecast_date, temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 13 DAY) ORDER BY forecast_date',
        [$store_id])->fetchAll(PDO::FETCH_ASSOC);
    if ($weather_today) {
        $tmax = (float)$weather_today['temp_max'];
        $rain = (int)$weather_today['precipitation_prob'];
        $btype = mb_strtolower($tenant['business_type'] ?? '');
        $fashion_kw = ['дрех','рокл','блуз','обувк','панталон','яке','палт','бельо','чорап','спорт','мод','fashion','cloth','shoe','sport','wear','бански','шал','ръкавиц','чант','аксесоар','бижут'];
        $is_fashion = false;
        foreach ($fashion_kw as $kw) {
            if (mb_strpos($btype, $kw) !== false) { $is_fashion = true; break; }
        }
        // Витрина + сезонност (за мода/дрехи/обувки)
        if ($is_fashion) {
            if ($tmax > 30) $weather_suggestion = 'Витрина: летни артикули отпред — рокли, сандали, шапки. Пуснати ли са зимните на намаление?';
            elseif ($tmax > 25) $weather_suggestion = 'Витрина: леки рокли и сандали. Ако имаш пролетни остатъци — време за намаление';
            elseif ($tmax > 20) $weather_suggestion = 'Витрина: тениски, къси панталони. Преходен период — миксирай сезони на витрината';
            elseif ($tmax > 15) $weather_suggestion = 'Витрина: леки якета, дънки. Зимните трябва да са на намаление или прибрани';
            elseif ($tmax > 10) $weather_suggestion = 'Витрина: якета и преходни обувки. Летните на разпродажба ако са останали';
            elseif ($tmax > 5) $weather_suggestion = 'Витрина: палта, пуловери, зимни обувки. Есенните артикули — намали или прибери';
            else $weather_suggestion = 'Витрина: пуховки, ботуши, шалове. Пълна зима — сезонните артикули отпред';
            if ($rain > 60) $weather_suggestion .= '. Дъжд — сложи чадъри или дъждобрани на витрината';
        } else {
            // Универсално за всеки тип магазин — само трафик
            if ($rain > 75) $weather_suggestion = 'Силен дъжд — очаквай 25-35% по-малко хора, но по-голяма кошница';
            elseif ($rain > 50) $weather_suggestion = 'Вероятен дъжд — възможно 15-25% по-малко хора';
            elseif ($tmax > 33) $weather_suggestion = 'Много горещо — хората избягват разходки, по-слаб трафик';
            elseif ($tmax > 25) $weather_suggestion = 'Хубаво време — добър ден за разходки и пазаруване';
            elseif ($tmax > 15) $weather_suggestion = 'Приятно време — нормален трафик';
            elseif ($tmax > 5) $weather_suggestion = 'Хладно — хората пазаруват по-целенасочено';
            else $weather_suggestion = 'Студено — по-малко разходки, но сериозни купувачи';
            if ($rain > 60) $weather_suggestion .= '. Дъждовен ден — обмисли промоция за онлайн или по-атрактивна витрина';
        }
        if (count($weather_week) >= 7) {
            $t0 = (float)$weather_week[0]['temp_max'];
            $t7 = (float)$weather_week[6]['temp_max'];
            $diff = round($t7 - $t0);
            if ($diff >= 5) $weather_suggestion .= '. Затопляне идва (+' . $diff . '\xC2\xB0C за 7 дни) — сезонна смяна наближава';
            elseif ($diff <= -5) $weather_suggestion .= '. Застудяване идва (' . $diff . '\xC2\xB0C за 7 дни) — сезонна смяна наближава';
        }
        $rainy_days = 0;
        foreach (array_slice($weather_week, 0, 7) as $d) {
            if ((int)$d['precipitation_prob'] > 50) $rainy_days++;
        }
        if ($rainy_days >= 4) $weather_suggestion .= '. ' . $rainy_days . ' дъждовни дни от 7 — планирай по-слаба седмица';
    }
} catch (Exception $e) {}

function wmoSvg($code) {
    if ($code <= 3) return '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
    if ($code <= 48) return '<path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z"/>';
    return '<path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z"/><line x1="8" y1="24" x2="10" y2="18"/><line x1="12" y1="24" x2="14" y2="18"/><line x1="16" y1="24" x2="18" y2="18"/>';
}

function wmoText($code) {
    if ($code <= 3) return 'Ясно';
    if ($code <= 48) return 'Облачно';
    if ($code <= 57) return 'Ръми';
    if ($code <= 67) return 'Дъжд';
    if ($code <= 77) return 'Сняг';
    if ($code <= 82) return 'Порой';
    return 'Буря';
}

// Plan badge colors
$plan_colors = match($plan) {
    'pro'   => ['bg' => 'rgba(192,132,252,.15)', 'br' => 'rgba(192,132,252,.3)', 'tx' => '#c084fc'],
    'start' => ['bg' => 'rgba(99,102,241,.15)',  'br' => 'rgba(99,102,241,.3)',  'tx' => '#818cf8'],
    default => ['bg' => 'rgba(107,114,128,.15)', 'br' => 'rgba(107,114,128,.3)', 'tx' => '#9ca3af'],
};
$plan_label = strtoupper($plan);

// ══════════════════════════════════════════════
// REVENUE — ALL PERIODS
// ══════════════════════════════════════════════
function periodData($tid, $sid, $r, $from, $to = null) {
    $to = $to ?? date('Y-m-d');
    $rev = (float)DB::run(
        'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
        [$tid, $sid, $from, $to])->fetchColumn();
    $cnt = (int)DB::run(
        'SELECT COUNT(*) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
        [$tid, $sid, $from, $to])->fetchColumn();
    $profit = 0;
    if ($r === 'owner' && $cnt > 0) {
        $profit = (float)DB::run(
            'SELECT COALESCE(SUM(si.quantity*(si.unit_price - COALESCE(si.cost_price,0))),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)>=? AND DATE(s.created_at)<=? AND s.status!="canceled"',
            [$tid, $sid, $from, $to])->fetchColumn();
    }
    return ['rev' => $rev, 'profit' => $profit, 'cnt' => $cnt];
}
function cmpPct($a, $b) { return $b > 0 ? round(($a - $b) / $b * 100) : ($a > 0 ? 100 : 0); }
function mgn($p) { return $p['rev'] > 0 ? round($p['profit'] / $p['rev'] * 100) : 0; }

$today = date('Y-m-d');
$d0  = periodData($tenant_id, $store_id, $role, $today, $today);
$d0p = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')));
$d7  = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-6 days')), $today);
$d7p = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-13 days')), date('Y-m-d', strtotime('-7 days')));
$d30  = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-29 days')), $today);
$d30p = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-59 days')), date('Y-m-d', strtotime('-30 days')));
$d365  = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-364 days')), $today);
$d365p = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-729 days')), date('Y-m-d', strtotime('-365 days')));

$periods_json = json_encode([
    'today' => [
        'rev' => round($d0['rev']), 'profit' => round($d0['profit']), 'cnt' => $d0['cnt'], 'margin' => mgn($d0),
        'cmp_rev' => cmpPct($d0['rev'], $d0p['rev']), 'cmp_prof' => cmpPct($d0['profit'], $d0p['profit']),
        'label' => 'Спрямо вчера',
        'sub_rev' => fmtMoney(round($d0p['rev']), '') . ' → ' . fmtMoney(round($d0['rev']), ''),
        'sub_prof' => fmtMoney(round($d0p['profit']), '') . ' → ' . fmtMoney(round($d0['profit']), ''),
    ],
    '7d' => [
        'rev' => round($d7['rev']), 'profit' => round($d7['profit']), 'cnt' => $d7['cnt'], 'margin' => mgn($d7),
        'cmp_rev' => cmpPct($d7['rev'], $d7p['rev']), 'cmp_prof' => cmpPct($d7['profit'], $d7p['profit']),
        'label' => 'Спрямо предишните 7 дни',
        'sub_rev' => fmtMoney(round($d7p['rev']), '') . ' → ' . fmtMoney(round($d7['rev']), ''),
        'sub_prof' => fmtMoney(round($d7p['profit']), '') . ' → ' . fmtMoney(round($d7['profit']), ''),
    ],
    '30d' => [
        'rev' => round($d30['rev']), 'profit' => round($d30['profit']), 'cnt' => $d30['cnt'], 'margin' => mgn($d30),
        'cmp_rev' => cmpPct($d30['rev'], $d30p['rev']), 'cmp_prof' => cmpPct($d30['profit'], $d30p['profit']),
        'label' => 'Спрямо предишните 30 дни',
        'sub_rev' => fmtMoney(round($d30p['rev']), '') . ' → ' . fmtMoney(round($d30['rev']), ''),
        'sub_prof' => fmtMoney(round($d30p['profit']), '') . ' → ' . fmtMoney(round($d30['profit']), ''),
    ],
    '365d' => [
        'rev' => round($d365['rev']), 'profit' => round($d365['profit']), 'cnt' => $d365['cnt'], 'margin' => mgn($d365),
        'cmp_rev' => cmpPct($d365['rev'], $d365p['rev']), 'cmp_prof' => cmpPct($d365['profit'], $d365p['profit']),
        'label' => 'Спрямо предишните 365 дни',
        'sub_rev' => fmtMoney(round($d365p['rev']), '') . ' → ' . fmtMoney(round($d365['rev']), ''),
        'sub_prof' => fmtMoney(round($d365p['profit']), '') . ' → ' . fmtMoney(round($d365['profit']), ''),
    ],
], JSON_UNESCAPED_UNICODE);

// ══════════════════════════════════════════════
// CONFIDENCE (for revenue card warning)
// ══════════════════════════════════════════════
$total_products = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1', [$tenant_id])->fetchColumn();
$with_cost = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0', [$tenant_id])->fetchColumn();
$confidence_pct = $total_products > 0 ? round($with_cost / $total_products * 100) : 0;

// ══════════════════════════════════════════════
// STORE HEALTH (AI ТОЧНОСТ bar)
// ══════════════════════════════════════════════
$health = 0;
try { $health = storeHealth($tenant_id, $store_id); } catch (Exception $e) {}

// ══════════════════════════════════════════════
// AI INSIGHTS (Briefing bubble)
// ══════════════════════════════════════════════
$insights = [];
$ghost_pills = [];
try {
    if (planAtLeast($plan, 'pro')) {
        $insights = getInsightsForModule($tenant_id, $store_id, $user_id, 'home', $plan, $role);
    } else {
        $ghost_pills = getGhostPills($tenant_id, $store_id, $user_id, $plan);
    }
} catch (Exception $e) {}

$briefing = array_slice($insights, 0, 3);
$remaining = max(0, count($insights) - 3);

// Generate action button from insight — DB columns first, fallback to topic_id
function insightAction(array $ins): array {
    // DB action columns (from compute-insights.php, populated by next session)
    if (!empty($ins['action_label'])) {
        return [
            'label' => $ins['action_label'],
            'type'  => $ins['action_type'] ?? 'chat',
            'url'   => $ins['action_url'] ?? null,
            'data'  => $ins['action_data'] ? json_decode($ins['action_data'], true) : null,
        ];
    }
    // Fallback: derive from topic_id (works with current 30 functions)
    $tid = $ins['topic_id'] ?? '';
    if (str_contains($tid, 'zero_stock') || str_contains($tid, 'low_stock'))
        return ['label' => 'Добави за поръчка', 'type' => 'order_draft', 'url' => null, 'data' => null];
    if (str_contains($tid, 'below_cost'))
        return ['label' => 'Коригирай цена', 'type' => 'deeplink', 'url' => 'products.php?filter=below_cost', 'data' => null];
    if (str_contains($tid, 'zombie'))
        return ['label' => 'Виж zombie стока', 'type' => 'deeplink', 'url' => 'products.php?filter=zombie', 'data' => null];
    if (str_contains($tid, 'no_photo'))
        return ['label' => 'Снимай сега', 'type' => 'deeplink', 'url' => 'products.php?filter=no_photo', 'data' => null];
    if (str_contains($tid, 'top_profit'))
        return ['label' => 'Виж артикулите', 'type' => 'deeplink', 'url' => 'products.php?filter=top_profit', 'data' => null];
    return ['label' => 'Разкажи повече', 'type' => 'chat', 'url' => null, 'data' => null];
}

// Map module to UI category for Signal Browser
function insightUICategory(array $ins): string {
    $m = $ins['module'] ?? 'home';
    $cat = $ins['category'] ?? '';
    if (in_array($cat, ['profit','price','price_change','cash','tax'])) return 'finance';
    if (in_array($cat, ['wh','xfer','data_quality'])) return 'warehouse';
    if (in_array($cat, ['promo','fashion','shoes','acc','lingerie','sport','size','new'])) return 'products';
    if ($cat === 'expense') return 'expenses';
    if ($m === 'warehouse') return 'warehouse';
    if ($m === 'products') return 'products';
    if ($m === 'stats') return 'finance';
    return 'sales';
}

$ui_categories = [
    'sales'     => ['label' => 'Продажби',  'color' => '#ef4444', 'items' => []],
    'warehouse' => ['label' => 'Склад',     'color' => '#818cf8', 'items' => []],
    'products'  => ['label' => 'Продукти',  'color' => '#c084fc', 'items' => []],
    'finance'   => ['label' => 'Финанси',   'color' => '#fbbf24', 'items' => []],
    'expenses'  => ['label' => 'Разходи',   'color' => '#6b7280', 'items' => []],
];

// Build JSON for all insights (Signal Detail + Browser)
$all_insights_for_js = [];
foreach ($insights as $idx => $ins) {
    $action = insightAction($ins);
    $uiCat = insightUICategory($ins);
    $ui_categories[$uiCat]['items'][] = $idx;
    $all_insights_for_js[] = [
        'title'      => $ins['title'],
        'detail'     => $ins['detail_text'] ?? '',
        'urgency'    => $ins['urgency'],
        'category'   => $ins['category'] ?? '',
        'uiCat'      => $uiCat,
        'value'      => (float)($ins['value_numeric'] ?? 0),
        'count'      => (int)($ins['product_count'] ?? 0),
        'data'       => $ins['data_json'] ? json_decode($ins['data_json'], true) : null,
        'action'     => $action,
        'topicId'    => $ins['topic_id'] ?? '',
    ];
}
$all_insights_json = json_encode($all_insights_for_js, JSON_UNESCAPED_UNICODE);
$ui_categories_json = json_encode($ui_categories, JSON_UNESCAPED_UNICODE);

// Urgency colors
function urgencyClass(string $u): string {
    return match($u) { 'critical' => 'sig-critical', 'warning' => 'sig-warning', default => 'sig-info' };
}

// ══════════════════════════════════════════════
// GREETING
// ══════════════════════════════════════════════
$hour = (int)date('H');
$greeting = match(true) {
    $hour >= 5 && $hour < 12 => 'Добро утро',
    $hour >= 12 && $hour < 18 => 'Добър ден',
    default => 'Добър вечер'
};
if ($user_name) $greeting .= ', ' . htmlspecialchars($user_name);
$greeting .= '!';

// ══════════════════════════════════════════════
// CHAT HISTORY
// ══════════════════════════════════════════════
$chat_messages = DB::run(
    'SELECT role, content, created_at FROM chat_messages WHERE tenant_id=? AND store_id=? ORDER BY created_at ASC LIMIT 50',
    [$tenant_id, $store_id])->fetchAll(PDO::FETCH_ASSOC);

?><!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="theme-color" content="#030712">
<title>RunMyStore.ai</title>
<style>
:root{--bg:#030712;--nav:52px}
*,*::before,*::after{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}
html{background:var(--bg)}
body{background:var(--bg);color:#f1f5f9;font-family:'Montserrat',Inter,system-ui,sans-serif;
    height:100dvh;display:flex;flex-direction:column;overflow:hidden;padding-bottom:var(--nav)}
body::before{content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);
    width:600px;height:350px;background:radial-gradient(ellipse,rgba(99,102,241,.07)0%,transparent 70%);
    pointer-events:none;z-index:0}

/* ── HEADER ── */
.header{position:sticky;top:0;z-index:50;padding:10px 14px 8px;
    background:rgba(3,7,18,.96);border-bottom:1px solid rgba(99,102,241,.07)}
.header-top{display:flex;align-items:center;justify-content:space-between}
.header-brand{font-size:10px;font-weight:700;color:rgba(165,180,252,.5);letter-spacing:.6px}
.header-right{display:flex;align-items:center;gap:6px}
.plan-badge{padding:3px 8px;border-radius:10px;font-size:7px;font-weight:700;letter-spacing:.3px;
    background:<?= $plan_colors['bg'] ?>;border:1px solid <?= $plan_colors['br'] ?>;color:<?= $plan_colors['tx'] ?>}
.simple-toggle{font-size:7px;color:rgba(165,180,252,.35);border:1px solid rgba(99,102,241,.08);
    border-radius:10px;padding:2px 7px;cursor:pointer;text-decoration:none}
.header-icon{width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,.03);
    display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative}
.header-icon svg{width:12px;height:12px;stroke:rgba(165,180,252,.35);fill:none;stroke-width:1.8}
.logout-dropdown{position:absolute;top:28px;right:0;background:#0f0f2a;
    border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:8px 14px;white-space:nowrap;
    z-index:60;box-shadow:0 8px 24px rgba(0,0,0,.5);font-size:11px;color:#fca5a5;
    font-weight:600;cursor:pointer;display:none;text-decoration:none}
.logout-dropdown.show{display:block}

/* ── MAIN SCROLL ── */
.main-scroll{flex:1;overflow-y:auto;overflow-x:hidden;position:relative;z-index:1;
    -webkit-overflow-scrolling:touch;scrollbar-width:none;transition:opacity .3s}
.main-scroll::-webkit-scrollbar{display:none}

/* ── REVENUE CARD ── */
.revenue-card{background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);
    border-radius:14px;padding:12px 14px 10px;margin:10px 12px 0}
.revenue-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2px}
.revenue-period-label{font-size:7px;font-weight:700;color:rgba(255,255,255,.2);
    text-transform:uppercase;letter-spacing:.5px}
.revenue-store-name{font-size:8px;color:rgba(165,180,252,.3)}
.revenue-store-name select{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);
    border-radius:8px;color:#a5b4fc;font-size:8px;font-weight:600;padding:2px 6px;
    font-family:inherit;cursor:pointer;outline:none}
.revenue-number-row{display:flex;align-items:baseline;gap:6px}
.revenue-number{font-size:28px;font-weight:800;color:#a5b4fc;letter-spacing:-1px}
.revenue-currency{font-size:11px;color:#4b5563;font-weight:600}
.revenue-change{font-size:16px;font-weight:800}
.revenue-change.up{color:#4ade80}
.revenue-change.down{color:#f87171}
.revenue-change.zero{color:#4b5563}
.revenue-comparison{font-size:8px;color:#4b5563;margin:1px 0 0}
.revenue-meta{font-size:9px;color:#4b5563;margin-top:3px}
.revenue-pills{display:flex;gap:0;margin-top:9px;align-items:center;flex-wrap:wrap;row-gap:6px}
.revenue-pill-group{display:flex;gap:3px}
.revenue-pill{font-size:8px;padding:4px 9px;border-radius:10px;cursor:pointer;white-space:nowrap;
    border:1px solid transparent;transition:all .2s}
.revenue-pill.active{color:#e2e8f0;background:rgba(99,102,241,.1);border-color:rgba(99,102,241,.18)}
.revenue-pill.inactive{color:#4b5563}
.revenue-divider{width:1px;height:16px;background:rgba(255,255,255,.06);margin:0 6px}
.confidence-warning{display:flex;align-items:center;gap:6px;margin:6px 0 0;padding:5px 8px;
    border-radius:8px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.15);
    font-size:9px;color:#fcd34d}
.confidence-warning svg{width:12px;height:12px;stroke:#fbbf24;fill:none;stroke-width:2.5;flex-shrink:0}

/* ── STORE HEALTH BAR ── */
.health-bar{margin:8px 12px 4px;display:flex;align-items:center;gap:8px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);border-radius:14px;padding:10px 14px}
.health-label{font-size:7px;font-weight:700;color:rgba(255,255,255,.18);letter-spacing:.5px;
    text-transform:uppercase;white-space:nowrap}
.health-track{flex:1;height:5px;border-radius:3px;background:rgba(255,255,255,.04);overflow:hidden}
.health-fill{height:100%;border-radius:3px;
    background:linear-gradient(90deg,#ef4444 0%,#f97316 25%,#eab308 50%,#84cc16 75%,#22c55e 100%)}
.health-percent{font-size:9px;font-weight:700}
.health-link{font-size:7px;color:#818cf8;white-space:nowrap;cursor:pointer}

/* ── HEALTH INFO ── */
.health-info-btn{width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;transition:all .2s}
.health-info-btn:active{transform:scale(.9)}
.health-info-btn svg{width:12px;height:12px;stroke:#a5b4fc;fill:none;stroke-width:2}
.health-tooltip{display:none;margin:6px 12px 0;padding:12px 14px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);border-radius:12px;font-size:11px;color:#d1d5db;line-height:1.5}
.health-tooltip.open{display:block;animation:cardin .3s ease both}
.health-tooltip b{color:#a5b4fc;font-weight:600}

/* ── SEPARATOR ── */
.separator{height:1px;margin:6px 12px;background:rgba(255,255,255,.04)}

/* ── AI META ── */
.ai-meta{display:flex;align-items:center;gap:4px;margin:10px 12px 5px}
.ai-meta svg{width:13px;height:13px;fill:none;stroke:#818cf8;stroke-width:1.5}
.ai-meta-label{font-size:10px;color:#818cf8;font-weight:600}
.ai-meta-time{font-size:9px;color:#4b5563}

/* ── AI BUBBLE ── */
.ai-bubble{max-width:92%;margin:0 12px;background:rgba(99,102,241,.12);
    border:1px solid rgba(99,102,241,.2);border-radius:14px 14px 14px 3px;padding:10px 12px}
.ai-bubble-text{font-size:11px;color:#d1d5db;line-height:1.4}
.ai-bubble-text.with-signals{margin-bottom:7px}

/* ── SIGNAL CARDS (inside bubble) — bubble style ── */
.signal-card{padding:9px 11px;margin:5px 0;border-radius:14px;cursor:pointer;
    display:flex;align-items:flex-start;gap:8px;transition:all .15s}
.signal-card:active{transform:scale(.98)}
.sig-critical{background:rgba(239,68,68,.03);border:.5px solid rgba(239,68,68,.15);border-left:4px solid #ef4444}
.sig-warning{background:rgba(251,191,36,.02);border:.5px solid rgba(251,191,36,.12);border-left:4px solid #fbbf24}
.sig-info{background:rgba(34,197,94,.02);border:.5px solid rgba(34,197,94,.12);border-left:4px solid #4ade80}
.signal-stripe{display:none}
.sig-critical-stripe{background:#ef4444}
.sig-warning-stripe{background:#fbbf24}
.sig-info-stripe{background:#4ade80}
.signal-content{flex:1;min-width:0}
.signal-title{font-size:11px;font-weight:600;line-height:1.3}
.sig-critical .signal-title{color:#fca5a5}
.sig-warning .signal-title{color:#fcd34d}
.sig-info .signal-title{color:#86efac}
.signal-body{font-size:10px;color:#6b7280;line-height:1.3;margin-top:1px}
.signal-arrow{color:rgba(255,255,255,.12);font-size:16px;flex-shrink:0;align-self:center}

/* ── SIGNAL DETAIL OVERLAY ── */
.signal-detail-bg{position:fixed;inset:0;background:rgba(3,7,18,.92);backdrop-filter:blur(10px);
    z-index:300;opacity:0;pointer-events:none;transition:opacity .25s}
.signal-detail-bg.open{opacity:1;pointer-events:auto}
.signal-detail-panel{position:fixed;bottom:-100%;left:0;right:0;height:85vh;
    background:rgba(5,8,20,.98);border-radius:20px 20px 0 0;z-index:310;
    display:flex;flex-direction:column;transition:bottom .3s cubic-bezier(.32,0,.67,0)}
.signal-detail-panel.open{bottom:0}
.signal-detail-header{display:flex;align-items:center;justify-content:space-between;
    padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.05);flex-shrink:0}
.signal-detail-header-left{display:flex;align-items:center;gap:8px;flex:1;min-width:0}
.signal-detail-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.signal-detail-dot-critical{background:#ef4444;box-shadow:0 0 8px rgba(239,68,68,.5)}
.signal-detail-dot-warning{background:#fbbf24;box-shadow:0 0 8px rgba(251,191,36,.5)}
.signal-detail-dot-info{background:#4ade80;box-shadow:0 0 8px rgba(34,197,94,.5)}
.signal-detail-title{font-size:13px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.signal-detail-body{flex:1;overflow-y:auto;padding:0 14px 14px;
    -webkit-overflow-scrolling:touch;scrollbar-width:none}
.signal-detail-body::-webkit-scrollbar{display:none}
.detail-hero{text-align:center;padding:16px 0 12px}
.detail-hero-num{font-size:34px;font-weight:800;letter-spacing:-1px}
.detail-hero-unit{font-size:10px;color:#6b7280;margin-top:2px}
.detail-why{background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.04);
    border-radius:12px;padding:10px 12px;margin-bottom:10px}
.detail-label{font-size:7px;font-weight:700;color:rgba(255,255,255,.15);
    text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.detail-text{font-size:11px;color:#d1d5db;line-height:1.5}
.detail-suggestion{background:rgba(99,102,241,.04);border:1px solid rgba(99,102,241,.1);
    border-radius:12px;padding:10px 12px;margin-bottom:10px}
.detail-suggestion-text{font-size:11px;color:#a5b4fc;line-height:1.5;margin-bottom:10px}
.detail-actions{display:flex;gap:6px}
.detail-action-primary{flex:1;padding:11px 8px;border-radius:10px;font-size:11px;font-weight:600;
    text-align:center;cursor:pointer;border:none;font-family:inherit;
    background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff}
.detail-action-primary:active{transform:scale(.98)}
.detail-action-secondary{flex:1;padding:11px 8px;border-radius:10px;font-size:11px;font-weight:600;
    text-align:center;cursor:pointer;border:1px solid rgba(255,255,255,.06);font-family:inherit;
    background:rgba(255,255,255,.03);color:#a5b4fc}
.detail-action-hint{font-size:8px;color:#4b5563;text-align:center;margin-top:5px}
.detail-section{font-size:7px;font-weight:700;color:rgba(255,255,255,.15);
    text-transform:uppercase;letter-spacing:.5px;margin:14px 0 6px}
.detail-row{display:flex;justify-content:space-between;align-items:center;padding:7px 10px;
    background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.04);
    border-radius:8px;margin-bottom:3px}
.detail-row-name{font-size:10px;color:#d1d5db}
.detail-row-value{font-size:10px;font-weight:700}

/* ── SIGNAL BROWSER OVERLAY ── */
.signal-browser-bg{position:fixed;inset:0;background:rgba(3,7,18,.92);backdrop-filter:blur(10px);
    z-index:300;opacity:0;pointer-events:none;transition:opacity .25s}
.signal-browser-bg.open{opacity:1;pointer-events:auto}
.signal-browser-panel{position:fixed;bottom:-100%;left:0;right:0;height:90vh;
    background:rgba(5,8,20,.98);border-radius:20px 20px 0 0;z-index:310;
    display:flex;flex-direction:column;transition:bottom .3s cubic-bezier(.32,0,.67,0)}
.signal-browser-panel.open{bottom:0}
.browser-body{flex:1;overflow-y:auto;padding:8px 12px;background:rgba(99,102,241,.06);
    -webkit-overflow-scrolling:touch;scrollbar-width:none}
.browser-body::-webkit-scrollbar{display:none}
.browser-cat-wrap{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.18);border-radius:14px;padding:8px;margin-bottom:10px}
.browser-cat-header{display:flex;align-items:center;gap:6px;margin:0 4px 6px;padding:0}
.browser-cat-dot{width:8px;height:8px;border-radius:50%}
.browser-cat-name{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.browser-cat-count{font-size:8px;color:#4b5563;font-weight:600}
.browser-signal{padding:9px 11px;margin:4px 0;border-radius:10px;cursor:pointer;border-left:4px solid transparent;background:transparent;border:1px solid rgba(255,255,255,.06);
    display:flex;align-items:flex-start;gap:8px;transition:all .15s}
.browser-signal:active{transform:scale(.98)}
.browser-future{padding:10px;font-size:9px;color:#4b5563;
    border:.5px dashed rgba(255,255,255,.08);border-radius:14px;text-align:center;margin:4px 0}

/* ── ACTION BUTTONS ── */
.action-buttons{display:flex;gap:5px;margin-top:8px;flex-wrap:wrap}
.action-button{padding:5px 11px;border-radius:8px;font-size:9px;font-weight:600;
    color:#a5b4fc;border:1px solid rgba(99,102,241,.15);background:transparent;
    cursor:pointer;font-family:inherit;transition:background .15s}
.action-button:active{background:rgba(99,102,241,.08)}
.action-button-more{color:#818cf8;border-color:rgba(99,102,241,.25);background:rgba(99,102,241,.1)}

/* ── GHOST PILL ── */
.ghost-pill{padding:5px 12px;border-radius:8px;font-size:10px;font-weight:600;
    color:rgba(168,85,247,.4);border:1px dashed rgba(168,85,247,.2);background:transparent;
    cursor:pointer;font-family:inherit;margin-top:6px}

/* ── USER BUBBLE (dashboard history) ── */
.user-row{display:flex;justify-content:flex-end;margin:10px 12px 4px;flex-direction:column;align-items:flex-end}
.user-time{font-size:8px;color:#4b5563;margin-bottom:3px}
.user-bubble{background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.1);
    border-radius:14px 14px 3px 14px;padding:8px 12px;font-size:11px;color:#e2e8f0;
    max-width:75%;line-height:1.4;word-break:break-word}

/* ── AI RESPONSE BUBBLE (dashboard history) ── */
.ai-response{max-width:88%;margin:0 12px;background:rgba(255,255,255,.025);
    border:1px solid rgba(255,255,255,.05);border-radius:14px 14px 14px 3px;
    padding:8px 12px;font-size:11px;color:#d1d5db;line-height:1.5;word-break:break-word;
    white-space:pre-wrap}

/* ── INPUT BAR (dashboard bottom) ── */
.input-bar{padding:8px 12px 6px;position:relative;z-index:5;cursor:pointer}
.input-bar-inner{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.025);
    border:1px solid rgba(255,255,255,.05);border-radius:16px;padding:7px 8px 7px 10px;
    transition:border-color .2s}
.input-bar-inner:active{border-color:rgba(99,102,241,.2)}
.input-waves{display:flex;align-items:flex-end;gap:2px;height:14px;flex-shrink:0}
.input-wave-bar{width:2px;border-radius:1px;animation:waveAnim .8s ease-in-out infinite alternate}
.input-wave-bar:nth-child(1){animation-delay:0s}
.input-wave-bar:nth-child(2){animation-delay:.15s}
.input-wave-bar:nth-child(3){animation-delay:.3s}
.input-wave-bar:nth-child(4){animation-delay:.45s}
.input-wave-bar:nth-child(5){animation-delay:.6s}
.input-placeholder{flex:1;font-size:11px;color:#374151}
.mic-button{width:32px;height:32px;border-radius:50%;
    background:linear-gradient(135deg,#4f46e5,#7c3aed);
    display:flex;align-items:center;justify-content:center;flex-shrink:0}
.mic-button svg{width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2}
.send-button{width:30px;height:30px;border-radius:50%;
    background:linear-gradient(135deg,#059669,#10b981);
    display:flex;align-items:center;justify-content:center;flex-shrink:0}
.send-button svg{width:13px;height:13px;fill:#fff}

/* ── BOTTOM NAV ── */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;height:var(--nav);
    background:rgba(3,7,18,.97);border-top:1px solid rgba(255,255,255,.04);
    display:flex;z-index:100}
.bottom-nav-tab{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:2px;font-size:8px;font-weight:600;text-decoration:none;transition:all .2s}
.bottom-nav-tab svg{width:18px;height:18px;stroke-width:1.5;fill:none}
.bottom-nav-tab.active{color:#a5b4fc}
.bottom-nav-tab.active svg{stroke:#a5b4fc}
.bottom-nav-tab.inactive{color:rgba(165,180,252,.45)}
.bottom-nav-tab.inactive svg{stroke:rgba(165,180,252,.45)}

/* ── CHAT OVERLAY ── */
.chat-overlay-bg{position:fixed;inset:0;background:rgba(3,7,18,.65);backdrop-filter:blur(8px);
    z-index:200;opacity:0;pointer-events:none;transition:opacity .25s}
.chat-overlay-bg.open{opacity:1;pointer-events:auto}
.chat-overlay-panel{position:fixed;bottom:-100%;left:0;right:0;height:72vh;
    background:rgba(8,10,24,.98);border-radius:20px 20px 0 0;z-index:210;
    display:flex;flex-direction:column;
    box-shadow:0 -8px 40px rgba(99,102,241,.12);transition:bottom .3s cubic-bezier(.32,0,.67,0)}
.chat-overlay-panel.open{bottom:0}
.chat-overlay-panel::before{content:'';position:absolute;inset:0;border-radius:20px 20px 0 0;
    background:radial-gradient(ellipse at 20% 10%,rgba(99,102,241,.04)0%,transparent 55%),
               radial-gradient(ellipse at 80% 90%,rgba(139,92,246,.03)0%,transparent 50%);
    pointer-events:none}

/* ── OVERLAY HEADER ── */
.overlay-header{display:flex;align-items:center;justify-content:space-between;
    padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.05);position:relative;z-index:1}
.overlay-header-left{display:flex;align-items:center;gap:8px}
.overlay-avatar{width:30px;height:30px;border-radius:50%;
    background:linear-gradient(135deg,#4f46e5,#7c3aed);
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 0 12px rgba(99,102,241,.3)}
.overlay-avatar svg{width:14px;height:14px;fill:#fff}
.overlay-title{font-size:13px;font-weight:600;color:#e2e8f0}
.overlay-close{width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.04);
    display:flex;align-items:center;justify-content:center;cursor:pointer;border:none}
.overlay-close svg{width:14px;height:14px;stroke:rgba(255,255,255,.35);fill:none;stroke-width:2}

/* ── OVERLAY CHAT MESSAGES ── */
.overlay-messages{flex:1;overflow-y:auto;padding:10px 12px;position:relative;z-index:1;
    display:flex;flex-direction:column;gap:8px;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.overlay-messages::-webkit-scrollbar{display:none}
.chat-message-group{display:flex;flex-direction:column;gap:4px}
.chat-meta-line{font-size:9px;color:#4b5563;display:flex;align-items:center;gap:4px}
.chat-meta-line.right{justify-content:flex-end}
.chat-ai-msg{max-width:85%;padding:8px 12px;font-size:12px;line-height:1.5;word-break:break-word;
    background:rgba(15,20,40,.8);border:.5px solid rgba(99,102,241,.15);color:#e2e8f0;
    border-radius:4px 14px 14px 14px;white-space:pre-wrap}
.chat-user-msg{max-width:75%;padding:8px 12px;font-size:12px;line-height:1.5;word-break:break-word;
    background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.15);color:#e2e8f0;
    border-radius:14px 14px 4px 14px;margin-left:auto}
.chat-typing-indicator{display:none;padding:8px 12px;background:rgba(15,20,40,.8);
    border:.5px solid rgba(99,102,241,.15);border-radius:4px 14px 14px 14px;width:fit-content}
.typing-dots{display:flex;gap:4px;align-items:center}
.typing-dot{width:5px;height:5px;border-radius:50%;background:#818cf8;animation:dotbounce 1.2s infinite}
.typing-dot:nth-child(2){animation-delay:.2s}
.typing-dot:nth-child(3){animation-delay:.4s}

/* ── OVERLAY REC BAR ── */
.recording-bar{display:none;align-items:center;gap:8px;padding:6px 12px;
    background:rgba(239,68,68,.06);border-top:1px solid rgba(239,68,68,.15);position:relative;z-index:1}
.recording-bar.on{display:flex}
.recording-dot{width:8px;height:8px;border-radius:50%;background:#ef4444;
    animation:recpulse 1s infinite;box-shadow:0 0 8px rgba(239,68,68,.6)}
.recording-label{font-size:9px;font-weight:700;color:#fca5a5;text-transform:uppercase;letter-spacing:.5px}
.recording-transcript{font-size:10px;color:#e2e8f0;flex:1}

/* ── OVERLAY INPUT ── */
.overlay-input{padding:6px 10px 8px;position:relative;z-index:1}
.overlay-input-inner{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.025);
    border:1px solid rgba(255,255,255,.05);border-radius:16px;padding:6px 8px 6px 12px}
.overlay-textarea{flex:1;background:transparent;border:none;color:#f1f5f9;font-size:12px;
    padding:6px 0;font-family:inherit;outline:none;resize:none;max-height:80px;line-height:1.4}
.overlay-textarea::placeholder{color:#374151}
.overlay-mic{width:34px;height:34px;border-radius:50%;flex-shrink:0;position:relative;
    display:flex;align-items:center;justify-content:center;cursor:pointer;
    background:linear-gradient(135deg,#4f46e5,#7c3aed);
    box-shadow:0 0 12px rgba(99,102,241,.3);transition:all .2s}
.overlay-mic.recording{background:linear-gradient(135deg,#ef4444,#dc2626);
    box-shadow:0 0 18px rgba(239,68,68,.5)}
.overlay-mic svg{width:16px;height:16px;color:#fff;z-index:1;stroke:#fff;fill:none;stroke-width:2}
.voice-ring{position:absolute;border-radius:50%;border:1.5px solid rgba(255,255,255,.3);opacity:0}
.overlay-mic.recording .voice-ring{border-color:rgba(255,255,255,.5)}
.vr1{width:20px;height:20px;animation:vrpulse 2s 0s ease-in-out infinite}
.vr2{width:32px;height:32px;animation:vrpulse 2s .3s ease-in-out infinite}
.vr3{width:44px;height:44px;animation:vrpulse 2s .6s ease-in-out infinite}
.overlay-send{width:34px;height:34px;border-radius:50%;
    background:linear-gradient(135deg,#059669,#10b981);
    border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;
    flex-shrink:0;transition:opacity .2s}
.overlay-send:disabled{opacity:.2}
.overlay-send svg{width:14px;height:14px;fill:#fff}

/* ── TOAST ── */
.toast{position:fixed;bottom:60px;left:50%;transform:translateX(-50%);
    background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;
    padding:7px 16px;border-radius:20px;font-size:11px;font-weight:600;z-index:500;
    opacity:0;transition:opacity .3s,transform .3s;pointer-events:none;white-space:nowrap}
.toast.show{opacity:1;transform:translateX(-50%) translateY(-6px)}

/* ── KEYFRAMES ── */
@keyframes waveAnim{0%{transform:scaleY(.4)}100%{transform:scaleY(1.6)}}
@keyframes dotbounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-4px)}}
@keyframes recpulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.3)}}
@keyframes vrpulse{0%{transform:scale(.5);opacity:.7}100%{transform:scale(1.6);opacity:0}}
@keyframes cardin{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════ -->
<!-- HEADER                                        -->
<!-- ══════════════════════════════════════════════ -->
<div class="header">
  <div class="header-top">
    <span class="header-brand">RUNMYSTORE.AI</span>
    <div class="header-right">
      <span class="plan-badge"><?= htmlspecialchars($plan_label) ?></span>
      <a href="simple.php" class="simple-toggle">&larr; Опростен</a>
      <div class="header-icon" onclick="location.href='settings.php'">
        <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3c.4-1.8 2.9-1.8 3.4 0a1.7 1.7 0 002.6 1.1c1.5-.9 3.3.8 2.4 2.4a1.7 1.7 0 001 2.6c1.8.4 1.8 2.9 0 3.3a1.7 1.7 0 00-1 2.6c.9 1.5-.9 3.3-2.4 2.4a1.7 1.7 0 00-2.6 1c-.4 1.8-2.9 1.8-3.3 0a1.7 1.7 0 00-2.6-1c-1.5.9-3.3-.9-2.4-2.4a1.7 1.7 0 00-1-2.6c-1.8-.4-1.8-2.9 0-3.3a1.7 1.7 0 001-2.6c-.9-1.5.9-3.3 2.4-2.4 1 .6 2.3.1 2.6-1.1z"/><circle cx="12" cy="12" r="3"/></svg>
      </div>
      <div class="header-icon" id="logoutWrap" onclick="toggleLogout()" style="position:relative">
        <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        <a href="logout.php" class="logout-dropdown" id="logoutDrop">Изход &rarr;</a>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════ -->
<!-- MAIN SCROLL AREA                              -->
<!-- ══════════════════════════════════════════════ -->
<div class="main-scroll" id="mainScroll">

  <!-- REVENUE CARD -->
  <div class="revenue-card" style="animation:cardin .5s ease both">
    <div class="revenue-top">
      <span style="display:flex;align-items:center;gap:6px"><span class="revenue-period-label" id="revLabel">ДНЕС</span><a href="stats.php" style="font-size:7px;color:#818cf8;text-decoration:none">Справки &rarr;</a></span>
      <?php if (count($all_stores) > 1): ?>
      <span class="revenue-store-name">
        <select onchange="location.href='?store='+this.value">
          <?php foreach ($all_stores as $st): ?>
          <option value="<?= $st['id'] ?>" <?= $st['id']==$store_id?'selected':'' ?>><?= htmlspecialchars($st['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </span>
      <?php else: ?>
      <span class="revenue-store-name"><?= htmlspecialchars($store_name) ?></span>
      <?php endif; ?>
    </div>
    <div class="revenue-number-row">
      <span class="revenue-number" id="revNum">0</span>
      <span class="revenue-currency"><?= $cs ?></span>
      <span class="revenue-change up" id="revPct">+0%</span><span id="revVs" style="font-size:8px;color:#4b5563;margin-left:4px"></span>
    </div>
    <div class="revenue-comparison" id="revCmp"></div>
    <div class="revenue-meta" id="revMeta">0 продажби</div>
    <?php if ($role === 'owner' && $confidence_pct < 100): ?>
    <div class="confidence-warning" id="confWarn" style="display:none">
      <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
      Данни за <?= $confidence_pct ?>% от артикулите (<?= $with_cost ?>/<?= $total_products ?>)
    </div>
    <?php endif; ?>
    <div class="revenue-pills">
      <div class="revenue-pill-group">
        <span class="revenue-pill active" onclick="setPeriod('today',this)">Днес</span>
        <span class="revenue-pill inactive" onclick="setPeriod('7d',this)">7 дни</span>
        <span class="revenue-pill inactive" onclick="setPeriod('30d',this)">30 дни</span>
        <span class="revenue-pill inactive" onclick="setPeriod('365d',this)">365 дни</span>
      </div>
      <?php if ($role === 'owner'): ?>
      <div class="revenue-divider"></div>
      <div class="revenue-pill-group">
        <span class="revenue-pill active" id="modeRev" onclick="setMode('rev')">Оборот</span>
        <span class="revenue-pill inactive" id="modeProfit" onclick="setMode('profit')">Печалба</span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- STORE HEALTH BAR -->
  <div class="health-bar">
    <span class="health-label">Точност</span>
    <div class="health-track"><div class="health-fill" style="width:<?= $health ?>%"></div></div>
    <span class="health-percent" style="color:<?= $health >= 80 ? '#4ade80' : ($health >= 50 ? '#fbbf24' : '#f87171') ?>"><?= $health ?>%</span>
    <span class="health-link" onclick="openChatQ('Как да подобря AI точността?')">Преброй &rarr;</span>
    <span class="health-info-btn" onclick="document.querySelector('.health-tooltip').classList.toggle('open')"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
  </div>
  <div class="health-tooltip">
    <b>Какво е AI Точност?</b><br>
    Колко добре AI познава магазина ти. Расте когато:<br>
    &bull; Въведеш <b>доставни цени</b> на артикулите<br>
    &bull; <b>Преброиш</b> стоката по рафтовете<br>
    &bull; Получиш <b>доставка</b> с фактура<br><br>
    По-висока точност = по-умни съвети от AI.
  </div>

  <div class="separator"></div>

  <!-- WEATHER CARD -->
  <?php if ($weather_today): ?>
  <div style="margin:6px 12px;padding:10px 14px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);border-radius:14px;animation:cardin .5s .05s ease both">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
      <div style="display:flex;align-items:center;gap:6px">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:none;stroke:#a5b4fc;stroke-width:1.5"><?= wmoSvg((int)$weather_today['weather_code']) ?></svg>
        <span style="font-size:11px;font-weight:700;color:#e2e8f0"><?= wmoText((int)$weather_today['weather_code']) ?></span>
      </div>
      <span style="font-size:18px;font-weight:800;color:#a5b4fc"><?= round($weather_today['temp_max']) ?>°</span>
    </div>
    <div style="font-size:9px;color:#94a3b8;margin-bottom:6px"><?= round($weather_today['temp_min']) ?>° / <?= round($weather_today['temp_max']) ?>° &middot; Дъжд <?= $weather_today['precipitation_prob'] ?>%</div>
    <div style="font-size:10px;color:#d1d5db;line-height:1.4"><?= htmlspecialchars($weather_suggestion) ?></div>
    <?php if (count($weather_week) >= 7): ?>
    <div style="display:flex;gap:4px;margin-top:8px;overflow-x:auto">
      <?php foreach (array_slice($weather_week, 1, 7) as $wd): ?>
      <div style="flex:1;min-width:36px;text-align:center;padding:4px 2px;background:rgba(255,255,255,.03);border-radius:8px">
        <div style="font-size:7px;color:#6b7280"><?= mb_substr(['Нд','Пн','Вт','Ср','Чт','Пт','Сб'][date('w',strtotime($wd['forecast_date']))], 0, 2) ?></div>
        <div style="font-size:10px;font-weight:700;color:#e2e8f0;margin:2px 0"><?= round($wd['temp_max']) ?>°</div>
        <div style="font-size:7px;color:<?= (int)$wd['precipitation_prob'] > 50 ? '#60a5fa' : '#4b5563' ?>"><?= $wd['precipitation_prob'] ?>%</div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- AI BRIEFING BUBBLE -->
  <div class="ai-meta">
    <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    <span class="ai-meta-label">AI</span>
    <span class="ai-meta-time">&middot; <?= date('H:i') ?></span>
  </div>

  <?php if (!empty($briefing)): ?>
  <!-- PRO: Real insights -->
  <div class="ai-bubble" style="animation:cardin .4s .1s ease both">
    <div class="ai-bubble-text with-signals"><?= htmlspecialchars($greeting) ?> Ето какво е важно:</div>
    <?php foreach ($briefing as $bidx => $ins): ?>
    <div class="signal-card <?= urgencyClass($ins['urgency']) ?>" onclick="openSignalDetail(<?= $bidx ?>)">
      <div class="signal-stripe <?= urgencyClass($ins['urgency']) ?>-stripe"></div>
      <div class="signal-content">
        <div class="signal-title"><?= htmlspecialchars($ins['title']) ?></div>
        <?php if (!empty($ins['detail_text'])): ?>
        <div class="signal-body"><?= htmlspecialchars(mb_substr($ins['detail_text'], 0, 80)) ?></div>
        <?php endif; ?>
      </div>
      <span class="signal-arrow">&rsaquo;</span>
    </div>
    <div style="text-align:right;margin:-2px 0 4px"><span style="font-size:9px;color:#818cf8;cursor:pointer" onclick="event.stopPropagation();openSignalDetail(<?= $bidx ?>)">Виж &rarr;</span></div>
    <?php endforeach; ?>
    <?php if (count($insights) > 3): ?>
    <div class="action-buttons">
      <button class="action-button action-button-more" onclick="openSignalBrowser()">Виж още <?= count($insights) - 3 ?> сигнала &rarr;</button>
    </div>
    <?php endif; ?>
  </div>

  <?php elseif (!empty($ghost_pills)): ?>
  <!-- START/FREE: Ghost pill teaser -->
  <div class="ai-bubble" style="animation:cardin .4s .1s ease both">
    <div class="ai-bubble-text with-signals"><?= htmlspecialchars($greeting) ?> AI има съвет за теб...</div>
    <button class="ghost-pill" onclick="showToast('Включи PRO за AI съвети')">PRO</button>
  </div>

  <?php else: ?>
  <!-- No insights / 1/4 silence -->
  <div class="ai-bubble" style="animation:cardin .4s .1s ease both">
    <div class="ai-bubble-text"><?= htmlspecialchars($greeting) ?> Попитай каквото искаш — говори или пиши.</div>
  </div>
  <?php endif; ?>

  <!-- Chat history is in overlay only -->

  <div style="height:14px"></div>
</div>

<!-- ══════════════════════════════════════════════ -->
<!-- INPUT BAR (tap → opens overlay)               -->
<!-- ══════════════════════════════════════════════ -->
<div class="input-bar" id="dashboardInput" onclick="openChat()">
  <div class="input-bar-inner">
    <div class="input-waves">
      <div class="input-wave-bar" style="height:5px;background:#4f46e5"></div>
      <div class="input-wave-bar" style="height:9px;background:#6366f1"></div>
      <div class="input-wave-bar" style="height:14px;background:#818cf8"></div>
      <div class="input-wave-bar" style="height:9px;background:#6366f1"></div>
      <div class="input-wave-bar" style="height:5px;background:#4f46e5"></div>
    </div>
    <span class="input-placeholder">Кажи или напиши...</span>
    <div class="mic-button"><svg viewBox="0 0 24 24"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg></div>
    <div class="send-button"><svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg></div>
  </div>
</div>

<!-- ══════════════════════════════════════════════ -->
<!-- CHAT OVERLAY (72%, blur bg, clean chat)       -->
<!-- ══════════════════════════════════════════════ -->
<div class="chat-overlay-bg" id="chatOverlayBg" onclick="closeChat()"></div>
<div class="chat-overlay-panel" id="chatOverlayPanel">
  <div class="overlay-header">
    <div class="overlay-header-left">
      <div class="overlay-avatar">
        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      </div>
      <span class="overlay-title">AI Асистент</span>
    </div>
    <button class="overlay-close" onclick="closeChat()">
      <svg viewBox="0 0 24 24"><path stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>

  <div class="overlay-messages" id="chatMessages">
    <?php if (empty($chat_messages)): ?>
    <div style="text-align:center;padding:30px 10px;color:#4b5563;font-size:12px">
      <div style="font-size:14px;font-weight:700;margin-bottom:6px;color:#a5b4fc">
        Здравей<?= $user_name ? ', '.htmlspecialchars($user_name) : '' ?>!
      </div>
      Попитай каквото искаш — говори или пиши.
    </div>
    <?php else: ?>
    <?php foreach ($chat_messages as $m): ?>
    <div class="chat-message-group">
      <?php if ($m['role'] === 'assistant'): ?>
      <div class="chat-meta-line">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        AI &middot; <?= date('H:i', strtotime($m['created_at'])) ?>
      </div>
      <div class="chat-ai-msg"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
      <?php else: ?>
      <div class="chat-meta-line right"><?= date('H:i', strtotime($m['created_at'])) ?></div>
      <div class="chat-user-msg"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <div class="chat-typing-indicator" id="chatTyping">
      <div class="typing-dots">
        <div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>
      </div>
    </div>
  </div>

  <div class="recording-bar" id="recBar">
    <div class="recording-dot"></div>
    <span class="recording-label">ЗАПИСВА</span>
    <span class="recording-transcript" id="recTranscript">Слушам...</span>
  </div>

  <div class="overlay-input">
    <div class="overlay-input-inner">
      <div style="display:flex;align-items:center;gap:2px;padding:0 4px;flex-shrink:0">
        <div class="input-wave-bar" style="height:5px;background:#4f46e5"></div>
        <div class="input-wave-bar" style="height:9px;background:#6366f1"></div>
        <div class="input-wave-bar" style="height:14px;background:#818cf8"></div>
        <div class="input-wave-bar" style="height:9px;background:#6366f1"></div>
        <div class="input-wave-bar" style="height:5px;background:#4f46e5"></div>
      </div>
      <textarea class="overlay-textarea" id="chatInput" placeholder="Кажи или пиши..." rows="1"
        oninput="this.style.height='';this.style.height=Math.min(this.scrollHeight,80)+'px';document.getElementById('chatSendBtn').disabled=!this.value.trim()"
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"></textarea>
      <div class="overlay-mic" id="voiceBtn" onclick="toggleVoice()">
        <div class="voice-ring vr1"></div><div class="voice-ring vr2"></div><div class="voice-ring vr3"></div>
        <svg viewBox="0 0 24 24"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg>
      </div>
      <button class="overlay-send" id="chatSendBtn" onclick="sendMsg()" disabled>
        <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════ -->
<!-- BOTTOM NAV                                    -->
<!-- ══════════════════════════════════════════════ -->
<nav class="bottom-nav">
  <a href="chat.php" class="bottom-nav-tab active">
    <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>AI
  </a>
  <a href="warehouse.php" class="bottom-nav-tab inactive">
    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>Склад
  </a>
  <a href="stats.php" class="bottom-nav-tab inactive">
    <svg viewBox="0 0 24 24" stroke-linecap="round">
      <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
      <line x1="6" y1="20" x2="6" y2="14"/></svg>Справки
  </a>
  <a href="sale.php" class="bottom-nav-tab inactive">
    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>Продажба
  </a>
</nav>

<!-- ══════════════════════════════════════════════ -->
<!-- SIGNAL DETAIL OVERLAY                         -->
<!-- ══════════════════════════════════════════════ -->
<div class="signal-detail-bg" id="sigDetailBg" onclick="closeSignalDetail()"></div>
<div class="signal-detail-panel" id="sigDetailPanel">
  <div class="signal-detail-header">
    <div class="signal-detail-header-left">
      <div class="signal-detail-dot" id="sigDetailDot"></div>
      <span class="signal-detail-title" id="sigDetailTitle"></span>
    </div>
    <button class="overlay-close" onclick="closeSignalDetail()">
      <svg viewBox="0 0 24 24"><path stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="signal-detail-body" id="sigDetailBody"></div>
</div>

<!-- ══════════════════════════════════════════════ -->
<!-- SIGNAL BROWSER OVERLAY                        -->
<!-- ══════════════════════════════════════════════ -->
<div class="signal-browser-bg" id="sigBrowserBg" onclick="closeSignalBrowser()"></div>
<div class="signal-browser-panel" id="sigBrowserPanel">
  <div class="overlay-header">
    <div class="overlay-header-left">
      <svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:none;stroke:#a5b4fc;stroke-width:1.5">
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      <span class="overlay-title">Всички сигнали</span>
    </div>
    <button class="overlay-close" onclick="closeSignalBrowser()">
      <svg viewBox="0 0 24 24"><path stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="browser-body" id="sigBrowserBody"></div>
</div>

<div class="toast" id="toast"></div>

<!-- ══════════════════════════════════════════════ -->
<!-- JAVASCRIPT                                    -->
<!-- ══════════════════════════════════════════════ -->
<script>
const P = <?= $periods_json ?>;
const CS = <?= json_encode($cs) ?>;
const IS_OWNER = <?= $role === 'owner' ? 'true' : 'false' ?>;
const ALL_INSIGHTS = <?= $all_insights_json ?>;
const UI_CATS = <?= $ui_categories_json ?>;
const CAT_LABELS = {sales:'Продажби',warehouse:'Склад',products:'Продукти',finance:'Финанси',expenses:'Разходи'};
const CAT_COLORS = {sales:'#ef4444',warehouse:'#818cf8',products:'#c084fc',finance:'#fbbf24',expenses:'#6b7280'};
const URG_COLORS = {critical:'#ef4444',warning:'#fbbf24',info:'#4ade80'};
const URG_TITLE = {critical:'#fca5a5',warning:'#fcd34d',info:'#86efac'};
const URG_BG = {critical:'rgba(239,68,68,.03)',warning:'rgba(251,191,36,.02)',info:'rgba(34,197,94,.02)'};
const URG_BORDER = {critical:'rgba(239,68,68,.15)',warning:'rgba(251,191,36,.12)',info:'rgba(34,197,94,.12)'};

function $(id) { return document.getElementById(id); }
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(n) { return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
function showToast(m) { const t=$('toast'); t.textContent=m; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),3000); }

// ══════════════════════════════════════════════
// REVENUE — Period & Mode switching
// ══════════════════════════════════════════════
let curPeriod = 'today';
let curMode = 'rev';

function updateRevenue() {
    const d = P[curPeriod];
    const val = curMode === 'rev' ? d.rev : d.profit;
    const pct = curMode === 'rev' ? d.cmp_rev : d.cmp_prof;
    const sub = curMode === 'rev' ? d.sub_rev : d.sub_prof;

    $('revNum').textContent = fmt(val);
    $('revPct').textContent = (pct >= 0 ? '+' : '') + pct + '%';
    $('revPct').className = 'revenue-change ' + (pct > 0 ? 'up' : pct < 0 ? 'down' : 'zero');
    $('revCmp').textContent = sub + ' ' + CS;

    const labels = { today: 'ДНЕС', '7d': '7 ДНИ', '30d': '30 ДНИ', '365d': '365 ДНИ' };
    const vsLabels = { today: 'спрямо вчера', '7d': 'спрямо предните 7 дни', '30d': 'спрямо предните 30 дни', '365d': 'спрямо предната година' };
    $('revVs').textContent = vsLabels[curPeriod];
    $('revLabel').textContent = labels[curPeriod];

    let meta = d.cnt + ' продажби';
    if (IS_OWNER && d.cnt > 0) meta += ' \u00b7 ' + d.margin + '% марж';
    $('revMeta').textContent = meta;

    const cw = $('confWarn');
    if (cw) cw.style.display = curMode === 'profit' ? 'flex' : 'none';
}

function setPeriod(period, el) {
    curPeriod = period;
    document.querySelectorAll('.revenue-pill-group:first-child .revenue-pill').forEach(p => {
        p.className = 'revenue-pill ' + (p === el ? 'active' : 'inactive');
    });
    updateRevenue();
}

function setMode(mode) {
    curMode = mode;
    const mr = $('modeRev'), mp = $('modeProfit');
    if (mr) { mr.className = 'revenue-pill ' + (mode === 'rev' ? 'active' : 'inactive'); }
    if (mp) { mp.className = 'revenue-pill ' + (mode === 'profit' ? 'active' : 'inactive'); }
    updateRevenue();
}

// ══════════════════════════════════════════════
// LOGOUT
// ══════════════════════════════════════════════
function toggleLogout() { $('logoutDrop').classList.toggle('show'); }
document.addEventListener('click', e => {
    if (!$('logoutWrap').contains(e.target)) $('logoutDrop').classList.remove('show');
});

// ══════════════════════════════════════════════
// CHAT OVERLAY
// ══════════════════════════════════════════════
let chatOpen = false;

function openChat() {
    if (chatOpen) return;
    chatOpen = true;
    try { sessionStorage.setItem('rms_chat_open','1'); } catch(e) {}
    $('chatOverlayBg').classList.add('open');
    $('chatOverlayPanel').classList.add('open');
    $('mainScroll').style.opacity = '.4';
    $('dashboardInput').style.opacity = '0';
    $('dashboardInput').style.pointerEvents = 'none';
    document.querySelector('.header').style.opacity = '.4';
    history.pushState({ chat: true }, '');
    scrollChatBottom();
    setTimeout(() => $('chatInput').focus(), 300);
}

function closeChat() {
    chatOpen = false;
    try { sessionStorage.removeItem('rms_chat_open'); } catch(e) {}
    $('chatOverlayBg').classList.remove('open');
    $('chatOverlayPanel').classList.remove('open');
    $('mainScroll').style.opacity = '1';
    $('dashboardInput').style.opacity = '1';
    $('dashboardInput').style.pointerEvents = 'auto';
    document.querySelector('.header').style.opacity = '1';
    stopVoice();
}

function openChatQ(question) {
    openChat();
    setTimeout(() => {
        $('chatInput').value = question;
        $('chatSendBtn').disabled = false;
        sendMsg();
    }, 350);
}

function scrollChatBottom() {
    const el = $('chatMessages');
    el.scrollTop = el.scrollHeight;
}

// ══════════════════════════════════════════════
// SEND MESSAGE
// ══════════════════════════════════════════════
async function sendMsg() {
    const inp = $('chatInput');
    const txt = inp.value.trim();
    if (!txt) return;

    addUserBubble(txt);
    inp.value = '';
    inp.style.height = '';
    $('chatSendBtn').disabled = true;
    $('chatTyping').style.display = 'block';
    scrollChatBottom();

    try {
        const r = await fetch('chat-send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: txt })
        });
        const raw = await r.text();
        let d;
        try { d = JSON.parse(raw); } catch(e) {
            $('chatTyping').style.display = 'none';
            addAIBubble('Грешка: ' + raw.substring(0, 200));
            return;
        }
        $('chatTyping').style.display = 'none';
        addAIBubble(d.reply || d.error || 'Грешка при обработка.', d.actions || null);
    } catch (e) {
        $('chatTyping').style.display = 'none';
        addAIBubble('Грешка при свързване. Опитай пак.');
    }
}

function addUserBubble(txt) {
    const g = document.createElement('div');
    g.className = 'chat-message-group';
    const t = new Date().toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit' });
    g.innerHTML = '<div class="chat-meta-line right">' + t + '</div><div class="chat-user-msg">' + esc(txt) + '</div>';
    $('chatMessages').insertBefore(g, $('chatTyping'));
    scrollChatBottom();
}

function addAIBubble(txt, actions) {
    const g = document.createElement('div');
    g.className = 'chat-message-group';
    const t = new Date().toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit' });
    let h = '<div class="chat-meta-line"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>AI \u00b7 ' + t + '</div><div class="chat-ai-msg">' + esc(txt) + '</div>';
    if (actions && actions.length) {
        h += '<div class="action-buttons" style="margin:6px 0 0">';
        actions.forEach(function(a) {
            h += '<button class="action-button" onclick="window.open(\'' + esc(a.url || '#') + '\',\'_blank\')">' + esc(a.label) + ' \u2192</button>';
        });
        h += '</div>';
    }
    g.innerHTML = h;
    $('chatMessages').insertBefore(g, $('chatTyping'));
    scrollChatBottom();
}

// ══════════════════════════════════════════════
// VOICE
// ══════════════════════════════════════════════
let voiceRec = null, isRecording = false, voiceText = '';

function toggleVoice() {
    if (isRecording) { stopVoice(); return; }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { showToast('Браузърът не поддържа гласов вход'); return; }
    isRecording = true;
    voiceText = '';
    $('voiceBtn').classList.add('recording');
    $('recBar').classList.add('on');
    $('recTranscript').innerText = 'Слушам...';
    voiceRec = new SR();
    voiceRec.lang = 'bg-BG';
    voiceRec.continuous = false;
    voiceRec.interimResults = true;
    voiceRec.onresult = e => {
        let fin = '', interim = '';
        for (let i = e.resultIndex; i < e.results.length; i++) {
            if (e.results[i].isFinal) fin += e.results[i][0].transcript;
            else interim += e.results[i][0].transcript;
        }
        if (fin) voiceText = fin;
        $('recTranscript').innerText = voiceText || interim || 'Слушам...';
    };
    voiceRec.onend = () => {
        isRecording = false;
        $('voiceBtn').classList.remove('recording');
        $('recBar').classList.remove('on');
        if (voiceText) {
            $('chatInput').value = voiceText;
            $('chatSendBtn').disabled = false;
            sendMsg();
        }
    };
    voiceRec.onerror = e => {
        stopVoice();
        if (e.error === 'no-speech') showToast('Не чух — опитай пак');
        else if (e.error === 'not-allowed') showToast('Разреши микрофона');
        else showToast('Грешка: ' + e.error);
    };
    try { voiceRec.start(); } catch (e) { stopVoice(); }
}

function stopVoice() {
    isRecording = false;
    voiceText = '';
    $('voiceBtn').classList.remove('recording');
    $('recBar').classList.remove('on');
    if (voiceRec) { try { voiceRec.stop(); } catch (e) {} voiceRec = null; }
}

// ══════════════════════════════════════════════
// SIGNAL DETAIL
// ══════════════════════════════════════════════
let sigDetailOpen = false;

function openSignalDetail(idx) {
    const s = ALL_INSIGHTS[idx];
    if (!s) return;
    sigDetailOpen = true;
    const u = s.urgency;
    const dot = $('sigDetailDot');
    dot.className = 'signal-detail-dot signal-detail-dot-' + u;
    const ttl = $('sigDetailTitle');
    ttl.textContent = s.title;
    ttl.style.color = URG_TITLE[u] || '#e2e8f0';

    const body = $('sigDetailBody');
    let h = '';

    // Hero number
    if (s.value) {
        const sign = u === 'info' ? '+' : '\u2212';
        const color = u === 'info' ? '#4ade80' : (u === 'critical' ? '#f87171' : '#fbbf24');
        h += '<div class="detail-hero"><div class="detail-hero-num" style="color:' + color + '">'
           + sign + fmt(Math.abs(s.value)) + ' ' + CS + '</div>'
           + '<div class="detail-hero-unit">' + (u === 'info' ? 'печалба/период' : 'пропуснати приходи/период') + '</div></div>';
    }

    // Why box
    if (s.detail) {
        h += '<div class="detail-why"><div class="detail-label">Защо</div>'
           + '<div class="detail-text">' + esc(s.detail) + '</div></div>';
    }

    // Suggestion box with action buttons inside
    h += '<div class="detail-suggestion"><div class="detail-label">Предложение</div>';
    if (s.detail) {
        h += '<div class="detail-suggestion-text">Обмисли действие по този сигнал.</div>';
    }
    h += '<div class="detail-actions">';
    if (s.action && s.action.label) {
        if (s.action.type === 'deeplink' && s.action.url) {
            h += '<button class="detail-action-primary" onclick="location.href=\'' + esc(s.action.url) + '\'">' + esc(s.action.label) + '</button>';
        } else if (s.action.type === 'order_draft') {
            h += '<button class="detail-action-primary" onclick="addToOrderDraft(' + idx + ')">' + esc(s.action.label) + '</button>';
        } else {
            h += '<button class="detail-action-primary" onclick="closeSignalDetail();openChatQ(\'' + esc(s.title) + '\')">' + esc(s.action.label) + '</button>';
        }
    }
    h += '<button class="detail-action-secondary" onclick="closeSignalDetail();openChatQ(\'' + esc(s.title) + '\')">Попитай AI</button>';
    h += '</div>';
    if (s.action && s.action.type === 'order_draft') {
        h += '<div class="detail-action-hint">Прибавя към чернова поръчка</div>';
    }
    h += '</div>';

    // Data rows if available
    if (s.data && s.data.products && s.data.products.length) {
        h += '<div class="detail-section">Засегнати артикули</div>';
        s.data.products.forEach(function(p) {
            const vc = p.qty === 0 ? 'color:#f87171' : (p.qty <= 2 ? 'color:#fbbf24' : 'color:#4ade80');
            h += '<div class="detail-row"><span class="detail-row-name">' + esc(p.name) + '</span>'
               + '<span class="detail-row-value" style="' + vc + '">' + p.qty + ' бр</span></div>';
        });
    }

    if (s.count > 0) {
        h += '<div class="detail-section">Обобщение</div>';
        h += '<div class="detail-row"><span class="detail-row-name">Засегнати артикули</span>'
           + '<span class="detail-row-value" style="color:#a5b4fc">' + s.count + '</span></div>';
    }

    body.innerHTML = h;
    $('sigDetailBg').classList.add('open');
    $('sigDetailPanel').classList.add('open');
    history.pushState({ sigDetail: true }, '');
}

function closeSignalDetail() {
    if (!sigDetailOpen) return;
    sigDetailOpen = false;
    $('sigDetailBg').classList.remove('open');
    $('sigDetailPanel').classList.remove('open');
}

function addToOrderDraft(idx) {
    const s = ALL_INSIGHTS[idx];
    showToast('Добавено към чернова поръчка');
    closeSignalDetail();
}

// ══════════════════════════════════════════════
// SIGNAL BROWSER
// ══════════════════════════════════════════════
let sigBrowserOpen = false;

function openSignalBrowser() {
    sigBrowserOpen = true;
    const body = $('sigBrowserBody');
    let h = '';
    const catOrder = ['sales', 'warehouse', 'products', 'finance', 'expenses'];

    catOrder.forEach(function(catKey) {
        const cat = UI_CATS[catKey];
        const items = cat.items || [];
        const label = CAT_LABELS[catKey];
        const color = CAT_COLORS[catKey];

        h += '<div class="browser-cat-wrap">'
           + '<div class="browser-cat-header">'
           + '<div class="browser-cat-dot" style="background:' + color + '"></div>'
           + '<span class="browser-cat-name" style="color:' + color + '">' + label + '</span>'
           + '<span class="browser-cat-count">' + (items.length || '\u2014') + '</span></div>';

        if (catKey === 'expenses' && items.length === 0) {
            h += '<div class="browser-future">Скоро: наем, ток, заплати, break-even</div></div>';
            return;
        }

        if (items.length === 0) {
            h += '<div class="browser-future">Няма сигнали за тази категория</div></div>';
            return;
        }

        items.forEach(function(idx) {
            const s = ALL_INSIGHTS[idx];
            if (!s) return;
            const u = s.urgency;
            h += '<div class="browser-signal" style="background:' + URG_BG[u] + ';border:.5px solid ' + URG_BORDER[u] + ';border-left:4px solid ' + URG_COLORS[u] + '"'
               + ' onclick="closeSignalBrowser();setTimeout(function(){openSignalDetail(' + idx + ')},300)">'
               + '<div class="signal-content"><div class="sig-t" style="color:' + URG_TITLE[u] + '">' + esc(s.title) + '</div>'
               + (s.detail ? '<div class="sig-d" style="font-size:9px;color:#6b7280;margin-top:1px">' + esc(s.detail.substring(0, 60)) + '</div>' : '')
               + '</div><span class="signal-arrow">\u203A</span></div>';
            h += '<div style="text-align:right;margin:-2px 11px 4px"><span style="font-size:9px;color:#818cf8;cursor:pointer" onclick="event.stopPropagation();closeSignalBrowser();setTimeout(function(){openSignalDetail(' + idx + ')},300)">Виж \u2192</span></div>';
        });
        h += '</div>';
    });

    body.innerHTML = h;
    $('sigBrowserBg').classList.add('open');
    $('sigBrowserPanel').classList.add('open');
    history.pushState({ sigBrowser: true }, '');
}

function closeSignalBrowser() {
    if (!sigBrowserOpen) return;
    sigBrowserOpen = false;
    $('sigBrowserBg').classList.remove('open');
    $('sigBrowserPanel').classList.remove('open');
}

// ══════════════════════════════════════════════
// BACK BUTTON + INIT
// ══════════════════════════════════════════════
window.addEventListener('popstate', () => {
    if (sigDetailOpen) { closeSignalDetail(); return; }
    if (sigBrowserOpen) { closeSignalBrowser(); return; }
    if (chatOpen) closeChat();
});

window.addEventListener('DOMContentLoaded', () => {
    updateRevenue();
    // S58: Auto-reopen chat if was open before navigation
    try {
        if (sessionStorage.getItem('rms_chat_open') === '1') {
            setTimeout(() => openChat(), 300);
        }
    } catch(e) {}
});
</script>
</body>
</html>
