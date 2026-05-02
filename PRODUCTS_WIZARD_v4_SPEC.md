# 🪄 PRODUCTS_WIZARD_v4_SPEC.md

**Версия:** v4 (final)
**Дата:** 02.05.2026
**Статус:** Approved by Тихол — ready for Code Code implementation
**Replaces:** старата 8-стъпкова wizard логика в products.php

---

## 0. PHILOSOPHY (KEEP THIS IN MIND)

**Принципът:** ЕДИН wizard, ЕДНА логика, разликата е "докъде стигна". Не два различни режима (Simple vs Detailed). Пешо натиска ЗАПАЗИ след стъпка 1. Митко минава всичките 3 стъпки. Same code, different speed.

**Реални use cases (приоритизирани):**

1. **Mass entry на партида** (60% от time spent): Митко получава доставка от Marina, 5 модела бикини × 5 цвята × 3 размера = 75 артикула. Първият = full wizard (60 секунди). Артикули 2-25 = "📋 Като предния" → 8 секунди всеки.

2. **Quick voice add** (25%): Пешо на касата, клиент иска нещо което няма в каталога. "Червени маратонки 50 лева Adidas" → 10 секунди → ЗАПАЗИ → продължава с продажбата.

3. **Template из стар каталог** (10%): Митко въвежда нов модел който прилича на стар. "🔍 Търси" → "Marina бикини черни" → избира template → попълва само разликите.

4. **Hands-free flow** (5%): Пешо с пълни ръце (държи стока). Voice-only от микрофон до ЗАПАЗИ, без tap.

**АНТИ-PATTERNS (избягвай):**
- ❌ Native клавиатурата НЕ изскача никога (Закон №1: Пешо НЕ ПИШЕ)
- ❌ Не питаме "Simple или Detailed?" (UI binarity = friction)
- ❌ Не блокираме save без задължителни 16 полета (confidence_score позволява partial save)
- ❌ Не показваме повече от 6 полета на screen едновременно (Z Flip6 viewport ограничение)

---

## 1. WIZARD STRUCTURE — 3 СТЪПКИ

### Стъпка 1: ИДЕНТИФИКАЦИЯ + КОДОВЕ
**Цел:** артикулът става **inventory-ready** (може да продаваш).

**Header:**
```
[✕] Нов артикул        [🔍 Търси] [📋 Като предния ⌄]
```

**Полета (по ред):**
1. 📷 **Снимка** (по избор) — голяма snimka в горната част, "+" placeholder ако празна. Hint текст: *"Без снимка? AI ще познае вариациите от категорията."*
2. 🎤 **Име** *(задължително)* — Web Speech voice + textbox display
3. 💰 **Цена на дребно** *(задължително)* — Whisper voice + custom numpad display
4. 🏪 **Доставчик** (по избор) — Web Speech voice + autocomplete dropdown от съществуващи доставчици
5. 📂 **Категория** (по избор) — auto-filter по supplier.categories, voice или tap
6. 📁 **Подкатегория** (по избор) — auto-filter по category.subcategories, voice или tap
7. 🏷️ **Баркод** (по избор) — Whisper voice + camera scan icon. Auto-gen EAN-13 ако празно при ЗАПАЗИ.
8. # **Артикулен номер** (по избор) — Whisper voice. Auto-gen tenant-specific SKU ако празно при ЗАПАЗИ.

**Бутони (footer):**
```
[ЗАПАЗИ] [🖨 ПЕЧАТАЙ] [Напред →]
```

**ЗАПАЗИ behavior:** Save & exit wizard. Артикулът активен с `confidence_score = 40%`. Може да се продава веднага. Може да се редактира по-късно от life-board nudge ("8 без вариации → ").

**🖨 ПЕЧАТАЙ behavior:** Save automatically (без exit) + send to printer-setup.php → DTM-5811 печата 50×30mm етикет (име + баркод + цена + дата). След печат → остава на стъпка 1 за следващ артикул.

**Напред → behavior:** Save automatically + go to стъпка 2.

---

### Стъпка 2: ВАРИАЦИИ + БРОЙКИ + ЗОНА
**Цел:** колко имаш и в какъв вид.

