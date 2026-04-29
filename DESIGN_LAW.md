# 🎨 DESIGN LAW — RunMyStore.ai

> Пълен опис на дизайна. Всеки елемент описан детайлно. Без препратки към други файлове.

---

# 1. РОЛЯ

Дизайнерски агент. Пипаш САМО визията: CSS, HTML wrappers, класове, цветове, layout, animations, SVG icons.

Ти НЕ пипаш: PHP, DB, API, i18n, валидации, routing, бизнес логика.

Ако заявката е смесена — правиш САМО визията.

---

# 2. БАЗОВА ПАЛИТРА

## 2.1 Текстови цветове
- **Основен текст:** `#f1f5f9` (почти бяло)
- **Вторичен текст:** `rgba(255,255,255,0.6)` (60% прозрачно бяло)
- **Заглушен текст:** `rgba(255,255,255,0.4)` (40% прозрачно бяло)
- **Сив текст:** `#6b7280` (среден сив)

## 2.2 Фонов цвят
- **Главен фон:** `#08090d` (почти черно с лек син оттенък)

## 2.3 Семантични цветове
- **Червен (danger / loss):** `#ef4444`
- **Амбър (warning / order):** `#f59e0b`
- **Зелен (success / gain):** `#22c55e`
- **Виолет (premium / AI magic):** `#8b5cf6`

## 2.4 Индиго скала (главен brand цвят)
- **Indigo 600 (тъмно):** `#4f46e5`
- **Indigo 500 (главен):** `#6366f1`
- **Indigo 400 (светло):** `#818cf8`
- **Indigo 300 (много светло):** `#a5b4fc`

## 2.5 Hue класове (прилагат се с `class="q-magic"` и т.н.)

| Клас | Hue1 | Hue2 | Усещане | Кога се ползва |
|---|---|---|---|---|
| `q-default` | 255 | 222 | индиго | Главни карти, hero, неутрално |
| `q-magic` | 280 | 310 | виолет | AI insights, magic, premium |
| `q-loss` | 0 | 15 | червен | Alert, "губиш", грешки |
| `q-gain` | 145 | 165 | зелен | Success, "печелиш" |
| `q-amber` | 38 | 28 | амбър | Warning, "поръчай" |
| `q-jewelry` | 38 | 28 | топъл амбър | Premium tier |

## 2.6 Product visuals (ИЗКЛЮЧЕНИЕ от палитрата)

**Структурни компоненти** (карти, бутони, badges, header, nav, FAB, alert, AI insight) ползват САМО hue класовете от 2.5.

**Но има едно изключение:** малки визуални елементи които представят **реални данни на продукта** — не структурата на UI-а. Тези елементи могат да ползват свой собствен цвят който отговаря на реалния продукт.

**Какво е "product visual":**
- Color swatch в продуктова карта (28×28 кръг показващ "този продукт е черен/бял/син")
- Plan/tier indicator с brand-specific цвят
- Brand logo gradient (когато показва конкретна марка)
- Иконка на категория с реален emoji (👕 / 💎)

**Какво НЕ е product visual:**
- Border на цялата карта — структурно, ползва q-default
- Box-shadow на карта — структурно, ползва hue от q-*
- Заглавие/текст color на карта — структурно
- Бутон "Запази" — структурно
- Alert banner border — структурно

## 2.6.1 Color swatch палитра

Когато трябва да показваш реален цвят на продукт (бельо черно, тениска синя, дънки бежови), ползваш тези готови swatch стилове:

| Цвят | Background | Glow color (за box-shadow) |
|---|---|---|
| Черно | `linear-gradient(135deg, #2a2a2e, #0f0f12)` | `hsl(220, 50%, 50%)` |
| Бяло | `linear-gradient(135deg, #f8f8fb, #d8d8de)` | `hsl(220, 30%, 80%)` |
| Синьо | `linear-gradient(135deg, #3b82f6, #1d4ed8)` | `hsl(220, 90%, 60%)` |
| Червено | `linear-gradient(135deg, #ef4444, #b91c1c)` | `hsl(0, 90%, 55%)` |
| Бежово | `linear-gradient(135deg, #e8c39e, #c08e5d)` | `hsl(35, 70%, 60%)` |
| Розово | `linear-gradient(135deg, #ec4899, #be185d)` | `hsl(335, 85%, 60%)` |
| Зелено | `linear-gradient(135deg, #22c55e, #15803d)` | `hsl(145, 80%, 50%)` |
| Жълто | `linear-gradient(135deg, #fbbf24, #d97706)` | `hsl(45, 95%, 55%)` |
| Виолетово | `linear-gradient(135deg, #a855f7, #7c3aed)` | `hsl(270, 80%, 60%)` |
| Сиво | `linear-gradient(135deg, #9ca3af, #4b5563)` | `hsl(220, 10%, 60%)` |
| Кафяво | `linear-gradient(135deg, #92400e, #451a03)` | `hsl(25, 70%, 30%)` |

## 2.6.2 Color swatch елемент — стилове

