# 📋 STATE_OF_THE_PROJECT — LIVE BUG INVENTORY REFRESH

**Aggregated дата:** 03.05.2026 EOD (replaces 02.05.2026 06:30 initial extract)  
**Update протокол:** Шеф-чат update-ва тази секция в EOD. Заменя aggregated тable.

---

## 🔴 P0 — BLOCKERS (16 items, 8 closed today, 8 active)

### ✅ CLOSED днес (8 items, removed from active)

| # | Bug code | Module | Closed by |
|---|---|---|---|
| C1 | products.php "+ Добави размер" | TRACK 2 помощник + S95 wizard restructure |
| C5 | products.php ChevronLeft compliance | TRACK 2 помощник |
| D3 | products.php Категории filter | TRACK 2 помощник |
| D5 | products.php Duplicate detection | TRACK 2 помощник |
| sale-race | sale.php:132 race condition | TRACK 2 помощник |
| insights-route | compute-insights.php:235 | S92.INSIGHTS.WRITE.FIX (9fe2c52) |
| sales-pulse | sales_pulse.py | S92.STRESS.DEPLOY |
| crontab-deploy | infra crontab www-data | S92.STRESS.DEPLOY |

### 🔴 ACTIVE P0 (8 items continued + 8 нови = 16 total)

| # | Bug code | Module | Описание | Source | Target session |
|---|---|---|---|---|---|
| 1 | RWQ-23 | tools/diagnostic | S80→S81 verify adaptation (Cat A=100%/D=100% baseline tenant=99) | REWORK QUEUE #23 | S81 |
| 2 | RWQ-24a | config | FAL_API_KEY ✅ DONE — added to /etc/runmystore/api.env | DONE 03.05 | — |
| 3 | RWQ-24b | services/ai-image-processor | fal.ai integration end-to-end NOT production-wired | NEW 03.05 | post-beta |
| 4 | RWQ-31 | promotions.php | Phase A2 pull-up (PromotionEngine + DB schema) | REWORK QUEUE #31 | post-beta (Тихол confirm) |
| 5 | RWQ-47 | tools/diagnostic | DIAG.FIX (status conflict с STATE) | REWORK QUEUE | EOD close — Rule #3 git wins |
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

**P0 conflict resolved:** RWQ-47 vs STATE "Diagnostic 100%" → STATE wins. REWORK QUEUE #47 entry → close в COMPASS update утре.

---

## 🟡 P1 — IMPORTANT (12 items, was 8 → +4 нови)

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

---

## 🟢 P2 — POLISH/POST-BETA

**Products.php Sprint C (14 bugs):** D1, D2, D4, D6, D9, D11, D12, D13, D16, D17, D18, D19, D21, D22 — COMPASS L1015-1037
**Products.php Sprint D (6 bugs):** D8, D10, D14, D15, D20, D23 — COMPASS L1023-1038
**Sale.php Sprint H deferred (3):** Multi-select search, B6 continuous bg-BG, B7 numpad decimal — COMPASS L1001-1004
**STRESS Helpers:** P2 helpers.php:161 cooldown, P2 helpers.php:170 urgency limits

---

## ✅ ВЕРИФИЦИРАНИ DONE (recent — 02-03.05.2026)

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

---

## 📊 STATUS HEADER UPDATE

**Phase:** A1 Foundation ~80% (was 75%, +5% от S95 wizard work)
**Phase A2:** 0% (untouched — products + sale + warehouse + deliveries + orders + transfers waiting)
**Beta countdown:** 11 дни (03.05 EOD → 14-15.05 launch)
**Latest session:** S95.WIZARD.RESTRUCTURE.PART1_1_A_PATCH (commit 8100c34, browser-tested ✅)
**TESTING_LOOP:** 🟢 ACTIVE (S92.STRESS.DEPLOY restored cron на www-data)
