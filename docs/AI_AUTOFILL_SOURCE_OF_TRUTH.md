# AI AUTO-FILL — ЕДИНЕН ИЗТОЧНИК НА ИСТИНАТА (Source of Truth)

**Дата на създаване:** 14.05.2026 (S143 EOD)
**Решено в:** S143 шеф-чат с Тих (документирано в MASTER_COMPASS S143 секции)
**Версия:** v1.0

---

## ❗ ЗА ВСЕКИ НОВ CHAT — ПРОЧЕТИ ТОЗИ ФАЙЛ ПЪРВО

Когато започваш работа по wizard "Добави артикул", AI обаждания, image
recognition, описания, категории — **прочети този документ преди всичко
останало**. Той отговаря на въпроса:

> **Откъде AI взема информация за да попълва автоматично категории,
> подкатегории, признаци, описание?**

Ако нещо в кода ти противоречи на този документ — питай Тих преди да
направиш каквато и да е промяна.

---

## 🎯 КРАТЪК ОТГОВОР (за бързо разбиране)

**AI auto-fill работи в 3 стъпки:**

1. **Преди AI обаждане:** проверка за кеш (Ниво 1+2 = безплатно)
   - Баркод lookup → ако артикулът е известен → копираме
   - Perceptual hash → ако подобна снимка → копираме

2. **AI обаждане (Gemini 2.5 Flash):** ако кешът не помага
   - AI взема контекст от `biz-coefficients.php` (за този бизнес)
   - AI връща предложения за категория, цвят, описание, признаци
   - **Цена: $0.0015 на снимка**

3. **Потвърждение от Пешо:** "Закачам към 'Бикини Дафи'? ДА/НЕ"
   - При ДА → snapshot записан за бъдещ кеш
   - При НЕ → 3 алтернативи + "Кажи ти"

---

## 📚 ИЗТОЧНИЦИ НА ИНФОРМАЦИЯ (КЪДЕ ЖИВЕЯТ ДАННИТЕ)

### 1. `biz-coefficients.php` — ГЛАВЕН ИЗТОЧНИК

**Файл:** `/var/www/runmystore/biz-coefficients.php` (274 KB)

**Съдържа:**

| Променлива | Какво е | Брой |
|---|---|---|
| `$BIZ_COEFFICIENTS` | Fuzzy matching ключове за business_type | 648 ключа |
| `$BIZ_VARIANTS` | Пълна дефиниция за всеки от 300 бизнес типа | 300 обекта |

**Всеки обект в `$BIZ_VARIANTS` съдържа:**

```php
[
    'id' => 'lingerie_store',
    'business_type' => 'Магазин за бельо',
    'has_variants' => true,
    'variant_fields' => ['Размер', 'Цвят'],
    'variant_presets' => [
        'Размер' => ['XS', 'S', 'M', 'L', 'XL'],
        'Цвят' => ['Бял', 'Черен', 'Бежов', ...]
    ],
    'units' => ['бр'],
    'typical_fields' => ['Марка', 'Материал', 'Сезон'],
    'suggested_subcategories' => ['Сутиени', 'Бикини', 'Боди', ...],
    'ai_scan_detects' => ['тип бельо', 'цвят', 'марка', 'материал', 'тип чашка']
]
```

**КРИТИЧНО — `ai_scan_detects`:** Това поле казва на AI какво да разпознае
от снимка ЗА КОНКРЕТНИЯ бизнес тип. Различно за различни бизнеси:

- Магазин за бельо → `тип бельо, цвят, марка, материал`
- Бижутериен магазин → `вид бижу, цвят метал, вид камък, форма`
- Магазин за маратонки → `марка, модел, цвят, дизайн`

### 2. `category-groups.json` — 50 ТИПА БИЗНЕСИ С КАТЕГОРИИ (отделно)

**Файл:** `/var/www/runmystore/category-groups.json` (2758 реда)

**Съдържа:** 50 типа бизнеси с 200 групи и ~1300 категории (1-ниво).

**ВАЖНО:** Този файл е по-стара структура. `biz-coefficients.php` е
канонично новата и пълна (300 бизнеса). Когато има несъответствие —
`biz-coefficients.php` печели.

### 3. `GEMINI_SEASONALITY.md` — СЕЗОННОСТ (само за 15 бизнеса)

**Файл:** `/mnt/project/GEMINI_SEASONALITY.md`

