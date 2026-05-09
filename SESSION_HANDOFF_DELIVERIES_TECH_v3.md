# 🤝 SESSION HANDOFF — DELIVERIES v3 TECHNICAL COMPANION

**Дата:** 09.05.2026, ~21:00
**Сесия cover:** Технически companion документ към `DELIVERIES_FINAL_v3_COMPLETE.md`
**Status:** ✓ COMPLETED + COMMITTED
**Commit:** `b4b984f` на `main`
**Beta launch:** 14-15.05.2026 (5-6 дни)

---

## TL;DR

Написан и commit-нат в repo техническият companion документ — 4404 реда, 18 части, 178KB. Покрива всичко необходимо за имплементация на модул "Доставки" v3: schema drift map, миграция M01-M10, services, API, OCR, Voice, Hidden Inventory, Lost Demand, Multi-Store Split, TSPL, Notifications, Offline, AI Signals, Audit, Performance, Migration Script, Test Scenarios, Post-Beta roadmap.

**Файл:** `/var/www/runmystore/DELIVERIES_TECHNICAL_v3_COMPANION.md`
**GitHub:** `https://github.com/tiholenev-tech/runmystore/blob/main/DELIVERIES_TECHNICAL_v3_COMPANION.md`
**Spec spътник:** `DELIVERIES_FINAL_v3_COMPLETE.md` (10552 реда, в repo root)

---

## КАКВО Е СВЪРШЕНО В ТАЗИ СЕСИЯ

1. ✓ Прочетен пълен `DELIVERIES_FINAL_v3_COMPLETE.md` (10552 реда, 50 секции, 6 части)
2. ✓ Audit на live код vs v3 spec — открит **значителен drift**:
   - 8 нови tables липсват
   - 4 ALTER-и липсват (suppliers 8 cols, products 3 cols, ai_insights 5 cols, deliveries+items дрейф)
   - 7 services липсват
3. ✓ Написан `DELIVERIES_TECHNICAL_v3_COMPANION.md` — 4404 реда, 18 части
4. ✓ Качен на droplet от Тихол → commit `b4b984f` → push origin main

---

## ТЕКУЩО СЪСТОЯНИЕ НА REPO

### Документи (canonical)

| Файл | Реди | Статус |
|---|---|---|
| `DELIVERIES_FINAL_v3_COMPLETE.md` | 10552 | ✓ logical canonical |
| `DELIVERIES_TECHNICAL_v3_COMPANION.md` | 4404 | ✓ technical canonical (този handoff) |
| `docs/DELIVERIES_BETA_READINESS.md` | 323 | ✓ S98 audit (8 P0/P1 fixes) |
| `SESSION_S89_DELIVERY_ORDERS_HANDOFF.md` | 165 | ✓ S89 services delivered |

### Code (live)

| Файл | LOC | Статус |
|---|---|---|
| `services/ocr-router.php` | 494 | ✓ работи (Gemini 2.5 Flash) |
| `services/duplicate-check.php` | — | ✓ работи (4 types) |
| `services/pricing-engine.php` | — | ✓ работи (assumed callable от P1-1 audit) |
| `services/voice-tier2.php` | — | ⚠ чака `GROQ_API_KEY` |
| `delivery.php` | 1073 | ✓ работи (camera flow, review, commit) |
| `deliveries.php` | 455 | ✓ работи (hub) |

### DB (live)

s88d migration applied. **8 нови tables НЕ съществуват** (виж migration plan).

---

## КАКВО ОСТАВА ДО BETA (5-6 ДНИ)

### Phase A — DB migration (1-2 дни)

10 миграции от `DELIVERIES_TECHNICAL_v3_COMPANION.md` Част 3 (M01-M10):

