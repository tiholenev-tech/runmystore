
All projects
ai бизнес партньор



How can I help you today?

S79 products.php event handlers fix
Last message 39 seconds ago
Маркетинг стратегия - кратко резюме
Last message 6 hours ago
Проблем с Anthropic
Last message 7 hours ago
шеф чат 1
Last message 13 hours ago
ЧАТ ИНФОРМАЦИЯ ЗА ВСИЧКО ТЕХНИЧЕСКО ПО ПРОЕКТА
Last message 14 hours ago
S78-ЧАТ 1 sequential startup protocol
Last message 14 hours ago
ЧАТ 2 Database schema и миграции протокол
Last message 15 hours ago
RunMyStore.ai S79 — products.php главна rewrite
Last message 15 hours ago
Документи и файлове за преглед
Last message 18 hours ago
AI фитнес инструктор
Last message 19 hours ago
Пълна консолидация на RunMyStore.ai архитектура и философия
Last message 23 hours ago
Пълна консолидация на RunMyStore.ai архитектура и философия
Last message 1 day ago
Runmystore.ai фундаменти и приоритизация
Last message 1 day ago
Визи за проекта
Last message 1 day ago
S77 план: основна страница и работни потоци
Last message 2 days ago
S79 products.php rewrite и UI тестване
Last message 2 days ago
S78.1 фундамент за products.php rewrite
Last message 2 days ago
S78 products.php фундамент
Last message 2 days ago
S76.1
Last message 2 days ago
Session 76 handoff documentation
Last message 2 days ago
S73B-C
Last message 2 days ago
Bible tech 3.0 документ
Last message 3 days ago
изцяло нова концепьия онбординг и скалиране по държави
Last message 3 days ago
сесия 71 супер важна промяна в ux режима
Last message 3 days ago
s74b2
Last message 3 days ago
Wizard rewrite task S73.B with HANDOFF priority
Last message 3 days ago
Обединяване на Bible Core и Bible Tech файлове
Last message 3 days ago
RunMyStore BIBLE консолидация - Част 3
Last message 3 days ago
Предварителна подготовка преди обсъждане
Last message 3 days ago
Обсъждане преди писане
Last message 3 days ago
Memory
Only you
Purpose & context Тихол е основателят и единствен разработчик на RunMyStore.ai — AI-first SaaS платформа за малки физически магазини в България и Европа. Целевият потребител е „Пешо" — нетехнически собственик на магазин. Проектът е на PHP/MySQL, хостван на DigitalOcean, репо tiholenev-tech/runmystore (ПУБЛИЧНО — Claude чете файлове директно от raw.githubusercontent.com). Основни закони (не се нарушават): ЗАКОН №1: Пешо не пише нищо. Всичко е глас, снимка или едно натискане. AI пита, Пешо отговаря с глас. ЗАКОН №2: PHP смята, Gemini говори. Всеки pill/signal = чист PHP+SQL. Gemini само в свободен чат. ЗАКОН №3: AI мълчи, PHP продължава (fallback ladder без crash). НИКОГА „Gemini" в UI — винаги „AI". НИКОГА hardcoded „лв"/„BGN"/„€" — винаги priceFormat($amount, $tenant). BG двойно обозначаване (€+лв) задължително до 8.8.2026; след това само €. НИКОГА hardcoded български текст — всичко през tenant.lang. Ключови хора: Ени Тихолов — първи бета клиент с няколко магазина (tenantid=52, EUR). Тест акаунт: tenantid=7, storeid=47. --- Current state Проектът е в активна разработка (~S73+). Основни модули в ход: products.php (~500KB+): S73 покрива пълен wizard rewrite с прогресивно разкриване (акордеон, без Next/Back stepper), fullscreen matrix overlay за вариации (Qty/Min per cell, autoMin formula Math.round(qty/2.5) мин.1). Завършени: CSS foundation (v4- prefix, Neon Glass), bugfixes (editProduct axes, wizLoadSubcats race condition). Neon Glass дизайн система: 25 HTML mockup файла в отделен chat. Всеки модул = scrollable страница с .glass карти, всяка карта има 4 span елемента (2× shine + 2× glow), gradient clip-path на големи числа, minified CSS. Wizard правила (S73.B): Няма „чернова" — минималният запис (ime+цена+брой) = истински продукт. Печатът е отделна страница/overlay достъпен от всяка стъпка чрез [🖨]. Supplier ПРЕДИ Category (защото един доставчик → много категории). Бижутерия проект: Нов tenant за бижута на същата база данни. Нужно: нов tenant (SQL), AI Studio конфигурация (Gemini API key), Web Bluetooth TSPL печат на етикети в products.php. Термо принтер: DTM-5811, TSPL, Bluetooth (DC:0D:51:AC:51:D9, PIN 0000), 50×30mm. Сървър: Upgraded от 1GB на 2GB RAM ($12/мес) — MySQL беше убиван от OOM killer. Активни P0 bugs (от S72, за fix преди wizard redesign): 7 бъга в products.php документирани в SESSION71HANDOFF.md. --- On the horizon S73.B: HTML rewrite на wizard variations стъпка (прогресивно разкриване, акордеон, field order per BIBLE v2.1 rules #69-83). S73.C: Matrix overlay с autoMin, ▲▼ бутони. S80.5: Разширен filter drawer за products.php (по цена, доставчик, категория, композиция, наличност, без снимка/баркод). Поръчки екосистема (S77+): orders.php — 12 входни точки, 11 типа поръчки, 8 статуса (draft→confirmed→sent→acked→partial→received→cancelled/overdue). 6-те въпроса вградени като supplierorderitems.fundamentalquestion ENUM. RULE S77 — 6 фундаментални въпроса: Всеки модул структуриран около: 1)Какво губя 2)От какво 3)Какво печеля 4)От какво 5)Поръчай 6)НЕ поръчай. UI цветове: червено=загуба, виолет=причина, зелено=печалба, амбър=поръчай, сиво=не. Термо принтери: 200 бр. поръчани, ~края на април 2026. Bluetooth Web API + ESC/POS интеграция. bizlearneddata (Фаза 2): AI се учи от клиентите — нови размери/цветове/подкатегории записват се с usagecount. Ревизионен протокол (S79+): След сесия с 5+ fix-ове → финална ревизия (мъртъв код, дубликати, SESSIONXXHANDOFF.md, commit). --- Key learnings & principles Wizard field order: Supplier → Category → Subcategory (доставчикът филтрира категориите чрез suppliercategories). Критичен подход: Тихол иска 60% плюсове + 40% честна критика. Никога 100% ентусиазъм. „Ти луд ли си" = сигнал че е пропуснат важен контекст. Дизайн промени: Claude НИКОГА не пита. Чете DESIGNSYSTEM.md + mockup-ите, прилага 1:1, дава Python скрипт веднага. Питане за дизайн = дразнене. Логически/продуктови решения: Питам Тихол. Технически решения: Claude решава сам, действа директно. Питане за технически = дразнене. Basket Analysis: Фаза 1, но се активира само ако има saleitems данни за поне 30 пълни дни. Season правило (#25): products.season = NULL/allyear → AI мълчи за времето. Signal tap: Отваря Signal Detail overlay (НЕ чат). Action бутони от DB. „Добави за поръчка" = orderdraft (чернова, не изпраща). Role-based режими: Пешо (seller) = SIMPLE MODE (home.php, 4 бутона + AI чат). Owner/Manager = DETAILED MODE (products.php, 6 секции, stats, orders, settings). 6-те въпроса = визуални за owner/mgr; за Пешо AI ги превежда в гласови действия. AI = операционен слой над всички модули (не отделен модул). Всеки модул декларира $MODULEACTIONS. AI чете intent, изпълнява или навигира. Достъпен от всеки екран (FAB/bottom nav). Pricing (S52+): FREE €0 (лоялна+етикети), START €19 (AI джаджа, каса+склад), PRO €49+€9.99/магазин (AI мозък, multi-store). Trial: 1 мес безплатен PRO → ден 29 избор. Ghost cards: START 1/ден, FREE 1/седмица. --- Approach & patterns Комуникация: Тихол комуникира на български, директно и кратко, all-caps за акцент. „ДЕЙСТВАЙ", „ОК", „ГОТОВО". Очаква действие без излишни въпроси. Workflow (S79+): КЛОД ТУК = 90% от случаите: малки fix-ове, Python скриптове за paste в droplet конзолата, логически решения, дизайн, mockups. CLAUDE CODE = само ГОЛЕМИ работи: цял rewrite на 500KB+ файл, многочасови задачи. Тихол предпочита paste в droplet конзолата пред Claude Code сложността. Сесия протокол: git pull в началото на всяка сесия. Чета NARACHNIKTIHOLv11.md, Biblev30appendix.md, DESIGNSYSTEM.md, последния SESSIONXXHANDOFF.md. products.php = 501KB+ → чета на части. Commit след всеки потвърден working fix: cd /var/www/runmystore && git add -A && git commit -m "S[XX]: [описание]" && git push origin main — без да питам. Сесията завършва с SESSIONXXHANDOFF.md. CHEAT МЕНЮ за Claude Code: 1=Yes (изпълни), 2=No (откажи). Безопасни команди (cp/cat/grep/ls/git/python/php/mysql) → 1. Опасни (rm/git reset/DROP/TRUNCATE) → 2, питай Тихол. AI Chat спецификация (Фаза 1): Разбира грешен правопис/говорим български. Гласов вход (speech-to-text) с дискретен бутон [🎤]. Честотен контрол на проактивни съобщения (настройва се). Всеки съвет идва с обяснение защо. Onboarding = разговор с AI (без форми). Confirm-first за всяко destructive действие. Навигация с deeplinks от чата. Проактивен (cron triggers). Наръчник за всяка функция. Предстои: AI Conversation Flow документ преди system prompt. Sale.php правила: Камера постоянно отворена + бийп + зелено при сканиране. НИКОГА native клавиатура — custom numpad (контекстен: код/бройки/отстъпка/получено), [АБВ→] за БГ фонетична клавиатура. Паркиране с ⏸ + swipe. Едро = смяна на цвят. Камера-хедър: видеото е фон (80px), overlay с навигация, зелена лазерна линия. Voice overlay дизайн (одобрен): rec-ov (backdrop-filter:blur(8px)) + rec-box (floating bottom, border-radius:20px, indigo glow). Голям REC индикатор: червена точка pulse + „● ЗАПИСВА"; зелена + „✓ ГОТОВО". Транскрипция + бутон „Изпрати →". Прилага се навсякъде. --- Tools & resources Сървър/инфраструктура: DigitalOcean, /var/www/runmystore/, 2GB RAM. DB creds: /var/www/runmystore/config/database.php (НЕ config.php). Helpers: /var/www/runmystore/config/helpers.php. Backup: mysqldump с MYSQLPWD env var → /root/backupsXXYYYYMMDDHHMM.sql. Структура: PHP файлове в корена, config/ docs/ css/ js/ images/ fonts/ includes/. Деплой правила: НИКОГА sed за file edits — само Python скриптове. Deployment sequence: heredoc → /tmp/sXXname.py → python3 /tmp/sXXname.py → php -l → git add -A && git commit && git push. Python patch scripts: duplicate-application guard + уникален anchor string (не line numbers). За файлове >11KB: xz+base64, 1 paste. GITHUB ПРЯК: https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/[FILE] (docs/ за .md файлове). AI модели: Opus (Adaptive mode): архитектура и сложни бъгове. Sonnet: стандартна разработка. Haiku: CSS и текстови промени. AI APIs: Gemini 2.5 Flash (основен), OpenAI GPT-4o-mini (fallback при 503/429). Два Gemini API ключа в ротация. Хардуер: DTM-5811 термо принтер (TSPL, Bluetooth, DC:0D:51:AC:51:D9, PIN 0000, 50×30mm). Документация (задължително четене на старт): NARACHNIKTIHOLv11.md Biblev30appendix.md (или последната BIBLE версия) DESIGNSYSTEM.md Roadmap.md Productsdesignlogic.md / Ordersdesignlogic.md Последния SESSIONXX_HANDOFF.md

Last updated 21 hours ago

Instructions
изпълнявай винаги моите инструкции едно към едно и никога не прави нещо на своя глава без да ме попиташ първо нито смяна на визия нито смяна на код и то поправка или нещо което би променило моята визия за проекта винаги питай преди да предприемеш действие и абсолютно спазвай моите команди и изисквания! Говори на български например visual reright ми кажи визуална корекция или нещо на български за да разбера!

Files
77% of project capacity used
Indexing

SHEF_RESTORE_PROMPT.md
252 lines

md



STARTUP_PROMPT.md
88 lines

md



MASTER_COMPASS.md
1,236 lines

md



RunMyStore.ai проект резюме.docx
4,196 lines

docx



orders-by-supplier-s77.html
443 lines

html



ORDERS_DESIGN_LOGIC.md
1,220 lines

md



PRODUCTS_DESIGN_LOGIC.md
1,360 lines

md



SESSION_77_HANDOFF.md
437 lines

md



ROADMAP.md
197 lines

md



ROADMAP.md
178 lines

md



BIBLE_v3_0_APPENDIX.md
612 lines

md



products (17).php
7,588 lines

php



sale-v5.html
278 lines

html



BIBLE_v3_0_TECH.md
5,243 lines

md



sale-payment-v3.html
241 lines

html



warehouse.html
248 lines

html



products-main (1).html
440 lines

html



promotions.html
469 lines

html



products-list.html
293 lines

html



orders.html
276 lines

html



login-register.html
413 lines

html



landing-hero.html
252 lines

html



inventory-onboarding.html
396 lines

html



inventory-hub-dialogs.html
406 lines

html



inventory-counting.html
233 lines

html



home-neon.html
1,272 lines

html



home-jouan.html
1,087 lines

html



home-detailed.html
916 lines

html



finance.html
1,097 lines

html



delivery.html
919 lines

html



customers.html
415 lines

html



ai-chat.html
220 lines

html



add-product-variations.html
1,656 lines

html



add-product-print.html
1,025 lines

html



add-product-business.html
940 lines

html



add-product-ai.html
848 lines

html



add-product.html
1,328 lines

html



warehouse.html
327 lines

html



stats.html
990 lines

html



signals-overlays.html
409 lines

html



settings.html
465 lines

html



PARTNERSHIP_SCALING_MODEL_v1.md
722 lines

md



DOCUMENT_1_LOGIC_PART_3_ONLY.md
1,493 lines

md



DOCUMENT_1_LOGIC_PART_2_ONLY.md
609 lines

md



DOCUMENT_1_LOGIC_PART_1_ONLY.md
832 lines

md



стратегия за скалира е.docx
327 lines

docx



01_mission_and_philosophy.md
61 lines

md



17_final_verification.md
146 lines

md



16_summary_and_next_steps.md
205 lines

md



15_reorder_precision.md
264 lines

md



14_shadow_testing_admin.md
226 lines

md



13_engagement_tracking.md
196 lines

md



12_feedback_and_actions.md
209 lines

md



11_simple_mode_ui.md
187 lines

md



10_history_of_day.md
141 lines

md



09_multi_role_visibility.md
173 lines

md



08_onboarding.md
172 lines

md



07_tonal_diversity.md
175 lines

md



06_anti_hallucination.md
168 lines

md



05_selection_engine.md
206 lines

md



04_plans_and_trial.md
93 lines

md



03_evening_wrap.md
109 lines

md



02_daily_rhythm.md
77 lines

md



CONSOLIDATION_HANDOFF.md
349 lines

md



STRIPE_CONNECT_AUTOMATION.md
1,278 lines

md



BIBLE_v3_0_CORE.md
3,452 lines

md



SESSION_73A_HANDOFF.md
241 lines

md



RUNMYSTORE_AI_BRIEF-1.md
203 lines

md



add-product-ai.html
848 lines

html



add-product-print.html
1,025 lines

html



add-product-print.html
1,025 lines

html



add-product-business.html
940 lines

html



SESSION_73_HANDOFF.md
187 lines

md



SESSION_72_HANDOFF.md
149 lines

md



NARACHNIK_TIHOL_v1_1.md
409 lines

md



OPERATING_MANUAL.md
469 lines

md



SESSION_71_HANDOFF.md
242 lines

md



inventory (3).php
253 lines

php



warehouse.php
528 lines

php



stats.php
1,216 lines

php



INVENTORY_v4.md
506 lines

md



chat (8).php
1,401 lines

php



CHAT_PHP_SPEC_v7.md
525 lines

md



ai-topics-catalog.json
14,704 lines

json



WEATHER_INTEGRATION_v1.md
248 lines

md



S51_AI_TOPICS_MASTER.md
263 lines

md



S51_UI_DESIGN_SPEC.md
231 lines

md



BIBLE_v1_2_ADDITIONS.md
142 lines

md



SESSION_51_HANDOFF.md
285 lines

md



SESSION_50_HANDOFF.md
47 lines

md



TECHNICAL_REFERENCE_v1.md
534 lines

md



BIBLE_v1_1.md
609 lines

md



RUNMYSTORE_PARTNER_PRESENTATION.md
303 lines

md



AI_BRAIN_v6_0.md
1,320 lines

md



BUSINESS_STRATEGY_v2.md
567 lines

md



MASTER_TRACKER_v9_0.md
258 lines

md



SESSION_48_HANDOFF.md
477 lines

md



biz-compositions.php
84 lines

php



TECHNICAL_ARCHITECTURE_v1.md
1,318 lines

md



AI_CONVERSATION_FLOW_TOPICS_v1.md
3,293 lines

md



build-prompt-integration.php
112 lines

php



INVENTORY_HIDDEN_v3.md
590 lines

md



ai-topics-catalog.json
1 line

json



topics-verification-v4.md
2,104 lines

md



GEMINI_SEASONALITY.md
3,150 lines

md



signin.html
373 lines

html



reset-password.html
334 lines

html



AI-Sklad-FINALEN-ARHIV-v2.md
401 lines

md



INVENTORY_v4.md
24.02 KB •506 lines
Formatting may be inconsistent from source

# RunMyStore.ai — Система за инвентаризация v4.0
## Финална спецификация | S60 | 13.04.2026
## Заменя: INVENTORY_HIDDEN_v3.md

---

## 1. ФИЛОСОФИЯ

**"Магазинът се учи докато работиш."**

Пешо не спира да продава за да брои. Не въвежда всичко предварително. Продава от секунда 1. Системата учи от всяко действие — продажба, доставка, броене. Точността расте органично.

**Единицата за броене е АРТИКУЛЪТ, не зоната.**

Четири AI-а предложиха зоната като единица. Собственикът (20 години опит) каза: "Стоката се движи. Броя рафта, след 10 минути вече съм взел 3 неща и съм ги сложил другаде." Артикулът се брои НАВСЯКЪДЕ наведнъж. Местенето не чупи нищо.

**ЗАКОН №1 — Пешо не пише нищо.** Всичко е глас, скенер или тап.

**ЗАКОН №2 — Продажбата НИКОГА не чака.** Нищо от инвентаризацията не блокира касата.

---

## 2. ДВА РЕЖИМА

Системата определя автоматично на база: брой артикули + имат ли вариации + business_type.

### БЪРЗ РЕЖИМ (бутик, <500 артикула, малко вариации)
- 2-3 места, скенер основно
- 30 мин/ден, 3-5 дни за целия магазин
- "Другаде?" → рядко "Да"

### ПЪЛЕН РЕЖИМ (500+ артикула ИЛИ много вариации)
- 30-40 места, глас + избор от списък
- 10-15 мин/ден, 1-2 месеца (при голям магазин)
- Всяка вариация се брои отделно (S:10, M:8, L:12)
- "Другаде?" → почти винаги "Да"

**400 артикула × 40 вариации = 16,000 SKU = ПЪЛЕН режим.**
**500 артикула × 1 вариация = 500 SKU = БЪРЗ режим.**

---

## 3. ОНБОРДИНГ

AI разговор, не форма. Всичко с глас и тап.

### Стъпка 1: Начало

"Искаш ли да знаеш точно колко стока имаш?"

- **"Да, да започваме"** → продължава
- **"Имам файл с артикули"** → CSV/Excel import
- **"По-късно"** → skip

### Стъпка 2: Размер на магазина

"Колко артикула имаш приблизително? Не е нужно да е точно."

- **Под 500** → потенциално БЪРЗ режим
- **Над 500** → потенциално ПЪЛЕН режим

"Повечето имат ли размери или цветове?"

- **"Да, повечето"** → потвърждава ПЪЛЕН ако е >200 арт.
- **"Не"** → потвърждава БЪРЗ ако е <500 арт.

### Стъпка 3: Покажи магазина

Голям (i) инфо бутон с подробно обяснение + примери.

AI: "Сега ще обходим магазина заедно. Снимай ВСЯКО отделно място където стои стока — щендер, рафт, витрина, маса, стена, кука, кашон на пода. Кръсти го както ТИ му казваш."

**Примери:** "Щендер до входа, Рафт зад касата долен, Витрина дясна, Стойка с чорапи, Рафт 1 отделение 3..."

За всяко място:
1. Пешо казва с глас какво е
2. Снимка е ЗАДЪЛЖИТЕЛНА — без нея не минава нататък
3. За склад — снимай рафт по рафт, отделение по отделение

(i) инфо бутон присъства на ВСЕКИ екран в целия flow.

### Стъпка 4: Потвърждение

Показва всички места с снимки, разделени по:
- МАГАЗИН (X места)
- СКЛАД (Y отделения)
- КАСА (Z рафта)

Бутони: "Точно е" / "Промени" / "Добави още"

### Стъпка 5: Откъде да започне

AI ПРЕПОРЪЧВА: "Започни от рафтовете в склада. Там е масата от стоката и се брои по-лесно. Когато стигнеш до магазина — повечето артикули вече ще са преброени."

Показва снимките на местата. Пешо тапва → влиза в броене.

---

## 4. БРОЕНЕ — ОСНОВЕН FLOW

### 4.1 Избор на място

Списък с всички места + снимки + статус (непреброено / преброено преди X дни).

### 4.2 Артикул по артикул

Пешо отваря отделението/рафта. За всеки артикул:

**Стъпка А: Идентификация**
- Скенира баркод → системата го намира
- Казва с глас → AI match (shortlist от 2-3 предложения с тап)
- Непознат баркод → "Нов артикул или нов баркод за нещо което вече продаваш?"

**Стъпка Б: Бройки**
- Без вариации → прост stepper (+/−)
- С вариации → размерна решетка (S: [+−], M: [+−], L: [+−], XL: [+−])
- Кашони → "Цели кашони × бройки в кашон + отделни = общо"

**Стъпка В: Без баркод?**
- Ако има Bluetooth принтер → "Печатай етикет" → лепи → сканира
- Ако няма принтер → показва артикулния номер (вече съществува от създаването) → Пешо го записва на ръка

**Стъпка Г: Без доставна цена?**
- "Този артикул ще го получаваш ли пак или е единичен?"
- Редовен → "Ще видим цената от следващата доставка"
- Единичен → "На колко го купи горе-долу?" → записва

**Стъпка Д: Готов → следващ артикул**

### 4.3 Край на рафт — BATCH проверка другаде

Когато Пешо каже "готово с това място", системата НЕ го кара да тича за всеки артикул поотделно.

Вместо това показва СПИСЪК:

"5 артикула от тук имаш и другаде. Преброй ги наведнъж — 3 минути."

- Бикини розово дантела → Стена с бикини
- Чорапи Sonic Mod → Стена с чорапи
- Чорапогащници Pompea → Рафт до касата
- ...

Бутон: "Тръгвам да ги броя" или "После"

Едно обхождане на магазина, не 8 отделни пътувания.

### 4.4 Нов артикул по време на броене (БЪРЗО ДОБАВЯНЕ)

Пешо среща артикул който го НЯМА в системата. Не минава през 7-стъпков wizard.

**Мини-добавяне (4 стъпки):**
1. "Какво е?" → глас или ръчно → ИМЕ
2. "На колко го продаваш?" → ЦЕНА
3. "Има ли размери/цветове?" → Да → СЪЩИЯ preset picker от products.php wizard стъпка 4 (biz-coefficients typeahead, бутон "Добави няколко")
4. Бройки → СЪЩИТЕ stepper-и от products.php wizard стъпка 6

Печат етикет / запиши код → Готово → продължава броенето.

Артикулът е създаден с confidence 20%. Останалото (снимка, доставчик, категория, AI описание) се допълва по-късно от products.php.

**СПОДЕЛЕН КОД:** Вариациите UI (preset picker + qty steppers) се извлича в общи функции, ползвани и от products.php wizard, и от inventory.php бързо добавяне.

### 4.5 Край на деня

"За днес стига!"

Показва:
- Преброени артикули
- Разлики
- Нови артикули
- Прогрес бар: "X от Y места (Z%)"
- "Утре продължаваш от Рафт 1 отд. 2"

Бутони: "Продължи с още едно място" / "Край за днес"

---

## 5. ПРОВЕРКИ ЗА КАЧЕСТВО

### 5.1 Случайна проверка по време на броене

На всеки 10-15 преброени модела, с шеговит тон:

"Бърза проверка! Чорапи Sonic Mod черни — каза 55 бройки. Още толкова ли са?"

- "Да" → ✓
- "Не точно" → коригира
- "Пропусни" → минава нататък

### 5.2 Бързо броене

45 артикула за 90 секунди → "Световен рекорд! Сигурен ли си че не пропусна нещо?"

### 5.3 "Бръкни назад"

След "готово" с място → "Бръкни и зад рафта. Паднало ли е нещо, скрито ли е?"

### 5.4 Голяма разлика → снимка + причина

Разлика >30% или >5 бройки → "Голяма разлика. Снимай рафта."

Причини (чипове за тап):
- Преместено
- Грешно минало броене
- Продадено без отчет
- Кражба
- Дефект
- Грешен етикет

### 5.5 Micro-Proof

За непотвърдени артикули, вместо "има ли го":
- "Прочети ми последните 3 цифри от баркода"
- "Какъв размер пише на етикета?"

Естествено потвърждение, не тест.

---

## 6. САМОКОРИГИРАЩА СЕ СИСТЕМА

### 6.1 Продажба разкрива грешки

- Преброено 5, продадени 6 без доставка → "Бройката не беше точна. Колко имаш реално?"
- Преброено 5, нищо не продадено 60 дни (а обикновено 2/месец) → "Сигурен ли си че имаш 5?"
- На минус без доставка → сигнал за грешно броене

### 6.2 Мини-проверки (ежедневно, 2 минути)

AI избира 5-10 артикула: "Бърза проверка — бялата пола, каза 3. Все още 3 ли са?"

Цели:
- Най-остарелите (30+ дни без потвърждение)
- Необичайни модели на продажба
- Предишни разлики

### 6.3 Увереност се разпада

- 30+ дни без потвърждение → увереност пада 5%/седмица
- 60+ дни → артикулът е "остарял"

### 6.4 При доставка на НЕПРЕБРОЕН артикул

Пешо скенира артикул от доставката. Системата вижда: никога не е бройн.

"Получаваш 30 бройки. Имаш ли вече такива в магазина?"

- "Да" → "Преброй ги сега навсякъде" → Общо = доставка + заварени
- "Не, нов артикул" → Общо = само доставката
- "Да, но после" → Записва доставката, маркира "чака броене на останалите"

### 6.5 При продажба на непреброен артикул

Просто продаваме. Записваме. Леко напомняне ВЕДНЪЖ на ден:

"Днес продаде 3 артикула които не са преброени. Когато имаш 5 минути — кажи колко са останали."

### 6.6 При продажба на артикул ИЗВЪН системата

Продажбата минава ВЕДНАГА (3 секунди, клиентът не чака). Системата създава артикула (име + цена). След като клиентът си тръгне:

"Тази черна тениска — имаш ли още от нея?"
- "Да" → "Колко горе-долу?" → записва
- "Не, беше последната" → бройка 0
- Игнорира → нищо

---

## 7. ДОСТАВНА ЦЕНА

### При броене:
"Ще го получаваш ли пак?" → Единичен → питаме веднага. Редовен → чакаме документ.

### При доставка:
Документът има цени → влизат автоматично. Без документ → питаме.

### Ескалация по време:
- Седмица 1-2: Нищо
- Седмица 3: Лек намек
- Месец 1: "Тези артикули продадоха за 800 лв, но не знам колко печелиш"
- Месец 2+: Задължително при всяко отваряне

Винаги приема "горе-долу" — по-добре приблизително отколкото нищо.

---

## 8. ДЕФЕКТНА СТОКА

При броене → бутон "С дефект". Записва се в отделен модул (дефектни артикули).

AI после: "Имаш дефектна стока за 85 лв. Пусни на промоция или отпиши."

---

## 9. КОГА Е СВЪРШИЛА ИНВЕНТАРИЗАЦИЯТА

### Първо пълно броене = завършено когато:
1. Всеки активен артикул е преброен поне веднъж
2. Всеки артикул има баркод (фабричен или наш)
3. Вариациите са преброени по вариация, не "общо"
4. Единичните артикули имат доставна цена

### 100% потвърден магазин:
3-4 месеца без аномалии → 20 УМНО ИЗБРАНИ артикула за проверка (скъпи + бързо продавани + с предишни разлики). Ако всичко съвпада → 100%.

На всеки 3-4 месеца → пак 20 случайни. Като медицински преглед.

### Никога не "свършва":
- Артикули остаряват
- AI предлага мини-проверки
- Повторно пълно броене на 2-3 месеца е препоръчително

### Проблемни сценарии:
- 95% преброени, последните 5% са в кашон → AI настоява
- 20+ самокорекции → "Броенето беше прибързано, тези 20 трябва пак"
- Артикули на 0 без да са маркирани "свършили" → "Свършили ли са или не си ги преброил?"

---

## 10. СМЯНА НА БАРКОД

При непознат баркод ПЪРВИЯТ въпрос е:

"Нов артикул или нов баркод за нещо което вече продаваш?"

- Нов артикул → бързо добавяне
- Нов баркод → обвързва стария артикул с новия баркод

Критично за бельо и чорапи — фабриките сменят баркодове между партиди.

---

## 11. КАШОНИ И ПАКЕТИ

Бутон "Кашон" при броене. Обяснение преди:

"Ако стоката е в кашони или пакети — не брой един по един. Кажи колко ЦЕЛИ кашони и колко бройки в един кашон."

UI:
- Цели кашони: [+−] × Бройки в кашон: [+−]
- + отделни: [+−]
- = ОБЩО: число

---

## 12. ПРОДАЖБА ПО ВРЕМЕ НА БРОЕНЕ

Пешо брои артикул на 3 места. Между първото и третото продава 1 бройка.

Системата пази baseline_at на всяка сесия. При завършване:

"Докато броеше, 1 бр. Черна рокля мина през касата. Коригирам."

Автоматично, без въпроси.

---

## 13. CRASH RECOVERY

Автоматично запазване на всеки 5-10 артикула.

При отваряне след crash: "Продължи от артикул 150?" → Да/Не.

---

## 14. ТРАНСФЕРИ МЕЖДУ МАГАЗИНИ

При трансфер на артикул в друг магазин:
- Ако НЕ Е преброен → "Преброй го първо преди да го дадеш"
- Ако Е преброен → намаляваме в магазин 1, увеличаваме в магазин 2
- Статус "На път" докато другият магазин потвърди

---

## 15. OFFLINE MODE (ЗАДЪЛЖИТЕЛНО V1)

В склада може да няма WiFi. Bluetooth принтерът се разкачва.

Броенето работи ИЗЦЯЛО на телефона. Записва локално. Когато се върне интернет → синхронизира. При конфликт (продажба междувременно) → показва и пита.

---

## 16. МОТИВАЦИЯ — "СКРИТИ ПАРИ"

Никога не казваме "инвентаризация". Казваме "скрити пари".

"Мисля че имаш стока за 400 лв скрита някъде, която не си продал от 6 месеца. Провери рафт 2 днес."

При намерен забравен артикул: "Намерено! Яке за 45 лв. Сканирай го, утре го пускаме на промоция."

### Ескалация (визуална, БЕЗ звук):
- 90%+ здраве → лек намек веднъж седмично
- 70-89% → веднъж дневно
- 50-69% → при всяко отваряне
- <50% → банер с конкретна стойност

Никога не блокира. Пешо може да игнорира завинаги.

---

## 17. STORE HEALTH

Преди първо броене: НЕ показваме процент. Показваме етап:
- "AI се калибрира"
- "AI знае ⅓ от магазина ти"

След достатъчно данни:
- 95-100% → "Магазинът е в перфектна форма"
- 80-94% → "Добре, но AI гадае за някои неща"
- 60-79% → "AI не е сигурен. Съветите може да са неточни"
- <60% → "AI гадае. Основните функции са ограничени"

### Компоненти:
- Покритие (40%): % артикули преброени в последните 30 дни
- Свежест (30%): средно време от последно броене
- Увереност (30%): средна confidence по артикули

---

## 18. ТЕХНИЧЕСКИ БЕЛЕЖКИ

### Нови таблици (V1):
```
store_zones              — места + снимки (за навигация и подсказки)
zone_stock               — snapshot: кое място колко е имало при последно броене
inventory_count_sessions — сесии за броене
inventory_count_lines    — ред по ред: product_id, variation_id, expected, counted, discrepancy
inventory_events         — история: продажба, доставка, корекция от броене
```

### Модификации на съществуващи таблици:
```
inventory                — +quantity_verified, +last_verified_at, +is_counted(bool)
products                 — +confidence_score, +last_counted_at, +counted_via
                           БЕЗ zone_id (НИКОГА)
```

### Модул: inventory.php (отделен файл)
products.php има бутон "Инвентаризация" → навигира към inventory.php

### Споделен код с products.php:
- Вариации UI (preset picker от biz-coefficients)
- Qty steppers (+/− по вариация)
- Извличат се в общи JS функции

### Зони = навигация, не истина:
- zone_stock е SNAPSHOT от последно броене, не real-time
- При продажба се пипа САМО inventory.quantity
- zone_stock се обновява САМО при броене

### State machine на сесия:
```
DRAFT → IN_PROGRESS → PAUSED → IN_PROGRESS → COMPLETED
Ако paused >24ч + движения → STALE → resume с warning
```

### Sale allocation: НЕ за V1
При продажба намаляваме inventory.quantity. Не пипаме zone_stock. Не гадаем от коя зона е продадено.

### UNASSIGNED зона: При доставка
Доставка → бройките влизат в inventory.quantity. Пешо казва къде ги слага → zone_stock се обновява. Ако "после" → остават "неразпределени" (zone_stock не се пипа).

---

## 19. ПРИОРИТЕТ НА ИМПЛЕМЕНТАЦИЯ

### V1 (Бета фаза B, S60-S65):
- SQL миграция (нови таблици + колони)
- inventory.php: онбординг + места CRUD + броене + review
- Бързо добавяне от инвентаризация (споделен код с products.php)
- Batch "провери другаде"
- Кашон режим
- Самокорекция от продажби
- Crash recovery (auto-save)
- Offline mode (локален storage + sync)
- Трансфери между магазини (базов)

### V1.1 (след 2 седмици реална употреба):
- Мини-проверки (ежедневни)
- Store Health визуализация
- Ескалация за доставни цени
- Причина за разлика (analytics)
- Дефектна стока модул

### V2:
- Комплекти/пакети (bundle логика)
- Разширен offline mode
- AI предложения за оптимизация на подредба

---

## 20. ЖЕЛЕЗНИ ПРАВИЛА

1. **Продажбата НИКОГА не чака** — нищо от инвентаризацията не блокира касата
2. **Артикулът е единицата** — не зоната
3. **Артикулът се приключва навсякъде наведнъж** — batch, не артикул по артикул
4. **inventory.quantity е оперативната истина** — zone_stock е snapshot
5. **Пешо не пише** — глас, скенер, тап
6. **Бързо добавяне = 4 стъпки** — после products.php допълва
7. **Етикетиране по време на броене** — едно действие = броене + етикетиране
8. **Offline е задължителен** — склад = лоша мрежа
9. **Никога не казваме "инвентаризация"** — казваме "скрити пари"
10. **Непреброен артикул при доставка = шанс да броим** — не пропускаме
11. **100% = 3-4 месеца без аномалии + 20 случайни ОК**
12. **Инвентаризацията никога не свършва** — само остарява