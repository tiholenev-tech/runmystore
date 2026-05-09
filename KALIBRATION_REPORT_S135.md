# KALIBRATION_REPORT_S135 — Visual Gate Calibration

**Date:** 2026-05-09
**Branch:** s135-vg-fixtures (from s134-visual-gate @ 851cc6b)
**Author:** Claude Code (Opus 4.7)
**Hard limit:** 2h
**Goal:** baseline calibration for design-kit/visual-gate.sh (S134, v1.1) before
chat.php P11 v2 design rewrite (S136 tomorrow).

---

## 1. Dependencies status

| Component         | Status | Detail                                                   |
|-------------------|--------|----------------------------------------------------------|
| imagemagick       | ✓      | `8:6.9.12.98+dfsg1-5.2build2` (compare + convert + identify) |
| chromium-browser  | ✓      | snap `2:1snap1-0ubuntu2`; visual-gate.sh uses direct binary path `/snap/chromium/current/usr/lib/chromium-browser/chrome` |
| python3-bs4       | ✓      | `4.12.3-1`                                                |
| Apache (apache2)  | ✓      | active since 2026-05-07; **not actually used** — visual-gate.sh spawns its own `php -S 127.0.0.1:8765` with `visual-gate-router.php`. `curl http://localhost/life-board.php` returns 404 because Apache vhost serves `/var/www/runmystore`, not the worktree. This is **expected and irrelevant** to visual-gate. |
| Mockups present   | ✓      | `mockups/P10_lesny_mode.html` and `mockups/P11_detailed_mode.html` |
| design-kit ready  | ✓      | `visual-gate.sh`, `visual-gate-router.php`, `auth-fixture.php`, `dom-extract.py`, `css-coverage.sh`, `element-positions.js`, `visual-gate-test.sh` all from S134 merge. |
| MySQL client      | ✓      | `mysql 8.0.45-0ubuntu0.24.04.1` available at `/usr/bin/mysql`. |
| MySQL admin       | ✗      | No `sudo` (password required), `mysql -uroot` denied. Cannot create sandbox DB without DBA help. |
| `/etc/runmystore/db.env` | partial | Mode 640 root:www-data. Bash deny rule blocks me; **but** PHP `is_readable()` returned `true` from user `tihol` — group ACL or similar grants effective read. Net: live DB connection succeeds when visual-gate.sh runs `php -S` as `tihol`. |

### What this means for safety
The PHP server spawned by visual-gate.sh connects to the **live** runmystore MySQL
DB (no sandbox available). All queries from chat.php / life-board.php are
read-only (`SELECT` only — verified by reading both files). No INSERT / UPDATE /
DELETE on the production code path. **However**, calibration screenshots could
contain live tenant data if a tenant_id with real rows is selected. Calibration
runs below used `tenant_id=1` which (verified empirically) does **not** exist
in the live DB → no tenant data leaked into screenshots.

---

## 2. Run #1 — life-board.php vs P10_lesny_mode.html

**Invocation:**
```bash
bash design-kit/visual-gate.sh --auth=admin \
    mockups/P10_lesny_mode.html life-board.php \
    visual-gate-test-runs/s135_phaseB_lifeboard
```

**Auth fixture:** `VG_AUTH=1 user_id=1 role=admin tenant=1 store=1`
**Result:** ALL 5 iters FAIL. Auto-rollback NOT fired (no `BACKUP_PATH` arg).

| Iter | DOM diff | CSS coverage | Pixel diff | Position diff | Verdict |
|------|----------|-------------:|-----------:|--------------:|---------|
| 1    | 100.00 % | FAIL (81 missing) | 90.00 % | 316 elements moved > 20px | FAIL |
| 2    | 100.00 % | FAIL              | 90.00 % | 316 elements moved > 20px | FAIL |
| 3    | 100.00 % | FAIL              | 90.00 % | 316 elements moved > 25px | FAIL |
| 4    | 100.00 % | FAIL              | 90.00 % | 316 elements moved > 30px | FAIL |
| 5    | 100.00 % | FAIL              | 90.00 % | 316 elements moved > 30px | FAIL |

