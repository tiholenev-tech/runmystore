# 📖 RUNMYSTORE.AI — BIBLE APPENDIX v3.1

## Детайлни допълнения към BIBLE_v3_0_CORE + TECH

**Версия:** 3.1 (обновено след S77 на 19.04.2026)  
**Заменя:** v3.0 Appendix  
**Обхват:** Добавя S77 правила (6-те фундаментални въпроса, orders екосистема, warehouse hub), плюс всичко от v3.0.

> **ПРАВИЛО ПРИ КОНФЛИКТ:** v3.1 > v3.0. Законите №1-5 (в CORE) не се променят.

---

# СЪДЪРЖАНИЕ

- **§1** Сезонност (GEMINI_SEASONALITY) — v3.0 запазено
- **§2** Bluetooth Print — v3.0 запазено
- **§3** Test данни — v3.0 запазено
- **§4** config.php — v3.0 запазено
- **§5** Capacitor + fal.ai — v3.0 запазено
- **§6** 🆕 **6-те фундаментални въпроса (S77 ЗАКОН)**
- **§7** 🆕 **Склад Hub архитектура (S77)**
- **§8** 🆕 **Поръчки екосистема (S77)**
- **§9** 🆕 **Lost Demand Flow (S77)**
- **§10** 🆕 **Design принципи (S77)**
- **§11** 🆕 **DB миграции S77**

---

# § §1 — §5 ОТ v3.0 ОСТАВАТ БЕЗ ПРОМЯНА

*(Виж старата версия — сезонност, Bluetooth, test данни, config.php, Capacitor, fal.ai)*

---

# § 6 — 🆕 6-ТЕ ФУНДАМЕНТАЛНИ ВЪПРОСА (S77 ЗАКОН)

## 6.1 Определение

Всеки AI отговор, pill, signal, Life Board съобщение, UI секция задължително адресира поне един от:

| # | Въпрос | Категория | Цвят |
|---|---|---|---|
| 1 | Какво губя? | Loss | 🔴 red (hue 0°) |
| 2 | От какво губя? | Loss Cause | 🟣 violet (hue 280°) |
| 3 | Какво печеля? | Gain | 🟢 green (hue 145°) |
| 4 | От какво печеля? | Gain Cause | 🔷 teal (hue 175°) |
| 5 | Какво да поръчам? | Order | 🟡 amber (hue 38°) |
| 6 | Какво да НЕ поръчам? | Anti-Order | ⚫ grey (hue 220°) |

## 6.2 Приоритизация

- **Loss (1+2) преди Gain (3+4)** — Пешо първо трябва да спре кръвенето, после да печели.
- **Anti-Order (6) преди Order (5)** — пазим от повтаряне на грешки.

## 6.3 Задължителни места на прилагане

Всеки нов или обновен модул **задължително** структуриран по 6-те:

- **products.php** → 6 horizontal scroll секции на главната
- **orders.php** → 6 tabs alternative view + 6 секции в draft detail + fundamental_question колона per item
- **home.php (Life Board)** → 6 категории в briefing
- **stats.php** → 6 групи графики (loss/cause/gain/cause/order/anti)
- **chat.php** → AI отговорите tag-нати с fundamental_question
- **sale.php** → toast alerts по 6-те
- **deliveries.php** → „От тази доставка какво губиш/печелиш"
- **inventory.php** → след броене, 6 въпроса тригери

## 6.4 DB механика

```sql
-- Нова задължителна колона:
ALTER TABLE ai_insights 
  ADD COLUMN fundamental_question 
  ENUM('loss','loss_cause','gain','gain_cause','order','anti_order')
  DEFAULT NULL AFTER urgency;

-- Всяка compute-insights функция ЗАДЪЛЖИТЕЛНО попълва fundamental_question.

-- supplier_order_items също има тази колона:
-- (виж §8.3)
```

## 6.5 Selection Engine — приоритет

