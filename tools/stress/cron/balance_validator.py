#!/usr/bin/env python3
"""
tools/stress/cron/balance_validator.py

Standalone подбор от sanity_checker.py — фокусиран САМО на balance проверка
с детайлен log per product. Полезен когато sanity_checker открие failures и
Тихол иска drill-down.

Може да бъде извикван ad-hoc:
  python3 balance_validator.py --product 12345 --store 5
  python3 balance_validator.py --tenant <id> --since 2026-04-01

Output: human-readable таблица + JSON summary.

Read-only (за разлика от sanity_checker --apply).
"""

import argparse
import json
import sys
from datetime import datetime, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from _db import (
    assert_stress_tenant,
    connect,
    dry_run_log,
    load_db_config,
    resolve_stress_tenant,
)


def fetch_history(conn, tenant_id: int, product_id: int | None,
                  store_id: int | None, since: datetime) -> list:
    where = "WHERE tenant_id = %s AND created_at >= %s"
    params = [tenant_id, since]
    if product_id:
        where += " AND product_id = %s"
        params.append(product_id)
    if store_id:
        where += " AND store_id = %s"
        params.append(store_id)

    with conn.cursor() as cur:
        cur.execute("SHOW TABLES LIKE 'stock_movements'")
        if not cur.fetchone():
            return []
        # `quantity_after` е опционална snapshot column — не съществува във всички
        # schema варианти. COALESCE не помага, защото всяко споменаване на колоната
        # в SELECT изисква тя да съществува. Затова откриваме чрез information_schema
        # и подменяме SELECT-а ако липсва.
        cur.execute(
            "SELECT 1 FROM information_schema.columns "
            "WHERE table_schema = DATABASE() AND table_name = 'stock_movements' "
            "AND column_name = 'quantity_after'"
        )
        has_quantity_after = bool(cur.fetchone())
        qa_select = "quantity_after" if has_quantity_after else "NULL"
        cur.execute(
            f"SELECT product_id, store_id, type, quantity, "
            f"{qa_select} AS quantity_after, "
            f"reference_type, reference_id, created_at "
            f"FROM stock_movements {where} ORDER BY product_id, store_id, created_at",
            params,
        )
        return list(cur.fetchall())


def validate_movements(history: list) -> list:
    """
    Group by (product_id, store_id), премини през записите по време,
    провери че quantity_after = previous_quantity_after ± quantity.

    Връща list от inconsistencies.
    """
    grouped = {}
    for r in history:
        key = (int(r["product_id"]), int(r["store_id"]))
        grouped.setdefault(key, []).append(r)

    inconsistencies = []
    for key, events in grouped.items():
        events.sort(key=lambda x: x["created_at"])
        prev_after = None
        for ev in events:
            qty = int(ev["quantity"])
            kind = ev["type"]  # 'in' / 'out' / 'adjust'
            actual_after = ev.get("quantity_after")
            if actual_after is None:
                continue
            actual_after = int(actual_after)
            if prev_after is None:
                prev_after = actual_after
                continue
            sign = -1 if kind == "out" else 1
            expected_after = prev_after + sign * qty
            if expected_after != actual_after:
                inconsistencies.append({
                    "product_id": key[0],
                    "store_id": key[1],
                    "event_at": ev["created_at"].isoformat(),
                    "type": kind,
                    "qty": qty,
                    "expected_after": expected_after,
                    "actual_after": actual_after,
                    "delta": actual_after - expected_after,
                    "reference_type": ev.get("reference_type"),
                    "reference_id": ev.get("reference_id"),
                })
            prev_after = actual_after

    return inconsistencies


