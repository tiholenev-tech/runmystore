#!/usr/bin/env python3
"""
tools/stress/setup_stress_tenant.py

Етап 1 — Стъпка 1: създаване на STRESS Lab tenant.

- Email: stress@runmystore.ai
- Plan:  PRO
- Country: BG, Currency: EUR, Language: bg
- Mode:  shadow (lab)

REFUSE GUARDS:
  * Никога не създава tenant с email tiholenev@gmail.com
  * Никога не пипа tenant_id=7
  * Ако STRESS Lab вече съществува → idempotent (връща съществуващия id)

Usage:
    python3 setup_stress_tenant.py --dry-run        # default — нищо не пише
    python3 setup_stress_tenant.py --apply          # реално създава tenant
    python3 setup_stress_tenant.py --apply --force  # пресъздава ако вече съществува (DANGEROUS)
"""

import argparse
import secrets
import string
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _db import (
    ENI_EMAIL,
    ENI_TENANT_ID,
    STRESS_EMAIL,
    connect,
    dry_run_log,
    load_db_config,
    resolve_stress_tenant,
    seed_rng,
)


TENANT_DEFAULTS = {
    "email": STRESS_EMAIL,
    "name": "STRESS Lab",
    "plan": "PRO",
    "country_code": "BG",
    "currency": "EUR",
    "language": "bg",
    "mode": "shadow",
}


def gen_password(n: int = 24) -> str:
    """Сигурна случайна парола (записва се в /etc/runmystore/stress.env след apply)."""
    alphabet = string.ascii_letters + string.digits + "!@#$%^&*"
    return "".join(secrets.choice(alphabet) for _ in range(n))


def discover_tenants_columns(conn) -> set[str]:
    """Чете колоните на `tenants` за да съставим INSERT само от съществуващите."""
    with conn.cursor() as cur:
        cur.execute("SHOW COLUMNS FROM tenants")
        return {row["Field"] for row in cur.fetchall()}


def build_insert(cols: set[str], values: dict) -> tuple[str, list]:
    used = [(k, v) for k, v in values.items() if k in cols]
    field_list = ", ".join(k for k, _ in used)
    placeholders = ", ".join(["%s"] * len(used))
    sql = f"INSERT INTO tenants ({field_list}) VALUES ({placeholders})"
    return sql, [v for _, v in used]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true",
                    help="Реално създай tenant. Default = dry-run.")
    ap.add_argument("--force", action="store_true",
                    help="Ако STRESS Lab вече съществува → грешка (без --force).")
    args = ap.parse_args()
    seed_rng()

    if not args.apply:
        print("[DRY-RUN] Няма да правя нищо. Минавам през план.")

    cfg = load_db_config()
    conn = connect(cfg, autocommit=False)

    # Hard refuse — никога не пускай setup на tenant с email на ENI Тихолов
    if TENANT_DEFAULTS["email"].lower() == ENI_EMAIL.lower():
        sys.exit(f"[REFUSE] Конфигурираният email е ENI: {TENANT_DEFAULTS['email']}")

    existing_id = resolve_stress_tenant(conn)
    if existing_id is not None:
        if existing_id == ENI_TENANT_ID:
            sys.exit(f"[REFUSE] STRESS email точно върху tenant_id=7 — невъзможно. Прекъсване.")
        if not args.force:
            print(f"[OK] STRESS Lab вече съществува: tenant_id={existing_id}. Idempotent — нищо не пиша.")
            dry_run_log("setup_stress_tenant", {
                "action": "noop", "tenant_id": existing_id, "reason": "already exists",
            })
            return 0
        print(f"[FORCE] Ще ПРЕСЪЗДАМ STRESS Lab — старият tenant_id={existing_id} ще се запази (само add).")

    cols = discover_tenants_columns(conn)
    password = gen_password()

    payload = dict(TENANT_DEFAULTS)
    # Schema може да използва или `password_hash` (старо), или `password` (S133).
    # И в двата случая записваме placeholder; реално bcrypt hash става post-apply.
    if "password_hash" in cols:
        payload["password_hash"] = "PENDING_BCRYPT"
    if "password" in cols:
        payload["password"] = "PENDING_BCRYPT"
    if "status" in cols:
        payload["status"] = "active"
    if "created_at" in cols:
        # MySQL ще запълни с NOW() ако е default; иначе литерал
        pass

    sql, params = build_insert(cols, payload)

    plan = {
        "sql": sql,
        "params": [p if not isinstance(p, str) or "PASSWORD" not in p.upper() else "***" for p in params],
        "tenant_payload": {k: v for k, v in payload.items() if k != "password_hash"},
        "password_will_be_written_to": "/etc/runmystore/stress.env",
    }

    print("[PLAN] INSERT INTO tenants:")
    for k, v in plan["tenant_payload"].items():
        print(f"  {k:14s} = {v}")

    if not args.apply:
        out = dry_run_log("setup_stress_tenant", {"action": "dry-run", "plan": plan})
        print(f"[DRY-RUN] План записан: {out}")
        return 0

    try:
        with conn.cursor() as cur:
            cur.execute(sql, params)
            new_id = cur.lastrowid
        conn.commit()
    except Exception as e:
        conn.rollback()
        sys.exit(f"[FAIL] INSERT провали: {e}")

    print(f"[OK] Създаден STRESS Lab tenant_id={new_id}")
    print(f"     Парола: {password}")
    print( "     ВНИМАНИЕ: запиши паролата в /etc/runmystore/stress.env (chmod 600).")
    dry_run_log("setup_stress_tenant", {
        "action": "applied", "tenant_id": new_id,
        "tenant_payload": {k: v for k, v in payload.items() if k != "password_hash"},
    })
    return 0


if __name__ == "__main__":
    sys.exit(main() or 0)
