<?php
/**
 * RunMyStore.ai — compute-insights.php
 * S52 | 12.04.2026
 * 
 * Изчислява всички insights за даден tenant+store.
 * Извиква се от cron-insights.php на всеки 15 мин.
 * 
 * UPSERT: INSERT ... ON DUPLICATE KEY UPDATE (по tenant_id+store_id+topic_id)
 * PHP пресмята, Gemini НЕ участва. Нула API cost.
 * 
 * ПРАВИЛО: Всеки нов модул добавя нови функции тук.
 * S52=30, +sale.php=45, +deliveries=65, +лоялна=75, +transfers=85
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/helpers.php';

// ══════════════════════════════════════
// UPSERT HELPER
// ══════════════════════════════════════

function upsertInsight(int $tid, int $sid, string $topicId, array $d): void {
    DB::run(
        "INSERT INTO ai_insights 
            (tenant_id, store_id, topic_id, category, grp, module, urgency, plan_gate, role_gate, 
             title, detail_text, data_json, value_numeric, product_count, created_at, expires_at)
         VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))
         ON DUPLICATE KEY UPDATE
            category=VALUES(category), grp=VALUES(grp), module=VALUES(module), urgency=VALUES(urgency),
            plan_gate=VALUES(plan_gate), role_gate=VALUES(role_gate), title=VALUES(title), 
            detail_text=VALUES(detail_text), data_json=VALUES(data_json), value_numeric=VALUES(value_numeric),
            product_count=VALUES(product_count), created_at=NOW(), expires_at=DATE_ADD(NOW(), INTERVAL 30 MINUTE)",
        [
            $tid, $sid, $topicId,
            $d['category'], $d['grp'] ?? 1, $d['module'] ?? 'home', $d['urgency'] ?? 'info',
            $d['plan_gate'] ?? 'pro', $d['role_gate'] ?? 'owner,manager',
            $d['title'], $d['detail_text'] ?? null,
            isset($d['data']) ? json_encode($d['data'], JSON_UNESCAPED_UNICODE) : null,
            $d['value'] ?? null, $d['count'] ?? null
        ]
    );
}

function cleanExpiredInsights(int $tid, int $sid): void {
    DB::run(
        "DELETE FROM ai_insights WHERE tenant_id=? AND store_id=? AND expires_at < NOW()",
        [$tid, $sid]
    );
}

// ══════════════════════════════════════
// ГЛАВНА ФУНКЦИЯ
// ══════════════════════════════════════

function computeAllInsights(int $tid, int $sid, string $currency): void {
    computeZeroStockBestsellers($tid, $sid, $currency);
    computeCriticalLow($tid, $sid, $currency);
    computeBelowMinimum($tid, $sid, $currency);
    computeOverstock($tid, $sid, $currency);
    computeZombie30($tid, $sid, $currency);
    computeZombie60($tid, $sid, $currency);
    computeSizeGaps($tid, $sid, $currency);
    computeNewNoSales($tid, $sid, $currency);
    computeNewSellThrough($tid, $sid, $currency);
    computeNoPhoto($tid, $sid);
    computeNoCostPrice($tid, $sid);
    computeNoBarcode($tid, $sid);
    computeNoSupplier($tid, $sid);
    computeNoCategory($tid, $sid);
    computeSellingAtLoss($tid, $sid, $currency);
    computeLowMargin($tid, $sid, $currency);
    computeWholesaleCloseToRetail($tid, $sid, $currency);
    computeTopProfitable($tid, $sid, $currency);
    computeBottomProfitable($tid, $sid, $currency);
    computeFrozenCapital($tid, $sid, $currency);
    computeDiscountExpiring($tid, $sid, $currency);
    computeDiscountOnBestseller($tid, $sid, $currency);
    computeDiscountBelowCost($tid, $sid, $currency);
    computeRevenueTodayVsYesterday($tid, $sid, $currency);
    computeRevenueWeekVsWeek($tid, $sid, $currency);
    computeRevenueMonthVsMonth($tid, $sid, $currency);
    computeBasketTrend($tid, $sid, $currency);
    computeTopCategoryRevenue($tid, $sid, $currency);
    computeTopSupplierRevenue($tid, $sid, $currency);
    computeUncounted30d($tid, $sid);
    cleanExpiredInsights($tid, $sid);
}

// ══════════════════════════════════════
// ГРУПА 1: СТОКА И НАЛИЧНОСТИ
// ══════════════════════════════════════

/** #1 — Бестселъри на нула (продажба в 30д, но quantity=0) */
function computeZeroStockBestsellers(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, p.code, p.retail_price,
                SUM(si.quantity) as sold_30d
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         JOIN sale_items si ON si.product_id=p.id
         JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
              AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity<=0
         GROUP BY p.id
         ORDER BY sold_30d DESC
         LIMIT 20",
        [$sid, $sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    $names = array_map(fn($r) => $r['name'], array_slice($rows, 0, 3));
    $dailyLoss = array_sum(array_map(fn($r) => ($r['sold_30d'] / 30) * $r['retail_price'], $rows));
    
    upsertInsight($tid, $sid, 'stock_zero_bestsellers', [
        'category' => 'stock', 'grp' => 1, 'module' => 'home', 'urgency' => 'critical',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => count($rows) . ' бестселъра на нула — губиш ~' . fmtMoney($dailyLoss, $cur) . '/ден',
        'detail_text' => implode(', ', $names) . (count($rows) > 3 ? '...' : ''),
        'data' => ['items' => $rows], 'value' => $dailyLoss, 'count' => count($rows)
    ]);
}

/** #2 — Критично ниски (1-2 бр, с продажби в 30д) */
function computeCriticalLow(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, p.code, i.quantity, p.retail_price
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0 AND i.quantity <= 2
         AND p.id IN (
             SELECT si.product_id FROM sale_items si
             JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
             AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         )
         ORDER BY i.quantity ASC
         LIMIT 20",
        [$sid, $tid, $sid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    $names = array_map(fn($r) => $r['name'] . ' (' . fmtQty($r['quantity']) . ')', array_slice($rows, 0, 3));
    
    upsertInsight($tid, $sid, 'stock_critical_low', [
        'category' => 'stock', 'grp' => 1, 'module' => 'home', 'urgency' => 'warning',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => count($rows) . ' артикула с 1-2 броя — скоро ще свършат',
        'detail_text' => implode(', ', $names),
        'data' => ['items' => $rows], 'count' => count($rows)
    ]);
}

/** #3 — Под минимално количество */
function computeBelowMinimum(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, p.code, i.quantity, 
                GREATEST(i.min_quantity, p.min_quantity) as min_qty
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 
         AND i.quantity < GREATEST(i.min_quantity, p.min_quantity)
         AND GREATEST(i.min_quantity, p.min_quantity) > 0
         ORDER BY (GREATEST(i.min_quantity, p.min_quantity) - i.quantity) DESC
         LIMIT 20",
        [$sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    upsertInsight($tid, $sid, 'stock_below_minimum', [
        'category' => 'stock', 'grp' => 1, 'module' => 'home', 'urgency' => 'warning',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => count($rows) . ' артикула под минимално количество',
        'detail_text' => implode(', ', array_map(fn($r) => $r['name'], array_slice($rows, 0, 3))),
        'data' => ['items' => $rows], 'count' => count($rows)
    ]);
}

/** #4 — Свръхналичност (quantity > 10x месечна продажба) */
function computeOverstock(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, i.quantity, p.cost_price,
                COALESCE(sold.qty, 0) as sold_30d,
                i.quantity * p.cost_price as frozen
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         LEFT JOIN (
             SELECT si.product_id, SUM(si.quantity) as qty
             FROM sale_items si JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
             AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY si.product_id
         ) sold ON sold.product_id=p.id
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0
         AND COALESCE(sold.qty, 0) > 0
         AND i.quantity > COALESCE(sold.qty, 0) * 10
         ORDER BY frozen DESC
         LIMIT 15",
        [$sid, $sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    $totalFrozen = array_sum(array_column($rows, 'frozen'));
    
    upsertInsight($tid, $sid, 'stock_overstock', [
        'category' => 'stock', 'grp' => 1, 'module' => 'products', 'urgency' => 'info',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => count($rows) . ' артикула с прекалено много наличност — ' . fmtMoney($totalFrozen, $cur) . ' замразени',
        'data' => ['items' => $rows], 'value' => $totalFrozen, 'count' => count($rows)
    ]);
}

/** #5 — Zombie 30+ дни без продажба */
function computeZombie30(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, i.quantity, p.cost_price,
                i.quantity * p.cost_price as frozen,
                DATEDIFF(NOW(), COALESCE(last_sale.last_date, p.created_at)) as days_idle
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         LEFT JOIN (
             SELECT si.product_id, MAX(s.created_at) as last_date
             FROM sale_items si JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
             GROUP BY si.product_id
         ) last_sale ON last_sale.product_id=p.id
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0
         AND DATEDIFF(NOW(), COALESCE(last_sale.last_date, p.created_at)) BETWEEN 30 AND 59
         ORDER BY frozen DESC
         LIMIT 20",
        [$sid, $sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    $totalFrozen = array_sum(array_column($rows, 'frozen'));
    
    upsertInsight($tid, $sid, 'zombie_30d', [
        'category' => 'zombie', 'grp' => 1, 'module' => 'home', 'urgency' => 'warning',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => count($rows) . ' артикула стоят 30+ дни — ' . fmtMoney($totalFrozen, $cur) . ' замразени',
        'detail_text' => implode(', ', array_map(fn($r) => $r['name'] . ' (' . $r['days_idle'] . 'д)', array_slice($rows, 0, 3))),
        'data' => ['items' => $rows], 'value' => $totalFrozen, 'count' => count($rows)
    ]);
}

/** #6 — Zombie 60+ дни (критично замразени пари) */
function computeZombie60(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, i.quantity, p.cost_price,
                i.quantity * p.cost_price as frozen,
                DATEDIFF(NOW(), COALESCE(last_sale.last_date, p.created_at)) as days_idle
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         LEFT JOIN (
             SELECT si.product_id, MAX(s.created_at) as last_date
             FROM sale_items si JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
             GROUP BY si.product_id
         ) last_sale ON last_sale.product_id=p.id
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0
         AND DATEDIFF(NOW(), COALESCE(last_sale.last_date, p.created_at)) >= 60
         ORDER BY frozen DESC
         LIMIT 20",
        [$sid, $sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    $totalFrozen = array_sum(array_column($rows, 'frozen'));
    
    upsertInsight($tid, $sid, 'zombie_60d', [
        'category' => 'zombie', 'grp' => 1, 'module' => 'home', 'urgency' => 'critical',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => count($rows) . ' артикула стоят 60+ дни — ' . fmtMoney($totalFrozen, $cur) . ' замразени',
        'detail_text' => implode(', ', array_map(fn($r) => $r['name'] . ' (' . $r['days_idle'] . 'д)', array_slice($rows, 0, 3))),
        'data' => ['items' => $rows], 'value' => $totalFrozen, 'count' => count($rows)
    ]);
}

/** #7 — Размерни дупки (parent има вариации, някои на нула, други се продават) */
function computeSizeGaps(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT parent.id as parent_id, parent.name as parent_name,
                p.id, p.name, p.size, p.color, i.quantity
         FROM products p
         JOIN products parent ON parent.id=p.parent_id AND parent.is_active=1
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity<=0
         AND parent.id IN (
             SELECT DISTINCT p2.parent_id FROM products p2
             JOIN inventory i2 ON i2.product_id=p2.id AND i2.store_id=?
             JOIN sale_items si ON si.product_id=p2.id
             JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
                  AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             WHERE p2.parent_id IS NOT NULL AND i2.quantity > 0
         )
         ORDER BY parent.name
         LIMIT 20",
        [$sid, $tid, $sid, $sid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    $parents = [];
    foreach ($rows as $r) {
        $parents[$r['parent_name']][] = $r['size'] ?: $r['color'] ?: '?';
    }
    $detail = [];
    foreach (array_slice($parents, 0, 3, true) as $name => $sizes) {
        $detail[] = $name . ': липсва ' . implode(', ', $sizes);
    }
    
    upsertInsight($tid, $sid, 'stock_size_gaps', [
        'category' => 'size', 'grp' => 1, 'module' => 'products', 'urgency' => 'warning',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => count($rows) . ' размерни дупки — клиентите питат, ти нямаш',
        'detail_text' => implode('; ', $detail),
        'data' => ['items' => $rows], 'count' => count($rows)
    ]);
}

/** #8 — Нови артикули 7+ дни без продажба (FIXED: LEFT JOIN instead of NOT IN) */
function computeNewNoSales(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, p.code, i.quantity,
                DATEDIFF(NOW(), p.created_at) as days_since_add
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         LEFT JOIN (
             SELECT DISTINCT si.product_id
             FROM sale_items si
             JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
         ) sold ON sold.product_id=p.id
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0
         AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         AND DATEDIFF(NOW(), p.created_at) >= 7
         AND sold.product_id IS NULL
         ORDER BY p.created_at ASC
         LIMIT 15",
        [$sid, $sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    upsertInsight($tid, $sid, 'new_no_sales_7d', [
        'category' => 'new', 'grp' => 1, 'module' => 'products', 'urgency' => 'warning',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => count($rows) . ' нови артикула без продажба 7+ дни',
        'detail_text' => implode(', ', array_map(fn($r) => $r['name'] . ' (' . $r['days_since_add'] . 'д)', array_slice($rows, 0, 3))),
        'data' => ['items' => $rows], 'count' => count($rows)
    ]);
}

/** #9 — Нови артикули: sell-through първа седмица */
function computeNewSellThrough(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, 
                COALESCE(sold.qty, 0) as sold_qty,
                COALESCE(sold.revenue, 0) as revenue
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         LEFT JOIN (
             SELECT si.product_id, SUM(si.quantity) as qty, SUM(si.total) as revenue
             FROM sale_items si JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
             GROUP BY si.product_id
         ) sold ON sold.product_id=p.id
         WHERE p.tenant_id=? AND p.is_active=1
         AND p.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)
         AND COALESCE(sold.qty, 0) > 0
         ORDER BY sold.revenue DESC
         LIMIT 5",
        [$sid, $sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    $totalRevenue = array_sum(array_column($rows, 'revenue'));
    
    upsertInsight($tid, $sid, 'new_sell_through', [
        'category' => 'new', 'grp' => 1, 'module' => 'products', 'urgency' => 'info',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => 'Нови хитове: ' . $rows[0]['name'] . ' — ' . fmtMoney($rows[0]['revenue'], $cur) . ' за първата седмица',
        'data' => ['items' => $rows], 'value' => $totalRevenue, 'count' => count($rows)
    ]);
}

// ══════════════════════════════════════
// ГРУПА 1: DATA QUALITY (ВИДИМА ЗА START+!)
// ══════════════════════════════════════

/** #10 — Без снимка */
function computeNoPhoto(int $tid, int $sid): void {
    $count = DB::run(
        "SELECT COUNT(*) FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0
         AND (p.image_url IS NULL OR p.image_url='')",
        [$sid, $tid]
    )->fetchColumn();
    
    if ($count == 0) return;
    
    upsertInsight($tid, $sid, 'dq_no_photo', [
        'category' => 'data_quality', 'grp' => 1, 'module' => 'products', 'urgency' => 'info',
        'plan_gate' => 'start', 'role_gate' => 'owner,manager',
        'title' => $count . ' артикула без снимка — снимка = 3x повече продажби',
        'count' => $count
    ]);
}

/** #11 — Без доставна цена */
function computeNoCostPrice(int $tid, int $sid): void {
    $count = DB::run(
        "SELECT COUNT(*) FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0 AND p.cost_price<=0",
        [$sid, $tid]
    )->fetchColumn();
    
    if ($count == 0) return;
    
    upsertInsight($tid, $sid, 'dq_no_cost_price', [
        'category' => 'data_quality', 'grp' => 1, 'module' => 'products', 'urgency' => 'info',
        'plan_gate' => 'start', 'role_gate' => 'owner',
        'title' => $count . ' артикула без доставна цена — не можем да покажем печалба',
        'count' => $count
    ]);
}

/** #12 — Без баркод */
function computeNoBarcode(int $tid, int $sid): void {
    $count = DB::run(
        "SELECT COUNT(*) FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0
         AND (p.barcode IS NULL OR p.barcode='')",
        [$sid, $tid]
    )->fetchColumn();
    
    if ($count == 0) return;
    
    upsertInsight($tid, $sid, 'dq_no_barcode', [
        'category' => 'data_quality', 'grp' => 1, 'module' => 'products', 'urgency' => 'info',
        'plan_gate' => 'start', 'role_gate' => 'owner,manager',
        'title' => $count . ' артикула без баркод',
        'count' => $count
    ]);
}

/** #13 — Без доставчик */
function computeNoSupplier(int $tid, int $sid): void {
    $count = DB::run(
        "SELECT COUNT(*) FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0
         AND p.supplier_id IS NULL",
        [$sid, $tid]
    )->fetchColumn();
    
    if ($count == 0) return;
    
    upsertInsight($tid, $sid, 'dq_no_supplier', [
        'category' => 'data_quality', 'grp' => 1, 'module' => 'products', 'urgency' => 'info',
        'plan_gate' => 'start', 'role_gate' => 'owner,manager',
        'title' => $count . ' артикула без доставчик',
        'count' => $count
    ]);
}

/** #14 — Без категория */
function computeNoCategory(int $tid, int $sid): void {
    $count = DB::run(
        "SELECT COUNT(*) FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0
         AND p.category_id IS NULL",
        [$sid, $tid]
    )->fetchColumn();
    
    if ($count == 0) return;
    
    upsertInsight($tid, $sid, 'dq_no_category', [
        'category' => 'data_quality', 'grp' => 1, 'module' => 'products', 'urgency' => 'info',
        'plan_gate' => 'start', 'role_gate' => 'owner,manager',
        'title' => $count . ' артикула без категория',
        'count' => $count
    ]);
}

// ══════════════════════════════════════
// ГРУПА 2: ПАРИ И ЦЕНИ
// ══════════════════════════════════════

/** #15 — Продава се под себестойност */
function computeSellingAtLoss(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, p.code, p.cost_price, p.retail_price,
                p.retail_price - p.cost_price as loss_per_unit,
                i.quantity
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND p.cost_price > 0 AND p.retail_price > 0
         AND p.retail_price < p.cost_price AND i.quantity > 0
         ORDER BY (p.cost_price - p.retail_price) DESC
         LIMIT 20",
        [$sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    upsertInsight($tid, $sid, 'price_selling_at_loss', [
        'category' => 'price', 'grp' => 2, 'module' => 'home', 'urgency' => 'critical',
        'plan_gate' => 'pro', 'role_gate' => 'owner',
        'title' => count($rows) . ' артикула се продават ПОД себестойност!',
        'detail_text' => implode(', ', array_map(fn($r) => $r['name'] . ' (губиш ' . fmtMoney(abs($r['loss_per_unit']), $cur) . '/бр)', array_slice($rows, 0, 3))),
        'data' => ['items' => $rows], 'count' => count($rows)
    ]);
}

/** #16 — Марж под 15% */
function computeLowMargin(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, p.cost_price, p.retail_price,
                ROUND((p.retail_price - p.cost_price) / p.retail_price * 100, 1) as margin_pct
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND p.cost_price > 0 AND p.retail_price > 0
         AND p.retail_price >= p.cost_price
         AND ((p.retail_price - p.cost_price) / p.retail_price * 100) < 15
         AND i.quantity > 0
         ORDER BY margin_pct ASC
         LIMIT 15",
        [$sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    upsertInsight($tid, $sid, 'price_low_margin', [
        'category' => 'price', 'grp' => 2, 'module' => 'products', 'urgency' => 'warning',
        'plan_gate' => 'pro', 'role_gate' => 'owner',
        'title' => count($rows) . ' артикула с марж под 15%',
        'detail_text' => implode(', ', array_map(fn($r) => $r['name'] . ' (' . $r['margin_pct'] . '%)', array_slice($rows, 0, 3))),
        'data' => ['items' => $rows], 'count' => count($rows)
    ]);
}

/** #17 — Wholesale твърде близо до retail (<10% разлика) */
function computeWholesaleCloseToRetail(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, p.wholesale_price, p.retail_price,
                ROUND((p.retail_price - p.wholesale_price) / p.retail_price * 100, 1) as diff_pct
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 
         AND p.wholesale_price > 0 AND p.retail_price > 0
         AND p.wholesale_price < p.retail_price
         AND ((p.retail_price - p.wholesale_price) / p.retail_price * 100) < 10
         AND i.quantity > 0
         LIMIT 15",
        [$sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    upsertInsight($tid, $sid, 'price_wholesale_close', [
        'category' => 'price', 'grp' => 2, 'module' => 'products', 'urgency' => 'warning',
        'plan_gate' => 'pro', 'role_gate' => 'owner',
        'title' => count($rows) . ' артикула: едро и дребно почти еднакви',
        'detail_text' => implode(', ', array_map(fn($r) => $r['name'] . ' (разлика ' . $r['diff_pct'] . '%)', array_slice($rows, 0, 3))),
        'data' => ['items' => $rows], 'count' => count($rows)
    ]);
}

/** #18 — Топ 5 най-печеливши (30д) */
function computeTopProfitable(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, 
                SUM(si.quantity * (si.unit_price - COALESCE(si.cost_price, p.cost_price))) as profit,
                SUM(si.total) as revenue
         FROM sale_items si
         JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
              AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         JOIN products p ON p.id=si.product_id AND p.tenant_id=?
         WHERE COALESCE(si.cost_price, p.cost_price) > 0
         GROUP BY p.id
         ORDER BY profit DESC
         LIMIT 5",
        [$sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    upsertInsight($tid, $sid, 'profit_top5', [
        'category' => 'profit', 'grp' => 2, 'module' => 'stats', 'urgency' => 'info',
        'plan_gate' => 'pro', 'role_gate' => 'owner',
        'title' => 'Топ печалба: ' . $rows[0]['name'] . ' — ' . fmtMoney($rows[0]['profit'], $cur) . ' за 30д',
        'data' => ['items' => $rows], 'value' => $rows[0]['profit'], 'count' => 5
    ]);
}

/** #19 — Дъно 5 най-нископечеливши */
function computeBottomProfitable(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name,
                SUM(si.quantity * (si.unit_price - COALESCE(si.cost_price, p.cost_price))) as profit,
                SUM(si.total) as revenue
         FROM sale_items si
         JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
              AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         JOIN products p ON p.id=si.product_id AND p.tenant_id=?
         WHERE COALESCE(si.cost_price, p.cost_price) > 0
         GROUP BY p.id
         HAVING profit > 0
         ORDER BY profit ASC
         LIMIT 5",
        [$sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    upsertInsight($tid, $sid, 'profit_bottom5', [
        'category' => 'profit', 'grp' => 2, 'module' => 'stats', 'urgency' => 'warning',
        'plan_gate' => 'pro', 'role_gate' => 'owner',
        'title' => 'Най-малко печалба: ' . $rows[0]['name'] . ' — само ' . fmtMoney($rows[0]['profit'], $cur) . ' за 30д',
        'data' => ['items' => $rows], 'value' => $rows[0]['profit'], 'count' => 5
    ]);
}

/** #20 — Замразен капитал в zombie стока */
function computeFrozenCapital(int $tid, int $sid, string $cur): void {
    $result = DB::run(
        "SELECT COUNT(*) as cnt,
                SUM(i.quantity * p.cost_price) as total_frozen
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         LEFT JOIN (
             SELECT si.product_id, MAX(s.created_at) as last_date
             FROM sale_items si JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
             GROUP BY si.product_id
         ) last_sale ON last_sale.product_id=p.id
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0 AND p.cost_price > 0
         AND DATEDIFF(NOW(), COALESCE(last_sale.last_date, p.created_at)) >= 30",
        [$sid, $sid, $tid]
    )->fetch();
    
    if (!$result || $result['cnt'] == 0 || $result['total_frozen'] <= 0) return;
    
    upsertInsight($tid, $sid, 'cash_frozen_zombie', [
        'category' => 'cash', 'grp' => 2, 'module' => 'home', 'urgency' => 'warning',
        'plan_gate' => 'pro', 'role_gate' => 'owner',
        'title' => fmtMoney($result['total_frozen'], $cur) . ' замразени в ' . $result['cnt'] . ' застояли артикула',
        'value' => $result['total_frozen'], 'count' => $result['cnt']
    ]);
}

// ══════════════════════════════════════
// ГРУПА 3: ПРОМОЦИИ
// ══════════════════════════════════════

/** #21 — Намаление изтича до 3 дни */
function computeDiscountExpiring(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, p.discount_pct, p.discount_ends_at, p.retail_price
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND p.discount_pct > 0
         AND p.discount_ends_at IS NOT NULL
         AND p.discount_ends_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
         AND i.quantity > 0
         ORDER BY p.discount_ends_at ASC",
        [$sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    upsertInsight($tid, $sid, 'promo_expiring', [
        'category' => 'promo_when', 'grp' => 3, 'module' => 'products', 'urgency' => 'warning',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => count($rows) . ' намаления изтичат до 3 дни',
        'detail_text' => implode(', ', array_map(fn($r) => $r['name'] . ' (-' . fmtQty($r['discount_pct']) . '%, до ' . $r['discount_ends_at'] . ')', array_slice($rows, 0, 3))),
        'data' => ['items' => $rows], 'count' => count($rows)
    ]);
}

/** #22 — Намаление на бестселър (ненужно) */
function computeDiscountOnBestseller(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, p.discount_pct, p.retail_price,
                SUM(si.quantity) as sold_30d
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         JOIN sale_items si ON si.product_id=p.id
         JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
              AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         WHERE p.tenant_id=? AND p.is_active=1 AND p.discount_pct > 0
         GROUP BY p.id
         HAVING sold_30d >= 10
         ORDER BY sold_30d DESC
         LIMIT 10",
        [$sid, $sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    upsertInsight($tid, $sid, 'promo_bestseller_discount', [
        'category' => 'promo_warning', 'grp' => 3, 'module' => 'products', 'urgency' => 'warning',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => count($rows) . ' бестселъра с намаление — продават се и без него',
        'detail_text' => implode(', ', array_map(fn($r) => $r['name'] . ' (' . $r['sold_30d'] . ' продажби, -' . fmtQty($r['discount_pct']) . '%)', array_slice($rows, 0, 3))),
        'data' => ['items' => $rows], 'count' => count($rows)
    ]);
}

/** #23 — Намалено под себестойност */
function computeDiscountBelowCost(int $tid, int $sid, string $cur): void {
    $rows = DB::run(
        "SELECT p.id, p.name, p.cost_price, p.retail_price, p.discount_pct,
                ROUND(p.retail_price * (1 - p.discount_pct/100), 2) as effective_price
         FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND p.discount_pct > 0 AND p.cost_price > 0
         AND (p.retail_price * (1 - p.discount_pct/100)) < p.cost_price
         AND i.quantity > 0
         ORDER BY (p.cost_price - (p.retail_price * (1 - p.discount_pct/100))) DESC
         LIMIT 15",
        [$sid, $tid]
    )->fetchAll();
    
    if (empty($rows)) return;
    
    upsertInsight($tid, $sid, 'promo_below_cost', [
        'category' => 'promo_warning', 'grp' => 3, 'module' => 'products', 'urgency' => 'critical',
        'plan_gate' => 'pro', 'role_gate' => 'owner',
        'title' => count($rows) . ' артикула: намалението ги вкарва ПОД себестойност!',
        'detail_text' => implode(', ', array_map(fn($r) => $r['name'] . ' (цена ' . fmtMoney($r['effective_price'], $cur) . ', доставна ' . fmtMoney($r['cost_price'], $cur) . ')', array_slice($rows, 0, 3))),
        'data' => ['items' => $rows], 'count' => count($rows)
    ]);
}

// ══════════════════════════════════════
// ГРУПА 5: ЗДРАВЕ НА БИЗНЕСА
// ══════════════════════════════════════

/** #24 — Оборот днес vs вчера */
function computeRevenueTodayVsYesterday(int $tid, int $sid, string $cur): void {
    $today = DB::run(
        "SELECT COALESCE(SUM(total), 0) as rev, COUNT(*) as cnt
         FROM sales WHERE store_id=? AND status='completed' AND DATE(created_at)=CURDATE()",
        [$sid]
    )->fetch();
    
    $yesterday = DB::run(
        "SELECT COALESCE(SUM(total), 0) as rev
         FROM sales WHERE store_id=? AND status='completed' AND DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
        [$sid]
    )->fetch();
    
    if ($today['cnt'] == 0 && $yesterday['rev'] == 0) return;
    
    $diff = ($yesterday['rev'] > 0) 
        ? round(($today['rev'] - $yesterday['rev']) / $yesterday['rev'] * 100) 
        : 0;
    $arrow = ($diff >= 0) ? '+' : '';
    
    upsertInsight($tid, $sid, 'biz_revenue_today', [
        'category' => 'biz_revenue', 'grp' => 5, 'module' => 'home', 'urgency' => 'info',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => 'Днес: ' . fmtMoney($today['rev'], $cur) . ' (' . $today['cnt'] . ' продажби)' . ($yesterday['rev'] > 0 ? ' ' . $arrow . $diff . '% vs вчера' : ''),
        'value' => $today['rev']
    ]);
}

/** #25 — Оборот тази седмица vs миналата */
function computeRevenueWeekVsWeek(int $tid, int $sid, string $cur): void {
    $thisWeek = DB::run(
        "SELECT COALESCE(SUM(total), 0) FROM sales 
         WHERE store_id=? AND status='completed' AND YEARWEEK(created_at, 1)=YEARWEEK(NOW(), 1)",
        [$sid]
    )->fetchColumn();
    
    $lastWeek = DB::run(
        "SELECT COALESCE(SUM(total), 0) FROM sales 
         WHERE store_id=? AND status='completed' AND YEARWEEK(created_at, 1)=YEARWEEK(DATE_SUB(NOW(), INTERVAL 1 WEEK), 1)",
        [$sid]
    )->fetchColumn();
    
    if ($thisWeek == 0 && $lastWeek == 0) return;
    
    $diff = ($lastWeek > 0) ? round(($thisWeek - $lastWeek) / $lastWeek * 100) : 0;
    $arrow = ($diff >= 0) ? '+' : '';
    
    upsertInsight($tid, $sid, 'biz_revenue_week', [
        'category' => 'biz_revenue', 'grp' => 5, 'module' => 'home', 'urgency' => 'info',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => 'Тази седмица: ' . fmtMoney($thisWeek, $cur) . ($lastWeek > 0 ? ' (' . $arrow . $diff . '% vs миналата)' : ''),
        'value' => $thisWeek
    ]);
}

/** #26 — Оборот този месец vs миналия */
function computeRevenueMonthVsMonth(int $tid, int $sid, string $cur): void {
    $thisMonth = DB::run(
        "SELECT COALESCE(SUM(total), 0) FROM sales 
         WHERE store_id=? AND status='completed'
         AND YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW())",
        [$sid]
    )->fetchColumn();
    
    $lastMonth = DB::run(
        "SELECT COALESCE(SUM(total), 0) FROM sales 
         WHERE store_id=? AND status='completed'
         AND YEAR(created_at)=YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH)) 
         AND MONTH(created_at)=MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))",
        [$sid]
    )->fetchColumn();
    
    if ($thisMonth == 0 && $lastMonth == 0) return;
    
    $diff = ($lastMonth > 0) ? round(($thisMonth - $lastMonth) / $lastMonth * 100) : 0;
    $arrow = ($diff >= 0) ? '+' : '';
    
    upsertInsight($tid, $sid, 'biz_revenue_month', [
        'category' => 'biz_revenue', 'grp' => 5, 'module' => 'stats', 'urgency' => 'info',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => 'Този месец: ' . fmtMoney($thisMonth, $cur) . ($lastMonth > 0 ? ' (' . $arrow . $diff . '% vs миналия)' : ''),
        'value' => $thisMonth
    ]);
}

/** #27 — Средна кошница тренд */
function computeBasketTrend(int $tid, int $sid, string $cur): void {
    $thisWeek = DB::run(
        "SELECT AVG(total) FROM sales 
         WHERE store_id=? AND status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        [$sid]
    )->fetchColumn();
    
    $lastWeek = DB::run(
        "SELECT AVG(total) FROM sales 
         WHERE store_id=? AND status='completed' 
         AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)",
        [$sid]
    )->fetchColumn();
    
    if (!$thisWeek) return;
    
    $diff = ($lastWeek > 0) ? round(($thisWeek - $lastWeek) / $lastWeek * 100) : 0;
    $arrow = ($diff >= 0) ? '+' : '';
    
    upsertInsight($tid, $sid, 'biz_basket_trend', [
        'category' => 'biz_revenue', 'grp' => 5, 'module' => 'stats', 'urgency' => 'info',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => 'Средна кошница: ' . fmtMoney($thisWeek, $cur) . ($lastWeek > 0 ? ' (' . $arrow . $diff . '% vs предишна седмица)' : ''),
        'value' => $thisWeek
    ]);
}

/** #28 — Топ категория по оборот 30д */
function computeTopCategoryRevenue(int $tid, int $sid, string $cur): void {
    $row = DB::run(
        "SELECT c.name as cat_name, SUM(si.total) as revenue
         FROM sale_items si
         JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
              AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         JOIN products p ON p.id=si.product_id AND p.tenant_id=?
         JOIN categories c ON c.id=p.category_id
         GROUP BY c.id
         ORDER BY revenue DESC
         LIMIT 1",
        [$sid, $tid]
    )->fetch();
    
    if (!$row) return;
    
    upsertInsight($tid, $sid, 'biz_top_category', [
        'category' => 'biz_revenue', 'grp' => 5, 'module' => 'stats', 'urgency' => 'info',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => 'Топ категория: ' . $row['cat_name'] . ' — ' . fmtMoney($row['revenue'], $cur) . ' за 30д',
        'value' => $row['revenue']
    ]);
}

/** #29 — Топ доставчик по оборот 30д */
function computeTopSupplierRevenue(int $tid, int $sid, string $cur): void {
    $row = DB::run(
        "SELECT sup.name as sup_name, SUM(si.total) as revenue
         FROM sale_items si
         JOIN sales s ON s.id=si.sale_id AND s.store_id=? AND s.status='completed'
              AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         JOIN products p ON p.id=si.product_id AND p.tenant_id=?
         JOIN suppliers sup ON sup.id=p.supplier_id
         GROUP BY sup.id
         ORDER BY revenue DESC
         LIMIT 1",
        [$sid, $tid]
    )->fetch();
    
    if (!$row) return;
    
    upsertInsight($tid, $sid, 'biz_top_supplier', [
        'category' => 'biz_revenue', 'grp' => 5, 'module' => 'stats', 'urgency' => 'info',
        'plan_gate' => 'pro', 'role_gate' => 'owner,manager',
        'title' => 'Топ доставчик: ' . $row['sup_name'] . ' — ' . fmtMoney($row['revenue'], $cur) . ' за 30д',
        'value' => $row['revenue']
    ]);
}

// ══════════════════════════════════════
// ГРУПА 6: ОПЕРАЦИИ
// ══════════════════════════════════════

/** #30 — Артикули непреброени 30+ дни */
function computeUncounted30d(int $tid, int $sid): void {
    $count = DB::run(
        "SELECT COUNT(*) FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0
         AND (p.last_counted_at IS NULL OR p.last_counted_at < DATE_SUB(NOW(), INTERVAL 30 DAY))",
        [$sid, $tid]
    )->fetchColumn();
    
    if ($count == 0) return;
    
    upsertInsight($tid, $sid, 'ops_uncounted_30d', [
        'category' => 'data_quality', 'grp' => 6, 'module' => 'home', 'urgency' => 'info',
        'plan_gate' => 'start', 'role_gate' => 'owner,manager',
        'title' => $count . ' артикула непреброени 30+ дни — преброй за по-точен склад',
        'count' => $count
    ]);
}
