# 📖 RUNMYSTORE.AI — БИБЛИЯ v3.0 TECH

## Техническа спецификация — UI, Wizard, Pills, Inventory, Архитектура, DB, Deploy, Фази

**Версия:** 3.0 TECH
**Дата:** 17.04.2026
**Обхват:** Заменя TECHNICAL_REFERENCE_v1, TECHNICAL_ARCHITECTURE_v1, INVENTORY_HIDDEN_v3, INVENTORY_v4, S51_UI_DESIGN_SPEC, CHAT_PHP_SPEC_v7, WEATHER_INTEGRATION_v1, MASTER_TRACKER_v9.0, BETA_ROADMAP_S59 + техническите части от BIBLE_v2_1, v2_2, v2_3, v2_4 ADDITIONS.

> **ЧЕТЕ СЕ ЗАЕДНО С:** `BIBLE_v3_0_CORE.md` (логика, концепции).
>
> **ПРАВИЛО:** Code rules trump concepts. Ако тук има конкретна DB колона/функция и в CORE има концепция — техническата версия печели.
>
> # 🔴 ЗАДЪЛЖИТЕЛНО — ПРОЧЕТИ И ТРИТЕ ФАЙЛА ПРЕДИ ДА ЗАПОЧНЕШ КОЙТО И ДА Е КОД
>
> **СТАРТОВ ПРОТОКОЛ ЗА ВСЯКА НОВА СЕСИЯ — БЕЗ ИЗКЛЮЧЕНИЕ:**
>
> 1. `OPERATING_MANUAL.md`
> 2. `NARACHNIK_TIHOL_v1_1.md`
> 3. `BIBLE_v3_0_CORE.md` (концепции, закони, AI поведение)
> 4. **`BIBLE_v3_0_TECH.md`** (този файл — техническа спецификация)
> 5. `BIBLE_v3_0_APPENDIX.md` (детайлни допълнения)
> 6. Последния `SESSION_XX_HANDOFF.md`
>
> **И ТРИТЕ BIBLE ФАЙЛА (CORE + TECH + APPENDIX) СА ЕДНА БИБЛИЯ — РАЗДЕЛЕНА ЗА УДОБСТВО.**
> Не пропускай нито един. Ако нямаш достъп до някой — спри и поискай от Тихол.
>
> След като прочетеш всичко — потвърди с: *"Прочетох CORE + TECH + APPENDIX + handoff. Готов съм за S[XX]."*

---

# СЪДЪРЖАНИЕ НА TECH ДОКУМЕНТА

- **Част 1** — UI режими + Wizard (4 стъпки vs 3 accordion) + Voice Input Layer
- **Част 2** — Pills & Signals архитектура + Chat.php спецификация + Weather integration
- **Част 3** — Inventory v4 + 13 архитектурни компонента + 10 заповеди + **AI Safety Architecture (6 нива)**
- **Част 4** — DB schema + Cron + Deploy + Фази A-F (S72-S140+) + 60+ правила + Operations

---

# ══════════════════════════════════════
# ЧАСТ 1 TECH — UI, WIZARD, VOICE
# ══════════════════════════════════════

# 1. ДВА UI РЕЖИМА — SIMPLE / DETAILED

## 1.1 Philosophy

Пешо иска опростеност. Но някои искат пълен контрол.

**Решение:** две версии на главния екран. User избира.

```sql
tenants.ui_mode ENUM('simple','detailed') DEFAULT 'simple'
```

## 1.2 Simple Mode (`simple.php`)

**За кого:** Пешо, typical small shop owner, мобилно, voice-first.

### Layout (от горе надолу):

```
┌─────────────────────────────────────┐
│  RUNMYSTORE.AI  [PRO] [Разширен →]  │ ← header (42px)
├─────────────────────────────────────┤
│                                     │
│  Оборот днес: 847 €                 │ ← compact revenue (60px)
│  +12% vs вчера                      │
│                                     │
├─────────────────────────────────────┤
│                                     │
│  AI те познава на 78%               │ ← store health (28px)
│  ████████████░░░░                   │
│                                     │
├─────────────────────────────────────┤
│                                     │
│  ☆ AI · 08:02                      │ ← AI брифинг bubble
│  ┌─────────────────────────────┐    │   (за PRO) или
│  │ Добро утро! 3 неща:         │    │   ghost pill
│  │ ▎ Nike 42 свърши — 420€     │    │   (за FREE/START)
│  │ ▎ Бельо на загуба: -6€/бр   │    │
│  │ ▎ Passionata +35%           │    │
│  │ [Виж] [Коригирай] [Още 9]  │    │
│  └─────────────────────────────┘    │
│                                     │
├─────────────────────────────────────┤
│                                     │
│  ┌─────────┬─────────┬─────────┐   │
│  │ Продай  │Поръчай  │Достав.  │   │ ← 4 quick actions
│  │   ⚡    │   📦    │   🚚    │   │
│  └─────────┴─────────┴─────────┘   │
│  ┌─────────────────────────────┐   │
│  │  Стоката ми                  │   │
│  │  📦                          │   │
│  └─────────────────────────────┘   │
│                                     │
├─────────────────────────────────────┤
│  🎤 Кажи на AI...              [➤] │ ← voice FAB (56px)
└─────────────────────────────────────┘
```

### Пиксели (от S51 UI Design Spec):

| Елемент | Детайл |
|---|---|
| Background | `#030712` (почти черно) |
| Cards | `rgba(255,255,255,.02)` + border `rgba(255,255,255,.04)` |
| Primary text | `#f1f5f9` |
| Indigo accent | `#4f46e5` → `#6366f1` gradient |
| Font | Montserrat (heading), Inter (body) |
| Border-radius | 14px cards, 16px inputs, 20px overlays |
| SVG icons only | Никога emoji |

### 4 quick actions:

- **Продай** → `sale.php` (каса)
- **Поръчай** → AI overlay ("какво да поръчам") + pre-filled data
- **Доставка** → `deliveries.php` (нова доставка)
- **Стоката ми** → `products.php`

### Voice FAB (долу):

- 56×56px гумена капсула
- Gradient `#4f46e5 → #7c3aed`
- `white-space:nowrap` за "Кажи на AI..."
- ScaleY bar animation (5 бара, различни височини)

## 1.3 Detailed Mode (`chat.php`)

**За кого:** Опитни потребители, desktop, повече числа на екрана.

### Layout:

```
┌─────────────────────────────────────────┐
│ RUNMYSTORE [PRO] [← Опростен] [⚙] [→]  │ ← header
├─────────────────────────────────────────┤
│                                         │
│ ДНЕС              Основен магазин ▼    │
│ 1 250 €                        +12%    │ ← compact revenue card
│                        1 116 → 1 250   │
│ 4 продажби · марж 38%                  │
│ [Днес] 7дни  30дни  365д [Оборот]     │
│                                         │
├─────────────────────────────────────────┤
│ ТОЧНОСТ ████████████░░░░ 78%  Преброй →│ ← store health bar
├─────────────────────────────────────────┤
│                                         │
│ ☆ AI · 08:02                           │
│ ┌─ AI брифинг bubble ────────────────┐│ ← chat scroll зона
│ │ Добро утро, Тихол! 3 неща:         ││   (основната част)
│ │ ▎ Nike 42 свърши — 420€/седм       ││
│ │ ▎ Бельо на загуба: -6€/бр         ││
│ │ ▎ Passionata +35% топ печалба      ││
│ │ [Поръчай Nike] [Коригирай] [Още 9]││
│ └────────────────────────────────────┘│
│                                         │
│ [Chat history bubbles]                  │
│                                         │
├─────────────────────────────────────────┤
│ 🎤 Кажи или напиши...           [🎤][➤]│ ← input bar
├─────────────────────────────────────────┤
│ [★AI] [📦Склад] [📊Справки] [⚡Продажба]│ ← bottom nav (52px)
└─────────────────────────────────────────┘
```

### Състояния на chat:

1. **Затворен** — dashboard с AI bubble видим (30% от екрана)
2. **Отворен** — 70% overlay с WhatsApp стил, blur фон, различен тапет

### Разлики от simple:

- Има bottom nav (4 tab-а)
- Revenue card е по-голяма с period pills (Днес/7дни/30дни/365д)
- Chat заема повече място
- AI брифингът е inline в chat-а, не bubble над бутоните
- Voice не е FAB — е input bar долу

## 1.4 Toggle между режимите

```php
// В header на всеки екран:
<?php if ($tenant['ui_mode'] === 'simple'): ?>
  <a href="chat.php" class="mode-toggle">Разширен →</a>
<?php else: ?>
  <a href="simple.php" class="mode-toggle">← Опростен</a>
<?php endif; ?>
```

При тап → `UPDATE tenants SET ui_mode=? WHERE id=?` + redirect.

**Default за нови потребители:** `simple` (Пешо first).
**Default за migrated опитни:** `detailed` (ако са ползвали преди simple mode).

## 1.5 Кой модул е за кой режим

| Модул | Simple mode | Detailed mode |
|---|---|---|
| `chat.php` | Redirect → `simple.php` | Full dashboard |
| `simple.php` | Main screen | Redirect → `chat.php` |
| `products.php` | Same (работи и за двата) | Same |
| `sale.php` | Same | Same |
| `warehouse.php` | Same | Same |
| `stats.php` | Same | Same |
| `inventory.php` | Same | Same |

**Важно:** Modules (не home екрани) работят еднакво в двата режима.

---

# 2. WIZARD — PROGRESSIVE DISCLOSURE

## 2.1 Неразрешено противоречие (решава се в S75)

**Имаме 2 философии за products.php wizard:**

### Философия А — BIBLE v2.1 ADDITIONS (15.04.2026): 1 екран, 3 accordion нива

Един екран. 3 нива на прогресивно разкриване.

**Ниво 1** (винаги видим):
- Снимка (по желание)
- Наименование (+ 🎤)
- Артикулен номер (auto-generated)
- Продажна цена (+ 🎤)
- Брой (+/− stepper)

**Ниво 2** ("Добави детайли ↓"):
- Вариации (размери, цветове — inline matrix)
- Покупна цена
- Цена едро
- Баркод (камера + 🎤 + ръчно)

**Ниво 3** ("Доставчик/произход/състав ↓"):
- Доставчик
- Категория / Подкатегория
- Произход (BG/чужда)
- Състав

**Запис:** артикулът може да се запази с МИНИМУМ ниво 1.

### Философия Б — SESSION_71 (16.04.2026): 4 стъпки с Напред/Назад

**Стъпка 0 — Вид:**
```
[👕 Единичен артикул]   [🎨 С вариации]
```

**Стъпка 1 — Основни:**
- Снимка (по желание)
- Наименование (+ 🎤) — ПЪРВОТО поле
- Артикулен номер (auto)
- Продажна цена (+ 🎤)
- Баркод
- Състав (+ 🎤)
- Брой (+/− stepper, САМО при единичен)
- Мерна единица (бр/сет/кг/м/л)

Бутони: `[Запази ✓] [🖨 Печатай] [Вариации →] [➕ Нов]`

**Стъпка 2 — Вариации (ако има):**
- Variation Picker + матрица бройки (fullscreen overlay)
- Sticky header + sticky лява колона
- Всяка клетка: Бр + Мин (auto с ▲▼)
- Quick fill chips: "Всички=1/2/5/Изчисти"

**Стъпка 3 — Бизнес детайли (ВСИЧКО пожелателно):**
- Доставчик (searchable + 🎤) — ПРЕДИ категория
- Категория (searchable + 🎤)
- Подкатегория
- Покупна цена (+ 🎤)
- Цена едро (+ 🎤)

**Стъпка 4 — AI екстри:**
- AI Image Studio (маха фон, virtual try-on)
- AI SEO описание

## 2.2 Решение за библията — ФИНАЛНО: 4 СТЪПКИ

**Решение:** Философия Б (4 стъпки) е финалното решение. Край на A/B дебата.

### Защо 4 стъпки печели (consensus от 4 AI анализатори):

**1. Mobile + Voice compatibility**
Accordion + iOS keyboard = viewport reshuffle + scroll jumps. 4 стъпки = линеен UX, няма "скачащ екран".

**2. Психологически прогрес**
"Стъпка 1 от 4" дава чувство за напредък. Accordion с 3 нива дава чувство за "страница-монстър с много скрити полета".

**3. Valide per step**
Всяка стъпка може да се validate отделно. При 4 стъпки → error recovery е много по-евтина. При accordion → трудно разграничаване къде е грешка.

**4. Autosave & resume**
4 стъпки → запис между стъпките (`wizard_draft` таблица). User може да спре и продължи. Accordion = всичко-или-нищо.

**5. Voice workflow**
Пешо говори → AI попълва → потвърждава → next step. Ясна прогресия. Accordion не поддържа естествен voice flow.

### 4-те стъпки (финална структура):

**Стъпка 0 — Вид:**
```
[👕 Единичен артикул]   [🎨 С вариации]
```

**Стъпка 1 — Основни:**
- Снимка (по желание)
- Наименование (+ 🎤) — ПЪРВОТО поле
- Артикулен номер (auto)
- Продажна цена (+ 🎤)
- Баркод (+ камера + 🎤)
- Състав (+ 🎤)
- Брой (+/− stepper, САМО при единичен)
- Мерна единица (бр/сет/кг/м/л)

Бутони: `[Запази чернова] [Напред →]`

**Стъпка 2 — Вариации (ако е избран "С вариации"):**
- Variation Picker + матрица бройки (fullscreen overlay)
- Sticky header + sticky лява колона
- Всяка клетка: Бр + Мин (auto с ▲▼)
- Quick fill chips: "Всички=1/2/5/Изчисти"

Бутони: `[← Назад] [Запази чернова] [Напред →]`

**Стъпка 3 — Бизнес детайли (ВСИЧКО пожелателно):**
- Доставчик (searchable + 🎤) — ПРЕДИ категория
- Категория (searchable + 🎤)
- Подкатегория
- Покупна цена (+ 🎤)
- Цена едро (+ 🎤)

Бутони: `[← Назад] [Запази артикула ✓] [🖨 Печатай] [Напред →]`

**Стъпка 4 — AI Studio (опционално):**
- Маха фон (fal.ai birefnet)
- AI описание (SEO)
- Virtual try-on (nano-banana-pro)

Бутони: `[Готово ✓] [Добави още един]`

### Важни правила за wizard:

- **Запис на всяка стъпка** (wizard_draft таблица) — crash recovery
- **"Както предния" бутон** (в стъпка 0) — копира последните 5 артикула
- **Конвейер** — между save-ове запазва: категория, надценка, доставчик, мерна единица, покупна цена
- **Voice навсякъде** (🎤 до всяко поле освен numeric/size/color)
- **Печат след стъпка 3** — tabs: [€+лв]/[Само €]/[Без цена]

### wizard_draft таблица (за crash recovery):

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

При reopen на products.php → ако има draft → "Имаш незавършен артикул. [Продължи →] [Започни нов]".

### 🟡 Debt marker: products.php монолит

**Забелязано:** products.php е 5919 реда. Това е too big.

Когато UX промяна струва 2-3 сесии — архитектурата е проблемна.

**План за след v1 (не сега):** разделяне на products.php на:
- `products-list.php` (список/search)
- `products-wizard.php` (4-стъпковия wizard)
- `products-edit.php` (edit mode)
- `products-ajax.php` (API endpoints)

Не е блокер за сега, но е debt marker за phase 3-4.

## 2.3 Правила ОБЩИ за wizard

### 2.3.1 "Както предния" (Правило #73)

```js
localStorage['_rms_lastWizProducts'] = [
    {
        category_id, subcategory_id,
        markup_pct,
        supplier_id,
        cost_price,
        unit,
        axes[], colors[]
    },
    // ...до 5 артикула
];
```

**НИКОГА БЕЗ ПОТВЪРЖДЕНИЕ** — AI показва preview → Пешо одобрява → копира.

### 2.3.2 Конвейер (Правило #74)

Между записите на wizard-а се запазват автоматично:
- Категория
- Надценка %
- Доставчик
- Мерна единица
- Покупна цена (highlighted с "⚠ Провери цената")

Нулират се:
- Снимка
- Брой
- Вариации
- Матрица
- Име

### 2.3.3 Микрофон навсякъде (Правило #75)

🎤 бутон до всяко поле освен:
- Numeric fields (бройки) — tap +/−
- Цветове — tap chips
- Размери — tap chips

### 2.3.4 Печат етикет (Правило #76)

Отделна страница след ВСЯКА стъпка:
- Tabs: `[€+лв]` / `[Само €]` / `[Без цена]`
- **Произход** полето се попълва САМО при печат (не преди) — ако стоката е от друга държава и произходът липсва
- Списък вариации + брой −/+ per вариация
- x2 / 1:1 бутон
- `[Печатай всички (N ет.)]`

### 2.3.5 Accordion при edit (Правило #78)

- Ако полето има данни → accordion **отворен**
- Ако няма → **затворен**
- Ако Пешо изтрие данните → остава **отворен** (не скача)

---

# 3. VOICE INPUT LAYER — 3-TIER АРХИТЕКТУРА

## 3.1 Философия

**Пешо не пише. Пешо говори.** Без лимити, без ограничения.

**Voice-first не е lux feature — това е Закон №1.** Архитектурата трябва да го поддържа надеждно на 20+ езика.

### Критичен принцип: 3-tier stack

Web Speech API сам по себе си **НЕ Е** ядрото на продукта. Той е Tier 1 в многослойна архитектура.

```
┌─────────────────────────────────────────┐
│ TIER 1: Web Speech API (browser-native) │ ← Безплатно, бързо, 80% от заявките
│ ├─ bg-BG, en-US, de-DE, es-ES, it-IT    │
│ └─ confidence check                     │
└─────────────────────────────────────────┘
              ↓ if confidence < 0.75
              ↓ AND language ∈ ["слаби"]
┌─────────────────────────────────────────┐
│ TIER 2: Whisper via Groq (server-side)  │ ← $0.006/мин, 92-96% accuracy
│ ├─ sr-RS, hr-HR, sl-SI, cs-CZ, hu-HU    │
│ └─ +Domain Adaptation Prompt            │
└─────────────────────────────────────────┘
              ↓ confidence still < 0.75
┌─────────────────────────────────────────┐
│ TIER 3: Graceful Degradation Stairs     │ ← НЕ е "клавиатура"
│ ├─ Quick Tap Grid (чести продукти)      │
│ ├─ Numeric pad (за цени/количества)     │
│ └─ Keyboard (last resort, optional)     │
└─────────────────────────────────────────┘
```

### БЕЗ ЛИМИТИ НА ГОВОРЕНЕ

Пешо може да говори колкото иска. Voice е core, не cost center.

**Икономика без лимити (при 10,000 активни юзъра):**
- Web Speech покрива ~80% от заявките → €0
- Whisper trigger-ва се на ~20% от заявките (само слаби езици + low confidence)
- Средна voice употреба: 5-10 мин/ден
- Whisper cost: $0.006 × 20% × 7 мин × 30 дни = $0.25/месец/юзър
- При 10,000 юзъра: **$2,500/месец**
- Vs приход: 10,000 × €35 avg = €350,000/месец
- **Voice cost = 0.7% от приходите** ✅ Напълно OK

## 3.2 TIER 1 — Web Speech API (Browser-native)

### Кога се използва:
- Първи опит винаги
- За силни езици (EN, DE, ES, IT, FR, PT, NL) — възможно само това да е нужно
- За BG, PL, RO — първи опит, Whisper е backup

### Качество по език:

| Език | Accuracy | Tier 1 достатъчен? |
|---|---|---|
| en-US, de-DE, es-ES, it-IT, fr-FR | 95%+ | ✅ Да |
| bg-BG, ro-RO, pl-PL, pt-PT, nl-NL | 80-90% | ✅ Обикновено |
| el-GR, cs-CZ, hu-HU, sk-SK | 70-85% | ⚠️ Понякога trigger Tier 2 |
| sr-RS, hr-HR, sl-SI, bs-BA | 60-75% | ❌ Често trigger Tier 2 |

### Frontend имплементация:

```javascript
const langMap = {
    'bg': 'bg-BG', 'en': 'en-US', 'ro': 'ro-RO', 'el': 'el-GR',
    'pl': 'pl-PL', 'de': 'de-DE', 'it': 'it-IT', 'fr': 'fr-FR',
    'es': 'es-ES', 'cs': 'cs-CZ', 'hu': 'hu-HU', 'sk': 'sk-SK',
    'sr': 'sr-RS', 'hr': 'hr-HR', 'sl': 'sl-SI'
};

async function startVoice(tenantLang) {
    const recognition = new webkitSpeechRecognition();
    recognition.lang = langMap[tenantLang] || 'en-US';
    recognition.interimResults = true;
    recognition.continuous = false;

    recognition.onresult = (event) => {
        const result = event.results[event.results.length - 1];
        const transcript = result[0].transcript;
        const confidence = result[0].confidence;

        // Show transcription LIVE (Law #1A)
        showTranscription(transcript, confidence);

        if (result.isFinal) {
            if (confidence >= 0.75 || !isWeakLanguage(tenantLang)) {
                // Tier 1 succeeded
                showConfirmButton(transcript);
            } else {
                // Trigger Tier 2
                fallbackToWhisper(audioBlob, tenantLang);
            }
        }
    };
}

function isWeakLanguage(lang) {
    return ['sr', 'hr', 'sl', 'bs', 'mk'].includes(lang);
}
```

## 3.3 TIER 2 — Whisper via Groq (Server-side)

### Кога се trigger-ва:
1. Web Speech confidence < 0.75 **AND**
2. Tenant language е "слаб" (sr/hr/sl/bs/mk)
3. Или explicit: user казва "диктувай отново" 2 пъти

### Domain Adaptation Prompt (критичен за качество):

Това е **безплатен** boost — повишава accuracy с 10-15% само чрез правилен prompt.

