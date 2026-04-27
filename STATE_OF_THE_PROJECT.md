# 🎯 STATE_OF_THE_PROJECT — Live Snapshot

**Дата на последен update:** 27.04.2026  
**Версия:** v1.0  
**Update протокол:** ВСЕКИ Claude Code в края на сесията update-ва САМО този файл (не COMPASS, не handoff).  
**Шеф-чат първо чете ТОЗИ файл, после COMPASS, после последен handoff.**

---

## ✅ КОЕ РАБОТИ В PRODUCTION (verified 27.04.2026)

### Frontend modules
- ✅ `chat.php` — v8 GLASS Life Board (6 q-cards q1-q6, glass dashboard, weather glass, AI Studio entry magenta button)
- ✅ `life-board.php` — НОВ файл (580 реда), Лесен режим за Пешо (4 collapsible cards + 4 ops glass buttons + AI Studio entry)
- ✅ `ai-studio.php` — standalone main page (5 категории cards, credits bar, bulk секция, история, FAB) — S84.STUDIO.REWIRE: чете реални данни през ai-studio-backend.php helpers
- ✅ `products.php` — wizard 8 стъпки работи, ⚠ 3 P0 bugs known + Phase B/C wizard redesign open
- ✅ `warehouse.php` — hub скелет
- ✅ `stats.php` — базово работи
- ✅ `printer-setup.php` — Bluetooth pair UI + diagnostic log
- ✅ `partials/header.php`, `partials/bottom-nav.php`, `partials/chat-input-bar.php` — production rms-shell
- ✅ `admin/beta-readiness.php` — live dashboard (7 sections incl. Testing Loop Health, auto-refresh 60s, owner+tenant=7 gated, mobile-friendly) — S86 + S87
- ✅ `tools/testing_loop/` — continuous AI insights validation (daily_runner.py + snapshot_diff.py + ANOMALY_LOG.md, tenant=99 isolated lab) — S87
- ✅ `tools/seed/sales_populate.py` — realistic sales seeder (tenant=99): peak hours, weekend boost, basket/return/discount distributions; `--count` + `--backfill-days` + `--dry-run`; tenant guard {7,99}; docs in `tools/seed/SALES_SEEDER.md` — S87

### Backend
- ✅ `ai-studio-backend.php` — 9 helper функции (get_credit_balance, consume_credit, refund_credit, check_retry_eligibility, check_anti_abuse, get_prompt_template, build_prompt, count_products_needing_ai, pre_flight_quality_check)
- ✅ `ai-studio-action.php` — нов HTTP endpoint type=tryon|studio|retry|refund
- ✅ `ai-image-processor.php` — bg removal само (fal.ai birefnet)
- ✅ `ai-image-credits.php` — credit helpers
- ✅ `compute-insights.php` — 19 pf*() функции, 9 активни на tenant=7
- ✅ `selection-engine.php` — MMR λ=0.75, 4 функции
- ✅ `chat-send.php`, `build-prompt.php`, `ai-safety.php` — chat infrastructure

### Bluetooth + APK
- ✅ **APK билдва** (GitHub Actions)
- ✅ **APK инсталиран** на Samsung Z Flip6 на Тихол
- ✅ **Capacitor bridge РАБОТИ** — `window.Capacitor` се инжектира
- ✅ **DTM-5811 БТ принтер ПЕЧАТА** — TSPL команди, BG cyrillic codepage 1251, етикети 50×30mm
- ✅ `js/capacitor-printer.js` — pair/print/test/forget API
- ✅ `wizPrintLabelsMobile` hook в products.php step 6

### Database (live runmystore DB)
- ✅ MySQL 8 на DigitalOcean Frankfurt
- ✅ Credentials в `/etc/runmystore/db.env` (chmod 600, outside git)
- ✅ 47 tenants seeded (2× PRO, 45× START)
- ✅ tenant=7 = Тихол, tenant=52 = ЕНИ live, tenant=99 = test/eval
- ✅ AI Studio schema applied (3 нови таблици: `ai_credits_balance`, `ai_spend_log`, `ai_prompt_templates`)
- ✅ tenants AI Studio columns (+6: `included_*_per_month`, `*_used_this_month`)
- ✅ products AI Studio columns (+4: `ai_category`, `ai_subtype`, `ai_description`, `ai_magic_image`)
- ✅ Crontab www-data installed (monthly 1-во число reset)
- ✅ ai_insights populated за tenant=7 (30 живи · 5 на всеки fundamental_question · seed top-up `tools/seed/insights_populate.py`)

### Dev infrastructure
- ✅ GitHub repo: `tiholenev-tech/runmystore`, main branch
- ✅ Apache 2 на droplet, `/var/www/runmystore/`
- ✅ Auto-deploy: `git pull` на droplet
- ✅ Backups: `/root/*.sql`, `/root/*.bak.*`
- ✅ Diagnostic protocol: `tools/diagnostic/` (pymysql, 124 scenarios)

---

## ⚠️ KNOWN ISSUES (verified 27.04.2026)

