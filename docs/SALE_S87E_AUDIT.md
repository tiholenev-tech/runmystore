# 🔍 SALE.PHP — S87E READ-ONLY AUDIT

**Author:** Code Code #2 (S95.DOCS.APPLY_DRAFTS_AND_SALE_AUDIT)
**Date:** 04.05.2026
**Branch:** main, base commit 23acdaa (post-EOD docs apply)
**Scope:** sale.php (3,780 LOC) + sale-save.php (91 LOC, orphan) + BIBLE §14 schema authority
**Mode:** READ-ONLY. **No code edits made.** This is a finding catalogue. Fixes deferred to Sprint S96 (RWQ-64 + sale-S87E P0).
**Reading order target:** Тихол — read top to bottom; first 3 sections are highest-impact.

---

## 0. EXECUTIVE SUMMARY

**Overall posture:** Sale flow is **atomic and schema-compatible** today (S90.RACE.SALE + S87.SALE.DBFIX did the heavy lifting). Race condition closed. But the file has **5 production bugs that don't crash the save** but **silently corrupt data** for analytics, wholesale, deferred payments, and audit. Plus the orphan `sale-save.php` is 2 schema timebombs and 1 undefined-`$pdo` fatal away from breaking — mercifully no caller invokes it.

**Top-3 most-impactful findings (fix first):**

