markdown

# 🎨 AI_STUDIO_LOGIC.md — RunMyStore.ai

**Версия:** 1.0 FINAL · **Дата:** 26.04.2026 · **Approved by:** Тихол
**Заменя:** PRODUCTS_DESIGN_LOGIC §7, обновява DOCUMENT_1_LOGIC §4 (планове) + BUSINESS_STRATEGY §14
**За:** Claude Code implementation на S82.STUDIO
**Mockups:** `ai-studio-main-v2.html` (standalone), `ai-studio-categories.html` (per-product modal)

---

## 0. TL;DR (10 принципа)

1. **3 операции:** 🖼 Бял фон / 📝 AI описание / ✨ AI магия (try-on + студийна)
2. **Bulk = САМО deterministic** (фон + описание). Магията НИКОГА не е bulk.
3. **5 категории:** 👕 Дрехи / 👙 Бельо · Бански / 💎 Бижута / 👜 Аксесоари / 📦 Друго
4. **2 режима в per-product модал:** Стандартно (1 click) / Настрой (опции)
5. **Quality Guarantee:** 1 paid + 2 free retries + refund (cost €0.075 на нас)
6. **3 типа кредити** в DB (bg / desc / magic) — отделни баланси
7. **Volume packs** имат 18-мес валидност (НЕ "не изтичат завинаги")
8. **AI описание стана платено** (€0.02) — преди беше безплатно
9. **nano-banana 2** (Gemini 3.1 Flash Image) — не nano-banana-pro
10. **Pre-flight Gemini Vision check** (€0.001) преди скъпите операции

---

## 1. БАЗОВИ ЦЕНИ И COSTS

| Операция | Модел (fal.ai) | Цена клиент | Cost нам | Margin | Скорост | Success | Bulk? |
|---|---|---|---|---|---|---|---|
| 🖼 Бял фон | birefnet/v2 | **€0.05** | €0.015 | 70% | 5-15s | >95% | ✅ ДА |
| 📝 AI описание | Gemini 2.5 Flash | **€0.02** | €0.005 | 75% | 3s | >98% | ✅ ДА |
| ✨ AI магия (try-on) | nano-banana 2 | **€0.30** | €0.075 | 75% | 10-20s | 70-80% | ❌ НЕ |
| 💎 Студийна снимка | nano-banana 2 | **€0.30** | €0.075 | 75% | 10-20s | 70-85% | ❌ НЕ |
| 🔍 Pre-flight check | Gemini Vision | (вътрешно) | €0.001 | — | 1s | — | — |

### Защо AI описание стана платено
- Gemini 2.5 Flash cost €0.005-€0.008 на BG/RO описание (cyrillic = 2-3× повече tokens)
- При 500 клиента × 100 описания/мес = €250-€400/мес чиста загуба
- Решение: €0.02 → 75% margin + 100 включени в START → малки магазини **не плащат extra**

### Защо nano-banana 2 (не -pro)
- Cost €0.075 vs €0.14 (-46%)
- 4× по-бърз
- С правилни prompts — "killer" качество (тествано)
- Switch from nano-banana-pro → nano-banana 2 е финално решение

---

## 2. ПЕТТЕ КАТЕГОРИИ (UI structure)

