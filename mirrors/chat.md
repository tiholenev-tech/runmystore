<?php
/**
 * chat-v2.php — P11 1:1 visual base + жива PHP логика (incremental)
 * S140 REBUILD: започваме от P11 макета, вкарваме PHP данни блок по блок.
 * НЕ заменя chat.php — съществува паралелно за безопасно тестване.
 *
 * Готови блокове:
 *   ✅ Auth + tenant + store + revenue periods
 *   ⏳ s82-dash калкулатор (този commit)
 *   ⏳ Weather (TBD)
 *   ⏳ AI Studio row (TBD)
 *   ⏳ Life Board cards (TBD)
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)($_SESSION['store_id'] ?? 0);
$role      = $_SESSION['role'] ?? 'seller';

// Store switch via GET (без redirect — за да тестваме лесно)
if (!empty($_GET['store'])) {
    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
        [(int)$_GET['store'], $tenant_id])->fetch();
    if ($chk) { $_SESSION['store_id'] = (int)$_GET['store']; $store_id = (int)$_GET['store']; }
    header('Location: chat-v2.php'); exit;
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

// Confidence (% артикули с попълнена себестойност) — нужно за приблизителна печалба warning
$total_products = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1', [$tenant_id])->fetchColumn();
$with_cost      = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0', [$tenant_id])->fetchColumn();
$confidence_pct = $total_products > 0 ? round($with_cost / $total_products * 100) : 0;

// Revenue periods (днес / вчера) — нужни за s82-dash
function v2periodData($tid, $sid, $r, $from, $to = null) {
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
function v2cmpPct($a, $b) { return $b > 0 ? round(($a - $b) / $b * 100) : ($a > 0 ? 100 : 0); }

$today = date('Y-m-d');
$d0   = v2periodData($tenant_id, $store_id, $role, $today, $today);
$d0p  = v2periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')));
$d7   = v2periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-6 days')), $today);
$d7p  = v2periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-13 days')), date('Y-m-d', strtotime('-7 days')));
$d30  = v2periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-29 days')), $today);
$d30p = v2periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-59 days')), date('Y-m-d', strtotime('-30 days')));
$d365  = v2periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-364 days')), $today);
$d365p = v2periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-729 days')), date('Y-m-d', strtotime('-365 days')));

function v2mgn($p) { return $p['rev'] > 0 ? round($p['profit'] / $p['rev'] * 100) : 0; }

$cmp_today = (int)v2cmpPct($d0['rev'], $d0p['rev']);
$cmp_sign  = $cmp_today > 0 ? '+' : '';

// JSON за JS — за pills превключване
$v2_periods_json = json_encode([
    'today' => [
        'rev' => round($d0['rev']), 'profit' => round($d0['profit']), 'cnt' => $d0['cnt'], 'margin' => v2mgn($d0),
        'cmp_rev' => v2cmpPct($d0['rev'], $d0p['rev']), 'cmp_prof' => v2cmpPct($d0['profit'], $d0p['profit']),
        'cntp' => $d0p['cnt'],
    ],
    '7d' => [
        'rev' => round($d7['rev']), 'profit' => round($d7['profit']), 'cnt' => $d7['cnt'], 'margin' => v2mgn($d7),
        'cmp_rev' => v2cmpPct($d7['rev'], $d7p['rev']), 'cmp_prof' => v2cmpPct($d7['profit'], $d7p['profit']),
        'cntp' => $d7p['cnt'],
    ],
    '30d' => [
        'rev' => round($d30['rev']), 'profit' => round($d30['profit']), 'cnt' => $d30['cnt'], 'margin' => v2mgn($d30),
        'cmp_rev' => v2cmpPct($d30['rev'], $d30p['rev']), 'cmp_prof' => v2cmpPct($d30['profit'], $d30p['profit']),
        'cntp' => $d30p['cnt'],
    ],
    '365d' => [
        'rev' => round($d365['rev']), 'profit' => round($d365['profit']), 'cnt' => $d365['cnt'], 'margin' => v2mgn($d365),
        'cmp_rev' => v2cmpPct($d365['rev'], $d365p['rev']), 'cmp_prof' => v2cmpPct($d365['profit'], $d365p['profit']),
        'cntp' => $d365p['cnt'],
    ],
], JSON_UNESCAPED_UNICODE);

// ══════════════════════════════════════════════
// WEATHER (днешен + 14-дневна прогноза)
// ══════════════════════════════════════════════
$weather_today = null; $weather_week = []; $weather_suggestion = '';
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
        if ($is_fashion) {
            if ($tmax > 30)      $weather_suggestion = 'Витрина: летни артикули отпред — рокли, сандали, шапки. Пуснати ли са зимните на намаление?';
            elseif ($tmax > 25)  $weather_suggestion = 'Витрина: леки рокли и сандали. Ако имаш пролетни остатъци — време за намаление';
            elseif ($tmax > 20)  $weather_suggestion = 'Витрина: тениски, къси панталони. Преходен период — миксирай сезони';
            elseif ($tmax > 15)  $weather_suggestion = 'Витрина: леки якета, дънки. Зимните трябва да са на намаление или прибрани';
            elseif ($tmax > 10)  $weather_suggestion = 'Витрина: якета и преходни обувки. Летните на разпродажба ако са останали';
            elseif ($tmax > 5)   $weather_suggestion = 'Витрина: палта, пуловери, зимни обувки. Есенните — намали или прибери';
            else                 $weather_suggestion = 'Витрина: пуховки, ботуши, шалове. Пълна зима — сезонните артикули отпред';
            if ($rain > 60) $weather_suggestion .= '. Дъжд — сложи чадъри или дъждобрани на витрината';
        } else {
            if ($rain > 75)      $weather_suggestion = 'Силен дъжд — очаквай 25-35% по-малко хора, но по-голяма кошница';
            elseif ($rain > 50)  $weather_suggestion = 'Вероятен дъжд — възможно 15-25% по-малко хора';
            elseif ($tmax > 33)  $weather_suggestion = 'Много горещо — хората избягват разходки, по-слаб трафик';
            elseif ($tmax > 25)  $weather_suggestion = 'Хубаво време — добър ден за разходки и пазаруване';
            elseif ($tmax > 15)  $weather_suggestion = 'Приятно време — нормален трафик';
            elseif ($tmax > 5)   $weather_suggestion = 'Хладно — хората пазаруват по-целенасочено';
            else                 $weather_suggestion = 'Студено — по-малко разходки, но сериозни купувачи';
            if ($rain > 60) $weather_suggestion .= '. Дъждовен ден — обмисли промоция за онлайн';
        }
        if (count($weather_week) >= 7) {
            $diff = round((float)$weather_week[6]['temp_max'] - (float)$weather_week[0]['temp_max']);
            if ($diff >= 5)       $weather_suggestion .= '. Затопляне идва (+' . $diff . '°C за 7 дни)';
            elseif ($diff <= -5)  $weather_suggestion .= '. Застудяване идва (' . $diff . '°C за 7 дни)';
        }
    }
} catch (Throwable $e) { /* weather недостъпен */ }

// Open-Meteo weather code → класове и SVG (по P11 стил: sunny/partly/cloudy/rain/snow)
function v2weatherClass($code) {
    $c = (int)$code;
    if ($c === 0)                        return 'sunny';
    if ($c === 1 || $c === 2)            return 'partly';
    if ($c === 3 || $c === 45 || $c === 48) return 'cloudy';
    if ($c >= 71 && $c <= 77)            return 'snow';
    if ($c >= 51)                        return 'rain';
    return 'partly';
}
function v2weatherSvg($code) {
    $cls = v2weatherClass($code);
    if ($cls === 'sunny')  return '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
    if ($cls === 'partly') return '<svg viewBox="0 0 24 24"><path d="M22 14a4 4 0 00-7.5-2 5.5 5.5 0 00-10 1A4 4 0 005 21h13a4 4 0 004-4 4 4 0 00-2-3.46"/><circle cx="6" cy="6" r="2"/></svg>';
    if ($cls === 'cloudy') return '<svg viewBox="0 0 24 24"><path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z"/></svg>';
    if ($cls === 'snow')   return '<svg viewBox="0 0 24 24"><path d="M20 17.58A5 5 0 0018 8h-1.26A8 8 0 104 16.25"/><line x1="8" y1="16" x2="8.01" y2="16"/><line x1="8" y1="20" x2="8.01" y2="20"/><line x1="12" y1="18" x2="12.01" y2="18"/><line x1="12" y1="22" x2="12.01" y2="22"/><line x1="16" y1="16" x2="16.01" y2="16"/><line x1="16" y1="20" x2="16.01" y2="20"/></svg>';
    /* rain */            return '<svg viewBox="0 0 24 24"><path d="M20 16.2A4.5 4.5 0 0017.5 8h-1.8A7 7 0 104 14.9"/><line x1="8" y1="19" x2="8" y2="21"/><line x1="8" y1="13" x2="8" y2="15"/><line x1="16" y1="19" x2="16" y2="21"/><line x1="16" y1="13" x2="16" y2="15"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="12" y1="15" x2="12" y2="17"/></svg>';
}
function v2bgDay($iso) {
    if ($iso === date('Y-m-d')) return 'Днес';
    $names = ['Нед','Пон','Вт','Ср','Чет','Пет','Съб'];
    return $names[(int)date('w', strtotime($iso))];
}

// ══════════════════════════════════════════════
// AI STUDIO — pending count (липсваща снимка ИЛИ описание)
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
// AI INSIGHTS — Life Board cards (top insight per fundamental_question)
// ══════════════════════════════════════════════
$insights = [];
$plan = function_exists('effectivePlan') ? effectivePlan($tenant) : 'pro';
try {
    if (function_exists('getInsightsForModule') && function_exists('planAtLeast') && planAtLeast($plan, 'pro')) {
        $insights = getInsightsForModule($tenant_id, $store_id, $user_id, 'home', $plan, $role);
    }
} catch (Throwable $e) { $insights = []; }

