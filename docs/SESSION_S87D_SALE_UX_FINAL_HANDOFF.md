# 🛒 S87D.SALE.UX_FINAL — Handoff

**Сесия:** S87D.SALE.UX_FINAL — Major UX overhaul
**Дата:** 2026-04-28
**Файл:** `/var/www/runmystore/sale.php` (~3072 реда)
**Базиран на:** S87B (commit `0fc0092`)
**Mockup:** `/var/www/runmystore/docs/SALE_V5_MOCKUP.html`

## Commits (3 + handoff)

| Phase | Commit | Δ lines | Description |
|---|---|---|---|
| UX1 | `657a8ab` | +113 / −6 | Layout cleanup |
| UX2 | `7c2477f` | +401 / −1 | Search Overlay full-screen |
| UX3 | `7239e12` | +223 / −153 | Payment simplify |
| Doc | `<this>` | — | Handoff doc |

Backups (untracked):
- `sale.php.bak.s87d_c1_0905`
- `sale.php.bak.s87d_c2_<HHMM>`
- `sale.php.bak.s87d_c3_<HHMM>`

---

## Phase 1 — Layout cleanup (UX1)

### What changed
- **`<body class="sale-page">`** added → enables CSS overrides
- **Bottom nav hidden** on sale.php only (`body.sale-page .rms-bottom-nav { display:none !important }`)
- **Swipe gestures disabled** (`#saleWrap { touch-action: pan-y; overscroll-behavior: contain }`)
- **Tabs ДРЕБНО/ЕДРО** moved INSIDE `cam-overlay` top bar as `.cam-tabs` (replaces `cam-title`)
  - New IDs: `#camTabs`, `#camTabRetail`, `#camTabWholesale`
  - Old `#tabsPill` (between cam-header and search-bar) now hidden via `body.sale-page #tabsPill { display:none }` — kept in DOM for legacy `render()` sync
- **🎤 emoji → SVG mic** at: `#recSend` (rec overlay), `#btnVoiceSearch` (search bar), numpad mic
- **Camera scan area enlarged** (130px instead of 80px height) — corners 20px, status text "НАСОЧИ КЪМ БАРКОДА"
- **ПЛАТИ + Парк buttons** smaller (38px instead of 42px)
- **Custom keyboard + numpad hidden** on sale.php (search uses overlay with native keyboard)
- **Search-display tap → openSearchOverlay()** (also АБВ button rewired)
- **Cart gets all available space** (`flex:1 1 auto; min-height:260px`)

### Preserved (NOT touched)
- All STATE structure
- All JS handlers: `addToCart`, `doSearch`, `openWholesale`, `openParked`, `parkSale`, `selectClient`, etc.
- All DOM IDs that JS depends on (legacy `tabRetail`/`tabWholesale` stubs kept)
- Theme toggle, debug overlay long-press, voice STT recognition
- `partials/header.php`, `partials/bottom-nav.php`, `css/shell.css`, `css/theme.css`

---

## Phase 2 — Search Overlay (UX2)

### Backend adapt
`quick_search` AJAX (sale.php:36-55) now returns:
- `id, code, name, retail_price, wholesale_price, barcode, stock` (existing)
- **NEW:** `parent_id, color, size, image_url`
- LIMIT 10 → 30 (so variant grouping shows >1 variant per master)

### Frontend
- New `<div class="search-ov" id="searchOverlay">` full-screen at end of `<body>`
- **State A** — master products list (search input + results)
- **State B** — variants screen (color swatch, size, stock, price, [+])
- **Tap [+] in State B** → `addToCart(product)` + green toast — does NOT close overlay (multi-add)
- **Tap master with single variant** → addToCart directly (skip State B)
- Native keyboard via `<input type="text" inputmode="search">` + `setTimeout(focus, 100)`
- Debounce 250ms on input; Enter triggers immediate search
- Hardware back (Capacitor `backbutton` event): State B → State A → close
- Voice mic in overlay reuses existing `startVoice()`

