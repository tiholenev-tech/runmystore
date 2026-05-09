# STRESS Cron Install Guide — S133 Phase G

> **Audience:** Тихол (or whoever has root on the production server).
> **Goal:** install the 4 STRESS cron files at `/etc/cron.d/`, with the right environment so they write to **sandbox** (`runmystore_stress_sandbox`), never production.

## ⚠️ Pre-install — CRITICAL

The 4 cron scripts read `/etc/runmystore/cron.env` via `BASH_ENV`. If that file is missing OR doesn't override `DB_NAME`, then `nightly_robot --apply` and `test_new_features --apply` will write to the production `runmystore` database. **Don't skip this step.**

### Step 0 — create `/etc/runmystore/cron.env`

```bash
sudo tee /etc/runmystore/cron.env > /dev/null <<'EOF'
# STRESS система — env за всички /etc/cron.d/stress-* jobs.
# Sourced by BASH_ENV directive в всеки cron file.

# Redirect _db.load_db_config() към sandbox.
# Без този override scripts пишат в production runmystore DB.
DB_NAME=runmystore_stress_sandbox

# Python може да се нуждае от това ако imports се счупят:
# PYTHONPATH=/var/www/runmystore/tools/stress
EOF

sudo chmod 0640 /etc/runmystore/cron.env
sudo chown root:www-data /etc/runmystore/cron.env

# Verify www-data can read it
sudo -u www-data cat /etc/runmystore/cron.env
```

### Step 1 — confirm /var/log/runmystore exists (it does, on this server)

```bash
ls -ld /var/log/runmystore
# Expected: drwxr-xr-x ... www-data www-data
# (Already exists; skip if so.)
```

### Step 2 — sanity-check crons would target sandbox

Run a single dry-run **as www-data** with the same env the cron will use, to prove DB_NAME override flows through:

```bash
sudo -u www-data env $(grep -v '^#' /etc/runmystore/cron.env | xargs) \
  python3 -c "import sys; sys.path.insert(0, '/var/www/runmystore/tools/stress'); \
              from _db import load_db_config; print('DB =', load_db_config()['DB_NAME'])"
# Expected: DB = runmystore_stress_sandbox
```

If that prints `runmystore` → STOP and re-check `cron.env`. **Do not install the crons until this prints `runmystore_stress_sandbox`.**

## Install — copy + permission + restart

```bash
# Run from the production checkout dir (the cron files cd into /var/www/runmystore)
cd /var/www/runmystore

sudo cp tools/stress/cron/installable/stress-nightly  /etc/cron.d/stress-nightly
sudo cp tools/stress/cron/installable/stress-morning  /etc/cron.d/stress-morning
sudo cp tools/stress/cron/installable/stress-sanity   /etc/cron.d/stress-sanity
sudo cp tools/stress/cron/installable/stress-newfeat  /etc/cron.d/stress-newfeat

sudo chown root:root /etc/cron.d/stress-*
sudo chmod 644 /etc/cron.d/stress-*

sudo systemctl reload cron     # picks up new files
# or:
sudo systemctl restart cron
```

## Verify install

```bash
# Files in place + correct perms
ls -la /etc/cron.d/stress-*
# Expected: -rw-r--r-- root root ... 4 files

# Each file is syntactically loaded (cron logs the parse on reload)
journalctl -u cron --since "5 minutes ago" | grep -i stress

# Show the schedule cron sees
sudo cat /etc/cron.d/stress-nightly
```

## Schedule summary

| Time | File | Script | Mutates? | Mode |
|---|---|---|---|---|
| 02:00 | stress-nightly | nightly_robot.py | Yes (--apply) | writes 200-300+ actions |
| 03:00 | stress-newfeat | test_new_features.py | Yes (--apply) | conditional, only if commits in last 24h |
| 06:00 | stress-morning | morning_summary.py | No (read-only) | summarizes overnight run |
| 06:30 | stress-morning | code_analyzer.sh | No | writes MORNING_REPORT.md only |
| 07:00 | stress-sanity | sanity_checker.py | No (read-only) | balance validation |
| 01:00 | stress-sanity | sanity_checker.py --cleanup-only | Yes | 60-day window cleanup of STRESS Lab tenant ONLY (guard: `assert_stress_tenant`) |

## Rollback

```bash
# Remove all 4 files
sudo rm /etc/cron.d/stress-*
sudo systemctl reload cron

# Optionally remove env file (keep if you may re-install)
# sudo rm /etc/runmystore/cron.env
```

## What was NOT done in this session

Per the session brief's "ZERO install в /etc/cron.d/", I generated this guide rather than installing. **Nothing is yet active in `/etc/cron.d/`.** Until you run the commands above, no STRESS cron is firing.

## Caveats / known issues to know before installing

1. **`balance_validator.py` movements mode crashes** on the current schema (`stock_movements.quantity_after` column missing). The `sanity_checker.py` cron uses the validator internally — verify which mode it invokes before relying on the 07:00 job. If `movements` mode is invoked, the cron will fail every morning until F.1 fix is shipped (see `BALANCE_VALIDATOR_REPORT.md`).
2. **First nightly_robot --apply run** in sandbox should be done manually before installing the 02:00 cron, to catch any first-run bugs. Suggested:

   ```bash
   sudo -u www-data env $(grep -v '^#' /etc/runmystore/cron.env | xargs) \
     python3 /var/www/runmystore/tools/stress/cron/nightly_robot.py --tenant 1000 --apply 2>&1 | tee /tmp/first_apply.log
   ```
3. **Telegram alerts** — `tools/stress/alerts/cron_hooks.py` is wired in but per the brief is **not** activated this round. The crons will still post heartbeats; they just won't page on P0 until the bot is configured.
