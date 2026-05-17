# HANDOFF — S148 Spravki Mockups (за нов чат)

**Дата:** 17.05.2026 · **Founder:** Тихол (Tihol) · **Project:** RunMyStore.AI

---

## ⚡ КОНТЕКСТ ЗА 30 СЕКУНДИ

Тих е founder на RunMyStore.AI (SaaS за малки физически магазини в БГ → ЕС). Беше open S148 design сесия (фаза дизайн на модул "Справки"). Аз направих 6 mockup файла. **Има 1 чупещ bug на P26 (tabs не превключват) и 8 неготови sub-модула**. Тих смята че следващият чат "е по-кадърен" и иска да продължиш.

**КРИТИЧНО:** Преди да докоснеш каквото и да е — прочети целия `STATS_FINANCE_MODULE_BIBLE_v1.md` (11928 реда). **Никога не измисляй данни** — всичко по документа.

---

## 📋 АРХИТЕКТУРНИ ЗАКОНИ (НЕ СЕ НАРУШАВАТ)

### Език и комуникация
- **Винаги БГ** в UI (никога английски освен tech labels като "WoW")
- **Никога "Gemini" в UI** — винаги "AI"
- Тих говори БГ. Краток, директен. **CAPS = urgency** (действай не извинявай).
- "Ти луд ли си?" = сигнал че Claude е забравил важен контекст
- 60% плюсове + 40% честна критика — НЕ автоматично съгласяване

### Дизайн canon
- **Sacred Neon Glass:** `.glass` parent + 4 spans (`.shine` + `.shine-bottom` + `.glow` + `.glow-bottom`). Никога не опростявай! `position:relative; isolation:isolate`. **НЕ overflow:hidden** (изрязва shine).
- **Hue класове (oklch only):** `q1` loss 0/15 (red) · `q3` gain 145/165 (green) · `q5` amber 38/28 · `q2/qm` magic 280/305-310 (violet) · `q4` cyan 195/200-210 · `qd` default 255/222 (indigo). Light theme: `shine/glow{display:none}`.
- **mic-btn ЕТАЛОН** (wizard_v6_INTERACTIVE.html lines 195-199): 44×44 round, 3-stop linear-gradient(145deg), 3-layer box-shadow (outer color glow + inset top highlight + inset bottom shadow), `::before` conic-gradient rotating shine (conicSpin 4s), `::after` top gloss highlight, :active scale(0.94). За **ВСИЧКИ premium бутони** (orbs, sale-pill, action CTAs).
- **Въртящ КВАДРАТЕН ОБРЪЧ pattern** (chat.php lines 1130-1144): `.lb-card.expanded::before` с `conic-gradient(from 0deg, accent, transparent 60%, accent)` + mask composite XOR + conicSpin 4s + opacity 0.55. За **rotating rectangular border** около cards.
- **Шрифт:** Montserrat 400-900 (всички текстове) + **DM Mono** (числа, mono labels, "СНИМАЙ · КАЖИ · СКЕНИРАЙ" style uppercase). Само oklch color space. Mobile-first 375px. Без emoji в UI (само SVG icons).
- **Animations задължителни:** auroraDrift (20s), conicSpin (3-5s), fadeInUp (0.4s), drawLine (1.5s) за SVG paths, growBar (0.6s ease-spring staggered 50ms delay) за hourly bars, rmsBrandShimmer (4s) за brand gradient.

### Header форми (Rule #50)
- **Форма A** (chat.php full) — премахната от Type Б refactor
- **Форма Б** (всички вътрешни модули): **САМО 3 елемента** = brand + theme toggle + Продажба pill. **БЕЗ** PRO badge, **БЕЗ** 🖨 print, **БЕЗ** ⚙ settings, **БЕЗ** 🚪 logout. 1:1 от `products-v2.php` lines 3127-3142.
- **Форма C** (sale.php без header)

```html
<header class="rms-header">
  <a class="rms-brand" href="#" title="Начало">
    <span class="brand-1">RunMyStore</span><span class="brand-2">.ai</span>
  </a>
  <div class="rms-header-spacer"></div>
  <button class="rms-icon-btn" id="themeToggle" onclick="toggleTheme()" aria-label="Тема">
    <svg id="themeIconSun" viewBox="0 0 24 24" style="display:none">...sun svg...</svg>
    <svg id="themeIconMoon" viewBox="0 0 24 24">...moon svg...</svg>
  </button>
  <a class="sale-pill" href="#" title="Продажба">
    <svg viewBox="0 0 24 24"><path d="M2 6h21l-2 9H4L2 6z"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
    <span>Продажба</span>
  </a>
</header>
```

