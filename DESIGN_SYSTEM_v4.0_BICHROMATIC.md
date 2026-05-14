# 🎨 RUNMYSTORE.AI — DESIGN SYSTEM v4.0 BICHROMATIC

**Дата:** 2026-05-07
**Стил:** Bichromatic — Light Hybrid (default) · Dark Neon Glass (option)
**Source of truth:** ТОЗИ ДОКУМЕНТ + `life-board.php` (reference implementation)
**Статус:** **АКТИВЕН ЕТАЛОН за целия проект**

> Този документ ОТМЕНЯ всички предишни версии:
> - DESIGN_SYSTEM_v1.md → archived
> - DESIGN_SYSTEM_v3_COMPLETE.md → archived (Neon Glass-only era)
> - kato-predniq-law.html → archived

---

# 🎯 КАК ДА ИЗПОЛЗВАШ ТОЗИ ДОКУМЕНТ

Този документ е **пълна и единствена** спецификация на дизайна. Всеки модул (chat.php, products.php, sale.php, ai-studio.php, orders.php, deliveries.php, transfers.php, inventory.php, stats.php, life-board.php, settings.php, register.php, login.php, и т.н.) ТРЯБВА да го следва **1:1**.

**5 закона:**

1. ❌ НЕ измисляй свои варианти на компоненти, тонове, ефекти
2. ✅ Копирай CSS блоковете точно както са дадени
3. ✅ Спазвай йерархията на CSS variables
4. ✅ Прилагай Adoption Checklist в края преди commit
5. ❌ НЕ ползвай Bootstrap / Tailwind / CSS frameworks

**Принципно правило (закон №0):**
> *Никога не пиши код, който не работи И в двата режима. Light без dark = bug. Dark без light = bug. Всеки компонент ТРЯБВА да има две тематични форми.*

---

# 📐 ЧАСТ 1 — ФУНДАМЕНТАЛНИ ПРИНЦИПИ

## 1.1 Двата режима

| Режим | Стил | Default | Background |
|-------|------|---------|------------|
| **LIGHT** | Light Hybrid (Neumorphism база + Neon Glass-style ефекти) | ✅ Default (първо влизане) | `#e0e5ec` сив-синкав |
| **DARK** | SACRED Neon Glass (запазен 1:1 от v3.0) | Option (toggle от header) | `#08090d` + radial gradients |

**Toggle:** `<button id="themeToggle" onclick="rmsToggleTheme()">` — вече съществува в `partials/header.php`.

## 1.2 Continuity (приемственост)

Двата режима споделят **идентична структура, форми, размери и анимации**. Различават се **САМО** по:
- Background (light: gray-blue / dark: dark blue radial gradients)
- Surface fill (light: same as bg / dark: 3-layer gradient with backdrop-blur)
- Shadow recipe (light: neumorphism convex / dark: Neon Glass conic shine + glow)
- Text color (light: `#2d3748` / dark: `#f1f5f9`)
- Border (light: `none` (shadow-only depth) / dark: `1px solid hsl(hue2, 12%, 20%)`)
- Aurora blend mode (light: `multiply` / dark: `plus-lighter`)
- Saturation (light: oklch full / dark: hsl 80% sat)

## 1.3 8 основни визуални правила

1. **Conic-gradient визуални акценти** на всеки „важен" елемент (Life Board orb, PRO badge, primary CTA, AI Brain)
2. **Hue-matched семантика** — всеки от 6-те фундаментални въпроса има специфичен цвят (q1-q6)
3. **Pill форми (radius 999px)** за ВСИЧКИ интерактивни pills, badges, status chips, mode toggles, store-chip, chat input bar
4. **Dark mode: backdrop-blur 12px** за всеки `.glass` елемент
5. **Light mode: convex shadows 8px / -8px двойни** за всяка карта
6. **Hue-tinted radial glows** в ъглите на важни елементи (само в dark)
7. **Aurora blobs на background** (видими в двата режима, различен blend)
8. **9 ефекта работят И в двата режима** (с различен blend mode/saturation)

## 1.4 Какво МОЖЕ и какво НЕ МОЖЕ да се пипа

**SACRED — никога не модифицирай:**
- `.glass` 3-layer linear-gradient + backdrop-filter:blur(12px) (dark only)
- `.shine` conic-gradient + mask-composite: subtract (dark only)
- `.glow` SVG noise mask + mix-blend-mode: plus-lighter (dark only)
- header.php 7-елементен ред (brand → plan → spacer → print → settings → logout → theme)
- bottom-nav.php 4-таб ред (AI → Склад → Справки → Продажба)

**Mutable (може да се пипа само през този документ):**
- Cards content
- Component recipes (но СЪГЛАСНО таблиците по-долу)
- Animations (но само от animation library в Част 9)

---

# 🎨 ЧАСТ 2 — TOKEN SYSTEM

## 2.1 Continuity tokens (СЪЩО в двата режима)

```css
:root {
  /* Hue анкери — определят семантиката на цвета */
  --hue1: 255;          /* Primary indigo */
  --hue2: 222;          /* Secondary blue */
  --hue3: 180;          /* Tertiary cyan */

  /* Радиуси */
  --radius:        22px;    /* Glass card */
  --radius-sm:     14px;    /* Small glass / inputs / lb-body / actions */
  --radius-pill:   999px;   /* All pills, store-chip, lb-mode-toggle, chat input bar, plan-badge */
  --radius-icon:   50%;     /* All circular icons (avatars, op-icons, lb-emoji, fb-btn, mic) */

  /* Borders */
  --border:        1px;

  /* Easing */
  --ease:          cubic-bezier(0.5, 1, 0.89, 1);
  --ease-spring:   cubic-bezier(0.34, 1.56, 0.64, 1);

  /* Duration */
  --dur-fast:      150ms;
  --dur:           250ms;
  --dur-slow:      350ms;

  /* Press scale */
  --press:         0.97;

  /* Fonts (continuity) */
  --font:          'Montserrat', sans-serif;
  --font-mono:     'DM Mono', ui-monospace, monospace;

  /* Z-index scale */
  --z-aurora:      0;
  --z-content:     5;
  --z-shine:       1;
  --z-glow:        3;
  --z-overlay:     50;
  --z-modal:       100;
}
```

## 2.2 LIGHT theme tokens (default)

```css
[data-theme="light"], :root:not([data-theme]) {
  /* Surface */
  --bg-main:        #e0e5ec;
  --surface:        #e0e5ec;       /* Same as bg — neumorphism principle */
  --surface-2:      #d1d9e6;

  /* Border (light = none, depth via shadows) */
  --border-color:   transparent;

  /* Text */
  --text:           #2d3748;       /* WCAG AAA on bg */
  --text-muted:     #64748b;       /* WCAG AA on bg */
  --text-faint:     #94a3b8;

  /* Neumorphism shadow colors */
  --shadow-light:   #ffffff;
  --shadow-dark:    #a3b1c6;

  /* Neumorphism distances */
  --neu-d:          8px;            /* big card distance */
  --neu-b:          16px;           /* big card blur */
  --neu-d-s:        4px;            /* small element distance */
  --neu-b-s:        8px;            /* small element blur */

  /* Composite shadow recipes (use these, never raw) */
  --shadow-card:
    var(--neu-d) var(--neu-d) var(--neu-b) var(--shadow-dark),
    calc(var(--neu-d) * -1) calc(var(--neu-d) * -1) var(--neu-b) var(--shadow-light);

  --shadow-card-sm:
    var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark),
    calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);

  --shadow-pressed:
    inset var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark),
    inset calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);

  /* Accent colors (oklch — full saturation) */
  --accent:         oklch(0.62 0.22 285);
  --accent-2:       oklch(0.65 0.25 305);
  --accent-3:       oklch(0.78 0.18 195);

  /* Semantic 6 fundamental questions */
  --q1-loss:        oklch(0.65 0.22 25);     /* red — Какво губиш */
  --q2-why-loss:    oklch(0.65 0.25 305);    /* violet — От какво губиш */
  --q3-gain:        oklch(0.68 0.18 155);    /* green — Какво печелиш */
  --q4-why-gain:    oklch(0.72 0.18 195);    /* teal — От какво печелиш */
  --q5-order:       oklch(0.72 0.18 70);     /* amber — Поръчай */
  --q6-no-order:    oklch(0.62 0.05 220);    /* gray — НЕ поръчвай */

  /* Status */
  --success:        oklch(0.68 0.18 155);
  --warning:        oklch(0.72 0.18 70);
  --danger:         oklch(0.65 0.22 25);

  /* Aurora */
  --aurora-blend:   multiply;
  --aurora-opacity: 0.35;
}
```

## 2.3 DARK theme tokens (SACRED — preserved 1:1 from v3.0)

```css
[data-theme="dark"] {
  /* Surface — Neon Glass */
  --bg-main:        #08090d;
  --surface:        hsl(220, 25%, 4.8%);     /* base of .glass 3-layer */
  --surface-2:      hsl(220, 25%, 8%);

  /* Border */
  --border-color:   hsl(var(--hue2), 12%, 20%);

  /* Text */
  --text:           #f1f5f9;
  --text-muted:     rgba(255, 255, 255, 0.6);
  --text-faint:     rgba(255, 255, 255, 0.4);

  /* Glass shadow */
  --shadow-card:
    hsl(var(--hue2) 50% 2%) 0 10px 16px -8px,
    hsl(var(--hue2) 50% 4%) 0 20px 36px -14px;

  --shadow-card-sm:
    hsl(var(--hue2) 50% 2%) 0 4px 8px -2px;

  --shadow-pressed:
    inset 0 2px 4px hsl(var(--hue2) 50% 2%);

  /* Accent (hsl saturation 80%) */
  --accent:         hsl(var(--hue1), 80%, 65%);
  --accent-2:       hsl(var(--hue2), 80%, 65%);
  --accent-3:       hsl(var(--hue3), 70%, 55%);

  /* Semantic — same hue, light values */
  --q1-loss:        hsl(0, 85%, 60%);
  --q2-why-loss:    hsl(280, 70%, 65%);
  --q3-gain:        hsl(145, 70%, 55%);
  --q4-why-gain:    hsl(175, 70%, 55%);
  --q5-order:       hsl(38, 90%, 60%);
  --q6-no-order:    hsl(220, 10%, 60%);

  /* Status */
  --success:        hsl(145, 70%, 55%);
  --warning:        hsl(38, 90%, 60%);
  --danger:         hsl(0, 85%, 60%);

  /* Aurora */
  --aurora-blend:   plus-lighter;
  --aurora-opacity: 0.35;
}
```

## 2.4 Token usage таблица

| Token | Use in | Forbidden in |
|-------|--------|--------------|
| `--bg-main` | `body { background }`, `.screen { background }` | Cards, buttons (use `--surface` или background recipes) |
| `--surface` | All cards / pills / btns base background | Body, body backdrop |
| `--text` | Headings, primary content | Hint text (use `--text-muted`) |
| `--text-muted` | Labels, meta, sub-titles | Primary content |
| `--accent` | Primary CTAs, gradient headlines | Body text (no readability) |
| `--q1-loss`...`--q6-no-order` | LB-card border-left, fq-tag-mini, glow ring | Декоративен splash без семантика |
| `--shadow-card` | All raised cards | Pressed state (use `--shadow-pressed`) |
| `--shadow-pressed` | `:active`, expanded states | Default state |
| `--neu-d`, `--neu-b` | Custom shadow recipes (light only) | Generic generic shadow values |
| `--aurora-blend` | aurora-blob mix-blend-mode | Other elements |

---

# 🏛 ЧАСТ 3 — HEADER & BOTTOM NAV (1:1 canonical)

## 3.1 Header structure — ЗАВИСИ ОТ СТРАНИЦАТА (S144 правило)

> **🔒 ЗАКОН (S144):** Header-ът има **3 различни форми** според страницата. Това правило е финално, не се обяснява повторно — четеш и прилагаш.

