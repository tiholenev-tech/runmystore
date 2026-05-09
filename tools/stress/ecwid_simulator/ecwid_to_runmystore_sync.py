#!/usr/bin/env python3
"""
tools/stress/ecwid_simulator/ecwid_to_runmystore_sync.py

Phase L3 (S130 extension). Чете spool-нати Ecwid поръчки от
data/ecwid_orders/*.json и ги превръща в `sales` + `inventory_events`
записи на STRESS Lab tenant.

ABSOLUTE GUARDS:
  * Само върху STRESS Lab (assert_stress_tenant).
  * --dry-run по default — нищо не пише, само показва план.
  * Refund job: за PAID поръчки симулира 15-20% return rate
    (post-order refund запис) — управлява се чрез --returns.

Status mapping (Ecwid → runmystore):
  PAID                 → sales.status='completed'
  PROCESSING           → sales.status='pending'
  CANCELLED            → не се записва (clean drop)
  PAYMENT_FAIL         → sales.status='payment_failed' (S066)
  PARTIALLY_FULFILLED  → sales.status='partial' (S067)
  AWAITING_PICKUP      → sales.status='awaiting_pickup' (S069)

Usage:
    python3 ecwid_to_runmystore_sync.py --dry-run
    python3 ecwid_to_runmystore_sync.py --apply --date 2026-05-09
    python3 ecwid_to_runmystore_sync.py --apply --returns
"""

import argparse
import json
import random
import sys
from datetime import datetime
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from _db import (
    assert_stress_tenant,
    connect,
    dry_run_log,
    load_db_config,
    resolve_stress_tenant,
    seed_rng,
)

SPOOL_DIR = Path(__file__).resolve().parent.parent / "data" / "ecwid_orders"

STATUS_MAP = {
    "PAID":                "completed",
    "PROCESSING":          "pending",
    "PAYMENT_FAIL":        "payment_failed",
    "PARTIALLY_FULFILLED": "partial",
    "AWAITING_PICKUP":     "awaiting_pickup",
}
SKIP_STATUSES = {"CANCELLED"}

REFUND_RATE = (0.15, 0.20)


def find_spool_file(target_date: datetime | None) -> list[Path]:
    if not SPOOL_DIR.exists():
        return []
    if target_date is None:
        return sorted(SPOOL_DIR.glob("orders_*.json"))
    name = f"orders_{target_date.strftime('%Y%m%d')}.json"
    p = SPOOL_DIR / name
    return [p] if p.exists() else []


def load_orders(paths: list[Path]) -> list[dict]:
    out = []
    for p in paths:
        try:
            with open(p) as f:
                data = json.load(f)
            for o in data.get("orders", []):
                o["_source_file"] = str(p)
                out.append(o)
        except Exception as e:
            print(f"[WARN] {p}: {e}", file=sys.stderr)
    return out


def discover_columns(conn, table: str) -> set[str]:
    try:
        with conn.cursor() as cur:
            cur.execute(f"SHOW COLUMNS FROM {table}")
            return {row["Field"] for row in cur.fetchall()}
    except Exception:
        conn.rollback()
        return set()


def insert_sale(conn, order: dict, sale_cols: set[str], item_cols: set[str],
                evt_cols: set[str]) -> dict:
    """Insert един sale + sale_items + inventory_events. Връща summary."""
    placed_at = order["placed_at"]
    rms_status = STATUS_MAP[order["status"]]
    sale_payload = {
        "tenant_id": order["tenant_id"],
        "status": rms_status,
        "total": order["total"],
        "subtotal": order["subtotal"],
        "discount": order["discount"],
        "shipping": order["shipping"],
        "payment_method": "online",
        "channel": "ecwid",
        "external_order_id": order["order_id"],
        "customer_email": order["customer"]["email"],
        "customer_name": (order["customer"]["first_name"]
                          + " " + order["customer"]["last_name"]),
        "created_at": placed_at,
        "updated_at": placed_at,
        "notes": f"order_type={order['type']} synthetic=1",
    }
    payload = {k: v for k, v in sale_payload.items() if k in sale_cols}
    cols = ", ".join(payload.keys())
    placeholders = ", ".join(["%s"] * len(payload))
    sale_id = None
    with conn.cursor() as cur:
        cur.execute(f"INSERT INTO sales ({cols}) VALUES ({placeholders})",
                    tuple(payload.values()))
        sale_id = cur.lastrowid

    items_inserted = 0
    inv_events = 0
    for li in order["line_items"]:
        item_payload = {
            "sale_id": sale_id,
            "tenant_id": order["tenant_id"],
            "product_id": li["product_id"],
            "name": li["name"],
            "quantity": li["quantity"],
            "unit_price": li["unit_price"],
            "subtotal": li["subtotal"],
            "created_at": placed_at,
        }
        ip = {k: v for k, v in item_payload.items() if k in item_cols}
        if ip:
            cols_i = ", ".join(ip.keys())
            ph_i = ", ".join(["%s"] * len(ip))
            with conn.cursor() as cur:
                cur.execute(
                    f"INSERT INTO sale_items ({cols_i}) VALUES ({ph_i})",
                    tuple(ip.values()))
            items_inserted += 1

        if rms_status in ("completed", "partial") and evt_cols:
            evt_payload = {
                "tenant_id": order["tenant_id"],
                "product_id": li["product_id"],
                "delta_quantity": -int(li["quantity"]),
                "type": "online_sale",
                "source": "ecwid_simulator",
                "ref_id": sale_id,
                "created_at": placed_at,
                "notes": f"order={order['order_id']}",
            }
            ep = {k: v for k, v in evt_payload.items() if k in evt_cols}
            if ep:
                cols_e = ", ".join(ep.keys())
                ph_e = ", ".join(["%s"] * len(ep))
                try:
                    with conn.cursor() as cur:
                        cur.execute(
                            f"INSERT INTO inventory_events ({cols_e}) "
                            f"VALUES ({ph_e})",
                            tuple(ep.values()))
                    inv_events += 1
                except Exception as e:
                    print(f"[WARN] inventory_events insert: {e}",
                          file=sys.stderr)
                    conn.rollback()
                    return {"sale_id": sale_id, "items": items_inserted,
                            "inv_events": inv_events, "error": str(e)}
    conn.commit()
    return {"sale_id": sale_id, "items": items_inserted,
            "inv_events": inv_events}


