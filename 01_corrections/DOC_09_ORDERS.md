> **⚠️ ТОЗИ ДОКУМЕНТ Е OVERVIEW.**
> За пълна спецификация виж **`ORDERS_DESIGN_LOGIC.md`** (S83-S85 reference):
> - Главна по доставчик (primary view)
> - Alternative views (по статус, 6 въпроса, календар)
> - Supplier detail + Draft detail screens
> - 12-те входни точки с source_ref
> - 11-те типа поръчки (order_type ENUM)
> - 8-те статуса с transitions
> - Lost demand integration (AI fuzzy matching)
> - compute-orders.php спецификация
> - DB schema
>
> И APPENDIX §8 в `BIBLE_v3_0_APPENDIX.md`.

---

# 📘 DOC 09 — ORDERS.PHP ЕКОСИСТЕМА

## Поръчките като нервна система на склада

**Версия:** 1.0 | **Дата:** 21.04.2026 | **Част от:** Група 4: МОДУЛИ

---

## 📑 СЪДЪРЖАНИЕ

1. Защо orders е **екосистема**, не модул
2. 12 входни точки
3. 11 типа поръчки
4. 8 статуса
5. 6-те въпроса вградени в orders
6. Draft detail екран
7. AI generation — Loss > Gain, Anti > Order
8. Lost demand → auto-order
9. Notifications
10. Сесии S83-S85

---

# 1. ЗАЩО ORDERS Е ЕКОСИСТЕМА

Повечето системи имат модул „Orders" — екран с таблица + бутон „Създай поръчка". Ние правим обратното.

**Orders.php е точка където се събират сигнали от 12 различни места в продукта.** Всеки модул може да „прати" артикули за поръчка. Orders ги агрегира, AI ги приоритизира, Пешо потвърждава.

Забележка от Gemini: *„Orders.php с 12 входа + 11 типа + 8 статуса е ERP, не SaaS v1."*

**Нашата препоръка:** v1 има **3 входа** (products, chat, manual) + **3 типа** + **4 статуса**. Останалото — Phase 2. 80/20 правило.

---

# 2. 12 ВХОДНИ ТОЧКИ

| # | Източник | Пример |
|---|---|---|
| 1 | products | Тап „Добави за поръчка" на zero-stock артикул |
| 2 | chat | Voice: „поръчай Nike 42" |
| 3 | home | AI insight [Поръчай →] бутон |
| 4 | sale | Когато stock=0 → „Искаш ли да поръчаш?" |
| 5 | delivery | Recurrent orders от last delivery |
| 6 | inventory | След Zone Walk → auto-reorder gaps |
| 7 | warehouse | Hub module „Нова поръчка" |
| 8 | voice | Voice FAB directly — „поръчай X" |
| 9 | lost_demand | AI detect отказани клиенти |
| 10 | basket | Basket analysis → bundle suggestions |
| 11 | automatic | Cron-auto based on thresholds |
| 12 | blind | „Не знам каква стока имам, поръчай стандарт" |

**За v1 — само 1, 2, 3.**

---

# 3. 11 ТИПА ПОРЪЧКИ

| # | Тип | Значение |
|---|---|---|
| 1 | min | Минимум — само bestseller-и на 0 |
| 2 | partial | Частична — top 50% |
| 3 | full | Пълна — всичко под reorder threshold |
| 4 | combined | Комбинирана от няколко доставчика |
| 5 | urgent | Спешна — доставка за 48ч |
| 6 | seasonal | Сезонна — за идващ сезон |
| 7 | replen | Replenishment на bestseller-и |
| 8 | blind | Blind buy — не знае stock |
| 9 | rebuy | Bestseller-и, повторно купуване |
| 10 | bundle | Комплект (A+B+C) |
| 11 | basket | От basket analysis |

**За v1 — min, partial, full.**

---

# 4. 8 СТАТУСА

```
draft → confirmed → sent → acked → partial → received
                                         ↘ cancelled
                                         ↘ overdue
```

| Status | Значение |
|---|---|
| draft | Пешо пише, не е пратена |
| confirmed | Потвърдена от Пешо, готова за изпращане |
| sent | Изпратена на доставчика (SMS/email) |
| acked | Доставчикът потвърди |
| partial | Пристигна частично |
| received | Пристигна напълно |
| cancelled | Отменена |
| overdue | Закъснява > X дни |

**За v1 — draft, sent, partial, received.**

---

# 5. 6-ТЕ ВЪПРОСА ВГРАДЕНИ В ORDERS

```sql
ALTER TABLE supplier_order_items
  ADD COLUMN fundamental_question
    ENUM('loss','loss_cause','gain','gain_cause','order','anti_order') NOT NULL;
```

