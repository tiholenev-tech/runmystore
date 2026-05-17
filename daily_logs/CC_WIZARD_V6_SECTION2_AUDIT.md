# 📋 AUDIT — Section 2 "Вариации" sacred logic

**Дата:** 2026-05-17
**Branch:** `s148-cc-phase2-frontend`
**Source:** `products.php` (sacred — read-only)
**Target:** `wizard-v6.php` + `js/wizard-variations.js` (port)
**Design canon:** `mockups/P13_bulk_entry.html` (ред 662-740 Section 2)

═══════════════════════════════════════════════════════════════

## 🗂 1. ДАННИ И СЪСТОЯНИЕ

### S.wizData (продукт)

| Поле | Тип | Sacred line | Описание |
|---|---|---|---|
| `axes[]` | Array of {name, values[]} | 8329 | Списък оси за вариация |
| `axes[i].name` | String | 8332, 8344, 8349, 8374 | "Размер" / "Цвят" / "Вариация N" |
| `axes[i].values[]` | Array<string> | 8333, 8380 | Стойностите на оста (S/M/L; Бял/Червен) |
| `_aiColorsApplied` | Boolean flag | 8365, 8384 | AI цветове вече merge-нати (once) |
| `_aiDetectedColors[]` | Legacy AI colors | 8353-8355 | Старо AI поле (deprecated, ползва _photos) |
| `_photos[].ai_color` | String | 8358-8364 | AI разпознат цвят на снимка (от Section 1) |
| `_photos[].ai_hex` | String hex | 8362 | AI hex код на снимка |
| `combos[]` | Array of cell objects | 7929-7931 | Matrix комбинации (size × color × ... qty + min) |
| `skip_axis` | String/null | TBD | Ос която потребителят пропусна |

### S (UI state)

| Поле | Sacred line | Описание |
|---|---|---|
| `_wizActiveTab` | 8387, 8418 | Index на текущо активна ос (tab) |
| `_wizPinnedGroups` | 8390-8396 | User-pinned size groups (localStorage `_rms_pinnedGroups_<storeId>`) |
| `_wizEditingGroup` | 8397 | Текущо редактирана група (preset edit) |
| `wizStep` | global | 4 = axes definition, 5 = matrix |
| `wizVoiceMode` | 8322 | Voice mode flag (show skip button) |

### Конфигурация

- `CFG.colors[]` = `{name, hex}` global color palette
- `_bizVariants.variant_fields[]` = бизнес-специфични default axis names
- `_getSizePresetsOrdered()` (line 10408) = default size groups (XS-XXL, 32-46, и т.н.)

═══════════════════════════════════════════════════════════════

## 🧩 2. SACRED FUNCTIONS — пълен inventory

### 2.1 Master renderers

| Function | Line | Какво прави | Зависимости |
|---|---|---|---|
| `renderWizPagePart2(step)` | 8321 | Master Section 2 renderer (step=4 axes, step=5 matrix) | целия модул |
| `renderWizStep2()` | 9228 | "По желание" detailed step (различно нещо) | retail/cost/markup |

### 2.2 Axis management (oсите)

| Function | Line | Какво прави |
|---|---|---|
| `wizAddAxis()` | 11500 | Добавя нова ос (за бутон "+") |
| `wizAddAxisFromTab()` | 10877 | Добавя ос от tab-bar "+ Add tab" |
| `wizAddAxisValue(axIdx)` | 11553 | Добавя стойност в ос (text input) |
| `wizAxisSuggest(axIdx, q)` | 11509 | Suggest autocomplete |
| `wizPickAxisVal(axIdx, val)` | 11546 | Избира предложена стойност |

### 2.3 Color picker / HEX (специфично за цвят-оси)

