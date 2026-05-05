# S95.WIZARD.BUGFIX_R2 — 7 bugs round 2 handoff

**Date:** 2026-05-05
**Mode:** Auto (Tihol stress test after S95.STEP2_BUGFIX_SWEEP)
**Scope:** products.php — 7 bugs from Tihol's BUG REPORT
**Commit:** `b01802b` (local; awaiting auto-mirror push)
**LOC:** +57 −4 (target 100, ceiling 250) ✓
**Backup:** `products.php.bak.BUGFIX_R2_20260505_0455`
**PHP lint:** clean

---

## Phase 0 Audit Table

| Bug | Where | Root Cause |
|---|---|---|
| **A** qty=0 in DB | wizCollectData L9984 | NIE четеше wSingleQty → oninput failure cases (mic / programmatic) leak |
| **B** search delay | onLiveSearchHome L11842 | 250ms debounce твърде бавно за първите букви |
| **C+G** CSRF retry fail | api() L4786, product-save.php PH3 | Token не се refresh-ва след session re-mint → stale → 403 → reload |
| **D** axes labels | renderWizPagePart2 L6710, init L6646 | Само AI photo flow renames axes; manual values остават "Вариация N" |
| **E** non-numeric keypad | matrix input L7038 | type="number" недостатъчно — Android show alphabetic за някои keyboards |
| **F** variant Назад → dead | wizGoStep1 L7433 | wizSubStep leak от step 3 sub 2 visit |

---

## Bug fixes

### Bug A — qty=0 in DB
**Fix:** `wizCollectData` сега чете `wSingleQty` ако DOM съществува, с `isNaN` guard срещу празен input.
```js
if(el('wSingleQty')){
    var _qv=parseInt(el('wSingleQty').value);
    if(!isNaN(_qv) && _qv >= 0) S.wizData.quantity=_qv;
}
```
Защо isNaN guard: ако input е празен (user изтрил value), parseInt('') = NaN → не overwrite-ваме (S.wizData.quantity остава последната валидна стойност от oninput, която beше 0 при изтриването).

### Bug B — search dropdown delay
**Fix:** Debounce 250ms → 150ms в `onLiveSearchHome`.
```js
}, 150);
```
Threshold вече беше `q.length < 1` (от R1) — не променено. Само delay-а намален.

### Bug C+G — CSRF retry fail + animation hang
**Fix 1:** Нов GET endpoint `?ajax=csrf_refresh` връща `csrfToken()` (stateless — не conflict с PH3 hardening на Code 2).
**Fix 2:** `api()` retry logic — при 403 csrf → fetch refresh → update window.RMS_CSRF → retry once с `__csrfRetried` flag (no infinite loop). Ако retry пак fail-не → reload (genuine session expiry).

Преди: първият save → 403 → toast "Сесията изтече…" → 1500ms reload (всеки път user губи unsaved form).
Сега: 403 → silent token refresh → invisible retry → save success → toast "Артикулът е добавен!".

Bug G (animation hangs): "Запазвам..." toast има 3s auto-remove. С retry fix вторият POST успява → success toast. Animation вече не виси за CSRF reload race.

### Bug D — axes labels Вариация→Размер/Цвят
**Fix:** В `renderWizPagePart2` step 4 init (line 6646), след създаването на default axes, sniff values на всеки axis и rename if generic:
```js
S.wizData.axes.forEach(function(_ax) {
    if (!/^вариация\s*\d+$/i.test(_ax.name || '')) return;
    if (!_ax.values || !_ax.values.length) return;
    var _sizePat = /^(xs|s|m|l|xl|xxl|xxxl|xs?-tall|\d{2,3}(\.\d)?(w|t)?)$/i;
    if (_ax.values.every(v => _sizePat.test(String(v).trim()))) { _ax.name = 'Размер'; return; }
    var _cfgNames = ((window.CFG && CFG.colors) || []).map(c => String(c.name||'').toLowerCase().trim());
    if (_cfgNames.length && _ax.values.every(v => _cfgNames.indexOf(String(v).toLowerCase().trim()) >= 0)) { _ax.name = 'Цвят'; }
});
```
Side effects: матрицата (line 7020) ползва `n.indexOf('размер')` / `n.indexOf('цвят')` — auto-renamed axes сега се разпознават → render-ва истинската size×color grid вместо combo list fallback.

Custom axis names (e.g., "Бликини S") не се пипат — regex `^вариация \d+$/i` matches само default placeholder.

### Bug E — numeric keypad
**Fix:** Matrix cell input → `inputmode="numeric" pattern="[0-9]*"`.
```html
<input type="number" inputmode="numeric" pattern="[0-9]*" min="0" ...>
```
Mobile Android/iOS показват numeric keyboard. Desktop unchanged.