**Покрива 15 типа:** auto_parts, bedding_textile, bookstore_stationery,
building_materials, butcher_deli, children_clothing, cosmetics,
electronics_accessories, grocery, jewelry_accessories, pet_shop,
pharmacy, sporting_goods, toys, womens_clothing.

**Съдържа:** пикове, dead periods, religious context, salary cycles,
revenue uplift %, stock-up weeks before.

**TODO (отложено за post-beta):** разширение за останалите 285 бизнеса.
Бюджет за разширяване = €30 еднократно AI генерация. **НЕ оскъпява AI
auto-fill** (статични данни в JSON).

### 4. `tenants.business_type_id` — ЗА КОЙ БИЗНЕС Е МАГАЗИНЪТ

**В DB:** колона `tenants.business_type_id` (или подобно — провери
конкретно име в текущата schema).

**Запълва се при onboarding:** Пешо казва с гласа си какво продава →
системата записва business_type_id. От тук нататък системата зарежда:
- Категории за този бизнес
- Признаци които AI трябва да извлича (от `ai_scan_detects`)
- AI prompt template адаптиран за този бизнес

**Виж:** `08_onboarding.md` — стъпка 7: "Какво продаваш — дрехи, обувки,
аксесоари?"

---

## 🤖 AI FLOW — СТЪПКА ПО СТЪПКА

### Стъпка 0: Wizard стартира
Пешо натиска "Добави артикул". Системата:
1. Чете `tenants.business_type_id` → знае кой е бизнесът
2. Зарежда от `biz-coefficients.php` → дефиницията за този бизнес:
   - `suggested_subcategories` (за UI dropdown)
   - `variant_presets` (за бързо избиране размер/цвят)
   - `ai_scan_detects` (за AI prompt-а)

### Стъпка 1: Сканирай баркод / напиши име / кажи с глас
Стандартна стъпка. Не променя AI логиката.

### Стъпка 2: СНИМКА (нова в S144)
Пешо снима артикула.

**Преди AI обаждане — checking cache:**

#### Ниво 1 — Barcode Lookup
```php
$existing = DB::run("SELECT * FROM products WHERE barcode=? AND tenant_id=? LIMIT 1",
    [$barcode, $tenant_id])->fetch();

if ($existing) {
    return copyFromExisting($existing);  // €0, без AI
}
```

**Бонус:** ако баркодът съществува в **друг tenant** на платформата →
копираме нечувствителни полета (категория, цвят, описание, материя).
**НЕ** копираме цени, маржове, доставчик.

#### Ниво 2 — Perceptual Hash
```php
$newHash = computePHash($photo);  // 64-bit pHash
$similar = DB::run("
    SELECT ai_response, final_data FROM ai_snapshots
    WHERE tenant_id=? AND confirmed_by_user=1
    AND BIT_COUNT(CAST(image_hash AS UNSIGNED) ^ CAST(? AS UNSIGNED)) < 8
    ORDER BY created_at DESC LIMIT 5
", [$tenant_id, $newHash])->fetchAll();

if ($similar) {
    return reuseResponse($similar[0]);  // €0, без AI
}
```

**Очаквана икономия:** ~50% спестявания след 1000+ артикула с
потвърждения.

#### Cache MISS → AI обаждане

```php
$response = callGeminiAI([
    'model' => 'gemini-2.5-flash',
    'image' => $photo,
    'prompt' => buildAIPrompt($business_type, $ai_scan_detects),
    'output_schema' => 'json'
]);

saveSnapshot($newHash, $response);  // за бъдещо кеширане
return $response;
```

### Стъпка 3: AI връща (в ЕДНО обаждане)

AI връща JSON с всичко наведнъж:

```json
{
  "category_suggestion": {
    "name": "Бикини",
    "confidence": 0.92
  },
  "subcategory_suggestion": {
    "name": "Дамски бикини",
    "confidence": 0.88
  },
  "color": "Розов",
  "size_visible": null,
  "material": "Памук + ластан",
  "gender": "female",
  "season": "all_year",
  "brand": null,
  "short_description": "Дамски бикини от мек памук с ластан. Розов цвят с малки бели точки. Класически крой с ниска талия. Удобни за ежедневие.",
  "ai_scan_detects_extracted": {
    "тип бельо": "бикини",
    "цвят": "розов",
    "марка": null,
    "материал": "памук + ластан",
    "тип чашка": null
  }
}
```

### Стъпка 4: Потвърждение от Пешо

UI показва:
```
🤖 Намерих "Бикини" в твоите категории.
   Закачам там? [✓ ДА] [✗ НЕ]
```