| Function | Line | Какво прави |
|---|---|---|
| `wizColorAddPrompt()` | 10483 | Diaog "добави цвят" с име + hex |
| `wizColorEditPrompt(oldName, oldHex)` | 10501 | Edit съществуващ цвят |
| `wizColorSelectAll()` | 10532, 10627 (duplicate!) | Bulk select all colors |
| `wizAddHexColor()` | 11218 | Добавя HEX → custom palette |
| `wizNameToHex(name)` | 11162 | "Червен" → "#dc2626" mapping |
| `_wizSuggestColorName(r,g,b)` | 11135 | RGB → suggested име |
| `_wizUpdatePickerFromHex(hex)` | 11203 | Update picker UI от hex value |
| `_wizSaveCustomColors()` | 11256 | localStorage save custom palette |
| `_wizLoadCustomColors()` | 11263 | localStorage load |
| `wizPhotoSetColor(idx, val)` | 9650 | Set color на снимка (Section 1, reused тук) |

### 2.4 Preset groups (размер групи / цвят палитри)

| Function | Line | Какво прави |
|---|---|---|
| `_getSizePresetsOrdered()` | 10408 | Default size group definitions (XS-XXL, 32-46, ...) |
| `wizTogglePresetInline(axIdx, val, chip)` | 11278 | Toggle единичен chip в активна група |
| `wizSavePinnedGroups()` | TBD | Save pinned groups в localStorage |
| `wizEditGroup(gid)` | TBD | Enter edit mode за group |

### 2.5 Matrix builder & qty per combo

| Function | Line | Какво прави |
|---|---|---|
| `_wizInitMatrix()` | 10952 | Init празна matrix array (S.wizData.combos) |
| `wizMatrixChanged(cellId, val)` | 10956 | Set qty на cell, recalc min |
| `wizMatrixFillAll(qty)` | 10962 | Bulk fill всички cells със стойност |
| `wizMatrixClear()` | 10982 | Clear all qty |
| `_wizApplyMatrixItems(items, sizeAxis, colorAxis)` | 11054 | Apply parsed voice items → matrix |
| `wizComboQty(idx, delta)` | 11574 | +/- stepper на единичен combo |

### 2.6 Voice input (axis values + matrix)

| Function | Line | Какво прави |
|---|---|---|
| `wizMicAxis(axIdx)` | 14995 | Mic за конкретна ос |
| `wizMicNewAxis()` | 15031 | Mic за нова ос (name + values) |
| `wizVoiceMatrix()` | 10992 | Voice → пълни matrix наведнъж ("Бял S 2 M 3 L 1") |
| `_voiceProcessAxis(text, axisName, existing)` | 14793 | i18n parse voice → values per language (bg/en/ro/...) |
| `wizSearchPresets(axIdx, q)` | 10906 | Search в presets с query |

### 2.7 Step navigation

| Function | Line | Какво прави |
|---|---|---|
| `wizSafeBack()` | global | Universal "Назад" (мине през history) |
| `wizGo(step)` | global | Forward to step N |
| `wizGoPreview()` | global | Go to preview/save step |
| `wizSave()` | global | Save всичко в DB |
| `wizPrintLabels(comboIdx)` | 13932 | Print SKU label |

═══════════════════════════════════════════════════════════════

## 🤖 3. AI COLOR AUTOFILL (Section 1 → Section 2)

**Sacred sequence (line 8352-8386):**

1. **Source 1 (deprecated):** `S.wizData._aiDetectedColors[]` — старо AI поле
2. **Source 2 (current):** `S.wizData._photos[i].ai_color` + `ai_hex` — от AI color detect endpoint в Section 1 (вече имплементирано в 2e++d).
3. **Algorithm:**
   - Build `_detectedColors[]` чрез deduplication по lowercase name
   - Find color axis: search axes за name съдържащ "цвят" / "color"
   - Ако няма → rename първи "Вариация N" → "Цвят" (auto-rename)
   - Push detected colors в `axes[colorIdx].values[]` (dedup чрез Set)
   - Set `_aiColorsApplied = true` (once flag — НЕ работи отново при следващи renderи)

**Resultat:** Когато потребител превключи от Single → Variant в Section 1 (или влиза в Section 2), Section 2 автоматично попълва "Цвят" оста с разпознатите от снимките цветове.

═══════════════════════════════════════════════════════════════

## 🎨 4. P13 DESIGN CANON — какво виждаме в mockup-а

