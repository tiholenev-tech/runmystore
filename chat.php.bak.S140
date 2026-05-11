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
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Начало — RunMyStore.ai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>
<link rel="stylesheet" href="/design-kit/tokens.css?v=<?= @filemtime(__DIR__.'/design-kit/tokens.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components-base.css?v=<?= @filemtime(__DIR__.'/design-kit/components-base.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components.css?v=<?= @filemtime(__DIR__.'/design-kit/components.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/light-theme.css?v=<?= @filemtime(__DIR__.'/design-kit/light-theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/header-palette.css?v=<?= @filemtime(__DIR__.'/design-kit/header-palette.css') ?: 1 ?>">
</head>
<body class="has-rms-shell mode-detailed">

<div class="app card-stagger" id="app">

    <!-- ═══════════════════════════════════════════ -->
    <!-- HEADER — design-kit v1.1 partial (Тип А)    -->
    <!-- ═══════════════════════════════════════════ -->
    <?php include __DIR__ . '/design-kit/partial-header.html'; ?>

    <!-- S140 REDESIGN — скрий legacy hue sliders (row2 от partial-header.html). -->
    <!-- Заменяме ги със стандартния subbar според LAYOUT_SHELL_LAW v1.1 §1B.   -->
    <style>
        .rms-header-row2 { display: none !important; }
        /* S140: header иконки малко по-компактни (печат/⚙/⤴/☀ не излизат от екрана) */
        .rms-header .rms-icon-btn { width: 22px; height: 22px; }
        .rms-header .rms-icon-btn svg { width: 9px; height: 9px; }
        .rms-header .rms-header-icons { gap: 3px; }
        /* Subbar (Тип А + разширен, по P11 еталона) */
        .rms-subbar {
            position: sticky; top: 56px; z-index: 49;
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px;
            background: var(--bg-main, #fff);
            border-bottom: 1px solid rgba(99,102,241,0.10);
        }
        [data-theme="dark"] .rms-subbar { background: hsl(220 25% 6%); border-bottom-color: rgba(255,255,255,0.06); }
        /* Native select стилизиран като pill — работи на всички браузъри */
        .rms-store-pill {
            position: relative;
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 28px 6px 10px;
            border-radius: 999px;
            background: transparent;
            border: 1px solid rgba(99,102,241,0.20);
            color: var(--text-primary, #1e1e2f);
            font: 600 12px/1 Montserrat, sans-serif;
            cursor: pointer;
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 12px 12px;
        }
        [data-theme="dark"] .rms-store-pill { color: #e8e9f0; border-color: rgba(255,255,255,0.12); }
        .rms-store-pill:focus { outline: 2px solid hsl(238 78% 60% / .35); outline-offset: 1px; }
        .rms-store-pill[disabled] { background-image: none; padding-right: 10px; cursor: default; opacity: .85; }
        .subbar-where { font: 700 11px/1 Montserrat, sans-serif; letter-spacing: .08em; color: var(--text-secondary, #64748b); text-transform: uppercase; }
        .lb-mode-toggle {
            margin-left: auto;
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 999px;
            color: var(--text-primary, #1e1e2f); text-decoration: none;
            font: 600 12px/1 Montserrat, sans-serif;
            border: 1px solid rgba(99,102,241,0.20);
        }
        [data-theme="dark"] .lb-mode-toggle { color: #e8e9f0; border-color: rgba(255,255,255,0.12); }
        .lb-mode-toggle svg { width: 12px; height: 12px; fill: none; stroke: currentColor; stroke-width: 2; }
    </style>

    <!-- ═══════════════════════════════════════════ -->
    <!-- SUBBAR (Тип А + разширен, P11 еталон)       -->
    <!-- LAYOUT_SHELL_LAW v1.1 §1B                   -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="rms-subbar">
        <?php if (count($all_stores) > 1): ?>
        <select class="rms-store-pill" aria-label="Смени обект" onchange="location.href='?store='+this.value">
            <?php foreach ($all_stores as $st): ?>
            <option value="<?= (int)$st['id'] ?>" <?= $st['id']==$store_id?'selected':'' ?>><?= htmlspecialchars($st['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php else: ?>
        <span class="rms-store-pill" aria-label="Обект" tabindex="-1" role="text" style="cursor:default; background-image:none; padding-right:10px;">
            <?= htmlspecialchars($store_name) ?>
        </span>
        <?php endif; ?>
        <span class="subbar-where">AI ЧАТ</span>
        <a class="lb-mode-toggle" href="/life-board.php" title="Лесен режим">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            <span>Лесен</span>
        </a>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- S82.VISUAL — DASHBOARD GLASS CARD (qd)      -->
    <!-- ═══════════════════════════════════════════ -->
    <?php
        $cmp_today = (int)cmpPct($d0['rev'], $d0p['rev']);
        $cmp_class = $cmp_today > 0 ? '' : ($cmp_today < 0 ? 'neg' : 'zero');
        $cmp_sign  = $cmp_today > 0 ? '+' : '';
    ?>
    <div class="glass sm s82-dash qd">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="s82-dash-top">
            <span class="s82-dash-period-label"><span id="revLabel">ДНЕС</span> · <?= htmlspecialchars($store_name) ?></span>
            <!-- S140: магазин dropdown премахнат — вече е в subbar (LAYOUT_SHELL_LAW v1.1 §1B) -->
        </div>
        <div class="s82-dash-numrow">
            <span class="s82-dash-num count-up" id="revNum" data-count="0">0</span>
            <span class="s82-dash-cur"><?= $cs ?></span>
            <span class="s82-dash-pct <?= $cmp_class ?>" id="revPct"><?= $cmp_sign . $cmp_today ?>%</span>
            <span class="s82-dash-cur" id="revVs" style="margin-left:4px"></span>
        </div>
        <div class="s82-dash-meta">
            <span id="revMeta">0 продажби</span>
            <span id="revCmp" style="display:inline"></span>
        </div>
        <?php if ($role === 'owner' && $confidence_pct < 100): ?>
        <div class="conf-warn" id="confWarn" style="display:none">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            Данни за <?= $confidence_pct ?>% от артикулите (<?= $with_cost ?>/<?= $total_products ?>)
        </div>
        <?php endif; ?>
        <div class="s82-dash-pills">
            <button type="button" class="s82-dash-pill rev-pill active" onclick="setPeriod('today',this)">Днес</button>
            <button type="button" class="s82-dash-pill rev-pill" onclick="setPeriod('7d',this)">7 дни</button>
            <button type="button" class="s82-dash-pill rev-pill" onclick="setPeriod('30d',this)">30 дни</button>
            <button type="button" class="s82-dash-pill rev-pill" onclick="setPeriod('365d',this)">365 дни</button>
            <?php if ($role === 'owner'): ?>
            <span class="s82-dash-divider"></span>
            <button type="button" class="s82-dash-pill rev-pill active" id="modeRev" onclick="setMode('rev')">Оборот</button>
            <button type="button" class="s82-dash-pill rev-pill" id="modeProfit" onclick="setMode('profit')">Печалба</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- S87.HEALTH.RESTORE — STORE HEALTH BAR (AI Точност) -->
    <!-- DESIGN_SYSTEM § D.5 + BIBLE §25                 -->
    <!-- ═══════════════════════════════════════════ -->
    <?php $h_color = $health >= 80 ? '#4ade80' : ($health >= 50 ? '#fbbf24' : '#f87171'); ?>
    <div class="glass sm health">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <span class="health-lbl">Точност</span>
        <div class="health-track">
            <div class="health-fill" style="width:<?= (int)$health ?>%"></div>
        </div>
        <span class="health-pct" style="color:<?= $h_color ?>"><?= (int)$health ?>%</span>
        <span class="health-link" onclick="openChatQ('Как да подобря AI точността?')">Преброй &rarr;</span>
        <span class="health-info" onclick="document.querySelector('.health-tooltip').classList.toggle('open')" aria-label="Какво е AI точност?">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        </span>
    </div>
    <div class="health-tooltip">
        <b>Какво е AI Точност?</b><br>
        Колко добре AI познава магазина ти. Расте когато:<br>
        &bull; Въведеш <b>доставни цени</b> на артикулите<br>
        &bull; <b>Преброиш</b> стоката по рафтовете<br>
        &bull; Получиш <b>доставка</b> с фактура<br><br>
        По-висока точност = по-умни съвети от AI.
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- S82.VISUAL — WEATHER GLASS CARD (qw)        -->
    <!-- ═══════════════════════════════════════════ -->
    <?php if ($weather_today): ?>
    <div class="glass sm s82-weather qw">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="s82-weather-top">
            <div class="s82-weather-left">
                <div class="s82-weather-icon"><svg viewBox="0 0 24 24"><?= wmoSvg((int)$weather_today['weather_code']) ?></svg></div>
                <span class="s82-weather-cond"><?= wmoText((int)$weather_today['weather_code']) ?></span>
            </div>
            <span class="s82-weather-temp"><?= round($weather_today['temp_max']) ?>°</span>
        </div>
        <div class="s82-weather-meta"><?= round($weather_today['temp_min']) ?>° / <?= round($weather_today['temp_max']) ?>° · Дъжд <?= (int)$weather_today['precipitation_prob'] ?>%</div>
        <?php if ($weather_suggestion): ?>
        <div class="s82-weather-sug"><?= htmlspecialchars($weather_suggestion) ?></div>
        <?php endif; ?>
        <?php if (count($weather_week) >= 7): ?>
        <div class="s82-weather-week">
            <?php foreach (array_slice($weather_week, 1, 7) as $wd):
                $dname = $bg_days_full[(int)date('w', strtotime($wd['forecast_date']))];
                $rain = (int)$wd['precipitation_prob'];
                $rain_cls = $rain > 50 ? 'wet' : 'dry';
            ?>
            <div class="s82-weather-day">
                <div class="s82-weather-day-name"><?= $dname ?></div>
                <div class="s82-weather-day-temp"><?= round($wd['temp_max']) ?>°</div>
                <div class="s82-weather-day-rain <?= $rain_cls ?>"><?= $rain ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════ -->
    <!-- S82.STUDIO.NAV — AI Studio entry (KEEP)    -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="ai-studio-row">
      <a href="/ai-studio.php" class="glass sm ai-studio-btn" aria-label="AI Studio">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <span class="as-icon">
          <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </span>
        <span class="as-text">
          <span class="as-label">AI Studio</span>
          <?php if ($ai_studio_count > 0): ?>
          <span class="as-sub">· <?= $ai_studio_count ?> чакат</span>
          <?php else: ?>
          <span class="as-sub">· каталог &amp; снимки</span>
          <?php endif; ?>
        </span>
        <?php if ($ai_studio_count > 0): ?>
        <span class="ai-studio-badge"><?= $ai_studio_count > 99 ? '99+' : $ai_studio_count ?></span>
        <?php endif; ?>
      </a>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- S82.VISUAL — LIFE BOARD (6 fundamental q)  -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="lb-header">
        <div class="lb-title">
            <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <span class="lb-title-text">Life Board</span>
        </div>
        <span class="lb-count"><?= count($briefing) ?> теми · <?= date('H:i') ?></span>
    </div>

    <?php if (!empty($briefing)):
        $fq_meta = [
            'loss'       => ['q'=>'q1', 'emoji'=>'🔴', 'name'=>'Какво губиш'],
            'loss_cause' => ['q'=>'q2', 'emoji'=>'🟣', 'name'=>'От какво губиш'],
            'gain'       => ['q'=>'q3', 'emoji'=>'🟢', 'name'=>'Какво печелиш'],
            'gain_cause' => ['q'=>'q4', 'emoji'=>'💎', 'name'=>'От какво печелиш'],
            'order'      => ['q'=>'q5', 'emoji'=>'🟡', 'name'=>'Поръчай'],
            'anti_order' => ['q'=>'q6', 'emoji'=>'⚪', 'name'=>'НЕ поръчвай'],
        ];
        $topic_to_idx = [];
        foreach ($all_insights_for_js as $idx => $v) {
            $topic_to_idx[$v['topicId']] = $idx;
        }
        foreach ($briefing as $ins):
            $fq = $ins['fundamental_question'];
            $meta = $fq_meta[$fq] ?? ['q'=>'q3','emoji'=>'•','name'=>''];
            $action = insightAction($ins);
            $idx_in_all = $topic_to_idx[$ins['topic_id']] ?? 0;
            $title_js = htmlspecialchars(addslashes($ins['title']), ENT_QUOTES);
    ?>
    <div class="glass sm lb-card <?= $meta['q'] ?>" data-topic="<?= htmlspecialchars($ins['topic_id'], ENT_QUOTES) ?>">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="lb-top">
            <span class="lb-fq-tag"><span class="lb-fq-emoji"><?= $meta['emoji'] ?></span> <?= $meta['name'] ?></span>
            <button type="button" class="lb-dismiss" aria-label="Скрий" onclick="lbDismissCard(event,this)">×</button>
        </div>
        <div class="lb-card-title"><?= htmlspecialchars($ins['title']) ?></div>
        <?php if (!empty($ins['detail_text'])): ?>
        <div class="lb-body"><?= htmlspecialchars($ins['detail_text']) ?></div>
        <?php endif; ?>
        <div class="lb-actions">
            <button type="button" class="lb-action" onclick="openChatQ('<?= $title_js ?>')">Защо?</button>
            <button type="button" class="lb-action" onclick="openSignalDetail(<?= $idx_in_all ?>)">Покажи</button>
            <?php if ($action['type'] === 'deeplink' && $action['url']): ?>
            <a class="lb-action primary" href="<?= htmlspecialchars($action['url']) ?>"><?= htmlspecialchars($action['label']) ?> →</a>
            <?php elseif ($action['type'] === 'order_draft'): ?>
            <button type="button" class="lb-action primary" onclick="addToOrderDraft(<?= $idx_in_all ?>)"><?= htmlspecialchars($action['label']) ?> →</button>
            <?php else: ?>
            <button type="button" class="lb-action primary" onclick="openChatQ('<?= $title_js ?>')"><?= htmlspecialchars($action['label']) ?> →</button>
            <?php endif; ?>
        </div>
        <div class="lb-feedback">
            <span class="lb-fb-label">Полезно?</span>
            <button type="button" class="lb-fb-btn" data-fb="up" onclick="lbSelectFeedback(event,this)" aria-label="Полезно">👍</button>
            <button type="button" class="lb-fb-btn" data-fb="down" onclick="lbSelectFeedback(event,this)" aria-label="Безполезно">👎</button>
            <button type="button" class="lb-fb-btn" data-fb="hmm" onclick="lbSelectFeedback(event,this)" aria-label="Неясно">🤔</button>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($remaining > 0): ?>
    <div class="lb-see-more"><button type="button" onclick="openSignalBrowser()">Виж още <?= $remaining ?> теми →</button></div>
    <?php endif; ?>

    <?php elseif (!empty($ghost_pills)): ?>
    <div class="glass sm lb-silent q3">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="lb-silent-icon">✨</div>
        <div class="lb-silent-text"><?= htmlspecialchars($greeting) ?></div>
        <div class="lb-silent-sub">AI има съвет за теб — включи PRO за пълен brief.</div>
        <div style="margin-top:10px"><button type="button" class="lb-action primary" onclick="showToast('Включи PRO за AI съвети')" style="padding:8px 18px">Включи PRO</button></div>
    </div>

    <?php else: ?>
    <div class="glass sm lb-silent q3">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="lb-silent-icon">🌿</div>
        <div class="lb-silent-text">Всичко върви добре днес</div>
        <div class="lb-silent-sub"><?= htmlspecialchars($greeting) ?> Няма нищо спешно — попитай каквото искаш.</div>
    </div>
    <?php endif; ?>

    <div style="height:20px"></div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- INPUT BAR — moved to partials/chat-input-bar.php       -->
<!-- ═══════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/partials/chat-input-bar.php'; ?>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- BOTTOM NAV — orb стил по P11 еталона (S140 redesign)   -->
<!-- LAYOUT_SHELL_LAW v1.1 §2 — 4 таба, само 1 .active     -->
<!-- ═══════════════════════════════════════════════════════ -->
<style>
.rms-bottom-nav {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 48;
    display: grid; grid-template-columns: repeat(4, 1fr);
    padding: 8px 4px calc(8px + env(safe-area-inset-bottom));
    background: var(--bg-main, #fff);
    border-top: 1px solid rgba(99,102,241,0.10);
    box-shadow: 0 -4px 16px rgba(0,0,0,0.04);
}
[data-theme="dark"] .rms-bottom-nav { background: hsl(220 25% 5%); border-top-color: rgba(255,255,255,0.06); box-shadow: 0 -4px 16px rgba(0,0,0,0.4); }
.rms-nav-tab {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    padding: 4px 0; text-decoration: none;
    color: var(--text-muted, #94a3b8);
    font: 700 10px/1 Montserrat, sans-serif; letter-spacing: .04em;
}
.rms-nav-tab .nav-orb {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: rgba(99,102,241,0.06);
    border: 1px solid rgba(99,102,241,0.10);
    transition: all .2s ease;
}
.rms-nav-tab .nav-orb svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.rms-nav-tab.active { color: hsl(238 78% 60%); }
.rms-nav-tab.active .nav-orb {
    background: radial-gradient(circle at 30% 30%, hsl(238 78% 70%), hsl(238 78% 50%));
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(99,102,241,0.35), inset 0 1px 0 rgba(255,255,255,0.25);
    animation: orbSpin 5s linear infinite;
}
.rms-nav-tab.active .nav-orb svg { stroke: #fff; }
@keyframes orbSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .rms-nav-tab.active .nav-orb { animation: none; } }
</style>
<nav class="rms-bottom-nav" id="rmsBottomNav">
    <a href="chat.php" class="rms-nav-tab active" aria-label="AI">
        <span class="nav-orb">
            <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </span>
        <span>AI</span>
    </a>
    <a href="warehouse.php" class="rms-nav-tab" aria-label="Склад">
        <span class="nav-orb">
            <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.7l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.7l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.3 7 12 12 20.7 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg>
        </span>
        <span>Склад</span>
    </a>
    <a href="stats.php" class="rms-nav-tab" aria-label="Справки">
        <span class="nav-orb">
            <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        </span>
        <span>Справки</span>
    </a>
    <a href="sale.php" class="rms-nav-tab" aria-label="Продажба">
        <span class="nav-orb">
            <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        </span>
        <span>Продажба</span>
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
    if(nxt==='light'){document.documentElement.setAttribute('data-theme','light')}
    else{document.documentElement.removeAttribute('data-theme')}
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
    // S140: Ако е дошъл с ?q=... от chat-v2.php card click → отвори чата и попитай
    try {
        const params = new URLSearchParams(location.search);
        const q = params.get('q');
        if (q) {
            setTimeout(() => openChatQ(q), 350);
            // Изчисти URL-а да не се повтаря при refresh
            history.replaceState({}, '', 'chat.php');
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
        for (var i = 4; i < cards.length; i++) cards[i].classList.add('scroll-reveal');
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
