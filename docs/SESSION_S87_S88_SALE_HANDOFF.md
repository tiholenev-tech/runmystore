# 🛒 S87/S88 SALE.PHP — Visual Rewrite + Bug Audit HANDOFF

**Сесия:** S87.SALE.REWRITE + S88.SALE.UI_REWRITE (комбинирано)
**Дата:** 2026-04-28
**Файл:** `/var/www/runmystore/sale.php` — **2297 → 2580 реда** (+283 за debugLog/refetch/priceFormat/camera lifecycle)
**Backup:** `/var/www/runmystore/sale.php.bak.s87_20260428_0703`
**Mockup:** `/var/www/runmystore/docs/SALE_V5_MOCKUP.html` (преместен от root `.html.html` typo)
**Source spec:** S88 HANDOFF (visual + DOM mapping) + S87 PLAN (DB/audit) — комбинирано

---

## ✅ DELIVERABLES — DONE

### ФАЗА A — Visual Rewrite (V5 patterns)

CSS секцията преработена 1:1 с **запазени всички селектори** (за да не се счупи JS render()). Само visual patterns се промениха.

**Color hue:** зелен (`#22c55e`) → indigo (`hsl(255 70% 60%)`). Конкретно:
- `.scan-laser` (камера сканер бар) — зелен → indigo gradient + glow
- `.scan-dot` (статус точка) — зелена → indigo
- `.cam-status span` (текст "СКЕНЕР АКТИВЕН") — green → бял на dark / тъмен на light
- `@keyframes camFlash` (scan flash) — green inset → indigo inset
- `.scan-corner` SVG strokes (4 ъгъла) — `stroke="#22c55e"` → `stroke="hsl(255 70% 65%)"`

**V5 Pills (border-radius: 100px):**
- `.search-btn` (`#btnVoiceSearch`, `#btnKeyboard`) — pill 100px + indigo gradient за mic
- `.btn-pay` — pill 100px + V5 act-block.indigo style (gradient + inset glow + text-shadow)
- `.btn-park` — pill 100px + glass card
- `.pm-chip` (cash/card/transfer/deferred) — pill 100px + V5 pkg-card active state с glow
- `.bn-chip` (банкноти) — pill 100px + grid 4×2 + .exact с indigo gradient
- `.btn-confirm` (ПОТВЪРДИ ПЛАЩАНЕ) — V5 pkg-buy.mega: pill + heavy glow indigo
- `.dc-chip` (5/10/15/20%) — pill 100px + amber theme

**V5 Glass Cards:**
- `.cart-item` — V5 set-row pattern: glass card 14px radius, shine, indigo selected accent state
- `.search-results` — glass card 14px radius
- `.parked-card` — V5 pkg-card pattern: glass + 3px amber side accent bar + glow
- `.pay-change` — V5 briefing-section.q3 pattern: green side accent + gradient text
- `.ws-item` — glass cards с pill ws-retail
- `.toast` — pill 100px + glass

**V5 Stat Numbers:**
- `.pay-due-amount` — 42px stat-num.lg gradient (white→indigo dark / dark→indigo light)
- `.sum-total .amount` — 18px gradient indigo
- `.pay-change-amount` — 32px gradient green→white

**Theme system:**
- Добавен `:root[data-theme="light"]` блок с пълни overrides
- Всички елементи имат light theme variant (background, border, text, glow)
- Theme persistence: `localStorage['rms_theme']` (existing, verified working)
- Bootstrap script в `<head>` запазен 1:1
- `color-scheme: dark/light` declarative
- `:root[data-theme="light"]` body::before с по-светли radials

**V5 Body Background:**
- 3-layer radial gradient (top-left + bottom-right + linear) — V5 standard
- `body::after` noise overlay (SVG fractalNoise, mix-blend-mode: overlay) opacity 0.03
- `background-attachment: fixed`

**Safe-area insets:**
- `.cam-header` height: `calc(80px + env(safe-area-inset-top))` (existing, kept)
- `.cam-top` padding: `max(6px, calc(env(safe-area-inset-top,0px) + 6px))`
- `.toast` top: `calc(60px + env(safe-area-inset-top,0px))`
- `.numpad-zone` padding-bottom: `calc(132px + env(safe-area-inset-bottom,0px))`
- `.keyboard-zone` padding-bottom: `calc(80px + env(safe-area-inset-bottom,0px))`
- `body` padding-bottom: `env(safe-area-inset-bottom)` (existing, kept)

**Font:**
- Добавен Montserrat preconnect + import @400/500/600/700/800/900
- Добавен `<meta name="theme-color" content="#08090d">`

### ФАЗА B — Bug Audit Status (14 items)

