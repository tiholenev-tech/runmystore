# 📦 RunMyStore.AI · Products Wizard Mockups — CONSOLIDATED HANDOFF

**Дата:** 08.05.2026
**Сесии:** 3 Шеф-чат orchestration sessions
**Статус:** 6 от 9 mockups APPROVED · 3 PENDING (P7/P8/P9)

---

## 0 · Източници (source of truth)

| Source | Path | Защо |
|---|---|---|
| Bible | `DESIGN_SYSTEM.md` v4.1 BICHROMATIC (~2750 реда) | Авторитет за всичко визуално |
| Live reference | `life-board.php` | Eталон на dark mode тон |
| Source за rewrite | `products.php` (14617 реда) | Логика 1:1 |
| Предишен handoff #1 | `HANDOFF_PRODUCTS_P2_P3.md` | P2 home + P3 list view decisions |
| Предишен handoff #2 | `HANDOFF_P4_P4b_FIND_COPY.md` | P4 wizard step + P4b photos + Find&Copy logic |
| Този документ | `HANDOFF_CONSOLIDATED.md` | Свежа консолидация на всичко |

---

## 1 · APPROVED files (6 mockups + 2 handoffs)

| File | Lines | Purpose |
|---|---|---|
| `P2_home_v2.html` | 1413 | products.php главна (suppliers + inventory + 6 q-cards + health bar) |
| `P3_list_v2.html` | 1605 | "Виж всички 247" list view + variant drawer |
| `P4_wizard_step1.html` | 1957 | Wizard consolidated Step 2 (photo + name/price/sup/cat/qty/code) — Bible §9.8 fc-pill, 🔍 Намери и копирай drawer |
| `P4b_photo_states.html` | 1102 | 6 photo states (A: empty, B: filled+AI, C: multi empty, D: multi 3 photos, E: camera picker без бранд, F: camera loop counter) |
| `P5_step4_variations.html` | 1477 | Step 4 — axis tabs / chips / pinned groups / matrix CTA / AI nudge |
| `P6_matrix_overlay.html` | 714 | mxOverlay fullscreen — 5×3 size×color qty matrix |

И двата handoff файла + Bible — заключени.

---

## 2 · PENDING (нов чат продължава)

| File | Source line range | Описание |
|---|---|---|
| **P7** | products.php 8022+ (`renderWizStep2`) | "Препоръчителни / Допълнителни данни" — cost / wholesale / profit% / композиция / произход / zone / AI Studio prompt в footer (variant flow) |
| **P8** | products.php 7689-7749 (`renderStudioStep`) | AI Studio (own wizard step) — credits + 3 options (Бял фон 0.05€ / AI Магия дрехи 0.50€ с 6 модели / AI Магия предмети 0.50€ с 8 presets) + skip |
| **P9** | products.php 7615-7684 (Step 6 print) | Print preview / final save — "Артикулът е записан!" + 3 print mode tabs + combos list + Печатай всички + Свали CSV |

**ВАЖНО:** Step 5 inline matrix (`renderWizPagePart2 step===5`, ред 7475-7610) изглежда е dead code (replaced от mxOverlay). Не правете отделен mockup за това.

---

## 3 · DECISIONS взети през сесиите (всички финални)

### 3.1 Wizard structure
- Step 4 (Variations) → tap matrix CTA → mxOverlay (P6) → close → Save → next е Step 8 (Препоръчителни) или AI Studio
- Logic 1:1 със products.php — само visual redesign
- Single mode flow: 2-step (Step 2 consolidated всичко основно + Step 8 препоръчителни)
- Variant mode flow: Step 2 + Step 4 (axes) + mxOverlay + Step 8 + AI Studio

### 3.2 Bible §9.8 fc-pill pattern (мandatory за всеки input)
- Container: 52px height, `radius-pill 999px`
- Input + mic 38x38 ВЪТРЕ в pill (не 4 отделни бутона)
- Action chips навън: ↻ (32x52) + + (32x52) + 📊 scan (32x52)
- Total bтons -40% vs стария code

### 3.3 "🔍 Намери и копирай" (replaces "📋 Като предния")
- 60px tall pill (title + subtitle "Копирай данни от друг артикул")
- Bottom sheet drawer (88vh)
- 5 filter tabs: Последни (default) / Топ продавани / Същ доставчик / Същ артикул / Всички
- Default view: ПОСЛЕДНИ 10 ДОБАВЕНИ (равноправни, без featured)
- Tap on item → copies fields (без image, barcode, code, qty, min_qty) → close + haptic [5,30,10]
- 3 нови AJAX endpoints (recent / search / copy_fields) — виж section 4

### 3.4 Camera picker tip — generic (без брандове)
- **Стар bug:** products.php ред 8225 hardcoded "Самсунг ще запомни" — само за Z Flip6
- **Нов текст:** "Ако се отвори селфи камерата: излез и я обърни на задна. Веднъж го направиш — телефонът помни."
- Без марки (1000+ телефони на пазара)

### 3.5 Emoji → SVG migration (приложено)
- 📦 (Единичен / Variant / Брой / Min) → SVG icons
- 📊 (С Вариации) → 4-grid SVG
- 📋 (Като предния) → search lupa SVG
- 💡 → info-circle SVG (gradient bg)
- 0 emoji в production UI (Bible §14)

### 3.6 Photo states (6 variants, P4b)
- A: Single empty (placeholder + 4 tips)
- B: Single filled (preview + AI разпозна pills)
- C: Variant multi empty (toggle "Снимки на вариации" active)
- D: Variant multi с 3 photos (★ ГЛАВНА badge)
- E: Camera picker drawer (без бранд tip)
- F: Camera loop counter ("Снимай цвят N" + 72px shoot orb)

