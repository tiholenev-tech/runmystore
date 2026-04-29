# DELIVERY + ORDERS — DECISIONS LOG (ФИНАЛЕН)

**След тройна проверка:** ПАС 1 (110 решения) + ПАС 2 (14 пропуска + 9 корекции) = **124 финални решения**

Този документ е готов за следващия chat (имплементационен) — той ще преобразува всяко решение в DB schema, code, миграции.

---

## A. ЗАКОНИ И ПРИНЦИПИ

**A1.** Закон №10 — „Пешо не знае функциите ни." Системата активно пита в момента на употреба. Никога не очаква Пешо да знае функция предварително. Прилага се навсякъде.

**A2.** Закон №11 — „Разделение архитектура от имплементация." Архитектурен chat решения. Имплементационен chat код. Финален chat обединение. Чатовете се подсещат един друг при това разделение.

**A3.** orders.php и delivery.php се правят едновременно (не S90→S91). Двете са страни на една операция.

**A4.** orders.php започва с 1-2 дни преднина — delivery зависи от orders schema (не обратно).

**A5.** Reconciliation е инструмент за откриване на грешки post-hoc, НЕ anti-fraud срещу крадец. Тонът никога не е обвинителен.

**A6.** Discovery през chat (приложение на Закон №10): когато Пешо пита нещо, AI проактивно предлага функции които не знае че съществуват. Пример: „колко платих на Marina" → AI: „€450. Между другото имаш 23 дефектни за връщане."

---

## B. VOICE СТРАТЕГИЯ

**B1.** Whisper via Groq навсякъде, всички планове, без диференциация. Web Speech API премахната.

**B2.** Cost: ~€0.40/месец на активен PRO клиент (включва chat queries).

**B3.** Whisper интеграция — prerequisite. Estimate 5-6 часа. Прави се преди или паралелно с orders/delivery.

**B4.** Едно решение за всички езици — BG, RO, GR, HR, RS — готово за expansion.

**B5.** Visual confirmation на transcript винаги (Закон №1A). Никога silent action.

**B6.** Synonym table учене: при потвърден mismatch („мариса" → „Marina") → INSERT във voice_synonyms (created_by='user_correction'). Self-improving система.

---

## C. PRICING

**C1.** PRO е €49 (НЕ €59).

**C2.** Auto-pricing learning: AI учи multiplier и ending pattern per category.

**C3.** Първа доставка → онбординг 2 въпроса (multiplier + ending).

**C4.** Pattern per category, не глобално.

**C5.** Pattern се учи от 3 източника: онбординг, ръчни корекции, sales velocity (зацикля → подценен/надценен) — feedback loop.

**C6.** Auto-pricing routing: > 0.85 confidence auto-apply, 0.5-0.85 confirm dialog, < 0.5 manual.

**C7.** Bestseller винаги питаме (рисково). Bestseller = > 5 продажби/седмица за последните 4 седмици.

**C8.** AI предлага САМО retail. Cost идва от фактурата.

**C9.** Cost variance auto-update правило:
- < 10% → tih insight, никакъв action
- 10-20% + confidence > 0.85 → auto-update retail
- > 20% или bestseller → ВИНАГИ confirm dialog

---

## D. 4 СЦЕНАРИЯ НА РАЗЛИКА

**D1.** Сценарий 1 — по-малко от поръчка (50 → 47): dialog [Не, провери] [Да, прие]. Audit trail в delivery_events.

**D2.** Сценарий 2 — повече от поръчка (48 → 52): dialog [Не, върни ги] [Да, прие всичко].

**D3.** Сценарий 3 — повече от фактура (фактура 50 → преброи 60): dialog с 3 бутона [Не върни 10] [Обади се] [Прие всичките]. Третия бутон → под-dialog „бонус или плащаш?".

**D4.** Сценарий 4 — по-малко от фактура (50 → 46): огледален [Провери пак] [Обади се] [Приемам]. „Приемам" → has_unreceived_paid=1.

**D5.** Тон: жълт (не червен), сухо. „Поръчал си 50. Дошли 42. Сигурен ли си?"

**D6.** AI обяснява в момента на действието какво значи всеки избор.

---

## E. DEFECTIVE ITEMS

**E1.** AI активно пита в края на review: „Има ли нещо счупено, скъсано или дефектно?"

