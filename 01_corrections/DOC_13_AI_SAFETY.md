# 📘 DOC 13 — AI SAFETY ARCHITECTURE

## Phase C: Capability Matrix, Kill Switch, Cost Guard, GDPR

**Версия:** 1.0 | **Дата:** 21.04.2026 | **Част от:** Група 5: AI BRAIN

---

## 📑 СЪДЪРЖАНИЕ

1. Защо AI Safety е отделен слой
2. Capability Matrix (role × plan × mode)
3. AI Context Leakage Prevention (GDPR)
4. Global Kill Switch
5. AI Cost Guard + Token Budget
6. AI Prompt Versioning
7. AI Shadow Mode
8. Dry-run levels (SAFE/REVIEW/DANGER)
9. Do Not Disturb Window
10. Trust Erosion Signal
11. Semantic Sanity Checks
12. Audit AI prompt/response
13. Photo upload security + dedup
14. Document Ledger hash chain
15. Feature Flags per tenant

---

# 1. ЗАЩО AI SAFETY Е ОТДЕЛЕН СЛОЙ

AI е **най-рисковата** част на продукта. Един bad response може:
- Да накара Пешо да вземе грешно бизнес решение
- Да наруши GDPR (leak на друг tenant data)
- Да изяде €1000 на месец в неочаквани API calls
- Да изтрие важни данни

**Phase C е посветена на AI Safety.** Преди AI да стане „операционен слой над продукта" (Phase B activate), всички тези защити трябва да са на място.

---

# 2. CAPABILITY MATRIX (ROLE × PLAN × MODE)

Вече описано в DOC 02 § 6. Кратко resumé:

```php
function can(string $action, array $ctx): bool {
    $CAPABILITIES = [ /* ... */ ];
    $cap = $CAPABILITIES[$action] ?? null;
    if (!$cap) return false;
    return in_array($ctx['role'], $cap['roles'])
        && in_array($ctx['plan'], $cap['plans'])
        && in_array($ctx['mode'], $cap['modes']);
}
```

Allchamming endpoint минава през middleware:

```php
requireCan('ai.free_text');
```

---

# 3. AI CONTEXT LEAKAGE PREVENTION (GDPR)

## 3.1 Проблемът (Kimi catch)

Ако AI има **persistent context** между requests → риск за cross-tenant leak:

```
Tenant A: "Моят топ клиент е Иван Петров, телефон 0888..."
[minutes later, different request]
Tenant B: "Кой е твоят топ клиент?"
AI (ако context не е изолиран): "Иван Петров, телефон 0888..."
```

**КАТАСТРОФА.** GDPR violation. Potential lawsuit.

## 3.2 Solution

### Правило 1: Stateless AI calls

Всеки request → нов context. Никакво persistent memory между requests.

```php
function callAI($tenant_id, $prompt) {
    $context = buildContextForTenant($tenant_id);
    return GeminiAPI::call($context, $prompt);
}
```

### Правило 2: Context building with tenant filter

```php
function buildContextForTenant($tenant_id) {
    $products = DB::run("SELECT * FROM products WHERE tenant_id=? AND deleted_at IS NULL",
        [$tenant_id])->fetchAll();

    return [
        'tenant_info' => getTenantInfo($tenant_id),
        'products' => $products,
    ];
}
```

### Правило 3: PII scrubbing в shared contexts

```php
function scrubPII($text) {
    $text = preg_replace('/\b[\w.-]+@[\w.-]+\.\w+\b/', '[EMAIL]', $text);
    $text = preg_replace('/\b\+?\d{9,15}\b/', '[PHONE]', $text);
    $text = preg_replace('/\b(Иван|Петър|Мария)\s+\w+/u', '[NAME]', $text);
    return $text;
}
```

### Правило 4: Audit log

```sql
CREATE TABLE ai_prompt_audit (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    prompt TEXT NOT NULL,
    context_summary TEXT NOT NULL,
    response TEXT NOT NULL,
    tokens_used INT NOT NULL,
    pii_scrubbed TINYINT DEFAULT 0,
    created_at DATETIME NOT NULL
);
```

---

