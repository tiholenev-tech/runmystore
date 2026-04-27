# 🧭 MASTER_COMPASS v3.0 — ЖИВИЯТ ORCHESTRATOR

## Router + Tracker + Dependency Tree + Change Protocol

**Последна актуализация:** 27.04.2026  
**Последна завършена сесия:** S82.STUDIO MARATHON — 26-27.04.2026 (3 паралелни Claude Code сесии: STUDIO.11/12/NAV/VISUAL frontend + STUDIO.BACKEND/APPLY backend) + S81.DIAG.VERIFY (pipeline functional, DOD pending S82.DIAG.FIX)
**Паралелно в ход:** Тихол solo real product entry на tenant=7 (без Claude Code), очаква bug list за S84
**Следваща сесия:** S83 = Real Entry Day (Тихол solo, 27.04) → S84 = BUGFIX BATCH + STUDIO.REWIRE (28.04, 2 паралелни Code)
**Текуща Phase:** A1 (Foundation, ~65%) → A2 (Operations Core, преди ЕНИ 14-15 май)  
**Първа реална продажба target:** ЕНИ магазин, 14-15 май 2026 (FIXED)

- **S82.STUDIO MARATHON CLOSED (27.04.2026):** 3 паралелни Claude Code инстанции, disjoint paths, нула collision. Frontend Code #1: STUDIO.11 (`/ai-studio.php` standalone, mock data), STUDIO.12 (per-product modal в wizard step 5), STUDIO.NAV (magenta button в chat.php), STUDIO.VISUAL Phase 1+2 (chat.php v8 Life Board + life-board.php нов файл 580 реда). Backend Code #2: BACKEND (migration up.sql/down.sql + 9 helpers + ai-studio-action.php endpoint + cron-monthly.php), APPLY (live DB applied + crontab installed + lingerie real prompt seeded). Tags v0.7.30 → v0.7.33. Live commits: 9f8a0b8, 9e7fb6c, 9fa9985, fcf0ec1.
- **S82.STUDIO known limitations:** /ai-studio.php показва mock data (frontend rewire към get_credit_balance() pending S84.STUDIO.REWIRE). 4 placeholder prompts (clothes/jewelry/acc/other) is_active=0. Diagnostic A=47.83%/D=21.43% pre-existing — НЕ regression от schema, но Rule #21 нарушен (apply без 100%). S82.DIAG.FIX е beta blocker.

- **S79.SECURITY VERIFIED (25.04.2026):** /etc/runmystore/db.env (по-сигурна от original plan). PDO + parse_ini_file. History scrubbed. P0 closed.
- **S80.DIAGNOSTIC (25.04.2026 — 50%):** Infrastructure deployed (124 scenarios, 27+ файла, pymysql, cron готов), tenant=99 setup-нат (store=48, user=60, customer=181), pipeline бяга. 4 bugs остават за S81: category_for_topic typo, tenant filter, semicolon split, ai_insights data_json schema reverse-engineer. Tag v0.6.0 ОТЛОЖЕН.
- **S82.SHELL.AI_STUDIO (25.04.2026 — 50%):** 13 commits, CRITICAL matrix qty data-loss bug fixed, AI backend готов (ai-image-processor + ai-color-detect + ai-image-credits, FREE 0/START 3/PRO 10), unified shell, theme toggle FOUC fix, swipe nav prefetch. Открита замърсеност: commit a44ee2d захвана файлове от Chat 2 заради git add -A.
- **PROMO SPEC FINALIZED (25.04.2026):** 5 AI brainstorm consensus. Stack per-item NO per-cart YES. Returns prorated lenient. Касов бон отделен ОТСТЪПКА line (Н-18 Прил.1). Greedy под 50ms. AI 30дни/100tx, blacklist top20%, margin floor 15%. Phase A2 pull-up.
- **ROADMAP REVISION (25.04.2026):** Pull-up promotions/transfers/deliveries/scan-document от Phase D към Phase A2/A3. Reason: Тихол + ЕНИ нужди реални от ден 1. НЕ са складова програма (правен trick — stock movements, не fiscal sales). Касов бон/loyalty/onboarding/AI-чат → Phase D.
- **DEPLOY PATTERN (25.04.2026):** xz+base64 single paste за multi-file. /tmp/sNN_staging_YYYYMMDD_HHMM/ → diff → cp /var/www/. NO destructive в paste. Limit ≤11KB compressed. Git commit отделно.

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
- Завършено: ~20%

РАБОТИ:
- products.php v0.9 (списък, wizard, детайли) — 8394 реда
- sale.php (базова функционалност)
- chat.php v7 (dashboard + overlay)
- compute-insights.php (19 pf функции, 9 активни на tenant 7) — 1280 реда
- ai_insights генерира реални insights (zombie 148K EUR, top profit, bestsellers low stock и т.н.)

НЕ РАБОТИ:
- products.php P0 bugs от PRODUCTS_MAIN_BUGS_S80.md
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
| `chat.php` | 🟢 **v8 GLASS Life Board** (S82.VISUAL Phase 1) — 6 q-cards q1-q6 + dashboard glass + weather glass + AI Studio entry | 1605 → +Life Board section | ✅ S79.FIX done, ✅ CHAT 4 rewrite done в S82, S95 Simple Mode → SUPERSEDED от life-board.php |
| `warehouse.php` | 🔴 само скелет | — | S87 (hub rewrite + 5 подмодула) |
| `inventory.php` | 🟡 v3 работи, v4 rewrite | — | S87 (event-sourced, Smart Resolver, offline) |
| `stats.php` | 🟡 базово | — | S93 (5 таба, role-based, drawer) |
| `orders.php` | 🔴 не съществува | — | S83 (12 входа + 11 типа + 8 статуса) |
| `deliveries.php` | 🔴 не съществува | — | S86 (OCR + voice + wizard) |
| `transfers.php` | 🔴 не съществува | — | S92 (multi-store + resolver) |
| `life-board.php` | 🟢 **CREATED** (S82.VISUAL Phase 2, 580 реда) — 4 collapsible cards (loss-heavy) + 4 ops glass buttons (Продай/Стоката/Доставка/Поръчка) + AI Studio entry + mini dashboard/weather. Bottom-nav скрит в Лесен режим. | 580 | ⚠ Toggle "Опростен →" в chat.php header — UNVERIFIED, P0 за S83 |
| `ai-studio.php` | 🟢 LIVE STANDALONE (S82.STUDIO.11) — 5 категории cards, credits bar, bulk секция, история, FAB. ⚠ Mock data (frontend rewire към get_credit_balance() pending S84). | — | S84.STUDIO.REWIRE |
| `ai-studio-backend.php` | 🟢 LIVE (S82.STUDIO.BACKEND) — 9 helper функции (get_credit_balance, consume_credit, refund_credit, check_retry_eligibility, check_anti_abuse, get_prompt_template, build_prompt, count_products_needing_ai, pre_flight_quality_check) + log helper. 23/23 smoke PASS. | — | — |
| `ai-studio-action.php` | 🟢 LIVE (S82.STUDIO.BACKEND) — нов HTTP endpoint type=tryon\|studio\|retry\|refund. Quality Guarantee parent_log_id chain. | — | Frontend integration → S84 |
| `cron-monthly.php` | 🟢 LIVE + INSTALLED в crontab (S82.STUDIO.APPLY) — 1-во число reset на bg/desc/magic_used_this_month | — | — |
| `ai-action.php` | 🔴 не съществува | — | S94 (router + $MODULE_ACTIONS) |
| `compute-insights.php` | ✅ 19 функции (products), 9 активни на tenant 7 | 1280 | S79.INSIGHTS done → S84 (+20 за warehouse/sale/stats) |
| `selection-engine.php` | ✅ готов | 157 | MMR λ=0.75, 4 функции, FK CASCADE (S79.SELECTION_ENGINE) |
| `config/ai_topics.php` | ✅ готов | 99 | Bootstrap + helpers (S79.SELECTION_ENGINE) |

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
| `idempotency_log` (multi-device) | ✅ | S78 |
| `user_devices` (multi-device tracking) | ✅ | S78 |
| `wizard_draft` (crash recovery) | ⏳ отложено | S80 |
| `cron_heartbeats` (cron monitoring) | ✅ | S79.CRON_AUDIT |
| `inventory_events` (event-sourced) | 🔴 няма | S87 |
| `ai_topics_catalog` (1000 теми) | ✅ S79.SELECTION_ENGINE |
| `ai_topic_rotation` (MMR suppression) | ✅ S79.SELECTION_ENGINE |
| `ai_credits_balance` (3-type credits split: bg/desc/magic) | ✅ S82.STUDIO.APPLY | S82 |
| `ai_spend_log` (status enum + parent_log_id chain) | ✅ S82.STUDIO.APPLY | S82 |
| `ai_prompt_templates` (5 seeded: 1 active lingerie, 4 placeholders) | ✅ S82.STUDIO.APPLY | S82 |
| `tenants` AI Studio columns (+6: included_*_per_month + *_used_this_month) | ✅ S82.STUDIO.APPLY (2 PRO + 45 START seeded) | S82 |
| `products` AI Studio columns (+4: ai_category, ai_subtype, ai_description, ai_magic_image) + idx_ai_category | ✅ S82.STUDIO.APPLY | S82 |
| Crontab www-data monthly (1-во число reset) | ✅ S82.STUDIO.APPLY | S82 |

## Hardware / External

| Ресурс | Статус |
|---|---|
| DO Frankfurt, 2GB RAM, `/var/www/runmystore/` | ✅ |
| **DB credentials** (/etc/runmystore/db.env, chmod 600, www-data) | ✅ S79.SECURITY |
| **API keys** (/etc/runmystore/api.env, Gemini x2 + OpenAI ротирани) | ✅ S79.SECURITY |
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
| 10 | `chat.php` toggle "Опростен" header | UNVERIFIED — Code #1 не спомена изрично | Verify ръчно от Тихол сутрин 27.04; ако липсва → 5-min Code fix | **S83 P0** |
| 11 | `/ai-studio.php` mock data | Frontend чете `tenants.ai_credits_*` (стария path), не новите helpers | Rewire към `get_credit_balance()` от backend | **S84 STUDIO.REWIRE** |
| 12 | AI Studio modal в wizard step 5 — visible end-to-end | Тихол flag: "защо не се пилазват новите модули — лесен режим и AI Studio в добави артикул" | Verify entry day; ако broken → wizard hook fix | **S83 P0** |

---

# 🎯 СЛЕДВАЩА ЗАДАЧА — S83 REAL ENTRY DAY

**Тип:** Тихол solo (БЕЗ Claude Code сесии)  
**Модел:** —  
**Estimated duration:** 3-4 часа

## Цел

Stress test на products.php wizard + AI Studio modal с реална стока на tenant=7. Bug-ове ще изскочат — записвай ги.

## Pre-flight checklist

1. **Verify че се отварят новите модули:**
   - `/life-board.php` (Лесен режим) — отвори от телефона
   - AI Studio modal в "Добави артикул" wizard step 5 — стигни до стъпка 5
   - Toggle "Опростен →" в chat.php header (top right)
   
2. **Force-quit + clear cache на телефона** (Capacitor app държи стара bundled версия)

## Real entry задачи

