# 🛒 SALE.PHP REWRITE PLAN — S87 (target 04.05.2026)

**Author:** Claude Code (S87 pre-work analysis)  
**Date:** 27.04.2026  
**Status:** DRAFT — pending Тихол approval (виж §10 Open Questions)  
**Mockup sources:** `sale-payment-v3.html` & `sale-v5.html` referenced in задание — **NOT FOUND в repo** (виж §10 Q1). Spec е sourced от `MASTER_COMPASS.md §sale.php`, `docs/BIBLE_v3_0_TECH.md §14.1 sales schema`, и live анализ на `sale.php` (2210 реда).

---

## 1. Executive Summary

`sale.php` е monolithic 2210-реден файл с **3 broken DB writes** (схема mismatch — `total_amount` / `subtotal` / `payment_status` columns липсват в реалната `sales` / `sale_items` таблица). UI слой работи: камера-header, custom numpad, voice overlay, BG/EL/Latin клавиатура, parked sales (localStorage). Rewrite не значи да изхвърлим UI кода — значи: **fix схема mismatch**, **разделим на модули** (camera, numpad, voice, payment), **wrap-нем DB::tx()**, и **въведем 4 audit-trail записа** (inventory_events, search_log, lost_demand, audit_log) които сега липсват. План: **15 commit-able stъpki за ~16h** total. Beta blocker риск среден — DB writes чупят payment flow.

---

## 2. Current State Analysis

### 2.1 File metrics

| Metric | Value |
|---|---|
| File path | `/var/www/runmystore/sale.php` |
| Total lines | **2210** |
| Approx. PHP | 120 (header + 3 AJAX handlers) |
| Approx. CSS | 880 (line ~132–1050, всички component styles) |
| Approx. HTML | 400 (line ~770–1119, body markup) |
| Approx. JS | 840 (line ~1121–2206, engine + handlers) |
| Last touched | S82.UI (theme toggle inline init, 25.04.2026) |
| Dependent peripheral files | `sale-save.php` (91), `sale-search.php` (45), `sale-voice.php` (78) |

### 2.2 PHP top-level (lines 1–120)

| Block | Lines | Purpose | Verdict |
|---|---|---|---|
| Session + tenant/user/store load | 1–28 | Standard auth gate, fetch `tenants`, `users`, `stores` rows | ✅ keep |
| Wholesale clients fetch | 30 | `SELECT FROM customers WHERE is_wholesale=1` | ✅ keep |
| AJAX: `?action=quick_search` | 35–55 | Inline endpoint; 1 query: `code/name/barcode LIKE` LIMIT 10 | 🔄 refactor → extract в `sale-search.php` (вече съществува, но UNUSED) |
| AJAX: `?action=barcode_lookup` | 57–71 | Inline endpoint; barcode-only fast path | 🔄 refactor → extract в peripheral |
| AJAX: `?action=save_sale` | 73–120 | INSERT sales+sale_items+UPDATE inventory+INSERT stock_movements | ❌ **REPLACE** (3 broken columns + no DB::tx) |

### 2.3 JavaScript functions (46 total)

Категоризирани по responsibility:

