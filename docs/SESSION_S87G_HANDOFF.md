# S87G.SALE.UX_BATCH — Handoff

**Сесия:** S87G.SALE.UX_BATCH — 7 P0/P1 bugs от mobile test 29.04
**Дата:** 2026-04-29
**Файл:** `/var/www/runmystore/sale.php`
**Базиран на:** S87E (`ba2c6b8`)

---

## Главен принцип

> "НИКАКВИ NATIVE PROMPT() / ALERT() / CONFIRM() В sale.php"
> Всичко = custom inline UI (numpad, modal, toast).

---

## 7 bugs DONE

| # | Bug | Решение |
|---|---|---|
| B1 | Live search без ENTER | `input` + `keyup` listeners на `#searchInput`, debounced 250ms; `min 2 chars` (под 2 → `inlineSearchClose()`); AbortController preserved (S87E); `Enter` keypress прави immediate trigger; idempotent wiring guard `__s87gWired` |
| B6 | Voice inline (без overlay) | `startVoice()` пренаписан: НЕ отваря `#recOv`. `btnVoiceSearch` получава `.voice-recording` (червен gradient + pulsing white dot top-right) → `.voice-processing` (spinner) → idle. `recognition.interimResults=true; continuous=false` → live interim transcript fills `#searchInput` в реално време + debounced `doInlineSearch`. Final transcript → `handleVoiceResult` (AI parsing flow през `sale-voice.php` запазен). Tap втори път → recognition.stop(). `#recOv` markup запазен (kept за legacy callers, no-op listeners) |
| B2 | Custom numpad qty edit | `openQtyEditor(idx)` пренаписан → отваря inline `#lpPopup` modal (вече съществуващ). Title = product name. Initial value = current quantity. OK с `q===0` → `removeItem`; `q>0` → set quantity + render. Cancel (`Откажи`) → close без промяна. Long-press popup TRIGGER (touchstart 500ms таймер върху row) **премахнат** — replace-нат от dotted underline tap |
| B7 | Custom numpad discount % | `customDiscount()` → `openDiscountModal(cur)`. Reuse-ва `#lpPopup` в mode `discount`. Title = "Отстъпка %". Validation: `0 ≤ n ≤ 100`, иначе `showToast('Невалиден процент (0–100)')`. На OK → `applyDiscount(n)` |
| B3 | Tap zones ляво/дясно qty | `+`/`−` buttons (`set-qty-btn`) **премахнати** от cart row HTML. `.set-qty-val` сега е split-tap zone: tap ляво половина → `quantity--` (или `removeItem` ако =1); tap дясно половина → `quantity++`. Long-press (touch 500ms) → отваря custom numpad modal (B2). Hover hint: ::before/::after стрелки `‹` `›` се появяват с opacity 0.55 на hover (idle = чист) |
| B5 | Search result tap → numpad | `srchOvAddProduct(product)` → `openSearchAddModal(product)` (mode `search` на `#lpPopup`). Title = product name; initial = `1`. OK → `addToCart(product, qty)` (extended signature: optional `qtyOverride`) + clear `#searchInput` + `inlineSearchClose()`. Cancel → close numpad без add (search results остават) |
| B4 | Swipe-to-delete cart row | DOM refactor: row съдържа `.ci-delete` (red gradient button с trash SVG, абсолютно позиционирано на right edge зад row content) + `.set-row-fg` (foreground content, translates left). Touch handlers: `touchstart` startX в `(rect.width - 30, rect.width)` диапазон → enable drag (per spec); `touchmove` lock-on-direction (`abs(dy) > abs(dx)` → abort, vertical scroll); drag follows finger clamped до `-90px`. `touchend` threshold: `dx ≤ -60px` ИЛИ `abs(dx)/width ≥ 0.30` → reveal (snap to `-90px`); иначе snap back. Tap delete → `removeItem(idx)`. Tap row body когато swiped → close swipe (no select) |

### Hint
"← плъзни наляво за изтриване" (1 път на устройство) — `localStorage` flag `rms_s87g_swipe_hint_shown`. Появява се при `STATE.cart.length > 0` на render; auto-dismiss след 4.5s. Стилизиран dashed indigo banner, 11px font. CSS клас `.s87g-swipe-hint`.