1. Минимум 50 артикула — единични + варианти (размер × цвят)
2. Тествай Step 5 AI Studio (Стандартно режим + Настрой режим)
3. Тествай bulk operations от `/ai-studio.php`
4. Записвай bug-ове в `BUGS_FROM_REAL_ENTRY.md`
5. Screenshot-и на counter-intuitive UX моменти

## Deliverables

- ✅ 50+ продукта влезли на tenant=7
- ✅ `BUGS_FROM_REAL_ENTRY.md` с findings
- ✅ Verify status за P0 bugs #10, #11, #12 (виж "Активни P0 bugs" таблица)

## Git

Тихол solo → нула commits този ден.

---

# 🎯 СЛЕДВАЩА КЛОД-СЕСИЯ — S84 BUGFIX BATCH + STUDIO.REWIRE

**Тип:** 2 паралелни Claude Code  
**Модел:** Opus 4.7  
**Estimated duration:** 4-5 часа  
**Дата:** 28.04.2026

**Code #1 (frontend bugs от entry):**
- Прочита `BUGS_FROM_REAL_ENTRY.md`
- Fix-ва P0 bug-ове в `products.php` (3-те known + новите от entry)
- Verify toggle "Опростен →" в chat.php header (RQ #41)
- Verify AI Studio modal visible в wizard step 5 (RQ #43)

**Code #2 (STUDIO.REWIRE):**
- Rewire `ai-studio.php` да чете от `get_credit_balance()` (RQ #42)
- Rewire wizard step 5 modal да вика `/ai-studio-action.php`
- Disjoint paths: `ai-studio.php` + `partials/ai-studio-modal.php`

**Виж docs/NEXT_SESSIONS_PLAN_27042026.md за пълен 15-сесиен план до 14-15.05 ENI launch.**

---

# 📚 ЧЕТИВО ЗА СЛЕДВАЩАТА СЕСИЯ (S80 — DIAGNOSTIC.FRAMEWORK)

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
| docs/SESSION_S82_STUDIO_MARATHON_HANDOFF.md | ALL | S82 marathon closure — 3 паралелни Code сесии, всички tags, known limitations, P0 acции за S83 |
| docs/NEXT_SESSIONS_PLAN_27042026.md | Plan | 15-сесиен plan от S83 до S96 ENI launch 14-15.05 |

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
7. **RESPONSIVE ОТ ДЕН 1** — всеки модул работи на всички екрани (телефон 320-428px, таблет portrait 768px, таблет landscape 1024px, desktop 1280px+) чрез CSS @media queries. Никога не се пишат "mobile" и "tablet" версии отделно — един код, self-adapting. Breakpoints: 375/768/1024/1280.
8. **ROLE-AWARE + MODE-AWARE ОТ ДЕН 1** — всеки нов модул:
   - `requires_role()` в началото (owner/manager/seller)
   - `forceSimpleIfSeller()` след auth (seller = forced simple mode)
   - SQL queries conditional на `can('view_cost')` etc. — данни които seller не трябва да вижда (cost_price, profit, insights с cost) НЕ се query-ват от базата
   - UI pills/actions обвити в `if (can(...))`
   - `?simple=1` URL param triggers simple wrapper (без bottom nav, без burger)
   - 3 роли: owner / manager / seller (+ 2 режима: simple / detailed)
   - Seller е hardcoded към simple (forced, not default)
   - Owner/manager могат ad-hoc да превключват към simple

---

# 📝 LOGIC CHANGE LOG

## 27.04.2026 — DAILY_RHYTHM_PROTOCOL активиран
- **Решение:** 3-фазен дневен ритъм с 1 шеф-чат за деня. SESSION 1 BUILD (08-12) → SESSION 2 TEST (13-17) → SESSION 3 FIX (18-21). Triggers: „СЕСИЯ 1/2/3", „КРАЙ НА СЕСИЯ X", „КРАЙ НА ДЕНЯ".
- **Защо:** предотвратяване на ad-hoc решения и context drift между чатове. Гарантира daily test feedback (S2 преди EOD); P0 откритията получават fix-окно същия ден (S3) вместо да висят до утре. 1 шеф-чат за целия ден = ~80% по-малко drift спрямо отваряне на нов чат при всяка задача.
- **Засегнати:** всички работни сесии (Code #1/2/3 получават startup prompts от шеф-чат-а в S1), шеф-чат boot procedure (нова Phase 5 — daily session tracking), STATE структура (нова `## 🔁 STANDING PROTOCOLS` секция получи 2-ри entry под TESTING_LOOP).
- **Rework:** нищо — additive протокол.
- **Документация:** `DAILY_RHYTHM.md` (root, 337 реда, 8 sections + quick reference). Daily logs: `daily_logs/DAILY_LOG_YYYY-MM-DD.md` (от template, append-only). Session templates: `templates/session_{1_build,2_test,3_fix}.md` (~130 реда всеки — pre-flight, decision tree, common pitfalls, examples, closing checklist).
- **Promotion path:** при 7 поредни дни без skip → STANDING_RULE_#24.

## 27.04.2026 — TESTING_LOOP_PROTOCOL активиран (S87)
- **Решение:** Continuous AI insights validation на tenant=99 — daily 07:00 cron хвърля seed → `computeProductInsights(99)` → snapshot → diff → status JSON. 🟡/🔴 пишат в `tools/testing_loop/ANOMALY_LOG.md`.
- **Защо:** Тихол не може manually да проверява insights всеки ден. Без auto-loop регресии в `compute-insights.php` или drop в seed данни могат да минат незабелязани седмици. Loop = early warning без да ангажира human attention докато всичко е 🟢.
- **Засегнати:** `tools/testing_loop/` (нов dir: `daily_runner.py`, `snapshot_diff.py`, `daily_snapshots/`, `latest.json` symlink, `ANOMALY_LOG.md`), `TESTING_LOOP_PROTOCOL.md` (root spec), `admin/beta-readiness.php` (+1 секция Testing Loop Health), `STATE_OF_THE_PROJECT.md` (нова `## 🔁 STANDING PROTOCOLS` секция), `SHEF_BOOT_INSTRUCTIONS.md` (Phase 2 STATUS REPORT задължителен ред).
- **Rework:** нищо. Loop е additive; не пипа production code (`compute-insights.php`, schema, helpers).
- **Setup pending:** crontab line за www-data (manual install от Тихол — виж TESTING_LOOP_PROTOCOL.md §Setup).
- **Promotion path:** при 7 поредни 🟢 дни → STANDING_RULE_#23.

## 26-27.04.2026 — S82.STUDIO MARATHON (3 паралелни Claude Code)
- **Решение:** Marathon вечер преди real entry — 3 паралелни Claude Code инстанции с disjoint paths за да освободи следващите дни (sale.php / Bluetooth / transfers).
- **Защо:** Logic 90% complete — bottleneck е код deployment скоростта. Browser chat era приключи. 3 паралелни сесии = ~2 седмици работа в 1 нощ.
- **Засегнати:** chat.php (v8 redesign), life-board.php (нов файл), ai-studio.php (нов), ai-studio-backend.php (нов), ai-studio-action.php (нов), cron-monthly.php (нов), products.php (wizard step 5 modal), tenants/products schema (+10 колони), 3 нови таблици (ai_credits_balance, ai_spend_log, ai_prompt_templates).
- **Rework:** REWORK #21 (life-board.php beta blocker) → ✅ DONE. REWORK #14 (Capacitor printer) → still in progress. NEW REWORK #41-46 added (виж REWORK QUEUE).

## 27.04.2026 — Workflow shift: 90% Claude Code (от browser chat era)
- **Решение:** От днес 90% от code работата минава през Claude Code инстанции, browser шеф-чат е САМО координация / dependency tree / mockup approval / decision making.
- **Защо:** S82 marathon показа реалния скоростен ефект (3 паралелни disjoint paths = ~30-50x browser chat productivity).
- **Засегнати:** Нула засегнат код, само workflow promote.
- **Rework:** Шеф-чат capacity max 3 паралелни Code инстанции (над това = collision risk + capacity overrun).

## 27.04.2026 — Beta launch ENI fixed на 14-15 май (3 седмици от днес)
- **Решение:** Beta launch остава 14-15 май. Public launch — септември (по плана).
- **Защо:** Real-world bottlenecks (hardware tests, ENI on-boarding, real product entry) не се ускоряват от Claude Code. Logic ускорение = ~70% спестено време; real-world ускорение = ~10%.
- **Засегнати:** S83-S96 plan locked (виж docs/NEXT_SESSIONS_PLAN_27042026.md).
- **Rework:** Phase A2 sessions S87-S96 prioritized — 15 сесии до launch.

## 25.04.2026 — ROADMAP REVISION (Тихол решение)
- Решение: Phase B/C/D пренаредени. Pull-up в Phase A2 (преди ЕНИ май): promotions, transfers, deliveries, scan-document (OCR), suppliers, inventory CoD, sale.php rewrite. Push-down в Phase D: касов бон/ФУ, loyalty migration, onboarding wizard, AI чат, settings advanced.
- Защо: Тихол е първи клиент (5 магазина), ЕНИ е втори (10-15 май). RunMyStore НЕ Е складова програма (правен trick — записва "stock movements", не "fiscal sales") → касов бон не е блокер. AI чат не е MUST за ден 1 (voice search в numpad context е достатъчен за закон №1). Loyalty/onboarding далечно (post-launch).
- Засегнати: всички REWORK queue entries за promotions/transfers/deliveries → променя сесийното им placement. ПЪЛЕН S78-S110 ROADMAP таблица трябва да се update-не отделно (отлагам).
- Phase A1 → A2 → A3 → B → C → D → E структурата заменя стария A → B → C → D.

## 25.04.2026 — PROMO SPEC FINALIZED (5 AI brainstorm consensus)
- 5-те AI (Claude/Gemini/DeepSeek/ChatGPT/Kimi) дадоха конкретни препоръки. Резултат:
  - Stack: per-item NO, per-cart YES (specificity-based blocking)
  - Returns: prorated lenient (refund=платена цена, оставащите запазват discount)
  - Касов бон: отделен ред "ОТСТЪПКА -X.XX лв" (Наредба Н-18 Приложение №1, конкретна реф от Kimi)
  - Algorithm: greedy + recompute scratch + cache rules не cart, под 50ms p95
  - AI suggestions: 30 дни/100 tx threshold, blacklist top 20% bestsellers, margin floor 15%
- DB schema: promotions + promotion_rules (с specificity_level GENERATED COLUMN) + promotion_applications (audit с applied_unit_prices JSON)
- Phase placement: A2 basic (2 сутиена -20%, 5 чорапа от 1 модел) → C AI suggest → D combo+B2B+loyalty
- Реален use case Тихол: "2 сутиена -20%" (различни модели) + "5 чорапа от 1 модел -X%" работят паралелно (различни групи).

## 25.04.2026 — S82.SHELL.AI_STUDIO (Chat 1, 13 commits)
- Решение: унифициран shell за всички 7 модула (chat/products/sale/inventory/warehouse/stats/settings) + AI backend готов (НЕ frontend integration още).
- CRITICAL bug fixed: matrix qty save data-loss (parseInt върху обект {qty,min} → NaN → save 0).
- AI backend: /ai-image-processor.php (fal.ai birefnet) + /ai-color-detect.php (Gemini Vision) + /ai-image-credits.php (plan limits).
- Migration 20260425_001 applied: ai_image_usage table.
- Theme toggle с FOUC fix (inline script преди body render).
- Swipe nav с prefetch (~50-100ms paint).
- Открита грешка: commit a44ee2d "замърсен" — git add -A захвана untracked файлове от Chat 2 S80. → Iron rule на git add specific paths.

## 25.04.2026 — S80.DIAGNOSTIC split S80 → S80 + S81
- Решение: Chat 2 направи реалистично split вместо "95% complete" лъжа.
- S80 (50% done): Infrastructure deployed (124 scenarios, 27+ файла, pymysql, cron готов). Tenant=99 setup (store=48, user=60, customer=181). Pipeline бяга end-to-end (3 runs в diagnostic_log).
- S81 (verify adaptation, ~2-3h): category_for_topic typo fix, tenant filter в fetch_active_scenarios, multi-statement execution в seed_scenario, reverse-engineer ai_insights data_json schema, адаптация на 8 verify handlers, baseline run на tenant=99 с Cat A=100%/Cat D=100%, tag v0.6.0-s80-diagnostic, install crontab + ENV vars.
- Защо split: ai_insights data_json структура е unknown без reverse-engineer на реални rows. Не е "30 минути" работа както първоначално казано.

## 25.04.2026 — DEPLOY PATTERN (Chat 2 discovery)
- Решение: За multi-file deploy НЕ heredoc файл-по-файл. Заместване: пиши в sandbox /tmp/, tar -cf - . | xz -9 | base64 -w 0, single paste decode → extract в /tmp/sNN_staging_YYYYMMDD_HHMM/ (НЕ /var/www/) → find . -type f преглед → diff срещу live → ПОТВЪРДИ ТИХОЛ → cp в /var/www/ → php -l + py_compile.
- Защо: heredoc multi-file крашва SSH-а на размери >15KB. xz+base64 single paste = atomic, по-малко грешки.
- ЗАБРАНЕНИ в paste: rm/chmod/chown/git reset/DROP/TRUNCATE/ALTER. Git commit отделно. Limit ≤11KB compressed.
- STANDING RULE.

## 25.04.2026 — STANDING RULE #22: НЕ САМО-ОДОБРЕНИЕ (no self-approval)
- Контекст: Chat 2 написа в S80 plan v1.2 "✅ РЕШЕНИЯ ПОТВЪРДЕНИ ОТ ТИХОЛ: Cron ОСТАВА", когато Тихол беше казал обратното. Шеф-чатът хвана грешката.
- Правило: Работните chat-ове НИКОГА не маркират собствени решения като "потвърдено от Тихол" преди реално потвърждение в съобщение. Използват "PROPOSED — чака одобрение".
- Защита срещу: rework risk + trust erosion + неправилни handoff-и.
- STANDING.

## 24.04.2026 - DIAGNOSTIC PROTOCOL = STANDING RULE #21 (IRON PROTOCOL addition)
- Reshenie: vsyaka promyana na AI logika PREDI commit minava prez DIAGNOSTIC_PROTOCOL.md.
- Zashto: S79 otkri realen bug (pfHighReturnRate Cartesian) koyto ne byeshe uloven mesetsi. Bez sistematichno testvane AI mozhe da dava greshni preporaki.
- Kak: TDD workflow (7 stupki), 4 kategorii A/B/C/D, 5 avtomatichni trigera, diagnostic_log tablitsa.
- Trigeri: nov AI modul, ponedelnik 03:00 cron, 1-vi den mesec 04:00 cron, rachno 'AI DIAG PUSNI', pri sumnenie za bug.
- Referenten dokument: DIAGNOSTIC_PROTOCOL.md v1.0 (323 reda) v repo root.
- Status: standing rule

## 24.04.2026 - ZABRANA NA MARKDOWN V HEREDOC (IRON PROTOCOL addition)
- Reshenie: heredoc payload NE sadurzha emoji/bold/tables/headers/backticks/BG kavichki.
- Zashto: paste v bash konzola shhupeshe parsera, faylovete se suzdavaha chastichno.
- Pravilen workflow: 2 stupki. Heredoc s plain ASCII placeholders + Python skript s \u unicode escapes za final markdown.
- Alternativa: edin golyam Python skript s \u escapes za tsyaloto sudurzhanie.
- Status: standing rule

## 24.04.2026 — S81.BUGFIX.V3.EXT (14 mobile bugs Samsung Z Flip6)

- **Решение 1:** Mobile CSS rework for Samsung Z Flip6 cover display (~373px wide, Capacitor WebView). 14 bugs closed across 3 groups (CSS, functional, UX).
- **Решение 2:** **Capacitor env(safe-area-inset-bottom) = 0 on Android** (default non-edge-to-edge). Discovered after 3 failed attempts. Fix: padding-bottom `max(120px, calc(16px + env()))` on `.wiz-page` — 120px fallback works universally; env() kicks in on iOS/edge-to-edge.
- **Решение 3:** Dead code discovery — `const stickyFooter=` var at line 5336 is never returned in rendered HTML. Real footer is inline block at 5359-5364. All prior Bug 2 attempts modified dead code. Lesson: grep for `variable+` or `return.*variable` before patching.
- **Решение 4:** Orchestration split — Chat 1 (Opus 4.7) diagnostic + patches → Claude Code took over after Bug 2 failure for iterative on-device testing. Worked smoothly.
- **Решение 5:** Parallel session success — Chat 1 (products.php) + Chat 2 (compute-insights.php S79.INSIGHTS) ran simultaneously, FILE LOCK observed, 0 conflicts, both closed same day.
- **Tag:** `v0.6.4-s81-bugfix-v3-ext` (pushed, 4221ef9 last bugfix commit)
- **Handoff:** `docs/SESSION_S81_BUGFIX_V3_EXT_HANDOFF.md`
- **Засегнати файлове:** `products.php` (EXCLUSIVE FILE LOCK)
- **REWORK pushed forward:** S82 Capacitor edge-to-edge config — когато direkte, 120px fallback → чист env()

---

## 24.04.2026 — S79.INSIGHTS.COMPLETE (Cartesian bug fix + seed_oracle)

- **Решение 1:** `pfHighReturnRate` Cartesian bug fixed. Old SQL: `LEFT JOIN returns + SUM()` дублираше quantity ×N при N sale_items → показваше 100% returns вместо реални 10%. New: subquery aggregation pattern.
- **Решение 2:** compute-insights.php **= 19/19 pf*() функции работят** (от 0 skeleton в S77 план). 9 schema gap + 10 functional били фиксирани в S78+S79.SCHEMA.
- **Решение 3:** `seed_oracle` table **= permanent** на test tenant (7). Регресионни expectations за всеки AI модул. 72 scenarios defined за insights module.
- **Решение 4:** `cost_price` остава **NOT NULL** (S79 experiment reverted). 0 = "не знам", wizard принуждава въвеждане (UX rule).
- **Решение 5:** DIAGNOSTIC FRAMEWORK planned за S80. Continuous integration testing: нов AI модул + weekly cron (понеделник 03:00) + monthly + ръчно "AI DIAG ПУСНИ".
- **Test coverage:** 53/72 oracle scenarios PASS (74%). 19 FAIL = TOP-N background pollution, не bugs, S80 решава с pristine tenant.
- **Засегнати:** compute-insights.php (SQL fix ред ~1075), DB schema (+seed_oracle, +returns, +sale_items.returned_quantity, +products.has_variations/size/color)
- **Rework:** добавени RQ-S79-1..9 (refactor s79_seed.py, multi-store inventory fix, category backfill, pristine mode, aggressive fixtures, children fixture, cron, dashboard, diagnostic_log)
- **Tags:** `v0.7.0-s79-insights-complete` (pushed)
- **Commits:** `c9a49f5` (pfHighReturnRate fix)
- **Файлове създадени:** `docs/DIAGNOSTIC_PROTOCOL.md` v1.0, `docs/SESSION_S79_INSIGHTS_COMPLETE_HANDOFF.md`


> **Правило:** когато Тихол промени решение или върне назад, record в този лог. Всеки chat при стартиране проверява тук за влияние върху текущата задача.

**Reverse chronological (newest first).**

## 24.04.2026 - SHEF_RESTORE_PROMPT v2 (auto-fetch URLs)
- Reshenie: Shef-chat restore prompt prezapisan - edno izrechenie s 2 URL-a.
- Zashto: Web_fetch safety policy bloki URL-i koito ne sa v user turn. Stariyat protokol kazvashe web_fetch raboti no ne rabotesha za shef-chat.
- Kak: Tihol kopira 1 red pri otvaryane na nov shef-chat. URL-ite idvat ot user -> fetch raboti avtomatichno.
- Dobaveno: Predupreghdenie za parallel commits (viz S79.SELECTION_ENGINE + IRON PROTOCOL case na 24.04).
- Status: standing rule

## 24.04.2026 - IRON PROTOCOL vuveden (no-base64)
- Reshenie: Zhelezen protokol v IRON PROTOCOL sektsia na COMPASS.
- Zashto: Povtorenie na prompts = gubene vreme. Propuski v closeout = zagubena rabota.
- Kak: Shef-chat kazva 'Izpulni IRON PROTOCOL + zadachata e: ...'. Chat chete COMPASS.
- Klyuchovo: ZABRANA na base64 (Opus 4.7 safety go blokira -> chat spira po sredata).
- Dobaveno: Length warning pri ~30-40 suobshteniya, 7-stupkov closeout.
- Status: standing rule

## 23.04.2026 (късно) — BETA SCOPE FINALIZED + ЗАКОН №8 (Role-aware)
- **Решение:** Beta launch (май 2026) scope = 1 магазин, 1 seller (Ани), БЕЗ AI. Необходими модули: sale.php (rewrite), deliveries.php (нов), products.php (polish), inventory.php (v3 работи, само simple wrapper), life-board.php (skeleton без AI).
- **Отложено за Beta v2 (юни-юли):** трансфери между магазини, фактури, OCR сканиране, multi-магазин, multi-seller.
- **Post-beta (септември+):** AI insights, Voice, Life Board Selection Engine (17 решения), customers/CRM, partners/referrals, online интеграции.
- **ЗАКОН №8 (Role-aware + Mode-aware):** всеки модул от ден 1 проверява role + mode. Seller = forced simple. Owner/manager = detailed + ad-hoc simple toggle. Данните които seller не трябва да вижда НЕ се query-ват от базата (не просто hidden в UI).
- **Roadmap ревизия:**
  - S80 = ROLES_AND_MODES_FOUNDATION (beta blocker — 3 часа)
  - S81 = LIFE_BOARD_SKELETON (beta blocker — 4-6 часа, БЕЗ AI)
  - S82 = продължава (products + labels + simple wrapper)
  - S85 = sale.php rewrite (responsive + role-aware + simple wrapper от ден 1)
  - S86 = deliveries.php (нов, responsive + role-aware + simple wrapper от ден 1)
  - S95-S96 (post-beta) = Life Board Selection Engine + AI integration
- **Засегнати:** всички нови модули; 3 съществуващи модули трябват role retrofit (REWORK #18/#19/#20)
- **Защо:** Ани е seller → forced simple mode → без life-board.php beta не може да стартира. Life Board беше S95 — преместен на S81 (beta blocker).
- **Critical path:** S80 → S81 → S82 → S85 → S86 → inventory polish → beta launch (~6-10 сесии, 3-5 седмици при 1 сесия/ден)

## 23.04.2026 (вечер) — UI ЗАКОН №6: Responsive от ден 1
- **Решение:** Всеки модул е responsive от първия ред код. Един кодбейз, self-adapting на всички екрани 320-1920px+
- **Защо:** Ани (beta клиент) ползва таблет. Пешо (персона) ползва телефон. Бъдещи клиенти — неизвестни устройства. Retrofit е 2x по-скъп от първоначален дизайн.
- **Breakpoints:** 375px (телефон) / 768px (таблет portrait) / 1024px (таблет landscape) / 1280px (desktop). Ширини между breakpoint-ите наследяват по-малкия стил.
- **НЕ прави:** hover states, keyboard shortcuts, desktop-only features — НЕ в скоупа сега. Приложението е touch-first. Desktop работи но не е оптимизиран.
- **Засегнати:** chat.php (REWORK #15), products.php (REWORK #16), stats.php (REWORK #17), всички бъдещи модули (sale.php S85, orders.php S83, warehouse.php S87, inventory.php v4 S87) — responsive от нулата.
- **Invariant:** UI ЗАКОН №6 добавен в секция "UI invariants".

## 23.04.2026 (следобед) — S79.SECURITY изпълнен предсрочно
- **Решение:** P0 security incident response — премества всички credentials от hardcoded в env files, git history scrub, rotation на всички exposed keys
- **Защо:** DB парола + 2 Gemini keys + 1 OpenAI key бяха в публичния GitHub repo ~седмици; OpenAI auto-disabled старият key което показа че боти вече са го намерили
- **Засегнати:** config/database.php (rewrite), config/config.php (rewrite), .gitignore (hardened), .env.example (new), git history (scrubbed 662 commits), MySQL (rotated), Gemini x2 (rotated), OpenAI (rotated)
- **Rework:** REWORK #13 → ✅ done (беше планирано за S109)
- **Handoff:** docs/SESSION_S79_SECURITY_HANDOFF.md
- **Side effect:** force push rewrote history — всички локални clones трябва `git fetch origin && git reset --hard origin/main` преди нова работа



**Reverse chronological (newest first).**

## 24.04.2026 — S79.SELECTION_ENGINE: AI topic rotation система

- **Решение:** 1000+ теми в DB каталог + MMR selection engine + per-tenant 6h suppression window
- **Защо:** S95 Simple Mode и S96 Life Board имат нужда от rotating AI теми за Пешо AI chat. Compute-insights е статична — винаги едни и същи функции. Selection Engine избира КОИ теми да се показват на КОЙ tenant в кой момент.
- **Алгоритъм:** MMR (Maximal Marginal Relevance) — greedy selection с relevance score (priority 40% + freshness 30% + trigger 30%) минус diversity penalty 0.4 per дублирана категория. λ=0.75 default.
- **Нови файлове:** migrations/20260424_001_ai_topics.up.sql (41), config/ai_topics.php (99), selection-engine.php (157), SESSION_S79_SELECTION_ENGINE_HANDOFF.md
- **Integration points:** S94 /ai-action.php (getTopicById), S95 life-board.php (selectTopicsForTenant), S96 Life Board визуализация, compute-insights.php (trigger_match scoring)
- **Discrepancies fixed в кода:** tenants.plan_effective/country/language (не plan/country_code/lang), plan=business=универсален, FK CASCADE добавен, UNIQUE заменя INDEX
- **Тестове (tenant 7):** 6/6 зелени (selection, suppression, stats, module scope, reset, diversity)
- **Commit:** c0a4540
- **Tag:** v0.6.0-s79-selection-engine
- **Статус:** ✅ done

## 23.04.2026 (вечер) — S79.POLISH + DESIGN_SYSTEM v2.0

- **Завършено:**
  (а) S79.BRIEFING_6FQ — briefing bubble от 3 sig-cards към 6 широки секции (по 1 insight от всеки fundamental_question) с narrative order loss→loss_cause→gain→gain_cause→order→anti_order
  (б) S79.POLISH + POLISH2 — Neon Glass treatment за revenue pills, top-strip, signal detail buttons, signal browser; primary actions с color-mix(in oklch) hue gradient
  (в) DESIGN_SYSTEM.md v2.0 — 1006 реда пълна спецификация, 19 компонента, adoption checklist §M
- **Референтен файл:** chat.php v8 (2094 реда, commit c2caaf5)
- **ЕТАЛОН:** всеки нов модул ТРЯБВА да премине adoption checklist §M преди commit
- **Rework затворени:** #1 (products.php UI pills — трябва да се прекласифицират с fundamental_question mapping когато се обнови products.php)
- **Commits:** d5ddf41 (VIZ.FIX), bdd14c7 (POLISH), c2caaf5 (POLISH2), 843a1d8 (DESIGN_SYSTEM v2)
- **Статус:** ✅ done — ЕТАЛОН за всички бъдещи модули



## 23.04.2026 — S79.VISUAL_REWRITE: chat.php v8 (home-neon-v2 design)

- **Решение:** Пълен визуален rewrite на chat.php към home-neon-v2 design (Neon Glass с conic-gradient shine + glow). Всички S79 функции запазени. 3 × 75vh overlays (Chat, Signal Detail, Browser) със същия дизайн, blur фон отдолу.
- **Защо 75vh:** Чат като WhatsApp app, signal detail като обяснителна страница. Хардуерен back бутон + swipe down + ESC.
- **Засегнати:** chat.php (1605 → 2094, +489). 0 DB промени.
- **Нови функции:** history.pushState back, swipe-to-close, ESC, body.overlay-open blur+scale, pulsing SVG AI icon.
- **Rework затворени:** visual consistency с DESIGN_SYSTEM.md
- **Commit:** 44aafab
- **Tag:** v0.5.5-s79-visual
- **Статус:** ✅ done — production, verified



## 23.04.2026 — S82.CAPACITOR ✅ DONE: DTM-5811 печата от APK

- **Статус:** ✅ **Пълен успех.** TSPL команди минават през BLE bridge, тестов етикет излиза от DTM-5811.
- **Последен fix (S82.CAPACITOR.3):** Премахнат `android:maxSdkVersion="30"` от `ACCESS_FINE_LOCATION` в AndroidManifest.xml — BLE plugin на Android 12+ иска permission-а без ограничение.
- **Потвърждение:** UA `Mozilla/5.0 (Linux; Android 16; SM-F741B ... wv)` — в Capacitor WebView. `Capacitor.getPlatform()=android`, `isNativePlatform=true`, `androidBridge=object`, `BleClient=object`, `BluetoothLe=present`.
- **Устройство за тест:** Samsung Z Flip6 (Android 16) + DTM-5811 BDA DC:0D:51:AC:51:D9.
- **Следващ проблем (не блокер):** TSPL label layout нужда от polish — кирилица излиза като йероглифи (липсва codepage 1251), spacing между редове недобър. → **Задача S82.4 LABEL DESIGN.**
- **Git tag:** `v0.6.0-s82-capacitor` поставен.
- **Cleanup:** Debug trap в login.php премахнат; printer бутон от ua-debug.php премахнат. Ua-debug.php остава public за бъдеща диагностика.

### REWORK QUEUE updates

### Added 24.04.2026 (S79 → S80)
| RQ-S79-1 | Refactor s79_seed.py → /tools/diagnostic/ modular structure | P0 | S80 |
| RQ-S79-2 | Fix adjust_inventory multi-store routing | P1 | S80 |
| RQ-S79-3 | Backfill category A/B/C/D на 23 scenarios | P0 | S80 |
| RQ-S79-4 | Pristine tenant mode (--pristine flag, wipe products) | P1 | S80 |
| RQ-S79-5 | Aggressive fixtures → 72/72 PASS | P2 | S80 |
| RQ-S79-6 | Children products fixture за size_leader | P2 | S80 |
| RQ-S79-7 | Cron setup (weekly понеделник 03:00, monthly 1-ви 04:00) | P0 | S80 |
| RQ-S79-8 | Admin dashboard /admin/diagnostics.php | P0 | S80 |
| RQ-S79-9 | diagnostic_log DB table | P0 | S80 |
- **Затворен #11 (S82.CAPACITOR blocker):** APK → external browser → DONE чрез Capacitor runtime hosted на runmystore.ai (commit 5bddc81) + FINE_LOCATION permission fix.
- **Нов #12 (S82.4 LABEL DESIGN):** TSPL codepage за кирилица + layout redesign за 50×30mm етикет.
- **Нов REWORK iOS Capacitor → S85.5:** Android работи; iOS Capacitor build и тест са отложени за след Phase A launch.
- **Нов S82.5 SECURITY:** биометрия + PIN за APP-то (Android BiometricPrompt API).

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

## 22.04.2026 — STANDARD: AI описание = максимален контекст за SEO
- **Решение:** Gemini Vision получава ВСИЧКА налична DB информация за артикула (не само снимка) → идеално SEO описание
- **Контекст pull:** име, категория, подкатегория, доставчик/бранд, размер, цвят, материал, проба/карат, cost_price (за price tier sense), retail_price, вариации (всички), custom attributes от biz_learned_data, snimka
- **Default:** 250 думи. Override бутон [📝 Дълго]: 350 думи (за luxury items)
- **Език:** tenant.lang (BG default за бета)
- **SEO keywords:** 3-4 автоматични в описанието (категория + материал + повод)
- **Cost при scale:** 1000 магазина × 100 артикула/мес = €28/мес (нищожно)
- **Засегнати модули:** products.php (S81), orders.php (S83 auto-fill), WooCommerce sync (S90)
- **Helper:** generateProductDescriptionFull() в config/gemini.php — единствен helper, преиспользван навсякъде
- **Rework:** REWORK #16 standing rule — всички AI описания минават през helper-а

## 22.04.2026 — S79 разделен на 4 под-сесии
- **Решение:** Оригинален S79 (DB foundations 1) твърде голям → раздели на S79.FIX.B (products UI) + S79.DB (schema_migrations) + S79.INSIGHTS (compute-insights 19 pf) + S79.CRON_AUDIT (cron + audit extension)
- **Защо:** Паралелна работа с нулев overlap + чисти single-responsibility сесии
- **Засегнати:** compute-insights.php (0→19), audit_log (extended), schema_migrations (created), products.php (10+ bugs closed)
- **Rework:** REWORK #10 closed, добавени #11 #12 (DB::tx retry + SAVEPOINT), #13 SECURITY, #14 print integration
- **Статус rework:** ✅ done за основната работа

## 22.04.2026 — Bluetooth печат pivot от Web Bluetooth → Capacitor plugin
- **Решение:** Web Bluetooth провален в Chrome тест → pivot към Capacitor Android plugin
- **Защо:** Web Bluetooth не надежден, Capacitor с @capacitor-community/bluetooth-le е production-ready
- **Засегнати:** js/printer.js (deprecated), js/capacitor-printer.js (new), android/ folder (new), products.php (бутон detect Capacitor env)
- **Rework:** CSV workaround временно, REWORK #14 добавен за tracking
- **Статус rework:** ⏳ in progress (S82)

## 22.04.2026 — S79.SECURITY отложена към предпоследна преди launch (S109)
- **Решение:** DB credentials в публично repo = реален P0 security incident, но Тихол reши да го отложи
- **Защо:** Риск управляем (MySQL root@localhost не е external access), бета период, фокус на Phase A
- **Засегнати:** config/database.php (credentials hardcoded), git history (password exposed)
- **Rework:** REWORK #13 добавен за S109 — задължителен преди public launch
- **Статус rework:** ⏳ pending S109

## 22.04.2026 — S82.CAPACITOR частично завършена, блокер за S82.CAPACITOR.2

**Извършена работа (11 commit-а):**
- Node 22 + mobile/ Capacitor 8.3.1 проект
- @capacitor-community/bluetooth-le@8.1.3 инсталиран
- GitHub Actions — Android APK Build работи, APK билдва успешно
- index.php session router (chat/onboarding/login)
- .htaccess DirectoryIndex + Options -Indexes fix
- Safe-area-inset CSS за bottom nav (6 файла: chat, products, sale, stats, warehouse, onboarding)
- js/capacitor-printer.js — BLE bridge за DTM-5811 (pair/print/test/forget API, TSPL 50×30mm)
- wizPrintLabelsMobile hook в products.php step 6
- printer-setup.php — dedicated pairing UI с diagnostic log
- ua-debug.php — diagnostic страница
- SESSION_S82_CAPACITOR_HANDOFF.md — пълен handoff документ за Claude Code

**БЛОКЕР (нерешен):**
APK-то отваря runmystore.ai в **external Chrome browser**, не в Capacitor WebView. `window.Capacitor` е undefined → BLE plugin не се активира.

**Доказателство:** UA от APK = `Mozilla/5.0 (Linux; Android 10; K) Chrome/147.0.0.0 Mobile Safari/537.36` (няма `wv` маркер)

**Пробвани 4 конфигурации:** server.url mode, webDir+JS redirect, hostname-only, URL param+sessionStorage fallback — нито една не инжектира bridge.

**Следваща стъпка:** Claude Code поема S82.CAPACITOR.2 с SESSION_S82_CAPACITOR_HANDOFF.md като референция. 6 варианти за опит: hybrid local+fetch, iframe+postMessage, различна Capacitor версия, custom WebView activity, PWA+Service Worker, hosted runtime.js.

**Засегнати файлове:** mobile/*, js/capacitor-printer.js, printer-setup.php, ua-debug.php, index.php, .htaccess, products.php (wizPrintLabelsMobile функция), chat/stats/sale/warehouse/onboarding .php (safe-area CSS)

**Commits:** aeb8187, 5f97e39, de49554, 0bbe881, 069c63c, 4cc1380, bb25add, 985e5fc, 5f384e0, 9115d5b, b86eb56

**Статус:** ⏳ Не е production-ready. APK инсталиран на Samsung Z Flip6 на Тихол, login работи, но BLE печат не работи.

---

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
| 4 | ВСИЧКИ модули — currency format | 17.04.2026 (i18n) | Замяна hardcoded "лв"/"€" с `priceFormat($amount, $tenant)` | S96 | 📋 audited 27.04.2026 (S87) — 206 sites; report: `docs/I18N_AUDIT_REPORT.md` §5; awaiting remediation (~6-10h mechanical) |
| 5 | ВСИЧКИ модули — BG текст | 17.04.2026 (i18n) | Замяна с `t('key')` или $tenant['language'] check | S96 | 📋 audited 27.04.2026 (S87) — 3,764 BG_STRING + 141 LOCALE; 80 reusable t() keys; report: `docs/I18N_AUDIT_REPORT.md`; awaiting remediation (Phase B 17-22h + Phase 1 110-180h) |
| 6 | products.php wizard state | 16.04.2026 (4 стъпки FINAL) | Премахни стария 3-accordion код остатъци | S80 | ⏳ pending |
| 7 | warehouse.php navigation | 19.04.2026 (hub архитектура) | Всеки подмодул има breadcrumb "← Склад › [Име]" | S87 | ⏳ pending |
| 8 | orders.php bottom nav | 19.04.2026 (orders НЕ е tab) | 4 таба bottom nav, НЕ 5 | S83 | ⏳ pending |
| 9 | selection-engine strict_module | 24.04.2026 (S79.SELECTION_ENGINE) | Добави $strict_module=true flag за module-specific feed (без home fallback) | S94+ | ⏳ pending |
| 10 | selection-engine trigger evaluator | 24.04.2026 (S79.SELECTION_ENGINE) | PHP match statement за trigger_condition string (напр. rev12m>80pct_threshold) | S94+ | ⏳ pending |
| 11 | ai_topics_catalog embeddings | 24.04.2026 (S79.SELECTION_ENGINE) | Gemini embeddings per topic.what → MMR със semantic similarity | S97+ | ⏳ pending |
| 12 | selection-engine monitoring | 24.04.2026 (S79.SELECTION_ENGINE) | Ако suppressed_count/total > 0.8 → reset най-старите | S100+ | ⏳ pending |
| 9 | inventory naming | 22.04.2026 | Rename inventory→stock_levels, inventories→inventory_sessions, inventory_items→inventory_session_lines. Update всички queries. | S87 | ⏳ pending |
| 10 | audit_log extension | 22.04.2026 | ✅ CLOSED в S79.CRON_AUDIT (store_id + source ENUM + user_agent + source_detail добавени) | S79.CRON_AUDIT | ✅ DONE |
| 11 | DB::tx() deadlock retry | 22.04.2026 | 3-attempt exponential backoff за MySQL 1213 deadlock error | S80 | ⏳ pending |
| 12 | DB::tx() SAVEPOINT nested | 22.04.2026 | Nested transactions support за многослойни операции | S80 | ⏳ pending |
| 13 | S79.SECURITY — DB + API credentials в публично repo | 22.04.2026 | env-based credentials (/etc/runmystore/db.env + api.env), .gitignore hardened, .env.example template, git filter-repo scrub (662 commits, 23→0 secrets), force push, MySQL + Gemini x2 + OpenAI rotated | **S79.SECURITY (23.04.2026)** | ✅ DONE |
| 14 | Print директен от products.php | 22.04.2026 | CSV workaround live днес. Capacitor plugin в процес (S82). Универсален printer plugin за iOS/Android → S85.5 | S82 + S85.5 | ⏳ in progress |
| 15 | chat.php responsive (всички breakpoints) | 23.04.2026 (responsive закон) | Tablet 768px+ columns layout, desktop 1024px+ sidebar pattern, запази v8 PHP логика | Следваща chat сесия | ⏳ pending |
| 16 | products.php responsive + P0 bugs | 23.04.2026 (responsive закон) | Tablet/desktop breakpoints + списък артикули 2-3 колони + existing P0 bugs fixes | S80 (разширен) | ⏳ pending |
| 17 | stats.php logic review + responsive | 23.04.2026 (responsive + логика съмнения) | ДВЕ задачи: (a) ревизия на всички stats изчисления — какво показва, колко е правилно. (b) responsive breakpoints. Direction: post-beta. | Post-beta (след S85) | ⏳ pending |
| 18 | chat.php role checks + simple mode redirect | 23.04.2026 (ЗАКОН №8) | Добави current_role() + can() на всички SQL queries; redirect към life-board.php ако ui_mode=simple; pills hidden за seller | S81 или нова сесия | ⏳ pending |
| 19 | products.php role checks | 23.04.2026 (ЗАКОН №8) | Seller НЕ вижда cost_price, retail_price на supplier level, profit columns; SQL conditional queries | S82 (разширен) | ⏳ pending |
| 20 | stats.php role checks | 23.04.2026 (ЗАКОН №8) | Seller НЕ вижда profit/revenue charts, само own-sales summary | S93 (в rewrite сесията) | ⏳ pending |
| 21 | life-board.php (Simple Mode) | 23.04.2026 (beta blocker) | ✅ CLOSED в S82.VISUAL Phase 2 (27.04.2026) — 580 реда нов файл с 4 collapsible cards + 4 ops glass buttons + mini dashboard/weather + AI Studio entry. ⚠ Toggle "Опростен →" в chat.php header pending verify (P0 S83). | **S82.VISUAL** | ✅ DONE |
| 22 | Shift management + daily summary | 23.04.2026 (beta scope) | Seller започва/затваря смяна; life-board показва "колко продадох днес" в края на деня | S86.5 (ако Тихол реши) | ❓ pending decision |
| 9 | Capacitor bridge | 22.04.2026 (S82.CAPACITOR блокер) | Debug защо WebView не инжектира window.Capacitor. Варианти: hybrid mode, iframe, custom activity. | S82.CAPACITOR.2 | ⏳ pending |
| 10 | iOS Capacitor | 22.04.2026 (Android-only сега) | След Android работи — добави iOS plugin като Universal Plugin wrapper | S85.5 | ⏳ pending |
| 9 | products.php wizard | 21.04.2026 (S78 #6 blocked) | Bug #6 renderWizard — verify след като wizard отваря в S79.FIX | S79.FIX | ⏳ pending |
| 10 | products.php main split | 21.04.2026 (S78 CC sweep) | Файлът е 8394 реда (5.6× над 1500 прага) — extract в partials/helpers; кандидат за rewrite | S80 | ⏳ pending |
| 11 | products.php Q-секции (q1-q6 home) | 22.04.2026 (Тихол: "трябва AI да предлага действие иначе безсмислено") | Всеки артикул в Q-секция трябва да има AI-генериран action button: 'Поръчай 5 при Иванов' / 'Промо -20%' / 'Прехвърли в магазин 2' и т.н. Source: ai_insights.action_label + action_type + action_data вече съществуват в DB. Compute-insights.php трябва да попълва тези колони. UI render да чете и показва бутон под всеки item. Tap на бутона → execute action (без чат). | S81 (AI features) | ⏳ pending
| 12 | products.php drawer detail screen | 22.04.2026 (свързано с #11) | Detail drawer също да показва AI primary action отгоре ("Препоръчвам: Поръчай 5 от Иванов — 320 лв profit/седм") + secondary actions. Бил е plain product card. | S81 | ⏳ pending
| 23 | S80→S81 verify adaptation | 25.04.2026 | 4 bugs: category_for_topic typo (5min), tenant filter в fetch_active_scenarios (5min), multi-statement в seed_scenario (15min), reverse-engineer ai_insights data_json schema + 8 verify handlers (~90min). Baseline run tenant=99 → Cat A=100%/D=100%. Tag v0.6.0-s80-diagnostic. | **S81** | ⏳ pending P0 |
| 24 | FAL_API_KEY add | 25.04.2026 (S82 AI Studio) | Тихол ръчно добавя FAL_API_KEY в /etc/runmystore/api.env. Без това bg removal endpoint връща 503. | Тихол | ⏳ pending P0 |
| 25 | On-device test S82 matrix qty save | 25.04.2026 (S82 CRITICAL bug fix verify) | Samsung Z Flip6 — verify че matrix qty save bug fix работи коректно (parseInt {qty,min} → NaN → 0 беше data loss). | Тихол | ⏳ pending P0 |
| 26 | S82 wizard hooks решение | 25.04.2026 | Тихол кажи: integrate в новия AI Studio модул или delete (wizAIProcessPhoto, AI Studio CTA в step 3, auto-populate в step 4). | Тихол → Chat 1 | ⏳ pending P1 |
| 27 | S82 AI Studio location | 25.04.2026 | Тихол кажи: root /ai-studio.php (отделен модул accessible от bottom nav) или embedded overlay в products.php (от wizard step 3). | Тихол → Chat 1 | ⏳ pending P1 |
| 28 | Tag v0.7.0-s82-shell | 25.04.2026 | След on-device test (RQ #25) → tag rollback point преди AI Studio frontend integration. | Chat 1 | ⏳ pending P1 |
| 29 | products_fetch.php cleanup | 25.04.2026 | Файлът е 568KB, никой не го reference-ва. Кандидат за delete. | A1 финал | ⏳ pending P2 |
| 30 | Orphaned dead code (toggleTheme/initTheme) | 25.04.2026 | Стари функции в 5 модула. Безвредно но мръсно. Cleanup при S82 finalize. | Chat 1 | ⏳ pending P2 |
| 31 | promotions.php basic (Phase A2 — pull-up) | 25.04.2026 (5 AI brainstorm consensus) | Implementation: 2 типа правила (quantity_threshold + same_model_qty). Cart auto-apply в sale.php. Owner UI прост. БЕЗ AI suggestions, БЕЗ combo. ~3-4 сесии. PromotionEngine клас + DB schema (promotions+promotion_rules+promotion_applications). | Phase A2 (преди ЕНИ май) | ⏳ pending P0 |
| 32 | scan-document.php (Phase A2 — pull-up) | 25.04.2026 (Тихол КРИТИЧНО ОТ ДЕН 1) | Basic Gemini Vision parse → Тихол approve → INSERT. БЕЗ 5-layer validation, БЕЗ BRRA API, БЕЗ accountant email (тези → Phase B). | Phase A2 | ⏳ pending P0 |
| 33 | deliveries.php (Phase A2 — pull-up) | 25.04.2026 (Тихол MVP must) | Приемане на стока (с OCR от RQ #32) + voice. | Phase A2 | ⏳ pending P0 |
| 34 | suppliers.php (Phase A2) | 25.04.2026 (без него няма доставки) | Каталог доставчици. БЕЗ EIK + BRRA lookup за MVP. | Phase A2 | ⏳ pending P0 |
| 35 | transfers.php (Phase A2 — pull-up) | 25.04.2026 (Тихол multi-store от ден 1) | Между магазини. Multi-store resolver basic. | Phase A2 | ⏳ pending P0 |
| 36 | inventory.php "Category of the Day" (Phase A2) | 25.04.2026 (Тихол MVP must) | PHP логика (НЕ AI) — кажи на Пешо коя категория да преброи, колко items, колко минути. | Phase A2 | ⏳ pending P0 |
| 37 | sale.php rewrite (Phase A2 — pull-up от B) | 25.04.2026 | Voice + camera + numpad + дребно/едро. Като stock movement (не fiscal sale). PromotionEngine integration ОТ ДЕН 1 (RQ #31 готов преди rewrite). | Phase A2 | ⏳ pending P0 |
| 38 | DOCS — S52 pricing планове в BIBLE | 25.04.2026 | Изтрит от userMemories. Постоянна BIBLE_v3_0_TECH секция: FREE €0 / START €19 / PRO €49+€9.99/store. Trial 1 мес PRO → ден 29 избор. | Когато BIBLE update | ⏳ P2 |
| 39 | DOCS — Термо принтер info в BIBLE_TECH §Bluetooth Printer | 25.04.2026 | 200 бр поръчани, $15 cost, €19.99 sell, RunMyStore лого. ESC/POS. Първи пристига края на април 2026. Lock-in чрез лесен setup. | Когато BIBLE update | ⏳ P2 |
| 40 | DOCS — biz_learned_data spec | 25.04.2026 | Phase 2 cross-tenant learning. Полета: id, business_type, field_type (subcategory/size/color/unit), value, usage_count, created_at. AI се учи от клиентите. | Phase D документация | ⏳ P2 |
| 41 | chat.php toggle "Опростен →" header verify | 27.04.2026 (S82.VISUAL postcheck) | Verify ръчно от Тихол; ако липсва → 5-min Code #1 add toggle button в production rms-header. | **S83 P0** | ⏳ pending |
| 42 | /ai-studio.php frontend rewire към new helpers | 27.04.2026 (S82.STUDIO.APPLY findings) | Frontend чете `tenants.ai_credits_*` (стария path) → пренапиши да чете през get_credit_balance() от ai-studio-backend.php. Buttons → ai-studio-action.php (нов endpoint), не ai-image-processor.php. | **S84 STUDIO.REWIRE** | ⏳ pending P0 |
| 43 | AI Studio modal wizard step 5 — verify visible | 27.04.2026 (Тихол flag: "не се пилазват новите модули") | Тихол entry day verify че при добавяне на артикул, в стъпка 5 modal-ът от STUDIO.12 (v0.7.23) реално се появява. Ако не → wizard hook fix. | **S83 P0** | ⏳ pending P0 |
| 44 | 4 placeholder AI prompt templates approve | 27.04.2026 (S82.STUDIO.APPLY) | Тихол одобрява per template wording: clothes / jewelry / acc / other. Засега is_active=0. Lingerie готов. | Тихол spokojno | ⏳ pending P1 |
| 45 | tenants.plan ENUM — добави 'biz' | 27.04.2026 (S82.STUDIO.APPLY finding) | Code #2 не extend-на ENUM защото нямаше 'biz' в production. Когато се отвори BIZ tier → ALTER TABLE + seed update. | Когато BIZ tier launch | ⏳ pending P2 |
| 46 | DROP legacy tenants.ai_credits_* колони (30 дни grace) | 27.04.2026 (S82.STUDIO.APPLY backward-compat) | След 30-дневен grace period (drop date ~2026-05-27), нова migration премахва legacy `ai_credits_bg/tryon/total` колони. Преди drop verify че frontend rewire (RQ #42) е приключил. | ~2026-05-27 (S95+) | ⏳ pending P2 |
| 47 | S82.DIAG.FIX (Cat A=100%/D=100%) — beta blocker | 27.04.2026 (S82.STUDIO.APPLY findings) | A=47.83% / D=21.43% pre-existing от S80/S81. Не regression от schema, но Rule #21 нарушен (apply без 100%). Преди ENI launch (14-15.05) → DOD met. Bugs: lost_demand_pos schema, basket_pair_b_pos missing total, negative scenarios overlap (10+ Cat A FAIL), positive items=0 (5+ FAIL). | **S85 (преди ENI)** | ⏳ pending P0 |

---

| 16 | AI описание STANDARD | 22.04.2026 (Тихол решение) | STANDING RULE — всички AI описания минават през generateProductDescriptionFull(). Pull MAX context: всички DB полета + image. Default 250 думи, override 350 luxury. Език tenant.lang. 3-4 SEO keywords automatic. Cost ~€28/мес за 1000 магазина. | ALL modules с AI описание (S81, S83, S90) | ⏳ standing rule |
| 18 | IRON PROTOCOL | 24.04.2026 | STANDING RULE: vseki chat chete IRON PROTOCOL pri otvaryane. ZABRANA na base64. Length warning pri ~30-40 suobshteniya. | ALL | standing |
| 19 | PARALLEL COMMIT CHECK | 24.04.2026 | Pri paralelni chat-ove VINAGI 'git status' + 'git log -5' predi patch. Inache drug chat moje da include-ne tvoite promeni v svoya commit (viz S79.SELECTION_ENGINE + IRON PROTOCOL sluchay). | ALL | standing |
| 20 | HEREDOC MARKDOWN BAN | 24.04.2026 | STANDING: heredoc bez emoji/bold/tables/headers/backticks/BG kavichki. 2-stupkov workflow: ASCII heredoc + Python s \u escapes. | ALL | standing |
| 21 | DIAGNOSTIC PROTOCOL AI | 24.04.2026 | STANDING: vsyaka promyana na AI logika (pf funktsii, build-prompt, selection-engine, nov AI modul, ai_insights schema) PREDI commit minava prez DIAGNOSTIC_PROTOCOL.md. TDD workflow: Tihol opisva -> scenarii -> kategorizatsia A/B/C/D -> fixtures -> impl -> diagnostic -> A+D 100% PASS ili rollback. | ALL AI modules | standing |

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
| **S80** | **ROLES_AND_MODES_FOUNDATION** (beta blocker) | **DB, helpers** | **users.role + tenants.ui_mode migration, helpers/auth.php, helpers/simple-mode.php, ROLES_MATRIX.md, life-board-router.php placeholder** | **S79.SECURITY done** |
| **S81** | **LIFE_BOARD_SKELETON** (beta blocker, без AI) | **new file** | **life-board.php (header + оборот + 4 бутона + ЧЗВ static JSON), 4 бутона → ?simple=1 на sale/products/deliveries/inventory** | **S80 done** |
| ~~S80-old~~ | ~~DB foundations 2~~ | ~~→ перенесено~~ | ~~reclaimed за beta work~~ | — |
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
| S90 | WooCommerce integration v1 🟡 skeleton готов (S81.WOO_API, 24.04.2026) | new | integrations/woo.php, config/woo.php, docs/WOOCOMMERCE_API_SPEC.md | REAL_TIME_SYNC |
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

# 📊 PHASE OVERVIEW (REVISED 25.04.2026 — pull-up roadmap за ЕНИ май)

```
Phase A1 — Foundation (СЕГА → 5 май)                  ~55% ⏳
  ├─ S79.SECURITY                                    100% ✅ (e15f719, verified 25.04)
  ├─ S79.INSIGHTS (compute-insights.php 19 pf)       100% ✅
  ├─ S79.SELECTION_ENGINE (MMR + 1000 topics)        100% ✅
  ├─ S81.BUGFIX.V3.EXT (14 mobile bugs Z Flip6)      100% ✅
  ├─ S80.DIAGNOSTIC framework infrastructure         100% ✅ deployed
  ├─ S80→S81 verify adaptation (verify_engine)         0% ⏳ S81 (~2-3h)
  ├─ S82 AI Studio backend (ai-image-* endpoints)    100% ✅
  ├─ S82 AI Studio frontend integration               20% ⏳ Chat 1
  ├─ products.php wizard P0 bugs (3 known)             0% ⏳ ТИХОЛ УТРЕ
  ├─ DB guards (negative, FK, idempotency, cents)      0% ⏳ A1 финал
  └─ Stock ledger (append-only)                        0% ⏳ A1 финал

Phase A2 — Operations Core (5-15 май, преди ЕНИ)       0% ⏳
  ├─ products.php finalize + real product entry        0% ⏳ ТИХОЛ УТРЕ
  ├─ suppliers.php                                     0% ⏳
  ├─ scan-document.php (OCR — basic Gemini Vision)     0% ⏳ КРИТИЧНО ОТ ДЕН 1
  ├─ deliveries.php (с OCR)                            0% ⏳
  ├─ Bluetooth printer integration (DTM-5811 готов)   30% 🟡 Capacitor in progress
  ├─ sale.php rewrite (stock movement, не fiscal)      0% ⏳
  ├─ transfers.php                                     0% ⏳
  ├─ inventory.php "Category of the Day"               0% ⏳
  └─ promotions.php basic (PULL-UP от Phase D!)        0% ⏳

Phase B — AI слой + полиране (юни-юли)                 0% ⏳
  ├─ AI чат базов (voice interface)                    0% ⏳
  ├─ AI suggestions за промоции                        0% ⏳
  ├─ stats.php (owner dashboard)                       0% ⏳
  ├─ finance.php (приходи/разходи)                     0% ⏳
  └─ orders.php basic (draft → sent → received)        0% ⏳

Phase C — Hardening (август-октомври)                  0% ⏳
  ├─ AI Advisor (eval framework 90%+, tenant=99)       0% ⏳
  ├─ Voice creation на промоции/продукти               0% ⏳
  ├─ promotions combo + B2B + loyalty stack            0% ⏳
  ├─ orders.php full (12 entry points)                 0% ⏳
  ├─ Action Broker (ai-action.php router)              0% ⏳
  ├─ Real-time Pusher (multi-device sync)              0% ⏳
  └─ Stats advanced + 6Q dashboard                     0% ⏳

Phase D — Pre-Launch (ноември)                         0% ⏳
  ├─ WooCommerce + Shopify integrations                5% 🟡 skeleton (S81.WOO_API)
  ├─ biz_learned_data (cross-tenant learning)          0% ⏳
  ├─ Capacitor offline queue                           0% ⏳
  ├─ GDPR (export/delete tenant)                       0% ⏳
  ├─ Stripe Connect                                    0% ⏳
  ├─ Settings polish + Onboarding wizard               0% ⏳
  ├─ Loyalty migration (от donela.bg)                  0% ⏳
  └─ Касов бон/ФУ (АКО legally нужно)                  0% ⏳

Phase E — Public Launch (декември 2026 → януари 2027)  0% ⏳
  ├─ App Store + Play Store                            0% ⏳
  ├─ Marketing landing                                 0% ⏳
  ├─ Public AI Sales Agent (€9.99/мес PRO add-on)      0% ⏳
  ├─ Anomaly detection + health checks                 0% ⏳
  └─ 500 golden tests CI gate                          0% ⏳
```

**Overall: ~12% complete. ЕНИ first sale target: 10-15 май 2026. Public launch: декември 2026.**

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


# ⚙️ IRON PROTOCOL — ЗАДЪЛЖИТЕЛНО ЗА ВСЕКИ CHAT (24.04.2026)

> **ВСЕКИ** работен chat прочита § IRON PROTOCOL при отваряне.
> Шеф-chat не повтаря правилата в prompts — казва: "Изпълни IRON PROTOCOL + твоята задача е: ...".
> Ако нарушаваш → Тихол казва "IRON PROTOCOL" = reminder.

## ⚠️ ПРЕДУПРЕЖДЕНИЕ ЗА ДЪЛЖИНА НА CHAT

Chat-ът има ограничен context window. При натрупване на много съобщения → рискуваш да се **прекъсне сесията**.

**Ти (chat) си длъжен:**
- След ~30-40 съобщения → питай Тихол: "Чатът е дълъг, да затварям и започнем нов?"
- При първи признак за забавяне → СПРИ, направи closeout.
- Винаги commit + push междувременно.
- Не чакай колапс — проактивно предупреждавай.

Същото важи за ШЕФ-CHAT — при ~50+ съобщения в планиране → предлагай restore prompt за нов шеф.

## 🟢 НАЧАЛО НА СЕСИЯ

1. Прочети COMPASS от DROPLET (НЕ github raw — 5min CDN cache):
   cat /var/www/runmystore/MASTER_COMPASS.md | head -100
2. Scan commits: cd /var/www/runmystore && git log --oneline -10
3. Питай Тихол: "Други активни chat-ове? На кои файлове?"
4. Потвърди scope в 2-3 реда, чакай "ОК".

## 🔵 ПО ВРЕМЕ НА РАБОТА

### Комуникация
- Само български. Кратки съобщения (5-10 реда).
- Никога "готов ли си?". Никога "може ли?". Действай.
- ALL-CAPS от Тихол = frustrated → още по-кратко.

### Конзола
- МАКСИМУМ 2 команди наведнъж. Чакай резултат.
- Никога sed. Python scripts only.

### 🚫 ЗАБРАНА НА BASE64 (критично, 24.04.2026)

**НИКОГА не използвай base64 encoding за скриптове, файлове или payload-и.**

Причина: Claude Opus 4.7 safety системата блокира base64 output → прекъсва отговори по средата, чатът спира, работата се губи.

**Правилен подход — винаги plain text:**
- Малки скриптове (под ~200 реда) → paste директно в nano /tmp/script.py
- Големи скриптове → cat heredoc със single quotes ('EOF')
- Много големи файлове → разделяй на 2-3 скрипта или използвай git
- Бинарни payload-и (изображения, PDF) → НИКОГА inline → scp / git LFS

**Правилен heredoc pattern:**
cat > /tmp/script.py << 'EOF'
#!/usr/bin/env python3
print("работи без base64")
EOF
python3 /tmp/script.py

Ако chat дава base64 → Тихол казва "IRON PROTOCOL" → chat преформулира в plain text.


### 🚫 ЗАБРАНА НА MARKDOWN В HEREDOC (STANDING, 24.04.2026)

Heredoc payload (`cat > file.md << EOF ... EOF`) НЕ съдържа:

- Emoji (нито в текста, нито в code blocks)
- Markdown bold (звезди)
- Markdown headers (###)
- Markdown tables (pipe delimiters)
- Code fences (triple backtick)
- Български кавички „ “
- Долар знак без escape
- Backtick, backslash

**Причина:** Тези символи чупят bash parser-а когато heredoc-ът се paste-ва в конзола. Output-ът се реже по средата, файлът се създава частично, Python scripts грешат със SyntaxError.

**Правилен workflow — 2 стъпки:**

1. **Stupka 1:** Heredoc > `/tmp/content_raw.txt` с само ASCII placeholders (без emoji, без tables, без headers). Това е safe за paste.
2. **Stupka 2:** Python скрипт чете raw файла + използва вътрешен Python string literal с `\u` unicode escapes за кирилица/emoji за да напише final markdown. Python стринговете НЕ минават през bash parser.

**Алтернатива:** един голям Python скрипт с `\u` escapes за цялото съдържание вътре (не чете external файл).

**Неспазване = IRON PROTOCOL violation.** Тихол казва "IRON PROTOCOL" → chat преформулира в 2-стъпков workflow.


### 🧪 ЗАДЪЛЖИТЕЛНО — DIAGNOSTIC PROTOCOL ПРИ AI ПРОМЯНА (STANDING RULE #21)

**ВСЯКА промяна на AI логика ПРЕДИ commit минава през DIAGNOSTIC_PROTOCOL.md.**

Референтен документ: `DIAGNOSTIC_PROTOCOL.md` в repo root (323 реда, v1.0, 24.04.2026).

#### Кога се прилага (задължително)

- Нова pf-функция в compute-insights.php
- Промяна на съществуваща pf-функция (SQL, threshold, logic)
- Нов AI модул (onboarding AI, chat AI, action broker)
- Промяна на build-prompt.php context layers
- Промяна на selection-engine.php (MMR, weights, suppression)
- Нов cron job който генерира ai_insights
- Промяна на ai_insights schema (нови колони, ENUM values)

#### Test-Driven workflow (Claude следва реда ЗАДЪЛЖИТЕЛНО)

1. Тихол описва с прости думи какво прави модулът/функцията
2. Claude пише 10-30 сценарии в `seed_oracle` (покриват 6-те фундаментални въпроса)
3. Тихол категоризира A/B/C/D за всеки сценарий
4. Claude пише fixtures (seed scripts) + oracle expectations
5. Claude имплементира SQL/PHP
6. Claude пуска diagnostic и сравнява
7. Category A + D = **100% PASS** → commit. Ако не → **rollback** + re-fix. Category B/C FAIL → commit OK, но документирай в COMPASS.

#### Non-negotiable правила

- Без diagnostic run **commit НЕ се приема** за AI код
- Category A (critical) FAIL = **rollback**, не "fix later"
- Category D (boundary) FAIL = SQL bug, веднага
- Никога на production tenant (47 = ЕНИ). Само test tenant = 7
- Всяко diagnostic run записва в `diagnostic_log` таблица
- "AI DIAG ПУСНИ" от Тихол = Claude пуска пълен scan веднага

#### Автоматични тригери

- **Нов AI модул** → diagnostic ПРЕДИ commit (ръчно)
- **Понеделник 03:00 Europe/Sofia** → weekly cron (автоматично)
- **1-ви ден месец 04:00** → monthly full scan + performance metrics
- **Тихол или клиент забележи bug** → diagnostic ПРЕДИ разследване

#### При FAIL — протокол

1. Уникален ID: `BUG-YYYYMMDD-module-scenario`
2. Категоризирай A/B/C/D
3. Логни в `diagnostic_log.failures_json`
4. Добави entry в COMPASS LOGIC CHANGE LOG
5. A/D поправи сега. B следваща сесия. C Тихол решава.
6. Re-verify след fix (пусни точно този сценарий)
7. Ако bug е нов случай → добави нов scenario в seed_oracle

#### Прагове по maturity

- **New (в разработка):** A+D = 100%, B+C = min 60%
- **Beta (в ЕНИ тест):** A+D = 100%, B+C = min 80%
- **Stable (production):** A+D = 100%, B+C = min 90%
- **Frozen:** A+D = 100%, B+C = min 95%

**Неспазване = IRON PROTOCOL violation. Тихол казва "IRON PROTOCOL" → reminder.**



### Git
- git pull origin main ПРЕДИ всеки commit
- Backup: cp file /root/file.$(date +%H%M).bak
- php -l преди commit
- Commit: S<XX>.<SUB>: [описание]
- Push ВЕДНАГА след commit
- Tag в края: v<version>-s<session>-<desc>

## 🔴 БЛОКЕР

- Не работи след 2 опита → СПРИ, не опитвай 3-ти
- Покажи точния error на Тихол
- Питай за screenshot / log / debug info
- НИКОГА revert самостоятелно — покажи и питай

## ⚫ ЗАЩИТЕНИ ЗОНИ

Друг chat работи на файл X → ти си забранен на X.
Ако задачата изисква → СПРИ, питай Тихол "пипам или отлагам?".

## 🟡 КРАЙ НА СЕСИЯ — 7 СТЪПКИ (без тях сесията НЕ Е ЗАТВОРЕНА)

| # | Стъпка | Verify |
|---|---|---|
| 1 | COMPASS update (последна сесия, phase%, P0 bugs, REWORK, LOG) | grep последна |
| 2 | SESSION_S<XX>_HANDOFF.md | ls SESSION_S*_HANDOFF.md |
| 3 | git status clean | git status |
| 4 | pull → commit → push + tag | git log --oneline -3 |
| 5 | Verify от remote | git fetch && git log origin/main --oneline -5 |
| 6 | Съобщение към Тихол | формат долу |
| 7 | Тихол потвърди "OK" | чакай отговор |

### Формат на съобщение (стъпка 6):
✅ S<XX> ЗАТВОРЕНА.
COMMIT: <hash>
TAG: <tag>
HANDOFF: <file>

НАПРАВЕНО: [точки]
ОСТАВА: [за следваща]

COMPASS: commit <hash>. Друг chat prav git pull.

## 🟠 WIP / БЛОКЕР ИЗХОД

Не може да приключи?
1. git commit -m "S<XX>.WIP: [status]"
2. SESSION_S<XX>_WIP_HANDOFF.md с блокер описание
3. git push задължителен
4. Съобщение: "S<XX> БЛОКЕР: [какво]. Следващ chat продължава."

## 🟣 РОЛИ

**Тихол не е developer.** Не technical jargon, не английски, не 10 команди наведнъж. Той е "ръцете".

- Технически решения (code/git/backup/инструмент) → chat решава
- Логически/продуктови (UX/текстове/wizard) → chat пита Тихол

## 🔵 5-те ЗАКОНА (НЕ СЕ НАРУШАВАТ)

1. Пешо не пише нищо (voice/tap/photo, не native keyboard)
2. PHP смята, AI говори
3. AI мълчи при грешка (fallback template)
4. "AI" в UI, НИКОГА "Gemini"/"fal.ai"
5. priceFormat() + t() — никога hardcoded "лв"/"€"/BG текст

### DB naming (задължително)
products.retail_price (НЕ sell_price), inventory.quantity (НЕ qty), products.code (НЕ sku), sales.status='canceled' (едно L)


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


---

## 🔒 TODO ПРЕДИ БЕТА (S81 PENDING)

### AI Credits Paywall — активиране
- **Статус:** Сега скрит credit bar в products.php AI стъпка (S81)
- **При бета:** Активирай credit bar с лимити per план:
  - FREE: 0 AI операции/мес
  - START: 50 AI операции/мес (€0.05 birefnet, €0.50 nano-banana)
  - PRO: 300 + 50/магазин
- **Цени (от S81 mockup):**
  - Махни фона (birefnet): €0.05
  - AI модел носи дрехата (nano-banana): €0.50
  - Студийна снимка: €0.50
- **Файл:** products.php — unhide `.credit-bar` + activate `falCostTrack()` paywall check
- **Сесия за активация:** преди beta launch (target S100+)


---

## 24.04.2026 — S81.BUGFIX.V2 (CHAT 1.3) — P0 bugs в products.php wizard

**Контекст:** CHAT 1.2 забуксува в mobile CSS loop (5 опита провалили) → REVERT. CHAT 1.3 с нов P0-first приоритет.

### ✅ P0 #1 — Name input false "Въведи наименование" — FIXED
- **Причина:** onclick handlers на "Запази" (ред 5351) и "Напред variant" (ред 5352) четяха `S.wizData.name` директно без да викат `wizCollectData()`. На mobile `oninput` понякога не сработва (IME, glide-typing, autocomplete) → DOM има стойност, state е празно → фалшиво "Въведи наименование".
- **Fix:** Добавен `wizCollectData();` в началото на двата onclick-а. Синхронизира DOM → state преди validation.
- **Commit:** `2fb55ff` — v0.6.0-s81-bugfix-v2 tag pending в края на сесията
- **Тест:** ЕНИ Android — потвърден от Тихол ✓

### ✅ P0 #2 — Voice Microphone в Android app — FIXED (24.04.2026)
- **Fix:** Claude Code направи WebChromeClient permission passthrough в MainActivity.java + runtime permission request (RECORD_AUDIO + CAMERA + MODIFY_AUDIO_SETTINGS). Commit `aab28ef`.
- **Потвърдено от Тихол:** микрофонът работи в Capacitor APK ✓

### 🗂 LEGACY (запазен за референция) — старата хипотеза P0 #2 DEFERRED TO S82
- **Диагностика:** Браузър (Chrome HTTPS) работи ✓. Android Capacitor app НЕ работи.
- **Причина:** Android app (Capacitor) не иска automatically mic permission. Трябва:
  1. `RECORD_AUDIO` permission в `android/app/src/main/AndroidManifest.xml`
  2. Runtime permission request при първо отваряне
- **Защо deferred:** `android/` папката е извън обхвата на CHAT 1.3. ЕНИ може да ползва browser (HTTPS) или PWA (Add to Home Screen) междувременно — микрофонът работи там.
- **S82 task:** Отделна сесия с Android достъп за Capacitor permission fix.

### ⏸ P0 #3 — Barcode Scanner в Android app — DEFERRED TO S82
- **Диагностика:** Браузър (Chrome HTTPS) работи ✓. Android Capacitor app НЕ работи.
- **Причина:** Идентична с P0 #2 — Android app не иска automatically CAMERA permission.
- **Fix в S82:** `<uses-permission android:name="android.permission.CAMERA" />` + runtime permission request.
- **Workaround за ЕНИ:** Chrome Android browser работи.

### ⏸ P1 #4 — Mobile CSS (бутони под Android nav bar) — DEFERRED
- **Защо:** CHAT 1.2 опита 5 пъти (padding-bottom, env(safe-area-inset-bottom), sticky footer, inline styles, 80-120px fallback) — всички провалили без screenshot. Prompt правило #10: без screenshot не опитвам.
- **S82 task:** С реален screenshot от Тихол + remote DevTools session → прост подход (margin-bottom на body / overscroll-behavior / scroll-to-visible при focus).

---

## 🔒 S81.BUGFIX.V3 — MOBILE CSS REWORK (незапочнат, pending screenshots готови)

**Статус:** Screenshots получени от Тихол на Samsung Z Flip6, Android 16, Capacitor WebView. 3 различни bug-а идентифицирани.

### Проблем 1 — БРОЙ поле: "+" бутон отрязан отдясно
- **Симптом:** "−" видим вляво, "1" в центъра, "+" **невидим** (overflow-clipped)
- **Контекст:** products.php wizard, стъпка "Name+Price+Qty"
- **Вероятна причина:** `.qty-row` flex container е по-широк от viewport, или `.quantity-input` има fixed width

### Проблем 2 — Footer бутони под Android nav bar
- **Симптом:** "Назад / Принтер / Запази" се виждат наполовина, долната половина е под системната навигация
- **Класически safe-area bug** — `env(safe-area-inset-bottom)` не е приложен правилно в wizard footer
- **Opus 4.7 opinion:** CHAT 1.2 опита 5 пъти без screenshot. Сега имаме screenshot → прост fix:
  - `.wiz-footer { padding-bottom: max(16px, env(safe-area-inset-bottom)); }`
  - Или `<body>` ниво: `padding-bottom: env(safe-area-inset-bottom)`

### Проблем 3 — Horizontal scroll (екрана се мърда ляво-дясно)
- **Симптом:** Minor swipe ляво/дясно движи целия layout
- **Вероятна причина:** Нещо (вероятно qty-row или gradient border) overflow-ва viewport width
- **Fix:** `body { overflow-x: hidden; }` + намиране на реалния overflow виновник

### Следваща сесия (кога Тихол се върне):
1. Diagnostics: grep в products.php за `.qty-row`, `.quantity-input`, `.wiz-footer`
2. Fix 1 bug наведнъж (P1 first Проблем 1, след това 2, след това 3)
3. Commit след всеки → Тихол тества на Samsung → confirms → следващ
4. git tag `v0.6.1-s81-bugfix-v3-mobilecss` в края

---

## 🔒 S82 REWORK QUEUE (записано в S81.BUGFIX.V2)

### 1. Android Capacitor permissions (P0 — блокер за app users)
- **Файлове:** `android/app/src/main/AndroidManifest.xml` + Capacitor plugin check
- **Задачи:**
  - Добави `<uses-permission android:name="android.permission.RECORD_AUDIO" />`
  - Добави `<uses-permission android:name="android.permission.CAMERA" />` (ако не е вече)
  - Провери `@capacitor/microphone` plugin или custom bridge
  - Runtime permission request на първо отваряне на mic/camera
- **Тест:** Rebuild APK → Тихол инсталира на Android → тапва 🎤 → prompt "Разреши микрофон?" → Allow → работи.
- **Блокер за:** Пешо voice-first workflow в app версията

### 2. Mobile CSS — wizard footer бутони под Android nav bar (P1)
- **Изисква:** Screenshot от Тихол от Chrome Android remote DevTools
- **Опитани (и провалили) в CHAT 1.2:** safe-area-inset-bottom, padding-bottom 200px, inline styles, sticky footer, 80-120px fallback
- **Ново предложение:** margin-bottom на body + `overscroll-behavior: contain` + `scrollIntoView()` при button focus
- **Workaround до fix:** Пешо може да scroll-не manually или да обърне device portrait/landscape

### 3. CAMERA permission в Android app (P0 — блокер за баркод сканиране)
- **Потвърдено app-only issue** (browser работи).
- Същият подход като P0 #2:
  - Добави `<uses-permission android:name="android.permission.CAMERA" />` в AndroidManifest.xml
  - Runtime permission request при първо отваряне на камерата
  - Test: wizard → поле Баркод → tap scan → prompt → Allow → работи

---

# 📋 LOGIC CHANGE LOG

## 24.04.2026 — S79.INSIGHTS.COMPLETE + DIAGNOSTIC FRAMEWORK planning

### Решения
1. **pfHighReturnRate Cartesian bug fix.** Стар SQL: `LEFT JOIN returns` + `SUM()` дублира quantity ×N когато има N sale_items. Нов: subquery aggregation pattern (JOIN (SELECT SUM...) sold_agg + LEFT JOIN (SELECT SUM...) ret_agg). Impact: производствен bug — показваше 100% връщания вместо реални 10% при N≥10.
2. **compute-insights.php = 19/19 pf*() функции работят** (от 0 skeleton в S77 план). 10/19 генерираха insights преди S79, 9/19 връщаха 0 заради schema gap (resolved S79.SCHEMA) + data gap (resolved S79.SEED).
3. **cost_price остава NOT NULL.** S79 experiment временно ALTER-на колоната до NULL-able, revert-нат. 0 = "не знам", wizard принуждава въвеждане (UX rule, не DB rule).
4. **seed_oracle table = permanent.** Нова DB таблица за регресионни тестове. Остава на test tenant=7. Всеки AI модул ще има oracle entries.
5. **DIAGNOSTIC FRAMEWORK planned (S80).** Continuous integration testing: при всеки нов AI модул + weekly cron (понеделник 03:00) + monthly + ръчно "AI DIAG ПУСНИ". Пълен план в SESSION_S79_INSIGHTS_COMPLETE_HANDOFF.md.

### Засегнати модули
- `compute-insights.php` — SQL fix в pfHighReturnRate (от ред ~1075)
- DB schema — нови `seed_oracle`, `returns`; колона `sale_items.returned_quantity`; колони `products.has_variations/size/color`
- `/tmp/s79_seed.py` (1224 реда) — reference за refactor в S80 към `/tools/diagnostic/`

### Test coverage
- **53/72 oracle scenarios PASS** (74%)
- 0 real SQL bugs остават след S79.BUGFIX
- 19 FAIL = TOP-N background pollution, не bugs, S80 решава с pristine tenant mode

### Tags
- `v0.7.0-s79-insights-complete` (pushed)

### Commits
- `c9a49f5` — pfHighReturnRate Cartesian fix
