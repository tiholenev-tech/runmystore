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
  border-radius: 999px;
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
.p11-cell-pct { font-family: 'DM Mono', ui-monospace, monospace; font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: 999px; margin-left: auto; }
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
  padding: 4px 10px; border-radius: 999px;
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

