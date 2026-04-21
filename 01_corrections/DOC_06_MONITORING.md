# 📘 DOC 06 — МОНИТОРИНГ И ЗАЩИТА ОТ БЪГОВЕ

## Как виждаме бъгове преди Пешо

**Версия:** 1.0 | **Дата:** 21.04.2026 | **Част от:** Група 3

---

## 📑 СЪДЪРЖАНИЕ

1. Философия: „Бъгове в пролуките"
2. UptimeRobot — външен monitor
3. Sentry — error tracking
4. Telegram alerts
5. `/health.php` endpoint
6. Rate limiting
7. CSRF protection
8. Feature flags
9. Circuit breakers
10. Kill switches
11. Когато нещо се счупи
12. Recovery procedures

---

# 1. ФИЛОСОФИЯ: „БЪГОВЕ В ПРОЛУКИТЕ"

Цитатът на Claude: *„Бъгове не се раждат там, където имаш защита. Раждат се в пролуките между защитите."*

Monitoring не е за „да хванем 100% от бъговете". Е за:
- Да знаем **преди Пешо** че нещо не работи
- Да виждаме patterns (не само single errors)
- Да реагираме за **минути**, не дни

---

# 2. UPTIMEROBOT — ВЪНШЕН MONITOR

## 2.1 Setup

UptimeRobot.com — безплатна tier до 50 monitors, 5-минутен interval.

**Endpoints за мониториране:**
- `https://runmystore.ai/health.php`
- `https://runmystore.ai/api/ping.php`
- `https://runmystore.ai/`
- `https://runmystore.ai/sale.php`

## 2.2 Alerts

При down > 2 минути:
- Telegram notification към Тихол
- SMS (backup)
- Email

---

# 3. SENTRY — ERROR TRACKING

## 3.1 PHP setup

```php
require_once 'vendor/sentry/sdk/init.php';

\Sentry\init([
    'dsn' => 'https://xxx@sentry.io/xxx',
    'environment' => 'production',
    'release' => file_get_contents('VERSION'),
    'traces_sample_rate' => 0.1,
    'before_send' => function ($event) {
        $event->setUser(null); // Scrub PII
        return $event;
    },
]);

set_exception_handler(function ($e) {
    \Sentry\captureException($e);
    http_response_code(500);
    include 'error_500.html';
});
```

## 3.2 JS setup

```javascript
Sentry.init({
    dsn: 'https://xxx@sentry.io/xxx',
    environment: 'production',
    tracesSampleRate: 0.1,
    beforeSend(event) {
        if (event.exception?.values?.[0]?.value?.includes('ResizeObserver')) {
            return null;
        }
        return event;
    }
});
```

## 3.3 Context

```php
\Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($user, $tenant) {
    $scope->setTag('tenant_id', $tenant['id']);
    $scope->setTag('plan', $tenant['plan']);
    $scope->setTag('role', $user['role']);
});
```

---

# 4. TELEGRAM ALERTS

## 4.1 Bot setup

@BotFather → нов bot → token.

```php
function alertTihol($message, $severity = 'warning') {
    $bot_token = getenv('TELEGRAM_BOT_TOKEN');
    $chat_id = getenv('TELEGRAM_CHAT_ID_TIHOL');

    $emoji = ['info' => 'ℹ️', 'warning' => '⚠️', 'critical' => '🚨'][$severity];

    $text = "$emoji [$severity] $message\n\n";
    $text .= "Server: " . gethostname() . "\n";
    $text .= "Time: " . date('Y-m-d H:i:s');

    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
```

## 4.2 Използване

```php
if ($failed_payments > 10) {
    alertTihol("Failed payments spike: $failed_payments in last hour", 'critical');
}
if ($ai_failure_rate > 0.05) {
    alertTihol("AI failure rate: " . round($ai_failure_rate*100) . "%", 'warning');
}
```

---

# 5. `/HEALTH.PHP` ENDPOINT

```php
header('Content-Type: application/json');

$checks = [];
$overall_ok = true;

// 1. DB connectivity
try {
    DB::run("SELECT 1")->fetch();
    $checks['db'] = ['status' => 'ok'];
} catch (Exception $e) {
    $checks['db'] = ['status' => 'error', 'message' => $e->getMessage()];
    $overall_ok = false;
}

// 2. Disk space
$free_bytes = disk_free_space('/');
$total_bytes = disk_total_space('/');
$free_pct = ($free_bytes / $total_bytes) * 100;
$checks['disk'] = ['status' => $free_pct > 10 ? 'ok' : 'warning', 'free_pct' => round($free_pct, 1)];
if ($free_pct < 5) $overall_ok = false;

// 3. Cron heartbeats
$stale_crons = DB::run(
    "SELECT job_name FROM cron_heartbeats
     WHERE last_run_at < DATE_SUB(NOW(), INTERVAL expected_interval_minutes*2 MINUTE)"
)->fetchAll(PDO::FETCH_COLUMN);
$checks['crons'] = ['status' => count($stale_crons) === 0 ? 'ok' : 'warning', 'stale' => $stale_crons];

// 4. AI availability
$ai_fails_last_hour = DB::run(
    "SELECT COUNT(*) FROM telemetry_events
     WHERE event_type='ai_failure' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
)->fetchColumn();
$checks['ai'] = ['status' => $ai_fails_last_hour < 10 ? 'ok' : 'warning'];

http_response_code($overall_ok ? 200 : 503);
echo json_encode(['status' => $overall_ok ? 'ok' : 'degraded', 'checks' => $checks]);
```

