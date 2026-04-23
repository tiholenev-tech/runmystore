# 🧭 MASTER_COMPASS v3.0 — ЖИВИЯТ ORCHESTRATOR

## Router + Tracker + Dependency Tree + Change Protocol

**Последна актуализация:** 23.04.2026
**Последна завършена сесия:** S79.VISUAL_REWRITE (chat.php v8, 23.04.2026)
**Следваща сесия:** S80 — products.php wizard rewrite (4 стъпки final)
**Текуща Phase:** A — Products Foundation  
**Първа реална продажба target:** ЕНИ магазин, 10-15 май 2026

---

# 🚀 СТАРТОВ ПРОТОКОЛ — ПРИ ВСЯКО ОТВАРЯНЕ НА CHAT

> **ВАЖНО: Chat-ът НЕ задава въпроси. Чете, казва състояние, започва работа.**

## Стъпка 1 — Прочети (3 задължителни файла)

```
1. MASTER_COMPASS.md (този файл)
2. DOC_01_PARVI_PRINCIPI.md (петте закона + Пешо)
3. Последен SESSION_XX_HANDOFF.md
```

## Стъпка 2 — Прочети specific за задачата

От таблицата "📚 ЧЕТИВО ЗА СЛЕДВАЩАТА СЕСИЯ" по-долу.  
Максимум 7 файла общо. Никога всички 40.

## Стъпка 3 — Кажи на Тихол състоянието (БЕЗ ВЪПРОСИ)

**Формат на отговора при отваряне:**

```
Прочетох COMPASS + DOC 01 + SESSION_79_INSIGHTS_HANDOFF + [specific].

СЪСТОЯНИЕ:
- Последна сесия: S79.INSIGHTS (compute-insights.php, 22.04.2026)
- Последен commit: 5b8a3e0 (S79.INSIGHTS)
- Текуща фаза: Phase A (Products Foundation)
- Завършено: ~15%

РАБОТИ:
- products.php v0.9 (списък, wizard, детайли) — 8394 реда
- sale.php (базова функционалност)
- chat.php v7 (dashboard + overlay)
- compute-insights.php (19 pf функции, 9 активни на tenant 7) — 1280 реда
- ai_insights генерира реални insights (zombie 148K EUR, top profit, bestsellers low stock и т.н.)

НЕ РАБОТИ:
- products.php P0 bugs от PRODUCTS_MAIN_BUGS_S80.md
- DB credentials в публично репо (P0 SECURITY)
- Cron за compute-insights не е настроен
- compute-insights само за първия магазин на tenant (multi-store отложено)
- orders.php, deliveries.php, simple.php (не съществуват)
- Bluetooth печат (не интегриран)

СЛЕДВАЩА ЗАДАЧА (S79.SECURITY P0):
1. Ротирай MySQL парола
2. Създай .env с credentials
3. .env в .gitignore
4. config/database.php чете от env vars
5. Force-rebase или accept exposure (старата парола ротирана = OK)

Започвам от задача 1. Команди за конзолата идват сега.
```

**Ако Тихол напише само "продължи" → започваш да действаш веднага.**

---

# 📍 ТЕКУЩО СЪСТОЯНИЕ (LIVE STATE)

## Модули в production

| Модул | Статус | Ред | Бележка |
|---|---|---|---|
| `products.php` | 🟡 работи с 3 P0 бъга | 8394 | S78 fix → S79 главна → S80 wizard → S81 AI → S82 polish |
| `sale.php` | 🟡 базово работи, нужен rewrite | — | S85 (voice primary + camera always-live + numpad) |
| `chat.php` | 🟢 v7 + 6Q AI context + proactive pills + ai_shown tracking | 1605 | S79.FIX (fq-badge), CHAT 4 visual rewrite, S95 Simple Mode |
| `warehouse.php` | 🔴 само скелет | — | S87 (hub rewrite + 5 подмодула) |
| `inventory.php` | 🟡 v3 работи, v4 rewrite | — | S87 (event-sourced, Smart Resolver, offline) |
| `stats.php` | 🟡 базово | — | S93 (5 таба, role-based, drawer) |
| `orders.php` | 🔴 не съществува | — | S83 (12 входа + 11 типа + 8 статуса) |
| `deliveries.php` | 🔴 не съществува | — | S86 (OCR + voice + wizard) |
| `transfers.php` | 🔴 не съществува | — | S92 (multi-store + resolver) |
| `simple.php` / `life-board.php` | 🔴 не съществува | — | S95 (AI chat = home) |
| `ai-action.php` | 🔴 не съществува | — | S94 (router + $MODULE_ACTIONS) |
| `compute-insights.php` | ✅ 19 функции (products), 9 активни на tenant 7 | 1280 | S79.INSIGHTS done → S84 (+20 за warehouse/sale/stats) |

## DB foundation

| Компонент | Статус | Сесия |
|---|---|---|
| `schema_migrations` таблица | ✅ S79.DB | S79 |
| Money cents миграция | 🔴 DECIMAL в момента | S79 |
| `audit_log` | ✅ S78 + helper S79.DB | S79 |
| Transaction wrapper `DB::tx()` | ✅ S79.DB (без deadlock retry) | S79 |
| Soft delete pattern | ✅ S79.DB (5 таблици) | S79 |
| Negative stock guard (TRIGGER) | 🔴 няма | S80 |
| Composite tenant FK | 🔴 няма | S80 |
| `idempotency_keys` таблица | 🔴 няма | S80 |
| Stock movements append-only ledger | 🔴 няма | S81 |
| `events` + `dead_letter_queue` | 🔴 няма | S81 |
| **S77 таблици** (ai_insights, supplier_orders*, lost_demand) | ✅ S78 + S79.INSIGHTS popolnen ai_insights | S78 |
| `idempotency_log` (multi-device) | 🔴 няма | S78 |
| `user_devices` (multi-device tracking) | 🔴 няма | S78 |
| `wizard_draft` (crash recovery) | 🔴 няма | S79 |
| `inventory_events` (event-sourced) | 🔴 няма | S87 |

## Hardware / External

| Ресурс | Статус |
|---|---|
| DO Frankfurt, 2GB RAM, `/var/www/runmystore/` | ✅ |
| GitHub `tiholenev-tech/runmystore` (public) | ✅ |
| Gemini 2.5 Flash (2 keys, rotation) | ✅ |
| OpenAI GPT-4o-mini (fallback 429/503) | ✅ |
| fal.ai (birefnet + nano-banana-pro) | ✅ |
| **DTM-5811 Bluetooth Classic printer** (TSPL, BT 2.1.1, BDA DC:0D:51:AC:51:D9) | ⚠ **CSV workaround** — печат през QU Printing app (Dothantech). Native plugin = S82. |
| Pusher account (real-time sync) | 🔴 не създаден (S88) |
| ЕНИ магазин beta | ⏳ 10-15 май 2026 first sale |
| 2-ри beta tenant (с онлайн) | ⏳ юни 2026 |
| Android Studio setup + Capacitor проект | ⏳ **prerequisite за S82** (не е инсталиран засега) |

## 🖨 Printer strategy — 3 фази

### ФАЗА 1 (S81, ДНЕС) — CSV workaround ✅

Пешо: products.php → продукт → [🏷 Етикет] → [Свали CSV] → отваря QU Printing app → импортира → печата.

Причина: DTM-5811 е Bluetooth Classic (BT 2.1), Web Bluetooth API не работи с него.

### ФАЗА 2 (S82, НОВ ЧАТ) — DTM-5811 Capacitor native plugin

**Обхват:** само DTM-5811 (200 поръчани принтера).

**Prerequisites (задължително преди стартиране):**
- Android Studio инсталиран
- Capacitor проект за RunMyStore създаден
- Java JDK 17+, Android SDK 34+
- Signing keys за APK

**Технически план:**
- Transport: Bluetooth Classic SPP (UUID 00001101-0000-1000-8000-00805F9B34FB)
- Protocol: TSPL (reuse `buildTSPL()` кода от S81)
- Custom Capacitor plugin Kotlin
- UI: същия drawer в products.php; бутонът [Печатай всички] вика plugin-а
- Fallback: CSV експорт остава достъпен

**Estimated effort:** 4-8 часа разработка + 1-2 часа тест + 2-3 часа Android Studio setup (ако липсва).

### ФАЗА 3 (S85.5, преди client launch) — Universal Printer Plugin

**Обхват:** всички масови thermal printers.

**Протоколи:** TSPL, ESC/POS (Epson/Star), CPCL (Zebra mobile), ZPL (Zebra desktop).
**Транспорти:** Bluetooth Classic SPP, BLE (GATT), WiFi TCP/IP, USB (Android USB Host).

**UI:** printer selector в Settings, auto-detect по model name, test print.

**Estimated effort:** 3-5 дни Android + 2-3 дни iOS + 1-2 дни WiFi/ZPL.

**Референции:**
- TSPL: TSC Auto ID / Gprinter manuals
- ESC/POS: Epson docs
- Niimbot BLE: github.com/MultiMote/niimblue
- Fichero/D11 BLE: github.com/0xMH/fichero-printer
- Capacitor BLE: @capacitor-community/bluetooth-le
- TSPL generator код: backup `/root/printer.js.bak.*` от S81

