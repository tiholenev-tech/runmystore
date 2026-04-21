# 🛒 ECOMMERCE_INTEGRATION

## WooCommerce + Shopify интеграция за RunMyStore.ai

**Версия:** 1.0  
**Дата:** 21.04.2026  
**Статус:** Phase B (S90-S91) — rescheduled от Phase 6 заради beta tenant  
**Причина за по-ранна интеграция:** beta tenant (или ENI разширение) ще има онлайн магазин  
**Target pricing:** START plan включва WooCommerce, PRO включва Shopify

---

## 📑 СЪДЪРЖАНИЕ

1. Защо това е критично за Phase B
2. 3 типа продажби (retail / wholesale / online)
3. WooCommerce интеграция
4. Shopify интеграция
5. Общ flow (и за двата)
6. DB schema
7. Multi-channel inventory sync
8. Stripe Connect за online
9. Български специфики (ДДС, Econt, Speedy)
10. Edge cases
11. Implementation plan
12. Testing

---

# 1. ЗАЩО Е КРИТИЧНО ЗА PHASE B

**Beta tenant изисква онлайн магазин от ден 1 на бета тестването.** Фаза 6 (post-launch, S115+) = твърде късно.

**Приоритет:**
1. **WooCommerce първо** — български пазар доминиран от WooCommerce (Wordpress базирани сайтове)
2. **Shopify второ** — за чуждестранни (PRO tier)

**Rescheduling:**
- Старо: Phase 6 (S115+)
- Ново: **Phase B, S90 (WooCommerce) + S91 (Shopify)**

---

# 2. 3 ТИПА ПРОДАЖБИ

Всяка продажба в RunMyStore има `sale_type`:

| sale_type | Описание | Канал | Документ |
|---|---|---|---|
| `retail` | Физическо лице, в магазина | Offline/Online магазин | Стокова разписка |
| `wholesale` | B2B клиент (фирма) | Физически магазин | Фактура с ДДС |
| `online` | WooCommerce / Shopify | Онлайн магазин | Електронна бележка + фактура по желание |

**Inventory се намалява веднъж, независимо от типа.**

---

# 3. WOOCOMMERCE ИНТЕГРАЦИЯ

## 3.1 Концепция

Пешо (или ISR partner) setup-ва WooCommerce магазин. Свързва го с RunMyStore.

**Setup path:**
1. Ени влиза в `settings.php` → "Онлайн магазини" → "Добави WooCommerce"
2. Въвежда:
   - URL на магазина (напр. `https://eni-moda.bg`)
   - Consumer Key (от WooCommerce → Settings → Advanced → REST API)
   - Consumer Secret
3. RunMyStore тества връзката → ако OK → запис в `ecommerce_channels`
4. Автоматичен setup на webhooks в WooCommerce (order.created, order.updated)

## 3.2 API Details

**WooCommerce REST API v3:**
- Base: `https://eni-moda.bg/wp-json/wc/v3/`
- Auth: Basic (Consumer Key + Secret)
- Products: `GET/POST/PUT /products`
- Orders: `GET /orders`, webhook `order.created`
- Stock: `PUT /products/{id}` с `stock_quantity`

## 3.3 Product sync (RunMyStore → WooCommerce)

**При добавяне/редакция на артикул в RunMyStore:**

```php
function syncProductToWooCommerce($product_id, $channel_id) {
    $product = getProduct($product_id);
    $channel = getChannel($channel_id);
    
    $payload = [
        'name' => $product['name'],
        'sku' => $product['code'],
        'regular_price' => (string)$product['retail_price'],
        'stock_quantity' => getCurrentStock($product_id),
        'manage_stock' => true,
        'status' => $product['is_active'] ? 'publish' : 'draft',
        'images' => [
            ['src' => $product['image_url']]
        ],
        'categories' => [/* mapped from RunMyStore categories */],
        'meta_data' => [
            ['key' => '_runmystore_product_id', 'value' => $product_id]
        ]
    ];
    
    // Първи път → POST, следващи → PUT
    $wc_product_id = $product['wc_product_id'] ?? null;
    if (!$wc_product_id) {
        $response = wcRequest('POST', '/products', $payload, $channel);
        DB::run("UPDATE products SET wc_product_id=? WHERE id=?", 
                [$response['id'], $product_id]);
    } else {
        wcRequest('PUT', "/products/{$wc_product_id}", $payload, $channel);
    }
}
```

**Trigger points:**
- При save в products.php wizard
- При inventory.changed event (Pusher) → update stock
- Bulk sync при първоначален setup

## 3.4 Order sync (WooCommerce → RunMyStore)

**Webhook handler:**

