# 📋 ДОКЛАД ЗА НЕСЪОТВЕТСТВИЯ В ДОКУМЕНТАЦИЯТА

**Подател:** Тихол (чрез Шеф-чат research session)
**Получател:** Шеф-чат / Architecture lead
**Дата на анализа:** 04.05.2026
**Source of truth използван:** GitHub `tiholenev-tech/runmystore` (актуални файлове) + Project Knowledge (52 файла) + userMemories
**Цел:** Списък с всички несъответствия + предложени мерки. **БЕЗ автоматични корекции** — всичко чака твоето одобрение.

---

## 🎯 ИЗПЪЛНИТЕЛНО РЕЗЮМЕ

В Project Knowledge има **52 документа**, около **1.4 MB** обща документация. От тях:

- **14 актуални** (последни версии)
- **13 определено остарели** (~470 KB) — counter-source-of-truth, могат да объркат нов чат
- **6 частично остарели** (~270 KB) — съдържат и актуални данни, но имат counter-info
- **17 transitional** (~115 KB) — изпълнили предназначение (input за консолидация)

**11 ключови файла в GitHub НЕ са качени в Project Knowledge** — нов чат не може да ги намери.

**Правилото което ползвам в този доклад:** последна дата печели. Когато документ A (по-стар) и документ B (по-нов) си противоречат — B е истината.

---

## 📊 ТАБЛИЦА НА КЛЮЧОВИТЕ КОНФЛИКТИ

| # | Тема | Стар вариант (отхвърлен) | Нов вариант (актуален) | Разлика | Източник на новия |
|---|------|--------------------------|------------------------|---------|--------------------|
| 1 | **Цени** | СТАРТ €49 / РАСТЕЖ €85 / ПРО €125 | FREE €0 / СТАРТ €19 / PRO €59 / БИЗНЕС €109 | +1 нов план, всички цени надолу | DOCUMENT_1_LOGIC_PART_2 (18.04) > AI-Sklad-FINALEN (старо) |
| 2 | **Online store партньор** | Shopify | Ecwid by Lightspeed | Цяла интеграция различна | Marketing Bible v1.0 (03.05) |
| 3 | **Маркетинг модул фаза** | Phase 6/7 (2028) | Phase 1-5 (Q4 2026 – Q2 2027) | Изместване ~1 година напред | userMemories + Marketing Bible (03.05) |
| 4 | **Inventory концепция** | Zone Walk (зона = единица) | Article (артикул = единица) | Различна архитектура | INVENTORY_v4 (13.04) изрично "заменя v3" |
| 5 | **Брой AI теми** | 649 теми | ~857 теми (S51) → регрупирани в 6 фундаментални въпроса (S77) | Двойна еволюция | S51 + Appendix v3.1 (19.04) |
| 6 | **Roadmap фази** | Фаза 1-6 (Основни/AI/Multi-store/Beta/SaaS/Scale) | Phase A1 / A2 / A3 / D | Reorganize 25.04 | MASTER_COMPASS_UPDATE (01.05) |
| 7 | **Текуща сесия** | S78-S87 план | S95 в ход | +17 сесии напред | MASTER_COMPASS + PRIORITY_TODAY (04.05) |
| 8 | **Architecture S77 правила** | Pre-S77 архитектура | 6-те фундаментални въпроса + warehouse hub + orders ecosystem | Структурна промяна | BIBLE_v3_0_APPENDIX v3.1 (19.04) |
| 9 | **Promotions modul** | Pre-promo brainstorm | 5 AI consensus финализиран — stacking rules, returns, Н-18 compliance | Promo spec locked | MASTER_COMPASS_UPDATE (25.04) |
| 10 | **Schema** | Pre-marketing schema | +25 нови `mkt_*` и `online_*` таблици + 9 ALTER-а pending | Migration преди beta | userMemories + Marketing Bible Technical |

---

## 🔴 КАТЕГОРИЯ A — ОПРЕДЕЛЕНО ОСТАРЕЛИ (13 файла)