def aggregate_balance(conn, tenant_id: int, since: datetime, product_id: int | None = None,
                      store_id: int | None = None) -> list:
    """
    X-Y+Z aggregate math (S130 expansion):
      closing = opening + deliveries_in - sales_out + refunds_in
                + transfers_in - transfers_out - write_offs - adjustments_out
                + adjustments_in
    Връща list of {product_id, store_id, computed_closing, actual_closing, delta}.

    NB: opening взема quantity_after от първия stock_movement преди `since` (или 0).
    """
    where_p = ""
    where_s = ""
    params: list = [tenant_id, since]
    if product_id:
        where_p = " AND product_id = %s"
        params.append(product_id)
    if store_id:
        where_s = " AND store_id = %s"
        params.append(store_id)

    rows = []
    with conn.cursor() as cur:
        # Опит за aggregation през stock_movements (canonical източник)
        cur.execute("SHOW TABLES LIKE 'stock_movements'")
        if not cur.fetchone():
            return []

        # opening = quantity_after от последен movement преди since per (product,store).
        # Ако такъв няма → assume 0.
        cur.execute(
            f"""
            SELECT product_id, store_id, type,
                   SUM(quantity) AS total_qty,
                   COUNT(*) AS n
            FROM stock_movements
            WHERE tenant_id = %s AND created_at >= %s {where_p} {where_s}
            GROUP BY product_id, store_id, type
            """,
            params,
        )
        agg: dict = {}
        for r in cur.fetchall():
            key = (int(r["product_id"]), int(r["store_id"]))
            agg.setdefault(key, {"in": 0, "out": 0, "adjust": 0, "n": 0})
            kind = r["type"]
            if kind == "in":
                agg[key]["in"] += int(r["total_qty"])
            elif kind == "out":
                agg[key]["out"] += int(r["total_qty"])
            else:
                agg[key]["adjust"] += int(r["total_qty"])
            agg[key]["n"] += int(r["n"])

        for (pid, sid), v in agg.items():
            # opening
            cur.execute(
                """
                SELECT quantity_after FROM stock_movements
                WHERE tenant_id = %s AND product_id = %s AND store_id = %s AND created_at < %s
                ORDER BY created_at DESC LIMIT 1
                """,
                (tenant_id, pid, sid, since),
            )
            op_row = cur.fetchone()
            opening = int(op_row["quantity_after"]) if (op_row and op_row.get("quantity_after") is not None) else 0
            computed = opening + v["in"] - v["out"] + v["adjust"]
            # actual closing = current inventory.quantity
            cur.execute(
                "SELECT quantity FROM inventory WHERE product_id = %s AND store_id = %s LIMIT 1",
                (pid, sid),
            )
            act_row = cur.fetchone()
            actual = int(act_row["quantity"]) if (act_row and act_row.get("quantity") is not None) else 0
            delta = actual - computed
            rows.append({
                "product_id": pid,
                "store_id": sid,
                "opening": opening,
                "in": v["in"],
                "out": v["out"],
                "adjust": v["adjust"],
                "computed_closing": computed,
                "actual_closing": actual,
                "delta": delta,
                "movements_count": v["n"],
            })
    return rows


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tenant", type=int, default=None)
    ap.add_argument("--product", type=int, default=None)
    ap.add_argument("--store", type=int, default=None)
    ap.add_argument("--since", type=str, default=None,
                    help="ISO date — default = 7 дни назад")
    ap.add_argument("--mode", choices=["movements", "aggregate", "both"], default="both",
                    help="movements = per-event; aggregate = X-Y+Z math; both = и двете")
    args = ap.parse_args()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=True)
    tenant_id = args.tenant if args.tenant else resolve_stress_tenant(conn)
    if tenant_id is None:
        sys.exit("[REFUSE] STRESS Lab tenant не съществува.")
    assert_stress_tenant(tenant_id, conn)

    if args.since:
        try:
            since = datetime.fromisoformat(args.since)
        except ValueError:
            sys.exit(f"Invalid --since: {args.since}")
    else:
        since = datetime.now() - timedelta(days=7)

    inconsistencies = []
    aggregate_rows = []
    aggregate_drifted = []

    if args.mode in ("movements", "both"):
        history = fetch_history(conn, tenant_id, args.product, args.store, since)
        print(f"[MOVEMENTS] Loaded {len(history)} stock_movements records since {since}")
        if history:
            inconsistencies = validate_movements(history)
            print(f"[MOVEMENTS] {len(inconsistencies)} inconsistencies открити.")
            for i in inconsistencies[:20]:
                print(
                    f"  ⚠️ p={i['product_id']} s={i['store_id']} "
                    f"{i['event_at']} {i['type']}{i['qty']:+d} "
                    f"expected_after={i['expected_after']} actual={i['actual_after']} Δ={i['delta']} "
                    f"ref={i['reference_type']}#{i['reference_id']}"
                )
            if len(inconsistencies) > 20:
                print(f"  ... и още {len(inconsistencies) - 20}")

    if args.mode in ("aggregate", "both"):
        aggregate_rows = aggregate_balance(conn, tenant_id, since, args.product, args.store)
        aggregate_drifted = [r for r in aggregate_rows if r["delta"] != 0]
        print(f"[AGGREGATE] X-Y+Z math: {len(aggregate_rows)} (product,store) pairs, "
              f"{len(aggregate_drifted)} drifted.")
        for r in aggregate_drifted[:20]:
            print(
                f"  ⚠️ p={r['product_id']} s={r['store_id']} "
                f"opening={r['opening']} +in={r['in']} -out={r['out']} ±adj={r['adjust']} "
                f"→ computed={r['computed_closing']} actual={r['actual_closing']} Δ={r['delta']}"
            )
        if len(aggregate_drifted) > 20:
            print(f"  ... и още {len(aggregate_drifted) - 20}")

    out = dry_run_log("balance_validator", {
        "action": "report", "tenant_id": tenant_id,
        "since": since.isoformat(),
        "mode": args.mode,
        "movements_inconsistencies_total": len(inconsistencies),
        "movements_inconsistencies_sample": inconsistencies[:50],
        "aggregate_pairs_total": len(aggregate_rows),
        "aggregate_drifted_total": len(aggregate_drifted),
        "aggregate_drifted_sample": aggregate_drifted[:50],
    })
    print(f"[LOG] {out}")
    return 0 if not (inconsistencies or aggregate_drifted) else 1


if __name__ == "__main__":
    sys.exit(main() or 0)
