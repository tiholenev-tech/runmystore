# SESSION S88.PRODUCTS.KAKTO_PREDNIA — HANDOFF

**Date:** 2026-04-29
**Files touched:** `products.php` only
**Backup:** `products.php.bak.s88kp_0527`
**Mockup source:** `kato-predniq-law.html` (1:1 base for visual)
**Authority:** `DESIGN_LAW.md` §2 (palette), §7 (glass — 4-span), §8 (buttons), §10 (inputs), §16 (forbidden)

---

## What changed

### 1. PHP — `products.php?ajax=last_product` extended
Now returns names + variant axes alongside the previous payload:
- `supplier_name`, `category_name`, `subcategory_name` (LEFT JOINs)
- `subcategory_id` (column read from products)
- `has_variants` flag
- `variant_axes`: `{ colors: [...], sizes: [...], by_color: { color → [sizes] } }` derived from `SELECT color, size FROM products WHERE parent_id=:srcId AND is_active=1`

The existing `next_code` (trailing-number increment) and `source_qty` are unchanged — option **B** chosen by Тихол: "BIK-007" → "BIK-008" / "47" → "48".

### 2. CSS — added inside the existing `<style>` block
- New aliases: `.q-magic` (280/310), `.q-gain` (145/165) — DESIGN_LAW §2.5.
- All new component classes namespaced with `.kp-` prefix to avoid collisions with the legacy wizard.
- 4-span glass pattern preserved on every glass card (shine + shine-bottom + glow + glow-bottom).
- Photo hero, name card, copied card (collapsible 8 fields), section heads, color cards, single section, copy-qty checkbox row, sticky bottom bar (Печат + AI Studio).
- §2.6.1 swatch palette: `.kp-swatch.c-{black,white,blue,red,beige,pink,green,yellow,violet,gray,brown}`.
- `tabular-nums` on every numeric value.

### 3. JS — wizard flow rewritten
- `openLikePreviousWizardS88()` no longer opens `openManualWizard()`. It now calls `renderLikePrevPageS88(d)` which builds a stand-alone overlay (`#kpModal`) matching the mockup 1:1.
- `injectLikePrevControlsS88()` kept as a no-op for backward compat.
- New helpers: `renderLikePrevPageS88`, `kpClose`, `kpFieldDef`, `kpFieldHtml`, `kpEditField`, `kpEditCommit`, `kpToggleCopied`, `kpSwatchClass`, `kpVariantSectionHtml`, `kpSingleSectionHtml`, `kpSingleAdj`, `kpCopyQtyToggle`, `kpAdj`, `kpRemoveColor`, `kpAddColor`, `kpPhotoPick`, `kpVoiceName`, `kpCollectIntoWizData`, `kpSave`, `kpSaveThenAIStudio`, `kpPrintNow`.
- On save → mirrors the new layout state into `S.wizData` (axes/_matrix/photoDataUrl/_likePrevSource), then calls existing `wizSave()` which posts to `product-save.php`. After success, modal auto-closes.

---

## Decisions taken (per Тихол's answers)