### A1. `AI-Sklad-FINALEN-ARHIV-v2.md` (19 KB)

**Конкретни остарели данни:**
- Таблица с цени: СТАРТ €49 / РАСТЕЖ €85 / ПРО €125
- "Онлайн магазин Shopify" — във всички фази
- "5% комисионна на онлайн продажба" — pricing model премахнат

**Кой го опровергава:** `BUSINESS_STRATEGY_v2.md` (11.04) + DOCUMENT_1_LOGIC_PART_2 (18.04) с нови цени.

**Препоръчано действие:** Архивирай в `_archive/2026-04-pre-bible-v3/`. Маркирай header-а с `⚠️ DEPRECATED — see BIBLE v3.1`.

---

### A2. `INVENTORY_HIDDEN_v3.md` (23 KB)

**Конкретни остарели данни:**
- На английски (целият проект е на BG)
- Концепция "Zone Walk" — броиш зони, не артикули
- Pre-S60 (S60 е 13.04)

**Кой го опровергава:** `INVENTORY_v4.md` (13.04) — изрично казва в header-а: *"Заменя: INVENTORY_HIDDEN_v3.md"*. Owner quote: *"Стоката се движи. Броя рафта, след 10 минути вече съм взел 3 неща и съм ги сложил другаде."*

**Препоръчано действие:** Архивирай. v4 е финалната версия.

---

### A3. `TECHNICAL_ARCHITECTURE_v1.md` (62 KB)

**Конкретни остарели данни:**
- Сесия 42 (09.04)
- 649 AI теми (вече 857 → 6 фундаментални въпроса)
- Цена €49 ("Пешо плаща €49 да не спре да вижда")
- Pre-S77 архитектура — няма 6-те фундаментални въпроса, няма warehouse hub, няма orders ecosystem
- Header explicit: *"Версия 1.0 — НЕ ПОДЛЕЖИ НА ПРОМЯНА БЕЗ ИЗРИЧНО РЕШЕНИЕ"* — но междувременно е заменен 3 пъти

**Кой го опровергава:** BIBLE_v3_0_TECH.md + BIBLE_v3_0_APPENDIX.md v3.1 (19.04).

**Препоръчано действие:** Архивирай. Не може да съществува паралелно с BIBLE — два tech sources of truth = объркване.

---

### A4. `TECHNICAL_REFERENCE_v1.md` (27 KB)

**Конкретни остарели данни:**
- Header самоописва: *"MERGE на AI_BRAIN v6.0 + INVENTORY_HIDDEN v3 + TECHNICAL_ARCHITECTURE v1 + S50 решения"*
- 3 от 4-те източника са вече остарели (INVENTORY_HIDDEN v3, TECH_ARCH v1, и AI_BRAIN v6.0 не съществува отделно)
- Реферира `BIBLE_v1_1.md` — реалната BIBLE сега е v3.1

**Кой го опровергава:** Самият header — реферира към несъществуващи или остарели файлове.

**Препоръчано действие:** Архивирай. Зависи от Tier 1 файлове които вече са остарели.

---

### A5. `ROADMAP.md` (8 KB) — Project Knowledge версия

**Конкретни остарели данни:**
- "Версия: 1.1 (19.04.2026)"
- "S78-S87 план" като текущи следващи 10 сесии
- Фаза 1-6 структура (Основни / AI мозък / Multi-store / Beta / SaaS / Scale)

**Кой го опровергава:** MASTER_COMPASS_UPDATE.md (01.05): *"ROADMAP REVISION (25.04.2026): Pull-up promotions/transfers/deliveries/scan-document от Phase D към Phase A2/A3."* + текуща сесия е S95.

**Препоръчано действие:** **GitHub `docs/ROADMAP.md` също е остарял** — пиши нов roadmap отразяващ Phase A1/A2/A3/D + текущ статус (Phase A1 ~75%, beta 14-15.05).

---

### A6. `SESSION_48_HANDOFF.md` (27 KB)