---

## DELETED

- `window.prompt(...)` calls в `openQtyEditor` (S87E.B4)
- `window.prompt(...)` + `alert(...)` calls в `customDiscount` (S87E.B5)
- Inline `alert('Невалиден брой')` (S87E.B4 errors) → `showToast(...)` toast
- `<button class="set-qty-btn" data-act="dec">−</button>` + `data-act="inc">+</button>` от cart row HTML
- Long-press TRIGGER на `.set-row` (touchstart 500ms → `openLpPopup`) — replace-нат от tap-edit + new long-press на `.set-qty-val` директно
- Half-baked swipe handlers на `.set-row` (S87E inherit) — replace-нати с правилен drag + threshold
- `recOv.classList.add('open')` в `startVoice` — overlay вече не се отваря за voice бутона
- Context-aware hints в rec-ov (numpadCtx switch)
- Old `body.sale-page .set-qty-val` cosmetics (padding 4px 6px, min-width 30px, dotted underline) → replace-нати от B3 split-tap CSS

## PRESERVED

- Всички DOM IDs от S87E: `#searchInput`, `#searchResultsInline`, `#parkedListInline`, `#lpPopup`, `#lpTitle`, `#lpDisplay`, `#recOv` markup (за legacy callers), `#payOverlay`, `#payRecvAmount`, `#cashSection`, `#btnConfirm`, `#cartZone`, `#cartEmpty`, `#actionBar`, `#btnPay`, `#camHeader`, `#camTitle`, `#themeToggle`, `#tabRetail/Wholesale`, `#tabsPill`, `#bulkParked`, `#discountChips`, `#sumCount`, `#sumTotal`, `#sumDiscountBtn`, `#parkedCount`, `#btnParkedBadge`, `#btnVoiceSearch`
- Master/variant групиране в search (`srchOvGroupByMaster`, `srchOvOpenVariants`, `srchOvRenderMasters`, `srchOvColorToCss`)
- AbortController + 250ms debounce за inline search
- `handleVoiceResult` AI parsing path (`sale-voice.php` endpoint) — извиква се при `recognition.onend` с final transcript
- `#recOv` overlay HTML и CSS (за бъдещи модули; sale-page voice button вече не я отваря; recSend/recCancel listeners запазени като no-ops)
- Numpad HTML (`lp-num` buttons → `lpNum('1')`, ..., `closeLpPopup()`, `confirmLpPopup()`) — функциите rewritten, но HTML не пипан
- `confirm(...)` за draft restore (S87D.FIX1) и back guard (S87D.FIX1) — те не са UX bugs, не са в scope на S87G; remain native browser dialogs
- `applyDiscount`, `addToCart`, `removeItem`, `selectCartItem`, `setNumpadCtx`, `render` — semantics запазени; `addToCart` extended с optional `qtyOverride`

## NEW IDs / classes / functions

- `.set-row-fg` (foreground content, translates on swipe)
- `.s87g-swipe-hint` (first-time hint banner)
- `.search-btn#btnVoiceSearch.voice-recording` (red pulse + dot)
- `.search-btn#btnVoiceSearch.voice-processing` (spinner)
- JS: `qmMode` ('qty'|'discount'|'search'), `qmTargetIdx`, `qmTargetProduct`, `qmValue`
- `_qmShow(title, initial)` — internal modal opener
- `openQtyModal(idx)` — alias for `openQtyEditor(idx)`
- `openDiscountModal(initial)` — opens discount modal
- `openSearchAddModal(product)` — opens search-add modal
- `s87gShowSwipeHintOnce()` — first-time hint helper
- `s87gVoiceActive` flag — voice button toggle/cancel guard
- `S87G_SWIPE_HINT_KEY` — localStorage key

---

## Known limitations

