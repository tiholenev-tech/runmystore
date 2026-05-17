# 🎨 RUNMYWALLET DESIGN SYSTEM HANDOFF
## Practical guide за нов дизайн чат

**Версия:** 1.0  
**Дата:** 17.05.2026 (S149)  
**За:** Нов Claude чат който продължава дизайн работа за RunMyWallet / RunMyStore.AI  
**Брат с:** WALLET_PHASE_1_DB_BRIEF_CC.md (DB schema)  

---

## 🎯 КАКВО ИМА ВЕЧЕ — ИЗПОЛЗВАЙ КАТО ОСНОВА

```
10 mockup-а в /var/www/runmystore/mockups/ (push-нати в GitHub):
  ✓ P20_runmywallet_home.html          Главна страница (Bottom nav)
  ✓ P22_runmywallet_onboarding.html    7-screen welcome flow
  ✓ P23_runmywallet_records.html       Списък със записи
  ✓ P24_runmywallet_analysis.html      4 sub-tabs с графики
  ✓ P25_runmywallet_goals.html         3 filter pills
  ✓ P26_runmywallet_settings.html      6 sections
  ✓ P27_runmywallet_voice_overlay.html 5 modal states
  ✓ P28_runmywallet_photo_receipt.html 4 camera states
  ✓ P29_runmywallet_add_goal.html      Form modal
  ✓ P30_runmywallet_notifications.html Notification list
  ✓ P21_dash82_v2.html                 Compact card за life-board.php

GitHub: https://github.com/tiholenev-tech/runmystore/tree/main/mockups
```

---

## 🏛️ КАКВО ГЛЕДАМ ЗА REFERENCE — supreme authority

### Главен референтен файл

```
/var/www/runmystore/mockups/P15_simple_FINAL.html (1 654 реда)
```

**Това е "Holy Grail"-ът — всички patterns идват от него.**

Конкретно гледам:
- `:root` CSS variable block (lines ~50-90)
- Sacred Glass canon (lines ~120-180)
- `.op-btn` pattern (lines ~184-205)
- `.studio-btn` pattern (lines ~210-230)
- `.help-card` pattern (lines ~250-280)
- `.mic-btn` (lines ~520-560 — sacred Cherry component)
- Hue класове (q1/q2/q3/q5/qd/qm)
- Aurora 4 blobs animation

### Secondary references (за специфични patterns)

```
P20_runmywallet_home.html       — главна структура + voice bar
P24_runmywallet_analysis.html   — графики (line/donut/sparkline)
P27_runmywallet_voice_overlay.html — fullscreen modals + states
P28_runmywallet_photo_receipt.html — camera UI + capture flow
P29_runmywallet_add_goal.html   — form fields + 4×3 icon picker
```

### Когато правя НОВ mockup

```
1. Копирам header + aurora + CSS variables от P20 (стабилна основа)
2. Копирам Sacred Glass canon (НИКОГА не съкращавам)
3. Избирам подходящ hue (q1/q3/q5/qd/qm) според семантиката
4. Build-вам specific patterns копирайки от най-близкия mockup
5. Voice bar + Bottom nav ако е главна страница
6. Demo bar отгоре за state switching
```

---

## 📜 СВЕЩЕНИ ЗАКОНИ (никога не нарушавай)

```
ЗАКОН №1: ONLY Montserrat font
  weights: 400 / 500 / 600 / 700 / 800 / 900
  font-variant-numeric: tabular-nums за всички числа
  DM Mono и други шрифтове ПРЕМАХНАТИ

ЗАКОН №2: ONLY SVG icons (никакви emoji в production HTML)
  Източник: https://lucide.dev (вграждай inline)
  stroke="currentColor" или explicit hsl()
  fill="none" + stroke-width=2 + stroke-linecap="round"

ЗАКОН №3: Sacred Glass canon — 4 spans винаги
  <div class="glass [hue]">
    <span class="shine"></span>
    <span class="shine shine-bottom"></span>
    <span class="glow"></span>
    <span class="glow glow-bottom"></span>
    [content]
  </div>
  Тhe four spans създават неонов canvas в dark mode
  В light mode стават display:none (запазваме neumorphic depth)

ЗАКОН №4: Light + Dark theme задължителен
  data-theme="light" / data-theme="dark"
  Toggle бутон в header
  localStorage 'rmw_theme' за персистентност
  Default: light

ЗАКОН №5: Mobile-first 375-480px
  max-width:480px на .app container
  margin:0 auto за центриране
  padding-bottom за bottom nav clearance

ЗАКОН №6: oklch color space за consistency
  Light mode: oklch(0.62 0.22 285) — accent
  Dark mode: hsl(255 80% 65%) — accent
  Hue ротация: 0=loss / 145=gain / 38=amber / 280=magic / 255=accent

ЗАКОН №7: НЕ overflow:hidden на Sacred Glass cards
  Изрязва shine/glow spans → counter-productive

ЗАКОН №8: Кратки кратки кратки числа
  Не "1437.50 €", a "1 437 €"
  Не "12,345.67", a "12 346"
  font-variant-numeric: tabular-nums за подравняване
```

