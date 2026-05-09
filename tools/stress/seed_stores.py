#!/usr/bin/env python3
"""
tools/stress/seed_stores.py

Етап 1 — Стъпка 2: 8 локации в STRESS Lab tenant.

Точно по спецификацията STRESS_TENANT_SEED.md §"8 ЛОКАЦИИ":

  1 Склад                       — warehouse, голям
  2 Магазин дрехи               — fashion-only, среден
  3 Магазин обувки              — shoes-only, среден
  4 Магазин mixed               — дрехи+обувки+аксесоари
  5 Магазин high-volume         — МОЛ, 200-400 продажби/ден
  6 Магазин бижута              — висок марж, малки бройки, AI hallucination риск
  7 Магазин домашни потреби     — голям асортимент 3000+
  8 Онлайн магазин              — Ecwid симулация

Idempotent: ако магазин с дадено име вече съществува — пропуска (не дублира).

Usage:
    python3 seed_stores.py --tenant <id> --dry-run   # default
    python3 seed_stores.py --tenant <id> --apply
"""

import argparse
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


STORES = [
    {
        "name": "Склад",
        "type": "warehouse",
        "address": "ул. „Тестова“ 1, София",
        "size": "large",
        "metadata": {
            "daily_deliveries": [5, 10],
            "daily_outgoing_transfers": [20, 30],
            "daily_wholesale_sales": [10, 20],
            "daily_retail_sales": [30, 50],
            "open": "08:00",
            "close": "18:00",
        },
    },
    {
        "name": "Магазин дрехи",
        "type": "retail-fashion",
        "address": "ул. „Витоша“ 25, София",
        "size": "medium",
        "metadata": {
            "daily_sales": [60, 80],
            "avg_basket_eur": [40, 70],
            "margin_pct": [50, 60],
            "seasonality": "high",
            "open": "10:00",
            "close": "20:00",
            "peak_hours": "16:00-19:00",
            "return_rate": [8, 12],
        },
    },
    {
        "name": "Магазин обувки",
        "type": "retail-shoes",
        "address": "ул. „Раковски“ 50, София",
        "size": "medium",
        "metadata": {
            "daily_sales": [40, 60],
            "avg_basket_eur": [60, 100],
            "margin_pct": [45, 55],
            "seasonality": "high",
            "open": "10:00",
            "close": "20:00",
            "return_rate": [12, 15],
            "size_matrix": True,
        },
    },
    {
        "name": "Магазин mixed",
        "type": "retail-mixed",
        "address": "ул. „Граф Игнатиев“ 10, София",
        "size": "medium",
        "metadata": {
            "daily_sales": [80, 120],
            "avg_basket_eur": [50, 80],
            "margin_pct": [50, 60],
            "seasonality": "medium",
            "cross_category_recommendations": True,
        },
    },
    {
        "name": "Магазин high-volume",
        "type": "retail-highvolume",
        "address": "МОЛ Парадайс, София",
        "size": "large",
        "metadata": {
            "daily_sales": [200, 400],
            "avg_basket_eur": [25, 40],
            "margin_pct": [30, 40],
            "seasonality": "low",
            "open": "10:00",
            "close": "22:00",
            "race_condition_test_target": True,
        },
    },
    {
        "name": "Магазин бижута",
        "type": "retail-jewelry",
        "address": "ул. „Гурко“ 5, София",
        "size": "small",
        "metadata": {
            "daily_sales": [5, 15],
            "avg_basket_eur": [100, 500],
            "margin_pct": [50, 70],
            "seasonality": "holiday",
            "ai_hallucination_risk": "high",
            "small_quantities_per_sku": [1, 3],
        },
    },
    {
        "name": "Магазин домашни потреби",
        "type": "retail-homegoods",
        "address": "Промишлена зона Изток, София",
        "size": "large",
        "metadata": {
            "daily_sales": [30, 50],
            "avg_basket_eur": [15, 30],
            "margin_pct": [25, 35],
            "seasonality": "seasonal",
            "assortment_size": 3000,
            "lost_demand_target": True,
        },
    },
    {
        "name": "Онлайн магазин",
        "type": "online",
        "address": "N/A",
        "size": "online",
        "metadata": {
            "daily_orders": [20, 40],
            "avg_basket_eur": [60, 100],
            "return_rate": [15, 20],
            "night_sales": "22:00-02:00",
            "black_friday_multiplier": 5,
        },
    },
]


def discover_columns(conn, table: str) -> set[str]:
    with conn.cursor() as cur:
        cur.execute(f"SHOW COLUMNS FROM {table}")
        return {row["Field"] for row in cur.fetchall()}


def existing_store_names(conn, tenant_id: int) -> set[str]:
    with conn.cursor() as cur:
        cur.execute("SELECT name FROM stores WHERE tenant_id = %s", (tenant_id,))
        return {row["name"] for row in cur.fetchall()}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tenant", type=int, default=None,
                    help="Tenant id (по default — резолва STRESS Lab по email)")
    ap.add_argument("--apply", action="store_true")
    args = ap.parse_args()
    seed_rng()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=False)

    tenant_id = args.tenant if args.tenant else resolve_stress_tenant(conn)
    if tenant_id is None:
        sys.exit("[REFUSE] STRESS Lab tenant не съществува. Изпълни setup_stress_tenant.py първо.")
    assert_stress_tenant(tenant_id, conn)

    cols = discover_columns(conn, "stores")
    existing = existing_store_names(conn, tenant_id)

    plan = {"tenant_id": tenant_id, "stores": [], "skipped": []}
    inserts = []

    import json as _json
    for store in STORES:
        if store["name"] in existing:
            plan["skipped"].append(store["name"])
            continue
        row = {"tenant_id": tenant_id, "name": store["name"]}
        if "type" in cols:
            row["type"] = store["type"]
        if "address" in cols:
            row["address"] = store["address"]
        if "size" in cols:
            row["size"] = store["size"]
        if "metadata" in cols:
            row["metadata"] = _json.dumps(store["metadata"], ensure_ascii=False)
        if "is_active" in cols:
            row["is_active"] = 1
        if "status" in cols:
            row["status"] = "active"
        plan["stores"].append(row)
        inserts.append(row)

    print(f"[PLAN] tenant_id={tenant_id} — {len(inserts)} нови магазина, {len(plan['skipped'])} вече съществуват.")
    for r in inserts:
        print(f"  + {r['name']:30s} ({r.get('type', '-')})")
    for s in plan["skipped"]:
        print(f"  = {s} (skip — вече съществува)")

    if not args.apply:
        out = dry_run_log("seed_stores", {"action": "dry-run", "plan": plan})
        print(f"[DRY-RUN] План: {out}")
        return 0

    inserted = 0
    try:
        with conn.cursor() as cur:
            for row in inserts:
                fields = ", ".join(row.keys())
                placeholders = ", ".join(["%s"] * len(row))
                sql = f"INSERT INTO stores ({fields}) VALUES ({placeholders})"
                cur.execute(sql, list(row.values()))
                inserted += 1
        conn.commit()
    except Exception as e:
        conn.rollback()
        sys.exit(f"[FAIL] INSERT провали (rollback изпълнен): {e}")

    print(f"[OK] Създадени {inserted} магазина за tenant_id={tenant_id}.")
    dry_run_log("seed_stores", {"action": "applied", "tenant_id": tenant_id, "inserted": inserted})
    return 0


if __name__ == "__main__":
    sys.exit(main() or 0)