| Domain | Functions | Verdict |
|---|---|---|
| **Theme toggle** (S82.UI) | `initTheme`, `toggleTheme` | ✅ keep |
| **Format helpers** | `fmtPrice`, `getTotal`, `getItemCount`, `esc` | ✅ keep |
| **Audio feedback** | `beep`, `ching` (3-tone success) | ✅ keep |
| **Visual feedback** | `greenFlash`, `flashCamScan`, `showToast` | ✅ keep |
| **Render** | `render` (105 lines, monolithic — пре-рисува цял cart) | 🔄 refactor (split: renderCart, renderSummary, renderHeader) |
| **Cart ops** | `addToCart`, `selectCartItem`, `removeItem`, `showUndo` | ✅ keep |
| **Numpad** | `setNumpadCtx`, `numPress`, `numOk` | ✅ keep |
| **Search** | `updateSearchDisplay`, `triggerSearch`, `doSearch`, `closeSearchResults`, `showNoResult` | 🔄 refactor (миграция към `sale-search.php` endpoint + add `search_log` INSERT) |
| **Letter keyboard** | `toggleKeyboard`, `kbPress` | ✅ keep |
| **Discount** | `toggleDiscount`, `applyDiscount`, `closeDiscount` | ✅ keep |
| **Payment** | `openPayment`, `closePayment`, `setPayMethod`, `payBanknote`, `updatePayment`, `confirmPayment` | 🔄 refactor (`confirmPayment` — fix POST payload schema) |
| **Wholesale** | `openWholesale`, `closeWholesale`, `selectClient` | ⚠️ **TODO** (line 1814 noop „We'd need to refetch prices — for now keep current") |
| **Parking** | `parkSale`, `saveParked`, `openParked`, `closeParked` | 🔄 refactor (localStorage → optional DB-backed `parked_sales` table — виж §10 Q3) |
| **Long-press popup numpad** | `openLpPopup`, `closeLpPopup`, `lpNum`, `confirmLpPopup` | ✅ keep |
| **Camera + barcode** | `startCamera`, `toggleCamera` (legacy noop), `startBarcodeScanner`, `scanLoop`, `handleBarcode` | ✅ keep (rename `toggleCamera` → DELETE legacy) |
| **Voice** | `startVoice`, `handleVoiceResult` | 🔄 refactor (recognition init на всеки call → singleton; и пробив към `sale-voice.php`) |
| **Swipe nav** | DOM-level touchstart/touchend (line 2180) | ✅ keep |
| **Init** | `DOMContentLoaded` → `render()` + `startCamera()` | ✅ keep |

### 2.4 DB queries (current)

| Query | Verdict |
|---|---|
| `SELECT * FROM tenants WHERE id=?` | ✅ keep |
| `SELECT * FROM users WHERE id=?` | ✅ keep |
| `SELECT * FROM stores WHERE id=? AND tenant_id=?` | ✅ keep |
| `SELECT FROM customers WHERE is_wholesale=1` | ✅ keep |
| Inline quick_search (`code/name/barcode LIKE` LIMIT 10) | 🔄 add `search_log` INSERT |
| Inline barcode_lookup (`barcode=?` LIMIT 1) | 🔄 add `lost_demand` INSERT on miss |
| `INSERT INTO sales (tenant_id, store_id, user_id, customer_id, total_amount, discount_amount, discount_pct, payment_method, status, created_at)` | ❌ **BROKEN** (`total_amount` does not exist; real: `total`. `discount_pct` OK. `paid_amount` not written.) |
| `INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, discount_pct, subtotal)` | ❌ **BROKEN** (`subtotal` does not exist; real: `total`. `cost_price` not snapshotted — margin tracking broken.) |
| `UPDATE inventory SET quantity = GREATEST(quantity - ?, 0)` | ⚠️ keep (но missing `inventory_events` INSERT — Закон #4 Audit Trail violated) |
| `INSERT INTO stock_movements (... type='out')` | ⚠️ keep (но `type` enum allows `'sale'` — по-семантично; Тихол approve в §10 Q4) |

### 2.5 UI components inventory

| Component | DOM id / class | LOC | Verdict |
|---|---|---|---|
| `partials/header.php` include | `partials/header.php` | 1 | ✅ keep |
| Camera-header (80px + safe-area) | `.cam-header` `#camHeader` | ~70 (CSS+HTML) | ✅ keep — match desired state |
| Search bar | `.search-bar` | ~25 | ✅ keep |
| Search results dropdown | `.search-results` `#searchResults` | ~30 | ✅ keep |
| Discount chips (5/10/15/20%) | `.discount-chips` `#discountChips` | ~20 | ✅ keep |
| Cart zone | `.cart-zone` `#cartZone` | ~35 (+ JS render) | 🔄 refactor (extract `partials/sale-cart.php` template — currently JS-built) |
| Summary bar | `.summary-bar` `#summaryBar` | ~25 | ✅ keep |
| Action bar (PAY + park) | `.action-bar` | ~30 | ✅ keep |
| Numpad zone | `.numpad-zone` `#numpadZone` | ~50 (CSS+HTML) | ✅ keep |
| Letter keyboard zone | `.keyboard-zone` `#keyboardZone` | ~80 (per-locale variants) | ✅ keep |
| Payment sheet | `.pay-sheet` `#paySheet` | ~80 | 🔄 refactor (split в `partials/sale-payment.php`) |
| Wholesale sheet | `.ws-sheet` `#wsSheet` | ~30 | ✅ keep |
| Parked sales overlay | `.parked-overlay` `.parked-card` | ~50 | 🔄 refactor (см §3.6) |
| Voice overlay (S56 standard) | `.rec-ov` `#recOv` | ~80 | ✅ keep |
| Toast | `.toast` `#toast` | ~14 | ✅ keep |
| Long-press popup numpad | `.lp-popup` `#lpPopup` | ~25 | ✅ keep |
| „Не намерен" popup | `.nf-popup` `#nfPopup` | ~10 | ✅ keep |
| Bottom-nav include | `partials/bottom-nav.php` | 1 | ✅ keep |
| Shell scripts include | `partials/shell-scripts.php` | 1 | ✅ keep |

### 2.6 State management

`STATE` (line 1157) holds 23 keys + `parked` bootstrap from `localStorage['rms_parked_<store_id>']`. Single global, no reactive layer. Cart mutations call `render()` which re-renders DOM. Acceptable for ~10 items, но за 50+ items в edro mode → flicker. **Verdict:** ✅ keep, но добави `requestIdleCallback`-debounced render за edge case.

### 2.7 Existing voice integration

- `sale-voice.php` (78 реда) → Anthropic Claude API (`CLAUDE_MODEL` от `config.php`) parsing → JSON `{items: [...]}`.
- `handleVoiceResult` четe `res.action ∈ {search, set_qty, set_received, add_items}`.
- ⚠️ Mismatch: `sale-voice.php` връща `{items:[...]}`, а `handleVoiceResult` чете `res.action` — **action key никога не се връща** от backend → винаги fall-back-ва към `else: search`. **Скрит bug.**

### 2.8 Camera integration

- `startCamera()` (line 1946): `getUserMedia({ video: { facingMode: 'environment', 1280×720 }})`.
- `BarcodeDetector` API (browser-native, Chromium 83+): `formats: ['ean_13','ean_8','code_128','qr_code','code_39']`.
- Fallback: `if (!('BarcodeDetector' in window))` → `console.warn` + silent (no UX surface). **Issue:** Safari < 16 мобилни (iOS) → camera-header е там, но не сканира → user confusion.

### 2.9 Numpad implementation

**Native vs custom?** Custom (NO native keyboard). 4 contexts: `code | qty | discount | received`. Color-coded `ctxLabel` (CSS classes `ctx-code/qty/discount/received`). 13 keys (0–9, 00, ., ⌫, C, OK, 🎤). Keyboard zone hidden until [АБВ→] toggle. ✅ Per Закон #1.

### 2.10 Bluetooth print integration

**ABSENT.** Receipt printing след `confirmPayment` НЕ е wired. ENI launch RULE: ОПЦИОНАЛНО (Тихол casovam bon — отделна тема). За S87 → out-of-scope (виж §10 Q5).

### 2.11 Error handling patterns

- `try/catch` в save_sale → JSON `{success: false, error: <msg>}`.
- Network error → `showToast('Мрежова грешка')`.
- `BarcodeDetector` missing → silent.
- `recognition.onerror` → 1.5s "ГРЕШКА" + auto-close. 
- **Missing:** retry logic, offline queue, idempotency keys.

---

## 3. Desired State Specification

Основан на `MASTER_COMPASS.md §sale.php` (lines 488–562) + `BIBLE_TECH §14.1`.

### 3.1 Camera always-live header (80px)

Зелена laser-line scan animation, beep при scan, double-beep при duplicate add. ✅ already there. Add: graceful fallback toast при няма `BarcodeDetector` ("Камерата не сканира — въведи код ръчно").

### 3.2 Custom numpad — контекстен

4 contexts: `code` (търсене), `qty` (бройки на selected item), `discount` (% на cart), `received` (плащане cash). Color-coded label. Single OK button — context-aware action. ✅ already there.

### 3.3 Voice overlay (S56 standard)

`.rec-ov` / `.rec-box` от products.php — RECORDING (червена точка пулсира) → READY (зелена точка ✓) → user tap "Изпрати". Cancel → abort. Context-aware hints (current ctx → различен placeholder). ✅ already there. Fix: `sale-voice.php` response schema mismatch (виж §3.10).

### 3.4 Parked sales strip (⏸ swipe)

Sticky badge top-right (camera-header), tap → list overlay. **NEW:** swipe-left on cart → quick-park (без modal). Bootstrap from DB-backed `parked_sales` table (виж §10 Q3 за table creation).

### 3.5 Toast feedback

3 типа: `success` (green, 2.5s), `error` (red, 4s), `warning` (orange) — за `lost_demand` създадено ("Записах: '_query_'. Ще потърся доставчик."). ✅ pattern there, но `lost_demand` toast missing.

### 3.6 Едро/дребно toggle

Header магически променя цвят (border indigo-400). Цените в cart **трябва да се refetch-нат** на toggle (TODO at line 1814 в JS). Helper: GET `sale.php?action=refetch_prices` → array of new prices keyed by product_id.

### 3.7 Customer loyalty integration

`loyalty_customers` table **не съществува още** (виж §10 Q6). Phase A2 за beta launch не блокира. Customer ID запис в `sales.customer_id` ✅.

### 3.8 DB::tx() wrapping

Atomic: `INSERT sales` → `INSERT sale_items` × N → `UPDATE inventory` × N → `INSERT inventory_events` × N → `INSERT stock_movements` × N → `INSERT audit_log`. Всичко вътре в `DB::tx(function($pdo){…})`. Deadlock retry автоматичен (S80.A).

### 3.9 inventory_events INSERT (S87+ event-sourced)

За всеки sale_item:
```sql
INSERT INTO inventory_events
  (store_id, product_id, event_type, quantity_delta, quantity_after,
   reference_id, reference_type, created_at)
  VALUES (?, ?, 'sale', -?, ?, <sale_id>, 'sales', NOW())
```
`quantity_after` се чете FOR UPDATE → calc → write. Idempotency: уникален индекс на `(reference_type, reference_id, product_id)` за да не двойни.

### 3.10 search_log INSERT

При всяко search (typed или voice → fall-back на search) → `INSERT INTO search_log (tenant_id, store_id, user_id, query, results_count, source='sale', created_at=NOW())`. Helper `logSearch()` вече съществува в `config/helpers.php`. Reuse.

### 3.11 lost_demand INSERT

При barcode miss или search results=0 → `recordLostDemand($tenant, $store, $user, $query, source='barcode_miss'|'search'|'voice')`. Helper `recordLostDemand()` съществува в `helpers.php`. Reuse.

### 3.12 Offline queue (IndexedDB)

`navigator.online === false` → `confirmPayment` → `idb.add('pending_sales', payload)` + toast „Запазено offline — ще се синхронизира". `online` event → flush queue с idempotency key (`crypto.randomUUID()`). ⚠️ **Phase B** — пускаме с TODO в S87, реализация в S88-S89.

### 3.13 Voice response schema

`sale-voice.php` трябва да върне:
```json
{ "action": "search|set_qty|set_received|add_items", "value": "...", "items": [...], "query": "..." }
```
а не само `{items:[]}`. Fix в `sale-voice.php` (out-of-scope за това PRE-WORK; tracking в Step 8).

---

## 4. Gap Analysis Matrix

| # | Component | Current | Desired | Effort | Risk |
|---|---|---|---|---|---|
| 1 | `sales` INSERT columns | Uses `total_amount` (broken) | `total`, `subtotal`, `discount_amount`, `paid_amount`, `note`, `due_date` per real schema | 30 min | 🔴 P0 (payment flow дъвче) |
| 2 | `sale_items` INSERT columns | Uses `subtotal` (broken) | `total`, `cost_price` snapshot | 30 min | 🔴 P0 |
| 3 | `payment_method='transfer'` JS | Mismatch with enum `bank_transfer` | Fix JS string → `bank_transfer` | 5 min | 🟡 P1 |
| 4 | DB::tx() wrap | Manual `beginTransaction` | `DB::tx(function(){})` | 45 min | 🟢 (deadlock retry win) |
| 5 | `inventory_events` INSERT | Missing | Per-item event with quantity_after | 1h | 🟡 (race FOR UPDATE) |
| 6 | `search_log` INSERT | Missing | reuse `logSearch()` helper | 20 min | 🟢 |
| 7 | `lost_demand` INSERT | Missing | reuse `recordLostDemand()` | 30 min | 🟢 |
| 8 | `audit_log` INSERT (S79+) | Missing | reuse `auditLog()` за action='create', table='sales' | 20 min | 🟢 |
| 9 | Wholesale price refetch on toggle | TODO noop (line 1814) | GET `?action=refetch_prices` | 45 min | 🟡 (UX confusion ако забавено) |
| 10 | Parked sales backing store | localStorage only | Optional DB `parked_sales` table | 1h | 🟢 (но виж §10 Q3) |
| 11 | Voice response schema | mismatch (no `action` key) | server returns `action` discriminator | 30 min | 🟡 |
| 12 | Camera fallback UX | silent | Toast warning + manual code path | 15 min | 🟢 |
| 13 | Cart render perf (50+ items edro) | full re-render | Diff render or `requestIdleCallback` | 30 min | 🟢 |
| 14 | Offline queue (IndexedDB) | absent | TODO в S87, implement S88+ | 0 (defer) | 🟡 |
| 15 | Bluetooth receipt print | absent | Defer (виж §10 Q5) | 0 (defer) | 🟢 |

**Summary:**
- 1:1 keep: ~70% (UI, CSS, theme, voice overlay, numpad)
- Cosmetic: ~10% (toasts, fallback labels)
- Fundamental rewrite: ~15% (save_sale handler, DB::tx, audit trail)
- Net new: ~5% (search_log/lost_demand/inventory_events writes)

---

## 5. Step-by-Step Rewrite Plan

15 commit-able steps, всеки ≤ 2h, всеки с ясна rollback пътека.

### Step 1 — Backup + branch isolation (15 min)

- `cp sale.php sale.php.s86_pre_rewrite.bak`
- Tag baseline: `git tag s86-sale-baseline`
- Rollback: `cp sale.php.s86_pre_rewrite.bak sale.php && git checkout s86-sale-baseline -- sale.php`
- DB changes: none.
- Test: `php -l sale.php` OK.

### Step 2 — Fix `INSERT INTO sales` schema (30 min)

- Файл: `sale.php` lines 94–96.
- Old columns → real columns: `total_amount` → `total`; добави `subtotal` + `discount_amount` (calculated) + `paid_amount` (= `received` ако `cash`, иначе `= total`) + `due_date` (NULL по подразбиране, set ако `payment_method='deferred'`) + `note` (от cart `note` ако има).
- Test: smoke `confirmPayment` на dev (tenant=99) → row inserted.
- Rollback: revert single block.

### Step 3 — Fix `INSERT INTO sale_items` + `cost_price` snapshot (30 min)

- Файл: `sale.php` lines 106–107.
- `subtotal` → `total`. Add `cost_price` (SELECT current product.cost_price WITHIN tx FOR UPDATE).
- Test: snapshot persists; margin тестове в stats.php pass.

### Step 4 — Wrap save_sale в `DB::tx()` (45 min)

- Файл: `sale.php` lines 85–119.
- Replace `$pdo->beginTransaction()` → `return DB::tx(function($pdo) use (...) { ... });`
- Convert `try/catch` → DB::tx-internal (rethrow → caller catches).
- Test: simulate deadlock (concurrent sales same product) → no double-spend.

### Step 5 — Add `inventory_events` INSERT (1h)

- Файл: `sale.php` save_sale handler.
- Per item: SELECT inventory.quantity FOR UPDATE → calc `qty_after` → UPDATE → INSERT event.
- Idempotency check: `WHERE NOT EXISTS (SELECT 1 FROM inventory_events WHERE reference_type='sales' AND reference_id=? AND product_id=?)`.
- Test: replay save_sale с същия body → 2-ри INSERT skipped.

### Step 6 — Add `audit_log` INSERT (20 min)

- Reuse `auditLog()` от `config/helpers.php`.
- `auditLog($user, 'create', 'sales', $sale_id, null, ['total'=>$total,'items'=>count($items)], 'ui', 'sale.php');`
- Test: row in `audit_log`.

### Step 7 — Wire `search_log` + `lost_demand` (45 min)

- Файлове: `sale.php` `quick_search` handler + `barcode_lookup` handler.
- `logSearch($tenant, $store, $user, $q, $resultsCount, 'sale')` след `quick_search` query.
- `recordLostDemand(... 'barcode_miss')` ако barcode_lookup → null.
- `recordLostDemand(... 'search')` ако quick_search results=0.
- Test: search "abc" → row в search_log; barcode "999" → row в lost_demand.

### Step 8 — Fix `sale-voice.php` response schema (30 min)

- Файл: `sale-voice.php` (peripheral, allowed).
- AI prompt update → връщай `action` discriminator.
- Test: voice "Nike 42 черни" → `{action:"add_items", items:[...]}`.

### Step 9 — Wholesale price refetch on toggle (45 min)

- Add `?action=refetch_prices` endpoint в `sale.php`.
- POST body: array of product_ids; response: `{[id]: {retail, wholesale}}`.
- JS `selectClient` → call → patch STATE.cart prices.
- Test: cart с 3 items → toggle Едро → prices update.

### Step 10 — `payment_method='transfer'` JS string fix (5 min)

- Файл: `sale.php` line ~1064.
- `data-method="transfer"` → `data-method="bank_transfer"`.
- Test: confirmPayment с transfer → enum accepted.

### Step 11 — Camera fallback UX toast (15 min)

- Файл: `sale.php` `startBarcodeScanner`.
- При `!('BarcodeDetector' in window)` → `showToast('Камерата не сканира на този браузър — въведи код ръчно', 'warning', 4000)`.
- Test: Safari 14 → toast shown.

### Step 12 — Extract `partials/sale-payment.php` (45 min)

- Move `.pay-sheet` markup (line 1052–1087) → `partials/sale-payment.php`.
- Include в sale.php main.
- Test: visual identical, no JS regression (IDs preserved).

### Step 13 — Diff render perf для edro 50+ items (30 min)

- Файл: JS `render()`.
- Replace `zone.querySelectorAll('.cart-item').forEach(el => el.remove())` + rebuild → diff: `for (i...) if (existing[i] !== STATE.cart[i]) replace`.
- Bench: 50 items → 1 add → must be < 50ms paint.

### Step 14 — Parked sales: optional DB backing (1h)

⚠️ Conditional on §10 Q3 approval. Ако yes:
- DDL: `parked_sales` table (id, tenant_id, store_id, user_id, cart_json, parked_at).
- Replace `localStorage` writes с POST `?action=park_sale` + DELETE on resume.
- Migration: import existing localStorage entries to DB on first load.
- Test: park, switch device, resume на втори device.

Ако no: skip step.

### Step 15 — Diagnostic Cat A + D run + commit (30 min)

- Rule #21 — AI logic touch (sale changes affect inventory). Run:
  `python3 tools/diagnostic/run_diag.py --module=insights --pristine`
- Cat A + D must be 100%.
- If pass → final commit `S87.SALE.REWRITE: fix schema + audit trail + 14 steps`.
- If fail → rollback step 14, escalate.

**Total estimated time:** ~10h work, ~6h test/review = **16h** (2 sessions от ~8h, или 1 marathon).

---

## 6. Test Scenarios

20 manual QA scenarios. Each: prerequisite → action → expected.

| # | Scenario | Expected |
|---|---|---|
| 1 | **Barcode happy path** — продукт `code=A001`, baracode `8888`, stock=10 — сканирай | Beep, +1 cart row, stock UPDATE → 9, inventory_events INSERT, no errors |
| 2 | **Barcode unknown** — сканирай `12345` | Toast „Баркод не е намерен", `lost_demand` INSERT (source='barcode_miss', times=1) |
| 3 | **Barcode unknown повтаря 5 пъти** | `lost_demand.times=5`, един row (idempotent merge) |
| 4 | **Voice "Nike 42 черни"** | Voice overlay → READY → tap → fuzzy match → add to cart |
| 5 | **Voice unclear** | `recognition.onerror` → toast „Не разбрах" → 1.5s auto-close |
| 6 | **Cart 5 items + 10% global discount + cash + точна сума** | sales.total = correct, sales.discount_amount calc, change=0, ching sound |
| 7 | **Park sale → resume от друг tab** | localStorage updates, парковете виждат се на другата таб (DB-backing — синхронизира между девайси, ако Step 14 done) |
| 8 | **Cancel sale след confirmPayment** | sales.status='canceled', UPDATE inventory ROLLBACK (+qty), inventory_events INSERT type='correction' |
| 9 | **Offline → confirmPayment** | IndexedDB queue (S88+ — за S87 toast „Offline mode WIP") |
| 10 | **Bluetooth print receipt** | DEFER (out of scope S87) |
| 11 | **Customer loyalty match** | DEFER (loyalty_customers table missing) |
| 12 | **Variant product (sizes 38–44)** | Long-press cart row → popup → choose size → cart row updates `meta` |
| 13 | **Race: продукт изчерпан по време на касиране** — paralel inventory drop: stock=1, две паралелни confirms | DB::tx 2nd attempt → SELECT FOR UPDATE → quantity=0 → throw „Out of stock" → rollback → toast |
| 14 | **Wholesale toggle с 3 items в cart** | Step 9: GET refetch → cart prices update → header border indigo-400 |
| 15 | **Discount 25% при max_discount=20%** | Toast warning „Превишен лимит" + clamp → 20% |
| 16 | **Сума получено < total** | btnConfirm disabled, no flicker |
| 17 | **Search „nike"** → 0 results | `lost_demand` INSERT source='search' + toast „Записах: nike" |
| 18 | **Park 5 paral sales** | Badge `🅿️5`, list overlay показва 5 carda (newest top), all open animations stagger |
| 19 | **Theme toggle по време на cart 10 items** | Re-render не губи cart, theme persists в localStorage |
| 20 | **Capacitor (mobile) → BarcodeDetector unavailable** | Step 11 toast — fall-back UX clear |

**Diagnostic harness coverage:** Тhose tests които пишат в DB (1, 2, 3, 6, 8, 17) могат да се добавят в `tools/diagnostic/modules/sale/` като Cat A scenarios (DB integrity check) + Cat D (regression).

---

## 7. Risk Matrix

10 risks по probability × impact.

| # | Risk | P | I | Mitigation |
|---|---|---|---|---|
| 1 | Schema drift broke save_sale в production sake | High | 🔴 Critical | Step 2+3 first; tenant=99 smoke before tenant=7 deploy |
| 2 | Race condition: concurrent sales → double inventory dec | Med | 🔴 Critical | DB::tx + SELECT inventory FOR UPDATE; idempotency uniq index on inventory_events |
| 3 | Camera scanner stops working на iOS Safari | High | 🟡 Major | Step 11 fallback toast + manual code path винаги наличен |
| 4 | Voice API quota / Anthropic 429 → casa bloked | Low | 🟡 Major | sale-voice.php returns 429 → JS fall-back на text search; toast „Гласът временно не работи" |
| 5 | Offline queue corrupt → загубени sales | Low | 🔴 Critical | DEFER S88; за S87 toast „Offline WIP"; IndexedDB write idempotent |
| 6 | Parked sale localStorage corrupt → user заглъхва | Med | 🟡 Major | Try/catch JSON.parse + recovery toast „Reset parking"; migrate to DB (Step 14) |
| 7 | DB deadlock на peak hours (5 sellers paralel) | Med | 🟡 Major | DB::tx auto-retry (S80.A) — exponential backoff |
| 8 | `cost_price` snapshot stale (product cost mid-day update) | Low | 🟢 Minor | SELECT FOR UPDATE within tx — snapshot е moment-in-time на sale |
| 9 | `payment_method='transfer'` legacy rows след fix | Low | 🟢 Minor | Migration script: `UPDATE sales SET payment_method='bank_transfer' WHERE payment_method='transfer'` (no rows expected since save was broken) |
| 10 | Diagnostic Cat A regression от schema fix | Med | 🟡 Major | Run Cat A+D (Rule #21) преди commit; ако fail → rollback на step 2 |
| 11 | Bottom-nav include collision (S82.UI shell) | Low | 🟢 Minor | Visual diff на partials/bottom-nav.php; reuse exists |
| 12 | mobile virtual keyboard accidently shows | Low | 🟡 Major | All `<input>` elements в sale.php → `inputmode="none"` + `readonly` (вече така е) |

---

## 8. Estimated Total Time

| Phase | Hours | Cumulative |
|---|---|---|
| Steps 1–4 (schema + tx) | 2.5 | 2.5 |
| Steps 5–8 (audit + voice fix) | 2.0 | 4.5 |
| Steps 9–11 (wholesale + UX) | 1.5 | 6.0 |
| Steps 12–13 (refactor + perf) | 1.5 | 7.5 |
| Step 14 (parked DB-backed) | 1.0 | 8.5 |
| Step 15 (diagnostic + commit) | 0.5 | 9.0 |
| **Manual QA (20 scenarios)** | 4.0 | 13.0 |
| **Buffer / unforeseen (~25%)** | 3.0 | **16.0** |

**Best case:** 1 marathon session 8h (Code #1 + Тихол shadow QA).  
**Realistic:** 2 sessions (S87 + S87.5) ~16h spread.

---

## 9. Dependencies

### 9.1 DB tables (read)

- `tenants`, `users`, `stores`, `customers` (wholesale), `products`, `inventory`, `product_variations` (для long-press popup variants).
- `sales`, `sale_items` (за parked resume + cancel reverse).

### 9.2 DB tables (write)

- `sales` (INSERT, UPDATE on cancel)
- `sale_items` (INSERT)
- `inventory` (UPDATE quantity)
- `inventory_events` (INSERT) — **NEW write от S87**
- `stock_movements` (INSERT, type='out' or 'sale')
- `search_log` (INSERT) — **NEW write от S87**
- `lost_demand` (INSERT/UPDATE merge) — **NEW write от S87**
- `audit_log` (INSERT) — **NEW write от S87**
- (optional) `parked_sales` (DDL pending §10 Q3)

### 9.3 Helpers (config/helpers.php)

- `logSearch()` — reuse
- `recordLostDemand()` — reuse
- `auditLog()` — reuse
- `fmtMoney()`, `fmtQty()` — reuse за UI

### 9.4 Library (config/database.php)

- `DB::tx()` — wrap save_sale

### 9.5 Peripheral PHP (read-only за S87 main, except)

- `sale-search.php` — currently UNUSED, не подлежащ на rewrite сега
- `sale-voice.php` — Step 8 schema fix
- `sale-save.php` — UNUSED legacy duplicate; recommend DELETE in S87.5 (виж §10 Q7)

### 9.6 Browser APIs

- `BarcodeDetector` (Chromium 83+, Safari 16.4+)
- `webkitSpeechRecognition` (Chromium, Safari 14.1+)
- `getUserMedia` (universal)
- `Vibration API` (no Safari iOS)
- `IndexedDB` (universal — для S88 offline queue)

### 9.7 Capacitor bridge

- Не е directly зависимост в S87. Bluetooth receipt — DEFER.
- Camera: works през Capacitor WebView fallback на native.

---

## 10. Open Questions (за Тихол approval преди S87 start)

| # | Q | Recommended A |
|---|---|---|
| **Q1** | Mockup files `sale-payment-v3.html` и `sale-v5.html` посочени в задание — НЕ съществуват в repo. Има ли ги локално и да ги paste-неш? Или да продължаваме само с COMPASS spec? | **Paste mockups ако ги имаш** — иначе COMPASS §sale.php е ground truth |
| Q2 | `payment_method` enum няма `'mixed'` (per BIBLE_TECH §14.1). Засегнат ли е flow за splitting cash+card? | За beta launch ENI: keep ENUM, defer mixed payments to Phase B |
| Q3 | Parked sales — DB-backed `parked_sales` table или localStorage остава? | **DB-backed** — за multi-device & за shef-chat visibility (recommend Step 14) |
| Q4 | `stock_movements.type` за продажба — `'out'` (current) или `'sale'` (по-семантично)? | **`'sale'`** — за clarity и stats join-ове |
| Q5 | Bluetooth receipt print автоматично след `confirmPayment` — да или не за beta launch? | **Опционално бутон в success toast** — не задължителен flow |
| Q6 | `loyalty_customers` table — да създадем за S87 или Phase B? | **Phase B** — не блокер за beta launch |
| Q7 | `sale-save.php` (91 реда, UNUSED, broken schema) — delete или keep? | **DELETE в S87.5** (cleanup след rewrite passes Cat A) |
| Q8 | Диагностично: dry-run flag за save_sale (return what would be inserted, no DB write) — useful за debugging? | **Yes, low cost** — 5 min Step add |
| Q9 | Sale `note` колонка — къде идва от UI? Voice "за Иван" → note? | **Voice transcription с keyword "забележка" → note** — Phase B logic |
| Q10 | Cat A scenarios за sale module — кога ги добавяме? | **S87.5** след rewrite stable; tools/diagnostic/modules/sale/ нов dir |

---

## 11. Sign-off Checklist

Преди S87 start:

- [ ] Тихол approves §10 Q1–Q10
- [ ] mockup paste-нати (или confirm "use COMPASS only")
- [ ] `parked_sales` DDL ready (ако Q3 = yes)
- [ ] tenant=99 smoke environment (sales seeded — ✅ done в S87 testing loop)
- [ ] backup `sale.php` → `.s86_pre_rewrite.bak` (Step 1)
- [ ] Rule #21 diagnostic baseline run (current Cat A=100%/D=100% ✅)
- [ ] Code session allocated (~8h block, single Code #1 пожелателно)

---

**Document version:** v1.0 — 27.04.2026  
**Next review:** Преди S87 kickoff (target 04.05.2026)  
**Owner:** Code #1 (S87 lead)  
**Reviewers:** Тихол, шеф-чат
