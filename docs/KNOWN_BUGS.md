# KNOWN BUGS — нерешени проблеми

**Last updated:** 2026-05-11 (S140 EOD)

---

## 🐛 BUG #1: Brand shimmer не работи в life-board.php

**Сесия:** S140
**Severity:** Cosmetic
**Status:** Unsolved — за бъдеща сесия

### Описание
В `chat.php` (P11 макет) brand-ът "RunMyStore" има готина wave shimmer
анимация която минава през gradient-а на ~4s цикъл.

В `life-board.php` (P10 макет) същият код за shimmer не работи —
gradient-ът е статичен.

### Опитани решения
- ✗ Override на `.rms-brand` parent с `background: none !important; -webkit-text-fill-color: initial !important; animation: none !important`
- ✗ Override на `.brand-1` с `!important` за gradient + `animation: rmsBrandShimmer 4s linear infinite !important`
- ✗ Hard refresh (Ctrl+Shift+R)
- ✗ Incognito mode

### Suspected causes
1. CSS specificity conflict с P10 inline `.rms-brand` style (има свой background + clip + animation)
2. Browser cache на Capacitor APK (трябва rebuild)
3. Inherited `-webkit-text-fill-color` from parent overrides animated child

### За дебъг
1. Отвори DevTools на `life-board.php` в Chrome
2. Inspect `.brand-1` element
3. View "Computed" tab → виж `animation-name` value
4. Виж "Styles" tab → виж кое правило побеждава (моя override с `!important` или P10 inline)
5. Проверка `background-position` в Computed дали се променя във времето (DevTools → Animations panel)

### Files involved
- `life-board.php` ред 599 (P10 inline `.rms-brand`)
- `life-board.php` ред 1332-1356 (S140 override блок)
- `chat.php` ред 599 (P11 inline — работи там)

---

## 🐛 BUG #2: Feedback бутони (👍👎❓) не записват в DB

**Сесия:** S140
**Severity:** Medium (AI brain няма обратна връзка от потребителя)
**Status:** Unsolved — за бъдеща сесия

### Описание
В Life Board cards (chat.php + life-board.php) expanded view има 3 feedback бутона:
- 👍 Полезно
- 👎 Неполезно
- ❓ Неясно

Click сменя визуално `selected` клас (CSS работи правилно), но **няма AJAX save**.
AI brain не получава обратна връзка → ще продължи да показва същите типове сигнали.

### Текуща имплементация (visual only)

```javascript
// chat.php / life-board.php
function v2lbFb(e, btn, kind) {
    if (e) e.stopPropagation();
    const card = btn.closest('.lb-card');
    if (!card) return;
    card.querySelectorAll('.lb-fb-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    if (navigator.vibrate) navigator.vibrate(8);
    // TODO: AJAX save към insights-feedback.php
}
```

### Required implementation

**1. Schema migration:**
```sql
CREATE TABLE IF NOT EXISTS ai_insight_feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    insight_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    kind ENUM('up','down','hmm') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_insight_user (insight_id, user_id),
    INDEX idx_tenant_created (tenant_id, created_at),
    FOREIGN KEY (insight_id) REFERENCES ai_insights(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**2. Endpoint** (`insights-feedback.php`):
```php
<?php
session_start();
require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
$insight_id = (int)($_POST['insight_id'] ?? 0);
$kind = $_POST['kind'] ?? '';
if (!$insight_id || !in_array($kind, ['up','down','hmm'])) { http_response_code(400); echo json_encode(['ok'=>false]); exit; }
DB::run(
    'INSERT INTO ai_insight_feedback (tenant_id, insight_id, user_id, kind)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE kind=VALUES(kind), created_at=CURRENT_TIMESTAMP',
    [(int)$_SESSION['tenant_id'], $insight_id, (int)$_SESSION['user_id'], $kind]
);
echo json_encode(['ok'=>true]);
```

**3. Update JS:**
```javascript
async function v2lbFb(e, btn, kind) {
    if (e) e.stopPropagation();
    const card = btn.closest('.lb-card');
    if (!card) return;
    const insightId = card.dataset.insightId;
    card.querySelectorAll('.lb-fb-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    if (navigator.vibrate) navigator.vibrate(8);
    if (!insightId) return;
    try {
        const fd = new FormData();
        fd.append('insight_id', insightId);
        fd.append('kind', kind);
        await fetch('/insights-feedback.php', { method: 'POST', body: fd });
    } catch(err) { console.error('Feedback save failed:', err); }
}
```

**4. Card HTML** — добави `data-insight-id="<?= $ins['id'] ?>"` на `.lb-card`.

### За дебъг / тест

```bash
# Provoke a feedback record
curl -X POST -d "insight_id=42&kind=up" -b "PHPSESSID=..." https://runmystore.ai/insights-feedback.php

# Verify
mysql> SELECT * FROM ai_insight_feedback WHERE insight_id=42;
```

---

## 📋 Tracking

| Bug # | Title | Severity | Sessions | Status |
|-------|-------|----------|----------|--------|
| 1 | Brand shimmer life-board | Cosmetic | S140 | Unsolved |
| 2 | Feedback no DB save | Medium | S140 | Unsolved |
