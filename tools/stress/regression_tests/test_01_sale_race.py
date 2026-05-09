#!/usr/bin/env python3
"""Bugfix 1 — sale.php race condition. ARCHIVAL (вече приложено в S97.HARDEN.PH1).

Verification: проверява инвариант — никога inventory.quantity < 0.
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
        return {"fix_id": "01_sale_race", "status": "skip",
                "evidence": "STRESS Lab tenant not found"}
    assert_stress_tenant(tenant_id, conn)

    with conn.cursor() as cur:
        cur.execute("""
            SELECT product_id, store_id, quantity FROM inventory
            WHERE quantity < 0 LIMIT 5
        """)
        negatives = cur.fetchall()

    if negatives:
        return {"fix_id": "01_sale_race", "status": "fail",
                "evidence": f"Found {len(negatives)} negative inventory rows: {negatives}"}
    return {"fix_id": "01_sale_race", "status": "pass",
            "evidence": "0 inventory rows with quantity < 0 — race fix holding"}


if __name__ == "__main__":
    print(run())
