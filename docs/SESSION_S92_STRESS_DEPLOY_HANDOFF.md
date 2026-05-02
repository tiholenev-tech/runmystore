# SESSION S92.STRESS.DEPLOY — HANDOFF

**Date:** 2026-05-02
**Predecessor:** S92.AIBRAIN.PHASE1 (a5605a9 / origin merged through 1630ef3)
**Time spent:** ~1.5h of 2.5h budget
**Status:** PARTIAL — 2 of 3 P0 fixes confirmed pre-existing, 3rd refactored;
crontab install + DB verifications BLOCKED on credentials Тихол must supply.

---

## Commits this session (linear, on top of origin/main `1630ef3`)

| # | Hash | Subject |
|---|------|---------|
| 1 | `16a43eb` | S92.STRESS.DEPLOY.01: race test harness for sale.php atomicity |
| 2 | `ebcba03` | S92.STRESS.DEPLOY.02: sales_pulse.py rewrite as sales_populate.py wrapper |
| 3 | (this doc) | S92.STRESS.DEPLOY.03: handoff doc + REWORK QUEUE entries #54-#57 |

**Push status:** ⚠️ NOT PUSHED. Local main is 2 commits ahead of origin/main.
HTTPS push from this shell fails with "could not read Username for github.com" —
no credential helper, no SSH key, no GH_TOKEN in env. Тихол: run
`git push origin main` from a shell with credentials, or have the auto-sync
process pick it up.

---

## DOD scorecard

Numerical acceptance criteria from the session brief:

