# 📘 SIMPLE_MODE_NEW_v1.md
## Нова визия за Лесен режим — AI-разговор-driven home + модули

**Версия:** 1.0  
**Дата:** 16 май 2026  
**Автор:** Тихол + Claude (S147)  
**Заменя:** SIMPLE_MODE_BIBLE.md v1.3 (29.04.2026) — частично (виж §1.2)  
**Статус:** APPROVED — готов за имплементация в S148+

---

## 0. КАК СЕ ИЗПОЛЗВА ТОЗИ ДОКУМЕНТ

Този документ е **главната визия** за Лесен режим (`?mode=simple`) във всеки чат на проекта от S148 нататък. Прочита се преди да се пише код за който и да е от 5-те главни екрана (home, products, deliveries, orders, inventory) в Лесен режим.

**Не променя:** chat.php (Detailed home), sale.php (изключение), backend services, DB business logic. Те остават както в production към 16.05.2026.

**Спецификацията тук побеждава** при противоречие с предишни документи (включително SIMPLE_MODE_BIBLE v1.3).

---

## 1. КОНТЕКСТ — ЗАЩО НОВА ВЕРСИЯ

### 1.1 Какво беше

SIMPLE_MODE_BIBLE v1.3 (29.04.2026) дефинираше Simple home като:
- **life-board.php** = таблица със сигнали (max 3 видими lb-card-а)
- Под тях — 4 ops бутона
- Под тях — AI Brain pill (малък бутон с 3 поведения)
- Сигналите се тапват → expand → action бутони
- AI Brain pill свети → tap → voice overlay

### 1.2 Защо се променя

Тихол (16.05.2026) идентифицира **3 проблема** със сегашния модел:

1. **Прекалено таблично** — Пешо вижда списък със сигнали, трябва САМ да тапне за да види какво има. Той не е такъв тип потребител. Той иска някой да му каже.

2. **Може да изпусне нещо** — таблицата показва max 3, останалите са скрити зад "Виж още". Пешо не скролва — пропуска ги.

3. **Не е достатъчно различно от конкурентите** — Square, Lightspeed, Shopify правят dashboards. Същата метафора. Никаква диференциация.

### 1.3 Какво се променя

**Главна метафора:** *dashboard със сигнали → активен AI разговор с Пешо*

AI говори първи: *"Здрасти Пешо. Имам 8 неща за днес. Искаш ли да чуеш?"*. Пешо отговаря (глас или тап). AI обхожда всеки сигнал ОТДЕЛНО с inline action бутони. Прогрес "1 от 8" показва къде е.

**5-те главни екрана** в Лесен режим получават същия pattern. Под-страниците остават нормален UI.

### 1.4 Какво НЕ се променя

- **Закон №9 Dual Mode Everywhere** — двата режима, един codebase, `?mode=simple`
- **Двете персони** (Пешо / Митко)
- **Sacred Neon Glass §5.4** — визуалният canon
- **Voice protocol §12.7** — STT + confirmation + synonyms
- **Confidence Routing (LAW №8)** — >0.85 / 0.5-0.85 / <0.5
- **ai_brain_queue схема** — с минимални разширения
- **chat.php** — остава Detailed home, нищо не пипаме
- **sale.php** — остава изключение, без AI разговор, неприкосновено

---

## 2. ЗАКОН №9: DUAL MODE EVERYWHERE (запазен от v1.3)

### 2.1 Принципи

Всеки модул в RunMyStore.ai работи в 2 пълни UI режима. Те делят SAME backend, SAME database, SAME business logic. Различава се само UI слоят — чрез URL parameter `?mode=simple` или body class `mode-simple`, **НЕ чрез отделни файлове**.

| Режим | За кого | Характеристика |
|---|---|---|
| **Simple Mode** | Пешо (non-technical продавач) | AI води разговор. Voice + 2-4 големи бутона. Едно нещо наведнъж. |
| **Detailed Mode** | Митко (мениджър/собственик) | Dashboards, таблици, графики, drill-down. Свободно навигиране. |

### 2.2 Принципи (запазени 1:1)

1. **Zero loss функционалност** — Simple НЕ е "ограничен" режим. Той върши същите 100% функции както Detailed, но през AI разговор вместо UI controls.
2. **Smart defaults** — Simple избира вместо Пешо където е възможно.
3. **Progressive disclosure** — Допълнителни опции зад voice command или "Виж всички" бутон.
4. **Toggle from anywhere** — Бутон за смяна на режим винаги в header (виж §9).
5. **Единен backend** — UI режимите делят един и същ data model.
6. **Един codebase, два UI renders** — `life-board.php` и `chat.php` са изключения (отделни home файлове). Всички модули са един файл с conditional rendering.

---

## 3. ДВЕТЕ ПЕРСОНИ (запазени от v1.3)

### 👴 Пешо — Simple Mode user

- 55-годишен продавач в магазин за дрехи в малък български град
- Не пише на телефон (артрит, не познава клавиатурата)
- Не знае какво е "filter", "dashboard", "metric", "tab"
- Говори с диалект, греши правописа в SMS-и
- Иска да продаде → касира → отиде вкъщи. Това е всичко.
- Ако види повече от 4-5 бутона на екран → затваря приложението
- Доверява се на AI ако: AI казва числа + защо + какво да направя

