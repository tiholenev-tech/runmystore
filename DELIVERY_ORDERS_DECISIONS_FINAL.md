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

---

## БРОЙ
**ФИНАЛНИ РЕШЕНИЯ: 124** (110 от ПАС 1 + 14 нови от ПАС 2 + 9 корекции inline)

Готов за следващия chat (имплементационен) — той ще преобразува всяко решение в DB schema, code, миграции.
