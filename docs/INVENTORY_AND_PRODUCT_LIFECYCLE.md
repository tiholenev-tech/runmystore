# 📦 INVENTORY AND PRODUCT LIFECYCLE — Един source of truth

**Версия:** 1.0
**Дата:** 14.05.2026 (S144)
**Цел:** Един документ който обобщава: откъде се добавят артикули, как се пресмята колко знаем за всеки артикул, кога е "преброен", кога не е.

## ⚠️ ВАЖНО — НИКОЙ ФАЙЛ НЕ Е ИЗТРИТ

Този документ **обобщава** на едно място само две конкретни теми (формулата за точките + нивата). Всички останали стари документи **остават валидни** и трябва да се четат за останалите теми.

### Кой документ за какво се чете

| Тема | Файл | Секция |
|---|---|---|
| **Формула за точките + 3-те нива** | `docs/INVENTORY_AND_PRODUCT_LIFECYCLE.md` (този файл) | §3, §4 |
| Философия "складът се изгражда сам" | `INVENTORY_HIDDEN_v3.md` | §1 |
| Onboarding flow (zone setup, photos) | `INVENTORY_HIDDEN_v3.md` | §3 |
| Day-1 selling without inventory | `INVENTORY_HIDDEN_v3.md` | §4 |
| Statistics with ranges (диапазони) | `INVENTORY_HIDDEN_v3.md` | §5 |
| Delivery → category count trigger | `INVENTORY_HIDDEN_v3.md` | §6 |
| Zone Walk — "Лов на скрити пари" | `INVENTORY_HIDDEN_v3.md` | §7 + `01_corrections/DOC_11_INVENTORY_WAREHOUSE.md` §5 |
| Data reconciliation (3-те условия) | `INVENTORY_HIDDEN_v3.md` | §8 |
| Self-correcting sales loop | `INVENTORY_HIDDEN_v3.md` | §9 |
| Store Health Score (3 компонента) | `INVENTORY_HIDDEN_v3.md` | §10 |
| Decay + mini-revisions | `INVENTORY_HIDDEN_v3.md` | §11 |
| "Hunting for lost money" (психология) | `INVENTORY_HIDDEN_v3.md` | §12 |
| Variations (3 states) | `INVENTORY_HIDDEN_v3.md` | §13 |
| Timeline 30 дни типичен магазин | `INVENTORY_HIDDEN_v3.md` | §14 |
| Technical notes (DB schema, PHP функции) | `INVENTORY_HIDDEN_v3.md` | §15 |
| Modules affected (кой модул засяга кое) | `INVENTORY_HIDDEN_v3.md` | §16 |
| 10-те принципа | `INVENTORY_HIDDEN_v3.md` | §17 + `01_corrections/DOC_11_INVENTORY_WAREHOUSE.md` §4, §10 |
| Какво НЕ може да се реши | `INVENTORY_HIDDEN_v3.md` | §18 |
| Inventory v4 — бърз vs пълен режим | `INVENTORY_v4.md` | §2 |
| Inventory v4 — броене flow | `INVENTORY_v4.md` | §4 |
| Inventory v4 — проверки за качество | `INVENTORY_v4.md` | §5 |
| Inventory v4 — дефектна стока | `INVENTORY_v4.md` | §8 |
| Inventory v4 — кога е свършила инвентаризацията | `INVENTORY_v4.md` | §9 |
| Warehouse.php hub (6 cards) | `01_corrections/DOC_11_INVENTORY_WAREHOUSE.md` | §2 |
| Smart Resolver (deduplication) | `01_corrections/DOC_11_INVENTORY_WAREHOUSE.md` | §7 |
| Ревизия (за счетоводител, PDF/Excel) | `01_corrections/DOC_11_INVENTORY_WAREHOUSE.md` | §9 |
| Quick Add — единен entry point | `SIMPLE_MODE_BIBLE.md` | §8.1, §8.2, §8.3 |
| NO draft / parking lot принцип | `SIMPLE_MODE_BIBLE.md` | §8.5 |
| Variation handling при продажба | `SIMPLE_MODE_BIBLE.md` | §9 |
| Wizard "Копирай предишния" | `SIMPLE_MODE_BIBLE.md` | §7.2.8, §7.2.8.5 |
| Wizard стъпки (4-7 stepped) | `01_corrections/DOC_08_PRODUCTS.md` | §4 + `PRODUCTS_DESIGN_LOGIC.md` |
| Voice add poetapno flow | `SIMPLE_MODE_BIBLE.md` | §7.2.5, §7.2.6 |
| Sale unknown product flow | `SIMPLE_MODE_BIBLE.md` | §7.1.5 + `INVENTORY_v4.md` §6.6 |
| Delivery — 3 типа фактури | `01_corrections/DOC_10_SALE_DELIVERIES_TRANSFERS.md` | §5 + `SIMPLE_MODE_BIBLE.md` §7.3 |
| Категории — глобални vs по доставчик | `CORE_BUSINESS_RULES.md` | Правило #1 |
| Wizard редизайн план (S144) | `TOMORROW_WIZARD_REDESIGN.md` | целия |
| AI auto-fill стратегия + Deep Research | `MASTER_COMPASS.md` (S143 секция) + `docs/AI_AUTOFILL_RESEARCH_2026.md` | — |

