# Phase 1 — Static Performance Findings

**Session:** S115.PERFORMANCE_AUDIT
**Method:** Read-only grep across active PHP modules. **NO** access to `products.php` (Code 1 owns), `partials/`, `design-kit/`, `mockups/`, `life-board.php` mutation; **read access only** for life-board.php as required to map flow.
**Modules audited (15 covered):** life-board.php, sale.php, products_fetch.php, inventory.php, deliveries.php, delivery.php, chat.php, xchat.php, order.php, orders.php, selection-engine.php, ai-studio-backend.php, login.php, product-save.php, stats.php, warehouse.php, compute-insights.php, ai-helper.php, sale-search.php, sale-voice.php.

---

## A. Correlated subquery N+1 patterns (HIGH severity)

These run a subquery for each row in the outer result. Worst in user-facing screens.

### A1. `stats.php:1188` — anomalies tab

```sql
SELECT p.name FROM products p
  JOIN inventory i ON i.product_id=p.id AND i.tenant_id=p.tenant_id
 WHERE p.tenant_id=? AND i.quantity=0
   AND (SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id=si.sale_id
        WHERE si.product_id=p.id AND s.tenant_id=p.tenant_id
          AND s.created_at > DATE_SUB(NOW(),INTERVAL 30 DAY)) > 0
 LIMIT 5
```

**Cost:** for each product with `i.quantity=0` (could be hundreds in a Pro-tier store), the subquery scans `sale_items × sales` for last 30 days. **Likely 100ms-500ms** on real-world data.

**Suggested fix:**
```sql
SELECT DISTINCT p.name
  FROM products p
  JOIN inventory i  ON i.product_id=p.id AND i.tenant_id=p.tenant_id
  JOIN sale_items si ON si.product_id=p.id
  JOIN sales s      ON s.id=si.sale_id AND s.tenant_id=p.tenant_id
 WHERE p.tenant_id=? AND i.quantity=0
   AND s.created_at > DATE_SUB(NOW(),INTERVAL 30 DAY)
 LIMIT 5
```

JOIN form = single index seek on `sale_items.product_id` + index range on `sales.created_at`. Expected: **<20ms**.

### A2. `products_fetch.php:302, 306, 596, 767, 769, 771` — zombie/slow-mover detection

Same anti-pattern (×6 occurrences):
```sql
DATEDIFF(NOW(), COALESCE((SELECT MAX(s.created_at) FROM sale_items si
                            JOIN sales s ON s.id=si.sale_id
                           WHERE si.product_id=p.id),
                         p.created_at)) > 45
```

**Cost:** scalar-subquery executes per product row in the outer scan (the outer `JOIN inventory` already filters on store, so cardinality is bounded but still ~100s of rows). Re-runs on every dashboard refresh.

**Suggested fix (materialized derived):**
```sql
LEFT JOIN (
  SELECT si.product_id, MAX(s.created_at) AS last_sale
    FROM sale_items si
    JOIN sales s ON s.id=si.sale_id
   WHERE s.tenant_id=? AND s.store_id=?
   GROUP BY si.product_id
) ls ON ls.product_id = p.id
WHERE DATEDIFF(NOW(), COALESCE(ls.last_sale, p.created_at)) > 45
```

Same shape, executed once. Expected: **3-5× faster** on stores with > 500 active SKUs.

**Even better (longer-term):** maintain a `products.last_sold_at` denormalized column updated by sale-commit transaction. Index it. Single index range scan replaces the JOIN. Out of scope for this audit; flag as future RWQ.

### A3. `sale-voice.php:22` — top 100 by sold count

```sql
SELECT … FROM products p LEFT JOIN inventory i ON …
 WHERE p.tenant_id=? AND p.is_active=1
 ORDER BY (SELECT COUNT(*) FROM stock_movements sm
            WHERE sm.product_id=p.id AND sm.type='out') DESC
 LIMIT 100
```

