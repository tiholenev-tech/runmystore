# 🎯 STATE_OF_THE_PROJECT — Live Snapshot

**Дата на последен update:** 02.05.2026 (v2.5 inventory section добавена)
**Версия:** v1.1 (+ LIVE BUG INVENTORY top section)
**Update протокол:** ВСЕКИ Claude Code в края на сесията update-ва САМО този файл (не COMPASS, не handoff). **Шеф-чат update-ва секция `📋 LIVE BUG INVENTORY` в EOD протокола.**
**Шеф-чат първо чете ТОЗИ файл, после COMPASS, после последен handoff.**

---

## 📋 LIVE BUG INVENTORY — single source of truth ⭐ NEW v2.5

> **ПРАВИЛО:** Тази секция е първото нещо което всеки шеф-чат чете в Phase 0.5. Тя aggregate-ва P0/P1/P2 от 5-те източника (PRIORITY_TODAY + COMPASS BUG TRACKER + REWORK QUEUE + STRESS_BOARD + own log) в едно място. Шеф-чат update-ва в EOD: добавя нови, marks completed.
>
> Aggregated дата: 02.05.2026 06:30 (initial extract)

### 🔴 P0 — BLOCKERS (12 items)

| # | Bug code | Module | Описание | Source | Target session |
|---|---|---|---|---|---|
| 1 | C1 | products.php | "+ Добави размер" бутон липсва (Sprint B claim 8/8 → реално 6/8) | COMPASS L1009 + PRIORITY 02.05 | S92.PRODUCTS.B_FIX |
| 2 | C5 | products.php | ChevronLeft (back arrow) inline без CSS class — compliance gap | COMPASS L1013 + PRIORITY 02.05 | S92.PRODUCTS.B_FIX |
| 3 | D3 | products.php | Категории не filter-ват по supplier (двойна логика global vs per-supplier) | COMPASS L1018 КРИТИЧЕН | Sprint B/C |
| 4 | D5 | products.php | Duplicate detection late at save (трябва LIVE при писане на име) | COMPASS L1020 КРИТИЧЕН | Sprint B/C |
| 5 | sale-race | sale.php:132 | Race condition: UPDATE inventory SET quantity = GREATEST(...) — двама паралелни купувачи | STRESS_BOARD ГРАФА 3 | S92.STRESS.DEPLOY |
| 6 | insights-route | compute-insights.php:235 | $module='products' hardcoded но life-board търси 'home' (99% невидими) | STRESS_BOARD ГРАФА 3 | S91.INSIGHTS_HEALTH (verify if real fix) |
| 7 | sales-pulse | tools/diagnostic/cron/sales_pulse.py | Пуска ВСИЧКИ продажби с DATE_ADD(DATE(NOW())) — нереалистична distribution | STRESS_BOARD ГРАФА 3 | S92.STRESS.DEPLOY |
| 8 | crontab-deploy | infra | STRESS Етап 1+2 cron не са installed на www-data → 4 дни тишина в snapshots | PRIORITY L174 + LIVE finding 02.05 | S92.STRESS.DEPLOY |
| 9 | RWQ-23 | tools/diagnostic | S80→S81 verify adaptation (4 bugs, baseline run tenant=99 → Cat A=100%/D=100%) | REWORK QUEUE #23 | S81 |
| 10 | RWQ-24 | config | FAL_API_KEY add (без него bg removal endpoint = 503) | REWORK QUEUE #24 | Тихол manual |
| 11 | RWQ-31 | promotions | promotions.php basic Phase A2 pull-up (PromotionEngine + DB schema) | REWORK QUEUE #31 | Phase A2 |
| 12 | RWQ-47 | tools/diagnostic | S82.DIAG.FIX (Cat A=100%/D=100%) — beta blocker (status confused между source: STATE казва resolved, REWORK казва pending — verify) | REWORK QUEUE #47 | S85 |

**P0 conflict to resolve:** RWQ-47 vs STATE row "✅ Diagnostic Cat A=100% / D=100%". STATE wins per Rule #3, но REWORK QUEUE entry трябва да се закрие при следващ EOD.

