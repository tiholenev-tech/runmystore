# 📘 SIMPLE_MODE_BIBLE.md

**Версия:** 1.3  
**Дата създаване:** 28.04.2026  
**Дата последна актуализация:** 29.04.2026 (5 add-ons от шеф-чат след анализ на v1.2)  
**Автор:** Тихол + Claude (синтез от 5 AI консултации + дълга вечерна сесия + v1.3 шеф-чат add-ons)  
**Статус:** Living document — попълва се с всеки нов модул  
**Свързани документи:** BIBLE_v3_0_TECH.md, BIBLE_v3_0_CORE.md, INVENTORY_v4.md, INVENTORY_HIDDEN_v3.md, TECHNICAL_REFERENCE_v1.md, MASTER_COMPASS.md, DESIGN_SYSTEM.md, ORDERS_DESIGN_LOGIC.md, PRODUCTS_DESIGN_LOGIC.md, ROADMAP.md

---

## 0. КАК СЕ ИЗПОЛЗВА ТОЗИ ДОКУМЕНТ

Този файл е **АВТОРИТАТИВЕН** за всичко свързано с Simple Mode и Dual Mode архитектура. Чете се от:
- Всеки нов чат който прави UI/UX за RunMyStore
- Преди писане на нови модули или промени в съществуващи
- Преди архитектурни решения свързани с дублиране на backend logic
- Преди промени в life-board.php или toggle между режими

Когато се добавя нов модул или ново решение — нова секция или подсекция се добавя. Open questions от раздел 11 се решават с Тихол и преместват в съответните секции.

---

## 1. ЗАКОН №9: DUAL MODE EVERYWHERE

### Основна философия

**Всеки модул в RunMyStore.ai работи в 2 пълни UI режима. Те делят SAME backend, SAME database, SAME business logic. Различава се само UI слоят — и това става чрез URL parameter `?mode=simple` или body class `mode-simple`, НЕ чрез отделни файлове.**

| Режим | За кого | Характеристика |
|---|---|---|
| **Simple Mode** | Пешо (non-technical продавач) | Минимум бутони, voice-first, нищо за писане, един екран — една задача |
| **Detailed Mode** | Митко (мениджър/собственик) | Пълна функционалност, таблици, графики, dropdowns, drill-down |

### Принципи

1. **Zero loss функционалност** — Simple НЕ е "ограничен" режим. Той върши същите 100% функции както Detailed, но с по-малко тапове и повече voice/photo/AI inference.

2. **Smart defaults** — Simple избира вместо Пешо където е възможно. Detailed дава контрол.

3. **Progressive disclosure** — Допълнителни опции зад "..." или voice command, не задръстват UI.

4. **Toggle from anywhere** — Бутон за смяна на режим винаги достъпен (детайли в секция 6).

5. **Единен backend** — UI режимите делят един и същ data model, едни и същи AJAX endpoints, едни и същи services. **Никакво дублиране на business logic.**

6. **Един codebase, два UI conditional renders** — НЕ две отделни приложения. Не `simple-sale.php` като отделен файл, а `sale.php` с `?mode=simple` или body class.

---

## 2. ДВЕТЕ ПЕРСОНИ

Тези персони са фундаментът на цялата архитектура. Всяко UI решение се проверява срещу: "работи ли за Пешо?" и "работи ли за Митко?".

### 👴 Пешо — Simple Mode user

- 55-годишен продавач в магазин за дрехи в малък български град
- Не пише на телефон (артрит, не познава клавиатурата)
- Не знае какво е "filter", "dashboard", "metric", "tab"
- Говори с диалект, греши правописа в SMS-и
- Иска да продаде → касира → отиде вкъщи. Това е всичко.
- Ако види повече от 4-5 бутона на екран — затваря приложението
- Доверява се на AI ако: AI казва числа + защо + какво да направя

**ВАЖНО:** Не правим Пешо на идиот. Той знае от кой доставчик взима, знае каква е категорията. Това което не може е да попълва форми с 16 полета на телефон с артрит. Затова му даваме **избор** — бърз режим за активни моменти, подробен режим когато има време.

### 👨‍💼 Митко — Detailed Mode user

- 35-годишен собственик на 2-5 магазина или manager
- Свободно работи на телефон и компютър
- Иска dashboards, таблици, графики, filters
- Интересуват го margins, retention, cohort analysis
- Може да чете 3 изречения текст. Иска drill-down.

### Какво НЕ правим

- Toggle "Beginner / Advanced" вътре в един модул
- Settings page с 30 опции "show this, hide that"
- "Wizard mode" първите 5 минути и after that пълен expert вид
- Един общ UI с условни елементи
- Отделни `simple-*.php` файлове паралелно на `*.php`

### Какво правим

- **Един и същ файл, два UI слоя.** `sale.php?mode=simple` показва опростен UI; `sale.php?mode=detailed` показва пълен. Backend identical.
- **Mode като body class** — `<body class="mode-simple">` или `<body class="mode-detailed">`. CSS правила скриват/пренареждат елементи.
- **Lesen и Detailed вкарване на артикул е ИЗБОР, не персонална характеристика.** И Пешо, и Митко имат двата режима. Пешо обикновено избира бърз. Митко обикновено избира подробен. Но и двамата имат двата.

---

## 3. АРХИТЕКТУРНИ ПРАВИЛА

### 3.1 Shared backend, separate views

**ЗАДЪЛЖИТЕЛНО:**

```
┌─────────────────────────────────────┐
│  sale.php (един файл, два render-а) │
│  Body class: mode-simple OR         │
│              mode-detailed           │
└──────────────────┬───────────────────┘
                   │
         ┌─────────┴─────────┐
         ▼                   ▼
   /api/sale.php       /api/products.php
   (REST endpoints)    (REST endpoints)
         │                   │
         └─────────┬─────────┘
                   ▼
         services/sale.php
         services/products.php
         (business logic)
                   │
                   ▼
                 ┌───┐
                 │DB │
                 └───┘
```

**Правила:**

1. **Един view файл** — `sale.php`, `products.php`, `delivery.php`, `orders.php` — всеки е ЕДИН файл с conditional rendering. НЕ две версии.
2. **Никакъв SQL в view файл.** Никаква бизнес логика. Само rendering и AJAX calls.
3. **`/api/*.php`** са REST-style endpoints. Един endpoint обслужва и Simple, и Detailed.
4. **`services/*.php`** съдържат бизнес правила. Един service = един източник на истината.
5. **DB queries** живеят в services, не в endpoints, не в views.
6. **CSS правила** използват body class за да скриват/пренареждат елементи: `.mode-simple .complex-panel { display:none }`.

### 3.2 Code review правила

PR се отхвърля ако съдържа:
- SQL заявка в view файл
- Дублирана бизнес логика
- Hardcoded стойности които трябва да са от database или config
- UI текст без `tenant.lang` lookup
- Hardcoded валута ("лв" / "BGN" / "€") вместо `priceFormat($amount, $tenant)`
- Споменаване на "Gemini" или други AI engine names в UI текст (винаги "AI")
- Нов `simple-*.php` файл паралелно на `*.php` (нарушение на Mode-as-Parameter архитектурата)
- Нова таблица за "draft" / "parking lot" продукти (нарушение на Confidence-Based Completion модел — виж секция 8.5)

### 3.3 user.preferred_mode — НЕ tenant.preferred_mode

**Критично за multi-user tenants.** Един магазин (tenant) има N служители (users). Митко-owner работи Detailed. Пешо-employee работи Simple. Същата DB, различни UI едновременно.

```sql
ALTER TABLE users ADD COLUMN preferred_mode ENUM('simple','detailed') DEFAULT 'detailed';
```

**Default = 'detailed'** за нови users — по-безопасно е Митко да попадне в Detailed, отколкото Пешо да се загуби в нея. Onboarding пита "Сам ли работиш?" и ако да → switch към 'simple'.

### 3.4 Tenant-level config

`tenants` таблицата запазва ниво глобални неща:
- `default_user_mode` — какво да е default за нов user в този магазин
- `simple_mode_enabled` — дали Simple Mode е активен за този tenant

### 3.5 Mode lock per employee (НОВО v1.3)

**Проблем:** в multi-user tenant служител може случайно да tap-не "→ Разширен" в life-board → попада в chat.php (Detailed) → започва произволни тапове в UI който не разбира → грешки в данни. Особено опасно за по-възрастни служители (Иван 50г) или нови наемници.

**Решение:** `users.mode_locked` колона позволява на owner-а да заключи режима на конкретен служител.

```sql
ALTER TABLE users 
  ADD COLUMN mode_locked TINYINT(1) DEFAULT 0 
  AFTER preferred_mode;
```

**Поведение:**
- `mode_locked=0` (default) — служителят вижда toggle бутон в life-board header, може свободно да сменя режим
- `mode_locked=1` — toggle бутонът **изчезва** (не disabled, просто го няма в DOM-а). Служителят е заключен в `preferred_mode`.

**UI в Detailed Mode (само за owner/manager):**
Settings → Служители → list. До всяко име: toggle "Заключен в [Simple/Detailed]" + dropdown за стартов режим.

**Permission rules:**
- Само `users.role='owner'` ИЛИ `users.role='manager'` могат да заключват/отключват служители
- Служителят НЕ може да заключи/отключи себе си (UI не показва опцията)
- Owner винаги има unlocked toggle (може да си смени по всяко време)
- Voice command "превключи режим" игнорира `mode_locked` САМО за owner. За служител с `mode_locked=1` voice отказва: "Нямаш разрешение да сменяш режим. Питай шефа."

**Use case (ENI магазин):**
- Митко (owner) → unlocked, toggle виден
- Иван (50г продавач) → `mode_locked=1`, preferred='simple' → винаги в life-board, никога chat.php
- Ани (25г, прави и справки) → `mode_locked=0`, preferred='simple' → има toggle, ползва Detailed когато трябва

---

## 4. LIFE-BOARD.PHP — Simple Mode home

### 4.1 Layout

5/5 AI единодушни с малки разлики в пиксели. **Този модул остава отделен файл** (life-board.php) — той е home на Simple Mode, няма Detailed еквивалент в същия файл. Detailed home е chat.php.

```
┌─────────────────────────────────┐
│  RunMyStore "Магазин" [Toggle]  │  ~10% header
│                                 │  Lean: brand + mode toggle
├─────────────────────────────────┤
│  🔴 Insight 1 (loss, q1)        │
│  🟢 Insight 2 (win, q3)         │  ~25% insights
│  🟡 Insight 3 (order, q5)       │  Max 3 visible
│  [↓ Виж още (4) →]              │  >3 → "Виж още" link
├─────────────────────────────────┤
│  ┌──────────┐  ┌──────────┐     │
│  │  💰      │  │  📦      │     │  ~45% grid 2×2
│  │ ПРОДАЙ   │  │ СТОКАТА  │     │  Buttons ~160×150px
│  └──────────┘  └──────────┘     │  Icon top, label bottom
│  ┌──────────┐  ┌──────────┐     │  Gap 16px
│  │  🚚      │  │  📋      │     │  .glass + .glow per q-color
│  │ ДОСТАВКА │  │ ПОРЪЧКА  │     │
│  └──────────┘  └──────────┘     │
├─────────────────────────────────┤
│         ┌─────────────┐         │  ~20% AI Brain
│         │ 🤖 AI Brain │         │  Pill-button (~80×44px)
│         └─────────────┘         │  Под 4-те
├─────────────────────────────────┤
│  (no bottom nav — hidden)       │  Safe area only
└─────────────────────────────────┘
```

