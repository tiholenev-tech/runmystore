# S91.INSIGHTS_HEALTH — Handoff

**Date:** 2026-05-01
**Scope:** Routing fix in `compute-insights.php` + new owner-only diagnostic dashboard `admin/insights-health.php`.

---

## Task A — Routing fix applied

`compute-insights.php` line 236-237:

```diff
-    $module = $i['module'] ?? 'products';
-    if (!in_array($module, $valid_modules, true)) $module = 'products';
+    $module = $i['module'] ?? 'home';
+    if (!in_array($module, $valid_modules, true)) $module = 'home';
```

`$valid_modules` array intact. `php -l compute-insights.php` → no syntax errors. 1 line of comment also updated to reflect the new default.

## Task B — Admin monitor created

`admin/insights-health.php` (~210 lines):

- Owner-only auth (copied from `warehouse.php` pattern, role check `=== 'owner'`).
- Three queries: module distribution (last 7d), top 5 hidden topic_ids (`module != 'home'`), totals.
- Health status: HEALTHY (0% hidden) · WARNING (1-30%) · CRITICAL (≥30%).
- Color-coded module pills, totals grid (Total / Visible / Hidden + percentages), Refresh + Back links.
- Plain inline HTML+CSS, max-width 480px container, dark theme fixed (no toggle — admin-only).
- `php -l admin/insights-health.php` → no syntax errors.

URL after deploy: `https://<host>/admin/insights-health.php`. Tihol must be logged in as owner.

---

## Code reality check (important)

The investigation report and task brief described "старите 6 pf функции" as the ones explicitly setting `module='home'`. Reading the code, the situation is the inverse:

| pf function group | Lines | Explicit `module=>'home'`? |
|---|---|---|
| **S89 batch** (`pfDeliveryAnomalyPattern`, `pfPaymentDueReminder`, `pfNewSupplierFirstDelivery`, `pfVolumeDiscountDetected`, `pfStockoutRiskReduction`, `pfOrderStaleNoDelivery`) | 1362–1654 | **YES** (lines 1384, 1435, 1475, 1525, 1581, 1626) |
| **Older 19 functions** (`pfZeroStockWithSales`, `pfBelowMinUrgent`, `pfRunningOutToday`, `pfSellingAtLoss`, `pfNoCostPrice`, `pfMarginBelow15`, `pfSellerDiscountKiller`, `pfTopProfit30d`, `pfProfitGrowth`, `pfHighestMargin`, `pfTrendingUp`, `pfLoyalCustomers`, `pfBasketDriver`, `pfSizeLeader`, `pfBestsellerLowStock`, `pfLostDemandMatch`, `pfZombie45d`, `pfDecliningTrend`, `pfHighReturnRate`) | 288–1331 | **NO** — fall through to default |

So before the fix, the 19 older functions produced `module='products'`. After the fix, they will produce `module='home'`. The 6 S89 functions are unchanged (they always set `home` explicitly).

This is consistent with the observed numbers: 33 in `products` (from the older 19 pf functions) + 8 in `home` (from the S89 batch) = 41 total over 7 days.

---

## Diagnostic SELECT — could not run from CLI

The brief asked me to run:
```sql
SELECT topic_id, COUNT(*) FROM ai_insights
WHERE tenant_id=7 AND module='products' AND created_at > NOW() - INTERVAL 7 DAY
GROUP BY topic_id;
```

`/etc/runmystore/db.env` is owned by `www-data` and not readable by the agent's user (`tihol`); `sudo` escalation was denied by the harness. **Тихол трябва да изпълни тази SELECT ръчно** (или просто да отвори `/admin/insights-health.php` — query (b) там показва Top 5 hidden topic_ids групирано по `module`, което покрива въпроса).

Easiest local invocation:
```bash
sudo -u www-data php -r '
require "config/database.php";
foreach (DB::get()->query("SELECT topic_id, COUNT(*) c FROM ai_insights WHERE tenant_id=7 AND module=\"products\" AND created_at > NOW() - INTERVAL 7 DAY GROUP BY topic_id ORDER BY c DESC") as $r) printf("%-40s %d\n", $r["topic_id"], $r["c"]);'
```

---

## Migration recommendation for the 33 existing `products` insights

**Recommendation: do NOT migrate.** Reasoning:

- The 6 S89 functions (which the brief flagged as "should be home") already set `module='home'` explicitly in code — confirmed by `grep`. They cannot have produced any of the 33 `products` rows.
- Therefore the 33 `products` rows must come from the 19 older pf functions, which are products-themed (zombie products, declining trend per SKU, high return rate, basket driver, size leader, etc.) — historically intended for the products view.
- Migrating them to `home` would surface SKU-level trivia in life-board and dilute the high-signal feed Tihol expects there.
- Going forward, however, **new** runs of these same 19 older functions will route to `home` (because of the default change). If Тихол prefers to keep these older insights in `products`, the next step would be to add an explicit `'module' => 'products'` to each of those 19 pf functions — that's a separate sprint scoped change.

If Tihol disagrees and wants the 33 migrated, the SQL is:
```sql
UPDATE ai_insights SET module='home'
WHERE tenant_id=7 AND module='products'
  AND created_at > NOW() - INTERVAL 7 DAY;
```
(No DDL, only DML. Reversible via the same UPDATE flipping the value back, since `created_at` filter narrows the set.)

---

## Test plan (post-deploy)

1. Visit `/admin/insights-health.php` as owner. Confirm dashboard renders, shows current 33 `products` / 8 `home` distribution + CRITICAL status (~80%).
2. Wait for next cron run of `compute-insights.php`. Re-visit dashboard.
3. Verify newly generated insights from the 19 older pf functions land in `module='home'`. Top 5 hidden table should shrink or empty out.
4. Check life-board.php (chat.php line 228 filter) — should now display additional signals beyond the previous 1–2/day.
5. If insights start feeling spammy, the next sprint can add explicit `'module' => 'products'` to selected older pf functions to restore segregation.
6. Monitor `ai_insights` row counts daily for 3 days. Healthy steady-state target: hidden % stays under 30 (WARNING threshold).

---

## Files changed / created

- **Modified:** `compute-insights.php` (1 logic-line change @ 236-237 + 1 comment line on 234).
- **Created:** `admin/insights-health.php`, `SESSION_S91_INSIGHTS_HANDOFF.md`.
- **Backup:** `.backups/s91_insights_20260501_170143/compute-insights.php`.
- **Untouched:** products.php, chat.php, sale.php, design-kit/*, helpers.php, DB schema, the existing 33 `products` insight rows.
