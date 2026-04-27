# SESSION 83 HANDOFF — AI STUDIO ARCHITECTURE COMPLETE

**Дата:** 2026-04-27
**Сесия:** S83.AI_STUDIO.MOCKUPS_FINAL
**Статус:** ✅ Mockup-и финализирани (V5). 🟡 Production код НЕ е писан.
**Следваща сесия:** S84 — Implementation phase

---

## 🎯 ОБОБЩЕНИЕ

Тази сесия дефинира **пълната архитектура** на AI Studio модула — UX flow, pricing, bulk правила, design tokens, backend endpoints, DB schema. **Не е писан production код.** Всички решения са визуализирани в mockup `ai_studio_FINAL_v5.html` и одобрени от Тихол.

**Ключово решение:** AI Studio има **3 режима** (Лесен / Разширен / Купи) и **2 bulk потока** (Wizard bulk вариации / Standalone bulk recovery). Магията е винаги per-артикул, никога bulk.

---

## 📐 АРХИТЕКТУРА — ОБЩА КАРТИНА

```
┌──────────────────────────────────────────────────────────────┐
│                                                                │
│   PRODUCTS.PHP (Wizard 4 стъпки)                              │
│   ──────────────────────────                                   │
│   Step 1: Вид                                                 │
│   Step 2: Основни (главна снимка)                             │
│   Step 3: Варианти (matrix N снимки)                          │
│   Step 4: Бизнес (на едро, описание)                          │
│         ↓                                                       │
│   [Запази артикул] → success екран                             │
│         ↓                                                       │
│   Печат на етикети (overlay)                                  │
│         ↓                                                       │
│   ┌──────────────────────────────────────────┐               │
│   │ ✨ AI Studio CTA card                    │ ← Mockup ①     │
│   │                                            │               │
│   │ "Искаш ли професионална снимка?"          │               │
│   │ + AI описание за каталог                   │               │
│   │                                            │               │
│   │  [Да, направи →]   [Не, готово]           │               │
│   └──────────────────────────────────────────┘               │
│         ↓ Tap "Да"                                             │
│         ↓ redirect to ai-studio.php?from_wizard=1&product_id=X │
│                                                                 │
└──────────────────────────────────────────────────────────────┘
                            ↓
┌──────────────────────────────────────────────────────────────┐
│                                                                │
│   AI-STUDIO.PHP (Standalone)                                   │
│   ─────────────────────────                                    │
│                                                                 │
│   ┌─ Mode detection ────────────────────────┐                 │
│   │                                            │                 │
│   │ if (?from_wizard=1) → Лесен Mode (default)│                 │
│   │ if (user.studio_mode='detailed') → Разшир.│                 │
│   │ Toggle горе вдясно: "⚙ Разширен" / "⚡ Лес."│                │
│   │                                            │                 │
│   └────────────────────────────────────────────┘                │
│                                                                  │
│   ЛЕСЕН MODE (Mockup ②):                                       │
│     - Vision auto-detect категория                              │
│     - Auto bg-removal (ready)                                   │
│     - AI SEO описание готово                                    │
│     - 1 главно решение: "Запази с бял фон €0.05"               │
│     - 1 opt-in: "Облечи на модел €0.50"                         │
│     - Skip: "Остави оригинала"                                  │
│                                                                  │
│   РАЗШИРЕН MODE (Mockup ③):                                    │
│     - 5 категории cards (Дрехи/Бельо/Бижута/Аксес./Друго)       │
│     - Стандартни настройки (модел/фон)                         │
│     - История на генерирани                                     │
│     - **BULK BANNERS** горе:                                    │
│       • "53 артикула без бял фон" → checklist + bulk button    │
│       • "47 артикула без AI описание" → checklist + bulk        │
│                                                                  │
│   WIZARD BULK (Mockup ⑤):                                      │
│     - При from_wizard=1 + артикул с N>1 варианти                │
│     - Показва PREDI лесен режим                                  │
│     - "Обработи всичките N снимки €X.XX (-20% bulk discount)"   │
│     - Само бял фон, БЕЗ магия (магията = per-артикул)          │
│                                                                  │
│   КУПИ КРЕДИТИ (Mockup ④):                                     │
│     - При credits=0 → empty hero "0 Кредити свършиха"            │
│     - 3 таба (Бял фон / Магия / Описание)                       │
│     - 5 пакета: Стартов / Среден -10% / Голям -20% / Макси -30% │
│       / МЕГА -50%                                                │
│     - Stripe checkout integration                                │
│                                                                  │
└──────────────────────────────────────────────────────────────┘
```

---

## 💰 PRICING MATRIX (ФИНАЛНА)

### БЯЛ ФОН (€0.05 базово/снимка)

| Пакет | Снимки | Цена | На снимка | Икономия |
|---|---|---|---|---|
| 🟦 Стартов | 15 | €0.75 | €0.05 | — |
| 🟪 Среден -10% | 100 | €4.50 | €0.045 | €0.50 |
| 🟧 Голям ⭐ -20% | 200 | €8.00 | €0.04 | €2.00 |
| 🟢 Макси -30% | 300 | €10.50 | €0.035 | €4.50 |
| 🔥 МЕГА -50% | 800 | €20.00 | €0.025 | €20.00 |

### AI МАГИЯ (€0.50 базово/магия)

| Пакет | Магии | Цена | На магия | Икономия |
|---|---|---|---|---|
| 🟦 Стартов | 3 | €1.50 | €0.50 | — |
| 🟪 Среден -10% | 20 | €9.00 | €0.45 | €1.00 |
| 🟧 Голям ⭐ -20% | 40 | €16.00 | €0.40 | €4.00 |
| 🟢 Макси -30% | 60 | €21.00 | €0.35 | €9.00 |
| 🔥 МЕГА -50% | 150 | €37.50 | €0.25 | €37.50 |

### AI SEO ОПИСАНИЕ (€0.02 базово/описание)

| Пакет | Описания | Цена | На описание | Икономия |
|---|---|---|---|---|
| 🟦 Стартов | 10 | €0.20 | €0.02 | — |
| 🟪 Среден -10% | 50 | €0.90 | €0.018 | €0.10 |
| 🟧 Голям ⭐ -20% | 150 | €2.40 | €0.016 | €0.60 |
| 🟢 Макси -30% | 300 | €4.20 | €0.014 | €1.80 |
| 🔥 МЕГА -50% | 600 | €6.00 | €0.01 | €6.00 |

**Логика:** 1 артикул = средно 5 цвята = **5 снимки бял фон + 1 главна магия + 1 описание**.

**Bulk вариации в wizard:** допълнителна -20% отстъпка върху бял фон (приложима само за артикули с N>1 варианти, едновременна обработка).

**Никакви trailing zeros в UI** — €0.02 не €0.020. Strict format.

---

## 📋 BULK ПРАВИЛА (КРИТИЧНО)

### 🟢 Bulk #1 — В WIZARD (видим, in-flow)
**Само за вариациите на ТЕКУЩИЯ артикул.**