### 4.2 Header

**Минимален.** Brand или име на магазин вляво. Toggle бутон вдясно. Няма settings gear, няма notification bell, няма store switcher (multi-store случаи = виж секция 11.2).

### 4.3 Insights секция

- **Max 3 видими.** Повече = paralysis. >3 → "Виж още (N) →" линк.
- **Приоритет на показване:** q1 (загуба) > q5 (поръчка) > q3 (печалба) > останалите. Loss aversion печели атеншън.
- **Dismissable** — swipe right премахва инсайт за 24 часа.
- **Mode-aware tone** — съдържанието на инсайта се различава в Simple и Detailed (виж секция 13).

### 4.4 4 operational buttons

| Бутон | Цел | Глагол |
|---|---|---|
| 💰 ПРОДАЙ | sale.php?mode=simple | "Продавам сега" |
| 📦 СТОКАТА | products.php?mode=simple | "Какво имам" |
| 🚚 ДОСТАВКА | delivery.php?mode=simple | "Получих стока" |
| 📋 ПОРЪЧКА | orders.php?mode=simple | "Трябва ми стока" |

**Размери:** ~160×150px. По-големите помагат за артрит и точност на тапа.

**Дизайн:** `.glass` + `.shine` + `.glow`. Цветовете на glow псевдо-елементите следват q-палитрата:
- Продай → q3 (зелен) — "печелиш"
- Стоката → qd (default neutral)
- Доставка → q5 (жълт) — "поръчка/доставка"
- Поръчка → q2 (лилав) — "от какво губиш" (поръчваш защото нямаш)

### 4.5 AI Brain pill — централен интерфейс на Пешо за разговор с AI (РАЗШИРЕНО v1.3)

#### 4.5.1 Какво е

AI Brain pill е малък бутон ПОД 4-те operational бутона в life-board. Това е **главният интерфейс на Пешо за разговор с AI** — където задава въпроси, получава предложения, делегира задачи.

**Не е просто voice recorder.** Не е "още един бутон". Това е сърцето на ЗАКОН №1 (Пешо не пише). Всичко което Митко прави с тапове, форми и dropdowns — Пешо прави през AI Brain с глас.

#### 4.5.2 Три поведения

**A. Reactive (Пешо стартира разговора):**
- Tap → отваря voice overlay (rec-ov, общ `partials/voice-overlay.php`) с червена пулсираща лампа
- Пешо говори ("колко продадох днес")
- AI отговаря с глас + кратък текст (1-2 изречения)
- Voice overlay затваря автоматично

**B. Proactive (AI има queue от items за разговор):**
- Когато AI има нерешени въпроси за Пешо → pill-ът добавя втори, по-ярък pulse (visual cue)
- Pulse rate: 1 pulse/2.5s (idle) → 2 pulses/2.5s (queue ≥1) → 3 pulses/2.5s (queue ≥3 escalation)
- Tap → AI говори първо БЕЗ Пешо да каже нищо: *"Имаш 3 неща за днес: 1) Marina ти достави 47 от 50 поръчани чорапа — провери какво липсва. 2) Nike 42 свърши — да поръчам ли 10 чифта? 3) 8 артикула чакат снимка."*
- Пешо може да каже:
  - "първото" / "второто" → AI отваря relevant модул с context
  - "пропусни" → понижи приоритет, не пита същия ден
  - "после" → snooze 2 часа (TTL reset)

**C. Conversational (Пешо вътре в модул, иска нещо извън flow-a):**
- Пешо е в `sale.php?mode=simple`, има 3 артикула в количката
- AI Brain pill виден винаги (mini-FAB долу-вдясно в Simple Mode модули)
- Tap → AI знае контекста: *"Сега правиш продажба. 3 артикула в количката за общо 75 евро. Какво искаш?"*
- Пешо: "сложи 10% отстъпка"
- AI прилага → cart показва 67.50 → НЕ напуска sale.php
- Пешо продължава продажбата

#### 4.5.3 Queue схема (за proactive поведение)

```sql
CREATE TABLE ai_brain_queue (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  insight_id INT NULL,                                  -- връзка с ai_insights ако е оттам
  type ENUM(
    'variation_reconcile',   -- "продаде N пъти X без вариации"
    'confidence_nudge',      -- "8 артикула чакат снимка"
    'reconciliation',        -- "Marina 47/50 чорапа"
    'stock_alert',           -- "Nike 42 свърши"
    'order_suggestion',      -- "поръчай 10 X"
    'review_check'           -- "записа 10 артикула с материя памук — потвърди"
  ) NOT NULL,
  priority TINYINT DEFAULT 50,                          -- 1-100, по-високо = първо
  message_text TEXT NOT NULL,                           -- какво AI говори
  action_data JSON NULL,                                -- какво се случва при "да"
  status ENUM('pending','snoozed','dismissed','done') DEFAULT 'pending',
  snooze_until DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ttl_hours INT DEFAULT 48,                             -- автоматично изтриване
  escalation_level TINYINT DEFAULT 0,                   -- 0/1/2 — повече pulses
  INDEX (tenant_id, user_id, status, priority)
);
```

**TTL правила:**
- Default 48 часа за повечето типове
- `stock_alert` → 24 часа (време-критично)
- `review_check` → 7 дни (не бърза, но не забрави)
- Ако `status='pending'` И `created_at + ttl_hours` минал → auto `status='dismissed'` (cron на 3:00)

**Escalation правила:**
- Item ignored 24ч → `escalation_level=1` (по-яркo pulse)
- Item ignored 48ч → `escalation_level=2` (3 pulses/sec) + копие в life-board insights (видим)
- 3+ items на `escalation_level=2` → AI прави stronger nudge: *"Имаш 4 неща които не сме обсъдили от 2 дни. Сега ли?"*

#### 4.5.4 НЕ дублира life-board insights

Двата feed-а са РАЗЛИЧНИ:

| Life-board insights (видими top секция) | AI Brain queue (зад pill-а) |
|---|---|
| Критични неща Пешо ТРЯБВА да види | Разговорни въпроси AI иска да обсъди |
| Max 3 видими | Неограничен брой (escalation handle) |
| Loss aversion priority (червени първо) | Priority по type + age |
| Tap → отваря модул | Tap pill → AI говори списък устно |
| Пример: "🔴 Nike 42 свърши" | Пример: "Вчера продаде 5 пъти бикини без цвят. 1 минута да ги уточним?" |

**Връзка:** insight може да генерира queue item (трябва разговор за решение). Queue item НЕ генерира insight (би било двойна notification).

#### 4.5.5 Защо ПОД 4-те бутона

Insights = primary attention (loss aversion печели). 4 бутона = primary actions. AI Brain = secondary — "ако искаш нещо различно от 4-те". Под = достъпен в thumb zone, но не доминира визуално.

В Simple Mode модули (sale.php, products.php и др.) → mini-FAB горе-вдясно (42×42px) или долу-вдясно (плаващ). Винаги достъпен.

### 4.6 Bottom nav

**СКРИТ ИЗЦЯЛО в Simple Mode.** 5/5 AI единодушни.

Достъп до Detailed = САМО през header toggle (секция 6).

Android hardware back от life-board = nothing happens (вече сме на home). Не затваря приложението случайно.

---

## 5. ОБЩ PATTERN ЗА МОДУЛИ В SIMPLE MODE

Всички модули в Simple Mode (sale.php, products.php, delivery.php, orders.php когато са с `?mode=simple` или body class `mode-simple`) следват ЕДИН и СЪЩИ pattern.

### 5.1 Header

```
┌─────────────────────────────────┐
│  [✕] Заглавие на модула  [🎤]   │  60-72px height
└─────────────────────────────────┘
```

- **[✕]** горе-вдясно = винаги връща в life-board (не browser back)
- **Заглавие** — голям читаем текст (Montserrat 800, ~22px)
- **[🎤]** горе-вдясно = бърз voice trigger за модула

### 5.2 БЕЗ bottom nav

В Simple Mode body class `mode-simple` → CSS hide на bottom nav.

### 5.3 БЕЗ toggle към Detailed вътре в модулите

Веднъж си в Simple, оставаш в Simple докато не се върнеш в life-board. Toggle живее само в life-board header (намалява accidental switches).

### 5.4 Close behavior

`[✕]` бутон → `history.replaceState` (не `history.back`) към life-board. Това гарантира че:
- При Android hardware back от life-board → не "размотава" обратно в модула
- При рестартиране на телефона по средата на flow → не се отваря наполовина-завършена операция

### 5.5 Confirmation при destructive close

Ако в момента на `[✕]` има uncommitted данни (продажба в процес, недовършена доставка):

```
┌─────────────────────────────────┐
│  Спри продажбата?               │
│                                 │
│  Имаш 3 артикула в количката.   │
│                                 │
│  [Продължи]    [Спри]           │
└─────────────────────────────────┘
```

### 5.6 Back-stack модел

**Max 2 нива дълбочина в Simple Mode:**

```
life-board (root, retained state)
    └── sale.php?mode=simple (level 1)
            └── confirm-screen / drawer (level 2 — modal layer, не page)
```

Drawer-и (edit overlays, confirms) са **modal layers**, не нови страници. Close на drawer = previous layer (стои на същата страница). Close на screen `[✕]` = home.

**Voice navigation:**
- "началото" → life-board.php
- "назад" → НЕ имплементирано (ambiguous — назад от къде?)

### 5.7 State preservation при превключване на режим (НОВО v1.3)

**Проблем:** Митко е в `sale.php?mode=detailed` с 5 артикула в количката, custom discount 13%, и split payment настройка. Tap → life-board → toggle към Simple → отваря `sale.php?mode=simple`. Какво става със state-а?

**Без правило:** или cart-ът пропада изцяло (frustrating UX), или всичко се запазва (но split payment в Simple няма UI → невидим bug).

**Решение:** localStorage TTL 30 минути с structured state per модул + matrix кои полета преживяват switch.

#### 5.7.1 Шаблон на localStorage ключ

```
runmystore_draft_${module}_${user_id}
```

Пример: `runmystore_draft_sale_5` за user_id=5 в sale.php.

**Стойност (JSON):**
```json
{
  "saved_at": "2026-04-29T14:23:15Z",
  "saved_mode": "detailed",
  "module": "sale",
  "ttl_minutes": 30,
  "state": { /* module-specific */ }
}
```

#### 5.7.2 Поведение при reopen

При load на модул в новия режим:
1. Прочети `runmystore_draft_${module}_${user_id}` от localStorage
2. Ако `saved_at + ttl_minutes` мина → изтрий ключа, нормален start
3. Ако валиден И `saved_mode !== current_mode` → adapt:
   - Полета които съществуват в двата режима → попълнени
   - Полета само в напуснатия режим → пропадат
   - Toast: "Някои настройки бяха премахнати при превключване на режим" (5 sec)
4. Ако валиден И `saved_mode === current_mode` → пълно възстановяване, no toast

#### 5.7.3 Matrix per модул

| Модул | Запазва се при switch | Пропада |
|---|---|---|
| `sale.php` | cart, customer, basic discount % (5/10/15/20) | split payment, manual price override, custom discount % (13.5 etc.) |
| `products.php` (wizard) | име, цена, категория, доставчик, материя | сложни вариации (matrix qty), copied-from-source ID |
| `delivery.php` | OCR resultati, основни bройки, supplier | счетоводни полета (ДДС, фактура №, плащане метод), reconciliation overlays |
| `orders.php` | артикули в cart, доставчик | priority overrides, manual ETA, custom note text |
| `inventory.php` | progress на броене, current zone | reconciliation overlays, expert filters |