### 🟡 P1 — IMPORTANT (8 items)

| # | Bug code | Module | Описание | Source |
|---|---|---|---|---|
| 13 | mirror-cron | infra | Mirror cron auto-sync hijack — 7 incidents 01.05 | PRIORITY L120 |
| 14 | RWQ-32 | scan-document.php | Phase A2 pull-up — basic Gemini Vision parse | REWORK QUEUE #32 |
| 15 | RWQ-33 | deliveries.php | Phase A2 — приемане на стока + voice + OCR | REWORK QUEUE #33 |
| 16 | RWQ-34 | suppliers.php | Phase A2 — каталог доставчици (без EIK/BRRA) | REWORK QUEUE #34 |
| 17 | RWQ-35 | transfers.php | Phase A2 — между магазини, multi-store resolver | REWORK QUEUE #35 |
| 18 | RWQ-36 | inventory.php | Phase A2 "Category of the Day" PHP логика | REWORK QUEUE #36 |
| 19 | aiinsights-uniq | ai_insights | UNIQUE key (tenant, store, topic) блокира нови INSERT | STRESS_BOARD ГРАФА 3 |
| 20 | RWQ-44 | AI templates | 4 placeholder prompt templates approve (clothes/jewelry/acc/other) | REWORK QUEUE #44 |

### 🟢 P2 — POLISH/POST-BETA (Sprint C/D detail)

**Products.php Sprint C (14 bugs):** D1, D2, D4, D6, D9, D11, D12, D13, D16, D17, D18, D19, D21, D22 — full details COMPASS L1015-1037
**Products.php Sprint D (6 bugs):** D8, D10, D14, D15, D20, D23 — COMPASS L1023-1038
**Sale.php Sprint H deferred (3):** Multi-select search, B6 continuous bg-BG, B7 numpad decimal — COMPASS L1001-1004
**STRESS Helpers:** P2 helpers.php:161 cooldown, P2 helpers.php:170 urgency limits — STRESS_BOARD ГРАФА 3

### ✅ ВЕРИФИЦИРАНИ DONE (recent — за reference)

- C2, C3, C4 (products.php Sprint B) — claim done в commit c0146c6
- B1-B7 (sale.php Sprint A) — done commits 3150cda + abda4a8
- G1 (global swipe nav removed) — done
- AIBRAIN PHASE1 (today S92) — done commits dca672b → 8f8d49c

### AGGREGATE COUNTERS (auto)

