# 📚 STRESS_SCENARIOS.md — РЕГИСТЪР НА ТЕСТ СЦЕНАРИИТЕ

**Версия:** 1.0  
**Дата създаване:** 08.05.2026  
**Цел:** Растящ списък на всички сценарии които стрес тестът пуска. Расте с проекта. Когато Code чат каже „завърших нова функция", Шеф чатът добавя нов сценарий тук в EOD протокол стъпка 5.

---

## 📐 СТРУКТУРА НА СЦЕНАРИЙ

Всеки сценарий има:

| Поле | Описание |
|---|---|
| **ID** | Уникален (S001, S002, ...) |
| **Дата добавен** | Кога влезе в регистъра |
| **Свързан с** | Модул / commit hash / нов feature |
| **Категория** | sale flow / AI brain / inventory / OCR / voice / cron / UI |
| **Precondition** | Какво трябва да е true преди да започне |
| **Action** | Какво се прави |
| **Expected result** | Какво трябва да стане |
| **Приоритет** | P0 (critical) / P1 (important) / P2 (nice-to-have) |
| **Статус** | active / disabled / under review |
| **Последно пускане** | Дата + резултат (записва се в STRESS_SCENARIOS_LOG.md) |

---

## 🎯 БАЗОВИ REGRESSION СЦЕНАРИИ (P0 — пускат се ВСЯКА НОЩ)

Тези сценарии тестват критичните workflows. Ако някой fail-не → червен ред в MORNING_REPORT.md.

---

### S001 — Пълен sale flow (продажба от край до край)

- **Дата добавен:** 08.05.2026
- **Свързан с:** sale.php
- **Категория:** sale flow
- **Precondition:** STRESS Lab tenant активен, поне 100 артикула в DB, поне 1 потребител логнат
- **Action:**
  1. Симулирай scan на баркод
  2. Добави артикул в количка
  3. Добави втори артикул
  4. Натисни „Плати"
  5. Избери начин на плащане (брой)
  6. Завърши продажбата
- **Expected result:**
  - Записан ред в `sales` таблицата
  - 2 реда в `sale_items`
  - `inventory.quantity` намалено за двата артикула
  - `sales.status = 'completed'`
  - `sales.total` правилно изчислено
- **Приоритет:** P0
- **Статус:** active

---

### S002 — Race condition (двама продавачи едновременно)

- **Дата добавен:** 08.05.2026
- **Свързан с:** sale.php — Бъг 1 от STRESS_BUILD_PLAN
- **Категория:** sale flow / concurrency
- **Precondition:** Артикул X с `inventory.quantity = 1`, двама потребители логнати
- **Action:**
  1. Потребител А отваря продажба, скенира артикул X
  2. Потребител Б отваря продажба, скенира артикул X (СЪЩАТА секунда)
  3. Потребител А завършва продажбата
  4. Потребител Б завършва продажбата
- **Expected result:**
  - **Само единият** трябва да успее
  - Другият получава грешка „Артикулът свърши"
  - `inventory.quantity = 0` (не отрицателно)
- **Текущо състояние:** ⚠️ FAIL — `GREATEST(quantity, 0)` позволява и двете
- **Приоритет:** P0
- **Статус:** active

---

### S003 — products.php wizard 2-стъпков (single product)

- **Дата добавен:** 08.05.2026
- **Свързан с:** products.php S95 wizard
- **Категория:** UI / data entry
- **Precondition:** STRESS Lab tenant, потребител с роля owner
- **Action:**
  1. Open products.php
  2. Натисни „+ Добави артикул"
  3. Избери „Единичен" (не вариации)
  4. Стъпка 1: попълни име, цена, бройка, мин-бройка
  5. Стъпка 2: пропусни (всичко е optional)
  6. Натисни „Запази"
- **Expected result:**
  - Артикулът създаден в `products` таблица
  - `inventory.quantity` правилен
  - `inventory.min_quantity = round(quantity / 2.5)` минимум 1
  - Артикулът се появява в списъка
- **Приоритет:** P0
- **Статус:** active

---

### S004 — products.php wizard 3-стъпков (with variations)

- **Дата добавен:** 08.05.2026
- **Свързан с:** products.php S95 wizard
- **Категория:** UI / data entry
- **Precondition:** STRESS Lab tenant
- **Action:**
  1. Open products.php
  2. Натисни „+ Добави артикул"
  3. Избери „Вариации"
  4. Стъпка 1: име, цена, размери (S, M, L, XL)
  5. Стъпка 2: бройки per размер (matrix UI)
  6. Стъпка 3: optional полета
  7. Запази
- **Expected result:**
  - Главен артикул в `products`
  - 4 child артикула (по 1 за размер) в `products`
  - `inventory.quantity` per child
- **Приоритет:** P0
- **Статус:** active

---

### S005 — AI Brain — pill render с реални числа

- **Дата добавен:** 08.05.2026
- **Свързан с:** chat.php + compute-insights.php
- **Категория:** AI brain
- **Precondition:** STRESS Lab tenant с history (продажби, артикули), `compute-insights.php` пуснат в последен час
- **Action:**
  1. Отваряй chat.php
  2. Чакай pills да се заредят (realtime + cached)
  3. Чети първите 5 pills
