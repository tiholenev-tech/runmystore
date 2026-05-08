# PRODUCTS_BULK_ENTRY_LOGIC.md

**Modul:** `products.php` → "Добави артикул"
**Replaces:** wizard P4-P9 (legacy)
**Canonical visual:** `mockups/P13_bulk_entry.html`
**Matrix overlay:** `mockups/P12_matrix.html`
**Author:** Шеф-чат session 08.05.2026
**Audience:** Claude Code

---

## 0. Закони (read first, never forget)

1. **P13 mockup е canonical.** Всеки CSS class, всеки SVG icon, всеки spacing, всеки animation, всеки color → 1:1 в production. Ако trябва да се отстъпиш — STOP и питай Тихол.
2. **0 emoji в UI** (Bible §14). SVG only.
3. **Никога "Gemini" в UI** — винаги "AI".
4. **PHP смята, AI вокализира** — confidence, margin, SKU summary, totals в PHP.
5. **Митко не вижда `confidence_score` число** (Hidden Inventory §5). Само консекуенциите (AI questions, ranges).
6. **Pesho не пише** (ЗАКОН №1) — voice бутон на всяко поле.
7. **Sacred Neon Glass** в dark mode — 4 spans + oklch + plus-lighter (`acc-section::before` + `::after` в P13).
8. **i18n всичко** — `t('key')` calls. Никога hardcoded BG/EN.
9. **Bulgarian dual pricing** до 08.08.2026 (€ + лв at 1.95583).

---

## 1. Page structure (top → bottom)

```
products.php → [+ Добави артикул] btn → отваря wizard view (full-screen)
  ├─ Header (sticky 56px)
  ├─ Search pill (collapsed) → expand to search panel
  ├─ Voice command bar
  ├─ Mode toggle: Единичен / С вариации
  ├─ Section 1: Минимум  (open by default, status="filled" )
  ├─ Section 2: Вариации (closed, status="active", hidden if mode=single)
  ├─ Section 3: Допълнителни (closed, status="empty")
  ├─ Section 4: Снимки (closed, status="active")
  ├─ Section 5: AI Studio (closed, status="empty", magic style)
  └─ Bottom bar (fixed): [Undo] [Печат] [CSV] [Запази · следващ ▼]
       └─ Dropdown: "Като предния" / "Празно"
  
Overlays (z-index 90-91):
  - AI Studio result sheet (преди/след) — slide up from bottom
  - Size groups bottom sheet (други размерни групи)
  - Unit groups bottom sheet (други мерни единици)
  - P12 matrix fullscreen overlay (когато се натисне "Цял екран" в матрица)
```

---

## 2. CSS class names → PHP/HTML mapping

**ALL class names from `mockups/P13_bulk_entry.html` are mandatory.** Не преименувай.

### Header
- `.bm-header` (sticky 56px, light=neumorph, dark=blur)
- `.bm-title` (gradient text "Добави артикул")
- `.icon-btn` (38×38 кръгъл бутон)
- `.icon-btn.scan-btn` (purple gradient + neon)

### Search
- `.find-pill` (collapsed by default)
- `.find-panel.show` (expanded with search input + filters + results)
- `.find-input-wrap` (input + close + voice)
- `.find-filters` (horizontal scrollable chips)
- `.find-filter.active` (purple gradient, "Като последния" е default active)
- `.find-results` → `.find-result` (rows with thumb + name + meta + arrow)

### Voice bar
- `.voice-bar` (sticky, gradient hint card)
- `.voice-bar-mic` (purple/magenta gradient + conic spin)

### Mode toggle
- `.mode-toggle` (pill-shaped, 4px padding)
- `.mode-tab.active` (purple gradient, white text)

### Accordion section
- `.acc-section[data-status="filled"]` — green check icon
- `.acc-section[data-status="active"]` — purple spinning orb icon
- `.acc-section[data-status="empty"]` — muted icon
- `.acc-section.magic[data-status="empty"]` — purple/magenta orb (AI Studio)
- `.acc-section.open` — opens with `max-height: 1800px`, padding restored
- `.acc-head` — клик handler `toggleAcc(this)`
- `.acc-chevron` — rotates 180deg when open

### Fields
- `.field` (margin-bottom: 12px)
- `.field-label` (mono, 9px uppercase)
- `.field-label .req` — red asterisk (required)
- `.field-label .opt-pill` — neumorph pill "ПО ЖЕЛАНИЕ"
- `.field-label .ditto` — chevron-down icon (наследено)
- `.field-hint` (10px faint, под input)

