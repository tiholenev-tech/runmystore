# 🏛 MASTER HANDOFF S147 → S148

> **От:** S147 шеф-чат (15-16.05.2026, Opus 4.7)
> **За:** S148 шеф-чат + Тих
> **Цел:** Систематизация на всичко свършено + 2-месечен roadmap до тестов продукт
> **Beta launch target:** 14-15.07.2026 (2 месеца от сега)
> **Тих's усещане:** "На 95-97% от крайния вариант като дизайни, UX и логики"
> **Какво остава:** обединение в реален продукт

═══════════════════════════════════════════════════════════════
📜 СЪДЪРЖАНИЕ
═══════════════════════════════════════════════════════════════

1. State of the world (16.05.2026)
2. Всичко завършено в S147
3. Текущо състояние на production (tenant_id=7)
4. КРИТИЧНИ ОТКРИТИ ВЪПРОСИ (12 общо)
5. 2-месечен roadmap до тестов продукт
6. ПЪРВА ЗАДАЧА за S148 (mockup-и Simple home + products + chat)
7. Стратегия за модули (poред на изграждане)
8. Какви документи има в repo + role на всеки
9. Чатове ecosystem (кой чат какво прави)
10. Финални препоръки

═══════════════════════════════════════════════════════════════
1. STATE OF THE WORLD (16.05.2026)
═══════════════════════════════════════════════════════════════

### Какво има днес:

**✅ Готови deliverables в repo:**
- 3 wizard mockup-а (INTERACTIVE, matrix_fullscreen, multi_photo_flow)
- 4 handoff документа за следващи чатове
- Stress система работеща на tenant_id=7 (3000 продукта, 22K sales, 13 insights)
- SIMPLE_MODE_NEW_v1.md — нова визия (AI разговор-driven)
- FACT_TENANT_7.md — окончателен запис

**❌ НЕ е имплементирано в production:**
- Wizard v6 в products.php (само mockup)
- AI vision/markup endpoints
- 4 нови DB колони в products
- Conversation engine
- Нова life-board.php layout
- Mockup-и за други модули (deliveries, orders, inventory)

**⚠️ Висящи технически дългове:**
- Production tree `/var/www/runmystore/` не updated (root-owned files)
- Confidence_score колоната не попълнена → Simple home показва "2369 Празни"
- Cron-овете не инсталирани в `/etc/cron.d/`
- 22 stress сценария със stale schema queries (out of scope)
- products.price schema mismatch в sales action simulator

═══════════════════════════════════════════════════════════════
2. ВСИЧКО ЗАВЪРШЕНО В S147 (15-16.05.2026)
═══════════════════════════════════════════════════════════════

### A. Wizard v6 mockup refinement (12 commits)
1. ✅ `wizard_v6_INTERACTIVE.html` — visual overhaul: aurora intensified, sacred glass spans, neumorphic buttons, flow корекции (Section 1 single order, Section 3 no-photo)
2. ✅ `wizard_v6_matrix_fullscreen.html` — отделен fullscreen matrix screen
3. ✅ `wizard_v6_multi_photo_flow.html` — 3-frame flow (capture → AI detect → result)
4. ✅ Matrix в Section 2 = копира fullscreen дизайна, тъмни цифри в light mode
5. ✅ Sacred glass CSS 1:1 от §5.4 (z-index vars + webkit prefix)

### B. Handoff документи (push-нати)
1. ✅ `WIZARD_v6_IMPLEMENTATION_HANDOFF.md` (1151 реда, 16 секции) — пълен handoff за wizard имплементация
2. ✅ `WIZARD_v6_BOOT_TEST.md` (15 въпроса + 3 trap-а + sycophancy)
3. ✅ `S148_NEW_CHAT_PROMPT.md` (entry prompt за wizard)
4. ✅ `S148_STRESS_TEST_PROMPT.md` (stress resurrection prompt)
5. ✅ `S148_CC_STRESS_PROMPT.md` (CC stress prompt — изпълнен снощи)
6. ✅ `FACT_TENANT_7.md` (окончателно решение за tenant_id=7)

