# WIZARD FIELDS AUDIT — products.php (S88B-1)

**Дата:** 2026-04-28
**Цел:** одит на текущия wizard преди build на S88B-1 (preorder + 4 нови полета + ↻ "като предния" бутони).
**Source of truth:** live код в `/var/www/runmystore/products.php` + `/var/www/runmystore/product-save.php` + DB schema (`products`, `categories`).

---

## 1. Текущ wizard — структура

| Item | Стойност |
|---|---|
| Файл | `products.php` |
| Общ брой реда | 11393 |
| Range на wizard JS | ~5513 → ~10980 (label/index const + всички `wiz*` функции) |
| Wizard модал HTML | `#wizModal` — CSS на 1615-3400, init в `openManualWizard` (~9568) |

**Step labels (line 5513):**
```js
const WIZ_LABELS = ['Вид','Основни','Варианти','Бизнес','AI Studio'];
const WIZ_UI_INDEX = [null, null, 3, 0, 1, 2, 4]; // step0/1 → redirect
```

**Step → render mapping (`renderWizPage` line 5929 + `renderWizPagePart2` line 6076):**

| step | UI label | Действие |
|---|---|---|
| 0 | (пропуска се) | line 5933: `setTimeout(()=>wizGo(3),0)` — auto-redirect към 3 |
| 1 | (пропуска се) | line 5939: auto-redirect към 3 |
| 2 | AI Image Studio | renderStudioStep() (отделен helper, line 6624) |
| **3** | **Основни (Вид)** | line 5950 — главната форма |
| 4 | Варианти | line 6080 — single = stub, variant = matrix UI |
| 5 | Бизнес (Бройки + AI описание + Final prompt) | line 6405 |
| 6 | Печат | line 6557 |

**Главен navigator:** `wizGo(step)` line 5746. Викa `wizCollectData()`, после `renderWizard()`.

**Тип-избор (single/variant):** `S.wizType` се set-ва от `wizSwitchType('single'|'variant')` (line 9349). UI toggle е в Step 3 inline (`v4-type-toggle`, line 6035 — НЕ е отделен Step 0). Auto-detect от voice line 5693-5694.

**Опционален "voice mode":** `S.wizVoiceMode` (line 5638/5663). Когато е true → `voiceForStep` се вика на всеки `wizGo`.

---

## 2. Полета per стъпка (ТЕКУЩИ)

### Step 3 — Основни (line 5949–6072)

| # | Поле | DOM id | Required | Type | Voice mic | Notes |
|---|---|---|---|---|---|---|
| 1 | Снимка (camera/gallery) | `photoInput` / `filePickerInput` | optional | file | — | line 6022-6028 — `_photoMode` toggle (single/multi за варианти, line 5977) |
| 2 | Тип (Единичен/С варианти) | `.v4-type-toggle` | **YES** | toggle | — | line 6035 — без избор `wizTypeGuard` блокира всяко поле (line 9340) |
| 3 | "Като предния" copy-prev card | inline `<div onclick="showToast('Както предния — S74')">` | — | stub | — | line 6031 — **STUB**: показва се само ако `localStorage._rms_lastWizProducts` съществува, но onclick e toast placeholder. Реалната `wizCopyPrevProduct` (line 8241) копира САМО axes от `_rms_lastWizAxes`. |
| 4 | Име * | `wName` | **YES** | text | ✅ `mic('name')` | line 6053 |
| 5 | Цена дребно * | `wPrice` | **YES** | decimal | ✅ | line 6055 |
| 6 | Цена едро | `wWprice` | optional | decimal | ✅ | line 6056 — скрита ако `CFG.skipWholesale` |
| 7 | Брой (single only) | `wSingleQty` | optional | int +/− | — | line 6040 — само за `isSingle` |
| 8 | Мин. количество (single only) | `wMinQty` | optional | int +/− | — | line 6040 — само за `isSingle`, default 1 |
| 9 | --- разделител "Пожелателно" --- | — | — | — | — | line 6059 |
| 10 | Доставна цена | `wCostPrice` | optional | decimal | ✅ | line 6060 |
| 11 | Баркод | `wBarcode` | optional (auto-fill) | text | ✅ + scan | line 6061 — `wizScanBarcode()` бутон |
| 12 | Състав / Материя | `wComposition` | optional | text | ✅ | line 6062 |
| 13 | Мерна единица | `wUnit` (select) | optional, default `бр` | dropdown + inline add | — | line 6063 |

### Step 4 — Варианти (line 6079+)