### Inputs
- `.input-row` (flex row: input + buttons)
- `.input-shell` → `.input-text` (42px, neumorph pressed)
- `.fbtn` (38×42 button)
- `.fbtn.cpy` (copy from prev — accent stroke)
- `.fbtn.add` (+ нова — accent text)
- `.fbtn.voice` (purple/magenta gradient)
- `.fbtn.scan` (accent gradient)

### Prices
- `.price-input-shell` (input + cur + buttons)
- `.price-input` (right-aligned, mono, accent color in dark)
- `.price-cur` (€ symbol, mono)
- `.price-bgn` (лв converted, faint, right-aligned)

### Quantity stepper
- `.qty-row` (stepper + voice button)
- `.qty-stepper` (flex 48px)
- `.qty-stepper.warn` (amber for min quantity)
- `.qty-step` (− / + buttons, accent text)
- `.qty-input` (center-aligned, mono, 18px)

### Margin badge
- `.margin-row` (label + badge)
- `.margin-badge` (green pill with up-trend SVG + "+%")

### Chips (sizes / colors)
- `.chip-sz` (size: XS, S, M, L, XL, XXL — pill, 34px)
- `.chip-sz.active` (purple gradient)
- `.chip-add` (dashed border, "+ добави")
- `.chip-col` (color chip with `.chip-col-dot` swatch)
- `.chip-col.active` (pressed state)

### Extra rows (groups buttons)
- `.extra-row` (under chips/select)
- `.groups-btn` (mono pill, "+ други групи →")

### Matrix (in P13 Section 2 inline)
- `.matrix-head-row` (label + actions)
- `.matrix-action.expand` → отваря P12 fullscreen overlay
- `.mx-grid` (CSS Grid: `50px repeat(N, 1fr)` columns)
- `.mx-corner` (top-left "РАЗ. × ЦВ.")
- `.mx-head` (color column headers with dot + name)
- `.mx-rowh` (size row labels)
- `.mx-cell` / `.mx-cell.has-qty` (filled state)
- `.mx-input-qty` (number input, 13px mono)
- `.mx-min-row` → `.mx-input-min` (small amber input под qty за min)

### SKU summary
- `.sku-summary` (info card under matrix)
- `.sku-ic` (gradient circle with check)
- `.sku-text b` (bold count)

### Save row (per section)
- `.save-row` (margin-top: 14px, flex)
- `.save-section-btn` (green gradient + conic shimmer, "Запази")
- `.save-aux-btn` (icon-only buttons: Печат, CSV)

### Photo section (Section 4)
- `.photo-bulk-cta` (dashed border card, "Заснеми всички наведнъж")
- `.photo-bulk-icon` (accent gradient circle с magic SVG)
- `.photo-bulk-title` / `.photo-bulk-sub` (centered text)
- `.photo-bulk-actions` → `.photo-action-btn.primary` + secondary
- `.photo-result-list` → `.photo-result-row`
- `.photo-result-color` (swatch + read-only name from Section 2)
- `.photo-result-thumb` (48px gradient placeholder)
- `.photo-result-conf` / `.photo-result-conf.low` (AI %, green/amber)
- `.photo-result-action.star.is-main` (★ filled amber)

### AI Studio (Section 5)
- `.ai-credits-strip` (gem icon + "17/30 безплатни магии" + "след това €0.05/магия")
- `.ai-link-row` (large clickable card → отваря P8b modal)
- `.ai-link-thumb` (purple/magenta gradient + conic shimmer)
- `.ai-quick-row` → `.ai-quick-btn` (Премахни фон / SEO описание)

### AI Studio result overlay
- `.ai-result-ov` / `.ai-result-ov.show` (backdrop)
- `.ai-result-sheet` (bottom sheet, slide up)
- `.ai-result-grid` (2 columns: преди / след thumbnails)
- `.ai-result-thumb-label.after` (green gradient label)
- `.ai-result-actions` → `.ai-result-btn.primary` (Приеми, green) / `.ai-result-btn.danger` (Отхвърли, red)

### Bottom bar
- `.bottom-bar` (fixed, 12px from edges, max-width 456px)
- `.bb-icon-btn.undo` (red stroke на undo SVG)
- `.bb-icon-btn` (Печат, CSV — neutral)
- `.btn-next` (purple/magenta gradient + conic shimmer + chevron pill)
- `.btn-next-chev` → opens `.next-menu`
- `.next-menu.show` (drop-up menu с 2 items)
- `.next-menu-item` (icon + title + sub)