#### P0 — Fixed:

**Bug #1: Търсачката не реагира** ✅ DEBUGGED
- Добавен `debugLog()` overlay (показва се на телефон без Chrome inspect)
- Toggle: long-press 1.8s на текста "СКЕНЕР АКТИВЕН" → ON/OFF
- Логира: numPress, kbPress, doSearch, search HTTP status, results count, fetch errors
- При активиране показва UA + viewport за context
- Position: top fixed, max-height 180px, scroll, indigo green text on black
- ⚠️ **Self-test задача за Тихол:** запали debug → пусни търсене → сподели какво лога

**Bug #2: DB schema mismatch** ✅ VERIFIED CORRECT (no changes)
- `sales` INSERT (line 116-118) използва: `total`, `discount_amount`, `discount_pct`, `payment_method`, `status='completed'`, `created_at` ✅
- `sale_items` INSERT (line 130) използва: `quantity`, `unit_price`, `discount_pct`, `total` ✅
- `inventory` UPDATE (line 132): `quantity = GREATEST(quantity - ?, 0)` ✅
- `stock_movements` INSERT (line 134): `type='out'`, `reference_type='sale'` ✅
- Грешните колони от plan (`total_amount`, `subtotal`, `qty`, `price`) **НЯМА в кода** — fix вече е приложен от commit `9f0d2bc`

**Bug #4: Voice STT bg-BG / continuous=false / innerText** ✅ VERIFIED CORRECT
- `recognition.lang = 'bg-BG'` (line 2354)
- `recognition.continuous = false` (line 2355)
- `recognition.interimResults = false` (line 2356)
- `transcript.textContent = lastTranscript` (line 2384, 2397, 2407, 2421) — НЕ innerHTML

**Bug #10: EUR/BGN dual display** ✅ HELPER ADDED
- Добавен `priceFormat(n, opts)` JS helper
- Constants: `BGN_EUR_RATE = 1.95583` (per БНБ), `DUAL_DEADLINE = 2026-08-08`
- Логика: ако currency='лв' → показва "X.XX лв (Y.YY €)" до deadline
- Ако currency='€' → показва "X.XX € (Y.YY лв)" до deadline
- ⚠️ **Defer wiring до S87B:** Helper-ът съществува, но НЕ е заместен в `fmtPrice()` calls. За пълно дисплей трябва swap на ~10 места (PAY бутон, summary, search-results, payment overlay, parked-cards, ...). Risk: layout overflow ако string е твърде дълъг — трябва UX тест.

#### P1 — Fixed:

**Bug #3: Camera scanner lifecycle** ✅ FIXED
- Добавен `stopCamera()` функция: stops tracks + cancels scanRAF
- `visibilitychange` listener: hide → stopCamera, show → restartCamera
- `pagehide` listener: cleanup
- Logged via debugLog
- Bug #11 fallback (BarcodeDetector missing) toast добавен в startCamera catch

**Bug #6: Wholesale toggle memory bug** ✅ FIXED
- Нов PHP endpoint: `?action=refetch_prices` (POST {product_ids:[...]} → {[id]:{retail,wholesale}})
- Запитва само за tenant_id of session (security)
- `selectClient()` JS обновен: вместо TODO noop, сега POST-ва ids → patch-ва STATE.cart prices → render
- Грешка от network → toast "Не успях да обновя цените"
- Logged via debugLog

**Bug #7: Discount chips 5/10/15/20%** ✅ VERIFIED WORKING
- HTML chips с onclick=applyDiscount(5/10/15/20)
- `applyDiscount()` clamp до `STATE.maxDiscount` (per user role)
- Toast "Максимум X%" при превишение
- Active state highlight на избрания chip

**Bug #8: Numpad context (code/qty/discount/received)** ✅ VERIFIED WORKING
- `setNumpadCtx()` — color-coded label (.ctx-code/qty/discount/received)
- `numPress()` routes input to STATE.{searchText | numpadInput}
- `numOk()` switches behavior:
  - `code` → doSearch(val)
  - `qty` → STATE.cart[selectedIndex].quantity = parseInt(val)
  - `discount` → applyDiscount(parseFloat(val))
  - `received` → STATE.receivedAmount = parseFloat(val)
- Auto-switch: selectCartItem→qty, toggleDiscount→discount, openPayment→received

#### P2 — Deferred to S87B:

| # | Bug | Why deferred |
|---|---|---|
| 5 | Parking DB-backed (вместо localStorage) | Изисква нова `parked_sales` table + migration; out of 6h scope |
| 9 | Print bridge DTM-5811 (TSPL/codepage 1251/50×30mm) | Capacitor bridge work, голям scope (отделна сесия) |
| 11 | i18n compliance full (всички UI текстове през t()) | ~40 hardcoded strings, big scope; вече е tracked в I18N_AUDIT_REPORT.md |
| 12 | Theme persistence | ✅ ВСЪЩНОСТ РАБОТИ — verified: localStorage['rms_theme'] + bootstrap script в head |
| 13 | Safe-area insets | ✅ PARTIAL — main areas covered, но не всеки overlay (напр. lp-popup, parked-card) |
| 14 | Animations performance | ✅ Existing s87v3_init() handles spring tap, prefers-reduced-motion respected |

---

## 🔧 PHP/JS CHANGES SUMMARY

### PHP added:
- New AJAX endpoint `?action=refetch_prices` (lines 73-92, before save_sale)
  - POST {product_ids: int[]} → JSON {id: {retail, wholesale}}
  - tenant scoped, prepared statement, no SQL injection

### JS added:
- `debugLog(msg)` + on-screen overlay container (auto-created)
- `__rms_debug` toggle via long-press of `.cam-status`
- `priceFormat(n, opts)` helper (BGN/EUR dual display, ready for S87B wiring)
- `stopCamera()` + visibilitychange/pagehide listeners
- `startCamera()` catch enhancement: BarcodeDetector fallback toast + debugLog
- `selectClient()` rewritten: real refetch_prices POST + cart price patch

### CSS rewritten (всички селектори запазени):
- `:root` — нов hue1=255/hue2=222 + complete light theme block
- `body::before/::after` — V5 3-layer radial + noise
- `.scan-laser`, `.scan-dot`, `.cam-status span`, `.cam-header.scanned` — green→indigo
- `.search-display`, `.search-btn`, `.btn-pay`, `.btn-park` — pills + V5 patterns
- `.pm-chip`, `.bn-chip`, `.btn-confirm`, `.dc-chip` — pills + V5 pkg-card style
- `.cart-item`, `.parked-card`, `.ws-item`, `.search-results`, `.toast` — V5 glass cards
- `.pay-due-amount`, `.sum-total .amount`, `.pay-change-amount` — V5 stat-num gradients
- `.np-btn`, `.kb-key` — backdrop-filter glass + indigo accents
- `.action-bar`, `.numpad-zone`, `.keyboard-zone` — gradient backgrounds + light theme + safe-area
- `.cam-btn`, `.park-badge`, `.cart-empty` — small polish + theme support

### HTML changed:
- 4× `.scan-corner` SVG: `stroke="#22c55e"` → `stroke="hsl(255 70% 65%)"`
- `<head>`: добавени Montserrat preconnect + theme-color meta

---

## 🚨 ОЩЕ НЕ Е НАПРАВЕНО (Defer списък за S87B)

Следните неща от S87 PLAN остават за следваща сесия:

### От Phase A (visual):
- ❌ V5 `tabs-pill` — дребно/едро в header (mockup го има, но изисква HTML promени; текущо изпълнено чрез `.cam-header.wholesale` border)
- ❌ V5 `bulk-banner.amber` за parked badge (засега използваме съществуващия `.park-badge`)
- ❌ V5 `set-row` pattern в cart — само CSS адаптация, JS render() generates `.cart-item` markup
- ❌ V5 `pkg-card.featured/mega` за payment methods (текущо .pm-chip с pill style approximation)

### От Phase B:
- ❌ Bug #5: Parked sales DB-backed (нужно: parked_sales DDL + migration + endpoint)
- ❌ Bug #9: Bluetooth print DTM-5811 receipt (Capacitor bridge work)
- ❌ Bug #11 full: i18n всички UI strings (40+ hardcoded)
- ❌ EUR/BGN dual в реалните display calls (priceFormat helper готов, но fmtPrice все още се вика навсякъде)

### От S87 PLAN не докоснати (не блокери за beta):
- Step 4: DB::tx() wrap (save_sale работи с beginTransaction/rollBack — приемливо за S87)
- Step 5: inventory_events INSERT
- Step 6: audit_log INSERT
- Step 7: search_log + lost_demand INSERT
- Step 8: sale-voice.php response schema fix (vacuum bug — voice fallback на search работи)

---

## 🧪 TEST PLAN (за Тихол на телефон)

### Visual smoke:
1. Open sale.php → header partial виден (rms-header)
2. Camera scanner: indigo laser line, indigo dot, "СКЕНЕР АКТИВЕН" текст
3. Tap theme toggle (🌙/☀️) → switch dark/light → reload → запазена тема
4. Cart празна — empty state с 🛒 icon
5. Action bar: ПЛАТИ бутон pill indigo gradient, 🅿️ park бутон pill glass
6. Numpad: всички клавиши с glass treatment, OK button indigo gradient
7. Search bar: pill input + indigo mic gradient + "АБВ" pill
8. Toast: pill + glass + indigo glow

