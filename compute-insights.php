<?php
/**
 * compute-insights.php
 * ====================
 * Генератор на редове в `ai_insights`.
 *
 * ПРИНЦИП: PHP смята → INSERT в ai_insights. AI САМО облича готовото число.
 *          (ЗАКОН №2: PHP смята, AI говори.)
 *
 * Всяка функция:
 *   - Приема $tenant_id (+ по избор $store_id)
 *   - Прави SQL (read-only агрегат)
 *   - Записва insight с fundamental_question (ЗАКОН S77: 6-те въпроса)
 *   - Idempotent: delete old rows with same topic_id → insert new
 *
 * Скелетон версия (S78). Реалните SQL тела се попълват в S79.
 *
 * USAGE (CLI):
 *   php /var/www/runmystore/compute-insights.php              # всички tenants
 *   php /var/www/runmystore/compute-insights.php 52           # само tenant 52
 *   php /var/www/runmystore/compute-insights.php 52 47        # tenant 52 / store 47
 *
 * CRON (S79+):
 *   * /15 * * * *  php /var/www/runmystore/compute-insights.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

// ─────────────────────────────────────────────────────────────
// 6-те фундаментални въпроса (ЗАКОН S77.1)
// ─────────────────────────────────────────────────────────────
const FQ_LOSS       = 'loss';         // 🔴 Какво губя?
const FQ_LOSS_CAUSE = 'loss_cause';   // 🟣 От какво губя?
const FQ_GAIN       = 'gain';         // 🟢 Какво печеля?
const FQ_GAIN_CAUSE = 'gain_cause';   // 🔷 От какво печеля?
const FQ_ORDER      = 'order';        // 🟡 Какво да поръчам?
const FQ_ANTI_ORDER = 'anti_order';   // ⚫ Какво да НЕ поръчам?

// Urgency matrix (FQ → default urgency) — може да се override per-function
const URGENCY_BY_FQ = [
    FQ_LOSS       => 'critical',
    FQ_LOSS_CAUSE => 'warning',
    FQ_GAIN       => 'opportunity',
    FQ_GAIN_CAUSE => 'info',
    FQ_ORDER      => 'warning',
    FQ_ANTI_ORDER => 'info',
];

// TTL (seconds) — кога insight-ите изтичат ако не се reprocess-нат
const INSIGHT_TTL = 3600; // 1 час; cron пуска на 15 мин и refresh-ва


// =============================================================
// CLASS ComputeInsights
// =============================================================
class ComputeInsights
{
    private int $tenant_id;
    private ?int $store_id;
    private int $inserted = 0;
    private int $skipped  = 0;
    private array $errors = [];

    public function __construct(int $tenant_id, ?int $store_id = null)
    {
        $this->tenant_id = $tenant_id;
        $this->store_id  = $store_id;
    }

    // ─────────────────────────────────────────────────────────
    // PUBLIC: Orchestrator
    // ─────────────────────────────────────────────────────────
    public function runForProducts(): array
    {
        $functions = [
            // LOSS (3)
            'zeroStockWithSales',
            'belowMinUrgent',
            'runningOutToday',
            // LOSS_CAUSE (4)
            'sellingAtLoss',
            'noCostPrice',
            'marginBelow15',
            'sellerDiscountKiller',
            // GAIN (2)
            'topProfit30d',
            'profitGrowth',
            // GAIN_CAUSE (5)
            'highestMargin',
            'trendingUp',
            'loyalCustomers',
            'basketDriver',
            'sizeLeader',
            // ORDER (2)
            'bestsellerLowStock',
            'lostDemandMatch',
            // ANTI_ORDER (3)
            'zombie45d',
            'decliningTrend',
            'highReturnRate',
        ];

        foreach ($functions as $fn) {
            try {
                $this->$fn();
            } catch (\Throwable $e) {
                $this->errors[] = "$fn: " . $e->getMessage();
            }
        }

        return [
            'tenant_id' => $this->tenant_id,
            'store_id'  => $this->store_id,
            'inserted'  => $this->inserted,
            'skipped'   => $this->skipped,
            'errors'    => $this->errors,
        ];
    }

    // =========================================================
    // 🔴 LOSS (Какво губя?) — 3 функции
    // =========================================================

    /**
     * ZERO STOCK WITH SALES
     * Артикули с qty=0 в inventory но имат продажби в последните 30д.
     * SQL skeleton:
     *   SELECT p.id, p.name, sold_30d.qty, sold_30d.profit
     *   FROM products p
     *   JOIN inventory i ON i.product_id=p.id AND i.store_id=?
     *   JOIN (SELECT si.product_id, SUM(si.quantity) qty,
     *                SUM(si.quantity*(si.unit_price-si.cost_price)) profit
     *         FROM sale_items si JOIN sales s ON s.id=si.sale_id
     *         WHERE s.tenant_id=? AND s.created_at>=NOW()-INTERVAL 30 DAY
     *               AND s.status='completed'
     *         GROUP BY si.product_id) sold_30d ON sold_30d.product_id=p.id
     *   WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity=0
     */
    private function zeroStockWithSales(): void
    {
        // TODO S79: implement SQL above; loop rows; per row:
        // $this->writeInsight('zero_stock_'.$product_id, 'products', FQ_LOSS, [...]);
        $this->skipped++;
    }

    /**
     * BELOW MIN URGENT
     * inventory.quantity <= products.min_quantity (и min_quantity > 0).
     * Критично ако sold_30d > 0.
     */
    private function belowMinUrgent(): void
    {
        // TODO S79: SELECT p.id, i.quantity, p.min_quantity, sold_30d ...
        $this->skipped++;
    }

    /**
     * RUNNING OUT TODAY
     * Прогноза: inventory.quantity / (sold_30d/30) < 1 → ще свърши днес.
     */
    private function runningOutToday(): void
    {
        // TODO S79: формула daily_run_rate = sold_30d/30;
        // days_left = quantity / daily_run_rate; WHERE days_left < 1
        $this->skipped++;
    }

    // =========================================================
    // 🟣 LOSS_CAUSE (От какво губя?) — 4 функции
    // =========================================================

    /**
     * SELLING AT LOSS
     * sale_items.unit_price < sale_items.cost_price (snapshot).
     * Идентифицира конкретни трансакции на загуба.
     */
    private function sellingAtLoss(): void
    {
        // TODO S79: SELECT si.product_id, SUM((si.cost_price-si.unit_price)*si.quantity) loss
        // FROM sale_items WHERE unit_price < cost_price GROUP BY product_id
        $this->skipped++;
    }

    /**
     * NO COST PRICE
     * products.cost_price IS NULL OR cost_price = 0.
     * Без cost_price profit не може да се сметне.
     */
    private function noCostPrice(): void
    {
        // TODO S79: COUNT(*) products with cost_price<=0 AND is_active=1
        $this->skipped++;
    }

    /**
     * MARGIN BELOW 15
     * (retail_price - cost_price) / retail_price < 0.15 за артикули със продажби.
     */
    private function marginBelow15(): void
    {
        // TODO S79: WHERE (retail_price-cost_price)/retail_price < 0.15
        //           AND sold_30d > 0
        $this->skipped++;
    }

    /**
     * SELLER DISCOTUN KILLER
     * Служители които дават >avg_discount_pct + 1 sigma. Identify top offenders.
     */
    private function sellerDiscountKiller(): void
    {
        // TODO S79: GROUP BY sales.user_id, AVG(sale_items.discount_pct) > threshold
        $this->skipped++;
    }

    // =========================================================
    // 🟢 GAIN (Какво печеля?) — 2 функции
    // =========================================================

    /**
     * TOP PROFIT 30D
     * Top 5 артикули по profit (quantity * (unit_price - cost_price)) last 30d.
     */
    private function topProfit30d(): void
    {
        // TODO S79: ORDER BY profit DESC LIMIT 5
        $this->skipped++;
    }

    /**
     * PROFIT GROWTH
     * Сравнение last 30d vs previous 30d — артикули с >20% growth.
     */
    private function profitGrowth(): void
    {
        // TODO S79: two subqueries (30d vs 30-60d) → diff%
        $this->skipped++;
    }

    // =========================================================
    // 🔷 GAIN_CAUSE (От какво печеля?) — 5 функции
    // =========================================================

    /**
     * HIGHEST MARGIN
     * Top artikuli by margin %, с поне 3 продажби за 30д (филтър срещу шум).
     */
    private function highestMargin(): void
    {
        // TODO S79: WHERE sold_30d >= 3 ORDER BY margin_pct DESC LIMIT 5
        $this->skipped++;
    }

    /**
     * TRENDING UP
     * Артикули с растяща продажба седмица-към-седмица последни 4 седмици.
     */
    private function trendingUp(): void
    {
        // TODO S79: 4 WEEKS of weekly aggregates; linear regression slope > 0
        $this->skipped++;
    }

    /**
     * LOYAL CUSTOMERS
     * Top клиенти по repeat purchases (customers.id JOIN sales).
     */
    private function loyalCustomers(): void
    {
        // TODO S79: COUNT(DISTINCT sale_id) per customer_id; threshold = 3+ покупки
        $this->skipped++;
    }

    /**
     * BASKET DRIVER
     * Артикули които най-често присъстват в многоартикулни sales (basket analysis).
     * Активира се САМО ако има >= 30 пълни дни sale_items данни (BIBLE rule).
     */
    private function basketDriver(): void
    {
        // TODO S79: first check: COUNT(DISTINCT DATE(created_at)) >= 30
        //           then: products that co-occur in sales with >=2 items
        $this->skipped++;
    }

    /**
     * SIZE LEADER
     * За артикули с вариации — кой размер е bestseller (variation_id GROUP BY).
     */
    private function sizeLeader(): void
    {
        // TODO S79: GROUP BY parent_id, variation_id; top size per parent
        $this->skipped++;
    }

    // =========================================================
    // 🟡 ORDER (Какво да поръчам?) — 2 функции
    // =========================================================

    /**
     * BESTSELLER LOW STOCK
     * sold_30d в top 20% AND quantity < sold_30d/3 (stock < 10 days).
     * action: отваря order_draft.
     */
    private function bestsellerLowStock(): void
    {
        // TODO S79: combine percentile + stock ratio filter;
        // action_type='order_draft', action_data={product_id, suggested_qty}
        $this->skipped++;
    }

    /**
     * LOST DEMAND MATCH
     * lost_demand.resolved=0 AND suggested_supplier_id IS NOT NULL.
     * Показва като "препоръчай поръчка за X".
     */
    private function lostDemandMatch(): void
    {
        // TODO S79: SELECT FROM lost_demand WHERE resolved=0
        //           AND suggested_supplier_id IS NOT NULL AND times >= 2
        $this->skipped++;
    }

    // =========================================================
    // ⚫ ANTI_ORDER (Какво да НЕ поръчам?) — 3 функции
    // =========================================================

    /**
     * ZOMBIE 45d
     * Артикул който НЕ е продаден за 45 дни и има quantity > 0.
     * Signal: "Не поръчвай още, предишното не се движи".
     */
    private function zombie45d(): void
    {
        // TODO S79: LEFT JOIN sale_items (last 45 days) IS NULL
        //           AND inventory.quantity > 0
        $this->skipped++;
    }

    /**
     * DECLINING TREND
     * sold_30d < sold_60d_90d * 0.5 (продажбите паднаха на половина).
     */
    private function decliningTrend(): void
    {
        // TODO S79: two windows compare, > 50% drop
        $this->skipped++;
    }

    /**
     * HIGH RETURN RATE
     * returns / sold > 10% (returns таблица — ако съществува; иначе sales с status='canceled')
     */
    private function highReturnRate(): void
    {
        // TODO S79: ratio of canceled sales per product; threshold 10%
        $this->skipped++;
    }

    // =========================================================
    // PRIVATE: writeInsight — unified insert/upsert
    // =========================================================

    /**
     * Записва 1 ред в ai_insights. Idempotent — DELETE стария same topic_id + INSERT нов.
     *
     * @param string $topic_id     Уникален ключ на insight-а (напр. "zero_stock_1234")
     * @param string $module       'products','home','warehouse',...
     * @param string $fq           FQ_LOSS / FQ_GAIN / ...
     * @param array  $data         Keys: pill_text, value_numeric, product_id?,
     *                             supplier_id?, action_label?, action_type?,
     *                             action_data?, detail?, urgency?
     */
    private function writeInsight(
        string $topic_id,
        string $module,
        string $fq,
        array  $data
    ): void {
        if (!isset(URGENCY_BY_FQ[$fq])) {
            $this->errors[] = "Unknown FQ: $fq ($topic_id)";
            return;
        }
        if (empty($data['pill_text'])) {
            $this->errors[] = "pill_text required ($topic_id)";
            return;
        }

        $urgency = $data['urgency'] ?? URGENCY_BY_FQ[$fq];
        $expires = date('Y-m-d H:i:s', time() + INSIGHT_TTL);

        // Idempotent: изтрий стар ред със същия (tenant_id, topic_id, module)
        DB::run(
            "DELETE FROM ai_insights
             WHERE tenant_id=? AND topic_id=? AND module=?
               AND (store_id IS NULL OR store_id=?)",
            [$this->tenant_id, $topic_id, $module, $this->store_id]
        );

        DB::run(
            "INSERT INTO ai_insights
                (tenant_id, store_id, topic_id, module, urgency, fundamental_question,
                 pill_text, detail_json, value_numeric, product_id, supplier_id,
                 action_label, action_type, action_data, is_active, computed_at, expires_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)",
            [
                $this->tenant_id,
                $this->store_id,
                $topic_id,
                $module,
                $urgency,
                $fq,
                (string)$data['pill_text'],
                isset($data['detail']) ? json_encode($data['detail'], JSON_UNESCAPED_UNICODE) : null,
                $data['value_numeric'] ?? null,
                $data['product_id']    ?? null,
                $data['supplier_id']   ?? null,
                $data['action_label']  ?? null,
                $data['action_type']   ?? 'chat',
                isset($data['action_data']) ? json_encode($data['action_data'], JSON_UNESCAPED_UNICODE) : null,
                $expires,
            ]
        );

        $this->inserted++;
    }
}


// =============================================================
// CLI RUNNER
// =============================================================
if (php_sapi_name() === 'cli') {
    $tenant_id = isset($argv[1]) ? (int)$argv[1] : 0;
    $store_id  = isset($argv[2]) ? (int)$argv[2] : null;

    if ($tenant_id > 0) {
        $runner = new ComputeInsights($tenant_id, $store_id);
        $result = $runner->runForProducts();
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }

    // Няма аргумент → обиколи всички активни tenants
    $tenants = DB::run("SELECT id FROM tenants WHERE 1=1")->fetchAll(PDO::FETCH_COLUMN);
    $totals = ['tenants' => 0, 'inserted' => 0, 'skipped' => 0, 'errors' => 0];

    foreach ($tenants as $tid) {
        $runner = new ComputeInsights((int)$tid, null);
        $r = $runner->runForProducts();
        $totals['tenants']++;
        $totals['inserted'] += $r['inserted'];
        $totals['skipped']  += $r['skipped'];
        $totals['errors']   += count($r['errors']);
    }

    echo "compute-insights done: " . json_encode($totals) . "\n";
    exit(0);
}