## Активни P0 bugs

| # | Файл | Симптом | Fix | Сесия |
|---|---|---|---|---|
| 5 | products.php `wizPhotoUpload()` | AI Studio _hasPhoto не се сетва | (S78 fix приложен — verify в S79.FIX.B / S80) | S78 ✅ done, retest pending |
| 6 | products.php `renderWizard()` | Нулира бройки при re-render (step 6) | (S78 fix приложен — retry verification в S79.FIX.B) | S78 ✅ done, retest pending |
| 7 | products.php `listProducts` | sold_30d = 0 | LEFT JOIN sale_items aggregated subquery | S78 ✅ done |
| 9 | products.php Q-секции тап | Артикулната карта в q1-q6 отваряше EDIT wizard (data risk) | `editProduct` → `openProductDetail` в `sec.items.map` render | **S79.FIX.B ✅ done** |

---

# 🎯 СЛЕДВАЩА ЗАДАЧА — S78

**Тип:** Фундамент — DB + P0 bugs + skeleton  
**Модел:** Opus 4.7  
**Estimated duration:** 3-4 часа

## Задачи

### 1. DB миграция — всички S77 таблици

SQL скрипт (от APPENDIX §11):
- `ai_insights` + `fundamental_question` ENUM колона
- `ai_shown` (cooldown tracking)
- `search_log` (за lost_demand)
- `lost_demand` с 4 нови колони (suggested_supplier_id, matched_product_id, resolved_order_id, times)
- `supplier_orders` (8 статуса + 11 типа enum)
- `supplier_order_items` (fundamental_question + source + ai_reasoning)
- `supplier_order_events` (audit trail)
- `idempotency_log` (multi-device race prevention)
- `user_devices` (multi-device tracking)

**Deployment:** Python скрипт `/tmp/s78_migrate.py` → backup → execute → verify → log.  
**Tenant priority:** tenant_id=7 (test) → tenant_id=52 (ЕНИ).

### 2. P0 bugs fix (#5, #6, #7)

Всеки — отделен Python patch script в `/tmp/`.  
Anchor-based replacement (не line numbers).  
`php -l` след всеки → git commit → git push.

### 3. compute-insights.php skeleton

15 функции по fundamental_question:
- **loss (3):** zero_stock_with_sales, below_min_urgent, running_out_today
- **loss_cause (4):** selling_at_loss, no_cost_price, margin_below_15, seller_discount_killer
- **gain (2):** top_profit_30d, profit_growth
- **gain_cause (5):** highest_margin, trending_up, loyal_customers, basket_driver, size_leader
- **order (2):** bestseller_low_stock, lost_demand_match
- **anti_order (3):** zombie_45d, declining_trend, high_return_rate

Всяка функция — signature + TODO body + `INSERT INTO ai_insights` заготовка.

## Deliverables

- ✅ Всички S77 таблици съществуват в production DB
- ✅ 3/3 P0 bugs verified fixed
- ✅ compute-insights.php с 15 skeleton функции в `/var/www/runmystore/`
- ✅ Git tag: `v0.5.0-s78-foundation`

## Git

```bash
# След всеки успешен fix:
cd /var/www/runmystore && git add -A && git commit -m "S78: [fix description]" && git push origin main

# Накрая на сесията:
git tag v0.5.0-s78-foundation && git push --tags
```

---

# 📚 ЧЕТИВО ЗА СЛЕДВАЩАТА СЕСИЯ (S78)

| Файл | Секция | Защо |
|---|---|---|
| `MASTER_COMPASS.md` | Цял | Router + dependency map + next action |
| `DOC_01_PARVI_PRINCIPI.md` | Цял | 5-те закона (не се нарушават никога) |
| `SESSION_77_HANDOFF.md` | Цял | P0 bugs детайли, S77 решения |
| `BIBLE_v3_0_APPENDIX.md` | §6, §8, §11 | 6-те въпроса закон, orders екосистема, DB миграция SQL |
| `BIBLE_v3_0_TECH.md` | §14 | Пълна DB schema (products, inventory, sales колони) |
| `DOC_05_DB_FUNDAMENT.md` | §§2-7, §16A | Migrations system, money cents, audit log, нови таблици |
| `DOC_08_PRODUCTS.md` | §11-12 | P0 bugs + compute-insights |
| `PRODUCTS_DESIGN_LOGIC.md` | §12, §14 | 15 compute-insights функции + DB queries |
| `OPERATIONAL_RULES.md` | Цял | Python scripts only, git workflow, comm style |

**Максимум: 9 файла. Не всичко.**

---

# 🌳 DEPENDENCY TREE — МОДУЛ ПО МОДУЛ

> **Правило:** когато Claude пипа модул X, трябва да знае кои логики X пипа, кои модули зависят от X, и какво може да се счупи.

## 🏠 products.php

**Роля:** централна таблица на всеки артикул. Източник на истина за stock, price, supplier, category.  
**Размер:** 5919 реда (ще расте до ~8000 след S78-S82).

### DB READ
```
products (главна таблица)
inventory (quantity per store)
suppliers + supplier_categories (за филтри)
categories + subcategories
sale_items (last 30d — за sold_30d column)
ai_insights WHERE module='products' (за 6-те секции)
ai_shown (cooldown — да не показва повторно)
lost_demand (за "Какво да поръчаш" секция)
biz_learned_data (AI учи нови размери/цветове)
```

### DB WRITE
```
products (INSERT при wizard save, UPDATE при edit)
product_variations (ако имат варианти)
inventory (INSERT при нов product, UPDATE при ръчна корекция)
wizard_draft (crash recovery при S79+)
ai_shown (когато Пешо тапне на insight)
audit_log (всяка промяна — S79+)
stock_movements (след S81 append-only ledger)
```

### SHARED HELPERS (ползва)
```
config/database.php → DB::run(), DB::fetch(), DB::tx()
config/helpers.php → priceFormat(), auth(), getTenant(), getCurrentStore()
config/i18n.php → t() за преводи (S96)
js/printer.js → Bluetooth print (S81+)
compute-insights.php → getInsightsForModule('products')
build-prompt.php → AI Wizard voice parser
```

### UI COMPONENTS (shared)
```
.glass .shine .glow (Neon Glass CSS)
.q1-.q6 hue CSS (6-те фундаментални цвята)
.rec-ov .rec-box (voice overlay, S56 стандарт)
.bottom-nav (52px, AI·Склад·Справки·Продажба)
.numpad (custom, без native keyboard — от sale.php)
```

### AFFECTED BY (кои сесии го променят)
- S78: P0 bugs fix, compute-insights hook
- S79: DB audit_log trigger за INSERT/UPDATE
- S80: wizard rewrite (4 стъпки + matrix overlay)
- S81: AI features (voice, Gemini Vision, fal.ai, Bluetooth)
- S82: filter drawer + CSV import + polish
- S83: orders.php чете products (чете-only — не променя products)
- S85: sale.php чете products (ако се промени schema → sale.php трябва да се обнови)
- S87: inventory.php чете-пише inventory (зависи от products schema)
- S91: Shopify sync (ако products schema се промени)

### AFFECTS (кои модули се чупят ако го счупим)
- **Critical:** sale.php, orders.php, inventory.php, deliveries.php, transfers.php — всички четат products.
- **AI:** chat.php, compute-insights.php, ai-action.php — четат products за контекст.
- **Stats:** stats.php — агрегира по products.
- **Life Board:** simple.php/chat.php — показва сигнали от products.

### LOGIC TOUCHES
- Price calculation (retail_price + discount + wholesale)
- Stock tracking (inventory.quantity per store)
- Supplier categorization (supplier_categories filter)
- Product variations (размери × цветове matrix)
- Barcode scanning (barcode column)
- AI context (който артикул се купува заедно с какво)
- Bluetooth print (TSPL generation)

### RULES (закони за products.php)
1. `products.retail_price` — НЕ `sell_price`, НЕ `price`
2. `inventory.quantity` — НЕ `qty`, НЕ `stock`
3. `products.code` — НЕ `sku`
4. Никога hardcoded "лв"/"€" — винаги `priceFormat()`
5. Никога hardcoded BG текст — винаги `t('key')` или `$tenant['language']` check
6. Wizard field order: **Доставчик → Категория → Подкатегория → Цена → Брой**
7. Минимален запис = (име + цена + брой) = истински продукт, НЕ чернова
8. `ai_insights.fundamental_question` колона задължителна при всеки insight
9. Матрица вариации: `autoMin = Math.round(qty/2.5)` мин. 1

---

## 📦 orders.php

**Роля:** екосистема, не CRUD. Групира по доставчик. Проследява цикъл draft→sent→received.  
**Статус:** не съществува (S83 нов файл).

### DB READ
```
supplier_orders (главна — 11 типа × 8 статуса)
supplier_order_items (всеки артикул с fundamental_question + source + ai_reasoning)
supplier_order_events (audit trail)
suppliers (за групиране)
products (за име, цена, снимка, sold_30d)
inventory (за current stock vs need)
lost_demand WHERE resolved=0 (auto-feed)
ai_insights WHERE fundamental_question='order' (препоръки)
```