### C. Stress система съживена (CC tonight)
1. ✅ HARD GUARD махнат от `_db.py`
2. ✅ Wipe + reseed tenant_id=7
3. ✅ 5 P0 bug-а fix-нати (J1-J6)
4. ✅ Rich seed: 3000 products (15 brands, 12 colors, 7 categories, gender/season distribution)
5. ✅ History seed: 22189 sales × 27371 stock_movements × 170 deliveries × 90 дни
6. ✅ Schema migration: ai_snapshots table CREATE
7. ✅ 16/16 product signals покрити с representative data
8. ✅ 13 active AI insights (6/6 fundamental_questions: loss, loss_cause, gain, gain_cause, order, anti_order)
9. ✅ Daily report writer + resume script
10. ✅ `STRESS_RICH_SEED_HANDOFF.md` документиран в repo

### D. Стратегически документи
1. ✅ `SIMPLE_MODE_NEW_v1.md` — нова визия за Лесен режим (899 реда, 20 секции)

═══════════════════════════════════════════════════════════════
3. ТЕКУЩО СЪСТОЯНИЕ НА PRODUCTION (tenant_id=7)
═══════════════════════════════════════════════════════════════

### DB на droplet 164.90.217.120, runmystore DB, tenant_id=7:

| Метрика | Брой | Източник |
|---|---|---|
| Products | 3000 (3031 - 31 duplicates) | seed_products_rich.py |
| Stores | 8 | seed_stores.py |
| Suppliers | 11 | seed_suppliers.py |
| Users | 5 | seed_users.py |
| Sales | 22,189 | seed_history_rich.py |
| Sale items | 22,189 | seed_history_rich.py |
| Stock movements | 27,371 | seed_history_rich.py |
| Deliveries | 170 | seed_history_rich.py |
| Delivery items | 2,282 | seed_history_rich.py |
| Inventory rows | 3,100 | computed |
| Negative inventory | 0 | ✅ |
| Out of stock | 662 | trigger zero_stock signal |
| AI insights active | 13 | compute-insights.php |

### Signal distribution (products.php pills):
- zero_stock: 838 / critical_low: 215 / below_min: 100 / out_total: 662
- at_loss: 108 (€2095 загуби) / low_margin: 258
- aging: 206 / zombie: 455 (€552,117 замразени!) / slow_mover: 350
- top_sales: 208 / top_profit: top 10 / new_week: 86
- no_barcode: 441 / no_cost: 150 / no_photo: 885 / no_supplier: 91

### AI insights distribution:
- 🔴 **loss (3):** zero_stock_with_sales (100p), below_min_urgent (100p), running_out_today (3p)
- 🔴 **loss_cause (3):** selling_at_loss (100p), margin_below_15 (100p), no_cost_price (50p)
- 🟢 **gain (2):** top_profit_30d (10p), profit_growth (10p)
- 🟢 **gain_cause (2):** highest_margin (10p), trending_up (10p)
- 🟠 **order (1):** bestseller_low_stock (50p)
- ⚪ **anti_order (2):** zombie_45d (100p), declining_trend (20p)

### Production tree status:
- `/var/www/rms-stress/` (worktree CC) = up-to-date с origin/main
- `/var/www/runmystore/` (production) = stale, root-owned files блокират `git pull`
- Тих trябва: `sudo git -C /var/www/runmystore pull origin main`

### Cron status:
- ❌ Crons НЕ инсталирани в `/etc/cron.d/`
- ✅ Manual nightly run работи (3150ms, run_id=1)
- ✅ daily_report_writer.py готов
- ✅ stress_resume.sh готов

═══════════════════════════════════════════════════════════════
4. КРИТИЧНИ ОТКРИТИ ВЪПРОСИ (12 общо)
═══════════════════════════════════════════════════════════════

### A. От SIMPLE_MODE_NEW_v1.md §17 (6 въпроса):

**Q1: AI chat card позиция в life-board.php**
- Горе ли? Долу ли? Sticky?
- Какво се случва при scroll?
- Решение: нуждае от mockup.

**Q2: AI Brain pill — отпада ли напълно?**
- Нов модел = AI говори първи → AI Brain pill може да е redundant
- Но: какво ако Пешо иска да зададе въпрос ad-hoc?
- Решение: запази pill като "ask anything" entry point ИЛИ изхвърли изцяло?

**Q3: Toggle позициониране в модулите**
- В header? Долу? Sticky FAB?
- Mode lock за служители (Пешо не може да toggle-не на Detailed)

**Q4: chat.php двойствеността от S144**
- Сега chat.php = Detailed home. Но AI разговор logic също може да живее там.
- Дали chat.php запазва Detailed home, или придобива нова role?

