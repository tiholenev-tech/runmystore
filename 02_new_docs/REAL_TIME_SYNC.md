# 📡 REAL_TIME_SYNC

## Мигновенна синхронизация за N магазина + N онлайн канала + N устройства

**Версия:** 1.0  
**Дата:** 21.04.2026  
**Статус:** Phase B (S88-S89) — CRITICAL  
**Real-world baseline:** tenant с 5 физически магазина + 2 онлайн магазина = 7+ активни канала  
**Принцип:** "Сървърът е единственият съдия. Клиентите питат, не спорят."  
**Цел:** < 1 секунда latency end-to-end. 0 overselling. Offline survives.

---

## 📑 СЪДЪРЖАНИЕ

1. Философия (3 правила)
2. Реалният случай (baseline)
3. Архитектура overview
4. Flow на всяко действие
5. Конфликти не съществуват — само ordering
6. Idempotency — защита от дубликати
7. Offline survival
8. Sync при reconnect
9. Webhook канали (WooCommerce/Shopify)
10. Event types (5 критични)
11. Клиентски код (минимален)
12. Latency budget
13. Cost & scale
14. DB schema
15. Implementation plan (5 сесии)
16. Testing
17. Защо е елегантно

---

# 1. ФИЛОСОФИЯ (3 ПРАВИЛА)

**Правило 1: Single source of truth = БД.**  
Никога клиент не решава нищо важно. Решава сървърът.

**Правило 2: Append-only event log.**  
Всяко действие е event. Events се записват в ред. Нищо не се overwrite-ва. Конфликтите не съществуват — само ordering.

**Правило 3: Един канал за tenant.**  
Независимо дали tenant-ът има 2 или 50 устройства — всички слушат ЕДИН канал. Клиентът филтрира какво го касае.

---

# 2. РЕАЛНИЯТ СЛУЧАЙ (BASELINE)

**Tenant ENI (tenant_id=52) сценарий:**

```
Магазин 1 (София, Витоша):  2 телефона активни
Магазин 2 (София, Младост): 1 телефон активен
Магазин 3 (Пловдив):         2 телефона активни
Магазин 4 (Варна):           1 телефон активен
Магазин 5 (Бургас):          1 телефон активен
Онлайн 1 (WooCommerce):      auto (24/7)
Онлайн 2 (Shopify):          auto (24/7)
Owner (Ени):                 1 телефон (observer + actions)

ОБЩО: 10 активни канала в tenant_id=52
```

**Черен петък 14:37:12.450 — паралелно случващо се:**

1. Магазин 1 Каса А продава последната черна блуза L
2. Магазин 3 Каса Б пита за същата блуза
3. WooCommerce получава онлайн поръчка за същата блуза
4. Shopify получава онлайн поръчка за същата блуза
5. Магазин 2 прави трансфер на 10 черни блузи към Магазин 1
6. Магазин 4 приема доставка — 50 черни блузи от доставчик
7. Ени от дома проверява stats

**7 паралелни събития на същия артикул в 2 секунди.**

**Какво НЕ трябва да се случи:**
- 3 продажби на 1 бройка (overselling)
- Магазин 3 да не разбере че няма блуза
- Shopify да продаде артикул който вече е продаден
- Transfer/доставка да изчезне при merge conflict

**Какво ТРЯБВА да се случи:**
- "Първият" (server timestamp) → печели
- Останалите получават graceful error с fallback suggestions
- Transfer и доставка актуализират inventory във всички магазини < 1s
- Ени вижда live metric update

---

# 3. АРХИТЕКТУРА OVERVIEW

```
[Магазин 1 каса А] ─┐
[Магазин 1 каса Б] ─┤
[Магазин 2 каса]   ─┤                    ┌─ MySQL (source of truth)
[Магазин 3 каса А] ─┤                    │   + event log (append-only)
[Магазин 3 каса Б] ─┼─▶ API Server ─────┤
[Магазин 4 каса]   ─┤   (PHP)           │
[Магазин 5 каса]   ─┤         │         └─ idempotency_log
[WooCommerce WH]   ─┤         │
[Shopify WH]       ─┤         ▼
[Owner phone]      ─┘    Pusher/Ably
                         (1 channel/tenant)
                              │
                              ▼
                    All clients listen (read-only)
```

**Компонентите:**
- **PHP API** — всяка write operation минава оттук
- **MySQL** — source of truth + event log
- **Pusher (или Ably)** — broadcast channel per tenant (`tenant-{id}`)
- **Clients** — pure listeners + pure senders, никаква local business logic

---

