# 📦 HANDOFF — products.php Wizard P4 + P4b (mockups APPROVED)

**Дата:** 08.05.2026
**Статус:** P4_wizard_step1 + P4b_photo_states одобрени от Тихол
**Source of truth:** `DESIGN_SYSTEM.md` v4.1 BICHROMATIC (Bible)
**Reference на сегашния код:** `products.php` ред 11355-11625 (renderWizPhotoStep)
**Continuation на:** `HANDOFF_PRODUCTS_P2_P3.md` (P2 home + P3 list view)

---

## 0. APPROVED FILES (тази сесия)

| File | Lines | Purpose |
|---|---|---|
| `mockups/P4_wizard_step1.html` | 1957 | Визуален redesign на сегашния renderWizPhotoStep — 1:1 logic |
| `mockups/P4b_photo_states.html` | 1102 | 6 photo states (A-F) — empty / filled / multi / camera picker / camera loop |

И двата файла:
- Light = default · Dark = toggle (sun/moon)
- Mobile-first 375px (Z Flip6)
- Aurora bg + brand shimmer + anti-flicker init
- 0 emoji — само SVG icons
- 0 hardcoded HEX — само `var(--*)` tokens
- 0 hardcoded BG текст — само `{T_KEY}` placeholders

---

## 1. P4 — Wizard Step (consolidated photo+fields)

### 1.1 Какво е ЗАПАЗЕНО 1:1 от сегашния `renderWizPhotoStep()`

| Елемент | Сегашен ред | Запазено |
|---|---|---|
| Photo mode toggle | 11362-11367 | ✓ "Една снимка / Снимки на вариации" — само в variant |
| Multi-photo grid | 11371-11411 | ✓ per-color thumb + AI confidence + ★ ГЛАВНА badge |
| 4 photo tips | 11419 | ✓ Равна светла повърхност / Без други предмети / Добро осветление / Ясна, неразмазана |
| Type toggle MANDATORY | 11432-11445 | ✓ 2 големи `s95-type-btn` + warning hint когато не избран |
| Lock guard | 11454 | ✓ `opacity:0.42; pointer-events:none; filter:saturate(0.4)` |
| Per-field ↻ copy | 11460+ | ✓ на всяко поле |
| Per-field 🎤 mic | 11460+ | ✓ Web Speech (BG) или Whisper Groq |
| Inline + бутони | 11482-11511 | ✓ supplier/category/subcategory new |
| Subcategory disabled | 11501-11502 | ✓ ако `!category_id` |
| qty + min_qty steppers | 11540-11567 | ✓ -/+ numpad + auto formula `Math.round(qty/2.5)` |
| code + barcode collapsible | 11515-11532 | ✓ chevron card, scan бутони |
| Footer Single 2-row | line ~11580 | ✓ [ЗАПИШИ pulse + 🖨] / [Препоръчителни →] |
| Footer Variant 1-row | (variant flow) | ✓ [‹] [🖨] [Запази] [Напред ›] |

**Важно:** wizCollectData / wizSave / wizGo работят със същите DOM IDs (wName, wPrice, wSupDD, wCatDD, wSubcat, wSingleQty, wMinQty, wCode, wBarcode) — no backend изменения.

### 1.2 Визуален redesign (Bible §9.8 chat-input-bar pattern)

**Преди (стария wiz CSS):** 4 отделни бутона на ред — `[input 44px] [mic 44x44] [↻ 36x44] [+ 36x44]` = 116px buttons.

**Сега (Bible 9.8):** input + mic ВЪТРЕ в един pill, ↻ + + малки навън:
```
[ pill 52px → input + mic 38x38 ВЪТРЕ ] [↻ 32x52] [+ 32x52]
```
**= 70px действия (-40%)**, повече място за писане/диктуване.

| Елемент | Размер | Recipe |
|---|---|---|
| `.fc-pill` | 52px height, radius-pill 999px | Light: pressed neumorphic. Dark: linear-gradient + 1px solid `hsl(255 60% 28% / 0.45)` outline + inset glow |
| `.fc-pill:focus-within` (Dark) | — | accent border + 3px glow halo + 18px outer halo |
| `.fc-mic` | 38x38 кръгъл | magenta gradient (Bible 9.8 chat-mic-btn 1:1) |
| `.fc-act.cpy` | 32x52 | accent color, indigo glow в dark |
| `.fc-act.add` | 32x52 | accent text + indigo border glow (НЕ gradient — премахнат, твърде натрупващо) |
| `.fc-act.scan` | 32x52 | green glow |
| `.qty-stepper` | 52px height, radius-pill | същият Dark recipe + accent -/+ buttons |
| `.qty-stepper.warn` | — | амбер outline + амбер -/+ buttons (за min_qty) |
| `.fc-pill.is-select` | — | native select обвит в pill, chevron автоматично в right |

