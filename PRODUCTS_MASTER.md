# 📦 PRODUCTS_MASTER.md — единен документ за `products.php`

**Версия:** v1.0 (DRAFT — в крачка)
**Създаден:** 2026-05-12 (шеф-чат Opus 4.7)
**Цел:** ЕДИН source of truth за всичко свързано с `products.php` — за да не се търси нищо в други 10 документа. Документът се променя в крачка, всяка промяна = нов commit с описание.

**Източници които обединява:**
- `HANDOFF_FINAL_BETA.md` (08.05.2026)
- `PRODUCTS_BULK_ENTRY_LOGIC.md` (35KB железна спец за P13 wizard)
- `docs/PRODUCTS_DESIGN_LOGIC.md` (57KB)
- `docs/PROMPT_TOMORROW_S99_VOICE.md` (voice spec)
- `AI_STUDIO_LOGIC_DELTA.md` (AI Studio integration)
- `SIMPLE_MODE_BIBLE.md` (simple mode философия)
- `INVENTORY_HIDDEN_v3.md` (confidence_score logic)
- `PREBETA_MASTER_v2.md` (задачи за products)
- `docs/S140_FINALIZATION.md` (Universal UI Laws §2)
- `services/voice-tier2.php` + `ai-color-detect.php` (sacred code)
- Самият `products.php` (14,074 реда, inspection)

---

# 1. ЦЕЛ И ОБХВАТ НА МОДУЛА

`products.php` е **най-големият модул в системата** (14,074 реда, 10× по-голям от `chat.php`). Той е централната работна среда за всичко свързано с артикули.

## 1.1 Двойна роля

Модулът обслужва **две различни персони** едновременно:

| Персона | Mode | Какво вижда | Цел |
|---|---|---|---|
| 👴 **Пешо** (seller) | `mode-simple` | "Стоката ми" home — тревоги + AI сигнали + бърз вход към wizard | Бързо продаване и добавяне без обучение |
| 👨‍💼 **Митко** (owner/manager) | `mode-detailed` | "Артикули" home + списък + статистики + 6-те фундаментални въпроса | Управление, анализ, поръчки |

Разделянето е по `$user_role` в `$_SESSION['role']` (default `seller`). Текущият products.php ред 4286:
```php
<body class="has-rms-shell mode-<?= ($user_role === 'seller') ? 'simple' : 'detailed' ?>">
```

## 1.2 Какво НЕ е products.php

- **НЕ** е "складова страница" в стария смисъл (т.е. не е плосък списък с CRUD)
- **НЕ** замества `life-board.php` (която е простият home екран на Пешо извън products модула)
- **НЕ** е касова система (това е `sale.php`)
- **НЕ** е поръчки/доставки (това са `orders.php` + `deliveries.php`)

## 1.3 Какво обединява products.php

| Подмодул | Описание | Размер в products.php |
|---|---|---|
| **Home view** | Тревоги, AI сигнали, бърз вход (P15 simple, P2 detailed) | scrHome (ред 4321-4635) |
| **List view** | Списък артикули с филтри, sorts, variations (P3) | scrProducts (4650-4694) |
| **Wizard "Добави артикул"** | Accordion с 5 sections, voice, photo AI, matrix (P13) | ~7800-12900 (~5000 реда) |
| **Matrix overlay** | Fullscreen вариации grid (P12) | overlay (нов в S141) |
| **AI Studio modal** | Photo retouching, color override (P8 семейство) | integration call |
| **Suppliers** | Списък доставчици | scrSuppliers (4638-4641) |
| **Categories** | Списък категории | scrCategories (4644-4647) |
| **Inventory & confidence** | Hidden inventory, Store Health Score | вградена логика |
| **Етикет печат** | DTM-5811 Bluetooth (TSPL) + browser fallback | window.print() + BLE |

---

# 2. 🔒 SACRED — НЕ пипай (1000%)

Тези елементи НИКОГА не се променят. Източник: `HANDOFF_FINAL_BETA.md` §2 ред 97 — *"Voice integration LOCKED commits `4222a66` + `1b80106` — НЕ РУШИ. Voice parser `_wizPriceParse` остава непроменен."* + Тиховата директива на 12.05.2026.

## 2.1 Voice — БГ числа разпознаване

Voice работи в hybrid режим: **Whisper Tier 2 (Groq) primary** → **Web Speech bg-BG fallback** → **toast error**.

### Sacred files (НЕ пипай съдържание)

| Файл | Размер | Какво прави |
|---|---|---|
| `services/voice-tier2.php` | 333 реда | Whisper Groq API client, БГ синоним normalization |
| `ai-color-detect.php` | 296 реда | Gemini Vision color detection (single + multi-image) |
| `tools/voice-lab.php` | 629 реда | Voice testing tool (admin) |
| `voice-tier2-test.php` | (admin) | Voice test endpoint |

### Sacred functions в products.php (НЕ пипай)

| Функция | Ред | Роля |
|---|---|---|
| `wizMic(field)` | 12900-12931 | Voice entry router (избира Whisper или Web Speech) |
| `_wizMicWhisper(field, lang)` | 12905 | Whisper Tier 2 call |
| `_wizMicWebSpeech(field, lang)` | 12910 | Web Speech bg-BG fallback |
| `_wizMicApply(field, transcript)` | (вътре в _wizMicWebSpeech) | Apply parsed value |
| `_wizMicInterim(field, text)` | (continuous transcript display) | Live transcript |
| `_wizPriceParse` / `_bgPrice` | (БГ числа parser) | "пет лева" → 5.00 |

### Sacred locked commits (НЕ revert-вай и НЕ override-вай)

- `4222a66` — voice integration baseline
- `1b80106` — voice integration tier 2

### Sacred wizard mic buttons (НЕ премахвай)

Всеки input във wizard има `wiz-mic` бутон до себе си. Текущите 8 полета:

| Поле | Ред в products.php |
|---|---|
| `name` (име) | 11088 |
| `retail_price` (цена) | 11097 |
| `supplier` (доставчик) | 11109 |
| `category` (категория) | 11120 |
| `code` (код) | 11148 |
| `barcode` (баркод) | 11157 |
| `quantity` (бройка) | 11182 |
| `min_quantity` (мин. бройка) | 11193 |

**Правило:** Ако добавям/местя input, mic бутонът **МИГРИРА с него**. Никога не остава input без mic.

## 2.2 Color detection — Gemini Vision

### Sacred file (НЕ пипай)

`ai-color-detect.php` — 296 реда. Връща JSON: `{ok, colors:[{name:"черен", hex:"#0a0a0a", confidence:0.95}], used, remaining, plan}`. Max 4 цвята, сортирани по доминиране. Цветовете са на български. Single и multi-image (`?multi=1`).

### Sacred call sites в products.php (НЕ пипай)

| Ред | Какво |
|---|---|
| 7958 | `fetch('/ai-color-detect.php', ...)` — single image wizard photo |
| 8286 | `fetch('ai-color-detect.php?multi=1', ...)` — multi-image bulk |

### Confidence threshold (sacred — от HANDOFF Phase E)

- **≥85%** → автоматично присвояване на цвят
- **60-85%** → потвърждение от Пешо
- **<60%** → block, ръчно избиране

## 2.3 Други sacred правила (от DESIGN_SYSTEM_v4.0_BICHROMATIC + HANDOFF §2)

| # | Правило | Защо |
|---|---|---|
| 1 | НИКОГА hardcoded БГ текст — само `t('key')` или `$T['...']` | Multi-language (RO/EL/SR/HR/MK) |
| 2 | НИКОГА hardcoded `BGN/лв/€` — само `priceFormat($amount, $tenant)` | EUR transition + dual pricing до 08.08.2026 |
| 3 | НИКОГА `ADD COLUMN IF NOT EXISTS` (MySQL 8 не поддържа) | Schema migrations |
| 4 | НИКОГА `sed` за file edits — само Python scripts (`/tmp/sXX_*.py`) | Sed не handle-ва multi-line Cyrillic правилно |
| 5 | НИКОГА emoji в UI — само SVG | Consistency, theme support |
| 6 | НИКОГА "Gemini" в UI — само "AI" | Brand neutrality |
| 7 | НИКОГА native клавиатура — custom numpad/voice | Закон №1 (Пешо не пише) |
| 8 | DB field names canonical (виж 2.4) | Избягване на 500 errors |
| 9 | Neon Glass borders са sacred (4 spans + oklch + mix-blend-mode plus-lighter) | Визуална идентичност |
| 10 | PHP смята, AI говори (confidence, margin, totals = PHP) | Закон №2, anti-hallucination |

## 2.4 DB field names canonical (sacred)

| Таблица.поле | Canonical | НЕ ползвай |
|---|---|---|
| `products.code` | ✅ | ~~sku~~ |
| `products.retail_price` | ✅ | ~~sell_price~~, ~~price~~ |
| `products.image_url` | ✅ | ~~image~~ |
| `products.cost_price` | ✅ | ~~buy_price~~ |
| `inventory.quantity` | ✅ | ~~qty~~ |
| `inventory.min_quantity` | ✅ | ~~min_stock~~ |
| `sales.status='canceled'` | ✅ (едно L) | ~~cancelled~~ |
| `sale_items.unit_price` | ✅ | ~~price~~ |

---

# 3. ТЕКУЩ `products.php` — структура

Размер: **14,074 реда** (към 12.05.2026). Файлът съдържа PHP + HTML + CSS + JS в един.

## 3.1 Главни блокове

| Блок | Ред (приблизително) | Размер | Описание |
|---|---|---|---|
| PHP header (session, role, includes) | 1-100 | ~100 | Initialization |
| Insight types map | 100-200 | ~100 | Categorization |
| AJAX endpoints (PHP) | 200-4280 | ~4000 | Server-side APIs (search, save, load, stats, ...) |
| HTML body opening | 4286 | 1 | `<body class="has-rms-shell mode-...">` |
| **scrHome** (active) | **4321-4635** | **~315 реда** | Home view — за seller е simple, за owner е detailed (CSS условно) |
| scrSuppliers | 4638-4641 | ~4 | Suppliers list section |
| scrCategories | 4644-4647 | ~4 | Categories list section |
| scrProducts | 4650-4694 | ~45 | Products list view (P3 target) |
| CSS styles | 4700-5000 | ~300 | Base + overrides |
| **JS functions** | **5000-12900** | **~7900 реда** | Всички client-side функции (wizard, voice, color, search, etc.) |
| Wizard render code | 7800-12200 | ~4400 реда | Wizard accordion HTML generation |
| Voice/mic JS | 12895-12931 | ~36 реда | wizMic + _wizMicWhisper + _wizMicWebSpeech |
| Helpers (esc, fmtPrice, fmtNum, ...) | 4921-4925 | ~5 | Utility functions |

## 3.2 Section markers (вече в кода)

```php
// ред 4286
<body class="has-rms-shell mode-<?= ($user_role === 'seller') ? 'simple' : 'detailed' ?>">

// ред 4321
<section id="scrHome" class="screen-section active">

// ред 4638
<section id="scrSuppliers" class="screen-section">

// ред 4644
<section id="scrCategories" class="screen-section">

// ред 4650
<section id="scrProducts" class="screen-section">
```

## 3.3 Ключови JS функции (top 20)

| Функция | Ред | Роля |
|---|---|---|
| `goScreen(scr, params)` | 5056 | Switch между sections |
| `loadScreen()` | 5084 | Initial load |
| `setHomeTab(t, btn)` | 5118 | Home tab toggle |
| `onLiveSearch(q)` | 5232 | Live search |
| `productCardHTML(p)` | 4974 | Render product card |
| `openManualWizard()` | (wizard entry) | Отваря add wizard |
| `openLikePreviousWizardS88()` | "Като предния" | Bulk continuation |
| `wizMic(field)` | 12900 | Voice entry router |
| `openStoreSwitcher()` | (header) | Магазин switcher |
| `openDrawer('filter')` | (filters) | Filter drawer |
| `searchInlineMic(btn)` | (search) | Inline voice search |
| `showToast(msg, type)` | 4927 | Toast notifications |
| `fmtPrice(v)` | 4922 | Format EUR |
| `fmtNum(v)` | 4923 | Format number |
| `stockClass(q, m)` | 4924 | Stock status class |
| `stockBar(q, m)` | 4925 | Stock bar color |
| `askAIAboutProduct(id, name)` | 5030 | AI Q&A about product |
| `toggleMoreMenu(e, id, name)` | 5039 | More menu toggle |
| `duplicateProduct(id)` | 5052 | Copy product (placeholder) |
| `deactivateProduct(id)` | 5053 | Deactivate (placeholder) |