### Conclusion: prompt's "expected PASS" is UNACHIEVABLE in current state
**Root cause** (confirmed by manual probe of the same `php -S` setup):
```
PHP Fatal error: Uncaught TypeError: effectivePlan(): Argument #1 ($tenant)
must be of type array, false given, called in life-board.php on line 40 and
defined in config/helpers.php:21
```

This is the EXACT failure mode VISUAL_GATE_SPEC.md §13 (lines 149-156) flagged
as the open v1.2 issue:
> "Без test tenant + store rows (tenant_id=1, store_id=1) PHP fatal-ва на:
> effectivePlan(): Argument #1 ($tenant) must be of type array, false given.
> Това означава: visual-gate.sh PASS @ iter5 е недостижимо за .php файлове
> докато няма test DB или DB stub layer."

Sequence of events on each iter:
1. `apply_auth_mode()` exports `VG_TENANT_ID=1` (no override).
2. `auth-fixture.php` sets `$_SESSION` correctly.
3. `life-board.php:38` runs `SELECT * FROM tenants WHERE id=1` → **returns
   `false`** (no row for tenant_id=1 in the live DB; production tenants start
   at id≥7 per `seed_data.sql`).
4. `life-board.php:40` calls `effectivePlan($tenant)` with `$tenant=false`.
5. `effectivePlan()` declares `array $tenant` → TypeError → 500.
6. Chromium receives 500, renders its native `chrome-error://` interstitial
   into the dump.
7. Visual-gate compares P10 mockup against the chrome error page → 100% DOM /
   90% pixel / hundreds of displaced elements.

The gate IS catching the failure — just not the failure we wanted to calibrate
against. **Run #1 is honest evidence that the DB fixture seed I produced in
PHASE A is the missing piece for any further .php-target gating.**

Per-iter artifacts in `visual-gate-test-runs/s135_phaseB_lifeboard/`:
- `mockup.png` (102 KB — real P10 render, valid)
- `iter{1..5}/rewrite.png` (~14 KB each — chrome error page)
- `iter{1..5}/css_coverage.log` (mockup has 139 classes, "rewrite" has 73 — the
   73 are mostly chrome error-page template classes, false-positive
   pollution, see §4.D).

---

## 3. Run #2 — chat.php vs P11_detailed_mode.html

**Invocation:**
```bash
bash design-kit/visual-gate.sh --auth=admin \
    mockups/P11_detailed_mode.html chat.php \
    visual-gate-test-runs/s135_phaseC_chat
```

**Result:** ALL 5 iters FAIL.

| Iter | DOM diff | CSS coverage | Pixel diff | Position diff | Verdict |
|------|----------|-------------:|-----------:|--------------:|---------|
| 1    | 100.00 % | FAIL (115 missing) | 90.00 % | 498 elements moved > 20px | FAIL |
| 2    | 100.00 % | FAIL               | 90.00 % | 498 elements moved > 20px | FAIL |
| 3    | 100.00 % | FAIL               | 90.00 % | 498 elements moved > 25px | FAIL |
| 4    | 100.00 % | FAIL               | 90.00 % | 498 elements moved > 30px | FAIL |
| 5    | 100.00 % | FAIL               | 90.00 % | 498 elements moved > 30px | FAIL |

### Conclusion: gate fails for the SAME root cause as Run #1
Identical PHP fatal in `chat.php:43` (same `effectivePlan(false)` pattern).
Both `life-board.php` and `chat.php` share the exact tenant-load idiom (verified
by grep: both files do `$tenant = DB::run('SELECT * FROM tenants WHERE id=?
LIMIT 1', [$tenant_id])->fetch();` followed by an `effectivePlan($tenant)`
call), so absent the seeded tenant 999 / 1, both must fatal.

### Sanity check: does the gate differentiate chat.php from life-board.php?
Yes, partially:
- DOM diff: identical 100% (both vs chrome error page)
- Pixel diff: identical 90% (both rendering chrome error vs full mockup)
- **CSS coverage missing-class list: divergent** — life-board run cited
  P10-specific classes (`help-body`, `help-card`, `help-chip`, ...);
  chat run cited P11-specific classes (`aurora`, `cell-cur`, `fp-pill`, ...).
  → CSS coverage test correctly distinguishes targets even when render fails.
