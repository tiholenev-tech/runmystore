# 🎯 STATE_OF_THE_PROJECT — Live Snapshot

**Дата на последен update:** 03.05.2026 EOD (replaces 02.05.2026 06:30 initial extract)
**Версия:** v1.1 (+ LIVE BUG INVENTORY top section)
**Update протокол:** ВСЕКИ Claude Code в края на сесията update-ва САМО този файл (не COMPASS, не handoff). **Шеф-чат update-ва секция `📋 LIVE BUG INVENTORY` в EOD протокола.**
**Шеф-чат първо чете ТОЗИ файл, после COMPASS, после последен handoff.**

---

## 📋 LIVE BUG INVENTORY — single source of truth ⭐ NEW v2.5

> **ПРАВИЛО:** Тази секция е първото нещо което всеки шеф-чат чете в Phase 0.5. Тя aggregate-ва P0/P1/P2 от 5-те източника (PRIORITY_TODAY + COMPASS BUG TRACKER + REWORK QUEUE + STRESS_BOARD + own log) в едно място. Шеф-чат update-ва в EOD: добавя нови, marks completed.
>
> Aggregated дата: 03.05.2026 EOD (replaces 02.05.2026 06:30 initial extract)

### 🔴 P0 — BLOCKERS (16 items, 8 closed today, 8+8 active)

#### ✅ CLOSED днес (8 items, removed from active)

| Bug code | Module | Closed by |
|---|---|---|
| C1 | products.php "+ Добави размер" | TRACK 2 помощник + S95 wizard restructure |
| C5 | products.php ChevronLeft compliance | TRACK 2 помощник |
| D3 | products.php Категории filter | TRACK 2 помощник |
| D5 | products.php Duplicate detection | TRACK 2 помощник |
| sale-race | sale.php:132 race condition | TRACK 2 помощник |
| insights-route | compute-insights.php:235 | S92.INSIGHTS.WRITE.FIX (9fe2c52) |
| sales-pulse | sales_pulse.py | S92.STRESS.DEPLOY |
| crontab-deploy | infra crontab www-data | S92.STRESS.DEPLOY |

#### 🔴 ACTIVE P0 (8 carry-over + 8 нови = 16 total)

| # | Bug code | Module | Описание | Source | Target session |
|---|---|---|---|---|---|
| 1 | RWQ-23 | tools/diagnostic | S80→S81 verify adaptation (Cat A=100%/D=100% baseline tenant=99) | REWORK QUEUE #23 | S81 |
| 2 | RWQ-24a | config | FAL_API_KEY ✅ DONE — added to /etc/runmystore/api.env | DONE 03.05 | — |
| 3 | RWQ-24b | services/ai-image-processor | fal.ai integration end-to-end NOT production-wired | NEW 03.05 | post-beta |
| 4 | RWQ-31 | promotions.php | Phase A2 pull-up (PromotionEngine + DB schema) | REWORK QUEUE #31 | post-beta (Тихол confirm) |
| 5 | RWQ-47 | tools/diagnostic | DIAG.FIX (status conflict с STATE) | REWORK QUEUE | EOD close — Rule #3 STATE wins |
| 6 | RWQ-72 | products.php voice | Voice-First Wizard Navigation (Whisper + trigger words "следващ") | NEW 03.05 | S95 ЧАСТ 1.2 (04.05) |
| 7 | RWQ-73 | products.php AI | AI Studio entry inline (e1 design, 3 reda под снимка conditional) | NEW 03.05 | S95 ЧАСТ 1.3 (04.05) |
| 8 | NAME_INPUT_DEAD | sale.php или products.php | TRACK 2 finding spec needed | TRACK 2 | post-wizard finish |
| 9 | D12_REGRESSION | products.php | TRACK 2 finding spec needed | TRACK 2 | post-wizard finish |
| 10 | WHOLESALE_NO_CURRENCY | sale.php или products.php | TRACK 2 finding spec needed | TRACK 2 | post-wizard finish |
| 11 | S95-PART2 | products.php | Wizard ЧАСТ 2 — matrix preserve + zone field S94 | wizard restructure | 04.05 |
| 12 | S95-PART3 | products.php | Wizard ЧАСТ 3 — prices/composition step | wizard restructure | 04.05 |
| 13 | S95-PART4 | products.php | Wizard ЧАСТ 4 — cleanup steps 0/4/6 | wizard restructure | 04.05 |
| 14 | RWQ-77 | mockups | AI Studio новата визия — mockups upload + commit | NEW 03.05 | 04.05 преди ЧАСТ 1.3 |
| 15 | RWQ-78 | services/ai-studio-* | AI Studio production wire (try-on €0.30 + SEO €0.02) | NEW 03.05 | post-beta |
| 16 | sale-S87E | sale.php | 8 bugs Sprint E + Pesho-in-the-Middle hardening | COMPASS BUG TRACKER | post-wizard finish |

