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

