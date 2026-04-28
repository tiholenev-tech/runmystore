# 🕒 TESTING_LOOP — CRON INSTALL & VERIFY

**Цел:** да инсталираме `tools/testing_loop/daily_runner.py` като ежедневен cron job
под `www-data` (така че `git push` от runner-а да работи без sudo prompts и
permissions върху `daily_snapshots/` да са consistent с web stack-а).

> ⚠️ **Този документ НЕ изпълнява нищо** — Тихол копира one-liner-а в droplet
> console-а (или go-go DigitalOcean web shell) ръчно. След това `verify`
> командата трябва да върне точно един ред.

---

## 1. Точният crontab line за `www-data`

```cron
30 3 * * * cd /var/www/runmystore && /usr/bin/python3 tools/testing_loop/daily_runner.py >> logs/testing_loop.log 2>&1
```

**Защо 03:30:**
- compute-insights cron heartbeat е `compute_insights_15min` — в 03:00 има
  свежи insights, в 03:30 snapshot улавя пълна картина.
- 04:00+ е reserve за други nightly jobs (mirrors, backups).

**Защо `cd /var/www/runmystore && python3 tools/...`:**
- `daily_runner.py` използва `REPO_ROOT = Path("/var/www/runmystore")` (вж.
  файла), но git operations за commit/push изискват CWD == repo root.
- Изричен `/usr/bin/python3` (а не `python3`) защото cron PATH е минимален.
- `>> logs/testing_loop.log 2>&1` — append вместо overwrite, за да можем
  да видим няколко runs назад.

---

## 2. Idempotent install one-liner

Копирай и изпълни в droplet console-а **като root** (или с `sudo -i`):

```bash
sudo -u www-data crontab -l 2>/dev/null | grep -q daily_runner || (sudo -u www-data crontab -l 2>/dev/null; echo "30 3 * * * cd /var/www/runmystore && /usr/bin/python3 tools/testing_loop/daily_runner.py >> logs/testing_loop.log 2>&1") | sudo -u www-data crontab -
```

**Какво прави:**
1. `crontab -l | grep -q daily_runner` — проверява дали редът вече съществува.
2. Ако не, append-ва текущия crontab + новия ред и го пише обратно.
3. `||` с групирани subshells пази съществуващите cron lines (gold standard
   idempotent pattern — multiple runs не дублират).

Ако няма съществуващ crontab за `www-data`, `crontab -l` пуска stderr
warning-а а не грешка — `2>/dev/null` го заглушава.

---

## 3. Verify (трябва да върне точно 1 ред)

```bash
sudo -u www-data crontab -l | grep daily_runner
```

Очаквано output:
```
30 3 * * * cd /var/www/runmystore && /usr/bin/python3 tools/testing_loop/daily_runner.py >> logs/testing_loop.log 2>&1
```

Ако върне 2+ реда → `crontab -l` грешка, прескочи install-а и махни ръчно
дублираните с `sudo -u www-data crontab -e`.

Ако върне 0 реда → install не сработи, провери дали `www-data` shell има
достъп до `crontab` binary (`which crontab` като www-data).

---

## 4. Manual trigger (днешния snapshot, без да чакаш 03:30)

```bash
sudo -u www-data /usr/bin/python3 /var/www/runmystore/tools/testing_loop/daily_runner.py
```

След run проверка:

```bash
ls -la /var/www/runmystore/tools/testing_loop/daily_snapshots/ | tail -5
cat /var/www/runmystore/tools/testing_loop/latest.json | python3 -m json.tool | head -40
```

Очаквано: нов файл `YYYY-MM-DD.json` (днешна дата) и `latest.json` symlink-нат към него.

---

## 5. Post-install monitoring (24h след първи cron run)

```bash
# Tail the log след 03:30 next morning
tail -50 /var/www/runmystore/logs/testing_loop.log

# Last snapshot status
python3 -c "import json; d=json.load(open('/var/www/runmystore/tools/testing_loop/latest.json')); print('date=', d.get('snapshot_date'), 'diff_status=', (d.get('diff') or {}).get('status'))"

# ANOMALY_LOG за 🟡/🔴 за последна седмица
tail -100 /var/www/runmystore/tools/testing_loop/ANOMALY_LOG.md
```

---

## Troubleshooting

| Проблем | Причина | Fix |
|---|---|---|
| `Permission denied` при git push от daily_runner | www-data няма SSH key за GitHub | Setup deploy key ИЛИ ползвай `--no-push` flag в crontab |
| `pymysql not found` | www-data Python venv различен | `sudo -u www-data /usr/bin/python3 -m pip install pymysql` |
| Snapshot има `snapshot_error` | `/etc/runmystore/db.env` не е readable за www-data | `chmod 644 /etc/runmystore/db.env` (file няма secrets освен DB pwd, която е и в php config) |
| Cron не стартира | crontab не е активен | `sudo systemctl status cron` → `sudo systemctl restart cron` |

---

**Last updated:** 2026-04-28 (S88.DIAG.EXTEND)
**Owner:** Тихол (manual install) + Claude (этот doc)