- **Multi-select при search** → DEFER S87H. Spec позволи "long-press на result → entering multi mode с checkboxes" но за S87G **не имплементирано**. Single tap → numpad е финалното поведение в S87G.
- **B6 voice continuous=false fallback** — `interimResults=true` дава real-time транскрипт. Bg-BG `continuous=true` понякога дегрейдва качеството → keep `continuous=false`. Ако в бъдеще искаме continuous voice (multi-utterance), → need extra state machine + manual stop button.
- **B6 numpad mic button (`np-btn.mic`)** — все още вика `startVoice()` (numpad-ът е скрит на `body.sale-page`, не активна пътека). Ако numpad се активира някога, voice ще използва inline визуализация на search bar бутона. Не блокирано.
- **B4 swipe right-edge constraint** — touchstart **must** start within rightmost 30px of row (per spec). Ако user-ите namерят неудобно ("защо не работи когато тръгна от средата на реда"), → drop the constraint в S87H (просто `if (sxStart < rect.width - 30) return;` се изтрива).
- **B4 swipe hint** — показва се 1 път на устройство (localStorage). Ако race с `rec-ov` бъде наблюдаван (което не очакваме, defunct в S87G), → премахни hint логиката в S87H.
- **B4 hint timing** — fires on `STATE.cart.length > 0` render. Ако draft restore-ва празен cart → не показва hint, нормално.
- **B3 long-press на `.set-qty-val`** — 500ms timer, активен само на touch (не mouse). Desktop tester ще трябва да ползва `dotted underline` визуален cue + double-click? — но desktop не е target. На mobile работи.
- **B7 discount numpad** — само integer values (no `.` key в HTML). Validator приема `parseFloat` за backward compat, но user не може да въведе 12.5% през numpad. Ако needed → S87H добавя `.` key (rearrange numpad layout).
- **`addToCart(product, qty)` extended signature** — все още обратносъвместим (single-arg callers: barcode handleBarcode, voice add_items получават qty=1 default). Verified: 4 caller sites.
- **Legacy `lpTargetIdx` / `lpValue` mirrors** — kept for any external module reading them. `lpNum` updates both `qmValue` and `lpValue`.

---

## DEFER list — S87H+

### S87H (UX polish)
- Multi-select при search results (long-press → checkbox mode → bulk add)
- "×" clear button вътре в `#searchInput` за бързо изчистване
- B7 numpad с `.` key (decimal discount % support)
- Swipe-to-delete без right-edge constraint (drop the 30px rule ако feedback-ът е "awkward")
- Animation за inline search results (slide-in)
- B6 voice: continuous mode toggle (multi-utterance)

### S88+ (от S87E DEFER list)
- Real product image thumbnails в `srch-master-ico`
- Color hex column миграция (вместо `srchOvColorToCss` heuristic)
- AI search synonyms
- Parked sales DB-backed
- Print bridge DTM-5811 (Capacitor) — продължение от S88
- i18n за hardcoded BG strings

---

## Test checklist (DOD)

- [x] `grep -c "window.prompt\|prompt("` = 0 (verified)
- [x] `grep -c "alert("` = 0 (verified)
- [x] `php -l sale.php` clean (verified)
- [ ] Live search trigger-ва на keyup, debounced 250ms, min 2 chars (manual QA)
- [ ] Voice бутон в-place: idle → red pulse → spinner → idle (manual QA)
- [ ] Voice transcript fills `#searchInput` real-time (manual QA на bg-BG device)
- [ ] Tap qty в cart row → numpad modal (title=product name) → OK update / 0 = remove (manual QA)
- [ ] Tap "Друго…" chip → numpad modal "Отстъпка %" → 0–100 valid → applyDiscount (manual QA)
- [ ] Tap left half на qty number → -1 (или remove); tap right half → +1 (manual QA)
- [ ] Long-press qty → numpad modal opens (manual QA)
- [ ] Tap search result → numpad modal → OK adds with qty + closes search (manual QA)
- [ ] Swipe left от right edge на cart row → reveals red delete button; tap delete = removeItem (manual QA)
- [ ] First open с cart > 0 → hint banner shown 1×, after dismissal localStorage flag = "1" (manual QA)
- [ ] 1 commit + push
- [ ] docs/SESSION_S87G_HANDOFF.md написан (this file)

---

## Sign-off

S87G completed 2026-04-29. Manual QA on Capacitor Android WebView required. For QA: hard reload, test in order — search (live, voice) → cart row qty (tap zones, long-press) → discount (Друго…) → search result tap → swipe-to-delete.