```php
$domainPrompt = match($context) {
    'sale' => "Контекст: касова операция в магазин. Разпознавай:
               имена на продукти, количества (бр, чифт, кг, л),
               цени в евро/лева/динари, баркод числа.
               НЕ отговаряй на въпроси, само транскрибирай.",

    'product_add' => "Контекст: добавяне на нов артикул в склад.
                      Разпознавай: марки (Nike, Adidas), размери (XS, S, M, L, XL, 38, 42),
                      цветове, материали, цени.",

    'inventory' => "Контекст: инвентаризация. Разпознавай:
                    числа (брой), имена на продукти, размери.",

    default => "Транскрибирай точно казаното. Retail context."
};
```

### API integration (Groq):

```php
function transcribeWithWhisper($audioBlob, $language, $context) {
    $prompt = $this->getDomainPrompt($context);

    $response = $this->http->post('https://api.groq.com/openai/v1/audio/transcriptions', [
        'headers' => ['Authorization' => 'Bearer ' . GROQ_API_KEY],
        'multipart' => [
            ['name' => 'file', 'contents' => $audioBlob],
            ['name' => 'model', 'contents' => 'whisper-large-v3'],
            ['name' => 'language', 'contents' => $language],
            ['name' => 'prompt', 'contents' => $prompt],
            ['name' => 'response_format', 'contents' => 'verbose_json']
        ]
    ]);

    $result = json_decode($response->getBody(), true);

    return [
        'text' => $result['text'],
        'confidence' => $this->estimateConfidence($result),
        'engine' => 'whisper',
        'duration_ms' => $result['duration'] * 1000
    ];
}
```

### Why Groq (not OpenAI direct):

- **Latency:** Groq = 0.3-0.5 sec. OpenAI = 1-3 sec.
- **Cost:** Groq = $0.006/мин. OpenAI = $0.006/мин. **Same.**
- **Reliability:** Groq has better uptime for voice workloads.

## 3.4 TIER 3 — Graceful Degradation (НЕ клавиатура)

**Важно:** Клавиатурата **НЕ Е** директен fallback. Тя е **последната** опция.

### 4-степенна degradation:

**Стъпка 1 (1-во неуспешно):**
```
"Не хванах добре. Кажи по-кратко."
```

**Стъпка 2 (2-ро неуспешно):**
→ Trigger Whisper (Tier 2) automatic
→ Ако е слаб език — директно Whisper за следващи опити

**Стъпка 3 (3-то неуспешно):** Quick Tap Grid

```
┌─────────────────────────────┐
│ Чести продукти:             │
│ [Nike 42] [Adidas 38]       │
│ [Puma 41] [Converse]        │
│ [Търси друго →]             │
└─────────────────────────────┘
```

Показва топ 10 продукти за контекста (sale / add / inventory).

**Стъпка 4 (4-то неуспешно):** Numeric pad (само за числа)

Ако се очаква число (цена, количество) → показва numpad 0-9.

**Стъпка 5 (ultimate fallback):** Клавиатура

Само ако Пешо изрично каже "искам да пиша" или активира "Manual mode" в settings.

### Философия:

> *"Клавиатурата е капитулация на Закон №1. Предпочитаме 3 tap-а пред една написана дума."*

## 3.5 Transcription display (Закон №1A enforcement)

Всеки voice input **винаги** показва транскрипцията преди изпращане (CORE Закон №1A).

```
┌─────────────────────────────────┐
│ 🎤 Слушам...                    │
│                                 │
│ "две черни..."  (interim)       │
│                                 │
│ ● ЗАПИСВА                       │
└─────────────────────────────────┘

След спиране:

┌─────────────────────────────────┐
│ ✓ "две черни тениски L 40 лева" │
│                                 │
│ Confidence: 87% ✓               │
│                                 │
│ [Диктувай отново]  [Изпрати →]  │
└─────────────────────────────────┘
```

**Ако confidence < 0.80:** Текстът в жълт цвят + warning "AI може да има грешка".

**Ако confidence < 0.60:** Text в червен цвят + fuzzy matched alternatives:
```
"dve cherni tenyski" — ниска увереност

Имаше ли предвид:
• Две черни тениски (87%)
• Две червени тениски (34%)
• Нещо друго → [🎤 Диктувай отново]
```

## 3.6 Fuzzy Match с product database

След voice transcription, Pешо може да има grammatical errors. PHP **fuzzy match**:

```php
function fuzzyMatchProducts($transcript, $tenantId) {
    $products = DB::run("SELECT id, name FROM products
                         WHERE tenant_id = ? AND is_active = 1",
                         [$tenantId])->fetchAll();

    $matches = [];
    foreach ($products as $product) {
        similar_text(
            mb_strtolower($transcript),
            mb_strtolower($product['name']),
            $percent
        );

        // Plus Levenshtein за typo tolerance
        $distance = levenshtein(
            mb_strtolower($transcript),
            mb_strtolower($product['name'])
        );
        $maxLen = max(mb_strlen($transcript), mb_strlen($product['name']));
        $levScore = (1 - $distance / $maxLen) * 100;

        $combinedScore = ($percent + $levScore) / 2;

        if ($combinedScore > 60) {
            $matches[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'score' => $combinedScore
            ];
        }
    }

    usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($matches, 0, 3);  // Top 3
}
```

## 3.7 Parser алгоритъм — гласов вход → JSON

### Пример:

```
Пешо: "Две черни тениски, 40 лева"
     ↓ Tier 1 (Web Speech) OR Tier 2 (Whisper)
Transcript: "Две черни тениски, 40 лева"
     ↓ AI system prompt Layer 5 (GPT/Gemini):
```

```json
{
  "items": [
    {
      "name": "тениски",
      "color": "черни",
      "quantity": 2,
      "unit_price_bg": 40.00,
      "unit_price_eur": 20.45,
      "size": null
    }
  ]
}
```

### _bgPrice logic:

```php
function parseVoicePrice($amount_str, $tenant) {
    $amount_str = mb_strtolower($amount_str);
    $number = floatval(preg_replace('/[^0-9\.,]/', '', str_replace(',', '.', $amount_str)));

    $today = date('Y-m-d');
    $is_bg_dual = ($tenant['country_code'] === 'BG' && $today < '2026-08-08');

    if (stripos($amount_str, 'лев') !== false || stripos($amount_str, 'лв') !== false) {
        $eur = round($number / 1.95583, 2);
        return ['amount_eur' => $eur, 'source' => 'bgn_explicit', 'original' => $number];
    }

    if (stripos($amount_str, 'евр') !== false || stripos($amount_str, 'eur') !== false) {
        return ['amount_eur' => $number, 'source' => 'eur_explicit'];
    }

    if ($is_bg_dual && $number > 100) {
        return ['amount_eur' => round($number / 1.95583, 2), 'source' => 'bgn_inferred'];
    }

    return ['amount_eur' => $number, 'source' => 'eur_default'];
}
```
## 3.8 _bgPrice JavaScript — forcePrice за ценови полета

**Проблем:** SpeechRecognition често връща цена „12.90" като `"12 и 90"` или 
`"дванайсет и деветдесет"`. Дефолтният `_bgPrice()` парсър третира това като 
**a+b = 102**, защото "дванайсет и половина" = 12.5 логиката работи само при 
a∈[0..9]. За „сто и двайсет" = 120 това е правилно, за цена е грешно.

**Решение:** `_bgPrice(text, forcePrice)` — 2-ри параметър.

```javascript
function _bgPrice(t, forcePrice) {
    // ... parseFloat, cleanup ...
    if (parts.length === 2) {
        var a = word(parts[0]), b = word(parts[1]);
        if (a !== null && b !== null) {
            if (forcePrice) return parseFloat(a + '.' + String(b).padStart(2, '0'));
            if (hasStotinki && tens.indexOf(b) !== -1) 
                return parseFloat(a + '.' + String(b).padStart(2, '0'));
            if (a >= 0 && a <= 9 && tens.indexOf(b) !== -1) 
                return parseFloat(a + '.' + String(b).padStart(2, '0'));
            return a + b;  // non-price default: "сто и двайсет" = 120
        }
    }
}
```

### Правило:
**Всяко поле за цена ползва `_bgPrice(text, true)`. Никога `_bgNum()` за цени.**

Приложимо за: `retail_price`, `wholesale_price`, `cost_price`, `discount_amount`, 
всяка бъдеща валутна стойност (EUR, BGN, USD, RON, ...).

### i18n (бъдещи езици):
Всеки нов езиков parser (`_enPrice`, `_roPrice`, `_dePrice`, ...) трябва да 
имплементира същия `forcePrice` флаг. Pattern:

```javascript
function _enPrice(t, forcePrice) { /* 12 and 90 → 12.90 if forcePrice */ }
function _roPrice(t, forcePrice) { /* 12 și 90 → 12.90 if forcePrice */ }
```

Router-ът избира parser по `tenant.lang` и винаги подава `forcePrice=true` 
за ценови полета.

### Имплементация:
- `products.php` → `_wizMicApply()` case `retail_price` / `wholesale_price` 
- Всеки нов voice handler за цена **трябва** да вика с `forcePrice=true`.

### Commit: S73.B.2d (18.04.2026)

## 3.9 Size mapper

```php
$sizeAliases = [
    'ес' => 'S', 'ем' => 'M', 'ел' => 'L', 'екселъл' => 'XL',
    'двойно ел' => 'XXL',
    'малък' => 'S', 'среден' => 'M', 'голям' => 'L',
    'трийсет и осем' => '38', '38' => '38',
    // ...
];
```

Voice: "Купих тениска голяма" → size='L'.

## 3.10 Color mapper

```php
$colorAliases = [
    'черни' => 'Черен', 'черна' => 'Черен',
    'бели' => 'Бял', 'бяла' => 'Бял',
    'червени' => 'Червен',
    'сини' => 'Син',
    'зелени' => 'Зелен',
    // ...
];
```

## 3.11 Voice overlay design (одобрен дизайн)

```
┌─────────────────────────────────────┐
│ [backdrop-filter: blur(8px)]        │
│                                     │
│  ┌─────────────────────────────┐    │
│  │  ● ЗАПИСВА                  │    │ ← rec-box, bottom floating
│  │                             │    │
│  │  "две черни тениски..."     │    │ ← transcript
│  │                             │    │
│  │  [Изпрати →]                │    │
│  └─────────────────────────────┘    │
│                                     │
└─────────────────────────────────────┘
```

### CSS spec:

```css
.rec-ov {
    position: fixed;
    inset: 0;
    background: rgba(3, 7, 18, 0.6);
    backdrop-filter: blur(8px);
    z-index: 9000;
    /* НЕ fullscreen block — blur overlay само */
}

.rec-box {
    position: absolute;
    bottom: 60px;
    left: 16px;
    right: 16px;
    padding: 20px;
    background: rgba(20, 24, 40, 0.95);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 20px;
    box-shadow: 0 0 40px rgba(99, 102, 241, 0.2);
}

.rec-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
}

.rec-indicator.recording .dot {
    background: #ef4444;
    animation: pulse 1s infinite;
}

.rec-indicator.done .dot {
    background: #22c55e;
}
```

### Поведение:

- **REC ON:** червена пулсираща точка + "● ЗАПИСВА"
- **REC DONE:** зелена точка + "✓ ГОТОВО"
- Transcript показва се в реално време (interimResults=true)
- "Изпрати →" бутон само когато има текст
- Тап на blur фона → затваря без изпращане

### Правило #29 (микрофонът не блокира екрана):

- Voice overlay НЕ е full-screen
- Blur overlay + floating box
- Пешо може да види dashboard-а долу под blur-а
- **Прилага се за ВСИЧКИ модули** — не само chat.php

## 3.8 Voice fallback (deprecated — use 3-tier архитектурата)

⚠️ **DEPRECATED:** Старата "auto-show keyboard" логика беше заменена със:

- **3-tier voice stack** (виж §3.1-3.4)
- **Graceful Degradation Stairs** (Quick Tap Grid → Numeric pad → Keyboard last resort)
- **Domain Adaptation Prompt** за Whisper

Старият подход "fallback към keyboard след 2 неуспеха" нарушаваше Закон №1 (Пешо не пише).

### Manual mode toggle (запазено):

В Settings:
```
[ ] Винаги пиша ръчно (изключи микрофона)
```

Опитни потребители или такива в шумна среда могат да изключат voice напълно.

### Smart noise detection (запазено):

```javascript
// Ако шумовото ниво е високо → suggest Quick Tap Grid (НЕ keyboard)
function detectAmbientNoise() {
    navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
        const audioCtx = new AudioContext();
        const analyser = audioCtx.createAnalyser();
        const source = audioCtx.createMediaStreamSource(stream);
        source.connect(analyser);

        const buffer = new Uint8Array(analyser.frequencyBinCount);
        analyser.getByteFrequencyData(buffer);
        const avg = buffer.reduce((a,b) => a+b) / buffer.length;

        if (avg > 100) { // Шумно
            showHint("Шумно е — voice може да греши. Покажи Quick Tap Grid?");
        }

        stream.getTracks().forEach(t => t.stop());
    });
}
```

# 4. СПОДЕЛЕНИ UI КОМПОНЕНТИ

## 4.1 Bottom nav (detailed mode only)

```html
<nav class="bottom-nav">
  <a href="chat.php" class="bottom-nav-tab active">
    <svg>...</svg>
    <span>AI</span>
  </a>
  <a href="warehouse.php" class="bottom-nav-tab">
    <svg>...</svg>
    <span>Склад</span>
  </a>
  <a href="stats.php" class="bottom-nav-tab">
    <svg>...</svg>
    <span>Справки</span>
  </a>
  <a href="sale.php" class="bottom-nav-tab">
    <svg>...</svg>
    <span>Продажба</span>  <!-- НЕ "Въвеждане" -->
  </a>
</nav>
```

### CSS:

```css
.bottom-nav {
    height: 52px;
    background: rgba(3, 7, 18, 0.97);
    border-top: 1px solid rgba(255,255,255,0.04);
    display: flex;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 100;
}

.bottom-nav-tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.18);
    text-decoration: none;
    font-size: 10px;
}

.bottom-nav-tab.active {
    color: #a5b4fc;
}

.bottom-nav-tab svg {
    width: 22px;
    height: 22px;
    stroke-width: 1.5;
    /* Stroke icons only, no fill */
}
```

**Правило:** SVG иконки, БЕЗ glow ефекти, БЕЗ emoji. Label "Продажба" (не "Въвеждане").

## 4.2 AI Float Button (voice FAB)

```html
<button class="ai-wave-bar" onclick="openVoiceOverlay()">
  <div class="waves">
    <span></span><span></span><span></span><span></span><span></span>
  </div>
  <span class="label">Кажи на AI</span>
</button>
```

### CSS:

```css
.ai-wave-bar {
    position: fixed;
    bottom: 80px; /* над bottom nav */
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    border-radius: 28px;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
    white-space: nowrap; /* ВАЖНО */
}

.waves {
    display: flex;
    align-items: center;
    gap: 2px;
    height: 18px;
}

.waves span {
    width: 2px;
    background: white;
    border-radius: 1px;
    animation: wave 1s ease-in-out infinite;
}

.waves span:nth-child(1) { height: 5px;  animation-delay: 0.0s; }
.waves span:nth-child(2) { height: 10px; animation-delay: 0.1s; }
.waves span:nth-child(3) { height: 14px; animation-delay: 0.2s; }
.waves span:nth-child(4) { height: 10px; animation-delay: 0.3s; }
.waves span:nth-child(5) { height: 5px;  animation-delay: 0.4s; }

@keyframes wave {
    0%, 100% { transform: scaleY(1); }
    50%      { transform: scaleY(1.5); }
}
```

## 4.3 Signal Detail Overlay (НЕ chat!)

Когато Пешо тапне на signal — **показва се detail overlay**, НЕ отваря чат.

```
┌─────────────────────────────────────┐
│ [X]                                 │ ← header
│                                     │
│ 3 артикула на нула                  │ ← signal title
│                                     │
│ • Nike Air Max 42                   │ ← list
│ • Adidas Superstar 38               │
│ • Puma Smash 41                     │
│                                     │
│ 🟡 Губиш ~32 €/ден                  │
│                                     │
│ [Добави за поръчка →]              │ ← action buttons
│ [Виж още 5 сигнала]                │
└─────────────────────────────────────┘
```

### Action бутони идват от DB:

```sql
ai_insights:
  action_label VARCHAR(100),    -- "Добави за поръчка"
  action_type VARCHAR(50),      -- "order_draft"
  action_url VARCHAR(255),      -- "order_draft:nike_42,adidas_38,puma_41"
  action_data JSON              -- additional params
```

### Типове actions:

- `order_draft` → отваря чернова поръчка с pre-filled артикули
- `navigate:url` → отваря конкретен екран
- `filter:products?zone_id=X` → filter в products
- `mark_done:insight_id` → маркира insight като handled

---

# 5. SIGNAL BROWSER — 5 КАТЕГОРИИ

Когато Пешо тапне "Виж още N сигнала" → Signal Browser overlay:

```
┌─────────────────────────────────────┐
│ [X]  Всички сигнали                 │
├─────────────────────────────────────┤
│ [Продажби] [Склад] [Продукти]       │ ← category tabs
│ [Финанси] [Разходи]                 │
├─────────────────────────────────────┤
│                                     │
│ 🔴 3 артикула на нула               │ ← signal cards
│ 🟡 7 артикула застояват             │
│ 🔵 Nike 42 hit — 4 продажби днес    │
│ ...                                 │
│                                     │
└─────────────────────────────────────┘
```

### 5-те категории:

1. **Продажби** — днешни, тренд, топ артикули, staff
2. **Склад** — на нула, zombie, нова стока
3. **Продукти** — без снимка, без доставчик, без цена
4. **Финанси** — marж, cash flow, ДДС (owner only)
5. **Разходи** — под себестойност, високи отстъпки (owner only)

Всяка категория има свой SQL query в `compute-insights.php`.

---

## КРАЙ НА TECH ЧАСТ 1

Следва **TECH Част 2** — Pills & Signals архитектура (3 слоя данни: realtime/hourly/nightly), Chat.php пълна спецификация (dashboard + 70% overlay), Weather integration (Open-Meteo, 30-дневна прогноза, температурни прагове за мода).

Общо редове TECH: ~650


---

# ══════════════════════════════════════
# ЧАСТ 2 TECH — PILLS, CHAT, WEATHER
# ══════════════════════════════════════

# 6. PILLS & SIGNALS — АРХИТЕКТУРА

## 6.1 Фундаментално разграничение

- **Pills** = визуален индикатор (число + цвят), БЕЗ текст
- **Signals** = pill + текстов съвет + action бутон

### Примери:

**Pill:**
```
[3 на нула]  [7 zombie]  [12 без снимка]
```

**Signal:**
```
🔴 3 артикула на нула
   Nike 42, Adidas 38, Puma 41
   Губиш ~32 €/ден
   [Добави за поръчка →]
```

## 6.2 3-слойна архитектура

### Слой 1 — Realtime (150ms)

**Когато:** при отваряне на екран (products.php, sale.php, chat.php).

**Как:** 15-20 PHP SQL заявки в paralell.

**Какво:** числа за pills:
- Артикули на нула
- Артикули под минимум
- Zombie count
- Днешни продажби/оборот
- Топ продавачка днес

**Cost:** 0 Gemini → 0 €. Само MySQL query.

### Слой 2 — Hourly cron (`cron-hourly.php`)

**Когато:** на всеки час.

**Какво изчислява:**
- Staff КПД (margin killer, upsell ability, etc)
- Lost demand aggregation (от search_log → lost_demand)
- Updated ai_insights таблица (15-минутен freshness)
- Basket analysis update

**Запис в:** `ai_insights` таблицата.

### Слой 3 — Nightly cron (`cron-nightly.php`)

**Когато:** 03:00 всеки ден.

**Какво изчислява:**
- YoY сравнения (година назад)
- 90-дневни тренд линии
- Сезонни pattern-и
- Cross-store агрегации (БИЗНЕС план)
- VIP клиенти "не са идвали X дни"
- Weather forecast cache refresh

**Запис в:** `ai_insights`, `store_patterns`, `weather_forecast`.

## 6.3 Morning & Evening briefings

### Morning briefing (`cron-morning.php` — 08:00 local)

За **PRO** потребители. Push notification:

```
☆ Добро утро, Пешо!
  3 неща за днес:
  • Nike 42 свърши (420 €/седм)
  • Бельо под себестойност (-6 €/бр)
  • Passionata +35% топ печалба
```

За **START** — без briefing.
За **FREE** — ghost notification 1×/седмица.

### Evening report (`cron-evening.php` — 21:00 local)

За **PRO** потребители:

```
☆ Вечерен отчет
  Днес: 1 240 €
  Топ: Nike (4 продажби)
  Марж: 38% (нормален)
  Проблем: Бельо на загуба (-6 €)
  Утре е петък — провери витрината.
```

## 6.4 ai_insights таблицата

```sql
CREATE TABLE ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    topic_id VARCHAR(50) NOT NULL,          -- напр. "zero_stock_nike_42"
    topic_category VARCHAR(30) NOT NULL,    -- "stock", "zombie", "price", etc
    urgency ENUM('critical','warning','info','passive') DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    body TEXT,
    value_numeric DECIMAL(12,2),            -- главното число
    related_product_ids JSON,               -- [42, 58, 103]
    action_label VARCHAR(100),              -- "Добави за поръчка"
    action_type VARCHAR(50),                -- "order_draft", "navigate", "filter"
    action_url VARCHAR(255),
    action_data JSON,
    module VARCHAR(30),                     -- "home", "products", "sale"
    plan_required ENUM('free','start','pro') DEFAULT 'pro',
    role_required ENUM('owner','manager','seller','all') DEFAULT 'all',
    confidence_class ENUM('A','B','C','D','E') DEFAULT 'B',
    confidence_score DECIMAL(4,3),          -- 0.000-1.000
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT NULL,      -- когато се resolve-не
    resolved_at TIMESTAMP DEFAULT NULL,
    INDEX idx_fetch (tenant_id, store_id, resolved_at, created_at),
    INDEX idx_topic (tenant_id, topic_id)
);
```

