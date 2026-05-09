#!/usr/bin/env python3
"""
tools/stress/seed_suppliers.py

Етап 1 — Стъпка 3: 11 доставчика по STRESS_TENANT_SEED.md §"11 ДОСТАВЧИКА".

  1  Дафи         Дамско бельо         MOQ 50,  lead_time 5,   reliability 8
  2  Ивон         Дамско бельо premium MOQ 30,  lead_time 10,  reliability 9
  3  Статера      Дамско бельо         MOQ 100, lead_time 7,   reliability 7
  4  Lord         Мъжко бельо          MOQ 50,  lead_time 7,   reliability 8
  5  Royal Tiger  Мъжко бельо (внос)   MOQ 30,  lead_time 14,  reliability 6
  6  Диекс        Мъжко бельо          MOQ 50,  lead_time 5,   reliability 7
  7  Петков       Пижами Ж/М           MOQ 20,  lead_time 7,   reliability 8
  8  Пико         Пижами дамски        MOQ 40,  lead_time 5,   reliability 9
  9  Иватекс      Пижами дамски        MOQ 50,  lead_time 7,   reliability 7
  10 Ареал        Микс                 MOQ 30,  lead_time 10,  reliability 6
  11 Sonic        Чорапи               MOQ 100, lead_time 3,   reliability 9

Всеки доставчик с разл payment_terms (NET 30/60/prepaid) и 5-10% intentional
late shipments (виж seed_history_90days.py за late_delivery generator).

Idempotent.
"""

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _db import (
    assert_stress_tenant,
    connect,
    dry_run_log,
    load_db_config,
    resolve_stress_tenant,
    seed_rng,
)


SUPPLIERS = [
    {"name": "Дафи",        "category": "Дамско бельо",     "moq": 50,  "lead_time_days": 5,  "reliability_score": 8, "payment_terms": "NET 30"},
    {"name": "Ивон",        "category": "Дамско бельо",     "moq": 30,  "lead_time_days": 10, "reliability_score": 9, "payment_terms": "prepaid",  "premium": True},
    {"name": "Статера",     "category": "Дамско бельо",     "moq": 100, "lead_time_days": 7,  "reliability_score": 7, "payment_terms": "NET 60"},
    {"name": "Lord",        "category": "Мъжко бельо",      "moq": 50,  "lead_time_days": 7,  "reliability_score": 8, "payment_terms": "NET 30"},
    {"name": "Royal Tiger", "category": "Мъжко бельо",      "moq": 30,  "lead_time_days": 14, "reliability_score": 6, "payment_terms": "prepaid",  "import": True},
    {"name": "Диекс",       "category": "Мъжко бельо",      "moq": 50,  "lead_time_days": 5,  "reliability_score": 7, "payment_terms": "NET 30"},
    {"name": "Петков",      "category": "Пижами (Ж+М)",     "moq": 20,  "lead_time_days": 7,  "reliability_score": 8, "payment_terms": "NET 30"},
    {"name": "Пико",        "category": "Пижами (дамски)",  "moq": 40,  "lead_time_days": 5,  "reliability_score": 9, "payment_terms": "NET 30"},
    {"name": "Иватекс",     "category": "Пижами (дамски)",  "moq": 50,  "lead_time_days": 7,  "reliability_score": 7, "payment_terms": "NET 60"},
    {"name": "Ареал",       "category": "Микс (бельо/пижами/детско)", "moq": 30, "lead_time_days": 10, "reliability_score": 6, "payment_terms": "NET 30"},
    {"name": "Sonic",       "category": "Чорапи",           "moq": 100, "lead_time_days": 3,  "reliability_score": 9, "payment_terms": "prepaid",  "fast": True},
]


def discover_columns(conn, table: str) -> set[str]:
    with conn.cursor() as cur:
        cur.execute(f"SHOW COLUMNS FROM {table}")
        return {row["Field"] for row in cur.fetchall()}


def existing_supplier_names(conn, tenant_id: int) -> set[str]:
    with conn.cursor() as cur:
        cur.execute("SELECT name FROM suppliers WHERE tenant_id = %s", (tenant_id,))
        return {row["name"] for row in cur.fetchall()}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tenant", type=int, default=None)
    ap.add_argument("--apply", action="store_true")
    args = ap.parse_args()
    seed_rng()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=False)
    tenant_id = args.tenant if args.tenant else resolve_stress_tenant(conn)
    if tenant_id is None:
        sys.exit("[REFUSE] STRESS Lab tenant не съществува.")
    assert_stress_tenant(tenant_id, conn)

    cols = discover_columns(conn, "suppliers")
    existing = existing_supplier_names(conn, tenant_id)

    plan = {"tenant_id": tenant_id, "suppliers": [], "skipped": []}
    inserts = []

    for s in SUPPLIERS:
        if s["name"] in existing:
            plan["skipped"].append(s["name"])
            continue
        row = {"tenant_id": tenant_id, "name": s["name"]}
        if "category" in cols:
            row["category"] = s["category"]
        if "moq" in cols:
            row["moq"] = s["moq"]
        if "lead_time_days" in cols:
            row["lead_time_days"] = s["lead_time_days"]
        elif "lead_time" in cols:
            row["lead_time"] = s["lead_time_days"]
        if "reliability_score" in cols:
            row["reliability_score"] = s["reliability_score"]
        if "payment_terms" in cols:
            row["payment_terms"] = s["payment_terms"]
        if "metadata" in cols:
            extra = {k: v for k, v in s.items() if k not in row}
            row["metadata"] = json.dumps(extra, ensure_ascii=False)
        if "is_active" in cols:
            row["is_active"] = 1
        plan["suppliers"].append(row)
        inserts.append(row)

    print(f"[PLAN] tenant_id={tenant_id} — {len(inserts)} нови доставчика, {len(plan['skipped'])} skipped.")
    for r in inserts:
        print(f"  + {r['name']:14s} {r.get('category', '-')}")

    if not args.apply:
        out = dry_run_log("seed_suppliers", {"action": "dry-run", "plan": plan})
        print(f"[DRY-RUN] План: {out}")
        return 0

    inserted = 0
    try:
        with conn.cursor() as cur:
            for row in inserts:
                fields = ", ".join(row.keys())
                placeholders = ", ".join(["%s"] * len(row))
                cur.execute(
                    f"INSERT INTO suppliers ({fields}) VALUES ({placeholders})",
                    list(row.values()),
                )
                inserted += 1
        conn.commit()
    except Exception as e:
        conn.rollback()
        sys.exit(f"[FAIL] INSERT провали (rollback изпълнен): {e}")

    print(f"[OK] Създадени {inserted} доставчика.")
    dry_run_log("seed_suppliers", {"action": "applied", "tenant_id": tenant_id, "inserted": inserted})
    return 0


if __name__ == "__main__":
    sys.exit(main() or 0)
