<?php
/**
 * ════════════════════════════════════════════════════════════════════
 * PATCH: 03 — stats.php anomalies subquery (S120.PERF_QUICKWINS)
 * ════════════════════════════════════════════════════════════════════
 *
 * Target file:        /var/www/runmystore/stats.php
 * Target line:        1173 (single SELECT inside the anomalies tab block)
 * Source audit:       /tmp/perf_audit/01_static_findings.md §A1
 * Recommended:        /tmp/perf_audit/04_recommended_fixes.md F14
 * Severity:           P1 — anomalies tab is opened on demand, not every page
 * Estimated p95:      50-200 ms BEFORE → < 20 ms AFTER (~5-10× faster)
 * Risk:               LOW (LIMIT 5 short-circuits both forms; result equivalent)
 * Effort:             ~15 minutes including verification
 */

/* ════════════════════════════════════════════════════════════════════
 * BEFORE (stats.php:1173 as of 2026-05-08)
 * ════════════════════════════════════════════════════════════════════ */

/*
$qa2 = $pdo->prepare(
    "SELECT p.name FROM products p
       JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
      WHERE p.tenant_id = ? AND i.quantity = 0
        AND (SELECT COUNT(*) FROM sale_items si
              JOIN sales s ON s.id = si.sale_id
              WHERE si.product_id = p.id
                AND s.tenant_id = p.tenant_id
                AND s.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) > 0
      LIMIT 5"
);
$qa2->execute([$tenant_id]);
*/

/* PROBLEMS:
 *   1. Correlated subquery in WHERE — for each `(p, i)` row with stock=0
 *      the sale_items × sales window subquery executes.
 *   2. Cardinality of products with zero stock can be large (several
 *      hundred for a 5k-SKU tenant).
 *   3. The `> 0` predicate forces the subquery to materialize a count,
 *      not just probe for existence — `EXISTS` is more idiomatic.
 *   4. LIMIT 5 short-circuits the OUTER scan, but the optimizer cannot
 *      always prune subquery executions efficiently.
 */


/* ════════════════════════════════════════════════════════════════════
 * AFTER — Variant A: JOIN form with DISTINCT (preferred per audit §A1)
 * ════════════════════════════════════════════════════════════════════ */