| Сценарий | Какво се рендира |
|---|---|
| `S.wizType==='single'` | Decorative card "Единичен артикул" + back/save/next бутони (line 6081 — stub) |
| `S.wizType==='variant'` | Tabs за axes (Размер, Цвят, custom) + chip picker + матрица CTA + AI prompt card |

**Axes:** `S.wizData.axes = [{name, values}, ...]`. Default: `'Вариация 1'` + `'Вариация 2'` (line 6089). AI auto-rename "Вариация N" → "Цвят" ако се детектират цветове (line 6114).

**Матрица:** size × color → `S.wizData._matrix[mx_<si>_<ci>] = {qty, min}`. Auto-min формула: `Math.round(qty/2.5)` (line 5756).

### Step 5 — Бизнес (Бройки + AI описание + Final prompt) — line 6405

| Поле | DOM id | Notes |
|---|---|---|
| Бройка single | `wSingleQty` | ако няма combos |
| Мин (single) | `wSingleMin` | ако няма combos |
| Матрица size×color | `mx_<si>_<ci>` | inline при variant |
| Бройка per combo | `data-combo` inputs | при custom axes |
| Мин. количество (глобално) | `wMinQty` | line 6517 — повтаря се! (вече има в Step 3 за single) |
| AI описание (textarea) | `wDesc` | line 6521 — readonly до AI генерация |
| Final AI prompt buttons | "Да отвори AI Studio" / "Не запази" | line 6543-6544 |

### Step 6 — Печат (line 6557)
Не е "поле" — output overlay със dual/eur/no-price tabs + per-vararion qty + print buttons.

---

## 3. Какво ЛИПСВА — потвърдено

### 3.1 Полета които Тихол изисква в S88B-1, но НЯМА в UI

| Поле | Колона в DB? | DOM id очакван от code | Реално рендирано? |
|---|---|---|---|
| **Доставчик** | `products.supplier_id` ✅ | `wSupDD` (collect line 9061), `wSup` (renderWizard line 5887) | ❌ **НЕ** — никъде в render-а няма `id="wSupDD"` или `id="wSup"`. Има `inlSup` collapsible add (line 9711) и voice handler (line 10433), но базовия dropdown не се рендира. |
| **Категория** | `products.category_id` ✅ | `wCatDD` / `wCat` | ❌ **НЕ** — същото. Code очаква елементи които не съществуват в DOM. |
| **Подкатегория** | категории с `parent_id` (поле `categories.parent_id` ✅) — НЕ отделна колона `subcategory_id` | `wSubcat` (collect line 9063, render-call line 5915, ajax loader line 9043) | ❌ **НЕ** — никога не се вмъква в HTML. Voice/inline handlers очакват го (line 10435), но е "dead UI". |
| **Markup %** | ❌ **НЯМА `markup_pct` колона** в `products`. Проверено: `SHOW COLUMNS FROM products LIKE 'markup%'` → 0 редове. | — | ❌ Не съществува нито в UI, нито в DB. |
| **Произход** | `products.origin_country` ✅ | `wOrigin` (collect line 9067, voice line 10433) | ❌ **НЕ** — няма render. Само voice/onboarding hint. |

### 3.2 Други observations

- `S.wizData.subcategory_id` се пише в payload (line 9063), но **НЕ** се изпраща към `product-save.php` — `payload` обектът на line 9208-9221 не съдържа `subcategory_id`. Така че дори voice да попълни subcategory, не се записва.
- `product-save.php` НЕ приема `subcategory_id` ключ (виж line 113-141).
- `cost_price` в payload е reads `S.wizData.cost_price` (line 9211) — има поле, OK.

---

## 4. Текущ "Като предния" бутон — анализ

### 4.1 Visible UI бутон (Step 3 top)
- **Локация:** `products.php` line **6031-6033**
- **Render условие:** само ако `localStorage._rms_lastWizProducts` съществува (line 5959)
- **OnClick:** `showToast('Както предния — S74')` — **STUB!** Няма реална логика.
- **Визуал:** glassmorphic card "Както предния артикул · Копирай данни"
- **Какво трябва да копира (per Тихол спец, бъдещо):** ВСИЧКИ полета ОСВЕН Име, Снимка, Баркод, Артикулен номер, (Размер/Цвят за variant)
- **Какво копира сега:** нищо. Toast notification.

### 4.2 Реална функция `wizCopyPrevProduct()` (line 8241-8252)
- Чете `localStorage._rms_lastWizAxes` (НЕ `_rms_lastWizProducts`)
- Копира **САМО `S.wizData.axes`** (т.е. вариациите — Размер/Цвят values)
- НЕ копира: supplier_id, category_id, retail_price, cost_price, etc.
- **Грешно име:** функцията се казва "wizCopyPrevProduct" но реално е "wizCopyPrevVariations"
- **НЕ е свързана** със Step-3 visual бутона.

