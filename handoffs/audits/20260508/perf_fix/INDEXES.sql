-- ════════════════════════════════════════════════════════════════════
-- INDEXES.sql — S120.PERF_QUICKWINS
-- ════════════════════════════════════════════════════════════════════
-- Source: /tmp/perf_audit/02_live_query_explain.md §5
-- Source: /tmp/perf_audit/04_recommended_fixes.md F4
-- Generated: 2026-05-08
--
-- ⚠ DO NOT APPLY BLINDLY. For each index below:
--   1. Run the corresponding SHOW INDEX FROM <table>; first
--      to confirm the index doesn't already exist under another name.
--   2. Run EXPLAIN on the proposed AFTER queries (in patches 01-04)
--      to confirm the optimizer will USE the index.
--   3. Apply during low-traffic window. Online DDL is supported by
--      MySQL 8 InnoDB but still locks metadata briefly + may pause
--      replication if multi-master.
--
-- Apply ORDER (lowest cost first):
--   §A → §B → §C → verify each → apply patches 01-04
-- ════════════════════════════════════════════════════════════════════

-- ════════════════════════════════════════════════════════════════════
-- §A — stock_movements(tenant_id, product_id, type)
-- ════════════════════════════════════════════════════════════════════
--
-- Required by:
--   - patch 01 sale-voice.php (sm_agg derived table)
--   - patch 02 sale-search.php (sm_agg derived table, both branches)
--
-- Without this index:
--   The derived table  SELECT product_id, COUNT(*)
--                        FROM stock_movements
--                       WHERE tenant_id=? AND type='out'
--                       GROUP BY product_id
--   falls back to full table scan (~200k rows for a Pro tenant).
--   The AFTER patches still produce correct results but only mildly
--   faster than BEFORE.
--
-- With this index:
--   The optimizer can use Index Range Scan + Loose Index Scan for
--   GROUP BY, materializing the aggregate in O(unique product_ids).
--
-- Verification BEFORE applying:
SHOW INDEX FROM stock_movements;
-- Look for an existing composite covering (tenant_id, product_id, type)
-- in any order. If any of these exists, this CREATE may be redundant:
--   - (tenant_id, product_id, type)
--   - (tenant_id, product_id) + (type) separately covers most plans
--   - Some shops have (tenant_id, type, product_id, created_at)

-- ALTER (apply only if confirmed missing):
ALTER TABLE stock_movements
    ADD INDEX idx_sm_tenant_product_type (tenant_id, product_id, type);

-- Estimated impact:
--   - Patch 01 p95: 300-1500 ms → 20-80 ms  (10-50× faster)
--   - Patch 02 p95: 80-300 ms → 8-30 ms     (~10× faster)
--   - Index size: ~12 bytes/row × N rows.
--     For 200k rows → ~2.4 MB index. Negligible.
--
-- Estimated DDL time on 200k rows: < 5 seconds.
-- Online DDL: yes, no table lock for INSERT/SELECT during build.


-- ════════════════════════════════════════════════════════════════════
-- §B — sale_items(product_id)
-- ════════════════════════════════════════════════════════════════════
--
-- Required by:
--   - patch 03 stats.php anomalies (JOIN sale_items si ON si.product_id=p.id)
--   - patch 04 products_fetch.php (all 6 sites, derived table groups by si.product_id)
--   - compute-insights.php probes (multiple sites — out of scope but free benefit)
--
-- Without this index:
--   sale_items × sales JOIN does a per-row lookup using PK only;
--   filtering by product_id is a full sale_items scan.
--
-- Verification BEFORE applying:
SHOW INDEX FROM sale_items;
-- Look for an existing index starting with product_id. Note: many
-- shops have a composite (sale_id, product_id) which DOES NOT help
-- product_id-driven queries (the leading column is sale_id).

-- ALTER (apply only if missing):
ALTER TABLE sale_items
    ADD INDEX idx_si_product_id (product_id);

-- Estimated impact:
--   - Patch 03 p95: 50-200 ms → 5-20 ms (~5-10× faster)
--   - Patch 04 p95: 150-400 ms → 30-80 ms (~3-5× faster)
--   - Index size: ~8 bytes/row. For 50k rows → ~400 KB. Trivial.
--
-- Estimated DDL time: < 2 seconds on 50k rows.
-- Online DDL: yes.


-- ════════════════════════════════════════════════════════════════════
-- §C — sales(tenant_id, store_id, created_at, status)
-- ════════════════════════════════════════════════════════════════════
--
-- Required by (helps but not strict requirement):
--   - patch 03 stats.php (sales(tenant_id, created_at) range scan)
--   - patch 04 products_fetch.php sites 4/5/6 (sales(tenant_id, store_id))
--   - compute-insights.php probes (cron-only, but background latency wins)
--   - chat.php / xchat.php / life-board.php homepage queries
--   - stats.php period range queries
--
-- The SAFE composite covers most time-window analytics queries:
--   (tenant_id, store_id, created_at, status)
-- Order matters: tenant_id is always equality-filtered, store_id
-- usually equality, created_at is range, status is equality.
--
-- Verification BEFORE applying:
SHOW INDEX FROM sales;
-- Look for any of:
--   - (tenant_id, store_id, created_at)  — current code may have this
--   - (tenant_id, created_at)            — partial cover, ok
--   - (tenant_id, status, created_at)    — would need redesign
--   - PK only                            — definitely add this
-- If a partial-cover variant exists, evaluate whether to extend or
-- create a new one (avoid index bloat).

