#!/bin/bash
set -e
LOG=/var/log/runmystore/diag_weekly_$(date +%Y%m%d).log
mkdir -p /var/log/runmystore
exec >> "$LOG" 2>&1
echo "═══ diagnostic_weekly $(date '+%Y-%m-%d %H:%M:%S') ═══"
cd /var/www/runmystore || exit 1
python3 tools/diagnostic/run_diag.py --module=insights --trigger=cron_weekly --pristine
RC=$?
echo "Exit code: $RC"
exit $RC
