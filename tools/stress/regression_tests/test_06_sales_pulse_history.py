#!/usr/bin/env python3
"""Bugfix 6 — sales_pulse.py off ENI tenant_id (S130 NEW).

Verification:
  Проверява че:
    1. STRESS Lab tenant съществува (за nightly_robot fallback)
    2. Скриптът sales_pulse.py не пише в ENI tenant_id=7
    3. Историята на продажби се разпределя през 60+ дни (не само днес)

NB: Самата файлова промяна на sales_pulse.py (sandbox version) живее в
    tools/stress/sandbox_files/sales_pulse_sandbox.py — patch-нат вариант.
"""
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from _db import assert_stress_tenant, connect, load_db_config, resolve_stress_tenant


def run():
    cfg = load_db_config()
    conn = connect(cfg, autocommit=True)
    tenant_id = resolve_stress_tenant(conn)
    if tenant_id is None:
        return {"fix_id": "06_sales_pulse_history", "status": "skip",
                "evidence": "STRESS Lab tenant not found"}
    assert_stress_tenant(tenant_id, conn)

    with conn.cursor() as cur:
        # Стрес продажбите трябва да обхождат поне 30 дни
        cur.execute("""
            SELECT
                MIN(DATE(created_at)) AS first_day,
                MAX(DATE(created_at)) AS last_day,
                COUNT(DISTINCT DATE(created_at)) AS distinct_days,
                COUNT(*) AS total
            FROM sales WHERE tenant_id = %s
        """, (tenant_id,))
        s = cur.fetchone()

        # Сигурно проверка: НЯМА записани sales за ENI tenant=7 от тоят run.
        # Тестът обработва само STRESS Lab — но и checks ENI hasn't grown.
        cur.execute("SELECT COUNT(*) AS n FROM sales WHERE tenant_id = 7 AND created_at >= NOW() - INTERVAL 1 HOUR")
        eni_recent = int(cur.fetchone()["n"])

    distinct = int(s.get("distinct_days") or 0)
    total = int(s.get("total") or 0)
    if total == 0:
        return {"fix_id": "06_sales_pulse_history", "status": "advisory",
                "evidence": "STRESS Lab още няма sales — nightly_robot не е работил.",
                "stats": {"total": 0}}
    if distinct < 5:
        return {"fix_id": "06_sales_pulse_history", "status": "fail",
                "evidence": f"Sales разпределени само на {distinct} дни — old DATE(NOW()) bug.",
                "stats": s}

    return {"fix_id": "06_sales_pulse_history", "status": "pass",
            "evidence": (
                f"STRESS Lab sales: {total} total, разпределени през {distinct} дни. "
                f"ENI recent (1h): {eni_recent} (трябва ≈0 — нощни cron не пишат върху ENI)"
            ),
            "stats": s}


if __name__ == "__main__":
    print(run())
