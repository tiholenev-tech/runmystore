#!/bin/bash
# Resume stress crons след manual fix на target scenario fail.
# Премахва disable файла, който daily_report_writer touch-ва при FAIL.
set -e
DISABLE=/etc/runmystore/stress.disabled
if [ -f "$DISABLE" ]; then
    rm -f "$DISABLE"
    echo "✓ Stress crons re-enabled (премахнат $DISABLE)."
else
    echo "ℹ️ Stress crons вече enabled — $DISABLE не съществува."
fi
echo "Cron-овете не са инсталирани в /etc/cron.d/ — manual run все още."
