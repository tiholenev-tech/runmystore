# 🎨 AI_STUDIO_LOGIC_DELTA.md — Промени към AI Studio логиката

**Версия:** 1.1 DELTA · **Дата:** 08.05.2026
**Базира се на:** `docs/AI_STUDIO_LOGIC.md` v1.0 FINAL (26.04.2026)
**Статус:** одобрен от Тихол в шеф-чат сесия (08.05.2026)

> Този документ описва **САМО какво се променя** спрямо AI_STUDIO_LOGIC.md v1.0.
> Всичко неспоменато тук остава както е във v1.0.

---

## 0. TL;DR (промените накратко)

1. **Нов flow** standalone → queue overlay → per-product modal (3 екрана вместо 2)
2. **Bulk магия** е изрично разрешена в queue overlay, но **САМО със safe automatic template** (без избор на стойка/поза/кадрировка)
3. **Разширен режим** (chips за поза/кадрировка/фон/voice) → **САМО per-product** (един по един), никога bulk
4. **Категория "Друго"** разширена с free-prompt textarea + voice + 3 примера + hint
5. **Visual:** всички emoji в UI заменени със SVG + текст (Bible §14 compliance)
6. **Queue overlay (нов екран)** — между standalone и per-product modal
7. **Подтипи** в Разширен режим минават от emoji+label към text-only chips (по-чисто)

---

## 1. ПРОМЕНИ В FLOW АРХИТЕКТУРАТА

### v1.0 (старо):
```
Lesny mode → ai-studio.php (P8 standalone)
                ↓ tap на категория
            [няма ясен flow какво се отваря]

Wizard products.php → step 5 → per-product modal (P8b)
```

### v1.1 (ново):
```
┌────────────────────────────────────────┐
│ Lesny mode                              │
│   ↓ tap "AI Studio · 385 чакат"        │
│ P8 ai-studio.php (standalone)          │
│   ├─ Bulk фон (deterministic)          │
│   ├─ Bulk описание (deterministic)     │
│   └─ tap на категория                   │
│        ↓                                │
│       P8c queue overlay  ← НОВ ЕКРАН    │
│         ├─ Bulk auto safe (вс. 8)      │
│         └─ tap на product row          │
│              ↓                          │
│             P8b per-product modal      │
│             (Лесен / Разширен)         │
└────────────────────────────────────────┘

Wizard "Добави артикул":
  Step 5 → P8b директно (без преминаване през P8/P8c)
```

**Документална връзка:**
- AI_STUDIO_LOGIC.md §7.417 само споменава "Tap на категория → отваря fullscreen overlay с queue list" без структурата.
- AI_STUDIO_LOGIC.md §17.847 testing checklist споменава "Tap на продукт в queue → отваря модала" — което потвърждава 3-екранния flow.
- Този документ финализира екрана като **P8c queue overlay** със следната задължителна структура (виж §3 по-долу).

---

## 2. BULK МАГИЯ — НОВО ПРАВИЛО

### v1.0 (стара логика):
- §7 "BULK vs QUEUE LOGIC" забранява напълно bulk магия:
  > **❌ НЕ bulk:** AI магия (try-on) · Probabilistic · 20-30% miss · PROPORTION LOCK изисква ръчно одобрение
- Категориите в standalone tap-ват към категория queue, но НЕ дава bulk магия възможност.

### v1.1 (нова логика):
**Bulk магия Е разрешена**, но със строги ограничения:

| Аспект | Правило |
|---|---|
| Promprompt | САМО `safe_automatic` template (auto-detect от `products.ai_subtype` + default настройки от `/settings/ai-defaults.php`) |
| Customization | НИЯКЪДЕ — Пешо НЕ настройва поза/кадрировка/фон/voice |
| Quality Guarantee | Прилага се per-product както при single (1 paid + 2 free retries + refund) |
| UI място | САМО в P8c queue overlay, секция "Bulk генерация" |
| Защита | Confirmation modal с total cost + продукти list преди старт |

