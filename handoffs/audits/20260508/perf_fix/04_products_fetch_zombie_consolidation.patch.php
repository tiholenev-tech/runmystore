<?php
/**
 * ════════════════════════════════════════════════════════════════════
 * PATCH: 04 — products_fetch.php zombie subquery consolidation
 *               (S120.PERF_QUICKWINS)
 * ════════════════════════════════════════════════════════════════════
 *
 * Target file:        /var/www/runmystore/products_fetch.php
 * Target lines:       302, 306, 596, 767, 769, 771 (6 sites with the same
 *                                                    correlated subquery)
 * Source audit:       /tmp/perf_audit/01_static_findings.md §A2
 * Recommended:        /tmp/perf_audit/04_recommended_fixes.md F13
 * Severity:           P0 — fires on every dashboard load (home_stats),
 *                          AI assist, and signal pillbar generation
 * Estimated p95:      150-400 ms BEFORE → 30-80 ms AFTER (~3-5× faster)
 * Risk:               MEDIUM (touches 6 separate query sites; verify
 *                              EXPLAIN on each after applying)
 * Effort:             ~1 hour for all 6 sites + EXPLAIN verification
 *
 * ⚠ NOTE: The audit flagged this fix as MEDIUM-risk because the patch
 *   touches 6 query sites in a 14k-line file. Apply incrementally:
 *   start with site 1 (line 302 in home_stats), benchmark, then sites
 *   2-6 in subsequent commits.
 */

/* ════════════════════════════════════════════════════════════════════
 * THE SHARED CORRELATED SUBQUERY (anti-pattern, used 6× in this file)
 * ════════════════════════════════════════════════════════════════════
 *
 *  DATEDIFF(NOW(),
 *    COALESCE(
 *      (SELECT MAX(s.created_at) FROM sale_items si
 *         JOIN sales s ON s.id = si.sale_id
 *        WHERE si.product_id = p.id),
 *      p.created_at
 *    )
 *  ) AS days_stale  -- (or threshold filter "> 45")
 *
 * For each product in the outer scan, the engine probes sale_items +
 * sales for the most recent sale matching that product. With ~5k
 * products and ~50k sale_items, this is the dominant cost on the
 * "home_stats" endpoint.
 *
 * THE SHARED REPLACEMENT (derived table, computed ONCE):
 *
 *  LEFT JOIN (
 *      SELECT si.product_id, MAX(s.created_at) AS last_sale
 *        FROM sale_items si
 *        JOIN sales s ON s.id = si.sale_id
 *       WHERE s.tenant_id = ?
 *       GROUP BY si.product_id
 *  ) ls ON ls.product_id = p.id
 *
 * Then: `DATEDIFF(NOW(), COALESCE(ls.last_sale, p.created_at)) AS days_stale`.
 *
 * Some sites also scope by store_id — add `AND s.store_id = ?` inside
 * the derived table's WHERE for those.
 */


/* ════════════════════════════════════════════════════════════════════
 * SITE 1 — line 302 ($zombies inside ajax='home_stats')
 * ════════════════════════════════════════════════════════════════════ */

/* BEFORE: */

/*
$zombies = DB::run(
    "SELECT p.id, p.name, p.code, p.retail_price, p.image_url,
            COALESCE(i.quantity,0) AS qty,
            DATEDIFF(NOW(),
              COALESCE(
                (SELECT MAX(s.created_at) FROM sale_items si
                   JOIN sales s ON s.id=si.sale_id
                  WHERE si.product_id=p.id),
                p.created_at
              )
            ) AS days_stale
       FROM products p
       JOIN inventory i ON i.product_id=p.id AND i.store_id=?
      WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity>0 AND p.parent_id IS NULL
     HAVING days_stale>45
      ORDER BY days_stale DESC LIMIT 10",
    [$sid, $tenant_id]
)->fetchAll(PDO::FETCH_ASSOC);
*/

/* AFTER: */

