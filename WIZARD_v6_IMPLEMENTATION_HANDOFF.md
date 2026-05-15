# 🎯 WIZARD v6 IMPLEMENTATION HANDOFF — S148 task brief

> **За кого:** Шеф-чат S148. Не Claude Code. Не друг чат за дизайн.
>
> **Цел:** Имплементирай новия wizard "Добави артикул" v6 в `products.php` без да счупиш sacred zones (voice STT, color detection, Bluetooth printer).
>
> **Beta launch:** 14-15.05.2026 → ~30 дни. Време е критично.
>
> **Подход:** 5 фази, контролирано. Ти push-ваш — Тих pull-ва на droplet. Малки commit-и, лесен rollback.

═══════════════════════════════════════════════════════════════
📜 СЪДЪРЖАНИЕ
═══════════════════════════════════════════════════════════════

1. State of the world (какво е завършено)
2. Sacred zones map (с exact line numbers)
3. Reading list (smart, не цели файлове)
4. ФАЗА 1 — Sacred glass CSS migration в products.php
5. ФАЗА 2 — DB migration + AI endpoints
6. ФАЗА 3 — Wizard HTML restructure (НАЙ-РИСКОВО)
7. ФАЗА 4 — Multi-photo + Matrix fullscreen integration
8. ФАЗА 5 — Integration testing на tenant_id=7
9. Acceptance criteria (per фаза)
10. Rollback plan (per фаза)
11. STOP signals
12. Communication protocol
13. Test checklist
14. AI endpoint JSON schemas
15. Pull-from-mockup checklist

═══════════════════════════════════════════════════════════════
1. STATE OF THE WORLD (какво е готово, какво остава)
═══════════════════════════════════════════════════════════════

### Завършено в S145 (concept, 13.05):
- ✅ `WIZARD_DOBAVI_ARTIKUL_v5_SPEC.md` (~1230 реда, 25 секции) — пълна спецификация
- ✅ Първа версия mockup `wizard_v5_ai_vision_FINAL.html` (не се ползва вече, заменен от v6)

### Завършено в S146 (design, друг чат, 14.05):
- ✅ `mockups/wizard_v6_INTERACTIVE.html` (1467 реда) — главен mockup с 4 акордеона, interactive demo bar за превключване между states

### Завършено в S147 (visual refinement, мен, 15.05):
- ✅ `mockups/wizard_v6_matrix_fullscreen.html` (364 реда) — matrix отделен fullscreen екран
- ✅ `mockups/wizard_v6_multi_photo_flow.html` (415 реда) — 3-frame flow (capture → AI detect → result)
- ✅ Sacred CSS injection в wizard_v6_INTERACTIVE (12 cards със `.glass.qd/qm/q3` + 4 spans)
- ✅ Aurora intensification (opacity 0.45, blur 80px, 4 blobs)
- ✅ Neumorphic depth на бутони (mic-btn, copy-btn, step-btn, save-btn-main, chips)
- ✅ Flow корекции: Section 1 single ред (име→цена→AI markup→qty→min→категория→артикулен→баркод→пол/сезон/марка/описание); Section 3 no-photo (бизнес полета горе, AI fallback долу)
- ✅ Matrix unified — Section 2 matrix копира fullscreen дизайна, цифрите по-тъмни в light mode

**Последен commit преди handoff:** `bf90f2d` (sacred glass CSS 1:1 от §5.4)
**Backup tag:** `pre-s147-wizard-redesign` (за emergency rollback)

### Какво ОСТАВА (твоята работа):
- ❌ Sacred glass CSS pattern в products.php (само в mockup-а сега)
- ❌ DB колони `gender`, `season`, `brand`, `description_short` (от S143 plan, още не приложени)
- ❌ DB таблица `ai_snapshots` (perceptual hash cache)
- ❌ DB таблица `pricing_patterns` (per-category multipliers)
- ❌ `services/ai-vision.php` endpoint (нов)
- ❌ `services/ai-markup.php` endpoint (нов)
- ❌ Wizard HTML restructure (4-те sub-pages → 4 акордеона)
- ❌ Multi-photo flow integration в products.php
- ❌ Matrix fullscreen overlay route
- ❌ Tests на tenant_id=7

═══════════════════════════════════════════════════════════════
2. SACRED ZONES MAP (С EXACT LINE NUMBERS)
═══════════════════════════════════════════════════════════════

### 2.1 НИКОГА НЕ ПИПАТЕ — sacred files:
- `services/voice-tier2.php` — Whisper Groq STT
- `services/ai-color-detect.php` — Color detection (вкл. `?multi=1`)
- `services/price-ai.php` — Price AI parser (LOCKED от 03.05.2026)
- `js/capacitor-printer.js` — DTM-5811 Bluetooth printer

### 2.2 products.php — sacred functions (НЕ ПИПАШ телата им):

| Function | Line | Защо sacred |
|---|---|---|
| `_wizDraftKey()` | 7598 | Draft autosave key |
| `_wizSaveDraft()` | 7602 | LocalStorage persistence |
| `_wizClearDraft()` | 7630 | Draft cleanup |
| `_wizGetDraft()` | 7633 | Draft recovery |
| `_wizDescribeDraft()` | 7646 | Draft summary helpers |
| `openManualWizard()` | 7658 | Entry point — НЕ менаш signature |
| `renderWizPage(step)` | 8047 | Main render — само ВНЕТРЕ заменяш |
| `renderWizPagePart2(step)` | 8321 | Step 4 vararations — само ВНЕТРЕ |
| `renderStudioStep()` | 8896 | AI Studio integration |
| `_wizAIInlineRows()` | 8997 | AI snippets — LOCKED |
| `_wizMarginPct(cost, retail)` | 9100 | Margin formula — НЕ MENI |
| `renderWizStep2()` | 9228 | Step 2 render |
| `renderWizPhotoStep()` | 12391 | Photo capture step |
| `renderLikePrevPageS88(d)` | 13369 | "Като предния" page renderer |
| `_wizMicWhisper(field, lang)` | 14341 | Voice Whisper integration |

### 2.3 8 mic input полета:
- В HTML wrapper-ите можеш да местиш, но `onclick="_wizMicWhisper('field_name', 'bg')"` остава **БЕЗ ПРОМЯНА на signature**
- Field names: `name`, `retail_price`, `wholesale_price`, `cost_price`, `quantity`, `barcode`, `code_sku`, `description_short` (нов от S143)

