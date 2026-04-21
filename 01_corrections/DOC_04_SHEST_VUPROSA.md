# 📘 DOC 04 — ШЕСТТЕ ФУНДАМЕНТАЛНИ ВЪПРОСА

## Loss / Loss_cause / Gain / Gain_cause / Order / Anti_order

**Версия:** 1.0 | **Дата:** 21.04.2026 | **Част от:** Група 2

---

## 📑 СЪДЪРЖАНИЕ

1. Какво са 6-те въпроса
2. Защо Loss > Gain
3. Защо Anti-order > Order
4. Цветно кодиране
5. Приложение в Simple Mode
6. Приложение в Detailed Mode
7. DB schema
8. Selection Engine
9. Примери per module

---

# 1. КАКВО СА 6-ТЕ ВЪПРОСА

Всеки собственик на магазин всяка сутрин пита едни и същи 6 въпроса:

1. **Loss** — какво губя? (артикули, пари, клиенти)
2. **Loss cause** — от какво го губя? (zombie, out-of-stock, margin erosion)
3. **Gain** — какво печеля? (bestsellers, топ категории)
4. **Gain cause** — от какво печеля? (кои продукти/клиенти/часове)
5. **Order** — какво да поръчам? (бестселъри които свършват)
6. **Anti-order** — какво да НЕ поръчам? (застоялата стока, косвено „продай каквото имаш")

Тези 6 въпроса са **ядрото на всяко бизнес решение**. Другото е детайл.

---

# 2. ЗАЩО LOSS > GAIN

Loss aversion е най-силната психологическа мотивация:
- Собственикът предпочита да **не** загуби €100 пред да спечели €200
- Губенето боли повече отколкото печалбата радва
- Loss-ът е по-мотивиращо да се действа

**UI правило:** Loss секции винаги са **първите** и **най-големите** на екрана. Gain е под тях.

---

# 3. ЗАЩО ANTI-ORDER > ORDER

Повечето ERP системи предлагат **какво да поръчаш**. RunMyStore акцентира на **какво НЕ да поръчаш**.

Защо:
- Собственикът плаща €5,000 за кашон Nike — не може да се върне
- Стоката замразена в склада = пари заключени, не работещи
- „Не поръчвай" е превенция, „поръчай" е реакция
- Anti-order спасява реални пари

**UI правило:** Anti-order е толкова видим колкото Order.

---

# 4. ЦВЕТНО КОДИРАНЕ

| Въпрос | Цвят | Hex |
|---|---|---|
| Loss | Червено | `#ef4444` |
| Loss cause | Виолетово | `#a855f7` |
| Gain | Зелено | `#22c55e` |
| Gain cause | Teal | `#14b8a6` |
| Order | Амбър | `#f59e0b` |
| Anti-order | Сив | `#6b7280` |

Това е **визуален език** който Пешо разпознава от ден 1.

---

# 5. ПРИЛОЖЕНИЕ В SIMPLE MODE

AI **не показва** 6 секции. AI **превежда** insights в разговорни съобщения.

### Пример

**В Detailed Mode** (products.php):
```
🔴 LOSS
Nike Air Max 42 на нула
Губиш €420/седмица

🟣 LOSS CAUSE
Последна доставка: преди 45 дни
Доставчик Моминекс не е отговарял 3 пъти
```

**В Simple Mode** (AI chat):
```
AI: Nike 42 свърши. Губиш ~€420/седмица.
    Причината: Моминекс не е доставил от 45 дни.

    [📞 Напомни на доставчика]
    [📦 Поръчай от друг]
```

Същата информация, различно представена. **AI е преводач**.

---

# 6. ПРИЛОЖЕНИЕ В DETAILED MODE

В Detailed Mode 6-те въпроса са **визуални секции** на всеки модулен екран.

## products.php layout

```
┌────────────────────────────────────┐
│ 🔴 LOSS                            │ ← секция 1
│ 3 артикула на нула                 │
│ Общо губиш: €1,260/седмица         │
├────────────────────────────────────┤
│ 🟣 LOSS CAUSE                      │ ← секция 2
│ Моминекс закъснява с 12 дни        │
│ Mediterranean извън работа 1 седм. │
├────────────────────────────────────┤
│ 🟢 GAIN                            │ ← секция 3
│ Top bestseller: Passionata +35%    │
│ €1,840 печалба този месец          │
├────────────────────────────────────┤
│ 💎 GAIN CAUSE                      │ ← секция 4
│ Петъчните клиенти +42%             │
│ Мария продава най-много            │
├────────────────────────────────────┤
│ 🟡 ORDER                           │ ← секция 5
│ 7 артикула за поръчка              │
│ Общо: ~€3,200                      │
├────────────────────────────────────┤
│ ⚪ ANTI-ORDER                      │ ← секция 6
│ 12 артикула застояват (€2,400)     │
│ НЕ поръчвай блузи тип А            │
└────────────────────────────────────┘
```

Scrolling от горе до долу = pesho вижда **целия бизнес** на един екран.

---

# 7. DB SCHEMA

```sql
ALTER TABLE ai_insights
  ADD COLUMN fundamental_question
    ENUM('loss','loss_cause','gain','gain_cause','order','anti_order') NOT NULL,
  ADD COLUMN priority INT DEFAULT 0,
  ADD COLUMN financial_impact DECIMAL(10,2) NULL,
  ADD INDEX idx_fq (tenant_id, fundamental_question, priority);
```

Всеки insight записан в `ai_insights` има точно **един** фундаментален въпрос.

---

# 8. SELECTION ENGINE

Кои insights да покажем от всичките (може да има 50+ за деня)?

```php
function selectInsightsForPage($tenant_id, $module) {
    $quotas = [
        'loss' => 3,
        'loss_cause' => 2,
        'gain' => 2,
        'gain_cause' => 2,
        'order' => 3,
        'anti_order' => 2,
    ];

    $selected = [];
    foreach ($quotas as $fq => $limit) {
        $insights = DB::run(
            "SELECT * FROM ai_insights
             WHERE tenant_id=? AND module=?
               AND fundamental_question=?
               AND resolved_at IS NULL
             ORDER BY priority DESC, financial_impact DESC
             LIMIT ?",
            [$tenant_id, $module, $fq, $limit]
        );
        $selected = array_merge($selected, $insights);
    }

    return $selected;
}
```

**Priority правила:**
1. Financial impact (колко €)
2. Recency (колко е нов insight)
3. User engagement (action_rate на предишни такива)
4. Seasonal relevance
5. Tonal diversity (не 5 пъти подред „сериозно" — размеси с „позитивно")

---

# 9. ПРИМЕРИ PER MODULE

## products.php

| FQ | Insight пример |
|---|---|
| Loss | „3 артикула на нула: Nike 42, Adidas 38, Puma 41" |
| Loss cause | „Моминекс не е доставил от 45 дни" |
| Gain | „Passionata +35% този месец" |
| Gain cause | „Червените рокли се продават 3x повече в петък" |
| Order | „7 артикула за поръчка: bestseller-и на изчерпване" |
| Anti-order | „Блузи тип А: 45 дни без продажба — НЕ поръчвай още" |

## orders.php

| FQ | Insight пример |
|---|---|
| Loss | „Моминекс доставя средно 12 дни закъснение" |
| Loss cause | „Платени €3,200 за забавени артикули" |
| Gain | „Mediterranean доставя точно навреме" |
| Gain cause | „Поръчките в понеделник идват до петък" |
| Order | „Подготви поръчка за Моминекс: 7 артикула" |
| Anti-order | „Не поръчвай от Polikox — 40% дефектна стока" |

## sale.php

| FQ | Insight пример |
|---|---|
| Loss | „4 клиента излязоха без покупка тази седмица" |
| Loss cause | „Размер 38 липсва — искан от 6 клиенти" |
| Gain | „€2,340 оборот тази седмица (+18%)" |
| Gain cause | „Мария продава с 15% по-висок марж" |
| Order | (не приложимо в sale.php) |
| Anti-order | (не приложимо в sale.php) |

## deliveries.php

| FQ | Insight пример |
|---|---|
| Loss | „€450 надплатено за Nike (цените паднаха)" |
| Loss cause | „Не преговаряш с доставчик от 6 месеца" |
| Gain | „€800 спестени от Mediterranean volume discount" |
| Gain cause | „Поръчваш на едро → по-добра цена" |
| Order | „Време е за следваща доставка Nike" |
| Anti-order | „Не поръчвай от доставчик X този месец" |

---

**КРАЙ НА DOC 04**