**Conditional rendering:**
```
IF category.has_variations = false (или НЕ е попълнена категория):
    Показва само: 1 поле "бройка" (Whisper) + zone (Web Speech)
    
IF category.has_variations = true:
    Показва: цветове chips → размери chips → matrix → zone
```

**Single product mode (no variations):**
```
Бройка: [____] (Whisper voice или custom numpad)
Зона в магазина: [_______] (Web Speech, по избор)
```

**Variations mode:**
```
Цветове: [+] chip-and-add UI
   Voice: "черно бяло синьо" → 3 chip-а създадени
   Manual: tap "+" → отваря color picker (12 базови)
   Saved автоматично в product.variations.color

Размери: [+] chip-and-add UI
   Voice: "S M L XL" → 4 chip-а създадени
   Voice: "тридесет и осем тридесет и девет четиридесет" → 3 chip-а
   Manual: tap "+" → отваря size picker (presets от category)
   Saved автоматично в product.variations.size

↓ Matrix се появява автоматично щом има поне 1 цвят И поне 1 размер:

         |  S  |  M  |  L  | XL  |
─────────┼─────┼─────┼─────┼─────┤
Черно    |  3  |  5  |  2  |  0  |
Бяло     |  2  |  4  |  0  |  1  |
Синьо    |  0  |  0  |  0  |  0  |
─────────┴─────┴─────┴─────┴─────┘

Tap клетка → custom numpad slide-up
Voice mode (long-press matrix header):
   "черно три бели пет синьо две три" → 
      AI parse: че черно=3, бяло=5, синьо=2, и още 3 при последен цвят
      Hybrid voice: Web Speech за цветовете, Whisper за числата

Зона в магазина: [_______] (Web Speech)
   "стелаж три рафт две"
   Помага при ревизии (zone walk)
```

**Бутони:**
```
[ЗАПАЗИ] [🖨 ПЕЧАТАЙ] [← Назад] [Напред →]
```

**ЗАПАЗИ behavior:** Save & exit. `confidence_score = 70%`.

---

### Стъпка 3: ЦЕНИ + ДОПЪЛНИТЕЛНИ
**Цел:** пълна financial картина + метаданни.

**Полета:**
1. 💵 **Доставна цена** — Whisper voice + custom numpad
2. 🏷️ **Цена едро** — Whisper voice + custom numpad
3. 📊 **ПЕЧАЛБА %** — read-only, live calc: `(retail - cost) / retail * 100`. Показано в зелено ако > 30%, амбер 15-30%, червено < 15%.
4. 🧵 **Материя** (по избор) — Web Speech, autocomplete от history (последните 50 уникални материи на tenant-а)
5. 🌍 **Произход** (по избор) — Web Speech, default "България"

**Бутони:**
```
[ЗАПАЗИ финал] [🖨 ПЕЧАТАЙ финал] [← Назад]
```

**ЗАПАЗИ behavior:** Save & exit. `confidence_score = 95%`. AI има пълна картина за reports/insights.

**Note:** AI описание + AI снимка са **извън wizard**. Triггерват се post-creation от life-board nudge ("12 артикула без AI описание — €0.24 общо → Генерирай").

---

## 2. VOICE ROUTING POLICY

> **OVERRIDES B1** от DELIVERY_ORDERS_DECISIONS_FINAL (30.04.2026)
> Reason: Live test (voice-tier2-test.php, 02.05.2026) показа Web Speech работи добре за български думи, грешен само на цифри/децимали. Hybrid > total replacement.

### Numeric fields → ВИНАГИ Whisper Groq (whisper-large-v3)
- `price_retail`, `price_wholesale`, `price_cost`
- `quantity` (per matrix cell)
- `discount_percent`, `markup_percent`
- `barcode` (числов string)
- `code_sku` (артикулен номер — често буквено-цифрен; Whisper по-добър за mixed)

### Text fields → Web Speech (browser native, безплатно)
- `name`, `description`
- `material`, `origin`, `notes`
- `supplier_name`, `customer_name`
- `category`, `subcategory` (chips от list)
- `color`, `size` (chips)
- `zone` (location text)