- **Trigger:** `?from_wizard=1` + продуктът има `variant_count > 1` ИЛИ `variant_photos > 1`
- **Показва се ПРЕДИ лесен режим** (Mockup ⑤)
- **Опции:**
  - 🟧 Primary: "Обработи всичките N снимки €X.XX (-20%)" — bulk бял фон
  - 🟦 Alt: "Само главната + AI магия €0.55" — индивидуална магия
  - ⚪ Skip: "Пропусни сега"
- **Само бял фон може bulk.**
- **Магията е ВИНАГИ поединично** (изисква user избор на модел/настройки).
- **Pricing формула:** `N × €0.05 × 0.80` (където N е брой варианти+1 главна)
  - Пример: 20 снимки = 20 × €0.05 × 0.80 = **€0.80**

### 🟦 Bulk #2 — В STANDALONE (recovery, не от wizard)
**За всичките стари артикули в магазина, които нямат AI обработка.**

- **Trigger:** Owner влиза в standalone (`from_wizard != 1`) с разширен режим
- **Показва се ГОРЕ на главната страница** (Mockup ③ горе)
- **Use case:** Пешо е вкарвал артикули БЕЗ AI обработка (от ревизия, скрита инвентаризация, бързо добавяне).
- **2 банера:**
  - 🔴 "53 артикула без бял фон (с вариантите им = 287 снимки)" → tap → checklist + bulk button
  - 🟣 "47 артикула без AI описание" → tap → checklist + bulk button
- **И двата bulk-а** изключват магията (твърде бавна за масово).
- **Bulk бял фон цена:** `total_snimki × €0.05 × 0.80` (-20% bulk discount)
- **Bulk SEO цена:** `count × €0.02 × 0.90` (-10% bulk discount)

### 🟣 Магия — ВИНАГИ per-артикул
- **Никога не е bulk** — нито в wizard, нито в standalone.
- Бавен процес (15-30 сек на магия), изисква user-specific настройки.
- "Quality Guarantee" застрахова: 2 безплатни retry-a + refund при block.

---

## 🔀 WIZARD CONTEXT vs STANDALONE CONTEXT (КРИТИЧНО)

**Същият файл `ai-studio.php`** работи в **2 различни режима**, базиран на URL параметър `?from_wizard=1`. Това е същият код, но **различни UI елементи се показват/скриват**.

### Detection логика

```php
$fromWizard = isset($_GET['from_wizard']) && $_GET['from_wizard'] == '1';
$productId  = $_GET['product_id'] ?? null;
$mode       = $user->studio_mode ?? 'simple'; // simple|detailed
```

### Какво се показва КЪДЕ — Master таблица

| UI елемент | Wizard context (`from_wizard=1`) | Standalone (без from_wizard) |
|---|---|---|
| **Header brand** | `AI STUDIO · ЛЕСЕН` (или РАЗШИРЕН) | `AI STUDIO · ЛЕСЕН` (или РАЗШИРЕН) |
| **Back бутон** | → връща в wizard success екран | → връща в предишен екран (chat / life-board) |
| **Toggle Лесен/Разширен** | ✅ ДА (горе вдясно) | ✅ ДА (горе вдясно) |
| **Preview pill (артикул)** | ✅ ДА — показва текущия артикул от wizard | ✅ ДА — показва избрания артикул |
| **Credits bar** | ✅ ДА | ✅ ДА |
| **Image compare (Преди/След)** | ✅ ДА — за главната снимка от wizard | ✅ ДА — за избрания артикул |
| **Vision auto-detect card** | ✅ ДА — върху главната снимка | ✅ ДА |
| **AI SEO описание card** | ✅ ДА — auto-generated при entry | ✅ ДА — auto-generated при entry |
| **Запази с бял фон бутон** | ✅ ДА | ✅ ДА |
| **Облечи на модел бутон (магия)** | ✅ ДА — само на главна снимка | ✅ ДА — само на главна снимка |
| **Остави оригинала** | ✅ ДА | ✅ ДА |
| **CSV бутони (Woo + Shopify)** | ✅ ДА — СЛЕД AI обработка (за този артикул) | ✅ ДА — СЛЕД AI обработка |
| **🟢 Wizard Bulk card (вариации)** | ✅ САМО АКО артикулът има N>1 варианти | ❌ НЕ |
| **🟦 Standalone Bulk банери** | ❌ НЕ | ✅ ДА — горе на главната страница |
| **5 категории cards (разширен)** | ❌ НЕ | ✅ ДА — само в Разширен mode |
| **Стандартни настройки** | ❌ НЕ | ✅ ДА — само в Разширен mode |
| **История на генерирани (8 thumbnails)** | ❌ НЕ | ✅ ДА — само в Разширен mode |
| **Bulk CSV export (всички 156 артикула)** | ❌ НЕ | ✅ ДА — само в Разширен mode |
| **FAB бутон (бърз create)** | ❌ НЕ | ✅ ДА — само в Разширен mode |
| **Quality Guarantee badge** | ✅ ДА — при магия | ✅ ДА — при магия |

### Логика — защо тази разлика?

**Wizard context = "довърши текущия артикул"**
- User е в линия на действие: добавил е артикул, иска да го направи готов за онлайн магазин
- Показваме само **което касае ТОЗИ артикул**: snimka, описание, варианти-bulk
- НЕ показваме glоbal teats: 5 категории, история, bulk recovery — те разсейват
- На bottom: "← Върни се към wizard" вместо стандартен back

**Standalone context = "разходи се по магазина / управлявай"**
- User е дошъл нарочно за AI Studio (от chat.php бутон / life-board)
- Показваме **глобалния поглед**: 5 категории, recovery банери, история, bulk export
- Wizard bulk card НЕ се показва (няма "текущ артикул")

### Mockup mapping към context

| Mockup | Context | Mode |
|---|---|---|
| ① CTA в wizard success | wizard | (преди влизане в AI Studio) |
| ② Лесен Standalone | wizard ИЛИ standalone | simple |
| ③ Разширен Standalone | standalone (НЕ wizard) | detailed |
| ④ Купи кредити | и двете (overlay при credits=0) | независимо |
| ⑤ Wizard Bulk | САМО wizard + N>1 варианти | simple |

### Conditional rendering в код (псевдо)

```php
// PSEUDO-CODE
function renderAIStudio() {
  $fromWizard = $_GET['from_wizard'] == '1';
  $mode = $user->studio_mode;
  $product = loadProduct($_GET['product_id']);

  echo renderHeader($fromWizard, $mode);
  
  if ($fromWizard && $product->variant_count > 1) {
    echo renderWizardBulkCard($product); // Mockup ⑤
    return; // Спира тук, ползвателят избира
  }
  
  if (!$fromWizard && $mode === 'detailed') {
    echo renderBulkRecoveryBanners(); // 53 без фон / 47 без описание
    echo renderCategoriesGrid();      // 5 категории
    echo renderStandardSettings();    // модел / фон
    echo renderHistory();             // 8 thumbnails
    echo renderBulkCsvExport();       // 156 артикула за Woo/Shopify
  }
  
  if ($product) {
    echo renderImageCompare($product);
    echo renderVisionDetect($product);
    echo renderSeoCard($product);
    echo renderActionButtons($product);
    if ($product->ai_bg_done && $product->ai_seo_done) {
      echo renderCsvButtons($product); // Mockup ② долна секция
    }
  }
  
  echo renderBottomNav();
}
```

