# 🏖 SANDBOX_GUIDE — STRESS DB Sandbox Workflow

**Версия:** 1.0 (S130)
**Дата:** 2026-05-09
**Цел:** Изолирано MySQL копие на production за стрес тестове БЕЗ да пипа prod.

---

## 🚨 ABSOLUTE GUARDS

1. Sandbox DB име = `runmystore_stress_sandbox`. Скриптовете refuse-ват ако target == `runmystore`.
2. Никой sandbox скрипт няма write достъп до production. _db.py чете credentials от `/etc/runmystore/db.env` — sandbox runs override-ват `DB_NAME` env.
3. ENI tenant_id=7 не съществува в sandbox (cleanup го изтрива). assert_stress_tenant() refuse-ва ENI на двойно ниво.
4. Всеки seed/run лог отива в `tools/stress/data/sandbox_runs/`.

---

## 🛠 BOOTSTRAP (еднократно)

```bash
# 0. Backup production преди всичко (винаги!)
sudo mysqldump -u root runmystore > /root/backup_pre_sandbox_$(date +%Y%m%d).sql

# 1. Създай sandbox DB и копирай схемата + данните
sudo -u www-data bash tools/stress/sandbox_db_setup.sh
# Време: 5-15 мин в зависимост от prod size.
# Лог: tools/stress/data/sandbox_runs/setup_<TS>.log

# 2. Apply S130 sandbox migrations (bugfix 3 + 5)
sudo -u www-data env DB_NAME=runmystore_stress_sandbox \
    mysql -u "$DB_USER" -p"$DB_PASS" runmystore_stress_sandbox \
    < tools/stress/sql/s130_03_ai_insights_unique_relax.up.sql

sudo -u www-data env DB_NAME=runmystore_stress_sandbox \
    mysql -u "$DB_USER" -p"$DB_PASS" runmystore_stress_sandbox \
    < tools/stress/sql/s130_05_urgency_limits.up.sql
```

---

## 🌱 SEED CYCLE

```bash
# Всички seed скрипти — задайте DB_NAME за да насочвате _db.py към sandbox
export STRESS_DB=runmystore_stress_sandbox

# Stage 1 — STRESS Lab tenant
sudo -u www-data DB_NAME=$STRESS_DB python3 tools/stress/setup_stress_tenant.py --apply

# Stage 2 — 8 локации
sudo -u www-data DB_NAME=$STRESS_DB python3 tools/stress/seed_stores.py --apply

# Stage 3 — 11 доставчика
sudo -u www-data DB_NAME=$STRESS_DB python3 tools/stress/seed_suppliers.py --apply

# Stage 4 — 5 продавачи
sudo -u www-data DB_NAME=$STRESS_DB python3 tools/stress/seed_users.py --apply

# Stage 5 — 3K артикула
sudo -u www-data DB_NAME=$STRESS_DB python3 tools/stress/seed_products_realistic.py --apply

# Stage 6 — 90 дни история
sudo -u www-data DB_NAME=$STRESS_DB python3 tools/stress/seed_history_90days.py --apply
```

**NB: `_db.py` чете `DB_NAME` env override (per `load_db_config()` fallback).** Ако `_db.py` не respect-ва env override, добавяне = trivial 2-редова промяна (виж секция Troubleshooting).

---

## 🧪 ВАЛИДАЦИЯ — ROW COUNTS

