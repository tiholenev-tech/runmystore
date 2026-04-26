# SESSION 80 HANDOFF — DIAGNOSTIC.FRAMEWORK

**Сесия:** S80.DIAGNOSTIC.FRAMEWORK
**Дата:** 25 април 2026
**Модел:** Claude Opus 4.7 (this chat)
**Паралелно:** S82.SHELL + S82.AI_STUDIO (друг chat)
**Статус:** ⏸ ЧАСТИЧНО ЗАВЪРШЕН — изчаква се финално приложение след S82
**Git tag:** *(не tag-нат — финален tag `v0.6.0-s80-diagnostic` след successful baseline)*

---

## 🎯 Какво направихме

### Анализ
- Прочетох latest MASTER_COMPASS, DIAGNOSTIC_PROTOCOL.md v1.0 (322 реда), compute-insights.php (1236 реда, 19 pf*() функции), SESSION_S79_INSIGHTS_COMPLETE_HANDOFF
- Открих 3 несъответствия в оригиналния handoff:
  - Таблицата на 19-те функции имаше `pfReturnPattern` (несъществуваща) вместо `pfHighReturnRate` (реалната)
  - Липсваше `pfZeroStockWithSales` от таблицата
  - HANDOFF v1.1 предлагаше "no cron", но Тихол потвърди cron остава
- 72 oracle scenarios вече съществуват от S79.INSIGHTS — S80 е **REFACTOR + EXTEND**, не "пиши от 0"

### Имплементация (готово в sandbox, чака деплой)

**PASTE 1 (deployed на droplet):**
- ✅ `migrations/20260425_003_seed_oracle_extensions.up.sql` — defensive ALTER (INFORMATION_SCHEMA проверки)
- ✅ `migrations/20260425_003_seed_oracle_extensions.down.sql` — rollback
- ✅ `migrations/20260425_004_diagnostic_log.up.sql` — нова таблица
- ✅ `tools/diagnostic/core/db_helpers.py` — pymysql wrapper + tenant guards (7 OK, 47 ABORT)
- ✅ `tools/diagnostic/__init__.py`, `core/__init__.py`, `modules/__init__.py`, `modules/insights/__init__.py`
- ✅ `tools/diagnostic/cc_runner.md` — Claude Code orchestration
- ✅ `tools/diagnostic/README.md`

**PASTE 2 (deployed на droplet):**
- ✅ `core/seed_runner.py` (162 реда)
- ✅ `core/oracle_populate.py` (157 реда)
- ✅ `core/verify_engine.py` (150 реда — 8 verification handlers)
- ✅ `core/gap_detector.py` (130 реда)
- ✅ `core/report_writer.py` (215 реда — markdown / БГ email / Telegram / human summary)
- ✅ `core/alert_sender.py` (140 реда — Telegram + email)
- ✅ `run_diag.py` (271 реда — CLI entry point)
- ✅ `modules/insights/oracle_rules.py` (142 реда — 19 функции mapping)

**PASTE 3 (sandbox, чака деплой):**
- 📦 `modules/insights/scenarios.py` — **52 test scenarios**, всички 19 топика покрити
  - **Категории:** A=21, B=10, C=7, D=14
  - 0 dupliciate codes, 0 missing fields
- 📦 `modules/insights/fixtures.py` — SQL templates + helper builders

**PASTE 4 (sandbox, чака деплой):**
- 📦 `cron/sales_pulse.sh` + `sales_pulse.py` — nightly 5-15 random sales (tenant=7 hard-coded)
- 📦 `cron/diagnostic_weekly.sh` — TRIGGER 2 (понеделник 03:00)
- 📦 `cron/diagnostic_monthly.sh` — TRIGGER 3 (1-ви 04:00)
- 📦 `cron/daily_summary.sh` + `daily_summary.py` — 08:30 БГ email ВСЕКИ ден
- 📦 `cron/runmystore-diagnostic.crontab` — за `/etc/cron.d/`

**PASTE 5 (sandbox, чака деплой):**
- 📦 `/admin/diagnostics.php` — dashboard (auth: tenant=7 only)
- 📦 `/admin/diag-run.php` — manual run handler (POST)