## 3.4 PHP AJAX endpoints (вътрешни в products.php)

products.php е едновременно view + AJAX endpoint. Detect-ва AJAX request чрез `$_GET['ajax']` или `$_POST['action']`. Endpoints (списък ще се дописва при scan):

- `?ajax=insights` — Life Board сигнали
- `?ajax=storeStats` — Store stats (top_sellers, zombies, low_stock, capital, ...)
- `?ajax=search` — Live search artikuli
- `?ajax=load_products` — Pagination
- (още — за документиране в Section 10 DB schema)

---

# 4. МОКАПИ — 5 canonical файла

Всички в `/var/www/runmystore/mockups/`. Mobile-first (375px viewport).

## 4.1 P15 — `products_simple.html` (10.05.2026)

**Title:** "Стоката ми · RunMyStore.AI"
**Размер:** 1332 реда HTML
**Роля:** НОВ home view за **Пешо** (mode-simple). Entry point на продуктовия модул за seller.
**Target в products.php:** scrHome (ред 4321-4635), CSS conditional за `mode-simple`

### Какво вижда Пешо

1. **Хедър** (Тип Б — вътрешен модул):
   - Brand: "RUNMYSTORE.AI" + PRO badge + ENI tenant
   - Title: "СТОКАТА МИ"
   - Mode toggle: "Разширен" (превключва към P2 detailed)
   - Бутони: Принтер, Настройки, Изход, Theme

2. **Top tревога row (2 карти):**
   - 🔴 **СВЪРШИЛИ:** "5 бр" + "−340 €/седмица" (загубен профит)
   - 🟡 **ЗАСТОЯЛИ 60+ ДНИ:** "2 бр" + "1 180 € замразени" (мъртъв капитал)

3. **Главни действия (2 карти):**
   - **Добави артикул** — "СНИМАЙ · КАЖИ · СКЕНИРАЙ" → отваря P13 wizard
   - **AI поръчка** — "AI подготвя поръчка" → orders flow

4. **Бърз старт card:**
   - "Как работи Стоката ми?"
   - Описание: "Добави артикул със снимка, глас или скенер. AI ще ти каже кога да поръчаш, кога да намалиш и какво търсят клиентите."
   - Видео: "Добави първия артикул · 2 минути"

5. **Попитай AI** (4 чипа):
   - ❓ Какво свърши?
   - ❓ Какво застоява?
   - ❓ Какво да поръчам?
   - ❓ Какво търсят клиентите?
   - "Всички помощни теми"

6. **AI вижда — 6 сигнала · 18:32** (feed):
   - 🟠 LATE: Емпорио ООД — закъсняла 3 дни
   - 🟢 TODAY: ZARA — очаквана днес 14:00 (12 артикула, 1240 €)
     - Action бутони: VIEW / REMIND / RECEIVE_NOW →
   - 🔵 RESOLVED: 12 артикула без снимка (продават 3× по-зле)
   - 🟣 PENDING: 5 поръчки чакат · 4 200 € общо
   - 🟡 CAUSE: Иватекс забавя · 4 дни средно
   - "Виж всичко 8 →"

7. **Bottom sheet (action menu):** "Получи доставка" — 4 опции:
   - 📷 OCR (снимка на фактура) · ~5 sec
   - 🎙️ Voice (кажи) · ~12 sec
   - 📦 Scan (баркод) · ~6 sec
   - 📊 Import (CSV/Excel) · ~10 sec

### Ключови различия спрямо текущ scrHome

| Текущ scrHome | P15 |
|---|---|
| "Артикули · 247" title | "СТОКАТА МИ" + 2 тревоги (свършили + застояли) |
| Search bar (име/код/баркод) | НЯМА search bar на главно ниво |
| "Добави артикул" + "Като предния" | "Добави артикул" + "AI поръчка" (нова концепция) |
| "Здраве на склада" % | НЯМА (заменено с тревоги + AI feed) |
| Магазин switcher | (в хедъра) |

P15 е **тревожно-AI-focused**, не "управленско-търсене-focused".

### Sacred elements от P15 (НЕ опростявай)

- Glass cards с shine + glow + 4 spans (Neon Glass)
- `oklch` light palette
- SVG icons (никога emoji)
- Footer bottom-nav: 4 orbs (AI / Склад / Справки / Продажба) — Тип А style, въпреки че мокапа е module-internal

---

## 4.2 P3 — `list_v2.html` (canonical list view)

**Title:** "Всички артикули · RunMyStore.AI"
**Размер:** 1921 реда HTML
**Роля:** **Списък артикули** (target и за Пешо, и за Митко — universal list)
**Target в products.php:** scrProducts (ред 4650-4694)

### Структура

1. **Хедър:** RunMyStore .ai PRO + ENI + "СПИСЪК" title + Лесен toggle + 4 икони
2. **Title row:** "Всички артикули · 247"
3. **Sort drawer:** Name/Price ASC/Price DESC/Stock ASC/Stock DESC/Newest
4. **Магазин switcher:** ENI · Витоша 25 / Студентски град / Младост / Овча купел / Люлин
5. **Доставчик филтър:** "Спорт Груп · изчерпани" — quick chip
6. **Бързи филтри row:** Цена / Наличност / Марж / Дата
7. **По сигнал филтри (6 chip-а — q1-q6):**
   - 🔴 Губиш (5)
   - 🟣 Причина (3)
   - 🟢 Печелиш (12)
   - 💎 {T_Q4_NAME} (4)
   - 🟡 {T_Q5_NAME} (28)
   - ⚪ {T_Q6_NAME} (9)
8. **Категории chips:** Обувки / Рокли / Тениски / Дънки / Якета / Чанти / Аксесоари
9. **Доставчици chips:** Спорт Груп / Marina / Zara / Мода Дистр. / Lavazza / H&M
10. **Списък артикули** (5 примера):
    - Nike Air Max 42 [ГУБИШ] | NIKE-AM · Спорт Груп | сайз chips: 40,41,42,43,44,+1 | 18 вариации | 120 € | 0 бр
    - Рокля Zara [ГУБИШ] | ZARA-DR · Zara | S,M,L,XL | 12 вариации | 89 € | 2 бр
    - Тениска H&M [ГУБИШ] | HM-TS | S,M,L,XL,+2 | 24 вариации | 24 € | 7 бр
    - Обувки Geox [{T_Q2_TAG}] | GEOX · Мода Дистр. | 37,38,39,40,41 | 10 вариации | 65 € | 12 бр
    - Levi's 501 [{T_Q3_TAG}] | LEVIS-501 · Спорт Груп | W30,W32,W34,W36 | 15 вариации | 180 € | 42 бр
    - "5 / 247 · {T_LOAD_MORE}…"
11. **Variations drawer** (bottom sheet) — отваря се на tap на артикул с вариации:
    - Nike Air Max | NIKE-AM | Спорт Груп · 18 вариации
    - Бутони: PRINT_ALL / EXPORT
    - Stats: 18 variations / 42 total stock / 3 изчерпани
    - {T_ALL_VARIATIONS} grid:
      - Черен · 40 (5 бр)
      - Черен · 41 (8 бр)
      - Черен · 42 (0 бр) ← red
      - Черен · 43 (3 бр)
      - Бял · 40 (2 бр)
      - Бял · 41 (6 бр)
      - Бял · 42 (0 бр) ← red
      - Червен · 41 (4 бр)
      - Червен · 42 (0 бр) ← red
      - Син · 40 (7 бр)
      - Син · 41 (7 бр)
12. **Bottom nav:** AI / Склад / Справки / Продажба

### Ключови концепции

- Tag pill за сигнал (ГУБИШ / Q2 / Q3 / Q4 / Q5 / Q6) — цветово кодиран
- Size chips inline на картата (визуализация на стоковите вариации)
- Variations drawer = bottom sheet с пълен grid (не fullscreen overlay — за това е P12)
- 6 q-filter chips отговарят на 6-те фундаментални въпроса

---

## 4.3 P2 — `home_v2.html` (legacy / detailed home)

**Title:** "Артикули · RunMyStore.AI"
**Размер:** 1729 реда HTML
**Роля:** Home view за **Митко** (mode-detailed) — управление/анализ focus
**Target в products.php:** scrHome (ред 4321-4635), CSS conditional за `mode-detailed`
**Статус:** HANDOFF_FINAL_BETA (08.05) маркира P2 като **"стар home (legacy reference)"**. PREBETA_MASTER_v2 (11.05) обаче го изисква като задача 1.1.2. **Конфликт — Тих ще реши.**

### Структура

1. **Хедър:** RunMyStore .ai PRO + ENI + "СКЛАД" title + Лесен toggle
2. **Title:** "Артикули · 247"
3. **Магазин switcher (5 магазина)**
4. **Add cards row (2):**
   - Добави нов артикул
   - Като предния
5. **Здраве на склада:** "5 изчерпани · 9 застояли · 3 без цена" → **82%**
6. **Доставчици box:** "Виж всички →"
   - Marina (47), Спорт Груп (82), Zara (23), Мода Дистр. (35), Lavazza (12), {T_ALL_SUPPLIERS}
7. **Инвентаризация box:** "34 за броене · Последно: 12 дни · Започни →"
8. **AI вижда — 6 сигнала** (6 expanded q-cards):
   - 🔴 **Губиш 5 · Изчерпани** — "−340 €/седмица"
     - Body: "Nike Air Max 42 · Adidas Stan Smith 38 · Puma RS-X 41 — артикули с продажби, които вече ги няма. Пропуснат profit ~340 €/седмица."
     - Actions: WHY / SHOW / ORDER →
     - Feedback: USEFUL? 👍👎🤔
   - 🟣 **Причина 3 · {T_MARGIN_PROBLEMS}** — "−180 € profit"
     - Body: "Артикули продавани под 15% марж или без записана доставна цена."
     - Actions: WHY / SHOW / FIX →
   - 🟢 **Печелиш 12 · {T_TOP_SELLERS}** — "+2 840 € profit/месец"
     - Actions: SHOW / REORDER →
   - 💎 **{T_Q4_NAME} 4 · {T_REASONS_GROWING}**
     - Actions: SHOW / TELL_ME_MORE
   - 🟡 **{T_Q5_NAME} 28 · {T_PRODUCTS_TO_ORDER}**
     - Actions: SHOW / CREATE_ORDER →
   - ⚪ **{T_Q6_NAME} 9 застояли · 2 480 € {T_FROZEN}**
     - Actions: SHOW / DISCOUNT →
9. **"{T_SEE_ALL_PRODUCTS} 247 →"** button
10. **Bottom nav:** AI / Склад / Справки / Продажба

### Защо HANDOFF го маркира "legacy"

HANDOFF (08.05) изглежда е залагал на P15 (10.05) да замени P2 за simple mode, а detailed mode да остане без специален home (само списък P3). Но PREBETA (11.05) върна P2 като detailed home → не е изхвърлен.

**Решение пред Тих:** P2 е canonical detailed home или само legacy reference?

---

## 4.4 P13 — `bulk_entry.html` (wizard "Добави артикул")

**Title:** "P13 · Добави артикул · RunMyStore.AI"
**Размер:** 1107 реда HTML
**Роля:** Wizard за добавяне на нов артикул (accordion с 5 секции)
**Target в products.php:** ~7800-12900 (около 5000 реда wizard код се замества)
**Spec:** Железна спецификация в `PRODUCTS_BULK_ENTRY_LOGIC.md` (35KB) — детайли в Section 5 на този документ.

### 5 секции (accordion)

| # | Секция | Ред в P13 |
|---|---|---|
| 1 | **МИНИМУМ** (име, цена, бройка, баркод) | 572 |
| 2 | **ВАРИАЦИИ** (size + color matrix → отваря P12 overlay) | 661 |
| 3 | **ДОПЪЛНИТЕЛНИ** (доставчик, категория, cost_price, min_quantity, ...) | 736 |
| 4 | **СНИМКИ** (camera capture + AI color detect + AI label OCR) | 844 |
| 5 | **AI STUDIO** (photo retouching, model wear, background removal) | 904 |

### Ключови concepts (от мокапа)

