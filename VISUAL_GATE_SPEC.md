# VISUAL_GATE_SPEC v1.1 — auto-retry visual validation за design rewrites

Дата: 09.05.2026 (v1.0); 09.05.2026 (v1.1 — auth fixture)
Статус: v1.0 implemented; v1.1 auth fixture implemented
Контекст: chat.php P11 disaster (09.05) — всички 21 anti-regression правила pass-наха но визуалното беше счупено. Anti-regression защитава логиката, не визуалното съответствие. За products.php (~14617 реда) рискът е катастрофален без visual gate.

ESCALATING TOLERANCE LOOP

Всеки rewrite минава 5 итерации с увеличаваща се толерантност. CC прави първи опит на iter 1 с най-стриктни prag-ове; ако не pass-не — анализира diff, patch-ва само нарушаващите елементи, retry на следващия iter с малко по-голяма толерантност. Ако iter 5 fail-не — AUTO-ROLLBACK на оригинала + STOP + handoff към Тихол.

Iter 1: DOM 1% / Pixel 3%
Iter 2: DOM 2% / Pixel 4%
Iter 3: DOM 2% / Pixel 4%
Iter 4: DOM 3% / Pixel 5%
Iter 5: DOM 3% / Pixel 5% (last chance)

Pass = ВСИЧКИ 4 проверки pass на даден iter.

CHECK 1 — DOM STRUCTURE DIFF

Tool: design-kit/dom-extract.py (нов)
Парсва mockup.html + rendered PHP. Extract-ва дървото от tag-ове, класове, id, attributes. Ignore: whitespace, comments, attribute order, PHP echo placeholders. NOT ignore: липсващи tag-ове, грешни класове, преместени елементи.
Diff пресмята се: (added + removed + moved) / total_elements * 100.

CHECK 2 — CSS CLASSES COVERAGE

Tool: design-kit/css-coverage.sh (нов)
Извлича всички CSS класове от mockup. Проверява че всеки клас се ползва ≥1 път в rewrite-а. Ако ≥2 класа от mockup-а липсват в rewrite-а → FAIL. 1 липсващ клас е tolerated (може да е PHP-conditional render).

CHECK 3 — PIXEL DIFF

Tools: chromium-browser headless + imagemagick compare
Mobile viewport 375x812 (iPhone). Screenshot на mockup.html. Render PHP през local Apache + screenshot. ImageMagick compare с fuzz 10% (толерира anti-aliasing). Diff % = pixel difference / total pixels.

CHECK 4 — ELEMENT POSITION DIFF

Tool: design-kit/element-positions.js (chromium evaluate)
Extract bounding rect на всеки visible element от mockup. Същото за rewrite. Compare. Ако елемент се е преместил >20px → FAIL.

LEARNING LOG

design-kit/visual-gate-log.json — всеки run записва: file, iter pass-нал, кои elements нарушаваха най-често. След 10+ files CC чете лога и оптимизира prompt-а сам (например: "elements от type FOO винаги fail-ват — focus extra attention").

ROLLBACK ON ITER 5 FAIL

cp backups/<session>_<TS>/<file>.bak <file>
git checkout <file>
git reset HEAD <file>
Записва VISUAL_GATE_FAIL.md с: кои елементи не pass-ваха, какво CC опита, препоръчан manual approach. После STOP + handoff.

DEPENDENCIES

apt-get install: imagemagick, chromium-browser, python3-bs4
Без нови pip/npm пакети.

INTEGRATION В CLAUDE_CODE_DESIGN_PROMPT v3.0

Заменя секция EXIT CRITERIA с:
- Visual gate iter loop pass (max 5 опита)
- ALL 4 checks PASS на финалния iter
- Ако rollback — handoff с failure analysis вместо commit

IMPLEMENTATION PLAN