---

## 🎨 CSS VARIABLES (copy-paste блок)

```css
:root{
  --hue1:255;
  --hue2:222;
  --hue3:180;
  --radius:22px;
  --radius-sm:14px;
  --radius-pill:999px;
  --radius-icon:50%;
  --border:1px;
  --ease:cubic-bezier(0.5,1,0.89,1);
  --ease-spring:cubic-bezier(0.34,1.56,0.64,1);
  --dur:250ms;
  --press:0.97;
  --font-mono:'Montserrat',sans-serif;
}

/* LIGHT THEME (default) */
:root:not([data-theme]),:root[data-theme="light"]{
  --bg-main:#e0e5ec;
  --surface:#e0e5ec;
  --surface-2:#d1d9e6;
  --border-color:transparent;
  --text:#2d3748;
  --text-muted:#64748b;
  --text-faint:#94a3b8;
  --shadow-light:#ffffff;
  --shadow-dark:#a3b1c6;
  --neu-d:8px;
  --neu-b:16px;
  --neu-d-s:4px;
  --neu-b-s:8px;
  --shadow-card: var(--neu-d) var(--neu-d) var(--neu-b) var(--shadow-dark),
                 calc(var(--neu-d) * -1) calc(var(--neu-d) * -1) var(--neu-b) var(--shadow-light);
  --shadow-card-sm: var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark),
                    calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);
  --shadow-pressed: inset var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark),
                    inset calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);
  --accent:oklch(0.62 0.22 285);
  --accent-2:oklch(0.65 0.25 305);
  --magic:oklch(0.65 0.25 305);
  --gain:oklch(0.6 0.18 145);
  --loss:oklch(0.6 0.22 25);
  --amber:oklch(0.7 0.18 60);
  --aurora-blend:multiply;
  --aurora-opacity:0.32;
}

/* DARK THEME */
:root[data-theme="dark"]{
  --bg-main:#08090d;
  --surface:hsl(220,25%,4.8%);
  --surface-2:hsl(220,25%,8%);
  --border-color:hsl(var(--hue2),12%,20%);
  --text:#f1f5f9;
  --text-muted:rgba(255,255,255,0.6);
  --text-faint:rgba(255,255,255,0.4);
  --shadow-card:hsl(var(--hue2) 50% 2%) 0 10px 16px -8px,
                hsl(var(--hue2) 50% 4%) 0 20px 36px -14px;
  --shadow-card-sm:hsl(var(--hue2) 50% 2%) 0 4px 8px -2px;
  --shadow-pressed:inset 0 2px 4px hsl(var(--hue2) 50% 2%);
  --accent:hsl(var(--hue1),80%,65%);
  --accent-2:hsl(var(--hue2),80%,65%);
  --magic:hsl(280,70%,65%);
  --gain:hsl(145,65%,55%);
  --loss:hsl(0,75%,65%);
  --amber:hsl(38,90%,60%);
  --aurora-blend:plus-lighter;
  --aurora-opacity:0.35;
}

/* BACKGROUNDS */
:root:not([data-theme]) body,[data-theme="light"] body{
  background:var(--bg-main);
  color:var(--text);
}
[data-theme="dark"] body{
  background:
    radial-gradient(ellipse 800px 500px at 20% 10%, hsl(var(--hue1) 60% 35% / .22) 0%, transparent 60%),
    radial-gradient(ellipse 700px 500px at 85% 85%, hsl(var(--hue2) 60% 35% / .22) 0%, transparent 60%),
    linear-gradient(180deg, #0a0b14 0%, #050609 100%);
  background-attachment:fixed;
  color:var(--text);
}
```

---

## 🌟 SACRED GLASS CANON (никога не съкращавай)