#### 5.7.4 Cleanup правило

При успешен finish (продажба, save, etc.) → autoclear на ключа.

При explicit cancel ("Спри") → confirm dialog показва "Запазвам черновата 30 мин?" → ако Да → ключът остава, ако Не → cleanup веднага.

#### 5.7.5 Защо 30 минути TTL

- < 5 мин → твърде кратко, Пешо не успява да направи кафе
- 30 мин → разумен прозорец за прекъсване (телефон, клиент, тоалетна)
- > 1 час → state-ът става stale, цените може да са се променили, по-добре свеж старт

#### 5.7.6 Cross-device behavior

localStorage е **per device**. Ако Митко започне продажба на телефона и продължи на таблета → НЕ виждa чернова. Това е приемливо за v1 — multi-device draft sync = future feature.

---

## 6. TOGGLE Simple ↔ Detailed

### 6.1 Позициониране

**Header бутон вдясно** (4/5 AI препоръчват).

```
В life-board.php (Simple Mode):
┌─────────────────────────────────┐
│ RunMyStore "Магазин" [Разширен →]│
└─────────────────────────────────┘

В chat.php (Detailed Mode):
┌─────────────────────────────────┐
│ ← [Опростен]  RunMyStore   ⋮    │
└─────────────────────────────────┘
```

### 6.2 Текст

**Препоръка:** Стандартно "Опростен / Разширен" (4/5 AI препоръчват).

Алтернативи отхвърлени:
- Persona-naming "Митко 35m / Пешо 55m" — оригинално но рисковано: имената може да обидят, и не работят в RO/GR launch
- Иконка-only — лоша discoverability за Пешо

### 6.3 Превключване

При tap на toggle бутона:
- `users.preferred_mode` се updates в DB
- Redirect към съответния home (life-board.php за Simple, chat.php за Detailed)

### 6.4 Voice fallback

Voice command винаги работи отвсякъде:
- "Превключи на простия режим" / "опрости" → Simple Mode
- "Превключи на разширен" → Detailed Mode

### 6.5 Long press shortcut (Detailed only)

В Detailed Mode, long-press на AI таб в bottom nav = jump към Simple Mode (life-board). Power-user shortcut за Митко.

---

## 7. МОДУЛИ — DETAILS PER MODULE

### 7.1 sale.php (Simple Mode = minimal mode)

**Статус:** sale.php съществува (2867 реда, S87 предстои rewrite). Simple Mode = body class `mode-simple` + CSS hide на advanced функции.

**Принцип:** Самата продажба е 3 секунди. Тук няма какво да опростяваме повече от това което вече е минимално.

#### 7.1.1 Какво остава видимо в Simple Mode

- Voice input ("продай бикини черни L 30 евро")
- Camera barcode scan
- Numpad ръчно
- Quick-create при unknown barcode (виж секция 8)
- Cancel/refund (с PIN защита)
- Apply discount (само AI-suggested, без manual)
- Auto loyalty lookup (no UI)
- Park sale (паркирай за после)
- Касиране, печат на касов бон, готово

#### 7.1.2 Какво се крие в Simple Mode

- Manual price override (само Митко)
- Split payment cash+card (само Митко, освен ако клиент изрично помоли)
- Sale history view (Митко работа)
- Z-report end of day (Митко работа)
- Post-sale статистики панел (toggle setting)

#### 7.1.3 Post-sale поведение

В Simple Mode след продажба Пешо вижда:
```
✓ 30€
Готово
```
Връща се в sale.php готов за следващ клиент.

В Detailed Mode след продажба Митко може да види:
```
✓ Продажба #4521 · 30€
Маржин: 18€ (60%)
Дневен оборот: 340€
Това е 12-та продажба днес
[Виж детайли]
```

Toggle в settings: "показвай статистики след продажба".

#### 7.1.4 Variation handling по време на продажба

Виж секция 9 — Variation handling. Кратко: продажбата НИКОГА не блокира за вариации. Питане идва **след** клиентът да си тръгне (по INVENTORY_v4 секция 6.6).

#### 7.1.5 Quick-create при unknown продукт

Когато Пешо/Митко казва име на продукт което не е в базата — отваря се shared Quick Add (секция 8). Confidence стартира 20%. Продажбата минава с този продукт. След това следва логиката на скритата продажба (INVENTORY_v4 секция 6.6).

---

### 7.2 products.php (Simple Mode = Hybrid layout)

**Статус:** products.php съществува (11,393 реда). Simple Mode = body class `mode-simple` + Hybrid layout.

#### 7.2.1 Layout (Hybrid)

```
┌─────────────────────────────────┐
│  [✕] Стоката             [🎤]   │  60px
├─────────────────────────────────┤
│  🎤 Кажи: име, цвят, размер...   │  ~25-30% (~120px)
│                            🎤   │  Collapsible след action
│                                  │  Placeholder hint включва вариации
├─────────────────────────────────┤
│  ⚠️ 8 артикула чакат снимка  → │  Confidence nudges
│  ⚠️ 5 без цена едро          → │  (виж 7.2.4)
├─────────────────────────────────┤
│  [img] Nike Air Max 42   12 бр  │
│  [img] Adidas Samba 41    5 бр  │  ~70-75% list
│  ...                            │  Default sort: last_sold_at DESC
└─────────────────────────────────┘
```

#### 7.2.2 Списък

- **Колоните:** image (48×48px), име, бройка
- **Цена:** TBD (open question 11.1) — препоръка ДА, малка под името
- **Default sort:** Last sold/added DESC (най-актуалните първи)
- **Search:** voice-only. Микрофон бутон = voice search input ("покажи маратонките").

#### 7.2.3 Edit drawer

Tap на ред → slide-up drawer (НЕ нов екран):

```
┌─────────────────────────────────┐
│ [Списъкът blur-нат на background]
│                                 │
│ ┌─────────────────────────────┐ │
│ │ [✕]  Nike Air Max 42        │ │  ~60% от екрана
│ │      [📷 голяма снимка]    │ │
│ │                             │ │
│ │ Бройка: [-] 12 [+]          │ │  Stepper
│ │ Цена:   89.90 € [✏️]        │ │  Tap → numpad
│ │                             │ │
│ │ 🎤 "Промени каквото искаш"  │ │  Voice fallback
│ │                             │ │
│ │      [🗑 Изтрий]            │ │
│ └─────────────────────────────┘ │
└─────────────────────────────────┘
```

#### 7.2.4 Confidence nudges

Тихи signals в горната секция показват какво липсва:
- "8 артикула чакат снимка → " (тап → филтрирано до тези 8)
- "5 артикула без цена едро → "
- "12 артикула чакат броене → " (води към inventory.php zone walk)

Numbers идват от confidence_score query. Пешо ги довършва когато има време.

#### 7.2.5 Voice add — placeholder hint

Microphone input има placeholder hint: **"Кажи: име, цвят, размер"**.

Това **заменя** detection логиката (AI познава вариациите от категорията). UI hint > AI inference.

Ако Пешо каже "черни тениски XL 30 евро от Marina" → AI parse-ва име="тениски", цвят="черни", размер="XL", цена=30€, доставчик="Marina". Confidence по-висока, нямаме нужда от питане после.

Ако каже само "тениски" → работи както преди (variation discovery през следващата продажба или доставка). Никакъв проблем.

#### 7.2.6 Voice add poetapno flow

Когато Пешо казва voice command:

```
Пешо: "Добави червени маратонки 50 евро"
   ↓
AI: "Червени маратонки, 50 €. Колко бройки?"  (текст + глас)
   ↓
Пешо: "Десет"
   ↓
AI: "Размер?"  (САМО ако категорията е дрехи/обувки)
   ↓
Пешо: "42"
   ↓
AI: "Готово. 10 бр червени маратонки 42, 50 €."
```

**АКО Пешо каже всичко наведнъж** ("червени маратонки 50 евро 10 чифта размер 42") → AI създава директно без да пита. Smart parsing.

#### 7.2.7 Подробен режим за вкарване

Пешо може да избере "Подробно добавяне" → отваря пълния 7-стъпков wizard от products.php (но без bottom nav, с X close, в `mode=simple`). Това НЕ е блокирано — е избор.

AI може да препоръча в life-board: "Имаш 12 артикула добавени с минимална информация. Когато имаш 10 минути, отвори Подробно добавяне за пълно попълване." Препоръка, не задължение.

#### 7.2.8 "Копирай от предишен" (Detailed Mode wizard) — НОВО v1.2

В Detailed Mode wizard (products.php при пълно добавяне) има бутон **"📋 Копирай предишния"** на първа стъпка.

**Tap → отваря wizard-а с всичките 16 полета вече попълнени** от последно записания артикул на този tenant. Митко само променя това което е различно (име, цена, цвят) и записва.

**Workflow в реалност:**
- Митко записва "Тениска бяла L 30 евро от Marina, дамски дрехи, памук, България"
- Натиска "Копирай предишния" → отваря нов wizard
- Всичко е попълнено от предишния
- Той сменя само: name → "Тениска черна L"
- Натиска запис → готово за 5 секунди

Това превръща 7-стъпков wizard в 1-tap-edit-save.

**Какво СЕ копира от предишния (10 полета — ОБНОВЕНО v1.3):**
- Доставчик
- Категория, подкатегория
- Материя
- Произход
- Доставна цена
- Retail цена
- Цена едро
- has_variations флаг
- Preset от размери и цветове (само СТРУКТУРАТА, не бройките)
- **Снимка (copy by default + tap за смяна)** — НОВО v1.3, виж 7.2.8.5 по-долу

**Какво НЕ се копира (винаги празно в новия артикул):**
- `name` — задължително празно (Митко трябва да въведе ново)
- `code` (артикулен номер) — задължително празно (Митко scan-ва barcode който става code, или voice въвежда)
- `barcode` — задължително празно
- **Бройките** — нов продукт = 0 бройки до доставка/инвентаризация

**Какво "предишен" значи:**

3 опции, **препоръка: опция 1 + опция 3 паралелно:**

1. **Последно записан артикул** (default) — последният `INSERT INTO products` от този user. Бутонът "Копирай предишния" винаги взима този.
2. ~~Последно гледан артикул — отхвърлено (несъмнена UX)~~
3. **Митко избира от recent 10** — стрелка ⌄ до бутона "Копирай предишния" → отваря модал с recent 10 продукта, tap = template

**Технически детайл:**
Копираме данните в новия product **в момента на tap, не reference**. Snapshot, не reference. Това решава edge case-а "ако copy-source е изтрит или модифициран по-късно" — новият продукт е независим.

**Risk и митигация:**
Митко натиска "Копирай предишния" много пъти и забравя да смени някое поле → магазинът пълен с грешен материал. Митигация: AI прави post-hoc check "записа 10 артикула с материя 'памук' за последния месец. Сигурен ли си че всички са памучни?" — soft nudge, не блокираща.

**Отбележи: ТОВА Е САМО ЗА DETAILED MODE.** В Simple Mode (voice add през products.php?mode=simple) Пешо казва voice command и AI попълва — там няма "копирай предишния" защото няма wizard. Но AI може да помни последния supplier/категория в session като default за следващите гласови команди ("Marina пак?" — "да").