### 👨‍💼 Митко — Detailed Mode user

- 35-годишен собственик на 2-5 магазина или manager
- Свободно работи на телефон и компютър
- Иска dashboards, таблици, графики, filters
- Интересуват го margins, retention, cohort analysis

### 🆕 Иван — нов sub-persona (ENI beta tester)

- 50-годишен продавач, **voice-only** (mode_locked=1, виж §12)
- Не е технически грамотен, но е готов да опита
- Първият реален потребител на новата визия (ENI магазин)
- **Тест критерий:** ако Иван разбира интерфейса за 5 минути без обяснение → визията работи

---

## 4. ФУНДАМЕНТАЛНА ПРОМЯНА — AI РАЗГОВОР Е ЦЕНТРАЛЕН

### 4.1 Старият модел (SIMPLE_MODE_BIBLE v1.3)

```
Пешо отваря life-board → вижда 3 сигнала → тапва един →
expand → вижда action бутони → тапва Поръчай → AI поръчва
```

**Проблеми:**
- Пешо инициира всичко (reactive)
- Може да изпусне сигнал извън видимите 3
- Никаква водеща ръка
- "Dashboard с tap" — нищо ново

### 4.2 Новият модел (от 16.05.2026)

```
Пешо отваря life-board → вижда AI bubble "Имам 8 неща. Искаш ли?" →
Тапва "Да" → AI говори първото: "Nike 42 свърши, губиш €32/ден.
Поръчай ли 10 от Спорт Груп?" + 3 бутона inline →
Пешо тапва "Поръчай" → AI: "Готово. Следващо: ..." → 2 от 8 → ...
```

**Защо това е по-добро:**

| Стар проблем | Решение в нов модел |
|---|---|
| "Плаши Пешо" | 1 нещо на екран. Не списък. |
| "Може да изпусне" | AI обхожда ВСИЧКИ. Progress "1 от 8". Никога не пропуска. |
| "Иска всичко което вижда Митко" | Същият `ai_brain_queue` за двата. Митко вижда таблица, Пешо чува разговор. |
| "Активно подавам" | AI задава въпроса, Пешо само "да/не". |
| "Не претрупваме" | Един въпрос → един отговор → следващ. |
| "Voice-only е risky (STT грешки)" | Voice + бутони ВИНАГИ паралелно. |

### 4.3 Метафора

**Старо:** магазинът е dashboard. Пешо го гледа.  
**Ново:** магазинът е приятел. Пешо разговаря с него.

---

## 5. ГЛАВНА СТРАНИЦА — LIFE-BOARD.PHP (НОВО LAYOUT)

### 5.1 Какво се запазва от сегашната версия

- ✅ Aurora background (3 blobs)
- ✅ `rms-header` (форма B — brand + PRO badge + 4 icons)
- ✅ `rms-subbar` (store toggle + Начало + Разширен link)
- ✅ `s82-dash` — Оборот днес с period pills
- ✅ `ops-section` — 4 op-btn (с info бутончета)
- ✅ `studio-row` — AI Studio
- ✅ `wfc` — Weather forecast card (7 дни)
- ✅ `chat-input-bar` (sticky bottom) — за **свободен** chat
- ✅ Sacred Neon Glass §5.4 — `.glass` + 2× `.shine` + 2× `.glow` spans

### 5.2 Какво се МАХА

- ❌ `help-card` (qhelp с 6 chips "Какво ми тежи / Кои са топ продавачите...")
- ❌ `lb-header` "Life Board · 8 неща"
- ❌ `lb-card` loop (collapsible signal cards)
- ❌ `see-more-mini`
- ❌ AI Brain pill като отделен бутон (поведенията се преместват в chat dialog-а — виж §7)

### 5.3 Какво се ДОБАВЯ

**Един активен Sacred Neon Glass card** на мястото на премахнатите елементи:

```html
<div class="glass ai-chat-card qhelp">
  <span class="shine"></span>
  <span class="shine shine-bottom"></span>
  <span class="glow"></span>
  <span class="glow glow-bottom"></span>
  
  <!-- Header -->
  <div class="ai-chat-head">
    <div class="ai-chat-head-orb">[brain icon]</div>
    <div class="ai-chat-head-text">
      <div class="ai-chat-title">AI разговор</div>
      <div class="ai-chat-sub">Здрасти Пешо · 8:02</div>
    </div>
    <span class="ai-chat-count">8 неща</span>
  </div>
  
  <!-- Active conversation thread -->
  <div class="ai-chat-thread">
    <div class="ai-bubble">
      Имам <strong>8 неща</strong> за теб днес. Искаш ли да ти кажа кое е най-важно?
    </div>
    <div class="ai-actions">
      <button class="ai-action primary">Да, кажи</button>
      <button class="ai-action">После</button>
    </div>
  </div>
</div>
```

### 5.4 Финален layout на life-board.php (Simple home)

