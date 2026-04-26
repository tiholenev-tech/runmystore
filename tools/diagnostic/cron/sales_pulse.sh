#!/bin/bash
set -e
LOG=/var/log/runmystore/sales_pulse_$(date +%Y%m%d).log
mkdir -p /var/log/runmystore
exec >> "$LOG" 2>&1
echo "═══ sales_pulse $(date '+%Y-%m-%d %H:%M:%S') ═══"
cd /var/www/runmystore || exit 1
python3 tools/diagnostic/cron/sales_pulse.py
RC=$?
echo "Exit code: $RC"
exit $RC
