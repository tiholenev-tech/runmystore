#!/usr/bin/env python3
"""Bugfix 3 — ai_insights UNIQUE relax (S130 NEW).

Verification:
  PRE: ai_insights има UNIQUE на (tenant_id, store_id, topic_id) — само 1 запис per topic
  POST: ai_insights има UNIQUE на (tenant_id, store_id, topic_id, created_at_bucket)
        + created_at_bucket колона
        → позволява нови entries в различни дни
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
        return {"fix_id": "03_ai_insights_unique", "status": "skip",
                "evidence": "STRESS Lab tenant not found"}
    assert_stress_tenant(tenant_id, conn)

    with conn.cursor() as cur:
        cur.execute("SHOW COLUMNS FROM ai_insights LIKE 'created_at_bucket'")
        has_bucket = cur.fetchone() is not None
        cur.execute("SHOW INDEX FROM ai_insights")
        indexes = cur.fetchall()

    bucket_unique = any(i.get("Key_name") == "uniq_tenant_store_topic_day" for i in indexes)
    old_unique = any(i.get("Key_name") == "uniq_tenant_store_topic" for i in indexes)

    if not has_bucket:
        return {"fix_id": "03_ai_insights_unique", "status": "fail",
                "evidence": "created_at_bucket column missing — migration s130_03 not applied"}
    if not bucket_unique:
        return {"fix_id": "03_ai_insights_unique", "status": "fail",
                "evidence": "uniq_tenant_store_topic_day index missing"}
    if old_unique:
        return {"fix_id": "03_ai_insights_unique", "status": "fail",
                "evidence": "old uniq_tenant_store_topic still present — should be dropped"}
    return {"fix_id": "03_ai_insights_unique", "status": "pass",
            "evidence": "bucket col + new uniq present, old uniq dropped"}


if __name__ == "__main__":
    print(run())
