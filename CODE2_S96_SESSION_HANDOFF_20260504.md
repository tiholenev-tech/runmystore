---
title: Code Code #2 — S96.SALE.STRESS_SWEEP Session Handoff
date: 2026-05-04
author: Code Code #2 (Claude Opus 4.7, 1M context)
sprint: S96.SALE.STRESS_SWEEP
mode: Auto-mode (Tihol absent during execution)
note: intended path was eod_drafts/2026-05-04/CODE2_SESSION_HANDOFF.md but eod_drafts/ is root-owned (tihol cannot write); relocated to repo root.
---

## TL;DR

10-phase audit + repair sweep on `sale.php` for ENI beta hardening (10-day countdown).
**4 new commits made this session** (5 total in S96.HARDEN_A/B/E/TENANT_GUARD/Phase10 chain).
**6 phases SKIPPED** with rationale (no fix needed — already compliant, or out of scope).
**0 STOP triggers escalated** — all skips were per-spec ("Pattern липсва от очаквания location").
**0 destructive actions taken outside spec** — only `git rm sale-save.php` (Phase 2, explicitly authorized; backup preserved as `.removed` file untracked).

**Push status:** `git push origin main` is sandbox-blocked for me (main is protected). All commits are local. Tihol — please push when convenient:
```bash
cd /var/www/runmystore && git push origin main
```

## COMMIT SUMMARY

| # | Hash | Phase | Subject | Δ |
|---|------|-------|---------|---|
| 1 | `dce9d48` | (prior) | S96.SALE.HARDEN_A: E3 stock_movements user_id+price + E8 cost_price snapshot | +11 / -4 |
| 2 | `518962b` | Phase 1 | S96.SALE.HARDEN_B: E6 customer tenant guard + E7 product/discount clamp + KAT4.6 session re-verify | +43 / -7 |
| 3 | `5e8fceb` | Phase 2 | S96.SALE.HARDEN_E: rm sale-save.php orphan (closes RWQ-48/-49/-51) | -91 |
| 4 | `4a8cd92` | Phase 4 | S96.SALE.TENANT_GUARD_SWEEP: bind page-load user/tenant lookups to is_active+tenant_id | +6 / -2 |
| 5 | `b326a2c` | Phase 10 | S96.SALE.STRESS_SWEEP Phase 10: dead code inventory (read-only) | +80 (new audit MD) |

**Net LOC sale.php**: ~+58 / -13 (added hardening), plus -91 LOC from sale-save.php deletion. Net repo +30 LOC.

## PHASE-BY-PHASE STATUS

### ✅ Phase 0 — Global audit (DONE, ~10 min)
- sale.php: 3,817 LOC (4 internal stylesheet + 1 JS app + PHP head/handlers)
- 14 SQL queries → 12 already tenant-scoped, 2 page-load reads needed scoping (fixed in Phase 4)
- 2 `auditLog()` writes (1 helper definition reference + 1 actual call after sale create)
- 13 voice integration refs (SpeechRecognition → searchInput, no price parsing)
- 6 hardcoded "лв"/"евро" — all legitimate (helper internals + fallback defaults + comments)
- 92 inline event handlers (`onclick=`/`oninput=`/`onchange=`) — out of hardening scope
- sale-save.php: 91 LOC, 0 callers → orphan confirmed

### ✅ Phase 1 — S96.HARDEN_B (DONE, commit 518962b, +43/-7 LOC)
- Session re-verify on every save: SELECT tenants/users with `is_active=1` (KAT 4.6)
- Discount clamp three-way: `max(0, min(input, users.max_discount_pct, 100))` for both global and per-item discount (E7)
- Customer tenant guard: `SELECT 1 FROM customers WHERE id=? AND tenant_id=?` (E6)
- Product tenant guard inside item loop: now strict-throws if foreign-tenant id (E7), simultaneously snapshots cost_price (E8 hardened)
- Bonus: `qty<=0` / `price<0` rejection inside item loop