## 6.5 ai_shown таблицата (cooldown)

```sql
CREATE TABLE ai_shown (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    insight_id INT NOT NULL,
    topic_id VARCHAR(50) NOT NULL,
    topic_category VARCHAR(30),
    shown_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_action ENUM('ignored','accepted','rejected','clicked_action') DEFAULT 'ignored',
    FOREIGN KEY (insight_id) REFERENCES ai_insights(id),
    INDEX idx_cooldown (tenant_id, user_id, topic_id, shown_at),
    INDEX idx_category (tenant_id, user_id, topic_category, shown_at)
);
```

## 6.6 Cooldown периоди

```php
function getCooldownHours($topic_category) {
    return match($topic_category) {
        'zero_stock', 'below_cost' => 12,        // Ежедневни — 12ч cooldown
        'zombie', 'missing_photo' => 168,        // Седмица
        'staff_kpd' => 168,                      // Седмично показване
        'seasonal_shift' => 720,                 // Месец
        'vip_absence' => 336,                    // 14 дни
        default => 72                            // 3 дни default
    };
}
```

**Правило:** един topic_id не се показва два пъти в рамките на cooldown периода.

## 6.7 getInsightsForModule() helper

```php
function getInsightsForModule($tenant_id, $store_id, $user_id, $module, $plan, $role) {
    // Вземи активни insights за модула
    $sql = "SELECT * FROM ai_insights
            WHERE tenant_id=? AND store_id=?
              AND module=?
              AND resolved_at IS NULL
              AND (expires_at IS NULL OR expires_at > NOW())
              AND plan_required <= ?  -- enum ordering
              AND (role_required='all' OR role_required=?)
            ORDER BY
              FIELD(urgency, 'critical','warning','info','passive'),
              created_at DESC
            LIMIT 20";

    $insights = DB::run($sql, [$tenant_id, $store_id, $module, $plan, $role])->fetchAll();

    // Филтрирай според cooldown
    $filtered = [];
    foreach ($insights as $ins) {
        if (shouldShowInsight($tenant_id, $user_id, $ins)) {
            $filtered[] = $ins;
        }
    }

    // Максимум 2 critical, 3 warning, 3 info
    $counts = ['critical'=>0, 'warning'=>0, 'info'=>0];
    $limits = ['critical'=>2, 'warning'=>3, 'info'=>3];
    $result = [];
    foreach ($filtered as $ins) {
        if ($counts[$ins['urgency']] < $limits[$ins['urgency']]) {
            $result[] = $ins;
            $counts[$ins['urgency']]++;
        }
    }

    // 1/4 отваряния = тишина
    if (!shouldSpeakToday($user_id)) {
        return [];
    }

    return $result;
}
```

## 6.8 build-prompt.php — full data dump strategy

**Layer 7 — AI Context Block** (за PRO план, в chat-send.php):

```php
// Взема ~44K chars от pre-computed ai_insights + status flags per product
$context = "\n\n[CONTEXT — не цитирай директно]\n";

// Добавяме PRE-COMPUTED status flags
foreach ($products as $p) {
    $flags = [];
    if ($p['quantity'] == 0 && $p['sold_last_30d'] > 0) $flags[] = 'ZERO_STOCK';
    if ($p['days_without_sale'] > 45) $flags[] = 'ZOMBIE';
    if ($p['quantity'] > 0 && $p['quantity'] <= $p['min_quantity']) $flags[] = 'LOW_STOCK';
    if ($p['retail_price'] < $p['cost_price']) $flags[] = 'SELLING_AT_LOSS';

    $context .= "- {$p['name']} (code: {$p['code']})";
    if ($flags) $context .= " [" . implode(',', $flags) . "]";
    $context .= ": {$p['quantity']} бр, цена {$p['retail_price']} {$currency}\n";
}

// Full data dump за 300 продукта = ~44K chars = ~11K tokens
```

**Ограничение:** `maxOutputTokens=4000`.

## 6.9 Правило за списък (Правило #30)

- ≤5 артикула → всички в отговора
- \>5 артикула → топ 5 + "...и още N" + action бутон "Виж всички"

---

# 7. CHAT.PHP — ПЪЛНА СПЕЦИФИКАЦИЯ

## 7.1 Концепция

`chat.php` е **главният екран** в detailed mode.

**Две състояния:**
- **Затворен** — dashboard с AI bubble видим (30% от екрана)
- **Отворен** — 70% overlay с WhatsApp стил, blur фон

Чатът **НЕ е скрит зад бутон**. Той е **видим** на dashboard-а като AI bubble.

Тап на input бара → разпъва до 70%.

## 7.2 Секции от горе надолу

### 7.2.1 Header (42px)

```
┌─ RUNMYSTORE.AI ──── [PRO] [← Опростен] [⚙] [→] ─┐
```

- Ляво: `RUNMYSTORE.AI` — 10px, font-weight:700, color `rgba(165,180,252,.5)`, letter-spacing:.5px
- Дясно: Plan badge → "← Опростен" toggle → Settings → Logout
- Plan badge colors: PRO=`#c084fc`, START=`#818cf8`, FREE=`#9ca3af`

### 7.2.2 Revenue card

```
┌──────────────────────────────────────┐
│ ДНЕС                 Основен магазин │
│ 1 250  EUR                    +12%  │
│                          1 116→1 250│
│ 4 продажби · марж 38%              │
│ [Днес] 7дни  30дни  365д  [Оборот] Печалба │
└──────────────────────────────────────┘
```

- Фон: `rgba(255,255,255,.02)`, border `rgba(255,255,255,.04)`, radius 14px
- Число: 28px, font-weight:800, `#f1f5f9`, letter-spacing:-1px
- Валута: 11px, `#4b5563`
- % промяна: 16px, font-weight:800 — зелен(+), червен(-)
- Сравнение: 8px, `#4b5563` ("1 116 → 1 250")
- Period pills: Днес/7дни/30дни/365д

### 7.2.3 Store Health bar

```
ТОЧНОСТ ████████████░░░░ 78%  Преброй →
```

- Bar height: 4px, radius 2px
- Fill gradient: червено → жълто → зелено
- Процент цвят по стойност

### 7.2.4 Chat scroll zone (основната част)

**AI брифинг bubble** (първо съобщение):

```
☆ AI · 08:02
┌─────────────────────────────────────────┐
│ Добро утро, Тихол! Ето какво е важно:  │
│                                         │
│ ▎ Nike Air Max 42 свърши               │ ← червена лента
│ ▎ 8 прод./мес · ~420 €/седм пропуснати │
│                                         │
│ ▎ Комплект бельо на загуба             │ ← жълта лента
│ ▎ 45 € → 39 € = −6 €/бр              │
│                                         │
│ ▎ Passionata +35%                      │ ← зелена лента
│ ▎ Топ печалба: 230 € марж/30д         │
│                                         │
│ [Поръчай Nike] [Коригирай цена] [Още 9]│
└─────────────────────────────────────────┘
```

**CSS:**

```css
.ai-bubble {
    max-width: 90%;
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.05);
    border-radius: 14px 14px 14px 3px;
    padding: 12px 14px;
    margin: 8px 0;
}

.signal-card {
    border-radius: 8px;
    padding: 8px 10px;
    margin: 7px 0 3px;
    border-left: 3px solid;
}

.signal-card.critical {
    border-left-color: #ef4444;
    background: rgba(239,68,68,.04);
}
.signal-card.critical .title { color: #fca5a5; font-size: 11px; font-weight: 600; }

.signal-card.warning {
    border-left-color: #fbbf24;
    background: rgba(251,191,36,.03);
}
.signal-card.warning .title { color: #fcd34d; }

.signal-card.info {
    border-left-color: #4ade80;
    background: rgba(34,197,94,.03);
}
.signal-card.info .title { color: #86efac; }

.action-btn {
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 600;
    color: #a5b4fc;
    border: 1px solid rgba(99,102,241,.15);
    margin-right: 4px;
}
```

**Action бутони** (максимум 3):

```php
function insightBtns($insight) {
    $tid = $insight['topic_id'] ?? '';
    $btns = [];
    if (str_contains($tid, 'zero_stock'))    $btns[] = ['t'=>'Покажи на нула', 'q'=>'Кои артикули са на нула?'];
    if (str_contains($tid, 'below_cost'))    $btns[] = ['t'=>'Коригирай цена', 'q'=>'Кои артикули се продават под себестойност?'];
    if (str_contains($tid, 'zombie'))        $btns[] = ['t'=>'Покажи zombie', 'q'=>'Покажи zombie стоката'];
    if (str_contains($tid, 'no_photo'))      $btns[] = ['t'=>'Без снимка', 'q'=>'Кои артикули нямат снимка?'];
    if (str_contains($tid, 'top_profit'))    $btns[] = ['t'=>'Топ печалба', 'q'=>'Най-печелившите артикули?'];
    if (empty($btns)) $btns[] = ['t'=>'Разкажи повече', 'q'=>$insight['title']];
    return $btns;
}
```

**Ghost pill** (за FREE/START):

```
☆ AI · 08:02
┌─────────────────────────────────────┐
│ Добро утро! AI има съвет за теб... │
│ [🔒 PRO]                           │ ← dashed border
└─────────────────────────────────────┘
```

- Border: `1px dashed rgba(168,85,247,.2)`
- Color: `rgba(168,85,247,.4)`
- Tap → showToast('Включи PRO за AI съвети')
- FREE = 1/седмица, START = 1/ден

**При тишина (1/4 отваряния или нов user):**

```
☆ AI · 08:02
┌─────────────────────────────────────┐
│ Добро утро! Попитай каквото искаш  │
│ — говори или пиши.                 │
└─────────────────────────────────────┘
```

### 7.2.5 Input бар (долу)

```
┌─ ||||| Кажи или напиши...  [🎤] [➤] ─┐
```

- Фон: `rgba(255,255,255,.03)`, border `rgba(255,255,255,.06)`, radius 16px
- AI waves: 5 бара (2px wide), heights 5/10/14/10/5px
- Текст: 11px, `#374151`, placeholder
- Mic button: 34px кръг, gradient `#4f46e5 → #7c3aed`
- Send button: 32px кръг, disabled докато няма текст
- **ТАП на целия input бар → openChat()** (разпъва overlay)
- Tap на mic → openChat() + toggleVoice()

### 7.2.6 Bottom nav (52px)

```
[★ AI]  [📦 Склад]  [📊 Справки]  [⚡ Продажба]
```

## 7.3 Отворен чат — 70% overlay

### Поведение:

1. Overlay: `rgba(3,7,18,.7)` + `backdrop-filter: blur(6px)`
2. Chat panel = долните 70%
3. Горните 30% = замъглен dashboard (виждаш числата през blur)
4. `history.pushState()` — back бутон затваря чата
5. Тап на blur фона → `closeChat()`

### Chat panel:

```css
.chat-overlay-panel {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 70vh;
    background: rgba(8,10,24,.98);
    border-radius: 20px 20px 0 0;
    box-shadow: 0 -8px 40px rgba(99,102,241,.15);
    animation: slideup 0.25s ease;

    /* РАЗЛИЧЕН ТАПЕТ от dashboard! */
    background-image:
        radial-gradient(ellipse at 25% 15%, rgba(99,102,241,.04) 0%, transparent 55%),
        radial-gradient(ellipse at 75% 85%, rgba(139,92,246,.03) 0%, transparent 50%);
}

@keyframes slideup {
    from { transform: translateY(30px); opacity: 0; }
    to   { transform: translateY(0); opacity: 1; }
}
```

### Sync messages:

При `openChat()` → `syncMessages()`:
- Копира всички `.msg-group` елементи от dashboard chat-scroll в overlay
- Clone-ва action бутоните, но ги пренасочва от `openChatQ()` → `sendAutoQ()`
- ScrollToBottom

## 7.4 chat-send.php

Endpoint: `POST /chat-send.php`

```php
<?php
// Payload: {message: "...", conversation_id?: "..."}

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'];

// Failover chain: Gemini KEY1 → KEY2 → OpenAI
$apis = [
    ['provider' => 'gemini', 'key' => GEMINI_API_KEY],
    ['provider' => 'gemini', 'key' => GEMINI_API_KEY_2],
    ['provider' => 'openai', 'key' => OPENAI_API_KEY]
];

$reply = null;
foreach ($apis as $api) {
    $reply = callAPI($api, $message, $system_prompt);
    if ($reply) break;
    // if 429 rate limit → next
}

if (!$reply) {
    echo json_encode(['error' => 'AI недостъпен сега, опитай пак']);
    exit;
}

// Auto action buttons (code-driven, не AI-generated)
$buttons = extractActionButtons($reply);

echo json_encode([
    'reply' => $reply,
    'buttons' => $buttons,
    'conversation_id' => $conv_id
]);
```

## 7.5 Greetings по час

```php
$hour = (int)date('H');
$greeting = match(true) {
    $hour >= 5 && $hour < 12  => 'Добро утро',
    $hour >= 12 && $hour < 18 => 'Добър ден',
    default                    => 'Добър вечер'
};
if ($user_name) $greeting .= ', ' . htmlspecialchars($user_name);
$greeting .= '!';
```

**i18n rule:** Всички 3 варианта минават през `t()` helper — не hardcoded.

---

# 8. WEATHER INTEGRATION

## 8.1 Концепция

AI вижда **30 дни напред** прогноза → свързва температура/дъжд с конкретна стока.

Пример: *"Следващите 10 дни са над 28°C — летните рокли ще тръгнат. Имаш 8 бр, обмисли 20-25."*

## 8.2 API: Open-Meteo

| Свойство | Стойност |
|---|---|
| Цена | **БЕЗПЛАТНО** |
| API ключ | НЕ ТРЯБВА |
| Rate limit | Няма строг |
| Forecast endpoint | `api.open-meteo.com/v1/forecast` |
| Historical | `archive-api.open-meteo.com/v1/archive` |
| Реална прогноза | 16 дни |
| Historical avg | Дни 17-30 (средно от 5 години) |

### Пример заявка:

```
https://api.open-meteo.com/v1/forecast
  ?latitude=42.70&longitude=23.32
  &daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max,
         precipitation_sum,wind_speed_max,weather_code,uv_index_max
  &timezone=auto
  &forecast_days=16
```

## 8.3 DB schema

### stores — нови колони:

```sql
ALTER TABLE stores
  ADD COLUMN latitude  DECIMAL(9,6) DEFAULT NULL,
  ADD COLUMN longitude DECIMAL(9,6) DEFAULT NULL;
```

### weather_forecast — нова таблица:

```sql
CREATE TABLE weather_forecast (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  forecast_date DATE NOT NULL,
  temp_max DECIMAL(4,1),
  temp_min DECIMAL(4,1),
  precipitation_prob TINYINT,
  precipitation_mm DECIMAL(5,1),
  wind_speed_max DECIMAL(4,1),
  weather_code SMALLINT,
  uv_index_max DECIMAL(3,1),
  source ENUM('forecast','historical_avg'),
  fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (store_id, forecast_date)
);
```

### WMO Weather Codes:

| Код | Значение | Ефект |
|---|---|---|
| 0 | Ясно | Нормален/висок трафик |
| 1-3 | Облачно | Нормален |
| 45,48 | Мъгла | Малко по-слаб |
| 51-57 | Ръми | 10-15% по-малко хора |
| 61-67 | Дъжд | 20-30% по-малко |
| 71-77 | Сняг | 30-50% по-малко |
| 80-82 | Пороен дъжд | 25-40% по-малко |
| 95-99 | Гръмотевична буря | Силно намален трафик |

## 8.4 Температурни прагове за мода

| Max температура | Сезон | Какво се търси |
|---|---|---|
| < 5°C | Дълбока зима | Пуховки, ботуши, шалове, ръкавици |
| 5-10°C | Зима | Палта, дебели пуловери, зимни обувки |
| 10-15°C | Ранна пролет / Късна есен | Якета, жилетки, преходни обувки |
| 15-20°C | Пролет / Есен | Блузи с дълъг ръкав, леки якета, дънки |
| 20-25°C | Топла пролет / Ранна есен | Тениски, къси панталони, леки рокли |
| 25-30°C | Лято | Плажни рокли, сандали, слънчеви очила |
| \> 30°C | Горещо лято | Минимално облекло, шапки, UV защита |

### Преходни зони (най-важните):

Когато температурата **ПРЕМИНАВА праг** (напр. 14 → 22°C за 5 дни):
- AI казва: *"Температурите скачат от 14 на 22°C. Летните рокли ще тръгнат. Имаш 8 бр — обмисли 20-25."*
- AI НЕ казва: *"Поръчай летни рокли!"* (забранен императив)

## 8.5 weather-cache.php (cron)

**Setup:**
```bash
# Всеки ден в 6:00
0 6 * * * php /var/www/runmystore/weather-cache.php >> /var/log/runmystore/weather.log 2>&1
```

**Flow:**
1. Взима всички магазини с lat/lng
2. Групира по уникална локация (2 магазина в София = 1 API call)
3. Fetch 16 дни реална прогноза
4. Fetch historical average за дни 17-30
5. UPSERT в `weather_forecast`
6. 200ms пауза между заявки

## 8.6 Helper функции

```php
// Взима прогноза за магазин (30 дни)
$forecast = getWeatherForecast($store_id, $tenant_id, 30);

// Връща текстово обобщение за AI prompt
$summary = getWeatherSummary($store_id, $tenant_id, 14);
```

## 8.7 build-prompt.php Layer 8

```php
require_once __DIR__ . '/weather-cache.php';
$weatherBlock = getWeatherSummary($store_id, $tenant_id, 14);
$prompt .= "\n\n" . $weatherBlock;
```

**Пример какво вижда Gemini:**

```
WEATHER FORECAST (next 14 days):
Week 1: avg 8-18°C, 2 rainy days
Week 2: avg 12-22°C, 0 rainy days
TREND: Warming significantly (+4°C). Summer items will start moving.

Detailed (next 5 days):
  Mon 14/04: 7-17°C Cloudy
  Tue 15/04: 9-19°C Clear
  Wed 16/04: 10-21°C Clear
  Thu 17/04: 8-16°C Rain RAIN 75%
  Fri 18/04: 6-14°C Showers RAIN 60%
```

## 8.8 Onboarding — как се настройва локация

1. Пешо казва града на магазина (в signup)
2. Геокодиране → lat/lng (Google Geocoding или hardcoded BG таблица)
3. Или: карта в Settings, Пешо слага пин

### BG hardcoded координати:

```php
$bgCities = [
    'София'          => [42.6977, 23.3219],
    'Пловдив'        => [42.1354, 24.7453],
    'Варна'          => [43.2141, 27.9147],
    'Бургас'         => [42.5048, 27.4626],
    'Русе'           => [43.8356, 25.9657],
    'Стара Загора'   => [42.4258, 25.6345],
    'Плевен'         => [43.4170, 24.6067],
    'Велико Търново' => [43.0757, 25.6172],
    'Благоевград'    => [42.0116, 23.0944],
    'Шумен'          => [43.2712, 26.9292],
];
```

## 8.9 Weather insights функции (в compute-insights.php)

```php
insightWeatherWarmingStock($pdo, $tid, $sid) { ... }
insightWeatherCoolingStock($pdo, $tid, $sid) { ... }
insightWeatherRainTraffic($pdo, $tid, $sid) { ... }
insightWeatherSeasonalShift($pdo, $tid, $sid) { ... }
// ...до weather_025
```

## 8.10 План за внедряване

| Стъпка | Какво | Кога |
|---|---|---|
| 1 | SQL миграция (stores + weather_forecast) | S53+ |
| 2 | weather-cache.php + cron | S53+ |
| 3 | Ръчно lat/lng за тест магазини | S53+ |
| 4 | getWeatherSummary() → Layer 8 | S53+ |
| 5 | weather_001-025 теми в JSON | S53+ |
| 6 | compute-insights.php weather функции | S53-54 |
| 7 | Автоматичен geocoding от store.city | S55+ |
| 8 | Карта в Settings за точно позициониране | S56+ |

## 8.11 Ценова стойност

**Weather е PRO план функция.** FREE и START не виждат weather insights.

**Силен selling point:**
- *"RunMyStore.ai гледа прогнозата вместо теб и ти казва КАКВО ДА ПОРЪЧАШ"*
- Конкурентите (Shopify, Square, Lightspeed) НЕ правят това
- Това е AI intelligence, не просто POS

---

## КРАЙ НА TECH ЧАСТ 2

Следва **TECH Част 3** — Inventory v4 (2 режима fast/full, counting flow, confidence decay, zone walk), 13 архитектурни компонента, 10 заповеди, **AI Safety Architecture (6 нива с Kimi корекции)**, Recovery Mode, Trust Decay.

Общо редове TECH: ~1400


---

# ══════════════════════════════════════
# ЧАСТ 3 TECH — INVENTORY, АРХИТЕКТУРА, AI SAFETY
# ══════════════════════════════════════

# 9. INVENTORY V4 — "СКРИТИ ПАРИ"

## 9.1 Философия

Инвентаризацията не е брутална "преброй всичко". Тя е **"лов на скрити пари"**.

Пешо вижда:
```
"Преброй рафт 3 — 8 артикула.
AI ще научи точните ти бройки → по-добри съвети."
```

НЕ:
```
"Инвентаризация на всички 320 артикула — 4 часа работа."
```

## 9.2 Два режима (auto-detection)

```php
function determineInventoryMode($tenant_id) {
    $product_count = DB::run("SELECT COUNT(*) FROM products
                              WHERE tenant_id=? AND is_active=1",
                              [$tenant_id])->fetchColumn();

    $variation_count = DB::run("SELECT COUNT(*) FROM products
                                WHERE tenant_id=? AND parent_id IS NOT NULL",
                                [$tenant_id])->fetchColumn();

    if ($product_count < 500 && $variation_count < $product_count * 2) {
        return 'fast';  // Малко артикули, малко вариации
    }
    return 'full';      // 500+ или много вариации
}
```