**Type buttons (`s95-type-btn`):**
- 84px height, 2 в ред
- Active: gradient + conic shimmer + box-shadow halo
- Inactive: surface neumorphic
- 0 emoji — SVG за иконите (rect single / 4-grid variant)

---

## 2. P4b — Photo States (6 variants)

| State | Описание |
|---|---|
| **A** | Single empty — placeholder card + 4 tips + Снимай/Галерия |
| **B** | Single + uploaded — preview 16:9 + замени/✕ overlay + AI разпозна pills (цвят 94% / категория 91% / марка 68% warn) + "Качена ✓" |
| **C** | Variant + multi empty — toggle "Снимки на вариации" active + big add cell ("Добави първа снимка · по 1 за всеки цвят") |
| **D** | Variant + multi с 3 photos — grid (★ ГЛАВНА Черно 94% / Червено 87% / Синьо AI...) + Add cell |
| **E** | **Camera picker drawer** — Tip + Снимай/Галерия + Откажи |
| **F** | **Camera loop counter** — gradient counter "[N] Снимай цвят N" + stage + back / shoot 72px orb / done |

### 2.1 BUG FIX в state E (camera picker tip)

**Сегашен products.php ред 8225 (BUG):**
> "Ако се отвори селфи камерата: излез, обърни я веднъж в нормалната Camera и **Самсунг** ще запомни задната завинаги."

`Самсунг` е **hardcoded brand name** (отрив от S82.COLOR.17 fix за Тихоловия Z Flip6). Production трябва да е generic — има 1000+ марки телефони.

**Нов текст (за подмяна на ред 8225):**
> "Ако се отвори селфи камерата: излез и я обърни на задна. Веднъж го направиш — телефонът помни."

i18n key: `T_TIP_CAMERA_FLIP` = горния текст.

### 2.2 Camera loop (state F)

`wizPhotoCameraLoop()` (ред 8263 в products.php) — counter "Снимай цвят N" остава 1:1, но визуалния redesign:
- Counter pill = magenta gradient + box-shadow halo
- Step badge (28x28 кръг) с number вътре
- 72px shoot orb (magenta + double-glow ring + 4px white border ring)
- Back / Done малки 44x44 neumorphic

Никаква промяна на JS — само CSS наследява от mockup-а.

---

## 3. NEW LOGIC: "🔍 Намери и копирай" (replaces "📋 Като предния")

### 3.1 Защо

Тихол: *"идеята е да търси, ако някой подобен артикул, по стар от предния, има подобен да го намери и копира"*

**Старо поведение:** "📋 Като предния" → tap → копира last `_rms_lastWizProductFields` от localStorage. **Само last item.** Няма търсачка.

**Ново поведение:** "🔍 Намери и копирай" → отваря **search drawer** със full history търсене. Включва last като top item, но с равноправен flow към ЛЮБ предишен артикул.

### 3.2 UI

**Trigger button** (replaces `wizCopyPrevProductFull` бутона):
- 60px tall pill (по-голям от старите 46px — за subtitle)
- **Title:** "Намери и копирай" (font 13px 800)
- **Subtitle:** "Копирай данни от друг артикул" (font 10px 600, 78% opacity)
- 38x38 SVG search icon в кръг (left)
- Chevron → (right)
- Magenta gradient + conic shimmer (винаги активен)

**Drawer (bottom sheet, 88vh):**
```
.cs-handle              (drag indicator)
.cs-head
  title: "Намери и копирай"
  sub:   "ПОДОБЕН АРТИКУЛ ОТ ИСТОРИЯТА"
  ✕ close
.cs-search              (fc-pill 52px + mic, voice search)
.cs-tabs                (5 filter chips h-scroll)
  [Последни] (active default) [Топ продавани] [Същ доставчик] [Същ артикул] [Всички]
.cs-list (scrollable)
  .cs-section-label "ПОСЛЕДНИ 10 ДОБАВЕНИ"
  10 × .cs-item        (равноправни, БЕЗ featured)
    [photo 44x44] [name + meta col] [price]
    meta: "code · supplier · преди X"
```

**Default view (без търсене):**
- Section "Последни 10 добавени"
- 10 items в DESC ред по `created_at`

**Tap на item:**
1. Copy полета (без image, barcode, code, qty) към `S.wizData`
2. Trigger autocomplete за supplier/category dropdowns (compat със `wSupDD`/`wCatDD` voice handler)
3. Close drawer
4. Haptic `[5,30,10]` (success pattern)
5. Re-render wizard form с попълнените полета