# 4. FLOW НА ВСЯКО ДЕЙСТВИЕ

## Примерна продажба

```
1. Каса А: click "Плати"
   └─ POST /api/sale {tenant_id, store_id, items, idempotency_key, device_id}

2. Сървър:
   ├─ Проверка idempotency_log (дубликат?)
   ├─ BEGIN TRANSACTION
   ├─ SELECT inventory WHERE product_id=X FOR UPDATE  (row lock)
   ├─ IF inventory.quantity >= requested:
   │     INSERT sale + sale_items
   │     UPDATE inventory (decrement)
   │     INSERT inventory_events (append-only)
   │     INSERT idempotency_log
   │     COMMIT
   │     Pusher.trigger('tenant-52', 'inventory.changed', {
   │         product_id, new_qty, store_id, source_device_id, reason: 'sale'
   │     })
   │     return {ok: true, sale_id}
   └─ ELSE:
         ROLLBACK
         return {ok: false, error: 'insufficient_stock', current_qty: X}

3. Каса А: UI update (продажба успешна)

4. Всички други 9 клиента в tenant-52 получават pusher event:
   ├─ Магазин 1 каса Б: inventory count −1 в UI
   ├─ Магазин 3 каса А: inventory count −1 в UI
   ├─ Owner phone: stats update live
   └─ WooCommerce worker: update stock в WooCommerce API
```

**Ключова точка:** Клиентите НЕ правят никаква бизнес логика. Receive pusher → update UI.

---

# 5. КОНФЛИКТИ — НЕ СЪЩЕСТВУВАТ (САМО ORDERING)

**Класически "конфликт":** 3 каси едновременно продават последната бройка.

**Решение:**
- 3 паралелни POST в рамките на 200ms
- MySQL `FOR UPDATE` сериализира → първият hvata lock-а
- Първият INSERT sale → COMMIT → OK
- Вторият: SELECT FOR UPDATE чака → inventory=0 → връща error
- Третият: същото

**UI response за загубилите:**
```json
{
  "ok": false,
  "error": "just_sold",
  "message": "Артикулът току-що беше продаден.",
  "similar": [array of подобни артикули с fuzzy match]
}
```

**Toast на потребителя:**
> "Съжалявам, артикулът току-що беше продаден в Магазин Пловдив. Имаш ли предвид:
> • Черна блуза XL (5 бр.)
> • Тъмносиня блуза L (3 бр.)"

---

# 6. IDEMPOTENCY — ЗАЩИТА ОТ ДУБЛИКАТИ

**Проблем:** Каса губи интернет за 3 секунди, retry-ва POST. Без защита → 2 продажби.

**Решение: idempotency_key.**

```javascript
// Клиент генерира UUID преди request
const key = crypto.randomUUID();

fetch('/api/sale', {
  method: 'POST',
  headers: {'Idempotency-Key': key},
  body: JSON.stringify({items: [...], device_id: DEVICE_ID})
});
```

```php
// Сървър проверява ПРЕДИ да изпълни
$existing = DB::run("SELECT result FROM idempotency_log WHERE `key`=?", [$key])->fetch();
if ($existing) {
    return json_decode($existing['result']);  // върни старото
}

// ... изпълни транзакцията ...

DB::run(
    "INSERT INTO idempotency_log (`key`, result, created_at) VALUES (?, ?, NOW())",
    [$key, $result_json]
);
```

**Cleanup cron:** Изтрива ключове > 24 часа daily.

---

# 7. OFFLINE SURVIVAL

**Философия:** Offline е **локален буфер**, не local-first. Сървърът винаги е истината.

## Какво работи offline:
- ✅ Продажби — записват се в IndexedDB queue с idempotency_key
- ✅ Приемане на доставка — запис в queue
- ✅ Броене в zone walk — запис в queue
- ✅ Търсене на артикули — cached от последен login
- ✅ Преглед на stats (cached) с overlay "Offline данни — последно: X мин"

## Какво НЕ работи offline:
- ❌ Трансфери между магазини — изисква двете online
- ❌ Онлайн продажби проверка
- ❌ Live stats — показва cached
- ❌ AI chat — "Няма връзка, опитай пак"

---

# 8. SYNC ПРИ RECONNECT

```javascript
// Всеки 30 сек проверка
if (navigator.onLine && hasQueuedEvents()) {
    const queue = getAllQueuedEvents();
    
    for (const event of queue) {
        try {
            const result = await fetch('/api/' + event.type, {
                method: 'POST',
                headers: {'Idempotency-Key': event.key},
                body: JSON.stringify(event.payload)
            });
            
            if (result.ok) {
                removeFromQueue(event.id);
            } else if (result.error === 'conflict') {
                showConflictUI(event, result);  // Owner/manager решава
            }
        } catch (e) {
            break;  // Network down — чака следващ опит
        }
    }
}
```