- Mode toggle "Единичен / Вариации" задължителен без default (Закон от PRODUCTS_BULK_ENTRY_LOGIC)
- Photo AI: до 2 снимки (продукт + фабричен етикет за composition + origin)
- Multi-image color detect: ai-color-detect.php?multi=1
- "Запази · следващ" с dropdown ("Като предния" / "Празно")
- Save per section (incremental) — bulk_session continuation
- Voice (wiz-mic) на всеки input — SACRED, виж §2.1
- Mini print overlay след save: [🖨 ПЕЧАТАЙ ЕТИКЕТ] [✓ ГОТОВО]

### Sister docs за P13

- `PRODUCTS_BULK_ENTRY_LOGIC.md` (35KB) — железна спецификация
- HANDOFF_FINAL_BETA.md §2 — verification gates
- AI_STUDIO_LOGIC_DELTA.md — за Section 5

---

## 4.5 P12 — `matrix.html` (variations overlay)

**Title:** Matrix overlay
**Размер:** 467 реда HTML
**Роля:** Fullscreen matrix за вариации (size × color grid)
**Target в products.php:** Нов overlay (нямa текущ еквивалент)
**Кога се отваря:**
- От P13 Section 2 (ако вариациите станат ≥4×4 cells)
- От P3 variations drawer "Покажи всички" (за preview/redакция на съществуващи)

### Структура (от mockup inspection)

- Fullscreen overlay (top z-index)
- Header: артикул име + back/close button
- Grid: size rows × color columns
- Всяка клетка: количество (editable) + status dot
- Footer: actions (Save / Cancel / Print all)

(Детайлно описание в Section 5 — wizard spec.)

---

## 4.6 Карта на мокапите → products.php секции

```
products.php (14,074 реда)
├── scrHome (4321-4635)
│   ├── mode-simple   → P15 (1332 реда мокап)
│   └── mode-detailed → P2  (1729 реда мокап) [legacy?]
├── scrProducts (4650-4694)
│   └── universal     → P3  (1921 реда мокап)
├── Wizard зона (~7800-12900, ~5000 реда)
│   └── add/edit      → P13 (1107 реда мокап)
└── Matrix overlay (нов в S141)
    └── variations    → P12 (467 реда мокап)
```

**Други мокапи свързани с products.php (не са в основните 5):**
- `ai-studio-main-v2.html` — AI Studio standalone (`ai-studio.php`, не products.php)
- `ai_studio_FINAL_v5.html` + P8 семейство — AI Studio per-product modal (отваря се от P13 Section 5)
- `ai-studio-categories.html` — AI Studio queue overlay
- `P14_deliveries.html`, `P14b_deliveries_detailed_v5_BG.html` — за deliveries.php
- `P10_lesny_mode.html` — life-board.php (не products.php)
- `P11_chat_v7_orbs2.html`, `P11_detailed_mode.html` — chat.php

---

# ⏸ Край на ЧАСТ 1

Това е първите 4 секции (цел, sacred, current state, мокапи). Следва ЧАСТ 2:
- Section 5: Wizard железна спецификация (P13 кондензирано)
- Section 6: Voice integration детайлно

**Total размер досега:** ~25KB | **commit:** `S141: PRODUCTS_MASTER.md ЧАСТ 1 (sections 1-4 — цел + sacred + structure + мокапи)`

---

# 5. WIZARD ЖЕЛЕЗНА СПЕЦИФИКАЦИЯ (P13 → products.php)

**Източник:** `PRODUCTS_BULK_ENTRY_LOGIC.md` (856 реда, кондензирано тук).
**Canonical visual:** `mockups/P13_bulk_entry.html` (1:1 implementation).
**Замества:** legacy wizard P4-P9.

## 5.1 Закони (read first, never forget)

| # | Закон |
|---|---|
| 1 | **P13 mockup е canonical.** Всеки CSS class, SVG, spacing, animation, color → 1:1 в production. Ако трябва отстъпление → STOP и питай Тих. |
| 2 | **0 emoji в UI** (Bible §14). SVG only. |
| 3 | **Никога "Gemini" в UI** — винаги "AI". |
| 4 | **PHP смята, AI вокализира** — confidence, margin, SKU summary, totals в PHP. |
| 5 | **Митко НЕ вижда `confidence_score` число** (Hidden Inventory §5). Само consequences. |
| 6 | **Пешо не пише** — voice бутон на всяко поле. |
| 7 | **Sacred Neon Glass** в dark — 4 spans + oklch + `mix-blend-mode: plus-lighter`. |
| 8 | **i18n всичко** — `t('key')` calls. Никога hardcoded. |
| 9 | **Bulgarian dual pricing** до 08.08.2026 (€ + лв at 1.95583). |

## 5.2 Page structure (top → bottom)

```
products.php → [+ Добави артикул] btn → wizard view (full-screen)
  ├─ Header (sticky 56px) — Title gradient + scan + close
  ├─ Search pill ("Намери артикул да копираме") — collapsed by default
  ├─ Voice command bar — gradient hint card + spinning mic
  ├─ Mode toggle: [Единичен] [С вариации]
  ├─ Section 1: МИНИМУМ (open by default, status="filled")
  ├─ Section 2: ВАРИАЦИИ (hidden if mode=single)
  ├─ Section 3: ДОПЪЛНИТЕЛНИ (closed)
  ├─ Section 4: СНИМКИ (closed)
  ├─ Section 5: AI STUDIO (closed, magic style)
  └─ Bottom bar (fixed): [Undo] [Печат] [CSV] [Запази · следващ ▼]
       └─ Dropdown: "Като предния" / "Празно"

Overlays (z 90-91):
  - AI Studio result sheet (преди/след) — slide up
  - Size groups bottom sheet (други размерни групи)
  - Unit groups bottom sheet (други мерни единици)
  - P12 matrix fullscreen overlay (натиска "Цял екран" в матрица)
```

## 5.3 CSS class names (MANDATORY — не преименувай)

Всички класове са от `P13_bulk_entry.html`. Списък на топ-нивовите:

**Header:** `.bm-header` `.bm-title` `.icon-btn` `.icon-btn.scan-btn`
**Search:** `.find-pill` `.find-panel.show` `.find-input-wrap` `.find-filters` `.find-filter.active` `.find-results` `.find-result`
**Voice bar:** `.voice-bar` `.voice-bar-mic`
**Mode toggle:** `.mode-toggle` (data-mode attr) `.mode-tab.active`
**Accordion:** `.acc-section[data-status="filled|active|empty"]` `.acc-section.magic` `.acc-section.open` `.acc-head` `.acc-chevron`
**Fields:** `.field` `.field-label` `.field-label .req` `.field-label .opt-pill` `.field-label .ditto` `.field-hint`
**Inputs:** `.input-row` `.input-shell` `.input-text` `.fbtn` `.fbtn.cpy` `.fbtn.add` `.fbtn.voice` `.fbtn.scan`
**Prices:** `.price-input-shell` `.price-input` `.price-cur` `.price-bgn`
**Quantity stepper:** `.qty-row` `.qty-stepper` `.qty-stepper.warn` `.qty-step` `.qty-input`
**Margin:** `.margin-row` `.margin-badge` (green, +%)
**Chips:** `.chip-sz` `.chip-sz.active` `.chip-add` `.chip-col` `.chip-col-dot` `.chip-col.active`
**Extra rows:** `.extra-row` `.groups-btn`
**Matrix inline:** `.matrix-head-row` `.matrix-action.expand` `.mx-grid` `.mx-corner` `.mx-head` `.mx-rowh` `.mx-cell` `.mx-cell.has-qty` `.mx-input-qty` `.mx-min-row` `.mx-input-min`
**SKU summary:** `.sku-summary` `.sku-ic` `.sku-text b`
**Save row:** `.save-row` `.save-section-btn` (green gradient + conic shimmer) `.save-aux-btn`
**Photo (Sec 4):** `.photo-bulk-cta` `.photo-bulk-icon` `.photo-bulk-title` `.photo-bulk-sub` `.photo-bulk-actions` `.photo-action-btn.primary` `.photo-result-list` `.photo-result-row` `.photo-result-color` `.photo-result-thumb` `.photo-result-conf` `.photo-result-conf.low` `.photo-result-action.star.is-main`
**AI Studio (Sec 5):** `.ai-credits-strip` `.ai-link-row` `.ai-link-thumb` `.ai-quick-row` `.ai-quick-btn`
**AI Result overlay:** `.ai-result-ov` `.ai-result-ov.show` `.ai-result-sheet` `.ai-result-grid` `.ai-result-thumb-label.after` `.ai-result-actions` `.ai-result-btn.primary` `.ai-result-btn.danger`
**Bottom bar:** `.bottom-bar` `.bb-icon-btn` `.bb-icon-btn.undo` `.btn-next` `.btn-next-chev` `.next-menu.show` `.next-menu-item`
**Bottom sheets:** `.gs-ov` `.gs-ov.show` `.gs-sheet` `.gs-handle` `.gs-group` `.gs-group-pin` `.gs-val`
**Aurora:** `.aurora` `.aurora-blob`

## 5.4 Mode toggle behavior

**State:** `data-mode` атрибут на `.mode-toggle` (`"single"` | `"var"`)

```javascript
function setMode(m) {
  document.querySelector('.mode-toggle').setAttribute('data-mode', m);
  document.querySelectorAll('.mode-tab').forEach(t =>
    t.classList.toggle('active', t.dataset.tab === m));
  document.getElementById('varSection').style.display = (m === 'single') ? 'none' : '';
  document.getElementById('singleQtyField').style.display = (m === 'single') ? '' : 'none';
  document.getElementById('singleMinField').style.display = (m === 'single') ? '' : 'none';
}
```

**Server side (PHP):**
- `single` → `products.has_variations=0`, `inventory.quantity=$singleQty`, `inventory.min_quantity=$singleMin`
- `var` → `products.has_variations=1`, qty/min разпределени по `inventory` rows за всеки SKU

## 5.5 Section 1: МИНИМУМ

### Полета (in order)

| # | Поле | DB | Required? | Voice parser | Special |
|---|---|---|---|---|---|
| 1 | Име | `products.name` | ✅ | Web Speech BG → Whisper fallback | On blur: `strlen >= 2` |
| 2 | Цена | `products.retail_price` | ✅ | **`_wizPriceParse` (commit `4222a66` — SACRED)** | EUR + лв auto-conversion; recompute margin |
| 3 | Количество | `inventory.quantity` | ✅ (single mode) | Whisper (number-only) | Hidden on `mode=var` |
| 4 | Мин. кол-во | `inventory.min_quantity` | ✅ (single mode) | Whisper (number-only) | Auto-fill: `Math.round(qty/2.5) min 1`; amber stepper; hidden on `mode=var` |
| 5 | Артикулен номер | `products.code` | ❌ ("ПО ЖЕЛАНИЕ") | Web Speech | Празно → AI auto-generate `tenant_prefix-YYMMDD-NNNNN` |
| 6 | Баркод | `products.barcode` | ❌ ("ПО ЖЕЛАНИЕ") | Web Speech + scan btn (Capacitor) | Празно → AI auto-generate EAN-13 на печат |

### Save endpoint: `POST /api/products-bulk-save.php`

Request:
```json
{
  "section": "minimum",
  "tenant_id": 7,
  "session_id": null,
  "mode": "single",
  "data": {
    "name": "Дамска тениска Tommy Jeans",
    "retail_price": 28.00,
    "single_qty": 5,
    "single_min": 2,
    "code": null,
    "barcode": null
  }
}
```

Response:
```json
{
  "ok": true,
  "product_id": 12345,
  "confidence_score": 30,
  "session_id": 567,
  "next_section_hint": "supplier"
}
```

### Confidence formula (Hidden Inventory §5)

| Section saved | +score |
|---|---|
| Section 1 | +30 |
| Section 2 | +20 |
| Section 3 | +25 |
| Section 4 | +15 |
| Section 5 | +10 |
| **Total max** | **100** |

## 5.6 Section 2: ВАРИАЦИИ (skip if single)

### Размери (default 6 chips)
XS · S · M · L · XL · XXL — toggle active.

`.extra-row`:
- `[+ добави размер]` — inline modal за custom (3XL, 44, W34)
- `[+ други групи →]` — opens `#sizeGroupsSheet` bottom sheet с **6 групи:**
  - EU дамски обувки (35-41)
  - EU мъжки обувки (39-46)
  - EU дамски облекло (34-48)
  - Дънки W (W26-W36)
  - Детски (86-128)
  - US/UK (XS-3XL)