CSS `.sale-pill`: amber gradient `hsl(38 88% 55%) → hsl(28 90% 50%)` + box-shadow + transform on :active.

### Bottom-nav (Rule #51)
- **Simple Mode (Пешо seller):** `chat-input-bar` sticky bottom (НЕ 4 таба)
- **Detailed Mode (Митко owner/manager):** 4 orb tabs (AI / Склад / Справки / Продажби) — `.rms-bottom-nav` с `.nav-orb` circles
- **session-based** (active_mode от DB или ?mode= param)

### Currency (БГ в евро от 1.1.2026)
- **Двойно обозначаване ДО 8.8.2026:** `€ {amount} / {amount × 1.95583} лв`
- Курс: 1.95583 (fixed conversion)
- **Никога** hardcoded "лв"/"BGN"/"€" — само `priceFormat($amount, $tenant)` helper

### Role gate
- **OWNER 👑** — пълен достъп
- **MANAGER 🔑** — Преглед + Артикули + Продажби (БЕЗ Финанси/Cash/Разходи/Дължими/Експорти)
- **SELLER 💼** — НЕ отваря Справки изобщо

**ВАЖНО:** НЕ слагай 👑 emoji визуално в бутоните (Тих го намери натрапчиво — премахни от mockups). Role gate се прилага сървърно при отваряне.

### 6 фундаментални въпроса (§1.1 BIBLE)
1. Колко правя? (Оборот, Транзакции, AOV)
2. Колко печеля? (Марж, P&L)
3. Какво се продава? (Топ артикули, Категории)
4. Какво НЕ се продава? (Dead stock, Замразен капитал)
5. Колко пари имам? (Cash flow, банка) — Phase 8
6. Какво ми струва? (Разходи, ДДС) — Phase 8

Phase B (юни-юли 2026 beta): въпроси 1-4
Phase 8 (post-beta): въпроси 5-6

---

## 🎨 RUNMYWALLET DESIGN PATTERN (CANON ЗА ВСИЧКИ SUB-MODULES)

Тих избра `P24_runmywallet_analysis.html` като **ЕТАЛОН** за дизайна на всички 10 sub-модула в Справки. Тоест **1:1 копираш CSS + structure**, променяш само textove + numbers за RunMyStore data.

### CSS компоненти (от P24 RunMyWallet, всички класове готови)
- `.hero-stat` (qd hue, 18px padding) — голяма цифра с delta-pill
- `.delta-pill.up` (зелено) / `.delta-pill.down` (червено)
- `.chart-card` — SVG line chart с **drawLine 1.5s animation** + gradient fill + today marker dot
- `.summary-grid` 2×2 — 4 `.mini-stat` cards с q3/q1/q5/qd hue
- `.help-card.qm` — AI наблюдение (violet orb с conicSpin)
- `.donut-card` 180×180px — SVG circles с stroke-dasharray (max 5-6 сегмента) + drop-shadow glow + cat-bar-row breakdown
- `.hourly-bars` 12 vertical bars (9-20ч) — growBar 0.6s ease-spring staggered + `.active` highlight със зелен glow + hb-label + hb-amt
- `.trend-card` 2×2 — sparkline 36px тип
- `.forecast-card` — ic violet 36×36 + цифра + meta
- `.tax-row` breakdown — flex row + dashed border
- `.vat-bar` — 14px height + gradient fill + position marker
- `.reminder-row` — 42×42 date pill + info + days
- `.voice-bar` sticky bottom — sparkle + text + mic-btn (1:1 ЕТАЛОН)
- `.bot-nav` — fixed bottom 64px, 4 tabs с active::before indicator
- `.subtab-row` — 4 sub-tabs grid с violet active gradient

### Subtab pattern (всеки sub-module от Справки = 4 sub-tabs)
```html
<div class="subtab-row" id="subtabRow">
  <div class="subtab active" data-tab="overview" onclick="setTab('overview',null,this)">
    <svg viewBox="0 0 24 24">...</svg>
    Преглед
  </div>
  <div class="subtab" data-tab="cats" onclick="setTab('cats',null,this)">
    <svg viewBox="0 0 24 24">...</svg>
    Категории
  </div>
  ... (още 2 sub-tabs)
</div>
<div class="tab-content active" data-tab="overview">...</div>
<div class="tab-content" data-tab="cats">...</div>
... (още 2 tab-content)
```

