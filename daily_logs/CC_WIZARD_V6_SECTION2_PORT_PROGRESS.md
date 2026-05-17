# 📋 PORT PROGRESS — Section 2 "Вариации"

**Дата:** 2026-05-17
**Status:** WORK IN PROGRESS — самоконтролен документ за CC

═══════════════════════════════════════════════════════════════

## 🎯 ЦЕЛ

P13_bulk_entry.html visual design + ВСЯКА sacred логика от products.php 1:1.
След всеки чекпойнт — преглеждам тази таблица и допълвам кое не е още портирано.

═══════════════════════════════════════════════════════════════

## 🗺 P13 MOCKUP VISUAL ELEMENTS

| # | P13 елемент | Line | Ported? | Notes |
|---|---|---|---|---|
| 1 | acc-section.open Section 2 header | 573-578 | ✅ | wizard-v6.php Section 2 wrapper |
| 2 | field-label "Размери" с req | 583 | ✅ | field-label markup |
| 3 | chips-row XS/S/M/L/XL/XXL | 585-592 | ⚠ Частично | Chips render-нати, но НЕ pre-loaded в initial state. User не вижда chips ако не са добавени. **TODO**: pre-load default size preset като chips, active state според axes[i].values |
| 4 | chip-add "+ добави размер" | 593 | ✅ | Има .chip-add бутон |
| 5 | groups-btn "други групи →" | 594 | ✅ | Има .groups-btn |
| 6 | field-label "Цветове" | 597 | ❌ | НЕ е "Цветове" множествено — рендера показва "ЦВЯТ" според axis.name. **TODO**: показва "ЦВЕТОВЕ" в label дори ако axes[].name='Цвят' |
| 7 | chip-col с color-dot за всеки цвят | 599-603 | ⚠ Частично | Рендера се само за active values. **TODO**: show ALL palette colors като chips (active/inactive) |
| 8 | chip-add "+ добави" за color | 604 | ✅ | Има |
| 9 | matrix-head-row "Брой по комбинация" | 607-611 | ❌ | Phase 3c.3 |
| 10 | mx-grid + mx-corner + mx-head + cells | 613-628 | ❌ | Phase 3c.3 |
| 11 | sku-summary | 630-633 | ✅ | wizSKUCount + renderSKUSummary |
| 12 | save-row Запази + Print + CSV | 635-639 | ❌ Не за Section 2 | Section 1 има save-row |

### ОТКРИТИ ДОПЪЛНИТЕЛНИ изисквания от Тих 2026-05-17:

| # | Изискване | Status | План |
|---|---|---|---|
| A | Pre-loaded размер chips (XS-XXL по default) | ❌ | Авто-показва от първа size preset група |
| B | Pre-loaded цвят chips | ❌ | Показва WIZ_BASE_COLORS като chips |
| C | Търсачка за размер — search across ALL groups | ❌ | Input "Търси размер..." до chip-add |
| D | Търсачка за цвят | ❌ | Input "Търси цвят..." до chip-add |
| E | Търсачка вътре в "други групи" expand | ❌ | Input "Търси група..." в inline-panel |
| F | "+ добави" за color → отваря HEX picker | ✅ | Native `<input type="color">` + name |
| G | Преимуване "Добави още една ос" → "Добави **друга вариация**" | ❌ | Rename label |
| H | "axes[i].values" labeling като "разновидности" | ⚠ | UI текст е "разновидности на основната вариация" |

═══════════════════════════════════════════════════════════════

## 🧩 SACRED FUNCTIONS PORT STATUS

### Renderers

| Sacred function | Line | Ported? | Where | Notes |
|---|---|---|---|---|
| `renderWizPagePart2(step=4)` | 8321 | ⚠ Частично | `renderWizSection2()` wizard-v6 | UI different но логика-карс е същата |
| `renderWizPagePart2(step=5)` matrix | TBD | ❌ Phase 3c.3 | — | Matrix renderer |

### Init + AI autofill

| Sacred function | Line | Ported? | Where |
|---|---|---|---|
| Init axes (axes empty fallback) | 8329-8335 | ✅ | `wizInitVariantsAxes()` |
| Auto-rename "Вариация N" → Размер/Цвят | 8336-8350 | ✅ | `wizInitVariantsAxes()` part |
| AI color autofill от _photos[] | 8352-8386 | ✅ | `wizAIColorAutofill()` |

### Axis management

