# DESIGN SYSTEM v2.0 — S79 NEON GLASS STANDARD

**Дата:** 2026-04-23
**След сесия:** S79.VISUAL_REWRITE + S79.POLISH2 (chat.php v8)
**Статус:** ЕТАЛОН. Всеки нов модул ТРЯБВА да следва тази спецификация 1:1.
**Референтен файл:** `/var/www/runmystore/chat.php` (2094 реда, commit c2caaf5)

---

## § A — ФУНДАМЕНТАЛНИ ПРИНЦИПИ

1. **Conic-gradient shine + glow на всяка "важна" карта** (revenue, briefing sections, overlay panels)
2. **Hue-matched визуални акценти** — всеки цвят има специфична семантика
3. **Pill форми 100px radius** за всички интерактивни елементи (бутони, pills, badges)
4. **Backdrop-blur(6-12px)** за всеки glass елемент
5. **Inset highlight 1px rgba(255,255,255,.04-.2)** отгоре на всеки контейнер за дълбочина
6. **Hue-tinted radial glows** в ъгли на важни елементи
7. **Text-shadow glow** на важни акценти (активни pills, gradient numbers, hue labels)
8. **Animation timings:** всички transitions `.15s` или `.2s` easing `cubic-bezier(0.5,1,0.89,1)`

---

## § B — ЦВЕТОВА СИСТЕМА

### B.1 Base tokens (CSS variables)

```css
:root{
    --hue1:255;             /* indigo/purple primary */
    --hue2:222;             /* blue secondary */
    --border:1px;
    --border-color:hsl(var(--hue2),12%,20%);
    --radius:22px;          /* glass cards */
    --radius-sm:14px;       /* smaller cards */
    --ease:cubic-bezier(0.5,1,0.89,1);
    --bg-main:#08090d;
    --text-primary:#f1f5f9;
    --text-secondary:rgba(255,255,255,.6);
    --text-muted:rgba(255,255,255,.4)
}
```

### B.2 Body background (MANDATORY)

```css
body{
    background:
        radial-gradient(ellipse 800px 500px at 20% 10%,hsl(var(--hue1) 60% 35% / .22) 0%,transparent 60%),
        radial-gradient(ellipse 700px 500px at 85% 85%,hsl(var(--hue2) 60% 35% / .22) 0%,transparent 60%),
        linear-gradient(180deg,#0a0b14 0%,#050609 100%);
    background-attachment:fixed
}
body::before{
    content:'';
    position:fixed;inset:0;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
    opacity:.03;
    pointer-events:none;z-index:1;mix-blend-mode:overlay
}
```

### B.3 Fundamental Question (6Q) hue mapping — BIBLE §6

| # | FQ key | Цвят | HSL | Използва за |
|---|---|---|---|---|
| 1 | `loss` (q1) | 🔴 Червен | `hsl(0,85%,55%)` | "Какво губиш" |
| 2 | `loss_cause` (q2) | 🟣 Виолет | `hsl(280,70%,62%)` | "От какво губиш" |
| 3 | `gain` (q3) | 🟢 Зелен | `hsl(145,70%,50%)` | "Какво печелиш" |
| 4 | `gain_cause` (q4) | 🔷 Teal | `hsl(175,70%,50%)` | "От какво печелиш" |
| 5 | `order` (q5) | 🟡 Амбър | `hsl(38,90%,55%)` | "Поръчай" |
| 6 | `anti_order` (q6) | ⚫ Сив | `hsl(220,10%,60%)` | "НЕ поръчвай" |

**Емоджи mapping:**
```php
'loss'       => '🔴', 'loss_cause' => '🟣',
'gain'       => '🟢', 'gain_cause' => '🔷',
'order'      => '🟡', 'anti_order' => '⚫'
```

### B.4 Urgency hue mapping

| Urgency | Цвят | Hex |
|---|---|---|
| `critical` | Червен glow | `#ef4444` |
| `warning` | Амбър glow | `#fbbf24` |
| `info` | Зелен glow | `#22c55e` |
| `passive` | **НЕ се показва в UI** | — |

### B.5 Статус / Action цветове

| Цел | Gradient |
|---|---|
| Primary action (бутони) | `linear-gradient(135deg,hsl(var(--hue1) 65% 50%),hsl(var(--hue2) 70% 45%))` |
| Send (positive) | `linear-gradient(135deg,#10b981,#059669)` |
| Cancel/Destructive | `rgba(239,68,68,.08)` + border `rgba(239,68,68,.2)` |
| Plan badges: PRO | `linear-gradient(135deg,hsl(280 70% 55%),hsl(300 70% 50%))` |
| Plan badges: START | `linear-gradient(135deg,hsl(220 70% 55%),hsl(240 70% 50%))` |
| Plan badges: FREE | `linear-gradient(135deg,#6b7280,#4b5563)` |

---

## § C — GLASS PATTERN (референция)

### C.1 Standard glass card

```css
.glass{
    position:relative;
    border-radius:var(--radius);
    border:var(--border) solid var(--border-color);
    background:
        linear-gradient(235deg,hsl(var(--hue1) 50% 10% / .8),hsl(var(--hue1) 50% 10% / 0) 33%),
        linear-gradient(45deg,hsl(var(--hue2) 50% 10% / .8),hsl(var(--hue2) 50% 10% / 0) 33%),
        linear-gradient(hsl(220deg 25% 4.8% / .78));
    backdrop-filter:blur(12px);
    -webkit-backdrop-filter:blur(12px);
    box-shadow:hsl(var(--hue2) 50% 2%) 0 10px 16px -8px,hsl(var(--hue2) 50% 4%) 0 20px 36px -14px;
    isolation:isolate
}
.glass.sm{--radius:var(--radius-sm)}
```

### C.2 Conic-gradient shine + glow (4 слоя)

**ВАЖНО:** Всеки `.glass` елемент ПРИЛАГА 4 span-а (shine top, shine bottom, glow top, glow bottom):