### Hybrid / Mixed input → Parallel run, parser-merge
**Кога:** voice command съдържа и текст и числа.

Примери:
- "червени тениски 25 лева" (на стъпка 1 voice add)
- "черно три бели пет" (на стъпка 2 matrix)
- "Marina цена 30 лева" (доставчик + цена)

**Логика:**
```javascript
// Parallel
const webSpeechResult = await runWebSpeech();  // captures text well
const audioBlob = await stopMediaRecorder();    // for Whisper
const whisperResult = await fetch('/services/voice-tier2.php', {audio: audioBlob});

// Parse
const parsed = parseHybridTranscript(webSpeechResult, whisperResult);
// parsed = {
//   text_parts: ["червени тениски"],     // from Web Speech
//   number_parts: [25],                   // from Whisper (more accurate)
//   units: ["лева"],
//   structured: { name: "червени тениски", price: 25 }
// }
```

**Cost:** ~30% от commands hit Whisper. 30% × 12 voice/day × 5 stores = 18 commands/day = ~1.5 min/day Whisper. Free tier 7,200 min/day → 0.02% utilization → €0.

---

## 3. VOICE CONTINUOUS FLOW (auto-advance)

### Принципът
След всяка успешна voice диктовка → AI populate-ва полето → 2-секунди confirm delay → ако silence → auto-advance към следващото поле.

### Flow на стъпка 1 (best-case, hands-free):
```
Пешо: tap 🎤 (или voice "ново")
   ↓
[STT отваря continuous mode, listens]
   ↓
Пешо: "червени тениски"
   ↓ Web Speech transcript: "червени тениски" (conf 0.85)
   ↓ AI populate name field
   ↓ 2-сек confirm delay → silence → ✅ confirmed
   ↓ AI auto-advance към price field, focus + voice prompt: "цена?"
   ↓
Пешо: "тридесет лева"
   ↓ Whisper transcript: "30.00" (conf 0.92)
   ↓ AI populate price = 30
   ↓ 2-сек silence → ✅ confirmed
   ↓ AI auto-advance към supplier
   ↓
Пешо: "Marina"
   ↓ Web Speech: "Marina" (conf 0.91)
   ↓ AI matches existing supplier (fuzzy "марина" → "Marina")
   ↓ AI loads supplier.categories → enables category dropdown
   ↓ silence → confirmed → advance
   ↓
Пешо: "тениски"
   ↓ Web Speech: "тениски"
   ↓ AI guess: subcategory="Тениски", parent category="Дрехи"
   ↓ AI populate БОТЕ category и subcategory → silence → confirmed → advance
   ↓
[next field = баркод]
   ↓
Пешо: "следващ"
   ↓ Magic word detected → skip баркод
   ↓ advance към артикулен номер
   ↓
Пешо: "следващ"
   ↓ skip артикулен номер
   ↓ STOP — последно поле, чака потвърждение
   ↓
AI: "Готово?" — показва summary card → highlights ЗАПАЗИ бутон с pulse animation
   ↓
Пешо: "запази"
   ↓ Magic word "запази" → trigger ЗАПАЗИ бутон → save
   ↓
✅ Артикул създаден за 12 секунди, hands-free.
```

### Magic words (recognized navigation commands)
**Винаги в priority над content parsing.** Ако voice transcript IS exactly една от magic думите → trigger action, не populate field.

| Magic дума | Action |
|---|---|
| "следващ" / "напред" / "по-нататък" | Skip current field, advance |
| "пропусни" | Same as следващ |
| "назад" / "предишен" | Previous field |
| "запази" | Trigger ЗАПАЗИ бутон |
| "печатай" / "печат" | Trigger 🖨 ПЕЧАТАЙ бутон |
| "отказ" | Close wizard без save |
| "като предния" | Trigger 📋 Като предния бутон |
| "търси" / "намери" | Open 🔍 Търси overlay |
| "стоп" / "спри" | Disable continuous mode (back to push-to-talk) |
| "не" / "поправи" | Cancel last auto-confirm, re-open last field |

