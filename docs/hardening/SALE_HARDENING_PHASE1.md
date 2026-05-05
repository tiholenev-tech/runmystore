# SALE_HARDENING_PHASE1 — Internal hardening sweep before live sales testing

**Spec:** `S97.SALE.HARDENING_PHASE1` (Standing Rule #29 — Module Hardening Protocol Phase 1)
**Date:** 2026-05-05
**Branch:** `main`
**Range:** `7b52853..c5373e5`
**Module:** `sale.php` (+ `config/helpers.php` for the new generic CSRF helper)
**Cumulative LOC:** +354 / -99 across 2 files

---

## Phase status

| # | Phase | Status | Commit | LOC ± |
| - | --- | --- | --- | --- |
| 0 | Audit existing protections | DONE (read-only) | — | — |
| 1 | Stock guard (FOR UPDATE + structured envelope) | **DONE** | `72afd06` | +95 / −5 |
| 2 | Race condition (DB::tx() migration) | **DONE** | `a15029c` | +78 / −64 |
| 3 | Discount guards reinforcement | **DONE (partial)** + flagged | `c16c59d` | +18 / −2 |
| 4 | CSRF tokens | **DONE** | `e56bdfb` | +50 / −2 |
| 5 | Rate limiting | **DONE** (re-scoped) | `037308d` | +38 / −0 |
| 6 | Input validation sweep | **DONE** | `1e703a8` | +26 / −7 |
| 7 | Error handling sweep | **DONE** | `abed690` | +56 / −35 |
| 8 | Stock_movements audit trail verify | **VERIFIED**, no code change | — | 0 |
| 9 | Security headers | **DONE (partial)** + flagged | `c5373e5` | +9 / −0 |
| 10 | Documentation | **DONE** (this file) | (next) | — |

`sale.php`: 3857 → 4087 lines.

---

## What was already there (Phase 0 audit)

Inventory of existing protections found during the read-only sweep:

- ✅ Atomic CAS stock decrement: `UPDATE inventory ... AND quantity >= ?` with `rowCount() === 0` guard.
- ✅ Manual transaction wrapper: `beginTransaction / commit / rollBack`.
- ✅ Tenant guards: customer (`sale.php:155`), product (`sale.php:208`), session re-verify against live DB on every save (`sale.php:175`).
- ✅ Per-user `max_discount_pct` clamp (`sale.php:200`).
- ✅ `auditLog()` helper (`config/helpers.php:425`) writes `audit_log` row outside the transaction.
- ✅ `DB::tx()` helper with deadlock retry + savepoint nesting (`config/database.php:116`).
- ✅ `qty <= 0` / `price < 0` rejection.
- ❌ No CSRF (the `aibrain_csrf_token()` helper exists but is scoped to the voice flow only).
- ❌ No rate limiting on any endpoint.
- ❌ No security headers on the page itself (only inside ai-brain JSON endpoints).
- ❌ Stock check returned only a generic message — the UI couldn't offer "sell only N available".

---

## What changed

### Phase 1 — Stock Guard (`72afd06`)

- New `StockException` class carrying `product_id, product_name, available, requested`.
- Inserted `SELECT quantity FROM inventory ... FOR UPDATE` before the existing CAS decrement, inside the same transaction. The lock holds until commit/rollback, which serialises concurrent sellers and multi-tab on the same SKU.
- Fetch now also pulls `products.name` so the JSON envelope can name the SKU in toasts.
- `catch (StockException)` → 200 with `{success:false, err:'stock', product, available, requested}`. The CAS-only fallback throw remains as defence-in-depth.
- `catch (Throwable)` upgrade: PDO/Runtime exceptions log full detail server-side and respond with a generic message. User-thrown business exceptions still surface verbatim.
- New `handleStockShortage(res)` JS in the confirmPayment flow: prompts the seller, clamps the cart line to the available count, and retries `confirmPayment()`.

### Phase 2 — Race Condition (`a15029c`)

- Replaced `$pdo->beginTransaction(); … $pdo->commit();` with `DB::tx(function ($pdo) use (...) { ... })`.
- Sale path now inherits exponential-backoff retry on MySQL 1213 (deadlock) / 1205 (lock timeout) without the seller seeing a confusing error.
- Closure returns `[$sale_id, $total, $discount_amount, $paid_amount]` for the post-commit `auditLog()` call. No side effects inside the closure (idempotency ok per `DB::tx()` docs at `config/database.php:101`).
- Foreach body re-indented one level to live inside the closure (cosmetic but kept readable).

### Phase 3 — Discount Clamp Surfacing (`c16c59d`)

- Server-side clamp at `sale.php:200` now records whether it activated and reports `notice='discount_clamped'` plus `discount_cap` in the success envelope. UI toasts after the green flash so the seller knows why the total differs from what they typed.
- "Per-item discount cannot exceed unit_price" is mathematically equivalent to `discount_pct <= 100` (already enforced by the existing 3-way clamp).

### Phase 4 — CSRF Tokens (`e56bdfb`)

- New generic helpers in `config/helpers.php`:
  - `csrfToken()` — mints (or returns) a 32-byte hex token in `$_SESSION['app_csrf']`. Storage key is independent of `aibrain_csrf` so the two flows don't trample each other.
  - `csrfCheck($token)` — `hash_equals` constant-time compare.
- New `sale_csrf_guard_or_die()` runs at the top of `save_sale` and `refetch_prices`. Returns `403 + {err:'csrf'}` on mismatch.
- Token exposed to JS as `RMS_CSRF` and attached as `X-CSRF-Token` on both POST fetches.
- UI catches `err:'csrf'` and reloads to remint (covers "session expired mid-shift").

GET endpoints (`quick_search`, `barcode_lookup`) are deliberately **not** CSRF-guarded — they read tenant-scoped data and CSRF gives no real protection on idempotent reads.

### Phase 5 — Rate Limiting (`037308d`)

Spec asked for `cart_add` 60/min, but cart is client-side. The actual abuse vectors are `save_sale` (writes) and the search endpoints (DB load). New `sale_rate_limit_or_die($bucket, $cap, $window)` is a sliding-window counter in `$_SESSION`:

- `save_sale` → 30/min (no realistic POS rate approaches this).
- `search` bucket (shared by `quick_search` + `barcode_lookup`) → 240/min (≈ 4 req/sec — covers fast typers and barcode scanner cadence).
- 429 + `Retry-After` + `{err:'rate_limit', retry_after}` envelope. Frontend toasts the cool-off and re-arms the confirm button.

### Phase 6 — Input Validation (`1e703a8`)

Walked every `$_GET / json_decode` read in `sale.php`:

- `quick_search.q` — `mb_substr` cap 64.
- `barcode_lookup.code` — `substr` cap 64; reject empty.
- `refetch_prices.product_ids` — `array_slice 200` + `array_unique` to bound the `IN(?,?,...)` query.
- `save_sale.payment_method` — whitelist `{cash, card, bank_transfer, deferred}`, default `cash`.
- `save_sale.received` — floor at 0 (was unguarded `floatval`, allowed NaN/-N).
- `save_sale.items` — reject if > 200 (memory + transaction-time guard).
- `save_sale` top-level — reject non-array JSON body.

`paid_amount` and `due_date` are derived server-side at `sale.php:188-190` and never read from `$_POST`, so the spec's "validate paid_amount/due_date" items have no attack surface here.

### Phase 7 — Error Handling (`abed690`)

`quick_search`, `barcode_lookup`, `refetch_prices` now wrap their DB call in `try/catch (Throwable)`. Full exception goes to `error_log`; response body is the safe shape the caller already handles (`[]`, `null`, `{}`) plus HTTP 500. PDO messages no longer leak to the network.

`save_sale` already had a hardened catch from Phase 1.

### Phase 8 — Stock Movements Verify (no code change)

Spec checklist vs. the live `INSERT INTO stock_movements` at `sale.php:268`:

| Column | In INSERT | Notes |
| --- | --- | --- |
| `tenant_id` | ✅ | |
| `store_id` | ✅ | |
| `product_id` | ✅ | |
| `user_id` | ✅ | (S96.HARDEN.E3) |
| `quantity` | ✅ | |
| `price` | ✅ | (S96.HARDEN.E3, margin/dispute audit) |
| `type` | ✅ | `'out'` |
| `reference_type` | ✅ | `'sale'` |
| `reference_id` | ✅ | sale_id |
| `created_at` | ✅ | NOW() |
| `reason` | ❌ NOT IN LIVE SCHEMA | spec line item — column does not appear in `delivery.php`'s mirror INSERT either |
| `notes` | ❌ NOT IN LIVE SCHEMA | same |

Mirror INSERT at `delivery.php` uses the same column set, so the schema is consistent. **R4 forbids ALTER in auto-mode** — flagged below if the team wants `notes`/`reason` columns.

### Phase 9 — Security Headers (`c5373e5`)

Top-of-file headers on `sale.php`:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(self), microphone=(self), geolocation=(self)`

CSP **deliberately omitted** — `sale.php` uses inline `<script>`, inline CSS, Google Fonts, and ships inside a Capacitor APK on `file://`. A strict CSP without nonces would break both. Flagged below for a separate pass.

---

## Outstanding items / flags for Tihol's Phase 2 (AI fan-out)

### Schema gaps (not changed in auto-mode per R4)
1. **`tenants.max_discount_pct`** — spec asks to cap discount per-tenant; column does not exist. Currently the per-user `users.max_discount_pct` is the only cap.
2. **`tenants.allow_negative_stock`** — spec asks for a per-tenant override; column does not exist. Stock guard is unconditional.
3. **`stock_movements.reason` / `stock_movements.notes`** — spec asks to verify these are populated; columns are not present in the live INSERT pattern.

### Deferred design work
4. **Content-Security-Policy** for `sale.php` + Capacitor APK — needs nonce-based design + fonts-aware policy.
5. **Persistent rate limit** — current limit is per-session; an attacker who scrambles sessions can still flood. Long-term fix is APCu/Redis IP+session bucket.
6. **`DB::tx()` retry observability** — when `DB::tx` retries a sale, we silently succeed. Adding a counter to `auditLog`'s `new_values` would give visibility on contention.

### Push status
- **All commits live on `main` locally** (8 hardening commits + 1 Phase 10 doc).
- **Push to `origin main` was denied by the harness** — the user/operator should run `git push origin main` manually after review.

---

## Browser test plan

> Run after pulling these commits on staging. Each test below assumes a fresh seller session.

### Stock Guard (Phase 1)

1. **Single-tab oversell** — Set `inventory.quantity = 3` for product X. Add 5 of X to cart. Tap **Плати → Потвърди**.
   *Expect:* JS confirm "Налични 3 (поискани 5). Да продам 3 бр?"; clicking OK sells 3 and clears the cart.
2. **Out of stock** — Set `inventory.quantity = 0`. Try to sell.
   *Expect:* toast "✗ "X" — наличност 0. Премахни от количката." Cart preserved.
3. **Negative stock blocked** — Manually set `inventory.quantity = -2`. Try to sell 1.
   *Expect:* same as #2.

### Race Condition (Phase 2)

4. **Multi-tab race** — Two browser tabs, same seller, `inventory.quantity = 1` for X. Add 1 of X to each cart. Click confirm in tab A and tab B simultaneously.
   *Expect:* one tab succeeds with sale, the other gets "Налични 0 (поискани 1)" with the offer-OK pathway. `inventory.quantity = 0` (never -1).
5. **Two sellers race** — same setup as #4 but two different user sessions on two devices. Same expectation.

### Discount Clamp (Phase 3)

6. **Discount above user cap** — Seller has `users.max_discount_pct = 30`. Open DevTools, override `STATE.discountPct = 80`, confirm sale.
   *Expect:* sale completes; toast "Отстъпка ограничена до 30%" appears after the green flash; `sales.discount_pct = 30`.

### CSRF (Phase 4)

7. **Missing token** — In DevTools, override the X-CSRF-Token header to empty before confirming sale.
   *Expect:* 403 + `{err:'csrf'}` + UI auto-reload.
8. **Wrong token** — Override to `"deadbeef"`.
   *Expect:* same.

### Rate limit (Phase 5)

9. **Save flood** — Script 35 sales in 60s (inside DevTools console with `for` loop).
   *Expect:* first 30 succeed, the rest 429 with `Retry-After` and toast.

### Input validation (Phase 6)

10. **Items overflow** — POST `items` array of 250 entries.
    *Expect:* `{success:false, error:'Прекалено много артикули в продажба (макс. 200).'}`.
11. **Bad payment_method** — POST `payment_method:'wire_money'`.
    *Expect:* falls back to `cash` (no error); `sales.payment_method = 'cash'`.
12. **Negative received** — POST `received:-100`.
    *Expect:* sale completes (received is display-only); server uses `0`.

### Error handling (Phase 7)

13. **DB outage simulated** — `service mysql stop` for 5 seconds, then trigger a search.
    *Expect:* search returns `[]` (UI shows empty); `error_log` has `[sale.quick_search] ...` entry; no PDO trace in browser network tab.

### Headers (Phase 9)

14. **`curl -I https://staging/sale.php`** as authenticated session.
    *Expect:* `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` present; no CSP.
