# DESIGN_CANON_v1.md
## RunMyStore.AI — Финален дизайн документ (canonical, S142 ratified)

**Версия:** 1.0
**Дата:** 13.05.2026
**Статус:** ✅ CANONICAL — заменя всички предишни design документи
**Автор на консолидация:** S142 шеф-чат (Claude Opus 4.7)
**Одобрено от:** Тих

---

## КАК ДА ПОЛЗВАШ ТОЗИ ДОКУМЕНТ

Ти си шеф-чат който прави нов mockup за модул (deliveries, orders, transfers, inventory, sale, marketing, reports, settings).

Прочети целия документ ВЕДНЪЖ. После работиш чрез позоваване на конкретни секции (примерно "следвам §3 Header pattern" + "§7 Закон 6 implementation").

**Източникът на истината са финалните mockup-и в `mockups/*_FINAL.html`** — този документ ги описва, не ги replace-ва.

---

## §0 SACRED — НИКОГА НЕ НАРУШАВАШ

### Кодова Sacred Zone
- `products.php` (14,074 реда — production live)
- `services/voice-tier2.php` (Whisper Groq, БГ числа)
- `services/ai-color-detect.php` (Gemini Vision color)
- `js/capacitor-printer.js` (DTM-5811 BLE/SPP)
- 8-те mic input полета във wizard (`_wizMicWhisper`, `_wizPriceParse`, `_bgPrice`)
- `config/helpers.php` (споделени функции, fmtMoney conflict risk)

### Дизайн Sacred
- `mockups/P15_simple_FINAL.html` — canonical Simple (1653 реда, одобрен в S142)
- `mockups/P2_v2_detailed_FINAL.html` — canonical Detailed (2703 реда, одобрен в S142)
- Закон №6 в Bible (universal pattern за ВСИЧКИ модули)
- Iridescent oklch palette (light) + dark neon glass
- Montserrat — единственият font
- SVG icons — никога emoji
- Mobile-first 375px (Z Flip6 ~373px target)

### Закони от Bible (6 общо)
1. **Закон №1** — ПЕШО НЕ ПИШЕ НИЩО (voice + photo + tap only)
2. **Закон №2** — PHP смята, AI говори (никога AI не генерира числа)
3. **Закон №3** — AI мълчи, PHP продължава (graceful AI failure)
4. **Закон №4** — Addictive UX (нещо ново при всяко отваряне)
5. **Закон №5** — Global-first i18n (никога hardcoded БГ)
6. **Закон №6** — Simple=signals / Detailed=data (нов от S142, universal)

---

## §1 ЗАКОН №6 — ОСНОВНИЯТ PATTERN

### Принципът

| | Simple (Пешо) | Detailed (Митко) |
|---|---|---|
| Какво | AI сигнали (push) | Пълни данни (pull) |
| Как | Реагира на alerts | Сам търси, филтрира |
| UI | Signal feed + 1-tap actions | Tabs, charts, tables, filters |
| Brain mode | "Какво да правя?" | "Защо?" |
| Audit | Tap signal → Detailed view | Source data винаги достъпен |

### 4 типа сигнали (Simple)

| Тип | Цвят | Когато | Брой |
|---|---|---|---|
| 🔴 Alert | red (q1) | Action днес | 3-8 |
| 🟡 Trend | amber (q5) | Следи | 5-15 |
| 🟢 Win | green (q3) | Празнувай | 2-5 |
| 💎 Discovery | purple (qm) | AI намерил | 1-3 |

**Total 10-30 сигнала/ден. Никога празно.**

### Confidence threshold
- `≥ 0.85` → Simple feed (auto-show)
- `0.5-0.85` → Detailed Графики ("AI предполага")
- `< 0.5` → не се показва

### Какво НЕ става сигнал (остава САМО в Detailed)
- Pareto 80/20
- Sezonnost heatmap
- ABC класификация (badge ОК, chart НЕ)
- Margin distribution histograms
- Всяка deep analytical visualisation

### Imperative за нов модул
1. DB queries — една PHP функция → структурирани данни
2. Detailed view — render pull (tabs, charts, tables)
3. Signal extractor — една PHP функция взима същите данни → array of signals
4. Simple view — render push (signal feed cards)
5. Audit linking — tap signal → Detailed view

**Не може един модул да съществува само в един режим.**

---

## §2 DESIGN TOKENS