```php
$AI_CATEGORIES = [
  'clothes' => [
    'label_bg' => 'Дрехи',
    'emoji' => '👕',
    'hue_class' => 'clothes', // CSS .clothes (indigo)
    'subtypes' => [
      'tshirt' => 'Тениска',
      'dress' => 'Рокля',
      'jeans' => 'Дънки',
      'jacket' => 'Сако',
      'shirt' => 'Риза',
      'sweater' => 'Пуловер',
      'shorts' => 'Шорти',
      'socks' => 'Чорапи',
      'other' => 'Друго'
    ],
    'options' => ['pose', 'cropping', 'background', 'voice'],
    'free_prompt' => false
  ],
  'lingerie' => [
    'label_bg' => 'Бельо и бански',
    'emoji' => '👙',
    'hue_class' => 'lingerie', // pink
    'subtypes' => [
      'bikini' => 'Бикини',
      'swimsuit' => 'Цял бански',
      'bra' => 'Сутиен',
      'thong' => 'Прашка',
      'corset' => 'Корсет',
      'bodysuit' => 'Боди'
    ],
    'options' => ['pose', 'cropping', 'background', 'voice'],
    'free_prompt' => false,
    'has_proportion_lock' => true // CRITICAL — виж §8
  ],
  'jewelry' => [
    'label_bg' => 'Бижута',
    'emoji' => '💎',
    'hue_class' => 'jewelry', // amber/gold
    'subtypes' => [
      'ring' => 'Пръстен',
      'necklace' => 'Гердан',
      'earrings' => 'Обеци',
      'watch' => 'Часовник',
      'bracelet' => 'Гривна',
      'brooch' => 'Брошка'
    ],
    'options' => ['surface_preset', 'voice'], // 8 пресета: ръка/мрамор/дърво/кадифе/цветя/floating/обувка/чанта
    'free_prompt' => false
  ],
  'acc' => [
    'label_bg' => 'Аксесоари',
    'emoji' => '👜',
    'hue_class' => 'acc', // teal
    'subtypes' => [
      'shoes' => 'Обувки',
      'bag' => 'Чанта',
      'hat' => 'Шапка',
      'glasses' => 'Очила',
      'belt' => 'Колан',
      'scarf' => 'Шалче'
    ],
    'options' => ['view_angle', 'on_model', 'voice'],
    'free_prompt' => false
  ],
  'other' => [
    'label_bg' => 'Друго / Предмет',
    'emoji' => '📦',
    'hue_class' => 'other', // purple
    'subtypes' => [], // EMPTY — Пешо описва свободно
    'options' => ['voice', 'background'],
    'free_prompt' => true,
    'free_prompt_examples' => [
      'Бутилка вино върху бяла мраморна повърхност, естествена светлина',
      'Кутия шоколадови бонбони отворена, с разпръснати бонбони наоколо',
      'Играчка плюшено мече седнало на дървена пейка'
    ]
  ]
];
```

**Auto-detect категория:** от `products.category_id` mapping → auto-select. Ако не намери match → 'other'.

**DB колони (нови в `products`):**
```sql
ALTER TABLE products
  ADD COLUMN ai_category VARCHAR(20) NULL DEFAULT NULL,
  ADD COLUMN ai_subtype VARCHAR(30) NULL DEFAULT NULL;
```

---

## 3. ДВА РЕЖИМА В PER-PRODUCT MODAL

### A) Стандартно (default)
- НЯМА избор на нищо
- 1 бутон: **"Генерирай €0.30"**
- Auto-detect категория от `products.ai_category`
- Built-in prompt template (тествани) — виж §8
- Default настройки от `/settings/ai-defaults.php`
- **Use case:** Бързи генерации без размишление

### B) Настрой (Configure)
Toggle-ва опции:
1. **Категория** chips (5 опции)
2. **Подтип** chips (зависи от категория)
3. **Поза модел** chips (4 опции — за clothes/lingerie)
4. **Кадрировка** chips (4 опции — за clothes/lingerie)
5. **Фон** chips (4 опции — за всичко)
6. **Voice добавка** (optional)
7. Бутон: **"Генерирай по моите настройки €0.30"**

### Категория "Друго" (📦)
- НЕ показва: подкатегории, поза, кадрировка
- Показва: textarea + voice бутон + 3 примера
- Free-form prompt директно в `prompt_user_addition`

---

## 4. QUALITY GUARANTEE (CRITICAL)

### Политика "3-нивов retry с refund"

```
Generation 1 (paid €0.30, attempt #1)
  ↓ Show preview
3 buttons:
  ✅ Запази          → status='completed_paid'
  ↻ Опитай пак       → FREE retry (max 2), parent_log_id linked
  ❌ Откажи и върни  → status='refunded_loss', credit restored

After attempt #3 (last):
  Only [✅ Запази] [❌ Refund] (без "Опитай пак")
```

### Икономика (на 100 генерации)