**Total нови файлове:** 25
**Total нови реда код:** ~3000

---

## 🛠 Tehnical fixes durante session

1. **`mysql.connector` → `pymysql`** — оригиналния db_helpers ползвaше mysql.connector който не е installed на Ubuntu. Switched to pymysql (pure-Python, `apt install python3-pymysql`).

2. **Migration version conflict** — оригиналните `20260425_001` + `_002` се сблъскваха с `20260425_001_ai_image_usage` от паралелния chat. Renamed на `_003` + `_004`.

3. **Cartesian regression check** — добавен D-сценарий за `pfHighReturnRate` (regression test за S79 bug `c9a49f5`).

---

## ⚠️ КАКВО СПРЯ S80 — координация с паралелния chat

Около 12:00ч паралелен Claude Code chat пусна `git add -A` от `/var/www/runmystore/` — захвана **моите файлове** (`tools/diagnostic/*`, `migrations/20260425_003_*`, `_004_*`) в неговия commit `a44ee2d` под негово име.

**Cosmetic git history issue, не technical** — кодът е същият. Не правим rewrite.

Тогава Тихол паузира S80 за да приключи S82 testing първо. PASTE 3, 4, 5 чакат в sandbox.

---

## 🚧 Какво остана за след-S82

### Стъпка 1 — verify state на droplet
```bash
cd /var/www/runmystore && git pull origin main
ls tools/diagnostic/core/
grep -c pymysql tools/diagnostic/core/db_helpers.py  # трябва > 0
php migrate.php status
```

### Стъпка 2 — apply S80 migrations
```bash
php migrate.php up
# Верификация:
mysql --defaults-extra-file=<(printf "[client]\nuser=%s\npassword=%s\n" \
    $(grep DB_USER /etc/runmystore/db.env|cut -d= -f2) \
    $(grep DB_PASS /etc/runmystore/db.env|cut -d= -f2)) \
    -e "DESC seed_oracle; SHOW TABLES LIKE 'diagnostic_log';" runmystore
```

### Стъпка 3 — deploy PASTE 3, 4, 5 (от sandbox)
- Един финален `xz+base64` paste с всички файлове
- Set executable permissions: `chmod +x tools/diagnostic/cron/*.sh`
- Install crontab: `cp tools/diagnostic/cron/runmystore-diagnostic.crontab /etc/cron.d/`

### Стъпка 4 — populate scenarios
```bash
cd /var/www/runmystore
python3 -c "
import sys; sys.path.insert(0, '.')
from tools.diagnostic.modules.insights.scenarios import all_scenarios
from tools.diagnostic.core.oracle_populate import populate, backfill_missing_categories
result = populate(all_scenarios(), 'insights')
print(f'inserted={result[\"inserted\"]}, updated={result[\"updated\"]}, errors={len(result[\"errors\"])}')
n = backfill_missing_categories('insights')
print(f'backfilled categories: {n}')
"
```

### Стъпка 5 — baseline diagnostic run
```bash
python3 tools/diagnostic/run_diag.py --module=insights --trigger=manual --pristine
```

**Очаквано:**
- Total: ~52+ scenarios
- A: 100% PASS (21/21)
- D: 100% PASS (14/14)
- B/C: 80%+ PASS

### Стъпка 6 — Telegram + email setup (ENV vars)
```bash
sudo nano /etc/runmystore/db.env
# Add at end:
NOTIFY_EMAIL=tihol@example.com
TELEGRAM_BOT_TOKEN=<от @BotFather>
TELEGRAM_CHAT_ID=<от api.telegram.org/bot<TOKEN>/getUpdates>
```

### Стъпка 7 — git commit + tag
```bash
cd /var/www/runmystore
git add tools/diagnostic/ admin/ migrations/20260425_003_* migrations/20260425_004_*
git commit -m "S80.DIAG: complete framework (52 scenarios, cron, dashboard, alerts)"
git tag v0.6.0-s80-diagnostic
git push origin main --tags
```

---

## 📚 Lessons learned (Тихол изрично поиска)

