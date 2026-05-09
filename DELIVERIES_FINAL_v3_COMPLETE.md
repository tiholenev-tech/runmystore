# 📦 МОДУЛ "ДОСТАВКИ" — ФИНАЛНА ПЪЛНА СПЕЦИФИКАЦИЯ v3

**Версия:** v3 COMPLETE (09.05.2026)
**Заместник на:** DELIVERIES_FINAL_v2.md (запазен в архив)
**Beta launch:** ENI Тихолов, 14-15.05.2026

---

## КАК ДА ИЗПОЛЗВАШ ТОЗИ ДОКУМЕНТ

Документът е **canonical** — заменя всеки предишен deliveries spec. Реди се **в този ред** при design/code task:

1. `DESIGN_PROMPT_v2_BICHROMATIC.md` (16 закона)
2. `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` (Bible)
3. `mockups/P13_bulk_entry.html` + `mockups/P10_lesny_mode.html` (visual canon)
4. **Този документ** (логика + connections + дизайн mapping)
5. `INVENTORY_HIDDEN_v3.md` (confidence rules)

Документът е разделен на **6 ЧАСТИ**:

| Част | Обхват |
|---|---|
| **Част 1** | Философия + архитектура + глобален Connection Map + цветове + API map |
| **Част 2** | Лесен режим — главен екран, AI карти, "Получи доставка" sheet, OCR + Voice flow |
| **Част 3** | Лесен режим — Manual + Import flow, mini-wizard, Smart Pricing |
| **Част 4** | Разширен режим — главен, KPI, status tabs, view modes, sort, chips, filter accordion |
| **Част 5** | Детайли — Detail на доставка/доставчик, меню (☰), bulk, audit, notifications, search |
| **Част 6** | Специални flow-ове, връзки модули, DB schema, edge cases, performance, accessibility, beta checklist |

---

# ═══════════════════════════════════════
# ЧАСТ 1 — АРХИТЕКТУРА & ВРЪЗКИ
# ═══════════════════════════════════════

## 1. ФИЛОСОФИЯ И ПРИНЦИПИ

### 1.1 Защо съществува модул "Доставки"

ENI получава **30-50 доставки месечно** (5 магазина × 6-10 доставки/магазин). Всяка доставка:
- Влиза в склад → нараства `inventory.quantity`
- Променя cost → актуализира `products.cost_price` + history
- Затваря `lost_demand` за чакащи артикули
- Добавя нови артикули в каталог (с `is_complete=0` ако недовършен)
- Тригерира печат на етикети
- Потенциално привързана към `supplier_orders` (sent → partial/received)

Без дисциплиниран процес — cost drift, грешки в марж, lost demand не се затваря, дубликати в каталог.

### 1.2 5 Закона приложими за модула

| # | Закон | Приложение в Доставки |
|---|---|---|
| **№1** | Пешо не пише | Voice бутон на ВСЯКО поле; OCR/Voice/Scan основни входове; manual е fallback |
| **№2** | PHP смята, AI вокализира | Confidence, margin %, печалба %, suggested cost — всичко в PHP. AI само speak. |
| **№3** | Никога "Gemini" в UI | Само "AI" |
| **№6** | Hidden Inventory | `confidence_score` НИКОГА visible на Митко (Бил, Иван, Стефан); skeleton+pulse при low conf, full opaque при ≥90% |
| **№11** | DB canonical names | `products.code` (НЕ sku), `products.cost_price` (НЕ buy_price), `products.retail_price` (НЕ sell_price), `inventory.quantity` (НЕ qty), `deliveries.status` ENUM('sent','acked','partial','received','overdue','cancelled') — 'cancelled' с две L |

### 1.3 Принципи на UX

1. **Inbox style, не Dashboard** в лесен режим — ясни задачи "ето какво се случва сега, какво да правиш"
2. **Progressive disclosure** — Пешо вижда минимум, Митко вижда всичко
3. **Confirm-first за destructive** — изтриване, отмяна, преместване
4. **One handed mobile** — 375px target, всички CTA-та достъпни с палец
5. **Никога не блокира работата** — offline 4 нива надолу, queue за sync
6. **AI = manager, не advisor** — формула "Number + Why + Soft suggestion", никога imperative

---

## 2. МЯСТО В НАВИГАЦИЯТА

### 2.1 Bottom nav (заключен от MASTER_COMPASS — НЕ ПИПАЙ)

```
┌─────────────────────────────────────┐
│  [🤖 AI]  [📦 Склад]  [📊 Справки]  [⚡ Продажба]  │
└─────────────────────────────────────┘
```

Доставки **НЕ са** в bottom nav. Живеят в **Склад hub**.

### 2.2 Йерархия

```
Bottom nav: [📦 Склад]
    ↓
Склад hub: warehouse.php
    ├─ [Артикули]      → products.php
    ├─ [Доставки]      → deliveries.php  ← ТОЗИ МОДУЛ
    ├─ [Поръчки]       → orders.php
    ├─ [Доставчици]    → suppliers.php
    ├─ [Трансфери]     → transfers.php
    └─ [Инвентаризация] → inventory.php
```

### 2.3 Достъп от Life Board (Пешо)

| Точка | Действие | Резултат |
|---|---|---|
| AI карта "Очаквана доставка днес" | Тап → | `deliveries.php?action=receive&order_id=X` |
| AI карта "Закъсняла доставка" | Тап → | `deliveries.php?filter=overdue` |
| AI карта "AI чернова поръчка" | Тап → | `orders.php?id=X&from=ai_draft` |
| AI карта "Lost demand готов" | Тап → | `deliveries.php?lost_resolved=true` |
| Бутон в op-grid (post-beta) | "Получи доставка" 4-та button | `deliveries.php?action=receive` |

### 2.4 Достъп от Митко (разширен)

| Точка | Действие |
|---|---|
| Склад hub `[Доставки]` | Главен deliveries.php (статуси/филтри/sort) |
| Прав път | `deliveries.php` (любим chip от меню) |
| Notification swipe | `deliveries.php?id=X&tab=items` |

### 2.5 Breadcrumb (top nav в детайл)

```
Склад › Доставки › #DEL-2026-0234
                     │
                     └─ [≪ назад към списък]   [⋯ меню]
```

---

## 3. ДВАТА РЕЖИМА — TOGGLE

### 3.1 Принципът

Toggle **per-модул** (НЕ глобален). Доставки имат собствен toggle който се запомня в localStorage `rms_mode_deliveries`.

| Лесен (Пешо) | Разширен (Митко) |
|---|---|
| Inbox style | Dashboard style |
| Макс 3 AI карти | 5-8 AI карти + filter |
| 1 голям бутон "Получи доставка" | KPI strip + status tabs + filters |
| Без статуси | Всички статуси |
| Без филтри | 17 секции филтри (accordion) |
| Без sort | 10 sortирания |
| Без bulk | Long-press → bulk select mode |
| Простата работа само | Всичко |

### 3.2 Как работи

- Default state при първо влизане: **Owner/Manager → разширен**, **Seller → лесен** (auto от `users.role`)
- Toggle UI: pill segments в горната част на screen, под header
- Toggle store: `localStorage.setItem('rms_mode_deliveries', 'extended')`
- Toggle persistence: запомня се за следващо влизане
- При role change (Митко вместо Пешо) → toggle се сбросва към role default

### 3.3 Toggle UI 1:1 от P13 (`.mode-toggle` от §3.3)

```html
<div class="mode-toggle" data-mode="extended">
  <button class="mode-tab" data-tab="easy" onclick="setMode('easy')">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="9"/>
    </svg>
    Лесен
  </button>
  <button class="mode-tab active" data-tab="extended" onclick="setMode('extended')">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
      <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
    </svg>
    Разширен
  </button>
</div>
```

**CSS** (от P13 `.mode-toggle`):
- Container: 4px padding, pill-shaped, neumorph pressed shadow в light, border + dark bg в dark
- Tab: flex:1, height:42px, font-size:12px, font-weight:800
- Active tab: linear-gradient(135deg, var(--accent), var(--accent-2)) + shadow `0 4px 14px hsl(var(--hue1) 80% 50% / 0.4)`

### 3.4 Архитектура на rendering

```
deliveries.php
    ↓
$mode = $_GET['mode'] ?? localStorage('rms_mode_deliveries') ?? defaultByRole($user)
    ↓
if ($mode === 'easy')
    include 'partials/deliveries-easy.php'   ← един template
else
    include 'partials/deliveries-extended.php'  ← различен template
    ↓
Shared: header, voice-bar, AI signals component, mode-toggle
```

---

## 4. ГЛОБАЛЕН CONNECTION MAP

Това е **най-важната диаграма** в документа. Описва как Доставки общува с останалите модули.

```
                                          ╔══════════════════════════╗
                                          ║   ДОСТАВКИ (deliveries)  ║
                                          ║      central hub         ║
                                          ╚════════════╤═════════════╝
                                                       │
   ┌───────────────────┬─────────────────┬─────────────┼─────────────┬──────────────────┬─────────────┐
   ▼                   ▼                 ▼             ▼             ▼                  ▼             ▼
┌────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌──────────┐  ┌────────────┐  ┌──────────┐
│PRODUCTS│  │  ORDERS    │  │ SUPPLIERS  │  │  INVENTORY │  │  SALE    │  │   STATS    │  │   CHAT   │
│        │  │            │  │            │  │            │  │          │  │            │  │ (Life Bd)│
└────────┘  └────────────┘  └────────────┘  └────────────┘  └──────────┘  └────────────┘  └──────────┘
   │            │                 │              │              │              │              │
   ▼            ▼                 ▼              ▼              ▼              ▼              ▼
   …           …                 …              …              …              …              …
```

### 4.1 → PRODUCTS (артикули)

**Какво се случва:**

| Trigger | Field в `products` | Field в `delivery_items` | Логика |
|---|---|---|---|
| Доставка пристига нов артикул | INSERT нов ред с `is_complete=0` | `product_id` ← новото id | OCR/voice екстрахира code/name; mini-wizard auto-fill; Пешо въвежда retail_price |
| Cost се променя ≥10% | UPDATE `cost_price` + history запис | — | Toast warning; AI signal в разширен; Smart Pricing Rules retrigger |
| Доставка получи `quantity` | UPDATE `inventory.quantity` (НЕ products) | — | Никога не пиши директно в products.quantity |
| Артикул `is_complete=0` влиза в доставка | Бадж "⚠ Недовършен" в каталог | `complete_after_delivery=true` | Wizard продължава от секция 2 при следващ tap |
| Cost history запис | INSERT в `cost_history(product_id, cost_old, cost_new, supplier_id, delivery_id, changed_at)` | — | Append-only, никога UPDATE |

**Deep links Доставки → Products:**

```
deliveries.php?id=123#item-456 → "Виж артикула" → products.php?id=456&from=delivery&delivery_id=123
products.php?id=456 → "Поръчай още" → orders.php?action=create&product_id=456
```

**Visual hint в products.php:**
- Артикул с `is_complete=0` показва pill "⚠ Недовършен" в horizontal list
- Тап → отваря wizard на P13 секция 2 (където беше прекратен)
- Filter "Само недовършени" в products.php → бърз достъп

### 4.2 → ORDERS (поръчки)

**Какво се случва:**

| Trigger | Field в `supplier_orders` | Field в `deliveries` | Логика |
|---|---|---|---|
| Доставка пристига от sent поръчка | UPDATE `status='received'` | INSERT row + `order_id=X` | Auto-link по `supplier_id + date_window`; AI suggest при ambiguity |
| Доставка частична | UPDATE `status='partial'` | INSERT row + `order_id=X` + `is_partial=1` | Останалите items стават нова "очакваща поръчка" |
| Доставка без свързана поръчка | — | INSERT row + `order_id=NULL` + `manual=1` | "Поръчай липсите" CTA → нова `supplier_orders` row |
| AI чернова сутрин (cron 03:00) | INSERT `supplier_orders.status='draft'` | — | compute-orders.php агрегира липси и създава draft per supplier |

**Deep links:**

```
deliveries.php?action=receive&order_id=789 → pre-fill items от orders
orders.php?id=789 → "Получи доставка" → deliveries.php?action=receive&order_id=789
deliveries.php?id=123 → "Поръчай липсите" → orders.php?action=create&from_delivery=123&missing=true
```

**Auto-link логика (PHP):**
```php
function findMatchingOrder($supplier_id, $delivery_date) {
    return DB::get("
        SELECT id, total_items, total_value
        FROM supplier_orders
        WHERE supplier_id = ?
          AND status IN ('sent', 'partial', 'acked')
          AND DATE_DIFF(?, created_at) BETWEEN 0 AND 14
        ORDER BY created_at DESC
        LIMIT 1
    ", [$supplier_id, $delivery_date]);
}
// Ако ≥2 матча: AI пита Митко "Това е срещу поръчка #789 (10.05) или #802 (07.05)?"
// Ако 0 матча: status='manual', "Поръчай липсите" CTA
```

### 4.3 → SUPPLIERS (доставчици)

**Какво се случва:**

| Trigger | Field в `suppliers` | Логика |
|---|---|---|
| Доставка получена | UPDATE `last_delivery_date`, `total_deliveries++`, `total_value += X` | Stats trigger (cron) |
| Доставка закъсняла >3 дни | UPDATE `lateness_days_avg` | AI signal "Доставчик X се забавя" |
| Доставка с проблем (липса/повреда) | INSERT `supplier_issues` + UPDATE `reliability_score` | AI signal "X грешки от 10" |
| Cost change | UPDATE `last_cost_change_date` | Cost trends в Detail |

**Deep links:**

```
deliveries.php?id=123 → header tap на "Доставчик: Емпорио ООД" → suppliers.php?id=42
suppliers.php?id=42 → "Поръчай" → orders.php?action=create&supplier_id=42
suppliers.php?id=42 → "История" → deliveries.php?supplier_id=42&view=by_supplier
```

**Shared детайл компонент:**
Detail на доставчик в deliveries.php = **същия компонент** като в orders.php — `partials/supplier-detail.php`. Показва KPI grid, history, notes, AI препоръки.

### 4.4 → INVENTORY (склад/количества)

**Какво се случва:**

| Trigger | Field в `inventory` | Логика |
|---|---|---|
| Доставка получена | INSERT/UPDATE per `(product_id, store_id)` | `quantity += delivered_qty` |
| Multi-store split | INSERT един row на всеки магазин | Per `delivery_items.target_store_id` |
| Negative stock resolved | UPDATE `quantity` от -3 → +X | Audit trail в `inventory_events` |
| Category count trigger | INSERT `inventory_audits` row | "Хайде да броим тениски" — AI signal |

**Confidence boost (Hidden Inventory §6):**
- Преди доставка: `confidence_score=0.65` за артикули които не са броени отдавна
- Доставка пристига → `+0.40` boost (max 1.0)
- Никога visible на Митко; влияе само на UI hints (skeleton vs full)

**Deep links:**

```
deliveries.php?id=123 → tab "Артикули" → tap артикул → inventory.php?product_id=X (показва текущо количество per магазин)
inventory.php → "Direct adjustment +5" → НЕ преминава през доставки (manual override)
```

### 4.5 → SALE (продажба)

**Какво се случва:**

| Trigger | Field в `sales` | Логика |
|---|---|---|
| Доставка пристига → ghost product match | UPDATE `sale_items.product_id` (от ghost → real) | Lost demand cycle затваря се |
| Cost change потенциално променя marж | (nothing) | Future stat: AI signal "Margin намалява" |

**Lost demand resolved cycle:**
```
1. Sale.php скенира barcode → продукт няма в каталог
2. Sale продължава с "ghost product" → INSERT в sale_items с product_id=NULL + код в notes
3. AI fuzzy match по код/име → INSERT в lost_demand
4. Cron (06:30) групира lost_demand по продукт
5. AI signal "Изпускаш €145 на ден от тениска синя L"
6. Доставка пристига → matcher намира съвпадение по code → UPDATE sale_items.product_id за висящи ghost-и
7. AI signal в Life Board: "Lost demand от тениска синя L затворен — €420 възстановени"
```

**Deep links:**

```
deliveries.php?id=123 → "Виж lost demand resolved" → stats.php?type=lost_demand_resolved&delivery_id=123
sale.php → ghost product → notification → "Доставката пристига утре"
```

### 4.6 → STATS (справки)

**Какво се случва:**

| Stat | Source data |
|---|---|
| Cost trends per product | `cost_history` |
| Supplier scorecard | `suppliers.reliability_score`, `lateness_days_avg`, `total_deliveries`, `total_issues` |
| Lead time tracking | `supplier_orders.created_at` → `deliveries.received_at` |
| ROI на AI чернова | `supplier_orders.is_ai_draft=1` × `delivery.success` |
| Delivery accuracy | `delivery_items.expected_qty` vs `delivered_qty` |
| Доход от lost demand resolved | sum(`lost_demand.estimated_loss` WHERE resolved_via_delivery=1) |

**Deep links:**

```
deliveries.php → меню (☰) "Справки за доставки" → stats.php?category=deliveries
stats.php?supplier=42 → "Виж доставки" → deliveries.php?supplier_id=42
```

### 4.7 → CHAT / Life Board (AI Brain)

**Какво се случва:**
AI signals от deliveries module се показват в Life Board home screen (P10) и в самия chat. 40 deliv_* topic-а в `S51_AI_TOPICS_MASTER.md`.

| Signal type | Trigger | Where shown |
|---|---|---|
| `deliv_overdue` | `status='overdue' OR (sent_at + lead_time + 3d < now())` | Life Board (Пешо), deliveries.php (Митко) |
| `deliv_expected_today` | `expected_at = today` | Life Board (Пешо) |
| `deliv_ai_draft_ready` | `supplier_orders.is_ai_draft=1 AND created_at = today` | Life Board (Митко) |
| `deliv_cost_increase` | `cost_change_pct >= 10%` | Chat (Митко), Detail на артикул |
| `deliv_lost_demand_resolved` | След получаване → matcher намира closure | Life Board (всички) |
| `deliv_margin_warning` | `(retail-cost)/retail < 25%` | Chat (Митко) |
| `deliv_supplier_late_avg` | `suppliers.lateness_days_avg > 5d` over 10 deliv | Chat (Митко) |
| `deliv_partial_delivery` | `status='partial'` | Life Board (всички) |

**Cooldown:** 24 часа per topic per tenant. AI signals никога не повтарят същата тема в рамките на ден.

**Phased rollout (06_anti_hallucination):**
- Фаза 1 (бета): 0% AI генерирани signals — всички темплейти
- Фаза 2: 30% AI, 70% template
- Фаза 3: 80% AI, 20% template (final)

### 4.8 → LOYALTY (post-beta)

**Какво се случва:**
Само пост-бета. Доставки нямат директна връзка с loyalty освен:
- При lost demand resolved → ако клиентът е loyalty member с notify preference, изпраща се SMS "Артикулът дойде — ела вземи"

### 4.9 → TRANSFERS (post-beta)

Когато transfers.php е готов:
- Doставка пристига в магазин 1 → split prompt "Раздели по магазини" → INSERT `transfers` rows
- transfer_id reference в delivery_items.split_reference

---

## 5. API ENDPOINT MAPPING

```
GET    /deliveries.php                    → главен списък (mode dependent)
GET    /deliveries.php?id=X               → detail
GET    /deliveries.php?action=receive     → flow за нова доставка (sheet)
GET    /deliveries.php?action=receive&order_id=Y → pre-filled от поръчка
GET    /deliveries.php?supplier_id=X      → филтър по доставчик
GET    /deliveries.php?status=overdue     → филтър по статус
GET    /deliveries.php?lost_resolved=true → специален view

POST   /api/deliveries/create             → нова доставка (OCR/voice/manual/import)
POST   /api/deliveries/X/items            → добави item
PATCH  /api/deliveries/X                  → update meta (cost, supplier, notes)
PATCH  /api/deliveries/X/items/Y          → update item (qty, cost, retail_price)
DELETE /api/deliveries/X/items/Y          → remove item (audit logged)
POST   /api/deliveries/X/finalize         → close + push inventory + close lost_demand
POST   /api/deliveries/X/cancel           → cancel (admin only, audit)
POST   /api/deliveries/X/split            → split по магазини
POST   /api/deliveries/X/print-labels     → trigger Bluetooth printer

POST   /api/ocr/scan                      → scanner_documents INSERT + jobid
GET    /api/ocr/scan/JOBID                → poll за result + confidence
POST   /api/voice/parse                   → STT → struct
POST   /api/products/quick-create         → mini-wizard от delivery (is_complete=0)
GET    /api/suppliers/match?date=X&keyword=Y → AI fuzzy match за supplier
GET    /api/orders/match?supplier_id=X&date=Y → auto-link suggestion
GET    /api/cost-history?product_id=X     → history за detail
GET    /api/lost-demand/resolved?delivery_id=X → cycle close audit
```

---

## 6. ЦВЕТОВА СЕМАНТИКА (6 hue класа от Bible §16)

Всеки UI компонент в Доставки **задължително** използва един от 6 hue класа. Никога нов цвят.

| Клас | hue1/hue2 | OKLCH (light) | HSL (dark) | Употреба в Доставки |
|---|---|---|---|---|
| **q-default** | 255/222 | oklch(0.62 0.22 285) | hsl(255 80% 65%) | Generic карти, primary CTA, "Получи доставка" бутон, default detail |
| **q-magic** | 280/310 | oklch(0.65 0.25 310) | hsl(280 70% 65%) | AI signals, AI чернова, voice bar, mini-wizard "magic" секции |
| **q-loss** | 0/15 | oklch(0.65 0.22 25) | hsl(0 85% 60%) | Closure (закъсняла), cost увеличение, supplier late, error toasts |
| **q-gain** | 145/165 | oklch(0.68 0.18 155) | hsl(145 70% 55%) | Получена доставка, lost demand resolved, success toasts, filled accordion |
| **q-amber** | 38/28 | oklch(0.72 0.18 70) | hsl(38 90% 60%) | Чакащи (sent/acked), warnings, partial delivery, "Поръчай липси" CTA |
| **q-jewelry** | 195 | oklch(0.78 0.18 195) | hsl(195 70% 55%) | (post-beta only — luxury items в детайл) |

### 6.1 Mapping за компоненти в Доставки

| Компонент | Hue клас | Защо |
|---|---|---|
| Главен бутон "Получи доставка" в лесен | q-default | Primary CTA |
| AI карта "Очаквана днес" | q-magic | AI suggestion |
| AI карта "Закъсняла доставка" | q-loss | Урgenc/проблем |
| AI карта "Lost demand resolved" | q-gain | Победа |
| AI карта "AI чернова поръчка" | q-magic | AI |
| Status pill "Получена" | q-gain | Success |
| Status pill "Частична" | q-amber | Warning |
| Status pill "Закъсняла" | q-loss | Алармa |
| Status pill "Чакаща" | q-amber | Pending |
| KPI cell "Стойност получено" | q-default | Neutral metric |
| KPI cell "Чакащи €" | q-amber | Pending value |
| KPI cell "Закъсняли" | q-loss | Problem count |
| Detail header за получена | q-gain | OK state |
| Detail header за закъсняла | q-loss | Алармa |
| OCR review confident <75% | q-loss | Reject zone |
| OCR review 75-92% | q-amber | Smart UI |
| OCR review >92% | q-gain | Auto-accept |
| Bulk select mode | q-magic | "Magic" multi-select |
| Cost increase warning ≥10% | q-loss | Анти-cost |
| Cost decrease ≥5% | q-gain | Saving |

### 6.2 Точни стойности за компоненти в dark mode (sacred neon glass)

```css
/* AI signal q-magic в Доставки */
.deliv-ai-signal.q-magic::before {
  background: conic-gradient(from 220deg at 50% 0%,
    oklch(0.7 0.22 280 / 0.8) 0deg,
    oklch(0.65 0.25 305 / 0.6) 90deg,
    oklch(0.7 0.22 280 / 0.4) 180deg,
    oklch(0.6 0.2 285 / 0.3) 270deg,
    oklch(0.7 0.22 280 / 0.8) 360deg);
}
.deliv-ai-signal.q-magic::after {
  background: radial-gradient(80% 60% at 50% 0%,
    oklch(0.72 0.24 280 / 0.28),
    oklch(0.7 0.22 305 / 0.18) 50%,
    transparent 70%);
}

/* AI signal q-loss за overdue */
.deliv-ai-signal.q-loss::before {
  background: conic-gradient(from 220deg at 50% 0%,
    oklch(0.65 0.22 25 / 0.8) 0deg,
    oklch(0.6 0.24 15 / 0.6) 90deg,
    oklch(0.65 0.22 25 / 0.4) 180deg,
    oklch(0.6 0.2 30 / 0.3) 270deg,
    oklch(0.65 0.22 25 / 0.8) 360deg);
}

/* AI signal q-gain за resolved */
.deliv-ai-signal.q-gain::before {
  background: conic-gradient(from 220deg at 50% 0%,
    oklch(0.7 0.18 145 / 0.8) 0deg,
    oklch(0.68 0.18 155 / 0.6) 90deg,
    oklch(0.7 0.18 145 / 0.4) 180deg,
    oklch(0.65 0.16 145 / 0.3) 270deg,
    oklch(0.7 0.18 145 / 0.8) 360deg);
}
```

---

## 7. КАК ИЗГЛЕЖДА ВСЕКИ КОМПОНЕНТ — БАЗА (от P13/P10)

В частите по-долу всеки flow ще цитира **точно** кой клас се копира. Тук е общата база.

### 7.1 Header (sticky, 56px) — заето от P13

```html
<header class="bm-header">
  <button class="icon-btn" onclick="history.back()">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <polyline points="15 18 9 12 15 6"/>
    </svg>
  </button>
  <div class="bm-title">Доставки</div>
  <button class="icon-btn search-btn">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
  </button>
  <button class="icon-btn scan-btn">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <path d="M3 7V5a2 2 0 012-2h2"/><path d="M17 3h2a2 2 0 012 2v2"/>
      <path d="M21 17v2a2 2 0 01-2 2h-2"/><path d="M7 21H5a2 2 0 01-2-2v-2"/>
      <line x1="7" y1="12" x2="17" y2="12"/>
    </svg>
  </button>
  <button class="icon-btn" id="themeToggle" onclick="rmsToggleTheme()">
    <!-- sun/moon icons -->
  </button>
</header>
```

7 елемента max (Bible §15).

### 7.2 Voice command bar — заето от P13

```html
<button class="voice-bar" onclick="startVoice()">
  <span class="voice-bar-mic">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <rect x="9" y="2" width="6" height="12" rx="3"/>
      <path d="M5 10v2a7 7 0 0014 0v-2"/>
    </svg>
  </span>
  <div class="voice-bar-text">
    <div class="voice-bar-title">Кажи на AI</div>
    <div class="voice-bar-sub">"получих доставка от Емпорио"</div>
  </div>
</button>
```

CSS:
- Light: `linear-gradient(135deg, oklch(0.94 0.05 285 / 0.6), oklch(0.94 0.05 310 / 0.5))` + `var(--shadow-card-sm)`
- Dark: `linear-gradient(135deg, hsl(280 30% 12% / 0.5), hsl(var(--hue1) 30% 12% / 0.4))` + border
- Mic conic shimmer: `conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.4) 85%, transparent 100%)` + `animation: conicSpin 4s linear infinite`

### 7.3 Aurora background

3 blobs (от P10), `animation: auroraDrift 20s ease-in-out infinite`. blur(60px). opacity 0.32 light / 0.35 dark.

---

## КРАЙ НА ЧАСТ 1

**Какво следва в Част 2:**
- Лесен режим — главен екран в детайл (layout, header, voice bar, AI карти, op-button)
- AI signal карта компонент 1:1 (от P13 acc-section + P10 lb-card)
- Empty state на лесен режим
- Loading state на лесен режим
- "Получи доставка" sheet (4 опции) — пълен design
- OCR flow от край-до-край с UI screens
- Voice flow с continuous scan

---

# ═══════════════════════════════════════
# ЧАСТ 2 — ЛЕСЕН РЕЖИМ (Пешо)
# ═══════════════════════════════════════

## 8. ГЛАВЕН ЕКРАН НА ЛЕСЕН РЕЖИМ

### 8.1 Цялостен layout (375px Z Flip6)

```
┌──────────────────────────────────────┐
│ [≪] Доставки   [🔍] [📷] [🌙]        │ ← header 56px (sticky)
├──────────────────────────────────────┤
│                                      │
│ [🎤] Кажи на AI                      │ ← voice bar (10px gap)
│      "получих от Емпорио"            │
│                                      │
│ ┌────────────────┬────────────────┐  │ ← mode toggle pill
│ │  ●  Лесен      │      Разширен  │  │   (active = q-default gradient)
│ └────────────────┴────────────────┘  │
│                                      │
│ ┌──────────────────────────────────┐ │
│ │ ⚠ Закъсняла доставка             │ │ ← AI карта 1 (q-loss)
│ │ Емпорио ООД, 3 дни закъснение    │ │
│ │ Очаквани 15 артикула за €840     │ │
│ │                                   │ │
│ │ [🔄 Питай доставчика]            │ │
│ └──────────────────────────────────┘ │
│                                      │
│ ┌──────────────────────────────────┐ │
│ │ 📦 Очаквана доставка днес        │ │ ← AI карта 2 (q-magic)
│ │ Бутик Мария — обикновено 14:00ч  │ │
│ │                                   │ │
│ │ [📷 Снимай фактура]              │ │
│ └──────────────────────────────────┘ │
│                                      │
│ ┌──────────────────────────────────┐ │
│ │ ✓ Lost demand затворен           │ │ ← AI карта 3 (q-gain)
│ │ Тениска синя L → €420 възста-    │ │
│ │ новени                            │ │
│ │                                   │ │
│ │ [👀 Виж детайли]                 │ │
│ └──────────────────────────────────┘ │
│                                      │
│ ╔══════════════════════════════════╗ │
│ ║                                  ║ │ ← голям бутон "Получи дост."
│ ║      📦 Получи доставка          ║ │   (q-default gradient + conic shimmer)
│ ║                                  ║ │   60px height, full-width
│ ║      Снимай · Кажи · Сканирай    ║ │
│ ╚══════════════════════════════════╝ │
│                                      │
│ ──────────────────────────────────── │
│ [📋 Нова поръчка]                    │ ← малък secondary бутон
│                                      │
├──────────────────────────────────────┤
│ [🤖] [📦] [📊] [⚡]                  │ ← bottom nav 86px (sticky)
└──────────────────────────────────────┘
```

### 8.2 Какво вижда Пешо

1. **Voice bar** (винаги горе, винаги достъпен)
2. **Toggle** (за да премине към разширен ако трябва — но няма повод обикновено)
3. **0-3 AI карти** (приоритет: критично → информативно → победно)
4. **"Получи доставка"** — главен бутон, винаги видим
5. **"Нова поръчка"** — secondary, по-малък

### 8.3 Какво НЕ вижда Пешо

| Скрито | Защо |
|---|---|
| KPI strip (стойности, броеве) | Информационен шум |
| Status tabs (Чакат/Готови/История) | Разрешава се чрез "Виж всички" link от AI карта |
| Sortирания | Не му трябва |
| Филтри | Не му трябва |
| Списък на всички доставки | Не му трябва |
| Bulk операции | Никога |
| Confidence scores | Hidden Inventory §6 |
| Margin %, Печалба %, Cost change | Тайна само за Митко |
| Меню (☰) с 6 секции | Заменено с voice bar |

### 8.4 Дизайн tokens (1:1 от P13/P10)

| Елемент | Token / Class |
|---|---|
| Background | Aurora 3 blobs + linear gradient (P10 §body) |
| Header | `.bm-header` (56px sticky) |
| Voice bar | `.voice-bar` + `.voice-bar-mic` (conic shimmer) |
| Mode toggle | `.mode-toggle` (pill с 4px padding) |
| AI карти | `.lb-card` (P10) с hue class |
| Голям бутон | `.op-btn` (P10) full-width + конкретен `.deliv-cta-receive` |
| Secondary бутон | `.lb-action` (P10 lb-action) |
| Spacing между карти | 12px gap |
| App padding | 12px (`.app{padding:12px}` от P13) |
| Shell max-width | 480px (`.shell` от P13) |

---

## 9. AI СИГНАЛИ В ЛЕСЕН РЕЖИМ

### 9.1 Колко

- **Минимум:** 0 (празно състояние ако нищо не се случва)
- **Максимум:** 3 карти едновременно
- Никога не прекалява, никога не претрупва

### 9.2 Какво се показва (приоритет)

Ред на приоритет за селекция (rule-based, не AI):

| Приоритет | Trigger | Hue клас | Cooldown |
|---|---|---|---|
| **P0 — критично** | Закъсняла доставка >3д | q-loss | 6 ч |
| **P0** | Очаквана доставка днес | q-magic | 12 ч |
| **P1 — задача** | AI чернова поръчка | q-magic | 24 ч |
| **P1** | Cost увеличение ≥10% | q-loss | 24 ч |
| **P2 — информация** | Lost demand resolved | q-gain | 24 ч |
| **P2** | "Хайде да броим" (category count trigger) | q-magic | 48 ч |
| **P3 — saver** | Доставчик предлага по-добра цена | q-gain | 7 дни |

Ако P0 не съществува → показва P1. Ако P1 не → P2. И т.н.

### 9.3 Структура на AI карта в лесен режим

```html
<div class="lb-card q-loss" data-topic="deliv_overdue" data-id="123">
  <div class="lb-card-head">
    <div class="lb-emoji-orb">
      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
        <!-- truck-late SVG -->
      </svg>
    </div>
    <div class="lb-card-text">
      <div class="lb-card-title">Закъсняла доставка</div>
      <div class="lb-card-sub">Емпорио ООД · 3 дни закъснение</div>
    </div>
  </div>
  <div class="lb-card-body">
    Очаквани 15 артикула за €840
  </div>
  <div class="lb-card-actions">
    <button class="lb-action q-loss">
      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
        <!-- refresh SVG -->
      </svg>
      Питай доставчика
    </button>
    <button class="lb-feedback" data-feedback="dismiss">
      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
        <!-- x SVG -->
      </svg>
    </button>
  </div>
</div>
```

**CSS recipe (1:1 от P10 `.lb-card`):**

```css
.lb-card{
  position:relative;
  padding:14px;
  border-radius:var(--radius);
  margin-bottom:12px;
}
.lb-card > * { position:relative; z-index:5; }

[data-theme="light"] .lb-card,
:root:not([data-theme]) .lb-card {
  background:var(--surface);
  box-shadow:var(--shadow-card);
}

[data-theme="dark"] .lb-card {
  background:hsl(220 25% 6% / 0.7);
  backdrop-filter:blur(8px);
  border:1px solid hsl(var(--hue2) 12% 18%);
}

/* Sacred Neon Glass (dark mode) */
[data-theme="dark"] .lb-card::before,
[data-theme="dark"] .lb-card::after {
  content:'';
  position:absolute;
  pointer-events:none;
  border-radius:inherit;
  mix-blend-mode:plus-lighter;
}

[data-theme="dark"] .lb-card::before{
  inset:0;
  padding:1px;
  background:conic-gradient(from 220deg at 50% 0%,
    oklch(0.7 0.18 var(--hue1) / 0.8) 0deg,
    oklch(0.65 0.2 280 / 0.6) 90deg,
    oklch(0.7 0.18 var(--hue2) / 0.4) 180deg,
    oklch(0.6 0.16 var(--hue1) / 0.3) 270deg,
    oklch(0.7 0.18 var(--hue1) / 0.8) 360deg);
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
  -webkit-mask-composite:xor;
  mask-composite:exclude;
  opacity:0.7;
}

[data-theme="dark"] .lb-card::after{
  inset:-1px;
  background:radial-gradient(80% 60% at 50% 0%,
    oklch(0.7 0.2 var(--hue1) / 0.18),
    transparent 70%);
  opacity:0.9;
  filter:blur(12px);
}

/* q-loss override */
[data-theme="dark"] .lb-card.q-loss::before {
  background: conic-gradient(from 220deg at 50% 0%,
    oklch(0.65 0.22 25 / 0.8) 0deg,
    oklch(0.6 0.24 15 / 0.6) 90deg,
    oklch(0.65 0.22 25 / 0.4) 180deg,
    oklch(0.6 0.2 30 / 0.3) 270deg,
    oklch(0.65 0.22 25 / 0.8) 360deg);
}
```

### 9.4 lb-emoji-orb per topic (q-loss / q-magic / q-gain)

```css
.lb-card.q-loss .lb-emoji-orb { background: hsl(0 50% 92%); }
[data-theme="dark"] .lb-card.q-loss .lb-emoji-orb { background: hsl(0 50% 12%); }
.lb-card.q-loss .lb-emoji-orb svg { stroke: hsl(0 70% 50%); }
[data-theme="dark"] .lb-card.q-loss .lb-emoji-orb svg { stroke: hsl(0 80% 70%); }

.lb-card.q-magic .lb-emoji-orb { background: hsl(280 50% 92%); }
[data-theme="dark"] .lb-card.q-magic .lb-emoji-orb { background: hsl(280 50% 12%); }
.lb-card.q-magic .lb-emoji-orb svg { stroke: hsl(280 70% 50%); }
[data-theme="dark"] .lb-card.q-magic .lb-emoji-orb svg { stroke: hsl(280 70% 70%); }

.lb-card.q-gain .lb-emoji-orb { background: hsl(145 50% 92%); }
[data-theme="dark"] .lb-card.q-gain .lb-emoji-orb { background: hsl(145 50% 12%); }
.lb-card.q-gain .lb-emoji-orb svg { stroke: hsl(145 70% 40%); }
[data-theme="dark"] .lb-card.q-gain .lb-emoji-orb svg { stroke: hsl(145 70% 65%); }
```

### 9.5 SVG icons за всеки топик

| Topic | Icon (paths) | Source |
|---|---|---|
| `deliv_overdue` | truck с clock наслагване | custom (truck + circle clock 12) |
| `deliv_expected_today` | truck + arrow right | DESIGN_PROMPT §4 [truck/delivery] |
| `deliv_ai_draft_ready` | sparkles | DESIGN_PROMPT §4 [magic-sparkles] |
| `deliv_cost_increase` | trending-up + arrow с warn | custom (trend-up red) |
| `deliv_lost_demand_resolved` | check-circle + box | custom (check + box) |
| `deliv_supplier_late_avg` | clock alert | custom |
| `deliv_partial_delivery` | box-open (полу) | custom |

### 9.6 Cooldown

- 24 часа per topic per tenant — никога не повтаря същата тема в рамките на ден
- Cooldown checked в PHP при селекция: `last_shown_at + cooldown_seconds > NOW() → SKIP`
- Стои в таблица `ai_topic_cooldown(tenant_id, topic_key, last_shown_at, dismissed_at)`

### 9.7 Phased rollout (от 06_anti_hallucination)

| Phase | Какво | Когато |
|---|---|---|
| Phase 1 (бета) | 100% template (без AI генерация) | 14-15.05.2026 |
| Phase 2 | 30% AI, 70% template | след 30 дни | 
| Phase 3 | 80% AI, 20% template | след 90 дни |

Template примери:
- `"Закъсняла доставка от {supplier_name} — {days} дни закъснение. Очаквани {item_count} артикула за {value}."`
- `"Очаквана доставка днес от {supplier_name}. Обикновено пристига около {time}."`
- `"AI чернова поръчка готова — {supplier_name}, {item_count} артикула за {value}. Прегледай и изпрати."`

### 9.8 Feedback

Всяка карта има discrete `[X]` бутон в горния десен ъгъл за dismiss. Dismiss → `ai_topic_cooldown.dismissed_at = NOW()` → cooldown extended до 7 дни.

---

## 10. БУТОН "ПОЛУЧИ ДОСТАВКА" — ГЛАВЕН CTA

### 10.1 Визуална структура

```html
<button class="op-btn deliv-receive q-default" onclick="openReceiveSheet()">
  <span class="op-icon">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <rect x="1" y="3" width="15" height="13" rx="1"/>
      <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
      <circle cx="5.5" cy="18.5" r="2.5"/>
      <circle cx="18.5" cy="18.5" r="2.5"/>
    </svg>
  </span>
  <div class="op-text">
    <div class="op-label">Получи доставка</div>
    <div class="op-sub">Снимай · Кажи · Сканирай</div>
  </div>
  <span class="op-chevron">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <polyline points="9 18 15 12 9 6"/>
    </svg>
  </span>
</button>
```

### 10.2 CSS (от P10 .op-btn + custom)

```css
.op-btn{
  width:100%;
  min-height:60px;
  padding:14px 18px;
  border-radius:var(--radius);
  display:flex;
  align-items:center;
  gap:14px;
  position:relative;
  overflow:hidden;
  margin:18px 0 12px;
}
.op-btn > * { position:relative; z-index:5; }

.op-btn.q-default{
  background:linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow:0 8px 24px hsl(var(--hue1) 80% 50% / 0.4);
  color:white;
}

/* Conic shimmer overlay */
.op-btn.q-default::before{
  content:'';
  position:absolute; inset:0;
  background:conic-gradient(from 0deg,
    transparent 70%, rgba(255,255,255,0.3) 85%, transparent 100%);
  animation:conicSpin 5s linear infinite;
  z-index:1;
  pointer-events:none;
}

.op-btn:active{ transform:scale(0.98); transition:transform 100ms ease; }

.op-btn .op-icon{
  width:42px; height:42px;
  border-radius:var(--radius-sm);
  background:rgba(255,255,255,0.18);
  display:grid; place-items:center;
  flex-shrink:0;
}
.op-btn .op-icon svg{ width:22px; height:22px; }

.op-btn .op-text{ flex:1; min-width:0; text-align:left; }
.op-btn .op-label{ font-size:16px; font-weight:800; letter-spacing:-0.01em; }
.op-btn .op-sub{
  font-family:var(--font-mono);
  font-size:10px; font-weight:700;
  opacity:0.9; letter-spacing:0.06em;
  text-transform:uppercase; margin-top:2px;
}

.op-btn .op-chevron{ width:24px; height:24px; opacity:0.85; }
.op-btn .op-chevron svg{ width:18px; height:18px; }
```

### 10.3 Tap interaction

- Tap → `transform: scale(0.98)` за 100ms feedback
- Long-press 800ms → бърз shortcut директно към default option (camera)
- Voice trigger ("получи доставка") → също отваря sheet-а

---

## 11. EMPTY STATE (никакви AI карти)

Когато няма AI карти — какво вижда Пешо?

```
┌──────────────────────────────────────┐
│ [≪] Доставки   [🔍] [📷] [🌙]        │
├──────────────────────────────────────┤
│ [🎤] Кажи на AI                      │
│ ┌────────────────┬────────────────┐  │
│ │  ●  Лесен      │      Разширен  │  │
│ └────────────────┴────────────────┘  │
│                                      │
│         ┌───────────────┐            │
│         │   📦 Празно   │            │
│         └───────────────┘            │
│                                      │
│      Няма доставки за внимание       │
│      Можеш да получиш нова сега      │
│                                      │
│ ╔══════════════════════════════════╗ │
│ ║      📦 Получи доставка          ║ │
│ ╚══════════════════════════════════╝ │
│                                      │
│ [📋 Нова поръчка]                    │
└──────────────────────────────────────┘
```

**HTML/CSS empty state:**

```html
<div class="deliv-empty">
  <div class="deliv-empty-orb">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <path d="M12.89 1.45l8 4A2 2 0 0122 7.24v9.53a2 2 0 01-1.11 1.79l-8 4..."/>
      <polyline points="2.32 6.16 12 11 21.68 6.16"/>
    </svg>
  </div>
  <div class="deliv-empty-title">Няма доставки за внимание</div>
  <div class="deliv-empty-sub">Можеш да получиш нова сега</div>
</div>
```

```css
.deliv-empty{
  text-align:center;
  padding:32px 20px;
  margin:24px 0;
}
.deliv-empty-orb{
  width:64px; height:64px;
  border-radius:50%;
  margin:0 auto 16px;
  display:grid; place-items:center;
  background:var(--surface);
  box-shadow:var(--shadow-pressed);
}
[data-theme="dark"] .deliv-empty-orb{
  background:hsl(220 25% 8%);
  border:1px solid hsl(var(--hue2) 12% 18%);
}
.deliv-empty-orb svg{ width:28px; height:28px; stroke:var(--text-muted); }

.deliv-empty-title{
  font-size:14px; font-weight:800;
  color:var(--text);
  margin-bottom:6px;
}
.deliv-empty-sub{
  font-family:var(--font-mono);
  font-size:10px; font-weight:700;
  color:var(--text-muted);
  letter-spacing:0.04em;
}
```

---

## 12. LOADING STATE

При първо зареждане на лесен режим (преди AI карти да са изчислени):

**Skeleton shimmer (3 карти placeholder):**

```html
<div class="deliv-skeleton">
  <div class="lb-card-skel"></div>
  <div class="lb-card-skel"></div>
  <div class="lb-card-skel"></div>
</div>
```

```css
.lb-card-skel{
  height:96px;
  border-radius:var(--radius);
  background:var(--surface);
  box-shadow:var(--shadow-card);
  margin-bottom:12px;
  position:relative;
  overflow:hidden;
}
.lb-card-skel::after{
  content:'';
  position:absolute; inset:0;
  background:linear-gradient(105deg,
    transparent 30%,
    rgba(255,255,255,0.08) 50%,
    transparent 70%);
  animation:shimmerSlide 1.6s ease-in-out infinite;
}
[data-theme="dark"] .lb-card-skel{
  background:hsl(220 25% 6% / 0.7);
  border:1px solid hsl(var(--hue2) 12% 18%);
}
[data-theme="dark"] .lb-card-skel::after{
  background:linear-gradient(105deg,
    transparent 30%,
    hsl(var(--hue1) 50% 30% / 0.15) 50%,
    transparent 70%);
}
```

Бутонът "Получи доставка" е видим веднага (не чака AI карти).

---

## 13. "ПОЛУЧИ ДОСТАВКА" — BOTTOM SHEET С 4 ОПЦИИ

### 13.1 Trigger

- Tap на голям бутон "Получи доставка" в лесен режим
- Tap на shortcut в op-grid (post-beta — 4-та button до AI/Склад/Справки/Продажба)
- Voice "получи доставка" / "доставка" / "пристигна доставка"

### 13.2 Sheet structure (от P13 §3.9 .gs-ov / .gs-sheet)

```html
<div class="gs-ov" id="receiveSheet">
  <div class="gs-sheet">
    <div class="gs-handle"></div>
    <div class="gs-title">Как да получиш доставката?</div>
    <div class="gs-sub">избери начин</div>

    <div class="receive-options">
      <!-- Option 1: OCR -->
      <button class="receive-opt q-magic" onclick="startOCR()">
        <span class="receive-opt-orb">
          <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
            <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
        </span>
        <div class="receive-opt-text">
          <div class="receive-opt-label">Снимай фактура</div>
          <div class="receive-opt-sub">AI разпознава за 4-7 секунди</div>
        </div>
        <span class="receive-opt-chevron">→</span>
      </button>

      <!-- Option 2: Voice -->
      <button class="receive-opt q-default" onclick="startVoice()">
        <span class="receive-opt-orb">
          <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
            <rect x="9" y="2" width="6" height="12" rx="3"/>
            <path d="M5 10v2a7 7 0 0014 0v-2"/>
          </svg>
        </span>
        <div class="receive-opt-text">
          <div class="receive-opt-label">Кажи какво има</div>
          <div class="receive-opt-sub">"5 тениски, 3 дънки, цена 320"</div>
        </div>
        <span class="receive-opt-chevron">→</span>
      </button>

      <!-- Option 3: Manual / Scan -->
      <button class="receive-opt q-default" onclick="startManual()">
        <span class="receive-opt-orb">
          <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
            <path d="M3 7V5a2 2 0 012-2h2"/><path d="M17 3h2a2 2 0 012 2v2"/>
            <path d="M21 17v2a2 2 0 01-2 2h-2"/><path d="M7 21H5a2 2 0 01-2-2v-2"/>
            <line x1="7" y1="12" x2="17" y2="12"/>
          </svg>
        </span>
        <div class="receive-opt-text">
          <div class="receive-opt-label">Сканирай един по един</div>
          <div class="receive-opt-sub">Continuous scan + ръчно</div>
        </div>
        <span class="receive-opt-chevron">→</span>
      </button>

      <!-- Option 4: Import -->
      <button class="receive-opt q-amber" onclick="startImport()">
        <span class="receive-opt-orb">
          <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
        </span>
        <div class="receive-opt-text">
          <div class="receive-opt-label">Импорт файл</div>
          <div class="receive-opt-sub">Excel, CSV или email</div>
        </div>
        <span class="receive-opt-chevron">→</span>
      </button>
    </div>

    <button class="gs-cancel" onclick="closeSheet()">Отказ</button>
  </div>
</div>
```

### 13.3 Sheet CSS

```css
.gs-ov{
  position:fixed; inset:0; z-index:200;
  background:rgba(0,0,0,0.55);
  backdrop-filter:blur(4px);
  display:flex; align-items:flex-end;
  opacity:0; pointer-events:none;
  transition:opacity 0.32s ease;
}
.gs-ov.show{ opacity:1; pointer-events:auto; }

.gs-sheet{
  width:100%; max-width:480px; margin:0 auto;
  border-radius:var(--radius) var(--radius) 0 0;
  padding:6px 16px calc(20px + env(safe-area-inset-bottom,0));
  background:var(--surface);
  box-shadow:var(--shadow-card);
  transform:translateY(100%);
  transition:transform 0.32s cubic-bezier(0.34,1.56,0.64,1);
}
[data-theme="dark"] .gs-sheet{
  background:linear-gradient(235deg, hsl(220 25% 8%), hsl(220 25% 4.8%));
  backdrop-filter:blur(20px);
  border:1px solid hsl(var(--hue2) 12% 18%);
  border-bottom:none;
}
.gs-ov.show .gs-sheet{ transform:translateY(0); }

.gs-handle{
  width:36px; height:4px;
  border-radius:var(--radius-pill);
  background:var(--text-faint);
  margin:0 auto 14px;
  opacity:0.4;
}
.gs-title{
  font-size:18px; font-weight:800;
  letter-spacing:-0.02em;
  margin-bottom:2px;
}
.gs-sub{
  font-family:var(--font-mono);
  font-size:9px; font-weight:700;
  color:var(--text-muted);
  letter-spacing:0.08em;
  text-transform:uppercase;
  margin-bottom:14px;
}

.receive-options{ display:flex; flex-direction:column; gap:10px; }

.receive-opt{
  display:flex; align-items:center; gap:14px;
  padding:14px;
  border-radius:var(--radius);
  background:var(--surface);
  box-shadow:var(--shadow-card);
  text-align:left;
}
[data-theme="dark"] .receive-opt{
  background:hsl(220 25% 6% / 0.7);
  backdrop-filter:blur(8px);
  border:1px solid hsl(var(--hue2) 12% 18%);
}
.receive-opt:active{ transform:scale(0.99); transition:transform 100ms ease; }

.receive-opt-orb{
  width:42px; height:42px;
  border-radius:var(--radius-sm);
  display:grid; place-items:center;
  flex-shrink:0;
}
.receive-opt.q-magic .receive-opt-orb{
  background:linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow:0 4px 12px hsl(280 70% 50% / 0.5);
}
.receive-opt.q-default .receive-opt-orb{
  background:linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow:0 4px 12px hsl(var(--hue1) 80% 50% / 0.4);
}
.receive-opt.q-amber .receive-opt-orb{
  background:linear-gradient(135deg, hsl(38 80% 55%), hsl(28 75% 50%));
  box-shadow:0 4px 12px hsl(38 80% 50% / 0.4);
}

.receive-opt-text{ flex:1; min-width:0; }
.receive-opt-label{ font-size:14px; font-weight:800; }
.receive-opt-sub{
  font-family:var(--font-mono);
  font-size:10px; font-weight:700;
  color:var(--text-muted);
  letter-spacing:0.04em;
  margin-top:2px;
}
.receive-opt-chevron{
  font-size:20px; opacity:0.5;
  font-weight:800;
}

.gs-cancel{
  width:100%; height:46px;
  border-radius:var(--radius);
  background:var(--bg-main);
  box-shadow:var(--shadow-pressed);
  font-size:13px; font-weight:800;
  color:var(--text-muted);
  margin-top:14px;
}
```

### 13.4 Защо 4 опции (логиката)

| Опция | Кога се ползва | Скорост | Точност |
|---|---|---|---|
| **OCR (📷 Снимай фактура)** | Има хартиена/PDF фактура | 4-7 сек / артикул | 75-95% (зависи от качество) |
| **Voice (🎤 Кажи какво има)** | Малка доставка, без фактура | ~12 сек / артикул | 85-92% |
| **Manual / Scan (📷 Сканирай)** | Има баркодове, малка доставка, проверка | ~6 сек / артикул | 99% |
| **Import (📥 Файл)** | Голяма доставка с Excel/CSV от доставчик | 30 сек / 100 артикула | 95% |

---

## 14. OCR FLOW (Снимай фактура)

### 14.1 6-стъпков flow (от INVENTORY_HIDDEN_v3 §6)

```
1. [📷 Снимай фактура] tap
2. Camera overlay → Пешо снима фактурата
3. Background OCR (Gemini 2.5 Flash + custom prompt)
4. Confidence routing:
   - >92% → AUTO_ACCEPT (показва summary, [Готово])
   - 75-92% → SMART_UI с item-level review
   - <75% → REJECT, fallback към voice
5. Multi-store split (ако ENI)
6. Finalize → INSERT deliveries + items + UPDATE inventory + close lost_demand
```

### 14.2 Camera overlay

```html
<div class="ocr-camera-overlay">
  <video id="ocrVideo" autoplay playsinline></video>
  <div class="ocr-hint">
    Снимай цялата фактура в кадър<br>
    Дръж телефона стабилно
  </div>
  <div class="ocr-frame"></div>  <!-- corner brackets -->
  <button class="ocr-shutter" onclick="captureOCR()">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <circle cx="12" cy="12" r="10"/>
    </svg>
  </button>
  <button class="ocr-cancel" onclick="closeCamera()">Отказ</button>
</div>
```

### 14.3 Processing state

```html
<div class="ocr-processing">
  <div class="ocr-spinner">
    <!-- conic spin -->
  </div>
  <div class="ocr-step">Чета фактурата...</div>
  <div class="ocr-progress">
    <div class="ocr-progress-bar" style="width: 60%"></div>
  </div>
</div>
```

```css
.ocr-spinner{
  width:48px; height:48px;
  border-radius:50%;
  margin:0 auto 16px;
  background:conic-gradient(from 0deg,
    transparent 0deg,
    var(--accent) 270deg,
    transparent 360deg);
  animation:conicSpin 1s linear infinite;
  position:relative;
}
.ocr-spinner::after{
  content:'';
  position:absolute; inset:4px;
  border-radius:50%;
  background:var(--surface);
}
```

### 14.4 Confidence Routing UI

**Confidence >92% (AUTO_ACCEPT):**

```
┌──────────────────────────────────────┐
│ ✓ Доставка готова                    │
│                                      │
│ Емпорио ООД                          │
│ 15 артикула, €840                    │
│ Точност: 96%                         │
│                                      │
│ Артикули:                            │
│ • Тениска синя L × 5  €120          │
│ • Дънки женски M × 3  €240          │
│ • Шапка × 7           €70           │
│ ... +12 още                          │
│                                      │
│ ╔══════════════════════════════════╗ │
│ ║         ✓ Готово · Получи       ║ │
│ ╚══════════════════════════════════╝ │
│                                      │
│ [Промени нещо] [Снимай отново]       │
└──────────────────────────────────────┘
```

**Confidence 75-92% (SMART UI):**

Item-level review с зелен/оранжев/червен бадж за всеки ред:

```
┌──────────────────────────────────────┐
│ ⚠ Прегледай артикулите               │
│                                      │
│ Емпорио ООД, 15 артикула €840        │
│ Точност: 84%                         │
│                                      │
│ ✓ Тениска синя L × 5  €120  [→]    │
│ ✓ Дънки женски M × 3  €240  [→]    │
│ ⚠ ??? × 7             €70   [✏]    │ ← оранжев, нужно ръчно
│ ✓ Шапка × 4           €40   [→]    │
│ ⚠ Якета L × 2         €280  [✏]    │
│ ...                                  │
│                                      │
│ ╔══════════════════════════════════╗ │
│ ║         ✓ Потвърди               ║ │
│ ╚══════════════════════════════════╝ │
└──────────────────────────────────────┘
```

Tap на оранжев item → отваря mini-wizard за този ред.

**Confidence <75% (REJECT):**

```
┌──────────────────────────────────────┐
│ ✗ Не разпознах фактурата             │
│                                      │
│ Точност твърде ниска (62%)           │
│ Може да опиташ:                      │
│                                      │
│ [📷 Снимай отново]                   │
│ [🎤 Кажи какво има]                  │
│ [📷 Сканирай един по един]           │
│                                      │
└──────────────────────────────────────┘
```

### 14.5 OCR извлича автоматично

| Поле | OCR confidence | Backup |
|---|---|---|
| Доставчик (име) | 90%+ обикновено | AI fuzzy match → suppliers; или "Нов доставчик?" |
| Дата | 95%+ | default = today |
| Общо количество | 85%+ | sum(items) verify |
| Обща стойност | 90%+ | sum(items × cost) verify |
| Per-item code | 70-95% | mini-wizard |
| Per-item name | 75-90% | mini-wizard |
| Per-item qty | 85-95% | numeric парсер |
| Per-item cost | 85-95% | numeric парсер |

### 14.6 Multi-store split (ENI 5 магазина)

След OCR, ако tenant има >1 магазин:

```
┌──────────────────────────────────────┐
│ Раздели по магазини                  │
│                                      │
│ Емпорио ООД, 15 артикула             │
│                                      │
│ Магазин Левски:        ◯  ●         │
│ ├─ Тениска синя L × 3                │
│ └─ Якета L × 1                       │
│                                      │
│ Магазин Лукс:          ◯  ●         │
│ ├─ Дънки женски × 3                  │
│ └─ Шапки × 4                         │
│                                      │
│ Магазин Сан Стефано:   ◯  ●         │
│ └─ Тениска синя L × 2                │
│                                      │
│ [🔮 AI препоръча по история]         │
│ [✓ Потвърди разпределение]           │
└──────────────────────────────────────┘
```

AI препоръка = базирана на историята: кой магазин е продал най-много от тоя артикул в последните 30 дни.

### 14.7 Audit trail (scanner_documents)

```sql
INSERT INTO scanner_documents (
  tenant_id, user_id, doc_type, file_path,
  ocr_text, ocr_confidence, ocr_engine,
  delivery_id, status, created_at
) VALUES (
  X, Y, 'invoice_ocr', '/uploads/scans/2026/05/abc.jpg',
  '<full OCR text>', 0.84, 'gemini-2.5-flash',
  NULL, 'processed', NOW()
);
-- delivery_id остава NULL докато не се финализира
-- При финализация: UPDATE scanner_documents SET delivery_id=Z WHERE id=...
```

---

## 15. VOICE FLOW (Кажи какво има)

### 15.1 Voice overlay (S56 стандарт от P13)

```html
<div class="voice-overlay" id="voiceOverlay">
  <div class="voice-overlay-bg"></div>
  <div class="voice-overlay-content">
    <div class="voice-mic-pulse">
      <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
        <rect x="9" y="2" width="6" height="12" rx="3"/>
        <path d="M5 10v2a7 7 0 0014 0v-2"/>
      </svg>
    </div>
    <div class="voice-state">Слушам...</div>
    <div class="voice-transcript">""</div>
    <button class="voice-stop" onclick="stopVoice()">Спри</button>
  </div>
</div>
```

```css
.voice-overlay{
  position:fixed; inset:0; z-index:300;
  display:none;
  background:rgba(0,0,0,0.7);
  backdrop-filter:blur(8px);
}
.voice-overlay.show{ display:flex; flex-direction:column; align-items:center; justify-content:center; }

.voice-mic-pulse{
  width:96px; height:96px;
  border-radius:50%;
  background:linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow:0 8px 32px hsl(280 70% 50% / 0.6);
  display:grid; place-items:center;
  position:relative;
  animation:micPulse 1.4s ease-in-out infinite;
}
.voice-mic-pulse::before{
  content:'';
  position:absolute; inset:-8px;
  border-radius:50%;
  border:2px solid hsl(280 70% 55%);
  opacity:0;
  animation:micRing 1.4s ease-in-out infinite;
}
.voice-mic-pulse svg{ width:36px; height:36px; }

@keyframes micPulse{
  0%, 100% { transform:scale(1); }
  50% { transform:scale(1.06); }
}
@keyframes micRing{
  0% { transform:scale(1); opacity:0.6; }
  100% { transform:scale(1.4); opacity:0; }
}

.voice-state{
  font-size:14px; font-weight:800;
  color:white;
  margin:24px 0 8px;
}
.voice-transcript{
  font-size:18px; font-weight:600;
  color:white;
  text-align:center;
  max-width:80%;
  min-height:64px;
}
.voice-stop{
  margin-top:24px;
  padding:14px 32px;
  border-radius:var(--radius-pill);
  background:rgba(255,255,255,0.15);
  border:1px solid rgba(255,255,255,0.3);
  color:white;
  font-size:13px; font-weight:800;
}
```

### 15.2 Voice flow (типичен пример)

```
1. Пешо: tap [🎤 Кажи какво има]
2. Voice overlay отваря
3. Пешо: "получих от Емпорио 5 тениски синя L по 24 лева"
4. STT (Whisper Groq) → text
5. AI parser:
   {
     "supplier": "Емпорио",
     "items": [
       { "name": "тениска синя L", "qty": 5, "cost": 24 }
     ]
   }
6. Confirmation overlay:
   "✓ Емпорио, 5 тениски синя L по €24 (€120 общо). Правилно?"
7. Пешо: [Да, добави още] / [Готово · Получи] / [Поправи]
8. Continuous mode: повтарят се 3-7 за следващия артикул
9. Накрая: "Готово" → finalize
```

### 15.3 STT engine choice (LOCKED commits 4222a66 + 1b80106)

| Тип данни | Engine | Защо |
|---|---|---|
| Числа (количества, цени) на български | Web Speech API | Instant, не консумира API quota |
| Текст (имена, бележки) | Web Speech API | Native browser |
| Числа на чужд език (рядко) | Whisper Groq | По-точен с акценти |
| Дълги фрази със сложна семантика | Whisper Groq | По-добро разбиране |

**`_wizPriceParse` parser в products.php** е canonical — има Cyrillic-aware boundaries + phonetic synonyms. Никога не пипай.

### 15.4 Voice fallback chain

```
Web Speech API →  не работи  →  Whisper Groq  →  не работи  →  fallback към manual entry
       ↓
   AI парсер (Gemini)  →  ниска увереност в parsing  →  показва transcript + ръчно редактиране
```

### 15.5 Voice команди в continuous scan mode

В Manual flow (option 3), след сканиране на баркод, voice бутон е активен за "5 броя", "цена 24", "следващ", "готово".

### 15.6 Voice бутон на всяко поле (Закон №1)

Според Bible — voice бутон е задължителен на всеки text/number input. В deliveries.php конкретно:

| Поле | Voice бутон? | Какво приема |
|---|---|---|
| Доставчик | Да | Име на доставчик |
| Дата | Да | "вчера", "10 май", "днес" |
| Артикул код | Да | Цифри + букви |
| Артикул име | Да | Име |
| Количество | Да | Число |
| Цена доставка | Да | Число + "лева"/"евро" |
| Цена продажба | Да | Число |
| Бележки | Да | Свободен текст |

---

## КРАЙ НА ЧАСТ 2

**Какво следва в Част 3:**
- Manual flow с continuous scan (Lightspeed/Microinvest pattern)
- Импорт flow (Excel/CSV/email)
- Mini-wizard за нов артикул в доставка (P13 минимум секция)
- Smart Pricing Rules (ПЕЧАЛБА %)
- Какво НЕ вижда Пешо в лесен режим (детайлно)

---

# ═══════════════════════════════════════
# ЧАСТ 2 — ЛЕСЕН РЕЖИМ (Пешо)
# ═══════════════════════════════════════

## 8. ГЛАВЕН ЕКРАН — LAYOUT

### 8.1 Структура (Inbox style, не Dashboard)

Лесен режим е **inbox** — задачи и сигнали в ред "ето какво има за днес". НЕ е dashboard с числа и графики.

```
┌─────────────────────────────────────┐
│ Header (.bm-header) 56px            │ ← sticky top
├─────────────────────────────────────┤
│ Aurora background (3 blobs)         │ ← fixed, z:0
├─────────────────────────────────────┤
│ .shell (max-width:480px, padding-bottom 86px) │
│                                              │
│  ┌─ Voice command bar (.voice-bar)  ┐       │
│  │   "Кажи на AI"                   │       │
│  │   "пример: получих доставка..."  │       │
│  └──────────────────────────────────┘       │
│                                              │
│  ┌─ Mode toggle (.mode-toggle)      ┐       │
│  │   [Лесен●] [Разширен]            │       │
│  └──────────────────────────────────┘       │
│                                              │
│  ┌─ Section title ──────────────────┐       │
│  │   [icon] Задачи за днес          │       │
│  └──────────────────────────────────┘       │
│                                              │
│  ┌─ AI signal card #1 (.lb-card.q5)─┐       │
│  │ [orb] CHAKAЩА                    │       │
│  │       Очаквана днес от Емпорио ▼ │       │
│  └──────────────────────────────────┘       │
│  ┌─ AI signal card #2 (.lb-card.q3)─┐       │
│  │ [orb] PEZULTAT                   │       │
│  │       Lost demand €420 затворен ▼│       │
│  └──────────────────────────────────┘       │
│  ┌─ AI signal card #3 (.lb-card.q1)─┐       │
│  │ [orb] ZAKAESHALA                 │       │
│  │       Закъсняла от Цвеп - 4 дни ▼│       │
│  └──────────────────────────────────┘       │
│                                              │
│  ┌─ Section title ──────────────────┐       │
│  │   [icon] Действия                │       │
│  └──────────────────────────────────┘       │
│                                              │
│  ┌─ Big op button (.op-btn.qd)─────┐        │
│  │      [icon truck 44x44]          │        │
│  │   ПОЛУЧИ ДОСТАВКА (q-default)   │        │
│  └──────────────────────────────────┘        │
│                                              │
│  ┌─ Small button (.studio-btn)─────┐        │
│  │  [icon plus] Нова поръчка        │        │
│  └──────────────────────────────────┘        │
│                                              │
├─────────────────────────────────────┤
│ Bottom nav (4 tabs) — заключен      │ ← fixed bottom
└─────────────────────────────────────┘
```

### 8.2 Pixel-точни размери

| Елемент | Размер |
|---|---|
| Header height | 56px + `env(safe-area-inset-top, 0)` |
| Bottom nav height | 86px (включва safe-area) |
| `.shell` max-width | 480px (mobile-first 375px target) |
| `.shell` padding | 12px странично + 86px отдолу |
| Voice bar height | ~54px (10px+38px+10px = 58px с padding-y 10px) |
| Mode toggle height | 50px (4px padding + 42px button) |
| AI signal card collapsed | ~52px (12px padding + 28px orb) |
| Op button | ~96px (16px padding + 44px icon + 8px gap + 14px label + 16px padding) |
| Section title | ~32px (font 11px uppercase + 8px margin) |

### 8.3 Slot order (приоритет отгоре надолу)

```
1. Header                          [винаги]
2. Voice bar                       [винаги]
3. Mode toggle                     [винаги]
4. Section title "Задачи за днес"  [ако ≥1 AI signal]
5. AI signals (max 3)              [conditional]
6. Section title "Действия"        [винаги]
7. Op button "Получи доставка"     [винаги]
8. Studio button "Нова поръчка"    [винаги]
9. (post-beta) "Скорошни доставки" [conditional, max 3]
```

Между секциите: 16px vertical gap.

### 8.4 Какво вижда Пешо

✅ Voice bar
✅ Mode toggle
✅ AI signals (макс 3, q-magic / q-loss / q-gain / q-amber)
✅ Голям бутон "Получи доставка"
✅ Малък бутон "Нова поръчка"

### 8.5 Какво НЕ вижда Пешо

❌ Status tabs (Чакат / Готови / История)
❌ KPI strip (числа за стойност, чакащи, закъснели)
❌ Sort dropdown
❌ Quick chips (saved filters)
❌ Filter accordion
❌ Detail header с meta info
❌ History на доставки (списък)
❌ Меню (☰)
❌ Bulk select mode
❌ Audit log
❌ `confidence_score` (Hidden Inventory §6 — никога visible)
❌ Marketing AI signals
❌ Promotion triggers (post-beta)

---

## 9. AI СИГНАЛИ В ЛЕСЕН РЕЖИМ

### 9.1 Колко

**Макс 3** карти едновременно. Никога повече.

Защо 3:
- Cognitive load на mobile screen (375px)
- Над 3 = wall-of-text
- Concept "очевидни задачи за днес"

Ако има >3 потенциални signals → priority engine избира top 3 (виж 9.4).

### 9.2 Видове сигнали в лесен (от 40 deliv_* в S51_AI_TOPICS_MASTER)

| Topic | Trigger | Hue клас |
|---|---|---|
| `deliv_expected_today` | `expected_at = TODAY` | q-amber |
| `deliv_overdue` | `status='overdue' OR (sent_at + lead_time + 3d < now())` | q-loss |
| `deliv_partial_received` | `status='partial'` (последните 3 дни) | q-amber |
| `deliv_ai_draft_ready` | `is_ai_draft=1 AND created_at = today` | q-magic |
| `deliv_lost_demand_resolved` | matcher cycle close | q-gain |
| `deliv_cost_increase` | `cost_change_pct >= 10%` (последна доставка) | q-loss |
| `deliv_category_count_due` | category никога не е броена + ≥3 нови deliveries | q-magic |

### 9.3 Какво НЕ се показва в лесен (само в разширен)

| Topic | Защо не |
|---|---|
| `deliv_supplier_late_avg` | Stat, не задача |
| `deliv_margin_warning` | Изисква разсъждение |
| `deliv_seasonal_drift` | Strategic |
| `deliv_supplier_reliability_drop` | Stat |
| `deliv_payment_due` | Финансово, само owner |
| `deliv_compare_to_last_year` | Stat |
| `deliv_inventory_imbalance_per_store` | Multi-store stat |

### 9.4 Selection Engine (rule-based, НЕ AI)

```php
function selectEasyModeSignals($tenant_id, $user_id) {
    $candidates = DB::get("
        SELECT * FROM insights
        WHERE tenant_id = ?
          AND module = 'deliveries'
          AND role_gate IN ('seller', 'all')
          AND shown_at IS NULL OR shown_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY priority DESC, created_at DESC
    ", [$tenant_id]);

    // Priority order:
    $priority = [
        'deliv_overdue' => 100,             // Закъснели — спешно
        'deliv_expected_today' => 90,       // Днес действие
        'deliv_lost_demand_resolved' => 80, // Победа, мотивация
        'deliv_ai_draft_ready' => 70,       // Действие
        'deliv_partial_received' => 60,     // Информативно
        'deliv_category_count_due' => 50,   // Опционално
        'deliv_cost_increase' => 40,        // Информативно
    ];

    return array_slice($candidates, 0, 3);
}
```

### 9.5 Plan + Role gate

| Plan | Какво вижда Пешо |
|---|---|
| FREE | 0 AI signals (template only) |
| START | 2 AI signals макс |
| PRO | 3 AI signals макс |
| BUSINESS | 3 AI signals макс + Marketing AI (post-beta) |

Role gate:
- `seller` → вижда `role_gate IN ('seller', 'all')`
- `manager`, `owner` → вижда `'all'` + може да вижда signals като `payment_due`

### 9.6 Cooldown

| Topic | Cooldown |
|---|---|
| Всички | 24 часа per topic per tenant |
| `deliv_lost_demand_resolved` | 12 часа (победите се повтарят) |
| `deliv_overdue` | 6 часа (urgent) |

DB: `insights.shown_at` обновява се при render.

### 9.7 Confidence routing (06_anti_hallucination)

| Signal source | Confidence | Действие |
|---|---|---|
| Pure SQL (deliveries.expected_at = today) | 100% | Show |
| AI cost analysis (cost_change_pct + market avg) | ≥ 0.85 | Show без confirm |
| AI suggestion (нова доставка от forecast) | 0.5–0.85 | Show с "ИЗБЕРИ" CTA |
| AI uncertain | < 0.5 | НЕ се показва |

### 9.8 Phased rollout (от 06_anti_hallucination)

| Фаза | Период | AI % | Template % |
|---|---|---|---|
| **Фаза 1 (бета)** | 14.05.2026 – Q3 2026 | 0% | 100% |
| **Фаза 2** | Q4 2026 | 30% | 70% |
| **Фаза 3** | Q1 2027 | 80% | 20% |

В Фаза 1 всички signals идват от **prepared template strings** (не Gemini API). Защита от hallucination в бета.

---

## 10. AI SIGNAL КАРТА — КОМПОНЕНТ 1:1

Базиран на `.lb-card` от P10. Hue класове (q1=q-loss, q2=q-magic, q3=q-gain, q5=q-amber).

### 10.1 Collapsed state HTML

```html
<article class="lb-card q5"
         data-topic="deliv_expected_today"
         data-priority="90"
         onclick="this.classList.toggle('expanded')">

  <div class="lb-collapsed">
    <span class="lb-emoji-orb">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round">
        <rect x="1" y="3" width="15" height="13" rx="1"/>
        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
        <circle cx="5.5" cy="18.5" r="2.5"/>
        <circle cx="18.5" cy="18.5" r="2.5"/>
      </svg>
    </span>
    <div class="lb-collapsed-content">
      <span class="lb-fq-tag-mini">CHAKAЩА</span>
      <span class="lb-collapsed-title">Очаквана днес от Емпорио ООД</span>
    </div>
    <button class="lb-expand-btn" aria-label="Разгъни">
      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2.5">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>
  </div>

  <div class="lb-expanded">
    <div class="lb-body">
      <p>Поръчка #SO-2026-0234 (отпрда 8 дни). Очаквано: <strong>23 артикула, 1,240 € стойност</strong>.</p>
      <p class="lb-why">Защото: вторник е стандартен ден за Емпорио (10/12 досега).</p>
    </div>
    <div class="lb-actions">
      <button class="lb-action-primary" onclick="navigate('deliveries.php?action=receive&order_id=234')">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Получи я
      </button>
      <button class="lb-action-secondary" onclick="dismissSignal(this)">По-късно</button>
    </div>
  </div>
</article>
```

### 10.2 Hue mapping за Доставки signals

| Topic | clase | orb color light | orb color dark |
|---|---|---|---|
| `deliv_overdue` | `q1` (loss) | `hsl(0 50% 92%)` | `hsl(0 50% 12%)` |
| `deliv_expected_today` | `q5` (amber) | `hsl(38 50% 92%)` | `hsl(38 50% 12%)` |
| `deliv_partial_received` | `q5` (amber) | `hsl(38 50% 92%)` | `hsl(38 50% 12%)` |
| `deliv_ai_draft_ready` | `q2` (magic) | `hsl(280 50% 92%)` | `hsl(280 50% 12%)` |
| `deliv_lost_demand_resolved` | `q3` (gain) | `hsl(145 50% 92%)` | `hsl(145 50% 12%)` |
| `deliv_cost_increase` | `q1` (loss) | `hsl(0 50% 92%)` | `hsl(0 50% 12%)` |
| `deliv_category_count_due` | `q2` (magic) | `hsl(280 50% 92%)` | `hsl(280 50% 12%)` |

### 10.3 Tag-mini text

| Topic | `lb-fq-tag-mini` text |
|---|---|
| `deliv_overdue` | "ZAKAESHALA" |
| `deliv_expected_today` | "CHAKAЩА" |
| `deliv_partial_received` | "CHASTICHNA" |
| `deliv_ai_draft_ready` | "AI CHERNOVA" |
| `deliv_lost_demand_resolved` | "REZULTAT" |
| `deliv_cost_increase` | "TSENA NAGORE" |
| `deliv_category_count_due` | "BROENE NUZHNO" |

DM Mono font, 8.5px, font-weight 800, letter-spacing 0.08em, uppercase, color `var(--text-muted)`.

### 10.4 Title text формула

> **"Number/Subject + Why + Soft suggestion"** (Bible §2 закон №2)

| Topic | Title формат |
|---|---|
| `deliv_expected_today` | "Очаквана днес от {supplier_name}" |
| `deliv_overdue` | "Закъсняла {supplier_name} - {days_late} дни" |
| `deliv_partial_received` | "Частично получена - {missing_count} артикула липсват" |
| `deliv_ai_draft_ready` | "Готова чернова за {supplier_count} доставчика" |
| `deliv_lost_demand_resolved` | "Lost demand {currency_amount} затворен" |
| `deliv_cost_increase` | "{product_name} +{pct}% от {old_cost} → {new_cost}" |
| `deliv_category_count_due` | "Хайде да броим {category}" |

### 10.5 Expanded body

Split в 3 части:
1. **Number block** — конкретни числа в bold (PHP-калкулирани)
2. **Why block** — историческо обяснение с `<span class="lb-why">`
3. **Action row** — primary CTA + dismiss

CSS за `.lb-why`:
```css
.lb-why {
  font-style: italic;
  color: var(--text-faint);
  font-size: 11.5px;
  margin-top: 6px;
  padding-left: 10px;
  border-left: 2px solid var(--border-color);
}
```

### 10.6 Expanded animation

От P10 `.lb-card.expanded::before` (conic border):

```css
.lb-card.expanded::before {
  content: '';
  position: absolute; inset: -1px;
  border-radius: var(--radius-sm);
  padding: 2px;
  background: conic-gradient(from 0deg, var(--accent), transparent 60%, var(--accent));
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor; mask-composite: exclude;
  animation: conicSpin 4s linear infinite;
  opacity: 0.55;
}
.lb-card.q1.expanded::before { background: conic-gradient(from 0deg, hsl(0 70% 55%), transparent 60%, hsl(0 70% 55%)); }
.lb-card.q2.expanded::before { background: conic-gradient(from 0deg, hsl(280 70% 55%), transparent 60%, hsl(280 70% 55%)); }
.lb-card.q3.expanded::before { background: conic-gradient(from 0deg, hsl(145 60% 50%), transparent 60%, hsl(145 60% 50%)); }
.lb-card.q5.expanded::before { background: conic-gradient(from 0deg, hsl(38 80% 55%), transparent 60%, hsl(38 80% 55%)); }
```

### 10.7 Action buttons CSS

```css
.lb-action-primary {
  flex: 1;
  height: 42px;
  border-radius: var(--radius-pill);
  font-size: 13px; font-weight: 800; letter-spacing: -0.01em;
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  color: white;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 14px hsl(var(--hue1) 80% 50% / 0.4);
}
.lb-action-primary svg { width: 14px; height: 14px; stroke: white; fill: none; stroke-width: 2.5; }

.lb-action-secondary {
  height: 42px; padding: 0 14px;
  border-radius: var(--radius-pill);
  font-size: 12px; font-weight: 700;
  color: var(--text-muted);
}
[data-theme="light"] .lb-action-secondary { background: var(--surface); box-shadow: var(--shadow-card-sm); }
[data-theme="dark"] .lb-action-secondary { background: hsl(220 25% 8%); border: 1px solid hsl(var(--hue2) 12% 18%); }
```

---

## 11. БУТОН "ПОЛУЧИ ДОСТАВКА" (главен CTA)

Базиран на `.op-btn` от P10. Class `qd` (q-default — primary).

### 11.1 HTML

```html
<button class="op-btn qd" onclick="openReceiveSheet()">
  <span class="op-icon">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round">
      <rect x="1" y="3" width="15" height="13" rx="1"/>
      <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
      <circle cx="5.5" cy="18.5" r="2.5"/>
      <circle cx="18.5" cy="18.5" r="2.5"/>
    </svg>
  </span>
  <span class="op-label">Получи доставка</span>
</button>
```

### 11.2 CSS (от P10)

```css
.op-btn {
  position: relative;
  padding: 16px 12px;
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  text-align: center;
  border-radius: var(--radius);
  transition: box-shadow var(--dur) var(--ease), transform var(--dur-fast) var(--ease);
}
[data-theme="light"] .op-btn { background: var(--surface); box-shadow: var(--shadow-card); }
[data-theme="dark"] .op-btn { background: hsl(220 25% 6% / 0.7); backdrop-filter: blur(8px); border: 1px solid hsl(var(--hue2) 12% 18%); }
.op-btn:active { transform: scale(0.98); }

.op-icon {
  width: 44px; height: 44px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
}
[data-theme="light"] .op-icon { background: var(--surface); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .op-icon { background: hsl(220 25% 4%); }
.op-icon svg { width: 22px; height: 22px; fill: none; stroke-width: 2; }
.op-btn.qd .op-icon svg { stroke: var(--accent); }
[data-theme="dark"] .op-btn.qd .op-icon svg { stroke: hsl(var(--hue1) 80% 70%); }

.op-label { font-size: 14px; font-weight: 800; letter-spacing: -0.005em; }
```

### 11.3 Sacred Neon Glass (dark mode)

```css
[data-theme="dark"] .op-btn { position: relative; }
[data-theme="dark"] .op-btn::before,
[data-theme="dark"] .op-btn::after {
  content: ''; position: absolute; pointer-events: none;
  border-radius: inherit; mix-blend-mode: plus-lighter;
}
[data-theme="dark"] .op-btn::before {
  inset: 0; padding: 1px;
  background: conic-gradient(from 220deg at 50% 0%,
    oklch(0.7 0.18 var(--hue1) / 0.8) 0deg,
    oklch(0.65 0.2 280 / 0.6) 90deg,
    oklch(0.7 0.18 var(--hue2) / 0.4) 180deg,
    oklch(0.6 0.16 var(--hue1) / 0.3) 270deg,
    oklch(0.7 0.18 var(--hue1) / 0.8) 360deg);
  -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
  -webkit-mask-composite: xor; mask-composite: exclude;
  opacity: 0.7;
}
[data-theme="dark"] .op-btn::after {
  inset: -1px;
  background: radial-gradient(80% 60% at 50% 0%, oklch(0.7 0.2 var(--hue1) / 0.18), transparent 70%);
  opacity: 0.9; filter: blur(12px);
}
```

---

## 12. БУТОН "НОВА ПОРЪЧКА" (secondary)

Базиран на `.studio-btn` от P10.

### 12.1 HTML

```html
<button class="studio-btn" onclick="navigate('orders.php?action=create')">
  <span class="studio-icon">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <line x1="12" y1="5" x2="12" y2="19"/>
      <line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
  </span>
  <div class="studio-text">
    <span class="studio-label">Нова поръчка</span>
    <span class="studio-sub">КЪМ ДОСТАВЧИК</span>
  </div>
  <svg class="studio-chev" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
    <polyline points="9 18 15 12 9 6"/>
  </svg>
</button>
```

### 12.2 CSS (от P10)

```css
.studio-btn {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px;
  border-radius: var(--radius);
  position: relative;
  transition: box-shadow var(--dur) var(--ease), transform var(--dur-fast) var(--ease);
  width: 100%;
}
[data-theme="light"] .studio-btn { background: var(--surface); box-shadow: var(--shadow-card); }
[data-theme="dark"] .studio-btn { background: hsl(220 25% 6% / 0.7); backdrop-filter: blur(8px); border: 1px solid hsl(var(--hue2) 12% 18%); }
.studio-btn:active { transform: scale(0.99); }

.studio-icon {
  width: 36px; height: 36px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow: 0 4px 12px hsl(280 70% 50% / 0.4);
  position: relative; overflow: hidden;
}
.studio-icon::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.4) 85%, transparent 100%);
  animation: conicSpin 4s linear infinite;
}
.studio-icon svg { width: 18px; height: 18px; stroke: white; fill: none; stroke-width: 2; position: relative; z-index: 1; }

.studio-text { flex: 1; min-width: 0; }
.studio-label { display: block; font-size: 14px; font-weight: 800; letter-spacing: -0.01em; }
.studio-sub { display: block; font-family: var(--font-mono); font-size: 9.5px; font-weight: 700; color: var(--text-muted); letter-spacing: 0.06em; text-transform: uppercase; margin-top: 2px; }

.studio-chev { width: 16px; height: 16px; stroke: var(--text-muted); flex-shrink: 0; }
```

---

## 13. EMPTY STATE

### 13.1 Кога се показва

- Tenant е нов (нула доставки)
- Всички AI signals dismiss-нати
- Няма очаквани доставки + няма закъсняли + няма partial

### 13.2 Layout

```
[Voice bar]
[Mode toggle]

[Section title "Започни тук"]

  ┌─ Empty illustration ───┐
  │                        │
  │   [SVG truck icon 64px] │
  │                        │
  │   Няма активни         │
  │   доставки в момента   │
  │                        │
  │   [Получи доставка]    │
  └────────────────────────┘
```

### 13.3 HTML

```html
<div class="empty-state-card">
  <div class="empty-state-icon">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="1.5">
      <rect x="1" y="3" width="15" height="13" rx="1"/>
      <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
      <circle cx="5.5" cy="18.5" r="2.5"/>
      <circle cx="18.5" cy="18.5" r="2.5"/>
    </svg>
  </div>
  <h3 class="empty-state-title">Няма активни доставки</h3>
  <p class="empty-state-sub">Получи първата си доставка, за да започнем</p>
  <button class="op-btn qd empty-state-cta" onclick="openReceiveSheet()">
    <span class="op-label">Получи доставка</span>
  </button>
</div>
```

### 13.4 CSS

```css
.empty-state-card {
  padding: 32px 16px;
  text-align: center;
  border-radius: var(--radius);
}
[data-theme="light"] .empty-state-card { background: var(--surface); box-shadow: var(--shadow-card); }
[data-theme="dark"] .empty-state-card { background: hsl(220 25% 6% / 0.7); border: 1px solid hsl(var(--hue2) 12% 18%); }

.empty-state-icon {
  width: 80px; height: 80px;
  border-radius: var(--radius-icon);
  margin: 0 auto 16px;
  display: grid; place-items: center;
}
[data-theme="light"] .empty-state-icon { background: var(--bg-main); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .empty-state-icon { background: hsl(220 25% 4%); }
.empty-state-icon svg { width: 40px; height: 40px; stroke: var(--text-muted); }

.empty-state-title { font-size: 16px; font-weight: 800; letter-spacing: -0.01em; margin-bottom: 6px; }
.empty-state-sub { font-size: 12px; color: var(--text-muted); margin-bottom: 20px; }
.empty-state-cta { width: 100%; max-width: 280px; margin: 0 auto; }
```

---

## 14. LOADING STATE

### 14.1 Skeleton за AI карти

```html
<div class="lb-card-skeleton">
  <div class="skel-orb"></div>
  <div class="skel-text">
    <div class="skel-tag"></div>
    <div class="skel-title"></div>
  </div>
</div>
```

### 14.2 CSS (shimmer animation)

```css
@keyframes shimmerLoad {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}
.lb-card-skeleton {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; margin-bottom: 8px;
  border-radius: var(--radius);
  background: var(--surface);
  box-shadow: var(--shadow-card);
}
.skel-orb { width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0; }
.skel-text { flex: 1; }
.skel-tag { height: 9px; width: 40%; margin-bottom: 6px; border-radius: 4px; }
.skel-title { height: 14px; width: 80%; border-radius: 4px; }

.skel-orb, .skel-tag, .skel-title {
  background: linear-gradient(90deg,
    var(--bg-main) 0%, var(--surface-2) 50%, var(--bg-main) 100%);
  background-size: 200% 100%;
  animation: shimmerLoad 1.5s ease-in-out infinite;
}
```

### 14.3 Loading sequence

```
[0-100ms]   Empty container, aurora visible
[100-300ms] Skeleton (3 cards) appears с fade-in
[300-800ms] AI signals replace skeleton (fade-out skeleton, fade-in cards)
[800ms+]    Op-button + studio-btn fade-in (delayed)
```

---

## 15. ERROR STATE (offline / API fail)

### 15.1 Banner отгоре

```html
<div class="error-banner q-loss" role="alert">
  <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
    <circle cx="12" cy="12" r="10"/>
    <line x1="12" y1="8" x2="12" y2="12"/>
    <line x1="12" y1="16" x2="12.01" y2="16"/>
  </svg>
  <div class="error-text">
    <strong>Няма интернет</strong>
    <span>Работим offline. Доставките ще се синхронизират.</span>
  </div>
  <button class="error-action" onclick="retryConnection()">Опитай</button>
</div>
```

### 15.2 CSS

```css
.error-banner {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; margin-bottom: 12px;
  border-radius: var(--radius-sm);
  background: linear-gradient(135deg, hsl(0 50% 95%), hsl(0 50% 92%));
  border: 1px solid hsl(0 50% 80%);
}
[data-theme="dark"] .error-banner {
  background: linear-gradient(135deg, hsl(0 30% 12% / 0.5), hsl(0 30% 8% / 0.4));
  border: 1px solid hsl(0 50% 28% / 0.5);
}
.error-banner svg { width: 18px; height: 18px; stroke: hsl(0 70% 50%); flex-shrink: 0; }
.error-text { flex: 1; font-size: 12px; }
.error-text strong { display: block; font-weight: 800; color: hsl(0 70% 40%); }
.error-text span { color: var(--text-muted); }
.error-action {
  padding: 6px 12px; border-radius: var(--radius-pill);
  font-size: 11px; font-weight: 800; color: hsl(0 70% 40%);
}
[data-theme="light"] .error-action { background: var(--surface); box-shadow: var(--shadow-card-sm); }
[data-theme="dark"] .error-action { background: hsl(0 50% 18%); }
```

### 15.3 Offline mode UX (от Бил Ниво 1-4)

| Ниво | Условие | UI |
|---|---|---|
| 1. OCR online | WiFi/4G | Full flow |
| 2. Voice fallback | OCR API down | Voice overlay активен по default |
| 3. Scan + cache | Voice API down | Continuous scan само, кеш в IndexedDB |
| 4. Blind receive | Всичко down | Само сума + supplier name; queue for sync |

Banner текст се променя според level.

---

## 16. "ПОЛУЧИ ДОСТАВКА" SHEET — 4 ОПЦИИ

### 16.1 Trigger

Тап на op-button "Получи доставка" → отваря bottom sheet (от P13 §3.9 `.gs-ov/.gs-sheet`).

### 16.2 HTML

```html
<div class="gs-ov" id="receiveSheet">
  <div class="gs-sheet">
    <div class="gs-handle"></div>

    <div class="gs-title">Как получаваш доставката?</div>
    <div class="gs-sub">Избери начин — ще ти помогнем</div>

    <div class="receive-options">
      <button class="receive-opt q-magic" onclick="startOCR()">
        <span class="receive-opt-icon">
          <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
            <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
        </span>
        <div class="receive-opt-text">
          <strong>Снимай фактура</strong>
          <span>OCR разчита автоматично</span>
        </div>
        <svg class="receive-opt-chev" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </button>

      <button class="receive-opt q-magic" onclick="startVoice()">
        <span class="receive-opt-icon">
          <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
            <rect x="9" y="2" width="6" height="12" rx="3"/>
            <path d="M5 10v2a7 7 0 0014 0v-2"/>
          </svg>
        </span>
        <div class="receive-opt-text">
          <strong>Кажи какво има</strong>
          <span>AI пише вместо теб</span>
        </div>
        <svg class="receive-opt-chev"><!-- chevron --></svg>
      </button>

      <button class="receive-opt q-default" onclick="startScan()">
        <span class="receive-opt-icon">
          <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
            <path d="M3 7V5a2 2 0 012-2h2"/>...
          </svg>
        </span>
        <div class="receive-opt-text">
          <strong>Сканирай артикулите</strong>
          <span>Барcode + ръчно количество</span>
        </div>
        <svg class="receive-opt-chev"><!-- chevron --></svg>
      </button>

      <button class="receive-opt q-default" onclick="openImport()">
        <span class="receive-opt-icon">
          <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
        </span>
        <div class="receive-opt-text">
          <strong>Импорт файл</strong>
          <span>CSV / Excel / PDF</span>
        </div>
        <svg class="receive-opt-chev"><!-- chevron --></svg>
      </button>
    </div>

    <button class="gs-cancel" onclick="closeSheet()">Отказ</button>
  </div>
</div>
```

### 16.3 CSS

```css
.gs-ov {
  position: fixed; inset: 0; z-index: 100;
  background: rgba(0,0,0,0.55); backdrop-filter: blur(4px);
  display: flex; align-items: flex-end;
  opacity: 0; pointer-events: none;
  transition: opacity 0.25s ease;
}
.gs-ov.show { opacity: 1; pointer-events: auto; }

.gs-sheet {
  width: 100%; max-width: 480px; margin: 0 auto;
  padding: 12px 16px calc(24px + env(safe-area-inset-bottom, 0));
  border-radius: 24px 24px 0 0;
  transform: translateY(100%);
  transition: transform 0.32s cubic-bezier(0.34, 1.56, 0.64, 1);
}
[data-theme="light"] .gs-sheet { background: var(--bg-main); box-shadow: 0 -10px 40px rgba(0,0,0,0.15); }
[data-theme="dark"] .gs-sheet {
  background: linear-gradient(235deg, hsl(220 25% 6% / 0.95), hsl(220 25% 4% / 0.95));
  backdrop-filter: blur(20px);
  border-top: 1px solid hsl(var(--hue2) 12% 18%);
}
.gs-ov.show .gs-sheet { transform: translateY(0); }

.gs-handle {
  width: 40px; height: 4px;
  background: var(--text-faint);
  border-radius: 2px;
  margin: 0 auto 12px;
  opacity: 0.5;
}

.gs-title { font-size: 16px; font-weight: 800; letter-spacing: -0.01em; text-align: center; }
.gs-sub { font-size: 12px; color: var(--text-muted); text-align: center; margin-bottom: 18px; }

.receive-options { display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px; }

.receive-opt {
  display: flex; align-items: center; gap: 12px;
  padding: 14px;
  border-radius: var(--radius);
  text-align: left;
  transition: transform 0.15s ease;
}
[data-theme="light"] .receive-opt { background: var(--surface); box-shadow: var(--shadow-card); }
[data-theme="dark"] .receive-opt { background: hsl(220 25% 6% / 0.7); border: 1px solid hsl(var(--hue2) 12% 18%); }
.receive-opt:active { transform: scale(0.98); }

.receive-opt-icon {
  width: 44px; height: 44px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
  position: relative; overflow: hidden;
}
.receive-opt.q-magic .receive-opt-icon {
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow: 0 4px 12px hsl(280 70% 50% / 0.4);
}
.receive-opt.q-magic .receive-opt-icon::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.4) 85%, transparent 100%);
  animation: conicSpin 4s linear infinite;
}
.receive-opt.q-default .receive-opt-icon {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.4);
}
.receive-opt-icon svg { width: 22px; height: 22px; stroke: white; fill: none; stroke-width: 2; position: relative; z-index: 1; }

.receive-opt-text { flex: 1; min-width: 0; }
.receive-opt-text strong { display: block; font-size: 14px; font-weight: 800; }
.receive-opt-text span { display: block; font-size: 11px; color: var(--text-muted); margin-top: 2px; }
.receive-opt-chev { width: 16px; height: 16px; stroke: var(--text-muted); flex-shrink: 0; }

.gs-cancel {
  width: 100%; height: 48px;
  border-radius: var(--radius-pill);
  font-size: 14px; font-weight: 800; color: var(--text-muted);
}
[data-theme="light"] .gs-cancel { background: var(--surface); box-shadow: var(--shadow-card-sm); }
[data-theme="dark"] .gs-cancel { background: hsl(220 25% 8%); border: 1px solid hsl(var(--hue2) 12% 18%); }
```

### 16.4 Защо 4 опции (не повече, не по-малко)

| Опция | Кога Пешо я избира |
|---|---|
| **Снимай фактура** | Доставчикът дава хартиена/PDF фактура. **70% от случаите** |
| **Кажи какво има** | Малки доставки без фактура (директна от куриер). **15% от случаите** |
| **Сканирай артикулите** | Доставка без фактура, но с артикули с баркодове. **10% от случаите** |
| **Импорт файл** | Доставчикът праща CSV/Excel предварително. **5% от случаите** |

5-та опция (Partner Portal) е post-beta — когато доставчик пише в свой portal и autopush в RunMyStore.

---

## 17. OCR FLOW (Снимай фактура)

### 17.1 6-стъпков flow (от INVENTORY_HIDDEN_v3 §6)

```
1. Camera → snapshot
2. Upload → API /api/ocr/scan → scanner_documents row + jobid
3. Poll → confidence + parsed JSON
4. Confidence routing:
   ≥ 92%  → AUTO ACCEPT (toast "23 артикула, 1240€ — провери")
   75-92% → SMART UI (review screen с highlighted ниско-conf полета)
   < 75%  → REJECT → fallback voice/scan
5. Finalize → INSERT deliveries + delivery_items + UPDATE inventory
6. Trigger label printer auto pop-up
```

### 17.2 Camera screen (Step 1)

```
┌─ Header (.bm-header) ─────────────┐
│ [back]  Снимай фактура  [flash]   │
├───────────────────────────────────┤
│                                   │
│   [Camera viewport, full bleed]   │
│                                   │
│   ┌───────────────────────────┐   │
│   │                           │   │
│   │   [Frame с corners]       │   │
│   │                           │   │
│   │      Постави фактурата    │   │
│   │      в рамката            │   │
│   │                           │   │
│   └───────────────────────────┘   │
│                                   │
│  Auto-detect: чакам ясна снимка   │
│                                   │
│        ┌─────────────┐            │
│        │   [shutter] │            │
│        └─────────────┘            │
│                                   │
│  [📁 Качи от галерия]              │
└───────────────────────────────────┘
```

CSS detail: shutter button = circular, 72px, gradient `var(--accent)` + outer ring, на active scale(0.92).

### 17.3 Smart UI review (Step 4 за 75-92%)

```
┌─ Header ─────────────────────────────┐
│ [back]  Провери фактурата (87% AI)   │
├──────────────────────────────────────┤
│                                      │
│ [thumb на снимката] [AI highlight]   │
│                                      │
│ ┌─ Доставчик ──────────────────────┐ │
│ │ Емпорио ООД              [pen]   │ │  ← high conf, green
│ └──────────────────────────────────┘ │
│                                      │
│ ┌─ Дата ───────────────────────────┐ │
│ │ 09.05.2026               [pen]   │ │
│ └──────────────────────────────────┘ │
│                                      │
│ ┌─ Артикули (23) ──────────────────┐ │
│ │ ⚠ Тениска синя L  qty 5  ?€?     │ │  ← low conf cost, yellow
│ │ ✓ Дънки 32×34     qty 3  29€     │ │  ← high conf, green
│ │ ⚠ ?нашка червена  qty 2  18€     │ │  ← low conf name, yellow
│ └──────────────────────────────────┘ │
│                                      │
│ [Покажи всички] [...]                │
│                                      │
│ [Voice "сини тениски 22 лв"]         │
│                                      │
│ ───────────────────────────────────  │
│ Общо: 1,240€ (24 €? липсва)          │
│                                      │
│ ┌──────────────────────────────────┐ │
│ │   [✓] Запази доставка            │ │
│ └──────────────────────────────────┘ │
└──────────────────────────────────────┘
```

### 17.4 Smart UI HTML структура (използва P13 acc-section)

```html
<section class="acc-section open" data-status="filled">
  <div class="acc-head">
    <span class="acc-head-ic">
      <svg><!-- check --></svg>
    </span>
    <span class="acc-title">Доставчик</span>
    <span class="acc-confidence">98%</span>
  </div>
  <div class="acc-body">
    <div class="field">
      <div class="field-label">
        <span>Име на доставчик</span>
      </div>
      <div class="input-row">
        <div class="input-shell">
          <input class="input-text" value="Емпорио ООД" data-ai-confidence="0.98">
        </div>
        <button class="fbtn voice"><svg><!-- mic --></svg></button>
      </div>
    </div>
  </div>
</section>

<section class="acc-section open magic" data-status="active">
  <div class="acc-head">
    <span class="acc-head-ic">
      <svg><!-- magic-sparkles --></svg>
    </span>
    <span class="acc-title">Артикули</span>
    <span class="acc-confidence q-amber">87%</span>
  </div>
  <div class="acc-body">
    <!-- per-item rows -->
  </div>
</section>
```

### 17.5 Confidence визуализация на ниво поле

```css
.input-shell[data-ai-confidence-level="high"] {
  border: 1px solid hsl(145 60% 50% / 0.4);
}
.input-shell[data-ai-confidence-level="medium"] {
  border: 1px solid hsl(38 80% 55% / 0.4);
  animation: pulseAmber 2s ease-in-out infinite;
}
.input-shell[data-ai-confidence-level="low"] {
  border: 1px solid hsl(0 70% 55% / 0.4);
}

@keyframes pulseAmber {
  0%, 100% { box-shadow: 0 0 0 0 hsl(38 80% 55% / 0.4); }
  50% { box-shadow: 0 0 8px 2px hsl(38 80% 55% / 0.2); }
}
```

PHP логика:
```php
function confidenceLevel($score) {
    if ($score >= 0.92) return 'high';
    if ($score >= 0.75) return 'medium';
    return 'low';
}
// Скоутът е винаги в data-attr, никога visible на Митко (Hidden Inventory §6)
// Пешо вижда само цвета на border-а
```

### 17.6 Item row inline mini-wizard

Когато OCR извлече нов артикул който НЕ е в каталога:

```
┌─ Item row ─────────────────────────┐
│ ⚠ Тениска синя L                   │
│   qty: [5]    cost: [22€]          │
│                                    │
│ ⓘ Артикул не е в каталога          │
│   Нужна продажна цена              │
│                                    │
│   Продажна цена:                   │
│   [   ?? €   ] [🎤]                │
│                                    │
│   AI препоръка: 38€ (марж 73%)     │
│   [✓] Прилагам AI цена             │
└────────────────────────────────────┘
```

Зад UI: `INSERT INTO products (..., is_complete=0)`. Митко ще довърши после в products.php.

### 17.7 Финал на OCR flow

```
[Запази доставка] →
   1. INSERT deliveries (status='received')
   2. INSERT delivery_items × N
   3. UPDATE inventory.quantity (per store)
   4. UPDATE products.cost_price + cost_history
   5. Match supplier_orders → UPDATE status
   6. close lost_demand → UPDATE sale_items.product_id
   7. Trigger label printer popup
   8. Toast "Доставката е приета. 23 артикула, 1240€"
   9. Navigate to detail или back
```

### 17.8 Confidence < 75% — REJECT screen

```
┌─ Header ────────────────────────────┐
│ [back]  Не разчетох ясно            │
├─────────────────────────────────────┤
│                                     │
│ [SVG warning 64px]                  │
│                                     │
│ Снимката е размазана или е          │
│ нестандартен формат. Опитай:        │
│                                     │
│ [📷 Снимай отново]                   │
│ [🎤 Кажи какво има в нея]            │
│ [📊 Сканирай артикулите 1 по 1]      │
│                                     │
│ [Връщане]                           │
└─────────────────────────────────────┘
```

### 17.9 Audit (scanner_documents)

```sql
CREATE TABLE scanner_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  type ENUM('invoice', 'receipt', 'other') DEFAULT 'invoice',
  status ENUM('pending', 'processing', 'parsed', 'rejected', 'finalized') DEFAULT 'pending',
  image_url VARCHAR(500),
  parsed_json JSON,
  confidence_avg DECIMAL(4,3),
  ocr_engine VARCHAR(50),  -- 'gemini-vision-2.5'
  delivery_id INT NULL,    -- backfill след finalize
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  finalized_at TIMESTAMP NULL,
  INDEX(tenant_id, created_at),
  INDEX(delivery_id)
);
```

Никога delete. Append-only.

---

## 18. VOICE FLOW (Кажи какво има)

### 18.1 Voice overlay (S56 стандарт от P13)

```
┌─ Voice overlay (full screen) ──────┐
│                                    │
│   [Top: speaker name]              │
│                                    │
│            [Conic spinner          │
│             с pulse 96px]           │
│                                    │
│           Слушам...                │
│                                    │
│   "получих доставка от             │
│    Емпорио, 23 неща, 1240"         │
│   ↑ live transcript                │
│                                    │
│                                    │
│        [Готов] [Отказ]             │
└────────────────────────────────────┘
```

### 18.2 HTML

```html
<div class="voice-overlay">
  <button class="voice-overlay-close" onclick="cancelVoice()">
    <svg><!-- X --></svg>
  </button>

  <div class="voice-orb-wrap">
    <div class="voice-orb">
      <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
        <rect x="9" y="2" width="6" height="12" rx="3"/>
        <path d="M5 10v2a7 7 0 0014 0v-2"/>
      </svg>
    </div>
    <div class="voice-orb-pulse"></div>
  </div>

  <div class="voice-state">Слушам...</div>

  <div class="voice-transcript">"получих доставка от Емпорио..."</div>

  <div class="voice-actions">
    <button class="voice-action-done">Готов</button>
    <button class="voice-action-cancel">Отказ</button>
  </div>
</div>
```

### 18.3 CSS

```css
.voice-overlay {
  position: fixed; inset: 0; z-index: 200;
  background: linear-gradient(180deg, hsl(280 50% 12% / 0.98), hsl(220 25% 4.8% / 0.98));
  backdrop-filter: blur(20px);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 40px 20px;
}

.voice-orb-wrap { position: relative; width: 96px; height: 96px; margin-bottom: 24px; }
.voice-orb {
  width: 96px; height: 96px;
  border-radius: 50%;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  display: grid; place-items: center;
  position: relative; overflow: hidden;
  box-shadow: 0 8px 32px hsl(280 70% 50% / 0.6);
}
.voice-orb::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.4) 85%, transparent 100%);
  animation: conicSpin 2s linear infinite;
}
.voice-orb svg { width: 36px; height: 36px; position: relative; z-index: 1; }

.voice-orb-pulse {
  position: absolute; inset: -8px;
  border-radius: 50%;
  border: 2px solid hsl(280 70% 55% / 0.5);
  animation: voicePulse 1.6s ease-out infinite;
}
@keyframes voicePulse {
  0%   { transform: scale(0.95); opacity: 0.7; }
  100% { transform: scale(1.4);  opacity: 0;   }
}

.voice-state {
  font-size: 14px; font-weight: 800; color: white;
  letter-spacing: 0.05em; text-transform: uppercase;
  font-family: var(--font-mono);
}

.voice-transcript {
  font-size: 16px; line-height: 1.5;
  color: rgba(255,255,255,0.9);
  text-align: center; max-width: 320px;
  margin: 16px 0 32px;
  min-height: 50px;
  font-style: italic;
}

.voice-actions { display: flex; gap: 10px; }
.voice-action-done {
  padding: 12px 24px; border-radius: var(--radius-pill);
  background: linear-gradient(135deg, hsl(145 70% 50%), hsl(155 70% 40%));
  color: white; font-weight: 800; font-size: 14px;
  box-shadow: 0 4px 14px hsl(145 70% 45% / 0.4);
}
.voice-action-cancel {
  padding: 12px 20px; border-radius: var(--radius-pill);
  background: hsl(220 25% 12%);
  color: rgba(255,255,255,0.7); font-weight: 700; font-size: 13px;
  border: 1px solid hsl(var(--hue2) 12% 18%);
}
```

### 18.4 STT engine choice (LOCKED)

| Сценарий | Engine | Защо |
|---|---|---|
| Български цени (числа) | **Web Speech API** (`bg-BG`) | Instant, offline-friendly, 90% точност |
| Български текст (имена, описания) | **Web Speech API** | Free, fast |
| Не-български цени | **Whisper Groq** | По-добра разпознаваемост на code-mixing |
| Continuous scan voice команди | **Web Speech API** | Нужен real-time |

LOCKED commits: `4222a66` + `1b80106`. Никой да не пипа `_wizPriceParse` parser в products.php.

### 18.5 Voice → Struct flow

```php
// След STT:
$transcript = "получих доставка от Емпорио 23 неща 1240";

// Server-side parse (Gemini 2.5 Flash):
{
  "type": "delivery",
  "supplier_name": "Емпорио",
  "supplier_match_id": 42,        // fuzzy match by name
  "supplier_match_confidence": 0.94,
  "items_total_count": 23,
  "amount_total_eur": 1240.00,
  "items": []                      // empty — Пешо ще ги сканира
}

// → opens "Smart UI review" с pre-filled supplier + total + празен items list
// → Пешо избира Continuous scan → ентърва items 1 по 1
```

### 18.6 Voice fallback

| Първи опит | Fallback 1 | Fallback 2 |
|---|---|---|
| OCR (90%+ conf) | Voice (overlay) | Continuous scan |
| Voice (90%+ conf) | Manual mini-wizard | — |
| Continuous scan | Voice ad-hoc | Manual entry |

### 18.7 Voice бутон навсякъде

Всеки text/number input в deliveries flow има `.fbtn.voice` бутон отдясно. Никога няма поле без voice.

```html
<div class="input-row">
  <div class="input-shell">
    <input class="input-text" placeholder="Количество">
  </div>
  <button class="fbtn voice" aria-label="Voice input">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <rect x="9" y="2" width="6" height="12" rx="3"/>
      <path d="M5 10v2a7 7 0 0014 0v-2"/>
    </svg>
  </button>
</div>
```

CSS:
```css
.fbtn {
  width: 42px; height: 42px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
}
[data-theme="light"] .fbtn { background: var(--surface); box-shadow: var(--shadow-card-sm); }
[data-theme="dark"] .fbtn { background: hsl(220 25% 8%); border: 1px solid hsl(var(--hue2) 12% 18%); }
.fbtn:active { box-shadow: var(--shadow-pressed); }
.fbtn svg { width: 16px; height: 16px; stroke: var(--text-muted); fill: none; stroke-width: 2; }
.fbtn.voice svg { stroke: hsl(280 70% 55%); }
[data-theme="dark"] .fbtn.voice svg { stroke: hsl(280 70% 70%); }
```

### 18.8 Voice команди в continuous scan mode

| Команда | Действие |
|---|---|
| "следващ" | Save current item, новото скениране |
| "назад" | Premove last item |
| "запиши" / "готов" | Finalize delivery |
| "отказ" | Cancel scan session |
| "увеличи количество" | qty++ |
| "поправи цена" | Edit cost_price на текущия item |
| "ново" | Start fresh delivery |

---

## КРАЙ НА ЧАСТ 2

**Какво следва в Част 3:**
- Manual flow (Lightspeed/Microinvest стандарт) с pre-fill, continuous scan, typeahead
- 4 пътя на добавяне (от sent поръчка / scan / typeahead / quick-create)
- Импорт flow (CSV/Excel/PDF + email forward)
- Mini-wizard за нов артикул в доставка (P13 минимум секция)
- Smart Pricing Rules (печалба %, закръгляване, per-category, AI препоръки)
- Какво НЕ вижда Пешо в лесен (експлицитен списък с обяснения)

---

# ═══════════════════════════════════════
# ЧАСТ 2A — ЛЕСЕН РЕЖИМ: ГЛАВЕН ЕКРАН
# ═══════════════════════════════════════

## 8. ГЛАВЕН ЕКРАН НА ЛЕСЕН РЕЖИМ

### 8.1 Структура (Inbox style, не Dashboard)

```
┌─────────────────────────────────────┐
│ [≪]  Доставки           [🔍][⚡][☀] │  ← .bm-header (56px)
├─────────────────────────────────────┤
│ ┌─────────────────────────────────┐ │
│ │ 🎤 Кажи на AI                  │ │  ← .voice-bar
│ │    "получих доставка"           │ │
│ └─────────────────────────────────┘ │
│                                      │
│ ┌─[ Лесен ]─[ Разширен ]──────────┐ │  ← .mode-toggle (post-toggle hidden ако role=seller)
│                                      │
│ ┌─────────────────────────────────┐ │
│ │ ⏰ Закъсняла доставка           │ │  ← AI карта q-loss (макс 3)
│ │ Емпорио ООД, чакана 06.05      │ │
│ │ [Виж] [Свържи се]               │ │
│ └─────────────────────────────────┘ │
│ ┌─────────────────────────────────┐ │
│ │ 📦 Очаквана днес                 │ │  ← AI карта q-magic
│ │ Турция Текстил — 240 артикула   │ │
│ │ [Получи сега]                    │ │
│ └─────────────────────────────────┘ │
│ ┌─────────────────────────────────┐ │
│ │ ✓ Lost demand затворен          │ │  ← AI карта q-gain (по choice)
│ │ €420 възстановени               │ │
│ │ [Виж детайли]                    │ │
│ └─────────────────────────────────┘ │
│                                      │
│ ┌─────────────────────────────────┐ │
│ │      ⬇                            │ │  ← голям op-btn (q-default)
│ │   ПОЛУЧИ ДОСТАВКА                │ │     86px height
│ │   Снимай / Кажи / Скенирай      │ │
│ └─────────────────────────────────┘ │
│                                      │
│ [Нова поръчка →]                     │  ← малък aux линк
│                                      │
│ Последно: вчера 17:42                │  ← timestamp
├─────────────────────────────────────┤
│  [🤖] [📦] [📊] [⚡]                  │  ← bottom nav
└─────────────────────────────────────┘
```

### 8.2 Принципи на главния екран

| Принцип | Реализация |
|---|---|
| Inbox style | Не KPI cards, а "ето какво трябва да правиш" |
| Макс 3 AI карти | Selection Engine избира top 3 priority topics |
| 1 голям бутон + 1 малък aux | "Получи доставка" е primary; "Нова поръчка" е tap-on-need |
| Без статуси/филтри/sort | Цялата сложност скрита; Митко може да бутне toggle |
| Всичко 1-tap | Нито една задача >2 tap-а |

### 8.3 Padding & spacing

```css
.shell {
  max-width: 480px; margin: 0 auto;
  padding: 0 12px calc(86px + env(safe-area-inset-bottom, 0));
}
.app { padding: 12px; }

/* AI карта margin */
.deliv-ai-signal { margin-bottom: 10px; }

/* Op-btn separation */
.op-btn-container { margin-top: 18px; }
```

### 8.4 Footer (timestamp + sync indicator)

```html
<div class="deliv-footer">
  <span class="footer-time">Последно обновено: <span id="lastSync">14:23</span></span>
  <span class="sync-dot online"></span>
</div>
```

CSS:
- `font-size: 9.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase`
- `color: var(--text-faint)`
- `.sync-dot.online { background: hsl(145 70% 50%); width: 6px; height: 6px; border-radius: 50% }`
- `.sync-dot.offline { background: hsl(38 90% 60%); animation: pulse 2s infinite }`

---

## 9. AI СИГНАЛИ В ЛЕСЕН РЕЖИМ

### 9.1 Колко

**Точно 3, не повече.** Ако има >3 кандидат-теми, Selection Engine приоритизира. Останалите се отлагат до cooldown.

### 9.2 Приоритет на теми (top 6 за beta)

| # | Topic | Priority | Hue клас | Кога trigger |
|---|---|---|---|---|
| 1 | `deliv_overdue` | P0 | q-loss | Доставка закъсняла >3 дни |
| 2 | `deliv_expected_today` | P0 | q-magic | Доставка очаквана днес |
| 3 | `deliv_partial_arrived` | P1 | q-amber | Получена частично, липсват items |
| 4 | `deliv_lost_demand_resolved` | P1 | q-gain | Lost demand затворен |
| 5 | `deliv_ai_draft_ready` | P1 | q-magic | AI генерирана чернова за поръчка |
| 6 | `deliv_category_count` | P2 | q-magic | "Хайде да броим тениски" (Hidden Inventory) |

**Selection Engine** (от `05_selection_engine.md`):
```
1. Filter by role (Пешо вижда P0+P1, Митко вижда P0+P1+P2 в лесен)
2. Filter by cooldown (24h per topic)
3. Sort by priority desc, then timestamp asc
4. Take top 3
5. Render
```

### 9.3 AI карта компонент (1:1 от P13 + P10)

```html
<article class="deliv-ai-signal q-loss" data-topic="deliv_overdue">
  <div class="signal-head">
    <span class="signal-orb">
      <!-- SVG icon, stroke=white в gradient bg -->
      <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <polyline points="12 6 12 12 16 14"/>
      </svg>
    </span>
    <div class="signal-text">
      <div class="signal-title">Закъсняла доставка</div>
      <div class="signal-sub">Емпорио ООД · чакана 06.05.2026</div>
    </div>
    <button class="signal-dismiss" aria-label="Затвори">
      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>
  </div>
  <div class="signal-body">
    <p>Закъснява <strong>3 дни</strong>. Това е 2-ра закъсняла доставка от Емпорио този месец.</p>
  </div>
  <div class="signal-actions">
    <button class="signal-action primary">Виж детайли</button>
    <button class="signal-action">Свържи се</button>
  </div>
</article>
```

**CSS базови tokens (light):**
```css
.deliv-ai-signal {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-card);
  padding: 14px;
  margin-bottom: 10px;
  position: relative;
}
.signal-head { display: flex; gap: 10px; align-items: center; margin-bottom: 8px; }
.signal-orb {
  width: 36px; height: 36px; border-radius: var(--radius-icon);
  display: grid; place-items: center; flex-shrink: 0;
  position: relative; overflow: hidden;
}
.signal-orb svg { width: 16px; height: 16px; position: relative; z-index: 1; }
.signal-text { flex: 1; min-width: 0; }
.signal-title { font-size: 14px; font-weight: 800; letter-spacing: -0.01em; color: var(--text); }
.signal-sub {
  font-family: var(--font-mono); font-size: 9px; font-weight: 700;
  letter-spacing: 0.04em; color: var(--text-muted); margin-top: 2px;
}
.signal-dismiss {
  width: 26px; height: 26px; border-radius: var(--radius-icon);
  display: grid; place-items: center;
  background: var(--surface); box-shadow: var(--shadow-card-sm);
}
.signal-dismiss svg { width: 12px; height: 12px; stroke: var(--text-faint); }
.signal-body { font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 12px; }
.signal-body strong { color: var(--text); font-weight: 700; }
.signal-actions { display: flex; gap: 8px; }
.signal-action {
  flex: 1; height: 38px; border-radius: var(--radius-pill);
  font-size: 12px; font-weight: 800; letter-spacing: -0.01em;
  background: var(--bg-main); box-shadow: var(--shadow-card-sm);
  color: var(--text);
}
.signal-action.primary {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.35);
  color: white;
}
.signal-action:active { box-shadow: var(--shadow-pressed); }
```

**CSS hue variants (orb gradients):**
```css
/* q-loss orb: червен */
.deliv-ai-signal.q-loss .signal-orb {
  background: linear-gradient(135deg, hsl(0 70% 55%), hsl(15 70% 50%));
  box-shadow: 0 4px 12px hsl(0 70% 50% / 0.4);
}

/* q-magic orb: лилав conic shimmer */
.deliv-ai-signal.q-magic .signal-orb {
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow: 0 4px 12px hsl(280 70% 50% / 0.4);
}
.deliv-ai-signal.q-magic .signal-orb::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.4) 85%, transparent 100%);
  animation: conicSpin 4s linear infinite;
}

/* q-gain orb: зелен */
.deliv-ai-signal.q-gain .signal-orb {
  background: linear-gradient(135deg, hsl(145 70% 50%), hsl(155 70% 40%));
  box-shadow: 0 4px 12px hsl(145 70% 45% / 0.4);
}

/* q-amber orb: кехлибар */
.deliv-ai-signal.q-amber .signal-orb {
  background: linear-gradient(135deg, hsl(38 90% 55%), hsl(28 85% 50%));
  box-shadow: 0 4px 12px hsl(38 90% 50% / 0.4);
}
```

**CSS dark mode (sacred neon glass):**
```css
[data-theme="dark"] .deliv-ai-signal {
  background: hsl(220 25% 6% / 0.7);
  backdrop-filter: blur(8px);
  border: 1px solid hsl(var(--hue2) 12% 18%);
  position: relative;
}
[data-theme="dark"] .deliv-ai-signal::before,
[data-theme="dark"] .deliv-ai-signal::after {
  content: ''; position: absolute; pointer-events: none;
  border-radius: inherit; mix-blend-mode: plus-lighter;
}
[data-theme="dark"] .deliv-ai-signal::before {
  inset: 0; padding: 1px;
  background: conic-gradient(from 220deg at 50% 0%,
    oklch(0.7 0.18 var(--hue1) / 0.8) 0deg,
    oklch(0.65 0.2 280 / 0.6) 90deg,
    oklch(0.7 0.18 var(--hue2) / 0.4) 180deg,
    oklch(0.6 0.16 var(--hue1) / 0.3) 270deg,
    oklch(0.7 0.18 var(--hue1) / 0.8) 360deg);
  -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
  -webkit-mask-composite: xor; mask-composite: exclude;
  opacity: 0.7;
}
[data-theme="dark"] .deliv-ai-signal::after {
  inset: -1px;
  background: radial-gradient(80% 60% at 50% 0%,
    oklch(0.7 0.2 var(--hue1) / 0.18), transparent 70%);
  opacity: 0.9; filter: blur(12px);
}

/* Hue overrides в dark — q-loss глоуа в червен */
[data-theme="dark"] .deliv-ai-signal.q-loss::before {
  background: conic-gradient(from 220deg at 50% 0%,
    oklch(0.65 0.22 25 / 0.8) 0deg,
    oklch(0.6 0.24 15 / 0.6) 90deg,
    oklch(0.65 0.22 25 / 0.4) 180deg,
    oklch(0.65 0.22 25 / 0.8) 360deg);
}
[data-theme="dark"] .deliv-ai-signal.q-loss::after {
  background: radial-gradient(80% 60% at 50% 0%,
    oklch(0.7 0.22 25 / 0.22), transparent 70%);
}

/* q-magic */
[data-theme="dark"] .deliv-ai-signal.q-magic::before {
  background: conic-gradient(from 220deg at 50% 0%,
    oklch(0.7 0.22 280 / 0.8) 0deg,
    oklch(0.65 0.25 305 / 0.6) 90deg,
    oklch(0.7 0.22 280 / 0.4) 180deg,
    oklch(0.7 0.22 280 / 0.8) 360deg);
}
[data-theme="dark"] .deliv-ai-signal.q-magic::after {
  background: radial-gradient(80% 60% at 50% 0%,
    oklch(0.72 0.24 280 / 0.28), transparent 70%);
}

/* q-gain */
[data-theme="dark"] .deliv-ai-signal.q-gain::before {
  background: conic-gradient(from 220deg at 50% 0%,
    oklch(0.7 0.18 145 / 0.8) 0deg,
    oklch(0.68 0.18 155 / 0.6) 90deg,
    oklch(0.7 0.18 145 / 0.4) 180deg,
    oklch(0.7 0.18 145 / 0.8) 360deg);
}

/* q-amber */
[data-theme="dark"] .deliv-ai-signal.q-amber::before {
  background: conic-gradient(from 220deg at 50% 0%,
    oklch(0.72 0.18 70 / 0.8) 0deg,
    oklch(0.7 0.2 60 / 0.6) 90deg,
    oklch(0.72 0.18 70 / 0.4) 180deg,
    oklch(0.72 0.18 70 / 0.8) 360deg);
}
```

### 9.4 Animation на entry

```css
.deliv-ai-signal {
  animation: fadeInUp 0.5s var(--ease-spring) both;
}
.deliv-ai-signal:nth-child(1) { animation-delay: 0ms; }
.deliv-ai-signal:nth-child(2) { animation-delay: 80ms; }
.deliv-ai-signal:nth-child(3) { animation-delay: 160ms; }
```

### 9.5 Dismiss поведение

- Tap на ✕ → `animation: fadeOut 0.3s ease forwards`
- POST `/api/ai-signals/dismiss?topic=X&tenant=Y`
- Cooldown 24h per topic — не се появи отново до утре
- Audit запис: `ai_signal_dismissals(topic, dismissed_by, dismissed_at)`

### 9.6 Tap поведение

- Tap на карта (не на бутон) → primary action
- Tap на primary action бутон → същото
- Tap на secondary бутон → различно действие
- Long-press (post-beta) → "Не ми показвай повече тази тема"

### 9.7 Empty state (няма AI signals)

```html
<div class="deliv-empty-signals">
  <span class="empty-orb">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <polyline points="20 6 9 17 4 12"/>
    </svg>
  </span>
  <div class="empty-title">Всичко наред</div>
  <div class="empty-sub">Нямаш чакащи проблеми с доставките</div>
</div>
```

CSS: q-gain orb (зелен), title 16px font-weight 800, sub 12px font-mono.

---

## 10. ОПЕРАЦИОНЕН БУТОН "ПОЛУЧИ ДОСТАВКА"

### 10.1 Структура (1:1 от P10 .op-btn pattern)

```html
<button class="deliv-op-btn q-default" onclick="openDeliveryReceiveSheet()">
  <span class="op-icon">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round">
      <rect x="1" y="3" width="15" height="13" rx="1"/>
      <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
      <circle cx="5.5" cy="18.5" r="2.5"/>
      <circle cx="18.5" cy="18.5" r="2.5"/>
      <line x1="12" y1="3" x2="12" y2="3" stroke-width="2.5"/>
      <polyline points="8 12 12 16 16 12" stroke-width="2.5"/>
    </svg>
  </span>
  <div class="op-text">
    <span class="op-label">Получи доставка</span>
    <span class="op-sub">Снимай · кажи · скенирай · импорт</span>
  </div>
  <span class="op-chev">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <polyline points="9 18 15 12 9 6"/>
    </svg>
  </span>
</button>
```

### 10.2 CSS (light)

```css
.deliv-op-btn {
  display: flex; align-items: center; gap: 12px;
  width: 100%; min-height: 86px;
  padding: 14px 16px;
  border-radius: var(--radius);
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 8px 24px hsl(var(--hue1) 80% 50% / 0.4);
  color: white;
  position: relative; overflow: hidden;
  margin-top: 18px;
  cursor: pointer;
  transition: transform 0.15s var(--ease);
}
.deliv-op-btn::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg,
    transparent 70%, rgba(255,255,255,0.3) 85%, transparent 100%);
  animation: conicSpin 5s linear infinite;
  z-index: 0;
}
.deliv-op-btn > * { position: relative; z-index: 5; }
.deliv-op-btn:active { transform: scale(0.98); }

.op-icon {
  width: 48px; height: 48px; border-radius: var(--radius-icon);
  background: rgba(255,255,255,0.2); backdrop-filter: blur(4px);
  display: grid; place-items: center; flex-shrink: 0;
}
.op-icon svg { width: 22px; height: 22px; stroke: white; }
.op-text { flex: 1; text-align: left; }
.op-label {
  display: block; font-size: 17px; font-weight: 800;
  letter-spacing: -0.01em;
}
.op-sub {
  display: block; font-family: var(--font-mono);
  font-size: 10px; font-weight: 700; letter-spacing: 0.06em;
  text-transform: uppercase; opacity: 0.85; margin-top: 2px;
}
.op-chev {
  width: 28px; height: 28px; opacity: 0.7;
}
.op-chev svg { width: 16px; height: 16px; stroke: white; stroke-width: 2.5; }
```

### 10.3 Aux линк "Нова поръчка"

```html
<a href="/orders.php?action=create" class="deliv-aux-link">
  <span>Нова поръчка</span>
  <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
    <polyline points="9 18 15 12 9 6"/>
  </svg>
</a>
```

CSS:
- `display: flex; gap: 6px; align-items: center; justify-content: center`
- `font-size: 13px; font-weight: 700`
- `color: var(--accent)`; в dark: `hsl(var(--hue1) 80% 75%)`
- `padding: 10px 0; margin-top: 4px`
- Tap → `transform: translateX(2px); transition: 0.2s`

---

## 11. EMPTY STATE НА ЛЕСЕН РЕЖИМ

### 11.1 Кога

- Първо влизане (още няма доставки) — onboarding
- Всичко затворено (няма pending, няма AI signals)

### 11.2 Onboarding empty state

```html
<div class="deliv-onboarding">
  <span class="onb-orb">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <rect x="1" y="3" width="15" height="13" rx="1"/>
      <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
      <circle cx="5.5" cy="18.5" r="2.5"/>
      <circle cx="18.5" cy="18.5" r="2.5"/>
    </svg>
  </span>
  <div class="onb-title">Първа доставка?</div>
  <div class="onb-sub">Вкарай артикулите и AI ще научи цените</div>
  <button class="onb-cta" onclick="openDeliveryReceiveSheet()">
    Започни сега
  </button>
</div>
```

CSS:
- Container: text-align center, padding 40px 20px
- Orb: 64px size, q-magic gradient
- Title: 18px font-weight 800, margin-top 16px
- Sub: 13px color text-muted, margin-top 6px line-height 1.5
- CTA: q-default bg, full-width, height 48px, margin-top 24px

### 11.3 "Всичко наред" state

Показва само сини мотиватор card (q-gain) + op-btn "Получи доставка". AI signals отсъстват.

---

## 12. LOADING STATE НА ЛЕСЕН РЕЖИМ

### 12.1 Skeleton (initial load)

```html
<div class="deliv-skeleton">
  <div class="sk-bar"></div>           <!-- voice bar placeholder -->
  <div class="sk-card"></div>           <!-- AI signal placeholder -->
  <div class="sk-card"></div>
  <div class="sk-card"></div>
  <div class="sk-op-btn"></div>         <!-- op-btn placeholder -->
</div>
```

CSS:
```css
.deliv-skeleton .sk-bar,
.deliv-skeleton .sk-card,
.deliv-skeleton .sk-op-btn {
  background: linear-gradient(90deg,
    var(--surface) 0%,
    var(--surface-2) 50%,
    var(--surface) 100%);
  background-size: 200% 100%;
  animation: shimmerSlide 1.5s ease-in-out infinite;
  border-radius: var(--radius);
}
.deliv-skeleton .sk-bar { height: 50px; margin-bottom: 14px; border-radius: var(--radius-pill); }
.deliv-skeleton .sk-card { height: 96px; margin-bottom: 10px; }
.deliv-skeleton .sk-op-btn { height: 86px; margin-top: 18px; }
```

### 12.2 Refresh (pull-to-refresh)

```html
<div class="deliv-pull-refresh" id="pullRefresh">
  <span class="pull-spinner">
    <svg viewBox="0 0 24 24"><!-- conic spinning --></svg>
  </span>
  <span class="pull-text">Обновявам...</span>
</div>
```

CSS:
- `transform: translateY(-100%)` initial; `translateY(0)` при refresh
- Spinner = conic shimmer same as voice-bar

### 12.3 AI signal loading

Когато AI signal-ът има computed данни но waiting за text:
```html
<article class="deliv-ai-signal q-magic loading">
  <!-- normal struct -->
  <div class="signal-body">
    <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
  </div>
</article>
```

Typing dots animation от P10 (`@keyframes typingDot`).

---

## 13. КАКВО НЕ ВИЖДА ПЕШО В ЛЕСЕН РЕЖИМ

| Елемент | Защо |
|---|---|
| Status pills (sent/acked/partial/overdue) | Сложно; AI казва само "имаш проблем" с context |
| Filter accordion | Не му трябва |
| Sort dropdown | Не му трябва |
| KPI strip | Не разбира числа без context |
| List on date/supplier (всички) | Inbox показва само необходимото |
| `confidence_score` | Hidden Inventory §6 |
| Cost breakdown в детайл | Само цена per брой |
| Audit log/edit history | Не му е работа |
| Bulk операции (long-press) | Single-item flow |
| Меню (☰) с 6 секции | Twiгер само от 1 бутон + AI signals |
| Multi-store split prompt | Auto-handled от AI или Митко после |
| Промо/discount calculations | Не му е работа |
| Tax/VAT преглед | Не му е работа |
| Supplier reliability score | Не му е работа |

**ВАЖНО:** В детайл на доставка (deliveries.php?id=X) Пешо вижда **същите детайли** като Митко за тази **една** доставка — само цени, артикули, бройки. Edit history и audit log са скрити. Това е защото детайлът е shared компонент; mode-toggle-ът оперира на нивото на главния списък.

---

## КРАЙ НА ЧАСТ 2A

# ═══════════════════════════════════════
# ЧАСТ 2B — ЛЕСЕН РЕЖИМ: 4 ВХОДА + OCR + VOICE
# ═══════════════════════════════════════

## 14. "ПОЛУЧИ ДОСТАВКА" — BOTTOM SHEET С 4 ОПЦИИ

### 14.1 Trigger

Tap на голям op-btn → bottom sheet се вдига. Базиран на P13 `.gs-ov` + `.gs-sheet` pattern.

### 14.2 HTML структура

```html
<div class="gs-ov" id="receiveSheetOv" onclick="closeReceiveSheet()"></div>
<div class="gs-sheet" id="receiveSheet" role="dialog" aria-label="Получи доставка">
  <div class="gs-grip"></div>
  <h2 class="gs-title">Как пристига доставката?</h2>

  <button class="recv-opt q-magic" onclick="startOCR()">
    <span class="recv-opt-orb">
      <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
        <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/>
        <circle cx="12" cy="13" r="4"/>
      </svg>
    </span>
    <div class="recv-opt-text">
      <span class="recv-opt-label">Снимай фактура</span>
      <span class="recv-opt-sub">AI чете автоматично</span>
    </div>
    <span class="recv-opt-pill">Препоръчано</span>
  </button>

  <button class="recv-opt q-default" onclick="startVoiceDelivery()">
    <span class="recv-opt-orb">
      <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
        <rect x="9" y="2" width="6" height="12" rx="3"/>
        <path d="M5 10v2a7 7 0 0014 0v-2"/>
      </svg>
    </span>
    <div class="recv-opt-text">
      <span class="recv-opt-label">Кажи какво има</span>
      <span class="recv-opt-sub">"тениска синя 10 броя"</span>
    </div>
  </button>

  <button class="recv-opt q-default" onclick="startManualScan()">
    <span class="recv-opt-orb">
      <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
        <path d="M3 7V5a2 2 0 012-2h2"/><path d="M17 3h2a2 2 0 012 2v2"/>
        <path d="M21 17v2a2 2 0 01-2 2h-2"/><path d="M7 21H5a2 2 0 01-2-2v-2"/>
        <line x1="7" y1="12" x2="17" y2="12"/>
      </svg>
    </span>
    <div class="recv-opt-text">
      <span class="recv-opt-label">Скенирай артикули</span>
      <span class="recv-opt-sub">Един по един с баркод</span>
    </div>
  </button>

  <button class="recv-opt q-default" onclick="startImport()">
    <span class="recv-opt-orb">
      <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
        <polyline points="17 8 12 3 7 8"/>
        <line x1="12" y1="3" x2="12" y2="15"/>
      </svg>
    </span>
    <div class="recv-opt-text">
      <span class="recv-opt-label">Импорт файл</span>
      <span class="recv-opt-sub">PDF · CSV · Excel · email</span>
    </div>
  </button>
</div>
```

### 14.3 CSS

```css
.gs-ov {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.4); backdrop-filter: blur(4px);
  z-index: 100; opacity: 0; pointer-events: none;
  transition: opacity 0.32s var(--ease);
}
.gs-ov.open { opacity: 1; pointer-events: auto; }

.gs-sheet {
  position: fixed; bottom: 0; left: 0; right: 0;
  max-width: 480px; margin: 0 auto;
  background: var(--bg-main);
  border-radius: 24px 24px 0 0;
  padding: 8px 16px calc(20px + env(safe-area-inset-bottom, 0));
  z-index: 101;
  transform: translateY(100%);
  transition: transform 0.32s var(--ease-spring);
  box-shadow: 0 -8px 32px rgba(0,0,0,0.16);
}
[data-theme="dark"] .gs-sheet {
  background: hsl(220 25% 6% / 0.95);
  backdrop-filter: blur(20px);
  border-top: 1px solid hsl(var(--hue2) 12% 18%);
}
.gs-sheet.open { transform: translateY(0); }

.gs-grip {
  width: 40px; height: 4px; border-radius: 2px;
  background: var(--text-faint); opacity: 0.4;
  margin: 4px auto 14px;
}
.gs-title {
  font-size: 17px; font-weight: 800; letter-spacing: -0.01em;
  margin-bottom: 14px; padding: 0 4px;
}

.recv-opt {
  display: flex; align-items: center; gap: 12px;
  width: 100%; min-height: 68px;
  padding: 12px 14px;
  border-radius: var(--radius);
  background: var(--surface); box-shadow: var(--shadow-card);
  margin-bottom: 10px;
  position: relative;
  cursor: pointer;
  transition: transform 0.15s var(--ease);
}
[data-theme="dark"] .recv-opt {
  background: hsl(220 25% 6% / 0.7); backdrop-filter: blur(8px);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}
.recv-opt:active { transform: scale(0.98); box-shadow: var(--shadow-pressed); }

.recv-opt-orb {
  width: 44px; height: 44px; border-radius: var(--radius-icon);
  display: grid; place-items: center; flex-shrink: 0;
  position: relative; overflow: hidden;
}
.recv-opt-orb svg { width: 18px; height: 18px; position: relative; z-index: 1; }

.recv-opt.q-magic .recv-opt-orb {
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow: 0 4px 12px hsl(280 70% 50% / 0.4);
}
.recv-opt.q-magic .recv-opt-orb::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.4) 85%, transparent 100%);
  animation: conicSpin 4s linear infinite;
}
.recv-opt.q-default .recv-opt-orb {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.4);
}

.recv-opt-text { flex: 1; text-align: left; min-width: 0; }
.recv-opt-label {
  display: block; font-size: 14px; font-weight: 800;
  letter-spacing: -0.01em; color: var(--text);
}
.recv-opt-sub {
  display: block; font-family: var(--font-mono);
  font-size: 9.5px; font-weight: 700; letter-spacing: 0.06em;
  text-transform: uppercase; color: var(--text-muted); margin-top: 2px;
}
.recv-opt-pill {
  font-size: 9.5px; font-weight: 800; letter-spacing: 0.04em;
  text-transform: uppercase;
  padding: 4px 8px; border-radius: var(--radius-pill);
  background: linear-gradient(135deg, hsl(38 90% 55%), hsl(28 85% 50%));
  color: white; box-shadow: 0 2px 8px hsl(38 90% 50% / 0.4);
  flex-shrink: 0;
}
```

### 14.4 Защо точно 4 опции

| Опция | Use case в ENI |
|---|---|
| 📷 **Снимай фактура** | 70% от случаите. ENI получава хартиена/PDF фактура — Пешо снимка от телефона |
| 🎤 **Кажи какво има** | 15% — бързи кашони, дребни доставки, без хартия |
| ⚡ **Скенирай артикули** | 10% — когато доставчикът дава barcode-нати артикули с printout |
| 📥 **Импорт файл** | 5% — когато доставчикът праща PDF/Excel email и Митко/Пешо forward-ват |

Не повече от 4 — закон №2 от P13 (`.acc-section` max 4 visible на secrh).

### 14.5 5-та опция (post-beta) — Partner Portal

Когато ENI има 20+ доставки/месец от един доставчик → Partner Portal endpoint където доставчикът директно качва документ → автоматично се появява в `/api/deliveries?source=partner_portal`.

---

## 15. OCR FLOW (СНИМАЙ ФАКТУРА)

### 15.1 6-стъпков flow (от INVENTORY_HIDDEN_v3 §6)

```
СТЪПКА 1: Scanner Camera View
  ↓
СТЪПКА 2: Снимка → Upload + OCR job
  ↓
СТЪПКА 3: Confidence Routing
  ├─ >92% → AUTO_ACCEPT (skip review)
  ├─ 75-92% → SMART REVIEW UI
  └─ <75% → REJECT + fallback prompt
  ↓
СТЪПКА 4: Supplier Match (auto или ask)
  ↓
СТЪПКА 5: Order Match (auto-link sent → received)
  ↓
СТЪПКА 6: Finalize → push inventory + close lost_demand
```

### 15.2 СТЪПКА 1 — Scanner Camera View

```html
<div class="ocr-camera-view">
  <video id="ocrVideo" autoplay playsinline></video>
  <div class="ocr-overlay">
    <div class="ocr-frame"></div>
    <p class="ocr-hint">Постави фактурата вътре в рамката</p>
    <div class="ocr-corners">
      <span class="corner tl"></span><span class="corner tr"></span>
      <span class="corner bl"></span><span class="corner br"></span>
    </div>
  </div>
  <button class="ocr-shutter" onclick="captureInvoice()">
    <span class="ocr-shutter-ring"></span>
  </button>
  <button class="ocr-cancel" onclick="closeOCR()">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
    </svg>
  </button>
  <button class="ocr-flash" onclick="toggleFlash()">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
    </svg>
  </button>
</div>
```

CSS:
```css
.ocr-camera-view {
  position: fixed; inset: 0; background: black; z-index: 200;
}
.ocr-camera-view video { width: 100%; height: 100%; object-fit: cover; }
.ocr-overlay {
  position: absolute; inset: 0;
  display: grid; place-items: center;
}
.ocr-frame {
  width: 75%; aspect-ratio: 0.75;
  border: 2px dashed white; border-radius: 12px;
  box-shadow: 0 0 0 9999px rgba(0,0,0,0.5);
}
.ocr-corners .corner {
  position: absolute; width: 24px; height: 24px;
  border: 3px solid hsl(var(--hue1) 80% 70%);
}
.corner.tl { top: 12.5%; left: 12.5%; border-right: 0; border-bottom: 0; }
.corner.tr { top: 12.5%; right: 12.5%; border-left: 0; border-bottom: 0; }
.corner.bl { bottom: 12.5%; left: 12.5%; border-right: 0; border-top: 0; }
.corner.br { bottom: 12.5%; right: 12.5%; border-left: 0; border-top: 0; }

.ocr-hint {
  position: absolute; top: 80px; left: 50%; transform: translateX(-50%);
  color: white; font-size: 13px; font-weight: 700;
  background: rgba(0,0,0,0.6); padding: 8px 14px; border-radius: var(--radius-pill);
  backdrop-filter: blur(8px);
}
.ocr-shutter {
  position: absolute; bottom: calc(40px + env(safe-area-inset-bottom, 0));
  left: 50%; transform: translateX(-50%);
  width: 72px; height: 72px; border-radius: 50%;
  background: white; border: 4px solid rgba(255,255,255,0.4);
  cursor: pointer;
}
.ocr-shutter:active { transform: translateX(-50%) scale(0.94); }
.ocr-cancel, .ocr-flash {
  position: absolute; top: calc(20px + env(safe-area-inset-top, 0));
  width: 40px; height: 40px; border-radius: 50%;
  background: rgba(0,0,0,0.5); display: grid; place-items: center;
  backdrop-filter: blur(8px);
}
.ocr-cancel { left: 16px; }
.ocr-flash { right: 16px; }
.ocr-cancel svg, .ocr-flash svg { width: 18px; height: 18px; }
```

### 15.3 СТЪПКА 2 — Upload + OCR job

PHP backend:
```php
// /api/ocr/scan
function startOCRScan($file_blob, $tenant_id, $user_id) {
    $job_id = uuid();
    $path = uploadFile($file_blob, "ocr/$tenant_id/$job_id.jpg");

    DB::run("INSERT INTO scanner_documents (
        id, tenant_id, user_id, file_path, status, created_at
    ) VALUES (?, ?, ?, ?, 'processing', NOW())",
        [$job_id, $tenant_id, $user_id, $path]);

    // Async dispatch към OCR worker (Gemini Vision)
    dispatch('ocr_extract', ['job_id' => $job_id]);
    return ['job_id' => $job_id];
}
```

UI loading state:
```html
<div class="ocr-processing">
  <span class="processing-orb">
    <svg viewBox="0 0 24 24"><!-- conic shimmer --></svg>
  </span>
  <h3>AI чете фактурата...</h3>
  <p class="processing-sub">Това отнема 5-15 секунди</p>
  <div class="processing-progress">
    <div class="progress-bar"></div>
  </div>
</div>
```

`processing-orb` = q-magic conic от P10. Progress bar е indeterminate animated.

Poll endpoint: `GET /api/ocr/scan/{job_id}` всеки 1.5s до `status='done'` или `status='failed'`.

### 15.4 СТЪПКА 3 — Confidence Routing

PHP:
```php
function routeOCRResult($result) {
    $conf = $result['confidence_avg'];
    if ($conf >= 0.92) {
        return ['action' => 'auto_accept', 'data' => $result];
    } elseif ($conf >= 0.75) {
        return ['action' => 'smart_review', 'data' => $result];
    } else {
        return ['action' => 'reject', 'reason' => 'low_confidence'];
    }
}
```

#### Confidence >92% — AUTO_ACCEPT

UI: Никаква review screen. Skip directly to Стъпка 4.
```html
<div class="ocr-auto-accept">
  <span class="autoaccept-orb">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="3">
      <polyline points="20 6 9 17 4 12"/>
    </svg>
  </span>
  <h3>AI разпозна 18 артикула</h3>
  <p class="aa-sub">Стойност: <strong>2 340.50 €</strong></p>
  <p class="aa-sub">Доставчик: <strong>Емпорио ООД</strong></p>
  <button class="aa-cta" onclick="confirmAutoAccept()">Получи доставката</button>
  <button class="aa-secondary" onclick="reviewOCRResult()">Прегледай първо</button>
</div>
```

`autoaccept-orb` = q-gain зелен. Strong цветова семантика — "всичко е наред, action".

#### Confidence 75-92% — SMART REVIEW UI

Списък на items с inline edit:
```html
<div class="ocr-review-list">
  <h3 class="rev-title">Прегледай намерените артикули</h3>
  <p class="rev-sub">AI разпозна 18 от ~20. Провери и потвърди.</p>

  <article class="ocr-item conf-high" data-item-id="1">
    <span class="item-conf">95%</span>
    <div class="item-name">Тениска синя L</div>
    <div class="item-row">
      <span class="item-qty-label">Бр:</span>
      <input class="item-qty" value="10" inputmode="numeric"/>
    </div>
    <div class="item-row">
      <span class="item-cost-label">Цена:</span>
      <input class="item-cost" value="12.50" inputmode="decimal"/>
      <span class="item-currency">€</span>
    </div>
  </article>

  <article class="ocr-item conf-mid" data-item-id="2">
    <span class="item-conf">82%</span>
    <!-- ... -->
  </article>

  <article class="ocr-item conf-low" data-item-id="3">
    <span class="item-conf">68%</span>
    <span class="item-warn">⚠ Провери</span>
    <!-- ... -->
  </article>

  <button class="ocr-add-missing" onclick="addOCRItem()">
    + Добави липсващ артикул
  </button>

  <div class="ocr-totals">
    <div class="totals-row"><span>Общо артикули:</span><strong>18</strong></div>
    <div class="totals-row"><span>Стойност:</span><strong id="ocrTotal">2 340.50 €</strong></div>
    <div class="totals-row"><span>Доставчик:</span><strong>Емпорио ООД</strong></div>
  </div>

  <div class="ocr-actions">
    <button class="action-secondary" onclick="rescanOCR()">Снимай отново</button>
    <button class="action-primary" onclick="finalizeOCR()">Получи доставката</button>
  </div>
</div>
```

CSS confidence visual:
```css
.ocr-item { padding: 12px; margin-bottom: 8px; border-radius: var(--radius); }
.ocr-item.conf-high { background: hsl(145 30% 95%); border-left: 3px solid hsl(145 70% 50%); }
.ocr-item.conf-mid { background: hsl(38 30% 95%); border-left: 3px solid hsl(38 90% 55%); }
.ocr-item.conf-low { background: hsl(0 30% 95%); border-left: 3px solid hsl(0 70% 55%); }
[data-theme="dark"] .ocr-item.conf-high { background: hsl(145 30% 8%); }
[data-theme="dark"] .ocr-item.conf-mid { background: hsl(38 30% 8%); }
[data-theme="dark"] .ocr-item.conf-low { background: hsl(0 30% 8%); }
.item-conf {
  display: inline-block; font-family: var(--font-mono);
  font-size: 9.5px; font-weight: 800; letter-spacing: 0.06em;
  padding: 2px 6px; border-radius: var(--radius-pill);
  background: white; color: var(--text);
}
.item-warn { font-size: 11px; color: hsl(0 70% 50%); font-weight: 700; }
```

#### Confidence <75% — REJECT + FALLBACK

```html
<div class="ocr-reject">
  <span class="reject-orb">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <circle cx="12" cy="12" r="10"/>
      <line x1="12" y1="8" x2="12" y2="12"/>
      <line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
  </span>
  <h3>Снимката не е достатъчно ясна</h3>
  <p class="reject-sub">AI може да сгреши. Опитай:</p>

  <button class="fallback-opt" onclick="rescanOCR()">
    📷 Снимай отново (по-светло)
  </button>
  <button class="fallback-opt" onclick="startVoiceDelivery()">
    🎤 Кажи какво има
  </button>
  <button class="fallback-opt" onclick="startManualScan()">
    ⚡ Скенирай артикулите
  </button>
</div>
```

`reject-orb` = q-loss червен. Никога не "изхвърля" Пешо без следваща стъпка.

### 15.5 СТЪПКА 4 — Supplier Match

OCR извлича supplier name. PHP fuzzy match:
```php
function matchSupplier($extracted_name, $tenant_id) {
    $candidates = DB::get("
        SELECT id, name, similarity_score(?, name) AS score
        FROM suppliers
        WHERE tenant_id = ? AND is_active = 1
        HAVING score > 0.7
        ORDER BY score DESC
        LIMIT 3
    ", [$extracted_name, $tenant_id]);

    if (count($candidates) === 1 && $candidates[0]['score'] > 0.92) {
        return ['action' => 'auto_match', 'supplier' => $candidates[0]];
    }
    if (count($candidates) === 0) {
        return ['action' => 'create_new', 'name' => $extracted_name];
    }
    return ['action' => 'choose', 'options' => $candidates];
}
```

UI ако ambiguity:
```html
<div class="supplier-match">
  <h3>Кой е доставчикът?</h3>
  <p class="match-sub">AI намери "Емпорио" — кое е?</p>
  <button class="match-opt selected">Емпорио ООД (95% match)</button>
  <button class="match-opt">Емпорио Текстил (82% match)</button>
  <button class="match-opt new">+ Нов доставчик: "Емпорио"</button>
</div>
```

### 15.6 СТЪПКА 5 — Order Match (auto-link)

`findMatchingOrder()` от Част 1 §4.2. Резултат:

| Случай | UI |
|---|---|
| Точно 1 match | Skip — auto-link |
| 0 matches | Прескача — създава manual delivery; покажда CTA "Поръчай липси" в детайл |
| 2+ matches | Питане — дисамбигуация sheet |

```html
<div class="order-match">
  <h3>Срещу коя поръчка е?</h3>
  <button class="match-order">
    <strong>#789 от 28.04.2026</strong>
    <span>20 артикула · 2 850 €</span>
  </button>
  <button class="match-order">
    <strong>#802 от 02.05.2026</strong>
    <span>15 артикула · 1 720 €</span>
  </button>
  <button class="match-order new">Без поръчка (manual delivery)</button>
</div>
```

### 15.7 СТЪПКА 6 — Finalize

PHP:
```php
function finalizeDelivery($delivery_id) {
    DB::transaction(function() use ($delivery_id) {
        $delivery = DB::get("SELECT * FROM deliveries WHERE id = ?", [$delivery_id]);
        $items = DB::all("SELECT * FROM delivery_items WHERE delivery_id = ?", [$delivery_id]);

        foreach ($items as $item) {
            // 1. Update inventory
            DB::run("INSERT INTO inventory (product_id, store_id, quantity, tenant_id)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)",
                [$item['product_id'], $delivery['target_store_id'], $item['delivered_qty'], $delivery['tenant_id']]);

            // 2. Update cost (if changed)
            if ($item['unit_cost'] != $item['previous_cost']) {
                DB::run("UPDATE products SET cost_price = ? WHERE id = ?",
                    [$item['unit_cost'], $item['product_id']]);
                DB::run("INSERT INTO cost_history (product_id, cost_old, cost_new, supplier_id, delivery_id, changed_at)
                         VALUES (?, ?, ?, ?, ?, NOW())",
                    [$item['product_id'], $item['previous_cost'], $item['unit_cost'],
                     $delivery['supplier_id'], $delivery_id]);
            }

            // 3. Close lost_demand
            DB::run("UPDATE lost_demand SET resolved_at = NOW(), resolved_via_delivery_id = ?
                     WHERE product_id = ? AND resolved_at IS NULL",
                [$delivery_id, $item['product_id']]);
        }

        // 4. Update delivery status
        DB::run("UPDATE deliveries SET status='received', received_at=NOW() WHERE id=?",
            [$delivery_id]);

        // 5. Update order status (if linked)
        if ($delivery['order_id']) {
            $is_partial = checkPartialDelivery($delivery['order_id'], $delivery_id);
            DB::run("UPDATE supplier_orders SET status=? WHERE id=?",
                [$is_partial ? 'partial' : 'received', $delivery['order_id']]);
        }

        // 6. Update supplier stats
        DB::run("UPDATE suppliers SET
                 last_delivery_date = NOW(),
                 total_deliveries = total_deliveries + 1,
                 total_value = total_value + ?
                 WHERE id = ?",
            [$delivery['total_value'], $delivery['supplier_id']]);

        // 7. Audit
        DB::run("INSERT INTO delivery_events (delivery_id, event_type, user_id, created_at, payload)
                 VALUES (?, 'finalized', ?, NOW(), ?)",
            [$delivery_id, $_SESSION['user_id'], json_encode($delivery)]);

        // 8. Hidden Inventory boost +0.40 confidence
        boostInventoryConfidence($delivery['target_store_id'],
            array_column($items, 'product_id'), 0.40);

        // 9. Trigger label print (auto pop-up)
        triggerLabelPrintBatch($delivery_id);
    });
}
```

UI success:
```html
<div class="ocr-success">
  <span class="success-orb">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="3">
      <polyline points="20 6 9 17 4 12"/>
    </svg>
  </span>
  <h3>Готово! 18 артикула получени</h3>
  <p class="success-sub">Стойност: 2 340.50 €</p>
  <p class="success-sub">Lost demand затворен: <strong>3 артикула, €420</strong></p>

  <button class="success-cta" onclick="printLabels()">
    🖨 Печатай етикети (18 бр)
  </button>
  <button class="success-secondary" onclick="goBack()">
    Готово
  </button>
</div>
```

`success-orb` = q-gain. Auto pop-up печат labels.

### 15.8 OCR — Multi-store split

Ако ENI има 5 магазина и доставката трябва да се раздели:

```html
<div class="multistore-split">
  <h3>Раздели по магазини?</h3>
  <p class="split-sub">AI препоръка: на база на история на продажбите</p>

  <div class="split-row" data-store-id="1">
    <span class="store-name">Магазин Витоша</span>
    <input class="store-qty" value="6" inputmode="numeric"/>
    <span class="store-percent">33%</span>
  </div>
  <div class="split-row" data-store-id="2">
    <span class="store-name">Магазин Цариградско</span>
    <input class="store-qty" value="4" inputmode="numeric"/>
    <span class="store-percent">22%</span>
  </div>
  <!-- ... 5 магазина общо -->

  <div class="split-totals">
    Общо: <strong>18 / 18</strong>
    <span class="split-balance ok">✓ Балансирано</span>
  </div>

  <button class="split-cta">Запази split</button>
  <button class="split-secondary">Цялата доставка в текущия магазин</button>
</div>
```

AI препоръка идва от:
```sql
SELECT store_id, SUM(qty) AS sold_30d
FROM sale_items si JOIN sales s ON si.sale_id = s.id
WHERE si.product_id IN (...)
  AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY store_id;
```

Proportional split. Митко може override per-store.

---

## 16. VOICE FLOW (КАЖИ КАКВО ИМА)

### 16.1 Voice overlay (S56 стандарт от P13 + STT LOCKED commits 4222a66+1b80106)

```html
<div class="voice-rec-ov" id="voiceRecOv">
  <div class="rec-conic-bg"></div>
  <div class="rec-ring outer"></div>
  <div class="rec-ring middle"></div>
  <div class="rec-ring inner"></div>
  <button class="rec-mic-btn" onclick="stopVoiceRec()">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <rect x="9" y="2" width="6" height="12" rx="3"/>
      <path d="M5 10v2a7 7 0 0014 0v-2"/>
    </svg>
  </button>
  <div class="rec-status">
    <span class="rec-time">00:03</span>
    <span class="rec-hint">Слушам...</span>
  </div>
  <div class="rec-transcript" id="voiceTranscript">
    <span class="transcript-soft">"тениска синя L десет броя по дванайсет лв..."</span>
  </div>
  <button class="rec-cancel" onclick="cancelVoiceRec()">Отказ</button>
</div>
```

### 16.2 CSS (от P13 voice overlay)

```css
.voice-rec-ov {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.85); backdrop-filter: blur(20px);
  z-index: 200; display: grid; place-items: center;
  opacity: 0; pointer-events: none;
  transition: opacity 0.32s var(--ease);
}
.voice-rec-ov.active { opacity: 1; pointer-events: auto; }

.rec-conic-bg {
  position: absolute; inset: -50%;
  background: conic-gradient(from 0deg,
    transparent 0%, hsl(var(--hue1) 80% 50% / 0.3) 30%,
    transparent 60%, hsl(var(--hue2) 80% 50% / 0.3) 90%, transparent 100%);
  animation: conicSpin 6s linear infinite;
  filter: blur(40px);
}

.rec-ring {
  position: absolute; border-radius: 50%;
  border: 2px solid hsl(var(--hue1) 80% 70% / 0.3);
  animation: ringPulse 2s ease-out infinite;
}
.rec-ring.outer { width: 240px; height: 240px; animation-delay: 0s; }
.rec-ring.middle { width: 200px; height: 200px; animation-delay: 0.3s; }
.rec-ring.inner { width: 160px; height: 160px; animation-delay: 0.6s; }

@keyframes ringPulse {
  0% { transform: scale(0.95); opacity: 0.8; }
  100% { transform: scale(1.15); opacity: 0; }
}

.rec-mic-btn {
  width: 96px; height: 96px; border-radius: 50%;
  background: linear-gradient(135deg, hsl(var(--hue1) 80% 55%), hsl(var(--hue2) 80% 55%));
  box-shadow: 0 8px 32px hsl(var(--hue1) 80% 50% / 0.5);
  display: grid; place-items: center;
  z-index: 5; cursor: pointer;
}
.rec-mic-btn svg { width: 36px; height: 36px; }

.rec-status {
  position: absolute; bottom: 30%;
  text-align: center; color: white;
}
.rec-time {
  display: block; font-family: var(--font-mono);
  font-size: 14px; font-weight: 800; letter-spacing: 0.08em;
  opacity: 0.7;
}
.rec-hint {
  display: block; font-size: 13px; font-weight: 700;
  margin-top: 4px; opacity: 0.9;
}

.rec-transcript {
  position: absolute; bottom: 18%; left: 5%; right: 5%;
  text-align: center; color: white;
  font-size: 16px; line-height: 1.5;
  min-height: 80px;
}
.transcript-soft { opacity: 0.6; font-style: italic; }
.transcript-final { opacity: 1; font-weight: 600; }

.rec-cancel {
  position: absolute; bottom: calc(40px + env(safe-area-inset-bottom, 0));
  left: 50%; transform: translateX(-50%);
  background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
  color: white; padding: 10px 24px; border-radius: var(--radius-pill);
  font-size: 13px; font-weight: 700;
}
```

### 16.3 Voice flow (4 фази)

```
ФАЗА 1: Tap "Кажи какво има" → overlay се вдига → mic active
ФАЗА 2: Пешо говори → STT engine choice
        ├─ BG език + цена/число → Web Speech API (instant, browser-native)
        └─ Не-BG или сложно → Whisper Groq (tier 2 cloud)
ФАЗА 3: Stop button или silence 2s → finalize
ФАЗА 4: AI parse → list items → confirm screen
```

PHP parse endpoint:
```php
// /api/voice/parse
function parseVoiceDelivery($transcript, $tenant_id) {
    // System prompt: "Ти получаваш voice transcript на български
    //  за доставка. Извлечи: артикули (име, бройка, цена), доставчик."
    $resp = callGemini($transcript, $tenant_id, 'voice_delivery');

    return [
        'items' => $resp['items'],
        'supplier_hint' => $resp['supplier'] ?? null,
        'total_value' => array_sum(array_column($resp['items'], 'subtotal')),
        'confidence' => $resp['confidence']
    ];
}
```

### 16.4 Voice confirm screen

Същата `ocr-review-list` структура но със source="voice". Няма confidence per item — има общ confidence.

### 16.5 STT engine choice — LOCKED rules (commits 4222a66 + 1b80106)

**`_wizPriceParse()` parser в products.php — НЕ ПИПАЙ:**
- Cyrillic-aware boundaries
- Phonetic synonyms ("дванайсет" = "12", "лв" = "лева" = "BGN")
- Number → digit normalize
- BG price → Web Speech API path (instant)
- Non-BG price или сложна транскрипция → Whisper Groq

### 16.6 Voice fallback (ако STT fail)

```html
<div class="voice-fail">
  <h3>Не разбрах добре</h3>
  <button onclick="startVoiceRec()">Опитай отново</button>
  <button onclick="startManualScan()">Скенирай по-добре</button>
  <button onclick="startOCR()">Снимай документа</button>
</div>
```

### 16.7 Voice бутон на всяко поле (Закон №1)

В Manual flow (Част 3) и в bulk операции (Част 5) всяко input поле има inline `[🎤]` бутон. Tap → voice → fill that field. Не overlay — inline drawer:

```html
<div class="input-shell">
  <input type="text" value="..." />
  <button class="fbtn voice" onclick="voiceFillField(this)">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <rect x="9" y="2" width="6" height="12" rx="3"/>
      <path d="M5 10v2a7 7 0 0014 0v-2"/>
    </svg>
  </button>
</div>
```

### 16.8 Voice continuous команди (post-beta)

Wake-word "Хей AI" → continuous listen. Команди:
- "Доставка готова" → finalize
- "Добави още" → next item
- "Отмени последния" → undo

---

## КРАЙ НА ЧАСТ 2B

# ═══════════════════════════════════════
# ЧАСТ 3A — ЛЕСЕН РЕЖИМ: MANUAL FLOW
# ═══════════════════════════════════════

## 17. MANUAL FLOW (СКЕНИРАЙ АРТИКУЛИ)

### 17.1 Стандарт (Lightspeed/Microinvest pattern)

Това е **fallback flow** когато OCR не работи и Voice не е удобен. Емулира индустриалния стандарт от Lightspeed Retail и Microinvest за получаване на стока.

**Принцип:**
- Continuous scan — Пешо държи скенера, скенира един след друг
- Камерата стои отворена (НИКОГА native scanner overlay)
- Beep + green flash при успешен scan
- Inline list расте отдолу
- Auto-quantity 1, +/− stepper за корекция
- Voice бутон за бройка (>10 е тромаво да тропаш)

### 17.2 4 пътя на добавяне

| Path | Когаabra | UI |
|---|---|---|
| **1. Pre-fill** | Срещу sent поръчка | Items вече в списъка с `expected_qty`; Пешо потвърждава с +/− |
| **2. Continuous scan** | Без поръчка | Empty list; всеки scan добавя нов ред |
| **3. Typeahead search** | Артикул няма barcode | Search bar → typeahead → tap → добави |
| **4. Top-20 quick add** | Често поръчвани | Bottom drawer с frequently-ordered → tap → +1 |

### 17.3 Главен екран на Manual flow

```html
<div class="manual-flow-view">
  <!-- Camera = header (live video background, 80px) -->
  <header class="manual-cam-header">
    <video id="scanCam" autoplay playsinline></video>
    <div class="cam-overlay">
      <div class="laser-line"></div>
      <span class="cam-corner tl"></span><span class="cam-corner tr"></span>
      <span class="cam-corner bl"></span><span class="cam-corner br"></span>
    </div>
    <div class="cam-controls">
      <button class="cam-back" onclick="exitManual()">
        <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
      </button>
      <span class="cam-status">
        <span class="status-dot"></span>
        Скенер активен
      </span>
      <button class="cam-flash" onclick="toggleFlash()">
        <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
          <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
        </svg>
      </button>
    </div>
  </header>

  <!-- Supplier banner (above list) -->
  <div class="manual-supplier-bar">
    <span class="supplier-icon">
      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
        <path d="M9 11l3 3L22 4"/>
        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
      </svg>
    </span>
    <span class="supplier-name" onclick="openSupplierPicker()">Емпорио ООД</span>
    <button class="supplier-change">Смени</button>
  </div>

  <!-- Items list -->
  <div class="manual-items-list" id="itemsList">
    <article class="manual-item" data-product-id="123">
      <div class="m-item-row">
        <span class="m-item-name">Тениска синя L</span>
        <button class="m-item-remove" onclick="removeItem(this)">
          <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
      <div class="m-item-row">
        <div class="qty-stepper">
          <button class="qty-btn minus" onclick="adjustQty(this, -1)">−</button>
          <input class="qty-input" value="3" inputmode="numeric"/>
          <button class="qty-btn plus" onclick="adjustQty(this, 1)">+</button>
        </div>
        <button class="m-voice-btn" onclick="voiceQty(this)">
          <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
            <rect x="9" y="2" width="6" height="12" rx="3"/>
            <path d="M5 10v2a7 7 0 0014 0v-2"/>
          </svg>
        </button>
        <span class="m-item-cost">12.50 €</span>
      </div>
    </article>
    <!-- ... повече items -->
  </div>

  <!-- Quick actions strip -->
  <div class="manual-quick-strip">
    <button class="quick-btn" onclick="openTypeahead()">
      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
        <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      Търси
    </button>
    <button class="quick-btn" onclick="openTop20()">
      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
      </svg>
      Често
    </button>
    <button class="quick-btn" onclick="openManualNew()">
      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Нов
    </button>
  </div>

  <!-- Sticky footer total + finalize -->
  <div class="manual-footer">
    <div class="footer-totals">
      <span class="t-label">Общо</span>
      <strong class="t-count">14 артикула</strong>
      <strong class="t-value">1 240.50 €</strong>
    </div>
    <button class="manual-finalize" onclick="finalizeManual()">
      Получи доставката
    </button>
  </div>
</div>
```

### 17.4 CSS — Camera header (live video)

```css
.manual-cam-header {
  position: sticky; top: 0; z-index: 50;
  height: 80px; overflow: hidden;
  background: #000;
}
.manual-cam-header video {
  position: absolute; inset: 0;
  width: 100%; height: 100%; object-fit: cover;
  filter: brightness(0.7);
}
.cam-overlay { position: absolute; inset: 0; }
.laser-line {
  position: absolute; left: 12%; right: 12%; top: 50%;
  height: 2px;
  background: linear-gradient(90deg,
    transparent, hsl(0 80% 60%), transparent);
  box-shadow: 0 0 8px hsl(0 80% 60%);
  animation: laserPulse 1.5s ease-in-out infinite;
}
@keyframes laserPulse {
  0%, 100% { opacity: 0.4; transform: scaleX(0.95); }
  50%      { opacity: 1; transform: scaleX(1); }
}
.cam-corner {
  position: absolute; width: 18px; height: 18px;
  border: 3px solid white;
}
.cam-corner.tl { top: 12px; left: 12%; border-right: 0; border-bottom: 0; }
.cam-corner.tr { top: 12px; right: 12%; border-left: 0; border-bottom: 0; }
.cam-corner.bl { bottom: 12px; left: 12%; border-right: 0; border-top: 0; }
.cam-corner.br { bottom: 12px; right: 12%; border-left: 0; border-top: 0; }

.cam-controls {
  position: relative; z-index: 5;
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 14px;
}
.cam-back, .cam-flash {
  width: 32px; height: 32px; border-radius: 50%;
  background: rgba(0,0,0,0.4); backdrop-filter: blur(8px);
  display: grid; place-items: center;
}
.cam-back svg, .cam-flash svg { width: 14px; height: 14px; }

.cam-status {
  font-family: var(--font-mono);
  font-size: 9.5px; font-weight: 800; letter-spacing: 0.06em;
  text-transform: uppercase; color: white;
  background: rgba(0,0,0,0.5); backdrop-filter: blur(8px);
  padding: 4px 10px; border-radius: var(--radius-pill);
  display: inline-flex; align-items: center; gap: 6px;
}
.status-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: hsl(145 70% 50%);
  animation: pulse 1.5s ease-in-out infinite;
  box-shadow: 0 0 4px hsl(145 70% 50%);
}
```

### 17.5 CSS — Supplier banner

```css
.manual-supplier-bar {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px;
  background: var(--surface);
  border-bottom: 1px solid var(--border-soft);
  position: sticky; top: 80px; z-index: 49;
}
[data-theme="dark"] .manual-supplier-bar {
  background: hsl(220 25% 6% / 0.85); backdrop-filter: blur(8px);
}
.supplier-icon {
  width: 28px; height: 28px; border-radius: var(--radius-icon);
  display: grid; place-items: center;
  background: linear-gradient(135deg,
    hsl(145 70% 50%), hsl(155 70% 40%));
}
.supplier-icon svg { width: 14px; height: 14px; stroke: white; stroke-width: 2.5; }
.supplier-name {
  flex: 1; font-size: 14px; font-weight: 800;
  letter-spacing: -0.01em; color: var(--text);
}
.supplier-change {
  font-size: 11px; font-weight: 700;
  color: var(--accent); padding: 4px 8px;
}
[data-theme="dark"] .supplier-change { color: hsl(var(--hue1) 80% 75%); }
```

### 17.6 CSS — Manual item card (със qty-stepper)

```css
.manual-item {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-card-sm);
  padding: 10px 12px; margin-bottom: 8px;
  animation: scanFlash 0.5s ease-out;
}
@keyframes scanFlash {
  0%   { background: hsl(145 70% 90%); transform: scale(0.98); }
  50%  { transform: scale(1.02); }
  100% { background: var(--surface); transform: scale(1); }
}
[data-theme="dark"] @keyframes scanFlash {
  0%   { background: hsl(145 70% 12%); transform: scale(0.98); }
  100% { background: hsl(220 25% 6% / 0.7); transform: scale(1); }
}
[data-theme="dark"] .manual-item {
  background: hsl(220 25% 6% / 0.7); backdrop-filter: blur(8px);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}

.m-item-row {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 6px;
}
.m-item-row:last-child { margin-bottom: 0; }
.m-item-name {
  flex: 1; font-size: 13px; font-weight: 700;
  color: var(--text); min-width: 0;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.m-item-remove {
  width: 24px; height: 24px; border-radius: 50%;
  display: grid; place-items: center;
  background: hsl(0 50% 95%); flex-shrink: 0;
}
[data-theme="dark"] .m-item-remove { background: hsl(0 50% 12%); }
.m-item-remove svg { width: 11px; height: 11px; stroke: hsl(0 70% 50%); }

/* qty-stepper от P13 */
.qty-stepper {
  display: flex; align-items: center; flex: 1;
  background: var(--bg-main); border-radius: var(--radius-pill);
  box-shadow: var(--shadow-pressed);
  height: 36px;
}
.qty-btn {
  width: 36px; height: 36px;
  display: grid; place-items: center;
  font-size: 16px; font-weight: 800; color: var(--text);
}
.qty-btn:active { color: var(--accent); }
.qty-input {
  flex: 1; text-align: center;
  font-size: 14px; font-weight: 800;
  background: transparent; border: 0;
}

.m-voice-btn {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--surface); box-shadow: var(--shadow-card-sm);
  display: grid; place-items: center; flex-shrink: 0;
}
.m-voice-btn svg { width: 13px; height: 13px; }

.m-item-cost {
  font-family: var(--font-mono); font-size: 12px; font-weight: 800;
  color: var(--text); flex-shrink: 0;
  min-width: 60px; text-align: right;
}
```

### 17.7 CSS — Quick strip + footer

```css
.manual-quick-strip {
  display: flex; gap: 8px;
  padding: 10px 12px;
  position: sticky; bottom: 86px; z-index: 5;
  background: var(--bg-main);
}
.quick-btn {
  flex: 1; height: 44px;
  background: var(--surface); box-shadow: var(--shadow-card-sm);
  border-radius: var(--radius-pill);
  display: flex; align-items: center; justify-content: center; gap: 6px;
  font-size: 12px; font-weight: 700;
  color: var(--text);
}
.quick-btn svg { width: 14px; height: 14px; }
.quick-btn:active { box-shadow: var(--shadow-pressed); }

.manual-footer {
  position: sticky; bottom: 0;
  padding: 12px 14px calc(12px + env(safe-area-inset-bottom, 0));
  background: var(--bg-main);
  border-top: 1px solid var(--border-soft);
  display: grid; gap: 10px;
}
[data-theme="dark"] .manual-footer {
  background: hsl(220 25% 4.8% / 0.95);
  backdrop-filter: blur(20px);
  border-top-color: hsl(var(--hue2) 12% 18%);
}
.footer-totals {
  display: flex; align-items: center; gap: 12px;
  padding: 0 4px;
}
.t-label {
  font-family: var(--font-mono); font-size: 9.5px;
  font-weight: 700; letter-spacing: 0.06em;
  text-transform: uppercase; color: var(--text-muted);
}
.t-count { font-size: 13px; font-weight: 800; color: var(--text); }
.t-value {
  margin-left: auto;
  font-size: 16px; font-weight: 800; color: var(--accent);
}
[data-theme="dark"] .t-value { color: hsl(var(--hue1) 80% 75%); }

.manual-finalize {
  width: 100%; height: 52px;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 6px 20px hsl(var(--hue1) 80% 50% / 0.4);
  color: white; border-radius: var(--radius);
  font-size: 15px; font-weight: 800; letter-spacing: -0.01em;
  position: relative; overflow: hidden;
}
.manual-finalize::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg,
    transparent 70%, rgba(255,255,255,0.3) 85%, transparent 100%);
  animation: conicSpin 5s linear infinite;
}
.manual-finalize:active { transform: scale(0.99); }
```

### 17.8 Path 1 — Pre-fill срещу поръчка

Когато entry от order_id=789:

```javascript
async function loadOrderItems(orderId) {
  const order = await fetch(`/api/orders/${orderId}`).then(r => r.json());
  order.items.forEach(item => {
    addItemToList({
      product_id: item.product_id,
      name: item.name,
      expected_qty: item.qty,
      delivered_qty: item.qty,  // pre-filled, Пешо потвърждава с +/−
      unit_cost: item.unit_cost,
      source: 'pre-fill'
    });
  });
}
```

UI: Items появяват се с `expected_qty` бадж до тях:
```html
<article class="manual-item pre-fill" data-expected="10">
  <span class="expected-pill">Очаквани: 10</span>
  <!-- ... -->
</article>
```

CSS:
```css
.manual-item.pre-fill {
  border-left: 3px solid hsl(var(--hue1) 80% 50%);
}
.expected-pill {
  font-family: var(--font-mono); font-size: 9px;
  font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
  background: hsl(var(--hue1) 30% 92%); color: var(--accent);
  padding: 2px 6px; border-radius: var(--radius-pill);
}
[data-theme="dark"] .expected-pill {
  background: hsl(var(--hue1) 30% 14%);
  color: hsl(var(--hue1) 80% 75%);
}
```

При finalize PHP проверка: ако `delivered_qty != expected_qty` → mark `is_partial=1` за тази позиция → AI signal в детайл "5 от 10 очаквани".

### 17.9 Path 2 — Continuous scan

Camera е винаги отворена в header (80px). Логика:

```javascript
let lastScannedCode = null;
const scannerCooldown = 1500; // ms между два scan-а на същия код

barcodeDetector.onScan = (code) => {
  const now = Date.now();
  if (code === lastScannedCode && now - lastScanTime < scannerCooldown) {
    return; // ignore double-scan
  }
  lastScannedCode = code;
  lastScanTime = now;

  // Beep + green flash
  playBeep();
  flashCameraGreen();

  // Try to find product
  fetch(`/api/products/by-code?code=${code}`)
    .then(r => r.json())
    .then(product => {
      if (product) {
        addItemToList({
          product_id: product.id,
          name: product.name,
          delivered_qty: 1,
          unit_cost: product.cost_price,
          source: 'scan'
        });
      } else {
        // Unknown barcode → mini-wizard for new product
        openMiniWizardForUnknownCode(code);
      }
    });
};
```

Beep + flash:
```css
@keyframes camFlashGreen {
  0% { background: rgba(0,0,0,0); }
  20% { background: rgba(72, 200, 100, 0.4); }
  100% { background: rgba(0,0,0,0); }
}
.cam-overlay.flash-green { animation: camFlashGreen 0.4s ease-out; }
```

```javascript
function flashCameraGreen() {
  const overlay = document.querySelector('.cam-overlay');
  overlay.classList.add('flash-green');
  setTimeout(() => overlay.classList.remove('flash-green'), 400);
}
function playBeep() {
  // 880Hz, 80ms, sine
  const ctx = new AudioContext();
  const osc = ctx.createOscillator();
  osc.frequency.value = 880;
  osc.connect(ctx.destination);
  osc.start();
  setTimeout(() => osc.stop(), 80);
}
```

### 17.10 Path 3 — Typeahead търсене

Когато артикул няма barcode (или баркодът е счупен/мрънкан):

```html
<div class="typeahead-overlay">
  <input class="ta-input" placeholder="Търси артикул..." autofocus />
  <div class="ta-results">
    <button class="ta-result" data-product-id="123">
      <span class="ta-name">Тениска синя L</span>
      <span class="ta-meta">SKU: ABC123 · 12.50€</span>
    </button>
    <!-- ... -->
  </div>
</div>
```

PHP:
```php
// /api/products/search?q=тениска&limit=10
function searchProducts($query, $tenant_id) {
    return DB::all("
        SELECT id, code, name, cost_price, retail_price, image_url
        FROM products
        WHERE tenant_id = ?
          AND is_active = 1
          AND (name LIKE ? OR code LIKE ?)
        ORDER BY
            CASE WHEN code = ? THEN 0
                 WHEN code LIKE ? THEN 1
                 WHEN name LIKE ? THEN 2
                 ELSE 3 END,
            name
        LIMIT 10
    ", [$tenant_id, "%$query%", "%$query%", $query, "$query%", "$query%"]);
}
```

Debounce: 250ms. Min 2 символа.

### 17.11 Path 4 — Top-20 quick add

Често поръчвани от този доставчик. Bottom drawer:

```html
<div class="top20-drawer">
  <h3>Често поръчвани от Емпорио</h3>
  <div class="top20-grid">
    <button class="top20-item" data-product-id="123">
      <img src="..." class="t20-thumb"/>
      <span class="t20-name">Тениска синя L</span>
      <span class="t20-pill">+1</span>
    </button>
    <!-- 20 items max в grid 2 колони -->
  </div>
</div>
```

PHP:
```sql
SELECT p.id, p.name, p.image_url, p.code,
       SUM(di.delivered_qty) AS total_delivered_90d
FROM delivery_items di
JOIN deliveries d ON di.delivery_id = d.id
JOIN products p ON di.product_id = p.id
WHERE d.supplier_id = ?
  AND d.tenant_id = ?
  AND d.received_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY p.id
ORDER BY total_delivered_90d DESC
LIMIT 20;
```

### 17.12 Какво ВЪВЕЖДА Пешо ръчно (минимум)

| Поле | Default | Override |
|---|---|---|
| Доставчик | AI auto-suggest | Smart picker (списък 5 most-recent) |
| Артикул | Scan / search / pre-fill / top-20 | manual |
| Бройка | 1 (continuous scan) или expected (pre-fill) | +/− stepper или voice |
| Cost | products.cost_price (last) | input override (Митко only) |
| Notes | "" | Voice → optional |

Пешо НЕ въвежда: дата (auto NOW), магазин (current store), номер на доставка (auto), VAT (auto от supplier defaults).

### 17.13 Empty state

Първо отваряне без scan:

```html
<div class="manual-empty">
  <span class="empty-orb">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <path d="M3 7V5a2 2 0 012-2h2"/><path d="M17 3h2a2 2 0 012 2v2"/>
      <line x1="7" y1="12" x2="17" y2="12"/>
    </svg>
  </span>
  <h3>Започни да скенираш</h3>
  <p>Камерата е активна. Дръж барода на 10-15см.</p>
  <button class="empty-cta" onclick="openTypeahead()">
    Или потърси артикул →
  </button>
</div>
```

`empty-orb` = q-default conic.

### 17.14 Long-press на item (post-beta)

Long-press на manual-item → bulk select mode (виж Част 5).

---

## КРАЙ НА ЧАСТ 3A

# ═══════════════════════════════════════
# ЧАСТ 3B — ИМПОРТ + MINI-WIZARD + PRICING
# ═══════════════════════════════════════

## 18. ИМПОРТ FLOW (ИМПОРТ ФАЙЛ)

### 18.1 Use cases в ENI

| % | Случай |
|---|---|
| 60% | Доставчик праща PDF/Excel email → Митко forward-ва на специален tenant email |
| 25% | Митко download-ва файл от доставчиков портал → upload през UI |
| 10% | Имейлcommerce-доставчик с XML/EDI feed (post-beta) |
| 5% | CSV export от стара ERP система (миграция) |

### 18.2 Flow (от PRODUCTS_DESIGN §5 CSV Import + email forward)

```
ENTRY 1: Tap "Импорт файл" в receive sheet
   ↓
ENTRY 2: Email forward → auto detected
   ↓
File picker / Auto upload
   ↓
PHP detect format (PDF/CSV/Excel/XML)
   ↓
Parse → preview screen
   ↓
Column/field mapping (ако CSV)
   ↓
Confidence Routing (същото като OCR)
   ↓
Smart Review UI или Auto-accept
   ↓
Finalize (същия PHP transaction)
```

### 18.3 ENTRY 1 — File picker UI

```html
<div class="import-flow">
  <h3 class="imp-title">Качи файл с доставка</h3>

  <button class="imp-dropzone" onclick="pickFile()">
    <span class="dz-orb">
      <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
        <polyline points="17 8 12 3 7 8"/>
        <line x1="12" y1="3" x2="12" y2="15"/>
      </svg>
    </span>
    <strong class="dz-title">Избери файл</strong>
    <span class="dz-sub">PDF · CSV · Excel · XML</span>
  </button>

  <div class="imp-divider">или</div>

  <div class="imp-email-card">
    <span class="email-orb">
      <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
        <path d="M4 4h16c1 0 2 1 2 2v12c0 1-1 2-2 2H4c-1 0-2-1-2-2V6c0-1 1-2 2-2z"/>
        <polyline points="22 6 12 13 2 6"/>
      </svg>
    </span>
    <div class="email-text">
      <strong>Праща директно от мейл:</strong>
      <span class="email-addr">eni-deliveries@runmystore.ai</span>
      <button class="email-copy" onclick="copyToClipboard(this)">Копирай</button>
    </div>
  </div>

  <button class="imp-cancel" onclick="closeImport()">Отказ</button>
</div>
```

### 18.4 CSS — Import UI

```css
.import-flow {
  padding: 16px;
  display: grid; gap: 14px;
}
.imp-title {
  font-size: 17px; font-weight: 800; letter-spacing: -0.01em;
}

.imp-dropzone {
  display: grid; place-items: center; gap: 10px;
  width: 100%; min-height: 140px;
  padding: 24px 20px;
  border: 2px dashed var(--border-soft);
  border-radius: var(--radius);
  background: var(--surface);
  transition: border-color 0.2s var(--ease);
}
[data-theme="dark"] .imp-dropzone {
  background: hsl(220 25% 6% / 0.5);
  border-color: hsl(var(--hue2) 12% 22%);
}
.imp-dropzone:active {
  border-color: var(--accent);
  background: hsl(var(--hue1) 30% 95%);
}
[data-theme="dark"] .imp-dropzone:active {
  background: hsl(var(--hue1) 30% 12%);
}
.dz-orb {
  width: 56px; height: 56px; border-radius: var(--radius);
  background: linear-gradient(135deg,
    hsl(var(--hue1) 80% 55%), hsl(var(--hue2) 80% 55%));
  display: grid; place-items: center;
  box-shadow: 0 6px 18px hsl(var(--hue1) 80% 50% / 0.4);
}
.dz-orb svg { width: 24px; height: 24px; }
.dz-title { font-size: 14px; font-weight: 800; color: var(--text); }
.dz-sub {
  font-family: var(--font-mono); font-size: 9.5px; font-weight: 700;
  letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--text-muted);
}

.imp-divider {
  text-align: center; font-size: 11px; color: var(--text-faint);
  position: relative;
}
.imp-divider::before, .imp-divider::after {
  content: ''; position: absolute; top: 50%;
  width: 35%; height: 1px; background: var(--border-soft);
}
.imp-divider::before { left: 0; }
.imp-divider::after  { right: 0; }

.imp-email-card {
  display: flex; gap: 12px; align-items: center;
  padding: 14px;
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-card);
}
[data-theme="dark"] .imp-email-card {
  background: hsl(220 25% 6% / 0.7);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}
.email-orb {
  width: 40px; height: 40px; border-radius: var(--radius-icon);
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  display: grid; place-items: center; flex-shrink: 0;
  box-shadow: 0 4px 12px hsl(280 70% 50% / 0.4);
}
.email-orb svg { width: 18px; height: 18px; }
.email-text { flex: 1; min-width: 0; }
.email-text strong {
  display: block; font-size: 12px; font-weight: 700;
  color: var(--text); margin-bottom: 4px;
}
.email-addr {
  display: block; font-family: var(--font-mono);
  font-size: 11px; font-weight: 800; color: var(--accent);
  word-break: break-all;
}
[data-theme="dark"] .email-addr { color: hsl(var(--hue1) 80% 75%); }
.email-copy {
  font-size: 11px; font-weight: 700; color: var(--accent);
  padding: 6px 10px; margin-top: 6px;
  background: var(--bg-main); border-radius: var(--radius-pill);
  box-shadow: var(--shadow-pressed);
}
```

### 18.5 ENTRY 2 — Email forward автоматичен detection

PHP backend:
```php
// Cron 5 мин: проверка inbox eni-deliveries@runmystore.ai
function pollEmailInbox($tenant_id) {
    $emails = imapFetchUnread($tenant_id);
    foreach ($emails as $email) {
        // Идентифицирай supplier от sender domain
        $supplier = matchSupplierByEmail($email->from, $tenant_id);

        // Извлечи attachments (PDF/Excel/CSV/XML)
        foreach ($email->attachments as $att) {
            $job_id = uuid();
            DB::run("INSERT INTO scanner_documents (
                id, tenant_id, source, file_path, supplier_id,
                email_subject, email_from, status, created_at
            ) VALUES (?, ?, 'email', ?, ?, ?, ?, 'processing', NOW())",
                [$job_id, $tenant_id, $att->path, $supplier?->id ?? null,
                 $email->subject, $email->from]);

            dispatch('parse_attachment', ['job_id' => $job_id]);
        }

        // AI signal: "Нова доставка от Емпорио (email)"
        createAISignal($tenant_id, 'deliv_email_received', [
            'supplier' => $supplier?->name,
            'subject' => $email->subject,
            'job_id' => $job_id
        ]);
    }
}
```

UI ефект: AI карта в Life Board:
```html
<article class="deliv-ai-signal q-magic">
  <div class="signal-head">
    <span class="signal-orb"><!-- email icon --></span>
    <div class="signal-text">
      <div class="signal-title">Нова доставка от email</div>
      <div class="signal-sub">Емпорио ООД · 14:23</div>
    </div>
  </div>
  <div class="signal-body">
    AI извлече <strong>18 артикула</strong> от прикачения PDF.
    Готови ли са за получаване?
  </div>
  <div class="signal-actions">
    <button class="signal-action primary">Прегледай</button>
    <button class="signal-action">По-късно</button>
  </div>
</article>
```

### 18.6 Parse logic per format

| Format | Library | Approach |
|---|---|---|
| **PDF** | tesseract / pdfparser + Gemini Vision | Извлечи text → AI structure → items[] |
| **CSV** | fgetcsv + heuristic detect | First-row header detect → column map UI |
| **Excel (xlsx/xls)** | PhpSpreadsheet | Sheet 1 → first table → column map |
| **XML/EDI** | SimpleXML / EDI parser | Schema-based mapping |

PHP CSV column mapping UI:
```html
<div class="csv-mapping">
  <h3>Кои колони отговарят на какво?</h3>
  <div class="map-row">
    <span class="map-label">Артикул:</span>
    <select class="map-select">
      <option value="0">Колона 1: "Наименование"</option>
      <option value="1" selected>Колона 2: "Описание"</option>
      <!-- ... -->
    </select>
  </div>
  <div class="map-row">
    <span class="map-label">Бройка:</span>
    <select><!-- ... --></select>
  </div>
  <div class="map-row">
    <span class="map-label">Цена:</span>
    <select><!-- ... --></select>
  </div>
  <div class="map-row">
    <span class="map-label">Код (SKU):</span>
    <select><!-- ... --></select>
  </div>
  <button class="map-confirm">Покажи preview</button>
</div>
```

AI auto-detect: ако header съдържа "name|наименование|описание" → mapping предлага автоматично.

### 18.7 Preview screen

Същата `ocr-review-list` структура (Част 2B §15.4) но със source="import". Confidence per item е "1.0" ако CSV/Excel (структуриран); "AI" ако PDF.

### 18.8 Edge cases в импорт

| Случай | Поведение |
|---|---|
| Артикул в CSV отсъства в каталог | Mini-wizard inline (виж §19) |
| Cost в CSV различен от текущ | Cost change warning ≥10% (виж Част 6) |
| Доставчик не разпознат | Smart picker fallback |
| Encoding проблем (Windows-1251) | Auto-detect → конвертирай UTF-8 |
| Празни редове / merged cells | Skip, log warning |
| Дублирани SKU | Aggregate qty + warn |
| Без header | AI guess по съдържание |

---

## 19. MINI-WIZARD ЗА НОВ АРТИКУЛ В ДОСТАВКА

### 19.1 Принцип "един wizard за артикул навсякъде"

P13 wizard е canonical за products. Когато в доставка се натъкнем на нов артикул (unknown barcode, нов code в CSV, нов name в OCR) → отваряме **същия wizard** но в "minimum mode" (само секция 1).

### 19.2 P13 минимум секция (auto-filled от source)

| Поле | OCR default | Voice default | Manual scan default | CSV default |
|---|---|---|---|---|
| `name` | ✓ extracted | ✓ extracted | "" | ✓ from column |
| `code` | optional | optional | ✓ от scanner | optional |
| `cost_price` | ✓ extracted | ✓ extracted | empty (input req) | ✓ from column |
| `category_id` | AI guess | AI guess | empty (input opt) | AI guess |
| `retail_price` | empty (input req) | empty (input req) | empty (input req) | empty (input req) |
| `quantity` | ✓ extracted | ✓ extracted | 1 (default) | ✓ from column |
| `is_complete` | **0** | **0** | **0** | **0** |

### 19.3 Mini-wizard inline (НЕ отделен screen)

В OCR review list, при confidence <80% за конкретен item → expandable inline:

```html
<article class="ocr-item conf-low expanded" data-item-id="3">
  <span class="item-conf">68%</span>

  <div class="mini-wizard-inline">
    <div class="mw-row">
      <label class="mw-label">Име <span class="req">*</span></label>
      <div class="input-shell">
        <input class="mw-input" value="Тениска синя L" />
        <button class="fbtn voice"><!-- mic --></button>
      </div>
    </div>

    <div class="mw-row">
      <label class="mw-label">Цена доставчик <span class="req">*</span></label>
      <div class="input-shell">
        <input class="mw-input" value="12.50" inputmode="decimal"/>
        <span class="input-suffix">€</span>
        <button class="fbtn voice"><!-- mic --></button>
      </div>
    </div>

    <div class="mw-row">
      <label class="mw-label">
        Цена продажба <span class="opt-pill">по правило</span>
      </label>
      <div class="input-shell">
        <input class="mw-input ai-suggest" value="29.90"
               data-rule="печалба 50% .90"/>
        <span class="input-suffix">€</span>
        <button class="fbtn ai-pill" onclick="recalcSuggested(this)">AI</button>
      </div>
      <small class="mw-hint">AI предлага по твоето правило: cost ×2 + .90</small>
    </div>

    <div class="mw-row">
      <label class="mw-label">Бройка</label>
      <div class="qty-stepper">
        <button class="qty-btn minus">−</button>
        <input class="qty-input" value="3" inputmode="numeric"/>
        <button class="qty-btn plus">+</button>
      </div>
    </div>

    <div class="mw-row">
      <label class="mw-label">Категория <span class="opt-pill">по желание</span></label>
      <select class="mw-select">
        <option value="">— Избери —</option>
        <option selected>Тениски (AI)</option>
        <option>Поло</option>
        <!-- ... -->
      </select>
    </div>

    <div class="mw-actions">
      <button class="mw-cancel" onclick="closeMiniWizard(this)">Отказ</button>
      <button class="mw-save primary" onclick="saveMiniWizard(this)">Запази</button>
    </div>

    <div class="mw-incomplete-hint">
      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      Артикулът ще се отбележи като "недовършен".
      Допълни детайли по-късно от Артикули.
    </div>
  </div>
</article>
```

### 19.4 CSS — Mini-wizard

```css
.mini-wizard-inline {
  margin-top: 12px; padding-top: 12px;
  border-top: 1px dashed var(--border-soft);
  display: grid; gap: 12px;
}
.mw-row { display: grid; gap: 6px; }
.mw-label {
  font-size: 11px; font-weight: 700;
  color: var(--text-muted);
  display: flex; align-items: center; gap: 6px;
}
.mw-label .req {
  color: hsl(0 70% 55%); font-weight: 800;
}
.mw-label .opt-pill {
  font-family: var(--font-mono); font-size: 8.5px; font-weight: 700;
  letter-spacing: 0.05em; text-transform: uppercase;
  background: var(--bg-main); color: var(--text-faint);
  padding: 2px 5px; border-radius: var(--radius-pill);
}

.input-shell {
  display: flex; align-items: center;
  background: var(--bg-main); border-radius: var(--radius-pill);
  box-shadow: var(--shadow-pressed);
  height: 40px; padding: 0 4px 0 12px;
}
.mw-input {
  flex: 1; background: transparent; border: 0;
  font-size: 14px; font-weight: 700; color: var(--text);
}
.mw-input.ai-suggest {
  color: var(--accent);
}
[data-theme="dark"] .mw-input.ai-suggest {
  color: hsl(var(--hue1) 80% 75%);
}
.input-suffix {
  font-family: var(--font-mono); font-size: 11px;
  font-weight: 800; color: var(--text-muted);
  margin: 0 6px;
}

.fbtn {
  width: 32px; height: 32px; border-radius: 50%;
  display: grid; place-items: center;
  background: var(--surface); box-shadow: var(--shadow-card-sm);
}
.fbtn svg { width: 13px; height: 13px; }
.fbtn.voice svg { stroke: var(--accent); }
[data-theme="dark"] .fbtn.voice svg { stroke: hsl(var(--hue1) 80% 75%); }

.fbtn.ai-pill {
  width: auto; padding: 0 10px;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  color: white; box-shadow: 0 2px 8px hsl(280 70% 50% / 0.4);
  font-size: 10px; font-weight: 800; letter-spacing: 0.06em;
  text-transform: uppercase;
  position: relative; overflow: hidden;
}
.fbtn.ai-pill::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg,
    transparent 70%, rgba(255,255,255,0.4) 85%, transparent 100%);
  animation: conicSpin 4s linear infinite;
}

.mw-hint {
  display: block; font-size: 10.5px; color: var(--text-muted);
  font-style: italic; margin-top: -4px;
}

.mw-actions {
  display: flex; gap: 8px; margin-top: 4px;
}
.mw-cancel, .mw-save {
  flex: 1; height: 38px; border-radius: var(--radius-pill);
  font-size: 12px; font-weight: 800;
}
.mw-cancel { background: var(--bg-main); box-shadow: var(--shadow-pressed); }
.mw-save.primary {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  color: white;
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.35);
}

.mw-incomplete-hint {
  display: flex; gap: 6px; align-items: flex-start;
  padding: 8px 10px;
  background: hsl(38 50% 95%);
  border-radius: var(--radius);
  font-size: 11px; color: hsl(38 60% 35%);
  line-height: 1.4;
}
[data-theme="dark"] .mw-incomplete-hint {
  background: hsl(38 30% 10%);
  color: hsl(38 50% 75%);
}
.mw-incomplete-hint svg {
  width: 14px; height: 14px; flex-shrink: 0;
  stroke: hsl(38 80% 50%);
}
```

### 19.5 Save logic

```php
// /api/products/quick-create
function quickCreateProduct($data, $tenant_id) {
    $product_id = DB::run("
        INSERT INTO products (
            tenant_id, name, code, cost_price, retail_price,
            category_id, is_complete, is_active, created_at, source
        ) VALUES (?, ?, ?, ?, ?, ?, 0, 1, NOW(), ?)
    ", [
        $tenant_id, $data['name'], $data['code'] ?? null,
        $data['cost_price'], $data['retail_price'],
        $data['category_id'] ?? null,
        $data['source']  // 'ocr' | 'voice' | 'scan' | 'csv'
    ]);

    // Audit
    DB::run("INSERT INTO product_events (product_id, event_type, user_id, created_at, payload)
             VALUES (?, 'quick_created_in_delivery', ?, NOW(), ?)",
        [$product_id, $_SESSION['user_id'], json_encode($data)]);

    return ['product_id' => $product_id, 'is_complete' => 0];
}
```

### 19.6 Incomplete продукти (is_complete=0)

В products.php:
- Filter "Само недовършени" → бърз достъп
- В horizontal list — pill "⚠ Недовършен"
- Tap → отваря P13 wizard на секция 2 (където беше прекратен)

В Life Board (cron):
- AI signal P2 "Имаш 5 недовършени артикула — допълни ги"

---

## 20. SMART PRICING RULES

### 20.1 Защо съществува

Когато доставка пристига и нов артикул се добавя:
- Cost = известно от доставчика
- Retail = трябва да се изчисли по правило (не "наценка", а **печалба %**)

Без правило → Пешо/Митко въвежда retail manually за всеки артикул → 30 секунди × 100 артикула = 50 минути губене на време.

### 20.2 Settings → Ценова стратегия

```
settings.php → "Ценова стратегия"
   ├─ Глобална стратегия
   │   ├─ Печалба %: [ 50 ]%
   │   ├─ Закръгляване:
   │   │     ◯ На цели лева (100, 110, 120)
   │   │     ◯ Завършва на .99 (29.99)
   │   │     ◯ Завършва на .90 (29.90)  ← default ENI
   │   │     ◯ Завършва на .50 (29.50)
   │   │     ◯ Кратно на 5 (25, 30, 35)
   │   ├─ Минимална печалба %: [ 25 ]%  (ако правило даде <25%, alert)
   │   └─ Допълнителна корекция в лв: [ 0.00 ]
   │
   ├─ Per-категория override
   │   ├─ Тениски: 60% .90 [✏]
   │   ├─ Чанти: 80% .50 [✏]
   │   └─ + Добави
   │
   ├─ Per-доставчик override
   │   ├─ Емпорио: 45% .90 [✏]  (защото вече марж-надценени)
   │   ├─ Турция Текстил: 55% .90 [✏]
   │   └─ + Добави
   │
   └─ Bulk apply
       ├─ "Преобновяй всички съществуващи цени по новото правило"
       │     [ Преглед на 234 артикула ] [ Прилагай ]
       └─ "Само нови артикули" (default ON)
```

### 20.3 Терминология (S88B-2 решение)

**Никога "наценка"** — винаги "Печалба %".

| ❌ | ✅ |
|---|---|
| Наценка 100% | Печалба 50% |
| Markup | Margin |
| Margin (като печалба над cost) | Печалба над retail |

**Формула:**
```
margin_pct = (retail - cost) / retail × 100
```

Примери:
- Cost 10€, Retail 20€ → margin 50%
- Cost 10€, Retail 100€ → margin 90%
- Cost 10€, Retail 11€ → margin 9%

### 20.4 Закръгляване — детайли

```php
function applyRounding($price, $rule) {
    switch ($rule) {
        case 'whole':       return ceil($price);                            // 29.34 → 30
        case 'end_99':      return floor($price) + 0.99;                    // 29.34 → 29.99
        case 'end_90':      return floor($price) + 0.90;                    // 29.34 → 29.90
        case 'end_50':      return floor($price * 2) / 2 + 0.5;             // sticky to .50
        case 'multi_5':     return ceil($price / 5) * 5;                    // 29.34 → 30
        case 'multi_10':    return ceil($price / 10) * 10;                  // 29.34 → 30
        default:            return round($price, 2);
    }
}
```

### 20.5 AI логика при нов артикул в доставка

```php
function calculateRetailPrice($cost, $product, $supplier_id, $tenant_id) {
    // Priority chain: per-supplier > per-category > global
    $rule = DB::get("SELECT margin_pct, rounding FROM pricing_rules
                     WHERE tenant_id = ? AND supplier_id = ?", [$tenant_id, $supplier_id])
         ?? DB::get("SELECT margin_pct, rounding FROM pricing_rules
                     WHERE tenant_id = ? AND category_id = ?",
                     [$tenant_id, $product['category_id']])
         ?? DB::get("SELECT margin_pct, rounding FROM pricing_rules
                     WHERE tenant_id = ? AND scope = 'global'", [$tenant_id]);

    // Reverse formula: retail = cost / (1 - margin_pct/100)
    $retail = $cost / (1 - $rule['margin_pct'] / 100);

    // Apply rounding
    $retail = applyRounding($retail, $rule['rounding']);

    // Min profit guard
    $actual_margin = ($retail - $cost) / $retail * 100;
    if ($actual_margin < ($rule['min_margin'] ?? 25)) {
        return [
            'retail' => $retail,
            'warning' => "Маржът е {$actual_margin}% — под минимума {$rule['min_margin']}%"
        ];
    }

    return ['retail' => $retail, 'actual_margin' => $actual_margin];
}
```

### 20.6 Bulk apply

UI:
```html
<div class="bulk-pricing">
  <h3>Преобновяване на цени</h3>
  <p>Промени правилото и виж новите цени преди да приложиш.</p>

  <div class="bulk-summary">
    <div class="b-row">
      <span>Засегнати артикули:</span>
      <strong>234</strong>
    </div>
    <div class="b-row">
      <span>От тях с увеличение ≥10%:</span>
      <strong class="warn">42</strong>
    </div>
    <div class="b-row">
      <span>Под минимума 25% марж:</span>
      <strong class="warn">8</strong>
    </div>
  </div>

  <button class="b-cta primary" onclick="previewBulkPricing()">
    Прегледай 234
  </button>
  <button class="b-cta">Прилагай без преглед</button>
  <button class="b-cta danger">Откажи</button>
</div>
```

### 20.7 Глобален обхват

Pricing Rules важат **навсякъде** в системата:
- При нов артикул в доставка
- При нов артикул в P13 wizard
- При cost change в доставка → auto-update retail (опционално)
- При AI suggest за promo (post-beta)

---

## КРАЙ НА ЧАСТ 3B

# ═══════════════════════════════════════
# ЧАСТ 4A — РАЗШИРЕН РЕЖИМ: ГЛАВЕН ЕКРАН
# ═══════════════════════════════════════

## 21. ГЛАВЕН ЕКРАН НА РАЗШИРЕН РЕЖИМ

### 21.1 Структура (Dashboard style)

```
┌─────────────────────────────────────┐
│ [≪] Доставки           [🔍][🎤][⚙]  │  ← header (56px) - 3 икони + меню
├─────────────────────────────────────┤
│ ┌─Лесен──[ Разширен ]───────────────┐│  ← mode-toggle (sticky)
├─────────────────────────────────────┤
│ ┌────────┬────────┬─────────────────┐│
│ │Получено│Чакащи  │ Закъснели       ││  ← KPI strip (3 cells)
│ │14 230€ │8 350€  │ 2 230€  (3)     ││
│ └────────┴────────┴─────────────────┘│
│                                      │
│ ┌──[ Чакат(8) ]─[Готови(34)]─[История]──┐│  ← status tabs
│                                      │
│ [⚡ Спешни 3] [Емпорио] [Този месец] [+]│  ← quick chips
│                                      │
│ ┌─ Sort: По дата ↓ ──── Изглед: По дата ▾┐│  ← sort + view
│                                      │
│ ┌─────────────────────────────────┐ │
│ │ 📦 #DEL-2026-0234              │ │  ← rich card 1
│ │ Емпорио ООД · 18 артикула      │ │
│ │ 2 340 € · Очаквана 10.05       │ │
│ │ [⏰ Чакаща] [Поръчка #789]    │ │
│ └─────────────────────────────────┘ │
│ ┌─────────────────────────────────┐ │
│ │ 📦 #DEL-2026-0233              │ │  ← rich card 2
│ │ Турция Текстил · 240 артикула  │ │
│ │ 6 800 € · Закъснява 3 дни ⚠   │ │
│ │ [🚨 Закъсняла] [Поръчка #802] │ │
│ └─────────────────────────────────┘ │
│ … още 6 карти                       │
│                                      │
│ ┌─────────────────────────────────┐ │
│ │ + Получи нова доставка          │ │  ← floating CTA
│ └─────────────────────────────────┘ │
├─────────────────────────────────────┤
│  [🤖] [📦] [📊] [⚡]                  │  ← bottom nav
└─────────────────────────────────────┘
```

### 21.2 Header — 3 икони + меню

| Икона | Action |
|---|---|
| 🔍 search | Header-mounted search bar (виж Част 4B §27) |
| 🎤 voice | Voice command — "Покажи закъснели от Емпорио" |
| ⚙ menu | Меню (☰) с 6 секции (виж Част 5B §31) |

Theme toggle е в menu, не в header (3 икони max за разширен режим).

### 21.3 Defaults при влизане

- **Status tab:** "Чакат" (всички pending)
- **Sort:** "По дата ↓" (newest first)
- **View:** "По дата"
- **Quick chips:** "Спешни 3" винаги първа (auto-generated от overdue count)
- **Mode toggle:** разширен (от localStorage)

### 21.4 Защо "По дата" а не "По доставчик"

| Защо | Обяснение |
|---|---|
| **Митко мисли темпорално** | "Какво има днес да получавам", не "какво има от Емпорио" |
| **Календарна работа** | Доставките са събития; времето е main axis |
| **AI signals се връзват към дата** | "Закъсняла 3 дни" е date-anchored |
| **Lightspeed/Microinvest стандарт** | Индустриалният default |

"По доставчик" остава като alternative view (виж §24).

### 21.5 Padding & spacing

```css
.deliv-extended-shell { padding: 0 12px; }
.deliv-kpi-strip { margin-top: 12px; }
.deliv-status-tabs { margin-top: 14px; }
.deliv-quick-chips { margin-top: 10px; padding-bottom: 4px; overflow-x: auto; }
.deliv-sort-bar { margin-top: 10px; }
.deliv-cards-list { margin-top: 8px; padding-bottom: 100px; }
```

---

## 22. KPI STRIP

### 22.1 Структура (3 cells)

```html
<div class="deliv-kpi-strip">
  <button class="kpi-cell q-gain" onclick="filterByStatus('received', 'this_month')">
    <span class="kpi-label">Получено</span>
    <strong class="kpi-value">14 230 <span class="kpi-cur">€</span></strong>
    <span class="kpi-sub">Този месец · 34 доставки</span>
    <span class="kpi-trend">↑ 12% vs мин.</span>
  </button>

  <button class="kpi-cell q-amber" onclick="filterByStatus('pending')">
    <span class="kpi-label">Чакащи</span>
    <strong class="kpi-value">8 350 <span class="kpi-cur">€</span></strong>
    <span class="kpi-sub">8 доставки</span>
  </button>

  <button class="kpi-cell q-loss pulse" onclick="filterByStatus('overdue')">
    <span class="kpi-label">Закъснели</span>
    <strong class="kpi-value">2 230 <span class="kpi-cur">€</span></strong>
    <span class="kpi-sub">3 доставки · ⚠</span>
  </button>
</div>
```

### 22.2 CSS

```css
.deliv-kpi-strip {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 8px;
}
.kpi-cell {
  text-align: left;
  padding: 12px 10px;
  border-radius: var(--radius);
  background: var(--surface);
  box-shadow: var(--shadow-card-sm);
  position: relative;
  cursor: pointer;
  transition: transform 0.15s var(--ease);
  display: grid; gap: 2px;
}
.kpi-cell:active { transform: scale(0.97); }
[data-theme="dark"] .kpi-cell {
  background: hsl(220 25% 6% / 0.7);
  backdrop-filter: blur(8px);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}

.kpi-label {
  font-family: var(--font-mono); font-size: 9px; font-weight: 800;
  letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--text-muted);
}
.kpi-value {
  font-size: 18px; font-weight: 800; letter-spacing: -0.02em;
  color: var(--text); line-height: 1.1;
}
.kpi-cur {
  font-size: 11px; font-weight: 800; opacity: 0.6;
  margin-left: 1px;
}
.kpi-sub {
  font-size: 9.5px; color: var(--text-faint);
  font-weight: 600; margin-top: 1px;
}
.kpi-trend {
  font-family: var(--font-mono); font-size: 9px; font-weight: 700;
  color: hsl(145 70% 45%); margin-top: 2px;
}

/* Hue accents — left border */
.kpi-cell.q-gain { border-left: 3px solid hsl(145 70% 50%); }
.kpi-cell.q-amber { border-left: 3px solid hsl(38 90% 55%); }
.kpi-cell.q-loss { border-left: 3px solid hsl(0 70% 55%); }
.kpi-cell.q-loss .kpi-value { color: hsl(0 70% 50%); }
[data-theme="dark"] .kpi-cell.q-loss .kpi-value { color: hsl(0 80% 70%); }

/* Pulse on critical */
.kpi-cell.pulse {
  animation: kpiPulse 2s ease-in-out infinite;
}
@keyframes kpiPulse {
  0%, 100% { box-shadow: var(--shadow-card-sm); }
  50% { box-shadow: 0 0 0 3px hsl(0 70% 55% / 0.2), var(--shadow-card-sm); }
}
```

### 22.3 Поведение

- Tap → филтрира главния списък по статус (closure до §23 status tabs)
- Trend arrow: показва само ако diff vs предходен период ≥5%
- Pulse: само на `q-loss` cell ако count > 0

---

## 23. STATUS TABS (3 МЕТА-КАТЕГОРИИ)

### 23.1 Защо 3, не 8

DB има 6 native статуса (`sent`, `acked`, `partial`, `received`, `overdue`, `cancelled`). За UX → 3 мета:

| Tab | DB статуси | Какво вижда Митко |
|---|---|---|
| **Чакат** (default) | `sent`, `acked`, `partial`, `overdue` | Всички pending. Sub-pills за overdue. |
| **Готови** | `received` (последни 30 дни) | Recently received |
| **История** | `received` (>30 дни), `cancelled` | Архив |

### 23.2 HTML

```html
<div class="deliv-status-tabs" role="tablist">
  <button class="status-tab active" data-tab="pending" role="tab">
    <span>Чакат</span>
    <span class="tab-count">8</span>
  </button>
  <button class="status-tab" data-tab="ready" role="tab">
    <span>Готови</span>
    <span class="tab-count">34</span>
  </button>
  <button class="status-tab" data-tab="history" role="tab">
    <span>История</span>
  </button>
</div>
```

### 23.3 CSS

```css
.deliv-status-tabs {
  display: flex; gap: 0;
  background: var(--bg-main);
  border-radius: var(--radius-pill);
  box-shadow: var(--shadow-pressed);
  padding: 4px;
}
.status-tab {
  flex: 1; height: 38px;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  font-size: 12px; font-weight: 800; letter-spacing: -0.01em;
  color: var(--text-muted);
  border-radius: var(--radius-pill);
  background: transparent;
  transition: all 0.25s var(--ease-spring);
}
.status-tab.active {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.35);
  color: white;
}
.status-tab.active::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg,
    transparent 70%, rgba(255,255,255,0.3) 85%, transparent 100%);
  animation: conicSpin 5s linear infinite;
  border-radius: var(--radius-pill);
}
.tab-count {
  font-family: var(--font-mono); font-size: 9.5px; font-weight: 800;
  background: rgba(255,255,255,0.2); color: inherit;
  padding: 2px 6px; border-radius: var(--radius-pill);
  min-width: 22px;
}
.status-tab:not(.active) .tab-count {
  background: var(--surface); color: var(--text);
}
```

### 23.4 Sub-pills в "Чакат"

Когато tab="Чакат" → показват се sub-pills за под-категоризация:

```html
<div class="deliv-substats">
  <button class="substat-pill all active">Всички 8</button>
  <button class="substat-pill q-amber">Sent 5</button>
  <button class="substat-pill q-amber">Acked 2</button>
  <button class="substat-pill q-loss">Overdue 1</button>
  <button class="substat-pill q-amber">Partial 0</button>
</div>
```

CSS: pill 24px height, font-mono 10px, gap 4px. Tap → second-level filter.

---

## 24. VIEW MODES — 5 ОПЦИИ

### 24.1 Sort + View dropdown горе

```html
<div class="deliv-sort-bar">
  <button class="sb-btn" onclick="openSortDropdown()">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <line x1="21" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/>
      <line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="11" y2="18"/>
    </svg>
    <span>Sort: По дата ↓</span>
  </button>
  <div class="sb-divider"></div>
  <button class="sb-btn" onclick="openViewDropdown()">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
      <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
    </svg>
    <span>Изглед: По дата</span>
  </button>
</div>
```

CSS: 38px height, flex 1 split, var(--surface), shadow-pressed на :active.

### 24.2 По дата (default)

Списък на rich cards descending по `expected_at` (sent/acked/overdue) или `received_at` (received).

```html
<article class="deliv-card" data-id="234">
  <header class="dc-head">
    <span class="dc-orb q-amber">
      <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
        <rect x="1" y="3" width="15" height="13" rx="1"/>
        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
      </svg>
    </span>
    <div class="dc-title">
      <strong>#DEL-2026-0234</strong>
      <span class="dc-supplier">Емпорио ООД</span>
    </div>
    <div class="dc-meta">
      <span class="dc-value">2 340 €</span>
      <span class="dc-time">10.05</span>
    </div>
  </header>
  <div class="dc-pills">
    <span class="status-pill q-amber">⏰ Чакаща</span>
    <span class="status-pill q-default">Поръчка #789</span>
    <span class="status-pill q-default">18 артикула</span>
  </div>
</article>
```

CSS:
```css
.deliv-card {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-card);
  padding: 12px 14px;
  margin-bottom: 8px;
  cursor: pointer;
  transition: transform 0.15s var(--ease);
}
[data-theme="dark"] .deliv-card {
  background: hsl(220 25% 6% / 0.7);
  backdrop-filter: blur(8px);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}
.deliv-card:active { transform: scale(0.99); }

.dc-head {
  display: flex; gap: 10px; align-items: center;
  margin-bottom: 8px;
}
.dc-orb {
  width: 36px; height: 36px; border-radius: var(--radius-icon);
  display: grid; place-items: center; flex-shrink: 0;
  position: relative; overflow: hidden;
}
.dc-orb svg { width: 16px; height: 16px; position: relative; z-index: 1; }
.dc-orb.q-amber {
  background: linear-gradient(135deg, hsl(38 90% 55%), hsl(28 85% 50%));
}
.dc-orb.q-loss {
  background: linear-gradient(135deg, hsl(0 70% 55%), hsl(15 70% 50%));
}
.dc-orb.q-gain {
  background: linear-gradient(135deg, hsl(145 70% 50%), hsl(155 70% 40%));
}
.dc-title { flex: 1; min-width: 0; }
.dc-title strong {
  display: block; font-family: var(--font-mono);
  font-size: 11px; font-weight: 800; letter-spacing: 0.06em;
  color: var(--text-muted);
}
.dc-supplier {
  display: block; font-size: 14px; font-weight: 800;
  color: var(--text); margin-top: 1px;
}
.dc-meta { text-align: right; }
.dc-value {
  display: block; font-size: 14px; font-weight: 800;
  color: var(--accent); letter-spacing: -0.01em;
}
[data-theme="dark"] .dc-value { color: hsl(var(--hue1) 80% 75%); }
.dc-time {
  display: block; font-family: var(--font-mono);
  font-size: 9px; font-weight: 700; color: var(--text-faint);
  letter-spacing: 0.06em; margin-top: 1px;
}

.dc-pills {
  display: flex; gap: 6px; flex-wrap: wrap;
}
.status-pill {
  font-family: var(--font-mono); font-size: 9px; font-weight: 800;
  letter-spacing: 0.04em; text-transform: uppercase;
  padding: 3px 8px; border-radius: var(--radius-pill);
}
.status-pill.q-amber {
  background: hsl(38 50% 92%); color: hsl(38 80% 35%);
}
.status-pill.q-loss {
  background: hsl(0 50% 92%); color: hsl(0 70% 40%);
}
.status-pill.q-gain {
  background: hsl(145 50% 92%); color: hsl(145 70% 30%);
}
.status-pill.q-default {
  background: var(--bg-main); color: var(--text-muted);
  box-shadow: var(--shadow-pressed);
}
[data-theme="dark"] .status-pill.q-amber { background: hsl(38 30% 12%); color: hsl(38 80% 70%); }
[data-theme="dark"] .status-pill.q-loss { background: hsl(0 30% 12%); color: hsl(0 80% 70%); }
[data-theme="dark"] .status-pill.q-gain { background: hsl(145 30% 12%); color: hsl(145 70% 70%); }
```

### 24.3 По доставчик (групиране)

Cards се групират под supplier headers. Headers са collapsible.

```html
<div class="deliv-group">
  <header class="dg-head" onclick="toggleGroup(this)">
    <span class="dg-orb">
      <svg viewBox="0 0 24 24"><!-- check --></svg>
    </span>
    <div class="dg-text">
      <strong>Емпорио ООД</strong>
      <span>5 доставки · 8 230 €</span>
    </div>
    <span class="dg-stats">
      <span class="dg-stat q-gain">3 готови</span>
      <span class="dg-stat q-amber">2 чакат</span>
    </span>
    <svg class="dg-chev" viewBox="0 0 24 24"><!-- chevron --></svg>
  </header>
  <div class="dg-body">
    <!-- deliv-card × N -->
  </div>
</div>
```

### 24.4 По статус

Грид с 4 секции (sent, acked, partial, overdue). Cards 2-колонен compact layout.

### 24.5 Календар (от ORDERS §6.3)

```html
<div class="deliv-calendar">
  <header class="cal-nav">
    <button>‹</button>
    <strong>Май 2026</strong>
    <button>›</button>
  </header>
  <div class="cal-grid">
    <span class="cal-day-head">П</span>
    <span class="cal-day-head">В</span>
    <!-- ... -->
    <button class="cal-day" data-date="2026-05-10">
      <span class="day-num">10</span>
      <span class="day-dot q-amber"></span>
      <span class="day-dot q-loss"></span>
    </button>
    <!-- ... -->
  </div>
</div>
```

Tap на ден → list view filter за тази дата.

### 24.6 По 6 въпроса (q1-q6)

Special view, agg-based за management decisions:

| Категория | Какво показва |
|---|---|
| q1 - Какво губя? | Закъснели + lost demand |
| q2 - От какво губя? | Cost increase warnings |
| q3 - Какво печеля? | Lost demand resolved + saved cost |
| q4 - От какво печеля? | Top suppliers |
| q5 - Поръчай! | AI чернова drafts |
| q6 - НЕ поръчай! | Slow movers warnings |

Default: collapsed. Tap → expand cards в съответната category.

---

## 25. SORTИРАНИЯ — 10 ОПЦИИ

### 25.1 Пълен списък

| # | Sort | Поле | Default order |
|---|---|---|---|
| 1 | По дата ↓ (default) | `expected_at` или `received_at` | DESC |
| 2 | По дата ↑ | същото | ASC |
| 3 | По доставчик | `suppliers.name` | ASC |
| 4 | По статус | `status` (custom order) | overdue → sent → received |
| 5 | По стойност ↓ | `total_value` | DESC |
| 6 | По стойност ↑ | същото | ASC |
| 7 | По печалба | calc `(retail-cost) × qty` | DESC |
| 8 | По марж % | calc `(retail-cost)/retail*100` | DESC |
| 9 | По бройка | `total_items` | DESC |
| 10 | По reliability | `suppliers.reliability_score` | DESC |
| 11 | По lead time | `received_at - sent_at` | DESC |

### 25.2 UI dropdown

```html
<div class="sort-dropdown" id="sortDD">
  <h3 class="dd-title">Подреди</h3>
  <button class="dd-opt active">
    <span>По дата ↓</span>
    <svg><!-- check --></svg>
  </button>
  <button class="dd-opt">По дата ↑</button>
  <button class="dd-opt">По доставчик</button>
  <!-- ... -->
</div>
```

CSS: dropdown 280px max-width, top-anchored, var(--surface), shadow-card. Active opt → q-default bg accent.

### 25.3 Custom sort (post-beta)

Митко може да създаде custom sort logic с условни sortирания (напр. "първо overdue, после по стойност DESC").

---

## 26. QUICK CHIPS (HARDCODED + SAVED)

### 26.1 Структура

5 hardcoded + N user-saved:

```html
<div class="deliv-quick-chips">
  <button class="qchip q-loss pulse" data-filter='{"status":"overdue"}'>
    <span class="qc-emoji">⚡</span>
    <span class="qc-label">Спешни</span>
    <span class="qc-count">3</span>
  </button>
  <button class="qchip q-default" data-filter='{"period":"this_month"}'>
    Този месец
  </button>
  <button class="qchip q-magic" data-filter='{"has_ai_signal":true}'>
    AI препоръчва
  </button>
  <button class="qchip q-amber" data-filter='{"status":"partial"}'>
    Частични
  </button>
  <button class="qchip q-gain" data-filter='{"lost_resolved":true}'>
    Lost resolved
  </button>
  <!-- saved by user -->
  <button class="qchip q-default user-saved" data-filter-id="42">
    Емпорио · €>1000
  </button>
  <button class="qchip add" onclick="openSaveCurrentFilters()">
    +
  </button>
</div>
```

### 26.2 CSS

```css
.deliv-quick-chips {
  display: flex; gap: 8px;
  overflow-x: auto;
  padding: 0 12px 4px;
  margin: 0 -12px;
  scrollbar-width: none;
}
.deliv-quick-chips::-webkit-scrollbar { display: none; }

.qchip {
  flex-shrink: 0;
  height: 32px; padding: 0 12px;
  display: inline-flex; align-items: center; gap: 6px;
  border-radius: var(--radius-pill);
  font-size: 12px; font-weight: 700;
  background: var(--surface); box-shadow: var(--shadow-card-sm);
}
[data-theme="dark"] .qchip {
  background: hsl(220 25% 6% / 0.7);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}
.qchip.active {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  color: white;
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.35);
}
.qchip.q-loss { border-left: 3px solid hsl(0 70% 55%); }
.qchip.q-amber { border-left: 3px solid hsl(38 90% 55%); }
.qchip.q-gain { border-left: 3px solid hsl(145 70% 50%); }
.qchip.q-magic { border-left: 3px solid hsl(280 70% 55%); }

.qchip.pulse {
  animation: kpiPulse 2s ease-in-out infinite;
}

.qc-emoji { font-size: 13px; }
.qc-count {
  font-family: var(--font-mono); font-size: 9.5px;
  background: rgba(0,0,0,0.06); color: var(--text-muted);
  padding: 1px 5px; border-radius: var(--radius-pill);
}
[data-theme="dark"] .qc-count { background: rgba(255,255,255,0.06); }

.qchip.add {
  width: 32px; padding: 0;
  justify-content: center;
  font-size: 16px; font-weight: 700;
  color: var(--text-faint);
  border: 1px dashed var(--border-soft);
  background: transparent; box-shadow: none;
}
```

### 26.3 Tap behavior

- Single tap → активира filter (replace current)
- Long-press (post-beta) → "редактирай / премести / изтрий"
- "+" → отваря "Запази текущите филтри"

---

## КРАЙ НА ЧАСТ 4A

# ═══════════════════════════════════════
# ЧАСТ 4B — РАЗШИРЕН: ФИЛТРИ + SEARCH + AI
# ═══════════════════════════════════════

## 27. ФИЛТРИ — ACCORDION DRAWER

### 27.1 Trigger

Tap на filter icon в sort-bar (или horizontal swipe от right edge на главния списък).

```html
<button class="open-filters-btn">
  <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
  </svg>
  <span class="filter-count">3</span>  <!-- active filters badge -->
</button>
```

### 27.2 Структура на drawer

Side-sheet (right edge) — 88vw максимум на mobile, full-height. Базиран на P13 `.gs-sheet` pattern но horizontal.

```html
<div class="filter-drawer" id="filterDrawer">
  <header class="fd-head">
    <h2>Филтри</h2>
    <button class="fd-clear" onclick="clearAllFilters()">Изчисти всички</button>
    <button class="fd-close" onclick="closeFilters()">
      <svg viewBox="0 0 24 24"><!-- X --></svg>
    </button>
  </header>

  <div class="fd-body">
    <!-- 17 accordion sections -->
    <details class="fd-section" open>
      <summary class="fd-summary">
        <span class="fd-icon"><!-- icon --></span>
        <span class="fd-label">Период</span>
        <span class="fd-active-count">2 избрани</span>
      </summary>
      <div class="fd-content">
        <!-- секция съдържание -->
      </div>
    </details>

    <!-- ... -->
  </div>

  <footer class="fd-foot">
    <button class="fd-apply">Приложи (12 резултата)</button>
  </footer>
</div>
```

### 27.3 CSS на drawer

```css
.filter-drawer {
  position: fixed; top: 0; right: 0; bottom: 0;
  width: 88vw; max-width: 420px;
  background: var(--bg-main);
  z-index: 150;
  transform: translateX(100%);
  transition: transform 0.32s var(--ease-spring);
  display: flex; flex-direction: column;
  box-shadow: -8px 0 32px rgba(0,0,0,0.16);
}
.filter-drawer.open { transform: translateX(0); }

.fd-head {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--border-soft);
}
.fd-head h2 { flex: 1; font-size: 17px; font-weight: 800; }
.fd-clear { font-size: 11px; color: var(--accent); font-weight: 700; }
.fd-close {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--surface); box-shadow: var(--shadow-card-sm);
  display: grid; place-items: center;
}

.fd-body { flex: 1; overflow-y: auto; padding: 8px 0; }
.fd-section {
  border-bottom: 1px solid var(--border-soft);
}
.fd-summary {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 16px;
  cursor: pointer;
  list-style: none;
}
.fd-summary::-webkit-details-marker { display: none; }
.fd-icon {
  width: 24px; height: 24px;
  display: grid; place-items: center;
  color: var(--text-muted);
}
.fd-label { flex: 1; font-size: 13px; font-weight: 700; }
.fd-active-count {
  font-family: var(--font-mono); font-size: 9px;
  font-weight: 800; letter-spacing: 0.04em;
  color: var(--accent);
}
.fd-section[open] .fd-summary::after {
  content: '−'; font-size: 18px; font-weight: 700; color: var(--text-muted);
}
.fd-section:not([open]) .fd-summary::after {
  content: '+'; font-size: 18px; font-weight: 700; color: var(--text-muted);
}

.fd-content { padding: 0 16px 14px; }

.fd-foot {
  padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0));
  border-top: 1px solid var(--border-soft);
  background: var(--bg-main);
}
.fd-apply {
  width: 100%; height: 48px;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  color: white; border-radius: var(--radius);
  font-size: 14px; font-weight: 800; letter-spacing: -0.01em;
  position: relative; overflow: hidden;
}
.fd-apply::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.3) 85%, transparent 100%);
  animation: conicSpin 5s linear infinite;
}
```

### 27.4 17 СЕКЦИИ — ПЪЛЕН СПИСЪК

Всяка секция има специфичен content type. Ниже — каква UI/control се ползва.

| # | Секция | Control type | Опции |
|---|---|---|---|
| 1 | **Период** | Date range + presets | Today / Yesterday / Last 7d / This month / Last month / Custom range / Last 90d / This year |
| 2 | **Статус** | Multi-checkbox | sent / acked / partial / received / overdue / cancelled |
| 3 | **Доставчик** | Search + multi-checkbox | Typeahead + 5 most recent + checkbox list |
| 4 | **Магазин** | Multi-checkbox | All ENI 5 stores |
| 5 | **Категория артикул** | Tree multi-select | Tree-view с expand/collapse |
| 6 | **Стойност (€)** | Range slider | Min - Max € (drag both ends) |
| 7 | **Бройка артикули** | Range slider | Min - Max items |
| 8 | **Печалба %** | Range slider | 0-100% |
| 9 | **Cost change** | Multi-checkbox | Без промяна / Увеличение ≥5% / ≥10% / ≥25% / Намаление |
| 10 | **Тип** | Single-select pills | Срещу поръчка / Manual / Email forward / Partner portal |
| 11 | **Произход** | Multi-checkbox | OCR / Voice / Manual scan / Import / Email |
| 12 | **Lead time** | Range slider | 0-30 дни |
| 13 | **Multi-store split** | Single-select | All / Само split / Само single-store |
| 14 | **Lost demand resolved** | Toggle | Yes / No / Any |
| 15 | **С AI signal** | Toggle | Само такива с активен AI signal |
| 16 | **Има incomplete продукти** | Toggle | Само такива със `is_complete=0` items |
| 17 | **Любими (saved filters)** | List | Apply saved filter as base |

### 27.5 Контроли — детайл

#### Date range + presets (секция 1)

```html
<div class="fd-content">
  <div class="preset-pills">
    <button class="preset-pill active">Този месец</button>
    <button class="preset-pill">Last 7d</button>
    <button class="preset-pill">Last 30d</button>
    <button class="preset-pill">Custom</button>
  </div>
  <div class="date-range" hidden>
    <input type="date" class="dr-from" />
    <span>—</span>
    <input type="date" class="dr-to" />
  </div>
</div>
```

#### Multi-checkbox (секции 2, 3, 4, 9, 11)

```html
<div class="fd-content">
  <label class="fd-check">
    <input type="checkbox" checked />
    <span class="fd-check-box"></span>
    <span class="fd-check-label">Sent</span>
    <span class="fd-check-count">5</span>
  </label>
  <label class="fd-check">
    <input type="checkbox" />
    <span class="fd-check-box"></span>
    <span class="fd-check-label">Acked</span>
    <span class="fd-check-count">2</span>
  </label>
  <!-- ... -->
</div>
```

CSS:
```css
.fd-check {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 0;
  cursor: pointer;
}
.fd-check input { display: none; }
.fd-check-box {
  width: 20px; height: 20px;
  border-radius: 6px;
  background: var(--bg-main); box-shadow: var(--shadow-pressed);
  display: grid; place-items: center;
}
.fd-check input:checked + .fd-check-box {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 2px 8px hsl(var(--hue1) 80% 50% / 0.35);
}
.fd-check input:checked + .fd-check-box::after {
  content: ''; width: 10px; height: 6px;
  border-left: 2px solid white; border-bottom: 2px solid white;
  transform: rotate(-45deg) translate(1px, -1px);
}
.fd-check-label { flex: 1; font-size: 13px; font-weight: 600; color: var(--text); }
.fd-check-count {
  font-family: var(--font-mono); font-size: 9px;
  font-weight: 700; color: var(--text-faint);
}
```

#### Range slider (секции 6, 7, 8, 12)

```html
<div class="fd-content">
  <div class="range-slider" data-min="0" data-max="10000">
    <div class="rs-track"></div>
    <div class="rs-fill" style="left: 20%; width: 60%"></div>
    <div class="rs-thumb left" style="left: 20%"></div>
    <div class="rs-thumb right" style="left: 80%"></div>
  </div>
  <div class="rs-values">
    <span>2 000 €</span>
    <span>—</span>
    <span>8 000 €</span>
  </div>
</div>
```

#### Tree multi-select (секция 5)

```html
<div class="fd-content">
  <details class="tree-node">
    <summary>Облекло</summary>
    <details class="tree-node">
      <summary>Тениски</summary>
      <label class="fd-check"><input type="checkbox"/> Тениски памук</label>
      <label class="fd-check"><input type="checkbox"/> Тениски синтетика</label>
    </details>
    <details class="tree-node">
      <summary>Поло</summary>
      <!-- ... -->
    </details>
  </details>
  <details class="tree-node">
    <summary>Чанти</summary>
    <!-- ... -->
  </details>
</div>
```

#### Toggle (секции 14, 15, 16)

```html
<div class="fd-content">
  <div class="fd-toggle-row">
    <span class="fd-toggle-label">Само такива с AI signal</span>
    <button class="fd-toggle" data-active="false">
      <span class="fd-toggle-knob"></span>
    </button>
  </div>
</div>
```

#### Single-select pills (секция 10)

```html
<div class="fd-content">
  <div class="pill-group">
    <button class="fd-pill active">Всички</button>
    <button class="fd-pill">Срещу поръчка</button>
    <button class="fd-pill">Manual</button>
    <button class="fd-pill">Email</button>
  </div>
</div>
```

### 27.6 Combine logic

- Default: всички филтри AND-ed
- Multi-checkbox values WITHIN секция: OR
- Между секции: AND

Пример: "статус IN (sent, overdue) AND supplier IN (Емпорио, Турция) AND value BETWEEN 2000 AND 8000"

### 27.7 Saved "Любими" filters

Митко може да запази текущите филтри:

```html
<div class="save-filter-modal">
  <h3>Запази филтрите</h3>
  <input class="sf-input" placeholder="Име (напр. 'Спешни от Емпорио')" />
  <div class="sf-icon-picker">
    <button class="sf-icon active">⚡</button>
    <button class="sf-icon">📦</button>
    <button class="sf-icon">⏰</button>
    <button class="sf-icon">🔥</button>
  </div>
  <div class="sf-color-picker">
    <button class="sf-color q-loss"></button>
    <button class="sf-color q-amber active"></button>
    <button class="sf-color q-gain"></button>
    <button class="sf-color q-magic"></button>
    <button class="sf-color q-default"></button>
  </div>
  <button class="sf-save">Запази</button>
</div>
```

DB:
```sql
CREATE TABLE saved_filters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT, user_id INT,
    module ENUM('deliveries','orders','products') DEFAULT 'deliveries',
    name VARCHAR(64),
    icon VARCHAR(8),
    hue_class VARCHAR(16),
    filters_json JSON,
    sort_order INT DEFAULT 0,
    is_pinned BOOLEAN DEFAULT 0,  -- pin в quick chips
    created_at TIMESTAMP
);
```

### 27.8 Live preview count

При всяко toggle на филтър — debounced 200ms PHP query за count:
```php
// /api/deliveries/count
function countWithFilters($filters, $tenant_id) {
    $where = buildFilterWhere($filters);
    return DB::get("SELECT COUNT(*) AS c FROM deliveries WHERE tenant_id=? $where",
        [$tenant_id])['c'];
}
```

UI update: footer button text "Приложи (12 резултата)" обновява се.

---

## 28. SEARCH

### 28.1 Trigger

- Tap на 🔍 в header → header се трансформира в search bar
- Voice search: tap на 🎤 → "AI разбираш ме?"

### 28.2 Search bar

```html
<header class="bm-header search-mode">
  <button class="search-back" onclick="closeSearch()">
    <svg><!-- X --></svg>
  </button>
  <input class="search-input" type="search" autofocus
         placeholder="Търси доставка, артикул, доставчик..." />
  <button class="search-voice">
    <svg><!-- mic --></svg>
  </button>
</header>

<div class="search-results">
  <!-- Recent searches -->
  <section class="sr-section">
    <h3 class="sr-head">Скорошни</h3>
    <button class="sr-row recent">
      <svg><!-- clock --></svg>
      <span>Емпорио май 2026</span>
    </button>
    <button class="sr-row recent">
      <svg><!-- clock --></svg>
      <span>тениска синя</span>
    </button>
  </section>

  <!-- Saved searches (post-typing) -->
  <section class="sr-section">
    <h3 class="sr-head">Резултати</h3>
    <article class="sr-row result">
      <span class="sr-thumb"><!-- icon --></span>
      <div class="sr-text">
        <strong>#DEL-2026-0234</strong>
        <span>Емпорио · 18 артикула · 2 340 €</span>
      </div>
      <span class="sr-pill q-amber">Чакаща</span>
    </article>
  </section>
</div>
```

### 28.3 Какво търси

Full-text search across:
- `deliveries.delivery_number` (e.g. "DEL-2026-0234", "0234")
- `suppliers.name` (e.g. "Емпорио")
- `delivery_items.notes` (e.g. "тениска синя")
- `products.name` (всички items в delivery)
- `products.code` (SKU/barcode)
- `deliveries.notes`
- `delivery_events.payload` (audit trail)

PHP:
```php
function searchDeliveries($query, $tenant_id, $limit = 20) {
    return DB::all("
        SELECT DISTINCT d.id, d.delivery_number, d.status, d.total_value,
               s.name AS supplier_name,
               (SELECT COUNT(*) FROM delivery_items WHERE delivery_id=d.id) AS items_count
        FROM deliveries d
        JOIN suppliers s ON d.supplier_id = s.id
        LEFT JOIN delivery_items di ON di.delivery_id = d.id
        LEFT JOIN products p ON di.product_id = p.id
        WHERE d.tenant_id = ?
          AND (
              d.delivery_number LIKE ?
              OR s.name LIKE ?
              OR di.notes LIKE ?
              OR p.name LIKE ?
              OR p.code = ?
              OR d.notes LIKE ?
          )
        ORDER BY d.created_at DESC
        LIMIT ?
    ", [$tenant_id, "%$query%", "%$query%", "%$query%", "%$query%", $query, "%$query%", $limit]);
}
```

### 28.4 Voice search

Tap на 🎤 в search bar → voice overlay (същия като Voice flow в Част 2B). Транскрипт → search query.

Special voice commands:
- "Покажи закъснели от Емпорио" → filter `status=overdue AND supplier=Емпорио`
- "Доставки тази седмица над 1000 евро" → period=this_week + value>=1000
- "Покажи частично доставени" → status=partial

### 28.5 Recent searches

```sql
CREATE TABLE recent_searches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT, tenant_id INT,
    module VARCHAR(32),
    query VARCHAR(256),
    searched_at TIMESTAMP
);
```

Cap 20 records per user. Auto-cleanup >30 days. Display top 5 при отваряне.

### 28.6 Saved searches (post-beta)

User може да pin-не search query като saved filter (виж §27.7).

---

## 29. AI СИГНАЛИ В РАЗШИРЕН РЕЖИМ

### 29.1 Колко

5-8 видими + "Виж всички" линк → AI Signal Browser (отделен экран).

### 29.2 40 теми в 4 категории (от S51_AI_TOPICS_MASTER)

| Категория | Брой | Примери |
|---|---|---|
| **deliv_track_*** (tracking) | 10 | overdue, expected_today, partial, late_avg, no_response |
| **deliv_receive_*** (получаване) | 8 | new_email, ocr_pending, voice_unclear, ai_draft_ready, multi_store_suggested |
| **deliv_after_*** (post-receive) | 12 | cost_increase, lost_resolved, margin_warn, label_print, hidden_boost, category_count, slow_mover, fast_seller, supplier_perfect, supplier_late, partial_followup, transfer_suggest |
| **deliv_analysis_*** (insights) | 10 | best_supplier, worst_supplier, lead_time_trend, seasonal_pattern, payment_terms, vat_anomaly, currency_volatility, gross_margin_trend, restock_velocity, dead_stock |

### 29.3 Plan + Role gate

```php
function canSeeSignal($topic, $user, $plan) {
    $rules = [
        'deliv_overdue'           => ['plan' => 'free',     'role' => ['owner','manager','seller']],
        'deliv_expected_today'    => ['plan' => 'free',     'role' => ['owner','manager','seller']],
        'deliv_lost_resolved'     => ['plan' => 'free',     'role' => ['owner','manager','seller']],
        'deliv_cost_increase'     => ['plan' => 'start',    'role' => ['owner','manager']],
        'deliv_margin_warn'       => ['plan' => 'start',    'role' => ['owner','manager']],
        'deliv_supplier_late'     => ['plan' => 'pro',      'role' => ['owner','manager']],
        'deliv_seasonal_pattern'  => ['plan' => 'pro',      'role' => ['owner','manager']],
        'deliv_dead_stock'        => ['plan' => 'business', 'role' => ['owner']],
        // ... 40 темa
    ];
    $rule = $rules[$topic] ?? null;
    if (!$rule) return false;

    return planMeets($plan, $rule['plan'])
        && in_array($user['role'], $rule['role']);
}
```

Plan tiers: `free < start < pro < business`.

### 29.4 Confidence routing (от 06_anti_hallucination)

```php
function routeAISignal($topic, $confidence) {
    if ($confidence >= 0.85) return 'auto_show';     // показва се без потвърждение
    if ($confidence >= 0.50) return 'soft_show';     // показва с пометка "AI предполага"
    return 'block';                                  // не се показва
}
```

UI разлика:
```html
<!-- auto_show -->
<article class="deliv-ai-signal q-loss">
  <h4>Закъсняла доставка</h4>
  <p>Емпорио — 3 дни закъснение.</p>
</article>

<!-- soft_show -->
<article class="deliv-ai-signal q-loss soft">
  <span class="soft-pill">AI предполага</span>
  <h4>Възможно закъснение</h4>
  <p>Емпорио вероятно закъснява — потвърди.</p>
</article>
```

CSS soft:
```css
.deliv-ai-signal.soft { opacity: 0.85; border-style: dashed; }
.soft-pill {
  font-family: var(--font-mono); font-size: 9px;
  font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase;
  background: linear-gradient(135deg, hsl(280 30% 88%), hsl(305 30% 88%));
  color: hsl(280 60% 35%); padding: 2px 6px;
  border-radius: var(--radius-pill);
}
[data-theme="dark"] .soft-pill {
  background: linear-gradient(135deg, hsl(280 30% 18%), hsl(305 30% 18%));
  color: hsl(280 80% 80%);
}
```

### 29.5 Selection Engine (rule-based, не AI)

```php
function selectSignals($candidates, $max = 8) {
    // 1. Filter by plan + role
    $allowed = array_filter($candidates, fn($s) =>
        canSeeSignal($s['topic'], $user, $plan));

    // 2. Filter by cooldown 24h
    $afterCooldown = array_filter($allowed, fn($s) =>
        !inCooldown($s['topic'], $tenant_id));

    // 3. Filter by confidence
    $afterConf = array_filter($afterCooldown, fn($s) =>
        routeAISignal($s['topic'], $s['confidence']) !== 'block');

    // 4. Sort by priority + recency
    usort($afterConf, function($a, $b) {
        if ($a['priority'] !== $b['priority']) return $a['priority'] - $b['priority'];  // P0 first
        return strtotime($b['created_at']) - strtotime($a['created_at']);  // newest first
    });

    // 5. Take top N
    return array_slice($afterConf, 0, $max);
}
```

### 29.6 AI Signal Browser (5 категории)

Отделен screen достъпен от "Виж всички":

```html
<div class="signal-browser">
  <header class="sb-head">
    <h2>Всички сигнали</h2>
    <button class="sb-close">X</button>
  </header>

  <div class="sb-cats">
    <button class="sb-cat active">Всички 24</button>
    <button class="sb-cat">Продажби 8</button>
    <button class="sb-cat">Склад 6</button>
    <button class="sb-cat">Продукти 4</button>
    <button class="sb-cat">Финанси 3</button>
    <button class="sb-cat">Разходи 3</button>
  </div>

  <div class="sb-list">
    <!-- AI signals × N -->
  </div>
</div>
```

### 29.7 role_gate в DB

```sql
ALTER TABLE ai_insights
ADD COLUMN role_gate ENUM('owner','manager','seller','all') DEFAULT 'all';
ALTER TABLE ai_insights
ADD COLUMN action_label VARCHAR(64),
ADD COLUMN action_type ENUM('navigate','order_draft','dismiss','contact_supplier'),
ADD COLUMN action_url VARCHAR(256),
ADD COLUMN action_data JSON;
```

Action buttons за owner/manager only. Seller вижда signal но без action button.

### 29.8 Phased rollout

| Фаза | Beta % | AI vs template |
|---|---|---|
| Фаза 1 (бета launch) | 0% AI | 100% templates (controlled wording) |
| Фаза 2 (после 30 дни) | 30% AI | 70% template fallback |
| Фаза 3 (после 90 дни) | 80% AI | 20% template (only for legal/sensitive) |

Template structure (от 12_feedback_and_actions):
```php
$templates = [
    'deliv_overdue' => [
        'title_template' => 'Закъсняла доставка',
        'body_template' => '{supplier_name} — {days_late} дни закъснение. {historical_context}',
        'historical_context' => function($supplier_id) {
            $count = countLateDeliveries($supplier_id, 30);
            return $count > 1 ? "Това е $count-та закъсняла от тях този месец." : "";
        }
    ],
    // ... 40 теми
];
```

---

## КРАЙ НА ЧАСТ 4B

# ═══════════════════════════════════════
# ЧАСТ 5A — DETAIL НА ДОСТАВКА + ДОСТАВЧИК
# ═══════════════════════════════════════

## 30. DETAIL НА ДОСТАВКА (4 ТАБА)

### 30.1 Header

```html
<header class="bm-header dd-header">
  <button class="icon-btn" onclick="history.back()">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <polyline points="15 18 9 12 15 6"/>
    </svg>
  </button>
  <div class="dd-title-block">
    <strong class="dd-num">#DEL-2026-0234</strong>
    <button class="dd-supplier-link" onclick="openSupplier(42)">
      Емпорио ООД ›
    </button>
  </div>
  <button class="dd-status-pill q-amber" onclick="openStatusInfo()">
    ⏰ Чакаща
  </button>
  <button class="icon-btn" onclick="openMeatball()">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <circle cx="12" cy="12" r="1"/>
      <circle cx="19" cy="12" r="1"/>
      <circle cx="5" cy="12" r="1"/>
    </svg>
  </button>
</header>
```

CSS:
```css
.dd-title-block { flex: 1; }
.dd-num {
  display: block; font-family: var(--font-mono);
  font-size: 11px; font-weight: 800; letter-spacing: 0.06em;
  color: var(--text-muted);
}
.dd-supplier-link {
  display: block; font-size: 14px; font-weight: 800;
  color: var(--text); margin-top: 1px;
  text-align: left;
}
.dd-status-pill {
  font-family: var(--font-mono); font-size: 9.5px; font-weight: 800;
  letter-spacing: 0.04em; text-transform: uppercase;
  padding: 4px 10px; border-radius: var(--radius-pill);
  margin-right: 4px;
}
.dd-status-pill.q-amber {
  background: hsl(38 50% 92%); color: hsl(38 80% 35%);
}
.dd-status-pill.q-loss {
  background: hsl(0 50% 92%); color: hsl(0 70% 40%);
  animation: kpiPulse 2s ease-in-out infinite;
}
.dd-status-pill.q-gain {
  background: hsl(145 50% 92%); color: hsl(145 70% 30%);
}
[data-theme="dark"] .dd-status-pill.q-amber { background: hsl(38 30% 12%); color: hsl(38 80% 70%); }
[data-theme="dark"] .dd-status-pill.q-loss { background: hsl(0 30% 12%); color: hsl(0 80% 70%); }
[data-theme="dark"] .dd-status-pill.q-gain { background: hsl(145 30% 12%); color: hsl(145 70% 70%); }
```

### 30.2 Hero summary card (под header)

```html
<section class="dd-hero">
  <div class="dh-row">
    <div class="dh-cell">
      <span class="dh-label">Стойност</span>
      <strong class="dh-value">2 340.50 €</strong>
    </div>
    <div class="dh-cell">
      <span class="dh-label">Артикули</span>
      <strong class="dh-value">18</strong>
    </div>
    <div class="dh-cell">
      <span class="dh-label">Магазин</span>
      <strong class="dh-value">Витоша</strong>
    </div>
  </div>
  <div class="dh-meta">
    <span class="dh-meta-row">
      <svg><!-- calendar --></svg>
      Очаквана: <strong>10.05.2026</strong>
    </span>
    <span class="dh-meta-row">
      <svg><!-- order --></svg>
      Поръчка: <a class="dh-link">#789</a>
    </span>
  </div>
</section>
```

CSS:
```css
.dd-hero {
  margin: 12px 12px 0;
  padding: 14px;
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-card);
}
[data-theme="dark"] .dd-hero {
  background: hsl(220 25% 6% / 0.7); backdrop-filter: blur(8px);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}
.dh-row { display: flex; gap: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border-soft); }
.dh-cell { flex: 1; }
.dh-label {
  display: block; font-family: var(--font-mono);
  font-size: 9px; font-weight: 800; letter-spacing: 0.06em;
  text-transform: uppercase; color: var(--text-muted);
}
.dh-value {
  display: block; font-size: 18px; font-weight: 800;
  letter-spacing: -0.02em; color: var(--text); margin-top: 2px;
}
.dh-meta { padding-top: 12px; display: grid; gap: 6px; }
.dh-meta-row {
  display: flex; align-items: center; gap: 8px;
  font-size: 12px; color: var(--text-muted);
}
.dh-meta-row svg { width: 14px; height: 14px; opacity: 0.6; }
.dh-meta-row strong { color: var(--text); font-weight: 700; }
.dh-link { color: var(--accent); font-weight: 700; }
[data-theme="dark"] .dh-link { color: hsl(var(--hue1) 80% 75%); }
```

### 30.3 4 таба

```html
<nav class="dd-tabs" role="tablist">
  <button class="dd-tab active" data-tab="items">
    Артикули <span class="tab-badge">18</span>
  </button>
  <button class="dd-tab" data-tab="history">
    История
  </button>
  <button class="dd-tab" data-tab="finance">
    Финанси
  </button>
  <button class="dd-tab" data-tab="docs">
    Документи <span class="tab-badge">2</span>
  </button>
</nav>
```

CSS:
```css
.dd-tabs {
  display: flex;
  margin: 14px 12px 0;
  border-bottom: 1px solid var(--border-soft);
  position: sticky; top: 56px; z-index: 40;
  background: var(--bg-main);
}
[data-theme="dark"] .dd-tabs { background: hsl(220 25% 4.8% / 0.95); backdrop-filter: blur(20px); }
.dd-tab {
  flex: 1; padding: 12px 6px;
  font-size: 12px; font-weight: 700; color: var(--text-muted);
  position: relative;
  display: flex; align-items: center; justify-content: center; gap: 6px;
}
.dd-tab.active { color: var(--accent); font-weight: 800; }
[data-theme="dark"] .dd-tab.active { color: hsl(var(--hue1) 80% 75%); }
.dd-tab.active::after {
  content: ''; position: absolute; bottom: -1px; left: 20%; right: 20%;
  height: 2px; border-radius: 2px;
  background: linear-gradient(90deg, var(--accent), var(--accent-2));
}
.tab-badge {
  font-family: var(--font-mono); font-size: 9px;
  font-weight: 800; padding: 1px 6px;
  background: var(--bg-main); color: var(--text);
  border-radius: var(--radius-pill);
  box-shadow: var(--shadow-pressed);
}
```

### 30.4 TAB 1 — Артикули

```html
<section class="dd-tab-pane active" data-tab-pane="items">
  <div class="items-actions">
    <button class="ia-btn" onclick="openBulkSelect()">
      <svg><!-- check-square --></svg>
      Избери
    </button>
    <button class="ia-btn" onclick="openItemSearch()">
      <svg><!-- search --></svg>
      Търси
    </button>
    <button class="ia-btn" onclick="exportItems()">
      <svg><!-- export --></svg>
      Експорт
    </button>
  </div>

  <article class="dd-item" data-item-id="1">
    <div class="di-row">
      <span class="di-thumb">
        <img src="/img/products/123.jpg" alt=""/>
      </span>
      <div class="di-info">
        <strong class="di-name">Тениска синя L</strong>
        <span class="di-sku">SKU: ABC123</span>
      </div>
      <div class="di-qty">
        <span class="di-qty-num">10 бр</span>
        <span class="di-qty-vs">от 10 очаквани</span>
      </div>
    </div>
    <div class="di-row di-prices">
      <span class="di-price-cell">
        <small>Cost</small>
        <strong>12.50 €</strong>
      </span>
      <span class="di-price-cell">
        <small>Retail</small>
        <strong>29.90 €</strong>
      </span>
      <span class="di-price-cell q-gain">
        <small>Печалба</small>
        <strong>58%</strong>
      </span>
      <span class="di-price-cell">
        <small>Subtotal</small>
        <strong>125.00 €</strong>
      </span>
    </div>
  </article>
  <!-- ... -->
</section>
```

CSS:
```css
.items-actions {
  display: flex; gap: 8px; padding: 12px;
}
.ia-btn {
  flex: 1; height: 38px;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  background: var(--surface); box-shadow: var(--shadow-card-sm);
  border-radius: var(--radius-pill);
  font-size: 12px; font-weight: 700;
}
.ia-btn svg { width: 13px; height: 13px; }

.dd-item {
  margin: 0 12px 8px; padding: 10px 12px;
  background: var(--surface); border-radius: var(--radius);
  box-shadow: var(--shadow-card-sm);
}
[data-theme="dark"] .dd-item {
  background: hsl(220 25% 6% / 0.7); backdrop-filter: blur(8px);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}
.di-row { display: flex; gap: 10px; align-items: center; padding: 4px 0; }
.di-thumb {
  width: 40px; height: 40px; border-radius: 8px; overflow: hidden;
  background: var(--bg-main); flex-shrink: 0;
}
.di-thumb img { width: 100%; height: 100%; object-fit: cover; }
.di-info { flex: 1; min-width: 0; }
.di-name {
  display: block; font-size: 13px; font-weight: 800; color: var(--text);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.di-sku {
  display: block; font-family: var(--font-mono);
  font-size: 9px; font-weight: 700; color: var(--text-muted);
  letter-spacing: 0.04em; margin-top: 1px;
}
.di-qty { text-align: right; }
.di-qty-num {
  display: block; font-size: 13px; font-weight: 800; color: var(--text);
}
.di-qty-vs {
  display: block; font-family: var(--font-mono); font-size: 9px;
  font-weight: 700; color: var(--text-faint); letter-spacing: 0.04em;
}
.di-prices {
  border-top: 1px dashed var(--border-soft);
  margin-top: 6px; padding-top: 6px;
}
.di-price-cell {
  flex: 1; display: grid; gap: 1px;
}
.di-price-cell small {
  font-family: var(--font-mono); font-size: 8.5px; font-weight: 700;
  letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--text-faint);
}
.di-price-cell strong {
  font-family: var(--font-mono); font-size: 11px; font-weight: 800;
  color: var(--text);
}
.di-price-cell.q-gain strong { color: hsl(145 70% 40%); }
[data-theme="dark"] .di-price-cell.q-gain strong { color: hsl(145 70% 70%); }
```

#### Tap на item

→ Inline expand с edit controls (qty stepper, cost input, retail input, voice бутони). При промяна → audit запис.

#### Long-press на item

→ Bulk select mode (виж Част 5B §32).

### 30.5 TAB 2 — История (audit timeline)

```html
<section class="dd-tab-pane" data-tab-pane="history">
  <div class="timeline">
    <article class="tl-event">
      <span class="tl-dot q-magic"></span>
      <div class="tl-card">
        <div class="tl-time">10.05.2026 14:23</div>
        <strong class="tl-title">Доставка получена</strong>
        <p class="tl-body">Пешо потвърди 18 от 18 артикула. Lost demand закrit за 3 артикула.</p>
        <span class="tl-actor">Пешо · OCR</span>
      </div>
    </article>
    <article class="tl-event">
      <span class="tl-dot q-default"></span>
      <div class="tl-card">
        <div class="tl-time">08.05.2026 09:15</div>
        <strong class="tl-title">Снимка на фактура (OCR)</strong>
        <p class="tl-body">AI извлече 18 артикула с confidence 89%.</p>
        <span class="tl-actor">Пешо</span>
      </div>
    </article>
    <article class="tl-event">
      <span class="tl-dot q-amber"></span>
      <div class="tl-card">
        <div class="tl-time">07.05.2026 10:42</div>
        <strong class="tl-title">Закъснение разпознато</strong>
        <p class="tl-body">AI: очаквана 06.05, не е получена. Сигнал в Life Board.</p>
        <span class="tl-actor">AI · auto</span>
      </div>
    </article>
    <article class="tl-event">
      <span class="tl-dot q-default"></span>
      <div class="tl-card">
        <div class="tl-time">28.04.2026 16:30</div>
        <strong class="tl-title">Поръчка #789 изпратена</strong>
        <span class="tl-actor">Митко</span>
      </div>
    </article>
  </div>
</section>
```

CSS:
```css
.timeline { padding: 12px; position: relative; }
.timeline::before {
  content: ''; position: absolute;
  top: 24px; bottom: 24px; left: 22px;
  width: 2px; background: var(--border-soft);
}
.tl-event {
  display: flex; gap: 14px; align-items: flex-start;
  margin-bottom: 14px; position: relative;
}
.tl-dot {
  width: 14px; height: 14px; border-radius: 50%;
  margin-top: 6px; flex-shrink: 0;
  position: relative; z-index: 1;
  border: 3px solid var(--bg-main);
}
.tl-dot.q-magic { background: hsl(280 70% 55%); box-shadow: 0 0 0 2px hsl(280 70% 55% / 0.2); }
.tl-dot.q-default { background: var(--accent); box-shadow: 0 0 0 2px hsl(var(--hue1) 80% 50% / 0.2); }
.tl-dot.q-amber { background: hsl(38 90% 55%); box-shadow: 0 0 0 2px hsl(38 90% 55% / 0.2); }
.tl-dot.q-loss { background: hsl(0 70% 55%); box-shadow: 0 0 0 2px hsl(0 70% 55% / 0.2); }
.tl-card {
  flex: 1; padding: 10px 12px;
  background: var(--surface); border-radius: var(--radius);
  box-shadow: var(--shadow-card-sm);
}
[data-theme="dark"] .tl-card {
  background: hsl(220 25% 6% / 0.7);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}
.tl-time {
  font-family: var(--font-mono); font-size: 9px; font-weight: 700;
  letter-spacing: 0.06em; color: var(--text-faint); text-transform: uppercase;
}
.tl-title { display: block; font-size: 13px; font-weight: 800; margin-top: 2px; color: var(--text); }
.tl-body { font-size: 12px; color: var(--text-muted); margin-top: 4px; line-height: 1.4; }
.tl-actor {
  display: inline-block; font-family: var(--font-mono);
  font-size: 9px; font-weight: 700; letter-spacing: 0.04em;
  background: var(--bg-main); color: var(--text-muted);
  padding: 2px 6px; border-radius: var(--radius-pill);
  margin-top: 6px;
}
```

### 30.6 TAB 3 — Финанси

```html
<section class="dd-tab-pane" data-tab-pane="finance">
  <div class="fin-summary">
    <div class="fin-row">
      <span>Cost (без ДДС)</span>
      <strong>1 950.42 €</strong>
    </div>
    <div class="fin-row">
      <span>ДДС (20%)</span>
      <strong>390.08 €</strong>
    </div>
    <div class="fin-row total">
      <span>Общо</span>
      <strong>2 340.50 €</strong>
    </div>
    <div class="fin-row">
      <span>Очаквана retail стойност</span>
      <strong>5 380.00 €</strong>
    </div>
    <div class="fin-row q-gain">
      <span>Очаквана печалба</span>
      <strong>3 040 € (56%)</strong>
    </div>
  </div>

  <div class="fin-card">
    <h4>Условия плащане</h4>
    <p>15 дни от датата на доставка</p>
    <p class="fin-due">Падеж: <strong>25.05.2026</strong></p>
  </div>

  <div class="fin-card">
    <h4>Cost change</h4>
    <p class="warn">3 артикула с увеличение ≥10%</p>
    <button class="fin-detail-btn">Виж детайли →</button>
  </div>

  <div class="fin-card">
    <h4>Bulgarian dual pricing</h4>
    <p>До 08.08.2026: € + лв</p>
    <p class="dual-note">€ 2 340.50 = лв 4 575.95 (курс 1.95583)</p>
  </div>
</section>
```

CSS:
```css
.fin-summary, .fin-card {
  margin: 10px 12px; padding: 12px 14px;
  background: var(--surface); border-radius: var(--radius);
  box-shadow: var(--shadow-card-sm);
}
[data-theme="dark"] .fin-summary,
[data-theme="dark"] .fin-card {
  background: hsl(220 25% 6% / 0.7);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}
.fin-row {
  display: flex; justify-content: space-between; padding: 6px 0;
  font-size: 13px;
}
.fin-row.total {
  border-top: 1px solid var(--border-soft);
  margin-top: 4px; padding-top: 8px;
  font-size: 15px; font-weight: 800;
}
.fin-row strong { font-family: var(--font-mono); font-weight: 800; }
.fin-row.q-gain strong { color: hsl(145 70% 40%); }
[data-theme="dark"] .fin-row.q-gain strong { color: hsl(145 70% 70%); }
.fin-card h4 { font-size: 12px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.fin-card p { font-size: 13px; margin-top: 4px; }
.fin-card .warn { color: hsl(0 70% 50%); font-weight: 700; }
[data-theme="dark"] .fin-card .warn { color: hsl(0 80% 70%); }
.fin-due strong { color: hsl(38 80% 35%); }
[data-theme="dark"] .fin-due strong { color: hsl(38 80% 75%); }
.dual-note {
  font-family: var(--font-mono); font-size: 11px; color: var(--text-muted);
}
```

### 30.7 TAB 4 — Документи

```html
<section class="dd-tab-pane" data-tab-pane="docs">
  <article class="dd-doc">
    <span class="doc-thumb">
      <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
      </svg>
    </span>
    <div class="doc-info">
      <strong>Фактура.pdf</strong>
      <span>2.4 MB · OCR · 89% confidence</span>
    </div>
    <button class="doc-action"><svg><!-- view --></svg></button>
    <button class="doc-action"><svg><!-- download --></svg></button>
  </article>

  <article class="dd-doc">
    <span class="doc-thumb image">
      <img src="..." />
    </span>
    <div class="doc-info">
      <strong>Снимка фактура.jpg</strong>
      <span>1.8 MB · 08.05.2026</span>
    </div>
    <button class="doc-action"><svg><!-- view --></svg></button>
  </article>

  <button class="doc-add">
    + Прикачи документ
  </button>
</section>
```

CSS: doc card 60px height, thumb 44x44 q-default gradient (или image preview), info flex:1, actions row.

### 30.8 Footer actions (sticky)

```html
<footer class="dd-footer">
  <button class="df-secondary" onclick="openContactSupplier()">
    <svg><!-- phone --></svg>
    Свържи се
  </button>
  <button class="df-primary" onclick="finalizeDelivery()">
    Получи доставката
  </button>
</footer>
```

Когато status='received': бутонът "Получи доставката" сменя на "Експорт PDF / Печатай етикети".

### 30.9 Meatball menu (⋯)

Bottom sheet с 8 опции:

```html
<div class="meatball-menu">
  <button class="mm-opt"><svg/> Експорт PDF</button>
  <button class="mm-opt"><svg/> Печатай етикети</button>
  <button class="mm-opt"><svg/> Дублирай поръчка</button>
  <button class="mm-opt"><svg/> Раздели по магазини</button>
  <button class="mm-opt"><svg/> Прикачи документ</button>
  <button class="mm-opt"><svg/> Експорт CSV</button>
  <button class="mm-opt q-loss"><svg/> Откажи доставка</button>
  <button class="mm-opt q-loss admin"><svg/> Изтрий (admin)</button>
</div>
```

Destructive (Откажи / Изтрий) → confirm dialog със soft delete (status='cancelled', никога hard delete).

---

## 31. DETAIL НА ДОСТАВЧИК (естествен мост)

**ВАЖНО:** Detail на доставчик е **shared компонент** между deliveries.php и orders.php — `partials/supplier-detail.php`. Един единствен файл, без дубликация.

### 31.1 Header + Hero

```html
<header class="bm-header sd-header">
  <button class="icon-btn back"><svg/></button>
  <div class="sd-title-block">
    <strong class="sd-name">Емпорио ООД</strong>
    <span class="sd-tagline">Дамски дрехи · от 2024</span>
  </div>
  <button class="sd-call"><svg/></button>
  <button class="icon-btn"><svg/></button>  <!-- meatball -->
</header>

<section class="sd-hero">
  <div class="sd-kpi-grid">
    <div class="sd-kpi q-gain">
      <span class="kl">Reliability</span>
      <strong class="kv">94%</strong>
      <span class="kt">↑ 2% vs предн.</span>
    </div>
    <div class="sd-kpi">
      <span class="kl">Lead time avg</span>
      <strong class="kv">7.2 d</strong>
    </div>
    <div class="sd-kpi">
      <span class="kl">Доставки 90д</span>
      <strong class="kv">14</strong>
    </div>
    <div class="sd-kpi">
      <span class="kl">Стойност 90д</span>
      <strong class="kv">28 450 €</strong>
    </div>
  </div>
</section>
```

CSS — KPI grid 2x2:
```css
.sd-kpi-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.sd-kpi {
  padding: 12px; border-radius: var(--radius);
  background: var(--surface); box-shadow: var(--shadow-card-sm);
}
.sd-kpi.q-gain { border-left: 3px solid hsl(145 70% 50%); }
.kl {
  font-family: var(--font-mono); font-size: 9px;
  font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--text-muted);
}
.kv {
  display: block; font-size: 18px; font-weight: 800;
  letter-spacing: -0.02em; margin-top: 2px;
}
.kt {
  display: block; font-family: var(--font-mono); font-size: 9px;
  font-weight: 700; color: hsl(145 70% 45%); margin-top: 2px;
}
```

### 31.2 6 секции

| # | Секция | Съдържание |
|---|---|---|
| 1 | **Активни поръчки** | 2-3 sent/acked поръчки с CTA "Виж" / "Получи" |
| 2 | **AI препоръки** | "Поръчай 12 SKU за следващата доставка" + AI чернова |
| 3 | **Lost demand за тях** | Артикули които често липсват — "Винаги поръчвай +20%" |
| 4 | **История** | Доставки timeline (последни 30 дни) |
| 5 | **Условия & бележки** | Payment terms, contact, address, бележки |
| 6 | **Метрики** | Detailed scorecard (lateness avg, partial rate, cost trends) |

```html
<section class="sd-section">
  <h3 class="sd-sec-head">Активни поръчки <span class="sd-count">2</span></h3>
  <div class="sd-orders-list">
    <article class="sd-order-card">
      <strong>#789</strong>
      <span>20 артикула · 2 850 €</span>
      <span class="status-pill q-amber">Чакаща</span>
      <button class="sd-receive-btn">Получи →</button>
    </article>
    <!-- ... -->
  </div>
</section>

<section class="sd-section">
  <h3 class="sd-sec-head">AI препоръки</h3>
  <article class="deliv-ai-signal q-magic" data-topic="deliv_supplier_restock">
    <h4>Време за нова поръчка</h4>
    <p>След 14 доставки знам какво продаваш. Подготвих чернова.</p>
    <button class="signal-action primary">Виж черновата</button>
  </article>
</section>

<section class="sd-section">
  <h3 class="sd-sec-head">Lost demand <span class="sd-count">€420</span></h3>
  <article class="lost-item">
    <strong>Тениска синя L</strong>
    <span>Изпускаш €145/седм</span>
    <button>+20% при следваща</button>
  </article>
  <!-- ... -->
</section>

<section class="sd-section">
  <h3 class="sd-sec-head">История</h3>
  <div class="sd-history-mini">
    <button class="sd-deliv-row">
      <span class="sd-d-date">10.05</span>
      <span class="sd-d-num">#0234</span>
      <span class="sd-d-val">2 340 €</span>
      <span class="status-pill q-gain">Получена</span>
    </button>
    <!-- ... -->
  </div>
</section>

<section class="sd-section">
  <h3 class="sd-sec-head">Бележки</h3>
  <div class="sd-notes-card">
    <p class="sd-note">Иван (управител) — пристига всеки втори вторник</p>
    <p class="sd-note">Купонът -10% за поръчки >5000€</p>
    <button class="sd-note-add">+ Добави бележка</button>
  </div>
</section>

<section class="sd-section">
  <h3 class="sd-sec-head">Метрики</h3>
  <div class="sd-metrics">
    <div class="sd-metric-row">
      <span>Pristina rate</span>
      <strong>94%</strong>
      <small>13/14 доставки навреме</small>
    </div>
    <div class="sd-metric-row">
      <span>Partial rate</span>
      <strong>7%</strong>
      <small>1/14 частична</small>
    </div>
    <div class="sd-metric-row">
      <span>Cost stability</span>
      <strong>+3.2%</strong>
      <small>средно увеличение 90д</small>
    </div>
  </div>
</section>
```

### 31.3 Action footer

```html
<footer class="sd-footer">
  <button class="sf-secondary"><svg/> Поръчай</button>
  <button class="sf-primary"><svg/> Получи доставка</button>
</footer>
```

### 31.4 Meatball menu (на supplier)

- Експорт всички доставки
- AI анализ за този доставчик
- Промени payment terms
- Свържи се (call/email)
- Маркирай като неактивен
- Изтрий (admin)

---

## КРАЙ НА ЧАСТ 5A

# ═══════════════════════════════════════
# ЧАСТ 5A — ДЕТАЙЛИ: ДОСТАВКА + ДОСТАВЧИК
# ═══════════════════════════════════════

## 30. DETAIL НА ДОСТАВКА — 4 ТАБА

### 30.1 Header

```html
<header class="dd-head">
  <button class="dd-back" onclick="history.back()">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <polyline points="15 18 9 12 15 6"/>
    </svg>
  </button>
  <div class="dd-title">
    <span class="dd-num">#DEL-2026-0234</span>
    <span class="dd-supplier" onclick="goToSupplier(42)">
      Емпорио ООД
      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
        <polyline points="9 18 15 12 9 6"/>
      </svg>
    </span>
  </div>
  <button class="dd-meatball" onclick="openMeatball()">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <circle cx="12" cy="6" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="18" r="1.5"/>
    </svg>
  </button>
</header>

<!-- Hero status banner -->
<div class="dd-hero q-amber">
  <span class="dd-hero-orb">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
    </svg>
  </span>
  <div class="dd-hero-text">
    <strong>Чакаща</strong>
    <span>Очаквана 10.05.2026 · 18 артикула · 2 340.50 €</span>
  </div>
  <span class="dd-hero-pill">Поръчка #789 →</span>
</div>
```

### 30.2 CSS Header + Hero

```css
.dd-head {
  display: flex; align-items: center; gap: 10px;
  height: 56px; padding: 0 12px;
  position: sticky; top: 0; z-index: 50;
  background: var(--bg-main);
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
[data-theme="dark"] .dd-head {
  background: hsl(220 25% 4.8% / 0.85);
  backdrop-filter: blur(16px);
  box-shadow: none;
  border-bottom: 1px solid hsl(var(--hue2) 12% 14%);
}
.dd-back, .dd-meatball {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--surface); box-shadow: var(--shadow-card-sm);
  display: grid; place-items: center; flex-shrink: 0;
}
.dd-back svg, .dd-meatball svg { width: 14px; height: 14px; }
.dd-title { flex: 1; min-width: 0; }
.dd-num {
  display: block; font-family: var(--font-mono);
  font-size: 10px; font-weight: 800; letter-spacing: 0.06em;
  color: var(--text-muted);
}
.dd-supplier {
  display: flex; align-items: center; gap: 4px;
  font-size: 14px; font-weight: 800; color: var(--text);
  margin-top: 1px;
}
.dd-supplier svg { width: 10px; height: 10px; opacity: 0.5; }

/* Hero */
.dd-hero {
  display: flex; align-items: center; gap: 10px;
  margin: 12px;
  padding: 14px 14px;
  border-radius: var(--radius);
  background: var(--surface);
  box-shadow: var(--shadow-card);
  position: relative;
  border-left: 4px solid hsl(38 90% 55%);  /* hue accent */
}
.dd-hero.q-amber { border-left-color: hsl(38 90% 55%); }
.dd-hero.q-loss { border-left-color: hsl(0 70% 55%); }
.dd-hero.q-gain { border-left-color: hsl(145 70% 50%); }
[data-theme="dark"] .dd-hero {
  background: hsl(220 25% 6% / 0.7);
  backdrop-filter: blur(8px);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}

.dd-hero-orb {
  width: 44px; height: 44px; border-radius: var(--radius-icon);
  display: grid; place-items: center; flex-shrink: 0;
  position: relative; overflow: hidden;
}
.dd-hero.q-amber .dd-hero-orb {
  background: linear-gradient(135deg, hsl(38 90% 55%), hsl(28 85% 50%));
  box-shadow: 0 4px 12px hsl(38 90% 50% / 0.4);
}
.dd-hero.q-loss .dd-hero-orb {
  background: linear-gradient(135deg, hsl(0 70% 55%), hsl(15 70% 50%));
  box-shadow: 0 4px 12px hsl(0 70% 50% / 0.4);
}
.dd-hero.q-gain .dd-hero-orb {
  background: linear-gradient(135deg, hsl(145 70% 50%), hsl(155 70% 40%));
  box-shadow: 0 4px 12px hsl(145 70% 45% / 0.4);
}
.dd-hero-orb svg { width: 18px; height: 18px; position: relative; z-index: 1; }

.dd-hero-text { flex: 1; min-width: 0; }
.dd-hero-text strong {
  display: block; font-size: 15px; font-weight: 800;
  color: var(--text); letter-spacing: -0.01em;
}
.dd-hero-text span {
  display: block; font-family: var(--font-mono);
  font-size: 10px; font-weight: 700;
  color: var(--text-muted); letter-spacing: 0.04em;
  margin-top: 2px;
}

.dd-hero-pill {
  font-family: var(--font-mono); font-size: 9.5px; font-weight: 800;
  letter-spacing: 0.04em; text-transform: uppercase;
  background: var(--bg-main); color: var(--accent);
  padding: 6px 10px; border-radius: var(--radius-pill);
  box-shadow: var(--shadow-pressed);
  cursor: pointer;
}
```

### 30.3 Tab навигация

```html
<div class="dd-tabs" role="tablist">
  <button class="dd-tab active" data-tab="items">
    Артикули <span class="dd-tab-count">18</span>
  </button>
  <button class="dd-tab" data-tab="history">История</button>
  <button class="dd-tab" data-tab="finance">Финанси</button>
  <button class="dd-tab" data-tab="docs">
    Документи <span class="dd-tab-count">2</span>
  </button>
</div>
```

CSS:
```css
.dd-tabs {
  display: flex;
  border-bottom: 1px solid var(--border-soft);
  padding: 0 12px;
  position: sticky; top: 56px; z-index: 49;
  background: var(--bg-main);
}
[data-theme="dark"] .dd-tabs {
  background: hsl(220 25% 4.8% / 0.85);
  backdrop-filter: blur(16px);
}
.dd-tab {
  flex: 1; height: 44px;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  font-size: 12px; font-weight: 700;
  color: var(--text-muted);
  position: relative;
}
.dd-tab.active {
  color: var(--accent); font-weight: 800;
}
[data-theme="dark"] .dd-tab.active { color: hsl(var(--hue1) 80% 75%); }
.dd-tab.active::after {
  content: ''; position: absolute; bottom: 0;
  left: 25%; right: 25%; height: 2px;
  background: linear-gradient(90deg, var(--accent), var(--accent-2));
  border-radius: 2px 2px 0 0;
  animation: navIndicator 0.3s var(--ease-spring);
}
.dd-tab-count {
  font-family: var(--font-mono); font-size: 9px; font-weight: 800;
  background: var(--bg-main); color: var(--text-muted);
  padding: 1px 5px; border-radius: var(--radius-pill);
  box-shadow: var(--shadow-pressed);
}
.dd-tab.active .dd-tab-count {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  color: white; box-shadow: none;
}
```

### 30.4 TAB 1 — Артикули

```html
<div class="dd-tab-content" data-tab="items">
  <!-- AI signal in tab (ако има) -->
  <article class="deliv-ai-signal q-magic in-tab">
    <div class="signal-head">
      <span class="signal-orb"><!-- magic --></span>
      <div class="signal-text">
        <div class="signal-title">3 артикула с cost увеличение ≥10%</div>
      </div>
    </div>
    <p class="signal-body">Предложи преобновяване на retail чрез Smart Pricing.</p>
    <button class="signal-action primary">Виж 3 артикула</button>
  </article>

  <!-- Group: Артикули в доставката -->
  <h3 class="dd-group-h">Артикули в доставката</h3>

  <article class="dd-item" data-item-id="1">
    <div class="di-row main">
      <span class="di-thumb">
        <img src="..." alt=""/>
      </span>
      <div class="di-text">
        <strong class="di-name">Тениска синя L</strong>
        <span class="di-code">SKU: ABC123</span>
      </div>
      <span class="di-qty">×3</span>
    </div>
    <div class="di-row meta">
      <div class="di-pricing">
        <span class="dp-label">Cost</span>
        <strong class="dp-val">12.50 €</strong>
        <span class="dp-trend up">↑ 8%</span>
      </div>
      <div class="di-pricing">
        <span class="dp-label">Retail</span>
        <strong class="dp-val">29.90 €</strong>
        <span class="dp-margin">маrg 58%</span>
      </div>
      <div class="di-pricing total">
        <span class="dp-label">Общо</span>
        <strong class="dp-val">37.50 €</strong>
      </div>
    </div>
    <div class="di-row pills">
      <span class="dd-pill q-gain">✓ Получен</span>
      <span class="dd-pill q-default">+0.40 conf</span>  <!-- HIDDEN от Митко -->
    </div>
  </article>

  <!-- ... 18 items -->

  <!-- Sticky stats footer for items tab -->
  <div class="dd-items-summary">
    <div class="di-sum-row">
      <span>Общо артикули:</span><strong>18</strong>
    </div>
    <div class="di-sum-row">
      <span>Стойност доставка:</span><strong>2 340.50 €</strong>
    </div>
    <div class="di-sum-row warn">
      <span>Cost увеличения ≥10%:</span><strong>3 артикула</strong>
    </div>
    <div class="di-sum-row gain">
      <span>Lost demand resolved:</span><strong>3 артикула · €420</strong>
    </div>
  </div>
</div>
```

CSS:
```css
.dd-item {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-card-sm);
  padding: 12px;
  margin-bottom: 8px;
}
[data-theme="dark"] .dd-item {
  background: hsl(220 25% 6% / 0.7);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}

.di-row { display: flex; align-items: center; gap: 10px; }
.di-row.main { margin-bottom: 8px; }
.di-row.meta {
  padding: 8px 0;
  border-top: 1px solid var(--border-soft);
  border-bottom: 1px solid var(--border-soft);
}
.di-row.pills { gap: 6px; flex-wrap: wrap; padding-top: 8px; }

.di-thumb {
  width: 40px; height: 40px; border-radius: var(--radius-icon);
  background: var(--bg-main); flex-shrink: 0;
  overflow: hidden;
}
.di-thumb img { width: 100%; height: 100%; object-fit: cover; }
.di-text { flex: 1; min-width: 0; }
.di-name {
  display: block; font-size: 13px; font-weight: 800;
  color: var(--text); overflow: hidden;
  text-overflow: ellipsis; white-space: nowrap;
}
.di-code {
  display: block; font-family: var(--font-mono);
  font-size: 9.5px; font-weight: 700; color: var(--text-faint);
  letter-spacing: 0.04em; margin-top: 2px;
}
.di-qty {
  font-family: var(--font-mono); font-size: 14px; font-weight: 800;
  color: var(--text); flex-shrink: 0;
}

.di-pricing {
  flex: 1; display: grid; gap: 1px;
}
.di-pricing.total { text-align: right; }
.dp-label {
  font-family: var(--font-mono); font-size: 8.5px; font-weight: 700;
  letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--text-muted);
}
.dp-val {
  font-size: 13px; font-weight: 800; color: var(--text);
}
.di-pricing.total .dp-val { color: var(--accent); }
[data-theme="dark"] .di-pricing.total .dp-val { color: hsl(var(--hue1) 80% 75%); }
.dp-trend {
  font-family: var(--font-mono); font-size: 9.5px; font-weight: 700;
}
.dp-trend.up { color: hsl(0 70% 50%); }
.dp-trend.down { color: hsl(145 70% 45%); }
.dp-margin {
  font-family: var(--font-mono); font-size: 9.5px; font-weight: 700;
  color: hsl(145 70% 45%);
}

.dd-pill {
  font-family: var(--font-mono); font-size: 9px; font-weight: 800;
  letter-spacing: 0.04em; text-transform: uppercase;
  padding: 3px 7px; border-radius: var(--radius-pill);
}
.dd-pill.q-gain { background: hsl(145 50% 92%); color: hsl(145 70% 30%); }
.dd-pill.q-loss { background: hsl(0 50% 92%); color: hsl(0 70% 40%); }
.dd-pill.q-default { background: var(--bg-main); color: var(--text-muted); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .dd-pill.q-gain { background: hsl(145 30% 12%); color: hsl(145 70% 70%); }
[data-theme="dark"] .dd-pill.q-loss { background: hsl(0 30% 12%); color: hsl(0 80% 70%); }

.dd-items-summary {
  margin-top: 16px; padding: 14px;
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-card-sm);
  display: grid; gap: 6px;
}
[data-theme="dark"] .dd-items-summary {
  background: hsl(220 25% 6% / 0.7);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}
.di-sum-row {
  display: flex; justify-content: space-between;
  font-size: 12px;
}
.di-sum-row span { color: var(--text-muted); }
.di-sum-row strong { font-weight: 800; color: var(--text); }
.di-sum-row.warn strong { color: hsl(0 70% 50%); }
.di-sum-row.gain strong { color: hsl(145 70% 45%); }
```

**Закон №6 (Hidden Inventory) ВАЖНО:** `+0.40 conf` pill **никога не се показва** на Митко в production! Тук е документиран само за разработчика. В UI: `if ($_SESSION['user_role'] === 'developer') showConfPill();`

### 30.5 TAB 2 — История (audit log)

```html
<div class="dd-tab-content" data-tab="history">
  <div class="dd-timeline">
    <article class="tl-event">
      <span class="tl-dot q-magic"></span>
      <div class="tl-text">
        <strong class="tl-title">Доставка създадена (OCR)</strong>
        <span class="tl-meta">Пешо · 09.05.2026 14:23</span>
        <span class="tl-detail">Confidence: 87% · 18 артикула извлечени</span>
      </div>
    </article>
    <article class="tl-event">
      <span class="tl-dot q-amber"></span>
      <div class="tl-text">
        <strong class="tl-title">Линкната към поръчка #789</strong>
        <span class="tl-meta">Auto · 09.05.2026 14:23</span>
      </div>
    </article>
    <article class="tl-event">
      <span class="tl-dot q-default"></span>
      <div class="tl-text">
        <strong class="tl-title">Промяна на цена</strong>
        <span class="tl-meta">Бил · 09.05.2026 15:14</span>
        <span class="tl-detail">Тениска синя L: cost 11.50 → 12.50 €</span>
      </div>
    </article>
    <article class="tl-event">
      <span class="tl-dot q-gain"></span>
      <div class="tl-text">
        <strong class="tl-title">Финализирана</strong>
        <span class="tl-meta">Бил · 09.05.2026 15:18</span>
        <span class="tl-detail">+ Inventory updated · Lost demand closed (3 items, €420)</span>
      </div>
    </article>
  </div>
</div>
```

CSS:
```css
.dd-timeline {
  position: relative;
  padding-left: 24px;
}
.dd-timeline::before {
  content: ''; position: absolute;
  left: 11px; top: 8px; bottom: 8px;
  width: 2px;
  background: var(--border-soft);
}
.tl-event {
  position: relative;
  padding: 10px 0 10px 18px;
}
.tl-dot {
  position: absolute; left: -19px; top: 14px;
  width: 14px; height: 14px;
  border-radius: 50%;
  border: 3px solid var(--bg-main);
  box-shadow: 0 0 0 2px var(--border-soft);
}
.tl-dot.q-magic { background: hsl(280 70% 55%); }
.tl-dot.q-loss { background: hsl(0 70% 55%); }
.tl-dot.q-gain { background: hsl(145 70% 50%); }
.tl-dot.q-amber { background: hsl(38 90% 55%); }
.tl-dot.q-default { background: var(--accent); }

.tl-title { display: block; font-size: 13px; font-weight: 800; color: var(--text); }
.tl-meta {
  display: block; font-family: var(--font-mono);
  font-size: 9.5px; font-weight: 700;
  letter-spacing: 0.04em; color: var(--text-muted);
  margin-top: 2px;
}
.tl-detail { display: block; font-size: 12px; color: var(--text-muted); margin-top: 4px; }
```

**Audit log = append-only:**
```sql
CREATE TABLE delivery_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    delivery_id INT NOT NULL,
    event_type ENUM('created','linked_to_order','price_changed','quantity_changed',
                    'item_added','item_removed','status_changed','finalized',
                    'cancelled','split','note_added'),
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payload JSON,  -- snapshot на промяната
    INDEX idx_delivery_events (delivery_id, created_at)
);
```

Никога не се изтриват редове. Никога не се UPDATE-ват.

### 30.6 TAB 3 — Финанси

```html
<div class="dd-tab-content" data-tab="finance">
  <!-- Big totals card -->
  <article class="dd-totals-card">
    <div class="tot-row hero">
      <span>Общо стойност доставка</span>
      <strong class="tot-val">2 340.50 €</strong>
    </div>
    <div class="tot-row">
      <span>ДДС 20%</span>
      <span>390.08 €</span>
    </div>
    <div class="tot-row">
      <span>Без ДДС</span>
      <span>1 950.42 €</span>
    </div>
  </article>

  <!-- Cost changes section -->
  <h3 class="dd-group-h">Cost промени</h3>
  <article class="dd-cost-change-list">
    <div class="cc-row up">
      <span class="cc-name">Тениска синя L</span>
      <span class="cc-from">11.50 €</span>
      <span class="cc-arrow">→</span>
      <span class="cc-to">12.50 €</span>
      <span class="cc-pct">+8.7%</span>
    </div>
    <div class="cc-row up warn">
      <span class="cc-name">Чанта черна</span>
      <span class="cc-from">22.00 €</span>
      <span class="cc-arrow">→</span>
      <span class="cc-to">26.00 €</span>
      <span class="cc-pct">+18.2% ⚠</span>
    </div>
    <div class="cc-row down">
      <span class="cc-name">Шапка</span>
      <span class="cc-from">8.50 €</span>
      <span class="cc-arrow">→</span>
      <span class="cc-to">7.80 €</span>
      <span class="cc-pct">−8.2%</span>
    </div>
  </article>

  <!-- Margin overview -->
  <h3 class="dd-group-h">Маржове</h3>
  <article class="dd-margin-summary">
    <div class="ms-row">
      <span>Среден марж:</span>
      <strong>52.8%</strong>
    </div>
    <div class="ms-row">
      <span>Най-нисък марж:</span>
      <strong class="warn">28% (Чанта черна)</strong>
    </div>
    <div class="ms-row">
      <span>Под минимум 25%:</span>
      <strong class="warn">0 артикула</strong>
    </div>
  </article>

  <!-- Payment terms (post-beta) -->
  <h3 class="dd-group-h">Плащане</h3>
  <article class="dd-payment">
    <div class="pay-row">
      <span>Условия:</span>
      <strong>30 дни</strong>
    </div>
    <div class="pay-row">
      <span>Краен срок:</span>
      <strong>09.06.2026</strong>
    </div>
    <div class="pay-row pending">
      <span>Статус:</span>
      <strong class="q-amber-text">Чакаща</strong>
    </div>
    <button class="pay-mark-paid">Маркирай като платена</button>
  </article>
</div>
```

CSS:
```css
.dd-totals-card {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-card);
  padding: 14px;
  margin-bottom: 12px;
}
.tot-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 6px 0;
  font-size: 12px;
}
.tot-row.hero {
  border-bottom: 2px solid var(--border-soft);
  padding-bottom: 10px; margin-bottom: 8px;
}
.tot-row.hero span { font-size: 13px; font-weight: 700; color: var(--text); }
.tot-val { font-size: 22px; font-weight: 800; color: var(--accent); letter-spacing: -0.02em; }

.cc-row {
  display: grid;
  grid-template-columns: 1fr auto auto auto auto;
  gap: 8px; align-items: center;
  padding: 10px 14px;
  background: var(--surface);
  border-radius: var(--radius);
  margin-bottom: 6px;
  font-size: 12px;
}
.cc-name { font-weight: 700; }
.cc-from { color: var(--text-muted); text-decoration: line-through; opacity: 0.7; }
.cc-arrow { color: var(--text-faint); }
.cc-to { font-weight: 800; }
.cc-pct {
  font-family: var(--font-mono); font-size: 11px; font-weight: 800;
  padding: 2px 6px; border-radius: var(--radius-pill);
}
.cc-row.up .cc-pct { background: hsl(0 50% 92%); color: hsl(0 70% 40%); }
.cc-row.up.warn .cc-pct { background: hsl(0 80% 90%); color: hsl(0 80% 30%); }
.cc-row.down .cc-pct { background: hsl(145 50% 92%); color: hsl(145 70% 30%); }
[data-theme="dark"] .cc-row.up .cc-pct { background: hsl(0 30% 14%); color: hsl(0 80% 70%); }
[data-theme="dark"] .cc-row.down .cc-pct { background: hsl(145 30% 14%); color: hsl(145 70% 70%); }
```

### 30.7 TAB 4 — Документи

```html
<div class="dd-tab-content" data-tab="docs">
  <article class="dd-doc-card">
    <span class="doc-thumb">
      <img src="/api/scanner_documents/123/preview" alt=""/>
    </span>
    <div class="doc-text">
      <strong>Фактура_Емпорио.pdf</strong>
      <span>OCR scan · Confidence 87% · 09.05 14:23</span>
    </div>
    <button class="doc-download">
      <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
    </button>
  </article>

  <article class="dd-doc-card">
    <span class="doc-thumb"><!-- email icon --></span>
    <div class="doc-text">
      <strong>email_emporio_2026-05-09.eml</strong>
      <span>Email forward · 09.05 14:18</span>
    </div>
    <button class="doc-download"><!-- download --></button>
  </article>

  <button class="dd-doc-add">
    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    Добави документ
  </button>
</div>
```

### 30.8 Footer actions (sticky)

Различни actions според статус:

| Status | Footer button(s) |
|---|---|
| `sent`/`acked` | [Получи доставката] |
| `partial` | [Получи остатъка] [Затвори частично] |
| `received` | [Поръчай липси] (ако e-shop има back-orders) |
| `overdue` | [Получи сега] [Отмени поръчката] |
| `cancelled` | (none) |

```html
<footer class="dd-foot">
  <button class="dd-action primary" onclick="receiveDelivery()">
    Получи доставката
  </button>
  <button class="dd-action secondary" onclick="splitToStores()">
    Раздели по магазини
  </button>
</footer>
```

CSS:
```css
.dd-foot {
  position: sticky; bottom: 0;
  padding: 12px 14px calc(12px + env(safe-area-inset-bottom, 0));
  background: var(--bg-main);
  border-top: 1px solid var(--border-soft);
  display: grid; gap: 8px;
}
.dd-action {
  height: 48px; border-radius: var(--radius);
  font-size: 14px; font-weight: 800; letter-spacing: -0.01em;
}
.dd-action.primary {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  color: white;
  box-shadow: 0 6px 20px hsl(var(--hue1) 80% 50% / 0.4);
  position: relative; overflow: hidden;
}
.dd-action.primary::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.3) 85%, transparent 100%);
  animation: conicSpin 5s linear infinite;
}
.dd-action.secondary {
  background: var(--bg-main); color: var(--text);
  box-shadow: var(--shadow-pressed);
}
```

### 30.9 Meatball menu (⋯)

```html
<div class="meatball-popup">
  <button class="mb-opt"><svg/> Дублирай доставка</button>
  <button class="mb-opt"><svg/> Експорт PDF</button>
  <button class="mb-opt"><svg/> Експорт Excel</button>
  <button class="mb-opt"><svg/> Печат етикети</button>
  <button class="mb-opt"><svg/> Прехвърли към друг магазин</button>
  <button class="mb-opt danger"><svg/> Откажи доставката</button>
  <button class="mb-opt danger"><svg/> Изтрий (admin only)</button>
</div>
```

`Откажи` → confirm dialog → status='cancelled'. `Изтрий` → недостъпно за non-admin. Само soft delete (`is_deleted=1`); никога hard DELETE.

---

## 31. DETAIL НА ДОСТАВЧИК (SHARED COMPONENT)

### 31.1 Принцип

`partials/supplier-detail.php` се ползва И в `deliveries.php`, И в `orders.php`, И в `suppliers.php` — един компонент, същия UI.

Достъпен от:
- Tap на supplier name в delivery detail header
- Tap на supplier card в orders.php
- Direct: suppliers.php?id=42

### 31.2 Header + Hero

```html
<header class="sd-head"><!-- back, title, meatball --></header>

<div class="sd-hero q-default">
  <span class="sd-hero-orb">
    <svg viewBox="0 0 24 24" stroke="white" fill="none" stroke-width="2">
      <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
      <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
    </svg>
  </span>
  <div class="sd-hero-text">
    <strong>Емпорио ООД</strong>
    <span>BG203456789 · доставчик от 2024</span>
  </div>
  <span class="sd-reliability good">Reliability 92%</span>
</div>
```

### 31.3 KPI grid 2x2

```html
<div class="sd-kpi-grid">
  <div class="sd-kpi q-gain">
    <span class="sd-kpi-label">Получено</span>
    <strong class="sd-kpi-val">42</strong>
    <span class="sd-kpi-sub">доставки</span>
  </div>
  <div class="sd-kpi q-default">
    <span class="sd-kpi-label">Стойност</span>
    <strong class="sd-kpi-val">38 240 €</strong>
    <span class="sd-kpi-sub">общо · 12 мес</span>
  </div>
  <div class="sd-kpi q-amber">
    <span class="sd-kpi-label">Lead time</span>
    <strong class="sd-kpi-val">8.2</strong>
    <span class="sd-kpi-sub">дни ср.</span>
  </div>
  <div class="sd-kpi q-loss">
    <span class="sd-kpi-label">Закъснения</span>
    <strong class="sd-kpi-val">3</strong>
    <span class="sd-kpi-sub">от последни 12</span>
  </div>
</div>
```

### 31.4 6 секции (collapsible)

| Секция | Съдържание |
|---|---|
| 1. **Активни поръчки** | Cards на pending orders → tap → orders.php?id=X |
| 2. **AI препоръки** | "Поръчай тениски — продават се добре" / "Намали поръчките за чанти" |
| 3. **Lost demand тук** | Top 5 артикула с висок lost demand at this supplier → CTA "Поръчай" |
| 4. **История доставки** | Last 12 deliveries → tap → deliveries.php?id=X |
| 5. **Бележки** | Свободни бележки от Митко (за payment, contact, fragile, etc.) |
| 6. **Контакти** | Phone, email, address, working hours (post-beta CRM) |

```html
<details class="sd-section" open>
  <summary>Активни поръчки <span class="sec-count">3</span></summary>
  <div class="sec-content">
    <article class="mini-order-card">
      <strong>#789 — 28.04.2026</strong>
      <span>20 артикула · 2 850 €</span>
      <span class="status-pill q-amber">Чакаща</span>
    </article>
    <!-- ... -->
  </div>
</details>

<details class="sd-section">
  <summary>AI препоръки <span class="sec-count">2</span></summary>
  <div class="sec-content">
    <article class="deliv-ai-signal q-magic in-supplier">
      <h4>Поръчай тениски синя L</h4>
      <p>Продаваш по 8/седмица. Имаш 12 в склад. Lead time 8 дни.</p>
      <button class="signal-action primary">Добави към чернова</button>
    </article>
    <!-- ... -->
  </div>
</details>

<details class="sd-section">
  <summary>Lost demand <span class="sec-count">€420</span></summary>
  <div class="sec-content">
    <article class="lost-demand-row">
      <strong>Тениска синя L</strong>
      <span>3 пъти търсена · €420 загуба</span>
      <button class="ld-cta">Поръчай</button>
    </article>
  </div>
</details>

<details class="sd-section">
  <summary>История доставки <span class="sec-count">42</span></summary>
  <!-- supplier-filtered list of deliveries -->
</details>

<details class="sd-section">
  <summary>Бележки</summary>
  <textarea class="sd-notes" placeholder="Свободни бележки..."></textarea>
  <button class="sd-notes-save">Запази</button>
</details>

<details class="sd-section">
  <summary>Контакти</summary>
  <div class="sd-contact-list">
    <a class="contact-row tel" href="tel:+359...">📞 +359 88 8888 888</a>
    <a class="contact-row email" href="mailto:...">✉ contact@emporio.bg</a>
    <div class="contact-row addr">📍 София, ул. Пример 12</div>
  </div>
</details>
```

CSS:
```css
.sd-kpi-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px; padding: 12px;
}
.sd-kpi {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-card-sm);
  padding: 12px 10px;
  border-left: 3px solid var(--accent);
  display: grid; gap: 1px;
}
.sd-kpi.q-gain { border-left-color: hsl(145 70% 50%); }
.sd-kpi.q-amber { border-left-color: hsl(38 90% 55%); }
.sd-kpi.q-loss { border-left-color: hsl(0 70% 55%); }

.sd-kpi-label {
  font-family: var(--font-mono); font-size: 9px;
  font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--text-muted);
}
.sd-kpi-val {
  font-size: 22px; font-weight: 800; color: var(--text);
  letter-spacing: -0.02em;
}
.sd-kpi.q-loss .sd-kpi-val { color: hsl(0 70% 50%); }
[data-theme="dark"] .sd-kpi.q-loss .sd-kpi-val { color: hsl(0 80% 70%); }
.sd-kpi-sub { font-size: 9.5px; color: var(--text-faint); font-weight: 600; }

.sd-section {
  border-bottom: 1px solid var(--border-soft);
}
.sd-section summary {
  display: flex; align-items: center; gap: 8px;
  padding: 14px 16px;
  font-size: 13px; font-weight: 800;
  cursor: pointer; list-style: none;
}
.sd-section summary::-webkit-details-marker { display: none; }
.sec-count {
  margin-left: auto;
  font-family: var(--font-mono); font-size: 9.5px; font-weight: 700;
  color: var(--accent);
}
.sd-section[open] summary::after { content: '−'; font-size: 18px; color: var(--text-muted); }
.sd-section:not([open]) summary::after { content: '+'; font-size: 18px; color: var(--text-muted); }
.sec-content { padding: 0 16px 14px; }

.sd-reliability {
  font-family: var(--font-mono); font-size: 9.5px; font-weight: 800;
  letter-spacing: 0.04em; text-transform: uppercase;
  padding: 4px 8px; border-radius: var(--radius-pill);
  margin-left: auto;
}
.sd-reliability.good { background: hsl(145 50% 92%); color: hsl(145 70% 30%); }
.sd-reliability.warn { background: hsl(38 50% 92%); color: hsl(38 80% 35%); }
.sd-reliability.bad { background: hsl(0 50% 92%); color: hsl(0 70% 40%); }
```

### 31.5 Footer actions

```html
<footer class="sd-foot">
  <button class="sd-action primary">Поръчай</button>
  <button class="sd-action secondary">Виж доставки</button>
</footer>
```

`Поръчай` → orders.php?action=create&supplier_id=42
`Виж доставки` → deliveries.php?supplier_id=42&view=by_supplier

### 31.6 Meatball на supplier

```
⋯ → [Експорт history PDF]
    [Изпрати email]
    [Архивирай доставчик]
    [Слей с друг доставчик] (admin only)
```

---

## КРАЙ НА ЧАСТ 5A

# ═══════════════════════════════════════
# ЧАСТ 5B — МЕНЮ + BULK + AUDIT + NOTIFY
# ═══════════════════════════════════════

## 32. МЕНЮ (☰) — 6 СЕКЦИИ

### 32.1 Достъп

Tap на ⚙ икона в header (3-та икона след search и voice). Side-sheet от right edge — 92vw mobile, max 460px. Същия `.gs-sheet` pattern като filter drawer (но right side).

### 32.2 Структура

```html
<div class="menu-drawer" id="menuDrawer">
  <header class="md-head">
    <h2>Меню</h2>
    <button class="md-close">X</button>
  </header>

  <div class="md-body">
    <!-- Section 1: Изгледи -->
    <details class="md-section" open>
      <summary><svg/> Изгледи</summary>
      <div class="md-content">
        <button class="md-row" onclick="setView('date')">📅 По дата</button>
        <button class="md-row" onclick="setView('supplier')">🏢 По доставчик</button>
        <button class="md-row" onclick="setView('status')">🚦 По статус</button>
        <button class="md-row" onclick="setView('calendar')">📆 Календар</button>
        <button class="md-row" onclick="setView('q1q6')">❓ По 6 въпроса</button>
      </div>
    </details>

    <!-- Section 2: Създай -->
    <details class="md-section" open>
      <summary><svg/> Създай</summary>
      <div class="md-content">
        <button class="md-row primary" onclick="openReceiveSheet()">
          + Получи доставка
        </button>
        <button class="md-row" onclick="goToOrders('create')">
          + Нова поръчка
        </button>
        <button class="md-row" onclick="goToSuppliers('create')">
          + Нов доставчик
        </button>
        <button class="md-row" onclick="duplicateLastDelivery()">
          ↻ Дублирай последната доставка
        </button>
      </div>
    </details>

    <!-- Section 3: Справки -->
    <details class="md-section">
      <summary><svg/> Справки</summary>
      <div class="md-content">
        <button class="md-row" onclick="goToStats('cost_trends')">📈 Cost тенденции</button>
        <button class="md-row" onclick="goToStats('supplier_scorecard')">⭐ Supplier scorecard</button>
        <button class="md-row" onclick="goToStats('lead_time')">⏱ Lead time анализ</button>
        <button class="md-row" onclick="goToStats('lost_demand_resolved')">✓ Lost demand ROI</button>
        <button class="md-row" onclick="goToStats('seasonal')">🍂 Сезонни доставки</button>
        <button class="md-row" onclick="goToStats('payment_terms')">💳 Payment terms</button>
      </div>
    </details>

    <!-- Section 4: Експорт -->
    <details class="md-section">
      <summary><svg/> Експорт</summary>
      <div class="md-content">
        <button class="md-row" onclick="exportPDF('current_filter')">📄 PDF (текущ filter)</button>
        <button class="md-row" onclick="exportExcel('current_filter')">📊 Excel</button>
        <button class="md-row" onclick="exportCSV('current_filter')">📋 CSV</button>
        <button class="md-row" onclick="emailReport()">✉ Email report</button>
        <button class="md-row" onclick="exportXBRLForVAT()">🧾 XBRL за ДДС (post-beta)</button>
      </div>
    </details>

    <!-- Section 5: Настройки -->
    <details class="md-section">
      <summary><svg/> Настройки</summary>
      <div class="md-content">
        <button class="md-row" onclick="openSettings('pricing_rules')">💰 Ценова стратегия</button>
        <button class="md-row" onclick="openSettings('default_supplier')">🏢 Default доставчици</button>
        <button class="md-row" onclick="openSettings('notifications')">🔔 Известия</button>
        <button class="md-row" onclick="openSettings('label_printer')">🖨 Етикетен printer</button>
        <button class="md-row" onclick="openSettings('email_forward')">✉ Email forward setup</button>
        <button class="md-row" onclick="openSettings('multi_store')">🏪 Multi-store split правила</button>
        <button class="md-row" onclick="rmsToggleTheme()">🌗 Тема (тъмна/светла)</button>
      </div>
    </details>

    <!-- Section 6: Помощ -->
    <details class="md-section">
      <summary><svg/> Помощ</summary>
      <div class="md-content">
        <button class="md-row" onclick="openTutorial('deliveries')">🎓 Tutorial</button>
        <button class="md-row" onclick="openFAQ('deliveries')">❓ Често задавани въпроси</button>
        <button class="md-row" onclick="openAIChat('deliveries_help')">💬 Питай AI</button>
        <button class="md-row" onclick="openVideoGuide('deliveries')">🎥 Видео гайд</button>
        <button class="md-row" onclick="contactSupport()">📧 Контакт с поддръжка</button>
      </div>
    </details>
  </div>
</div>
```

### 32.3 CSS

```css
.menu-drawer {
  position: fixed; top: 0; right: 0; bottom: 0;
  width: 92vw; max-width: 460px;
  background: var(--bg-main);
  z-index: 150;
  transform: translateX(100%);
  transition: transform 0.32s var(--ease-spring);
  display: flex; flex-direction: column;
  box-shadow: -8px 0 32px rgba(0,0,0,0.16);
}
.menu-drawer.open { transform: translateX(0); }

.md-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px;
  border-bottom: 1px solid var(--border-soft);
}
.md-head h2 { font-size: 17px; font-weight: 800; }

.md-body { flex: 1; overflow-y: auto; }
.md-section { border-bottom: 1px solid var(--border-soft); }
.md-section summary {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 16px;
  font-size: 13px; font-weight: 800;
  cursor: pointer; list-style: none;
}
.md-section summary::-webkit-details-marker { display: none; }
.md-section[open] summary::after { content: '−'; margin-left: auto; font-size: 18px; }
.md-section:not([open]) summary::after { content: '+'; margin-left: auto; font-size: 18px; }

.md-row {
  display: flex; align-items: center; gap: 10px;
  width: 100%; padding: 12px 16px 12px 32px;
  font-size: 13px; font-weight: 600; color: var(--text);
  text-align: left;
  transition: background 0.15s var(--ease);
}
.md-row:active { background: var(--surface); }
.md-row.primary {
  font-weight: 800; color: var(--accent);
}
[data-theme="dark"] .md-row.primary { color: hsl(var(--hue1) 80% 75%); }
```

### 32.4 Mapping секции от ORDERS_DESIGN_LOGIC §10

| Меню секция | Източник |
|---|---|
| Изгледи | View modes от §24 |
| Създай | Quick actions |
| Справки | stats.php deep links |
| Експорт | Export library (PDF/Excel/CSV/XBRL) |
| Настройки | settings.php deep links |
| Помощ | help.php / AI chat / video |

---

## 33. BULK ОПЕРАЦИИ

### 33.1 Активиране

**Long-press** на която и да е delivery card в главния списък → влиза в bulk select mode. Same pattern като в ORDERS_DESIGN_LOGIC §11.

```javascript
let pressTimer;
document.querySelectorAll('.deliv-card').forEach(card => {
  card.addEventListener('touchstart', e => {
    pressTimer = setTimeout(() => enterBulkMode(card), 500);  // 500ms threshold
  });
  card.addEventListener('touchend', () => clearTimeout(pressTimer));
  card.addEventListener('touchmove', () => clearTimeout(pressTimer));
});
```

### 33.2 Bulk header (replaces normal header)

```html
<header class="bulk-head">
  <button class="bulk-cancel" onclick="exitBulkMode()">
    <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  </button>
  <span class="bulk-count">3 избрани</span>
  <button class="bulk-select-all" onclick="selectAllVisible()">Всички</button>
</header>
```

CSS:
```css
.bulk-head {
  display: flex; align-items: center; gap: 12px;
  height: 56px; padding: 0 12px;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  position: sticky; top: 0; z-index: 50;
  position: relative; overflow: hidden;
}
.bulk-head::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.3) 85%, transparent 100%);
  animation: conicSpin 5s linear infinite;
}
.bulk-head > * { position: relative; z-index: 5; }
.bulk-cancel, .bulk-select-all {
  width: 32px; height: 32px; border-radius: 50%;
  background: rgba(255,255,255,0.2); backdrop-filter: blur(8px);
  display: grid; place-items: center;
  color: white;
}
.bulk-select-all { width: auto; padding: 0 12px; font-size: 12px; font-weight: 700; }
.bulk-count {
  flex: 1; color: white;
  font-size: 14px; font-weight: 800;
}
```

### 33.3 Selected card visual

```css
.deliv-card.bulk-selected {
  position: relative;
  background: linear-gradient(135deg,
    hsl(280 70% 95%), hsl(305 65% 95%));
  border-left: 3px solid hsl(280 70% 55%);
}
[data-theme="dark"] .deliv-card.bulk-selected {
  background: linear-gradient(135deg,
    hsl(280 30% 10%), hsl(305 30% 10%));
}
.deliv-card.bulk-selected::after {
  content: '✓';
  position: absolute; top: 8px; right: 8px;
  width: 24px; height: 24px; border-radius: 50%;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  color: white;
  display: grid; place-items: center;
  font-size: 14px; font-weight: 800;
  box-shadow: 0 2px 8px hsl(280 70% 50% / 0.4);
}
```

### 33.4 Bottom action bar (3 actions)

```html
<footer class="bulk-bottom">
  <button class="bulk-action q-default" onclick="bulkPriceChange()">
    <svg/><span>Цена</span>
  </button>
  <button class="bulk-action q-default" onclick="bulkQtyChange()">
    <svg/><span>Бройка</span>
  </button>
  <button class="bulk-action q-magic" onclick="bulkMore()">
    <svg/><span>Още</span>
  </button>
</footer>
```

### 33.5 [💰 Цена] действие

Bottom sheet с опции:
```
- Промени cost с %  ([+10%] [-5%] custom)
- Промени retail с %  ([+10%] [-5%] custom)
- Приложи Smart Pricing rule
- Промени margin %
- Custom abs стойност
```

### 33.6 [📦 Бройка] действие

```
- Промени qty
- Възстанови очаквани (от поръчка)
- Маркирай като "Получени всички"
- Маркирай като "0 получени" (cancel)
```

### 33.7 [⚙ Още] действие

```
- Промени доставчик
- Промени магазин (target_store)
- Промени дата (expected_at)
- Експорт PDF/Excel
- Печат етикети bulk
- Прехвърли към друг магазин (transfers)
- Откажи всички (admin only)
```

### 33.8 Voice bulk команди (continuous scan mode)

В manual flow scan mode:
- "Промени всички да са по 10" → qty=10 за всички scanned
- "Цена 12.50" → cost=12.50 за last scanned
- "Триене на последния" → undo last scan
- "Готов" → finalize

### 33.9 Undo + Audit log

След всяка bulk операция:
```html
<div class="bulk-undo-toast">
  <span>3 доставки обновени</span>
  <button onclick="undoBulkOperation()">Undo</button>
</div>
```

CSS: bottom-anchored, 5 секунди auto-dismiss.

DB: всеки bulk write създава `delivery_events` записи (по 1 на ID).

```php
function bulkPriceChange($delivery_ids, $price_delta_pct) {
    DB::transaction(function() use ($delivery_ids, $price_delta_pct) {
        foreach ($delivery_ids as $id) {
            $delivery = DB::get("SELECT * FROM deliveries WHERE id = ?", [$id]);
            $items = DB::all("SELECT * FROM delivery_items WHERE delivery_id = ?", [$id]);
            foreach ($items as $item) {
                $new_cost = $item['unit_cost'] * (1 + $price_delta_pct / 100);
                DB::run("UPDATE delivery_items SET unit_cost = ? WHERE id = ?",
                    [$new_cost, $item['id']]);
            }
            DB::run("INSERT INTO delivery_events (delivery_id, event_type, user_id, payload, created_at)
                     VALUES (?, 'bulk_price_changed', ?, ?, NOW())",
                [$id, $_SESSION['user_id'], json_encode([
                    'delta_pct' => $price_delta_pct,
                    'affected_items' => count($items)
                ])]);
        }
    });
}
```

### 33.10 Multi-select на ниво доставки (shared)

Long-press на supplier groupheader (в "По доставчик" view) → selects all under that group.

---

## 34. EDIT HISTORY & AUDIT

### 34.1 Append-only DB structure (MASTER_COMPASS rule #3)

```sql
CREATE TABLE delivery_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    delivery_id INT NOT NULL,
    event_type ENUM(
        'created',                  -- доставка създадена
        'item_added',               -- артикул добавен
        'item_removed',             -- артикул премахнат
        'item_qty_changed',         -- qty променен
        'item_cost_changed',        -- cost променен
        'item_retail_changed',      -- retail променен
        'supplier_changed',         -- доставчик променен
        'order_linked',             -- свързан с поръчка
        'order_unlinked',           -- размах от поръчка
        'status_changed',           -- статус променен (sent → received)
        'notes_updated',            -- бележки добавени
        'finalized',                -- доставка финализирана
        'cancelled',                -- доставка отменена
        'split_to_stores',          -- multi-store split
        'document_attached',        -- документ прикачен
        'bulk_price_changed',       -- bulk операция
        'bulk_qty_changed',
        'lost_demand_resolved',     -- lost demand затворен
        'cost_history_snapshot'     -- snapshot за cost trend
    ),
    user_id INT,
    user_name VARCHAR(64),  -- кеширано име за бърз показ
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payload JSON,           -- snapshot на промяната
    ip_address VARCHAR(45), -- audit
    user_agent VARCHAR(255),
    INDEX idx_delivery_events (delivery_id, created_at),
    INDEX idx_user_events (user_id, created_at),
    INDEX idx_event_type (event_type, created_at)
);
```

### 34.2 Никога не се изтрива

- Никога `DELETE FROM delivery_events`
- Никога `UPDATE delivery_events`
- Само `INSERT`
- Soft delete на parent `deliveries` (`is_deleted=1`) НЕ изтрива events

### 34.3 Достъп

| Кой | Какво вижда |
|---|---|
| **Митко (owner)** | Всички events за всички доставки |
| **Manager** | Events за доставки в неговия магазин |
| **Seller (Пешо)** | НЕ вижда audit (модулът е hidden в лесен режим) |
| **Developer** | Plus IP, user_agent, raw payload JSON |

### 34.4 Compliance

Българско СУПТО изискване (post-beta когато бъде въведено): export на audit log в XBRL/XML format. Endpoint `/api/audit/delivery_events?from=...&to=...&format=xbrl`.

---

## 35. NOTIFICATIONS

### 35.1 Типове

| Type | Trigger | Channels | Default |
|---|---|---|---|
| **Очаквана днес** | `expected_at = today` сутрин 09:00 | Push + In-app | ON |
| **Закъсняла** | `now() - expected_at > 3d` | Push + In-app + Email | ON |
| **AI чернова готова** | Cron 03:00 generates supplier_orders draft | Push + In-app | ON |
| **Email forward получен** | `pollEmailInbox()` намира нов email | In-app | ON |
| **Cost увеличение ≥10%** | Auto-detect при finalize | In-app | ON for owner/manager |
| **Cost увеличение ≥25%** | Auto-detect | Push + In-app + Email | ON for owner |
| **Lost demand resolved** | След finalize и matcher | In-app | ON |
| **OCR ready за review** | OCR job finished | Push + In-app | ON |
| **Multi-store split препоръка** | OCR + AI suggest | In-app | ON |
| **Etikets ready за print** | След finalize | Push (silent) + In-app | ON |
| **Доставка от Партньор** | Partner portal upload | Push + In-app | ON (post-beta) |

### 35.2 Delivery channels

```php
function sendNotification($user_id, $type, $payload) {
    $prefs = getUserNotificationPrefs($user_id);

    // 1. In-app — винаги (ако пользователят не е изключил всичко)
    if ($prefs[$type]['in_app'] ?? true) {
        DB::run("INSERT INTO notifications (...) VALUES (...)");
    }

    // 2. Push (mobile + browser)
    if ($prefs[$type]['push'] ?? true) {
        sendPushNotification($user_id, $payload);
    }

    // 3. Email
    if ($prefs[$type]['email'] ?? false) {  // off by default unless type req
        sendEmail($user_id, $type, $payload);
    }

    // 4. SMS (post-beta, only critical)
    if ($prefs[$type]['sms'] ?? false) {
        sendSMS($user_id, $payload);
    }
}
```

### 35.3 In-app notification UI

Notification icon в bottom nav badge (red dot ако > 0 unread).

```html
<aside class="notif-drawer">
  <header class="nd-head">
    <h2>Известия</h2>
    <button class="mark-all-read">Маркирай всички</button>
  </header>

  <article class="notif-item unread q-loss">
    <span class="ni-orb"><svg/></span>
    <div class="ni-text">
      <strong>Закъсняла доставка</strong>
      <span>Емпорио ООД · 3 дни</span>
      <span class="ni-time">преди 2 часа</span>
    </div>
    <button class="ni-action">Виж →</button>
  </article>

  <article class="notif-item read q-magic">
    <!-- ... -->
  </article>
</aside>
```

### 35.4 Settings per notification type

```html
<div class="notif-settings">
  <h3>Закъсняла доставка</h3>
  <label class="toggle-row">
    <span>В приложението</span>
    <button class="toggle on"><span class="knob"/></button>
  </label>
  <label class="toggle-row">
    <span>Push известия</span>
    <button class="toggle on"><span class="knob"/></button>
  </label>
  <label class="toggle-row">
    <span>Email</span>
    <button class="toggle off"><span class="knob"/></button>
  </label>

  <h4>Тих час</h4>
  <div class="quiet-hours">
    <span>От:</span><input type="time" value="22:00"/>
    <span>До:</span><input type="time" value="08:00"/>
  </div>
</div>
```

### 35.5 Quiet hours

Default: 22:00 - 08:00 локално време. Push блокира се. In-app се появи но без звук. Email НЕ блокира (legitimate notification).

### 35.6 Push notification payload structure

```json
{
  "to": "user_token",
  "data": {
    "type": "deliv_overdue",
    "delivery_id": 234,
    "deeplink": "runmystore://deliveries/234"
  },
  "notification": {
    "title": "Закъсняла доставка",
    "body": "Емпорио ООД — 3 дни закъснение",
    "icon": "/icons/delivery-late.png",
    "badge": 1,
    "sound": "default"
  }
}
```

### 35.7 Notification deep linking

Tap на push → `runmystore://deliveries/234` → Capacitor router → отваря `deliveries.php?id=234`. Track click events за engagement (от `13_engagement_tracking`).

---

## КРАЙ НА ЧАСТ 5B

# ═══════════════════════════════════════
# ЧАСТ 6A — СПЕЦИАЛНИ FLOW-ОВЕ
# ═══════════════════════════════════════

## 36. MULTI-STORE SPLIT (ENI 5 МАГАЗИНА)

### 36.1 Use case

ENI има 5 магазина (Витоша, Цариградско, Студентски, Овча купел, Банкя). Една доставка от Емпорио идва в централен склад (или в магазин 1) и трябва да се раздели по 5-те магазина според потребностите.

### 36.2 Trigger (3 точки)

| Trigger | UI |
|---|---|
| **Ръчно** | Detail header → button "Раздели по магазини" |
| **Auto-prompt при OCR** | Ако `total_items > 30` → AI пита "Да разделя ли по магазини?" |
| **AI signal** | После finalize → "Имаш недостиг в Магазин 3, прехвърли 4 от тук?" |

### 36.3 Split prompt UI

```html
<div class="split-prompt q-magic">
  <span class="sp-orb"><svg/></span>
  <strong>Раздели по магазини?</strong>
  <p>Доставката е голяма (240 артикула). AI може да предложи разделение на база на продажби в последните 30 дни.</p>
  <div class="sp-actions">
    <button class="sp-btn primary">AI предложи</button>
    <button class="sp-btn">Цялата в текущия магазин</button>
    <button class="sp-btn">Аз ще разделя</button>
  </div>
</div>
```

### 36.4 [AI предложи] flow

PHP логика:
```php
function aiSuggestSplit($delivery_items, $tenant_id) {
    $stores = DB::all("SELECT id, name FROM stores WHERE tenant_id = ? AND is_active = 1",
                      [$tenant_id]);

    $suggestions = [];
    foreach ($delivery_items as $item) {
        // Sales за last 30d per store
        $sales_per_store = DB::all("
            SELECT s.store_id, SUM(si.quantity) AS sold
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE si.product_id = ?
              AND s.tenant_id = ?
              AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND s.status = 'completed'
            GROUP BY s.store_id
        ", [$item['product_id'], $tenant_id]);

        $total_sold = array_sum(array_column($sales_per_store, 'sold'));

        // Proportional split
        if ($total_sold > 0) {
            foreach ($sales_per_store as $sps) {
                $share = $sps['sold'] / $total_sold;
                $allocated = round($item['delivered_qty'] * $share);
                $suggestions[$item['id']][$sps['store_id']] = $allocated;
            }
        } else {
            // Equal split fallback
            $per_store = floor($item['delivered_qty'] / count($stores));
            foreach ($stores as $store) {
                $suggestions[$item['id']][$store['id']] = $per_store;
            }
        }
    }
    return $suggestions;
}
```

### 36.5 Split UI

```html
<div class="split-screen">
  <header><h2>Раздели доставката</h2></header>

  <!-- Summary strip -->
  <div class="split-summary">
    <span class="ss-total">240 артикула общо</span>
    <span class="ss-allocated">240 разпределени</span>
    <span class="ss-balance ok">✓ Балансирано</span>
  </div>

  <!-- Per-item rows -->
  <article class="split-item">
    <div class="si-head">
      <span class="si-name">Тениска синя L</span>
      <span class="si-total">10 общо</span>
    </div>
    <div class="si-stores">
      <div class="si-store">
        <span class="ss-name">Витоша</span>
        <input class="ss-qty" value="4" inputmode="numeric"/>
        <span class="ss-pct">40%</span>
      </div>
      <div class="si-store">
        <span class="ss-name">Цариградско</span>
        <input class="ss-qty" value="3" inputmode="numeric"/>
        <span class="ss-pct">30%</span>
      </div>
      <div class="si-store">
        <span class="ss-name">Студентски</span>
        <input class="ss-qty" value="2" inputmode="numeric"/>
        <span class="ss-pct">20%</span>
      </div>
      <div class="si-store">
        <span class="ss-name">Овча купел</span>
        <input class="ss-qty" value="1" inputmode="numeric"/>
        <span class="ss-pct">10%</span>
      </div>
      <div class="si-store">
        <span class="ss-name">Банкя</span>
        <input class="ss-qty" value="0" inputmode="numeric"/>
        <span class="ss-pct">0%</span>
      </div>
    </div>
    <div class="si-balance">
      <span>Сума: <strong>10 / 10</strong></span>
      <span class="ai-hint">AI предложи на база продажби (30д)</span>
    </div>
  </article>

  <!-- ... 18 items -->

  <footer class="split-foot">
    <button class="split-cta">Запази split</button>
  </footer>
</div>
```

CSS:
```css
.split-summary {
  display: flex; gap: 12px; padding: 14px;
  background: var(--surface);
  border-radius: var(--radius);
  margin: 12px;
  font-size: 12px;
}
.ss-balance.ok { color: hsl(145 70% 45%); font-weight: 800; margin-left: auto; }
.ss-balance.warn { color: hsl(38 80% 45%); font-weight: 800; margin-left: auto; }
.ss-balance.error { color: hsl(0 70% 50%); font-weight: 800; margin-left: auto; }

.split-item {
  background: var(--surface);
  border-radius: var(--radius);
  padding: 12px; margin: 8px 12px;
  box-shadow: var(--shadow-card-sm);
}
.si-head {
  display: flex; justify-content: space-between;
  margin-bottom: 8px;
  font-size: 13px; font-weight: 800;
}
.si-stores { display: grid; gap: 6px; }
.si-store {
  display: grid;
  grid-template-columns: 1fr 60px 40px;
  gap: 8px; align-items: center;
  padding: 6px 0;
  font-size: 12px;
}
.ss-qty {
  text-align: center; font-weight: 800;
  background: var(--bg-main); border: 0;
  border-radius: var(--radius-pill);
  height: 32px; padding: 0;
}
.ss-pct {
  font-family: var(--font-mono); font-size: 9.5px;
  font-weight: 700; color: var(--text-muted);
}
.si-balance {
  display: flex; justify-content: space-between;
  margin-top: 8px; padding-top: 8px;
  border-top: 1px solid var(--border-soft);
  font-size: 11px; color: var(--text-muted);
}
.ai-hint { font-style: italic; color: hsl(280 70% 55%); }
```

### 36.6 PHP finalize за split

```php
function finalizeSplitDelivery($delivery_id, $allocations) {
    // $allocations = [item_id => [store_id => qty]]
    DB::transaction(function() use ($delivery_id, $allocations) {
        foreach ($allocations as $item_id => $stores) {
            foreach ($stores as $store_id => $qty) {
                if ($qty > 0) {
                    // Insert per-store row
                    DB::run("INSERT INTO delivery_item_stores
                             (delivery_item_id, store_id, allocated_qty)
                             VALUES (?, ?, ?)",
                        [$item_id, $store_id, $qty]);

                    // Update inventory per store
                    DB::run("INSERT INTO inventory (product_id, store_id, quantity, tenant_id)
                             SELECT product_id, ?, ?, tenant_id
                             FROM delivery_items WHERE id = ?
                             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)",
                        [$store_id, $qty, $item_id]);
                }
            }
        }

        DB::run("INSERT INTO delivery_events (delivery_id, event_type, user_id, payload, created_at)
                 VALUES (?, 'split_to_stores', ?, ?, NOW())",
            [$delivery_id, $_SESSION['user_id'], json_encode($allocations)]);
    });
}
```

### 36.7 Result

След split → главната доставка остава една, но в Detail tab "Артикули" се показва per-item per-store breakdown:

```
Тениска синя L (10 общо)
  ├─ Витоша: 4
  ├─ Цариградско: 3
  ├─ Студентски: 2
  └─ Овча купел: 1
```

### 36.8 Transfer scenario (post-beta)

Ако след split AI signal "Магазин Витоша има недостиг" → CTA "Прехвърли 2 от Цариградско" → автоматично създава `transfers` row, без да се пипа `delivery_item_stores`.

---

## 37. OFFLINE MODE — 4 НИВА НАДОЛУ

### 37.1 Принцип

Internet липсва ≠ Пешо спира работа. AI деградира градивно през 4 нива.

### 37.2 4 нива

| Ниво | Internet | OCR | Voice | Scan | Действие |
|---|---|---|---|---|---|
| **1. Online (full)** | ✓ | Cloud OCR | Whisper Groq | All | Всичко работи |
| **2. Limited** | Slow/intermittent | Local OCR fallback | Web Speech API only | All | Без cloud features |
| **3. Cached** | OFFLINE | NO | NO | Барод scan match с local cache | Само scan + manual |
| **4. Blind receive** | OFFLINE + cache stale | NO | NO | NO | Само сума + доставчик ИМЕ |

### 37.3 UI индикатор

```html
<div class="offline-banner level-3">
  <span class="ob-orb"><svg/></span>
  <div class="ob-text">
    <strong>Офлайн режим</strong>
    <span>Скенерът работи. Voice/OCR временно недостъпни.</span>
  </div>
  <button class="ob-retry">Опитай отново</button>
</div>
```

CSS:
```css
.offline-banner {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px;
  background: hsl(38 50% 95%);
  border-bottom: 2px solid hsl(38 90% 55%);
  position: sticky; top: 56px; z-index: 49;
}
.offline-banner.level-3 { background: hsl(0 50% 95%); border-bottom-color: hsl(0 70% 55%); }
.offline-banner.level-4 { background: hsl(280 30% 95%); border-bottom-color: hsl(280 70% 55%); }
[data-theme="dark"] .offline-banner { background: hsl(38 30% 12%); }
.ob-orb { width: 32px; height: 32px; flex-shrink: 0; }
.ob-text { flex: 1; }
.ob-text strong { display: block; font-size: 13px; font-weight: 800; }
.ob-text span { display: block; font-size: 11px; color: var(--text-muted); }
.ob-retry {
  font-size: 11px; font-weight: 700;
  padding: 6px 10px;
  background: var(--surface); border-radius: var(--radius-pill);
}
```

### 37.4 Offline cache в телефона

ServiceWorker кешира:
- products list (top 1000 by `last_sold_at`)
- suppliers list (всички активни)
- last 30 deliveries headers
- pricing rules

Cap: 5 MB localStorage / IndexedDB.

```javascript
const CACHE_VERSION = 'v3-2026-05-09';
const CACHE_MANIFEST = [
  '/api/products/cache?limit=1000',
  '/api/suppliers/cache',
  '/api/deliveries/recent?limit=30',
  '/api/pricing-rules'
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE_VERSION).then(c => c.addAll(CACHE_MANIFEST)));
});
```

### 37.5 Blind receive (Ниво 4)

```html
<div class="blind-receive">
  <header><h2>Бърза blind доставка</h2></header>
  <p class="blind-hint">Без интернет. Запазваме само основното — детайлите ще се добавят после.</p>

  <div class="mw-row">
    <label class="mw-label">Доставчик</label>
    <input class="mw-input" placeholder="Име..." />
  </div>
  <div class="mw-row">
    <label class="mw-label">Обща стойност</label>
    <div class="input-shell">
      <input class="mw-input" inputmode="decimal" placeholder="0.00"/>
      <span class="input-suffix">€</span>
    </div>
  </div>
  <div class="mw-row">
    <label class="mw-label">Бележки</label>
    <textarea class="mw-input">Получено offline на 09.05 14:23</textarea>
  </div>
  <button class="blind-save">Запази (ще се синхронизира после)</button>
</div>
```

PHP:
```php
function saveBlindDelivery($data, $tenant_id) {
    DB::run("INSERT INTO deliveries (
        tenant_id, supplier_name_text, total_value, notes,
        status, source, is_blind, queued_for_sync, created_offline_at
    ) VALUES (?, ?, ?, ?, 'received', 'blind', 1, 1, ?)",
        [$tenant_id, $data['supplier_name'], $data['total_value'],
         $data['notes'], $data['offline_timestamp']]);
}
```

### 37.6 Queue for sync

При възстановяване на интернет:
- Service worker детектира `online` event
- Пуска background sync
- Изпраща queued deliveries
- AI signal "Имаш 3 offline доставки за допълване" → owner може да ги дообогати

```javascript
self.addEventListener('online', async () => {
  const queued = await getQueuedDeliveries();
  for (const d of queued) {
    await fetch('/api/deliveries/sync', { method: 'POST', body: JSON.stringify(d) });
    await markSynced(d.id);
  }
});
```

---

## 38. COST CHANGE WARNING

### 38.1 Trigger

При finalize на delivery PHP сравнява `delivery_items.unit_cost` vs `products.cost_price` (текущ):

```php
$change_pct = ($new_cost - $old_cost) / $old_cost * 100;
```

### 38.2 Прагове

| Промяна | UI ефект |
|---|---|
| **<5%** | Тиха актуализация (no UI) |
| **5-10%** | Toast: "Cost на тениска синя обновен (+8%)" |
| **10-25%** | AI signal P1 "Cost увеличение ≥10% за 3 артикула" → Smart Pricing prompt |
| **≥25%** | AI signal P0 + email + push: "Внимание — cost увеличение 28% за чанта черна. Провери дали е грешка." |

### 38.3 Auto-update логика

Setting в Pricing Rules: "При cost промяна — auto-update retail?"
- ✓ Yes (default OFF) — retail recalc според margin %
- ✗ No — само пометка, retail остава

```php
function applyAutoRetailUpdate($product_id, $old_cost, $new_cost, $tenant_id) {
    $setting = getSetting($tenant_id, 'auto_update_retail_on_cost_change', false);
    if (!$setting) return;

    $product = DB::get("SELECT * FROM products WHERE id = ?", [$product_id]);
    $current_margin_pct = ($product['retail_price'] - $old_cost) / $product['retail_price'] * 100;

    // Maintain same margin %
    $new_retail = $new_cost / (1 - $current_margin_pct / 100);
    $new_retail = applyRounding($new_retail, getSetting($tenant_id, 'rounding_rule', 'end_90'));

    DB::run("UPDATE products SET retail_price = ? WHERE id = ?", [$new_retail, $product_id]);
}
```

### 38.4 AI signal в Detail (post-beta)

В detail view, артикули с cost change ≥10% получават визуален акцент:
```css
.dd-item.cost-changed-up::after {
  content: '↑'; position: absolute; top: 8px; right: 8px;
  width: 18px; height: 18px; border-radius: 50%;
  background: hsl(0 70% 55%); color: white;
  font-size: 11px; font-weight: 800;
  display: grid; place-items: center;
}
```

---

## 39. LOST DEMAND RESOLVED CYCLE

### 39.1 Trigger

При finalize на delivery PHP runs matcher:
```php
function closeLostDemand($delivery_id) {
    $items = DB::all("SELECT product_id FROM delivery_items WHERE delivery_id = ?", [$delivery_id]);
    $product_ids = array_column($items, 'product_id');

    if (empty($product_ids)) return;

    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $resolved = DB::all("
        SELECT id, product_id, customer_phone, estimated_loss
        FROM lost_demand
        WHERE product_id IN ($placeholders)
          AND resolved_at IS NULL
        ORDER BY created_at ASC
    ", $product_ids);

    foreach ($resolved as $ld) {
        DB::run("UPDATE lost_demand
                 SET resolved_at = NOW(), resolved_via_delivery_id = ?
                 WHERE id = ?",
            [$delivery_id, $ld['id']]);

        // Optional notify customer (loyalty members only, post-beta)
        if ($ld['customer_phone'] && hasLoyaltyConsent($ld['customer_phone'])) {
            sendSMS($ld['customer_phone'],
                "Здравей! Артикулът който търсеше дойде. Ела до магазина.");
        }
    }

    return [
        'count' => count($resolved),
        'total_loss_recovered' => array_sum(array_column($resolved, 'estimated_loss'))
    ];
}
```

### 39.2 UI след finalize

```html
<div class="lost-resolved-summary q-gain">
  <span class="lr-orb"><svg/></span>
  <div class="lr-text">
    <strong>3 артикула възстановени</strong>
    <span>€420 lost demand закрит</span>
  </div>
  <button class="lr-cta">Виж детайли</button>
</div>
```

### 39.3 ROI tracking (за owner)

В stats.php → "Lost Demand ROI":
```
Този месец:
  ✓ Resolved: 12 случая
  ✓ Восстановени €: 1 240
  ✓ Average resolution time: 8.2 дни
  ✓ Top products възстановени: тениска синя L (3 пъти)

Best supplier за resolution: Емпорио ООД (5 случая, €620)
Worst: Турция Текстил (lead time 18 дни, само 1 случай)
```

### 39.4 Cron нощем (06:30) — fuzzy match

За артикули в `sale_items` с `product_id=NULL` + barcode notes:
```sql
UPDATE sale_items si
JOIN products p ON p.code = JSON_EXTRACT(si.notes, '$.barcode')
SET si.product_id = p.id
WHERE si.product_id IS NULL
  AND si.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### 39.5 Стандартен Lost Demand cycle

```
1. sale.php scan → barcode → no product → ghost sale
2. AI fuzzy match (cron 06:30) → INSERT lost_demand
3. AI signal "Изпускаш €145/ден от тениска синя L"
4. Митко поръчва (orders.php или AI чернова)
5. Доставка пристига → finalize → matcher closures
6. AI signal "Lost demand resolved €420"
7. (post-beta) Loyalty SMS notify customer
```

---

## 40. HIDDEN INVENTORY +40% BOOST (ЗАКОН №6)

### 40.1 Принцип (INVENTORY_HIDDEN_v3 §6)

`confidence_score` (0.0 - 1.0) трекира колко сигурно знаем `inventory.quantity` за всяка `(product_id, store_id)` двойка. **НИКОГА visible на Митко в production UI** — само влияе на skeleton vs full opacity на инвентаризационните UI.

### 40.2 Confidence levels

| Score | UI | Trigger |
|---|---|---|
| `< 0.3` | skeleton + pulse + "AI proverya" | Дълго време без активност |
| `0.3 - 0.6` | dimmed (opacity 0.7) | Old data |
| `0.6 - 0.9` | normal | Recent activity |
| `> 0.9` | full opaque + "потвърдено" pill | Recent inventory action |

### 40.3 +0.40 Delivery boost

При finalize delivery → за всеки `(product_id, target_store_id)`:

```php
function boostInventoryConfidence($store_id, $product_ids, $boost = 0.40) {
    foreach ($product_ids as $pid) {
        DB::run("
            INSERT INTO inventory_confidence (product_id, store_id, confidence_score, last_event_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                confidence_score = LEAST(1.0, confidence_score + ?),
                last_event_at = NOW()
        ", [$pid, $store_id, $boost, $boost]);
    }
}
```

### 40.4 7 events boost

| Event | Boost | Decay (per day) |
|---|---|---|
| Доставка finalize | +0.40 | -0.005 |
| Manual count | +0.50 | -0.005 |
| Sale (scan barcode) | +0.10 | (per item) |
| Transfer received | +0.30 | -0.005 |
| Adjust manual | +0.40 | -0.005 |
| Inventory full count | reset to 1.0 | -0.005 |
| Sale (no scan, voice add) | +0.05 | (small) |

### 40.5 Negative stock resolution

Ако `inventory.quantity < 0` (пример: scan на barcode без stock):
1. Sale продължава като ghost (защото Пешо не може да чака)
2. На finalize delivery → matcher по barcode → STock се возстановява
3. UI: stat в Detail "3 артикула възстановени от негативно stock"

### 40.6 Никога visible на Митко

```php
// products.php — show product details
$showConfidence = ($_SESSION['user_role'] === 'developer'
                || $_SESSION['feature_flags']['debug_confidence']);

if ($showConfidence) {
    echo "<span class='conf-pill'>Conf: {$conf}</span>";
}
// иначе UI просто прави skeleton/pulse без обяснение
```

### 40.7 Category count trigger

AI signal "Хайде да броим тениски": когато една категория има средно `confidence_score < 0.5` за >50% от артикулите → AI signal P2 (cron 06:30):

```sql
SELECT category_id,
       COUNT(*) AS total,
       AVG(ic.confidence_score) AS avg_conf
FROM products p
LEFT JOIN inventory_confidence ic ON p.id = ic.product_id
WHERE p.tenant_id = ?
  AND p.is_active = 1
GROUP BY category_id
HAVING avg_conf < 0.5 AND total >= 10;
```

---

## 41. AUTO PRINT LABELS (TSPL ЗА DTM-5811)

### 41.1 Trigger

След finalize delivery → ако `auto_print_labels = ON` в settings → bottom sheet за print preview:

```html
<div class="print-prompt">
  <h3>Печат на етикети</h3>
  <p>18 артикула × N броя = 47 етикета</p>
  <div class="pp-options">
    <label>
      <input type="checkbox" checked/>
      Включи всички (47 етикета)
    </label>
    <label>
      <input type="checkbox" />
      Само нови артикули (3)
    </label>
    <label>
      <input type="checkbox" />
      Само артикули с cost change (5)
    </label>
  </div>
  <button class="pp-print primary">Печатай</button>
  <button class="pp-skip">Пропусни</button>
</div>
```

### 41.2 TSPL команди (за Bluetooth printer DTM-5811, 50×30mm)

```php
function generateTSPLLabel($product, $tenant) {
    $name = mb_substr($product['name'], 0, 22);
    $price_eur = priceFormat($product['retail_price'], $tenant, 'EUR');
    $price_bgn = priceFormat($product['retail_price'] * 1.95583, $tenant, 'BGN');

    // BG dual pricing required by law until 08.08.2026
    return "
SIZE 50 mm,30 mm
GAP 2 mm,0
DENSITY 8
SPEED 4
DIRECTION 1
CLS
TEXT 20,10,\"3\",0,1,1,\"$name\"
BARCODE 20,50,\"128\",60,1,0,2,2,\"{$product['code']}\"
TEXT 20,120,\"4\",0,1,1,\"$price_eur\"
TEXT 20,160,\"2\",0,1,1,\"$price_bgn\"
PRINT 1,1
";
}
```

### 41.3 Bluetooth flow

```javascript
async function printLabels(products) {
  const device = await navigator.bluetooth.requestDevice({
    filters: [{ name: 'DTM-5811' }],
    optionalServices: ['000018f0-0000-1000-8000-00805f9b34fb']
  });
  const server = await device.gatt.connect();
  const service = await server.getPrimaryService('000018f0-...');
  const char = await service.getCharacteristic('00002af1-...');

  for (const p of products) {
    const tspl = await fetch(`/api/labels/tspl?product_id=${p.id}`).then(r => r.text());
    await char.writeValue(new TextEncoder().encode(tspl));
    await sleep(200);  // wait between labels
  }
}
```

### 41.4 Label format (BG dual pricing)

```
┌─────────────────────────────┐
│ Тениска синя L              │  ← name (truncated 22 chars)
│                             │
│ ║│║│ ║║║│║│║║│║║ │║║│║      │  ← Code 128 barcode
│ ABC123                      │
│                             │
│  29.90 €                    │  ← EUR (primary)
│  58.50 лв                   │  ← BGN (legal until 08.08.2026)
└─────────────────────────────┘
```

### 41.5 Setting

```html
<div class="settings-print">
  <h3>Етикетен printer</h3>
  <label class="toggle-row">
    <span>Auto-print при доставка</span>
    <button class="toggle on"><span/></button>
  </label>
  <label class="toggle-row">
    <span>BG двойна цена (€ + лв)</span>
    <button class="toggle on disabled"><span/></button>  <!-- legally required -->
  </label>
  <p class="legal-hint">До 08.08.2026 двойна цена е задължителна по закон.</p>

  <h4>Свързан printer</h4>
  <div class="printer-status">
    <span class="ps-dot connected"></span>
    DTM-5811 (DC:0D:51:AC:51:D9)
    <button class="ps-disconnect">Прекъсни</button>
  </div>
</div>
```

---

## КРАЙ НА ЧАСТ 6A

# ═══════════════════════════════════════
# ЧАСТ 6B — DB + EDGE CASES + ФИНАЛИЗАЦИЯ
# ═══════════════════════════════════════

## 42. DB SCHEMA — ПЪЛЕН СПИСЪК

### 42.1 `deliveries` (главна таблица)

```sql
CREATE TABLE deliveries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    delivery_number VARCHAR(32) UNIQUE,         -- 'DEL-2026-0234'
    supplier_id INT,                            -- може да е NULL (manual blind)
    supplier_name_text VARCHAR(255),            -- ако NULL supplier (blind/email)
    order_id INT,                               -- свързана поръчка (може NULL)
    target_store_id INT,                        -- основен магазин (може override per item)

    status ENUM('sent','acked','partial','received','overdue','cancelled') DEFAULT 'sent',
    is_partial BOOLEAN DEFAULT 0,
    is_blind BOOLEAN DEFAULT 0,                 -- offline ниво 4
    is_ai_draft BOOLEAN DEFAULT 0,
    is_deleted BOOLEAN DEFAULT 0,               -- soft delete only

    source ENUM('ocr','voice','manual','import','email','partner_portal','blind') DEFAULT 'manual',
    confidence_avg DECIMAL(3,2),                -- average OCR confidence (0.00-1.00)

    expected_at DATETIME,                       -- очаквана дата
    received_at DATETIME,                       -- реална дата на получаване
    sent_at DATETIME,                           -- дата на изпращане на поръчка
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_offline_at DATETIME,                -- ако е била offline
    created_by INT NOT NULL,
    finalized_at DATETIME,
    finalized_by INT,
    cancelled_at DATETIME,
    cancelled_by INT,

    total_items INT DEFAULT 0,                  -- denormalized count
    total_value DECIMAL(12,2) DEFAULT 0,        -- in EUR
    total_value_with_vat DECIMAL(12,2),
    vat_amount DECIMAL(12,2),
    currency CHAR(3) DEFAULT 'EUR',

    payment_terms_days INT,                     -- 30/60/90
    payment_due_at DATE,
    is_paid BOOLEAN DEFAULT 0,
    paid_at DATETIME,

    notes TEXT,
    internal_notes TEXT,                        -- only owner/manager
    queued_for_sync BOOLEAN DEFAULT 0,          -- offline queue

    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_supplier (supplier_id, expected_at),
    INDEX idx_order (order_id),
    INDEX idx_expected (expected_at),
    INDEX idx_received (received_at),
    INDEX idx_sync_queue (queued_for_sync, created_offline_at)
);
```

### 42.2 `delivery_items`

```sql
CREATE TABLE delivery_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_id INT NOT NULL,
    product_id INT NOT NULL,                    -- ALWAYS set (mini-wizard ако нов)

    expected_qty INT,                           -- от поръчка ако има
    delivered_qty INT NOT NULL,
    unit_cost DECIMAL(10,4) NOT NULL,           -- с/без VAT според supplier setup
    unit_retail DECIMAL(10,4),                  -- snapshot от Smart Pricing

    previous_cost DECIMAL(10,4),                -- за cost change tracking
    cost_change_pct DECIMAL(5,2),               -- pre-computed

    line_subtotal DECIMAL(12,2),                -- delivered_qty × unit_cost
    line_vat DECIMAL(12,2),
    line_total DECIMAL(12,2),

    confidence DECIMAL(3,2),                    -- per-item OCR confidence
    source ENUM('ocr','voice','scan','manual','typeahead','top20','prefill','csv'),
    notes VARCHAR(255),                         -- voice/OCR raw extract

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    is_complete_after_delivery BOOLEAN DEFAULT 1,  -- ако mini-wizard create — false

    INDEX idx_delivery (delivery_id),
    INDEX idx_product (product_id),
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
);
```

### 42.3 `delivery_item_stores` (split per store)

```sql
CREATE TABLE delivery_item_stores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_item_id INT NOT NULL,
    store_id INT NOT NULL,
    allocated_qty INT NOT NULL,
    transferred_at DATETIME,                    -- ако е post-receive transfer
    UNIQUE KEY uniq_item_store (delivery_item_id, store_id),
    FOREIGN KEY (delivery_item_id) REFERENCES delivery_items(id) ON DELETE CASCADE
);
```

### 42.4 `delivery_events` (audit log)

(виж §34.1 за пълен schema)

### 42.5 `scanner_documents`

```sql
CREATE TABLE scanner_documents (
    id CHAR(36) PRIMARY KEY,                    -- UUID
    tenant_id INT NOT NULL,
    user_id INT,
    delivery_id INT,                            -- ако е финализиран
    file_path VARCHAR(512),                     -- /uploads/{tenant}/ocr/{uuid}.{ext}
    file_size INT,
    mime_type VARCHAR(64),
    source ENUM('camera','upload','email','partner_portal'),
    email_from VARCHAR(255),                    -- ако source=email
    email_subject VARCHAR(512),
    supplier_id INT,                            -- AI fuzzy match
    confidence_avg DECIMAL(3,2),
    extracted_data JSON,                        -- raw AI output
    status ENUM('pending','processing','done','failed'),
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_delivery (delivery_id)
);
```

### 42.6 `inventory_confidence` (Hidden Inventory Hidden, Закон №6)

```sql
CREATE TABLE inventory_confidence (
    product_id INT NOT NULL,
    store_id INT NOT NULL,
    confidence_score DECIMAL(4,3) DEFAULT 0.500,   -- 0.000 - 1.000
    last_event_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_decay_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, store_id)
);
```

Никога не се чете от owner UI directly. Само PHP backend.

### 42.7 `lost_demand`

```sql
CREATE TABLE lost_demand (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    product_id INT,                             -- ако може да се мaчне
    barcode_scanned VARCHAR(64),                -- raw scanned code (ако no match)
    name_text VARCHAR(255),                     -- ако voice/manual
    customer_phone VARCHAR(32),                 -- post-beta loyalty
    quantity_requested INT DEFAULT 1,
    estimated_loss DECIMAL(10,2),               -- qty × последна цена

    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    occurred_in_store_id INT,
    occurred_in_sale_id INT,                    -- ако от sale flow

    resolved_at DATETIME NULL,
    resolved_via_delivery_id INT NULL,
    notification_sent_at DATETIME NULL,         -- post-beta loyalty

    INDEX idx_unresolved (tenant_id, resolved_at, product_id),
    INDEX idx_product (product_id, occurred_at)
);
```

### 42.8 `saved_filters` (виж §27.7)

(виж §27.7)

### 42.9 `recent_searches` (виж §28.5)

(виж §28.5)

### 42.10 `pricing_rules`

```sql
CREATE TABLE pricing_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    scope ENUM('global','category','supplier') NOT NULL,
    category_id INT,                            -- ако scope=category
    supplier_id INT,                            -- ако scope=supplier
    margin_pct DECIMAL(5,2) DEFAULT 50.00,
    rounding ENUM('whole','end_99','end_90','end_50','multi_5','multi_10','none') DEFAULT 'end_90',
    min_margin_pct DECIMAL(5,2) DEFAULT 25.00,
    additional_correction DECIMAL(10,2) DEFAULT 0.00,
    auto_update_on_cost_change BOOLEAN DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_global (tenant_id, scope, category_id, supplier_id)
);
```

### 42.11 `cost_history` (за cost trend reporting)

```sql
CREATE TABLE cost_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    cost_old DECIMAL(10,4),
    cost_new DECIMAL(10,4),
    cost_change_pct DECIMAL(5,2),
    supplier_id INT,
    delivery_id INT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_time (product_id, changed_at)
);
```

### 42.12 `notifications`

```sql
CREATE TABLE notifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT, user_id INT,
    type VARCHAR(64),                           -- 'deliv_overdue', 'deliv_email_received', etc.
    deliv_id INT,                               -- specific link
    payload JSON,
    is_read BOOLEAN DEFAULT 0,
    read_at DATETIME,
    is_dismissed BOOLEAN DEFAULT 0,
    dismissed_at DATETIME,
    delivered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_unread (user_id, is_read, delivered_at DESC)
);
```

### 42.13 ALTER на `suppliers` (за tracking)

```sql
ALTER TABLE suppliers
ADD COLUMN total_deliveries INT DEFAULT 0,
ADD COLUMN total_value DECIMAL(12,2) DEFAULT 0,
ADD COLUMN last_delivery_date DATE,
ADD COLUMN avg_lead_time_days DECIMAL(4,1),
ADD COLUMN reliability_score DECIMAL(4,1) DEFAULT 50.0,    -- 0-100
ADD COLUMN late_count_30d INT DEFAULT 0,
ADD COLUMN issues_count INT DEFAULT 0,
ADD COLUMN forwarded_email VARCHAR(255),                    -- per-supplier email if needed
ADD COLUMN payment_terms_default INT DEFAULT 30;
```

### 42.14 ALTER на `products` (новите полета)

```sql
ALTER TABLE products
ADD COLUMN is_complete BOOLEAN DEFAULT 1,        -- false ако mini-wizard
ADD COLUMN created_in_delivery_id INT NULL,
ADD COLUMN cost_history_count INT DEFAULT 0;
```

---

## 43. EDGE CASES (12+ СЦЕНАРИИ)

### 43.1 Списък

| # | Сценарий | Поведение |
|---|---|---|
| 1 | OCR не разпознава доставчик | Smart picker + "Нов доставчик: [extracted name]" |
| 2 | Пешо снима грешен документ | Reject + fallback (виж §15.4) |
| 3 | OCR извлича нечислови стойности като цена ("12.50€" с единици) | Parser нормализира; ако fail → low confidence reject |
| 4 | Артикул в доставка с QR код вместо barcode | Scanner улавя; типеahead match по camera ROI text |
| 5 | Двама Пешовци наведнъж приемат същата доставка | Lock на `delivery_id`; първият спечелва, втория получава "доставката се обработва от X" |
| 6 | Internet прекъсва по средата на OCR processing | Job се запазва в `scanner_documents.status='pending_offline'`; продължава на reconnect |
| 7 | Доставка с >500 артикула | Pagination в detail (lazy load); progress bar при finalize; warning "Голяма доставка — отнеме време" |
| 8 | Един артикул се появява 2 пъти в OCR (different names) | AI dedup heuristic (similarity >0.85); ask Митко при ambiguity |
| 9 | Cost = 0 (грешка в OCR) | Reject this item; force manual input; warning "Цена не може да е 0" |
| 10 | Margin <0% (retail < cost) | Block finalize; warning "Maрж е отрицателен — провери" |
| 11 | Supplier с дубликат име ("Емпорио" vs "Емпорио ООД") | AI fuzzy match suggest merge; админ може да слее (post-beta) |
| 12 | Доставка за изтекла поръчка (>30 дни) | Allow link, AI signal "Поръчка беше cancelled преди 5 дни — възстанови?" |
| 13 | Multi-currency доставка (TRY/USD) | Convert по `exchange_rate` table; запази оригинал + EUR; Intrastat post-beta |
| 14 | Same SKU в 2 доставки в същия ден | Aggregate inventory; show 2 cards в detail tab "История" |
| 15 | Bluetooth printer не свързва | Fallback: PDF preview за email/тачскрин print по-късно |
| 16 | Voice STT транскрипира wrong language | Detect + auto-fallback към Whisper; ако fail → manual |
| 17 | Email forward от unauthorized sender | Reject; spam log; AI signal "Подозрителен email opitvаm Емпорио" |
| 18 | Доставка за артикул който няма в каталог + няма barcode | Mini-wizard create + AI suggest category по name |
| 19 | `delivery_item.delivered_qty=0` (липсваща позиция) | Mark `is_partial=1`; "Поръчай липсата" CTA в detail |
| 20 | Incomplete продукт (`is_complete=0`) появява в нова доставка | UI: "Чакаш да допълниш този артикул" → wizard step 2 link |

### 43.2 Detailed handling — Edge case 5 (concurrent receive)

```php
function lockDeliveryForReceive($delivery_id, $user_id) {
    $locked = DB::run("
        UPDATE deliveries
        SET locked_by = ?, locked_at = NOW()
        WHERE id = ?
          AND (locked_by IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE))
    ", [$user_id, $delivery_id]);

    if ($locked === 0) {
        $current = DB::get("SELECT u.name FROM deliveries d JOIN users u ON d.locked_by = u.id WHERE d.id = ?",
                           [$delivery_id]);
        return ['locked' => true, 'by' => $current['name']];
    }
    return ['locked' => false];
}
```

UI ако locked:
```html
<div class="locked-banner q-amber">
  <strong>Доставката се обработва от Иван</strong>
  <p>Започнал е преди 3 минути. Изчакай или го попитай дали е готов.</p>
  <button onclick="forceUnlock()">Принудително отключи (admin only)</button>
</div>
```

---

## 44. PERFORMANCE BUDGETS

### 44.1 Page load targets (3G mobile, beta requirement)

| Page | Target | Strategy |
|---|---|---|
| `deliveries.php` (list) | <1.5s LCP | SSR first 5 cards, lazy load rest |
| `deliveries.php?id=X` (detail) | <1.2s LCP | All in single SSR; tabs lazy hydrated |
| OCR camera open | <0.8s to live preview | Preload `getUserMedia` permission |
| Voice rec start | <0.3s | Preload Web Audio context |
| Filter drawer open | <0.2s | CSS-only animation |
| Bulk select activate | <0.15s | No backend call |

### 44.2 Bundle size

| Asset | Target | Method |
|---|---|---|
| HTML page | <40KB gzip | Server-rendered minimal |
| CSS | <80KB gzip | Tailwind purge + design tokens only |
| JS | <120KB gzip | No framework; vanilla + minimal libs (BarcodeDetector polyfill) |
| Inline images (icons) | 0 bytes | SVG inline |
| Total per pageload | <240KB | — |

### 44.3 DB query budget

| Operation | Target | Note |
|---|---|---|
| List query (with filters) | <50ms | Use `idx_tenant_status` |
| Detail query | <80ms | 4 sub-queries для tabs |
| Finalize transaction | <500ms | Worst case 18 items × 4 ops = 72 ops |
| Search | <100ms | Full-text, debounce 250ms client |
| AI signal selection | <30ms | Pre-cached candidates |

### 44.4 Image strategy

- Product thumbnails: 80×80 webp, lazy load
- OCR scan preview: 320px width, blur-up placeholder
- No background images on cards (CSS gradients only)

---

## 45. ACCESSIBILITY (a11y)

### 45.1 Mandatory checks

| Check | Implementation |
|---|---|
| Semantic HTML | `<header>`, `<main>`, `<article>`, `<nav>`, `<aside>` |
| Landmark roles | `role="navigation"`, `role="dialog"`, `role="tablist"` |
| Focus state | `:focus-visible` ring 2px hsl(var(--hue1) 80% 60% / 0.5) |
| Keyboard nav | Tab → next focusable; Esc → close drawer/sheet |
| Screen reader labels | `aria-label` on icon-only buttons; `aria-live="polite"` for AI signals |
| Reduced motion | `@media (prefers-reduced-motion: reduce)` disables all animations |
| Color contrast | Body text ≥4.5:1; large text ≥3:1; verified per hue класс in light + dark |
| Touch targets | ≥44×44px (Apple HIG); spacing ≥8px |

### 45.2 Reduced motion (вече в DESIGN_SYSTEM §7)

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation: none !important;
    transition: none !important;
  }
}
```

### 45.3 Voice-only navigation (post-beta)

Wake-word "Хей AI":
- "Покажи закъснелите" → filter
- "Получи следващата" → next pending
- "Назад" → history.back()
- "Помощ" → AI chat overlay

### 45.4 Dyslexia mode (post-beta)

Setting: ON → font-family OpenDyslexic; line-height 1.7; letter-spacing 0.05em.

### 45.5 ARIA примери

```html
<button class="dd-meatball" aria-label="Меню за повече действия" aria-haspopup="menu">
  <svg aria-hidden="true" focusable="false">...</svg>
</button>

<div role="dialog" aria-labelledby="receiveSheetTitle" aria-modal="true">
  <h2 id="receiveSheetTitle">Как пристига доставката?</h2>
  ...
</div>

<aside class="notif-drawer" role="complementary" aria-label="Известия">
  <article class="notif-item" role="article" aria-label="Закъсняла доставка от Емпорио">
    ...
  </article>
</aside>
```

---

## 46. TEST SCENARIOS (BETA MANUAL TEST PROTOCOL)

### 46.1 Smoke tests (пускат се преди всеки deploy)

| # | Scenario | Expected |
|---|---|---|
| T1 | Login като Пешо → влез deliveries.php | Лесен mode, max 3 AI карти, 1 op-btn |
| T2 | Tap "Получи доставка" | Sheet с 4 опции |
| T3 | OCR на тестова фактура | Confidence ≥80%, 5+ items extracted |
| T4 | Voice "тениска синя 10 броя по 12 лв" | Парсва: name + qty=10 + cost=12 |
| T5 | Manual scan 3 баркода | 3 items в list; beep + green flash |
| T6 | Finalize delivery | inventory updated, lost_demand closed (ако applicable) |
| T7 | Login като Митко → toggle разширен | KPI strip + status tabs + 5+ AI signals |
| T8 | Filter accordion → Status=overdue | Списък само на overdue |
| T9 | Long-press card → bulk mode | Header lila, ✓ badge, bottom action bar |
| T10 | Detail на доставка → 4 tabs scroll | All tabs render < 0.5s |
| T11 | Detail на доставчик → 6 секции | All sections collapsible |
| T12 | Settings → Pricing rules → save | Persists, applies to new product |

### 46.2 Edge case tests

| # | Scenario | Expected |
|---|---|---|
| E1 | Internet OFF → OCR | Fallback to voice или manual |
| E2 | Internet OFF → manual scan | Cache hit → product matched |
| E3 | Internet OFF → blind receive | Saves локално; AI signal post-sync |
| E4 | Bluetooth OFF → finalize → print | PDF fallback; no error |
| E5 | OCR confidence <50% | Reject + fallback prompt |
| E6 | Cost increase 28% | AI signal P0 + push + email |
| E7 | Multi-store split prompt | AI suggest + manual override |
| E8 | Voice STT in dialect | Web Speech preferred; Whisper fallback |
| E9 | Concurrent receive (2 users) | Lock UI; second sees "обработва се от X" |
| E10 | 500-item delivery finalize | Progress bar; <30s total time |
| E11 | Email forward от unauthorized | Reject; spam log entry |
| E12 | Same SKU 2 deliveries same day | Aggregate inventory; both в audit log |

### 46.3 Performance tests

| # | Scenario | Threshold |
|---|---|---|
| P1 | List render 100 deliveries | <1.5s LCP on 3G |
| P2 | Filter with 5 active filters | <50ms query |
| P3 | OCR processing | <15s for 1MB image |
| P4 | Voice transcript finalize | <2s after stop |
| P5 | Finalize 18-item delivery | <500ms |
| P6 | Bluetooth print 47 labels | <60s total |

### 46.4 Accessibility tests

| # | Scenario | Expected |
|---|---|---|
| A1 | Keyboard-only navigation | All actions reachable via Tab + Enter |
| A2 | Screen reader (VoiceOver iOS) | All buttons announced; AI signals read |
| A3 | Reduced motion ON | No animations; instant transitions |
| A4 | Color contrast в dark mode | All text ≥4.5:1 measured |
| A5 | Touch targets | All interactive ≥44×44px |

---

## 47. BETA LAUNCH CHECKLIST (за 14-15.05.2026)

### 47.1 Code completeness

- [ ] `deliveries.php` главен — лесен + разширен mode
- [ ] `deliveries.php?id=X` детайл с 4 таба
- [ ] `partials/supplier-detail.php` shared компонент
- [ ] `partials/deliveries-easy.php` rendering
- [ ] `partials/deliveries-extended.php` rendering
- [ ] `api/deliveries/*` 22 endpoints
- [ ] `api/ocr/*` endpoints
- [ ] `api/voice/parse` endpoint
- [ ] `api/products/quick-create` (mini-wizard)
- [ ] `api/suppliers/match` fuzzy
- [ ] `api/orders/match` auto-link
- [ ] OCR worker (Gemini Vision integration)
- [ ] Email forward poller (cron 5min)
- [ ] AI signal selection engine
- [ ] Service worker for offline cache
- [ ] Bluetooth printer integration
- [ ] Push notification setup (FCM)

### 47.2 DB migration

- [ ] `deliveries` table
- [ ] `delivery_items` table
- [ ] `delivery_item_stores` table
- [ ] `delivery_events` table
- [ ] `scanner_documents` table
- [ ] `inventory_confidence` table
- [ ] `lost_demand` table (ако още няма)
- [ ] `saved_filters` table
- [ ] `recent_searches` table
- [ ] `pricing_rules` table
- [ ] `cost_history` table
- [ ] `notifications` table
- [ ] ALTER `suppliers` (12 нови полета)
- [ ] ALTER `products` (3 нови полета)
- [ ] Seed data (test supplier, test pricing rule)

### 47.3 Settings configured for ENI

- [ ] 5 stores created (Витоша/Цариградско/Студентски/Овча купел/Банкя)
- [ ] Default pricing rule (50% margin, .90 rounding)
- [ ] Email forward setup `eni-deliveries@runmystore.ai`
- [ ] Bluetooth printer paired (DTM-5811, MAC DC:0D:51:AC:51:D9)
- [ ] BG dual pricing ON (legal req)
- [ ] AI signals в Phase 1 (0% AI, 100% template)
- [ ] Notifications default ON for owner/manager
- [ ] Quiet hours 22:00-08:00

### 47.4 Visual canon compliance

- [ ] Bottom nav 4 икони locked (AI/Склад/Справки/Продажба)
- [ ] Header sticky 56px max 3 икони (search/voice/menu)
- [ ] Voice bar глобален element under header
- [ ] mode-toggle на разширен screen
- [ ] 6 hue класа only (no new colors)
- [ ] Sacred Neon Glass dark mode не изменен
- [ ] No emoji в UI (SVG only)
- [ ] No "Gemini" в UI ("AI" only)
- [ ] Mobile-first 375px target (Z Flip6 ~373px)
- [ ] Reduced motion respected

### 47.5 Acceptance tests

- [ ] T1-T12 (smoke) all PASS
- [ ] E1-E12 (edge) all PASS
- [ ] P1-P6 (perf) all PASS
- [ ] A1-A5 (a11y) all PASS

### 47.6 Rollback plan

- [ ] Git tag `beta-eni-2026-05-14` before deploy
- [ ] DB backup automated 06:30 daily
- [ ] Feature flag `ENABLE_DELIVERIES_V3=true` (за лесен rollback)
- [ ] Monitoring: Sentry за PHP errors; Plausible за UX events
- [ ] Hotfix process: tmux session always-on; Claude Code ready

### 47.7 Training за ENI staff

- [ ] Видео гайд 5 мин (за Пешо в лесен mode)
- [ ] Видео гайд 12 мин (за Митко разширен)
- [ ] PDF cheat sheet (1 страница)
- [ ] WhatsApp група за бърз support
- [ ] Първа седмица — Тихол on-call, real-time observation

---

## 48. ИНДЕКС НА ВРЪЗКИТЕ С ДРУГИ МОДУЛИ

| Module | Връзки от Доставки | Връзки към Доставки |
|---|---|---|
| **products.php** | INSERT нов (`is_complete=0`); UPDATE `cost_price` + history; никога UPDATE `quantity` directly | "Поръчай още" deep link |
| **orders.php** | UPDATE supplier_orders status; auto-link по supplier+date; "Поръчай липси" CTA | "Получи доставка" deep link от sent поръчка |
| **suppliers.php** | UPDATE last_delivery_date, total_deliveries, reliability_score; shared detail компонент | Tap supplier name в delivery detail |
| **inventory.php** | INSERT/UPDATE per (product, store); +0.40 confidence boost; multi-store split rows | Direct adjustment не минава през доставки |
| **sale.php** | Lost demand resolved cycle (UPDATE sale_items.product_id ghost → real) | Sale ghost → AI signal "Доставката пристига утре" |
| **stats.php** | Cost trends, supplier scorecard, lead time, lost ROI, gross margin | Deep link от supplier detail "Виж stats" |
| **chat.php / Life Board** | 40 deliv_* AI signal topics; cooldown 24h; selection engine | AI карта tap → deep link към delivery |
| **transfers.php** (post-beta) | Multi-store split → INSERT `transfers` rows | Transfer received → +0.30 confidence boost |
| **loyalty.php** (post-beta) | Lost demand resolved → SMS "Артикулът дойде" | — |
| **settings.php** | Pricing rules, notifications, label printer, email forward, multi-store split rules | — |

---

## 49. ФИНАЛНИ ПРИНЦИПИ — НИКОГА ДА НЕ СЕ НАРУШАВАТ

### 49.1 SACRED (от MASTER_COMPASS + DESIGN_PROMPT_v2)

1. **Закон №1** — Пешо не пише; voice/scan/photo only
2. **Закон №2** — PHP смята, AI вокализира
3. **Закон №3** — Никога "Gemini" в UI, само "AI"
4. **Закон №6** — `confidence_score` НИКОГА visible на Митко
5. **Закон №11** — DB names canonical (`code`, `cost_price`, `retail_price`, `quantity`)
6. **Bottom nav заключен** — 4 икони (AI/Склад/Справки/Продажба) — НЕ ПИПАЙ
7. **6 hue класа only** — q-default/magic/loss/gain/amber/jewelry
8. **Sacred Neon Glass** dark mode — никога simplify (oklch + plus-lighter + 4 spans)
9. **No emoji** в UI — SVG icons only
10. **Mobile-first 375px** — Z Flip6 target
11. **0 emoji в UI** — SVG only
12. **BG dual pricing** до 08.08.2026 — legal req
13. **No `ALTER TABLE ADD COLUMN IF NOT EXISTS`** в MySQL 8 — PREPARE/EXECUTE
14. **Audit log append-only** — никога DELETE/UPDATE на `delivery_events`
15. **Soft delete only** — никога hard DELETE; винаги `is_deleted=1`
16. **`priceFormat($amount, $tenant)`** — никога hardcoded "лв"/"BGN"/"€"
17. **`t('key')`** — i18n за всички UI текстове
18. **STT engine choice LOCKED** — commits 4222a66 + 1b80106; не пипай `_wizPriceParse`
19. **Phased rollout AI** — Фаза 1 = 0% AI, Фаза 2 = 30%, Фаза 3 = 80%
20. **Confidence routing** — >0.85 auto / 0.5-0.85 soft / <0.5 block

### 49.2 Принципи на разработка

- Always `git pull origin main` before changes
- Always `php -l` before commit
- Final filenames only — никога `_v2`, `_FINAL`, дати
- Python scripts за file edits — никога sed
- Multi-file deploy: tar+xz+base64 → /tmp/staging → diff → confirm → cp to /var/www
- Никога destructive ops без explicit approval (rm/chmod/git reset/DROP/TRUNCATE)
- tmux ВИНАГИ за Claude Code sessions
- End-of-session: dead code, duplicates, cleanup, handoff doc, commit

---

## 50. КАК ДА ИЗПОЛЗВАШ ТОЗИ ДОКУМЕНТ ЗА ДРУГИ МОДУЛИ

При планиране на ORDERS, TRANSFERS, INVENTORY и т.н. — копирай **същата структура** на този документ:

| Структура за всеки модул |
|---|
| 1. Философия & 5 закона приложими |
| 2. Място в навигацията + breadcrumb |
| 3. Двата режима + toggle |
| 4. **Глобален Connection Map** — таблица връзки с DB полета + deep links |
| 5. API endpoint mapping |
| 6. Цветова семантика (мapping на 6 hue класа към компоненти) |
| 7. Главен екран лесен/разширен с пълно HTML+CSS |
| 8. AI signals (топ темы + selection engine) |
| 9. Detail screen (tabs ако applicable) |
| 10. Shared детайл компонент (ако има, напр. supplier-detail) |
| 11. Bulk операции (long-press) |
| 12. Edit history & audit |
| 13. Notifications |
| 14. Специални flow-ове (offline, multi-store, edge cases) |
| 15. DB schema |
| 16. Performance budgets |
| 17. Accessibility |
| 18. Test scenarios |
| 19. Beta checklist |
| 20. Връзки с други модули (overview table) |

При планирането на връзки — **винаги** опиши и двете посоки:
- Какво се случва **от** твоя модул към другите
- Какво идва **към** твоя модул от другите

Това е ключът да се поддържат intractlly свързаните модули в синхрон.

---

# 🎯 КРАЙ НА ДОКУМЕНТА

**Версия:** v3 COMPLETE (09.05.2026)
**Размер:** ~50 секции, 6 части (1, 2A, 2B, 3A, 3B, 4A, 4B, 5A, 5B, 6A, 6B)
**За:** ENI beta launch 14-15.05.2026

**Следващи документи за писане:**
- `ORDERS_FINAL_v1.md` — копира структурата
- `TRANSFERS_FINAL_v1.md` — post-beta
- `INVENTORY_FINAL_v1.md` — post-beta
- `SUPPLIERS_FINAL_v1.md` — обогатяване на shared компонент