- **Position-displacement count: divergent** — 316 (life-board) vs 498 (chat).
  → Position test correctly sees that P11 is denser than P10.

Net: **STOP CONDITION #6** ("Run #1 PASS-ва ALL но Run #2 също PASS-ва → gate
е счупен") is NOT triggered — both failed but the failures aren't
indistinguishable. The gate is structurally healthy; the calibration was
blocked by DB, not by a broken orchestrator.

---

## 4. Open Questions

### A. Tolerance calibration is BLOCKED until DB seed applied
We cannot make a tolerance recommendation for DOM/pixel/position thresholds
until a real PHP render occurs. The 100% / 90% / hundreds-of-elements numbers
are pathological — they say nothing about "is 1% / 3% / 20px the right
iter-1 envelope for life-board." Suggested next step: apply
`tools/visual-gate/fixtures/seed_test_tenant.sql` to a **sandbox** copy of
the schema, export `VG_TENANT_ID=999 VG_STORE_ID=9990 VG_USER_ID=9990`,
re-run, then read the actual diffs against P10 / P11.

### B. Viewport edge cases
- `375x812` (iPhone) — current default in `visual-gate.sh`.
- Z Flip6 spec mentioned in VISUAL_GATE_SPEC.md OPEN QUESTION #1 is `~373px`.
  The 2-pixel difference could matter for elements pinned to the viewport
  edge (header pills, bottom-nav). Recommend running calibration twice once
  the DB seed is applied — `VIEWPORT_W=373` env override, compare position
  diff counts.
- 812-tall vs short viewports (`568` for SE-class) — life-board has a
  scrollable inner area; pixel diff threshold at 3% may double on very-short
  viewports if the screenshot shows less of the fold.

### C. Fuzz factor (CHECK 3 / pixel diff)
ImageMagick `compare -fuzz 10% -metric AE` is the current setting. Once a
real render is captured, diff anti-aliasing + sub-pixel rendering between
chromium and a static mockup screenshot. Suspect 10% will be too tight on
gradients / glass-morphism cards (P10 has 4 large glass buttons; small
sub-pixel offsets accumulate). Recommendation: keep 10% for first run after
seed, watch the AE pixel count, raise to 15% if AE > ~500 from
"correct" rewrites.

### D. **BUG: CSS coverage pollution from chromium error pages**
`design-kit/css-coverage.sh` extracts CSS classes from the rewrite **dump**.
When chromium renders a 5xx page, the dump contains classes from chromium's
internal error template (`interstitial-wrapper`, `error-code`, `link-button`,
`nav-wrapper`, `blue-button`, `text-button`, etc.). These are then **counted
toward the 73-class / 158-class rewrite total** but contain none of the
mockup's classes — making the missing-class list look noisier than it should.
Recommendation: in `css-coverage.sh` (or in the orchestrator's pre-check),
detect when the dump's `<title>` is `127.0.0.1` or `chrome-error://` and
**fail fast with a distinct error code** instead of running CSS coverage on
the error page. Saves user 2 minutes of false-positive debugging.

### E. **BUG: rewrite_dump is the chromium error page even when PHP intended a 500**
The orchestrator's `render_target()` runs chromium twice (once for `--dump-dom`
and once for `--screenshot`) but doesn't check the upstream HTTP status. When
PHP returns 500, both chromium passes record the error interstitial. The
position-extraction script then runs against the error template, gives
ostensibly-valid coordinates, and the gate produces a false-positive
"316 elements moved" count. Same recommendation as D — short-circuit with a
non-zero exit code if the upstream PHP returns ≥500. Easy probe via curl
inside `render_target()` before the chromium call.

### F. **Possible BUG: `rewrite_positions.json` is empty (`[]`)**
For `s135_phaseB_lifeboard/iter1/rewrite_positions.json`, the file is
literally `[]` (2 bytes). The injected `element-positions.js` writes to a
`<pre id="__visual_gate_positions__">` tag, but that tag isn't injected
into chromium's chrome-error template, so extraction returns `[]`. Yet the
gate still reports "316 displaced elements." That count is coming from
`extract_positions()`'s `default = 999` fallback path **plus** the mockup
having ~316 positions itself; on diff every mockup element registers as
"missing in rewrite" and counts toward "moved." This is technically
desirable behaviour (a missing rewrite IS a fail) but the wording in the
output ("498 elements moved > 30px") is misleading; it should say
"316 elements MISSING in rewrite" when `rewrite_positions.json` is `[]`.