**Q5: Какво се случва когато няма ai_brain_queue items?**
- AI казва "Нямам нищо за теб днес. Иди си почини"?
- Или показва empty state с "Добави нов артикул"?
- Или fallback към lb-cards (старо поведение)?

**Q6: AI tone evolution (от BIBLE §11)**
- Tон #1 (новак): "Не разбирам някои неща, но ще се опитам"
- Тон #4 (експерт): "Знам всичко за магазина ти"
- Кога/как се променя tone-ът? По времетраене? По брой actions?

### B. От моите въпроси (6 нови):

**Q7: Тествал ли си концепцията със истински Пешо?**
- Не Иван (ENI), не Митко — реален 55-год продавач
- Без реален user test → правим си илюзия
- Препоръка: 2-3 интервюта преди да правим 3-седмична имплементация

**Q8: "Имам 8 неща" — диференциация между дни?**
- Понеделник 8 / Вторник 5 / Сряда 12 — нормално variance
- Но всеки ден винаги 8 → ще омръзне
- Решение: spec за "поне 3, най-много 10" + цикличност на signal видове?

**Q9: Voice + buttons "винаги паралелно" — CSS challenge?**
- 375px viewport + voice button + 4 action buttons + AI text + прогрес + toggle = много
- Тест: дали наистина се събира с глас icons + 2-3 action бутона + прогрес
- Препоръка: prototype на mockup първо

**Q10: AI tone не е разписан**
- §17 Q6 призна това
- Без conkretно tone spec → AI ще звучи "като ChatGPT" → не trustworthy за БГ магазинер
- Препоръка: 10 примера на сцени с точни phrase-и преди имплементация

**Q11: Beta timing — 3 седмици забавяне приемливо?**
- Beta target: 14-15.07.2026 (2 месеца)
- Conversation engine = ~3 седмици
- Wizard "Добави артикул" = ~3-4 дни
- Просто математика: има време ако започнем веднага

**Q12: Conversation engine = голяма повърхност за провал**
- Текущ AI Brain pill = малка повърхност (1 voice prompt)
- Нов engine = много prompts, много flow, много fail points
- Препоръка: post-beta v2.0, не pre-beta

═══════════════════════════════════════════════════════════════
5. 2-МЕСЕЧЕН ROADMAP ДО ТЕСТОВ ПРОДУКТ
═══════════════════════════════════════════════════════════════

**Beta launch target: 14-15.07.2026**
**Днес: 16.05.2026 → 60 дни оставаха**

### СЕДМИЦА 1 (17-23.05) — Wizard "Добави артикул"
- **Понеделник:** Open Questions Q1-Q12 → Тих + S148 chat обсъждат, взимат решения
- **Вторник:** Mockup-и за life-board.php (Simple home AI conversation-driven) + chat.php (Detailed home refresh)
- **Сряда:** Mockup за products.php Simple mode (list view с filter drawer)
- **Четвъртък-Петък:** S148 започва wizard implementation Фаза 1 (sacred glass CSS migration)

### СЕДМИЦА 2 (24-30.05) — Wizard завършване
- Фаза 2: DB migration + AI endpoints (ai-vision.php, ai-markup.php)
- Фаза 3: Wizard HTML restructure (4-те sub-pages → 4 акордеона)
- Фаза 4: Multi-photo + Matrix fullscreen integration
- Фаза 5: Integration testing на tenant_id=7

### СЕДМИЦА 3 (31.05-06.06) — Mockup-и за всички модули
- products.php Simple mode + Detailed mode (list view, signal filter, search)
- deliveries.php Simple + Detailed mode (mockup)
- orders.php Simple + Detailed mode (mockup)
- inventory.php Simple + Detailed mode (mockup)
- Mockup-и са **ground truth** — всеки модул следва тях

### СЕДМИЦА 4 (07-13.06) — products.php Simple mode (имплементация)
- list view с AI conversation pattern (ако решим conversation engine)
- ИЛИ запази lb-cards pattern (ако решим post-beta)
- Filter drawer (per S144)
- Quick filter pills работят
- Signal cards с action buttons

### СЕДМИЦА 5 (14-20.06) — deliveries.php имплементация
- DB schema (deliveries, delivery_items, transfers, supplier_orders)
- Voice flow за приемане доставка
- Печат на bar codes (DTM-5811 — sacred)
- Stress тестове S010 (доставки) активирани