### Структура (P13 ред 662-740)

```
<section class="acc-section" data-status="active" id="varSection">
  <div class="acc-head">...Вариации</div>
  <div class="acc-body">

    <!-- AXIS 1: Размери (chips) -->
    <div class="field">
      <div class="field-label">Размери</div>
      <div class="chips-row">
        <button class="chip-sz">XS</button>
        <button class="chip-sz active">S</button>  ← selected
        <button class="chip-sz active">M</button>
        ...
      </div>
      <div class="extra-row">
        <button class="chip-add">+ добави размер</button>
        <button class="groups-btn">други групи →</button>  ← опен sheet/drawer
      </div>
    </div>

    <!-- AXIS 2: Цветове (chips с hex dot) -->
    <div class="field">
      <div class="field-label">Цветове</div>
      <div class="chips-row">
        <button class="chip-col">
          <span class="chip-col-dot" style="background:#dc2626"></span>Червен
        </button>
        <button class="chip-col active">...Бял</button>
        <button class="chip-add">+ добави</button>
      </div>
    </div>

    <!-- MATRIX header -->
    <div class="matrix-head-row">
      <span class="matrix-head-label">Брой по комбинация · мин.</span>
      <button class="matrix-action" onclick="mxFillAll(2)">+ Всички = 2</button>
      <button class="matrix-action expand">⛶ Цял екран</button>
    </div>

    <!-- MATRIX grid (size × color) -->
    <div class="mx-grid">
      <div class="mx-corner">РАЗ.×ЦВ.</div>
      <div class="mx-head">[dot]Бял</div>
      <div class="mx-head">[dot]Розов</div>

      <div class="mx-rowh">S</div>
      <div class="mx-cell has-qty">
        <input class="mx-input-qty" value="2">
        <div class="mx-min-row"><span>мин</span><input class="mx-input-min" value="1"></div>
      </div>
      ...
    </div>

    <!-- SKU summary -->
    <div class="sku-summary">
      <span class="sku-ic">✓</span>
      <div class="sku-text">3 размера × 2 цвята = <b>6 SKU</b> · Σ <b>14 бр.</b></div>
    </div>

    <!-- Save row -->
    <div class="save-row">
      <button class="save-section-btn">✓ Запази</button>
      <button class="save-aux-btn">🖨</button>
      <button class="save-aux-btn">⬇ CSV</button>
    </div>
  </div>
</section>
```

### P13 ХАРАКТЕРИСТИКИ

- **2 fields** в P13 — Размери + Цветове (фиксиран ред)
- Sacred има **N axes** (повече от 2 възможни — материя/състав/...)
- P13 chips винаги inline, без tabs
- P13 matrix е inline в section body (не fullscreen — има бутон за P12 fullscreen)
- P13 има inline "+ добави" на края на chips-row
- P13 има "други групи →" бутон отваря preset sheet

### КАКВО P13 ЛИПСВА (sacred има, P13 няма)

| Sacred feature | Дали е в P13 | План |
|---|---|---|
| HEX picker UI | НЕ | Inline `<input type="color">` или sheet/drawer |
| Color name autocomplete | НЕ | Inline suggest в "+ добави" prompt |
| Multiple groups palette | НЕ напълно (има "други групи" sheet) | Sheet със бутони "Размери ман/жен/деца/обувки" |
| Voice per axis | НЕ | Добавя .fbtn.voice в .extra-row |
| AI color auto-pop | НЕ visualized | Логиката работи автоматично, без UI промяна |
| Axis с >2 (материя/...) | НЕ | Допусни до 2 в Section 2 — sacred позволява но P13 ограничава за UX |
| Tab navigation | НЕ | Replace с inline fields (per P13) |

═══════════════════════════════════════════════════════════════

## 🎯 5. PORT PLAN — задача по задача

### 3c.1 — Skeleton + AI color autofill (1h)
- Create `js/wizard-variations.js` (analogous to wizard-parser.js, wizard-photo.js)
- Copy 1:1 sacred:
  - `_getSizePresetsOrdered`
  - AI color autofill block (line 8352-8386)
  - Axis init (line 8329-8350)