- **Expected result:**
  - Pills съдържат конкретни числа (не „X" placeholder)
  - Числата съответстват на реални SQL query резултати
  - Pills имат правилни цветове по urgency
  - Pills отварят AI overlay при tap
- **Приоритет:** P0
- **Статус:** active

---

### S006 — AI hallucination probe (ambiguous question)

- **Дата добавен:** 08.05.2026
- **Свързан с:** AI brain Phase 2 (Fact Verifier когато активен)
- **Категория:** AI brain / hallucination
- **Precondition:** STRESS Lab tenant, артикул „Nike 42" с `inventory.quantity = 0`
- **Action:**
  1. Отваряй AI overlay
  2. Питай: „Колко Nike 42 имам?"
- **Expected result:**
  - AI отговаря с факта от DB: „Имаш 0 чифта Nike 42"
  - НЕ халюцинира число (5, 10, 12)
  - Ако Fact Verifier активен → reject ако твърдението не съвпада с DB
- **Приоритет:** P0
- **Статус:** active (full Fact Verifier — Phase 2)

---

### S007 — Voice STT — българска цена

- **Дата добавен:** 08.05.2026
- **Свързан с:** products.php voice wizard
- **Категория:** voice
- **Precondition:** STRESS Lab tenant, products.php wizard отворен на цена поле
- **Action:**
  1. Натисни микрофон
  2. Кажи: „трийсет и пет лева и петдесет"
- **Expected result:**
  - Web Speech API парсва (не Whisper)
  - Полето се попълва с `35.50`
  - НЕ показва грешен parse
- **Приоритет:** P0
- **Статус:** active

---

### S008 — Lost demand auto-capture

- **Дата добавен:** 08.05.2026
- **Свързан с:** sale.php search → 0 results
- **Категория:** sale flow / lost demand
- **Precondition:** STRESS Lab tenant, артикул „Бели маратонки 38" не съществува
- **Action:**
  1. Open sale.php
  2. Search „бели маратонки 38"
  3. Резултат: 0 артикула
  4. Затвори search без да правиш нищо
- **Expected result:**
  - Запис в `search_log` (query, results_count=0)
  - Запис в `lost_demand` (query_text, source='search')
  - Появява се в orders.php как „lost demand за този доставчик"
- **Приоритет:** P0
- **Статус:** active

---

### S009 — Inventory accuracy — продажба намалява quantity

- **Дата добавен:** 08.05.2026
- **Свързан с:** sale.php + inventory таблица
- **Категория:** inventory
- **Precondition:** Артикул X с `inventory.quantity = 10`
- **Action:**
  1. Симулирай продажба на 3 единици от артикул X
- **Expected result:**
  - `inventory.quantity = 7` (10 - 3)
  - Запис в `inventory_events`
  - Realtime pill се обновява в chat.php
- **Приоритет:** P0
- **Статус:** active

---

### S010 — Doставка увеличава quantity

- **Дата добавен:** 08.05.2026
- **Свързан с:** deliveries.php (когато готов)
- **Категория:** inventory / deliveries
- **Precondition:** Артикул X с `inventory.quantity = 5`, `deliveries.php` модулът работи
- **Action:**
  1. Open deliveries.php
  2. Регистрирай нова доставка
  3. Добави 20 бройки от артикул X
  4. Запази
- **Expected result:**
  - `inventory.quantity = 25` (5 + 20)
  - Запис в `delivery_items`
  - Запис в `inventory_events`
- **Приоритет:** P0
- **Статус:** pending (deliveries.php не е готов)

---

### S011 — Трансфер между магазини

- **Дата добавен:** 08.05.2026
- **Свързан с:** transfers.php (когато готов)
- **Категория:** inventory / transfers
- **Precondition:** Артикул X с `inventory.quantity = 10` в Магазин 1, `inventory.quantity = 0` в Магазин 2
- **Action:**
  1. Open transfers.php
  2. Прехвърли 5 единици от Магазин 1 → Магазин 2
- **Expected result:**
  - Магазин 1: `inventory.quantity = 5`
  - Магазин 2: `inventory.quantity = 5`
  - 2 записа в `inventory_events`
  - Запис в `transfers` таблица
- **Приоритет:** P0
- **Статус:** pending (transfers.php не е готов)

---

### S012 — Поръчка към доставчик от lost_demand

- **Дата добавен:** 08.05.2026
- **Свързан с:** orders.php + lost_demand
- **Категория:** orders / cross-module
- **Precondition:** Има 5 записа в `lost_demand` за доставчик X, `orders.php` готов
- **Action:**
  1. Open orders.php
  2. Избери доставчик X
  3. Виж AI footer „6 нови артикула (3 от lost_demand)"
- **Expected result:**
  - В chernovata фигурират 3 от lost_demand записите като предложени артикули
  - Когато се потвърди поръчката → lost_demand записите получават `suggested_supplier_id`
- **Приоритет:** P1
- **Статус:** pending (orders.php не е готов)

---

## 🎬 НОВИ СЦЕНАРИИ ОТ ДНЕС (попълват се при EOD стъпка 5)

```
[празно]
```

---

## 📊 СТАТИСТИКА

| Метрика | Стойност |
|---|---|
| Общо сценарии | 12 |
| Активни | 9 |
| Pending (чакат модули) | 3 |
| P0 | 11 |
| P1 | 1 |
| P2 | 0 |

---

## 🔄 ЖИЗНЕН ЦИКЪЛ НА СЦЕНАРИЙ

1. **Създаване:** в EOD стъпка 5, когато нов модул/feature е готов
2. **Activation:** статус active → влиза в нощния пробег
3. **Disable:** ако сценарий стане неактуален (модулът премахнат) → статус disabled
4. **Update:** ако expected result се променя → коригира се в място
5. **Removal:** само ако сценарият е счупен и невъзможно да поправим — `disabled` permanent

Никога не триеш сценарий — само маркираш като disabled.

---

**КРАЙ НА STRESS_SCENARIOS.md v1.0**