### HTML structure (ВСЕКИ glass card):

```html
<div class="glass q3">  <!-- q3 = gain hue (или q1/q2/q5/qd/qm) -->
  <span class="shine"></span>
  <span class="shine shine-bottom"></span>
  <span class="glow"></span>
  <span class="glow glow-bottom"></span>
  
  <!-- TVOITE контент тук -->
  <h2>Заглавие</h2>
  <p>Описание</p>
</div>
```

### CSS (copy-paste — никога не променяй):

```css
.glass{
  position:relative;
  border-radius:var(--radius);
  border:var(--border) solid var(--border-color);
  isolation:isolate;
}
.glass.sm{border-radius:var(--radius-sm)}

.glass .shine,.glass .glow{--hue:var(--hue1)}
.glass .shine-bottom,.glass .glow-bottom{--hue:var(--hue2);--conic:135deg}

/* LIGHT MODE: hide shine/glow, ползваме neumorphic */
[data-theme="light"] .glass,
:root:not([data-theme]) .glass{
  background:var(--surface);
  box-shadow:var(--shadow-card);
  border:none;
}
[data-theme="light"] .glass .shine,
[data-theme="light"] .glass .glow,
:root:not([data-theme]) .glass .shine,
:root:not([data-theme]) .glass .glow{display:none}

/* DARK MODE: pъ ОН неон glow */
[data-theme="dark"] .glass{
  background:
    linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%),
    linear-gradient(45deg, hsl(var(--hue2) 50% 10% / .8), hsl(var(--hue2) 50% 10% / 0) 33%),
    linear-gradient(hsl(220 25% 4.8% / .78));
  backdrop-filter:blur(12px);
  box-shadow:var(--shadow-card);
}
[data-theme="dark"] .glass .shine{
  pointer-events:none;
  border-radius:0;
  border-top-right-radius:inherit;
  border-bottom-left-radius:inherit;
  border:1px solid transparent;
  width:75%;
  aspect-ratio:1;
  display:block;
  position:absolute;
  right:-1px;
  top:-1px;
  z-index:1;
  background:conic-gradient(from var(--conic, -45deg) at center in oklch,
    transparent 12%, hsl(var(--hue), 80%, 60%), transparent 50%) border-box;
  mask:linear-gradient(transparent),linear-gradient(black);
  mask-clip:padding-box,border-box;
  mask-composite:subtract;
}
[data-theme="dark"] .glass .shine.shine-bottom{
  right:auto; top:auto; left:-1px; bottom:-1px;
}
[data-theme="dark"] .glass .glow{
  pointer-events:none;
  border-top-right-radius:calc(var(--radius) * 2.5);
  border-bottom-left-radius:calc(var(--radius) * 2.5);
  border:calc(var(--radius) * 1.25) solid transparent;
  inset:calc(var(--radius) * -2);
  width:75%;
  aspect-ratio:1;
  display:block;
  position:absolute;
  left:auto;
  bottom:auto;
  background:conic-gradient(from var(--conic, -45deg) at center in oklch,
    hsl(var(--hue), 80%, 60% / .5) 12%, transparent 50%);
  filter:blur(12px) saturate(1.25);
  mix-blend-mode:plus-lighter;
  z-index:3;
  opacity:0.6;
}
[data-theme="dark"] .glass .glow.glow-bottom{
  inset:auto;
  left:calc(var(--radius) * -2);
  bottom:calc(var(--radius) * -2);
}

/* Hue класове */
[data-theme="dark"] .glass.q1 .shine,
[data-theme="dark"] .glass.q1 .glow{--hue:0}     /* loss (red) */
[data-theme="dark"] .glass.q1 .shine-bottom,
[data-theme="dark"] .glass.q1 .glow-bottom{--hue:15}

[data-theme="dark"] .glass.q3 .shine,
[data-theme="dark"] .glass.q3 .glow{--hue:145}   /* gain (green) */
[data-theme="dark"] .glass.q3 .shine-bottom,
[data-theme="dark"] .glass.q3 .glow-bottom{--hue:165}

[data-theme="dark"] .glass.q5 .shine,
[data-theme="dark"] .glass.q5 .glow{--hue:38}    /* amber */
[data-theme="dark"] .glass.q5 .shine-bottom,
[data-theme="dark"] .glass.q5 .glow-bottom{--hue:28}

[data-theme="dark"] .glass.qd .shine,
[data-theme="dark"] .glass.qd .glow{--hue:var(--hue1)}  /* default purple */
[data-theme="dark"] .glass.qd .shine-bottom,
[data-theme="dark"] .glass.qd .glow-bottom{--hue:var(--hue2)}

[data-theme="dark"] .glass.qm .shine,
[data-theme="dark"] .glass.qm .glow{--hue:280}   /* magic violet */
[data-theme="dark"] .glass.qm .shine-bottom,
[data-theme="dark"] .glass.qm .glow-bottom{--hue:310}

/* Z-index за content над shine/glow */
.glass > *:not(.shine):not(.glow){position:relative; z-index:5}
```