```php
// POST /api/webhook/woocommerce
$signature = $_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'] ?? '';
$body = file_get_contents('php://input');
$secret = $channel['webhook_secret'];

// Verify HMAC signature
$expected = base64_encode(hash_hmac('sha256', $body, $secret, true));
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit;
}

$order = json_decode($body, true);

// Map WooCommerce order → RunMyStore sale
$sale_id = createSale([
    'tenant_id' => $channel['tenant_id'],
    'store_id' => $channel['default_store_id'],  // online orders го assign-ват към "primary"
    'sale_type' => 'online',
    'channel' => 'woocommerce',
    'channel_order_id' => $order['id'],
    'customer_name' => $order['billing']['first_name'] . ' ' . $order['billing']['last_name'],
    'customer_email' => $order['billing']['email'],
    'total' => $order['total'],
    'status' => mapStatus($order['status']),  // processing → confirmed
    'items' => mapItems($order['line_items'])
]);

// Inventory automatic update (sale creation triggers it)
// Pusher event → всички магазини виждат

http_response_code(200);
echo json_encode(['ok' => true]);
```

## 3.5 Stock sync (bidirectional)

**RunMyStore → WooCommerce (at inventory.changed):**
```
Продажба в Магазин 1 → inventory.changed Pusher event
                    → WooCommerce worker (cron /1 мин или immediate)
                    → PUT /products/{wc_id} stock_quantity=новото
```

**WooCommerce → RunMyStore (at order):**
```
Онлайн поръчка в WC → webhook → sale created в RMS → inventory decrement
```

## 3.6 Conflicts

**Race:** Физическа продажба + онлайн поръчка на последната бройка.

**Решение (вече в REAL_TIME_SYNC.md):**
- MySQL FOR UPDATE на inventory → първият печели
- Вторият получава error → за webhook връщаме 200 + логваме
- Online customer вижда "Out of stock" при checkout → refund ако вече е платил
- Edge case: ако customer вече е платил → trigger refund flow автоматично

---

# 4. SHOPIFY ИНТЕГРАЦИЯ

## 4.1 Концепция

**Shopify различия от WooCommerce:**
- Хостван (няма self-hosted) — tenant създава account при Shopify
- OAuth-based auth (не Consumer Key)
- По-строги limits (API rate limiting)
- По-богат feature set (Shopify Markets, POS и др.)

**Setup path:**

**Option A — Shopify AI (tier PRO):**
RunMyStore създава автоматично магазин (`pesho.myshopify.com`) чрез Shopify Partners API. Пешо не въвежда нищо.

**Option B — Existing Shopify magazin:**
Пешо има Shopify магазин → инсталира "RunMyStore App" от Shopify App Store → OAuth flow → свързване.

## 4.2 Shopify App Architecture

Това е **отделен проект** — Node.js/PHP app registered в Shopify Partners:
- Listens за OAuth callbacks
- Subscribes за webhooks (orders/create, inventory_levels/update, products/update)
- Forwards към главния RunMyStore API

**Alternative (по-прост подход):** Custom App per tenant — Пешо генерира access token в собствения си Shopify Admin → дава го на RunMyStore. Работи за 1-10 tenants, не скалира.

**Препоръка за S91:** Custom App (ручен setup). Public App е за месеци 3-6 след launch.

## 4.3 Product sync

```php
function syncProductToShopify($product_id, $channel_id) {
    $product = getProduct($product_id);
    $channel = getChannel($channel_id);
    
    $payload = [
        'product' => [
            'title' => $product['name'],
            'body_html' => $product['description'],
            'vendor' => getSupplierName($product['supplier_id']),
            'product_type' => getCategoryName($product['category_id']),
            'variants' => [[
                'sku' => $product['code'],
                'price' => (string)$product['retail_price'],
                'inventory_quantity' => getCurrentStock($product_id),
                'inventory_management' => 'shopify'
            ]],
            'images' => [
                ['src' => $product['image_url']]
            ]
        ]
    ];
    
    $shopify_product_id = $product['shopify_product_id'] ?? null;
    if (!$shopify_product_id) {
        $response = shopifyRequest('POST', '/admin/api/2024-04/products.json', $payload, $channel);
        DB::run("UPDATE products SET shopify_product_id=? WHERE id=?", 
                [$response['product']['id'], $product_id]);
    } else {
        shopifyRequest('PUT', "/admin/api/2024-04/products/{$shopify_product_id}.json", $payload, $channel);
    }
}
```

## 4.4 Order webhook (Shopify → RunMyStore)

Същата логика като WooCommerce, но HMAC verification е различен:

```php
$hmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
$body = file_get_contents('php://input');
$computed = base64_encode(hash_hmac('sha256', $body, $channel['webhook_secret'], true));
if (!hash_equals($computed, $hmac)) {
    http_response_code(401);
    exit;
}
// ... мап-ване към sale както в WooCommerce
```

---

# 5. ОБЩ FLOW (И ЗА ДВАТА)

## Онлайн продажба → физически магазини виждат −1

```
1. Customer на eni-moda.bg поръчва (или eni.myshopify.com)
2. Платформа изпраща webhook към RunMyStore (/api/webhook/{channel})
3. RunMyStore валидира HMAC
4. BEGIN TRANSACTION
5. SELECT inventory FOR UPDATE (row lock)
6. INSERT sale със sale_type='online', channel='woocommerce' или 'shopify'
7. DECREMENT inventory
8. INSERT inventory_events
9. COMMIT
10. Pusher.trigger('tenant-{id}', 'inventory.changed')
11. Всички 10 устройства в tenant получават update < 1s
12. WooCommerce/Shopify НЕ получават back-sync (те вече знаят — те bronirahta tази продажба)
```

## Физическа продажба → онлайн магазини виждат −1

```
1. Магазин 1 продажба (offline)
2. INSERT sale, DECREMENT inventory
3. Pusher.trigger('tenant-{id}', 'inventory.changed')
4. WooCommerce worker (cron /1 мин или event listener)
5. PUT /products/{wc_id} с new stock_quantity
6. Shopify worker (same)
7. PUT /products/{shopify_id}/variants/{variant_id}
8. Онлайн магазинът показва актуалната наличност
```

## Race condition: 1 бройка, физическа каса + онлайн customer

```
t=0:     Customer добавя в количка онлайн (НЕ намалява inventory в RunMyStore)
t=2sec:  Каса сканира → POST /api/sale → FOR UPDATE lock
         └─ Inventory намалява от 1 на 0
         └─ Pusher event: new_qty=0
         └─ WooCommerce worker immediate: PUT stock_quantity=0
t=4sec:  Customer натиска "Плати"
         └─ WooCommerce проверява stock → 0
         └─ Customer вижда "Out of stock" на checkout
         └─ Плащане не се обработва
```

**Резултат:** 0 overselling. Customer leicht разочарован, но няма финансов проблем.

**Edge case:** Ако Woo customer вече е платил ПРЕДИ физическата продажба (t<-1):
- Webhook създава sale в RunMyStore
- Физическа продажба получава error → "Продадено онлайн преди 3 сек"
- Каса cashier казва на клиент "Съжаляваме, продадено онлайн"
- AI предлага подобни артикули

---

# 6. DB SCHEMA

```sql
-- 1. E-commerce channels registry (от REAL_TIME_SYNC.md)
CREATE TABLE ecommerce_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    channel_type ENUM('woocommerce','shopify','other') NOT NULL,
    channel_name VARCHAR(100),
    api_url VARCHAR(500),
    api_key VARCHAR(255),
    api_secret VARCHAR(255),
    access_token VARCHAR(500),      -- за Shopify OAuth
    webhook_secret VARCHAR(64),
    default_store_id INT,            -- кой физически магазин чиято inventory отразява
    last_sync_at TIMESTAMP,
    status ENUM('active','paused','error') DEFAULT 'active',
    INDEX (tenant_id, status)
);

-- 2. Products → external channels mapping
ALTER TABLE products
    ADD COLUMN wc_product_id BIGINT NULL,
    ADD COLUMN shopify_product_id BIGINT NULL,
    ADD COLUMN shopify_variant_id BIGINT NULL,
    ADD INDEX (wc_product_id),
    ADD INDEX (shopify_product_id);

-- 3. Sales → external channels mapping
ALTER TABLE sales
    ADD COLUMN sale_type ENUM('retail','wholesale','online') DEFAULT 'retail',
    ADD COLUMN channel VARCHAR(50) NULL,       -- 'woocommerce', 'shopify', NULL за offline
    ADD COLUMN channel_order_id VARCHAR(100) NULL,
    ADD COLUMN customer_name VARCHAR(255) NULL,
    ADD COLUMN customer_email VARCHAR(255) NULL,
    ADD INDEX (channel, channel_order_id);

-- 4. Sync queue (за retry при failure)
CREATE TABLE ecommerce_sync_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    channel_id INT NOT NULL,
    action ENUM('product_create','product_update','stock_update') NOT NULL,
    payload JSON,
    attempts TINYINT DEFAULT 0,
    next_retry_at TIMESTAMP NULL,
    status ENUM('pending','succeeded','failed') DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (status, next_retry_at)
);
```

---