### Bottom sheets (size/unit groups)
- `.gs-ov` / `.gs-ov.show` (backdrop)
- `.gs-sheet` (slide-up sheet)
- `.gs-handle` (small bar at top)
- `.gs-group` (one group card, neumorph pressed)
- `.gs-group-pin` (small "обувки", "талия" labels)
- `.gs-val` (chip-pill in group)

### Aurora (background atmosphere)
- `.aurora` → 2 `.aurora-blob` divs (animated)

---

## 3. Mode toggle behavior

**State:** `data-mode` атрибут на `.mode-toggle` ("single" | "var")

```js
function setMode(m) {
  document.querySelector('.mode-toggle').setAttribute('data-mode', m);
  document.querySelectorAll('.mode-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === m));
  
  // Section 2 (Вариации) → hidden when single
  document.getElementById('varSection').style.display = (m === 'single') ? 'none' : '';
  
  // Section 1 (Минимум): qty + min fields → show only when single
  document.getElementById('singleQtyField').style.display = (m === 'single') ? '' : 'none';
  document.getElementById('singleMinField').style.display = (m === 'single') ? '' : 'none';
}
```

**Server side (PHP):**
- `mode === 'single'` → `products.has_variations = 0`, `inventory.quantity = $singleQty`, `inventory.min_quantity = $singleMin`
- `mode === 'var'` → `products.has_variations = 1`, qty + min разпределени по `inventory` rows за всеки SKU (size × color combination)

---

## 4. Section 1: Минимум

### Полета (in order)

1. **Име** (`products.name`, required)
   - Input + ↻ copy + voice
   - On blur: validation `strlen >= 2`
   - Voice: Web Speech BG, fallback Whisper

2. **Цена** (`products.retail_price`, required)
   - Number input (decimal) + ↻ + voice + auto-EUR + лв conversion
   - Voice parser: `_wizPriceParse` (commit `4222a66` — DO NOT TOUCH)
   - On change → recompute margin in Section 3 (auto)
   - Display: `priceFormat($price, $tenant)` with `€` cur

3. **Количество** (`inventory.quantity`, required, ONLY for single mode)
   - Stepper (− input +) + voice
   - Default: empty (placeholder "0")
   - Hidden on `mode=var` (qty is per-SKU in matrix)

4. **Минимално кол-во** (`inventory.min_quantity`, required, ONLY for single mode)
   - Stepper warn (amber color) + voice + hint "AI auto-set от количеството (qty/2.5)"
   - Auto-fill on qty change: `Math.round(qty / 2.5) min 1`
   - Hidden on `mode=var` (min is per-SKU in matrix)

5. **Артикулен номер** (`products.code`, optional)
   - Input + ↻ + voice + opt-pill "ПО ЖЕЛАНИЕ"
   - Hint: "Празно → AI ще генерира уникален код автоматично"
   - Auto-generate on save (if empty): `tenant_prefix-YYMMDD-NNNNN` (5-digit incremental within tenant)

6. **Баркод** (`products.barcode`, optional)
   - Input + scan btn + voice + opt-pill "ПО ЖЕЛАНИЕ"
   - Hint: "Празно → AI ще генерира EAN-13 при отпечатване"
   - Scan = native barcode scanner (Capacitor plugin) → fills input

### Save endpoint
`POST /api/products-bulk-save.php`
```json
{
  "section": "minimum",
  "tenant_id": 7,
  "session_id": null | int,
  "mode": "single" | "var",
  "data": {
    "name": "Дамска тениска Tommy Jeans",
    "retail_price": 28.00,
    "single_qty": 5,
    "single_min": 2,
    "code": null,
    "barcode": null
  }
}
```

Response:
```json
{
  "ok": true,
  "product_id": 12345,
  "confidence_score": 30,
  "session_id": 567,
  "next_section_hint": "supplier"
}
```

**confidence_score formula** (Hidden Inventory §5):
- Section 1 saved → +30
- Section 2 saved → +20
- Section 3 saved → +25
- Section 4 saved → +15
- Section 5 saved → +10
- Total: 100

---

## 5. Section 2: Вариации (skip if single)

### Размери
- 6 default chips: XS · S · M · L · XL · XXL (toggle active)
- `.extra-row`:
  - `[+ добави размер]` — inline input modal за custom size (3XL, 44, W34)
  - `[+ други групи →]` — opens `#sizeGroupsSheet` bottom sheet с 6 groups:
    - EU дамски обувки (35-41)
    - EU мъжки обувки (39-46)
    - EU дамски облекло (34-48)
    - Дънки W (W26-W36)
    - Детски (86-128)
    - US/UK (XS-3XL)

Selection logic: tap размер chip → toggle active. Active sizes → matrix rows.