### DB WRITE
```
supplier_orders (INSERT при нов draft, UPDATE при status change)
supplier_order_items (INSERT при добавяне, UPDATE qty_received при receive)
supplier_order_events (INSERT при всяка промяна — append-only)
lost_demand (UPDATE resolved_order_id при match)
products (IGNORE — orders не пипа products schema)
inventory (UPDATE при status='received' — +qty_received)
```

### ВХОДНИ ТОЧКИ (12 — кои модули викат addToOrder())
```
1.  products.php (секция "Какво да поръчаш" + detail → "Поръчай още")
2.  chat.php (AI signal action button)
3.  home.php / simple.php (pulse signal)
4.  sale.php (quick-create toast + размер липсва → auto)
5.  delivery.php (недостиг → "Поръчай липсите")
6.  inventory.php (след броене → "под min")
7.  warehouse.php (нов бутон)
8.  voice overlay (навсякъде)
9.  lost_demand auto-feed (AI, cron)
10. basket analysis (AI, cron)
11. manual (от самия orders.php)
```

### SHARED HELPERS
```
addToOrder($product_id, $qty, $source, $source_ref, $fundamental_question)
  → getSupplierIdForProduct($product_id)
  → createOrFindDraft($supplier_id)
  → generateReasoning($product_id, $question, $source)
  → recalculateOrder($draft_id)
  → logOrderEvent($draft_id, $event_type, $payload)

sendOrder($order_id)
  → validate supplier info (phone/email/address)
  → status='sent', sent_at=NOW()
  → trigger email/sms/copy-to-clipboard
  → status state machine check

receiveOrder($order_id, $items)
  → per item qty_received UPDATE
  → inventory UPDATE (+qty)
  → status='received' или 'partial'
```

### UI COMPONENTS
```
Supplier card (logo, meta, status badges, KPI strip, progress bar)
Order detail overlay (secionirano по 6-те въпроса)
Alternative views: by status, by 6 questions, calendar
Status badges (draft/confirmed/sent/acked/partial/received/cancelled/overdue)
Alert banner (ако има overdue)
```

### AFFECTED BY
- S78: DB таблиците трябва да съществуват преди S83
- S83: нов файл, v1
- S84: lost_demand AI draft
- S87: inventory feed-ва orders (след броене)
- S88: Pusher real-time (ако друг user е отворил същия draft)

### AFFECTS
- products.php (четат orders за "Поръчано?" pill)
- deliveries.php (receive идва след sent order)
- inventory.php (qty_received → +inventory.quantity)
- stats.php (агрегира по supplier_orders)
- Life Board (показва "Чернова готова" сигнали)

### LOGIC TOUCHES
- State machine (8 статуса, valid transitions)
- 1 поръчка = 1 доставчик (combined = planning, splits при send)
- ROI tracking (lost_demand → resolved → matched_product → sold → profit)
- 6-те въпроса per item (не per order)
- Anti-Order filter (AI отхвърля artикuli с anti_order тип)

### RULES
1. 1 supplier_order има само 1 supplier_id (combined splits при send)
2. `supplier_orders.status` ENUM ('draft','confirmed','sent','acked','partial','received','cancelled','overdue')
3. `supplier_order_items.fundamental_question` задължителна
4. `supplier_order_events` append-only, никакви UPDATE
5. Draft detail УИ — секционирано по 6-те въпроса
6. Anti-Order артикули = визуално сиви, WARN преди add

---

## 💰 sale.php

**Роля:** касов модул. Voice primary, camera always-live, numpad custom.  
**Статус:** базово работи, S85 rewrite.

### DB READ
```
products + inventory (за lookup при барcode/voice)
product_variations
customers + loyalty_customers (FREE план)
sales (parked, за ⏸)
supplier_orders (за "Последен път продадено, поръчай отново?")
ai_insights WHERE module='sale'
```

### DB WRITE
```
sales (INSERT при нова продажба, UPDATE при cancel)
sale_items (INSERT per артикул)
inventory (UPDATE quantity -= sold)
inventory_events (INSERT 'sale' event — S87+)
search_log (INSERT при voice/keyboard search)
lost_demand (INSERT ако barcode miss или размер липсва)
loyalty_points (UPDATE ако customer известен)
audit_log (S79+)
parked_sales (INSERT при ⏸, DELETE при resume)
```

### SHARED HELPERS
```
config/database.php → DB::tx() (transaction — sale + items + inventory едновременно)
addToOrder() (при lost_demand auto-feed)
logAudit() (audit trail)
```

### UI COMPONENTS
```
Camera-header (80px, винаги жив, зелена лазерна линия)
Custom numpad (контекстен: код/бройки/отстъпка/получено)
BG фонетична клавиатура (при [АБВ→] toggle)
Parked sales strip (⏸ swipe)
Едро toggle (цветна смяна)
Voice overlay (S56 стандарт)
Toast feedback (успех/грешка/lost_demand created)
```

### AFFECTED BY
- S78: DB таблици (lost_demand нови колони)
- S85: rewrite Част 1 (camera + numpad)
- S86: rewrite Част 2 (voice + search_log)
- S87: rewrite Част 3 (toast + pills + inventory_events)

### AFFECTS
- inventory.php (inventory.quantity променя)
- orders.php (auto-feed lost_demand)
- stats.php (revenue, top-selling, customer patterns)
- Life Board (real-time signals: "продаде X")

### LOGIC TOUCHES
- Barcode scan → product lookup (must NOT block UI)
- Voice: "Nike 42" → fuzzy match → select → add
- Parked sale (allocated_millis reservation — S80+)
- Cancel sale (reverse inventory — idempotent)
- Customer loyalty (ако има customer_id → loyalty_points)
- Discount rules (per-user max_discount_pct, seller warning)
- Toast-driven quick actions (поръчай отново? / размер няма → добави?)

### RULES
1. Камерата винаги е жива (зелена линия, бийп при сканиране)
2. Никога native клавиатура — само custom numpad
3. `sales.status` = `'canceled'` (едно L, не две)
4. Voice → транскрипция първо, action после (Закон №1A)
5. DB::tx() обгражда sale + items + inventory
6. Offline queue (IndexedDB) → sync при online

---

## 📥 deliveries.php

**Роля:** OCR фактура + voice + wizard. Категория broene след доставка.  
**Статус:** не съществува (S86 нов).

### DB READ
```
suppliers (за match)
products (за match per item)
supplier_orders WHERE status IN ('sent','acked') (отворени поръчки за match)
scanner_supplier_templates (OCR knowledge base — Phase C S106)
scanner_supplier_whitelist + scanner_vies_cache (Phase C S105)
```

### DB WRITE
```
deliveries (INSERT нова)
delivery_items (per item)
inventory (UPDATE +qty)
inventory_events (INSERT 'delivery')
scanner_documents (OCR resultats)
scanner_audit_log (Phase C)
supplier_order_items.qty_received (ако е от поръчка)
products (UPDATE ако нови размери/цветове научени → biz_learned_data)
```

### SHARED HELPERS
```
OCR wrapper (S86 nov — fal.ai / Gemini Vision)
addToOrder() при "Поръчай липсите"
logAudit()
build-prompt.php (за AI parsing)
```

### UI COMPONENTS
```
Камера за OCR снимка
Preview таблица (editable)
Voice overlay (при voice add)
Manual wizard (fallback)
```

### AFFECTED BY
- S78: DB таблици
- S86: пълен nov файл
- S87: category count trigger (delivery-driven)
- S101-S107: Phase C AI Safety 6 нива

### AFFECTS
- inventory.php (+qty при receive)
- orders.php (qty_received close или partial)
- products.php (ако scanner_supplier_templates научи нови SKUs)

### LOGIC TOUCHES
- OCR → structured JSON (items, qty, unit_cost)
- Fuzzy match към съществуващи products (barcode първо, после име)
- Нови items → auto-create в products.php (wizard path)
- Delivery-triggered category count (inventory-friendly)
- Unit cost UPDATE на products (за margin tracking)

### RULES
1. OCR резултати минават 7 AI Safety нива (Phase C)
2. Всяко unmatch item → визуален warning, не auto-insert
3. Scanner audit log append-only
4. VIES cache 30 дни (BG EIK проверки)

---

## 📊 inventory.php + warehouse.php (HUB)

**Роля:** warehouse.php = hub екран (5 подмодула cards). inventory.php = counting flow.  
**Статус:** warehouse скелет, inventory v3 работи, v4 rewrite в S87.

### warehouse.php (HUB)

Показва 5 card-а с ключово число + fundamental_question:
- Артикули → products.php
- Доставки → deliveries.php
- Поръчки → orders.php
- Трансфери → transfers.php
- Инвентаризация → inventory.php

**НЕ съдържа логика** — само navigation + aggregated KPIs от всеки подмодул.

### inventory.php (v4 rewrite в S87)

### DB READ
```
products + inventory (текущо)
store_zones (зонирани секции в магазина)
zone_stock (кой артикул в коя зона)
inventory_count_sessions (отворена ли е инвентаризация)
inventory_count_lines (прогрес)
inventory_events (event-sourced, S87+)
sales (за confidence model)
deliveries (за confidence model)
```