1. **UI strategy:** A — new custom render bypassing `renderWizard()` (default after no answer; matches the stand-alone mockup 1:1).
2. **Auto-increment код:** B — trailing-number increment of source's code (already implemented in PHP, kept).
3. **Variant vs Single:** B — auto from source. If source has children rows in `products` table → variant mode with colors/sizes pre-populated. Otherwise → single.
4. **Copied fields:** 8 (mockup authority). Цена дребно · Доставна · Печалба (авто) · Доставчик · Категория · Подкатегория · Материя · Произход. Margin computes live as `(retail - cost) / retail × 100`.
5. **Bottom bar:** Печат → `wizPrintLabels(-1)` after seeding `_printCombos`; AI Studio → `kpSave()` then `openStudioModal(savedId)`. Save is **only** in the rms-header (no duplicate in bottom bar).
6. **Photo:** Auto-copies from source via `fetch → readAsDataURL`. Shown as preview inside `.kp-photo-hero.has-photo` with `.kp-photo-overlay` "Tap за смяна на снимка".
7. **Voice mic:** Inline `webkitSpeechRecognition`/`SpeechRecognition` for the name field (the wizard's `voiceForStep` is wizStep-bound and not reusable here). Button auto-disabled when SR unavailable.
8. **CSS placement:** Inside the existing `<style>` block in `products.php` (no new tag, no `shell.css` change).

---

## 3 handlers — where they live

| Handler | Location | How |
|---|---|---|
| **Auto-increment код** | PHP `last_product` (preserved) → JS hidden `#wCode` | `next_code` from existing trailing-number logic (option B) |
| **Празен barcode** | JS `renderLikePrevPageS88` | `<input type="hidden" id="wBarcode" value="">` |
| **qty=0 default + "Копирай и количество" чекбокс** | JS `kpSingleSectionHtml` + `kpCopyQtyToggle` | Hidden `#wSingleQty` starts at 0; checkbox shown only when source had qty > 0; flips between 0 ↔ srcQty |
| **Photo auto-copy** (bonus, brief req) | JS `kpCollectIntoWizData` | If source had `image_url` and user didn't pick a new photo, fetch + readAsDataURL → `S.wizData._photoDataUrl` so wizSave uploads it |

---

## DESIGN_LAW compliance checklist

- [x] §2 palette respected — all colors from indigo/violet/green/red scale, no custom hue
- [x] §2.6 product visuals — swatch is the only place real product color leaks; cards stay structural (`q-default` indigo)
- [x] §7 glass — 4-span pattern (shine + shine-bottom + glow + glow-bottom) on every `.glass` card
- [x] §8.2 Save button — green neon, 145 hue, with check-icon SVG
- [x] §8.6 Voice mic — 32×32 round, hue-tinted gradient, 14×14 white SVG
- [x] §8.8 Stepper buttons — 30×30, 8px radius, hue tint
- [x] §10.1 Inputs — 14px padding, 12px radius, 0.4-alpha indigo border
- [x] §16.1 — 0 emoji in **new** kp-* code (verified `grep -E "📋|💡|✨|🔴|🟢|📷|🎤"` returns no hits inside the S88.KP block). 17 pre-existing emoji elsewhere in `products.php` are NOT touched (out of scope per "запази всички DOM IDs + JS handlers, които не fix-ваш изрично").
- [x] §16.1 no localStorage/sessionStorage in new code
- [x] §16.2 tabular-nums on every numeric value (qty, price, margin, color totals)
- [x] §16.2 var(--ease) on every transition

---

## How to test (acceptance for Пешо)

1. Open `products.php` → tap the "..." button next to "Добави нов" (entry point line 3969 unchanged).
2. Bottom sheet → "Като предния" → new full-screen overlay opens.
3. Verify visually:
   - Header sticky with Назад / "Като предния" + sub (source name · supplier · category) / зелен ЗАПАЗИ
   - Photo hero shows source's image (preview + "Tap за смяна" overlay) OR empty dashed if no image
   - Name input pre-filled with `<source name> (копие)`
   - Copied card (зелен q-gain glass) collapsed by default; tap to expand 8 fields; tap pencil to inline-edit
   - If source has variants → variant section with one color card per source-color, sizes pre-populated, all qty=0
   - If source is single → single section with Брой stepper (qty=0) + Минимум stepper + "Копирай и количество (N бр.)" чекбокс when source had stock
   - Bottom bar: Печат (q-default indigo) + AI Studio (q-magic violet)
4. Edit any field via pencil — it inline-replaces value with input; Enter or blur commits, Esc cancels
5. Toggle "Копирай и количество" → Брой stepper view flips to source qty (or back to 0)
6. Tap ЗАПАЗИ → success toast, modal closes, product list refreshes (via existing wizSave path)
7. Tap AI Studio → saves first, then opens AI Studio modal for the new product
8. Tap ПЕЧАТ → prints labels for the qty entered (variant rows with qty>0, or single qty)

---

## Known scope-bounded omissions

- No "Add size" button inside color cards (mockup has it but adding new sizes mid-card needs a deeper UX decision; fall back to existing axis-edit flow if needed)
- "Добави цвят" uses `prompt()` for color name (matches existing variant flow style — no in-page voice picker)
- AI Studio invocation falls back to `openStudioModal` if `openAIStudio` is undefined (the latter is referenced in legacy code but not defined)
- Variant qty-from-source per (color, size) cell is **not** copied (req #3 says qty=0 default; per-variant copy would need a per-cell inventory fetch — out of scope for v1)

---

## Files changed in this commit

- `products.php` — PHP `last_product` AJAX extension + new CSS block + JS rewrite of "Като предния" flow

## Files NOT touched

- `product-save.php` — no schema/payload change required (existing wizSave payload covers all fields)
- `compute-insights.php`, `sale.php`, `chat.php`, `partials/*`, `css/shell.css`, `css/theme.css` — out of scope

## Backup
`products.php.bak.s88kp_0527` — pre-change snapshot.