### Защо bulk магия е разрешена:
- Конкурентен натиск — Пешо има 47 продукта, не е реалистично да направи 47 click-а един по един
- Quality Guarantee пази margin-а (refund при fail) — limited downside
- Safe template = тестван (89-90% success на bellето)
- Customization (chips) ОСТАВА само за per-product където риском е оправдан

### Икономика:
- Bulk 8 продукта × €0,30 = €2,40
- При 70% accept на първи опит, 25% на втори, 5% refund:
  - Cost: 8 × €0,075 base + retries
  - Revenue: 7,6 × €0,30 = €2,28
  - Net margin ~55-60% (същия като single)

---

## 3. P8c QUEUE OVERLAY — НОВ ЕКРАН (СПЕЦИФИКАЦИЯ)

### 3.1 Trigger
Tap на категория-row в P8 (`ai-studio.php`):
- Пример: "👙 Бельо · 8 чакат" (от AI магия секцията)
- Action: opens fullscreen overlay (slide-up animation, modal-like)

### 3.2 Структура (top to bottom)

**Header (sticky):**
- ✕ back button (затваря overlay)
- Кръгла category icon (с цветен hue per category — pink за бельо, indigo за дрехи и т.н.)
- Title: "Бельо" + subtitle "8 продукта чакат"
- Theme toggle button
- ✕ close button

**Compact strip (3 cells):**
- magic credits remaining (17/30)
- bulk total cost (€2,40 за 8 продукта)
- estimated time (~3 мин)

**Bulk генерация card (q-magic):**
- Header: иконка + "Bulk генерация" + sub "Безопасни тествани настройки"
- Info банер с ✓ checkmark: пояснение че са тествани настройки + указание че за индивидуални настройки tap на продукт долу
- Голям gradient бутон: "Генерирай всичките 8 · €2,40"

**Divider:** "ИЛИ"

**Индивидуални list card:**
- Header: "Индивидуални · избери продукт за настройка" + count badge (8)
- Скролируем списък продукти, всеки ред:
  - Кръгла thumb (с hue tag за категорията)
  - Име на продукта + код · цена
  - Status pill: "ЧАКА" (amber) или "✓ ГОТОВ" (green)
  - → arrow
- Tap на ред → отваря P8b модал за този продукт (с Лесен/Разширен toggle)

**Footer:** "← Назад към AI Studio"

### 3.3 DB query (PHP)
```php
$products = DB::run("
  SELECT id, name, code, retail_price, image_url,
         CASE WHEN ai_magic_image IS NOT NULL AND ai_magic_image != '' 
              THEN 'done' ELSE 'waiting' END AS magic_status
  FROM products
  WHERE tenant_id = ? AND ai_category = ? AND is_active = 1
  ORDER BY ai_magic_image IS NULL DESC, name ASC
", [$tid, $category])->fetchAll();

$total_cost = count(array_filter($products, fn($p) => $p['magic_status'] === 'waiting')) * 0.30;
```

### 3.4 Bulk action API
```
POST /ai-image-processor.php
Body:
  type=bulk_magic_safe
  category=lingerie
  product_ids=[12,15,18,21,22,25,30,33]
  template=safe_automatic   ← FIXED, не configurable

Response:
  job_id: "abc123"
  total_cost: 2.40
  eta_seconds: 180
  per_product_callback_url: "..."  ← всеки продукт минава Quality Guarantee
```

### 3.5 Per-product confirmation flow в bulk
- За всеки продукт от bulk-а:
  1. AI генерира с safe template
  2. Notification → Пешо отваря preview overlay (3 buttons: ✓ Запази / ↻ Retry / ✕ Refund)
  3. След action → авто-преход към следващ продукт
- Пешо може да напусне overlay-я и да се върне по-късно (jobs остават в queue)

---

## 4. ЛЕСЕН / РАЗШИРЕН РЕЖИМ (P8b) — РАЗШИРЕН СЪС ВСИЧКИ ВАРИАНТИ