---

## 🔄 NAVIGATION FLOW (детайлен)

### Поток 1: Wizard → AI Studio → обратно

```
products.php (wizard step 4 success екран)
  ↓ tap "Да, направи професионална"
  ↓ window.location = '/ai-studio.php?from_wizard=1&product_id=X'
  
ai-studio.php?from_wizard=1
  ↓ Detection: $fromWizard = true
  ↓ Header показва "← Назад" → wizard success
  ↓ IF product.variant_count > 1: показва WIZARD BULK CARD (Mockup ⑤)
  ↓ ELSE: показва ЛЕСЕН MODE (Mockup ②)
  ↓ User обработва (бял фон / магия / SEO)
  ↓ tap "Запази" → DB update + кредити decrement
  ↓ Показват се CSV бутони (готов за онлайн магазин)
  ↓ tap "← Назад" 
  ↓ window.location = '/products.php?from_studio=1&product_id=X'
  
products.php?from_studio=1
  ↓ Detection: hydrate state, load product от DB
  ↓ Wizard success екран показан с обновени данни:
  ↓   - "AI обработката е готова!" badge
  ↓   - CSV бутоните вече видими (има ai_seo_text)
  ↓ User: [+ Нов артикул] / [Затвори]
```

### Поток 2: Standalone → AI Studio → стои вътре

```
chat.php (магенто бутон)
  ↓ tap → /ai-studio.php (без from_wizard)
  ↓
ai-studio.php
  ↓ Detection: $fromWizard = false
  ↓ Header показва "← Назад" → chat.php
  ↓ Mode = $user->studio_mode (simple ИЛИ detailed)
  ↓
  IF detailed mode:
    показва Mockup ③ (разширен):
    - Bulk recovery банери (53 без фон / 47 без описание)
    - 5 категории cards
    - Стандартни настройки
    - История 8 thumbnails
    - Bulk CSV export (156 готови артикула)
    - FAB бутон долу
  
  IF simple mode:
    "Нямаш текущ артикул" → показва списък с готови артикули
    User избира → отива в Mockup ② Лесен mode
```

### Поток 3: Кредити = 0 (overlay)

```
ai-studio.php (всеки flow)
  ↓ User tap "Запази с бял фон" (или магия / SEO)
  ↓ Backend проверка: bg_credits < 1
  ↓ Response: error_code = 'no_credits'
  ↓ Frontend показва modal/overlay → /ai-studio-buy-credits.php (Mockup ④)
  ↓ User избира пакет → Stripe Checkout
  ↓ Payment success → webhook → credits +N
  ↓ Redirect back → ai-studio.php (възстановява state)
  ↓ User повтаря action — сега работи
```

### Поток 4: Bulk recovery (от standalone)

```
ai-studio.php (standalone, detailed mode)
  ↓ tap банер "53 артикула без бял фон"
  ↓ Open overlay/drawer → checklist на 53-те артикула
  ↓ User избира кои → tap "Обработи всички €11.48 (-20%)"
  ↓ Backend: bulk_bg_recovery action → batch processing
  ↓ Progress overlay: "Обработвам 1/53... 2/53..."
  ↓ След batch SUCCESS: показва summary "47/53 готови, 6 failed"
  ↓ Кредити updated в credits bar
  ↓ Банерите автоматично updated (само неуспешните в банера)
```

---



Gemini Vision API анализира снимката и връща точен тип + препоръчителен модел/настройки.

### Vision response structure

```json
{
  "category": "clothes_female",
  "gender": "female",
  "age_group": "adult",
  "confidence": 0.94,
  "recommended_model": "female_25_30_european",
  "recommended_bg": "white_studio",
  "recommended_pose": "front_neutral"
}
```

### 7 типа категории + препоръка на модел

| Vision category | Препоръка action | Препоръчителен модел/настройка |
|---|---|---|
| `clothes_male` | "облечи на мъж" | male_25_35_european, front_neutral |
| `clothes_female` | "облечи на жена" | female_25_30_european, front_neutral |
| `clothes_kids_boy` | "облечи на момче" | child_male_8_12, playful |
| `clothes_kids_girl` | "облечи на момиче" | child_female_8_12, playful |
| `clothes_teen_male` | "облечи на тийн" | teen_male_15_18, casual |
| `clothes_teen_female` | "облечи на тийн" | teen_female_15_18, casual |
| `lingerie_female` | "облечи на жена" | female_25_30_european, lingerie_pose |
| `lingerie_male` | "облечи на мъж" | male_25_35_european, underwear_pose |
| `swimwear_female` | "плажна сцена" | female_25_30_beach, beach_bg |
| `swimwear_male` | "плажна сцена" | male_25_35_beach, beach_bg |
| `jewelry` | "студийна близка" | no_model, macro_studio_bg |
| `accessories_shoes` | "обувка на крак" | foot_only_european, white_bg |
| `accessories_bag` | "чанта на рамо" | female_25_30_european, casual |
| `accessories_watch` | "часовник на ръка" | hand_only, dark_bg |
| `accessories_hat` | "шапка на глава" | head_only, white_bg |
| `other` | "опиши какво искаш" | custom_prompt_required |

### Confidence routing (LAW №8 от Bible)

- `confidence >= 0.85` → **auto-apply** препоръката, показва "AI разпозна: [категория]"
- `confidence 0.5 - 0.85` → показва "AI смята: [категория] - потвърди?" → user избира
- `confidence < 0.5` → показва "AI не успя да разпознае - избери ръчно" → list с 7-те типа

### Cost
- **Vision call: €0.02 per снимка** (един път per продукт, кешира се в `ai_vision_cache`)
- Не се плаща при повторен entry в AI Studio — взема се от cache

---

## 📝 AI SEO ОПИСАНИЕ — WORKFLOW

### Кога се генерира

**Лесен mode (Mockup ②):**
- Auto-generated **при entry в standalone** (background, докато user разглежда)
- Показва се в seo-card СЛЕД като е готово (~2-3 sec)
- Цена: **€0.02** от seo_credits (взема се при успешно генериране)
- Показва: ✓ ГОТОВО badge + 2 действия:
  - "Виж пълното →" (overlay с пълен текст)
  - "↻ Регенерирай" (-€0.02 нова операция)

**Разширен mode (Mockup ③):**
- Не auto-generates → user избира
- В detail-drawer на product → бутон "Генерирай AI описание €0.02"

**Wizard CTA (Mockup ①):**
- НЕ auto-generated в wizard
- User първо избира "Да, направи професионална" → влиза в standalone → там се генерира

### Gemini prompt template (за SEO description)

```
Ти си SEO copywriter за дамски/мъжки/детски облекла. На база следните данни,
напиши SEO-friendly описание на {language} език за онлайн магазин:

Продукт: {product.name}
Категория: {product.category}
Цена: {product.retail_price} EUR
Цвят: {variants_colors}
Размери: {variants_sizes}
Vision context: {ai_vision_cache.category}, {gender}, {age_group}

Изисквания:
- 80-150 думи
- Естествен tone, не маркетинг spam
- Споменай keyword "{category}" поне веднъж
- Завърши с практичен съвет (combine, грижа, etc.)
- БЕЗ цена в текста (динамично се сменя)
- БЕЗ HTML тагове
```