**Главен екран на orders.php** = 6 таба по въпрос.

```
┌────────────────────────────────┐
│ Поръчки                        │
├────────────────────────────────┤
│ [Loss] [L.cause] [Gain]        │
│ [G.cause] [Order] [Anti-order] │
├────────────────────────────────┤
│ ⟶ таб съдържание               │
└────────────────────────────────┘
```

## 5.1 Loss таб
Артикули които губят пари сега (на нула от bestseller).

## 5.2 Loss cause таб
Защо губиш — доставчици които се бавят, сезонни проблеми.

## 5.3 Gain таб
Артикули които трябва да поръчаш още (bestseller-и продължават да се продават).

## 5.4 Gain cause таб
От какво се движи bestseller-а — витрина, сезон, реклама.

## 5.5 Order таб
**Препоръчани поръчки** — AI генерирани draft-ове.

## 5.6 Anti-order таб
**НЕ поръчвай** — застояла стока, доставчици с проблеми, сезонни ограничения.

---

# 6. DRAFT DETAIL ЕКРАН

```
┌─────────────────────────────────────┐
│ ← Draft #42 — Моминекс              │
├─────────────────────────────────────┤
│ 🔴 LOSS — губиш сега                │
│ Nike Air Max 42    [3 бр] €135     │
│ Adidas 41          [2 бр] €90      │
├─────────────────────────────────────┤
│ 🟢 GAIN — bestseller-и              │
│ Passionata 75B     [5 бр] €225     │
│ Passionata 80B     [3 бр] €135     │
├─────────────────────────────────────┤
│ 🟡 ORDER — препоръчани              │
│ Puma 40            [2 бр] €90      │
├─────────────────────────────────────┤
│ ⚪ ANTI-ORDER — изключени           │
│ Блузи тип А (45 дни без продажба)   │
│ [Включи ако настояваш]              │
├─────────────────────────────────────┤
│ Общо: 15 броя, €675                 │
│ [Редактирай] [SMS Моминекс] [Email] │
└─────────────────────────────────────┘
```

---

# 7. AI GENERATION — LOSS > GAIN, ANTI > ORDER

AI-Мозъкът генерира draft с приоритет:

1. **Loss items** — артикули които губят пари
2. **Gain items** — bestseller-и
3. **Order items** — препоръки
4. **Anti-order** — изключения (Пешо може да override)

Формула за priority:

```php
function orderPriority($product) {
    $loss_per_day = $product['sold_avg_daily'] * $product['margin'];

    if ($product['stock'] === 0 && $product['sold_30d'] > 0) {
        return 100 + ($loss_per_day / 10);
    }
    if ($product['stock'] <= $product['min_quantity']) {
        return 70 + ($loss_per_day / 10);
    }
    if ($product['sold_30d'] === 0 && $product['stock_age_days'] > 45) {
        return -1;
    }
    return 50;
}
```

---

# 8. LOST DEMAND → AUTO-ORDER

## 8.1 Detection

В sale.php когато Пешо търси артикул който не съществува → log в `search_log`:

```sql
CREATE TABLE search_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    search_term VARCHAR(200) NOT NULL,
    found_count INT NOT NULL DEFAULT 0,
    customer_left_without_buy TINYINT DEFAULT 0,
    seller_id INT NULL,
    created_at DATETIME NOT NULL
);
```

## 8.2 AI Analysis

```sql
SELECT search_term, COUNT(*) as search_count
FROM search_log
WHERE found_count = 0
  AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY search_term
HAVING search_count >= 5
ORDER BY search_count DESC;
```

## 8.3 Insight

```
AI: 7 клиента питаха за "черна рокля размер 42"
    тази седмица. Нямаш.

    Моминекс има. Цена €28. Поръчай 3 за тест?

    [Поръчай] [Игнорирай]
```

---

# 9. NOTIFICATIONS

## 9.1 На Пешо (Owner)
- Дневно в 09:00: „Имаш 2 чернови поръчки за преглед"
- При overdue: „Поръчка Моминекс закъснява с 5 дни"
- При partial: „Получи 80% от поръчка. Провери липсите"

## 9.2 На Manager
Същите, без финансовите суми (€).

## 9.3 На Seller
Никакви. Seller не вижда поръчки.

---

# 10. СЕСИИ S83-S85

| Сесия | Задача |
|---|---|
| S83 | orders.php v1 (3 входа, 3 типа, 4 статуса) |
| S84 | Lost demand detection + basic AI draft generation |
| S85 | Notifications + SMS/email integration |

**След S85:** orders.php v1 production-ready.

---

**КРАЙ НА DOC 09**