## Conflict UI при sync

**Пример:** Магазин offline продава 3 черни блузи. Междувременно Магазин 2 transfer-ира всички 5. При reconnect:

```json
{
  "error": "stock_insufficient_at_sync",
  "your_action": "3 продажби на черна блуза L (14:37, 14:42, 14:51)",
  "server_state": "Нямаше инвентар — прехвърлен в Магазин 2 в 14:35",
  "resolution_options": [
    "Потвърди продажбите (negative stock allowed — обяснение нужно)",
    "Отмени продажбите (връщане на пари на клиентите)",
    "Покажи ми детайлите"
  ]
}
```

**Само owner или manager решава.** Seller не може.

---

# 9. WEBHOOK КАНАЛИ (WOOCOMMERCE/SHOPIFY)

**Онлайн магазините се третират като "още един канал" в същия tenant.**

## Incoming webhook (онлайн продажба):
```
WooCommerce → POST /api/webhook/woocommerce
            → сървър валидира HMAC signature
            → INSERT sale със sale_type='online', channel='woocommerce'
            → SAME FOR UPDATE flow (race protection)
            → Pusher event → всички магазини виждат −1
```

## Outgoing sync (физическа продажба → обнови WooCommerce):
```
Физическа продажба → Pusher event
                  → WooCommerce worker (cron /1 мин)
                  → PUT /wp-json/wc/v3/products/123 {stock_quantity}
```

Пълна спецификация в `ECOMMERCE_INTEGRATION.md`.

---

# 10. EVENT TYPES (5 КРИТИЧНИ)

| Event | Кога | Payload | Кой слуша |
|---|---|---|---|
| `inventory.changed` | Продажба, доставка, трансфер, adjustment | `{product_id, store_id, new_qty, reason, source_device_id}` | Всички магазини + online worker |
| `sale.created` | Нова продажба | `{sale_id, store_id, total, items, cashier}` | Owner, managers |
| `transfer.initiated` | Нов transfer между магазини | `{transfer_id, from_store, to_store, items}` | Receiving store |
| `transfer.received` | Потвърдено получаване | `{transfer_id, confirmed_by}` | Sending store, owner |
| `delivery.received` | Доставка пристигнала | `{delivery_id, store_id, supplier, items}` | Owner, managers |

**Всички ходят в `tenant-{id}` канал.** Клиентът филтрира.

---

# 11. КЛИЕНТСКИ КОД (МИНИМАЛЕН)

```javascript
// Setup веднъж при login
const pusher = new Pusher(PUSHER_KEY, {cluster: 'eu'});
const channel = pusher.subscribe('tenant-' + TENANT_ID);

channel.bind('inventory.changed', (data) => {
    // Обнови UI ако виждам този продукт
    if (currentView.showsProduct(data.product_id)) {
        updateInventoryDisplay(data.product_id, data.new_qty);
    }
    
    // Toast ако в същия магазин и не аз съм source
    if (data.store_id === MY_STORE_ID && data.source_device_id !== MY_DEVICE_ID) {
        showToast(`${data.reason} — ${data.product_name}: ${data.new_qty} бр.`);
    }
});

channel.bind('transfer.initiated', (data) => {
    if (data.to_store === MY_STORE_ID) {
        showNotification(
            `Трансфер пристига: ${data.items.length} артикула от ${data.from_store_name}`
        );
    }
});

channel.bind('sale.created', (data) => {
    if (USER_ROLE === 'owner' || USER_ROLE === 'manager') {
        updateLiveStats(data);
    }
});

channel.bind('delivery.received', (data) => {
    if (USER_ROLE === 'owner' || USER_ROLE === 'manager') {
        showNotification(`Доставка прие: ${data.items.length} артикула`);
    }
});

channel.bind('transfer.received', (data) => {
    if (data.sending_store_id === MY_STORE_ID) {
        showNotification(`Трансферът до ${data.to_store_name} е потвърден`);
    }
});
```

**~30 реда код на целия клиент.** Това е.

---

# 12. LATENCY BUDGET

**Target:** < 1 секунда end-to-end.

**Breakdown:**
- Клиент POST /api/sale: 50-200ms (mobile network)
- Server processing (FOR UPDATE + INSERT + COMMIT): 20-50ms
- Pusher broadcast: 100-300ms
- Client receive + UI update: 50ms

