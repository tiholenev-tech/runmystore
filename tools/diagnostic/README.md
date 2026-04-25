# tools/diagnostic — Diagnostic Framework

Регресионна тестова система за AI логики на RunMyStore.ai.

## Структура

```
tools/diagnostic/
├── core/                       # Универсални helpers
│   ├── db_helpers.py          # parse /etc/runmystore/db.env, mysql.connector wrapper, tenant guards
│   ├── seed_runner.py         # universal scenario seeder (S80.STEP2)
│   ├── oracle_populate.py     # bulk insert/update в seed_oracle (S80.STEP2)
│   ├── verify_engine.py       # 8 verification_type handlers (S80.STEP2)
│   ├── gap_detector.py        # find pf*() функции без oracle entries (S80.STEP2)
│   ├── report_writer.py       # markdown/email/Telegram отчети (S80.STEP2)
│   └── alert_sender.py        # Telegram CRITICAL alerts (S80.STEP4D)
├── modules/
│   └── insights/              # За compute-insights.php
│       ├── scenarios.py       # 50+ scenarios (S80.STEP3)
│       ├── fixtures.py        # SQL templates за seed (S80.STEP3)
│       └── oracle_rules.py    # mapping pf_name → expected_topic (S80.STEP3)
├── cron/
│   ├── sales_pulse.sh         # nightly 03:00 — 5-15 random sales на tenant=7 (S80.STEP4A)
│   ├── diagnostic_weekly.sh   # понеделник 03:00 (S80.STEP5)
│   ├── diagnostic_monthly.sh  # 1-ви 04:00 (S80.STEP5)
│   └── daily_summary.sh       # 08:30 БГ email на Тихол (S80.STEP4B)
├── sql/                       # Generated SQL за migrations/ (handled от migrate.php)
├── cc_runner.md               # Claude Code orchestration инструкции
└── run_diag.py                # CLI entry point (S80.STEP2)
```

## CLI

```bash
# Manual run (TRIGGER 4)
python3 tools/diagnostic/run_diag.py --module=insights --trigger=manual

# Pristine wipe + reseed + run (best за baseline)
python3 tools/diagnostic/run_diag.py --module=insights --trigger=manual --pristine

# Single scenario debug
python3 tools/diagnostic/run_diag.py --scenario=zombie_pos_0

# JSON output (за Claude Code)
python3 tools/diagnostic/run_diag.py --orchestrated --module=insights --trigger=milestone

# Self-test на DB connection
python3 tools/diagnostic/core/db_helpers.py
```

## Exit codes

| Code | Значение | Действие |
|---|---|---|
| 0 | All A+D PASS | Continue |
| 1 | A или D fail | Rollback signal — не commit |
| 2 | B или C fail | Warning, не блокер |
| 3 | Gap detected | Add scenario преди run |

## Safety

- **Tenant guard:** `assert_safe_tenant()` отказва всичко освен 7 (test) и 99 (eval).
- **Production tenants** (47 ЕНИ, future clients) — категорично забранени.
- **Pristine mode** изтрива products само в whitelisted tenant — никога production.

## Връзки

- `DIAGNOSTIC_PROTOCOL.md` (repo root) — главният протокол
- `MASTER_COMPASS.md` — текущо състояние на rework queue
- `cc_runner.md` (тази папка) — Claude Code orchestration
