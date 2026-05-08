# 📦 HANDOFF — products.php P2 + P3 (mockups APPROVED)

**Дата:** 08.05.2026
**Статус:** P2_home_v2 + P3_list_v2 одобрени от Тихол
**Source of truth:** `DESIGN_SYSTEM.md` v4.1 BICHROMATIC (Bible)
**Reference implementation (eталон):** `life-board.php`
**Replaces в products.php:** scrHome (~ред 4353-4670) + scrProducts (~ред 4682-4726)

---

## 0. APPROVED FILES

| File | Lines | Purpose |
|---|---|---|
| `mockups/P2_home_v2.html` | 1413 | products.php главна — search + add + suppliers + здраве + 6 сигнала |
| `mockups/P3_list_v2.html` | 1605 | products.php list view — filter stack + variations drawer |

И двата файла:
- Light = default · Dark = toggle option (sun/moon в header)
- Mobile-first 375px (Z Flip6 viewport)
- Вкл. canonical header (7 елемента) + bottom nav (4 tabs)
- Aurora background (multiply / plus-lighter)
- Brand shimmer animation (Bible §22.5.1)
- Anti-flicker theme init inline преди paint
- `setAttribute('data-theme', X)` (НЕ removeAttribute) — Bible §22.5.6

---

## 1. P2 — products home

### 1.1 Структура (по ред)

Header (canonical) → .title-row + store-picker → .search-bar → .add-row (CTA + Like-prev) → .health-card → .section-head + .sup-strip → .inv-card → .sig-head + 6× .lb-card.qN → .see-all-bottom → Bottom nav.

### 1.2 Health bar логика

| Threshold | Class | Color |
|---|---|---|
| pct >= 80 | (default) | `var(--success)` |
| 50-79 | `.warn` | `var(--warning)` |
| < 50 | `.bad` | `var(--danger)` |

### 1.3 6 collapsible сигнала (lb-card)

Данните идват **готови** от `ai_insights` (Закон №2 — PHP смята, AI говори). Pattern 1:1 от life-board.php. q1-q6 cards използват **Bible v4.1 vars**.

### 1.4 Store picker

`<select class="store-picker" onchange="location.href='?store='+this.value">`. SQL `SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name`. При смяна → SET `$_SESSION['store_id']` → redirect.

---

## 2. P3 — list view (Виж всички 247)

### 2.1 Структура

Header → .page-hdr (back + title + sort + store-picker) → .search-bar → .active-chips → .qfltr-row (4 quick filters) → .sigfltr-row (6 q-color pills) → .f-chip-row × 2 (categories + suppliers) → 5× .prod-row → .pag-row → .var-sheet (drawer, hidden) → Bottom nav.

### 2.2 Sort dropdown (6 опции)

| `data-sort` | SQL ORDER BY |
|---|---|
| `name` (default) | `p.name ASC` |
| `price_asc` | `p.retail_price ASC` |
| `price_desc` | `p.retail_price DESC` |
| `stock_asc` | `store_stock ASC` |
| `stock_desc` | `store_stock DESC` |
| `newest` | `p.created_at DESC` |

### 2.3 Quick filters (4 pills)

| Pill | Backend param |
|---|---|
| Цена | `qfPrice=min:max` |
| Наличност | `qfStock=all|has|zero|counted|uncounted` |
| Марж | `qfMargin=under|mid|over` (само ако can_see_margin) |
| Дата | `qfDate=today|7d|30d` |

### 2.4 Signal filter pills (q1-q6)

q1 → zero_stock | q2 → at_loss | q3 → top_sales | q4 → custom (gain causes) | q5 → running_out | q6 → zombie. Count badges от `ai_insights` (hourly cache).

### 2.5 Product row DB полета

- `products.code` (НЕ sku)
- `products.retail_price` (НЕ sell_price)
- `products.image_url` (НЕ image)
- `inventory.quantity` (НЕ qty)
- `suppliers.name` (joined)

---

## 3. Variations drawer

### 3.1 Trigger
Tap на `.prod-row` → `openVariations(productId)` → fetch + show drawer.

### 3.2 Структура
.var-handle → .var-head (photo + title + ✕) → .var-actions (🖨 Печатай всички + ⤓ Експорт) → .var-summary (3 cells) → .var-list (.var-row × N).

### 3.3 Затваряне
Tap backdrop / ✕ / ESC.

### 3.4 Print "всички" — НОВ AJAX endpoint