### Confirm delay configuration
**Default:** 2 секунди silence → auto-confirm.

**Overrides:**
- Numeric fields (price, quantity): **3 секунди** (по-голям risk при грешка → повече време за reaction)
- Text fields (name, description): **2 секунди**
- Magic word fields (предишен, запази): **0 секунди** (instant action)

**Visual feedback:** countdown bar под транскрипцията. Прогрес от 0 → 100%. Ако Пешо каже нещо → reset countdown → re-evaluate.

### Continuous mode toggle
- Default ON в wizard (от стъпка 1 до save)
- Pешо може да каже "стоп" → disable → standard push-to-talk
- Settings flag в users table: `continuous_voice_default` (default true за owner/manager, default false за seller — Пешо първо trябва да се запознае)

---

## 4. "📋 КАТО ПРЕДНИЯ" БУТОН

**Source:** SIMPLE_MODE_BIBLE.md §7.2.8 (v1.3) — пълна спецификация там, summary тук.

### Поведение
- **Tap бутон** → 1-tap копира **последния** създаден артикул на този tenant
- **Стрелка ⌄** до бутона → recent 10 артикули modal (tap = template)
- **Voice** "като предния" → същото като tap

### Какво се копира (10 полета)
1. Доставчик
2. Категория
3. Подкатегория
4. Материя
5. Произход
6. Доставна цена
7. Retail цена
8. Цена едро
9. has_variations флаг + structure (само цветове + размери на чипса, БЕЗ бройките)
10. Снимка (с `−10 confidence_score` penalty за реминд later)

### Какво НЕ се копира (винаги празно)
- Име
- Баркод
- Артикулен номер
- Бройки (matrix == 0 за всички ceti)

### След "Като предния" auto-advance
- Snimkata copied → ✅ filled, skip
- Цена copied → но highlighted с pulse → AI казва "Цена същата 30 лв?" → 3-сек silence → confirm
- Доставчик copied → silence → confirm
- Категория copied → silence → confirm
- Подкатегория copied → silence → confirm
- Име **празно** → focus mic, AI prompt "Име?"
- Баркод **празно** → focus mic OR camera scan
- Артикулен номер **празно** → "следващ" пропуска (auto-gen)

### Risk mitigation (от BIBLE §7.2.8 risk секция)
Митко натиска "Като предния" много пъти и забравя да смени поле → magazinеt пълен с грешен материал. AI nudge post-hoc:
> "Записа 10 артикула с материя 'памук' за последния месец. Сигурен ли си че всички са памучни?"

---

## 5. "🔍 ТЪРСИ" БУТОН (нов в v4)

### Поведение
- **Tap** → отваря search overlay (slide from right, 80% screen)
- **Voice** "търси" → същото
- Voice или typed search ("Marina бикини черни")
- Резултатите = **всички артикули** на tenant-а (не само recent 10)

### Search overlay UI
```
┌──────────────────────────────────────────┐
│  [✕]  🔍 Намери template                  │
├──────────────────────────────────────────┤
│   🎤 [voice търсене_____________]         │  Web Speech
│      или [📷 баркод scan]                 │
├──────────────────────────────────────────┤
│   📷 Тениска бяла L                       │
│      Marina · Дрехи · Тениски · 30лв     │  Last edited 2 wks
│   ─────────────────────────────────────   │
│   📷 Тениска черна L                      │
│      Marina · Дрехи · Тениски · 30лв     │  Last edited 1 mo
│   ─────────────────────────────────────   │
│   📷 Тениска синя XL                      │
│      Marina · Дрехи · Тениски · 32лв     │  Last edited 3 mo
└──────────────────────────────────────────┘
```

### Search логика
- Search query (voice или typed) → matches срещу:
  - `products.name` (full-text + LIKE)
  - `products.barcode` (exact match)
  - `products.code` (exact match)
  - `products.category` + `subcategory`
  - `suppliers.name` (joined)
- Sort: relevance DESC, потом `updated_at DESC`
- Top 20 резултата
- Voice command "първия" / "втория" / "третия" → tap-equivalent