### Форма А — chat.php (FULL header, 4 икони)

ОТ `partials/header.php` — НЕ пипай.

**7 елемента в ТОЧЕН ред:**

```html
<header class="rms-header" id="rmsHeader">
    <a href="chat.php" class="rms-brand" title="Начало">RUNMYSTORE.AI</a>
    <span class="rms-plan-badge <?= $rms_plan ?>"><?= $rms_plan_label ?></span>
    <div class="rms-header-spacer"></div>
    <button class="rms-icon-btn rms-print" id="printStatusBtn" onclick="rmsOpenPrinter()">[print svg]</button>
    <a href="settings.php" class="rms-icon-btn">[gear svg]</a>
    <button class="rms-icon-btn" id="logoutBtn" onclick="rmsToggleLogout(event)">
        [exit svg]
        <a href="logout.php" class="rms-logout-dd" id="logoutDrop">Изход →</a>
    </button>
    <button class="rms-icon-btn" id="themeToggle" onclick="rmsToggleTheme()">[sun/moon]</button>
</header>
```

**Ред (никога не променяй):** brand → plan-badge → spacer → Print → Settings → Logout → Theme

### Форма Б — всички ОСТАНАЛИ страници (опростен header, 2 действия)

`products-v2.php`, `warehouse.php`, `stats.php`, `inventory.php`, `transfers.php`, `deliveries.php`, `suppliers.php`, `finance.php`, всички производни страници.

**4 елемента:**

```html
<header class="rms-header">
    <a href="life-board.php" class="rms-brand">RUNMYSTORE.AI</a>
    <div class="rms-header-spacer"></div>
    <button class="rms-icon-btn" id="themeToggle" onclick="rmsToggleTheme()">[sun/moon]</button>
    <a href="sale.php" class="sale-pill">
        <svg>[cart icon]</svg>
        <span>Продажба</span>
    </a>
</header>
```

**Ред:** brand → spacer → Theme → Продажба pill (амбър gradient)

**НЕ показва:** plan-badge, Print, Settings, Logout, Camera, Back бутон.

### Форма В — sale.php (БЕЗ header)

sale.php няма header изобщо. Камерата заема цялата горна част на екрана (`v-camera-header` 80px видео фон с зелена лазерна линия).

### Правилото с 1 изречение

**Във всеки модул е форма Б, освен chat.php = форма А, sale.php = форма В.**

## 3.2 Header CSS (apply 1:1)

```css
.rms-header {
  position: sticky; top: 0; left: 0; right: 0;
  z-index: 50;
  height: 56px;
  padding: 0 16px;
  display: flex; align-items: center; gap: 8px;
  border-bottom: 1px solid var(--border-color);
  padding-top: env(safe-area-inset-top, 0);
  padding-left: max(16px, env(safe-area-inset-left, 0));
  padding-right: max(16px, env(safe-area-inset-right, 0));
}

[data-theme="light"] .rms-header {
  background: var(--bg-main);
  box-shadow: 0 4px 12px rgba(163, 177, 198, 0.15);
}

[data-theme="dark"] .rms-header {
  background: hsl(220 25% 4.8% / 0.85);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}

.rms-brand {
  font-family: var(--font);
  font-size: 13px; font-weight: 900;
  letter-spacing: 0.06em;
  color: var(--text);
  text-decoration: none;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  -webkit-background-clip: text; background-clip: text;
  color: transparent;
}

.rms-plan-badge {
  position: relative;
  padding: 5px 12px;
  border-radius: var(--radius-pill);
  font-family: var(--font-mono);
  font-size: 9px; font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--text);
  border: 1px solid var(--border-color);
  overflow: hidden;
}

[data-theme="light"] .rms-plan-badge {
  background: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: none;
}

[data-theme="dark"] .rms-plan-badge {
  background: hsl(220 25% 8% / 0.7);
  backdrop-filter: blur(8px);
}

/* Animated conic ring (Effect #4 — both modes) */
.rms-plan-badge::before {
  content: ''; position: absolute; inset: -1px;
  border-radius: inherit;
  padding: 1.5px;
  background: conic-gradient(from 0deg,
    hsl(var(--hue1) 80% 60%),
    hsl(var(--hue2) 80% 60%),
    hsl(var(--hue3) 70% 60%),
    hsl(var(--hue1) 80% 60%));
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
  animation: conicSpin 3s linear infinite;
  opacity: 0.6;
  pointer-events: none;
}

.rms-header-spacer { flex: 1; }

.rms-icon-btn {
  width: 40px; height: 40px;
  border-radius: var(--radius-icon);
  border: 1px solid var(--border-color);
  background: var(--surface);
  display: grid; place-items: center;
  cursor: pointer;
  transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
  position: relative;
}
.rms-icon-btn svg {
  width: 18px; height: 18px;
  stroke: var(--text); fill: none; stroke-width: 2;
}
.rms-icon-btn:active { transform: scale(var(--press)); }

[data-theme="light"] .rms-icon-btn {
  box-shadow: var(--shadow-card-sm);
  border: none;
}
[data-theme="light"] .rms-icon-btn:active { box-shadow: var(--shadow-pressed); }

[data-theme="dark"] .rms-icon-btn {
  background: hsl(220 25% 8% / 0.7);
  backdrop-filter: blur(8px);
  box-shadow: 0 4px 12px hsl(var(--hue2) 50% 4%);
}

/* Logout dropdown */
.rms-logout-dd {
  position: absolute; top: calc(100% + 6px); right: 0;
  display: none;
  padding: 8px 14px;
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--text);
  font-size: 12px; font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
}
[data-theme="light"] .rms-logout-dd { box-shadow: var(--shadow-card); }
[data-theme="dark"] .rms-logout-dd {
  background: hsl(220 25% 4.8% / 0.95);
  backdrop-filter: blur(12px);
  border: 1px solid var(--border-color);
}
.rms-logout-dd.show { display: block; }
```

## 3.3 Bottom nav — SESSION-BASED (S144 правило)

> **🔒 ЗАКОН (S144):** Bottom-nav-ът се показва/скрива според **режима на влизане**, не според текущата страница. Това правило е финално — четеш и прилагаш.

### Правилото с 1 изречение

**Влязъл от Лесен → никъде нямаш 4 таба. Влязъл от Разширен → навсякъде имаш 4 таба.**

### Как се запомня

`$_SESSION['active_mode']` се сетва когато потребителят влиза в режим:

- **Simple home** (chat.php или life-board.php или `?mode=simple`) → `$_SESSION['active_mode'] = 'simple'`
- **Detailed home** (`?mode=detailed`) → `$_SESSION['active_mode'] = 'detailed'`
- Owner default (без override) → `'detailed'`
- Seller default → `'simple'`

### Как се ползва в partials/bottom-nav.php

```php
<?php if (($_SESSION['active_mode'] ?? 'simple') === 'detailed'): ?>
<nav class="rms-bottom-nav">
    [4 tabs]
</nav>
<?php endif; ?>
```

В Simple — bottom-nav изобщо не се рендерира. Вместо това chat-input-bar (`life-board.php` стил).

### 4 tabs структура (когато се показва, в Detailed)

ОТ `partials/bottom-nav.php` — НЕ пипай.

**4 tabs в ТОЧЕН ред:**

```php
$isAI    = in_array($rms_current_module, ['chat','simple','life-board','index'], true);
$isWh    = in_array($rms_current_module, ['warehouse','inventory','transfers','deliveries','suppliers','products'], true);
$isStats = in_array($rms_current_module, ['stats','finance'], true);
$isSale  = ($rms_current_module === 'sale');
```

| # | Tab | Label | Href | Active for modules |
|---|-----|-------|------|---------------------|
| 1 | 🤖 AI | "AI" | `chat.php` | chat, simple, life-board, index |
| 2 | 📦 Склад | "Склад" | `warehouse.php` | warehouse, inventory, transfers, deliveries, suppliers, products |
| 3 | 📊 Справки | "Справки" | `stats.php` | stats, finance |
| 4 | ⚡ Продажба | "Продажба" | `sale.php` | sale |

AI tab има animated SVG bars (audio waveform).

## 3.4 Bottom nav CSS (apply 1:1)

```css
.rms-bottom-nav {
  position: fixed; left: 12px; right: 12px; bottom: 12px;
  z-index: 50;
  height: 64px;
  display: grid; grid-template-columns: repeat(4, 1fr);
  border-radius: var(--radius);
  border: 1px solid var(--border-color);
  padding-bottom: env(safe-area-inset-bottom, 0);
  margin-bottom: env(safe-area-inset-bottom, 0);
}

[data-theme="light"] .rms-bottom-nav {
  background: var(--surface);
  box-shadow: var(--shadow-card);
  border: none;
}

[data-theme="dark"] .rms-bottom-nav {
  background:
    linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%),
    linear-gradient(45deg, hsl(var(--hue2) 50% 10% / .8), hsl(var(--hue2) 50% 10% / 0) 33%),
    linear-gradient(hsl(220 25% 4.8% / .9));
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow: var(--shadow-card);
}

.rms-nav-tab {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 3px;
  text-decoration: none;
  color: var(--text-muted);
  font-family: var(--font);
  font-size: 10px; font-weight: 700;
  cursor: pointer;
  position: relative;
  transition: color var(--dur) var(--ease);
}
.rms-nav-tab svg {
  width: 22px; height: 22px;
  stroke: currentColor; fill: none; stroke-width: 2;
  transition: transform var(--dur) var(--ease-spring);
}
.rms-nav-tab:active svg { transform: scale(0.85); }

.rms-nav-tab.active {
  color: var(--accent);
}
.rms-nav-tab.active svg {
  animation: navBounce 0.5s var(--ease-spring);
}
.rms-nav-tab.active::before {
  content: '';
  position: absolute; top: 6px; left: 50%; transform: translateX(-50%);
  width: 32px; height: 4px;
  background: var(--accent);
  border-radius: 999px;
  animation: navIndicator 0.4s var(--ease-spring);
}

[data-theme="dark"] .rms-nav-tab.active::before {
  box-shadow: 0 0 12px var(--accent);
}

.rms-nav-tab-label { display: block; }
```

## 3.5 Body padding for header + bottom nav

```css
body {
  padding-top: 56px;                                                  /* compensate sticky header */
  padding-bottom: calc(64px + 24px + env(safe-area-inset-bottom, 0)); /* compensate bottom nav */
}
```

---

# 🌌 ЧАСТ 4 — BACKGROUND SYSTEM

## 4.1 LIGHT background (default)

```css
[data-theme="light"] body {
  background: var(--bg-main);
}
```

Aurora blobs дават визуална глъб на иначе плоския фон.

## 4.2 DARK background (SACRED от life-board.php)

```css
[data-theme="dark"] body {
  background:
    radial-gradient(ellipse 800px 500px at 20% 10%,
      hsl(var(--hue1) 60% 35% / .22) 0%, transparent 60%),
    radial-gradient(ellipse 700px 500px at 85% 85%,
      hsl(var(--hue2) 60% 35% / .22) 0%, transparent 60%),
    linear-gradient(180deg, #0a0b14 0%, #050609 100%);
  background-attachment: fixed;
}
```

## 4.3 Aurora blobs (Effect #1 — both modes)

```html
<div class="aurora">
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
</div>
```