| Сценарий | % | Cost | Revenue | Margin |
|---|---|---|---|---|
| Accept 1-ви опит | 70 | €0.075 | €0.30 | 75% |
| Accept след 1 retry | 20 | €0.150 | €0.30 | 50% |
| Accept след 2 retries | 8 | €0.225 | €0.30 | 25% |
| **Refund (загуба)** | **2** | €0.075 | €0 | **-100%** |

**Weighted effective margin: ~60%** (от номинален 75%)

### Anti-abuse mechanisms

**1. Hidden retry counter (soft warning):**
```php
$retry_rate_24h = (
  SUM(retry_free) / NULLIF(SUM(completed_paid), 0)
) FROM ai_spend_log WHERE tenant_id=? AND created_at > NOW() - INTERVAL 24 HOUR;

IF rate > 0.6 → show "Имаш необичайно много retry-и. Имаш ли проблем с качеството?"
```

**2. Hard daily cap:**
```php
IF retries_today >= 30 → block free retry, force €0.30 charge
```

**3. Pre-flight Gemini Vision (€0.001):**
```json
{
  "is_dark": bool,
  "is_blurry": bool,
  "is_too_small": bool,
  "subject_detected": "clothing|jewelry|accessory|object|none",
  "warning": "text or empty"
}
```
→ Ако е лоша → warning ПРЕДИ €0.30 charge ("Снимката е тъмна. Препоръчваме нова.") НЕ блокирай — Пешо избира.

### DB schema (Quality Guarantee)
```sql
ALTER TABLE ai_spend_log
  MODIFY COLUMN status ENUM(
    'completed_paid',   -- accept, кредит отчетен
    'retry_free',       -- безплатен retry, cost on us
    'refunded_loss'     -- refund, загуба за нас
  ) NOT NULL DEFAULT 'completed_paid',
  ADD COLUMN parent_log_id INT NULL,
  ADD COLUMN attempt_number INT NOT NULL DEFAULT 1,
  ADD INDEX (tenant_id, status, created_at),
  ADD INDEX (parent_log_id);
```

### Refund logic
```php
function refund_credit($log_id) {
  DB::run("UPDATE ai_spend_log SET status='refunded_loss' WHERE id=?", [$log_id]);
  DB::run("UPDATE ai_credits_balance SET magic_credits = magic_credits + 1 WHERE tenant_id=?", [$tenant_id]);
  // log loss for monitoring
}
```

### При safety block (Layer 2 server-side)
- Auto-refund credit (никога не charge при block)
- Log за monitoring
- Message: "AI блокира тази снимка. Кредитът е върнат."

---

## 5. ТРИ ТИПА КРЕДИТИ + ПЛАНОВЕ

### Включено в плановете (per month)

| План | Месечно | bg | desc | magic | Стойност |
|---|---|---|---|---|---|
| FREE | €0 | 0 | 0 | 0 | €0 |
| START | €19 | 50 | 100 | 10 | €7.50 |
| PRO | €59 | 300 | 500 | 30 | €34.00 |
| BIZ | €109 | 1,000 | 1,500 | 80 | €104.00 |

### DB schema
```sql
-- Раздели credits на 3 типа
ALTER TABLE ai_credits_balance
  ADD COLUMN bg_credits INT DEFAULT 0,
  ADD COLUMN desc_credits INT DEFAULT 0,
  ADD COLUMN magic_credits INT DEFAULT 0;
-- DROP старата унифицирана `credits` колона (ако съществува)

-- Plan limits в tenants
ALTER TABLE tenants
  ADD COLUMN included_bg_per_month INT DEFAULT 0,
  ADD COLUMN included_desc_per_month INT DEFAULT 0,
  ADD COLUMN included_magic_per_month INT DEFAULT 0,
  ADD COLUMN bg_used_this_month INT DEFAULT 0,
  ADD COLUMN desc_used_this_month INT DEFAULT 0,
  ADD COLUMN magic_used_this_month INT DEFAULT 0;
```

### Cron monthly reset (1-во число)
```php
// /cron-monthly.php
DB::run("UPDATE tenants SET 
  bg_used_this_month=0, 
  desc_used_this_month=0, 
  magic_used_this_month=0
");
```

