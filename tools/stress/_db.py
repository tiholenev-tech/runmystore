#!/usr/bin/env python3
"""
tools/stress/_db.py — споделени DB helper-и + предпазители за STRESS Lab.

Всички seed/cron скриптове в tools/stress/ викат тук:
  - load_db_config()           — чете /etc/runmystore/db.env
  - connect()                  — pymysql connect (UTF-8, autocommit=False)
  - resolve_stress_tenant()    — намира tenant по email stress@runmystore.ai
  - assert_stress_tenant()     — refuse ако email == tiholenev@gmail.com или ако ENI
                                 tenant_id попадне в parameter
  - dry_run_log()              — пише JSON в tools/stress/data/dry_run_logs/

ABSOLUTE GUARD: всеки скрипт трябва да започне с
    tenant_id = assert_stress_tenant(args)
преди която и да е mutация.

Random seed = 42 за всички генератори (deterministic).
"""

import json
import os
import random
import sys
import time
from datetime import datetime
from pathlib import Path

DB_ENV = "/etc/runmystore/db.env"
STRESS_EMAIL = "stress@runmystore.ai"
ENI_EMAIL = "tiholenev@gmail.com"
ENI_TENANT_ID = 7
RANDOM_SEED = 42

DRY_RUN_DIR = Path(__file__).resolve().parent / "data" / "dry_run_logs"


def seed_rng():
    """Винаги един и същ seed → reproducible test data."""
    random.seed(RANDOM_SEED)


def load_db_config(env_path: str = DB_ENV) -> dict:
    """Чете /etc/runmystore/db.env. Хвърля ясни грешки при липса/permission."""
    if not os.path.exists(env_path):
        raise FileNotFoundError(env_path)
    if not os.access(env_path, os.R_OK):
        raise PermissionError(
            f"{env_path} не е четим от текущия потребител "
            "(трябва www-data или sudo)"
        )
    cfg = {}
    with open(env_path) as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            k, _, v = line.partition("=")
            cfg[k.strip()] = v.strip().strip("'\"")
    required = {"DB_HOST", "DB_NAME", "DB_USER", "DB_PASS"}
    missing = required - set(cfg)
    if missing:
        raise ValueError(f"missing keys in {env_path}: {missing}")
    cfg.setdefault("DB_HOST", "127.0.0.1")
    # Env override lets sandbox runs redirect to runmystore_stress_sandbox without
    # editing /etc/runmystore/db.env. Required by SANDBOX_GUIDE seed cycle.
    if os.getenv("DB_NAME"):
        cfg["DB_NAME"] = os.getenv("DB_NAME")
    return cfg


def connect(cfg: dict | None = None, autocommit: bool = False):
    """pymysql connection с UTF-8 + cursor=DictCursor."""
    try:
        import pymysql
        import pymysql.cursors
    except ImportError:
        print("[FATAL] pymysql не е инсталиран: pip install pymysql", file=sys.stderr)
        sys.exit(2)
    if cfg is None:
        cfg = load_db_config()
    return pymysql.connect(
        host=cfg["DB_HOST"],
        user=cfg["DB_USER"],
        password=cfg["DB_PASS"],
        database=cfg["DB_NAME"],
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=autocommit,
    )


def resolve_stress_tenant(conn) -> int | None:
    """Връща tenant_id на STRESS Lab или None ако не съществува."""
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id FROM tenants WHERE email = %s LIMIT 1",
            (STRESS_EMAIL,),
        )
        row = cur.fetchone()
    return int(row["id"]) if row else None


def assert_stress_tenant(tenant_id: int, conn) -> int:
    """
    HARD GUARD. Хвърля SystemExit ако:
      - tenant_id == ENI tenant (id=7)
      - tenant.email == tiholenev@gmail.com
      - tenant.email != stress@runmystore.ai
      - tenant не съществува

    Това е защитата срещу случайно изпълнение върху реален магазин.
    """
    if tenant_id == ENI_TENANT_ID:
        sys.exit(
            f"[REFUSE] tenant_id={tenant_id} е ENI Тихолов. "
            "STRESS скриптовете НИКОГА не пишат върху ENI."
        )
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, email, name FROM tenants WHERE id = %s LIMIT 1",
            (tenant_id,),
        )
        row = cur.fetchone()
    if not row:
        sys.exit(f"[REFUSE] tenant_id={tenant_id} не съществува")
    email = (row.get("email") or "").lower()
    if email == ENI_EMAIL.lower():
        sys.exit(
            f"[REFUSE] tenant_id={tenant_id} ({email}) е ENI Тихолов. "
            "STRESS скриптовете НИКОГА не пишат върху ENI."
        )
    if email != STRESS_EMAIL.lower():
        sys.exit(
            f"[REFUSE] tenant_id={tenant_id} ({email}) не е STRESS Lab. "
            f"Очаквано: {STRESS_EMAIL}. Прекъсване — възможна грешка."
        )
    return int(row["id"])


def dry_run_log(script_name: str, payload: dict) -> Path:
    """Записва JSON snapshot в tools/stress/data/dry_run_logs/."""
    DRY_RUN_DIR.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    out = DRY_RUN_DIR / f"{script_name}_{ts}.json"
    payload.setdefault("script", script_name)
    payload.setdefault("timestamp", datetime.now().isoformat())
    out.write_text(json.dumps(payload, ensure_ascii=False, indent=2))
    return out


def now_ms() -> int:
    return int(time.time() * 1000)