```
┌──────────────────────────────────────┐
│ rms-header — RunMyStore.ai [PRO]    │  56px sticky
│         🖨 ⚙ 🚪 🌙                    │
├──────────────────────────────────────┤
│ Цариградско  Начало  Разширен →     │  subbar 40px sticky
├──────────────────────────────────────┤
│ ┌──────────────────────────────────┐ │
│ │ Днес · Цариградско          +12%│ │  s82-dash (Оборот)
│ │ 847 EUR                         │ │
│ │ 7 продажби · vs 4 вчера         │ │
│ │ [Днес][7д][30д][365д] | [Об][Пе]│ │
│ └──────────────────────────────────┘ │
├──────────────────────────────────────┤
│ ┌────────────┐ ┌────────────┐        │
│ │   💰      │ │   📦      │        │  ops grid 2×2
│ │  Продай    │ │  Стоката   │        │  ~160×150px each
│ └────────────┘ └────────────┘        │
│ ┌────────────┐ ┌────────────┐        │
│ │   🚚      │ │   📋      │        │
│ │ Доставка   │ │ Поръчка    │        │
│ └────────────┘ └────────────┘        │
├──────────────────────────────────────┤
│ ┌──────────────────────────────────┐ │
│ │ ⭐ AI Studio    8 чакат  [8]    │ │  studio-row
│ └──────────────────────────────────┘ │
├──────────────────────────────────────┤
│ ┌──────────────────────────────────┐ │
│ │ ☀ Прогноза  AI препоръки         │ │  weather (q4)
│ │ [Днес][Пет][Съб][Нед][Пон][Вт]   │ │
│ └──────────────────────────────────┘ │
├──────────────────────────────────────┤
│ ┌──────────────────────────────────┐ │
│ │ 🧠 AI разговор   8 неща         │ │
│ │ Здрасти Пешо · 8:02              │ │  ★ НОВО ★
│ │ ┌──────────────────────────────┐ │ │  ai-chat-card (qhelp)
│ │ │ Имам 8 неща. Искаш ли?      │ │ │  активен разговор
│ │ └──────────────────────────────┘ │ │
│ │ [Да, кажи]  [После]              │ │
│ └──────────────────────────────────┘ │
├──────────────────────────────────────┤
│ 🎤 Питай нещо или диктувай...   ●● │  chat-input-bar (sticky)
└──────────────────────────────────────┘
```

### 5.5 Реда на елементите — обоснована

Тихол потвърди (16.05.2026): *"чат прозорец трябва да е най-отгоре винаги, бутоните под него"*. Но в life-board има специфика — top-stat (Оборот днес) е едно число което Митко/Пешо искат веднага да виждат. Затова:

1. **s82-dash** най-отгоре — едно число (Оборот днес), важно за всеки.
2. **ops grid** — основните 4 действия (Пешо може директно).
3. **AI Studio + weather** — секционни widgets (полезни но не критични).
4. **AI chat card** — финалната секция в `<main>`, но именно тя е "сърцето" на разговорната визия.
5. **chat-input-bar** — sticky bottom (свободен chat).

**Алтернатива (за обсъждане в S148):** Преместване на chat-card НАД s82-dash — да е първото нещо което Пешо вижда. Open question §17.

---

## 6. МОДУЛИТЕ В ЛЕСЕН РЕЖИМ

### 6.1 Универсален pattern

Всеки от 4-те модула (без `sale.php`) получава същата структура когато се отвори с `?mode=simple`:

```
┌──────────────────────────────────────┐
│ [←] {Модул}     Разширен →           │  header (форма B)
├──────────────────────────────────────┤
│ ┌──────────────────────────────────┐ │
│ │ 🧠 AI разговор за {модула}      │ │
│ │ Имам N неща за... искаш ли?      │ │  AI chat card
│ │ [Да, кажи]  [После]              │ │  (НАЙ-ОТГОРЕ)
│ └──────────────────────────────────┘ │
├──────────────────────────────────────┤
│ 🔍 Търси {в модула}...               │  search bar
├──────────────────────────────────────┤
│ [+ Главно действие на модула]        │  1-2 основни бутона
├──────────────────────────────────────┤
│ Скрити пари / Сегашни {неща}         │
│ ⚠ N {nudge type 1}           →      │  nudges (от
│ ⚠ N {nudge type 2}           →      │  confidence_score
│ ⚠ N {nudge type 3}           →      │  или статус)
├──────────────────────────────────────┤
│ 🎤 Или кажи нещо за {модула}...     │  voice bar
└──────────────────────────────────────┘
```

**Реда:**
1. **Chat най-отгоре** — Тихол правило (16.05.2026): "чат прозореца трябва да е най-отгоре винаги"
2. **Под него** — основните бутони които винаги ти трябват (search, добави нов, главно действие)
3. **Долу** — nudges/scrited пари (статус-базирани предложения)
4. **Sticky bottom** — voice bar за свободен chat

### 6.2 ПРОДАЙ (sale.php) — ИЗКЛЮЧЕНИЕ

