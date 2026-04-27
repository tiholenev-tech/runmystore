# REAL_BUGS_FOUND — S85.DIAG.FIX escalation list

**Date:** 2026-04-27  
**Session:** S85.DIAG.FIX  
**Author:** Claude Code (diagnostic-only scope)

After running diagnostic and applying ALL diagnostic-side fixes, the items below remain
unresolved because they require changes outside `tools/diagnostic/` (PHP business logic
or product-design decisions). Each is escalated for shef-chat / Tihol review.

---

## 1. `highest_margin_d_no_sales` — pfHighestMargin does not filter by sales

**Topic:** `highest_margin`  
**Scenario expectation:** Product with high margin but zero sales (`product 9092`,
retail=100, cost=5, no sale rows) should NOT appear in the topic's items list.

**PHP actual behaviour (`compute-insights.php` `pfHighestMargin`, lines 658–693):**
The SQL is a pure `SELECT … FROM products … ORDER BY margin_pct DESC LIMIT 10` with no
join to `sale_items` / `sales`. Any active product with `cost_price > 0` and
`retail_price > cost_price` is eligible regardless of sales activity.

**Why this matters:**
The topic conceptually answers "how do I make money?" (gain_cause). A product that has
never been sold contributes zero to margins realised. Whether it should be promoted
("you have 95% theoretical margin — push it") or hidden ("recommend only sellers")
is a product decision.

**Decision needed:**
- **Option A** — keep PHP as-is (theoretical-margin ranker). The diagnostic scenario
  `highest_margin_d_no_sales` is then incorrect and was disabled (`is_active=0`) for
  S85.DIAG.FIX. A new positive-D scenario should be authored that covers the *boundary*
  this scenario was trying to reach.
- **Option B** — modify `pfHighestMargin` SQL to require at least one sale in the last
  N days (e.g., `EXISTS (SELECT 1 FROM sale_items si JOIN sales s …)`). Re-enable the
  scenario.

**Status in seed_oracle:** `is_active=0` (deactivated by S85.DIAG.FIX so Cat D = 100%
is achievable today). Reactivate after option chosen.

**Action owner:** Tihol (product) + shef-chat (PHP edit if option B).

---

## 2. (none — all other failures resolved diagnostic-side)

The remaining 29 failures from baseline run #16 were all caused by diagnostic-side bugs
(verifier semantics, fixture schema mismatches, fixture-vs-rule arithmetic gaps,
sale_id collision between scenarios). All are fixed in S85.DIAG.FIX commit. See
`MASTER_COMPASS.md` LOGIC CHANGE LOG entry for the full list.