def insert_refund(conn, sale_id: int, order: dict,
                  sale_cols: set[str], evt_cols: set[str]) -> dict:
    """Симулира refund за PAID поръчка (15-20% rate)."""
    if "refund_id" in sale_cols or sale_id is None:
        pass  # placeholder — ако имаш refunds таблица, тук добавяме

    try:
        with conn.cursor() as cur:
            cur.execute(
                "UPDATE sales SET status = 'refunded', updated_at = NOW() "
                "WHERE id = %s",
                (sale_id,))
        for li in order["line_items"]:
            if not evt_cols:
                continue
            evt = {
                "tenant_id": order["tenant_id"],
                "product_id": li["product_id"],
                "delta_quantity": int(li["quantity"]),
                "type": "online_refund",
                "source": "ecwid_simulator",
                "ref_id": sale_id,
                "created_at": datetime.now().isoformat(timespec="seconds"),
                "notes": f"refund of {order['order_id']}",
            }
            ep = {k: v for k, v in evt.items() if k in evt_cols}
            if not ep:
                continue
            cols_e = ", ".join(ep.keys())
            ph_e = ", ".join(["%s"] * len(ep))
            with conn.cursor() as cur:
                cur.execute(
                    f"INSERT INTO inventory_events ({cols_e}) VALUES ({ph_e})",
                    tuple(ep.values()))
        conn.commit()
        return {"sale_id": sale_id, "refunded": True}
    except Exception as e:
        conn.rollback()
        return {"sale_id": sale_id, "refunded": False, "error": str(e)}


def main():
    ap = argparse.ArgumentParser(description="Ecwid → runmystore sync (Phase L)")
    ap.add_argument("--apply", action="store_true",
                    help="Реално insert-ва в DB. Default = dry-run.")
    ap.add_argument("--date", type=str, default=None,
                    help="YYYY-MM-DD. Default = всички spool файлове.")
    ap.add_argument("--returns", action="store_true",
                    help="След sync, симулира 15-20%% refunds на PAID поръчки.")
    ap.add_argument("--tenant", type=int, default=None)
    args = ap.parse_args()
    seed_rng()

    target_date = datetime.strptime(args.date, "%Y-%m-%d") if args.date else None
    paths = find_spool_file(target_date)
    if not paths:
        print(f"[INFO] Няма spool файлове в {SPOOL_DIR}")
        return 0

    orders = load_orders(paths)
    print(f"[INFO] Заредени {len(orders)} поръчки от {len(paths)} spool файла")

    summary = {
        "files": [str(p) for p in paths],
        "loaded": len(orders),
        "to_insert": 0,
        "skipped": 0,
        "by_status": {},
    }
    for o in orders:
        s = o.get("status", "?")
        summary["by_status"][s] = summary["by_status"].get(s, 0) + 1
        if s in SKIP_STATUSES:
            summary["skipped"] += 1
        else:
            summary["to_insert"] += 1

    if not args.apply:
        out = dry_run_log("ecwid_to_runmystore_sync",
                          {"action": "dry-run", "summary": summary})
        print(f"[DRY-RUN] {summary['to_insert']} sales would be inserted, "
              f"{summary['skipped']} skipped (CANCELLED)")
        print(f"[DRY-RUN] By status: {summary['by_status']}")
        print(f"[DRY-RUN] План: {out}")
        return 0

    cfg = load_db_config()
    conn = connect(cfg, autocommit=False)
    tenant_id = args.tenant if args.tenant else resolve_stress_tenant(conn)
    if tenant_id is None:
        sys.exit("[REFUSE] STRESS Lab tenant не съществува")
    assert_stress_tenant(tenant_id, conn)

    sale_cols = discover_columns(conn, "sales")
    item_cols = discover_columns(conn, "sale_items")
    evt_cols = discover_columns(conn, "inventory_events")
    if not sale_cols:
        sys.exit("[REFUSE] sales table недостъпна")

    inserted = 0
    refunded = 0
    failed = 0
    for o in orders:
        if o.get("status") in SKIP_STATUSES:
            continue
        if o["tenant_id"] != tenant_id:
            o["tenant_id"] = tenant_id  # adopt
        try:
            res = insert_sale(conn, o, sale_cols, item_cols, evt_cols)
            inserted += 1
            if args.returns and o["status"] == "PAID":
                if random.random() < random.uniform(*REFUND_RATE):
                    rr = insert_refund(conn, res["sale_id"], o,
                                       sale_cols, evt_cols)
                    if rr.get("refunded"):
                        refunded += 1
        except Exception as e:
            failed += 1
            print(f"[WARN] {o['order_id']}: {e}", file=sys.stderr)
            conn.rollback()

    conn.close()

    final = {**summary, "inserted": inserted, "refunded": refunded,
             "failed": failed}
    dry_run_log("ecwid_to_runmystore_sync",
                {"action": "applied", "summary": final})
    print(f"[OK] inserted={inserted} refunded={refunded} failed={failed}")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
