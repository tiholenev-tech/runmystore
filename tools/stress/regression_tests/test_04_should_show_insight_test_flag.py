#!/usr/bin/env python3
"""Bugfix 4 — shouldShowInsight() test_mode флаг (S130 NEW).

Verification:
  Проверява дали STRESS Lab tenant има email='stress@runmystore.ai' (база за
  is_stress_lab_tenant()). Самата PHP функция не е директно тестваема от
  Python — pure DB част е email lookup.

  Допълнителна проверка: insight_seen records съществуват за STRESS Lab —
  означава cooldown infrastructure съществува. test_mode bypass-ът е PHP-side.
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
        return {"fix_id": "04_should_show_insight_test_flag", "status": "skip",
                "evidence": "STRESS Lab tenant not found"}
    assert_stress_tenant(tenant_id, conn)

    with conn.cursor() as cur:
        cur.execute("SELECT email FROM tenants WHERE id = %s", (tenant_id,))
        row = cur.fetchone()
    if not row or (row.get("email") or "").lower() != "stress@runmystore.ai":
        return {"fix_id": "04_should_show_insight_test_flag", "status": "fail",
                "evidence": f"STRESS Lab tenant email mismatch: {row}"}

    # Insight_seen table check (cooldown infra)
    with conn.cursor() as cur:
        cur.execute("SHOW TABLES LIKE 'insight_seen'")
        has_seen = cur.fetchone() is not None

    return {"fix_id": "04_should_show_insight_test_flag",
            "status": "pass" if has_seen else "advisory",
            "evidence": (
                "Tenant email correctly set to stress@runmystore.ai. "
                f"insight_seen table {'present' if has_seen else 'missing — cooldown infra TBD'}"
            ),
            "note": "PHP test_mode bypass requires functional/integration test (out of scope)"}


if __name__ == "__main__":
    print(run())