```html
<div class="glass">
    <span class="shine"></span>
    <span class="shine shine-bottom"></span>
    <span class="glow"></span>
    <span class="glow glow-bottom"></span>
    <!-- content -->
</div>
```

CSS (пълен, копирай от chat.php ~ред 420):

```css
.glass .shine,.glass .glow{--hue:var(--hue1)}
.glass .shine-bottom,.glass .glow-bottom{--hue:var(--hue2);--conic:135deg}
.glass .shine,
.glass .shine::before,
.glass .shine::after{
    pointer-events:none;
    border-radius:0;
    border-top-right-radius:inherit;
    border-bottom-left-radius:inherit;
    border:1px solid transparent;
    width:75%;aspect-ratio:1;
    display:block;position:absolute;
    right:calc(var(--border) * -1);top:calc(var(--border) * -1);
    left:auto;z-index:1;
    --start:12%;
    background:conic-gradient(from var(--conic,-45deg) at center in oklch,transparent var(--start,0%),hsl(var(--hue),var(--sat,80%),var(--lit,60%)),transparent var(--end,50%)) border-box;
    mask:linear-gradient(transparent),linear-gradient(black);
    mask-repeat:no-repeat;
    mask-clip:padding-box,border-box;
    mask-composite:subtract
}
.glass .shine::before,.glass .shine::after{content:"";width:auto;inset:-2px;mask:none}
.glass .shine::after{z-index:2;--start:17%;--end:33%;background:conic-gradient(from var(--conic,-45deg) at center in oklch,transparent var(--start,0%),hsl(var(--hue),var(--sat,80%),var(--lit,85%)),transparent var(--end,50%))}
.glass .shine-bottom{top:auto;bottom:calc(var(--border) * -1);left:calc(var(--border) * -1);right:auto}
.glass .glow{
    pointer-events:none;
    border-top-right-radius:calc(var(--radius) * 2.5);
    border-bottom-left-radius:calc(var(--radius) * 2.5);
    border:calc(var(--radius) * 1.25) solid transparent;
    inset:calc(var(--radius) * -2);
    width:75%;aspect-ratio:1;
    display:block;position:absolute;
    left:auto;bottom:auto;
    mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='240' height='240'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='3' seed='5'/%3E%3CfeColorMatrix values='0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    mask-mode:luminance;mask-size:29%;
    opacity:1;
    filter:blur(12px) saturate(1.25) brightness(0.5);
    mix-blend-mode:plus-lighter;
    z-index:3
}
.glass .glow.glow-bottom{inset:calc(var(--radius) * -2);top:auto;right:auto}
.glass .glow::before,.glass .glow::after{
    content:"";position:absolute;inset:0;
    border:inherit;border-radius:inherit;
    background:conic-gradient(from var(--conic,-45deg) at center in oklch,transparent var(--start,0%),hsl(var(--hue),var(--sat,95%),var(--lit,60%)),transparent var(--end,50%)) border-box;
    mask:linear-gradient(transparent),linear-gradient(black);
    mask-repeat:no-repeat;mask-clip:padding-box,border-box;mask-composite:subtract;
    filter:saturate(2) brightness(1)
}
.glass .glow::after{
    --lit:70%;--sat:100%;--start:15%;--end:35%;
    border-width:calc(var(--radius) * 1.75);
    border-radius:calc(var(--radius) * 2.75);
    inset:calc(var(--radius) * -.25);
    z-index:4;opacity:.75
}
```

---

## § D — КОМПОНЕНТИ (ЕТАЛОН)

### D.1 Header

```css
.header{display:flex;align-items:center;gap:8px;padding:4px 2px 12px}
.brand{
    font-size:11px;font-weight:900;
    letter-spacing:.12em;
    color:hsl(var(--hue1) 50% 70%);
    text-shadow:0 0 10px hsl(var(--hue1) 60% 50% / .3)
}
.header-icon-btn{
    width:28px;height:28px;border-radius:50%;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.05);
    color:var(--text-secondary);
    cursor:pointer;display:flex;align-items:center;justify-content:center
}
.header-icon-btn svg{width:12px;height:12px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
```

**SVG icon size:** 12×12px
**Border-radius:** 50% (кръг)

### D.2 Bottom Nav (4 таба)

```css
.bottom-nav{
    position:fixed;bottom:0;left:0;right:0;
    max-width:480px;margin:0 auto;display:flex;
    padding:8px 8px 14px;
    background:linear-gradient(180deg,hsl(220 25% 6% / .85),hsl(220 25% 4% / .95));
    backdrop-filter:blur(20px);
    border-top:1px solid hsl(var(--hue2) 20% 15% / .5);
    z-index:40
}
.nav-tab{
    flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;
    padding:7px 4px 4px;border-radius:12px;
    background:transparent;border:none;
    color:var(--text-muted);cursor:pointer;
    font-family:inherit;text-decoration:none
}
.nav-tab svg{width:20px;height:20px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.nav-tab-label{font-size:9px;font-weight:700;letter-spacing:.02em}
.nav-tab.active{color:hsl(var(--hue1) 60% 88%);text-shadow:0 0 8px hsl(var(--hue1) 70% 50% / .5)}
.nav-tab.active svg{filter:drop-shadow(0 0 6px hsl(var(--hue1) 70% 50% / .6))}
```

**4 таба за ВСЕКИ модул:**
1. AI (chat.php)
2. Склад (warehouse.php)
3. Справки (stats.php)
4. Продажба (sale.php)

**Active tab:** glow drop-shadow на SVG + text-shadow на label

**Safe-area-inset:** `padding-bottom:calc(14px + env(safe-area-inset-bottom))`

### D.3 Revenue Card (detailed модул главна)

```css
.rev-card{padding:16px 16px 14px;margin-bottom:12px}
.rev-val{
    font-size:34px;font-weight:900;letter-spacing:-.03em;
    background:linear-gradient(135deg,#fff 0%,hsl(var(--hue1) 60% 85%) 100%);
    -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
    line-height:1;font-variant-numeric:tabular-nums
}
.rev-cur{font-size:14px;color:var(--text-muted);font-weight:700}
.rev-change{font-size:15px;font-weight:900;color:#22c55e;text-shadow:0 0 8px rgba(34,197,94,.3)}
.rev-change.neg{color:#ef4444;text-shadow:0 0 8px rgba(239,68,68,.3)}
```

