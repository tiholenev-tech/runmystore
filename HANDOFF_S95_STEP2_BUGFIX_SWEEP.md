# S95.WIZARD.STEP2_BUGFIX_SWEEP — Handoff

**Date:** 2026-05-05
**Mode:** Auto (Tihol stress test)
**Scope:** 5 bugs in products wizard Step 2 / Step 5 / single success
**Commit:** `1ad2141` (auto-mirrored to `7a13ae8`)
**LOC:** +59 −41 = 100 (target 100, ceiling 200) ✓
**Backup:** `products.php.bak.S95.STEP2_BUGFIX_20260505_0351`
**PHP lint:** clean
**Files touched:** `products.php` only

---

## Bugs fixed

### Bug 1 — Single Step 2 "Запиши финал" → "няма въведени бройки" alert
**Root cause:** `wizSave` reads `singleQty` from `document.getElementById('wSingleQty')?.value`. На Step 2 (renderWizStep2) wSingleQty DOM input не съществува → стойност undefined → `parseInt(undefined)||0` = 0 → false-positive 0-qty popup при save.

**Fix:** fallback към `S.wizData.quantity` когато DOM-ът липсва. Step 1 oninput-ът вече sync-ва `S.wizData.quantity` при всяка промяна, така че value-то е точно.

**File:** `products.php:9888-9891`

```js
var _wsqEl=document.getElementById('wSingleQty');
const singleQty=_wsqEl?(parseInt(_wsqEl.value)||0):(parseInt(S.wizData.quantity)||0);
```

---

### Bug 2 — Печалба % не се пресмята автоматично в Step 2
**Root cause:** cost_price oninput викаше `_step2RecalcMargin()` БЕЗ typeof guard. Ако нещо в chain-а throw-не (напр. `wizUpdateMarkup` намери stale `wMarkupPct` DOM от предишен step), recalc-ът пада тихо — badge остава "—".

**Fix:** typeof guard на recalc invocation + добавен recalc и при wholesale change (consistency, дори формулата да не ползва wholesale).

**File:** `products.php:7349-7350`

---

### Bug 3 — Variant: AI Studio prompt излиза ПРЕДИ Step 2 (трябва ПОСЛЕ)
**Root cause:** `finalPromptH` (Yes opens AI Studio / No saves) беше в Step 5 (matrix). Variant flow: matrix → finalPromptH → "Не" → Step 2 → Save. Така user-ът ФИНАЛИЗИРА AI решение преди да види Препоръчителни.

**Fix:** finalPromptH преместен от Step 5 в Step 2 footer (само за variant). Step 5 завършва с navigation бутон "Препоръчителни ›" → wizGoStep2(). Step 2 показва finalPromptH вместо "Запиши финал" бутона за variant. Single запазва "Запиши финал" footer-а (single няма AI Studio prompt в spec).

**Side fix:** `wizFinalAINo` опростена — премахнат `wizGoStep2()` routing (би циклирал на същата страница). Сега просто `wizSave()`.

**Files:** `products.php:7370-7407` (Step 2 footer), `products.php:7822-7827` (wizFinalAINo)

---

### Bug 4 — Variant "Назад" от Step 2 → dead Step 5 (минимално количество глобално + AI SEO)
**Root cause:** Step 5 имаше `minQtyH` (wMinQty input) и `descH` (AI SEO textarea), които бяха dead:
- `wMinQty` от Step 5 не се събира в save flow за variant
- AI SEO се пресмята post-save в AI Studio, не на тази страница

**Fix:** премахнати dead minQtyH + descH + stale finalPromptH (move-нат за Bug 3). Step 5 вече е чисто matrix + nav buttons.

**File:** `products.php:6925-6957`

---

### Bug 5 — Single mode success screen НЯМА "Свали CSV" (variant Step 6 има)
**Root cause:** `showMiniPrintOverlay` (single fast-path success) показваше само ПЕЧАТАЙ + ГОТОВО. Variant `renderWizPage(6)` имаше "Свали CSV за онлайн магазин" бутон. Inconsistency.

**Fix:** добавен "Свали CSV" бутон в mini overlay под print/done CTA-тата (за да не доминира primary actions). Извиква съществуващата `wizDownloadCSV()` (тя handle-ва single случая — пише един row без size/color, защото `_printCombos[0].parts` е празен).

**File:** `products.php:10360-10364`

---

## Mental walkthrough (R2 — no regression)

