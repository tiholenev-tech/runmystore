# 🎯 WIZARD "ДОБАВИ АРТИКУЛ" v5 — ПЪЛНА СПЕЦИФИКАЦИЯ

**Версия:** 5.0 FINAL
**Дата:** 15 май 2026 (S145 шеф-чат)
**Approved by:** Тихол
**Тип:** Спецификация за дизайн чат — създаване на mockup HTML файл

---

## 📌 ЗА КОГО Е ТОЗИ ДОКУМЕНТ

Този файл описва пълните визуални и логически изисквания за **wizard "Добави артикул"** в RunMyStore.AI. Документът е написан така, че **друг чат** (специализиран в дизайн) да може да създаде HTML mockup файл без да задава допълнителни въпроси за визията.

**Reference материали в repo-то:**
- `mockups/P13_bulk_entry.html` — canonical акордеонен mockup (база за стилове)
- `mockups/P15_simple_FINAL.html` — Simple home (kp-pill, glass cards)
- `mockups/ai_studio_FINAL_v5.html` — AI Studio (отделна страница, линкова връзка)
- `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` — design tokens (CSS variables, colors, typography)
- `docs/AI_AUTOFILL_SOURCE_OF_TRUTH.md` — AI Vision flow (Gemini 2.5 Flash, JSON schema)
- `AUTO_PRICING_DESIGN_LOGIC.md` — AI markup formulas (×2.5+.99, confidence routing)
- `TOMORROW_WIZARD_REDESIGN.md` — DB колони добавени (gender/season/brand)

---

## 1. MISSION & ПРИНЦИПИ

### 1.1 Концепция в едно изречение

> **Пешо снима артикула → AI разпознава всичко (категория, цвят, пол, сезон, марка, описание) → Пешо потвърждава с 1 tap.**

### 1.2 Закон №1 — ПЕШО НЕ ПИШЕ

Native клавиатура **никога** не се появява. Всички полета са:
- Voice (микрофон)
- Снимка (камера)
- Сканиране (баркод)
- Tap на chip / dropdown
- Custom numpad (НЕ system keyboard)

### 1.3 Закон №6 — SIMPLE = СИГНАЛИ · DETAILED = ДАННИ

Wizard работи в **един и същ код** за двата режима:
- **Пешо (Simple mode)** — отваря само първия акордеон (Снимка + Основно), записва, готов.
- **Митко (Detailed mode)** — разтваря всички 4 акордеона, попълва ръчно където AI не е попаднал.

### 1.4 "ЧИСТА МАГИЯ" принцип (КЛЮЧОВО)

> *Цитат на Тих: "ЦВЕТОВЕ ПОЛ СЕЗОННОСТ КАТЕГОРИЯ!!!! ЧИСТА МАГИЯ!"*

- **Има снимка** → AI попълва ВСИЧКО автоматично → видимо в **първи акордеон** със ✨ AI badge
- **Няма снимка** → AI полетата (Пол, Сезон, Марка, Кратко описание) се **скриват** от първи акордеон → пренасочват се към **3-ти акордеон "Допълнителни"** за ръчно попълване

Това е conditional rendering — UI се адаптира спрямо контекста.

### 1.5 Без чернова

Минималният запис (име + цена + бройка) = **истински продукт** в DB. НЕ "Запази като чернова". Артикул с непълни данни се показва в списъка с ⚠ "недовършен" chip → tap → отваря wizard на липсваща стъпка.

---

## 2. SACRED ZONES — НЕ СЕ ПИПАТ

Тези функции работят перфектно. Дизайн чатът може да ги използва **само за справка** в mockup-а, но не променя поведението им:

| Файл / функция | Какво прави | Защо sacred |
|---|---|---|
| `services/voice-tier2.php` | Whisper Groq STT за числа/цени | LOCKED от S95 |
| `services/ai-color-detect.php` | Color detection от снимка (включително `?multi=1`) | Работи перфектно, Тих работи много по него |
| `js/capacitor-printer.js` | DTM-5811 Bluetooth принтер | Production-tested |
| 8 mic input полета | Voice triggers в wizard | LOCKED от S95 |
| `_wizMicWhisper`, `_wizPriceParse`, `_bgPrice` | Voice parsing функции | LOCKED от S95 |

**Multi-photo color detection:** `services/ai-color-detect.php?multi=1` — приема няколко снимки, връща JSON с разпознат цвят за всяка. Tих го е работил много, работи perfectly. **НЕ ПИПАМЕ.**

---

## 3. WIZARD ENTRY POINTS

Wizard се отваря от 4 точки:

1. **Simple home (Пешо)** — "Добави артикул" qa-btn в `chat.php`
2. **Detailed home (Митко)** — quick action в `products-v2.php` Detailed mode
3. **Bulk add след save** — bottom bar [+ Следващ ▾] → "Празно"
4. **"Като предния"** — от Simple home kp-pill, или от bottom bar Next menu → "Като предния"

Wizard НЕ е страница — той е **fullscreen overlay** над `products-v2.php`.

---

## 4. TOP HEADER (СТРУКТУРА)

Wizard има свой top bar (НЕ ползва глобалния header форма Б).

```
┌─────────────────────────────────────────────────┐
│ [←]  Добави артикул   [🟣 Като предния] [🔍] [🌙] │  ← 56px height
└─────────────────────────────────────────────────┘
```

**Елементи (отляво надясно):**
1. **← Назад** — иконен бутон (28px svg в 38px кръг) → затваря wizard (с потвърждение ако има промени)
2. **"Добави артикул"** — заглавие (15px, font-weight 800, gradient text)
3. **🟣 "Като предния" pill** — purple gradient (q-magic), винаги видим, **active state ако режимът е включен**
   - SVG: refresh-arrow icon
   - Tap → активира bulk mode (виж §13)
4. **🔍 Скенирай pill** — accent gradient (за бърз scan на баркод преди да започне wizard)
5. **🌙 Тема** — light/dark toggle

**Под header (само в bulk mode):**