**Главното число:** 34px, font-weight:900, gradient бяло→hue1, tabular-nums

### D.4 Revenue/Period Pills (ЕТАЛОН за всички pill групи)

```css
.rev-pill-group{
    display:flex;gap:4px;padding:3px;
    background:rgba(0,0,0,.25);border-radius:100px;
    border:1px solid rgba(255,255,255,.04)
}
.rev-pill{
    padding:6px 12px;border-radius:100px;
    font-size:10px;font-weight:700;cursor:pointer;
    font-family:inherit;letter-spacing:.02em;
    border:none;background:transparent;color:rgba(255,255,255,.5);
    transition:all .2s
}
.rev-pill.active{
    background:linear-gradient(135deg,hsl(var(--hue1) 60% 45%),hsl(var(--hue2) 65% 40%));
    color:white;font-weight:800;
    box-shadow:
        0 2px 8px hsl(var(--hue1) 60% 45% / .4),
        inset 0 1px 0 rgba(255,255,255,.2),
        inset 0 0 12px hsl(var(--hue1) 70% 60% / .15);
    text-shadow:0 0 8px hsl(var(--hue1) 80% 70% / .5)
}
.rev-divider{width:1px;height:24px;background:linear-gradient(180deg,transparent,rgba(255,255,255,.1),transparent);margin:0 4px}
```

**Правило:** ЕТАЛОН за всеки segmented control (tabs, filter pills, period selectors)

### D.5 Health Bar (точност)

```css
.health{padding:10px 14px;margin-bottom:10px;--radius:var(--radius-sm);display:flex;align-items:center;gap:8px}
.health-track{flex:1;height:5px;border-radius:100px;background:rgba(255,255,255,.04);overflow:hidden}
.health-fill{
    height:100%;border-radius:100px;
    background:linear-gradient(90deg,#ef4444 0%,#f97316 25%,#eab308 50%,#84cc16 75%,#22c55e 100%)
}
```

**Rainbow gradient:** червен→оранж→жълт→лайм→зелен (за progress bars)

### D.6 Briefing Section — 6Q блок (ЕТАЛОН за всеки list item с fq категория)

```css
.briefing-section{
    position:relative;z-index:5;
    margin:10px 0;padding:14px 14px 12px;
    border-radius:14px;
    border:1px solid rgba(255,255,255,.06);
    background:
        linear-gradient(135deg,rgba(255,255,255,.025),rgba(0,0,0,.15)),
        linear-gradient(hsl(220 25% 6% / .6));
    backdrop-filter:blur(8px);
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.04),
        0 4px 12px rgba(0,0,0,.2);
    overflow:hidden
}
.briefing-section::before{
    content:'';position:absolute;
    top:0;left:0;bottom:0;width:3px;
    border-radius:14px 0 0 14px;
    background:linear-gradient(180deg,var(--qcol,transparent) 0%,transparent 100%);
    box-shadow:0 0 20px 1px var(--qcol,transparent);
    opacity:.9
}
.briefing-section::after{
    content:'';position:absolute;
    top:-1px;right:-1px;
    width:80px;height:80px;
    background:radial-gradient(circle at top right,var(--qcol,transparent) 0%,transparent 60%);
    opacity:.12;pointer-events:none
}
.briefing-section.q1{--qcol:hsl(0,85%,55%)}
.briefing-section.q2{--qcol:hsl(280,70%,62%)}
.briefing-section.q3{--qcol:hsl(145,70%,50%)}
.briefing-section.q4{--qcol:hsl(175,70%,50%)}
.briefing-section.q5{--qcol:hsl(38,90%,55%)}
.briefing-section.q6{--qcol:hsl(220,10%,60%)}
```

**Anatomy (обязат. ред):**
1. `.briefing-head` (emoji + name)
2. `.briefing-title` (главен текст 14px)
3. `.briefing-detail` (обяснение 12px)
4. `.briefing-items` (списък артикули, 2-3 items)
5. `.briefing-actions` (2 бутона: primary + secondary)

```css
.briefing-emoji{font-size:14px;filter:drop-shadow(0 0 6px var(--qcol,transparent))}
.briefing-name{
    font-size:9px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;
    color:var(--qcol);text-shadow:0 0 10px var(--qcol)
}
.briefing-title{font-size:14px;font-weight:800;color:#f1f5f9;line-height:1.4;margin-bottom:6px}
.briefing-detail{font-size:12px;font-weight:500;color:rgba(255,255,255,.7);line-height:1.55;margin-bottom:10px}
```

### D.7 Hue-matched Primary Button (ЕТАЛОН за category-specific action)

```css
.briefing-btn-primary{
    flex:1;padding:10px 12px;border-radius:100px;
    font-size:11px;font-weight:800;
    text-align:center;cursor:pointer;
    border:1px solid;font-family:inherit;
    text-decoration:none;display:flex;align-items:center;justify-content:center;gap:4px;
    letter-spacing:.02em;transition:transform .15s,box-shadow .15s;
    background:linear-gradient(135deg,
        color-mix(in oklch,var(--qcol) 35%,hsl(220 30% 10%)) 0%,
        color-mix(in oklch,var(--qcol) 20%,hsl(220 30% 8%)) 100%);
    border-color:color-mix(in oklch,var(--qcol) 50%,transparent);
    color:white;
    box-shadow:
        0 4px 14px color-mix(in oklch,var(--qcol) 35%,transparent),
        inset 0 1px 0 rgba(255,255,255,.12),
        inset 0 0 20px color-mix(in oklch,var(--qcol) 10%,transparent)
}
.briefing-btn-primary:active{transform:scale(.97)}
```