### 3.3 Backend AJAX endpoints (нови)

```php
// products.php?ajax=recent_products
{
  limit: 10,
  store_id: $sid
}
→ JSON [
  {
    id, name, code, supplier_id, supplier_name,
    category_id, retail_price, image_url,
    created_at, age_label  // "преди 12 мин" / "вчера" / etc.
  },
  ...
]

// products.php?ajax=search_products
{
  q: string,
  filter: 'recent'|'top'|'same_supplier'|'same_category'|'all',
  context_supplier_id?: int,   // за filter='same_supplier'
  context_category_id?: int,   // за filter='same_category'
  limit: 30
}
→ JSON [...]

// products.php?ajax=copy_fields_from_product
{
  source_product_id: int
}
→ JSON {
  name: string,
  retail_price: decimal,
  cost_price: decimal,         // ако can_see_cost
  wholesale_price: decimal,
  supplier_id: int,
  supplier_name: string,
  category_id: int,
  category_name: string,
  subcategory_id: int,
  composition: string,
  origin_country: string,
  unit: string,
  // НЕ копира: image_url, barcode, code, quantity, min_quantity
}
```

**Filter SQL примери:**
```sql
-- recent (default)
SELECT * FROM products WHERE tenant_id=? AND parent_id IS NULL AND is_active=1
ORDER BY created_at DESC LIMIT 10

-- top (продажби 30д)
SELECT p.* FROM products p
JOIN sale_items si ON si.product_id = p.id
JOIN sales s ON s.id = si.sale_id
WHERE p.tenant_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  AND s.status != 'canceled' AND p.parent_id IS NULL
GROUP BY p.id ORDER BY SUM(si.quantity) DESC LIMIT 10

-- same_supplier
SELECT * FROM products WHERE tenant_id=? AND supplier_id=?
  AND parent_id IS NULL AND is_active=1
ORDER BY created_at DESC LIMIT 10

-- same_category
SELECT * FROM products WHERE tenant_id=? AND category_id=?
  AND parent_id IS NULL AND is_active=1
ORDER BY created_at DESC LIMIT 10

-- search (q-driven)
SELECT * FROM products WHERE tenant_id=?
  AND (name LIKE ? OR code LIKE ? OR barcode LIKE ?)
  AND parent_id IS NULL AND is_active=1
ORDER BY name LIMIT 30
```

### 3.4 Privacy / Какво НЕ се копира

- `image_url` — ново снимане, не reuse
- `barcode` — уникален per product
- `code` (SKU) — уникален per product
- `quantity` — нов брой, винаги 0 default
- `min_quantity` — auto-derived от qty (formula)

### 3.5 i18n keys (нови)

```
T_FIND_AND_COPY_TITLE       → "Намери и копирай"
T_FIND_AND_COPY_SUB         → "Копирай данни от друг артикул"
T_LAST_10_ADDED             → "Последни 10 добавени"
T_RECENT                    → "Последни"
T_TOP_SELLERS               → "Топ продавани"
T_SAME_SUPPLIER             → "Същ доставчик"
T_SAME_CATEGORY             → "Същ артикул"
T_ALL                       → "Всички"
T_AGE_MINUTES_AGO           → "преди {n} мин"
T_AGE_HOURS_AGO             → "преди {n} ч"
T_AGE_YESTERDAY             → "вчера"
T_AGE_DAYS_AGO              → "преди {n} дни"
T_AGE_WEEK_AGO              → "преди седмица"
T_AGE_WEEKS_AGO             → "преди {n} седмици"
T_AGE_MONTH_AGO             → "преди месец"
T_AGE_MONTHS_AGO            → "преди {n} месеца"

T_TIP_CAMERA_FLIP           → "Ако се отвори селфи камерата: излез и я обърни на задна. Веднъж го направиш — телефонът помни."
```

---

## 4. Emoji → SVG migration (приложено в P4 + P4b)

**Правило (userPrefs):** *"Никога не слагай emoji в UI — само SVG icons."*

| Bъв wizard | Beше | Сега |
|---|---|---|
| Type toggle | 📦 Единичен / 📊 С Вариации | rect SVG / 4-grid SVG (текст без emoji) |
| Qty label | 📦 Брой | box SVG (12px, accent) |
| Min qty label | 📦 Минимално кол. | box SVG (12px, accent) |
| Camera picker tip | 💡 | info-circle SVG (gradient bg) |
| Camera loop shoot | (gradient circle) | shoot ring SVG ring + 72px orb |

