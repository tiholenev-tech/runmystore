# 📘 DOC 05 — DB ФУНДАМЕНТ

## Базата данни която не се чупи

**Версия:** 1.0 | **Дата:** 21.04.2026 | **Част от:** Група 3: DB И ИНФРАСТРУКТУРА

---

## 📑 СЪДЪРЖАНИЕ

1. Философия на фундамента
2. Schema migrations система
3. Money cents + currency
4. Quantity millis
5. Soft delete pattern
6. Audit log + source tracking
7. Transaction wrapper `tx()`
8. Negative stock guard
9. Cached stock reconciliation
10. Timezone UTC
11. Parked sale allocated
12. Tenant isolation + composite FK
13. Multi-store resolver
14. Idempotency keys
15. Stock movements ledger (append-only)
16. operation_id + Global Undo
17. Event queue + DLQ
18. State machines
19. FK + CHECK constraints
20. Cron heartbeat
21. 20-те нови таблици

---

# 1. ФИЛОСОФИЯ НА ФУНДАМЕНТА

Цитатът от Gemini: *„Сложността е данък върху скоростта. Заковей здраво мазето днес, за да можеш да строиш етажи утре без страх от срутване."*

DB фундаментът не е feature. Е **мазето**. Ако пропуснем schema_migrations, money_cents, soft delete, audit log сега — след 6 месеца не можем да добавим поръчки, sale rewrite, inventory v4 без да рискуваме цялата платформа.

**Правило:** всичко тук се прави в Phase A (S80-S82), **преди** който и да е нов модул.

---

# 2. SCHEMA MIGRATIONS СИСТЕМА

## 2.1 Проблемът

В момента DB промените се правят ръчно. Нямаме версиониране. Ако Claude прави грешка в една сесия — няма rollback.

## 2.2 Решение

```sql
CREATE TABLE schema_migrations (
    version VARCHAR(20) PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    checksum VARCHAR(64) NOT NULL,
    applied_at DATETIME NOT NULL,
    applied_by VARCHAR(100) NOT NULL,
    execution_time_ms INT NOT NULL,
    rollback_sql TEXT NULL
);
```

## 2.3 Migration файл формат

```
/migrations/
  20260421_001_add_money_cents.up.sql
  20260421_001_add_money_cents.down.sql
  20260421_002_soft_delete.up.sql
  20260421_002_soft_delete.down.sql
```

## 2.4 PHP runner

```php
class Migrator {
    public function up() {
        $pending = $this->getPendingMigrations();
        foreach ($pending as $migration) {
            $sql = file_get_contents($migration['file_up']);
            $checksum = hash('sha256', $sql);

            $existing = DB::run("SELECT checksum FROM schema_migrations WHERE version=?",
                [$migration['version']])->fetch();
            if ($existing && $existing['checksum'] !== $checksum) {
                throw new Exception("Migration $migration[version] tampered!");
            }

            DB::beginTransaction();
            try {
                $start = microtime(true);
                DB::exec($sql);
                $elapsed = (microtime(true) - $start) * 1000;

                DB::run("INSERT INTO schema_migrations
                         (version, name, checksum, applied_at, applied_by, execution_time_ms, rollback_sql)
                         VALUES (?, ?, ?, NOW(), ?, ?, ?)",
                    [$migration['version'], $migration['name'], $checksum,
                     $this->getSessionId(), $elapsed,
                     file_get_contents($migration['file_down'])]);
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                exit(1);
            }
        }
    }
}
```

## 2.5 Правила

1. Всяка DB промяна минава през migration файл
2. Migration e immutable — ако е applied, не се променя
3. Rollback SQL задължителен
4. Checksum enforcement
5. One command: `git pull && migrate:up`

---

# 3. MONEY CENTS + CURRENCY

## 3.1 Проблемът

`sales.total DECIMAL(10,2)`. Floating point грешки. €19.99 + €0.01 ≠ €20.00 в някои случаи.