**Защо остарял:** 10.04.2026 handoff. Текущата сесия е S95. Handoff-овете са еднократни за следващата сесия — не са вечен референс.

**Препоръчано действие:** Архивирай. Запази като audit trail но не като активен документ.

---

### A7. `CONSOLIDATION_HANDOFF.md` (26 KB)

**Защо остарял:** 18.04 handoff с инструкция: *"Обедини в DOCUMENT_1_LOGIC.md + DOCUMENT_2_TECHNICAL.md + NARACHNIK_TIHOL_v1_3.md"*. Задачата е изпълнена — `DOCUMENT_1_LOGIC_PART_1/2/3_ONLY.md` съществуват.

**Препоръчано действие:** Архивирай. Mission accomplished.

---

### A8. `STARTUP_PROMPT.md` (3 KB) — **КРИТИЧНО СЧУПЕН**

**Конкретни остарели данни:**
URL: `https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/docs/compass/MASTER_COMPASS.md`

Тестван: **HTTP 404** (файл не съществува на тази позиция).

Реалното място: `https://github.com/tiholenev-tech/runmystore/blob/main/MASTER_COMPASS.md` (root, не `/docs/compass/`).

**Импакт:** Всеки нов чат който следва STARTUP_PROMPT не може да зареди MASTER_COMPASS → стартира без context.

**Препоръчано действие:** **СПЕШНА ПОПРАВКА** — обнови URL-а или премести MASTER_COMPASS.md в `/docs/compass/`.

---

### A9. `DESIGN_SYSTEM_v1.md` (17 KB)

**Защо остарял:** "27.04.2026 след одобрението на S82.STUDIO mockups". Заместен на 28.04 с `DESIGN_SYSTEM_v3_COMPLETE.md` който изрично се обявява за *"ЕТАЛОН за целия проект"*.

**Където v3 има по-стрикни правила:** v1 говори за "GLASS система", v3 говори за "Neon Glass · Premium Dark" с точна спецификация на shine/glow/conic-gradient.

**Препоръчано действие:** Архивирай v1.

---

### A10. `AI_CONVERSATION_FLOW_TOPICS_v1.md` (160 KB)

**Защо остарял:**
- Сесия 38 (09.04)
- 500 AI теми с философия "помисли за зареждане" vs "поръчай"
- Pre-S77 — няма 6-те фундаментални въпроса
- Темите не са регрупирани по новата структура

**Кой го опровергава:** BIBLE_v3_0_APPENDIX.md v3.1 (19.04) — *"6-те фундаментални въпроса (S77 ЗАКОН)"*.

**Препоръчано действие:** Архивирай. Темите още имат стойност, но са в стара групировка.

---

### A11. `S51_AI_TOPICS_MASTER.md` (14 KB) + A12. `topics-verification-v4.md` (59 KB) + A13. `ai-topics-catalog.json` (150 KB)

**Защо остарели (заедно):**
- 857 теми в 35 категории (S51, 12.04)
- Регрупирането от S77 (19.04) ги разпределя в **6 фундаментални въпроса** + module assignments

**Конкретен пример от каталога:** `tax_001` "VAT threshold approaching" — категория `tax`, module `home`. Нямаме mapping към "Какво губя/Какво печеля/Какво да поръчам/Какво да НЕ поръчам/От какво губя/От какво печеля".

**Препоръчано действие:**
- **JSON да остане** като raw data feed (cron го обхожда)
- `S51_AI_TOPICS_MASTER.md` + `topics-verification-v4.md` → архивирай. Те описват старата групировка.
- Нужна е **нова версия** на каталога с `fundamental_question` колона (memory-то споменава: *"compute-insights.php — 30 функции + fundamental_question"* в S79 план)

---

## 🟡 КАТЕГОРИЯ B — ЧАСТИЧНО ОСТАРЕЛИ (6 файла)

Тези съдържат и актуални неща, и counter-info. Решение — поправяй или архивирай.

### B1. `RUNMYSTORE_PARTNER_PRESENTATION.md` (18 KB)