```
┌────────────────────────────────────────────────┐
│ 🟣 Режим Като предния · име, цена, доставчик │
│    наследени от [Дамски бикини Tommy Jeans] [×]│
└────────────────────────────────────────────────┘
```

---

## 5. SEARCH PILL + VOICE BAR

Под header има 2 reusable controls от P13 (mockup base):

### 5.1 Search pill (collapsed)

```
🔍 [Намери артикул да копираме] 🎤
```

- Tap → expand до full panel със search input + filter chips (Като последния / Всички / Tommy Jeans / Бельо / Тениски / Наскоро) + резултати
- Tap на резултат → копира продукт като база за новия

### 5.2 Voice command bar (purple)

```
🟣 [🎙] Кажи "Дамски бикини 28 лева Tommy" → AI парсва ↓
```

- Bulk voice flow: Пешо казва "Дамски бикини 28 лева Tommy" → AI парсва име/цена/доставчик едновременно

---

## 6. MODE TOGGLE (под search/voice bar)

Pill toggle между двата режима:

```
┌──────────────────────────────────┐
│  [Единичен]  [С вариации]       │  ← active state purple gradient
└──────────────────────────────────┘
```

**Поведение:**
- **Единичен** → Section 1 показва "Количество" и "Мин. количество" полета. Section 2 (Вариации) е скрита.
- **С вариации** → Section 1 крие qty полетата. Section 2 е активна (размери, цветове, matrix).

---

## 7. STRUCTURE: 4 АКОРДЕОНА

(Преди беше 5 в P13. Слети sections 1+4: Минимум + Снимки → СНИМКА + ОСНОВНО.)

```
┌────────────────────────────────────────────┐
│ 1. 🟣 СНИМКА + ОСНОВНО   (open default)   │  ← active, conic spin
│ 2. ⚪ Вариации             (само ако С вар.)│  
│ 3. ⚪ Допълнителни         (fallback)       │  
│ 4. 🟣 AI Studio            (magic, empty)   │  ← purple magic
└────────────────────────────────────────────┘
```

**State indicators (acc-head-ic):**
- ⚪ **Empty** (gray) — нищо попълнено
- 🟣 **Active** (purple gradient + conic spin) — текущо отворен или с AI работа
- 🟢 **Filled** (зелен check) — попълнен и валидиран
- ⚠ **Error** (червен) — validation грешка
- 🟪 **Magic** (purple gradient + conic spin) — AI Studio акордеон (винаги special)

**Sacred CSS class:** `.acc-section` (от P13). Glass neon ефект в dark mode (conic-gradient border, oklch colors). НЕ опростявай.

---

## 8. SECTION 1: СНИМКА + ОСНОВНО (детайлно)

> **Това е централната секция — 80% от UX магията е тук.**

### 8.1 Photo mode toggle (само ако С вариации)

```
┌──────────────────────────────────────────┐
│ [📷 Една снимка] [🔲 Различни цветове]   │
└──────────────────────────────────────────┘
```

- **Една снимка** — Пешо снима 1 артикул (single product, или вариант с общ look)
- **Различни цветове** — Пешо снима всеки цвят отделно. AI разпознава всеки цвят, попълва в Section 2 (Вариации) chips автоматично.

### 8.2 Photo zone

**Empty state:**
```
┌──────────────────────────────────────────┐
│           ✨                              │
│      Снимай артикула                     │
│   AI ще разпознае всичко                 │
│                                          │
│   [📷 Камера]    [🖼 Галерия]           │
└──────────────────────────────────────────┘
```
- Drop zone, 250px високо
- 2 бутона: 📷 Камера / 🖼 Галерия
- Drag-drop активен за desktop

**With photo:**
```
┌──────────────────────────────────────────┐
│ [   IMAGE PREVIEW 16:10 aspect ratio  ]  │
│                                          │
│ [📷 Снимай отново]  [🖼 Галерия]        │
│                                          │
│ [🖼 Махни фона на всички N снимки]      │  ← само в multi mode
└──────────────────────────────────────────┘
```

**Loading state (AI анализира):**
```
┌──────────────────────────────────────────┐
│ [    IMAGE PREVIEW (полупрозрачно)    ]  │
│                                          │
│          ◌  spinner                      │
│      AI разпознава ...                   │
└──────────────────────────────────────────┘
```

### 8.3 AI Vision Banner (показва се след успешно разпознаване)

```
┌──────────────────────────────────────────┐
│ ✨ AI разпозна 6 полета                  │
│ Цвят · Пол · Сезон · Марка · Описание    │
│                              [Приеми]    │
└──────────────────────────────────────────┘
```

- Background: q-magic gradient (purple → pink)
- Conic-gradient overlay animation (5s linear infinite)
- Tap [Приеми] → confirms all, hides banner

### 8.4 ОСНОВНИ ПОЛЕТА (винаги видими)

**Име** *(задължително):*
```
ИМЕ *
[Дамски бикини Tommy Jeans              ] [↻] [🎤]
```
- Web Speech voice
- Copy от последния (↻) бутон ако има предишен

**Цена** *(задължително):*
```
ЦЕНА *
[28.00] € [↻] [🎤]
54,76 лв

╔══════════════════════════════════════════╗
║ 🟢 AI предлага €27.99                    ║
║    ×2.5 + .99 · бельо · confidence 92%   ║
║                       [✓]  [Друга]       ║
╚══════════════════════════════════════════╝
```

- Custom numpad на tap (НЕ native keyboard)
- Whisper voice
- **AI markup row** (зелен banner) — показва се при налична доставна цена (cost_price)
- Виж §12 за пълна логика на AI markup

**Количество** *(само Единичен, задължително):*
```
КОЛИЧЕСТВО *
[−] [    5    ] [+] [🎤]
```
- Custom stepper (−/+ бутони + numeric input)
- Whisper voice

**Минимално кол-во** *(само Единичен):*
```
МИНИМАЛНО КОЛ-ВО *  (за сигнали)
[−] [    2    ] [+] [🎤]
AI auto-set от количеството (qty/2.5).
```
- Amber/warn колор (q-amber border)
- AI auto-calculates: Math.round(qty/2.5)

