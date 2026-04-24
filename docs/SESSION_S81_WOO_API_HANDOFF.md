# SESSION S81.WOO_API — HANDOFF

**Дата:** 24.04.2026  
**Тип:** CHAT 2 — паралелно на CHAT 1 (S81.PRODUCTS_COMPLETE)  
**Продължителност:** ~1 чат  
**Tag:** `v0.6.0-s81-woo-api-skeleton`  
**Статус:** ✅ ЗАВЪРШЕНО

---

## Цел на сесията

Подготовка на S90 infrastructure за директна WooCommerce REST API интеграция:
1. Research на WooCommerce REST API v3 + CSV import schema.
2. Spec документ за CHAT 1 (правилните CSV колонни имена за `export-csv.php`).
3. Skeleton на `integrations/woo.php` с stub функции.
4. Config `config/woo.php` с endpoints, payload builders, helpers.
5. DB schema промените документирани (БЕЗ миграция — S90 ще я направи).

---

## Какво е направено

### 1. ETAP 1 — Research ✅
Източници:
- https://woocommerce.github.io/woocommerce-rest-api-docs/
- https://github.com/woocommerce/woocommerce/wiki/Product-CSV-Import-Schema
- https://github.com/woocommerce/woocommerce-rest-api-docs (variations endpoint)

Ключови находки:
- REST API base: `/wp-json/wc/v3/` — Basic Auth (HTTPS) с consumer_key:consumer_secret.
- CSV importer: колонни имена case-sensitive, booleans = 1/0, categories с `>` йерархия.
- Variable products: parent ред с `Type=variable` + вариационни редове с `Type=variation` + `Parent=SKU`.
- Attributes: 5 колони на атрибут (name, value(s), visible, global, default).
- Prices в REST API са STRING (`"199.99"`), в CSV десетични без валутен символ.

### 2. ETAP 2 — `docs/WOOCOMMERCE_API_SPEC.md` ✅
340 реда. Раздели:
- §0 — CRITICAL правила за CHAT 1 (8 точки — booleans, prices, categories, images, variable format, stock, UTF-8, ID колона).
- §1 — CSV Import Schema с пълна таблица колонни имена.
- §2 — Mapping RunMyStore DB → WooCommerce CSV (per поле).
- §3 — CSV примери (simple, variable, multi-attribute).
- §4 — REST API endpoints + JSON payloads за S90.
- §5 — Webhook reverse sync план.
- §6 — DB schema промени за S90 (БЕЗ МИГРАЦИЯ).
- §7 — Test environment.

**Commit:** `871ce66` — push-нат рано за да може CHAT 1 да го ползва в `export-csv.php`.

### 3. ETAP 3 — `integrations/woo.php` skeleton ✅
230 реда. Класът `WooSync`:
- Конструктор: чете credentials от tenants (ако schema не е мигрирана → exception с ясно съобщение).
- `apiCall()` — работещ cURL wrapper с Basic Auth, JSON, error mapping.
- `testConnection()` — **РАБОТИ** (GET /system_status — за validate на credentials в settings.php S90).
- `pushProduct()`, `pullInventoryUpdates()`, `syncCategories()`, `batchPush()`, `handleWebhook()` — stubs с `throw Exception("not implemented — S90 stub")` и inline TODO с конкретни стъпки за S90.

Public helpers:
- `wooSyncTenant($tenant_id)` — cron entry-point (stub).
- `wooTestTenantConnection($tenant_id)` — за "Test connection" бутон в settings.

### 4. ETAP 4 — `config/woo.php` ✅
299 реда. Съдържание:
- `$WOO_ENDPOINTS` масив (18 endpoint-а с method+path шаблони).
- Constants: `WOO_ATTR_SIZE_NAME`, `WOO_ATTR_COLOR_NAME`, `WOO_DEFAULT_MANAGE_STOCK`, `WOO_BATCH_MAX`.
- `wooProductPayload($product_id, $tenant_id)` — builds JSON от DB (simple + variable, images, categories, attributes, meta).
- `wooVariationPayload($variation)` — builds variation JSON.
- `wooCategoryPayload($category)` — category с parent support.
- `wooPrice($cents)` — cents → euro string format (с assumption check).
- `wooEndpoint($key, $params)` — resolver за path params.
- `wooCategoryPath()` — hierarchy builder (`Parent > Child > Leaf`).

### 5. ETAP 5 — DB schema notes ✅
Документирани в `docs/WOOCOMMERCE_API_SPEC.md` §6. НЕ приложени (S90 ще ги направи след тест с реален WC).

Колонни промени планирани:
- `tenants`: woo_store_url, woo_consumer_key, woo_consumer_secret, woo_sync_enabled, woo_last_sync_at, woo_webhook_secret.
- `products`: woo_product_id, woo_sync_status ENUM, woo_last_sync_at, woo_sync_error + 2 индекса.
- `product_variations`: woo_variation_id, woo_sync_status, woo_last_sync_at.
- `categories`: woo_category_id.
- Нова таблица: `woo_sync_log` (full audit trail).

### 6. ETAP 6 — Communication с CHAT 1 ✅
- Spec-ът push-нат в commit 871ce66 (рано в сесията).
- CHAT 1 трябва да направи `git pull` и да прочете `docs/WOOCOMMERCE_API_SPEC.md` §0-§3 преди финализация на `export-csv.php`.