Active размери → matrix rows.

### Цветове (default 5 chips with swatches)
Черен · Бял · Розов · Червен · Син

`[+ добави]` → color picker modal (color input + name + voice).

Active цветове → matrix columns + photo result rows в Section 4.

### Matrix (inline grid в P13 Section 2)

- Rows = active sizes
- Columns = active colors
- Всяка клетка:
  - `qty` input (number, default empty)
  - `min` input (small amber, optional)
  - `.has-qty` клас когато qty > 0
- `[Цял екран]` → opens **P12 matrix overlay** (recommend at ≥4×4 cells)

### SKU summary (auto-computed в PHP)

```
{N_SIZES} размера × {N_COLORS} цвята = {N_SKU} SKU · Σ {TOTAL_QTY} бр.
```

Example: `3 размера × 2 цвята = 6 SKU · Σ 14 бр.`

### Save endpoint

```json
{
  "section": "variations",
  "product_id": 12345,
  "data": {
    "sizes": ["S", "M", "L"],
    "colors": [
      {"name": "Бял", "hex": "#ffffff"},
      {"name": "Розов", "hex": "#ec4899"}
    ],
    "matrix": [
      {"size": "S", "color": "Бял", "qty": 2, "min": 1},
      {"size": "S", "color": "Розов", "qty": 3, "min": 1},
      {"size": "M", "color": "Бял", "qty": 3, "min": 1},
      {"size": "M", "color": "Розов", "qty": 4, "min": 2},
      {"size": "L", "color": "Бял", "qty": 1, "min": 1},
      {"size": "L", "color": "Розов", "qty": 1, "min": 1}
    ]
  }
}
```

PHP създава `inventory` row за всеки combination (6 SKU = 6 rows).

## 5.7 Section 3: ДОПЪЛНИТЕЛНИ

| # | Поле | DB | Voice | Special |
|---|---|---|---|---|
| 1 | Доставна цена | `products.cost_price` | `_wizPriceParse` | EUR + лв; recompute margin |
| 2 | Цена на едро | `products.wholesale_price` (optional) | `_wizPriceParse` | EUR + лв |
| 3 | **Марж % (auto)** | `products.margin_pct` (cached) | — | Read-only badge. Formula: `((retail-cost)/cost)*100`. Color: green >50%, amber 20-50%, red <20% |
| 4 | Доставчик | `products.supplier_id` FK | Web Speech | "+ нов доставчик" inline modal |
| 5 | Категория | `products.category_id` FK | Web Speech | "+ нова категория" inline modal; on change → reload subcategory |
| 6 | Подкатегория | `products.subcategory_id` FK | Web Speech | Disabled до category select |
| 7 | Материя/състав | `products.material` (optional) | Web Speech | Plain text |
| 8 | Произход | `products.origin_country` | Web Speech | Default 4 options + "+ нова" |
| 9 | Мерна единица | `products.unit` | Web Speech | Default 4: Брой, Чифт, Кг, Метър. `[+ други групи]` → bottom sheet с 5 groups |

### Save endpoint

```json
{
  "section": "supplier_details",
  "product_id": 12345,
  "data": {
    "cost_price": 12.00,
    "wholesale_price": 20.00,
    "supplier_id": 4,
    "category_id": 12,
    "subcategory_id": 47,
    "material": "100% памук",
    "origin_country": "Турция",
    "unit": "Брой"
  }
}
```

## 5.8 Section 4: СНИМКИ (AI photo recognition)

**Source of truth:** colors selected в Section 2.

### При variations mode

1. Натиска **"Заснеми всички наведнъж"** или **"Галерия"**
2. Camera/file picker → multi-file upload (3-10 photos)
3. Files → upload to **`/api/photo-ai-detect.php`**
4. Server runs **Gemini Vision API** да детектне dominant color per photo
5. Match each photo to color от Section 2 (ΔE distance, threshold < 25)
6. Returns JSON:
```json
{
  "results": [
    {"file_idx": 0, "matched_color": "Бял", "confidence": 0.94, "image_url": "/uploads/p12345_w.jpg"},
    {"file_idx": 1, "matched_color": "Розов", "confidence": 0.72, "image_url": "/uploads/p12345_p.jpg"},
    {"file_idx": 2, "matched_color": null, "confidence": 0.18, "image_url": "/uploads/p12345_unkn.jpg", "suggested": ["Розов", "Червен"]}
  ]
}
```
7. Frontend renders `.photo-result-row` per match
8. **Override UX:** "размени" бутон → modal с list на всички цветове → tap да премести photo
9. **★ Главна** radio per row — само 1 active за артикула

### При single mode

- 1 large photo card (no AI detection — single product, single photo)
- Camera + Gallery buttons
- 4 photo tips (легнало, без други, светлина, рязкост)

### Confidence routing (sacred)

| Confidence | Action |
|---|---|
| ≥ 0.85 | Auto-attach. Pill "AI 94%" green |
| 0.60 – 0.85 | Require tap-to-confirm. Pill "AI 72%" amber |
| < 0.60 | Block. "Размени" button highlighted |

### Save endpoint

```json
{
  "section": "photos",
  "product_id": 12345,
  "data": {
    "photos": [
      {"color_name": "Бял", "image_url": "/uploads/p12345_w.jpg", "is_main": true, "ai_confidence": 0.94},
      {"color_name": "Розов", "image_url": "/uploads/p12345_p.jpg", "is_main": false, "ai_confidence": 0.72}
    ]
  }
}
```

PHP създава `product_images` rows (1 main, others secondary, indexed by color).

## 5.9 Section 5: AI Studio integration

### Credits strip (top)

```html
<div class="ai-credits-strip">
  <span class="ai-cred-gem">[gem SVG]</span>
  <span class="ai-cred-text"><b>17 / 30</b> безплатни магии</span>
  <span class="ai-cred-after">след това · <b>€0.05/магия</b></span>
</div>
```

Source: `SELECT free_credits_used, free_credits_total FROM tenant_ai_credits WHERE tenant_id=?`

### AI Studio link (`.ai-link-row`)

Tap → opens `ai_studio_FINAL_v5.html` modal с product context.

### Quick actions (2 buttons)

1. **Премахни фон** → opens `.ai-result-ov` overlay с преди/след preview
2. **SEO описание** → AJAX → fills `products.description_seo` → toast "AI генерира описание"

### AI Result overlay (`.ai-result-ov`)

- Slide up (translateY 100% → 0)
- Header: "AI завърши обработката · 2.4 сек."
- 2 thumbnails grid: Преди / След (с green "След" label)
- 3 buttons: **Отхвърли** (red) / **Опитай пак** / **Приеми** (green primary)
- Приеми → save processed image, close
- Отхвърли → discard, close
- Опитай пак → re-run с different params

## 5.10 Bottom bar

### Бутони

| # | Бутон | Поведение |
|---|---|---|
| 1 | **Undo** (red icon) | Maintains client-side action stack (last N=20). Tap → pop last, revert UI, `POST /api/products-bulk-undo.php` ако server-side. |
| 2 | **Печат** | Print modal. Lists всички SKUs (current + bulk session). Tabs: € + лв (default) / Само € / Без цена. Toggle "Печат без баркод". Per-SKU qty steppers. "Печатай всички N етикета" CTA. |
| 3 | **CSV** | Downloads current product OR пълна bulk session като CSV. Header: name, code, barcode, retail, cost, supplier, category, sizes, colors, qty. Rows: 1/SKU. |
| 4 | **Запази · следващ** | Saves всички unsaved sections atomically. Chevron tap → `.next-menu` (slide up): **"Като предния"** (template inheritance + ditto markers) / **"Празно"** (clears form) |

### Bulk session state

- 1-ви артикул save → creates `bulk_sessions` row, sets `template_product_id`
- 2-ри+ artikul → reads template, applies inheritance
- "Като предния" → uses latest saved като template
- "Празно" → clears `template_product_id`
- Session ends on close OR 30 min idle
- All bulk session items linked via `bulk_session_items.session_id`

## 5.11 Search "Намери и копирай"

### Collapsed
`.find-pill` с "Намери артикул да копираме" + voice icon → tap expand.

### Expanded (`.find-panel.show`)
- Search input + close + voice
- Filter chips (scrollable):
  - **"Като последния"** (default active, ↻ icon) — copies last saved
  - "Всички" — no filter
  - "Tommy Jeans", "Бельо", "Тениски", "Наскоро" — auto от recent
- Results (max-height 280px): thumb + name + meta (supplier · price · sizes · SKU count) + arrow

### Tap result
`GET /api/products-search-copy.php?id=12345&action=copy` → frontend collapse panel + populate fields с ditto markers + toast "Копирано от: {product_name}".


---

# 6. VOICE INTEGRATION (sacred)

**Източници:**
- `services/voice-tier2.php` (333 реда) — sacred file
- `docs/PROMPT_TOMORROW_S99_VOICE.md` — voice spec за бъдеща работа
- `products.php` ред 12895-12931 — `wizMic` router
- Locked commits: `4222a66` + `1b80106`

## 6.1 Architecture (current state, May 2026)

**Hybrid voice stack** — Whisper Tier 2 (primary) → Web Speech (fallback) → toast error.

```
User taps wiz-mic on input
  ↓
wizMic(field) — router (products.php:12900)
  ↓
  ├─ Numeric/price field? → _wizMicWhisper(field, lang)  [PRIMARY]
  │      ↓
  │      services/voice-tier2.php
  │      ↓
  │      Groq Whisper API (audio → transcript + confidence)
  │      ↓
  │      normalizeWithSynonyms() [voice-tier2.php:204] — БГ числа
  │      ↓
  │      _bgPrice / _wizPriceParse [SACRED commits]
  │      ↓
  │      Apply to input
  │
  └─ Text field? → _wizMicWebSpeech(field, lang)
         ↓
         Web Speech API (browser, bg-BG)
         ↓
         continuous=true, interimResults=true
         ↓
         _wizMicInterim(field, text) — live transcript display
         ↓
         _wizMicApply(field, final) — apply on .onresult final
```

## 6.2 Whisper Tier 2 (Groq)

### Credentials

Файл: `/etc/runmystore/api.env` (chmod 600)
Ключ: `GROQ_API_KEY`
Loader: `getGroqApiKey()` (voice-tier2.php:23, static cached per request)

### Why Whisper за числа

Web Speech bg-BG = max ~80% точност за числа (testing с 4 patch цикли, capped). Whisper Tier 2 на Groq = 95%+ за БГ числа, с context-aware prompt hints.

### Confidence model

Whisper segments връщат `avg_logprob` (винаги ≤ 0). Конвертирано в [0..1]:

```php
function whisperLogprobToConfidence(float $avg_logprob): float {
    $p = exp($avg_logprob);
    return max(0.0, min(1.0, $p));
}
```

Empirically (voice-tier2.php:43-44):
- `-0.10` → `0.90` confidence
- `-0.30` → `0.74`
- `-0.70` → `0.50`
- `-1.50` → `0.22`

### Confidence routing (Закон №1A)

| Confidence | Action | UI |
|---|---|---|
| ≥ 0.85 | Auto-apply | Green toast "Записано" |
| 0.70 – 0.85 | Apply with warning | Yellow toast |
| < 0.70 | Block, request re-record | "Не разпознах ясно, повтори" |

## 6.3 `normalizeWithSynonyms` — БГ синоним нормализация

Файл: `services/voice-tier2.php:204`
Signature: `function normalizeWithSynonyms(string $transcript, int $tenant_id, string $lang = 'bg'): string`

Това е критичната функция която прави "edno i petdeset" / "едно и петдесет" → разпознаваеми числа. Тих работи цял ден да я нагласи. **НЕ пипай.**

## 6.4 `_wizPriceParse` / `_bgPrice` (sacred JS)

Локация: products.php (около ред 12930+, точният ред варира).
Commits: **`4222a66`** + **`1b80106`** (LOCKED — никога не revert).

DoD targets (от PROMPT_TOMORROW_S99_VOICE):

| Voice вход | Очакван изход |
|---|---|
| "пет лева" | `5.00` ✅ |
| "едно и петдесет" | `1.50` ✅ (днес fail-ва — за S99 работа) |
| "двайсе и пет" | `25.00` ✅ |
| "сто двайсе и пет лева и петдесет стотинки" | `125.50` ✅ |
| "и двайсе" (incomplete) | Toast "Кажи цялата цена" + low confidence ✅ |
| "пет" в Брой | `5` ✅ |
| "петнайсе" в Брой | `15` ✅ |