### Logic на consumption (priority)
1. Първо: included monthly (`bg_used_this_month < included_bg_per_month`)
2. След това: bought credits (`bg_credits > 0` от ai_credits_balance)
3. Ако няма ни едното — block + show "Купи още"

### Migration (30 days grace period)
START/PRO/BIZ клиенти от преди миграцията:
- Описанието остава **безплатно за 30 дни**
- AI магия по старата цена (€0.40-0.50) за 30 дни
- След това — новите цени
- Notification на ден 25: "Цените се променят. Виж новата система."

---

## 6. VOLUME PACKS

### Цени и съдържание

| Пакет | Цена | bg | desc | magic | Спестяване |
|---|---|---|---|---|---|
| Mini | €5 | 100 | 250 | 16 | 0% (база) |
| Standard | €15 | 350 | 870 | 58 | -14% |
| Plus | €30 | 800 | 2,000 | 130 | -25% |
| Pro | €50 | 1,500 | 3,750 | 250 | -33% |
| Max | €100 | 3,500 | 8,750 | 600 | -43% |

### Per-unit цени във всеки пакет

| Пакет | bg/бр | desc/бр | magic/бр |
|---|---|---|---|
| Mini | €0.050 | €0.020 | €0.300 |
| Standard | €0.043 | €0.017 | €0.258 |
| Plus | €0.038 | €0.015 | €0.225 |
| Pro | €0.033 | €0.013 | €0.200 |
| **Max** | **€0.029** | **€0.011** | **€0.166** |

### Margin при пакетите

| Пакет | bg margin | magic margin |
|---|---|---|
| Mini | 70% | 75% |
| Standard | 65% | 71% |
| Plus | 61% | 67% |
| Pro | 55% | 62% |
| **Max** | **48%** | **55%** |

→ Дори на най-голям margin остава **здравословен 48-55%** (lock-in компенсира).

### Валидност
- **18 месеца** от датата на покупка (НЕ "не изтичат завинаги")
- Ако клиент спре абонамента 6+ месеца → кредитите се **замразяват** (frozen state)
- При възстановяване на абонамент → unfreeze

### Логика на покупка
```
1. Tap "Купи още кредити"
2. Modal с 5 пакета (Mini → Max)
3. Избор на пакет
4. Избор на ТИП кредит (1 пакет = 1 тип)
5. Stripe Connect → checkout
6. Webhook → INCREMENT в ai_credits_balance.{bg|desc|magic}_credits
```

### Real примери

**Сценарий 1 — PRO активен месец:**
- Включено: 300 фон + 500 описания + 30 магия (€59)
- Изхарчил: 280/420/35 → магия превишена с 5
- Купува Mini €5 = 16 магия → има 11 за следващия месец
- Общо: **€64/месец**

**Сценарий 2 — BIZ multi-store:**
- Включено: 1000/1500/80 (€109)
- Изхарчил: 850/1200/100 → магия превишена с 20
- Купува Standard €15 = 58 магия → има 38 за следващ
- Общо: **€124/месец**

**Сценарий 3 — Тих месец PRO:**
- Изхарчил: 50/80/5 → всички в плана
- Общо: **€59 без доплащане**

---

## 7. BULK vs QUEUE LOGIC

### ✅ MAY bulk (deterministic)

| Операция | Защо може bulk |
|---|---|
| Бял фон (birefnet) | Deterministic · €0.05/бр · 5-15s · success >95% |
| AI описание (Gemini Flash) | Deterministic · €0.02/бр · 3s · success >98% |

### ❌ НЕ bulk (probabilistic)

| Операция | Защо НЕ |
|---|---|
| AI магия (try-on) | Probabilistic · €0.30/бр · 20-30% miss · PROPORTION LOCK изисква ръчно одобрение · различен модел per продукт |
| Студийна снимка | Probabilistic · различни presets per продукт · ръчно одобрение |

