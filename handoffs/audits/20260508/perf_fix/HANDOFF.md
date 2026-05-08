# S120.PERF_QUICKWINS — HANDOFF

**Date:** 2026-05-08
**Author:** Code Code 1 (Opus, draft only)
**Duration:** ~45 minutes (well under 3h cap)
**Method:** Read-only static analysis + 4 patch drafts + INDEXES.sql + this handoff.
**Status:** ⚠ **DRAFT — awaiting Тихол approval before any production change**.

---

## Executive Summary

Four query rewrites + three indexes. All targets identified by the prior `S115.PERFORMANCE_AUDIT` (`/tmp/perf_audit/04_recommended_fixes.md` F1, F2, F4, F13, F14). This session converts the recommendations into ready-to-apply patch files with before/after, benchmark estimates, smoke tests, and rollback plans.

**Cumulative impact estimate (P0+P1 user-visible paths):** ~600 ms shaved off worst-case sale-voice / sale-search / stats anomalies / products dashboard, **without touching production yet**.

---

## Files Delivered

| File | Type | Topic |
|------|------|-------|
| `01_sale-voice_correlated_subquery.patch.php` | Query rewrite | F1 — ORDER BY correlated subquery → derived table JOIN. Fixes missing tenant_id scope as bonus. |
| `02_sale-search_keystroke_optimization.patch.php` | Query rewrite | F2 — Same pattern, fires per keystroke. Both barcode + search branches. |
| `03_stats_anomalies_derived_table.patch.php` | Query rewrite | F14 — `WHERE (SELECT COUNT(*)) > 0` → `JOIN ... DISTINCT` (Variant A) or `EXISTS` (Variant B). |
| `04_products_fetch_zombie_consolidation.patch.php` | Query rewrite | F13 — 6 sites in products_fetch.php, all sharing the same `MAX(s.created_at)` correlated pattern → shared derived table `ls`. |
| `INDEXES.sql` | DDL | §A `stock_movements(tenant_id, product_id, type)`, §B `sale_items(product_id)`, §C `sales(tenant_id, store_id, status, created_at)`, §D/§E verify-only. |
| `HANDOFF.md` (this file) | Executive summary | Per-fix file:line + before/after + p95 estimate + apply order. |

**Total:** 6 files (4 patch + 1 SQL + 1 HANDOFF).

---

## Per-Fix Summary Table

| # | Patch | Source file:line | Severity | p95 BEFORE | p95 AFTER | Speedup | Risk | Effort |
|---|-------|------------------|---------:|-----------:|----------:|--------:|-----:|-------:|
| 1 | sale-voice correlated subq | sale-voice.php:16-26 | **P0** | 300-1500 ms | 20-80 ms | ~10-50× | LOW | 20 min |
| 2 | sale-search keystroke | sale-search.php:14-42 (×2 sites) | **P0** | 80-300 ms / keystroke | 8-30 ms | ~10× | LOW | 15 min |
| 3 | stats anomalies | stats.php:1173 | **P1** | 50-200 ms | 5-20 ms | ~5-10× | LOW | 15 min |
| 4 | products_fetch zombie | products_fetch.php:302, 306, 596, 767, 769, 771 (×6 sites) | **P0** | 150-400 ms | 30-80 ms | ~3-5× | MEDIUM | ~1 h |

**P0 user-impact priorities:** #1, #2, #4 (all in user-facing flows).
**P1:** #3 (anomalies tab, on-demand).

---

## Apply Order (lowest risk first — strongly recommended)

### Stage 1: Indexes (during off-peak)

Apply `INDEXES.sql` sections in order:

1. `§A` — `idx_sm_tenant_product_type` on `stock_movements`
2. `§B` — `idx_si_product_id` on `sale_items`
3. `§C` — `idx_sales_tenant_store_status_created` on `sales`

Verify each with `SHOW INDEX FROM <table>` BEFORE creating, and `EXPLAIN` on the AFTER queries AFTER creating.

Indexes alone (without patches) already buy ~10-30% speedup on most queries because the existing correlated subqueries can use them. **You can apply indexes only first as a zero-risk first stage.**

### Stage 2: Patch 01 (sale-voice)

- Smallest patch (single query, 1 file).
- Test: open `/sale.php`, click voice mic, speak any product, confirm response time and content unchanged.
- Validates §A index works as expected.

### Stage 3: Patch 02 (sale-search)

- Hot path. Verify keystroke responsiveness in DevTools Network tab.
- Two branches (barcode vs search) — test both.

### Stage 4: Patch 03 (stats anomalies)

- On-demand path. Open `/stats.php` → "Аномалии" tab.
- Variant A (DISTINCT JOIN) preferred. Fall back to Variant B (EXISTS) if EXPLAIN shows `Using temporary; Using filesort`.

### Stage 5: Patch 04 site 1 only (products_fetch zombies, line 302)

- Apply ONLY the `$zombies` site first.
- Test `home_stats` AJAX endpoint: open the warehouse / products dashboard.
- If clean, proceed to sites 2-6 incrementally.

### Stage 6: Patch 04 sites 2-6 (incremental commits)

- Each site as a separate commit so individual rollback is possible.
- Sites 4/5/6 (signal pillbar generation) all share the same shape — can be done together as one commit.