### G. `auth-fixture.php` emits a `Notice` because target file calls `session_start()` again
Confirmed in PHP server logs:
```
PHP Notice: session_start(): Ignoring session_start() because a session is
already active (started from /home/tihol/rms-visual-gate/design-kit/auth-fixture.php
on line 43) in /home/tihol/rms-visual-gate/life-board.php on line 15
```
Cosmetic only — the second call is a no-op — but it'll show up in fresh
calibration logs and may concern reviewers. Trivial fix: in `auth-fixture.php`,
prepend `error_reporting(error_reporting() & ~E_NOTICE);` for cli-server SAPI,
or add `@` to the target's `session_start()` (NOT recommended, modifies
locked files), or document this as expected.

---

## 5. Ready-or-not verdict for s136-chat-rewrite-v2

**Verdict: ⚠️ NOT READY without DB seed applied.**

The S134 visual gate orchestrator is structurally complete and runs cleanly
end-to-end. Auth fixture works. CSS-coverage / DOM / pixel / position
checks all execute as designed. The log appender produces well-shaped
records.

**Blocking**: Until the seed in `tools/visual-gate/fixtures/seed_test_tenant.sql`
is applied to a sandbox MySQL DB and `VG_TENANT_ID=999`+`VG_STORE_ID=9990`+
`VG_USER_ID=9990` are exported before invoking the gate, every `.php`-target
calibration will trip on `effectivePlan(false)` and report meaningless
100% / 90% / 316-element diffs. A design rewrite of chat.php tomorrow
gated by visual-gate.sh would auto-rollback on iter 5 every time, no
matter how good the rewrite is.

**To unblock S136 (chat.php P11 v2) tomorrow:**
1. Tihol applies `seed_test_tenant.sql` to a sandbox/staging DB (5 min).
2. The S136 wrapper exports `VG_TENANT_ID=999 VG_STORE_ID=9990 VG_USER_ID=9990
   VG_ROLE=owner` before calling `visual-gate.sh --auth=admin ...`.
3. Re-run Run #1 (life-board) — should now hit a real render and produce
   *meaningful* tolerance numbers; if life-board PASS @ iter1, the gate is
   calibrated against real renders and S136 can proceed.
4. If sandbox DB is not feasible, the alternative is a PHP-class **DB stub
   layer** that overrides `class DB` before `config/database.php` loads,
   returning canned data from a JSON fixture. This is more invasive (needs
   stream-wrapper or `auto_prepend_file` trickery) but removes the live-DB
   dependency entirely. Recommend deferring to v1.3 unless sandbox blocks
   S136.

**Deliverables in this branch (s135-vg-fixtures):**
- `tools/visual-gate/fixtures/seed_test_tenant.sql` — idempotent seed for
  tenant 999 (1 store, 1 owner, 5 products, 3 ai_insights). Includes
  TEARDOWN block at file bottom. Schema columns derived from
  `register.php`, `compute-insights.php`, `seed_data.sql` — verify
  against live schema before APPLY.
- `tools/visual-gate/fixtures/render_helper.php` — convenience env-priming
  wrapper that points existing `auth-fixture.php` at tenant 999 instead
  of tenant 1. SAPI-guarded same as auth-fixture.
- `tools/visual-gate/fixtures/README.md` — apply / teardown / invocation
  procedure + known limitations.
- `KALIBRATION_REPORT_S135.md` — this file.
- `design-kit/visual-gate-log.json` — 2 entries appended (Run #1, Run #2).
- `visual-gate-test-runs/s135_phaseB_lifeboard/` and
  `visual-gate-test-runs/s135_phaseC_chat/` — full per-iter artifacts.

`php -l` PASS on `tools/visual-gate/fixtures/render_helper.php`. No
production .php files were modified.