### 8.5 AI РАЗПОЗНАТИ ПОЛЕТА (conditional — само ако има снимка)

> **Тези 5 полета са в Section 1 САМО ако има снимка. Без снимка → отиват в Section 3 (Допълнителни).**

**Категория** — special UX (Rule #38: AI НИКОГА не създава нова сама)

```
КАТЕГОРИЯ * [✨ AI]
[Бикини                ▾]  [+] [🎤]

╔══════════════════════════════════════════╗
║ Намерих Бикини — закачам там?            ║
║                        [✓ ДА]  [✗ НЕ]    ║
╚══════════════════════════════════════════╝
```

При **НЕ** — показват се 3 алтернативи + "Кажи ти":

```
[Дамски бикини] [Бельо] [Бельо и чорапи] [🎤 Кажи ти]
```

**Пол** — 4 chips (single-select):
```
ПОЛ  [✨ AI]
┌──────────┬──────────┬──────────┬──────────┐
│ Мъжко    │ Женско ✨│ Детско   │ Унисек   │  ← active = purple (q-magic)
└──────────┴──────────┴──────────┴──────────┘
```
- AI предлага → подсветва един с q-magic gradient + ✨ corner badge
- User може да override → AI suggestion се сменя на нормален active state

**Сезон** — 4 chips (single-select):
```
СЕЗОН  [✨ AI]
┌──────────┬──────────┬──────────┬──────────┐
│ Лято ✨  │ Зима     │ Преходен │Целогодишно│
└──────────┴──────────┴──────────┴──────────┘
```

**Марка** — text input + recently used chips:
```
МАРКА  [✨ AI]
[Tommy Jeans                            ] [🎤]
[Nike] [Adidas] [Calvin Klein] [Mango]
```
- Voice input (Web Speech, не Whisper — Марка е текст)
- Recently used chips (history от tenant)
- Empty state ако AI не разпозна лого: "AI не разпозна марка от логото — кажи я"

**Кратко описание** — textarea + AI generate бутон + counter:
```
КРАТКО ОПИСАНИЕ  [✨ AI]
╔══════════════════════════════════════════╗
║ Дамски бикини от мек памук с ластан.     ║
║ Розов цвят с малки бели точки.           ║
║ Класически крой с ниска талия...         ║
║                                32 / 20–50║  ← counter, ok=зелено
╚══════════════════════════════════════════╝
[✨ Генерирай отново]  [🎤]
```

- Character counter (думи): 20-50 ✓ зелено, иначе амбер
- ✨ AI generate бутон — повторно обажда AI (€0.02)
- Voice (Web Speech)
- 20-50 думи, factual (само какво се вижда)

### 8.6 Опционални полета (винаги видими)

**Артикулен номер:**
```
АРТИКУЛЕН НОМЕР  [ПО ЖЕЛАНИЕ]
[                                       ] [↻] [🎤]
Празно → AI ще генерира уникален код автоматично.
```

**Баркод:**
```
БАРКОД  [ПО ЖЕЛАНИЕ]
[Скенирай или въведи              ] [📷 scan] [🎤]
Празно → AI ще генерира EAN-13 при отпечатване.
```

### 8.7 Save row (footer на акордеона)

```
[✓ Запази]  [🖨]  [📁]
```

- **Запази** — зелен primary (q-gain gradient, conic spin)
- **🖨 Печат** — aux
- **📁 CSV** — aux

---

## 9. SECTION 2: ВАРИАЦИИ

Показва се **само ако** mode toggle = "С вариации".

### 9.1 Multi-photo hint (показва се ако AI разпозна 2+ цвята в Section 1)

```
┌──────────────────────────────────────────┐
│ ✨ AI разпозна 3 цвята от снимките ·     │
│ вече попълнени отдолу · можеш само да    │
│ допълниш размерите ↓                     │
└──────────────────────────────────────────┘
```

### 9.2 Размери chips

```
РАЗМЕРИ
[XS] [S✓] [M✓] [L✓] [XL] [XXL]
[+ добави размер]  [🔲 други групи ▸]
```

- Multi-select chips
- "Добави размер" → inline input
- "Други групи" → bottom sheet с groups (буквени, числови, US, EU)

### 9.3 Цветове chips (AI-filled от multi-photo)

```
ЦВЕТОВЕ  [✨ AI]
[● Бял✨][● Розов✨][● Черен✨] [● Червен] [● Син] [+ добави]
```

- Покрай ai-filled chips (purple gradient + ✨ corner) — които AI разпозна
- Останалите — нормални chips за ръчно избиране
- Color swatch dot (.chip-col-dot) от категорията цветове

### 9.4 Matrix за бройки

```
БРОЙ ПО КОМБИНАЦИЯ · МИН.        [+= Всички = 2] [⛶ Цял екран]

┌──────┬──────┬──────┬──────┐
│      │ Бял  │ Розов│ Черен│
├──────┼──────┼──────┼──────┤
│  S   │ 2 м1 │ 3 м1 │ 0 м0 │
│  M   │ 3 м1 │ 4 м2 │ 1 м1 │
│  L   │ 1 м1 │ 1 м1 │ 0 м0 │
└──────┴──────┴──────┴──────┘
```

- Inline 2-axis grid
- Per-cell: qty input (top) + мин input (bottom, amber color)
- AutoMin: `Math.round(qty/2.5)` (min 1) — checkbox toggle
- Visual: green ако qty>min, yellow ако 0<qty<min, red ако qty=0
- Tap "Цял екран" → fullscreen overlay (от P12 mockup)

### 9.5 SKU summary

```
✓ 3 размера × 3 цвята = 9 SKU · Σ 22 бр.
```

### 9.6 Save row

```
[✓ Запази]  [🖨]  [📁]
```

---

## 10. SECTION 3: ДОПЪЛНИТЕЛНИ

> Двойна функция:
> 1. **Fallback** за AI полета когато няма снимка
> 2. **Останалите** не-AI полета (цени, доставчик, материя, произход, мерна единица)

### 10.1 Info banner (горе в акордеона)

```
┌──────────────────────────────────────────┐
│ ℹ Полета за ръчно попълване              │
│ Без снимка → попълни тук Пол, Сезон,     │
│ Марка, Описание                          │
└──────────────────────────────────────────┘
```

### 10.2 Fallback AI полета (показват се само ако НЯМА снимка в Section 1)

**Пол** — 4 chips (БЕЗ ai-suggested state):
```
ПОЛ
[Мъжко] [Женско] [Детско] [Унисек]
```

**Сезон** — 4 chips:
```
СЕЗОН
[Лято] [Зима] [Преходен] [Целогодишно]
```

**Марка** — input + recently used chips (без AI prefill):
```
МАРКА
[                                       ] [🎤]
[Nike] [Adidas] [Calvin Klein] [Mango]
```

**Кратко описание** — textarea + counter (БЕЗ AI generate):
```
КРАТКО ОПИСАНИЕ
[                                       ]
                                  0 / 20–50
[🎤]
```

### 10.3 Не-AI полета (винаги тук)

**Доставна цена:**
```
ДОСТАВНА ЦЕНА  (на доставчик)  [↻]
[12.00] € [↻] [🎤]
23,47 лв
```

**Цена едро:**
```
ЦЕНА ЕДРО
[20.00] € [↻] [🎤]
39,12 лв
```

**Марж badge (auto):**
```
МАРЖ (auto)              [+133%]  ← green q-gain
```

**Доставчик** — autocomplete dropdown (НЕ bottom sheet — както в текущ код):
```
ДОСТАВЧИК  [↻]
[Tommy Hilfiger Distribution    ▾] [↻] [+] [🎤]
```

**Категория** — autocomplete (само от избрания доставчик):
```
КАТЕГОРИЯ  (от избрания доставчик)
[Дамски                          ▾] [↻] [+] [🎤]
```

**Подкатегория** — autocomplete (само от избраната категория):
```
ПОДКАТЕГОРИЯ
[Тениски                         ▾] [↻] [+] [🎤]
```

**Материя/състав:**
```
МАТЕРИЯ / СЪСТАВ
[напр. 100% памук                       ] [🎤]
```

**Произход:**
```
ПРОИЗХОД
[България                        ▾] [+] [🎤]
```

**Мерна единица:**
```
МЕРНА ЕДИНИЦА
[Брой                            ▾]
[🔲 други групи ▸]
```

### 10.4 Save row

```
[✓ Запази]  [🖨]  [📁]
```

---

## 11. SECTION 4: AI STUDIO

> **AI Studio = ОТДЕЛНА страница** (`ai_studio_FINAL_v5.html`). Wizard има само линк навън.
> 
> **Изключение:** Махане на фон + SEO описание = ЛЕСНИ функции, могат да са в Section 1 като quick buttons (виж §8.3 AI Vision Banner).
> 
> **AI Магия (try-on / студийна)** = НЕ работи в wizard, трябват специални команди → отделна страница.

### 11.1 Credits strip

```
┌──────────────────────────────────────────┐
│ 💎 17 / 30 безплатни магии · след това   │
│    €0.05/магия                           │
└──────────────────────────────────────────┘
```

### 11.2 AI Studio entry link

```
┌──────────────────────────────────────────┐
│ 🟣 Отвори AI Studio                      │
│    снимка · фон · описание · магия    ›  │
└──────────────────────────────────────────┘
```

- Tap → отваря `ai_studio_FINAL_v5.html` в нов overlay
- След обработка → връща в wizard на същата стъпка със new photo

### 11.3 Quick buttons (опционални — за дублиране на функции от Section 1)

```
[🖼 Премахни фон]  [📝 SEO описание]
```

Тези работят inline (€0.05 + €0.02). AI Магия (€0.30) — само през Studio.

---

## 12. AI MARKUP AUTO-SUGGESTION (за цени)

> Reference: `AUTO_PRICING_DESIGN_LOGIC.md`

### 12.1 Mission

> **Пешо никога не въвежда продажна цена. AI я предлага по неговия стил.**

### 12.2 Cold start (нов tenant)

Първа доставка — модал преди review screen:

**Въпрос 1 — multiplier:**
```
Първа доставка. Каква ти е наценката?
🎤 Кажи или избери:
[×1.5]  [×1.8]  [×2]  [×2.5]  [×3]  [Друго]
```

**Въпрос 2 — ending pattern:**
```
Кръгли ли ги цените?
[X.90]  [X.99]  [X.50]  [Точни]
```

### 12.3 Markup row в wizard (под полето Цена)

Показва се при налична доставна цена (cost_price):

```
╔══════════════════════════════════════════╗
║ 🟢 AI предлага €27.99                    ║
║    ×2.5 + .99 · бельо · confidence 92%   ║
║                       [✓ Прие] [Друга]   ║
╚══════════════════════════════════════════╝
```

- Background: q-gain gradient (green oklch)
- Формула txt: `[multiplier] + [ending] · [category] · confidence [N]%`
- Tap "смени" → отваря settings за per-category multiplier configuration
- Tap [✓] → applies retail price
- Tap [Друга] → manual edit retail

### 12.4 Confidence Routing (LAW №8)

| Confidence | Action | UX |
|---|---|---|
| > 0.85 | Auto-apply | Toast "✓ €5.90 (×2 + .90)" |
| 0.5 - 0.85 | Confirm dialog | "AI препоръчва €5.90. Да? [Прие] [Друга цена]" |
| < 0.5 | Manual entry | "Нова категория, въведи цена" |

### 12.5 Per-category patterns (примери)

| Категория | Multiplier | Ending | Confidence after 30д |
|---|---|---|---|
| Бельо | ×2.5 | .99 | 0.92 |
| Чорапи | ×1.8 | .50 | 0.88 |
| Тениски | ×2.5 | .90 | 0.95 |
| Бижута евтини | ×3 | точни | 0.85 |
| Бижута скъпи | ×1.8 | .00 | 0.78 |

AI учи всяка категория самостоятелно от 3 източника:
1. Onboarding answers
2. Manual corrections (Пешо смени €5.90 на €6.50 → AI учи)
3. Sales velocity feedback (30 дни нула продажби → намаля multiplier)

---

## 13. "КАТО ПРЕДНИЯ" PATTERN

> *Цитат на Тих: "ТРЯБВА ДА ИМА БУТОНИ КАТО ПРЕДНИЯ И ДА СЛАГА СЪЩОТО КАКВОТО Е БИЛО НА ПРЕДНИЯ АРТИКУЛ."*

### 13.1 Top header pill

Видим **винаги** в top header:

```
🟣 Като предния
```

- Purple gradient (q-magic)
- Active state ако режимът е включен (с conic glow)
- Tap → активира bulk mode

### 13.2 Bulk mode banner (когато активно)

Показва се под header:

```
┌────────────────────────────────────────────────┐
│ 🟣 Режим Като предния · име, цена, доставчик,  │
│    категория · наследени от                    │
│    Дамски бикини Tommy Jeans              [×] │
└────────────────────────────────────────────────┘
```

### 13.3 Bulk add след save (от bottom bar)

В bottom bar има [+ Следващ ▾] бутон → отваря Next menu:

```
┌──────────────────────────────────────┐
│ 📋 Като предния                       │
│    Наследява име · цена · доставчик · │
│    категория                          │
├──────────────────────────────────────┤
│ + Празно                              │
│    Нов артикул от 0 (различен        │
│    доставчик/тип)                     │
└──────────────────────────────────────┘
```

### 13.4 Поведение в bulk mode

> *Цитат на Тих: "И В BULK ДОБАВЯНЕТО ОТ КАТО ПРЕДНИЯ ДА НЕ СЕ ПИШЕ И ЦЕНА ДОРИ."*

Когато режимът е активен:
- **Име** — placeholder показва "наследено: Дамски бикини Tommy Jeans"
- **Цена** — placeholder показва "наследена: €28.00" → дори не се пише
- **Доставчик** — pre-filled със ↻ AI mark
- **Категория** — pre-filled със ↻ AI mark
- **Снимка** — НЕ се наследява (нов артикул)
- **Баркод / Артикулен номер** — НЕ се наследяват (уникални)

User може да override всяко поле.

### 13.5 Per-field copy (отделно от bulk mode)

Всяко поле в Section 1 има малък `[↻]` бутон до voice mic:
- Tap → копира **само това поле** от последния артикул

---

## 14. MULTI-PHOTO ЦВЕТОВЕ

> *Цитат на Тих: "АКО СНИМАМ ВСИЧКИТЕ ЦВЕТОВЕ СЕГА GEMINO РАЗПОЗНАВА ПЕРФЕКТНО ЦВЕТОВЕТЕ (И ТОВА ГО РАБОТИХМЕ МНОГО!!!) НЕКА ОСТАНЕ ТАКА."*

### 14.1 Flow

1. В Section 1 → photo mode toggle = "Различни цветове"
2. Пешо снима всеки цвят отделно (camera или галерия)
3. След всяка снимка → AI разпознава цвета (`services/ai-color-detect.php?multi=1`)
4. Photo grid показва thumbs + recognized color name + confidence %
5. Section 2 (Вариации) → Цветове chips автоматично се попълват от разпознатите
6. Matrix за бройки автоматично се появява
7. Пешо само допълва размерите (или ги допълва ако AI ги е пропуснал)

### 14.2 Photo grid (multi mode)

```
┌─────────┬─────────┬─────────┬─────────┐
│ [photo] │ [photo] │ [photo] │   +     │
│ Бял 94% │Розов 89%│Черен 72%│ Добави  │
└─────────┴─────────┴─────────┴─────────┘

[🖼 Махни фона на всички 3 снимки]
```

- Цветова точка (color swatch dot) + name + AI confidence %
- Conf badge: ≥75% зелено, 50-74% амбер, <50% gray
- "+" Добави още снимка
- Edit color name ако AI грешно (text input под thumb)
- **Bulk bg removal бутон** — за всички снимки наведнъж, без излизане от wizard

### 14.3 Section 2 multi-photo hint

В Section 2 (Вариации), когато 2+ цвята са разпознати:

```
┌──────────────────────────────────────────┐
│ ✨ AI разпозна 3 цвята от снимките ·     │
│ вече попълнени отдолу · можеш само да    │
│ допълниш размерите ↓                     │
└──────────────────────────────────────────┘
```

И chips отдолу — purple gradient + ✨ corner badge.

---

## 15. VOICE STT POLICY

> Reference: `PRODUCTS_WIZARD_v4_SPEC.md` §2, LOCKED в S95.

### 15.1 Numeric полета → ВИНАГИ Whisper Groq

- `retail_price`, `wholesale_price`, `cost_price`
- `quantity` (per matrix cell)
- `barcode` (числов string)
- `code_sku`

### 15.2 Text полета → Web Speech (browser native, безплатно)

- `name`, `description`
- `material`, `origin`
- `supplier_name`, `category`, `subcategory`
- `color`, `size`
- `brand` ← (нов)
- `zone`

### 15.3 Hybrid input (mixed text + numbers)

Voice command "Червени бикини 25 лева" → parallel run:
- Web Speech → "червени бикини"
- Whisper → "25"
- Parser merge → { name: "червени бикини", price: 25 }

### 15.4 Voice auto-advance

> *Цитат на Тих: "С ДВЕТЕ — 2 СЕКУНДИ ТИШИНА + БУТОН МИГНОВЕННО."*

И двата начина работят паралелно:

| Случай | Какво се случва |
|---|---|
| **Voice mode** + 2 сек silence | Auto-advance към следващото поле |
| **Voice mode** + ✓ tap | Manual advance веднага (по-бързо) |
| **Manual mode** | Само ✓ tap (без auto-advance) |

Логика: Пешо със заети ръце → ползва silence (hands-free). Пешо със свободни ръце → ползва бутон (по-бързо).

---

## 16. CONDITIONAL LOGIC — ИМА / НЯМА СНИМКА

Това е КЛЮЧОВОТО UX правило.

### 16.1 С снимка → ЧИСТА МАГИЯ

```
SECTION 1: СНИМКА + ОСНОВНО
├── Photo zone (с image)
├── AI Vision Banner ("AI разпозна 6 полета")
├── Име
├── Цена + AI markup row
├── Количество (single)
├── Мин (single)
├── ── AI РАЗПОЗНАТИ (с ✨) ──
│   ├── Категория + ДА/НЕ confirm
│   ├── Пол chips (AI suggested)
│   ├── Сезон chips (AI suggested)
│   ├── Марка input + brand chips
│   └── Кратко описание textarea
├── Артикулен номер (по желание)
└── Баркод (по желание)

SECTION 3: ДОПЪЛНИТЕЛНИ
├── ── НЕ-AI полета само ──
├── Доставна цена
├── Цена едро
├── Марж auto
├── Доставчик
├── Категория (manual override)
├── Подкатегория
├── Материя
├── Произход
└── Мерна единица
```

### 16.2 Без снимка → fallback

```
SECTION 1: СНИМКА + ОСНОВНО
├── Photo zone (empty drop zone)
├── Име
├── Цена + AI markup row (само ако има cost_price)
├── Количество
├── Мин
├── Артикулен номер
└── Баркод

SECTION 3: ДОПЪЛНИТЕЛНИ  ← AI полета мигрират ТУК
├── ── AI полета (без ✨, ръчни) ──
│   ├── Пол chips (manual)
│   ├── Сезон chips (manual)
│   ├── Марка input + brand chips
│   └── Кратко описание textarea
├── ── НЕ-AI полета ──
│   ├── Доставна цена ... (както по-горе)
```

### 16.3 Implementation hint

JS логика: проверка `S.wizData._photoDataUrl !== null`:
- Truthy → показва `#aiRecognizedBlock` в Section 1, скрива fallback в Section 3
- Falsy → крие `#aiRecognizedBlock` в Section 1, показва fallback в Section 3

---

## 17. STATE INDICATORS

### 17.1 На акордеоните (acc-head-ic)

- ⚪ **Empty** — gray background, gray icon, hue=defaults
- 🟣 **Active** — accent gradient + conic-spin animation + white icon
- 🟢 **Filled** — green gradient (q-gain) + white check
- ⚠ **Error** — red border + amber icon (q-loss)
- 🟪 **Magic** — purple gradient + conic-spin (само за AI Studio акордеон)

### 17.2 На полетата

- **AI auto-filled** → ✨ "AI" badge до label (purple)
- **User edited (override AI)** → ✨ badge изчезва, не маркирано
- **Required not filled** → `req *` маркер (червен)
- **AI suggested chip** → `ai-suggested` клас (purple text + border)
- **AI suggested chip active** → full purple gradient + ✨ corner

---

## 18. ГОТОВНОСТ % ФОРМУЛА

### 18.1 Точки

| Поле | Точки |
|---|---|
| Име + Цена | 20 |
| Бройки (single) или Matrix (variant) | +15 |
| Снимка (вкл. AI auto-fill 9 полета) | +30 |
| Доставчик + Категория | +10 |
| Доставна цена | +20 |
| Баркод или SKU | +5 |
| **Max** | **100** |

### 18.2 3 нива

- 🔴 **Минимална** 0-39 → label "Празни"
- 🟡 **Частична** 40-79 → label "Недовършени"
- 🟢 **Пълна** 80-100 → label "Готови"

### 18.3 4-те нови AI полета (пол / сезон / марка / описание)

> *Цитат на Тих: "НЕ ТЕ СА ЕКСТРА"*

**НЕ влизат в %.** Те са bonus информация. Confidence остава 100% дори без тях.

### 18.4 Live progress bar (опционално в bottom bar)

```
Готовност: 65% Частична ━━━━━━━━━○○○○
```

- Update след всяко поле
- Tap on bar → tooltip "+15 точки ако добавиш доставна цена"

---

## 19. COLOR TOKENS & UI ПРАВИЛА

> Reference: `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` §2

### 19.1 6 hue класа (sacred)

| Class | HSL | Употреба в wizard |
|---|---|---|
| `q-default` | hue1=255, hue2=222 | Header, навигация, neutral |
| **`q-magic`** | hue1=280, hue2=310 | AI badges, snimka, sparkle, AI suggested chips |
| `q-loss` | hue1=0, hue2=15 | Validation errors, Undo бутон |
| **`q-gain`** | hue1=145, hue2=165 | Confidence ≥80, Запиши бутон, AI markup row |
| **`q-amber`** | hue1=38, hue2=28 | Мин. количество warning, Цени accent (НЕ за non-цена) |
| `q-jewelry` | special | (не за wizard) |

### 19.2 НЕ ползвай

- `q-jewelry` или `q-amber` за non-цена секции
- Native `<select>` за категория/доставчик в wizard — autocomplete input (както е сега)
- Native клавиатура — никога
- Emoji в UI — само SVG иконки (стилизирано: ✨ badges са изключение, OK)
- "Скелетон" loaders > 500ms — ако е по-дълго, real progress bar

### 19.3 Sacred CSS правила (от DESIGN_SYSTEM v4)

**Neon Glass dark mode (acc-section, glass cards):**
- `.glass + .shine + .glow` блок
- Conic-gradient border (oklch colors)
- mix-blend-mode: plus-lighter
- Noise SVG mask
- НЕ опростявай. 4 задължителни span-а: `.shine`, `.shine.shine-bottom`, `.glow`, `.glow.glow-bottom`

**Bottom-nav:** Wizard е fullscreen overlay → bottom nav е СКРИТ (виж DESIGN_SYSTEM_v4 §3.3). Wizard има свой bottom bar (Undo/Печат/CSV/Следващ).

---

## 20. КАКВО НЕ ПРАВИМ В WIZARD

❌ **Cancel button** — само × горе вдясно
❌ **Confirm modal "Сигурен ли си?"** — данните се пазят в localStorage (draft auto-save)
❌ **Modal-в-modal** — chips → bottom sheet, не popup
❌ **Auto-advance в manual mode** — само в voice mode (S.wizVoiceMode=true)
❌ **Native клавиатура** — никога (Закон №1)
❌ **Emoji в UI** — само SVG иконки
❌ **"Помощ" tooltip wizards** — UX трябва да е очевиден
❌ **AI магия (try-on / студийна) вътре в wizard** — отделна страница (AI Studio)
❌ **Bottom sheet drawer за категория/доставчик** — autocomplete input (както сега)
❌ **Нова категория от AI** — Rule #38, AI само предлага съществуващи

---

## 21. EDGE CASES

### 21.1 Draft recovery

Wizard auto-save в `localStorage['_rms_wizDraft_TENANTID']` на всяко render.
При повторно отваряне:
```
"Намерих незавършен артикул 'Дамски бикини...' · 3 мин назад.
 Да продължа от където беше? [Откажи = започни наново]"
```

Draft се изтрива:
- При успешен save
- 7 дни без update (стейл cleanup)
- Ръчно "Откажи"

### 21.2 Voice STT failure

> Закон №3: AI мълчи, PHP продължава.

Ако voice service е надолу (network error, Whisper rate limit):
- Toast "Voice временно недостъпен — въведи ръчно" (но НЕ native клавиатура — manual button → custom numpad)
- Wizard продължава работа

### 21.3 AI Vision failure

Ако Gemini не отговори / връща low-confidence:
- AI banner НЕ се показва
- AI полета НЕ се auto-fill
- Поведение като "Без снимка" → fallback към Section 3

### 21.4 Без доставна цена → AI markup hidden

Markup row се показва **САМО ако** има cost_price > 0. Без него — поле "Цена" остава празно, Пешо въвежда ръчно.

### 21.5 Multi-photo с грешен AI цвят

User може да:
- Edit color name (text input под thumb)
- Remove снимка (× върху thumb)
- Add нова снимка (+ в края на grid)

Section 2 (Вариации) chips се update-ват автоматично при промяна.

### 21.6 Барод съществува в DB (този tenant)

> Reference: `AI_AUTOFILL_SOURCE_OF_TRUTH.md` §141-149 (Ниво 1 barcode lookup)

Преди AI обаждане — barcode lookup в DB:
- Match → автоматично копира всичко от съществуващия артикул (€0, без AI)
- Match в друг tenant → копира нечувствителни полета (категория, цвят, описание, материал — НЕ цени, маржове, доставчик)

UI:
```
✓ Намерих артикула в базата — попълнено всичко.
  (Цени и доставчик са твои да определиш.)
```

---

## 22. EXAMPLE FLOW — ПЕШО В МАГАЗИНА

**Сценарий:** Пешо получава доставка от 5 модела бикини × 3 цвята × 4 размера = 60 артикула.

### Артикул #1 (full wizard, ~60 секунди):

1. Пешо tap "Добави артикул" в Simple home
2. Wizard се отваря с първи акордеон отворен
3. Mode toggle → "С вариации"
4. Photo toggle → "Различни цветове"
5. Пешо снима 3 цвята последователно → 3 thumbs в grid, AI разпозна "Бял 94%", "Розов 89%", "Черен 72%"
6. AI Vision Banner: "AI разпозна 8 полета · Цвят · Пол · Сезон · Марка · Описание · Категория · Подкатегория · Материал"
7. Поле **Име** — Пешо казва "Дамски бикини Tommy Jeans" → Web Speech → попълва
8. Поле **Цена** — AI markup row показва "AI предлага €27.99 ×2.5+.99 confidence 92%" → Пешо tap [✓ Прие]
9. **Категория** — AI confirm "Намерих 'Бикини' — закачам там? ДА/НЕ" → Пешо tap [✓ ДА]
10. **Пол** chips → Женско вече с purple ✨ → Пешо го оставя
11. **Сезон** chips → Лято вече с purple ✨ → Пешо го оставя
12. **Марка** input → "Tommy Jeans" вече попълнено ✨ → Пешо го оставя
13. **Кратко описание** textarea → AI попълни 32 думи → Пешо tap "Прие"
14. Section 2 (Вариации) акордеон → AI auto-filled 3 цвята chips → Пешо добавя размери "S, M, L, XL"
15. Matrix за бройки → Пешо tap "Всички = 5" → matrix се попълва
16. Tap [✓ Запиши] → 12 SKU създадени в DB

### Артикул #2-5 (bulk "Като предния", ~10 секунди всеки):

1. След save → bottom bar [+ Следващ ▾] → menu → "Като предния"
2. Wizard се отваря — bulk mode banner показва "Наследени: име, цена, доставчик, категория от 'Дамски бикини Tommy Jeans'"
3. Цена placeholder = "наследена €28" → Пешо дори не я пише
4. Пешо снима нов модел → AI разпознава цветовете → Section 2 chips автоматично
5. Пешо tap [✓ Запиши] → нов артикул със същата цена/доставчик/категория

**Total time:** 60 сек (Артикул 1) + 4 × 10 сек = **100 секунди за 5 модела × 60 SKU = 1.7 сек per SKU.**

---

## 23. ИМПЛЕМЕНТАЦИОННИ БЕЛЕЖКИ ЗА ДИЗАЙН ЧАТА

### 23.1 База за стартиране

Започни от `mockups/P13_bulk_entry.html` — той е canonical акордеонен mockup със:
- 5 акордеона (трябва да сведеш до 4 — слей секции МИНИМУМ + СНИМКИ → СНИМКА + ОСНОВНО)
- Glass card стилове
- Neon Glass dark mode CSS (sacred)
- Bottom bar + Next menu (запази)
- AI Result Overlay (запази)
- Search pill + Voice command bar (запази)
- Mode toggle (запази)

### 23.2 Готов mockup в repo

`mockups/wizard_v5_ai_vision_FINAL.html` — има първа версия от S145 chat-а. Дизайн чатът може да го отвори и да го доразвие/преправи, ако предпочита да започне от него.

### 23.3 Какво ТРЯБВА ДА СЪЗДАДЕШ

1. **Mockup с conditional rendering** — два state-а:
   - Файл 1: `mockups/wizard_v5_with_photo.html` — С снимка, AI разпознато всичко
   - Файл 2: `mockups/wizard_v5_no_photo.html` — Без снимка, AI полета в Section 3
2. **Mockup за bulk mode** — `mockups/wizard_v5_bulk_mode.html` — Active "Като предния" + наследени полета
3. **Mockup за multi-photo flow** — `mockups/wizard_v5_multi_photo.html` — Photo grid с 3 цвята + Section 2 auto-filled

### 23.4 Тестове за приемане

Дизайн чатът трябва да провери:
- ✅ 4 акордеона (НЕ 5, НЕ 3)
- ✅ Section 1 е отворен default
- ✅ Snimka mode toggle само при "С вариации"
- ✅ AI Vision Banner има conic-spin animation
- ✅ AI markup row е зелен (q-gain) при cost_price > 0
- ✅ "Като предния" pill е винаги видим в header
- ✅ Bulk mode banner се показва само ако режимът е активен
- ✅ Multi-photo grid с recognized colors
- ✅ Bulk bg removal бутон при multi mode
- ✅ В dark mode — neon glass conic-gradient border на акордеоните
- ✅ Montserrat font, DM Mono за labels/numbers
- ✅ НЕ emoji в UI (освен ✨ badges като изключение)
- ✅ НЕ native `<select>` (autocomplete input вместо това)

### 23.5 НЕ ПРАВИ

- Не пиши backend код (PHP / SQL / JS логика). Само HTML/CSS mockup.
- Не променяй sacred zones (Voice STT, Color detection, Bluetooth printer).
- Не предлагай нови UX patterns без да провериш в `DESIGN_SYSTEM_v4.0_BICHROMATIC.md`.
- Не сменяй структурата на 4-те акордеона без да питаш Тих.

---

## 24. СВЪРЗАНИ ФАЙЛОВЕ (FULL LIST)

### Mockup references
- `mockups/P13_bulk_entry.html` — canonical акордеонен mockup
- `mockups/P15_simple_FINAL.html` — Simple home (kp-pill, glass cards)
- `mockups/P11_detailed_mode.html` — Detailed Mode визуал
- `mockups/P12_matrix.html` — Matrix fullscreen overlay
- `mockups/P8b_advanced_clothes.html` — AI Studio advanced (per-category)
- `mockups/ai_studio_FINAL_v5.html` — AI Studio standalone
- `mockups/wizard_v5_ai_vision_FINAL.html` — S145 draft (отправна точка)

### Design system
- `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` — tokens, colors, typography
- `DESIGN_CANON_v1.md` — sacred design invariants

### Wizard / Products docs
- `docs/PRODUCTS_DESIGN_LOGIC.md` — products цялостен design
- `PRODUCTS_WIZARD_v4_SPEC.md` — старата 3-стъпкова logic
- `TOMORROW_WIZARD_REDESIGN.md` — DB колони добавени в S143 (gender/season/brand)
- `docs/AI_AUTOFILL_SOURCE_OF_TRUTH.md` — AI Vision flow (Gemini 2.5 Flash, JSON schema, ai_snapshots таблица)
- `docs/AI_AUTOFILL_RESEARCH_2026.md` — Deep research (икономика, model comparison)
- `docs/AI_STUDIO_LOGIC.md` — AI Studio спецификация

### Pricing
- `AUTO_PRICING_DESIGN_LOGIC.md` — AI markup formulas, confidence routing, cold start

### Architecture laws
- `docs/BIBLE_v3_0_CORE.md` §Закон №1 (Пешо не пише), §Закон №6 (Simple=signals)
- `MASTER_COMPASS.md` — Standing Rules (Rule #38: AI не създава категории)

### Code references (sacred zones — read only)
- `products.php` редове 7598-15050 — текущ wizard код (стара 4-sub-page архитектура)
- `services/voice-tier2.php` — Whisper Groq integration
- `services/ai-color-detect.php` — Color detection (multi-photo)
- `js/capacitor-printer.js` — Bluetooth printer

---

## 25. РЕЗЮМЕ — TL;DR (за бързо разбиране)

**Wizard "Добави артикул" = 4 акордеона:**
1. **Снимка + Основно** (open default) — слети снимка + основни полета + AI разпознати полета (conditional)
2. **Вариации** (само при "С вариации" toggle) — размери + цветове (AI auto-filled от multi-photo) + matrix
3. **Допълнителни** — fallback за AI полета (без снимка) + не-AI полета (цени, доставчик, материя)
4. **AI Studio** — линк навън (отделна страница) + 2 quick buttons (фон, SEO)

**Магията:**
- Снимка → AI попълва всичко наведнъж: категория, цвят, пол, сезон, марка, описание, материал
- За категория — explicit ДА/НЕ confirm (Rule #38)
- За другите — silent fill с ✨ AI badge
- Без снимка → AI полета мигрират към Section 3

**Цени:**
- AI markup auto-suggestion: cost × multiplier + ending (например ×2.5 + .99)
- Confidence routing: >0.85 auto, 0.5-0.85 confirm, <0.5 manual
- Per-category patterns се учат от 3 източника (onboarding, manual corrections, sales velocity)

**Bulk add:**
- "Като предния" pill в header (винаги видим)
- Bulk mode banner отдолу
- В bulk режим цена дори не се пише — наследява се

**Voice:**
- BG цена → Web Speech (instant)
- Non-BG/numbers → Whisper Groq
- Voice auto-advance: 2 сек silence + manual button (и двете работят)

**Multi-photo:**
- Toggle "Една снимка / Различни цветове" в Section 1
- AI разпознава всеки цвят (sacred ai-color-detect.php?multi=1)
- Section 2 chips се попълват автоматично
- Bulk bg removal бутон за всички снимки наведнъж

---

**END OF SPECIFICATION**

> Когато дизайн чатът създаде mockup-а, върни го на Тих за approval. След approval → S146 ще започне backend имплементацията (PHP + JS + DB migration за `ai_snapshots` таблица).
