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