### Какво заменя този документ — точно

| Стара секция | В кой файл | Защо е заменена |
|---|---|---|
| Таблицата с 4 нива (Минимално / Частично / Добро / Пълно) | `INVENTORY_HIDDEN_v3.md §2 Levels` | Минаваме на **3 нива** за да отговарят на 3-те секции в wizard акордеона |
| Таблицата "Confidence стартова стойност по място" (20/50/60/80%) | `SIMPLE_MODE_BIBLE.md §8.4` | Имаше различни цифри от формулата → конфликт |

**Всичко останало в тези два файла остава валидно.** Само тези две таблици са преместени тук.

---

## 1. КАКВО Е CONFIDENCE — С ПРОСТИ ДУМИ

Всеки артикул има невидимо число от 0 до 100. Колкото повече знаем за него — толкова по-голямо. **Пешо никога не вижда числото.** Вижда само следствията — например AI казва "печалбата е между 200 и 400 лв" вместо "печалбата е 312 лв".

Числото казва **колко да вярваме на данните** за този артикул.

---

## 2. ОТКЪДЕ СЕ ДОБАВЯТ АРТИКУЛИ — 6 ПЪТЯ

| # | Откъде | Кога | Стартова стойност |
|---|---|---|---|
| 1 | **sale.php** — продажба на непознат баркод | Клиент пред касата, артикул няма в базата. Пешо казва име + цена. | 20 (само име+цена) |
| 2 | **products.php voice add** — с глас | Пешо натиска микрофона "тениска черна L 30 евро от Marina" | 35-50 (име+цена+доставчик+категория) |
| 3 | **products.php wizard** — пълен пълно с ръка | Митко добавя на спокойствие, попълва всичко | 50-80 (зависи колко полета попълни) |
| 4 | **products.php "Копирай предишния"** | Eдин артикул вече добавен, нов подобен (същ доставчик/категория) | 50-70 (от копираните полета) |
| 5 | **inventory.php бързо добавяне** — по време на броене | Пешо брои, среща непознат → бързо мини-добавяне | 40-60 (име+цена+физическо потвърждение) |
| 6 | **deliveries.php OCR** — снимай фактура | Доставка дойде, Пешо снима фактурата | 60-80 (име+цена+доставчик+доставна+бройки+категория) |

**Всичките 6 пътя създават нормален `products` ред.** Няма "draft" или "pending" статус. Артикулът съществува или не съществува. Между двете — `confidence_score` казва колко довършен е.

---

## 3. ФОРМУЛАТА ЗА ТОЧКИТЕ — ОБНОВЕНА (заменя `INVENTORY_HIDDEN_v3.md §2`)

> **ПРАВИЛО #49 (S143):** Преброяване ≠ Информация. Това са **две различни концепции**.
> Confidence score (тази формула) измерва **колко знаем за артикула** (информация). Преброяването е **отделен axis** (показва се самостоятелно — "Не е броен · X дни").

| Какво | Точки | Защо |
|---|---|---|
| Име + Цена | **20** | База. Без тях не може да съществува артикул. |
| **Бройки (Пешо казал колко има)** | **+15** | Без бройки AI не може да каже "поръчай" или "застой". |
| Снимка (включва AI auto-fill: gender, season, brand, description_short) | **+30** | Една снимка → AI попълва 4 полета безплатно. Огромна стойност. |
| Доставчик + Категория | **+10** | За поръчки, филтри, supplier reliability. |
| Доставна цена | **+20** | Без нея няма марж/печалба. Критично за бизнеса. |
| Баркод/SKU | **+5** | За скан в sale.php. |
| **Max** | **100** | |

