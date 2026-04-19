# 📦 ORDERS.PHP — ДИЗАЙН И ЛОГИКА (S83-S85)

## Пълна спецификация на поръчки екосистема

**Версия:** 1.0 (19.04.2026)  
**Статус:** Reference document за сесии S83-S85  
**Mockups:** orders-by-supplier-s77.html, orders-s77.html  
**Design system:** Neon Glass  
**Предпоставка:** products.php трябва да е готов (S82 tag `v1.0.0-products-complete`)

---

# СЪДЪРЖАНИЕ

1. [Философия на екосистемата](#1-философия-на-екосистемата)
2. [12 Входни точки](#2-12-входни-точки)
3. [11 Типа поръчки](#3-11-типа-поръчки)
4. [8 Статуса (жизнен цикъл)](#4-8-статуса-жизнен-цикъл)
5. [Главна страница — по доставчик (primary view)](#5-главна-страница--по-доставчик)
6. [Alternative views](#6-alternative-views)
7. [Supplier detail екран](#7-supplier-detail-екран)
8. [Draft detail екран](#8-draft-detail-екран)
9. [Create new order flow](#9-create-new-order-flow)
10. [Menu (☰) — 6 секции](#10-menu--6-секции)
11. [Lost demand integration](#11-lost-demand-integration)
12. [Status transitions](#12-status-transitions)
13. [compute-orders.php](#13-compute-ordersphp)
14. [AJAX Endpoints](#14-ajax-endpoints)
15. [DB Schema](#15-db-schema)
16. [Notifications](#16-notifications)
17. [Edge Cases](#17-edge-cases)

---

# 1. ФИЛОСОФИЯ НА ЕКОСИСТЕМАТА

## 1.1 Това не е CRUD модул

Orders **НЕ Е** „списък на поръчки с add/edit/delete". Orders е **екосистема** която:

1. **Recipes (получава сигнали) от 12 източника** (products, chat, home, sale, delivery, inventory, warehouse, voice, lost_demand, basket)
2. **Групира по доставчик** (1 поръчка = 1 доставчик)
3. **Проследява жизнения цикъл** (draft → sent → received)
4. **Агрегира 6 фундаментални въпроса** (всеки артикул знае защо е там)
5. **Блокира грешки** (Anti-Order filter)
6. **Генерира ROI** (lost demand → sold → tracked)

## 1.2 Защо 1 поръчка = 1 доставчик

Real-world: Пешо праща email / вика по телефона / посещава склад на **ОДИН** доставчик наведнъж. Не може да прати „обща поръчка" до 5 доставчика.

Изключение: `order_type = 'combined'` — това е **планиране** за няколко доставчика едновременно, но при `status = 'sent'` се разделя на N отделни поръчки.

## 1.3 6-те въпроса вградени

Всеки артикул в поръчка носи `fundamental_question`. Това позволява:
- **Draft detail екран** да е секциониран по въпрос (вижда се приоритет)
- **AI отхвърля** артикули с `anti_order` (zombie блокери)
- **ROI** да се attribute-ва per въпрос („Loss покритие: 78%")

---

# 2. 12 ВХОДНИ ТОЧКИ

## 2.1 Таблица

| # | Входна точка | `source` value | Автоматично? |
|---|---|---|---|
| 1 | products.php · „Какво да поръчаш" | `products` | Manual (tap) |
| 2 | products.php · detail → „Поръчай още" | `products` | Manual |
| 3 | chat.php · AI signal action button | `chat` | Manual (tap) |
| 4 | home.php · pulse signal | `home` | Manual |
| 5 | sale.php · quick-create → toast „Поръчай отново?" | `sale` | Manual |
| 6 | sale.php · размер липсва → auto lost_demand | `sale` | Automatic |
| 7 | delivery.php · при недостиг → „Поръчай липсите" | `delivery` | Manual |
| 8 | inventory.php · след броене → „под min" | `inventory` | Manual |
| 9 | warehouse.php · „Нова поръчка" бутон | `warehouse` | Manual |
| 10 | Voice: „Поръчай 10 Nike 42" (всеки 🎤) | `voice` | Manual |
| 11 | lost_demand auto-feed (AI) | `lost_demand` | Automatic |
| 12 | Basket analysis („купуват го с X") | `basket` | Automatic |

## 2.2 Flow при добавяне

Всяко добавяне минава през един общ handler:

```php
function addToOrder($product_id, $qty, $source, $source_ref = null, $fundamental_question = 'order') {
  // 1. Намери или създай чернова за този доставчик
  $supplier_id = getSupplierIdForProduct($product_id);
  $draft = DB::run("
    SELECT id FROM supplier_orders 
    WHERE tenant_id=? AND store_id=? AND supplier_id=? 
      AND status='draft'
    ORDER BY created_at DESC LIMIT 1
  ", [$tid, $sid, $supplier_id])->fetchColumn();
  
  if (!$draft) {
    $draft = createDraft($supplier_id);
  }
  
  // 2. Добави артикул (или увеличи qty ако съществува)
  $existing = DB::run("
    SELECT id, qty_ordered FROM supplier_order_items
    WHERE order_id=? AND product_id=?
  ", [$draft, $product_id])->fetch();
  
  if ($existing) {
    DB::run("UPDATE supplier_order_items SET qty_ordered = qty_ordered + ? WHERE id=?",
      [$qty, $existing['id']]);
  } else {
    // 3. AI reasoning
    $ai_reasoning = generateReasoning($product_id, $fundamental_question, $source);
    
    DB::run("INSERT INTO supplier_order_items 
      (order_id, product_id, qty_ordered, unit_cost, 
       fundamental_question, source, source_ref, ai_reasoning)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
      [$draft, $product_id, $qty, $cost, $fundamental_question, 
       $source, $source_ref, $ai_reasoning]);
  }
  
  // 4. Event log
  logOrderEvent($draft, 'item_added', ['product_id' => $product_id, 'qty' => $qty]);
  
  // 5. Recalculate totals
  recalculateOrder($draft);
  
  return $draft;
}
```

## 2.3 UI feedback след добавяне

Toast:
```
✓ Добавено в чернова „Спорт Груп"
  2 840 лв · +1 180 лв profit
  [Виж черновата →]
```

---

# 3. 11 ТИПА ПОРЪЧКИ

## 3.1 Таблица

| Type | Кога | Генератор |
|---|---|---|
| `min` | Достига до min_quantity | AI (compute-orders) |
| `partial` | Пешо избира от препоръки | Manual / Mixed |
| `full` | AI препоръчва всичко + safety stock | AI |
| `combined` | 2+ доставчика в 1 сесия | Manual |
| `urgent` | Спешна, висок приоритет | AI (при critical) |
| `seasonal` | Голям обем преди сезон | AI (seasonal cron) |
| `replen` | Авто при threshold | Cron |
| `blind` | Тестов обем нови SKU | Manual |
| `rebuy` | Агресивен restock bestseller | AI |
| `bundle` | Volume discount комбинация | AI |
| `basket` | Complementary (basket driven) | AI |

## 3.2 Визуализация на типа

В supplier card + draft detail:
```
Тип: [partial ▾]  ← tap отваря selector
```

Pill в header на draft:
```
🎯 Минимална  / 📦 Пълна  / ⚡ Спешна  / 🌱 Сезонна
```

## 3.3 Разлики в UI

| Type | Специфично UI |
|---|---|
| `urgent` | Red banner отгоре: „Спешна! Изпрати до 24 часа" |
| `seasonal` | Timeline: „Сезон започва след 30 дни" |
| `combined` | Split preview: „2 чернови ще се създадат при изпращане" |
| `blind` | Safety warning: „Нов SKU — тестов обем. AI може да се обърка." |

---

# 4. 8 СТАТУСА (ЖИЗНЕН ЦИКЪЛ)

## 4.1 State machine

```
draft ──Пешо потвърждава──→ confirmed ──изпрати──→ sent
  │                                                   │
  │                                       supplier ack│
  │                                                   ↓
  │                                              acked
  │                                                   │
  │                         получена частично ────────┤
  │                                                   ↓
  │                                              partial
  │                                                   │
  │                              пълно получена ──────┤
  │                                                   ↓
  │                                             received
  │
  └──otkaz──→ cancelled
  └──datata mina──→ overdue (ако беше sent/acked)
```

## 4.2 Статус матрица

| Status | Цвят | Може ли да се редактира? | Action бутони |
|---|---|---|---|
| `draft` | Сив (#rgba 255,255,255,.08) | Да | [Потвърди] [Изпрати] [Откажи] [Редактирай] |
| `confirmed` | Синьо-сив | Не (readonly) | [Изпрати] [Откажи] |
| `sent` | Индиго (#99 102 241) | Не | [Маркирай ack] [Маркирай частично] [Откажи] |
| `acked` | Индиго-светъл | Не | [Маркирай частично] [Пълно получена] |
| `partial` | Амбър (#251 191 36) | Partial edit | [Добави остатък] [Пълно получена] [Откажи] |
| `received` | Зелен (#34 197 94) | Не (historical) | [Copy за ново] |
| `cancelled` | Червено-сив | Не | [Copy за ново] |
| `overdue` | Red pulsating | Не | [Изпрати reminder] [Маркирай получена] [Откажи] |

## 4.3 Transitions

Записани в `supplier_order_events` — audit log.

Auto transitions:
- `sent` → `overdue` (cron, когато expected_delivery < NOW())
- `partial` → `received` (когато всички qty_received == qty_ordered)

Manual transitions — бутони в UI.

---

# 5. ГЛАВНА СТРАНИЦА — ПО ДОСТАВЧИК

## 5.1 Цел

Primary view. Показва всички доставчици + активни поръчки при всеки + AI препоръки.

## 5.2 Структура

```
┌────────────────────────────────────────┐
│ Header: ← Склад › Поръчки · ⚙ 🔍      │
│ Title: „Поръчки · по доставчици · 6"   │
│                                        │
│ 🚨 [Red alert]: 1 закъсняла ...        │ ← ако има overdue
│                                        │
│ KPI Strip [3 glass cells]:            │
│ [Спешни 2] [Активни 6] [14 560 лв]    │
│                                        │
│ Controls: [🔍 Търси] [Подр: Спешност▾]│
│                                        │
│ ═══ АКТИВНИ ДОСТАВЧИЦИ · 6 ═══         │
│                                        │
│ ┌─ Zinc Shoes ────────────────────┐   │ ← supplier card (overdue)
│ │ ⏰ 1 закъсняла −3д              │   │
│ │ 3 340 лв · +1 520 лв profit     │   │
│ └──────────────────────────────────┘   │
│                                        │
│ ┌─ Спорт Груп ────────────────────┐   │ ← supplier card (active)
│ │ 📝 1 чернова + 5 получ 30д      │   │
│ │ 2 840 лв · +1 180 лв profit     │   │
│ │ ⭐ AI: 6 нови препоръки [Добави]│   │ ← AI footer
│ └──────────────────────────────────┘   │
│                                        │
│ ┌─ Fashion Club ──────────────────┐   │ ← partial
│ │ ⏳ 1 частична 67% · 12/18       │   │
│ │ ▓▓▓▓▓▓▓░░ 67%                   │   │
│ │ 3 240 лв · +1 420 profit        │   │
│ └──────────────────────────────────┘   │
│                                        │
│ ... още suppliers ...                  │
│                                        │
│ ═══ БЕЗ АКТИВНИ · 4 (dormant) ═══     │
│ [Сиви по-малки cards]                  │
│                                        │
│ ═══ AI СИГНАЛИ (collapsed) ═══        │
│ [Tap разгъва → pills по 6 въпроса]    │
│                                        │
│ FAB: [+] нова чернова                  │
│ Bottom nav: AI · Склад(act) · Спр · Пр │
└────────────────────────────────────────┘
```

## 5.3 Alert banner (ако има overdue)

```html
<div class="glass urgent sm alert">
  <div class="alert-ic">⏰</div>
  <div class="alert-body">
    <div class="alert-t">1 закъсняла поръчка</div>
    <div class="alert-s">Zinc Shoes — 3 дни забавяне, 3 340 лв</div>
  </div>
  <div class="alert-arr">›</div>
</div>
```

Кликването отваря supplier detail на Zinc Shoes.

## 5.4 KPI strip

3 glass cells:

| Cell | Какво показва | Tap |
|---|---|---|
| **Спешни** (red) | Брой urgent + overdue поръчки | Филтрира главната |
| **Активни** (warn) | Общо активни поръчки (без received/cancelled) | Reset filter |
| **Общо лв в поръчки** | Sum на total_cost за активни | Report screen |

## 5.5 Supplier card

### 5.5.1 Структура (пълна)

```html
<div class="glass sm sp">
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <span class="glow"></span>  <!-- optional за hot suppliers -->
  
  <!-- Top row -->
  <div class="sp-top">
    <div class="sp-logo" style="--lh:160;--lh2:180">СГ</div>
    <div class="sp-info">
      <div class="sp-nm">Спорт Груп</div>
      <div class="sp-meta">Чернова + нови препоръки чакат</div>
    </div>
    <div class="sp-arr">›</div>
  </div>
  
  <!-- Status badges -->
  <div class="sp-badges">
    <span class="sp-bg draft"><span class="n">1</span> чернова</span>
    <span class="sp-bg received"><span class="n">5</span> получени 30д</span>
  </div>
  
  <!-- Money row -->
  <div class="sp-money">
    <div class="sp-val">
      <div class="sp-val-n">2 840 лв</div>
      <div class="sp-val-l">Чернова</div>
    </div>
    <div class="sp-val r">
      <div class="sp-val-n profit">+1 180 лв</div>
      <div class="sp-val-l">Очакван profit</div>
    </div>
  </div>
  
  <!-- Progress bar (ако има sent/partial) -->
  <div class="sp-prog">
    <div class="sp-prog-l">
      <span>Напредък (sent → delivery)</span>
      <b>65%</b>
    </div>
    <div class="sp-prog-t">
      <div class="sp-prog-f" style="width:65%"></div>
    </div>
  </div>
  
  <!-- AI footer (ако има препоръки) -->
  <div class="sp-ai">
    <div class="sp-ai-ic">⭐</div>
    <div class="sp-ai-t">
      AI: <b>6 нови артикула</b> готови да добавиш към черновата
    </div>
    <div class="sp-ai-b">Добави →</div>
  </div>
</div>
```

### 5.5.2 Logo

Hue per supplier — деривативен от `supplier_id`:
```javascript
function supplierHue(supplierId) {
  return (supplierId * 37) % 360; // consistent псевдо-random
}
```

2 букви: първите на всеки име (Спорт Груп → СГ, Balkan Denim → БД).

### 5.5.3 Meta line

Кратко описание на всичко:
- „Чернова + нови препоръки чакат"
- „12/18 получени · Доостатък пт"
- „18 артикула · Очаквана утре 08-12ч"
- „8 чифта обувки · очаквана вчера"

### 5.5.4 Status badges

Множество едновременно:
- 📝 Чернова (сив)
- 📤 Изпратена (индиго)
- ⏳ Частична X% (амбър)
- ⏰ Закъсняла −Nд (пулсиращ червен)
- ✓ Получени 30д (зелен, optional, показва история)

### 5.5.5 Money row

- Ляво: главната стойност (чернова / в транзит / получено)
- Дясно: **profit** (зелен gradient)

### 5.5.6 Progress bar (когато)

- sent → до expected_delivery (% изтекло време)
- partial → qty_received / qty_ordered %
- Цвят индиго за sent, амбър за partial

### 5.5.7 AI footer

Показва се ако има нови lost_demand / AI препоръки за този доставчик:
```
⭐ AI: 6 нови артикула готови [Добави →]
```

Tap → добавя всички 6 в съществуващата чернова (или създава нова).

## 5.6 Сортиране

Бутон „Подреди: Спешност ▾":
- **Спешност** (default): overdue > partial > sent > draft > dormant
- **По име**: азбучен
- **По стойност**: total_cost DESC
- **По profit**: expected_profit DESC

## 5.7 Dormant section

Доставчици без активни поръчки:
- По-малки cards (padding 11px vs 14px)
- Opacity 0.65
- Само name + last order date
- Tap → supplier detail (виж § 7)

---

# 6. ALTERNATIVE VIEWS

## 6.1 По статус

От menu → „Изгледи" → „По статус"

```
Tabs: [Всички 6] [Чернови 3] [Належащи 2] [Чакат 5] 
      [Частично 1] [Получени 7] [Закъснели 1]

Content: order cards (не supplier cards), filtered by status
```

### Order card

```html
<div class="glass sm oc">
  <div class="oc-logo">БД</div>
  <div class="oc-body">
    <div class="oc-top">
      <div class="oc-nm">Balkan Denim</div>
      <div class="oc-status sent">Изпратена</div>
    </div>
    <div class="oc-meta">18 арт. · Очаквана утре 08:00-12:00</div>
    <div class="oc-bot">
      <div class="oc-total">3 240 лв</div>
      <div class="oc-date">Преди 2 дни</div>
    </div>
    <div class="oc-prog">
      <div class="oc-prog-f" style="width:65%"></div>
    </div>
  </div>
</div>
```

## 6.2 По 6 въпроса

От menu → „Изгледи" → „По 6 въпроса"

```
6 tabs (scrollable horizontally):
[1 Губиш 12] [2 От какво 5] [3 Печелиш 18] 
[4 От какво 7] [5 Поръчай 28] [6 НЕ 9]

Content per tab: article cards (не по доставчик)
```

Полезен когато Пешо иска да види „какво изпускам като profit" независимо от доставчика.

## 6.3 Календар

От menu → „Изгледи" → „Календар"

```
┌────────────────────────────────┐
│   < Април 2026 >               │
│ Пн Вт Ср Чт Пт Сб Нд           │
│           1  2  3  4  5        │
│ 6  7  8  9 10 11 12            │
│13 14 15 16 17 18 19            │
│20 21 22 [23] 24 25 26          │  ← днес
│27 28 29 30                     │
│                                │
│ 20.04: 🔵 Balkan Denim (sent)  │
│ 22.04: 🟡 Fashion Club (part) │
│ 16.04: ⏰ Zinc Shoes (overdue) │
└────────────────────────────────┘
```

Visual по `expected_delivery` дати.

---

# 7. SUPPLIER DETAIL ЕКРАН

## 7.1 Цел

Tap на supplier card → отваря detail с **пълна картина** за този доставчик.

## 7.2 Структура

```
┌────────────────────────────────┐
│ ← Спорт Груп                ⋯ │
├────────────────────────────────┤
│ [Logo] СГ                      │
│ Спорт Груп ЕООД                │
│ гр. София, бул. Цариградско 42│
│ ☎ 0888 123 456                 │
│                                │
│ Lead time: 2 дни средно        │
│ Reliability: 94%               │
│ Последна поръчка: преди 8 дни  │
│                                │
│ ═══ АКТИВНИ ПОРЪЧКИ · 1 ═══    │
│ [Order card с черновата]       │
│                                │
│ ═══ AI ПРЕПОРЪКИ · 6 артикула ═│
│ 🔴 Nike 42 черни (18 прод/седм)│
│   Поръчай 24 бр                │
│ 🟢 Adidas Superstar 40 (топ #1)│
│   Поръчай 12 бр                │
│ ...                            │
│                                │
│ Lost demand от клиенти (7д):   │
│ • Nike 38 бели ×3              │
│ • Adidas 41 черни ×2           │
│                                │
│ [+ Добави всички в черновата]  │
│                                │
│ ═══ ИСТОРИЯ (last 6 months) ═══│
│ [Timeline на минали поръчки]   │
│                                │
│ ═══ БЕЛЕЖКИ ═══                │
│ Note-а от Пешо за доставчика   │
└────────────────────────────────┘
```

## 7.3 Contact info

- Address, phone, email — DB полета на suppliers таблица
- Tap на phone → dial
- Tap на email → compose with „Поръчка" subject + черновата като PDF attach

## 7.4 Статистика

- **Lead time**: AVG(actual_delivery - sent_at)
- **Reliability**: % не-overdue / не-cancelled поръчки
- **Last order**: `MAX(created_at)`
- **Total spent (12m)**: SUM(total_cost)

## 7.5 AI препоръки секция

Автоматично генерира список от:
1. lost_demand с suggested_supplier_id = X
2. insights с fundamental_question IN ('loss', 'order') за негови артикули
3. Basket drivers за негови артикули

## 7.6 История timeline

```
┌──────────────────────────────┐
│ 18.04.2026 📤 Изпратена       │
│ 12 арт · 2 840 лв             │
├──────────────────────────────┤
│ 10.04 ✓ Получена              │
│ 18 арт · 3 240 лв             │
├──────────────────────────────┤
│ 05.04 ✓ Получена              │
│ 8 арт · 1 420 лв              │
└──────────────────────────────┘
```

Tap на timeline item → отваря order detail (историческа).

---

# 8. DRAFT DETAIL ЕКРАН

## 8.1 Цел

Когато Пешо отваря чернова → вижда всички артикули групирани по фундаментални въпроси, може да редактира, изпрати.

## 8.2 Структура

```
┌────────────────────────────────┐
│ ← Чернова #47 — Спорт Груп  ⋯ │
├────────────────────────────────┤
│ Status: 📝 Чернова             │
│ Създадена: Днес 14:22 (Пешо)   │
│ Тип: [partial ▾]               │
│ Artikuli: 12 · Общо: 2 840 лв  │
│ Profit: +1 180 лв (41%)        │
│                                │
│ ═══ ПО ФУНДАМЕНТАЛНИ ВЪПРОСИ ═══│
│                                │
│ 🔴 ГУБИШ СЕГА (4 арт.)         │
│ ┌──────────────────────────┐  │
│ │ Nike Air Max 42      ×24 │  │ ← item row
│ │ 18 прод/седм · 120 лв    │  │
│ │ [−] [+] [✕]              │  │
│ └──────────────────────────┘  │
│ ...още 3...                    │
│                                │
│ 🟢 ТОПОВЕ (6 арт.)             │
│ [items...]                     │
│                                │
│ 🔷 BASKET DRIVERS (2)          │
│ [items...]                     │
│                                │
│ ⚠ БЛОКЕРИ — AI отхвърли (2)  │
│ ✗ Блуза Mango XS (78д zombie) │
│   „не поръчвай — замразен цкап"│
│ ✗ Яке зимно XL (спад 40%)     │
│   [Override →] (ако Пешо иска) │
│                                │
│ 📊 LOST DEMAND (клиенти 7д):  │
│ • Nike 38 бели ×3              │
│   [Добави →]                   │
│ • Adidas 41 ×2                 │
│   [Добави →]                   │
│                                │
│ 📝 БЕЛЕЖКА:                    │
│ [textarea...]                  │
│                                │
│ [🎤 Добави с глас] [➕ Ръчно]  │
├────────────────────────────────┤
│ [Откажи] [Запази] [Изпрати →]  │
└────────────────────────────────┘
```

## 8.3 Item row

```html
<div class="glass xs di-item">
  <div class="di-photo">[img or SVG]</div>
  <div class="di-body">
    <div class="di-nm">Nike Air Max 42</div>
    <div class="di-ctx">18 прод/седм · 120 лв · marж 40%</div>
    <div class="di-ai">💡 Причина: Топ #1 — свършил е</div>
  </div>
  <div class="di-qty">
    <button class="di-minus">−</button>
    <input type="number" value="24" class="di-input">
    <button class="di-plus">+</button>
  </div>
  <button class="di-remove">✕</button>
</div>
```

## 8.4 Групиране по fundamental_question

Автоматично — секциите се показват само ако има артикули в тях:

```php
$items = DB::run("SELECT * FROM supplier_order_items WHERE order_id=? ORDER BY fundamental_question", [$oid])->fetchAll();
$grouped = [];
foreach ($items as $item) {
  $grouped[$item['fundamental_question']][] = $item;
}
// Render секция per key in $grouped (respecting priority: loss → gain → order)
```

## 8.5 Блокери секция (Anti-Order)

AI отхвърли артикули, които **иначе** биха влезли в поръчката, защото:
- Zombie (45+ дни без продажба)
- Declining trend (spad 20%+)
- High return rate (> 10%)

Тези **не са** в поръчката, но се показват като „за твое знание". Пешо може да override с [Override →] бутон (тогава се добавя с warning).

## 8.6 Lost Demand секция

Показва lost_demand с `suggested_supplier_id = current_supplier_id` и `resolved = 0` (still unresolved).

Бутон „Добави →" на всеки ред:
- Ако matched_product_id съществува → добавя в поръчката
- Ако не → отваря wizard за създаване на нов артикул

## 8.7 Action buttons

- **Откажи** → status = `cancelled`, confirm dialog
- **Запази** → само UPDATE, status остава draft
- **Изпрати →** → открива „Send overlay" (виж § 12)

## 8.8 Edit history (⋯ menu)

Tap на ⋯ → показва:
- 📜 История на промените (от supplier_order_events)
- 🖨 Печат (PDF)
- 📧 Email към доставчика
- 📋 Duplicate (нова чернова от тази)
- ✕ Изтрий черновата

---

# 9. CREATE NEW ORDER FLOW

## 9.1 Entry points

- FAB [+] бутон
- Menu → „Създай" → 4 опции

## 9.2 FAB flow

```
1. Tap [+]
2. Bottom sheet: „Как?":
   [🎤 AI voice] [✏️ Ръчно] [🔁 Повтори последна] [📷 От фактура]
3. Избираш метод → съответния flow
```

## 9.3 AI voice flow

```
1. Voice overlay (S56 стил)
2. AI: „За кой доставчик?"
3. Пешо: „Спорт Груп"
4. AI: „Какво?"
5. Пешо: „10 Nike 42, 5 Adidas 40, 20 тениски"
6. AI parsing → показва preview:
   - Nike 42 ×10 (намерен ID 123)
   - Adidas 40 ×5 (намерен ID 456)
   - Тениски ×20 (⚠ не открит — търси се)
7. Пешо confirms → създава чернова
```

## 9.4 Ръчно flow

```
1. Избор на доставчик (searchable dropdown)
2. Празна чернова
3. [+ Добави артикул] → search от products → tap → qty input
4. Repeat
5. Save as draft
```

## 9.5 Повтори последна

```
1. „От кой доставчик?"
2. Зарежда последната received поръчка от този доставчик
3. Създава нова чернова с същите артикули + qty
4. Показва diff: „Промени ли от миналия път? (3 са zombie сега)"
5. Пешо review + save
```

## 9.6 От фактура (OCR)

```
1. Snap / upload фактура
2. Gemini Vision парсва
3. Preview table — артикули, qty, цени
4. Map to existing products (AI fuzzy match)
5. Unmapped → create new
6. Save as draft
```

---

# 10. MENU (☰) — 6 СЕКЦИИ

## 10.1 Структура (bottom sheet)

```
┌────────────────────────────────┐
│ Поръчки — меню              ✕ │
├────────────────────────────────┤
│                                │
│ 📋 ИЗГЛЕДИ                     │
│   ● По доставчик (сега)       │
│   ○ По статус                 │
│   ○ По 6 въпроса              │
│   ○ Календар                  │
│                                │
│ ⊕ СЪЗДАЙ                       │
│   🎤 AI voice                 │
│   ✏️ Ръчно                    │
│   🔁 Повтори последна         │
│   📷 От фактура               │
│                                │
│ 📊 СПРАВКИ                     │
│   Точност AI препоръки        │
│   Топ доставчици              │
│   Lead time per доставчик     │
│   Закъснения история          │
│                                │
│ 📤 ЕКСПОРТ                     │
│   Печат чернова (PDF)         │
│   Excel/CSV                   │
│   Email към доставчик         │
│                                │
│ ⚙ НАСТРОЙКИ                    │
│   Auto-restock правила        │
│   Reorder threshold           │
│   Уведомления при закъснения  │
│   Bluetooth принтер           │
│                                │
│ ℹ️ ПОМОЩ                       │
│   Как работи AI поръчки       │
│   6-те въпроса — обяснение    │
└────────────────────────────────┘
```

## 10.2 Разгръщане

Bottom sheet с max-height 85vh. Swipe down затваря.

---

# 11. LOST DEMAND INTEGRATION

## 11.1 Flow

Виж Appendix §9.

## 11.2 Къде се показва в orders.php

### 11.2.1 Supplier card (главна страница)
AI footer показва count на lost_demand за този supplier:
```
⭐ AI: 6 нови артикула (3 от lost_demand)
```

### 11.2.2 Supplier detail
Отделна секция „Lost demand" с всички unresolved записи.

### 11.2.3 Draft detail
При отваряне на чернова — ако има unresolved lost_demand за този доставчик, показва като блок под артикулите.

## 11.3 AI fuzzy matching

Ежедневен cron:
```php
function matchLostDemand() {
  $unmatched = DB::run("
    SELECT ld.* FROM lost_demand ld
    WHERE ld.suggested_supplier_id IS NULL 
      AND ld.resolved = 0
      AND ld.last_asked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
  ")->fetchAll();
  
  foreach ($unmatched as $ld) {
    $result = gemini_match($ld['query_text'], getSupplierCatalogs());
    if ($result['confidence'] > 0.7) {
      DB::run("UPDATE lost_demand SET 
        suggested_supplier_id = ?, matched_product_id = ? 
        WHERE id = ?",
        [$result['supplier_id'], $result['product_id'], $ld['id']]);
    }
  }
}
```

## 11.4 Resolution tracking

Когато артикул от lost_demand се поръча → received → sold:
```sql
UPDATE lost_demand SET 
  resolved = 1,
  resolved_order_id = ?
WHERE matched_product_id = ? 
  AND resolved = 0;
```

ROI report в menu → „Справки" → „Lost demand ROI":
```
Миналия месец 23 записа lost demand
12 → AI матчна → поръчани → продадени
Резултат: +1 840 лв profit
Петя е записала 18 от 23 → комисион 92 лв
```

---

# 12. STATUS TRANSITIONS

## 12.1 draft → confirmed

```
Action: Пешо натиска [Запази & потвърди]
Changes:
  status = 'confirmed'
  notes: Preserved
Event: status_change draft → confirmed
```

## 12.2 confirmed → sent

```
Action: Пешо натиска [Изпрати →]
→ Send overlay:
  ┌──────────────────────────────┐
  │ Как да изпратим?             │
  │ [📧 Email на spotgrup@..]    │
  │ [📱 SMS/WhatsApp]            │
  │ [📞 Ще се обадя сам]         │
  │                              │
  │ Очаквана доставка: [05.05 ▾] │
  │                              │
  │ [Отказ] [Изпрати]            │
  └──────────────────────────────┘

Changes:
  status = 'sent'
  sent_at = NOW()
  expected_delivery = ?
Event: sent
Email: Auto-generate PDF + send (ако е избран email)
```

## 12.3 sent → acked

```
Action: Manual (Пешо маркира) ИЛИ auto (ако supplier portal съществува)
Changes:
  status = 'acked'
Event: acked
```

## 12.4 sent/acked → partial

```
Action: При получаване на доставка в deliveries.php:
  Пешо отбелязва „12 от 18 получени"
Changes:
  status = 'partial'
  supplier_order_items.qty_received = X (per item)
Event: partial_received
Notification: „Fashion Club — получена частично 12/18"
```

## 12.5 partial → received

```
Action: Останалите получени
Changes:
  status = 'received'
  received_at = NOW()
  actual_delivery = NOW()
Event: fully_received
Notification: toast „Fashion Club — поръчката е изцяло получена"
```

## 12.6 sent/acked → overdue (AUTO)

Cron:
```sql
UPDATE supplier_orders 
SET status = 'overdue'
WHERE status IN ('sent', 'acked') 
  AND expected_delivery < CURDATE()
  AND actual_delivery IS NULL;
```

Event: status_change → overdue.
Notification: push „Zinc Shoes закъсня с 1 ден"

## 12.7 Any → cancelled

```
Action: Пешо [Откажи]
Confirm dialog: „Сигурен ли си? Това ще откаже поръчката."
Changes:
  status = 'cancelled'
Event: cancelled
```

---

# 13. compute-orders.php

## 13.1 Цел

Нов файл. Генерира **чернови автоматично** според приоритета Loss > Gain, Anti > Order.

## 13.2 Flow

```php
function computeOrders($tenant_id, $store_id) {
  // 1. Вземи всички insights с fundamental_question IN ('loss', 'order', 'anti_order')
  $insights = DB::run("
    SELECT * FROM ai_insights 
    WHERE tenant_id=? AND store_id=? 
      AND fundamental_question IN ('loss','order','anti_order')
      AND is_active = 1
      AND product_id IS NOT NULL
  ", [$tenant_id, $store_id])->fetchAll();
  
  // 2. Групирай по supplier
  $by_supplier = [];
  foreach ($insights as $ins) {
    $p = getProduct($ins['product_id']);
    if (!$p['supplier_id']) continue;
    $by_supplier[$p['supplier_id']][] = $ins;
  }
  
  // 3. За всеки supplier — създай/обнови чернова
  foreach ($by_supplier as $supplier_id => $items) {
    $draft = getOrCreateDraft($supplier_id, $tenant_id, $store_id);
    
    // Приоритизация: Loss → Order → Anti (но анти-блокира)
    $priority = ['loss' => 1, 'order' => 2, 'anti_order' => 3];
    usort($items, fn($a,$b) => $priority[$a['fundamental_question']] <=> $priority[$b['fundamental_question']]);
    
    $blocked = [];
    foreach ($items as $ins) {
      if ($ins['fundamental_question'] === 'anti_order') {
        $blocked[$ins['product_id']] = $ins;
        continue;
      }
      
      if (isset($blocked[$ins['product_id']])) continue; // AI отхвърли
      
      $qty = calcRecommendedQty($ins);
      addToDraft($draft, $ins['product_id'], $qty, 
                 'lost_demand', $ins['id'], 
                 $ins['fundamental_question']);
    }
    
    // 4. Запази blocked в draft metadata
    updateDraftMetadata($draft, ['blocked' => $blocked]);
    
    // 5. Add lost_demand suggestions
    addLostDemandSuggestions($draft, $supplier_id);
    
    // 6. Recalculate totals
    recalculateOrder($draft);
  }
}
```

## 13.3 calcRecommendedQty

Алгоритъм:
```php
function calcRecommendedQty($insight) {
  $p = getProduct($insight['product_id']);
  $velocity = getVelocity($p['id']); // sales per day (30d avg)
  $lead_time = getSupplierLeadTime($p['supplier_id']); // days
  $safety_factor = 1.5;
  
  // Base: what sells during lead time + safety
  $base_qty = ceil($velocity * $lead_time * $safety_factor);
  
  // Consider min_quantity
  $base_qty = max($base_qty, $p['min_quantity']);
  
  // Consider MOQ от supplier
  $base_qty = max($base_qty, getSupplierMOQ($p['supplier_id'], $p['id']));
  
  // Bestseller bonus (fundamental_question = 'gain')
  if ($insight['fundamental_question'] === 'gain') {
    $base_qty = round($base_qty * 1.3); // агресивен restock
  }
  
  return $base_qty;
}
```

## 13.4 Cron

```
# Orders — нощен генератор
0 3 * * * www-data php /var/www/runmystore/compute-orders.php
```

Изпълнява веднъж в нощта (03:00).

---

# 14. AJAX ENDPOINTS (orders.php)

| Endpoint | Method | Returns |
|---|---|---|
| `ajax=list_suppliers` | GET | Supplier cards с агрегирани статуси |
| `ajax=list_by_status` | GET | Order cards filtered by status |
| `ajax=list_by_question` | GET | Items filtered by fundamental_question |
| `ajax=supplier_detail` | GET | Пълна информация за supplier |
| `ajax=order_detail` | GET | Draft detail |
| `ajax=create_draft` | POST | Нова празна чернова |
| `ajax=add_item` | POST | Добавя артикул (от 12-те входа) |
| `ajax=update_item_qty` | POST | Промяна на qty |
| `ajax=remove_item` | POST | Премахва артикул |
| `ajax=send_order` | POST | status = sent, email trigger |
| `ajax=cancel_order` | POST | status = cancelled |
| `ajax=receive_full` | POST | status = received |
| `ajax=receive_partial` | POST | items.qty_received update |
| `ajax=calendar_events` | GET | За календарния изглед |
| `ajax=lost_demand_for_supplier` | GET | lost_demand filtered |
| `ajax=stats_leadtime` | GET | Lead time per доставчик |
| `ajax=stats_reliability` | GET | Reliability score |

---

# 15. DB SCHEMA

Виж **BIBLE_v3_0_APPENDIX §8.5** и **§11** — пълен SQL там.

## 15.1 Ключови invariants

```sql
-- CHECK constraints
ALTER TABLE supplier_orders 
  ADD CONSTRAINT chk_total CHECK (total_cost >= 0);
ALTER TABLE supplier_orders 
  ADD CONSTRAINT chk_items CHECK (total_items >= 0);
ALTER TABLE supplier_order_items 
  ADD CONSTRAINT chk_qty CHECK (qty_ordered > 0);
ALTER TABLE supplier_order_items 
  ADD CONSTRAINT chk_received CHECK (qty_received >= 0 AND qty_received <= qty_ordered);

-- Triggers
DELIMITER $$
CREATE TRIGGER recalc_order_on_item_change
AFTER INSERT ON supplier_order_items
FOR EACH ROW BEGIN
  UPDATE supplier_orders SET 
    total_items = (SELECT SUM(qty_ordered) FROM supplier_order_items WHERE order_id = NEW.order_id),
    total_cost = (SELECT SUM(qty_ordered * unit_cost) FROM supplier_order_items WHERE order_id = NEW.order_id)
  WHERE id = NEW.order_id;
END$$
DELIMITER ;
```

---

# 16. NOTIFICATIONS

## 16.1 Типове

| Event | Notification |
|---|---|
| Order overdue (1 ден след expected_delivery) | Push: „Zinc Shoes закъсня с 1 ден" |
| Partial received | Toast: „Fashion Club — 12 от 18 получени" |
| Full received | Toast: „Balkan Denim — поръчката е получена" |
| Cancelled (от доставчика) | Push: „Mango Distr. — отказана поръчка" |
| New lost_demand match | Push: „AI открихме доставчик за lost demand" |
| Auto-restock trigger (replen) | In-app: „Nike 42 достигна reorder point — чернова готова" |

## 16.2 Delivery

- **In-app toast** — винаги
- **Push notification** — ако приложението не е отворено (Capacitor)
- **Email summary** — ежедневен digest (opt-in в Settings)

---

# 17. EDGE CASES

## 17.1 Доставчик без contact info

При `[Изпрати →]` → показва „Добави email/phone първо" + inline form.

## 17.2 Артикул без supplier_id

При add to order → „Този артикул няма доставчик. Избери:" → supplier picker.

## 17.3 Артикул с multi-suppliers

Ако същия артикул се продава от 2+ доставчика (supplier_products таблица):
- При add to order → „Кой доставчик?" → избор
- Memoriz-ирай избора за бъдеще per артикул

## 17.4 Comb типа поръчки (combined)

- Чернова може да съдържа артикули от 2+ доставчика (workaround: отделни suppliers records)
- При [Изпрати →] → split на N поръчки → N emails

## 17.5 Грешна доставка (received > ordered)

- UI позволява: Пешо натиска [+] повече пъти
- Warning: „Получил си повече от поръчаното — ще броим излишъка като бонус"
- Записва се в supplier_order_events

## 17.6 Оставащо количество след partial

Ако Пешо получи 12 от 18 и никога няма да получи останалите:
- [Маркирай като пълно получена] → status = received, но qty_received остава 12
- Записва бележка: „Получени 12 от 18 (разлика списана)"

## 17.7 Изпратена поръчка редакция

**Не се позволява.** Ако Пешо иска промяна:
- [Откажи] старата → нова чернова
- Или чрез свободно поле „Бележка" — пише промяна към доставчика (email)

## 17.8 Auto-restock конфликт

Ако AI genererа нова чернова но вече има draft за същия доставчик:
- Не създава дублирана → добавя в existing draft
- Notification: „Добавих 5 нови артикули в черновата за Спорт Груп"

## 17.9 Ден на изпратените ≈ expected delivery

За локални доставчици lead_time = 0 дни → показвай „утре" като default.

## 17.10 Дълго време без поръчки (dormant supplier)

Ако 90+ дни без поръчка → показва в dormant + „Спрян доставчик? Архивирай?"

---

**КРАЙ НА ORDERS DESIGN LOGIC**