```css
.aurora {
  position: fixed;            /* fixed = visible on all pages while scrolling */
  inset: 0;
  overflow: hidden;
  pointer-events: none;
  z-index: var(--z-aurora);
}
.aurora-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(60px);
  opacity: var(--aurora-opacity);
  mix-blend-mode: var(--aurora-blend);  /* light: multiply | dark: plus-lighter */
  animation: auroraDrift 20s ease-in-out infinite;
}
.aurora-blob:nth-child(1) {
  width: 280px; height: 280px;
  background: hsl(var(--hue1), 80%, 60%);
  top: -60px; left: -80px;
  animation-delay: 0s;
}
.aurora-blob:nth-child(2) {
  width: 240px; height: 240px;
  background: hsl(var(--hue3), 70%, 60%);
  top: 35%; right: -100px;
  animation-delay: 4s;
}
.aurora-blob:nth-child(3) {
  width: 200px; height: 200px;
  background: hsl(var(--hue2), 80%, 60%);
  bottom: 80px; left: -50px;
  animation-delay: 8s;
}

@keyframes auroraDrift {
  0%, 100% { transform: translate(0,0) scale(1); }
  33% { transform: translate(30px,-25px) scale(1.1); }
  66% { transform: translate(-25px,35px) scale(0.95); }
}
```

**Use:** Aurora е GLOBAL фонов layer. Слагай го веднъж в shell, не във всеки модул.

---

# 💎 ЧАСТ 5 — GLASS COMPONENT (SACRED Neon Glass + Light convex)

## 5.1 Базова структура (continuity in двата режима)

```html
<div class="glass">
  <span class="shine"></span>
  <span class="shine shine-bottom"></span>
  <span class="glow"></span>
  <span class="glow glow-bottom"></span>

  <div style="position:relative; z-index:5">
    <!-- Цялото съдържание винаги с z-index:5+ -->
  </div>
</div>
```

**ЗАДЪЛЖИТЕЛНО (continuity):**
- ✅ Винаги 4 псевдо-span-а (shine + shine-bottom + glow + glow-bottom)
- ✅ Content винаги с `z-index: 5` или по-високо
- ✅ `.glass.sm` за по-малки карти (radius 14px)

> В **light mode** `.shine` и `.glow` стават `display: none` автоматично — но ВИНАГИ ги слагаш в HTML-а за consistency.

## 5.2 CSS — base (1:1 copy-paste)

```css
.glass {
  position: relative;
  border-radius: var(--radius);
  border: var(--border) solid var(--border-color);
  isolation: isolate;
}

.glass.sm { --radius: var(--radius-sm); }

.glass .shine, .glass .glow { --hue: var(--hue1); }
.glass .shine-bottom, .glass .glow-bottom {
  --hue: var(--hue2);
  --conic: 135deg;
}
```

## 5.3 LIGHT — neumorphism convex

```css
[data-theme="light"] .glass {
  background: var(--surface);
  box-shadow: var(--shadow-card);
  border: none;
}

/* Hide SACRED elements in light (plus-lighter не работи на светъл фон) */
[data-theme="light"] .glass .shine,
[data-theme="light"] .glass .glow { display: none; }
```

## 5.4 DARK — SACRED Neon Glass (1:1 от life-board.php)

```css
[data-theme="dark"] .glass {
  background:
    linear-gradient(235deg,
      hsl(var(--hue1) 50% 10% / .8),
      hsl(var(--hue1) 50% 10% / 0) 33%),
    linear-gradient(45deg,
      hsl(var(--hue2) 50% 10% / .8),
      hsl(var(--hue2) 50% 10% / 0) 33%),
    linear-gradient(hsl(220 25% 4.8% / .78));
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow: var(--shadow-card);
}

/* SACRED .shine — conic-gradient + mask-composite: subtract */
[data-theme="dark"] .glass .shine {
  pointer-events: none;
  border-radius: 0;
  border-top-right-radius: inherit;
  border-bottom-left-radius: inherit;
  border: 1px solid transparent;
  width: 75%; aspect-ratio: 1;
  display: block; position: absolute;
  right: calc(var(--border) * -1);
  top: calc(var(--border) * -1);
  z-index: var(--z-shine);
  background: conic-gradient(from var(--conic, -45deg) at center in oklch,
    transparent 12%,
    hsl(var(--hue), 80%, 60%),
    transparent 50%) border-box;
  mask:
    linear-gradient(transparent),
    linear-gradient(black);
  mask-clip: padding-box, border-box;
  mask-composite: subtract;
}

[data-theme="dark"] .glass .shine.shine-bottom {
  right: auto; top: auto;
  left: calc(var(--border) * -1);
  bottom: calc(var(--border) * -1);
}

/* SACRED .glow — SVG noise mask + plus-lighter blend */
[data-theme="dark"] .glass .glow {
  pointer-events: none;
  border-top-right-radius: calc(var(--radius) * 2.5);
  border-bottom-left-radius: calc(var(--radius) * 2.5);
  border: calc(var(--radius) * 1.25) solid transparent;
  inset: calc(var(--radius) * -2);
  width: 75%; aspect-ratio: 1;
  display: block; position: absolute;
  left: auto; bottom: auto;
  background: conic-gradient(from var(--conic, -45deg) at center in oklch,
    hsl(var(--hue), 80%, 60% / .5) 12%,
    transparent 50%);
  filter: blur(12px) saturate(1.25);
  mix-blend-mode: plus-lighter;
  z-index: var(--z-glow);
  opacity: 0.6;
}

[data-theme="dark"] .glass .glow.glow-bottom {
  inset: auto;
  left: calc(var(--radius) * -2);
  bottom: calc(var(--radius) * -2);
}
```

## 5.5 Когато използваш `.glass`

✅ **Cards:** lb-card, op-btn, top-row cells, studio-btn
✅ **Major UI:** modals, overlays, drawers
❌ **НЕ** на: малки бутони (lb-action), badges, pills, fb-btn, mic
❌ **НЕ** на: chat-input-bar (има специален CSS — виж Част 7)

---

# ✨ ЧАСТ 6 — 9 EFFECTS (Animation library)

Всички 9 ефекта работят И В ДВАТА РЕЖИМА с подходящи blend modes.

## Effect #1 — Aurora blobs ✅ (виж Част 4.3)

## Effect #2 — `.shine` (SACRED, само в dark — виж Част 5.4)

## Effect #3 — `.glow` (SACRED, само в dark — виж Част 5.4)

## Effect #4 — Animated conic border на pill

```css
.conic-ring {
  position: relative;
  overflow: hidden;
}
.conic-ring::before {
  content: ''; position: absolute; inset: -1px;
  border-radius: inherit;
  padding: 1.5px;
  background: conic-gradient(from 0deg,
    hsl(var(--hue1) 80% 60%),
    hsl(var(--hue2) 80% 60%),
    hsl(var(--hue3) 70% 60%),
    hsl(var(--hue1) 80% 60%));
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
  animation: conicSpin 3s linear infinite;
  opacity: 0.6;
  pointer-events: none;
}
```

**Where:** plan-badge, primary CTA buttons (with sub-spec), Life Board orb wrapper.

## Effect #5 — Conic orb (Life Board заглавие)

```html
<div class="lb-title-orb"></div>
```

```css
.lb-title-orb {
  width: 24px; height: 24px;
  border-radius: 50%;
  background: conic-gradient(from 0deg,
    hsl(var(--hue1) 80% 60%),
    hsl(var(--hue2) 80% 60%),
    hsl(var(--hue3) 70% 60%),
    hsl(var(--hue1) 80% 60%));
  position: relative;
  animation: orbSpin 5s linear infinite;
}
.lb-title-orb::before {
  content: ''; position: absolute; inset: -6px;
  border-radius: 50%;
  background: inherit;
  filter: blur(8px);
  opacity: 0.55;
  z-index: -1;
}
.lb-title-orb::after {
  content: ''; position: absolute; inset: 4px;
  border-radius: 50%;
  background: var(--bg-main);
}
```

## Effect #6 — Conic glow ring при card expand

```css
.lb-card.expanded::before {
  content: ''; position: absolute; inset: -1px;
  border-radius: var(--radius);
  padding: 2px;
  background: conic-gradient(from 0deg,
    var(--card-accent, var(--accent)),
    transparent 60%,
    var(--card-accent, var(--accent)));
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
  animation: conicSpin 4s linear infinite;
  opacity: 0.55;
  pointer-events: none;
  z-index: 1;
}

/* Per fundamental question */
.lb-card.q1 { --card-accent: var(--q1-loss); }
.lb-card.q2 { --card-accent: var(--q2-why-loss); }
.lb-card.q3 { --card-accent: var(--q3-gain); }
.lb-card.q4 { --card-accent: var(--q4-why-gain); }
.lb-card.q5 { --card-accent: var(--q5-order); }
.lb-card.q6 { --card-accent: var(--q6-no-order); }
```

## Effect #7 — Conic shimmer на primary CTA

```css
.btn-primary {
  position: relative;
  background: linear-gradient(135deg,
    hsl(var(--hue1) 80% 55%),
    hsl(var(--hue2) 80% 55%));
  color: white;
  overflow: hidden;
  border: none;
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.4);
}
[data-theme="light"] .btn-primary {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
}

.btn-primary::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg,
    transparent 70%,
    rgba(255,255,255,0.4) 85%,
    transparent 100%);
  animation: conicSpin 3s linear infinite;
}
.btn-primary > * { position: relative; z-index: 1; }
```

## Effect #8 — Soft glow halo (на op-btn hover)

```css
.op-btn::before {
  content: ''; position: absolute;
  width: 80%; height: 80%;
  border-radius: 50%;
  background: radial-gradient(circle,
    hsl(var(--hue1) 80% 60%) 0%,
    transparent 70%);
  filter: blur(20px);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  mix-blend-mode: var(--aurora-blend);
}
.op-btn:hover::before { opacity: 0.4; }
```

## Effect #9 — Iridescent shimmer на AI Brain

```css
.ai-brain-pill {
  position: relative;
  overflow: hidden;
}
/* Layer 1: rotating conic */
.ai-brain-pill::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg,
    transparent,
    rgba(255,255,255,0.25),
    transparent);
  animation: conicSpin 4s linear infinite;
}
/* Layer 2: sweeping linear */
.ai-brain-pill::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(105deg,
    transparent 30%,
    rgba(255,255,255,0.35) 50%,
    transparent 70%);
  animation: shimmerSlide 3.5s ease-in-out infinite;
}
.ai-brain-pill > * { position: relative; z-index: 1; }
```

---

# 🎬 ЧАСТ 7 — ANIMATION LIBRARY

```css
/* Entry animations */
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}
@keyframes slideInRight {
  from { opacity: 0; transform: translateX(20px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* Loop animations */
@keyframes orbSpin   { to { transform: rotate(360deg); } }
@keyframes conicSpin { to { transform: rotate(360deg); } }
@keyframes shimmerSlide {
  0%, 100% { transform: translateX(-100%); }
  50%      { transform: translateX(100%); }
}
@keyframes auroraDrift {
  0%, 100% { transform: translate(0,0) scale(1); }
  33%      { transform: translate(30px,-25px) scale(1.1); }
  66%      { transform: translate(-25px,35px) scale(0.95); }
}

/* Interaction animations */
@keyframes navBounce {
  0%, 100% { transform: scale(1); }
  40%      { transform: scale(1.2) translateY(-2px); }
}
@keyframes navIndicator {
  from { width: 0;    opacity: 0; }
  to   { width: 32px; opacity: 1; }
}
@keyframes pulse {
  0%, 100% { transform: scale(1);   opacity: 1;   }
  50%      { transform: scale(1.4); opacity: 0.4; }
}
@keyframes typingDot {
  0%, 60%, 100% { transform: scale(1);   opacity: 0.5; }
  30%           { transform: scale(1.4); opacity: 1;   }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation: none !important;
    transition: none !important;
  }
}
```

## Animation timing reference

