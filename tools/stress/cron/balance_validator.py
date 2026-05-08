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
        cur.execute(
            f"SELECT product_id, store_id, type, quantity, "
            f"COALESCE(quantity_after, NULL) AS quantity_after, "
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


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tenant", type=int, default=None)
    ap.add_argument("--product", type=int, default=None)
    ap.add_argument("--store", type=int, default=None)
    ap.add_argument("--since", type=str, default=None,
                    help="ISO date — default = 7 дни назад")
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

    history = fetch_history(conn, tenant_id, args.product, args.store, since)
    print(f"[INFO] Loaded {len(history)} stock_movements records since {since}")

    if not history:
        print("[OK] Няма stock_movements за дадените filters.")
        return 0

    inconsistencies = validate_movements(history)

    print(f"[VALIDATE] {len(inconsistencies)} inconsistencies открити.")
    for i in inconsistencies[:20]:
        print(
            f"  ⚠️ p={i['product_id']} s={i['store_id']} "
            f"{i['event_at']} {i['type']}{i['qty']:+d} "
            f"expected_after={i['expected_after']} actual={i['actual_after']} Δ={i['delta']} "
            f"ref={i['reference_type']}#{i['reference_id']}"
        )
    if len(inconsistencies) > 20:
        print(f"  ... и още {len(inconsistencies) - 20}")

    out = dry_run_log("balance_validator", {
        "action": "report", "tenant_id": tenant_id,
        "since": since.isoformat(),
        "history_count": len(history),
        "inconsistencies_total": len(inconsistencies),
        "inconsistencies_sample": inconsistencies[:50],
    })
    print(f"[LOG] {out}")
    return 0 if not inconsistencies else 1


if __name__ == "__main__":
    sys.exit(main() or 0)
