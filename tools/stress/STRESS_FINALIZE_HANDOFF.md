# STRESS Finalize Handoff — S133

- **Дата:** 2026-05-09
- **Branch:** `s133-stress-finalize` (forked from `origin/main` at `e2a7936`)
- **Worktree used during this session:** `/var/www/rms-stress`
- **Sandbox DB created:** `runmystore_stress_sandbox`
- **Production DB touched:** **0**. Verified at every phase: `runmystore` has 0 rows for `tenants.email='stress@runmystore.ai'` and 0 rows for `users.email LIKE '%stress.lab'`.
- **ENI tenant_id=7:** removed from sandbox; 0 residue across all 54 `tenant_id`-scoped tables.

## What was tested in sandbox

| Phase | What ran | Result |
|---|---|---|
| A1-A2 | mysqldump production → /tmp baseline (2.7 MB, 73 tables) | OK |
| A3-A5 | Sandbox DB created; ENI cleanup; placeholder id=999 inserted | OK (47 tenants total — see deviation below) |
| B1 | `setup_stress_tenant.py --apply` → STRESS Lab tenant id=1000 | OK (after B0 fix) |
| B2-B6 | `seed_stores` (8), `seed_suppliers` (11), `seed_users` (5), `seed_products_realistic` (3031 + 3031 inventory), `seed_history_90days` (57025 sales × 95357 items × 90 distinct days) | OK (after B0a/B0b fixes) |
| B7 | Final validation: STRESS Lab counts, ENI residue=0, production untouched | OK |
| C | `regression_tests/runner.py` — 6 tests, 3 ran (after applying s130_03/s130_05) | 3 pass, 1 advisory, 2 fail. See `data/REGRESSION_REPORT.md` |
| D | Bugfix patches verified (read-only, since target files are on ABSOLUTE NO list and no sandbox copies exist) | See `data/BUGFIX_VERIFY_REPORT.md` |
| E | `nightly_robot.py` dry-run | 75 scenarios + 745 actions, 0 errors. See `data/NIGHTLY_DRY_RUN_REPORT.md` |
| F | `balance_validator.py` dry-run (movements + aggregate) | movements crashes on `quantity_after` schema mismatch; aggregate trivially passes (empty stock_movements). See `data/BALANCE_VALIDATOR_REPORT.md` |
| H | `sanity_checker.py` dry-run | 0 failures (vacuous — same root cause as F). See `data/SANITY_CHECK_REPORT.md` |

## Commits on this branch

```
b8862c6 S133.STRESS.B0b: seed_history_90days.py — drop products.category from SELECT
d9023a6 S133.STRESS.B0a: seed_users.py — handle password column too
8b40b09 S133.STRESS.B0:  schema-mismatch fixes in seed scripts
4b53596 S133.STRESS.A0:  _db.py — honor DB_NAME env override for sandbox redirection
```

(Plus the two pre-existing `mirrors: auto-sync ...` commits.)

The CRON_INSTALL_GUIDE and this handoff are committed under separate G and I commits (see `git log` after the final push).

## What's READY for production

| Item | Apply command | Notes |
|---|---|---|
| `_db.py` DB_NAME env override | merge `s133-stress-finalize` to main | Required by everything else |
| Seed-script schema fixes | merge `s133-stress-finalize` to main | Make Phase B reproducible from scratch |
| `s130_05_urgency_limits.up.sql` | `mysql … runmystore < tools/stress/sql/s130_05_urgency_limits.up.sql` | Clean idempotent migration; safe to ship |

## What's PENDING (NOT done in this session)