| # | Migration | Тип | Приоритет |
|---|---|---|---|
| **M01** | `delivery_item_stores` CREATE | Нова | **P0** (multi-store split) |
| **M02** | `scanner_documents` CREATE | Нова | **P0** (OCR audit) |
| **M03** | `inventory_confidence` CREATE | Нова | **P0** (Закон №6) |
| **M04** | `pricing_rules` CREATE | Нова | **P0** (Smart Pricing) |
| **M05** | `notifications` CREATE | Нова | **P1** |
| **M06** | `lost_demand` SYNC | Existing live | **P0** (verify schema) |
| **M07** | `suppliers` ALTER (8 cols) | ALTER | **P0** |
| **M08** | `products` ALTER (3 cols) | ALTER | **P0** |
| **M09** | `ai_insights` ALTER (role_gate + 4 action cols) | ALTER | **P0** |
| **M10** | `deliveries`+`delivery_items` drift fixes | ALTER | **P1** |

Всички миграции са idempotent (INFORMATION_SCHEMA guards). Точен SQL в Технически companion §3.3-3.13.

### Phase B — Services (2-3 дни)

7 нови файла:

```
services/inventory-confidence.php    — Закон №6 (P0)
services/lost-demand-matcher.php     — Lost demand close + cron 06:30 (P0)
services/multi-store-split.php       — ENI 5 магазина (P0)
services/notification-dispatcher.php — FCM + in-app + email (P1)
services/tspl-generator.php          — DTM-5811 labels (P0)
services/email-poller.php            — IMAP cron 5min (P2 post-beta)
services/ocr-worker.php              — async dispatch (P2 post-beta)
```

OCRRouter refactor — добави `dispatchAsync()` + `getJob()` методи (виж §4.4).

### Phase C — S98 P0/P1 Audit Fixes (1 ден, ~117 LOC общо)

| # | Item | LOC | Приоритет |
|---|---|---|---|
| P0-1 | Voice fallback dead-end | ~10 | **P0** |
| P0-2 | Sanitize OCR error messages | ~15 | **P0** |
| P0-3 | Defective proactive prompt §E1 | ~12 | **P0** |
| P1-1 | Auto-pricing C6 routing wired | ~30 | **P1** |
| P1-2 | `has_mismatch` end-of-tx compute | ~25 | **P1** |
| P1-3 | Fuzzy product matching (Levenshtein) | ~20 | **P1** |
| P1-4 | OCR retry button | ~15 | **P1** |
| P1-5 | Loading copy correction | ~10 | **P1** |

Total P0 = 37 LOC, P0+P1 = 117 LOC.

### Phase D — Test + Deploy (1 ден)

- Smoke tests T1-T12 (виж §18.1)
- Edge cases E1-E20 (§18.2)
- Performance P1-P6 (§18.3)
- Capacitor APK rebuild (sprint A fixes)
- Bluetooth printer pairing test (DTM-5811 + Z Flip6)
- ENI staff briefing (5min + 12min videos + PDF cheat)

---

## РЕД НА ИЗПЪЛНЕНИЕ — РЕКОМЕНДАЦИЯ

```
Day 1 (10.05): M01-M05 + M07-M09 миграции (idempotent ALTER + 5 нови tables)
                Backup първо! mysqldump > /var/backups/runmystore/pre_v3_*.sql.gz
Day 2 (11.05): M06 lost_demand sync + M10 deliveries drift +
                services/inventory-confidence.php +
                services/lost-demand-matcher.php
Day 3 (12.05): services/multi-store-split.php + services/tspl-generator.php +
                S98 P0 fixes (37 LOC) + UI multi-store split
Day 4 (13.05): services/notification-dispatcher.php +
                S98 P1 fixes (~80 LOC) +
                Capacitor APK rebuild + смоук tests
Day 5 (14.05): ENI brief + soft launch (1 магазин Витоша) + наблюдение
Day 6 (15.05): Full ENI rollout (5 магазина) + WhatsApp on-call
```

---

## КРИТИЧНИ ПРАВИЛА (НЕ НАРУШАВАЙ)

