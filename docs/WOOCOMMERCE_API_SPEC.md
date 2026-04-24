# WooCommerce Integration Spec — RunMyStore ↔ WooCommerce

**Създадена:** S81.WOO_API (CHAT 2)  
**Цел:** Reference за CSV export (CHAT 1 / S81) + REST API integration (S90)  
**Версия:** v1.0 — 24.04.2026

---

## 0. ВАЖНО ЗА CHAT 1 (CSV export)

CHAT 1 прави `export-csv.php` за **ръчен upload** в WooCommerce admin (Products → Import).  
Използва се WooCommerce-то **вградено** CSV importer (core, не платен плагин).  
Колонни имена са **case-sensitive** и трябва да съвпадат буквално с таблицата по-долу.

**CRITICAL правила (НЕ направяй грешка):**

1. **Booleans = `1` или `0`** (НИКОГА `true`/`false`/`yes`/`no`). Пример: `Published=1`, `Is featured?=0`.
2. **Prices = десетични без валутен символ** с точка (`.`) като разделител. Пример: `Regular price=149.99` (НЕ `€149.99`, НЕ `149,99`).
3. **Categories hierarchy:** `>` за вложени, `,` за multi-category. Пример: `Бижута > Пръстени, Подаръци`.
4. **Multiple images:** comma-separated URL-та, първата = featured. Пример: `https://.../1.jpg, https://.../2.jpg`.
5. **Variable products:** ПАРЕНТ + ВАРИАЦИИ в един CSV. Parent има `Type=variable`, вариациите `Type=variation` и `Parent` колона = `id:123` или SKU на parent-а.
6. **Stock management:** число в `Stock` колоната = manage_stock=true. Празно = WC не управлява наличност за продукта.
7. **UTF-8 encoding** задължително. Без BOM е безопасно.
8. **ID колона:** ако е попълнена = WC презаписва съществуващ продукт с това ID. За нови продукти = остави празна (WC генерира).

---

## 1. CSV Import Schema — Официални колонни имена

Източник: https://github.com/woocommerce/woocommerce/wiki/Product-CSV-Import-Schema

### Basic (задължителни + често използвани)

| CSV колона | Maps to | Тип / Пример | Notes |
|---|---|---|---|
| `ID` | id | `100` | Празно за нов продукт. Попълнено = UPDATE. |
| `Type` | type | `simple` / `variable` / `variation` / `grouped` / `external` | Задължително. Обикновен продукт = `simple`. Продукт с размери/цветове = `variable` (parent) + `variation` (child rows). |
| `SKU` | sku | `ENI-001` | Unique. Mapping: `products.code`. |
| `Name` | name | `Сребърен пръстен` | Mapping: `products.name`. |
| `Published` | status | `1` | `1`=publish, `0`=private, `-1`=draft. |
| `Is featured?` | featured | `0` | 1/0. |
| `Visibility in catalog` | catalog_visibility | `visible` | Values: `visible`, `catalog`, `search`, `hidden`. |
| `Short description` | short_description | `Ръчно изработен` | Plain text or HTML. |
| `Description` | description | `Пълно описание...` | HTML allowed. |
| `Date sale price starts` | date_on_sale_from | `2026-05-01` | YYYY-MM-DD или празно. |
| `Date sale price ends` | date_on_sale_to | `2026-05-15` | YYYY-MM-DD или празно. |
| `Tax status` | tax_status | `taxable` | Values: `taxable`, `shipping`, `none`. |
| `Tax class` | tax_class | `standard` | Default празно = standard. |
| `In stock?` | stock_status | `1` | 1/0. |
| `Stock` | stock_quantity | `20` | Число = manage_stock=true. Празно = без stock tracking. |
| `Low stock amount` | low_stock_amount | `3` | Опционален threshold. |
| `Backorders allowed?` | backorders | `0` | `0`, `1`, или `notify`. |
| `Sold individually?` | sold_individually | `0` | 1/0. |
| `Weight (kg)` | weight | `0.5` | Само число. Unit зависи от WC настройките. |
| `Length (cm)` | length | `10` | Само число. |
| `Width (cm)` | width | `5` | Само число. |
| `Height (cm)` | height | `2` | Само число. |
| `Allow customer reviews?` | reviews_allowed | `1` | 1/0. |
| `Purchase note` | purchase_note | `Благодарим!` | Текст след покупка. |
| `Sale price` | sale_price | `19.99` | Намалена цена или празно. |
| `Regular price` | regular_price | `24.99` | Задължително. Mapping: `products.retail_price / 100`. |
| `Categories` | category_ids | `Бижута > Пръстени` | CSV list. `>` = йерархия. Auto-created ако не съществуват. |
| `Tags` | tag_ids | `сребро, ръчна изработка` | CSV list. |
| `Shipping class` | shipping_class_id | `Име на класа` | Име на shipping клас или празно. |
| `Images` | image_id / gallery_image_ids | `https://.../img.jpg, https://.../img2.jpg` | Първата = featured. Auto-import в media library. |