**E2.** Defective items НЕ влизат в inventory. Отиват в `supplier_defectives` (нова таблица).

**E3.** Натрупват се срещу supplier. AI Brain insight автоматичен при праг (20+ бройки или €50).

**E4.** Бутони в Detailed Mode supplier секция: [Върни всички към Marina], [Отпиши като загуба].

**E5.** Финансова връзка: при плащане на фактура AI пита „имаш €68 кредит — приспадаме ли?"

**E6.** Proactive напомняне 2 момента:
- При нова поръчка → „Да добавя връщане в съобщението?"
- При нова доставка → „Не забравяй 23-те за връщане."

**E7.** Chat въпроси: „Имам ли дефектни?" → отговаря от supplier_defectives.

---

## F. DUPLICATE DETECTION (GLOBAL)

**F1.** Засяга: deliveries, payments/finance, transfers, sales — глобален helper.

**F2.** Слой 1 (точно дублиране): supplier + invoice_number → hard block.

**F3.** Слой 2 (съмнително): различни invoice numbers, еднакви артикули + бройки + цени в 24-48ч → soft warning.

**F4.** За sales специфично: 1-2 артикула не предупреждаваме, 3+ артикула в 5 минути → soft warning.

**F5.** UI lock на бутона „Продай" — disable за 1-2 секунди след tap.

**F6.** Хеш на content_signature за бърза проверка през индекс.

---

## G. ДРУГИ DELIVERY СЦЕНАРИИ

**G1.** Частична доставка на поръчка: системата автоматично match-ва. AI пита „това ли са оставащите от поръчката от 12.04?"

**G2.** Доставка без поръчка: НИКАКЪВ dialog. Reconciliation срещу поръчка skip-ва, срещу фактура работи нормално.

**G3.** Грешен доставчик при snap: soft warning ако supplier name от OCR ≠ избран.

**G4.** Multi-supplier фактура: не е специален случай. Един дистрибутор, множество brand-ове.

**G5.** Бонус/мостри: при сценарий 3 третия бутон → под-dialog „бонус или плащаш?". „Бонус" → cost €0, is_bonus=1.

**G6.** Връщане на доставка: пълно изтриване лесно. Частично — checkbox list, edit qty per row, generate return slip, нови stock_movements тип `return_to_supplier`. Оригиналният запис остава за audit.

**G7.** Cost variance без разлика в бройки: tih insight (НЕ блокиращ), праг ≥ 10%.

**G8.** Reorder след връщане: ако след връщане продуктът падне под min_quantity → orders.php trigger.

**G9.** Volume discount detected: ако `unit_cost` < исторически avg -5% → insight „Marina ти даде по-добра цена."

---

## H. VOICE + STT СПЕЦИФИЧНО

**H1.** Whisper навсякъде (виж B).

**H2.** Voice диктовка с грешки → Закон №1A визуално потвърждение винаги.

**H3.** Synonyms table — „мариса" → „Marina" auto-corrected.

**H4.** Cost диктовка: „четири и двадесет" → 4.20 с Whisper естествено.

**H5.** Single supplier per voice session. Multi-supplier диктовка → split на два delivery записа.

**H6.** При забравен supplier → AI пита „От кого получи?" преди да продължи.

---

## I. OCR PIPELINE

**I1.** OCR pipeline вече описан в BIBLE_v3_0_TECH §13. Confidence-based routing.

**I2.** Override 1: Multi-page фактура — auto-stitch на всички страници. AI разбира кога фактурата свършва по „Общо/Total".

**I3.** Override 2: Confidence праговете per тип фактура:
- Чиста: > 0.92 auto, 0.75-0.92 smart UI, < 0.75 reject
- Полу-чиста: > 0.92 auto на parent ред НО задължителен matrix flow за вариациите. Auto-pass правилото игнорирано за вариационни редове.
- Ръкописна: < 0.5 → directly voice fallback (не reject error)

**I4.** Override 3: Ръкописна (Тип 3) с confidence < 0.5 → voice диктовка fallback.

**I5.** Supplier templates са goldmine — повтарящ доставчик получава confidence boost от cached fields. Втора доставка от Marina = почти zero friction.

**I6.** Системата сама определя кой тип е фактурата (1, 2, 3) на базата на:
- > 0.85 confidence + всички редове атомарни → Тип 1
- > 0.85 confidence + един или повече редове `has_variations=true` без описание → Тип 2
- < 0.5 confidence ИЛИ null → Тип 3