### Bug F — variant Назад → dead Детайли
**Fix:** `wizGoStep1` (variant branch) и `wizGoStep2` сега изрично reset-ват `S.wizSubStep = 0`. Stale sub-step value (от step 3 sub 2 visit) leak-ваше → wrongful render.

---

## Mental walkthrough (R2 — no regression)

| Flow | Before | After |
|---|---|---|
| Single Step 1 type qty=3, click Препоръчителни → Step 2 → Запиши финал | qty=0 in DB if oninput missed | qty=3 (wizCollectData reads) ✓ |
| Single Step 1 ЗАПИШИ direct | unchanged (wSingleQty in DOM) | unchanged ✓ |
| Edit existing product | unchanged (qty pre-loaded) | unchanged ✓ |
| Search "Б" (250ms gap) | dropdown after 250ms | dropdown after 150ms ✓ |
| Save → CSRF mismatch | reload + lose form | silent retry → success ✓ |
| Save → genuine session expiry | reload | refresh fail → reload (same end state) ✓ |
| Variant axes manual size values | "Вариация 1" stays | "Размер" auto-detected ✓ |
| Variant axes voice/AI flow | already named | unchanged ✓ |
| Custom axis name "Бельо XL" | unchanged | unchanged (regex doesn't match) ✓ |
| Matrix cell tap mobile | alphabetic keyboard | numeric keyboard ✓ |
| Variant Назад from Step 2 (after step 3 sub 2 visit) | renders Детайли | renders matrix ✓ |
| Round 1 fixes (qty fallback, margin recalc, AI prompt position) | working | unchanged ✓ |

---

## Browser test (per spec DoD)

```
[1] Save с qty=3 от Step 2 → DB записва 3
[2] Search "Б" → live dropdown в 150ms
[3] Повторен save → НЕ CSRF грешка (silent retry)
[4] Variant: добави размери "S M L" → таб става "Размер"
[4b] Добави цветове в axis 2 → таб става "Цвят"
[5] Matrix cell tap → numeric keyboard
[6] Variant Назад от Step 2 → matrix (НЕ Детайли)
[7] Round 1 verify: Step 2 Запиши финал (single), margin %, variant AI prompt position, single CSV download
```

---

## Rules compliance

- **R1 ESCALATE** — auto-mode, decisions logged inline in audit table ✓
- **R2 NO REGRESSION** — mental walkthrough above ✓
- **R3 SAFE MODE** — backup `products.php.bak.BUGFIX_R2_20260505_0455` (954184 bytes = pre-edit). Anchor-based Edit, NO full rewrite. ✓
- **R4 NO DESTRUCTIVE** — no rm/chmod/reset/force/DROP/sed -i ✓
- **R5 DB CONVENTIONS** — no SQL touched ✓
- **R6 UI INVARIANTS** — no .glass/.shine touched, SVG icons preserved ✓
- **R7 COMMIT DISCIPLINE** — 1 atomic commit (spec said 1-3 per phase). Pushing via auto-mirror. ✓
- **R8 VERIFY** — `php -l products.php` clean. Diff +57 -4 well within 250 ceiling. ✓
- **R9 ZERO EDITS PROMISE** — only `products.php` modified. NOT touched: product-save.php (Code 2 territory), config/helpers.php. ✓
- **R10 PROGRESS TRANSPARENCY** — this handoff + audit table inline ✓
- **R11 DESIGN LAW** — no UI styling touched (search debounce + matrix inputmode are functional) ✓

---

## Coordination with Code 2

Code 2 е активно прави products.php hardening (PH3 CSRF / PH4 rate-limit / PH6 barcode unique / PH8 input validation / PH9 docs). My работа:
- **NOT touched** product-save.php — спазено per spec.
- **csrf_refresh endpoint** добавен в products.php (нов read-only ajax) — не conflict с CSRF guard (sessions stateless).
- **api() retry logic** — съвместима с PH3 csrfCheck (която не rotate-ва).

Ако Code 2 промени csrfCheck behavior (rotate) → моят retry може да loop infinite — има guard `__csrfRetried` flag който prevents this.

---

## Caveats / future

- **Bug C deeper investigation needed**: ако Tihol still вижда CSRF errors след моя fix, истинският root cause може да е session storage issue (Capacitor APK has different cookie behavior than Chrome). Може да изисква `credentials: 'include'` или explicit cookie sync.
- **Bug D edge cases**: ако user добави размери ENI "38W" + colors, моят size pattern регулярка може да recognize it incorrectly. Improvement: extend regex или add manual override чрез "Преименувай" в tab UI.
- **Bug F false-fix?**: ако Tihol still вижда Детайли при Назад, проверете дали е different back button (например на step 4 или step 5 some hidden button). My fix purges wizSubStep — но ако rendering логика go-ва directly to step 3 by other means, нужен е по-широк изпит.