**P0 conflict resolved:** RWQ-47 vs STATE "Diagnostic 100%" → STATE wins per Rule #3. REWORK QUEUE #47 entry → closed in COMPASS update 03.05.2026 EOD.

### 🟡 P1 — IMPORTANT (12 items, was 8 → +4 нови)

| # | Bug code | Module | Описание | Source |
|---|---|---|---|---|
| 17 | mirror-cron | infra | Mirror cron auto-sync — monitor stability | PRIORITY 02.05 |
| 18 | RWQ-32 | scan-document.php | Phase A2 — Gemini Vision parse | post-beta |
| 19 | RWQ-33 | deliveries.php | Phase A2 — приемане на стока (ENI critical 4) | 06-08.05 |
| 20 | RWQ-34 | suppliers.php | Phase A2 — каталог доставчици | post-beta |
| 21 | RWQ-35 | transfers.php | Phase A2 — между магазини, multi-store (ENI critical 4) | 09-10.05 |
| 22 | RWQ-36 | inventory.php | Phase A2 "Category of the Day" (ENI critical 4) | 06-09.05 |
| 23 | aiinsights-uniq | ai_insights | UNIQUE key tenant+store+topic блокира INSERT | STRESS_BOARD |
| 24 | RWQ-44 | AI templates | 4 placeholder prompts approve (clothes/jewelry/acc/other) | post-beta |
| 25 | RWQ-71 | ai-studio.php | AI Studio rewire с нов дизайн (post-beta) | NEW 03.05 |
| 26 | RWQ-74 | js/capacitor-printer.js | Multi-printer support (D520BT) — currently breaking DTM stability | NEW 03.05 |
| 27 | RWQ-75 | printer reliability | Diagnose intermittent BLE connection | NEW 03.05 |
| 28 | RWQ-76 | UI header / nav | Printer health indicator (🟢/🟡/🔴) | NEW 03.05 |

### 🟢 P2 — POLISH/POST-BETA (Sprint C/D detail)

**Products.php Sprint C (14 bugs):** D1, D2, D4, D6, D9, D11, D12, D13, D16, D17, D18, D19, D21, D22 — full details COMPASS L1015-1037
**Products.php Sprint D (6 bugs):** D8, D10, D14, D15, D20, D23 — COMPASS L1023-1038
**Sale.php Sprint H deferred (3):** Multi-select search, B6 continuous bg-BG, B7 numpad decimal — COMPASS L1001-1004
**STRESS Helpers:** P2 helpers.php:161 cooldown, P2 helpers.php:170 urgency limits — STRESS_BOARD ГРАФА 3

### ✅ ВЕРИФИЦИРАНИ DONE (recent — 02-03.05.2026)

- C1, C2, C3, C4, C5, D3, D5, D1, D2, D9, D11 (products.php Sprint B + TRACK 2)
- B1-B7 (sale.php Sprint A) — 3150cda + abda4a8
- G1 (global swipe nav removed)
- AIBRAIN PHASE1 — dca672b → 8f8d49c
- S92.STRESS.DEPLOY — sales-pulse + sale-race + crontab + insights-route
- S92.INSIGHTS.WRITE.FIX — 9fe2c52 (pfUpsert dashboard 50% visible)
- S92.SPEC PRODUCTS_WIZARD_v4_SPEC.md — df49758
- S92.AIBRAIN.PHASE1 — 8f8d49c (magenta pill + voice-overlay)
- GROQ_API_KEY configured (root:www-data 640)
- FAL_API_KEY configured (RWQ-24a)
- S95.WIZARD.RESTRUCTURE PART1 + PART1_1 + PART1_1_A — cad029e + 0ccdb52 + 8100c34
- Marketing Bible v1.0 push (commit 54c4e79 от earlier)

