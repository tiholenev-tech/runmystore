# S81.DIAG.VERIFY HANDOFF — 26.04.2026

**Tag:** v0.6.0-s81-diag-verify (commit 33d0c13)
**Status:** Pipeline functional, DOD postponed S82+

## 8 Fixes
1. seed_runner: sqlparse multi-statement
2. seed_runner: cleanup_test_data cross-tenant + TEST_SALE_ID_RANGE
3. seed_runner: trigger_compute_insights positional argv
4. seed_runner: fetch_active_scenarios sys.path fix (CRITICAL silent skip)
5. oracle_rules: topic_to_category alias
6. oracle_populate: backfill_missing_categories guard
7. scenarios: high_return_rate field 'rate'
8. fixtures: INVENTORY_TPL/SALE_TPL/RETURN_TPL tenant_id+total cols

## DB
- 52 active oracle (tenant=99) + 173 deprecated
- 13 diagnostic_log runs

## Baseline (RUN #13)
A=47.83% B=50% C=57% D=21% — EXIT=1

## TODO S82
P1: lost_demand 'query' col, basket_pair inline INSERT total, negative scenarios overlap
P2: crontab install, ENV vars Telegram/email, re-baseline target A=100% D=100%

## Run command
python3 -B tools/diagnostic/run_diag.py --module=insights --trigger=manual --tenant=99 --pristine --skip-gap-check