### Output format

```json
{
  "description": "Класически син деним за модерна жена...",
  "tags": ["women", "denim", "slim", "casual"],
  "seo_title": "Дънки Mustang син деним - дамски slim fit",
  "seo_meta": "Slim fit дамски дънки от мек деним..."
}
```

Записва се в:
- `products.ai_seo_text` (full description)
- `products.ai_seo_title` (за SEO Title в CSV)
- `products.ai_seo_meta` (за SEO Description в CSV)
- `products.ai_seo_tags` (JSON array, за Tags в CSV)

---

## 💸 CREDITS CONSUMPTION FLOW (точна логика)

### Кога се харчат кредити

| Action | Credit type | Сума | Кога |
|---|---|---|---|
| Vision detect (auto) | bg_credits (0) | €0 | Free, само cost €0.02 за tenant |
| Bg removal (single) | bg_credits | -1 | При SUCCESS, не при queue |
| Bg removal (bulk wizard) | bg_credits | -N | След batch SUCCESS, на цена -20% |
| Bg removal (bulk standalone) | bg_credits | -N | След batch SUCCESS, на цена -20% |
| AI Magic (single) | magic_credits | -1 | При SUCCESS, не при queue |
| AI SEO description | seo_credits | -1 | При SUCCESS на response |
| AI SEO regenerate | seo_credits | -1 | Втора plata при tap "Регенерирай" |

### Quality Guarantee retry logic

```
Tap "Облечи на модел" (€0.50, magic_credits -1)
  ↓
AI generates → user преглежда
  ↓
Ако не харесва → tap "↻ Опитай пак" (FREE, retry_count++)
  ↓ (max 2 free retries)
След 2 неуспешни → "Не успях да направя добро. Refund?" → magic_credits +1
  ↓
Ако harasva → "Запази" (final, без refund)
```

### Атомарни transactions

Всеки credit decrement трябва да е в DB transaction с операцията:

```php
DB::beginTransaction();
try {
    DB::run('INSERT INTO ai_studio_operations ...');
    DB::run('UPDATE tenant_ai_credits SET bg_credits = bg_credits - 1 WHERE tenant_id = ?', [$tid]);
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
    throw $e;
}
```

### Edge cases

- **Кредити = 0** при tap → redirect to `ai-studio-buy-credits.php`
- **Кредити стигат за bulk = ?** → ако са по-малко → "Имаш само X кредита. Купи още или обработи частично."
- **Failed AI call** → НЕ се харчат кредити, retry безплатно
- **User затваря browser** → текущата операция продължава (queued), кредитите се харчат при completion

---

## 🗂 ФАЙЛОВЕ — IMPLEMENTATION CHECKLIST

### Файлове за читане преди започване на код (FileZilla download)

```
/var/www/runmystore/
├── chat.php                       ← ЕТАЛОН за Neon Glass (commit c2caaf5)
├── products.php                   ← Wizard, тук добавяме CTA card
├── ai-studio.php                  ← Standalone (съществува, ще се rewrite)
├── ai-studio-action.php           ← Backend endpoint
├── ai-studio-backend.php          ← fal.ai/Gemini wrapper
├── ai-image-processor.php         ← Bg removal logic
├── product-save.php               ← Save endpoint (има bug с variant photos)
├── DESIGN_SYSTEM.md               ← Neon Glass spec v2.0
├── BIBLE_v3_0_CORE.md             ← 5 закона
├── BIBLE_v3_0_TECH.md             ← DB schema §14
├── PRODUCTS_DESIGN_LOGIC.md       ← Wizard логика
└── config/
    └── config.php                 ← API ключове (НЕ commit-вай)
```

### Файлове за писане/редактиране (production)

#### 🟢 CREATE NEW

```
/var/www/runmystore/
├── ai-studio-buy-credits.php           ← НОВ (Mockup ④, Купи кредити)
├── ai-studio-bulk.php                  ← НОВ (Mockup ⑤+бульк процеси)
├── ai-studio-vision.php                ← НОВ (Gemini Vision auto-detect)
└── ai-studio-stripe-webhook.php        ← НОВ (credits увеличаване след payment)
```

#### 🟡 EDIT EXISTING

```
/var/www/runmystore/
├── products.php                        ← Добавя CTA card в success екран
├── ai-studio.php                       ← Rewrite целия (Лесен + Разширен)
├── ai-studio-action.php                ← Добавя 'detect_category', 'bulk_bg', 'generate_seo'
├── ai-studio-backend.php               ← fal.ai nano-banana 2 wiring
└── config/config.php                   ← +AI_STUDIO_API_KEYS, +STRIPE_SECRET
```

#### 📦 DB SCHEMA (необходими changes)

```sql
-- 1. Кредити per tenant
CREATE TABLE IF NOT EXISTS tenant_ai_credits (
  id              INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id       INT NOT NULL,
  bg_credits      INT DEFAULT 0,
  magic_credits   INT DEFAULT 0,
  seo_credits     INT DEFAULT 0,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_tenant (tenant_id),
  INDEX idx_tenant (tenant_id)
);

-- 2. Покупки на пакети (audit trail)
CREATE TABLE IF NOT EXISTS ai_credit_purchases (
  id                 INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id          INT NOT NULL,
  user_id            INT NOT NULL,
  package_type       ENUM('bg','magic','seo') NOT NULL,
  package_size       ENUM('starter','medium','large','maxi','mega') NOT NULL,
  credits_added      INT NOT NULL,
  amount_eur         DECIMAL(10,2) NOT NULL,
  discount_pct       INT DEFAULT 0,
  stripe_session_id  VARCHAR(200),
  status             ENUM('pending','completed','refunded','failed') DEFAULT 'pending',
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant_status (tenant_id, status),
  INDEX idx_stripe (stripe_session_id)
);

-- 3. AI операции (audit trail на consumption)
CREATE TABLE IF NOT EXISTS ai_studio_operations (
  id              INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id       INT NOT NULL,
  user_id         INT NOT NULL,
  product_id      INT,
  variant_id      INT NULL,
  operation_type  ENUM('bg_removal','magic_model','magic_studio','seo_description','vision_detect') NOT NULL,
  is_bulk         TINYINT(1) DEFAULT 0,
  bulk_batch_id   VARCHAR(40) NULL,
  credits_used    INT DEFAULT 1,
  cost_eur        DECIMAL(8,3),
  status          ENUM('queued','processing','completed','failed','refunded') DEFAULT 'queued',
  error_msg       TEXT NULL,
  result_url      VARCHAR(500) NULL,
  retry_count     INT DEFAULT 0,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at    TIMESTAMP NULL,
  INDEX idx_tenant_product (tenant_id, product_id),
  INDEX idx_status (status),
  INDEX idx_bulk (bulk_batch_id)
);

-- 4. Auto-detect cache (Vision results)
CREATE TABLE IF NOT EXISTS ai_vision_cache (
  id              INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id       INT NOT NULL,
  product_id      INT NOT NULL,
  category        VARCHAR(50),     -- "clothes_female", "jewelry", etc
  gender          ENUM('male','female','unisex','na'),
  age_group       ENUM('adult','teen','kid','baby','na'),
  confidence      DECIMAL(3,2),    -- 0.00-1.00
  raw_response    JSON,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_product (tenant_id, product_id)
);

-- 5. Стандартни настройки per user (Разширен mode)
CREATE TABLE IF NOT EXISTS ai_studio_settings (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id         INT NOT NULL,
  user_id           INT NOT NULL,
  default_model     VARCHAR(50) DEFAULT 'female_25_30_european',
  default_bg        VARCHAR(50) DEFAULT 'white_studio',
  studio_mode       ENUM('simple','detailed') DEFAULT 'simple',
  UNIQUE KEY unique_user (tenant_id, user_id)
);

-- 6. User column за UI mode preference
ALTER TABLE users
  ADD COLUMN studio_mode ENUM('simple','detailed') DEFAULT 'simple' AFTER role;

-- 7. products column — flag дали е обработен от AI Studio
ALTER TABLE products
  ADD COLUMN ai_bg_done TINYINT(1) DEFAULT 0 AFTER retail_price,
  ADD COLUMN ai_magic_done TINYINT(1) DEFAULT 0 AFTER ai_bg_done,
  ADD COLUMN ai_seo_done TINYINT(1) DEFAULT 0 AFTER ai_magic_done,
  ADD COLUMN ai_seo_text TEXT NULL AFTER ai_seo_done,
  ADD INDEX idx_ai_bg (tenant_id, ai_bg_done),
  ADD INDEX idx_ai_seo (tenant_id, ai_seo_done);

-- 8. product_variations column (когато bug-ът се оправи)
ALTER TABLE product_variations
  ADD COLUMN image_url VARCHAR(500) NULL AFTER quantity,
  ADD COLUMN ai_bg_done TINYINT(1) DEFAULT 0 AFTER image_url;
```