### Fast mode (под 500 артикула):
- Zone Walk = 15 мин
- Direct count: сканирай → +1
- Без категоризация предварително

### Full mode (500+ или много вариации):
- Zone Walk с матрица размер×цвят
- Sticky headers
- Quick fill "Всички = 1 / 2 / 5"
- Crash recovery

## 9.3 Counting flow (Zone Walk)

### UI:

```
┌─────────────────────────────────────┐
│ Рафт: Мъжки дрехи (Zone 3)          │
│                                     │
│ 18 артикула · 42 бр.                │
│                                     │
│ [📷 Сканирай] [🎤 Кажи]             │
│                                     │
├─────────────────────────────────────┤
│                                     │
│ Nike T-Shirt M Black                │
│ Системата: 5 бр.                    │
│ Преброих: [−] 5 [+]                 │ ← -/+ stepper
│                                     │
│ ✓ Съвпада                           │ ← зелен ако match
│                                     │
├─────────────────────────────────────┤
│ Adidas Shorts L Blue                │
│ Системата: 3 бр.                    │
│ Преброих: [−] 2 [+]                 │
│                                     │
│ ⚠ Разлика: -1                       │ ← червен ако diff≥1
│                                     │
├─────────────────────────────────────┤
│                                     │
│ [Готово с рафта →]                  │
│ [⏸ Паузирай]                       │
└─────────────────────────────────────┘
```

### Behavior:

- **−/+ stepper** (не клавиатура!)
- **Системата казва** — readonly display
- **Преброих** — editable с stepper
- **Match state:** зелен check ако съвпада, червен warning ако ≥1 разлика
- **Sticky header** с zone info + progress (18 артикула, 12 преброени)

## 9.4 Counting screen features (от S61-S66)

### Duplicate warning

Когато Пешо въведе същия артикул втори път:
```
⚠ Вече преброи този артикул.
  Това ще го обнови ли? [Да] [Не]
```

### Filter chips

По доставчик / категория:
```
[Всички] [Nike] [Adidas] [Puma] [Clear ×]
```

Тап на chip → филтрира списъка.

### Barcode scanner

`BarcodeDetector API` (ако browser поддържа) + USB/Bluetooth keyboard listener:

```js
// Camera scanner
if ('BarcodeDetector' in window) {
    const detector = new BarcodeDetector({ formats: ['ean_13', 'code_128'] });
    // ...
}

// USB/Bluetooth keyboard — ако сканерът е HID device
document.addEventListener('keypress', (e) => {
    if (scanBuffer.length > 5 && e.key === 'Enter') {
        processBarcode(scanBuffer);
        scanBuffer = '';
    } else {
        scanBuffer += e.key;
    }
});
```

### Cancel бутон

Всеки момент Пешо може да натисне "⏸ Паузирай" → session се запазва → може да продължи по-късно.

### Edit capability (pencil button)

Всяка вече преброена бройка има 🖊 бутон → отваря модален stepper → коригира се.

### Zone-level quantity display

```
Мъжки дрехи (Zone 3) · 18 артикула · 42 бр.
                        ░░░░░░░░ 12/18 преброени
```

## 9.5 Crash recovery

### Auto-save на всеки 10 секунди:

```js
setInterval(() => {
    if (hasUnsavedChanges) {
        fetch('inventory-autosave.php', {
            method: 'POST',
            body: JSON.stringify({
                session_id: currentSessionId,
                lines: pendingLines
            })
        });
    }
}, 10000);
```

### При reopen на модула:

```
⏸ Имаш незавършена инвентаризация
  Рафт 3 · 12 от 18 артикула · от 14:32 вчера

  [Продължи →] [Започни нова]
```

## 9.6 Offline Mode — Event-Sourced with Smart Business Logic

### Философия: Последната дума на бизнес логиката, не на timestamp-а.

**Проблемът с Last-Write-Wins:** Server винаги "печели" защото офлайн timestamp-ите са по-стари → Пешо губи броенето си → ядосва се → не ползва offline mode.

**Правилният подход:** Разграничаваме **типа операция** и прилагаме различна резолюция стратегия за всяка.

### 9.6.1 Два типа inventory events

**Тип A — Delta events (merge естествено):**
- `SALE: -1` (продажба)
- `DELIVERY: +5` (доставка)
- `TRANSFER_OUT: -3` / `TRANSFER_IN: +3`

Тези merge-ват се **commutatively** — не зависи от редa на прилагане.

**Тип B — Count assertions (изискват business logic):**
- `COUNT: 5` ("преброй на рафта видях 5")

Това **не е** delta. Това е **твърдение за наблюдавана реалност** към определен момент.

**Тип C — Adjustments (explicit corrections):**
- `ADJUSTMENT: quantity=3, reason="намерени в склад"`

Adjustment побеждава всички останали за conflict period (owner override).

### 9.6.2 Event-Sourced Schema

```sql
CREATE TABLE inventory_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    product_id INT NOT NULL,
    event_type ENUM('SALE','DELIVERY','TRANSFER_IN','TRANSFER_OUT',
                    'COUNT','ADJUSTMENT','RETURN') NOT NULL,
    quantity_delta INT,              -- NULL за COUNT (виж asserted_quantity)
    asserted_quantity INT,           -- NULL за delta events
    baseline_before_event INT,        -- snapshot за COUNT events
    source ENUM('online','offline_sync','manual') NOT NULL,
    user_id INT,
    device_id VARCHAR(50),
    local_timestamp BIGINT,          -- клиент time (UTC ms)
    server_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sync_status ENUM('pending','applied','conflict','rejected') DEFAULT 'applied',
    INDEX (tenant_id, store_id, product_id, server_timestamp),
    INDEX (sync_status)
);
```

### 9.6.3 Smart Business Logic Resolver

```php
class InventoryConflictResolver {

    public function resolve($event) {
        return match($event['event_type']) {
            'SALE'          => $this->resolveSale($event),
            'DELIVERY'      => $this->resolveDelivery($event),
            'COUNT'         => $this->resolveCount($event),
            'ADJUSTMENT'    => $this->resolveAdjustment($event),
            'TRANSFER_IN',
            'TRANSFER_OUT'  => $this->resolveTransfer($event),
            default         => $this->resolveDefault($event)
        };
    }

    /**
     * SALE: Винаги прилагаме ако има наличност.
     * Това е непрекъсваемо бизнес правило.
     */
    private function resolveSale($event) {
        $current = $this->getCurrentStock($event['product_id'], $event['store_id']);

        if ($current >= abs($event['quantity_delta'])) {
            $this->applyDelta($event);
            return ['status' => 'applied', 'new_stock' => $current + $event['quantity_delta']];
        }

        // Insufficient stock — partial fulfillment or reject
        return [
            'status' => 'partial_rejected',
            'alert' => 'INSUFFICIENT_STOCK',
            'ui_message' => "Продажбата не може да се приложи — няма достатъчно наличност"
        ];
    }

    /**
     * DELIVERY: Винаги се прилага — merge commutatively.
     */
    private function resolveDelivery($event) {
        $this->applyDelta($event);
        return ['status' => 'applied'];
    }

    /**
     * COUNT: Най-сложната логика — сравнява с baseline + online deltas.
     *
     * Пример:
     *   Baseline при започване на broenie: 6
     *   Online sales след baseline: -1
     *   Expected now: 5
     *   Пешо counted: 5 → AUTO-RESOLVE, no conflict
     *
     *   vs.
     *
     *   Expected now: 5
     *   Пешо counted: 3 → REAL DISCREPANCY = shrinkage/scan error
     *   → Отваря се discrepancy workflow
     */
    private function resolveCount($event) {
        // 1. Вземи baseline от момента на започване на броенето
        $baseline = $event['baseline_before_event'];

        // 2. Намери всички online deltas след baseline
        $onlineDeltas = DB::run("
            SELECT SUM(quantity_delta) as total_delta
            FROM inventory_events
            WHERE tenant_id = ? AND store_id = ? AND product_id = ?
              AND server_timestamp > FROM_UNIXTIME(? / 1000)
              AND event_type IN ('SALE', 'DELIVERY', 'TRANSFER_IN', 'TRANSFER_OUT', 'RETURN')
              AND source = 'online'
        ", [
            $event['tenant_id'], $event['store_id'],
            $event['product_id'], $event['local_timestamp']
        ])->fetchColumn() ?? 0;

        // 3. Изчисли expected
        $expectedNow = $baseline + $onlineDeltas;
        $counted = $event['asserted_quantity'];

        // 4. Сравни
        $discrepancy = $counted - $expectedNow;

        if ($discrepancy === 0) {
            // PERFECT MATCH — auto-resolve
            $this->setStock($event['product_id'], $event['store_id'], $counted);
            return [
                'status' => 'auto_resolved',
                'ui_message' => null  // тихо приемане, без notification
            ];
        }

        // Има разлика — business workflow
        return [
            'status' => 'discrepancy',
            'discrepancy' => $discrepancy,
            'ui_explanation' => $this->buildExplanation($baseline, $onlineDeltas, $counted),
            'options' => [
                'accept_counted',  // Приеми преброеното (корекция)
                'accept_expected', // Запази очакваното (грешка в броене)
                'open_review'      // Отвори детайлен преглед
            ]
        ];
    }

    /**
     * ADJUSTMENT: Owner override — винаги печели.
     */
    private function resolveAdjustment($event) {
        $this->setStock($event['product_id'], $event['store_id'], $event['asserted_quantity']);
        return ['status' => 'applied_as_override'];
    }

    private function buildExplanation($baseline, $onlineDeltas, $counted) {
        $onlineText = $onlineDeltas > 0
            ? "доставени +{$onlineDeltas}"
            : "продадени " . abs($onlineDeltas);

        return "Когато започнахте броенето: {$baseline} бр.
                Междувременно онлайн: {$onlineText}.
                Очаквано: " . ($baseline + $onlineDeltas) . ".
                Преброено: {$counted}.
                Разлика: " . ($counted - $baseline - $onlineDeltas) . " бр.";
    }
}
```

### 9.6.4 UX при auto-resolve (0 конфликти)

**Пример 1: Пешо брои 5, онлайн продадено 1 → expected 5, counted 5**

```
✓ Рафт броене запазен
(Без notification, тихо приемане)
```

**Пример 2: Пешо брои 3, онлайн продадено 1, expected 5 → разлика -2**

```
┌───────────────────────────────────────┐
│ Разминаване на рафт: Nike 42          │
│                                       │
│ Когато започна броенето: 6 бр.        │
│ Междувременно продадени: 1            │
│ Очаквано сега: 5 бр.                  │
│ Преброи: 3 бр.                        │
│ Разлика: -2 бр.                       │
│                                       │
│ Вероятни причини:                     │
│ • Скрити някъде в магазина            │
│ • Неотразена грешка при броене        │
│ • Пропусната продажба                 │
│                                       │
│ [Приеми 3 (коригирам)]                │
│ [Запази 5 (грешка при броене)]        │
│ [Отвори детайлен преглед]             │
└───────────────────────────────────────┘
```

**НЕ "конфликт — разминаване" + "кое печели".**
**А "обяснение какво се е случило" + "какво искаш да направиш".**

### 9.6.5 Offline queue в IndexedDB

```javascript
const OfflineQueue = {
    async addEvent(event) {
        const db = await this.getDB();
        const tx = db.transaction('events', 'readwrite');
        await tx.objectStore('events').add({
            ...event,
            localTimestamp: Date.now(),
            deviceId: this.getDeviceId(),
            syncStatus: 'pending'
        });

        this.triggerSyncIfOnline();
    },

    async addCountEvent(productId, countedQty, baseline) {
        return this.addEvent({
            eventType: 'COUNT',
            productId,
            assertedQuantity: countedQty,
            baselineBeforeEvent: baseline
        });
    },

    async sync() {
        const pending = await this.getPendingEvents();
        if (pending.length === 0) return;

        const response = await fetch('/api/inventory/sync', {
            method: 'POST',
            body: JSON.stringify({ events: pending })
        });

        const results = await response.json();

        // Обработи резултатите
        for (const result of results) {
            if (result.status === 'discrepancy') {
                this.showDiscrepancyUI(result);
            }
            await this.markSynced(result.eventId);
        }
    }
};
```

### 9.6.6 Authorization: кой може да accept discrepancy

| Role | Authority |
|---|---|
| **Owner** | Приема всички discrepancies |
| **Manager** | Приема до ±10% разлика на артикул |
| **Seller** | НЕ приема — маркира като "open for review" (owner/manager обработва после) |

**Защо това е важно:** Seller-ът не трябва да може да скрие кражба като "correction". Open for review flow предотвратява това.

### 9.6.7 Защо НЕ CRDT

CRDT (Conflict-free Replicated Data Types) звучи модерно, но:

- CRDT е за collaborative text editing (Google Docs)
- Inventory НЕ Е текстов документ
- Inventory има **business constraints** (не може да е отрицателен, SALE не е commutative)
- CRDT добавя ненужна сложност за домейн който не го изисква

**Event sourcing с business logic resolver е правилният patterна retail inventory.**


## 9.7 Fast-add от inventory (4 стъпки)

Ако Пешо сканира артикул който **не съществува** в системата — бърза добавка:

**Стъпка 1:** Снимка (по желание) + наименование + цена
**Стъпка 2:** Вариации (ако е с вариации)
**Стъпка 3:** Основна инфо (доставчик, категория)
**Стъпка 4:** AI описание (auto) + принт етикет

Споделя код с products.php wizard steps 4+6.

## 9.8 Confidence model

### Как confidence расте:

```php
function updateProductConfidence($product_id) {
    // Фактори:
    // +0.05 за всяка продажба (signal че е реален артикул)
    // +0.10 за всяка доставка
    // +0.20 за всяко zone_walk counting
    // +0.15 за всяка manual corrective action от owner
    // -0.02 всеки 30 дни без activity (decay)

    $sales = DB::run("SELECT COUNT(*) FROM sale_items
                      WHERE product_id=?", [$product_id])->fetchColumn();
    $deliveries = DB::run("SELECT COUNT(*) FROM delivery_items
                           WHERE product_id=?", [$product_id])->fetchColumn();
    $walks = DB::run("SELECT COUNT(*) FROM inventory_count_lines
                      WHERE product_id=?", [$product_id])->fetchColumn();

    $score = 0.20 + ($sales * 0.05) + ($deliveries * 0.10) + ($walks * 0.20);
    $score = min(1.00, $score);

    // Decay if last activity > 30 days ago
    $last_seen = DB::run("SELECT GREATEST(
        COALESCE(MAX(s.created_at), '2000-01-01'),
        COALESCE(MAX(d.created_at), '2000-01-01'),
        COALESCE(MAX(icl.created_at), '2000-01-01')
    ) FROM products p
    LEFT JOIN sale_items si ON si.product_id=p.id
    LEFT JOIN sales s ON s.id=si.sale_id
    LEFT JOIN delivery_items d ON d.product_id=p.id
    LEFT JOIN inventory_count_lines icl ON icl.product_id=p.id
    WHERE p.id=?", [$product_id])->fetchColumn();

    $days_old = (time() - strtotime($last_seen)) / 86400;
    if ($days_old > 30) $score -= 0.02 * floor($days_old / 30);
    $score = max(0, $score);

    DB::run("UPDATE products SET confidence_score=? WHERE id=?",
            [$score, $product_id]);
}
```

### 12 железни правила за Inventory v4

1. **Никога не изисквай пълна инвентаризация** — само zone walks
2. **Stepper винаги, клавиатура никога** (освен "as override")
3. **Auto-save на всеки 10 сек** — crash recovery
4. **Confidence score винаги видим** — "AI точност 78%"
5. **Duplicate warning** при повторно въвеждане
6. **Filter chips** за по-бърза работа
7. **Barcode scanner** винаги опция
8. **Pause/Resume** — никога не блокирай Пешо
9. **Offline-first** — pending lines в IndexedDB
10. **Fast-add в 4 стъпки** — ако артикулът не съществува
11. **Zone-level display** — не overwhelm с целия магазин
12. **Discrepancy ≥ 1** показва warning — winter wake-up

---

# 10. 13 АРХИТЕКТУРНИ КОМПОНЕНТА (от v2.2)

## 10.1 Компонент #1 — JSON State Contract

Всеки AI отговор има **строго дефинирана JSON структура**:

```json
{
  "response_type": "FACT|DIAGNOSIS|RECOMMENDATION",
  "confidence_class": "A|B|C|D|E",
  "confidence_score": 0.95,
  "main_number": {
    "value": 847.50,
    "currency": "EUR",
    "unit": null,
    "source_sql_hash": "abc123..."
  },
  "reason": "string — one sentence",
  "suggestion": "string — soft action",
  "action_buttons": [
    {"label": "Виж артикули", "type": "filter", "url": "products.php?filter=zero"}
  ],
  "freshness": "2026-04-17 14:23:00",
  "related_entities": ["product:42", "product:58"]
}
```

## 10.2 Компонент #2 — Action Broker L0-L4

AI никога не action-ва директно. Всяко действие минава през брокер с permission levels:

| Level | Действие | Потвърждение |
|---|---|---|
| **L0** | Read-only (виж данни) | Без |
| **L1** | Navigate (отвори екран) | Без |
| **L2** | Soft suggestion (предложи) | Без |
| **L3** | Prepare action (подготви поръчка) | 1 tap от Пешо |
| **L4** | Execute action (смени цена) | **Confirm + PIN** |

**Днес:** AI работи до L2. L3/L4 са Phase 2.

## 10.3 Компонент #3 — Audit Log

```sql
CREATE TABLE ai_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT,
    event_type VARCHAR(50),
    event_data JSON,
    ai_response TEXT,
    ai_confidence DECIMAL(4,3),
    sql_queries_hash VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id, created_at),
    INDEX (event_type, created_at)
);
```

**Append-only.** Отделен MySQL user с `INSERT` only (никога UPDATE/DELETE).

## 10.4 Компонент #4 — Entity Resolution

AI разбира какво иска Пешо дори при неточно описание:

- "тениските" → кои точно? Категория "tshirt" → най-новите
- "черни" → кои цветове? Color "black" вариации
- "летните рокли" → категория + сезонност
- "от Иватекс" → supplier_id по name matching

**Алгоритъм:**

```php
function resolveEntity($text, $tenant_id) {
    // Fuzzy name matching
    // Synonym lookup (tshirt = тениска)
    // Category detection
    // Supplier detection by name fragment
    // Size/color extraction
    // Return: ['type' => 'product_filter', 'filters' => [...]]
}
```

## 10.5 Компонент #5 — Response Types

3 типа AI отговор, всеки с различен visual treatment:

| Type | Badge | Confidence min | Пример |
|---|---|---|---|
| FACT | Зелен ✓ | 0.95 | *"Днес: 847 €"* |
| DIAGNOSIS | Син ℹ | 0.80 | *"Nike пада 30% от понеделник"* |
| RECOMMENDATION | Жълт 💡 | 0.70 | *"Обмисли намаление с 10%"* |

**Ако < min confidence → AI мълчи (Закон №3).**

## 10.6 Компонент #6 — Confidence Contract

Описано в CORE Част 3 (5 класа A-E). Тук добавяме:

### Visual treatment:

```
A (100%):    точно число                 → 847.50 €
B (95-99%):  точно число + звездичка     → 847.50 €*
C (80-95%):  диапазон                    → 800-900 €
D (60-80%):  диапазон + bar индикатор    → 700-950 € [░░░░░░░░]
E (<60%):    AI мълчи                    → (nothing)
```

## 10.7 Компонент #7 — Freshness Tracker

Всеки insight има `expires_at` timestamp:

```php
$expires = match($urgency) {
    'critical' => strtotime('+2 hours'),
    'warning'  => strtotime('+24 hours'),
    'info'     => strtotime('+7 days'),
    default    => strtotime('+30 days')
};
```

**След expiry:** insight се скрива, cron го checks again.

## 10.8 Компонент #8 — Failure Taxonomy

AI failure modes:

| Failure | Detection | Recovery |
|---|---|---|
| API timeout | >10s | Retry KEY2 → OpenAI |
| Invalid JSON | Parser fail | Template fallback |
| Hallucination | Fact Verifier fail | Log + return "Нямам сигурни данни" |
| Off-topic | Keyword detection | Redirect to main flow |
| Out of context | Memory overflow | Summarize + retry |

## 10.9 Компонент #9 — Feature Flags