### Цветове (oklch палитра)

**Light theme (iridescent):**
```css
--bg-main: #e0e5ec;
--surface: #e0e5ec;
--surface-2: #d1d9e6;
--accent: oklch(0.62 0.22 285);    /* purple */
--accent-2: oklch(0.65 0.25 305);
--accent-3: oklch(0.78 0.18 195);  /* cyan */
--magic: oklch(0.65 0.25 305);
--text: hsl(220 25% 15%);
--text-muted: hsl(220 15% 45%);
```

**Dark theme (neon):**
```css
--bg-main: #08090d;
--surface: hsl(220, 25%, 4.8%);
--surface-2: hsl(220, 25%, 8%);
--text: hsl(220 20% 92%);
--text-muted: hsl(220 15% 65%);
```

### 6 hue класа (semantic)

| Class | Hue | Значение |
|---|---|---|
| `q-default` | 240 | Neutral info |
| `q-magic` (qm) | 285-305 | AI / новост |
| `q-loss` (q1) | 0-15 | Червено · негативно · alerts |
| `q-gain` (q3) | 145 | Зелено · позитивно · wins |
| `q-amber` (q5) | 35 | Жълто · внимание · поръчай |
| `q-jewelry` | 275 | Лилаво · причини · discoveries |

### Typography
- **Font family:** `Montserrat`, system-ui (единствен)
- **Mono font:** `'JetBrains Mono', 'Courier New', monospace` (numbers, kpi-num, kp-pill)
- **Sizes:** 9px (label) / 10-11px (sub) / 12-14px (body) / 17-19px (kpi-num) / 24px (h2)

### Spacing
- **Base unit:** 4px
- **Card padding:** 14-16px
- **Section gap:** 12px
- **Touch target min:** 44×44px

### Border radius
- `--radius-sm`: 12px (cells, sm cards)
- `--radius`: 18px (regular cards)
- `--radius-lg`: 24px (large cards)
- `--radius-pill`: 999px (pills, chips)

---

## §3 HEADER PATTERN

### Simple Mode header (опростен)
```html
<header class="rms-header">
  <button class="rms-icon-btn" onclick="location.href='life-board.php'" aria-label="Назад">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
  </button>
  <a class="rms-brand" href="life-board.php">
    <span class="brand-1">RunMyStore</span><span class="brand-2">.ai</span>
  </a>
  <div class="rms-header-spacer"></div>
  <button id="themeToggle" onclick="rmsToggleTheme()" aria-label="Тема">
    <!-- sun/moon SVG -->
  </button>
</header>
```

**Елементи (в строг ред):**
1. Back бутон → life-board.php
2. Лого RunMyStore.ai (link → life-board.php)
3. Spacer (flex: 1)
4. Theme toggle

**НЕ в Simple:** Camera, Printer, Settings, Logout, PRO badge

### Detailed Mode header (пълен)
Същата структура + добавки между spacer и theme:
- Camera (sканирай баркод)
- Printer (printer-setup.php)
- Settings (settings.php)
- Logout (с confirm)
- Theme toggle

### Subbar (под header)
```html
<div class="rms-subbar">
  <select class="subbar-store">ENI ▾</select>
  <span class="subbar-where">[МОДУЛЪТ]</span>
  <button class="mode-toggle" onclick="rmsToggleMode()">
    [Simple: "Разширен →" / Detailed: "← Лесен"]
  </button>
</div>
```

**"[МОДУЛЪТ]"** = името на текущия модул главно ("СТОКАТА МИ", "ДОСТАВКИ", "ПОРЪЧКИ", и т.н.). Това е модулен label, не текст за украса.

---

## §4 GLASS / SHINE / GLOW (Neon Glass) — SACRED CSS

Препис **1:1 от life-board.php (ред 659-686)**. НЕ модифицирай.

### Pattern
```html
<div class="glass [sm|lg] [q-class]">
  <span class="shine"></span>
  <span class="shine shine-bottom"></span>
  <span class="glow"></span>
  <span class="glow glow-bottom"></span>
  <!-- content -->
</div>
```

