#!/usr/bin/env python3
"""
tools/stress/cron/sanity_checker.py

Cron 07:00 (след morning_summary) — авто-ловец на бъгове (Етап 4).

X-Y+Z balance validator. Проверява че:
  start_quantity (преди 24h)  -  sold_qty  +  delivered_qty  =  current_quantity

За всеки product × store комбинация в STRESS Lab.

Output:
  - tools/stress/data/dry_run_logs/sanity_checker_<ts>.json
  - DB табл sanity_failures (auto-create)
  - Heartbeat към admin/health.php

Линкове:
  - STRESS_BUILD_PLAN.md ред 199-216 (Етап 4 — Авто-ловец)
  - MORNING_REPORT_TEMPLATE.md ред 91 (червен ред при balance fail)
"""

import argparse
import json
import os
import sys
import time
import urllib.parse
import urllib.request
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


def heartbeat(cron_name: str, status: str, message: str = "", duration_ms: int = 0) -> None:
    token = os.getenv("CRON_HEALTH_TOKEN")
    if not token:
        return
    base = os.getenv("CRON_HEALTH_URL", "https://runmystore.ai/admin/health.php")
    data = urllib.parse.urlencode({
        "cron": cron_name, "status": status, "message": message[:500], "duration_ms": duration_ms,
    }).encode()
    req = urllib.request.Request(base, data=data, method="POST",
                                 headers={"Authorization": f"Bearer {token}"})
    try:
        urllib.request.urlopen(req, timeout=5).read()
    except Exception:
        pass


def ensure_table(conn) -> None:
    with conn.cursor() as cur:
        cur.execute("""
            CREATE TABLE IF NOT EXISTS sanity_failures (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                product_id INT NOT NULL,
                store_id INT NOT NULL,
                expected_qty INT NOT NULL,
                actual_qty INT NOT NULL,
                delta INT NOT NULL,
                check_period_start DATETIME NOT NULL,
                check_period_end DATETIME NOT NULL,
                detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant_detected (tenant_id, detected_at),
                INDEX idx_product (product_id, store_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """)
    conn.commit()