```sql
CREATE TABLE feature_flags (
    flag_name VARCHAR(100) PRIMARY KEY,
    enabled TINYINT(1) DEFAULT 0,
    rollout_percentage INT DEFAULT 0,
    tenants_whitelist JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Ползване:**

```php
if (featureEnabled('weather_insights', $tenant_id)) {
    $prompt .= getWeatherSummary(...);
}
```

## 10.10 Компонент #10 — Idempotency

Всяка AI actions има idempotency key:

```php
function aiAction($action_type, $payload, $tenant_id) {
    $key = hash('sha256', $tenant_id . $action_type . json_encode($payload));

    $existing = DB::run("SELECT result FROM ai_actions_log
                         WHERE idempotency_key=?", [$key])->fetch();
    if ($existing) return $existing['result'];

    $result = executeAction($action_type, $payload);
    DB::run("INSERT INTO ai_actions_log (idempotency_key, result) VALUES (?,?)",
            [$key, json_encode($result)]);
    return $result;
}
```

## 10.11 Компонент #11 — Snapshot Layer

Всеки ден в 03:00:

```sql
INSERT INTO ai_daily_snapshot (
    tenant_id, store_id, snapshot_date,
    total_products, total_inventory_value,
    avg_confidence, avg_freshness,
    pending_insights_count,
    resolved_insights_count_30d
)
SELECT ... FROM ... ;
```

Служи за:
- Тренд анализи YoY
- Rollback при problem
- Debug защо AI каза X на определена дата

## 10.12 Компонент #12 — Multi-tenant Brain

Cross-tenant knowledge (анонимизирано):

```sql
CREATE TABLE biz_learned_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_type VARCHAR(100),      -- "дрехи детски"
    field_type VARCHAR(50),          -- "size", "color", "category"
    value VARCHAR(255),               -- "104/110"
    usage_count INT DEFAULT 1,
    first_seen_at TIMESTAMP,
    UNIQUE KEY (business_type, field_type, value)
);
```

**Пример:** нов магазин в "дрехи детски" получава suggestions за размери които други 50 магазина вече са добавили.

## 10.13 Компонент #13 — Cost Control

```sql
CREATE TABLE api_cost_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    provider VARCHAR(30),
    model VARCHAR(50),
    input_tokens INT,
    output_tokens INT,
    cost_usd DECIMAL(10,6),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id, created_at)
);
```

### Daily lookup:

```php
function getDailyAPISpend($tenant_id = null) {
    $sql = "SELECT SUM(cost_usd) FROM api_cost_log WHERE DATE(created_at)=CURDATE()";
    if ($tenant_id) $sql .= " AND tenant_id=?";
    return DB::run($sql, $tenant_id ? [$tenant_id] : [])->fetchColumn();
}
```

### Kill switch:

```php
$spend = getDailyAPISpend();
if ($spend > 75) {
    // Червен режим — FREE tenants paused
    // Send Telegram alert
    if ($tenant['plan'] === 'free') {
        return ['error' => 'AI temporarily unavailable. Try again tomorrow.'];
    }
}
```

---

# 11. 10-ТЕ ЗАПОВЕДИ (v2.2 + v2.3 корекции)

## Заповед №1 — Всяко число има SQL source

Ако AI споменава число → backend записва в `audit_log.sql_queries_hash`.
Ако Fact Verifier не може да validates → **AI мълчи**.

## Заповед №2 — Никога не измисляй имена

AI не казва "Nike" ако няма Nike в products таблицата.
Entity resolution ПРЕДИ generation.

## Заповед №3 — Confidence class винаги видим

Всеки AI отговор има badge A/B/C/D.
E отговори = тишина.

## Заповед №4 — DB invariants (CHECK + TRIGGERS)

```sql
ALTER TABLE products
  ADD CONSTRAINT chk_prices CHECK (retail_price >= 0 AND cost_price >= 0),
  ADD CONSTRAINT chk_stock CHECK (min_quantity >= 0);

ALTER TABLE sales
  ADD CONSTRAINT chk_total CHECK (total >= 0),
  ADD CONSTRAINT chk_status CHECK (status IN ('pending','completed','canceled'));
```

Триггери за автоматично updates на `confidence_score`, `last_seen_at`, etc.

## Заповед №5 — Idempotency ключ за всяко action

Виж Компонент #10.

## Заповед №6 — Audit log append-only

Виж Компонент #3. Physical impossibility чрез GRANT-ове.

## Заповед №7 — Glossary Guardian PHP клас

```php
class GlossaryGuardian {
    private $forbidden_words = ['марж', 'churn', 'ROI', 'KPI', 'AOV'];
    private $allowed_replacements = [
        'марж' => 'чиста печалба',
        'churn' => 'спрели клиенти',
        'ROI' => 'колко пари ти връща',
    ];

    public function sanitize($text) {
        foreach ($this->forbidden_words as $word) {
            if (stripos($text, $word) !== false) {
                if (isset($this->allowed_replacements[$word])) {
                    $text = str_ireplace($word, $this->allowed_replacements[$word], $text);
                } else {
                    // Log and return fallback
                    logError("Forbidden word detected: $word");
                    return null;
                }
            }
        }
        return $text;
    }
}
```

## Заповед №8 — Fact Verifier skeleton

```php
class FactVerifier {
    public function verify($ai_response, $tenant_id) {
        $claims = $this->extractClaims($ai_response);

        foreach ($claims as $claim) {
            $sql = $this->buildVerificationSQL($claim);
            $actual = DB::run($sql, $claim['params'])->fetchColumn();

            if (abs($actual - $claim['value']) > $claim['tolerance']) {
                return [
                    'verified' => false,
                    'claim' => $claim,
                    'actual' => $actual,
                    'reason' => "Number mismatch: AI said {$claim['value']}, actual is {$actual}"
                ];
            }
        }

        return ['verified' => true];
    }
}
```

## Заповед №9 — Retrospective след всяка фаза

След S80, S100, S120, S140 — ретроспективен документ:
- Какво работи?
- Какво не?
- Какво да променим в следващата фаза?
- BIBLE_vX.Y_ADDITIONS.md с корекции

## Заповед №10 — Never deploy without Python script

```
- Никога `sed` за file modifications
- Никога regex patches през console
- Винаги Python скрипт → review → deploy
```

---

# 12. RECOVERY MODE + TRUST DECAY (v2.3)

## 12.1 Recovery Mode

Активира се когато:
- 3+ AI failures за един tenant в последните 60 минути
- OR глобално 5%+ failure rate в последните 15 минути

**Поведение:**
- AI отговори са template-base (не LLM-generated)
- Pills/signals се показват нормално (те са PHP)
- User вижда: *"AI се възстановява. Функциите работят без съвети за момента."*
- Auto-resume след 15 минути без failures

## 12.2 Trust Decay (per-tenant)

```sql
CREATE TABLE tenant_ai_trust (
    tenant_id INT PRIMARY KEY,
    trust_score DECIMAL(4,3) DEFAULT 1.000,
    failures_count INT DEFAULT 0,
    last_failure_at TIMESTAMP,
    paused_until TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Логика:

```php
function adjustTrust($tenant_id, $outcome) {
    if ($outcome === 'failure') {
        DB::run("UPDATE tenant_ai_trust
                 SET trust_score = GREATEST(0, trust_score - 0.05),
                     failures_count = failures_count + 1,
                     last_failure_at = NOW()
                 WHERE tenant_id=?", [$tenant_id]);

        // Pause за 15 мин ако 3+ failures
        $trust = DB::run("SELECT * FROM tenant_ai_trust WHERE tenant_id=?",
                         [$tenant_id])->fetch();
        if ($trust['failures_count'] >= 3) {
            DB::run("UPDATE tenant_ai_trust
                     SET paused_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                     WHERE tenant_id=?", [$tenant_id]);
        }
    } else {
        // Success → бавно recovers
        DB::run("UPDATE tenant_ai_trust
                 SET trust_score = LEAST(1.000, trust_score + 0.01)
                 WHERE tenant_id=?", [$tenant_id]);
    }
}
```

## 12.3 Global Kill Switch

```sql
-- Config таблица
CREATE TABLE system_config (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

```php
function isAIEnabled() {
    $kill_switch = DB::run("SELECT `value` FROM system_config WHERE `key`='ai_kill_switch'")
                     ->fetchColumn();
    return $kill_switch !== '1';
}

// Преди всяка AI заявка:
if (!isAIEnabled()) {
    return ['error' => 'AI temporarily disabled for maintenance.'];
}
```

**Admin може да kill цялата AI система с 1 SQL update.**

---

# 13. AI SAFETY ARCHITECTURE — 6 НИВА + CONFIDENCE ROUTING

*(Обединена версия от AI Scanner MASTER v2.2 + Kimi корекции + ChatGPT анализ. Прилага се за ВСИЧКИ AI входове: OCR фактури, voice добавяне, AI Image Studio, AI описания.)*

## 13.1 Централен принцип — CONFIDENCE-BASED ROUTING (не blocked by default)

**Старата философия (отхвърлена):** *"AI е БЛОКИРАН. Всичко минава през 6 нива + user confirmation."*

**Проблем:** 100% от фактурите преминават през **всички** проверки + **user confirmation** → бавно, frustrating, потребителят е изморен още от 10-тата фактура.

**Новата философия: Confidence Routing** (Kimi's brilliant insight).

```
┌────────────────────────────────────────────────────────┐
│  OCR Pipeline (паралелно изпълнение на validations)   │
└────────────────────────────────────────────────────────┘
                        ↓
              ┌─────────────────┐
              │ Confidence Score│
              └─────────────────┘
                        ↓
        ┌───────────────┼───────────────┐
        ↓               ↓               ↓
   > 0.92            0.75-0.92         < 0.75
   ┌──────┐         ┌──────┐          ┌──────┐
   │AUTO- │         │SMART │          │REJECT│
   │ACCEPT│         │  UI  │          │      │
   └──────┘         └──────┘          └──────┘
   0 friction    Edit only        Manual entry
                 uncertain fields
```

### Ефект:

- **~60% от фактурите** имат >92% confidence → **0 friction**
- **~25% имат 75-92%** → Smart UI показва **само несигурните полета**
- **~15% <75%** → manual entry (нормално за повредени/ръкописни)

Това променя потребителското преживяване от "15 секунди confirmation за всяка" до "1 секунда за 60% от случаите".

## 13.2 Confidence Routing Implementation

```php
class OCRRouter {
    public function process($file, $tenant_id) {
        // Precondition: File Quality Gate (Ниво 0)
        $fileCheck = $this->fileQualityGate($file, $tenant_id);
        if (!$fileCheck['passed']) {
            return ['status' => 'REJECTED', 'reason' => $fileCheck['reason']];
        }

        // Extract with strict JSON (Ниво 1)
        $rawData = $this->aiVisionExtract($file);

        // Run validations in PARALLEL
        $validations = [
            'math' => $this->mathValidator->validate($rawData),
            'business' => $this->businessValidator->validate($rawData),
            'semantic' => $this->semanticValidator->validate($rawData),
            'kb' => $this->knowledgeBase->enrich($rawData)
        ];

        $result = $this->mergeValidations($rawData, $validations);
        $confidence = $result['confidence'];

        // CONFIDENCE ROUTING
        if ($confidence > 0.92) {
            // AUTO-ACCEPT — няма нужда от UI
            $this->saveInvoice($result, 'auto_accepted');
            return [
                'status' => 'AUTO_ACCEPTED',
                'data' => $result,
                'show_ui' => false  // тихо приемане
            ];
        }

        if ($confidence >= 0.75) {
            // SMART UI — покажи само несигурните полета
            return [
                'status' => 'REVIEW_NEEDED',
                'data' => $result,
                'uncertain_fields' => $result['uncertain_fields'],
                'show_ui' => 'smart'  // само несигурните полета
            ];
        }

        // < 0.75 — reject, manual entry
        return [
            'status' => 'REJECTED',
            'reason' => $result['errors'],
            'suggest_manual' => true
        ];
    }
}
```

## 13.3 Smart UI при 75-92% confidence

Вместо да се показват ВСИЧКИ полета — **показват се само несигурните**:

```
┌─────────────────────────────────────────┐
│ ✓ Доставчик: Иватекс ЕООД (auto)       │
│ ✓ ЕИК: 123456789 (auto)                │
│ ✓ Дата: 17.04.2026 (auto)              │
│ ⚠ Сума: 450.00 лв.      [ Потвърди ]   │ ← uncertainty flag
│ ⚠ ДДС 20%: 75.00 лв.    [ Потвърди ]   │
│ ✓ Общо: 525.00 лв. (auto)              │
└─────────────────────────────────────────┘
```

Пешо тапва "Потвърди" само за **2 полета** вместо да review-ва всичките 12.

## 13.4 Ниво 0 — File Quality Gate (Precondition)

**НЕ Е "ниво" в pipeline-а — това е PRECONDITION.** Ако файлът не премине → няма OCR изобщо.

### File duplicate detection (Kimi корекция — НЕ само SHA256):

```php
// ❌ ЛОШО: hash('sha256', $file) — един и същ файл, два различни потребителя = false duplicate

// ✅ ПРАВИЛНО: Per-user file hash
$user_file_hash = hash('sha256', $user_id . '|' . file_get_contents($file));

// ПЛЮС: Semantic hash (cross-user)
$semantic_hash = hash('sha256', implode('|', [
    $supplier_eik,
    $document_number,
    $document_date,
    $total_amount
]));

// Логика:
// user_file_hash match → "Същата снимка — не таксуваме"
// semantic_hash match for SAME user → "Вече имаш тази фактура в архива"
// semantic_hash match for DIFFERENT user → нормално (не е проблем)
```

### File quality checks:

| Проверка | Логика | При неуспех |
|---|---|---|
| Size | 10KB ≤ file ≤ 10MB | Блок + съобщение |
| Тип | JPG, PNG, WEBP, PDF (whitelist) | Блок + съобщение |
| Резолюция | minimum 300px | Alert "По-ясна снимка" |
| Multi-page PDF | `getPDFPageCount() > 1` | **Alert:** "Документът има X страници. Обработвам първата." |

### PDF → Image конверсия (Kimi корекция — НЕ base64):

```php
// ❌ ЛОШО: PDF директно → base64 → AI (до 13MB payload)
// ✅ ПРАВИЛНО: ImageMagick конверсия → JPG 300 DPI

function convertPDFToImage($pdf_path) {
    $output = tempnam(sys_get_temp_dir(), 'ocr_') . '.jpg';

    $cmd = sprintf(
        "convert -density 300 -strip -quality 90 %s[0] %s 2>&1",
        escapeshellarg($pdf_path),
        escapeshellarg($output)
    );
    exec($cmd, $output_lines, $return_code);

    if ($return_code !== 0 || !file_exists($output)) {
        throw new Exception("PDF conversion failed");
    }

    // Компресиране ако >3MB
    if (filesize($output) > 3_000_000) {
        exec(sprintf("convert %s -resize 2000x2000\\> -quality 80 %s",
             escapeshellarg($output), escapeshellarg($output)));
    }

    return $output;
}
```

## 13.3 Ниво 1 — AI Vision промпт защити (7 правила)

```python
SYSTEM_PROMPT = """
You are an OCR assistant. Your ONLY task is to extract data from the invoice image.

CRITICAL RULES:
1. Return ONLY valid JSON, no commentary
2. If you cannot read a field, return null — NEVER invent data
3. Include "confidence" score (0.00-1.00) for the overall document
4. If there's a QR code, prioritize QR data over visual reading
5. Dates MUST be YYYY-MM-DD format
6. Amounts MUST be decimal numbers with 2 places, NO currency symbols
7. Extract ONLY what is VISIBLE in the image — do not infer from context

Output structure:
{
  "supplier_eik": "123456789" or null,
  "supplier_name": "..." or null,
  "invoice_number": "..." or null,
  "date": "YYYY-MM-DD" or null,
  "base_amount": 0.00 or null,
  "vat_rate": 0-100 or null,
  "vat_amount": 0.00 or null,
  "total_amount": 0.00 or null,
  "currency": "BGN|EUR|USD|GBP",
  "line_items": [...],
  "confidence": 0.00-1.00,
  "used_qr": true/false
}
"""
```

### Threshold:

- `confidence >= 0.95` → автоматична обработка
- `confidence < 0.95` → жълт alert, user review required
- `confidence < 0.80` → red alert, forced review

## 13.4 Ниво 2 — PHP математическа верификация

```php
function validateMath($data) {
    $issues = [];

    // Проверка 1: base + vat = total (±€0.02)
    if (abs(($data['base_amount'] + $data['vat_amount']) - $data['total_amount']) > 0.02) {
        $issues[] = [
            'level' => 'RED',
            'vibrate' => true,
            'message' => "Математика не съвпада: {$data['base_amount']} + {$data['vat_amount']} ≠ {$data['total_amount']}"
        ];
    }

    // Проверка 2: base × (vat_rate/100) = vat (±€0.02)
    $expected_vat = $data['base_amount'] * ($data['vat_rate'] / 100);
    if (abs($expected_vat - $data['vat_amount']) > 0.02) {
        $issues[] = [
            'level' => 'YELLOW',
            'message' => "VAT изчисление нетипично"
        ];
    }

    // Проверка 3: всички суми положителни
    if ($data['base_amount'] <= 0 || $data['vat_amount'] < 0 || $data['total_amount'] <= 0) {
        $issues[] = ['level' => 'BLOCK', 'message' => 'Невалидни суми'];
    }

    // Проверка 4: валута whitelist
    if (!in_array($data['currency'], ['BGN', 'EUR', 'USD', 'GBP'])) {
        $issues[] = ['level' => 'BLOCK', 'message' => 'Екзотична валута'];
    }

    return $issues;
}
```

## 13.5 Ниво 3 — Бизнес логика

### ЕИК контролна сума:

```php
function validateEIK($eik) {
    $eik = preg_replace('/\s+/', '', $eik);
    if (!preg_match('/^\d{9}$|^\d{13}$/', $eik)) return false;

    if (strlen($eik) === 9) {
        // 9-цифрен алгоритъм
        $weights = [1, 2, 3, 4, 5, 6, 7, 8];
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $sum += intval($eik[$i]) * $weights[$i];
        }
        $check = $sum % 11;
        if ($check === 10) {
            $weights = [3, 4, 5, 6, 7, 8, 9, 10];
            $sum = 0;
            for ($i = 0; $i < 8; $i++) {
                $sum += intval($eik[$i]) * $weights[$i];
            }
            $check = $sum % 11;
            if ($check === 10) $check = 0;
        }
        return intval($eik[8]) === $check;
    }

    // 13-цифрен алгоритъм (ДДС регистрация)
    // ... similar logic
    return true;
}
```

### Други checks:

| Проверка | Логика | При неуспех |
|---|---|---|
| Date range | Не в бъдещето, не >5 г назад | Warning |
| Min amount | ≥ €0.01 | Block |
| Max amount | ≤ €100,000 без потвърждение | Extra confirm step |

## 13.6 Ниво 3.5 — Cross-field semantic validation (Kimi новост)

**Математиката може да е вярна с грешни семантики!** Пример: Claude обърква `base` с `total`.

```php
function validateSemantics($data) {
    $issues = [];

    // Total трябва да е най-голямото число
    if ($data['total_amount'] < $data['base_amount']) {
        $issues[] = "Обща сума {$data['total_amount']} < базова {$data['base_amount']} — coordinate field mapping?";
    }

    // VAT % валидни за България
    if ($data['base_amount'] > 0) {
        $vat_pct = ($data['vat_amount'] / $data['base_amount']) * 100;
        if (!in_array(round($vat_pct), [0, 5, 9, 20])) {
            $issues[] = "VAT % = {$vat_pct}% — нетипично (очаквано 0/5/9/20)";
        }
    }

    // Sanity range
    if ($data['total_amount'] < 0.01 || $data['total_amount'] > 1_000_000) {
        $issues[] = "Сума извън нормалния диапазон";
    }

    return $issues;
}
```

## 13.7 Ниво 4 — Whitelist (BG + VIES)

### Supplier whitelist:

```sql
CREATE TABLE scanner_supplier_whitelist (
    eik VARCHAR(13) PRIMARY KEY,
    name VARCHAR(255),
    country VARCHAR(2),
    vat_treatment VARCHAR(30),    -- "standard", "reverse_charge", etc
    verified_at TIMESTAMP,
    source ENUM('manual','vies','auto') DEFAULT 'auto'
);
```

### VIES с 30-дневен кеш (Kimi корекция):

```php
function checkVIES($vat_number) {
    // Cache check (30 дни)
    $cached = DB::run("SELECT status, checked_at FROM scanner_vies_cache
                       WHERE vat_number = ?
                         AND checked_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
                       [$vat_number])->fetch();
    if ($cached) return $cached['status'];

    // Real-time call
    $result = callVIESAPI($vat_number);  // slow, has rate limits

    DB::run("INSERT INTO scanner_vies_cache (vat_number, status, checked_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE status=VALUES(status), checked_at=NOW()",
             [$vat_number, $result]);

    return $result;
}
```

## 13.8 Ниво 5 — Knowledge Base (ТЕМПЛЕЙТИ, не кеширани данни!)

**Kimi критична корекция:** KB не връща стари данни — KB връща **ТЕМПЛЕЙТ НА ДОСТАВЧИКА**, не конкретна фактура.

### Schema:

```sql
CREATE TABLE scanner_supplier_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_eik VARCHAR(13) UNIQUE,
    supplier_name VARCHAR(255),
    supplier_address TEXT,
    supplier_mol VARCHAR(100),
    supplier_iban VARCHAR(34),
    typical_vat_rate DECIMAL(5,2),
    invoice_format_hints TEXT,   -- "Номер горе дясно, дата под него"
    confidence_avg DECIMAL(4,3),
    sample_count INT DEFAULT 1,
    last_seen_at TIMESTAMP,
    learned_from_user_id INT,
    INDEX (supplier_eik)
);

