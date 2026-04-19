# SESSION 77 HANDOFF v2
## RunMyStore.ai | 19.04.2026
## Тип: DESIGN + ARCHITECTURE SESSION (Артикули + Поръчки + Неизпълнени задачи)
## Модел: Claude Opus 4.7
## Git: последен S76 commit `07381e9` (преди S77 дизайн работа)

---

# 🎯 ОБОБЩЕНИЕ НА S77

S77 е **дизайн и архитектурна сесия**, не код. Решения за:
1. **6-те фундаментални въпроса** като закон за всички модули
2. **Нова главна на products.php** — артикул-центрична с 6 секции
3. **Нов модул orders.php** — ЦЯЛА екосистема (не единичен модул)
4. **Warehouse.php = hub** за всички складови подмодули
5. **Lost demand интеграция** — sale.php → orders.php → ROI tracking

**Създадени artifacts:** 10 HTML mockups във външен design чат.

---

# 🎯 ПРИОРИТЕТ: PRODUCTS.PHP ПЪЛНО ЗАВЪРШВАНЕ ПЪРВО

**Решение от 19.04.2026:** Преди да се започне orders.php, products.php трябва да е напълно готов със ВСИЧКИ модули, функции и интеграции. Частичен products.php, който работи наполовина, ще спъва всички следващи модули (защото те четат/пишат в него).

## Какво значи „пълно завършване" на products.php

1. ✅ Работещ списък с филтри (expanded filter drawer)
2. ✅ Работещ wizard (4 стъпки, от всички пътища)
3. ✅ 3 флоу на добавяне: AI voice / Ръчно / CSV Import
4. ✅ AI Image Studio (fal.ai) — „бял фон" + „на модел"
5. ✅ AI от снимка на фабричен етикет (Gemini Vision)
6. ✅ Bluetooth print на етикети (DTM-5811)
7. ✅ Главна страница със 6-те фундаментални въпроса
8. ✅ compute-insights.php функции за products модул
9. ✅ 6-те въпроса tag-нати в DB (ai_insights.fundamental_question)
10. ✅ Всички P0 бъгове от S71-S73A затворени
11. ✅ Voice overlay (S56 стил) навсякъде

**Разбивка в 5 сесии:** S78 → S82.

---

# 📚 ЗАКОНИ — НОВИ ПРАВИЛА ОТ S77

## ПРАВИЛО S77.1 — 6-те фундаментални въпроса (ЗАКОН)

Всеки модул задължително структуриран около:

1. 🔴 **Какво губя?** (Loss)
2. 🟣 **От какво губя?** (Loss Cause)
3. 🟢 **Какво печеля?** (Gain)
4. 🔷 **От какво печеля?** (Gain Cause)
5. 🟡 **Какво да поръчам?** (Order)
6. ⚫ **Какво да НЕ поръчам?** (Anti-Order)

Цветно кодиране:
- Loss = red (hue 0°) · Cause = violet (hue 280°) · Gain = green (hue 145°)
- Gain Cause = teal (hue 175°) · Order = amber (hue 38°) · Anti = grey (hue 220°)

**Приоритет:** Loss (1+2) > Gain (3+4). Anti-Order (6) > Order (5).

## ПРАВИЛО S77.2 — Profit, не оборот

Всяко число в UI = profit. Забранено „оборот", „марж".

## ПРАВИЛО S77.3 — Артикул-центричност

Записите са **артикули**, не доставчик/категория. Изключение: orders.php.

## ПРАВИЛО S77.4 — Доставчик/категория = филтри, не навигация

## ПРАВИЛО S77.5 — Склад Hub архитектура

```
Bottom nav: AI · Склад · Справки · Продажба
              ↓
       warehouse.php (hub)
              ↓
 Артик.  Дост.  Поръч.  Трансф.  Инвент.
```

## ПРАВИЛО S77.6 — ai_insights.fundamental_question колона задължителна

---

# 🔴 PRODUCTS.PHP — ПЪЛНА СПЕЦИФИКАЦИЯ

## 1. ГЛАВНА СТРАНИЦА (products.php home view)

### Структура

