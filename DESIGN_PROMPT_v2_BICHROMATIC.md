# DESIGN_PROMPT_v2_BICHROMATIC.md

**Версия:** v2 (08.05.2026 — след S97 P13 deployment)
**Заместник на:** `CLAUDE_CODE_DESIGN_PROMPT.md` (legacy)
**Действие:** Този документ е **canonical** за всеки нов design task. Винаги се чете преди да се пише код или mockup.
**Аудитория:** Шеф-чат (Claude orchestration) + Claude Code (direct execution).

---

## 0. SACRED NON-NEGOTIABLES (никога не се отстъпва)

> Тези правила не са guidelines. Те са **закони**. Всяко тяхно нарушение = STOP, питай Тихол. Не "опростявай", не "оптимизирай", не "modernize". 1:1 копира от P13.

### 0.1 Visual canon
- **`mockups/P13_bulk_entry.html`** = canonical template за всички interactive forms.
- **`mockups/P12_matrix.html`** = canonical за grid/matrix UIs.
- **`mockups/P10_lesny_mode.html`** = canonical за дашборди/home/board views.
- **`mockups/P11_detailed_mode.html`** = canonical за chat/feed/list views.
- **`mockups/P3_list_v2.html`** = canonical за catalog/products lists.
- **`mockups/P8b_studio_modal.html`** = canonical за full-screen modals.

**Всеки нов дизайн копира class names, animation timing, color tokens, SVG icon style, spacing scale, typography hierarchy, accordion behavior — 1:1.**

### 0.2 Закони (Bible §1-§16)

| # | Закон | Нарушение = |
|---|---|---|
| 1 | **Пешо не пише** — voice бутон на всяко поле | Sale fail |
| 2 | **PHP смята, AI вокализира** — confidence/margin/SKU в PHP, AI само speak | Hallucination |
| 3 | **Никога "Gemini" в UI** — винаги "AI" | Brand fail |
| 4 | **0 emoji в UI** (§14) — SVG only | Compliance fail |
| 5 | **Sacred Neon Glass dark mode** (§5) — oklch + plus-lighter + 4 spans | Visual regression |
| 6 | **Hidden Inventory** — `confidence_score` никога visible на Митко | UX violation |
| 7 | **i18n всичко** — `t('key')`, никога hardcoded strings | Translation break |
| 8 | **`priceFormat($amount, $tenant)`** — никога hardcoded "лв"/"€" | Currency break |
| 9 | **Bulgarian dual pricing** до 08.08.2026 (€ + лв @ 1.95583) за `country_code='BG'` | Legal fail |
| 10 | **Voice STT LOCKED** commits `4222a66` + `1b80106` — `_wizPriceParse` не пипай | Voice fail |
| 11 | **DB canonical names** — `products.code`, `products.retail_price`, `inventory.quantity` etc | 500 errors |
| 12 | **Никога `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`** в MySQL 8 — PREPARE/EXECUTE | DDL fail |
| 13 | **Никога `sed`** за file edits — Python scripts only | File corruption |
| 14 | **Mobile-first 375px** (Z Flip6 ~373px) — desktop е bonus | UX fail |
| 15 | **Header 7 елемента max** + bottom nav 4 tabs LOCKED | Layout break |
| 16 | **6 фиксирани hue класа** (q-default 255/222, q-magic 280/310, q-loss 0/15, q-gain 145/165, q-amber 38/28, q-jewelry) | Color drift |

---

## 1. MANDATORY READS (преди ВСЕКИ design task)

Прочети **в този ред** преди да напишеш един ред код или mockup:

```
1. /var/www/runmystore/DESIGN_SYSTEM_v4.0_BICHROMATIC.md  (Bible — целия)
2. /var/www/runmystore/HANDOFF_FINAL_BETA.md              (deployment plan)
3. /var/www/runmystore/PRODUCTS_BULK_ENTRY_LOGIC.md       (1:1 spec на P13)
4. /var/www/runmystore/INVENTORY_HIDDEN_v3.md             (confidence_score правила)
5. /var/www/runmystore/MASTER_COMPASS.md                  (coordination)
6. mockups/P13_bulk_entry.html                            (visual canon — целия)
7. mockups/P12_matrix.html                                (grid canon)
8. mockups/P10_lesny_mode.html                            (board canon)
```

**Ако някой от тези файлове не е accessible — STOP. Питай Тихол.**