### AGGREGATE COUNTERS (auto)

- Total open: 28 items (16 P0 + 12 P1 + P2 list)
- Total P0 (blockers): 16 (8 active carry-over + 8 нови, 8 closed today)
- Total P1: 12 (8 carry-over + 4 нови)
- Items needing Тихол manual: 1 (RWQ-77 mockups upload)
- Items requiring TRACK 2 spec: 3 (NAME_INPUT_DEAD, D12_REGRESSION, WHOLESALE_NO_CURRENCY)

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

- **TESTING_LOOP** (active since 27.04, S87): tenant=99 daily auto-seed → compute-insights → snapshot → diff. **STATUS 03.05: 🟢 ACTIVE** — S92.STRESS.DEPLOY restored cron на www-data.
- **DAILY_RHYTHM** (active since 27.04, S87): 3-фазен ритъм SESSION 1 BUILD (08-12) → SESSION 2 TEST (13-17) → SESSION 3 FIX (18-21).
- **INVENTORY GATE** ⭐ (active since 02.05, v2.5): шеф-чат задължително прави Phase 0.5 inventory extraction преди status report. Без inventory output → ne plan generation. Spec: SHEF_RESTORE_PROMPT v2.5 Phase 0.5.

---

## 🚧 КОЕ В ПРОЦЕС / СКОРО

- **S95 (03-04.05 текущо):** WIZARD.RESTRUCTURE PART1 + PART1_1 + PART1_1_A ✅ DONE; PART1_2 voice-first + PART1_3 AI Studio + PART2-4 PENDING (04.05)
- **S96 (04-05.05):** sale.php S87E + Pesho-in-the-Middle hardening (RWQ-64) — pending
- **S97-S100 (05-10.05):** ENI critical 4 модула — warehouse + deliveries + orders + transfers
- **S103 (14-15.05):** **ENI BETA LAUNCH** (LOCKED) — 10 дни остават от 04.05

---

## ❌ КОЕ НЕ Е ЗАПОЧНАТО

- `transfers.php` — нов модул (REWORK #35, ENI critical 4)
- `suppliers.php` — нов модул (REWORK #34)
- `promotions.php` — нов модул (REWORK #31, post-beta per ROADMAP REVISION 2)
- `scan-document.php` — нов модул (REWORK #32, post-beta)
- `inventory.php v4` — event-sourced rewrite (REWORK #36)
- iOS Capacitor (post-Android)
- Marketing AI (Phase 0 prep юни-юли 2026 per ROADMAP REVISION 2)
- Online Store (Ecwid integration, post-Marketing AI Phase 1)

---

## 🎯 BUSINESS STATE

- **Beta launch (ENI):** 14-15 май 2026 (FIXED) — **10 дни** от 04.05
- **Public launch:** септември 2026
- **Pricing v2:** Lite €99-149 / Standard €149-249 / Pro €249-399 / Enterprise €499-799 (per Marketing Bible v1.0)
- **AI cost target:** €0.24/tenant/month gross
- **Quality Guarantee:** 2 free retries + refund

---

## 📊 PHASE PROGRESS

- **Phase A1 (Foundation):** ~80% (was 75%, +5% от S95 wizard work)
- **Phase A2 (ENI Critical 4):** 0% (untouched — products + sale + warehouse + deliveries + orders + transfers waiting)
- **Phase B (Beta Polish):** 0%
- **Phase 1 (Public Launch):** 0%
- **Beta countdown:** 10 дни (04.05 → 14-15.05)
- **Latest session:** S95.WIZARD.RESTRUCTURE.PART1_1_A_PATCH (commit 8100c34, browser-tested ✅)
- **TESTING_LOOP:** 🟢 ACTIVE (S92.STRESS.DEPLOY restored cron на www-data)

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
11. **Rule #26 Marketing AI Activation Gate** (NEW 03.05) — inventory accuracy ≥95% за 30 дни + sale.php hardened + promotions live + tenant opt-in + spend cap
12. **Rule #27 Hard Spend Caps** (NEW 03.05) — non-negotiable per-tenant monthly cap, auto-pause at 100%
13. **Rule #28 Confidence Routing extended за Marketing** (NEW 03.05) — >0.85 auto, 0.5-0.85 tenant confirm, <0.5 escalate Тихол

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