**Total: 220-600ms.** Safely под 1 секунда.

**Fallback при Pusher down:**
- Long polling към `/api/events?since={timestamp}` на всеки 3 сек
- Latency: 1-3 сек (приемливо за edge case)

---

# 13. COST & SCALE

**Pusher pricing (2026):**
- Free tier: 100 concurrent + 200k messages/day — ok за 10-20 tenants
- Startup tier ($49/мес): 500 concurrent + 10M messages/мес — ok за 200-500 tenants
- Pro tier ($99/мес): 2,000 concurrent + 50M messages/мес — ok за 2000+ tenants

**Alternative: Ably (по-щедър free tier).**

**Очаквани messages per tenant per day:**
- Среден tenant (1 магазин, 2 устройства, 50 продажби): ~500 messages/day
- Big tenant (5 магазина, 10 устройства, 500 продажби): ~5,000 messages/day
- 200 tenants средни + 5 big = 100k + 25k = **125k/day** — под limit на Startup tier

**Препоръка:** Startup tier от S88 директно. Upgrade ако стане нужно.

---

# 14. DB SCHEMA

```sql
-- 1. Idempotency log (нова)
CREATE TABLE idempotency_log (
    `key` VARCHAR(64) PRIMARY KEY,
    result JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (created_at)
);

-- 2. Event log (append-only) — разширение на inventory_events
ALTER TABLE inventory_events 
    ADD COLUMN source_device_id VARCHAR(64),
    ADD COLUMN source_ip VARCHAR(45),
    ADD INDEX (tenant_id, created_at);

-- 3. Devices tracking
CREATE TABLE user_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_id VARCHAR(64) NOT NULL,
    device_name VARCHAR(100),
    user_agent TEXT,
    last_seen_at TIMESTAMP,
    UNIQUE (user_id, device_id)
);

-- 4. Online store channels registry (за WooCommerce/Shopify)
CREATE TABLE ecommerce_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    channel_type ENUM('woocommerce','shopify','other') NOT NULL,
    channel_name VARCHAR(100),
    api_url VARCHAR(500),
    api_key VARCHAR(255),
    api_secret VARCHAR(255),
    webhook_secret VARCHAR(64),
    last_sync_at TIMESTAMP,
    status ENUM('active','paused','error') DEFAULT 'active',
    INDEX (tenant_id, status)
);
```

---

# 15. IMPLEMENTATION PLAN (5 СЕСИИ)

| Сесия | Какво | Deliverable |
|---|---|---|
| **S88** | Pusher account + PHP SDK + 1 channel per tenant | tenant-52 channel works, test broadcast |
| **S89** | Server-side events emitter (sale, inventory, transfer, delivery) + MySQL FOR UPDATE locking | Race condition prevented за 2 каси |
| **S90** | Idempotency middleware + offline queue (IndexedDB) + retry logic | 3-sec интернет dropout не създава дубликати (→ S90 also WooCommerce integration) |
| **S91** | Sync at reconnect + conflict UI + Shopify webhook | Owner/manager resolve flow + online channel works |
| **S92** | Transfers multi-store + resolver + final polish | Tenant с 5 магазина + 2 онлайн напълно sync |

---

# 16. TESTING — КАК ГАРАНТИРАМЕ

**Load test:** 20 паралелни POST към същия артикул с qty=1 → очакваме 1 успешна + 19 errors. Автоматизирано в CI.

**Chaos test:** 
- Kill Pusher mid-transaction → падаме на long polling
- Kill MySQL replica → fail gracefully
- Kill network на 1 каса за 10 мин → sync-ва при reconnect без дубликати

**Integration test:**
- Симулираме сценария в §2 (7 паралелни събития)
- Очакваме: 1 sale успешна, 6 errors с graceful UI, 0 overselling

**Production monitoring:**
- Dashboard: messages/sec per tenant, latency p95/p99
- Alert при race condition > 0.5% от транзакциите

---

# 17. ЗАЩО ТОВА Е ЕЛЕГАНТНО

- **30 реда клиентски код** — pure listener
- **1 Pusher канал per tenant** — не 7, не 17, винаги един
- **MySQL FOR UPDATE** решава 95% от race conditions без нов код
- **Idempotency key** е 1 header + 5 реда server middleware
- **Server винаги печели** — няма merge conflicts, CRDT, voting
- **Същата архитектура** за 2 устройства и за 50

**Никаква special logic за multi-store.** Tenant има N канала → 1 Pusher channel + N devices слушат.

---

**КРАЙ НА REAL_TIME_SYNC.md**