- При **ДА** → snapshot.confirmed_by_user = 1 → запазва се за бъдещ кеш
- При **НЕ** → "Виж предложения:
  1. Дамски бикини
  2. Бельо
  3. Бельо и чорапи
  Или кажи ти каква категория."

**КРИТИЧНО — Rule #38:** AI НИКОГА не създава нова категория сам.
Само owner може да добавя нови категории в settings.

---

## 🔑 КАКВО AI НИКОГА НЕ ПРАВИ (ЗАБРАНИ)

1. **❌ Не създава нови категории сам** — само предлага от съществуващите.
2. **❌ Не генерира цени** — Пешо казва цената с глас или с натискане.
3. **❌ Не генерира маржове** — изчисляват се от cost_price + retail_price.
4. **❌ Не "халюцинира"** — ако не вижда нещо ясно → връща `null`, не гадае.
5. **❌ Не предполага за качество/използване** — само факти от снимката.
   - ❌ "Удобни за бягане по тревен терен" (предположение)
   - ✅ "Маратонки със въздушна възглавница, бяла подметка" (факт)

---

## 🤖 AI МОДЕЛИ (от Deep Research)

| Какво | Модел | Цена |
|---|---|---|
| Image-to-Attributes | **Gemini 2.5 Flash** | $0.0015/снимка |
| Алтернатива (евтина) | Qwen 2.5-VL 72B (DeepInfra) | $0.0006/снимка |
| Voice-to-Text (live) | **Deepgram Nova-3** | $0.0043/мин |
| Voice-to-Text (async) | **Whisper API** | $0.006/мин |
| Текст generation | **GPT-4o mini** | $0.0006/1K tok |
| Маркетинг снимки | DALL-E 3 | $0.04/снимка |

**Default:** Gemini 2.5 Flash + GPT-4o mini + Deepgram Nova-3.

**Виж:** `docs/AI_AUTOFILL_RESEARCH_2026.md` за пълен анализ.

---

## 📊 ЛИМИТИ ПО ПЛАН (Rule #45)

| План | Auto-fill | STT минути | SEO description | Img gen |
|---|---|---|---|---|
| FREE €0 | 20/мес | 0 | 0 | 0 |
| START €19 | 150/мес | 150 | 5 | 0 |
| PRO €49 | 1000/мес | 1000 | 50 | 10 |
| BUSINESS €109 | unlimited | 5000 (fair use) | unlimited | 50 |

**Над лимитите → кредитна система:**
- €5 за 50 допълнителни image обработки
- €5 за 500 допълнителни STT минути

---

## 📦 КРАТКО ОПИСАНИЕ vs ДЪЛГО ОПИСАНИЕ

| Параметър | Кратко описание | Дълго описание |
|---|---|---|
| Дължина | 20-50 думи | 100-200 думи |
| За какво | Вътрешно търсене + AI контекст | Google + Ecwid онлайн |
| Език | Фактологичен (само какво се вижда) | Маркетингов |
| Кога се генерира | В **един** AI обаждане заедно с категория (БЕЗПЛАТНО) | По натискане на бутон "Опиши за онлайн" (ПЛАТЕНО, ~$0.005) |
| Кой го пише | AI Gemini 2.5 Flash | AI GPT-4o |

**Решение S143:** Краткото описание е **в същото обаждане** като
категорията — НЕ оскъпява AI разходите.

---

## 🗄️ DB SCHEMA (КОЛОНИ КОИТО ИЗПОЛЗВАМЕ)

### `products` таблица

| Колона | Тип | За какво |
|---|---|---|
| `name` | VARCHAR | Име |
| `code` | VARCHAR | SKU/код (НЕ "sku" — Rule!) |
| `barcode` | VARCHAR | EAN/баркод |
| `retail_price` | DECIMAL | Продажна цена (НЕ "sell_price" — Rule!) |
| `cost_price` | DECIMAL | Доставна цена |
| `category_id` | INT | FK към categories |
| `parent_id` | INT | За вариации (NULL = главен) |
| `supplier_id` | INT | FK към suppliers |
| `image_url` | VARCHAR | URL към снимка |
| `description` | TEXT | **Кратко описание** (AI generated, fact-based) |
| `description_long` | TEXT | Дълго описание (по натискане, маркетинг) — добавя се в S144 |
| `gender` | ENUM | male/female/kids/unisex (добавено S143) |
| `season` | ENUM | summer/winter/transitional/all_year (S143) |
| `brand` | VARCHAR | Марка (S143) |
| `confidence_score` | TINYINT | 0-100, completeness на инфо |

