<?php
/**
 * build-prompt.php — buildSystemPrompt() + getSeasonalContext()
 * RunMyStore.ai S41 — FULL DATA DUMP
 *
 * Dumps ALL products, sales, suppliers, invoices, customers, sellers
 * into the Gemini system prompt so AI can answer ANY question with
 * real names, numbers and concrete suggestions.
 *
 * ~350 products = ~15K tokens. Gemini 2.5 Flash 1M context = no problem.
 */

require_once __DIR__ . '/ai-topics.php';
require_once __DIR__ . '/weather-cache.php';

function getSeasonalContext(string $business_type, string $country): string {
    static $data = null;
    if ($data === null) {
        $path = __DIR__ . '/GEMINI_SEASONALITY.md';
        if (!file_exists($path)) return '';
        $raw  = trim(file_get_contents($path));
        $raw  = preg_replace('/\]\s*\[/', ',', $raw);
        $data = json_decode($raw, true) ?? [];
    }

    $type_map = [
        'womens_clothing'   => ['дрехи_женски','women_clothing','clothing_women','дрехи','lingerie','бельо','underwear'],
        'children_clothing' => ['детски_дрехи','children_clothing','kids_clothing','детски'],
        'sporting_goods'    => ['спортни_стоки','sporting','sport','спорт'],
        'jewelry'           => ['бижута','jewelry','jewellery','аксесоари','accessories'],
        'grocery'           => ['хранителни','grocery','food','хранителен'],
        'butcher'           => ['месарница','butcher','delikates','деликатеси'],
        'florist'           => ['цветарница','florist','flowers','цветя'],
        'pharmacy'          => ['аптека','pharmacy','drugstore','дрогерия'],
        'hardware'          => ['строителни','hardware','строителен'],
        'auto_parts'        => ['авточасти','auto_parts','автомобилни'],
        'toys'              => ['играчки','toys','toy_store'],
        'bookstore'         => ['книжарница','bookstore','stationery','канцелария'],
        'cosmetics'         => ['козметика','cosmetics','beauty'],
        'pet_store'         => ['зоомагазин','pet_store','pets','домашни_любимци'],
        'home_textiles'     => ['спално_бельо','home_textiles','textiles','текстил'],
        'electronics'       => ['електроника','electronics','electronics_accessories'],
    ];

    $bt = strtolower($business_type);
    $matched = $bt;
    foreach ($type_map as $canonical => $aliases) {
        if ($bt === $canonical || in_array($bt, $aliases)) { $matched = $canonical; break; }
        foreach ($aliases as $a) {
            if (str_contains($bt, $a) || str_contains($a, $bt)) { $matched = $canonical; break 2; }
        }
    }

    $cu = strtoupper($country);
    $entry = null;
    foreach ($data as $row) {
        if (strtoupper($row['country'] ?? '') !== $cu) continue;
        $rt = strtolower($row['business_type'] ?? '');
        if ($rt === $matched) { $entry = $row; break; }
        foreach ($type_map[$matched] ?? [] as $a) {
            if (str_contains($rt, $a) || str_contains($a, $rt)) { $entry = $row; break 2; }
        }
    }
    if (!$entry && $cu !== 'BG') return getSeasonalContext($business_type, 'BG');
    if (!$entry) return '';

    $today  = new DateTime();
    $lines  = [];

    foreach ($entry['peaks'] ?? [] as $pk) {
        $pts = explode('/', $pk['period'] ?? '');
        if (count($pts) < 2) continue;
        try {
            $s    = new DateTime($pts[0]);
            $e    = new DateTime($pts[1]);
            $warn = (clone $s)->modify('-' . ($pk['stock_up_weeks_before'] ?? 4) . ' weeks');
            $diff = (int)$today->diff($s)->days;

            if ($today >= $s && $today <= $e) {
                $prods = implode(', ', array_slice($pk['products'] ?? [], 0, 4));
                $lines[] = "ACTIVE SALES PEAK: \"{$pk['name']}\" — RIGHT NOW! Products: {$prods}. Expected uplift: +{$pk['revenue_uplift_pct']}%. Check stock immediately.";
            } elseif ($today >= $warn && $today < $s) {
                $prods = implode(', ', array_slice($pk['products'] ?? [], 0, 4));
                $lines[] = "UPCOMING PEAK: \"{$pk['name']}\" in {$diff} days. Products: {$prods}. Uplift: +{$pk['revenue_uplift_pct']}%. Must restock NOW.";
            } elseif ($today < $s && $diff <= 30) {
                $lines[] = "PEAK IN {$diff} DAYS: \"{$pk['name']}\" (+{$pk['revenue_uplift_pct']}%).";
            }
        } catch (Exception $e2) { continue; }
    }

    foreach ($entry['dead_periods'] ?? [] as $dp) {
        $pts = explode('/', $dp['period'] ?? '');
        if (count($pts) < 2) continue;
        try {
            $s = new DateTime($pts[0]);
            $e = new DateTime($pts[1]);
            if ($today >= $s && $today <= $e) {
                $lines[] = "DEAD PERIOD: \"{$dp['name']}\" — {$dp['reason']} Reduce orders. Focus on liquidating slow stock.";
            }
        } catch (Exception $e2) { continue; }
    }

    foreach (array_slice($entry['local_specifics'] ?? [], 0, 2) as $sp) {
        $lines[] = "LOCAL MARKET INSIGHT: {$sp}";
    }

    $cycle_map = [
        'end_of_month'                    => 'end of month',
        '15th_and_end_of_month'           => '15th and end of month',
        'end_of_month_plus_14th_salary'   => 'end of month + 13th/14th salary bonus',
        '27th_of_month'                   => '27th of month',
        '24th_of_month_plus_vakantiegeld' => '24th + vacation money',
        'end_of_month_plus_subsidies'     => 'end of month + subsidies',
        'end_of_month_plus_extra_pay'     => 'end of month + bonuses',
    ];
    if (!empty($entry['salary_cycle'])) {
        $cycle = $cycle_map[$entry['salary_cycle']] ?? $entry['salary_cycle'];
        $day   = (int)date('d');
        if ($day >= 1 && $day <= 5)   $lines[] = "SALARY EFFECT: People just received salaries. Expect higher traffic.";
        elseif ($day >= 25)            $lines[] = "SALARY EFFECT: Salaries paid {$cycle}. Next few days will be active.";
    }

    return empty($lines) ? '' : implode("\n", $lines);
}

