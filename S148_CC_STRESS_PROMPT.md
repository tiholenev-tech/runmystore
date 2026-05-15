# 🌙 CLAUDE CODE — STRESS START TONIGHT — TENANT 7

> **AUDIENCE:** Claude Code в tmux session на droplet 164.90.217.120.
> **WHEN:** Тази вечер (15.05.2026 ~22:00 EEST).
> **GOAL:** Утре сутрин 07:00 — Тих отваря `STRESS_DAILY_REPORT.md` и вижда дали 4 сценария (S001, S002, S007, S009) минават.
> **TENANT:** 7 (Тих's пробен, изцяло wipe-able).

═══════════════════════════════════════════════════════════════
🚨 АБСОЛЮТНИ ЗАБРАНИ
═══════════════════════════════════════════════════════════════

1. ❌ НЕ пипаш tenant_id ≠ 7 (никога)
2. ❌ НЕ `rm -rf` под `/var/www/runmystore/` или `/etc/`
3. ❌ НЕ `git push --force`
4. ❌ НЕ `DROP TABLE` (само truncate ако е strictly tenant=7 scope)
5. ❌ НЕ пипаш sacred файлове:
   - `services/voice-tier2.php`
   - `services/ai-color-detect.php`
   - `js/capacitor-printer.js`
   - functions `_wizMicWhisper`, `_wizPriceParse`, `_bgPrice` в products.php
6. ❌ НЕ activate-ваш Telegram alerts
7. ❌ НЕ install-ваш cron-овете в `/etc/cron.d/` тази нощ. Manual run only.

**Ако нарушиш — Тих изгубва доверие за пореден път. Не оставаш в проекта.**

═══════════════════════════════════════════════════════════════
📋 КОНТЕКСТ — КАКВО Е ГОТОВО, КАКВО НЕ РАБОТИ
═══════════════════════════════════════════════════════════════

**Repo:** `tiholenev-tech/runmystore` (main)
**Path:** `/var/www/runmystore/`
**DB:** `runmystore` (production), creds в `/etc/runmystore/db.env`

**Готово (от S128/S131/S133):**
- ✅ 75 сценария дефинирани в `tools/stress/scenarios/`
- ✅ Sandbox DB `runmystore_stress_sandbox` тестван — не ползваме
- ✅ Seed scripts (seed_stores, seed_suppliers, seed_users, seed_products_realistic, seed_history_90days)
- ✅ `nightly_robot.py` готов
- ✅ Branch `s133-stress-finalize` с 4 commits — **НЕ merged**

**5 P0 bug-а нерешени:**
1. `s130_03` migration не idempotent (DROP INDEX без EXISTS)
2. `seed_history_90days.py` не пише stock_movements, deliveries, transfers
3. `balance_validator.py` crash на `quantity_after`
4. 112 rows negative inventory (seed позволява това)
5. `test_02` queries несъществуващ `status` column

**HARD GUARD:** `tools/stress/_db.py` ред 113-117 отказва писане на tenant_id=7. **Тази вечер трябва да го махнем.**

═══════════════════════════════════════════════════════════════
🌙 ТАЗИ ВЕЧЕР — 6 СТЪПКИ (TIMELINE ~3-4 ЧАСА)
═══════════════════════════════════════════════════════════════

### СТЪПКА 1 — Backup (10 мин)

```bash
cd /var/www/runmystore

# DB backup
mysqldump runmystore > /tmp/runmystore_pre_stress_$(date +%Y%m%d_%H%M).sql
gzip /tmp/runmystore_pre_stress_*.sql

# Git tag
git pull origin main
git tag pre-s148-stress-tonight-$(date +%Y%m%d_%H%M)
git push origin pre-s148-stress-tonight-*

# Verify tenant_id=7 е празен ИЛИ имаш OK от Тих за wipe
mysql runmystore -e "SELECT COUNT(*) FROM products WHERE tenant_id=7;
                     SELECT COUNT(*) FROM sales WHERE tenant_id=7;"
```

⚠️ **Ако в tenant_id=7 има >100 продукта или >0 sales** → STOP. Питай Тих преди wipe.

### СТЪПКА 2 — Премахни HARD GUARD (15 мин)

Edit `tools/stress/_db.py` редове 113-117:

```python
# СТАРО (премахни):
if tenant_id == ENI_TENANT_ID:  # = 7
    sys.exit(f"[REFUSE] tenant_id={tenant_id} е ENI Тихолов...")

# НОВО (вместо това):
# ENI_TENANT_ID guard деактивиран по решение на Тих 15.05.2026
# tenant_id=7 е пробен профил, изцяло wipe-able
# Когато ENI клиент бъде onboard-ван на собствен tenant → set REAL_ENI_TENANT_ID константа
REAL_ENI_TENANT_ID = -1  # placeholder, set когато ENI клиент е готов
if tenant_id == REAL_ENI_TENANT_ID:
    sys.exit(f"[REFUSE] tenant_id={tenant_id} е РЕАЛЕН ENI. Refuse.")
```

Също коригирай `STRESS_EMAIL` или email check ако блокира.

```bash
git add tools/stress/_db.py
git commit -m "S148: премахни HARD GUARD за tenant_id=7 (пробен профил per Тих 15.05)"
git push origin main
```

### СТЪПКА 3 — Wipe + Seed tenant_id=7 (30-40 мин)

```bash
# Wipe (САМО tenant_id=7)
python3 tools/stress/reset_stress_tenant.py --tenant 7 --confirm 2>&1 | tee /tmp/wipe.log

# Verify wipe
mysql runmystore -e "SELECT COUNT(*) FROM products WHERE tenant_id=7;
                     SELECT COUNT(*) FROM stores WHERE tenant_id=7;
                     SELECT COUNT(*) FROM sales WHERE tenant_id=7;"
# Expected: всичко 0

# Seed (production DB, tenant=7)
python3 tools/stress/seed_stores.py --tenant 7 --apply              # 8 stores
python3 tools/stress/seed_suppliers.py --tenant 7 --apply           # 11 suppliers
python3 tools/stress/seed_users.py --tenant 7 --apply               # 5 users
python3 tools/stress/seed_products_realistic.py --tenant 7 --apply  # 3031 products
```

⚠️ **Преди seed_history_90days → STOP**. Има P0 bug. Виж стъпка 4.

### СТЪПКА 4 — Fix-ни 5-те P0 bug-а (60-90 мин)

**P0 #1 — s130_03 idempotency:**
```sql
-- В tools/stress/sql/s130_03_urgency_limits.up.sql
-- Промени DROP INDEX за safe drop:
SET @s := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema=DATABASE() AND table_name='sales' AND index_name='idx_old_name')>0,
  'ALTER TABLE sales DROP INDEX idx_old_name',
  'SELECT "no_old_index" AS msg'
));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
```

**P0 #2 — seed_history_90days пише stock_movements/deliveries:**

В `tools/stress/seed_history_90days.py` — за всяка продажба:
```python
# След INSERT в sales:
cursor.execute("""
  INSERT INTO stock_movements (tenant_id, product_id, store_id,
    movement_type, quantity, quantity_after, reference_type, reference_id, created_at)
  VALUES (%s, %s, %s, 'sale', -%s, %s, 'sales', %s, %s)
""", [tenant_id, product_id, store_id, qty, current_qty - qty, sale_id, sale_date])

# UPDATE inventory:
cursor.execute("""
  UPDATE inventory SET quantity = quantity - %s
  WHERE tenant_id=%s AND product_id=%s AND store_id=%s
""", [qty, tenant_id, product_id, store_id])
```

За deliveries (1-2 на седмица):
```python
# Random доставка от supplier на random категория
delivery_id = cursor.execute("INSERT INTO deliveries (...) VALUES (...)")
for product in random_products:
    cursor.execute("INSERT INTO delivery_items (...) VALUES (...)")
    cursor.execute("""
      INSERT INTO stock_movements (tenant_id, product_id, ..., movement_type, quantity, quantity_after)
      VALUES (..., 'delivery', +%s, %s)
    """, [qty, current_qty + qty])
```

**P0 #3 — balance_validator schema:**

Виж `tools/stress/balance_validator.py` — fix-ни schema mismatch. Може би просто `quantity_after` колоната не съществува в stock_movements. Ако е така:

```sql
-- Add quantity_after колона
ALTER TABLE stock_movements ADD COLUMN quantity_after INT NULL AFTER quantity;
```

ИЛИ премахни от query-то в `balance_validator.py` ако не ни трябва.

**P0 #4 — negative inventory check:**

В `seed_history_90days.py`:
```python
# Преди INSERT в sales — check inventory > 0
if current_inventory < qty:
    qty = max(1, current_inventory // 2)  # adjust qty
    if current_inventory <= 0:
        continue  # skip тая продажба
```

**P0 #5 — test_02 status column:**

Намери `test_02` в `tools/stress/regression_tests/` и виж какъв column използва. Probably трябва `state` или `sale_status` вместо `status`. Verify schema:
```bash
mysql runmystore -e "SHOW COLUMNS FROM sales;"
```

После fix query-то.

Commit-вай всеки fix отделно:
```bash
git add tools/stress/seed_history_90days.py
git commit -m "S148.P0#2: seed_history пише stock_movements + deliveries"

git add tools/stress/balance_validator.py
git commit -m "S148.P0#3: balance_validator schema fix"
# ... etc.
```

### СТЪПКА 5 — Run seed_history + manual nightly (30 мин)

```bash
# Seed history (57K sales × 90 дни)
python3 tools/stress/seed_history_90days.py --tenant 7 --apply 2>&1 | tee /tmp/seed_history.log

# Verify
mysql runmystore -e "
  SELECT COUNT(*) AS total_sales FROM sales WHERE tenant_id=7;
  SELECT COUNT(*) AS total_movements FROM stock_movements WHERE tenant_id=7;
  SELECT COUNT(*) AS negative_inv FROM inventory WHERE tenant_id=7 AND quantity < 0;
"
# Expected: ~57000 sales, ~57000+ movements, 0 negative inventory

# Manual първи nightly run
python3 tools/stress/cron/nightly_robot.py --tenant 7 --apply 2>&1 | tee /tmp/first_nightly.log

# Check изхода — трябва да минaт S001, S002, S007, S009 (другите disabled)
```

⚠️ **Ако някой от S001/S002/S007/S009 fail-не** — STOP. Не activate-вай report. Питай Тих.

### СТЪПКА 6 — Daily Report Writer + Disable Switch (45-60 мин)

Създай `tools/stress/daily_report_writer.py`:

```python
#!/usr/bin/env python3
"""
Чете изхода на nightly_robot и пише STRESS_DAILY_REPORT.md в repo root.
При FAIL — touch /etc/runmystore/stress.disabled (cron-овете спират).
"""
import json, sys, subprocess
from datetime import datetime
from pathlib import Path

REPORT_PATH = Path('/var/www/runmystore/STRESS_DAILY_REPORT.md')
DISABLE_FILE = Path('/etc/runmystore/stress.disabled')

def main():
    # Read nightly_robot output
    with open('/tmp/last_nightly.log') as f:
        log = f.read()

    # Parse pass/fail per scenario
    results = {}  # {scenario_id: {pass: bool, error: str}}
    for line in log.split('\n'):
        if 'SCENARIO' in line:
            # Parse: SCENARIO S001 PASS / SCENARIO S002 FAIL: error_msg
            ...

    # Determine overall status
    failed = [s for s, r in results.items() if not r['pass']]
    status = '🔴 ПРОБЛЕМ' if failed else '✅ OK'

    # Write report
    md = f"""# 🌙 STRESS DAILY REPORT

**{status}**
**Дата:** {datetime.now():%Y-%m-%d %H:%M EEST}
**Tenant:** 7 (Тих's пробен)

---

## Резултати

"""
    for sid, r in sorted(results.items()):
        icon = '✅' if r['pass'] else '🔴'
        md += f"- {icon} **{sid}**"
        if not r['pass']:
            md += f" — `{r['error']}`"
        md += '\n'

    if failed:
        md += f"\n## ⚠️ Crons СПРЕНИ\n\nЗа resume след fix:\n```bash\nbash /var/www/runmystore/tools/stress_resume.sh\n```\n"

    REPORT_PATH.write_text(md)

    # Disable crons ако FAIL
    if failed:
        DISABLE_FILE.touch()

    # Commit + push report
    subprocess.run(['git', '-C', '/var/www/runmystore', 'add',
                    'STRESS_DAILY_REPORT.md'], check=True)
    subprocess.run(['git', '-C', '/var/www/runmystore', 'commit', '-m',
                    f'stress: daily report {datetime.now():%Y-%m-%d}'], check=False)
    subprocess.run(['git', '-C', '/var/www/runmystore', 'push',
                    'origin', 'main'], check=False)

if __name__ == '__main__':
    main()
```

Създай `tools/stress_resume.sh`:
```bash
#!/bin/bash
# Resume cron-овете след manual fix
rm -f /etc/runmystore/stress.disabled
echo "✓ Stress crons re-enabled. Next nightly at 02:00."
```

```bash
chmod +x tools/stress_resume.sh tools/stress/daily_report_writer.py
git add tools/stress/daily_report_writer.py tools/stress_resume.sh
git commit -m "S148.STEP6: daily_report_writer + resume script"
git push origin main
```

═══════════════════════════════════════════════════════════════
🌅 УТРЕ СУТРИН (07:00 EEST)
═══════════════════════════════════════════════════════════════

Manual run на цялата chain (не cron — ще се монтират по-късно):

```bash
python3 /var/www/runmystore/tools/stress/cron/nightly_robot.py --tenant 7 --apply 2>&1 | tee /tmp/last_nightly.log
python3 /var/www/runmystore/tools/stress/daily_report_writer.py
```

Тих отваря:
```
https://github.com/tiholenev-tech/runmystore/blob/main/STRESS_DAILY_REPORT.md
```

═══════════════════════════════════════════════════════════════
✅ ACCEPTANCE
═══════════════════════════════════════════════════════════════

- [ ] DB backup направен ✓
- [ ] Git tag pre-s148-stress-tonight-* ✓
- [ ] HARD GUARD премахнат ✓
- [ ] tenant_id=7 wipe-нат и re-seeded ✓
- [ ] 5-те P0 bug-а fix-нати ✓
- [ ] Manual nightly run завършил без crash ✓
- [ ] S001, S002, S007, S009 PASS ✓
- [ ] `STRESS_DAILY_REPORT.md` push-нат в repo ✓

**Ако всичко 8/8 — Тих сутрин ще види report. Иначе — кажи му какво се счупи.**

═══════════════════════════════════════════════════════════════
💬 ПРИ ПРОБЛЕМ — STOP И ПИТАЙ
═══════════════════════════════════════════════════════════════

Преди каквото и да е от:
- Edit на sacred zone
- Промяна на DB schema (CREATE/DROP/ALTER извън планираното)
- `git push --force` (никога)
- Wipe на каквото и да е от друг tenant_id

→ STOP. Запиши какво искаш да направиш. Чакай Тих.

═══════════════════════════════════════════════════════════════

**Готов?** Започвай от Стъпка 1. Pull-вай repo-то първо.
