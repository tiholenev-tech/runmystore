# VISUAL_GATE_SPEC v1.0 — auto-retry visual validation за design rewrites

Дата: 09.05.2026
Статус: SPEC pending implementation
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