---

## 2. UNIVERSAL DESIGN TOKENS (1:1 от P13)

Всеки нов mockup/file започва със **същия CSS variables block**:

```css
:root{
  --hue1:255;--hue2:222;--hue3:180;
  --radius:22px;--radius-sm:14px;--radius-pill:999px;--radius-icon:50%;
  --ease:cubic-bezier(0.5,1,0.89,1);
  --ease-spring:cubic-bezier(0.34,1.56,0.64,1);
  --dur:250ms;
  --font-mono:'DM Mono',ui-monospace,monospace;
}
:root:not([data-theme]),:root[data-theme="light"]{
  --bg-main:#e0e5ec;--surface:#e0e5ec;--surface-2:#d1d9e6;
  --text:#2d3748;--text-muted:#64748b;--text-faint:#94a3b8;
  --shadow-light:#ffffff;--shadow-dark:#a3b1c6;
  --shadow-card:8px 8px 16px var(--shadow-dark),-8px -8px 16px var(--shadow-light);
  --shadow-card-sm:4px 4px 8px var(--shadow-dark),-4px -4px 8px var(--shadow-light);
  --shadow-pressed:inset 4px 4px 8px var(--shadow-dark),inset -4px -4px 8px var(--shadow-light);
  --accent:oklch(0.62 0.22 285);--accent-2:oklch(0.65 0.25 305);
  --magic:oklch(0.65 0.25 310);
  --aurora-blend:multiply;--aurora-opacity:0.32;
}
:root[data-theme="dark"]{
  --bg-main:#08090d;--surface:hsl(220,25%,4.8%);--surface-2:hsl(220,25%,8%);
  --text:#f1f5f9;--text-muted:rgba(255,255,255,0.6);--text-faint:rgba(255,255,255,0.4);
  --shadow-card:hsl(var(--hue2) 50% 2%) 0 10px 16px -8px;
  --shadow-card-sm:hsl(var(--hue2) 50% 2%) 0 4px 8px -2px;
  --shadow-pressed:inset 0 2px 4px hsl(var(--hue2) 50% 2%);
  --accent:hsl(var(--hue1),80%,65%);--accent-2:hsl(var(--hue2),80%,65%);
  --magic:hsl(280,70%,65%);
  --aurora-blend:plus-lighter;--aurora-opacity:0.35;
}
```

**Никога не променяй стойностите.** Никога не добавяй нов hue. Никога не сменяй radius scale.

### Light mode = neumorphism
- `box-shadow: 8px 8px 16px var(--shadow-dark), -8px -8px 16px var(--shadow-light)`
- Inset shadow за pressed state.
- Color = oklch palette (никога RGB).

### Dark mode = sacred neon glass (Bible §5)
- Background = radial-gradient на oklch hue1 + hue2 + linear gradient.
- Cards: `position: relative` + `::before` (conic-gradient border) + `::after` (radial blur glow).
- `mix-blend-mode: plus-lighter` ЗАДЪЛЖИТЕЛЕН на ::before и ::after.
- `mask-composite: xor` (или `exclude`) за border ring.
- Никога `overflow: hidden` на neon cards (clips spans).

---

## 3. MANDATORY UI PATTERNS (copy 1:1 от P13)

### 3.1 Header (sticky, 56px)
```html
<header class="bm-header">
  <button class="icon-btn">[back SVG]</button>
  <div class="bm-title">Заглавие</div>
  <button class="icon-btn scan-btn">[scan SVG]</button>
  <button class="icon-btn" onclick="rmsToggleTheme()">[theme SVG]</button>
</header>
```
- Light: `box-shadow: 0 4px 12px rgba(163,177,198,0.15)`
- Dark: `backdrop-filter: blur(16px)` + transparent bg
- Title = gradient text (linear-gradient(135deg, var(--text), var(--accent)))

### 3.2 Voice command bar (Pesho hint)
```html
<button class="voice-bar">
  <span class="voice-bar-mic">[conic spin mic SVG]</span>
  <div class="voice-bar-text">
    <div class="voice-bar-title">Кажи на AI</div>
    <div class="voice-bar-sub">"пример"</div>
  </div>
</button>
```
- Background = magenta/purple gradient (multiply blend в light, plus-lighter в dark)
- Mic icon има conic-gradient shimmer (`animation: conicSpin 4s linear infinite`)