- Total open: 32+ items (12 P0 + 8 P1 + 12+ P2)
- Total P0 (blockers): 12
- Total P1: 8
- Items needing browser test verify: 5 (C1, C5, D3, D5, insights-route)
- Items needing crontab install: 1 (#8)
- Items requiring Тихол manual: 2 (#10 FAL_API_KEY, #20 prompt approve)

---

## ✅ КОЕ РАБОТИ В PRODUCTION (verified 02.05.2026)

### Frontend modules
- ✅ `chat.php` — design-kit v1.1 migration (S91.MIGRATE.CHAT, commit 1c69012)
- ✅ `chat.php` v8 GLASS Life Board (6 q-cards q1-q6, glass dashboard, weather glass, AI Studio entry magenta button)
- ✅ `chat.php` — animation timing v2.1 (2.5s spacious launch) + animation system v3 FULL PACK (7 groups)
- ✅ `chat.php` — health-bar HTML restored (was missing since S79)
- ✅ Animation system v3 rolled out (4 modules patched: life-board, stats, warehouse, sale)
- ✅ `life-board.php` — Лесен режим за Пешо (4 collapsible cards + 4 ops glass buttons + AI Studio entry + AI Brain pill ⭐ S92 02.05)
- ✅ `partials/ai-brain-pill.php` (NEW S92.AIBRAIN.PHASE1, commit 4126b2e + 2c3cb4d) — magenta pill ПОД 4-те ops buttons
- ✅ `partials/voice-overlay.php` (NEW S92.AIBRAIN.PHASE1) — общ rec-ov с REC pulse + транскрипция + Изпрати
- ✅ `ai-brain-record.php` (NEW S92.AIBRAIN.PHASE1) — backend stub passthrough към chat-send
- ✅ `ai-studio.php` — standalone main page (S84.STUDIO.REWIRE: чете реални данни)
- ✅ `products.php` — wizard 8 стъпки работи, **23 bugs pending в P0/P1 inventory**
- ✅ `warehouse.php` — hub скелет
- ✅ `stats.php` — базово работи
- ✅ `printer-setup.php` — Bluetooth pair UI + diagnostic log
- ✅ `sale.php` — design-kit v1.1 migration (S91.MIGRATE.SALE 04fa915) + S87G.SALE.UX_BATCH 7 P0/P1 bugs done (3150cda + abda4a8 hotfix B5) + S90.RACE.SALE atomicity (34041ca)
- ✅ `partials/header.php`, `partials/bottom-nav.php`, `partials/chat-input-bar.php` — production rms-shell
- ✅ `admin/beta-readiness.php` — live dashboard (S87)
- ✅ `admin/insights-health.php` — routing fix monitor (S91 c9009d2)
- ✅ `tools/testing_loop/` — continuous AI insights validation (S87)
- ✅ `tools/seed/sales_populate.py` — realistic sales seeder
- ✅ `delivery.php`, `deliveries.php`, `orders.php`, `order.php`, `defectives.php` — design-kit native (S89, commit 9862b04 +4931 lines)

### Backend
- ✅ `ai-studio-backend.php` — 9 helper функции
- ✅ `ai-studio-action.php` — HTTP endpoint type=tryon|studio|retry|refund
- ✅ `ai-image-processor.php` — bg removal само (fal.ai birefnet)
- ✅ `ai-image-credits.php` — credit helpers
- ✅ `compute-insights.php` — 19 pf*() функции, 9 активни на tenant=7
- ✅ `selection-engine.php` — MMR λ=0.75, 4 функции
- ✅ `chat-send.php`, `build-prompt.php`, `ai-safety.php` — chat infrastructure

### Bluetooth + APK
- ✅ APK инсталиран на Samsung Z Flip6
- ✅ DTM-5811 БТ принтер ПЕЧАТА — TSPL, BG cyrillic codepage 1251, 50×30mm
- ✅ Capacitor bridge РАБОТИ (window.Capacitor injects)

### Database
- ✅ MySQL 8 на DigitalOcean Frankfurt
- ✅ Credentials в `/etc/runmystore/db.env` (chmod 600, www-data)
- ✅ 47 tenants seeded
- ✅ AI Studio schema applied (3 нови таблици + 10 колони)
- ✅ Crontab www-data **monthly reset only — daily seed crontab НЕ Е installed** (виж P0 #8)
- ✅ ai_insights populated за tenant=7 (30 живи)
- ✅ ai_brain_queue table CREATE-нат на live (S92 02.05) ⭐
- ✅ S88D schema foundation: 5 нови таблици (delivery_events, supplier_defectives, price_change_log, pricing_patterns, voice_synonyms) + 39 колони + 9 indexes (commit 30b6518)

### Design System
- ✅ DESIGN-KIT v1.0 LOCKED (commit ed8834d, 16 файла) + v1.1 patches (3 modules migrated)

### Documentation
- ✅ `SIMPLE_MODE_BIBLE.md` v1.3 (1752 реда, 5 add-ons commit f5826e8)
- ✅ `DELIVERY_ORDERS_DECISIONS_FINAL.md` (560 реда, 165 решения, commit 919b80a)
- ✅ `STRESS_BOARD.md` + `STRESS_DECISIONS_FINAL.md` (commits 25741fb + ee20fc3)
- ✅ `BIBLE_v3_0_TECH.md` §14 sync с production schema

---

## 🔁 STANDING PROTOCOLS

- **TESTING_LOOP** (active since 27.04, S87): tenant=99 daily auto-seed → compute-insights → snapshot → diff. **STATUS 02.05: 🟡 STALE** — last snapshot 28.04, crontab НЕ е installed (P0 #8 в inventory). Snapshot status="healthy" но 4 дни тишина.
- **DAILY_RHYTHM** (active since 27.04, S87): 3-фазен ритъм SESSION 1 BUILD (08-12) → SESSION 2 TEST (13-17) → SESSION 3 FIX (18-21).
- **INVENTORY GATE** ⭐ (active since 02.05, v2.5): шеф-чат задължително прави Phase 0.5 inventory extraction преди status report. Без inventory output → ne plan generation. Spec: SHEF_RESTORE_PROMPT v2.5 Phase 0.5.

---

## 🚧 КОЕ В ПРОЦЕС / СКОРО

- **S92 (02.05 текущо):** AIBRAIN PHASE1 ✅ DONE; STRESS.DEPLOY pending; PRODUCTS.B_FIX pending
- **S93 (03-05.05):** Sprint C — 14 bugs (D1, D2, D4, D6, D9, D11-D13, D16-D19, D21, D22)
- **S94 (06-08.05):** Sprint D — 6 bugs polish + Inventory v4 module
- **S95 (14-15 май):** ENI launch (FIXED) — 12 дни остават

---

## ❌ КОЕ НЕ Е ЗАПОЧНАТО

- `transfers.php` — нов модул (REWORK #35, Phase A2)
- `suppliers.php` — нов модул (REWORK #34)
- `promotions.php` — нов модул (REWORK #31)
- `scan-document.php` — нов модул (REWORK #32)
- `inventory.php v4` — event-sourced rewrite (REWORK #36)
- iOS Capacitor (post-Android)
- WooCommerce / Shopify integration (Phase B)

---

## 🎯 BUSINESS STATE

- **Beta launch (ENI):** 14-15 май 2026 (FIXED) — 12 дни
- **Public launch:** септември 2026
- **Pricing v2:** FREE €0 / START €19 / PRO €59 / BIZ €109 + Volume packs €5-100
- **AI cost model:** bg=€0.05, desc=€0.02, magic=€0.30/€0.50
- **Quality Guarantee:** 2 free retries + refund

---

## 📊 PHASE PROGRESS

- **Phase A1 (Foundation):** ~75% (per PRIORITY 01.05)
- **Phase A2 (Operations Core):** 0% (стартира S87+)
- **Phase B (Beta Polish):** 0%
- **Phase 1 (Public Launch):** 0%

---

## 🛡 RULES

1. **Закон #1:** Пешо НЕ пише — voice/photo/tap only
2. **Закон #2:** PHP смята, AI говори
3. **Закон #3:** Inventory Gate (PHP=truth, AI=форма)
4. **Закон #4:** Audit Trail
5. **Закон #5:** Confidence Routing (>0.85 auto, 0.5-0.85 confirm, <0.5 block)
6. **Rule #19 PARALLEL COMMIT CHECK:** git status + git log -5 преди commit
7. **Rule #21 DIAGNOSTIC PROTOCOL:** AI logic промени → Cat A+D 100% преди commit
8. **Rule #22 COMPASS WRITE LOCK:** само шеф-чат update-ва COMPASS/BIBLE
9. **STATE write rule:** ВСЕКИ Claude Code update-ва STATE в края на сесията
10. **Rule #13 INVENTORY GATE** ⭐ v2.5: шеф-чат update-ва `📋 LIVE BUG INVENTORY` в EOD

---

## 🔄 КАК ДА UPDATE-НЕШ ТОЗИ ФАЙЛ

```bash
cd /var/www/runmystore
nano STATE_OF_THE_PROJECT.md
# Code Code: update-ваш ✅ КОЕ РАБОТИ + ⚠️ KNOWN ISSUES + 🚧 в процес
# Шеф-чат (EOD only): update-ваш 📋 LIVE BUG INVENTORY (close-нати + нови)
# Save
git add STATE_OF_THE_PROJECT.md
git commit -m "STATE: [session ID] — [какво се промени]"
git push origin main
```

**НЕ пиши целия handoff тук.** Handoff doc-овете в `docs/` са за full detail.
**НЕ update-вай COMPASS оттук.** Шеф-чат прави merge при следващ старт.

**Цел:** да можеш да отвориш този файл и да знаеш истината за проекта **за 60 секунди** + пълен P0/P1 list **за 30 секунди**.