### СЕДМИЦА 6 (21-27.06) — orders.php имплементация
- Supplier orders flow
- Auto-order от lost_demand
- Stress тестове S012 активирани

### СЕДМИЦА 7 (28.06-04.07) — inventory.php + полиране
- Inventory check flow (Voice + barcode scan)
- Transfers между магазини (sacred — DTM-5811)
- Stress тестове S011 активирани

### СЕДМИЦА 8 (05-11.07) — Pre-beta testing
- Manual testing на tenant_id=7
- Bug bash
- 22 stress scenarios stale schema queries — fix-нати
- Performance benchmark
- Beta acceptance checklist (30 checks)

### СЕДМИЦА 9 (12-15.07) — Beta launch
- 14-15.07: ENI клиент onboarding (нов tenant_id)
- Тих наблюдава първи 3 дни активно
- Daily standup със ENI feedback

═══════════════════════════════════════════════════════════════
6. ПЪРВА ЗАДАЧА ЗА S148 — НОВИ MOCKUP-И
═══════════════════════════════════════════════════════════════

**Тих's request:** "Първа задача би трябвало да е лесна да направим нови mockup-и на основните страници Лесен режим на чат и на артикули."

### Mockup-и нужни (S148 чат прави):

**1. life-board.php Simple mode (нов AI conversation-driven layout):**
- AI говори първи: "Здрасти Пешо. Имам 8 неща за днес. Искаш ли да чуеш?"
- Прогрес "1 от 8" компонент
- Voice + buttons паралелно
- Inline action buttons за всеки signal
- Empty state когато няма ai_brain_queue items
- Trigger Open Questions Q1, Q2, Q5

**2. chat.php Detailed mode (refresh):**
- Запази текущ layout (Detailed home работи)
- Може само visual polish (sacred glass на повече cards)
- Резулт от Open Question Q4

**3. products.php Simple mode + Detailed mode:**
- Simple: AI says "Имам 16 неща за внимание в стоката ти"
- Detailed: signal pills (16 types) + filter drawer + list view
- Quick filters работят
- Search bar

### Структура на mockup-ите:
- Path: `mockups/lifeboard_v2_simple.html`, `mockups/lifeboard_v2_detailed.html`, `mockups/products_v2_simple.html`, `mockups/products_v2_detailed.html`
- Pattern same as wizard_v6: aurora + sacred glass + 4 spans + neumorphic depth
- Interactive demo bar за state switching
- Theme toggle dark/light

### Acceptance criteria:
- Тих отваря на mobile (375px) → визуално перфектен
- Sacred glass на всички cards (purple/violet shine)
- Buttons depth (neumorphic gradients)
- Voice button винаги достъпен
- За Simple: AI разговор pattern ясен
- За Detailed: всички 16 signal types + 4 quick filters рендерират

═══════════════════════════════════════════════════════════════
7. СТРАТЕГИЯ ЗА МОДУЛИ (ред на изграждане)
═══════════════════════════════════════════════════════════════

### Принцип: "Без модул → без stress test за него"

| # | Модул | Файл | Зависимости | Mockup първо | После имплементация |
|---|---|---|---|---|---|
| 1 | Wizard "Добави артикул" | products.php (+ нов wizard) | ✅ mockup-и готови | ✅ ДА | Седмица 1-2 |
| 2 | products.php Simple mode | products.php?mode=simple | mockup-и седмица 1 | ✅ ДА | Седмица 3-4 |
| 3 | products.php Detailed mode | products.php (default) | работи в production вече | ❌ Refresh само | Седмица 4 (polish) |
| 4 | deliveries.php | deliveries.php | DB schema нужна | ✅ ДА | Седмица 5 |
| 5 | orders.php | orders.php | deliveries + lost_demand | ✅ ДА | Седмица 6 |
| 6 | inventory.php | inventory.php | products + stock_movements | ✅ ДА | Седмица 7 |
| 7 | sale.php | sale.php | exists, sacred за wizard | ❌ Не пипа | (изключение) |
| 8 | chat.php | chat.php | работи в production | ❌ Refresh само | Седмица 4 |
| 9 | life-board.php | life-board.php | нов layout | ✅ ДА | Зависи от conversation решение |

### Решение за conversation engine:

**Опция A — Pre-beta (3 седмици забавяне):**
- Включваме conversation engine
- Тих → ENI с unique pattern
- Риск: ако engine fail-не = beta delay