**Ключови техники:**
- `color-mix(in oklch, hue 35%, dark_bg)` — смесване на hue цвят с тъмен фон
- 3-слойно box-shadow (outer glow + inner highlight + inner radial glow)
- `scale(.97)` on active

### D.8 Secondary Glass Pill Button

```css
.briefing-btn-secondary{
    padding:10px 16px;border-radius:100px;
    font-size:11px;font-weight:700;
    text-align:center;cursor:pointer;
    font-family:inherit;letter-spacing:.02em;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.08);
    color:rgba(255,255,255,.7);
    backdrop-filter:blur(4px);
    transition:transform .15s,background .15s,color .15s
}
.briefing-btn-secondary:active{
    transform:scale(.97);
    background:rgba(255,255,255,.06);
    color:rgba(255,255,255,.95)
}
```

### D.9 Top-strip Pills (proactive notifications)

```css
.top-pill{
    flex-shrink:0;padding:8px 14px;border-radius:100px;
    cursor:pointer;font-size:10px;font-weight:700;line-height:1.2;
    background:linear-gradient(135deg,rgba(255,255,255,.04),rgba(0,0,0,.2));
    border:1px solid rgba(255,255,255,.08);
    color:#e2e8f0;backdrop-filter:blur(6px);
    display:flex;align-items:center;gap:7px;max-width:260px;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.05);
    transition:transform .15s
}
.top-pill.q1{  /* hue за loss */
    --qc:hsl(0,85%,55%);
    border-color:color-mix(in oklch,var(--qc) 40%,transparent);
    background:linear-gradient(135deg,
        color-mix(in oklch,var(--qc) 20%,hsl(220 30% 8%)),
        color-mix(in oklch,var(--qc) 8%,hsl(220 30% 6%)));
    box-shadow:
        0 2px 10px color-mix(in oklch,var(--qc) 25%,transparent),
        inset 0 1px 0 rgba(255,255,255,.1),
        inset 0 0 16px color-mix(in oklch,var(--qc) 12%,transparent)
}
```

**Scroll контейнер:** horizontal scroll с `scrollbar-width:none` (hidden scrollbar)

### D.10 75vh Overlay Panel (Chat / Signal Detail / Browser)

```css
.ov-bg{
    position:fixed;inset:0;
    background:rgba(5,8,20,.55);
    backdrop-filter:blur(16px) saturate(.85);
    -webkit-backdrop-filter:blur(16px) saturate(.85);
    z-index:200;opacity:0;pointer-events:none;
    transition:opacity .3s var(--ease)
}
.ov-bg.open{opacity:1;pointer-events:auto}
.ov-panel{
    position:fixed;bottom:-80vh;left:0;right:0;
    max-width:480px;margin:0 auto;
    height:75vh;z-index:210;
    display:flex;flex-direction:column;
    transition:bottom .35s var(--ease);
    border-radius:24px 24px 0 0;
    overflow:hidden;
    background:
        linear-gradient(235deg,hsl(var(--hue1) 50% 10% / .9),hsl(var(--hue1) 50% 8% / .7) 33%),
        linear-gradient(45deg,hsl(var(--hue2) 50% 10% / .9),hsl(var(--hue2) 50% 8% / .7) 33%),
        linear-gradient(hsl(220deg 30% 6% / .96));
    border:1px solid hsl(var(--hue1) 30% 25% / .5);
    border-bottom:none;
    backdrop-filter:blur(24px);
    box-shadow:
        0 -20px 60px rgba(0,0,0,.6),
        0 -8px 40px hsl(var(--hue1) 60% 45% / .15),
        inset 0 1px 0 hsl(var(--hue1) 60% 50% / .2)
}
.ov-panel.open{bottom:0}
.ov-panel::before{
    content:'';position:absolute;
    top:0;left:20%;right:20%;height:1px;
    background:linear-gradient(90deg,transparent,hsl(var(--hue1) 70% 65% / .7),transparent);
    z-index:5;pointer-events:none
}
.ov-handle{
    position:absolute;top:6px;left:50%;
    transform:translateX(-50%);
    width:38px;height:4px;border-radius:100px;
    background:rgba(255,255,255,.18);
    z-index:6;pointer-events:none
}
```

**Body overlay-open state** (blur зад overlay):
```css
body.overlay-open{overflow:hidden}
body.overlay-open .app{filter:blur(6px) brightness(.5);transform:scale(.97);pointer-events:none}
```

**Анимация open/close:** `bottom: -80vh → 0` в `.35s cubic-bezier(.32,0,.67,0)`

### D.11 Hardware Back Button + Swipe Down Close

**JavaScript** (копирай от chat.php):
```javascript
// Open
history.pushState({ov:'panelName'}, '');

// Popstate (hardware back)
window.addEventListener('popstate', e => {
    if (OV.panel) closePanel(true);
});

// ESC key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && OV.panel) closePanel();
});

// Swipe down from top 80px
let _touchY = 0;
panel.addEventListener('touchstart', e => { _touchY = e.touches[0].clientY; }, {passive:true});
panel.addEventListener('touchend', e => {
    const dy = e.changedTouches[0].clientY - _touchY;
    const rect = panel.getBoundingClientRect();
    if (_touchY < rect.top + 80 && dy > 80) closePanel();
}, {passive:true});
```

### D.12 Chat Messages (WhatsApp Neon)

```css
.msg-ai{
    max-width:82%;padding:10px 13px;
    font-size:13px;line-height:1.5;
    background:linear-gradient(135deg,hsl(var(--hue1) 30% 15% / .85),hsl(var(--hue1) 30% 10% / .7));
    border:1px solid hsl(var(--hue1) 35% 25% / .4);
    color:#e2e8f0;
    border-radius:4px 16px 16px 16px;  /* corner tail top-left */
    box-shadow:
        0 2px 12px rgba(0,0,0,.25),
        0 0 16px hsl(var(--hue1) 60% 40% / .08),
        inset 0 1px 0 hsl(var(--hue1) 60% 50% / .15);
    align-self:flex-start
}
.msg-user{
    max-width:75%;padding:10px 13px;
    font-size:13px;line-height:1.5;
    background:linear-gradient(135deg,hsl(var(--hue1) 55% 28%),hsl(var(--hue2) 60% 22%));
    border:1px solid hsl(var(--hue1) 55% 40% / .5);
    color:white;
    border-radius:16px 16px 4px 16px;  /* corner tail bottom-right */
    align-self:flex-end;
    box-shadow:
        0 2px 12px rgba(0,0,0,.35),
        0 0 14px hsl(var(--hue1) 60% 45% / .25),
        inset 0 1px 0 rgba(255,255,255,.1)
}
```