**Не получава chat-driven UI.** sale.php остава както е дефинирано в SIMPLE_MODE_BIBLE §7.1 (минимален mode):

- Камера постоянно отворена + beep при сканиране
- Popup само при long press
- Voice STT → AI попълва → confirm
- Custom numpad (НИКОГА native клавиатура)
- БЕЗ AI сигнали, БЕЗ chat dialog

**Защо:** sale.php е "in the moment" — Пешо има клиент пред себе си. Не време за разговор с AI. Скорост > всичко.

### 6.3 СТОКАТА (products.php?mode=simple)

**Чат най-отгоре** — обхожда сигнали за стоката (свърши се, цена под себестойност, top sellers).

**Основни бутони:**
- 🔍 **Търси стока** → отваря пълен списък на артикули + търсене (нормална под-страница)
- ➕ **Добави нов артикул** → отваря Add Product wizard (4 стъпки, нормална под-страница)

**Nudges (Скрити пари):**
- N артикула чакат снимка (→ AI Studio)
- N без цена едро (→ filter)
- N чакат броене (→ inventory.php)

**Под-страници (нормален UI, БЕЗ chat):**
- Списък на артикули (search + table)
- Add Product wizard (4 стъпки)
- Detail на артикул
- Filter drawer

### 6.4 ДОСТАВКА (deliveries.php?mode=simple) — + ТРАНСФЕРИ ВЪТРЕ

**Решение на Тихол (16.05.2026):** В лесен режим **доставка и трансфери са в един модул**. В разширен — могат да са разделени.

**Чат най-отгоре** — обхожда сигнали:
- Закъсняла доставка (от доставчик)
- Очаквана доставка днес
- Предложение за трансфер (multi-store)

**Основни бутони:**
- 🚚 **Получи нова доставка** → 4 опции (Снимай фактура / Кажи какво има / Сканирай / Импорт)

**Nudges:**
- Сегашни доставки (закъснели, очаквани)
- Трансфери между магазини (предложения)

**Под-страници (нормален UI):**
- Списък на доставки
- OCR camera + voice (вече дефинирано в SIMPLE_MODE_BIBLE §7.3)
- Detail на доставка
- Трансфер wizard (multi-store)

### 6.5 ПОРЪЧКА (orders.php?mode=simple)

**Чат най-отгоре** — обхожда AI чернови:
- "Чернова за Спорт Груп: 10 артикула, 450€. Изпращам ли?"
- "Чернова за Marina: 6 артикула, 180€. Изпращам ли?"

**Основни бутони:**
- 📋 **Направи нова поръчка** → voice cart (AI пита "От кой доставчик?")

**Nudges:**
- AI чернови (по доставчик)
- Изпратени поръчки чакащи доставка

**Под-страници (нормален UI):**
- Списък на поръчки (с status)
- Voice cart wizard (вече в SIMPLE_MODE_BIBLE §7.4)
- Detail на поръчка

### 6.6 СКРИТИ ПАРИ (inventory.php?mode=simple)

**ВАЖНО:** Mental rebrand — никога "Инвентаризация", винаги "Скрити пари".

**Чат най-отгоре** — обхожда:
- "В склада има 8 артикула които не са преброени никога. 3 минути работа."
- "7 артикула стоят над 90 дни без продажба. ~280€ замразени."

**Основни бутони:**
- 📦 **Започни броене** → zone walk (вече дефинирано в INVENTORY_v4)

**Nudges:**
- Store Health % (AI те познава на 78%)
- N артикула чакат броене
- Zombie артикули (90+ дни)

**Под-страници (нормален UI):**
- Zone walk (AI води по артикул)
- Списък zombie артикули
- Store Health detail

### 6.7 Какво НЕ влиза в chat — sub-pages остават нормален UI

Тихол потвърди (16.05.2026): *"страничните страници като след като отвори продукти ще му ще има списък. След като отвори добави артикул ще има целия в Лоу на добави артикул. Само ОСНОВНИТЕ СТРАНИЦИ ще бъдат с чат и с по един-два бутона."*

| Страница | Има ли chat? |
|---|---|
| life-board.php (home) | ✅ Да |
| products.php?mode=simple | ✅ Да |
| products-list.php (списък) | ❌ Не — нормален UI |
| products-add.php (wizard) | ❌ Не — нормален UI |
| deliveries.php?mode=simple | ✅ Да |
| deliveries-receive.php (OCR/voice) | ❌ Не |
| orders.php?mode=simple | ✅ Да |
| orders-create.php (voice cart) | ❌ Не |
| inventory.php?mode=simple | ✅ Да |
| inventory-zone.php (zone walk) | ❌ Не |
| sale.php (всички режими) | ❌ Не — ИЗКЛЮЧЕНИЕ |

---

## 7. AI CONVERSATION ENGINE

### 7.1 ai_brain_queue (extended schema)

Запазва се от SIMPLE_MODE_BIBLE §4.5.3 с **2 нови колони**:

```sql
ALTER TABLE ai_brain_queue 
  ADD COLUMN module ENUM('home','products','deliveries','orders','inventory') 
  DEFAULT 'home' AFTER type;

ALTER TABLE ai_brain_queue 
  ADD COLUMN action_buttons JSON NULL AFTER action_data;
-- Пример action_buttons:
-- [
--   {"label":"Поръчай","action":"order_draft","data":{"product_id":123,"qty":10},"style":"primary"},
--   {"label":"Не сега","action":"dismiss","style":"secondary"},
--   {"label":"Покажи още","action":"open_detail","data":{"product_id":123},"style":"tertiary"}
-- ]

ALTER TABLE ai_brain_queue 
  ADD INDEX idx_module_pri (tenant_id, user_id, module, status, priority);
```

**Why `module` колона:** AI се обхожда per-module. Home обхожда `module='home'` queue. Products обхожда `module='products'`. Един и същи insight може да генерира 2 queue items (един за home brief, един за products module brief) — те имат различен contextе.

### 7.2 conversation_state (НОВА таблица)

За запазване на progress при превключване между модули:

```sql
CREATE TABLE conversation_state (
  user_id INT NOT NULL,
  module ENUM('home','products','deliveries','orders','inventory'),
  current_queue_id INT NULL,
  progress_position INT DEFAULT 0,
  total_items INT DEFAULT 0,
  last_interaction_at DATETIME,
  ttl_until DATETIME,  -- 4 часа default
  PRIMARY KEY (user_id, module),
  INDEX (ttl_until)
);
```

**Поведение:** Пешо е на step 3 от 8 в home brief, отваря sale.php (продажба), връща се → продължава от step 3. След 4 часа → reset.

**Cron:** на час, изтрива записи с `ttl_until < NOW()`.

### 7.3 conversation-engine.php (НОВ файл)

Един PHP файл с 3 основни функции:

```php
function startConversation($tenant, $user, $module = 'home') {
  // Заявка към ai_brain_queue WHERE module=$module AND status='pending'
  // Подреждане по priority + age
  // Връща първото съобщение + action_buttons
  // INSERT в conversation_state (progress=0)
}

function continueConversation($tenant, $user, $module, $action) {
  // $action = 'yes' | 'no' | 'later' | 'show_all' | 'custom_voice'
  // Маркира текущия queue item като done/dismissed/snoozed
  // Increment progress_position в conversation_state
  // Връща следващото съобщение, или края
}

function executeAction($action_type, $action_data) {
  // $action_type = 'order_draft' | 'price_adjust' | 'mark_resolved' | ...
  // Извършва действието през съществуващи endpoints
  // Връща confirmation message за toast
}
```

**Размер:** ~400 реда. Recycling на съществуващи endpoints — не нова бизнес логика.

### 7.4 Plan gating — same skeleton, different prompts

Тихол: *"Какво правим когато имаме само малкия план който не включва AI съвети?"*

**Решение:** Лесният режим работи и без AI мозък, но UI скелетът остава. Само prompt-ът се различава:

| План | AI разговор казва (пример) |
|---|---|
| **PRO** | "Имаш 8 неща. Nike свърши, губиш €32/ден..." (пълни insights) |
| **START** | "Имаш 3 артикула без снимка, 5 без баркод. Искаш ли да ги попълниш?" (само факти от DB, без анализ) |
| **FREE** | "Здрасти Пешо. Готов ли си да започнеш да продаваш?" (само onboarding nudges) |

Същият `conversation-engine.php`. Различни `prompt_template_{plan}.php` файлове. Никога празен екран. Никога "AI is locked" message.

---

## 8. VOICE + BUTTONS ПРОТОКОЛ

### 8.1 Винаги паралелно (новo правило от 16.05.2026)

Тихол: *"100% съгласен — трябва да има задължително винаги и втори изход в крайна сметка ако има много клиенти той не може да говори"*

**Правило:** Всеки AI въпрос идва с **глас + текст + 2-4 визуални бутона**. Винаги. Никога voice-only.

- Voice STT е 70-85% точност (стандартен говор) или 50-65% (диалект)
- Бутоните са fallback за шумен магазин, опашка, лошо настроение
- Бутоните са primary за нови потребители които още не са свикнали с voice

### 8.2 "Тих режим" — ОТПАДА

Старо предложение: settings toggle "AI ми пречи, тих режим".

**Решение на Тихол (16.05.2026):** *"не съм съгласен то ако си искат тишина ще вземе на обикновенна програма нямаше да вземе нас. Винаги мога да го игнорира и да не го чета и да си кара по съществения ред."*

**Реализация:** Игнорирането е достатъчно. Пешо тапва "После" и AI спира. Тапва [Стоката] директно и AI не пречи. Тапва [Продай] и AI няма достъп там. Никакъв toggle не е нужен.

### 8.3 Confidence routing (LAW №8) — запазен

Същият както в SIMPLE_MODE_BIBLE §12.7.3:
- `> 0.85` → Auto execute. Toast.
- `0.5 - 0.85` → Confirm dialog
- `< 0.5` → Reject + voice retry

### 8.4 voice_synonyms таблица — запазена

Същата като SIMPLE_MODE_BIBLE §12.7.2. Self-improving база per tenant lang.