| # | Criterion | Status | Evidence |
|---|-----------|--------|----------|
| 1 | crontab installed on www-data; `crontab -l -u www-data` ≥3 entries | ⚠️ MANUAL | sudo password not available — Тихол runs install (see Part A) |
| 2 | `sales_pulse.sh --dry-run` exit 0 | ⚠️ MANUAL | as tihol: PermissionError on db.env (expected); needs `sudo -u www-data` to fully verify |
| 3 | `SELECT module, COUNT(*) FROM ai_insights WHERE tenant_id=7 GROUP BY module` → home ≥ 6 | ⚠️ MANUAL | open /admin/insights-health.php (S91 dashboard); S91.INSIGHTS_HEALTH default `'home'` is in place at compute-insights.php:236 |
| 4 | 2-parallel race test: 1 success + 1 stockout | ⚠️ MANUAL | harness ready (`tools/diagnostic/tests/race_test_sale.py`); needs `sudo -u www-data` to run |
| 5 | sales_pulse dry-run shows peak hours 11-13 + 17-19 | ⚠️ MANUAL | guaranteed by `sales_populate.PEAK_HOURS = (11,12,13,17,18,19)` + `PEAK_SHARE = 0.60`; visible after manual sudo run |
| 6 | this handoff doc ≥ 80 lines + DOD scorecard | ✅ | this file |
| 7 | 4 REWORK QUEUE entries (#54-#57) recorded | ✅ | section below |
| 8 | L4: 4-6 commits "S92.STRESS.DEPLOY.[STEP]: ..." pushed | ⚠️ 3 commits, push BLOCKED | hashes above |

---

## Part A — Crontab install (BLOCKED on sudo, manual)

**File:** `tools/diagnostic/cron/runmystore-diagnostic.crontab` (4 entries)

| When | Job | Script |
|------|-----|--------|
| `0  3 * * *` (daily 03:00) | nightly sales pulse on tenant=7 | `sales_pulse.sh` |
| `0  3 * * 1` (Mon 03:00) | weekly diagnostic | `diagnostic_weekly.sh` |
| `0  4 1 * *` (1st 04:00) | monthly diagnostic | `diagnostic_monthly.sh` |
| `30 8 * * *` (daily 08:30) | daily summary email | `daily_summary.sh` |

### Commands for Тихол to execute (in order)

```bash
# 1. Install user crontab for www-data
sudo crontab -u www-data /var/www/runmystore/tools/diagnostic/cron/runmystore-diagnostic.crontab

# 2. Verify — expect 4 active job lines plus header comments
sudo -u www-data crontab -l

# 3. Smoke-test sales_pulse with dry-run (no INSERTs, no inventory updates)
sudo -u www-data /var/www/runmystore/tools/diagnostic/cron/sales_pulse.sh --dry-run
echo "exit=$?"
# expected: exit=0 + log line at /var/log/runmystore/sales_pulse_YYYYMMDD.log
# showing time-range across today with peak in 11-13 and 17-19h
```

After step 1, DOD #1 is satisfied. After step 3, DOD #2 + #5 are satisfied.

---

## Part B — 3 P0 fixes

### FIX #1 — sale.php inventory race condition

**Status:** ✅ CONFIRMED PRE-EXISTING (S90.RACE commit `34041ca`, 2026-05-01)

**File:** `sale.php:135-139` (STRESS_BOARD references "ред 132" — line drift from
S91 visual migration of 04fa915, the actual fix sits at 135 now).

**Code in place:**

```php
$upd = DB::run("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND store_id = ? AND quantity >= ?",
    [$qty, $pid, $store_id, $qty]);
if ($upd->rowCount() === 0) {
    throw new Exception("Артикулът свърши преди да го продадеш. Презареди и опитай отново.");
}
```

The `WHERE quantity >= ?` makes the decrement atomic at SQL level (InnoDB row
lock + WHERE re-evaluation). The `rowCount()===0` branch surfaces the stockout
to the user inside the existing transaction, triggering a rollback of the
INSERTs into `sales` + `sale_items`.

**Verify (Тихол, manual):**

```bash
sudo -u www-data python3 /var/www/runmystore/tools/diagnostic/tests/race_test_sale.py
# expected last line: "PASS: exactly 1 winner, inventory drained to 0 (no over-sell)"
```

The harness picks any tenant=99 product, snapshots its quantity, forces it to
1, spawns two parallel UPDATEs in independent processes/transactions, asserts
exactly one wins (`rc_sum == 1`) and final qty=0, then restores the snapshot.

### FIX #2 — compute-insights.php module routing

**Status:** ✅ CONFIRMED PRE-EXISTING (S91.INSIGHTS_HEALTH commit `c9009d2`, 2026-05-01)

**File:** `compute-insights.php:234-237`

```php
// module enum е ('home','products','warehouse','stats','sale') — default 'home' (S91 fix), override чрез $i['module']
$valid_modules = ['home','products','warehouse','stats','sale'];
$module = $i['module'] ?? 'home';
if (!in_array($module, $valid_modules, true)) $module = 'home';
```

The 6 fundamental-question pf functions (q1-q6) which previously fell through
to `'products'` now default to `'home'` — they appear on life-board.

**Verify (Тихол, manual):** open `/admin/insights-health.php` (owner-only S91
dashboard). Module-distribution table should show `home` row with count ≥ 6
for last 7 days on tenant=7. No SQL CLI required.

### FIX #3 — sales_pulse.py DATE_ADD + GREATEST bugs

**Status:** ✅ FIXED THIS SESSION (commit `ebcba03`)

**File:** `tools/diagnostic/cron/sales_pulse.py` (full rewrite, 53 lines)

**Before (v1):** every sale received `created_at = DATE(NOW()) + INTERVAL X HOUR`
→ every timestamp landed on `YYYY-MM-DD HH:00:00` (no minute/second variation),
peak weights skewed to 14-17h (wrong vs spec 11-13/17-19), and inventory used
`UPDATE … SET quantity = GREATEST(quantity - X, 0)` which silently clamped at 0
instead of failing — stress diagnostic could never detect a stockout.

**After (v2):** thin wrapper around `tools/seed/sales_populate.main()` which
already implements:

  - `PEAK_HOURS = (11, 12, 13, 17, 18, 19)`, `PEAK_SHARE = 0.60`
  - per-second timestamps within `BUSINESS_HOURS = range(9, 22)`
  - `BASKET_DIST` (avg 1.88 items), `ITEM_QTY_DIST` (avg 1.18)
  - `RETURN_RATE = 0.05`, `REPEAT_CUSTOMER_RATE = 0.30`
  - atomic inventory decrement keyed by `inventory.id` with skip-on-zero
  - idempotency marker `sales.note = '[seed-s87]'`
  - `--dry-run` rolls back instead of committing

The wrapper invokes it with `--tenant 7 --count <random 5-15> --confirm`, plus
`--dry-run` if passed in. `sales_pulse.sh` now forwards `"$@"`.

**Verify (Тихол, manual):**

```bash
sudo -u www-data python3 /var/www/runmystore/tools/diagnostic/cron/sales_pulse.py --dry-run --count 200
# expected: exit 0, "[DRY-RUN] Seeded N sales", time-range today,
# inspect output line "Time range: ..." — sub-second timestamps,
# eyeball histogram → peak in 11-13 and 17-19
```

---

## REWORK QUEUE entries (for Шеф chat ingestion)

Verbatim entries — copy into the master REWORK QUEUE. Source: STRESS_BOARD ГРАФА 3.

### #54 — P1 — ai_insights UNIQUE key blocks new INSERTs

**File / table:** `ai_insights` table, UNIQUE `(tenant_id, store_id, topic_id)`
plus matching idempotent check at compute-insights.php:239-245.

**Symptom:** once an insight per `topic_id` exists, subsequent cron runs only
UPDATE the existing row. The signal count stays fixed regardless of how often
compute-insights runs. Stress system needs growth in row count to measure
"more vs fewer signals over time".

**Suggested fix path:** evaluate whether UNIQUE should be relaxed to allow a
windowed key (`tenant_id, store_id, topic_id, expires_at`) or whether the
"insight" concept should split into "signal" (append-only) + "current state"
(idempotent UPDATE). Bizdec needed before code.

### #55 — P2 — helpers.php:161 cooldown hides recently-seen insights

**File:** `helpers.php:161` — `shouldShowInsight()` cooldown logic.

**Symptom:** an insight a user has dismissed (or even just seen) is suppressed
from life-board for the cooldown window. Acceptable in production; harmful
during stress test where the test wants to confirm the same signal re-appears
on the next cron tick. Need a flag (env var? request header? config row?) to
disable cooldown when a stress run is active.

### #56 — P2 — helpers.php:170 urgency limits cap visible info insights

**File:** `helpers.php:170` — urgency caps `critical=2, warning=3, info=3`,
PLUS rule "if critical or warning is present, hide all info".

**Symptom:** the cap caps total displayed at ~8 signals. Stress test cannot
exercise the rendering of >8 signals or measure the "tail" of info insights.
Need a stress-mode override that lifts caps and shows everything.

### #57 — Phase B — admin/stress-board.php UI build

**Source:** STRESS_BUILD_PLAN Phase B. STRESS_BOARD.md is currently a flat
markdown file with 4 columns + archive. Phase B is to render it as an
admin-only HTML view (similar shape to admin/insights-health.php from S91)
with:
  - Column 1: "to test tonight" (write-from day chat, read-from cron)
  - Column 2: "results from last night" (write-from cron, read-from стрес/шеф)
  - Column 3: "to fix" (write-from стрес, read-from code chat)
  - Column 4: "fixed, retest" (write-from code, read-from cron)
  - Archive: 2-green-night-confirmed bugs

Owner-only gate. Dependency: STRESS_BOARD.md needs structured form (YAML or
DB table) before UI can render — currently it's free-text under headings.

---

## Files touched this session

| File | Change | Commit |
|------|--------|--------|
| `INVESTIGATION_REPORT.md` | append S92 verify section (~20 lines) | 16a43eb |
| `tools/diagnostic/tests/race_test_sale.py` | new harness (~120 lines) | 16a43eb |
| `tools/diagnostic/cron/sales_pulse.py` | rewrite (was 109 lines, now 53) | ebcba03 |
| `tools/diagnostic/cron/sales_pulse.sh` | 1-line edit to forward `"$@"` | ebcba03 |
| `docs/SESSION_S92_STRESS_DEPLOY_HANDOFF.md` | new (this doc) | (this commit) |

**NEVER touched (per session brief):** partials/ai-brain-pill.php,
partials/voice-overlay.php, life-board.php, products.php, chat.php,
ai-studio*.php, design-kit/*, delivery.php, orders.php,
docs/BUG_AUDIT_2026-05-02.md.

---

## Bugs found extra (not in STRESS_BOARD)

None. The session was tightly scoped to the 6 bugs already cataloged.

One observation worth noting (not a bug): `STRESS_BOARD.md` references line
numbers that have drifted since S91 visual migrations (sale.php "ред 132" →
actually 135, compute-insights.php "ред 235" → actually 236). Worth a sweep
to update line references in STRESS_BOARD next time it's touched.

---

## Time budget

| Phase | Spent | Budget |
|-------|-------|--------|
| Reading + plan | ~30 min | — |
| Implementation (race test, sales_pulse rewrite, doc) | ~50 min | — |
| Commits + handoff | ~10 min | — |
| **Total** | **~1.5h** | **2.5h** |

Under budget by ~1h — the savings come from FIX #1 + FIX #2 already being in
place from S90/S91; only FIX #3 needed real code change.

---

## Next session prerequisites (for Тихол + next chat)

Before the next stress chat picks up:

1. **`sudo crontab -u www-data tools/diagnostic/cron/runmystore-diagnostic.crontab`** — Тихол, blocking item #1.
2. **`sudo -u www-data tools/diagnostic/cron/sales_pulse.sh --dry-run`** — confirm exit 0.
3. **`sudo -u www-data python3 tools/diagnostic/tests/race_test_sale.py`** — confirm "PASS".
4. **Open `/admin/insights-health.php`** in browser as owner — confirm `home` row count ≥ 6.
5. **`git push origin main`** with valid GitHub credentials — push the 3 commits.

After all 5, the stress system is live: sales_pulse runs nightly @03:00,
weekly diag Mon, monthly diag 1st, summary email daily @08:30.

---

## Appendix: how this session diverged from the brief

The session brief asked me to install crontab, run race test, run SQL
verification, and rewrite 3 P0 bugs — most of these required either sudo or
DB credentials I cannot obtain from a `tihol` shell. I converted those into
manual prereqs above with exact commands, while completing all source-level
work (race test harness, sales_pulse rewrite, INVESTIGATION_REPORT update,
this handoff). FIX #1 and FIX #2 were verified to already be in place from
S90.RACE and S91.INSIGHTS_HEALTH respectively — no code change needed there,
which the brief acknowledged as a possibility ("verify дали наистина работи").