### Bulk implementation
```php
function bulk_remove_bg($tenant_id, $product_ids) {
  $count = count($product_ids);
  $cost = $count * 0.05;
  
  // Confirm modal: "Ще обработя 47 продукта за €2.35. Продължи?"
  if (!user_confirmed) return;
  
  // List на продукти за preview ("Mustang 32, Nike L, Adidas 42 + 44 още")
  
  foreach ($product_ids as $pid) {
    bg_remove_job::dispatch($tenant_id, $pid);
  }
}
```

### Standalone AI Studio логика
- Bulk бутони показват се **САМО** ако count > 0
- Bulk преди final action → confirm modal с list на products
- Категории винаги показват се (всички 5) дори при count=0
- Tap на категория → отваря **fullscreen overlay** с queue list:

```html

  👙 Бельо · 8 продукта чакат
  
    
      
      Дамски бански Passionata
      PSN-2024 · €34.90
    
    
  

```

---

## 8. PROMPT TEMPLATE SYSTEM

### DB schema
```sql
CREATE TABLE ai_prompt_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(20) NOT NULL,
  subtype VARCHAR(30) NULL,
  template TEXT NOT NULL,
  success_rate DECIMAL(5,2) DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  is_ab_test TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (category, is_active)
);
```

### Lingerie template (APPROVED — 90% success rate)

```
STUDIO PHOTO. White background.
Model torso only. Model slightly turned to the right.
DO NOT CHANGE THE GARMENT.
LOCK THE SIDE WIDTH EXACTLY as in the reference product photo.
KEEP THE ORIGINAL PATTERN SIZE, ORIGINAL CUT, ORIGINAL HIP WIDTH.
DO NOT widen hips. Do NOT stretch sides. Do NOT adapt product to the body.
The product controls the shape. The body must adapt to the product.
Match the exact proportions from the flat product photo.
STRICT PROPORTION LOCK. 1:1 garment width replication.
Maintain EXACT horizontal tightness — NO lateral expansion allowed.
```

**Защо работи:**
- Започва с "STUDIO PHOTO" → signal product photography (не glamour)
- "Model torso only" → technical not glamour
- CAPS за restrictions → AI ги взима seriously
- "1:1 replication" → technical language
- БЕЗ trigger думи (sexy/seductive/provocative)

### Защо НЕ Gemini app (4 аргумента за Пешо)

**1. Workflow integration (време = пари):**
- Gemini app: 5-8 min/продукт (switch app → upload → prompt → wait → copy → switch back → upload в RMS)
- RMS AI Studio: **30 sec** (tap → избери → генерирай → save)
- При 50 продукта/мес × €6/час = **€20-€40 време загубено** vs €15 в RMS

**2. Prompt expertise:**
- Без RMS: trial-and-error (50% успех)
- С RMS: тествани templates (89-90% успех) с PROPORTION LOCK
- Бельо: Gemini app блокира 60% → RMS API режим (BLOCK_NONE) минава 80%+

**3. API режим vs Consumer app:**
- Consumer Gemini: 20/ден лимит, Layer 1 safety винаги ON
- RMS (API): без лимит, BLOCK_NONE configurable, до 4K resolution

**4. Quality Guarantee + Lock-in:**
- Refund при лош резултат (consumer app — твой проблем)
- История на всички версии
- Auto-save в каталога (consumer — manual upload)
- Bulk операции (47 продукта без фон → 1 натисни)

### Templates за останалите 4 категории
**TODO за Claude Code/Тихол:** Създай default templates базирани на:
- Принципите от lingerie template (CAPS LOCK, "DO NOT CHANGE", "PROPORTION LOCK")
- Технически framing ("STUDIO PHOTO", "white background", "e-commerce catalog")
- Без trigger думи

### Rendering
```php
function build_prompt($product, $category, $options = []) {
  $template = get_active_template($category);
  
  $prompt = str_replace([
    '{garment_type}', '{model_gender}', '{pose}', '{cropping}', '{background}'
  ], [
    $options['subtype'] ?? '',
    $options['gender'] ?? 'female',
    $options['pose'] ?? 'slightly turned to the right',
    $options['cropping'] ?? 'torso only',
    $options['background'] ?? 'white studio'
  ], $template);
  
  if (!empty($options['voice_text'])) {
    $prompt .= "\n\nAdditional: " . $options['voice_text'];
  }
  
  return $prompt;
}
```