# 4. GLOBAL KILL SWITCH

Описано в DOC 06 § 10. AI може да бъде изключен globally:

```sql
INSERT INTO kill_switches (name, active) VALUES ('ai_global', 0);
```

Когато active=1:
- AI calls → instant fallback template
- Pills и Signals продължават да работят (чист PHP)
- User вижда banner „AI временно недостъпен"

Auto-activate при:
- AI failure rate > 50% за 10 min
- Cost spike > 3× normal за 1h
- Emergency manual trigger от Тихол (Telegram command)

---

# 5. AI COST GUARD + TOKEN BUDGET

## 5.1 Проблемът

Rogue prompt loop: AI изпраща 10,000 requests за 1 час → €500 bill.

## 5.2 Solution

```sql
CREATE TABLE ai_cost_tracking (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    input_tokens INT NOT NULL,
    output_tokens INT NOT NULL,
    cost_cents INT NOT NULL,
    model VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_tenant_created (tenant_id, created_at)
);
```

Daily budget per tenant:

```php
function checkCostBudget($tenant_id, $plan) {
    $daily_budget_cents = [
        'free' => 500,
        'start' => 1500,
        'pro' => 5000,
    ][$plan] ?? 500;

    $used_today = DB::run(
        "SELECT COALESCE(SUM(cost_cents), 0) FROM ai_cost_tracking
         WHERE tenant_id=? AND DATE(created_at)=CURDATE()",
        [$tenant_id]
    )->fetchColumn();

    if ($used_today >= $daily_budget_cents) {
        alertTihol("Tenant $tenant_id exceeded daily AI budget: €" . ($used_today/100));
        return false;
    }

    return true;
}
```

---

# 6. AI PROMPT VERSIONING

Всеки production prompt има версия:

```sql
CREATE TABLE ai_prompt_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    version VARCHAR(10) NOT NULL,
    system_prompt TEXT NOT NULL,
    user_template TEXT NOT NULL,
    active TINYINT DEFAULT 0,
    shadow_mode TINYINT DEFAULT 0,
    created_at DATETIME NOT NULL,
    created_by VARCHAR(100) NOT NULL,
    UNIQUE KEY uk_name_version (name, version)
);
```

При промяна:
1. Create new version (не update стария)
2. Run в shadow mode 2 weeks
3. Promote → mark active=1, deactivate стария
4. Retain audit trail

---

# 7. AI SHADOW MODE

Описано в DOC 12 § 11.

Shadow mode за **нови insight templates**:
1. Template генерира insights, save в `ai_shadow_log`
2. НЕ показва на user
3. Тихол преглежда седмично
4. Approve/reject/rewrite

---

# 8. DRY-RUN LEVELS

Всяко AI action има risk level:

| Level | Action example | Confirmation |
|---|---|---|
| **SAFE** | get_stock, view_stats | Нищо — изпълнява се директно |
| **REVIEW** | create_order_draft, suggest_price | Preview показан, Пешо confirms |
| **DANGER** | delete_product, bulk_price_change | Voice PIN + typed confirmation |

```php
function executeAction($action, $params) {
    $risk = $MODULE_ACTIONS[$action]['risk_level'];

    if ($risk === 'REVIEW') {
        $preview = generatePreview($action, $params);
        return ['status' => 'pending_confirmation', 'preview' => $preview];
    }

    if ($risk === 'DANGER') {
        if (!verifyPinAndTyped($params)) {
            return ['status' => 'pin_required'];
        }
    }

    return ActionHandlers::run($action, $params);
}
```

---

# 9. DO NOT DISTURB WINDOW

Описано в DOC 02 § 8. Per-tenant configuration:

```sql
ALTER TABLE tenants
  ADD COLUMN dnd_start TIME DEFAULT '22:00',
  ADD COLUMN dnd_end TIME DEFAULT '07:00',
  ADD COLUMN dnd_action_policy ENUM('block','require_pin','log_only') DEFAULT 'require_pin';
```

В DND window:
- Voice actions → PIN required
- Push notifications → paused
- AI proactive messages → queued for morning

---

# 10. TRUST EROSION SIGNAL