### Variant grouping logic
```js
key = parent_id ? 'p<parent_id>' : 'n:<name.toLowerCase()>'
```
Falls back to name-grouping when product has no parent_id (50% of products are master-only — they appear as 1 master with 1 variant; the fast-path skips State B for these).

### Color swatch fallback
`srchOvColorToCss(color_string)` maps Bulgarian/English color names (червен, син, зелен, …) to hex. Unknown colors → neutral grey swatch. The DB `products.color` column is a string; there is no `color_hex`, so this is best-effort.

---

## Phase 3 — Payment simplify (UX3)

### What changed
- **From:** bottom-sheet with backdrop + 4 methods (cash/card/bank_transfer/deferred) + V5 pkg-card design
- **To:** full-screen overlay + 3 methods (КЕШ/КАРТА/ПРЕВОД) + simple pill buttons
- **Editable input** for "Точна сума получена" (`<input type="text" inputmode="decimal">`)
- **Banknotes ADD to input** (real cashier behavior — was: replace)
- **ТОЧНО button** = sets input to total
- **Auto-resto** below banknotes (РЕСТО · NN,NN €)
- **Big confirm button** with inline amount (ПОТВЪРДИ ПЛАЩАНЕ NN,NN €)
- ОТЛОЖЕНО (deferred) **dropped** from UI

### JS preserved/replaced
- `openPayment` — rewrites overlay state, pre-fills input with total
- `closePayment` — `style.display='none'` + remove `overlay-open`
- `setPayMethod(method, skipShow)` — simple `.active` toggle on `.pay-method-pill`
- `payBanknote(amt)` — adds to input value (not replaces)
- `payExact()` — new — sets input to total
- `payCalcChange()` — new — reads input, syncs `STATE.receivedAmount`, recalcs resto + confirm enable
- `updatePayment()` — kept as STATE→input sync wrapper (voice/numpad legacy callers)
- `updatePmCardActive()` — no-op alias (legacy V5 pkg-card removed)
- `confirmPayment` — UNCHANGED (uses `STATE.payMethod`, `STATE.receivedAmount`)

### Backend save_sale (UNCHANGED)
- Accepts payment_method as string — values used: `cash`, `card`, `bank_transfer`
- `received` field required for cash
- No DB migration needed

---

## DOM IDs preserved across all 3 phases

These stay valid — JS handlers continue to work:
- `#payOverlay`, `#payDueAmount`, `#payDueCur`, `#payRecvAmount` (now input.value), `#payChangeBox`, `#payChangeAmount`, `#btnConfirm`, `#cashSection`
- `#searchDisplay`, `#searchInput`, `#searchResults`, `#btnVoiceSearch`, `#nfPopup`
- `#cartZone`, `#cartEmpty`, `#actionBar`, `#btnPay`
- `#camHeader`, `#cameraVideo`, `#camTitle` (hidden), `#themeToggle`, `#greenFlash`
- `#tabRetail`, `#tabWholesale` (legacy, hidden)
- New: `#camTabRetail`, `#camTabWholesale`, `#searchOverlay`, `#srchStateA/B`, `#srchOvInput`, `#srchOvResults`, `#srchOvVariants`, `#payConfirmAmount`

---

## Test checklist

### Sale main screen
- [ ] Bottom nav скрит (само на sale.php — header.php navbar остава)
- [ ] Tabs ДРЕБНО/ЕДРО във вътрешността на cam-overlay (не в отделен ред под cam)
- [ ] Микрофон на search bar = SVG (не emoji)
- [ ] Камера scan area ≈130px високо (по-голяма)
- [ ] "НАСОЧИ КЪМ БАРКОДА" текст под scan
- [ ] ПЛАТИ + Парк бутоните ≈38px (по-малки)
- [ ] Cart показва 5+ items без scroll
- [ ] Swipe left/right disabled (pull-to-refresh OK)