**Border-radius tail:** AI bubble tail top-left `4px`, User bubble tail bottom-right `4px`

### D.13 Input Bar (fixed bottom, glass pill)

```css
.input-bar{
    position:fixed;bottom:60px;left:0;right:0;
    max-width:480px;margin:0 auto;padding:8px 12px;
    z-index:35
}
.input-bar-inner{
    display:flex;align-items:center;gap:8px;
    padding:10px 14px;border-radius:100px;  /* PILL */
    background:linear-gradient(135deg,hsl(var(--hue1) 35% 15% / .85),hsl(var(--hue2) 35% 12% / .7));
    border:1px solid hsl(var(--hue1) 30% 25% / .6);
    backdrop-filter:blur(20px);
    box-shadow:0 8px 24px rgba(0,0,0,.35),0 0 16px hsl(var(--hue1) 60% 45% / .2)
}
```

**Safe-area:** `bottom:calc(60px + env(safe-area-inset-bottom))`

### D.14 Voice Mic Button (pulsing rings)

```css
.chat-mic{
    width:34px;height:34px;border-radius:50%;
    position:relative;
    background:linear-gradient(135deg,hsl(var(--hue1) 65% 50%),hsl(var(--hue2) 70% 45%));
    box-shadow:0 0 14px hsl(var(--hue1) 60% 45% / .45),inset 0 1px 0 rgba(255,255,255,.18)
}
.chat-mic.rec{
    background:linear-gradient(135deg,#ef4444,#dc2626);
    box-shadow:0 0 20px rgba(239,68,68,.55)
}
.voice-ring{
    position:absolute;border-radius:50%;
    border:1.5px solid rgba(255,255,255,.3);
    opacity:0;pointer-events:none
}
.vr1{width:22px;height:22px;animation:vrpulse 2s 0s ease-in-out infinite}
.vr2{width:32px;height:32px;animation:vrpulse 2s .3s ease-in-out infinite}
.vr3{width:44px;height:44px;animation:vrpulse 2s .6s ease-in-out infinite}
@keyframes vrpulse{0%{transform:scale(.5);opacity:.7}100%{transform:scale(1.6);opacity:0}}
```

### D.15 Rec Bar (recording indicator)

```css
.rec-bar{
    display:none;align-items:center;gap:8px;
    padding:8px 14px;
    background:linear-gradient(90deg,rgba(239,68,68,.08),rgba(239,68,68,.03));
    border-top:1px solid rgba(239,68,68,.15)
}
.rec-bar.on{display:flex}
.rec-dot{
    width:9px;height:9px;border-radius:50%;
    background:#ef4444;
    animation:recpulse 1s infinite;
    box-shadow:0 0 10px rgba(239,68,68,.7)
}
@keyframes recpulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.25)}}
```

### D.16 Toast

```css
.toast{
    position:fixed;bottom:80px;left:50%;
    transform:translateX(-50%);
    background:linear-gradient(135deg,hsl(var(--hue1) 60% 40%),hsl(var(--hue2) 65% 35%));
    color:white;padding:10px 18px;border-radius:100px;
    font-size:12px;font-weight:700;
    z-index:500;opacity:0;
    transition:opacity .3s,transform .3s;
    pointer-events:none;white-space:nowrap;
    box-shadow:0 8px 24px rgba(0,0,0,.4),0 0 16px hsl(var(--hue1) 60% 45% / .4)
}
.toast.show{opacity:1;transform:translateX(-50%) translateY(-6px)}
```

### D.17 Typing Dots

```css
.typing{
    padding:10px 14px;
    background:linear-gradient(135deg,hsl(var(--hue1) 30% 15% / .85),hsl(var(--hue1) 30% 10% / .7));
    border:1px solid hsl(var(--hue1) 35% 25% / .4);
    border-radius:4px 16px 16px 16px;
    width:fit-content
}
.typing-dots{display:flex;gap:4px}
.typing-dot{width:5px;height:5px;border-radius:50%;background:hsl(var(--hue1) 60% 70%);animation:tdot 1.2s infinite}
.typing-dot:nth-child(2){animation-delay:.2s}
.typing-dot:nth-child(3){animation-delay:.4s}
@keyframes tdot{0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-4px);opacity:1}}
```

### D.18 Signal Detail Hero Number (от сигнал)

```css
.sig-hero-num{
    font-size:42px;font-weight:900;
    letter-spacing:-.03em;
    font-variant-numeric:tabular-nums;
    line-height:1
}
.sig-hero-num.critical{color:#fca5a5;text-shadow:0 0 20px rgba(239,68,68,.4)}
.sig-hero-num.warning{color:#fcd34d;text-shadow:0 0 20px rgba(251,191,36,.4)}
.sig-hero-num.info{color:#86efac;text-shadow:0 0 20px rgba(34,197,94,.4)}
```

### D.19 FQ Badge (малка капсула с категория)

```css
.sig-fq-badge{
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 10px;border-radius:100px;
    font-size:9px;font-weight:800;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.06);
    letter-spacing:.03em
}
.sig-fq-badge.q1{color:#fca5a5;border-color:hsl(0,85%,50%,.35);background:rgba(239,68,68,.08)}
.sig-fq-badge.q2{color:#c4b5fd;border-color:hsl(280,70%,60%,.35);background:rgba(168,85,247,.08)}
.sig-fq-badge.q3{color:#86efac;border-color:hsl(145,70%,50%,.35);background:rgba(34,197,94,.08)}
.sig-fq-badge.q4{color:#5eead4;border-color:hsl(175,70%,50%,.35);background:rgba(20,184,166,.08)}
.sig-fq-badge.q5{color:#fcd34d;border-color:hsl(38,90%,55%,.35);background:rgba(251,191,36,.08)}
.sig-fq-badge.q6{color:#9ca3af;border-color:hsl(220,10%,50%,.35);background:rgba(107,114,128,.08)}
```

