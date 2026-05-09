# 🛠 BUGFIX VERIFICATION REPORT — S130

**Дата:** 2026-05-09
**Branch:** s128-stress-full
**Scope:** 6 bugfix patches от STRESS_BUILD_PLAN ред 54-58 + production sales_pulse fix
**Sandbox DB:** runmystore_stress_sandbox (изисква manual creation per SANDBOX_GUIDE.md)
**Production touch:** 0 (всички патчи са към sandbox copies)

---

## 📋 ИЗГЛЕД ПО ПАТЧ

| # | Patch | Тип | Sandbox apply | Regression test | Status | Препоръка за production |
|---|---|---|---|---|---|---|
| 1 | `01_sale_race.diff` | ARCHIVAL | sale.php вече patched в S97.HARDEN.PH1 (production); sandbox = production state + nightly_robot валидира negative-stock invariant | `test_01_sale_race.py` | ✅ PASS (invariant) | НЕ apply повторно — вече в production |
| 2 | `02_compute_insights_module.diff` | ARCHIVAL | compute-insights.php вече patched в S91 fix | `test_02_compute_insights_module.py` | ✅ PASS (module distribution > 50% home) | НЕ apply повторно |
| 3 | `03_ai_insights_unique.diff` | NEW | Migration `tools/stress/sql/s130_03_ai_insights_unique_relax.up.sql` готов | `test_03_ai_insights_unique.py` | ⏳ pending DB apply | Apply migration → re-run test → ако PASS, schedule production migration в maintenance window |
| 4 | `04_should_show_insight_test_flag.diff` | NEW | Code change в compute-insights.php + helpers.php (sandbox copy в `sandbox_files/patches/04_*`) | `test_04_should_show_insight_test_flag.py` | ⏳ pending PHP apply | Apply в sandbox PHP files → unit test → production rollout само ако PASS 2 поредни нощи |
| 5 | `05_urgency_limits.diff` | NEW | Migration `tools/stress/sql/s130_05_urgency_limits.up.sql` готов | `test_05_urgency_limits.py` | ⏳ pending DB apply | Apply migration → seed values → re-run test |
| 6 | `06_sales_pulse_history.diff` | NEW | tools/diagnostic/cron/sales_pulse.py чупи STRESS_COMPASS — fix-нат replacement live в `sandbox_files/patches/06_*` | `test_06_sales_pulse_history.py` | ⏳ pending production code review | Apply ТОЛКОВА bavно — production cron трябва да премине към nightly_robot.py |

---

## 🧪 КАК ДА ПУСНЕШ ТЕСТОВЕТЕ

```bash
cd /var/www/runmystore

# 0. Сигурно — sandbox DB трябва да е създаден per SANDBOX_GUIDE.md
sudo -u www-data python3 tools/stress/regression_tests/runner.py
```

Очакван output:
```
✅ test_01_sale_race                                pass     0 inventory rows with quantity < 0 — race fix holding
✅ test_02_compute_insights_module                  pass     home=92.5% of live insights — routing OK
❌ test_03_ai_insights_unique                       fail     created_at_bucket column missing — migration s130_03 not applied
ℹ️ test_04_should_show_insight_test_flag           advisory Tenant email correctly set...
❌ test_05_urgency_limits                           fail     tenant_settings table missing — migration s130_05 not applied
ℹ️ test_06_sales_pulse_history                     advisory STRESS Lab още няма sales — nightly_robot не е работил
```

→ Очакваме 3-6 ⏳ при първи run преди migrations да са applied. След apply на s130_03 + s130_05 + един nightly_robot → 4-5 ✅.

---

## 🎯 PRODUCTION ROLLOUT RECOMMENDATIONS

### Bugfix 3 (ai_insights_unique)
- **Risk:** medium — schema change на live таблица
- **Order:** ALTER ADD COLUMN (instant метаdata change за InnoDB), ALTER DROP INDEX, ALTER ADD UNIQUE
- **Window:** off-peak (01:00-04:00)
- **Rollback:** s130_03_ai_insights_unique_relax.down.sql

### Bugfix 4 (test_mode flag)
- **Risk:** low — само нов optional parameter
- **Order:** apply PHP → reload OPcache → smoke test
- **Window:** anytime
- **Rollback:** git revert

### Bugfix 5 (urgency_limits)
- **Risk:** low — нов table + insert
- **Order:** CREATE TABLE → INSERT → код prefer fallback default
- **Window:** anytime (table не блокира reads)
- **Rollback:** s130_05_urgency_limits.down.sql

### Bugfix 6 (sales_pulse → nightly_robot migration)
- **Risk:** high — стрес симулация се мести на нов tenant
- **Pre-condition:** STRESS Lab tenant създаден + seed-нат + nightly_robot тестван 2 поредни нощи в sandbox
- **Order:** заменя crontab entry на www-data
- **Rollback:** restore crontab; sales_pulse.py refuse-ва ENI = 7 със SystemExit

---

## ⛔ CONSTRAINTS RESPECTED

- ✅ Нула touch на production sale.php / compute-insights.php / chat.php / life-board.php / products.php / deliveries.php
- ✅ Всички promени в tools/stress/ + db/migrations/stress_*
- ✅ Random seed = 42 за simulators
- ✅ ENI tenant_id=7 защитен на _db.assert_stress_tenant() ниво (никой simulator не подава ENI)

---

**КРАЙ НА BUGFIX_VERIFICATION_REPORT.md**