### 3.3 Mode toggle (pill segments)
```html
<div class="mode-toggle" data-mode="X">
  <button class="mode-tab active" data-tab="A" onclick="setMode('A')">[ic] A</button>
  <button class="mode-tab" data-tab="B" onclick="setMode('B')">[ic] B</button>
</div>
```
- 4-px padding, pill-shaped
- Active = purple gradient + 0 4px 14px hsl(255 80% 50% / 0.4) shadow

### 3.4 Accordion section
```html
<section class="acc-section [open]" data-status="filled|active|empty|magic">
  <div class="acc-head" onclick="toggleAcc(this)">
    <span class="acc-head-ic">[icon SVG]</span>
    <span class="acc-title">Заглавие</span>
    <span class="acc-chevron">[chevron SVG]</span>
  </div>
  <div class="acc-body">
    [fields]
    <div class="save-row">
      <button class="save-section-btn">[check SVG] Запази</button>
      <button class="save-aux-btn">[print SVG]</button>
      <button class="save-aux-btn">[download SVG]</button>
    </div>
  </div>
</section>
```

**Status icons (LOCKED):**
- `filled` → green check (linear-gradient(135deg,hsl(145 70% 50%),hsl(155 70% 40%)))
- `active` → purple spinning orb (conic-gradient shimmer)
- `empty` → muted neumorph
- `magic` → magenta/purple orb with shimmer

**Animation:** `max-height: 0 → 1800px`, `padding: 0 → 14px 14px`. Никога `display: none`.

**Dark mode neon glass:** ::before conic border + ::after radial glow. Цветовете ротират според статуса (filled=green, active=purple, magic=magenta).

### 3.5 Field
```html
<div class="field">
  <div class="field-label">
    <span>Етикет<span class="req">*</span></span>
    <span class="opt-pill">ПО ЖЕЛАНИЕ</span>  <!-- optional -->
    <span class="ditto">[chevron-down SVG]</span>  <!-- inherited -->
  </div>
  <div class="input-row">
    <div class="input-shell"><input class="input-text"></div>
    <button class="fbtn cpy">[copy SVG]</button>
    <button class="fbtn voice">[mic SVG]</button>
    <button class="fbtn scan">[scan SVG]</button>  <!-- if applicable -->
    <button class="fbtn add">+</button>  <!-- if applicable -->
  </div>
  <div class="field-hint">Помощен текст ако нужно.</div>
</div>
```

**Voice button задължителен на текстови полета.** Copy/scan/add по contextual нужда.

### 3.6 Stepper (qty/min)
```html
<div class="qty-stepper [warn]">
  <button class="qty-step">−</button>
  <input type="number" class="qty-input" value="X">
  <button class="qty-step">+</button>
</div>
```
- 48px height, pill-shaped
- `.warn` = amber color (за min qty)

### 3.7 Chips (tag/select)
```html
<div class="chips-row">
  <button class="chip-sz">XS</button>
  <button class="chip-sz active">S</button>
  <button class="chip-add">[+ SVG] добави</button>
</div>
```

### 3.8 Bottom bar (fixed, max-width 456px)
```html
<div class="bottom-bar">
  <button class="bb-icon-btn undo">[undo SVG]</button>
  <button class="bb-icon-btn">[print SVG]</button>
  <button class="bb-icon-btn">[csv SVG]</button>
  <button class="btn-next">
    [+ SVG] Запази · следващ
    <span class="btn-next-chev">[chevron]</span>
  </button>
</div>
```
- 12px from edges
- Primary button = purple/magenta gradient + conic shimmer
- Padding-bottom: env(safe-area-inset-bottom, 0)

### 3.9 Bottom sheets
```html
<div class="gs-ov [show]">
  <div class="gs-sheet">
    <div class="gs-handle"></div>
    <div class="gs-title">Заглавие</div>
    <div class="gs-sub">подзаглавие</div>
    [groups/content]
  </div>
</div>
```
- Slide up: `transform: translateY(100% → 0)` with `--ease-spring`
- Backdrop: rgba(0,0,0,0.55) + blur(4px)
- Light: shadow card; Dark: linear-gradient(235deg) + blur(20px)

### 3.10 Aurora background
```html
<div class="aurora">
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
</div>
```
- 2-3 animated blobs, blur(60px)
- Light: multiply blend, opacity 0.32
- Dark: plus-lighter blend, opacity 0.35