### 4.3 LocalStorage keys — съществуващи
- `_rms_lastWizAxes` — пише се от `_wizSaveAxesToLocal()` (line 8255) при successful save
- `_rms_lastWizProducts` — **търси се** като индикатор за `hasLast` в Step 3 (line 5959) и в `wizAddInline` (line ~9701) но **НИКЪДЕ не се пише** в текущия код. Mъртъв key.

**Вердикт:** "Като предния" е напълно неимплементиран на полево ниво. Само axes копиране работи (и не е достъпно от UI бутон в Step 3).

---

## 5. Auto-fill за Баркод и Артикулен номер

### 5.1 Колони
- **Баркод колона:** `products.barcode` (varchar(100))
- **Артикулен номер колона:** `products.code` (varchar(100)) — Тихол го нарича "артикулен номер", в код = `code`

### 5.2 Текуща auto-generation логика (`product-save.php`)

**Code auto-gen (line 219-227):**
```php
if (!$code) {
    $words = preg_split('/\s+/', $name);
    $code = '';
    foreach ($words as $w) { $code .= mb_strtoupper(mb_substr($w, 0, 2)); }
    $code = substr($code, 0, 6) . '-' . rand(10,99);
    $exists = DB::run("SELECT id FROM products WHERE tenant_id=? AND code=?", [$tenant_id, $code])->fetch();
    if ($exists) $code .= rand(1,9);
}
```
→ Първите 2 букви от всяка дума, max 6 символа, dash, 2 random цифри. Пример: `ДЪMUSI-42` за "Дънки Mustang син".

**Barcode auto-gen (line 229-233):**
```php
$needBarcode = (!$barcode && $product_type === 'simple' && empty($sizes) && empty($colors) && !$has_variants && empty($variants_json) && empty($variants_raw));
if ($needBarcode) {
    $barcode = generateEAN13($tenant_id);
}
```
→ EAN-13 generated с `generateEAN13` (line 491-500). Format: 3-digit tenant prefix + 9 random + 1 checksum digit. Валиден EAN-13.

**За variant продукти:** parent няма barcode (NULL), всяка вариация получава separate `generateEAN13()` (line 362, 402, 439).

### 5.3 Препоръка
Format-ите са OK за Пешо. Препоръка:
- **Не променяй** EAN-13 алгоритъма (валидиран от съществуващи продукти).
- За UI: показвай `(авто)` placeholder в полето; ако Пешо го попълни ръчно — backend го уважава; ако празно — auto.
- Текущият Step 3 баркод поле вече казва `(авто ако празно)` (line 6061) ✅.

---

## 6. Markup % — DB колона

```sql
SHOW COLUMNS FROM products LIKE 'markup%';
-- 0 rows
```

**❌ НЕ СЪЩЕСТВУВА.** Изисква `ALTER TABLE products ADD markup_pct DECIMAL(5,2) NULL;`

**STOP — чакам Тихол approve за ALTER преди build.**

Алтернативи без ALTER:
- **Computed-only (без store):** UI показва markup = `((retail_price - cost_price) / cost_price) * 100` без да се пази. ✅ Препоръчвам това като MVP — markup е derivable, не нужно DB колона.
- **С DB колона:** ако Тихол иска markup да е editable независимо от cost_price (напр. "Поставям 60% markup" → cost_price се изчислява назад) → нужен ALTER.

---

## 7. Снимки — текуща логика

### 7.1 Главна снимка
- **Колона:** `products.image_url` (varchar(1000))
- **Upload endpoint:** `products.php?ajax=upload_image` (вика се от wizSave line 9244-9246)
- **DataURL flow:** `S.wizData._photoDataUrl` (data: URL) → POST към endpoint → файл в `/uploads/products/{tenant_id}/{product_id}_<timestamp>.jpg` → URL се записва в `products.image_url`.

### 7.2 Снимки на варианти (S88.BUG#1 — ВЕЧЕ имплементирано)
- **Mode toggle:** `_photoMode` = `'single' | 'multi'` (line 5970-5982). Multi е достъпен само при `S.wizType === 'variant'`.
- **Storage:** `S.wizData._photos = [{dataUrl, ai_color, ai_hex, ai_confidence}, ...]`
- **AI color detect:** `wizPhotoDetectColors()` (line 7041) — праща снимките към endpoint, връща предложен цвят + hex + confidence
- **Save flow:** `wizSave` line 9248-9262: за всеки `_photos[i]`, намира всички variant_ids със същия `ai_color`, и POST-ва snimkata към всеки variant child (`product_id = cid`).
- **Auto-create color axis:** ако `_photos[].ai_color` се детектира, се добавя в първата generic "Вариация N" axis (renamed на "Цвят") (line 6105-6125).