---

## Commits

| Hash | Message |
|---|---|
| 871ce66 | S81.WOO_API: WOOCOMMERCE_API_SPEC.md — CSV schema + REST API reference for CHAT 1 |
| f4b5daa | S81.WOO_API: woo.php skeleton + config/woo.php payload builders (S90 stubs) |

**Tag:** `v0.6.0-s81-woo-api-skeleton`

---

## CRITICAL бележки за CHAT 1 (CSV export)

Когато CHAT 1 прави `export-csv.php`, трябва да:

1. **Прочете `docs/WOOCOMMERCE_API_SPEC.md`** — особено §0 (CRITICAL правила) и §1 (колонни имена).
2. **Използва точно тези колонни имена** (case-sensitive):
   - `ID`, `Type`, `SKU`, `Name`, `Published`, `Is featured?`, `Visibility in catalog`, `Regular price`, `Categories`, `Stock`, `In stock?`, `Images`.
   - `Attribute 1 name`, `Attribute 1 value(s)`, `Attribute 1 visible`, `Attribute 1 global` (за variable products).
3. **Booleans = `1`/`0`** НЕ `true`/`false`. Особено за `Attribute N visible` и `global`.
4. **Prices = десетични без валутен символ**, точка разделител. `products.retail_price` е в cents → / 100.
5. **Categories hierarchy:** `Parent > Child`, multi: `Cat1, Cat2`.
6. **Variable products:** parent ред `Type=variable` + вариационни редове `Type=variation` с `Parent=SKU_на_parent`. На variation редовете: `Name` празно, `Attribute 1 visible`/`global` празни.
7. **UTF-8 encoding** (без BOM safe).
8. **Attribute naming конвенция:** Attribute 1 = "Размер", Attribute 2 = "Цвят" (за бъдеща S90 API компатибилност).

Пример CSV — виж §3 на spec документа.

---

## TODO за S90 (реална имплементация)

### DB миграция
```sql
-- виж docs/WOOCOMMERCE_API_SPEC.md §6 за пълния списък
ALTER TABLE tenants ADD COLUMN woo_store_url VARCHAR(255) NULL;
-- ... и т.н.
CREATE TABLE woo_sync_log (...);
```

### Функционалност за имплементация
1. **pushProduct($id)** — реален push на продукт + вариации към WC.
2. **pullInventoryUpdates($since)** — polling за stock changes.
3. **syncCategories()** — two-way category sync.
4. **batchPush()** — /products/batch за mass sync.
5. **handleWebhook()** — HMAC validation + dispatch.
6. **Retry/backoff** — exponential retry на 429/5xx.
7. **`/woo-webhook.php` endpoint** — за reverse sync.
8. **`cron-woo-sync.php`** — периодичен push на dirty products.
9. **settings.php UI** — "Свързване с WooCommerce" форма (store URL, consumer_key, consumer_secret, Test Connection бутон).
10. **Manual trigger** — "Push now" бутон в products.php detail view.

### Тестване преди production
- Locally: WordPress + WooCommerce Docker (wp-cli, включи pretty permalinks, генерирай keys).
- Staging: https://wptest.runmystore.ai с fake products.
- Production: ЕНИ (tenant 47) първо — след като CSV workaround е stabilized и клиентът има нужда.

---

## Рискове / отворени въпроси

1. **Price units:** products.retail_price в cents ли е или direct euros? `wooPrice()` използва heuristic — трябва direct проверка на DB schema в S90.
2. **Categories hierarchy:** RunMyStore има ли поле parent_id в categories? Ако не → всички категории са flat (no `>` в CSV).
3. **Global vs local attributes:** S90 трябва да регистрира "Размер"/"Цвят" като global WC attributes преди push. Алтернатива: local attributes (по-просто, но без cross-product consistency).
4. **Image handling:** `products.image_url` е един URL — но wizard може да генерира multiple images. В S90: поле за gallery URLs в products или separate product_images таблица?
5. **Webhook verification:** WC подава `X-WC-Webhook-Signature` с HMAC-SHA256 на payload. Secret се генерира при webhook creation — трябва да се store в `tenants.woo_webhook_secret`.
6. **Rate limits:** без официален limit, но hosting може да налага 60 req/min. Batch API е решението.

---

## За CHAT 1 — единствено действие

**Преди commit на `export-csv.php`:**

```bash
git pull origin main
less docs/WOOCOMMERCE_API_SPEC.md  # чете §0-§3
```

Ако имаш въпрос за CSV формат → отговорът е в spec-а. Ако нещо не е ясно → отвори issue в MASTER_COMPASS.md REWORK секцията.

---

## Следваща сесия за WooCommerce

**S90 — WooCommerce integration v1** (след S82 products done + първа ЕНИ продажба).

Prerequisites:
- [ ] DB миграция (виж §6).
- [ ] Тестов WC store с fake products.
- [ ] Тихол получава consumer_key/secret от ЕНИ WooCommerce admin.
- [ ] Settings UI за connection config.

След това: имплементация на pushProduct → pullInventoryUpdates → webhook → batchPush.

---

**Край на S81.WOO_API handoff.**
