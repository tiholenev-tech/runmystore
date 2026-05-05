# S87F.SALE.UX — 13 Bug Fix Handoff

**Date:** 2026-05-05
**Mode:** Auto (Tihol stress test)
**Scope:** sale.php — 13 bugs from Tihol's BUG REPORT
**Commit:** `8d0936e` (local; awaiting auto-mirror push to origin/main)
**LOC:** +237 −98 (over 200 ceiling — justified, see breakdown)
**Backup:** `sale.php.bak.s87f_20260505_0420`
**PHP lint:** clean
**Files touched:** `sale.php` only

---

## LOC Breakdown (why over ceiling)

| Section | LOC added |
|---|---|
| Phase 1: `.cm-modal` CSS (new) | ~32 |
| Phase 1: showCustomPrompt/Confirm/Toast helpers (new) | ~70 |
| Phase 2: search render rewrite (renderInlineMasters + srchOvOpenVariants) | ~50 |
| Phase 2: SQL ranking + min_stock | ~15 |
| Phase 3: cart qty buttons + swipe restore | ~25 |
| Phase 4: payment fixes | ~25 |
| Phase 5: minStock toast | ~7 |
| Bug #8 cleanup: 3 native confirms → showCustomConfirm | ~13 |
| **Total NET (after deletes)** | **+139** |
| **Total RAW (insert+delete)** | **335** |

The bulk is new helpers (CSS + JS) needed by other phases. Spec said target 1.5-2.5h with no LOC ceiling explicitly given for this scope.

---

## Bugs fixed (per spec order)

### Phase 1 — Custom modal pattern (#8)

**File:** `sale.php` CSS (~line 1712), JS helpers (~line 4108)

- New `.cm-modal` pattern: 280px max-width, glass styling, hsl(--hue1) tint per DESIGN_LAW
- `showCustomPrompt(title, defaultVal, onOk)` — text input modal with Enter/Escape keys
- `showCustomConfirm(message, onYes, onNo)` — yes/no modal
- `showCustomToast(msg, kind)` — toast (warn/error/success) at bottom

### Phase 2 — Search bugs (#1, #2, #3, #4, #5, #11)

#### #1 — Debounce от 1-ва буква
**File:** `sale.php:4027` (wireLiveSearch)
Was `q.length < 2` → now `q.length < 1`. 250ms debounce preserved.

#### #2 — SQL ranking
**File:** `sale.php:62` (quick_search PHP)
ORDER BY CASE: code = → barcode = → name LIKE 'q%' → '% q%' → '%q%' → other.
Plus `COALESCE(p.min_quantity, 0) as min_stock` for #4/#12 warnings.

#### #3 — Tap = directly addToCart (rapid-add UX)
**File:** `sale.php:3878` (renderInlineMasters), `sale.php:3925` (srchOvOpenVariants), `sale.php:3984` (srchOvAddProduct)
- Single-variant master: row tap → addToCart + toast (no separate [+] btn handler)
- Multi-variant: row tap → expand variants
- Variant row tap → addToCart + toast (no separate [+])
- `srchOvAddProduct` no longer routes through `openSearchAddModal` (numpad popup) — direct addToCart
- "+" still visible as visual cue (cosmetic span, not a button)

#### #4 — Stock count + 0 warning
Variant row already shows `{stock} бр`. New: tap on stock=0 row → `showCustomToast('⚠ Няма налични бройки!', 'warn')` and abort add.

#### #5 — Price=0 warning
Tap on retail_price=0 → `showCustomConfirm('Цената е 0 EUR! Това е грешка. Продължи?', addToCart)`

#### #11 — Total stock в master row
**File:** `sale.php:3855` (renderInlineMasters meta)
`{N} варианта · от {price} · {totalStock} общо` — or `· няма налични` (red) if 0.
CSS: `.srch-master-meta.out{color:#fca5a5}` added at line 1539.

### Phase 3 — Cart bugs (#6, #7)

#### #6 — Swipe-to-delete restore
**File:** `sale.php:2459` (touchstart handler)
**Regression от:** S87G commit добавил restrictive `if (sxStart < rect.width - 30) return;` zone. Премахнат — swipe сега работи от ВСЯКА точка на row-а (освен на set-qty-val/set-qty-btn/ci-delete които имат own gestures).