---

## 🔧 BACKEND ENDPOINTS

### `/ai-studio-action.php` actions

```php
// 1. Auto-detect категория от снимка (Vision)
POST /ai-studio-action.php
  action=detect_category
  product_id=X
→ {category, gender, age_group, confidence, recommended_model}

// 2. Bg removal (single)
POST /ai-studio-action.php
  action=bg_remove
  product_id=X
  variant_id=Y (optional)
→ {result_url, credits_remaining}

// 3. AI Magic (model try-on)
POST /ai-studio-action.php
  action=magic_apply
  product_id=X
  preset=female_25_30 (or custom)
→ {result_url, credits_remaining, retry_available: 2}

// 4. AI SEO description
POST /ai-studio-action.php
  action=generate_seo
  product_id=X
  language=bg
→ {description, credits_remaining}

// 5. Bulk bg removal (from wizard, варианти)
POST /ai-studio-action.php
  action=bulk_bg_wizard
  product_id=X
  variant_ids=[Y1,Y2,...] (или ALL)
→ {batch_id, queued_count, total_cost_eur, credits_used}

// 6. Bulk bg removal (from standalone, recovery)
POST /ai-studio-action.php
  action=bulk_bg_recovery
  product_ids=[X1,X2,...]
→ {batch_id, queued_count, total_cost_eur, credits_used, discount_pct}

// 7. Bulk SEO recovery
POST /ai-studio-action.php
  action=bulk_seo_recovery
  product_ids=[X1,X2,...]
→ {batch_id, total_cost_eur, credits_used}

// 8. Get bulk progress
GET /ai-studio-action.php
  action=bulk_status
  batch_id=Z
→ {total, completed, failed, in_progress}

// 9. Get standalone bulk recovery counts (за банери)
GET /ai-studio-action.php
  action=recovery_counts
→ {missing_bg: 53, missing_seo: 47, total_variants_no_bg: 287}
```

### `/ai-studio-buy-credits.php`

```php
GET  → page (Mockup ④)
POST → action=initiate_stripe
       package_type=bg|magic|seo
       package_size=starter|medium|large|maxi|mega
     → {checkout_url}
```

### `/ai-studio-stripe-webhook.php`

```php
POST  ← Stripe webhook (payment_intent.succeeded)
      → INSERT ai_credit_purchases.status='completed'
      → UPDATE tenant_ai_credits SET bg/magic/seo += credits_added
```

---

## 📂 PRODUCTS.PHP — конкретни промени

### Localизация

`products.php` ред ~7587 има `wizGoSuccess()` (или подобна) function която показва success екран след save. Тук добавяме AI Studio CTA card.

### Промяна 1 — Махане на стария AI Studio Step (ред 4900-4960)

```php
// СТАР КОД — премахни целия блок:
function renderStudioStep() { ... }   // редове ~4900-4960
// + всички references към него (~ред 4406 в step===2 handler)
```

### Промяна 2 — WIZ_LABELS

```php
// СТАРО: 5 елемента
const WIZ_LABELS = ['Вид', 'Основни', 'Варианти', 'Бизнес', 'AI Studio'];

// НОВО: 4 елемента
const WIZ_LABELS = ['Вид', 'Основни', 'Варианти', 'Бизнес'];
```

### Промяна 3 — Success екран

В `wizGoSuccess()` или еквивалент, между existing "Свали CSV" и "Добави нов" бутоните:

```html
<!-- AI Studio CTA card — СЛЕД print etiketi, ПРЕДИ CSV/Add new buttons -->
<div class="briefing-section q-violet" style="margin:14px 0">
  <div class="briefing-actions" style="flex-direction:column;gap:8px">
    <!-- Conditional copy базиран на _hasPhoto -->
    <div style="font-size:13px;font-weight:800;color:#f1f5f9;margin-bottom:8px">
      <?= $hasPhoto ? 'Искаш ли професионална снимка?' : 'Забрави да снимаш?' ?>
    </div>
    <div style="font-size:11px;color:rgba(255,255,255,.7);margin-bottom:10px">
      <?= $hasPhoto ? 'За онлайн магазин или Facebook + AI описание' 
                    : 'Може да добавиш снимка сега и AI да я обработи' ?>
    </div>
    <button class="briefing-btn-primary" style="--qcol:hsl(280,70%,62%);width:100%"
            onclick="window.location.href='/ai-studio.php?from_wizard=1&product_id=<?= $newProductId ?>'">
      <?= $hasPhoto ? 'Да, направи професионална →' : 'Да, добави снимка →' ?>
    </button>
    <button class="briefing-btn-secondary" style="width:100%" onclick="wizClose()">
      Не, готово е така
    </button>
  </div>
</div>
```

### Промяна 4 — Return handler

В горната част на `products.php` добавяме detection дали идва return от ai-studio:

```php
// Ако ?from_studio=1 → показваме wizard в success state
// (артикулът вече е записан, AI обработката е била изпълнена)
if (isset($_GET['from_studio']) && $_GET['from_studio'] == '1' 
    && isset($_GET['product_id'])) {
    // Hydrate wizard state from DB → show success екран
    // Артикулът има ai_bg_done=1, ai_magic_done=?, ai_seo_done=?
}
```

### Net delta: ~ -50 реда (махам повече отколкото добавям)

---

## 🎨 NEON GLASS DESIGN TOKENS (за copy-paste)

### CSS Variables

