#!/usr/bin/env python3
"""
tools/stress/reset_stress_tenant.py

Безопасен reset на STRESS Lab tenant.

ABSOLUTE GUARDS:
  1. Refuse ако email = tiholenev@gmail.com
  2. Refuse ако tenant_id = 7
  3. Изисква explicit --yes-i-am-sure флаг + --tenant <id>
  4. Mysqldump backup ПРЕДИ DELETE-ите (записва в /tmp/runmystore_backups/)
  5. Default = --dry-run (само принтира план)

Какво трие (само за дадения tenant):
  - sale_items (cascade през sales)
  - sales
  - inventory
  - stock_movements
  - returns (ако таблицата съществува)
  - lost_demand (ако таблицата съществува)
  - ai_insights
  - delivery_items (cascade през deliveries)
  - deliveries
  - search_log
  - users (с email *@stress.lab)
  - suppliers
  - stores
  - products
  - tenant ред — само ако --include-tenant-row

Никакъв TRUNCATE — само DELETE WHERE tenant_id = X (изолация).

Usage:
    python3 reset_stress_tenant.py --tenant <id>                       # dry-run
    python3 reset_stress_tenant.py --tenant <id> --yes-i-am-sure --apply
    python3 reset_stress_tenant.py --tenant <id> --include-tenant-row --yes-i-am-sure --apply
"""

import argparse
import os
import shlex
import subprocess
import sys
from datetime import datetime
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _db import (
    assert_stress_tenant,
    connect,
    dry_run_log,
    load_db_config,
)


# Ред на изтриване — child-first, за да не нарушим foreign keys
DELETE_ORDER = [
    # depend on sales
    ("sale_items",       "DELETE si FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.tenant_id = %s"),
    ("sales",            "DELETE FROM sales WHERE tenant_id = %s"),
    # inventory + movements
    ("stock_movements",  "DELETE FROM stock_movements WHERE tenant_id = %s"),
    ("inventory",        "DELETE FROM inventory WHERE tenant_id = %s"),
    # returns / lost_demand / search_log
    ("returns",          "DELETE FROM returns WHERE tenant_id = %s"),
    ("lost_demand",      "DELETE FROM lost_demand WHERE tenant_id = %s"),
    ("search_log",       "DELETE FROM search_log WHERE tenant_id = %s"),
    # AI
    ("ai_insights",      "DELETE FROM ai_insights WHERE tenant_id = %s"),
    # deliveries (ако таблиците съществуват)
    ("delivery_items",   "DELETE di FROM delivery_items di JOIN deliveries d ON d.id = di.delivery_id WHERE d.tenant_id = %s"),
    ("deliveries",       "DELETE FROM deliveries WHERE tenant_id = %s"),
    # transfers
    ("transfer_items",   "DELETE ti FROM transfer_items ti JOIN transfers t ON t.id = ti.transfer_id WHERE t.tenant_id = %s"),
    ("transfers",        "DELETE FROM transfers WHERE tenant_id = %s"),
    # orders
    ("order_items",      "DELETE oi FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.tenant_id = %s"),
    ("orders",           "DELETE FROM orders WHERE tenant_id = %s"),
    # base lookups
    ("products",         "DELETE FROM products WHERE tenant_id = %s"),
    ("suppliers",        "DELETE FROM suppliers WHERE tenant_id = %s"),
    ("stores",           "DELETE FROM stores WHERE tenant_id = %s"),
    ("users",            "DELETE FROM users WHERE tenant_id = %s AND email LIKE '%%@stress.lab'"),
]


def table_exists(conn, name: str) -> bool:
    with conn.cursor() as cur:
        cur.execute("SHOW TABLES LIKE %s", (name,))
        return cur.fetchone() is not None


def count_rows(conn, table: str, where_sql: str, params: tuple) -> int:
    if not table_exists(conn, table):
        return -1  # липсва
    sql = f"SELECT COUNT(*) AS c FROM {table} WHERE {where_sql}"
    with conn.cursor() as cur:
        try:
            cur.execute(sql, params)
            row = cur.fetchone()
            return int(row["c"])
        except Exception:
            return -1


