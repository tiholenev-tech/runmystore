# Phase 3 — Recommended Fixes (priority sorted)

**Session:** S115.PERFORMANCE_AUDIT
**Source:** `01_static_findings.md` + `02_live_query_explain.md`
**Sort:** P0 → P3, low-effort/high-impact wins first.

---

## P0 — User-facing critical (do first)

### F1. Fix `sale-voice.php:14-26` — ORDER BY correlated subquery

**Impact:** highest. Sale UX entrypoint; can take 300-1500 ms on Pro tenants today.
**Effort:** ~20 min.
**Risk:** LOW (read-only path, easy to A/B).

```diff
- ORDER BY (SELECT COUNT(*) FROM stock_movements sm WHERE sm.product_id=p.id AND sm.type='out') DESC
+ LEFT JOIN (SELECT product_id, COUNT(*) AS sold_count
+              FROM stock_movements
+             WHERE tenant_id=? AND type='out'
+             GROUP BY product_id) sm_agg ON sm_agg.product_id=p.id
+ ORDER BY sm_agg.sold_count DESC
```

**Also fixes:** missing `tenant_id` scope in subquery (security smell flagged in §A3 of static findings).

### F2. Fix `sale-search.php:19, 31` — same correlated subquery, fires per keystroke

**Impact:** every search key press → 80-300 ms. Multiplied by typing speed.
**Effort:** ~15 min (same diff as F1, two query sites).
**Risk:** LOW.

### F3. Drop dead query `deliveries.php:65-69`

**Impact:** trivial, but **negative LOC** (cleanup) + 5 ms saved per deliveries hub load.
**Effort:** ~2 min.
**Risk:** ZERO — it's currently a no-op writing `null` to `$kpi_week` that gets immediately overwritten.

```diff
-$kpi_week = (float)$pdo->prepare("
-    SELECT COALESCE(SUM(total), 0) FROM deliveries
-    WHERE tenant_id = ? AND status = 'committed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
-")->execute([$tenant_id]) ? null : null;
-
 $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM deliveries WHERE tenant_id=? AND …");
 $stmt->execute([$tenant_id]);
 $kpi_week = (float)$stmt->fetchColumn();
```

### F4. Verify + add critical indexes (`stock_movements`, `sale_items`)

