#!/usr/bin/env python3
"""
daily_summary.py — изпраща дневен email 08:30 БГ.
"""

import sys
sys.path.insert(0, '/var/www/runmystore')
from tools.diagnostic.core.db_helpers import fetchone, get_notify_config
from tools.diagnostic.core.alert_sender import send_email_summary


def get_last_run():
    return fetchone("""
        SELECT *
        FROM diagnostic_log
        ORDER BY run_timestamp DESC
        LIMIT 1
    """)


def get_previous_run(current_id):
    if not current_id:
        return None
    return fetchone("""
        SELECT *
        FROM diagnostic_log
        WHERE id < %s
        ORDER BY id DESC
        LIMIT 1
    """, (current_id,))


def count_sales_today(tenant_id=7):
    r = fetchone("""
        SELECT COUNT(*) AS cnt
        FROM sales
        WHERE tenant_id = %s
          AND DATE(created_at) = CURDATE()
    """, (tenant_id,))
    return int(r['cnt']) if r else 0


def main():
    last = get_last_run()
    prev = get_previous_run(last['id']) if last else None
    sales_today = count_sales_today(7)

    cfg = get_notify_config()
    if not cfg.get('email'):
        print("NOTIFY_EMAIL не е configured — пропускам")
        return 0

    ok, msg = send_email_summary(last, previous=prev, sales_today=sales_today)
    print("email_sent=" + str(ok) + ", msg=" + str(msg))
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