-- ALTER (apply only if missing or worse-covered):
ALTER TABLE sales
    ADD INDEX idx_sales_tenant_store_status_created (tenant_id, store_id, status, created_at);

-- Estimated impact:
--   - Time-window queries (chat.php, life-board.php "today revenue"):
--     ~30-50 ms → ~5-10 ms
--   - Patch 03 stats anomalies: secondary boost
--   - Patch 04 sites 4/5/6: secondary boost
--
-- ⚠ Index size: ~28 bytes/row × N rows. For 30k rows → ~840 KB.
-- For larger shops this can be 10-50 MB. Verify capacity.
--
-- ⚠ Estimated DDL time: 5-30 seconds on a busy production sales table.
-- Schedule during true off-hours (e.g., 03:00-04:00 local time).
-- Online DDL: yes for index add (InnoDB).


-- ════════════════════════════════════════════════════════════════════
-- §D — inventory(product_id, store_id, tenant_id) [VERIFY only]
-- ════════════════════════════════════════════════════════════════════
--
-- This is a check, not a CREATE. Most shops should already have:
--   PRIMARY KEY (id) + UNIQUE (product_id, store_id) OR
--   PRIMARY KEY (product_id, store_id) [composite]
--
-- If neither, the JOIN inventory i ON i.product_id=p.id AND i.store_id=?
-- (used in patches 03 and 04) will be slow.

SHOW INDEX FROM inventory;

-- If missing, add (avoid duplicating the PK):
-- ALTER TABLE inventory
--     ADD UNIQUE INDEX idx_inv_product_store (product_id, store_id);


-- ════════════════════════════════════════════════════════════════════
-- §E — products(tenant_id, is_active, parent_id) [VERIFY only]
-- ════════════════════════════════════════════════════════════════════
--
-- Used in: every patch's outer WHERE clause.
-- Very likely already exists (S2/S3-era schema).
SHOW INDEX FROM products;

-- If only PK exists, the outer scans degrade. Add:
-- ALTER TABLE products
--     ADD INDEX idx_products_tenant_active_parent (tenant_id, is_active, parent_id);


-- ════════════════════════════════════════════════════════════════════
-- POST-APPLY VERIFICATION
-- ════════════════════════════════════════════════════════════════════
--
-- After applying §A, §B, §C, run the EXPLAIN ANALYZE battery from
-- /tmp/perf_audit/02_live_query_explain.md §3 against tenant_id=99.
--
-- Expected outcomes:
--   - Patch 01 (sale-voice): EXPLAIN should show "Using index for group-by"
--     on sm_agg derived table; outer products scan should be type=ref.
--   - Patch 02 (sale-search): same as 01.
--   - Patch 03 (stats anomalies): no DEPENDENT SUBQUERY rows; sale_items
--     join via idx_si_product_id (type=ref).
--   - Patch 04 (products_fetch): derived ls table materialized once;
--     no DEPENDENT SUBQUERY rows.
--
-- Stop-and-investigate if:
--   - Any AFTER query shows "Using temporary; Using filesort" with rows>10000
--   - p95 of any AFTER query is WORSE than BEFORE (rare, but possible
--     if optimizer picks a bad plan with the new indexes)


-- ════════════════════════════════════════════════════════════════════
-- ROLLBACK
-- ════════════════════════════════════════════════════════════════════
--
-- Indexes are additive — they cannot break correctness, only space and
-- DML performance. To remove (only if truly causing regressions):
--
-- ALTER TABLE stock_movements DROP INDEX idx_sm_tenant_product_type;
-- ALTER TABLE sale_items      DROP INDEX idx_si_product_id;
-- ALTER TABLE sales           DROP INDEX idx_sales_tenant_store_status_created;
--
-- Before dropping, run:
--   pt-index-usage /var/log/mysql/slow.log -- --user=... --password=...
-- to see which queries actually use each index.


-- ════════════════════════════════════════════════════════════════════
-- TIMING / OPERATIONAL NOTES
-- ════════════════════════════════════════════════════════════════════
--
-- Apply during off-peak window (suggest 03:00-05:00 local time).
-- Total estimated DDL time for §A + §B + §C: 10-60 seconds depending
-- on table sizes. None of these are blocking for SELECT/INSERT under
-- InnoDB online DDL, but they do briefly lock metadata.
--
-- For tenants with very large sales/sale_items/stock_movements tables,
-- consider pt-online-schema-change (Percona Toolkit) instead of native
-- ALTER TABLE for zero-blocking apply.
--
-- ════════════════════════════════════════════════════════════════════
