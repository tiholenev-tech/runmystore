#!/usr/bin/env python3
"""
tools/stress/seed_users.py

Етап 1 — Стъпка 4: 5 продавачи в STRESS Lab.

Distribution от STRESS_TENANT_SEED.md §"5 ПРОДАВАЧА":

  1 Петя     Склад      top performer (30% от продажби, висок upsell)
  2 Иван     Склад      среден (25%, понякога раздава отстъпки, lost demand records)
  3 Мария    Склад      нова (15%, висок cancel rate, грешки в кода)
  4 Стефан   Склад      опитен B2B (15%, висок basket value)
  5 Цветана  Rotation   loyal customers champion (15%)

Всеки user има:
  - роля 'sales' (или 'staff' ако такава колона)
  - линкване към tenant_id и default store (warehouse за 4-те, rotation за Цветана)

Idempotent — пропуска ако email вече съществува за tenant.
"""

import argparse
import json
import secrets
import string
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


USERS = [
    {
        "email": "petya@stress.lab",
        "name": "Петя",
        "role": "sales",
        "default_store": "Склад",
        "performance": {"sales_share_pct": 30, "upsell_rate": 0.18, "cancel_rate": 0.02, "loyal_rate": 0.30},
    },
    {
        "email": "ivan@stress.lab",
        "name": "Иван",
        "role": "sales",
        "default_store": "Склад",
        "performance": {"sales_share_pct": 25, "upsell_rate": 0.10, "cancel_rate": 0.05, "discount_giver": True, "lost_demand_recorder": True},
    },
    {
        "email": "maria@stress.lab",
        "name": "Мария",
        "role": "sales",
        "default_store": "Склад",
        "performance": {"sales_share_pct": 15, "upsell_rate": 0.05, "cancel_rate": 0.18, "code_errors": True, "low_retention": True},
    },
    {
        "email": "stefan@stress.lab",
        "name": "Стефан",
        "role": "sales",
        "default_store": "Склад",
        "performance": {"sales_share_pct": 15, "b2b_specialist": True, "avg_basket_eur": [200, 800]},
    },
    {
        "email": "cvetana@stress.lab",
        "name": "Цветана",
        "role": "sales",
        "default_store": None,  # rotation
        "performance": {"sales_share_pct": 15, "loyal_customers_champion": True, "rotation_stores": ["Магазин дрехи", "Магазин обувки", "Магазин mixed"]},
    },
]


def gen_password(n: int = 16) -> str:
    alpha = string.ascii_letters + string.digits
    return "".join(secrets.choice(alpha) for _ in range(n))


def discover_columns(conn, table: str) -> set[str]:
    with conn.cursor() as cur:
        cur.execute(f"SHOW COLUMNS FROM {table}")
        return {row["Field"] for row in cur.fetchall()}


def existing_user_emails(conn, tenant_id: int) -> set[str]:
    with conn.cursor() as cur:
        cur.execute("SELECT email FROM users WHERE tenant_id = %s", (tenant_id,))
        return {(row["email"] or "").lower() for row in cur.fetchall()}


def stores_by_name(conn, tenant_id: int) -> dict:
    with conn.cursor() as cur:
        cur.execute("SELECT id, name FROM stores WHERE tenant_id = %s", (tenant_id,))
        return {row["name"]: int(row["id"]) for row in cur.fetchall()}


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

    cols = discover_columns(conn, "users")
    existing = existing_user_emails(conn, tenant_id)
    stores_map = stores_by_name(conn, tenant_id)

    plan = {"tenant_id": tenant_id, "users": [], "skipped": [], "passwords_redacted": True}
    inserts = []

    for u in USERS:
        if u["email"].lower() in existing:
            plan["skipped"].append(u["email"])
            continue
        row = {"tenant_id": tenant_id, "email": u["email"], "name": u["name"]}
        if "role" in cols:
            row["role"] = u["role"]
        if "password_hash" in cols:
            row["password_hash"] = "PENDING_BCRYPT"  # PHP helper ще го регенерира
        if "store_id" in cols and u["default_store"]:
            sid = stores_map.get(u["default_store"])
            if sid:
                row["store_id"] = sid
        if "metadata" in cols:
            row["metadata"] = json.dumps(u["performance"], ensure_ascii=False)
        if "status" in cols:
            row["status"] = "active"
        if "is_active" in cols:
            row["is_active"] = 1
        plan["users"].append({k: v for k, v in row.items() if k != "password_hash"})
        plan["users"][-1]["_password"] = "(redacted — генерира се при apply)"
        inserts.append(row)

    print(f"[PLAN] tenant_id={tenant_id} — {len(inserts)} нови потребителя, {len(plan['skipped'])} skipped.")
    for r in inserts:
        print(f"  + {r['email']:25s} {r['name']:10s} role={r.get('role', '-')}")

    if not args.apply:
        out = dry_run_log("seed_users", {"action": "dry-run", "plan": plan})
        print(f"[DRY-RUN] План: {out}")
        return 0

    inserted = 0
    passwords = {}
    try:
        with conn.cursor() as cur:
            for row in inserts:
                pw = gen_password()
                passwords[row["email"]] = pw
                # password_hash остава 'PENDING_BCRYPT' — PHP helper ще hash-не паролата отделно
                fields = ", ".join(row.keys())
                placeholders = ", ".join(["%s"] * len(row))
                cur.execute(
                    f"INSERT INTO users ({fields}) VALUES ({placeholders})",
                    list(row.values()),
                )
                inserted += 1
        conn.commit()
    except Exception as e:
        conn.rollback()
        sys.exit(f"[FAIL] INSERT провали (rollback изпълнен): {e}")

    print(f"[OK] Създадени {inserted} потребителя.")
    print( "     ВНИМАНИЕ: password_hash = 'PENDING_BCRYPT'. Тихол:")
    print( "       cd /var/www/runmystore && php -r 'require \"config/db.php\"; ...'  # bcrypt rehash")
    print( "     Паролите за писане в /etc/runmystore/stress_users.env (chmod 600):")
    for email, pw in passwords.items():
        print(f"       {email} = {pw}")
    dry_run_log("seed_users", {"action": "applied", "tenant_id": tenant_id, "inserted": inserted})
    return 0


if __name__ == "__main__":
    sys.exit(main() or 0)