| Item | Why deferred | Suggested next step |
|---|---|---|
| Telegram bot activation | Brief said "пропусни Phase M2 alerts integration (post-beta)" | Configure `tools/stress/alerts/telegram_bot.py` env after this session |
| Cron install in `/etc/cron.d/` | Brief said "ZERO install в /etc/cron.d/" | Follow `tools/stress/CRON_INSTALL_GUIDE.md` step-by-step. **Critically — create `/etc/runmystore/cron.env` first** with `DB_NAME=runmystore_stress_sandbox`, otherwise nightly_robot writes to production |
| Production apply of `s130_03` | Migration not idempotent on this schema (DROP INDEX on a name that doesn't exist) | Patch the migration to detect index name + use prepared-statement DROP, then ship |
| Production apply of patches 04/05 PHP-side | Target files (chat.php, compute-insights.php, helpers.php) on ABSOLUTE NO list | Schedule a separate session focused on PHP review |
| `tools/diagnostic/cron/sales_pulse.py` cleanup | Patch 06 deprecated by `nightly_robot.py` | `git rm` cleanup commit on a separate branch |
| Manual chat.php rewrite (S133 P11) | Your active work on `s133-chat-rewrite` — explicitly out of scope here | Continue separately |
| Production hash for STRESS Lab tenant + per-user passwords | Generated as plaintext during seed; placed in seed log files in `/tmp/stress_finalize_*/` | Hash with `password_hash()` and write to `/etc/runmystore/stress.env` (chmod 600) |

## Known issues (P0)

Detailed in `tools/stress/data/SANITY_CHECK_REPORT.md`. Quick recap:

| # | Issue | Fixed this session |
|---|---|---|
| 1 | `_db.py` DB_NAME override | ✅ |
| 2 | `setup_stress_tenant` password column | ✅ |
| 3 | `seed_users` role + password | ✅ |
| 4 | `seed_history` products.category | ✅ |
| 5 | `s130_03` migration idempotency | ❌ |
| 6 | `seed_history` doesn't write stock_movements / deliveries / transfers | ❌ |
| 7 | `balance_validator` `quantity_after` crash | ❌ |
| 8 | `seed_history` allows negative inventory (112 rows) | ❌ |
| 9 | `test_02` queries non-existent `status` column | ❌ |

## Sandbox state at handoff

Useful for the next session — sandbox is hot and ready for nightly_robot --apply or further test work.

```sql
-- in runmystore_stress_sandbox:
-- tenant 1000 = STRESS Lab (stress@runmystore.ai)
-- 8 stores, 11 suppliers, 5 users (passwords in /tmp/stress_finalize_*/)
-- 3031 products + inventory
-- 57025 sales, 95357 sale_items, spanning 90 distinct days (2026-02-08 → 2026-05-08)
-- 0 stock_movements / 0 deliveries / 0 transfers (TODO in seed)
-- s130_03 + s130_05 migrations applied (s130_03 needed manual repair)
```

## Deviations from the brief

1. **A3 cleanup scope** — brief read literally as "DELETE WHERE id=7 + add placeholder", but A4 said "0-1 placeholder tenant total". I did the literal A3 (narrow). Sandbox keeps 46 non-ENI production tenants + 1 placeholder = 47 total. If you actually wanted aggressive cleanup, I'm happy to extend in a follow-up.
2. **Phase D apply step** — patches in `sandbox_files/patches/` are doc-style diffs (not git-applicable) and target files on the session's ABSOLUTE NO list. There are no sandbox-copy files. So Phase D became a verification report rather than apply-and-rerun-tests cycle.
3. **`balance_validator.py` --target=sandbox --readonly** — script doesn't accept those flags. Used the equivalent (`--mode aggregate` + DB_NAME env override).

## How to push

When you're satisfied, push the branch yourself with:

```bash
cd /var/www/rms-stress     # or wherever the worktree lives
git push -u origin s133-stress-finalize
```

I deliberately didn't push — pushing is shared-state mutation that warrants explicit confirmation from you.

## Reverse plan (if you want to discard)

```bash
# In the production checkout, if the branch was already pushed:
# git push origin --delete s133-stress-finalize

# Locally:
git worktree remove /var/www/rms-stress     # cleans up the worktree
git branch -D s133-stress-finalize          # deletes the branch

# Remove sandbox DB:
sudo mysql -e "DROP DATABASE runmystore_stress_sandbox;"

# Remove perm exception you added at session start:
sudo chgrp www-data /etc/runmystore/db.env  # restore original group
sudo chown -R root:root /var/www/runmystore/tools/stress  # if you want it back to root
```
