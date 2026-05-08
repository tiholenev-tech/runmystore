<?php
/**
 * ════════════════════════════════════════════════════════════════════
 * PATCH: 01 — sale-voice.php correlated subquery (S120.PERF_QUICKWINS)
 * ════════════════════════════════════════════════════════════════════
 *
 * Target file:        /var/www/runmystore/sale-voice.php
 * Target lines:       16-26 (single SELECT)
 * Source audit:       /tmp/perf_audit/01_static_findings.md §A3
 * Recommended:        /tmp/perf_audit/04_recommended_fixes.md F1
 * Severity:           P0 — user-facing voice command path
 * Estimated p95:      300-1500 ms BEFORE → 20-80 ms AFTER (~10-50× faster)
 * Risk:               LOW (read-only path, idempotent, easy A/B)
 * Effort:             ~20 minutes including smoke test
 *
 * ────────────────────────────────────────────────────────────────────
 * THIS IS A PATCH PROPOSAL FILE — NOT A LIVE FILE.
 * Apply manually after Тихол approves. NO git ops here.
 * ────────────────────────────────────────────────────────────────────
 */

/* ════════════════════════════════════════════════════════════════════
 * BEFORE (sale-voice.php:16-26 as of 2026-05-08)
 * ════════════════════════════════════════════════════════════════════ */

/*
$stmt = $pdo->prepare("
    SELECT p.id, p.code, p.name, p.retail_price, p.wholesale_price,
           COALESCE(i.quantity, 0) as stock
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
    WHERE p.tenant_id = ? AND p.is_active = 1
    ORDER BY (SELECT COUNT(*) FROM stock_movements sm WHERE sm.product_id = p.id AND sm.type = 'out') DESC
    LIMIT 100
");
$stmt->execute([$store_id, $tenant_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
*/

/* PROBLEMS:
 *   1. Correlated subquery in ORDER BY → executed for EVERY row in WHERE result
 *      before LIMIT can short-circuit. On a 5k-SKU tenant: 5,000 subquery
 *      executions per voice command.
 *   2. Subquery missing `tenant_id` scope — relies on product_id uniqueness
 *      across tenants for correctness. Also blocks query planner from pushing
 *      tenant filter into the subquery.
 *   3. Filesort guaranteed (subquery output cannot use index for ORDER BY).
 */


/* ════════════════════════════════════════════════════════════════════
 * AFTER (proposed)
 * ════════════════════════════════════════════════════════════════════ */