| Use | Animation | Duration | Easing |
|-----|-----------|----------|--------|
| Card entry | `fadeInUp` | 0.6s | `ease-spring` |
| Stagger entry | `fadeInUp` + delay 0.1s | 0.6s | `ease-spring` |
| Press feedback | `scale()` | 150ms | `ease` |
| Hover lift | `translateY(-2px)` | 250ms | `ease` |
| Card expand | `max-height` | 350ms | `ease` |
| Tab switch | `navBounce` + `navIndicator` | 500ms / 400ms | `ease-spring` |
| Conic ring | `conicSpin` | 3s loop | linear |
| Orb spin | `orbSpin` | 5s loop | linear |
| Shimmer | `shimmerSlide` | 3.5s loop | ease-in-out |
| Aurora drift | `auroraDrift` | 20s loop | ease-in-out |
| Pulse (live indicators) | `pulse` | 2s loop | ease-in-out |
| Typing dot | `typingDot` | 1.4s loop, stagger 0.2s | linear |

---

# 🔤 ЧАСТ 8 — TYPOGRAPHY

## 8.1 Font import (continuity)

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
```

## 8.2 Font roles

| Use | Font | Weight | Size scale |
|-----|------|--------|------------|
| **All UI** (default) | Montserrat | 400-900 | 9-34px |
| **Numbers, badges, labels, mono** | DM Mono | 400-500 | 9-13px |

## 8.3 Type scale

```css
--font-size-9:   9px;     /* badges, brand */
--font-size-10:  10px;    /* meta, sub-labels */
--font-size-11:  11px;    /* secondary labels, lb-fb-label */
--font-size-12:  12px;    /* small body, lb-action, mode-toggle */
--font-size-13:  13px;    /* lb-title items, ai-brain-msg */
--font-size-14:  14px;    /* section titles, lb-title-text */
--font-size-15:  15px;    /* head card titles */
--font-size-18:  18px;    /* page titles, lb-emoji */
--font-size-22:  22px;    /* small stat */
--font-size-26:  26px;    /* weather temp */
--font-size-30:  30px;    /* main stat (cell-num) */
--font-size-34:  34px;    /* hero stat */
```

## 8.4 Font usage rules

- **Brand** (`RUNMYSTORE.AI`): Montserrat 900, letter-spacing 0.06em, uppercase, gradient text-clip
- **Headings** (`.cell-num`, `.lb-title-text`): Montserrat 700-800, letter-spacing -0.04em
- **Body**: Montserrat 500-600
- **Labels** (uppercase): Montserrat 800, letter-spacing 0.08em
- **Numbers, badges**: DM Mono 500, letter-spacing 0
- **Time/clock**: DM Mono 600

```css
/* Standard heading (gradient) */
.heading-gradient {
  background: linear-gradient(135deg, var(--text), var(--accent));
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
```

---

# 🧩 ЧАСТ 9 — COMPONENT RECIPES

## 9.1 Top stat cell (Днес / Времето)

```html
<div class="cell glass q3">
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <span class="glow"></span><span class="glow glow-bottom"></span>
  <div class="cell-content">
    <div class="cell-label">Днес · ENI</div>
    <div class="cell-numrow">
      <span class="cell-num">247</span>
      <span class="cell-cur">€</span>
      <span class="cell-pct">+18%</span>
    </div>
    <div class="cell-meta">8 продажби · 89 печалба</div>
  </div>
</div>
```

```css
.cell {
  padding: 14px;
  position: relative;
  animation: fadeInUp 0.6s var(--ease-spring) both;
  overflow: hidden;
}
.cell-content { position: relative; z-index: 5; }
.cell-label {
  font-family: var(--font-mono);
  font-size: 10px; font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 6px;
}
.cell-numrow { display: flex; align-items: baseline; gap: 4px; }
.cell-num {
  font-family: var(--font);
  font-size: 30px; font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1;
  background: linear-gradient(135deg, var(--text) 0%, var(--accent) 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
.cell-cur { font-size: 13px; font-weight: 600; color: var(--text-muted); }
.cell-pct {
  margin-left: auto;
  padding: 3px 8px;
  border-radius: var(--radius-pill);
  font-family: var(--font-mono);
  font-size: 11px; font-weight: 700;
  color: var(--success);
  border: 1px solid hsl(145 70% 50% / 0.3);
  background: hsl(145 70% 50% / 0.1);
}
.cell-pct.neg { color: var(--danger); border-color: hsl(0 85% 50% / 0.3); background: hsl(0 85% 50% / 0.1); }
.cell-meta { font-size: 11px; color: var(--text-muted); margin-top: 6px; font-weight: 500; }
```

## 9.2 LB-card (Life Board card)

```html
<div class="lb-card glass q1" data-card>
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <span class="glow"></span><span class="glow glow-bottom"></span>

  <div class="lb-collapsed">
    <span class="lb-emoji">🔴</span>
    <div class="lb-collapsed-content">
      <span class="lb-fq-tag-mini">Какво губиш</span>
      <span class="lb-collapsed-title">Nike Air Max 42 — 60 дни. €180 замразени.</span>
    </div>
    <button class="lb-expand-btn">[chevron]</button>
  </div>
  <div class="lb-expanded">
    <div class="lb-body">[detail text]</div>
    <div class="lb-actions">
      <button class="lb-action">Защо?</button>
      <button class="lb-action">Покажи</button>
      <button class="lb-action btn-primary"><span>Намали →</span></button>
    </div>
    <div class="lb-feedback">
      <span class="lb-fb-label">USEFUL?</span>
      <button class="lb-fb-btn">👍</button>
      <button class="lb-fb-btn">👎</button>
      <button class="lb-fb-btn">🤔</button>
    </div>
  </div>
</div>
```

```css
.lb-card {
  position: relative;
  border-radius: var(--radius);
  padding: 14px;
  margin-bottom: 12px;
  cursor: pointer;
  isolation: isolate;
  animation: fadeInUp 0.6s var(--ease-spring) both;
  transition: box-shadow 0.3s ease;
}
/* See Part 5 for theme-specific .glass styles */

/* Expand glow ring (Effect #6) */
.lb-card.expanded::before {
  content: ''; position: absolute; inset: -1px;
  border-radius: var(--radius);
  padding: 2px;
  background: conic-gradient(from 0deg,
    var(--card-accent, var(--accent)),
    transparent 60%,
    var(--card-accent, var(--accent)));
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
  animation: conicSpin 4s linear infinite;
  opacity: 0.55;
  pointer-events: none;
  z-index: 1;
}

.lb-collapsed {
  display: flex; align-items: center; gap: 12px;
  position: relative; z-index: 5;
}
.lb-emoji {
  width: 40px; height: 40px;
  border-radius: 50%;
  display: grid; place-items: center;
  font-size: 18px; flex-shrink: 0;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .lb-emoji {
  background: var(--surface);
  box-shadow: var(--shadow-pressed);
  border: none;
}
[data-theme="dark"] .lb-emoji {
  background: hsl(220 25% 4%);
}

.lb-collapsed-content { flex: 1; min-width: 0; }
.lb-fq-tag-mini {
  display: inline-block;
  font-family: var(--font-mono);
  font-size: 9px; font-weight: 800;
  color: var(--card-accent);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 2px;
}
.lb-collapsed-title {
  display: block;
  font-size: 13px; font-weight: 600;
  line-height: 1.3;
}

.lb-expand-btn {
  width: 28px; height: 28px;
  border-radius: 50%;
  border: 1px solid var(--border-color);
  display: grid; place-items: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: transform 0.3s ease, box-shadow var(--dur) var(--ease);
}
[data-theme="light"] .lb-expand-btn {
  background: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: none;
}
[data-theme="dark"] .lb-expand-btn {
  background: hsl(220 25% 8%);
}
.lb-expand-btn svg {
  width: 12px; height: 12px;
  stroke: var(--text-muted); fill: none; stroke-width: 2.5;
}
.lb-card.expanded .lb-expand-btn { transform: rotate(180deg); }
[data-theme="light"] .lb-card.expanded .lb-expand-btn { box-shadow: var(--shadow-pressed); }
.lb-card.expanded .lb-expand-btn svg { stroke: var(--accent); }

.lb-expanded {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease, padding-top 0.3s ease;
  position: relative; z-index: 5;
}
.lb-card.expanded .lb-expanded {
  max-height: 400px;
  padding-top: 12px;
}

.lb-body {
  font-size: 12px; line-height: 1.5;
  color: var(--text-muted);
  padding: 10px;
  border-radius: var(--radius-sm);
  margin-bottom: 12px;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .lb-body {
  background: var(--surface);
  box-shadow: var(--shadow-pressed);
  border: none;
}
[data-theme="dark"] .lb-body {
  background: hsl(220 25% 4%);
}
```

## 9.3 LB-action button

```css
.lb-action {
  flex: 1; min-width: 60px;
  padding: 8px 12px;
  color: var(--text);
  border-radius: var(--radius-sm);
  font-family: var(--font);
  font-size: 11px; font-weight: 700;
  cursor: pointer;
  text-decoration: none;
  text-align: center;
  border: 1px solid var(--border-color);
  transition: all var(--dur) var(--ease);
}
[data-theme="light"] .lb-action {
  background: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: none;
}
[data-theme="light"] .lb-action:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-action {
  background: hsl(220 25% 8%);
}
.lb-action:hover { color: var(--accent); }

/* Apply Effect #7 to .btn-primary (used inside .lb-action.btn-primary) */
.lb-action.btn-primary {
  border: none;
  color: white;
}
.lb-action.btn-primary:hover { color: white; }
```

## 9.4 LB-feedback buttons (👍 👎 🤔)

```css
.lb-feedback {
  display: flex; align-items: center; gap: 8px;
  padding-top: 8px;
  border-top: 1px solid var(--border-color);
}
[data-theme="light"] .lb-feedback {
  border-top: 1px solid rgba(163,177,198,0.3);
}
.lb-fb-label {
  font-family: var(--font-mono);
  font-size: 10px; font-weight: 700;
  color: var(--text-muted);
}
.lb-fb-btn {
  width: 30px; height: 30px;
  border-radius: 50%;
  border: 1px solid var(--border-color);
  display: grid; place-items: center;
  font-size: 13px;
  cursor: pointer;
  transition: all var(--dur) var(--ease);
}
[data-theme="light"] .lb-fb-btn {
  background: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: none;
}
[data-theme="dark"] .lb-fb-btn {
  background: hsl(220 25% 8%);
}
.lb-fb-btn:active,
.lb-fb-btn.selected { transform: scale(0.92); }
[data-theme="light"] .lb-fb-btn:active,
[data-theme="light"] .lb-fb-btn.selected { box-shadow: var(--shadow-pressed); }
```

## 9.5 Op-button (4 големи operational бутона)

```html
<a href="sale.php" class="op-btn">
  <div class="op-icon"><svg>...</svg></div>
  <div class="op-label">Продай</div>
</a>
```

```css
.ops-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 14px;
}
.op-btn {
  position: relative;
  border-radius: var(--radius);
  padding: 18px 14px;
  aspect-ratio: 1.6 / 1;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 8px;
  text-decoration: none;
  color: var(--text);
  cursor: pointer;
  border: 1px solid var(--border-color);
  isolation: isolate;
  transition: all var(--dur) var(--ease);
  animation: fadeInUp 0.6s var(--ease-spring) both;
  overflow: hidden;
}
[data-theme="light"] .op-btn {
  background: var(--surface);
  box-shadow: var(--shadow-card);
  border: none;
}
[data-theme="dark"] .op-btn {
  background:
    linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%),
    linear-gradient(45deg, hsl(var(--hue2) 50% 10% / .8), hsl(var(--hue2) 50% 10% / 0) 33%),
    linear-gradient(hsl(220 25% 4.8% / .78));
  box-shadow: var(--shadow-card);
}

/* Stagger delay (apply with --i custom property OR :nth-child) */
.op-btn:nth-child(1) { animation-delay: 0.7s; }
.op-btn:nth-child(2) { animation-delay: 0.78s; }
.op-btn:nth-child(3) { animation-delay: 0.86s; }
.op-btn:nth-child(4) { animation-delay: 0.94s; }

/* Effect #8: hover halo */
.op-btn::before {
  content: ''; position: absolute;
  width: 80%; height: 80%;
  border-radius: 50%;
  background: radial-gradient(circle, hsl(var(--hue1) 80% 60%) 0%, transparent 70%);
  filter: blur(20px);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
  mix-blend-mode: var(--aurora-blend);
}
.op-btn:hover::before { opacity: 0.4; }
.op-btn:active { transform: scale(var(--press)); }
[data-theme="light"] .op-btn:active { box-shadow: var(--shadow-pressed); }

.op-icon {
  width: 48px; height: 48px;
  border-radius: 50%;
  display: grid; place-items: center;
  position: relative; z-index: 1;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .op-icon {
  background: var(--surface);
  box-shadow: var(--shadow-pressed);
  border: none;
}
[data-theme="dark"] .op-icon {
  background: hsl(220 25% 4%);
}
.op-icon svg {
  width: 22px; height: 22px;
  stroke: var(--accent); fill: none; stroke-width: 2;
}
.op-label {
  font-size: 13px; font-weight: 800;
  letter-spacing: -0.01em;
  position: relative; z-index: 1;
}
```

## 9.6 AI Brain pill

```html
<div class="ai-brain-pill">
  <svg>[brain icon]</svg>
  <div class="ai-brain-text">
    <div class="ai-brain-label">AI BRAIN · ACTIVE</div>
    <div class="ai-brain-msg">Записан си 4 дни. Продължи →</div>
  </div>
</div>
```

```css
.ai-brain-pill {
  position: relative;
  background: linear-gradient(135deg,
    hsl(var(--hue1) 80% 55%),
    hsl(var(--hue2) 80% 55%));
  color: white;
  border-radius: var(--radius-sm);
  padding: 14px 16px;
  margin-bottom: 12px;
  display: flex; align-items: center; gap: 10px;
  box-shadow: 0 8px 24px hsl(var(--hue1) 80% 40% / 0.4);
  cursor: pointer;
  overflow: hidden;
  animation: fadeInUp 0.6s var(--ease-spring) both;
}
[data-theme="light"] .ai-brain-pill {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: var(--shadow-card), 0 8px 24px oklch(0.62 0.22 285 / 0.3);
}

/* Effect #9 — двоен shimmer */
.ai-brain-pill::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg,
    transparent,
    rgba(255,255,255,0.25),
    transparent);
  animation: conicSpin 4s linear infinite;
}
.ai-brain-pill::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(105deg,
    transparent 30%,
    rgba(255,255,255,0.35) 50%,
    transparent 70%);
  animation: shimmerSlide 3.5s ease-in-out infinite;
}
.ai-brain-pill > * { position: relative; z-index: 1; }

.ai-brain-pill svg {
  width: 20px; height: 20px;
  stroke: white; fill: none; stroke-width: 2;
  flex-shrink: 0;
}
.ai-brain-text { flex: 1; }
.ai-brain-label {
  font-family: var(--font-mono);
  font-size: 10px; font-weight: 800;
  opacity: 0.9;
  letter-spacing: 0.08em;
}
.ai-brain-msg {
  font-size: 13px; font-weight: 700;
  margin-top: 2px;
}
```

## 9.7 Studio button (AI Studio)

```html
<a href="ai-studio.php" class="studio-btn">
  <span class="studio-icon">[star svg]</span>
  <span class="studio-text">
    <span class="studio-label">AI Studio</span>
    <span class="studio-sub">3 ЧАКАТ</span>
  </span>
  <span class="studio-badge">3</span>
</a>
```

```css
.studio-btn {
  position: relative;
  border-radius: var(--radius-sm);
  padding: 14px 16px;
  display: flex; align-items: center; gap: 12px;
  text-decoration: none;
  color: var(--text);
  cursor: pointer;
  border: 1px solid var(--border-color);
  isolation: isolate;
  animation: fadeInUp 0.6s var(--ease-spring) both;
  transition: all var(--dur) var(--ease);
}
[data-theme="light"] .studio-btn {
  background: var(--surface);
  box-shadow: var(--shadow-card);
  border: none;
}
[data-theme="dark"] .studio-btn {
  background:
    linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%),
    linear-gradient(hsl(220 25% 4.8% / .78));
  backdrop-filter: blur(12px);
  box-shadow: var(--shadow-card);
}
[data-theme="light"] .studio-btn:active { box-shadow: var(--shadow-pressed); }