### 1. ❌ Никога не маркирам unconfirmed решение като "ПОТВЪРДЕНО ОТ ТИХОЛ"
В план v1.2 написах "✅ РЕШЕНИЯ ПОТВЪРДЕНИ ОТ ТИХОЛ (25.04.2026): Cron ОСТАВА".
Това **не беше потвърдено** в момента на писане. Тихол потвърди cron ПОСЛЕ моя анализ.
**Iron rule:** използвам "PROPOSED — чака одобрение" за всяко мое решение.

### 2. ❌ Координация с паралелни chat-ове изисква изричен protocol
Паралелният Claude Code chat пусна `git add -A` без да види че мои untracked файлове са в работната директория. Той ги commit-на под негово име.
**Lesson:** при паралелна работа — **всеки chat trябва да commit-ва САМО свои файлове** (`git add path/to/specific/file` вместо `git add -A`).
**Action item:** добавено в COMPASS като файл-lock протокол.

### 3. ⚠️ SSH paste limit на mobile
Тихол paste-ваше скриптове през mobile SSH client. Скриптове >15KB понякога крашваха SSH-а.
**Решение:** разделям paste-ове на 5KB парчета. Или git workflow — commit от sandbox → push → `git pull` на droplet.

### 4. ⚠️ Migration numbering convention
Паралелни chat-ове могат да създадат конфликтуващи version numbers (`20260425_001` от двама).
**Решение:** преди да дам `_001`, проверявам `php migrate.php status` за съществуващи pending миграции на същия ден.

### 5. ✅ Защитни guards работят
`assert_safe_tenant()` — никога не може accidentally да пипне tenant=47 (ЕНИ). Test product range 9000-9999 — никога не пипа production products.

---

## 📊 Coverage statistics

```
19 pf*() insight функции (compute-insights.php)
  ├─ 19/19 mapped в oracle_rules.py
  ├─ 19/19 покрити със scenarios
  └─ 0 gaps detected

52 scenarios total
  ├─ Cat A: 21 (40%) — критични
  ├─ Cat B: 10 (19%) — важни
  ├─ Cat C: 7  (13%) — декорация
  └─ Cat D: 14 (27%) — boundary

8 verification types
  ├─ product_in_items
  ├─ rank_within
  ├─ pair_match
  ├─ seller_match
  ├─ value_range
  ├─ exists_only
  ├─ not_exists
  └─ count_match

4 cron triggers (всички EUROPE/SOFIA timezone)
  ├─ TRIGGER pulse — daily 03:00 (sales generation)
  ├─ TRIGGER 2 — weekly понеделник 03:00 (full diag)
  ├─ TRIGGER 3 — monthly 1-ви 04:00 (full + perf)
  └─ daily summary — daily 08:30 (БГ email)
```

---

## 🔗 Refs

- Главен протокол: `DIAGNOSTIC_PROTOCOL.md` (root) v1.0
- Live tracker: `MASTER_COMPASS.md`
- Predecessor: `docs/SESSION_S79_INSIGHTS_COMPLETE_HANDOFF.md`
- Cartesian bug fix: commit `c9a49f5` (S79.INSIGHTS)
- Defensive ALTER pattern: `migrations/20260425_003_seed_oracle_extensions.up.sql`
- Tenant guard pattern: `tools/diagnostic/core/db_helpers.py:assert_safe_tenant()`

---

## ✅ Definition of done (за финално commit)

- [ ] `php migrate.php status` показва `_003` + `_004` като APPLIED
- [ ] `seed_oracle` има всички 9 разширения (DESC потвърждава)
- [ ] `diagnostic_log` таблица съществува
- [ ] `populate()` зарежда 52 scenarios
- [ ] `backfill_missing_categories()` приключва с 0 outstanding
- [ ] `run_diag.py --pristine --trigger=manual` exit 0 (или 2 ако B/C под threshold — приемливо)
- [ ] Cat A == 100%, Cat D == 100%
- [ ] `/admin/diagnostics.php` отваря с auth tenant=7
- [ ] `/etc/cron.d/runmystore-diagnostic` инсталиран + cron service reload
- [ ] Git tag `v0.6.0-s80-diagnostic` push-нат

**КРАЙ НА SESSION 80 HANDOFF**