**Разлика "бройки" vs "преброен":**
- **Бройки (+15)** = Пешо казва "12 броя" при добавяне. *Обявени*. Влиза в confidence score.
- **Преброен** = Пешо отишъл на рафта, физически броил. *Проверени*. **НЕ влиза в confidence score** — показва се отделно (`inventory.last_counted_at` визуализирано като "Не е броен · X дни" warning).

**Защо +30 за снимка (увеличено от +10):** Снимката носи 9 полета наведнъж от AI auto-fill — стойността ѝ е огромна. Преди формулата беше неуравновесена (преброеният давaше +20, а снимка с 9 AI полета само +10). Сега снимката получава реалната си тежест.

---

## 4. ТРИТЕ НИВА — ЕДИНСТВЕНАТА ПРОМЯНА СПРЯМО СТАРИТЕ 4

| Ниво | Bracket | Какво знае AI |
|---|---|---|
| 🔴 **Минимална** | 0–39 | Само име и цена. Може би снимка. AI няма какво да каже. |
| 🟡 **Частична** | 40–79 | Има бройки, доставчик, може и доставна цена. AI може да даде диапазон ("печалба между 180 и 340 лв"). |
| 🟢 **Пълна** | 80–100 | Всичко. AI знае точно. Може да каже "поръчай 5 нови", "продаде 12 този месец, печалба 240 лв". |

**Защо 3 нива** (не 4): UI акордеонът в "Добави артикул" wizard има 3 секции (Задължителни / Препоръчителни / AI Studio). Confidence нивата трябва да отговарят на UI стъпките.

---

## 5. AI AUTO-FILL — ВКЛЮЧЕНО В "СНИМКА"

Като Пешо снима артикул, AI прави **едно безплатно обаждане** и попълва наведнъж:

| Поле | Тип | DB колона |
|---|---|---|
| Категория (предложение) | Suggest | `products.category_id` |
| Подкатегория (предложение) | Suggest | `products.subcategory_id` |
| Цвят | Detect | `products.color` |
| Размер (ако се вижда на етикета) | Detect | (variations) |
| Материя/състав | Detect | `products.composition` |
| Пол | ENUM | `products.gender` ✅ S143 |
| Сезон | ENUM | `products.season` ✅ S143 |
| Марка (от лого) | Text | `products.brand` ✅ S143 |
| Кратко описание (20-50 думи) | Text | `products.description` (съществува) |

**Тези не дават отделни точки** — те са включени в "+10 за снимка". Логиката: ако има снимка → AI попълва. Снимка без AI = безполезна. AI без снимка = невъзможно.

**Платено** (отделно AI обаждане, по натискане на бутон):
- Дълго описание (100-200 думи маркетингов език) → нова колона `products.description_long`

---

## 6. КОГА АРТИКУЛ Е "ПРЕБРОЕН" ИЛИ "НЕПРЕБРОЕН" (отделно от confidence)

`inventory.last_counted_at` (DATETIME, NULL ако никога не е броен) — добавена в S143 миграцията.