### Цветове
- 5 default chips with swatches (Черен, Бял, Розов, Червен, Син)
- `[+ добави]` → opens color picker modal (color-input + name + воice)

Active colors → matrix columns + photo result rows in Section 4.

### Matrix
- Inline grid: rows = active sizes, columns = active colors
- Each cell:
  - `qty` input (number, default empty)
  - `min` input (small amber, optional)
  - `.has-qty` class on cell when qty > 0
- `[Цял екран]` button → opens `mockups/P12_matrix.html` overlay (when ≥4×4 cells, recommend it)

### SKU summary (auto-computed in PHP)
```
{N_SIZES} размера × {N_COLORS} цвята = {N_SKU} SKU · Σ {TOTAL_QTY} бр.
```

Example: `3 размера × 2 цвята = 6 SKU · Σ 14 бр.`

### Save endpoint
```json
{
  "section": "variations",
  "product_id": 12345,
  "data": {
    "sizes": ["S", "M", "L"],
    "colors": [{"name": "Бял", "hex": "#ffffff"}, {"name": "Розов", "hex": "#ec4899"}],
    "matrix": [
      {"size": "S", "color": "Бял", "qty": 2, "min": 1},
      {"size": "S", "color": "Розов", "qty": 3, "min": 1},
      {"size": "M", "color": "Бял", "qty": 3, "min": 1},
      {"size": "M", "color": "Розов", "qty": 4, "min": 2},
      {"size": "L", "color": "Бял", "qty": 1, "min": 1},
      {"size": "L", "color": "Розов", "qty": 1, "min": 1}
    ]
  }
}
```

PHP creates `inventory` rows за всеки combination (6 SKU = 6 rows).

---

## 6. Section 3: Допълнителни

### Полета (in order)

1. **Доставна цена** (`products.cost_price`)
   - Price input + ↻ + voice + лв conversion
   - On change → recompute margin

2. **Цена на едро** (`products.wholesale_price`, optional)
   - Same pattern

3. **Марж % (auto)** — `.margin-badge` with green gradient
   - Formula: `((retail_price - cost_price) / cost_price) * 100`
   - Display: `+{margin.toFixed(1)}%` with up-trend SVG icon
   - Color: green if >50%, amber if 20-50%, red if <20%
   - Read-only — никога editable

4. **Доставчик** (`products.supplier_id` FK)
   - Select dropdown + ↻ + ↳ "+ нов доставчик" + voice
   - "+ нов" opens inline modal: input name + save → AJAX → adds to dropdown → auto-selects

5. **Категория** (`products.category_id` FK)
   - Same pattern with "+ нова категория"
   - On change → reload subcategory dropdown

6. **Подкатегория** (`products.subcategory_id` FK, depends on category)
   - Disabled until category selected
   - Same pattern with "+ нова подкатегория"

7. **Материя / състав** (`products.material`, optional)
   - Plain text input + voice

8. **Произход** (`products.origin_country`)
   - Select with default 4 options + "+ нова" voice

9. **Мерна единица** (`products.unit`)
   - Select default 4: Брой, Чифт, Кг, Метър
   - `[+ други групи →]` → opens `#unitGroupsSheet` with 5 groups (стандартни, тегло, дължина, обем, площ)

### Save endpoint
```json
{
  "section": "supplier_details",
  "product_id": 12345,
  "data": {
    "cost_price": 12.00,
    "wholesale_price": 20.00,
    "supplier_id": 4,
    "category_id": 12,
    "subcategory_id": 47,
    "material": "100% памук",
    "origin_country": "Турция",
    "unit": "Брой"
  }
}
```

PHP computes margin and stores in `products.margin_pct` (cached).

---

## 7. Section 4: Снимки (АI photo recognition)

### Логика (gen ial)

**Source of truth:** colors selected in Section 2.

**При variations mode:**
1. Митко натиска **"Заснеми всички наведнъж"** или **"Галерия"**
2. Camera opens / file picker → multi-file upload
3. Files (3-10 photos) → upload to `/api/photo-ai-detect.php`
4. Server runs **Gemini Vision API** to detect dominant color per photo
5. Match each photo to a color from Section 2 (using ΔE color distance, threshold ΔE < 25)
6. Return JSON:
   ```json
   {
     "results": [
       {"file_idx": 0, "matched_color": "Бял", "confidence": 0.94, "image_url": "/uploads/p12345_w.jpg"},
       {"file_idx": 1, "matched_color": "Розов", "confidence": 0.72, "image_url": "/uploads/p12345_p.jpg"},
       {"file_idx": 2, "matched_color": null, "confidence": 0.18, "image_url": "/uploads/p12345_unkn.jpg", "suggested": ["Розов", "Червен"]}
     ]
   }
   ```