```bash
sudo -u www-data env DB_NAME=runmystore_stress_sandbox python3 -c "
import sys; sys.path.insert(0, 'tools/stress')
from _db import connect, load_db_config, resolve_stress_tenant, assert_stress_tenant
conn = connect(load_db_config())
tid = resolve_stress_tenant(conn)
assert tid is not None, 'STRESS Lab tenant missing'
assert_stress_tenant(tid, conn)
with conn.cursor() as cur:
    for q in [
        ('stores',     'SELECT COUNT(*) AS n FROM stores WHERE tenant_id = %s'),
        ('suppliers',  'SELECT COUNT(*) AS n FROM suppliers WHERE tenant_id = %s'),
        ('users',      'SELECT COUNT(*) AS n FROM users WHERE tenant_id = %s'),
        ('products',   'SELECT COUNT(*) AS n FROM products WHERE tenant_id = %s'),
        ('inventory',  'SELECT COUNT(*) AS n FROM inventory i JOIN products p ON p.id = i.product_id WHERE p.tenant_id = %s'),
        ('sales 90d',  'SELECT COUNT(*) AS n FROM sales WHERE tenant_id = %s AND created_at >= NOW() - INTERVAL 90 DAY'),
    ]:
        cur.execute(q[1], (tid,))
        print(f'{q[0]:14s} {cur.fetchone()[\"n\"]:>6d}')
"
```

Очаквани числа (от STRESS_BUILD_PLAN):

| Таблица | Очаквано |
|---|---|
| stores | 8 |
| suppliers | 11 |
| users | 5 |
| products | ≈ 3000 |
| inventory | 3000 × 8 = 24000 (или с distribution) |
| sales 90d | 13500-36000 (150-400/ден × 90) |

---

## 🔄 RESET CYCLE

Reset връща sandbox в seed-нато състояние без drop на цялата DB.

```bash
# Reset с backup (preferred)
sudo -u www-data env DB_NAME=runmystore_stress_sandbox python3 \
    tools/stress/reset_stress_tenant.py --yes-i-am-sure --apply

# Re-seed history (master данни остават)
sudo -u www-data env DB_NAME=runmystore_stress_sandbox python3 \
    tools/stress/seed_history_90days.py --apply

# Run regression tests
sudo -u www-data env DB_NAME=runmystore_stress_sandbox python3 \
    tools/stress/regression_tests/runner.py
```

**Idempotency check:** двата поредни run-а на reset → seed → regression трябва да дават
същата pass/fail таблица.

---

## 🌙 NIGHTLY ROBOT СРЕЩУ SANDBOX

```bash
sudo -u www-data env DB_NAME=runmystore_stress_sandbox python3 \
    tools/stress/cron/nightly_robot.py --apply

sudo -u www-data env DB_NAME=runmystore_stress_sandbox python3 \
    tools/stress/cron/morning_summary.py
```

Очакван output: 200-300 действия, sales поправно decrement-ват inventory, никога < 0.

---

## 🔧 TROUBLESHOOTING

### „STRESS Lab tenant не съществува“
- Setup script-ът не е стартиран. Изпълни `setup_stress_tenant.py --apply` първо.

### „/etc/runmystore/db.env permission denied“
- Винаги изпълнявай като www-data: `sudo -u www-data ...`

### „mysqldump aborted: connection lost“
- Production DB е под натоварване. Изпълни bootstrap в off-peak (01:00-04:00).

### `_db.load_db_config()` не respect-ва `DB_NAME` env override
- Patch (5 реда): след `cfg = ...` добави `if os.getenv('DB_NAME'): cfg['DB_NAME'] = os.getenv('DB_NAME')`.
- Виж предложение в `tools/stress/sandbox_files/patches/db_env_override.diff` (TBD).

### Очаквана продължителност
- Bootstrap: 5-15 мин
- Seed (всички 6): 30-60 мин (history dominates)
- Reset: 1-3 мин
- Nightly robot apply: 5-15 мин
- Regression test runner: < 1 мин

---

## 📦 ARTIFACTS

```
tools/stress/
├── sandbox_db_setup.sh              ← bootstrap
├── SANDBOX_GUIDE.md                 ← този файл
├── sql/
│   ├── s130_03_ai_insights_unique_relax.up/down.sql
│   └── s130_05_urgency_limits.up/down.sql
├── sandbox_files/
│   └── patches/                     ← 6 bugfix diffs (versioned)
├── regression_tests/
│   ├── runner.py
│   └── test_0[1-6]_*.py
└── data/sandbox_runs/               ← всички logs + JSON отчети
```

---

**КРАЙ НА SANDBOX_GUIDE.md**