**Актуално:**
- Цени €19/€59/€109 ✅
- Партньорски модел (100% за месеци 2-4) ✅
- 5-те валидационни етапа ✅

**Остаряло:**
- "Фаза 7: Маркетинг модул" — реално Phase 1-5
- "Фаза 6: Онлайн магазин (Shopify)" — реално Ecwid

**Препоръка:** Update phase numbers + замени Shopify → Ecwid. Иначе е добра презентация — не я архивирай.

---

### B2. DOCUMENT_1_LOGIC_PART_1/2/3_ONLY.md (общо 178 KB)

**Актуално:**
- 4 плана FREE/START/PRO/BUSINESS с правилните цени ✅
- ISR партньорски модел ✅
- 5-те непроменими закона ✅
- 6-те фундаментални въпроса (Life Board) ✅

**Остаряло:**
- Споменава Shopify в Phase 6 (трябва Ecwid)
- "Translation pipeline (Phase 6+)" — трябва Phase 1-5
- Phased Rollout Phase 1-5 vs реалното A1/A2/A3/D
- Маркетинг модул като Phase 7 (трябва Phase 1-5)
- Header съветва за `OPERATING_MANUAL.md` и `NARACHNIK_TIHOL_v1_3.md` — не съм сигурен дали тези съществуват в актуална версия

**Препоръка:** Тези документи са много по-добри от старите — направи **3 surgical edit-а** (Shopify→Ecwid, Phase 6/7→Phase 1-5, A1/A2/A3/D mapping). Не ги архивирай — те са мост между старата и новата ера.

---

### B3. `PARTNERSHIP_SCALING_MODEL_v1.md` (39 KB)

**Актуално:**
- Head of Country / Regional / Sub-Affiliate структура ✅
- Stripe Connect integration ✅
- 5 валидационни етапа ✅
- Цени €19/€59/€109 ✅

**Остаряло:**
- Реферира Shopify в Phase 5+ глобализация

**Препоръка:** 1-2 surgical edit-а за Shopify→Ecwid. Иначе остави.

---

### B4. `RUNMYSTORE_AI_BRIEF-1.md` (13 KB)

**Защо частично:**
- 16.04 — pre-S77
- Описва AI архитектурата преди 6-те фундаментални въпроса
- НО — съдържа добра background информация за консултация с външни AI модели

**Препоръка:** Маркирай като "**Архивен брийф — pre-S77 architecture, used as input for 5 AI consultation, Apr 16**". Не източник на истина, а исторически документ.

---

### B5. `WEATHER_INTEGRATION_v1.md` (11 KB)

**Защо частично:**
- S53 (12.04) — концепцията остава валидна
- Стара цена €49 "AI vs служител"
- Концепцията е интегрирана в BIBLE → дублиране

**Препоръка:** Ако weather интеграцията е в BIBLE — архивирай. Ако не е — extract концепцията в нов file без старите цени.

---

### B6. `стратегия_за_скалира_е.docx` (13 KB)

**Защо частично:** Оригинал от автор. Заместен от `PARTNERSHIP_SCALING_MODEL_v1.md` (по-пълен).

**Препоръка:** Архивирай. v1.md е superset.

---

## 🟢 КАТЕГОРИЯ C — TRANSITIONAL (17 файла, ~115 KB)

Файловете `01_mission_and_philosophy.md` → `17_final_verification.md`.

**Контекст:** В CONSOLIDATION_HANDOFF е инструкцията *"обедини в DOCUMENT_1"*. В `17_final_verification.md` Claude казва: *"BIBLE v3.0 ВЕЧЕ Е ОБНОВЕНА"* + изброява 10 update-а нанесени в BIBLE_v3_0_*.

**Заключение:** Тези 17 файла са били **input** за консолидация. Output (BIBLE v3.1 + DOCUMENT_1) е създаден. Файловете са изпълнили предназначението си.

**Препоръка за всички 17:**
- Архивирай в `_archive/2026-04-life-board-design-sessions/`
- Запази като audit trail на 132-та архитектурни решения от Life Board дизайн сесиите 17-18.04
- Премахни от активното Project Knowledge за да не се четат от нови чатове

