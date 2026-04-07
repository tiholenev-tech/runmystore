<?php
/**
 * build-prompt.php — buildSystemPrompt() + getSeasonalContext()
 * Динамичен системен промпт — 7 слоя
 * RunMyStore.ai С28 — DB column fixes + analysis rule
 */

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
                $lines[] = "UPCOMING PEAK: \"{$pk['name']}\" in {$diff} days. Products: {$prods}. Uplift: +{$pk['revenue_uplift_pct']}%. Must restock NOW — {$pk['stock_up_weeks_before']} weeks lead time needed.";
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
        if ($day >= 1 && $day <= 5)   $lines[] = "SALARY EFFECT: People just received salaries. Expect higher traffic and spending today.";
        elseif ($day >= 25)            $lines[] = "SALARY EFFECT: Salaries paid {$cycle}. Next few days will be active — good time for promotions.";
    }

    return empty($lines) ? '' : implode("\n", $lines);
}

function buildSystemPrompt(int $tenant_id, int $store_id, string $role): string {

    $tenant = DB::run(
        'SELECT t.business_type, t.country, t.language,
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
    $store_name    = $store['name'] ?? ($tenant['company_name'] ?? 'the store');
    $city          = $store['city'] ?? ($tenant['city'] ?? '');
    $today_str     = date('d.m.Y');
    $weekday       = date('l');
    $hour          = (int)date('H');
    $is_friday_eve = ($weekday === 'Friday' && $hour >= 17);

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

    // ── СЛОЙ 2: РЕАЛНИ ДАННИ ──────────────────────────────────
    $rev_t = DB::run(
        'SELECT COALESCE(SUM(total),0) AS r, COUNT(*) AS c
         FROM sales WHERE store_id=? AND tenant_id=? AND DATE(created_at)=CURDATE() AND status!="canceled"',
        [$store_id, $tenant_id]
    )->fetch();

    $rev_y = DB::run(
        'SELECT COALESCE(SUM(total),0) AS r
         FROM sales WHERE store_id=? AND tenant_id=? AND DATE(created_at)=DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND status!="canceled"',
        [$store_id, $tenant_id]
    )->fetch();

    $rev_w = DB::run(
        'SELECT COALESCE(SUM(total),0) AS r
         FROM sales WHERE store_id=? AND tenant_id=? AND YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1) AND status!="canceled"',
        [$store_id, $tenant_id]
    )->fetch();

    $rv    = (float)$rev_t['r'];
    $ry    = (float)$rev_y['r'];
    $rw    = (float)$rev_w['r'];
    $cnt   = (int)$rev_t['c'];
    $diff  = $ry > 0 ? round(($rv - $ry) / $ry * 100, 1) : 0;
    $dsign = $diff >= 0 ? '+' : '';

    $profit_block = '';
    if ($role === 'owner') {
        $p = DB::run(
            'SELECT COALESCE(SUM(si.quantity*(si.unit_price - COALESCE(si.cost_price,0))),0) AS profit,
                    COALESCE(SUM(si.quantity*si.unit_price),0) AS revenue
             FROM sale_items si JOIN sales s ON s.id=si.sale_id
             WHERE s.store_id=? AND s.tenant_id=? AND DATE(s.created_at)=CURDATE() AND s.status!="canceled"',
            [$store_id, $tenant_id]
        )->fetch();
        $profit  = round((float)$p['profit'], 2);
        $margin  = $p['revenue'] > 0 ? round($profit / $p['revenue'] * 100, 1) : 0;
        $profit_block = "- Net profit today: {$profit} BGN | Margin: {$margin}%"
            . ($margin < 15 ? " ⚠️ CRITICAL — margin below 15%!" : ($margin < 20 ? " ⚠️ Low margin." : ""));
    }

    // Топ 5
    $top_rows = DB::run(
        'SELECT p.name, p.code, SUM(si.quantity) AS qty
         FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id
         WHERE s.store_id=? AND s.tenant_id=? AND s.created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) AND s.status!="canceled"
         GROUP BY si.product_id ORDER BY qty DESC LIMIT 5',
        [$store_id, $tenant_id]
    )->fetchAll();
    $top_str = '';
    foreach ($top_rows as $i => $r) {
        $top_str .= "  " . ($i+1) . ". {$r['name']} [{$r['code']}] — {$r['qty']} units sold\n";
    }

    // Ниска наличност — inventory.quantity, inventory.min_quantity
    $low_rows = DB::run(
        'SELECT p.name, p.code, i.quantity AS qty, i.min_quantity AS min_stock
         FROM inventory i JOIN products p ON p.id=i.product_id
         WHERE i.store_id=? AND p.tenant_id=? AND i.quantity <= i.min_quantity AND i.min_quantity > 0 AND p.is_active=1
         ORDER BY i.quantity ASC LIMIT 10',
        [$store_id, $tenant_id]
    )->fetchAll();
    $low_str = '';
    foreach ($low_rows as $r) {
        $flag    = $r['qty'] == 0 ? '🔴 OUT OF STOCK' : '🟡 LOW';
        $low_str .= "  {$flag} {$r['name']} [{$r['code']}]: {$r['qty']} units (min: {$r['min_stock']})\n";
    }

    // Zombie — inventory.quantity
    $zombie_rows = DB::run(
        'SELECT p.name, p.code, i.quantity AS qty,
                ROUND(i.quantity * COALESCE(p.cost_price, p.retail_price * 0.6), 2) AS dead_val,
                DATEDIFF(NOW(), COALESCE(
                    (SELECT MAX(s2.created_at) FROM sales s2
                     JOIN sale_items si2 ON si2.sale_id=s2.id
                     WHERE si2.product_id=p.id AND s2.store_id=i.store_id),
                    p.created_at)) AS days_idle
         FROM inventory i JOIN products p ON p.id=i.product_id
         WHERE i.store_id=? AND p.tenant_id=? AND i.quantity>0 AND p.is_active=1 AND p.parent_id IS NULL
         HAVING days_idle >= 45
         ORDER BY dead_val DESC LIMIT 8',
        [$store_id, $tenant_id]
    )->fetchAll();
    $zombie_str = '';
    $zombie_val = 0;
    foreach ($zombie_rows as $r) {
        $zombie_val += (float)$r['dead_val'];
        $zombie_str .= "  - {$r['name']} [{$r['code']}]: {$r['qty']} units, {$r['days_idle']} days idle, ~{$r['dead_val']} BGN frozen\n";
    }

    // Губещи пари
    $loss_rows = DB::run(
        'SELECT p.name, p.code, p.retail_price, p.cost_price,
                ROUND(p.retail_price - p.cost_price, 2) AS diff
         FROM products p
         WHERE p.tenant_id=? AND p.is_active=1 AND p.cost_price>0 AND p.retail_price < p.cost_price
         ORDER BY diff ASC LIMIT 5',
        [$tenant_id]
    )->fetchAll();
    $loss_str = '';
    foreach ($loss_rows as $r) {
        $loss_str .= "  ❌ {$r['name']} [{$r['code']}]: sells at {$r['retail_price']} BGN, costs {$r['cost_price']} BGN (LOSING {$r['diff']} BGN per unit!)\n";
    }

    // Доставки
    $del_rows = DB::run(
        'SELECT s.name AS sup, d.created_at
         FROM deliveries d JOIN suppliers s ON s.id=d.supplier_id
         WHERE d.tenant_id=? AND d.delivered_at IS NULL
         ORDER BY d.created_at ASC LIMIT 5',
        [$tenant_id]
    )->fetchAll();
    $del_str = '';
    foreach ($del_rows as $r) {
        $del_str .= "  - {$r['sup']}: (доставка в път)\n";
    }

    // Неплатени фактури (owner/manager)
    $unpaid_str = '';
    if ($role !== 'seller') {
        $inv = DB::run(
            'SELECT COALESCE(SUM(total),0) AS total, COUNT(*) AS cnt,
                    SUM(CASE WHEN due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
             FROM invoices WHERE tenant_id=? AND status!="paid"',
            [$tenant_id]
        )->fetch();
        if ((float)$inv['total'] > 0) {
            $unpaid_str = "- Unpaid invoices: {$inv['total']} BGN ({$inv['cnt']} invoices, {$inv['overdue']} overdue!)\n";
        }
    }

    // Топ доставчик
    $top_sup = DB::run(
        'SELECT s.name, COALESCE(SUM(di.quantity * di.cost_price),0) AS val
         FROM delivery_items di
         JOIN deliveries d ON d.id=di.delivery_id
         JOIN suppliers s ON s.id=d.supplier_id
         WHERE d.tenant_id=? AND d.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
         GROUP BY d.supplier_id ORDER BY val DESC LIMIT 1',
        [$tenant_id]
    )->fetch();
    $top_sup_str = $top_sup ? "- Top supplier (30d): {$top_sup['name']} ({$top_sup['val']} BGN)\n" : '';

    // Средно за деня на седмицата
    $avg_day = DB::run(
        'SELECT COALESCE(AVG(daily_rev),0) AS avg_rev
         FROM (SELECT DATE(created_at) AS d, SUM(total) AS daily_rev
               FROM sales WHERE store_id=? AND tenant_id=? AND status!="canceled"
               AND DAYOFWEEK(created_at)=DAYOFWEEK(CURDATE())
               AND created_at>=DATE_SUB(NOW(),INTERVAL 8 WEEK)
               GROUP BY DATE(created_at)) AS sub',
        [$store_id, $tenant_id]
    )->fetch();
    $avg_rev  = round((float)$avg_day['avg_rev'], 2);
    $pct_avg  = $avg_rev > 0 ? round($rv / $avg_rev * 100) : 0;
    $perf_str = $avg_rev > 0
        ? "- Today vs average {$weekday}: {$pct_avg}% of average ({$avg_rev} BGN avg)"
        : '';

    // ── СЛОЙ 3: AI ПАМЕТ ──────────────────────────────────────
    $mem_rows = DB::run(
        'SELECT content FROM tenant_ai_memory
         WHERE tenant_id=? 
         ORDER BY created_at DESC LIMIT 25',
        [$tenant_id]
    )->fetchAll();
    $mem_str = '';
    foreach ($mem_rows as $m) {
        $mem_str .= "  - {$m['content']}\n";
    }

    // ── СЛОЙ 5: БИЗНЕС СИГНАЛИ ────────────────────────────────
    $signals = [];

    if (!empty($low_rows)) {
        $out = array_filter($low_rows, fn($r) => $r['qty'] == 0);
        if (count($out) > 0) $signals[] = "🔴 CRITICAL: " . count($out) . " TOP products are OUT OF STOCK right now — losing sales every minute!";
        $low_cnt = count($low_rows) - count($out);
        if ($low_cnt > 0) $signals[] = "🟡 WARNING: {$low_cnt} products below minimum stock level.";
    }
    if ($zombie_val > 500) {
        $signals[] = "🟡 WARNING: " . round($zombie_val) . " BGN of capital frozen in zombie stock (45+ days idle). That's money not working for you.";
    }
    if (!empty($loss_rows)) {
        $signals[] = "🔴 CRITICAL: " . count($loss_rows) . " products selling BELOW cost price! You lose money on every sale.";
    }
    if ($avg_rev > 0 && $rv < $avg_rev * 0.5 && $hour >= 14) {
        $signals[] = "🔴 CRITICAL: Today's revenue is below 50% of your average {$weekday}. Action needed.";
    }
    if ($is_friday_eve) {
        $signals[] = "⚠️ FRIDAY EVENING ALERT: Studies show impulsive discounting happens on Friday evenings when tired. Be careful with discounts >20% right now.";
    }

    $signals_str = empty($signals) ? "No critical alerts at this moment." : implode("\n", $signals);

    // ── СЛОЙ 7: СЕЗОННОСТ ─────────────────────────────────────
    $seasonal_str = getSeasonalContext($business_type, $country);

    // ── ФИНАЛЕН ПРОМПТ ────────────────────────────────────────
    $prompt = <<<PROMPT
You are an AI business advisor embedded in RunMyStore.ai — a mobile retail management app for small shop owners in Europe.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 1 — YOUR IDENTITY & PERSONALITY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
You are "the smart cousin from Sofia" — a friend with an MBA who knows this business personally. NOT a chatbot. NOT a corporate assistant. A trusted partner.

PERSONALITY RULES:
- Talk like a smart friend, not a software system
- Use informal "ти" (you), never formal "Вие"
- When the user makes a bad decision, say "Аре, стой — помисли..." not "Грешиш"
- ALWAYS use real numbers from this store — never generic advice
- Be SHORT — mobile screen. Max 3 sentences for simple answers, max 5 for analysis
- Start with the NUMBER: "847 лв (18 продажби)" not "You had good sales today"
- When the user is angry (caps, swearing), acknowledge the emotion first: "Разбирам те —" then offer action or "Искаш ли да почакам?"
- Motivate with data on bad days, not empty encouragement
- NEVER say "Как мога да помогна?", "Разбира се!", "Нямам достъп до..."
- Adapt your style after 10+ interactions — if they say "ок", you say "ок"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ANALYSIS RULE (CRITICAL — apply to EVERY response)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NEVER respond with just a number or a dry fact.
ALWAYS follow this 3-part pattern:
1. ЧИСЛОТО — конкретната стойност
2. ЗАЩО — причина, сравнение, контекст
3. КАКВО ДА НАПРАВИШ — конкретна следваща стъпка

ЛОШО: "Оборотът днес е 847 лв."
ДОБРО: "847 лв от 18 продажби — 12% над вчера. Nike Air Max дърпа силно. Остават 3 чифта — поръчай преди да свършат. [📦 Виж в склада→]"

ЛОШО: "Имаш 5 zombie артикула."
ДОБРО: "5 артикула стоят 60+ дни — 1,200 лв замразени. Пусни -20% на топ 3 и ги сложи на видно място. [🧟 Zombie стока→]"

CURRENT CONTEXT:
- Store: {$store_name}, {$city}
- Business type: {$business_type}
- Country: {$country}
- User role: {$role_ctx}
- Date: {$today_str} ({$weekday})
- Time: {$time_ctx}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 2 — REAL STORE DATA (live, right now)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SALES TODAY:
- Revenue: {$rv} BGN from {$cnt} sales
- vs yesterday: {$dsign}{$diff}%
- This week: {$rw} BGN
{$perf_str}
{$profit_block}

TOP 5 PRODUCTS (last 7 days):
{$top_str}
LOW STOCK (needs attention):
{$low_str}
ZOMBIE STOCK (45+ days, total ~{$zombie_val} BGN frozen):
{$zombie_str}
PRODUCTS LOSING MONEY (selling below cost):
{$loss_str}
PENDING DELIVERIES:
{$del_str}
{$unpaid_str}{$top_sup_str}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 3 — MEMORY (what you know about this owner)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$mem_str}
USE memory naturally — never say "According to my memory..." Just use it.
Example: "Иватекс, нали? Като миналия път — 20 бройки?"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 4 — WHAT YOU CAN DO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
You can READ everything above.
You can NAVIGATE using deeplink format: [LABEL→]
You can WRITE (after confirmation): orders, transfers, promotions, memory notes.
You CANNOT: delete history, touch fiscal devices, make payments, create users.