### DB WRITE
```
inventory_count_sessions (INSERT при start, UPDATE при resume/close)
inventory_count_lines (INSERT per zone walk entry)
inventory_events (INSERT 'zone_walk' или 'adjustment')
inventory (UPDATE при close session — reconciliation)
audit_log
```

### SHARED HELPERS
```
Smart Business Logic Resolver (auto-resolve конфликти — 0 конфликта UX)
Confidence model (как confidence расте)
DB::tx() (reconciliation — inventory + events atomic)
```

### UI COMPONENTS
```
Zone Walk UI (секция по секция, филтър chips, barcode scanner)
Fast mode (под 500 артикула) / Full mode (500+)
Crash recovery (auto-save на 10 сек)
Confidence visual (🟢🟡🔴 per артикул)
Duplicate warning toast
```

### AFFECTED BY
- S78: DB skelet (inventory_events още няма)
- S87: пълен v4 rewrite
- S88: offline mode (IndexedDB queue)
- S89: multi-device concurrent counting (FOR UPDATE)

### AFFECTS
- products.php (quantity shown)
- sale.php (достъпност)
- orders.php (ако под min → order draft)
- deliveries.php (category count triggered)
- stats.php (accuracy % metric)

### LOGIC TOUCHES
- Event-sourced (всяко broene = event, не overwrite)
- Smart Resolver (baseline_before_event + delta reconciliation)
- Confidence per product (colour-coded)
- Zone walk (секция по секция, не random)
- Crash recovery (10s auto-save)
- Multi-device: последната дума на бизнес логиката (не timestamp)

### RULES
1. inventory_events append-only (V5 заключено)
2. НЕ CRDT (виж BIBLE TECH §9.6.7)
3. 12 правила за Inventory v4 (виж BIBLE TECH §9.8)

---

## 🔁 transfers.php

**Роля:** многомагазинен трансфер. Между stores на един tenant.  
**Статус:** не съществува (S92).

### DB READ
```
stores (всички на tenant)
products + inventory per store
```

### DB WRITE
```
transfers (INSERT)
transfer_items (per артикул)
inventory (DECREMENT source, INCREMENT destination — tx)
inventory_events ('transfer')
```

### LOGIC TOUCHES
- `store_id` колони във всичко (vs. tenant_id)
- Multi-store resolver (който store е активен сега)
- Auto-suggest: ако store A има zombie + store B търси → transfer

---

## 📈 stats.php

**Роля:** 5 таба, role-based visibility, drawer при click, AI препоръки.  
**Статус:** базово работи, S93 rewrite.

### DB READ (преиспользва всичко)
```
sales, sale_items (revenue, top-seller)
products, inventory (stock health)
supplier_orders (pipeline)
deliveries (cost tracking)
customers, loyalty (behavior)
ai_insights (препоръки)
```

### LOGIC TOUCHES
- Role-based visibility (owner sees all, manager sees store, seller sees own)
- Click на число → drawer с детайли
- 5 таба: Продажби · Склад · Клиенти · Служители · Поръчки
- 6-те въпроса — как се appy за stats
- Никога "оборот" / "марж" — само **profit**

---

## 💬 chat.php + simple.php/life-board.php

**Роля:** chat.php = Detailed Mode dashboard. simple.php = Simple Mode AI chat (Пешо).  
**Статус:** chat.php v7 работи, simple.php не съществува (S95).

### chat.php DB READ
```
ai_insights WHERE module IN ('home','all')
ai_shown (cooldown)
sales, products, suppliers — за live stats
chat_messages (история)
tenant_ai_memory (long-term)
weather_forecast (за сезонни препоръки)
```

### DB WRITE
```
chat_messages (INSERT)
ai_shown (при tap)
tenant_ai_memory (при научаване)
api_cost_log (tracking AI spend)
ai_audit_log (Phase C)
```

### LOGIC TOUCHES
- Confidence routing (>92% direct, 75-92% confirm UI, <75% block)
- Voice fallback ladder (Web Speech → Whisper Groq → degradation)
- 3-tier architecture (realtime 150ms / hourly / nightly)
- Signal Detail Overlay (НЕ chat при tap)
- 70% chat overlay (при explicit chat open)
- Greetings по час
- 857 AI теми (Selection Engine MMR λ=0.75)
- Fact Verifier (Phase C)

### SHARED
```
build-prompt.php (8 layers — role, product data, weather, etc.)
getInsightsForModule()
ai-action.php router (S94+)
```

### UI COMPONENTS
```
Revenue card
Store Health bar
Chat scroll zone
Input bar с voice FAB
Signal Detail Overlay
70% chat overlay
Bottom nav (detailed only)
```

---

## 🎛️ ai-action.php

**Роля:** hybrid router. AI intent → action execution.  
**Статус:** не съществува (S94).

### LOGIC TOUCHES
- Action Broker L0-L4 (виж BIBLE TECH §10.2)
- `$MODULE_ACTIONS` convention — всеки модул декларира actions
- Security validation (permission per role)
- Audit log append-only
- Idempotency keys
- Dry-run mode (Phase C)

### SHARED
```
Всички модули трябва да декларират $MODULE_ACTIONS в своя PHP
Пример:
  $MODULE_ACTIONS = [
    'add_product' => ['requires' => ['owner','manager'], 'params' => [...]],
    'print_label' => ['requires' => ['any'], 'params' => [...]]
  ];
```

---

## 🧠 compute-insights.php

**Роля:** generates ai_insights rows. Cron-driven.  
**Статус:** 0 функции, S78 skeleton (15) → S84 (+20) → S92 (+30) → S121+ (target 100+).

### DB READ (всичко)
```
products, inventory, sales, sale_items, supplier_orders,
lost_demand, search_log, biz_learned_data, weather_forecast
```

### DB WRITE
```
ai_insights (INSERT с fundamental_question)
```

### CRON SCHEDULE
```
cron-insights.php → every 15 min (fast signals)
cron-hourly.php → hourly aggregates
cron-nightly.php → 03:00 (heavy computes)
cron-morning.php → 08:00 local (briefing)
cron-evening.php → 21:00 local (wrap)
cron-weather.php → 06:00
```

### LOGIC TOUCHES
- Всяка function връща {topic_id, pill_text, value_numeric, action, fundamental_question}
- Dedup (ако същият insight съществува → UPDATE)
- Expires_at (auto-cleanup)
- Idempotent (повторно run не дуплицира)

---

# 🔗 CROSS-MODULE IMPACT MATRIX

> **Правило:** при промяна в колона на единия модул → провери impact на всички модули дето четат.

## Когато пипнеш X → провери Y

| Промяна в | Засяга модули | Тип impact |
|---|---|---|
| `products.code` rename | sale.php, orders.php, inventory.php, deliveries.php, transfers.php, stats.php | SEVERE — SQL queries break |
| `products.retail_price` schema | sale.php, stats.php, orders.php (total_cost) | HIGH — calculations break |
| `inventory.quantity` schema | sale.php, warehouse.php, stats.php, compute-insights.php | SEVERE — stock tracking breaks |
| `sales.status` ENUM values | sale.php, stats.php, cron-nightly.php | HIGH — state machine breaks |
| `ai_insights.fundamental_question` | ВСИЧКИ модули (6 секции UI), compute-insights.php | SEVERE — 6-те въпроса закон |
| `supplier_orders.status` ENUM | orders.php, deliveries.php, stats.php, cron-hourly.php | HIGH — state machine |
| Neon Glass CSS (.glass .q1-.q6) | ВСИЧКИ UI модули | VISUAL — design system |
| Voice overlay CSS (.rec-ov .rec-box) | products.php, sale.php, chat.php, orders.php, deliveries.php | VISUAL |
| `priceFormat()` helper | ВСИЧКИ модули | HIGH — currency display breaks |
| `addToOrder()` helper | products.php, chat.php, home.php, sale.php, delivery.php, inventory.php, warehouse.php, voice | HIGH — 12 входни точки |
| Bottom nav structure | chat.php, products.php, sale.php, warehouse.php, stats.php | VISUAL |
| `$MODULE_ACTIONS` convention (S94+) | ВСИЧКИ модули + ai-action.php | HIGH — AI routing breaks |
| `DB::tx()` wrapper (S79) | sale.php, orders.php, inventory.php, deliveries.php, transfers.php | HIGH — atomic ops |
| `audit_log` schema | ВСИЧКИ модули които пишат DB | MEDIUM — audit trail |
| 5-те непроменими закона | ВСИЧКИ модули | CRITICAL — ако нарушен, cascade rework |

## Shared Resources Invariants

### DB invariants (НЕ се нарушават)
1. `tenant_id` винаги присъства в WHERE clauses (tenant isolation)
2. `store_id` присъства в inventory, sales, deliveries, transfers
3. Всяка tenant-specific таблица има (tenant_id, …) composite FK (S80+)
4. Money columns → `_cents BIGINT` след S79 (не DECIMAL)
5. Времеви колони → UTC винаги, per-tenant tz конверсия на display