## 3.2 Решение

Всички парични стойности като **INTEGER cents + currency код**.

```sql
ALTER TABLE sales
  ADD COLUMN total_cents BIGINT NOT NULL DEFAULT 0,
  ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'EUR',
  ADD CHECK (total_cents >= 0);

UPDATE sales SET total_cents = ROUND(total * 100);
```

## 3.3 Обхват

- `sales.total_cents`
- `sale_items.unit_price_cents`
- `products.retail_price_cents`
- `products.cost_price_cents`
- `products.wholesale_price_cents`
- `invoices.amount_cents`
- `deliveries.total_cost_cents`
- `orders.total_budget_cents`

## 3.4 Helper функции

```php
function moneyCentsToFloat($cents) { return $cents / 100; }
function moneyFloatToCents($float) { return (int) round($float * 100); }

function moneyFormat($cents, $tenant) {
    $amount = $cents / 100;
    $currency = $tenant['currency'] ?? 'EUR';

    switch ($currency) {
        case 'EUR':
            return number_format($amount, 2, ',', '.') . ' €';
        case 'BGN':
            return number_format($amount, 2, ',', ' ') . ' лв';
        default:
            return number_format($amount, 2) . ' ' . $currency;
    }
}

// BG dual price (до 8.8.2026)
function moneyFormatBgDual($cents, $tenant) {
    if ($tenant['country_code'] !== 'BG') {
        return moneyFormat($cents, $tenant);
    }
    $cutoff = strtotime('2026-08-08');
    if (time() > $cutoff) {
        return moneyFormat($cents, array_merge($tenant, ['currency' => 'EUR']));
    }
    $eur = $cents / 100;
    $bgn = $eur * 1.95583;
    return sprintf('%s € (%s лв)',
        number_format($eur, 2, ',', '.'),
        number_format($bgn, 2, ',', ' ')
    );
}
```

## 3.5 Правила

1. Никога не правиш аритметика с float на пари
2. Винаги конвертираш в cents на входа
3. Никога `DECIMAL(10,2)` за нови парични колони
4. PHP `moneyFormat()` единственият начин да покажеш пари на user

---

# 4. QUANTITY MILLIS

## 4.1 Проблемът

`inventory.quantity DECIMAL(12,4)` — позволява 3.1415 артикула. Плюс float грешки.

## 4.2 Решение

`quantity_millis BIGINT` — количество × 1000.

```sql
ALTER TABLE inventory
  ADD COLUMN quantity_millis BIGINT NOT NULL DEFAULT 0,
  ADD CHECK (quantity_millis >= -1000000000);

UPDATE inventory SET quantity_millis = ROUND(quantity * 1000);
```

1 брой = 1000 millis. 0.5 кг = 500 millis (за бъдещи meat/grocery магазини).

## 4.3 Helper

```php
function qtyMillisToFloat($millis) { return $millis / 1000; }
function qtyFloatToMillis($float) { return (int) round($float * 1000); }
function qtyFormat($millis, $unit = 'pc') {
    $qty = $millis / 1000;
    if ($unit === 'pc') return (int) $qty . ' бр';
    return number_format($qty, 3) . ' ' . $unit;
}
```

---

# 5. SOFT DELETE PATTERN

## 5.1 Проблемът

Ако Пешо изтрие продукт → sales history се чупи (FK violation).

## 5.2 Решение

```sql
ALTER TABLE products
  ADD COLUMN deleted_at DATETIME NULL,
  ADD COLUMN deleted_by INT NULL,
  ADD COLUMN delete_reason VARCHAR(200) NULL,
  ADD INDEX idx_deleted_at (deleted_at);
```

