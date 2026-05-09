# 📋 STRESS_HANDOFF_20260509.md — S130 SESSION HANDOFF

**Дата:** 2026-05-09
**Сесия:** S130 — STRESS система пълно production-ready превъplащане
**Branch:** `s128-stress-full` (отделен от main)
**Time budget:** 6h hard limit
**Статус:** ✅ всички 5 фази (G/H/I/J/K) готови code-side; реален DB apply очаква Тихол

---

## 🎯 КАКВО СЕ НАПРАВИ В S130

### ✅ PHASE G — SANDBOX BOOTSTRAP & APPLY CYCLE
| # | Файл | Бележка |
|---|---|---|
| G1 | `tools/stress/sandbox_db_setup.sh` | mysqldump main → sandbox + tenant cleanup, refuse-ва ако target = production |
| G2 | (apply cycle scripts) | Не са изпълнени — изискват `sudo -u www-data` + db.env. Документирани в SANDBOX_GUIDE.md |
| G3 | reset cycle test | Reset cycle документиран; idempotency check описан в guide |
| G4 | `tools/stress/SANDBOX_GUIDE.md` | Пълен bootstrap → seed → reset → nightly_robot workflow + troubleshooting |

### ✅ PHASE H — BUGFIX APPLY + REGRESSION TESTS
| # | Файл | Бележка |
|---|---|---|
| H1 | `tools/stress/sandbox_files/patches/0[1-6]_*.diff` | Versioned копия на /tmp/bugfix_*.diff |
| H1 | `tools/stress/sql/s130_03_ai_insights_unique_relax.{up,down}.sql` | Bugfix 3 — bucket колона + UNIQUE relax |
| H1 | `tools/stress/sql/s130_05_urgency_limits.{up,down}.sql` | Bugfix 5 — tenant_settings + STRESS Lab override |
| H2 | `tools/stress/regression_tests/test_0[1-6]_*.py` | По 1 тест per fix, smoke + DB invariants |
| H2 | `tools/stress/regression_tests/runner.py` | Извиква всички 6 + JSON output в `data/sandbox_runs/` |
| H3 | (не пуснат — изисква sandbox DB) | Изпълнение очаква Тихол |
| H4 | `tools/stress/BUGFIX_VERIFICATION_REPORT.md` | Status table per fix + production rollout препоръки |

### ✅ PHASE I — SCENARIOS EXPANSION (12 → 60)
| # | Файл/група | Бележка |
|---|---|---|
| I1 | `tools/stress/scenarios/S013-S060.json` (48 нови) | 16 sale + 10 AI + 10 inv + 10 voice + 8 cron + 6 OCR общо 60 |
| I2 | `STRESS_SCENARIOS.md` | Нов root-level регистър с пълна таблица + breakdown по категории |
| I3 | JSON parse валидация | 60/60 файла OK (`python3 -m json.tool` per файл) |
| I4 | scenarios full run на sandbox | Очаква DB; nightly_robot.py авто-зарежда всички 60 при следващ run |

### ✅ PHASE J — NIGHTLY ROBOT REAL IMPLEMENTATION
| # | Файл | Бележка |
|---|---|---|
| J1 | `tools/stress/cron/action_simulators.py` | NEW — 13 real simulators (sales/voice/lost_demand/deliveries/transfers/refunds…) с DB-level TRANSACTIONS, table-existence guards, samples logging |
| J1 | `tools/stress/cron/nightly_robot.py` | Updated — заменя TODO stub в L269 с реален invocation на SIMULATORS dict |
| J2 | `tools/stress/cron/morning_summary.py` | (не променян — вече четеше реално от stress_runs DB ред в S128) |
| J3 | `tools/stress/cron/code_analyzer.sh` + `morning_report_writer.py` | (не променяни — вече реален MORNING_REPORT генератор в S128) |
| J4 | `tools/stress/cron/balance_validator.py` | Updated — нова `aggregate_balance()` за X-Y+Z math (in/out/adjust); запазен existing per-movement check; нов `--mode movements/aggregate/both` flag |
| J5 | `tools/stress/cron/installable/stress-{nightly,newfeat,morning,sanity}` | 4 /etc/cron.d/-style файла, generated НЕ install-нати |

### ✅ PHASE K — DOCS + COMMITS
| # | Файл | Бележка |
|---|---|---|
| K1 | `STRESS_HANDOFF_20260509.md` (този файл) | Single source of truth за S130 work |
| K2 | `STRESS_BOARD.md` | Добавена ГРАФА 7 — Етапи изграждане status table |
| K3 | `tools/stress/README.md` | Добавена секция S130 EXPANSION + pre-flight checklist |
| K4 | Commits | per phase per scope (S130.STRESS.G1/G4/H1/H2/H4/I1/I2/J1/J4/J5/K1/K2/K3) |
| K5 | Push | `git push -u origin s128-stress-full` (НЕ merge към main) |

---

## 🚨 КРИТИЧНИ BLOCKERS / WARNINGS

### 1. Sandbox DB не е създаден от мене

`tihol` user няма R на `/etc/runmystore/db.env`. Бъг тестове (regression_tests, nightly_robot --apply, реална seed cycle) изискват **www-data** изпълнение.

**Действие за Тихол:**
```bash
cd /var/www/runmystore
git fetch origin && git checkout s128-stress-full
sudo -u www-data bash tools/stress/sandbox_db_setup.sh
sudo -u www-data env DB_NAME=runmystore_stress_sandbox python3 \
    tools/stress/setup_stress_tenant.py --apply
# ... виж SANDBOX_GUIDE.md за пълен ред
```

### 2. `_db.py` НЕ respect-ва `DB_NAME` env override (DEFAULT)