$zombies = DB::run("
    SELECT p.id, p.name, p.code, p.retail_price, p.image_url,
           COALESCE(i.quantity, 0) AS qty,
           DATEDIFF(NOW(), COALESCE(ls.last_sale, p.created_at)) AS days_stale
      FROM products p
      JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
      LEFT JOIN (
            SELECT si.product_id, MAX(s.created_at) AS last_sale
              FROM sale_items si
              JOIN sales s ON s.id = si.sale_id
             WHERE s.tenant_id = ?
             GROUP BY si.product_id
      ) ls ON ls.product_id = p.id
     WHERE p.tenant_id = ?
       AND p.is_active = 1
       AND i.quantity > 0
       AND p.parent_id IS NULL
       AND DATEDIFF(NOW(), COALESCE(ls.last_sale, p.created_at)) > 45
     ORDER BY days_stale DESC
     LIMIT 10
", [$sid, $tenant_id, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

/* Note: HAVING moved to WHERE because days_stale is now computed from a
 * column (ls.last_sale) that's available pre-aggregation. WHERE is
 * usually cheaper than HAVING here. */


/* ════════════════════════════════════════════════════════════════════
 * SITE 2 — line 306 ($slow_movers, same ajax='home_stats')
 * ════════════════════════════════════════════════════════════════════ */

/* BEFORE: same shape as $zombies but `HAVING days_stale BETWEEN 25 AND 45` */

/* AFTER: */

$slow_movers = DB::run("
    SELECT p.id, p.name, p.code, p.retail_price, p.image_url,
           COALESCE(i.quantity, 0) AS qty,
           DATEDIFF(NOW(), COALESCE(ls.last_sale, p.created_at)) AS days_stale
      FROM products p
      JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
      LEFT JOIN (
            SELECT si.product_id, MAX(s.created_at) AS last_sale
              FROM sale_items si
              JOIN sales s ON s.id = si.sale_id
             WHERE s.tenant_id = ?
             GROUP BY si.product_id
      ) ls ON ls.product_id = p.id
     WHERE p.tenant_id = ?
       AND p.is_active = 1
       AND i.quantity > 0
       AND p.parent_id IS NULL
       AND DATEDIFF(NOW(), COALESCE(ls.last_sale, p.created_at)) BETWEEN 25 AND 45
     ORDER BY days_stale DESC
     LIMIT 10
", [$sid, $tenant_id, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

/* OPTIMIZATION OPPORTUNITY (defer to later session — out of scope for S120):
 * Sites 1+2 (zombies + slow_movers) compute the SAME ls derived table
 * twice in the same request. Could be fetched once into PHP and processed
 * client-side in PHP, OR use a CTE / temporary table:
 *
 *   WITH ls AS (...)
 *   SELECT * FROM products p ... JOIN ls ...
 *
 * MySQL 8 supports CTEs. Defer this consolidation to S121.
 */


/* ════════════════════════════════════════════════════════════════════
 * SITE 3 — line 596 ($zombie count for AI assist)
 * ════════════════════════════════════════════════════════════════════ */

/* BEFORE: */

/*
$zombie = DB::run(
    "SELECT COUNT(*) FROM products p
       JOIN inventory i ON i.product_id=p.id AND i.store_id=?
      WHERE p.tenant_id=? AND i.quantity>0
        AND DATEDIFF(NOW(),
              COALESCE(
                (SELECT MAX(s.created_at) FROM sale_items si
                   JOIN sales s ON s.id=si.sale_id
                  WHERE si.product_id=p.id),
                p.created_at
              )
            ) > 45",
    [$store_id, $tenant_id]
)->fetchColumn();
*/

/* AFTER: */

$zombie = DB::run("
    SELECT COUNT(*)
      FROM products p
      JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
      LEFT JOIN (
            SELECT si.product_id, MAX(s.created_at) AS last_sale
              FROM sale_items si
              JOIN sales s ON s.id = si.sale_id
             WHERE s.tenant_id = ?
             GROUP BY si.product_id
      ) ls ON ls.product_id = p.id
     WHERE p.tenant_id = ?
       AND i.quantity > 0
       AND DATEDIFF(NOW(), COALESCE(ls.last_sale, p.created_at)) > 45
", [$store_id, $tenant_id, $tenant_id])->fetchColumn();


/* ════════════════════════════════════════════════════════════════════
 * SITE 4 — line 767 ($z = zombie agg with cnt + val)
 * ════════════════════════════════════════════════════════════════════ */

/* BEFORE: same correlated subquery but inner WHERE also has `s.store_id=?` */

/* AFTER: */

$z = DB::run("
    SELECT COUNT(DISTINCT p.id) AS cnt,
           COALESCE(SUM(i.quantity * p.retail_price), 0) AS val
      FROM products p
      JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
      LEFT JOIN (
            SELECT si.product_id, MAX(s.created_at) AS last_sale
              FROM sale_items si
              JOIN sales s ON s.id = si.sale_id
             WHERE s.tenant_id = ? AND s.store_id = ?
             GROUP BY si.product_id
      ) ls ON ls.product_id = p.id
     WHERE p.tenant_id = ?
       AND p.is_active = 1
       AND p.parent_id IS NULL
       AND i.quantity > 0
       AND DATEDIFF(NOW(), COALESCE(ls.last_sale, p.created_at)) > 45
", [$sid, $tenant_id, $sid, $tenant_id])->fetch(PDO::FETCH_ASSOC);


/* ════════════════════════════════════════════════════════════════════
 * SITE 5 — line 769 ($v = aging 90+ days)
 * ════════════════════════════════════════════════════════════════════ */

/* AFTER: */

$v = DB::run("
    SELECT COUNT(DISTINCT p.id)
      FROM products p
      JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
      LEFT JOIN (
            SELECT si.product_id, MAX(s.created_at) AS last_sale
              FROM sale_items si
              JOIN sales s ON s.id = si.sale_id
             WHERE s.tenant_id = ? AND s.store_id = ?
             GROUP BY si.product_id
      ) ls ON ls.product_id = p.id
     WHERE p.tenant_id = ?
       AND p.is_active = 1
       AND p.parent_id IS NULL
       AND i.quantity > 0
       AND DATEDIFF(NOW(), COALESCE(ls.last_sale, p.created_at)) > 90
", [$sid, $tenant_id, $sid, $tenant_id])->fetchColumn();


/* ════════════════════════════════════════════════════════════════════
 * SITE 6 — line 771 ($v = slow movers 25-45 days)
 * ════════════════════════════════════════════════════════════════════ */

/* AFTER: */

$v = DB::run("
    SELECT COUNT(DISTINCT p.id)
      FROM products p
      JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
      LEFT JOIN (
            SELECT si.product_id, MAX(s.created_at) AS last_sale
              FROM sale_items si
              JOIN sales s ON s.id = si.sale_id
             WHERE s.tenant_id = ? AND s.store_id = ?
             GROUP BY si.product_id
      ) ls ON ls.product_id = p.id
     WHERE p.tenant_id = ?
       AND p.is_active = 1
       AND p.parent_id IS NULL
       AND i.quantity > 0
       AND DATEDIFF(NOW(), COALESCE(ls.last_sale, p.created_at)) BETWEEN 25 AND 45
", [$sid, $tenant_id, $sid, $tenant_id])->fetchColumn();


/* ════════════════════════════════════════════════════════════════════
 * BENCHMARK ESTIMATE (cumulative across 6 sites)
 * ════════════════════════════════════════════════════════════════════
 *
 * Tenant: 5k products, 50k sale_items, 30k sales rows.
 *
 * BEFORE (per home_stats endpoint hit):
 *   - 6 separate queries, each correlated subquery:
 *       Site 1: zombies list (HAVING-filter, 10 rows out)        ~80 ms
 *       Site 2: slow_movers list (similar)                       ~80 ms
 *       Site 3: zombie count (AI assist)                         ~50 ms
 *       Site 4: zombie agg with cnt+val (signal pill)            ~80 ms
 *       Site 5: aging 90+ count (signal pill)                    ~50 ms
 *       Site 6: slow_mover 25-45 count (signal pill)             ~50 ms
 *     Total: ~390 ms p95 worst case (without indexes)
 *     With idx_si_product_id: ~150-200 ms
 *
 * AFTER:
 *   - Each query: derived table materialized once per call (~30-50 ms),
 *     then trivial outer scan + LIMIT.
 *   - Total: ~30-80 ms per call × 6 calls = ~200-300 ms worst case.
 *   - BUT: 5 of 6 sites are aggregate counts (no LIMIT), where the
 *     derived table is the dominant cost — speedup is ~3-5×.
 *
 * Realistic uplift: home_stats endpoint p95 from ~200ms → ~80ms.
 *
 * ⚠ ADVANCED OPTIMIZATION (defer to S121):
 *   Sites 1+2 share the same store_id — could compute the derived
 *   table ONCE in PHP and reuse for both. Would halve their cost.
 *
 *   Sites 4+5+6 share both tenant_id and store_id — same reuse opportunity.
 *
 *   Best long-term: maintain a `products.last_sold_at` denormalized
 *   column updated by sale-commit DB::tx hook. Single index range scan
 *   replaces all 6 derived tables. See audit recommended fix F13 §
 *   "Even better future fix".
 */


/* ════════════════════════════════════════════════════════════════════
 * REQUIRED INDEXES (see INDEXES.sql)
 * ════════════════════════════════════════════════════════════════════
 *
 *  §B: sale_items(product_id) — for the GROUP BY scan in derived table
 *  §C: sales(tenant_id, store_id, created_at, status) — for the JOIN
 *      and tenant scope inside derived table
 *
 * Without these, the AFTER plan may be SLOWER than BEFORE because
 * MySQL falls back to full sale_items scan. APPLY INDEXES FIRST.
 */


/* ════════════════════════════════════════════════════════════════════
 * EQUIVALENCE CHECK
 * ════════════════════════════════════════════════════════════════════
 *
 * For each site, the BEFORE and AFTER queries return identical counts /
 * row sets when:
 *   1. The same `(tenant_id, store_id)` is passed.
 *   2. No products have the same id across tenants (FK constraint).
 *   3. NULL / non-existent last_sale handled identically by COALESCE.
 *
 * Verification SQL (per site):
 *   -- Site 1
 *   SELECT COUNT(*) FROM (
 *       BEFORE_QUERY  -- as-is
 *   ) b;
 *   SELECT COUNT(*) FROM (
 *       AFTER_QUERY   -- as-is
 *   ) a;
 *   -- Should be equal.
 *
 * Run this for each of the 6 sites against tenant_id=99 in low traffic.
 */


/* ════════════════════════════════════════════════════════════════════
 * ROLLBACK PLAN
 * ════════════════════════════════════════════════════════════════════
 *
 *   1. Backup before applying:
 *        cp -p /var/www/runmystore/products_fetch.php \
 *              /var/www/runmystore/backups/s120_$(date +%Y%m%d-%H%M)/products_fetch.php.bak
 *
 *   2. Apply incrementally (recommended order):
 *        a) Apply indexes from INDEXES.sql (during low traffic).
 *        b) Patch site 1 (zombies list, line 302) — biggest user-visible win.
 *        c) Verify on staging or production with EXPLAIN; smoke test
 *           home_stats endpoint.
 *        d) Patch sites 2-6 in subsequent commits if 1 looks good.
 *
 *   3. If site 1 regresses → revert from backup, investigate EXPLAIN.
 *
 *   4. If specific site (e.g. site 4) regresses on a particular tenant:
 *      keep the BEFORE form for that site only (document why in the
 *      commit message).
 *
 *   5. ⚠ DO NOT drop indexes on rollback — they benefit other queries.
 */


/* ════════════════════════════════════════════════════════════════════
 * APPLY ORDER (in conjunction with patches 01, 02, 03)
 * ════════════════════════════════════════════════════════════════════
 *
 *   1. INDEXES.sql §A (idx_sm_tenant_product_type) — for patches 01 & 02
 *   2. INDEXES.sql §B (idx_si_product_id)          — for THIS patch & patch 03
 *   3. INDEXES.sql §C (idx_sales_*)                — for THIS patch & patch 03
 *
 *   Then patches in this order (lowest risk first):
 *     1. patch 01 sale-voice                        (single query, easy)
 *     2. patch 02 sale-search                       (two queries, hot path)
 *     3. patch 03 stats anomalies                   (single query, on-demand)
 *     4. patch 04 products_fetch site 1 only        (THIS PATCH, partial)
 *     5. patch 04 products_fetch sites 2-6          (full migration)
 */