7. Frontend renders `.photo-result-row` for each detected match
8. **Override UX:** "размени" бутон → modal с list на всички colors → tap да премести photo на друг color
9. **★ Главна** radio per row — само 1 active за целия артикул

**При single mode:**
- 1 large photo card (no AI detection — single product, single photo)
- Camera + Galery buttons
- Photo tips (legnalo, no others, light, sharpness) — кратки 4 chips

### Confidence routing
- `confidence >= 0.85` → auto-attach, status pill "AI 94%" green
- `0.60 <= confidence < 0.85` → require tap-to-confirm, status pill "AI 72%" amber
- `confidence < 0.60` → block + show "Размени" button highlighted

### Save endpoint
```json
{
  "section": "photos",
  "product_id": 12345,
  "data": {
    "photos": [
      {"color_name": "Бял", "image_url": "/uploads/p12345_w.jpg", "is_main": true, "ai_confidence": 0.94},
      {"color_name": "Розов", "image_url": "/uploads/p12345_p.jpg", "is_main": false, "ai_confidence": 0.72}
    ]
  }
}
```

PHP creates `product_images` rows (1 main, others secondary, indexed by color).

---

## 8. Section 5: AI Studio integration

### Credits strip (top of section)
```html
<div class="ai-credits-strip">
  <span class="ai-cred-gem">[gem SVG]</span>
  <span class="ai-cred-text"><b>17 / 30</b> безплатни магии</span>
  <span class="ai-cred-after">след това · <b>€0.05/магия</b></span>
</div>
```

Server-side: `SELECT free_credits_used, free_credits_total FROM tenant_ai_credits WHERE tenant_id=?`

### AI Studio link (`.ai-link-row`)
- Tap → opens `P8b_studio_modal.html` modal с product context
- Modal закача snimkata (от Section 4) и предлага actions
- Cancel / Apply → returns to P13 → triggers `.ai-result-ov` overlay

### Quick actions (2 buttons)
1. **Премахни фон** → opens `.ai-result-ov` overlay with преди/след preview
2. **SEO описание** → AJAX call → fills `products.description_seo` → toast "AI генерира описание"

### AI Result overlay (`.ai-result-ov`)
- Slide up from bottom (transform: translateY(100% → 0))
- Header: "AI завърши обработката · 2.4 сек."
- 2 thumbnails grid: Преди / След (with green "След" label)
- 3 buttons: **Отхвърли** (red text) / **Опитай пак** / **Приеми** (green primary)
- Tap Приеми → save processed image, close overlay
- Tap Отхвърли → discard, close overlay
- Tap Опитай пак → re-run with different params

---

## 9. Bottom bar logic

### Buttons
1. **Undo** (red icon) — `.bb-icon-btn.undo`
   - Maintains client-side action stack (last N=20 changes)
   - Each chip toggle, input change, photo upload pushes to stack
   - Tap Undo → pops last action, reverts UI + sends `POST /api/products-bulk-undo.php` ако action was server-side saved

2. **Печат** — opens print modal
   - Lists all SKUs (от current product + всички в bulk session)
   - Print mode tabs: € + лв (default) / Само € / Без цена
   - Toggle "Печат без баркод"
   - Combos with qty steppers (per SKU)
   - "Печатай всички N етикета" green CTA

3. **CSV** — downloads current product OR full bulk session as CSV
   - Header row: name, code, barcode, retail, cost, supplier, category, sizes, colors, qty
   - Rows: 1 per SKU

4. **Запази · следващ** — main primary button
   - Saves всички unsaved sections atomically
   - Tap chevron → opens `.next-menu` (slide up):
     - **"Като предния"** — copies all fields from last saved (template inheritance) + adds ditto markers
     - **"Празно"** — clears form, fresh start

### Bulk session state
- 1-ви артикул save → creates `bulk_sessions` row, sets `template_product_id`
- 2-ри+ артикул → reads template, applies inheritance
- "Като предния" → uses latest saved as template (sets `template_product_id` to it)
- "Празно" → clears `template_product_id` for next item
- Session ends on close OR after 30 min idle
- All bulk session items linked via `bulk_session_items.session_id`

---

## 10. Search ("Намери и копирай")

### Collapsed state
- `.find-pill` with text "Намери артикул да копираме" + voice icon
- Tap → expand to `.find-panel.show`