1. **DB invariants** — `products.code` (НЕ sku), `products.cost_price` (НЕ buy_price), `products.retail_price` (НЕ sell_price), `inventory.quantity` (НЕ qty), `sale_items.unit_price` (НЕ price), `sales.status='canceled'` (една L). Не добавяй колони с v3 имена ако live имена работят — добави като alias (виж §2.1, §2.2).

2. **Никога `ALTER TABLE ADD COLUMN IF NOT EXISTS`** в MySQL 8 — ползвай `INFORMATION_SCHEMA.COLUMNS` PREPARE/EXECUTE (примери в §3.6-3.12).

3. **Закон №6 (Hidden Inventory)** — `inventory_confidence.confidence_score` НИКОГА visible на Митко в production UI. Само developer/feature flag.

4. **Закон №1 (Voice everywhere)** — Пешо никога не пише. Voice button на ВСЯКО поле. Whisper Groq за non-BG, Web Speech API за BG.

5. **Закон №2 (PHP смята, AI вокализира)** — Confidence/margin/profit изчисления в PHP. AI само speak.

6. **Sacred Neon Glass** — Никога не променяй glass cards (4 mandatory spans, oklch, conic-gradient).

7. **Нова таблица `cost_history` НЕ се създава** — VIEW alias върху `price_change_log` (s88d). Виж §3.13.

8. **purchase_orders, НЕ supplier_orders** — canonical name в live. v3 spec използва `supplier_orders` в connection map, но в live е `purchase_orders`.

9. **delivery_events VARCHAR(64), НЕ ENUM** — s88d избор. Whitelist enforce се в PHP layer (§15.3).

10. **BG dual pricing на labels до 08.08.2026** — TSPL automatic при `tenant.country_code='BG'` (§4.3.5).

---

## CONTEXT ЗА COLD START (АКО ПАМЕТТА Е ЗАГУБЕНА)

### Кой е Тихол
- Founder на RunMyStore.ai (BG retail SaaS)
- Owner на ENI fashion chain (5 stores) — primary beta client
- Bulgarian, не developer
- Преди тази сесия: 48+ сесии, S88-S99 на текущ sprint
- Workflow: Claude (тук, 90%) или Claude Code на droplet (10%, large rewrites)

### Текущ stack
- PHP 8.3 / MySQL 8 / DigitalOcean Frankfurt droplet `164.90.217.120`
- `/var/www/runmystore/`
- GitHub: `tiholenev-tech/runmystore` (public)
- Capacitor APK / Samsung Z Flip6 test device (~373px)
- Gemini 2.5 Flash (primary OCR, 2 keys rotating post-beta)
- Groq Whisper (voice tier 2, чака KEY)
- DTM-5811 Bluetooth printer (TSPL)
- DB credentials: `/etc/runmystore/db.env` (chmod 600, outside git)
- API keys: `/etc/runmystore/api.env`

### Tenant info
- ENI = `tenant_id=7`, реален, beta launch
- 5 stores: Витоша/Цариградско/Студентски/Овча купел/Банкя (store_ids 47-51)
- STRESS Lab = отделен tenant (`stress@runmystore.ai`) с 90д fake история, 11 suppliers, 5 sellers, 2-3K fake products

### GitHub access от sandbox
- `raw.githubusercontent.com` и `api.github.com` са BLOCKED
- Само `github.com` blob URLs работят
- Helper: `tools/gh_fetch.py` (parse `"rawLines":[...]` от blob HTML)
- ИЛИ git clone `--depth=1` в `/tmp/gh_cache/`

### Workflow rules
- `git pull origin main` ВИНАГИ преди changes
- `php -l` преди commit
- Commit format: `S[number]: [description]`
- Малки fixes → Claude (тук) → Python script → Тихол стартира на droplet
- Големи rewrites (500KB+, multi-hour) → Claude Code в tmux
- Никога `_v2`, `_FINAL`, dates в filenames

### Style preferences (Тихол)
- Винаги български
- Максимална краткост, директен код
- Никога "готов ли си" въпроси
- 60% actionable + 40% honest critique
- Никога 100% enthusiasm — посочи рискове, edge cases
- "Ти луд ли си" = signal че Claude е забравил важен контекст