## 6.5 Locale-aware prompt context (за S99 future work)

Файл: `config/voice-locales.json` (тепърва се добавя)

```json
{
  "bg": {
    "lang": "bg",
    "currency_words": ["лева", "лв", "стотинки", "стот"],
    "number_hints": "Числа на български: едно, две, три, петнайсе, двайсе, трийсе, петдесет, сто, сто двайсе и пет лева и петдесет стотинки.",
    "field_hints": {
      "retail_price": "Цена в лева. Например: пет лева, двайсе и пет, сто лева.",
      "quantity": "Брой. Цяло число от 1 до 999.",
      "barcode": "Баркод 8 или 13 цифри."
    },
    "parser": "_bgPrice"
  },
  "ro": { "lang": "ro", "currency_words": ["lei", "leu", "bani"], "parser": "_roPrice" },
  "el": { "lang": "el", "currency_words": ["ευρώ", "λεπτά"], "parser": "_elPrice" },
  "sr": { "lang": "sr", "...": "..." },
  "hr": { "lang": "hr", "...": "..." },
  "mk": { "lang": "mk", "...": "..." }
}
```

Settings page edit: `tenants.voice_locale` ENUM. Default `bg`.

## 6.6 Voice Activity Detection (VAD) — за S99

Future improvement (не текущо):
- Auto-stop при тишина > 1.5 sec
- RMS audio level threshold detect
- Без fixed timeout (5s беше грешка)

Flow:
```
Tap mic → recording старт → user говори → пауза 1.5s → auto-stop → POST към Whisper
```

## 6.7 Pre-record buffer (200ms) — за S99

Future improvement: stream винаги active в background докато wizard отворен. Buffer rolling 200ms. При tap mic → буферът се приключва към recording → захваща началото на първата дума.

Решава проблема "едно/една/едно" се губи в Web Speech bg-BG.

## 6.8 Sequential queue — за S99

Само 1 активен Whisper request в момента. Tap по време на pending request → cancel предишния, старти нов.

Решава race condition: "пет" в Цена → tap Брой → "пет" се появява в Брой instead.

## 6.9 Fallback chain (sacred order)

```
1. Whisper Tier 2 (primary за числа, цени, баркодове)
2. Web Speech bg-BG (fallback за текст; fallback ако Whisper fail/timeout)
3. Numpad UI (numeric fallback ако и двете fail)
4. Toast "Въведи ръчно"
```

## 6.10 Voice command intents (бъдеща работа — S99+)

| Command | Effect |
|---|---|
| "Нов артикул" | Focus Section 1 Име |
| "Тениска двадесет и осем лева" | Fill Име + retail_price |
| "Размер S M L" | Toggle active size chips |
| "Бял розов червен" | Toggle color chips |
| "Снимай всички" | Trigger camera bulk upload |
| "Магия фон" | Trigger Премахни фон |
| "Запази минимум" | Tap save в Section 1 |
| "Като предния" | Tap "Като предния" в Next menu |
| "Назад" | Undo |
| "Затвори" | Close wizard |

### Trigger words (БГ + non-BG fallback)

- "запиши" / "запази" / "save"
- "следващ" / "next"
- "назад" / "back" / "undo"
- "магия" / "magic"

## 6.11 Cost budget

Groq Whisper ~$0.0001/sec audio.

Estimate (от PROMPT_TOMORROW_S99_VOICE):
- 100 tenants × 50 артикула/седмица × 5 numeric fields × 3s = **$7.50/седмица = $30/месец** total

Приемливо.

## 6.12 Voice integration LOCKED files (DO NOT MODIFY)

| Файл | Commits |
|---|---|
| `services/voice-tier2.php` | sacred (333 реда) |
| `ai-color-detect.php` | sacred (296 реда) |
| `products.php:12900-12931` | `4222a66`, `1b80106` |
| `_wizPriceParse` JS function | `4222a66` |
| `_bgPrice` JS function | `4222a66` |


---

# 7. COLOR DETECTION (Gemini Vision)

**Sacred file:** `ai-color-detect.php` (296 реда). НЕ пипай.
**Source code:** S82.AI_STUDIO + S82.COLOR.4 (multi-image).

## 7.1 Endpoint specification

`POST /ai-color-detect.php` (multipart form)

### Single image mode (default)

**Request:**
- Field: `image` (file, jpg/png/webp, max 10 MB)
- Auth: `$_SESSION['tenant_id']` required

**Response:**
```json
{
  "ok": true,
  "colors": [
    {"name": "черен", "hex": "#0a0a0a", "confidence": 0.95},
    {"name": "бял", "hex": "#fafafa", "confidence": 0.7}
  ],
  "plan": "PRO",
  "used": 3,
  "remaining": 7
}
```

Max 4 цвята, сортирани по доминиране, БГ имена.

### Multi-image mode (`?multi=1`)

**Request:**
- Field: `image_0`, `image_1`, ..., `image_N` (up to 30 files)
- Field: `count` (int, expected count)
- Auth: same

**Response:**
```json
{
  "ok": true,
  "results": [
    {"idx": 0, "color_bg": "черен", "hex": "#0a0a0a", "confidence": 0.92},
    {"idx": 1, "color_bg": "бял", "hex": "#fafafa", "confidence": 0.81},
    {"idx": 2, "color_bg": "розов", "hex": "#ec4899", "confidence": 0.68}
  ],
  "plan": "PRO",
  "used": 4,
  "remaining": 6
}
```

`idx` отговаря на оригиналния ред на снимките (0-индексирано). Multi mode prompt expressly forces AI да върне цвят дори при low confidence (никога празно).

## 7.2 Gemini Vision prompt (sacred)

### Single image prompt
```
Намери обекта, който е ТОЧНО в средата на снимката (центъра на кадъра). 
Този централен обект е продуктът, който трябва да анализираш.
Игнорирай ВСИЧКО останало: фона, ръцете на модела, сенките, други предмети 
по краищата/ъглите, повърхността под обекта.
Върни МАКСИМУМ 4 основни цвята САМО на този централен обект, сортирани по доминиране.
Отговори САМО с валиден JSON, БЕЗ markdown:
[{"name":"черен","hex":"#0a0a0a","confidence":0.95}, {"name":"бял","hex":"#fafafa","confidence":0.7}]
```

### Multi-image prompt
```
Анализирай N снимки на артикули в реда на подаване.
За ВСЯКА снимка намери обекта, който е ТОЧНО в средата на кадъра (центъра) — това е продуктът.
Игнорирай фона, ръцете, сенките и всички предмети в краищата/ъглите.
Върни ЕДИН доминиращ цвят САМО на този централен обект.
Дори при ниска увереност (confidence<0.7) ВСЕ ПАК давай предположение — НЕ оставяй цвят празен.
Отговори САМО с валиден JSON, БЕЗ markdown:
{"results":[{"idx":0,"color_bg":"черен","hex":"#0a0a0a","confidence":0.92}, ...]}
idx трябва да съответства на реда на снимките (0-индексирано).
```

## 7.3 API config

| Setting | Value |
|---|---|
| Model | `gemini-2.5-flash` (default, configurable via `GEMINI_MODEL` env) |
| Temperature | 0.2 (deterministic) |
| maxOutputTokens single | 512 |
| maxOutputTokens multi | 4096 (S82.COLOR.7 fix: 1024 не стигаше за 3+ снимки) |
| responseMimeType | `application/json` |
| Timeout | 30s single / 45s multi |
| Connect timeout | 5s |
| API endpoint | `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={key}` |

Credentials: `GEMINI_API_KEY` (primary) с fallback `GEMINI_API_KEY_2` (rotation) в `/etc/runmystore/api.env` (chmod 600).

## 7.4 Plan limits & quota

Аналогично на bg removal:

| Plan | Daily limit |
|---|---|
| FREE | 0 |
| START | 3 |
| PRO | 10 |
| BUSINESS | (TBD) |

Quota check: `rms_image_check_quota($tenant_id)` (от `ai-image-credits.php`).
Usage record: `rms_image_record_usage($tenant_id, $user_id, 'color_detect')`.

429 response при exceeded: `{ok: false, reason: ..., plan, used, limit}`.

## 7.5 Error handling

| HTTP | Condition | Reason |
|---|---|---|
| 200 | Success | — |
| 400 | Bad request | "Липсва или невалидна снимка." / "Няма валидни снимки." |
| 401 | Not logged in | "Не сте влезли." |
| 405 | Wrong method | "POST only." |
| 413 | File too big | "Снимката е по-голяма от 10 MB." |
| 415 | Wrong mime | "Поддържат се само JPG, PNG, WebP." |
| 429 | Quota exceeded | dynamic от `rms_image_check_quota` |
| 502 | AI failed | "AI услугата не отговаря." / "AI грешка ({http})." / "Празен отговор от AI." / "AI върна неразпознаваем формат." / "AI не разпозна цветове." |
| 503 | Config missing | "AI Studio: липсва конфигурация. Свържи се с поддръжка." (`setup_required: true`) |

## 7.6 Confidence routing (Wizard Section 4)

| Confidence | UX |
|---|---|
| ≥ 0.85 | Auto-attach photo to color. Pill "AI 94%" green. |
| 0.60 – 0.85 | Show photo, require tap-to-confirm. Pill "AI 72%" amber. |
| < 0.60 | Block. "Размени" button highlighted. Manual override. |

## 7.7 Sacred response normalization

Server-side в endpoint-а (НЕ пипай):
- Hex normalize: `preg_replace('/[^#0-9a-fA-F]/', '', ...)` → ensure `#` prefix → fallback `#888888` ако invalid
- Confidence clamp: `max(0, min(1, $conf))`
- Name fallback: `'неуточнен'` ако празно име
- JSON fence strip: `^\`\`\`(?:json)?\s*` и `\s*\`\`\`$`

---

# 8. AI STUDIO INTEGRATION

**Източник:** `AI_STUDIO_LOGIC_DELTA.md` (21KB)
**Canonical mockup:** `ai_studio_FINAL_v5.html` + P8 семейство

## 8.1 Архитектура (v1.1 — current)

```
products.php (P13 wizard Section 5)
  └─ tap "Отвори AI Studio"
       ↓
ai-studio.php (standalone modal с product context)
  ├─ ai-studio-main-v2.html (canonical layout)
  ├─ ai_studio_FINAL_v5.html (per-product modal)
  ├─ ai-studio-categories.html (queue overlay = P8c)
  ├─ P8b advanced views:
  │    ├─ ai-studio-advanced-clothes.html
  │    ├─ ai-studio-advanced-acc.html (accessories)
  │    ├─ ai-studio-advanced-jewelry.html
  │    ├─ ai-studio-advanced-lingerie.html
  │    └─ ai-studio-advanced-other.html
  └─ P8b studio modal
```

**Не е отделен модул в products.php** — AI Studio е external integration през API/iframe modal.

## 8.2 Credits система

```html
<div class="ai-credits-strip">
  <span class="ai-cred-gem">[gem SVG]</span>
  <span class="ai-cred-text"><b>17 / 30</b> безплатни магии</span>
  <span class="ai-cred-after">след това · <b>€0.05/магия</b></span>
</div>
```

DB table: `tenant_ai_credits`
- `free_credits_used` INT — used this cycle
- `free_credits_total` INT — default 30
- `paid_credits_balance` INT — paid top-ups
- `last_reset_at` DATETIME — last reset

PRO plan = 30 free magic credits/month. След като свърши → €0.05/magic от paid balance.

## 8.3 P8c Queue overlay (от AI_STUDIO_LOGIC_DELTA §3)

### Trigger
Когато Митко натиска "Bulk магия" в AI Studio main view → otvarya queue overlay.

### Структура

- Header: "AI Magic Queue · N артикула"
- List: products с photo thumb + name + chosen action (Премахни фон / На модел / Описание)
- Per-item: status pill (Pending / Processing / Done / Failed)
- Footer: "Стартирай всички N магии" CTA + estimated cost (`N × €0.05 = €Х.ХХ`)

### DB query (PHP)

```php
SELECT p.id, p.name, p.image_url, q.action_type, q.status, q.error_msg
FROM ai_studio_queue q
JOIN products p ON p.id = q.product_id
WHERE q.tenant_id = ? AND q.status IN ('pending', 'processing')
ORDER BY q.created_at ASC
```