Ако Пешо често дава 👎 на insights → AI trust score decay:

```sql
CREATE TABLE ai_trust_scores (
    tenant_id INT PRIMARY KEY,
    trust_score INT DEFAULT 100,
    last_updated DATETIME NOT NULL
);
```

При ниско trust (<50):
- Reduce frequency на proactive messages
- Add disclaimer („Може да бъркам, провери")
- Show more data, less interpretation

---

# 11. SEMANTIC SANITY CHECKS

Преди AI output да стигне Пешо:

```php
function semanticSanity($ai_output, $source_data) {
    preg_match_all('/\d+/', $ai_output, $nums);
    foreach ($nums[0] as $n) {
        if (!numberExistsInData($n, $source_data)) {
            return false;
        }
    }

    preg_match_all('/(Nike|Adidas|Passionata)\s+\w+/u', $ai_output, $products);
    foreach ($products[0] as $p) {
        if (!productExists($p, $source_data['tenant_id'])) {
            return false;
        }
    }

    preg_match_all('/\d{4}-\d{2}-\d{2}/', $ai_output, $dates);
    foreach ($dates[0] as $d) {
        if (strtotime($d) < strtotime('-5 years') || strtotime($d) > strtotime('+1 year')) {
            return false;
        }
    }

    return true;
}
```

---

# 12. AUDIT AI PROMPT/RESPONSE

Full logging (DOC 03 § 3):

```sql
CREATE TABLE ai_full_audit (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    system_prompt TEXT NOT NULL,
    user_prompt TEXT NOT NULL,
    full_response TEXT NOT NULL,
    fact_verifier_result JSON NOT NULL,
    semantic_sanity_result JSON NOT NULL,
    tokens_input INT NOT NULL,
    tokens_output INT NOT NULL,
    cost_cents INT NOT NULL,
    model VARCHAR(50) NOT NULL,
    latency_ms INT NOT NULL,
    created_at DATETIME NOT NULL
);
```

Retention: 90 дни. После archive в cold storage.

---

# 13. PHOTO UPLOAD SECURITY + DEDUP

## 13.1 Security checks

```php
function validatePhoto($file) {
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
        throw new Exception('Invalid file type');
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File too large');
    }

    $img = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    if (!$img) {
        throw new Exception('Not a valid image');
    }
    imagedestroy($img);

    $stripped = stripExif($file['tmp_name']);
    return $stripped;
}
```

## 13.2 SHA-256 dedup

```php
function uploadPhoto($file, $tenant_id) {
    $hash = hash_file('sha256', $file['tmp_name']);

    $existing = DB::run(
        "SELECT path FROM photo_uploads WHERE tenant_id=? AND sha256=?",
        [$tenant_id, $hash]
    )->fetch();

    if ($existing) {
        return $existing['path'];
    }

    $path = "/uploads/$tenant_id/" . $hash . '.jpg';
    move_uploaded_file($file['tmp_name'], $path);

    DB::run("INSERT INTO photo_uploads (tenant_id, sha256, path, uploaded_at)
             VALUES (?, ?, ?, NOW())",
        [$tenant_id, $hash, $path]);

    return $path;
}
```

---

# 14. DOCUMENT LEDGER HASH CHAIN

За НАП compliance — audit trail на всички финансови документи:

```sql
CREATE TABLE document_ledger (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    document_id BIGINT NOT NULL,
    previous_hash VARCHAR(64) NOT NULL,
    current_hash VARCHAR(64) NOT NULL,
    payload_json JSON NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_tenant_type (tenant_id, document_type)
);
```

Всеки нов document → SHA-256 на previous_hash + payload → current_hash.

Tampering detection: rebuild chain, compare hashes.

---

# 15. FEATURE FLAGS PER TENANT

Описано в DOC 06 § 8.

Нови AI features → gradual rollout:

```
1. Dev — само Тихол
2. Beta — 5 beta tenants
3. 10% — random 10% от users
4. 50% — random 50%
5. 100% — all users
6. Remove flag
```

Ако bug → instant rollback чрез flag.

---

**КРАЙ НА DOC 13**