def fetch_balance_data(conn, tenant_id: int, hours: int = 24) -> list:
    """
    За всеки product × store пресмята:
      - sold_qty       = SUM(sale_items.quantity) през последните N часа
      - delivered_qty  = SUM(delivery_items.quantity) през последните N часа
      - returned_qty   = SUM(returns.quantity) ако таблица съществува
      - current_qty    = inventory.quantity сега
      - start_qty      = current_qty + sold - delivered + returned (изчислено reverse)

    Сравнява с stock_movements snapshot ако има, иначе използва пресметнатия start.

    Връща list от dict-и с потенциални balance failures.
    """
    failures = []
    period_end = datetime.now()
    period_start = period_end - timedelta(hours=hours)

    with conn.cursor() as cur:
        # Test за съществуване на таблиците
        cur.execute("SHOW TABLES LIKE 'returns'")
        has_returns = cur.fetchone() is not None
        cur.execute("SHOW TABLES LIKE 'delivery_items'")
        has_deliveries = cur.fetchone() is not None
        cur.execute("SHOW TABLES LIKE 'stock_movements'")
        has_movements = cur.fetchone() is not None

        # Active inventory rows на tenant
        cur.execute("""
            SELECT i.product_id, i.store_id, i.quantity AS current_qty
            FROM inventory i
            JOIN products p ON p.id = i.product_id
            WHERE p.tenant_id = %s
        """, (tenant_id,))
        rows = list(cur.fetchall())

        for r in rows:
            pid = int(r["product_id"])
            sid = int(r["store_id"])
            current = int(r["current_qty"])

            # sold
            cur.execute("""
                SELECT COALESCE(SUM(si.quantity), 0) AS sold
                FROM sale_items si
                JOIN sales s ON s.id = si.sale_id
                WHERE s.tenant_id = %s AND si.product_id = %s AND s.store_id = %s
                  AND s.created_at BETWEEN %s AND %s
            """, (tenant_id, pid, sid, period_start, period_end))
            sold = int((cur.fetchone() or {}).get("sold") or 0)

            # delivered
            delivered = 0
            if has_deliveries:
                cur.execute("""
                    SELECT COALESCE(SUM(di.quantity), 0) AS delivered
                    FROM delivery_items di
                    JOIN deliveries d ON d.id = di.delivery_id
                    WHERE d.tenant_id = %s AND di.product_id = %s AND d.store_id = %s
                      AND d.created_at BETWEEN %s AND %s
                """, (tenant_id, pid, sid, period_start, period_end))
                delivered = int((cur.fetchone() or {}).get("delivered") or 0)

            # returned
            returned = 0
            if has_returns:
                try:
                    cur.execute("""
                        SELECT COALESCE(SUM(quantity), 0) AS returned
                        FROM returns
                        WHERE tenant_id = %s AND product_id = %s AND store_id = %s
                          AND created_at BETWEEN %s AND %s
                    """, (tenant_id, pid, sid, period_start, period_end))
                    returned = int((cur.fetchone() or {}).get("returned") or 0)
                except Exception:
                    returned = 0

            # actual start_qty от stock_movements (ако има)
            start_qty_recorded = None
            if has_movements:
                try:
                    cur.execute("""
                        SELECT quantity_after
                        FROM stock_movements
                        WHERE tenant_id = %s AND product_id = %s AND store_id = %s
                          AND created_at <= %s
                        ORDER BY created_at DESC LIMIT 1
                    """, (tenant_id, pid, sid, period_start))
                    mvm = cur.fetchone()
                    if mvm and mvm.get("quantity_after") is not None:
                        start_qty_recorded = int(mvm["quantity_after"])
                except Exception:
                    pass

            # Балансната формула: expected_now = start - sold + delivered + returned
            if start_qty_recorded is not None:
                expected = start_qty_recorded - sold + delivered + returned
                delta = current - expected
                if delta != 0:
                    failures.append({
                        "tenant_id": tenant_id,
                        "product_id": pid,
                        "store_id": sid,
                        "start_qty": start_qty_recorded,
                        "sold": sold,
                        "delivered": delivered,
                        "returned": returned,
                        "expected_qty": expected,
                        "actual_qty": current,
                        "delta": delta,
                        "period_start": period_start.isoformat(),
                        "period_end": period_end.isoformat(),
                    })

    return failures


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tenant", type=int, default=None)
    ap.add_argument("--hours", type=int, default=24, help="Период за balance check.")
    ap.add_argument("--apply", action="store_true",
                    help="Запиши failures в DB. Default = print only.")
    args = ap.parse_args()
    t0 = time.perf_counter()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=False)
    tenant_id = args.tenant if args.tenant else resolve_stress_tenant(conn)
    if tenant_id is None:
        heartbeat("sanity_checker", "FAIL", "STRESS Lab tenant not found")
        sys.exit("[REFUSE] STRESS Lab tenant не съществува.")
    assert_stress_tenant(tenant_id, conn)

    ensure_table(conn)

    failures = fetch_balance_data(conn, tenant_id, args.hours)

    print(f"[CHECK] tenant_id={tenant_id} period={args.hours}h failures={len(failures)}")
    for f in failures[:10]:
        print(f"  ⚠️ product={f['product_id']} store={f['store_id']} expected={f['expected_qty']} actual={f['actual_qty']} Δ={f['delta']}")
    if len(failures) > 10:
        print(f"  ... и още {len(failures) - 10} failures")

    if not args.apply:
        out = dry_run_log("sanity_checker", {
            "action": "dry-run", "tenant_id": tenant_id,
            "failures_total": len(failures), "failures_sample": failures[:20],
        })
        print(f"[DRY-RUN] {out}")
        heartbeat("sanity_checker", "OK" if not failures else "WARN",
                  f"failures={len(failures)} (dry-run)",
                  int((time.perf_counter() - t0) * 1000))
        return 0 if not failures else 1

    # APPLY: записва failures в DB
    inserted = 0
    if failures:
        with conn.cursor() as cur:
            for f in failures:
                cur.execute("""
                    INSERT INTO sanity_failures
                    (tenant_id, product_id, store_id, expected_qty, actual_qty, delta,
                     check_period_start, check_period_end)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                """, (
                    f["tenant_id"], f["product_id"], f["store_id"],
                    f["expected_qty"], f["actual_qty"], f["delta"],
                    f["period_start"], f["period_end"],
                ))
                inserted += 1
        conn.commit()

    duration_ms = int((time.perf_counter() - t0) * 1000)
    print(f"[OK] inserted={inserted} balance failures (period={args.hours}h)")
    dry_run_log("sanity_checker", {
        "action": "applied", "tenant_id": tenant_id,
        "failures_total": len(failures), "inserted": inserted,
        "duration_ms": duration_ms,
    })
    status = "OK" if not failures else "WARN"
    if len(failures) > 50:
        status = "CRIT"
    heartbeat("sanity_checker", status,
              f"failures={len(failures)} period={args.hours}h", duration_ms)
    return 0 if not failures else 1


if __name__ == "__main__":
    try:
        rc = main()
    except SystemExit:
        raise
    except Exception as e:
        heartbeat("sanity_checker", "FAIL", f"unhandled: {type(e).__name__}: {str(e)[:200]}")
        raise
    sys.exit(rc or 0)