### Hue семантика (когато използваш кой)

```
q1 (loss/red 0-15):       Грешки, разходи, ДДС crossing, warnings critical
q3 (gain/green 145-165):  Приходи, постижения, success states
q5 (amber 28-38):         Цели, лимити warning, осигуровки, alerts medium
qd (default purple):      Балансиран state, profit, neutral metrics
qm (magic 280-310):       AI insights, sparkle moments, special CTAs
```

---

## 🎬 ANIMATIONS (copy-paste keyframes)

```css
/* Aurora drift (background blobs) */
@keyframes auroraDrift{
  0%,100%{transform:translate(0,0) scale(1)}
  33%{transform:translate(30px,-20px) scale(1.05)}
  66%{transform:translate(-20px,30px) scale(0.95)}
}

/* Conic spin (на бутони и orbs) */
@keyframes conicSpin{to{transform:rotate(360deg)}}

/* Fade in от долу */
@keyframes fadeInUp{
  from{opacity:0; transform:translateY(8px)}
  to{opacity:1; transform:translateY(0)}
}

/* Brand shimmer (logo gradient) */
@keyframes rmsBrandShimmer{
  0%{background-position:0% center}
  100%{background-position:200% center}
}

/* Pulse на recording mic */
@keyframes recordPulse{
  0%,100%{transform:scale(1); box-shadow:0 12px 40px hsl(0 75% 45% / 0.7)}
  50%{transform:scale(1.08); box-shadow:0 18px 70px hsl(0 75% 45% / 0.95)}
}

/* Check pop при success */
@keyframes checkPop{
  0%{transform:scale(0); opacity:0}
  60%{transform:scale(1.2)}
  100%{transform:scale(1); opacity:1}
}

/* Skeleton shimmer */
@keyframes skeletonShimmer{
  0%{background-position:-200% 0}
  100%{background-position:200% 0}
}
```

---

## 🌈 AURORA BACKGROUND (Copy-paste)

```html
<!-- HTML (в начало на body) -->
<div class="aurora">
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
</div>
```

```css
.aurora{
  position:fixed;
  inset:0;
  overflow:hidden;
  pointer-events:none;
  z-index:0;
}
.aurora-blob{
  position:absolute;
  border-radius:50%;
  filter:blur(60px);
  opacity:var(--aurora-opacity);
  mix-blend-mode:var(--aurora-blend);
  animation:auroraDrift 20s ease-in-out infinite;
}
.aurora-blob:nth-child(1){
  width:280px; height:280px;
  background:hsl(var(--hue1),80%,60%);
  top:-60px; left:-80px;
}
.aurora-blob:nth-child(2){
  width:240px; height:240px;
  background:hsl(var(--hue3),70%,60%);
  top:35%; right:-100px;
  animation-delay:4s;
}
.aurora-blob:nth-child(3){
  width:200px; height:200px;
  background:hsl(var(--hue2),80%,60%);
  bottom:80px; left:-50px;
  animation-delay:8s;
}
```

---

## 🎛️ KEY PATTERNS (copy-paste от mockup-ите)

### 1. Header с brand shimmer

```
ОТКЪДЕ: P20_runmywallet_home.html lines ~95-130
КОГА: Главна страница за всеки tab
```

### 2. Op-btn (primary action card)

```
ОТКЪДЕ: P20_runmywallet_home.html "Запиши с глас" бутон
КОГА: Primary actions с icon + title + sub
ПАТЕРН:
  glass card → 56×56 gradient icon → title + sub → conic spin overlay
```

### 3. Studio-btn (secondary action)

```
ОТКЪДЕ: P20_runmywallet_home.html "Снимай касова бележка"
КОГА: Secondary actions, lighter visual weight
```

### 4. Mic-btn (sacred — НИКОГА не копирай частично)