### CSS принципи (sacred — копираш 1:1)
```css
.glass {
  position: relative;
  background: var(--surface);
  border-radius: var(--radius);
  overflow: visible;  /* НЕ overflow:hidden — изрязва shine */
}

.shine, .shine-bottom {
  position: absolute;
  inset: 0;
  border-radius: inherit;
  pointer-events: none;
  background: conic-gradient(...);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
}

[data-theme="dark"] .top-row .glow,
[data-theme="dark"] .top-row-3 .glow {
  display: none;  /* срещу розова leak линия (S142 fix) */
}
```

### Hue tokens per class

| Class | --hue1 (горно-дясно) | --hue2 (долно-ляво) |
|---|---|---|
| qd | --hue1 (240) | --hue2 (260) |
| q1 | 0 | 15 |
| q3 | 130 | 160 |
| q5 | 30 | 45 |
| qm | 280 | 310 |

**Защо отделни hue1/hue2:** ако са еднакви, двата glow-а се сливат в плътна линия. С различни — мек преход, не leak.

---

## §5 SEARCH BAR PATTERN

### HTML structure
```html
<div class="search-wrap">
  <input type="text" id="hSearchInp" placeholder="Търси по име или баркод">
  <button class="s-btn filter" onclick="openDrawer('filter')">
    <svg><!-- filter icon --></svg>
  </button>
  <button class="s-btn mic" onclick="searchInlineMic(this, 'hSearchInp')">
    <svg><!-- microphone icon --></svg>
  </button>
  <div class="search-results-dd" id="hSearchDD"><!-- autocomplete --></div>
</div>
```

### Voice mic (sacred copy 1:1 от products.php ред 5310)
```js
function searchInlineMic(btn, inputId){
    if (_searchMicRec) { try { _searchMicRec.stop(); } catch(e){} return; }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    const inp = document.getElementById(inputId);
    btn.classList.add('recording');  // червен + пулсираща точка
    const rec = new SR();
    rec.lang = 'bg-BG';
    rec.continuous = true; rec.interimResults = true;
    // Auto-stop след 2 сек тишина
    // Повторен tap = manual stop
    // Текст се пише директно в input (не overlay)
}
```

### Recording state
- Bg: red gradient
- Animation: pulsing red dot
- Label: "REC..." с opacity oscillation
- Tap отново → manual stop

### Filter drawer (bottom sheet)
Под търсачката има link **"Виж всички N [артикули/доставки/поръчки]"** който води в P3 list view.

---

## §6 SIMPLE MODE LAYOUT (canonical P15)

### Структура
```
Header (back + brand + theme)
Subbar (ENI ▾ + МОДУЛ + Разширен →)
Inv-nudge pill (амбър, ако applicable)
Search bar + filter btn + mic
"Виж всички N ___" link
Quick actions row:
  - "Добави ___" qa-btn (40x40 oklch icon)
    └─ "Като предния" kp-pill вътре, вдясно (вариант Б)
  - "AI поръчка" studio-btn (qm)
Help card "Как работи ___?"
Multi-store glance (5 stores · dot + trend pill + revenue · БЕЗ графики)
AI feed (10 различни type сигнали като lb-cards)
Chat-input-bar (floating pill, fixed bottom: 16px)
```

### "Добави ___" qa-btn (1:1 от P15)
```html
<div role="button" class="glass sm qa-btn qa-primary qd" onclick="openAdd[Module]()">
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <span class="glow"></span><span class="glow glow-bottom"></span>
  <div class="qa-ic">
    <svg viewBox="0 0 24 24"><!-- plus icon --></svg>
  </div>
  <div class="qa-text">
    <div class="qa-title">Добави [артикул/доставка/поръчка]</div>
    <div class="qa-sub">Натисни и започни</div>
  </div>
  <button class="kp-pill" onclick="event.stopPropagation();openLikePrevious()">
    <svg><!-- refresh icon --></svg>
    <span>Като предния</span>
  </button>
</div>
```

### AI feed cards (lb-card pattern от life-board.php)
```html
<div class="lb-card q1" onclick="lbToggleCard(event, this)">
  <div class="lb-collapsed">
    <span class="lb-emoji-orb lb-ic-alert">
      <svg><!-- alert icon --></svg>
    </span>
    <div class="lb-text">
      <span class="lb-tag">АЛАРМА</span>
      <span class="lb-title">Свърши Nike Air Max 42 · 7 продажби</span>
    </div>
    <button class="lb-expand-arrow">▼</button>
  </div>
  <div class="lb-expanded">
    <!-- description, details, action buttons, feedback 👍/👎 -->
  </div>
</div>
```