### Bulk action API

`POST /api/ai-studio-queue-start.php` { action_type, product_ids[] } → enqueues N items → cron worker процесва.

## 8.4 Bulk magic правило (v1.1 NEW)

**v1.0 (стара логика):** Bulk магия НЕ разрешена.
**v1.1 (нова):** Bulk магия РАЗРЕШЕНА само за:
- Премахни фон (всички photos на варианти)
- SEO описание (всички products в категория)
- Color override (manual, не AI)

НЕ разрешена за:
- "На модел" (твърде variable, изисква per-product approval)
- Custom prompts (require human review)

### Икономика

Bulk магия = 30% от monthly magic budget. Останалите 70% са per-item interactive (preview/accept/reject). Митко може да изключи bulk в settings ако предпочита 100% manual.

## 8.5 P8b Advanced — категория-специфични prompts

Different prompt templates per product category:

| Категория | Prompt focus |
|---|---|
| Облекло (clothes) | Model wear, fabric texture, full-body shots |
| Аксесоари (acc) | Close-up, single object, no model |
| Бижута (jewelry) | Macro detail, light reflection, white/black background |
| Бельо (lingerie) | Soft lighting, mannequin OR model, modest framing |
| Друго (other) | Generic product photography |

Mockup-и в `mockups/P8b_advanced_*.html` дефинират категория-специфичните бутони и опции.

## 8.6 Visual changes (v1.1)

| v1.0 | v1.1 |
|---|---|
| Emoji в UI (🪄✨🎨) | SVG only (icons) |
| "Gemini" / "fal.ai" labels | "AI" винаги |
| Inline buttons | Bottom sheet за advanced |

---

# 9. INVENTORY & CONFIDENCE_SCORE

**Източник:** `INVENTORY_HIDDEN_v3.md` (релевантни секции за products.php)

## 9.1 Философия — "The warehouse builds itself"

Pesho не прави inventory на day 1. Отваря касата и продава. Със всяко действие — продажба, доставка, AI въпрос — системата се учи. Точността расте организично, без усилие, без натиск.

## 9.2 Confidence score model

Всеки продукт има невидим `confidence_score` (0-100). Pesho НИКОГА не вижда числото. AI го ползва да знае колко да доверява на данните.

### Изчисление

| Event | Confidence |
|---|---|
| Created during sale (just name + price) | +20 |
| + barcode или артикулен номер | +10 |
| + cost price (от delivery/invoice) | +20 |
| + категория и доставчик | +10 |
| + delivery (quantity от invoice) | +20 |
| + physical confirmation (counted) | +20 |
| **Maximum** | **100** |

### Levels

| Level | Score | Какво AI знае |
|---|---|---|
| 🔴 Minimal | 0-30 | Name, retail price |
| 🟡 Partial | 31-60 | + barcode, category, supplier |
| 🟠 Good | 61-80 | + cost price, deliveries |
| 🟢 Full | 81-100 | Всичко |

### Wizard contribution (P13)

| Section saved | +confidence |
|---|---|
| Section 1 (Минимум) | +30 |
| Section 2 (Вариации) | +20 |
| Section 3 (Допълнителни — cost + supplier + cat) | +25 |
| Section 4 (Снимки) | +15 |
| Section 5 (AI Studio enhanced) | +10 |
| **Max from wizard** | **100** |

## 9.3 Sacred rule

**Confidence е НИКОГА не се показва на Pesho/Митко като число.** Само consequences:
- Ranges в статистики ("180-340€ profit" вместо "240€")
- AI въпроси по пътя ("Колко имаш на склад?")
- Suggestion strength ("сигурен съм" vs "вероятно")

## 9.4 Hidden Inventory paths

При onboarding AI пита: "Ще transfer-неш ли stock от файл/програма, или предпочиташ да започнем и системата учи докато работиш?"

### Path A: Transfer (CSV/Excel/програма)
→ Import → confidence 60-90% → Zone Walk за physical confirmation

### Path B: Lazy Way (Hidden Inventory)
→ Pesho продава от second 1 → системата учи от sales + deliveries + zone walks
→ AI: "Перфектно. Първо, дай ми да науча layout-а на магазина."

## 9.5 Zone setup (mandatory за hidden inventory)

3 типа зони:
- 🟢 **CUSTOMER ZONE** — display, видими за клиента (хангерс, центрова витрина)
- 🟡 **SHELF ZONE** — зад caundara, reserve в магазина (рафт зад касата)
- 🔴 **STORAGE ZONE** — back room, отделна стая, кутии

AI пита: "Имаш ли отделна storage room?" → 2 или 3 зони.

## 9.6 Self-correcting sales loop

При продажба:
1. Pesho скенира/казва име → AI намира product (или предлага creation at 20%)
2. AI знае стока = калкулирана (deliveries - sales)
3. Ако негативна (продал си преди да заскадиш) → AI: "Май имах грешка в склада. Колко имаш сега?"
4. Pesho отговаря → confidence +20% за този артикул

## 9.7 Mass confidence boost от deliveries

**One photo of invoice = 20 products from 30% to 80% in 30 seconds.**

При delivery OCR (Phase B):
1. Pesho снима фактурата
2. AI OCR → 20 lines (name, qty, cost_price, supplier)
3. Match срещу existing products (fuzzy):
   - Matched: fills cost_price + qty → confidence +40%
   - New: creates with 80% confidence (name + price + cost + qty)
4. Pesho confirms с 1 tap

## 9.8 Store Health Score (Митко вижда)

Replacement за "confidence" числото. Показва се на главна (P2 "Здраве на склада 82%"):

```php
function storeHealth($tenant_id, $store_id) {
    // 40% accuracy: % confirmed в 30 дни
    $total = COUNT(products WHERE tenant_id=?);
    $confirmed = COUNT(WHERE last_counted_at >= NOW() - 30 days);
    $accuracy = ($confirmed / $total) * 100;
    
    // 30% freshness: avg days since last zone walk
    $avg_days = AVG(DATEDIFF(NOW(), zones.last_walked_at));
    $freshness = max(0, 100 - ($avg_days * 3));
    
    // 30% AI confidence: average
    $avg_conf = AVG(products.confidence_score);
    
    return round(($accuracy * 0.4) + ($freshness * 0.3) + ($avg_conf * 0.3));
}
```

---

# 10. DB SCHEMA — products + bulk_sessions + миграции

**Migration file (Phase B):** `/var/www/runmystore/db/migrations/2026_05_p13_bulk_entry.sql`

## 10.1 `products` — нови колони (от HANDOFF Phase B + BULK §12)

```sql
ALTER TABLE products ADD COLUMN confidence_score TINYINT UNSIGNED DEFAULT 0 
  COMMENT 'Hidden Inventory: 0-100. Не се показва на Митко.';
ALTER TABLE products ADD COLUMN has_variations TINYINT(1) DEFAULT 0;
ALTER TABLE products ADD COLUMN last_counted_at DATETIME DEFAULT NULL;
ALTER TABLE products ADD COLUMN counted_via ENUM('manual','barcode','rfid','ai') DEFAULT NULL;
ALTER TABLE products ADD COLUMN first_sold_at DATETIME DEFAULT NULL;
ALTER TABLE products ADD COLUMN first_delivered_at DATETIME DEFAULT NULL;
ALTER TABLE products ADD COLUMN zone_id INT DEFAULT NULL;
ALTER TABLE products ADD COLUMN subcategory_id INT DEFAULT NULL;
ALTER TABLE products ADD COLUMN wholesale_price DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE products ADD COLUMN margin_pct DECIMAL(5,2) DEFAULT NULL 
  COMMENT 'Cached: ((retail-cost)/cost)*100';
ALTER TABLE products ADD COLUMN material VARCHAR(255) DEFAULT NULL;
ALTER TABLE products ADD COLUMN origin_country VARCHAR(100) DEFAULT NULL;
ALTER TABLE products ADD COLUMN unit VARCHAR(50) DEFAULT 'Брой';
```

**MySQL 8 НЕ поддържа `IF NOT EXISTS` на ADD COLUMN.** Pattern за idempotent миграция:

```sql
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'products' 
    AND COLUMN_NAME = 'confidence_score'
);
SET @sql = IF(@col_exists = 0, 
  'ALTER TABLE products ADD COLUMN confidence_score TINYINT UNSIGNED DEFAULT 0', 
  'SELECT 1');
PREPARE stmt FROM @sql; 
EXECUTE stmt; 
DEALLOCATE PREPARE stmt;
```

## 10.2 `subcategories` (нова таблица)

```sql
CREATE TABLE IF NOT EXISTS subcategories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  category_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant_cat (tenant_id, category_id),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);
```

## 10.3 `bulk_sessions` (нова таблица)

```sql
CREATE TABLE IF NOT EXISTS bulk_sessions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME DEFAULT NULL,
  template_product_id INT DEFAULT NULL,
  total_saved INT DEFAULT 0,
  total_sku INT DEFAULT 0,
  INDEX idx_tenant_user (tenant_id, user_id),
  INDEX idx_active (ended_at)
);
```

## 10.4 `bulk_session_items` (нова таблица)

```sql
CREATE TABLE IF NOT EXISTS bulk_session_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  session_id INT NOT NULL,
  product_id INT NOT NULL,
  saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  position INT NOT NULL,
  INDEX idx_session (session_id),
  FOREIGN KEY (session_id) REFERENCES bulk_sessions(id) ON DELETE CASCADE
);
```

## 10.5 `product_images` — AI confidence cache

```sql
ALTER TABLE product_images ADD COLUMN ai_confidence DECIMAL(3,2) DEFAULT NULL;
ALTER TABLE product_images ADD COLUMN ai_detected_color VARCHAR(50) DEFAULT NULL;
ALTER TABLE product_images ADD COLUMN color_override TINYINT(1) DEFAULT 0 
  COMMENT 'Митко override AI detection';
```

## 10.6 `tenant_ai_credits` (нова таблица)

```sql
CREATE TABLE IF NOT EXISTS tenant_ai_credits (
  tenant_id INT PRIMARY KEY,
  free_credits_used INT DEFAULT 0,
  free_credits_total INT DEFAULT 30,
  paid_credits_balance INT DEFAULT 0,
  last_reset_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
INSERT IGNORE INTO tenant_ai_credits (tenant_id) SELECT id FROM tenants;
```

## 10.7 Canonical field names — REFERENCE TABLE

(вече в Section 2.4, повтаряно за визуална референция)

| Field | Canonical | DO NOT use |
|---|---|---|
| Product code | `products.code` | sku |
| Retail price | `products.retail_price` | sell_price, price |
| Image URL | `products.image_url` | image |
| Cost price | `products.cost_price` | buy_price |
| Stock qty | `inventory.quantity` | qty |
| Min stock | `inventory.min_quantity` | min_stock |
| Sale status | `sales.status='canceled'` | cancelled (двойно L = бъг) |
| Sale item price | `sale_items.unit_price` | price |
| Sale total | `sales.total` | total_amount |


---

# 11. I18N KEYS — пълен списък за products

**Phase D migration:** Add to `i18n_translations` table за `bg` и `en`. Bulgarian fallback за всички липсващи ключове.

## 11.1 Wizard keys (bulk entry P13)