### `ai_snapshots` таблица (нова в S144)

```sql
CREATE TABLE ai_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    image_hash VARCHAR(64) NOT NULL,
    ai_response JSON NOT NULL,
    confidence FLOAT,
    confirmed_by_user TINYINT DEFAULT 0,
    final_data JSON,
    product_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hash (image_hash),
    INDEX idx_tenant_confirmed (tenant_id, confirmed_by_user)
);
```

---

## 🔗 ВРЪЗКИ КЪМ СВЪРЗАНИ ДОКУМЕНТИ

| Документ | За какво |
|---|---|
| `docs/AI_AUTOFILL_RESEARCH_2026.md` | Deep Research (413 реда, икономика) |
| `MASTER_COMPASS.md` | S143 секции с всички решения |
| `TOMORROW_WIZARD_REDESIGN.md` | План за S144 wizard редизайн |
| `CORE_BUSINESS_RULES.md` | Бизнес правила (категории 2 вида, etc.) |
| `biz-coefficients.php` | 300 бизнеса с ai_scan_detects |
| `category-groups.json` | 50 бизнеса с категории (по-стара структура) |
| `GEMINI_SEASONALITY.md` | Сезонност за 15 бизнеса |
| `/mnt/project/08_onboarding.md` | Voice onboarding flow (стъпка 7 = business_type) |
| `services/voice-tier2.php` | Whisper Groq integration (SACRED) |
| `services/ai-color-detect.php` | Color detection (РАЗШИРИ за пол/сезон) |

---

## 🚫 SACRED ZONES — НЕ ПИПАЙ ПРИ S144

- `services/voice-tier2.php` (Whisper voice)
- `services/ai-color-detect.php` (РАЗШИРИ за пол/сезон/материя; НЕ изтривай)
- `js/capacitor-printer.js` (Bluetooth принтер)
- 8 mic input полета във wizard
- `_wizMicWhisper`, `_wizPriceParse`, `_bgPrice` функции

---

## 🎓 КЛЮЧОВИ ПРАВИЛА (от MASTER_COMPASS Standing Rules)

- **Rule #38:** AI никога не създава категории сам.
- **Rule #39:** Всеки AI обаждане с снимка → snapshot за бъдещо обучение.
- **Rule #40:** При <50 магазина — не правим локален ML модел.
- **Rule #43:** AI Auto-fill винаги в PRO+ план. Това е USP.
- **Rule #44:** Default AI = Gemini 2.5 Flash + GPT-4o mini.
- **Rule #45:** Лимити по план задължителни, отвъд = кредити.
- **Rule #46:** Prompt caching + perceptual hashing задължителни.
- **Rule #47:** AI COGS никога не надвишава 15% от приходите.
- **Rule #48:** EU AI Act прозрачност — UI показва "генерирано от AI".

---

## ❓ ЧЕСТО ЗАДАВАНИ ВЪПРОСИ

**Q: Откъде AI знае коя категория да предложи?**
A: От `biz-coefficients.php` — взема `suggested_subcategories` за този бизнес.

**Q: Откъде AI знае какво да разпознае от снимка?**
A: От `biz-coefficients.php` — взема `ai_scan_detects` за този бизнес.

**Q: Какво ако AI разпознае категория която не съществува в магазина?**
A: AI връща предложение, UI пита Пешо "Закачам тук? ДА/НЕ". Ако НЕ → 3
   алтернативи от съществуващите. Никога не се създава нова.

**Q: Колко струва AI auto-fill месечно?**
A: €0.15 за магазин с 100 артикула/месец (Gemini 2.5 Flash, $0.0015/снимка).

**Q: Какво ако Пешо не иска AI?**
A: Wizard работи и без снимка/AI. Пешо ръчно избира категория с гласа си.

**Q: AI може ли да работи на български?**
A: Да. Gemini 2.5 Flash работи отлично на български в input и output.
   GPT-4o mini също.

**Q: Какво ако AI обаждането не работи (network/API down)?**
A: Wizard продължава БЕЗ AI попълване — Пешо ръчно слага категория.
   Закон №3: AI мълчи, PHP продължава.

---

**КРАЙ НА ДОКУМЕНТА**

При промени в това поле — обнови ВЕРСИЯ горе + добави entry в MASTER_COMPASS.
