# 📘 DOC 15 — ЛАНСИРАНЕ + ПУБЛИЧЕН RELEASE

## Phase D: От beta до App Store

**Версия:** 1.0 | **Дата:** 21.04.2026 | **Част от:** Група 6

---

## 📑 СЪДЪРЖАНИЕ

1. Философия на launch
2. Capacitor Offline Persistent Queue
3. GDPR PII Scrubbing
4. Tenant Export script
5. Anomaly Detection
6. Health Check Cron
7. Online Schema Migrations
8. Retention Policies
9. AI Action Race (advisory locks)
10. Optimistic Locking
11. Secrets Management
12. Integration Test Suite
13. RTO/RPO targets
14. iOS + Android submission
15. Launch checklist

---

# 1. ФИЛОСОФИЯ НА LAUNCH

**Launch не е ден. Launch е процес.**

Преди да пуснем публично:
- 2 beta магазина (ЕНИ + един независим) работят 30+ дни
- Zero critical bugs за 14 дни
- Integration test suite passing
- Backup restore tested
- All AI safety layers active
- GDPR compliant
- App Store + Play Store approved

---

# 2. CAPACITOR OFFLINE PERSISTENT QUEUE

## 2.1 Проблемът

Пешо в магазин, WiFi падна. Прави продажба. Как я record-ваме?

## 2.2 Решение

IndexedDB queue в mobile app:

```javascript
class OfflineQueue {
    async enqueue(action, payload) {
        const db = await this.openDB();
        const tx = db.transaction('queue', 'readwrite');
        await tx.objectStore('queue').add({
            id: crypto.randomUUID(),
            action,
            payload,
            timestamp: Date.now(),
            retries: 0,
            synced: false
        });
    }

    async sync() {
        if (!navigator.onLine) return;

        const db = await this.openDB();
        const unsynced = await db.transaction('queue')
            .objectStore('queue')
            .index('synced')
            .getAll(false);

        for (const item of unsynced) {
            try {
                await fetch('/api/' + item.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Idempotency-Key': item.id
                    },
                    body: JSON.stringify(item.payload)
                });

                item.synced = true;
                await db.transaction('queue', 'readwrite')
                    .objectStore('queue').put(item);
            } catch (e) {
                item.retries++;
                if (item.retries > 10) {
                    this.moveToFailed(item);
                }
            }
        }
    }
}

window.addEventListener('online', () => offlineQueue.sync());
setInterval(() => offlineQueue.sync(), 30000);
```

## 2.3 UI indicator

```
📡 Офлайн — 3 продажби в queue
Ще синхронизирам при връзка
```

При reconnect:
```
✅ Синхронизирано: 3 продажби
```

---

# 3. GDPR PII SCRUBBING

## 3.1 При логване

```php
function scrubForLog($data) {
    $sensitive_keys = ['email', 'phone', 'password', 'card_number', 'address'];

    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (in_array($key, $sensitive_keys)) {
                $data[$key] = '[SCRUBBED]';
            } elseif (is_array($value)) {
                $data[$key] = scrubForLog($value);
            }
        }
    }

    return $data;
}
```

## 3.2 Data retention