```python
def select_insights(pool, max=3):
    # Сортирай по приоритет:
    # 1. Loss (critical, urgent)
    # 2. Loss_cause 
    # 3. Anti-order (блокер)
    # 4. Order
    # 5. Gain
    # 6. Gain_cause
    # S79 UPDATE 22.04.2026 — two orderings:
    # selection_priority: loss,loss_cause,anti_order,order,gain,gain_cause
    # narrative_flow (S79 default): loss,loss_cause,gain,gain_cause,order,anti_order
    priority = {
        'loss': 1, 'loss_cause': 2,
        'gain': 3, 'gain_cause': 4,
        'order': 5, 'anti_order': 6
    }
    return sorted(pool, key=lambda x: (
        priority[x.fundamental_question],
        -x.value_numeric  # абс. стойност в лв
    ))[:max]
```

## 6.6 UI Pattern (референция)

Всяка секция в products.php:

```html
<div class="q-head q1"> <!-- q1-q6 -->
  <div class="q-badge">1</div>
  <div class="q-ttl">
    <div class="q-nm q1">Какво губиш</div>
    <div class="q-sub">Артикули с продажби без наличност</div>
  </div>
  <div class="q-total q1">−340 лв/седм</div>
</div>
<div class="h-scroll">
  <!-- mini cards с article + context -->
</div>
```

CSS класове `.q1` до `.q6` дефинират hue1/hue2 per категория.

## 6.7 Забранени изключения

- НЕ е позволено секция в нов модул БЕЗ fundamental_question mapping
- НЕ е позволено insight БЕЗ маркирана категория
- НЕ е позволено AI отговор БЕЗ число + защо (правило от CORE §3.6)

---

# § 7 — 🆕 СКЛАД HUB АРХИТЕКТУРА (S77)

## 7.1 Йерархия

```
Bottom Nav (4 таба):
┌───┬────────┬─────────┬──────────┐
│AI │ Склад  │ Справки │ Продажба │
└───┴────────┴─────────┴──────────┘
        ↓
  warehouse.php (hub екран)
        ↓
 ┌──────────┬──────────┬──────────┬──────────┬──────────────┐
 │Артикули  │Доставки  │ Поръчки  │Трансфери │Инвентаризация│
 │products  │deliver.  │ orders   │transfers │ inventory    │
 └──────────┴──────────┴──────────┴──────────┴──────────────┘
```

## 7.2 warehouse.php (hub) — какво показва

- 5 големи cards (по 1 за всеки подмодул)
- Всяка card показва **ключово число** + **fundamental_question сигнал**:
  - **Артикули**: общ брой + „3 губиш днес"
  - **Доставки**: pending + „1 закъсняла"
  - **Поръчки**: активни + „2 спешни"
  - **Трансфери**: предложени + „3 магазини ниски"
  - **Инвентаризация**: accuracy% + „преброй зона 3"

## 7.3 Правило

- Поръчки **НЕ** е bottom nav tab — складова операция е
- Всички 5 подмодула имат breadcrumb „← Склад › [Име]"
- Back бутон от подмодул → warehouse hub (НЕ direct към home)

---

# § 8 — 🆕 ПОРЪЧКИ ЕКОСИСТЕМА (S77)

## 8.1 Философия

Поръчки НЕ е единичен модул. Поръчки е **екосистема** с:
- **12 входни точки** (откъдето може да се добави артикул)
- **11 типа поръчки** (различни бизнес сценарии)
- **8 статуса** (жизнен цикъл)
- **6 фундаментални въпроса** (вградени в UI и DB)

## 8.2 12 Входни точки

| # | Входна точка | source value |
|---|---|---|
| 1 | products.php „Какво да поръчаш" | `products` |
| 2 | products.php detail → „Поръчай още" | `products` |
| 3 | chat.php → AI signal action button | `chat` |
| 4 | home.php → pulse signal | `home` |
| 5 | sale.php → quick-create → toast | `sale` |
| 6 | sale.php → размер липсва → auto | `sale` |
| 7 | delivery.php → недостиг | `delivery` |
| 8 | inventory.php → след броене | `inventory` |
| 9 | warehouse.php → нов бутон | `warehouse` |
| 10 | Voice навсякъде | `voice` |
| 11 | lost_demand auto-feed | `lost_demand` |
| 12 | Basket analysis | `basket` |

