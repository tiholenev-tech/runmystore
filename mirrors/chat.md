<?php
/**
 * chat.php — RunMyStore.ai Подробен режим (S133 P11 rewrite)
 *
 * Visual: mockups/P11_detailed_mode.html (1:1 ground truth) — P10 base + filter pills,
 *         categorized lb-cards, bottom nav.
 * Logic: 100% preserved from S132 (3 overlays, Revenue dashboard, Health bar, voice,
 *        AI insights, proactive pills, chat-send.php integration).
 *
 * NEW BEHAVIOR (S133):
 *   1. $_SESSION['ui_mode'] bootstrap (seller→simple, else→detailed)
 *   2. Render guard: simple back-arrow header + skip bottom nav когато
 *      ?from=lifeboard или ui_mode==simple (Лесен режим е навигирал тук)
 */
session_start();

// ─── NEW BEHAVIOR (S133): UI mode bootstrap ───
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

// ─── P11 helpers (presentation only — NEW for WFC card + filter pills) ───
function wfcDayClass(int $code): string {
    if ($code <= 1)  return 'sunny';
    if ($code <= 3)  return 'partly';
    if ($code <= 48) return 'cloudy';
    if ($code <= 67) return 'rain';
    if ($code <= 77) return 'rain';
    return 'storm';
}
function wfcDayIcon(int $code): string {
    switch (wfcDayClass($code)) {
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
function wfcDayName(string $date_str, bool $is_today): string {
    if ($is_today) return 'Днес';
    $names = ['Нд','Пн','Вт','Ср','Чт','Пт','Сб'];
    return $names[(int)date('w', strtotime($date_str))];
}
// Append from=lifeboard if upstream came from lifeboard (preserves render-guard breadcrumb)
function chatLink(?string $url): string {
    if ($url === null || $url === '') return '';
    $isFrom = isset($_GET['from']) && $_GET['from'] === 'lifeboard';
    if (!$isFrom) return $url;
    if (strpos($url, 'from=lifeboard') !== false) return $url;
    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    return $url . $sep . 'from=lifeboard';
}
// Map insight category → Bulgarian module label for filter pill / lb-fq-tag-mini prefix
function fqModuleLabel(?string $cat, ?string $module): string {
    if (!$cat) $cat = '';
    if (!$module) $module = '';
    $by_cat = [
        'profit'=>'ФИНАНСИ','price'=>'ФИНАНСИ','price_change'=>'ФИНАНСИ','cash'=>'ФИНАНСИ','tax'=>'ФИНАНСИ',
        'wh'=>'СКЛАД','data_quality'=>'СКЛАД',
        'xfer'=>'ТРАНСФЕРИ',
        'promo'=>'ПРОДАЖБИ','fashion'=>'ПРОДАЖБИ','shoes'=>'ПРОДАЖБИ','acc'=>'ПРОДАЖБИ',
        'lingerie'=>'ПРОДАЖБИ','sport'=>'ПРОДАЖБИ','size'=>'ПРОДАЖБИ','new'=>'ПРОДАЖБИ',
        'expense'=>'РАЗХОДИ',
    ];
    if (isset($by_cat[$cat])) return $by_cat[$cat];
    if ($module === 'warehouse') return 'СКЛАД';
    if ($module === 'products') return 'ПРОДУКТИ';
    if ($module === 'stats') return 'ФИНАНСИ';
    return 'ОБЩО';
}

$plan_label = strtoupper($plan);

// ══════════════════════════════════════════════
// S82.STUDIO.NAV — AI Studio entry pending count
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
    if (!empty($ins['action_label'])) {
        return [
            'label' => $ins['action_label'],
            'type'  => $ins['action_type'] ?? 'chat',
            'url'   => $ins['action_url'] ?? null,
            'data'  => $ins['action_data'] ? json_decode($ins['action_data'], true) : null,
        ];
    }
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

// ──────────────────────────────────────────────
// NEW BEHAVIOR (S133) — render guard
// ──────────────────────────────────────────────
$isFromLifeboard = isset($_GET['from']) && $_GET['from'] === 'lifeboard';
$isSimpleMode = ($_SESSION['ui_mode'] ?? 'detailed') === 'simple';
$renderSimpleHeader = $isFromLifeboard || $isSimpleMode;

// SVG icon per fq for P11 collapsed lb-card lb-emoji-orb
$fq_svg_p11 = [
    'q1' => '<svg viewBox="0 0 24 24"><polygon points="10.29 3.86 1.82 18 22.18 18 13.71 3.86 10.29 3.86"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    'q2' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    'q3' => '<svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
    'q4' => '<svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
    'q5' => '<svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
    'q6' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
];

?><!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Подробен режим — RunMyStore.ai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>
<link rel="stylesheet" href="/design-kit/tokens.css?v=<?= @filemtime(__DIR__.'/design-kit/tokens.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components-base.css?v=<?= @filemtime(__DIR__.'/design-kit/components-base.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components.css?v=<?= @filemtime(__DIR__.'/design-kit/components.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/light-theme.css?v=<?= @filemtime(__DIR__.'/design-kit/light-theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/header-palette.css?v=<?= @filemtime(__DIR__.'/design-kit/header-palette.css') ?: 1 ?>">
<style>
/* ════════════════════════════════════════════════════════════════════
   chat.php P11 inline additions — only NEW classes not в design-kit
   (cell, wfc, help-card, fp-row, lb-collapsed, info-overlay, etc.)
   Preserves all design-kit styles for .glass, .s82-dash, .ov-panel,
   .lb-card, .health, etc. — those continue to work as before.
   ════════════════════════════════════════════════════════════════════ */

/* Render-guard simple header (S133) */
.rms-simple-header {
  position: sticky; top: 0; z-index: 50;
  height: 56px; padding: 0 16px;
  display: flex; align-items: center;
  border-bottom: 1px solid var(--border-color, transparent);
  padding-top: env(safe-area-inset-top, 0);
}
[data-theme="light"] .rms-simple-header,
:root:not([data-theme]) .rms-simple-header { background: var(--bg-main); box-shadow: 0 4px 12px rgba(163,177,198,0.15); }
[data-theme="dark"] .rms-simple-header { background: hsl(220 25% 4.8% / 0.85); backdrop-filter: blur(16px); }
.rms-simple-back {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 8px 14px;
  border-radius: var(--radius-pill);
  font-family: 'DM Mono', ui-monospace, monospace; font-size: 11px; font-weight: 700;
  letter-spacing: 0.04em; color: var(--text); text-decoration: none;
}
[data-theme="light"] .rms-simple-back, :root:not([data-theme]) .rms-simple-back {
  background: var(--surface); box-shadow: var(--shadow-card-sm);
}
[data-theme="dark"] .rms-simple-back { background: hsl(220 25% 8% / 0.7); backdrop-filter: blur(8px); }
.rms-simple-back svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.5; }

/* P11 TOP ROW (Днес glance + Weather glance) */
.p11-top-row {
  display: grid; grid-template-columns: 1.4fr 1fr; gap: 10px;
  margin-bottom: 12px;
  animation: fadeInUp 0.6s var(--ease-spring, cubic-bezier(0.34, 1.56, 0.64, 1)) both;
}
.p11-cell { padding: 12px 14px; }
.p11-cell > * { position: relative; z-index: 5; }
.p11-cell-header-row { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.p11-cell-label { font-family: 'DM Mono', ui-monospace, monospace; font-size: 9px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.p11-cell-numrow { display: flex; align-items: baseline; gap: 4px; margin-top: 6px; }
.p11-cell-num { font-size: 24px; font-weight: 800; letter-spacing: -0.02em; line-height: 1; }
.p11-cell-cur { font-family: 'DM Mono', ui-monospace, monospace; font-size: 11px; font-weight: 700; color: var(--text-muted); }
.p11-cell-pct { font-family: 'DM Mono', ui-monospace, monospace; font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: var(--radius-pill); margin-left: auto; }
.p11-cell-pct.pos { background: oklch(0.92 0.08 145 / 0.5); color: hsl(145 60% 35%); }
[data-theme="dark"] .p11-cell-pct.pos { background: hsl(145 50% 12%); color: hsl(145 70% 65%); }
.p11-cell-pct.neg { background: oklch(0.92 0.08 25 / 0.5); color: hsl(0 60% 45%); }
[data-theme="dark"] .p11-cell-pct.neg { background: hsl(0 50% 12%); color: hsl(0 80% 70%); }
.p11-cell-pct.zero { background: oklch(0.92 0.02 220 / 0.5); color: var(--text-muted); }
[data-theme="dark"] .p11-cell-pct.zero { background: hsl(220 12% 12%); color: var(--text-muted); }
.p11-cell-meta { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 4px; line-height: 1.2; }
.p11-weather-top { display: flex; align-items: baseline; gap: 6px; }
.p11-weather-icon svg { width: 22px; height: 22px; stroke: hsl(38 80% 50%); fill: hsl(38 80% 60%); stroke-width: 1.5; }
[data-theme="dark"] .p11-weather-icon svg { stroke: hsl(38 90% 60%); fill: hsl(38 80% 50%); }
.p11-weather-temp { font-size: 22px; font-weight: 800; letter-spacing: -0.02em; }
.p11-weather-cond { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-top: 3px; }

/* P11 STUDIO ROW restyle */
.p11-studio-row { margin-bottom: 12px; animation: fadeInUp 0.8s var(--ease-spring, cubic-bezier(0.34, 1.56, 0.64, 1)) both; }
.p11-studio-btn { display: flex; align-items: center; gap: 12px; padding: 12px 14px; position: relative; text-decoration: none; color: var(--text); }
.p11-studio-btn > * { position: relative; z-index: 5; }
.p11-studio-btn:active { transform: scale(0.99); }
.p11-studio-icon {
  width: 36px; height: 36px;
  border-radius: 50%; display: grid; place-items: center; flex-shrink: 0;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow: 0 4px 12px hsl(280 70% 50% / 0.5);
  position: relative; overflow: hidden;
}
.p11-studio-icon::before { content: ''; position: absolute; inset: 0; background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.4) 85%, transparent 100%); animation: conicSpin 4s linear infinite; }
.p11-studio-icon svg { width: 18px; height: 18px; stroke: white; fill: none; stroke-width: 2; position: relative; z-index: 1; }
.p11-studio-text { flex: 1; min-width: 0; }
.p11-studio-label { display: block; font-size: 14px; font-weight: 800; letter-spacing: -0.01em; }
.p11-studio-sub { display: block; font-family: 'DM Mono', ui-monospace, monospace; font-size: 9.5px; font-weight: 700; color: var(--text-muted); letter-spacing: 0.06em; text-transform: uppercase; margin-top: 2px; }
.p11-studio-badge {
  font-family: 'DM Mono', ui-monospace, monospace; font-size: 10px; font-weight: 800;
  padding: 4px 10px; border-radius: var(--radius-pill);
  color: white;
  background: linear-gradient(135deg, hsl(0 75% 55%), hsl(15 75% 55%));
  box-shadow: 0 2px 8px hsl(0 70% 45% / 0.4);
  flex-shrink: 0;
}
@keyframes conicSpin { to { transform: rotate(360deg); } }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes popUp { from { opacity: 0; transform: scale(0.9) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
</style>
</head>
<body class="has-rms-shell mode-detailed">

<?php if ($renderSimpleHeader): ?>
<!-- ═══ S133 RENDER GUARD: simple back-arrow header ═══ -->
<header class="rms-simple-header">
  <a href="/life-board.php" class="rms-simple-back" title="Към начало">
    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    <span>Към начало</span>
  </a>
</header>
<?php else: ?>
<!-- ═══ Canonical header (design-kit partial) ═══ -->
<?php include __DIR__ . '/design-kit/partial-header.html'; ?>
<?php endif; ?>

<div class="app card-stagger" id="app">

    <?php if (!$renderSimpleHeader): ?>
    <!-- ═══ S83.PRE_ENTRY.FIX — toggle to Лесен mode ═══ -->
    <div class="cb-mode-row">
        <a href="/life-board.php" class="cb-mode-toggle" title="Лесен режим">
            Лесен <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
    </div>
    <?php endif; ?>

    <!-- ═══ P11 TOP ROW — quick-glance Днес + Времето ═══ -->
    <?php
        $cmp_today = (int)cmpPct($d0['rev'], $d0p['rev']);
        $glance_class = $cmp_today > 0 ? 'pos' : ($cmp_today < 0 ? 'neg' : 'zero');
        $glance_sign  = $cmp_today > 0 ? '+' : '';
    ?>
    <div class="p11-top-row">
        <div class="glass sm p11-cell qd">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <span class="glow"></span><span class="glow glow-bottom"></span>
            <div class="p11-cell-header-row">
                <div class="p11-cell-label">Днес · <?= htmlspecialchars($store_name) ?></div>
            </div>
            <div class="p11-cell-numrow">
                <span class="p11-cell-num"><?= number_format(round($d0['rev']), 0, '.', ' ') ?></span>
                <span class="p11-cell-cur"><?= $cs ?></span>
                <span class="p11-cell-pct <?= $glance_class ?>"><?= $glance_sign . $cmp_today ?>%</span>
            </div>
            <div class="p11-cell-meta"><?= $d0['cnt'] ?> продажби<?php if ($role === 'owner' && $d0['profit'] > 0): ?> · <?= number_format(round($d0['profit']), 0, '.', ' ') ?> печалба<?php endif; ?></div>
        </div>
        <?php if ($weather_today): ?>
        <div class="glass sm p11-cell qd">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <span class="glow"></span><span class="glow glow-bottom"></span>
            <div class="p11-weather-top">
                <span class="p11-weather-icon"><svg viewBox="0 0 24 24"><?= wmoSvg((int)$weather_today['weather_code']) ?></svg></span>
                <span class="p11-weather-temp"><?= round($weather_today['temp_max']) ?>°</span>
            </div>
            <div class="p11-weather-cond"><?= wmoText((int)$weather_today['weather_code']) ?></div>
            <div class="p11-cell-meta"><?= round($weather_today['temp_min']) ?>°/<?= round($weather_today['temp_max']) ?>° · Дъжд <?= (int)$weather_today['precipitation_prob'] ?>%</div>
        </div>
        <?php else: ?>
        <div class="glass sm p11-cell qd">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="p11-weather-top">
                <span class="p11-weather-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/></svg></span>
                <span class="p11-weather-temp">—</span>
            </div>
            <div class="p11-weather-cond">Времето</div>
            <div class="p11-cell-meta">—</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- S82.VISUAL — DASHBOARD GLASS CARD (qd) — KEEP for deep period/mode -->
    <!-- ═══════════════════════════════════════════ -->
    <?php
        $cmp_class = $cmp_today > 0 ? '' : ($cmp_today < 0 ? 'neg' : 'zero');
        $cmp_sign  = $cmp_today > 0 ? '+' : '';
    ?>
    <div class="glass sm s82-dash qd">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="s82-dash-top">
            <span class="s82-dash-period-label"><span id="revLabel">ДНЕС</span> · <?= htmlspecialchars($store_name) ?></span>
            <?php if (count($all_stores) > 1): ?>
            <span class="s82-dash-store">
                <select onchange="location.href='?store='+this.value" aria-label="Магазин">
                    <?php foreach ($all_stores as $st): ?>
                    <option value="<?= (int)$st['id'] ?>" <?= $st['id']==$store_id?'selected':'' ?>><?= htmlspecialchars($st['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </span>
            <?php endif; ?>
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
    <!-- S87.HEALTH.RESTORE — STORE HEALTH BAR (AI Точност) — KEEP -->
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
    <!-- P11 AI STUDIO ROW — restyled                -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="p11-studio-row">
      <a href="<?= htmlspecialchars(chatLink('/ai-studio.php')) ?>" class="glass sm p11-studio-btn" aria-label="AI Studio">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <span class="p11-studio-icon">
          <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </span>
        <span class="p11-studio-text">
          <span class="p11-studio-label">AI Studio</span>
          <?php if ($ai_studio_count > 0): ?>
          <span class="p11-studio-sub"><?= $ai_studio_count ?> ЧАКАТ</span>
          <?php else: ?>
          <span class="p11-studio-sub">КАТАЛОГ &amp; СНИМКИ</span>
          <?php endif; ?>
        </span>
        <?php if ($ai_studio_count > 0): ?>
        <span class="p11-studio-badge"><?= $ai_studio_count > 99 ? '99+' : $ai_studio_count ?></span>
        <?php endif; ?>
      </a>
    </div>

    <?php if (!empty($weather_week)): ?>
    <!-- ═══════════════════════════════════════════ -->
    <!-- P11 WEATHER FORECAST CARD (14 дни + AI препоръки)  -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="glass sm wfc q4" data-range="3" style="padding:14px;margin-bottom:14px;animation:fadeInUp 0.85s var(--ease-spring) both">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="wfc-head" style="display:flex;align-items:center;gap:10px;margin-bottom:12px;position:relative;z-index:5">
            <div class="wfc-head-ic" style="width:36px;height:36px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0;background:linear-gradient(135deg,hsl(195 75% 60%),hsl(38 85% 60%));box-shadow:0 4px 12px hsl(195 70% 50% / 0.45);position:relative;overflow:hidden">
                <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:white;fill:none;stroke-width:2;position:relative;z-index:1"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
            </div>
            <div class="wfc-head-text" style="flex:1;min-width:0">
                <div class="wfc-title" style="font-size:14px;font-weight:800;letter-spacing:-0.01em;background:linear-gradient(135deg,var(--text),hsl(195 70% 50%));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent">Прогноза за времето</div>
                <div class="wfc-sub" style="font-family:'DM Mono',ui-monospace,monospace;font-size:9px;font-weight:700;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase;margin-top:1px">AI препоръки за седмицата</div>
            </div>
        </div>
        <div class="wfc-tabs" style="display:flex;gap:3px;padding:3px;border-radius:var(--radius-pill);margin-bottom:12px;position:relative;z-index:5">
            <button type="button" class="wfc-tab" data-tab="3" onclick="wfcSetRange('3')" style="flex:1;height:28px;border-radius:var(--radius-pill);font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:800;letter-spacing:0.04em;color:var(--text-muted);border:none;background:transparent">3д</button>
            <button type="button" class="wfc-tab" data-tab="7" onclick="wfcSetRange('7')" style="flex:1;height:28px;border-radius:var(--radius-pill);font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:800;letter-spacing:0.04em;color:var(--text-muted);border:none;background:transparent">7д</button>
            <button type="button" class="wfc-tab" data-tab="14" onclick="wfcSetRange('14')" style="flex:1;height:28px;border-radius:var(--radius-pill);font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:800;letter-spacing:0.04em;color:var(--text-muted);border:none;background:transparent">14д</button>
        </div>
        <div class="wfc-days" style="display:flex;gap:6px;overflow-x:auto;padding-bottom:8px;margin-bottom:12px;-webkit-overflow-scrolling:touch;scrollbar-width:none;position:relative;z-index:5">
            <?php foreach ($weather_week as $idx => $w):
                $is_today = ($w['forecast_date'] === date('Y-m-d'));
                $cls = wfcDayClass((int)$w['weather_code']);
                $rain_pct = (int)$w['precipitation_prob'];
                $rain_dry = $rain_pct < 30;
            ?>
            <div class="wfc-day <?= $cls ?><?= $is_today ? ' today' : '' ?>" style="flex:0 0 auto;width:64px;padding:10px 6px;border-radius:var(--radius-sm);display:flex;flex-direction:column;align-items:center;gap:4px">
                <div class="wfc-day-name" style="font-family:'DM Mono',ui-monospace,monospace;font-size:9px;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted)"><?= wfcDayName($w['forecast_date'], $is_today) ?></div>
                <div class="wfc-day-ic" style="width:28px;height:28px;display:grid;place-items:center"><?= wfcDayIcon((int)$w['weather_code']) ?></div>
                <div class="wfc-day-temp" style="font-size:14px;font-weight:800;letter-spacing:-0.01em"><?= round($w['temp_max']) ?>°<small style="font-size:10px;font-weight:600;color:var(--text-muted);margin-left:1px">/<?= round($w['temp_min']) ?></small></div>
                <div class="wfc-day-rain<?= $rain_dry ? ' dry' : '' ?>" style="font-family:'DM Mono',ui-monospace,monospace;font-size:9px;font-weight:700;color:<?= $rain_dry ? 'var(--text-faint)' : 'hsl(210 70% 50%)' ?>"><?= $rain_pct ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="wfc-recs-divider" style="display:flex;align-items:center;gap:8px;margin:4px 0 10px;font-family:'DM Mono',ui-monospace,monospace;font-size:9px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--magic,oklch(0.65 0.25 310));position:relative;z-index:5">
            <span style="flex:0 0 auto">AI препоръки</span>
        </div>
        <?php if ($weather_suggestion): ?>
        <div class="wfc-rec window" style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;margin-bottom:6px;border-radius:var(--radius-sm);position:relative;z-index:5">
            <span class="wfc-rec-ic" style="width:30px;height:30px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0;background:hsl(330 50% 92%)">
                <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:hsl(330 70% 50%);fill:none;stroke-width:2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
            </span>
            <div class="wfc-rec-text" style="flex:1;min-width:0">
                <span class="wfc-rec-label" style="display:block;font-family:'DM Mono',ui-monospace,monospace;font-size:9px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:2px">Витрина / Трафик</span>
                <div class="wfc-rec-body" style="font-size:12px;font-weight:600;line-height:1.35"><?= htmlspecialchars($weather_suggestion) ?></div>
            </div>
        </div>
        <?php endif; ?>
        <div class="wfc-source" style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:var(--radius-pill);font-family:'DM Mono',ui-monospace,monospace;font-size:8.5px;font-weight:700;color:var(--text-muted);letter-spacing:0.04em;margin-top:8px;position:relative;z-index:5">
            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12.01" y2="8"/><line x1="11" y1="12" x2="12" y2="16"/></svg>
            <span>OPEN-METEO · Обновено <?= date('H:i') ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════ -->
    <!-- P11 AI HELP CARD (qhelp = magic violet)     -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="glass sm help-card qhelp" style="padding:14px;margin-bottom:14px;animation:fadeInUp 0.9s var(--ease-spring) both">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="help-head" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;position:relative;z-index:5">
            <div class="help-head-ic" style="width:36px;height:36px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0;background:linear-gradient(135deg,hsl(280 70% 55%),hsl(305 65% 55%));box-shadow:0 4px 12px hsl(280 70% 50% / 0.5);position:relative;overflow:hidden">
                <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:white;fill:none;stroke-width:2;position:relative;z-index:1"><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/><circle cx="12" cy="12" r="10"/></svg>
            </div>
            <div class="help-head-text" style="flex:1;min-width:0">
                <div class="help-title" style="font-size:15px;font-weight:800;letter-spacing:-0.01em;background:linear-gradient(135deg,var(--text),var(--magic,oklch(0.65 0.25 310)));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent">AI ти помага</div>
                <div class="help-sub" style="font-size:11px;font-weight:600;color:var(--text-muted);margin-top:2px;line-height:1.3">Питай за всичко — каталог, продажби, тенденции</div>
            </div>
        </div>
        <div class="help-body" style="font-size:12px;font-weight:600;color:var(--text-muted);line-height:1.5;margin-bottom:10px;position:relative;z-index:5">
            Просто пиши или говори. <b style="color:var(--text);font-weight:800">AI знае всичко за магазина ти</b>. Не се нуждаеш от меню — само попитай.
        </div>
        <div class="help-chips-label" style="font-family:'DM Mono',ui-monospace,monospace;font-size:9px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;position:relative;z-index:5">Опитай да попиташ</div>
        <div class="help-chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;position:relative;z-index:5">
            <button type="button" class="help-chip" onclick="openChatQ('Какво ми тежи на склада')" style="padding:7px 12px;border-radius:var(--radius-pill);font-size:11.5px;font-weight:700;color:var(--text);border:none;display:inline-flex;align-items:center;gap:5px;background:var(--surface);box-shadow:var(--shadow-card-sm)"><span style="font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:900;color:var(--magic,oklch(0.65 0.25 310))">?</span><span>Какво ми тежи на склада</span></button>
            <button type="button" class="help-chip" onclick="openChatQ('Кои са топ продавачи')" style="padding:7px 12px;border-radius:var(--radius-pill);font-size:11.5px;font-weight:700;color:var(--text);border:none;display:inline-flex;align-items:center;gap:5px;background:var(--surface);box-shadow:var(--shadow-card-sm)"><span style="font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:900;color:var(--magic,oklch(0.65 0.25 310))">?</span><span>Кои са топ продавачи</span></button>
            <button type="button" class="help-chip" onclick="openChatQ('Колко да поръчам')" style="padding:7px 12px;border-radius:var(--radius-pill);font-size:11.5px;font-weight:700;color:var(--text);border:none;display:inline-flex;align-items:center;gap:5px;background:var(--surface);box-shadow:var(--shadow-card-sm)"><span style="font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:900;color:var(--magic,oklch(0.65 0.25 310))">?</span><span>Колко да поръчам</span></button>
            <button type="button" class="help-chip" onclick="openChatQ('Защо приходите паднаха')" style="padding:7px 12px;border-radius:var(--radius-pill);font-size:11.5px;font-weight:700;color:var(--text);border:none;display:inline-flex;align-items:center;gap:5px;background:var(--surface);box-shadow:var(--shadow-card-sm)"><span style="font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:900;color:var(--magic,oklch(0.65 0.25 310))">?</span><span>Защо приходите паднаха</span></button>
            <button type="button" class="help-chip" onclick="openChatQ('Покажи Adidas 42')" style="padding:7px 12px;border-radius:var(--radius-pill);font-size:11.5px;font-weight:700;color:var(--text);border:none;display:inline-flex;align-items:center;gap:5px;background:var(--surface);box-shadow:var(--shadow-card-sm)"><span style="font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:900;color:var(--magic,oklch(0.65 0.25 310))">?</span><span>Покажи Adidas 42</span></button>
            <button type="button" class="help-chip" onclick="openChatQ('Какво продаваме днес')" style="padding:7px 12px;border-radius:var(--radius-pill);font-size:11.5px;font-weight:700;color:var(--text);border:none;display:inline-flex;align-items:center;gap:5px;background:var(--surface);box-shadow:var(--shadow-card-sm)"><span style="font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:900;color:var(--magic,oklch(0.65 0.25 310))">?</span><span>Какво продаваме днес</span></button>
        </div>
        <div class="help-video-ph" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius-sm);margin-bottom:8px;background:var(--surface);box-shadow:var(--shadow-pressed);border:1px dashed oklch(0.62 0.22 285 / 0.3);position:relative;z-index:5">
            <span class="help-video-ic" style="width:28px;height:28px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0;background:linear-gradient(135deg,hsl(280 70% 55%),hsl(305 65% 55%));box-shadow:0 2px 6px hsl(280 70% 50% / 0.4)">
                <svg viewBox="0 0 24 24" style="width:12px;height:12px;stroke:white;fill:white;stroke-width:0"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            </span>
            <div class="help-video-text" style="flex:1;min-width:0">
                <div class="help-video-title" style="font-size:11.5px;font-weight:700">Видео урок</div>
                <div class="help-video-sub" style="font-family:'DM Mono',ui-monospace,monospace;font-size:9.5px;font-weight:600;color:var(--text-muted);margin-top:1px">Скоро</div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- LIFE BOARD HEADER + FILTER PILLS + CARDS    -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="lb-header">
        <div class="lb-title">
            <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <span class="lb-title-text">Life Board</span>
        </div>
        <span class="lb-count"><?= count($briefing) ?> теми · <?= date('H:i') ?></span>
    </div>

    <?php if (!empty($all_insights_for_js)): ?>
    <!-- P11 Filter pills (open Signal Browser with category context) -->
    <div class="fp-row" style="display:flex;gap:6px;overflow-x:auto;padding:0 4px 8px;margin-bottom:8px;-webkit-overflow-scrolling:touch;scrollbar-width:none;position:relative;z-index:5">
        <button type="button" class="fp-pill active" onclick="openSignalBrowser()" style="flex:0 0 auto;height:32px;padding:0 14px;border-radius:var(--radius-pill);display:inline-flex;align-items:center;gap:5px;font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:800;letter-spacing:0.04em;color:white;border:none;background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 4px 12px hsl(255 80% 50% / 0.4);white-space:nowrap">
            Всички <span class="fp-count" style="font-size:9px;padding:1px 6px;border-radius:var(--radius-pill);background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.2)"><?= count($all_insights_for_js) ?></span>
        </button>
        <?php
        $cat_pill_meta = [
            'finance'   => ['label' => 'Финанси',  'svg' => '<svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>'],
            'sales'     => ['label' => 'Продажби', 'svg' => '<svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1.5"/><circle cx="18" cy="21" r="1.5"/><path d="M3 3h2l2.7 12.3a2 2 0 002 1.7h7.6a2 2 0 002-1.5L21 8H6"/></svg>'],
            'warehouse' => ['label' => 'Склад',    'svg' => '<svg viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>'],
            'products'  => ['label' => 'Продукти', 'svg' => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'],
            'expenses'  => ['label' => 'Разходи',  'svg' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>'],
        ];
        foreach ($cat_pill_meta as $catKey => $meta):
            $cnt = count($ui_categories[$catKey]['items'] ?? []);
            if ($cnt === 0) continue;
        ?>
        <button type="button" class="fp-pill" onclick="openSignalBrowser()" style="flex:0 0 auto;height:32px;padding:0 14px;border-radius:var(--radius-pill);display:inline-flex;align-items:center;gap:5px;font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:800;letter-spacing:0.04em;color:var(--text-muted);border:none;background:var(--surface);box-shadow:var(--shadow-card-sm);white-space:nowrap">
            <?= $meta['svg'] ?>
            <?= htmlspecialchars($meta['label']) ?>
            <span class="fp-count" style="font-size:9px;padding:1px 6px;border-radius:var(--radius-pill);background:var(--accent);color:white"><?= $cnt ?></span>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($briefing)):
        $fq_meta = [
            'loss'       => ['q'=>'q1', 'name'=>'Какво губиш'],
            'loss_cause' => ['q'=>'q2', 'name'=>'От какво губиш'],
            'gain'       => ['q'=>'q3', 'name'=>'Какво печелиш'],
            'gain_cause' => ['q'=>'q4', 'name'=>'От какво печелиш'],
            'order'      => ['q'=>'q5', 'name'=>'Поръчай'],
            'anti_order' => ['q'=>'q6', 'name'=>'НЕ поръчвай'],
        ];
        $topic_to_idx = [];
        foreach ($all_insights_for_js as $idx => $v) {
            $topic_to_idx[$v['topicId']] = $idx;
        }
        $first_card = true;
        foreach ($briefing as $ins):
            $fq = $ins['fundamental_question'];
            $meta = $fq_meta[$fq] ?? ['q'=>'q3','name'=>''];
            $action = insightAction($ins);
            $idx_in_all = $topic_to_idx[$ins['topic_id']] ?? 0;
            $title_js = htmlspecialchars(addslashes($ins['title']), ENT_QUOTES);
            $svg_html = $fq_svg_p11[$meta['q']] ?? $fq_svg_p11['q3'];
            $module_label = fqModuleLabel($ins['category'] ?? '', $ins['module'] ?? 'home');
            // Първият card (gain ако има, иначе loss) — expanded; останалите — collapsed
            $expanded_class = $first_card ? ' expanded' : '';
            $first_card = false;
    ?>
    <div class="glass sm lb-card <?= $meta['q'] ?><?= $expanded_class ?>" data-topic="<?= htmlspecialchars($ins['topic_id'], ENT_QUOTES) ?>" style="padding:12px 14px;margin-bottom:8px;cursor:pointer">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="lb-collapsed" onclick="lbToggleCard(event,this)" style="display:flex;align-items:center;gap:10px;position:relative;z-index:5">
            <span class="lb-emoji-orb" style="width:28px;height:28px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0;background:hsl(0 50% 92%);box-shadow:var(--shadow-pressed)"><?= $svg_html ?></span>
            <div class="lb-collapsed-content" style="flex:1;min-width:0">
                <span class="lb-fq-tag-mini" style="display:block;font-family:'DM Mono',ui-monospace,monospace;font-size:8.5px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-muted)"><?= htmlspecialchars($module_label) ?> · <?= htmlspecialchars($meta['name']) ?></span>
                <span class="lb-collapsed-title" style="display:block;font-size:12px;font-weight:700;margin-top:2px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($ins['title']) ?></span>
            </div>
            <button type="button" class="lb-expand-btn" aria-label="Разгъни" style="width:24px;height:24px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0;background:var(--surface);box-shadow:var(--shadow-card-sm);border:none;transition:transform 0.3s ease">
                <svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:var(--text-muted);fill:none;stroke-width:2.5"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <button type="button" class="lb-dismiss" aria-label="Скрий" onclick="lbDismissCard(event,this)" style="width:22px;height:22px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0;background:transparent;border:none;color:var(--text-faint);font-size:14px;line-height:1">×</button>
        </div>
        <div class="lb-expanded" style="max-height:0;overflow:hidden;transition:max-height 0.35s ease,padding-top 0.35s ease;position:relative;z-index:5">
            <?php if (!empty($ins['detail_text'])): ?>
            <div class="lb-body" style="font-size:12px;line-height:1.5;color:var(--text-muted);padding:10px 12px;border-radius:var(--radius-sm);margin-bottom:10px;background:var(--surface);box-shadow:var(--shadow-pressed)"><?= htmlspecialchars($ins['detail_text']) ?></div>
            <?php endif; ?>
            <div class="lb-actions" style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap">
                <button type="button" class="lb-action" onclick="openChatQ('<?= $title_js ?>')">Защо?</button>
                <button type="button" class="lb-action" onclick="openSignalDetail(<?= $idx_in_all ?>)">Покажи</button>
                <?php if ($action['type'] === 'deeplink' && $action['url']): ?>
                <a class="lb-action primary" href="<?= htmlspecialchars(chatLink($action['url'])) ?>"><?= htmlspecialchars($action['label']) ?> →</a>
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
    </div>
    <?php endforeach; ?>
    <style>
    .lb-card.expanded .lb-expanded { max-height: 600px !important; padding-top: 12px !important; }
    .lb-card.expanded .lb-expand-btn { transform: rotate(180deg); }
    </style>
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
<!-- INPUT BAR — partial (preserved)                        -->
<!-- ═══════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/partials/chat-input-bar.php'; ?>

<?php if (!$renderSimpleHeader): ?>
<!-- ═══════════════════════════════════════════════════════ -->
<!-- BOTTOM NAV — partial (skip in simple-mode render guard) -->
<!-- ═══════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>
<?php endif; ?>

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

<!-- ═══ P11 INFO POPOVER (preserved JS for parity, no triggers in chat.php) ═══ -->
<div class="info-overlay" id="infoOverlay" onclick="if(event.target===this)closeInfo()" style="position:fixed;inset:0;background:rgba(163,177,198,0.5);backdrop-filter:blur(8px);z-index:100;display:none;align-items:center;justify-content:center;padding:16px">
    <div class="info-card" style="width:100%;max-width:380px;border-radius:var(--radius);padding:18px 16px;position:relative;background:var(--surface);box-shadow:var(--shadow-card)">
        <div class="info-card-head" style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
            <div class="info-card-ic" id="infoIc" style="width:44px;height:44px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0;background:var(--surface);box-shadow:var(--shadow-pressed)"></div>
            <div class="info-card-title" id="infoTitle" style="flex:1;font-size:16px;font-weight:800;letter-spacing:-0.01em"></div>
            <button type="button" class="info-card-close" onclick="closeInfo()" aria-label="Затвори" style="width:32px;height:32px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0;background:var(--surface);box-shadow:var(--shadow-card-sm);border:none">
                <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:var(--text);fill:none;stroke-width:2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="info-card-body" id="infoBody" style="font-size:13px;font-weight:600;color:var(--text-muted);line-height:1.45;margin-bottom:14px"></div>
        <div class="info-card-voice" style="padding:10px 12px;border-radius:var(--radius-sm);margin-bottom:14px;background:var(--surface);box-shadow:var(--shadow-pressed)">
            <div class="info-card-voice-label" style="font-family:'DM Mono',ui-monospace,monospace;font-size:8.5px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--magic,oklch(0.65 0.25 310));margin-bottom:4px;display:flex;align-items:center;gap:4px">
                <svg viewBox="0 0 24 24" style="width:10px;height:10px;stroke:currentColor;fill:none;stroke-width:2"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0014 0v-2"/></svg>
                <span>Опитай с глас</span>
            </div>
            <div class="info-card-voice-text" id="infoVoice" style="font-size:12px;font-weight:700;color:var(--text);font-style:italic;line-height:1.4"></div>
        </div>
        <a class="info-card-cta" id="infoCta" href="#" style="width:100%;height:46px;padding:0 14px;border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;gap:8px;font-size:13px;font-weight:800;color:white;border:none;background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 4px 14px hsl(255 80% 50% / 0.4);text-decoration:none">
            <span id="infoCtaLabel"></span>
            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:white;fill:none;stroke-width:2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
    </div>
</div>
<style>.info-overlay.active { display: flex !important; animation: fadeIn 0.2s ease; }</style>

<script>
// ═══════════════════════════════════════════════════════
// THEME TOGGLE — default DARK, user can switch to LIGHT
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

let _revAnimatedOnce = false;
function updateRevenue() {
    const d = P[curPeriod];
    const val = curMode === 'rev' ? d.rev : d.profit;
    const pct = curMode === 'rev' ? d.cmp_rev : d.cmp_prof;
    const sub = curMode === 'rev' ? d.sub_rev : d.sub_prof;

    const revEl = $('revNum');
    if (!_revAnimatedOnce && typeof animateCountUp === 'function') {
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
    if (IS_OWNER && d.cnt > 0) meta += ' · ' + d.margin + '% марж';
    $('revMeta').textContent = meta;

    const cw = $('confWarn');
    if (cw) cw.style.display = curMode === 'profit' ? 'flex' : 'none';
}

function setPeriod(period, el) {
    curPeriod = period;
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

// S133 P11 — Toggle collapsible lb-card (P11 design)
function lbToggleCard(e, row) {
    if (e.target.closest('.lb-action') || e.target.closest('.lb-fb-btn') || e.target.closest('.lb-dismiss') || e.target.closest('.lb-expand-btn')) {
        // Click on inner button — handled separately, but for expand-btn fall through to toggle
        if (!e.target.closest('.lb-expand-btn')) return;
    }
    var card = row.closest('.lb-card');
    if (!card) return;
    card.classList.toggle('expanded');
    if (navigator.vibrate) navigator.vibrate(6);
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
    var lb = $('logoutBtn');
    if (lb && !lb.contains(e.target)) {
        var dr = $('logoutDrop');
        if (dr) dr.classList.remove('show');
    }
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

    if (s.fqLabel && s.qClass) {
        h += '<div style="text-align:center;margin-bottom:4px">'
           + '<span class="sig-fq-badge ' + s.qClass + '">' + esc(s.fqLabel) + '</span></div>';
    }

    if (s.value) {
        const sign = u === 'info' ? '+' : '−';
        h += '<div class="sig-hero"><div class="sig-hero-num ' + u + '">'
           + sign + fmt(Math.abs(s.value)) + ' ' + CS + '</div>'
           + '<div class="sig-hero-unit">' + (u === 'info' ? 'печалба / период' : 'пропуснати приходи / период') + '</div></div>';
    }

    if (s.detail) {
        h += '<div class="sig-box"><div class="sig-label">Защо</div>'
           + '<div class="sig-text">' + esc(s.detail) + '</div></div>';
    }

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
           + '<span class="browser-cat-cnt">' + (items.length || '—') + '</span></div>';

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
               + '<div class="sig-card-arr">›</div></div>';
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
          + 'AI · ' + t + '</div>'
          + '<div class="msg-ai">' + esc(txt) + '</div>';
    if (actions && actions.length) {
        h += '<div class="action-buttons">';
        actions.forEach(function(a) {
            h += '<button class="action-button" onclick="window.open(\'' + esc(a.url || '#') + '\',\'_blank\')">' + esc(a.label) + ' →</button>';
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
    if (typeof ALL_INSIGHTS !== 'undefined') {
        for (let i = 0; i < ALL_INSIGHTS.length; i++) {
            if (ALL_INSIGHTS[i].topicId === topic) { openSignalDetail(i); return; }
        }
    }
    openChatQ(title);
}

// ═══════════════════════════════════════════════════════
// S133 P11 — Info popover (preserved JS for parity)
// ═══════════════════════════════════════════════════════
const INFO_DATA = {};
function openInfo(key) {
    const d = INFO_DATA[key]; if (!d) return;
    document.getElementById('infoIc').innerHTML = d.iconSvg || '';
    document.getElementById('infoTitle').textContent = d.title || '';
    document.getElementById('infoBody').innerHTML = d.body || '';
    document.getElementById('infoVoice').textContent = d.voice || '';
    document.getElementById('infoCtaLabel').textContent = d.cta || '';
    if (d.href) document.getElementById('infoCta').setAttribute('href', d.href);
    document.getElementById('infoOverlay').classList.add('active');
}
function closeInfo() {
    document.getElementById('infoOverlay').classList.remove('active');
}

// S133 P11 — WFC range tab toggle
function wfcSetRange(r) {
    var card = document.querySelector('.wfc');
    if (card) card.setAttribute('data-range', r);
    // Update active tab visual
    document.querySelectorAll('.wfc-tab').forEach(function(t){
        t.style.color = (t.getAttribute('data-tab') === r) ? 'white' : 'var(--text-muted)';
        t.style.background = (t.getAttribute('data-tab') === r) ? 'linear-gradient(135deg, hsl(195 70% 50%), hsl(38 80% 55%))' : 'transparent';
        t.style.boxShadow = (t.getAttribute('data-tab') === r) ? '0 3px 10px hsl(195 70% 45% / 0.4)' : 'none';
    });
    // Hide/show wfc-day cells per range
    var days = document.querySelectorAll('.wfc-days .wfc-day');
    days.forEach(function(d, i){
        if (r === '3') d.style.display = (i < 3) ? '' : 'none';
        else if (r === '7') d.style.display = (i < 7) ? '' : 'none';
        else d.style.display = '';
    });
}

// ═══════════════════════════════════════════════════════
// INIT + HAPTICS
// ═══════════════════════════════════════════════════════
window.addEventListener('DOMContentLoaded', () => {
    updateRevenue();
    try {
        if (sessionStorage.getItem('rms_chat_open') === '1') {
            setTimeout(() => openChat(), 300);
        }
    } catch(e) {}
    // Initialize WFC default range = 3
    wfcSetRange('3');
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
        const eased = 1 - Math.pow(1 - progress, 3);
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

window.addEventListener('load', () => {
    setTimeout(() => {
        document.querySelectorAll('.count-up[data-count]').forEach(el => {
            const v = parseInt(el.dataset.count, 10);
            if (!isNaN(v) && v > 0 && el.id !== 'revNum') {
                animateCountUp(el, v, 1800);
            }
        });
    }, 1200);
});

// ═══════════════════════════════════════════════════════
// S87.ANIMATIONS v3 FULL PACK — Groups 1-6 JS hooks
// ═══════════════════════════════════════════════════════
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
        var cards = document.querySelectorAll('.lb-card');
        for (var i = 4; i < cards.length; i++) cards[i].classList.add('scroll-reveal');
        document.querySelectorAll('.scroll-reveal').forEach(function(el){
            obs.observe(el);
        });
    });
})();

function addMessageWithAnimation(role, txt){
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

(function s87v3_overlayClose(){
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

function changeContext(targetUrl){
    var app = document.querySelector('.app');
    if (!app || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
        window.location.href = targetUrl;
        return;
    }
    app.classList.add('context-out');
    setTimeout(function(){ window.location.href = targetUrl; }, 250);
}

(function s87v3_periodSmoothNum(){
    var origSetPeriod = window.setPeriod;
    if (typeof origSetPeriod !== 'function') return;
    window.setPeriod = function(period, el){
        origSetPeriod(period, el);
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

(function attachSpringRelease(){
    const SEL = ['.spring-tap', '.briefing-btn-primary', '.briefing-btn-secondary',
                 '.sig-btn-primary', '.sig-btn-secondary',
                 '.nav-tab', '.header-icon-btn', '.rms-icon-btn',
                 '.top-pill', '.rev-pill', '.s82-dash-pill',
                 '.sig-card', '.sig-more',
                 '.lb-action', '.lb-dismiss', '.lb-fb-btn',
                 '.chat-mic', '.chat-send',
                 '.ov-back', '.ov-close',
                 '.fp-pill', '.help-chip', '.wfc-tab', '.lb-expand-btn'].join(',');
    const handler = (el) => {
        el.classList.remove('released');
        void el.offsetWidth;
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