POST `products.php?ajax=print_all_variations` — body: `parent_product_id` + `format`. Backend: fetch all child products → loop printLabel() → return `{printed: N, errors: []}`.

### 3.5 Експорт — НОВ AJAX endpoint

POST `products.php?ajax=export_variations` — body: `parent_product_id` + `format=csv|xlsx`. Output CSV header + rows: Цвят, Размер, Код, Баркод, Стока, Цена дребно, Цена едро, Доставна. UTF-8 BOM за Excel.

### 3.6 Per-row print (.var-row-print)

POST `products.php?ajax=print_label` — `product_id` + `count` + `format`. `stopPropagation()` иначе trigger-ва row tap.

---

## 4. Compliance checklist (Bible §14)

- [x] Header / Bottom nav canonical
- [x] [data-theme="light"] + [data-theme="dark"] правила
- [x] 0 hardcoded hex (var(--*))
- [x] .glass cards: 4 spans
- [x] .shine / .glow display:none в light
- [x] Tokens: --radius / --radius-sm / --radius-pill / --radius-icon
- [x] Shadows: --shadow-card / --shadow-card-sm / --shadow-pressed
- [x] Transitions: var(--dur) + var(--ease)
- [x] prefers-reduced-motion: reduce
- [x] safe-area-inset
- [x] Body padding-top 56px + padding-bottom 88px+
- [x] Mobile-first max-width 480px
- [x] Brand shimmer + pulse
- [x] Anti-flicker (NO body transition)
- [x] setAttribute('data-theme', X)
- [x] Google Fonts (Montserrat 400-900 + DM Mono 400-500)
- [x] {T_KEY} placeholders за i18n
- [x] Без "Gemini"/"fal.ai"/"Anthropic" в UI

---

## 5. i18n keys (нови за `/lang/*.json`)

products.title, products.search_placeholder, products.add_new, products.like_previous, products.suppliers, products.see_all, products.warehouse_health, products.out_of_stock_short, products.zombie, products.no_cost, products.inventory, products.need_counting, products.last_run, products.days_ago, products.start, products.ai_sees, products.things, products.list.all, products.list.search_full, products.list.clear_all, products.list.quick_filters, products.list.by_signal, products.list.categories, products.list.price, products.list.stock, products.list.margin, products.list.date, products.list.load_more, products.list.var, products.list.pcs, products.sort.* (6), products.var.print_all, products.var.export, products.var.variations, products.var.total_stock, products.var.all_variations, q1-q6.name + q1-q6.tag, color.* (палитра).

ВСИЧКИ цени през `priceFormat($amount, $tenant)`.

---

## 6. Open questions

1. **Wizard structure (P4):** Toggle Единичен/Вариации mandatory ИЛИ conditional?
2. **AI Studio entry:** wizard step 5 ИЛИ post-creation от life-board?
3. **Color → hex map:** партиал `partials/color-map.php` — нужна спецификация
4. **Експорт format:** CSV само ИЛИ + XLSX? UTF-8 BOM?
5. **"Печатай всички" ред:** по код / по цвят-размер / по DB?
6. **Health bar threshold:** 80/50 ОК?

---

## 7. Next mockups

- **P1** — Empty state
- **P4-P6** — Wizard 3 стъпки (зависи от Q1)
- **A1-A6** — AI Studio (зависи от Q2)

---

## 8. PHP implementation notes

1. Backup: `cp products.php products.php.bak.S<XX>_<TIMESTAMP>`
2. Запази INTACT: wizGo / wizSave / wizMatrix, searchInlineMic, openManualWizard, openLikePreviousWizardS88, AJAX endpoints (sections/list/search/product_detail/variations_matrix), printLabel BLE TSPL.
3. Премахни: q-head + h-scroll секции, .add-card glass, qfltr-row без tokens.
4. Pre-commit hook + check-compliance.sh = 0 errors задължително.
5. Тест: Z Flip6 (~373px) light + dark + Capacitor APK.

---

## 9. References

| Source | Path |
|---|---|
| Bible | `DESIGN_SYSTEM.md` |
| Eталон | `life-board.php` |
| Текущ код | `products.php` (~14600 реда) |
| Logic | `docs/PRODUCTS_DESIGN_LOGIC.md` |
| Wizard | `PRODUCTS_WIZARD_v4_SPEC.md` |
| Wizard audit | `WIZARD_FIELDS_AUDIT.md` |
| AI Studio | `docs/AI_STUDIO_LOGIC.md` |
| Compliance | `CLAUDE_CODE_DESIGN_PROMPT.md` |

---

**КРАЙ.**