### 8.5 Visual transcript confirmation — запазен

Никога silent action. Винаги показваме transcript-а ПРЕДИ AI parsing/action. Виж SIMPLE_MODE_BIBLE §12.7.1.

---

## 9. TOGGLE КЪМ РАЗШИРЕН РЕЖИМ

### 9.1 Позициониране

- **В life-board.php** — pill бутон в `rms-subbar` вдясно: `Разширен →`
- **В модулите** (products/deliveries/orders/inventory) — същия pill в header вдясно

### 9.2 Поведение

- Tap → `location.href='chat.php'` (или съответния модул `?mode=detailed`)
- `users.preferred_mode='detailed'` се запазва
- При следваща сесия — отваря Detailed home (chat.php)

### 9.3 Mode lock — за служители (запазено от v1.3)

`users.mode_locked=1` → toggle бутонът **изчезва от DOM-а** (не disabled). Иван (ENI 50г) пример use case — заключен в Simple, никога не вижда chat.php.

---

## 10. АРХИТЕКТУРНИ ПРАВИЛА (запазени от v1.3 §3)

1. **Един view файл** — `products.php`, `deliveries.php`, `orders.php`, `inventory.php` — всеки е ЕДИН файл с conditional rendering за `?mode=simple`. НЕ две версии.
2. **Никакъв SQL в view файл.** Само rendering + AJAX calls.
3. **`/api/*.php`** са REST endpoints. Един endpoint обслужва двата режима.
4. **`services/*.php`** съдържат бизнес правила. Един service = един източник на истина.
5. **DB queries** живеят в services, не в endpoints, не в views.
6. **CSS правила** използват body class: `<body class="mode-simple">` или `<body class="mode-detailed">`. Pattern: `.mode-simple .complex-panel { display:none }`.

### 10.1 Изключения (отделни файлове)

- `life-board.php` = Simple home (отделен файл, не делитa file с chat.php)
- `chat.php` = Detailed home (отделен файл)
- `sale.php` = един файл, но `mode-simple` body class активира специален UI без AI

### 10.2 Какво НЕ правим

- ❌ Toggle "Beginner / Advanced" вътре в един модул
- ❌ Settings page с 30 опции "show this, hide that"
- ❌ Wizard mode първите 5 минути и after that пълен expert
- ❌ Отделни `simple-*.php` файлове паралелно на `*.php`
- ❌ Споменаване "Gemini" в UI (винаги "AI")
- ❌ Hardcoded валута ("лв"/"€") — винаги `priceFormat($amount, $tenant)`
- ❌ Hardcoded BG текст — винаги `$T[]` lookup за `tenant.lang`

---

## 11. SACRED NEON GLASS — ЗАДЪЛЖИТЕЛНИ ПРАВИЛА

От HANDOFF_WIZARD_DESIGN_S146.md (15.05.2026). Тези правила се прилагат **на всеки glass card** в Лесен режим.

### 11.1 HTML pattern (задължителен)

```html
<div class="glass {hue-class}">
  <span class="shine"></span>
  <span class="shine shine-bottom"></span>
  <span class="glow"></span>
  <span class="glow glow-bottom"></span>
  
  <div style="position:relative; z-index:5">
    <!-- Цялото съдържание винаги с z-index:5+ -->
  </div>
</div>
```

**4 spans задължителни.** Без тях няма iridescent border и outer glow.

### 11.2 CSS правила (от DESIGN_SYSTEM_v4.0_BICHROMATIC.md §5.4)

- ✅ `conic-gradient` за iridescent border (НЕ linear-gradient)
- ✅ `oklch()` за неон цветовете (НЕ rgba())
- ✅ `mix-blend-mode: plus-lighter` на `.glow` (без него цветовете са мътни)
- ❌ НЕ `overflow: hidden` на `.glass` (убива outer glow)

### 11.3 Hue классове

- `.q1` — червен (loss/danger), hue 0
- `.q2` — виолетов (cause-loss), hue 280
- `.q3` — зелен (gain/success), hue 145
- `.q4` — циан (cause-gain), hue 180
- `.q5` — амбър (order/warning), hue 38
- `.qd` — default indigo (без специфична семантика)
- `.qhelp` — magic violet (за AI), hue 280

### 11.4 За AI chat card специфично

```html
<div class="glass ai-chat-card qhelp">
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <span class="glow"></span><span class="glow glow-bottom"></span>
  <!-- chat съдържание тук -->
</div>
```

**Hue:** `qhelp` (violet 280/310) — защото е AI секция, тон magic/intelligence.

---

## 12. MODE LOCK PER EMPLOYEE (запазен от v1.3 §3.5)

### 12.1 Защо

В multi-user tenant служител може случайно да tap-не "→ Разширен" → попада в chat.php → произволни тапове → грешки в данни. Особено опасно за по-възрастни (Иван 50г) или нови наемници.

### 12.2 Реализация

```sql
ALTER TABLE users 
  ADD COLUMN mode_locked TINYINT(1) DEFAULT 0 
  AFTER preferred_mode;
```

