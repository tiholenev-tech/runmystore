# 🧪 tools/stress/ — STRESS система

**Версия:** 1.0 (S128)
**Дата:** 2026-05-08
**Цел:** Полу-автоматична стрес тест система за runmystore.ai. 5 етапа +
6 known бъга. Виж `STRESS_BUILD_PLAN.md` за пълен контекст.

---

## 🚨 ABSOLUTE GUARDS (никога не нарушавай!)

1. **ENI tenant_id=7 е свещен** — `tools/stress/_db.assert_stress_tenant()` refuse-ва ENI.
2. **Email guard** — refuse ако `email == tiholenev@gmail.com`.
3. **Default = `--dry-run`** — нищо не се записва без явен `--apply` флаг.
4. **DB credentials** — само от `/etc/runmystore/db.env` (chmod 640, www-data only).
5. **Backup задължителен** преди `reset_stress_tenant.py --apply`.
6. **Random seed = 42** — deterministic за reproducibility.

---

## 📁 ФАЙЛОВА СТРУКТУРА

```
tools/stress/
├── _db.py                          ← споделен DB helper + GUARDS (всичко зависи от него)
├── setup_stress_tenant.py          ← Етап 1.1 — създава STRESS Lab tenant
├── seed_stores.py                  ← Етап 1.2 — 8 локации
├── seed_suppliers.py               ← Етап 1.3 — 11 доставчика
├── seed_users.py                   ← Етап 1.4 — 5 продавачи
├── seed_products_realistic.py      ← Етап 1.5 — 3K артикула
├── seed_history_90days.py          ← Етап 1.6 — 90 дни история
├── reset_stress_tenant.py          ← Re-seed утилита (с backup + guards)
├── scenarios/                      ← S001-S012 JSON файлове
│   ├── S001_full_sale_flow.json
│   ├── S002_race_condition.json
│   ... (12 файла)
├── cron/
│   ├── nightly_robot.py            ← Етап 3 — 02:00 cron
│   ├── morning_summary.py          ← 06:00 — raw статистики
│   ├── code_analyzer.sh            ← 06:30 — пише MORNING_REPORT.md
│   ├── morning_report_writer.py    ← използва се от code_analyzer.sh
│   ├── test_new_features.py        ← 03:00 — тества нови commits
│   ├── sanity_checker.py           ← 07:00 — Етап 4 balance validator
│   ├── balance_validator.py        ← drill-down инструмент (ad-hoc)
│   └── crontab.example             ← препоръчителни cron редове (НЕ install-вам сам)
├── data/
│   └── dry_run_logs/               ← всеки --dry-run пише JSON snapshot тук
├── sql/                            ← reserved за бъдещи migrations
└── README.md                       ← този файл
```

---

## 🚀 EXECUTION ORDER (Етап 1 — еднократно)

⚠️ **ВСИЧКИ КОМАНДИ КАТО `www-data`** (db.env е достъпен само за www-data).

```bash
# 0. Backup на DB ПРЕДИ всичко
sudo mysqldump -u root runmystore > /root/backup_pre_stress_$(date +%Y%m%d).sql

cd /var/www/runmystore

# 1. Създай STRESS Lab tenant
sudo -u www-data python3 tools/stress/setup_stress_tenant.py             # dry-run
sudo -u www-data python3 tools/stress/setup_stress_tenant.py --apply     # реално
# ВНИМАНИЕ: записва генерирана парола → копирай я в /etc/runmystore/stress.env

# 2. 8 локации
sudo -u www-data python3 tools/stress/seed_stores.py                     # dry-run
sudo -u www-data python3 tools/stress/seed_stores.py --apply

# 3. 11 доставчика
sudo -u www-data python3 tools/stress/seed_suppliers.py
sudo -u www-data python3 tools/stress/seed_suppliers.py --apply

# 4. 5 продавачи
sudo -u www-data python3 tools/stress/seed_users.py
sudo -u www-data python3 tools/stress/seed_users.py --apply
# Запиши паролите в /etc/runmystore/stress_users.env (chmod 600)

# 5. 3K артикула с realistic distribution
sudo -u www-data python3 tools/stress/seed_products_realistic.py         # dry-run (внимавай — много данни)
sudo -u www-data python3 tools/stress/seed_products_realistic.py --apply
# Очаквана продължителност: 5-10 минути

# 6. 90 дни история
sudo -u www-data python3 tools/stress/seed_history_90days.py             # dry-run
sudo -u www-data python3 tools/stress/seed_history_90days.py --apply
# Очаквана продължителност: 30-60 минути (зависи от volume)
# За smoke test: --max-sales 1000 за бърз пробег
```

---

## ⏰ CRON SETUP (Етап 3 — нощни)

**НЕ инсталирам автоматично.** Тихол install-ва ръчно:

```bash
# 1. Създай env file (chmod 600, owner=www-data)
sudo tee /etc/runmystore/cron.env <<EOF
CRON_HEALTH_TOKEN=<run: openssl rand -hex 32>
CRON_HEALTH_URL=https://runmystore.ai/admin/health.php
TELEGRAM_BOT_TOKEN=<optional>
TELEGRAM_CHAT_ID=<optional>
PYTHONPATH=/var/www/runmystore/tools/stress
EOF
sudo chmod 600 /etc/runmystore/cron.env
sudo chown www-data:www-data /etc/runmystore/cron.env

# 2. Създай log dir
sudo mkdir -p /var/log/runmystore
sudo chown www-data:www-data /var/log/runmystore

# 3. Копирай съдържанието от tools/stress/cron/crontab.example в crontab:
sudo crontab -u www-data -e
# (paste редовете от crontab.example)

# 4. Провери:
sudo crontab -u www-data -l

# 5. Open https://runmystore.ai/admin/health.php — провери heartbeats
```