| Sacred function | Line | Ported? | Notes |
|---|---|---|---|
| `wizAddAxis()` | 11500 | ⚠ | Заменено с `wizAxisAddNew()` (prompt) — TODO 1:1 sacred |
| `wizAddAxisFromTab()` | 10877 | ❌ | Sacred има tab UI — wizard-v6 inline → не нужно |
| `wizAddAxisValue(axIdx)` | 11553 | ⚠ | Има `wizAxisAddInlineConfirm` — нужна 1:1 verification |
| `wizAxisSuggest(axIdx, q)` | 11509 | ❌ | TODO — за autocomplete |
| `wizPickAxisVal(axIdx, val)` | 11546 | ⚠ | Има `wizAxisToggle` — similar но не identical |

### Color picker / HEX

| Sacred function | Line | Ported? |
|---|---|---|
| `wizColorAddPrompt()` | 10483 | ❌ Inline native picker (Q2=A+B) — TODO нужен и слайдер drawer (Q2=B) |
| `wizColorEditPrompt(name, hex)` | 10501 | ❌ TODO — edit съществуващ chip color |
| `wizColorSelectAll()` | 10532, 10627 (dup!) | ❌ TODO — bulk select всички |
| `wizAddHexColor()` | 11218 | ❌ TODO — full hex input + save |
| `wizNameToHex(name)` | 11162 | ⚠ Replaced from `wizHexForName(name)` — TODO 1:1 check |
| `_wizSuggestColorName(r,g,b)` | 11135 | ❌ TODO RGB→suggested name |
| `_wizUpdatePickerFromHex(hex)` | 11203 | ❌ TODO |
| `_wizSaveCustomColors()` | 11256 | ❌ TODO localStorage save |
| `_wizLoadCustomColors()` | 11263 | ❌ TODO localStorage load |
| `wizPhotoSetColor(idx, val)` | 9650 | ✅ Section 1 (multi-photo) |

### Preset groups

| Sacred function | Line | Ported? | Notes |
|---|---|---|---|
| `_SIZE_GROUPS[]` (27 групи) | 10369-10395 | ✅ | `js/wizard-variations.js` |
| `_getSizePresetsOrdered()` | 10408 | ✅ | `js/wizard-variations.js` |
| `wizTogglePresetInline(axIdx, val, chip)` | 11278 | ⚠ | Имам `wizAxisToggle` similar but може да не 1:1 |
| Pinned groups (localStorage) | 8390-8396 | ❌ TODO — pin/unpin групи |

### Matrix

| Sacred function | Line | Ported? | Notes |
|---|---|---|---|
| `_wizInitMatrix()` | 10952 | ❌ Phase 3c.3 |
| `wizMatrixChanged(cellId, val)` | 10956 | ❌ Phase 3c.3 |
| `wizMatrixFillAll(qty)` | 10962 | ❌ Phase 3c.3 |
| `wizMatrixClear()` | 10982 | ❌ Phase 3c.3 |
| `wizComboQty(idx, delta)` | 11574 | ❌ Phase 3c.3 |
| `_wizApplyMatrixItems(items, sizeAx, colorAx)` | 11054 | ❌ Phase 3c.5 (voice → matrix) |
| `mxFillAll(qty)`, `mxX2()`, `mxHalf()`, `mxClear()`, `mxImportLast()` (P12 toolbar) | TBD P12_matrix.html | ❌ Phase 3c.3 |

### Voice

| Sacred function | Line | Ported? | Notes |
|---|---|---|---|
| `wizMicAxis(axIdx)` | 14995 | ⚠ | Stub имплементация (split parse). TODO 1:1 |
| `wizMicNewAxis()` | 15031 | ❌ TODO |
| `wizVoiceMatrix()` | 10992 | ❌ Phase 3c.3 |
| `_voiceProcessAxis(text, axisName, existing)` | 14793 | ❌ TODO — i18n per language |

### Step navigation

| Sacred function | Ported? | Notes |
|---|---|---|
| `wizSafeBack()` | ❌ Не нужно за one-page wizard-v6 |
| `wizGo(step)` | ❌ Не нужно |
| `wizSave()` | ❌ Phase 4 |
| `wizPrintLabels(comboIdx)` | ❌ Phase 4 |

═══════════════════════════════════════════════════════════════

## 🚨 КРИТИЧНИ TODO ЗА СЛЕДВАЩИЯ COMMIT (3c.2c)

1. **Pre-load chips** за двата axis-а:
   - Размер: показва default preset (Дрехи букви) chips като inactive
   - Цвят: показва WIZ_BASE_COLORS chips като inactive
   - Active state = в axes[i].values
   - Tap toggles