### 2.4 `S.wizData` state machine:
- Глобална JS променлива — НЕ преименуваш
- Keys: `_photoDataUrl`, `category`, `subcategory`, `colors`, `sizes`, `gender`, `season`, `brand`, `description_short`, `retail_price`, `cost_price`, `wholesale_price`, `quantity`, `min_quantity`, `barcode`, `code_sku`, `supplier_id`, `material`, `origin`, `unit`
- `S.wizStep` — current step (0-8). Запазваме enumeration.

═══════════════════════════════════════════════════════════════
3. READING LIST (SMART, ПЕСТИШ КОНТЕКСТ)
═══════════════════════════════════════════════════════════════

### Phase A — Pre-read (изцяло):
1. `WIZARD_DOBAVI_ARTIKUL_v5_SPEC.md` (1230 реда) — ПЪЛНА spec
2. `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` редове 720-790 (§5.4 Sacred Glass)
3. `AUTO_PRICING_DESIGN_LOGIC.md` (568 реда) — markup formulas
4. `docs/AI_AUTOFILL_SOURCE_OF_TRUTH.md` (600 реда) — Gemini JSON schema + ai_snapshots DDL
5. `docs/BIBLE_v3_0_CORE.md` — Закон №1, №3, №6
6. `MASTER_COMPASS.md` — Standing Rules (Rule #38)
7. `TOMORROW_WIZARD_REDESIGN.md` — DB колони от S143

### Phase B — Mockup-и (изцяло, те са визуалната референция):
8. `mockups/wizard_v6_INTERACTIVE.html` (1467 реда)
9. `mockups/wizard_v6_matrix_fullscreen.html` (364 реда)
10. `mockups/wizard_v6_multi_photo_flow.html` (415 реда)

### Phase C — products.php (САМО grep, НЕ цяло четене):

```bash
# Sacred functions (виж секция 2.2 за пълен списък)
grep -n "function _wizMicWhisper\|function _wizPriceParse\|function _bgPrice" products.php

# Wizard state machine
grep -n "S.wizData\|S.wizStep\|S.wizVoiceMode" products.php | head -30

# Existing sub-page rendering
grep -n "function renderWizPage\|function renderWizPagePart2\|function renderWizStep2\|function renderWizPhotoStep" products.php
```

**View конкретни редове само когато се налага:** `python3 /tmp/gh.py products.php -r 8047:8320` (renderWizPage целия).

### Phase D — Services (cele, малки):
11. `services/voice-tier2.php` (~300 реда) — sacred reference
12. `services/ai-color-detect.php` (~250 реда) — sacred reference
13. `services/price-ai.php` (~200 реда) — sacred reference

**Не цял `products.php` (15530 реда).** Пести контекста за реалната работа.

═══════════════════════════════════════════════════════════════
4. ФАЗА 1 — SACRED GLASS CSS MIGRATION В products.php (4-6h)
═══════════════════════════════════════════════════════════════

**Цел:** Прехвърли `.glass + .shine + .glow + hue overrides` от `wizard_v6_INTERACTIVE.html` в products.php, така че Simple home + wizard да получат neon на cards.

### 4.1 Sacred CSS блок (КОПИРАЙ 1:1 от §5.4 DESIGN_SYSTEM_v4.0):

Добави в `<style>` секцията на products.php (преди existing rules):

```css
/* SACRED GLASS — 1:1 от §5.4 DESIGN_SYSTEM_v4.0_BICHROMATIC */
:root{
  --border:1px;
  --z-aurora:0; --z-shine:1; --z-glow:3; --z-content:5;
}
.glass{position:relative;border-radius:var(--radius);
  border:var(--border) solid transparent;isolation:isolate}
.glass.sm{border-radius:var(--radius-sm)}
.glass .shine,.glass .glow{--hue:var(--hue1)}
.glass .shine-bottom,.glass .glow-bottom{--hue:var(--hue2);--conic:135deg}

[data-theme="light"] .glass,
:root:not([data-theme]) .glass{
  background:var(--surface);
  box-shadow:var(--shadow-card)
}

[data-theme="light"] .glass .shine,[data-theme="light"] .glass .glow,
:root:not([data-theme]) .glass .shine,:root:not([data-theme]) .glass .glow{
  display:none
}

[data-theme="dark"] .glass{
  background:
    linear-gradient(235deg,
      hsl(var(--hue1) 50% 10% / .8),
      hsl(var(--hue1) 50% 10% / 0) 33%),
    linear-gradient(45deg,
      hsl(var(--hue2) 50% 10% / .8),
      hsl(var(--hue2) 50% 10% / 0) 33%),
    linear-gradient(hsl(220 25% 4.8% / .78));
  backdrop-filter:blur(12px);
  -webkit-backdrop-filter:blur(12px);
  box-shadow:var(--shadow-card)
}

[data-theme="dark"] .glass .shine{
  pointer-events:none;
  border-radius:0;
  border-top-right-radius:inherit;
  border-bottom-left-radius:inherit;
  border:1px solid transparent;
  width:75%; aspect-ratio:1;
  display:block; position:absolute;
  right:calc(var(--border) * -1);
  top:calc(var(--border) * -1);
  z-index:var(--z-shine);
  background:conic-gradient(from var(--conic, -45deg) at center in oklch,
    transparent 12%, hsl(var(--hue), 80%, 60%), transparent 50%) border-box;
  mask:linear-gradient(transparent),linear-gradient(black);
  mask-clip:padding-box,border-box;
  mask-composite:subtract
}

[data-theme="dark"] .glass .shine.shine-bottom{
  right:auto; top:auto;
  left:calc(var(--border) * -1);
  bottom:calc(var(--border) * -1)
}

[data-theme="dark"] .glass .glow{
  pointer-events:none;
  border-top-right-radius:calc(var(--radius) * 2.5);
  border-bottom-left-radius:calc(var(--radius) * 2.5);
  border:calc(var(--radius) * 1.25) solid transparent;
  inset:calc(var(--radius) * -2);
  width:75%; aspect-ratio:1;
  display:block; position:absolute;
  left:auto; bottom:auto;
  background:conic-gradient(from var(--conic, -45deg) at center in oklch,
    hsl(var(--hue), 80%, 60% / .5) 12%, transparent 50%);
  filter:blur(12px) saturate(1.25);
  mix-blend-mode:plus-lighter;
  z-index:var(--z-glow);
  opacity:0.6
}

[data-theme="dark"] .glass .glow.glow-bottom{
  inset:auto;
  left:calc(var(--radius) * -2);
  bottom:calc(var(--radius) * -2)
}

/* Hue overrides */
[data-theme="dark"] .glass.q1 .shine,[data-theme="dark"] .glass.q1 .glow{--hue:0}
[data-theme="dark"] .glass.q1 .shine-bottom,[data-theme="dark"] .glass.q1 .glow-bottom{--hue:15}
[data-theme="dark"] .glass.q2 .shine,[data-theme="dark"] .glass.q2 .glow,
[data-theme="dark"] .glass.qm .shine,[data-theme="dark"] .glass.qm .glow{--hue:280}
[data-theme="dark"] .glass.q2 .shine-bottom,[data-theme="dark"] .glass.q2 .glow-bottom{--hue:305}
[data-theme="dark"] .glass.qm .shine-bottom,[data-theme="dark"] .glass.qm .glow-bottom{--hue:310}
[data-theme="dark"] .glass.q3 .shine,[data-theme="dark"] .glass.q3 .glow{--hue:145}
[data-theme="dark"] .glass.q3 .shine-bottom,[data-theme="dark"] .glass.q3 .glow-bottom{--hue:165}
[data-theme="dark"] .glass.q4 .shine,[data-theme="dark"] .glass.q4 .glow{--hue:180}
[data-theme="dark"] .glass.q4 .shine-bottom,[data-theme="dark"] .glass.q4 .glow-bottom{--hue:195}
[data-theme="dark"] .glass.q5 .shine,[data-theme="dark"] .glass.q5 .glow{--hue:38}
[data-theme="dark"] .glass.q5 .shine-bottom,[data-theme="dark"] .glass.q5 .glow-bottom{--hue:28}
[data-theme="dark"] .glass.qd .shine,[data-theme="dark"] .glass.qd .glow{--hue:var(--hue1)}
[data-theme="dark"] .glass.qd .shine-bottom,[data-theme="dark"] .glass.qd .glow-bottom{--hue:var(--hue2)}

.glass > *:not(.shine):not(.glow){
  position:relative; z-index:var(--z-content)
}
```

### 4.2 JS injection hook за auto-spans:

Добави в края на products.php script секцията:

```javascript
// SACRED GLASS INJECTION — wraps cards със .glass class + 4 spans
(function injectGlassSpans(){
  function addSpans(el){
    if(el.querySelector(':scope > .shine')) return;
    var s=['shine','shine shine-bottom','glow','glow glow-bottom'];
    s.reverse().forEach(c=>{
      var sp=document.createElement('span');sp.className=c;
      el.insertBefore(sp,el.firstChild);
    });
  }
  // Target classes (расти при нужда)
  var targets=[
    '.acc-section',          // wizard акордеони (когато ги направим)
    '.qa-btn','.qa-card',    // quick action cards Simple home
    '.lb-card',              // life-board cards
    '.help-card',            // help cards
    '.ai-vision-banner',     // нов AI vision banner
    '.ai-markup-row'         // нов AI markup row
  ];
  document.querySelectorAll(targets.join(',')).forEach(function(el){
    if(!el.classList.contains('glass')) el.classList.add('glass','qd');
    addSpans(el);
  });
})();
```

### 4.3 Acceptance:
- ✅ Refresh `products.php?tenant_id=7` на droplet
- ✅ В dark mode — съществуващите cards (Simple home quick actions) трябва да имат purple conic shine top-right + glow bottom-left
- ✅ В light mode — нищо не се променя (sacred правило)
- ✅ Wizard НЕ е счупен (отвори wizard с "Добави артикул" → виж че всичко работи)

### 4.4 Rollback (ако счупим нещо):
```bash
git revert HEAD && git push origin main
```

═══════════════════════════════════════════════════════════════
5. ФАЗА 2 — DB MIGRATION + AI ENDPOINTS (4-6h)
═══════════════════════════════════════════════════════════════

### 5.1 DB migration script:

⚠️ **MySQL 8 НЕ поддържа `ADD COLUMN IF NOT EXISTS`** — ползвай PREPARE/EXECUTE с information_schema:

```sql
-- migrations/s148_wizard_v6.sql

-- 1) Добави 4 нови колони в products
SET @s := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema=DATABASE() AND table_name='products' AND column_name='gender')=0,
  'ALTER TABLE products ADD COLUMN gender ENUM(\"male\",\"female\",\"kid\",\"unisex\") NULL AFTER subcategory_id',
  'SELECT \"gender exists\" AS msg'
));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema=DATABASE() AND table_name='products' AND column_name='season')=0,
  'ALTER TABLE products ADD COLUMN season ENUM(\"summer\",\"winter\",\"transition\",\"year_round\") NULL AFTER gender',
  'SELECT \"season exists\" AS msg'
));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema=DATABASE() AND table_name='products' AND column_name='brand')=0,
  'ALTER TABLE products ADD COLUMN brand VARCHAR(100) NULL AFTER season',
  'SELECT \"brand exists\" AS msg'
));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema=DATABASE() AND table_name='products' AND column_name='description_short')=0,
  'ALTER TABLE products ADD COLUMN description_short TEXT NULL AFTER brand',
  'SELECT \"description_short exists\" AS msg'
));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Нова таблица ai_snapshots (perceptual hash cache за AI vision)
CREATE TABLE IF NOT EXISTS ai_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  phash VARCHAR(64) NOT NULL,
  result_json JSON NOT NULL,
  confidence DECIMAL(4,3) NOT NULL DEFAULT 0,
  used_count INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_phash (phash),
  INDEX idx_tenant_phash (tenant_id, phash),
  INDEX idx_last_used (last_used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Нова таблица pricing_patterns (per-category multipliers)
CREATE TABLE IF NOT EXISTS pricing_patterns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  category_id INT NULL,            -- NULL = global default
  subcategory_id INT NULL,
  multiplier DECIMAL(4,2) NOT NULL,   -- e.g. 2.50
  ending_pattern ENUM('.99','.90','.50','exact') NOT NULL DEFAULT '.99',
  confidence DECIMAL(4,3) NOT NULL DEFAULT 0.500,
  sample_size INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tenant_category (tenant_id, category_id, subcategory_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Apply процедура:**
```bash
# 1. Backup
mysqldump runmystore products > /tmp/products_pre_s148_$(date +%Y%m%d).sql
mysqldump runmystore --no-data ai_snapshots pricing_patterns > /tmp/new_tables_check.sql 2>&1 || echo "Tables don't exist yet — expected"

# 2. Apply
mysql runmystore < migrations/s148_wizard_v6.sql

# 3. Verify
mysql runmystore -e "SHOW COLUMNS FROM products LIKE '%gender%';"
mysql runmystore -e "SHOW COLUMNS FROM products LIKE '%season%';"
mysql runmystore -e "SHOW COLUMNS FROM products LIKE '%brand%';"
mysql runmystore -e "SHOW COLUMNS FROM products LIKE '%description_short%';"
mysql runmystore -e "SHOW TABLES LIKE 'ai_snapshots';"
mysql runmystore -e "SHOW TABLES LIKE 'pricing_patterns';"
```

### 5.2 `services/ai-vision.php` endpoint (нов):

**Цел:** 1 обаждане към Gemini 2.5 Flash → JSON с всичко (category, subcategory, color, material, gender, season, brand, short_description).

**JSON response schema:**
```json
{
  "ok": true,
  "phash": "abcd1234...",
  "cache_hit": false,
  "result": {
    "category": "Бикини",
    "category_confidence": 0.94,
    "subcategory": "Дамски бикини",
    "subcategory_confidence": 0.87,
    "color_primary": {"name": "Розов", "hex": "#ec4899", "confidence": 0.89},
    "color_secondary": [{"name": "Бял", "hex": "#ffffff", "confidence": 0.72}],
    "material": "Памук с ластан",
    "material_confidence": 0.78,
    "gender": "female",
    "gender_confidence": 0.96,
    "season": "summer",
    "season_confidence": 0.85,
    "brand": "Tommy Jeans",
    "brand_confidence": 0.82,
    "description_short": "Дамски бикини от мек памук с ластан. Розов цвят с малки бели точки. Класически крой с ниска талия."
  },
  "tokens_used": 245,
  "cost_eur": 0.0015
}
```

**2-level cache (преди обаждане към Gemini):**

```php
// Level 1: barcode lookup (ако barcode е подаден)
$existing = DB::run("SELECT * FROM products WHERE barcode = ? LIMIT 1", [$barcode]);
if ($existing) {
    // Same tenant → копира всичко
    // Different tenant → копира non-sensitive (category, color, material, gender, season, brand, description_short — НЕ цени)
    return ['ok'=>true, 'cache_hit'=>'barcode', 'result'=>$existing];
}

// Level 2: perceptual hash от ai_snapshots
$phash = perceptual_hash($image_data);  // helper
$cached = DB::run("SELECT result_json FROM ai_snapshots
                   WHERE tenant_id = ? AND phash = ? AND confidence > 0.7
                   ORDER BY last_used DESC LIMIT 1",
                  [$tenant_id, $phash]);
if ($cached) {
    DB::run("UPDATE ai_snapshots SET used_count=used_count+1, last_used=NOW()
             WHERE tenant_id = ? AND phash = ?", [$tenant_id, $phash]);
    return ['ok'=>true, 'cache_hit'=>'phash', 'result'=>json_decode($cached['result_json'], true)];
}

// Level 3: Gemini 2.5 Flash обаждане
$response = call_gemini($image_data, $prompt);

// Save в cache
DB::run("INSERT INTO ai_snapshots (tenant_id, phash, result_json, confidence)
         VALUES (?, ?, ?, ?)",
        [$tenant_id, $phash, json_encode($response['result']), $response['confidence']]);

return ['ok'=>true, 'cache_hit'=>false, 'result'=>$response['result']];
```

**Gemini prompt** (от `docs/AI_AUTOFILL_SOURCE_OF_TRUTH.md` §schema):
- Подаваш image + JSON schema instruction
- Очакваш JSON output (Gemini support-ва response_mime_type='application/json')
- Cost: ~€0.0015 на снимка

### 5.3 `services/ai-markup.php` endpoint (нов):

**Цел:** При даден cost_price → AI предлага retail price.

**Request:**
```json
{
  "cost_price": 12.00,
  "category_id": 47,
  "subcategory_id": 132,
  "tenant_id": 7
}
```

**Response:**
```json
{
  "ok": true,
  "retail_price": 27.99,
  "multiplier": 2.5,
  "ending": ".99",
  "category_name": "бельо",
  "confidence": 0.92,
  "sample_size": 47,
  "routing": "auto"
}
```

**Логика:**
```php
// 1) Find pricing pattern
$pattern = DB::run("SELECT * FROM pricing_patterns
                    WHERE tenant_id = ? AND category_id = ? AND subcategory_id = ?
                    LIMIT 1", [$tenant_id, $category_id, $subcategory_id]);

if (!$pattern) {
    // Fallback to category-only
    $pattern = DB::run("SELECT * FROM pricing_patterns
                        WHERE tenant_id = ? AND category_id = ? AND subcategory_id IS NULL
                        LIMIT 1", [$tenant_id, $category_id]);
}

if (!$pattern) {
    // Cold start: global default ×2 + .90 (от AUTO_PRICING_DESIGN_LOGIC §3.3)
    $pattern = ['multiplier'=>2.0, 'ending_pattern'=>'.90', 'confidence'=>0.5];
}

// 2) Compute
$raw = $cost_price * $pattern['multiplier'];
$retail = apply_ending($raw, $pattern['ending_pattern']);

// 3) Confidence routing (LAW №8)
$routing = $pattern['confidence'] > 0.85 ? 'auto'
         : ($pattern['confidence'] >= 0.5 ? 'confirm' : 'manual');

return ['ok'=>true, 'retail_price'=>$retail, 'multiplier'=>$pattern['multiplier'],
        'ending'=>$pattern['ending_pattern'], 'confidence'=>$pattern['confidence'],
        'routing'=>$routing];

function apply_ending($raw, $pattern) {
    $floor = floor($raw);
    switch($pattern) {
        case '.99': return $floor + 0.99;
        case '.90': return $floor + 0.90;
        case '.50': return $floor + 0.50;
        case 'exact': return round($raw, 2);
    }
}
```

### 5.4 Acceptance:
- ✅ DB колони добавени без data loss
- ✅ `curl -X POST .../services/ai-vision.php -F image=@test.jpg -F tenant_id=7` връща JSON с правилен schema
- ✅ `curl -X POST .../services/ai-markup.php -d cost_price=12&category_id=47&tenant_id=7` връща retail price
- ✅ `ai_snapshots` таблица натрупва entries при повторни обаждания

### 5.5 Rollback:
```sql
-- ВНИМАНИЕ: губим колони + таблици
ALTER TABLE products DROP COLUMN description_short, DROP COLUMN brand,
                     DROP COLUMN season, DROP COLUMN gender;
DROP TABLE ai_snapshots;
DROP TABLE pricing_patterns;
```

═══════════════════════════════════════════════════════════════
6. ФАЗА 3 — WIZARD HTML RESTRUCTURE (НАЙ-РИСКОВО, 8-12h)
═══════════════════════════════════════════════════════════════

⚠️ **ВНИМАНИЕ:** Това е най-рисковата фаза. Тих е загубил доверие след S104/S105/S113 (3 проваля рестарта на products.php). Действай **бавно**, по 1 sub-page на commit.

### 6.1 Стратегия — DELETE + INSERT, не MERGE (Standing Rule #32):
- Mockup `wizard_v6_INTERACTIVE.html` = ground truth
- Локализирай target секция в products.php → DELETE целия HTML/CSS блок → INSERT mockup body innerHTML 1:1
- Запази JS handlers + PHP backend INTACT
- Mockup ID-та = production DOM ID-та

### 6.2 Mapping mockup → products.php:

| Mockup секция | products.php функция | Line | Какво се прави |
|---|---|---|---|
| Top header (← + title + Като предния pill + theme) | `renderWizPage(0)` или нов | new | DELETE old top bar, INSERT new |
| Search pill + Voice bar | reuse from existing | existing | НЕ менаш |
| Mode toggle (Единичен/С вариации) | `renderWizPage(0)` body | 8047-8320 | INSERT new |
| Section 1 (Снимка + Основно) | merge of `renderWizPage(0)` + `renderWizPhotoStep()` | 8047 + 12391 | merge в нов акордеон |
| Section 2 (Вариации) | `renderWizPagePart2(step=4)` | 8321 | INSERT нова Variations секция |
| Section 3 (Допълнителни) | new | new | INSERT нов акордеон |
| Section 4 (AI Studio) | reuse `renderStudioStep()` | 8896 | wrapper само |
| Bottom bar (Undo / Print / CSV / Следващ) | existing | existing | НЕ менаш функционалност |

### 6.3 4-те AI conditional полета:

**HTML structure (от mockup-а):**
```html
<!-- Section 1 — AI РАЗПОЗНАТИ полета (показват се само когато S.wizData._photoDataUrl !== null) -->
<div id="aiCategoryBlock" style="display:none">
  <!-- Категория с AI confirm row -->
</div>

<!-- Артикулен номер + Баркод (винаги видими) -->
...

<!-- AI РАЗПОЗНАТИ полета (продължение) -->
<div id="aiOtherBlock" style="display:none">
  <!-- Пол chips (4 опции, single-select) -->
  <!-- Сезон chips (4 опции, single-select) -->
  <!-- Марка input + recently-used chips -->
  <!-- Кратко описание textarea + AI ✨ generate -->
</div>
```

**JS conditional logic:**
```javascript
function updateAIBlocks() {
  var hasPhoto = !!(S.wizData && S.wizData._photoDataUrl);
  document.getElementById('aiCategoryBlock').style.display = hasPhoto ? 'block' : 'none';
  document.getElementById('aiOtherBlock').style.display   = hasPhoto ? 'block' : 'none';
  // Fallback в Section 3
  document.getElementById('fallbackAIBlock').style.display = hasPhoto ? 'none' : 'block';
  document.getElementById('fallbackBanner').style.display  = hasPhoto ? 'none' : 'flex';
}
```

### 6.4 "Като предния" pill в header:

```html
<button class="kp-pill glass sm qm" onclick="toggleBulkMode()">
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <span class="glow"></span><span class="glow glow-bottom"></span>
  <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/>
    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
  <span>Като предния</span>
</button>
```

**JS:**
```javascript
function toggleBulkMode() {
  S.wizBulkMode = !S.wizBulkMode;
  document.getElementById('kpPill').classList.toggle('active');
  document.getElementById('bulkBanner').classList.toggle('show');

  if (S.wizBulkMode && S.lastSavedProduct) {
    // Inherit name, price, supplier, category (НЕ снимка, НЕ баркод)
    S.wizData.name = S.lastSavedProduct.name;
    S.wizData.retail_price = S.lastSavedProduct.retail_price;
    S.wizData.supplier_id = S.lastSavedProduct.supplier_id;
    S.wizData.category_id = S.lastSavedProduct.category_id;
    // Render placeholders showing inherited values
    document.querySelector('#wizNameField input').placeholder =
      'наследено: ' + S.lastSavedProduct.name;
    // Price дори не се пише в bulk режим
    document.querySelector('#wizPriceField input').disabled = true;
  }
}
```

### 6.5 AI Vision integration:

```javascript
async function uploadPhoto(file) {
  // Upload + show preview
  var reader = new FileReader();
  reader.onload = async function(e) {
    S.wizData._photoDataUrl = e.target.result;
    document.getElementById('photoPreview').src = e.target.result;

    // Show loading state
    showLoadingOverlay('AI разпознава...');

    // Call AI Vision endpoint
    var formData = new FormData();
    formData.append('image', file);
    formData.append('tenant_id', TENANT_ID);
    formData.append('barcode', S.wizData.barcode || '');

    try {
      var resp = await fetch('/services/ai-vision.php', {
        method: 'POST', body: formData
      });
      var data = await resp.json();

      if (data.ok) {
        // Auto-fill fields
        S.wizData.category = data.result.category;
        S.wizData.subcategory = data.result.subcategory;
        S.wizData.colors = [data.result.color_primary, ...data.result.color_secondary];
        S.wizData.material = data.result.material;
        S.wizData.gender = data.result.gender;
        S.wizData.season = data.result.season;
        S.wizData.brand = data.result.brand;
        S.wizData.description_short = data.result.description_short;

        // Show category confirm row (Rule #38)
        showCategoryConfirm(data.result.category, data.result.category_confidence);

        // Update UI
        updateAIBlocks();
        renderAIFields();

        // Show banner
        document.getElementById('aiBanner').classList.add('show');
      }
    } catch (err) {
      // Закон №3: AI мълчи, PHP продължава
      toast('AI временно недостъпен. Попълни ръчно.');
    } finally {
      hideLoadingOverlay();
    }
  };
  reader.readAsDataURL(file);
}
```

### 6.6 AI Markup row (под полето Цена):

При cost_price > 0:
```javascript
async function checkAIMarkup() {
  if (!S.wizData.cost_price || S.wizData.cost_price <= 0) return;

  var resp = await fetch('/services/ai-markup.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      cost_price: S.wizData.cost_price,
      category_id: S.wizData.category_id,
      subcategory_id: S.wizData.subcategory_id,
      tenant_id: TENANT_ID
    })
  });
  var data = await resp.json();

  if (data.ok) {
    var markupRow = document.getElementById('aiMarkupRow');
    markupRow.querySelector('.aim-title b').textContent = '€' + data.retail_price.toFixed(2);
    markupRow.querySelector('.aim-formula span').textContent =
      `×${data.multiplier} + ${data.ending} · ${data.category_name} · confidence ${Math.round(data.confidence*100)}%`;
    markupRow.classList.add('show');

    // Confidence routing
    if (data.routing === 'auto') {
      // Auto-apply
      S.wizData.retail_price = data.retail_price;
      document.querySelector('#wizPriceField input').value = data.retail_price.toFixed(2);
      toast(`✓ €${data.retail_price.toFixed(2)} (×${data.multiplier} + ${data.ending})`);
    }
    // confirm / manual → user избира [Прие] или [Друга]
  }
}
```

### 6.7 Acceptance per sub-step:

**Step 1:** Top header + bulk banner работят (можеш да отвориш wizard, kp-pill click toggle-ва bulk режим).
**Step 2:** Section 1 (Снимка + Основно) рендерира с всички полета. Upload снимка показва preview. AI Vision call работи (виж network tab).
**Step 3:** Section 2 (Вариации) показва се при mode toggle "С вариации". Размери chips работят. Цветове AI-filled когато multi-photo.
**Step 4:** Section 3 (Допълнителни) — AI fallback полета се показват само без снимка.
**Step 5:** Section 4 (AI Studio) link работи.
**Step 6:** Save flow завършва успешно → нов продукт в DB на tenant_id=7.

### 6.8 Rollback:
```bash
# Backup tag преди всеки sub-step
git tag pre-phase3-step{N}-$(date +%Y%m%d-%H%M%S)
git push origin pre-phase3-step{N}-...

# При проблем
git reset --hard pre-phase3-step{N}-...
git push origin main --force  # ВНИМАНИЕ: иска Тих's "OK"
```

═══════════════════════════════════════════════════════════════
7. ФАЗА 4 — MULTI-PHOTO + MATRIX FULLSCREEN (4-6h)
═══════════════════════════════════════════════════════════════

### 7.1 Matrix fullscreen overlay:

**Route:** Tap на "Цял екран" в Section 2 matrix → `window.location.href = 'wizard_matrix.php'` (нов файл) или modal overlay.

**По-просто:** Embed `wizard_v6_matrix_fullscreen.html` content като modal в products.php.

```javascript
function openMatrixFullscreen() {
  var modal = document.createElement('div');
  modal.id = 'matrixFullscreenModal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:1000;background:var(--bg-main)';
  modal.innerHTML = /* copy от wizard_v6_matrix_fullscreen.html body */;
  document.body.appendChild(modal);
  initMatrixGrid();  // bind input handlers
}
```

### 7.2 Multi-photo flow:

**Toggle "Различни цветове"** в Section 1 photo zone → multi-photo capture mode:

1. Пешо натиска "Камера" → отваря native camera
2. Снима 1 цвят → JS callback с image data
3. Auto-call `services/ai-color-detect.php?multi=1` → връща recognized color
4. Thumbnail в photo grid с confidence badge
5. Repeat за следващи цветове
6. След 2+ снимки → Section 2 (Вариации) chips auto-populate

**ВНИМАНИЕ:** `services/ai-color-detect.php` е sacred — не пипа. Само нов JavaScript wrapper.

```javascript
async function captureMultiPhoto(file, photoIndex) {
  var formData = new FormData();
  formData.append('image', file);
  formData.append('multi', '1');
  formData.append('tenant_id', TENANT_ID);

  var resp = await fetch('/services/ai-color-detect.php?multi=1', {
    method: 'POST', body: formData
  });
  var data = await resp.json();

  if (data.ok) {
    // Add to photo grid
    var thumb = createPhotoThumb(file, data.color_name, data.confidence);
    document.getElementById('photoGrid').appendChild(thumb);

    // Add to S.wizData.colors
    S.wizData.colors = S.wizData.colors || [];
    S.wizData.colors.push({
      name: data.color_name,
      hex: data.color_hex,
      confidence: data.confidence,
      photo_url: URL.createObjectURL(file)
    });

    // If 2+ photos → populate Variations chips
    if (S.wizData.colors.length >= 2) {
      autoFillVariationColors();
      document.getElementById('multiPhotoHint').style.display = 'flex';
    }
  }
}
```

### 7.3 Bulk bg removal:

```javascript
async function bulkRemoveBackground() {
  var photos = S.wizData.colors.filter(c => !c.bg_removed);
  showLoadingOverlay(`Премахвам фон от ${photos.length} снимки...`);

  for (var photo of photos) {
    var resp = await fetch('/services/ai-bg-removal.php', {
      method: 'POST',
      body: new FormData(/* image_url */)
    });
    var data = await resp.json();
    if (data.ok) {
      photo.photo_url = data.processed_url;
      photo.bg_removed = true;
    }
  }

  hideLoadingOverlay();
  toast(`✓ Фон премахнат от ${photos.length} снимки (€${(photos.length * 0.05).toFixed(2)})`);
}
```

### 7.4 Acceptance:
- ✅ Matrix fullscreen overlay се отваря и затваря plynно
- ✅ Multi-photo capture: 3 снимки → 3 разпознати цвята → 3 chips в Вариации
- ✅ Bulk bg removal работи на 3 снимки наведнъж

═══════════════════════════════════════════════════════════════
8. ФАЗА 5 — INTEGRATION TESTING (tenant_id=7) (2-4h)
═══════════════════════════════════════════════════════════════

### 8.1 Test scenarios:

**T1 — Full single product flow:**
1. Open wizard → toggle "Единичен"
2. Upload снимка → verify AI Vision auto-fills fields
3. Confirm категория "ДА"
4. Verify пол/сезон/марка/описание попълнени с ✨ AI badges
5. Enter cost_price 12 → verify AI markup row → "AI предлага €27.99"
6. Tap [✓ Прие] → retail_price auto-populates
7. Enter quantity 5, min_quantity auto = 2
8. Save → verify product в DB

**T2 — Multi-photo variations:**
1. Toggle "С вариации"
2. Toggle "Различни цветове"
3. Снима 3 цвята (Бял, Розов, Черен)
4. Verify Section 2 chips ✨ auto-populated
5. Add размери S, M, L
6. Verify matrix 3×3 = 9 SKU
7. Tap [✓ Всички = 2] → 18 units total
8. Save → verify 9 SKU в DB

**T3 — No-photo manual entry:**
1. Open wizard, NE upload снимка
2. Verify AI fields НЕ са в Section 1
3. Open Section 3 (Допълнителни)
4. Verify AI fallback полета са там (Пол, Сезон, Марка, Описание)
5. Enter всичко ръчно
6. Save → verify в DB с правилни полета

**T4 — "Като предния" bulk mode:**
1. Save 1 product
2. Click "Като предния" pill в header
3. Verify bulk banner показва "Наследено от: [name]"
4. Verify полето Цена е disabled
5. Upload нова снимка → AI разпознава нови полета
6. Save → verify новият product има наследени cena/supplier/category но различни AI полета

**T5 — Voice STT:**
1. Click mic на полето "Цена"
2. Кажи "двадесет и осем лева"
3. Verify polето попълни с 28.00
4. Click mic на полето "Име"
5. Кажи "Дамски бикини Tommy Jeans"
6. Verify полето попълни текста

**T6 — Print test (DTM-5811):**
1. Save product
2. Click Print бутон в Save row
3. Verify бар код отпечата на DTM-5811

### 8.2 Acceptance:
- ✅ Всичките 6 теста минават
- ✅ Никакви console errors
- ✅ Voice STT работи правилно (sacred zone не е счупен)
- ✅ Color detection multi-photo работи (sacred zone не е счупен)

═══════════════════════════════════════════════════════════════
9. ROLLBACK PLAN (PER ФАЗА)
═══════════════════════════════════════════════════════════════

### Преди всяка фаза:
```bash
git tag pre-phase{N}-$(date +%Y%m%d-%H%M%S)
git push origin pre-phase{N}-...

# DB backup ако фазата прави schema промени
mysqldump runmystore products inventory ai_snapshots pricing_patterns > /tmp/db_pre_phase{N}.sql
```

### При проблем:
```bash
# Code rollback
git reset --hard pre-phase{N}-...
git push origin main --force  # САМО със Тих's "OK"

# DB rollback (ако schema е променена)
mysql runmystore < /tmp/db_pre_phase{N}.sql
```

═══════════════════════════════════════════════════════════════
10. STOP SIGNALS — НИКОГА БЕЗ "OK" ОТ ТИХ
═══════════════════════════════════════════════════════════════

1. ❌ `git push --force` (force push)
2. ❌ `rm -rf` на каквото и да е под `/var/www/runmystore/`
3. ❌ Промяна на sacred zone функции (виж секция 2)
4. ❌ `DROP TABLE`, `TRUNCATE`, `DELETE FROM ... WHERE 1=1`
5. ❌ Edit на `/etc/runmystore/db.env` или `api.env`
6. ❌ Промяна на `/etc/cron.d/`
7. ❌ Едновременен rewrite на 2+ sub-pages в 1 commit (Standing Rule #33)

═══════════════════════════════════════════════════════════════
11. COMMUNICATION PROTOCOL С ТИХ
═══════════════════════════════════════════════════════════════

**Кога ДЕЙСТВАШ САМ:**
- Python скриптове за file modification
- Git tags, commits, push (без force)
- Малки fix-ове (typo, semicolon, missing div)
- Technical decisions (method choice, library, sandbox vs production)

**Кога ИЗРИЧНО ПИТАШ:**
- Sacred zone докосване
- Destructive операции (rm, DROP, force-push)
- UX/продуктови решения
- Многочасови задачи (>2h непрекъсната работа)

**Tone:**
- Bulgarian, кратко, директно
- Никога "Готов ли си?" / "Може би" / "Опитай се"
- Caps от Тих = urgency → действай, не извинения
- 60% позитив + 40% критика, никога 100% ентусиазъм

═══════════════════════════════════════════════════════════════
12. SMART-READING — ИКОНОМИЯ НА КОНТЕКСТ
═══════════════════════════════════════════════════════════════

### За products.php (15530 реда):

```bash
# Sacred functions (виж секция 2.2)
python3 /tmp/gh.py products.php -r 7598:8047    # _wizDraft* helpers + openManualWizard

# Wizard rendering
python3 /tmp/gh.py products.php -r 8047:8321    # renderWizPage
python3 /tmp/gh.py products.php -r 8321:8896    # renderWizPagePart2
python3 /tmp/gh.py products.php -r 8896:9100    # renderStudioStep + helpers
python3 /tmp/gh.py products.php -r 9228:9500    # renderWizStep2
python3 /tmp/gh.py products.php -r 12391:12700  # renderWizPhotoStep
python3 /tmp/gh.py products.php -r 13369:13800  # renderLikePrevPageS88
python3 /tmp/gh.py products.php -r 14341:14600  # _wizMicWhisper
```

**Не четеш цели 15K реда.** Иначе изхабиш контекста и нямаш с какво да работиш.

═══════════════════════════════════════════════════════════════
13. PULL-FROM-MOCKUP CHECKLIST
═══════════════════════════════════════════════════════════════

Когато копираш от mockup → products.php, ползвай тази checklist:

### От `wizard_v6_INTERACTIVE.html`:
- [ ] Header HTML structure (← + title + KP pill + theme)
- [ ] Bulk mode banner HTML
- [ ] Search pill HTML + voice bar HTML
- [ ] Mode toggle HTML
- [ ] Photo mode toggle HTML (Section 1)
- [ ] Photo zone HTML (empty + has-photo + loading states)
- [ ] AI Vision banner HTML
- [ ] AI Markup row HTML
- [ ] Section 1 ОСНОВНИ полета (name, price, qty, min)
- [ ] Section 1 AI conditional полета (#aiCategoryBlock + #aiOtherBlock)
- [ ] Артикулен номер + Баркод HTML
- [ ] Section 2 (Variations) HTML
- [ ] Section 3 (Допълнителни) HTML
- [ ] Section 4 (AI Studio) HTML
- [ ] CSS за всички нови класове (.kp-pill, .bulk-banner, .ai-vision-banner, .ai-markup-row, .chip, .matrix-board, etc.)
- [ ] JS injectGlassSpans() injection
- [ ] JS updateAIBlocks() conditional
- [ ] JS toggleBulkMode()
- [ ] JS uploadPhoto() с AI Vision call
- [ ] JS checkAIMarkup() с AI Markup call

### От `wizard_v6_matrix_fullscreen.html`:
- [ ] mx-board + mx-grid HTML structure
- [ ] mx-cell стилове (has-qty, warn, zero)
- [ ] Auto-min toggle HTML + JS
- [ ] Quick actions (Всички = N, AI разпредели, Изчисти)

### От `wizard_v6_multi_photo_flow.html`:
- [ ] Frame 1 (capture) HTML — viewfinder, corner brackets, capture button
- [ ] Frame 2 (AI detect) HTML — spinner, scan-line, detect-list
- [ ] Frame 3 (result) HTML — result-photos grid, bulk-bg-cta
- [ ] JS captureMultiPhoto() с ai-color-detect call
- [ ] JS bulkRemoveBackground()

═══════════════════════════════════════════════════════════════
14. AI ENDPOINT JSON SCHEMAS — ПЪЛНИ
═══════════════════════════════════════════════════════════════

### 14.1 POST /services/ai-vision.php

**Request (multipart/form-data):**
```
image: <file>
tenant_id: 7
barcode: (optional)
```

**Response:**
```json
{
  "ok": true,
  "phash": "abcd1234efgh5678",
  "cache_hit": false,  // "barcode" | "phash" | false
  "result": {
    "category": "Бикини",
    "category_confidence": 0.94,
    "category_id": null,  // populated if match found in DB
    "subcategory": "Дамски бикини",
    "subcategory_confidence": 0.87,
    "subcategory_id": null,
    "color_primary": {
      "name": "Розов",
      "hex": "#ec4899",
      "confidence": 0.89
    },
    "color_secondary": [
      {"name": "Бял", "hex": "#ffffff", "confidence": 0.72}
    ],
    "material": "Памук с ластан",
    "material_confidence": 0.78,
    "gender": "female",  // "male" | "female" | "kid" | "unisex"
    "gender_confidence": 0.96,
    "season": "summer",  // "summer" | "winter" | "transition" | "year_round"
    "season_confidence": 0.85,
    "brand": "Tommy Jeans",
    "brand_confidence": 0.82,
    "description_short": "Дамски бикини от мек памук с ластан. Розов цвят с малки бели точки. Класически крой с ниска талия."
  },
  "tokens_used": 245,
  "cost_eur": 0.0015
}
```

**Error response:**
```json
{
  "ok": false,
  "error": "RATE_LIMIT" | "TIMEOUT" | "INVALID_IMAGE" | "GEMINI_DOWN",
  "fallback": "manual"  // hint за UI
}
```

### 14.2 POST /services/ai-markup.php

**Request (application/json):**
```json
{
  "cost_price": 12.00,
  "category_id": 47,
  "subcategory_id": 132,  // optional
  "tenant_id": 7
}
```

**Response:**
```json
{
  "ok": true,
  "retail_price": 27.99,
  "multiplier": 2.5,
  "ending": ".99",
  "category_name": "бельо",
  "subcategory_name": "дамски бикини",
  "confidence": 0.92,
  "sample_size": 47,
  "routing": "auto",  // "auto" (>0.85) | "confirm" (0.5-0.85) | "manual" (<0.5)
  "explanation": "На база на 47 продажби в категория 'бельо' през последните 30 дни"
}
```

═══════════════════════════════════════════════════════════════
15. ИНФРАСТРУКТУРА
═══════════════════════════════════════════════════════════════

- **Server:** root@164.90.217.120 (DigitalOcean Frankfurt)
- **Path:** /var/www/runmystore/
- **GitHub:** tiholenev-tech/runmystore (main branch)
- **DB:** MySQL 8 `runmystore`, creds в /etc/runmystore/db.env
- **API keys:** /etc/runmystore/api.env (GROQ_API_KEY, GEMINI_API_KEY)
- **Test tenant:** tenant_id=7 (Тих's пробен профил, wipe-able)
- **Mobile test:** Samsung Z Flip6 (~373px viewport)
- **Bluetooth printer:** DTM-5811 (TSPL, 50×30mm, MAC DC:0D:51:AC:51:D9)

═══════════════════════════════════════════════════════════════
16. ПОСЛЕДНИ ДУМИ
═══════════════════════════════════════════════════════════════

- **Beta е след 30 дни.** Време е критично, но не толкова че да рискуваме sacred zones.
- **Voice STT е 6 месеца работа на Тих.** Не пипа.
- **Color detection multi-photo е "perfect" според Тих.** Не пипа.
- **Малки commit-и, лесен rollback.** Не batch-ваш 5 неща в 1 commit.
- **Питай ако не си сигурен.** По-добре 30 секунди разговор отколкото час дебъг.

> **Beta success = wizard работи на tenant_id=7 + voice STT не е счупено + Bluetooth print работи + ENI клиент може да добави първите 50 продукта без помощ.**

═══════════════════════════════════════════════════════════════
**КРАЙ.**

> Когато си готов да започнеш Phase 1 → пиши на Тих: "Прочетох handoff-а, готов съм за Phase 1." Той ще каже "ОК" или "чакай".