```css
:root{
  --hue1:255;
  --hue2:222;
  --border:1px;
  --border-color:hsl(var(--hue2),12%,20%);
  --radius:22px;
  --radius-sm:14px;
  --ease:cubic-bezier(0.5,1,0.89,1);
  --bg-main:#08090d;
  --text-primary:#f1f5f9;
  --text-secondary:rgba(255,255,255,.6);
  --text-muted:rgba(255,255,255,.4)
}
```

### Body Background (mandatory)

```css
body{
  background:
    radial-gradient(ellipse 800px 500px at 20% 10%,hsl(var(--hue1) 60% 35% / .22) 0%,transparent 60%),
    radial-gradient(ellipse 700px 500px at 85% 85%,hsl(var(--hue2) 60% 35% / .22) 0%,transparent 60%),
    linear-gradient(180deg,#0a0b14 0%,#050609 100%);
  background-attachment:fixed;
}
body::before{
  content:'';position:fixed;inset:0;
  background-image:url("data:image/svg+xml,...turbulence noise...");
  opacity:.03;pointer-events:none;z-index:1;mix-blend-mode:overlay;
}
```

### 6Q Hue Mapping

```css
.q1 { --qcol: hsl(0,85%,55%); }      /* loss / red */
.q2 { --qcol: hsl(280,70%,62%); }    /* loss_cause / violet */
.q3 { --qcol: hsl(145,70%,50%); }    /* gain / green */
.q4 { --qcol: hsl(175,70%,50%); }    /* gain_cause / teal */
.q5 { --qcol: hsl(38,90%,55%); }     /* order / amber */
.q6 { --qcol: hsl(220,10%,60%); }    /* anti_order / gray */
```

### Hue-matched primary button

```css
.btn-hue {
  background:linear-gradient(135deg,
    color-mix(in oklch,var(--qcol) 35%,hsl(220 30% 10%)) 0%,
    color-mix(in oklch,var(--qcol) 20%,hsl(220 30% 8%)) 100%);
  border:1px solid color-mix(in oklch,var(--qcol) 50%,transparent);
  box-shadow:
    0 4px 14px color-mix(in oklch,var(--qcol) 35%,transparent),
    inset 0 1px 0 rgba(255,255,255,.12),
    inset 0 0 20px color-mix(in oklch,var(--qcol) 10%,transparent);
}
```

### Glass Card (with conic-shine + glow)

Шаблон в HTML:

```html
<div class="glass">
  <span class="shine"></span>
  <span class="shine shine-bottom"></span>
  <span class="glow"></span>
  <span class="glow glow-bottom"></span>
  <!-- content here, position:relative;z-index:5 -->
</div>
```

Пълен CSS — копирай от `chat.php` ред 260-500 (или от mockup `ai_studio_FINAL_v5.html`).

---

## 📥 CSV EXPORT — WooCommerce + Shopify (КРИТИЧНО)

### Value proposition
**Защо Пешо плаща AI Studio?** За да получи **ready-to-import артикули** в WooCommerce/Shopify. Без работещ CSV export цялата AI обработка е безсмислена.

### Правило: КОГА има CSV бутон

**🟢 ИМА CSV бутон:**
1. **Mockup ① (CTA в wizard success)** — само ако артикулът има оригинална снимка + име + цена. CSV съдържа базовите полета (без AI обработка).
2. **Mockup ② (Лесен mode СЛЕД AI обработка)** — CSV вече има AI снимка + AI описание = **пълно ready-to-import**.
3. **Mockup ③ (Разширен mode)** — CSV download винаги достъпен в product detail (drawer от list).

**🔴 НЯМА CSV бутон:**
- Артикул без снимка (безсмислено — Woo/Shopify искат снимки)
- Артикул без описание (празно поле, не приема внос)
- Артикул без цена

### Логика в код (PHP)

```php
$canExportCsv = !empty($product->main_image_url) 
             && !empty($product->ai_seo_text) 
             && !empty($product->name) 
             && $product->retail_price > 0;

if ($canExportCsv) {
    // Покажи [↓ CSV за Woo] [↓ CSV за Shopify] бутони
}
```

### Препоръчителен подход: 2 формата с 2 бутона

```
[↓ CSV за WooCommerce]    [↓ CSV за Shopify]
```

PHP пише 1 master структура от DB, после трансформира в нужния формат при export.

---

## 📊 WOOCOMMERCE CSV FORMAT

### Полета (header row)

```csv
ID,Type,SKU,Name,Published,"Is featured?","Visibility in catalog",
"Short description",Description,"Tax status","Tax class","In stock?",
Stock,"Backorders allowed?","Sold individually?","Weight (kg)",
"Allow customer reviews?","Sale price","Regular price",Categories,Tags,
"Shipping class",Images,Parent,
"Attribute 1 name","Attribute 1 value(s)","Attribute 1 visible","Attribute 1 global",
"Attribute 2 name","Attribute 2 value(s)","Attribute 2 visible","Attribute 2 global"
```

### Mapping от RunMyStore DB → WooCommerce CSV

| WooCommerce поле | RunMyStore source | Пример |
|---|---|---|
| `ID` | празно (auto-assigned от Woo) | — |
| `Type` | `simple` или `variable` | `variable` |
| `SKU` | `products.code` | `MUST-001` |
| `Name` | `products.name` | `Дънки Mustang син деним` |
| `Published` | `1` (ако ai_bg_done=1) | `1` |
| `Description` | `products.ai_seo_text` (AI generated) | пълен SEO текст |
| `Short description` | първите 150 chars от ai_seo_text | excerpt |
| `In stock?` | `1` ако `inventory.quantity > 0` | `1` |
| `Stock` | `inventory.quantity` | `45` |
| `Regular price` | `products.retail_price` | `35.00` |
| `Sale price` | празно или промо цена | — |
| `Categories` | `category > subcategory` | `Дрехи > Дънки` |
| `Tags` | от AI SEO + auto (gender, season) | `women, denim, slim` |
| `Images` | comma-separated URLs (AI snimka първо) | `https://.../ai_bg.jpg,https://.../v1.jpg` |
| `Attribute 1 name` | `Цвят` (фиксирано) | `Цвят` |
| `Attribute 1 value(s)` | pipe-separated стойности | `Mustang син \| черен \| бял` |
| `Attribute 2 name` | `Размер` (фиксирано) | `Размер` |
| `Attribute 2 value(s)` | pipe-separated | `S \| M \| L \| XL` |
| `Weight (kg)` | `products.weight_kg` (ако съществува) | `0.4` |

### За артикули с варианти (variable products) — 2 типа редове

**Ред 1: Parent (variable product):**
```csv
,variable,MUST-001,"Дънки Mustang",1,0,visible,
"Slim fit дамски...","Класически син...",taxable,,1,,1,0,0.4,1,,35.00,
"Дрехи > Дънки","women,denim",,main_ai_bg.jpg,,
"Цвят","син|черен|бял",1,1,
"Размер","S|M|L|XL",1,1
```

**Редове 2+: Variations (вариации):**
```csv
,variation,MUST-001-СИН-S,,1,,,,,,,1,12,,,,,,35.00,,,,,blue_ai_bg.jpg,MUST-001,
,"син",,,,"S",,
```