$stmt = $pdo->prepare("
    SELECT p.id, p.code, p.name, p.retail_price, p.wholesale_price,
           COALESCE(i.quantity, 0) AS stock,
           COALESCE(sm_agg.sold_count, 0) AS sold_count
      FROM products p
      LEFT JOIN inventory i
             ON i.product_id = p.id AND i.store_id = ?
      LEFT JOIN (
            SELECT product_id, COUNT(*) AS sold_count
              FROM stock_movements
             WHERE tenant_id = ? AND type = 'out'
             GROUP BY product_id
      ) sm_agg ON sm_agg.product_id = p.id
     WHERE p.tenant_id = ? AND p.is_active = 1
     ORDER BY sm_agg.sold_count DESC
     LIMIT 100
");
$stmt->execute([$store_id, $tenant_id, $tenant_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* WHY THIS IS FASTER:
 *   - Derived table sm_agg is materialized ONCE (single GROUP BY scan
 *     of stock_movements with tenant_id index seek).
 *   - Outer SELECT is a simple LEFT JOIN with an indexed key — the
 *     optimizer can use idx_sm_tenant_product_type (see INDEXES.sql).
 *   - ORDER BY operates on a regular column (sm_agg.sold_count), not
 *     a per-row scalar — sortable with index or limited filesort buffer.
 *
 * SECURITY UPGRADE:
 *   - Subquery now scoped to tenant_id (defense vs cross-tenant leakage
 *     in case of product_id collision).
 *   - Three params instead of two: [store_id, tenant_id, tenant_id].
 */


/* ════════════════════════════════════════════════════════════════════
 * BENCHMARK ESTIMATE
 * ════════════════════════════════════════════════════════════════════
 *
 * Tenant size assumptions:
 *   - 5,000 active products
 *   - 200,000 stock_movements rows (40 movements/product avg)
 *   - 1 store
 *
 * BEFORE (correlated subquery in ORDER BY):
 *   - Outer scan:         5,000 rows
 *   - Subquery executions: 5,000 (one per product)
 *   - Each subquery:       table scan ~200k rows OR index scan if
 *                          (product_id, type) index exists
 *   - Total IO with index:   5,000 × 50ms (index range scans) = 250s worst case
 *                            5,000 × 0.1ms (with proper index) = 500ms
 *   - Without index:         several seconds, query timeout possible
 *   - Filesort + temp table: yes
 *   - Realistic p95:         300-1500 ms
 *
 * AFTER (derived table):
 *   - sm_agg materialization:   1× GROUP BY scan, ~200k rows
 *                               with idx_sm_tenant_product_type:
 *                               index range scan + group-by hash → 20-50ms
 *   - Outer LEFT JOIN:          5,000 rows × 1 hash lookup = ~5ms
 *   - Filesort on sm_agg.sold_count: yes, but on derived table (small)
 *   - Realistic p95:            20-80 ms
 *
 * Speedup: 10-50× depending on stock_movements size and index presence.
 *
 * REQUIRED INDEX (see INDEXES.sql):
 *   ALTER TABLE stock_movements
 *     ADD INDEX idx_sm_tenant_product_type (tenant_id, product_id, type);
 *
 * Without this index the AFTER query is still faster than BEFORE because
 * the GROUP BY runs once instead of N times — but the gain is much smaller.
 */


/* ════════════════════════════════════════════════════════════════════
 * SMOKE TEST PLAN
 * ════════════════════════════════════════════════════════════════════
 *
 *  1. Pick a Pro tenant with ≥500 SKUs (e.g. tenant_id=99 per audit's
 *     reference).
 *  2. Capture baseline:
 *       mysql --defaults-extra-file=/etc/runmystore/db.env runmystore -e "
 *         SET @tid := 99; SET @sid := (SELECT id FROM stores WHERE tenant_id=@tid LIMIT 1);
 *         SELECT BENCHMARK(1, (
 *           SELECT COUNT(*) FROM (
 *             $BEFORE_QUERY  -- inline the BEFORE SELECT here
 *           ) x
 *         ));"
 *  3. Apply patch (edit live sale-voice.php only after Тихол OK).
 *  4. Re-run BENCHMARK with the AFTER query.
 *  5. Functional check: load /sale.php on the tenant, click voice mic,
 *     speak "две тениски и един бански", confirm response includes the
 *     items with same id ordering as before (top sellers first).
 *
 * Equivalence note: the result SET is the same (top-100 most-sold
 * products), only the ORDER may differ for products with equal
 * sold_count (no tie-breaker → tie-broken by p.id under JOIN). Add
 * `ORDER BY sm_agg.sold_count DESC, p.id ASC` if deterministic order
 * across tenants is required.
 */


/* ════════════════════════════════════════════════════════════════════
 * ROLLBACK PLAN
 * ════════════════════════════════════════════════════════════════════
 *
 *  IF a regression appears (slower in production OR wrong items returned):
 *
 *  1. Revert sale-voice.php to backup:
 *       cp -p sale-voice.php.bak.S120 sale-voice.php
 *
 *  2. Or git revert the migration commit:
 *       git revert <S120.PERF.sale-voice commit hash>
 *       # NOTE: this is the user's prerogative; this patch file
 *       # contains NO git operations.
 *
 *  3. Index removal (if causing harm):
 *       ALTER TABLE stock_movements DROP INDEX idx_sm_tenant_product_type;
 *
 *  Backup procedure BEFORE applying:
 *       cp -p /var/www/runmystore/sale-voice.php \
 *             /var/www/runmystore/backups/s120_$(date +%Y%m%d-%H%M)/sale-voice.php.bak
 */


/* ════════════════════════════════════════════════════════════════════
 * RELATED FIXES IN THIS SESSION
 * ════════════════════════════════════════════════════════════════════
 *
 *  - 02_sale-search_keystroke_optimization.patch.php (same pattern, hot path)
 *  - INDEXES.sql                                     (idx_sm_tenant_product_type required)
 *
 * Both should be applied together for max effect.
 */