def mysqldump_backup(cfg: dict, tenant_id: int) -> Path | None:
    """mysqldump на цялата DB → /tmp/runmystore_backups/. Връща path или None."""
    backup_dir = Path("/tmp/runmystore_backups")
    backup_dir.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    out = backup_dir / f"stress_reset_t{tenant_id}_{ts}.sql"
    cmd = [
        "mysqldump",
        f"--host={cfg['DB_HOST']}",
        f"--user={cfg['DB_USER']}",
        f"--password={cfg['DB_PASS']}",
        "--single-transaction",
        "--quick",
        "--default-character-set=utf8mb4",
        cfg["DB_NAME"],
    ]
    print(f"[BACKUP] mysqldump → {out}")
    try:
        with open(out, "wb") as f:
            res = subprocess.run(cmd, stdout=f, stderr=subprocess.PIPE, timeout=600)
        if res.returncode != 0:
            print(f"[WARN] mysqldump exit={res.returncode}: {res.stderr.decode()[:500]}", file=sys.stderr)
            return None
        size_mb = out.stat().st_size / (1024 * 1024)
        print(f"[OK] Backup: {out} ({size_mb:.1f} MB)")
        return out
    except FileNotFoundError:
        print("[WARN] mysqldump не е намерен на $PATH — пропускам backup.", file=sys.stderr)
        return None
    except Exception as e:
        print(f"[WARN] mysqldump fail: {e}", file=sys.stderr)
        return None


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tenant", type=int, required=True,
                    help="STRESS Lab tenant id — задължителен (никаква дефолтна резолюция)")
    ap.add_argument("--apply", action="store_true",
                    help="Реално DELETE. Без него — dry-run.")
    ap.add_argument("--yes-i-am-sure", action="store_true",
                    help="Безусловно потвърждение. Без него — refuse.")
    ap.add_argument("--include-tenant-row", action="store_true",
                    help="Изтрий и tenants реда. Default — само данни, tenant остава.")
    ap.add_argument("--no-backup", action="store_true",
                    help="Пропусни mysqldump (НЕ препоръчително)")
    args = ap.parse_args()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=False)
    tenant_id = assert_stress_tenant(args.tenant, conn)  # ← refuse-ва ENI

    if args.apply and not args.yes_i_am_sure:
        sys.exit("[REFUSE] --apply изисква --yes-i-am-sure. Прекъсване.")

    print(f"[PLAN] reset на tenant_id={tenant_id} (STRESS Lab)")
    plan_counts = {}
    for table, _ in DELETE_ORDER:
        # за counts използваме просто tenant_id или JOIN-ваме
        if table in ("sale_items", "delivery_items", "transfer_items", "order_items"):
            parent = {"sale_items": ("sales", "sale_id"), "delivery_items": ("deliveries", "delivery_id"),
                      "transfer_items": ("transfers", "transfer_id"), "order_items": ("orders", "order_id")}[table]
            if not table_exists(conn, table):
                plan_counts[table] = -1
                continue
            with conn.cursor() as cur:
                try:
                    cur.execute(
                        f"SELECT COUNT(*) AS c FROM {table} t JOIN {parent[0]} p ON p.id = t.{parent[1]} "
                        f"WHERE p.tenant_id = %s",
                        (tenant_id,),
                    )
                    plan_counts[table] = int(cur.fetchone()["c"])
                except Exception:
                    plan_counts[table] = -1
            continue
        if table == "users":
            plan_counts[table] = count_rows(conn, table, "tenant_id = %s AND email LIKE '%%@stress.lab'", (tenant_id,))
        else:
            plan_counts[table] = count_rows(conn, table, "tenant_id = %s", (tenant_id,))

    for table, cnt in plan_counts.items():
        marker = "—" if cnt == -1 else f"{cnt} реда"
        print(f"  [{table:18s}] {marker}")

    if args.include_tenant_row:
        print(f"  [tenants]            1 ред (ще се изтрие — --include-tenant-row)")

    if not args.apply:
        out = dry_run_log("reset_stress_tenant", {
            "action": "dry-run", "tenant_id": tenant_id,
            "counts": plan_counts, "include_tenant_row": args.include_tenant_row,
        })
        print(f"[DRY-RUN] План: {out}")
        return 0

    if not args.no_backup:
        backup_path = mysqldump_backup(cfg, tenant_id)
        if not backup_path:
            sys.exit("[REFUSE] Backup не успя. Прекъсване (--no-backup за override, не препоръчително).")
    else:
        print("[WARN] --no-backup — продължавам без backup. Това е НА ТВОЯ ОТГОВОРНОСТ.")

    deleted = {}
    try:
        with conn.cursor() as cur:
            for table, sql in DELETE_ORDER:
                if not table_exists(conn, table):
                    deleted[table] = "skip (no table)"
                    continue
                try:
                    cur.execute(sql, (tenant_id,))
                    deleted[table] = cur.rowcount
                except Exception as e:
                    deleted[table] = f"error: {e}"
            if args.include_tenant_row:
                cur.execute("DELETE FROM tenants WHERE id = %s", (tenant_id,))
                deleted["tenants"] = cur.rowcount
        conn.commit()
    except Exception as e:
        conn.rollback()
        sys.exit(f"[FAIL] DELETE провали (rollback): {e}")

    print("[OK] Изтрити:")
    for t, c in deleted.items():
        print(f"  {t:18s} {c}")
    dry_run_log("reset_stress_tenant", {
        "action": "applied", "tenant_id": tenant_id, "deleted": deleted,
        "include_tenant_row": args.include_tenant_row,
    })
    return 0


if __name__ == "__main__":
    sys.exit(main() or 0)
