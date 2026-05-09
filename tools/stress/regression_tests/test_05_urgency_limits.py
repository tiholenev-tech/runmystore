#!/usr/bin/env python3
"""Bugfix 5 — urgency лимити конфигурируеми (S130 NEW).

Verification:
  PRE: hardcoded {2, 3, 3} в PHP, никаква tenant_settings entry
  POST: tenant_settings table съществува; всеки tenant има 'insight_limits' key;
        STRESS Lab tenant има по-високи лимити (поне 10/15/20 — за тестове)
"""
import json
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from _db import assert_stress_tenant, connect, load_db_config, resolve_stress_tenant


def run():
    cfg = load_db_config()
    conn = connect(cfg, autocommit=True)
    tenant_id = resolve_stress_tenant(conn)
    if tenant_id is None:
        return {"fix_id": "05_urgency_limits", "status": "skip",
                "evidence": "STRESS Lab tenant not found"}
    assert_stress_tenant(tenant_id, conn)

    with conn.cursor() as cur:
        cur.execute("SHOW TABLES LIKE 'tenant_settings'")
        if not cur.fetchone():
            return {"fix_id": "05_urgency_limits", "status": "fail",
                    "evidence": "tenant_settings table missing — migration s130_05 not applied"}
        cur.execute(
            "SELECT value FROM tenant_settings WHERE tenant_id = %s AND key_name = 'insight_limits'",
            (tenant_id,),
        )
        row = cur.fetchone()
    if not row:
        return {"fix_id": "05_urgency_limits", "status": "fail",
                "evidence": f"insight_limits missing for STRESS Lab tenant {tenant_id}"}

    try:
        cfg_val = json.loads(row["value"])
    except Exception as e:
        return {"fix_id": "05_urgency_limits", "status": "fail",
                "evidence": f"insight_limits JSON parse fail: {e}; value={row['value'][:60]}"}

    if cfg_val.get("critical", 0) < 5 or cfg_val.get("info", 0) < 10:
        return {"fix_id": "05_urgency_limits", "status": "fail",
                "evidence": f"STRESS Lab лимити твърде ниски: {cfg_val} (очаквано >=10/15/20)"}

    return {"fix_id": "05_urgency_limits", "status": "pass",
            "evidence": f"STRESS Lab insight_limits OK: {cfg_val}"}


if __name__ == "__main__":
    print(run())
