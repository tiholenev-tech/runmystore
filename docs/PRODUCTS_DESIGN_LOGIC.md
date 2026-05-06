# 📱 PRODUCTS.PHP — ДИЗАЙН И ЛОГИКА (S78-S82)

## Пълна спецификация на всички модули на products.php

**Версия:** 1.0 (19.04.2026)  
**Статус:** Reference document за сесии S78-S82  
**Mockups:** products-home-6-questions.html, filter-drawer-s77-svg.html, add-product-variations.html  
**Design system:** Neon Glass (shine/glow/glass/gradients)

---

# СЪДЪРЖАНИЕ

1. [Главна страница (home view)](#1-главна-страница-home-view)
2. [Пълен списък (full list)](#2-пълен-списък-full-list)
3. [Wizard — 4 стъпки](#3-wizard--4-стъпки)
4. [AI Wizard (voice flow)](#4-ai-wizard-voice-flow)
5. [CSV Import flow](#5-csv-import-flow)
6. [Product detail / Edit flow](#6-product-detail--edit-flow)
7. [AI Image Studio](#7-ai-image-studio)
8. [AI от фабричен етикет](#8-ai-от-фабричен-етикет)
9. [Bluetooth Print](#9-bluetooth-print)
10. [Expanded Filter Drawer](#10-expanded-filter-drawer)
11. [Voice Overlay (universal)](#11-voice-overlay-universal)
12. [compute-insights.php функции](#12-compute-insightsphp-функции)
13. [AJAX Endpoints](#13-ajax-endpoints)
14. [DB заявки (референции)](#14-db-заявки-референции)
15. [Empty States / Loading / Errors](#15-empty-states--loading--errors)
16. [Interaction Patterns](#16-interaction-patterns)
17. [Edge Cases](#17-edge-cases)

---

# 1. ГЛАВНА СТРАНИЦА (home view)

## 1.1 Цел

Пешо отваря Артикули → вижда веднага **какво губи** (червено) и **какво печели** (зелено) на артикул-ниво. Не списък. Не статистика. **Фокусирани сигнали** групирани по 6-те фундаментални въпроса.

## 1.2 Визуална йерархия

```
┌────────────────────────────────────────┐
│ Header (☰ LOGO Магазин▾ PRO ⚙)        │ 40px
│ Title: „Артикули · 247 шт."            │
│ Search [🔍 search [⚙3] [🎤]]           │
│ [+ Добави артикул] card                │
│                                        │
│ ═══ 6 СЕКЦИИ ═══                       │
│                                        │
│ 🔴 Q-head: Какво губиш   −340 лв/седм │ ← section 1
│ └── H-scroll article cards (4-8)       │
│                                        │
│ 🟣 Q-head: От какво губиш  −180 лв    │ ← section 2
│ └── H-scroll                           │
│                                        │
│ 🟢 Q-head: Какво печелиш  +2 840 лв   │ ← section 3
│ └── H-scroll                           │
│                                        │
│ 🔷 Q-head: От какво печелиш 4 причини │ ← section 4
│ └── H-scroll                           │
│                                        │
│ 🟡 Q-head: Какво да поръчаш 28 арт.   │ ← section 5
│ └── H-scroll → orders.php              │
│                                        │
│ ⚫ Q-head: Какво да НЕ поръчаш 9 арт. │ ← section 6
│ └── H-scroll                           │
│                                        │
│ [Виж всички 247 артикула →]            │
│ Bottom nav (AI · Склад · Спр · Продажба) │
└────────────────────────────────────────┘
```

## 1.3 CSS hue system (задължителен)

```css
/* 6 варианта на glass hue */
.glass.q1 { --hue1: 0;   --hue2: 340; --border-color: hsl(0, 25%, 22%); }     /* Loss */
.glass.q2 { --hue1: 280; --hue2: 260; --border-color: hsl(280, 20%, 22%); }   /* Cause */
.glass.q3 { --hue1: 145; --hue2: 165; --border-color: hsl(145, 20%, 22%); }   /* Gain */
.glass.q4 { --hue1: 175; --hue2: 195; --border-color: hsl(175, 20%, 22%); }   /* Gain cause */
.glass.q5 { --hue1: 38;  --hue2: 28;  --border-color: hsl(38, 22%, 22%); }    /* Order */
.glass.q6 { --hue1: 220; --hue2: 230; --border-color: hsl(220, 10%, 18%); }   /* Anti */

/* Text colors per question */
.q-nm.q1 { color: #fca5a5; text-shadow: 0 0 10px rgba(239,68,68,.3); }
.q-nm.q2 { color: #d8b4fe; text-shadow: 0 0 10px rgba(192,132,252,.3); }
.q-nm.q3 { color: #86efac; text-shadow: 0 0 10px rgba(34,197,94,.3); }
.q-nm.q4 { color: #5eead4; text-shadow: 0 0 10px rgba(45,212,191,.3); }
.q-nm.q5 { color: #fcd34d; text-shadow: 0 0 10px rgba(251,191,36,.3); }
.q-nm.q6 { color: rgba(255,255,255,.7); }
```

## 1.4 Q-head структура

```html
<div class="q-head q1">
  <div class="q-badge">1</div>
  <div class="q-ttl">
    <div class="q-nm q1">Какво губиш</div>
    <div class="q-sub">Артикули с продажби без наличност</div>
  </div>
  <div class="q-total q1">−340 лв/седм</div>
</div>
```

**Total pill логика** (изчислява се в backend):

| Секция | Формула | Пример |
|---|---|---|
| 1. Loss | SUM(velocity × retail × 7) за всички 0-stock с продажби | „−340 лв/седм" |
| 2. Loss Cause | SUM(потенциален profit) от under-margin / no-cost | „−180 лв profit" |
| 3. Gain | SUM(profit) за топ 10 за 30д | „+2 840 лв" |
| 4. Gain Cause | COUNT на артикули с причини | „4 причини" |
| 5. Order | COUNT препоръки | „28 арт." |
| 6. Anti-Order | COUNT + SUM(замразен капитал) | „9 · 2 480 лв" |

## 1.5 Article card в h-scroll

```html
<div class="glass sm q1 art" onclick="openProduct(123)">
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <div class="art-photo">
    <svg><!-- product silhouette или image_url ако има --></svg>
    <span class="tag bad">0 БР</span>  <!-- tag per question type -->
  </div>
  <div class="art-nm">Nike Air Max 42 черни</div>
  <div class="art-bot">
    <div class="art-prc">120 лв</div>
    <div class="art-stk danger">0 бр</div>  <!-- ok/warn/danger -->
  </div>
  <div class="art-ctx q1">
    3 търсения /7д · <b>~360 лв profit/мес пропуснат</b>
  </div>
</div>
```

**Размери:**
- Card width: 162px fixed
- Gap между cards: 8px
- H-scroll с `scroll-snap-type: x mandatory`
- Padding на контейнера: `0 -12px` (bleed до края на екрана)

## 1.6 Tag variants (в ъгъла на снимката)

| Tag | Кога | Цвят | Пример |
|---|---|---|---|
| `.tag.bad` | Loss situations | Red | „0 БР", „−8%", „?" |
| `.tag.good` | Gain, topовете | Green | „#1", „#2" |
| `.tag.teal` | Gain causes | Teal | „58%", „↑22%" |
| `.tag.hot` | Order recommendations | Amber | „24", „18" |
| `.tag.dim` | Anti-order (zombie) | Grey | „78д", „−40%" |
| `.tag.violet` | Loss causes | Violet | „−8%", „?" |

## 1.7 Context line formats (под всеки артикул)

**Правила:**
- Винаги започва с наблюдение (число + период)
- Разделител „ · "
- Завършва с **bold ефект в пари** (profit)
- Използва същия цвят като `.q-nm.qX`

**Шаблони per секция:**

```
🔴 Loss:
"{N} търсения /{period} · <b>~{amount} лв profit/мес пропуснат</b>"
"{X} прод/30д · <b>свършва утре</b>"
"{X} прод/30д · <b>под минимум</b>"

🟣 Loss Cause:
"Доставна {X} лв · <b>продаваш на загуба</b>"
"Без доставна цена · <b>не виждаш profit</b>"
"Марж под 15% · <b>profit {X} лв</b>"
"{Seller} даде отстъпки · <b>−{X} лв profit</b>"

🟢 Gain:
"{X} прод · <b>+{amount} лв profit</b>"

🔷 Gain Cause:
"Най-висок марж · <b>{X}% профитност</b>"
"Растящ тренд · <b>↑{X}% седмично</b>"
"{N} повторни клиенти · <b>лоялен артикул</b>"
"Купуват го с {X} · <b>basket driver</b>"
"Размер {M} най-продаван · <b>size leader</b>"

🟡 Order:
"Топ №{N} · <b>поръчай {X} бр</b>"
"Bestseller · <b>поръчай {X} бр</b>"

⚫ Anti-Order:
"{X} дни · <b>{amount} лв замразени</b>"
"Спад {X}% · <b>не поръчвай</b>"
```

## 1.8 Empty state per секция

Когато секцията няма артикули за показване:

```html
<div class="glass sm q3 art empty">
  <div class="empty-ico"><svg><!-- sparkle --></svg></div>
  <div class="empty-txt">Няма губиш за момента 🎉</div>
  <div class="empty-sub">Проверявай пак утре</div>
</div>
```

**Специфично per секция:**

| Секция | Empty message |
|---|---|
| 🔴 Loss | „Няма губиш за момента 🎉" |
| 🟣 Loss Cause | „Всички артикули са с правилен марж" |
| 🟢 Gain | „Липсват продажби — започни да продаваш за да видиш топовете" |
| 🔷 Gain Cause | „Събирам данни — ще има инсайти след 14 дни продажби" |
| 🟡 Order | „Нищо за поръчване засега" |
| ⚫ Anti-Order | „Няма zombie стока — добре управляваш инвентара" |

**Ако цяла секция е празна (няма какво да се изчисли):**
Секцията се скрива напълно (`display: none`). Прескача се.

## 1.9 Loading state

При първо зареждане или pull-to-refresh:

```html
<div class="glass sm q1 art skeleton">
  <div class="skel-photo"></div>
  <div class="skel-line w80"></div>
  <div class="skel-line w50"></div>
  <div class="skel-ctx w70"></div>
</div>
```

CSS animation:
```css
@keyframes shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}
.skeleton {
  background: linear-gradient(90deg, 
    rgba(255,255,255,.03) 25%, 
    rgba(255,255,255,.08) 50%, 
    rgba(255,255,255,.03) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
```

3 skeleton cards per секция докато не дойдат данните.

## 1.10 Refresh логика

- **Pull-to-refresh:** reload всички 6 секции + total pills
- **Auto-refresh:** при връщане в tab-а (visibilitychange event)
- **След продажба:** realtime update — sale.php JS вика `refreshProducts()`
- **Cache:** 5 мин TTL на client side (sessionStorage)

## 1.11 Interactions

| Action | Resultat |
|---|---|
| Tap на артикул | Отваря product detail / edit overlay |
| Long-press на артикул | Quick actions menu (Edit / Delete / Print / Duplicate) |
| Tap на Q-head | Отваря filtered list page със само тази категория |
| Tap на total pill | Същото като Q-head |
| Tap на „Виж всички" | Full list page |
| Swipe left на article card | Quick action: Add to order |
| Swipe right | Quick action: Mark as counted (inventory) |

---

# 2. ПЪЛЕН СПИСЪК (full list page)

## 2.1 Цел

Когато Пешо натисне „Виж всички 247 артикула →" — отваря се списък с всички артикули, с sort + filter.

## 2.2 Структура

```
Header: ← Артикули (всички)   [⚙] [🔍]
Title: „247 артикула · 12 840 лв"

Sort tabs: [Най-нови] [По име] [По цена] [По profit]

Filter chips (активни): „Доставчик: Спорт Груп ×" „Категория: Обувки ×"

List (vertical scroll):
┌────────────────────────────────┐
│ [img] Nike Air Max 42    [0 БР] │ ← article row
│       NIKE-AM-42 · Спорт Груп   │
│       40 41 [42 red] 43         │ ← variation dots
│       120 лв          🔴 Губиш  │ ← tag правилен от 6-те
└────────────────────────────────┘
┌────────────────────────────────┐
│ [img] Рокля Zara черна  [2 бр] │
│       ZARA-DR · Мода Дистр.     │
│       S [M amber] L             │
│       89 лв           🟢 Топ    │
└────────────────────────────────┘
...

[Infinite scroll / pagination]
```

## 2.3 Article row

```html
<div class="glass sm prod" onclick="openProduct(id)">
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <div class="prod-photo">🏷️</div>
  <div class="prod-info">
    <div class="prod-nm">Nike Air Max 42 <span class="prod-badge zero">0</span></div>
    <div class="prod-meta">NIKE-AM-42 · Спорт Груп</div>
    <div class="prod-vars">
      <span class="prod-var">40</span>
      <span class="prod-var active">41</span>
      <span class="prod-var danger">42</span>  <!-- 0 stock variant -->
      <span class="prod-var">43</span>
    </div>
  </div>
  <div class="prod-right">
    <div class="prod-price">120 лв</div>
    <div class="prod-stock danger">0 бр</div>
    <div class="prod-q-tag q1">🔴 Губиш</div>  <!-- optional tag -->
  </div>
</div>
```

## 2.4 Status tags на артикул

В списъка (не на home), артикул може да има 1 от:
- `🔴 Губиш` (ако е в секция 1 на home)
- `🟣 Марж` (ако е в секция 2)
- `🟢 Топ` (ако е в секция 3)
- `🔷 Драйвер` (ако е в секция 4)
- `🟡 Поръчай` (ако е в секция 5)
- `⚫ Zombie` (ако е в секция 6)
- `⚠ Недовършен` (ако wizard е incomplete)
- `📷 Без снимка` / `🔢 Без баркод` (меки напомняния)

## 2.5 Sorts

| Sort | SQL order |
|---|---|
| Най-нови | `created_at DESC` |
| По име | `name ASC` |
| По цена | `retail_price DESC` |
| По profit | `profit_30d DESC` |

## 2.6 Pagination

- Infinite scroll
- 20 артикула per page
- При скрол към bottom → AJAX `ajax=list&offset=N&limit=20`
- Loading skeleton в края докато се зарежда

---

# 3. WIZARD — 4 СТЪПКИ

## 3.1 Overview

**Старият wizard** — 7 стъпки (вид/снимка/основни/варианти1/варианти2/доставчик/преглед). Много click-ове, много scroll.

**Новият wizard** — 4 стъпки с progressive disclosure. Всичко важно на стъпка 1 (бърз запис). Останалото optional.

## 3.2 Мапване

| Нова стъпка | Какво съдържа | Стара еквивалентност |
|---|---|---|
| **0. Vid** | Single / Variant избор | Old 1 |
| **1. Основни** | Снимка + име + цена + брой (минимум) + AI avtofield | Old 2+3 merged |
| **2. Варианти** | Fullscreen matrix (ако variant) | Old 4+5 merged |
| **3. Доставчик + AI** | Supplier → Category → AI Image Studio + опционални полета | Old 6+7 merged |

## 3.3 Стъпка 0 — Vid (single/variant)

```
Title: „Какъв тип?"

┌──────────────────┐  ┌──────────────────┐
│      [Single]    │  │    [Variant]     │
│  1 продукт, 1    │  │  Разни размери/  │
│  цена, 1 бройка  │  │  цветове         │
└──────────────────┘  └──────────────────┘

[Пропусни (по-късно) →]
```

**Auto-skip logic:** Ако в последния добавен артикул `_rms_lastWizProducts` има същия тип — skip тази стъпка, иди директно на стъпка 1.

## 3.4 Стъпка 1 — Основни

**Критично:** Минимумът (име + цена + брой) създава истински продукт. Другите полета се попълват по-късно.

```
Title: „Какъв е артикулът?"

[📷 Snap снимка]  ← camera button

Име: [________________] [🎤]
Цена: [___] лв [🎤]        Брой: [___] [🎤]

─── Optional (tap to expand) ───
Баркод: [______] [📷 scan]
Арт. номер: [______]
Мерна единица: [бр ▾]
Мин. количество: [___]

[Запази ✓]  [Печатай 🖨]  [Нов ➕]
```

**Voice на име:** тригер-ва AI autofield — от „Nike Air Max 42 черни" → парсва и попълва:
- name: „Nike Air Max"
- size: „42"
- color: „черни"
- подсказва category: „Обувки"

**Edge case:** ако Пешо само натисне „Запази" с име+цена+брой:
- Създава продукт с `is_complete = 0` → показва „⚠ Недовършен" в списъка
- При tap на „⚠ Недовършен" → отваря wizard на стъпка, която липсва

## 3.5 Стъпка 2 — Варианти (Matrix overlay)

Само ако Vid=Variant.

```
Title: „Варианти"

Ос 1 (търсене): [Размер ▾] — добавя chip-ове: 38 39 40 41 42 43
Ос 2 (търсене): [Цвят ▾] — chip-ове: Черен, Бял, Червен
Ос 3 (optional): [Материал ▾]

[Отваряй матрица 🔲]  ← launches fullscreen overlay
```

### Matrix overlay (fullscreen)

```
┌──────────────────────────────────┐
│ × Варианти · 6×3 = 18 клетки    │
├──────────────────────────────────┤
│         38    39    40   ...     │
│ Черен  [4][2][5][2][8][3]...     │  ← Qty/Min per cell
│        ▲▼  ▲▼  ▲▼               │
│ Бял    [0][0][2][1][3][1]...     │
│ Червен [1][1][0][0][2][1]...     │
├──────────────────────────────────┤
│ AutoMin: ✓ (Math.round(qty/2.5)) │
│ [Нулирай] [Запази всички]        │
└──────────────────────────────────┘
```

**autoMin формула:** `Math.round(qty/2.5)` min 1. Toggle-ва се с checkbox.

**Edit mode:** При редакция на съществуващ артикул, `_editVariations` data идва от DB → попълва се matrix.

**Visual:**
- Зелено background ако qty >= min
- Жълто ако qty < min но > 0
- Червено ако qty = 0
- Сиво ако клетката е disabled (рядко)

## 3.6 Стъпка 3 — Доставчик + AI

```
Title: „Доставчик и категория"

Доставчик: [Спорт Груп ▾] [🎤]
Категория: [Обувки ▾] [🎤]  ← filterе от supplier_categories
Подкатегория: [Маратонки ▾] [🎤]  ← optional

─── Optional ───
Покупна цена: [___] лв [🎤]  ← „Пропусни ако не знаеш"
Цена едро: [___] лв [🎤]
Произход: [България ▾] [🎤]
Състав: [100% памук] [🎤]
Season: [Целогодишен] [🎤]
Sequence: [AW25] [🎤]

─── AI ───
[📸 AI Image Studio]  ← ако има снимка
[✨ AI SEO описание]

[Запази ✓] [Печатай 🖨] [Нов ➕] [Готово]
```

**Поле ред критично:** Доставчик ПРЕДИ Категория. Причина: `supplier_categories` таблица филтрира кои категории са за кой доставчик.

**AI Image Studio:** отваря overlay — виж секция 7.

**AI SEO описание:** генерира описание за артикула (marketing копирайтинг). textarea rows=5, бутон „Генерирай" → Gemini → попълва.

## 3.7 Печат overlay (от всяка стъпка)

Бутон [🖨] присъства на стъпка 1, 2, 3 → отваря отделна страница/overlay:

```
┌────────────────────────────────┐
│ ← Печат етикет                │
├────────────────────────────────┤
│ Format: [€+лв] [Само €] [Без ц]│
│                                 │
│ ⚠ Произход: [България ▾]       │  ← ако е чуждестранна стока, задължително
│                                 │
│ Бр. за печат: [−][ 5 ][+]      │
│                                 │
│ [Preview 50×30mm]              │
│                                 │
│ [x2] [1:1]                     │
│ [🖨 Печатай 5 етикета]         │
└────────────────────────────────┘
```

**За variant артикули:** показват се всички вариации със отделни бройки −/+ per вариация + 🖨 per вариация + „Печатай всички".

## 3.8 Quick Actions pill bar

При отваряне на wizard от главната, горе се показва:

```
[🎤 AI Wizard] [✏️ Ръчно] [📄 Импорт] [⬡ Скан]
```

Tap на pill — директно към съответния flow.

---

# 4. AI WIZARD (voice flow)

## 4.1 Цел

Пешо говори. AI попълва. Конфирмира. 10 секунди vs 2 минути с keyboard.

## 4.2 Flow

```
1. Пешо натиска [🎤 AI Wizard]
   → voice overlay се появява (S56 стил)

2. AI: „Какъв е артикулът?"
   Пешо: „Ако да направя черни тениски Nike M-L-XL за 35 лева"
   AI parse:
     - name: „Тениска Nike черна"
     - color: „черен" 
     - size: L, M, XL (AI detect-ва вариации)
     - price: 35 лв
     - quantity: ? (не е казано)

3. AI показва транскрипция + parsed данни:
   „Тениска Nike черна, вариации: M, L, XL, по 35 лв.
    Колко бройки от всеки размер?"

4. Пешо: „По 10 броя всеки"
   AI: quantity M=10, L=10, XL=10

5. AI: „От кой доставчик?"
   Пешо: „Спорт Груп"
   AI: supplier_id resolved

6. AI: „Готово! Запазвам. Да добавя ли нов?"
   - Ако да → [Печатай] [Нов]
   - Ако не → [Готово]
```

## 4.3 Voice overlay UI (от S56 стандарт)

```html
<div class="rec-ov" id="voiceOverlay">
  <div class="rec-box">
    <div class="rec-head">
      <div class="rec-dot red pulse"></div>  <!-- 48px точка -->
      <div class="rec-label">● ЗАПИСВА</div>
    </div>
    <div class="rec-transcript">
      Тениска Nike черна, M L XL, по 35 лева...
    </div>
    <div class="rec-actions">
      <button class="rec-redo">🎤 Диктувай отново</button>
      <button class="rec-send">Изпрати →</button>
    </div>
  </div>
</div>
```

**CSS:**
```css
.rec-ov {
  position: fixed; inset: 0;
  backdrop-filter: blur(8px);
  background: rgba(0,0,0,.25);
  z-index: 100;
}
.rec-box {
  position: fixed; bottom: 20px; left: 12px; right: 12px;
  max-width: 456px; margin: 0 auto;
  padding: 20px;
  border-radius: 20px;
  background: linear-gradient(135deg, hsl(340 40% 18%), hsl(240 40% 15%));
  border: 1px solid hsl(340 40% 30%);
  box-shadow: 0 0 30px hsl(340 60% 45% / .4); /* indigo-pink glow */
}
.rec-dot {
  width: 48px; height: 48px;
  border-radius: 50%;
  background: #ef4444;
  animation: pulse 1s infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: .7; transform: scale(1.1); }
}
```

## 4.4 AI parsing prompts

Backend (Gemini) получава:
```
Контекст: Магазин за дрехи, business_type="дрехи — луксозни"
Ти си assistant който помага при добавяне на артикул.
Parse следното voice input:
"Ако да направя черни тениски Nike M-L-XL за 35 лева"

Върни JSON:
{
  "name": "...",
  "brand": "...",
  "color": "...",
  "sizes": [...],
  "price": ...,
  "supplier_hint": "...",
  "category_hint": "...",
  "missing_fields": [...]
}
```

## 4.5 Confirmation loop

След всеки voice input:
1. Показвай транскрипция (interim results)
2. След като спре — парсни + покажи резултата
3. Confidence < 0.8 → жълт background + „Сигурен ли си?"
4. Confidence < 0.6 → предложи alternatives (fuzzy match)

## 4.6 Fallback при voice fail

Ако micrфон не работи, WiFi няма, STT не разпознава:
- Toast: „Voice не работи. Използвай ръчно wizard."
- Auto-switch към manual wizard

---

# 5. CSV IMPORT FLOW

## 5.1 Overview

Вход: CSV/Excel файл с артикули. AI auto-detect колони → preview → bulk insert.

## 5.2 Flow

```
1. Пешо натиска [📄 Импорт] в Quick Actions
2. File picker (accept: .csv, .xlsx, .xls)
3. Parse file (PapaParse или SheetJS)
4. AI auto-detect колони:
   - „name" (Име / Наименование / Product Name / ...)
   - „code" (Код / SKU / Article)
   - „price" (Цена / Retail / Sell price)
   - „supplier" (Доставчик)
   - ...
5. Preview таблица:
   - 10 първи реда
   - Колони mapped с confidence score
   - Пешо може да override mapping
6. Validation:
   - Missing name / price → error row highlight
   - Duplicate code → warning
   - Unknown supplier → „Да създам ли?"
7. [Импортирай всички (247 артикула)]
8. Progress bar
9. Toast: „247 артикула импортирани · 12 404 лв обща стойност"
```

## 5.3 Auto-detect алгоритъм

Backend (PHP или Gemini):
```
Input: header row ["Наим.", "Код", "Цена", "Дост.", "Кол.", "Кат."]
Output:
{
  "name": { column: "Наим.", confidence: 0.92 },
  "code": { column: "Код", confidence: 0.98 },
  "retail_price": { column: "Цена", confidence: 0.95 },
  "supplier": { column: "Дост.", confidence: 0.88 },
  "quantity": { column: "Кол.", confidence: 0.87 },
  "category": { column: "Кат.", confidence: 0.93 }
}
```

Ако confidence < 0.7 → Пешо трябва да избере ръчно.

## 5.4 Duplicate handling

- По `code` → „{N} артикула имат същия код като съществуващи. Какво да правим?"
  - [Презапиши] [Пропусни] [Създай с нов код]

## 5.5 Грешки при import

- Всяка грешка се показва в списък с line number
- Пешо може да [Fix inline] или [Remove from import]

## 5.6 Auto-detect на програмата (S96)

Преди CSV mapping AI разпознава ОТ КОЯ ПРОГРАМА идва файлът — три стъпки:

**1. Encoding детекция** (PHP `mb_detect_encoding`):

| Encoding | Регион | Типични програми |
|---|---|---|
| Windows-1251 (CP1251) | България | Microinvest, StoreHouse, Mistral, GenSoft |
| Windows-1250 | Полша, Чехия, Румъния (SAGA) | Subiekt GT, Pohoda, SAGA C |
| Windows-1253 | Гърция | PRISMA Win, SoftOne (стари) |
| Windows-1252 | Италия, Испания | Danea Easyfatt, Factusol |
| ISO-8859-2 | Румъния (Sedona) | Sedona Retail |
| UTF-8 | Международни облачни | Loyverse, Shopify, Lightspeed, SmartBill |

Приоритет на проверка: UTF-8 → CP1251 → CP1250 → CP1253 → CP1252 → ISO-8859-2.

**2. Разделител детекция** (броене на първите 5 реда):

| Разделител | Типични програми |
|---|---|
| Точка и запетая (`;`) | Европейски локални (Microinvest, PRISMA, Subiekt, Danea, Factusol) |
| Запетая (`,`) | Международни облачни (Loyverse, Shopify, Lightspeed, SmartBill) |
| Таб (`\t`) | Microinvest (алтернативен експорт) |

Който е най-чест и дава еднакъв брой колони — той е разделителят.

**3. Header fingerprint** — пръстов отпечатък на програмата.

## 5.7 Библиотека от известни формати

Системата пази шаблони с уникални заглавни редове и сравнява нормализирания (lowercase, trim, no-BOM) header:

```
Microinvest:    Наименование;Код;Баркод;Мярка;Група;Цена1;Цена2  (CP1251, ;)
Eltrade/Детел.: Код;Наименование;Баркод;Цена;Група;ДДС група      (CP1251, ;)
SmartBill (RO): Nume,SKU,Cod_bare,Pret,UM,Tip                     (UTF-8, ,)
SAGA C:         Cod;Denumire;UM;Pret_vanzare;Pret_achizitie       (CP1250, ;)
SoftOne (GR):   Κωδικός;Περιγραφή;Barcode;Τιμή;Χρώμα;Μέγεθος       (UTF-8/CP1253, ;)
Loyverse:       Handle,Item Name,Category,SKU,Barcode,Price,Option1 Name,Option1 Value
Shopify:        Handle,Title,Option1 Name,Option1 Value,SKU,Variant Price
Lightspeed:     ID,Handle,SKU,Name,Variant 1,Variant 2,Price,Retail Price
```

**Confidence scoring:**
- **≥ 90%** → автоматично mapping
- **70-89%** → потвърждение от потребителя ("Това е Microinvest, така ли?")
- **< 70%** → ръчно mapping (Пешо избира коя колона е какво)

## 5.8 AI групиране на вариации (Gemini)

Главният проблем при импорт: Shopify/Loyverse групират вариациите чрез поле `Handle`, но локалните програми (Microinvest, SAGA, StoreHouse) често експортват вариациите като **напълно отделни редове** без Parent ID:

```
Nike Air Max 90 - 42 - Черен;NAM-42-BK;...
Nike Air Max 90 - 43 - Черен;NAM-43-BK;...
Nike Air Max 90 - 42 - Бял;NAM-42-WH;...
```

**Алгоритъм:**

1. **Regex базово групиране** — повтарящ се префикс ("Nike Air Max 90") + суфикси (размери/цветове) → кандидат за обединяване
2. **Gemini верификация** — получава групата имена → връща JSON:
   ```json
   {
     "parent_name": "Nike Air Max 90",
     "axes": {"size": ["42","43","44"], "color": ["Черен","Бял"]},
     "confidence": 0.92
   }
   ```
3. **Потребителско потвърждение при confidence < 85%** — "Тези 6 артикула изглеждат като един продукт с вариации. Правилно ли е?" → [Да, обедини] / [Не, остави отделни]
4. **Mapping към RunMyStore** — създава 1 product (parent) + N product_variants

## 5.9 Импорт на движения (последни 6 месеца)

Освен артикули, при налични данни се импортират и движения за обогатяване на AI контекста:

**Продажби (последни 6 месеца):**
- Дата, артикул (по код или баркод), количество, цена, тип плащане

**Доставки (последни 6 месеца):**
- Дата, артикул, количество, покупна цена, доставчик

**Филтриране (какво НЕ дърпаме):**
- Сторнирани операции с неясна причина
- Тестови записи
- Движения по-стари от 6 месеца
- Артикули с нулево количество И нулеви продажби (мъртви артикули)

**Confidence на импортираните данни:** 60-90% — имат имена, кодове, цени, може количества, но не са физически потвърдени. Препоръчва се **Zone Walk** след импорт за пълна точност.

**Принцип:** Три месеца собствени данни в RunMyStore = по-точни от 2 години мръсна история от стара програма.

## 5.10 Препратка

Пълна спецификация (29 секции, header fingerprints per всяка програма, технически компоненти, DB схема `import_sessions`/`import_mappings`/`import_log`) — виж **DATA_MIGRATION_STRATEGY_v1.md**.

---

# 6. PRODUCT DETAIL / EDIT FLOW

## 6.1 Overview

Tap на артикул (в списък или секция) → detail overlay (bottom sheet 90vh).

## 6.2 Detail overlay layout

```
┌────────────────────────────────┐
│ ← Nike Air Max 42 черни     ⋯  │ ← header с back + menu
├────────────────────────────────┤
│ [Голяма снимка 300×300]        │
│                                 │
│ Nike Air Max 42 черни           │
│ NIKE-AM-42-BLK · EAN 5901234    │
│ Спорт Груп · Обувки · Маратонки│
│                                 │
│ 120 лв  (покупна 70 лв, +71%)  │
│ 0 бр.                           │
│                                 │
│ Вариации:                       │
│  40: 3 бр · 41: 2 бр · 42: 0 бр │
│  43: 5 бр                       │
│                                 │
│ Статистика 30 дни:              │
│  Продажби: 18                   │
│  Profit: +840 лв                │
│  Последна продажба: преди 2ч    │
│                                 │
│ ═══ AI ИНСАЙТИ за този арт ═══ │
│ 🔴 3 търсения /7д · ~360 лв     │
│ 🟢 Топ №1 за 30 дни             │
│ 🟡 Поръчай 24 бр от Спорт Груп  │
│                                 │
│ [✏️ Редактирай] [🖨] [📤 Добави │
│                       в поръчка]│
│ [⋯ Още (дупликирай / деактив.)] │
└────────────────────────────────┘
```

## 6.3 Edit flow

Tap [✏️ Редактирай] → отваря wizard в edit mode → pre-populate всички полета.

**Критично за edit:**
- `editProduct(id)` функция трябва да зарежда ВСИЧКИ полета (не само някои) — beg S72 fix
- `_editVariations` съдържа съществуващите вариации + qty → попълва matrix overlay
- При save: UPDATE, не INSERT

## 6.4 Quick actions (⋯ menu)

- Дупликирай — нов артикул със същите данни + суфикс „копие"
- Деактивирай — `is_active = 0` (не се показва в списък/каса)
- Архивирай — трайно премахване от UI (но не DELETE от DB)
- Печатай етикет
- Сподели (whatsapp / email)

---

# 7. AI IMAGE STUDIO

## 7.1 Overview

След snap на снимка в wizard → бутон „AI Image Studio" → отваря overlay с опции.

## 7.2 Overlay layout

```
┌────────────────────────────────┐
│ × AI Image Studio              │
├────────────────────────────────┤
│ [Оригинална снимка 300×300]    │
│                                 │
│ Опции:                          │
│ ┌────────────────────────────┐ │
│ │ 🪄 Махни фон     €0.05    │ │ ← birefnet
│ │ (бял фон за каталог)      │ │
│ └────────────────────────────┘ │
│ ┌────────────────────────────┐ │
│ │ 👤 На модел       €0.50   │ │ ← nano-banana
│ │ Жена / Мъж / Момиче /     │ │
│ │ Момче / Тийн. / Тийн.     │ │
│ └────────────────────────────┘ │
│                                 │
│ [➡ Пропусни]                   │
└────────────────────────────────┘
```

## 7.3 fal.ai интеграция

### Махни фон (birefnet)
```php
$response = fal_request('fal-ai/birefnet/v2', [
  'image_url' => $uploaded_image_url,
  'model' => 'General'
]);
// cost: €0.05
```

### На модел (nano-banana-pro)
```php
$response = fal_request('fal-ai/nano-banana-pro/edit', [
  'image_url' => $image_url,
  'prompt' => "Young {$gender} model wearing this {$product_type}, 
               studio lighting, white background, fashion photography",
  'num_images' => 1
]);
// cost: €0.50
```

## 7.4 Лимити per plan

| Plan | Махни фон/ден | На модел/ден |
|---|---|---|
| FREE | 0 | 0 |
| START | 3 | 1 |
| PRO | 10 | 5 |

Лимитите в DB: `ai_usage_daily` таблица (tenant_id, user_id, feature, count, date).

## 7.5 Loading / Error states

- Loading: spinner + „Правя магия... (5-15 сек)"
- Error: „Неуспех. Опитай пак." + запазва оригиналната
- Success: preview + [Запази това] [Върни оригинала]

## 7.6 Cost tracking

Всяка генерация → INSERT в `ai_spend_log`:
```sql
INSERT INTO ai_spend_log (tenant_id, feature, cost_eur, created_at)
VALUES (?, 'image_studio_bg', 0.05, NOW());
```

Счита се от Пешо-акаунта срещу margin — никога от крайния потребител.

---

# 8. AI ОТ ФАБРИЧЕН ЕТИКЕТ (Gemini Vision)

## 8.1 Overview

Стъпка 1 на wizard: **2 снимки**:
1. Стоката (главна)
2. Фабричен етикет (optional)

Gemini Vision чете etiketa → auto-populate composition + origin_country.

## 8.2 Flow

```
1. Пешо натиска [📷 Snap снимка] (стъпка 1)
2. Прави снимка на стоката
3. Показва се: „Искаш ли да добавиш фабричен етикет за автоматичен състав?"
4. Tap „Да" → втора снимка на етикета
5. Backend: Gemini Vision request:
   "Extract from factory label:
    - Composition (material breakdown with percentages)
    - Origin country
    - Size (if visible)
    - Care instructions (ignore)
    Return JSON."
6. Auto-populate полетата:
   - composition: "100% памук"
   - origin_country: "България"
   - size_hint: "M" (ако видим)
7. Пешо потвърждава или коригира
```

## 8.3 Gemini Vision request

```php
$gemini_response = gemini_vision_request([
  'model' => 'gemini-2.5-flash',
  'image_parts' => [
    ['mime_type' => 'image/jpeg', 'data' => base64_encode($label_image)]
  ],
  'prompt' => 'Extract from this factory label. Return JSON: {composition, origin_country, size}. Composition example: "80% cotton, 20% polyester". Origin country example: "Bulgaria". If not visible, return null.'
]);
```

## 8.4 Fallback

- Ако Gemini не успее (blur, лошо осветление) → „Не мога да прочета. Попълни ръчно?"
- Ако confidence < 0.8 → показва prediction но с жълт border „Сигурен ли си?"

---

# 9. BLUETOOTH PRINT (DTM-5811)

## 9.1 Overview

Принтер DTM-5811 (поръчани 200 бр, април 2026). TSPL protocol, Bluetooth, 50×30mm labels.

## 9.2 Pair flow (Settings)

```
Settings → [Сдвои принтер]
→ Web Bluetooth scan (показва всички BT устройства)
→ Избери „DTM-5811-XXXX"
→ PIN 0000
→ „Свързан" + success toast
→ BDA (DC:0D:51:AC:51:D9) запазен в tenant settings
```

## 9.3 Print flow

След save на артикул или от detail:
```
[🖨 Печат етикет]
→ Print overlay (виж 3.7)
→ [Печатай N етикета]
→ Generate TSPL commands:
  CLS
  SIZE 50 mm, 30 mm
  GAP 3 mm, 0
  DIRECTION 0
  CODEPAGE UTF-8
  TEXT 30,20,"0",0,1,1,"Nike Air Max 42"
  TEXT 30,50,"0",0,1,1,"120 лв / 61.37 €"
  BARCODE 30,100,"128",70,1,0,2,2,"5901234567890"
  PRINT 1
→ Send via Web Bluetooth to characteristic UUID
```

## 9.4 Code

```javascript
async function printLabel(product, format = 'eur_bgn') {
  const device = await getPairedPrinter(); // cached from Settings
  const server = await device.gatt.connect();
  const service = await server.getPrimaryService(0xffb0);
  const char = await service.getCharacteristic(0xffb2);
  
  const tspl = generateTSPL(product, format);
  const bytes = new TextEncoder().encode(tspl);
  
  // Chunked write (BLE MTU ~20 bytes)
  for (let i = 0; i < bytes.length; i += 20) {
    await char.writeValue(bytes.slice(i, i + 20));
    await new Promise(r => setTimeout(r, 50));
  }
}
```

## 9.5 Edge cases

- Принтерът не е pairнат → toast „Сдвои принтер в Settings"
- BT disconnect → auto-reconnect + retry
- Out of paper → принтерът връща error status → „Зареди нови етикети"

---

# 10. EXPANDED FILTER DRAWER

## 10.1 Overview

Button [⚙ filter] до search → отваря drawer с 4 секции.

## 10.2 Drawer layout

```
┌──────────────────────────────────┐
│ × Филтри                    Нулирай │
├──────────────────────────────────┤
│                                    │
│ КЛАСИФИКАЦИЯ                      │
│ Доставчик: [Всички ▾]             │ ← drill-down list
│ Категория: [Всички ▾]             │ ← filtered by supplier
│ Подкатегория: [Всички ▾]          │
│                                    │
│ ЦЕНИ И НАЛИЧНОСТ                  │
│ Цена: [___] до [___] лв          │
│ Наличност: ◉ Всички ○ Има        │
│            ○ Нула ○ Преброени    │
│            ○ Непреброени          │
│                                    │
│ ПРОБЛЕМИ                          │
│ □ Zombie (45+ дни)                │
│ □ Под марж 15%                    │
│ □ Без доставна цена               │
│ □ Без категория                   │
│                                    │
│ СПЕЦИАЛНИ                         │
│ □ Топ продаван                    │
│ □ На промоция                     │
│ □ Без снимка                      │
│ □ Без баркод                      │
│                                    │
├──────────────────────────────────┤
│     [Покажи 47 артикула →]         │
└──────────────────────────────────┘
```

## 10.3 Drill-down категории

При tap на „Категория" → отваря sub-drawer с категориите:
- Ако е избран доставчик → показва САМО негови категории (от `supplier_categories`)
- Ако не е избран доставчик → показва ВСИЧКИ глобални категории
- Header pill показва scope: „От Спорт Груп" или „Всички категории"

## 10.4 Брояч активни филтри

В главната, [⚙3] badge показва колко филтри са активни. Tap чита чипове на активни филтри под search (dismissible „×").

## 10.5 Save / reset

- Save: филтрите се запазват в sessionStorage (презареждане запазва)
- Reset: бутон „Нулирай" в header на drawer

---

# 11. VOICE OVERLAY (universal)

Виж **секция 4.3** (AI Wizard). Прилага се НАВСЯКЪДЕ където има 🎤 бутон:
- Главната search
- Wizard — всеки 🎤 бутон
- AI Wizard
- Full list search
- Ai Chat (отделен модул)

---

# 12. compute-insights.php ФУНКЦИИ ЗА PRODUCTS

15 функции, всяка маркирана с `fundamental_question`.

## 12.1 Шаблон на функция

```php
function insight_zero_stock_with_sales($tenant_id, $store_id) {
  $rows = DB::run("
    SELECT p.id, p.name, p.retail_price, p.cost_price,
           COUNT(si.id) as sold_30d,
           SUM(si.quantity) as qty_sold
    FROM products p
    JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
    LEFT JOIN sale_items si ON si.product_id = p.id
    LEFT JOIN sales s ON s.id = si.sale_id 
      AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      AND s.status != 'canceled'
    WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
      AND i.quantity = 0
    GROUP BY p.id
    HAVING sold_30d > 0
    ORDER BY qty_sold DESC
    LIMIT 10
  ", [$store_id, $tenant_id])->fetchAll();
  
  foreach ($rows as $r) {
    $velocity = $r['qty_sold'] / 30; // per day
    $weekly_loss = $velocity * 7 * $r['retail_price'];
    
    DB::run("INSERT INTO ai_insights 
      (tenant_id, store_id, topic_id, module, urgency, 
       fundamental_question, pill_text, value_numeric, product_id, 
       computed_at, expires_at)
      VALUES (?, ?, 'zero_stock_sales', 'products', 'critical', 
              'loss', ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY))",
      [$tenant_id, $store_id, 
       "{$r['name']} · {$r['sold_30d']} прод/30д · свършил",
       round($weekly_loss, 2), $r['id']]);
  }
}
```

## 12.2 Всички 15 функции

### Loss
1. `insight_zero_stock_with_sales` — 0 бр + продажби
2. `insight_below_min_urgent` — под min + висок velocity
3. `insight_running_out_today` — ще свърши днес

### Loss Cause
4. `insight_selling_at_loss` — retail < cost
5. `insight_no_cost_price` — cost_price IS NULL
6. `insight_margin_below_15` — profit% < 15
7. `insight_seller_discount_killer` — продавачка раздава маржа

### Gain
8. `insight_top_profit_30d` — топ 5 по profit
9. `insight_profit_growth` — ръст спрямо миналия месец

### Gain Cause
10. `insight_highest_margin` — най-висок марж%
11. `insight_trending_up` — velocity ↑ 20%+
12. `insight_loyal_customers` — 3+ повторни купувачи
13. `insight_basket_driver` — често купуван с X
14. `insight_size_leader` — размер M най-продаван

### Order
15. `insight_bestseller_low_stock` — топ + ниска наличност
16. `insight_lost_demand_match` — lost_demand + наличен доставчик

### Anti-Order
17. `insight_zombie_45d` — 45+ дни без продажба
18. `insight_declining_trend` — спад 20%+ за 6 седмици
19. `insight_high_return_rate` — връщания > 10%

## 12.3 Cron

`/etc/cron.d/runmystore-insights`:
```
# Products insights — всеки час
0 * * * * www-data php /var/www/runmystore/cron-insights.php --module=products
```

---

# 13. AJAX ENDPOINTS (products.php)

Всички endpoints са в `products.php?ajax=X`.

| Endpoint | Method | Returns | Сесия |
|---|---|---|---|
| `ajax=sections` | GET | 6-те секции с insights | S79 |
| `ajax=list` | GET | Paginated list | S79 |
| `ajax=search` | GET | Search results | EXISTS |
| `ajax=product_detail` | GET | Full product data | EXISTS |
| `ajax=product_save` | POST | Create/Update | EXISTS (product-save.php) |
| `ajax=variations_matrix` | GET | Matrix overlay data | S80 |
| `ajax=csv_preview` | POST | Parse CSV + detect columns | S82 |
| `ajax=csv_import` | POST | Bulk insert | S82 |
| `ajax=voice_parse` | POST | Gemini parse voice input | S81 |
| `ajax=label_ocr` | POST | Gemini Vision label | S81 |
| `ajax=image_studio` | POST | fal.ai request | S81 |
| `ajax=ai_description` | POST | AI SEO description | S81 |
| `ajax=filter_options` | GET | Categories/Suppliers for drawer | S82 |

## 13.1 `ajax=sections` response

```json
{
  "q1": {
    "total_pill": "−340 лв/седм",
    "total_value": 340,
    "items": [
      {
        "id": 123,
        "name": "Nike Air Max 42 черни",
        "retail_price": 120,
        "stock": 0,
        "image_url": "/uploads/...",
        "tag": "0 БР",
        "context": "3 търсения /7д · <b>~360 лв profit/мес пропуснат</b>",
        "fundamental_question": "loss"
      }
    ]
  },
  "q2": {...},
  ...
}
```

## 13.2 `ajax=list` parameters

- `offset`: int
- `limit`: 20 default
- `sort`: `newest` | `name` | `price` | `profit`
- `filter_supplier`: int[] (multi)
- `filter_category`: int[]
- `filter_price_min`: float
- `filter_price_max`: float
- `filter_stock`: `all` | `has` | `zero` | `counted` | `uncounted`
- `filter_problems`: `zombie` | `under_margin` | `no_cost` | `no_category`
- `filter_special`: `top_seller` | `promo` | `no_photo` | `no_barcode`
- `q`: search query

---

# 14. DB ЗАЯВКИ (референции)

## 14.1 sold_30d subquery (бъг #7 fix)

```sql
SELECT p.*, 
       IFNULL(sold.qty, 0) as sold_30d,
       IFNULL(sold.profit, 0) as profit_30d
FROM products p
LEFT JOIN (
  SELECT si.product_id,
         SUM(si.quantity) as qty,
         SUM(si.quantity * (si.unit_price - COALESCE(p.cost_price, 0))) as profit
  FROM sale_items si
  JOIN sales s ON s.id = si.sale_id
  JOIN products p ON p.id = si.product_id
  WHERE s.store_id = ? 
    AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND s.status != 'canceled'
  GROUP BY si.product_id
) sold ON sold.product_id = p.id
WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
ORDER BY p.created_at DESC
LIMIT 20 OFFSET 0;
```

## 14.2 Variant mini-dots в list

```sql
SELECT v.id, v.axes_json, i.quantity
FROM products v
JOIN inventory i ON i.product_id = v.id AND i.store_id = ?
WHERE v.parent_id = ? AND v.is_active = 1
ORDER BY v.axes_json;
```

Във frontend: parse `axes_json` → показва `40`, `41`, `42 (red ако qty=0)`, `43`.

---

# 15. EMPTY STATES / LOADING / ERRORS

## 15.1 Loading

- Skeleton cards в всяка секция (3 бр per секция)
- Shimmer animation

## 15.2 Empty main page (нов tenant)

```
┌────────────────────────────────┐
│       Онбординг prompt         │
│                                 │
│  📦 Нямаш артикули още         │
│                                 │
│  Започни с:                     │
│  [🎤 Gласно]  [📷 Снимка]      │
│  [✏️ Ръчно]  [📄 CSV]          │
│                                 │
│  Или „Пропусни и започни да     │
│  продаваш — системата ще се     │
│  учи от продажбите"             │
└────────────────────────────────┘
```

## 15.3 Empty секция

Виж 1.8 (скрива се или показва friendly message).

## 15.4 Network error

Toast: „Няма връзка. Опитай пак."

## 15.5 Server error

Toast: „Нещо не е наред. Опитай пак след малко." + log към Sentry.

---

# 16. INTERACTION PATTERNS

## 16.1 Основни

| Tap | Action |
|---|---|
| Article card (главна) | Product detail overlay |
| Q-head / total pill | Full list filtered by тази категория |
| [+ Добави артикул] | Quick actions menu (AI / Ръчно / CSV / Scan) |
| [🖨] на артикул | Print overlay |
| [⚙] filter | Expanded filter drawer |

## 16.2 Gesture-базирани

| Gesture | Action |
|---|---|
| Swipe left на article в list | Quick add to order |
| Swipe right | Mark as counted |
| Pull-to-refresh главна | Reload всички секции |
| Long-press на article | Context menu (edit/delete/print/duplicate) |

## 16.3 Haptic feedback

- Light tap: `navigator.vibrate(5)` — на всички buttons
- Medium: `navigator.vibrate(10)` — на important actions (save, send)
- Success: `navigator.vibrate([5,50,5])` — след успешен save

---

# 17. EDGE CASES

## 17.1 Потребители с много артикули (500+)

- Главната изпълнява 6 парallelen SQL-а → общо време < 500ms
- Ако > 500ms → cache в ai_insights таблица (hourly cron)
- List с infinite scroll + 20 per page

## 17.2 Потребители с 0 артикули (нов tenant)

- Empty state с онбординг prompt (15.2)
- 6-те секции скрити
- Focus на „Добави първи артикул"

## 17.3 Бавен интернет

- Timeout 10 сек на AJAX
- Fallback към cached данни в sessionStorage
- Toast „Работя офлайн"

## 17.4 Offline mode (Capacitor PWA)

- Product list cached в IndexedDB
- Search работи върху cached данни
- Wizard може да добавя артикули (queue for sync)
- Sync при reconnect

## 17.5 Multi-store tenant

- Store switcher в header променя `store_id`
- Всички секции reload с новия store_id
- Inventory per store (не global)

## 17.6 Variant артикули (родител)

- Главната показва родителя като 1 card
- Детайлът разширява всички вариации
- Search филтрира parent-only (бъг #3 S72 fix)

## 17.7 Деактивиран артикул с inventory

- Signal в секция 6 (Anti-Order): „deactivated_with_stock"
- „Активирай пак?" или „Разпродай и затвори"

## 17.8 Недовършен артикул

- `is_complete = 0` → показва в list с „⚠ Недовършен"
- На главната: **не се** включва в 6-те секции (filter `is_complete = 1`)
- Tap → wizard на правилната стъпка (коя липсва)

## 17.9 Артикул без снимка

- SVG силует placeholder в секциите
- В list — малък icon
- Soft nudge: „📷 Добави снимка — артикулите със снимки продават 3× повече"

## 17.10 Дублирани артикули

- При save → проверка по `code` → error „Артикулен номер съществува"
- Similarity check (name + category + price) > 90% → warning „Има подобен артикул. Дубликат?"

---

**КРАЙ НА PRODUCTS DESIGN LOGIC**