-- Всяка фактура = отделен запис, никога кеширана
CREATE TABLE scanner_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT,
    user_id INT,
    file_hash_per_user VARCHAR(64),
    semantic_hash VARCHAR(64),
    supplier_eik VARCHAR(13),
    invoice_number VARCHAR(50),
    document_date DATE,
    total_amount DECIMAL(12,2),
    ocr_confidence DECIMAL(4,3),
    math_valid TINYINT(1),
    semantic_valid TINYINT(1),
    is_vies TINYINT(1),
    raw_json TEXT,
    status ENUM('pending','confirmed','rejected','blocked'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id, semantic_hash),
    INDEX (tenant_id, supplier_eik, document_date)
);
```

### Логика:

```php
function processInvoice($file, $user_id, $tenant_id) {
    // 1. Check duplicate (file_hash_per_user)
    $user_hash = hash('sha256', $user_id . '|' . file_get_contents($file));
    $existing = DB::run("SELECT * FROM scanner_documents
                         WHERE file_hash_per_user=?", [$user_hash])->fetch();
    if ($existing) {
        return ['status' => 'duplicate', 'document' => $existing];
    }

    // 2. OCR с AI
    $ocr_data = callAIVision($file);

    // 3. Check supplier template (KB)
    $template = DB::run("SELECT * FROM scanner_supplier_templates
                         WHERE supplier_eik=?",
                         [$ocr_data['supplier_eik']])->fetch();

    if ($template && $ocr_data['confidence'] >= 0.95) {
        // Попълваме константи от template (адрес, МОЛ, IBAN)
        $ocr_data['supplier_address'] = $template['supplier_address'];
        $ocr_data['supplier_mol'] = $template['supplier_mol'];
        $ocr_data['supplier_iban'] = $template['supplier_iban'];
        // Променливите (номер, дата, суми) ИДВАТ ОТ AI, никога не се кешират
    }

    // 4. PHP validation (math, business, semantic)
    $issues = [...validateMath($ocr_data), ...validateBusiness($ocr_data),
               ...validateSemantics($ocr_data)];

    // 5. User confirmation
    // 6. На confirm → save + update template if new/better

    return ['status' => 'pending_review', 'data' => $ocr_data, 'issues' => $issues];
}
```

**Ефект:** след 6 месеца **50%+ от фактурите** имат известен шаблон → AI се фокусира само на променливите → **драстично по-евтино**. Но **никога не кешираме конкретни числа**.

## 13.9 Ниво 6 — User Confirmation (UX)

| Elem | Описание |
|---|---|
| 🔴 Червен alert + вибрация | Математическа грешка |
| 🟡 Жълт alert | Confidence < 0.95 |
| 🟢 VIES badge | Познат доставчик |
| ✓ Math badge | Зелен ако OK, червен ако не |
| 🖊 Бутон РЕДАКТИРАЙ | Навсякъде преди потвърждение |
| Кредит след ПОТВЪРДИ | Никога автоматично |

## 13.10 Audit log append-only (Kimi корекция)

```sql
-- MySQL users
CREATE USER 'scanner_writer'@'localhost' IDENTIFIED BY '...';
GRANT INSERT ON runmystore.scanner_audit_log TO 'scanner_writer'@'localhost';

CREATE USER 'scanner_reader'@'localhost' IDENTIFIED BY '...';
GRANT SELECT ON runmystore.scanner_audit_log TO 'scanner_reader'@'localhost';

-- Приложението използва 'scanner_writer' за writes, 'scanner_reader' за reads
-- НИКОЙ не може физически да UPDATE/DELETE scanner_audit_log
```

## 13.11 Rate limits & Telegram alerts

### Daily spend thresholds:

| Threshold | $USD | Действие |
|---|---|---|
| Green | < 25 | Нормално |
| Yellow | 25-50 | Info notification |
| Orange | 50-75 | FREE accounts получават 10-sec delay |
| Red | > 75 | FREE accounts → background queue, резолва се на сутринта |

### Reset (Kimi корекция):

```
Reset = UTC midnight (не BG timezone — consistency)
```

### FREE queue logic:

```php
function canProcessFREE($user_id) {
    $daily_spend = getDailyAPISpend();

    if ($daily_spend < 25) return ['allowed' => true, 'delay' => 0];
    if ($daily_spend < 50) return ['allowed' => true, 'delay' => 0, 'tier' => 'notice'];
    if ($daily_spend < 75) return ['allowed' => true, 'delay' => 10, 'tier' => 'slow'];

    // Red → queue
    $queue_pos = DB::run("SELECT COUNT(*) FROM scanner_queue WHERE status='pending'")
                   ->fetchColumn();
    DB::run("INSERT INTO scanner_queue (user_id, file_path, status) VALUES (?,?,'pending')",
            [$user_id, $file_path]);

    return [
        'allowed' => false,
        'queue_position' => $queue_pos + 1,
        'eta' => 'Утре сутрин (след UTC midnight)'
    ];
}
```

## 13.12 Generalized принцип

**6-нивовата защита се прилага за ВСИЧКИ AI входове, не само OCR:**

| AI вход | Level 0 | Level 1 | Level 2 | Level 3 | Level 4 | Level 5 | Level 6 |
|---|---|---|---|---|---|---|---|
| OCR фактури | File quality | Strict JSON | Math | EIK | VIES | Template | Confirm |
| Voice add | Audio quality | Parse JSON | Price math | Category check | — | biz_learned | Confirm |
| AI Image | Image size | Format check | — | — | — | — | — |
| AI описания | Input length | Output JSON | — | — | — | — | Manual edit |

---

## КРАЙ НА TECH ЧАСТ 3

Следва **TECH Част 4** — DB schema (всички таблици), Cron система (15-мин / hourly / nightly / morning / evening), Deploy процес (server, git flow, Python scripts), Фази A-F roadmap (S72-S140+), 60+ обединени правила, Operations (как работим, Claude модели, стопове).

Общо редове TECH: ~2200


---

# ══════════════════════════════════════
# ЧАСТ 4 TECH — DB, CRON, DEPLOY, ФАЗИ, ПРАВИЛА
# ══════════════════════════════════════

# 14. ПЪЛНА DB SCHEMA

## 14.1 Core tables

### tenants

```sql
CREATE TABLE tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    business_type VARCHAR(100),               -- "дрехи — луксозни"
    country_code CHAR(2) DEFAULT 'BG',
    language CHAR(2) DEFAULT 'bg',
    currency CHAR(3) DEFAULT 'EUR',            -- Important: EUR, не BGN!
    timezone VARCHAR(50) DEFAULT 'Europe/Sofia',
    plan ENUM('free','start','pro') DEFAULT 'free',
    plan_effective ENUM('free','start','pro') DEFAULT 'pro',
    trial_ends_at TIMESTAMP NULL,
    extra_stores INT UNSIGNED DEFAULT 0,
    ui_mode ENUM('simple','detailed') DEFAULT 'simple',
    onboarding_status ENUM('new','in_progress','core_unlocked','operating') DEFAULT 'new',
    onboarding_milestones JSON,
    affiliate_code VARCHAR(50),
    referred_by_code VARCHAR(50),
    stripe_customer_id VARCHAR(100),
    email VARCHAR(255),
    phone VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (plan, created_at),
    INDEX (country_code, plan)
);
```

### stores

```sql
CREATE TABLE stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    latitude DECIMAL(9,6),
    longitude DECIMAL(9,6),
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
```

### users

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT,
    role ENUM('owner','manager','seller') DEFAULT 'seller',
    name VARCHAR(100),
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(50),
    password_hash VARCHAR(255),
    pin VARCHAR(10),                          -- 4-digit quick access
    commission_pct DECIMAL(4,2),              -- for sellers
    max_discount_pct DECIMAL(4,2) DEFAULT 10,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id, role),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
```

### products

```sql
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    parent_id INT DEFAULT NULL,               -- За вариации
    code VARCHAR(50),                         -- НЕ sku!
    name VARCHAR(255) NOT NULL,
    barcode VARCHAR(50),
    category_id INT,
    subcategory_id INT,
    supplier_id INT,
    description TEXT,
    cost_price DECIMAL(10,2),
    retail_price DECIMAL(10,2),               -- НЕ sell_price!
    wholesale_price DECIMAL(10,2),
    currency CHAR(3) DEFAULT 'EUR',
    unit VARCHAR(20) DEFAULT 'бр',
    min_quantity INT DEFAULT 0,               -- НЕ в inventory!
    size VARCHAR(20),
    color VARCHAR(50),
    season ENUM('all_year','spring_summer','autumn_winter','summer_only','winter_only') DEFAULT 'all_year',
    composition VARCHAR(255),
    origin_country VARCHAR(100),
    is_domestic TINYINT(1) DEFAULT 0,
    image_url VARCHAR(500),                   -- НЕ image!
    has_variations TINYINT(1) DEFAULT 0,
    confidence_score DECIMAL(4,3) DEFAULT 0.200,
    has_physical_count TINYINT(1) DEFAULT 0,
    last_counted_at TIMESTAMP NULL,
    counted_via ENUM('manual','zone_walk','delivery','sale') DEFAULT NULL,
    zone_id INT,
    first_sold_at TIMESTAMP NULL,
    first_delivered_at TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (tenant_id, is_active),
    INDEX (tenant_id, parent_id),
    INDEX (tenant_id, category_id),
    INDEX (tenant_id, supplier_id),
    INDEX (tenant_id, barcode),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
```

### inventory

```sql
CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 0,                    -- НЕ qty!
    reserved_quantity INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (tenant_id, store_id, product_id),
    INDEX (product_id)
);
```

### sales

```sql
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    user_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    total DECIMAL(12,2),                      -- НЕ total_amount!
    discount_pct DECIMAL(5,2) DEFAULT 0,
    payment_method ENUM('cash','card','bank','mixed') DEFAULT 'cash',
    status ENUM('pending','completed','canceled') DEFAULT 'completed', -- НЕ cancelled (двойно L)
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id, store_id, created_at),
    INDEX (user_id, created_at)
);
```

### sale_items

```sql
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT,
    unit_price DECIMAL(10,2),                 -- НЕ price!
    cost_price DECIMAL(10,2),                 -- copy from product at sale time
    discount_pct DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (sale_id) REFERENCES sales(id)
);
```

## 14.2 AI tables

### ai_insights (от т. 6.4)

### ai_shown (от т. 6.5)

### ai_audit_log (от т. 10.3)

### ai_actions_log (idempotency)

```sql
CREATE TABLE ai_actions_log (
    idempotency_key VARCHAR(64) PRIMARY KEY,
    action_type VARCHAR(50),
    payload JSON,
    result JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### ai_daily_snapshot (от т. 10.11)

### tenant_ai_memory (long-term memory)

```sql
CREATE TABLE tenant_ai_memory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    category VARCHAR(50),                     -- "pattern","preference","warning"
    content TEXT NOT NULL,                    -- НЕ key/value, content е колоната
    importance DECIMAL(3,2) DEFAULT 0.5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX (tenant_id, category),
    INDEX (tenant_id, importance, created_at)
);
```

### tenant_ai_trust (от т. 12.2)

### feature_flags (от т. 10.9)

### api_cost_log (от т. 10.13)

### biz_learned_data (от т. 10.12)

### chat_messages

```sql
CREATE TABLE chat_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT,
    user_id INT,
    role ENUM('user','assistant','system') NOT NULL,
    content TEXT,
    action_buttons JSON,
    confidence_class CHAR(1),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id, store_id, created_at)
);
```

## 14.3 Inventory tables (INVENTORY_v4)

### store_zones

```sql
CREATE TABLE store_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    name VARCHAR(100),                         -- "Мъжки дрехи", "Витрина"
    description TEXT,
    photo_url VARCHAR(500),
    last_walked_at TIMESTAMP NULL,
    product_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id, store_id)
);
```

### zone_stock

```sql
CREATE TABLE zone_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 0,
    min_quantity INT DEFAULT 0,
    last_counted_at TIMESTAMP NULL,
    UNIQUE KEY (zone_id, product_id)
);
```

### inventory_count_sessions

```sql
CREATE TABLE inventory_count_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    user_id INT NOT NULL,
    zone_id INT DEFAULT NULL,                  -- NULL = cross-zone
    status ENUM('in_progress','paused','completed','cancelled') DEFAULT 'in_progress',
    lines_expected INT DEFAULT 0,
    lines_counted INT DEFAULT 0,
    discrepancies INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX (tenant_id, status)
);
```

### inventory_count_lines

```sql
CREATE TABLE inventory_count_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    product_id INT NOT NULL,
    system_quantity INT,                       -- what system said
    counted_quantity INT,                      -- what user counted
    discrepancy INT,                           -- counted - system
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (session_id)
);
```

### inventory_events

```sql
CREATE TABLE inventory_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    product_id INT NOT NULL,
    event_type ENUM('sale','delivery','return','adjustment','zone_walk','transfer') NOT NULL,
    quantity_change INT,
    quantity_before INT,
    quantity_after INT,
    user_id INT,
    reference_id INT,                          -- FK към sale_id, delivery_id и т.н.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (product_id, created_at),
    INDEX (tenant_id, store_id, created_at)
);
```

## 14.4 Lost demand

### search_log (от т. 26.4)
### lost_demand (от т. 26.4)

## 14.5 Weather (от т. 8.3)

## 14.6 Scanner tables (AI Safety)

### scanner_supplier_templates (от т. 13.8)
### scanner_documents (от т. 13.8)
### scanner_supplier_whitelist (от т. 13.7)
### scanner_vies_cache (от т. 13.7)
### scanner_audit_log (от т. 13.10)
### scanner_queue (от т. 13.11)

## 14.7 Subscription & payments

### subscriptions

```sql
CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    plan ENUM('start','pro') NOT NULL,
    extra_stores INT DEFAULT 0,
    status ENUM('active','cancelled','past_due') DEFAULT 'active',
    stripe_subscription_id VARCHAR(100),
    current_period_start TIMESTAMP,
    current_period_end TIMESTAMP,
    cancel_at_period_end TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id, status)
);
```

### affiliate_commissions

```sql
CREATE TABLE affiliate_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affiliate_code VARCHAR(50) NOT NULL,
    tenant_id INT NOT NULL,
    month_number INT,                           -- 2, 3, 4 (100% payout months)
    amount_eur DECIMAL(10,2),
    status ENUM('pending','paid','failed') DEFAULT 'pending',
    stripe_transfer_id VARCHAR(100),
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (affiliate_code, status),
    INDEX (tenant_id)
);
```

## 14.8 Loyalty (FREE, стая завинаги)

### loyalty_customers

```sql
CREATE TABLE loyalty_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100),
    phone VARCHAR(50),
    qr_code VARCHAR(100) UNIQUE,
    points INT DEFAULT 0,
    total_purchases DECIMAL(12,2) DEFAULT 0,
    visit_count INT DEFAULT 0,
    first_visit_at TIMESTAMP,
    last_visit_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id, qr_code)
);
```

---

# 15. CRON СИСТЕМА

## 15.1 Cron jobs

```
*/15 * * * *  php /var/www/runmystore/cron-insights.php
0 * * * *     php /var/www/runmystore/cron-hourly.php
0 3 * * *     php /var/www/runmystore/cron-nightly.php
0 8 * * *     php /var/www/runmystore/cron-morning.php
0 21 * * *    php /var/www/runmystore/cron-evening.php
0 6 * * *     php /var/www/runmystore/weather-cache.php
*/5 * * * *   php /var/www/runmystore/scanner-queue.php
0 2 * * 0     php /var/www/runmystore/weekly-staff-report.php
```

## 15.2 cron-insights.php (15-минутно)

```php
<?php
// Обновява ai_insights за всички active tenants
// Пуска за всеки tenant/store:
//   - insightZeroStock()
//   - insightLowStock()
//   - insightZombie()
//   - insightBelowCost()
//   - insightMissingPhoto()
//   - insightTopProfit()
//   - insightDeliveryLate()
//   - insightSizeMissing()
//   - insightSupplierNotOrdered()
//   - insightSeasonalShift()
// Resolve-ва insights където условието вече не е вярно.

require_once 'config/database.php';
require_once 'compute-insights.php';