---

## 🟦 КАТЕГОРИЯ D — АКТУАЛНИ (14 файла) — ЗАПАЗИ

| Файл | Версия | Статус |
|---|---|---|
| `BIBLE_v3_0_APPENDIX.md` | v3.1 (19.04) | ✅ Active |
| `BUSINESS_STRATEGY_v2.md` | v2 (11.04) | ✅ Active (cherry-pick — само по-старите цени са rejected, новите са в DOC_1) |
| `DESIGN_SYSTEM_v3_COMPLETE.md` | v3 (28.04) | ✅ ЕТАЛОН |
| `INVENTORY_v4.md` | v4 (13.04, S60) | ✅ Active |
| `LOYALTY_BIBLE.md` | v1.0 (27.04) | ✅ Active (отделен подпроект `loyalty.donela.bg`) |
| `ORDERS_DESIGN_LOGIC.md` | v1.0 (19.04) | ✅ S83-S85 reference |
| `PRODUCTS_DESIGN_LOGIC.md` | v1.0 (19.04) | ✅ S78-S82 reference (текущата работа) |
| `STRIPE_CONNECT_AUTOMATION.md` | v1.0 (17.04) | ✅ Future reference |
| `GEMINI_SEASONALITY.md` | data feed | ✅ Reference data |
| `BOOT_TEST_FOR_SHEF.md` | helper | ✅ Tool |
| `SHEF_RESTORE_PROMPT.md` | helper | ✅ Tool |
| `biz-compositions.php` | data (S47) | ✅ Static |
| `build-prompt-integration.php` | code snippet | ✅ Active |
| HTML mockups (3 бр.) | visual references | ✅ Reference |

---

## 🚨 КАТЕГОРИЯ E — ЛИПСВАЩИ В PROJECT KNOWLEDGE (11 файла)

Тези съществуват в GitHub `tiholenev-tech/runmystore` като **active source of truth**, но не са качени в Project Knowledge. Нов чат не може да ги намери.

| Файл в GitHub | Дата | Защо е критичен |
|---|---|---|
| `MASTER_COMPASS.md` (root) | 27.04 | Главен orchestrator на проекта — всеки нов чат го чете |
| `MASTER_COMPASS_UPDATE.md` | 01.05 EOD | Latest changes log |
| `PRIORITY_TODAY.md` | 04.05 | Текущи приоритети — 10 дни до beta |
| `SIMPLE_MODE_BIBLE.md` | v1.3 | Simple Mode (Пешо) полна спецификация |
| `BIBLE_v3_0_CORE.md` (`/docs/`) | v3.x | 3,451 реда — основна Bible |
| `BIBLE_v3_0_TECH.md` (`/docs/`) | v3.x | 5,438 реда — техническа Bible |
| `MARKETING_BIBLE_LOGIC_v1.md` (`/docs/marketing/`) | 03.05 | Маркетинг стратегия |
| `MARKETING_BIBLE_TECHNICAL_v1.md` (`/docs/marketing/`) | 03.05 | DB schema + API за маркетинг |
| `DELIVERY_ORDERS_DECISIONS_FINAL.md` | recent | Доставки финални решения |
| `AUTO_PRICING_DESIGN_LOGIC.md` | recent | Auto pricing |
| `AI_STUDIO_LOGIC.md` | recent | AI Studio specification |

**Препоръка:** Качи всички 11 в Project Knowledge. Без тях нов чат стартира със стара информация (от Project Knowledge) и не знае за реалната ситуация.

---

## ⚙️ КАТЕГОРИЯ F — userMemories АКТУАЛИЗАЦИИ

Memory-то е добро, но има 1 missing piece:

**Memory сега казва:** *"СТАРТ €19 / PRO €59 / БИЗНЕС €109"* — но **FREE €0/мес** липсва.