```css
.swatch {
    width: 28px;            /* или 30px за по-голям */
    height: 28px;
    border-radius: 8px;     /* или 50% за кръг */
    border: 1.5px solid hsl(220, 30%, 30%, 0.4);
    box-shadow:
        0 0 8px var(--swatch-glow, currentColor),
        inset 0 1px 0 rgba(255,255,255,0.18);
    flex-shrink: 0;
}
```

Цветът се прилага през `style` attribute или клас `c-black`, `c-white`, `c-blue` и т.н. Това е **позволено инline на data-level**, защото не е структурен hue.

**Пример (правилно):**
```html
<div class="swatch c-black"></div>
<!-- или -->
<div class="swatch" style="background: linear-gradient(135deg, #2a2a2e, #0f0f12);"></div>
```

## 2.6.3 ВАЖНО — какво се запазва структурно

Дори в карта която показва "Черно бельо" / "Бяло бельо" / "Синьо бельо":
- **Картата (`.glass`) остава `q-default`** (индиго neon)
- **Border, shadow, glow на картата — индиго** (от q-default)
- **Заглавие "Черно" — бяло/secondary текст** (не оцветено)
- **Само swatch-ът показва реалния цвят на продукта**

Така всички 3 цветни карти изглеждат като братя (един consistent UI), а реалният цвят на продукта се вижда от 28×28 swatch-а.

## 2.6.4 Когато се пита потребителят (за product visuals)

- Ако цветът на продукта не е в таблица 2.6.1 → питаш потребителя
- Ако трябва нов начин за показване на product data (напр. размер pill, doсtaвчик emblem) → питаш потребителя
- НЕ измисляш custom hue за structural component, дори ако "пасва" на product visual

---

# 3. ШРИФТ

**Единствен шрифт:** Montserrat (Google Fonts).

**Тегла:** 400, 500, 600, 700, 800, 900.
Никога не ползваш 100/200/300.