$tenants = DB::run("SELECT t.*, s.id as store_id
                   FROM tenants t
                   JOIN stores s ON s.tenant_id=t.id
                   WHERE t.plan_effective='pro'")->fetchAll();

foreach ($tenants as $row) {
    $tenant_id = $row['id'];
    $store_id = $row['store_id'];

    computeAllInsights($tenant_id, $store_id);
}
```

## 15.3 cron-hourly.php (час)

- Staff КПД refresh
- Lost demand aggregation
- Basket analysis (ако има 30+ дни продажби)
- `ai_insights` freshness update

## 15.4 cron-nightly.php (03:00)

- YoY сравнения
- 90-дневни тренди
- Сезонни patterns
- Cross-store агрегации (БИЗНЕС)
- VIP клиенти absence check
- Daily snapshot в `ai_daily_snapshot`

## 15.5 cron-morning.php (08:00 local per tenant)

- Morning brief push notification (PRO only)
- Днешни priorities за tenant-а
- Storage cleanup (темп. файлове >1 ден)

## 15.6 cron-evening.php (21:00 local)

- Evening report push (PRO only)
- Днешни insights summary
- Утрешни priorities
- MAX 1 push per ден (Addictive UX)

## 15.7 weather-cache.php (06:00)

- От т. 8.5. Обновява weather_forecast за всички stores с lat/lng.

## 15.8 scanner-queue.php (5 мин)

- Processes pending items в `scanner_queue`
- Обработва FREE tenants които са били в red tier
- Само при API spend < 75 USD за деня

---

# 16. DEPLOY & ОПЕРАЦИИ

## 16.1 Сървър

- **Provider:** DigitalOcean Frankfurt
- **IP:** `164.90.217.120`
- **OS:** Ubuntu 24 LTS
- **Stack:** Apache + PHP 8.3 + MySQL 8
- **RAM:** 2GB ($12/мес) — от S71 upgrade
- **Path:** `/var/www/runmystore/`
- **Log:** `/var/log/runmystore/`

## 16.2 GitHub

- **Repo:** `github.com/tiholenev-tech/runmystore`
- **Branch:** `main`
- **Archive папка:** `/archive/` (стари BIBLE, handoffs, v2 versions)

## 16.3 AI engines

### Primary: Gemini 2.5 Flash
- `GEMINI_API_KEY` (KEY1, jqSU...)
- `GEMINI_API_KEY_2` (KEY2, S5Gc...)
- Failover: KEY1 → 429 → KEY2 → "опитай пак"

### Fallback: OpenAI GPT-4o-mini
- `OPENAI_API_KEY`
- Само при ЛАЙВ Gemini failures

**Claude API е МАХНАТ** (твърде скъп за production).

### Image AI: fal.ai
- `birefnet/v2` за background removal (~€0.015/image → sell at €0.05 = 72% margin)
- `nano-banana-pro/edit` за virtual try-on (~€0.14/image → sell at €0.50 = 72% margin)

## 16.4 Deploy процес (non-negotiable)

### Железни правила:

1. **НИКОГА `sed`** за file modifications — **САМО Python scripts**
2. **НИКОГА частични patches** — винаги пълен файл или targeted Python patch
3. **НИКОГА regex patches** през console
4. **След всеки fix** — git commit+push без питане
5. **ВСЯКА сесия** започва с `git pull origin main`

### Standard workflow:

```bash
# 1. Pull latest
cd /var/www/runmystore && git pull origin main

# 2. Create Python patch script (Tihol runs this)
nano /tmp/s74_fix_sold_30d.py
python3 /tmp/s74_fix_sold_30d.py

# 3. Test (Tihol тества на телефон)

# 4. If OK → commit + push
cd /var/www/runmystore && git add -A && git commit -m "S74: fix sold_30d LEFT JOIN" && git push origin main
```

### config.php deploy (специален случай):

**НИКОГА** не editва `config.php` чрез bash heredoc wrapper. Само:
- `nano` в SSH, или
- GitHub web editor с pure PHP content

## 16.5 Git fetch pattern (когато нужно)

```bash
git fetch https://[token]@github.com/tiholenev-tech/runmystore.git main
git checkout -f FETCH_HEAD -- [file]
```

Полезно когато локалния файл е corrupted или се нуждае от clean revert.

---

# 17. ФАЗИ A-F ROADMAP (S72-S140+)

## 17.1 Фаза A — ФУНДАМЕНТ (S72-S80)

**Цел:** стабилни products.php, wizard, DB invariants, AI таблици.

| Сесия | Задача | Модел | Визия |
|---|---|---|---|
| S72 | ✅ Баркод + product-save.php бъгове | Opus | — |
| S73 | ✅ Вариации create/edit + wizLoadSubcats | Opus | add-product-variations.html |
| S74 | AI Studio + renderWizard + sold_30d | Opus | add-product-ai.html |
| S75 | **Wizard 4 стъпки / 3 accordion FINAL** | Opus | add-product.html + variations + business |
| S76 | Матрица fullscreen overlay | Sonnet | add-product-variations.html |
| S77 | "Както предния" + конвейер | Sonnet | add-product.html |
| S78 | DB invariants (CHECK + TRIGGERS) | Opus | — |
| S79 | Празни AI таблици (audit, transactions, snapshots) | Sonnet | — |
| S80 | Glossary Guardian + Fact Verifier + Retrospective + BIBLE v2.3 | Opus | — |
| S80.5 | Разширен филтър drawer за products.php | Opus | — |

## 17.2 Фаза B — МОДУЛИ (S81-S100)

**Цел:** simple.php, sale.php rewrite, deliveries, orders, inventory v4, transfers, stats, warehouse, chat polish, i18n, Bluetooth печат.

| Сесия | Задача | Модел | Визия |
|---|---|---|---|
| S81 | simple.php (4 бутона + "AI те познава") | Opus | home-detailed/neon/jouan.html |
| S82 | simple.php AI voice FAB | Sonnet | ai-chat.html |
| S83 | sale.php Част 1: камера + numpad | Opus | sale.html |
| S84 | sale.php Част 2: voice + search_log | Opus | sale.html |
| S85 | sale.php Част 3: toast + pills | Sonnet | sale.html |
| S86 | deliveries.php от нулата | Opus | delivery.html |
| S87 | deliveries.php OCR за START+ | Sonnet | delivery.html |
| S88 | deliveries.php ↔ products.php wizard | Sonnet | — |
| S89 | orders.php (матрица) | Sonnet | orders.html |
| S90 | Inventory v4 (нов артикул, review, crash) | Opus | inventory-counting + hub-dialogs |
| S91 | Inventory v4 offline mode | Opus | — |
| S92 | transfers.php | Sonnet | — |
| S93 | stats.php visual update | Sonnet | stats.html |
| S94 | warehouse.php visual update | Haiku | warehouse.html |
| S95 | chat.php "← Опростен" toggle + polish | Haiku | ai-chat.html |
| S96 | i18n ревизия на всички модули | Sonnet | — |
| S97 | Bluetooth printer (универсален print API) | Opus | add-product-print.html |
| S98 | Bluetooth printer pairing UI | Sonnet | add-product-print.html |
| S99 | Performance audit | Opus | — |
| S100 | Checkpoint 80% + adversarial testing старт | Opus | — |

## 17.3 Фаза C — AI ЗАЩИТА (S101-S120)

**Цел:** Имплементация на 6-нивовата защита, Audit log, Recovery Mode.

| Сесия | Задача |
|---|---|
| S101 | OCR Ниво 0 — File quality gate (SHA256 per-user, semantic hash) |
| S102 | OCR Ниво 1 — AI Vision промпт защити |
| S103 | OCR Ниво 2 — PHP математическа верификация |
| S104 | OCR Ниво 3 + 3.5 — Бизнес логика + Semantic validation |
| S105 | OCR Ниво 4 — BG + VIES whitelist |
| S106 | OCR Ниво 5 — Knowledge Base templates |
| S107 | OCR Ниво 6 — User confirmation UI |
| S108 | Audit log — append-only infrastructure |
| S109 | Recovery Mode + Trust Decay |
| S110 | Global Kill Switch + Telegram alerts |
| S111-120 | Voice add protection, AI chat Fact Verifier, bugs |

## 17.4 Фаза D — AI INTELLIGENCE (S121-S140)

**Цел:** 857 AI теми пълен catalog, compute-insights до 100+ функции, AI Navigator, cross-store intelligence.

| Сесия | Задача |
|---|---|
| S121-130 | compute-insights.php разширение (от 30 до 100+ функции) |
| S131-135 | AI Navigator — action buttons за всички 6 модула |
| S136-140 | Cross-store patterns (биз-план), ROI tracking, retrospective |

## 17.5 Фаза E — BETA (S141+)

**Цел:** 10 реални магазина в бета, feedback cycle, polish, marketing materials.

- Launch pe Viber/TikTok/счетоводители
- 100 клиенти за 4 месеца (според timeline)
- Phase 5 — собствен Bluetooth принтер launch

## 17.6 Фаза F — БЪДЕЩИ МОДУЛИ (S200+)

- Shopify integration (Phase 6)
- Marketing модул (Phase 7)
- Счетоводен модул разширение (Phase 8)
- HR / ТРЗ + СУПТО (Phase 9)

---

# 18. 60+ ПРАВИЛА (ОБЕДИНЕНИ)

## 18.1 Code rules (1-34)

1. Пълен файл, никога частичен код
2. `DB::run()` винаги, никога `$pdo`
3. Реални DB полета (виж 14.1): `code`, `retail_price`, `unit_price`, `quantity`, `total`, `status='canceled'`
4. Дизайн: `#030712` bg, indigo, SVG иконки
5. Никога "Gemini" в UI — винаги "AI"
6. Никога TODO/placeholder в production код
7. Multi-part strategy за големи файлове (>500 реда)
8. Verify с `grep -n` преди deploy
9. Git commit след всяка промяна
10. Никога deploy от project knowledge — винаги от server
11. Никога Python patch за JS string concat с вложени кавички
12. Discuss UX/flow before code
13. Full rewrites over partial patches за сложни промени
14. Never regex patches via console
15. `i18n`: никога hardcoded български — винаги `$tenant['language']`
16. `EUR`: никога hardcoded "лв"/"€"/"$" — винаги `priceFormat($amount, $tenant)`
17. Fallback currency = EUR (не BGN!)
18. Bluetooth/печат: универсален print API, не TSPL/ESC-POS специфика
19. Voice overlay: blur overlay, не fullscreen block
20. Микрофонът никога не блокира екрана
21. Bottom nav: SVG иконки, без glow, label "Продажба" (не "Въвеждане")
22. Plan enforcement: `effectivePlan($tenant)` навсякъде (не subscriptions таблица)
23. Git pull в началото на всяка сесия
24. mysqldump backup преди DB промени
25. `GlossaryGuardian::sanitize()` на всеки AI output
26. `FactVerifier::verify()` при PRO insights
27. Idempotency key за всяко AI action
28. Auto action бутони — code-driven, не AI-generated
29. Chat state persistence в sessionStorage
30. AI не отрязва списъци (≤5 всички, >5 top5 + N)
31. Пълен файл страта се валидира преди commit
32. Failover chain: Gemini KEY1 → KEY2 → OpenAI → "опитай пак"
33. Никога Claude API в production (твърде скъп)
34. Confidence class задължителен в AI response

## 18.2 Inventory rules (35-49)

35. Никога не изисквай пълна инвентаризация
36. Stepper винаги, клавиатура никога
37. Auto-save на всеки 10 сек
38. Confidence score винаги видим
39. Duplicate warning при повторно въвеждане
40. Filter chips за по-бърза работа
41. Barcode scanner винаги опция
42. Pause/Resume — никога не блокирай Пешо
43. Offline-first (IndexedDB pending lines)
44. Fast-add в 4 стъпки (ако артикулът не съществува)
45. Zone-level display (не целия магазин)
46. Discrepancy ≥ 1 = warning
47. 2 поредни zone walks с 0 discrepancies → decay спира
48. 1-2 месеца без нови открития → инвентаризация complete
49. 95%+ confidence → CTA карти изчезват

## 18.3 Voice rules (50-56)

50. 🎤 бутон до всяко поле освен numeric/size/color
51. SpeechRecognition API lang=`bg-BG`, continuous=false, interimResults=true
52. Voice overlay с blur (`backdrop-filter:blur(8px)`), не fullscreen
53. Rec indicator: червен pulse + "● ЗАПИСВА" / зелен + "✓ ГОТОВО"
54. Transcript box с реалновременна трансkripция
55. "Изпрати →" бутон само при наличен текст
56. Voice flows — auto-next field след "Приеми" (chained input)

## 18.4 Wizard rules (69-83)

Виж т. 2.3. Основни:
69. Full file не partial patches за products.php
70. Never reset `wizData` в `renderWizard`
71. Test voice input нова стойност и overwrite съществуваща
72. Progressive disclosure (3 accordion нива или 4 стъпки — S75 решение)
73. "Както предния" (`_rms_lastWizProducts` localStorage)
74. Конвейер (запазва категория/надценка/доставчик между save-ове)
75. Микрофон навсякъде (освен numeric/size/color)
76. Печат след всяка стъпка (€+лв / само € / без цена)
77. Произход полето само при печат
78. Accordion open/closed според данни
79. Запис на всяка стъпка (S71 philosophy)
80. Undo toast 5 секунди
81. Никога без потвърждение за "Както предния"
82. Fullscreen matrix overlay за вариации (sticky headers)
83. autoMin формула: qty≤3→1, qty≤7→qty/2.5 round, etc

## 18.5 AI behavior rules (57-68)

57. "Число + Защо + Меко предложение" — формула за всеки отговор
58. Забранени думи: ROI, KPI, churn, optimize, марж (→ "чиста печалба")
59. Забранени императиви: "направи", "поръчай", "пусни"
60. Забранени прогнози: "ще спечелиш", "ще загубиш"
61. Response types: FACT / DIAGNOSIS / RECOMMENDATION (с badges)
62. Confidence classes A-E (E = тишина)
63. 1/4 отваряния = тишина (Addictive UX)
64. Максимум 3 тригера наведнъж
65. Максимум 3 action бутона на bubble
66. Cooldown по topic_id (виж 6.6)
67. Деескалация при "не" (7 дни → 30 дни → mute)
68. Минимални данни за съвет: 14+ дни тенденция

---

# 19. OPERATIONS — КАК РАБОТИМ

## 19.1 Роли

**Tihol** — founder, sole developer-collaborator, първи beta client.
- Не е developer от професия
- Нуждае се от команди ЕДНА ПО ЕДНА, ясно обяснени
- Maximum краткост
- Винаги пълен файл, никога partial patches
- Работи 10-12 часа/ден
- Говори директно, неформално български, често all-caps
- На мобилен телефон често (команди да се дават стъпка по стъпка)

**Claude (AI partner)** — sessions с различни модели:
- **Opus 4.7** — сложна логика, архитектура, wizard rewrites
- **Sonnet 4.6** — standard feature work
- **Haiku 4.5** — CSS, текст, дребни polish

## 19.2 Модели — кога се ползва кой

| Задача | Модел |
|---|---|
| Нова архитектурна промяна | Opus 4.7 |
| Wizard UI rewrite | Opus 4.7 |
| AI Safety implementation | Opus 4.7 |
| Bug fix с множество файла | Opus 4.7 |
| Нов модул (от нулата) | Opus 4.7 |
| Standard feature add | Sonnet |
| CSS tweaking | Haiku |
| Текстови корекции | Haiku |

**Правило:** ако не знаеш → **Sonnet**.

## 19.3 Стартов промпт за нова сесия

```
Прочети по ред:
1. OPERATING_MANUAL.md
2. NARACHNIK_TIHOL_v1_1.md
3. BIBLE_v3_0_CORE.md
4. BIBLE_v3_0_TECH.md
5. SESSION_XX_HANDOFF.md (последния)

Задачата е S[XX]: [описание].
```

## 19.4 HTML визии workflow

Когато в сесия има маркер ⚠️ **ДАЙ ВИЗИЯ** — Тихол копира съответния HTML от друг чат (16.04 визии) и paste-ва.

### 13 HTML визии в проекта:

```
add-product-ai.html, add-product-business.html, add-product-print.html
index.html, about.html, blog.html, blog-post.html, contact.html
help.html, newsletter.html, 404.html
signin.html, signup.html, reset-password.html
```

## 19.5 Handoff документи

В края на всяка сесия → `SESSION_XX_HANDOFF.md` съдържащ:
- Git commit hash
- Какво е направено
- Какво остава (известни бъгове)
- Следваща сесия task
- Lessons learned

## 19.6 Стоп сигнали

Тихол прекъсва сесията при:
- Claude пише код преди документите
- Claude пише UI код без HTML визия (при ⚠️ сесия)
- Claude предлага пълен rewrite без нужда
- Claude използва `sed`
- Claude пише "Gemini" в UI
- Hardcoded български
- Измислени DB колони (`sku` вместо `code`)
- Забравен git commit
- 30 реда patch вместо пълен файл

Когато Тихол прекъсва → нов чат → същия стартов текст → продължава.

## 19.7 Седмичен ритуал

**Всяка седмица (петък):**
- Преглед на сесиите
- Handoff-ите в Claude Project
- Цена за AI (Gemini + Claude)

**Всяка фаза (S80, S100, S120, S140):**
- Нова BIBLE версия (ADDITIONS или refresh)
- Retrospective
- Нов наръчник за следващата фаза

## 19.8 Текущ статус (към 17.04.2026, след S73)

- **Последен commit:** `247c320` (S73)
- **products.php:** ~5919 реда
- **Сървър RAM:** 2GB (от S71)
- **Test tenant:** `tenant_id=7`, `store_id=47`, role='owner', business_type='дрехи — луксозни'
- **Real client:** `tenant_id=52` (ЕНИ)
- **Next session:** S73-CONT (UI rewrite на wizard вариации стъпка)

---

---

# 20. INTERNATIONALIZATION — TECHNICAL CHECKLIST

## 20.1 Защо това е критичен раздел

**Сегашното състояние (към S73):** Продуктът е написан само за България. Hardcoded български текстове на много места. AI отговаря на български. Voice работи само на bg-BG.

**За да скалираме в Европа** (CORE §6 Territory Model + §30 Phased Rollout) → трябват **6 технически блока**, всеки със свой own roadmap.

### 🎯 Стратегия: AI-first translation + 2-3 markets initially

**НЕ правим full 20-language i18n преди да имаме customers на тези езици.**

Вместо "32 сесии за 20 езика" → **10-12 сесии за infrastructure + 2-3 launch markets**.

**Timeline (ускорен):**

| Седмица | Задача | Tool |
|---|---|---|
| 1 | Extract 1,280 strings + Weblate setup | Custom extractor script + Weblate |
| 2-3 | AI translate 20 езика (batch) | GPT-4o-mini, ~$10 total |
| 4-5 | Native speaker review (Territory Partners) | Weblate interface |
| 6 | Voice prompts + SSML testing | Browser TTS + Whisper test |
| 7-8 | VAT/invoice formats (5 initial markets) | PHP country-config files |
| 9-10 | Accounting exports (Saga, DATEV, Pohoda) | CSV generators |
| 11-12 | Integration testing | Playwright E2E |

**Launch markets (initial):**
- BG (native)
- RO (first expansion)
- GR или PL (based on Territory Partner availability)

**Infrastructure ready for 20 езика, но activated только за 3 initially.**

## 20.2 5 отделни подзадачи (Kimi insight)

"i18n" не е **една задача** — това са **5 различни подзадачи** с различна сложност:

| Подзадача | Сложност | Tool | Сесии |
|---|---|---|---|
| **1. UI translation** | Ниска | AI batch translate (GPT-4o-mini) | 2 |
| **2. Prompt localization** | Средна | Manual per language | 2 |
| **3. Speech aliases** | Средна | Per-language synonym dicts | 2 |
| **4. Number/date/currency formatting** | Ниска | PHP Intl | 1 |
| **5. Legal/tax/accounting localization** | **ВИСОКА (истинският ад)** | Expert-driven per country | 5 per country |

**Само задачи 1-4 са "лесни" с tooling.** Задача 5 изисква **локален счетоводител-партньор** за всяка държава.

**Препоръка:** не пускай нова държава **без** Territory Partner който познава местното счетоводство.

## 20.3 AI Translation Service (Kimi implementation)

```php
class AITranslationService {
    public function translateBatch($texts, $targetLang) {
        // Батчиране с контекст е КРИТИЧНО за качеството
        $prompt = "Translate these UI strings for a retail POS app.

        Context: Voice-first mobile app for small shop owners.

        Rules:
        - Keep imperative tone (commands, not suggestions)
        - Max 30 chars for button labels
        - Use formal 'you' (Bulgarian: 'Вие', не 'ти')
        - Currency stays numeric, format per locale
        - Preserve {placeholders} exactly
        - Preserve HTML tags exactly

        Return JSON object: {key: translated_text, ...}

        Strings to translate:
        " . json_encode($texts, JSON_UNESCAPED_UNICODE);

        // 50 texts for ~$0.01-0.03 (GPT-4o-mini)
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => "You are a professional UI translator for retail software."],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object']
        ]);

        return $this->parseTranslation($response);
    }
}
```

**Cost breakdown:**
- 1,280 UI strings × 20 languages = 25,600 translations
- ~$0.0005 per translation = **$12.80 total for all 20 languages**
- Plus review time (native speakers) = 2-3 days

## 20.4 Текуща готовност vs нужно състояние

| Компонент | Сегашно | Нужно (за 5 езика) | Сесии |
|---|---|---|---|
| UI текстове | hardcoded BG | 5 езика JSON | 8 |
| AI prompts | BG focused | Multi-lang | 1 |
| Voice recognition | bg-BG | Map по език | 1 |
| Currency formats | EUR + лв (BG) | 10+ валути | 2 |
| Date formats | dd.mm.YYYY | По локал | included |
| VAT rates | 20% (BG) | По държава | 5 (за 5 страни) |
| Invoice formats | BG EIK | По държава | included |
| Счетоводен export | Microinvest/Sigma/Ajur | По държава | 12 (за 5 страни) |
| Email templates | BG only | По език | 1 (i18n keys) |
| Push notifications | BG | По език | 0 (използват i18n) |
| **ОБЩО (full scope, 5 launch markets)** | | | **~32 сесии theoretical / 10-12 actual с AI translate** |

## 20.3 БЛОК 1 — i18n Foundation (UI текстове)

### Цел:
Никакви hardcoded текстове. Всичко през `t($key, $tenant)` helper.

### Файлове за извличане (по приоритет):

| Файл | Hardcoded текстове (приблизително) | Сесии |
|---|---|---|
| products.php | ~400 | 2 |
| chat.php | ~150 | 1 |
| sale.php | ~200 | 1 |
| warehouse.php | ~100 | 0.5 |
| stats.php | ~80 | 0.5 |
| inventory.php | ~120 | 1 |
| settings.php | ~80 | 0.5 |
| AI чат отговори | ~100 templates | 1 |
| Email templates | ~50 emails | 0.5 |
| **ОБЩО** | **~1,280 ключа** | **8 сесии** |

### Структура на JSON файлове:

`/var/www/runmystore/lang/bg.json`:
```json
{
  "common": {
    "save": "Запази",
    "cancel": "Откажи",
    "delete": "Изтрий",
    "yes": "Да",
    "no": "Не"
  },
  "products": {
    "add_new": "Нов артикул",
    "search_placeholder": "Търсене...",
    "count_summary": "{count} артикула"
  },
  "ai": {
    "morning_briefing": "Добро утро, {name}!",
    "no_data": "Все още нямам достатъчно данни"
  }
}
```

### Helper:

```php
// config/helpers.php
function t($key, $tenant, $params = []) {
    static $cache = [];
    $lang = $tenant['language'] ?? 'bg';

    if (!isset($cache[$lang])) {
        $path = __DIR__ . "/../lang/{$lang}.json";
        if (!file_exists($path)) {
            $path = __DIR__ . "/../lang/en.json";  // fallback
        }
        $cache[$lang] = json_decode(file_get_contents($path), true);
    }

    // Dot notation lookup: "products.add_new"
    $value = $cache[$lang];
    foreach (explode('.', $key) as $part) {
        if (!isset($value[$part])) {
            // Fallback to English
            if ($lang !== 'en') {
                return t($key, ['language' => 'en'] + $tenant, $params);
            }
            return $key;  // Return key itself as last resort
        }
        $value = $value[$part];
    }

    foreach ($params as $k => $v) {
        $value = str_replace("{{$k}}", $v, $value);
    }
    return $value;
}
```

### Приоритетен ред на езици:

1. bg.json (BG, master template)
2. en.json (fallback за всичко)
3. ro.json (Румъния — Phase 4)
4. el.json (Гърция — Phase 5)
5. pl.json (Полша — Phase 5)
6. de.json (Германия — Phase 5)
7. it.json (Италия — Phase 5)

### Детектиране на hardcoded текст:

```bash
# Скрипт за намиране на hardcoded BG текст
grep -rn '[а-я]\{3,\}' /var/www/runmystore --include='*.php' \
  | grep -v 'lang/' \
  | grep -v 'comment' \
  > /tmp/hardcoded_bg.txt

# Резултат: списък на всички места с >2 кирилични букви подред
```

## 20.4 БЛОК 2 — AI Multi-Language Prompts

### Цел:
AI отговаря на езика на tenant-а, не само на български.

### Имплементация в build-prompt.php:

```php
$lang_name_map = [
    'bg' => 'Bulgarian',
    'en' => 'English',
    'ro' => 'Romanian',
    'el' => 'Greek',
    'pl' => 'Polish',
    'de' => 'German',
    'it' => 'Italian',
    'fr' => 'French',
    'es' => 'Spanish',
    'cs' => 'Czech',
    'hu' => 'Hungarian',
    'sk' => 'Slovak',
    'sr' => 'Serbian',
    'hr' => 'Croatian',
    'sl' => 'Slovenian'
];

$lang_name = $lang_name_map[$tenant['language']] ?? 'English';

$prompt .= "\n\n=== LANGUAGE INSTRUCTION ===\n";
$prompt .= "RESPOND ONLY IN {$lang_name}. Use natural, conversational style. ";
$prompt .= "Do not mix languages. Use local idioms and currency.\n";
```

### Качество по език (тестване):

| Език | Очаквано качество | Тестване |
|---|---|---|
| Английски | Отлично (95%+) | 1 ден |
| Немски, испански, италиански, френски | Много добро (90%+) | 1 ден всеки |
| Български, румънски, полски | Много добро (85%+) | 1 ден всеки |
| Гръцки, чешки, унгарски | Добро (80%+) | 2 дни всеки |
| Сръбски, словашки, словенски | Приемливо (70%+) | 2 дни всеки |

**Тестването = native speaker оценява 30 random AI отговора.**

## 20.5 БЛОК 3 — Voice Language Mapping

### Цел:
SpeechRecognition да работи на всеки език.

### Имплементация:

```javascript
const langMap = {
    'bg': 'bg-BG',
    'en': 'en-US',
    'ro': 'ro-RO',
    'el': 'el-GR',
    'pl': 'pl-PL',
    'de': 'de-DE',
    'it': 'it-IT',
    'fr': 'fr-FR',
    'es': 'es-ES',
    'cs': 'cs-CZ',
    'hu': 'hu-HU',
    'sk': 'sk-SK',
    'sr': 'sr-RS',
    'hr': 'hr-HR',
    'sl': 'sl-SI'
};

function startVoice(tenantLang) {
    const recognition = new webkitSpeechRecognition();
    recognition.lang = langMap[tenantLang] || 'en-US';
    recognition.interimResults = true;
    // ...
}
```

### Качество по език (Chrome Web Speech API):

| Език | Accuracy | Препоръка |
|---|---|---|
| en-US, de-DE, es-ES, it-IT, fr-FR | 95%+ | Voice-first OK |
| bg-BG, ro-RO, pl-PL, el-GR | 80-90% | Voice + auto-fallback (виж TECH §3.6) |
| cs-CZ, hu-HU, sk-SK | 70-85% | Voice optional, keyboard prominent |
| sr-RS, hr-HR, sl-SI | 60-75% | Keyboard primary, voice optional |

**За "слаби" езици → auto-fallback на keyboard след 2 неуспеха** (виж TECH §3.6 — Voice fallback).

## 20.6 БЛОК 4 — Currency и Date Formats

### Цел:
Правилно показване на цени и дати по локал.

### Currency map:

```php
$currency_map = [
    'BG' => 'EUR',  // от 1.1.2026 (виж CORE §3.2)
    'GR' => 'EUR',
    'DE' => 'EUR',
    'IT' => 'EUR',
    'ES' => 'EUR',
    'FR' => 'EUR',
    'AT' => 'EUR',
    'NL' => 'EUR',
    'BE' => 'EUR',
    'PT' => 'EUR',
    'RO' => 'RON',  // Lei
    'PL' => 'PLN',  // Złoty
    'CZ' => 'CZK',  // Koruna
    'HU' => 'HUF',  // Forint
    'SE' => 'SEK',
    'DK' => 'DKK',
    'GB' => 'GBP',
    'US' => 'USD',
    'CH' => 'CHF',
    'NO' => 'NOK',
];
```

### priceFormat() helper:

```php
function priceFormat($amount, $tenant) {
    $currency = $tenant['currency'] ?? 'EUR';
    $country = $tenant['country_code'] ?? 'BG';

    // BG dual labeling до 8.8.2026
    if ($country === 'BG' && date('Y-m-d') < '2026-08-08') {
        $eur = number_format($amount, 2, '.', ' ');
        $bgn = number_format($amount * 1.95583, 2, '.', ' ');
        return "{$eur} € / {$bgn} лв";
    }

    // Decimal/thousand separators по локал
    $formats = [
        'DE' => ['decimal' => ',', 'thousand' => '.', 'symbol_pos' => 'after'],
        'AT' => ['decimal' => ',', 'thousand' => '.', 'symbol_pos' => 'after'],
        'PL' => ['decimal' => ',', 'thousand' => ' ', 'symbol_pos' => 'after'],
        'CZ' => ['decimal' => ',', 'thousand' => ' ', 'symbol_pos' => 'after'],
        'HU' => ['decimal' => ',', 'thousand' => ' ', 'symbol_pos' => 'after'],
        'GB' => ['decimal' => '.', 'thousand' => ',', 'symbol_pos' => 'before'],
        'US' => ['decimal' => '.', 'thousand' => ',', 'symbol_pos' => 'before'],
    ];
    $fmt = $formats[$country] ?? ['decimal' => '.', 'thousand' => ' ', 'symbol_pos' => 'after'];

    $symbol = ['EUR' => '€', 'RON' => 'lei', 'PLN' => 'zł', 'CZK' => 'Kč',
               'HUF' => 'Ft', 'GBP' => '£', 'USD' => '$', 'SEK' => 'kr',
               'DKK' => 'kr', 'CHF' => 'CHF', 'NOK' => 'kr'][$currency] ?? $currency;

    $formatted = number_format($amount, 2, $fmt['decimal'], $fmt['thousand']);

    return $fmt['symbol_pos'] === 'before'
        ? "{$symbol}{$formatted}"
        : "{$formatted} {$symbol}";
}
```

### dateFormat() helper:

```php
function dateFormat($date, $tenant, $format_type = 'short') {
    $country = $tenant['country_code'] ?? 'BG';

    $patterns = [
        'BG' => ['short' => 'd.m.Y', 'long' => 'd F Y'],
        'RO' => ['short' => 'd.m.Y', 'long' => 'd F Y'],
        'PL' => ['short' => 'd.m.Y', 'long' => 'd F Y'],
        'DE' => ['short' => 'd.m.Y', 'long' => 'd. F Y'],
        'IT' => ['short' => 'd/m/Y', 'long' => 'd F Y'],
        'FR' => ['short' => 'd/m/Y', 'long' => 'd F Y'],
        'ES' => ['short' => 'd/m/Y', 'long' => 'd F Y'],
        'GB' => ['short' => 'd/m/Y', 'long' => 'd F Y'],
        'US' => ['short' => 'm/d/Y', 'long' => 'F d, Y'],
    ];

    $pattern = $patterns[$country][$format_type] ?? 'Y-m-d';
    return date($pattern, is_numeric($date) ? $date : strtotime($date));
}
```

## 20.7 БЛОК 5 — Локални VAT и Invoice Formats

### Цел:
OCR на фактури работи за всяка държава. ДДС изчисления са правилни.

### Country-specific config файлове:

`/var/www/runmystore/lib/country-config/RO.php`:
```php
return [
    'vat_id_pattern' => '/^RO?\d{2,10}$/',
    'vat_id_validator' => 'validateRomanianCUI',
    'vat_rates' => [19, 9, 5, 0],
    'standard_vat' => 19,
    'invoice_required_fields' => ['serie', 'numar', 'data', 'CUI_furnizor', 'CUI_client', 'TVA'],
    'date_format' => 'd.m.Y',
    'phone_format' => '/^\+?40\d{9}$/',
    'company_registry' => 'ANAF',
    'language' => 'ro',
    'currency' => 'RON',
    'electronic_invoicing' => 'e-Factura',  // ANAF задължително от 2024
];
```

### ДДС rates по държава:

| Държава | Стандартен | Алтернативни | Особености |
|---|---|---|---|
| България | 20% | 9% (туризъм), 0% (export) | EIK 9 или 13 цифри |
| Румъния | 19% | 9%, 5% | CUI, e-Factura задължителен |
| Гърция | 24% | 13%, 6% | АФМ, myDATA задължителен |
| Полша | 23% | 8%, 5%, 0% | NIP, JPK файлове |
| Чехия | 21% | 15%, 10% | DIČ |
| Унгария | 27% (НАЙ-ВИСОК В ЕС) | 18%, 5% | Online Számla |
| Германия | 19% | 7% | USt-IdNr, GoBD compliance |
| Италия | 22% | 10%, 5%, 4% | Codice Fiscale + Partita IVA + Fatturapa |
| Испания | 21% | 10%, 4% | NIF/CIF |
| Франция | 20% | 10%, 5.5%, 2.1% | SIREN/SIRET, Factur-X |

### Roadmap (per state):

- 1 ден изследване (юрист или local accountant консултация)
- 1 сесия имплементация (config + validators)
- = **5 сесии за 5 държави + 1 ден изследване всяка**

## 20.8 БЛОК 6 — Локални Счетоводни CSV Exports

### Цел:
Sub-партньорите (счетоводители) могат да exportват данни в техния local софтуер.

### Текущо състояние:
- ✅ Microinvest (BG) — готов
- ✅ Sigma (BG) — готов
- ✅ Ajur (BG) — готов
- ❌ Всички останали — липсват

### Топ счетоводни софтуери по държава:

| Държава | Топ 3 софтуера | Сесии |
|---|---|---|
| България | Microinvest, Sigma, Ajur | ✅ Готов |
| Румъния | Saga, WinMentor, NextUp | 3 |
| Гърция | Singular, SoftOne, Galaxy | 3 |
| Полша | Comarch, Sage Symfonia, Insert | 3 |
| Чехия | Pohoda, Money S3 | 2 |
| Унгария | RLB, Kulcs-Soft | 2 |
| Германия | DATEV, Lexware, Sage | **3** (DATEV е критичен!) |
| Италия | TeamSystem, Zucchetti, Fatturapa | 3 (Fatturapa = electronic invoicing!) |
| Испания | A3, Sage, Holded | 3 |
| Франция | Sage, Cegid, EBP | 3 |

**За първа година (5 държави):** ~12 сесии.

### Architecture:

```php
interface AccountingExporter {
    public function export(array $invoices, array $options = []): string;
    public function getFormat(): string;  // 'csv', 'xml', 'json'
    public function getExtension(): string;
}

class SagaExporter implements AccountingExporter {
    public function export(array $invoices, array $options = []): string {
        // Saga-specific format
        $csv = "Data;Furnizor;CUI;Numar;Suma;TVA\n";
        foreach ($invoices as $inv) {
            $csv .= sprintf("%s;%s;%s;%s;%.2f;%.2f\n",
                $inv['date'], $inv['supplier'], $inv['cui'],
                $inv['number'], $inv['total'], $inv['vat']);
        }
        return $csv;
    }
    public function getFormat(): string { return 'csv'; }
    public function getExtension(): string { return 'csv'; }
}

// Usage:
$exporter = ExporterFactory::get($tenant['country_code'], $tenant['preferred_accounting']);
$file = $exporter->export($invoices);
```

## 20.9 ROADMAP — 32 СЕСИИ ОБЩО

### Месец 1 (Фаза 2 паралелно):
- Сесии S81-S88 (8): Блок 1 — i18n extract на всички файлове
- Резултат: bg.json + en.json готови, всички файлове ползват t() helper

### Месец 2:
- Сесии S89-S90 (2): Блок 2 + Блок 3 — AI multi-lang + voice mapping
- Сесии S91-S92 (2): Блок 4 — Currency/date formats
- Резултат: продуктът работи на английски, voice работи на 5+ езика

### Месец 3-4 (Фаза 3 завършваща):
- Сесии S93-S100 (8): Блок 5 — VAT/invoice за 5 държави
- Резултат: OCR работи за RO, GR, PL, CZ, DE

### Месец 5-6 (Фаза 4):
- Сесии S101-S112 (12): Блок 6 — Счетоводни exports за 5 държави
- Резултат: Sub-партньори в RO, GR, PL, CZ, DE могат да правят CSV exports

**Общо:** **10-12 сесии** при AI-first стратегия (виж §20.1) или 32 сесии при manual translation за **пълна готовност за 5 чужди пазара**.

При AI-first темп **2 сесии/седмица** → **6 седмици работа** (вместо 4 месеца).
При темп **3-4 сесии/ден** → **1 седмица работа**.

## 20.10 STOP RULES

В коя ситуация **СПИРАМЕ** работата по нов език:

| Ситуация | Действие |
|---|---|
| Voice accuracy < 60% за този език | Не пускаме voice, само keyboard |
| Native translator не е достъпен | Не пускаме този език |
| Локален VAT/invoice format не е ясен | Изчакваме юрист преди продължение |
| Нямаме Territory Partner за тази държава | Не плащаме за пълна локализация |

**Не правим всичко за всички — фокус на готовност.**


---

# 21. TESTING STRATEGY — CAPABILITY MATRIX

## 21.1 Защо не testing по държави

**Грешно мислене:**
```
12 държави × 10 модула = 120 тестови набори
```

Това води до **combinatorial explosion** — impossible за един developer да поддържа.

## 21.2 Правилен подход — Capability Matrix

Разделяме тестовете по **capability axes**, не по държави.

### Capability axes (8):

| Ос | Стойности |
|---|---|
| **language** | bg, en, ro, el, pl, de, it, fr, es, cs, hu, sk, sr, hr, sl |
| **currency** | EUR, RON, PLN, CZK, HUF, RSD, BGN (legacy) |
| **vat_regime** | 20%, 19%, 24%, 23%, 21%, 27% etc. |
| **invoice_template** | BG_EIK, RO_CUI, GR_AFM, PL_NIP, DE_USt, etc. |
| **accounting_export** | Microinvest, Saga, DATEV, Pohoda, Singular, Comarch |
| **voice_locale** | bg-BG, ro-RO, de-DE, sr-RS, etc. |
| **payment_provider** | Stripe (primary), Revolut, PayPal |
| **printer_adapter** | ESC/POS, TSPL (experimental) |

### Защо matrix мислене е по-добро

Добавянето на нова държава **НЕ Е** добавяне на нови тестове — това е само:
- Нов locale pack
- Нов tax profile
- Нов export adapter
- Вече съществуващи тестове **automatically run** на новата държава

## 21.3 5 Test Layers

### Layer 1 — Domain Tests (Pure PHP Unit Tests)

**Цел:** Математическа коректност на бизнес логика.

```php
// tests/Unit/VATCalculatorTest.php
class VATCalculatorTest extends TestCase {
    public function testBGStandardVAT() {
        $calc = new VATCalculator(['country' => 'BG', 'vat_rate' => 20]);
        $this->assertEquals(20.00, $calc->calculate(100.00));
    }

    public function testROReducedVAT() {
        $calc = new VATCalculator(['country' => 'RO', 'vat_rate' => 9]);
        $this->assertEquals(9.00, $calc->calculate(100.00));
    }

    public function testHUHighestVAT() {
        $calc = new VATCalculator(['country' => 'HU', 'vat_rate' => 27]);
        $this->assertEquals(27.00, $calc->calculate(100.00));
    }
}
```

**Тестват се:**
- VAT изчисления (всички rates × всички countries)
- Invoice totals (subtotal + VAT = total)
- Currency conversions & rounding (EUR ↔ BGN, PLN, RON)
- Commission ledger calculations (50% × 6, 15% forever)
- Stock conflict engine (delta, count, adjustment resolutions)

**Брой тестови случаи:** ~50

### Layer 2 — Golden Master Tests (UI + Exports)

**Цел:** Да хваща regressions в формати.

```php
// tests/GoldenMaster/ExportTest.php
class ExportTest extends TestCase {
    public function testDATEVExportFormat() {
        $invoice = $this->generateInvoice('DE');
        $csv = $this->export('DATEV', $invoice);

        // Compare with stored "golden master" file
        $expected = file_get_contents(__DIR__ . '/masters/de_datev.csv');
        $this->assertEquals($expected, $csv);
    }

    public function testPohodaXMLFormat() {
        $invoice = $this->generateInvoice('CZ');
        $xml = $this->export('Pohoda', $invoice);
        $expected = file_get_contents(__DIR__ . '/masters/cz_pohoda.xml');
        $this->assertEquals($expected, $xml);
    }
}
```

**Golden Master файлове** в репото:
```
/tests/masters/
  ├── de_datev.csv
  ├── ro_saga.csv
  ├── cz_pohoda.xml
  ├── gr_singular.csv
  ├── pl_comarch.csv
  └── bg_microinvest.csv
```

**Брой тестови случаи:** ~20

### Layer 3 — API Contract Tests

**Цел:** API response structure НЕ се чупи при промени.

```php
// tests/Contract/InventoryAPITest.php
class InventoryAPITest extends TestCase {
    public function testInventoryResponseStructure() {
        $response = $this->get('/api/inventory?lang=sr&country=RS');

        $this->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'quantity', 'price', 'currency']
            ],
            'meta' => ['currency', 'vat_rate', 'locale']
        ]);

        // Country-specific assertions
        $this->assertEquals('RSD', $response->json('meta.currency'));
    }

    public function testStripeWebhookHandler() {
        // Mock Stripe event
        $event = $this->createInvoicePaidEvent();

        $response = $this->postJson('/webhooks/stripe', $event);

        $this->assertEquals(200, $response->status());
        $this->assertDatabaseHas('stripe_events', [
            'event_id' => $event['id'],
            'event_type' => 'invoice.paid'
        ]);
    }
}
```

**Брой тестови случаи:** ~15

### Layer 4 — E2E Smoke Tests (Playwright)

**Цел:** Критични user journeys работят end-to-end.

**НЕ тестваме всичко.** Избираме **3 критични пътеки** които покриват 80% от value:

```javascript
// tests/e2e/critical-paths.spec.js

test('Path 1: Onboarding → First sale → Print receipt', async ({ page }) => {
    await page.goto('/onboarding');
    await page.fill('[data-testid="business-type"]', 'clothing');
    await page.click('[data-testid="continue"]');
    // ... full flow
    await page.goto('/sale');
    await page.fill('[data-testid="product"]', 'Nike 42');
    await page.click('[data-testid="complete-sale"]');
    await expect(page.locator('[data-testid="receipt"]')).toBeVisible();
});

test('Path 2: Add product → Receive delivery → Check stock', async ({ page }) => {
    // Full product lifecycle
});

test('Path 3: View stats → Ask AI → Get answer', async ({ page }) => {
    // AI integration test
});
```

**Multi-language smoke test:**
```javascript
test('Product list in 12 languages', async ({ page }) => {
    for (const lang of ['bg', 'ro', 'el', 'pl', 'de', 'cs', 'hu', 'sr', 'hr', 'sl', 'en', 'it']) {
        await page.goto(`/products?lang=${lang}`);
        // Visual regression с screenshot
        await expect(page).toHaveScreenshot(`products-${lang}.png`, {
            maxDiffPixels: 100  // tolerance за rendering differences
        });
    }
});
```

**Брой E2E flow:** ~10 (3 критични × 3 локала + 1 multi-lang smoke)

### Layer 5 — Canary Release с Territory Partners

**Това е gemчето на стратегията ни** — използваме Territory Partners като real-world QA.

```php
class CanaryRelease {
    public function deploy($featureName, $country) {
        // Стъпка 1: Деплой само към territory partner в държавата
        $partner = Partner::where('country', $country)
            ->where('type', 'territory')
            ->first();

        FeatureFlag::enableForUsers($featureName, [$partner->user_id]);

        // Стъпка 2: Изпрати notification
        $partner->notify(new NewFeatureForTesting($featureName, [
            'test_guide_url' => '/partner/test-guide/' . $featureName,
            'deadline_hours' => 48
        ]));

        // Стъпка 3: След 48 часа — auto-expand ако няма issues
        $this->scheduleAutoExpansion($featureName, $country, hours: 48);
    }

    public function expandAfterApproval($featureName, $country) {
        $partner = $this->getTerritoryPartner($country);

        if ($partner->hasApprovedFeature($featureName)) {
            // General availability за цялата държава
            FeatureFlag::enableForCountry($featureName, $country);
            $this->notifyAllUsersInCountry($country, $featureName);
        } else {
            // Partner върна issues → hold release
            $this->notifyDevTeam($featureName, $partner->getFeedback());
        }
    }
}
```

### UX за Territory Partners

Territory Partner получава:

```
📬 Нова функция за тестване: "Bluetooth printer auto-pair"

🎯 Срок: 48 часа до general release в твоята територия

📋 Test guide:
  1. Свържи Bluetooth принтер
  2. Провери че auto-pairing работи
  3. Тествай печат на 5 различни етикета
  4. Провери print-quality

  [Тествам сега] [Намерих проблем] [Одобрявам за всички]
```

Ако partner одобри → feature пуска се за всички в страната.
Ако partner намери issue → **feature holds** → дев team получава детайли.

## 21.4 Парallel CI Pipeline

```yaml
# .github/workflows/test.yml
name: Test Matrix

on: [push, pull_request]

jobs:
  unit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Domain Unit Tests
        run: phpunit --testsuite Unit

  golden_master:
    strategy:
      matrix:
        country: [BG, RO, GR, PL, DE, CZ, HU]
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Golden Master Tests
        run: phpunit --filter=GoldenMaster --group=${{ matrix.country }}

  contract:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: API Contract Tests
        run: phpunit --testsuite Contract

  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: E2E Smoke Tests
        run: npx playwright test critical-paths
```

**Parallel execution:** ~15 min за full test suite на 7 държави.

## 21.5 Общ брой тестове (реалистично за solo developer)

| Layer | Тестови случаи | Поддръжка |
|---|---|---|
| L1: Unit | ~50 | Автоматично — добавям при нова логика |
| L2: Golden Master | ~20 | Обновяване при intentional format changes |
| L3: Contract | ~15 | Стабилни, рядко променяни |
| L4: E2E Smoke | ~10 | 3 критични пътеки × 3 локала |
| L5: Canary Partners | 0 (manual QA от partners) | Semi-automated |
| **ОБЩО** | **~95 тестови случая** | Manageable за един developer |

**Не 600+ (теоретично покритие), а 95 (реалистично).**

## 21.6 Testing calendar

| Честота | Какво | Автоматизация |
|---|---|---|
| На всеки commit | L1 + L3 (unit + contract) | CI auto |
| Преди release | L1 + L2 + L3 + L4 | CI auto |
| На нова държава | L2 + Territory Partner canary | Manual trigger |
| На нов feature | Всичко + Territory Partner canary | Manual trigger |
| Weekly | Regression tests | CI scheduled |

## 21.7 Никога не го правим

❌ Manual testing като primary defense
❌ "Territory Partners ще тестват" без automated foundation
❌ E2E за всяка permutation на capability axes (overkill)
❌ Golden master без tolerance за minor formatting changes

## 21.8 Fixtures per country

```php
// tests/Fixtures/CountryFixtures.php
class CountryFixtures {
    public static function seedRomania() {
        return [
            'tenant' => [
                'country' => 'RO',
                'language' => 'ro',
                'currency' => 'RON',
                'vat_id' => 'RO12345678'
            ],
            'products' => [
                ['name' => 'Tricou Nike', 'price' => 100.00, 'vat' => 19],
                ['name' => 'Pantaloni Adidas', 'price' => 200.00, 'vat' => 19]
            ],
            'customers' => [
                ['name' => 'Ion Popescu', 'vat_id' => 'RO87654321']
            ]
        ];
    }

    public static function seedGermany() { /* ... */ }
    public static function seedPoland() { /* ... */ }
}
```

**Всеки test run започва със seeded data за съответната държава.**



## КРАЙ НА TECH ЧАСТ 4 — КРАЙ НА BIBLE_v3_0_TECH.md

Общо редове в TECH: **~3200**

---

# 📖 РЕЗЮМЕ

**BIBLE v3.0 е обединена в 2 документа:**

### `BIBLE_v3_0_CORE.md` (~2500 реда)
- Закони (5)
- Концепция + позициониране + СУПТО
- Глобалност + i18n + EUR правила
- Планове FREE/START/PRO + Trial + Affiliate + Lock-in
- AI поведение (тон, формула, 15 тригера, Staff КПД)
- Onboarding (5 екрана с Kimi корекции, OCR голяма врата, escape hatch)
- WOW Tiers, Store Health, Lost Demand
- 857 AI теми (6 групи)
- Бъдещи модули (Shopify, маркетинг, счетоводство, HR)

### `BIBLE_v3_0_TECH.md` (~3200 реда, този файл)
- UI режими (simple / detailed)
- Wizard (4 стъпки vs 3 accordion — S75 final)
- Voice Input Layer (_bgPrice logic)
- Pills & Signals архитектура (3 слоя)
- Chat.php пълна спецификация
- Weather Integration
- Inventory v4 (Zone Walk, confidence model, 12 правила)
- 13 архитектурни компонента
- 10 заповеди + Recovery Mode + Trust Decay
- **AI Safety Architecture — 6 нива с Kimi корекции**
- DB Schema (всички таблици)
- Cron система
- Deploy процес
- Фази A-F (S72-S140+)
- 60+ обединени правила
- Operations (модели Claude, стопове, workflow)

---

*RunMyStore.ai — Пешо говори. AI прави.*
*Библия v3.0 TECH — 17.04.2026*