**I7.** Pipeline-ът извлича JSON. Delivery-specific UX логика (зелено/жълто/червено) е надстройка над pipeline-а.

---

## J. VARIATION HANDLING

**J1.** При първа доставка на нов вариационен продукт — AI прави 2 проверки:
1. Lookup в `$BIZ_VARIANTS` за бизнес типа на магазина
2. Pattern matching по името

**J2.** Кога AI пита Пешо:
- Двете показват вариации → DIRECT въпрос „с цветове и размери?"
- Едно несигурно → SOFT confirm „обикновено имат, така ли?"
- Двете отричат → auto-create без питане, has_variations='false'
- Двете несигурни → широк въпрос „вариационен ли е?"

**J3.** Defensive default: при всяко съмнение → has_variations='unknown', AI пита при първата продажба.

**J4.** Reuse на matrix overlay от products wizard (PRODUCTS_DESIGN_LOGIC §3.5).

**J5.** Voice flow за axes: AI пита „какви цветове?" → diktovka → „какви размери?" → diktovka → matrix отваря се.

**J6.** Sum check: сборът от matrix трябва = бройката на агрегирания ред. Ако не → AI пита да коригира.

**J7.** Вариационен продукт = един cost (€3 на потник), една предложена retail. Опция за per-variation цена.

---

## K. UX 3-те ТИПА ФАКТУРА (SIMPLE MODE)

**K1.** Entry screen: [📷 Снимай фактурата] primary, [🎤 Кажи какво получи] secondary. БЕЗ barcode scan.

**K2.** Системата сама определя кой тип. Пешо не избира.

**K3.** Тип 1 (чиста): review screen с всички редове. ВСЕКИ ред задължително одобряван от Пешо ☐ → ✓.

**K4.** Tap на ред → bottom sheet:
- Edit qty/cost
- AI препоръчва retail
- Quick Add ако непознат
- Matrix flow ако вариационен без описание

**K5.** „Заприходи всичко" активен само когато всички 76/76 redoa одобрени.

**K6.** Бутон [✓ Одобри всички зелени] — само високо confidence redoa.

**K7.** Тип 2 (полу-чиста): жълт ред с „Уточни вариации" → matrix flow задължителен.

**K8.** Тип 3 (ръкописна): автоматично voice диктовка fallback (SIMPLE_MODE_BIBLE §7.3.5).

**K9.** Math validation в края — soft toast ако total не излиза. Tolerance 0.5 лв. Не блокира commit.

**K10.** State preservation: при прекъсване (телефон звъни) → localStorage TTL 30 минути → „Продължи доставката от Marina?" [Да] [Не].

---

## L. DETAILED MODE (МИТКО)

**L1.** Същият backend, body class `mode-detailed`, table view.

**L2.** ДДС полета (number, дата, payment_method, padezh, ДДС база, ДДС, общо) — НЕ блокиращи.

**L3.** PDF attachment бутон.

**L4.** Reconciliation overlay inline (НЕ tih insight като в Simple).

**L5.** Cost variance таблица с history per продукт.

**L6.** Edit на минала доставка позволено (Пешо не може). Audit trail в delivery_events.

---

## M. AI BRAIN INSIGHTS

**M1.** Базови 6 типа:
- reconciliation_mismatch (HIGH, всички)
- cost_variance (MEDIUM, owner+manager)
- zone_walk_trigger (LOW, всички)
- variation_reconcile (MEDIUM, всички)
- confidence_nudge (LOW, всички)
- supplier_reactivated (LOW, owner)