Всяка има `source_ref` (insight_id, lost_demand_id и т.н.) за audit trail.

## 8.3 11 Типа поръчки (order_type ENUM)

| Type | Описание | Автогенериран? |
|---|---|---|
| `min` | Достига до min_quantity | AI |
| `partial` | Пешо избира от препоръки | Manual |
| `full` | AI препоръчва всичко + safety | AI |
| `combined` | 2+ доставчика (split при send) | Manual/AI |
| `urgent` | Спешна, висок приоритет | AI/Manual |
| `seasonal` | Голям обем преди сезон | AI |
| `replen` | Авто при threshold | Auto (cron) |
| `blind` | Тестов обем нови SKU | Manual |
| `rebuy` | Агресивен restock bestseller | AI |
| `bundle` | Volume discount комбинация | AI |
| `basket` | Complementary (basket driven) | AI |

## 8.4 8 Статуса

```
draft ──→ confirmed ──→ sent ──→ acked ──→ partial ──→ received
                                         ↓          ↓
                                    cancelled    overdue
```

## 8.5 DB Schema

```sql
CREATE TABLE supplier_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  store_id INT NOT NULL,
  supplier_id INT NOT NULL,
  order_type ENUM('min','partial','full','combined','urgent','seasonal',
                  'replen','blind','rebuy','bundle','basket') DEFAULT 'partial',
  status ENUM('draft','confirmed','sent','acked','partial','received',
              'cancelled','overdue') DEFAULT 'draft',
  priority TINYINT DEFAULT 5,
  total_items INT DEFAULT 0,
  total_cost DECIMAL(12,2) DEFAULT 0,
  expected_profit DECIMAL(12,2) DEFAULT 0,
  expected_delivery DATE NULL,
  actual_delivery DATE NULL,
  notes TEXT,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL,
  received_at TIMESTAMP NULL,
  INDEX idx_tenant_status (tenant_id, status),
  INDEX idx_supplier (supplier_id, status),
  INDEX idx_overdue (expected_delivery, status)
);

CREATE TABLE supplier_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  variation_id INT NULL,
  qty_ordered INT NOT NULL,
  qty_received INT DEFAULT 0,
  unit_cost DECIMAL(10,2) NOT NULL,
  fundamental_question ENUM('loss','loss_cause','gain','gain_cause',
                            'order','anti_order') DEFAULT 'order',
  source ENUM('products','chat','home','sale','delivery','inventory',
              'warehouse','voice','lost_demand','basket','manual') DEFAULT 'manual',
  source_ref INT NULL,
  ai_reasoning TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order (order_id),
  INDEX idx_product (product_id),
  FOREIGN KEY (order_id) REFERENCES supplier_orders(id) ON DELETE CASCADE
);

CREATE TABLE supplier_order_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  event_type ENUM('created','edited','status_change','item_added',
                  'item_removed','item_qty_change','note_added','sent',
                  'acked','partial_received','fully_received','cancelled'),
  old_value TEXT,
  new_value TEXT,
  user_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order_time (order_id, created_at)
);
```

## 8.6 orders.php — 4 Views

1. **По доставчик** (default, primary) — supplier cards с status badges
2. **По статус** — 7 tabs: Всички/Чернови/Належащи/Чакат/Частично/Получени/Закъснели
3. **По 6 въпроси** — 6 tabs, артикули групирани по fundamental_question
4. **Календар** — по очаквани дати (visual)

Превключване от ☰ menu → „Изгледи".

## 8.7 compute-orders.php (нов файл)

Генерира чернови automatically:

```php
function computeOrders($tenant_id, $store_id) {
  // 1. Най-приоритетни: fundamental_question = 'loss' или 'loss_cause'
  // 2. След това: 'anti_order' БЛОКЕРИ (не включвай)
  // 3. След това: 'order' standard reorder
  // 4. Най-ниска приоритет: 'gain_cause' basket drivers
  // 
  // Групира по supplier_id → 1 чернова per доставчик
  // Филтрира anti_order блокери (не добавя zombie артикули)
  
  // Пишат в supplier_orders + supplier_order_items
}
```

Cron: веднъж на ден нощем генерира нови препоръки.

---

# § 9 — 🆕 LOST DEMAND FLOW (S77)

## 9.1 Входни точки (какво пълни lost_demand)

1. sale.php → search с 0 резултата (source=`search`)
2. sale.php → voice search 0 results (source=`search`)
3. sale.php → barcode scan miss (source=`barcode_miss`)
4. chat.php → бутон „Какво търсиха" + voice (source=`voice`)
5. home.php → quick button (source=`manual`)

## 9.2 Изходни точки (къде се показва)

| Модул | Как |
|---|---|
| home.php | Weekly pill „5× търсиха бели маратонки 38" |
| chat.php | Signal „Топ lost demand тази седмица" |
| orders.php | При supplier card AI footer „Клиенти търсиха: Nike 42 ×5" |
| products.php | В „Какво губиш" секция |
| stats.php | „Lost demand value" — изгубен profit |
| Staff КПД | „Петя записа 12, Пешо поръча 4, продадени 3 = 180 лв" |

## 9.3 AI Fuzzy Matching (критично)

lost_demand.query_text е **свободен текст**. Трябва да го матчнем към доставчик.

```sql
ALTER TABLE lost_demand 
  ADD COLUMN suggested_supplier_id INT NULL AFTER query_text,
  ADD COLUMN matched_product_id INT NULL AFTER suggested_supplier_id,
  ADD COLUMN resolved_order_id INT NULL AFTER matched_product_id,
  ADD COLUMN times INT DEFAULT 1;
```

**Алгоритъм:**
1. Parse query_text → keywords (brand, category, size, color)
2. Match vs supplier_catalog (кои доставчици продават подобни)
3. Top match → suggested_supplier_id
4. Ако confidence > 0.7 → показва се в техния supplier card

## 9.4 Затворен цикъл (ROI tracking)

```
sale.php → lost_demand
                ↓
          (AI match)
                ↓
       orders.php чернова
                ↓
           Пешо send
                ↓
         delivery.php получава
                ↓
          sale.php продава
                ↓
     lost_demand.matched_product_id = X
     lost_demand.resolved = 1
                ↓
     ROI: „Lost demand → +280 лв profit. 
           Петя комисион: 14 лв"
```

## 9.5 Cross-store сигнал (BIZ план)

Ако lost_demand.query_text се появи в 3+ магазина за 14 дни:
```
🔴 „В 3 от 5 магазина търсиха РОКЛИ за 14 дни.
    Никога не си продавал рокли.
    Помисли да пробваш."
```

---

# § 10 — 🆕 DESIGN ПРИНЦИПИ (S77)

## 10.1 Artikul-центричност

Всеки UI запис = артикул с:
- Снимка / SVG силует
- Име (max 2 реда)
- Цена + наличност
- **Context line** (divider отгоре): число + защо
- Meta pills: доставчик · цена · **profit** (зелен gradient)

## 10.2 Profit навсякъде

НИКОГА оборот. НИКОГА „марж". САМО **profit** (чиста печалба).

Формат: 
- „+840 лв profit" (зелен gradient)
- „−360 лв profit пропуснат" (червен)
- „240 лв замразени" (сив, за zombie)

## 10.3 Цветно кодиране (6-те hue)