---

## §7 DETAILED MODE LAYOUT (canonical P2_v2)

### Структура
```
Header (back + brand + camera + printer + settings + logout + theme)
Subbar (ENI ▾ + МОДУЛ + ← Лесен)
Inv-nudge pill
Search bar + filter + mic
"Виж всички N ___" link
Q-chips row (6 сигнал филтри · horizontal scroll)
Tab bar (Преглед/Графики/Управление/Артикули) — 4 таба
Tab content:
  - Преглед: 11 секции (виж по-долу)
  - Графики: 5-6 charts
  - Управление: suppliers + multi-store + bulk
  - Артикули: list + filter chips + search
Chat-input-bar (floating pill, fixed bottom: calc(64px + 24px))
Bottom-nav (4 gradient orbs · 1:1 от chat.php)
```

### Detailed Tab Преглед — 11 секции (canonical)

1. **Period toggle** (Днес/7д/30д/90д) + ✨ YoY toggle
2. **Quick actions** (Добави + Като предния pill + AI поръчка)
3. **5-KPI scroll** (horizontal, в зависимост от модула)
4. **Тревоги 2-cell** (модул-специфични — Свършили/Доставка закъсня, или Pending/Overdue, или т.н.)
5. **Cash reconciliation tile** (POS / Реално / Разлика + 7-day avg) — ако модул работи с пари
6. **Weather Forecast Card** (7/14 дни tabs + AI препоръка) — ако модул е weather-relevant
7. **Health card** + Weeks of Supply
8. **Sparkline toggle** (Печеливши ↔ Застояли)
9. **Топ 3 за [action]** (AI quick action)
10. **Топ 3 [supplier/store/category]** + reliability score
11. **Магазини ranked table** + Transfer Dependence column

### Tab Графики — canonical 5 charts
- Pareto 80/20
- Марж тренд 90д
- [Модул-специфичен] по category/supplier
- Сезонност AI откри
- **Календар heatmap с дати + бр** (S142 — задължително дати + числа, не празни квадрати)

---

## §8 BOTTOM-NAV (1:1 ОТ chat.php)

**САМО в Detailed Mode.** Simple няма bottom-nav (по Закон 11).

### Структура
```html
<nav class="rms-bottom-nav">
  <button class="nav-orb nav-ai" onclick="location.href='chat.php'">
    <div class="orb-bg"></div>
    <svg><!-- AI star icon --></svg>
    <span class="nav-label">AI</span>
  </button>
  <button class="nav-orb nav-stock active">
    <div class="orb-bg"></div>
    <div class="orb-glow-ring"></div>  <!-- spinning conic gradient -->
    <svg><!-- box icon --></svg>
    <span class="nav-label">Склад</span>
  </button>
  <button class="nav-orb nav-stats" onclick="location.href='stats.php'">
    <div class="orb-bg"></div>
    <svg class="nav-stats-svg">
      <polyline class="nav-stats-line"/>  <!-- drawing line animation -->
      <circle class="nav-stats-dot"/>  <!-- pulsing dots -->
    </svg>
    <span class="nav-label">Справки</span>
  </button>
  <button class="nav-orb nav-sale" onclick="location.href='sale.php'">
    <div class="orb-bg"></div>
    <svg class="nav-sale-bolt"><!-- zap bolt -->
    </svg>
    <span class="nav-label">Продажба</span>
  </button>
</nav>
```

### Per-tab градиенти (gradient orb)
```css
.nav-ai .orb-bg { background: radial-gradient(circle, oklch(0.65 0.25 290), oklch(0.40 0.18 280)); }
.nav-stock .orb-bg { background: radial-gradient(circle, oklch(0.70 0.20 200), oklch(0.45 0.15 195)); }
.nav-stats .orb-bg { background: radial-gradient(circle, oklch(0.72 0.20 145), oklch(0.42 0.15 140)); }
.nav-sale .orb-bg { background: radial-gradient(circle, oklch(0.78 0.18 40), oklch(0.55 0.16 35)); }
```

### Анимации (sacred)
- `navOrbBreath` 3.2s — гladko дишане на всички orbs
- `navOrbShimmer` 6s — slow shimmer overlay
- `navOrbActiveSpin` 3s — само на active (conic gradient ring)
- `navStatsLineDraw` 3s — line drawing на Справки
- `navStatsDotPulse` 1.6s — pulsing dots
- `navBoltZap` 2.2s — zap анимация на Продажба
- **Stagger delays** — orbs не пулсират едновременно (0s, -0.8s, -1.6s, -2.4s)
- `@media (prefers-reduced-motion)` — всичко спира