#### 7.2.8.5 Снимка — copy by default (НОВО v1.3, заменя старата логика "винаги празна")

**Решение:** Снимка се копира от source-product по default. Потребителят може да tap-не за смяна. Поведението е **еднакво в Simple и Detailed Mode** (dual-mode принцип: same UX, не отделни flows).

**Защо copy, не "винаги празна":**

Use case "Като предния" в реалност = бельо/дрехи магазин с десетки идентични продукти в различни цветове:
- Магазин с 25 пъти "същи бикини, различен цвят" → принудително празна снимка означава 25 пъти стоене с камерата → frustrating UX → Пешо/Митко спира да ползва "Копирай предишния" → губим стойността на функцията
- AI Studio (next step) генерира magic от base снимка → копираната служи като семплинг (background removal, color try-on), не като финален продукт-визуал
- "1 tap за смяна" е по-добро от "винаги задължителна нова снимка" — гъвкаво, но не насилствено

**Mode-aware confidence safeguard (компенсира risk-а):**

- Артикул с copied снимка стартира с `confidence_score` **−10 точки** vs ръчно качена → AI nudge в life-board
- Detailed Mode: dashboard показва "8 артикула с копирани снимки чакат потвърждение"
- Simple Mode: тих nudge "Имаш 8 артикула с копирани снимки. Когато имаш време → AI Studio за свежи."
- Bulk replace в AI Studio когато Митко има 5 минути

**UI поведение (еднакво в двата режима):**

- Photo-hero показва копираната снимка с overlay text "Tap за смяна" в долния ляв ъгъл
- Tap → camera/gallery picker (същото като нов wizard без template)
- Snapshot, не reference (копирана в момента на "Копирай предишния" tap)
- Source-product може да бъде променен или изтрит без affecting нов продукт

**Защо това НЕ нарушава dual-mode принципа:**

Поведението е идентично — Пешо и Митко виждат същата снимка, същия overlay, същия flow при смяна. Confidence safeguard работи в backend (един source of truth — `confidence_score`). UI се различава само по как се показва nudge-ът (life-board insight за Пешо vs dashboard ред за Митко) — но action-ът зад него е същият: bulk replace в AI Studio.

**Override към BIBLE 7.2.8 v1.2:** старата логика "снимка задължително празна" се отменя. Replaced by 7.2.8.5 v1.3.


---

### 7.3 delivery.php (Simple Mode = OCR camera-first + voice)

**Статус:** delivery.php не съществува още. Когато се създаде — `?mode=simple` от ден 1.

**Принцип:** В Simple Mode има 3 типа фактури, всеки със собствен flow.

#### 7.3.1 Трите типа фактури

| Тип фактура | Какво вижда системата | Simple Mode flow | Detailed Mode flow |
|---|---|---|---|
| **1. Чиста** (всичко описано до вариация) | OCR разчита всичко | Tap "Заприходи" — готово | Същото + management overlays |
| **2. Полу-чиста** (липсват вариации) | OCR вижда "10 бр бикини", AI вижда че продуктът е вариационен | AI пита: "Има ли цветове и размери? Кажи ги" — voice диктовка | Tabular form с редове за всяка вариация |
| **3. Ръкописна / без фактура / OCR fail** | Системата не може нищо | Voice диктовка на всичко: "Бикини 18, 23 броя; корсаж 41, 22 броя" — AI parse-ва | Manual tabular entry с клавиатура |

#### 7.3.2 Entry screen

```
┌─────────────────────────────────┐
│  [✕] Доставка             [🎤]  │
├─────────────────────────────────┤
│                                 │
│   ┌─────────────────────────┐   │
│   │   📷 СНИМАЙ ФАКТУРАТА   │   │  Primary CTA (200×120px)
│   │      (препоръчително)   │   │  q5 yellow glow
│   └─────────────────────────┘   │
│                                 │
│   ─── или ───                   │
│                                 │
│   [🎤 Кажи какво получи]        │  Secondary
│   [📦 Сканирай barcode]         │  Tertiary
└─────────────────────────────────┘
```

#### 7.3.3 OCR flow (типове 1 и 2)

1. Camera opens → snap → AI processing 3-5 sec (loading с neon glow)
2. Review screen с **3-цветна разпознаваемост:**
   - ✅ Зелено — match с product DB, confidence 100%
   - ⚠️ Жълто — нов продукт или unsure → tap = voice clarification
   - ❌ Червено — не разчете → manual edit
3. Зелените auto-pass. Жълтите/червените изискват tap.
4. [✓ Заприходи всичко] → inventory update + life-board insight

#### 7.3.4 Polu-чиста фактура mini-inventory

Когато OCR чете "10 бр бикини" и AI вижда че продуктът е вариационен:
- Автоматично се влиза в **mini-inventory mode** за тези 10 бройки
- Пешо разопакова кашона и диктува вариациите ("5 черни L, 3 черни M, 2 бели L")
- AI попълва вариациите в inventory таблицата

#### 7.3.5 Voice диктовка (тип 3 — без фактура)

Пешо в Simple Mode диктува както говори с приятел:
> "От Marina, 30 чифта чорапи, 20 черни и 10 бели, по 3 лева"

AI parse-ва, показва списък за потвърждение, готово.

#### 7.3.6 Detailed Mode добавки

Detailed Mode има СЪЩИЯ flow за всички 3 типа, но **добавя 3 management overlays:**

1. **Reconciliation срещу поръчка** — "Поръча 50 чорапа от Marina преди 2 седмици. Тя ти достави 47. Където са 3-те?"
2. **Cost variance check** — "Миналата доставка чорапите бяха 2.80 лв/чифт. Сега са 3.20. Marina вдигна цените."
3. **ДДС/счетоводство** — Митко прикача PDF на фактурата, попълва номер на фактура, дата за ДДС декларация, плащане кеш/банка

Ако Митко пропусне да попълни ДДС полетата → доставката пак минава, AI напомня след това. Zero-blocking philosophy.

#### 7.3.7 Reconciliation insight в Simple Mode (ВАЖНО — защита от измама)

**Reconciliation срещу поръчка трябва да е в Simple Mode също**, но като тих life-board insight:

> "Marina ти достави 47 от 50 поръчани чорапа. Провери дали всичко е тук, или липсва."

Не като форма за попълване, а като прост AI signал. Защо: ако Пешо в Simple Mode никога не види "Поръча 50, дойдоха 47" → той никога няма да забележи че Marina го мами с 3 чифта на всяка доставка. След година това са €500 загуба.

---

### 7.4 orders.php (Simple Mode = voice cart с задължителен доставчик)

**Статус:** Спецификация в ORDERS_DESIGN_LOGIC.md за Detailed Mode (готова, S83-S85). Simple Mode се добавя.

**Принцип:** Лесен режим има 2 фази на живот които работят паралелно — initial voice cart + mature 1-tap.

#### 7.4.1 Detailed Mode (от ORDERS_DESIGN_LOGIC.md)

Главната страница е **по доставчик** — карта за всеки доставчик с активна поръчка. Етапи: чернова → изпратена → частично получена → пълно получена → затворена. 12 входни точки за добавяне в поръчка. Артикулите групирани по 6-те фундаментални въпроси. AI отхвърля zombie артикули.

Пълната спецификация в ORDERS_DESIGN_LOGIC.md.

#### 7.4.2 Simple Mode — Phase Initial (първите 30+ дни)

AI няма достатъчно данни. Пешо сам си прави поръчките, AI само улеснява voice input.

```
┌─────────────────────────────────┐
│  [✕] Поръчка             [🎤]   │
├─────────────────────────────────┤
│                                  │
│   "От кой доставчик?"            │  ЗАДЪЛЖИТЕЛЕН в началото
│   🎤  или                        │
│   [Sport Group] [Marina] [...]  │
│                                  │
└─────────────────────────────────┘
```

**Защо задължителен доставчик в началото:** Несериозно е да се поръчва от 60 доставчика едновременно. Без supplier намерение, AI ще греши групирането.

След избор на доставчик:

```
┌─────────────────────────────────┐
│  [✕] Поръчка от Sport Group     │
├─────────────────────────────────┤
│  📦 Sport Group:                 │
│   • Nike 42 черни · 10 бр        │  Растящ cart
│   • Adidas Samba 41 · 5 бр       │
│                                  │
├─────────────────────────────────┤
│   🎤 [Кажи още или "готов съм"] │  Voice винаги visible
└─────────────────────────────────┘
```

**Voice flow:**
- AI пита еднократно "От кой доставчик?" — voice или tap от recent suppliers
- Пешо: "10 Nike 42 черни"
- Cart: добавя ред (растящ списък VISIBLE на екрана)
- Пешо: "и 5 Adidas Samba 41"
- Cart: добавя втори ред
- Пешо: "готов съм"
- AI генерира съобщение (Viber/email) към Sport Group → Пешо одобрява текста → send → готово

Ако Пешо иска да поръча от втори доставчик — **прави нова поръчка отначало** (нова сесия). Не комбинираме доставчици в една поръчка.

#### 7.4.3 Inventory check НЕ веднага (batch reconciliation)

При voice диктовка на поръчка, AI **НЕ пита веднага** "имаш ли вече от тези в магазина?". Поръчката минава бързо, без прекъсване.

Inventory проверката става в **удобен момент след това** (вечер, когато затишие, или на следващия ден като batch):

> "Вчера поръча 10 Nike 42 и 5 Adidas Samba 41 от Sport Group. Имаш ли от тях вече? Кажи бройките."

Това е същата логика като скритата продажба (INVENTORY_v4 секция 6.6) и скритата инвентаризация. Поръчката е 30 секунди, не 2 минути с прекъсвания.

#### 7.4.4 Simple Mode — Phase Mature (след 30+ дни, confidence >70%)

AI може да предложи поръчки от life-board insights — тогава `[Поръчай]` бутонът директно отваря `orders.php?mode=simple&prefill=...` с 1-tap confirm flow:

```
┌─────────────────────────────────┐
│  Поръчка от Sport Group         │
│                                  │
│  Nike Air Max 42                │
│  10 бр × 45€ = 450€             │
│                                  │
│  ✅ ПОРЪЧАЙ ВЕДНАГА             │
│                                  │
│  💡 +Добави още                 │  Collapsed link
└─────────────────────────────────┘
```

Двата фази (initial cart + mature 1-tap) **живеят паралелно** — Пешо може и сам да поръча с глас, дори когато AI вече предлага.

#### 7.4.5 3-те "background" states (изпратена, очаквана, получена)

В Simple Mode НЕ ги показваме като страници или табове. Те живеят в life-board като тихи insights:

- "Sport Group поръчката е от 5 дни — провери дали е дошла" (etap "изпратена")
- "Marina ти достави 47 от 50 поръчани чорапа — провери дали всичко е там" (etap "получена")
- "Имаш 2 неплатени фактури за общо 340€" (etap "затворена")

Tap → отваря съответния item directly, няма "tab navigation".

В Detailed Mode това са табове (Очаквани / Получени / Архив).

---

### 7.5 inventory.php

**Статус:** Спецификацията в INVENTORY_v4.md (V1: S60-S65 → актуализирано в roadmap S91-S93).

**Бележка:** inventory.php има отделен Simple flow (zone walk, quick add) описан в INVENTORY_v4. Не дублираме спецификацията тук. Simple/Detailed разлики са вече описани там.

---

## 8. QUICK ADD — INVENTORY ENTRY MODE