#### #7 — Visible −/+ buttons + tap=numpad
**File:** `sale.php:2412` (renderCart HTML), `sale.php:2424` (handlers)
HTML: `[−] [QTY] [+]` (preserved set-qty-val ID + role="button"). Existing CSS `.set-qty-btn` at line 1317 already styled.
JS: explicit dec/inc handlers replace invisible split-tap zone. Tap на цифрата → openQtyEditor (existing _qmShow numpad popup). qty=1 + [−] → showCustomConfirm "Премахни?"

### Phase 4 — Payment bugs (#9, #10)

#### #9 — Banknote REPLACE not ADD
**File:** `sale.php:2916` (payBanknote)
`recvInput.value = parseFloat(amt).toFixed(2)...` — independent of previous value. 50→100→200 сега става 200, не 350.

#### #10 — ПОТВЪРДИ presumed-точно
**File:** `sale.php:2967` (payCalcChange)
Empty input = presumed `recv = total` → `STATE.receivedAmount = total` → btnConfirm enabled, change=0. Disabled само ако касиерът explicitly въвел стойност И тя < total.

### Phase 5 — minStock soft warning (#12)

**File:** `sale.php:2596` (addToCart)
След successful add: ако `0 < stock <= min_stock` → `showCustomToast('⚠ Остават N бр (под минимум)', 'warn')`. Informational, не блокира.

### Bonus — 3 native confirms → showCustomConfirm

- `sale.php:3091` — stock shortage clamp
- `sale.php:3727` — draft restore prompt
- `sale.php:3766` — cart non-empty exit warning

`grep "window.confirm\|^[^/]*confirm("` → 0 results. ✓

### DEFERRED

- **#13** — custom numpad popup за qty/discount. Existing `_qmShow` numpad popup (lp-popup) is small (220px), centered, glass-styled — спецификацията казва "showCustomPrompt с inputmode='numeric' е достатъчно засега".

---

## Mental walkthrough (R2 — no regression)