### Expanded state
- Search input + close + voice
- Filter chips (horizontal scroll):
  - **"Като последния"** (default active, ↻ icon) — copies last saved
  - "Всички" — no filter
  - "Tommy Jeans", "Бельо", "Тениски", "Наскоро" — auto-generated from recent categories/suppliers
- Results list (max-height: 280px, overflow-y: auto)
- Each result: thumb + name + meta (supplier · price · sizes · SKU count) + arrow

### On tap result
- AJAX: `GET /api/products-search-copy.php?id=12345&action=copy`
- Returns full product data
- Frontend: collapse panel + populate all fields with ditto markers
- Toast: "Копирано от: {product_name}"

---

## 11. Voice integration (visual only — Claude Code implements)

### Where voice buttons appear (`.fbtn.voice` or `.find-pill-mic` or `.voice-bar-mic`)
- Header voice (left side, large): triggers continuous listening
- Voice bar (gradient hint): "Кажи на AI" + example
- Search pill: voice icon
- Search input expand: voice icon (replace value with transcription)
- Every text/number input: small voice button on right
- Bulk continuation: voice trigger word "следващ" → автоматично tap [Запази · следващ]

### Voice command examples (intents)
- "Нов артикул" → focus Section 1 Име
- "Тениска двадесет и осем лева" → fill Име + retail_price
- "Размер S M L" → toggle active chips
- "Бял розов червен" → toggle color chips
- "Снимай всички" → trigger camera bulk upload
- "Магия фон" → trigger Премахни фон
- "Запази минимум" → tap save in Section 1
- "Като предния" → tap "Като предния" in Next menu
- "Назад" → undo last action
- "Затвори" → close wizard

### Trigger words (Bulgarian + non-BG fallback to Whisper)
- "запиши" / "запази" / "save"
- "следващ" / "next"
- "назад" / "back" / "undo"
- "магия" / "magic"

---

## 12. DB schema additions (Phase B migration)

**File:** `/var/www/runmystore/db/migrations/2026_05_p13_bulk_entry.sql`

```sql
-- products: confidence_score + Hidden Inventory fields
ALTER TABLE products ADD COLUMN confidence_score TINYINT UNSIGNED DEFAULT 0 COMMENT 'Hidden Inventory: 0-100. Не се показва на Митко.';
ALTER TABLE products ADD COLUMN has_variations TINYINT(1) DEFAULT 0;
ALTER TABLE products ADD COLUMN last_counted_at DATETIME DEFAULT NULL;
ALTER TABLE products ADD COLUMN counted_via ENUM('manual','barcode','rfid','ai') DEFAULT NULL;
ALTER TABLE products ADD COLUMN first_sold_at DATETIME DEFAULT NULL;
ALTER TABLE products ADD COLUMN first_delivered_at DATETIME DEFAULT NULL;
ALTER TABLE products ADD COLUMN zone_id INT DEFAULT NULL;
ALTER TABLE products ADD COLUMN subcategory_id INT DEFAULT NULL;
ALTER TABLE products ADD COLUMN wholesale_price DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE products ADD COLUMN margin_pct DECIMAL(5,2) DEFAULT NULL COMMENT 'Cached: ((retail-cost)/cost)*100';
ALTER TABLE products ADD COLUMN material VARCHAR(255) DEFAULT NULL;
ALTER TABLE products ADD COLUMN origin_country VARCHAR(100) DEFAULT NULL;
ALTER TABLE products ADD COLUMN unit VARCHAR(50) DEFAULT 'Брой';

-- subcategories table
CREATE TABLE IF NOT EXISTS subcategories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  category_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant_cat (tenant_id, category_id),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- bulk sessions
CREATE TABLE IF NOT EXISTS bulk_sessions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME DEFAULT NULL,
  template_product_id INT DEFAULT NULL,
  total_saved INT DEFAULT 0,
  total_sku INT DEFAULT 0,
  INDEX idx_tenant_user (tenant_id, user_id),
  INDEX idx_active (ended_at)
);

-- bulk session items
CREATE TABLE IF NOT EXISTS bulk_session_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  session_id INT NOT NULL,
  product_id INT NOT NULL,
  saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  position INT NOT NULL,
  INDEX idx_session (session_id),
  FOREIGN KEY (session_id) REFERENCES bulk_sessions(id) ON DELETE CASCADE
);

-- product_images: AI confidence cache
ALTER TABLE product_images ADD COLUMN ai_confidence DECIMAL(3,2) DEFAULT NULL;
ALTER TABLE product_images ADD COLUMN ai_detected_color VARCHAR(50) DEFAULT NULL;
ALTER TABLE product_images ADD COLUMN color_override TINYINT(1) DEFAULT 0 COMMENT 'Митко override AI detection';

-- tenant_ai_credits (if not exists)
CREATE TABLE IF NOT EXISTS tenant_ai_credits (
  tenant_id INT PRIMARY KEY,
  free_credits_used INT DEFAULT 0,
  free_credits_total INT DEFAULT 30,
  paid_credits_balance INT DEFAULT 0,
  last_reset_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
INSERT IGNORE INTO tenant_ai_credits (tenant_id) SELECT id FROM tenants;
```