**КРИТИЧНА АРХИТЕКТУРНА КОРЕКЦИЯ:** Quick Add НЕ е отделен компонент. Той е **inventory entry mode**.

### 8.1 Защо

В реалността има само ЕДИН път за добавяне на артикул: **инвентаризация**. Всички "пътища" — доставка, sale unknown barcode, manual — са триггери на inventory flow:

- **Доставка непълна фактура** → mini-inventory mode за непокритите вариации
- **Sale unknown barcode** → quick-create + post-sale "имаш ли още?" (виж INVENTORY_v4 секция 6.6)
- **Manual ("+Добави нов" в products)** → същата quick-add форма като в inventory zone walk

### 8.2 Архитектура — hooks към inventory.php

```
sale.php (unknown barcode) ─┐
delivery.php (непълна фактура) ├─→ inventory.php?quick_add=1&prefill={...}
products.php "+ Добави нов" ─┘
```

**Eдинствен Quick Add UI** — този на inventory.php. Hooks го отварят с prefill данни.

### 8.3 Минимална единица за нов продукт

```
🎤 / клавиатура: бикини       ← име (задължително)
30 евро                        ← цена (задължително)
от Marina                      ← доставчик (по избор)
дамско бельо                   ← категория (AI познава от името)
```

**4 полета. Това е.** Всичко останало (вариации, материя, произход, баркод, снимка, цена едро, доставна цена) се попълва после чрез:
- Confidence-driven nudges в life-board ("8 артикула чакат снимка")
- Self-correcting loop при доставка/продажба
- Митко в Detailed Mode за batch попълване

### 8.4 Confidence стартова стойност по място на създаване

Виж TECHNICAL_REFERENCE_v1.md секция Confidence Модел. Тук обобщение:

| Място на създаване | Стартови данни | Confidence |
|---|---|---|
| sale.php quick-create (unknown barcode) | име + цена | **20%** |
| products.php voice add (Simple Mode) | име + цена + доставчик + категория | **50%** |
| inventory.php zone walk add | име + цена + физическо потвърждение | **60%** |
| products.php full wizard (Detailed Mode) | всичко | **80-100%** |

### 8.5 Confidence-Based Completion (НЯМА draft / parking lot таблица) — НОВО v1.2

**АРХИТЕКТУРНО РЕШЕНИЕ (за избягване на грешки в други чатове):**

Недовършените артикули **НЕ влизат в отделна таблица или статус "draft" / "pending" / "parking_lot".** Те са **нормални продукти** в `products` таблицата с **нисък `confidence_score`**.

#### 8.5.1 Защо НЕ отделна таблица

**3 причини против:**

1. **Не дублираме schema.** Една таблица `products`, едно поле `confidence_score`. Не `products_draft` + `products` + sync logic между тях.

2. **Артикулът работи веднага** — продава се, влиза в инвентара, появява се в search. Чака ли 16 полета попълнени за да съществува? Не. Ако съществува физически в магазина → съществува в системата.

3. **Self-correcting loop работи естествено.** При следваща продажба/доставка на същия артикул, AI попълва липсващи полета автоматично. Confidence се качва. Без user action, без "promote draft to product" логика.

#### 8.5.2 Как се работи с тях

- `products.confidence_score` (0-100%) — вече дефинирано в INVENTORY_v4 секция 18
- Confidence-driven nudges в life-board (Simple Mode): "8 артикула чакат снимка → ", "5 без цена едро → ", "12 чакат броене → "
- Tap на nudge → филтрира products списъка до тези артикули → batch попълване
- Митко в Detailed Mode има **completion dashboard** където вижда всички с low confidence и може да попълва накуп

**Никакви draft / paused / parking flags. Просто нисък confidence.**

#### 8.5.3 In-progress wizard edit case

Какво става ако Митко започне Detailed wizard, попълни 5 полета, прекъсне (телефонът звъни)?

**Решение от секция 12.6:** auto-save в localStorage на всеки 5-10 секунди. При reopen → "Продължи от където беше? [Да] [Не]". Ако каже Да → продължава wizard. Ако каже Не → localStorage се чисти, нищо не е записано в DB. **Wizard НЕ записва в `products` таблицата докато потребителят не натисне "Запази" финално.**

Това решава "in-progress" use case без нужда от draft статус.

#### 8.5.4 Code review правило

PR се отхвърля ако съдържа:
- Нова таблица `products_draft`, `products_pending`, `products_parking_lot`, или подобни
- Колона `products.is_draft`, `products.status='draft'`, или подобни
- Logic за "promote draft → published"

Артикулът или съществува (`products` row), или не съществува. Между тях — confidence_score определя how-completed-is-it. Нищо повече.

---

## 9. VARIATION HANDLING

### 9.1 Закон

**Никога не питаме "единичен или вариационен?" при създаване на продукт.**

### 9.2 Стратегии за откриване на вариации

**A. UI hint на microphone (primary):**
Microphone placeholder: **"Кажи: име, цвят, размер"**.

Ако Пешо каже "черни тениски XL" → AI parse-ва три полета и знае че продуктът е вариационен с цвят=черен, размер=XL. Confidence по-висока, нямаме нужда от питане после.

Ако каже само "тениски" → AI решава по категорията (от biz-coefficients.php):
- "дамско бельо" → has_variations='true'
- "хранителни" → has_variations='false'
- Ако AI не е сигурен → `has_variations='unknown'`, питаме при ПЪРВАТА продажба

**B. От доставка (OCR):**
Ако фактурата чете "Бикини модел 23 черни L × 5 бр" — AI веднага създава вариация без да пита.

**C. От продажба (semi-variation flow):**
Виж 9.3 по-долу.

### 9.3 При продажба на вариационен продукт без попълнени вариации