### Variations логика
- 1 parent ред + N variation редове (по 1 per цвят×размер комбинация)
- Variation редове наследяват parent данните (Description, Categories, Tags) — само Stock + Variant Image + Variant SKU + Attribute values се пишат
- Variation ред има `Parent` колона = parent SKU

---

## 📊 SHOPIFY CSV FORMAT

### Полета (header row)

```csv
Handle,Title,"Body (HTML)",Vendor,"Product Category",Type,Tags,Published,
"Option1 Name","Option1 Value","Option2 Name","Option2 Value",
"Option3 Name","Option3 Value","Variant SKU","Variant Grams",
"Variant Inventory Tracker","Variant Inventory Qty",
"Variant Inventory Policy","Variant Fulfillment Service","Variant Price",
"Variant Compare At Price","Variant Requires Shipping","Variant Taxable",
"Variant Barcode","Image Src","Image Position","Image Alt Text",
"SEO Title","SEO Description","Variant Image","Variant Weight Unit",
"Cost per item",Status
```

### Mapping от RunMyStore DB → Shopify CSV

| Shopify поле | RunMyStore source | Пример |
|---|---|---|
| `Handle` | slug от name (lowercased, dashed, ASCII) | `dunki-mustang-sin-denim` |
| `Title` | `products.name` | `Дънки Mustang син деним` |
| `Body (HTML)` | `<p>` + `products.ai_seo_text` + `</p>` | HTML wrapped |
| `Vendor` | `products.brand` или `tenant.name` | `Mustang` |
| `Type` | `products.category` | `Дрехи` |
| `Tags` | comma-separated от AI | `women, denim, slim` |
| `Published` | `TRUE` ако ai_bg_done=1 | `TRUE` |
| `Option1 Name` | `Цвят` (фиксирано) | `Цвят` |
| `Option1 Value` | per row, един вариант | `Mustang син` |
| `Option2 Name` | `Размер` (фиксирано) | `Размер` |
| `Option2 Value` | per row | `M` |
| `Variant SKU` | `products.code` + variant suffix | `MUST-001-СИН-M` |
| `Variant Inventory Tracker` | `shopify` | `shopify` |
| `Variant Inventory Qty` | `inventory.quantity` за вариант | `12` |
| `Variant Inventory Policy` | `deny` или `continue` | `deny` |
| `Variant Price` | `products.retail_price` | `35.00` |
| `Variant Requires Shipping` | `TRUE` | `TRUE` |
| `Variant Taxable` | `TRUE` | `TRUE` |
| `Variant Barcode` | `products.barcode` | `3800123456789` |
| `Image Src` | AI snimka URL (главна) | `https://.../ai_bg.jpg` |
| `Image Position` | `1` за main, `2,3...` за варианти | `1` |
| `Image Alt Text` | `products.name` | `Дънки Mustang син деним` |
| `Variant Image` | специфична за варианта | `https://.../blue_ai.jpg` |
| `SEO Title` | първите 70 chars от name + ключови думи | `Дънки Mustang син деним - дамски slim fit` |
| `SEO Description` | първите 160 chars от ai_seo_text | excerpt |
| `Variant Weight Unit` | `kg` | `kg` |
| `Status` | `active` | `active` |

### Shopify variations — flat structure (различно от Woo!)

Shopify не ползва parent/child. **Един ред per вариант**, всички data повторени:

```csv
Handle,Title,Body (HTML),Vendor,Tags,Option1 Value,Option2 Value,Variant SKU,Variant Price,Variant Inventory Qty,Image Src
dunki-mustang,"Дънки Mustang","<p>Slim fit...</p>",Mustang,"women,denim",Син,S,MUST-001-С-S,35.00,5,https://.../blue.jpg
dunki-mustang,"Дънки Mustang","<p>Slim fit...</p>",Mustang,"women,denim",Син,M,MUST-001-С-M,35.00,12,https://.../blue.jpg
dunki-mustang,"Дънки Mustang","<p>Slim fit...</p>",Mustang,"women,denim",Черен,M,MUST-001-Ч-M,35.00,8,https://.../black.jpg
```

**ВАЖНО:** `Handle` е същият за всички редове на 1 product (така Shopify ги групира в 1 артикул).

---

## 🔧 ИМПЛЕМЕНТАЦИОННА ЛОГИКА — `csv-export.php`

### Endpoint: `/csv-export.php?product_id=X&format=woo|shopify`

```php
<?php
// 1. Проверка валидност
$product = DB::run('SELECT * FROM products WHERE id=? AND tenant_id=?', 
                   [$_GET['product_id'], $tenant_id])->fetch();

if (empty($product->main_image_url) || empty($product->ai_seo_text) 
    || empty($product->name) || $product->retail_price <= 0) {
    http_response_code(400);
    exit('CSV не е готов — артикулът няма снимка, описание, име или цена');
}

// 2. Зареждане на свързани данни
$variants = DB::run('SELECT * FROM product_variations WHERE product_id=?', 
                    [$product->id])->fetchAll();
$inventory_by_variant = DB::run(
    'SELECT variant_id, SUM(quantity) as qty FROM inventory 
     WHERE product_id=? GROUP BY variant_id', [$product->id])->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Format selection
$format = $_GET['format'] ?? 'woo';
if ($format === 'shopify') {
    $csv = generate_shopify_csv($product, $variants, $inventory_by_variant);
    $filename = $product->code . '_shopify.csv';
} else {
    $csv = generate_woocommerce_csv($product, $variants, $inventory_by_variant);
    $filename = $product->code . '_woocommerce.csv';
}

// 4. Headers + UTF-8 BOM (важно за Excel/WooCommerce кирилица)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM
echo $csv;
```

### Universal helpers

```php
function csv_escape($value) {
    if ($value === null || $value === '') return '';
    $value = (string)$value;
    if (strpbrk($value, ",\"\n\r") !== false) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

function shopify_handle($name) {
    $h = mb_strtolower($name, 'UTF-8');
    $h = str_replace(['ъ','ь','ю','я','ч','ш','щ','ж','ц','ъ','ё','é'], 
                     ['a','y','yu','ya','ch','sh','sht','zh','ts','a','e','e'], $h);
    $h = preg_replace('/[^a-z0-9]+/', '-', $h);
    return trim($h, '-');
}
```

---

## 📋 КЪДЕ СЕ ДОБАВЯТ CSV БУТОНИТЕ В UI

### Mockup ① (CTA в wizard success екран)
- **Текущ ред в success екран:** `[↓ Свали CSV для онлайн магазин]`
- **Промяна:** Стария single CSV бутон → 2 бутона (само ако `$canExportCsv === true`):
  ```
  [↓ CSV за Woo]    [↓ CSV за Shopify]
  ```
- **При невалидно състояние** (без снимка/описание): бутоните **не се показват** (вместо disabled)

### Mockup ② (Лесен mode в standalone, СЛЕД AI обработка)
- **НОВ елемент:** Под action бутоните "Запази с бял фон" + "Магия" + "Остави оригинала", добавя ред:
  ```
  ─── Готов за онлайн магазин ───
  [↓ CSV за Woo]    [↓ CSV за Shopify]
  ```
- Появява се само СЛЕД като user е tap-нал "Запази с бял фон" (artikulът е готов с AI данни)