$qa2 = $pdo->prepare("
    SELECT DISTINCT p.name
      FROM products p
      JOIN inventory i  ON i.product_id = p.id AND i.tenant_id = p.tenant_id
      JOIN sale_items si ON si.product_id = p.id
      JOIN sales s       ON s.id = si.sale_id AND s.tenant_id = p.tenant_id
     WHERE p.tenant_id = ?
       AND i.quantity = 0
       AND s.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
     LIMIT 5
");
$qa2->execute([$tenant_id]);

/* WHY THIS IS FASTER:
 *   - Single execution plan. Optimizer can choose nested-loop with
 *     index seeks on (sale_items.product_id, sales.id, sales.created_at).
 *   - No correlated subquery; LIMIT 5 actually prunes the scan.
 *   - `s.tenant_id = p.tenant_id` permits the optimizer to push tenant
 *     filter into sales scan early.
 *
 * REQUIRED INDEXES (verify with EXPLAIN):
 *   - inventory(product_id, tenant_id, quantity) — likely already present
 *   - sale_items(product_id) — see INDEXES.sql §B
 *   - sales(tenant_id, created_at) OR sales(tenant_id, store_id, created_at, status)
 *     — see INDEXES.sql §C
 */


/* ════════════════════════════════════════════════════════════════════
 * AFTER — Variant B (alternative): EXISTS form
 * ════════════════════════════════════════════════════════════════════
 *
 * If JOIN-with-DISTINCT proves slow due to high sale_items × sales
 * cardinality (which is unlikely but possible on huge stores), fall
 * back to EXISTS form:
 *
 *  SELECT p.name FROM products p
 *    JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
 *   WHERE p.tenant_id = ? AND i.quantity = 0
 *     AND EXISTS (
 *           SELECT 1 FROM sale_items si
 *             JOIN sales s ON s.id = si.sale_id
 *            WHERE si.product_id = p.id
 *              AND s.tenant_id = p.tenant_id
 *              AND s.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
 *           LIMIT 1
 *   )
 *   LIMIT 5
 *
 * EXISTS allows the planner to short-circuit the subquery as soon as
 * the first matching row is found, vs the original COUNT(*) > 0 which
 * had to count all matching rows.
 *
 * Use Variant B if Variant A's EXPLAIN shows `Using temporary` on the
 * outer plan (DISTINCT cost prohibitive).
 */


/* ════════════════════════════════════════════════════════════════════
 * BENCHMARK ESTIMATE
 * ════════════════════════════════════════════════════════════════════
 *
 * Tenant: 5k products, 100 zero-stock products, 50k sale_items in 30d.
 *
 * BEFORE:
 *   - Outer scan with LIMIT 5 prefix:     ~50 rows scanned (depending
 *                                          on i.quantity=0 selectivity)
 *   - Subquery per row:                   joins 50k sale_items to
 *                                          ~30k sales, filtered to 30 days
 *                                          - per execution: 5-15 ms
 *   - Total:                              50 × 10 ms = 500 ms (worst case)
 *   - p95:                                100-200 ms typical
 *
 * AFTER (Variant A — DISTINCT JOIN):
 *   - Plan: hash join or nested loop with indexes
 *   - With idx_si_product_id + sales(created_at):
 *     scan 30k sales (30d window) → join sale_items → join inventory
 *   - LIMIT 5 prunes after DISTINCT collation
 *   - p95: 5-20 ms
 *
 * AFTER (Variant B — EXISTS):
 *   - Outer scan: same as BEFORE (50 rows)
 *   - EXISTS short-circuit: 1 hit per row, ~1 ms
 *   - Total: 50 × 1 ms = 50 ms worst case
 *   - p95: 20-40 ms
 *
 * Variant A faster on average; Variant B more robust on extreme
 * cardinality. Pick A first; fall back to B if regressions appear.
 */


/* ════════════════════════════════════════════════════════════════════
 * EQUIVALENCE CHECK
 * ════════════════════════════════════════════════════════════════════
 *
 * BEFORE returns: products with i.quantity=0 AND sold ≥ 1 time in
 * the last 30 days. Up to 5 names.
 *
 * AFTER (DISTINCT JOIN) returns: SAME — DISTINCT collapses duplicates
 * created by the JOIN to sale_items. The semantics are identical.
 *
 * Verification SQL (run on tenant_id=99 in low-traffic window):
 *   SELECT
 *     (SELECT COUNT(*) FROM (BEFORE_QUERY) x) AS before_cnt,
 *     (SELECT COUNT(*) FROM (AFTER_QUERY) y)  AS after_cnt;
 * Should print 5 = 5 (or both lower if fewer matching products exist).
 */


/* ════════════════════════════════════════════════════════════════════
 * SMOKE TEST PLAN
 * ════════════════════════════════════════════════════════════════════
 *
 *   1. Open /stats.php on a Pro tenant.
 *   2. Switch to "Аномалии" tab.
 *   3. Confirm "Топ артикул изчерпан" entries are populated and the
 *      product names match expectation (a known sold-out product
 *      should appear).
 *   4. Time the page load before/after — anomalies tab should render
 *      noticeably faster on a tenant with many zero-stock products.
 *   5. Check that products with zero stock but never sold do NOT appear
 *      (correctness — same as BEFORE).
 */


/* ════════════════════════════════════════════════════════════════════
 * ROLLBACK PLAN
 * ════════════════════════════════════════════════════════════════════
 *
 *   1. Backup before applying:
 *        cp -p /var/www/runmystore/stats.php \
 *              /var/www/runmystore/backups/s120_$(date +%Y%m%d-%H%M)/stats.php.bak
 *
 *   2. If wrong rows or slower:
 *        - First try Variant B (EXISTS).
 *        - If still wrong, revert from backup or git revert.
 *
 *   3. No index dropped on rollback (indexes are additive, drop only
 *      if they harm other queries — verify with `pt-index-usage` or
 *      `slow_query_log` before).
 */


/* ════════════════════════════════════════════════════════════════════
 * RELATED FIXES
 * ════════════════════════════════════════════════════════════════════
 *
 *  - INDEXES.sql §B (idx_si_product_id) is required for max speedup.
 *  - INDEXES.sql §C (idx_sales_tenant_store_status_created) helps but
 *    is not strictly required — the outer LIMIT short-circuits enough.
 *
 *  Patch order:
 *   1. Apply INDEXES.sql §B + §C (during low traffic).
 *   2. Apply this patch.
 *   3. Re-run EXPLAIN on the AFTER query — confirm `type: ref` on
 *      sale_items and sales joins, no `Using filesort`.
 */