---

## § E — ТИПОГРАФИЯ

### E.1 Font

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
```

```css
body{font-family:'Montserrat',Inter,system-ui,sans-serif}
```

**Weight scale:** 400 (body), 500 (secondary text), 600-700 (muted labels), 800 (titles), 900 (key labels, big numbers)

### E.2 Font sizes (scale)

| Роля | Size | Weight | Letter-spacing |
|---|---|---|---|
| Big number (revenue) | 34px | 900 | -.03em |
| Hero number (signal detail) | 42px | 900 | -.03em |
| Title (card header) | 14px | 800 | -.01em |
| Body detail | 12px | 500-600 | 0 |
| Label (small caps) | 9-10px | 900 | .08-.1em (UPPERCASE) |
| Pill text | 10-11px | 700-800 | .02em |
| Input placeholder | 12-13px | 600 | .02em |
| Nav tab label | 9px | 700 | .02em |
| Sub-time (meta) | 9px | 600 | 0 |

### E.3 Line-height

- Body/detail: `1.4-1.55`
- Titles: `1.25-1.4`
- Single-line labels: `1` or `1.2`

### E.4 Tabular numerals

Винаги за числа — `font-variant-numeric:tabular-nums`:
- Price values
- Quantity
- Percentages
- Time formats

---

## § F — SHADOWS + GLOWS (система)

### F.1 Shadow levels

| Ниво | Случай | Shadow |
|---|---|---|
| L1 (inset highlight) | Всеки glass container | `inset 0 1px 0 rgba(255,255,255,.04-.2)` |
| L2 (card lift) | Standard card | `0 4px 12px rgba(0,0,0,.2)` |
| L3 (elevated) | Active pill, primary button | `0 4px 14-16px color-mix(--hue 35%,transparent)` |
| L4 (hero overlay) | 75vh overlay top | `0 -20px 60px rgba(0,0,0,.6)` |

### F.2 Glow techniques

**Text glow:**
```css
text-shadow: 0 0 8-10px hsl(var(--hue1) 80% 70% / .4-.5);
```

**SVG glow:**
```css
filter: drop-shadow(0 0 6px var(--qcol));
```

**Border glow (via box-shadow):**
```css
box-shadow: 0 0 20px 1px var(--qcol);
```

**Inset radial hue glow:**
```css
box-shadow: inset 0 0 20px color-mix(in oklch,var(--hue) 10%,transparent);
```

---

## § G — АНИМАЦИИ

### G.1 Timings

```css
--ease: cubic-bezier(0.5,1,0.89,1);   /* primary easing */
/* Alternative: cubic-bezier(.32,0,.67,0) for overlay slide-up */
```

### G.2 Transitions (стандартни)

| Компонент | Property | Duration |
|---|---|---|
| Buttons (tap feedback) | `transform` | `.15s` |
| Pill color change | `background, color, box-shadow` | `.2s` |
| Overlay slide | `bottom` | `.35s cubic-bezier(.32,0,.67,0)` |
| Overlay fade | `opacity` | `.3s var(--ease)` |
| Body blur | `filter, transform` | `.3s var(--ease)` |

### G.3 Keyframes (задължителни за модул)

```css
@keyframes cardin{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes vrpulse{0%{transform:scale(.5);opacity:.7}100%{transform:scale(1.6);opacity:0}}
@keyframes recpulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.25)}}
@keyframes tdot{0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-4px);opacity:1}}
@keyframes wavebar{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.4)}}
```

### G.4 Entry animation

Всяка нова карта/section при зареждане:
```css
animation: cardin .4s .1s ease both;
```

(stagger: 1-ви елемент `.1s`, 2-ри `.2s`, 3-ти `.3s`)

### G.5 Tap feedback

Всички интерактивни елементи:
```css
.element:active{transform:scale(.96-.98)}
```

### G.6 Vibrate feedback (haptics)

```javascript
function vib(n) { if (navigator.vibrate) navigator.vibrate(n || 6); }
// Call vib(6) on tap of interactive elements
```

Приложи на: `.sig-card, .sig-more, .nav-tab, .header-icon-btn, .store-sel, .health-link, .health-info, .top-pill, .rev-pill`

---

## § H — INTERACTIVE PATTERNS

### H.1 Store Selector (dropdown → pill)

```css
.store-sel{
    padding:3px 10px;border-radius:100px;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.06);
    color:var(--text-secondary);
    font-size:10px;font-weight:700;
    display:flex;align-items:center;gap:4px
}
.store-sel select{background:transparent;border:none;color:inherit;appearance:none}
```

### H.2 Segment Control (period + mode pills) — виж D.4

### H.3 Plan Badge

```css
.plan-badge{
    padding:3px 8px;border-radius:100px;
    color:white;font-size:9px;font-weight:900;letter-spacing:.08em;
    box-shadow:0 0 10px color-mix(...),inset 0 1px 0 rgba(255,255,255,.2)
}
```

(gradient spec: виж B.5)

---

## § I — LAYOUT (max-width + safe-area)

### I.1 Root container

```css
.app{
    position:relative;z-index:2;
    max-width:480px;
    margin:0 auto;
    padding:12px 12px 20px;
    transition:filter .3s var(--ease),transform .3s var(--ease)
}
body{padding-bottom:calc(140px + env(safe-area-inset-bottom))}
```

### I.2 Sticky elements (all max-width:480px)

- Input bar: `position:fixed;bottom:60px`
- Bottom nav: `position:fixed;bottom:0`
- Overlays: `position:fixed` (full width без max-width в самата overlay, но контентът е в max-width)

### I.3 Safe-area insets

```css
/* iOS notch + Android gesture bar */
.bottom-nav{padding-bottom:calc(14px + env(safe-area-inset-bottom))}
.input-bar{bottom:calc(60px + env(safe-area-inset-bottom))}
.chat-input{padding-bottom:calc(12px + env(safe-area-inset-bottom))}
```

---

## § J — GLOBAL BEHAVIORS

### J.1 Meta tags (задължителни)

```html
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
```

### J.2 No tap highlight + no text select

```css
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{-webkit-user-select:none;user-select:none}
```

### J.3 No scrollbars на основни scroll zones

```css
.scrollable{scrollbar-width:none;-webkit-overflow-scrolling:touch}
.scrollable::-webkit-scrollbar{display:none}
```

### J.4 `prefers-reduced-motion` (accessibility)

(Препоръка за бъдещи промени — в момента не е вдигнато)

---

## § K — ЗАБРАНЕНИ PATTERNS

НИКОГА в Neon Glass модул:

- ❌ Плосък фон без gradient/radial (цялото body без background layers)
- ❌ Square corners на бутони (всички PILL 100px ИЛИ rounded 12-16px за карти)
- ❌ Solid color бутони без box-shadow (винаги има поне 1 inset highlight + outer shadow)
- ❌ Native select dropdowns без custom стил (виж H.1)
- ❌ Bootstrap/Tailwind базирани компоненти (винаги vanilla CSS)
- ❌ CSS framework класове (btn-primary, rounded, shadow-lg и т.н.)
- ❌ `border:none` без `outline:none` (особено на textareas и бутони)
- ❌ `cursor:default` на clickable елементи (винаги `cursor:pointer`)
- ❌ Шрифт различен от Montserrat за UI елементи
- ❌ Цветове извън дефинираната hue system (избягвай ad-hoc `#4a5568`, винаги `rgba(255,255,255,.X)` или hue-based)
- ❌ Fixed px сегашни spacing без reason (използвай 4/6/8/10/12/14/16 прогресия)
- ❌ `overflow:auto` без hidden scrollbar styling (J.3)
- ❌ Transitions без easing функция (винаги `var(--ease)` или `.2s`)
- ❌ Gradient от само 2 еднакви цветове (винаги hue1→hue2 или hue+darkbg)
- ❌ **Magnetic hover** ефекти (cursor-следящи трансформации) — performance killer на mobile
- ❌ **Parallax scrolling** (background-attachment scroll-zависим) — лагва на iOS Safari
- ❌ **Gradient animations** (`background-position` keyframes) — repaint hell
- ❌ **Box-shadow transitions** в keyframes — не GPU-acceleratable
- ❌ Animation duration `>800ms` — над budget-а, чувства се "счупено"
- ❌ Transition / animation **без `cubic-bezier()` easing** (никога pure `linear` или дефолт)
- ❌ Animate-ване на `width`, `height`, `top`, `left`, `margin`, `padding` (винаги `transform` + `opacity`)