Текущият `load_db_config()` чете само от `/etc/runmystore/db.env`. Sandbox изисква override.
Trivial 5-line patch в _db.py (или нов sandbox_db.env file). НЕ съм направил patch
автоматично за да не пипам shared скрипт извън scope.

**Препоръчван patch (sandbox_files/patches/db_env_override.diff):**
```python
# В load_db_config(), след `cfg.setdefault("DB_HOST", ...)`:
override_db = os.getenv("DB_NAME")
if override_db:
    cfg["DB_NAME"] = override_db
```

### 3. Cron-овете НЕ Е инсталирано в /etc/cron.d/

Per K1 instructions — generated files живеят в `tools/stress/cron/installable/`,
не в `/etc/cron.d/`. Тихол прави ръчно:
```bash
sudo cp tools/stress/cron/installable/stress-* /etc/cron.d/
sudo chown root:root /etc/cron.d/stress-*
sudo chmod 644 /etc/cron.d/stress-*
sudo systemctl restart cron
```

### 4. Bugfix patches 1+2 са ARCHIVAL

`/tmp/bugfix_sale_race.diff` + `/tmp/bugfix_compute_insights_module.diff` describe
state already реализирано в production (S97.HARDEN.PH1 + S91 fix). Регресионните
тестове 01 + 02 проверяват **invariants** (negatives, module distribution) вместо да
ре-apply-ват кода.

### 5. Production touch = 0

Нула промени в:
- sale.php / products.php / chat.php / life-board.php / ai-studio.php /
  deliveries.php / orders.php / mockups/
- ENI tenant_id=7 (двойно guarded в _db.py + simulators)
- main branch (всичко е на s128-stress-full)
- /etc/cron.d/ (cron files само generated, не install-нати)

---

## 📊 КАКВО ОСТАВА ЗА ИМПЛЕМЕНТАЦИЯ (TODO за Тихол / следваща сесия)

### High priority — преди първи production cron run
1. **Sandbox DB creation + seed cycle** — изпълни `SANDBOX_GUIDE.md` стъпка по стъпка.
2. **DB_NAME env override patch** в `_db.py` (5 реда) — за seed scripts да насочват към sandbox.
3. **Apply migrations** s130_03 + s130_05 в sandbox → re-run regression tests → verify 6/6 pass.
4. **First nightly_robot --apply run** на sandbox → check stress_runs + stress_scenarios_log.
5. **Inspect MORNING_REPORT.md** generated by code_analyzer.sh.

### Medium priority — преди beta launch
6. **Bugfix 4 (test_mode flag)** apply в production helpers.php + compute-insights.php.
7. **Bugfix 6 (sales_pulse → nightly_robot migration)** — replace cron-insights `sales_pulse.py` line.
8. **Telegram alert testing** — set TELEGRAM_BOT_TOKEN + CHAT_ID; trigger fake P0.
9. **Concurrent runner real implementation** — S002, S022, S032 — изискват pcntl_fork wrapper.

### Low priority — Phase 5 / септември 2026
10. Ecwid симулатор (Етап 5 от STRESS_BUILD_PLAN).
11. Playwright runner за voice/UI scenarios.
12. OCR pipeline + image fixtures за S055-S060.

---

## 🔬 ВЕРИФИКАЦИЯ — DRY-RUN END-TO-END (S130)

```bash
# 1. JSON parse 60 сценария
for f in tools/stress/scenarios/*.json; do
    python3 -m json.tool "$f" >/dev/null
done
# Изход: всички 60 OK

# 2. Python syntax 6 регресии + 1 runner + action_simulators
for f in tools/stress/regression_tests/*.py tools/stress/cron/action_simulators.py; do
    python3 -c "import ast; ast.parse(open('$f').read())"
done
# Изход: All OK

# 3. Bash syntax cron + setup
bash -n tools/stress/sandbox_db_setup.sh
bash -n tools/stress/cron/code_analyzer.sh
# Изход: 0

# 4. nightly_robot dry-run (изисква DB env, не пуснат)
sudo -u www-data env DB_NAME=runmystore_stress_sandbox \
    python3 tools/stress/cron/nightly_robot.py
# Очаквано: dry-run JSON snapshot + лог в data/dry_run_logs/
```

---

## 📞 ВЪПРОСИ / ESCALATIONS

- **OQ-S130-1:** _db.py DB_NAME override — apply сега или separate PR? (препоръка: now, trivial)
- **OQ-S130-2:** Cron install timing — преди или след първи green nightly_robot run? (препоръка: след)
- **OQ-S130-3:** Bugfix 6 production rollout — заменя ли cron-insights изцяло или паралелно paid 2 поредни нощи?

---

## 📦 ARTIFACTS / FILES TO REVIEW

```
tools/stress/
├── action_simulators.py                 ← NEW — 13 real simulators
├── sandbox_db_setup.sh                  ← NEW — bootstrap
├── SANDBOX_GUIDE.md                     ← NEW — workflow
├── BUGFIX_VERIFICATION_REPORT.md        ← NEW — fix status table
├── sandbox_files/patches/               ← NEW — 6 versioned diffs
├── sql/s130_*.sql                       ← NEW — 4 migration файла (up/down)
├── regression_tests/                    ← NEW — 6 tests + runner
├── scenarios/S013-S060.json             ← NEW — 48 файла
├── cron/installable/stress-*            ← NEW — 4 cron файла
├── cron/balance_validator.py            ← UPDATED — X-Y+Z mode
└── cron/nightly_robot.py                ← UPDATED — wired to action_simulators

STRESS_SCENARIOS.md                      ← NEW — 60 регистър
STRESS_HANDOFF_20260509.md               ← NEW — този файл
STRESS_BOARD.md                          ← UPDATED — Графа 7 status
```

---

**КРАЙ НА STRESS_HANDOFF_20260509.md**