### След selection
- **Same as "Като предния":** копира 10 полета от избрания template
- Wizard се refreshва с populated полета
- Same auto-advance logic (highlight цена, празно име, празни кодове)

### Use case
> "Преди година направих абсолютно същия модел в син цвят. Не помня детайлите."
> Митко tap "🔍 Търси" → "син модел" → намира template → промения само цвят на "червен" → ЗАПАЗИ.

---

## 6. AUTO-GEN BARCODE + SKU

### Trigger
При ЗАПАЗИ ако `barcode` или `code` са празни → backend генерира.

### Barcode formula (EAN-13, scanner-compatible)
```
Структура: TTPPPPPPPPCCD
- TT (2 цифри): tenant_id padded (07 за tenant=7)
- PPPPPPP (7 цифри): product_id padded
- CC (2 цифри): tenant store_id (00 ако default)
- D (1 цифра): EAN-13 checksum digit (computed)

Пример: tenant=7, product_id=12345, store=1
→ "07" + "0012345" + "01" + checksum = "0700123450107"
   (check digit calculated)
```

### SKU formula (tenant-specific human-readable)
```
Структура: ENI-YYYY-NNNN
- ENI (3 chars): tenant.short_code (от tenants.short_code, default first 3 chars of tenant.name)
- YYYY: year на създаване
- NNNN (zero-padded): tenant-specific sequential

Пример: tenant=7 (ENI), 2026, 47-ми артикул
→ "ENI-2026-0047"
```

### Backend implementation
```php
function ensureProductCodes(int $product_id, int $tenant_id): array {
    $product = DB::run("SELECT barcode, code FROM products WHERE id = ?", [$product_id])
        ->fetch();
    
    $updates = [];
    if (empty($product['barcode'])) {
        $updates['barcode'] = generateEAN13($tenant_id, $product_id);
    }
    if (empty($product['code'])) {
        $updates['code'] = generateSKU($tenant_id, $product_id);
    }
    
    if (!empty($updates)) {
        DB::run(
            "UPDATE products SET barcode = ?, code = ? WHERE id = ?",
            [$updates['barcode'] ?? $product['barcode'], 
             $updates['code'] ?? $product['code'], 
             $product_id]
        );
    }
    
    return $updates;
}
```

---

## 7. CATEGORY + SUBCATEGORY CASCADE

### Логика (запазва се от текущата система)
```
1. Tenant има list от своите категории (categories table)
2. Категория има list от подкатегории (subcategories table, FK to category)
3. Доставчик има list от категории (suppliers.categories JSON или join table)

При смяна на доставчик:
   → reload category dropdown filtered by supplier.categories
   → ако selected category не е в новата filtered list → reset category + subcategory

При смяна на категория:
   → reload subcategory dropdown filtered by category.subcategories
   → ако selected subcategory не е в новата filtered list → reset subcategory
```

### AI guess logic
Воice "тениски" → AI:
1. Search subcategories WHERE name LIKE '%тениск%' OR alt_names LIKE '%тениск%'
2. Find parent category
3. Find suppliers (JOIN suppliers.categories) — if exactly 1 supplier има тази category → auto-fill supplier също
4. If multiple suppliers → ask voice "От кой доставчик?"

### Confidence routing на AI guess
- High confidence (exact match): auto-fill, skip user confirmation
- Medium confidence (fuzzy match >0.7): show 2-3 chip options, voice "първия"/"втория"
- Low confidence (<0.7): manual select от dropdown

---

## 8. SAVE BEHAVIOR (confidence_score model)

### При ЗАПАЗИ от различни стъпки:

| ЗАПАЗИ от | confidence_score | Може да продава | Виден в search | Виден в AI insights |
|---|---|---|---|---|
| Стъпка 1 | 40% | ✅ ДА | ✅ ДА | ✅ ДА (low confidence flagged) |
| Стъпка 2 | 70% | ✅ ДА | ✅ ДА | ✅ ДА |
| Стъпка 3 | 95% | ✅ ДА | ✅ ДА | ✅ ДА (full data) |

**Артикулът съществува след всеки ЗАПАЗИ.** Не draft, не pending. Real product със confidence_score колона.