**Запазено:** SVG за всички иконки (search lupa, polyline check, plus minus, microphone outline, barcode rect-grid, etc.).

**ВАЖНО:** Сегашният products.php има emoji-та в HTML — при rewrite **всеки emoji трябва да се заменя със SVG** (Bible §14 compliance check).

---

## 5. Open questions (унаследени от P2/P3 + нови)

### Унаследени (НЕ отговорени още):
1. ~~Wizard structure~~ — РЕШЕНО: 1:1 със сегашната consolidated step (P4)
2. **AI Studio entry:** в wizard step 5 (AI_STUDIO_LOGIC §10) ИЛИ post-creation от life-board nudge?
3. **Color → hex map:** има ли спецификация (`partials/color-map.php`)? Ако не — трябва.
4. **Експорт format (P3 drawer):** CSV само (default) или CSV + XLSX?
5. **"Печатай всички" ред:** по код / цвят-размер / DB?
6. **Health bar threshold:** 80/50 ли остава?

### Нови (от тази сесия):
7. **"Намери и копирай" tap behavior:** мигом copy + toast | preview confirm | undo flash?
   *Препоръка:* мигом copy + Toast "Копирано · отмени?" с 5s undo timeout.
8. **Фильтър "Същ доставчик/категория":** context-aware (от current `S.wizData`) или global filter?
   *Препоръка:* context-aware — ако вече има избран supplier в wizard form, drawer филтрира same. Ако не — disabled tab.
9. **Поне 1 запазен product** ли е prerequisite за "Намери и копирай"? Ако не — drawer показва empty state със обяснителен текст.
10. **Сравнителен `barcode` vs `code` (SKU):** има ли практически смисъл и двете? Тихол ще конвертира?

---

## 6. NEXT mockups roadmap

Пред теб (по реда):

| Mockup | Source | Lines (ест.) |
|---|---|---|
| **P5** | Step 4 — Variations axes (вариации) — products.php ред ~7185+ | ~2200 |
| **P6** | Step 5 — Бизнес/AI prompt (cost / wholesale / margin / композиция / произход) | ~1800 |
| **P7** | Step 6 — Print preview + final save | ~1500 |
| **P8** | Step 8 — "Допълнителни данни (препоръчително)" — single optional details | ~1600 |

Препоръчителен ред: P5 → P6 → P8 → P7. P7 (печат) е независим от P8 (по желание).

---

## 7. Backend implementation notes (за Claude Code session)

**Преди rewrite на products.php:**
1. Backup задължителен: `cp products.php products.php.bak.S<XX>_<TIMESTAMP>`
2. **НЕ ПИПАЙ:** `wizGo` / `wizSave` / `wizCollectData` / `wizPhotoCameraLoop` / Bluetooth `printLabel` / sale.php integration
3. **ЗАМЕНИ:**
   - `renderWizPhotoStep()` (11355-11625) → нов markup със Bible §9.8 fc-pill pattern
   - `wizCopyPrevProductFull()` (11195) → `openCopySimilarSheet()` + AJAX call
   - Photo picker drawer (ред 8220-8235) → нов markup със generic tip
   - **Премахни** `cam-drawer-tip-app` (📷 Camera span) — premахнат от UX, generic tip без app мention
4. **Добавeно:**
   - 3 нови AJAX endpoints (recent / search / copy_fields)
   - JS: `openCopySimilar()` / `csPick()` / `csSearch()` / `csTabSwitch()`
   - localStorage `_rms_lastWizProductFields` остава като fallback ако backend down (offline mode)
5. **Compliance check:** `bash /var/www/runmystore/design-kit/check-compliance.sh products.php` — exit 0 mandatory
6. **Test:** Z Flip6 viewport (~373px) light + dark + Capacitor APK

---

## 8. Reference

| Source | Path | Why |
|---|---|---|
| Bible §9.8 | `DESIGN_SYSTEM.md` 1583-1643 | chat-input-bar / fc-pill 1:1 pattern |
| Camera tip bug | `products.php` 8225 | hardcoded "Самсунг" — заменя се с generic |
| Wizard photo step | `products.php` 11355-11625 | source за visual redesign (logic preserve) |
| Existing copy logic | `products.php` 11195-11220 | wizCopyPrevProductFull → replace с drawer flow |
| Camera loop | `products.php` 8263-8295 | wizPhotoCameraLoop → запазен 1:1 |
| Previous handoff | `HANDOFF_PRODUCTS_P2_P3.md` | P2 home + P3 list view design |

---

**КРАЙ. Готов за P5 (Variations axes / Step 4) когато избере Тихол да продължи.**