---

# 6. RATE LIMITING

```sql
CREATE TABLE rate_limit_buckets (
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    window_start DATETIME NOT NULL,
    count INT NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, action, window_start)
);
```

```php
class RateLimiter {
    private static $limits = [
        'ai.call' => ['window' => 60, 'max' => 30],
        'sale.create' => ['window' => 60, 'max' => 100],
        'product.create' => ['window' => 3600, 'max' => 200],
    ];

    public static function check($user_id, $action) {
        $limit = self::$limits[$action] ?? null;
        if (!$limit) return true;

        $window_start = date('Y-m-d H:i:00', floor(time() / $limit['window']) * $limit['window']);
        $count = DB::run(
            "SELECT count FROM rate_limit_buckets
             WHERE user_id=? AND action=? AND window_start=?",
            [$user_id, $action, $window_start]
        )->fetchColumn();

        if ($count >= $limit['max']) return false;

        DB::run(
            "INSERT INTO rate_limit_buckets (user_id, action, window_start, count)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE count = count + 1",
            [$user_id, $action, $window_start]
        );
        return true;
    }
}
```

---

# 7. CSRF PROTECTION

```php
function csrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}
```

---

# 8. FEATURE FLAGS

```sql
CREATE TABLE feature_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    flag_name VARCHAR(100) NOT NULL,
    enabled TINYINT DEFAULT 0,
    rollout_pct INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_tenant_flag (tenant_id, flag_name)
);
```

```php
function featureEnabled($flag_name, $tenant_id) {
    $specific = DB::run(
        "SELECT enabled FROM feature_flags WHERE flag_name=? AND tenant_id=?",
        [$flag_name, $tenant_id]
    )->fetchColumn();
    if ($specific !== false) return (bool) $specific;

    $global = DB::run(
        "SELECT enabled, rollout_pct FROM feature_flags
         WHERE flag_name=? AND tenant_id IS NULL",
        [$flag_name]
    )->fetch();
    if (!$global || !$global['enabled']) return false;

    if ($global['rollout_pct'] < 100) {
        $hash = crc32($tenant_id . $flag_name) % 100;
        return $hash < $global['rollout_pct'];
    }
    return true;
}
```

---

# 9. CIRCUIT BREAKERS

```php
class CircuitBreaker {
    public static function call($service_name, callable $fn, $threshold = 5, $window = 60) {
        if (self::isOpen($service_name, $threshold, $window)) {
            throw new CircuitOpenException("$service_name circuit is open");
        }
        try {
            $result = $fn();
            self::recordSuccess($service_name);
            return $result;
        } catch (Exception $e) {
            self::recordFailure($service_name);
            throw $e;
        }
    }
}
```

---

# 10. KILL SWITCHES

```sql
CREATE TABLE kill_switches (
    name VARCHAR(100) PRIMARY KEY,
    active TINYINT DEFAULT 0,
    activated_at DATETIME NULL,
    activated_by VARCHAR(100) NULL,
    reason TEXT NULL
);
```

```php
function killSwitchActive($name) {
    static $cache = [];
    if (isset($cache[$name])) return $cache[$name];
    $active = DB::run("SELECT active FROM kill_switches WHERE name=?", [$name])->fetchColumn();
    return $cache[$name] = (bool) $active;
}

if (killSwitchActive('ai_global')) {
    return templateFallback('AI временно изключен');
}
```

## 10.2 Автоматично активиране

```php
// cron_auto_kill.php
$ai_failure_rate = DB::run(
    "SELECT AVG(CASE WHEN event_type='ai_failure' THEN 1 ELSE 0 END)
     FROM telemetry_events
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
)->fetchColumn();

if ($ai_failure_rate > 0.50) {
    DB::run("UPDATE kill_switches SET active=1, activated_at=NOW(),
             activated_by='auto', reason='AI failure rate > 50%'
             WHERE name='ai_global'");
    alertTihol("AUTO KILL: AI global switch activated", 'critical');
}
```

---

# 11. КОГАТО НЕЩО СЕ СЧУПИ

## 11.1 MySQL пада

**Recovery:**
1. SSH в droplet
2. `sudo systemctl status mysql`
3. `sudo systemctl restart mysql`
4. Check logs: `tail -100 /var/log/mysql/error.log`
5. Ако OOM → upgrade droplet memory

## 11.2 AI API падне

- Circuit breaker open
- Kill switch auto-activated ако > 50% failures
- Fallback templates
- Pills / Signals работят (чист PHP)

**Recovery:** нищо. Изчакваме да се върне. Магазинът продължава.

## 11.3 Disk пълен

**Recovery:**
1. `du -sh /var/log/*`
2. Изтрий стари logs
3. Upgrade droplet ако е нужно

## 11.4 Cron силент

**Recovery:**
1. SSH → `crontab -l`
2. Ако липсва → re-add
3. Ако PHP fatal → logs

---

# 12. RECOVERY PROCEDURES

## 12.1 Data corruption

1. Stop writes (kill switch)
2. Full backup
3. Identify scope
4. Restore from last clean backup
5. Replay events since backup
6. Verify integrity

## 12.2 Accidental delete

1. Audit log check → what was deleted
2. Soft delete? → restore
3. Hard delete? → restore from nightly backup
4. Cross-reference stock_movements ledger

## 12.3 Security incident

1. Rotate all API keys
2. Invalidate all sessions
3. Force password reset for affected tenants
4. Audit log forensics
5. Notify affected users (GDPR)

---

**КРАЙ НА DOC 06**
