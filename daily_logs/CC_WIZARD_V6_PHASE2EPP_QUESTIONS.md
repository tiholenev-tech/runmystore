# ❓ CC WIZARD v6 — QUESTIONS за ФАЗА 2e++ (multi-photo + AI inline + color detect)

**Дата:** 2026-05-17
**Branch:** `s148-cc-phase2-frontend`
**HEAD:** `31f9c05` (след 2f)
**Запитващ:** Claude (Opus 4.7, 1M context)
**Чакам решение преди да пиша първи line за 2e++a.**

═══════════════════════════════════════════════════════════════

## ⚠ ВЪПРОС #1 (БЛОКЕР) — Как да направя photo-mode toggle видим?

### Контекст

Sacred logic (products.php 12395-12404):

```js
if(S.wizType!=='variant')_photoMode='single';   // 12395 — заковава single ако не е variant
...
var _photoModeToggle='';
if(S.wizType==='variant'){                       // 12398 — toggle само в variant flow
    _photoModeToggle = '<div class="photo-mode-toggle">...';
}
```

В wizard-v6.php (твой explicit init от 2e prompt):
```js
var S = { wizData: {}, wizStep: 0, wizType: null, wizBulkMode: false };
                                  └─ wizType: null
```

→ `wizSetPhotoMode('multi')` се извиква, но при следващ render `if(S.wizType!=='variant')_photoMode='single'` го връща в single. **Multi mode е unreachable без промяна.**

### Опции

| # | Опция | Промяна | Sacred fidelity |
|---|---|---|---|
| **A** | Set S init `wizType: 'variant'` като default | 1 line промяна в моя S declaration (НЕ в sacred) | ✅ Sacred 1:1 (само init value) |
| **B** | Премахни guard `if(S.wizType!=='variant')_photoMode='single';` от моя `renderWizSection1Photo()` | 1 line изтриване в моя 1:1 copy на photoBlock construction | ⚠ deviation от sacred логика |
| **C** | Добави свой type toggle (Единичен / С вариации) — UI само за wizType state, без axes/sizes machinery | ~15 lines нов JS + малко CSS | ✅ Sacred 1:1 + sacred-flavored scaffold |
| **D** | Add new always-visible photo-mode toggle (NOT coupled to wizType) | Нов CSS + нова логика | ❌ Big deviation |

### Моя препоръка: **C**

**Защо C над A:**
- A крие default UX: потребителят започва с multi-mode видимо без да е поискал variant — конфузно UX, особено за единичен артикул (типичен случай).
- C дава UX явно: tap "Единичен" / "С вариации" → wizType се set-ва → multi-photo toggle се появява само ако е "С вариации". **Това е exactly sacred поведение** (products.php 12468-12488 type toggle).
- C scaffold-ва Phase 3 (вариации) UI **БЕЗ да добавя axes/sizes/colors data structure**. Phase 3 STOP остава intact (renderWizPagePart2 не се пипа). Само wizType state се set-ва от toggle-а.

**Защо НЕ B:** модифицира sacred photoBlock логиката → нарушава "1:1 sacred".
**Защо НЕ A:** прескача type-choice mandatory state от sacred (12468-12488 показва "▼ Избери тип артикул" hint и lock-ва полетата под него докато не е избран).

### Конкретен план при опция C

```js
// Нов render fn renderWizSection1Type() — БЕЗ axes/sizes data, само wizType state.
// 1:1 от products.php 12468-12488 (typeChosen + s95-type-btn рендериране).
function renderWizSection1Type(){
  var typeChosen=(S.wizType==='single'||S.wizType==='variant');
  var sActive=(S.wizType==='single');
  var vActive=(S.wizType==='variant');
  var typeBtnSingle='<button ... onclick="wizSwitchType(\'single\')" class="s95-type-btn'+(sActive?' active':'')+'">...📦 Единичен</button>';
  var typeBtnVariant='<button ... onclick="wizSwitchType(\'variant\')" class="s95-type-btn variant'+(vActive?' active':'')+'">...📊 С Вариации</button>';
  var typeHint=typeChosen ? '' : '<div style="...font-weight:600">▼ Избери тип артикул</div>';
  return '<div style="margin-bottom:10px">'+typeHint+'<div style="display:flex;gap:8px;...">'+typeBtnSingle+typeBtnVariant+'</div></div>';
}

function wizSwitchType(type){
  S.wizType=type;
  renderWizard();
}
```

`.s95-type-btn` CSS — 1:1 от products.php (ще го намеря и копирам).

renderWizard() става:
```js
host.innerHTML = renderWizSection1Type() + renderWizSection1Photo() + renderWizSection1Name();
```

**Декларирай отговор:** A / B / **C** / D / друго

═══════════════════════════════════════════════════════════════

## 📋 ПЛАНИРАНИ РЕШЕНИЯ (без блокери — ще действам ако НЕ възразиш)

Тези НЕ са въпроси — само ги декларирам, за да отхвърлиш при нужда. Иначе ще действам по тях.

### Решение 1: AI inline endpoints — sacred direct call

Sacred precedent: `ai-color-detect.php` се вика директно (НЕ през bridge). Запазвам същия pattern:

| Funct | Endpoint | Файл | Локално |
|---|---|---|---|
| `wizAIInlineBgRemove` | `/ai-image-processor.php` | sacred | ✅ exists (4122 bytes) |
| `wizAIInlineSeoDesc` | `products.php?ajax=ai_description` | sacred ajax handler (ред 914) | ✅ exists |
| `wizAIInlineMagic` | `/ai-studio-action.php` | sacred | ✅ exists (12640 bytes) |
| `wizPhotoDetectColors` | `/ai-color-detect.php?multi=1` | sacred | ✅ exists |

**Алтернатива:** ако искаш да минават през bridge → разшири `services/wizard-bridge.php` map с 3 нови actions (`ai_bg_remove`, `ai_desc`, `ai_magic`). Кажи и ще го направя в pre-2e++ sub-step.

### Решение 2: Camera loop UI — full sacred copy 1:1

Sacred CSS (1:1, p.php 2032-2141 ~110 lines):
- `.cam-loop-ov / -stage / -empty / -preview / -controls / -btn` + variants `.shoot/.retake/.next/.done/.cancel`
- `.cam-drawer-tip / -icon / -text / b / -app / -or / -flip`
- `.ai-working-ov / -card / -orb / -title / -msg / -hint`
- `@keyframes tipFadeIn, tipShine, tipIconPulse, tipFlipRot, aiOvFade, aiOrbPulse`

Sacred JS (1:1, p.php 9418-9590 ~170 lines):
`wizPhotoMultiPick` (drawer UI), `wizPhotoMultiGalleryPick`, `wizPhotoCameraLoop`, `wizCamRenderEmpty`, `wizCamShoot`, `wizCamLoopOnFile`, `wizCamRetake`, `wizCamAccept`, `wizCamLoopFinish`, `wizCamLoopClose`, `_downscaleDataUrl`, `wizShowAIWorking`, `wizHideAIWorking`, `wizPhotoMultiAdd`

### Решение 3: AI inline impl — full sacred copy + DOM guards

`_wizAIInlineToBlob` (9007), `wizAIInlineBgRemove` (9009-9032), `wizAIInlineSeoDesc` (9034-9064), `wizAIInlineMagic` (9066-9092).

`wizAIInlineSeoDesc` reference-ва DOMs/state които 2e++ scope НЕ включва (`wComposition` input, `CFG.categories`, `CFG.suppliers`, `S.wizData.axes`). Sacred код вече има `(typeof CFG !== 'undefined' && CFG.categories) ? ...` guards и `if (compEl)` checks. ✅ 1:1 copy ще silent no-op-не на липсващите DOMs.

### Решение 4: Color detect — sacred direct call

`wizPhotoDetectColors` (p.php 9666-9749) + `_markPhotosFailed` (9657-9664) → `ai-color-detect.php?multi=1` (sacred direct, не през bridge — прецедент).

### Решение 5: Light mode overrides

Всички нови dark-only CSS класове ще получат `[data-theme="light"]` overrides (както в 2e+/2f). Sacred dark остава непроменен. Ще оставя camera loop черен фон (sacred — full-screen camera UX иска dark always).

### Решение 6: Sub-step ред

Sacred dependency graph диктува ред различен от твоя списък:

| Твой list | Реален ред (по deps) | Защо |
|---|---|---|
| a (toggle) | **2e++a: TYPE toggle + S wizType (опция C)** | Pre-requisite за всичко друго |
| b (grid) | **2e++b: Camera loop CSS + JS infra (incl _downscaleDataUrl, ai-working overlay)** | wizPhotoMultiAdd зависи от _downscaleDataUrl + showAIWorking |
| c (color) | **2e++c: Multi-photo add/remove (wizPhotoMultiPick drawer → wizPhotoCameraLoop → wizPhotoMultiAdd)** | UI loop се отваря тук |
| d (AI inline) | **2e++d: Color detect (ai-color-detect.php integration + wizPhotoDetectColors)** | Auto-triggers след wizPhotoMultiAdd → trial цвят на всяка снимка |
| e (camera) | **2e++e: AI inline buttons (BgRemove/SEO/Magic)** | Standalone — последно, понеже зависи от single _photoDataUrl |

Ако реалния ред те устройва → ще ползвам него. Ако предпочиташ твоя оригинален → кажи (има циклично reference в твоя list — b изисква e first; реалния ред го разсича).

═══════════════════════════════════════════════════════════════

## 📊 ОБЩА СКАЛА

- **Нов CSS:** ~175 реда (~30 за classes, ~30 за @keyframes, ~50 за s95-type-btn, ~65 за light overrides)
- **Нов JS:** ~330 реда (170 camera loop + 80 AI inline + 80 color detect)
- **Wizard-v6.php growth:** 744 → ~1300 реда
- **Time estimate:** 4-5h както каза (5 sub-steps × ~50-60min)

═══════════════════════════════════════════════════════════════

## ✋ STOP — чакам отговор поне на Q1

При OK на **Q1** + (по подразбиране) приемане на Решения 1-6 → започвам **2e++a**.

При възражение на което и да е Решение → flag в чата кое + защо.

При друг ред на sub-steps → дай новия ред.

**Команди за продължаване (когато OK дойде):**
```bash
cd /home/tihol/runmystore
git checkout s148-cc-phase2-frontend
# 2e++a: type toggle (опция C) + wizType state
```