```
ОТКЪДЕ: P20 voice bar / P22 onboarding / P27 voice overlay
3 цвята stop gradient + 3-layer shadow + ::before conicSpin + ::after gloss
46×46 (compact) или 160×160 (big-mic)
```

### 5. Help-card (qm magic hue AI insight)

```
ОТКЪДЕ: P20 / P24 / P25 / P30
КОГА: AI insights, suggestions, ML predictions
36×36 magic gradient icon с conic spin overlay
```

### 6. Period pills (4-tab switcher)

```
ОТКЪДЕ: P24 lines ~270-290
ТАБОВЕ: Седмица / Месец / Тримесечие / Година
Selected = gradient background, others = transparent с muted text
```

### 7. Sub-tabs (vertical icon + label)

```
ОТКЪДЕ: P24 lines ~300-340
4 cells grid с SVG + текст
Selected = full gradient + white
```

### 8. Cat-row (transaction list item)

```
ОТКЪДЕ: P20 "Топ харчове" / P23 records list
42×42 cat orb (colored gradient) + name + meta + amount
```

### 9. Chart SVG patterns

```
ОТКЪДЕ: P24_runmywallet_analysis.html
ЛИНЕЕН: viewBox="0 0 320 170", 2 series, drop-shadow, animated draw
ДОНУТ: viewBox="0 0 100 100", 6 conic stroke segments, transform:rotate(-90)
SPARKLINE: viewBox="0 0 100 36", polyline + 1 endpoint circle
```

### 10. Stat hero (big number)

```
ОТКЪДЕ: P24 Преглед tab
36px font-weight:900 letter-spacing:-0.025em
text-shadow в dark mode за глоу
Delta pill (+18% / -5%) встрани
```

---

## 🎚️ COMPONENT CATALOG

| Component | Где е | Кога ползваш |
|-----------|-------|--------------|
| `.rms-header` | P20 lines ~100 | Главна страница header |
| `.rms-brand` | P20 lines ~110 | Logo с shimmer |
| `.rms-plan-badge` | P20 lines ~120 | Plan badge до logo |
| `.back-btn` | P26 / P29 / P30 | Sub-page header back |
| `.op-btn` | P20 | Primary action card |
| `.studio-btn` | P20 | Secondary action card |
| `.mic-btn` | Всички | Voice trigger (sacred) |
| `.help-card` | Всички | AI insights |
| `.period-row` | P24 | 4-tab period switcher |
| `.subtab-row` | P24 | Vertical icon tabs |
| `.cat-row` | P20 / P23 | Transaction list item |
| `.lb-card` | Дне се ползва | Life-board card (RMS) |
| `.delta-pill` | P24 | +X% / -Y% индикатори |
| `.chart-svg` | P24 | Line chart |
| `.donut-svg` | P24 | Donut chart |
| `.trend-spark` | P24 | Sparkline mini-chart |
| `.vat-bar-track` | P24 / P30 | Progress bar |
| `.reminder-row` | P24 | Calendar date с meta |
| `.toggle` | P26 | iOS-style on/off |
| `.bot-nav` | P20 / P23 / P24 / P25 | Bottom navigation 4 tabs |
| `.voice-bar` | P20 / P23 / P24 / P25 | Sticky voice input |
| `.cta-btn` | P27 / P28 / P29 | Primary modal action |
| `.notif-card` | P30 | Notification list item |
| `.type-card` | P29 | Form type selector |
| `.icon-cell` | P29 | Icon picker grid item |

---

## 🔄 BOTTOM NAV (за main pages)

```
ОТКЪДЕ: P20 / P23 / P24 / P25 (всички main tabs)

Tabs (4):
  Начало    /home          house icon
  Записи    /records       list icon  
  Анализ    /analysis      bar-chart icon
  Цели      /goals         target icon

Active state:
  Color → accent (purple)
  Top gradient line 26×3 със glow
  
Pattern: position:fixed; bottom:12px; left+right:12px; max-width:456px;
         border-radius:22px; height:64px; 4-col grid
```

---

## 📋 START-NEW-MOCKUP TEMPLATE

Когато правиш нов mockup, копирай тази основа:

```html
<!DOCTYPE html>
<html lang="bg" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<title>RunMyWallet · [PAGE NAME]</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<script>(function(){try{var s=localStorage.getItem('rmw_theme')||'light';document.documentElement.setAttribute('data-theme',s);}catch(_){document.documentElement.setAttribute('data-theme','light');}})();</script>

<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{min-height:100%}
body{font-family:'Montserrat',sans-serif;overflow-x:hidden;font-variant-numeric:tabular-nums}
button,input,a{font-family:inherit;color:inherit}
button{background:none;border:none;cursor:pointer}
a{text-decoration:none}

/* [PASTE CSS VARIABLES BLOCK ОТ ГОРЕ] */
/* [PASTE SACRED GLASS BLOCK ОТ ГОРЕ] */
/* [PASTE AURORA BLOCK ОТ ГОРЕ] */
/* [PASTE ANIMATIONS ОТ ГОРЕ] */

/* DEMO BAR (за state switching during dev) */
.demo-bar{position:fixed;top:0;left:0;right:0;z-index:200;background:hsl(280 60% 15% / .96);backdrop-filter:blur(20px);border-bottom:1px solid hsl(280 60% 25%);padding:8px 12px;display:flex;align-items:center;gap:6px;font-size:11px}
.demo-bar-label{color:#fff;font-weight:800;font-size:10px;opacity:0.7}
.demo-btn{padding:6px 10px;border-radius:8px;border:1px solid hsl(280 50% 35%);background:hsl(280 50% 20%);color:#fff;font-weight:700;cursor:pointer;font-size:11px}
.demo-btn.active{background:linear-gradient(135deg,hsl(280 70% 55%),hsl(305 65% 55%));border-color:transparent;box-shadow:0 4px 12px hsl(280 70% 50% / 0.5)}

/* HEADER (copy-paste от P20) */
.rms-header{position:sticky;top:38px;z-index:50;height:56px;padding:0 16px;display:flex;align-items:center;gap:8px}
/* ... continue with custom styles per page ... */

.app{position:relative;z-index:5;max-width:480px;margin:0 auto;padding:14px 12px calc(40px + env(safe-area-inset-bottom,0))}
</style>
</head>
<body>

<div class="aurora">
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
  <div class="aurora-blob"></div>
</div>

<div class="demo-bar">
  <span class="demo-bar-label">THEME</span>
  <button class="demo-btn active" onclick="toggleTheme()">Свети/Тъмно</button>
</div>

<header class="rms-header">
  <!-- TODO: header content -->
</header>

<div class="app">
  <!-- TODO: page content -->
</div>

<script>
function toggleTheme(){
  const cur = document.documentElement.getAttribute('data-theme') || 'light';
  const next = cur === 'light' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', next);
  try{localStorage.setItem('rmw_theme', next);}catch(_){}
}
</script>

</body>
</html>
```

---

## ❌ DON'TS — Често допускани грешки

```
✗ НЕ overflow:hidden на glass cards (cuts shine/glow)
✗ НЕ box-sizing:content-box (винаги border-box)
✗ НЕ aspect-ratio:1 за бутони с текст (use min-height + flex)
✗ НЕ emoji в production HTML (само в demo bar)
✗ НЕ hardcoded цветове "#" — само oklch() / hsl() с CSS variables
✗ НЕ font-family Arial/sans-serif fallback (само Montserrat)
✗ НЕ забравяй <meta viewport>
✗ НЕ забравяй font-variant-numeric: tabular-nums за числа
✗ НЕ заобикаляй Sacred Glass canon (всичките 4 spans)
✗ НЕ забравяй z-index:5 на content вътре в glass
✗ НЕ забравяй data-theme defaulting в localStorage script (виж top of head)
✗ НЕ pad-вай glass.shine — display:block + position:absolute
✗ НЕ smaller отколкото 9.5px font-size в production (читаемост)
✗ НЕ повече от 4 hue класа на 1 страница (визуален хаос)
```

---

## 🎁 BONUS: SVG ICONS използвани често (copy-paste)

### Mic
```svg
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
  <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
  <line x1="12" y1="19" x2="12" y2="23"/>
  <line x1="8" y1="23" x2="16" y2="23"/>
</svg>
```

### Camera
```svg
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
  <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
  <circle cx="12" cy="13" r="4"/>
</svg>
```

### Sparkle (AI)
```svg
<svg viewBox="0 0 24 24" fill="currentColor">
  <path d="M12 2l2.4 7.4L22 12l-7.6 2.6L12 22l-2.4-7.4L2 12l7.6-2.6z"/>
</svg>
```

### Check
```svg
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
  <polyline points="20 6 9 17 4 12"/>
</svg>
```