Spec в CSS:
```css
.glass.q1 {--hue1:0;--hue2:340}    /* Loss */
.glass.q2 {--hue1:280;--hue2:260}  /* Cause */
.glass.q3 {--hue1:145;--hue2:165}  /* Gain */
.glass.q4 {--hue1:175;--hue2:195}  /* Gain cause */
.glass.q5 {--hue1:38;--hue2:28}    /* Order */
.glass.q6 {--hue1:220;--hue2:230}  /* Anti */
```

## 10.4 Neon Glass компоненти

Всяка card задължително:
```html
<div class="glass sm qX art">
  <span class="shine"></span>
  <span class="shine shine-bottom"></span>
  <span class="glow"></span>       <!-- optional but preferred -->
  <span class="glow glow-bottom"></span>
  <!-- content -->
</div>
```

## 10.5 Приоритетни елементи

На главна на всеки модул задължително имат:
1. **Alert banner** (пулсиращ червен) — ако има spesneshni неща
2. **KPI strip** — 3 cells със scan-at-a-glance числа
3. **Primary content** — по 6-те въпроса
4. **Secondary content** — допълнителни данни (ако има)

## 10.6 Voice Overlay (стандарт)

От S56 одобрен. Прилага се:
- products.php
- chat.php
- onboarding.php
- sale.php
- orders.php (за voice add)

Спецификация: backdrop-filter blur(8px), floating bottom sheet, голяма пулсираща червена точка (48px), „● ЗАПИСВА" / „✓ ГОТОВО", transcript box, „Изпрати →" button.

---

# § 11 — 🆕 DB МИГРАЦИИ S77 (изпълнява се в S78)

## 11.1 Миграционен скрипт

```sql
-- =====================================================
-- S77 DB MIGRATION — 19.04.2026
-- =====================================================

-- 1. Phase 0 основни таблици (ако липсват):
CREATE TABLE IF NOT EXISTS ai_insights (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  store_id INT NULL,
  topic_id VARCHAR(50) NOT NULL,
  module ENUM('home','products','warehouse','stats','sale','orders','deliveries',
              'transfers','inventory','loyalty') NOT NULL,
  urgency ENUM('critical','warning','info','opportunity') NOT NULL,
  fundamental_question ENUM('loss','loss_cause','gain','gain_cause',
                            'order','anti_order') DEFAULT NULL,
  pill_text VARCHAR(255) NOT NULL,
  detail_json JSON NULL,
  value_numeric DECIMAL(12,2) NULL,
  product_id INT NULL,
  supplier_id INT NULL,
  action_label VARCHAR(100) NULL,
  action_type ENUM('chat','url','order_draft','inline') DEFAULT 'chat',
  action_url VARCHAR(255) NULL,
  action_data JSON NULL,
  is_active BOOLEAN DEFAULT TRUE,
  computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL,
  INDEX idx_tenant_module (tenant_id, module, urgency),
  INDEX idx_question (fundamental_question),
  INDEX idx_product (product_id),
  INDEX idx_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS ai_shown (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  insight_id INT NOT NULL,
  shown_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  tapped BOOLEAN DEFAULT FALSE,
  tapped_at TIMESTAMP NULL,
  INDEX idx_tenant_user (tenant_id, user_id)
);

CREATE TABLE IF NOT EXISTS search_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  store_id INT NOT NULL,
  user_id INT NOT NULL,
  query VARCHAR(255) NOT NULL,
  results_count INT DEFAULT 0,
  source ENUM('products','sale','warehouse','chat') DEFAULT 'products',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id, created_at),
  INDEX idx_zero (tenant_id, results_count, created_at)
);

CREATE TABLE IF NOT EXISTS lost_demand (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  store_id INT NOT NULL,
  user_id INT NULL,
  query_text VARCHAR(500) NOT NULL,
  suggested_supplier_id INT NULL,
  matched_product_id INT NULL,
  resolved_order_id INT NULL,
  source ENUM('search','voice','barcode_miss','ai_chat','manual') DEFAULT 'search',
  resolved TINYINT(1) DEFAULT 0,
  times INT DEFAULT 1,
  first_asked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_asked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant_resolved (tenant_id, resolved, last_asked_at),
  INDEX idx_supplier (suggested_supplier_id)
);

-- 2. Supplier orders екосистема (нови таблици):
CREATE TABLE IF NOT EXISTS supplier_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  store_id INT NOT NULL,
  supplier_id INT NOT NULL,
  order_type ENUM('min','partial','full','combined','urgent','seasonal',
                  'replen','blind','rebuy','bundle','basket') DEFAULT 'partial',
  status ENUM('draft','confirmed','sent','acked','partial','received',
              'cancelled','overdue') DEFAULT 'draft',
  priority TINYINT DEFAULT 5,
  total_items INT DEFAULT 0,
  total_cost DECIMAL(12,2) DEFAULT 0,
  expected_profit DECIMAL(12,2) DEFAULT 0,
  expected_delivery DATE NULL,
  actual_delivery DATE NULL,
  notes TEXT,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL,
  received_at TIMESTAMP NULL,
  INDEX idx_tenant_status (tenant_id, status),
  INDEX idx_supplier (supplier_id, status),
  INDEX idx_overdue (expected_delivery, status)
);

CREATE TABLE IF NOT EXISTS supplier_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  variation_id INT NULL,
  qty_ordered INT NOT NULL,
  qty_received INT DEFAULT 0,
  unit_cost DECIMAL(10,2) NOT NULL,
  fundamental_question ENUM('loss','loss_cause','gain','gain_cause',
                            'order','anti_order') DEFAULT 'order',
  source ENUM('products','chat','home','sale','delivery','inventory',
              'warehouse','voice','lost_demand','basket','manual') DEFAULT 'manual',
  source_ref INT NULL,
  ai_reasoning TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order (order_id),
  INDEX idx_product (product_id),
  FOREIGN KEY (order_id) REFERENCES supplier_orders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS supplier_order_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  event_type ENUM('created','edited','status_change','item_added',
                  'item_removed','item_qty_change','note_added','sent',
                  'acked','partial_received','fully_received','cancelled'),
  old_value TEXT,
  new_value TEXT,
  user_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order_time (order_id, created_at)
);
```