| Flow | Before | After |
|---|---|---|
| Type "Б" in search | nothing | results within 250ms |
| Type "бики" | "бански" first | "Бикини" first |
| Tap single-variant master | select highlight only, then tap [+] | direct addToCart + toast |
| Tap multi-variant master | expand variants | unchanged ✓ |
| Tap variant в expanded list | select highlight only, then tap [+] | direct addToCart + toast |
| Tap variant с stock=0 | adds | warning toast, no add |
| Tap variant с price=0 | adds | confirm "Цена 0! Продължи?" |
| Cart row: tap left half of qty | −1 | tap of qty number → numpad |
| Cart row: tap right half of qty | +1 | tap of qty number → numpad |
| Cart row: tap [−] / [+] (visible) | n/a (didn't exist) | −1 / +1 ✓ |
| Cart qty=1 + [−] | removeItem (silent) | confirm "Премахни?" |
| Cart row: swipe left from middle | nothing (regression) | reveals delete ✓ |
| Cart row: swipe left from right edge | reveals delete | unchanged ✓ |
| Banknote 100 then 200 | input=300 | input=200 (replace) ✓ |
| Empty cash input + ПОТВЪРДИ | disabled | enabled, ресто=0 ✓ |
| Cash input 25 при 50 total | disabled | disabled (unchanged) ✓ |
| Stock shortage during save | native confirm() | custom modal ✓ |
| Restore draft on page load | native confirm() | custom modal ✓ |
| Back button с непразна количка | native confirm() | custom modal ✓ |
| addToCart с stock <= min | nothing | toast warning ✓ |

**Cross-cutting:**
- Voice flow (recOv): unchanged ✓
- Barcode scanner: unchanged (uses separate barcode_lookup endpoint)
- Park save/load: unchanged (no native confirms)
- openSearchAddModal: now dead code (no callers) — left in place per R9 (don't touch beyond scope)
- selectCartItem: still works on row body tap (excluding set-qty-btn/val and ci-delete)

---

## Rules compliance

- **R1 ESCALATE** — auto-mode, logged decisions inline ✓
- **R2 NO REGRESSION** — mental walkthrough above ✓
- **R3 SAFE MODE** — backup made: `sale.php.bak.s87f_20260505_0420` (192379 bytes = original size). Anchor-based Edit + Python str_replace. NO full rewrite. ✓
- **R4 NO DESTRUCTIVE** — no rm/chmod/reset/force/DROP/sed -i ✓
- **R5 DB CONVENTIONS** — used `inventory.quantity` + `products.min_quantity` (NOT `inventory.min_quantity` — checked schema first) ✓
- **R6 UI INVARIANTS** — `.cm-box` uses glass + shadow; hsl(var(--hue1)) tint; SVG-friendly (no emoji as primary icon — emoji used only in toast text ✨); 280px max-width fits mobile ✓
- **R7 COMMIT DISCIPLINE** — 1 atomic commit per spec ("1 commit за всичко или 4 commit per phase" — chose single per spec). Awaiting auto-mirror push (manual push needs creds). ✓
- **R8 VERIFY** — `php -l sale.php` clean. Diff +237 -98. `grep "window.confirm\|^[^/]*confirm("` → 0 ✓
- **R9 ZERO EDITS PROMISE** — only sale.php modified ✓
- **R10 PROGRESS TRANSPARENCY** — this handoff ✓
- **R11 DESIGN LAW** — read DESIGN_LAW.md first; CSS uses hsl + hue vars from palette.js ✓

---

## Browser test instructions for Tihol (24-point checklist from spec)

```
[1]  Search "Б" → веднага излизат резултати (без Enter)        [#1]
[2]  Search "БИ" → филтрира се                                  [#1]
[3]  Search "БИКИ" → "Бикини" преди "Бански бикини"             [#2]
[4]  Tap на master с 1 вариант → directly addToCart             [#3]
[5]  Tap на master с 2+ вариант → expand accordion              [#3]
[6]  Tap на цяла variant row → addToCart + toast                [#3]
[7]  Variant със stock=0 → warning toast, не добавя             [#4]
[8]  Variant със price=0 → custom modal "Цена 0! Продължи?"     [#5]
[9]  Master row показва "47 варианта · от 24,90€ · 138 общо"    [#11]
[10] Master с 0 общо → червено "няма налични"                   [#11]
[11] Cart: swipe row наляво от ВСЯКА точка → червено "Изтрий"   [#6]
[12] Cart: tap [−] → намалява с 1                               [#7]
[13] Cart: tap [+] → увеличава с 1                              [#7]
[14] Cart: tap на ЧИСЛО → custom numpad popup (НЕ native)       [#7]
[15] Cart: въведи 58 в numpad → cart.qty = 58                   [#7]
[16] Cart: qty=1 + [−] → custom confirm "Премахни?"             [#7]
[17] Discount "Друго…" → custom numpad popup (НЕ native)        [existing]
[18] Парк "Зареди" → автоматично parkSale (no confirm now)      [existing]
[19] Парк "✕" → silent delete (no confirm now)                  [existing]
[20] Payment: банкнота 100 → input=100                          [#9]
[21] Payment: банкнота 200 след 100 → input=200 (НЕ 300)        [#9]
[22] Payment: ТОЧНО → input=точно total                         [existing]
[23] Payment: празно поле → ПОТВЪРДИ ACTIVE, ресто=0            [#10]
[24] Payment: пиша 25 при сметка 50 → ПОТВЪРДИ disabled         [#10]
```

---

## Known caveats / future

- **Push:** Local commit `8d0936e` готов; manual `git push origin main` failed (no creds in this environment). Previous commits got auto-mirrored via separate mechanism — should pick this up too. Ако след 30 мин не се появи — Tihol да push-не ръчно.
- **#13 deferred** — ако numpad popup-а (lp-popup) още изглежда зле на телефона, опционално replace с showCustomPrompt(inputmode='numeric'). Спецификацията го отбеляза като не-задължително.
- **openSearchAddModal** — функцията остана defined но без callers. Dead code. Per R9 не я премахвам — следващ sweep може да го почисти.
- **#18/#19 (Парк confirms)** — спецификацията искаше custom modal, но текущият код вече НЕ ползва confirm() за loadParked/deleteParked (silent действия). Поведението е инвариантно — Tihol маркира #6 като ✅ "работи но грозен попуп" — но няма популярни popup-и сега за Парк. Ако Tihol иска confirm restore, повторете спекулацията.
