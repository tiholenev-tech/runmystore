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
// S82.STUDIO.NAV — AI Studio entry pending count
// (products needing bg removal OR description — drives the badge)
// ══════════════════════════════════════════════
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
<title>P11 · Подробен режим · RunMyStore.AI</title>

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
  position: relative; font-size: 15px; font-weight: 900; letter-spacing: 0.10em;
  background: linear-gradient(90deg, hsl(var(--hue1) 80% 60%), hsl(var(--hue2) 80% 60%), hsl(var(--hue3) 70% 55%), hsl(var(--hue2) 80% 60%), hsl(var(--hue1) 80% 60%));
  background-size: 200% auto;
  -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
  animation: rmsBrandShimmer 4s linear infinite;
  filter: drop-shadow(0 0 12px hsl(var(--hue1) 70% 50% / 0.4));
}
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
[data-range="3"] .wfc-tab[data-tab="3"],
[data-range="7"] .wfc-tab[data-tab="7"],
[data-range="14"] .wfc-tab[data-tab="14"] {
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
</style>
</head>
<body class="has-rms-shell mode-detailed">

<div class="aurora" aria-hidden="true">
  <div class="aurora-blob"></div><div class="aurora-blob"></div><div class="aurora-blob"></div>
</div>

<?php include __DIR__ . "/partials/header.php"; ?>

<div class="lb-mode-row">
  <a class="lb-mode-toggle">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    <span>{T_SIMPLE_MODE}</span>
  </a>
</div>

<main class="app">

  <!-- ═══ TOP ROW (Днес + Времето) ═══ -->
  <div class="top-row">
    <div class="glass sm cell qd">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="cell-header-row">
        <div class="cell-label">{T_TODAY} · ENI</div>
      </div>
      <div class="cell-numrow">
        <span class="cell-num">847</span>
        <span class="cell-cur">€</span>
        <span class="cell-pct pos">+12%</span>
      </div>
      <div class="cell-meta">12 продажби · 318 печалба</div>
    </div>
    <div class="glass sm cell qd">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="weather-cell-top">
        <span class="weather-cell-icon">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
        </span>
        <span class="weather-cell-temp">22°</span>
      </div>
      <div class="weather-cell-cond">{T_SUNNY}</div>
      <div class="cell-meta">14°/22° · {T_RAIN} 5%</div>
    </div>
  </div>

    <!-- ═══ AI STUDIO ROW ═══ -->
  <div class="studio-row">
    <a class="glass sm studio-btn">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <span class="studio-icon">
        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      </span>
      <span class="studio-text">
        <span class="studio-label">AI Studio</span>
        <span class="studio-sub">385 {T_WAITING}</span>
      </span>
      <span class="studio-badge">99+</span>
    </a>
  </div>

  
  <!-- ═══ WEATHER FORECAST CARD ═══ -->
  <div class="glass sm wfc q4" data-range="3">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>

    <div class="wfc-head">
      <div class="wfc-head-ic">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
      </div>
      <div class="wfc-head-text">
        <div class="wfc-title">{T_WEATHER_FORECAST}</div>
        <div class="wfc-sub">{T_AI_RECS_FOR_WEEK}</div>
      </div>
    </div>

    <!-- Range tabs -->
    <div class="wfc-tabs">
      <button class="wfc-tab" data-tab="3" onclick="wfcSetRange('3')">{T_3_DAYS}</button>
      <button class="wfc-tab" data-tab="7" onclick="wfcSetRange('7')">{T_7_DAYS}</button>
      <button class="wfc-tab" data-tab="14" onclick="wfcSetRange('14')">{T_14_DAYS}</button>
    </div>

    <!-- Days strip (14 days, hidden via [data-range] selector) -->
    <div class="wfc-days">
      <!-- Today -->
      <div class="wfc-day today sunny">
        <div class="wfc-day-name">{T_TODAY_SHORT}</div>
        <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg></div>
        <div class="wfc-day-temp">22°<small>/14</small></div>
        <div class="wfc-day-rain dry">5%</div>
      </div>
      <!-- Tomorrow -->
      <div class="wfc-day partly">
        <div class="wfc-day-name">{T_DAY_FRI}</div>
        <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M22 14a4 4 0 00-7.5-2 5.5 5.5 0 00-10 1A4 4 0 005 21h13a4 4 0 004-4 4 4 0 00-2-3.46"/><circle cx="6" cy="6" r="2"/></svg></div>
        <div class="wfc-day-temp">24°<small>/15</small></div>
        <div class="wfc-day-rain dry">15%</div>
      </div>
      <!-- Sat -->
      <div class="wfc-day rain">
        <div class="wfc-day-name">{T_DAY_SAT}</div>
        <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M20 16.2A4.5 4.5 0 0017.5 8h-1.8A7 7 0 104 14.9"/><line x1="8" y1="19" x2="8" y2="21"/><line x1="8" y1="13" x2="8" y2="15"/><line x1="16" y1="19" x2="16" y2="21"/><line x1="16" y1="13" x2="16" y2="15"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="12" y1="15" x2="12" y2="17"/></svg></div>
        <div class="wfc-day-temp">19°<small>/13</small></div>
        <div class="wfc-day-rain">75%</div>
      </div>
      <!-- Sun -->
      <div class="wfc-day rain">
        <div class="wfc-day-name">{T_DAY_SUN}</div>
        <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M20 16.2A4.5 4.5 0 0017.5 8h-1.8A7 7 0 104 14.9"/><line x1="8" y1="19" x2="8" y2="21"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="16" y1="19" x2="16" y2="21"/></svg></div>
        <div class="wfc-day-temp">17°<small>/12</small></div>
        <div class="wfc-day-rain">82%</div>
      </div>
      <!-- Mon -->
      <div class="wfc-day cloudy">
        <div class="wfc-day-name">{T_DAY_MON}</div>
        <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z"/></svg></div>
        <div class="wfc-day-temp">21°<small>/14</small></div>
        <div class="wfc-day-rain dry">25%</div>
      </div>
      <!-- Tue -->
      <div class="wfc-day partly">
        <div class="wfc-day-name">{T_DAY_TUE}</div>
        <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M22 14a4 4 0 00-7.5-2 5.5 5.5 0 00-10 1A4 4 0 005 21h13a4 4 0 004-4z"/><circle cx="6" cy="6" r="2"/></svg></div>
        <div class="wfc-day-temp">25°<small>/16</small></div>
        <div class="wfc-day-rain dry">10%</div>
      </div>
      <!-- Wed -->
      <div class="wfc-day sunny">
        <div class="wfc-day-name">{T_DAY_WED}</div>
        <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg></div>
        <div class="wfc-day-temp">28°<small>/17</small></div>
        <div class="wfc-day-rain dry">0%</div>
      </div>
      <!-- Thu (day 8 — appears at 14d) -->
      <div class="wfc-day sunny">
        <div class="wfc-day-name">{T_DAY_THU}</div>
        <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg></div>
        <div class="wfc-day-temp">29°<small>/18</small></div>
        <div class="wfc-day-rain dry">0%</div>
      </div>
      <div class="wfc-day partly"><div class="wfc-day-name">9.V</div><div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M22 14a4 4 0 00-7.5-2 5.5 5.5 0 00-10 1A4 4 0 005 21h13a4 4 0 004-4z"/><circle cx="6" cy="6" r="2"/></svg></div><div class="wfc-day-temp">26°<small>/16</small></div><div class="wfc-day-rain dry">15%</div></div>
      <div class="wfc-day rain"><div class="wfc-day-name">10.V</div><div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M20 16.2A4.5 4.5 0 0017.5 8h-1.8A7 7 0 104 14.9"/><line x1="8" y1="19" x2="8" y2="21"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="16" y1="19" x2="16" y2="21"/></svg></div><div class="wfc-day-temp">22°<small>/15</small></div><div class="wfc-day-rain">65%</div></div>
      <div class="wfc-day storm"><div class="wfc-day-name">11.V</div><div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M19 16.9A5 5 0 0018 7h-1.26a8 8 0 10-11.62 9"/><polyline points="13 11 9 17 15 17 11 23"/></svg></div><div class="wfc-day-temp">20°<small>/14</small></div><div class="wfc-day-rain">88%</div></div>
      <div class="wfc-day cloudy"><div class="wfc-day-name">12.V</div><div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z"/></svg></div><div class="wfc-day-temp">23°<small>/15</small></div><div class="wfc-day-rain dry">30%</div></div>
      <div class="wfc-day partly"><div class="wfc-day-name">13.V</div><div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M22 14a4 4 0 00-7.5-2 5.5 5.5 0 00-10 1A4 4 0 005 21h13a4 4 0 004-4z"/><circle cx="6" cy="6" r="2"/></svg></div><div class="wfc-day-temp">25°<small>/16</small></div><div class="wfc-day-rain dry">20%</div></div>
      <div class="wfc-day sunny"><div class="wfc-day-name">14.V</div><div class="wfc-day-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg></div><div class="wfc-day-temp">27°<small>/17</small></div><div class="wfc-day-rain dry">5%</div></div>
    </div>

    <!-- AI recs divider -->
    <div class="wfc-recs-divider"><span>{T_AI_RECS}</span></div>

    <!-- 3 AI recommendations based on weather -->
    <div class="wfc-rec window">
      <span class="wfc-rec-ic">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
      </span>
      <div class="wfc-rec-text">
        <span class="wfc-rec-label">{T_REC_WINDOW}</span>
        <div class="wfc-rec-body">Топла седмица идва — изложи <b>летни рокли</b> и <b>сламени шапки</b>. В събота дъжд → добави <b>чадъри</b> на витрината.</div>
      </div>
    </div>

    <div class="wfc-rec order">
      <span class="wfc-rec-ic">
        <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.7l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.7l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.3 7 12 12 20.7 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg>
      </span>
      <div class="wfc-rec-text">
        <span class="wfc-rec-label">{T_REC_ORDER}</span>
        <div class="wfc-rec-body">Прохладни вечери (12-15°) — <b>поръчай 12 пуловера</b> от Tommy Jeans. Средата на седмицата 28° → <b>по-малко якета</b>.</div>
      </div>
    </div>

    <div class="wfc-rec transfer">
      <span class="wfc-rec-ic">
        <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
      </span>
      <div class="wfc-rec-text">
        <span class="wfc-rec-label">{T_REC_TRANSFER}</span>
        <div class="wfc-rec-body">София топъл уикенд → <b>прехвърли 8 банки от Магазин 2</b> (Пловдив, по-хладно). Заявка готова за tap.</div>
      </div>
    </div>

    <div class="wfc-source">
      <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12.01" y2="8"/><line x1="11" y1="12" x2="12" y2="16"/></svg>
      <span>OPEN-METEO · {T_UPDATED_TIME} 18:32</span>
    </div>
  </div>

<!-- ═══ AI HELP CARD (новa секция, qhelp) ═══ -->
  <div class="glass sm help-card qhelp">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>

    <div class="help-head">
      <div class="help-head-ic">
        <svg viewBox="0 0 24 24"><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/><circle cx="12" cy="12" r="10"/></svg>
      </div>
      <div class="help-head-text">
        <div class="help-title">{T_AI_HELPS_YOU}</div>
        <div class="help-sub">{T_AI_HELPS_SUB}</div>
      </div>
    </div>

    <div class="help-body">
      {T_AI_HELP_BODY_1} <b>{T_AI_HELP_BODY_2}</b>. {T_AI_HELP_BODY_3}
    </div>

    <div class="help-chips-label">{T_TRY_ASKING}</div>
    <div class="help-chips">
      <button class="help-chip"><span class="help-chip-q">?</span><span>Какво ми тежи на склада</span></button>
      <button class="help-chip"><span class="help-chip-q">?</span><span>Кои са топ продавачи</span></button>
      <button class="help-chip"><span class="help-chip-q">?</span><span>Колко да поръчам от Nike</span></button>
      <button class="help-chip"><span class="help-chip-q">?</span><span>Защо приходите паднаха</span></button>
      <button class="help-chip"><span class="help-chip-q">?</span><span>Покажи ми Adidas 42</span></button>
      <button class="help-chip"><span class="help-chip-q">?</span><span>Какво продаваме днес</span></button>
    </div>

    <div class="help-video-ph">
      <span class="help-video-ic">
        <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
      </span>
      <div class="help-video-text">
        <div class="help-video-title">{T_VIDEO_LESSON}</div>
        <div class="help-video-sub">{T_COMING_SOON}</div>
      </div>
    </div>

    <a class="help-link-row">
      <span>{T_VIEW_ALL_CAPABILITIES}</span>
      <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <!-- ═══ LIFE BOARD HEADER + FILTER PILLS + 12 CARDS ═══ -->
  <div class="lb-header">
    <div class="lb-title">
      <div class="lb-title-orb"></div>
      <span class="lb-title-text">Life Board</span>
    </div>
    <span class="lb-count">12 {T_THINGS} · 18:32</span>
  </div>

  <!-- Filter pills (модули) -->
  <div class="fp-row">
    <button class="fp-pill active">{T_FP_ALL} <span class="fp-count">12</span></button>
    <button class="fp-pill">
      <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
      {T_FP_FINANCE} <span class="fp-count">3</span>
    </button>
    <button class="fp-pill">
      <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1.5"/><circle cx="18" cy="21" r="1.5"/><path d="M3 3h2l2.7 12.3a2 2 0 002 1.7h7.6a2 2 0 002-1.5L21 8H6"/></svg>
      {T_FP_SALES} <span class="fp-count">2</span>
    </button>
    <button class="fp-pill">
      <svg viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
      {T_FP_INVENTORY} <span class="fp-count">2</span>
    </button>
    <button class="fp-pill">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      {T_FP_ORDERS} <span class="fp-count">2</span>
    </button>
    <button class="fp-pill">
      <svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/></svg>
      {T_FP_DELIVERIES} <span class="fp-count">1</span>
    </button>
    <button class="fp-pill">
      <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
      {T_FP_TRANSFERS} <span class="fp-count">1</span>
    </button>
    <button class="fp-pill">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      {T_FP_CUSTOMERS} <span class="fp-count">1</span>
    </button>
  </div>

  <!-- Card 1 — q1 ФИНАНСИ Cash flow -->
  <div class="glass sm lb-card q1">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">ФИНАНСИ · {T_Q_LOSS}</span>
        <span class="lb-collapsed-title">Cash flow негативен — −820 € последни 7 дни</span>
      </div>
      <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 2 — q3 ПРОДАЖБИ EXPANDED -->
  <div class="glass sm lb-card q3 expanded">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">ПРОДАЖБИ · {T_Q_GAIN}</span>
        <span class="lb-collapsed-title">Passionata +35% топ печалба тази седмица</span>
      </div>
      <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
    <div class="lb-expanded">
      <div class="lb-body">Passionata Bikini Line — 12 броя за 7 дни. Печалба <b>185€</b> (+35% спрямо м.с.). Запасите 8 броя — мисли за зареждане.</div>
      <div class="lb-actions">
        <button class="lb-action">{T_WHY}</button>
        <button class="lb-action">{T_SHOW}</button>
        <button class="lb-action primary">{T_REORDER} →</button>
      </div>
      <div class="lb-feedback">
        <span class="lb-fb-label">{T_USEFUL}?</span>
        <button class="lb-fb-btn up"><svg viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3zM7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3"/></svg></button>
        <button class="lb-fb-btn down"><svg viewBox="0 0 24 24"><path d="M10 15v4a3 3 0 003 3l4-9V2H5.72a2 2 0 00-2 1.7l-1.38 9a2 2 0 002 2.3zM17 2h2.67A2.31 2.31 0 0122 4v7a2.31 2.31 0 01-2.33 2H17"/></svg></button>
        <button class="lb-fb-btn hmm"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></button>
      </div>
    </div>
  </div>

  <!-- Card 3 — q5 ПОРЪЧКИ -->
  <div class="glass sm lb-card q5">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">ПОРЪЧКИ · {T_Q_ORDER}</span>
        <span class="lb-collapsed-title">Tommy Jeans 32 — под минимум, поръчай 12 бр</span>
      </div>
      <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 4 — q1 СКЛАД zombie -->
  <div class="glass sm lb-card q1">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><polygon points="10.29 3.86 1.82 18 22.18 18 13.71 3.86 10.29 3.86"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">СКЛАД · {T_Q_LOSS}</span>
        <span class="lb-collapsed-title">Nike Air Max 42 — 60 дни, 180 € замразени</span>
      </div>
      <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 5 — q2 ДОСТАВКИ забавяне -->
  <div class="glass sm lb-card q2">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">ДОСТАВКИ · {T_Q_LOSS_CAUSE}</span>
        <span class="lb-collapsed-title">Иватекс забавя — 4 дни средно повече</span>
      </div>
      <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 6 — q1 ФИНАНСИ марж -->
  <div class="glass sm lb-card q1">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">ФИНАНСИ · {T_Q_LOSS}</span>
        <span class="lb-collapsed-title">Бельо под себестойност — 12 артикула, −68 €</span>
      </div>
      <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 7 — q3 КЛИЕНТИ retention -->
  <div class="glass sm lb-card q3">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">КЛИЕНТИ · {T_Q_GAIN}</span>
        <span class="lb-collapsed-title">8 нови повторни клиенти този месец</span>
      </div>
      <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 8 — q5 ПРОДАЖБИ peak hour -->
  <div class="glass sm lb-card q5">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">ПРОДАЖБИ · {T_Q_GAIN_CAUSE}</span>
        <span class="lb-collapsed-title">Петък 15:00 — пик. Сложи 2-ри продавач</span>
      </div>
      <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 9 — q5 ПОРЪЧКИ Nike -->
  <div class="glass sm lb-card q5">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">ПОРЪЧКИ · {T_Q_ORDER}</span>
        <span class="lb-collapsed-title">Nike размери 38, 39, 40 свършват тази седмица</span>
      </div>
      <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 10 — q4 СКЛАД точност -->
  <div class="glass sm lb-card q3">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">СКЛАД · {T_Q_GAIN_CAUSE}</span>
        <span class="lb-collapsed-title">Точност 94% — почти готово за AI Marketing</span>
      </div>
      <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 11 — q4 ТРАНСФЕРИ -->
  <div class="glass sm lb-card q3">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">ТРАНСФЕРИ · {T_Q_GAIN_CAUSE}</span>
        <span class="lb-collapsed-title">Магазин 3 има 8 дамски летни рокли излишни</span>
      </div>
      <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 12 — q1 ФИНАНСИ ДДС -->
  <div class="glass sm lb-card q1">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">ФИНАНСИ · {T_Q_LOSS}</span>
        <span class="lb-collapsed-title">ДДС период — остават 6 дни до 25-ти</span>
      </div>
      <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>


  <div class="glass sm lb-card q1">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb">
        <svg viewBox="0 0 24 24"><polygon points="10.29 3.86 1.82 18 22.18 18 13.71 3.86 10.29 3.86"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">{T_Q_LOSS}</span>
        <span class="lb-collapsed-title">Nike Air Max 42 — 60 дни без продажба</span>
      </div>
      <button class="lb-expand-btn" aria-label="{T_EXPAND}"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 2 — q3 gain (EXPANDED demo) -->
  <div class="glass sm lb-card q3 expanded">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
      <span class="lb-emoji-orb">
        <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">{T_Q_GAIN}</span>
        <span class="lb-collapsed-title">Passionata +35% топ печалба тази седмица</span>
      </div>
      <button class="lb-expand-btn" aria-label="{T_EXPAND}"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
    <div class="lb-expanded">
      <div class="lb-body">Passionata Bikini Line — продадени 12 броя за 7 дни. Печалба <b>185€</b> (+35% спрямо предишна седмица). Запасите още 8 броя — мисли за зареждане преди да свършат.</div>
      <div class="lb-actions">
        <button class="lb-action">{T_WHY}</button>
        <button class="lb-action">{T_SHOW}</button>
        <button class="lb-action primary">{T_REORDER} →</button>
      </div>
      <div class="lb-feedback">
        <span class="lb-fb-label">{T_USEFUL}?</span>
        <button class="lb-fb-btn up" aria-label="{T_YES}"><svg viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3zM7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3"/></svg></button>
        <button class="lb-fb-btn down" aria-label="{T_NO}"><svg viewBox="0 0 24 24"><path d="M10 15v4a3 3 0 003 3l4-9V2H5.72a2 2 0 00-2 1.7l-1.38 9a2 2 0 002 2.3zM17 2h2.67A2.31 2.31 0 0122 4v7a2.31 2.31 0 01-2.33 2H17"/></svg></button>
        <button class="lb-fb-btn hmm" aria-label="{T_UNCLEAR}"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></button>
      </div>
    </div>
  </div>

  <!-- Card 3 — q5 order -->
  <div class="glass sm lb-card q5">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">{T_Q_ORDER}</span>
        <span class="lb-collapsed-title">Tommy Jeans 32 — под минимум, поръчай</span>
      </div>
      <button class="lb-expand-btn" aria-label="{T_EXPAND}"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 4 — q1 loss -->
  <div class="glass sm lb-card q1">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb">
        <svg viewBox="0 0 24 24"><polygon points="10.29 3.86 1.82 18 22.18 18 13.71 3.86 10.29 3.86"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">{T_Q_LOSS}</span>
        <span class="lb-collapsed-title">Бельо под себестойност — 12 артикула</span>
      </div>
      <button class="lb-expand-btn" aria-label="{T_EXPAND}"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <!-- Card 5 — q2 cause -->
  <div class="glass sm lb-card q2">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">{T_Q_LOSS_CAUSE}</span>
        <span class="lb-collapsed-title">Иватекс забавя — 4 дни средно повече</span>
      </div>
      <button class="lb-expand-btn" aria-label="{T_EXPAND}"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
  </div>

  <div class="see-more-mini">{T_VIEW_ALL} 12 →</div>

</main>

<!-- ═══ INFO POPOVER OVERLAY ═══ -->
<div class="info-overlay" id="infoOverlay" onclick="if(event.target===this)closeInfo()">
  <div class="info-card">
    <div class="info-card-head">
      <div class="info-card-ic" id="infoIc"></div>
      <div class="info-card-title" id="infoTitle"></div>
      <button class="info-card-close" onclick="closeInfo()" aria-label="{T_CLOSE}">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="info-card-body" id="infoBody"></div>
    <div class="info-card-voice">
      <div class="info-card-voice-label">
        <svg viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0014 0v-2"/></svg>
        <span>{T_VOICE_EXAMPLE}</span>
      </div>
      <div class="info-card-voice-text" id="infoVoice"></div>
    </div>
    <button class="info-card-cta" id="infoCta">
      <span id="infoCtaLabel"></span>
      <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </button>
  </div>
</div>

<!-- Chat input bar -->
<div class="chat-input-bar">
  <span class="chat-input-icon">
    <svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="3" y2="12"/><line x1="6" y1="9" x2="6" y2="15"/><line x1="9" y1="6" x2="9" y2="18"/><line x1="12" y1="9" x2="12" y2="15"/><line x1="15" y1="11" x2="15" y2="13"/></svg>
  </span>
  <span class="chat-input-text">{T_SAY_OR_TYPE}</span>
  <button class="chat-mic" aria-label="{T_VOICE}">
    <svg viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0014 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
  </button>
  <button class="chat-send" aria-label="{T_SEND}">
    <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
  </button>
</div>

<!-- preserved overlays -->
<!-- ═══════════════════════════════════════════════════════ -->
<!-- 75vh CHAT OVERLAY (WhatsApp стил, blur отдолу)         -->
<!-- ═══════════════════════════════════════════════════════ -->
<div class="ov-bg" id="chatBg" data-vg-skip="overlay" onclick="closeChat()"></div>
<div class="ov-panel" id="chatPanel" data-vg-skip="overlay">
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
<div class="ov-bg" id="sigBg" data-vg-skip="overlay" onclick="closeSignalDetail()"></div>
<div class="ov-panel" id="sigPanel" data-vg-skip="overlay">
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
<div class="ov-bg" id="brBg" data-vg-skip="overlay" onclick="closeSignalBrowser()"></div>
<div class="ov-panel" id="brPanel" data-vg-skip="overlay">
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

<?php include __DIR__ . "/partials/bottom-nav.php"; ?>

<script>
// Info popover content per button
const INFO_DATA = {
  sell: {
    title: 'Продай',
    iconSvg: '<svg viewBox="0 0 24 24" style="stroke:hsl(145 60% 45%);"><circle cx="9" cy="21" r="1.5"/><circle cx="18" cy="21" r="1.5"/><path d="M3 3h2l2.7 12.3a2 2 0 002 1.7h7.6a2 2 0 002-1.5L21 8H6"/></svg>',
    body: 'Регистрирай <b>нова продажба</b>. Скенирай или избери продукти, въведи количества и цена, прие̇ми плащане. AI може да попълни всичко по глас.',
    voice: 'Продай 2 черни тениски Nike размер L',
    cta: 'Отвори продажба'
  },
  inventory: {
    title: 'Стоката',
    iconSvg: '<svg viewBox="0 0 24 24" style="stroke:hsl(255 70% 60%);"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
    body: 'Управлявай <b>артикулите</b> в магазина. Добавяй нови, редактирай цени, преглеждай наличности, печатай етикети. Може с глас или ръчно.',
    voice: 'Покажи Nike размер 42',
    cta: 'Отвори стоката'
  },
  delivery: {
    title: 'Доставка',
    iconSvg: '<svg viewBox="0 0 24 24" style="stroke:hsl(38 80% 50%);"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
    body: 'Получавай <b>нова стока</b>. Скенирай за бърз вход или снимай фактурата (AI чете 30 артикула за 30 секунди). Сравнява с поръчката.',
    voice: 'Снимай фактура от Иватекс',
    cta: 'Нова доставка'
  },
  order: {
    title: 'Поръчка',
    iconSvg: '<svg viewBox="0 0 24 24" style="stroke:hsl(280 60% 50%);"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>',
    body: 'Управлявай <b>поръчки към доставчици</b>. AI препоръчва какво да поръчаш базирано на продажбите и минимумите. Изпращай по email или WhatsApp.',
    voice: 'Какво да поръчам от Nike',
    cta: 'Нова поръчка'
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
  document.getElementById('infoOverlay').classList.add('active');
}
function closeInfo() {
  document.getElementById('infoOverlay').classList.remove('active');
}

function wfcSetRange(r) {
  var card = document.querySelector('.wfc');
  if (card) card.setAttribute('data-range', r);
}

function lbToggleCard(e, row) {
  if (e.target.closest('.lb-action') || e.target.closest('.lb-fb-btn')) return;
  row.parentElement.classList.toggle('expanded');
}

function syncThemeIcons() {
  var t = document.documentElement.getAttribute('data-theme') || 'light';
  document.getElementById('themeIconSun').style.display  = (t === 'dark') ? 'block' : 'none';
  document.getElementById('themeIconMoon').style.display = (t === 'dark') ? 'none'  : 'block';
}
window.rmsToggleTheme = function () {
  var cur = document.documentElement.getAttribute('data-theme') || 'light';
  var nxt = (cur === 'light') ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', nxt);
  try { localStorage.setItem('rms_theme', nxt); } catch (_) {}
  syncThemeIcons();
};
syncThemeIcons();

// ═══════════════════════════════════════════════════════
// THEME TOGGLE — default DARK, user can switch to LIGHT
// Persisted in localStorage['rms_theme']
// ═══════════════════════════════════════════════════════
(function initTheme(){
    try{
        var saved=localStorage.getItem('rms_theme');
        if(saved==='light'){
            document.documentElement.setAttribute('data-theme','light');
        }
        document.addEventListener('DOMContentLoaded',function(){
            var sun=document.getElementById('themeIconSun');
            var moon=document.getElementById('themeIconMoon');
            if(!sun||!moon)return;
            var isLight=document.documentElement.getAttribute('data-theme')==='light';
            if(isLight){sun.style.display='';moon.style.display='none'}
            else{sun.style.display='none';moon.style.display=''}
        });
    }catch(_){}
})();
function toggleTheme(){
    var cur=document.documentElement.getAttribute('data-theme')||'dark';
    var nxt=(cur==='light')?'dark':'light';
    document.documentElement.setAttribute('data-theme', nxt);
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

let _revAnimatedOnce = false;
function updateRevenue() {
    const d = P[curPeriod];
    const val = curMode === 'rev' ? d.rev : d.profit;
    const pct = curMode === 'rev' ? d.cmp_rev : d.cmp_prof;
    const sub = curMode === 'rev' ? d.sub_rev : d.sub_prof;

    const revEl = $('revNum');
    if (!_revAnimatedOnce && typeof animateCountUp === 'function') {
        // S87.ANIMATIONS v2.1 — spacious count-up (1.2s delay, 1.8s duration)
        _revAnimatedOnce = true;
        revEl.dataset.count = String(Math.round(val));
        setTimeout(() => animateCountUp(revEl, Math.round(val), 1800), 1200);
    } else {
        revEl.textContent = fmt(val);
    }
    const pctEl = $('revPct');
    pctEl.textContent = (pct >= 0 ? '+' : '') + pct + '%';
    pctEl.className = 's82-dash-pct ' + (pct > 0 ? '' : (pct < 0 ? 'neg' : 'zero'));

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
    // Period pills are the first 4 .rev-pill buttons (today/7d/30d/365d).
    // Mode pills (Оборот/Печалба) carry an id (modeRev/modeProfit) — exclude them.
    document.querySelectorAll('.rev-pill').forEach(p => {
        if (p.id === 'modeRev' || p.id === 'modeProfit') return;
        p.classList.toggle('active', p === el);
    });
    updateRevenue();
    vib(7);
}

// S82.VISUAL — Life Board card helpers (visual-only, no backend)
function lbSelectFeedback(e, btn){
    e.stopPropagation();
    var card = btn.closest('.lb-card'); if (!card) return;
    card.querySelectorAll('.lb-fb-btn').forEach(function(b){ b.classList.remove('selected'); });
    btn.classList.add('selected');
    if (navigator.vibrate) navigator.vibrate(8);
}
function lbDismissCard(e, btn){
    e.stopPropagation();
    var card = btn.closest('.lb-card'); if (!card) return;
    card.style.transition = 'opacity .25s, transform .25s';
    card.style.opacity = '0';
    card.style.transform = 'translateX(60px)';
    setTimeout(function(){ card.style.display = 'none'; }, 260);
    if (navigator.vibrate) navigator.vibrate(8);
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

document.querySelectorAll(['.sig-card','.sig-more','.nav-tab','.header-icon-btn','.store-sel','.health-link','.health-info','.top-pill','.rev-pill'].join(',')).forEach(el => {
    el.addEventListener('click', () => vib(6));
});

// ═══════════════════════════════════════════════════════
// S87.ANIMATIONS v2 — count-up + spring-tap touchend
// ═══════════════════════════════════════════════════════
function animateCountUp(el, finalValue, duration) {
    duration = duration || 1800;
    if (!el || isNaN(finalValue)) return;
    const start = 0;
    const startTime = performance.now();
    el.classList.add('animating');
    function tick(now) {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
        const current = Math.floor(start + (finalValue - start) * eased);
        el.textContent = current.toLocaleString('bg-BG');
        if (progress < 1) {
            requestAnimationFrame(tick);
        } else {
            el.classList.remove('animating');
            el.textContent = finalValue.toLocaleString('bg-BG');
        }
    }
    requestAnimationFrame(tick);
}

// Apply count-up на всички [data-count] elements after page entrance
window.addEventListener('load', () => {
    setTimeout(() => {
        document.querySelectorAll('.count-up[data-count]').forEach(el => {
            const v = parseInt(el.dataset.count, 10);
            if (!isNaN(v) && v > 0 && el.id !== 'revNum') {
                // revNum се анимира от updateRevenue() (динамична стойност)
                animateCountUp(el, v, 1800);
            }
        });
    }, 1200);
});

// ═══════════════════════════════════════════════════════
// S87.ANIMATIONS v3 FULL PACK — Groups 1-6 JS hooks
// DESIGN_SYSTEM § O.14-O.19
// ═══════════════════════════════════════════════════════

// GROUP 1 — Scroll-driven reveal + sticky-header blur (§O.14)
(function s87v3_scroll(){
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
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
    if (!('IntersectionObserver' in window)) return;
    var obs = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
            if (entry.isIntersecting) {
                entry.target.style.animation = 'scrollIn 0.7s cubic-bezier(0.34,1.8,0.64,1) both';
                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
    window.addEventListener('load', function(){
        // Auto-tag .lb-card from index 4+ as scroll-reveal (below the fold on phone)
        var cards = document.querySelectorAll('.lb-card');
        /* S136.ALIGN: scroll-reveal disabled */
        document.querySelectorAll('.scroll-reveal').forEach(function(el){
            obs.observe(el);
        });
    });
})();

// GROUP 2 — State transitions: addMessageWithAnimation hook (§O.15)
function addMessageWithAnimation(role, txt){
    // role: 'ai' | 'user'
    var g = document.createElement('div');
    g.className = 'msg-group';
    var t = new Date().toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit' });
    if (role === 'user') {
        g.innerHTML = '<div class="msg-meta right">' + t + '</div>'
                    + '<div class="msg-user new">' + esc(txt) + '</div>';
    } else {
        g.innerHTML = '<div class="msg-meta">'
                    + '<svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'
                    + 'AI · ' + t + '</div>'
                    + '<div class="msg-ai new">' + esc(txt) + '</div>';
    }
    var typing = document.getElementById('typing');
    var box = document.getElementById('chatMessages');
    if (box && typing) box.insertBefore(g, typing);
    else if (box) box.appendChild(g);
    if (typeof scrollChatBottom === 'function') scrollChatBottom();
    return g;
}

// GROUP 3 — Live data: smooth number transition + badge bounce (§O.16)
function animateNumberChange(el, newValue, duration){
    if (!el) return;
    duration = duration || 600;
    var oldValue = parseInt(String(el.textContent).replace(/\D/g, ''), 10) || 0;
    if (oldValue === newValue) return;
    var startTime = performance.now();
    function tick(now){
        var progress = Math.min((now - startTime) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        var current = Math.floor(oldValue + (newValue - oldValue) * eased);
        el.textContent = current.toLocaleString('bg-BG');
        if (progress < 1) requestAnimationFrame(tick);
        else el.textContent = newValue.toLocaleString('bg-BG');
    }
    requestAnimationFrame(tick);
}
function bounceBadge(el){
    if (!el) return;
    el.classList.remove('bounce');
    void el.offsetWidth;
    el.classList.add('bounce');
    setTimeout(function(){ el.classList.remove('bounce'); }, 520);
}

// GROUP 4 — Micro-animations: graceful overlay close + toast hide + elastic pull (§O.17)
(function s87v3_overlayClose(){
    // Wrap close functions so panel uses 'closing' choreography before unmount
    function wrapClose(name, panelId){
        var orig = window[name];
        if (typeof orig !== 'function') return;
        window[name] = function(){
            var args = arguments;
            var p = document.getElementById(panelId);
            if (p && p.classList.contains('open') && !p.classList.contains('closing') &&
                !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
                p.classList.add('closing');
                setTimeout(function(){
                    p.classList.remove('closing');
                    orig.apply(window, args);
                }, 600);
            } else {
                orig.apply(window, args);
            }
        };
    }
    // Defer wrapping until DOM ready (orig functions defined earlier in this script)
    if (document.readyState !== 'loading') {
        wrapClose('closeChat', 'chatPanel');
        wrapClose('closeSignal', 'sigPanel');
        wrapClose('closeSignalBrowser', 'brPanel');
    } else {
        document.addEventListener('DOMContentLoaded', function(){
            wrapClose('closeChat', 'chatPanel');
            wrapClose('closeSignal', 'sigPanel');
            wrapClose('closeSignalBrowser', 'brPanel');
        });
    }
})();

// Toast graceful hide (overrides showToast's removal step)
(function s87v3_toastHide(){
    var origToast = window.showToast;
    if (typeof origToast !== 'function') return;
    window.showToast = function(m){
        var t = document.getElementById('toast');
        if (!t) { origToast(m); return; }
        t.classList.remove('hiding');
        t.textContent = m;
        t.classList.add('show');
        clearTimeout(t._s87Timer);
        t._s87Timer = setTimeout(function(){
            t.classList.remove('show');
            t.classList.add('hiding');
            setTimeout(function(){ t.classList.remove('hiding'); }, 320);
        }, 2800);
    };
})();

// GROUP 5 — Context changes: changeContext + period pill re-trigger (§O.18)
function changeContext(targetUrl){
    var app = document.querySelector('.app');
    if (!app || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
        window.location.href = targetUrl;
        return;
    }
    app.classList.add('context-out');
    setTimeout(function(){ window.location.href = targetUrl; }, 250);
}

// Период pill change → re-trigger num animation на dashboard num (using v3 animateNumberChange)
(function s87v3_periodSmoothNum(){
    var origSetPeriod = window.setPeriod;
    if (typeof origSetPeriod !== 'function') return;
    window.setPeriod = function(period, el){
        origSetPeriod(period, el);
        // After updateRevenue() runs, smooth-tween revNum from old to new (only after first count-up)
        try {
            if (typeof _revAnimatedOnce !== 'undefined' && _revAnimatedOnce && typeof P !== 'undefined') {
                var d = P[period];
                if (d) {
                    var val = (typeof curMode !== 'undefined' && curMode === 'profit') ? d.profit : d.rev;
                    var revEl = document.getElementById('revNum');
                    if (revEl) animateNumberChange(revEl, Math.round(val), 700);
                }
            }
        } catch(_) {}
    };
})();

// GROUP 6 — AI magic moments helper: tag inserted top-pill as .new for slide-in glow (§O.19)
function spawnTopPill(html){
    var strip = document.querySelector('.top-strip');
    if (!strip) return null;
    var tmp = document.createElement('div');
    tmp.innerHTML = html.trim();
    var el = tmp.firstChild;
    if (!el) return null;
    el.classList.add('top-pill', 'new');
    strip.insertBefore(el, strip.firstChild);
    setTimeout(function(){ el.classList.remove('new'); }, 2200);
    return el;
}

// Spring tap release — overshoot animation на touchend
(function attachSpringRelease(){
    const SEL = ['.spring-tap', '.briefing-btn-primary', '.briefing-btn-secondary',
                 '.sig-btn-primary', '.sig-btn-secondary',
                 '.nav-tab', '.header-icon-btn', '.rms-icon-btn',
                 '.top-pill', '.rev-pill', '.s82-dash-pill',
                 '.sig-card', '.sig-more',
                 '.lb-action', '.lb-dismiss', '.lb-fb-btn',
                 '.chat-mic', '.chat-send',
                 '.ov-back', '.ov-close'].join(',');
    const handler = (el) => {
        el.classList.remove('released');
        void el.offsetWidth; // force reflow
        el.classList.add('released');
        setTimeout(() => el.classList.remove('released'), 400);
    };
    document.querySelectorAll(SEL).forEach(el => {
        el.addEventListener('touchend', () => handler(el), { passive: true });
        el.addEventListener('mouseup', () => handler(el));
    });
})();
</script>

<?php include __DIR__ . '/partials/shell-scripts.php'; ?>

<!-- design-kit v1.1: theme-toggle MUST be before palette -->
<script src="/design-kit/theme-toggle.js?v=<?= @filemtime(__DIR__.'/design-kit/theme-toggle.js') ?: 1 ?>"></script>
<script src="/design-kit/palette.js?v=<?= @filemtime(__DIR__.'/design-kit/palette.js') ?: 1 ?>"></script>

</body>
</html>