---

## Required Indexes — Cross-reference

| Index | Used by patch(es) | Required or optional? |
|-------|-------------------|----------------------|
| §A `idx_sm_tenant_product_type` | 01, 02 | **REQUIRED** (without it, AFTER may be slower than BEFORE) |
| §B `idx_si_product_id` | 03, 04 | **REQUIRED** for max speedup |
| §C `idx_sales_tenant_store_status_created` | 03, 04 (and many other queries) | OPTIONAL but high-value (helps many queries beyond this session) |
| §D verify `inventory` | 03, 04 | sanity check only |
| §E verify `products` | all | sanity check only |

---

## Cumulative Impact Estimate (post-apply)

Aggregated across the 4 user-facing flows:

| Flow | Before | After | Saved |
|------|-------:|------:|------:|
| `/sale.php` voice command | 350-1550 ms | 30-90 ms | **~300-1500 ms** |
| `/sale.php` keystroke search (5 chars) | 400-1500 ms | 40-150 ms | **~360-1350 ms** |
| `/stats.php` anomalies tab | 50-200 ms | 5-20 ms | **~45-180 ms** |
| `/products_fetch.php?ajax=home_stats` | 200-400 ms | 80-150 ms | **~120-250 ms** |

**Realistic uplift: ~600-1500 ms peeled off worst-case interactions on Pro tenants.** Median pageloads should also see 30-100 ms reduction from index §C alone (not directly tied to a patch in this session but a nice side-effect).

---

## What's NOT in this Session (deferred)

Per the recommended-fixes audit, the following remain for later sessions:

| ID | Description | Suggested session |
|----|-------------|-------------------|
| F3 | `deliveries.php:65-69` dead query — pure cleanup | S121.PERF.CLEANUP |
| F5 | `warehouse.php` 6 COUNTs → UNION ALL | S122.PERF.BATCH |
| F6 | `xchat.php:196-197` dual COUNT → CASE WHEN | S122.PERF.BATCH |
| F7 | `deliveries.php:42` + `orders.php:38` correlated subqueries | S122.PERF.BATCH |
| F8 | `inventory.php:48-54` SELECT-then-INSERT/UPDATE → ON DUPLICATE | S122.PERF.BATCH |
| F9 | `delivery.php:425-450` same upsert pattern | S122.PERF.BATCH |
| F10 | Per-request tenant cache (`pf_tenant_cfg`) | S123.PERF.CACHE |
| F11 | SELECT * → projected columns (~18 sites) | S123.PERF.CACHE |
| F12 | `sale.php` per-item product fetch → batched | S122.PERF.BATCH |
| Future | `products.last_sold_at` denormalized column (sale-commit hook) | S124.PERF.DENORM |

These are intentionally out of scope for the 3h S120 cap and the "top 4 quick wins" charter.

---

## DOD Verification

| Criterion | Status |
|-----------|--------|
| 4 patch files in /tmp/perf_fix/ | ✅ 4 (01-04) |
| INDEXES.sql | ✅ |
| HANDOFF.md | ✅ (this file) |
| ZERO git ops | ✅ (verified — no `git add`, `commit`, `push`, `revert` invoked) |
| ZERO production mutations | ✅ (no live ALTER, no live PHP edits — patches are .patch.php in /tmp/) |
| Time ≤ 3h | ✅ ~45 min |

---

## Approval Checkpoint

Before any of these patches are applied to production:

1. **Тихол reviews this HANDOFF + each patch file individually.**
2. Тихол confirms scheduling window for index DDL (off-peak).
3. Тихол runs the EXPLAIN battery (`/tmp/perf_audit/02_live_query_explain.md §3`) against tenant_id=99 to validate predictions.
4. Тихол approves each stage individually (Stages 1-6 above).
5. **Then** Code Code 1 (or Code 2, depending on file ownership) applies each stage as a separate commit with a backup taken first.

**No git operation will happen from this session.** All output is read-only documentation in `/tmp/perf_fix/`.

---

## Rollback Quick Reference (consolidated from per-patch sections)

```bash
# Single-file rollback:
cp -p /var/www/runmystore/backups/s120_<TS>/<FILE>.bak \
       /var/www/runmystore/<FILE>

# Or git revert (Тихол's call):
git revert <commit-hash-of-this-stage>

# Index removal (only if causing harm — verify via pt-index-usage first):
ALTER TABLE stock_movements DROP INDEX idx_sm_tenant_product_type;
ALTER TABLE sale_items      DROP INDEX idx_si_product_id;
ALTER TABLE sales           DROP INDEX idx_sales_tenant_store_status_created;
```

---

## File Inventory & Sizes

```
/tmp/perf_fix/
  01_sale-voice_correlated_subquery.patch.php       ~6 KB
  02_sale-search_keystroke_optimization.patch.php   ~7 KB
  03_stats_anomalies_derived_table.patch.php        ~7 KB
  04_products_fetch_zombie_consolidation.patch.php  ~12 KB
  INDEXES.sql                                       ~7 KB
  HANDOFF.md                                        (this file, ~7 KB)
```

**Total: 6 files, ~46 KB of structured documentation.** No production binaries touched.