## 11.2 Изпълнение

- **Кога:** S78 като първа задача
- **Как:** Python скрипт `/tmp/s78_migrate.py` който:
  1. Backup-ва DB (mysqldump)
  2. Пуска SQL-а с `DB::run`
  3. Проверява че таблиците съществуват
  4. Logs резултата
- **Тест:** tenant_id=7 (test) първо, после tenant_id=52 (ЕНИ)

## 11.3 MySQL notes

- **НЕ** ползвай `ADD COLUMN IF NOT EXISTS` — текущата MySQL версия не поддържа. Ползвай SELECT schema check първо.
- **Foreign keys** — всички orders таблици ENGINE=InnoDB.
- **Character set** — utf8mb4_unicode_ci за всички текстови полета.

---

---

# § 12 — 🆕 МИГРАЦИЯ ОТ СТАРИ ПРОГРАМИ (S96 ЗАКОН)

## 12.1 Миграцията като продажбена функция

Повечето малки магазини вече имат складова програма (Microinvest, Sigma, StoreHouse, SmartBill или друга). Най-голямата бариера за смяна е "ще трябва да въвеждам всичко наново".

**RunMyStore премахва тази бариера.** Клиентът качва файл от старата си програма → AI разпознава формата → 15 минути по-късно е в RunMyStore с пълна база.

**Рекламно послание:** *"Прехвърли магазина за 15 минути. AI работи от ден 1."*

Това е решаващо предимство пред Loyverse, iCash, Shopify POS — нито една облачна система не предлага автоматично разпознаване на десетки локални програми.

## 12.2 Onboarding въпрос (задължителен)

При регистрация AI пита:

> "Имаш ли складова програма в момента?"