// UI category mapping (1:1 copy from production chat.php — insightUICategory)
function v2insightUICat(array $ins): string {
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

// ──────────────────────────────────────────────────────────────
// S140.SIGNALS — v2generateBody($ins)
// 3-tier routing: topic_id prefix → category generic → fundamental_question fallback.
// Винаги връща непразен полезен body (≤500 chars, plain text) от data_json.
// Каталог: docs/SIGNALS_CATALOG_v1.md
// ──────────────────────────────────────────────────────────────
function v2TopicPrefix(string $topic_id): string {
    $p = preg_replace('/(_\d+)+$/', '', $topic_id);
    return $p ?? $topic_id;
}
function v2Plural(int $n, string $s, string $p): string { return $n === 1 ? $s : $p; }
function v2Money(float $v): string {
    if ($v >= 1000) return number_format($v, 0, '.', ' ') . ' EUR';
    return number_format($v, 2, '.', ' ') . ' EUR';
}
function v2Pct(float $v): string {
    return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.') . '%';
}
function v2Num(float $v): string {
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
}
function v2BodyByTopic(string $prefix, array $ins, ?array $data): string {
    $items = (is_array($data) && !empty($data['items']) && is_array($data['items'])) ? $data['items'] : [];
    $top = $items[0] ?? null;
    $pc = isset($ins['product_count']) ? (int)$ins['product_count'] : count($items);
    switch ($prefix) {
        case 'zero_stock_with_sales': {
            $lost = (float)($data['lost_per_day'] ?? ($ins['value_numeric'] ?? 0));
            $n = $top['name'] ?? '—'; $s = isset($top['sold_30d']) ? (int)$top['sold_30d'] : 0;
            return sprintf('%d %s са на 0 наличност, а се продаваха. Топ: „%s" (%d бр/30д). Без поръчка губим ~%s/ден от пропуснати продажби. Подготвена е батч поръчка — натисни „Поръчай".',
                $pc, v2Plural($pc,'артикул','артикула'), $n, $s, v2Money($lost));
        }
        case 'below_min_urgent': {
            $n = $top['name'] ?? '—'; $q = isset($top['qty']) ? (int)$top['qty'] : 0; $m = isset($top['min']) ? (int)$top['min'] : 0;
            return sprintf('%d %s са под зададения минимум. Най-критично: „%s" — %d бр срещу мин %d. Без поръчка ще паднат на нула и ще загубим следващите купувачи.',
                $pc, v2Plural($pc,'артикул','артикула'), $n, $q, $m);
        }
        case 'running_out_today': {
            $n = $top['name'] ?? '—'; $q = isset($top['qty']) ? (int)$top['qty'] : 0; $a = isset($top['avg_daily']) ? (float)$top['avg_daily'] : 0.0;
            return sprintf('%d %s имат запас ≤ дневните си продажби — днес ще свършат. Пример: „%s" — %d бр при %s бр/ден темп. Спешна поръчка или загубваме утрешните клиенти.',
                $pc, v2Plural($pc,'артикул','артикула'), $n, $q, v2Num($a));
        }
        case 'selling_at_loss': {
            $n = $top['name'] ?? '—'; $c = isset($top['cost']) ? (float)$top['cost'] : 0.0;
            $r = isset($top['retail']) ? (float)$top['retail'] : 0.0; $lpu = isset($top['loss_per_unit']) ? (float)$top['loss_per_unit'] : 0.0;
            return sprintf('%d %s имат retail < cost. Топ: „%s" — губим %s/брой (cost %s / retail %s). Всяка продажба = чиста загуба. Покачи цените или маркирай артикулите неактивни.',
                $pc, v2Plural($pc,'артикул','артикула'), $n, v2Money($lpu), v2Money($c), v2Money($r));
        }
        case 'no_cost_price': {
            $w = (int)($data['with_sales'] ?? 0); $n = $top['name'] ?? '—'; $s = isset($top['sold_30d']) ? (int)$top['sold_30d'] : 0;
            return sprintf('%d %s без записана доставна цена. От тях %d вече се продават — не знаем дали печелим или губим. Топ загадка: „%s" (%d бр/30д). Импортирай costs от последна доставка или въведи ръчно.',
                $pc, v2Plural($pc,'артикул','артикула'), $w, $n, $s);
        }
        case 'margin_below_15': {
            $n = $top['name'] ?? '—'; $m = isset($top['margin_pct']) ? (float)$top['margin_pct'] : 0.0;
            return sprintf('%d %s имат марж под 15%% — тънка червена линия. Най-нисък: „%s" — %s. Под този праг един дисконт или връщане яде печалбата. Прегледай качване на retail или смяна на доставчик.',
                $pc, v2Plural($pc,'артикул','артикула'), $n, v2Pct($m));
        }
        case 'seller_discount_killer': {
            $cnt = count($items); $n = $top['name'] ?? '—';
            $a = isset($top['avg_disc']) ? (float)$top['avg_disc'] : 0.0;
            $i = isset($top['items']) ? (int)$top['items'] : 0;
            $lm = isset($top['lost_money']) ? (float)$top['lost_money'] : 0.0;
            return sprintf('%d %s дават средно >20%% отстъпка. Топ: %s — %s ср., %d продажби за 30д, ~%s неполучени. Прегледай разрешените лимити в техния профил.',
                $cnt, v2Plural($cnt,'продавач','продавачи'), $n, v2Pct($a), $i, v2Money($lm));
        }
        case 'delivery_anomaly':
            return sprintf('%s. Pattern-ът се повтаря — не е инцидент. Прегледай с тях писмено или обмисли смяна на доставчик.', $ins['title'] ?? 'Доставчик системно недодава');
        case 'top_profit_30d': {
            $total = (float)($data['total_profit'] ?? ($ins['value_numeric'] ?? 0));
            $cnt = count($items); $n = $top['name'] ?? '—'; $p = isset($top['profit']) ? (float)$top['profit'] : 0.0;
            $share = $total > 0 ? round(($p / $total) * 100) : 0;
            return sprintf('Топ-%d артикула донесоха %s печалба за 30 дни. Шампион: „%s" (%s, ~%d%% от групата). Внимавай да не свършат — без тях ще усетим спад.',
                $cnt, v2Money($total), $n, v2Money($p), $share);
        }
        case 'profit_growth': {
            $cnt = count($items); $n = $top['name'] ?? '—';
            $now = isset($top['profit_now']) ? (float)$top['profit_now'] : 0.0;
            $prev = isset($top['profit_prev']) ? (float)$top['profit_prev'] : 0.0;
            $g = isset($top['growth_pct']) ? (float)$top['growth_pct'] : 0.0;
            return sprintf('%d %s удвояват или растат значимо печалбата си спрямо предходния период. Топ: „%s" — от %s на %s (+%s). Прицели се в зареждане и витрина за тях.',
                $cnt, v2Plural($cnt,'артикул','артикула'), $n, v2Money($prev), v2Money($now), v2Pct($g));
        }
        case 'volume_discount':
            return sprintf('%s. Спрямо средната ти цена от последните 90 дни. Зареди повече или преразгледай retail — има място за по-висок марж.', $ins['title'] ?? 'Доставчик ни дава по-добра цена');
        case 'stockout_risk_reduced': {
            $cnt = count($items); $n = $top['name'] ?? '—';
            $st = isset($top['in_stock']) ? (int)$top['in_stock'] : 0;
            $s = isset($top['sold_30d']) ? (int)$top['sold_30d'] : 0;
            return sprintf('%d %s, които бяха на нула, вече са попълнени. Топ: „%s" — %d бр в наличност, %d бр/30д темп. Възобнови витрина и проактивни препоръки.',
                $cnt, v2Plural($cnt,'бестселър','бестселъра'), $n, $st, $s);
        }
        case 'highest_margin': {
            $margins = array_map(fn($it) => (float)($it['margin_pct'] ?? 0), $items);
            $minM = $margins ? min($margins) : 0; $maxM = $margins ? max($margins) : 0;
            $cnt = count($items); $n = $top['name'] ?? '—'; $tm = isset($top['margin_pct']) ? (float)$top['margin_pct'] : 0.0;
            return sprintf('Топ-%d артикула с марж между %s и %s. Шампион: „%s" (%s). Това е „златен резерв" — една продажба тук компенсира 3 с нисък марж. Сложи ги на видно място.',
                $cnt, v2Pct($minM), v2Pct($maxM), $n, v2Pct($tm));
        }
        case 'trending_up': {
            $cnt = count($items); $n = $top['name'] ?? '—';
            $a7 = isset($top['avg_7d']) ? (float)$top['avg_7d'] : 0.0;
            $a30 = isset($top['avg_30d']) ? (float)$top['avg_30d'] : 0.0;
            $g = isset($top['growth_pct']) ? (float)$top['growth_pct'] : 0.0;
            return sprintf('%d %s продават значимо повече през последните 7 дни спрямо 30-дневната си средна. Топ: „%s" — от %s на %s бр/ден (+%s). Зареди преди да свършат.',
                $cnt, v2Plural($cnt,'артикул','артикула'), $n, v2Num($a30), v2Num($a7), v2Pct($g));
        }
        case 'loyal_customers': {
            $total = (float)($data['total'] ?? ($ins['value_numeric'] ?? 0));
            $cnt = count($items); $n = $top['name'] ?? '—';
            $p = isset($top['purchases']) ? (int)$top['purchases'] : 0;
            $tt = isset($top['total']) ? (float)$top['total'] : 0.0;
            return sprintf('%d %s направиха ≥3 покупки за 60 дни — общо %s. Топ: %s (%d покупки, %s). Помисли за лоялна оферта или SMS — те издържат магазина.',
                $cnt, v2Plural($cnt,'клиент','клиенти'), v2Money($total), $n, $p, v2Money($tt));
        }
        case 'basket_driver': {
            $cnt = count($items); $n = $top['name'] ?? '—';
            $bc = isset($top['basket_count']) ? (int)$top['basket_count'] : 0;
            return sprintf('%d %s често пътуват в комплект — присъстват в множество multi-item покупки. Топ: „%s" — в %d различни сметки за 30д. Сложи ги до касата или предложи комплект-оферта.',
                $cnt, v2Plural($cnt,'артикул','артикула'), $n, $bc);
        }
        case 'size_leader': {
            $cnt = count($items); $pn = $top['parent_name'] ?? '—'; $v = $top['variation'] ?? '—';
            $q = isset($top['qty_sold']) ? (int)$top['qty_sold'] : 0;
            return sprintf('За %d %s има 1 размер/цвят, който продава ≥3 пъти повече от останалите. Пример: „%s" — вариация „%s" с %d бр продадени. Зареди именно тази вариация, не „по равно".',
                $cnt, v2Plural($cnt,'артикул','артикула'), $pn, $v, $q);
        }
        case 'new_supplier_first':
            return sprintf('%s. Прегледай качество, lead time и pricing — има още малко база за оценка. След 3-та доставка системата ще даде reliability score.', $ins['title'] ?? 'Първа доставка от нов доставчик');
        case 'bestseller_low_stock': {
            $cnt = count($items); $n = $top['name'] ?? '—';
            $q = isset($top['qty']) ? (int)$top['qty'] : 0; $s = isset($top['sold_30d']) ? (int)$top['sold_30d'] : 0;
            return sprintf('%d %s с наличност близо или под минимум, но с реални продажби ≥5/30д. Топ: „%s" — %d бр налични, %d бр/30д темп. Батч поръчка е подготвена — натисни и изпрати.',
                $cnt, v2Plural($cnt,'бестселър','бестселъра'), $n, $q, $s);
        }
        case 'lost_demand_match': {
            $ta = (int)($data['total_asks'] ?? ($ins['value_numeric'] ?? 0));
            $cnt = count($items); $tq = $top['query'] ?? '—'; $n = $top['name'] ?? '—'; $t = isset($top['times']) ? (int)$top['times'] : 0;
            return sprintf('Клиенти питаха за %d %s общо %d пъти за 14 дни — нямахме ги в наличност. Топ заявка: „%s" (%d пъти, мач: „%s"). Поръчай или предложи аналог.',
                $cnt, v2Plural($cnt,'артикул','артикула'), $ta, $tq, $t, $n);
        }
        case 'order_stale':
            return sprintf('%s. Обади се за статус — може да е изгубена или забавена. Без действие рискуваме line-out на бестселърите.', $ins['title'] ?? 'Поръчка без доставка');
        case 'zombie_45d': {
            $tf = (float)($data['total_frozen'] ?? ($ins['value_numeric'] ?? 0));
            $cnt = count($items); $n = $top['name'] ?? '—';
            $q = isset($top['qty']) ? (int)$top['qty'] : 0; $d = isset($top['days_stale']) ? (int)$top['days_stale'] : 0;
            return sprintf('%d %s не са продавани повече от 45 дни. Замразен капитал: %s. Топ „мъртвец": „%s" — %d бр × %d дни. Промо -20%% освобождава касата и връща оборот.',
                $cnt, v2Plural($cnt,'артикул','артикула'), v2Money($tf), $n, $q, $d);
        }
        case 'declining_trend': {
            $cnt = count($items); $n = $top['name'] ?? '—';
            $a7 = isset($top['avg_7d']) ? (float)$top['avg_7d'] : 0.0;
            $a30 = isset($top['avg_30d']) ? (float)$top['avg_30d'] : 0.0;
            $d = isset($top['down_pct']) ? (float)$top['down_pct'] : 0.0;
            return sprintf('%d %s продават значимо по-малко през последните 7 дни спрямо 30-дневната средна. Топ спад: „%s" — от %s на %s бр/ден (-%s). НЕ зареждай — може да е сезонен край.',
                $cnt, v2Plural($cnt,'артикул','артикула'), $n, v2Num($a30), v2Num($a7), v2Pct($d));
        }
        case 'high_return_rate': {
            $cnt = count($items); $n = $top['name'] ?? '—';
            $s = isset($top['sold']) ? (int)$top['sold'] : 0;
            $r = isset($top['returned']) ? (int)$top['returned'] : 0;
            $rt = isset($top['rate']) ? (float)$top['rate'] : 0.0;
            return sprintf('%d %s имат >15%% връщания за 30д. Топ: „%s" — %d/%d връщания (%s). Преди да поръчаш още — провери защо (размер, качество, описание). Възможна е грешка в каталога.',
                $cnt, v2Plural($cnt,'артикул','артикула'), $n, $r, $s, v2Pct($rt));
        }
        case 'payment_due':
            return sprintf('%s. Без плащане доставчикът може да забави следващи доставки или да добави такса. Прехвърли сега и маркирай платена.', $ins['title'] ?? 'Плащане към доставчик наближава');
    }
    return '';
}
function v2BodyByCategory(string $cat, array $ins, ?array $data): string {
    $title = $ins['title'] ?? '';
    switch ($cat) {
        case 'tax':                          return sprintf('%s. Подготви документите със счетоводителя или ползвай експорт от Финанси. Просрочване води до глоби.', $title);
        case 'acc':                          return sprintf('%s. Прегледай преди затварянето на текущия отчетен период.', $title);
        case 'cash':                         return sprintf('%s. Прецени трансфер между сметки или ускоряване на събиране от длъжници.', $title);
        case 'biz': case 'biz_health': case 'biz_revenue':
            return sprintf('%s. Сравни с предходния период за context — ако трендът е стабилен 2+ седмици, заслужава активно действие.', $title);
        case 'inventory': case 'wh': case 'stock':
            return sprintf('%s. Прегледай реалните наличности на тези артикули.', $title);
        case 'xfer':                         return sprintf('%s. Предложение за трансфер между магазини — батч prepare-нат, остане потвърждение от приемащия.', $title);
        case 'pricing': case 'price': case 'price_change':
            return sprintf('%s. Прегледай ценова матрица — конкурентни цени, маржове, и cost база.', $title);
        case 'sup': case 'supplier':         return sprintf('%s. Профил на доставчика: lead time, надеждност и pricing. Сравни с алтернативи.', $title);
        case 'delivery': case 'delivery_anomaly_pattern': case 'payment_due_reminder':
        case 'new_supplier_first_delivery': case 'volume_discount_detected':
        case 'stockout_risk_reduction': case 'order_stale_no_delivery':
            return sprintf('%s. Виж детайли по поръчката/доставката за следваща стъпка.', $title);
        case 'ops': case 'order':            return sprintf('%s. Системата държи списъка готов за preview и редакция.', $title);
        case 'customer': case 'cust': case 'loyalty_repeat': case 'loyalty_churn':
        case 'loyalty_program': case 'loyalty_basket': case 'feedback':
            return sprintf('%s. SMS или специална оферта може да върне ангажираността.', $title);
        case 'new': case 'new_product':      return sprintf('%s. За нови артикули първите 14 дни са сигнал за adoption — без продажби ⇒ преразгледай цена/витрина/описание.', $title);
        case 'fashion': case 'shoes': case 'lingerie': case 'sport':
        case 'acc': case 'size': case 'product_mix':
            return sprintf('%s. Балансирай наличности по размер/цвят според реалния sales mix.', $title);
        case 'ss': case 'aw': case 'hol':
        case 'season_calendar': case 'season_holiday': case 'season_transition':
            return sprintf('%s. Сезонният прозорец е къс — подготвена витрина и зареждане 2-3 седмици преди пика.', $title);
        case 'weather_temp': case 'weather_rain': case 'weather_event':
        case 'weather_season_shift': case 'time':
            return sprintf('%s. Време-зависим сигнал — реагирай витрина/staff plan в рамките на 24-48ч.', $title);
        case 'promo':                        return sprintf('%s. Сравни с baseline (4 предходни седмици) преди да удължиш или повториш.', $title);
        case 'display_front': case 'display_zone': case 'display_visual':
        case 'floor': case 'basket': case 'pos':
            return sprintf('%s. Малка пренареждаща смяна на витрина или зона може да отключи 10-15%% повече продажби.', $title);
        case 'quality': case 'ret': case 'return_reason': case 'return_cost':
        case 'return_prevention': case 'return_supplier':
            return sprintf('%s. Връщанията носят и скрит cost (logistics, restock, photo). Идентифицирай причина → действие.', $title);
        case 'staff': case 'staff_cost': case 'staff_performance':
        case 'staff_schedule': case 'staff_training': case 'labor':
            return sprintf('%s. Прегледай профила/смяната, обсъди с екипа на 1:1.', $title);
        case 'expense_rent': case 'expense_fixed': case 'expense_per_sale': case 'expense_compare':
            return sprintf('%s. Health rule: разходите трябва да растат по-бавно от оборота. Иначе маржът се топи.', $title);
        case 'ws':                           return sprintf('%s. Wholesale има по-нисък марж — поддържай дисциплина с просрочия и кредитни лимити.', $title);
        case 'cross_store':                  return sprintf('%s. Трансфер между магазини е по-евтин от нова поръчка и решава проблема веднага.', $title);
        case 'data_quality':                 return sprintf('%s. Без чисти данни AI препоръките губят сила — върни се за 5 мин днес.', $title);
        case 'onboard':                      return sprintf('%s. Следвай suggested стъпките — всеки скок ускорява value time.', $title);
        case 'trend': case 'demand':         return sprintf('%s. 7-дневен прозорец срещу 30-дневна средна — реагирай само ако трендът се потвърди 2+ дни.', $title);
        case 'anomaly':                      return sprintf('%s. Аномалия в логовете — отвори транзакцията/записа за детайли. Може да е грешка, може да е fraud.', $title);
    }
    return '';
}
function v2BodyByFQ(string $fq, array $ins): string {
    $title = $ins['title'] ?? '';
    $pc = isset($ins['product_count']) ? (int)$ins['product_count'] : 0;
    $sfx = $pc > 0 ? sprintf(' Засегнати ~%d %s.', $pc, v2Plural($pc,'позиция','позиции')) : '';
    switch ($fq) {
        case 'loss':       return sprintf('%s.%s Това е активна загуба — реагирай в рамките на дни, не седмици.', $title, $sfx);
        case 'loss_cause': return sprintf('%s.%s Това обяснява откъде идва загубата — поправяме корена, не симптома.', $title, $sfx);
        case 'gain':       return sprintf('%s.%s Това е реален принос — защити го с наличност и видимост.', $title, $sfx);
        case 'gain_cause': return sprintf('%s.%s Това обяснява защо печелиш — копирай pattern-а върху съседните артикули.', $title, $sfx);
        case 'order':      return sprintf('%s.%s Подготвена е препоръка за поръчка — прегледай преди финален send.', $title, $sfx);
        case 'anti_order': return sprintf('%s.%s НЕ зареждай повече — освободи капитал с промо или маркирай неактивни.', $title, $sfx);
    }
    return '';
}
/**
 * v2generateBody — главна функция. Винаги връща непразен полезен body string ≤500 ch.
 * Routing: topic_id prefix → category → fundamental_question fallback → title+count.
 * Виж docs/SIGNALS_CATALOG_v1.md за пълния каталог и body templates.
 */
function v2generateBody(array $ins): string {
    $tid = (string)($ins['topic_id'] ?? '');
    $cat = (string)($ins['category'] ?? '');
    $fq  = (string)($ins['fundamental_question'] ?? '');
    $raw = $ins['data_json'] ?? null;
    $data = null;
    if (is_array($raw)) $data = $raw;
    elseif (is_string($raw) && $raw !== '') { $tmp = json_decode($raw, true); if (is_array($tmp)) $data = $tmp; }
    $body = v2BodyByTopic(v2TopicPrefix($tid), $ins, $data);
    if ($body === '') $body = v2BodyByCategory($cat, $ins, $data);
    if ($body === '') $body = v2BodyByFQ($fq, $ins);
    if ($body === '') {
        $body = $ins['title'] ?? '';
        $pc = isset($ins['product_count']) ? (int)$ins['product_count'] : 0;
        if ($pc > 0) $body .= ' · засегнати ' . $pc . ' ' . v2Plural($pc,'артикул','артикула') . '.';
    }
    if (mb_strlen($body) > 500) $body = mb_substr($body, 0, 497) . '...';
    return $body;
}

// Top 1 insight per fundamental_question (6 категории)
$by_fq = [];
foreach ($insights as $ins) {
    $fq = $ins['fundamental_question'] ?? '';
    if ($fq && !isset($by_fq[$fq])) { $by_fq[$fq] = $ins; }
}
$narrative_order = ['loss', 'loss_cause', 'gain', 'gain_cause', 'order', 'anti_order'];
$briefing = [];
foreach ($narrative_order as $fq) {
    if (isset($by_fq[$fq])) { $briefing[] = $by_fq[$fq]; }
}
$remaining = max(0, count($insights) - count($briefing));

// Meta за всяка fundamental_question (q-клас + име + SVG icon)
$v2_fq_meta = [
    'loss'       => ['q'=>'q1', 'name'=>'ФИНАНСИ · КАКВО ГУБЯ',     'svg'=>'<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'],
    'loss_cause' => ['q'=>'q2', 'name'=>'ПРИЧИНА · ОТ КАКВО ГУБЯ',  'svg'=>'<path d="M21 16V8a2 2 0 00-1-1.7l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.7l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.3 7 12 12 20.7 7"/>'],
    'gain'       => ['q'=>'q3', 'name'=>'ПРОДАЖБИ · КАКВО ПЕЧЕЛЯ',  'svg'=>'<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>'],
    'gain_cause' => ['q'=>'q4', 'name'=>'ПРИЧИНА · ОТ КАКВО ПЕЧЕЛЯ', 'svg'=>'<polygon points="6 3 18 3 22 9 12 22 2 9 6 3"/><line x1="11" y1="3" x2="8" y2="9"/><line x1="13" y1="3" x2="16" y2="9"/><line x1="2" y1="9" x2="22" y2="9"/>'],
    'order'      => ['q'=>'q5', 'name'=>'ПОРЪЧКИ · ПОРЪЧАЙ',         'svg'=>'<circle cx="9" cy="21" r="1.5"/><circle cx="18" cy="21" r="1.5"/><path d="M3 3h2l2.7 12.3a2 2 0 002 1.7h7.6a2 2 0 002-1.5L21 8H6"/>'],
    'anti_order' => ['q'=>'q6', 'name'=>'СКЛАД · НЕ ПОРЪЧВАЙ',        'svg'=>'<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>'],
];
?>
<!DOCTYPE html>
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
  position: relative; font-size: 17px; letter-spacing: -0.01em;
  display: inline-flex; align-items: baseline; gap: 0;
  filter: drop-shadow(0 0 12px hsl(var(--hue1) 70% 50% / 0.4));
}
.rms-brand .brand-1 {
  font-weight: 900;
  background: linear-gradient(90deg, hsl(var(--hue1) 80% 60%), hsl(var(--hue2) 80% 60%), hsl(var(--hue3) 70% 55%), hsl(var(--hue2) 80% 60%), hsl(var(--hue1) 80% 60%));
  background-size: 200% auto;
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
</head>
<body>

<div class="aurora" aria-hidden="true">
  <div class="aurora-blob"></div><div class="aurora-blob"></div><div class="aurora-blob"></div>
</div>

<header class="rms-header">
  <a class="rms-brand"><span class="brand-1">RunMyStore</span><span class="brand-2">.ai</span></a>
  <span class="rms-plan-badge">PRO</span>
  <div class="rms-header-spacer"></div>
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

<!-- Sub-header bar: store toggle + where + mode toggle (sticky) -->
<div class="rms-subbar">
  <?php if (count($all_stores) > 1): ?>
  <select class="rms-store-toggle" aria-label="Смени обект" onchange="location.href='?store='+this.value" style="-webkit-appearance:none;-moz-appearance:none;appearance:none;background-image:url(&quot;data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5'><polyline points='6 9 12 15 18 9'/></svg>&quot;);background-repeat:no-repeat;background-position:right 8px center;background-size:12px 12px;padding-right:28px;">
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
  <span class="subbar-where">Начало</span>
  <a class="lb-mode-toggle" href="/life-board.php" title="Лесен режим">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    <span>Лесен</span>
  </a>
</div>

<main class="app">

  <!-- ═══ S82-DASH (production prozor за печалба) — БЕЗ compact weather ═══ -->
  <div class="glass sm s82-dash qd">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="s82-dash-top">
      <span class="s82-dash-period-label"><span id="revLabel">ДНЕС</span> · <?= htmlspecialchars($store_name) ?></span>
    </div>
    <div class="s82-dash-numrow">
      <span class="s82-dash-num" id="revNum"><?= number_format($d0['rev'], 0, '.', ' ') ?></span>
      <span class="s82-dash-cur"><?= $cs ?></span>
      <span class="s82-dash-pct<?= $cmp_today < 0 ? ' neg' : ($cmp_today == 0 ? ' zero' : '') ?>" id="revPct"><?= $cmp_sign . $cmp_today ?>%</span>
    </div>
    <div class="s82-dash-meta" id="revMeta"><?= (int)$d0['cnt'] ?> продажби · vs <?= (int)$d0p['cnt'] ?> вчера</div>
    <?php if ($role === 'owner' && $confidence_pct < 100): ?>
    <div class="conf-warn" id="confWarn" style="display:none; align-items:center; gap:8px; padding:8px 10px; margin-top:8px; border-radius:var(--radius-sm); background:rgba(251,191,36,0.10); border:1px solid rgba(251,191,36,0.25); font-size:11px; line-height:1.4;">
      <svg viewBox="0 0 24 24" style="width:14px; height:14px; flex-shrink:0; fill:none; stroke:hsl(38 80% 50%); stroke-width:2;"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
      <span>Приблизителна печалба — данни за <?= $confidence_pct ?>% от артикулите (<?= $with_cost ?>/<?= $total_products ?>). <a href="inventory.php" style="color:inherit; text-decoration:underline;">Инвентаризация →</a></span>
    </div>
    <?php endif; ?>
    <div class="s82-dash-pills">
      <button type="button" class="s82-dash-pill rev-pill active" data-period="today"  onclick="v2setPeriod('today',this)">Днес</button>
      <button type="button" class="s82-dash-pill rev-pill"        data-period="7d"     onclick="v2setPeriod('7d',this)">7 дни</button>
      <button type="button" class="s82-dash-pill rev-pill"        data-period="30d"    onclick="v2setPeriod('30d',this)">30 дни</button>
      <button type="button" class="s82-dash-pill rev-pill"        data-period="365d"   onclick="v2setPeriod('365d',this)">365 дни</button>
      <?php if ($role === 'owner'): ?>
      <span class="s82-dash-divider"></span>
      <button type="button" class="s82-dash-pill rev-pill active" id="modeRev"    onclick="v2setMode('rev')">Оборот</button>
      <button type="button" class="s82-dash-pill rev-pill"        id="modeProfit" onclick="v2setMode('profit')">Печалба</button>
      <?php endif; ?>
    </div>
  </div>

    <!-- ═══ AI STUDIO ROW ═══ -->
  <div class="studio-row">
    <a class="glass sm studio-btn" href="ai-studio.php">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <span class="studio-icon">
        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      </span>
      <span class="studio-text">
        <span class="studio-label">AI Studio</span>
        <span class="studio-sub"><?= $ai_studio_count > 0 ? $ai_studio_count . ' чакащи' : 'всичко готово' ?></span>
      </span>
      <?php if ($ai_studio_count > 0): ?>
      <span class="studio-badge"><?= $ai_studio_count > 99 ? '99+' : $ai_studio_count ?></span>
      <?php endif; ?>
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
        <div class="wfc-title">Прогноза за времето</div>
        <div class="wfc-sub">AI препоръки за седмицата</div>
      </div>
    </div>

    <!-- Range tabs -->
    <div class="wfc-tabs">
      <button class="wfc-tab active" data-tab="7" onclick="wfcSetRange('7')">7 дни</button>
      <button class="wfc-tab" data-tab="14" onclick="wfcSetRange('14')">14 дни</button>
    </div>

    <!-- Days strip — динамично от $weather_week (14 дни max) -->
    <div class="wfc-days" data-range="7">
      <?php if (empty($weather_week)): ?>
      <div class="wfc-day" style="opacity:.5">
        <div class="wfc-day-name">—</div>
        <div class="wfc-day-temp">Няма данни</div>
      </div>
      <?php else: foreach ($weather_week as $i => $w): ?>
      <div class="wfc-day <?= $i === 0 ? 'today ' : '' ?><?= v2weatherClass($w['weather_code']) ?>">
        <div class="wfc-day-name"><?= htmlspecialchars(v2bgDay($w['forecast_date'])) ?></div>
        <div class="wfc-day-ic"><?= v2weatherSvg($w['weather_code']) ?></div>
        <div class="wfc-day-temp"><?= (int)round($w['temp_max']) ?>°<small>/<?= (int)round($w['temp_min']) ?></small></div>
        <div class="wfc-day-rain <?= (int)$w['precipitation_prob'] < 30 ? 'dry' : '' ?>"><?= (int)$w['precipitation_prob'] ?>%</div>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <?php if (!empty($weather_suggestion)): ?>
    <!-- AI recs divider -->
    <div class="wfc-recs-divider"><span>AI ПРЕПОРЪКА</span></div>

    <!-- 1 динамична препоръка на база $weather_suggestion (от business_type + температура + дъжд) -->
    <div class="wfc-rec window">
      <span class="wfc-rec-ic">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
      </span>
      <div class="wfc-rec-text">
        <span class="wfc-rec-label">ПРЕПОРЪКА</span>
        <div class="wfc-rec-body"><?= htmlspecialchars($weather_suggestion) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="wfc-source">
      <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12.01" y2="8"/><line x1="11" y1="12" x2="12" y2="16"/></svg>
      <span>OPEN-METEO · <?= date('H:i') ?></span>
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
        <div class="help-title">AI ти помага</div>
        <div class="help-sub">Питай каквото искаш</div>
      </div>
    </div>

    <div class="help-body">
      Питай за <b>продажби, склад, доставки</b>. Получаваш отговор веднага.
    </div>

    <div class="help-chips-label">Пробвай:</div>
    <div class="help-chips">
      <button class="help-chip" onclick="rmsOpenChat(event)"><span class="help-chip-q">?</span><span>Какво ми тежи на склада</span></button>
      <button class="help-chip" onclick="rmsOpenChat(event)"><span class="help-chip-q">?</span><span>Кои са топ продавачите</span></button>
      <button class="help-chip" onclick="rmsOpenChat(event)"><span class="help-chip-q">?</span><span>Какво да поръчам</span></button>
      <button class="help-chip" onclick="rmsOpenChat(event)"><span class="help-chip-q">?</span><span>Защо приходите паднаха</span></button>
      <button class="help-chip" onclick="rmsOpenChat(event)"><span class="help-chip-q">?</span><span>Покажи ми артикул</span></button>
      <button class="help-chip" onclick="rmsOpenChat(event)"><span class="help-chip-q">?</span><span>Какво продаваме днес</span></button>
    </div>

    <div class="help-video-ph">
      <span class="help-video-ic">
        <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
      </span>
      <div class="help-video-text">
        <div class="help-video-title">Видео урок</div>
        <div class="help-video-sub">скоро</div>
      </div>
    </div>

    <a class="help-link-row">
      <span>Виж всичко което AI може →</span>
      <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <!-- ═══ LIFE BOARD HEADER + FILTER PILLS + 12 CARDS ═══ -->
  <div class="lb-header">
    <div class="lb-title">
      <div class="lb-title-orb"></div>
      <span class="lb-title-text">Life Board</span>
    </div>
    <span class="lb-count"><?= count($briefing) ?> теми · <?= date('H:i') ?></span>
  </div>

  <!-- Filter pills (модули) — динамични по реалните 5 UI категории (production logic) -->
  <?php if (!empty($insights)):
      $v2_cat_meta = [
          'sales'     => ['name' => 'Продажби', 'svg' => '<circle cx="9" cy="21" r="1.5"/><circle cx="18" cy="21" r="1.5"/><path d="M3 3h2l2.7 12.3a2 2 0 002 1.7h7.6a2 2 0 002-1.5L21 8H6"/>'],
          'warehouse' => ['name' => 'Склад',    'svg' => '<path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>'],
          'products'  => ['name' => 'Продукти', 'svg' => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>'],
          'finance'   => ['name' => 'Финанси',  'svg' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'],
          'expenses'  => ['name' => 'Разходи',  'svg' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
      ];
      $v2_cat_counts = ['sales'=>0, 'warehouse'=>0, 'products'=>0, 'finance'=>0, 'expenses'=>0];
      foreach ($insights as $ins) {
          $uiCat = v2insightUICat($ins);
          if (isset($v2_cat_counts[$uiCat])) $v2_cat_counts[$uiCat]++;
      }
  ?>
  <div class="fp-row">
    <button class="fp-pill active">Всички <span class="fp-count"><?= count($insights) ?></span></button>
    <?php foreach ($v2_cat_meta as $cat => $meta): if (empty($v2_cat_counts[$cat])) continue; ?>
    <button class="fp-pill">
      <svg viewBox="0 0 24 24"><?= $meta['svg'] ?></svg>
      <?= htmlspecialchars($meta['name']) ?> <span class="fp-count"><?= $v2_cat_counts[$cat] ?></span>
    </button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ═══ LIFE BOARD CARDS — динамичен loop от $briefing ═══ -->
  <?php if (empty($briefing)): ?>
  <div class="glass sm lb-card q3">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">ВСИЧКО Е НАРЕД</span>
        <span class="lb-collapsed-title">Няма нищо спешно — попитай AI каквото искаш</span>
      </div>
    </div>
  </div>
  <?php else: foreach ($briefing as $ins):
      $fq = $ins['fundamental_question'] ?? 'gain';
      $meta = $v2_fq_meta[$fq] ?? $v2_fq_meta['gain'];
      $card_title_js = htmlspecialchars(addslashes($ins['title'] ?? ''), ENT_QUOTES);
      // S140.SIGNALS: detail_text първо, fallback към v2generateBody() (topic-aware body от data_json)
      $card_body = trim((string)($ins['detail_text'] ?? ''));
      if ($card_body === '') $card_body = v2generateBody($ins);
      $card_body_html = htmlspecialchars($card_body);
      $action_lbl = '';
      if ($fq === 'order' || $fq === 'gain_cause') $action_lbl = 'Поръчай';
      elseif ($fq === 'loss' || $fq === 'loss_cause') $action_lbl = 'Виж';
      elseif ($fq === 'gain') $action_lbl = 'Покажи';
      else $action_lbl = 'Виж';
  ?>
  <div class="glass sm lb-card <?= $meta['q'] ?>" data-topic="<?= htmlspecialchars($ins['topic_id'] ?? '', ENT_QUOTES) ?>">
    <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed" onclick="v2lbToggleCard(event, this)" style="cursor:pointer">
      <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><?= $meta['svg'] ?></svg></span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini"><?= htmlspecialchars($meta['name']) ?></span>
        <span class="lb-collapsed-title"><?= htmlspecialchars($ins['title'] ?? '') ?></span>
      </div>
      <button class="lb-expand-btn" aria-label="Разшири"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>
    <div class="lb-expanded">
      <div class="lb-body"><?= $card_body_html ?></div>
      <div class="lb-actions">
        <button class="lb-action" onclick="v2openCardQ('<?= $card_title_js ?>')">Защо</button>
        <button class="lb-action" onclick="v2openCardQ('Покажи ми: <?= $card_title_js ?>')">Покажи</button>
        <button class="lb-action primary" onclick="v2openCardQ('<?= $action_lbl ?>: <?= $card_title_js ?>')"><?= htmlspecialchars($action_lbl) ?> →</button>
      </div>
      <div class="lb-feedback">
        <span class="lb-fb-label">Полезно?</span>
        <button class="lb-fb-btn up" onclick="v2lbFb(event,this,'up')" aria-label="Полезно"><svg viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3zM7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3"/></svg></button>
        <button class="lb-fb-btn down" onclick="v2lbFb(event,this,'down')" aria-label="Неполезно"><svg viewBox="0 0 24 24"><path d="M10 15v4a3 3 0 003 3l4-9V2H5.72a2 2 0 00-2 1.7l-1.38 9a2 2 0 002 2.3zM17 2h2.67A2.31 2.31 0 0122 4v7a2.31 2.31 0 01-2.33 2H17"/></svg></button>
        <button class="lb-fb-btn hmm" onclick="v2lbFb(event,this,'hmm')" aria-label="Неясно" style="color:hsl(38 80% 50%)"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></button>
      </div>
    </div>
  </div>
  <?php endforeach; endif; ?>

  <?php if (!empty($briefing) && $remaining > 0): ?>
  <div class="see-more-mini" onclick="v2openCardQ('Покажи ми всички AI препоръки за днес')" style="cursor:pointer">Виж всичко <?= count($insights) ?> →</div>
  <?php endif; ?>

</main>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- S140.OVERLAY: 75vh AI CHAT OVERLAY (ported from chat.php)             -->
<!-- HTML/CSS/JS изолирани в .chat-overlay-* namespace — не пипат основния -->
<!-- layout. JS секцията долу свързва rmsOpenChat()/v2openCardQ().         -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<style>
/* ─── S140.OVERLAY: namespace .chat-overlay-* (ported from design-kit/components.css) ─── */
.chat-overlay-bg{
    position:fixed;inset:0;
    background:rgba(5,8,20,.55);
    backdrop-filter:blur(16px) saturate(.85);
    -webkit-backdrop-filter:blur(16px) saturate(.85);
    z-index:200;opacity:0;pointer-events:none;
    transition:opacity .3s var(--ease, cubic-bezier(0.5,1,0.89,1))
}
.chat-overlay-bg.open{opacity:1;pointer-events:auto}
.chat-overlay-panel{
    position:fixed;bottom:-80vh;left:0;right:0;
    max-width:480px;margin:0 auto;
    height:75vh;z-index:210;
    display:flex;flex-direction:column;
    transition:bottom .35s var(--ease, cubic-bezier(0.5,1,0.89,1));
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
.chat-overlay-panel.open{bottom:0}
.chat-overlay-panel::before{
    content:'';position:absolute;
    top:0;left:20%;right:20%;height:1px;
    background:linear-gradient(90deg,transparent,hsl(var(--hue1) 70% 65% / .7),transparent);
    z-index:5;pointer-events:none
}
.chat-overlay-handle{
    position:absolute;top:6px;left:50%;
    transform:translateX(-50%);
    width:38px;height:4px;border-radius:var(--radius-pill);
    background:rgba(255,255,255,.18);
    z-index:6;pointer-events:none
}
.chat-overlay-header{
    display:flex;align-items:center;gap:10px;
    padding:18px 14px 10px;
    border-bottom:1px solid rgba(255,255,255,.05);
    flex-shrink:0;position:relative;z-index:5
}
.chat-overlay-back{
    width:32px;height:32px;border-radius:50%;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.06);
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;color:var(--text-muted);flex-shrink:0
}
.chat-overlay-back svg{width:14px;height:14px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.chat-overlay-title-wrap{flex:1;display:flex;align-items:center;gap:8px;min-width:0}
.chat-overlay-avatar{
    width:32px;height:32px;border-radius:50%;
    background:linear-gradient(135deg,hsl(var(--hue1) 65% 50%),hsl(var(--hue2) 70% 45%));
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 0 14px hsl(var(--hue1) 60% 50% / .45);
    flex-shrink:0
}
.chat-overlay-avatar svg{width:14px;height:14px;fill:white}
.chat-overlay-title{font-size:14px;font-weight:800;color:#e2e8f0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chat-overlay-sub{font-size:9px;color:var(--text-muted);font-weight:600}
.chat-overlay-close{
    width:32px;height:32px;border-radius:50%;
    background:rgba(239,68,68,.08);
    border:1px solid rgba(239,68,68,.2);
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;color:#fca5a5;flex-shrink:0
}
.chat-overlay-close svg{width:14px;height:14px;stroke:currentColor;stroke-width:2.5;fill:none;stroke-linecap:round}
.chat-overlay-messages{
    flex:1;overflow-y:auto;padding:12px 12px 8px;
    -webkit-overflow-scrolling:touch;scrollbar-width:none;
    display:flex;flex-direction:column;gap:8px
}
.chat-overlay-messages::-webkit-scrollbar{display:none}
.chat-overlay-empty{text-align:center;padding:40px 20px;color:var(--text-muted);font-size:11px}
.chat-overlay-empty-t{font-size:14px;font-weight:800;color:hsl(var(--hue1) 60% 80%);margin-bottom:6px}
.chat-overlay-msg-group{display:flex;flex-direction:column;gap:4px}
.chat-overlay-msg-meta{font-size:9px;color:var(--text-muted);font-weight:600;display:flex;align-items:center;gap:4px;padding:0 2px}
.chat-overlay-msg-meta.right{justify-content:flex-end}
.chat-overlay-msg-meta svg{width:10px;height:10px;fill:none;stroke:hsl(var(--hue1) 60% 70%);stroke-width:2}
.chat-overlay-msg-ai{
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
.chat-overlay-msg-user{
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
.chat-overlay-typing{
    display:none;padding:10px 14px;
    background:linear-gradient(135deg,hsl(var(--hue1) 30% 15% / .85),hsl(var(--hue1) 30% 10% / .7));
    border:1px solid hsl(var(--hue1) 35% 25% / .4);
    border-radius:4px 16px 16px 16px;
    width:fit-content;align-self:flex-start
}
.chat-overlay-typing.on{display:block}
.chat-overlay-typing-dots{display:flex;gap:4px;align-items:center}
.chat-overlay-typing-dot{width:5px;height:5px;border-radius:50%;background:hsl(var(--hue1) 60% 70%);animation:chatOverlayTdot 1.2s infinite}
.chat-overlay-typing-dot:nth-child(2){animation-delay:.2s}
.chat-overlay-typing-dot:nth-child(3){animation-delay:.4s}
@keyframes chatOverlayTdot{0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-4px);opacity:1}}
.chat-overlay-rec-bar{
    display:none;align-items:center;gap:8px;
    padding:8px 14px;
    background:linear-gradient(90deg,rgba(239,68,68,.08),rgba(239,68,68,.03));
    border-top:1px solid rgba(239,68,68,.15);
    flex-shrink:0
}
.chat-overlay-rec-bar.on{display:flex}
.chat-overlay-rec-dot{
    width:9px;height:9px;border-radius:50%;
    background:#ef4444;
    animation:chatOverlayRecpulse 1s infinite;
    box-shadow:0 0 10px rgba(239,68,68,.7)
}
@keyframes chatOverlayRecpulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.25)}}
.chat-overlay-rec-label{font-size:10px;font-weight:900;color:#fca5a5;text-transform:uppercase;letter-spacing:.08em}
.chat-overlay-rec-transcript{font-size:12px;color:#e2e8f0;flex:1;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chat-overlay-input-area{padding:8px 10px calc(12px + env(safe-area-inset-bottom));flex-shrink:0}
.chat-overlay-input-inner{
    display:flex;align-items:center;gap:8px;
    padding:8px 10px;border-radius:var(--radius-pill);
    background:linear-gradient(135deg,hsl(var(--hue1) 35% 15% / .85),hsl(var(--hue2) 35% 12% / .7));
    border:1px solid hsl(var(--hue1) 30% 25% / .6);
    box-shadow:0 4px 16px rgba(0,0,0,.3),0 0 12px hsl(var(--hue1) 60% 45% / .15)
}
.chat-overlay-waves{display:flex;gap:2px;align-items:flex-end;height:14px;flex-shrink:0}
.chat-overlay-wave-bar{width:3px;border-radius:var(--radius-pill);animation:chatOverlayWavebar 1.2s ease-in-out infinite}
.chat-overlay-wave-bar:nth-child(1){height:5px;background:hsl(var(--hue1) 60% 50%);animation-delay:0s}
.chat-overlay-wave-bar:nth-child(2){height:9px;background:hsl(var(--hue1) 65% 55%);animation-delay:.15s}
.chat-overlay-wave-bar:nth-child(3){height:14px;background:hsl(var(--hue1) 70% 60%);animation-delay:.3s}
.chat-overlay-wave-bar:nth-child(4){height:9px;background:hsl(var(--hue1) 65% 55%);animation-delay:.45s}
.chat-overlay-wave-bar:nth-child(5){height:5px;background:hsl(var(--hue1) 60% 50%);animation-delay:.6s}
@keyframes chatOverlayWavebar{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.4)}}
.chat-overlay-ta{
    flex:1;background:transparent;border:none;
    color:#f1f5f9;font-size:13px;padding:6px 4px;
    font-family:inherit;outline:none;resize:none;
    max-height:80px;line-height:1.4;font-weight:500
}
.chat-overlay-ta::placeholder{color:var(--text-muted);font-weight:600}
.chat-overlay-mic{
    width:34px;height:34px;border-radius:50%;
    flex-shrink:0;position:relative;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;
    background:linear-gradient(135deg,hsl(var(--hue1) 65% 50%),hsl(var(--hue2) 70% 45%));
    box-shadow:0 0 14px hsl(var(--hue1) 60% 45% / .45),inset 0 1px 0 rgba(255,255,255,.18);
    transition:all .2s
}
.chat-overlay-mic.rec{
    background:linear-gradient(135deg,#ef4444,#dc2626);
    box-shadow:0 0 20px rgba(239,68,68,.55)
}
.chat-overlay-mic svg{width:16px;height:16px;color:white;stroke:white;fill:none;stroke-width:2;stroke-linecap:round}
.chat-overlay-voice-ring{
    position:absolute;border-radius:50%;
    border:1.5px solid rgba(255,255,255,.3);
    opacity:0;pointer-events:none
}
.chat-overlay-mic.rec .chat-overlay-voice-ring{border-color:rgba(255,255,255,.5)}
.chat-overlay-vr1{width:22px;height:22px;animation:chatOverlayVrpulse 2s 0s ease-in-out infinite}
.chat-overlay-vr2{width:32px;height:32px;animation:chatOverlayVrpulse 2s .3s ease-in-out infinite}
.chat-overlay-vr3{width:44px;height:44px;animation:chatOverlayVrpulse 2s .6s ease-in-out infinite}
@keyframes chatOverlayVrpulse{0%{transform:scale(.5);opacity:.7}100%{transform:scale(1.6);opacity:0}}
.chat-overlay-send{
    width:34px;height:34px;border-radius:50%;
    background:linear-gradient(135deg,#10b981,#059669);
    border:none;color:white;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;transition:opacity .2s;
    box-shadow:0 0 12px rgba(16,185,129,.35)
}
.chat-overlay-send:disabled{opacity:.2;box-shadow:none}
.chat-overlay-send svg{width:14px;height:14px;fill:white}
.chat-overlay-actions{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.chat-overlay-action-btn{
    padding:6px 12px;border-radius:var(--radius-pill);
    background:linear-gradient(135deg,hsl(var(--hue1) 55% 35%),hsl(var(--hue2) 60% 28%));
    border:1px solid hsl(var(--hue1) 55% 45% / .5);
    color:#fff;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit
}
/* ─── Light theme overrides ─── */
html[data-theme="light"] .chat-overlay-bg{
    background:rgba(240,243,250,.55);
    backdrop-filter:blur(16px) saturate(.95);
    -webkit-backdrop-filter:blur(16px) saturate(.95);
}
html[data-theme="light"] .chat-overlay-panel{
    background:
        linear-gradient(235deg,hsl(var(--hue1) 70% 93% / .9),hsl(var(--hue1) 70% 96% / .7) 33%),
        linear-gradient(45deg,hsl(var(--hue2) 70% 93% / .9),hsl(var(--hue2) 70% 96% / .7) 33%),
        linear-gradient(hsl(215deg 40% 99% / .96));
    border:1px solid hsl(var(--hue2),25%,80%);
    border-bottom:none;
    box-shadow:
        0 -20px 60px hsl(var(--hue2) 40% 60% / .18),
        0 -8px 40px hsl(var(--hue1) 60% 55% / .12),
        inset 0 1px 0 hsl(var(--hue1) 60% 70% / .3);
}
html[data-theme="light"] .chat-overlay-panel::before{
    background:linear-gradient(90deg,transparent,hsl(var(--hue1) 70% 60% / .6),transparent);
}
html[data-theme="light"] .chat-overlay-handle{background:rgba(15,23,42,.18)}
html[data-theme="light"] .chat-overlay-header{border-bottom-color:rgba(15,23,42,.06)}
html[data-theme="light"] .chat-overlay-back{
    background:rgba(15,23,42,.04);
    border-color:rgba(15,23,42,.10);
    color:var(--text-muted);
}
html[data-theme="light"] .chat-overlay-close{
    background:rgba(15,23,42,.04);
    border-color:rgba(15,23,42,.10);
    color:#dc2626;
}
html[data-theme="light"] .chat-overlay-title{color:#0f172a}
html[data-theme="light"] .chat-overlay-sub{color:var(--text-muted)}
html[data-theme="light"] .chat-overlay-empty{color:var(--text-muted)}
html[data-theme="light"] .chat-overlay-empty-t{color:hsl(var(--hue1) 55% 45%)}
html[data-theme="light"] .chat-overlay-msg-ai{
    background:linear-gradient(135deg,hsl(var(--hue1) 60% 96%),hsl(var(--hue1) 60% 93%));
    border:1px solid hsl(var(--hue1) 50% 80%);
    color:#1e293b;
    box-shadow:
        0 2px 12px hsl(var(--hue2) 40% 60% / .10),
        0 0 16px hsl(var(--hue1) 60% 70% / .10),
        inset 0 1px 0 hsl(var(--hue1) 60% 95%);
}
html[data-theme="light"] .chat-overlay-msg-user{
    background:linear-gradient(135deg,hsl(var(--hue1) 70% 58%),hsl(var(--hue2) 70% 52%));
    border:1px solid hsl(var(--hue1) 70% 55%);
    color:#fff;
    box-shadow:
        0 2px 12px hsl(var(--hue1) 60% 50% / .28),
        0 0 14px hsl(var(--hue1) 60% 55% / .22),
        inset 0 1px 0 rgba(255,255,255,.18);
}
html[data-theme="light"] .chat-overlay-msg-meta{color:var(--text-muted)}
html[data-theme="light"] .chat-overlay-msg-meta svg{stroke:hsl(var(--hue1) 60% 50%)}
html[data-theme="light"] .chat-overlay-typing{
    background:linear-gradient(135deg,hsl(var(--hue1) 60% 96%),hsl(var(--hue1) 60% 93%));
    border:1px solid hsl(var(--hue1) 50% 80%);
}
html[data-theme="light"] .chat-overlay-typing-dot{background:hsl(var(--hue1) 60% 55%)}
html[data-theme="light"] .chat-overlay-rec-bar{
    background:linear-gradient(90deg,rgba(239,68,68,.10),rgba(239,68,68,.04));
    border-top-color:rgba(239,68,68,.20);
}
html[data-theme="light"] .chat-overlay-rec-label{color:#dc2626}
html[data-theme="light"] .chat-overlay-rec-transcript{color:#1e293b}
html[data-theme="light"] .chat-overlay-input-inner{
    background:linear-gradient(135deg,hsl(var(--hue1) 60% 95% / .95),hsl(var(--hue2) 60% 95% / .90));
    border:1px solid hsl(var(--hue1) 40% 75%);
    box-shadow:
        0 4px 16px hsl(var(--hue2) 30% 60% / .18),
        0 0 12px hsl(var(--hue1) 60% 70% / .15);
}
html[data-theme="light"] .chat-overlay-ta{color:#0f172a}
html[data-theme="light"] .chat-overlay-ta::placeholder{color:var(--text-muted)}
@media (prefers-reduced-motion: reduce){
    .chat-overlay-bg,.chat-overlay-panel,
    .chat-overlay-typing-dot,.chat-overlay-rec-dot,
    .chat-overlay-wave-bar,.chat-overlay-voice-ring{
        animation:none !important;transition:none !important
    }
}
</style>

<div class="chat-overlay-bg" id="chatOverlayBg" onclick="closeChat()"></div>
<div class="chat-overlay-panel" id="chatOverlayPanel">
    <div class="chat-overlay-handle"></div>
    <div class="chat-overlay-header">
        <button class="chat-overlay-back" onclick="closeChat()" title="Назад" type="button">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </button>
        <div class="chat-overlay-title-wrap">
            <div class="chat-overlay-avatar">
                <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div style="min-width:0">
                <div class="chat-overlay-title">AI Асистент</div>
                <div class="chat-overlay-sub">Онлайн · отговаря бързо</div>
            </div>
        </div>
        <button class="chat-overlay-close" onclick="closeChat()" title="Затвори" type="button">
            <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="chat-overlay-messages" id="chatOverlayMessages">
        <div class="chat-overlay-empty" id="chatOverlayEmpty">
            <div class="chat-overlay-empty-t">Здравей!</div>
            Попитай каквото искаш — говори или пиши.
        </div>
        <div class="chat-overlay-typing" id="chatOverlayTyping">
            <div class="chat-overlay-typing-dots">
                <div class="chat-overlay-typing-dot"></div>
                <div class="chat-overlay-typing-dot"></div>
                <div class="chat-overlay-typing-dot"></div>
            </div>
        </div>
    </div>
    <div class="chat-overlay-rec-bar" id="chatOverlayRecBar">
        <div class="chat-overlay-rec-dot"></div>
        <span class="chat-overlay-rec-label">ЗАПИСВА</span>
        <span class="chat-overlay-rec-transcript" id="chatOverlayRecTranscript">Слушам...</span>
    </div>
    <div class="chat-overlay-input-area">
        <div class="chat-overlay-input-inner">
            <div class="chat-overlay-waves">
                <div class="chat-overlay-wave-bar"></div>
                <div class="chat-overlay-wave-bar"></div>
                <div class="chat-overlay-wave-bar"></div>
                <div class="chat-overlay-wave-bar"></div>
                <div class="chat-overlay-wave-bar"></div>
            </div>
            <textarea class="chat-overlay-ta" id="chatOverlayInput" placeholder="Кажи или пиши..." rows="1"
                oninput="this.style.height='';this.style.height=Math.min(this.scrollHeight,80)+'px';document.getElementById('chatOverlaySend').disabled=!this.value.trim()"
                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"></textarea>
            <div class="chat-overlay-mic" id="chatOverlayMic" onclick="toggleVoice()">
                <div class="chat-overlay-voice-ring chat-overlay-vr1"></div>
                <div class="chat-overlay-voice-ring chat-overlay-vr2"></div>
                <div class="chat-overlay-voice-ring chat-overlay-vr3"></div>
                <svg viewBox="0 0 24 24"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
            </div>
            <button class="chat-overlay-send" id="chatOverlaySend" onclick="sendMsg()" disabled type="button">
                <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
        </div>
    </div>
</div>
<!-- ═══ END S140.OVERLAY ═══ -->

<!-- ═══ INFO POPOVER OVERLAY ═══ -->
<div class="info-overlay" id="infoOverlay" onclick="if(event.target===this)closeInfo()">
  <div class="info-card">
    <div class="info-card-head">
      <div class="info-card-ic" id="infoIc"></div>
      <div class="info-card-title" id="infoTitle"></div>
      <button class="info-card-close" onclick="closeInfo()" aria-label="Затвори">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="info-card-body" id="infoBody"></div>
    <div class="info-card-voice">
      <div class="info-card-voice-label">
        <svg viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0014 0v-2"/></svg>
        <span>Натисни и говори</span>
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
<div class="chat-input-bar" onclick="rmsOpenChat(event)" role="button" tabindex="0" style="cursor:pointer">
  <span class="chat-input-icon">
    <svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="3" y2="12"/><line x1="6" y1="9" x2="6" y2="15"/><line x1="9" y1="6" x2="9" y2="18"/><line x1="12" y1="9" x2="12" y2="15"/><line x1="15" y1="11" x2="15" y2="13"/></svg>
  </span>
  <span class="chat-input-text">Кажи или напиши...</span>
  <button class="chat-mic" aria-label="Глас" onclick="event.stopPropagation();rmsOpenChat(event)">
    <svg viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0014 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
  </button>
  <button class="chat-send" aria-label="Изпрати" onclick="event.stopPropagation();rmsOpenChat(event)">
    <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
  </button>
</div>

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

function wfcSetRange(range){
  var d=document.querySelector('.wfc-days');if(!d)return;
  d.setAttribute('data-range',range);
  document.querySelectorAll('.wfc-tab').forEach(function(t){t.classList.toggle('active',t.dataset.tab===range);});
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
</script>
<!-- Bottom nav — circular orb icons (като AI Studio) -->
<nav class="rms-bottom-nav">
  <a href="chat-v2.php" class="rms-nav-tab active" aria-label="AI">
    <span class="nav-orb">
      <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    </span>
    <span>AI</span>
  </a>
  <a href="warehouse.php" class="rms-nav-tab" aria-label="Склад">
    <span class="nav-orb">
      <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.3 7 12 12 20.7 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg>
    </span>
    <span>Склад</span>
  </a>
  <a href="stats.php" class="rms-nav-tab" aria-label="Справки">
    <span class="nav-orb">
      <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline class="nav-stats-line" points="3 17 8 12 12 14 16 8 21 4"/>
        <circle class="nav-stats-dot" cx="3" cy="17" r="1.5" fill="white"/>
        <circle class="nav-stats-dot" cx="8" cy="12" r="1.5" fill="white"/>
        <circle class="nav-stats-dot" cx="12" cy="14" r="1.5" fill="white"/>
        <circle class="nav-stats-dot" cx="16" cy="8" r="1.5" fill="white"/>
        <circle class="nav-stats-dot" cx="21" cy="4" r="1.8" fill="white"/>
      </svg>
    </span>
    <span>Справки</span>
  </a>
  <a href="sale.php" class="rms-nav-tab" aria-label="Продажба">
    <span class="nav-orb">
      <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 8 L19 8 L18 21 L6 21 Z"/>
        <path d="M9 8 V5 a3 3 0 0 1 6 0 V8"/>
        <path class="nav-bolt" d="M13 12 L10 16 L13 16 L11 19" stroke-width="2.2"/>
      </svg>
    </span>
    <span>Продажба</span>
  </a>
</nav>

<script>
// ═══ S140.OVERLAY: input bar отваря локалния 75vh overlay (не навигира) ═══
window.rmsOpenChat = function(e){
    if (e) { e.preventDefault(); if (e.stopPropagation) e.stopPropagation(); }
    if (typeof window.openChat === 'function') openChat();
    else location.href = 'chat.php';
};

// ─────────────────────────────────────────────────────────
// S82-DASH pills: превключване на период + Оборот/Печалба
// ─────────────────────────────────────────────────────────
const V2_PERIODS = <?= $v2_periods_json ?>;
const V2_CS      = <?= json_encode($cs) ?>;
const V2_IS_OWN  = <?= $role === 'owner' ? 'true' : 'false' ?>;
const V2_LABELS  = { today: 'ДНЕС', '7d': '7 ДНИ', '30d': '30 ДНИ', '365d': '365 ДНИ' };
const V2_VS      = { today: 'вчера', '7d': 'предишните 7 дни', '30d': 'предишните 30 дни', '365d': 'предишната година' };
let v2curPeriod = 'today';
let v2curMode   = 'rev';

function v2fmt(n) { return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }

function v2updateDash() {
    const d = V2_PERIODS[v2curPeriod];
    if (!d) return;
    const val = v2curMode === 'rev' ? d.rev : d.profit;
    const pct = v2curMode === 'rev' ? d.cmp_rev : d.cmp_prof;
    const num = document.getElementById('revNum');
    const pctEl = document.getElementById('revPct');
    const lblEl = document.getElementById('revLabel');
    const metaEl = document.getElementById('revMeta');
    const cw = document.getElementById('confWarn');
    if (num)   num.textContent = v2fmt(val);
    if (pctEl) {
        pctEl.textContent = (pct >= 0 ? '+' : '') + pct + '%';
        pctEl.className = 's82-dash-pct ' + (pct > 0 ? '' : (pct < 0 ? 'neg' : 'zero'));
    }
    if (lblEl) lblEl.textContent = V2_LABELS[v2curPeriod];
    if (metaEl) {
        let txt = d.cnt + ' продажби · vs ' + d.cntp + ' ' + V2_VS[v2curPeriod];
        if (V2_IS_OWN && d.cnt > 0 && v2curMode === 'rev') txt += ' · ' + d.margin + '% марж';
        metaEl.textContent = txt;
    }
    // Confidence warning се показва САМО при режим Печалба (когато confidence < 100%)
    if (cw) cw.style.display = (v2curMode === 'profit') ? 'flex' : 'none';
}

function v2setPeriod(period, el) {
    v2curPeriod = period;
    document.querySelectorAll('.rev-pill[data-period]').forEach(p => {
        p.classList.toggle('active', p === el);
    });
    v2updateDash();
    if (navigator.vibrate) navigator.vibrate(6);
}

function v2setMode(mode) {
    v2curMode = mode;
    const mr = document.getElementById('modeRev'), mp = document.getElementById('modeProfit');
    if (mr) mr.classList.toggle('active', mode === 'rev');
    if (mp) mp.classList.toggle('active', mode === 'profit');
    v2updateDash();
    if (navigator.vibrate) navigator.vibrate(6);
}

// Life Board card click → отваря AI чат с въпрос за този сигнал (локален overlay)
function v2openCardQ(title) {
    if (typeof window.openChatQ === 'function') {
        if (!title) { openChat(); return; }
        openChatQ(title);
        return;
    }
    if (!title) { location.href = 'chat.php'; return; }
    try { sessionStorage.setItem('rms_prefill_q', title); } catch(_) {}
    location.href = 'chat.php?q=' + encodeURIComponent(title);
}

// Global haptic feedback на всички тап елементи
document.addEventListener('DOMContentLoaded', function(){
    const tappables = '.rms-icon-btn, .rms-store-toggle, .lb-mode-toggle, .op-btn, .op-info-btn, .lb-action, .lb-fb-btn, .lb-expand-btn, .help-chip, .s82-dash-pill, .wfc-tab, .fp-pill, .studio-btn, .see-more-mini, .chat-mic, .chat-send, .lb-collapsed, .rms-nav-tab';
    document.querySelectorAll(tappables).forEach(el => {
        el.addEventListener('click', () => {
            if (navigator.vibrate) navigator.vibrate(6);
        }, { passive: true });
    });
});

// Life Board card toggle (expand/collapse вътрешен info панел)
function v2lbToggleCard(e, el) {
    if (e) e.stopPropagation();
    const card = el.closest('.lb-card');
    if (!card) return;
    card.classList.toggle('expanded');
    // Завърти chevron на бутона
    const btn = el.querySelector('.lb-expand-btn svg');
    if (btn) btn.style.transform = card.classList.contains('expanded') ? 'rotate(180deg)' : '';
    if (navigator.vibrate) navigator.vibrate(6);
}

// Life Board feedback (👍 / 👎) — визуален избор; backend следваща стъпка
function v2lbFb(e, btn, kind) {
    if (e) e.stopPropagation();
    const card = btn.closest('.lb-card');
    if (!card) return;
    card.querySelectorAll('.lb-fb-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    if (navigator.vibrate) navigator.vibrate(8);
}

// ═══════════════════════════════════════════════════════════════════
// S140.OVERLAY: 75vh AI chat overlay (ported from chat.php)
// Функции: openChat / closeChat / openChatQ / sendMsg / addUserBubble /
//          addAIBubble / toggleVoice / stopVoice
// AJAX: chat-send.php (POST JSON {message} → {reply, actions?, error?})
// ═══════════════════════════════════════════════════════════════════
(function(){
    let _voiceRec = null, _isRecording = false, _voiceText = '';
    let _isOpen = false;

    function _q(id){ return document.getElementById(id); }
    function _esc(s){
        return String(s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
            .replace(/'/g,'&#39;');
    }
    function _vib(n){ if (navigator.vibrate) navigator.vibrate(n || 6); }
    function _scrollBottom(){
        const el = _q('chatOverlayMessages');
        if (el) el.scrollTop = el.scrollHeight;
    }

    window.openChat = function openChat(){
        if (_isOpen) return;
        _isOpen = true;
        const bg = _q('chatOverlayBg'), panel = _q('chatOverlayPanel');
        if (bg) bg.classList.add('open');
        if (panel) panel.classList.add('open');
        document.body.classList.add('overlay-open');
        try { history.pushState({ ov: 'chat' }, ''); } catch(_) {}
        setTimeout(_scrollBottom, 50);
        const inp = _q('chatOverlayInput');
        if (inp) setTimeout(function(){ try { inp.focus(); } catch(_) {} }, 350);
        _vib();
    };

    window.closeChat = function closeChat(skipHistory){
        if (!_isOpen) return;
        _isOpen = false;
        const bg = _q('chatOverlayBg'), panel = _q('chatOverlayPanel');
        if (bg) bg.classList.remove('open');
        if (panel) panel.classList.remove('open');
        window.stopVoice();
        document.body.classList.remove('overlay-open');
        try {
            if (!skipHistory && history.state && history.state.ov === 'chat') history.back();
        } catch(_) {}
    };

    window.openChatQ = function openChatQ(question){
        openChat();
        setTimeout(function(){
            const inp = _q('chatOverlayInput');
            const sendBtn = _q('chatOverlaySend');
            if (inp) inp.value = question;
            if (sendBtn) sendBtn.disabled = false;
            window.sendMsg();
        }, 400);
    };

    window.sendMsg = async function sendMsg(){
        const inp = _q('chatOverlayInput');
        const sendBtn = _q('chatOverlaySend');
        if (!inp || !sendBtn) return;
        const txt = inp.value.trim();
        if (!txt) return;
        window.addUserBubble(txt);
        inp.value = '';
        inp.style.height = '';
        sendBtn.disabled = true;
        const typing = _q('chatOverlayTyping');
        if (typing) typing.classList.add('on');
        _scrollBottom();
        try {
            const r = await fetch('chat-send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: txt })
            });
            const raw = await r.text();
            let d;
            try { d = JSON.parse(raw); } catch(e) {
                if (typing) typing.classList.remove('on');
                window.addAIBubble('Грешка: ' + raw.substring(0, 200));
                return;
            }
            if (typing) typing.classList.remove('on');
            window.addAIBubble(d.reply || d.error || 'Грешка при обработка.', d.actions || null);
        } catch (e) {
            if (typing) typing.classList.remove('on');
            window.addAIBubble('Грешка при свързване. Опитай пак.');
        }
    };

    window.addUserBubble = function addUserBubble(txt){
        const msgs = _q('chatOverlayMessages'), typing = _q('chatOverlayTyping');
        if (!msgs) return;
        const emp = _q('chatOverlayEmpty');
        if (emp) emp.style.display = 'none';
        const g = document.createElement('div');
        g.className = 'chat-overlay-msg-group';
        const t = new Date().toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit' });
        g.innerHTML = '<div class="chat-overlay-msg-meta right">' + t + '</div>'
                    + '<div class="chat-overlay-msg-user">' + _esc(txt) + '</div>';
        if (typing) msgs.insertBefore(g, typing);
        else msgs.appendChild(g);
        _scrollBottom();
    };

    window.addAIBubble = function addAIBubble(txt, actions){
        const msgs = _q('chatOverlayMessages'), typing = _q('chatOverlayTyping');
        if (!msgs) return;
        const emp = _q('chatOverlayEmpty');
        if (emp) emp.style.display = 'none';
        const g = document.createElement('div');
        g.className = 'chat-overlay-msg-group';
        const t = new Date().toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit' });
        let h = '<div class="chat-overlay-msg-meta">'
              + '<svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'
              + 'AI · ' + t + '</div>'
              + '<div class="chat-overlay-msg-ai">' + _esc(txt) + '</div>';
        if (actions && actions.length) {
            h += '<div class="chat-overlay-actions">';
            actions.forEach(function(a) {
                h += '<button type="button" class="chat-overlay-action-btn" onclick="window.open(\'' + _esc(a.url || '#') + '\',\'_blank\')">' + _esc(a.label) + ' →</button>';
            });
            h += '</div>';
        }
        g.innerHTML = h;
        if (typing) msgs.insertBefore(g, typing);
        else msgs.appendChild(g);
        _scrollBottom();
    };

    window.toggleVoice = function toggleVoice(){
        if (_isRecording) { window.stopVoice(); return; }
        const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SR) {
            try { alert('Браузърът не поддържа гласов вход'); } catch(_) {}
            return;
        }
        _isRecording = true;
        _voiceText = '';
        const mic = _q('chatOverlayMic'), rec = _q('chatOverlayRecBar'), tr = _q('chatOverlayRecTranscript');
        if (mic) mic.classList.add('rec');
        if (rec) rec.classList.add('on');
        if (tr) tr.innerText = 'Слушам...';
        _voiceRec = new SR();
        _voiceRec.lang = 'bg-BG';
        _voiceRec.continuous = false;
        _voiceRec.interimResults = true;
        _voiceRec.onresult = function(e){
            let fin = '', interim = '';
            for (let i = e.resultIndex; i < e.results.length; i++) {
                if (e.results[i].isFinal) fin += e.results[i][0].transcript;
                else interim += e.results[i][0].transcript;
            }
            if (fin) _voiceText = fin;
            if (tr) tr.innerText = _voiceText || interim || 'Слушам...';
        };
        _voiceRec.onend = function(){
            _isRecording = false;
            if (mic) mic.classList.remove('rec');
            if (rec) rec.classList.remove('on');
            if (_voiceText) {
                const inp = _q('chatOverlayInput'), sendBtn = _q('chatOverlaySend');
                if (inp) inp.value = _voiceText;
                if (sendBtn) sendBtn.disabled = false;
                window.sendMsg();
            }
        };
        _voiceRec.onerror = function(e){ window.stopVoice(); };
        try { _voiceRec.start(); } catch(e) { window.stopVoice(); }
    };

    window.stopVoice = function stopVoice(){
        _isRecording = false;
        _voiceText = '';
        const mic = _q('chatOverlayMic'), rec = _q('chatOverlayRecBar');
        if (mic) mic.classList.remove('rec');
        if (rec) rec.classList.remove('on');
        if (_voiceRec) { try { _voiceRec.stop(); } catch(e) {} _voiceRec = null; }
    };

    window.addEventListener('popstate', function(){
        if (_isOpen) window.closeChat(true);
    });
})();
</script>
</body>
</html>
