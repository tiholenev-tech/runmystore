#!/usr/bin/env python3
"""Bugfix 2 — compute-insights.php module hardcode. ARCHIVAL (S91 fix).

Verification: новите ai_insights records трябва да имат module='home' (не 'products')
за insights, които идват от 'home' source.
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
        return {"fix_id": "02_compute_insights_module", "status": "skip",
                "evidence": "STRESS Lab tenant not found"}
    assert_stress_tenant(tenant_id, conn)

    with conn.cursor() as cur:
        cur.execute("""
            SELECT module, COUNT(*) AS n FROM ai_insights
            WHERE tenant_id = %s AND status = 'live'
              AND created_at >= NOW() - INTERVAL 7 DAY
            GROUP BY module
        """, (tenant_id,))
        rows = cur.fetchall()
    distribution = {r["module"]: int(r["n"]) for r in rows}

    home = distribution.get("home", 0)
    products = distribution.get("products", 0)
    total = sum(distribution.values()) or 1
    home_pct = home / total * 100

    if total == 0:
        return {"fix_id": "02_compute_insights_module", "status": "skip",
                "evidence": "No live ai_insights to evaluate"}
    if home_pct < 50:
        return {"fix_id": "02_compute_insights_module", "status": "fail",
                "evidence": f"home pct={home_pct:.1f}% (< 50% — module routing broken)",
                "distribution": distribution}
    return {"fix_id": "02_compute_insights_module", "status": "pass",
            "evidence": f"home={home_pct:.1f}% of live insights — routing OK",
            "distribution": distribution}


if __name__ == "__main__":
    print(run())