### Search Overlay
- [ ] Tap на search-display → отваря full-screen overlay
- [ ] Native keyboard auto-opens (input focus)
- [ ] Резултати = master products (групирани по parent_id или име)
- [ ] Single-variant master → tap = addToCart директно (skip State B)
- [ ] Multi-variant master → tap = State B
- [ ] State B показва color swatch + size + stock + price + [+]
- [ ] Tap [+] → toast "✓ <name> добавен" + остава на overlay
- [ ] ← в State B → връща в State A (input запазен)
- [ ] ✕ → затваря overlay, връща в Sale
- [ ] Hardware back на Android: State B → State A → close

### Payment
- [ ] 3 методи (КЕШ/КАРТА/ПРЕВОД), активният е indigo gradient
- [ ] Input "Точна сума получена" = editable (тапни → клавиатура)
- [ ] Банкнота 5 → добавя 5 към input (не replace)
- [ ] Банкнота 100 → добавя 100
- [ ] ТОЧНО → input = total
- [ ] Ресто се пресмята автоматично
- [ ] При card/bank_transfer: cashSection скрита, confirm винаги enabled
- [ ] При cash: confirm disabled докато recv < total
- [ ] Голям ПОТВЪРДИ ПЛАЩАНЕ бутон с inline сума
- [ ] Confirm → save_sale POST → toast "Продажба #N записана"

### Regression
- [ ] addToCart от scanner (баркод) още работи
- [ ] Bulk-banner (паркирани) още показва бр. + сума
- [ ] Voice flow (рекординг overlay) още работи
- [ ] Theme toggle (☀️/🌙) още работи
- [ ] Debug overlay long-press на cam-status още работи

---

## DEFER list (S87E / S88+)

### Variant UX
- **Real product image thumbnails** в `srch-master-ico` (currently `image_url` if present, иначе 📦 placeholder). DB колоната `image_url` е попълнена за ~10 / 804 продукта.
- **Color hex column** — DB has `products.color` (string only). `color_hex` would need migration. Currently `srchOvColorToCss()` maps Bulgarian/English names heuristically.
- **AI search synonyms** — voice/text "червена тениска L" → match across attributes (out of scope).

### Backend
- **Bug #5: Parked sales DB-backed** — изисква `parked_sales` DDL + migration (currently localStorage).
- **Bug #9: Print bridge DTM-5811** (Capacitor) — separate session.
- **Bug #11 i18n** — ~40+ hardcoded UI strings hardcoded BG.
- **DB::tx() wrap, inventory_events, audit_log, search_log** — S87 PLAN Steps 4-7.

### Edge cases noticed
- `payCalcChange()` reads input.value as comma-decimal ("125,50" → 125.50). If user types only "." (US format), parse OK. If user pastes "125.50,00" (junk), parse → NaN → 0. No validation popup.
- `srchOvSearch` uses `LIMIT 30` — if a popular product name has >30 variants (e.g., "тениска"), some variants are dropped. Not common, but worth noting.
- Voice flow: `srchOvVoice()` calls `startVoice()` which opens its own recording overlay; the search overlay stays in background. Voice result is still injected via existing `STATE.searchText` flow — tested only by code review, not on device.

---

## Known limitations
1. **No tests written** — pure manual QA on device required.
2. **Backend `payment_method` enum** — `bank_transfer` value matches existing schema; `transfer` would have required schema check. Kept `bank_transfer` as canonical value.
3. **Old `.pay-overlay` legacy CSS** at line 546-551 is overridden via `body.sale-page .pay-overlay { opacity:1 !important }` — if the body class is ever removed elsewhere, the payment overlay becomes invisible.
4. **`srchOvColorToCss` heuristic** — hardcoded Bulgarian + English color name map. New languages will fall back to grey.

---

## Sign-off

S87D завършен 2026-04-28 ~09:30. Всички 3 commit-а pushнати на main. PHP lint pass. Backups safe.

For QA on device: hard-reload, then test in order — Sale main → Search overlay → Add 2-3 items → Payment cash → Payment card → Park sale.