### 7.3 "Направи главна" функционалност
- **❌ НЕ СЪЩЕСТВУВА.** Кодът автоматично assign-ва snimka per variant_id чрез matching `ai_color`. Няма UI бутон "избери коя да е главна".
- За единична снимка → автоматично е `image_url` на parent.
- За multi → всеки вариант си има отделна снимка; parent (group-level) има НЯКОЯ от тях (зависи от order на upload, не explicit selection).

**За Тихол спец (Step 2 Вариант B2):** "Направи главна" бутон трябва да се добави като нов UI element + flag в `_photos[i].is_main`, после wizSave да изпрати `image_url` на parent от main-marked photo.

---

## 8. Какво има, но е счупено / непълно

| Item | Описание | Locator |
|---|---|---|
| `wSupDD` / `wCatDD` / `wSubcat` / `wOrigin` UI | Code очаква dropdowns със тези IDs (collect, voice, render-helpers); реално НИКЪДЕ не се рендират в HTML | grep `id="wSup` etc. → 0 hits |
| "Като предния" Step-3 button | Visible но onClick = toast stub | line 6032 |
| `wizCopyPrevProduct()` функция | Копира само axes, не полета. Не е свързана с visible button. | line 8241 |
| `_rms_lastWizProducts` localStorage | Чете се (line 5959, 9701) но никога не се пише | grep |
| `S.wizData.subcategory_id` | Collect-ва се (line 9063) но НЕ се праща в payload към product-save.php | line 9208-9221 |
| `wMinQty` дублиран | Има го и в Step 3 (само single) и в Step 5 (глобален) — confusing UX | lines 6040, 6517 |
| `S.wizData.origin_country` | Collect/voice има, render няма | line 9067 |
| Step 0/1 редирект | Прехвърля към Step 3 безусловно — ефективно няма Step 0 UI (per Тихол spec трябва да има!) | lines 5933, 5939 |
| `Печат` бутон в Step 3 sticky footer | onClick = `showToast('Печат — S73.B.5')` — stub | line 6043 |

---

## 9. Step 0 cleanup (Bug #3) — анализ

Тихол казва: "Step 0 на текущия wizard има 3 бутона: ✏️ молив, 🎤 микрофон, 📋 като предния."

**Реалност:** Step 0 е redirect към Step 3 (line 5933). Тези 3 бутона **не са** в Step 0 — те трябва да са в Step 3 top бара. Текущо състояние на Step 3 entry area (line 6035 → line 6045 return):

1. **typeToggle** (`.v4-type-toggle`) — Single/Variant избор бутони ✅
2. **copyPrev** glass card — "Както предния артикул" (само ако `hasLast`) — STUB onclick
3. (никъде в Step 3 НЯМА ✏️ молив или 🎤 микрофон бутон над Type toggle)

**Hypothesis:** ✏️ молив и 🎤 микрофон бутоните, които Тихол вижда, са **глобални FAB-ове** или част от quickActions pill bar, НЕ от wizard Step. Трябва да открием exact element. Не е намерен в `products.php` grep — възможно да са в:
- `partials/header.php`
- `aibrain-modals.js` (DISJOINT LOCK)
- `chat.php`

**Препоръка:** преди да "махнем" тези бутони — да idenftifицираме къде живеят. Ако са в DISJOINT LOCK файлове, не можем да ги пипнем директно.

---

## 10. Препоръка за placement (S88B-1 build)

### 10.1 Step 0 (нов)
Тихол иска ЯВЕН Step 0 с 2 бутона "Единичен" / "С вариации". Текущо `wizGo(0)` redirect-ва към 3.
- **Премахни** redirect-ите на line 5933 и 5939.
- **Имплементирай** Step 0 рендер: 2 големи glass cards + global "📋 Копирай от последния" под тях.
- При tap → `wizSwitchType('single'|'variant')` → `wizGo(2)` (снимка) или `wizGo(3)` (основни).
- Removed buttons (✏️/🎤): preserve само "📋 Копирай от последния" видим.

### 10.2 Step 2 (снимка като отделен step)
- Single → 1 slot + "Може да пропуснеш"
- Variant + B1 → 1 slot
- Variant + B2 → multi grid (вече има — `_photoMode='multi'`) + **нов бутон "Направи главна"** на всяка снимка → `_photos[i].is_main = true`

### 10.3 Step 3 — preorder per Тихол спец

