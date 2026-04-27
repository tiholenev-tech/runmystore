# 🔁 TESTING_LOOP_PROTOCOL — Continuous AI Insights Validation

**Версия:** v1.0  
**Активиран:** 27.04.2026 (S87.TESTING_LOOP)  
**Tenant:** 99 (`DIAGNOSTIC_TEST_TENANT`, isolated от Тихол=7 и ЕНИ=52)

---

## 🎯 Цел и философия

Тихол не може да проверява `ai_insights` всеки ден ръчно. Loop-ът прави това вместо
него: всяка сутрин 07:00 `cron` хвърля seed → cron pipeline → snapshot → compare с
вчерашния → статус (🟢/🟡/🔴) → записва anomaly. Шеф-чат при boot чете `latest.json` и
нос състоянието в STATUS REPORT.

Принципи:

1. **Tenant=99 = isolated lab.** Не пипа production tenant=7 / ЕНИ=52.
2. **Idempotent.** Re-run в същия ден презаписва, не дуплицира.
3. **Graceful degradation.** Ако `tools/seed/sales_populate.py` още не е готов
   (Code #3 dependency), loop-ът работи и без daily seed — само cron + snapshot diff.
4. **Read-only към production code.** Не патчва `compute-insights.php`,
   `cron-insights.php`, schema или helpers.

---

## 🧩 4 компонента

| # | Компонент | Файл | Какво прави |
|---|---|---|---|
| 1 | **Seed** | `tools/seed/sales_populate.py` (own by Code #3) | Сипва ~15 нови продажби в tenant=99 → захранва pf*() функциите. |
| 2 | **Cron** | `compute-insights.php::computeProductInsights(99)` | Ре-генерира `ai_insights` за tenant=99. |
| 3 | **Snapshot** | `tools/testing_loop/daily_runner.py` | Прави SQL counters снимка → `daily_snapshots/YYYY-MM-DD.json`. |
| 4 | **Diff** | `tools/testing_loop/snapshot_diff.py` | Сравнява today vs yesterday → status + reason + recommendations JSON. |

---

## 🚦 Алертинг прагове

| Status | Условие |
|---|---|
| 🟢 **healthy** | insights в ±20% range vs yesterday · 6/6 fundamental_question покрити · cron ≤ 30 min ago |
| 🟡 **warning** | insights drop 20-40% · 1-2 въпроса = 0 · cron 30-60 min ago |
| 🔴 **critical** | cron failed / no run · insights drop > 40% · 3+ въпроса = 0 |

🟡 / 🔴 → се добавят в `tools/testing_loop/ANOMALY_LOG.md`. 🟢 → не log-ва (тишина = добро).

---

## ⏰ Schedule

`07:00` daily. `cron-insights.php` вече върви на всеки 15 min, така че snapshot е
винаги ≤ 15 min stale при snapshot time.

---

## 📁 Файлова структура

```
tools/testing_loop/
├── daily_runner.py            # orchestrator, влиза през crontab
├── snapshot_diff.py           # status engine
├── daily_snapshots/
│   ├── 2026-04-27.json
│   ├── 2026-04-28.json
│   └── …                       # 1 файл на ден; archived чрез git history
├── latest.json                 # symlink → най-нов snapshot (atomic update)
└── ANOMALY_LOG.md             # append-only при 🟡/🔴
```

`TESTING_LOOP_PROTOCOL.md` (този файл) живее в **repo root** — четен от шеф-чат
boot procedure.

---

## 🤖 Как шеф-чат го чете при boot

`SHEF_BOOT_INSTRUCTIONS.md` Phase 2 STATUS REPORT задължителен ред:

```
TESTING LOOP: <status> · last run <ts> · <N> live insights tenant=99
             (read tools/testing_loop/latest.json)
```

Ако `latest.json` липсва → "TESTING LOOP: not yet running (cron not installed)".  
Ако status != healthy → шеф-чат **първо** flag-ва anomaly преди всичко друго.

---

## 🛠 Manual override команди (debugging)

```bash
# Single run, full pipeline
python3 /var/www/runmystore/tools/testing_loop/daily_runner.py

# Only diff (без seed/cron) — анализ на вчерашен снимка
python3 /var/www/runmystore/tools/testing_loop/snapshot_diff.py \
  --today  tools/testing_loop/daily_snapshots/2026-04-28.json \
  --yesterday tools/testing_loop/daily_snapshots/2026-04-27.json

# Force snapshot only (без seed, без diff, без git push)
python3 /var/www/runmystore/tools/testing_loop/daily_runner.py --snapshot-only

# Skip git push (за local dry-run)
python3 /var/www/runmystore/tools/testing_loop/daily_runner.py --no-push
```

---

## ⚙️ Setup — manual command за Тихол

Crontab НЕ се инсталира автоматично от Claude Code. Тихол paste-ва:

```bash
sudo crontab -e -u www-data
```

Add line:

```
0 7 * * * /usr/bin/python3 /var/www/runmystore/tools/testing_loop/daily_runner.py >> /var/log/testing_loop.log 2>&1
```

Verify:

```bash
sudo crontab -l -u www-data | grep testing_loop
sudo touch /var/log/testing_loop.log && sudo chown www-data:www-data /var/log/testing_loop.log
```

---

## 💥 Failure modes + recovery

| Symptom | Likely cause | Recovery |
|---|---|---|
| `latest.json` стое > 26h без update | crontab не инсталиран / www-data permission на `daily_snapshots/` | `sudo crontab -l -u www-data` + `chown -R www-data:www-data tools/testing_loop/daily_snapshots/` |
| Runner crash на seed step | `tools/seed/sales_populate.py` още не съществува или има bug | Очаквано — runner продължава с warning. След като Code #3 закара seeder-а, recovery автоматично. |
| Runner crash на cron step | DB credentials в `/etc/runmystore/db.env` са недостъпни за www-data | `sudo -u www-data cat /etc/runmystore/db.env` — chmod 600 + chown www-data:www-data |
| `git push` fail (mirror cron race) | `/usr/local/bin/sync-md-mirrors.sh` бута паралелно (incident e5c2929) | Runner прави 1× retry с pull --rebase. Ако пак fail → snapshot стои в работно дърво, шеф-чат ще го забележи при следващ boot. |
| Anomaly log расте бързо | Реален regression в `compute-insights.php` или drop в seed данни | Спри loop temporarily чрез коментиране на crontab line; investigate `ANOMALY_LOG.md` last 5 entries. |
| Diff status винаги "no_baseline" | По-малко от 2 snapshot-а в `daily_snapshots/` | Wait 1 ден — самокоригира се. |

---

## 🔒 Какво loop-ът НЕ прави

- Не пипа tenant=7 (Тихол) или tenant=52 (ЕНИ).
- Не deploy-ва код, не модифицира schema.
- Не trigger-ва retentions / clean-up — само append snapshots.
- Не изпраща нотификации навън (no Slack/email). Сигнал минава САМО през
  `latest.json` + `ANOMALY_LOG.md` + `admin/beta-readiness.php`.

---

## 📜 Promotion to STANDING RULE?

При 7 поредни 🟢 дни (или 14 дни overall с ≥80% 🟢), шеф-чат promote-ва loop-а
като STANDING_RULE_#23 в `MASTER_COMPASS.md` LOGIC CHANGE LOG.