```php
class Products {
    public static function getActive($tenant_id) {
        return DB::run("SELECT * FROM products
                        WHERE tenant_id=? AND deleted_at IS NULL",
            [$tenant_id])->fetchAll();
    }

    public static function delete($product_id, $user_id, $reason = '') {
        DB::run("UPDATE products
                 SET deleted_at=NOW(), deleted_by=?, delete_reason=?
                 WHERE id=?",
            [$user_id, $reason, $product_id]);
    }

    public static function restore($product_id) {
        DB::run("UPDATE products
                 SET deleted_at=NULL, deleted_by=NULL, delete_reason=NULL
                 WHERE id=?",
            [$product_id]);
    }
}
```

## 5.3 Cron housekeeping

```sql
DELETE FROM products WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

## 5.4 Приложимост

Soft delete за: products, suppliers, customers, users, stores, categories.
Не прилагаме за: sales, sale_items, stock_movements (append-only).

---

# 6. AUDIT LOG + SOURCE TRACKING

## 6.1 Проблемът

„Кой промени цената на Nike 42 от €60 на €45?" — в момента не знаем.

## 6.2 Решение

```sql
CREATE TABLE audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    store_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    source ENUM('ui','ai','api','cron','system') NOT NULL,
    source_detail VARCHAR(200) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_tenant_created (tenant_id, created_at),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB;