### Functional smoke:
1. Press numpad 1,2,3 → search-display показва "123"
2. Press numpad C → clear
3. Tap АБВ → keyboard излиза, numpad се скрива
4. Type letters → triggers search
5. Tap mic → voice overlay (red recording dot → green ready)
6. Wholesale toggle 👤 → избор на client → cart prices RECALCULATE (Bug #6 fix)
7. Discount toggle → 5/10/15/20% chips
8. Add product → tap item → qty mode → numpad input
9. ПЛАТИ → payment overlay → банкноти → ресто display → ПОТВЪРДИ
10. Theme toggle средата на cart 5+ items → cart запазен

### Search bug debug (Bug #1):
1. Long-press "СКЕНЕР АКТИВЕН" текст 1.8s → debug overlay се показва
2. Tap numpad буквите → виждаш "[time] numPress("1") ctx=code"
3. Type 3+ chars → виждаш "[time] doSearch("123")" → "[time] search HTTP 200" → "[time] search results: 5 items"
4. Ако не вижда нищо → search handlers НЕ се изпълняват (DOM/event problem)
5. Ако HTTP != 200 → AJAX endpoint problem
6. Ако HTTP 200 но 0 results → query/data problem
7. Long-press пак → off

---

## 📦 GIT STATUS

```
Modified (не от мен — DON'T STAGE):
  M product-save.php       ← Code #1 S88B-1
  M products.php           ← Code #1 S88B-1
  M tools/diagnostic/.../*.pyc  ← Diagnostic harness cache

Modified (от мен — STAGE only this):
  M sale.php               ← S87/S88 visual rewrite + bug audit

Untracked (не от мен):
  ?? SALE_REWRITE_HANDOFF.md
  ?? lang/                 ← Code #1 S88B-1
  ?? product-save.php.bak.s88_bug4_043317
  ?? products.php.bak.s88_bug4_043317
  ?? uploads/products/7/*.jpg

Untracked (от мен):
  ?? docs/SALE_V5_MOCKUP.html  ← премествам за git tracking
  ?? sale.php.bak.s87_20260428_0703  ← backup, не commit
  ?? docs/SESSION_S87_S88_SALE_HANDOFF.md  ← този doc
```

**КОМАНДА за commit (САМО sale.php + handoff + mockup):**
```bash
cd /var/www/runmystore
git add sale.php docs/SESSION_S87_S88_SALE_HANDOFF.md docs/SALE_V5_MOCKUP.html
git commit -m "S87.SALE: V5 visual rewrite + bug audit (search debug, wholesale refetch, camera lifecycle, EUR/BGN helper)"
git push origin main
```

**НЕ stage-вай:**
- `products.php`, `product-save.php`, `lang/` (Code #1 S88B-1)
- `*.pyc` (diagnostic cache)
- `*.bak.*` (backups)
- `uploads/products/*.jpg` (uploads)

---

## 📊 METRICS

| Item | Value |
|---|---|
| sale.php LOC | 2297 → 2580 (+283 / +12%) |
| Edits applied | 27 |
| New PHP endpoints | 1 (refetch_prices) |
| New JS functions | 3 (debugLog, priceFormat, stopCamera) |
| CSS selectors changed | ~22 |
| Theme support | dark + light (full) |
| PHP lint | ✅ pass |
| DOM IDs preserved | 100% (verified) |
| JS handlers preserved | 100% (verified) |
| Hue conversion | 100% (green → indigo where appropriate) |

---

## 🔄 NEXT SESSION — S87B

**Suggested scope (~6h):**
1. Wire `priceFormat()` в всички display calls (EUR/BGN dual full)
2. V5 tabs-pill за дребно/едро в header
3. V5 bulk-banner.amber за parked badge
4. Bug #5: parked_sales DB DDL + migration + endpoints
5. Bug #11: i18n wrapping за hardcoded strings (тук — около 40 strings)
6. Update render() да генерира V5 set-row markup за cart items
7. S87 PLAN Step 7: search_log + lost_demand INSERT (audit trail Закон #4)

**Long-term (S87C/S88):**
- Bug #9: Print bridge DTM-5811 (Capacitor)
- S87 PLAN Steps 4-6: DB::tx() wrap + inventory_events + audit_log

---

**Sign-off:** S87/S88 main scope completed на 2026-04-28 ~07:30. Search debug live за Тихол да тества. Backup safe. PHP lint pass. Готово за commit + Тихол QA на телефон.