**Use PREPARE/EXECUTE for `ADD COLUMN` (MySQL 8 не поддържа `IF NOT EXISTS`).**
Помощник pattern:
```sql
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'confidence_score');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE products ADD COLUMN confidence_score TINYINT UNSIGNED DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
```

---

## 13. i18n keys (Phase D)

Add to `i18n_translations` for `bg` and `en`:

```json
{
  "bulk.title": "Добави артикул",
  "bulk.search.placeholder": "Намери артикул да копираме",
  "bulk.search.input": "Търси име · баркод · код",
  "bulk.search.filter.like_last": "Като последния",
  "bulk.search.filter.all": "Всички",
  "bulk.search.filter.recent": "Наскоро",
  "bulk.voice.title": "Кажи на AI",
  "bulk.voice.example": "\"Тениска 28лв · бял розов · S M L\"",
  "bulk.mode.single": "Единичен",
  "bulk.mode.var": "С вариации",
  "bulk.section.minimum": "Минимум",
  "bulk.section.variations": "Вариации",
  "bulk.section.supplier_details": "Допълнителни",
  "bulk.section.photos": "Снимки",
  "bulk.section.ai_studio": "AI Studio",
  "bulk.field.name": "Име",
  "bulk.field.retail_price": "Цена",
  "bulk.field.qty": "Количество",
  "bulk.field.min_qty": "Минимално кол-во",
  "bulk.field.code": "Артикулен номер",
  "bulk.field.barcode": "Баркод",
  "bulk.field.cost_price": "Доставна цена",
  "bulk.field.wholesale_price": "Цена на едро",
  "bulk.field.margin": "Марж (auto)",
  "bulk.field.supplier": "Доставчик",
  "bulk.field.category": "Категория",
  "bulk.field.subcategory": "Подкатегория",
  "bulk.field.material": "Материя / състав",
  "bulk.field.origin": "Произход",
  "bulk.field.unit": "Мерна единица",
  "bulk.field.sizes": "Размери",
  "bulk.field.colors": "Цветове",
  "bulk.field.optional": "ПО ЖЕЛАНИЕ",
  "bulk.hint.code_auto": "Празно → AI ще генерира уникален код автоматично.",
  "bulk.hint.barcode_auto": "Празно → AI ще генерира EAN-13 при отпечатване.",
  "bulk.hint.min_auto": "AI auto-set от количеството (qty/2.5).",
  "bulk.matrix.label": "Брой по комбинация · мин.",
  "bulk.matrix.fill_all": "Всички = 2",
  "bulk.matrix.expand": "Цял екран",
  "bulk.matrix.sku_summary": "{n_sizes} размера × {n_colors} цвята = {n_sku} SKU · Σ {total} бр.",
  "bulk.photos.cta_title": "Заснеми всички наведнъж",
  "bulk.photos.cta_sub": "AI ще разпознае цветовете и закачи всяка снимка автоматично",
  "bulk.photos.camera": "Камера",
  "bulk.photos.gallery": "Галерия",
  "bulk.photos.is_main": "Главна",
  "bulk.photos.swap": "Размени",
  "bulk.ai.credits": "<b>{used} / {total}</b> безплатни магии",
  "bulk.ai.after": "след това · <b>€{price}/магия</b>",
  "bulk.ai.open_studio": "Отвори AI Studio",
  "bulk.ai.studio_sub": "снимка · фон · описание · магия",
  "bulk.ai.remove_bg": "Премахни фон",
  "bulk.ai.seo_desc": "SEO описание",
  "bulk.ai.result_title": "AI завърши обработката",
  "bulk.ai.result_before": "Преди",
  "bulk.ai.result_after": "След",
  "bulk.ai.result_accept": "Приеми",
  "bulk.ai.result_retry": "Опитай пак",
  "bulk.ai.result_reject": "Отхвърли",
  "bulk.save.section": "Запази",
  "bulk.save.print": "Печат",
  "bulk.save.csv": "CSV експорт",
  "bulk.bottom.undo": "Отмени стъпка",
  "bulk.bottom.print_all": "Печат всички",
  "bulk.bottom.csv_all": "CSV сесия",
  "bulk.bottom.next": "Следващ",
  "bulk.next.like_prev": "Като предния",
  "bulk.next.like_prev_sub": "Наследява име · цена · доставчик · категория",
  "bulk.next.empty": "Празно",
  "bulk.next.empty_sub": "Нов артикул от 0 (различен доставчик/тип)",
  "bulk.groups.size_title": "Други размерни групи",
  "bulk.groups.unit_title": "Други мерни единици",
  "bulk.groups.tap_to_use": "тапни група за активиране",
  "bulk.groups.tap_to_pick": "тапни за избор"
}
```