.studio-icon {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg,
    hsl(var(--hue1) 80% 55%),
    hsl(var(--hue2) 80% 55%));
  display: grid; place-items: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 40% / 0.5);
}
[data-theme="light"] .studio-icon {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
}
.studio-icon svg {
  width: 18px; height: 18px;
  stroke: white; fill: none; stroke-width: 2;
}
.studio-text { flex: 1; }
.studio-label { font-size: 13px; font-weight: 800; }
.studio-sub {
  display: block;
  font-family: var(--font-mono);
  font-size: 10px; font-weight: 700;
  color: var(--text-muted);
  letter-spacing: 0.06em;
  margin-top: 1px;
}
.studio-badge {
  background: linear-gradient(135deg, var(--danger), hsl(0 85% 50%));
  color: white;
  width: 28px; height: 28px;
  border-radius: 50%;
  display: grid; place-items: center;
  font-family: var(--font-mono);
  font-size: 11px; font-weight: 800;
  box-shadow: 0 4px 10px hsl(0 85% 50% / 0.4);
}
```

## 9.8 Chat input bar

```html
<div class="chat-input-bar">
  <input type="text" placeholder="Попитай AI…">
  <button class="chat-mic-btn"><svg>[mic]</svg></button>
</div>
```

```css
.chat-input-bar {
  position: fixed;
  left: 12px; right: 12px;
  bottom: calc(64px + 24px + env(safe-area-inset-bottom, 0));    /* над bottom-nav */
  border-radius: var(--radius-pill);
  padding: 8px 8px 8px 18px;
  display: flex; align-items: center; gap: 10px;
  z-index: var(--z-overlay);
  border: 1px solid var(--border-color);
}
[data-theme="light"] .chat-input-bar {
  background: var(--surface);
  box-shadow: var(--shadow-card);
  border: none;
}
[data-theme="dark"] .chat-input-bar {
  background:
    linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%),
    linear-gradient(hsl(220 25% 4.8% / .9));
  backdrop-filter: blur(12px);
  box-shadow: 0 8px 24px hsl(var(--hue2) 50% 4%);
}
.chat-input-bar input {
  flex: 1;
  background: transparent;
  border: none; outline: none;
  color: var(--text);
  font-family: var(--font);
  font-size: 13px; font-weight: 500;
}
.chat-input-bar input::placeholder { color: var(--text-muted); }

.chat-mic-btn {
  width: 38px; height: 38px;
  border-radius: 50%;
  background: linear-gradient(135deg,
    hsl(var(--hue1) 80% 55%),
    hsl(var(--hue2) 80% 55%));
  border: none;
  display: grid; place-items: center;
  cursor: pointer;
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 40% / 0.5);
}
[data-theme="light"] .chat-mic-btn {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
}
.chat-mic-btn svg {
  width: 18px; height: 18px;
  stroke: white; fill: none; stroke-width: 2;
}
```

## 9.9 Store chip (header за магазин)

```html
<div class="store-chip">
  <div class="store-avatar">E</div>
  <div class="store-name">ENI<small>Витоша 25</small></div>
</div>
```

```css
.store-chip {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 14px 6px 6px;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border-color);
  position: relative;
  z-index: 2;
}
[data-theme="light"] .store-chip {
  background: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: none;
}
[data-theme="dark"] .store-chip {
  background: hsl(220, 25%, 8% / 0.7);
  backdrop-filter: blur(8px);
  box-shadow: 0 4px 12px hsl(var(--hue2) 50% 4%);
}
.store-avatar {
  width: 28px; height: 28px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  color: white;
  display: grid; place-items: center;
  font-family: var(--font);
  font-weight: 700; font-size: 12px;
  box-shadow: inset 2px 2px 4px rgba(0,0,0,0.2);
}
.store-name { font-size: 12px; font-weight: 700; line-height: 1.1; }
.store-name small {
  display: block;
  font-size: 10px;
  color: var(--text-muted);
  margin-top: 1px;
  font-weight: 500;
}
```

## 9.10 Mode toggle (Подробен / Simple)

```html
<a href="chat.php" class="lb-mode-toggle">
  Подробен <svg>[chevron-right]</svg>
</a>
```

```css
.lb-mode-toggle {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 8px 14px;
  border-radius: var(--radius-pill);
  color: var(--text-muted);
  font-size: 11px; font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all var(--dur) var(--ease);
  border: 1px solid var(--border-color);
}
[data-theme="light"] .lb-mode-toggle {
  background: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: none;
}
[data-theme="light"] .lb-mode-toggle:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-mode-toggle {
  background: hsl(220 25% 8% / 0.7);
}
.lb-mode-toggle:hover { color: var(--accent); }
.lb-mode-toggle svg {
  width: 12px; height: 12px;
  stroke: currentColor; fill: none; stroke-width: 2.5;
}
```

## 9.11 Inputs (textarea, select, text input)

```css
.input,
input[type="text"],
input[type="email"],
input[type="password"],
textarea {
  width: 100%;
  padding: 14px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-color);
  font-family: var(--font);
  font-size: 13px; font-weight: 500;
  color: var(--text);
  outline: none;
  transition: box-shadow var(--dur) var(--ease);
}
[data-theme="light"] .input,
[data-theme="light"] input[type="text"],
[data-theme="light"] textarea {
  background: var(--surface);
  box-shadow: var(--shadow-pressed);
  border: none;
}
[data-theme="light"] .input:focus,
[data-theme="light"] input[type="text"]:focus,
[data-theme="light"] textarea:focus {
  box-shadow:
    var(--shadow-pressed),
    0 0 0 3px hsl(var(--hue1) 80% 60% / 0.2);
}
[data-theme="dark"] .input,
[data-theme="dark"] input[type="text"],
[data-theme="dark"] textarea {
  background: hsl(220 25% 4%);
}
[data-theme="dark"] .input:focus,
[data-theme="dark"] input[type="text"]:focus,
[data-theme="dark"] textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px hsl(var(--hue1) 80% 60% / 0.2);
}
::placeholder { color: var(--text-muted); }
```

## 9.12 Toast / notification

```css
.toast {
  position: fixed;
  top: calc(56px + 12px + env(safe-area-inset-top, 0));
  left: 50%; transform: translateX(-50%);
  z-index: var(--z-overlay);
  padding: 12px 18px;
  border-radius: var(--radius-pill);
  font-family: var(--font);
  font-size: 12px; font-weight: 700;
  color: white;
  display: flex; align-items: center; gap: 8px;
  animation: fadeIn 0.35s var(--ease-spring);
  background: linear-gradient(135deg,
    hsl(var(--hue1) 80% 55%),
    hsl(var(--hue2) 80% 55%));
  box-shadow: 0 8px 24px hsl(var(--hue1) 80% 40% / 0.4);
}
.toast.success { background: linear-gradient(135deg, hsl(145 70% 50%), hsl(155 70% 45%)); }
.toast.danger  { background: linear-gradient(135deg, hsl(0 85% 55%),  hsl(10 85% 50%)); }
.toast.warning { background: linear-gradient(135deg, hsl(38 90% 55%), hsl(28 90% 50%)); }
```

## 9.13 Modal / overlay

```css
.modal-backdrop {
  position: fixed; inset: 0;
  z-index: var(--z-modal);
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  animation: fadeIn 0.25s var(--ease);
}
.modal {
  position: fixed; left: 12px; right: 12px;
  bottom: 12px;
  z-index: calc(var(--z-modal) + 1);
  max-height: 80vh;
  border-radius: var(--radius);
  padding: 18px;
  overflow-y: auto;
  animation: fadeInUp 0.35s var(--ease-spring);
}
[data-theme="light"] .modal {
  background: var(--surface);
  box-shadow: var(--shadow-card);
}
[data-theme="dark"] .modal {
  background:
    linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%),
    linear-gradient(hsl(220 25% 4.8% / .95));
  backdrop-filter: blur(20px);
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-card);
}
```

---

# 🎨 ЧАСТ 10 — 6 FUNDAMENTAL QUESTIONS COLOR SEMANTICS

## 10.1 Семантика

| Code | Question | Hue | Light hex (oklch) | Dark hex (hsl) |
|------|----------|-----|-------------------|----------------|
| q1 | Какво губиш | red 25 | `oklch(0.65 0.22 25)` | `hsl(0 85% 60%)` |
| q2 | От какво губиш | violet 305 | `oklch(0.65 0.25 305)` | `hsl(280 70% 65%)` |
| q3 | Какво печелиш | green 155 | `oklch(0.68 0.18 155)` | `hsl(145 70% 55%)` |
| q4 | От какво печелиш | teal 195 | `oklch(0.72 0.18 195)` | `hsl(175 70% 55%)` |
| q5 | Поръчай | amber 70 | `oklch(0.72 0.18 70)` | `hsl(38 90% 60%)` |
| q6 | НЕ поръчвай | gray 220 | `oklch(0.62 0.05 220)` | `hsl(220 10% 60%)` |

## 10.2 Apply to LB cards

```html
<div class="lb-card glass q1">[loss content]</div>
<div class="lb-card glass q2">[why-loss content]</div>
<div class="lb-card glass q3">[gain content]</div>
<div class="lb-card glass q4">[why-gain content]</div>
<div class="lb-card glass q5">[order content]</div>
<div class="lb-card glass q6">[no-order content]</div>
```

```css
.lb-card.q1 { --card-accent: var(--q1-loss); }
.lb-card.q2 { --card-accent: var(--q2-why-loss); }
.lb-card.q3 { --card-accent: var(--q3-gain); }
.lb-card.q4 { --card-accent: var(--q4-why-gain); }
.lb-card.q5 { --card-accent: var(--q5-order); }
.lb-card.q6 { --card-accent: var(--q6-no-order); }

