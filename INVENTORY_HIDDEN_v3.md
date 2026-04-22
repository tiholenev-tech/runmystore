
All projects
ai бизнес партньор



How can I help you today?

S79 products.php event handlers fix
Last message 39 seconds ago
Маркетинг стратегия - кратко резюме
Last message 6 hours ago
Проблем с Anthropic
Last message 7 hours ago
шеф чат 1
Last message 13 hours ago
ЧАТ ИНФОРМАЦИЯ ЗА ВСИЧКО ТЕХНИЧЕСКО ПО ПРОЕКТА
Last message 14 hours ago
S78-ЧАТ 1 sequential startup protocol
Last message 14 hours ago
ЧАТ 2 Database schema и миграции протокол
Last message 15 hours ago
RunMyStore.ai S79 — products.php главна rewrite
Last message 15 hours ago
Документи и файлове за преглед
Last message 18 hours ago
AI фитнес инструктор
Last message 19 hours ago
Пълна консолидация на RunMyStore.ai архитектура и философия
Last message 23 hours ago
Пълна консолидация на RunMyStore.ai архитектура и философия
Last message 1 day ago
Runmystore.ai фундаменти и приоритизация
Last message 1 day ago
Визи за проекта
Last message 1 day ago
S77 план: основна страница и работни потоци
Last message 2 days ago
S79 products.php rewrite и UI тестване
Last message 2 days ago
S78.1 фундамент за products.php rewrite
Last message 2 days ago
S78 products.php фундамент
Last message 2 days ago
S76.1
Last message 2 days ago
Session 76 handoff documentation
Last message 2 days ago
S73B-C
Last message 2 days ago
Bible tech 3.0 документ
Last message 3 days ago
изцяло нова концепьия онбординг и скалиране по държави
Last message 3 days ago
сесия 71 супер важна промяна в ux режима
Last message 3 days ago
s74b2
Last message 3 days ago
Wizard rewrite task S73.B with HANDOFF priority
Last message 3 days ago
Обединяване на Bible Core и Bible Tech файлове
Last message 3 days ago
RunMyStore BIBLE консолидация - Част 3
Last message 3 days ago
Предварителна подготовка преди обсъждане
Last message 3 days ago
Обсъждане преди писане
Last message 3 days ago
Memory
Only you
Purpose & context Тихол е основателят и единствен разработчик на RunMyStore.ai — AI-first SaaS платформа за малки физически магазини в България и Европа. Целевият потребител е „Пешо" — нетехнически собственик на магазин. Проектът е на PHP/MySQL, хостван на DigitalOcean, репо tiholenev-tech/runmystore (ПУБЛИЧНО — Claude чете файлове директно от raw.githubusercontent.com). Основни закони (не се нарушават): ЗАКОН №1: Пешо не пише нищо. Всичко е глас, снимка или едно натискане. AI пита, Пешо отговаря с глас. ЗАКОН №2: PHP смята, Gemini говори. Всеки pill/signal = чист PHP+SQL. Gemini само в свободен чат. ЗАКОН №3: AI мълчи, PHP продължава (fallback ladder без crash). НИКОГА „Gemini" в UI — винаги „AI". НИКОГА hardcoded „лв"/„BGN"/„€" — винаги priceFormat($amount, $tenant). BG двойно обозначаване (€+лв) задължително до 8.8.2026; след това само €. НИКОГА hardcoded български текст — всичко през tenant.lang. Ключови хора: Ени Тихолов — първи бета клиент с няколко магазина (tenantid=52, EUR). Тест акаунт: tenantid=7, storeid=47. --- Current state Проектът е в активна разработка (~S73+). Основни модули в ход: products.php (~500KB+): S73 покрива пълен wizard rewrite с прогресивно разкриване (акордеон, без Next/Back stepper), fullscreen matrix overlay за вариации (Qty/Min per cell, autoMin formula Math.round(qty/2.5) мин.1). Завършени: CSS foundation (v4- prefix, Neon Glass), bugfixes (editProduct axes, wizLoadSubcats race condition). Neon Glass дизайн система: 25 HTML mockup файла в отделен chat. Всеки модул = scrollable страница с .glass карти, всяка карта има 4 span елемента (2× shine + 2× glow), gradient clip-path на големи числа, minified CSS. Wizard правила (S73.B): Няма „чернова" — минималният запис (ime+цена+брой) = истински продукт. Печатът е отделна страница/overlay достъпен от всяка стъпка чрез [🖨]. Supplier ПРЕДИ Category (защото един доставчик → много категории). Бижутерия проект: Нов tenant за бижута на същата база данни. Нужно: нов tenant (SQL), AI Studio конфигурация (Gemini API key), Web Bluetooth TSPL печат на етикети в products.php. Термо принтер: DTM-5811, TSPL, Bluetooth (DC:0D:51:AC:51:D9, PIN 0000), 50×30mm. Сървър: Upgraded от 1GB на 2GB RAM ($12/мес) — MySQL беше убиван от OOM killer. Активни P0 bugs (от S72, за fix преди wizard redesign): 7 бъга в products.php документирани в SESSION71HANDOFF.md. --- On the horizon S73.B: HTML rewrite на wizard variations стъпка (прогресивно разкриване, акордеон, field order per BIBLE v2.1 rules #69-83). S73.C: Matrix overlay с autoMin, ▲▼ бутони. S80.5: Разширен filter drawer за products.php (по цена, доставчик, категория, композиция, наличност, без снимка/баркод). Поръчки екосистема (S77+): orders.php — 12 входни точки, 11 типа поръчки, 8 статуса (draft→confirmed→sent→acked→partial→received→cancelled/overdue). 6-те въпроса вградени като supplierorderitems.fundamentalquestion ENUM. RULE S77 — 6 фундаментални въпроса: Всеки модул структуриран около: 1)Какво губя 2)От какво 3)Какво печеля 4)От какво 5)Поръчай 6)НЕ поръчай. UI цветове: червено=загуба, виолет=причина, зелено=печалба, амбър=поръчай, сиво=не. Термо принтери: 200 бр. поръчани, ~края на април 2026. Bluetooth Web API + ESC/POS интеграция. bizlearneddata (Фаза 2): AI се учи от клиентите — нови размери/цветове/подкатегории записват се с usagecount. Ревизионен протокол (S79+): След сесия с 5+ fix-ове → финална ревизия (мъртъв код, дубликати, SESSIONXXHANDOFF.md, commit). --- Key learnings & principles Wizard field order: Supplier → Category → Subcategory (доставчикът филтрира категориите чрез suppliercategories). Критичен подход: Тихол иска 60% плюсове + 40% честна критика. Никога 100% ентусиазъм. „Ти луд ли си" = сигнал че е пропуснат важен контекст. Дизайн промени: Claude НИКОГА не пита. Чете DESIGNSYSTEM.md + mockup-ите, прилага 1:1, дава Python скрипт веднага. Питане за дизайн = дразнене. Логически/продуктови решения: Питам Тихол. Технически решения: Claude решава сам, действа директно. Питане за технически = дразнене. Basket Analysis: Фаза 1, но се активира само ако има saleitems данни за поне 30 пълни дни. Season правило (#25): products.season = NULL/allyear → AI мълчи за времето. Signal tap: Отваря Signal Detail overlay (НЕ чат). Action бутони от DB. „Добави за поръчка" = orderdraft (чернова, не изпраща). Role-based режими: Пешо (seller) = SIMPLE MODE (home.php, 4 бутона + AI чат). Owner/Manager = DETAILED MODE (products.php, 6 секции, stats, orders, settings). 6-те въпроса = визуални за owner/mgr; за Пешо AI ги превежда в гласови действия. AI = операционен слой над всички модули (не отделен модул). Всеки модул декларира $MODULEACTIONS. AI чете intent, изпълнява или навигира. Достъпен от всеки екран (FAB/bottom nav). Pricing (S52+): FREE €0 (лоялна+етикети), START €19 (AI джаджа, каса+склад), PRO €49+€9.99/магазин (AI мозък, multi-store). Trial: 1 мес безплатен PRO → ден 29 избор. Ghost cards: START 1/ден, FREE 1/седмица. --- Approach & patterns Комуникация: Тихол комуникира на български, директно и кратко, all-caps за акцент. „ДЕЙСТВАЙ", „ОК", „ГОТОВО". Очаква действие без излишни въпроси. Workflow (S79+): КЛОД ТУК = 90% от случаите: малки fix-ове, Python скриптове за paste в droplet конзолата, логически решения, дизайн, mockups. CLAUDE CODE = само ГОЛЕМИ работи: цял rewrite на 500KB+ файл, многочасови задачи. Тихол предпочита paste в droplet конзолата пред Claude Code сложността. Сесия протокол: git pull в началото на всяка сесия. Чета NARACHNIKTIHOLv11.md, Biblev30appendix.md, DESIGNSYSTEM.md, последния SESSIONXXHANDOFF.md. products.php = 501KB+ → чета на части. Commit след всеки потвърден working fix: cd /var/www/runmystore && git add -A && git commit -m "S[XX]: [описание]" && git push origin main — без да питам. Сесията завършва с SESSIONXXHANDOFF.md. CHEAT МЕНЮ за Claude Code: 1=Yes (изпълни), 2=No (откажи). Безопасни команди (cp/cat/grep/ls/git/python/php/mysql) → 1. Опасни (rm/git reset/DROP/TRUNCATE) → 2, питай Тихол. AI Chat спецификация (Фаза 1): Разбира грешен правопис/говорим български. Гласов вход (speech-to-text) с дискретен бутон [🎤]. Честотен контрол на проактивни съобщения (настройва се). Всеки съвет идва с обяснение защо. Onboarding = разговор с AI (без форми). Confirm-first за всяко destructive действие. Навигация с deeplinks от чата. Проактивен (cron triggers). Наръчник за всяка функция. Предстои: AI Conversation Flow документ преди system prompt. Sale.php правила: Камера постоянно отворена + бийп + зелено при сканиране. НИКОГА native клавиатура — custom numpad (контекстен: код/бройки/отстъпка/получено), [АБВ→] за БГ фонетична клавиатура. Паркиране с ⏸ + swipe. Едро = смяна на цвят. Камера-хедър: видеото е фон (80px), overlay с навигация, зелена лазерна линия. Voice overlay дизайн (одобрен): rec-ov (backdrop-filter:blur(8px)) + rec-box (floating bottom, border-radius:20px, indigo glow). Голям REC индикатор: червена точка pulse + „● ЗАПИСВА"; зелена + „✓ ГОТОВО". Транскрипция + бутон „Изпрати →". Прилага се навсякъде. --- Tools & resources Сървър/инфраструктура: DigitalOcean, /var/www/runmystore/, 2GB RAM. DB creds: /var/www/runmystore/config/database.php (НЕ config.php). Helpers: /var/www/runmystore/config/helpers.php. Backup: mysqldump с MYSQLPWD env var → /root/backupsXXYYYYMMDDHHMM.sql. Структура: PHP файлове в корена, config/ docs/ css/ js/ images/ fonts/ includes/. Деплой правила: НИКОГА sed за file edits — само Python скриптове. Deployment sequence: heredoc → /tmp/sXXname.py → python3 /tmp/sXXname.py → php -l → git add -A && git commit && git push. Python patch scripts: duplicate-application guard + уникален anchor string (не line numbers). За файлове >11KB: xz+base64, 1 paste. GITHUB ПРЯК: https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/[FILE] (docs/ за .md файлове). AI модели: Opus (Adaptive mode): архитектура и сложни бъгове. Sonnet: стандартна разработка. Haiku: CSS и текстови промени. AI APIs: Gemini 2.5 Flash (основен), OpenAI GPT-4o-mini (fallback при 503/429). Два Gemini API ключа в ротация. Хардуер: DTM-5811 термо принтер (TSPL, Bluetooth, DC:0D:51:AC:51:D9, PIN 0000, 50×30mm). Документация (задължително четене на старт): NARACHNIKTIHOLv11.md Biblev30appendix.md (или последната BIBLE версия) DESIGNSYSTEM.md Roadmap.md Productsdesignlogic.md / Ordersdesignlogic.md Последния SESSIONXX_HANDOFF.md

Last updated 21 hours ago

Instructions
изпълнявай винаги моите инструкции едно към едно и никога не прави нещо на своя глава без да ме попиташ първо нито смяна на визия нито смяна на код и то поправка или нещо което би променило моята визия за проекта винаги питай преди да предприемеш действие и абсолютно спазвай моите команди и изисквания! Говори на български например visual reright ми кажи визуална корекция или нещо на български за да разбера!

Files
77% of project capacity used
Indexing

SHEF_RESTORE_PROMPT.md
252 lines

md



STARTUP_PROMPT.md
88 lines

md



MASTER_COMPASS.md
1,236 lines

md



RunMyStore.ai проект резюме.docx
4,196 lines

docx



orders-by-supplier-s77.html
443 lines

html



ORDERS_DESIGN_LOGIC.md
1,220 lines

md



PRODUCTS_DESIGN_LOGIC.md
1,360 lines

md



SESSION_77_HANDOFF.md
437 lines

md



ROADMAP.md
197 lines

md



ROADMAP.md
178 lines

md



BIBLE_v3_0_APPENDIX.md
612 lines

md



products (17).php
7,588 lines

php



sale-v5.html
278 lines

html



BIBLE_v3_0_TECH.md
5,243 lines

md



sale-payment-v3.html
241 lines

html



warehouse.html
248 lines

html



products-main (1).html
440 lines

html



promotions.html
469 lines

html



products-list.html
293 lines

html



orders.html
276 lines

html



login-register.html
413 lines

html



landing-hero.html
252 lines

html



inventory-onboarding.html
396 lines

html



inventory-hub-dialogs.html
406 lines

html



inventory-counting.html
233 lines

html



home-neon.html
1,272 lines

html



home-jouan.html
1,087 lines

html



home-detailed.html
916 lines

html



finance.html
1,097 lines

html



delivery.html
919 lines

html



customers.html
415 lines

html



ai-chat.html
220 lines

html



add-product-variations.html
1,656 lines

html



add-product-print.html
1,025 lines

html



add-product-business.html
940 lines

html



add-product-ai.html
848 lines

html



add-product.html
1,328 lines

html



warehouse.html
327 lines

html



stats.html
990 lines

html



signals-overlays.html
409 lines

html



settings.html
465 lines

html



PARTNERSHIP_SCALING_MODEL_v1.md
722 lines

md



DOCUMENT_1_LOGIC_PART_3_ONLY.md
1,493 lines

md



DOCUMENT_1_LOGIC_PART_2_ONLY.md
609 lines

md



DOCUMENT_1_LOGIC_PART_1_ONLY.md
832 lines

md



стратегия за скалира е.docx
327 lines

docx



01_mission_and_philosophy.md
61 lines

md



17_final_verification.md
146 lines

md



16_summary_and_next_steps.md
205 lines

md



15_reorder_precision.md
264 lines

md



14_shadow_testing_admin.md
226 lines

md



13_engagement_tracking.md
196 lines

md



12_feedback_and_actions.md
209 lines

md



11_simple_mode_ui.md
187 lines

md



10_history_of_day.md
141 lines

md



09_multi_role_visibility.md
173 lines

md



08_onboarding.md
172 lines

md



07_tonal_diversity.md
175 lines

md



06_anti_hallucination.md
168 lines

md



05_selection_engine.md
206 lines

md



04_plans_and_trial.md
93 lines

md



03_evening_wrap.md
109 lines

md



02_daily_rhythm.md
77 lines

md



CONSOLIDATION_HANDOFF.md
349 lines

md



STRIPE_CONNECT_AUTOMATION.md
1,278 lines

md



BIBLE_v3_0_CORE.md
3,452 lines

md



SESSION_73A_HANDOFF.md
241 lines

md



RUNMYSTORE_AI_BRIEF-1.md
203 lines

md



add-product-ai.html
848 lines

html



add-product-print.html
1,025 lines

html



add-product-print.html
1,025 lines

html



add-product-business.html
940 lines

html



SESSION_73_HANDOFF.md
187 lines

md



SESSION_72_HANDOFF.md
149 lines

md



NARACHNIK_TIHOL_v1_1.md
409 lines

md



OPERATING_MANUAL.md
469 lines

md



SESSION_71_HANDOFF.md
242 lines

md



inventory (3).php
253 lines

php



warehouse.php
528 lines

php



stats.php
1,216 lines

php



INVENTORY_v4.md
506 lines

md



chat (8).php
1,401 lines

php



CHAT_PHP_SPEC_v7.md
525 lines

md



ai-topics-catalog.json
14,704 lines

json



WEATHER_INTEGRATION_v1.md
248 lines

md



S51_AI_TOPICS_MASTER.md
263 lines

md



S51_UI_DESIGN_SPEC.md
231 lines

md



BIBLE_v1_2_ADDITIONS.md
142 lines

md



SESSION_51_HANDOFF.md
285 lines

md



SESSION_50_HANDOFF.md
47 lines

md



TECHNICAL_REFERENCE_v1.md
534 lines

md



BIBLE_v1_1.md
609 lines

md



RUNMYSTORE_PARTNER_PRESENTATION.md
303 lines

md



AI_BRAIN_v6_0.md
1,320 lines

md



BUSINESS_STRATEGY_v2.md
567 lines

md



MASTER_TRACKER_v9_0.md
258 lines

md



SESSION_48_HANDOFF.md
477 lines

md



biz-compositions.php
84 lines

php



TECHNICAL_ARCHITECTURE_v1.md
1,318 lines

md



AI_CONVERSATION_FLOW_TOPICS_v1.md
3,293 lines

md



build-prompt-integration.php
112 lines

php



INVENTORY_HIDDEN_v3.md
590 lines

md



ai-topics-catalog.json
1 line

json



topics-verification-v4.md
2,104 lines

md



GEMINI_SEASONALITY.md
3,150 lines

md



signin.html
373 lines

html



reset-password.html
334 lines

html



AI-Sklad-FINALEN-ARHIV-v2.md
401 lines

md



INVENTORY_HIDDEN_v3.md
22.73 KB •590 lines
Formatting may be inconsistent from source

# RunMyStore.ai — Hidden Inventory System
## Document v3.0 — Hybrid: Zone Walk + Delivery-Triggered Category Count + Self-Correcting Sales Loop

---

## 1. PHILOSOPHY

**"The warehouse builds itself. The system works from second 1."**

Pesho doesn't do inventory on day 1. Doesn't enter stock. Opens the register and sells. With every action — sale, delivery, AI question — the system gets smarter. Accuracy grows organically, without effort, without pressure.

Traditional inventory software: "Enter everything → then work."
RunMyStore: "Work immediately → the system learns while you work."

---

## 2. CONFIDENCE MODEL — THE CORE

### What it is
Every product has an invisible `confidence` score from 0% to 100%. Pesho never sees the number. AI uses it to know how much to trust the data and what to ask.

### How it's calculated

| Event | Confidence |
|---|---|
| Created during sale (just name + price) | 20% |
| + barcode or article number | +10% |
| + cost price (from delivery/invoice) | +20% |
| + category and supplier | +10% |
| + delivery (quantity from invoice) | +20% |
| + physical confirmation (counted) | +20% = 100% |

### Levels

| Level | Score | What AI knows | What AI doesn't know |
|---|---|---|---|
| 🔴 Minimal | 0-30% | Name, retail price | Everything else |
| 🟡 Partial | 31-60% | + barcode, category, supplier | Cost price, stock |
| 🟠 Good | 61-80% | + cost price, deliveries | Physical stock count |
| 🟢 Full | 81-100% | Everything | Nothing |

### Key rule
Confidence is NEVER shown to Pesho as a number. He only sees consequences — ranges in statistics and AI questions along the way.

---

## 3. ONBOARDING — PATH SELECTION

AI asks: **"Will you transfer your stock from a file/program, or do you prefer we start and the system learns while you work?"**

### Path A: Transfer (CSV/Excel/program)
→ Import → confidence 60-90% → Zone Walk for physical confirmation

### Path B: The Lazy Way (Hidden Inventory)
→ Pesho sells from second 1 → system learns from sales + deliveries + zone walks
→ AI: "Perfect. First, let me learn your store layout."

### Path B — Zone Setup (mandatory for hidden inventory):

**Step 1: Two mandatory areas**

AI asks: "Every store has two parts — the customer area where products are displayed, and storage behind the scenes. Let's start with the customer area."

**Three zone types:**

| Zone | Description | Example sub-zones |
|---|---|---|
| 🟢 CUSTOMER ZONE | Everything displayed, visible to customers | "Left hangers", "Center display stand", "Window display", "Accessories table" |
| 🟡 SHELF ZONE | In the store but behind counter, under surfaces, reserve stock within reach | "Shelf behind register", "Under counter", "Top shelf storage" |
| 🔴 STORAGE ZONE | Back room, separate room, boxes (if exists) | "Back room — left shelf", "Back room — boxes on floor" |

AI asks: "Do you have a separate storage room or back area?" → If yes → 3 zones. If no → 2 zones.

**Step 2: Define sub-zones with MANDATORY photo**

For each zone, Pesho:
1. AI asks questions: "How many display areas do you have in the customer zone?", "What's on the left?", "What's on the right?"
2. Names each sub-zone by voice: "Left hangers near window"
3. Takes a MANDATORY photo of each sub-zone

The photo serves two purposes:
- **During inventory:** AI shows the photo → Pesho immediately knows which area
- **Future reference:** If layout changes → Pesho re-photographs → AI knows new reality

**Step 3: Confirmation**

AI: "Your store has 7 sub-zones: 4 customer, 1 shelf, 2 storage. We'll go through one per day — 5-10 minutes each. No rush."

---

## 4. DAY 1+: SELLING WITHOUT INVENTORY

### Rule: NEVER block a sale. Nothing is mandatory except the price.

**Three ways to sell (by priority):**

**1. Barcode (best case):**
Scan → system doesn't know it → "New product. Price?" → Pesho says "40 leva" → done. 3 seconds.

**2. Voice (primary):**
Pesho: "Nike 42 black, 40 leva" → AI parses → creates product → adds to cart → done. 5 seconds.

**3. Photo (offline fallback):**
One tap → photo → AI recognizes type → "Price?" → done.

### After the sale:
Product exists in the system with confidence 20-30%. No questions. No popups. Sale recorded, revenue is accurate.

### Negative stock = normal
Pesho sold 3 Nike shirts but never entered that he has them. System shows: stock = -3. Doesn't crash, doesn't block. Just means "sold 3 before stocking."

### Deduplication (AI decides, not Pesho):
- "Nike 42 черни" and "Найки 42 чрн" → AI matches at 85%+ → merges automatically
- Below 85% → asks ONCE: "Is this the same as Nike Air Max 42 Black?" → yes/no
- Remembers forever → never asks again

---

## 5. STATISTICS — ALWAYS VISIBLE, WITH RANGES

### Revenue (always accurate):
> "Today: 840€ from 12 sales"

Revenue is 100% accurate from day 1. Doesn't depend on confidence.

### Profit (range):
> "Profit today: 180€ – 340€"
> "± 160€ because 8 products don't have cost price"
> [Add in 2 min →]

Range calculation:
- Products WITH cost price → exact profit
- Products WITHOUT cost price → AI assumes 30-60% margin (from biz-coefficients for business_type)
- Lower bound = if margin is 30%
- Upper bound = if margin is 60%

### Visual narrowing:
```
Monday:    Profit: 180€ — 340€    [========............]
Tuesday:   Profit: 260€ — 340€    [============........]
Wednesday: Profit: 310€ — 340€    [================....]
Thursday:  Profit: 328€           [====================] ✓
```

The bar shrinks. The number focuses. Pesho feels progress without being told "complete the products".

---

## 6. DELIVERY — MASS CONFIDENCE BOOST + CATEGORY COUNT TRIGGER

### This is the MOST IMPORTANT moment for hidden inventory.

### Flow:

1. Pesho photographs the invoice
2. AI (Gemini OCR) extracts: products, quantities, cost prices, supplier, tax ID
3. AI matches with already existing products in the system
4. For matched: fills cost price + adds quantity → confidence jumps +40%
5. For new: creates them with 80% confidence (name + price + cost + quantity)
6. Pesho confirms with one tap

**One photo = mass confidence boost.** 20 products from 30% to 80% in 30 seconds.

### DELIVERY TRIGGERS CATEGORY COUNT

This is the natural bridge between deliveries and inventory:

A delivery arrives for category "Blouses" from supplier "Nike".

AI says: **"Nike blouses arrived. While you're at it — let's count ALL blouses everywhere. Should take 3 minutes."**

Pesho is already handling blouses. The mental context is there. He walks through:
- Customer zone → counts blouses on hangers
- Shelf zone → counts blouses behind counter
- Storage zone → counts blouses in boxes

**This is category-based counting triggered by a natural event (delivery).** Not arbitrary "today is blouse day."

### Negative stock resolution during delivery:

> "Nike 42 — you sold 3, but never stocked it. 20 arrived from delivery. Stock now: 17. Correct?"

Pesho says "yes" → confidence 80%.
Pesho says "no, I have 15" → corrects → confidence 100%.

---

## 7. ZONE WALK — FILLING THE GAPS

### What it is
Zone Walk covers everything that deliveries DON'T cover — dead stock, misplaced items, forgotten products.

### When it happens
- Between deliveries
- AI suggests: "We haven't checked the window display in 3 weeks. 5 minutes today?"
- Always optional, never forced

### How it works

AI shows the photo of the sub-zone: **"Today we're doing 'Left hangers near window'. Here's the photo. Stand in front and say Ready."**

Pesho scans EVERYTHING left to right, top to bottom. Doesn't think about categories — blouse, dress, jacket, doesn't matter. Beep, beep, beep.

For each scan:
- Known product → ✓ confirmed, confidence = 100%
- Unknown barcode → "New product! What's it called and how much?" → added
- No barcode → "Take a photo" → AI recognizes or Pesho says by voice

### Items already counted (from delivery)

If blouses were already counted during a delivery this week, AI knows:

> "The blouses here were counted on Tuesday. Still 12?" → Quick tap ✓

Pesho doesn't re-count what's already confirmed. Zone Walk just **fills the gaps**.

### Double counting = CROSS-VALIDATION (this is a GOOD thing)

If an item WAS counted during delivery (category-based) AND gets scanned during Zone Walk (zone-based):
- Numbers match → ✅ double confirmation, confidence = 100%
- Numbers DON'T match → 🔴 caught an error → AI: "You said 12 blouses on Tuesday, but I'm seeing 11 now. Which is correct?"

**Two independent counts catching each other's mistakes.**

### When Pesho says "Done" with a zone:

**1. Speed detection:**
45 items in 90 seconds? → "Wow, world record 😄 Sure you didn't miss anything?"

**2. "Reach behind":**
"Great. Now reach to the back of the shelf. Anything fallen, hidden, without a label back there?"

**3. Unconfirmed items list:**
AI shows items that should be in this zone but weren't scanned.

**4. Micro-Proof (no Honeypot — that's annoying):**
For each unconfirmed item, AI doesn't ask "Is it there?" (too easy to lie). Instead:
- "Read me the last 3 digits of the barcode" → forces physical contact
- "What size is on the label?" → forces picking it up
- "How many pieces do you see?" → forces counting

This is natural, not a test. Pesho doesn't feel tested — just confirming.

**5. Split-Location Ping:**
Pesho scanned 2 pieces on the shelf. System knows 8 exist (from delivery - sales).
→ "There are 2 on the shelf. Are the other 6 in the back room boxes?"
→ Automatically schedules those for storage zone walk.

---

## 8. DATA RECONCILIATION — AFTER ALL ZONES

When all zones have been walked:

AI: **"We've been through all zones. 8 products weren't found anywhere."**

For each: "Black blouse M, code BL-0412 — which zone is it in?"
- Pesho names a zone → but wasn't scanned there → red flag → "Go check right there"
- Pesho says "It's gone" → marked as missing → stock adjusted
- Pesho says "In storage" → AI: "We'll confirm when we do the storage zone"

### The 3 conditions for "Store is Clean":

**Condition 1:** Every zone has been walked (all sub-zones have a walk date)
**Condition 2:** Every product has an answer (scanned, confirmed with count, or marked missing)
**Condition 3:** Cross-validation passed (category counts ≈ zone counts, no speed flags, micro-proof answers OK)

If all 3 → 🟢 Store Health: 95%+
If 1+2 pass but 3 has flags → 🟡 "Counted, but AI isn't fully confident about 12 items. Quick check?"

---

## 9. SELF-CORRECTING SALES LOOP (runs forever)

The most powerful mechanism: **the system doesn't rely only on inventory. It self-checks every day through sales.**

### How it works:

Pesho said he has 5 black blouses. Sells 1 → system says 4. Sells another → 3. Another → 2. Another → 1. Another → 0.

**Oversell detection:**
If Pesho sells a 6th black blouse but system says 0:
> "Hmm, this one should have been out of stock. Looks like the last count was off. How many do you actually have?"

**Stale stock detection:**
If system says 5 but Pesho hasn't sold any in 60 days (normal pace = 2/month):
> "The black blouse M — haven't sold any in 2 months. Are you sure you still have 5?"

### Why it's powerful:
Lazy lies from inventory get exposed over time. Pesho can't lie forever — sales reveal the truth. And every correction improves data without a new inventory.

### Limitation:
Only works for items that sell. "Dead" stock (doesn't sell, sits on shelf) stays uncorrected. That's why → periodic mini-revisions (see below).

---

## 10. STORE HEALTH SCORE

We don't show "inventory %" — that's boring. We show **Store Health** — the health of the store:

### Components:

| Component | Weight | What it measures |
|---|---|---|
| Stock accuracy | 40% | % of products physically confirmed in last 30 days |
| Data freshness | 30% | Days since last zone walk per zone |
| AI confidence | 30% | Average confidence of all products |

### Visual:

🟢 **95-100** — "Store is in perfect shape. AI knows everything."
🟡 **80-94** — "Good, but AI is guessing about some things."
🟠 **60-79** — "AI is unsure. Advice may be inaccurate."
🔴 **< 60** — "AI is guessing. Core features limited."

### Direct link to value:

> "When Store Health is 95%+, AI can tell you exactly when to reorder and what's selling badly. Below 80% — it's guessing."

This is the key — **we don't push Pesho to count "because he should", but because AI becomes more useful with better data.**

---

## 11. CONFIDENCE DECAY + MINI-REVISIONS

### Decay:
Physical confirmation is valid for **30 days**. After that:
- 30+ days → confidence drops 5% per week
- 60+ days without confirmation → product is "stale"

### Mini-revisions (daily, 2 minutes):

AI picks 5-10 items and asks:
> "Quick check: Do you still have 3 of the white skirt? And the red bag?"

Targets:
- Most "stale" products
- Products with unusual sales patterns
- Products with previous discrepancies

### Full zone refresh:
After ~3 months, enough products are stale that Store Health drops to ~80%. AI suggests:
> "45 products haven't been seen in 2 months. Want to do a zone walk this week?"

Not mandatory. Just a suggestion when data is getting old.

---

## 12. MOTIVATION — "HUNTING FOR LOST MONEY"

### Core psychology:
We never say "inventory". We say **"hunting for lost money."**

> "Pesho, I think there's stock worth 400 leva hidden somewhere that we haven't sold in 6 months. Let's clean shelf 2 today."

When Pesho finds hidden/forgotten stock:
> "Bingo! Found a jacket worth 45 lv. Scan it, tomorrow we put it on sale to turn it into cash."

### Escalation (visual, NO sound):

| Store Health | AI behavior |
|---|---|
| 90%+ | 🟢 Gentle hint once a week |
| 70-89% | 🟡 Hint once a day |
| 50-69% | 🟠 Hint every time app opens |
| < 50% | 🔴 Banner with concrete value proposition |

But NEVER blocks. Pesho can ignore forever and the system still works.

### Concrete value, not abstract:

**Bad:** "You have 12 products without cost price."
**Good:** "Tell me the cost of these 5 Nike products and you'll learn you make 340€ more from Nike than Adidas per month."

**Bad:** "Do inventory."
**Good:** "Check only the dresses (3 min) → you'll know which sizes to reorder."

---

## 13. VARIATIONS

### Three states:

| Flag | Meaning |
|---|---|
| `has_variations: false` | Single product (1 SKU) |
| `has_variations: true` | Variations entered and tracked |
| `has_variations: unknown` | System doesn't know |

### When AI asks:
At EVERY quantity input for a product with `unknown`:
> "Nike Air Max — do they have sizes or colors?"

Pesho: "Yes, sizes 40 to 45" → `has_variations: true` → AI creates variations
Pesho: "No" → `has_variations: false` → never asks again

---

## 14. TIMELINE — TYPICAL STORE WITH 200 PRODUCTS

```
Day 1:       Sells, 0% confidence. Sees revenue + top products.
Day 2-3:     30 products from sales. Confidence: 20-30%.
Day 4:       First delivery → invoice photo → 40 products boosted.
             Delivery triggers category count → 40 more confirmed.
             Confidence: 50-60%.
Day 5-7:     AI asks along the way → +20 products. Confidence: 65%.
Day 7-10:    Zone Walk starts → 1 zone per day, fills gaps.
             Cross-validates with delivery counts.
Day 10-14:   More deliveries → more category counts.
             Zone Walk continues for uncovered zones.
Day 14:      All zones walked. Data Reconciliation.
             Store Health: 90%+
Day 15-30:   Self-correcting through sales.
             Mini-revisions daily (2 min).
             New deliveries → fresh category counts.
Day 30:      Store Health: 95%+. AI fully confident.
Month 2-3:   Maintenance mode. Confidence stays high.
             Periodic zone refresh when data gets stale.
```

**Without a single moment of "sit down and enter everything."**

---

## 15. TECHNICAL NOTES

### New fields:
```sql
products.confidence_score    TINYINT DEFAULT 0    -- 0-100
products.has_physical_count  BOOLEAN DEFAULT FALSE
products.last_counted_at     TIMESTAMP NULL       -- when physically confirmed
products.counted_via         ENUM('zone_walk','delivery','mini_revision','sale_correction') NULL
products.zone_id             INT NULL             -- which sub-zone
products.has_variations      ENUM('true','false','unknown') DEFAULT 'unknown'
products.variations_tracked  BOOLEAN DEFAULT FALSE
products.first_sold_at       TIMESTAMP NULL
products.first_delivered_at  TIMESTAMP NULL
```

### New tables:
```sql
CREATE TABLE store_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    zone_type ENUM('customer','shelf','storage') NOT NULL,
    photo_url VARCHAR(500) NULL,
    sort_order INT DEFAULT 0,
    last_walked_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE zone_walks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    zone_id INT NOT NULL,
    walked_by INT NOT NULL,              -- user_id
    products_scanned INT DEFAULT 0,
    products_confirmed INT DEFAULT 0,
    products_missing INT DEFAULT 0,
    products_new INT DEFAULT 0,
    duration_seconds INT NULL,
    speed_flag BOOLEAN DEFAULT FALSE,    -- suspiciously fast
    confidence_before TINYINT,
    confidence_after TINYINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE inventory_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    product_id INT NOT NULL,
    check_type ENUM('zone_walk','delivery_count','mini_revision','sale_correction') NOT NULL,
    zone_id INT NULL,
    quantity_found INT NOT NULL,
    quantity_expected INT NULL,
    discrepancy INT DEFAULT 0,
    verified_by ENUM('scan','micro_proof','voice_count','tap_confirm') NOT NULL,
    confidence_before TINYINT,
    confidence_after TINYINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Confidence calculation (PHP):
```php
function calcConfidence($product) {
    $score = 0;
    if ($product['name'] && $product['retail_price']) $score += 20;
    if ($product['barcode'] || $product['code']) $score += 10;
    if ($product['cost_price'] > 0) $score += 20;
    if ($product['category_id'] && $product['supplier_id']) $score += 10;
    if ($product['first_delivered_at']) $score += 20;
    if ($product['has_physical_count']) $score += 20;
    return min($score, 100);
}
```

### Confidence decay (cron, daily):
```php
function applyConfidenceDecay($tenant_id) {
    // Products not confirmed in 30+ days lose 5% per week
    DB::run("
        UPDATE products 
        SET confidence_score = GREATEST(confidence_score - 5, 20)
        WHERE tenant_id = ? 
          AND has_physical_count = 1
          AND last_counted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND DAYOFWEEK(NOW()) = 1  -- once per week on Monday
    ", [$tenant_id]);
}
```

### Store Health Score calculation:
```php
function storeHealth($tenant_id, $store_id) {
    // Stock accuracy (40%): % confirmed in last 30 days
    $total = DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1", [$tenant_id])->fetchColumn();
    $confirmed = DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND last_counted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$tenant_id])->fetchColumn();
    $accuracy = $total > 0 ? ($confirmed / $total) * 100 : 0;
    
    // Data freshness (30%): avg days since last zone walk
    $zones = DB::run("SELECT COUNT(*) AS total, AVG(DATEDIFF(NOW(), COALESCE(last_walked_at, created_at))) AS avg_days FROM store_zones WHERE tenant_id=? AND store_id=?", [$tenant_id, $store_id])->fetch();
    $freshness = max(0, 100 - ($zones['avg_days'] * 3)); // lose 3% per day
    
    // AI confidence (30%): average confidence
    $avg_conf = DB::run("SELECT AVG(confidence_score) FROM products WHERE tenant_id=? AND is_active=1", [$tenant_id])->fetchColumn() ?: 0;
    
    return round(($accuracy * 0.4) + ($freshness * 0.3) + ($avg_conf * 0.3));
}
```

### Speed detection:
```php
function checkWalkSpeed($zone_walk) {
    $seconds_per_item = $zone_walk['duration_seconds'] / max($zone_walk['products_scanned'], 1);
    // Less than 3 seconds per item = suspicious
    return $seconds_per_item < 3;
}
```

---

## 16. MODULES AFFECTED

| Module | How inventory affects it |
|---|---|
| **onboarding.php** | Path selection (transfer vs lazy), zone setup with photos, initial zone definitions |
| **sale.php** | Create product on-the-fly at confidence 20%, negative stock handling, oversell detection |
| **deliveries.php** | Invoice OCR, mass confidence boost, TRIGGERS category count for delivered category |
| **products.php** | Zone Walk UI, Store Health Score display, zone filter, inventory button on home screen |
| **chat.php** | AI triggers for mini-revisions, motivation messages, "hunting for lost money", zone walk prompts |
| **settings.php** | Zone management (add/edit/delete/reorder zones, update photos) |
| **build-prompt.php** | Include Store Health + stale products count + zone walk status in AI context |
| **cron jobs** | Confidence decay (weekly), Store Health recalculation (daily), stale product detection |

### RULE FOR EVERY SESSION:
**When working on ANY module listed above, read this document first. If any inventory logic applies to the current task, proactively suggest implementing it.**

---

## 17. TEN PRINCIPLES

1. **Never block a sale** — even without name, barcode, or anything
2. **Voice is primary** → photo is fallback → barcode is best case
3. **Stock is calculated** — deliveries minus sales, not a manual field
4. **Negative stock is normal** — means "sold before stocking"
5. **Statistics ALWAYS work** — with ranges, never hidden
6. **Ranges motivate** — "180-340€" makes Pesho want the exact number
7. **Delivery = mass boost** — one photo = 20 products + triggers category count
8. **Zone Walk fills gaps** — covers dead stock, misplaced items, forgotten products
9. **Double counting is cross-validation** — category count + zone count catching each other's errors
10. **The system works from second 1** — accuracy grows, it's not demanded

---

## 18. WHAT CANNOT BE SOLVED

Honest assessment — **100% guarantee is impossible** without RFID or third-party physical count.

1. **Determined Pesho who lies systematically** — can say "5" instead of "3" every time. Self-correcting loop catches it EVENTUALLY, not immediately.
2. **Physically hidden product** (in bottom of box, behind cabinet) — if Pesho can't see it, system can't find it. Fallback: Data Reconciliation shows "missing" products.
3. **Theft** — if an employee steals, neither Pesho nor system knows. But discrepancy shows at next revision.

**Realistic goal: not 100%, but 95-98% with a mechanism for constant self-improvement.** The remaining 2-5% gets caught over time through sales, mini-revisions, and repeat walks. This is sufficient — even large retail chains operate at 95-97% inventory accuracy.