### Self-correcting loop
Артикули с low confidence генерират life-board nudges:
- "8 артикула без снимка → tap"
- "12 без цена едро → tap"
- "5 без вариации → tap"

Tap → филтрира products list → batch попълване (Митко в Detailed Mode).

---

## 9. PRINT BEHAVIOR

### При [🖨 ПЕЧАТАЙ] от всяка стъпка:

1. **Auto-save** артикула с current данни (запазва preди print)
2. Determine print template:
   - Стъпка 1+: minimal etiket (име + баркод + цена + дата) — 50×30mm
   - Стъпка 2+: + бройка
   - Стъпка 3+: + материя (full)
3. Send to printer-setup.php → TSPL → DTM-5811
4. След успешен print → toast "Етикет отпечатан"
5. Stays на текущата стъпка (НЕ exit wizard)

### Edge cases
- Принтер не е свързан → toast "Свържи принтера" + tap → отваря printer-setup.php
- Print fail → toast "Опитай отново" + retry бутон
- Без налична снимка/име → blocking error "Поне име трябва да въведеш преди печат"

---

## 10. PRINT BEHAVIOR (continued) + I18N

### Локализация
- **ВСИЧКИ** UI текстове през `t('key')` функция
- Lang ключове в `lang/bg.json` за wizard:
  - `wizard.step1.title` = "Нов артикул"
  - `wizard.step1.name_label` = "Име"
  - `wizard.step1.price_label` = "Цена на дребно"
  - `wizard.copy_previous_btn` = "📋 Като предния"
  - `wizard.search_template_btn` = "🔍 Търси"
  - ... (~50 keys общо)

### Двойно обозначение (BG валута)
- Цените показвани едновременно: "30 лв (15.34 €)"
- Курс fixed 1.95583 (BG национален курс)
- Задължително до 8.8.2026 (BG законен изискан период)

---

## 11. BACKEND CHANGES REQUIRED

### Нови файлове
1. **`services/voice-router.php`** (~80 реда)
   - `routeVoice($field_type, $audio_b64, $web_speech_data)` 
   - Returns unified result {transcript, confidence, engine, cost}
   - Calls `transcribeWithWhisper()` for numeric fields
   - Returns Web Speech result as-is for text fields

2. **`services/parse-hybrid-voice.php`** (~120 реда)
   - `parseHybridTranscript($web_speech_text, $whisper_text, $context)`
   - NLP за разделяне на text + numbers + units
   - Returns structured object: `{name, price, quantity, color, size}` per context

3. **`services/copy-product-template.php`** (~60 реда)
   - `copyProductTemplate($source_product_id, $tenant_id)`
   - Returns 10-field copied data + null-фields за name/barcode/code/quantities

### Edited файлове
1. **`products.php`** — пълен rewrite на wizard секцията (~1500 реда)
2. **`product-save.php`** — добавя auto-gen baркод/SKU + confidence_score logic
3. **`partials/voice-overlay.php`** — добавя `data-field-type` aware logic + magic words parser
4. **`build-prompt.php`** — нови контексти за wizard voice (за multi-field parsing)

### DB миграции
**S93 migration** (нова, ще се пише в Code Code сесия):
```sql
-- 1. Confidence score (ako не съществува)
ALTER TABLE products 
  ADD COLUMN IF NOT EXISTS confidence_score TINYINT DEFAULT 95,
  ADD COLUMN IF NOT EXISTS source_template_id INT NULL,
  ADD COLUMN IF NOT EXISTS created_via ENUM('wizard_v4','wizard_legacy','quick_add','import','api') DEFAULT 'wizard_v4',
  ADD INDEX idx_confidence (confidence_score, tenant_id);

-- 2. Voice command log (за analytics)
CREATE TABLE IF NOT EXISTS voice_command_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  field_type VARCHAR(50),
  engine ENUM('web_speech', 'whisper', 'hybrid'),
  transcript TEXT,
  confidence DECIMAL(3,2),
  duration_ms INT,
  audio_size_bytes INT,
  cost_usd DECIMAL(8,5) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant_user (tenant_id, user_id, created_at)
);

-- 3. Tenant short code (за SKU generation, ako не съществува)
ALTER TABLE tenants
  ADD COLUMN IF NOT EXISTS short_code VARCHAR(8) NULL;

-- 4. Backfill short_code за existing tenants
UPDATE tenants SET short_code = UPPER(LEFT(name, 3)) WHERE short_code IS NULL;
```