Бутони: **[Microinvest] [Sigma] [StoreHouse] [SmartBill] [Друга] [Нямам]**

**Ако избере конкретна програма:**
> "Данните ти актуални ли са? Бройките отговарят ли на реалността?"

→ **[Да, чисти са]** → пълен импорт (артикули + количества + 6 мес продажби)
→ **[Не съвсем]** → само артикули + количества, без история
→ **[Не знам]** → само артикули, без количества

**Ако избере "Нямам":** → Път Б (Hidden Inventory / Zone Walk — сканира/снимка/глас)

**Ако избере "Друга":** → универсален CSV шаблон с празни колони

## 12.3 Три стъпки на auto-detect

Адаптерът проверява суровия файл по три критерия:

| # | Стъпка | Какво определя |
|---|---|---|
| 1 | **Encoding** | Регион (CP1251=БГ, CP1250=PL/CZ/RO, CP1253=GR, UTF-8=облачни) |
| 2 | **Разделител** | Тип софтуер (`;`=локален, `,`=облачен, `\t`=Microinvest алт.) |
| 3 | **Header fingerprint** | Конкретна програма (уникални имена на колони) |

При sigurност **≥ 90%** → автоматично mapping. При **70-89%** → потребителско потвърждение. Под **70%** → ръчно mapping.

## 12.4 Приоритет на адаптерите (Фаза 1 → Фаза 6)

| Фаза | Адаптери | Кога |
|---|---|---|
| **1 — Бета** | Универсален CSV + Microinvest (БГ) | Преди ENI beta |
| **2 — БГ разширение** | Eltrade/Детелина, StoreHouse, GenSoft, Mistral, I-Cash | След бета |
| **3 — Румъния** | SmartBill (REST API), Sedona, SAGA C | При навлизане в RO |
| **4 — Гърция** | SoftOne, PRISMA Win, Pylon | При навлизане в GR |
| **5 — Облачни** | Loyverse, Shopify POS, Lightspeed | Глобално покритие |
| **6 — Per държава** | JTL (DE), Danea (IT), Factusol (ES), Subiekt (PL) | При навлизане |

**Мнемоника:** Microinvest (БГ) → SmartBill (РО) → SoftOne (ГР) → облачни (Loyverse/Shopify/Lightspeed).

## 12.5 Вариации — главният проблем

Международните облачни POS (Shopify, Loyverse, Lightspeed) групират вариациите чрез поле **Handle** — всички редове с еднакъв Handle = един артикул с различни размери/цветове.

Локалните програми (Microinvest, SAGA, StoreHouse) често експортват вариациите като **напълно отделни редове** без Parent ID:

```
Nike Air Max 90 - 42 - Черен;NAM-42-BK;...
Nike Air Max 90 - 43 - Черен;NAM-43-BK;...
Nike Air Max 90 - 42 - Бял;NAM-42-WH;...
```

**Решение — AI групиране (Gemini):**
1. **Regex базово групиране** — търси повтарящи се шаблони ("Nike Air Max 90" се среща 6 пъти)
2. **Gemini верификация** — определя майчино име, цветове, размери, confidence score
3. **Потребителска проверка при confidence < 85%** — "Тези 6 артикула изглеждат като един продукт. Правилно ли е?"
4. **Mapping към RunMyStore** — products + product_variants

## 12.6 Confidence при импорт

Импортираните артикули получават **60-90% confidence** — имат имена, кодове, цени, може количества, но не са физически потвърдени.

**За пълна точност се препоръчва Zone Walk след импорт.**

**Принцип:** Три месеца собствени данни в RunMyStore = по-точни от 2 години мръсна история от стара програма. Целта на импорта е бърз старт, не перфектни данни.

## 12.7 Препратка

Пълна спецификация — виж **DATA_MIGRATION_STRATEGY_v1.md** (29 секции, библиотека от формати, header fingerprints per програма, технически компоненти).

---

**КРАЙ НА BIBLE APPENDIX v3.1**