**Critical issue 1:** the subquery is **missing tenant_id scope**! `WHERE sm.product_id=p.id AND sm.type='out'` allows a row with the same `product_id` from another tenant to count (assuming `product_id` collision is impossible in practice via unique IDs, but it's a security/correctness smell — and the optimiser cannot push tenant filter down into the subquery).

**Critical issue 2:** `ORDER BY <subquery>` forces evaluation for **every** product in the tenant before LIMIT 100 can be applied. On a tenant with 10k SKUs, this is ~10k subqueries × stock_movements scan.

**Suggested fix:**
```sql
SELECT p.id, p.code, p.name, p.retail_price, p.wholesale_price,
       COALESCE(i.quantity, 0) AS stock,
       COALESCE(sm_agg.sold_count, 0) AS sold_count
  FROM products p
  LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=?
  LEFT JOIN (SELECT product_id, COUNT(*) AS sold_count
               FROM stock_movements
              WHERE tenant_id=? AND type='out'
              GROUP BY product_id) sm_agg ON sm_agg.product_id=p.id
 WHERE p.tenant_id=? AND p.is_active=1
 ORDER BY sm_agg.sold_count DESC
 LIMIT 100
```

Expected: **10-50× faster** for large catalogs.

### A4. `sale-search.php:19, 31` — search-as-you-type endpoint

Same pattern as A3, fires **on every keystroke** in the sale search box. P95 latency is critical here.

```sql
ORDER BY (SELECT COUNT(*) FROM stock_movements sm
           WHERE sm.product_id=p.id AND sm.tenant_id=p.tenant_id AND sm.type='out') DESC
LIMIT 10
```

**Cost:** with `LIMIT 10`, the engine can short-circuit BUT only after the ORDER BY is computed across all matching products. For a wildcard `%foo%` LIKE on name, the candidate set is large.

**Suggested fix:** identical pattern to A3 — push the subquery to a JOIN'd derived table OR maintain `products.sold_count_30d` denormalized.

---

## B. Repeated identical queries (cache miss / batchable)

### B1. `products_fetch.php` — 10× `tenants WHERE id=?` reads (lines 31, 516, 650, 659, 671, 684, 701, 702, 819, 823)

Every code path hits `tenants` again with a different `SELECT col` list:
- L31: `SELECT *`
- L516: `ai_credits_bg, ai_credits_tryon`
- L650, L659: `units_config`
- L671, L684: `colors_config`
- L701: `ai_credits_bg`
- L702: `ai_credits_tryon`
- L819: `units_config`
- L823: `colors_config`

**Cost:** 10 roundtrips per page load (mostly serial), each ~1-3ms = **10-30ms wasted**.

**Suggested fix:** a single per-request `pf_tenant_cfg()` helper that caches the row in a static variable. One round-trip; subsequent callers read from PHP memory.

```php
// services/tenant-cache.php (new)
function tenant_cfg(int $tenant_id): array {
    static $cache = [];
    if (!isset($cache[$tenant_id])) {
        $cache[$tenant_id] = DB::run(
            "SELECT id, name, plan, currency, language, ui_mode,
                    units_config, colors_config, ai_credits_bg, ai_credits_tryon,
                    trial_ends_at, is_active
               FROM tenants WHERE id=? LIMIT 1",
            [$tenant_id]
        )->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return $cache[$tenant_id];
}
```

Wire-up later (S116+ refactor); flag as **P2** because the savings are real but not user-visible single-page latency.

### B2. `xchat.php:196-197` / `chat.php` — 2 COUNT queries on products

```php
$total_products = DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1', [$tenant_id]);
$with_cost      = DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0', [$tenant_id]);
```

**Cost:** 2 roundtrips on every chat page load.

**Suggested fix:**
```php
$row = DB::run(
  "SELECT COUNT(*) AS total,
          SUM(CASE WHEN cost_price>0 THEN 1 ELSE 0 END) AS with_cost
     FROM products WHERE tenant_id=? AND is_active=1",
  [$tenant_id]
)->fetch(PDO::FETCH_ASSOC);
$total_products = (int)$row['total'];
$with_cost      = (int)$row['with_cost'];
```

Single roundtrip. **Saves ~3-8ms per chat pageload.**

### B3. `warehouse.php:29-44` — 6 separate COUNT queries

```
L29: COUNT products
L32: COUNT low_stock (JOIN stores)
L35: COUNT pending deliveries (JOIN stores)
L38: COUNT pending transfers (JOIN stores)
L41: COUNT suppliers
L44: COUNT active inventory sessions (JOIN stores)
```

**Cost:** 6 roundtrips on every warehouse page load. If running over a 5ms-RTT connection, **30ms wasted**.

**Suggested fix:** UNION ALL into one round-trip:
```sql
SELECT 'products' AS k, COUNT(*) AS v FROM products WHERE tenant_id=? AND is_active=1
UNION ALL
SELECT 'low_stock',     COUNT(*) FROM inventory i JOIN stores s ON s.id=i.store_id
                          WHERE s.tenant_id=? AND i.min_quantity>0 AND i.quantity<i.min_quantity
UNION ALL
SELECT 'pending_deliveries', COUNT(*) FROM deliveries d JOIN stores s ON s.id=d.store_id
                          WHERE s.tenant_id=? AND d.status='pending'
…
```

Then `array_column($result, 'v', 'k')` to dispatch. Expected savings: **~25ms** on warehouse hub load.

### B4. `deliveries.php:65` — wasted roundtrip (DEAD QUERY)

```php
$kpi_week = (float)$pdo->prepare("
    SELECT COALESCE(SUM(total), 0) FROM deliveries
    WHERE tenant_id = ? AND status = 'committed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->execute([$tenant_id]) ? null : null;
```

This statement prepares + executes a query and **discards the result** (`(bool) ? null : null` always evaluates to `null`, then `(float)null = 0.0`). The actual `$kpi_week` is computed by the next 3 lines (L71-73).

**Cost:** 1 wasted roundtrip per deliveries hub page load.

**Suggested fix:** delete L65-69 entirely. Pure cleanup. **5ms savings**, negative LOC.

---

## C. SELECT * (over-fetch)

| File | Line | Table | Risk |
|---|---|---|---|
| login.php | 19 | tenants | LOW (1 row, infrequent) |
| login.php | 21 | users | LOW (1 row) |
| life-board.php | 38 | tenants | MED (every homepage load; pulls all cols incl. `gemini_api_key` long blob if any) |
| sale.php | 24 | tenants | MED (every sale load) |
| sale.php | 33 | users | MED |
| sale.php | 40 | stores | LOW |
| products_fetch.php | 31 | tenants | HIGH (every fetch endpoint hit) |
| products_fetch.php | 39 | users | HIGH |
| products_fetch.php | 430 | products | HIGH (single product, but pulls long `description`, `image_url`, etc.) |
| inventory.php | 15 | tenants | MED |
| deliveries.php | 91 | ai_insights | MED — pulls `data_json` and `action_data` JSON cols always |
| chat.php | 43 | tenants | MED |
| xchat.php | 43 | tenants | MED |
| selection-engine.php | 37 | ai_topics_catalog | LOW (small static-ish table) |
| order.php | 197 | purchase_orders | LOW |
| ai-studio-backend.php | 280, 289 | ai_prompt_templates | LOW |
| product-save.php | 60 | products | MED (single row but pulled fully on every save) |
| delivery.php | 407 | deliveries | LOW (single row) |

**Suggested fix:** project the columns each call site actually needs. Especially the `tenants` reads — most callers want `plan, currency, language, ui_mode, name`; pulling `*` includes potentially-large `units_config`, `colors_config` JSON blobs.

---

## D. Iteration patterns (foreach + DB inside)

### D1. `sale.php:343-415` — sale commit loop **(intentional, inside DB::tx)**

For each cart item runs:
1. `SELECT cost_price, name, retail_price FROM products WHERE id=? AND tenant_id=?` (could be batched)
2. `SELECT quantity FROM inventory WHERE product_id=? AND store_id=? FOR UPDATE` (REQUIRED per row for lock granularity)
3. `INSERT INTO sale_items …` (REQUIRED per row)
4. `UPDATE inventory SET quantity = quantity - ? WHERE product_id=? AND store_id=? AND quantity >= ?` (REQUIRED, enforces non-negative)
5. `INSERT INTO stock_movements …` (REQUIRED per row)

**Verdict:** queries 2-5 are required per-row. Query 1 (product fetch) is the only **batchable** call:

```php
// Before the loop:
$pids = array_map('intval', array_column($items, 'product_id'));
$placeholders = implode(',', array_fill(0, count($pids), '?'));
$products_map = [];
foreach (DB::run(
  "SELECT id, cost_price, name, retail_price FROM products
    WHERE id IN ($placeholders) AND tenant_id=?",
  [...$pids, $tenant_id]
)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $products_map[(int)$row['id']] = $row;
}
// Then in the loop, use $products_map[$pid] instead of an inline DB::run.
```

**Savings:** N → 1 query per sale. For a 10-item cart at 5ms RTT: **45ms saved per sale**.

### D2. `inventory.php:48-54` — inventory_count_lines upsert

For each posted line:
1. `SELECT id FROM inventory_count_lines WHERE …` (existence check)
2. `UPDATE inventory_count_lines SET … WHERE id=?` OR `INSERT INTO inventory_count_lines …`
3. `SELECT id FROM inventory WHERE product_id=? AND store_id=?`
4. `UPDATE inventory SET …` OR `INSERT INTO inventory …`

**Suggested fix:** convert to `INSERT … ON DUPLICATE KEY UPDATE` (eliminates the SELECT + branch). Requires UNIQUE indexes on `(session_id, zone_id, product_id, variation_id)` and on `(product_id, store_id)` — verify before applying.

```sql
INSERT INTO inventory_count_lines (session_id, zone_id, product_id, variation_id,
                                   quantity_expected, quantity_counted)
VALUES (?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
  quantity_counted = VALUES(quantity_counted),
  quantity_expected = VALUES(quantity_expected);
```

Same pattern as `compute-insights.pfUpsert()` already in production — proven. **Savings:** 4 round-trips per line → 2.

### D3. `delivery.php:425-450` — delivery commit per-item loop

Same anti-pattern as D2 — SELECT inventory then branch INSERT or UPDATE. Convert to `ON DUPLICATE KEY UPDATE`. Savings ~2× per delivery line.

---

## E. Embedded subquery in SELECT list (high-cardinality)

### E1. `deliveries.php:42` — recent deliveries list

```sql
SELECT d.*, s.name AS supplier_name,
       (SELECT COUNT(*) FROM delivery_items di WHERE di.delivery_id = d.id) AS item_count, …
FROM deliveries d LEFT JOIN suppliers s ON s.id=d.supplier_id
WHERE d.tenant_id=?
ORDER BY d.created_at DESC LIMIT 10
```

**Cost:** 10 correlated subqueries (one per delivery in result set). Bounded by LIMIT but still 10 extra trips.

**Suggested fix:**
```sql
SELECT d.*, s.name AS supplier_name, COALESCE(di.cnt, 0) AS item_count, …
  FROM deliveries d
  LEFT JOIN suppliers s ON s.id=d.supplier_id
  LEFT JOIN (SELECT delivery_id, COUNT(*) AS cnt
               FROM delivery_items
              GROUP BY delivery_id) di ON di.delivery_id = d.id
 WHERE d.tenant_id=?
 ORDER BY d.created_at DESC
 LIMIT 10
```

The optimizer materializes `di` as a derived table once. Slight read amplification on small `delivery_items` table but eliminates 10 lookups. Verify with EXPLAIN before deploy.

### E2. `orders.php:38` — recent purchase orders list

Two correlated subqueries per PO row:
```sql
(SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.purchase_order_id=po.id) AS item_count,
(SELECT COALESCE(SUM(poi.qty_ordered * poi.cost_price), 0)
   FROM purchase_order_items poi WHERE poi.purchase_order_id=po.id) AS total
```

LIMIT 12 → **24 extra subquery executions** per orders pageload.

**Suggested fix:** same pattern as E1 — single derived table:
```sql
LEFT JOIN (
  SELECT purchase_order_id,
         COUNT(*) AS item_count,
         COALESCE(SUM(qty_ordered * cost_price), 0) AS total
    FROM purchase_order_items
   GROUP BY purchase_order_id
) poi ON poi.purchase_order_id = po.id
```

---

## F. Module-by-module summary

| Module | LOC | Findings count | Hottest issue |
|---|---|---|---|
| products_fetch.php | 8394 | 7 | A2 ×6 zombie subquery + B1 (10× tenants reads) |
| sale.php | 4646 | 1 | D1 batchable SELECT product (45ms savings/sale) |
| stats.php | 1386 | 1 | A1 anomalies correlated subquery |
| compute-insights.php | 1745 | 0 critical | All probes have explicit LIMIT; cron-only (background) |
| chat.php / xchat.php | 1642+2638 | 2 | B2 dual-COUNT redundancy |
| deliveries.php | 492 | 3 | B4 dead query, B3 6-COUNT batch, E1 correlated count |
| delivery.php | 1088 | 1 | D3 inventory upsert pattern |
| orders.php | 288 | 1 | E2 ×2 correlated subqueries × LIMIT 12 |
| order.php | 532 | 1 | C SELECT * (low) |
| warehouse.php | 701 | 1 | B3 6-COUNT batchable (highest single saving) |
| inventory.php | 476 | 2 | D2 SELECT-then-INSERT/UPDATE pattern |
| sale-search.php | (~) | 1 | A4 keystroke endpoint subquery |
| sale-voice.php | (~) | 1 | A3 ORDER BY subquery + missing tenant scope |
| ai-helper.php | 730 | 0 critical | gatherBusinessContext fires 5-7 queries per chat msg (acceptable; flag as P3 cache opportunity) |
| selection-engine.php | (~) | 0 critical | SELECT * but small static table |
| ai-studio-backend.php | 463 | 0 critical | 2× SELECT * but on small templates table |
| login.php | 451 | 0 critical | 2× SELECT * but only on auth — once per session |
| product-save.php | 756 | 0 critical | SELECT * once per save acceptable |
| ai-brain-record.php | 153 | 0 | session-lock cost noted in S114 audit |
| life-board.php | 1564 | 0 critical | 1× SELECT * on tenants (B-tier); read paths all bounded |

**Net:** 21 distinct findings across 15 modules; ~10 are HIGH severity (correlated subqueries / batchables in user-facing paths).