### 4.1 v1.0 (старо)
§3 описваше:
- "A) Стандартно (default) — 1 бутон"
- "B) Настрой — Категория + Подтип + Поза + Кадрировка + Фон + Voice"
- "Категория Друго" — textarea + voice + 3 примера

Имплементация в `ai-studio-categories.html` беше непълна за бижута/аксесоари.

### 4.2 v1.1 (ново — пълна спецификация)

**Лесен режим (Лесен = Стандартно):**
- 1 голям бутон "Генерирай €0,30"
- Hint: "Auto-detect: [Бельо · Бикини]" с пълна purple pill
- Допълнителен hint: "Настройки от `settings/ai-defaults.php`"
- Auto-detect от:
  - `products.ai_category`
  - `products.ai_subtype`
  - tenant default pose/cropping/background

**Разширен режим (Разширен = Настрой):**

Структура зависи от селектираната категория:

| Категория | Подтип | Поза | Кадрировка | Фон | Повърхност | Изглед | Free-prompt | Voice |
|---|---|---|---|---|---|---|---|---|
| 👕 Дрехи (9) | ✅ | ✅ | ✅ | ✅ | — | — | — | ✅ |
| 👙 Бельо (6) | ✅ | ✅ | ✅ | ✅ | — | — | — | ✅ |
| 💎 Бижута (6) | ✅ | — | — | — | ✅ (8 пресета) | — | — | ✅ |
| 👜 Аксесоари (6) | ✅ | — | — | ✅ | — | ✅ (4 опции) | — | ✅ |
| 📦 Друго | — | — | — | — | — | — | ✅ | ✅ |

**Подтипи:**

*Дрехи (9):* Тениска · Рокля · Дънки · Сако · Риза · Пуловер · Шорти · Чорапи · Друго

*Бельо (6):* Бикини · Цял бански · Сутиен · Прашка · Корсет · Боди

*Бижута (6):* Пръстен · Гердан · Обеци · Часовник · Гривна · Брошка

*Аксесоари (6):* Обувки · Чанта · Шапка · Очила · Колан · Шалче

*Друго:* — (няма подтипи; вместо това textarea за свободно описание)

**Поза (clothes/lingerie, 4):**
Корпус 3/4 надясно · Корпус 3/4 наляво · Лице напред · Гръб

**Кадрировка (clothes/lingerie, 4):**
Само торс · Половин ръст · Цял ръст · Близък детайл

**Фон (clothes/lingerie/acc, 4):**
Бял студиен · Неутрален сив · Плажен (бански) · Lifestyle

**Повърхност (jewelry, 8):**
Върху ръка · Бял мрамор · Дърво · Кадифе · Цветя · Floating · Върху обувка · Върху чанта

**Изглед (acc, 4):**
Каталог · бял фон · На модел · Lifestyle · 3/4 ракурс

**Free-prompt (other only):**
- Textarea (auto-grow)
- Voice бутон (whisper Groq)
- 3 готови примера показани като hint:
  - "Бутилка вино върху бяла мраморна повърхност, естествена светлина"
  - "Кутия шоколадови бонбони отворена, с разпръснати бонбони наоколо"
  - "Играчка плюшено мече седнало на дървена пейка"
- Hint иконка + "Колкото по-конкретен опис, толкова по-добра снимка. AI запазва оригиналния продукт без деформация."

**Voice добавка row (clothes/lingerie/jewelry/acc — НЕ за other):**
"Кажи нещо специално (не задължително) — 'мек дневен светлик', 'профил отстрани'..."

### 4.3 Категория-специфични prompt templates
Според AI_STUDIO_LOGIC.md §8 — всяка категория има отделен template в `ai_prompt_templates` таблица:

```sql
-- INSERT default templates per category
-- jewelry — surface preset вместо pose/cropping
INSERT INTO ai_prompt_templates (category, template, success_rate) VALUES
  ('clothes', '...', NULL),
  ('lingerie', '...90%...', 90.00),  -- existing
  ('jewelry', '...', NULL),
  ('acc', '...', NULL),
  ('other', '...', NULL);  -- placeholder + free_prompt prepended
```