**Реалност според DOCUMENT_1_LOGIC_PART_2 (18.04):**
- FREE €0/мес — лоялна програма + етикети + 50 артикула, БЕЗ AI съвети, ЗАВИНАГИ
- START €19/мес — неограничени артикули, AI = джаджа
- PRO €49 (не €59) /мес — AI advice + actions
- BUSINESS €109 (multi-store)

**Memory има €59 за PRO. DOCUMENT_1 има €49.** Кой е верен? — последната дата в DOCUMENT_1 е 18.04. Memory cite-ва BUSINESS_STRATEGY_v2 (11.04). По правилото "последна дата печели" → **PRO е €49, не €59**.

**Препоръчано действие:**
1. Update memory: добави FREE €0 plan
2. Update memory: PRO е €49 (не €59)
3. Verify с Тихол дали е €49 или €59 (ако е €59, тогава DOCUMENT_1 трябва да се ъпдейтне)

---

## 📦 ПРЕДЛОЖЕНА СТРУКТУРА СЛЕД РЕОРГАНИЗАЦИЯ

```
Project Knowledge/
│
├─ ACTIVE/                                  ← четат се от всеки нов чат
│  ├─ MASTER_COMPASS.md                     [ADD from GitHub]
│  ├─ MASTER_COMPASS_UPDATE.md              [ADD from GitHub]
│  ├─ PRIORITY_TODAY.md                     [ADD from GitHub]
│  ├─ BIBLE_v3_0_CORE.md                    [ADD from GitHub]
│  ├─ BIBLE_v3_0_TECH.md                    [ADD from GitHub]
│  ├─ BIBLE_v3_0_APPENDIX.md                [keep — already v3.1]
│  ├─ SIMPLE_MODE_BIBLE.md                  [ADD from GitHub]
│  ├─ DESIGN_SYSTEM_v3_COMPLETE.md          [keep]
│  ├─ INVENTORY_v4.md                       [keep]
│  ├─ PRODUCTS_DESIGN_LOGIC.md              [keep]
│  ├─ ORDERS_DESIGN_LOGIC.md                [keep]
│  ├─ DELIVERY_ORDERS_DECISIONS_FINAL.md    [ADD from GitHub]
│  ├─ AUTO_PRICING_DESIGN_LOGIC.md          [ADD from GitHub]
│  ├─ AI_STUDIO_LOGIC.md                    [ADD from GitHub]
│  ├─ MARKETING_BIBLE_LOGIC_v1.md           [ADD from GitHub]
│  ├─ MARKETING_BIBLE_TECHNICAL_v1.md       [ADD from GitHub]
│  ├─ STRIPE_CONNECT_AUTOMATION.md          [keep]
│  ├─ LOYALTY_BIBLE.md                      [keep]
│  ├─ GEMINI_SEASONALITY.md                 [keep]
│  ├─ BUSINESS_STRATEGY_v2.md               [keep]
│  ├─ PARTNERSHIP_SCALING_MODEL_v1.md       [keep, edit Shopify→Ecwid]
│  ├─ DOCUMENT_1_LOGIC_PART_1_ONLY.md       [keep, 3 surgical edits]
│  ├─ DOCUMENT_1_LOGIC_PART_2_ONLY.md       [keep]
│  ├─ DOCUMENT_1_LOGIC_PART_3_ONLY.md       [keep]
│  ├─ RUNMYSTORE_PARTNER_PRESENTATION.md    [keep, 2 edits]
│  ├─ ai-topics-catalog.json                [keep — но нужна v2 с fundamental_question]
│  └─ helpers/                              ← инструменти
│     ├─ STARTUP_PROMPT.md                  [FIX URL]
│     ├─ BOOT_TEST_FOR_SHEF.md              [keep]
│     ├─ SHEF_RESTORE_PROMPT.md             [keep]
│     ├─ biz-compositions.php               [keep]
│     └─ build-prompt-integration.php       [keep]
│
├─ ARCHIVE/                                 ← запазено, но не четено
│  ├─ 2026-04-pre-bible-v3/
│  │  ├─ AI-Sklad-FINALEN-ARHIV-v2.md
│  │  ├─ INVENTORY_HIDDEN_v3.md
│  │  ├─ TECHNICAL_ARCHITECTURE_v1.md
│  │  ├─ TECHNICAL_REFERENCE_v1.md
│  │  ├─ DESIGN_SYSTEM_v1.md
│  │  ├─ AI_CONVERSATION_FLOW_TOPICS_v1.md
│  │  ├─ S51_AI_TOPICS_MASTER.md
│  │  ├─ topics-verification-v4.md
│  │  ├─ RUNMYSTORE_AI_BRIEF-1.md
│  │  ├─ WEATHER_INTEGRATION_v1.md
│  │  └─ стратегия_за_скалира_е.docx
│  │
│  ├─ 2026-04-life-board-design-sessions/   ← 17 transitional файла
│  │  ├─ 01_mission_and_philosophy.md
│  │  ├─ 02_daily_rhythm.md
│  │  ├─ ...
│  │  └─ 17_final_verification.md
│  │
│  ├─ 2026-04-handoffs/
│  │  ├─ SESSION_48_HANDOFF.md
│  │  ├─ CONSOLIDATION_HANDOFF.md
│  │  └─ ROADMAP.md (стара версия)
│  │
│  └─ INDEX.md                              ← Какво има в архива и защо
│
└─ INCONSISTENCIES_REPORT_v1.md             ← този файл (audit trail)
```