| # | Issue | Severity | Кога се решава |
|---|---|---|---|
| 1 | Toggle "Лесен ↔ Подробен" — ✅ RESOLVED 27.04.2026 (S86.CHAT.TOGGLE: chat.php toggle verified visible, tap target 30px → 44px min, bidirectional confirmed; life-board.php side already had reverse toggle) | 🟢 RESOLVED 27.04.2026 | ✅ done |
| 3 | AI Studio modal в wizard step 5 — UNVERIFIED дали е новия дизайн (STUDIO.12 v0.7.23 беше mockup approval, не code merge) | 🟡 P1 | S83 verify |
| 4 | Diagnostic Cat A=100% / D=100% (51/51 PASS, run #18) — S85.DIAG.FIX resolved | 🟢 RESOLVED 27.04.2026 | ✅ done; 1 PHP question escalated в `tools/diagnostic/REAL_BUGS_FOUND.md` §1 (pfHighestMargin sales filter) |
| 5 | products.php 3 P0 bugs (от старите S78) — main nav buttons, Step 0 wizard call, mobile file picker | 🟡 P1 | S84 |
| 6 | products.php Phase B (Step 3 redesign) — open task | 🟢 P2 | S84+ |
| 7 | products.php Phase C (Step 4 color rows list) — open task | 🟢 P2 | S84+ |
| 8 | tenants.plan ENUM няма 'biz' | 🟢 P2 | Когато BIZ launch |
| 9 | 4 placeholder AI prompt templates (clothes/jewelry/acc/other) — `is_active=0` | 🟢 P2 | Тихол approve |
| 10 | Legacy `tenants.ai_credits_*` колони — 30 дни grace, drop ~2026-05-27 | 🟢 P2 | S95+ |

---

## 🔁 STANDING PROTOCOLS

- **TESTING_LOOP** (active since 27.04.2026, S87): tenant=99 daily auto-seed (`tools/seed/sales_populate.py`) → `compute-insights.php::computeProductInsights(99)` → snapshot → diff → see `tools/testing_loop/latest.json` for current status (🟢/🟡/🔴). Anomalies logged to `tools/testing_loop/ANOMALY_LOG.md`. Шеф-чат reads this at boot. Spec: `TESTING_LOOP_PROTOCOL.md` (root). Crontab: `0 7 * * *` за www-data (manual install от Тихол).

---

## 🚧 КОЕ В ПРОЦЕС / СКОРО

- **S83 (днес 27.04):** Real product entry tenant=7, минимум 50 артикула
- **S84 (28.04):** BUGFIX BATCH + STUDIO.REWIRE
- **S85.DIAG.FIX (27.04):** ✅ DONE — Cat A=100%/D=100% (51/51 PASS); pfHighestMargin escalated
- **S87-S91 (4-8 май):** sale.php rewrite + transfers + inventory v4 + deliveries + orders
- **S95 (14-15 май):** ENI launch (FIXED)

Виж `docs/NEXT_SESSIONS_PLAN_27042026.md` за пълен 15-сесиен план.

---

## ❌ КОЕ НЕ Е ЗАПОЧНАТО

- `transfers.php` — нов модул (S88)
- `deliveries.php` — нов модул (S90)
- `orders.php` — нов модул (S91)
- `suppliers.php` — нов модул (S93)
- `inventory.php v4` — event-sourced rewrite (S89)
- `sale.php` — voice + camera + numpad rewrite (S87)
- Stripe Connect нови packs (S94)
- Promotions Phase B (S92)
- iOS Capacitor (post-Android)
- WooCommerce / Shopify integration (Phase B)

---

## 🎯 BUSINESS STATE

- **Beta launch (ENI):** 14-15 май 2026 (FIXED)
- **Public launch:** септември 2026
- **Pricing v2:** FREE €0 / START €19 / PRO €59 / BIZ €109 + Volume packs €5-100 (виз AI_CREDITS_PRICING_v2.md)
- **AI cost model:** bg=€0.05, desc=€0.02, magic=€0.30 (nano-banana-2) или €0.50 (nano-banana-pro)
- **Quality Guarantee:** 2 free retries + refund (cost €0.14 absorbed)

---

## 📊 PHASE PROGRESS

- **Phase A1 (Foundation):** ~65%
- **Phase A2 (Operations Core):** 0% (стартира S87)
- **Phase B (Beta Polish):** 0%
- **Phase 1 (Public Launch):** 0%

---

## 🛡 RULES (всеки чат МОРЕ ДА ЗНАЕ)

1. **Закон #1:** Пешо НЕ пише — voice/photo/tap only
2. **Закон #2:** PHP смята, AI говори
3. **Закон #3:** Inventory Gate (PHP=truth, AI=форма)
4. **Закон #4:** Audit Trail
5. **Закон #5:** Confidence Routing (>0.85 auto, 0.5-0.85 confirm, <0.5 block)
6. **Rule #19 PARALLEL COMMIT CHECK:** git status + git log -5 преди всеки commit
7. **Rule #21 DIAGNOSTIC PROTOCOL:** AI logic промени → Cat A+D 100% преди commit
8. **Rule #22 COMPASS WRITE LOCK:** само шеф-чат update-ва COMPASS/BIBLE; работни чатове пишат `[COMPASS UPDATE NEEDED]` в handoff
9. **STATE_OF_THE_PROJECT.md write rule:** ВСЕКИ Claude Code update-ва този файл в края на сесията

---

## 🔄 КАК ДА UPDATE-НЕШ ТОЗИ ФАЙЛ

В края на твоята Claude Code сесия:

```bash
cd /var/www/runmystore
nano STATE_OF_THE_PROJECT.md
# Намери секция която се променила (✅ работи / ⚠️ known issues / 🚧 в процес)
# Update-ни 1-2 реда
# Save
git add STATE_OF_THE_PROJECT.md
git commit -m "STATE: [твоя session ID] — [какво се промени]"
git push origin main
```

**НЕ пиши целия handoff тук.** Handoff doc-овете в `docs/` са за full detail.  
**НЕ update-вай COMPASS оттук.** Шеф-чат прави merge при следващ старт.

**Цел:** да можеш да отвориш този файл и да знаеш истината за проекта **за 60 секунди**.