### При cold start

1. Прочети `MASTER_COMPASS.md` (live държава, file ownership)
2. Прочети `SHEF_RESTORE_PROMPT.md` (16-question IQ test за orientation)
3. Прочети `DELIVERIES_FINAL_v3_COMPLETE.md` + `DELIVERIES_TECHNICAL_v3_COMPANION.md` (този handoff е спътник)
4. Запитай Тихол: "Кой е следващият task — миграциите M01-M10 или S98 P0 fixes?"

---

## ВЪПРОСИ ОЧАКВАЕМИ ОТ СЛЕДВАЩ CLAUDE

| # | Въпрос | Отговор |
|---|---|---|
| 1 | Кога стартираме миграциите? | След като другата Claude Code сесия приключи (тя пише на droplet) |
| 2 | M01-M10 в един run или отделно? | Един run — `migrations/run_v3_migrations.sh` (виж §17.1) |
| 3 | Backup първо? | ДА — `mysqldump > /var/backups/runmystore/pre_v3_*.sql.gz` |
| 4 | Какво ако M03 (inventory_confidence) fail? | Rollback: `gunzip < pre_v3_*.sql.gz \| mysql` (§17.3) |
| 5 | services/ файлове — пиша ли тук или в Claude Code? | Тук (Claude). Тихол копира на droplet. |
| 6 | UI промени за multi-store split? | След services/multi-store-split.php е готов. |
| 7 | Voice tier 2 пускам ли в beta? | НЕ — `GROQ_API_KEY` още не е configured. Чакаме post-beta. |
| 8 | Marketing AI? | Q4 2026, изисква 95% inventory accuracy за 30д. Не сега. |

---

## ФАЙЛОВИ ЛОКАЦИИ ЗА БЪРЗ REFERENCE

```
/var/www/runmystore/                                — repo root
  DELIVERIES_FINAL_v3_COMPLETE.md                   — logical canonical (10552 lines)
  DELIVERIES_TECHNICAL_v3_COMPANION.md              — technical canonical (4404 lines, NEW)
  docs/DELIVERIES_BETA_READINESS.md                 — S98 audit (8 P0/P1 fixes)
  migrations/s88d_delivery_schema.sql               — текущ s88d schema
  services/ocr-router.php                           — Gemini OCR (live)
  services/duplicate-check.php                      — duplicate logic (live)
  services/voice-tier2.php                          — Whisper (чака KEY)
  delivery.php                                      — main flow (live)
  deliveries.php                                    — hub (live)
  config/database.php                               — DB connection
  config/helpers.php                                — pf functions, lost_demand entry

/etc/runmystore/db.env                              — DB credentials
/etc/runmystore/api.env                             — API keys (Gemini, Groq, FCM TBD)
/var/backups/runmystore/                            — backups location

GitHub:
  https://github.com/tiholenev-tech/runmystore
```

---

## КОМАНДИ ЗА COLD START

```bash
# Sandbox side (Claude следваща сесия)
cd /tmp && rm -rf gh_cache && mkdir gh_cache && cd gh_cache
git clone --depth=1 https://github.com/tiholenev-tech/runmystore.git
cd runmystore
ls *.md | grep -i deliv

# Прочети двата canonical документа
cat DELIVERIES_FINAL_v3_COMPLETE.md | head -50
cat DELIVERIES_TECHNICAL_v3_COMPANION.md | head -50

# Провери последните commits
git log --oneline -10
```

---

# 🎯 КРАЙ НА HANDOFF

**Тази сесия done.** Документът е committed (b4b984f). Беше launch на v3 техническата основа.

Следващ Claude → започни от **Phase A (миграциите)** или **Phase C (S98 P0 fixes)** според това кое Тихол избере.

Ако нещо в repo-то се разминава с handoff-а → repo има предимство. Pull first, винаги.