**Продажбата минава ВЕДНАГА (3 секунди, клиентът не чака.)** Това е железно правило (INVENTORY_v4 железно правило #1).

Нова DB колона за semi-variation продажби:

```sql
ALTER TABLE sale_items ADD COLUMN variation_description VARCHAR(100) NULL;
```

**Flow:**
- Пешо: "Продай бикини черни L 30 евро"
- Inventory.quantity намалява с 1 (от 20 на 19)
- `sale_items.variation_description = "черни L"` (текстов field)
- Клиент си тръгва

**След продажбата (тих чат бубъл, не блокира):**
> "Продаде Бикини 23. Кои? [черни L] [бели М] [Друг] [🎤]"

Бутоните показват вече използваните variations (от history). Пешо тапва или игнорира.

### 9.4 Batch reconciliation в края на ден

Ако Пешо игнорира тихия чат бубъл, в края на деня AI пита накуп:

> "Днес продаде 7 пъти бикини и 3 пъти чорапи без да каза цветове и размери. 2 минути да ги уточним?"

Тап → mini-screen с всички 10 продажби, всяка с бутончета [черни L] [бели М] [пропусни]. 30 секунди работа за цял ден.

### 9.5 Self-correcting при доставка

При следваща доставка на същия продукт от същия supplier, AI matches:

> "OCR казва получи 5 черни L. В магазина според мен са 8 черни L (10 - 2 продадени). Сигурен ли си?"

Пешо може да коригира предишни грешни variation_description-и. Self-correcting loop.

---

## 10. ОНБОРДИНГ — BEHAVIOR-BASED

**НЕ питай "Simple or Detailed?"** — Пешо ще си помисли че Simple = ограничен trial. Митко ще избере Simple от love-of-minimalism и после ще се ядоса.

**Питай behavior questions** в onboarding:
- "Сам ли работиш в магазина?" (да → Simple)
- "Колко обекта имаш?" (1 → Simple, 2+ → Detailed)
- "Колко често правиш справки?" (рядко → Simple, често → Detailed)

Behavior > Self-identification.

`users.preferred_mode` се записва на база отговорите.

---

## 11. OPEN QUESTIONS — ЗА РЕШЕНИЕ

### 11.1 Q3c: Цена в списъка на products.php (Simple Mode)?

**Контекст:** В списъка всеки ред показва image + име + бройка. Дали да има и цена?

**Опции:**
- A: Цена в списъка (4/5 AI препоръчват, малка под името)
- B: Без цена, само в edit drawer (Claude — "обърква продажна или доставна?")
- C: Цена САМО ако промо/намалено (hybrid)

**Препоръка:** A. Реалност: Пешо знае че списъкът показва продажна цена.

### 11.2 Multi-store в Simple Mode

Ако Пешо има 2+ магазина, как сменя магазин без да навлезе в Detailed?

**Препоръка:** В Simple Mode по default 1 активен store (последно отворен). Voice command "смени магазин на X" работи. Multi-store dashboard живее само в Detailed.

### 11.3 Кога правим shared Quick Add hook-овете?

**Опции:**
- A: СЕГА като част от Simple Mode v2 sprint (S90)
- B: ПОСЛЕ като отделен refactor sprint (S95)

**Препоръка:** TBD — Тихол решава в началото на S90.

### 11.4 Дизайн-полиране — empty/loading/error states (MUST за S90, ОБНОВЕНО v1.3)

**Статус v1.3:** Преместено от open question → **задължителен milestone преди ENI deploy (14 май 2026)**.

**Защо задължително, не оптционално:**
Ако Иван (служител) натисне бутон без empty state UI → нищо не става (loading) → той пак натисне → дублиран action → грешка в данните. Това е **demo-killer за ENI launch**.

**Phase 2 (S90) deploy gate:** без states pass → не deploy. Period.

#### 11.4.1 Empty states — задължителни за всеки модул

| Модул | State | Какво се показва |
|---|---|---|
| life-board | 0 insights (нов магазин) | Placeholder card: "AI се запознава с магазина. Започни като продадеш нещо или добавиш артикул." + 1 CTA "Добави артикул" |
| life-board | 0 queue items в AI Brain | Pill без extra pulse, tap → "Засега няма какво да обсъдим. Питай ме нещо." |
| products.php | 0 артикула | Голям + бутон "Добави първи артикул" + onboarding hint "Кажи какво продаваш или сканирай barcode" |
| sale.php | empty cart | Camera viewfinder + "Сканирай или кажи име" |
| orders.php | 0 recent suppliers | "Кой е първият ти доставчик?" voice prompt |
| orders.php Detailed | 0 история | AI prompt "Започни като поръчаш нещо" |
| inventory.php | няма zone walks | "Започни zone walk на коя категория?" voice select |
| delivery.php | няма приета доставка | "📷 Снимай фактурата" CTA |

#### 11.4.2 Loading states — задължителни

| Action | Loading UI |
|---|---|
| OCR processing | Neon glow spinner + "Чета фактурата…" (3-5 sec) |
| AI generating message за поръчка | Typing indicator (3 пулсиращи точки) + "AI пише…" |
| Voice transcription | Pulse на mic icon + "Слушам…" |
| AI response от chat | Pulse на AI Brain pill + scroll auto на text |
| DB query > 1 sec | Skeleton loader (бели кутии вместо реални данни) |
| Image upload | Progress bar 0-100% + "Качвам…" |

#### 11.4.3 Error states — задължителни

| Error | UI Response |
|---|---|
| Voice не се разчете | "Не разбрах. Опитай пак или натисни клавиатурата [Кажи пак] [Клавиатура]" |
| Network fail | Yellow border на екрана + toast "Нямам интернет, записвам в тефтера" (offline queue → S88) |
| AI timeout (>10s) | Fallback "Бавно е. Искаш ли да продължиш ръчно?" + [Чакай] [Ръчно] бутони |
| DB error (500) | Soft toast "Нещо се обърка. Опитай след малко." + автоматичен retry в 3 секунди |
| Camera permission denied | "Камерата е заключена. Иди в настройки." с deeplink към settings |
| BT printer disconnected | Inline button директно: "[🖨 Свържи принтера отново]" (виж 12.4) |

#### 11.4.4 Specifications за дизайнерски агент

Преди S90 deploy → специфичен prompt за Claude Code (или дизайн агент):
- HTML мокъпи на всички 6+5+6 = 17 states в `mockups/states/`
- Всеки state има: Simple версия + Detailed версия (когато се различават)
- Color palette per state: empty=qd neutral, loading=q-default indigo, error=q-loss red
- Animation specs: cardin entrance, pulse за loading, shake за error

#### 11.4.5 Acceptance criteria за S90 deploy

- ✅ 17/17 states имат UI implementation (не placeholder!)
- ✅ Тестване на real Пешо (30 мин) → 0 cases на "натисках без feedback"
- ✅ Тестване на real Митко (30 мин) → 0 cases на "не разбрах какво стана"
- ✅ Network throttle test (slow 3G) → всички loading states се появяват < 100ms
- ✅ Offline test → error states работят без crash


---

## 12. EDGE CASES & ERROR HANDLING

### 12.1 Auto-revert от Detailed (gentle, не force)

Ако Пешо случайно тапне "→ Разширен" и за 60-90 секунди няма interaction различна от scroll:

```
Toast: "Изглежда сложно? Искаш ли обратно в простия режим?"
[Да, върни ме] [Не, оставам]
```

**НЕ force-revert.** Само prompt. Ако Пешо ignore-нe → не питаме отново тази сесия.

### 12.2 Mode-aware AI prompts

Notifications/AI отговори се различават по тон в двата режима. Това НЕ е translation — е **различна content generation**.

| Detailed | Simple |
|---|---|
| "Sales: 340€ today. -12% vs last Tue. Top SKU: Nike 42 (3 units, 45% margin)." | "Днес продаде 340 евро. Това е добре." |
| "Stock alert: Nike Air Max 42 on zero. Avg sales 3/wk. ROP triggered." | "Nike 42-те свършиха. Поръчай?" |

AI prompt-ът чете `user.preferred_mode` и генерира съответно.

### 12.3 Offline режим

**Offline режимът НЕ е дефиниран в този документ.** Той има самостоятелна спецификация и dedicated sprint:

- **MASTER_COMPASS:** "S88: offline mode (IndexedDB queue)"
- **INVENTORY_v4 секция 15:** OFFLINE MODE (ЗАДЪЛЖИТЕЛНО V1) — броенето работи изцяло на телефона, записва локално, sync при reconnect
- **PRODUCTS_DESIGN_LOGIC секции 17.3-17.4:** Capacitor PWA offline — IndexedDB cache, queue for sync
- **RUNMYSTORE_AI_BRIEF Problem 10:** Offline intelligence
- **Tech stack:** PWA + localStorage queue (вече е в стека)

При имплементация на S88, offline ще обхваща и Simple Mode, и Detailed Mode. Този документ само препраща към съответните спецификации.

### 12.4 Bluetooth printer auto-recovery (inline)

DTM-5811 често прекъсва. В Simple Mode Пешо НЕ може да навигира до settings.

При print failure → голям inline бутон директно на screen-а:

```
[🖨 Свържи принтера отново]
```

Auto-pair логика in-place. Без redirect, без settings page.

Ползва съществуващата capacitor-printer.js логика (S82.CAPACITOR).

### 12.5 Fat-finger Undo тостове (5 секунди)

Всяко destructive action (изтриване продукт, поръчка >€100, отказ продажба) има 5-секунден Undo toast:

```
[✓ Изтрит] [Отмени]    ← 5 sec timer, после заявката отлита към DB
```

Защита от грешки с дебели пръсти. Особено важно в Simple Mode заради големите бутони.

### 12.6 Crash recovery / In-progress wizard

При crash по време на flow в `*.php?mode=simple` или Detailed wizard:
- Auto-save state на всеки 5-10 sec в localStorage
- При reopen → "Продължи от където беше?" [Да] [Не]
- Ако каже Да → продължава wizard / flow
- Ако каже Не → localStorage се чисти, нищо не е записано в DB

**Wizard НЕ записва в `products` таблицата докато потребителят не натисне "Запази" финално.** Това решава "in-progress" use case без draft статус (виж секция 8.5).

### 12.7 Voice protocol — STT confidence + диалект handling (НОВО v1.3)

**Реалност:** Web Speech API на български работи с ~70-85% accuracy за стандартен говор. За диалект (Пешо в Пирин или Странджа) → 50-65%. Това означава **1 от 3 voice команди може да се транскрибира грешно**. Voice-first без protocol = data corruption.

#### 12.7.1 Visual confirmation на transcript (задължително)

**Никога silent action на voice.** След speech-to-text → винаги показваме transcript-а ПРЕДИ AI parsing/action.

UI pattern (rec-ov overlay):
```
┌─────────────────────────────────┐
│  ● ЗАПИСВА (червена точка)      │  → recording
├─────────────────────────────────┤
│  ✓ ГОТОВО                       │  → done
│                                 │
│  "червени маратонки 50 евро    │  ← STT transcript visible
│   10 чифта 42 размер"           │
│                                 │
│  [Изпрати →]   [Кажи пак]       │
└─────────────────────────────────┘
```

Пешо чете transcript. Ако правилно → "Изпрати →". Грешно → "Кажи пак" → ново recording.

**Без confirmation step → directly to AI** е забранено. Дори в conversational AI Brain (4.5.2 C) — кратък glimpse на transcript преди execute (1.5 sec, дисapptearing toast).

#### 12.7.2 Synonyms таблица (per tenant lang)

```sql
CREATE TABLE voice_synonyms (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NULL,                 -- NULL = global (за всички tenant-и в lang)
  lang CHAR(2) NOT NULL,              -- 'bg', 'ro', 'gr'
  synonym VARCHAR(50) NOT NULL,
  canonical VARCHAR(50) NOT NULL,
  category VARCHAR(50) NULL,          -- "категория" / "цвят" / "размер" / NULL
  usage_count INT DEFAULT 0,
  created_by ENUM('seed','user_correction','ai_learned') DEFAULT 'seed',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (tenant_id, lang, synonym)
);
```

**Seed данни за BG (примери):**
| synonym | canonical | category |
|---|---|---|
| къси гащета | шорти | категория |
| панталони къси | шорти | категория |
| тениска без ръкави | потник | категория |
| мариса | Marina | доставчик |
| мариска | Marina | доставчик |
| кафяв | кафяво | цвят |
| сини | синьо | цвят |
| бирки | размер | (passthrough) |

**Workflow:**
1. STT връща transcript ("къси гащета черни L 30 евро")
2. AI пасва transcript през `voice_synonyms` за `tenant_id=current OR NULL` AND `lang=tenant.lang` → ("шорти черни L 30 евро")
3. Canonical text → AI parsing с по-висока accuracy
4. `usage_count++` на matched synonyms (за learning)

**User correction loop:**
Ако Пешо казва "къси гащета", confirms transcript, но крайният артикул е classified като "панталони" → след save AI пита "Това шорти ли е?" → ако да → INSERT в voice_synonyms ("къси гащета" → "шорти", `created_by='user_correction'`). Self-improving.

#### 12.7.3 Confidence routing (LAW №8 интеграция)

AI parsing на canonical text връща confidence 0-1 per поле:
- `name_confidence`, `qty_confidence`, `price_confidence`, `category_confidence`

**Aggregate confidence = min(all per-field confidences)**

Router decision (LAW №8):
| Aggregate confidence | Action |
|---|---|
| > 0.85 | Auto execute. Toast "✓ Готово, добавих 10 шорти 30€" |
| 0.5 - 0.85 | Confirm dialog: "Това ли искаш: 10 шорти черни L 30€? [Да] [Поправи]" |
| < 0.5 | Reject + voice retry: "Не разбрах добре. Опитай пак или напиши." |

**Edge case:** total confidence < 0.3 → не само reject, но и suggest type-in fallback ("Опитай пак или [📝 Напиши]").

#### 12.7.4 Никога silent failure

Voice command никога не пропада тихо. Винаги има feedback:
- ✅ "Готово, добавих 10 шорти" (toast 2 sec)
- ⚠️ "Това не разбрах" (toast 3 sec + retry button)
- ❌ "Грешка, не мога сега. Интернет?" (toast 5 sec + manual fallback)
- 🔇 Silent → BUG (трябва да flag-ваме в diagnostic Cat A)

#### 12.7.5 Диалект strategies

**A. Crowdsourced learning (auto):**
voice_synonyms растe от user corrections. След 100 продажби в магазин → AI има добра база за този tenant.

**B. Pre-seeded регионални пакети:**
При onboarding пита "Откъде си? (за по-добро разбиране)" → optional dialect pack injection (Пирин / Странджа / Родопи / София-стандарт).

**C. Confidence escalation:**
Ако voice confidence < 0.5 в 5+ поредни команди от един user → AI предлага: "Изглежда не те разбирам добре днес. Кажи бавно и кратко, или избирай от менютата."

#### 12.7.6 Не-voice fallback винаги достъпен

Дори с perfect voice → AI Brain pill винаги има малка [📝] икона до микрофона за typing fallback. Ако ден когато micro is broken / Пешо има грип → той пак може да работи.

Това е **single-point-of-failure protection.** Voice е primary, не only.

---

## 13. NOTIFICATIONS (mode-aware)

### Backend

Една notification се генерира с base data (severity, action_link, key_facts). Frontend форматира различно за Simple vs Detailed.

### Detailed format

> "Sales alert: Daily revenue 340€ (-12% vs prev Tue). Top SKU: Nike Air Max 42 (3 units, 45% margin). Action: Review pricing strategy."

Tap → отваря съответния модул (chat, sale, etc.).

### Simple format

> "Днес продаде 340 евро. Това е по-малко от обикновено."

Tap → отваря life-board с highlight на съответния insight (не нов модул — Пешо не разбира "отвори products.php").

---

## 14. TESTING & QA

### 14.1 Тестове на API ниво

Тества се **services + API**, не UI. UI може да се сменя, API е contract.

### 14.2 Persona testing

Преди deploy на ново Simple Mode поведение в нов модул:
- 30 минути с реален Пешо (Тихол го наблюдава, не помага)
- Метрики: time-to-task, accidental taps, abandoned flows
- Threshold: ако accidental tap rate > 10% → връщаме за дизайн revision

### 14.3 Confidence regression tests

При промяна на confidence formula или decay → тест на dataset от seed_data.sql, проверява че нито един product не пада под минимума за неговото "вярно" confidence ниво.

---

## 15. MIGRATION PATH

### От текущ life-board.php към Simple Mode v2

**Phase 1 (current, 28.04.2026):**
- life-board.php има 4 бутона които водят към `/sale.php`, `/products.php` (full Detailed versions)
- Bottom nav виден
- Toggle частично в chat.php header
- НЯМА `?mode=simple` parameter handling

**Phase 2 (S90 — Simple Mode foundation):**
- DB миграция: `users.preferred_mode`, `sale_items.variation_description` (виж секция 16)
- Скриваме bottom nav в life-board (1 line CSS промяна)
- Добавяме toggle в life-board header (стандартно "→ Разширен")
- 4-те бутона в life-board сочат към `*.php?mode=simple`
- CSS body class `mode-simple` пакет — hide bottom nav, show X close, hide complex panels
- Тестване с реален Пешо

**Phase 3 (S91 — sale.php Simple polish):**
- sale.php в `mode=simple` body class
- Hide manual price override, split payment, history, Z-report
- Show simplified post-sale screen (3 sec confirm)
- Quick-create при unknown barcode → hook към inventory.php (виж 8.2)

**Phase 4 (S92 — products.php Hybrid + "Копирай предишния"):**
- products.php в `mode=simple` body class
- Hybrid layout: 25-30% chat / 70-75% list
- Microphone placeholder "Кажи: име, цвят, размер"
- Confidence nudges секция
- **Detailed Mode wizard добавя бутон "📋 Копирай предишния"** (секция 7.2.8)

**Phase 5 (S93-S94 — orders.php Simple + delivery.php):**
- orders.php Simple: задължителен доставчик в началото, voice cart, 2 фази
- delivery.php Simple: 3 типа фактура flow

**Phase 6 (S95 — Quick Add hooks refactor):**
- Cleanup: премахване на дублиран quick-add код (ако има)
- Всички 3 entry points (sale, delivery, products) → единна inventory quick-add UI

**Phase 7 (S96 — Onboarding behavior-based):**
- Onboarding wizard с behavior questions
- `users.preferred_mode` active default logic

---

## 16. DB SCHEMA CHANGES

### 16.1 Нови колони (за Phase 2 S90 — ОБНОВЕНО v1.3)

```sql
-- Mode preference per user (multi-user tenant support) — v1.1
ALTER TABLE users 
  ADD COLUMN preferred_mode ENUM('simple','detailed') 
  DEFAULT 'detailed' 
  AFTER role;

-- Mode lock — owner може да заключи служител в режим (v1.3, секция 3.5)
ALTER TABLE users 
  ADD COLUMN mode_locked TINYINT(1) DEFAULT 0 
  AFTER preferred_mode;

-- Tenant-level config — v1.1
ALTER TABLE tenants 
  ADD COLUMN default_user_mode ENUM('simple','detailed') 
  DEFAULT 'detailed';

ALTER TABLE tenants 
  ADD COLUMN simple_mode_enabled TINYINT(1) 
  DEFAULT 1;

-- Variation description for semi-variation sales (sale_items) — v1.1
ALTER TABLE sale_items 
  ADD COLUMN variation_description VARCHAR(100) NULL 
  AFTER quantity;

-- AI Brain queue (proactive AI items за Simple Mode user) — v1.3, секция 4.5.3
CREATE TABLE IF NOT EXISTS ai_brain_queue (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  insight_id INT NULL,
  type ENUM('variation_reconcile','confidence_nudge','reconciliation','stock_alert','order_suggestion','review_check') NOT NULL,
  priority TINYINT DEFAULT 50,
  message_text TEXT NOT NULL,
  action_data JSON NULL,
  status ENUM('pending','snoozed','dismissed','done') DEFAULT 'pending',
  snooze_until DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ttl_hours INT DEFAULT 48,
  escalation_level TINYINT DEFAULT 0,
  INDEX (tenant_id, user_id, status, priority),
  INDEX (created_at, ttl_hours)
);

-- Voice synonyms (per tenant lang, dialect support) — v1.3, секция 12.7.2
CREATE TABLE IF NOT EXISTS voice_synonyms (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NULL,
  lang CHAR(2) NOT NULL,
  synonym VARCHAR(50) NOT NULL,
  canonical VARCHAR(50) NOT NULL,
  category VARCHAR(50) NULL,
  usage_count INT DEFAULT 0,
  created_by ENUM('seed','user_correction','ai_learned') DEFAULT 'seed',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (tenant_id, lang, synonym)
);
```

### 16.2 Препратки към съществуващи таблици

Тези вече са дефинирани в други документи и НЕ се пишат тук:

- `products.confidence_score`, `products.last_counted_at`, `products.counted_via` → INVENTORY_v4 секция 18
- `store_zones`, `zone_stock`, `inventory_count_sessions`, `inventory_count_lines`, `inventory_events` → INVENTORY_v4 секция 18
- `supplier_orders`, `supplier_order_items`, `supplier_order_events` → ORDERS_DESIGN_LOGIC секция 15 + BIBLE_v3_0_APPENDIX §8.5
- `tenant_insight_queue`, `insight_templates`, `insight_display_log`, `insight_state` → Life Board файлове 13, 14
- `lost_demand_log` → Life Board файлове + ORDERS_DESIGN_LOGIC

### 16.3 НЯМА нови draft / parking lot таблици (НОВО v1.2)

**Изрично архитектурно решение** (виж секция 8.5): НЕ създаваме нова таблица за недовършени артикули. Ползваме `products.confidence_score` (вече дефинирана).

Бъдещи чатове които предлагат `products_draft`, `products_pending` или подобни таблици — отхвърляме PR-а. Code review правило в секция 3.2.

### 16.4 Migration script (за S90)

При започване на Phase 2 (S90), Claude ще:
1. Изпълни `SHOW TABLES` и `DESCRIBE` на production DB за всяка от секциите 16.1 + 16.2
2. Идентифицира кои колони и таблици вече съществуват, кои липсват
3. Генерира `s90_migration.sql` с ТОЧНО което трябва да се добави
4. Тихол одобрява → изпълнение в production

---

## 17. APPENDIX

### 17.1 Цветова система (q-палитра)

| Hue | Цвят | Семантика |
|---|---|---|
| q1 | Червен | Загуба, критично |
| q2 | Лилав | Причина за загуба |
| q3 | Зелен | Печалба |
| q4 | Син | Причина за печалба |
| q5 | Жълт | Поръчай |
| q6 | Сив | НЕ поръчвай |
| qd | Default neutral | Информационни |

### 17.2 Размери (mobile 380px viewport)

| Елемент | Размер |
|---|---|
| Header | 60-72px |
| 4 buttons grid | ~160×150px each |
| AI Brain pill | ~80×44px |
| Insights cards | full width × 80-120px |
| Edit drawer | 60% screen height |
| Voice overlay | full screen z-index 999 |
| X close button | ~44×44px hit area минимум |

### 17.3 Шрифтове

- Montserrat 400 — body text
- Montserrat 700 — labels
- Montserrat 800-900 — headers, buttons
- Tabular numerals (`font-variant-numeric: tabular-nums`) за всички цифри

### 17.4 Свързани файлове

- DESIGN_SYSTEM.md — visual tokens, glass classes, components
- card_light_neon.html — light theme reference
- warehouse.html — dark theme reference
- INVENTORY_v4.md — confidence модел, zone walk, скрита продажба, offline
- INVENTORY_HIDDEN_v3.md — original hidden inventory philosophy
- TECHNICAL_REFERENCE_v1.md — confidence detail
- BIBLE_v3_0_TECH.md — overall tech architecture
- ORDERS_DESIGN_LOGIC.md — orders.php Detailed Mode пълна спецификация
- PRODUCTS_DESIGN_LOGIC.md — products.php pattern
- MASTER_COMPASS.md — decision log + sprint plan
- ROADMAP.md — phased rollout S78-S100

### 17.5 Неоменими правила

Тези правила идват от user_memories и BIBLE — НЕ се променят:

1. **i18n:** Всеки UI текст през `tenant.lang ($lang)`. НЕ хардкоднам български.
2. **Валута:** `priceFormat($amount, $tenant)`. Bulgaria EUR от 1.1.2026. Dual display (€+лв rate 1.95583) до 8.8.2026.
3. **AI brand:** В UI винаги "AI", никога "Gemini", "ChatGPT", "Claude".
4. **Voice-first:** Пешо НЕ ПИШЕ. Гласово, photo, single tap. Numpad е изключение за цифри.
5. **DB колони:** `products.code` (не sku), `products.retail_price` (не sell_price), `inventory.quantity` (не qty), `sales.status='canceled'` (едно L), `sales.total` (не total_amount), `sale_items.unit_price` (не price). Винаги `DB::run()` / `DB::get()` — никога raw `$pdo`.
6. **Glass design:** `.glass` + `.shine` + `.glow` псевдо-елементи. Не solid backgrounds, не flat cards.
7. **Mobile-first:** Default viewport 380px. Desktop е bonus.

---

## 18. VERSION HISTORY

| Версия | Дата | Промени | Автор |
|---|---|---|---|
| 1.0 | 28.04.2026 | Initial document. Synthesizes 5 AI consultations. ЗАКОН №9 + life-board.php + general simple-* pattern + variation handling + Quick Add concept. | Тихол + Claude |
| 1.1 | 28.04.2026 (вечерна сесия) | **ОГРОМНА АРХИТЕКТУРНА КОРЕКЦИЯ:** Премахната концепцията за отделни `simple-*.php` файлове — заменена с `?mode=simple` URL parameter / body class. Quick Add е inventory entry mode (hooks), не компонент. Един единствен path за нов артикул = инвентаризация. Variation handling рефиниран (microphone placeholder hint, sale_items.variation_description, batch reconciliation). Доставка — 3 типа фактури. Поръчка — 2 фази (initial cart + mature 1-tap), задължителен доставчик в началото, batch inventory check, 3 background states като life-board insights. Продажба — minimal mode не отделен файл. Marina reconciliation insight в Simple Mode. 4 bonus идеи добавени (BT recovery, Undo toasts, user.preferred_mode, offline reference към S88). Migration path обновен (Phases 1-7). DB schema changes секция добавена. | Тихол + Claude |
| 1.2 | 28.04.2026 (late-night корекции) | **3 НОВИ ЛОГИКИ:** (1) "📋 Копирай предишния" в Detailed wizard — секция 7.2.8 нова. Копира 9 полета от последен артикул; name/code/barcode/снимка/бройки винаги празни. Опция 1 (последен) + опция 3 (recent 10 модал). (2) **Confidence-Based Completion** — секция 8.5 нова. НЯМА draft / parking lot таблица. Артикули с непълни данни живеят в `products` с нисък confidence_score. Code review правило срещу нови _draft таблици в секция 3.2. (3) Дизайн-полиране (empty/loading/error states) добавено като open question 11.4. Phase 4 от migration обновена с "Копирай предишния". DB schema 16.3 нов параграф потвърждаваща NO draft tables. | Тихол + Claude |
| 1.3 | 29.04.2026 (шеф-чат анализ + add-ons) | **5 ADD-ONS след шеф-чат критика на v1.2:** (1) **Mode lock per employee** (3.5 нова) — `users.mode_locked` колона, owner може да заключи служител в режим. Решава multi-user tenant safety. (2) **AI Brain pill разширена дефиниция** (4.5 пълно rewrite) — 3 поведения (reactive/proactive/conversational), `ai_brain_queue` нова таблица с TTL и escalation, не дублира life-board insights. Превръща AI Brain в централен интерфейс на Пешо за разговор с AI, не просто voice recorder. (3) **State preservation на mode switch** (5.7 нова) — localStorage TTL 30 мин + matrix per модул кои полета преживяват switch. (4) **Снимка copy by default** (7.2.8.5 нова, заменя старото правило "винаги празна") — copy от source-product + tap за смяна, confidence -10 safeguard, същото поведение в двата режима (dual-mode принцип). (5) **Voice protocol** (12.7 нова) — visual transcript confirmation задължителен, voice_synonyms таблица per tenant lang за диалект, confidence routing (LAW №8), никога silent failure. **Други промени:** Empty/loading/error states (11.4) преместени от open question в MUST за S90 deploy gate. DB schema (16.1) добавени `users.mode_locked`, `ai_brain_queue`, `voice_synonyms`. | Тихол + Claude (шеф-чат) |

---

## 19. КРИТИЧНА БЕЛЕЖКА (моерна критика на самия документ)

### 60% положителни

- 5 AI консултации дадоха strong consensus на 6/9 въпроса.
- Confidence моделът от INVENTORY_v4 елегантно се интегрира с философията "продуктът никога не е готов".
- **Архитектурната корекция от v1.1 (no separate simple-* files) елиминира огромен dual-product-drift риск.** Това е най-важното от вечерната сесия.
- **v1.2 потвърждение на Confidence-Based Completion** (NO draft table) предотвратява schema bloat. Code review правило защитава решението от регрес в бъдещи чатове.
- **"Копирай предишния" в Detailed wizard** е стандартен retail pattern — простa и силна функция за Митко при batch добавяне на similar products.
- Variation handling logic е practical и съответства на BG retail реалност.
- Microphone placeholder hint "Кажи: име, цвят, размер" замества AI inference логика — по-просто, по-точно, по-голяма агентност за Пешо.
- Single path за нов артикул (= inventory) драматично опростява всичко.
- Lesen vs Detailed като ИЗБОР, не персонална характеристика — корекция от Тихол която прави модела по-човечен.

### 40% критики

- **Consensus illusion риск:** 5 AI се съгласиха на 2×2 grid единодушно, но никой не предизвика "защо НЕ 1×4 vertical стак?". Vertical би дал full-width бутони с по-голяма hit area. Този frame не беше тестван. **Действие:** в Phase 2 deployment пробваме 2×2 С реален Пешо. Ако accidental tap rate > 10%, тестваме vertical.
- **`?mode=simple` като body class** изглежда елегантно, но има реален риск: ако `sale.php` става "и двата режима с условни render-и", файлът ще става все по-сложен с conditions (`if mode==simple show this, else show that`). След 6 месеца имаш 5000-редов файл с 30 conditions и никой не знае какво се показва кога. **Митигация:** Conditions само на UI ниво, не на logic ниво. CSS class-и >> JS conditions. PHP rendering identical.
- **"Копирай предишния" risk** (v1.2): Митко натиска многократно и забравя да смени поле — магазин пълен с грешен материал/категория. Mitigация: AI post-hoc check (секция 7.2.8). Но реално Митко може да ignore и тази проверка. Решение: confidence на копираните полета може да стартира малко по-ниско от пълно ръчно попълнен (например 75% вместо 90%) → AI nudges за verification.
- **Confidence-Based Completion** (v1.2) е елегантна архитектурно, но: ако никога не покажем на user "тези 50 артикула са недовършени" → никой няма да ги допълни. Решение: completion dashboard в Detailed Mode (Митко) + nudges в life-board (Пешо). И двете вече са в документа, но трябва да се имплементират правилно.
- **Behavior-based onboarding** звучи добре, но няма proof. Никой AI не цитира study.
- **Variation inference от biz-coefficients** работи за обувки/дрехи, но има категории edge cases (например "аксесоари" — могат да са с цветове или без). Първоначална UX тестване с реални категории е задължително.
- **Скритата inventory check след voice диктовка на поръчка** е елегантно, но: ако Пешо ignore-ва batch reconciliation prompt-а 10 пъти подред — той никога няма да попълни вариациите. Решение: confidence-driven escalation от INVENTORY_v4 секция 16 (visual escalation, не блокираща).
- **Marina reconciliation insight** е добра идея за защита от измама — но реално Пешо може да ignore-ва insight-а 10 поредни пъти. Решение: confidence escalation, или "този insight се повтаря 5 пъти — да говорим ли за Marina?"
- **Този документ е големия — но не е всичко.** Реалните решения за пиксели, animation timings, exact placement на бутони ще идват от mockups + iteration с реален Пешо. (виж open question 11.4)
- **Аз пиша този документ преди модулите да съществуват в Simple Mode.** Реалните edge cases ще се открият когато започнем да кодираме. Документът ще има v1.3, v2.0.

### Финал

Този документ е **компас, не карта**. Дава посока, не маршрут.

Не приемай нищо тук сляпо. Всяка секция трябва да премине проверката "работи ли за Пешо в стрес?" преди да отиде в production.

---

## 20. САМОПРОВЕРКА (ЧЕК-ЛИСТ срещу решенията)

### 20.1 Решения от вечерната сесия (v1.1)

| # | Решение | В документа |
|---|---|---|
| 1 | No separate simple-*.php — ?mode=simple URL param | ✅ Секции 1.6, 3.1, 5, всички 7.x |
| 2 | Quick Add = inventory entry mode (hooks, не компонент) | ✅ Секция 8 цялата |
| 3 | Single path за нов артикул = inventory (всичко друго е тригер) | ✅ Секция 8.1 |
| 4a | Microphone placeholder "Кажи: име, цвят, размер" | ✅ Секции 7.2.5, 9.2 |
| 4b | sale_items.variation_description VARCHAR(100) | ✅ Секции 9.3, 16.1 |
| 4c | Variation при продажба не блокира — питаме след | ✅ Секции 7.1.4, 9.3 |
| 4d | Batch reconciliation в края на ден | ✅ Секция 9.4 |
| 5 | Lesen vs Detailed = ИЗБОР, не персонална характеристика | ✅ Секция 2 ("Какво правим"), 7.2.7 |
| 6 | Минимална единица = 4 полета | ✅ Секция 8.3 |
| 7 | Confidence стартова стойност таблица | ✅ Секция 8.4 |
| 8 | Доставка — 3 типа фактури | ✅ Секция 7.3.1 |
| 9 | Detailed delivery + 3 management overlays | ✅ Секция 7.3.6 |
| 10 | Поръчка — 2 фази | ✅ Секции 7.4.2, 7.4.4 |
| 11 | Поръчка задължителен доставчик в началото | ✅ Секция 7.4.2 |
| 12 | Поръчка inventory check НЕ веднага (batch) | ✅ Секция 7.4.3 |
| 13 | Поръчка 3 background states = life-board insights | ✅ Секция 7.4.5 |
| 14 | Продажба = sale.php в minimal mode | ✅ Секции 7.1.1, 7.1.2 |
| 15a | Bluetooth printer auto-recovery inline | ✅ Секция 12.4 |
| 15b | Undo тостове 5 секунди | ✅ Секция 12.5 |
| 15c | user.preferred_mode на user level | ✅ Секции 3.3, 16.1 |
| 15d | Offline режим препратка към S88 | ✅ Секция 12.3 |
| 16 | Migration path обновени фази | ✅ Секция 15 (Phases 1-7) |
| 17 | Marina reconciliation insight в Simple | ✅ Секция 7.3.7 |

### 20.2 Нови решения v1.2

| # | Решение | В документа |
|---|---|---|
| L1 | "Копирай предишния" в Detailed wizard | ✅ Секция 7.2.8 нова |
| L1a | 9 копирани полета (supplier, category, материя, и т.н.) | ✅ Секция 7.2.8 списък |
| L1b | 5 НЕ-копирани (name, code, barcode, снимка, бройки) | ✅ Секция 7.2.8 списък |
| L1c | Опция 1 + Опция 3 (recent 10 модал) | ✅ Секция 7.2.8 |
| L1d | Snapshot, не reference (момент на tap) | ✅ Секция 7.2.8 технически детайл |
| L1e | Phase 4 от migration обновена | ✅ Секция 15 Phase 4 |
| L2 | Confidence-Based Completion (NO draft table) | ✅ Секция 8.5 нова |
| L2a | 3 причини против отделна таблица | ✅ Секция 8.5.1 |
| L2b | In-progress wizard = auto-save в localStorage | ✅ Секции 8.5.3, 12.6 |
| L2c | Code review правило срещу _draft таблици | ✅ Секции 3.2, 16.3 |
| L3 | Дизайн-полиране като open question | ✅ Секция 11.4 нова |
| L3a | Empty states (4 случая) | ✅ Секция 11.4 |
| L3b | Loading states (3 случая) | ✅ Секция 11.4 |
| L3c | Error states (3 случая) | ✅ Секция 11.4 |
| L3d | Цветове на header per module — TBD | ✅ Секция 11.4 |
| L3e | Анимации преходи — TBD | ✅ Секция 11.4 |

### 20.3 Нови решения v1.3 (шеф-чат add-ons, 29.04.2026)

| # | Решение | В документа |
|---|---|---|
| V1 | Mode lock per employee (`users.mode_locked`) | ✅ Секция 3.5 нова + 16.1 update |
| V1a | Owner/manager UI в Settings → Служители | ✅ Секция 3.5 |
| V1b | Voice override блокиран за locked служител | ✅ Секция 3.5 |
| V2 | AI Brain pill = централен интерфейс (не voice recorder) | ✅ Секция 4.5 пълно rewrite |
| V2a | 3 поведения: reactive / proactive / conversational | ✅ Секция 4.5.2 |
| V2b | `ai_brain_queue` таблица + TTL + escalation | ✅ Секция 4.5.3 + 16.1 |
| V2c | НЕ дублира life-board insights (matrix) | ✅ Секция 4.5.4 |
| V3 | State preservation на mode switch | ✅ Секция 5.7 нова |
| V3a | localStorage TTL 30 мин с structured state | ✅ Секция 5.7.1 |
| V3b | Matrix per модул (запазва се vs пропада) | ✅ Секция 5.7.3 |
| V4 | Снимка copy by default (не "винаги празна") | ✅ Секция 7.2.8.5 нова |
| V4a | Confidence -10 safeguard за copied снимки | ✅ Секция 7.2.8.5 |
| V4b | Same UX в двата режима (dual-mode принцип) | ✅ Секция 7.2.8.5 |
| V5 | Voice protocol (STT confidence, диалект, synonyms) | ✅ Секция 12.7 нова |
| V5a | Visual transcript confirmation задължителен | ✅ Секция 12.7.1 |
| V5b | `voice_synonyms` таблица per tenant lang | ✅ Секция 12.7.2 + 16.1 |
| V5c | Confidence routing (LAW №8) > 0.85 / 0.5-0.85 / < 0.5 | ✅ Секция 12.7.3 |
| V5d | Никога silent failure | ✅ Секция 12.7.4 |
| V6 | Empty/loading/error states = MUST за S90 (не оптционално) | ✅ Секция 11.4 пълно rewrite |
| V6a | Acceptance criteria за S90 deploy gate | ✅ Секция 11.4.5 |

### 20.4 Самопроверка срещу неoменими правила (секция 17.5)

- ✅ i18n: `tenant.lang ($lang)` споменато в секция 3.2 (rule), 17.5
- ✅ Валута: `priceFormat($amount, $tenant)` споменато в секция 3.2, 17.5
- ✅ AI brand: "AI" не "Gemini" — секция 3.2, 17.5
- ✅ Voice-first: секции 4.5, 7.2.5, 7.4.2, 12.7, 17.5
- ✅ DB колони правилни: `sale_items.variation_description`, `users.mode_locked`, `ai_brain_queue.*`, `voice_synonyms.*` в секция 16; `sales.status='canceled'` едно L споменато в 17.5
- ✅ Glass design: секции 4.4, 17.5
- ✅ Mobile-first: 380px viewport — секция 17.2

**Документ е пълен. Готов за качване в проекта.**

---

**КРАЙ НА SIMPLE_MODE_BIBLE.md v1.3**