**TODO за Claude Code:** Създаване на templates за clothes/jewelry/acc/other базирани на принципите от lingerie template (CAPS LOCK, "DO NOT CHANGE", "PROPORTION LOCK") но адаптирани:
- jewelry: "MAINTAIN EXACT GEMSTONE COLORS AND CUTS. DO NOT modify metal type/finish."
- clothes: "PRESERVE FABRIC PATTERN, TEXTURE, AND COLOR EXACTLY."
- acc: "KEEP ORIGINAL SHAPE, MATERIALS, AND HARDWARE UNCHANGED."
- other: "{user_free_prompt} -- Object position must remain natural. Do not warp or alter the product."

---

## 5. VISUAL CHANGES — EMOJI → SVG

### v1.0 (старо)
Mockup `ai-studio-categories.html` използва emoji за всички chip-ове:
- Категории: 👕 👙 💎 👜 📦
- Подтипи: 33 различни emoji
- Поза/Кадрировка/Фон/Повърхност/Изглед: ~20 различни emoji

### v1.1 (ново)
Bible §14 правило (No emoji in UI — SVG only) се прилага навсякъде:

**Категории (5):** SVG + текст label
- Дрехи: tshirt SVG
- Бельо: bikini-band SVG
- Бижута: diamond SVG
- Аксесоари: bag SVG
- Друго: box SVG

**Подтипи (33):** **САМО ТЕКСТ** (без icon) — по-чисто, по-четивно
- Аргумент: 33 различни SVG биха претрупали UI-я. Текстът е достатъчен за идентификация.

**Поза (4):** rotate-cw / rotate-ccw / face-front / face-back SVG
**Кадрировка (4):** torso / half-body / full-body / zoom-in SVG
**Фон (4):** square / cloud / sun / home SVG
**Повърхност jewelry (8):** hand / marble / wood / velvet-ribbon / flower / floating-cloud / shoe / bag-small SVG
**Изглед acc (4):** catalog-box / on-model / sunrise-lifestyle / angle-triangle SVG

**P8c queue category icon:**
Кръгла category icon в header вече използва SVG (bikini-band за бельо) вместо 👙.

---

## 6. ШТО ОСТАВА БЕЗ ПРОМЯНА

| Sekcija | Status |
|---|---|
| §1 Базови цени и costs | ✅ Без промяна (€0,05 / €0,02 / €0,30) |
| §2 Петте категории + DB | ✅ Без промяна |
| §4 Quality Guarantee + retry | ✅ Без промяна |
| §5 Три типа кредити + планове | ✅ Без промяна (50/100/10 START, 300/500/30 PRO, 1000/1500/80 BIZ) |
| §6 Volume packs (€5-€100, 18 мес валидност) | ✅ Без промяна |
| §7 Bulk фон + описание deterministic | ✅ Без промяна |
| §8 Prompt templates (lingerie 90% success) | ✅ Без промяна |
| §11 API endpoints | ⚠ Добавяне на нов type=`bulk_magic_safe` (виж §3.4 тук) |
| §12 Error handling | ✅ Без промяна |
| §13 Risks + mitigations | ✅ Без промяна |
| §14 Critical rules | ✅ Без промяна |

---

## 7. DELIVERABLES (за Claude Code)

### 7.1 Нови файлове
```
NEW FILES:
  /var/www/runmystore/partials/ai-studio-queue-overlay.php  ← P8c queue overlay
  /var/www/runmystore/migrations/20260508_001_ai_studio_safe_template_seeds.sql

UPDATED FILES:
  /var/www/runmystore/ai-studio.php                          ← P8 standalone (само visual emoji→SVG)
  /var/www/runmystore/partials/ai-studio-modal.php           ← P8b (visual + structural)
  /var/www/runmystore/ai-image-processor.php                 ← добавя type=bulk_magic_safe
  /var/www/runmystore/settings/ai-defaults.php               ← нов файл за per-tenant defaults

NEW DOCS:
  AI_STUDIO_LOGIC_DELTA.md  ← този файл, за реfference
```

