# tools/stress/ci/

**Phase N5 (S130 extension) — CI workflow placeholders.**

## stress-registry-check.yml

GitHub Action workflow който проверява дали `STRESS_SCENARIOS.md` и
`STRESS_BOARD.md` са синкронизирани с `tools/stress/scenarios/*.json`.

### Защо тук вместо в `.github/workflows/`?

Текущият scope на работа на `s130-stress-extension` branch-а ограничава
писане до `tools/stress/`, `db/migrations/stress_*`, `admin/stress-*`
и `admin/health.php`. `.github/workflows/` е извън този allowlist.

### Активиране

```bash
# 1. Премести workflow-а в .github/workflows/ (изисква sudo / chown ако
#    .github/workflows/ е root-owned)
mv tools/stress/ci/stress-registry-check.yml .github/workflows/

# 2. Commit + push
git add .github/workflows/stress-registry-check.yml
git commit -m "ci: enable STRESS registry check"
git push

# 3. Verify в GitHub — Actions tab трябва да показва нов workflow.
```

### Какво проверява

При PR / push към main който пипа:
- `tools/stress/scenarios/**`
- `tools/stress/sync_registries.py`
- `tools/stress/sync_board_progress.py`
- `STRESS_SCENARIOS.md`
- `STRESS_BOARD.md`

Workflow-ът пуска `--check` режим на двата sync скрипта. Ако някоя от
auto-секциите е out of sync (т.е. някой добави scenario JSON без да
пусне `--update`) → CI fail с подсказка как да поправиш.