### A/B testing capability
- `is_ab_test=1` → split traffic между templates
- Track `success_rate` per template (от accepted vs refunded ratio)
- Admin panel за template management (по-късно)

---

## 9. STANDALONE PAGE LOGIC (`ai-studio.php`)

### Page structure (от mockup `ai-studio-main-v2.html`)

1. **Top bar** — back + title "AI Studio" + PRO pill + printer pill
2. **Hero banner** glass card (text/branding)
3. **Credits bar** (3 типа)
4. **Bulk секция** (deterministic actions)
5. **Категории** (5 cards)
6. **История** (last 8 generated, scrollable thumbnails)
7. **Settings rows** (3 settings)
8. **FAB** "Питай AI" долу

### Page query (PHP)

```php
// Count products needing each operation per category
$counts = DB::run("
  SELECT 
    ai_category,
    SUM(CASE WHEN image_url IS NULL OR image_url = '' THEN 1 ELSE 0 END) AS need_bg,
    SUM(CASE WHEN ai_description IS NULL OR ai_description = '' THEN 1 ELSE 0 END) AS need_desc,
    SUM(CASE WHEN ai_magic_image IS NULL OR ai_magic_image = '' THEN 1 ELSE 0 END) AS need_magic
  FROM products
  WHERE tenant_id = ?
  GROUP BY ai_category
", [$tenant_id])->fetchAll();
```

### Credits bar load
```php
$balance = DB::run("SELECT bg_credits, desc_credits, magic_credits FROM ai_credits_balance WHERE tenant_id=?", [$tid])->fetch();
$tenant = DB::run("SELECT included_bg_per_month, bg_used_this_month, ... FROM tenants WHERE id=?", [$tid])->fetch();

$bg_remaining = $balance['bg_credits'] + ($tenant['included_bg_per_month'] - $tenant['bg_used_this_month']);
// repeat for desc, magic
```

### Bulk logic
- Show button само ако count > 0
- Format: "47 продукта без бял фон → Махни всички €2.35"
- Tap → confirm modal с list на products → batch process

### Категория cards
- Show 5 cards винаги (всички категории)
- Show "X чакат" count на всяка
- Tap → fullscreen overlay с queue list

### История section
```sql
SELECT * FROM ai_spend_log 
WHERE tenant_id = ? 
  AND status = 'completed_paid' 
  AND output_image_url IS NOT NULL
ORDER BY created_at DESC 
LIMIT 8;
```

---

## 10. PER-PRODUCT MODAL (`products.php` wizard step 5)

### Replace текущия модал в products.php

Mockup: `ai-studio-categories.html`

### Structure
1. Modal header: back + title + PRO pill + print pill + draft pill
2. Wiz steps progress (4 done + 1 active = step 5/5)
3. Wiz label: "Стъпка 5 от 5 · AI Studio · преди запазване"
4. Product preview pill (име + код + цена + размери)
5. Credits bar (compact, 3 types)
6. **Image compare** (Оригинал / След генериране)
7. **Бързи действия** card (deterministic):
   - 🖼 Махни фон €0.05
   - 📝 AI описание €0.02
8. **AI магия** card с **Стандартно/Настрой toggle**
9. AI описание quick row (€0.02)
10. Save / Skip buttons

### Save vs Skip
- **Save** ("Готово · Запази артикула"): UPDATE с AI generated данни
- **Skip** ("Без AI · запази"): UPDATE без AI данни (Пешо може да добави по-късно)
- И двата → wizard приключва, отива към products list

---

## 11. API ENDPOINTS

### Single try-on / studio
```
POST /ai-image-processor.php
Body:
  type=tryon | studio
  product_id=123
  category=lingerie
  subtype=bikini
  options={pose, cropping, background, voice_text}
  mode=default | configure
  
Response:
  {
    log_id: 456,
    generated_image_url: "https://...",
    attempt_number: 1,
    retries_remaining: 2,
    status: 'completed_paid'
  }
```