---

## 14. Photo AI detection endpoint specification

**File:** `/var/www/runmystore/api/photo-ai-detect.php`

```php
<?php
// POST multipart/form-data
// Files: file1, file2, ... (up to 10)
// JSON body: {product_id, target_colors: [{"name": "Бял", "hex": "#ffffff"}, ...]}

$prompt = "Detect dominant clothing color in image. Match against these target colors: {LIST}. Return JSON: {matched_color: 'name'|null, confidence: 0.0-1.0, suggested: ['alt1', 'alt2']}";

// Loop files → call Gemini Vision API:
$response = $gemini->vision($file_url, $prompt);
// Parse response → match colors

// Return JSON of all results
```

Async fallback:
- If >5 files OR processing >2 seconds → respond `{"async": true, "job_id": "abc123"}`
- Frontend polls `/api/photo-ai-detect-status.php?job_id=abc123` every 2s
- Cron worker `cron/photo-ai-detect-worker.php` processes queued jobs

Confidence routing (frontend):
```js
function renderPhotoResult(r) {
  let confClass = 'photo-result-conf';
  if (r.confidence >= 0.85) {
    // auto
  } else if (r.confidence >= 0.60) {
    confClass += ' low'; // amber
  } else {
    // block + force манual override
    return renderUnknownPhoto(r);
  }
  // ... render row
}
```

---

## 15. Checklist преди commit

- [ ] **0 emoji в UI** (compliance check)
- [ ] **Никога "Gemini" в UI** — само "AI"
- [ ] All `t('key')` calls — никога hardcoded BG strings
- [ ] All prices via `priceFormat($amount, $tenant)` 
- [ ] Bulgarian dual pricing on `country_code='BG'` до 08.08.2026
- [ ] PHP `php -l products.php` exit 0
- [ ] Design Kit compliance: `bash design-kit/check-compliance.sh products.php` exit 0
- [ ] Visual diff vs `mockups/P13_bulk_entry.html` ≤ 1% (use Chrome DevTools 375px viewport)
- [ ] Smoke test:
  - [ ] Single mode: създай артикул "Test 1" с qty=5, min=2 → save Section 1 → confidence=30
  - [ ] Var mode: създай артикул "Test 2" S/M × Бял/Розов матрица → save Section 2 → 4 inventory rows
  - [ ] Photo upload: качи 2 снимки → AI detect → appears in result list
  - [ ] AI Studio link: tap → отваря P8b modal
  - [ ] Bulk session: save 1-ви → tap "Като предния" → 2-ри артикул inherited fields
  - [ ] Print: tap → modal с SKUs
  - [ ] Undo: chip toggle → undo → reverts

---

## 16. Common pitfalls (DO NOT)

- ❌ Не променяй CSS class names от mockup
- ❌ Не "опростявай" sacred neon glass dark mode
- ❌ Не премахвай anim ations (conic spin, aurora drift)
- ❌ Не използвай emoji вместо SVG (Bible §14)
- ❌ Не показвай "confidence_score" число на Митко
- ❌ Не създавай нов wizard step (P13 е 1 page accordion, не 6 steps)
- ❌ Не пиши hardcoded "лв"/"€"/"BGN" — use `priceFormat()`
- ❌ Не пиши hardcoded BG strings — use `t('key')`
- ❌ Не commit-вай без `php -l` + compliance check
- ❌ Не deploy direct в production — staging first via `/tmp/staging_*/`

---

## 17. After successful Phase C deployment

Notify Тихол in Slack/чат:
```
S97.P13_BULK_ENTRY ✅ deployed
- products.php rewritten (accordion 5 sections)
- Single + Variations modes
- Photo AI detection
- Bulk session continuation
- Undo + Print + CSV bottom bar
- All sections per-section save with confidence_score
- All compliance gates passed

Ready for Phase F (Capacitor APK rebuild).
```

End of `PRODUCTS_BULK_ENTRY_LOGIC.md`