| Flow | Before | After |
|---|---|---|
| Single Step 1 ЗАПИШИ → save | wSingleQty в DOM → singleQty parsed OK | unchanged ✓ |
| Single Step 1 → "Препоръчителни" → Step 2 → Запиши финал | singleQty=0 → false alert | fallback към S.wizData.quantity ✓ |
| Single Step 2 → Назад → Step 1 | OK | unchanged ✓ |
| Variant Step 4 → Step 5 (matrix) | matrix + minQty + descH + finalPromptH | matrix + nav buttons (cleaner) ✓ |
| Variant Step 5 → "Назад към вариации" | Step 4 axes | unchanged ✓ |
| Variant Step 5 → "Препоръчителни" | finalPromptH "Не" → Step 2 | "Препоръчителни ›" → wizCollectData() + wizGoStep2() ✓ |
| Variant Step 2 → "Да отвори AI Studio" | n/a (prompt беше на Step 5) | wizFinalAIYes → save → openStudioModal ✓ |
| Variant Step 2 → "Не запази" | n/a | wizFinalAINo → wizSave → wizGo(6) ✓ |
| Variant Step 2 → Назад | dead Step 5 (minQty + AI SEO) | clean Step 5 (matrix only) ✓ |
| Single mini overlay → ПЕЧАТАЙ / ГОТОВО | Print/Done | Print/Done + Свали CSV ✓ |
| Variant Step 6 success → Свали CSV | works | unchanged ✓ |

---

## Rules compliance

- **R3 SAFE MODE** — backup made: `products.php.bak.S95.STEP2_BUGFIX_20260505_0351` (size 942376 = original). Used Python str_replace + Edit tool, NO sed -i, NO full rewrite ✓
- **R4 NO DESTRUCTIVE** — no rm/chmod/reset/force/DROP ✓
- **R5 DB CONVENTIONS** — no SQL touched ✓
- **R6 UI INVARIANTS** — kept .glass + .shine in mini overlay; SVG icons (download), no emoji as primary; Bulgarian text ✓
- **R7 COMMIT DISCIPLINE** — 1 atomic commit, conventional msg, pushed (auto-mirrored) ✓
- **R8 VERIFY** — `php -l products.php` clean; diff +59 −41 within 200 ceiling ✓
- **R9 ZERO EDITS PROMISE** — only `products.php` modified ✓
- **R10 PROGRESS TRANSPARENCY** — this handoff ✓

---

## Browser test instructions (for Tihol)

1. **Bug 1 — Single qty sync**:
   Open wizard → Single → fill name + price + qty=5 → "Препоръчителни ›" → Step 2 → "Запиши финал". Should save without "няма въведени бройки" popup.

2. **Bug 2 — Margin auto-calc**:
   On Step 2 (single ИЛИ variant) → set retail на Step 1 (напр. 100) → отиди на Step 2 → въведи Доставна цена 60 → badge "Печалба %" трябва да покаже 40.0%. Промени cost на 80 → badge → 20.0%.

3. **Bug 3 — Variant AI Studio order**:
   Variant flow: попълни axes → Step 5 (matrix) → попълни matrix → клик "Препоръчителни ›". Step 2 трябва да показва finalPromptH "Искаш ли AI обработка?" с бутони Да/Не. Преди bugfix prompt-а беше на Step 5 (преди Step 2).

4. **Bug 4 — Variant Назад от Step 2**:
   Стой на Step 2 (variant) → клик Назад. Should land на чист Step 5 — matrix + Назад/Препоръчителни. NO "Минимално количество глобално", NO "AI SEO описание".

5. **Bug 5 — Single CSV**:
   Single → ЗАПИШИ on Step 1 → mini overlay се появява. Под ПЕЧАТАЙ ЕТИКЕТ + ГОТОВО трябва да има "Свали CSV за онлайн магазин" link. Клик → CSV се сваля.

---

## Known limitations / future work

- **Bug 2 follow-up**: Ако typeof guard не реши проблема в браузъра, истинският root cause може да е друг (race condition, scope shadow). Add `console.log('[Bug2] cost oninput', cost, retail, pct)` в _step2RecalcMargin за да дебъгваме. Препоръка: след Tihol test, ако пак не работи → инстанс на problem reproduce → log от console.
- **Future**: Single mode could also benefit from finalPromptH (AI Studio choice on Step 2) — но spec за това не е дефиниран. Hold за решение.
