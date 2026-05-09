# 📋 SHEF_HANDOFF — 10.05.2026 EOD

**Шеф-чат:** #16
**Дата:** 10.05.2026 (неделя), краен час ~21:00 EEST
**Beta status:** ОТМЕНЕН — Тихол: "аз съм бетата, когато реша ще е". Никакъв countdown.

---

## 1. КАКВО БЕШЕ НАПРАВЕНО ДНЕС

Branches push-нати на GitHub (НЕ merge към main след revert):
- s135-vg-fixtures — sandbox DB routing + seed_test_tenant.sql + render_helper + KALIBRATION_REPORT
- s136-partials-standard — стандартизирани partials/header.php + partials/bottom-nav.php + session mode logic + PARTIALS_STANDARD.md
- s136-chat-rewrite-v3 — chat.php P11 rewrite (8 commits) + visual-gate v1.3 + ALL 4 checks PASS на iter 5

Visual-gate инфраструктура — финализирана:
- v1.3 documented в VISUAL_GATE_SPEC.md §14
- 4 проверки работят: DOM diff, CSS coverage, Pixel diff, Position (selector-based)
- Sandbox DB runmystore_sandbox създадена с seed на tenant_id=999 (5 продукта, 3 ai_insights, 1 store, 1 user)
- 3 нови design-kit подобрения: dom-extract data-vg-skip, css-coverage rendered dump, element-positions tree-path selectors

Sandbox DB:
- CREATE DATABASE runmystore_sandbox (utf8mb4)
- Schema copied via mysqldump --no-data
- GRANT ALL за runmystore user
- Seed работи (след 2 ENUM корекции — urgency + fundamental_question)
- Production runmystore DB НЕ ПИПАНА

Catastrophe + revert:
- Merge на s136-chat-rewrite-v3 към main (commit a0835e0) → счупи production live
- Партиалите бяха агресивно refactor-нати → засегна ВСИЧКИ 20+ модула
- git reset --hard 8e440be + force push → production върнат към работещо състояние
- s136-chat-rewrite-v3 клонът остава на GitHub като АРХИВ

---

## 2. ГЛАВЕН УРОК ОТ ДЕНЯ

Visual-gate работи механично, но не гарантира production safety. Gate проверява 1 файл срещу 1 mockup. Не проверява че другите 19 модула които ползват същите партиали продължават да работят.

Реалният root cause:
- Mockup-ите от Опус включват header/nav вътре в самите mockups
- За да match-нем mockup, агресивно refactor-нахме shared partials
- Shared partials = афектират ВСИЧКИ страници едновременно
- 1 dom-извличане от gate ≠ функционален integration test

Корекция: Layout shell (header, nav, sidebar) и Page content са два РАЗЛИЧНИ слоя. Mockup-ите трябва да рисуват само content. Layout shell идва от production партиалите. Стандарт във всички сериозни frameworks (React, Laravel, Rails).

---

## 3. КЛЮЧОВИ РЕШЕНИЯ ОТ ТИХОЛ

А) Beta deadline ОТМЕНЕН — Тихол: "няма бета аз съм бетата когато реша ще е". Действие за следващи шеф-чатове: забрана да пишат "beta countdown X дни". Няма такъв.

Б) Component Library стратегия (препоръка, не приета още) — extract на повтарящи UI парчета (q-card, signal-card, KPI strip, op-button, glass surface) в partials/components.php като PHP функции. Mockup-ите дефинират компонент веднъж, всички модули го ползват.

В) Staging environment (не setup-нат) — препоръка: staging.runmystore.ai отделен webroot, същия codebase, тестова DB. Всяка дизайн промяна първо там.

Г) Опус mockup корекция — Опус трябва да преначертае всички 12 mockup файла да съдържат САМО content (body частта), не header/nav. Външна задача към него, ~2-3ч.

---

## 4. КАКВО ДА НЕ ПРАВИ УТРЕШНИЯТ ШЕФ-ЧАТ

1. Не приема beta countdown. Няма deadline 14-15.05. Тихол е бетата.
2. Не пуска "продължаваме s136 chat.php P11". S136 е архив, не следваща стъпка. Visual-gate v1.3 в него е useful, но самият rewrite не става merge без нова стратегия.
3. Не модифицира партиали (partials/header.php, partials/bottom-nav.php, partials/shell-init.php, partials/shell-scripts.php) докато:
   - Component library не е документирана като стратегия
   - Опус не е преначертал mockup-ите без layout shell
   - Staging environment не е setup-нат
4. Не пуска CC за визуален rewrite на повече от 1 модул паралелно без staging тест.
5. Не merge-ва на main без manual review на 5+ модула едновременно — не само target-а, а и shared dependency-та.
6. Не приема предишните P0 за beta blocker. Те бяха измислени около 14-15.05.

---

## 5. КАКВО ДА НАПРАВИ УТРЕШНИЯТ ШЕФ-ЧАТ (препоръчителен ред — Тихол одобрява)

1. DESIGN_REFACTOR_STRATEGY.md (нов файл, ~30 мин шеф-чат работа) — документира component library plan, layout shell vs content разделение, staging environment setup, mockup contract с Опус.

2. Брифинг за Опус (handoff към него) — преначертай 12-те mockup файла без header/nav, само content слой.