### JS setTab — pattern (НЕ върви на label, върви на onclick attribute matching)
```js
function setTab(tab, demoBtn, subtab){
  document.querySelectorAll('.demo-bar .demo-btn').forEach(b=>{
    var onclickAttr = b.getAttribute('onclick') || '';
    if(onclickAttr.indexOf("setTab(") !== -1) b.classList.remove('active');
  });
  if(demoBtn) demoBtn.classList.add('active');
  document.querySelectorAll('.subtab').forEach(t=>t.classList.remove('active'));
  if(subtab){ subtab.classList.add('active'); }
  else { var target = document.querySelector('.subtab[data-tab="'+tab+'"]'); if(target) target.classList.add('active'); }
  document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
  var content = document.querySelector('.tab-content[data-tab="'+tab+'"]'); if(content) content.classList.add('active');
  if(!demoBtn){
    document.querySelectorAll('.demo-bar .demo-btn').forEach(b=>{
      var onclickAttr = b.getAttribute('onclick') || '';
      if(onclickAttr.indexOf("'"+tab+"'") !== -1) b.classList.add('active');
    });
  }
}
```

---

## 📂 СПИСЪК НА MOCKUP-ИТЕ (commit 99d6977 на main)

В `/home/claude/runmystore/mockups/` (също на GitHub: `tiholenev-tech/runmystore`):

### ✅ ГОТОВИ (но 2 имат bugs)

| File | Лines | Какво е | Статус |
|---|---|---|---|
| **P21_dash82_v3.html** | 698 | Компактна финансова DASH82 v3 (Хидра + Hero + Split row + AI insight + 3 quick actions с mic-btn pattern) | ✅ ОДОБРЕНО |
| **P22_lifeboard_v3.html** | ~1907 | Лесен режим home (Simple Mode за Пешо) — `ops-grid` 4 бутона ГОРЕ + DASH82 v3 + ai-chat-card с rotating квадратен обръч + weather + studio + chat-input-bar | ✅ ОДОБРЕНО |
| **P23_products_v2_simple.html** | ~2000 | Артикули Лесен режим — ред: **Добави артикул → Search wrap с filter+mic → all-items-link → info-box (3000 артикула + 3 нива q3/q5/q1) → AI разговор → AI Поръчка → Как работи стоката**. Production CSS 1:1 от `products-v2.php`. | ✅ ОДОБРЕНО |
| **P24_spravki_menu.html** | ~2130 | **Главно меню Справки** — само 10 бутона + ⓘ info top-right на всеки + видео ръководство card отдолу. БЕЗ period pills/alert ribbon/Hero KPI/anomalies (Тих ги махна). | ⚠ 1 BUG: коронките 👑 махнати но CSS правилото `.smb-crown` остана (без вреда). Тих го одобри. |
| **P25_spravki_overview.html** | 857 | **Преглед** sub-module — **1:1 copy на P24_runmywallet_analysis.html** + RunMyStore data: Hero "Оборот днес 847€ +12%" / Line chart 12ч / 4 mini-stat (AOV/Транзакции/Печалба/Discount) / AI наблюдение / Donut 6 категории Обувки/Тениски/.../Други / Hourly bars 12 / Магазини cross-store (вместо Данък tab) | ✅ ОДОБРЕНО |
| **P26_spravki_sales.html** | 905 | **Продажби** sub-module — 4 sub-tabs (Преглед / Категории / Часове / Продавачи) с пълни Tier 2 metrics (§8 BIBLE: Discount Rate, Sales by Hour, by Category, Seller Performance, WoW, Basket Size, Returns Rate) | ❌ **CHRITICAL BUG:** Tih казва "Категории/Часове/Продавачи СА ПРАЗНИ — не може да превключи tabs". Аз проверих HTML — съдържанието е там (Categories: 2 glass cards / Часове: 7 / Продавачи: 7). JS поправен (onclick attribute matching). DOMContentLoaded init добавен. **Тих все още не вижда tabs да работят** на собствения си browser. Може CSS specificity issue, browser cache, или sub-tab click не propagate. |