### Retry (free)
```
POST /ai-image-processor.php
Body:
  type=retry
  parent_log_id=456
  
Response:
  {
    log_id: 457,  // new log entry
    generated_image_url: "...",
    attempt_number: 2,
    retries_remaining: 1
  }
```

### Refund
```
POST /ai-image-processor.php
Body:
  type=refund
  log_id=456
  
Response:
  {
    status: 'refunded_loss',
    credits_restored: 1
  }
```

### Bulk operations
```
POST /ai-image-processor.php
Body:
  type=bulk_bg | bulk_desc
  product_ids=[1, 2, 3, ...]
  
Response:
  {
    job_id: "abc123",
    eta_seconds: 60,
    total_cost: 2.35
  }
```

### Buy credits
```
POST /ai-credits-buy.php
Body:
  pack_id=plus_30
  credit_type=magic | bg | desc
  
Response:
  {
    stripe_checkout_url: "https://checkout.stripe.com/..."
  }
```

### Webhook (Stripe → credits)
```
POST /webhooks/stripe-credits.php
On checkout.session.completed:
  → INCREMENT ai_credits_balance.{type}_credits
  → INSERT ai_credits_purchases (audit log)
```

---

## 12. ERROR HANDLING

```php
try {
  $result = fal_request('fal-ai/nano-banana-2/edit', $params);
} catch (RateLimitException $e) {
  return ['error' => 'rate_limit', 'message' => 'Опитай след минута'];
} catch (SafetyBlockException $e) {
  // Layer 2 server-side block
  log_safety_block($product_id, $params);
  refund_credit($log_id); // automatic refund
  return ['error' => 'content_blocked', 'message' => 'AI блокира тази снимка. Кредитът е върнат.'];
} catch (Exception $e) {
  return ['error' => 'generic', 'message' => 'Опитай отново'];
}
```

### Error states по UI

| Грешка | UI message | Action |
|---|---|---|
| rate_limit | "Опитай след минута" | Retry button |
| content_blocked | "AI блокира снимката. Кредитът е върнат." | Show original |
| generic | "Опитай отново" | Retry button |
| network | "Няма връзка" | Retry button |
| credit_exhausted | "Свърши кредит. Купи още." | Show pack modal |

---

## 13. RISKS + MITIGATIONS

### R1: Конкуренти безплатно описание
**Mitigation:** START включва 100 desc/мес → малки магазини **никога не плащат**. Конкуренти "100% free" не работят след 100/мес.

### R2: Volume packs cash dump
**Mitigation:** 18-мес валидност (не вечно). Ако спре abonнamenta 6+ мес → frozen.

### R3: Quality Guarantee abuse
**Mitigation:** Hidden retry counter + hard limit 30/ден + pre-flight Gemini Vision check.

### R4: Margin пада на Max пакет
**Reality:** Max magic margin = 55% (от 75%).
**Защо OK:** Volume = по-малко support cost, lock-in е по-силен (€100 изхарчени = няма да си отиде).

### R5: AI описание migration shock
**Mitigation:** 30-дневен grace period + push notification на ден 25 + email + in-app banner.

---

## 14. CRITICAL RULES (NON-NEGOTIABLE)

❌ **НИКОГА** "Gemini" в UI (винаги "AI")
❌ **НИКОГА** hardcode-вай Bulgarian (използвай i18n `tenant.lang`)
❌ **НИКОГА** $pdo директно (DB::run() винаги)
❌ **НИКОГА** `git add -A` (specific paths only)
❌ **НИКОГА** override config.php
✅ Approve UX/flow преди код
✅ Test на tenant_id=7 преди commit
✅ Mobile-first (480px max-width)
✅ Voice/photo/tap only (Закон №1)
✅ `continuous=false` за SpeechRecognition (BG)
✅ `innerText` не `innerHTML` за transcripts
✅ Currency: `priceFormat($amount, $tenant)` (dual € + лв до 8.8.2026)

---

## 15. DELIVERABLES (за Claude Code)