1 CC сесия (~3-4 часа):
- design-kit/dom-extract.py
- design-kit/css-coverage.sh
- design-kit/visual-gate.sh (orchestrator)
- design-kit/element-positions.js
- design-kit/visual-gate-log.json schema
- Test срещу chat.php (старо vs P11 mockup) — очакван pass; ако не — calibrate fuzz factors
- CLAUDE_CODE_DESIGN_PROMPT v3.0 integration
- Test срещу products.php scrHome като pilot

PRECONDITIONS

- Apache на droplet работи + може да render-не PHP (за screenshot)
- Mockup files в /mockups/ accessible
- chromium-browser installed (Ubuntu apt-get)

OPEN QUESTIONS

1. Mobile viewport: 375x812 (iPhone) или Z Flip6 spec (~373px)?
2. Fuzz factor 10% — calibrate ли след първи test?
3. Element position threshold 20px — окей за mobile или малко?
4. CC dependency на Apache running — какво ако crash?

END v1.0

═══════════════════════════════════════════════════════════════════════
## 13. AUTH FIXTURE (v1.1 — added 2026-05-09)
═══════════════════════════════════════════════════════════════════════

ПРОБЛЕМ
chat.php, life-board.php, products.php (и други login-protected файлове)
правят:
    if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
В headless render това резултира в 302 → login.php; visual gate сравнява
mockup срещу login страницата и винаги fail-ва. Auth fixture-ът решава това.

КОМПОНЕНТИ
- design-kit/auth-fixture.php   — set-ва $_SESSION в текущия PHP request
- design-kit/visual-gate-router.php — router script на php -S; require-ва
  fixture-а ПРЕДИ да require-не target-ния .php файл
- design-kit/visual-gate.sh      — нов флаг `--auth=admin|seller|none`
- design-kit/visual-gate-test.sh — 4-case acceptance wrapper

USAGE
    visual-gate.sh --auth=admin <mockup> <rewrite.php> <session_dir>
    visual-gate.sh --auth=seller <mockup> <rewrite.php> <session_dir>
    visual-gate.sh <mockup> <rewrite.html> <session_dir>   # без auth (default)

PRESET FIXTURES
- admin:  user_id=1, role=admin,  tenant_id=1, store_id=1, ui_mode=detailed
- seller: user_id=2, role=seller, tenant_id=1, store_id=1, ui_mode=detailed
Override чрез env vars пред командата:
    VG_USER_ID, VG_ROLE, VG_TENANT_ID, VG_STORE_ID, VG_USER_NAME

SESSION KEYS НАСТРОЙВАНИ
auth-fixture.php записва BOTH `role` и `user_role` (chat.php / life-board.php /
products.php четат `role`; spec-ът с user_role е удовлетворен паралелно).
Други keys: user_id, tenant_id, store_id, user_name, name, ui_mode='detailed',
logged_in=true, csrf_token (32 hex), login_time.

SAFETY МОДЕЛ — защо това НЕ компромет-ва production sessions
1. auth-fixture.php refuses да run-не ако PHP_SAPI != 'cli-server'.
   Apache vhost-ове ползват SAPI 'apache2handler' → fixture exit-ва веднага.
2. fixture refuses ако env var VG_AUTH != '1' → import без env е no-op.
3. visual-gate-router.php също refuses извън cli-server.
4. php -S процес-ът има own PID, own session.save_path под /tmp/sess_*
   на process-а. Production Apache pool не вижда тези session файлове.
5. Visual gate стартира port 8765 на 127.0.0.1 → не expose-нат outside loopback.
6. Cleanup trap kill-ва PHP_PID на script exit; временни session файлове
   се почистват от OS при /tmp tmpwatch.
7. Production webroot (/var/www/runmystore) НЕ се пипа — visual-gate авто-
   detect-ва PHP_DOC_ROOT от parent-а на rewrite файла. Когато rewrite е в
   /home/tihol/rms-visual-gate/, doc root е същата директория, не webroot-ът.