### Code invariants
1. `DB::run()` с `?` placeholders, НЕ `{$var}` интерполация
2. `php -l file.php` преди всеки commit
3. Python patch scripts, НЕ sed
4. `priceFormat($amount, $tenant)` навсякъде за пари
5. `t('key')` или `$tenant['language']` за текстове (никога hardcoded BG)
6. "AI" в UI, НЕ "Gemini"
7. `profit`, НЕ "оборот" / "марж"
8. `sales.status = 'canceled'` (едно L)

### UI invariants
1. Mobile-first, 56px bottom nav, safe-area-insets, viewport-fit=cover
2. Min tap target 44×44px
3. Neon Glass pattern: `<div class="glass sm qX art"><span class="shine"></span>...`
4. 6-те hue classes (q1-q6) — с фиксирани hue1/hue2 стойности
5. Voice overlay от S56 стандарт — никой модул не прави свой
6. Никаква native клавиатура в sale.php/numpad контексти

---

# 📝 LOGIC CHANGE LOG

> **Правило:** когато Тихол промени решение или върне назад, record в този лог. Всеки chat при стартиране проверява тук за влияние върху текущата задача.

**Reverse chronological (newest first).**

## 23.04.2026 — S79.VISUAL_REWRITE: chat.php v8 (home-neon-v2 design)

- **Решение:** Пълен визуален rewrite на chat.php към home-neon-v2 design (Neon Glass с conic-gradient shine + glow). Всички S79 функции запазени. 3 × 75vh overlays (Chat, Signal Detail, Browser) със същия дизайн, blur фон отдолу.
- **Защо 75vh:** Чат като WhatsApp app, signal detail като обяснителна страница. Хардуерен back бутон + swipe down + ESC.
- **Засегнати:** chat.php (1605 → 2094, +489). 0 DB промени.
- **Нови функции:** history.pushState back, swipe-to-close, ESC, body.overlay-open blur+scale, pulsing SVG AI icon.
- **Rework затворени:** visual consistency с DESIGN_SYSTEM.md
- **Commit:** 44aafab
- **Tag:** v0.5.5-s79-visual
- **Статус:** ✅ done — production, verified



