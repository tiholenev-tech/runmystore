# SESSION S79.CRON_AUDIT -- HANDOFF

**Date:** 2026-04-22
**Chat:** CHAT 3 (Opus)
**Status:** DONE
**Tag:** v0.5.3-s79-cron-audit

---

## What was done

### Task 1 -- CRON SETUP for compute-insights.php

New files:
- cron-insights.php -- wrapper invoking computeProductInsights() for all active tenants
- /etc/cron.d/runmystore -- every 15 min\n- /var/log/runmystore-cron.log (644, www-data:adm)

New table: cron_heartbeats (job_name PK, last_run_at, last_status, last_error, last_duration_ms, expected_interval_minutes, updated_at)

Patch: compute-insights.php -- added COMPUTE_INSIGHTS_NO_CLI guard so require from cron wrapper doesn't auto-run.

### Task 2 -- audit_log extension (S79.AUDIT.EXT)

Migration: migrations/20260422_002_audit_log_extension.{up|down}.sql

audit_log changes:
- New cols: store_id, source (ENUM), source_detail, user_agent
- New indexes: idx_source, idx_store
- action ENUM expanded: cron_run, ai_action, system_event
- Backfill: existing rows set source='ui'

Helper auditLog() v2 (backwards compatible, new optional args source+ sourceDetail). Auto-picks user['store_id'] and $_SERVER['HTTP_USER_AGENT'].

---

## Test results (2026-04-22 13:49:07)

- 46 active tenants in 1153ms
- 13 insights (tenant 7: 10, 8: 1, 50: 1, 52: 1)
- Heartbeat: ok
- Audit summary: written

---

## How to test

Manual cron run:
```
sudo -u www-data php /var/www/runmystore/cron-insights.php
```

Heartbeat: SELECT * FROM cron_heartbeats;
Cron audit: SELECT * FROM audit_log WHERE source='cron' ORDER BY id DESC LIMIT 5;
Cron log: tail -f /var/log/runmystore-cron.log

---

## Git

- Commit 75e10fa
- Tag v0.5.3-s79-cron-audit

## Backups

- /root/backups/backup_s79_audit_20260422_1308.sql
- /root/backups/helpers_backup_20260422_1308.php

## REWORK QUEUE #10 -- DONE

## Notes

1. cron.service doesn't support reload -- /etc/cron.d/ is auto-read
2. Any wrapper of compute-insights.php must define('COMPUTE_INSIGHTS_NO_CLI',true)
3. PAT token regenerated with repo+workflow scope