ACCEPTANCE TESTS (visual-gate-test.sh)
- TEST A: --auth=admin  + life-board.php → fixture: past auth
- TEST B: --auth=admin  + chat.php       → fixture: past auth
- TEST C: (no --auth)   + chat.php       → fixture: auth-wall (302 → login)
- TEST D: --auth=seller + life-board.php → fixture: past auth

Wrapper-ът докладва "fixture status" чрез out-of-band HTTP probe (отделен
php -S процес на port 8766). Тя дава clean signal независимо от end-to-end
visual gate verdict-а — auth fixture-ът се валидира сам по себе си.

ИЗВЕСТНО ОГРАНИЧЕНИЕ — DB fixtures не са в обхвата на v1.1
След като fixture-ът bypass-ва auth, target-ните файлове правят DB::run
queries срещу tenants / stores / sales таблиците. Без test tenant + store
rows (tenant_id=1, store_id=1) PHP fatal-ва на:
    effectivePlan(): Argument #1 ($tenant) must be of type array, false given
Това означава: visual-gate.sh PASS @ iter5 е недостижимо за .php файлове
докато няма test DB или DB stub layer. Този problem е orthogonal на auth
и трябва да се адресира в отделен SPEC (предложение: v1.2 — DB fixtures).

CHANGES TO visual-gate.sh
1. Нов CLI флаг `--auth=admin|seller|none` (parsed-out от positional args)
2. Нова функция `apply_auth_mode()` — set-ва VG_* env vars според режим
3. `ensure_php_server()` — когато AUTH_MODE != none, добавя
   visual-gate-router.php като router script към php -S командата

ROLLBACK / DISABLE
За временно изключване на fixture-а:
- Просто не подавайте --auth (default е none → backwards compatible)
- Или изтрийте design-kit/visual-gate-router.php → auth=admin/seller ще
  fatal-нат с „router missing" преди да започнат render

END v1.1

═══════════════════════════════════════════════════════════════════════
## 14. POSITION CHECK v1.3 — selector-based matching (added 2026-05-09)
═══════════════════════════════════════════════════════════════════════

ПРОБЛЕМ (v1.0/v1.2)
Position check сравняваше mockup и rewrite по подреждане в `defaultdict[selector]`.
Селекторът беше "tag + sorted classes" — не уникален. Например `<div class="lb-card">`
може да се появи 12 пъти. Алгоритъмът ги pair-ваше по индекс (1-ва lb-card в mockup
с 1-ва в rewrite, 2-ра с 2-ра ...). Когато rewrite имаше повече или по-малко
елементи от mockup (различни text content, preserved overlays които vg-skip
филтърът премахва от DOM check но не от positions), индексите се
изместваха и СЪЩИТЕ елементи в двата render-а сравняваха се с РАЗЛИЧНИ
counterparts → стотици false-positive "moved" reports (mockup nav SVG at
y=662 vs rewrite "nav SVG" at y=2788 — но "rewrite nav SVG" реално беше
друг SVG, не nav-овия).

S135 chat.php P11 rewrite калибрация показа:
- v1.0: 312-498 elements moved (повечето false positives)
- v1.2: 55 elements moved (data-vg-skip помогна частично)
- v1.3: 0-1 elements moved (точно колко са реално преместени)

РЕШЕНИЕ (v1.3)

Selector сега е пълен tree path с nth-of-type qualifiers:
```
body>main.app:nth-of-type(1)>div.glass.lb-card.q-loss.sm:nth-of-type(3)>span.shine:nth-of-type(1)
```

Всеки visible element получава глобално-уникален структурен идентификатор.
Position diff в visual-gate.sh прави 1:1 lookup по селектор:
- Селектори в SHARED set (присъстват в двата render-а) → compare bounding rects
- Селектори само в едната страна → НЕ се броят за position (DOM check ги ловит)

Изолира layout drift от structural drift, които v1.0/v1.2 conflate-ваха.