---

## 12. FRONTEND ARCHITECTURE

### State management
Wizard state в **single object** в module-level (НЕ window globals):
```javascript
const wizardState = {
  step: 1,                          // 1, 2, 3
  data: {                           // populated as user inputs
    name: null,
    price_retail: null,
    supplier_id: null,
    category_id: null,
    subcategory_id: null,
    barcode: null,
    code: null,
    photo_url: null,
    has_variations: false,
    variations: { color: [], size: [] },
    matrix: {},                     // {color_size: quantity}
    zone: null,
    price_cost: null,
    price_wholesale: null,
    material: null,
    origin: 'България',
    confidence_score: null
  },
  source_template_id: null,         // ako е "Като предния" / "Търси" used
  voice_state: {
    continuous_mode: true,
    listening: false,
    current_field: null,
    auto_advance_pending: false,
    last_transcript: null
  },
  saves: []                          // log of saves для retry/undo
};
```

### Auto-save protection
- Crash recovery: state се auto-saves в localStorage всеки 5 секунди
- При reload → "Продължи незавършено добавяне? [Да] [Не, ново]"
- Save record в localStorage се чисти при final ЗАПАЗИ или при отказ

### Performance targets
- Wizard mount → 200ms
- Стъпка transition → 150ms (no full reload)
- Voice STT result populate → 500ms (Web Speech) или 1500ms (Whisper)
- Save commit → 800ms (включително printer ако активиран)

---

## 13. VISUAL DESIGN

### Compliance с DESIGN_LAW.md
- 3-те свещени неща: shine + glow + magenta hue (q-magic 280°-310°) на ZAPAZI бутон
- Bottom nav: HIDDEN в wizard (full-screen takeover)
- Header: simplified — само ✕ close + brand + 2 action buttons (Търси, Като предния)

### Step indicator (top)
```
●━━━━○━━━━○   Стъпка 1 от 3
```
Активна стъпка = neon magenta circle, бъдещи = grey ring.
Tap на завършена стъпка → връща се на нея (ако е save-натa).

### Glass cards
Field groups в .glass с .shine + .shine-bottom + .glow.
Колор:
- Стъпка 1 = q-default (255°/222°)
- Стъпка 2 = q-jewelry (variations focus)
- Стъпка 3 = q-amber (financial focus)

### Voice button states
```
🎤 idle (magenta glow, breathing pulse)
🎤 recording (red pulse, "● ЗАПИСВА" текст)
✓ confirming (green check, "✓ ГОТОВО" текст 2 секунди)
⚠ low confidence (амбер, "Не разбрах" + retry)
❌ error (red, error message)
```

---

## 14. IMPLEMENTATION CHECKLIST (DOD за Code Code)

### Numerical DOD
- [ ] L4 pushed: 6-10 commits "S93.WIZARD.V4.[N]: ..."
- [ ] PHP lint clean на всички modified files
- [ ] 0 native prompt() / alert() / confirm()
- [ ] 0 hardcoded "лв" / "BGN" / "€" — priceFormat() везде
- [ ] 0 hardcoded BG текст — t('key') везде
- [ ] design-kit/check-compliance.sh PASS на всички changed files
- [ ] 3 нови backend services PHP files exist + syntax clean
- [ ] migrations/s93_wizard_v4_up.sql + down.sql exist + idempotent
- [ ] migration applied на live DB tenant=7 + tenant=99 verify
- [ ] products.php loads без 500 на mobile + desktop
- [ ] Стъпка 1 → 2 → 3 navigation works
- [ ] ЗАПАЗИ от стъпка 1 създава real product със confidence_score=40
- [ ] "📋 Като предния" copies 10 fields, leaves 4 empty
- [ ] "🔍 Търси" search returns relevant results
- [ ] Voice routing: numeric field calls Whisper endpoint, text field uses Web Speech
- [ ] Magic words "следващ", "запази" trigger correct actions
- [ ] Auto-advance after 2-сек silence (3-сек for numeric)
- [ ] Auto-gen barcode (EAN-13 valid checksum) on empty save
- [ ] Auto-gen SKU (tenant short_code prefix) on empty save
- [ ] Custom numpad slide-up на price tap (no native keyboard)
- [ ] Matrix renders когато има цветове AND размери
- [ ] Print бутон auto-saves THEN trigger printer
- [ ] localStorage crash recovery works (test: reload mid-wizard)