---

## § L — REFERENCE FILES

За всяка нова разработка — ВЗЕМИ от:

| Файл | Съдържание | Ред |
|---|---|---|
| `chat.php` | **ЕТАЛОН — ВСИЧКИ ПАТЪРНИ** | 2094 |
| `chat.php` ред 260-500 | Glass + shine + glow CSS | — |
| `chat.php` ред 600-800 | Revenue, Health, Weather, Briefing Section | — |
| `chat.php` ред 800-1050 | Overlays (75vh) | — |
| `chat.php` ред 1050-1200 | Chat messages, Input bar, Voice, Rec bar | — |
| `chat.php` ред 1200-1400 | Signal Detail, Browser | — |
| `home-neon-v2.html` (mockup) | Visual reference | — |

---

## § M — ADOPTION CHECKLIST (нов модул)

Преди да commit-неш нов модул:

- [ ] Import Montserrat font (E.1)
- [ ] CSS variables от B.1 дефинирани в `:root`
- [ ] Body background 3-layer (B.2)
- [ ] Body noise overlay (B.2 `::before`)
- [ ] `.app` container с max-width:480px (I.1)
- [ ] Safe-area-insets на всички fixed елементи (I.3)
- [ ] Header с brand + plan badge + icons (D.1)
- [ ] Bottom nav 4 tabs с active state glow (D.2)
- [ ] Glass cards със shine + glow 4 layers (C.1 + C.2)
- [ ] PILL бутони 100px radius (D.4, D.7, D.8)
- [ ] hue1-hue2 gradient за primary actions
- [ ] color-mix(in oklch, ...) за category-specific бутони
- [ ] q1-q6 hue mapping за fundamental_question (B.3)
- [ ] `body.overlay-open` blur behavior ако има overlay (D.10)
- [ ] Hardware back + swipe + ESC ако има overlay (D.11)
- [ ] Animation `cardin` на всеки нов елемент (G.4)
- [ ] `vib(6)` на tap feedback (G.6)
- [ ] `tabular-nums` на всички числа (E.4)
- [ ] Toast, typing dots, rec bar ако има AI chat (D.16-D.17)
- [ ] Page entrance (`.app` има `animation: pageIn`) — § O.2
- [ ] Card stagger клас на section групи (`.card-stagger`) — § O.3
- [ ] `.spring-tap` на всички interactive (бутони, pills, cards) — § O.4
- [ ] Overlay content fade-in (ако има modal `.ov-panel`) — § O.5
- [ ] `@media (prefers-reduced-motion: reduce)` блок реализиран — § O.6

---

## § N — ВЕРСИИ (update)

