# S87E.SALE.INLINE — Handoff

**Сесия:** S87E.SALE.INLINE_FIX — 8 bugs от QA на Тихол
**Дата:** 2026-04-29
**Файл:** `/var/www/runmystore/sale.php` (3607 реда)
**Базиран на:** S87D (`3167ddf`)
**Commit:** `ba2c6b8` (1 файл, +311 / −324)

---

## Главен принцип

> "НИЩО НЕ ОТВАРЯ ОТДЕЛЕН ЕКРАН. ВСИЧКО Е INLINE."
> (Изключение: payment screen + voice rec popup)

---

## 8 bugs DONE

| # | Bug | Решение |
|---|---|---|
| 1 | ПЛАТИ button невидим/неактивен | 💵/🅿️ → SVG; `body.sale-page .action-bar { position:sticky; bottom:0; display:flex !important; margin-top:auto }`; `summary-bar` минава в normal flow; `#cartZone padding-bottom:0` |
| 2 | Search inline (не overlay) | `<input id="searchInput" inputmode="search">` заменя `.search-display`; `<div id="searchResultsInline">` под `.search-bar`; `doInlineSearch(q)` с `AbortController` + 250ms debounce; back-button (inside результати) от варианти → master |
| 3 | Voice inline + червена лампа | `.rec-ov.open` остава (не променян); `handleVoiceResult` → `fillAndSearch(q)` слага в `#searchInput.value` + извиква `doInlineSearch` (запазва AI parsing flow) |
| 4 | Ръчно qty (за 58 бр) | `.set-qty-val` → `data-qty-edit + cursor:pointer + underline dotted`; `openQtyEditor(idx)` → `prompt(...)` → 0 = `removeItem`, NaN = alert, OK = update |
| 5 | Ръчно discount % (12%, 13%) | 5-та chip `Друго…` → `customDiscount()` → prompt → applyDiscount |
| 6 | Паркирани inline accordion | `<div id="parkedListInline">` под `bulk-banner`; `openParked()` toggle; `renderParkedInline()`, `loadParked(i)`, `deleteParked(i)`, `closeParked()`; CSS: `.parked-list-inline`, `.parked-row-inline`, `.pr-info/pr-name/pr-meta/pr-load/pr-del` |
| 7 | Пay input празен + placeholder | `recv.value=''`; `recv.placeholder = 'напр. ' + Math.max(20, ceil((total+20)/10)*10)`; `payCalcChange` enabled при cash само ако `recv ≥ total` |
| 8 | SVG mic / emoji cleanup | `💵 🅿️ 🛒` в HTML body → SVG; toast `🅿️` премахнат; DOD grep `[💵🅿💳🛒🎤🎯]` = 0 |

---

## DELETED