**Импорт винаги в `<head>`:**
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
```

**Размерна скала (никога не правиш ad-hoc):**

| Размер | Тегло | Кога |
|---|---|---|
| 8px | 800 | Tile-sig pill, мини-labels |
| 9px | 800-900 | brand text, FQ labels, hero KPI label |
| 10px | 600-800 | meta text, sub-text, small label |
| 11px | 700-800 | secondary label, button text small |
| 12px | 800 | small button, pill text |
| 13px | 800-900 | card title, item text |
| 14px | 800 | section title, big card title |
| 17px | 800 | page title (wizard) |
| 26px | 900 | hero title (dashboard) |
| 34px | 900 | main stat number |
| 54px | 900 | hero stat number |

---

# 4. РАЗМЕРИ И РАДИУСИ

## 4.1 Border-radius — позволени стойности
- **6px** — tile-sig малки pill
- **7-8px** — малки иконки, swatches, labels
- **8-11px** — sub-cards, photo thumbs, icon squares
- **9px** — chip select
- **10-11px** — back button, icon container
- **12px** — input fields
- **14px** (--radius-sm) — sub-card, briefing-section, alert
- **16px** — bottom-bar бутон
- **22px** (--radius) — главна glass карта
- **100px** — pill (бутони, badge, chips)
- **50%** — кръг (FAB, mic, swatch)

## 4.2 Padding — стойности

| Стойност | Кога |
|---|---|
| 2-4px | micro gaps между sub-elements |
| 6-8px | tight spacing |
| 10-12px | стандартни padding-и |
| 14px | глinна card padding |
| 16-18px | hero / large card |
| padding 5px 12px | pill бутон |
| padding 10px 18px | primary бутон |
| padding 14px | input |

## 4.3 Spacing scale
**Само тези стойности:** 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 24, 32px.
Всичко друго е нарушение.

---

# 5. BODY (винаги еднакъв)

## 5.1 3-слойно фоново изображение
```css
body {
    background:
        radial-gradient(ellipse 800px 500px at 20% 10%, hsl(var(--hue1) 60% 35% / 0.22) 0%, transparent 60%),
        radial-gradient(ellipse 700px 500px at 85% 85%, hsl(var(--hue2) 60% 35% / 0.22) 0%, transparent 60%),
        linear-gradient(180deg, #0a0b14 0%, #050609 100%);
    background-attachment: fixed;
}
```

**Какво прави всеки слой:**
- Слой 1: Indigo radial glow горе-ляво (800×500px ellipse, 22% алфа)
- Слой 2: Blue radial glow долу-дясно (700×500px ellipse, 22% алфа)
- Слой 3: Linear gradient `#0a0b14` → `#050609` (тъмно индиго към почти черно)

## 5.2 Noise overlay (винаги)
```css
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
    opacity: 0.03;
    pointer-events: none;
    z-index: 1;
    mix-blend-mode: overlay;
}
```

Това е тънка SVG turbulence текстура с 3% видимост, mix-blend overlay. Без този слой → не е Neon Glass.

---

# 6. APP CONTAINER

```css
.app {
    position: relative;
    z-index: 2;
    max-width: 480px;
    margin: 0 auto;
    width: 100%;
}
```

Mobile-first. Ширина 480px max. Центриран.

---

# 7. GLASS КАРТА (основният компонент)

## 7.1 Базови стилове
```css
.glass {
    border-radius: 22px;
    border: 1px solid hsl(var(--hue2), 12%, 20%);
    background:
        linear-gradient(235deg, hsl(var(--hue1) 50% 10% / 0.8), transparent 33%),
        linear-gradient(45deg, hsl(var(--hue2) 50% 10% / 0.8), transparent 33%),
        linear-gradient(hsl(220deg 25% 4.8% / 0.78));
    backdrop-filter: blur(12px);
    box-shadow:
        hsl(var(--hue2) 50% 2%) 0 10px 16px -8px,
        hsl(var(--hue2) 50% 4%) 0 20px 36px -14px;
    isolation: isolate;
    position: relative;
}
.glass.sm { border-radius: 14px; }
```

## 7.2 4-span задължителен pattern
**Всяка `.glass` карта има точно 4 spans вътре:**
```html
<div class="glass">
    <span class="shine"></span>
    <span class="shine shine-bottom"></span>
    <span class="glow"></span>
    <span class="glow glow-bottom"></span>
    <!-- съдържание тук -->
</div>
```

**Какво правят:**
- `.shine` (горе-вдясно) — конусен gradient hue1, ярка тънка линия в десния горен ъгъл
- `.shine-bottom` (долу-вляво) — конусен gradient hue2, ярка тънка линия в левия долен ъгъл
- `.glow` (горе-вдясно) — размит neon glow с noise mask, hue1
- `.glow-bottom` (долу-вляво) — размит neon glow, hue2

## 7.3 Съдържанието вътре
**Винаги:** Всеки елемент-съдържание вътре в `.glass` трябва да има:
```css
position: relative;
z-index: 5;
```
Иначе се крие зад псевдо-елементите.

---

# 8. БУТОНИ — всички видове

## 8.1 Primary бутон (главно действие)
- **Размер:** padding 10-13px вертикално, 18px странично
- **Радиус:** 100px (pill) ИЛИ 11-13px (rounded rect)
- **Фон:** `linear-gradient(135deg, hsl(hue1, 70%, 38%), hsl(hue2, 70%, 32%))`
- **Border:** `1.5px solid hsl(hue1, 75%, 60%, 0.85)`
- **Color:** `hsl(hue1, 95%, 96%)` (почти бяло, тинт от hue)
- **Font:** Montserrat 12-13px, weight 900, letter-spacing 0.04em
- **Text-shadow:** `0 0 7px hsl(hue1, 90%, 70%, 0.5)`
- **Box-shadow:**
  ```
  0 0 16px hsl(hue1, 75%, 55%, 0.45),
  inset 0 1px 0 hsl(hue1, 80%, 70%, 0.3)
  ```
- **Active (натиснат):** `transform: translateY(1px)`
- **Cursor:** pointer

## 8.2 Save / Confirm бутон (зелен)
- Същата структура като 8.1
- **Hue1 = 145** (зелен)
- Често с галка ✓ icon отляво (SVG `polyline 20 6 9 17 4 12`)
- Текст: бял, weight 900

## 8.3 Danger / Cancel бутон
- **Фон:** `rgba(239,68,68,0.08)`
- **Border:** `1px solid rgba(239,68,68,0.22)`
- **Color:** `#fca5a5` (светло червен текст)
- **Box-shadow:** няма heavy glow
- За X icon: stroke `#fca5a5`, stroke-width 2.5

## 8.4 Pill бутон (small action)
- **Padding:** 5px 12px
- **Радиус:** 100px
- **Фон:** `rgba(255,255,255,0.03)` до `rgba(255,255,255,0.06)`
- **Border:** `1px solid rgba(255,255,255,0.05)` до `rgba(255,255,255,0.08)`
- **Color:** `rgba(255,255,255,0.6)` (secondary)
- **Font:** 10-11px, weight 700-800

## 8.5 Icon бутон (28-32px)
- **Размер:** 28×28 или 32×32 px
- **Радиус:** 50% (кръг) или 8-11px (squircle)
- **Фон:** `rgba(255,255,255,0.03)`
- **Border:** `1px solid rgba(255,255,255,0.05)`
- **Color:** secondary text
- **SVG вътре:** 12-14px, stroke 2px, fill none, stroke-linecap round, stroke-linejoin round

## 8.6 Mic / Voice бутон
- **Размер:** 32×32 кръг (50% radius)
- **Фон:** `linear-gradient(135deg, hsl(hue1, 65%, 50%), hsl(hue2, 70%, 45%))`
- **Box-shadow:** `0 0 14px hsl(hue1, 60%, 45%, 0.4)`
- **SVG:** 14-16px, бял (stroke white или fill white)

## 8.7 FAB (Floating Action Button)
- **Размер:** 56-60px кръг
- **Position:** fixed, bottom 68-72px (над bottom nav), left 50%, transform translateX(-50%)
- **Z-index:** 45
- **Фон:** `linear-gradient(135deg, hsl(hue1, 70%, 55%), hsl(hue2, 70%, 50%))`
- **Box-shadow:**
  ```
  0 0 32px hsl(hue1, 70%, 50%, 0.6),
  0 8px 28px rgba(0,0,0,0.4),
  inset 0 1px 0 rgba(255,255,255,0.3)
  ```
- **Двойна ringing animation:** 2 pseudo-елемента (`::before`, `::after`):
  ```css
  inset: -6px;
  border: 2px solid hsl(hue1, 70%, 60%, 0.45);
  animation: fabRing 2.5s ease-out infinite;
  /* ::after с delay 1.25s */
  ```

## 8.8 Stepper бутон (-/+)
- **Размер:** 30×30px
- **Радиус:** 8px
- **Фон:** `hsl(hue1, 60%, 35%, 0.25)` — тинт от родителската карта
- **Border:** `1px solid hsl(hue1, 60%, 50%, 0.4)`
- **Color:** `hsl(hue1, 85%, 85%)`
- **Font:** 16px, weight 800
- **Active:** `transform: scale(0.92)`

---

# 9. КАРТИ (видове)

## 9.1 Hero card (на dashboard страница)
- **Padding:** 18px 16px 14px
- **Radius:** 22px
- **Текст:** centered (text-align: center)
- **Структура:**
  1. Hero icon — 56×56 squircle (radius 16px), gradient hue1→hue2, glow shadow
  2. Title — 26px, weight 900, letter-spacing -0.03em, **tricolor gradient text:** `linear-gradient(135deg, #fff 0%, hsl(hue1, 70%, 85%) 50%, hsl(hue2, 70%, 80%) 100%)` с `-webkit-background-clip: text`
  3. Sub — 11px, weight 700, color `hsl(hue1, 60%, 78%)`
  4. (опционално) Health bar — описан в 9.2
  5. (опционално) KPI grid — 4 cells

## 9.2 Health bar (вътре в hero)
- **Wrapper:** `padding: 9px 12px; border-radius: 11px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.04)`
- **Label:** 8px, weight 800, uppercase, letter-spacing 0.08em, muted color
- **Track:** flex 1, height 5px, radius 100px, background `rgba(255,255,255,0.05)`
- **Fill:** 5-color gradient `#ef4444 → #f97316 → #eab308 → #84cc16 → #22c55e`, box-shadow `0 0 8px currentColor`
- **Pct:** 11px, weight 900, tabular-nums, color `#86efac` (зелен)

## 9.3 KPI cell (в hero)
- **Размер:** padding 8px 4px, radius 9px
- **Фон:** `rgba(255,255,255,0.025)`, border `1px solid rgba(255,255,255,0.04)`
- **Number:** 17px, weight 900, letter-spacing -0.02em, tabular-nums, color white
- **Number warning:** color `#fbbf24` с text-shadow `0 0 10px rgba(251,191,36,0.4)`
- **Label:** 8px, weight 800, uppercase, letter-spacing 0.06em, muted

## 9.4 Sub-card / glass.sm
- **Padding:** 12-14px
- **Radius:** 14px
- **За:** alert banners, AI insights, copied info, color cards

## 9.5 Tile (в grid 2×N)
- **Размер:** min-height 102px
- **Padding:** 14px 13px
- **Radius:** 14px
- **Active:** `transform: scale(0.97)`
- **Структура:**
  - Tile-head: ico 36×36 (gradient) отляво + badge 100px pill отдясно
  - Tile-body: title (13px, 900) + desc (9.5px, 600) + tile-sig pill (9px, 800)
- **Tile-ico variants (i1-i8):** разни hue gradient-и:
  - i1: indigo 255 → 222
  - i2: amber 35 → 25
  - i3: green 145 → 160
  - i4: teal 175 → 190
  - i5: violet 280 → 310
  - i6: red 0 → 15
  - i7: orange 15 → 30
  - i8: blue 220 → 240

## 9.6 Tile-ico (36×36)
- **Размер:** 36×36px
- **Radius:** 10px
- **Фон:** linear-gradient в hue
- **Box-shadow:** `0 0 12px hsl(hue, 65%, 50%, 0.45), inset 0 1px 0 rgba(255,255,255,0.2)`
- **SVG вътре:** 16×16, бяло stroke, stroke-width 2

---

# 10. INPUT ПОЛЕТА

## 10.1 Текстов input
- **Padding:** 12-14px
- **Radius:** 12px
- **Фон:** `rgba(0,0,0,0.4)` (тъмен)
- **Border:** `1px solid rgba(99,102,241,0.4)` (subtle indigo)
- **Color:** `#f1f5f9`
- **Font:** Montserrat 14-16px, weight 700
- **Outline:** none
- **На focus:**
  - Border: `hsl(255, 75%, 65%)` (ярко индиго)
  - Box-shadow: `0 0 0 1px hsl(255, 80%, 65%, 0.3), 0 0 18px hsl(255, 70%, 55%, 0.35)`
- **Placeholder:** `color: #6b7280, font-weight: 500`

## 10.2 Number stepper (бройка ± бутони + цифра)
- Описани в 8.8
- Цифрата помежду: 16px, weight 800, tabular-nums, `min-width: 44px; text-align: center`

---

# 11. PILLS, BADGES, CHIPS

## 11.1 Plan badge (PRO/START/FREE/BIZ)
- **Padding:** 3px 8px
- **Radius:** 100px
- **Font:** 9px, weight 900, letter-spacing 0.08em
- **Color:** white
- **Box-shadow base:** `0 0 10-12px [hue glow], inset 0 1px 0 rgba(255,255,255,0.2)`

| Plan | Gradient |
|---|---|
| PRO | `hsl(280, 70%, 55%) → hsl(300, 70%, 50%)` (виолет) |
| START | `hsl(220, 70%, 55%) → hsl(240, 70%, 50%)` (синьо) |
| FREE | `#6b7280 → #4b5563` (сиво) |
| BIZ | `hsl(38, 90%, 55%) → hsl(20, 85%, 50%)` (амбър-fire) |

## 11.2 Status badge (брой/процент в tile)
- **Padding:** 3px 8px
- **Radius:** 100px
- **Font:** 10px, weight 900, tabular-nums, letter-spacing 0.04em

| Variant | Фон | Border | Color |
|---|---|---|---|
| `.badge.red` | `rgba(239,68,68,0.15)` | `1px rgba(239,68,68,0.3)` | `#fca5a5` |
| `.badge.amber` | `rgba(245,158,11,0.15)` | `1px rgba(245,158,11,0.3)` | `#fbbf24` |
| `.badge.green` | `rgba(34,197,94,0.12)` | `1px rgba(34,197,94,0.3)` | `#86efac` |
| `.badge.blue` | `rgba(129,140,248,0.12)` | `1px rgba(129,140,248,0.28)` | `#a5b4fc` |
| `.badge.violet` | `rgba(139,92,246,0.12)` | `1px rgba(139,92,246,0.3)` | `#c4b5fd` |

## 11.3 Tile signal pill
- **Padding:** 3px 8px
- **Radius:** 6px (по-square от badge)
- **Font:** 9px, weight 800, letter-spacing 0.02em
- **Display:** inline-block

| Variant | Фон | Border | Color |
|---|---|---|---|
| `.tile-sig.loss` | `rgba(239,68,68,0.08)` | `1px rgba(239,68,68,0.2)` | `#fca5a5` |
| `.tile-sig.warn` | `rgba(245,158,11,0.08)` | `1px rgba(245,158,11,0.2)` | `#fcd34d` |
| `.tile-sig.ok` | `rgba(34,197,94,0.08)` | `1px rgba(34,197,94,0.2)` | `#86efac` |
| `.tile-sig.info` | `rgba(129,140,248,0.08)` | `1px rgba(129,140,248,0.2)` | `#a5b4fc` |
| `.tile-sig.violet` | `rgba(139,92,246,0.08)` | `1px rgba(139,92,246,0.2)` | `#c4b5fd` |

## 11.4 Selectable chip (избор от опции)
- **Padding:** 9px 8px
- **Radius:** 9px
- **Default state:**
  - Фон: `rgba(255,255,255,0.025)`
  - Border: `1px solid rgba(255,255,255,0.08)`
  - Color: secondary text
- **Selected state (`.sel`):**
  - Фон: `linear-gradient(135deg, hsl(hue1, 60%, 25%, 0.6), hsl(hue1, 60%, 18%, 0.6))`
  - Border: `hsl(hue1, 70%, 55%, 0.65)`
  - Box-shadow: `0 0 10px hsl(hue1, 70%, 50%, 0.35), inset 0 1px 0 hsl(hue1, 70%, 60%, 0.2)`
  - Color: `hsl(hue1, 90%, 88%)` с text-shadow `0 0 5px hsl(hue1, 80%, 55%, 0.35)`

---

# 12. RMS HEADER (винаги еднакъв във всички модули)

## 12.1 Какво е

Глобален sticky header горе на всяка страница. **Винаги един и същ.** 8 елемента в строг ред отляво надясно. Не се променя от модул в модул. Никога не се пише наново — винаги се include-ва от `partials/header.php`.

## 12.2 Структура отляво надясно (ЗАДЪЛЖИТЕЛЕН РЕД)

```
[1: BRAND] [2: PLAN BADGE] [3: SPACER] [4: ПЕЧАТ] [5: НАСТРОЙКИ] [6: ИЗХОД] [7: ТЕМА]
```

| # | Елемент | Клас | Action |
|---|---|---|---|
| 1 | RUNMYSTORE.AI logo-link | `.rms-brand` | → chat.php (home) |
| 2 | Plan badge (FREE/START/PRO) | `.rms-plan-badge` + плановия клас | → settings.php (smjana plan) |
| 3 | Празно flex пространство | `.rms-header-spacer` | — |
| 4 | Bluetooth printer бутон | `.rms-icon-btn .rms-print` | → отваря printer popup |
| 5 | Настройки gear icon | `.rms-icon-btn` | → settings.php |
| 6 | Изход (logout) icon | `.rms-icon-btn` | → logout.php (с dropdown потвърждение) |
| 7 | Тема (sun/moon) toggle | `.rms-icon-btn` | → превключва светла/тъмна тема |

## 12.3 CSS на контейнера

```css
.rms-header {
    position: sticky;
    top: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: max(12px, calc(env(safe-area-inset-top, 0px) + 12px)) 16px 12px;
    background: rgba(3, 7, 18, 0.93);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}
.rms-header.scrolled {
    /* Когато потребителят скролне надолу >30px */
    background: rgba(3, 7, 18, 0.98);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}
```

## 12.4 Brand link (`.rms-brand`)

```css
.rms-brand {
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.12em;
    color: hsl(255, 50%, 70%);
    text-shadow: 0 0 12px hsl(255, 70%, 60%, 0.25);
    text-decoration: none;
    cursor: pointer;
}
```
**Винаги пише:** `RUNMYSTORE.AI` (uppercase, никога с малки)

## 12.5 Plan badge (`.rms-plan-badge`)

```css
.rms-plan-badge {
    padding: 3px 8px;
    border-radius: 100px;
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.08em;
    color: white;
    box-shadow: 0 0 12px [hue], inset 0 1px 0 rgba(255,255,255,0.2);
}
```

| Plan клас | Background gradient | Glow color |
|---|---|---|
| `.rms-plan-badge.free` | `#6b7280 → #4b5563` | `rgba(107,114,128,0.4)` |
| `.rms-plan-badge.start` | `hsl(220 70% 55%) → hsl(240 70% 50%)` | `hsl(220 70% 50% / 0.4)` |
| `.rms-plan-badge.pro` | `hsl(280 70% 55%) → hsl(300 70% 50%)` | `hsl(280 70% 50% / 0.4)` |
| `.rms-plan-badge.biz` | `hsl(38 90% 55%) → hsl(20 85% 50%)` | `hsl(38 90% 50% / 0.4)` |

Текст в badge: `FREE` / `START` / `PRO` / `BIZ` (винаги uppercase).

## 12.6 Header spacer

```css
.rms-header-spacer {
    flex: 1;
}
```
Това избутва icon бутоните вдясно. Без този елемент header-ът се чупи.

## 12.7 Icon бутон (`.rms-icon-btn`) — ползван за printer / settings / logout / theme

```css
.rms-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    text-decoration: none;
    transition: all 0.2s var(--ease);
    padding: 0;
}
.rms-icon-btn:hover {
    color: var(--indigo-300);
    border-color: rgba(99, 102, 241, 0.4);
    background: rgba(255, 255, 255, 0.06);
}
.rms-icon-btn svg {
    width: 14px;
    height: 14px;
    stroke: currentColor;
    stroke-width: 2;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}
```

## 12.8 SVG icons за всеки бутон (фиксирани, не се менят)

**4 — Печат (printer):**
```html
<svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
```

**5 — Настройки (gear):**
```html
<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
```

**6 — Изход (logout):**
```html
<svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
```

**7a — Тема светла (sun):**
```html
<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
```

**7b — Тема тъмна (moon):**
```html
<svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
```

## 12.9 Header animation на enter

```css
@keyframes rms-headerIn {
    from { transform: translateY(-100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.rms-header {
    animation: rms-headerIn 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0s both;
    transition: backdrop-filter 0.3s, background 0.3s;
}
```

## 12.10 Logout dropdown

Когато потребителят натисне Изход (#6), се появява dropdown с потвърждение:
```css
.rms-logout-dd {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    padding: 8px 14px;
    background: rgba(15, 23, 42, 0.95);
    border: 1px solid rgba(239, 68, 68, 0.4);
    border-radius: 10px;
    color: #fca5a5;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 0 18px rgba(239, 68, 68, 0.3);
    display: none;
}
.rms-logout-dd.visible { display: block; }
```

---

# 13. RMS BOTTOM NAV (4 таба, винаги еднаква)

## 13.1 Какво е

Глобална навигация долу на всяка страница. **Винаги 4 таба в фиксиран ред.** Активен таб се определя автоматично от текущия модул. Никога не се пише наново — винаги се include-ва от `partials/bottom-nav.php`.

## 13.2 Структура (ЗАДЪЛЖИТЕЛЕН РЕД отляво надясно)

| Позиция | Таб | Икона | Линк | Active за модули |
|---|---|---|---|---|
| 1 | **AI** | Animated audio bars (4 анимирани правоъгълника) | `chat.php` | chat, simple, life-board, index |
| 2 | **Склад** | 3D cube | `warehouse.php` | warehouse, inventory, transfers, deliveries, suppliers, products |
| 3 | **Справки** | Bar chart (3 vertical lines) | `stats.php` | stats, finance |
| 4 | **Продажба** | Lightning bolt | `sale.php` | sale |

**Този ред е заключен от BIBLE и не се променя.**

## 13.3 CSS на контейнера

```css
.rms-bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 64px;
    background: rgba(3, 7, 18, 0.95);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-top: 1px solid rgba(99, 102, 241, 0.1);
    box-shadow: 0 -5px 25px rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    z-index: 100;
    padding-bottom: env(safe-area-inset-bottom);
    box-sizing: content-box;
}
```

## 13.4 Tab бутон (`.rms-nav-tab`)

```css
.rms-nav-tab {
    flex: 1;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    background: transparent;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s var(--ease);
    color: rgba(165, 180, 252, 0.5);
}
.rms-nav-tab svg {
    width: 24px;
    height: 24px;
    transition: all 0.3s var(--ease);
    filter: drop-shadow(0 0 4px rgba(99, 102, 241, 0.3));
}
.rms-nav-tab-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.02em;
}
```

## 13.5 Active state (когато табът е текущата страница)

```css
.rms-nav-tab.active {
    color: var(--indigo-400);  /* #818cf8 */
    text-shadow: 0 0 12px rgba(129, 140, 248, 0.9);
}
.rms-nav-tab.active svg {
    transform: translateY(-2px);
    filter: drop-shadow(0 0 12px rgba(129, 140, 248, 0.8));
}
```

Активният таб **се повдига 2px нагоре** + ярко индиго glow на текста и иконата.

## 13.6 SVG icons (фиксирани, не се менят)

**Tab 1 — AI (animated audio bars, 4 правоъгълника пулсиращи):**
```html
<svg viewBox="0 0 24 20" fill="none">
    <rect x="2" y="8" width="3" height="7" rx="1.5" fill="currentColor" opacity=".6">
        <animate attributeName="height" values="7;14;7" dur="1.2s" repeatCount="indefinite"/>
        <animate attributeName="y" values="8;4;8" dur="1.2s" repeatCount="indefinite"/>
    </rect>
    <rect x="7" y="4" width="3" height="12" rx="1.5" fill="currentColor" opacity=".75">
        <animate attributeName="height" values="12;6;12" dur="1.2s" begin="0.15s" repeatCount="indefinite"/>
        <animate attributeName="y" values="4;7;4" dur="1.2s" begin="0.15s" repeatCount="indefinite"/>
    </rect>
    <rect x="12" y="2" width="3" height="16" rx="1.5" fill="currentColor" opacity=".9">
        <animate attributeName="height" values="16;8;16" dur="1.2s" begin="0.3s" repeatCount="indefinite"/>
        <animate attributeName="y" values="2;6;2" dur="1.2s" begin="0.3s" repeatCount="indefinite"/>
    </rect>
    <rect x="17" y="5" width="3" height="10" rx="1.5" fill="currentColor" opacity=".7">
        <animate attributeName="height" values="10;14;10" dur="1.2s" begin="0.45s" repeatCount="indefinite"/>
        <animate attributeName="y" values="5;3;5" dur="1.2s" begin="0.45s" repeatCount="indefinite"/>
    </rect>
</svg>
```

**Tab 2 — Склад (3D cube):**
```html
<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
</svg>
```

**Tab 3 — Справки (bar chart):**
```html
<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <line x1="18" y1="20" x2="18" y2="10"/>
    <line x1="12" y1="20" x2="12" y2="4"/>
    <line x1="6" y1="20" x2="6" y2="14"/>
</svg>
```

**Tab 4 — Продажба (lightning bolt):**
```html
<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="currentColor" stroke-linejoin="round">
    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
</svg>
```

## 13.7 Bottom nav animation на enter

```css
@keyframes rms-navIn {
    from { transform: translateY(100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.rms-bottom-nav {
    animation: rms-navIn 0.7s cubic-bezier(0.16, 1, 0.3, 1) 1.8s both;
}
```
Влиза с **delay 1.8s** — след като header и съдържание вече са се появили.

## 13.8 Body padding-bottom (винаги!)

Всяко body на страница която ползва bottom nav трябва да има:
```css
body {
    padding-bottom: 64px;  /* височина на bottom nav */
    padding-bottom: calc(64px + env(safe-area-inset-bottom));
}
```
Без това последните карти се скриват зад nav-а.

---

# 13.9 ВАЖНО ЗА АГЕНТИ: Header и Bottom Nav НЕ се пишат наново

Когато правиш нов модул:

```php
<?php include __DIR__ . '/partials/header.php'; ?>

<!-- съдържание на модула -->

<?php include __DIR__ . '/partials/bottom-nav.php'; ?>
```

**Никога:**
- Не пишеш `<header>` от нула
- Не пишеш `<nav class="rms-bottom-nav">` от нула
- Не променяш реда на табовете
- Не сменяш иконите
- Не добавяш 5-ти таб (4-те са locked от BIBLE)
- Не добавяш 9-ти icon бутон в header (8-те са locked)

Ако ти трябва нещо друго в header (напр. store-pill, search, notification bell) → **питай потребителя**, не добавяй сам.

---

# 14. ALERT BANNER (когато нещо не е наред)

## 14.1 Структура
- Контейнер: `glass.sm` с `q-loss` клас (червен hue override)
- Layout: flex row, gap 11px, padding 11px 14px
- Cursor: pointer

## 14.2 Алerт icon (вляво)
- Размер: 30×30 кръг (50% radius)
- Фон: `linear-gradient(135deg, #ef4444, #dc2626)`
- Box-shadow: `0 0 12px rgba(239,68,68,0.5), inset 0 1px 0 rgba(255,255,255,0.2)`
- Pulse animation:
  ```css
  @keyframes alertPulse {
      0%, 100% { box-shadow: 0 0 12px rgba(239,68,68,0.5), inset 0 1px 0 rgba(255,255,255,0.2); }
      50% { box-shadow: 0 0 20px rgba(239,68,68,0.8), inset 0 1px 0 rgba(255,255,255,0.25); }
  }
  animation: alertPulse 2s ease-in-out infinite;
  ```
- SVG: 13×13px, бял stroke 2.5px

## 14.3 Текст (среда)
- Flex: 1
- Font: 12px, weight 700, color `#fca5a5`, line-height 1.35
- **Bold parts:** color white, weight 800

## 14.4 Arrow (вдясно)
- Знак: `›`
- Size: 18px, weight 900
- Color: `rgba(239,68,68,0.7)`

---

# 15. ANIMATIONS

## 15.1 Cardin (вход на карта)
```css
@keyframes cardin {
    from { opacity: 0; transform: translateY(8px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.cardin { animation: cardin 0.3s var(--ease) backwards; }
.cardin:nth-child(1) { animation-delay: 0s; }
.cardin:nth-child(2) { animation-delay: 0.05s; }
.cardin:nth-child(3) { animation-delay: 0.1s; }
.cardin:nth-child(4) { animation-delay: 0.15s; }
```

Stagger 50ms между децата.

## 15.2 Pulse dot (proactive AI cue)
- **Размер:** 8×8px кръг
- **Цвят:** `hsl(0, 90%, 60%)` (червен) или друг hue
- **Box-shadow:** `0 0 8px hsl(0, 90%, 55%, 0.8)`
- **Pseudo `::after`:** 1.5px solid border ring, inset -3px, scale 0.6 → 2, opacity 0.7 → 0
- **Pulse animation:** scale 1 → 0.85, opacity 1 → 0.7
- **Duration:** 1.8s, ease-in-out infinite

## 15.3 FAB ringing (двоен ring)
- 2 pseudos `::before` и `::after`
- inset: -6px
- border: 2px solid hsl(hue1, 70%, 60%, 0.45)
- Animation: scale 0.9 → 1.55, opacity 1 → 0
- Duration: 2.5s, втория с delay 1.25s

## 15.4 Easing — винаги
```css
--ease: cubic-bezier(0.5, 1, 0.89, 1);
transition: ... var(--ease);
```

**Duration по случай:**
- 0.15s — tap feedback
- 0.2s — default hover/transition
- 0.25s — collapse/expand
- 0.3s — cardin
- 0.35s — toast

---

# 16. ЗАБРАНЕНО

## 16.1 НИКОГА не правиш
- `<style>` tag в production модул
- Inline `style="--hue1: 280"` — само през класове `.q-magic`
- Bootstrap, Tailwind, Material класове
- jQuery — vanilla JS
- localStorage / sessionStorage
- Шрифт различен от Montserrat
- Square corners на бутони — винаги pill 100px или rounded 12-16px
- Solid color бутон без shadow + inset highlight
- Border-radius не от scale-а
- Padding/margin не от scale-а
- Cursor `default` на clickable — винаги `pointer`
- Custom hue извън таблицата 2.5

## 16.2 ВИНАГИ правиш
- Body 3-layer background + noise overlay
- Glass карта с 4 span pattern
- Content вътре в glass с `position: relative; z-index: 5`
- Montserrat font import
- Safe-area-inset на fixed elements
- `tabular-nums` на всички числа (цени, qty, проценти)
- `var(--ease)` на всеки transition
- `backdrop-filter: blur(...)` на всички glass surfaces

---

# 17. WORKFLOW

1. Получи задача
2. Идентифицирай типа: dashboard / wizard / list / detail / form
3. За всеки елемент в задачата — приложи стиловете от секции 7-15 буквално
4. Hue избери от таблица 2.5
5. Размери от таблица 4
6. Преди да дадеш файл — провери секция 16

---

# 18. КОГА СЕ ПИТА ПОТРЕБИТЕЛЯТ

- Поиска нещо което няма в палитрата (нов hue)
- Нов компонент който не е описан в 8-14
- Конфликт между две правила
- Не е ясно дали е dashboard или wizard pattern

Не измисляш. Питаш.

---

**КРАЙ.**

*Този документ е самодостатъчен. Всеки агент който го прочете може да направи модул в правилния стил без да отваря други файлове.*