---

## §9 CHAT-INPUT-BAR (1:1 ОТ chat.php)

Floating pill, **fixed position** в долен край.

### HTML
```html
<div class="chat-input-bar" role="button" tabindex="0" onclick="rmsOpenChat()">
  <span class="chat-waveform-ic">
    <svg><!-- waveform icon --></svg>
  </span>
  <span class="chat-placeholder">Кажи или напиши...</span>
  <button class="chat-mic" onclick="event.stopPropagation();searchInlineMic(this,'chatInp')">
    <svg><!-- mic icon --></svg>
  </button>
  <button class="chat-send" onclick="event.stopPropagation();rmsSendChat()">
    <svg><!-- send icon --></svg>
  </button>
</div>
```

### Position
- Simple Mode: `bottom: 16px` (няма bottom-nav)
- Detailed Mode: `bottom: calc(64px + 24px)` (плава 88px над bottom-nav)
- `max-width: 456px`, centered
- Pill shape (`border-radius: var(--radius-pill)`)

### Анимации
- Mic: 2 разширяващи се ringa (`chatMicRing 2s ease-out infinite`)
- Send: leky horizontal drift 0→2px (`chatSendDrift 1.8s`)
- Light: neumorphic surface + shadow-card
- Dark: gradient + `backdrop-filter: blur(16px)`

---

## §10 FORBIDDEN PATTERNS

### НЕ прави това:
- ❌ Mac-glass effect (semi-transparent с frosted bg) — старо macOS look, не Neon Glass
- ❌ Emoji в UI (само SVG icons)
- ❌ `overflow: hidden` на `.glass` cards (изрязва shine spans)
- ❌ Hardcoded "лв" / "BGN" / "€" — само през `priceFormat($amount, $tenant)`
- ❌ Lavender/pastel градиенти — sacred е neon (saturated oklch)
- ❌ Material Design ripple effects — конфликт с neon glass
- ❌ Tailwind classes (project е inline CSS pattern, не utility-first)
- ❌ Box shadows на dark mode (използваме glow blobs вместо)
- ❌ Date pickers с native input — pos custom keyboard
- ❌ PRO badge в header (махнат в S142)
- ❌ "Стоката ми" / "Доставки" текст вместо лого в header
- ❌ Bottom-nav в Simple Mode (по Закон 11)
- ❌ Празна "AI feed" — винаги минимум 5-10 сигнала (по Тих директивата)

### Старо vs Ново (S142 промени)

| Старо | Ново |
|---|---|
| GMROI в KPI | "Замразен €" (4/4 AI consensus) |
| 3 KPI cards | 5-KPI scroll (horizontal) |
| "Застояли 60+" в тревоги | "Доставка закъсня" |
| Календар heatmap празни цветни квадрати | Календар с дати + бр продажби |
| Multi-store с sparklines в Simple | Без sparklines, само dot + trend pill + revenue |
| "Като предния" отделна карта | "Като предния" pill вътре в Добави карта (вариант Б) |
| PRO badge | Махнат |
| Bottom-nav плоски икони | 4 gradient orbs (1:1 от chat.php) |

---

## §11 IMPLEMENTATION CHECKLIST

При **всеки нов mockup** питай:

### Pre-design
- [ ] Прочетох SESSION_S142_FULL_HANDOFF.md?
- [ ] Прочетох module-specific spec (DELIVERIES_FINAL_v3, ORDERS_DESIGN_LOGIC, etc.)?
- [ ] Имам ли двата canonical mockup-а отворени за reference?

### Design
- [ ] Header Simple = back + brand + theme (без extras)?
- [ ] Header Detailed = + camera/printer/settings/logout?
- [ ] Subbar с модулен label?
- [ ] Search bar с микрофон (searchInlineMic pattern)?
- [ ] "Виж всички N ___" link?
- [ ] "Добави ___" с "Като предния" pill (вариант Б)?
- [ ] Multi-store glance (ако модул работи with stores)?
- [ ] AI feed с минимум 8 сигнала (Закон 6)?
- [ ] 5-KPI scroll в Detailed (не 3)?
- [ ] Bottom-nav 1:1 от chat.php в Detailed?
- [ ] Chat-input-bar floating pill?