### 7.2 Mockup файлове (от шеф-чат)
```
mockups/
  P8_studio_main.html              ← AI Studio standalone
  P8b_studio_modal.html            ← per-product modal (lingerie default)
  P8b_advanced_clothes.html        ← Разширен режим · Дрехи активна
  P8b_advanced_lingerie.html       ← Разширен режим · Бельо активна
  P8b_advanced_jewelry.html        ← Разширен режим · Бижута активна
  P8b_advanced_acc.html            ← Разширен режим · Аксесоари активна
  P8b_advanced_other.html          ← Разширен режим · Друго активна (free-prompt)
  P8c_studio_queue.html            ← queue overlay (за бельо категория, 8 продукта)
```

### 7.3 Order на работа за Claude Code
1. Update `partials/ai-studio-modal.php` — visual + 5 категории structural
2. Create `partials/ai-studio-queue-overlay.php` — нов екран
3. Update `ai-studio.php` — само visual (emoji→SVG за категории)
4. Update `ai-image-processor.php` — добави `type=bulk_magic_safe`
5. Migration: SQL seeds за prompt templates на 5-те категории
6. Test на tenant_id=7

---

## 8. TESTING CHECKLIST (промени)

**Допълни към §17 в AI_STUDIO_LOGIC.md v1.0:**

### P8c queue overlay (нов)
- [ ] Tap на категория в P8 → отваря P8c overlay
- [ ] Bulk бутон показва правилния total cost
- [ ] Tap на bulk → confirm modal с list
- [ ] Bulk run прилага safe_automatic template (без customization)
- [ ] Tap на product row → отваря P8b модал
- [ ] След P8b save → връща в P8c с обновен status (waiting → done)
- [ ] Status pill: "ЧАКА" (amber) vs "✓ ГОТОВ" (green)
- [ ] Hue color на category icon съответства на категорията

### P8b разширен режим (5 категории)
- [ ] Дрехи селектирано → 9 подтипа + поза + кадрировка + фон
- [ ] Бельо селектирано → 6 подтипа + поза + кадрировка + фон
- [ ] Бижута селектирано → 6 подтипа + 8 повърхности (БЕЗ поза/кадрировка)
- [ ] Аксесоари селектирано → 6 подтипа + изглед + фон (БЕЗ поза)
- [ ] Друго селектирано → САМО textarea + voice + 3 примера (БЕЗ всичко друго)
- [ ] Voice row показва се за всичко освен other
- [ ] Sliding panels — само една активна visible

### Visual (emoji → SVG)
- [ ] 0 emoji в production HTML output на /ai-studio.php
- [ ] 0 emoji в /partials/ai-studio-modal.php output
- [ ] 0 emoji в /partials/ai-studio-queue-overlay.php output
- [ ] SVG icons имат `stroke: currentColor` за да следят color theme
- [ ] Light mode: SVG stroke = `var(--text)` или `var(--magic)`
- [ ] Dark mode: SVG stroke = white/light

---

## 9. OPEN QUESTIONS (за Тихол)

1. **Безопасен template per категория** — кой template точно е "safe"? lingerie има 90% success. За clothes/jewelry/acc/other още няма definite template. Дали bulk магия се пуска само за категории с ≥80% success, или fallback на generic template?

2. **Bulk job state** — ако Пешо затвори overlay-я по средата, jobs продължават ли да се изпълняват? Или паузират? Препоръка: продължават backend-side, Пешо вижда notification когато е готово.

3. **Queue overlay — "Генерирай всичките 8" преди да приключи** — какво ако се рестартира midway след product 4? Webhook-ове ще възобновяват или прескачат вече готовите.

4. **AI описание quick row в P8b** — €0,02 индивидуално. Нужно ли е да го заместим с bulk-style row "Описания за всичките 8"? Или остава single-product?

---

**КРАЙ НА DELTA ДОКУМЕНТА**

*Източник: шеф-чат session 08.05.2026 (P7 approved + P8/P8b/P8c mockups iterated)*
*Approval: Тихол · 08.05.2026*