```
NEW FILES:
  /var/www/runmystore/ai-studio.php
  /var/www/runmystore/partials/ai-studio-modal.php (shared между products.php и ai-studio.php)
  /var/www/runmystore/migrations/20260427_001_ai_studio_schema.sql

UPDATED FILES:
  /var/www/runmystore/products.php (replace wizard step 5 with new modal)
  /var/www/runmystore/ai-image-processor.php (add Quality Guarantee + retry/refund + new pricing)
  /var/www/runmystore/cron-monthly.php (reset used_this_month counters)

NEW DOCS to update:
  PRODUCTS_DESIGN_LOGIC.md §7 → "AI Studio — виж AI_STUDIO_LOGIC.md"
  DOCUMENT_1_LOGIC §4 (планове) → нови лимити (50/100/10, 300/500/30, 1000/1500/80)
  BUSINESS_STRATEGY_v2 §14 → Volume packs €5-€100 + 3 типа кредити
  BIBLE_v3_0_TECH §6 (AI Studio) → нови цени + Quality Guarantee + 5 категории
```

### Git workflow
```bash
cd /var/www/runmystore && git pull origin main

# Work in /tmp/s82_staging_$(date +%Y%m%d_%H%M)/
# Diff against /var/www/runmystore/
# cp confirmed files

git add ai-studio.php partials/ai-studio-modal.php migrations/20260427_001_ai_studio_schema.sql
git commit -m "S82.STUDIO: new ai-studio.php + per-product modal v2

- 5 categories (clothes/lingerie/jewelry/acc/other)
- Toggle Стандартно/Настрой modes
- Quality Guarantee: 2 free retries + refund
- New pricing: 0.05/0.02/0.30
- Volume packs 5-100 EUR
- Bulk bg + desc operations
- Anti-abuse retry counter
- Pre-flight Gemini Vision check"

git add products.php
git commit -m "S82.STUDIO: products.php wizard step 5 replaced"

git add ai-image-processor.php cron-monthly.php
git commit -m "S82.STUDIO: API + cron for new credit model"

git push origin main
```

---

## 16. OPEN QUESTIONS (за Тихол преди final deploy)

1. **Default prompts** за категории Дрехи/Бижута/Аксесоари (има само за Бельо)?
2. **API alternative** — пробваме FASHN.ai за try-on, или само nano-banana 2?
3. **Stripe Connect нови packs** (€30/€50/€100) — кога се setup-ват?
4. **Migration grace period** — 30 дни ОК или повече?
5. **Frozen credits logic** — какъв exact UX при unfreeze?

---

## 17. TESTING CHECKLIST

**Manual tests с tenant_id=7:**

### Standalone main page
- [ ] Load `/ai-studio.php` без error
- [ ] Кредити bar показва правилни числа (3 типа)
- [ ] Bulk бутон фон работи (mock 1 продукт)
- [ ] Bulk описание работи (mock 1 продукт)
- [ ] Tap на категория → отваря queue list
- [ ] Tap на продукт в queue → отваря модала
- [ ] FAB работи

### Per-product модал
- [ ] Toggle Стандартно/Настрой работи
- [ ] Стандартно режим — 1 click → generate (mock)
- [ ] Настрой режим — всички 5 категории selectable
- [ ] Подкатегории се променят със смяна на категория
- [ ] Категория "Друго" показва voice + примери (НЕ подкатегории)
- [ ] Voice бутон recording работи

### Quality Guarantee
- [ ] Generate → 3 бутона visible
- [ ] "Опитай пак" не charge-ва credit
- [ ] След 3 опита → "Опитай пак" disappears
- [ ] Refund възстановява credit
- [ ] Check ai_spend_log има parent_log_id chain

### Anti-abuse
- [ ] При >60% retry rate → soft warning
- [ ] При 30 retries/day → hard limit kicks in

### Mobile
- [ ] Test на iPhone size (375px)
- [ ] Test на small Android (360px)
- [ ] Voice input на Chrome Android

---

**КРАЙ НА ДОКУМЕНТА**

*Source-ове: AI_CREDITS_PRICING_v3_FINAL.md + S82_STUDIO_CLAUDE_CODE_PROMPT.md (3× прочетени по протокола)*
*Mockup files: `ai-studio-main-v2.html` + `ai-studio-categories.html`*
Compacting our conversation so we can keep chatting...
in progress
82%