```

## 6.3 Helper

```php
function auditLog($user, $action, $entity_type, $entity_id, $old, $new, $source = 'ui') {
    DB::run("INSERT INTO audit_log
             (tenant_id, user_id, store_id, action, entity_type, entity_id,
              old_values, new_values, source, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [$user['tenant_id'], $user['id'], $user['store_id'] ?? null,
         $action, $entity_type, $entity_id,
         json_encode($old), json_encode($new), $source,
         $_SERVER['REMOTE_ADDR'] ?? null,
         $_SERVER['HTTP_USER_AGENT'] ?? null]);
}
```

## 6.4 Retention

Audit log се пази **7 години** (данъчни изисквания в БГ).

---

# 7. TRANSACTION WRAPPER `tx()`

## 7.1 Проблемът

Много места в кода правят multi-table updates без transaction. Един fail по средата → частичен update.

## 7.2 Решение

```php
class DB {
    public static function tx(callable $callback) {
        $pdo = self::get();
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
```

## 7.3 Приложение

```php
DB::tx(function() use ($sale_data) {
    $sale_id = Sales::create($sale_data);
    foreach ($sale_data['items'] as $item) {
        SaleItems::create($sale_id, $item);
        Inventory::decrement($item['product_id'], $item['quantity']);
        StockMovements::append($sale_id, $item);
    }
    CachedStock::reconcile($sale_data['store_id']);
    auditLog($user, 'sale.create', 'sale', $sale_id, null, $sale_data);
});
```

**Всяко** действие което touch-ва > 1 таблица → задължително `DB::tx()`.

---

# 8. NEGATIVE STOCK GUARD

```sql
ALTER TABLE inventory
  ADD CONSTRAINT check_quantity_non_negative CHECK (quantity_millis >= 0);
```

```php
DB::tx(function() use ($product_id, $quantity, $store_id) {
    $inv = DB::run("SELECT * FROM inventory
                    WHERE product_id=? AND store_id=?
                    FOR UPDATE",
        [$product_id, $store_id])->fetch();

    if (!$inv || $inv['quantity_millis'] < $quantity * 1000) {
        throw new InsufficientStockException();
    }

    DB::run("UPDATE inventory
             SET quantity_millis = quantity_millis - ?
             WHERE product_id=? AND store_id=?",
        [$quantity * 1000, $product_id, $store_id]);
});
```

---

# 9. CACHED STOCK RECONCILIATION

```php
// cron_reconcile_stock.php
foreach (DB::run("SELECT id, tenant_id FROM stores")->fetchAll() as $store) {
    $discrepancies = DB::run(
        "SELECT p.id, p.cached_stock,
                COALESCE(SUM(i.quantity_millis), 0) / 1000 as actual_stock
         FROM products p
         LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
         WHERE p.tenant_id = ?
         GROUP BY p.id
         HAVING p.cached_stock != actual_stock",
        [$store['id'], $store['tenant_id']]
    )->fetchAll();

    foreach ($discrepancies as $d) {
        DB::run("UPDATE products SET cached_stock = ? WHERE id = ?",
            [$d['actual_stock'], $d['id']]);
        if (abs($d['cached_stock'] - $d['actual_stock']) > 5) {
            alertTihol("Stock drift product={$d['id']}");
        }
    }
}
```

---

# 10. TIMEZONE UTC

```sql
SET GLOBAL time_zone = '+00:00';
SET SESSION time_zone = '+00:00';
```

```php
date_default_timezone_set('UTC');

function toTenantTime($utc_datetime, $tenant) {
    $tz = new DateTimeZone($tenant['timezone'] ?? 'Europe/Sofia');
    $dt = new DateTime($utc_datetime, new DateTimeZone('UTC'));
    $dt->setTimezone($tz);
    return $dt;
}
```

```sql
ALTER TABLE tenants ADD COLUMN timezone VARCHAR(64) DEFAULT 'Europe/Sofia';
```

---

# 11. PARKED SALE ALLOCATED

## 11.1 Проблемът (DeepSeek's critical catch)

Пешо паркира продажба с Nike 42 (1 брой). Stock остава = 1. Друг seller го продава. Пешо разпаркира — Nike 42 вече го няма.

## 11.2 Решение

```sql
ALTER TABLE inventory
  ADD COLUMN allocated_millis BIGINT NOT NULL DEFAULT 0;
```

```php
// При парк
DB::tx(function() use ($parked_items) {
    foreach ($parked_items as $item) {
        DB::run("UPDATE inventory
                 SET allocated_millis = allocated_millis + ?
                 WHERE product_id=? AND store_id=?",
            [$item['qty'] * 1000, $item['product_id'], $item['store_id']]);
    }
});

// При finalize
DB::tx(function() use ($sale_items) {
    foreach ($sale_items as $item) {
        DB::run("UPDATE inventory
                 SET quantity_millis = quantity_millis - ?,
                     allocated_millis = allocated_millis - ?
                 WHERE product_id=? AND store_id=?",
            [$item['qty']*1000, $item['qty']*1000, $item['product_id'], $item['store_id']]);
    }
});
```

## 11.3 UI

```
Nike 42: 3 броя налични (1 паркиран)
```

---

# 12. TENANT ISOLATION + COMPOSITE FK

```sql
ALTER TABLE sale_items ADD COLUMN tenant_id INT NOT NULL;
ALTER TABLE sale_items DROP FOREIGN KEY fk_sale_items_sale;
ALTER TABLE sale_items
  ADD CONSTRAINT fk_sale_items_sale
  FOREIGN KEY (tenant_id, sale_id)
  REFERENCES sales (tenant_id, id);
ALTER TABLE sales
  ADD UNIQUE KEY uk_tenant_id (tenant_id, id);
```

Сега физически е невъзможно sale_item да принадлежи към sale от друг tenant.

```php
class TenantDB {
    private $tenant_id;
    public function __construct($tenant_id) { $this->tenant_id = $tenant_id; }
    public function run($sql, $params = []) {
        if (!preg_match('/WHERE/i', $sql)) {
            $sql .= " WHERE tenant_id=?";
            $params[] = $this->tenant_id;
        } elseif (!preg_match('/tenant_id\s*=/', $sql)) {
            $sql = preg_replace('/WHERE/i', "WHERE tenant_id=? AND ", $sql, 1);
            array_unshift($params, $this->tenant_id);
        }
        return DB::run($sql, $params);
    }
}
```

---

# 13. MULTI-STORE RESOLVER

```sql
ALTER TABLE sales ADD COLUMN store_id INT NOT NULL;
ALTER TABLE inventory ADD COLUMN store_id INT NOT NULL;
ALTER TABLE deliveries ADD COLUMN store_id INT NOT NULL;
-- products остава tenant-level (споделени между магазини)
```

```php
$_SESSION['current_store_id'] = $user['default_store_id'];

function switchStore($store_id) {
    if (!can('store.switch', ['store_id' => $store_id])) {
        throw new Exception('Forbidden');
    }
    $_SESSION['current_store_id'] = $store_id;
}
```

---

# 14. IDEMPOTENCY KEYS

```sql
CREATE TABLE idempotency_keys (
    key_hash VARCHAR(64) PRIMARY KEY,
    user_id INT NOT NULL,
    request_body TEXT NOT NULL,
    response_body TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_created (created_at)
);
```

```php
function handleIdempotency($user_id, $request_body) {
    $key = $request_body['idempotency_key'] ?? null;
    if (!$key) return null;

    $hash = hash('sha256', $user_id . ':' . $key);
    $existing = DB::run("SELECT response_body FROM idempotency_keys WHERE key_hash=?",
        [$hash])->fetch();
    if ($existing) return json_decode($existing['response_body'], true);
    return null;
}
```

```javascript
// При voice command
const idempotencyKey = crypto.randomUUID();
fetch('/ai-action.php', {
    body: JSON.stringify({ text: voiceText, idempotency_key: idempotencyKey })
});
```

```sql
DELETE FROM idempotency_keys WHERE created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR);
```

---

# 15. STOCK MOVEMENTS LEDGER (APPEND-ONLY)

```sql
CREATE TABLE stock_movements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    product_id INT NOT NULL,
    movement_type ENUM('sale','return','delivery','transfer_in','transfer_out','adjustment','loss','count') NOT NULL,
    quantity_millis BIGINT NOT NULL,
    balance_after_millis BIGINT NOT NULL,
    related_entity_type VARCHAR(50) NULL,
    related_entity_id BIGINT NULL,
    user_id INT NOT NULL,
    operation_id VARCHAR(36) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_tenant_store_product (tenant_id, store_id, product_id),
    INDEX idx_operation (operation_id),
    INDEX idx_created (created_at)
);
```

```php
function appendStockMovement($data) {
    $current_balance = DB::run(
        "SELECT quantity_millis FROM inventory
         WHERE product_id=? AND store_id=?",
        [$data['product_id'], $data['store_id']]
    )->fetchColumn() ?: 0;
    $new_balance = $current_balance + $data['quantity_millis'];

    DB::run("INSERT INTO stock_movements
             (tenant_id, store_id, product_id, movement_type, quantity_millis,
              balance_after_millis, related_entity_type, related_entity_id,
              user_id, operation_id, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [$data['tenant_id'], $data['store_id'], $data['product_id'],
         $data['type'], $data['quantity_millis'], $new_balance,
         $data['entity_type'], $data['entity_id'], $data['user_id'],
         $data['operation_id'], $data['notes']]);

    DB::run("UPDATE inventory
             SET quantity_millis=?, updated_at=NOW()
             WHERE product_id=? AND store_id=?",
        [$new_balance, $data['product_id'], $data['store_id']]);
}
```

## 15.4 Rebuild query

„Колко Nike 42 имах на 15.04?"

```sql
SELECT balance_after_millis
FROM stock_movements
WHERE product_id = ? AND store_id = ?
  AND created_at <= '2026-04-15 23:59:59'
ORDER BY created_at DESC, id DESC
LIMIT 1;
```

---

# 16. OPERATION_ID + GLOBAL UNDO

```php
$operation_id = uniqid('op_', true);

DB::tx(function() use ($sale_data, $operation_id) {
    $sale_id = Sales::create($sale_data, $operation_id);
    foreach ($sale_data['items'] as $item) {
        SaleItems::create($sale_id, $item, $operation_id);
        appendStockMovement([
            'type' => 'sale',
            'product_id' => $item['product_id'],
            'quantity_millis' => -$item['qty'] * 1000,
            'operation_id' => $operation_id,
        ]);
    }
});
```

## 16.3 Global Undo

```php
function undoOperation($operation_id, $user_id) {
    DB::tx(function() use ($operation_id, $user_id) {
        $movements = DB::run(
            "SELECT * FROM stock_movements WHERE operation_id=?",
            [$operation_id]
        )->fetchAll();

        foreach ($movements as $m) {
            appendStockMovement([
                'tenant_id' => $m['tenant_id'],
                'store_id' => $m['store_id'],
                'product_id' => $m['product_id'],
                'type' => 'adjustment',
                'quantity_millis' => -$m['quantity_millis'],
                'related_entity_type' => 'undo',
                'related_entity_id' => $m['id'],
                'user_id' => $user_id,
                'operation_id' => 'undo_' . $operation_id,
            ]);
        }

        DB::run("UPDATE sales SET status='canceled', cancelled_at=NOW(),
                 cancelled_by=?, cancel_reason='User undo'
                 WHERE operation_id=?",
            [$user_id, $operation_id]);
    });
}
```

---

# 17. EVENT QUEUE + DLQ

```sql
CREATE TABLE events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    type VARCHAR(64) NOT NULL,
    payload_json JSON NOT NULL,
    status ENUM('pending','processing','done','failed') DEFAULT 'pending',
    retry_count TINYINT DEFAULT 0,
    last_error TEXT NULL,
    sequence_number BIGINT NOT NULL,
    depends_on_event_id BIGINT NULL,
    created_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    INDEX idx_status_created (status, created_at)
);

CREATE TABLE dead_letter_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    original_event_id BIGINT NOT NULL,
    tenant_id INT NOT NULL,
    type VARCHAR(64) NOT NULL,
    payload_json JSON NOT NULL,
    final_error TEXT NOT NULL,
    retry_count TINYINT NOT NULL,
    moved_to_dlq_at DATETIME NOT NULL
);
```

## 17.4 Sync vs Async rules

| Action | Sync/Async |
|---|---|
| Продажба → stock | **SYNC** |
| Продажба → cached_stock | **SYNC** |
| Продажба → audit log | **SYNC** |
| Продажба → AI notify | **ASYNC** |
| Продажба → analytics | **ASYNC** |
| Продажба → email receipt | **ASYNC** |

---

# 18. STATE MACHINES

## Orders

```
draft → confirmed → sent → acked → received
                       ↘ cancelled
                       ↘ overdue