### NE PIPAS list
- partials/ai-brain-pill.php (S92 AIBRAIN)
- partials/voice-overlay.php — wizard ще използва **нов** wizard-voice-overlay.php (отделен от AIBRAIN)
- sale.php (S87G done)
- chat.php (S91 done)
- delivery.php / orders.php / order.php / defectives.php (S89 done)
- ai-studio*.php / inventory.php / stats.php
- design-kit/* (LOCKED)
- life-board.php / compute-insights.php
- voice-tier2-test.php (test page, untouched)
- services/voice-tier2.php (consume only)
- STATE_OF_THE_PROJECT.md / MASTER_COMPASS.md (само шеф-чат update-ва)

---

## 15. ROLLBACK PLAN

### Pre-implementation
- `cp products.php products-legacy.php` → keep as fallback
- Add toggle в settings: `users.wizard_version` ENUM('v4','legacy') DEFAULT 'v4'
- products.php при load checks user.wizard_version → renders v4 OR includes products-legacy.php

### Post-deployment safety net (24 часа)
- Ако beta blocker discovered → toggle settings.wizard_version = 'legacy' за all users
- Single SQL: `UPDATE users SET wizard_version = 'legacy' WHERE tenant_id = 7`
- Без code rollback нужен

### After 7 days successful operation
- Drop products-legacy.php
- Drop users.wizard_version column
- Cleanup commit "S95.WIZARD.V4.LOCK: remove legacy fallback"

---

## 16. TIME ESTIMATE

| Task | Hours |
|---|---|
| Backend: voice-router.php + parse-hybrid-voice.php + copy-product-template.php | 1.5 |
| DB migration write + test | 0.5 |
| Frontend: products.php wizard rewrite (3 steps + state mgmt) | 3.0 |
| Frontend: voice continuous flow + magic words parser | 1.5 |
| Frontend: matrix component (variations) | 1.0 |
| Frontend: "Като предния" + "Търси" overlays | 1.0 |
| Frontend: auto-gen barcode/SKU integration | 0.5 |
| Testing: unit tests + integration smoke | 1.0 |
| Documentation update (handoff doc) | 0.5 |
| **TOTAL** | **10.5h** |

**Realistic Code Code session:** 8h твърд budget (дневен максимум). 10.5h estimated → split на 2 сесии:
- **Session 1 (8h):** Backend + DB + frontend stъпка 1 (most critical)
- **Session 2 (3h):** Стъпки 2-3 + Като предния + Търси + polish

---

## 17. WHAT'S NEXT (POST-WIZARD V4)

### Phase 1 (post-beta polishing)
- Variation auto-detection from category presets (knit cellect by category)
- Voice command "като поръчката от вчера" (history-based templates)
- Photo AI auto-categorization (tap photo → AI detects category)

### Phase 2 (Q3 2026)
- Continuous mode default ON for all users (after Pешо comfort threshold reached)
- Wake word "АЙ" for true hands-free
- Multi-language wake words (RO, GR, HR, RS for partner countries)

### Phase 3 (post-public-launch)
- Shared template marketplace (per category, per supplier)
- AI-suggested templates based on shopping list patterns
- "Magic batch" mode: voice "10 артикула, всички като предния, само цвета сменя" → AI loops 10 times

---

## КРАЙ НА SPEC

**Approval status:** ✅ approved by Тихол (02.05.2026)
**Implementation:** ⏳ pending — next available Code Code session
**Owner:** Тихол + Шеф-чат за coordination, Code Code за code
**Bunke:** S93 след S92 closure
