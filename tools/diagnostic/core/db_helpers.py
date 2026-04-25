"""
db_helpers.py — DB connection + safety guards за tools/diagnostic/

Чете credentials от /etc/runmystore/db.env (KEY=VALUE format, без [section]).
Никога hardcoded creds. Никога raw mysqli — само mysql.connector с context manager.

Tenant guard: всяка destructive операция изисква tenant_id IN (7, 99).
Production tenants (47 ЕНИ, future clients) винаги забранени.
"""

import os
import sys
import mysql.connector
from contextlib import contextmanager

ENV_PATH = '/etc/runmystore/db.env'
ALLOWED_TENANTS = (7, 99)
PRODUCTION_TENANTS = (47,)


def parse_env(path: str = ENV_PATH) -> dict:
    """Parse KEY=VALUE format file (no INI sections)."""
    if not os.path.exists(path):
        raise FileNotFoundError(f"DB env file not found: {path}")
    cfg = {}
    with open(path, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#'):
                continue
            if '=' not in line:
                continue
            key, _, value = line.partition('=')
            cfg[key.strip()] = value.strip().strip('"').strip("'")
    required = ['DB_USER', 'DB_PASS']
    missing = [k for k in required if not cfg.get(k)]
    if missing:
        raise ValueError(f"Missing required env keys in {path}: {missing}")
    cfg.setdefault('DB_HOST', '127.0.0.1')
    cfg.setdefault('DB_NAME', 'runmystore')
    cfg.setdefault('DB_PORT', '3306')
    return cfg


def get_conn(autocommit: bool = False):
    cfg = parse_env()
    return mysql.connector.connect(
        host=cfg['DB_HOST'],
        port=int(cfg['DB_PORT']),
        user=cfg['DB_USER'],
        password=cfg['DB_PASS'],
        database=cfg['DB_NAME'],
        autocommit=autocommit,
        use_unicode=True,
        charset='utf8mb4',
        collation='utf8mb4_unicode_ci',
    )


@contextmanager
def conn_ctx(autocommit: bool = False):
    """Context manager wrapper — ensures connection is always closed."""
    conn = get_conn(autocommit=autocommit)
    try:
        yield conn
    finally:
        try:
            conn.close()
        except Exception:
            pass


@contextmanager
def transaction():
    """Begin transaction — auto-commit on success, rollback on exception."""
    conn = get_conn(autocommit=False)
    try:
        yield conn
        conn.commit()
    except Exception:
        try:
            conn.rollback()
        except Exception:
            pass
        raise
    finally:
        try:
            conn.close()
        except Exception:
            pass


def assert_safe_tenant(tenant_id: int) -> None:
    """Hard guard: refuse to operate on production tenants."""
    tid = int(tenant_id)
    if tid in PRODUCTION_TENANTS:
        raise SystemExit(
            f"ABORT: tenant_id={tid} is PRODUCTION. "
            f"Diagnostic операции категорично забранени."
        )
    if tid not in ALLOWED_TENANTS:
        raise SystemExit(
            f"ABORT: tenant_id={tid} не е в разрешения списък {ALLOWED_TENANTS}. "
            f"Diagnostic може да оперира САМО на 7 (test) или 99 (eval)."
        )


def fetchone(sql: str, params: tuple = ()):
    with conn_ctx(autocommit=True) as c:
        cur = c.cursor(dictionary=True)
        cur.execute(sql, params)
        row = cur.fetchone()
        cur.close()
        return row


def fetchall(sql: str, params: tuple = ()):
    with conn_ctx(autocommit=True) as c:
        cur = c.cursor(dictionary=True)
        cur.execute(sql, params)
        rows = cur.fetchall()
        cur.close()
        return rows


def execute(sql: str, params: tuple = ()) -> int:
    with transaction() as c:
        cur = c.cursor()
        cur.execute(sql, params)
        n = cur.rowcount
        cur.close()
        return n


def get_notify_config() -> dict:
    """
    Чете NOTIFY_EMAIL, TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID от db.env.
    Връща празни stringове ако липсват — caller проверява.
    """
    cfg = parse_env()
    return {
        'email': cfg.get('NOTIFY_EMAIL', ''),
        'telegram_token': cfg.get('TELEGRAM_BOT_TOKEN', ''),
        'telegram_chat_id': cfg.get('TELEGRAM_CHAT_ID', ''),
    }


if __name__ == '__main__':
    print("db_helpers.py self-test")
    print("=" * 60)
    try:
        cfg = parse_env()
        print(f"  ✓ Parsed {ENV_PATH}: {len(cfg)} keys")
        print(f"  - DB_HOST={cfg['DB_HOST']} DB_NAME={cfg['DB_NAME']} DB_USER={cfg['DB_USER'][:3]}***")
        with conn_ctx(autocommit=True) as c:
            cur = c.cursor()
            cur.execute("SELECT VERSION(), DATABASE()")
            ver, db = cur.fetchone()
            print(f"  ✓ Connected: MySQL {ver}, db={db}")
            cur.execute("SHOW TABLES LIKE 'seed_oracle'")
            so = cur.fetchone()
            print(f"  - seed_oracle exists: {bool(so)}")
            cur.execute("SHOW TABLES LIKE 'diagnostic_log'")
            dl = cur.fetchone()
            print(f"  - diagnostic_log exists: {bool(dl)}")
        nc = get_notify_config()
        print(f"  - NOTIFY_EMAIL: {'set' if nc['email'] else 'EMPTY (placeholder)'}")
        print(f"  - TELEGRAM_BOT_TOKEN: {'set' if nc['telegram_token'] else 'EMPTY (placeholder)'}")
        print(f"  - TELEGRAM_CHAT_ID: {'set' if nc['telegram_chat_id'] else 'EMPTY (placeholder)'}")
        try:
            assert_safe_tenant(47)
            print("  ✗ FAIL: tenant=47 трябваше да бъде отхвърлен")
            sys.exit(1)
        except SystemExit:
            print("  ✓ Tenant guard работи (47 → ABORT)")
        assert_safe_tenant(7)
        print("  ✓ Tenant guard работи (7 → OK)")
        print("\nALL CHECKS PASSED ✅")
    except Exception as e:
        print(f"\n✗ FAILED: {e}")
        sys.exit(1)