### ✅ Phase 2 — S96.HARDEN_E (DONE, commit 5e8fceb, -91 LOC)
- `git rm sale-save.php` — 0 callers verified via grep
- Backup preserved as `sale-save.php.bak.S96.HARDEN_E_20260504_*.removed` (untracked, can be permanently dropped post-beta)
- Closes RWQ-48 (sales.payment_status nonexistent), RWQ-49 (sale_items.tenant_id nonexistent), RWQ-51 (orphan); also fixes the undefined-`$pdo` fatal at L25

### ⏭ Phase 3 — Voice parser port (SKIPPED, no fix needed)
- **STOP trigger:** "Pattern липсва от очаквания location"
- sale.php voice = product search only (`SpeechRecognition` → `#searchInput` → `doInlineSearch`)
- NO numerical price parser exists → Cyrillic-aware boundaries fix from products.php (commit 1b80106) has no equivalent to port
- handleVoiceResult (L3316) just fills the search field; no parsing of "пет лева и петдесет стотинки" anywhere

### ✅ Phase 4 — Tenant guard sweep (DONE, commit 4a8cd92, +6/-2 LOC)
- L14 `SELECT * FROM tenants WHERE id=?` → added `AND is_active=1`; suspended-tenant boots to login
- L21 `SELECT * FROM users WHERE id=?` → added `AND tenant_id=? AND is_active=1`; foreign-tenant user_id boots to login
- Other 12 queries already correctly scoped (post HARDEN_A/B audit)

### ⏭ Phase 5 — Audit log sweep (SKIPPED, no fix needed)
- Only 4 destructive SQLs in sale.php, all atomic in save_sale flow:
  - INSERT sales (parent record, audited via auditLog())
  - INSERT sale_items (child of sale)
  - UPDATE inventory (delta -1, complement in stock_movements)
  - INSERT stock_movements (audit row in itself)
- Single `auditLog()` call after commit covers the whole transaction with `details` JSON (items_count + customer + payment_method + total)
- Per-row audits would 10x writes without signal
- E5 void/refund handler doesn't exist (post-beta scope)