2. **Search инпут** за всеки axis (до chip-add):
   - Размер search → live search across ALL 27 _SIZE_GROUPS
   - Цвят search → live filter на WIZ_BASE_COLORS
3. **Search в "други групи"** inline panel
4. **Rename** "Добави още една ос" → "Добави друга **вариация**"
5. **Label** "Стойности" / "Values" → "**Разновидности**" в UI

═══════════════════════════════════════════════════════════════

## 🛤 NEXT STEPS

- ✅ **3c.2d** (done): P13 static skeleton 1:1 verbatim
- ✅ **3c.3** (now): Size axis dynamic — chip toggle + search + manual add + groups expand
- ⏭ **3c.4**: Color axis dynamic — chip toggle + HEX picker + AI photo autofill activates
- ⏭ **3c.5**: Matrix dynamic — qty cells wire + recalc SKU + "Всички = N" + import
- ⏭ **3c.6**: P12_matrix.html fullscreen modal
- ⏭ **3c.7**: Voice per axis (_voiceProcessAxis i18n)
- ⏭ **3c.8**: Pinned groups + custom colors localStorage
- ⏭ **3d**: Manual test scenarios + refinement

═══════════════════════════════════════════════════════════════

## ✅ 3c.3 — SIZE AXIS DYNAMIC (ported sacred logic 1:1)

### Sacred functions ported:

| Sacred | Source line | Where в port | Status |
|---|---|---|---|
| `_levenshtein(a,b)` | 12787 | `js/wizard-variations.js wizLevenshtein` | ✅ 1:1 |
| `fuzzyMatch80(input,cands)` | 12808 | `wizFuzzyMatch80` | ✅ 1:1 |
| `fuzzyConfirmAdd(label,input,...)` | 12830 | `wizFuzzyConfirmAdd` | ✅ 1:1 |
| `wizTogglePresetInline(axIdx,val,chip)` | 11278 | `wizSizeToggle(val)` (re-render based) | ✅ funcequivalent |
| `wizPickAxisVal(axIdx,val)` | 11546 | `wizSizeAddPickSuggestion(val)` | ✅ 1:1 |
| `wizAddAxisValue(axIdx)` | 11553 | `wizSizeAddConfirm()` (fuzzy 1:1) | ✅ 1:1 |
| `wizAxisSuggest(axIdx,q)` search | 11509 | `wizSizeAddInputChange(q)` + `wizSizeSearchAll(q)` | ✅ 1:1 logic |
| `_SIZE_GROUPS` (27 presets) | 10369-10395 | `js/wizard-variations.js` (3c.1) | ✅ 1:1 |

### NEW size axis UI flows:

1. **Default chips XS-XXL** pre-loaded → tap toggles active state (sacred wizTogglePresetInline pattern)
2. **"+ добави размер"** → inline expand panel с:
   - Search input (live suggest от ALL 27 _SIZE_GROUPS чрез `wizSizeSearchAll`)
   - Tap suggestion → add без fuzzy check
   - Tap "Добави" → manual add с **fuzzy 80% match** (1:1 sacred fuzzyConfirmAdd)
3. **"други групи →"** → inline expand panel с:
   - Search input filter by group.label или group.values
   - Click group → bulk add ALL values от групата (dedup)
   - vibrate + close panel + re-render

### Какво НЕ е портнато (TODO следваща sub-step):

- Voice mic за axis — wired stub-овано в текущ Section 2 markup, но НЕ функционално. _voiceProcessAxis (i18n) трябва порт за full voice flow.
- AI color autofill — backend logic работи (wizAIColorAutofill silent), но Цветове axis e STATIC P13 markup, не показва AI цветовете. Ще активира с 3c.4.
- Pinned groups (S._wizPinnedGroups + localStorage `_rms_pinnedGroups_<storeId>`) — sacred 8390-8396 не портнат.
- _v4UpdateAfterToggle inner DOM patching (вместо full re-render) — за performance.

═══════════════════════════════════════════════════════════════

## SECTION 2 — статус след 3c.3

| Sub-section | Visual | Functional | Notes |
|---|---|---|---|
| Размери (axis) | ✅ P13 | ✅ Wired (chip toggle, add inline, groups expand, search) | Sacred logic 1:1 |
| Цветове (axis) | ✅ P13 (static) | ❌ Не wired | 3c.4 next |
| Matrix grid | ✅ P13 (static) | ❌ Не wired | 3c.5 next |
| SKU summary | ✅ P13 (static) | ❌ Не recalculates | 3c.5 |
| Save row | ✅ P13 (static) | ❌ Stubs | Phase 4 |