---

## 🎬 ПРИОРИТИЗИРАНИ ДЕЙСТВИЯ (за шеф-чат)

### P0 — СПЕШНО (блокери преди beta 14-15.05):

1. **Поправи STARTUP_PROMPT.md URL** (HTTP 404) → нови чатове не зареждат context
2. **Качи 11-те GitHub файла в Project Knowledge** (MASTER_COMPASS, BIBLE v3 CORE/TECH, Marketing Bibles, etc.)
3. **Verify FREE plan + PRO €49 vs €59** с Тихол → update memory

### P1 — ВАЖНО (преди следваща голяма сесия):

4. **Архивирай 13-те Category A файла** в `_archive/2026-04-pre-bible-v3/`
5. **Архивирай 17-те Life Board файла** в `_archive/2026-04-life-board-design-sessions/`
6. **Surgical edits на DOCUMENT_1** (Shopify→Ecwid, Phase 6/7→Phase 1-5)
7. **Surgical edits на PARTNER_PRESENTATION** (Phase numbers, Ecwid)
8. **Update GitHub `docs/ROADMAP.md`** с Phase A1/A2/A3/D + текущ S95 статус

### P2 — ЖЕЛАТЕЛНО (пост-beta):

9. **Нова v2 на ai-topics-catalog.json** с `fundamental_question` колона (S79 deliverable)
10. **Създай ARCHIVE/INDEX.md** обясняващ какво има в архива и защо
11. **Quarterly audit** — на всеки 3 месеца този анализ да се повтаря

---

## 📈 ВЪЗМОЖНИ ПРИЧИНИ ЗА БРОЯ ОСТАРЕЛИ ФАЙЛОВЕ

1. **Бърза еволюция** — проектът се развива агресивно (S78 → S95 за 2 седмици)
2. **Project Knowledge не auto-syncs с GitHub** — manual upload pattern означава lag
3. **Няма "старо/ново" tag система** — нов чат не знае кое е current source of truth
4. **17-те transitional файла** показват healthy "design sprint → consolidate" pattern, но output не е почистен

---

## 🤝 ЗАКЛЮЧИТЕЛНА ПРЕПОРЪКА

Документацията е **здрава по съдържание** — проблемът е **навигационен**, не съдържателен. Новите документи са много по-добри от старите. Просто старите още висят и могат да объркат нов чат.

**1 час работа** на шеф-чат + Тихол = всичко почистено и нов чат стартира с ясна Source of Truth.

---

**END OF REPORT**

*Генерирано от: research session, Шеф-чат research mode*
*Без автоматични корекции — само анализ. Действията чакат одобрение от Тихол + шеф-чат.*