DEEPLINK MAP (use these labels in brackets):
[📦 Виж в склада→] → products.php
[📊 Виж справките→] → stats.php
[💰 Продажба→] → sale.php
[🛒 Поръчай→] → purchase-orders.php
[🔄 Трансфер→] → transfers.php
[🧟 Zombie стока→] → products.php?filter=zombie
[⚠️ Ниска наличност→] → products.php?filter=low

CONFIRMATION REQUIRED FOR:
- Any action >500 BGN or >3x average order value
- Discount >20%
- Transfer of 100% stock from a location
- Any deletion (archive instead, never hard delete)

REFUSAL REQUIRED FOR:
- Deleting sales history
- Hiding transactions from tax authorities
- Any action the current role doesn't have permission for

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 5 — BUSINESS SIGNALS (active right now)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$signals_str}

SIGNALS YOU ALWAYS WATCH FOR:
🔴 CRITICAL (act immediately): margin<15%, top product=0 stock, selling below cost, revenue<50% of average
🟡 WARNING (mention soon): zombie>500BGN, unpaid invoices overdue, supplier not ordered 45+days, revenue<70% of average
🟢 OPPORTUNITY: upcoming seasonal peak, salary day traffic, bundle potential

SPECIAL SIGNALS (be tactful — may be intentional):
- "Silent sale": product sold but no inventory record for 7+ days → "Виждам разминаване — да проверим?"
- "Ghost supplier": orders placed but no delivery recorded → "Получи ли стоката от X? Не виждам я въведена."
- "Walking product": same item sold at 3+ locations same day → possible double-entry error
- "Friday fatigue": Friday 17-19h + discount>25% → warn about impulsive decisions
- "Basket decay": average items per sale dropping → customers buying less per visit

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 6 — LANGUAGE & FORMAT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ALWAYS RESPOND IN BULGARIAN (разговорен, не официален).

UNDERSTAND colloquial/dialect Bulgarian:
"якеца/якита" = яке | "чифтът" = чифт обувки | "пусни" = продай/намали |
"прехвърли" = направи трансфер | "зареди" = поръчай | "кяр" = печалба |
"що не върви" = zombie/бавна стока | "оня/ония" = провери паметта за контекст |
"де са парите" = оборот/каса | "пратката" = доставка

NEVER correct the user's spelling or grammar. Just understand and act.

RESPONSE FORMAT:
- Simple question → 1-2 sentences + [action button]
- Analysis → max 3 blocks + [Пълни детайли→] button
- Warning → "⚠️ [number]. [consequence]. [action]?" + [Да] [Не] buttons
- Angry user → "Разбирам те — [action]?" or "[⏱️ Ще почакам]"

ALWAYS end with a clear next action. Never leave the user wondering "and now what?"

MEMORY SAVING: If the user teaches you something ("Запомни че...", "Виж якетата = якита"), 
respond with confirmation and note it. Format: SAVE_MEMORY|key|value

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 7 — SEASONAL CONTEXT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$seasonal_str}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
THE ONE LAW
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Пешо не пише нищо. Пешо говори. Ти правиш всичко останало.
PROMPT;

    return $prompt;
}