/* fq-tag-mini автоматично взема цвета през --card-accent */
```

## 10.3 Priority rules (от bizes logic)

- **Loss > Gain** → q1/q2 cards се показват първи
- **Anti-Order > Order** → q6 се показва преди q5
- **Max 4 cards** в Life Board (5+ → "Виж всички N →")

---

# 📏 ЧАСТ 11 — SPACING / SIZING

## 11.1 Padding scale

```
--space-2:   2px
--space-4:   4px
--space-6:   6px
--space-8:   8px
--space-10:  10px
--space-12:  12px
--space-14:  14px
--space-16:  16px
--space-20:  20px
--space-24:  24px
```

Primary use: 4 / 8 / 12 / 14 / 16 / 24.

## 11.2 Component dimensions

| Component | Width / Height | Padding | Radius |
|-----------|----------------|---------|--------|
| `.rms-header` | 100% / 56px | 0 16px | none |
| `.rms-icon-btn` | 40px / 40px | grid center | 50% |
| `.rms-plan-badge` | auto / auto | 5px 12px | 999px |
| `.rms-bottom-nav` | left/right 12px / 64px | grid 4 cols | 22px |
| `.rms-nav-tab` | flex / 64px | grid center | inherit |
| `.glass` (default) | varies | 14px | 22px |
| `.glass.sm` | varies | 10px-14px | 14px |
| `.cell` | varies | 14px | 22px |
| `.cell-num` | auto | - | - |
| `.lb-card` | full | 14px | 22px |
| `.lb-emoji` | 40px / 40px | grid center | 50% |
| `.lb-expand-btn` | 28px / 28px | grid center | 50% |
| `.lb-action` | flex 1 / auto | 8px 12px | 14px |
| `.lb-fb-btn` | 30px / 30px | grid center | 50% |
| `.op-btn` | aspect 1.6:1 | 18px 14px | 22px |
| `.op-icon` | 48px / 48px | grid center | 50% |
| `.ai-brain-pill` | full / auto | 14px 16px | 14px |
| `.studio-btn` | full / auto | 14px 16px | 14px |
| `.studio-icon` | 36px / 36px | grid center | 50% |
| `.studio-badge` | 28px / 28px | grid center | 50% |
| `.chat-input-bar` | left/right 12px / auto | 8px 8px 8px 18px | 999px |
| `.chat-mic-btn` | 38px / 38px | grid center | 50% |
| `.store-chip` | auto | 6px 14px 6px 6px | 999px |
| `.store-avatar` | 28px / 28px | grid center | 50% |
| `.lb-title-orb` | 24px / 24px | - | 50% |

## 11.3 Layout grids

```css
/* Top stat row (Днес + Времето) */
.top-row {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 12px;
  margin-bottom: 18px;
}

/* Ops grid (4 големи бутона) */
.ops-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 14px;
}

/* App content max-width (mobile-first) */
.app {
  max-width: 480px;
  margin: 0 auto;
  padding: 8px 16px 100px;
}
```

## 11.4 Safe-area-insets

```css
.rms-header {
  padding-top: env(safe-area-inset-top, 0);
  padding-left: max(16px, env(safe-area-inset-left, 0));
  padding-right: max(16px, env(safe-area-inset-right, 0));
}
.rms-bottom-nav {
  margin-bottom: env(safe-area-inset-bottom, 0);
}
.chat-input-bar {
  bottom: calc(64px + 24px + env(safe-area-inset-bottom, 0));
}
body {
  padding-bottom: calc(64px + 24px + env(safe-area-inset-bottom, 0));
}
```

---

# 🔌 ЧАСТ 12 — THEME TOGGLE LOGIC

## 12.1 Default (LIGHT)

```html
<!-- При първо влизане НЯМА data-theme attribute → light е default -->
<html lang="bg">
<head>
  <!-- ... -->
</head>
<body>
  <!-- ... -->
</body>
</html>
```

```css
:root:not([data-theme]),
:root[data-theme="light"] {
  /* light tokens (виж Част 2.2) */
}
```

## 12.2 JavaScript за toggle

```javascript
// shell-scripts.php
function rmsToggleTheme() {
  const html = document.documentElement;
  const current = html.dataset.theme || 'light';
  const next = current === 'light' ? 'dark' : 'light';
  html.dataset.theme = next;

  // Persist в cookie/localStorage
  document.cookie = `rms_theme=${next};path=/;max-age=31536000;SameSite=Lax`;
  localStorage.setItem('rms_theme', next);

  // Toggle iconите в header
  document.getElementById('themeIconSun').style.display = next === 'dark' ? 'block' : 'none';
  document.getElementById('themeIconMoon').style.display = next === 'dark' ? 'none' : 'block';

  // Server-side persistence (за връзка между сесиите)
  fetch('/api/theme.php', {
    method: 'POST',
    body: JSON.stringify({ theme: next })
  }).catch(() => {/* silent */});
}

// При load: възстановяване на запазената тема
(function rmsInitTheme() {
  const saved = localStorage.getItem('rms_theme') ||
                document.cookie.match(/rms_theme=(\w+)/)?.[1] ||
                'light';
  document.documentElement.dataset.theme = saved;
  document.getElementById('themeIconSun').style.display = saved === 'dark' ? 'block' : 'none';
  document.getElementById('themeIconMoon').style.display = saved === 'dark' ? 'none' : 'block';
})();
```

## 12.3 Server-side rendering

```php
// partials/shell-init.php
$theme = $_COOKIE['rms_theme'] ?? 'light';
if (!in_array($theme, ['light', 'dark'], true)) $theme = 'light';
$rms_theme = $theme;

// в HTML:
<html lang="bg" data-theme="<?= htmlspecialchars($rms_theme) ?>">
```

---

# 🚫 ЧАСТ 13 — FORBIDDEN PATTERNS

**НИКОГА в RunMyStore модул:**

| ❌ Никога | ✅ Вместо |
|-----------|-----------|
| Хардкоднат hex цвят `#6366f1` | `hsl(var(--hue1) 80% 60%)` или `var(--accent)` |
| `border-radius: 8px` | `var(--radius-sm)` (14px) или `var(--radius)` (22px) |
| `font-family: 'Inter'` | `var(--font)` (Montserrat) |
| `box-shadow: 0 2px 4px rgba(0,0,0,.1)` | `var(--shadow-card)` или `var(--shadow-card-sm)` |
| `transition: all .3s` | `transition: <prop> var(--dur) var(--ease)` |
| Square corners на бутони | `var(--radius-pill)` (999px) ИЛИ `var(--radius-sm)` (14px) |
| Solid color бутон без shadow | Винаги `box-shadow` (light) или `linear-gradient` (dark) |
| Native `<select>` | Custom styled dropdown |
| Bootstrap / Tailwind / framework class | Vanilla CSS |
| `border: none` без `outline: none` | Ako премахваш борда — премахни и outline-а |
| `cursor: default` на clickable елементи | `cursor: pointer` |
| Шрифт различен от Montserrat | Само Montserrat (UI) и DM Mono (numbers) |
| Цвят извън hue system | Само h1=255, h2=222, h3=180 + 6-те q-цвята |
| Хардкоднат "лв" / "€" / "BGN" | `priceFormat($amount, $tenant)` |
| Хардкоднат BG текст | `t('key')` или `$tenant['lang']` check |
| "Gemini" / "fal.ai" / "Anthropic" в UI | Винаги "AI" |
| Native keyboard в numpad/sale | Custom numpad |
| `localStorage` за business state | Server-side state (БД) |
| `prefers-color-scheme` auto-detect | Manual toggle (rmsToggleTheme()) |
| `transitions` без easing | `var(--ease)` или `var(--ease-spring)` |

---

# ✅ ЧАСТ 14 — ADOPTION CHECKLIST

Преди commit на нов или модифициран модул, ВСИЧКИ ✅:

## 14.1 Header / Bottom nav
- [ ] Включен `partials/header.php` (нe пиши custom header)
- [ ] Включен `partials/bottom-nav.php` (не пиши custom nav)
- [ ] `$rms_current_module` зададено правилно (за active tab detection)

## 14.2 Theme support
- [ ] `[data-theme="light"]` rules за всеки нов компонент
- [ ] `[data-theme="dark"]` rules за всеки нов компонент
- [ ] Не уча хардкоднати цветове (всички през CSS variables)
- [ ] Border-color използва `var(--border-color)` (transparent в light, hsl в dark)

## 14.3 SACRED elements
- [ ] `.glass` cards имат **точно 4 span-а** (shine + shine-bottom + glow + glow-bottom)
- [ ] Content в `.glass` винаги с `position:relative; z-index:5` или по-високо
- [ ] `.glass .shine` и `.glass .glow` НЕ са модифицирани
- [ ] В light mode `.shine` и `.glow` са `display: none` автоматично

## 14.4 Tokens
- [ ] Радиуси през `--radius`, `--radius-sm`, `--radius-pill`, `--radius-icon`
- [ ] Shadows през `--shadow-card`, `--shadow-card-sm`, `--shadow-pressed`
- [ ] Цветове през `--accent`, `--text`, `--text-muted` или `--qN-*`
- [ ] Font-family през `var(--font)` или `var(--font-mono)`
- [ ] Transitions с `var(--dur)` + `var(--ease)`

## 14.5 Animations
- [ ] Entry animations използват `fadeInUp` или `fadeIn`
- [ ] Stagger delays на cards (0.05s, 0.12s, 0.20s, etc.)
- [ ] `@media (prefers-reduced-motion: reduce)` блок включен
- [ ] Conic effects използват `--ease` linear или `conicSpin`

## 14.6 Layout
- [ ] Mobile-first (max-width 480px на `.app`)
- [ ] `safe-area-inset` на всички sticky/fixed елементи
- [ ] Body padding-top 56px + padding-bottom 88px+