**Single mode layout:**
1. Име (има)
2. Цена retail (има)
3. Доставна цена + Markup % (conditional, side-by-side) — *Markup computed-only ако без ALTER*
4. Минимално количество (има, но премести)
5. Доставчик ❗ NEW dropdown
6. Категория ❗ NEW dropdown
7. Подкатегория ❗ NEW dropdown (parent_id-filtered)
8. Цвят (има като single field в schema, добави chip selector)
9. Размер (има като single field)
10. Материя/Състав (има като `wComposition`)
11. Произход ❗ NEW dropdown
12. Collapsible: Баркод + Артикулен номер (auto-fill)

**Variant mode layout:** като single но **без** Цвят/Размер на този step (преместват се в Step 4 axes).

### 10.4 ↻ "Като предния" бутони

Нов малък ↻ icon бутон до всяко поле от списъка (Цена retail, Доставна, Markup, Мин кол-во, Доставчик, Категория, Подкатегория, Цвят, Размер, Материя, Произход).

OnClick: чете `localStorage._rms_lastWizProductFields` (NEW key) → попълва САМО това поле.

При successful save → `_wizSaveLastFields()` пише snapshot на тези полета (НЕ Име/Снимка/Баркод/Код).

### 10.5 "📋 Копирай от последния" глобалния бутон
- Премести от "..." menu в Step 0 (visible)
- Копира всичко от `_rms_lastWizProductFields` ОСВЕН Име, Снимка, Баркод, Код, (Размер/Цвят за variant)
- Reuse-ва ↻ logic, но bulk

### 10.6 DB miграция (изисква Тихол approve)

```sql
-- ОПЦИОНАЛНО — Markup като persisted колона:
ALTER TABLE products ADD markup_pct DECIMAL(5,2) NULL AFTER cost_price;

-- ЗАДЪЛЖИТЕЛНО за subcategory:
-- (НЕ НУЖНО — subcategories вече съществуват в `categories` с parent_id)
-- product-save.php трябва само да приема subcategory_id и да го SAVE-ва в category_id
-- (т.к. subcategory IS a category — leaf level)
-- ИЛИ нова колона `products.subcategory_id` ако искаме да пазим parent_category отделно.
-- → препоръчвам втория вариант за чистота:
ALTER TABLE products ADD subcategory_id INT UNSIGNED NULL AFTER category_id,
                     ADD CONSTRAINT fk_products_subcategory FOREIGN KEY (subcategory_id) REFERENCES categories(id);
```

**STOP — чакам Тихол approve преди да изпълня някоя от тези migrations.**

---

## 11. Ред на промени (build sequence) — препоръка

1. **Backend:** добави `subcategory_id` + `markup_pct` в `product-save.php` payload handling (без DB ALTER, тестово).
2. **DB ALTER:** Тихол approve → execute migrations.
3. **UI Step 0:** махни redirect, добави нов choice screen.
4. **UI Step 3:** добави липсващите 4 dropdowns (sup/cat/subcat/origin) + Markup conditional.
5. **↻ Buttons:** нов helper `wizCopyFieldFromPrev(field)` + visible ↻ icons на 11-те полета.
6. **localStorage save:** в `wizSave` success → write `_rms_lastWizProductFields` snapshot.
7. **Step 0 cleanup:** ако ✏️/🎤 бутоните се намерят в products.php — remove. Ако са в DISJOINT файлове — flag за Тихол.
8. **Step 2 Variant B2 "Направи главна":** add UI flag + parent image_url override.

---

## 12. Open questions за Тихол (преди build)

1. **Markup persisted vs computed?** Препоръчвам computed-only (без ALTER). OK?
2. **Subcategory отделна колона?** `products.subcategory_id` или продължаваме да използваме `category_id` за leaf?
3. **Цвят/Размер като single field в Step 3 Single mode** — schema има `products.color` / `products.size`. Използваме тях, или въвеждаме axes даже при single? (Текущо: само variant ползва axes.)
4. **"Копирай от последния" excludes:** потвърди списъка — Име, Снимка, Баркод, Артикулен номер. (Размер/Цвят за variant — clarify дали важи и за single?)
5. **Step 0 Voice/Edit бутоните** — ако не са в `products.php` (а в DISJOINT файл), да оставим ли хирургична намеса или skip?
6. **`bg.json` i18n файл:** Тихол прескочи го като required reading, защото **ЛИПСВА** в repo. Текущите wizard strings са hardcoded на български. Build на S88B-1 ще добави още hardcoded strings — създаваме ли `lang/bg.json` сега или отлагаме i18n за по-късна сесия?

---

**Край на audit.**