### Variable product-specific

| CSV колона | Notes |
|---|---|
| `Parent` | За `variation` редове: `id:100` или SKU на parent-а. За `simple`/`variable` = празно. |
| `Grouped products` | За `grouped` тип: `id:100, id:101`. |
| `Upsells` | `id:100, id:101` или SKU. |
| `Cross-sells` | `id:100, id:101` или SKU. |
| `External URL` | Само за `external` тип. |
| `Button text` | Само за `external` тип. |
| `Position` | Menu order (int). |

### Attributes (размер, цвят, материал…)

За всеки атрибут: 5 колони на атрибут. WooCommerce поддържа Attribute 1, Attribute 2, Attribute 3... (неограничено).

| CSV колона | Тип / Пример | Notes |
|---|---|---|
| `Attribute 1 name` | `Размер` | Име на атрибута. Може да е global (WC Products → Attributes) или ad-hoc. |
| `Attribute 1 value(s)` | `S, M, L, XL` | CSV list на стойностите за parent. За `variation` ред: само 1 стойност. |
| `Attribute 1 visible` | `1` | 1/0. 1 = показва се в Additional Info tab. |
| `Attribute 1 global` | `1` | 1/0. 1 = taxonomy-based global attribute (препоръчително). 0 = local само за този продукт. |
| `Attribute 1 default` | `M` | Default стойност за variable product. Опционално. |

Повтаря се за `Attribute 2 name`, `Attribute 2 value(s)` и т.н.

**Preferred:** Attribute 1 = Размер, Attribute 2 = Цвят (унифицирано за RunMyStore export-и).

---

## 2. MAPPING: RunMyStore DB → WooCommerce CSV

| RunMyStore поле | WooCommerce CSV колона | Трансформация |
|---|---|---|
| `products.id` | — | Не се export-ва (WC генерира свой ID). |
| `products.code` | `SKU` | Direct copy. |
| `products.name` | `Name` | Direct copy. |
| `products.description` | `Description` | Direct copy (може HTML). |
| `products.retail_price` | `Regular price` | `retail_price / 100` ако се пази в cents, или direct ако euros. ПРОВЕРИ DB schema. |
| `products.sale_price` | `Sale price` | Същата трансформация. Празно ако NULL. |
| `products.cost_price` | — | НЕ се export-ва (internal). |
| `products.barcode` | `Meta: _barcode` | Custom meta колона (опционално). |
| `products.image_url` | `Images` | Direct URL. Ако multiple → comma-separated. |
| `products.is_active` | `Published` | `1` ако active, `0` ако не. |
| `inventory.quantity` | `Stock` | `SUM(quantity) WHERE product_id=X` (sum от всички складове). |
| `inventory.min_quantity` | `Low stock amount` | Mapping. |
| `categories.name` (join) | `Categories` | Hierarchy: `Parent > Child` ако има parent_id. |
| `product_variations.size` | `Attribute 1 value(s)` | Attribute 1 name="Размер". |
| `product_variations.color` | `Attribute 2 value(s)` | Attribute 2 name="Цвят". |
| `product_variations.retail_price` | `Regular price` (на variation row) | / 100 ако cents. |
| `product_variations.code` | `SKU` (на variation row) | Unique sku на вариацията. |

**КРИТИЧНО за CHAT 1:**
- Ако продукт НЯМА вариации = `Type=simple`, една CSV ред.
- Ако продукт ИМА вариации = `Type=variable` parent ред + по една `Type=variation` ред на всяка вариация.
- Parent редът: `Attribute 1 value(s)="S, M, L"` (всички стойности).
- Variation ред: `Attribute 1 value(s)="M"` (само една стойност), `Parent=ENI-001` (SKU на parent).
- Variation ред: `Regular price`, `SKU`, `Stock` попълнени, `Name` празно (WC генерира от parent + атрибути).
- Variation ред: `Attribute 1 visible`, `Attribute 1 global` ПРАЗНИ на variation редове (наследяват от parent).

---

## 3. CSV примери

### 3.1 Simple product (без вариации)