```

## Sales

```
cart → pending → completed
       ↘ parked
       ↘ canceled
```

```php
class OrderStateMachine {
    private static $transitions = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['sent', 'cancelled'],
        'sent' => ['acked', 'cancelled'],
        'acked' => ['received', 'overdue'],
        'received' => [],
        'cancelled' => [],
        'overdue' => ['received', 'cancelled'],
    ];

    public static function canTransition($from, $to) {
        return in_array($to, self::$transitions[$from] ?? []);
    }
}
```

---

# 19. FK + CHECK CONSTRAINTS

```sql
ALTER TABLE sale_items
  ADD CONSTRAINT fk_si_product FOREIGN KEY (product_id) REFERENCES products(id);

ALTER TABLE products
  ADD CONSTRAINT chk_prices CHECK (retail_price_cents >= 0 AND cost_price_cents >= 0);

ALTER TABLE inventory
  ADD CONSTRAINT chk_inv_qty CHECK (quantity_millis >= 0),
  ADD CONSTRAINT chk_inv_alloc CHECK (allocated_millis >= 0),
  ADD CONSTRAINT chk_inv_alloc_leq CHECK (allocated_millis <= quantity_millis);

ALTER TABLE products
  ADD CONSTRAINT uk_code UNIQUE (tenant_id, code);