```
Header (☰ · LOGO · Магазин ▾ · PRO · ⚙)
Title: Артикули · N шт.
Search: [🔍____ [⚙badge] [🎤]]
Добави card: [+ Добави] [🎤] [✏️] [⋯]

═══ 6 СЕКЦИИ (h-scroll per секция) ═══

🔴 Какво губиш          −340 лв/седм
[H-scroll 4-8 артикули с контекст]

🟣 От какво губиш         −180 лв
🟢 Какво печелиш         +2 840 лв
🔷 От какво печелиш       4 причини
🟡 Какво да поръчаш       28 арт. → orders.php
⚫ Какво да НЕ поръчаш    9 · 2 480 лв

[Виж всички N артикула →]  ← full list page
Bottom nav
```

### Секция pattern

- **Q-head**: badge (1-6, цветен gradient) + заглавие + подзаглавие + total pill
- **Horizontal scroll** 4-8 артикули, 162px wide
- Всеки артикул:
  - Снимка/SVG силует в цветна рамка
  - Tag в ъгъла (0 БР / #1 / 24 / 78д / −8%)
  - Име (2 реда max)
  - Цена + наличност row
  - **Context line** (divider): число + защо + profit

### Контекстни текстове per секция (референции)

**🔴 Какво губиш:** „3 търсения /7д · **~360 лв profit/мес пропуснат**"

**🟣 От какво губиш:** „Доставна 70 лв · **продаваш на загуба**"

**🟢 Какво печелиш:** „18 прод · **+840 лв profit**"

**🔷 От какво печелиш:** „Най-висок марж · **58% профитност**"

**🟡 Какво да поръчаш:** „Топ №1 · **поръчай 24 бр**"

**⚫ Какво да НЕ поръчаш:** „78 дни · **240 лв замразени**"

## 2. СПИСЪК (full list page)

- Header с back button
- Filter drawer бутон + search + sort
- Sort: Най-нови / По име / По цена / По profit
- Карти с всички данни
- Pagination / infinite scroll
- „⚠ недовършен" / „🔴 Губиш" / „🟢 Топ" / „⚫ Zombie" тагове

## 3. WIZARD — 4 СТЪПКИ

### Мапване 7→4

| Стара | Нова |
|---|---|
| 1. Вид | 0. Vid |
| 2. Снимка + 3. Основни | **1. Основни (+снимка)** |
| 4. Варианти1 + 5. Варианти2 | **2. Варианти (matrix overlay)** |
| 6. Доставчик + 7. Преглед | **3. Доставчик (+AI)** |

### Поле ред (критично)
```
Снимка → Доставчик → Категория → Подкатегория → Цена → Брой
```
Доставчик ПРЕДИ категория — supplier_categories filter.

### Минимален запис
**НЯМА „чернова"** — минимумът (име+цена+брой) създава истински продукт. „⚠ недовършен" pill → tap отваря wizard.

### Печат
Отделна страница достъпна от ВСЯКА стъпка чрез [🖨].

### Matrix overlay (S73.C)
- Fullscreen
- Qty/Min per cell
- autoMin формула: `Math.round(qty/2.5)` min 1
- ▲▼ бутони
- Зелено/червено при съвпадение/разлика

## 4. 3 ФЛОУ НА ДОБАВЯНЕ

### 4.1 AI Wizard (voice, primary)
Пешо говори → AI попълва → confirm. 4 voice въпроса + транскрипция + Confirm.

### 4.2 Ръчен wizard (fallback)
Keyboard + taps. 4 стъпки. Voice 🎤 до всяко поле.

### 4.3 CSV/Excel Import
Preview таблица + AI auto-detect колони + bulk insert.

## 5. AI IMAGE STUDIO (fal.ai)

- [🪄 Махни фон — €0.05] birefnet/v2
- [👤 На модел — €0.50] nano-banana-pro/edit
- Лимити: FREE 0, START 3/ден, PRO 10/ден

## 6. AI ОТ СНИМКА НА ФАБРИЧЕН ЕТИКЕТ

2 снимки в стъпка 1:
1. Стоката
2. Фабричен етикет (optional)

Gemini Vision → composition + origin_country + размер (ако видим).

## 7. BLUETOOTH PRINT (DTM-5811)

- Pair в Settings (PIN 0000)
- Web Bluetooth API + TSPL protocol
- 50×30mm labels
- Format: €+лв / само € / без цена
- [x2 / 1:1], [Печатай всички]

## 8. EXPANDED FILTER DRAWER

Секции:
1. **Класификация**: Доставчик → Категория → Подкатегория (drill-down)
2. **Цени и наличност**: Цена от-до, Наличност (Всички/Има/Нула/Преброени/Непреброени)
3. **Проблеми**: Сигнали (zombie, under-margin, under-cost, missing data)
4. **Специални**: Toggles (Zombie, Top seller, Промоция, Без снимка, Без баркод)

Active filter badge [⚙N] до search.

## 9. VOICE OVERLAY (standard S56)

Навсякъде в products.php:
- `rec-ov` backdrop blur 8px
- `rec-box` floating bottom, border-radius 20px, indigo glow
- Голяма червена пулсираща точка 48px
- „● ЗАПИСВА" / „✓ ГОТОВО"
- Transcript + „Изпрати →"

## 10. compute-insights.php ФУНКЦИИ ЗА PRODUCTS

15 функции, маркирани с fundamental_question:

**loss**: zero_stock_with_sales · below_min_urgent · running_out_today  
**loss_cause**: selling_at_loss · no_cost_price · margin_below_15 · seller_discount_killer  
**gain**: top_profit_30d · profit_growth  
**gain_cause**: highest_margin · trending_up · loyal_customers · basket_driver · size_leader  
**order**: bestseller_low_stock · lost_demand_match  
**anti_order**: zombie_45d · declining_trend · high_return_rate

---

# 🔴 НЕИЗПЪЛНЕНИ ЗАДАЧИ

## 🔥 P0 БЪГОВЕ В PRODUCTS.PHP (S71-S73A)

### Бъг #5: AI Studio _hasPhoto
**Файл**: products.php `wizPhotoUpload()` ред ~3200
**Fix**: `S.wizData._hasPhoto = true;` след FileReader `onload`
**Сесия**: S78

### Бъг #6: renderWizard нулира бройки
**Fix A**: `wizCollectData()` в началото на renderWizard() ако step===6
**Fix B**: `wizGenDescription()` само обновява textarea
**Сесия**: S78

### Бъг #7: sold_30d = 0
**Файл**: products.php `listProducts` ред ~850
**Fix**: LEFT JOIN със sale_items aggregated subquery
**Сесия**: S78

## 🔥 P0 FEATURES ЗА PRODUCTS.PHP

| # | Feature | Сесия |
|---|---|---|
| 1 | Phase 0 Engine (ai_insights + всички S77 таблици) | S78 |
| 2 | products главна rewrite (6 секции) | S79 |
| 3 | Wizard rewrite 4 стъпки + matrix overlay | S80 |
| 4 | AI Wizard voice | S81 |
| 5 | AI фабричен етикет (Gemini Vision) | S81 |
| 6 | AI Image Studio (fal.ai) | S81 |
| 7 | Expanded filter drawer | S82 |
| 8 | CSV Import flow | S82 |
| 9 | Bluetooth print DTM-5811 | S82 |

## ⚠️ P1 (отлагат се след products.php complete)

| # | Задача | Сесия |
|---|---|---|
| 1 | Categories дедупликация | S86+ |
| 2 | Copy/Deactivate endpoints | S86+ |
| 3 | Page transition performance (3-4 сек) | S86+ |
| 4 | Supplier address/city UI | S82 (в products) |
| 5 | Batch photo upload + AI match | S83+ |

---

# 🛠️ ПЛАН ЗА PRODUCTS.PHP COMPLETION (S78 → S82)

## S78 (Opus 4.7): Фундамент — DB + P0 бъгове

1. **DB миграция** — всички S77 таблици:
   - ai_insights + fundamental_question ENUM
   - ai_shown, search_log
   - lost_demand с нови колони (suggested_supplier_id, matched_product_id, resolved_order_id, times)
   - supplier_orders + supplier_order_items + supplier_order_events (за бъдеща orders.php)
2. **Бъг #5**: AI Studio _hasPhoto
3. **Бъг #6**: renderWizard нулира бройки
4. **Бъг #7**: sold_30d LEFT JOIN
5. **compute-insights.php skeleton** — hook-нат в products.php

**Deliverable:** DB готов за всичко. products.php без критични бъгове.

## S79 (Opus 4.7): Главна rewrite (6 секции)

1. Нов HTML layout — 6 секции h-scroll
2. Neon Glass CSS за 6 hue-а (q1-q6)
3. JS: горизонтален scroll + context lines + total pills
4. AJAX endpoint `ajax=sections` зарежда per секция
5. compute-insights.php — 15 функции за products
6. Всяка функция маркира fundamental_question
7. Tap на артикул → edit flow

**Deliverable:** Главната изглежда и работи според S77 дизайн.

## S80 (Opus 4.7): Wizard rewrite (4 стъпки + matrix)

1. Мерге на 7→4 стъпки
2. Fullscreen matrix overlay за варианти
3. Field order Supplier → Category
4. Минимален запис (няма чернова flow)
5. „⚠ недовършен" pill в списъка
6. Печат overlay от всяка стъпка

**Deliverable:** Добавянето на артикул тече гладко през 4 стъпки.

## S81 (Opus 4.7): AI Features

1. AI Wizard (voice) — 4 voice въпроса
2. AI от снимка на фабричен етикет (Gemini Vision)
3. AI Image Studio (fal.ai) — махни фон + на модел
4. Voice overlay навсякъде (S56 стил)

**Deliverable:** Пешо може да добавя артикули изцяло с глас + снимка.

## S82 (Opus 4.7): Final Polish

1. Expanded filter drawer (всички 4 секции)
2. CSV Import flow
3. Bluetooth print (DTM-5811)
4. Supplier address/city UI
5. Full list page
6. End-to-end testing
7. Git tag: `v1.0.0-products-complete`

**Deliverable:** products.php готов за production.

---

# 📦 СЛЕД S82 — ORDERS.PHP (ИЗЦЯЛО НОВА ЕКОСИСТЕМА)

**Детайли ще стоят в handoff за S82.**

Кратко резюме:
- DB schema вече готов от S78
- 12 входни точки · 11 типа · 8 статуса · 6 въпроса вградени
- Primary view = по доставчик
- Alt views: по статус, по 6 въпроса, календар
- Lost demand интеграция (AI fuzzy match → supplier)
- Menu: 6 секции (Изгледи/Създай/Справки/Експорт/Настройки/Помощ)

**Сесии:** S83 → S85 (3 сесии).

---

# 📸 HTML MOCKUPS (референции за S79-S82)

От S77 design session:
1. `products-home-6-questions.html` — 6 секции главна
2. `orders-by-supplier-s77.html` — orders primary view
3. `orders-s77.html` — orders alt view
4. `filter-drawer-s77-svg.html` — expanded filter drawer
5. Плюс 6 други итерации

Всички = **reference only** за стил. Логика следва S77 правила.

---

# 📝 ГИТ СТАТУС

```
07381e9 S76 (последен преди S77)
```

S77 е design-only, без commits. S78 започва оттук.

---

# 🚀 СТАРТОВ ТЕКСТ ЗА S78

```
Прочети по ред:
1. OPERATING_MANUAL.md
2. NARACHNIK_TIHOL_v1_1.md
3. BIBLE_v3_0_CORE.md
4. BIBLE_v3_0_TECH.md
5. BIBLE_v3_0_APPENDIX.md (v3.1 — с S77 updates)
6. DESIGN_SYSTEM.md
7. COST_PRICE_INTEGRATION.md
8. ROADMAP.md (провери дали е валиден; ако не — предложи промени)
9. SESSION_77_HANDOFF.md v2 (този файл)

Задачата е S78: PRODUCTS.PHP ФУНДАМЕНТ
1. DB миграция — всички S77 таблици
   (ai_insights + fundamental_question ENUM, ai_shown, search_log, 
    lost_demand с нови колони, supplier_orders, supplier_order_items, 
    supplier_order_events)
2. P0 бъгове products.php (#5, #6, #7)
3. compute-insights.php skeleton

Команди:
cd /var/www/runmystore && git pull origin main
git log --oneline | head -3
```

---

# 🎓 НАУЧЕНИ УРОЦИ ОТ S77

1. **Products.php трябва да завърши преди orders.php.** Orders чете от products — частичен products = частичен orders.

2. **6-те въпроса са закон.**

3. **Артикул-центричност > доставчик-центричност** (освен за orders.php).

4. **Profit, не оборот.**

5. **„Чернова" = обикновен артикул.**

6. **Екосистемата > модула** (orders = 12 входа + 11 типа + 8 статуса).

7. **Roadmap е жив документ.**

---

**КРАЙ НА SESSION 77 HANDOFF v2**