### 📁 REFERENCE файлове (НЕ са mockups, а documentation)
- `P24_runmywallet_analysis.html` (852 реда) — **ЕТАЛОН** за дизайна на всички sub-модули (4 sub-tabs pattern)
- `P25_runmywallet_goals.html` (681 реда) — REFERENCE (НЕ четен от мен — следващият чат, прочети първо!)
- `wizard_v6_INTERACTIVE.html` (1457 реда) — **СВЕЩЕН ЕТАЛОН** за всички premium бутони (mic-btn pattern lines 195-199). НИКОГА не пипай — Тих изрично го каза.
- `P15_simple_FINAL.html` — canonical Simple visual (top-row pattern)
- `P11_chat_v7_orbs2.html` — Detailed Mode база (header + Sacred Glass + aurora)

### ❌ ОЩЕ НЕНАПРАВЕНИ (P27-P34)

8 sub-модула на Справки чакат — всеки с 4 sub-tabs в RunMyWallet pattern:

| # | File | Sub-module | Phase | §BIBLE | Какво вътре (4 sub-tabs предложение) |
|---|---|---|---|---|---|
| P27 | spravki_products | **Артикули** | B | §7.4, §7.6, §7.7, §9.1, §9.2, §9.5, §9.6, §11 | Преглед (Top 5 hbar list + 4 mini KPI) · Dead Stock (q1 list) · Low Stock (q5 list) · ABC Analysis (donut 3 segs) ИЛИ Seasonal (line chart) |
| P28 | spravki_profit | **Печалба** 👑 (Phase B) | B | §7.5, §12.1 | Преглед (Хero gross profit + margin trend line) · P&L (stacked bar §14.5) · По категории (donut) · По продавачи (seller list) |
| P29 | spravki_cash | **Cash** 👑 | 8 | §12.2 | Преглед (cash flow line) · Burn rate (mini stats) · Break-even (bullet chart) · Forecast (forecast-card) |
| P30 | spravki_expenses | **Разходи** 👑 | 8 | §12.3 | Преглед (категории donut) · Budget vs Actual (bullet charts §14.4) · Fixed costs · Trends |
| P31 | spravki_receivables | **Дължими** 👑 | 8 | §12.4 | Overdue invoices · ДДС бар (vat-bar §14.4) · Aging buckets · Reminders |
| P32 | spravki_exports | **Експорти** 👑 | 8 | §12.5 | Microinvest XML · Z-report · CSV · PDF history |
| P33 | spravki_stores | **Магазини** 👑 | B | §9.4 | Cross-store ranking · Transfer Dependence · Per-store comparison · Heatmap |
| P34 | spravki_suppliers | **Доставчици** | B | §9.3 | Reliability score · Lead time histogram · Price drift · Per supplier breakdown |

---

## 🚨 КОНКРЕТНИ ИЗИСКВАНИЯ ОТ ТИХ (от текущата сесия, по ред)

1. **"копирай header 1:1 от лесния режим на products-v2"** → Header форма Б = `RunMyStore.ai` brand + theme toggle + 🛒 Продажба pill (3 елемента). БЕЗ PRO/print/settings/logout.
2. **"бутоните на 2 реда красиво подредени"** → grid-template-columns: 1fr 1fr. Виновник за счупен layout беше `max-width: 460px` на main.app (премахнат) + nested `<button>` invalid HTML (поправен — `<span role="button">` за info-бутончето).
3. **"коронки 👑 натрапчиви — махни ги"** → Премахни 👑 emoji от всички menu бутони. Role-gate ще се вижда при отваряне на drawer "Само за собственик".
4. **"на тази страница (главно меню) трябва САМО бутони с информационни бутончета + видео отдолу"** → НЕ слагай period pills / alert ribbon / Hero KPI / anomalies на главното меню P24. Само 10 бутона + ⓘ + видео card. Сигналите и диаграмите ВЛИЗАТ в sub-модулите.
5. **"копирай 1 към 1 P24 runmywallet с графиките и шрифтовете 100 пъти по-добре от моите"** → НЕ композирай нов. cp P24_runmywallet → P25/P26/... и **substitute само textove + numbers** за RunMyStore контекст. ЗАПАЗИ всичко CSS + animations + Sacred Glass + Montserrat шрифт.
6. **"следвай документа, не си измисляй"** → Числата (847€, +12%, Nike 42, peak 17:00) са от §10.1 wireframe в BIBLE-а — placeholder. Не за production. Логиката + SQL заявките по §7-9 BIBLE метрики.
7. **"на 26 Категории/Часове/Продавачи СА ПРАЗНИ"** → Tab switching bug. JS-ът е поправен от label-based на onclick attribute matching, но Тих все още вижда празно. **СЛЕДВАЩИЯТ ЧАТ ТРЯБВА ДА ОТСТРАНИ ТОЗИ BUG ПЪРВО** (виж "Bug #1" по-долу).