```
ID,Type,SKU,Name,Published,Is featured?,Visibility in catalog,Short description,Description,Regular price,Categories,Stock,In stock?,Images
,simple,ENI-101,Сребърна гривна,1,0,visible,,Ръчно изработена гривна,149.99,Бижута > Гривни,5,1,https://eni.bg/img/101.jpg
```

### 3.2 Variable product (с размери)

```
ID,Type,SKU,Name,Parent,Published,Regular price,Categories,Stock,In stock?,Attr 1 name,Attr 1 value(s),Attr 1 visible,Attr 1 global,Images
,variable,ENI-200,Сребърен пръстен,,1,,Бижута > Пръстени,,1,Размер,"52, 54, 56, 58",1,1,https://eni.bg/img/200.jpg
,variation,ENI-200-52,,ENI-200,1,199.99,,3,1,,52,,,
,variation,ENI-200-54,,ENI-200,1,199.99,,2,1,,54,,,
,variation,ENI-200-56,,ENI-200,1,209.99,,5,1,,56,,,
,variation,ENI-200-58,,ENI-200,1,209.99,,1,1,,58,,,
```

(Забележка: колоните в горния пример са абревиирани като `Attr 1 name` само за четимост. В истинския CSV използвай пълните имена `Attribute 1 name`, `Attribute 1 value(s)` и т.н.)

---

## 4. REST API Integration (S90 — бъдеща имплементация)

Тази секция е reference за S90. CHAT 1 може да я игнорира.

### 4.1 Endpoints

| Method | Endpoint | Цел |
|---|---|---|
| GET | `/wp-json/wc/v3/` | API index (test connection). |
| GET | `/wp-json/wc/v3/system_status` | Store info, WC version. |
| GET | `/wp-json/wc/v3/products` | List всички продукти. Query: `?per_page=100&page=1&sku=XYZ`. |
| POST | `/wp-json/wc/v3/products` | Create нов продукт. |
| GET | `/wp-json/wc/v3/products/{id}` | Get product by ID. |
| PUT | `/wp-json/wc/v3/products/{id}` | Update product. |
| DELETE | `/wp-json/wc/v3/products/{id}?force=true` | Delete (force=true прескача Trash). |
| POST | `/wp-json/wc/v3/products/{parent_id}/variations` | Create variation. |
| GET | `/wp-json/wc/v3/products/{parent_id}/variations` | List variations. |
| PUT | `/wp-json/wc/v3/products/{parent_id}/variations/{vid}` | Update variation. |
| GET | `/wp-json/wc/v3/products/categories` | List categories. |
| POST | `/wp-json/wc/v3/products/categories` | Create category. |
| GET | `/wp-json/wc/v3/products/attributes` | List global attributes. |
| POST | `/wp-json/wc/v3/products/attributes` | Create global attribute. |
| POST | `/wp-json/wc/v3/products/batch` | Batch create/update/delete до 100 items. |

### 4.2 Authentication

**HTTPS** → Basic Auth:

```
Authorization: Basic base64(consumer_key:consumer_secret)
```

**HTTP (не-SSL)** → OAuth 1.0a signature (сложно, избягва се). Изисква се HTTPS за production.

### 4.3 Product JSON payload (POST /products)

```
{
  "name": "Сребърен пръстен",
  "type": "variable",
  "sku": "ENI-200",
  "regular_price": "199.99",
  "description": "Пълно описание...",
  "short_description": "Кратко описание",
  "status": "publish",
  "catalog_visibility": "visible",
  "categories": [
    {"id": 15}
  ],
  "images": [
    {"src": "https://eni.bg/img/200.jpg", "position": 0}
  ],
  "attributes": [
    {
      "id": 1,
      "name": "Размер",
      "position": 0,
      "visible": true,
      "variation": true,
      "options": ["52", "54", "56", "58"]
    }
  ],
  "manage_stock": false,
  "meta_data": [
    {"key": "_barcode", "value": "3800123456789"}
  ]
}
```

**ВАЖНО:**
- `regular_price` е STRING (`"199.99"`), не число.
- Booleans са JSON `true`/`false` (НЕ 1/0 както в CSV).
- `categories` = array of `{id}`. Category-то трябва да съществува преди POST.
- `images` = array of `{src}` или `{id}`. `{src}` = URL, WC ще го import-не.
- `attributes[].id` = ID на global attribute. За ad-hoc (локален) = махаш `id` и оставяш `name`.

### 4.4 Variation JSON payload (POST /products/{parent_id}/variations)