### Closure
- [ ] Glass shine/glow от life-board.php pattern (не lb-card)?
- [ ] Top-row glow off в dark mode?
- [ ] Mobile-first 375px (виж в Z Flip6 viewport)?
- [ ] Без emoji, само SVG?
- [ ] Без hardcoded currency?
- [ ] Approved от Тих преди commit?

---

## §12 SACRED MOCKUP REFERENCES

При съмнение, отвори тези файлове в нов tab:

| Mockup | За какво е reference |
|---|---|
| `mockups/P15_simple_FINAL.html` | Simple Mode цялостна структура |
| `mockups/P2_v2_detailed_FINAL.html` | Detailed Mode + tabs + 11 секции |
| `chat.php` (production) | Bottom-nav + sticky bar анимации |
| `life-board.php` (production) | Glass shine/glow + lb-cards expand |
| `products.php` (production) | Search dropdown + filter drawer + searchInlineMic |

---

## §13 КОГА ДА ПИТАШ ТИХ

### Питай винаги за (UX/продуктово):
- Кое поле е задължително
- Какво вижда Пешо vs Митко (Закон 6 boundary)
- Брой/тип сигнали в Simple feed
- Колко секции/charts в Detailed (sweep)
- Action button label-и

### Решавай сам за (технически):
- CSS sizing (с !important за нови SVG)
- PHP query optimization
- AJAX endpoint naming
- JS handler imp
- File structure / partial extraction

---

## §14 КАК ДА КОМИТНЕШ

```bash
# Преди:
git tag pre-[module]-design
git push origin pre-[module]-design

# След approval от Тих:
cp /tmp/[mockup].html /home/claude/runmystore/mockups/PXX_[module]_simple_FINAL.html
git add mockups/PXX_[module]_simple_FINAL.html
git commit -m "S[N]: Финален Simple mockup за [module] — approved от Тих"
git push origin main
```

Двата FINAL файла (Simple + Detailed) трябва да бъдат commit-нати ЕДНОВРЕМЕННО или в последователни commits. Не оставяй "half-module" в repo.

---

## §15 ИЗОСТАВЕНИ ДОКУМЕНТИ (DEPRECATED)

Тези файлове са **остарели** — не следвай тях:

| Файл | Защо deprecated | Заместен от |
|---|---|---|
| `DESIGN_PROMPT_v2_BICHROMATIC.md` | Преди S142 patterns | `DESIGN_CANON_v1.md` (този) |
| `DESIGN_SYSTEM.md` | v1, преди oklch | `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` (tokens) + `DESIGN_CANON_v1.md` (patterns) |
| `CLAUDE_CODE_DESIGN_PROMPT.md` | Claude Code era, преди SWAP | `DESIGN_CANON_v1.md` |
| `HANDOFF_S96_DESIGN_BICHROMATIC.md` | S96-specific, остарял | `SESSION_S142_FULL_HANDOFF.md` |
| `TOMORROW_WIZARD_REDESIGN.md` | Wizard era, преди sacred zone | Sacred — не редизайнваме wizard |

`DESIGN_SYSTEM_v4.0_BICHROMATIC.md` остава като reference за **дизайн tokens** (цветове, typography). Patterns и layout идват от **`DESIGN_CANON_v1.md`** (този документ).

---

## §16 ЗАКЛЮЧЕНИЕ

Този документ е **canonical reference за всеки нов модул**. Той описва ТОЧНО как изглежда:
- Header (Simple vs Detailed)
- Subbar
- Search bar с voice
- Quick actions с "Като предния" pill
- AI feed с 4 типа сигнали
- 11-секционен Detailed Tab Преглед
- Bottom-nav с 4 gradient orbs
- Chat-input-bar floating pill
- Glass shine/glow neon pattern
- Dark mode fixes

**При несигурност — отвори canonical mockup-ите (`P15_simple_FINAL` + `P2_v2_detailed_FINAL`) и копирай 1:1.**

**Никога не измисляй нов pattern. Винаги копирай от sacred sources.**

---

**Версия 1.0 — ratified 13.05.2026, S142 шеф-чат.**
**Следваща ревизия:** При major design promяна (примерно ако Тих одобри нов pattern в S143+).