**Поведение:**
- `mode_locked=0` (default) — toggle бутон виден, служителят сменя свободно
- `mode_locked=1` — toggle бутонът **изчезва от DOM-а**

### 12.3 Use case (ENI магазин — beta)

- Митко (owner) → unlocked, toggle виден
- Иван (50г продавач) → `mode_locked=1`, preferred='simple' → винаги в life-board
- Ани (25г) → `mode_locked=0`, preferred='simple' → има toggle

---

## 13. NOTIFICATIONS (mode-aware, запазени от v1.3 §13)

### Backend
Един notification trigger. Render-ва се различно за всеки потребител в зависимост от `preferred_mode`.

### Detailed format
`"Nike Air Max 42: low stock alert. Current: 0 units. Avg daily sales: 1.2. Suggested reorder: 10 units from Sport Group (€8.5/unit, lead time 3d)."`

### Simple format  
`"Nike 42 свърши. Губиш €32/ден. Поръчай ли 10?"`

Същите данни. Различен tone. Симетрично с AI разговор pattern.

---

## 14. DB SCHEMA CHANGES — TOTAL

Минимални:

```sql
-- 1. Extension на ai_brain_queue
ALTER TABLE ai_brain_queue 
  ADD COLUMN module ENUM('home','products','deliveries','orders','inventory') DEFAULT 'home' AFTER type,
  ADD COLUMN action_buttons JSON NULL AFTER action_data,
  ADD INDEX idx_module_pri (tenant_id, user_id, module, status, priority);

-- 2. Нова conversation_state таблица
CREATE TABLE conversation_state (
  user_id INT NOT NULL,
  module ENUM('home','products','deliveries','orders','inventory'),
  current_queue_id INT NULL,
  progress_position INT DEFAULT 0,
  total_items INT DEFAULT 0,
  last_interaction_at DATETIME,
  ttl_until DATETIME,
  PRIMARY KEY (user_id, module),
  INDEX (ttl_until)
);
```

**Колонии запазени от v1.3:** `users.preferred_mode`, `users.mode_locked`, `voice_synonyms` таблица — всички остават както са.

---

## 15. MIGRATION PATH

### 15.1 Phase 0 — Решения (1 ден, само Тихол)

Преди да напишем код — потвърждение на:
- AI chat card позиция: над s82-dash или след weather? (Open Q1 — виж §17)
- AI Brain pill — премахната напълно или mini-FAB в модулите? (Open Q2)
- Toggle позициониране в модулите — header или subbar? (Open Q3)

### 15.2 Phase 1 — DB (1 ден)

ALTER + CREATE от §14. Готово.

### 15.3 Phase 2 — conversation-engine.php (3-5 дни)

Нов файл, 3 главни функции. Recycling на съществуващи endpoints.

### 15.4 Phase 3 — life-board.php rewrite (3-4 дни)

- Махаме help-card + lb-cards
- Добавяме ai-chat-card (qhelp)
- Запазваме всичко друго 1:1
- Тестване с tenant_id=7 (тестов профил)

### 15.5 Phase 4 — Модули по ред (2-3 дни всеки)

В реда на важност:
1. **products.php?mode=simple** — най-използван
2. **deliveries.php?mode=simple** (+ трансфери вградени) — beta-critical
3. **orders.php?mode=simple**
4. **inventory.php?mode=simple** (mental rebrand → "Скрити пари")

### 15.6 Phase 5 — Plan gating (1 ден)

3 различни prompt_template файла. `effectivePlan()` функция избира.

### 15.7 Phase 6 — Testing (2-3 дни)

С Иван от ENI (нов tenant_id, не 7). Acceptance criteria:
- Иван разбира AI разговора за 5 минути без обяснение
- Тапва правилния бутон при всеки signal
- Не се загубва между home и модулите

### 15.8 Total

**~13-15 дни.** Реалистично с buffer: **20 дни**.

---

## 16. BETA DELAY IMPACT

**Беше:** край на май / начало на юни 2026  
**Сега:** **середата на юни 2026** (~2 седмици delay)

Тихол прие забавянето като приемливо (16.05.2026). Алтернативата (само home + старите модули) би създала inconsistent UX — Пешо влиза в "новия" home → tap на Стоката → "стар" UI → объркване.

**ENI магазин** ще е първият реален потребител. Иван (50г) ще тества новата визия.

---

## 17. OPEN QUESTIONS — ЗА РЕШЕНИЕ В S148

### Q1: AI chat card позиция в life-board

- **A)** Над s82-dash (Пешо вижда AI пръв)
- **B)** След weather (както сегашния mockup)
- **C)** На мястото на help-card (между weather и end)

Препоръка: B (минимална промяна) или A (по-Simple-first). Решава се на S148 start.

### Q2: AI Brain pill — отпада ли напълно?

SIMPLE_MODE_BIBLE v1.3 имаше AI Brain pill като отделен бутон с 3 поведения (reactive/proactive/conversational). В новата визия:

- **Reactive** (Пешо стартира) → в life-board: voice bar отдолу. В модулите: voice bar отдолу.
- **Proactive** (AI има queue) → в life-board: chat card е винаги активен с първото съобщение. В модулите: chat card горе.
- **Conversational** (Пешо в модул, иска нещо извън flow) → ?

**Open:** Имаме ли нужда от **mini-FAB** в `sale.php` за case C (Пешо в продажба иска "сложи 10% отстъпка")?

Препоръка: Да, mini-FAB остава в sale.php (само там, тъй като sale.php няма chat card). В останалите 4 модула — voice bar е достатъчен.

### Q3: Toggle позициониране в модулите

Header (с back бутон) или subbar (отделна линия)?

Препоръка: Header — едно ниво по-горе от chat card, ясна йерархия.

### Q4: chat.php двойствеността от S144

В S144 (15.05.2026) се беше въвело "chat.php ~95% работещ Simple home сигнали стабилни". С новата визия:

- **chat.php** = Detailed home ТОЧНО. Никаква Simple логика.
- "Simple home сигнали" от S144 → пренасят се в life-board.php
- Това **нарушава** Закон #9 ("един файл = един режим") ако се остави

**Решение:** В Phase 3 (life-board rewrite) → изтриваме Simple-home логиката от chat.php. chat.php е САМО Detailed.

### Q5: Какво се случва когато няма ai_brain_queue items?

Пешо отваря life-board → `module='home'` queue е празна → какво показва AI chat card?

Препоръка: 
> *"Здрасти Пешо. Днес всичко е тихо. Продаде 287€ досега. Карай — ако ти трябва нещо, питай ме."*

Без бутони. Само информативно. По-добре от тишина (която прави Пешо да си мисли че приложението не работи).

### Q6: AI tone evolution (от BIBLE_v3_0_CORE §11)

Старата BIBLE дефинираше AI tone evolution: чирак (първи 30 дни) → колега (30-90д) → автопилот (90+д). 

Open: Влияе ли това на новата визия? Промянят ли се prompt-те по време?

Препоръка: Да, остава. `conversation-engine.php` чете `tenant.days_active` и избира tone. Минимална допълнителна логика.

---

## 18. КАКВО СЕ ПРОМЕНЯ В ДРУГИ ДОКУМЕНТИ

### 18.1 SIMPLE_MODE_BIBLE.md v1.3

- §4.1 Layout (life-board) → **deprecated**, заменено от §5 в този документ
- §4.3 Insights секция → **deprecated**, премахната
- §4.5 AI Brain pill → **частично deprecated**, поведенията се преместват в chat card (виж §7 тук)
- §5 Общ pattern за модули → **разширен**, новата визия в §6 тук
- §7.1-7.4 Modules details → **остават актуални** за под-страниците (списъци, wizards), но главните модулни екрани се описват в §6 тук

### 18.2 BIBLE_v3_0_TECH.md

- §1.2 Simple Mode = simple.php → **incorrect**, поправка: simple.php не съществува, Simple home е life-board.php
- §7 CHAT.PHP пълна спецификация → **актуална**, само за Detailed home

### 18.3 DESIGN_SYSTEM_v4.0_BICHROMATIC.md

- §1.1 Двата режима → **остава актуално**
- §5.4 Sacred Neon Glass → **остава актуално** (тук просто го referenc-ваме)
- §3.1 Header форми A/B/C → **остава актуално**, в Simple използваме форма B

### 18.4 PREBETA_MASTER_v2.md

- Phase 4 (Simple Mode finalize) → се обновява със Solid plan от §15 тук
- Beta дата → края на май → средата на юни

### 18.5 MASTER_COMPASS.md

- S146 → S147 entry (този документ)
- "chat.php ~95% работещ Simple home сигнали" → корекция: chat.php е САМО Detailed, life-board.php е Simple

---

## 19. CHECKLIST за S148 boot

Преди да започнем код в следващия чат:

- [ ] Прочети този документ изцяло
- [ ] Прочети HANDOFF_WIZARD_DESIGN_S146.md (Sacred Neon Glass правила)
- [ ] Прочети DESIGN_SYSTEM_v4.0_BICHROMATIC.md §5.4
- [ ] Прочети life-board.php редове 516-720 (CSS) + 1476-2207 (body)
- [ ] Прочети mockup-а: `life-board-easy.html` от S147 (16.05.2026)
- [ ] Тихол решава Open Q1-Q6 от §17
- [ ] Започваме Phase 1 (DB) → Phase 2 (conversation-engine)

---

## 20. РЕЗЮМЕ ЗА ТИХОЛ

**Какво се променя в едно изречение:**
> Главните 5 екрана в Лесен режим (home + 4 модула) получават активен AI разговор най-отгоре. Под-страниците остават нормални.

**Какво НЕ се променя:**
- Sacred Neon Glass дизайн (1:1)
- 4-те ops бутона
- sale.php
- chat.php (Detailed)
- Backend, DB business logic

**Колко работа:** ~15-20 дни. Beta delay ~2 седмици.

**Първи realен тест:** Иван (50г) от ENI магазин в средата на юни 2026.

---

**END SIMPLE_MODE_NEW_v1.md**