```

---

# 20. CRON HEARTBEAT

```sql
CREATE TABLE cron_heartbeats (
    job_name VARCHAR(100) PRIMARY KEY,
    last_run_at DATETIME NOT NULL,
    last_status ENUM('ok','error') NOT NULL,
    last_error TEXT NULL,
    last_duration_ms INT NOT NULL,
    expected_interval_minutes INT NOT NULL
);
```

```php
function cronHeartbeat($job_name, callable $work) {
    $start = microtime(true);
    try {
        $work();
        $elapsed = (microtime(true) - $start) * 1000;
        DB::run("REPLACE INTO cron_heartbeats
                 (job_name, last_run_at, last_status, last_duration_ms, expected_interval_minutes)
                 VALUES (?, NOW(), 'ok', ?, ?)",
            [$job_name, $elapsed, getExpectedInterval($job_name)]);
    } catch (Exception $e) {
        alertTihol("Cron $job_name failed: {$e->getMessage()}");
    }
}
```

---

# 21. 20-ТЕ НОВИ ТАБЛИЦИ

1. `schema_migrations`
2. `audit_log`
3. `idempotency_keys`
4. `stock_movements`
5. `events`
6. `dead_letter_queue`
7. `cron_heartbeats`
8. `feature_flags`
9. `ai_rate_limits`
10. `ai_cost_tracking`
11. `ai_trust_scores`
12. `telemetry_events`
13. `ai_shadow_log`
14. `tenant_backups`
15. `document_ledger`
16. `session_audit`
17. `failed_login_attempts`
18. `api_keys`
19. `webhook_subscriptions`
20. `health_check_results`

---

**КРАЙ НА DOC 05**


---

# 16A. КРИТИЧНИ КОЛОНИ В СЪЩЕСТВУВАЩИТЕ ТАБЛИЦИ

Отвъд новите таблици, тези колони трябва да съществуват:

## tenants (разширения)

```sql
ui_mode ENUM('simple','detailed') DEFAULT 'simple'
onboarding_status ENUM('new','in_progress','core_unlocked','operating') DEFAULT 'new'
onboarding_milestones JSON
plan ENUM('free','start','pro') DEFAULT 'free'
plan_effective ENUM('free','start','pro') DEFAULT 'pro'
trial_ends_at TIMESTAMP NULL
trial_phase ENUM('month_1_free','months_2_4','operating') DEFAULT 'month_1_free'
stated_stock_retail_value DECIMAL(10,2)
stripe_customer_id VARCHAR(100)
affiliate_code VARCHAR(50)
referred_by_code VARCHAR(50)
dnd_start TIME DEFAULT '22:00'
dnd_end TIME DEFAULT '07:00'
dnd_action_policy ENUM('block','require_pin','log_only') DEFAULT 'require_pin'
```

## users (разширения)

```sql
role ENUM('owner','manager','seller') DEFAULT 'seller'
pin VARCHAR(10)
commission_pct DECIMAL(4,2)
max_discount_pct DECIMAL(4,2) DEFAULT 10
```

## wizard_draft (нова)

```sql
CREATE TABLE wizard_draft (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    current_step INT DEFAULT 0,
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (tenant_id, user_id)
);
```

## inventory_events (event-sourced — В5 заключено решение)

```sql
CREATE TABLE inventory_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    product_id INT NOT NULL,
    event_type ENUM('sale','delivery','return','adjustment','zone_walk','transfer') NOT NULL,
    asserted_quantity INT,
    baseline_before_event INT,
    quantity_delta INT,
    user_id INT,
    reference_id INT,
    source_device_id VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (product_id, created_at),
    INDEX (tenant_id, created_at)
);
```

## idempotency_log (нова — за multi-device race prevention)

```sql
CREATE TABLE idempotency_log (
    `key` VARCHAR(64) PRIMARY KEY,
    result JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (created_at)
);
```

## user_devices (нова — multi-device tracking)

```sql
CREATE TABLE user_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_id VARCHAR(64) NOT NULL,
    device_name VARCHAR(100),
    last_seen_at TIMESTAMP,
    UNIQUE (user_id, device_id)
);
```

---
