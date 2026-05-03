# 📋 STRESS_BOARD — ОБЩА ДЪСКА НА СТРЕС СИСТЕМАТА

**Последно обновяване:** 04.05.2026 — S95 night entry added
**Активна нощ:** 03→04.05 (S95.WIZARD scenarios)

---

## ГРАФА 1 — ЗА ТЕСТВАНЕ ТАЗИ ВЕЧЕР

Пише се от дневния чат когато завърши нещо. Чете се от нощните cron-ове.

Формат: дата | модул | файл | статус | очаквано поведение | бележка

### S95.WIZARD.RESTRUCTURE — products.php (3 commits на main 03.05)

**Commits to test:**
- cad029e (PART1 — consolidated step 1 + mini print overlay)
- 0ccdb52 (PART1_1_HOTFIX — 5 browser-test bugs + qty/min auto-formula)
- 8100c34 (PART1_1_A_PATCH — qty stepper + print fallback + Като предния)

**Scenarios to verify (cron + manual ако нужно):**

#### Single product save flow (3 sec basic case)
- Open wizard → tap Единичен → fill Name + Price + Qty → tap ЗАПИШИ
- Expected: mini print overlay appears with [🖨][✓ ГОТОВО]
- Expected: tap ✓ ГОТОВО → wizard closes → product visible в list
- Expected: auto-gen barcode (13 digits) + auto-gen SKU added at save time

#### Auto-formula min qty
- Tests: qty=1 → min=1, qty=5 → min=2, qty=7 → min=3, qty=10 → min=4
- Manual override: min set ръчно на X → qty change → min stays X (dataset.userEdited)

#### Toggle mandatory choice
- New wizard → fields disabled (opacity 0.42)
- Tap "Единичен" → fields enabled (opacity 1.0)
- Tap "С Вариации" → fields enabled, save button still requires step 4-5 completion

#### Dropdowns (Доставчик / Категория)
- Tap field → list appears below input
- Type "Да..." → list filters live
- Tap item → fills + closes dropdown
- Tab/Enter → confirms current top match

#### Подкатегория native select
- Visible chevron arrow ▼
- Tap → native browser select opens
- Filter by category (existing logic preserved, RQ D3)

#### "Като предния" button
- New install / no products → button shows "(след първия запис)" placeholder
- Tap placeholder → toast hint
- After first product save → button becomes active (indigo gradient)
- Tap active → fills all fields from last saved product

#### Variations flow (untouched в ЧАСТИ 1/1.1/1.1.A)
- Tap "С Вариации" → fields enabled
- Tap ЗАПИШИ on step 1 → toast "Първо завърши вариациите"
- Tap Напред → → step 4 (matrix XXS-5XL chips)
- Matrix → step 5 (zone field S94 already added)

#### Print fallback от mini overlay
- After Single save → tap 🖨 ПЕЧАТАЙ ЕТИКЕТ
- Expected: BLE printer (DTM-5811) prints label
- Expected: combo.printQty correctly extracted (not lblQty<i> step 6 DOM elements)
- Edge: BLE intermittent — graceful "не е свързан" toast ако fail

### Edge cases & race conditions

#### Concurrent product save
- 2 sessions same tenant create products simultaneously
- Expected: no auto-gen SKU collision (timestamp + random suffix in S94 logic)
- Expected: no auto-gen barcode collision (13-digit EAN-13 with check digit)

#### Wizard exit mid-flow
- Open wizard → fill some fields → click outside / browser back
- Expected: localStorage cleanup или recoverable draft
- Expected: no orphaned product DB row (created only at successful save)

#### Wizard refresh mid-flow
- Open wizard → fill fields → hard refresh page
- Expected: localStorage state restored or graceful empty start
- Decision: not critical for beta (Пешо обикновено completes flow)

### ENI critical 4 модула baseline (preparation)

#### sale.php S87 read-only stress
- Active POS sessions: 5 concurrent simulated tenants
- Read queries: products lookup, customer search, recent sales
- Expected: <500ms p95 query latency
- Note: NO writes — sale.php S87E hardening still pending

#### warehouse.php read-only
- Active sessions: 3 simulated
- Read queries: inventory levels, low-stock alerts
- Expected: <300ms p95

#### deliveries.php placeholder
- Module не съществува yet → skip
- Will be added 06-08.05

#### transfers.php placeholder
- Module не съществува yet → skip
- Will be added 09-10.05

### BLE Printer Reliability (RWQ-75 investigation)

#### Reconnect after sleep
- Print 5 etikets continuously
- Wait 30 минути (printer auto-sleep)
- Print 1 etiket → Expected: auto-reconnect или graceful "натисни Свържи"

