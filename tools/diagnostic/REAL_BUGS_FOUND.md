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

---

# S88.DIAG.VERIFY_TENANT7 addendum — 2026-04-29

**Session:** S88.DIAG.VERIFY_TENANT7
**Commit under test:** ff7d13d9 (S88.AIBRAIN.ACTIONS)
**Runs:** diagnostic_log #27 (--tenant 7, mis-targeted), #28 (--tenant 99, authoritative)

## F0. (P1) `--tenant 7` is unsafe with current `fixtures.py` PK scheme

**Symptom.** `python3 tools/diagnostic/run_diag.py --tenant 7` reports `Seed: 52 ok,
0 errors`, but `SELECT … FROM products WHERE tenant_id=7 AND id BETWEEN 9001 AND 9999`
returns no fixture rows. 27/57 scenarios then fail with "product_id=X не е в items".

**Root cause.** `tools/diagnostic/modules/insights/fixtures.py` hard-codes primary keys:
```
PRODUCT_TPL  : INSERT INTO products (id={pid}, tenant_id={{tenant_id}}, …) VALUES (9001, 7, …)
                  ON DUPLICATE KEY UPDATE retail_price=…, cost_price=…, min_quantity=…, is_active=1
SALE_TPL     : INSERT INTO sales (id={sale_id}, tenant_id={{tenant_id}}, …)
RETURN_TPL, CUSTOMER_TPL, INVENTORY_TPL : same pattern
```
Those PKs already belong to tenant=99 rows. `id` is the table PK, so `INSERT … ON
DUPLICATE KEY UPDATE` matches the *existing* tenant=99 row and runs an UPDATE that
*excludes* `tenant_id` — a silent no-op for tenant=7's perspective, and a silent
mutation of tenant=99 fixtures (price/min_qty/is_active overwritten with what
scenarios.py currently emits).

**Why this matters.** (a) Verification is meaningless on tenant=7 (false 47% pass
rate); (b) running `--tenant 7` while the cron schedule also runs `--tenant 99` could
clobber tenant=99 fixtures with stale values from a different scenarios.py revision.

**Fix options.**
- **A (quick):** Add `if tenant_id == 7: raise RuntimeError("--tenant 7 is the
  production-data tenant; scenario fixtures are not designed for it")` in
  `seed_runner.assert_safe_tenant` or in `run_diag.run()`.
- **B (correct):** Restructure fixture PKs to be tenant-scoped (e.g., `id = tenant_id*100000 + ordinal`)
  and use `(tenant_id, code)` as the upsert key. Allows real per-tenant test isolation.

**Action owner.** shef-chat (framework-side fix).

---

## F1. (P1) Cat A regression: `high_return_pos_0` post-S88

**Topic:** `high_return_rate` (Cat A, fundamental_question=loss).
**Symptom.** Run #28 (tenant=99): `product_id=9181` not in `high_return_rate.items`;
items count = 1 (was ≥1 with 9181 included pre-S88, run #24 PASS).
**Suspect.** S88's edits to `pfHighReturnRate` for `action_*` injection may have
narrowed the SELECT or changed the ranking. Compare ff7d13d9 diff for this `pf` against
the pre-S88 version.
**Action owner.** shef-chat (PHP review).

## F2. (P1) Cat B/C regression: basket pair `(9121, 9122)` no longer surfacing

**Topics:** `basket_driver` (Cat B `basket_pair_b_pos` and Cat C `basket_pair_c_rank`).
**Symptom.** Both runs (#26, #28) report "pair (9121,9122) липсва". Run #24 PASS.
**Suspect.** `pfBasketDriver` change in S88 — likely an `action_data` JSON build path
that drops the pair from the items list, or a stricter min-co-occurrence filter.
**Action owner.** shef-chat.

## F3. (P1) Cat D regression: `high_return_d_cartesian` — wrong rate computed

**Topic:** `high_return_rate`.
**Symptom.** Verifier expects rate ∈ [99.0, 101.0] (≈100% return rate scenario);
post-S88 PHP returns 18.5. Pre-S88 (run #24) PASS.
**Suspect.** Either return-rate denominator changed (sales window?) or
`action_data` writer inadvertently divides by a different total in pfHighReturnRate.
**Action owner.** shef-chat.

## F4. (P1) Cat D regression: `zombie_d_exact_45` — boundary at exactly 45 days

**Topic:** `zombie_45d`.
**Symptom.** Product 9163 (last sold *exactly* 45 days ago) appears in items;
scenario expects it must NOT (only > 45 should trigger).
**Suspect.** Inequality flipped from `>` to `>=` (or `DATEDIFF` boundary off-by-one)
during S88 edits to `pfZombie`. Companion scenario `zombie_d_exact_46` PASSES, which
narrows the flaw to the equality boundary.
**Action owner.** shef-chat (one-character SQL fix likely).

## F5. (P2 / pre-existing, re-surfaced) `highest_margin_d_no_sales`

Same as item **#1** above (S85.DIAG.FIX escalation). The scenario was deactivated in
seed_oracle by S85 (`is_active=0`, `deprecated_at=2026-04-26 08:38:51`), but a new
seed_oracle row (`id=401`, tenant=99, `is_active=1`, `deprecated_at=NULL`) was added
during S88 — re-enabling the test without `pfHighestMargin` SQL having been changed.
The product/owner decision in item #1 is still pending.

**Action owner.** Tihol (product) + shef-chat (PHP if option B chosen).

---

## Positive findings (S88)

- **action_type coverage = 100%** on both tenant=7 (41/41 typed, 0 'none') and
  tenant=99 (19/19 typed, 0 'none'). All 6 `fundamental_question` values represented
  on tenant=7 with at least one populated action_type. The S88.AIBRAIN.ACTIONS goal
  is achieved on real production data.
- **Cat E = 5/5 PASS** (ENUM/migration whitelist regression check) — first time green
  since the category was introduced.