- Add `var WIZ_DEFAULT_COLORS = CFG.colors` shim
- Test: variant mode → photo upload → AI detect → Section 2 auto color

### 3c.2 — Render Section 2 (1.5h)
- `renderWizSection2()` в wizard-v6.php
- Render 2 fields (Sizes + Colors) с P13 chips-row + chip-sz / chip-col
- Inline "+ добави" → prompt → axes[i].values.push (or sacred wizColorAddPrompt)
- "други групи →" бутон → groups sheet (sacred presets)
- Voice .fbtn.voice → wizMicAxis(idx)
- Toggle chip → axes[i].values add/remove

### 3c.3 — Matrix (1h)
- `renderVariationsMatrix()` — P13 .mx-grid layout
- Cells with qty + min inputs
- "Всички = N" bulk fill бутон → wizMatrixFillAll
- "Цял екран" → toast "P12 fullscreen — Phase 4" (или открит)
- SKU summary calculation

### 3c.4 — HEX picker + color UI (45min)
- `<input type="color">` inline за "+ добави" на цвят
- Save в CFG.colors + custom palette (localStorage)
- Reuse sacred wizNameToHex + _wizSuggestColorName

### 3c.5 — Voice + groups sheet (45min)
- Sheet drawer (similar to wzQf drawer pattern):
  - Размери: XS-XXL / 32-46 / W34-W40 / Детски ...
  - Цветове: 4 класически / Пастел / Земни тонове / ...
- Tap group → bulk add values

### 3c.6 — Sacred behavior preservation tests (30min)
- Section 1 photos с AI color → Section 2 цветове auto-populated
- Manual color add → matrix recalc
- Size change → matrix recalc
- Back nav: matrix → size axis → color axis
- Voice: "Бял S 2 M 3 L 1" → matrix auto-fill

═══════════════════════════════════════════════════════════════

## ❓ 6. ВЪПРОСИ ЗА ТИХ (нужни преди порт)

### Q1: AXIS LIMIT

P13 показва само 2 axes (Размер + Цвят). Sacred позволява N axes (също Материя, Състав, ...). Какво искаш?
- **A)** Само 2 в Section 2 — UX simple
- **B)** 2 + "+ Добави ос" бутон за допълнителни — sacred 1:1
- **C)** Друго

### Q2: HEX PICKER UI

Sacred има wizColorAddPrompt с inline hex input. P13 няма UI. Какво?
- **A)** `<input type="color">` нативен picker (минимална UI, brave)
- **B)** Sheet с RGB sliders + hex hex input + recent colors
- **C)** Без hex — само CFG.colors palette

### Q3: GROUPS SHEET (P13 "други групи →")

Каква да е презентацията на sheet?
- **A)** Bottom-sheet (similar to drawer wzQf) с list groups → tap → bulk add
- **B)** Inline expand под "+ добави" — без drawer
- **C)** Без sheet — само static chips в chips-row

### Q4: МАТРИЦА fullscreen

P13 има бутон "Цял екран" → отваря P12 mockup. Какво?
- **A)** Sheet drawer с матрицата голяма (no separate page)
- **B)** Modal full-screen (на цял viewport)
- **C)** Stub toast "Phase 4" — отложи

### Q5: Sacred renderWizStep2 (line 9228) — "По желение"

Sacred има отделна "Step 2" страница с retail/cost/markup/zone. В моя wizard това е Section 3 (Допълнителни) — Phase 4 territory. Какво?
- **A)** Игнорирам — wizSave-ът в Section 1 ще е enough
- **B)** Section 3 ще има тези полета — Phase 4

═══════════════════════════════════════════════════════════════

## ✋ STOP — чакам отговори Q1-Q5

При **OK на плана + отговори Q1-Q5** → стартирам **3c.1 (Skeleton + AI autofill)**.

При неяснота → STOP, още QUESTIONS.

Команди за продължаване:
```bash
cd /home/tihol/runmystore
git checkout s148-cc-phase2-frontend
# 3c.1: създаваме js/wizard-variations.js + AI autofill
```