---

## 🔄 DRY-RUN ВСИЧКО (без mutации)

```bash
sudo -u www-data python3 tools/stress/setup_stress_tenant.py
sudo -u www-data python3 tools/stress/seed_stores.py
sudo -u www-data python3 tools/stress/seed_suppliers.py
sudo -u www-data python3 tools/stress/seed_users.py
sudo -u www-data python3 tools/stress/seed_products_realistic.py
sudo -u www-data python3 tools/stress/seed_history_90days.py --max-sales 100

# Cron-ове (read-only ако без --apply)
sudo -u www-data python3 tools/stress/cron/nightly_robot.py
sudo -u www-data python3 tools/stress/cron/morning_summary.py
sudo -u www-data python3 tools/stress/cron/sanity_checker.py
```

Всеки скрипт записва JSON snapshot в `tools/stress/data/dry_run_logs/`.

---

## 🧹 RESET ПРОЦЕДУРА

```bash
# Безопасен reset (изисква explicit confirmation):
sudo -u www-data python3 tools/stress/reset_stress_tenant.py --tenant <id>                         # dry-run
sudo -u www-data python3 tools/stress/reset_stress_tenant.py --tenant <id> --yes-i-am-sure --apply
```

`reset_stress_tenant.py` прави:
1. mysqldump на цялата DB → `/tmp/runmystore_backups/`
2. `assert_stress_tenant()` refuse-ва ENI
3. DELETE WHERE tenant_id = ... (само за STRESS Lab данни)
4. По default НЕ изтрива tenants реда (иска `--include-tenant-row`)

---

## 🐛 KNOWN BUGS — PATCHES

В `/tmp/bugfix_*.diff` (НЕ apply-нати в production):

1. `bugfix_sale_race.diff` — sale.php FOR UPDATE (вече приложен — patch e archival)
2. `bugfix_compute_insights_module.diff` — module='home' (вече приложен — patch e archival)
3. `bugfix_ai_insights_unique.diff` — UNIQUE relax + bucket колона (нов)
4. `bugfix_should_show_insight_test_flag.diff` — test_mode за STRESS Lab
5. `bugfix_urgency_limits.diff` — конфигурируеми лимити в tenant_settings
6. `bugfix_sales_pulse_history.diff` — sales_pulse.py off ENI tenant_id

**ПРЕДИ APPLY:** прочети diff-а, провери че интеграцията не противоречи на
текущото състояние на кода (някои са вече частично приложени).

---

## 🛡 ЗАЩО ВСИЧКО Е DRY-RUN ПО DEFAULT

Стрес системата манипулира `inventory.quantity`, `sales`, и др. — ако случайно
target tenant е ENI, реалната ENI инвентаризация се счупва. Затова:

- `--dry-run` е default
- `--apply` изисква explicit команда
- `assert_stress_tenant()` refuse-ва ENI винаги
- mysqldump backup задължителен преди reset

---

## 📊 SUCCESS CRITERIA (от STRESS_TENANT_SEED.md)

STRESS Lab е „готов" когато:
- [ ] 8 локации съществуват
- [ ] 11 доставчика регистрирани
- [ ] 5 продавачи с performance distribution
- [ ] 2-3K артикула с вариации
- [ ] 90 дни история
- [ ] AI insights генерират 50-100 pills
- [ ] Lost demand има 50-100 записа
- [ ] S002 race condition тест минава
- [ ] Никой реален клиент не вижда STRESS Lab

---

## 📞 TROUBLESHOOTING

**`PermissionError: /etc/runmystore/db.env` →** изпълнявай като www-data:
```bash
sudo -u www-data python3 ...
```

**`STRESS Lab tenant не съществува` →** изпълни setup първо:
```bash
sudo -u www-data python3 tools/stress/setup_stress_tenant.py --apply
```

**`tenant_id=7 е ENI Тихолов. Прекъсване` →** правилно. НИКОГА не пускай
върху ENI. Провери че подаваш правилен `--tenant <id>`.

**Cron не работи →** провери `/var/log/runmystore/<cron_name>.log` и
`admin/health.php` за heartbeats.

---

## 🔗 РЕФЕРЕНЦИИ

- `STRESS_COMPASS.md` — три железни закона + 5 чата
- `STRESS_BOARD.md` — централна дъска (всеки чат чете при startup)
- `STRESS_BUILD_PLAN.md` — пет етапа + 6 known бъга
- `STRESS_TENANT_SEED.md` — детайли на STRESS Lab tenant (8/11/5/3K)
- `STRESS_SCENARIOS.md` — S001-S012 регистър
- `STRESS_SCENARIOS_LOG.md` — история на пробегите
- `MORNING_REPORT_TEMPLATE.md` — какво пише code_analyzer.sh
- `END_OF_DAY_PROTOCOL.md` — EOD стъпки

---

**КРАЙ НА tools/stress/README.md**