## 23.04.2026 — S82.CAPACITOR.2: Capacitor runtime hosted on runmystore.ai
- **Решение:** Вместо да разчитаме на Capacitor's auto-injection (която не работи надеждно на Samsung Z Flip6 WebView), хостваме `native-bridge.js` + `@capacitor/core` + `bluetooth-le` в `/js/capacitor/` на сървъра. `js/capacitor-printer.js` сам ги инжектира през document.write преди всяка печатна операция.
- **Защо:** `window.Capacitor` остана undefined в APK → BLE plugin не работеше. Нашето решение инициализира bridge-а manually от страна на server-served JS, без нужда от auto-injection.
- **Засегнати:** includes/capacitor-head.php (нов), js/capacitor-bundle.js (нов), js/capacitor/*.js (3 нови), js/capacitor-printer.js (rewrite), printer-setup.php, ua-debug.php (богат debug), mobile/capacitor.config.json (без редундантен hostname), mobile/www/index.html (fallback redirect)
- **products.php:** НЕ е пипана (правилото от handoff). Старият `<script src="js/capacitor-printer.js">` автоматично взима новото поведение.
- **Статус:** ⏳ deployed — awaiting Тихол device test. Ако `/ua-debug.php` покаже `window.Capacitor: object` и `BleClient: function`, old APK стига. Иначе — нов APK build ще е готов в GitHub Actions.
- **Commit:** 5bddc81

## 22.04.2026 — S82.CAPACITOR частично завършена, блокер за S82.CAPACITOR.2
- **Завършено:** Node 22 + mobile/ + BLE plugin 8.1.3 + GitHub Actions + APK build работи + index.php router + .htaccess fix + safe-area fix (6 files) + capacitor-printer.js + wizPrintLabelsMobile hook + printer-setup.php + ua-debug.php
- **Блокер:** APK отваря runmystore.ai в **external Chrome browser**, не в Capacitor WebView. `window.Capacitor` е undefined → BLE plugin не работи.
- **Доказано:** UA-то от APK няма `wv` маркер. Пробвани са 3 config варианта, нито един не инжектира bridge.
- **Следваща стъпка:** Claude Code поема задачата с SESSION_S82_CAPACITOR_HANDOFF.md като референция. Варианти: hybrid local+fetch, iframe+postMessage, различна Capacitor version, custom WebView activity.
- **Засегнати:** mobile/, js/capacitor-printer.js, printer-setup.php
- **Статус:** ⏳ Нe е продакшън готов



## 22.04.2026 — S79.CHAT_INTEGRATION: chat.php → ai_insights свързан

- **Решение:**
  (а) build-prompt.php получава 6Q context block от ai_insights (готови числа за AI)
  (б) chat.php проактивни pills с 6h cooldown + Signal Detail с fq actions
  (в) нов endpoint mark-insight-shown.php за tracking
  (г) ORDER BY narrative flow: loss → loss_cause → gain → gain_cause → order → anti_order
- **Защо narrative flow:** Разказвателен поток е по-естествен за AI reading context. Старият priority order (loss→loss_cause→anti_order→order→gain→gain_cause) остава за Selection Engine когато избираме top-3.
- **Засегнати модули:** build-prompt.php, chat.php, mark-insight-shown.php (нов), ai_shown (INSERT flow), BIBLE §6.5
- **Rework затворени:** #3 (chat.php AI prompts + fundamental_question)
- **Нови rework:** S79.FIX (fq-badge в Signal Detail overlay, priceFormat/qtyFormat в top-pill values)
- **Статус:** ✅ done
- **Commits:** 8a91c27 (ETAP 1 build-prompt), 33eb831 (ETAP 2+3 chat.php + endpoint)
- **Tag:** v0.5.4-s79-chat-integration

## 22.04.2026 — S79.CRON_AUDIT завършено (CHAT 3)
- **Решение:** cron-insights.php wrapper (15 min), audit_log extension, cron_heartbeats, auditLog() v2
- **Засегнати:** compute-insights.php (guard), config/helpers.php, new: cron-insights.php, /etc/cron.d/runmystore, migrations/20260422_002_*
- **REWORK QUEUE #10:** DONE
- **Tag:** v0.5.3-s79-cron-audit
- **Commit:** 75e10fa

## 22.04.2026 — S81 Printer decision: 3-фазен план

- **Решение:** DTM-5811 е Bluetooth Classic (BT 2.1), Web Bluetooth API не работи с него. 3-фазен план:
  - Днес (S81): CSV → QU Printing app
  - Нов чат (S82): Capacitor native plugin за DTM-5811 (Kotlin, BT Classic SPP, TSPL)
  - Преди client launch: Universal plugin (всички brands)
- **Защо:** 200 DTM-5811 поръчани. Capacitor native е единственото надеждно решение.
- **Prerequisites за S82:** Android Studio, JDK 17+, Android SDK 34+, Capacitor проект.
- **Изтрити файлове:** js/printer.js, print.php, print-test.php (неуспешен Web Bluetooth опит).
- **Статус rework:** ⏳ S82 (DTM) + S85.5 (Universal).

## 22.04.2026 — S79.DB COMPLETE (CHAT 2 parallel session)
- **Решение:** DB foundation v1 готов: schema_migrations + Migrator + audit_log helper + DB::tx() + soft delete на 5 таблици
- **Засегнати модули:** config/database.php, config/helpers.php, lib/Migrator.php (нов), migrate.php (нов), migrations/ (нов)
- **DB changes:** +1 таблица (schema_migrations), +5 ALTER (suppliers/customers/users/stores/categories с deleted_at/by/reason + idx)
- **Rework генериран:** S79.SECURITY (P0), S79.AUDIT.EXT (P1), S80 deadlock retry (P1), S80 SAVEPOINT (P2)
- **Tag:** v0.5.1-s79-db (commit eca6506)
- **Status rework:** ⏳ S79.SECURITY P0 — ЗАДЪЛЖИТЕЛНО преди следваща сесия

## 21.04.2026 — S78 closeout

- **Решение:** S78 затворена с DB + skeleton + verification. Bug #6 отложен.
- **Защо:** #5 и #7 вече fix-нати в стари сесии (grep verified). #6 блокиран от S79 wizard breakage — "+ Добави" не отваря wizard, следователно renderWizard не може да се тества.
- **Засегнати модули:** products.php (untouched този S78), MASTER_COMPASS §2, §6
- **Rework:** Нова сесия **S79.FIX** — приоритет P0 bugs от `PRODUCTS_MAIN_BUGS_S80.md` (10 счупени бутона на главната). След S79.FIX → retry Bug #6 → S80 (wizard rewrite).
- **Статус rework:** ⏳ pending (S79.FIX)

## 21.04.2026 — MASTER_COMPASS v3.0 създаден
- **Решение:** Добавен dependency tree + change protocol + rework queue
- **Защо:** Тихол иска да се знае при всяка промяна какво се засяга
- **Засегнати:** всички chat-ове при отваряне (нов стартов протокол)
- **Rework:** нищо (нова функционалност)

## 21.04.2026 — WooCommerce/Shopify преместени от Phase 6 → Phase B (S90-S91)
- **Решение:** Beta tenant с онлайн магазин изисква integration преди public launch
- **Засегнати:** products.php (sync), inventory.php (sync), orders.php (fulfillment), REAL_TIME_SYNC
- **Rework:** Roadmap.md обновена, MASTER_COMPASS §"Phase B" добавен

## 21.04.2026 — Multi-device real-time sync = critical S88-S89
- **Решение:** 5+ магазина + 2+ онлайн канала изисква Pusher архитектура от ден 1
- **Засегнати:** sale.php, inventory.php, products.php (всички пишещи модули)
- **Rework:** DOC 05 §8 (locking), нов файл REAL_TIME_SYNC.md

## 19.04.2026 — 6-те фундаментални въпроса = ЗАКОН (S77)
- **Решение:** Всеки AI отговор, pill, UI секция — структурирани по 6-те
- **Засегнати:** ВСИЧКИ модули
- **Rework:** 
  - products.php home → 6 секции (S79)
  - ai_insights.fundamental_question колона (S78)
  - supplier_order_items.fundamental_question колона (S78)
  - Всички съществуващи UI pills трябва да се класифицират
  - CSS q1-q6 hue system в shared stylesheet

## 19.04.2026 — Warehouse hub архитектура (S77)
- **Решение:** warehouse.php = hub, orders не е bottom nav tab
- **Засегнати:** warehouse.php (rewrite), bottom nav (4 таба не 5)
- **Rework:** S87 пренаписва warehouse от нулата, breadcrumb "← Склад"

## 19.04.2026 — Products.php приоритет ПЪРВИ (S77)
- **Решение:** products.php пълно завършване преди orders.php
- **Защо:** orders чете products, частичен products = частичен orders
- **Rework:** Стар Roadmap.md имаше orders в S81, преместено в S83

## 18.04.2026 — 9 CONSOLIDATION decisions (К1-К7+В4+В5)
- К1: Trial 4 месеца (PRO month 1 безплатен, month 2-4 PRO на START цена)
- К2: Ghost pills OFF
- К3: AI персона = управител, не императиви
- К4: Simple Mode → life-board.php заменя simple.php (прогресивно разкриване 1→6)
- К5: Onboarding ENUM в DB + Life Board UI (4 етапа)
- К6: Партньори FLAT ISR (15% territory + 50%×6 месеца referral, Stripe Separate Charges)
- К7: NARACHNIK отделен файл
- В4: DB spelling `'canceled'` (едно L)
- В5: `inventory_events` event-sourced (НЕ CRDT)
- **Rework:** целият CONSOLIDATION_HANDOFF.md документира decisions

## 17.04.2026 — BIBLE v3.0 (3 файла)
- **Решение:** BIBLE_CORE + BIBLE_TECH + BIBLE_APPENDIX
- **Засегнати:** цялата документация
- **Rework:** старата BIBLE v2.3 deprecated

## 16.04.2026 (S71) — Wizard 4 стъпки FINAL
- **Беше:** Spor между 3-accordion (S71 дата 15.04) vs 4-step (S71 дата 16.04)
- **Решение:** 4 стъпки побеждават (consensus от 4 AI анализатори)
- **Rework:** BIBLE v2.1 Additions (15.04) ADDITIONS overwritten

## Template за ново решение (Claude попълва при добавяне)

```markdown
## [ДАТА] — [SHORT TITLE]
- **Решение:** ...
- **Защо:** ...
- **Засегнати модули:** ...
- **Rework необходим:** ...
- **Статус rework:** ⏳ pending / ✅ done
```

---

# 🔄 REWORK QUEUE — НЕЗАВЪРШЕНИ ПОСЛЕДСТВИЯ

> **Правило:** когато decision change засяга модул който още не е докоснат в текущата задача, добавяме тук. Когато дойде сесията на този модул — chat-ът проверява queue и ги закрива.

| # | Засегнат модул | Произход (дата/решение) | Какво да се направи | Кога (сесия) | Статус |
|---|---|---|---|---|---|
| 1 | products.php UI pills | 19.04.2026 (6-те въпроса закон) | Класифицирай всички съществуващи pills с fundamental_question | S79 | ⏳ pending |
| 2 | ai_insights съществуващи редове | 19.04.2026 (6-те въпроса закон) | Migration script попълва fundamental_question за стари редове | S78 | ⏳ pending |
| 3 | chat.php AI prompts | 19.04.2026 (AI tag per insight) | build-prompt.php добавя fundamental_question в context | **S79 ✅ done 22.04.2026** | ✅ closed |
| 9 | chat.php Signal Detail | 22.04.2026 (S79 S79.FIX) | Добави fq-badge (q1-q6) в overlay header + priceFormat/qtyFormat в top-pill values | S79.FIX или CHAT 4 rewrite | ⏳ pending |
| 10 | chat.php visual | 22.04.2026 (home-neon-v2 approved) | Пълен CSS rewrite към home-neon-v2 дизайн, запазвайки S79 PHP логика | CHAT 4 | ⏳ pending |
| 4 | ВСИЧКИ модули — currency format | 17.04.2026 (i18n) | Замяна hardcoded "лв"/"€" с `priceFormat($amount, $tenant)` | S96 | ⏳ pending |
| 5 | ВСИЧКИ модули — BG текст | 17.04.2026 (i18n) | Замяна с `t('key')` или $tenant['language'] check | S96 | ⏳ pending |
| 6 | products.php wizard state | 16.04.2026 (4 стъпки FINAL) | Премахни стария 3-accordion код остатъци | S80 | ⏳ pending |
| 7 | warehouse.php navigation | 19.04.2026 (hub архитектура) | Всеки подмодул има breadcrumb "← Склад › [Име]" | S87 | ⏳ pending |
| 8 | orders.php bottom nav | 19.04.2026 (orders НЕ е tab) | 4 таба bottom nav, НЕ 5 | S83 | ⏳ pending |
| 9 | Capacitor bridge | 22.04.2026 (S82.CAPACITOR блокер) | Debug защо WebView не инжектира window.Capacitor. Варианти: hybrid mode, iframe, custom activity. | S82.CAPACITOR.2 | ⏳ pending |
| 10 | iOS Capacitor | 22.04.2026 (Android-only сега) | След Android работи — добави iOS plugin като Universal Plugin wrapper | S85.5 | ⏳ pending |
| 9 | products.php wizard | 21.04.2026 (S78 #6 blocked) | Bug #6 renderWizard — verify след като wizard отваря в S79.FIX | S79.FIX | ⏳ pending |
| 10 | products.php main split | 21.04.2026 (S78 CC sweep) | Файлът е 8394 реда (5.6× над 1500 прага) — extract в partials/helpers; кандидат за rewrite | S80 | ⏳ pending |
| 11 | products.php Q-секции (q1-q6 home) | 22.04.2026 (Тихол: "трябва AI да предлага действие иначе безсмислено") | Всеки артикул в Q-секция трябва да има AI-генериран action button: 'Поръчай 5 при Иванов' / 'Промо -20%' / 'Прехвърли в магазин 2' и т.н. Source: ai_insights.action_label + action_type + action_data вече съществуват в DB. Compute-insights.php трябва да попълва тези колони. UI render да чете и показва бутон под всеки item. Tap на бутона → execute action (без чат). | S81 (AI features) | ⏳ pending
| 12 | products.php drawer detail screen | 22.04.2026 (свързано с #11) | Detail drawer също да показва AI primary action отгоре ("Препоръчвам: Поръчай 5 от Иванов — 320 лв profit/седм") + secondary actions. Бил е plain product card. | S81 | ⏳ pending

---

# ❓ PENDING DECISIONS — ЧАКАТ ТИХОЛ

| # | Въпрос | Засяга | Deadline |
|---|---|---|---|
| 1 | fal.ai pricing при мащаб — ОК ли е? | S81 AI Image Studio | преди S81 |
| 2 | Web Bluetooth на iOS работи ли за DTM-5811? | S81 Bluetooth печат | преди S81 |
| 3 | Pusher account creation | S88 | преди S88 |
| 4 | Stripe Connect setup | S107 Partners launch | преди S107 |
| 5 | EU VAT OSS регистрация | S103 GDPR/Legal | преди public launch |
| 6 | AI action button persona — императив ("Поръчай 5") или предложение ("Препоръчвам: Поръчай 5")? | products.php Q-секции | преди S81 |

---

# 📋 ПЪЛЕН S78-S110 ROADMAP

| Сесия | Задача | Модул | Файлове пипнати | Dependency |
|---|---|---|---|---|
| S78 | DB migration + 3 P0 bugs + compute-insights skeleton | products.php, DB | products.php, compute-insights.php, /tmp/s78_*.py | → цяла Phase A blocker |
| S79 | DB foundations 1: schema_migrations, audit_log, DB::tx(), soft delete | DB, helpers | config/database.php, config/helpers.php, migrations/ | S78 done |
| S80 | DB foundations 2: negative stock guard, composite FK, idempotency, cents | DB, helpers | migrations/, products.php patch | S79 done |
| S81 | DB foundations 3: stock ledger, events queue, Bluetooth | DB, products.php, js/ | migrations/, products.php, js/printer.js | S80 done |
| S82 | Products wizard complete + filter drawer + CSV + AI features | products.php | products.php (5919→~8000) | S78-S81 done |
| — | **🎯 PHASE A DONE. ЕНИ first sale: 10-15 май 2026** | | | |
| S83 | orders.php v1 (12 входа, 11 типа, 8 статуса, 6 tabs) | orders.php | orders.php (нов файл) | S78 DB таблици |
| S84 | Lost demand + AI draft | orders.php, lost_demand | orders.php patch, cron-hourly.php | S83 done |
| S82 | **DTM-5811 Capacitor plugin** (Android build, TSPL + BT Classic) | printer-plugin/, products.php | Kotlin plugin, Android Studio, нов APK | critical за ЕНИ beta |
| S82.5 | Products wizard complete + filter drawer + CSV + AI features | products.php | products.php (5919→~8000) | S78-S81 done |
| S85 | sale.php rewrite (voice + camera + numpad) | sale.php | sale.php (rewrite) | S82 products done |
| S85.5 | **Universal Printer Plugin** (TSPL + ESC/POS + ZPL × BT Classic + BLE + WiFi) | printer-plugin/, Settings | Kotlin + Swift | преди client launch |
| S86 | deliveries.php (OCR + voice + wizard) | deliveries.php | deliveries.php (нов) | S82 done |
| S87 | inventory v4 + warehouse hub | inventory.php, warehouse.php | inventory.php (rewrite), warehouse.php (rewrite) | S78 DB |
| S88 | Pusher setup + tenant channel | real-time infra | config/pusher.php, js/pusher.js | external account |
| S89 | Multi-device events + MySQL FOR UPDATE | sale.php, inventory.php | sale.php patch, inventory.php patch | S88 done |
| S90 | WooCommerce integration v1 | new | integrations/woo.php | REAL_TIME_SYNC |
| S91 | Shopify integration v1 | new | integrations/shopify.php | S90 patterns |
| S92 | transfers + multi-store resolver | transfers.php | transfers.php (нов), helpers | S87 inventory |
| S93 | stats.php rewrite (5 таба, role-based) | stats.php | stats.php (rewrite) | all modules data |
| S94 | /ai-action.php router + $MODULE_ACTIONS | ai-action.php | ai-action.php (нов), всички модули patch | все модули S92 |
| S95 | Simple Mode = AI chat (life-board.php) | simple.php | life-board.php (нов) | S94 done |
| S96 | Life Board v1 + Selection Engine + i18n цялостна ревизия | simple.php, all | life-board.php, 17 Life Board files, currency helpers | S95 |
| — | **🎯 PHASE B DONE.** | | | |
| S97 | Capability Matrix + Access Control | security | all modules patch | — |
| S98 | AI Context Leakage Prevention | AI infra | build-prompt.php, chat.php | — |
| S99 | Kill Switch + Cost Guard | AI infra | config/, admin UI | — |
| S100 | Prompt Versioning + Shadow Mode | AI infra | ai_shadow_log | — |
| S101 | Dry-run + DND + Trust Erosion | AI infra | tenants.dnd_*, trust_scores | — |
| S102 | Semantic Sanity + Photo Security + Full Audit | AI infra | Phase C полиране | — |
| — | **🎯 PHASE C DONE.** | | | |
| S103 | Capacitor Offline Queue | mobile | Capacitor plugin | — |
| S104 | GDPR Compliance | legal | export/delete tenant, consent | — |
| S105 | Anomaly Detection + Health Checks | monitoring | health_check_results, cron | — |
| S106 | Advanced Concurrency (2-phase commit, race tests) | infra | — | — |
| S107 | Secrets + Integration Tests | CI/CD | tests/ | — |
| S108 | App Store + Play Store | deploy | build configs | — |
| S109 | Marketing + Landing | marketing | landing page | — |
| S110 | Launch Checklist | — | — | — |
| — | **🎯 PUBLIC LAUNCH — септ 2026** | | | |

---

# 🔄 CHANGE PROTOCOL — КАК СЕ ПРОМЕНЯ ЛОГИКА

> **Това е критичният механизъм който Тихол иска. Всяка промяна на логика или rollback минава през този протокол.**

## Когато Тихол каже "променям X":

### Стъпка 1 — Claude идентифицира impact
Chat-ът:
1. Проверява DEPENDENCY TREE (секция горе) за модула X
2. Изброява всички модули които четат/пишат X
3. Проверява CROSS-MODULE IMPACT MATRIX
4. Казва на Тихол: *"Промяната засяга модули A, B, C. Съгласен ли си да продължа?"*

### Стъпка 2 — Тихол потвърждава
Тихол: "да" / "не" / "само A, не B, не C".

### Стъпка 3 — Claude update-ва MASTER_COMPASS
Трябва да направи всичките:

```
1. Update на съответните секции в DEPENDENCY TREE (ако модулът X промени DB колона / helper signature / UI компонент)

2. Add entry в LOGIC CHANGE LOG:
   ## [ДАТА] — [КРАТКО ЗАГЛАВИЕ]
   - Решение: X → Y
   - Защо: ...
   - Засегнати: A, B, C
   - Rework: списъкът

3. За всеки засегнат модул който НЕ е текущата задача:
   → Add в REWORK QUEUE с кога трябва да се направи

4. Ако променя пълна сесия (напр. S85 rewrite → S85 patch) 
   → Update секция "СЛЕДВАЩА ЗАДАЧА" + "ПЪЛЕН ROADMAP"

5. Ако променя Phase Overview %
   → Update процентите
```

### Стъпка 4 — Commit на COMPASS
```bash
cd /var/www/runmystore && git add docs/compass/MASTER_COMPASS.md && git commit -m "COMPASS: [change description]" && git push origin main
```

### Стъпка 5 — Всички други chat-ове виждат промяната
Следващия път когато друг chat отвори, прочита COMPASS → вижда LOGIC CHANGE LOG най-горе → knows what changed → adapts.

---

## Когато Тихол каже "върнахме се назад от решение Y":

### Стъпка 1 — Claude идентифицира какво беше направено
Търси в LOGIC CHANGE LOG кога е било "Решение Y" → вижда кои модули бяха засегнати, какъв rework беше направен.

### Стъпка 2 — Изброява UNDO списъка
```
"Върнахме решение Y от [ДАТА]. Това означава:
1. Модул A — undo X промяна (ред ~150)
2. Модул B — възстанови старата колона в DB
3. Модул C — премахни нови UI pills
4. REWORK QUEUE entries #N, #M → отменям

Съгласен ли си?"
```

### Стъпка 3 — Тихол потвърждава
"да" / "само A" / "не".

### Стъпка 4 — Изпълнява UNDO + update COMPASS
1. Python patch scripts за всеки модул
2. DB migration (ако е schema change) — rollback SQL
3. Add entry в LOGIC CHANGE LOG с "ROLLBACK на [старата entry]"
4. Update REWORK QUEUE — премахни отменените entries, добави нови ако rollback генерира допълнителен rework

### Стъпка 5 — Verify
```bash
php -l [всеки засегнат файл]
git commit -m "ROLLBACK: [description]"
git push
```

---

## Pattern за commit messages

```
S78: [описание]                       — нормална сесийна работа
S78.B: [описание]                     — под-задача
COMPASS: [описание]                   — само compass update
HOTFIX: [описание]                    — спешен fix
ROLLBACK: [описание]                  — връщане назад
DEPENDENCY: [описание]                — промяна в dependency tree
```

---

# 🔒 ЗАКЛЮЧЕНИ РЕШЕНИЯ (НЕ ПРЕГОВАРЯЙ)

От CONSOLIDATION_HANDOFF (18.04.2026):

| # | Тема | Решение |
|---|---|---|
| К1 | Trial | 4 месеца — month 1 PRO безплатно, months 2-4 PRO на START цена. Карта ден 29. |
| К2 | Ghost pills | OFF. Заместител: PRO седмица |
| К3 | AI персона | Управител. Забранени императиви. |
| К4 | Simple Mode | life-board.php заменя simple.php |
| К5 | Onboarding | ENUM в DB + Life Board UI. 4 етапа. |
| К6 | Партньори | FLAT ISR. 15% + 50%×6мес. Stripe Separate Charges. |
| К7 | NARACHNIK | Отделен файл. |
| В4 | DB spelling | `'canceled'` (едно L). |
| В5 | inventory_events | Event-sourced. НЕ CRDT. |

И **5-те непроменими закона** (DOC 01 + BIBLE_CORE §1):

1. Пешо не пише нищо (voice/tap/photo only)
2. PHP смята, Gemini говори
3. AI мълчи, PHP продължава (fallback ladder)
4. Addictive UX (1/4 тишина)
5. Глобален от ден 1 (i18n, EUR, country_code)

Под-закон №1A: Voice винаги показва транскрипция преди action.

---

# 🚧 АКТИВНИ БЛОКЕРИ

### Hardware/External
- **ЕНИ magazin test** зависи от: DTM-5811 pair + wizard стабилен + бижута инвентаризирани
- **Pusher account** — не създаден (за S88)
- **fal.ai pricing scale** — unknown

### Technical unknowns
- **Web Bluetooth iOS** — известни ограничения за BDA стабилност
- **Stripe Connect setup** — не започнат

### Решения чакащи Тихол
Виж секция "❓ PENDING DECISIONS".

---

# 📊 PHASE OVERVIEW

```
Phase A — DB Foundation + Products (S78-S82)          ~10% ⏳
  ├─ P0 bugs fixed                                    0% ⏳ S78
  ├─ S77 DB migrations                                0% ⏳ S78
  ├─ DB foundations (cents, audit, soft delete)       0% ⏳ S79
  ├─ DB guards (negative, FK, idempotency)            0% ⏳ S80
  ├─ Stock ledger + event queue                       0% ⏳ S81
  ├─ Bluetooth print                                  50% ⏳ S82 (код готов, bridge блокер)
  ├─ products.php главна rewrite                      0% ⏳ S79
  ├─ products.php wizard rewrite                      0% ⏳ S80
  ├─ products.php AI features                         0% ⏳ S81
  ├─ products.php polish                              0% ⏳ S82
  └─ ЕНИ first sale                                   0% ⏳ 10-15 май

Phase B — Module Ecosystem + Real-time (S83-S96)      0% ⏳
  ├─ orders.php v1                                    0% ⏳ S83
  ├─ Lost demand + AI draft                           0% ⏳ S84
  ├─ sale.php rewrite                                 0% ⏳ S85
  ├─ deliveries.php                                   0% ⏳ S86
  ├─ inventory v4 + warehouse hub                     0% ⏳ S87
  ├─ Pusher real-time (CRITICAL)                      0% ⏳ S88
  ├─ Multi-device FOR UPDATE (CRITICAL)               0% ⏳ S89
  ├─ WooCommerce integration                          0% ⏳ S90
  ├─ Shopify integration                              0% ⏳ S91
  ├─ transfers + multi-store                          0% ⏳ S92
  ├─ stats.php rewrite                                0% ⏳ S93
  ├─ /ai-action.php router                            0% ⏳ S94
  ├─ Simple Mode = AI chat                            0% ⏳ S95
  └─ Life Board v1 + i18n                             0% ⏳ S96

Phase C — AI Safety (S97-S102)                        0% ⏳

Phase D — Launch (S103-S110)                          0% ⏳
```

**Overall: ~2% complete. Public launch target: септ 2026.**

---

# 📝 ПОДДРЪЖКА НА COMPASS

## В края на всяка сесия — Claude задължително update-ва:

1. **"Последна завършена сесия"** (горе)
2. **"Следваща сесия"** (горе)
3. **"СЛЕДВАЩА ЗАДАЧА"** секция — пренаписва се за новата
4. **"ЧЕТИВО ЗА СЛЕДВАЩАТА СЕСИЯ"** таблица
5. **Phase Overview %**
6. **Модули в production** таблица (ако статусът се е сменил)
7. **DB foundation** таблица (ако нещо е построено)
8. **Активни P0 bugs** (махни затворените, добави нови)
9. **LOGIC CHANGE LOG** (ако има промяна на решение)
10. **REWORK QUEUE** (ако ново rework е възникнало; ако старо е приключено → махни)

## Никога:
- Не изтривай стари решения от LOGIC CHANGE LOG (историята е важна)
- Не маркирай "complete" без verification
- Не пропускай да свържеш REWORK QUEUE entries с бъдещи сесии

## Всяка промяна в COMPASS = отделен commit
```bash
git commit -m "COMPASS: update after S78 — bugs closed, tables created"
```

---

# ❓ QUICK FAQ

**Q: Chat отваря, какво прави първо?**  
→ Чете COMPASS + DOC 01 + последен handoff. Казва състояние БЕЗ ВЪПРОСИ.

**Q: Тихол пише "променям X да работи по нов начин"?**  
→ Chat проверява DEPENDENCY TREE + CROSS-MODULE IMPACT → изброява засегнатите → чака ОК → update COMPASS → прилага промяната.

**Q: Тихол пише "върнахме решение Y"?**  
→ Chat търси в LOGIC CHANGE LOG → изброява UNDO списъка → чака ОК → прилага + добавя ROLLBACK entry.

**Q: Чат отваря и не знае коя е текущата сесия?**  
→ Чете "Следваща сесия" в top header на COMPASS.

**Q: Нова сесия чете ли всички 40+ файла?**  
→ НЕ. COMPASS + DOC 01 + handoff + 2-5 specific от таблицата. Максимум 9.

**Q: 2 паралелни chat-а работят едновременно?**  
→ Виж "CHAT_1_OPUS.md / CHAT_2_OPUS.md / CHAT_3_SONNET.md" файлове за разпределяне. Всеки chat commit-ва на различни файлове (file locks в COMPASS секция).

**Q: COMPASS и BIBLE си противоречат?**  
→ 5-те закона > 9-те заключени > по-нова дата. Ако още неясно → питай Тихол.

**Q: Нещо което chat прави не е в COMPASS?**  
→ Започни от DOC 01. После питай Тихол.

---


---

# 🔗 FILE URLS ЗА CLAUDE — ЖИВИ ОГЛЕДАЛА

**PHP файловете се огледалват автоматично като .md в /mirrors/ (cron на 5 мин).**
**Всеки нов chat чете тези URL-и от COMPASS и ги използва за fetch.**

| Файл | URL |
|---|---|
| products.php | https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/mirrors/products.md |
| sale.php | https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/mirrors/sale.md |
| chat.php | https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/mirrors/chat.md |
| warehouse.php | https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/mirrors/warehouse.md |
| inventory.php | https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/mirrors/inventory.md |
| stats.php | https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/mirrors/stats.md |
| INVENTORY_v4.md | https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/INVENTORY_v4.md |
| INVENTORY_HIDDEN_v3.md | https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/INVENTORY_HIDDEN_v3.md |
| DOC_11_INVENTORY_WAREHOUSE.md | https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/DOC_11_INVENTORY_WAREHOUSE.md |

**MD файловете в корена:**
- MASTER_COMPASS.md → https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/MASTER_COMPASS.md
- SESSION_77_HANDOFF.md → https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/SESSION_77_HANDOFF.md
- SESSION_78_HANDOFF.md → https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/SESSION_78_HANDOFF.md



---

## 🔒 LOGIC CHANGE — S79.FIX (22.04.2026): Скрита инвентаризация на главен екран

**ЗАКОН:** Секцията "Скрита инвентаризация" (Store Health % + Непреброени артикули + бутон "Започни Zone Walk →") е **ПОСТОЯННА** и **ЗАДЪЛЖИТЕЛНА** на:

1. **products.php** (Detailed Mode за Owner/Manager) — преди 6-те Q-секции
2. **simple.php / home.php** (Simple Mode за Пешо/Seller) — на видимо място

**Винаги видима**, дори при Store Health 95%+ — защото инвентаризацията НИКОГА не свършва (артикули остаряват, нови идват, кашони се отварят).

**Идентичен дизайн** в двата режима — една визуална компонента, два контекста.

**Източници:**
- INVENTORY_v4.md §9 — кога свършва инвентаризацията
- INVENTORY_HIDDEN_v3.md §10 — Store Health Score формула
- DOC_11_INVENTORY_WAREHOUSE.md §8 — Progress bar "AI знае магазина на X%"

**Имплементация:** S79.FIX (тази сесия) — products.php; следваща сесия — simple.php/home.php.


**КРАЙ НА MASTER_COMPASS v3.0**

*„Живият документ. Ако нещо в системата се промени и не е тук — грешка е.""*


## 22.04.2026 — Q-секции: тапът отваря DETAIL (НЕ EDIT) — Bug #9 fix
- **Решение:** В `products.php` Q-секции (q1-q6 fundamental questions home), тапът на артикулна карта вика `openProductDetail(id)` (read-only drawer), НЕ `editProduct(id)` (wizard в EDIT режим).
- **Защо:** EDIT режимът беше data risk — Пешо случайно тапва, попада в wizard, може неволно да изтрие/промени бройки. Detail drawer = безопасен read-only обзор + знае как да предложи next action.
- **Засегнати модули:** products.php (само 1 onclick в `sec.items.map` render block); drawer detail и main list onclick-ове ЗАПАЗВАТ editProduct (owner action, безопасен контекст).
- **Rework:** ЗАВЪРШЕН в S79.FIX.B (commit с маркер S79.FIX.B-BUG9).

## 22.04.2026 — Hidden Inventory Section (Вариант B) добавена
- **Решение:** В `products.php` Home екран добавена тюркоаз/cyan карта "Здраве на склада" (Store Health %) между Add card и Q-секциите. Tap → bottom-sheet overlay с breakdown (Точност 40% + Свежест 30% + AI увереност 30%) + 3 действия (Бърза проверка / Зона по зона / Пълно броене).
- **Формула:** Accuracy = % продукти с last_counted_at < 30 дни; Freshness = 100 − (avg_days_since_last_counted × 100/30); Confidence = AVG(products.confidence_score).
- **Source:** `INVENTORY_HIDDEN_v3.md §10 + §12`, `INVENTORY_v4.md §9`.
- **Засегнати:** products.php (нов endpoint output `store_health` в home_stats; нов HTML/CSS/JS; нов overlay).
- **Rework:** ЗАВЪРШЕН в S79.FIX.B (маркер S79.FIX.B-HIDDEN-INV-*).