```json
{
  "bulk.title": "Добави артикул",
  "bulk.search.placeholder": "Намери артикул да копираме",
  "bulk.search.input": "Търси име · баркод · код",
  "bulk.search.filter.like_last": "Като последния",
  "bulk.search.filter.all": "Всички",
  "bulk.search.filter.recent": "Наскоро",
  "bulk.voice.title": "Кажи на AI",
  "bulk.voice.example": "\"Тениска 28лв · бял розов · S M L\"",
  "bulk.mode.single": "Единичен",
  "bulk.mode.var": "С вариации",
  "bulk.section.minimum": "Минимум",
  "bulk.section.variations": "Вариации",
  "bulk.section.supplier_details": "Допълнителни",
  "bulk.section.photos": "Снимки",
  "bulk.section.ai_studio": "AI Studio",
  "bulk.field.name": "Име",
  "bulk.field.retail_price": "Цена",
  "bulk.field.qty": "Количество",
  "bulk.field.min_qty": "Минимално кол-во",
  "bulk.field.code": "Артикулен номер",
  "bulk.field.barcode": "Баркод",
  "bulk.field.cost_price": "Доставна цена",
  "bulk.field.wholesale_price": "Цена на едро",
  "bulk.field.margin": "Марж (auto)",
  "bulk.field.supplier": "Доставчик",
  "bulk.field.category": "Категория",
  "bulk.field.subcategory": "Подкатегория",
  "bulk.field.material": "Материя / състав",
  "bulk.field.origin": "Произход",
  "bulk.field.unit": "Мерна единица",
  "bulk.field.sizes": "Размери",
  "bulk.field.colors": "Цветове",
  "bulk.field.optional": "ПО ЖЕЛАНИЕ",
  "bulk.hint.code_auto": "Празно → AI ще генерира уникален код автоматично.",
  "bulk.hint.barcode_auto": "Празно → AI ще генерира EAN-13 при отпечатване.",
  "bulk.hint.min_auto": "AI auto-set от количеството (qty/2.5).",
  "bulk.matrix.label": "Брой по комбинация · мин.",
  "bulk.matrix.fill_all": "Всички = 2",
  "bulk.matrix.expand": "Цял екран",
  "bulk.matrix.sku_summary": "{n_sizes} размера × {n_colors} цвята = {n_sku} SKU · Σ {total} бр.",
  "bulk.photos.cta_title": "Заснеми всички наведнъж",
  "bulk.photos.cta_sub": "AI ще разпознае цветовете и закачи всяка снимка автоматично",
  "bulk.photos.camera": "Камера",
  "bulk.photos.gallery": "Галерия",
  "bulk.photos.is_main": "Главна",
  "bulk.photos.swap": "Размени",
  "bulk.ai.credits": "<b>{used} / {total}</b> безплатни магии",
  "bulk.ai.after": "след това · <b>€{price}/магия</b>",
  "bulk.ai.open_studio": "Отвори AI Studio",
  "bulk.ai.studio_sub": "снимка · фон · описание · магия",
  "bulk.ai.remove_bg": "Премахни фон",
  "bulk.ai.seo_desc": "SEO описание",
  "bulk.ai.result_title": "AI завърши обработката",
  "bulk.ai.result_before": "Преди",
  "bulk.ai.result_after": "След",
  "bulk.ai.result_accept": "Приеми",
  "bulk.ai.result_retry": "Опитай пак",
  "bulk.ai.result_reject": "Отхвърли",
  "bulk.save.section": "Запази",
  "bulk.save.print": "Печат",
  "bulk.save.csv": "CSV експорт",
  "bulk.bottom.undo": "Отмени стъпка",
  "bulk.bottom.print_all": "Печат всички",
  "bulk.bottom.csv_all": "CSV сесия",
  "bulk.bottom.next": "Следващ",
  "bulk.next.like_prev": "Като предния",
  "bulk.next.like_prev_sub": "Наследява име · цена · доставчик · категория",
  "bulk.next.empty": "Празно",
  "bulk.next.empty_sub": "Нов артикул от 0 (различен доставчик/тип)",
  "bulk.groups.size_title": "Други размерни групи",
  "bulk.groups.unit_title": "Други мерни единици",
  "bulk.groups.tap_to_use": "тапни група за активиране",
  "bulk.groups.tap_to_pick": "тапни за избор"
}
```

## 11.2 Home / List view keys (P15 + P2 + P3)

Тези ще се финализират при S141 implementation. Placeholder ключове видяни в P15/P2/P3 мокапи:

```
T_TAG_LATE, T_TAG_TODAY, T_TAG_RESOLVED, T_TAG_PENDING, T_TAG_CAUSE
T_VIEW, T_REMIND, T_RECEIVE_NOW, T_USEFUL, T_VIEW_ALL, T_GOT_IT
T_RECEIVE_HOW, T_RECEIVE_HOW_SUB
T_OPT_OCR, T_OPT_VOICE, T_OPT_SCAN, T_OPT_IMPORT, T_OPT_OCR_SUB, T_OPT_VOICE_SUB, T_OPT_SCAN_SUB, T_OPT_IMPORT_SUB, T_SEC
T_SORT_NAME, T_SORT_PRICE_ASC, T_SORT_PRICE_DESC, T_SORT_STOCK_ASC, T_SORT_STOCK_DESC, T_SORT_NEWEST
T_VAR, T_LOAD_MORE, T_VARIATIONS, T_TOTAL_STOCK, T_ALL_VARIATIONS, T_PRINT_ALL, T_EXPORT
T_COLOR_BLACK, T_COLOR_WHITE, T_COLOR_RED, T_COLOR_BLUE (и т.н. цветове)
T_Q2_TAG, T_Q3_TAG, T_Q4_NAME, T_Q5_NAME, T_Q6_NAME
T_PRODUCTS, T_WHY, T_SHOW, T_ORDER, T_MARGIN_PROBLEMS, T_FIX
T_TOP_SELLERS, T_REORDER, T_MONTH
T_REASONS_GROWING, T_TELL_ME_MORE
T_PRODUCTS_TO_ORDER, T_CREATE_ORDER
T_FROZEN, T_DISCOUNT
T_SEE_ALL_PRODUCTS, T_ALL_SUPPLIERS
```

## 11.3 i18n rule (sacred)

```php
// loadTranslations() loaded once per request
$T = loadTranslations($tenant['language']);

// Use везде:
echo t('bulk.field.name', $tenant);
echo t('bulk.matrix.sku_summary', $tenant, ['n_sizes' => 3, 'n_colors' => 2, 'n_sku' => 6, 'total' => 14]);
```

**Никога:**
- `echo "Име";`  ❌
- `<label>Цена</label>` ❌

**Винаги:**
- `echo t('bulk.field.name', $tenant);` ✅
- `<label><?= t('bulk.field.retail_price', $tenant) ?></label>` ✅

---

# 12. ЕТИКЕТ ПЕЧАТ

**Архитектура:** Dual printer bridge — BLE GATT + Bluetooth Classic SPP.
**Sacred file:** `js/capacitor-printer.js` (2097 реда). История от S82 до S98 — не пипай без backup tag.

## 12.1 Поддържани принтери

| Принтер | Транспорт | Протокол | Service / Char |
|---|---|---|---|
| **DTM-5811** | BLE GATT | TSPL | `18f0` service / `2af1` char |
| **D520BT** | Bluetooth Classic SPP / RFCOMM | TSPL | SDP 1101 |

Plugin-и (Capacitor):
- `@capacitor-community/bluetooth-le` (за DTM BLE)
- `@e-is/capacitor-bluetooth-serial` (за D520 SPP)

## 12.2 API (window.RmsPrinter)

| Функция | Описание |
|---|---|
| `pair()` | DTM BLE pairing flow (legacy alias, backwards-compatible) |
| `pairD520()` | D520BT Classic SPP pairing flow (нов в S96) |
| `print(opts)` | Route by active printer type. `opts.type='DTM'\|'D520'` overrides |
| `printAll(labels)` | Bulk print N labels |

## 12.3 Print флоу

```
products.php P13 wizard save → toast + print modal
  → Pesho tap [🖨 ПЕЧАТАЙ ЕТИКЕТ]
       ↓
window.RmsPrinter.print({ items: [{ name, code, barcode, retail_price, ... }] })
       ↓
  ├─ Active printer = DTM-5811 → BLE GATT path
  │     ↓
  │     discoverDtmEndpoint(ble, deviceId, deviceName)  [ред 227]
  │     ↓
  │     writeChunked_DTM(ble, deviceId, tsplBytes)  [ред 1511]
  │
  └─ Active printer = D520BT → Bluetooth Classic SPP path
        ↓
        writeSPP_D520(address, tsplBytes)  [ред 1524]
```

## 12.4 TSPL команден шаблон (50×30mm етикет)

Етикетът съдържа:
- Име продукт (1-2 реда)
- Артикулен номер
- Баркод (EAN-13 или Code128)
- Цена (€ + лв до 08.08.2026)

Формат: 50×30mm, TSPL ASCII-only (BG cyrillic превърнат в latin transliteration ИЛИ ASCII fallback при D520).

## 12.5 D520BT SPP caveat (от capacitor-printer.js docs)

`@e-is` plugin's `write()` encodes JS string с `getBytes(UTF_8)` от Java side. ASCII (0x00-0x7F) минава. **0x80-0xFF стават 2-byte UTF-8 sequences** (corrupting the wire).

**Phase 3 (current):** ASCII-only TSPL → БГ → latin transliteration.
**Phase 4 (future):** Frame-wrapping → изисква fork на plugin за ISO-8859-1 mapping ИЛИ base64.

## 12.6 S95 history (do NOT revisit)

5 BLE подхода failed за D520BT:
- Phomemo D-family BLE
- LuckPrinter SDK
- Exact replay (BLE)
- writeWithoutResponse
- Frame wrapping over GATT

**Result:** D520BT advertises BLE service `ff00` но print engine е hardwired към RFCOMM SDP 1101 (потвърдено с Wireshark btsnoop, 0 btatt packets during print). Всички BLE опити изтрити в S96.D520.2.

**Sacred lesson:** Не опитвай BLE за D520BT. Винаги Classic SPP.

## 12.7 Browser fallback (NO Capacitor)

products.php ред 5527, 8747, 12643:

```javascript
// Generic browser fallback (window.print() + JsBarcode)
html += '<script>setTimeout(function(){window.print();},400);</script>';
```

Pattern:
1. Build HTML с label info
2. Render barcode (JsBarcode lib, EAN13 format)
3. `setTimeout(window.print, 400ms)` — wait за barcode rendering
4. Browser print dialog

**Toast ако не е mobile app:** "Печатът работи само в мобилното приложение (DTM-5811 BLE)" (products.php ред 8696).

## 12.8 Print modal (P13 bottom bar Печат)

UI tabs:
- **€ + лв (default)** — двойно обозначаване до 08.08.2026
- **Само €** — без лв ред
- **Без цена** — само име/код/баркод

Toggle: "Печат без баркод" (за артикули без барод yет).

Per-SKU qty steppers — Pesho избира колко етикета на всяка вариация.

CTA: "Печатай всички N етикета" (green primary).

---

# 13. РАБОТЕН РЕД (PREBETA tasks → реалистичен sequence)

**Източник:** PREBETA_MASTER_v2.md задачи 1.1.1 - 1.1.7

## 13.1 PREBETA задачи за products.php

| # | Задача | Mockup | Target в products.php | Стратегия |
|---|---|---|---|---|
| 1.1.1 | Лесен режим simple view | **P15** | scrHome (4321-4635) for mode-simple | LOCAL REPLACE (не INJECT-ONLY) |
| 1.1.2 | Разширен dashboard | **P2** | scrHome for mode-detailed | LOCAL REPLACE (но HANDOFF маркира P2 като legacy — pending Тих) |
| 1.1.3 | Разширен списък | **P3** | scrProducts (4650-4694) | LOCAL REPLACE |
| 1.1.4 | Wizard (добави артикул) | **P13** + voice-first | Wizard зона (~7800-12900) | MAJOR REWRITE (Phase C от HANDOFF) |
| 1.1.5 | Matrix (вариации) | **P12** overlay | НОВ overlay | NEW component |
| 1.1.6 | Етикети: печат работи от двата режима | (capacitor-printer.js) | Print modal в bottom bar | TEST only (логиката работи) |
| 1.1.7 | Тест: light + dark + Z Flip6 373px | — | Цял модул | Visual regression |

## 13.2 Препоръчителен реален sequence

Логика: започваме от entry point (P15) → wizard (P13 = главната работа) → matrix → list → detailed home → printing → test.

| Стъпка | Задача | Размер | Риск | Backup tag |
|---|---|---|---|---|
| 1 | **P15 → scrHome simple** | ~317 реда replace | Среден | `pre-S141-p15-home` |
| 2 | **P13 → wizard rewrite** | ~5000 реда replace | Висок (~3 дни работа) | `pre-S141-p13-wizard` |
| 3 | **P12 → matrix overlay** | ~500 реда нов код | Среден | `pre-S141-p12-matrix` |
| 4 | **P3 → scrProducts list** | ~45 реда (current) → ~800 нови | Среден | `pre-S141-p3-list` |
| 5 | **P2 → scrHome detailed** | ~317 реда replace | Среден | `pre-S141-p2-detailed` |
| 6 | **Печат тест** | 0 реда (тест-only) | Нисък | — |
| 7 | **Z Flip6 + light/dark test** | 0 реда (test-only) | Нисък | — |