### 3.7 Matrix overlay (P6)
- Fullscreen modal върху Step 4
- Header: ‹ Назад / "Матрица на бройките" + "5 размера × 3 цвята · 15 комбинации"
- Info card: "Въведи бройка... МКП auto"
- Quick fill: Всички=1/2/5/10 / Изчисти / Voice 🎤
- Scrollable table: row labels (S/M/L/XL/XXL) sticky + 3 color cells per row
- Stats footer: Попълнени / Общо бр / МКП общо
- Actions: Откажи / Готово (green gradient + conic shimmer)

### 3.8 Communication style със Тихол (от userPrefs)
- Винаги български, кратко, без "може би"
- Не питай "готов ли си"
- 60% позитиви + 40% honest critique
- "Ти луд ли си" = сигнал за забравен контекст
- Действай при техническо, питай при логическо/UX

---

## 4 · BACKEND нужди (нови — за Claude Code session по-късно)

### 4.1 Нови AJAX endpoints
```php
// products.php?ajax=recent_products
{ limit: 10, store_id: $sid }
→ JSON [{ id, name, code, supplier_id, supplier_name, category_id,
         retail_price, image_url, created_at, age_label }, ...]

// products.php?ajax=search_products
{ q, filter: 'recent'|'top'|'same_supplier'|'same_category'|'all',
  context_supplier_id?, context_category_id?, limit: 30 }
→ JSON [...]

// products.php?ajax=copy_fields_from_product
{ source_product_id }
→ JSON { name, retail_price, cost_price, wholesale_price,
         supplier_id, supplier_name, category_id, category_name,
         subcategory_id, composition, origin_country, unit }
// НЕ копира: image_url, barcode, code, quantity, min_quantity
```

### 4.2 SQL примери (от 3-те филтъра)
```sql
-- recent (default)
SELECT * FROM products WHERE tenant_id=? AND parent_id IS NULL AND is_active=1
ORDER BY created_at DESC LIMIT 10

-- top sellers (30d)
SELECT p.* FROM products p
JOIN sale_items si ON si.product_id = p.id
JOIN sales s ON s.id = si.sale_id
WHERE p.tenant_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  AND s.status != 'canceled' AND p.parent_id IS NULL
GROUP BY p.id ORDER BY SUM(si.quantity) DESC LIMIT 10

-- same supplier
SELECT * FROM products WHERE tenant_id=? AND supplier_id=?
  AND parent_id IS NULL AND is_active=1
ORDER BY created_at DESC LIMIT 10

-- search
SELECT * FROM products WHERE tenant_id=?
  AND (name LIKE ? OR code LIKE ? OR barcode LIKE ?)
  AND parent_id IS NULL AND is_active=1
ORDER BY name LIMIT 30
```

### 4.3 Нови i18n keys (16)
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

## 5 · OPEN QUESTIONS (10 — без отговор)

**Унаследени (от P2/P3 sessions):**
1. AI Studio entry point: в Step 5 ИЛИ post-creation от life-board nudge?
2. Color → hex map: има ли спецификация (`partials/color-map.php`)?
3. Експорт format (P3 drawer): CSV само ИЛИ CSV + XLSX?
4. Print order: по код / цвят-размер / DB order?
5. Health bar threshold: 80/50 ли остава?
6. Wizard structure — РЕШЕНО (1:1 със сегашната consolidated step)

**Нови (от P4/P4b/P5/P6):**
7. **"Намери и копирай" tap behavior:** мигом copy + Toast undo (5s) ИЛИ preview confirm? *Препоръка: мигом + Toast.*
8. **Filter "Същ доставчик/категория":** context-aware (от current S.wizData) ИЛИ global filter? *Препоръка: context-aware (disabled tab ако context празен).*
9. **Empty state в drawer когато 0 history:** info card "Няма добавени артикули. Първият ще се появи тук."?
10. **Voice search behavior в drawer:** BG → Web Speech, други → Whisper Groq (consistent със P2/P3 search bar)?

---

## 6 · BACKEND — какво НЕ ПИПА production code

При rewrite на products.php (Claude Code session по-късно):
- `wizGo` / `wizSave` / `wizCollectData`
- `wizPhotoCameraLoop` / `wizCamShoot`
- Bluetooth `printLabel` (TSPL protocol на DTM-5811 принтера)
- Sale.php integration
- `_wizMicApply` voice handler — **S95 voice STT LOCKED 04.05.2026** (commits `4222a66` + `1b80106`)

Само visual markup се rewrite-ва. JS handler functions remain wired to the same DOM IDs (`wName`, `wPrice`, `wSupDD`, `wCatDD`, `wSubcat`, `wSingleQty`, `wMinQty`, `wCode`, `wBarcode`, и т.н.).

---

## 7 · ROLLOUT (когато P7/P8/P9 са готови)

Преди rewrite на products.php:
1. Backup: `cp products.php products.php.bak.S<XX>_<TIMESTAMP>`
2. PHP syntax check: `php -l products.php` (mandatory)
3. Compliance check: `bash /var/www/runmystore/design-kit/check-compliance.sh products.php` (exit 0)
4. Test viewport: Z Flip6 ~373px + Light + Dark + Capacitor APK
5. Git commit format: `S[XX]: products.php wizard visual rewrite (P2-P9)`
6. Push: `origin/main`

---

**КРАЙ.** Всички decisions взети през тази Шеф-чат session series са по-горе.