## 14.7 i18n / pricing
- [ ] Всеки текст през `t('key')` ИЛИ check `$tenant['lang']`
- [ ] Цена през `priceFormat($amount, $tenant)`
- [ ] Никакво "Gemini" / "Anthropic" / "fal.ai" в UI — само "AI"

## 14.8 SVG icons
- [ ] Stroke използва `currentColor` или `var(--text)` / `var(--accent)`
- [ ] `stroke-width: 2` за стандартни икони
- [ ] `width: 18-22px` (header/op-icons), `width: 12-14px` (small)

## 14.9 Compliance script
- [ ] `bash /var/www/runmystore/design-kit/check-compliance.sh module.php`
- [ ] Exit 0 = approved, exit 1 = rejected → fix преди push

## 14.10 Visual regression
- [ ] Тестирано на Z Flip6 (~373px viewport)
- [ ] Тестирано в Light режим
- [ ] Тестирано в Dark режим
- [ ] Тестирано в Capacitor APK (не само Chrome desktop)
- [ ] Theme toggle работи (sun/moon icons се сменят)

---

# 🛣 ЧАСТ 15 — MIGRATION GUIDE

## 15.1 За всеки съществуващ модул (chat.php, products.php, sale.php, etc.)

**Стъпка 1:** Премахни всички CSS правила в `<style>` блока на модула, които са:
- Hex цветове → замени с `var(--accent)`, `var(--text)`, etc.
- Hardcoded `border-radius` → замени с `var(--radius*)`
- Hardcoded `box-shadow` → замени с `var(--shadow-card*)`
- `font-family: 'Inter'` или подобни → `var(--font)`

**Стъпка 2:** За всяка `.glass` карта:
- Провери има ли 4 span-а — ако не, добави
- Премести cont. в wrapper с `z-index: 5`
- В CSS, премести theme-specific styling в `[data-theme="light"]` / `[data-theme="dark"]`

**Стъпка 3:** Добави support за двата режима — копирай блокчета от Част 9

**Стъпка 4:** Тествай и в двата режима на Z Flip6

**Стъпка 5:** Run `check-compliance.sh` → fix → push

## 15.2 Приоритетен ред за миграция (Beta deadline 14-15 май)

1. **life-board.php** (Simple Mode home) — ETALON
2. **chat.php** (Detailed Mode home)
3. **sale.php** (S87E pending — 8 bug fix-а)
4. **products.php** (S95 wizard работа)
5. **deliveries.php**
6. **orders.php**
7. **warehouse.php / inventory.php**
8. **stats.php**
9. **transfers.php**
10. **register.php / login.php / settings.php**
11. **ai-studio.php**

## 15.3 За НОВ модул

1. Започвай от template (виж Част 17.1 — `partials/_template.php`)
2. Включи `header.php` + `bottom-nav.php`
3. Добави `aurora` div (от Част 4.3)
4. Добави съдържание използвайки recipes от Част 9
5. Run checklist (Част 14)

---

# 📋 ЧАСТ 16 — REFERENCE FILES

## 16.1 Authoritative

| Файл | Path | Описание |
|------|------|----------|
| **DESIGN_SYSTEM_v4.0_BICHROMATIC.md** | `/var/www/runmystore/DESIGN_SYSTEM.md` | ТОЗИ ДОКУМЕНТ — единствен source of truth |
| **header.php** | `/var/www/runmystore/partials/header.php` | Canonical 7-element header |
| **bottom-nav.php** | `/var/www/runmystore/partials/bottom-nav.php` | Canonical 4-tab bottom nav |
| **shell-init.php** | `/var/www/runmystore/partials/shell-init.php` | Инициализация на theme + plan + module |
| **shell-scripts.php** | `/var/www/runmystore/partials/shell-scripts.php` | rmsToggleTheme + rmsToggleLogout + rmsOpenPrinter |
| **style.css** | `/var/www/runmystore/partials/style.css` | Global tokens + reset |
| **life-board.php** | `/var/www/runmystore/life-board.php` | Reference implementation на Bible (etalon) |

## 16.2 Reference mockups (одобрени)

| Mockup | Status | Date |
|--------|--------|------|
| `life-board-bichromatic.html` | ✅ ETALON | 07.05.2026 |
| `life-board-hybrid.html` | ✅ Light hybrid one-mode reference | 07.05.2026 |

## 16.3 Archived (не използвай)

- `DESIGN_SYSTEM_v1.md` (April 19, 2026)
- `DESIGN_SYSTEM_v3_COMPLETE.md` (April 28, 2026)
- `kato-predniq-law.html`
- `card_light_neon.html` (preliminary mockup)
- `home-detailed-v2.html` (предишен design study)

---

# 🛠 ЧАСТ 17 — TEMPLATES

## 17.1 Page template (`partials/_template.php`)

```php
<?php
require __DIR__ . '/partials/shell-init.php';
$rms_current_module = 'YOUR_MODULE_NAME';   // chat, life-board, products, sale, etc.
?>
<!DOCTYPE html>
<html lang="bg" data-theme="<?= htmlspecialchars($rms_theme) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#08090d">
  <title>RunMyStore.AI</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/partials/style.css">
  <style>
    /* MODULE-SPECIFIC styles тук — само ако стандартните не покриват */
  </style>
</head>
<body>

<!-- Aurora background (global) -->
<div class="aurora">
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
</div>

<!-- Header -->
<?php include __DIR__ . '/partials/header.php'; ?>

<!-- Main content -->
<main class="app">
  <!-- ВАЖНО: Цялото съдържание тук -->
</main>

<!-- Chat input bar (когато е приложимо) -->
<?php include __DIR__ . '/partials/chat-input-bar.php'; ?>

<!-- Bottom nav -->
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>

<!-- Shell scripts (theme toggle, etc.) -->
<?php include __DIR__ . '/partials/shell-scripts.php'; ?>

</body>
</html>
```

## 17.2 New module checklist

```
1. Copy _template.php → my-module.php
2. Set $rms_current_module = 'my-module'
3. Add to $isAI/$isWh/$isStats/$isSale в bottom-nav.php (ако е нов entry point)
4. Build content using recipes от Част 9
5. Run check-compliance.sh
6. Test light + dark на Z Flip6
7. Commit: "S[XX]: [my-module] migrate to v4.0 BICHROMATIC"
```

---

# 🔍 ЧАСТ 18 — COMPLIANCE SCRIPT (notes за update)

`design-kit/check-compliance.sh` трябва да валидира за нов файл:

```bash
# Hardcoded цветове (без CSS variables)
grep -nE '#([0-9a-fA-F]{3,6})' --exclude="DESIGN_SYSTEM*"

# Hardcoded radius
grep -nE 'border-radius:\s*[0-9]+px(?!\s*\))'

# Native font-family (не Montserrat / DM Mono)
grep -nE "font-family:\s*['\"]?(Inter|Roboto|Arial|sans-serif|system-ui)"

# Hardcoded box-shadow
grep -nE 'box-shadow:\s*0\s+\d+px'

# Bootstrap / Tailwind classes
grep -nE 'class="[^"]*\b(btn-primary|d-flex|col-md|p-[0-9]|m-[0-9])\b'

# Hardcoded BG текст
grep -nE '>[^<]*[бвгджзилмнопрстфхцчшщъьюя][^<]*<' --exclude="t\(|\$tenant\['lang"

# Native dropdowns
grep -nE '<select[^>]*>'

# "Gemini" / "Claude" / "fal.ai" в UI
grep -niE 'Gemini|fal\.ai|Anthropic'

# Brand mention (не RUNMYSTORE.AI)
grep -nE '(RunMyStore|RUN MY STORE)' --exclude="DESIGN_SYSTEM*"

# .shine/.glow проверки за `.glass` cards
# (Custom logic — pseudo)
echo "Глас карти без 4 span-а..."
```

Exit codes:
- `0` = всички проверки минават
- `1` = поне една проверка не минава, изброй кои

---

# 📊 ЧАСТ 19 — CONTINUITY MATRIX (бърза справка)

## 19.1 Какво е СЪЩО в двата режима

| Aspect | Value |
|--------|-------|
| Радиуси | 22px / 14px / 999px / 50% |
| Размери | 40px header btn, 48px op-icon, 28px lb-emoji/store-avatar/badge, 30px fb-btn, 24px lb-orb |
| Layout | 1.4fr/1fr top-row, 2x2 ops grid, 1.6:1 op-btn aspect |
| Анимации | fadeInUp, conicSpin, shimmerSlide, orbSpin, navBounce, pulse |
| Easing | `cubic-bezier(0.5, 1, 0.89, 1)` (.ease) и `cubic-bezier(0.34, 1.56, 0.64, 1)` (.ease-spring) |
| Шрифт | Montserrat (UI) + DM Mono (numbers) |
| Press scale | 0.97 |
| Z-index | aurora=0, content=5, shine=1, glow=3, overlay=50, modal=100 |
| 9 effects | All work in both themes (different blend modes) |

## 19.2 Какво се СМЕНЯ между режимите

| Aspect | Light | Dark |
|--------|-------|------|
| `--bg-main` | `#e0e5ec` | `#08090d` |
| `--text` | `#2d3748` (WCAG AAA) | `#f1f5f9` |
| `--text-muted` | `#64748b` | `rgba(255,255,255,0.6)` |
| `--surface` | `#e0e5ec` (= bg) | `hsl(220 25% 4.8%)` (3-layer) |
| `--border-color` | `transparent` | `hsl(var(--hue2), 12%, 20%)` |
| Card shadow | `--shadow-card` (convex 8/-8 двойна) | `--shadow-card` (Neon Glass shadow) |
| Background | Plain | 3-layer radial gradients |
| `.shine`, `.glow` | `display: none` | SACRED enabled |
| Aurora blend | `multiply` | `plus-lighter` |
| Aurora opacity | 0.35 | 0.35 |
| Color saturation | oklch 0.18-0.25 chroma | hsl 70-90% |
| Backdrop-filter | none | `blur(12px)` |

## 19.3 9 Effects — режим compatibility

| # | Effect | Light | Dark | Note |
|---|--------|-------|------|------|
| 1 | Aurora blobs | ✅ multiply | ✅ plus-lighter | Различен blend mode |
| 2 | `.shine` (SACRED) | ❌ display:none | ✅ enabled | Невидимо в light (правилно) |
| 3 | `.glow` (SACRED) | ❌ display:none | ✅ enabled | Невидимо в light (правилно) |
| 4 | Conic ring (PRO badge) | ✅ enabled | ✅ enabled | Identical animation |
| 5 | Conic orb (Life Board) | ✅ enabled | ✅ enabled | Identical animation |
| 6 | Conic glow ring (expand) | ✅ enabled | ✅ enabled | Per q1-q6 цвят |
| 7 | Conic shimmer (CTA) | ✅ enabled | ✅ enabled | Identical animation |
| 8 | Soft glow halo (hover) | ✅ multiply | ✅ plus-lighter | Различен blend |
| 9 | Iridescent shimmer (AI Brain) | ✅ enabled | ✅ enabled | Identical (двоен) |

---

# 🚨 ЧАСТ 20 — КРИТИЧНИ ПРАВИЛА ОТ ПРОЕКТА

## 20.1 Production laws

1. **Закон №0 (DESIGN):** Всеки компонент работи И в двата режима, или не съществува
2. **Закон №1:** Пешо НЕ пише — voice/photo/tap only
3. **Закон №2:** PHP смята, AI говори
4. **Закон №3:** AI is silent, PHP continues (AI failure не блокира core)
5. **Закон №4 (бъдещ):** Audit Trail
6. **Закон №5 (бъдещ):** Confidence Routing (>0.85 auto, 0.5-0.85 confirm, <0.5 block)

## 20.2 UI rules

- "AI" винаги, никога "Gemini" / "fal.ai" / "Anthropic"
- `t('key')` или tenant lang check за всеки текст
- `priceFormat()` за всяка цена
- Voice button във всеки input