3. Staging environment setup (~1-2ч CC сесия) — staging.runmystore.ai отделен webroot, същия code, тестова DB.

4. Component library extraction (~3-4ч CC сесия) — partials/components.php с PHP функции за всеки UI компонент.

5. Едва тогава rewrite-и модул по модул със staging тест преди live merge.

---

## 6. ПРАВИЛА ЗА КОМУНИКАЦИЯ С ТИХОЛ

1. Само български. Без английски технически термини. Например: rewrite → пренаписване, commit → запис, branch → клон, merge → сливане. Изключение: имена на файлове, команди за конзола, code snippets.

2. Максимум 2-3 изречения на отговор. Изключение: разширени отговори когато Тихол изрично каже "разширено", или EOD протокол (handoff документи).

3. На дроплет команди се пускат от root, а Code Code сесии се стартират от user tihol чрез su - tihol. Тихол го прави сам, не се оплаква от Rule #36.

4. Никаква bullet списъци за status освен явно поискани.

5. Действай уверено за технически решения; питай за UX/логически.

6. Никога не команда EOD протокол сам — само Тихол го стартира с фразата "изпълни протокол за приключване на сесията".

---

## 7. CRON / СТРЕС СИСТЕМА — INFO

CC #15 каза 4 cron-а инсталирани (02:00, 03:00, 06:00, 06:30) за вечерен тест 09→10.05. Аз НЕ проверих /var/log/runmystore/MORNING_REPORT_*.md днес — Тихол не пожела. Утрешният шеф-чат да започне с проверка дали има reports от двете нощи (09→10 и 10→11).

---

## 8. WORKTREES STATE

/var/www/runmystore = main (production webroot, върнат към 8e440be)
/var/www/rms-design = s133-chat-rewrite (стар, не използван)
/var/www/rms-stress = s133-stress-finalize
/home/tihol/rms-visual-gate = s136-chat-rewrite-v3 + s136-partials-standard работни branches

Утре worktrees трябва да се преначистят след стратегията се финализира.

---

## 9. ОТВОРЕНИ ВЪПРОСИ ЗА ТИХОЛ (отговаря утре)

1. Component library стратегия — одобрен ли е общият план (partials/components.php)?
2. Mockup корекция — кога Опус ще е свободен да преначертае 12-те файла?
3. Staging environment — на същия droplet (поддиректория) или отделен droplet?
4. Какво е приоритет №1 след стратегия фаза — chat.php или products.php P3 (главна страница)?

---

## 10. КОМАНДА ЗА START НА СЛЕДВАЩ ШЕФ

Тихол paste-ва в нов чат:

Ти си шеф-чат #17 за RunMyStore.AI проект. Прочети SHEF_HANDOFF_20260510_EOD.md от GitHub (github.com/tiholenev-tech/runmystore/blob/main/SHEF_HANDOFF_20260510_EOD.md?plain=1 + парси rawLines), плюс MASTER_COMPASS.md (LOGIC LOG entry 10.05 EOD), STATE_OF_THE_PROJECT.md, PRIORITY_TODAY.md.

КРИТИЧНО: бета е ОТМЕНЕНА. Няма countdown. Тихол е бетата.

Комуникация: само български без английски термини. Максимум 2-3 изречения per отговор. На дроплет команди от root, CC от su - tihol.

Дай кратък status report (15/16 IQ test формат), после чакай команда.

---

## 11. КАТЕГОРИЧНИ ЗАБРАНИ (HARD STOPS — не подлежат на тълкуване)

Утрешният шеф-чат НЯМА правото да:

1. Да предлага CC сесия която модифицира ANY файл в partials/ — забраната е абсолютна докато DESIGN_REFACTOR_STRATEGY.md не е написана И одобрена от Тихол.

2. Да предлага git merge към main без 4-те условия:
   а) Staging environment работи и rewrite-ът е тестван там
   б) Manual review на ВСИЧКИ модули които ползват засегнатите файлове (не само target-а)
   в) Backup tar.gz на цялото repo state е направен ПРЕДИ merge
   г) Тихол изрично е казал "merge-вай" — НЕ "продължавай" или "ОК"

3. Да приема CC препоръки от типа "опция 2 — clean fix, ~1-2ч". Когато CC дава 3-4 опции с препоръка, шеф-чатът ЗАДЪЛЖИТЕЛНО спира и анализира blast radius. Колко модула засяга? Има ли shared dependencies? Какъв е rollback time?

4. Да продължава сесия която е надхвърлила 4 часа без EXPLICIT pause + checkpoint от Тихол. След 4ч умора + momentum = катастрофа. 7-8 часови сесии са забранени без изрична санкция.

5. Да предлага "force push" на main за каквото и да е, освен emergency revert след катастрофа.

6. Да започне визуален rewrite на products.php (~14617 реда) докато:
   - Component library е extracted и тествана на 2+ малки модула
   - Опус е преначертал mockup-ите без layout shell
   - Staging environment е работещ
   - Тихол изрично е дал зелена светлина

7. Да предлага "продължаваме s136" — S136 е архивиран урок, не следваща стъпка.

Ако шеф-чатът наруши някоя от тези забрани, Тихол го RESTART-ва незабавно. Тези 7 правила не подлежат на дискусия.

---