#### localStorage state integrity
- Pair printer → close APK → reopen → print
- Expected: localStorage retains rms_printer_device_id + service_uuid + write_char_uuid
- Expected: no re-pair required

### Marketing AI prep (Phase 0 readiness baseline)

#### Inventory accuracy passive scoring (RWQ-63 prep)
- Run query: count of products without cost_price
- Run query: count of inventory mismatches (sales > stock_at_time_of_sale)
- Expected: baseline metric for tenant=99 (synthetic data) — TBD threshold

(Note: RWQ-63 is a build task, not yet runnable — this is just a metric capture for trending later.)

### НЕ ЗА ТАЗИ НОЩ (ясно отбелязано)

- Voice-first wizard (RWQ-72) — ЧАСТ 1.2 not yet implemented
- AI Studio entry (RWQ-73) — ЧАСТ 1.3 not yet implemented
- Wizard ЧАСТИ 2-4 — not yet implemented
- Marketing AI scenarios — Phase 0 not yet started

### CRON RUN EXPECTED at: 04.05.2026 ~05:00 EEST

`tools/diagnostic/cron/sales_pulse.py` will run, generate snapshot в `tools/testing_loop/daily_snapshots/2026-05-04.json`.
If snapshot empty или missing → flag P0 в STATE LIVE BUG INVENTORY (crontab regression).

### OUTCOME REVIEW (ГРАФА 2 утре сутрин)

Шеф-чат на 04.05 EOD ще:
1. Read /tools/testing_loop/daily_snapshots/2026-05-04.json
2. Move passed scenarios from ГРАФА 1 → ГРАФА 4 (2 поредни зелени → archive)
3. Move failed scenarios → ГРАФА 3 (за оправяне)
4. Update STATE LIVE BUG INVENTORY accordingly

---

## ГРАФА 2 — РЕЗУЛТАТИ ОТ СНОЩИ

Пише се от cron-овете и Code анализа сутрин. Чете се от Стрес чата и Шеф чата.

Формат: дата | какво е тествано | минали | гръмнали | бележки

**Текущо:** празно — стрес системата още не е активна

---

## ГРАФА 3 — ЗА ОПРАВЯНЕ

Пише се от Стрес чата след анализ. Чете се от Code чатовете когато получат задача.

Формат: приоритет | модул | файл | ред | описание | свързана документация

**Текущо — открити бъгове чакащи фикс (от анализа на разговора 30.04.2026):**

P0 | sale.php | 132 | race condition: UPDATE inventory SET quantity = GREATEST(quantity - X, 0) — двама продавачи продават последния артикул едновременно и двамата успяват | Inventory v4 §1 ЗАКОН №2

P0 | compute-insights.php | 235 | hardcoded $module = 'products' но life-board.php търси 'home' — 99% от сигналите не стигат до борда | TECHNICAL_REFERENCE_v1 §4

P1 | ai_insights table | UNIQUE ключ | (tenant_id, store_id, topic_id) блокира нови записи, само UPDATE на съществуващи — броят сигнали остава фиксиран независимо колко пъти cron работи | -

P2 | helpers.php | 161 | shouldShowInsight() крие веднъж видени сигнали (cooldown) — мъчи стрес теста, нужен е флаг за изключване в test режим | -

P2 | helpers.php | 170 | urgency лимити (critical=2, warning=3, info=3) и ако има critical/warning се крият всички info | -

P0 | tools/diagnostic/cron/sales_pulse.py | целия | пуска всички продажби с DATE_ADD(DATE(NOW())) — винаги днес, няма история; и използва GREATEST — стоката никога не свършва | STRESS_BUILD_PLAN Етап 3

---

## ГРАФА 4 — ОПРАВЕНО, ТЕСТВАЙ ОТНОВО

Пише се от Code чат когато оправи нещо. Чете се от нощния cron — следващата вечер тества повторно.

Формат: дата | бъг | файл | сесия която го оправи | очаквам зелено

**Текущо:** празно

**Exit criteria:** бъг се мести от тук в раздел "АРХИВ ЗАКРИТИ" след 2 поредни зелени нощи в cron 2. Един зелен ден не стига — false positive риск.

---

## АРХИВ ЗАКРИТИ

Пише се от Стрес чата след 2 поредни зелени нощи. Чете се от никой — само за история.

Формат: дата на закриване | бъг | файл | колко нощи зелено

**Текущо:** празно

---

ПРОТОКОЛ ИЗПЪЛНЕН: Минах разговора три пъти.
