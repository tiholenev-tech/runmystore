<?php
/**
 * chat.php — RunMyStore.ai Dashboard + AI Chat v8.0
 * S79.VISUAL_REWRITE — home-neon-v2 design + всички S79 patches запазени
 *
 * Затворен: Revenue карта + Точност + Weather + AI Briefing + input бар
 * Отворен:  75vh overlay (WhatsApp стил), blur фон отдолу
 * Signal:   75vh overlay (обяснителна страница), blur фон, same modern design
 *
 * Закон №1: Пешо не пише нищо — voice/tap/photo only.
 * Закон №2: PHP смята, Gemini говори. Pills/Signals = чист PHP+SQL.
 * Закон №3: AI мълчи, PHP продължава.
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

// ══════════════════════════════════════════════
// WEATHER FORECAST
// ══════════════════════════════════════════════
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
            if ($diff >= 5) $weather_suggestion .= '. Затопляне идва (+' . $diff . '°C за 7 дни) — сезонна смяна наближава';
            elseif ($diff <= -5) $weather_suggestion .= '. Застудяване идва (' . $diff . '°C за 7 дни) — сезонна смяна наближава';
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
    if ($code <= 3) return 'Слънчево';
    if ($code <= 48) return 'Облачно';
    if ($code <= 57) return 'Ръми';
    if ($code <= 67) return 'Дъжд';
    if ($code <= 77) return 'Сняг';
    if ($code <= 82) return 'Порой';
    return 'Буря';
}

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

// S79.BRIEFING_6FQ — BIBLE §6.5 narrative_flow: top 1 insight от всеки fundamental_question
$by_fq = [];
foreach ($insights as $ins) {
    $fq = $ins['fundamental_question'] ?? '';
    if ($fq && !isset($by_fq[$fq])) {
        $by_fq[$fq] = $ins;
    }
}
$narrative_order = ['loss', 'loss_cause', 'gain', 'gain_cause', 'order', 'anti_order'];
$briefing = [];
$briefing_indices = []; // запазваме оригиналния index в $all_insights_for_js за Signal Detail
foreach ($narrative_order as $fq) {
    if (isset($by_fq[$fq])) {
        $briefing[] = $by_fq[$fq];
    }
}
$remaining = max(0, count($insights) - count($briefing));

// S79_P4_PROACTIVE_STRIP — top strip pills (loss+order, 6h cooldown)
$proactive_pills = [];
try {
    if (planAtLeast($plan, 'pro')) {
        $sql = "SELECT i.id, i.topic_id, i.fundamental_question, i.title,
                       i.value_numeric, i.product_count, i.category, i.product_id
                FROM ai_insights i
                LEFT JOIN ai_shown s ON s.tenant_id = i.tenant_id
                  AND s.topic_id = i.topic_id
                  AND s.user_id = ?
                  AND s.shown_at > NOW() - INTERVAL 6 HOUR
                WHERE i.tenant_id = ?
                  AND (i.store_id = 0 OR i.store_id = ?)
                  AND (i.expires_at IS NULL OR i.expires_at > NOW())
                  AND i.fundamental_question IN ('loss','order')
                  AND (i.role_gate = '' OR i.role_gate IS NULL OR FIND_IN_SET(?, i.role_gate) > 0)
                  AND s.id IS NULL
                ORDER BY FIELD(i.fundamental_question,'loss','order'),
                         (i.urgency='critical') DESC,
                         i.value_numeric DESC
                LIMIT 3";
        $proactive_pills = DB::run($sql, [$user_id, $tenant_id, $store_id, $role])->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) { error_log("S79 proactive pills: " . $e->getMessage()); }

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
    // S79_P1_INSIGHT_ACTION_FQ — 2-ри fallback: fundamental_question специфични actions
    $fq = $ins['fundamental_question'] ?? '';
    $tid = $ins['topic_id'] ?? '';
    if ($fq && empty($ins['action_label'])) {
        switch ($fq) {
            case 'loss':
                if (str_contains($tid, 'zero') || str_contains($tid, 'stock') || str_contains($tid, 'below_min') || str_contains($tid, 'running_out'))
                    return ['label' => 'Поръчай липсите', 'type' => 'order_draft', 'url' => null, 'data' => null];
                return ['label' => 'Виж детайли', 'type' => 'deeplink', 'url' => 'products.php', 'data' => null];
            case 'loss_cause':
                if (!empty($ins['supplier_id']))
                    return ['label' => 'Виж доставчика', 'type' => 'deeplink', 'url' => 'products.php?supplier='.(int)$ins['supplier_id'], 'data' => null];
                if (str_contains($tid, 'below_cost') || str_contains($tid, 'selling_at_loss') || str_contains($tid, 'margin'))
                    return ['label' => 'Коригирай цени', 'type' => 'deeplink', 'url' => 'products.php?filter=below_cost', 'data' => null];
                return ['label' => 'Виж причината', 'type' => 'chat', 'url' => null, 'data' => null];
            case 'gain':
                return ['label' => 'Виж продажби', 'type' => 'deeplink', 'url' => 'products.php?filter=top_profit', 'data' => null];
            case 'gain_cause':
                return ['label' => 'Повече данни', 'type' => 'chat', 'url' => null, 'data' => null];
            case 'order':
                return ['label' => 'Подготви поръчка', 'type' => 'order_draft', 'url' => null, 'data' => null];
            case 'anti_order':
                return ['label' => 'Виж zombie стока', 'type' => 'deeplink', 'url' => 'products.php?filter=zombie', 'data' => null];
        }
    }
    // 3-ти fallback: derive from topic_id
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
        // S79_P2_JS_FQ — fundamental_question + UI hue class
        'fq'         => $ins['fundamental_question'] ?? '',
        'qClass'     => (function($f){ return match($f){
            'loss'=>'q1', 'loss_cause'=>'q2', 'gain'=>'q3',
            'gain_cause'=>'q4', 'order'=>'q5', 'anti_order'=>'q6',
            default=>'' }; })($ins['fundamental_question'] ?? ''),
        'fqLabel'    => (function($f){ return match($f){
            'loss'=>'🔴 Какво губиш', 'loss_cause'=>'🟣 От какво губиш',
            'gain'=>'🟢 Какво печелиш', 'gain_cause'=>'🔷 От какво печелиш',
            'order'=>'🟡 Поръчай', 'anti_order'=>'⚫ НЕ поръчвай',
            default=>'' }; })($ins['fundamental_question'] ?? ''),
    ];
}
$all_insights_json = json_encode($all_insights_for_js, JSON_UNESCAPED_UNICODE);
$ui_categories_json = json_encode($ui_categories, JSON_UNESCAPED_UNICODE);

// Urgency class (from v7 mapping, now shorter)
function urgencyClass(string $u): string {
    return match($u) { 'critical' => 'critical', 'warning' => 'warning', default => 'info' };
}

// ══════════════════════════════════════════════
// GREETING
// ══════════════════════════════════════════════
$hour = (int)date('H');
$greeting = match(true) {
    $hour >= 5 && $hour < 12 => 'Добро утро',
    $hour >= 12 && $hour < 18 => 'Здрасти',
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

$bg_days_full = ['Нд','Пн','Вт','Ср','Чт','Пт','Сб'];

?><!DOCTYPE html>
<html lang="bg" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Начало — RunMyStore.ai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{
    --hue1:255; --hue2:222;
    --border:1px; --border-color:hsl(var(--hue2),12%,20%);
    --radius:22px; --radius-sm:14px;
    --ease:cubic-bezier(0.5,1,0.89,1);
    --bg-main:#08090d;
    --text-primary:#f1f5f9;
    --text-secondary:rgba(255,255,255,.6);
    --text-muted:rgba(255,255,255,.4)
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{background:var(--bg-main);color:var(--text-primary);font-family:'Montserrat',Inter,system-ui,sans-serif;min-height:100vh;overflow-x:hidden;-webkit-user-select:none;user-select:none}
body{
    background:
        radial-gradient(ellipse 800px 500px at 20% 10%,hsl(var(--hue1) 60% 35% / .22) 0%,transparent 60%),
        radial-gradient(ellipse 700px 500px at 85% 85%,hsl(var(--hue2) 60% 35% / .22) 0%,transparent 60%),
        linear-gradient(180deg,#0a0b14 0%,#050609 100%);
    background-attachment:fixed;
    padding-bottom:140px;
    position:relative
}
body::before{
    content:'';
    position:fixed;inset:0;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
    opacity:.03;
    pointer-events:none;z-index:1;mix-blend-mode:overlay
}
body.overlay-open{overflow:hidden}
body.overlay-open .app{filter:blur(6px) brightness(.5);transform:scale(.97);pointer-events:none}

.app{position:relative;z-index:2;max-width:480px;margin:0 auto;padding:12px 12px 20px;transition:filter .3s var(--ease),transform .3s var(--ease)}

/* ─────────────────────────────────────────── */
/* GLASS BASE (conic-gradient shine + glow)    */
/* ─────────────────────────────────────────── */
.glass{
    position:relative;
    border-radius:var(--radius);
    border:var(--border) solid var(--border-color);
    background:
        linear-gradient(235deg,hsl(var(--hue1) 50% 10% / .8),hsl(var(--hue1) 50% 10% / 0) 33%),
        linear-gradient(45deg,hsl(var(--hue2) 50% 10% / .8),hsl(var(--hue2) 50% 10% / 0) 33%),
        linear-gradient(hsl(220deg 25% 4.8% / .78));
    backdrop-filter:blur(12px);
    -webkit-backdrop-filter:blur(12px);
    box-shadow:hsl(var(--hue2) 50% 2%) 0 10px 16px -8px,hsl(var(--hue2) 50% 4%) 0 20px 36px -14px;
    isolation:isolate
}
.glass .shine,.glass .glow{--hue:var(--hue1)}
.glass .shine-bottom,.glass .glow-bottom{--hue:var(--hue2);--conic:135deg}
.glass .shine,
.glass .shine::before,
.glass .shine::after{
    pointer-events:none;
    border-radius:0;
    border-top-right-radius:inherit;
    border-bottom-left-radius:inherit;
    border:1px solid transparent;
    width:75%;aspect-ratio:1;
    display:block;position:absolute;
    right:calc(var(--border) * -1);top:calc(var(--border) * -1);
    left:auto;z-index:1;
    --start:12%;
    background:conic-gradient(from var(--conic,-45deg) at center in oklch,transparent var(--start,0%),hsl(var(--hue),var(--sat,80%),var(--lit,60%)),transparent var(--end,50%)) border-box;
    mask:linear-gradient(transparent),linear-gradient(black);
    mask-repeat:no-repeat;
    mask-clip:padding-box,border-box;
    mask-composite:subtract
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
    width:75%;aspect-ratio:1;
    display:block;position:absolute;
    left:auto;bottom:auto;
    mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='240' height='240'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='3' seed='5'/%3E%3CfeColorMatrix values='0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    mask-mode:luminance;mask-size:29%;
    opacity:1;
    filter:blur(12px) saturate(1.25) brightness(0.5);
    mix-blend-mode:plus-lighter;
    z-index:3
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
.glass .glow::after{
    --lit:70%;--sat:100%;--start:15%;--end:35%;
    border-width:calc(var(--radius) * 1.75);
    border-radius:calc(var(--radius) * 2.75);
    inset:calc(var(--radius) * -.25);
    z-index:4;opacity:.75
}
.glass.sm{--radius:var(--radius-sm)}

/* ─────────────────────────────────────────── */
/* HEADER                                      */
/* ─────────────────────────────────────────── */
.header{display:flex;align-items:center;gap:8px;padding:4px 2px 12px}
.brand{
    font-size:11px;font-weight:900;
    letter-spacing:.12em;
    color:hsl(var(--hue1) 50% 70%);
    text-shadow:0 0 10px hsl(var(--hue1) 60% 50% / .3)
}
.plan-badge{
    padding:3px 8px;border-radius:100px;
    background:linear-gradient(135deg,hsl(280 70% 55%),hsl(300 70% 50%));
    color:white;font-size:9px;font-weight:900;letter-spacing:.08em;
    box-shadow:0 0 10px hsl(280 70% 50% / .4),inset 0 1px 0 rgba(255,255,255,.2)
}
.plan-badge.free{background:linear-gradient(135deg,#6b7280,#4b5563);box-shadow:0 0 8px rgba(107,114,128,.3),inset 0 1px 0 rgba(255,255,255,.1)}
.plan-badge.start{background:linear-gradient(135deg,hsl(220 70% 55%),hsl(240 70% 50%));box-shadow:0 0 10px hsl(220 70% 50% / .4),inset 0 1px 0 rgba(255,255,255,.2)}
.header-spacer{flex:1}
.header-icon-btn{
    width:28px;height:28px;border-radius:50%;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.05);
    color:var(--text-secondary);
    cursor:pointer;display:flex;align-items:center;justify-content:center;
    position:relative;text-decoration:none
}
.header-icon-btn svg{width:12px;height:12px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.logout-dropdown{
    position:absolute;top:34px;right:0;
    background:#0f0f2a;
    border:1px solid rgba(239,68,68,.3);
    border-radius:10px;padding:8px 14px;white-space:nowrap;
    z-index:60;box-shadow:0 8px 24px rgba(0,0,0,.5);
    font-size:13px;color:#fca5a5;font-weight:700;
    cursor:pointer;display:none;text-decoration:none
}
.logout-dropdown.show{display:block}

/* ─────────────────────────────────────────── */
/* REVENUE CARD                                */
/* ─────────────────────────────────────────── */
.rev-card{padding:16px 16px 14px;margin-bottom:12px}
.rev-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;position:relative;z-index:5;gap:8px}
.rev-label{
    font-size:10px;font-weight:800;
    text-transform:uppercase;letter-spacing:.08em;
    color:var(--text-muted);
    display:flex;align-items:center;gap:6px
}
.rev-link{
    font-size:9px;color:hsl(var(--hue1) 60% 78%);
    font-weight:700;cursor:pointer;text-decoration:none
}
.store-sel{
    padding:3px 10px;border-radius:100px;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.06);
    color:var(--text-secondary);
    font-size:10px;font-weight:700;
    cursor:pointer;display:flex;align-items:center;gap:4px;
    font-family:inherit
}
.store-sel svg{width:10px;height:10px;stroke:currentColor;stroke-width:2;fill:none}
.store-sel select{
    background:transparent;border:none;color:inherit;font:inherit;
    cursor:pointer;outline:none;appearance:none;-webkit-appearance:none;
    padding-right:4px;max-width:100px;text-overflow:ellipsis
}
.store-sel select option{background:#0f0f2a;color:#e2e8f0}
.rev-big{display:flex;align-items:baseline;gap:8px;margin-bottom:4px;position:relative;z-index:5}
.rev-val{
    font-size:34px;font-weight:900;letter-spacing:-.03em;
    background:linear-gradient(135deg,#fff 0%,hsl(var(--hue1) 60% 85%) 100%);
    -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
    line-height:1;font-variant-numeric:tabular-nums
}
.rev-cur{font-size:14px;color:var(--text-muted);font-weight:700}
.rev-change{
    margin-left:auto;font-size:15px;font-weight:900;
    color:#22c55e;text-shadow:0 0 8px rgba(34,197,94,.3)
}
.rev-change.neg{color:#ef4444;text-shadow:0 0 8px rgba(239,68,68,.3)}
.rev-change.zero{color:#6b7280;text-shadow:none}
.rev-vs{font-size:10px;color:var(--text-muted);margin-left:4px;font-weight:600}
.rev-compare{font-size:10px;color:var(--text-muted);font-weight:600;margin-bottom:10px;position:relative;z-index:5}
.rev-meta{
    font-size:11px;color:var(--text-secondary);font-weight:600;
    margin-bottom:12px;padding-bottom:12px;
    border-bottom:1px solid rgba(255,255,255,.04);
    position:relative;z-index:5
}
.rev-meta b{color:hsl(var(--hue1) 60% 85%);font-weight:800}
/* S79.POLISH2 — Revenue pills (period + mode) */
.rev-pills{display:flex;align-items:center;gap:8px;position:relative;z-index:5;flex-wrap:wrap}
.rev-pill-group{display:flex;gap:4px;padding:3px;background:rgba(0,0,0,.25);border-radius:100px;border:1px solid rgba(255,255,255,.04)}
.rev-pill{
    padding:6px 12px;border-radius:100px;
    font-size:10px;font-weight:700;cursor:pointer;
    font-family:inherit;letter-spacing:.02em;
    border:none;background:transparent;color:rgba(255,255,255,.5);
    transition:all .2s;font-family:inherit
}
.rev-pill:not(.active):active{color:rgba(255,255,255,.85)}
.rev-pill.active{
    background:linear-gradient(135deg,hsl(var(--hue1) 60% 45%),hsl(var(--hue2) 65% 40%));
    color:white;font-weight:800;
    box-shadow:
        0 2px 8px hsl(var(--hue1) 60% 45% / .4),
        inset 0 1px 0 rgba(255,255,255,.2),
        inset 0 0 12px hsl(var(--hue1) 70% 60% / .15);
    text-shadow:0 0 8px hsl(var(--hue1) 80% 70% / .5)
}
.rev-divider{width:1px;height:24px;background:linear-gradient(180deg,transparent,rgba(255,255,255,.1),transparent);margin:0 4px}
.conf-warn{
    display:flex;align-items:center;gap:6px;
    margin:8px 0 0;padding:6px 10px;
    border-radius:10px;
    background:rgba(251,191,36,.06);
    border:1px solid rgba(251,191,36,.15);
    font-size:9px;color:#fcd34d;
    position:relative;z-index:5;font-weight:600
}
.conf-warn svg{width:11px;height:11px;stroke:#fbbf24;fill:none;stroke-width:2.5;flex-shrink:0}

/* ─────────────────────────────────────────── */
/* HEALTH BAR                                  */
/* ─────────────────────────────────────────── */
.health{padding:10px 14px;margin-bottom:10px;--radius:var(--radius-sm);display:flex;align-items:center;gap:8px}
.health-lbl{
    font-size:9px;font-weight:800;color:var(--text-muted);
    text-transform:uppercase;letter-spacing:.06em;
    white-space:nowrap;position:relative;z-index:5
}
.health-track{
    flex:1;height:5px;border-radius:100px;
    background:rgba(255,255,255,.04);overflow:hidden;
    position:relative;z-index:5
}
.health-fill{
    height:100%;border-radius:100px;
    background:linear-gradient(90deg,#ef4444 0%,#f97316 25%,#eab308 50%,#84cc16 75%,#22c55e 100%)
}
.health-pct{
    font-size:11px;font-weight:900;
    position:relative;z-index:5;
    font-variant-numeric:tabular-nums
}
.health-link{
    font-size:9px;color:hsl(var(--hue1) 60% 78%);
    font-weight:700;cursor:pointer;
    white-space:nowrap;position:relative;z-index:5
}
.health-info{
    width:18px;height:18px;border-radius:50%;
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.1);
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;flex-shrink:0;
    position:relative;z-index:5
}
.health-info svg{width:10px;height:10px;stroke:hsl(var(--hue1) 60% 70%);stroke-width:2;fill:none}
.health-tooltip{
    display:none;margin:6px 0 10px;
    padding:12px 14px;border-radius:14px;
    background:rgba(99,102,241,.08);
    border:1px solid rgba(99,102,241,.15);
    font-size:11px;color:#d1d5db;line-height:1.5
}
.health-tooltip.open{display:block;animation:cardin .3s ease both}
.health-tooltip b{color:hsl(var(--hue1) 60% 85%);font-weight:800}

/* ─────────────────────────────────────────── */
/* WEATHER CARD                                */
/* ─────────────────────────────────────────── */
.weather{padding:12px 14px;margin-bottom:10px;--radius:var(--radius-sm)}
.weather-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;position:relative;z-index:5}
.weather-left{display:flex;align-items:center;gap:6px}
.weather-left svg{width:18px;height:18px;stroke:hsl(var(--hue1) 60% 78%);stroke-width:1.5;fill:none}
.weather-condition{font-size:11px;font-weight:700;color:white}
.weather-temp{font-size:18px;font-weight:900;color:hsl(var(--hue1) 60% 85%);font-variant-numeric:tabular-nums}
.weather-range{font-size:9px;color:var(--text-muted);font-weight:600;margin-bottom:6px;position:relative;z-index:5}
.weather-sug{font-size:10.5px;color:rgba(255,255,255,.82);font-weight:600;line-height:1.45;position:relative;z-index:5}
.weather-week{display:flex;gap:4px;margin-top:10px;overflow-x:auto;scrollbar-width:none;position:relative;z-index:5}
.weather-week::-webkit-scrollbar{display:none}
.weather-day{
    flex:1;min-width:38px;text-align:center;
    padding:5px 3px;
    background:rgba(255,255,255,.02);
    border-radius:8px;
    border:1px solid rgba(255,255,255,.03)
}
.weather-day-name{font-size:8px;color:var(--text-muted);font-weight:700}
.weather-day-temp{font-size:11px;font-weight:800;color:white;margin:2px 0;font-variant-numeric:tabular-nums}
.weather-day-rain{font-size:8px;font-weight:700}

.indigo-sep{height:1px;background:linear-gradient(90deg,transparent,hsl(var(--hue1) 40% 30% / .3),transparent);margin:8px 0}

/* ─────────────────────────────────────────── */
/* AI BRIEFING                                 */
/* ─────────────────────────────────────────── */
.ai-meta{display:flex;align-items:center;gap:5px;padding:8px 4px 6px}
.ai-meta svg{width:13px;height:13px;fill:none;stroke:hsl(var(--hue1) 60% 70%);stroke-width:1.5}
.ai-meta-lbl{font-size:10px;color:hsl(var(--hue1) 60% 75%);font-weight:700}
.ai-meta-time{font-size:9px;color:var(--text-muted)}

/* Proactive top-strip (S79) */
.top-strip{display:flex;gap:6px;padding:4px 2px 8px;overflow-x:auto;scrollbar-width:none}
.top-strip::-webkit-scrollbar{display:none}
/* S79.POLISH2 — Top-strip proactive pills */
.top-pill{
    flex-shrink:0;padding:8px 14px;border-radius:100px;
    cursor:pointer;font-size:10px;font-weight:700;line-height:1.2;
    background:linear-gradient(135deg,rgba(255,255,255,.04),rgba(0,0,0,.2));
    border:1px solid rgba(255,255,255,.08);
    color:#e2e8f0;backdrop-filter:blur(6px);
    display:flex;align-items:center;gap:7px;max-width:260px;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.05);
    transition:transform .15s,box-shadow .2s
}
.top-pill:active{transform:scale(.96)}
.top-pill.q1{
    --qc:hsl(0,85%,55%);
    border-color:color-mix(in oklch,var(--qc) 40%,transparent);
    background:linear-gradient(135deg,
        color-mix(in oklch,var(--qc) 20%,hsl(220 30% 8%)),
        color-mix(in oklch,var(--qc) 8%,hsl(220 30% 6%)));
    box-shadow:
        0 2px 10px color-mix(in oklch,var(--qc) 25%,transparent),
        inset 0 1px 0 rgba(255,255,255,.1),
        inset 0 0 16px color-mix(in oklch,var(--qc) 12%,transparent)
}
.top-pill.q5{
    --qc:hsl(38,90%,55%);
    border-color:color-mix(in oklch,var(--qc) 40%,transparent);
    background:linear-gradient(135deg,
        color-mix(in oklch,var(--qc) 20%,hsl(220 30% 8%)),
        color-mix(in oklch,var(--qc) 8%,hsl(220 30% 6%)));
    box-shadow:
        0 2px 10px color-mix(in oklch,var(--qc) 25%,transparent),
        inset 0 1px 0 rgba(255,255,255,.1),
        inset 0 0 16px color-mix(in oklch,var(--qc) 12%,transparent)
}
.top-pill .tp-txt{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.top-pill .tp-val{font-weight:900;flex-shrink:0;font-variant-numeric:tabular-nums}
.top-pill.q1 .tp-val{color:#fca5a5}
.top-pill.q5 .tp-val{color:#fcd34d}

.ai-bubble{padding:12px 14px;margin-bottom:10px;border-radius:var(--radius) var(--radius) var(--radius) 4px}
.ai-bubble-text{font-size:12px;color:rgba(255,255,255,.88);line-height:1.5;margin-bottom:8px;position:relative;z-index:5}

/* Signal cards in briefing */
.sig-card{
    display:flex;align-items:center;gap:9px;
    padding:9px 11px;margin-bottom:5px;
    border-radius:11px;
    background:rgba(0,0,0,.2);
    border:1px solid rgba(255,255,255,.04);
    cursor:pointer;border-left:3px solid;
    position:relative;z-index:5;
    transition:transform .15s,background .15s
}
.sig-card:active{transform:scale(.98)}
.sig-card.critical{border-left-color:#ef4444}
.sig-card.warning{border-left-color:#fbbf24}
.sig-card.info{border-left-color:#22c55e}
.sig-card.q1{border-left-color:hsl(0,85%,55%) !important}
.sig-card.q2{border-left-color:hsl(280,70%,62%) !important}
.sig-card.q3{border-left-color:hsl(145,70%,50%) !important}
.sig-card.q4{border-left-color:hsl(175,70%,50%) !important}
.sig-card.q5{border-left-color:hsl(38,90%,55%) !important}
.sig-card.q6{border-left-color:hsl(220,10%,55%) !important}

/* S79.POLISH — Neon Glass briefing sections */
.briefing-section{
    position:relative;z-index:5;
    margin:10px 0;padding:14px 14px 12px;
    border-radius:14px;
    border:1px solid rgba(255,255,255,.06);
    background:
        linear-gradient(135deg,rgba(255,255,255,.025),rgba(0,0,0,.15)),
        linear-gradient(hsl(220 25% 6% / .6));
    backdrop-filter:blur(8px);
    -webkit-backdrop-filter:blur(8px);
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.04),
        0 4px 12px rgba(0,0,0,.2);
    overflow:hidden
}
.briefing-section::before{
    content:'';position:absolute;
    top:0;left:0;bottom:0;width:3px;
    border-radius:14px 0 0 14px;
    background:linear-gradient(180deg,var(--qcol,transparent) 0%,transparent 100%);
    box-shadow:0 0 20px 1px var(--qcol,transparent);
    opacity:.9
}
.briefing-section::after{
    content:'';position:absolute;
    top:-1px;right:-1px;
    width:80px;height:80px;
    background:radial-gradient(circle at top right,var(--qcol,transparent) 0%,transparent 60%);
    opacity:.12;pointer-events:none;
    border-radius:0 14px 0 0
}
.briefing-section.q1{--qcol:hsl(0,85%,55%)}
.briefing-section.q2{--qcol:hsl(280,70%,62%)}
.briefing-section.q3{--qcol:hsl(145,70%,50%)}
.briefing-section.q4{--qcol:hsl(175,70%,50%)}
.briefing-section.q5{--qcol:hsl(38,90%,55%)}
.briefing-section.q6{--qcol:hsl(220,10%,60%)}

.briefing-head{
    display:flex;align-items:center;gap:8px;
    margin-bottom:8px;position:relative;z-index:2
}
.briefing-emoji{
    font-size:14px;line-height:1;
    filter:drop-shadow(0 0 6px var(--qcol,transparent))
}
.briefing-name{
    font-size:9px;font-weight:900;
    letter-spacing:.1em;text-transform:uppercase;
    color:var(--qcol);
    text-shadow:0 0 10px var(--qcol)
}

.briefing-title{
    font-size:14px;font-weight:800;
    color:#f1f5f9;line-height:1.4;
    margin-bottom:6px;letter-spacing:-.01em;
    position:relative;z-index:2
}
.briefing-detail{
    font-size:12px;font-weight:500;
    color:rgba(255,255,255,.7);
    line-height:1.55;margin-bottom:10px;
    position:relative;z-index:2
}

.briefing-items{
    margin:8px 0 12px;padding:8px 10px;
    background:rgba(0,0,0,.3);
    border-radius:10px;
    border:1px solid rgba(255,255,255,.04);
    position:relative;z-index:2
}
.briefing-item{
    display:flex;align-items:center;gap:8px;
    padding:4px 0;font-size:11px
}
.briefing-item:not(:last-child){border-bottom:1px solid rgba(255,255,255,.03)}
.bi-dot{
    width:5px;height:5px;border-radius:50%;
    background:var(--qcol);flex-shrink:0;
    box-shadow:0 0 8px var(--qcol);
    opacity:.7
}
.bi-name{
    flex:1;color:rgba(255,255,255,.85);
    font-weight:600;overflow:hidden;
    text-overflow:ellipsis;white-space:nowrap
}
.bi-qty{
    color:rgba(255,255,255,.55);font-weight:700;
    font-size:10px;font-variant-numeric:tabular-nums;
    flex-shrink:0;padding:1px 6px;
    background:rgba(255,255,255,.04);
    border-radius:100px
}

.briefing-actions{
    display:flex;gap:6px;margin-top:10px;
    position:relative;z-index:2
}
.briefing-btn-primary{
    flex:1;padding:10px 12px;
    border-radius:100px;
    font-size:11px;font-weight:800;
    text-align:center;cursor:pointer;
    border:1px solid;font-family:inherit;
    text-decoration:none;display:flex;
    align-items:center;justify-content:center;gap:4px;
    letter-spacing:.02em;
    transition:transform .15s,box-shadow .15s;
    background:linear-gradient(135deg,
        color-mix(in oklch,var(--qcol) 35%,hsl(220 30% 10%)) 0%,
        color-mix(in oklch,var(--qcol) 20%,hsl(220 30% 8%)) 100%);
    border-color:color-mix(in oklch,var(--qcol) 50%,transparent);
    color:white;
    box-shadow:
        0 4px 14px color-mix(in oklch,var(--qcol) 35%,transparent),
        inset 0 1px 0 rgba(255,255,255,.12),
        inset 0 0 20px color-mix(in oklch,var(--qcol) 10%,transparent)
}
.briefing-btn-primary:active{
    transform:scale(.97);
    box-shadow:
        0 2px 8px color-mix(in oklch,var(--qcol) 25%,transparent),
        inset 0 1px 0 rgba(255,255,255,.08)
}
.briefing-btn-secondary{
    padding:10px 16px;
    border-radius:100px;
    font-size:11px;font-weight:700;
    text-align:center;cursor:pointer;
    font-family:inherit;letter-spacing:.02em;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.08);
    color:rgba(255,255,255,.7);
    backdrop-filter:blur(4px);
    transition:transform .15s,background .15s,color .15s
}
.briefing-btn-secondary:active{
    transform:scale(.97);
    background:rgba(255,255,255,.06);
    color:rgba(255,255,255,.95)
}
.sig-card-body{flex:1;min-width:0}
.sig-card-t{font-size:11px;font-weight:800;line-height:1.25}
.sig-card.critical .sig-card-t{color:#fca5a5}
.sig-card.warning .sig-card-t{color:#fcd34d}
.sig-card.info .sig-card-t{color:#86efac}
.sig-card-d{font-size:9px;color:var(--text-muted);font-weight:600;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sig-card-arr{color:rgba(255,255,255,.25);font-size:13px;font-weight:900;flex-shrink:0}
/* S79.POLISH2 — "Виж всички сигнала" button */
.sig-more{
    display:inline-flex;align-items:center;gap:4px;
    padding:8px 16px;border-radius:100px;
    background:linear-gradient(135deg,
        hsl(var(--hue1) 40% 20% / .8),
        hsl(var(--hue2) 45% 15% / .8));
    border:1px solid hsl(var(--hue1) 40% 35% / .5);
    color:hsl(var(--hue1) 60% 88%);
    font-size:10px;font-weight:800;
    cursor:pointer;font-family:inherit;
    letter-spacing:.03em;margin-top:8px;
    box-shadow:
        0 2px 10px hsl(var(--hue1) 60% 35% / .3),
        inset 0 1px 0 rgba(255,255,255,.06);
    backdrop-filter:blur(6px);
    transition:transform .15s
}
.sig-more:active{transform:scale(.97)}

.ghost-pill{
    display:inline-flex;padding:7px 14px;
    border-radius:100px;background:transparent;
    border:1px dashed rgba(168,85,247,.3);
    color:rgba(192,132,252,.6);
    font-size:10px;font-weight:800;cursor:pointer;
    font-family:inherit;margin-top:4px
}

/* ─────────────────────────────────────────── */
/* INPUT BAR (dashboard)                       */
/* ─────────────────────────────────────────── */
.input-bar{
    position:fixed;bottom:60px;left:0;right:0;
    max-width:480px;margin:0 auto;padding:8px 12px;
    z-index:35;cursor:pointer;
    transition:opacity .3s,transform .3s
}
body.overlay-open .input-bar{opacity:0;pointer-events:none;transform:translateY(20px)}
.input-bar-inner{
    display:flex;align-items:center;gap:8px;
    padding:10px 14px;border-radius:100px;
    background:linear-gradient(135deg,hsl(var(--hue1) 35% 15% / .85),hsl(var(--hue2) 35% 12% / .7));
    border:1px solid hsl(var(--hue1) 30% 25% / .6);
    backdrop-filter:blur(20px);
    box-shadow:0 8px 24px rgba(0,0,0,.35),0 0 16px hsl(var(--hue1) 60% 45% / .2)
}
.input-waves{display:flex;gap:2px;align-items:flex-end;height:14px;flex-shrink:0}
.input-wave-bar{width:3px;border-radius:100px;animation:wavebar 1.2s ease-in-out infinite}
.input-wave-bar:nth-child(1){height:5px;background:hsl(var(--hue1) 60% 50%);animation-delay:0s}
.input-wave-bar:nth-child(2){height:9px;background:hsl(var(--hue1) 65% 55%);animation-delay:.15s}
.input-wave-bar:nth-child(3){height:14px;background:hsl(var(--hue1) 70% 60%);animation-delay:.3s}
.input-wave-bar:nth-child(4){height:9px;background:hsl(var(--hue1) 65% 55%);animation-delay:.45s}
.input-wave-bar:nth-child(5){height:5px;background:hsl(var(--hue1) 60% 50%);animation-delay:.6s}
@keyframes wavebar{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.4)}}
.input-placeholder{flex:1;font-size:12px;color:var(--text-muted);font-weight:600;letter-spacing:.02em}
.input-mic{
    width:32px;height:32px;border-radius:50%;
    background:linear-gradient(135deg,hsl(var(--hue1) 65% 50%),hsl(var(--hue2) 70% 45%));
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
    box-shadow:0 0 12px hsl(var(--hue1) 60% 45% / .4),inset 0 1px 0 rgba(255,255,255,.2)
}
.input-mic svg{width:14px;height:14px;stroke:white;stroke-width:2;fill:none;stroke-linecap:round}
.input-send{
    width:32px;height:32px;border-radius:50%;
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.1);
    display:flex;align-items:center;justify-content:center;flex-shrink:0
}
.input-send svg{width:13px;height:13px;fill:var(--text-muted)}

/* ─────────────────────────────────────────── */
/* BOTTOM NAV                                  */
/* ─────────────────────────────────────────── */
.bottom-nav{
    position:fixed;bottom:0;left:0;right:0;
    max-width:480px;margin:0 auto;display:flex;
    padding:8px 8px 14px;
    background:linear-gradient(180deg,hsl(220 25% 6% / .85),hsl(220 25% 4% / .95));
    backdrop-filter:blur(20px);
    border-top:1px solid hsl(var(--hue2) 20% 15% / .5);
    z-index:40;
    transition:opacity .3s
}
body.overlay-open .bottom-nav{opacity:0;pointer-events:none}
.nav-tab{
    flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;
    padding:7px 4px 4px;border-radius:12px;
    background:transparent;border:none;
    color:var(--text-muted);cursor:pointer;
    font-family:inherit;text-decoration:none
}
.nav-tab svg{width:20px;height:20px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.nav-tab-label{font-size:9px;font-weight:700;letter-spacing:.02em}
.nav-tab.active{color:hsl(var(--hue1) 60% 88%);text-shadow:0 0 8px hsl(var(--hue1) 70% 50% / .5)}
.nav-tab.active svg{filter:drop-shadow(0 0 6px hsl(var(--hue1) 70% 50% / .6))}

/* ═══════════════════════════════════════════════════════ */
/* 75vh OVERLAY (Chat + Signal Detail + Signal Browser)   */
/* ═══════════════════════════════════════════════════════ */
.ov-bg{
    position:fixed;inset:0;
    background:rgba(5,8,20,.55);
    backdrop-filter:blur(16px) saturate(.85);
    -webkit-backdrop-filter:blur(16px) saturate(.85);
    z-index:200;opacity:0;pointer-events:none;
    transition:opacity .3s var(--ease)
}
.ov-bg.open{opacity:1;pointer-events:auto}
.ov-panel{
    position:fixed;bottom:-80vh;left:0;right:0;
    max-width:480px;margin:0 auto;
    height:75vh;z-index:210;
    display:flex;flex-direction:column;
    transition:bottom .35s var(--ease);
    border-radius:24px 24px 0 0;
    overflow:hidden;
    background:
        linear-gradient(235deg,hsl(var(--hue1) 50% 10% / .9),hsl(var(--hue1) 50% 8% / .7) 33%),
        linear-gradient(45deg,hsl(var(--hue2) 50% 10% / .9),hsl(var(--hue2) 50% 8% / .7) 33%),
        linear-gradient(hsl(220deg 30% 6% / .96));
    border:1px solid hsl(var(--hue1) 30% 25% / .5);
    border-bottom:none;
    backdrop-filter:blur(24px);
    -webkit-backdrop-filter:blur(24px);
    box-shadow:
        0 -20px 60px rgba(0,0,0,.6),
        0 -8px 40px hsl(var(--hue1) 60% 45% / .15),
        inset 0 1px 0 hsl(var(--hue1) 60% 50% / .2)
}
.ov-panel.open{bottom:0}
.ov-panel::before{
    content:'';position:absolute;
    top:0;left:20%;right:20%;height:1px;
    background:linear-gradient(90deg,transparent,hsl(var(--hue1) 70% 65% / .7),transparent);
    z-index:5;pointer-events:none
}
.ov-handle{
    position:absolute;top:6px;left:50%;
    transform:translateX(-50%);
    width:38px;height:4px;border-radius:100px;
    background:rgba(255,255,255,.18);
    z-index:6;pointer-events:none
}

.ov-header{
    display:flex;align-items:center;gap:10px;
    padding:18px 14px 10px;
    border-bottom:1px solid rgba(255,255,255,.05);
    flex-shrink:0;position:relative;z-index:5
}
.ov-back{
    width:32px;height:32px;border-radius:50%;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.06);
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;color:var(--text-secondary);flex-shrink:0
}
.ov-back svg{width:14px;height:14px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.ov-title-wrap{flex:1;display:flex;align-items:center;gap:8px;min-width:0}
.ov-avatar{
    width:32px;height:32px;border-radius:50%;
    background:linear-gradient(135deg,hsl(var(--hue1) 65% 50%),hsl(var(--hue2) 70% 45%));
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 0 14px hsl(var(--hue1) 60% 50% / .45);
    flex-shrink:0
}
.ov-avatar svg{width:14px;height:14px;fill:white}
.ov-title{font-size:14px;font-weight:800;color:#e2e8f0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ov-sub{font-size:9px;color:var(--text-muted);font-weight:600}
.ov-close{
    width:32px;height:32px;border-radius:50%;
    background:rgba(239,68,68,.08);
    border:1px solid rgba(239,68,68,.2);
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;color:#fca5a5;flex-shrink:0
}
.ov-close svg{width:14px;height:14px;stroke:currentColor;stroke-width:2.5;fill:none;stroke-linecap:round}

.ov-signal-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.ov-signal-dot.critical{background:#ef4444;box-shadow:0 0 10px rgba(239,68,68,.5)}
.ov-signal-dot.warning{background:#fbbf24;box-shadow:0 0 10px rgba(251,191,36,.5)}
.ov-signal-dot.info{background:#22c55e;box-shadow:0 0 10px rgba(34,197,94,.5)}

/* ═══ CHAT MESSAGES (WhatsApp neon) ═══ */
.chat-messages{
    flex:1;overflow-y:auto;padding:12px 12px 8px;
    -webkit-overflow-scrolling:touch;scrollbar-width:none;
    display:flex;flex-direction:column;gap:8px
}
.chat-messages::-webkit-scrollbar{display:none}
.chat-empty{text-align:center;padding:40px 20px;color:var(--text-muted);font-size:11px}
.chat-empty-t{font-size:14px;font-weight:800;color:hsl(var(--hue1) 60% 80%);margin-bottom:6px}

.msg-group{display:flex;flex-direction:column;gap:4px}
.msg-meta{font-size:9px;color:var(--text-muted);font-weight:600;display:flex;align-items:center;gap:4px;padding:0 2px}
.msg-meta.right{justify-content:flex-end}
.msg-meta svg{width:10px;height:10px;fill:none;stroke:hsl(var(--hue1) 60% 70%);stroke-width:2}

.msg-ai{
    max-width:82%;padding:10px 13px;
    font-size:13px;line-height:1.5;word-break:break-word;
    background:linear-gradient(135deg,hsl(var(--hue1) 30% 15% / .85),hsl(var(--hue1) 30% 10% / .7));
    border:1px solid hsl(var(--hue1) 35% 25% / .4);
    color:#e2e8f0;
    border-radius:4px 16px 16px 16px;
    white-space:pre-wrap;
    box-shadow:
        0 2px 12px rgba(0,0,0,.25),
        0 0 16px hsl(var(--hue1) 60% 40% / .08),
        inset 0 1px 0 hsl(var(--hue1) 60% 50% / .15);
    align-self:flex-start;position:relative
}
.msg-user{
    max-width:75%;padding:10px 13px;
    font-size:13px;line-height:1.5;word-break:break-word;
    background:linear-gradient(135deg,hsl(var(--hue1) 55% 28%),hsl(var(--hue2) 60% 22%));
    border:1px solid hsl(var(--hue1) 55% 40% / .5);
    color:white;
    border-radius:16px 16px 4px 16px;
    align-self:flex-end;
    box-shadow:
        0 2px 12px rgba(0,0,0,.35),
        0 0 14px hsl(var(--hue1) 60% 45% / .25),
        inset 0 1px 0 rgba(255,255,255,.1);
    position:relative
}

.typing{
    display:none;padding:10px 14px;
    background:linear-gradient(135deg,hsl(var(--hue1) 30% 15% / .85),hsl(var(--hue1) 30% 10% / .7));
    border:1px solid hsl(var(--hue1) 35% 25% / .4);
    border-radius:4px 16px 16px 16px;
    width:fit-content;align-self:flex-start
}
.typing.on{display:block}
.typing-dots{display:flex;gap:4px;align-items:center}
.typing-dot{width:5px;height:5px;border-radius:50%;background:hsl(var(--hue1) 60% 70%);animation:tdot 1.2s infinite}
.typing-dot:nth-child(2){animation-delay:.2s}
.typing-dot:nth-child(3){animation-delay:.4s}
@keyframes tdot{0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-4px);opacity:1}}

/* ═══ REC BAR ═══ */
.rec-bar{
    display:none;align-items:center;gap:8px;
    padding:8px 14px;
    background:linear-gradient(90deg,rgba(239,68,68,.08),rgba(239,68,68,.03));
    border-top:1px solid rgba(239,68,68,.15);
    flex-shrink:0
}
.rec-bar.on{display:flex}
.rec-dot{
    width:9px;height:9px;border-radius:50%;
    background:#ef4444;
    animation:recpulse 1s infinite;
    box-shadow:0 0 10px rgba(239,68,68,.7)
}
@keyframes recpulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.25)}}
.rec-label{font-size:10px;font-weight:900;color:#fca5a5;text-transform:uppercase;letter-spacing:.08em}
.rec-transcript{font-size:12px;color:#e2e8f0;flex:1;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ═══ CHAT INPUT (bottom of overlay) ═══ */
.chat-input{padding:8px 10px 12px;flex-shrink:0}
.chat-input-inner{
    display:flex;align-items:center;gap:8px;
    padding:8px 10px;border-radius:100px;
    background:linear-gradient(135deg,hsl(var(--hue1) 35% 15% / .85),hsl(var(--hue2) 35% 12% / .7));
    border:1px solid hsl(var(--hue1) 30% 25% / .6);
    box-shadow:0 4px 16px rgba(0,0,0,.3),0 0 12px hsl(var(--hue1) 60% 45% / .15)
}
.chat-ta{
    flex:1;background:transparent;border:none;
    color:#f1f5f9;font-size:13px;padding:6px 4px;
    font-family:inherit;outline:none;resize:none;
    max-height:80px;line-height:1.4;font-weight:500
}
.chat-ta::placeholder{color:var(--text-muted);font-weight:600}
.chat-mic{
    width:34px;height:34px;border-radius:50%;
    flex-shrink:0;position:relative;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;
    background:linear-gradient(135deg,hsl(var(--hue1) 65% 50%),hsl(var(--hue2) 70% 45%));
    box-shadow:0 0 14px hsl(var(--hue1) 60% 45% / .45),inset 0 1px 0 rgba(255,255,255,.18);
    transition:all .2s
}
.chat-mic.rec{
    background:linear-gradient(135deg,#ef4444,#dc2626);
    box-shadow:0 0 20px rgba(239,68,68,.55)
}
.chat-mic svg{width:16px;height:16px;color:white;stroke:white;fill:none;stroke-width:2;stroke-linecap:round}
.voice-ring{
    position:absolute;border-radius:50%;
    border:1.5px solid rgba(255,255,255,.3);
    opacity:0;pointer-events:none
}
.chat-mic.rec .voice-ring{border-color:rgba(255,255,255,.5)}
.vr1{width:22px;height:22px;animation:vrpulse 2s 0s ease-in-out infinite}
.vr2{width:32px;height:32px;animation:vrpulse 2s .3s ease-in-out infinite}
.vr3{width:44px;height:44px;animation:vrpulse 2s .6s ease-in-out infinite}
@keyframes vrpulse{0%{transform:scale(.5);opacity:.7}100%{transform:scale(1.6);opacity:0}}
.chat-send{
    width:34px;height:34px;border-radius:50%;
    background:linear-gradient(135deg,#10b981,#059669);
    border:none;color:white;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;transition:opacity .2s;
    box-shadow:0 0 12px rgba(16,185,129,.35)
}
.chat-send:disabled{opacity:.2;box-shadow:none}
.chat-send svg{width:14px;height:14px;fill:white}

/* ═══ SIGNAL DETAIL CONTENT ═══ */
.sig-body{
    flex:1;overflow-y:auto;
    padding:14px 14px 20px;
    -webkit-overflow-scrolling:touch;scrollbar-width:none
}
.sig-body::-webkit-scrollbar{display:none}
.sig-hero{text-align:center;padding:14px 0 18px}
.sig-hero-num{
    font-size:42px;font-weight:900;
    letter-spacing:-.03em;
    font-variant-numeric:tabular-nums;
    line-height:1
}
.sig-hero-num.critical{color:#fca5a5;text-shadow:0 0 20px rgba(239,68,68,.4)}
.sig-hero-num.warning{color:#fcd34d;text-shadow:0 0 20px rgba(251,191,36,.4)}
.sig-hero-num.info{color:#86efac;text-shadow:0 0 20px rgba(34,197,94,.4)}
.sig-hero-unit{
    font-size:11px;color:var(--text-muted);
    margin-top:6px;font-weight:700;
    text-transform:uppercase;letter-spacing:.08em
}
.sig-fq-badge{
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 10px;border-radius:100px;
    font-size:9px;font-weight:800;margin-bottom:10px;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.06);
    letter-spacing:.03em
}
.sig-fq-badge.q1{color:#fca5a5;border-color:hsl(0,85%,50%,.35);background:rgba(239,68,68,.08)}
.sig-fq-badge.q2{color:#c4b5fd;border-color:hsl(280,70%,60%,.35);background:rgba(168,85,247,.08)}
.sig-fq-badge.q3{color:#86efac;border-color:hsl(145,70%,50%,.35);background:rgba(34,197,94,.08)}
.sig-fq-badge.q4{color:#5eead4;border-color:hsl(175,70%,50%,.35);background:rgba(20,184,166,.08)}
.sig-fq-badge.q5{color:#fcd34d;border-color:hsl(38,90%,55%,.35);background:rgba(251,191,36,.08)}
.sig-fq-badge.q6{color:#9ca3af;border-color:hsl(220,10%,50%,.35);background:rgba(107,114,128,.08)}

.sig-box{
    background:linear-gradient(135deg,hsl(var(--hue1) 30% 12% / .65),hsl(var(--hue1) 30% 8% / .5));
    border:1px solid hsl(var(--hue1) 35% 22% / .35);
    border-radius:14px;
    padding:12px 14px;margin-bottom:10px;
    position:relative;overflow:hidden;
    box-shadow:inset 0 1px 0 hsl(var(--hue1) 60% 50% / .12)
}
.sig-label{
    font-size:9px;font-weight:900;
    color:hsl(var(--hue1) 60% 70%);
    text-transform:uppercase;letter-spacing:.08em;
    margin-bottom:6px
}
.sig-text{font-size:12px;color:#e2e8f0;line-height:1.55;font-weight:500}
.sig-suggest{color:hsl(var(--hue1) 60% 85%);font-weight:600}

.sig-actions{display:flex;gap:6px;margin-top:6px}
/* S79.POLISH2 — Signal Detail buttons (neon glass pill) */
.sig-btn-primary{
    flex:1;padding:13px 14px;border-radius:100px;
    font-size:12px;font-weight:800;
    text-align:center;cursor:pointer;border:1px solid;font-family:inherit;
    background:linear-gradient(135deg,hsl(var(--hue1) 65% 48%),hsl(var(--hue2) 70% 42%));
    border-color:hsl(var(--hue1) 60% 55% / .5);
    color:white;letter-spacing:.03em;
    box-shadow:
        0 4px 16px hsl(var(--hue1) 60% 45% / .45),
        inset 0 1px 0 rgba(255,255,255,.2),
        inset 0 0 20px hsl(var(--hue1) 70% 65% / .15);
    text-shadow:0 0 10px hsl(var(--hue1) 80% 75% / .4);
    transition:transform .15s,box-shadow .2s
}
.sig-btn-primary:active{
    transform:scale(.97);
    box-shadow:
        0 2px 8px hsl(var(--hue1) 60% 45% / .35),
        inset 0 1px 0 rgba(255,255,255,.15)
}
.sig-btn-secondary{
    flex:1;padding:13px 14px;border-radius:100px;
    font-size:12px;font-weight:700;
    text-align:center;cursor:pointer;
    border:1px solid rgba(255,255,255,.1);font-family:inherit;
    background:linear-gradient(135deg,rgba(255,255,255,.04),rgba(0,0,0,.15));
    color:rgba(255,255,255,.8);
    letter-spacing:.03em;
    backdrop-filter:blur(6px);
    transition:all .15s
}
.sig-btn-secondary:active{
    transform:scale(.97);
    background:rgba(255,255,255,.08);
    color:white
}
.sig-hint{
    font-size:9px;color:var(--text-muted);
    text-align:center;margin-top:6px;font-weight:600;letter-spacing:.03em
}

.sig-section{
    font-size:9px;font-weight:900;
    color:var(--text-muted);
    text-transform:uppercase;letter-spacing:.08em;
    margin:16px 0 6px;padding:0 2px
}
.sig-row{
    display:flex;justify-content:space-between;align-items:center;
    padding:9px 12px;
    background:rgba(255,255,255,.02);
    border:1px solid rgba(255,255,255,.04);
    border-radius:10px;margin-bottom:4px
}
.sig-row-name{font-size:12px;color:#e2e8f0;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;margin-right:8px}
.sig-row-val{font-size:12px;font-weight:900;font-variant-numeric:tabular-nums;flex-shrink:0}

/* Signal Browser grid */
/* S79.POLISH2 — Signal Browser categories */
.browser-cat{
    position:relative;
    background:
        linear-gradient(135deg,rgba(255,255,255,.025),rgba(0,0,0,.15)),
        linear-gradient(hsl(220 25% 7% / .6));
    border:1px solid rgba(255,255,255,.06);
    border-radius:16px;padding:12px;margin-bottom:12px;
    backdrop-filter:blur(6px);
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.04),
        0 3px 10px rgba(0,0,0,.2);
    overflow:hidden
}
.browser-cat::before{
    content:'';position:absolute;
    top:0;left:0;width:3px;bottom:0;
    background:linear-gradient(180deg,var(--ccol,transparent),transparent);
    box-shadow:0 0 16px 1px var(--ccol,transparent);
    opacity:.8;border-radius:16px 0 0 16px
}
.browser-cat-h{
    display:flex;align-items:center;gap:8px;
    margin:0 2px 10px;position:relative;z-index:2
}
.browser-cat-dot{
    width:9px;height:9px;border-radius:50%;
    box-shadow:0 0 10px currentColor;
    flex-shrink:0
}
.browser-cat-name{
    font-size:11px;font-weight:900;
    text-transform:uppercase;letter-spacing:.08em;
    text-shadow:0 0 10px currentColor
}
.browser-cat-cnt{
    font-size:10px;color:rgba(255,255,255,.5);
    font-weight:800;margin-left:auto;
    padding:2px 8px;border-radius:100px;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.04);
    font-variant-numeric:tabular-nums
}
.browser-future{
    padding:12px;font-size:11px;
    color:rgba(255,255,255,.4);
    border:1px dashed rgba(255,255,255,.08);
    border-radius:10px;text-align:center;margin:4px 0;
    font-weight:600;font-style:italic
}

/* Toast */
.toast{
    position:fixed;bottom:80px;left:50%;
    transform:translateX(-50%);
    background:linear-gradient(135deg,hsl(var(--hue1) 60% 40%),hsl(var(--hue2) 65% 35%));
    color:white;padding:10px 18px;border-radius:100px;
    font-size:12px;font-weight:700;
    z-index:500;opacity:0;
    transition:opacity .3s,transform .3s;
    pointer-events:none;white-space:nowrap;
    box-shadow:0 8px 24px rgba(0,0,0,.4),0 0 16px hsl(var(--hue1) 60% 45% / .4)
}
.toast.show{opacity:1;transform:translateX(-50%) translateY(-6px)}

/* Action buttons (AI chat actions) */
.action-buttons{display:flex;gap:5px;margin-top:8px;flex-wrap:wrap}
.action-button{
    padding:7px 13px;border-radius:8px;
    font-size:11px;font-weight:700;
    color:hsl(var(--hue1) 60% 80%);
    border:1px solid hsl(var(--hue1) 40% 30% / .3);
    background:rgba(99,102,241,.04);
    cursor:pointer;font-family:inherit;
    transition:background .15s
}
.action-button:active{background:rgba(99,102,241,.15)}

@keyframes cardin{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* Safe area (iOS / Capacitor) */
body{padding-bottom:calc(140px + env(safe-area-inset-bottom))}
.bottom-nav{padding-bottom:calc(14px + env(safe-area-inset-bottom))}
.input-bar{bottom:calc(60px + env(safe-area-inset-bottom))}
.chat-input{padding-bottom:calc(12px + env(safe-area-inset-bottom))}

/* ═══════════════════════════════════════════════════════════ */
/* LIGHT THEME OVERRIDES — activated via <html data-theme="light"> */
/* Preview file xchat.php — real toggle will propagate same rules  */
/* ═══════════════════════════════════════════════════════════ */
html[data-theme="light"]{
    --border-color:hsl(var(--hue2),22%,82%);
    --bg-main:#f4f6fb;
    --text-primary:#0f172a;
    --text-secondary:rgba(15,23,42,.70);
    --text-muted:rgba(15,23,42,.50);
}
html[data-theme="light"] body{
    background:
        radial-gradient(ellipse 900px 550px at 20% 10%,hsl(var(--hue1) 85% 72% / .30) 0%,transparent 60%),
        radial-gradient(ellipse 750px 500px at 85% 85%,hsl(var(--hue2) 85% 72% / .30) 0%,transparent 60%),
        linear-gradient(180deg,#f8faff 0%,#e7ebf5 100%);
    background-attachment:fixed;
    color:var(--text-primary);
}
html[data-theme="light"] body::before{
    opacity:.04;
    mix-blend-mode:multiply;
}

/* Glass cards — light gradient surfaces with hue tint */
html[data-theme="light"] .glass{
    background:
        linear-gradient(235deg,hsl(var(--hue1) 70% 92% / .85),hsl(var(--hue1) 70% 95% / 0) 33%),
        linear-gradient(45deg,hsl(var(--hue2) 70% 92% / .85),hsl(var(--hue2) 70% 95% / 0) 33%),
        linear-gradient(hsl(215deg 40% 99% / .92));
    border-color:hsl(var(--hue2),25%,80%);
    box-shadow:
        hsl(var(--hue2) 40% 75% / .35) 0 10px 16px -8px,
        hsl(var(--hue2) 40% 70% / .30) 0 20px 36px -14px;
}
html[data-theme="light"] .glass .shine,
html[data-theme="light"] .glass .shine::before,
html[data-theme="light"] .glass .shine::after{
    --lit:55%;--sat:85%;
}
html[data-theme="light"] .glass .glow{
    filter:blur(14px) saturate(1.5) brightness(.75);
    opacity:.55;
    mix-blend-mode:multiply;
}
html[data-theme="light"] .glass .glow::after{
    --lit:55%;--sat:90%;
    opacity:.55;
}

/* Brand text */
html[data-theme="light"] .brand{
    color:hsl(var(--hue1) 55% 48%);
    text-shadow:0 0 10px hsl(var(--hue1) 60% 60% / .20);
}

/* Gradient text titles — warm light variant */
html[data-theme="light"] h1,
html[data-theme="light"] .hero-title,
html[data-theme="light"] .gradient-title{
    background:linear-gradient(110deg,#1e1b4b 0%,#4338ca 35%,#1e1b4b 70%);
    -webkit-background-clip:text;background-clip:text;
    -webkit-text-fill-color:transparent;color:transparent;
}

/* Invert common dark-on-dark surface patterns (rgba white ratio ≤ .15) */
/* These are inline styles spread across chat.php — we can't edit them,
   but we can use attribute selectors + !important to patch visible ones. */
html[data-theme="light"] [style*="rgba(255,255,255,0.03)"],
html[data-theme="light"] [style*="rgba(255,255,255,0.04)"],
html[data-theme="light"] [style*="rgba(255,255,255,0.05)"]{
    background:rgba(15,23,42,0.03) !important;
}
html[data-theme="light"] [style*="rgba(255,255,255,0.06)"],
html[data-theme="light"] [style*="rgba(255,255,255,0.08)"],
html[data-theme="light"] [style*="rgba(255,255,255,0.1)"]{
    background:rgba(15,23,42,0.06) !important;
}

/* Header icon buttons */
html[data-theme="light"] .header-icon-btn{
    background:rgba(15,23,42,.04);
    border-color:rgba(15,23,42,.10);
    color:var(--text-secondary);
}
html[data-theme="light"] .header-icon-btn:hover{
    background:rgba(15,23,42,.08);
}

/* Bottom nav */
html[data-theme="light"] .bottom-nav{
    background:
        linear-gradient(180deg,hsl(215deg 40% 99% / .75),hsl(215deg 40% 97% / .95)),
        hsl(215deg 40% 99% / .8);
    border-top-color:hsl(var(--hue2),25%,80%);
    backdrop-filter:blur(14px);
    -webkit-backdrop-filter:blur(14px);
}
html[data-theme="light"] .nav-item{color:var(--text-secondary)}
html[data-theme="light"] .nav-item.active{color:hsl(var(--hue1) 65% 50%)}

/* Input bar (chat composer) */
html[data-theme="light"] .input-bar{
    background:
        linear-gradient(180deg,hsl(215deg 40% 99% / .85),hsl(215deg 40% 97% / .95));
    border-color:hsl(var(--hue2),25%,80%);
}
html[data-theme="light"] .chat-input,
html[data-theme="light"] .chat-input input,
html[data-theme="light"] .chat-input textarea{
    background:rgba(255,255,255,.85) !important;
    color:var(--text-primary) !important;
    border-color:hsl(var(--hue2),25%,82%) !important;
}
html[data-theme="light"] .chat-input input::placeholder,
html[data-theme="light"] .chat-input textarea::placeholder{
    color:var(--text-muted) !important;
}

/* Buttons that used dark on dark */
html[data-theme="light"] button,
html[data-theme="light"] .btn{
    color:inherit;
}
html[data-theme="light"] button[style*="rgba(255,255,255"]{
    color:var(--text-primary) !important;
}

/* Revenue pill + weather pill (common pill look) */
html[data-theme="light"] .pill,
html[data-theme="light"] .card,
html[data-theme="light"] .weather-card,
html[data-theme="light"] .revenue-card{
    background:
        linear-gradient(180deg,hsl(215deg 40% 99% / .85),hsl(215deg 40% 96% / .92));
    border-color:hsl(var(--hue2),25%,82%);
    color:var(--text-primary);
}

/* Meta theme-color (status bar on mobile browsers) */
html[data-theme="light"]{
    color-scheme:light;
}

/* Text color fallback */
html[data-theme="light"] body,
html[data-theme="light"] .app,
html[data-theme="light"] p,
html[data-theme="light"] span,
html[data-theme="light"] div{
    color:var(--text-primary);
}
/* Keep secondary/muted spans that were explicitly semi-white readable */
html[data-theme="light"] [style*="rgba(255,255,255,0.4)"],
html[data-theme="light"] [style*="rgba(255,255,255,0.5)"],
html[data-theme="light"] [style*="rgba(255,255,255,0.6)"]{
    color:rgba(15,23,42,.65) !important;
}
html[data-theme="light"] [style*="rgba(255,255,255,0.7)"],
html[data-theme="light"] [style*="rgba(255,255,255,0.85)"]{
    color:rgba(15,23,42,.85) !important;
}

/* Dark-on-dark overlay backdrops → invert for light */
html[data-theme="light"] .overlay,
html[data-theme="light"] .modal-ov{
    background:rgba(255,255,255,.92);
}

/* SVG strokes that were stroke="currentColor" with white fill auto-follow text */
/* no override needed */

/* END LIGHT THEME PREVIEW */
</style>
</head>
<body>

<div class="app" id="app">

    <!-- ═══════════════════════════════════════════ -->
    <!-- HEADER                                      -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="header">
        <div class="brand">RUNMYSTORE.AI</div>
        <span class="plan-badge <?= htmlspecialchars($plan) ?>"><?= htmlspecialchars($plan_label) ?></span>
        <button class="header-icon-btn theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Светла/тъмна тема" style="margin-left:8px">
            <svg id="themeIconSun" viewBox="0 0 24 24" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg id="themeIconMoon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <div class="header-spacer"></div>
        <a href="simple.php" class="header-icon-btn" title="Опростен режим">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </a>
        <a href="settings.php" class="header-icon-btn" title="Настройки">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </a>
        <button class="header-icon-btn" id="logoutBtn" onclick="toggleLogout(event)" title="Изход">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <a href="logout.php" class="logout-dropdown" id="logoutDrop">Изход →</a>
        </button>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- REVENUE CARD                                -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="glass rev-card" style="animation:cardin .5s ease both">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="rev-head">
            <div class="rev-label"><span id="revLabel">ДНЕС</span> <a class="rev-link" href="stats.php">Справки →</a></div>
            <?php if (count($all_stores) > 1): ?>
            <div class="store-sel">
                <select onchange="location.href='?store='+this.value">
                    <?php foreach ($all_stores as $st): ?>
                    <option value="<?= (int)$st['id'] ?>" <?= $st['id']==$store_id?'selected':'' ?>><?= htmlspecialchars($st['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <svg viewBox="0 0 24 24" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <?php else: ?>
            <div class="store-sel"><?= htmlspecialchars($store_name) ?></div>
            <?php endif; ?>
        </div>
        <div class="rev-big">
            <div class="rev-val" id="revNum">0</div>
            <div class="rev-cur"><?= $cs ?></div>
            <div class="rev-change" id="revPct">+0%</div>
            <span class="rev-vs" id="revVs"></span>
        </div>
        <div class="rev-compare" id="revCmp"></div>
        <div class="rev-meta" id="revMeta">0 продажби</div>
        <?php if ($role === 'owner' && $confidence_pct < 100): ?>
        <div class="conf-warn" id="confWarn" style="display:none">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            Данни за <?= $confidence_pct ?>% от артикулите (<?= $with_cost ?>/<?= $total_products ?>)
        </div>
        <?php endif; ?>
        <div class="rev-pills">
            <div class="rev-pill-group">
                <button class="rev-pill active" onclick="setPeriod('today',this)">Днес</button>
                <button class="rev-pill" onclick="setPeriod('7d',this)">7 дни</button>
                <button class="rev-pill" onclick="setPeriod('30d',this)">30 дни</button>
                <button class="rev-pill" onclick="setPeriod('365d',this)">365 дни</button>
            </div>
            <?php if ($role === 'owner'): ?>
            <div class="rev-divider"></div>
            <div class="rev-pill-group">
                <button class="rev-pill active" id="modeRev" onclick="setMode('rev')">Оборот</button>
                <button class="rev-pill" id="modeProfit" onclick="setMode('profit')">Печалба</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- HEALTH BAR                                  -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="glass sm health">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <div class="health-lbl">Точност</div>
        <div class="health-track"><div class="health-fill" style="width:<?= $health ?>%"></div></div>
        <div class="health-pct" style="color:<?= $health >= 80 ? '#86efac' : ($health >= 50 ? '#fcd34d' : '#fca5a5') ?>"><?= $health ?>%</div>
        <div class="health-link" onclick="openChatQ('Как да подобря AI точността?')">Преброй →</div>
        <div class="health-info" onclick="document.getElementById('healthTip').classList.toggle('open')">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        </div>
    </div>
    <div class="health-tooltip" id="healthTip">
        <b>Какво е AI Точност?</b><br>
        Колко добре AI познава магазина ти. Расте когато:<br>
        • Въведеш <b>доставни цени</b> на артикулите<br>
        • <b>Преброиш</b> стоката по рафтовете<br>
        • Получиш <b>доставка</b> с фактура<br><br>
        По-висока точност = по-умни съвети от AI.
    </div>

    <div class="indigo-sep"></div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- WEATHER CARD                                -->
    <!-- ═══════════════════════════════════════════ -->
    <?php if ($weather_today): ?>
    <div class="glass sm weather">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <div class="weather-head">
            <div class="weather-left">
                <svg viewBox="0 0 24 24"><?= wmoSvg((int)$weather_today['weather_code']) ?></svg>
                <div class="weather-condition"><?= wmoText((int)$weather_today['weather_code']) ?></div>
            </div>
            <div class="weather-temp"><?= round($weather_today['temp_max']) ?>°</div>
        </div>
        <div class="weather-range"><?= round($weather_today['temp_min']) ?>° / <?= round($weather_today['temp_max']) ?>° · Дъжд <?= (int)$weather_today['precipitation_prob'] ?>%</div>
        <div class="weather-sug"><?= htmlspecialchars($weather_suggestion) ?></div>
        <?php if (count($weather_week) >= 7): ?>
        <div class="weather-week">
            <?php foreach (array_slice($weather_week, 1, 7) as $wd):
                $dname = $bg_days_full[(int)date('w', strtotime($wd['forecast_date']))];
                $rain = (int)$wd['precipitation_prob'];
            ?>
            <div class="weather-day">
                <div class="weather-day-name"><?= $dname ?></div>
                <div class="weather-day-temp"><?= round($wd['temp_max']) ?>°</div>
                <div class="weather-day-rain" style="color:<?= $rain > 50 ? '#60a5fa' : '#4b5563' ?>"><?= $rain ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="indigo-sep"></div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════ -->
    <!-- AI BRIEFING                                 -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="ai-meta">
        <svg viewBox="0 0 24 20" fill="none"><rect x="2" y="8" width="3" height="7" rx="1.5" fill="currentColor" opacity=".6"><animate attributeName="height" values="7;14;7" dur="1.2s" repeatCount="indefinite"/><animate attributeName="y" values="8;4;8" dur="1.2s" repeatCount="indefinite"/></rect><rect x="7" y="4" width="3" height="12" rx="1.5" fill="currentColor" opacity=".75"><animate attributeName="height" values="12;6;12" dur="1.2s" begin="0.15s" repeatCount="indefinite"/><animate attributeName="y" values="4;7;4" dur="1.2s" begin="0.15s" repeatCount="indefinite"/></rect><rect x="12" y="2" width="3" height="16" rx="1.5" fill="currentColor" opacity=".9"><animate attributeName="height" values="16;8;16" dur="1.2s" begin="0.3s" repeatCount="indefinite"/><animate attributeName="y" values="2;6;2" dur="1.2s" begin="0.3s" repeatCount="indefinite"/></rect><rect x="17" y="5" width="3" height="10" rx="1.5" fill="currentColor" opacity=".7"><animate attributeName="height" values="10;14;10" dur="1.2s" begin="0.45s" repeatCount="indefinite"/><animate attributeName="y" values="5;3;5" dur="1.2s" begin="0.45s" repeatCount="indefinite"/></rect></svg>
        <span class="ai-meta-lbl">AI</span>
        <span class="ai-meta-time">· <?= date('H:i') ?></span>
    </div>

    <?php /* S79_P5_TOP_STRIP_HTML — proactive pills top strip */ if (!empty($proactive_pills)): ?>
    <div class="top-strip">
        <?php foreach ($proactive_pills as $pp):
            $pp_q = match($pp['fundamental_question']){ 'loss'=>'q1', 'order'=>'q5', default=>'' };
            $pp_val = (float)($pp['value_numeric'] ?? 0);
            // S79.FIX: EU формат + правилна единица (пари vs брой)
            $pp_money_topics = ['zero_stock_with_sales','selling_at_loss','seller_discount_killer','zombie_45d'];
            $pp_is_money = in_array($pp['topic_id'] ?? '', $pp_money_topics, true);
            $pp_val_str = $pp_val > 0
                ? ($pp_is_money ? fmtMoney($pp_val, $cs) : fmtQty($pp_val))
                : '';
        ?>
        <div class="top-pill <?= $pp_q ?>" data-topic="<?= htmlspecialchars($pp['topic_id'], ENT_QUOTES) ?>" data-cat="<?= htmlspecialchars($pp['category'] ?? '', ENT_QUOTES) ?>" data-pid="<?= (int)($pp['product_id'] ?? 0) ?>" onclick="proactivePillTap(this, '<?= htmlspecialchars(addslashes($pp['title']), ENT_QUOTES) ?>')">
            <span class="tp-txt"><?= htmlspecialchars($pp['title']) ?></span>
            <?php if ($pp_val_str): ?><span class="tp-val"><?= $pp_val_str ?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($briefing)): ?>
    <!-- PRO: 6-by-fq narrative briefing (BIBLE §6.5) -->
    <div class="glass ai-bubble" style="animation:cardin .4s .1s ease both">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="ai-bubble-text"><?= htmlspecialchars($greeting) ?> Ето пълната картина за днес:</div>
        <?php
        $fq_meta = [
            'loss'       => ['q'=>'q1', 'emoji'=>'🔴', 'name'=>'КАКВО ГУБИШ'],
            'loss_cause' => ['q'=>'q2', 'emoji'=>'🟣', 'name'=>'ОТ КАКВО ГУБИШ'],
            'gain'       => ['q'=>'q3', 'emoji'=>'🟢', 'name'=>'КАКВО ПЕЧЕЛИШ'],
            'gain_cause' => ['q'=>'q4', 'emoji'=>'🔷', 'name'=>'ОТ КАКВО ПЕЧЕЛИШ'],
            'order'      => ['q'=>'q5', 'emoji'=>'🟡', 'name'=>'ПОРЪЧАЙ'],
            'anti_order' => ['q'=>'q6', 'emoji'=>'⚫', 'name'=>'НЕ ПОРЪЧВАЙ'],
        ];
        // Find the original index in $all_insights_for_js by topic_id for openSignalDetail()
        $topic_to_idx = [];
        foreach ($all_insights_for_js as $idx => $v) {
            $topic_to_idx[$v['topicId']] = $idx;
        }
        foreach ($briefing as $ins):
            $fq = $ins['fundamental_question'];
            $meta = $fq_meta[$fq];
            $action = insightAction($ins);
            $idx_in_all = $topic_to_idx[$ins['topic_id']] ?? 0;
            // Extract sub-items (top 3 affected products)
            $items = [];
            if (!empty($ins['data_json'])) {
                $dj = json_decode($ins['data_json'], true);
                if (!empty($dj['products'])) {
                    $items = array_slice($dj['products'], 0, 3);
                }
            }
        ?>
        <div class="briefing-section <?= $meta['q'] ?>">
            <div class="briefing-head">
                <span class="briefing-emoji"><?= $meta['emoji'] ?></span>
                <span class="briefing-name"><?= $meta['name'] ?></span>
            </div>
            <div class="briefing-title"><?= htmlspecialchars($ins['title']) ?></div>
            <?php if (!empty($ins['detail_text'])): ?>
            <div class="briefing-detail"><?= htmlspecialchars($ins['detail_text']) ?></div>
            <?php endif; ?>
            <?php if (!empty($items)): ?>
            <div class="briefing-items">
                <?php foreach ($items as $it):
                    $it_qty = isset($it['qty']) ? (int)$it['qty'] : null;
                    $it_name = $it['name'] ?? '';
                ?>
                <div class="briefing-item">
                    <span class="bi-dot"></span>
                    <span class="bi-name"><?= htmlspecialchars($it_name) ?></span>
                    <?php if ($it_qty !== null): ?>
                    <span class="bi-qty"><?= $it_qty ?> бр</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="briefing-actions">
                <?php if ($action['type'] === 'deeplink' && $action['url']): ?>
                <a href="<?= htmlspecialchars($action['url']) ?>" class="briefing-btn-primary"><?= htmlspecialchars($action['label']) ?> →</a>
                <?php elseif ($action['type'] === 'order_draft'): ?>
                <button class="briefing-btn-primary" onclick="addToOrderDraft(<?= $idx_in_all ?>)"><?= htmlspecialchars($action['label']) ?> →</button>
                <?php else: ?>
                <button class="briefing-btn-primary" onclick="openChatQ('<?= htmlspecialchars(addslashes($ins['title']), ENT_QUOTES) ?>')"><?= htmlspecialchars($action['label']) ?> →</button>
                <?php endif; ?>
                <button class="briefing-btn-secondary" onclick="openSignalDetail(<?= $idx_in_all ?>)">Детайли</button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if ($remaining > 0): ?>
        <button class="sig-more" onclick="openSignalBrowser()">Виж всички <?= count($insights) ?> сигнала →</button>
        <?php endif; ?>
    </div>

    <?php elseif (!empty($ghost_pills)): ?>
    <!-- START/FREE: Ghost pill teaser -->
    <div class="glass ai-bubble" style="animation:cardin .4s .1s ease both">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <div class="ai-bubble-text"><?= htmlspecialchars($greeting) ?> AI има съвет за теб...</div>
        <button class="ghost-pill" onclick="showToast('Включи PRO за AI съвети')">Включи PRO</button>
    </div>

    <?php else: ?>
    <!-- No insights / 1/4 silence -->
    <div class="glass ai-bubble" style="animation:cardin .4s .1s ease both">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <div class="ai-bubble-text"><?= htmlspecialchars($greeting) ?> Попитай каквото искаш — говори или пиши.</div>
    </div>
    <?php endif; ?>

    <div style="height:20px"></div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- INPUT BAR (tap → opens 75vh chat overlay)              -->
<!-- ═══════════════════════════════════════════════════════ -->
<div class="input-bar" id="inpBar" onclick="openChat()">
    <div class="input-bar-inner">
        <div class="input-waves">
            <div class="input-wave-bar"></div>
            <div class="input-wave-bar"></div>
            <div class="input-wave-bar"></div>
            <div class="input-wave-bar"></div>
            <div class="input-wave-bar"></div>
        </div>
        <span class="input-placeholder">Кажи или напиши...</span>
        <div class="input-mic">
            <svg viewBox="0 0 24 24"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
        </div>
        <div class="input-send">
            <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- BOTTOM NAV (4 tabs)                                    -->
<!-- ═══════════════════════════════════════════════════════ -->
<nav class="bottom-nav">
    <a href="chat.php" class="nav-tab active">
        <svg viewBox="0 0 24 20" fill="none"><rect x="2" y="8" width="3" height="7" rx="1.5" fill="currentColor" opacity=".6"><animate attributeName="height" values="7;14;7" dur="1.2s" repeatCount="indefinite"/><animate attributeName="y" values="8;4;8" dur="1.2s" repeatCount="indefinite"/></rect><rect x="7" y="4" width="3" height="12" rx="1.5" fill="currentColor" opacity=".75"><animate attributeName="height" values="12;6;12" dur="1.2s" begin="0.15s" repeatCount="indefinite"/><animate attributeName="y" values="4;7;4" dur="1.2s" begin="0.15s" repeatCount="indefinite"/></rect><rect x="12" y="2" width="3" height="16" rx="1.5" fill="currentColor" opacity=".9"><animate attributeName="height" values="16;8;16" dur="1.2s" begin="0.3s" repeatCount="indefinite"/><animate attributeName="y" values="2;6;2" dur="1.2s" begin="0.3s" repeatCount="indefinite"/></rect><rect x="17" y="5" width="3" height="10" rx="1.5" fill="currentColor" opacity=".7"><animate attributeName="height" values="10;14;10" dur="1.2s" begin="0.45s" repeatCount="indefinite"/><animate attributeName="y" values="5;3;5" dur="1.2s" begin="0.45s" repeatCount="indefinite"/></rect></svg>
        <span class="nav-tab-label">AI</span>
    </a>
    <a href="warehouse.php" class="nav-tab">
        <svg viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        <span class="nav-tab-label">Склад</span>
    </a>
    <a href="stats.php" class="nav-tab">
        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        <span class="nav-tab-label">Справки</span>
    </a>
    <a href="sale.php" class="nav-tab">
        <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        <span class="nav-tab-label">Продажба</span>
    </a>
</nav>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- 75vh CHAT OVERLAY (WhatsApp стил, blur отдолу)         -->
<!-- ═══════════════════════════════════════════════════════ -->
<div class="ov-bg" id="chatBg" onclick="closeChat()"></div>
<div class="ov-panel" id="chatPanel">
    <div class="ov-handle"></div>
    <div class="ov-header">
        <button class="ov-back" onclick="closeChat()" title="Назад">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </button>
        <div class="ov-title-wrap">
            <div class="ov-avatar">
                <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div style="min-width:0">
                <div class="ov-title">AI Асистент</div>
                <div class="ov-sub">Онлайн · отговаря бързо</div>
            </div>
        </div>
        <button class="ov-close" onclick="closeChat()" title="Затвори">
            <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="chat-messages" id="chatMessages">
        <?php if (empty($chat_messages)): ?>
        <div class="chat-empty">
            <div class="chat-empty-t">Здравей<?= $user_name ? ', '.htmlspecialchars($user_name) : '' ?>!</div>
            Попитай каквото искаш — говори или пиши.
        </div>
        <?php else: ?>
        <?php foreach ($chat_messages as $m): ?>
        <div class="msg-group">
            <?php if ($m['role'] === 'assistant'): ?>
            <div class="msg-meta">
                <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                AI · <?= date('H:i', strtotime($m['created_at'])) ?>
            </div>
            <div class="msg-ai"><?= nl2br(htmlspecialchars(preg_replace(['/\*\*(.+?)\*\*/u','/^#{1,3}\s*/mu','/```[\s\S]*?```/u','/`([^`]+)`/u'],['$1','','','$1'],$m['content']))) ?></div>
            <?php else: ?>
            <div class="msg-meta right"><?= date('H:i', strtotime($m['created_at'])) ?></div>
            <div class="msg-user"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <div class="typing" id="typing">
            <div class="typing-dots">
                <div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>
            </div>
        </div>
    </div>

    <div class="rec-bar" id="recBar">
        <div class="rec-dot"></div>
        <span class="rec-label">ЗАПИСВА</span>
        <span class="rec-transcript" id="recTranscript">Слушам...</span>
    </div>

    <div class="chat-input">
        <div class="chat-input-inner">
            <div class="input-waves">
                <div class="input-wave-bar"></div><div class="input-wave-bar"></div>
                <div class="input-wave-bar"></div><div class="input-wave-bar"></div>
                <div class="input-wave-bar"></div>
            </div>
            <textarea class="chat-ta" id="chatInput" placeholder="Кажи или пиши..." rows="1"
                oninput="this.style.height='';this.style.height=Math.min(this.scrollHeight,80)+'px';document.getElementById('chatSend').disabled=!this.value.trim()"
                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"></textarea>
            <div class="chat-mic" id="micBtn" onclick="toggleVoice()">
                <div class="voice-ring vr1"></div>
                <div class="voice-ring vr2"></div>
                <div class="voice-ring vr3"></div>
                <svg viewBox="0 0 24 24"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
            </div>
            <button class="chat-send" id="chatSend" onclick="sendMsg()" disabled>
                <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- 75vh SIGNAL DETAIL OVERLAY                             -->
<!-- ═══════════════════════════════════════════════════════ -->
<div class="ov-bg" id="sigBg" onclick="closeSignalDetail()"></div>
<div class="ov-panel" id="sigPanel">
    <div class="ov-handle"></div>
    <div class="ov-header">
        <button class="ov-back" onclick="closeSignalDetail()" title="Назад">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </button>
        <div class="ov-title-wrap">
            <div class="ov-signal-dot" id="sigDot"></div>
            <div style="min-width:0;flex:1">
                <div class="ov-title" id="sigTitle"></div>
                <div class="ov-sub" id="sigSub">Сигнал · детайли</div>
            </div>
        </div>
        <button class="ov-close" onclick="closeSignalDetail()" title="Затвори">
            <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="sig-body" id="sigBody"></div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- 75vh SIGNAL BROWSER OVERLAY                            -->
<!-- ═══════════════════════════════════════════════════════ -->
<div class="ov-bg" id="brBg" onclick="closeSignalBrowser()"></div>
<div class="ov-panel" id="brPanel">
    <div class="ov-handle"></div>
    <div class="ov-header">
        <button class="ov-back" onclick="closeSignalBrowser()" title="Назад">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </button>
        <div class="ov-title-wrap">
            <div class="ov-avatar">
                <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div style="min-width:0">
                <div class="ov-title">Всички сигнали</div>
                <div class="ov-sub">Категоризирани</div>
            </div>
        </div>
        <button class="ov-close" onclick="closeSignalBrowser()" title="Затвори">
            <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="sig-body" id="brBody"></div>
</div>

<div class="toast" id="toast"></div>

<script>
// ═══════════════════════════════════════════════════════
// THEME TOGGLE (preview — xchat.php)
// ═══════════════════════════════════════════════════════
(function initTheme(){
    try{
        var saved=localStorage.getItem('rms_theme');
        // Default = light for this preview; production default will be dark
        var theme=saved||'light';
        document.documentElement.setAttribute('data-theme',theme);
        document.addEventListener('DOMContentLoaded',function(){
            var sun=document.getElementById('themeIconSun');
            var moon=document.getElementById('themeIconMoon');
            if(!sun||!moon)return;
            if(theme==='light'){sun.style.display='';moon.style.display='none'}
            else{sun.style.display='none';moon.style.display=''}
        });
    }catch(_){}
})();
function toggleTheme(){
    var cur=document.documentElement.getAttribute('data-theme')||'light';
    var nxt=(cur==='light')?'dark':'light';
    document.documentElement.setAttribute('data-theme',nxt);
    try{localStorage.setItem('rms_theme',nxt)}catch(_){}
    var sun=document.getElementById('themeIconSun');
    var moon=document.getElementById('themeIconMoon');
    if(sun&&moon){
        if(nxt==='light'){sun.style.display='';moon.style.display='none'}
        else{sun.style.display='none';moon.style.display=''}
    }
    if(navigator.vibrate)navigator.vibrate(5);
}

// ═══════════════════════════════════════════════════════
// DATA FROM PHP
// ═══════════════════════════════════════════════════════
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
function esc(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
        .replace(/'/g,'&#39;');
}
function fmt(n) { return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
function showToast(m) {
    const t = $('toast'); t.textContent = m; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
}
function vib(n) { if (navigator.vibrate) navigator.vibrate(n || 6); }

// ═══════════════════════════════════════════════════════
// REVENUE — Period & Mode switching
// ═══════════════════════════════════════════════════════
let curPeriod = 'today';
let curMode = 'rev';

function updateRevenue() {
    const d = P[curPeriod];
    const val = curMode === 'rev' ? d.rev : d.profit;
    const pct = curMode === 'rev' ? d.cmp_rev : d.cmp_prof;
    const sub = curMode === 'rev' ? d.sub_rev : d.sub_prof;

    $('revNum').textContent = fmt(val);
    const pctEl = $('revPct');
    pctEl.textContent = (pct >= 0 ? '+' : '') + pct + '%';
    pctEl.className = 'rev-change ' + (pct > 0 ? '' : (pct < 0 ? 'neg' : 'zero'));

    const labels = { today: 'ДНЕС', '7d': '7 ДНИ', '30d': '30 ДНИ', '365d': '365 ДНИ' };
    const vsLabels = { today: 'спрямо вчера', '7d': 'спрямо предните 7 дни', '30d': 'спрямо предните 30 дни', '365d': 'спрямо предната година' };
    $('revVs').textContent = vsLabels[curPeriod];
    $('revLabel').textContent = labels[curPeriod];
    $('revCmp').textContent = sub + ' ' + CS;

    let meta = d.cnt + ' продажби';
    if (IS_OWNER && d.cnt > 0) meta += ' \u00b7 ' + d.margin + '% марж';
    $('revMeta').textContent = meta;

    const cw = $('confWarn');
    if (cw) cw.style.display = curMode === 'profit' ? 'flex' : 'none';
}

function setPeriod(period, el) {
    curPeriod = period;
    document.querySelectorAll('.rev-pill-group:first-child .rev-pill').forEach(p => {
        p.classList.toggle('active', p === el);
    });
    updateRevenue();
    vib(7);
}

function setMode(mode) {
    curMode = mode;
    const mr = $('modeRev'), mp = $('modeProfit');
    if (mr) mr.classList.toggle('active', mode === 'rev');
    if (mp) mp.classList.toggle('active', mode === 'profit');
    updateRevenue();
    vib(7);
}

// ═══════════════════════════════════════════════════════
// LOGOUT
// ═══════════════════════════════════════════════════════
function toggleLogout(e) {
    e.stopPropagation();
    $('logoutDrop').classList.toggle('show');
}
document.addEventListener('click', e => {
    if (!$('logoutBtn').contains(e.target)) $('logoutDrop').classList.remove('show');
});

// ═══════════════════════════════════════════════════════
// OVERLAY STATE + HISTORY (hardware Back button)
// ═══════════════════════════════════════════════════════
const OV = { chat: false, sig: false, br: false };

function _openBody() { document.body.classList.add('overlay-open'); }
function _closeBody() { if (!OV.chat && !OV.sig && !OV.br) document.body.classList.remove('overlay-open'); }

// ─── CHAT OVERLAY ───
function openChat() {
    if (OV.chat) return;
    OV.chat = true;
    try { sessionStorage.setItem('rms_chat_open', '1'); } catch(e) {}
    $('chatBg').classList.add('open');
    $('chatPanel').classList.add('open');
    _openBody();
    history.pushState({ ov: 'chat' }, '');
    setTimeout(() => scrollChatBottom(), 50);
    setTimeout(() => $('chatInput').focus(), 350);
    vib();
}

function closeChat(skipHistory) {
    if (!OV.chat) return;
    OV.chat = false;
    try { sessionStorage.removeItem('rms_chat_open'); } catch(e) {}
    $('chatBg').classList.remove('open');
    $('chatPanel').classList.remove('open');
    stopVoice();
    _closeBody();
    if (!skipHistory && history.state && history.state.ov === 'chat') history.back();
}

function openChatQ(question) {
    openChat();
    setTimeout(() => {
        $('chatInput').value = question;
        $('chatSend').disabled = false;
        sendMsg();
    }, 400);
}

function scrollChatBottom() {
    const el = $('chatMessages');
    el.scrollTop = el.scrollHeight;
}

// ─── SIGNAL DETAIL OVERLAY ───
function openSignalDetail(idx) {
    if (OV.sig) return;
    const s = ALL_INSIGHTS[idx];
    if (!s) return;
    OV.sig = true;

    // Mark as tapped (S79 ai_shown tracking)
    try {
        if (s.topicId) markInsightShown(s.topicId, 'tapped', s.category || '', 0);
    } catch(e) {}

    const u = s.urgency;
    $('sigDot').className = 'ov-signal-dot ' + u;
    $('sigTitle').textContent = s.title;
    $('sigTitle').style.color = URG_TITLE[u] || '#e2e8f0';
    $('sigSub').textContent = s.fqLabel || 'Сигнал · детайли';

    const body = $('sigBody');
    let h = '';

    // fq badge на върха (S79 §6)
    if (s.fqLabel && s.qClass) {
        h += '<div style="text-align:center;margin-bottom:4px">'
           + '<span class="sig-fq-badge ' + s.qClass + '">' + esc(s.fqLabel) + '</span></div>';
    }

    // Hero number (загуба/печалба)
    if (s.value) {
        const sign = u === 'info' ? '+' : '\u2212';
        h += '<div class="sig-hero"><div class="sig-hero-num ' + u + '">'
           + sign + fmt(Math.abs(s.value)) + ' ' + CS + '</div>'
           + '<div class="sig-hero-unit">' + (u === 'info' ? 'печалба / период' : 'пропуснати приходи / период') + '</div></div>';
    }

    // Why box
    if (s.detail) {
        h += '<div class="sig-box"><div class="sig-label">Защо</div>'
           + '<div class="sig-text">' + esc(s.detail) + '</div></div>';
    }

    // Suggestion box + action buttons
    h += '<div class="sig-box"><div class="sig-label">Предложение</div>';
    if (s.detail) {
        h += '<div class="sig-text sig-suggest">Обмисли действие по този сигнал.</div>';
    }
    h += '<div class="sig-actions">';
    if (s.action && s.action.label) {
        if (s.action.type === 'deeplink' && s.action.url) {
            h += '<button class="sig-btn-primary" onclick="location.href=\'' + esc(s.action.url) + '\'">' + esc(s.action.label) + '</button>';
        } else if (s.action.type === 'order_draft') {
            h += '<button class="sig-btn-primary" onclick="addToOrderDraft(' + idx + ')">' + esc(s.action.label) + '</button>';
        } else {
            h += '<button class="sig-btn-primary" onclick="closeSignalDetail();setTimeout(function(){openChatQ(\'' + esc(s.title) + '\')},400)">' + esc(s.action.label) + '</button>';
        }
    }
    h += '<button class="sig-btn-secondary" onclick="closeSignalDetail();setTimeout(function(){openChatQ(\'' + esc(s.title) + '\')},400)">Попитай AI</button>';
    h += '</div>';
    if (s.action && s.action.type === 'order_draft') {
        h += '<div class="sig-hint">Прибавя към чернова поръчка</div>';
    }
    h += '</div>';

    // Засегнати артикули (от data.products)
    if (s.data && s.data.products && s.data.products.length) {
        h += '<div class="sig-section">Засегнати артикули</div>';
        s.data.products.forEach(function(p) {
            const vc = (p.qty === 0) ? '#fca5a5' : (p.qty <= 2 ? '#fcd34d' : '#86efac');
            h += '<div class="sig-row">'
               + '<div class="sig-row-name">' + esc(p.name) + '</div>'
               + '<div class="sig-row-val" style="color:' + vc + '">' + p.qty + ' бр</div>'
               + '</div>';
        });
    }

    // Обобщение
    if (s.count > 0) {
        h += '<div class="sig-section">Обобщение</div>';
        h += '<div class="sig-row">'
           + '<div class="sig-row-name">Засегнати артикули</div>'
           + '<div class="sig-row-val" style="color:#a5b4fc">' + s.count + '</div>'
           + '</div>';
    }

    body.innerHTML = h;
    $('sigBg').classList.add('open');
    $('sigPanel').classList.add('open');
    _openBody();
    history.pushState({ ov: 'sig', idx: idx }, '');
    vib();
}

function closeSignalDetail(skipHistory) {
    if (!OV.sig) return;
    OV.sig = false;
    $('sigBg').classList.remove('open');
    $('sigPanel').classList.remove('open');
    _closeBody();
    if (!skipHistory && history.state && history.state.ov === 'sig') history.back();
}

function addToOrderDraft(idx) {
    const s = ALL_INSIGHTS[idx];
    if (!s) return;
    showToast('Добавено към чернова поръчка');
    closeSignalDetail();
    // TODO: actual order draft integration (S83 orders.php)
}

// ─── SIGNAL BROWSER OVERLAY ───
function openSignalBrowser() {
    if (OV.br) return;
    OV.br = true;
    const body = $('brBody');
    let h = '';
    const catOrder = ['sales', 'warehouse', 'products', 'finance', 'expenses'];

    catOrder.forEach(function(catKey) {
        const cat = UI_CATS[catKey];
        const items = cat.items || [];
        const label = CAT_LABELS[catKey];
        const color = CAT_COLORS[catKey];

        h += '<div class="browser-cat" style="--ccol:' + color + '">'
           + '<div class="browser-cat-h">'
           + '<div class="browser-cat-dot" style="background:' + color + ';color:' + color + '"></div>'
           + '<span class="browser-cat-name" style="color:' + color + '">' + label + '</span>'
           + '<span class="browser-cat-cnt">' + (items.length || '\u2014') + '</span></div>';

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
            h += '<div class="sig-card ' + u + '" style="margin:4px 0" onclick="closeSignalBrowser();setTimeout(function(){openSignalDetail(' + idx + ')},400)">'
               + '<div class="sig-card-body">'
               + '<div class="sig-card-t">' + esc(s.title) + '</div>'
               + (s.detail ? '<div class="sig-card-d">' + esc(s.detail.substring(0, 80)) + '</div>' : '')
               + '</div>'
               + '<div class="sig-card-arr">\u203A</div></div>';
        });
        h += '</div>';
    });

    body.innerHTML = h;
    $('brBg').classList.add('open');
    $('brPanel').classList.add('open');
    _openBody();
    history.pushState({ ov: 'br' }, '');
    vib();
}

function closeSignalBrowser(skipHistory) {
    if (!OV.br) return;
    OV.br = false;
    $('brBg').classList.remove('open');
    $('brPanel').classList.remove('open');
    _closeBody();
    if (!skipHistory && history.state && history.state.ov === 'br') history.back();
}

// ─── Hardware Back Button (телефон) ───
window.addEventListener('popstate', e => {
    if (OV.sig) closeSignalDetail(true);
    else if (OV.br) closeSignalBrowser(true);
    else if (OV.chat) closeChat(true);
});

// ─── ESC key (desktop) ───
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (OV.sig) closeSignalDetail();
        else if (OV.br) closeSignalBrowser();
        else if (OV.chat) closeChat();
    }
});

// ─── Swipe down to close overlay ───
let _touchY = 0;
['chatPanel', 'sigPanel', 'brPanel'].forEach(id => {
    const p = $(id);
    if (!p) return;
    p.addEventListener('touchstart', e => { _touchY = e.touches[0].clientY; }, { passive: true });
    p.addEventListener('touchend', e => {
        const dy = e.changedTouches[0].clientY - _touchY;
        // Only close if swipe начва от горните 80px и слиза >80px
        const rect = p.getBoundingClientRect();
        if (_touchY < rect.top + 80 && dy > 80) {
            if (id === 'chatPanel') closeChat();
            else if (id === 'sigPanel') closeSignalDetail();
            else if (id === 'brPanel') closeSignalBrowser();
        }
    }, { passive: true });
});

// ═══════════════════════════════════════════════════════
// SEND MESSAGE
// ═══════════════════════════════════════════════════════
async function sendMsg() {
    const inp = $('chatInput');
    const txt = inp.value.trim();
    if (!txt) return;

    addUserBubble(txt);
    inp.value = '';
    inp.style.height = '';
    $('chatSend').disabled = true;
    $('typing').classList.add('on');
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
            $('typing').classList.remove('on');
            addAIBubble('Грешка: ' + raw.substring(0, 200));
            return;
        }
        $('typing').classList.remove('on');
        addAIBubble(d.reply || d.error || 'Грешка при обработка.', d.actions || null);
    } catch (e) {
        $('typing').classList.remove('on');
        addAIBubble('Грешка при свързване. Опитай пак.');
    }
}

function addUserBubble(txt) {
    const g = document.createElement('div');
    g.className = 'msg-group';
    const t = new Date().toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit' });
    g.innerHTML = '<div class="msg-meta right">' + t + '</div>'
                + '<div class="msg-user">' + esc(txt) + '</div>';
    $('chatMessages').insertBefore(g, $('typing'));
    scrollChatBottom();
}

function addAIBubble(txt, actions) {
    const g = document.createElement('div');
    g.className = 'msg-group';
    const t = new Date().toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit' });
    let h = '<div class="msg-meta">'
          + '<svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'
          + 'AI \u00b7 ' + t + '</div>'
          + '<div class="msg-ai">' + esc(txt) + '</div>';
    if (actions && actions.length) {
        h += '<div class="action-buttons">';
        actions.forEach(function(a) {
            h += '<button class="action-button" onclick="window.open(\'' + esc(a.url || '#') + '\',\'_blank\')">' + esc(a.label) + ' \u2192</button>';
        });
        h += '</div>';
    }
    g.innerHTML = h;
    $('chatMessages').insertBefore(g, $('typing'));
    scrollChatBottom();
}

// ═══════════════════════════════════════════════════════
// VOICE
// ═══════════════════════════════════════════════════════
let voiceRec = null, isRecording = false, voiceText = '';

function toggleVoice() {
    if (isRecording) { stopVoice(); return; }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { showToast('Браузърът не поддържа гласов вход'); return; }
    isRecording = true;
    voiceText = '';
    $('micBtn').classList.add('rec');
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
        $('micBtn').classList.remove('rec');
        $('recBar').classList.remove('on');
        if (voiceText) {
            $('chatInput').value = voiceText;
            $('chatSend').disabled = false;
            sendMsg();
        }
    };
    voiceRec.onerror = e => {
        stopVoice();
        if (e.error === 'no-speech') showToast('Не чух — опитай пак');
        else if (e.error === 'not-allowed') showToast('Разреши микрофона');
        else showToast('Грешка: ' + e.error);
    };
    try { voiceRec.start(); } catch(e) { stopVoice(); }
}

function stopVoice() {
    isRecording = false;
    voiceText = '';
    const mb = $('micBtn'), rb = $('recBar');
    if (mb) mb.classList.remove('rec');
    if (rb) rb.classList.remove('on');
    if (voiceRec) { try { voiceRec.stop(); } catch(e) {} voiceRec = null; }
}

// ═══════════════════════════════════════════════════════
// S79 — MARK INSIGHT SHOWN + PROACTIVE PILL TAP
// ═══════════════════════════════════════════════════════
function markInsightShown(topicId, action, category, pid) {
    try {
        fetch('mark-insight-shown.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'topic_id=' + encodeURIComponent(topicId || '')
                + '&action=' + encodeURIComponent(action || 'shown')
                + '&category=' + encodeURIComponent(category || '')
                + '&product_id=' + encodeURIComponent(pid || 0)
        }).catch(function(e) { console.warn('mark-insight-shown fail', e); });
    } catch(e) {}
}

function proactivePillTap(el, title) {
    const topic = el.getAttribute('data-topic') || '';
    const cat = el.getAttribute('data-cat') || '';
    const pid = el.getAttribute('data-pid') || 0;
    markInsightShown(topic, 'tapped', cat, pid);
    // Опитай да намериш insight в текущия pool
    if (typeof ALL_INSIGHTS !== 'undefined') {
        for (let i = 0; i < ALL_INSIGHTS.length; i++) {
            if (ALL_INSIGHTS[i].topicId === topic) { openSignalDetail(i); return; }
        }
    }
    // Fallback: отвори чат с title
    openChatQ(title);
}

// ═══════════════════════════════════════════════════════
// INIT + HAPTICS
// ═══════════════════════════════════════════════════════
window.addEventListener('DOMContentLoaded', () => {
    updateRevenue();
    // S58: Auto-reopen chat if was open before navigation
    try {
        if (sessionStorage.getItem('rms_chat_open') === '1') {
            setTimeout(() => openChat(), 300);
        }
    } catch(e) {}
});

document.querySelectorAll('.sig-card,.sig-more,.nav-tab,.header-icon-btn,.store-sel,.health-link,.health-info,.top-pill,.rev-pill').forEach(el => {
    el.addEventListener('click', () => vib(6));
});
</script>

</body>
</html>