# 7. MULTI-CHANNEL INVENTORY SYNC

**Принцип:** RunMyStore е single source of truth. Всеки external channel получава same stock_quantity.

## Deadlock prevention

**Не прави бесконечен loop:** 
- RunMyStore → WC (update) → WC webhook back към RMS → LOOP

**Решение:** 
- Когато RunMyStore обновява WC → маркираме `_runmystore_sync=true` в meta
- Когато получим webhook за product.updated → ако има този marker → ignore

## Rate limiting

- WooCommerce: няма hard limit, но > 100 req/min може да stress-не сървъра
- Shopify: 2 calls/sec default, 4 calls/sec за Plus

**Решение:** Queue-based batch updates (ecommerce_sync_queue) — cron worker процесира 50/min.

---

# 8. STRIPE CONNECT ЗА ONLINE

**Плащания:**
- WooCommerce default → WooCommerce Stripe plugin → директно към Пешо
- Shopify default → Shopify Payments (Stripe under the hood) → към Пешо

**RunMyStore НЕ минава през плащането** за online. Само записва продажбата.

**Exception:** Ако Пешо иска unified billing (всички канали през RunMyStore Stripe Connect) → trial feature за PRO plan → Phase C.

---

# 9. БЪЛГАРСКИ СПЕЦИФИКИ

## ДДС

**WooCommerce:** Има native ДДС support → Пешо configure-ва веднъж → RunMyStore чете `tax_class` при webhook.

**Shopify:** Markets feature → ДДС правилно calculated по region.

**RunMyStore записва:**
```sql
ALTER TABLE sales
    ADD COLUMN vat_rate DECIMAL(4,2) DEFAULT 20.00,  -- 20% стандарт
    ADD COLUMN vat_amount DECIMAL(10,2),
    ADD COLUMN total_without_vat DECIMAL(10,2);
```

## Econt / Speedy

**WooCommerce:** Има български plugins за Econt/Speedy (Konrad-BG, eKontakt).

**Интеграция с RunMyStore:**
- Webhook вкарва метаданни за куриер в `sales.shipping_meta` JSON
- AI на Пешо: "Онлайн поръчка #142 — Econt, Варна, очаквано доставяне 23.04"

**Phase C:** Директна Econt/Speedy API integration (печат на товарителници от RunMyStore).

## BG → EU преход (8.8.2026)

- До 8.8.2026: цени се показват и в лева и в евро
- След: само евро
- WooCommerce/Shopify trebвa да отразяват същото
- RunMyStore emit-ва stock_price_updated event → channel worker update-ва

---

# 10. EDGE CASES

| Случай | Решение |
|---|---|
| Онлайн платена поръчка, но stock=0 при fulfill | Автоматичен refund (Stripe API) + email до customer |
| WC product manual update (Пешо go промени директно в WC) | Webhook product.updated → RunMyStore приема промяната → emit към Shopify |
| Shopify sync fail 3× (API down) | Ecommerce_sync_queue → retry exponential backoff → след 10 min Ени получава notification |
| Tenant disable channel | `ecommerce_channels.status='paused'` → worker пропуска |
| Channel webhook нарязан | Polling fallback всеки 15 мин — `GET /orders?after=last_sync` |
| Duplicate order webhook (двойно изпращане) | idempotency_log on `channel_order_id` → втори webhook връща 200 без side effects |

---

# 11. IMPLEMENTATION PLAN

## S90 — WooCommerce v1 (5-6 часа работа)

1. DB migrations (`ecommerce_channels`, ALTERs на products/sales)
2. `settings.php` → нов раздел "Онлайн магазини"
3. UI form за добавяне на WooCommerce channel
4. Connection test endpoint
5. Product create/update sync
6. Webhook endpoint за orders
7. Stock update worker (cron /1 мин)
8. First successful end-to-end test на beta tenant

## S91 — Shopify v1 (5-6 часа работа)

1. Custom App flow (access token manual setup)
2. Shopify API wrappers
3. Product sync (вариации!)
4. Webhook endpoint
5. HMAC verification (различна от Woo)
6. Stock update worker
7. End-to-end test

---

# 12. TESTING

**Unit:**
- HMAC verification за Woo + Shopify
- Payload mappers (order → sale)
- Deadlock prevention (marker check)

**Integration:**
- Local WooCommerce docker → fake products → webhook → RunMyStore → asserted sale
- Shopify dev store (free) → same flow
- Race condition: POST /sale + webhook order → expect 1 success + 1 graceful decline

**E2E:**
- Beta tenant real WooCommerce store
- Real products sync, real order flow

---

**КРАЙ НА ECOMMERCE_INTEGRATION.md**
