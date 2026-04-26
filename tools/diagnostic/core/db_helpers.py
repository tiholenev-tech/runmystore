"""
db_helpers.py — DB connection + safety guards (PyMySQL).
"""
import os
import sys
import pymysql
import pymysql.cursors
from contextlib import contextmanager

ENV_PATH = '/etc/runmystore/db.env'
ALLOWED_TENANTS = (7, 99)
PRODUCTION_TENANTS = (47,)


def parse_env(path: str = ENV_PATH) -> dict:
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
        raise ValueError(f"Missing required env keys: {missing}")
    cfg.setdefault('DB_HOST', '127.0.0.1')
    cfg.setdefault('DB_NAME', 'runmystore')
    cfg.setdefault('DB_PORT', '3306')
    return cfg


def get_conn(autocommit: bool = False, dict_rows: bool = True):
    cfg = parse_env()
    return pymysql.connect(
        host=cfg['DB_HOST'],
        port=int(cfg['DB_PORT']),
        user=cfg['DB_USER'],
        password=cfg['DB_PASS'],
        database=cfg['DB_NAME'],
        autocommit=autocommit,
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor if dict_rows else pymysql.cursors.Cursor,
    )


@contextmanager
def conn_ctx(autocommit: bool = False):
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
    tid = int(tenant_id)
    if tid in PRODUCTION_TENANTS:
        raise SystemExit(f"ABORT: tenant_id={tid} is PRODUCTION.")
    if tid not in ALLOWED_TENANTS:
        raise SystemExit(f"ABORT: tenant_id={tid} not in {ALLOWED_TENANTS}.")


def fetchone(sql, params=()):
    with conn_ctx(autocommit=True) as c:
        cur = c.cursor()
        cur.execute(sql, params)
        row = cur.fetchone()
        cur.close()
        return row


def fetchall(sql, params=()):
    with conn_ctx(autocommit=True) as c:
        cur = c.cursor()
        cur.execute(sql, params)
        rows = cur.fetchall()
        cur.close()
        return rows


def execute(sql, params=()):
    with transaction() as c:
        cur = c.cursor()
        cur.execute(sql, params)
        n = cur.rowcount
        cur.close()
        return n


def get_notify_config() -> dict:
    cfg = parse_env()
    return {
        'email': cfg.get('NOTIFY_EMAIL', ''),
        'telegram_token': cfg.get('TELEGRAM_BOT_TOKEN', ''),
        'telegram_chat_id': cfg.get('TELEGRAM_CHAT_ID', ''),
    }


if __name__ == '__main__':
    print("db_helpers self-test")
    print("=" * 60)
    try:
        cfg = parse_env()
        print(f"  Parsed env: {len(cfg)} keys (host={cfg['DB_HOST']}, db={cfg['DB_NAME']})")
        with conn_ctx(autocommit=True) as c:
            cur = c.cursor()
            cur.execute("SELECT VERSION() AS v, DATABASE() AS d")
            r = cur.fetchone()
            print(f"  Connected: MySQL {r['v']}, db={r['d']}")
            cur.execute("SHOW TABLES LIKE 'seed_oracle'")
            print(f"  seed_oracle: {bool(cur.fetchone())}")
            cur.execute("SHOW TABLES LIKE 'diagnostic_log'")
            print(f"  diagnostic_log: {bool(cur.fetchone())}")
        try:
            assert_safe_tenant(47)
            print("  FAIL: tenant=47 should be rejected")
            sys.exit(1)
        except SystemExit:
            print("  Tenant guard OK (47 -> ABORT)")
        assert_safe_tenant(7)
        print("  Tenant guard OK (7 -> OK)")
        print("\nALL CHECKS PASSED")
    except Exception as e:
        print(f"\nFAILED: {type(e).__name__}: {e}")
        sys.exit(1)