```sql
DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 YEAR);
DELETE FROM ai_full_audit WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
DELETE FROM idempotency_keys WHERE created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR);
DELETE FROM session_audit WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

## 3.3 Right to erasure

```php
function deleteUserData($tenant_id) {
    DB::tx(function() use ($tenant_id) {
        DB::run("UPDATE sales SET customer_name=NULL, customer_phone=NULL,
                 customer_email=NULL WHERE tenant_id=?", [$tenant_id]);

        DB::run("DELETE FROM customers WHERE tenant_id=?", [$tenant_id]);

        DB::run("UPDATE users SET email=CONCAT('deleted_', id, '@example.com'),
                 phone=NULL, full_name='Deleted User' WHERE tenant_id=?", [$tenant_id]);

        $photos = DB::run("SELECT path FROM photo_uploads WHERE tenant_id=?",
            [$tenant_id])->fetchAll();
        foreach ($photos as $p) @unlink($p['path']);
        DB::run("DELETE FROM photo_uploads WHERE tenant_id=?", [$tenant_id]);

        DB::run("UPDATE tenants SET deleted_at=NOW(), status='deleted'
                 WHERE id=?", [$tenant_id]);
    });

    emailUser($tenant_id, 'Your data has been deleted');
}
```

---

# 4. TENANT EXPORT

```php
class TenantExporter {
    public function export($tenant_id) {
        $archive = "/tmp/tenant_$tenant_id_" . date('YmdHis') . ".zip";
        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::CREATE);

        $products = DB::run("SELECT * FROM products WHERE tenant_id=?", [$tenant_id])->fetchAll();
        $zip->addFromString('products.csv', $this->toCsv($products));

        $sales = DB::run("SELECT * FROM sales WHERE tenant_id=?", [$tenant_id])->fetchAll();
        $zip->addFromString('sales.csv', $this->toCsv($sales));

        $items = DB::run("SELECT * FROM sale_items WHERE tenant_id=?", [$tenant_id])->fetchAll();
        $zip->addFromString('sale_items.csv', $this->toCsv($items));

        $inv = DB::run("SELECT * FROM inventory WHERE tenant_id=?", [$tenant_id])->fetchAll();
        $zip->addFromString('inventory.csv', $this->toCsv($inv));

        $customers = DB::run("SELECT * FROM customers WHERE tenant_id=?", [$tenant_id])->fetchAll();
        $zip->addFromString('customers.csv', $this->toCsv($customers));

        $movements = DB::run("SELECT * FROM stock_movements WHERE tenant_id=?", [$tenant_id])->fetchAll();
        $zip->addFromString('stock_movements.csv', $this->toCsv($movements));

        $photos = DB::run("SELECT * FROM photo_uploads WHERE tenant_id=?", [$tenant_id])->fetchAll();
        foreach ($photos as $p) {
            if (file_exists($p['path'])) {
                $zip->addFile($p['path'], 'photos/' . basename($p['path']));
            }
        }

        $audit = DB::run("SELECT * FROM audit_log WHERE tenant_id=?", [$tenant_id])->fetchAll();
        $zip->addFromString('audit_log.csv', $this->toCsv($audit));

        $settings = DB::run("SELECT * FROM tenants WHERE id=?", [$tenant_id])->fetch();
        $zip->addFromString('settings.json', json_encode($settings, JSON_PRETTY_PRINT));

        $zip->close();
        return $archive;
    }
}
```

---

# 5. ANOMALY DETECTION

Cron job, работи всяка нощ:

```php
function detectAnomalies() {
    // 1. Sudden sales spike
    $spikes = DB::run(
        "SELECT tenant_id, DATE(created_at) as day, COUNT(*) as sales_count
         FROM sales
         WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY tenant_id, day
         HAVING sales_count > avg_daily_sales*5"
    )->fetchAll();

    foreach ($spikes as $s) {
        alertTihol("Tenant {$s['tenant_id']} had {$s['sales_count']} sales on {$s['day']} (5× avg)");
    }

    // 2. Negative stock despite guards
    $negatives = DB::run("SELECT * FROM inventory WHERE quantity_millis < 0")->fetchAll();
    if ($negatives) {
        alertTihol("Negative stock detected!", 'critical');
    }

    // 3. AI cost anomaly
    $cost_spikes = DB::run(
        "SELECT tenant_id, SUM(cost_cents)/100 as cost_today
         FROM ai_cost_tracking
         WHERE DATE(created_at)=CURDATE()
         GROUP BY tenant_id
         HAVING cost_today > 50"
    )->fetchAll();

    foreach ($cost_spikes as $c) {
        alertTihol("Tenant {$c['tenant_id']} AI cost today: €{$c['cost_today']}");
    }

    // 4. Failed login attempts
    $brute_force = DB::run(
        "SELECT ip_address, COUNT(*) as attempts
         FROM failed_login_attempts
         WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
         GROUP BY ip_address
         HAVING attempts > 20"
    )->fetchAll();

    foreach ($brute_force as $b) {
        blockIP($b['ip_address']);
        alertTihol("IP blocked: {$b['ip_address']}", 'critical');
    }
}
```

---

# 6. HEALTH CHECK CRON

Every 5 minutes:

```php
$checks = [];

$start = microtime(true);
try {
    DB::run("SELECT 1")->fetch();
    $checks['db_latency_ms'] = round((microtime(true) - $start) * 1000);
} catch (Exception $e) {
    alertTihol("DB down", 'critical');
    exit(1);
}

$free_pct = (disk_free_space('/') / disk_total_space('/')) * 100;
if ($free_pct < 10) {
    alertTihol("Disk space low: $free_pct%", 'warning');
}

$stale = DB::run(
    "SELECT * FROM cron_heartbeats
     WHERE last_run_at < DATE_SUB(NOW(), INTERVAL expected_interval_minutes*2 MINUTE)"
)->fetchAll();
foreach ($stale as $s) {
    alertTihol("Cron stale: {$s['job_name']}", 'warning');
}

DB::run("INSERT INTO health_check_results
         (checks_json, overall_status, created_at)
         VALUES (?, ?, NOW())",
    [json_encode($checks), 'ok']);
```

---

# 7. ONLINE SCHEMA MIGRATIONS

За таблици с 5M+ редове — `pt-online-schema-change`:

```bash
pt-online-schema-change \
    --alter "ADD COLUMN new_field VARCHAR(100)" \
    --execute \
    D=runmystore,t=large_table
```

Нулев downtime.

---

# 8. RETENTION POLICIES PER TABLE

```php
$policies = [
    'audit_log' => '7 YEARS',
    'ai_full_audit' => '90 DAYS',
    'ai_cost_tracking' => '1 YEAR',
    'idempotency_keys' => '48 HOURS',
    'session_audit' => '90 DAYS',
    'health_check_results' => '30 DAYS',
    'failed_login_attempts' => '30 DAYS',
    'telemetry_events' => '30 DAYS',
    'ai_shadow_log' => '90 DAYS',
    'search_log' => '180 DAYS',
];

foreach ($policies as $table => $age) {
    $sql = "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL $age)";
    DB::exec($sql);
}
```

---

# 9. AI ACTION RACE (ADVISORY LOCKS)

```php
function withAdvisoryLock($lock_name, callable $fn, $timeout_seconds = 10) {
    $locked = DB::run("SELECT GET_LOCK(?, ?)", [$lock_name, $timeout_seconds])
        ->fetchColumn();

    if (!$locked) {
        throw new Exception("Could not acquire lock: $lock_name");
    }

    try {
        return $fn();
    } finally {
        DB::run("SELECT RELEASE_LOCK(?)", [$lock_name]);
    }
}

withAdvisoryLock("reconcile_store_$store_id", function() use ($store_id) {
    reconcileInventory($store_id);
});
```

---

# 10. OPTIMISTIC LOCKING

```sql
ALTER TABLE inventory ADD COLUMN version INT DEFAULT 0;
```

```php
function updateInventoryOptimistic($product_id, $store_id, $new_qty) {
    $current = DB::run(
        "SELECT quantity_millis, version FROM inventory
         WHERE product_id=? AND store_id=?",
        [$product_id, $store_id]
    )->fetch();

    $updated = DB::run(
        "UPDATE inventory
         SET quantity_millis=?, version=version+1, updated_at=NOW()
         WHERE product_id=? AND store_id=? AND version=?",
        [$new_qty, $product_id, $store_id, $current['version']]
    )->rowCount();

    if ($updated === 0) {
        throw new OptimisticLockException("Inventory changed concurrently");
    }
}
```

---

# 11. SECRETS MANAGEMENT

**Никога в git:**
- DB passwords
- API keys (Gemini, Stripe, fal.ai, OpenAI)
- JWT secrets
- Telegram bot tokens

**Solution:** environment variables + `.env` file (gitignored):

```bash
# /var/www/runmystore/.env
DB_HOST=localhost
DB_USER=runmystore
DB_PASS=<strong_password>
GEMINI_API_KEY_1=AIzaSy...
GEMINI_API_KEY_2=AIzaSy...
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
FAL_AI_API_KEY=...
TELEGRAM_BOT_TOKEN=...
```

```php
function env($key, $default = null) {
    static $loaded = false;
    if (!$loaded) {
        $lines = file('/var/www/runmystore/.env');
        foreach ($lines as $line) {
            if (strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', trim($line), 2);
            $_ENV[$k] = $v;
        }
        $loaded = true;
    }
    return $_ENV[$key] ?? $default;
}
```

## 11.1 Rotation

Key rotation every 90 days:
1. Generate new key
2. Deploy with both old+new accepted
3. Wait 7 days (grace period)
4. Remove old key

---

# 12. INTEGRATION TEST SUITE

PHPUnit tests за критични flows:

```php
class SaleFlowTest extends TestCase {
    public function testCompleteSaleFlow() {
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant);
        $product = $this->createProduct($tenant, ['retail_price_cents' => 4000]);
        $this->setInventory($product, $store, 10);

        $sale_id = Sales::create([
            'tenant_id' => $tenant['id'],
            'store_id' => $store['id'],
            'items' => [
                ['product_id' => $product['id'], 'qty' => 2]
            ]
        ]);

        $this->assertEquals(8, Inventory::getQty($product, $store));
        $this->assertEquals(8000, Sales::getTotal($sale_id));
        $this->assertCount(1, StockMovements::forSale($sale_id));
        $this->assertCount(1, AuditLog::forEntity('sale', $sale_id));
    }
}
```

CI on each push:

```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1
      - run: composer install
      - run: php scripts/migrate.php up
      - run: vendor/bin/phpunit
```

---

# 13. RTO/RPO TARGETS

**RTO** (Recovery Time Objective): максимално време за възстановяване
**RPO** (Recovery Point Objective): максимално изгубени данни

| Scenario | RTO | RPO |
|---|---|---|
| Application crash | 5 min | 0 |
| Database corruption | 2 hours | 1 hour |
| Server down | 4 hours | 1 hour |
| Regional disaster | 24 hours | 4 hours |

## Backups

- **Hourly:** incremental MySQL backup to S3
- **Daily:** full MySQL dump to S3 (retain 30 days)
- **Weekly:** full tenant export (retain 12 weeks)
- **Monthly:** full snapshot (retain 12 months)

## Restore drill

**Ежемесечно:** Тихол прави test restore от backup в staging server.

---

# 14. iOS + ANDROID SUBMISSION

## 14.1 Capacitor build

```bash
npm run build
npx cap sync
npx cap open ios
npx cap open android
```

## 14.2 iOS — App Store

**Submission requirements:**
- Apple Developer account ($99/year)
- App Store screenshots (6.7", 6.5", 5.5")
- Privacy policy URL
- App description в BG + EN
- Age rating: 4+
- Category: Business
- TestFlight beta first (2 weeks)

**Review timeline:** 1-7 дни.

## 14.3 Android — Google Play

**Submission requirements:**
- Google Play Console account ($25 one-time)
- APK/AAB signed
- Screenshots (phone + tablet)
- Feature graphic (1024×500)
- Short description (80 chars)
- Full description (4000 chars)
- Content rating via IARC
- Data safety form

**Review timeline:** 1-3 дни.

---

# 15. LAUNCH CHECKLIST

## Technical
- [ ] All Phase A migrations applied
- [ ] All Phase B modules working
- [ ] All Phase C safety layers active
- [ ] Integration tests passing (100%)
- [ ] Load test: 1000 concurrent users
- [ ] Backup restore tested
- [ ] Rate limits active
- [ ] CSRF protection everywhere
- [ ] HTTPS enforced (HSTS)
- [ ] Error tracking live (Sentry)
- [ ] Uptime monitoring
- [ ] Health check endpoint
- [ ] Cron heartbeats green

## Business
- [ ] Terms of Service finalized
- [ ] Privacy Policy finalized
- [ ] GDPR DPA available
- [ ] Stripe Connect accounts
- [ ] Affiliate program T&C
- [ ] Pricing page live
- [ ] Help docs (min 20 topics)

## Product
- [ ] Onboarding < 5 min
- [ ] First WOW moment < 2 min
- [ ] Voice works в BG (>85%)
- [ ] AI responses < 3 sec p95
- [ ] Pills load < 100ms
- [ ] Offline queue tested
- [ ] Push notifications working

## Marketing
- [ ] Landing page live
- [ ] Demo video (2 min)
- [ ] Case studies
- [ ] Partner network (5+ affiliates)

## Operations
- [ ] Support email active
- [ ] FAQ page
- [ ] Telegram alerts configured
- [ ] Stripe webhooks tested

## App Stores
- [ ] iOS approved
- [ ] Android approved

---

**КРАЙ НА DOC 15**