## 13.3 Стратегия per стъпка

### Стъпка 1: P15 → scrHome simple (LOCAL REPLACE)

**Защо НЕ INJECT-ONLY:** P15 е ~30% match с current scrHome съдържание. CSS overlay = маскиране, не replacement. P15 има нови блокове (СВЪРШИЛИ, ЗАСТОЯЛИ, AI поръчка, AI вижда signals) които текущ scrHome няма.

**LOCAL REPLACE strategy:**
1. Backup tag `pre-S141-p15-home`
2. Read P15 mockup → identify CSS classes, HTML structure, embedded JS
3. Replace ред 4321-4635 в products.php (sceHome content) с P15 1:1
4. Wrap в `<?php if ($user_role === 'seller'): ?>` ... `<?php endif; ?>` (показва се само за seller)
5. CSS overrides в нов "S141 OVERRIDES" блок
6. Connect data binding: PHP queries за свършили, застояли, AI insights → frontend rendering
7. Test on staging → backup tag check → push

**Не пипай:** wizMic, ai-color-detect, wizard code, all functions от Section 2 sacred list.

### Стъпка 2: P13 → wizard rewrite (MAJOR)

Това е **Phase C от HANDOFF_FINAL_BETA**. Най-голямата работа в проекта.

**Spec source:** `PRODUCTS_BULK_ENTRY_LOGIC.md` (35KB железна) + Section 5 от този документ.

**Стратегия:** Read целия P13 mockup → migrate CSS classes → HTML structure → 5 accordion sections → integrate с existing voice/color sacred functions.

**Verification gates (от HANDOFF):**
- `php -l products.php` exit 0
- `bash design-kit/check-compliance.sh products.php` exit 0
- Visual diff vs P13 ≤ 1% pixel divergence (375px viewport)
- Mode toggle работи
- Save per section запазва confidence_score правилно
- Matrix expand → P12 overlay
- Photo AI detect → confidence threshold правилно
- AI Studio link → modal
- Bottom bar Undo работи
- "Запази · следващ" dropdown показва "Като предния" + "Празно"

### Стъпки 3-7: TBD при достигането им

Всяка стъпка отделен план след завършване на предишната.

## 13.4 Cycle на стъпка (от S140 proven workflow)

```
1. Аз: backup tag → push
2. Аз: grep + view target секция в sandbox
3. Аз: пиша промяната локално (Python скрипт за edits)
4. Аз: git add + commit + push
5. Аз: давам ти команда между ═══:

   ═══════════════════════════════════════════
   cd /var/www/runmystore && git pull origin main
   ═══════════════════════════════════════════

6. Ти: paste, refresh браузъра, feedback (ОК / screenshot / описание)
7. Аз: fix → или следващ блок
```

При визуално счупване → emergency revert:
```bash
cd /var/www/runmystore && git reset --hard pre-S141-<step> && git push origin main --force
```

---

# 14. ИЗВЕСТНИ BUGS И ОТВОРЕНИ ВЪПРОСИ

## 14.1 От `docs/KNOWN_BUGS.md` (S140 EOD, 11.05.2026)

### 🐛 BUG #1: Brand shimmer не работи в life-board.php
- **Severity:** Cosmetic
- **Status:** Unsolved
- **Симптом:** `.rms-brand .brand-1` shimmer animation работи в chat.php (P11), не в life-board.php (P10). И двата имат identical CSS.
- **Влияние върху products.php:** Косвено. Ако ползваме същия brand pattern в hедъра на products.php, проверявай дали анимацията тече.

### 🐛 BUG #2: Feedback бутони (👍👎❓) не записват в DB
- **Severity:** Medium
- **Status:** Unsolved
- **Симптом:** `lb-fb-btn` визуално работи (selected class toggle), но няма AJAX save → AI brain няма обратна връзка.
- **Влияние върху products.php:** Косвено. AI insights в P15 (AI вижда секцията) ще имат feedback бутони → ще трябва същия endpoint `insights-feedback.php` + DB schema `ai_insight_feedback`.

## 14.2 От `SESSION_48_HANDOFF.md` — products P0/P1 списък

### P0 (blockers)
- **sold_30d постоянен fix** — НЕ Е ЗАПОЧНАТО
- **Бутон "Чакащи потвърждение"** — НЕ Е ТЕСТВАНО
- **Етикет за произход — AI от снимка на фабричен етикет** — НЕ Е ЗАПОЧНАТО (wizard Section 4 extension)
  - Wizard стъпка 1: 2 снимки (стока + фабричен етикет)
  - Gemini Vision чете от етикет: composition + origin_country
  - Auto-populates polя

### P1 (open)
- **Категории дедупликация** — НЕ
- **Копирай/Деактивирай реални endpoints** — НЕ (placeholder функции в products.php 5052-5053)
- **Voice input redesign 🎤** до всяко поле — done за wizard, но търсене още има старо UI
- **Page transition performance** 3-4 сек — НЕ optimized
- **Supplier address/city в UI** — DB колоните вече има, UI липсва

## 14.3 От `EOD_HANDOFF_S95.md` — products module state

**P0 BLOCKERS ЗА BETA** (продуктови):
- (тук ще се обнови след scan на S95 docs за detailed list — за следваща версия на този документ)

## 14.4 Открит конфликт (изисква Тих решение)

**P2 mockup status:**
- HANDOFF_FINAL_BETA.md (08.05) маркира P2 като **"стар home (legacy reference)"**
- PREBETA_MASTER_v2.md (11.05) изисква P2 като **detailed home target** (задача 1.1.2)

**Решение pending:** P2 е canonical за detailed home или само reference?

## 14.5 Voice — "едно и петдесет" → 1.50 fail

От PROMPT_TOMORROW_S99_VOICE DoD list:
- "едно и петдесет" → `1.50` ✅ → **днес fail-ва**

Тих работи цял ден да нагласи voice. S99 е планираният fix цикъл. До тогава, edge case "и петдесет" може да върне грешно число.

---

# 15. ФАЙЛ ТОПОЛОГИЯ

**Кои файлове участват в products модула.** Reference за бъдещ Claude — къде да гледа.

## 15.1 Главен файл

```
/var/www/runmystore/products.php  (14,074 реда — PHP + HTML + CSS + JS в един)
```

## 15.2 Sacred dependencies (не пипай)

```
/var/www/runmystore/services/voice-tier2.php       (333 реда — Whisper Tier 2 Groq client)
/var/www/runmystore/ai-color-detect.php            (296 реда — Gemini Vision color detect)
/var/www/runmystore/tools/voice-lab.php            (629 реда — voice testing tool, admin)
/var/www/runmystore/voice-tier2-test.php           (voice test endpoint)
/var/www/runmystore/js/capacitor-printer.js        (2097 реда — DTM + D520 BT bridge)
```

## 15.3 AI Studio integration

```
/var/www/runmystore/ai-studio.php                  (AI Studio standalone — opens from P13 Section 5)
/var/www/runmystore/ai-studio-backend.php          (AI Studio backend)
/var/www/runmystore/ai-image-credits.php           (credits quota helper)
/var/www/runmystore/cron/ai-studio-queue-worker.php  (bulk magic worker, P8c queue)
```

## 15.4 Helpers / config

```
/var/www/runmystore/config/database.php            (DB::run() singleton)
/etc/runmystore/db.env                              (DB creds, chmod 600)
/etc/runmystore/api.env                             (GEMINI_API_KEY, GROQ_API_KEY, chmod 600)
/var/www/runmystore/services/duplicate-check.php   (product duplicate detection)
/var/www/runmystore/services/ocr-router.php        (delivery invoice OCR)
/var/www/runmystore/products_fetch.php             (paginated products fetcher)
/var/www/runmystore/product-save.php               (legacy save endpoint)
/var/www/runmystore/biz-coefficients.php           (auto-pricing helper)
/var/www/runmystore/biz-compositions.php           (composition templates)
```

## 15.5 Mockups (canonical visual references)

```
/var/www/runmystore/mockups/P15_products_simple.html     (1332 реда — simple home for seller)
/var/www/runmystore/mockups/P3_list_v2.html              (1921 реда — list view universal)
/var/www/runmystore/mockups/P2_home_v2.html              (1729 реда — detailed home [legacy?])
/var/www/runmystore/mockups/P13_bulk_entry.html          (1107 реда — wizard accordion)
/var/www/runmystore/mockups/P12_matrix.html              (467 реда — variations overlay)

/var/www/runmystore/mockups/ai_studio_FINAL_v5.html      (AI Studio per-product modal)
/var/www/runmystore/mockups/ai-studio-main-v2.html       (AI Studio standalone)
/var/www/runmystore/mockups/ai-studio-categories.html    (P8c queue overlay)
/var/www/runmystore/mockups/P8b_advanced_clothes.html    (category-specific AI Studio)
/var/www/runmystore/mockups/P8b_advanced_acc.html
/var/www/runmystore/mockups/P8b_advanced_jewelry.html
/var/www/runmystore/mockups/P8b_advanced_lingerie.html
/var/www/runmystore/mockups/P8b_advanced_other.html
```

## 15.6 Documentation sources

```
/var/www/runmystore/HANDOFF_FINAL_BETA.md             (08.05 — основен handoff)
/var/www/runmystore/PRODUCTS_BULK_ENTRY_LOGIC.md      (35KB железна wizard spec)
/var/www/runmystore/docs/PRODUCTS_DESIGN_LOGIC.md     (57KB design logic v1)
/var/www/runmystore/docs/PROMPT_TOMORROW_S99_VOICE.md (voice S99 plan)
/var/www/runmystore/AI_STUDIO_LOGIC_DELTA.md          (AI Studio v1.1 changes)
/var/www/runmystore/SIMPLE_MODE_BIBLE.md              (simple mode философия)
/var/www/runmystore/INVENTORY_HIDDEN_v3.md            (confidence_score logic)
/var/www/runmystore/PREBETA_MASTER_v2.md              (PK only — задачи)
/var/www/runmystore/docs/S140_FINALIZATION.md         (Universal UI Laws §2)
/var/www/runmystore/docs/KNOWN_BUGS.md                (нерешени bugs)
/var/www/runmystore/CLAUDE_AUTO_BOOT.md               (boot скрипт)
/var/www/runmystore/COMPASS_APPEND_S140.md            (last EOD)
```

## 15.7 DB schema files

```
/var/www/runmystore/db/migrations/2026_05_p13_bulk_entry.sql   (Phase B — TBD)
/var/www/runmystore/migrations/*.sql                            (legacy migrations)
```

## 15.8 Design system (sacred — locked)

```
/var/www/runmystore/design-kit/                                 (13 locked files)
/var/www/runmystore/design-kit/check-compliance.sh              (gate — exit 0 required)
/var/www/runmystore/design-kit/PROMPT.md                        (design prompt)
/var/www/runmystore/design-kit/theme-toggle.js                  (light/dark)
/var/www/runmystore/DESIGN_SYSTEM_v4.0_BICHROMATIC.md            (Bible)
```

## 15.9 Test tenant

```
ENI (tenant_id = 7)
5 магазина:
  - ENI · Витоша 25
  - ENI · Студентски град
  - ENI · Младост
  - ENI · Овча купел
  - ENI · Люлин
```

## 15.10 Mobile testing

```
Samsung Z Flip6 (cover display ~373px, main display ~860×2376)
Capacitor APK build via:
  cd /var/www/runmystore/capacitor && npx cap sync android && npx cap build android
```

---

# 🏁 КРАЙ НА PRODUCTS_MASTER.md v1.0

**Total size:** ~85KB | **Total sections:** 15
**Sources merged:** 11 файла + products.php inspection
**Created:** 2026-05-12 (шеф-чат Opus 4.7)

## История на промените

| Версия | Дата | Промени |
|---|---|---|
| v1.0 | 2026-05-12 | Първоначално създаване — обединение на HANDOFF_FINAL_BETA + PRODUCTS_BULK_ENTRY_LOGIC + voice/color sacred + PREBETA tasks. ЧАСТИ 1-4 в 4 commits. |

## Бъдещи промени (when needed)

- Section 11.2 — финализация на home/list i18n keys при S141 implementation
- Section 14.3 — обнови от EOD_HANDOFF_S95 след scan
- Section 14.4 — Тих решение за P2 status (legacy или canonical detailed home)
- Section 13.3 — детайлни планове за стъпки 3-7 при достигането им

---

**EOF**