### Plus
```svg
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
  <line x1="12" y1="5" x2="12" y2="19"/>
  <line x1="5" y1="12" x2="19" y2="12"/>
</svg>
```

### Chevron right
```svg
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
  <polyline points="9 18 15 12 9 6"/>
</svg>
```

**Цялата icon library:** https://lucide.dev/icons

---

## 📚 ОТКОВО ВЪЗМОЖНО Е НОВ MOCKUP — Какво остава

```
P31  Login / Signup            (auth flow преди onboarding)
P32  Forgot Password           (reset flow)
P33  Record Detail / Edit      (tap на запис → детайл)
P34  Manual Entry Fallback     (когато voice/photo fail-нат)
P35  Goal Detail Page          (история, edit, delete)
P36  Upgrade Plan              (FREE/START/PRO comparison)
P37  Empty States              (no records / no goals)
P38  Tax Declaration View      (пълен screen с pre-fill данни)
P39  Export Modal              (CSV/Excel/PDF)

Wallet още:
P40  Splash Screen
P41  Permission Requests
P42  Tutorial Hints overlay

RunMyStore mockups:
RMS01  Stats Overview         (за shop_dashboard)
RMS02  Products v3 Stats Tab
RMS03  Orders Analytics
```

---

## 🔗 БЪРЗИ ЛИНКОВЕ

```
Repo: https://github.com/tiholenev-tech/runmystore
Mockups folder: https://github.com/tiholenev-tech/runmystore/tree/main/mockups

Главните файлове за reference:
  P15 (supreme):    /var/www/runmystore/mockups/P15_simple_FINAL.html
  P20 (RMW home):   https://github.com/tiholenev-tech/runmystore/blob/main/mockups/P20_runmywallet_home.html
  P24 (charts):     https://github.com/tiholenev-tech/runmystore/blob/main/mockups/P24_runmywallet_analysis.html
  P27 (modals):     https://github.com/tiholenev-tech/runmystore/blob/main/mockups/P27_runmywallet_voice_overlay.html

Bible:
  STATS_FINANCE_MODULE_BIBLE_v1.md (12 627 реда — v1.5)
  https://github.com/tiholenev-tech/runmystore/blob/main/STATS_FINANCE_MODULE_BIBLE_v1.md
```

---

## 📞 BOOT PROMPT за нов чат

Когато стартираш нова дизайн сесия, прати на Claude следното:

```
═══════════════════════════════════════════════════════════
ROLE: Старши UI дизайнер за RunMyWallet (sub-brand на RunMyStore.AI)

CONTEXT: 
- Sole founder в България работи върху voice-first finance app
- 10 mockup-а вече направени в S148-S149 (P20-P30)
- Sacred Glass canon + Aurora + neumorphic depth
- ONLY Montserrat + ONLY SVG + Mobile-first 375-480px
- Light + Dark theme задължителен

REFERENCE FILES (прочети ги ПЪРВО):
1. DESIGN_HANDOFF.md (този документ)
2. Mockup P15_simple_FINAL.html (supreme reference)
3. Mockup P20_runmywallet_home.html (current home page)
4. Mockup P24_runmywallet_analysis.html (chart patterns)

COMMUNICATION:
- БГ език само
- Кратко, директно, без surveys
- Технически решения → Claude решава САМ
- Логически / продуктови → пита Тихол
- Commit само на explicit "GIT" / "DAVAJ GIT"

КЪДЕ ДА НАМЕРИШ ФАЙЛОВЕТЕ:
GitHub repo: tiholenev-tech/runmystore
Folder: /mockups/

ЗАКОНИ (никога не нарушавай):
1. Sacred Glass: 4 spans винаги (shine/shine-bottom/glow/glow-bottom)
2. Montserrat ONLY (NO DM Mono или други шрифтове)
3. SVG icons ONLY (NO emoji)  
4. Mobile-first 375-480px
5. Light + Dark theme toggle задължителен
6. Hue класове: q1(loss) q3(gain) q5(amber) qd(default) qm(magic)
7. font-variant-numeric: tabular-nums за всички числа
8. НЕ overflow:hidden на glass cards
═══════════════════════════════════════════════════════════
```

---

**END OF HANDOFF v1.0**

Този документ дава пълни инструкции за нов дизайн чат. Прочети го веднъж, копирай patterns когато ти трябват, ползвай mockup-ите като референтна point.