| Версия | Дата | Промени |
|---|---|---|
| v1.0 | 2026-04-19 | Инициализация след S74.7 |
| v1.1 | 2026-04-19 | S75.2 — Required Toggle pattern |
| **v2.0** | **2026-04-23** | **S79.POLISH2 — ПЪЛНА NEON GLASS СПЕЦИФИКАЦИЯ от chat.php v8.** Цветова система с 6Q hue mapping, glass pattern conic-shine+glow, всички компоненти, 75vh overlays, hardware back, hue-matched buttons (color-mix in oklch), typography scale, animations, забранени patterns, adoption checklist. |
| **v2.1** | **2026-04-27** | **S87.ANIMATIONS — 5 mandatory patterns. Live в chat.php.** § O Animation System v1: page entrance, card stagger, spring tap, overlay choreography, reduced-motion. Performance budget ≤800ms, само opacity+transform, GPU-only. |

---

## § O — ANIMATION SYSTEM v1 (S87 — MANDATORY)

### O.1 Philosophy

1. **Анимацията е език**, не декорация. Всяко движение информира потребителя за състоянието (вход, действие, навигация).
2. **Performance > visual.** GPU-only properties (`transform`, `opacity`). Никога `width/height/top/left/margin`.
3. **Reduced-motion е задължителен.** `@media (prefers-reduced-motion: reduce)` НЕ е препоръка — е mandatory accessibility (WCAG 2.3.3).
4. **5 patterns max.** Никакви ad-hoc keyframes извън тези 5. Дисциплина → cohesion.
5. **≤ 800ms budget** за entrance анимации. Над това = чувства се "счупено".

### O.2 PATTERN 1 — PAGE ENTRANCE

```css
@keyframes pageIn {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
.app { animation: pageIn .5s cubic-bezier(0.25,0.46,0.45,0.94) both; }
```

**Кога:** Първо зареждане на главния `.app` контейнер. Subtle fade + 12px lift.

### O.3 PATTERN 2 — CARD STAGGER

```css
@keyframes cardin {
    from { opacity: 0; transform: translateY(8px) scale(.98); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
.card-stagger > * {
    opacity: 0;
    animation: cardin .45s cubic-bezier(0.34,1.56,0.64,1) both;
}
.card-stagger > *:nth-child(1) { animation-delay: .05s; }
.card-stagger > *:nth-child(2) { animation-delay: .12s; }
.card-stagger > *:nth-child(3) { animation-delay: .19s; }
.card-stagger > *:nth-child(4) { animation-delay: .26s; }
.card-stagger > *:nth-child(5) { animation-delay: .33s; }
.card-stagger > *:nth-child(6) { animation-delay: .40s; }
.card-stagger > *:nth-child(7) { animation-delay: .47s; }
.card-stagger > *:nth-child(8) { animation-delay: .54s; }
.card-stagger > *:nth-child(n+9) { animation-delay: .60s; }
```

**Кога:** Контейнер с няколко carded children (briefing sections, q-cards). Spring easing за лек "поп". Replaces ad-hoc `cardin` от § G.4.

### O.4 PATTERN 3 — SPRING TAP FEEDBACK

```css
.spring-tap { transition: transform .15s cubic-bezier(0.34,1.56,0.64,1); }
.spring-tap:active { transform: scale(0.96); }
```

**Кога:** ВСЕКИ interactive елемент (бутони, pills, nav-tab, header icons, cards).
**Замества:** старите ad-hoc `:active{transform:scale(.96-.98)}` (§ G.5). Един клас вместо N декларации.
**Adoption rule:** add `class="... spring-tap"` на: `.briefing-btn-primary`, `.briefing-btn-secondary`, `.nav-tab`, `.header-icon-btn`, `.top-pill`, `.rev-pill`, `.sig-card`.

### O.5 PATTERN 4 — OVERLAY CONTENT CHOREOGRAPHY

```css
@keyframes overlayContentIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
.ov-panel.open .ov-content {
    animation: overlayContentIn .4s .15s cubic-bezier(0.25,0.46,0.45,0.94) both;
}
```

**Кога:** Когато overlay slide-up завърши (delay `.15s`), контентът се появява с втора фаза. Двуфазна choreography → усеща се "premium".

### O.6 PATTERN 5 — REDUCED MOTION (mandatory accessibility)

```css
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
    .app, .card-stagger > * { opacity: 1 !important; transform: none !important; }
}
```

**Защо:** WCAG 2.3.3 + потребители с vestibular disorders + iOS "Reduce Motion" toggle. **БЕЗ ИЗКЛЮЧЕНИЯ.**

### O.7 Adoption rules (checklist)

- [ ] Page entrance — `.app` има `animation: pageIn`
- [ ] Card stagger клас на section групи (`.card-stagger`)
- [ ] `.spring-tap` на всички interactive елементи
- [ ] Overlay content fade-in (ако има `.ov-panel`)
- [ ] `@media (prefers-reduced-motion: reduce)` блок реализиран

### O.8 Performance budget

- ❌ **НЕ** анимирай: `width`, `height`, `top`, `left`, `margin`, `padding`, `box-shadow` (repaint hell, без GPU)
- ✅ **САМО** `transform` (translate/scale/rotate) + `opacity` (composited на GPU)
- ⚠ **≤ 5 елемента едновременно** в keyframe анимация (повече = jank на mid-tier Android)
- ⚠ **GPU-only.** Ако елементът има `filter`/`backdrop-filter` + анимация → проверявай Chrome DevTools "Rendering > Paint flashing"

### O.9 Reference

**Live имплементация:** `chat.php` (S87.ANIMATIONS commit, 2026-04-27). Чети style блока 422-1796 за пълните 5 patterns в работещ контекст.

### O.10 Forbidden patterns (виж § K за пълен списък)

- ❌ Magnetic hover (cursor-следящи трансформации)
- ❌ Parallax scrolling (`background-attachment` зависимости)
- ❌ Gradient animations (`background-position` keyframes)
- ❌ Box-shadow transitions/keyframes
- ❌ Duration `> 800ms`
- ❌ Animation/transition без `cubic-bezier()` easing
- ❌ Animate-ване на `width/height/top/left` (виж O.8)

---

**КРАЙ НА DESIGN SYSTEM v2.1**

*Референтен модул: `chat.php` v8 (commit c2caaf5). Всеки нов модул ТРЯБВА да премине adoption checklist § M.*