**Impact:** F1 + F2 fixes still benefit from these; without them the JOIN plans degrade.
**Effort:** ~30 min (run EXPLAINs first to confirm they're missing).
**Risk:** MEDIUM — `ALTER TABLE` on large tables locks metadata briefly. Run during low traffic.

```sql
-- Run these only AFTER EXPLAIN confirms they're not already present:
ALTER TABLE stock_movements
  ADD INDEX idx_sm_tenant_product_type (tenant_id, product_id, type);

ALTER TABLE sale_items
  ADD INDEX idx_si_product_id (product_id);
```

---

## P1 — Hot module quick wins

### F5. Batch `warehouse.php:29-44` — 6 COUNTs → 1 UNION ALL

**Impact:** ~25 ms saved on every warehouse hub load.
**Effort:** ~30 min including testing.
**Risk:** LOW.

**Why P1 not P0:** warehouse.php is less hot than sale.php / chat.php. Still good ROI.

### F6. Batch `xchat.php:196-197` — dual COUNT → single CASE WHEN

**Impact:** ~5 ms per chat pageload. xchat is hit constantly for owner-mode users.
**Effort:** ~10 min.
**Risk:** ZERO.

```diff
-$total_products = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1', [$tenant_id])->fetchColumn();
-$with_cost      = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0', [$tenant_id])->fetchColumn();
+$row = DB::run(
+  "SELECT COUNT(*) AS total,
+          SUM(CASE WHEN cost_price>0 THEN 1 ELSE 0 END) AS with_cost
+     FROM products WHERE tenant_id=? AND is_active=1",
+  [$tenant_id]
+)->fetch(PDO::FETCH_ASSOC);
+$total_products = (int)$row['total'];
+$with_cost      = (int)$row['with_cost'];
```

Apply same pattern in `chat.php` if duplicated.

### F7. Replace correlated subquery in `deliveries.php:42` and `orders.php:38` with derived table

**Impact:** deliveries hub: -10 subquery executions → ~30 ms saved. orders.php: -24 → ~60 ms.
**Effort:** ~20 min each.
**Risk:** LOW (verify EXPLAIN shows derived table materialized once).

### F8. `inventory.php:48-54` — convert SELECT-then-INSERT/UPDATE to ON DUPLICATE KEY UPDATE

**Impact:** halves roundtrips per inventory_count_line save.
**Effort:** ~45 min (includes verifying the UNIQUE indexes exist; if not, add migration).
**Risk:** MEDIUM (touches inventory commit path; need careful test against `inventory_count_lines.UNIQUE` schema).

**Reference:** `compute-insights.pfUpsert()` already uses this pattern in production (since S92.INSIGHTS.WRITE).

### F9. `delivery.php:425-450` — same upsert pattern as F8 for delivery commit

**Impact:** halves roundtrips per delivery line.
**Effort:** ~30 min.
**Risk:** MEDIUM.

---

## P2 — Lower-traffic / refactor wins

### F10. Per-request tenant cache (`products_fetch.php` + `helpers`)

**Impact:** 10× tenant fetches → 1 fetch on `products_fetch.php`. Saves ~10-30 ms per pageload.
**Effort:** ~1 h (new helper + 10 call-site rewrites in products_fetch.php).
**Risk:** LOW.

```php
// services/tenant-cache.php (new file)
function tenant_cfg(int $tenant_id): array {
    static $cache = [];
    if (!isset($cache[$tenant_id])) {
        $cache[$tenant_id] = DB::run(
            "SELECT id, name, plan, currency, language, ui_mode,
                    units_config, colors_config,
                    ai_credits_bg, ai_credits_tryon,
                    trial_ends_at, is_active
               FROM tenants WHERE id=? LIMIT 1",
            [$tenant_id]
        )->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return $cache[$tenant_id];
}
```

### F11. Project columns instead of `SELECT *` (across 18 sites)

**Impact:** smaller payload per fetch; matters most where `tenants.*` returns JSON config blobs.
**Effort:** ~1.5 h (each site needs an inspection of which columns it reads).
**Risk:** ZERO — purely additive constraint.

**Highest-impact targets:**
- `life-board.php:38` (homepage)
- `chat.php:43` / `xchat.php:43` (every chat load)
- `products_fetch.php:31` (every fetch)
- `sale.php:24` (every sale page)
- `deliveries.php:91` — the `SELECT * FROM ai_insights` includes large `data_json` and `action_data` JSON cols.

### F12. `sale.php:343-415` — batch product fetch outside the per-item loop

**Impact:** N items in cart → 1 product SELECT instead of N. ~40-50 ms saved on a 10-item cart.
**Effort:** ~30 min.
**Risk:** MEDIUM (touches sale commit transaction; needs full test against StockException + negative override paths).

```diff
+$pids = array_map('intval', array_column($items, 'product_id'));
+$placeholders = implode(',', array_fill(0, count($pids), '?'));
+$products_map = [];
+foreach (DB::run("SELECT id, cost_price, name, retail_price FROM products
+                   WHERE id IN ($placeholders) AND tenant_id=?",
+                 [...$pids, $tenant_id])->fetchAll() as $row) {
+    $products_map[(int)$row['id']] = $row;
+}
 foreach ($items as $it) {
-    $prod = DB::run("SELECT cost_price, name, retail_price FROM products WHERE id=? AND tenant_id=? LIMIT 1",
-                    [$pid, $tenant_id])->fetch();
+    $prod = $products_map[$pid] ?? null;
     if (!$prod) {
         throw new Exception("Артикул #$pid не съществува в твоя магазин.");
     }
     …
 }
```

The `FOR UPDATE` lock query, INSERT sale_items, UPDATE inventory, INSERT stock_movements stay per-iteration (intentional per-row locking).

### F13. `products_fetch.php:302-306, 596, 767-771` — extract zombie subquery to derived table

**Impact:** 3-5× speedup on these 6 dashboard queries.
**Effort:** ~1.5 h (refactor + EXPLAIN verification).
**Risk:** MEDIUM.

**Even better future fix:** add `products.last_sold_at` denormalized column maintained by sale-commit trigger (or PHP DB::tx). Eliminates the JOIN entirely. Out of scope for this session; flag as RWQ for S118+.

### F14. `stats.php:1188` — anomalies subquery → JOIN form

**Impact:** 50-200 ms saved on stats anomalies tab.
**Effort:** ~15 min.
**Risk:** LOW (verify result equivalence; LIMIT 5 is on outer query).

---

## P3 — Background / nice-to-have

### F15. `compute-insights.php` cron probes

All ~30 probe functions have explicit `LIMIT N`. They are slow (collectively several hundred ms per tenant) but **background**. Defer optimization until tenant count crosses ~100. P3.

### F16. `ai-helper.php gatherBusinessContext`

5-7 queries per chat message. Cacheable per (tenant_id, store_id) for ~60s — Pешо's chat doesn't need second-by-second freshness on "today's revenue".

**Suggested:** wrap in a per-request memoization with a soft TTL (e.g., file/APCu cache). P3 because chat latency is dominated by the LLM call, not DB.

### F17. `selection-engine.php`, `ai-studio-backend.php` SELECT *

Small reference tables (`ai_topics_catalog`, `ai_prompt_templates`). Negligible. P3.

---

## Cumulative impact estimate

| Bucket | Findings | Cumulative latency saved (best-case) |
|---|---|---|
| P0 (F1-F4) | 4 | **300 ms - 1.5 s** on sale UX paths |
| P1 (F5-F9) | 5 | ~120 ms across warehouse + chat + deliveries + orders + inventory commit |
| P2 (F10-F14) | 5 | ~150 ms across products_fetch + stats |
| P3 (F15-F17) | 3 | Mostly background; ~40 ms chat |

**Total realistic uplift:** **~600 ms** off worst-case product/sale flows; **~30-100 ms** off median pageloads.

---

## Sequencing for next sessions

1. **S118.PERF.QUICKWINS** (~3 h) — F1, F2, F3, F6 (all zero-risk, biggest user-visible wins).
2. **S119.PERF.INDEXES** (~2 h) — F4: EXPLAIN battery + missing index migrations.
3. **S120.PERF.BATCH** (~4 h) — F5, F7, F8, F9, F12 (mid-risk batchable patterns).
4. **S121.PERF.CACHE** (~3 h) — F10, F11, F16 (caching layer + SELECT * cleanup).
5. **S122.PERF.DEFER** (~2 h) — F13, F14 + denormalized `products.last_sold_at` design.

**Total: ~14 h spread across 5 sessions.** Don't try to land it all at once — each phase is a separate testable commit set.

---

## Anti-patterns NOT to apply

- ❌ **Don't add Redis** for caching tenant config (F10) — APCu / static var is sufficient at current scale.
- ❌ **Don't add EXPLAIN logging on every query** — just use slow query log with `long_query_time=0.5`.
- ❌ **Don't denormalize aggressively** before profiling. F13's `products.last_sold_at` is the only justified denorm; it has clear write-side discipline (trigger or DB::tx hook) and obvious read-side win.
- ❌ **Don't change `compute-insights.php`** for cron-only paths during user-facing perf push. It works; defer.
- ❌ **Don't refactor `sale.php:343-415`** beyond F12. The per-row FOR UPDATE + UPDATE pattern is correct and intentional.