**ВАЖНО:** Преброяването **НЕ влиза в confidence score** (правило #49). То е **отделен axis** който се визуализира самостоятелно в UI:

- **Преброен ≤ 30 дни** → "✓ Броено · преди X дни" (ok) — зелено
- **Преброен 30-60 дни** → "⊘ Не е броено · X дни" (warn) — амбър
- **Никога преброен / > 60 дни** → "⚠ Никога не е броено" (danger) — червено

**Защо per-store** (а не per-product): артикул може да е броен в София но не и в Бургас. Преброено е *в магазин*, не *в общо*.

**Къде се вижда в UI:**
- Detail drawer — под бройките (meta line)
- Списък — chip "не е броено · 47 дни"
- Zone walk модул — приоритизира артикули с най-стар last_counted_at

---

## 7. SELF-CORRECTING LOOP — ЗАЩО ТОЧКИТЕ САМИ СЕ ПОПРАВЯТ

(От `INVENTORY_HIDDEN_v3 §9` — остава както е, повтарям накратко.)

Дори ако Пешо лъже при броене → продажбите разкриват истината:

- Пешо казал 5, но продал 6 без доставка → "Бройката не беше точна. Колко имаш реално?"
- Пешо казал 5, нищо не продадено 60 дни → "Сигурен ли си че имаш 5?"

Всяка продажба, всяка доставка → шанс да се поправи confidence без нова инвентаризация.

---

## 8. DB SCHEMA — ТЕКУЩО + ДОБАВКИ

**Съществува (S31 + S143):**
- `products.confidence_score` TINYINT UNSIGNED DEFAULT 0 (S31)
- `products.has_physical_count` TINYINT(1) DEFAULT 0 (S31)
- `products.gender` ENUM (S143)
- `products.season` ENUM (S143)
- `products.brand` VARCHAR(80) (S143)
- `inventory.last_counted_at` DATETIME (S143)
- `products.description` VARCHAR/TEXT (стара, ползва се за кратко описание)
- `products.composition`, `products.origin_country`, `products.color` (стари)

**Предстои да се добави в S144:**
- `products.description_long` TEXT NULL — за платено AI маркетингово описание
- (по желание) `products.counted_via` ENUM('zone_walk','delivery','mini_revision','sale_correction') — за audit trail на броенето

---

## 9. BACKFILL — ЗА 82-те АРТИКУЛА СЪС `SCORE = 0`

Тези артикули имат score 0 защото никога не е смятан (DEFAULT 0 от ALTER TABLE). SQL изчислява точките по формулата по-горе от съществуващите им полета:

```sql
-- Backfill confidence_score за артикули с score=0
-- Изчислява точките по формулата от §3 (правило #49: БЕЗ преброен)
UPDATE products p
SET confidence_score = (
    -- База: име + цена (винаги има за активни)
    20
    -- + бройки (от inventory)
    + IFNULL((SELECT IF(SUM(i.quantity) > 0, 15, 0)
              FROM inventory i WHERE i.product_id = p.id), 0)
    -- + снимка (включва AI auto-fill — 9 полета наведнъж)
    + IF(p.image_url IS NOT NULL AND p.image_url != '', 30, 0)
    -- + доставчик + категория (двете заедно)
    + IF(p.supplier_id IS NOT NULL AND p.category_id IS NOT NULL, 10, 0)
    -- + доставна цена
    + IF(p.cost_price IS NOT NULL AND p.cost_price > 0, 20, 0)
    -- + баркод или SKU
    + IF((p.barcode IS NOT NULL AND p.barcode != '')
         OR (p.code IS NOT NULL AND p.code != ''), 5, 0)
    -- Преброеността НЕ участва (правило #49)
)
WHERE p.tenant_id = 7
  AND p.is_active = 1
  AND p.parent_id IS NULL
  AND p.confidence_score = 0;
```

След това: ENI tenant ще има реалистично разпределение по 3-те нива вместо 82 артикула на 0.

---

## 10. ОТРАЗЯВАНЕ В UI — КЪДЕ СЕ ВИЖДА CONFIDENCE

**4 места в момента:**

1. **Wizard акордеон** в `products.php` Step 2 → 3 секции (Цени / Детайли / AI Studio) — отговаря на 3-те нива.
2. **Filter drawer** в `products-v2.php` ред 4250 → секция "Информация":
   - Старо: `Пълна` / `Чака допълване` (2 нива)
   - Ново: `Пълна` / `Частична` / `Минимална` (3 нива)
3. **Info-box v2** (S143 pending) → 3 нива badge + малък прогрес-бар per артикул в списъка.
4. **Store Health** в `config/helpers.php` ред 287 → AVG(confidence_score) — без промяна, само UI labels.

---

## 11. КАКВО НЕ ПРАВИМ — ЗАБРАНИ

- ❌ **Не показваме числото на Пешо.** Само следствия (диапазони, AI въпроси).
- ❌ **Не правим отделна `products_draft` таблица.** Артикул със score 20 е нормален `products` ред.
- ❌ **Не блокираме продажба** заради нисък confidence.
- ❌ **AI не създава нова категория сам** (Rule #38). Само предлага от съществуващите.
- ❌ **Не правим decay по-агресивен от 5%/седмица** — рискуваме визуален шок.

---

## 12. РЕЗЮМЕ ЗА БЪДЕЩИ ШЕФ-ЧАТОВЕ

Ако някой шеф-чат пита **"как пресмятаме confidence?"** или **"колко нива има?"** → отговор: §3 и §4 на ТОЗИ файл.

За всичко друго свързано с инвентаризация, добавяне на артикули, zone walks, store health, и т.н. → виж **картата с reference-и в началото** на този файл (секция "Кой документ за какво се чете"). Старите документи **остават валидни** и **не са изтрити**.

---

**КРАЙ.**