---

## 4. SVG ICON LIBRARY (никога emoji)

**Никога:** 📷 ✓ ★ + - 🎤 🔍 ↻ ⚠ 💎 🖨 📊 (Bible §14)

**Винаги:** Inline SVG, stroke-width: 2 (или 2.5 за emphasis), stroke="currentColor", fill="none".

Icon library (от P13, copy директно):

```
[check] <polyline points="20 6 9 17 4 12"/>
[chevron-down] <polyline points="6 9 12 15 18 9"/>
[chevron-right] <polyline points="9 18 15 12 9 6"/>
[chevron-left] <polyline points="15 18 9 12 15 6"/>
[plus] <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
[minus] <line x1="5" y1="12" x2="19" y2="12"/>
[mic] <rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0014 0v-2"/>
[camera] <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/>
[scan/qr-corners] <path d="M3 7V5a2 2 0 012-2h2"/><path d="M17 3h2a2 2 0 012 2v2"/><path d="M21 17v2a2 2 0 01-2 2h-2"/><path d="M7 21H5a2 2 0 01-2-2v-2"/><line x1="7" y1="12" x2="17" y2="12"/>
[search] <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
[print] <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
[csv/download] <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
[undo] <path d="M3 7v6h6"/><path d="M21 17a9 9 0 00-15-6.7L3 13"/>
[copy] <path d="M9 2v6h6V2M5 4v16a2 2 0 002 2h10a2 2 0 002-2V8.5L13.5 2H7a2 2 0 00-2 2z"/>
[star] <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
[swap] <path d="M16 3l4 4-4 4"/><path d="M20 7H4"/><path d="M8 21l-4-4 4-4"/><path d="M4 17h16"/>
[expand] <polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>
[grid-2x2] <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
[circle] <circle cx="12" cy="12" r="9"/>
[magic-sparkles] <path d="M12 2l1.5 4.5L18 8l-4.5 1.5L12 14l-1.5-4.5L6 8l4.5-1.5L12 2z"/><path d="M19 14l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z"/>
[trend-up] <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
[truck/delivery] <rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
[box] <path d="M12.89 1.45l8 4A2 2 0 0122 7.24v9.53a2 2 0 01-1.11 1.79l-8 4a2 2 0 01-1.79 0l-8-4a2 2 0 01-1.1-1.8V7.24a2 2 0 011.11-1.79l8-4a2 2 0 011.78 0z"/><polyline points="2.32 6.16 12 11 21.68 6.16"/><line x1="12" y1="22.76" x2="12" y2="11"/>
[refresh] <path d="M3 12a9 9 0 1018 0M21 4v8h-8"/>
[circle-info] <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
```