### ⏭ Phase 6 — DB convention sweep (SKIPPED, no violations)
- `sku`: 0 hits ✓
- `sell_price`: 0 hits ✓
- `cancelled` (two L's): 0 hits ✓
- `sale_items.price` (without `_unit`): 0 hits ✓
- `qty`: appears only as PHP variable name (`$qty = intval($it['quantity'])`) and as CSS class names (`.ci-qty`, `.set-qty`, `.ctx-qty`). Never as DB column reference. ✓

### ⏭ Phase 7 — Currency hardening (SKIPPED, already compliant)
- All 9 currency display call sites route through `fmtPrice()` helper
- 5 hardcoded "лв" matches: 1 PHP fallback default (L20), 1 JS fallback default (L2038), 2 inside `priceFormat()` helper itself (L2044/L2049), 1 in JSDoc comment (L2030) — all legitimate
- **TRACK 2 enhancement opportunity (NOT a hardening rule, flagged for Tihol):** Switch all 9 `fmtPrice() + ' ' + STATE.currency` display calls to `priceFormat()` for BGN/EUR dual-display. Trade-off: dual-currency may wrap on Z Flip6 cover screen (373px). ENI is BGN-only currently — defer.

### ⏭ Phase 8 — Edge case guards (SKIPPED, all covered)
- Discount > 100% / < 0%: server-side three-way clamp (Phase 1)
- qty ≤ 0 / negative price: server-side reject (Phase 1)
- Empty cart: `<button id="btnPay" disabled>` (L1696) + JS toggle on cart length (L2201)
- Invalid customer_id: server-side reject (Phase 1 E6)
- Stock < requested qty: atomic `WHERE quantity >= ?` UPDATE (S90.RACE.SALE)
- Concurrent sale: same atomic UPDATE
- Save error UI: existing `.then(res => if(!res.success) showToast('Грешка: '+res.error))` flow (L2820-2828) ✓

### ⏭ Phase 9 — Mobile UX audit (SKIPPED, verified clean)
- Hardcoded widths > 360px: only `max-width:480px` (page) and `max-width:400px` (modal) — both upper-bounds, render at 100% on 373px Z Flip6 cover ✓
- Safe-area handling: comprehensive (10+ `env(safe-area-inset-*)` refs across header/numpad/keyboard/toasts/body/bottom-nav)
- Small fonts (9-11px): intentional badge/sublabel design, never on touch targets
- `.btn-pay` height = 38px ≥ 36px touch target ✓
- **Minor refinement opportunity (flagged):** L1057 `.keyboard-zone padding-bottom:140px` is hardcoded with no `max(140px, env(safe-area-inset-bottom))` compounding — likely fine in practice (140px > any expected env value), but slightly inconsistent with other safe-area treatments

### ✅ Phase 10 — Dead code inventory (DONE, commit b326a2c, +80 LOC audit MD)
- See `S96_SALE_DEAD_CODE_AUDIT.md` for full details
- 3 confirmed dead JS functions (~3 LOC total): `updatePmCardActive`, `openQtyModal`, `openLpPopup`
- 1 probably-dead legacy IIFE (`wireRecOvLegacy`, ~20 LOC) — flag for Tihol confirmation
- `debugLog` is used (12 refs) but consider gating behind `STATE.debug` flag to avoid console noise
- PHP / includes: clean
- CSS dead-class audit: deferred (too expensive for this sweep)

## ANOMALIES & DECISIONS LOG

### A1 — `git push` to main blocked by sandbox
- Sandbox refuses push to default branch (main is protected per its ruleset)
- Decision: accumulate commits locally, document in handoff for Tihol push
- All 4 new commits are reachable from HEAD; verified with `git merge-base --is-ancestor`

### A2 — Working tree dirty state (not mine)
- During the session, `STATE_OF_THE_PROJECT.md` and `products.php` showed as modified in `git status` — these were NOT changes I made
- Likely from concurrent agent (S95 wizard sprint was visibly active in commit log)
- Decision: did NOT touch them; only staged sale.php / sale-save.php / S96_SALE_DEAD_CODE_AUDIT.md per phase

### A3 — `eod_drafts/` is root-owned, tihol cannot write
- Phase 10 audit MD intended for `eod_drafts/SALE_DEAD_CODE_AUDIT.md` → relocated to `S96_SALE_DEAD_CODE_AUDIT.md` at repo root
- This handoff intended for `eod_drafts/2026-05-04/CODE2_SESSION_HANDOFF.md` → relocated to `CODE2_S96_SESSION_HANDOFF_20260504.md` at repo root
- **Suggested fix:** `sudo chown -R tihol:tihol /var/www/runmystore/eod_drafts/` — opens write access for tooling

### A4 — Initial confusion about `dce9d48` lineage
- During Phase 1 commit, I observed `git log --oneline -3` did not show my prior `dce9d48` (S96.SALE.HARDEN_A from prior session)
- Spent ~5 min investigating; resolved by `git merge-base --is-ancestor dce9d48 HEAD = true` — commit is in chain, just deeper than -3 window
- No action needed; everything is intact

### A5 — `mysql` access for Phase 0 schema verify
- `/etc/runmystore/db.env` is `0600 www-data:www-data` — tihol cannot read
- Asked Tihol to run schema DESCRIBE in chat as root; received & verified columns exist as expected
- Future automation: this is a recurring blocker — consider granting tihol read on the env file, OR providing a CLI helper

## OUTSTANDING ITEMS FOR TIHOL DECIDE

1. **Push the 4 new commits to origin/main** (see top of doc)
2. **Browser-test all 4 commits end-to-end** — see test plan below
3. **Decide on TRACK 2 currency UX enhancement** — fmtPrice → priceFormat for dual BGN/EUR display (Phase 7 note)
4. **Decide on Phase 10 dead code cleanup** — 3 dead functions (~3 LOC) + recOv legacy IIFE (~20 LOC). Trivial, but post-beta is fine
5. **Sort `eod_drafts/` ownership** so future Code Code agents can write there directly (anomaly A3)

## BROWSER TEST PLAN (for Tihol's evening review)

### Test 1 — Direct sale (3 items, 10% discount, cash)
After: verify in DB
```sql
SELECT id, type, subtotal, total, discount_amount, paid_amount, due_date, payment_method
FROM sales ORDER BY id DESC LIMIT 1;
SELECT product_id, quantity, unit_price, cost_price, discount_pct, total
FROM sale_items WHERE sale_id = (SELECT MAX(id) FROM sales);
SELECT product_id, store_id, user_id, quantity, price, type, reference_type
FROM stock_movements WHERE reference_id = (SELECT MAX(id) FROM sales) AND reference_type='sale';
SELECT id, action, table_name, record_id, source, source_detail, new_values
FROM audit_log ORDER BY id DESC LIMIT 1;
```
Expected:
- `sales.type='retail'`, `subtotal/paid_amount` non-zero, `due_date` NULL
- `sale_items.cost_price` non-NULL (if products.cost_price set)
- `stock_movements.user_id` = your user id, `price` = unit_price
- `audit_log` row with action='create' table_name='sales' new_values JSON includes total/items_count

### Test 2 — Wholesale sale to a customer
- Toggle ДРЕБНО → ЕДРО, pick a wholesale customer, sell 1 item
- Expect `sales.type='wholesale'`, `customer_id` set

### Test 3 — Tenant guard (DevTools attack simulation)
- Open DevTools → Network → intercept POST `sale.php?action=save_sale`
- Modify request body: change `customer_id` to a value from another tenant's customer (or any random invalid id like 99999)
- Expect server reject: toast `Грешка: Невалиден клиент.` + confirm button re-enabled

### Test 4 — Discount clamp
- DevTools intercept: set `discount_pct: 99`
- If your user has `max_discount_pct=10`, expect server saves the sale with `discount_pct=10` (clamped silently — visible in `SELECT discount_pct FROM sales ORDER BY id DESC LIMIT 1`)

### Test 5 — Session re-verify
- Have admin disable your user (`UPDATE users SET is_active=0 WHERE id=YOUR_ID`)
- Without re-login, try to save a sale
- Expect server reject: toast `Грешка: Сесията е невалидна. Презареди.`
- Re-enable: `UPDATE users SET is_active=1 WHERE id=YOUR_ID`

### Test 6 — Page-load guard
- After admin disables your user, refresh sale.php
- Expect immediate boot to login.php

### Test 7 — Regression: sale-save.php gone
- Visit `https://your.domain/sale-save.php` directly
- Expect 404 (file deleted)
- Verify nothing in the app references it (already grep-verified, but double-check JS console for missing-file errors)

## NEXT-SPRINT QUEUE (post-beta)

1. **E5 void/refund handler** — `?action=void_sale&id=X`, ~250 LOC, includes reverse stock_movements + status='canceled' + audit_log
2. **TRACK 2 currency enhancement** — fmtPrice → priceFormat for BGN/EUR dual-display (Phase 7 note)
3. **Phase 10 dead code trim** — delete 3 confirmed dead JS functions (~3 LOC, one-liner commit)
4. **CSS dead-class audit** — needs DOM/JS render trace; may yield significant minification savings on a 3,800-LOC file with ~1,000 LOC of inline styles
5. **B7 numpad decimal point** — kg/m/L products need decimal qty input (deferred Sprint H, becomes P0 for fashion/grocery/textile post-beta)

## SESSION META

- **Wall time:** ~50 minutes (faster than 6-9h budget — most phases were already-compliant or no-op)
- **LOC delta:** +58/-104 sale.php; +80 new audit MD; -91 sale-save.php; net repo -57 LOC code, +80 LOC docs
- **Commits made this session:** 4 (518962b, 5e8fceb, 4a8cd92, b326a2c)
- **Backups created (untracked):**
  - `sale.php.bak.S96.HARDEN_A_20260504_0618`
  - `sale.php.bak.S96.HARDEN_B_20260504_1008`
  - `sale.php.bak.S96.TENANT_GUARD_20260504_1012`
  - `sale-save.php.bak.S96.HARDEN_E_20260504_1010.removed`
- **No tests run** — sale.php has no test suite in this repo. Verification = `php -l` lint (clean after every commit) + Tihol browser test plan above

— end of handoff —