**Опция B — Post-beta (препоръчвам):**
- Beta върви с текущ AI Brain pill pattern
- conversation engine = v2.0 release (август 2026)
- Сigурно beta launch

═══════════════════════════════════════════════════════════════
8. ВСИЧКИ ДОКУМЕНТИ В REPO (16.05.2026)
═══════════════════════════════════════════════════════════════

### Wizard "Добави артикул" (готови):
- `WIZARD_DOBAVI_ARTIKUL_v5_SPEC.md` — пълна продуктова spec (S145)
- `WIZARD_v6_IMPLEMENTATION_HANDOFF.md` — handoff за S148 (S147)
- `WIZARD_v6_BOOT_TEST.md` — boot test 15 въпроса (S147)
- `S148_NEW_CHAT_PROMPT.md` — entry prompt (S147)
- `mockups/wizard_v6_INTERACTIVE.html` — главен mockup
- `mockups/wizard_v6_matrix_fullscreen.html` — matrix отделен
- `mockups/wizard_v6_multi_photo_flow.html` — 3-кадри flow

### Simple mode визия (нова):
- `SIMPLE_MODE_NEW_v1.md` — нова визия (16.05.2026, NEW)
- `SIMPLE_MODE_BIBLE.md` (v1.3) — стара визия, заменена частично
- `BIBLE_v3_0_CORE.md` — Закон №1, №3, №6, №9
- `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` — §5.4 Sacred Neon Glass

### Stress система (CC tonight):
- `STRESS_COMPASS.md` — стара (с warning), tenant_id=7 правилото OUTDATED
- `STRESS_SCENARIOS.md` — 75 сценария (S001-S075)
- `STRESS_TENANT_SEED.md` — 8 stores, 11 suppliers
- `STRESS_FINALIZE_HANDOFF.md` (tools/stress/) — S133 P0 issues (решени)
- `STRESS_RICH_SEED_HANDOFF.md` — coverage report от CC tonight
- `STRESS_DAILY_REPORT.md` — daily report (writer pусна се)
- `FACT_TENANT_7.md` — окончателно решение
- `tools/stress/` — Python infrastructure (seeders, alerts, perf, beta_acceptance)
- `tools/stress_resume.sh` — resume script
- `tools/stress/daily_report_writer.py` — daily report generator

### AI стратегия:
- `AUTO_PRICING_DESIGN_LOGIC.md` — markup formulas + confidence routing
- `docs/AI_AUTOFILL_SOURCE_OF_TRUTH.md` — Gemini schema + 2-level cache
- `S148_STRESS_TEST_PROMPT.md` — за нов чат stress
- `S148_CC_STRESS_PROMPT.md` — за CC stress (изпълнено)