| # | Finding | Why critical | Fix effort |
|---|---|---|---|
| **F1** | `is_wholesale=true` is sent from JS but **server ignores it** → all sales saved as `sales.type='retail'` regardless of toggle | Wholesale reporting is silently zero. Affects revenue split, customer LTV, margin analysis, Marketing AI Profit Maximizer (RWQ-65). | 1-line fix: add `$is_wholesale` extract + set `$sale_type = $is_wholesale ? 'wholesale' : 'retail'` + add to INSERT. |
| **F2** | NO `audit_log` INSERT after sale save | Pesho-in-the-Middle detection impossible (RWQ-64). No record of "who/when/where" for compliance, dispute resolution, fraud. **Marketing AI Activation Gate (Standing Rule #26) explicitly requires this.** | 5-line append inside the transaction. |
| **F3** | `sales.subtotal`, `sales.paid_amount`, `sales.due_date` are **never inserted** by sale.php (rely on DB defaults) | `subtotal` defaults to 0 → "gross before discount" reporting is broken. `paid_amount=0` for cash/card sales is wrong (should = total). `due_date` for `payment_method='deferred'` never set → AR aging can't run. | 3 columns to add to INSERT statement. |

Below: 7 categories with full findings, line refs, severity, and proposed fixes.

---

## 1. KAT 1 — RACE CONDITIONS / ATOMICITY

### ✅ 1.1 sale-race CLOSED (S90.RACE.SALE 34041ca verified)
**Status:** Already fixed. Verification today confirms.
- `sale.php:113` `$pdo->beginTransaction()` wraps all sale writes ✓
- `sale.php:135` `UPDATE inventory SET quantity = quantity - ? WHERE product_id=? AND store_id=? AND quantity >= ?` — atomic, **NOT** GREATEST (older bug)
- `sale.php:137-139` `if ($upd->rowCount() === 0) throw Exception("Артикулът свърши...")` — fails fast, rollback fires
- `sale.php:146` `$pdo->rollBack()` on any exception

The original RWQ "sale-race" is genuinely closed. STRESS_BOARD ГРАФА 3 entry that mentioned `GREATEST(quantity-X, 0)` does not exist in current code — the fix is in place.

### ⚠️ 1.2 NO `FOR UPDATE` lock on inventory before search/lookup
**Status:** Theoretically OK because UPDATE is atomic, but BIBLE §14 stock_movements protocol expects locking semantics for multi-step ops.
- `sale.php:50-57` (quick_search), `sale.php:67-73` (barcode_lookup) read `inventory.quantity` for display **without** lock — Пешо sees "stock=5" while another seller is mid-transaction. Display can lie briefly. UPDATE with `quantity >= ?` catches the actual conflict at save time, so user-facing race is contained. **Severity: P2 (UI-only stale read).**

### 🔴 1.3 `inventory` UPDATE has no clamp guard against negative
**Status:** Defensive only — current logic prevents going negative via `quantity >= ?` predicate. Solid.
- But there's NO secondary check that pre-existing data can't already have `inventory.quantity < 0` (e.g. from sale-save.php on tenants where it ever ran, or from delivery rollbacks). If `quantity = -3` and someone sells 1 → `WHERE quantity >= 1` fails, save is correctly aborted. So this is OK. **Severity: informational.**

### 🟡 1.4 Multi-step in a single transaction — good, but no SAVEPOINT
**Status:** Per RWQ #11, #12 (existing): DB::tx() lacks deadlock retry + nested SAVEPOINT support. sale.php uses raw `$pdo->beginTransaction()` (not DB::tx wrapper) so doesn't even have those niceties. On MySQL 1213 (deadlock) the entire sale aborts and the user re-tries. **Severity: P2 (rare under low concurrency, important post-beta at scale).**

---

## 2. KAT 2 — SCHEMA DRIFT (BIBLE §14.9 LIVE WINS)

### 🔴 2.1 sale.php's `INSERT INTO sales` is missing 3 columns it computes but discards
`sale.php:121-123`:
```sql
INSERT INTO sales (tenant_id, store_id, user_id, customer_id,
                   total, discount_amount, discount_pct,
                   payment_method, status, created_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
```
Columns **PRESENT in BIBLE §14.1 but NOT inserted** (rely on defaults):
- `subtotal` → defaults to 0. **PHP computes it (`$subtotal` at L114-117) but throws it away.** Reports that subtract `discount_amount` from `subtotal` to verify discount math will see `0 − discount_amount = negative`. Marketing AI Profit Maximizer (RWQ-65) needs gross-before-discount.
- `paid_amount` → defaults to 0. For `payment_method IN ('cash','card','bank_transfer')` should be set = `$total`. For `'deferred'` should be 0 (correctly). Currently always 0 → AR aging treats every cash sale as unpaid. **Severity: P0 for finance reporting.**
- `due_date` → for `payment_method='deferred'` should be set (sale-save.php had this logic at its L75-77 but sale.php has no equivalent — there's no deferred date input in the active flow). **Severity: P0 if deferred payment is supposed to work today; P1 if Тихол is OK with deferred-without-due-date for beta.**
- `type` → defaults to `'retail'`. See Finding **F1** (KAT 5). **Severity: P0.**
- `note` → defaults to NULL. Acceptable.

**Severity:** P0 for `subtotal`, `paid_amount`, `type`. P1 for `due_date`. P2 for `note`.

### 🟢 2.2 sale.php's `INSERT INTO sale_items` is schema-correct
`sale.php:133-134`:
```sql
INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, discount_pct, total)
```
- BIBLE §14.1 sale_items has: id, sale_id, product_id, quantity, returned_quantity (default 0), unit_price, cost_price (nullable), discount_pct, total. sale.php INSERTs 6 of these 9 — the 3 missing all have defaults or are nullable. **OK.**
- ⚠️ `cost_price` is NOT snapshot at sale time → margin reports later have to JOIN `products.cost_price` which may have changed since the sale → margin numbers will drift. **Severity: P1 for margin reporting integrity.**

### 🔴 2.3 sale.php's `INSERT INTO stock_movements` is missing 2 columns
`sale.php:140-141`:
```sql
INSERT INTO stock_movements (tenant_id, product_id, store_id, quantity,
                             type, reference_type, reference_id, created_at)
VALUES (?, ?, ?, ?, 'out', 'sale', ?, NOW())
```
Missing per BIBLE §14.1 stock_movements:
- `user_id` — NULLABLE so save succeeds, but **the column is the only "who did this" field on stock_movements**. Pesho-in-the-Middle detection (RWQ-64) **literally cannot work** without this. **Severity: P0 for RWQ-64.**
- `price` — NULLABLE so save succeeds, but no audit of "what was the unit price at the moment of stock decrement". For dispute resolution + margin attribution, this is required. **Severity: P1.**

### 🔴 2.4 `type='out'` mismatched with BIBLE-recommended `type='sale'`
- BIBLE §14.1 stock_movements ENUM includes both `'out'` and `'sale'`. sale.php uses `'out'` (generic), sale-save.php uses also a passed param (typically `'out'`). Per BIBLE comment "и двете живеят" the column tolerates both. But **`'sale'` is more semantic** and lets stock_movement reports filter by actual cause without joining sales table. **Severity: P2 (cosmetic / reporting hint).**

### 🔴 2.5 sale-save.php `payment_status` column DOES NOT EXIST (RWQ-48 still open)
`sale-save.php:28-31`:
```sql
INSERT INTO sales (tenant_id, store_id, user_id, customer_id, total,
                   discount_amount, payment_method, payment_status, created_at)
```
`sales.payment_status` is NOT in BIBLE §14.1. BIBLE §14.1 has `status` ENUM('draft','completed','canceled','returned') — that's the only status column. sale-save.php would fatal on first call. **Severity: P0** but **BLAST RADIUS = 0 today** because no caller invokes sale-save.php (RWQ #51 orphan finding). **Action:** delete the file or rewrite. RWQ #48 stays open until then.

### 🔴 2.6 sale-save.php `sale_items.tenant_id` column DOES NOT EXIST (RWQ-49 still open)
`sale-save.php:51-54`:
```sql
INSERT INTO sale_items (tenant_id, sale_id, product_id, quantity,
                        unit_price, discount_pct, total)
```
`sale_items` has no `tenant_id` (per BIBLE §14.1, tenant scoping is via `JOIN sales ON ...`). Same orphan situation as 2.5 — fatal-if-called, currently never called. **Severity: P0 (gated by orphan).** Action: delete or rewrite.

### 🔴 2.7 sale-save.php fatal: `$pdo` never initialized
`sale-save.php:5` requires `config/database.php` (which provides `DB::run(...)` and `DB::get()`). But `sale-save.php:25` does `$pdo->beginTransaction()` without ever calling `$pdo = DB::get();`. **The very first DB call is on undefined `$pdo` → fatal.** sale.php:7 has `$pdo = DB::get();` correctly. **Severity: P0** but again gated by orphan status. The catch-block at L88-90 also references `$pdo->rollBack()` on the same undefined.

### 🔴 2.8 sale.php and sale-save.php divergent stock_movements column order (RWQ-50 still open)
- sale.php order: `(tenant_id, product_id, store_id, quantity, type, reference_type, reference_id, created_at)`
- sale-save.php order: `(tenant_id, store_id, product_id, type, quantity, unit_price, reference_id, reference_type, created_by, created_at)`

Both are NAMED INSERTs so positional drift doesn't actually corrupt — column names route values correctly. **The "field order" risk in RWQ-50 is overstated** as long as named INSERTs are kept. The real problem: sale-save.php uses `created_by` which is **not in BIBLE §14.1** (BIBLE has `user_id`). Yet another orphan timebomb. **Severity: P1 reclassify of RWQ-50 — the divergence is in *column choice* not order. Recommend rename RWQ-50 entry to "named-INSERT enforcement + column-name canonicalization".**

### 🟢 2.9 BIBLE §14.9 LIVE SCHEMA AUTHORITY — sale.php compliance score
- ✅ Uses live ENUM `payment_method IN ('cash','card','bank_transfer','deferred')` (post S87.SALE.DBFIX — old `'transfer'` not used)
- ✅ Uses `sales.total` (not `total_amount`)
- ✅ Uses `sale_items.total` (not `subtotal`)
- ✅ Uses `status='completed'` literal (matches ENUM)
- ⚠️ Doesn't populate `subtotal`, `paid_amount`, `due_date`, `type` — see 2.1
- ⚠️ Doesn't populate `stock_movements.user_id`, `.price` — see 2.3

---

## 3. KAT 3 — AUDIT TRAIL GAPS (Pesho-in-the-Middle, RWQ-64)

### 🔴 3.1 sale.php `save_sale` makes ZERO audit_log writes
`sale.php:100-150` end-to-end has **no** `INSERT INTO audit_log`. sale-save.php at L80-83 had it (orphan, never runs).
- BIBLE Закон #4 (Audit Trail) explicitly requires every state change.
- Standing Rule #26 (Marketing AI Activation Gate) requires it as gate condition.
- Standing Rule #10 (audit_log extension, RWQ closed entry #10) added store_id/source/user_agent/source_detail to the schema specifically for this — **and sale.php still doesn't use it.**

**Recommendation:** add inside the transaction, after stock_movements insert, before commit:
```sql
INSERT INTO audit_log (tenant_id, user_id, store_id, action, entity_type, entity_id,
                       source, source_detail, user_agent, created_at)
VALUES (?, ?, ?, 'create', 'sale', ?, 'sale.php',
        ?, ?, NOW())
```
Where `source_detail` = JSON `{is_wholesale, payment_method, item_count, total, customer_id}` and `user_agent` = `$_SERVER['HTTP_USER_AGENT']`.

**Severity: P0** — direct blocker for RWQ-64 hardening. Estimated fix: 8 LOC.

### 🔴 3.2 No audit on void/refund (because no void/refund handler exists)
- sale.php has **zero handlers** for voiding or refunding a sale.
- BIBLE §14.1 status ENUM includes `'canceled'` and `'returned'` and sale_items has `returned_quantity` — schema is ready, code is absent.
- Pesho-in-the-Middle scenario: Пешо makes a sale, then deletes it via a phpMyAdmin shortcut a tech-savvy seller might have. No audit. No way to detect.
- **Severity: P0** for compliance + RWQ-64.
- Effort: medium (200-300 LOC for a `?action=void_sale&id=X` handler with permission check + status update + reverse stock_movements + audit_log).

### 🔴 3.3 No audit on inventory display reads (information disclosure)
- Lower severity but worth noting: barcode_lookup + quick_search return inventory levels without logging. A motivated insider could scrape stock counts via repeat AJAX. **Severity: P3.** Defer post-beta.

### 🟡 3.4 stock_movements.user_id missing kills "who decremented stock" trace
- See 2.3. Even if 3.1 is fixed (audit_log on sales), `stock_movements` rows for the sale still have `user_id=NULL`. To answer "show me all stock decrements user 42 made on 2026-05-04" you'd need to JOIN through sales. **Severity: P1** — fix together with 2.3 (one extra param in the INSERT).

---

## 4. KAT 4 — PESHO-IN-THE-MIDDLE HARDENING (RWQ-64)

### 🔴 4.1 No tenant-scope guard on `customer_id` from JS
- `sale.php:107` accepts `customer_id` from JSON body verbatim. It's later inserted at L121-123 without checking whether `customer_id` belongs to the same `tenant_id`.
- **Attack:** seller from tenant=42 inspects a list of customer IDs from another tenant, posts them in `confirmPayment`, and tags the sale to a foreign customer. Won't crash (FK isn't multi-tenant aware in BIBLE §14.1) but corrupts customer LTV across tenants.
- **Recommendation:** verify `SELECT 1 FROM customers WHERE id=? AND tenant_id=?` before using.
- **Severity: P0** for multi-tenant integrity.

### 🔴 4.2 No tenant-scope guard on `product_id` from JS
- `sale.php:127` accepts `product_id` from items[] verbatim. INSERT into sale_items + stock_movements + UPDATE inventory all rely on the WHERE clauses — and the inventory UPDATE does include `tenant_id`-scoping via store_id (L135 `WHERE product_id=? AND store_id=?` and the store was already tenant-scoped at session start L26).
- BUT: nothing prevents posting a product_id from another tenant where the same product happens to exist in the seller's store_id with positive stock. Edge case but real on shared-supplier scenarios.
- **Recommendation:** verify `SELECT 1 FROM products WHERE id=? AND tenant_id=?` before processing each item, OR add `tenant_id=?` to the inventory UPDATE WHERE.
- **Severity: P1** (rare in practice, but the fix is trivial).

### 🔴 4.3 `discount_pct` is not bounded server-side
- `sale.php:106` `$discount_pct = floatval($data['discount_pct'] ?? 0)` — no min/max clamp.
- The PHP user-level cap exists at `sale.php:23` (`$max_discount = floatval($user['max_discount_pct'] ?? 100)`) but **it's never compared against `$discount_pct` in the save handler**. The cap is applied only client-side (in JS).
- **Attack:** DevTools, set discount_pct to 99.9 even if Пешо's role allows max 10%. Server saves it.
- **Recommendation:** server-side clamp `min($discount_pct, $max_discount)`.
- **Severity: P0** for revenue integrity.

### 🟡 4.4 `unit_price` is trusted from JS (no server reconciliation)
- `sale.php:129` `$price = floatval($it['unit_price'])`. The price can be anything — wholesale, retail, or whatever JS posts.
- This is **intentional design** (manual price override, B2B negotiation, employee discount). But there's no audit log of "JS-posted price 2.50, products.retail_price was 12.99 at time of sale".
- **Recommendation:** snapshot `cost_price` AND `retail_price` AND `posted_unit_price` in `sale_items` so margin reports show whether Пешо deviated from list price. (The `cost_price` part is already in BIBLE §14.1 sale_items; just not used.)
- **Severity: P1** for margin attribution.

### 🟡 4.5 Hardcoded `'completed'` status — no `'draft'` flow
- BIBLE §14.1 status ENUM has `'draft'`. There's no flow that creates a sale in draft (e.g. "park a cart, resume later from another POS terminal"). All sales hit DB as final.
- This is fine for beta, but Standing Rule #4 (Audit Trail) and the parked-cart UX in sale.php JS (L2081 mentions "Парк N · ...") suggest there's parked-cart functionality that NEVER persists to DB — it's localStorage-only. Cart is lost if Пешо's phone dies mid-сесия.
- **Severity: P2** for beta; **P1** for post-beta with multi-store.

### 🔴 4.6 Session-stored `tenant_id` / `store_id` not re-verified per request
- `sale.php:8-10` `$tenant_id = $_SESSION['tenant_id']; $store_id = $_SESSION['store_id'] ?? 1;` — used everywhere, never re-fetched from DB.
- If admin disables a tenant or moves a user to another store mid-session, sale.php happily keeps writing as the old session. Stale-session attack surface.
- **Recommendation:** `SELECT is_active FROM tenants WHERE id=?` + `SELECT 1 FROM users WHERE id=? AND tenant_id=? AND is_active=1` at top of save_sale handler.
- **Severity: P1**.

---

## 5. KAT 5 — TRACK 2 P0 FINDINGS (open)

⚠️ **No TRACK 2 spec doc found in repo.** Searched: `docs/`, file-system grep for `NAME_INPUT_DEAD`, `D12_REGRESSION`, `WHOLESALE_NO_CURRENCY` — nothing. So findings below are **best-effort code-observation** — Тихол / TRACK 2 помощник should confirm or correct.

### F1 — WHOLESALE: `is_wholesale` flag silently dropped server-side ⭐ TOP PRIORITY
**Location:** `sale.php:101-150` (save_sale handler) + `sale.php:2715` (JS sends it).
- **JS side (`sale.php:2715`):** `is_wholesale: STATE.isWholesale` is included in POST body.
- **Server side (`sale.php:103-110`):** `$data` is parsed; `payment_method`, `discount_pct`, `customer_id`, `received` are extracted. **`is_wholesale` is NEVER extracted.**
- **Server side INSERT (`sale.php:121-123`):** `INSERT INTO sales` does not include the `type` column → defaults to `'retail'`.
- **Result:** Every wholesale sale lands in DB as `type='retail'`. `STATE.customerId` (the wholesale customer FK) IS saved correctly, but `type` is wrong. Reports filtering on `WHERE type='wholesale'` see 0 rows even when Пешо selected the customer.

**Likely "WHOLESALE_NO_CURRENCY" mapping:** This may be the TRACK 2 finding — wholesale price isn't formatted with the multi-currency `priceFormat()` helper at sale.php:1958. Looking at fmtPrice usage:
- `sale.php:2141` uses `fmtPrice(item.unit_price) + ' ' + STATE.currency` — single-currency.
- `priceFormat()` (sale.php:1958) supports BG/EUR dual display ("1.95 € (3.82 лв)") but **is not called anywhere in the sale UI for line items, totals, or stat cards** (grep confirms: only L1958 defines it; only legacy/comment refs use the name).
- So wholesale lines and retail lines both render via `fmtPrice` only. **Currency display is single-currency.** If the TRACK 2 finding is "wholesale price misses currency formatting" then the bug is broader — **the entire sale.php uses `fmtPrice` only, never `priceFormat`**, regardless of wholesale/retail.

**Severity: P0** for reporting (F1 type column) + **P1** for currency display (TRACK 2 WHOLESALE_NO_CURRENCY).

### F2 — NAME_INPUT_DEAD: no input field for new wholesale customer
**Location:** `sale.php:1815-1837` (wsSheet wholesale picker).
- The wholesale-client picker renders **only existing** `$wholesale_clients` entries (PHP-rendered loop). There is **no `<input>`** for typing a new name on the spot.
- Flow today: if Пешо wants to sell to a new wholesale customer, they must EXIT sale.php → go to a customer-management page (which?) → add the customer → return.
- **If TRACK 2 spec wanted "type a name and create on save"** → not implemented. Code has `STATE.customerName` (sale.php:1928) but it's only set by selecting an existing client, never by typing a new one.
- **Severity: P0** if Тихол confirms this is the intended UX gap; **N/A** if NAME_INPUT_DEAD is about a different field entirely.

### F3 — D12_REGRESSION: Markup terminology
**Location:** searched `sale.php` for `Markup`, `markup`, `Накрутка`, `Надценка` — **0 results**. D12 is the products.php Sprint C bug (Markup vs Profit margin terminology in pricing input). Not in sale.php — false flag from helper memory. **Confirm with Тихол: D12_REGRESSION belongs in products.php, not sale.php.**

---

## 6. KAT 6 — SPRINT H DEFERRED ITEMS

### B6 — Voice continuous=false (deferred)
- `sale.php:3161` `recognition.continuous = false` confirmed not enabled.
- `sale.php:3160` `recognition.lang = 'bg-BG'` confirmed.
- Decision per COMPASS L1001-1004: keep `continuous=false` until bg-BG quality is verified. Sprint H deferred status is correct — no action needed pre-beta.

### B7 — Numpad decimal point (deferred, integer-only today)
- `sale.php:1628-1632` numpad-zone HTML grid + numpad-grid; `sale.php:1924-1925` `STATE.numpadInput` is a string but used contextually as integer (qty/price contexts).
- No `.` button visible in the numpad-grid HTML structure (sample at sale.php:527-530 .numpad-grid CSS). Decimal entry would need new key + parser logic.
- Sprint H deferred status is correct — but **for kg/m/L products (BIBLE §14.1 sale_items.quantity is DECIMAL(12,4)), this becomes a hard blocker** when fashion tenants need to sell fabric by meter, etc. Today only integer qty supported.
- **Severity: P2 pre-beta** (ENI tenants are fashion/clothing — integer qty is fine). **P0 post-beta** for deli/grocery/textile tenants.

### Multi-select при search (long-press, deferred)
- `sale.php:2163` comment "S87G.B3 — split tap zone on qty value: left half = -1, right half = +1; long-press = numpad" — this is qty long-press, NOT search multi-select.
- Search results today are single-tap-add. Bulk add is via barcode scanning multiple times.
- Sprint H deferred status is correct.

---

## 7. KAT 7 — SPRINT E "8 BUGS" IDENTIFICATION

⚠️ **The "8 bugs Sprint E" reference in COMPASS BUG TRACKER (sale-S87E P0 in STATE) is mentioned without an itemized list.** Below is my best-effort inference from code observation. Тихол / Track 2 should reconcile this list against the actual Sprint E spec.

| # | Inferred bug | Location | Severity | Notes |
|---|---|---|---|---|
| **E1** | `is_wholesale` ignored server-side → `sales.type` always 'retail' | `sale.php:101-150` | P0 | KAT 5 F1 |
| **E2** | `subtotal` / `paid_amount` / `due_date` / `type` missing from INSERT | `sale.php:121-123` | P0 | KAT 2.1 |
| **E3** | `stock_movements.user_id` and `.price` missing | `sale.php:140-141` | P0 (RWQ-64) | KAT 2.3 + 3.4 |
| **E4** | NO `audit_log` write on sale create | `sale.php:100-150` end | P0 (RWQ-64) | KAT 3.1 |
| **E5** | NO void / refund / cancel handler | `sale.php` (absent) | P0 (compliance + RWQ-64) | KAT 3.2 |
| **E6** | `customer_id` not tenant-scope-guarded server-side | `sale.php:107` | P0 (multi-tenant) | KAT 4.1 |
| **E7** | `discount_pct` not server-side bounded by `users.max_discount_pct` | `sale.php:106` | P0 (revenue) | KAT 4.3 |
| **E8** | `cost_price` not snapshot at sale time → margin drift | `sale.php:133-134` | P1 (analytics) | KAT 2.2 + 4.4 |

**Confidence on this list:** medium. Several other candidates (F2 NAME_INPUT_DEAD, F3 D12_REGRESSION, F-WHOLESALE_NO_CURRENCY) may be the *real* Sprint E items. Recommend Тихол post-and-confirm the Sprint E spec verbatim before fix work begins.

---

## 8. RECONCILIATION WITH EXISTING REWORK QUEUE

| RWQ # | Status before audit | Status after audit | Note |
|---|---|---|---|
| #48 sale-save.php payment_status | 🔴 P0 critical (timebomb) | **VERIFIED still timebomb. Orphan = blast radius 0 today.** | Recommend close as DELETE-the-file; or rewrite as a single integrated handler in S96. |
| #49 sale_items.tenant_id | 🔴 P0 critical (timebomb) | **VERIFIED still timebomb. Same orphan gating.** | Same recommendation as #48. |
| #50 stock_movements field-order divergent | 🟡 P1 verify | **RECLASSIFY: not an order issue (both files use named INSERT). Real issue: column-name divergence (sale-save.php has `created_by` not in BIBLE; sale.php missing `user_id` + `price`).** | Recommend rename queue entry to "stock_movements column canonicalization + named-INSERT enforcement". |
| #51 sale-save.php orphan | 🟡 P1 cleanup | **VERIFIED orphan (no callers). Plus undefined-`$pdo` fatal at L25 — file would fatal on first call regardless of schema.** | Recommend close as DELETE-the-file. |
| #64 Pesho-in-the-Middle hardening | 🔴 P0 (S96 target) | **EXPANDED scope: 8 sub-items** (KAT 4.1-4.6 + KAT 3.1 + KAT 3.2). | This audit IS the scoping doc for RWQ-64. |

---

## 9. RECOMMENDED FIX SEQUENCE (S96 plan)

**Group A — schema-correctness (1 commit, ~30 LOC):**
- Add `subtotal`, `paid_amount`, `due_date`, `type` to `INSERT INTO sales` (KAT 2.1 + F1)
- Add `user_id`, `price` to `INSERT INTO stock_movements` (KAT 2.3)
- Snapshot `cost_price` to `sale_items` (KAT 2.2)

**Group B — server-side validation (1 commit, ~20 LOC):**
- Tenant-scope `customer_id` and `product_id` (KAT 4.1, 4.2)
- Clamp `discount_pct` against `$max_discount` (KAT 4.3)
- Re-verify session at handler top (KAT 4.6)

**Group C — audit_log (1 commit, ~10 LOC):**
- Add `audit_log` INSERT inside transaction (KAT 3.1)

**Group D — void/refund handler (1 commit, ~250 LOC) — POST-BETA OK:**
- New `?action=void_sale&id=X` with permission check, reverse stock_movements, status update, audit_log (KAT 3.2)

**Group E — orphan cleanup (1 commit, ~5 LOC):**
- DELETE `sale-save.php` (or git rm) — closes RWQ #48, #49, #50 (named INSERT canonicalization separate), #51

**Estimated total effort:** Groups A+B+C+E pre-beta = ~65 LOC + 1 file deletion, 4 commits, ~3 hours. Group D post-beta separately.

**Browser test plan:** STRESS_BOARD ГРАФА 1 should add a "POST-S96 sale verification" entry once fixes land — test all 8 inferred bugs end-to-end on tenant=99 synthetic data.

---

## 10. WHAT THIS AUDIT DID **NOT** COVER

Out of scope by instruction (no time + read-only):
- sale.php JS layer (3,000+ LOC of view/state) — no UX bug audit beyond items above
- Inventory display logic (race only audited at write-path)
- Camera/scanner integration (camHeader, scan modal at L223 etc)
- Print path from sale.php (BLE/Capacitor)
- Performance / query plan analysis on indexed JOINs
- products.php (out of scope, Code Code #1 territory)
- ai-studio.php interactions (RWQ-71 separately)

Out of scope by lack of spec:
- True Sprint E "8 bugs" list (KAT 7 is best-effort inference)
- TRACK 2 NAME_INPUT_DEAD / D12_REGRESSION / WHOLESALE_NO_CURRENCY exact intent

---

## 11. SIGN-OFF CHECKLIST

- [x] sale.php read end-to-end (3,780 LOC)
- [x] sale-save.php read end-to-end (91 LOC, orphan)
- [x] BIBLE §14.1 sales / sale_items / stock_movements compared against live INSERTs
- [x] BIBLE §14.9 LIVE SCHEMA AUTHORITY consulted
- [x] STRESS_BOARD ГРАФА 3 prior sale-race entry verified closed
- [x] No code edits in sale.php / sale-save.php (read-only)
- [x] Findings categorized into 7 KAT groups
- [x] Severity assigned per finding
- [x] Reconciliation against RWQ #48-51 + #64 included
- [x] Fix sequence proposed for S96

**Audit duration:** ~45 min reading + ~30 min writing. Total Phase B time: under 90 min within 4h cap.

**Next action for Тихол:**
1. Read top-3 findings (F1, F2 audit-section, F3 audit-section).
2. Confirm/correct TRACK 2 P0 findings (KAT 5).
3. Confirm/post Sprint E "8 bugs" spec for KAT 7 reconciliation.
4. Approve S96 fix sequence (or reorder).
5. Schedule S96 in PRIORITY_TODAY.