**M2.** Допълнителни 5:
- delivery_anomaly_pattern (3+ поредни mismatches)
- payment_due_reminder (от payment_terms)
- new_supplier_first_delivery (LOW, onboarding)
- volume_discount_detected (LOW, owner)
- stockout_risk_reduction (bestseller wasn't 0 → in stock)

**M3.** Split insights vs ai_brain_queue:
- ai_insights = passive observation (life-board pills)
- ai_brain_queue = actionable task (chat пита proactive)

**M4.** Bidirectional link: ai_insights.linked_brain_queue_id → resolve в едната затваря другата.

**M5.** TTL и auto-resolve:
- reconciliation_mismatch: НИКОГА auto-resolve. Auto-merge при следваща доставка от същия supplier (status='superseded')
- variation_reconcile: 7 дни TTL с escalation на ден 2 и 7
- cost_variance: 14 дни auto-dismiss
- zone_walk_trigger: 24h auto-dismiss
- confidence_nudge: 7 дни batch dismiss
- supplier_reactivated: 24h auto

**M6.** Zone walk trigger след save: async през ai_brain_queue с trigger_after = NOW + 2h. Не блокира commit.

---

## N. DB SCHEMA

**N1.** Schema name drift: live wins. Продължаваме с `purchase_orders` (НЕ `supplier_orders`). BIBLE се обновява.

**N2.** **5 НОВИ ТАБЛИЦИ:**
1. `deliveries` — шапка
2. `delivery_items` — редове
3. `delivery_events` — audit trail
4. `supplier_defectives` — pool за връщане
5. `price_change_log` — auto-pricing история

**N3.** **2 НОВИ ТАБЛИЦИ за бъдещо ползване (от deliveries):**
- `pricing_patterns` (per category multiplier + ending)
- `voice_synonyms` (вече описана в SIMPLE_MODE_BIBLE)

**N4.** **НОВИ КОЛОНИ на existing tables:**
- `suppliers.reliability_score` (0-100)
- `ai_insights.linked_brain_queue_id`
- `products.has_variations` ENUM('true','false','unknown') — verify дали вече е така в live

**N5.** Връзка delivery↔orders: many-to-many на ред-ниво. `delivery_items.purchase_order_item_id` (NULLable FK). НЕ `deliveries.matched_order_id`.

**N6.** Задължителни колони във всичките нови таблици:
- `tenant_id` (data isolation)
- `store_id`
- `currency_code` snapshot

**N7.** В `deliveries` (важни колони):
- `invoice_type` ENUM('clean','semi','manual') — определя се автоматично
- `pack_size` factor (за кутии × бройка)
- `ocr_raw_json` (debug)
- `source_media_urls` JSON (snimkata на фактурите)
- `reviewed_by`/`reviewed_at`, `committed_by`/`committed_at`, `locked_at`
- `auto_close_reason` ENUM('user_committed','auto_after_session','imported','merged_with_next','voided')
- `has_mismatch`, `mismatch_summary` (или JSON)
- `has_unfactured_excess`, `has_unreceived_paid`

**N8.** В `delivery_items`:
- `line_number`
- `total_cost` (denormalized)
- `product_name_snapshot`, `barcode_snapshot`, `sku_snapshot`
- `supplier_product_code` („златен ключ")
- `pack_size`
- `vat_rate_applied`
- `received_condition` ENUM('new','damaged','expired','wrong_item')
- `original_ocr_text`
- `is_bonus` (true ако is подарък/мостра)
- `variation_pending` bool
- `parent_product_id` (за вариационни редове)

**N9.** НЕ в `delivery_items`: `previous_cost`, `cost_variance_pct` — тези са computed snapshot за ai_insights, не facts.

**N10.** Foreign keys ON DELETE RESTRICT (финансови записи). Изключение: `delivery_items` → `deliveries` CASCADE.

**N11.** Indexes:
- `deliveries`: (tenant_id, supplier_id, created_at DESC), (tenant_id, status, created_at), (matched_order_id), partial (has_mismatch=1)
- `delivery_items`: (delivery_id), (tenant_id, product_id, created_at), (variation_pending), (supplier_product_code)
- `supplier_defectives`: (tenant_id, supplier_id, status)

**N12.** Migration: idempotent script. Първо проверка дали `delivery_items` вече съществува (compute-insights.php я референсва ред 2400, 2416).

---

## O. AI BRAIN MEMORY MAP

**O1.** `buildSupplierContext($tenant_id, $supplier_id)` helper в build-prompt.php сглобява context при всеки разговор за supplier:
- unresolved_mismatches от ai_insights
- pending_defectives от supplier_defectives
- excess_unresolved от deliveries
- last_3_deliveries от deliveries
- cost_history per product от delivery_items

**O2.** PHP смята всичко. AI получава готов JSON. AI не търси сам в DB (Закон №2).

**O3.** AI Brain Memory Map таблица в финалния документ — кое какво помни, в коя таблица, кой я чете, при какво условие.

**O4.** Downstream consumers на delivery insights:
- life-board.php (Simple Mode pills)
- chat.php (Detailed Mode signal cards)
- warehouse.php (badge с pending mismatches count)

---

## P. BETA STRATEGY

**P1.** Не правим Q3 решение (orders ↔ delivery порядък) защото правим заедно сега.

**P2.** Reconciliation timing — жълт toast веднага + persistent insight след 2ч.

**P3.** ENI launch 14-15 май остава твърд deadline.

---

## Q. CHAT SCOPE ЗАЩИТА

**Q1.** Chat-ът отговаря само за магазина. „Аз съм за магазина ти. Не мога да помогна с това."

**Q2.** Сухо и фактически, без emojis, без сладкодумни.

**Q3.** Soft rate limits per план:
- FREE: 5 минути voice/месец, 20 текстови съобщения/ден
- START: 60 минути, 100 текстови
- PRO: 500 минути, 500 текстови
- BIZ: 2000 fair use

**Q4.** Anti-abuse cron monitor:
- Повторение на въпрос 5+ пъти за 10 мин → flag
- Voice минути > 2x median → flag
- Off-topic ratio > 30% → notification на owner
- При flag → email на Митко

---

## R. ИНТЕГРАЦИИ (DOWNSTREAM)

**R1.** Финансов модул: всяка доставка генерира `accounts_payable` запис от ден 1, дори преди UI да съществува. Бъдещият модул чете оттам. Връщания/дефектни → credit записи.

**R2.** Inventory + stock_movements: автоматичен update при всяко delivery action (стандарт).

**R3.** Bluetooth print: trigger след successful commit. Async queue. Само за нови продукти, не препечатваме existing.

**R4.** Offline (S88): отлага се. Delivery следва общ pattern. Конкретно cache-ва се: snimkata, voice transcript, supplier list, products list, pending commit job.

**R5.** Reorder: при връщане на доставка ако падне под min_quantity → orders.php trigger.

------

## S. PAYMENT LIFECYCLE (НОВО)

**S1.** `deliveries.payment_status` ENUM('unpaid','partial','paid') DEFAULT 'unpaid' — задължителна колона на deliveries.

**S2.** `suppliers.payment_terms_days` INT DEFAULT 0 — нова колона. 0 = плащане при доставка. 30 = 30 дни кредит. AI учи от историята (ако всеки път плащаш Marina на 30-я ден → suggest payment_terms_days=30 onboarding).

**S3.** `deliveries.due_date` DATE NULL — computed при commit: created_at + suppliers.payment_terms_days. NULL ако payment_terms_days=0 (cash on delivery).

**S4.** AI Brain insight `payment_due_reminder` се генерира от cron daily 08:00 за всички deliveries с `payment_status IN ('unpaid','partial')` AND `due_date <= NOW() + 3 дни`. Severity HIGH ако `due_date < NOW()` (просрочено).

**S5.** Платежният модул (бъдещ) НЕ е scope тук. Сега само се записват факти в `accounts_payable` (R1). Бъдещ модул чете оттам и update-ва `payment_status`.

**S6.** При Detailed Mode (Митко) — секция "Неплатени" в supplier dashboard с total amount due + due_date list. Tap на доставка → отваря delivery detail с [Маркирай платено] бутон.

**S7.** При Simple Mode (Пешо) — proactive insight в life-board: "Утре трябва да платиш Marina €450". Voice: "плати ли си Marina?" → AI отговаря.

**S8.** Partial payments: `payments` таблица (бъдеща, не сега) с FK към delivery. За beta — само full payment toggle.

---

## T. PACK_SIZE UX (НОВО)

**T1.** Review screen в delivery: всеки ред с `pack_size > 1` показва toggle "📦 пакети / 🔢 бройки". Default визуализация = бройки (общо), но пакети са visible отдолу: "(= 10 пакета × 12)".

**T2.** OCR auto-extract на pack_size:
- Pattern matching на текст в OCR резултата: "10 кутии × 12", "10 куф. по 12", "10 х 12"
- Ако намери → `delivery_items.pack_size=12`, `quantity=120`
- Ако не намери, но `name` съдържа "кутия" / "опаковка" / "пакет" → AI пита Пешо при review: "Това на пакети ли е? Колко в пакет?"

**T3.** Stepper input в review row е bound към единици (бройки). Toggle на pack mode → стъпката става × pack_size (един + добавя цял пакет).

**T4.** Inventory record-ът след commit винаги е в **бройки** (не пакети). pack_size се запазва на `delivery_items` ниво за audit и за следваща auto-detection.

**T5.** Бизнес правило: `pack_size` може да varies между доставки на същия продукт (Marina праща в кутии от 12, друг supplier в кутии от 10). Не насилваме unification — всяка доставка пише свой pack_size.

**T6.** Voice flow: "30 пакета чорапи по 12 в пакет от Marina" → AI parse → 30 пакета × 12 = 360 бройки + pack_size=12. Confirm: "30 пакета = 360 чорапи. Да?"

**T7.** Edge case: фактура казва "10 кутии 50€", без бройка-в-кутия. AI пита: "Колко чорапи в кутия?" → попълва pack_size + бройки.

---

## U. ORDER LIFECYCLE (РАЗШИРЕНО — допълнение към G + N)

**U1.** `purchase_orders.status` ENUM добавя `'stale'` (към existing draft/sent/partially_received/received/cancelled).

**U2.** Cron daily 09:00 проверява: всички `purchase_orders` със `status='sent'` AND `created_at < NOW() - 14 days` AND **0 свързани deliveries** → SET `status='stale'`.

**U3.** AI Brain insight `order_stale_no_delivery` (нов type, добавя се към M1 списъка) — severity MEDIUM, role_gate='owner','manager'. Текст: "Поръчка от 14.04 към Marina не е доставена. Да я отменим или да попитам?"

**U4.** Action на insight: 3 бутона:
- [Обади се] → отваря Митко Detailed pricing call screen, или Пешо voice prompt "обади се на Marina"
- [Отмени поръчката] → status='cancelled', insight resolved
- [Чакай още] → snooze 7 дни, insight се връща

**U5.** Reconciliation на partial deliveries: ако от 50 поръчани са дошли 30 в първа доставка → status='partially_received'. След 14 дни без втора доставка → `status='stale'` за останалите 20. AI insight reflect-ва само останалите.

**U6.** Auto-merge logic: ако нова доставка от същия supplier пристигне и съдържа артикули от stale order → автоматично се закача към оригиналния order, status се обновява. Никакво питане.

---

## V. BONUS / МОСТРИ — DEEP (РАЗШИРЕНО — допълнение към G5)

**V1.** `delivery_items.is_bonus=1` НЕ update-ва `inventory.cost_basis` (Weighted Average Cost). Existing WAC се запазва. Ако продуктът е нов → cost_basis = NULL (не 0), AI пита Пешо при първа продажба или go to default retail.

**V2.** Sales report financial separation:
- Gross margin от purchased items: (revenue - WAC × qty)
- Gross margin от bonus items: 100% (revenue - 0)
- Reports показват ОТДЕЛНО → Митко вижда реалната рентабилност

**V3.** AI Brain learning: "Marina ти дава средно 8% мостри за година" → patterns в `pricing_patterns` или нова `supplier_bonus_history` таблица. Бъдеща версия (defer).

**V4.** Bonus VAT handling: `delivery_items.vat_rate_applied=0` за is_bonus rows (мостри обикновено без ДДС). Митко може да override в Detailed.

**V5.** Inventory valuation report (бъдещ модул) trябва да отбелязва: "1500 бройки в склад, от които 87 бонус (€0 cost) → average cost = X €/бр". Прозрачност за owner.

**V6.** Frontend hint: ред с is_bonus=1 в delivery review → green pill "БОНУС" вдясно. Cost field disabled (read-only €0). AI prompt при първи bonus: "Това безплатно ли е?" → confirm.

---

## W. SUPPLIER_PRODUCT_CODE FLOW (РАЗШИРЕНО — допълнение към N8)

**W1.** Дефиниция: код който supplier-ът ползва за този product (тяхна вътрешна номенклатура). Не баркод. Не наш SKU. Пример: Marina пише "MAR-CHR-42BLK" за черни чорапи 42 — това е техният код.

**W2.** Извличане:
- OCR auto-extract: ако фактурата има колона "Код" / "Артикул" / "SKU" → извлича в `delivery_items.supplier_product_code`
- При първа доставка от нов supplier → AI може да пропусне (не знае кой е код, кой е barcode)
- Пешо НЕ въвежда ръчно (Закон №1)

**W3.** Auto-matching на следваща доставка:
- При нова OCR от същия supplier → за всеки delivery_item търси предишен `supplier_product_code` match
- Ако намери → linkproductId автоматично, без Пешо да избира
- Confidence boost +0.15 (от I5 supplier templates)

**W4.** Множествени codes: ако Marina има 3 различни кода за същия наш product (rebrand, version) → всички се запазват в `supplier_product_code_history` (бъдеща таблица). За beta — само last_seen в delivery_items.

**W5.** Празна стойност = OK. Не е блокер. Просто matching няма да работи между доставки → fallback на name/barcode similarity (slower, less accurate).

**W6.** Index на `delivery_items.(tenant_id, supplier_id, supplier_product_code)` — fast lookup. Trябва да е добавен в N11 indexes list.

**W7.** UX визуализация: при review screen, ако delivery_item има supplier_product_code от предишна доставка → tih indicator "⚡ позната" (visual cue че auto-match-нато). Tap за detail.

---

## ПРЕРАВНОВЕСЕН БРОЙ

**Нови решения от Append: 30 (S1-S8, T1-T7, U1-U6, V1-V6, W1-W7)**

**ФИНАЛНИ РЕШЕНИЯ: 124 + 30 = 154**

(110 от ПАС 1 + 14 от ПАС 2 + 30 от ПАС 3 шеф-чат append)
## X. BACKUP РЕЖИМ — БЕЗ ИНТЕРНЕТ (ЗАДЪЛЖИТЕЛЕН)

**X1.** Backup режим е **задължителен за 3-те модула** — доставка, поръчка, продажба. Никой модул не оставя Пешо в "счупено" състояние при offline.

**X2.** Закон №1 (Пешо не пише) се **suspend-ва САМО в offline mode** с explicit indicator на върха („НЯМА ИНТЕРНЕТ — РЪЧЕН РЕЖИМ"). Пешо разбира защо. Няма мълчалив fallback.

**X3.** При detect на offline в delivery/orders entry screen → AI alternatives недостъпни, показват се 2 опции:
- **„Добави артикул"** — primary action
- **„Запази снимка за после"** — IndexedDB queue, при reconnect AI обработва автоматично с notification „Доставката от Marina готова за преглед."

**X4.** „Добави артикул" е **shortcut към products.php?action=add** wizard. Никаква нова offline форма. Reuse на existing wizard + helper функции (price predictor, last-cost autofill, supplier autofill, barcode scan offline).

**X5.** Wizard приема `return_to` параметър: `products.php?action=add&return_to=delivery&delivery_session=xyz`. След save → автоматичен redirect към `delivery.php?session=xyz` с preserved state.

**X6.** Loop pattern: delivery → wizard → save → return към delivery с растяща количка → „Има ли още артикул?" [Добави още] [Готова съм]. Цикъл докато Пешо приключи.

**X7.** State preservation на доставката offline:
- localStorage (TTL 30 минути за бърз recovery при крах)
- DB draft (deliveries.status='draft') ако има auth + connection преди offline
- IndexedDB pending queue ако напълно offline

**X8.** Същият loop pattern за orders.php — voice cart → fallback → product picker → return към cart с running total.

**X9.** Sync при reconnect:
- Bottom toast: „3 действия от offline режима — синхронизирам..."
- Photo queue → AI обработва във фон → notification per готова доставка
- Pending operations → server sync с conflict resolution (last-write-wins за simple data)

**X10.** Pending sync таблица в IndexedDB: action_type, payload, created_at, retry_count, status. Visible в Detailed Mode за Митко (Митко вижда какво чака sync).

**X11.** **Никога silent failures** — всеки offline action показва status visually:
- Save → toast „Записан offline · ще се синхронизира"
- При reconnect → toast „Синхронизация готова"
- При sync конфликт → ai_insight към Митко

---

## БРОЙ (ОБНОВЕН)
**ФИНАЛНИ РЕШЕНИЯ: 154 + 11 (backup) = 165**

(110 ПАС 1 + 14 ПАС 2 + 30 шеф-чат append S/T/U/V/W + 11 BACKUP X)
## БРОЙ
**ФИНАЛНИ РЕШЕНИЯ: 124** (110 от ПАС 1 + 14 нови от ПАС 2 + 9 корекции inline)

Готов за следващия chat (имплементационен) — той ще преобразува всяко решение в DB schema, code, миграции.