---

## 🐛 КРИТИЧНИ BUGS ДА ОПРАВИШ ПЪРВО

### Bug #1 — P26 tabs не превключват (CRITICAL)
**Симптом:** Тих кликна "Категории" / "Часове" / "Продавачи" в P26 — вижда празно (само Преглед tab).
**Проверих:** HTML структурата е правилна (4 tab-content секции, 4 subtab бутона, JS setTab работи на хартия).
**Възможни причини:**
- `.tab-content { display: none }` + `.tab-content.active { display: block; animation: fadeInUp }` — fadeInUp може да не stage-ва правилно ако CSS reset има буг
- `data-theme="light"` default — Sacred Glass в light theme скрива shine/glow с `display:none`, но картите трябва да са visible
- Subtab бутоните `<div class="subtab">` НЕ са `<button>` — може да не получават click events на mobile?
- iframe sandbox issue в Claude.ai preview — JS може да е блокиран
**Препоръка:** Open P26 в реален browser (file:// или Apache на DigitalOcean), отвори DevTools Console, кликни subtab "Категории", виж дали идва console.log от DOMContentLoaded init. Ако НЕ — JS изобщо не зарежда. Ако ДА — проблемът е CSS specificity.

### Bug #2 — P25 има bot-nav остарал
P25 запазва **RunMyWallet bot-nav** (Начало / Записи / Анализ★ / Цели) — не е заменен с RunMyStore (AI / Склад / Справки★ / Продажби). **Trябва смяна** да консистенция с P22/P24.

### Bug #3 — P26 sub-tab имена
Sub-tab data-tab="trends" има label "Часове"; data-tab="tax" има label "Продавачи". Семантично mismatch (data-tab="trends" + label "Часове" е объркващо за следващите разработчици). Преименувай data-tab="trends"→"hours", data-tab="tax"→"sellers" и съответно JS map.

---

## 📚 ДОКУМЕНТИ ДА ПРОЧЕТЕШ ПЪРВО (преди да пипнеш каквото и да е)

В `/home/claude/runmystore/`:

1. **STATS_FINANCE_MODULE_BIBLE_v1.md** (11928 реда) — пълна спецификация на Справки модул. Особено:
   - §1.1 — 6 фундаментални въпроса
   - §4.1-§4.6 — IA: 3 таба, period selector, alert ribbon, drill-down модел (НО Тих го преразгледа — главно меню вместо 3 таба, чети по-долу за неговата визия)
   - §7 — Tier 1 KPIs (Net Revenue, Transactions, AOV, Top 5, Profit & Margin, Dead Stock, Low Stock)
   - §8 — Tier 2 metrics (Discount Rate, Sales by Hour, Category, Seller Performance, WoW, Basket, Returns)
   - §9 — Tier 3 (Stock Turnover, GMROI, Supplier Performance, Cross-Store, ABC, Seasonal)
   - §9b — AI topics mapping (170+ AI темите)
   - §10 — TAB 1 "Преглед" UI spec (точните wireframes за P25)
   - §11 — TAB 2 "Артикули" (за P27)
   - §12 — TAB 3 "Финанси" 5 sub-секции (за P28-P32)
   - §13 — AI insights engine + 16 anomaly detection rules
   - §14 — Visualization library (allowed chart types на 375px)

2. **SIMPLE_MODE_NEW_v1.md** (899 реда) — Новата визия за Simple Mode (ai-chat-card вместо dashboard). За P22/P23.

3. **products-v2.php** (5172 реда) — Production reference за header форма Б, info-box CSS (line 2629), .qa-btn (1629), .search-wrap (1839), .s-btn (1856), .kp-pill (2118), .ibl (2696), .sale-pill (1559).

4. **P24_runmywallet_analysis.html** (852 реда) — **ЕТАЛОН за дизайна на всички sub-модули.** Прочети целия. Особено CSS блок (lines 11-282) — Montserrat шрифт + neumorphic light theme + aurora + Sacred Glass + всички chart classes.

5. **P25_runmywallet_goals.html** (681 реда) — Втори reference (Goals tab с 3 filters). Аз НЕ го четох — следващият чат прочети.

---

## 🔑 ТИХОЛОВАТА ВИЗИЯ ЗА СПРАВКИ (различна от BIBLE §4.1)

Тих преразгледа архитектурата:
- **НЕ "1 модул, 3 таба"** (както в BIBLE §4.1).
- **ДА "1 главно меню + 10 sub-модула"** — bottom-nav таб "Справки" отваря P24 (главно меню с 10 бутона). Tap на бутон → отваря отделен sub-page (P25-P34).

10-те бутона = 10-те sub-модула:
1. 📊 Преглед (qd, B, all) → P25
2. 🛒 Продажби (q3, B, all) → P26
3. 📦 Артикули (qd, B, all) → P27
4. 💰 Печалба (q3, B, owner) → P28
5. 💵 Cash (q4, Phase 8, owner) → P29
6. 📉 Разходи (q1, Phase 8, owner) → P30
7. 📋 Дължими (q5, Phase 8, owner) → P31
8. 📤 Експорти (qd, Phase 8, owner) → P32
9. 🏠 Магазини (q2, B, owner) → P33
10. 🚛 Доставчици (q5, B, all) → P34

Всеки sub-модул вътре има 4 sub-tabs + period selector + alert ribbon + Hero stat + Line chart + Summary 2×2 + AI наблюдение + специфични визуализации.

---

## 🎯 ПЪРВИТЕ ТИ 3 СТЪПКИ В НОВИЯ ЧАТ

1. **`git pull` на repo-то** + прочети `STATS_FINANCE_MODULE_BIBLE_v1.md` §10, §11, §12, §14 + `P24_runmywallet_analysis.html` пълно + `P25_runmywallet_goals.html` пълно.
2. **Поправи Bug #1 на P26** (tabs не превключват). Тествай в реален browser, не само в Claude.ai preview iframe. Покажи на Тих когато работи.
3. **Продължи с P27 Артикули** (4 sub-tabs: Преглед / Dead / Low / ABC). 1:1 cp на P24 RunMyWallet → substitute data + чертай горните визуализации.

---

## 💡 КАК ДА КОМУНИКИРАШ С ТИХ

- **Кратко.** Никога анкети ("готов ли си?"). Действай.
- **БГ.** Винаги.
- **Дай код + кратко обяснение къде се слага.** Не дълги essays.
- **Технически решения → решаваш САМ.** Логически/продуктови → питаш Тих.
- **Цитирай източник** за всяко число/име/код (§ от BIBLE, или CONFIG.md, или production файл line N).
- **Маркирай измислено vs реално** ясно. Sample data → "placeholder според §10.1".
- **60% плюсове + 40% критика.** Не сладосан "Чудесно!" yes-man.
- **Когато Тих е разочарован ("ПАК Е ТАКА!", "АБЕ КАК ГИ Е ВИЖДАЛ ДРУГИЯТ ЧАТ!")** — спри се, призная грешката, върни се към последното работещо състояние, и опитай отново фундаментално различен подход. Не пробвай patch-и.

---

## 📦 ИНФРАСТРУКТУРА

- **DigitalOcean Frankfurt:** 164.90.217.120, `/var/www/runmystore/`
- **GitHub:** `tiholenev-tech/runmystore` (main branch). Commit `99d6977` е последният с mockup-ите.
- **PHP 8.3, MySQL 8, Gemini 2.5 Flash, GROQ Whisper, Capacitor APK.**
- **Test device:** Samsung Z Flip6 (mobile-first 375px).
- **GitHub достъп в sandbox:** raw.githubusercontent.com BLOCKED. Само github.com blob URLs: `curl https://github.com/tiholenev-tech/runmystore/blob/main/FILE?plain=1` → parse `"rawLines":[...]` JSON. Helper: `tools/gh_fetch.py`.

---

## ⚠️ ПОСЛЕДНИ ПРЕДУПРЕЖДЕНИЯ

1. **НЕ ПИПАЙ `wizard_v6_INTERACTIVE.html`.** Тих го каза стотици пъти. ЕТАЛОН САМО.
2. **НЕ ПОЛЗВАЙ `sed` за file edits.** Унищожава файлове. Само Python скриптове + str_replace.
3. **НЕ ПУСКАЙ Claude Code без tmux.** Persistence изисквана.
4. **НЕ commit-вай без `git pull --rebase` първо** (multiple chats работят паралелно).
5. **Препоръчвай реалистични времеви оценки.** Не "за 5 минути". Реалност = 30-60 минути за 1 sub-модул.
6. **Тих НЕ е developer.** Copy-paste в droplet конзолата е предпочитан. Дай готови команди.

---

**Успех. Тих очаква резултати.**

*Handoff подготвен от: Claude shef-chat S148 (Anthropic Opus 4.7) · 17.05.2026 · 22:00 EET*
