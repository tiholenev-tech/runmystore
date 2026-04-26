#!/bin/bash
set -e
LOG=/var/log/runmystore/daily_summary_$(date +%Y%m%d).log
mkdir -p /var/log/runmystore
exec >> "$LOG" 2>&1
echo "═══ daily_summary $(date '+%Y-%m-%d %H:%M:%S') ═══"
cd /var/www/runmystore || exit 1
python3 tools/diagnostic/cron/daily_summary.py
RC=$?
echo "Exit code: $RC"
exit $RC