## 20.3 DB field naming (за избягване на 500 errors)

- `products.code` (НЕ sku)
- `products.retail_price` (НЕ sell_price)
- `products.image_url` (НЕ image)
- `inventory.quantity` (НЕ qty)
- `inventory.min_quantity` (НЕ min_stock)
- `sales.status = 'canceled'` (едно L)
- `sales.total` (НЕ total_amount)
- `sale_items.unit_price` (НЕ price)
- НИКОГА `ALTER TABLE ADD COLUMN IF NOT EXISTS` (MySQL 8 не поддържа) — използвай PREPARE/EXECUTE с information_schema check

---



# 🆕 ЧАСТ 22.5 — S96 ADDITIONS (07.05.2026)

> Промени след първа реална имплементация на life-board.php.
> Тези правила са **задължителни** наравно с останалите.

## 22.5.1 Brand logo (анимация — задължително)

`.rms-brand` НИКОГА не е статичен текст. Винаги анимиран:

```css
.rms-brand {
  position: relative;
  font-size: 15px;          /* desktop */
  font-weight: 900;
  letter-spacing: 0.10em;
  text-decoration: none;
  background: linear-gradient(90deg,
    hsl(var(--hue1) 80% 60%) 0%,
    hsl(var(--hue2) 80% 60%) 25%,
    hsl(var(--hue3) 70% 55%) 50%,
    hsl(var(--hue2) 80% 60%) 75%,
    hsl(var(--hue1) 80% 60%) 100%
  );
  background-size: 200% auto;
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  -webkit-text-fill-color: transparent;
  animation: rmsBrandShimmer 4s linear infinite;
  filter: drop-shadow(0 0 12px hsl(var(--hue1) 70% 50% / 0.4));
}
.rms-brand::after {
  content: "";
  position: absolute; inset: -4px -8px;
  border-radius: 8px;
  background: radial-gradient(ellipse at center,
    hsl(var(--hue1) 70% 50% / 0.15) 0%, transparent 70%);
  z-index: -1; pointer-events: none;
  animation: rmsBrandPulse 3s ease-in-out infinite;
}
@keyframes rmsBrandShimmer {
  0%   { background-position: 0% center; }
  100% { background-position: 200% center; }
}
@keyframes rmsBrandPulse {
  0%, 100% { opacity: 0.5; transform: scale(1); }
  50%      { opacity: 0.9; transform: scale(1.05); }
}
@media (max-width: 380px) {
  .rms-brand { font-size: 13px; letter-spacing: 0.08em; }
}
```

**ВАЖНО:** brand-ът се ползва **САМО от `partials/header.php`** — никога не пиши custom brand елементи.

## 22.5.2 Store picker (когато има 2+ магазина)

В Simple Mode (`life-board.php`) и Detailed Mode (`chat.php`) — store picker ВИНАГИ е в "Днес" cell-а:

```html
<div class="cell-header-row">
  <div class="cell-label">Днес · <?= htmlspecialchars($store_name) ?></div>
  <?php if (!empty($all_stores) && count($all_stores) > 1): ?>
  <select class="lb-store-picker" onchange="location.href='?store='+this.value" aria-label="Магазин">
    <?php foreach ($all_stores as $st): ?>
    <option value="<?= (int)$st['id'] ?>" <?= $st['id']==$store_id?'selected':'' ?>><?= htmlspecialchars($st['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
</div>
```

```css
.cell-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 6px;
  flex-wrap: wrap;
  min-width: 0;
  position: relative; z-index: 5;
}
.cell-header-row .cell-label {
  margin-bottom: 0;
  flex-shrink: 1; min-width: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.lb-store-picker {
  font-family: var(--font-mono);
  font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.04em;
  border-radius: var(--radius-pill);
  padding: 4px 22px 4px 10px;
  border: 1px solid var(--border-color);
  color: var(--text);
  cursor: pointer;
  outline: none;
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23667' stroke-width='3'><polyline points='6 9 12 15 18 9'/></svg>");
  background-repeat: no-repeat;
  background-position: right 6px center;
  background-size: 10px;
  max-width: 110px;
  flex-shrink: 0;
}
[data-theme="light"] .lb-store-picker,
:root:not([data-theme]) .lb-store-picker {
  background-color: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: none;
}
[data-theme="dark"] .lb-store-picker {
  background-color: hsl(220 25% 8%);
}
```

**SQL за `$all_stores` се зарежда винаги:**
```php
$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
```

## 22.5.3 Засилени op-buttons shadows (depth)

Стандартните `--shadow-card` НЕ са достатъчни за op-buttons. Override:

```css
[data-theme="light"] .op-btn,
:root:not([data-theme]) .op-btn {
  box-shadow:
    12px 12px 24px var(--shadow-dark),
    -12px -12px 24px var(--shadow-light) !important;
}
[data-theme="light"] .op-btn:active,
:root:not([data-theme]) .op-btn:active {
  box-shadow:
    inset 6px 6px 12px var(--shadow-dark),
    inset -6px -6px 12px var(--shadow-light) !important;
}
[data-theme="light"] .op-icon,
:root:not([data-theme]) .op-icon {
  box-shadow:
    inset 6px 6px 12px var(--shadow-dark),
    inset -6px -6px 12px var(--shadow-light);
}
```

## 22.5.4 Dark mode neon на op-buttons

В DARK mode op-bts получават **outer glow + inner highlight**:

```css
[data-theme="dark"] .op-btn {
  box-shadow:
    inset 0 1px 0 hsl(var(--hue1) 80% 60% / 0.15),
    0 0 24px hsl(var(--hue1) 80% 50% / 0.18),
    var(--shadow-card) !important;
}
[data-theme="dark"] .op-btn::after {
  content: '';
  position: absolute; inset: 0;
  border-radius: var(--radius);
  pointer-events: none;
  background: radial-gradient(ellipse at top,
    hsl(var(--hue1) 80% 60% / 0.08) 0%, transparent 60%);
  z-index: 0;
}
[data-theme="dark"] .op-icon {
  box-shadow:
    inset 0 0 12px hsl(var(--hue1) 80% 40% / 0.4),
    0 0 16px hsl(var(--hue1) 80% 50% / 0.3),
    inset 0 1px 0 hsl(var(--hue1) 80% 70% / 0.2);
}
[data-theme="dark"] .op-icon svg {
  filter: drop-shadow(0 0 6px hsl(var(--hue1) 80% 60% / 0.5));
}
```

## 22.5.5 Anti-flicker правило (КРИТИЧНО)

**НЕ слагай transition на body** — premигва при theme switch:

```css
/* ❌ ЛОШО — премигва */
body { transition: background 0.5s ease, color 0.5s ease; }

/* ✅ ДОБРЕ — без transition на body */
body { /* no transition */ }
```

Всеки компонент може да има свой transition на специфични properties (box-shadow, color), но **не на background**.

## 22.5.6 Theme toggle JavaScript (задължителна логика)

`partials/shell-scripts.php` ТРЯБВА да съдържа:

```javascript
// INIT THEME (default = light)
(function () {
  try {
    var saved = localStorage.getItem('rms_theme');
    var initial = saved || 'light';
    document.documentElement.setAttribute('data-theme', initial);
  } catch (_) {
    document.documentElement.setAttribute('data-theme', 'light');
  }
})();

window.rmsToggleTheme = function () {
  var cur = document.documentElement.getAttribute('data-theme') || 'light';
  var nxt = (cur === 'light') ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', nxt);  // ВИНАГИ setAttribute
  try { localStorage.setItem('rms_theme', nxt); } catch (_) {}
  syncThemeIcons();
  if (navigator.vibrate) navigator.vibrate(5);
};
```

**КРИТИЧНО:** НЕ ползвай `removeAttribute('data-theme')` — това активира `:root:not([data-theme])` (light) винаги, а dark mode никога не работи.

## 22.5.7 Google Fonts link (задължителен във всеки модул)

Ако използваш `var(--font)` (Montserrat) и `var(--font-mono)` (DM Mono) — **трябва** да включиш:

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
```

> Ако не го включиш — браузърът ще ползва default sans-serif (грозно).

---

# 🔄 ЧАСТ 21 — VERSIONING

| Версия | Дата | Промени |
|--------|------|---------|
| v1.0 | 2026-04-19 | Инициализация след S74.7 |
| v2.0 | 2026-04-23 | S79.POLISH2 — пълна Neon Glass спецификация |
| v3.0 | 2026-04-28 | Пълна спецификация на dark Neon Glass |
| **v4.0** | **2026-05-07** | **BICHROMATIC. Light = default. Dark = SACRED Neon Glass option. 9 effects continuity. ОТМЕНЯ всички предишни.** |
| **v4.1** | **2026-05-07** | **S96 ADDITIONS: animated brand, store picker recipe, засилени op shadows, dark ops neon, anti-flicker rule, theme toggle JS spec, fonts requirement.** |

**Bump rules:**
- Major (v5.0) — фундаментални промени (нов default режим, нова семантика на цветове)
- Minor (v4.1, v4.2) — нови компоненти, нови ефекти, нови recipes
- Patch (v4.0.1) — корекции, typos, missing CSS

---

# 🎓 ЧАСТ 22 — БЪРЗА СПРАВКА (Cheat sheet)

## Цветове

```
Hue1: 255   → indigo (primary)
Hue2: 222   → blue (secondary)
Hue3: 180   → cyan (tertiary)

q1: red 25     → "губиш"
q2: violet 305 → "от какво губиш"
q3: green 155  → "печелиш"
q4: teal 195   → "от какво печелиш"
q5: amber 70   → "поръчай"
q6: gray 220   → "не поръчвай"
```

## Радиуси

```
22px  → cards (.glass, .lb-card, .op-btn, .top-row .cell)
14px  → small cards, inputs, lb-action, lb-body, ai-brain, studio
999px → all pills, store-chip, mode-toggle, plan-badge, chat-input-bar
50%   → all circular icons (avatars, op-icons, mic, fb-btn, lb-emoji)
```

## Размери на padding

```
Glass card:        14px
Cell, lb-card:     14px
Op-btn:            18px 14px
AI brain, studio:  14px 16px
Pill (lb-mode):    8px 14px
Plan-badge:        5px 12px
LB-action:         8px 12px
Chat input bar:    8px 8px 8px 18px
```

## Font sizes

```
9px   → labels, brand
10px  → meta, sub, fb-label
11px  → secondary labels, lb-action
12px  → buttons, store-name
13px  → titles, lb-collapsed-title, ai-brain-msg
14px  → section titles, lb-title-text
22px  → small stat
26px  → weather temp
30px  → main stat (cell-num)
34px  → hero stat
```

## Animation timings

```
150ms  → press feedback (.dur-fast)
250ms  → default (.dur)
350ms  → modal/expand (.dur-slow)
500ms  → nav transitions
600ms  → entry animations (fadeInUp)
2.4s   → pulse loops
3s     → conic spin (badges, CTAs)
3.5s   → shimmer slide
4s     → orb / aurora cycle
20s    → aurora drift
```

---

# 🏁 КРАЙ НА DESIGN SYSTEM v4.0 BICHROMATIC

> **Този документ е единственият valid source of truth за дизайн в RunMyStore.AI.**
>
> *Ако нещо е изпълнено в проекта и не е тук → грешка е, или нужда от update.*
> *Източник на истината: ТОЗИ ДОКУМЕНТ + life-board.php (post-migration etalon).*
> *Versioning при промяна: bump на minor (v4.1, v4.2), всеки път с дата + причина.*
> *Никаква интерпретация. Всеки модул задължително следва Adoption Checklist (Част 14).*

**Подпис:**
- Финализирано: 07.05.2026
- Approved by: Tihol (founder, primary tester)
- Reference impl: life-board.php (commit pending)
- Beta launch: ENI store, 14-15.05.2026