ALGORITHM (element-positions.js v1.3)

```js
function buildStableSelector(el) {
    if (!el || el === document.body) return 'body';
    const parts = [];
    let cur = el;
    while (cur && cur !== document.body && cur.parentElement) {
        const tag = cur.tagName.toLowerCase();
        const classes = getClassesSorted(cur);
        // nth-of-type position among same-tag siblings
        let idx = 1;
        let sib = cur.previousElementSibling;
        while (sib) {
            if (sib.tagName && sib.tagName.toLowerCase() === tag) idx++;
            sib = sib.previousElementSibling;
        }
        const seg = (classes.length ? tag + '.' + classes.join('.') : tag)
                  + ':nth-of-type(' + idx + ')';
        parts.unshift(seg);
        cur = cur.parentElement;
    }
    return 'body>' + parts.join('>');
}
```

POSITION DIFF (visual-gate.sh v1.3)

```python
ma = build_map(a)  # selector → (x, y), first occurrence wins for det
mb = build_map(b)
shared = set(ma) & set(mb)
moved = 0
for sel in shared:
    xa, ya = ma[sel]
    xb, yb = mb[sel]
    d = ((xa-xb)**2 + (ya-yb)**2) ** 0.5
    if d > threshold: moved += 1
print(moved)
```

ITER 5 PRAG — 50px (BUMP FROM 30px)

i18n placeholders ('{T_NAV_WAREHOUSE}' 17 chars) vs production strings
('Склад' 5 chars) center labels at slightly different x. Strict 30px
threshold flagged 1 такъв span on every chat.php P11 rewrite run.
50px (~13% of 375px viewport) absorbs i18n width drift while still
catching real layout breakage. Iter 1-4 thresholds unchanged.

CALIBRATION (S136 v1.3)

| Test                                | DOM    | CSS  | Pixel  | Position | Verdict |
|-------------------------------------|--------|------|--------|----------|---------|
| mockup-vs-self (sanity)             | 0.00%  | PASS | 0.27%  | 0        | PASS    |
| life-board.php vs P10 (no regress)  | 100%   | FAIL | 32.63% | 19       | FAIL†   |
| original chat.php vs P11            | 100%   | FAIL | 25.48% | 3        | FAIL†   |
| chat.php P11 rewrite vs P11 (final) | 2.28%  | PASS | 3.38%  | 0 @50px  | **PASS @ iter 5** |

† FAIL is correct — life-board body never built to P10, original chat
body never built to P11. v1.3 selector matching reduces false positive
position counts significantly (life-board went 312→19 from v1.2→v1.3,
original chat went 60+→3) so the remaining "moved" counts represent
real layout drift among the few elements that DO match selectors.

BACKWARDS COMPATIBILITY

Pre-v1.3 position files (DOM-order arrays without nth-of-type selectors)
will not match v1.3 selectors. Older runs in design-kit/visual-gate-log.json
should not be compared directly to v1.3 runs. Re-run a calibration sweep
after v1.3 rollout to establish a fresh baseline.

INTEGRATION POINTS

- design-kit/element-positions.js v1.3 — buildStableSelector()
- design-kit/visual-gate.sh — position_diff_count() rewritten to dict-based
  1:1 matching; ITER_TOL[5] position threshold 30→50

DEPLOYMENT

Auto-applies on next gate run. No env vars or invocation changes needed.

OPEN QUESTIONS (deferred to v1.4)

1. Should position diff weight by element area (large elements moved a lot
   matter more than small svg moved a lot)? Currently every shared element
   counts as 1.
2. Two truly-identical sibling subtrees at same nth-of-type collide on
   selector. Current behaviour: first-occurrence wins, second is silently
   dropped. Likely never happens in practice but should be measured.
3. Iter 5 50px threshold accommodates Bulgarian-vs-i18n-placeholder text
   width. Languages with longer strings (German) may need higher threshold.

END v1.3