function buildSystemPrompt(int $tenant_id, int $store_id, string $role): string {

    // ── TENANT + STORE INFO ───────────────────────────────────
    $tenant = DB::run(
        'SELECT t.business_type, t.country, t.language, t.currency,
                c.name AS company_name, c.city
         FROM tenants t LEFT JOIN companies c ON c.tenant_id = t.id
         WHERE t.id = ? LIMIT 1',
        [$tenant_id]
    )->fetch();

    $store = DB::run(
        'SELECT name, city FROM stores WHERE id = ? LIMIT 1',
        [$store_id]
    )->fetch();

    $business_type = $tenant['business_type'] ?? 'retail';
    $country       = strtoupper($tenant['country'] ?? 'BG');
    $currency      = $tenant['currency'] ?? '€';
    $store_name    = $store['name'] ?? ($tenant['company_name'] ?? 'the store');
    $city          = $store['city'] ?? ($tenant['city'] ?? '');
    $today_str     = date('d.m.Y');
    $weekday       = date('l');
    $hour          = (int)date('H');

    $time_ctx = match(true) {
        $hour >= 8  && $hour < 11 => 'morning — start of day',
        $hour >= 11 && $hour < 14 => 'midday — peak hours',
        $hour >= 14 && $hour < 17 => 'afternoon — analysis time',
        $hour >= 17 && $hour < 20 => 'evening — closing time',
        $hour >= 20               => 'late evening — avoid complex decisions',
        default                   => 'early morning'
    };

    $role_ctx = match($role) {
        'owner'   => 'OWNER — sees everything: revenue, profit, margins, all staff data',
        'manager' => 'MANAGER — sees revenue and operations, but NOT net profit/margins',
        'seller'  => 'SELLER — sees only operational data: stock levels, current sales, product search',
        default   => 'EMPLOYEE'
    };

    // ══════════════════════════════════════════════════════════
    // LAYER 2A — ALL PRODUCTS (full dump)
    // Format: name|code|category|supplier|retail|cost|qty|min_qty|last_sale_date|days_idle
    // ══════════════════════════════════════════════════════════
    $all_products = DB::run(
        'SELECT p.id, p.name, p.code,
                COALESCE(cat.name, "-") AS category,
                COALESCE(sup.name, "-") AS supplier,
                p.retail_price, COALESCE(p.cost_price, 0) AS cost_price,
                COALESCE(i.quantity, 0) AS qty,
                COALESCE(p.min_quantity, 0) AS min_qty,
                (SELECT DATE(MAX(s2.created_at)) FROM sales s2
                 JOIN sale_items si2 ON si2.sale_id = s2.id
                 WHERE si2.product_id = p.id AND s2.store_id = ? AND s2.status != "canceled"
                ) AS last_sale_date,
                DATEDIFF(NOW(), COALESCE(
                    (SELECT MAX(s3.created_at) FROM sales s3
                     JOIN sale_items si3 ON si3.sale_id = s3.id
                     WHERE si3.product_id = p.id AND s3.store_id = ? AND s3.status != "canceled"),
                    p.created_at
                )) AS days_idle
         FROM products p
         LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
         LEFT JOIN categories cat ON cat.id = p.category_id
         LEFT JOIN suppliers sup ON sup.id = p.supplier_id
         WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
         ORDER BY p.name',
        [$store_id, $store_id, $store_id, $tenant_id]
    )->fetchAll(PDO::FETCH_ASSOC);

    $products_dump = "name|code|category|supplier|retail|cost|qty|min_qty|last_sale|days_idle|status\n";
    $total_products = 0;
    $total_stock_value = 0;
    $zero_stock = 0;
    $zombie_count = 0;
    $zombie_value = 0;
    $below_cost = 0;
    $low_stock = 0;

    foreach ($all_products as $p) {
        $total_products++;
        $q = (int)$p['qty'];
        $cost = (float)$p['cost_price'];
        $retail = (float)$p['retail_price'];
        $min = (int)$p['min_qty'];
        $idle = (int)$p['days_idle'];
        $ls = $p['last_sale_date'] ?? '-';

        $stock_val = $q * ($cost > 0 ? $cost : $retail * 0.6);
        $total_stock_value += $stock_val;

        if ($q == 0) $zero_stock++;
        if ($idle >= 45 && $q > 0) { $zombie_count++; $zombie_value += $stock_val; }
        if ($cost > 0 && $retail < $cost) $below_cost++;
        if ($min > 0 && $q <= $min && $q > 0) $low_stock++;

        $flags = [];
        if ($q == 0) $flags[] = 'ZERO_STOCK';
        if ($idle >= 45 && $q > 0) $flags[] = 'ZOMBIE';
        if ($min > 0 && $q <= $min && $q > 0) $flags[] = 'LOW_STOCK';
        if ($cost > 0 && $retail < $cost) $flags[] = 'SELLING_AT_LOSS';
        $flag_str = empty($flags) ? 'OK' : implode(',', $flags);
        $products_dump .= "{$p['name']}|{$p['code']}|{$p['category']}|{$p['supplier']}|{$retail}|{$cost}|{$q}|{$min}|{$ls}|{$idle}|{$flag_str}\n";
    }

    // ══════════════════════════════════════════════════════════
    // LAYER 2B — SALES LAST 30 DAYS BY PRODUCT
    // Format: name|code|qty_sold|revenue|profit
    // ══════════════════════════════════════════════════════════
    $sales_by_product = DB::run(
        'SELECT p.name, p.code,
                SUM(si.quantity) AS qty_sold,
                ROUND(SUM(si.quantity * si.unit_price), 2) AS revenue,
                ROUND(SUM(si.quantity * (si.unit_price - COALESCE(si.cost_price, 0))), 2) AS profit
         FROM sale_items si
         JOIN sales s ON s.id = si.sale_id
         JOIN products p ON p.id = si.product_id
         WHERE s.tenant_id = ? AND s.store_id = ? AND s.status != "canceled"
         AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY si.product_id
         ORDER BY qty_sold DESC',
        [$tenant_id, $store_id]
    )->fetchAll(PDO::FETCH_ASSOC);

    $sales_product_dump = "name|code|qty_sold|revenue|profit\n";
    foreach ($sales_by_product as $sp) {
        $sales_product_dump .= "{$sp['name']}|{$sp['code']}|{$sp['qty_sold']}|{$sp['revenue']}|{$sp['profit']}\n";
    }

    // ══════════════════════════════════════════════════════════
    // LAYER 2C — DAILY SALES (last 30 days)
    // Format: date|revenue|count|profit
    // ══════════════════════════════════════════════════════════
    $daily_sales = DB::run(
        'SELECT DATE(s.created_at) AS d,
                ROUND(SUM(s.total), 2) AS revenue,
                COUNT(*) AS cnt,
                ROUND(SUM(
                    (SELECT SUM(si2.quantity * (si2.unit_price - COALESCE(si2.cost_price, 0)))
                     FROM sale_items si2 WHERE si2.sale_id = s.id)
                ), 2) AS profit
         FROM sales s
         WHERE s.tenant_id = ? AND s.store_id = ? AND s.status != "canceled"
         AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY DATE(s.created_at)
         ORDER BY d DESC',
        [$tenant_id, $store_id]
    )->fetchAll(PDO::FETCH_ASSOC);

    $daily_dump = "date|revenue|sales_count|profit\n";
    foreach ($daily_sales as $ds) {
        $daily_dump .= "{$ds['d']}|{$ds['revenue']}|{$ds['cnt']}|{$ds['profit']}\n";
    }

    // Today's summary from daily data
    $today_date = date('Y-m-d');
    $today_rev = 0; $today_cnt = 0; $today_profit = 0;
    foreach ($daily_sales as $ds) {
        if ($ds['d'] === $today_date) {
            $today_rev = (float)$ds['revenue'];
            $today_cnt = (int)$ds['cnt'];
            $today_profit = (float)$ds['profit'];
            break;
        }
    }

    // Yesterday
    $yesterday_date = date('Y-m-d', strtotime('-1 day'));
    $yesterday_rev = 0;
    foreach ($daily_sales as $ds) {
        if ($ds['d'] === $yesterday_date) { $yesterday_rev = (float)$ds['revenue']; break; }
    }
    $diff_pct = $yesterday_rev > 0 ? round(($today_rev - $yesterday_rev) / $yesterday_rev * 100) : 0;

    // Week total
    $week_rev = 0;
    $week_start = date('Y-m-d', strtotime('monday this week'));
    foreach ($daily_sales as $ds) {
        if ($ds['d'] >= $week_start) $week_rev += (float)$ds['revenue'];
    }

    // ══════════════════════════════════════════════════════════
    // LAYER 2D — SUPPLIERS
    // Format: name|owed|unpaid_count|last_delivery_date|products_count
    // ══════════════════════════════════════════════════════════
    $suppliers_data = DB::run(
        'SELECT sup.id, sup.name,
                (SELECT DATE(MAX(d.created_at)) FROM deliveries d
                 WHERE d.supplier_id = sup.id AND d.tenant_id = ?) AS last_delivery,
                (SELECT COUNT(*) FROM products p
                 WHERE p.supplier_id = sup.id AND p.tenant_id = ? AND p.is_active = 1) AS products_count
         FROM suppliers sup
         WHERE sup.tenant_id = ?
         ORDER BY sup.name',
        [$tenant_id, $tenant_id, $tenant_id]
    )->fetchAll(PDO::FETCH_ASSOC);

    $suppliers_dump = "name|last_delivery|products_count\n";
    $total_owed = 0;
    foreach ($suppliers_data as $sd) {
        $ld = $sd['last_delivery'] ?? '-';
        $suppliers_dump .= "{$sd['name']}|{$ld}|{$sd['products_count']}\n";
    }

    // ══════════════════════════════════════════════════════════
    // LAYER 2E — UNPAID INVOICES (all)
    // Format: supplier|amount|due_date|days_overdue
    // ══════════════════════════════════════════════════════════
    $unpaid_invoices = [];
    if ($role !== 'seller') {
        $unpaid_invoices = DB::run(
            'SELECT COALESCE(cust.name, "—") AS customer_name,
                    inv.total AS amount,
                    inv.due_date,
                    GREATEST(0, DATEDIFF(CURDATE(), inv.due_date)) AS days_overdue
             FROM invoices inv
             LEFT JOIN customers cust ON cust.id = inv.customer_id
             WHERE inv.tenant_id = ? AND inv.status IN ("sent","overdue")
             ORDER BY inv.due_date ASC',
            [$tenant_id]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    $invoices_dump = "supplier|amount|due_date|days_overdue\n";
    foreach ($unpaid_invoices as $ui) {
        $invoices_dump .= "{$ui['supplier']}|{$ui['amount']}|{$ui['due_date']}|{$ui['days_overdue']}\n";
    }

    // ══════════════════════════════════════════════════════════
    // LAYER 2F — TOP CUSTOMERS (last 90 days)
    // Format: name|total_bought|visits|last_visit|owed
    // ══════════════════════════════════════════════════════════
    $customers_dump = "name|total_bought|visits|last_visit|owed\n";
    try {
        $customers = DB::run(
            'SELECT c.name,
                    COALESCE(SUM(s.total), 0) AS total_bought,
                    COUNT(s.id) AS visits,
                    DATE(MAX(s.created_at)) AS last_visit,
                    COALESCE(c.balance, 0) AS owed
             FROM customers c
             LEFT JOIN sales s ON s.customer_id = c.id AND s.status != "canceled"
                AND s.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
             WHERE c.tenant_id = ?
             GROUP BY c.id
             ORDER BY total_bought DESC
             LIMIT 30',
            [$tenant_id]
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($customers as $cu) {
            $lv = $cu['last_visit'] ?? '-';
            $customers_dump .= "{$cu['name']}|{$cu['total_bought']}|{$cu['visits']}|{$lv}|{$cu['owed']}\n";
        }
    } catch (Exception $e) {
        $customers_dump .= "(no customer data)\n";
    }

    // ══════════════════════════════════════════════════════════
    // LAYER 2G — SELLERS (if multiple)
    // Format: name|sales_count|revenue
    // ══════════════════════════════════════════════════════════
    $sellers_dump = '';
    try {
        $sellers = DB::run(
            'SELECT u.name,
                    COUNT(s.id) AS sales_count,
                    COALESCE(SUM(s.total), 0) AS revenue
             FROM users u
             JOIN sales s ON s.user_id = u.id
             WHERE u.tenant_id = ? AND s.store_id = ? AND s.status != "canceled"
             AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY u.id
             ORDER BY revenue DESC',
            [$tenant_id, $store_id]
        )->fetchAll(PDO::FETCH_ASSOC);
        if (count($sellers) > 1) {
            $sellers_dump = "name|sales_count_30d|revenue_30d\n";
            foreach ($sellers as $sl) {
                $sellers_dump .= "{$sl['name']}|{$sl['sales_count']}|{$sl['revenue']}\n";
            }
        }
    } catch (Exception $e) {}

    // ══════════════════════════════════════════════════════════
    // LAYER 2H — CATEGORIES SUMMARY
    // Format: category|products|zero_stock|zombie|total_stock_value
    // ══════════════════════════════════════════════════════════
    $cat_summary = [];
    foreach ($all_products as $p) {
        $cat = $p['category'] ?: '-';
        if (!isset($cat_summary[$cat])) {
            $cat_summary[$cat] = ['products' => 0, 'zero' => 0, 'zombie' => 0, 'value' => 0];
        }
        $cat_summary[$cat]['products']++;
        if ((int)$p['qty'] == 0) $cat_summary[$cat]['zero']++;
        if ((int)$p['days_idle'] >= 45 && (int)$p['qty'] > 0) $cat_summary[$cat]['zombie']++;
        $cost = (float)$p['cost_price'];
        $cat_summary[$cat]['value'] += (int)$p['qty'] * ($cost > 0 ? $cost : (float)$p['retail_price'] * 0.6);
    }
    $categories_dump = "category|products|zero_stock|zombie|stock_value\n";
    foreach ($cat_summary as $cat => $cs) {
        $categories_dump .= "{$cat}|{$cs['products']}|{$cs['zero']}|{$cs['zombie']}|" . round($cs['value']) . "\n";
    }

    // ── PROFIT BLOCK (owner only) ─────────────────────────────
    $profit_block = '';
    if ($role === 'owner') {
        $margin = $today_rev > 0 ? round($today_profit / $today_rev * 100) : 0;
        $profit_block = "- Net profit today: {$today_profit} {$currency} | Margin: {$margin}%";
        if ($margin > 0 && $margin < 15) $profit_block .= " — CRITICAL: margin below 15%!";
    }

    // ── CONFIDENCE ────────────────────────────────────────────
    $with_cost = 0;
    foreach ($all_products as $p) { if ((float)$p['cost_price'] > 0) $with_cost++; }
    $confidence_pct = $total_products > 0 ? round($with_cost / $total_products * 100) : 0;

    // ── AVERAGE FOR THIS WEEKDAY ──────────────────────────────
    $avg_day = DB::run(
        'SELECT COALESCE(AVG(daily_rev), 0) AS avg_rev
         FROM (SELECT DATE(created_at) AS d, SUM(total) AS daily_rev
               FROM sales WHERE store_id = ? AND tenant_id = ? AND status != "canceled"
               AND DAYOFWEEK(created_at) = DAYOFWEEK(CURDATE())
               AND created_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
               GROUP BY DATE(created_at)) AS sub',
        [$store_id, $tenant_id]
    )->fetch();
    $avg_rev = round((float)$avg_day['avg_rev'], 2);
    $pct_avg = $avg_rev > 0 ? round($today_rev / $avg_rev * 100) : 0;

    // ── AI MEMORY ─────────────────────────────────────────────
    $mem_rows = DB::run(
        'SELECT content FROM tenant_ai_memory
         WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 25',
        [$tenant_id]
    )->fetchAll(PDO::FETCH_ASSOC);
    $mem_str = '';
    foreach ($mem_rows as $m) {
        $mem_str .= "  - {$m['content']}\n";
    }

    // ── BUSINESS SIGNALS ──────────────────────────────────────
    $signals = [];
    if ($zero_stock > 0) $signals[] = "CRITICAL: {$zero_stock} products are OUT OF STOCK.";
    if ($below_cost > 0 && $role === 'owner') $signals[] = "CRITICAL: {$below_cost} products selling BELOW cost price!";
    if ($zombie_value > 100) $signals[] = "WARNING: " . round($zombie_value) . " {$currency} frozen in zombie stock ({$zombie_count} products, 45+ days idle).";
    if ($low_stock > 0) $signals[] = "WARNING: {$low_stock} products below minimum stock.";
    if ($avg_rev > 0 && $today_rev < $avg_rev * 0.5 && $hour >= 14) $signals[] = "WARNING: Today's revenue below 50% of average {$weekday}.";
    if ($total_owed > 0 && $role !== 'seller') $signals[] = "INFO: Total owed to suppliers: {$total_owed} {$currency}.";
    $signals_str = empty($signals) ? "No critical alerts." : implode("\n", $signals);

    // ── ACTIVE SIGNALS (from ai_insights cron) ────────────────
    $active_signals_str = '';
    try {
        $ai_rows = DB::run(
            'SELECT urgency, title, body FROM ai_insights
             WHERE tenant_id=? AND store_id=? AND urgency IN ("critical","warning","info")
             ORDER BY FIELD(urgency,"critical","warning","info"), created_at DESC
             LIMIT 15',
            [$tenant_id, $store_id]
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ai_rows as $ar) {
            $u = strtoupper($ar['urgency']);
            $active_signals_str .= "- {$u}: {$ar['title']}";
            if (!empty($ar['body'])) $active_signals_str .= " — {$ar['body']}";
            $active_signals_str .= "\n";
        }
    } catch (Exception $e) {}
    if (empty($active_signals_str)) $active_signals_str = 'No active insights.';

    // ── SEASONAL CONTEXT ──────────────────────────────────────
    $seasonal_str = getSeasonalContext($business_type, $country);

    // ── PENDING DELIVERIES ────────────────────────────────────
    $del_str = '';
    try {
        $del_rows = DB::run(
            'SELECT sup.name, d.created_at
             FROM deliveries d
             LEFT JOIN suppliers sup ON sup.id = d.supplier_id
             WHERE d.tenant_id = ? AND d.status = "pending"
             ORDER BY d.created_at ASC LIMIT 10',
            [$tenant_id]
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($del_rows as $dr) {
            $del_str .= "  - {$dr['name']}: pending since {$dr['created_at']}\n";
        }
    } catch (Exception $e) {}

    // ══════════════════════════════════════════════════════════
    // FINAL PROMPT ASSEMBLY
    // ══════════════════════════════════════════════════════════
    $dsign = $diff_pct >= 0 ? '+' : '';

    $prompt = <<<PROMPT
You are an AI business advisor in RunMyStore.ai — a mobile retail app for small European shop owners.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 1 — IDENTITY & RULES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
You are "the smart cousin from Sofia" — a trusted friend with an MBA who knows this business.

PERSONALITY:
- Informal "ти", never "Вие"
- ALWAYS use real product names and numbers from the data below
- Be SHORT — max 3 sentences for simple answers, max 6 for analysis
- Start with the NUMBER, then explain, then suggest action
- NEVER say "Как мога да помогна?", "Разбира се!", "Нямам достъп до..."
- NEVER give just numbers — always: NUMBER + WHY + WHAT TO DO

CURRENCY: Always use {$currency} (never BGN, never лв unless the user does).

CRITICAL RESPONSE RULE — apply to EVERY answer:
1. ЧИСЛОТО — the concrete value with product names
2. ЗАЩО — reason, comparison, context
3. КАКВО ДА НАПРАВИШ — concrete next step or suggestion (soft, never imperative)

When the user asks "Кои X са на нула?" or "Покажи zombie стоката" — LIST THEM ALL BY NAME with quantities and values. Never say "имаш 8 артикула" without naming them.

EXAMPLE BAD: "Имаш 8 zombie артикула за 1200 €."
EXAMPLE GOOD: "8 артикула стоят 45+ дни без продажба (1,200 €):
1. Nike Air Max 42 — 3 бр × 89 € = 267 €, 67 дни
2. Adidas Superstar 38 — 5 бр × 79 € = 395 €, 52 дни
3. ...
Помисли за -20% на първите 3 — те държат 680 €."

CONTEXT:
- Store: {$store_name}, {$city}
- Business: {$business_type} | Country: {$country}
- Role: {$role_ctx}
- Date: {$today_str} ({$weekday}) | Time: {$time_ctx}
- Confidence: {$confidence_pct}% of products have cost price ({$with_cost}/{$total_products})

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 2A — ALL PRODUCTS ({$total_products} items, stock value ~{$currency} {round($total_stock_value)})
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$products_dump}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 2B — SALES BY PRODUCT (last 30 days)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$sales_product_dump}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 2C — DAILY SALES (last 30 days)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$daily_dump}
TODAY: {$today_rev} {$currency} from {$today_cnt} sales ({$dsign}{$diff_pct}% vs yesterday)
THIS WEEK: {$week_rev} {$currency}
AVG {$weekday}: {$avg_rev} {$currency} (today = {$pct_avg}% of average)
{$profit_block}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 2D — CATEGORIES SUMMARY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$categories_dump}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 2E — SUPPLIERS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$suppliers_dump}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 2F — UNPAID CUSTOMER INVOICES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$invoices_dump}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 2G — CUSTOMERS (last 90 days)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$customers_dump}
PROMPT;

    if ($sellers_dump) {
        $prompt .= <<<SELLERS

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 2H — SELLERS PERFORMANCE (30 days)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$sellers_dump}
SELLERS;
    }

    if ($del_str) {
        $prompt .= <<<DELIVERIES

PENDING DELIVERIES:
{$del_str}
DELIVERIES;
    }

    $prompt .= <<<REST

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 3 — MEMORY (what you know about this owner)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$mem_str}
Use memory naturally — never say "According to my memory..."

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 4 — BUSINESS SIGNALS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$signals_str}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 4B — ACTIVE SIGNALS (shown on user screen right now)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$active_signals_str}
The user sees these signals on their dashboard. When they ask about a signal topic, you already know the context — answer directly with specifics, don't repeat what they already read.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 5 — SEASONAL CONTEXT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$seasonal_str}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 6 — LANGUAGE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ALWAYS respond in Bulgarian (разговорен).
UNDERSTAND dialects: "якеца" = яке, "чифтът" = чифт обувки, "пусни" = намали, "зареди" = поръчай, "кяр" = печалба, "що не върви" = zombie, "де са парите" = оборот, "пратката" = доставка.
NEVER correct spelling. Just understand and answer.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
THE ONE LAW
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Пешо не пише. Пешо говори. Когато пита — давай КОНКРЕТНИ имена, числа, стойности. Никога "имаш N артикула" без да ги изброиш. AI е управителят — докладва с факти, предлага меко.
REST;


    // ═══════════════════════════════════════
    // LAYER 7 — AI TOPICS (1000 topics, pick 5-8)
    // ═══════════════════════════════════════
    $plan = 'pro';
    try {
        $sub = DB::run('SELECT plan FROM subscriptions WHERE tenant_id=? ORDER BY created_at DESC LIMIT 1', [$tenant_id])->fetch();
        if ($sub) $plan = strtolower($sub['plan'] ?? 'free');
    } catch (Exception $e) {}

    $days_of_data = 0;
    try {
        $fs = DB::run('SELECT MIN(created_at) FROM sales WHERE tenant_id=? AND status!="canceled"', [$tenant_id])->fetchColumn();
        if ($fs) $days_of_data = (int)((time() - strtotime($fs)) / 86400);
    } catch (Exception $e) {}

    $total_customers = 0;
    try { $total_customers = (int)DB::run('SELECT COUNT(DISTINCT customer_id) FROM sales WHERE tenant_id=? AND customer_id IS NOT NULL AND status!="canceled"', [$tenant_id])->fetchColumn(); } catch (Exception $e) {}

    $sellers_count = 0;
    try { $sellers_count = (int)DB::run('SELECT COUNT(*) FROM users WHERE tenant_id=? AND role="seller" AND is_active=1', [$tenant_id])->fetchColumn(); } catch (Exception $e) {}

    $has_multi_store = false;
    try { $has_multi_store = (int)DB::run('SELECT COUNT(*) FROM stores WHERE tenant_id=?', [$tenant_id])->fetchColumn() > 1; } catch (Exception $e) {}

    $dataStats = [
        'days_of_data' => $days_of_data,
        'total_products' => $total_products,
        'total_sales' => 0,
        'total_customers' => $total_customers,
        'has_cost_price' => ($total_products > 0 && $below_cost >= 0),
        'has_wholesale' => false,
        'has_returns' => false,
        'has_invoices' => false,
        'has_deliveries' => false,
        'has_multi_store' => $has_multi_store,
        'sellers_count' => $sellers_count,
        'has_variations' => false,
    ];

    $topicsBlock = selectRelevantTopics($tenant_id, $store_id, $role, $plan, $country, $dataStats);
    if ($topicsBlock) {
        $prompt .= $topicsBlock;
    }

    // ═══════════════════════════════════════
    // LAYER 8 — WEATHER FORECAST
    // ═══════════════════════════════════════
    $weatherBlock = getWeatherSummary($store_id, $tenant_id, 14);
    if ($weatherBlock) {
        $prompt .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nLAYER 8 — WEATHER FORECAST\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . $weatherBlock;
    }


    return $prompt;
}