- `<div class="search-ov" id="searchOverlay">` — целият markup (state A + state B)
- CSS `.search-ov*` (search-ov, search-ov-state, search-ov-head, search-ov-back, search-ov-close, search-ov-title, search-ov-input-row, #srchOvInput, search-ov-mic, search-ov-meta, search-ov-results, search-ov-variants)
- JS `openSearchOverlay`, `closeSearchOverlay`, `srchOvBackToMaster`
- `<div class="parked-overlay">` + CSS `.parked-overlay`, `.parked-container`
- `body.sale-page .search-display { cursor:pointer }`, `body.sale-page #btnKeyboard { display:none }` (елементите вече ги няма)
- `searchDisplay` tap handler в DOMContentLoaded; `btnKeyboard` onclick wire

## PRESERVED (по изричен план)

- `srchOvGroupByMaster`, `srchOvOpenVariants`, `srchOvAddProduct`, `srchOvColorToCss`, `srchOvRenderMasters`, `showSrchToast` — само target елементите retargeted на `#searchResultsInline`
- `.srch-master*`, `.srch-variant*`, `.srch-toast` CSS — без промяна
- `openLpPopup`/`confirmLpPopup`/`lpNum` — long-press popup за compatibility (alternative qty editor)
- Voice flow (`startVoice`, `recOv`, AI `sale-voice.php`) — без промяна
- Всички DOM IDs не-deleted: `#payOverlay`, `#payRecvAmount`, `#cashSection`, `#btnConfirm`, `#cartZone`, `#cartEmpty`, `#actionBar`, `#btnPay`, `#camHeader`, `#camTitle`, `#themeToggle`, `#tabRetail/Wholesale`, `#camTabRetail/Wholesale`, `#tabsPill`, `#bulkParked`, `#discountChips`, `#searchResults` (legacy hidden), `#sumCount`, `#sumTotal`, `#sumDiscountBtn`, `#parkedCount`, `#btnParkedBadge`

## NEW IDs / classes

- `#searchInput` (`.search-input`)
- `#searchResultsInline` (`.s-results-inline`)
- `#parkedListInline` (`.parked-list-inline`, `.parked-row-inline`, `.pr-info/name/meta/load/del`)

---

## Known limitations

- **`prompt()` за qty/discount = quick & dirty** → DEFER S87F custom modal. На Capacitor APK на Android WebView native `prompt()` понякога е блокиран или с лош UX (no inputmode hint). Ако Тихол тества и фейлне → S87F прави bottom-sheet с `<input inputmode="numeric">`.
- **Voice on-device test pending** — AI parsing path е пипнат (`fillAndSearch`); fallback също. Code review-ed, not device-tested.
- **AbortController за inline search** — race protection добавена; гледа за `err.name === 'AbortError'` и преглъща тихо.
- **Sticky action-bar** — досегашният `position:fixed` (FIX1/FIX2) бе заместен със `position:sticky + margin-top:auto`. Ако в бъдеще `.sale-wrap` не запълва viewport-а, action-bar няма да е залепнал за дъното — но в момента `.sale-wrap` е flex column с `min-height:100vh` (виж shell). Ако някога видим, че action-bar изчезва над content-а — wrapper-ът може да не reach-ва viewport bottom. В такъв случай или wrapper `min-height:100dvh`, или връщане към fixed.
- **`searchDisplay`/`btnKeyboard` references** — `updateSearchDisplay` сега sync-ва `#searchInput.value` (не `searchDisplay.innerHTML`). Numpad context-specific text cursor (blink anim) е премахнат — native input има native cursor. Това може да изглежда странно за dev, но за крайния потребител е по-добре.
- **`doSearch` legacy alias** — рутира към `doInlineSearch`. Numpad-driven search (от код, който още вика `doSearch(STATE.searchText)`) ще render-ва inline резултати. Numpad е скрит на `body.sale-page` така или иначе, така че не е активна пътека.

---

## DEFER list — S87F+

### S87F (UX polish)
- Custom modal вместо `prompt()` за qty (BUG #4) и discount (BUG #5) — bottom-sheet с inputmode=numeric
- "Изчисти" / "×" бутон вътре в `#searchInput` за бързо clear
- Animation за inline search results (slide-in вместо instant show)

### S88+ (по-стари DEFER от S87D)
- Real product image thumbnails в `srch-master-ico` (DB `image_url` ~10/804 попълнени)
- Color hex column миграция (вместо `srchOvColorToCss` heuristic)
- AI search synonyms (voice/text "червена тениска L" → cross-attribute match)
- Parked sales DB-backed (currently localStorage)
- Print bridge DTM-5811 (Capacitor) — продължение от S88
- i18n за ~40+ hardcoded BG strings
- DB::tx() wrap, inventory_events, audit_log, search_log

---

## Test checklist (DOD)

- [ ] ПЛАТИ button visible с SVG (money icon) — cart празен → disabled (gray); cart>0 → enabled (gradient)
- [ ] Tap в `#searchInput` отваря native клавиатура (no overlay)
- [ ] Live debounced search 250ms; results inline под input
- [ ] Master с 1 вариант → tap = addToCart; master с >1 → variant list inline + back button
- [ ] Voice mic → червена лампа + после fills `#searchInput`
- [ ] Tap на qty value → prompt → 0 = remove, число = update
- [ ] "Друго…" chip → prompt → arbitrary % discount (0–100)
- [ ] Bulk-banner amber → toggle accordion с parked rows; load + del работят
- [ ] Pay overlay: input празен; placeholder = "напр. NN,NN"; ТОЧНО slag-ва total; banknote добавя; cash без recv → btnConfirm disabled
- [ ] grep -E "[💵🅿💳🛒🎤🎯]" sale.php = 0 ✓ (verified)
- [ ] php -l clean ✓ (verified)
- [ ] 1 commit + push ✓ (`ba2c6b8` pushed)

---

## Sign-off

S87E completed 2026-04-29. Manual QA on device required (Capacitor Android WebView).

For QA: hard reload, test in order — search → voice → qty edit → discount → park → load park → pay flow.