```
{
  "regular_price": "199.99",
  "sku": "ENI-200-52",
  "manage_stock": true,
  "stock_quantity": 3,
  "image": {"src": "https://eni.bg/img/200-52.jpg"},
  "attributes": [
    {"id": 1, "option": "52"}
  ]
}
```

### 4.5 Response codes

| Code | Значение |
|---|---|
| 200 | OK (GET, PUT) |
| 201 | Created (POST) |
| 400 | Bad Request (invalid JSON, missing required field) |
| 401 | Unauthorized (лоши credentials) |
| 404 | Not Found |
| 500 | Server error |

### 4.6 Rate limits и best practices

- Без hard rate limit в core WC, но hosting може да налага (обикновено 60 req/min).
- За bulk операции → използвай `/products/batch` (до 100 items наведнъж).
- Retry strategy: exponential backoff на 429/500/502/503/504. Start 1s, max 30s, 5 retries.
- Timestamps: WC връща UTC. Конвертирай локално при нужда.
- SSL verification: в production задължително verify. В dev може `CURLOPT_SSL_VERIFYPEER=false`.

---

## 5. Webhook за reverse sync (S90+)

WooCommerce може да push-ва събития към RunMyStore при промяна на stock.

**Event topics (най-полезни):**
- `product.updated` — когато stock се промени в WC (online продажба).
- `product.deleted`
- `order.created` — нова онлайн поръчка → намаляваме stock в RunMyStore.
- `order.updated`

RunMyStore endpoint: `/woo-webhook.php?tenant_id=X&topic=Y`.  
Auth: HMAC-SHA256 secret в header `X-WC-Webhook-Signature`.  
Payload: JSON (същият като REST API response).

---

## 6. DB schema промени за S90 (БЕЗ МИГРАЦИЯ СЕГА — само reference)

```
-- tenants:
ALTER TABLE tenants ADD COLUMN woo_store_url VARCHAR(255) NULL;
ALTER TABLE tenants ADD COLUMN woo_consumer_key VARCHAR(100) NULL;
ALTER TABLE tenants ADD COLUMN woo_consumer_secret VARCHAR(100) NULL;
ALTER TABLE tenants ADD COLUMN woo_sync_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tenants ADD COLUMN woo_last_sync_at TIMESTAMP NULL;
ALTER TABLE tenants ADD COLUMN woo_webhook_secret VARCHAR(64) NULL;

-- products:
ALTER TABLE products ADD COLUMN woo_product_id BIGINT NULL;
ALTER TABLE products ADD COLUMN woo_sync_status ENUM('synced','dirty','failed','never') NOT NULL DEFAULT 'never';
ALTER TABLE products ADD COLUMN woo_last_sync_at TIMESTAMP NULL;
ALTER TABLE products ADD COLUMN woo_sync_error TEXT NULL;
ALTER TABLE products ADD INDEX idx_woo_sync (woo_sync_status, woo_last_sync_at);
ALTER TABLE products ADD INDEX idx_woo_id (woo_product_id);

-- product_variations:
ALTER TABLE product_variations ADD COLUMN woo_variation_id BIGINT NULL;
ALTER TABLE product_variations ADD COLUMN woo_sync_status ENUM('synced','dirty','failed','never') NOT NULL DEFAULT 'never';
ALTER TABLE product_variations ADD COLUMN woo_last_sync_at TIMESTAMP NULL;

-- categories:
ALTER TABLE categories ADD COLUMN woo_category_id BIGINT NULL;

-- sync log (нова таблица):
CREATE TABLE woo_sync_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  action ENUM('push','pull','webhook') NOT NULL,
  resource_type ENUM('product','variation','category','order') NOT NULL,
  resource_id BIGINT NOT NULL,
  woo_id BIGINT NULL,
  status ENUM('success','failed') NOT NULL,
  error_message TEXT NULL,
  request_payload JSON NULL,
  response_payload JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant_time (tenant_id, created_at),
  INDEX idx_resource (resource_type, resource_id)
);
```

НЕ прилагай тези SQL-и сега — S90 ще го направи след тест с реален WC store.

---

## 7. Test environment

- Test tenant: 7 (RunMyStore dev).
- За CHAT 1 CSV test: download CSV → install локален WordPress/WC → Products → Import → upload CSV → verify.
- За S90 API test: създай тестов WC store (https://wptest.runmystore.ai) с fake products.

---

## 8. Changelog

- v1.0 (24.04.2026) — Initial spec. S81.WOO_API research (CHAT 2). CSV schema + REST API reference + mapping table.