### Архитектурни:
- `MASTER_COMPASS.md` — Standing Rules (Rule #38, etc.)
- `BIBLE_v3_0_CORE.md` — 9 закона
- `BIBLE_v3_0_TECH.md` — technical bible
- `TOMORROW_WIZARD_REDESIGN.md` — S143 DB columns plan
- `PREBETA_MASTER_v2.md` — beta launch checklist

═══════════════════════════════════════════════════════════════
9. ЧАТОВЕ ECOSYSTEM
═══════════════════════════════════════════════════════════════

### S148 шеф-чат (главен):
- **Какво прави:** координира всичко, mockup-и, малки fixes, разговаря с Тих
- **Boot:** WIZARD_v6_BOOT_TEST.md + SIMPLE_MODE_NEW_v1.md + този handoff
- **Първа задача:** mockup-и (life-board, chat, products в Simple + Detailed)
- **Чете:** repo през GitHub bootstrap

### Claude Code (CC) на droplet:
- **Какво прави:** изпълнение на droplet (DB queries, file deploys, stress runs)
- **Disabled когато:** Тих няма доверие → използваме шеф-чат вместо
- **Active в момента:** stress система работи, daily reports

### Шеф-чат за wizard implementation (евентуално отделен):
- **Какво прави:** само wizard "Добави артикул" в products.php
- **Boot:** WIZARD_v6_IMPLEMENTATION_HANDOFF.md
- **Може да е отделен чат:** ако главният S148 е претоварен

### Не ползваме:
- Claude Code за wizard implementation (per Тих "проваля всичко")
- Други AI-та (ChatGPT, Gemini) — не познават проекта

═══════════════════════════════════════════════════════════════
10. ФИНАЛНИ ПРЕПОРЪКИ
═══════════════════════════════════════════════════════════════

### За Тих (утре):

**1. Прочети SIMPLE_MODE_NEW_v1.md спокойно.**
Той е твой документ от днес. Решения вътре в него ще определят 3 седмици работа.

**2. Реши за conversation engine: pre-beta или post-beta?**
- Pre-beta = innovation risk
- Post-beta = safer launch + v2.0 differentiation
- Моята препоръка: **post-beta**

**3. Отговори на 6-те Open Questions от §17.**
- Q1: AI chat card позиция
- Q2: AI Brain pill — отпада?
- Q3: Toggle позициониране
- Q4: chat.php role
- Q5: Empty state
- Q6: AI tone evolution

**4. Тествай с истински Пешо.**
Един реален 55-год продавач = 100 теоретични дискусии.

**5. Отвори tenant_id=7 в браузър.**
- `runmystore.ai/products-v2.php?tenant_id=7`
- `runmystore.ai/home.php?tenant_id=7`
- `runmystore.ai/chat.php?tenant_id=7`
- Виж дали 16-те signal pills рендерират със numbers

### За S148 чат (boot):

**1. ПЪРВО прочети:**
- `FACT_TENANT_7.md` (1 минута)
- Този handoff (`MASTER_HANDOFF_S147_S148.md`)
- `SIMPLE_MODE_NEW_v1.md` (10-15 минути)

**2. Boot test (15 въпроса от WIZARD_v6_BOOT_TEST.md):**
- 14/15 + 3 trap-ове честно + sycophancy защита

**3. Открий с Тих Open Questions Q1-Q12.**
Не започвай mockup-и преди да има решения.

**4. След решения — направи 3 mockup-а (Седмица 1 task):**
- `mockups/lifeboard_v2_simple.html`
- `mockups/products_v2_simple.html`
- `mockups/products_v2_detailed.html`

**5. Не пипа sacred zones.**
Виж списъка в WIZARD_v6_IMPLEMENTATION_HANDOFF.md §2.

═══════════════════════════════════════════════════════════════
11. NUMBERS REALITY CHECK
═══════════════════════════════════════════════════════════════

### Time budget:
- 60 дни до beta
- 5 работни дни/седмица × 8 седмици = 40 работни дни
- Buffer: 20 дни (1/3 от total)

### Tasks budget:
- Wizard implementation: ~10 дни (4 фази × 2-3 дни)
- 4 модула mockup-и: ~8 дни (2 дни всеки)
- 4 модула implementation: ~16 дни (4 дни всеки)
- Testing + polishing: ~6 дни
- **Total: ~40 дни** (точно покрива budget-а)

### Risk factors:
- Conversation engine pre-beta = +15 дни → буффер изчезва
- Open Questions неразрешени = блокират имплементация
- Sacred zone счупване = +5 дни recovery
- ENI клиент променя scope = +10 дни

### Препоръка:
**Заключи scope-а до 23.05.2026.** След това → frozen, само imp работа.

═══════════════════════════════════════════════════════════════
12. КРАЙНО РЕЗЮМЕ ЗА ТИХ
═══════════════════════════════════════════════════════════════

> **Ти си на 95-97% като дизайн/UX/логика — потвърждавам.**
>
> **Какво остава:**
> 1. 12 решения (Open Questions) — 1 ден разговор
> 2. 4 нови mockup-а (Simple + Detailed за всеки модул) — 1 седмица
> 3. Wizard "Добави артикул" implementation — 2 седмици
> 4. 4 модула implementation — 4 седмици
> 5. Pre-beta testing — 1 седмица
> 6. Beta launch — 14-15.07.2026
>
> **Total: 60 дни. Точно толкова имаш.**
>
> **Препоръка #1:** Post-beta conversation engine. Beta = текущ AI Brain pattern.
> **Препоръка #2:** Тествай с реален Пешо преди да заковеш Simple mode визия.
> **Препоръка #3:** Заключи scope-а до 23.05.2026 → frozen → impленtация.
>
> **Stress система = работи. Започни всеки ден с STRESS_DAILY_REPORT.md.**

═══════════════════════════════════════════════════════════════

**КРАЙ. S147 chat downloads.**

> Когато S148 започне → boot test → разговор за Open Questions → mockup-и Седмица 1.