### Mockup ③ (Разширен mode — глобален bulk export)
- **В края на главната страница** (след "Последно генерирано" thumbnails) — нова секция `↓ Експорт за онлайн магазин`
- **Briefing-section card** (q3 / зелен hue) с:
  - "📦 156 артикула готови за експорт" (динамично — броят на артикули с ai_bg_done=1 + ai_seo_done=1)
  - 1 primary бутон "Свали всички за WooCommerce" (зелен hue-matched)
  - 1 secondary бутон "↓ Свали всички за Shopify"
  - Hint: "Или избери конкретни артикули → tap на категория"
- **При tap на категория** (Дрехи/Бельо/Бижута/...) → drawer с filter + per-category CSV download
- **Endpoint:** `csv-export.php?bulk=1&format=woo|shopify` → multi-row CSV с всички готови артикули

---

## 🆕 НОВ ФАЙЛ ЗА ПИСАНЕ

```
/var/www/runmystore/
└── csv-export.php          ← НОВ (Woo + Shopify CSV generator)
```

---

## 🐛 KNOWN BUGS (за следваща сесия)

### P1 — Variations photo persistence
- **Симптом:** Wizard "Варианти" разпознава цветовете ✅, прикрепя снимки в UI ✅, но при save → НЕ се запазват в DB.
- **Засегнати:** `products.php` (variations matrix), `product-save.php`, `product_variations` таблица
- **Verification:** `SHOW COLUMNS FROM product_variations LIKE 'image%';`
- **БЛОКИРА:** Wizard bulk на варианти (Mockup ⑤) не може да работи докато bug-ът не се оправи.

### P0 от STATE_OF_THE_PROJECT (от предишна сесия)
- Navigation buttons на products home stopped working (deploy_wizard.py презаписа JS)
- Step 0 wizard call uses `renderWizard()` instead of `wizGo(1)`
- Mobile file picker частично misbehaves
- Duplicate file `ai-safety.php.` (with trailing dot) needs manual delete
- `_hasPhoto` race condition (line 3571/3574/6554)
- `renderWizard()` resets brojki на step 6
- `sold_30d=0` на line 253/260

---

## 📦 fal.ai / Gemini API НАСТРОЙКИ (още не са вкарани)

```php
// config/config.php — добавя следните:
define('FAL_AI_API_KEY', 'fal_xxxxx');
define('FAL_AI_BG_REMOVAL_MODEL', 'birefnet/v2');           // €0.05
define('FAL_AI_TRYON_MODEL', 'fal-ai/nano-banana-2');       // €0.50 (НЕ Pro!)
define('GEMINI_API_KEY_1', 'AIza...');
define('GEMINI_API_KEY_2', 'AIza...');
define('GEMINI_VISION_MODEL', 'gemini-2.5-flash');           // за auto-detect
define('GEMINI_SEO_MODEL', 'gemini-2.5-flash');              // за описания
define('STRIPE_SECRET_KEY', 'sk_live_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
```

**ВАЖНО:** API ключовете НЕ се committva в git. Държат се в `/etc/runmystore/db.env` (chmod 600), извън git, прочитат се чрез `parse_ini_file()`.

---

## 🚦 IMPLEMENTATION ORDER (S84 plan)

### Phase 1 — DB + Backend (Claude Code)
1. Run all DB migrations (8 changes)
2. Create `ai-studio-vision.php` (auto-detect)
3. Create `ai-studio-stripe-webhook.php`
4. Update `ai-studio-action.php` с 9-те actions
5. fal.ai/Gemini API wiring в config

### Phase 2 — UI (Claude here, в чата)
6. Edit `products.php`:
   - Махни старата renderStudioStep
   - Добави CTA card в success екран
   - Update WIZ_LABELS (5→4)
   - Add return handler
7. Rewrite `ai-studio.php`:
   - Лесен mode (Mockup ②)
   - Разширен mode (Mockup ③)
   - Toggle между двете
   - Wizard bulk detection (Mockup ⑤)
   - Standalone bulk banners (Mockup ③ горна част)

### Phase 3 — Pricing + Stripe (Claude Code)
8. Create `ai-studio-buy-credits.php` (Mockup ④)
9. Stripe Checkout integration
10. Webhook handler
11. Credit consumption flow

### Phase 4 — Testing
12. Test всичко с tenant_id=7 (Тихол)
13. Edge cases (0 кредити, failed payment, retry)
14. Mobile UI на iPhone/Android

---

## 📝 ФАЙЛОВЕ В ТАЗИ СЕСИЯ (за FileZilla upload)

### Mockup HTML файлове в `/mnt/user-data/outputs/`:

- `ai_studio_FINAL_v5.html` — **ФИНАЛЕН одобрен** mockup (5 phone-frames)
- `ai_studio_mockups_v3_neon.html` — V3 (3 phone-frames, prelim)
- `ai_studio_mockup_v4_buy_credits.html` — V4 (3 buy tabs, prelim)
- `UX_CONSULT_AI_STUDIO_WIZARD.md` — UX research от 5 AI системи

### Documentation в проекта:

- `SESSION_83_HANDOFF.md` (този файл) — пълна спецификация
- Reference от GitHub: `DESIGN_SYSTEM.md` v2.0 (1006 lines)
- Reference от project: `BIBLE_v3_0_CORE.md`, `BIBLE_v3_0_TECH.md`

---

## ⚙ DECISIONS LOG (как стигнахме до тук)

1. **Концепция Лесен/Разширен** — copy от chat.php / Pешо vs Owner pattern
2. **AI Studio = standalone, не отделна wizard стъпка** — UX consensus от 5 AI (Claude/ChatGPT/DeepSeek/Kimi/Gemini)
3. **CTA card СЛЕД печат на етикети** — separation of mental modes (физически магазин vs онлайн)
4. **Bulk = бял фон + описание (бързо)**, **Магия = винаги per-артикул (бавно)**
5. **5 пакета per тип (Стартов / Среден / Голям / Макси / Мега)** — Тихолова pricing logic
6. **МЕГА -50%** на бял фон, магия, описание — максимална стимулация на bulk покупки
7. **Цена per снимка винаги видна** в всеки пакет card — transparency
8. **Без trailing zeros** в UI — €0.02 не €0.020
9. **Истински Neon Glass** — conic-shine + glow + 6Q hues + pill 100px (DESIGN_SYSTEM v2.0)

---

## ⚠ NOT YET DONE (pending за S84)

- ❌ Production code (ai-studio.php rewrite, products.php edits)
- ❌ DB migrations executed
- ❌ fal.ai / Gemini API wiring
- ❌ Stripe Checkout integration
- ❌ Webhook handler
- ❌ AI Vision auto-detect imp.
- ❌ Bulk batch processing imp.
- ❌ Variant photo persistence bug fix
- ❌ Quality Guarantee retry logic

---

**Край на handoff.**

**Следваща сесия (S84):** Implementation phase — Claude Code за DB+Backend, Claude here за UI rewrite на products.php + ai-studio.php.

---

*Записано: 2026-04-27, Сесия S83*
*Reference: DESIGN_SYSTEM.md v2.0, BIBLE_v3_0_CORE.md, BIBLE_v3_0_TECH.md*
*Mockup: ai_studio_FINAL_v5.html*