**За нови нужни icons:** прочети Lucide Icons (https://lucide.dev) и копирай compatible паттерн (stroke-width 2, currentColor).

---

## 5. ANIMATIONS (LOCKED)

```css
@keyframes conicSpin{to{transform:rotate(360deg)}}
@keyframes auroraDrift{
  0%,100%{transform:translate(0,0) scale(1)}
  33%{transform:translate(30px,-25px) scale(1.1)}
  66%{transform:translate(-25px,35px) scale(0.95)}
}

/* Sacred conic shimmer на active orbs */
.x::before{
  content:'';position:absolute;inset:0;
  background:conic-gradient(from 0deg,transparent 70%,rgba(255,255,255,0.4) 85%,transparent 100%);
  animation:conicSpin 3s linear infinite;
}

/* Accordion */
.acc-body{transition:max-height 0.4s ease, padding 0.3s ease}

/* Bottom sheet */
.gs-sheet{transition:transform 0.32s cubic-bezier(0.34,1.56,0.64,1)}

/* Reduced motion respect */
@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation:none !important;transition:none !important}
}
```

---

## 6. THEME TOGGLE (LOCKED)

```html
<button class="icon-btn" id="themeToggle" onclick="rmsToggleTheme()">
  <svg id="themeIconSun" style="display:none">[sun SVG]</svg>
  <svg id="themeIconMoon">[moon SVG]</svg>
</button>

<script>
(function(){try{var s=localStorage.getItem('rms_theme')||'light';
  document.documentElement.setAttribute('data-theme',s);}catch(_){}})();

function syncThemeIcons(){var t=document.documentElement.getAttribute('data-theme')||'light';
  document.getElementById('themeIconSun').style.display=(t==='dark')?'block':'none';
  document.getElementById('themeIconMoon').style.display=(t==='dark')?'none':'block'}

window.rmsToggleTheme=function(){var c=document.documentElement.getAttribute('data-theme')||'light';
  var n=(c==='light')?'dark':'light';document.documentElement.setAttribute('data-theme',n);
  try{localStorage.setItem('rms_theme',n)}catch(_){}syncThemeIcons()};
syncThemeIcons();
</script>
```

**Никога модифицирай тази функция.** Persistence на localStorage е sacred. Icons swap на toggle е sacred.

---

## 7. MOBILE-FIRST RULES

- Viewport `width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover`
- Max-width `.shell` = 480px
- Test target: Z Flip6 ~373px / iPhone 14 ~390px
- Bottom bar respects `env(safe-area-inset-bottom, 0)`
- Header respects `env(safe-area-inset-top, 0)`
- Touch targets ≥ 38×38px (icon-btn), 42px height (input/select), 46-54px height (primary buttons)

---

## 8. FORBIDDEN (никога)

| ❌ Не прави | Защо |
|---|---|
| Material Design / Bootstrap / Tailwind defaults | Bible §1 — neumorph + neon glass only |
| RGB цветове в light mode | Bible — oklch only |
| Hex colors извън swatches | Color drift |
| `display: none` за accordion | Animation break |
| Inline styles | Design Kit compliance |
| `border-radius` извън scale (22 / 14 / 999 / 50%) | Bible — radius scale locked |
| Multiple gradients без purpose | Visual noise |
| Drop shadows без neumorph pattern | Off-system |
| Backdrop-filter в light mode | Performance |
| Mix-blend-mode извън plus-lighter (dark) или multiply (light) | Bible §5 |
| Cubic-bezier custom без --ease/--ease-spring | Animation drift |
| New fonts | Bible — Montserrat + DM Mono only |
| Animations >500ms (освен conicSpin/auroraDrift) | Sluggish UX |
| `transition: all` | Performance |
| Nested accordions >2 levels | Cognitive overload |
| Modal на mobile освен bottom sheet | UX violation |

---

## 9. WORKFLOW PROMPTS

### 9.1 За шеф-чат (orchestration на нова задача)

```
[ДИЗАЙН ЗАДАЧА — ШЕФ-ЧАТ]
Модул: [файл/feature]
Тип: [нов mockup / fix / migration]

ЗАДЪЛЖИТЕЛНО прочети първо:
1. DESIGN_PROMPT_v2_BICHROMATIC.md (този doc)
2. DESIGN_SYSTEM_v4.0_BICHROMATIC.md (Bible)
3. mockups/P13_bulk_entry.html (canon)
4. + module-specific docs (HANDOFF_FINAL_BETA.md, INVENTORY_HIDDEN_v3.md, etc)

Задачата е:
- Питай преди UX/логически решения
- Действай при технически (component pattern, CSS, SVG)
- Никога не отстъпвай от §0 SACRED
- Mockup в /mnt/user-data/outputs/[файл].html
- 0 emoji, 0 i18n placeholders
- Тествай light + dark
- 1:1 копира class names + icons + animations + tokens от P13

Не commit-ваш — Тихол прави това.
След approval → давай команда за upload + commit.
```

### 9.2 За Claude Code (production rewrite)

```
[ДИЗАЙН ЗАДАЧА — CLAUDE CODE]
Модул: [.php файл]
Mockup canonical: [P13/P12/P10/P11/P3/P8b/...]

ЗАДЪЛЖИТЕЛНО прочети в този ред:
1. /var/www/runmystore/DESIGN_PROMPT_v2_BICHROMATIC.md (целия)
2. /var/www/runmystore/DESIGN_SYSTEM_v4.0_BICHROMATIC.md (целия)
3. /var/www/runmystore/[MODULE_LOGIC].md (specific spec)
4. /var/www/runmystore/mockups/[CANONICAL].html (целия)
5. /var/www/runmystore/INVENTORY_HIDDEN_v3.md
6. /var/www/runmystore/MASTER_COMPASS.md

Workflow:
1. backup на текущия файл (`cp X.php X.php.bak.$(date +%s)`)
2. tmux session задължителен (`tmux new -s cc_X`)
3. write production code 1:1 от mockup
4. `php -l X.php` exit 0
5. `bash /var/www/runmystore/design-kit/check-compliance.sh X.php` exit 0
6. light theme test
7. dark theme test (verify sacred neon glass)
8. screenshot до Тихол за preview
9. след Тихол approval → `git add X.php && git commit --no-verify -m "S[N].MODULE: ..."`
10. `git push origin main`

SACRED (never touch):
- header.php, bottom-nav.php
- .glass / .shine / .glow в dark
- rmsToggleTheme()
- Voice STT commits 4222a66 + 1b80106
- _wizPriceParse parser

ABORT signals (питай Тихол ако):
- Mockup и Bible са в конфликт
- Pre-commit hook FAIL
- Тихол каза "почакай"

Pre-commit hook ще те блокира при сbark. SACRED files няма да се pass-ват автоматично — питай преди да ги пипаш.
```

### 9.3 За Claude Code (нова feature без mockup)

```
[НОВА FEATURE — без canon mockup]
Тази задача няма точен canonical mockup.

Workflow:
1. STOP. НЕ пишеш код.
2. Иди в шеф-чат сесия (Тихол отваря)
3. Шеф-чат създава mockup в /mnt/user-data/outputs/
4. Тихол approve mockup
5. Шеф-чат commit-ва mockup в repo
6. ТОГАВА Claude Code приема task с mockup като canon

Никога не пишеш production code без canonical mockup.
```

---

## 10. VERIFICATION GATES

Преди всеки commit на UI работа:

- [ ] `php -l [файл].php` → exit 0
- [ ] `bash design-kit/check-compliance.sh [файл]` → exit 0
- [ ] Visual diff vs canonical mockup ≤ 1% pixel divergence (Chrome DevTools 375px)
- [ ] **Light theme** — neumorphism shadows visible, no blur, oklch colors
- [ ] **Dark theme** — sacred neon glass spans visible (::before conic, ::after radial)
- [ ] **Theme toggle** — persistence на localStorage works, icons swap
- [ ] **0 emoji** в UI (grep `[\U0001F300-\U0001FAFF\U00002600-\U000027BF]` exit 1)
- [ ] **0 hardcoded "лв"/"€"/"BGN"** (grep -rE "(лв|BGN|€)" -- *.php only in priceFormat() context)
- [ ] **0 hardcoded BG strings** (всички `t('key')`)
- [ ] **0 "Gemini"** в UI (grep -i "gemini" → exit 1 in UI files)
- [ ] **Voice button** на всеки text/number input
- [ ] **Mobile 375px** test passes (no overflow, all CTAs reachable, bottom bar above safe-area)
- [ ] **Animations** respect prefers-reduced-motion
- [ ] **Bottom safe-area** padding на bottom bar
- [ ] **Sacred files** untouched (header.php, bottom-nav.php, theme toggle, voice parsers)

---

## 11. WHEN TO STOP & ASK ТИХОЛ

| Сигнал | Действие |
|---|---|
| Mockup и Bible конфликтират | STOP. Питай Тихол кое е canon. |
| Нова visual фича без mockup | STOP. Шеф-чат първо прави mockup. |
| Pre-commit hook fail след 2 опита | STOP. Питай. |
| `confidence_score` иска да се покаже на Митко | STOP. Hidden Inventory violation. |
| AI prompt казва нещо за "Gemini" | STOP. Това е production word. |
| Performance issue заради neon glass | STOP. Не маxвай sacred — питай. |
| Тихол каза "ти луд ли си?" | STOP. Загубил си контекст. Питай за compass. |
| Backwards compatibility break | STOP. Питай преди migration. |

---

## 12. SISTER DOCUMENTS (читателски ред)

```
DESIGN_PROMPT_v2_BICHROMATIC.md  ← (този doc, винаги първи)
↓
DESIGN_SYSTEM_v4.0_BICHROMATIC.md  ← Bible (technical detail)
↓
HANDOFF_FINAL_BETA.md  ← orchestration
↓
PRODUCTS_BULK_ENTRY_LOGIC.md  ← module-specific spec
↓
mockups/P13_bulk_entry.html  ← visual canon
↓
INVENTORY_HIDDEN_v3.md  ← philosophy
↓
MASTER_COMPASS.md  ← coordination
```

End of `DESIGN_PROMPT_v2_BICHROMATIC.md`
