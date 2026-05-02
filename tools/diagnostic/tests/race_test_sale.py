#!/usr/bin/env python3
"""
race_test_sale.py — concurrency test for sale.php inventory atomicity.

Confirms that S90.RACE fix (sale.php:135-139) prevents over-selling at
the SQL primitive level:

    UPDATE inventory SET quantity = quantity - %s
        WHERE product_id = %s AND store_id = %s AND quantity >= %s

Test plan:
    1. Pick any tenant=99 product, snapshot original quantity
    2. Force inventory.quantity = 1
    3. Spawn 2 parallel processes, each issues the same UPDATE qty=1
       inside its own BEGIN/COMMIT transaction
    4. Read back final quantity
    5. Restore original quantity (cleanup)

Expected:
    rowCount(p1) + rowCount(p2) == 1   (exactly one winner)
    final inventory.quantity == 0       (drained, not negative)

Usage (must run as a user that can read /etc/runmystore/db.env):
    sudo -u www-data python3 tools/diagnostic/tests/race_test_sale.py

Exit codes: 0=PASS, 1=FAIL, 2=setup error
"""
import sys
import multiprocessing as mp

sys.path.insert(0, '/var/www/runmystore')
from tools.diagnostic.core.db_helpers import get_conn

TENANT = 99
STORE = 48


def attempt_decrement(pid, store, return_dict, key):
    """Run inside a child process. Opens fresh connection, BEGIN/UPDATE/COMMIT."""
    conn = get_conn(autocommit=False)
    try:
        with conn.cursor() as cur:
            conn.begin()
            cur.execute(
                "UPDATE inventory SET quantity = quantity - 1 "
                "WHERE product_id = %s AND store_id = %s AND quantity >= 1",
                (pid, store),
            )
            return_dict[key] = cur.rowcount
            conn.commit()
    except Exception as e:
        return_dict[key] = -1
        return_dict[key + '_err'] = f"{type(e).__name__}: {e}"
    finally:
        try:
            conn.close()
        except Exception:
            pass


def main():
    conn = get_conn(autocommit=True)
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT p.id, i.quantity AS orig_qty "
                "FROM products p "
                "JOIN inventory i ON i.product_id = p.id AND i.store_id = %s "
                "WHERE p.tenant_id = %s AND p.is_active = 1 "
                "ORDER BY i.quantity DESC LIMIT 1",
                (STORE, TENANT),
            )
            row = cur.fetchone()
            if not row:
                print(f"ERROR: no eligible product on tenant={TENANT} store={STORE}",
                      file=sys.stderr)
                return 2
            pid = int(row['id'])
            orig_qty = int(row['orig_qty'])
            cur.execute(
                "UPDATE inventory SET quantity = 1 "
                "WHERE product_id = %s AND store_id = %s",
                (pid, STORE),
            )
            print(f"setup: product_id={pid} orig_qty={orig_qty} forced to qty=1")
    finally:
        conn.close()

    mgr = mp.Manager()
    rd = mgr.dict()
    p1 = mp.Process(target=attempt_decrement, args=(pid, STORE, rd, 'p1'))
    p2 = mp.Process(target=attempt_decrement, args=(pid, STORE, rd, 'p2'))
    p1.start(); p2.start()
    p1.join(); p2.join()

    rc1, rc2 = rd.get('p1', -1), rd.get('p2', -1)
    err1, err2 = rd.get('p1_err'), rd.get('p2_err')
    print(f"result: p1.rowCount={rc1} p2.rowCount={rc2}")
    if err1: print(f"  p1 error: {err1}")
    if err2: print(f"  p2 error: {err2}")

    conn = get_conn()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT quantity FROM inventory "
                "WHERE product_id=%s AND store_id=%s",
                (pid, STORE),
            )
            final = int(cur.fetchone()['quantity'])
        cur2 = conn.cursor()
        cur2.execute(
            "UPDATE inventory SET quantity = %s "
            "WHERE product_id = %s AND store_id = %s",
            (orig_qty, pid, STORE),
        )
        conn.commit()
        cur2.close()
        print(f"final: inventory.quantity={final} (restored to {orig_qty})")
    finally:
        conn.close()

    rc_sum = (rc1 if rc1 >= 0 else 0) + (rc2 if rc2 >= 0 else 0)
    if rc_sum == 1 and final == 0:
        print("PASS: exactly 1 winner, inventory drained to 0 (no over-sell)")
        return 0
    print(f"FAIL: rc_sum={rc_sum} (expected 1), final={final} (expected 0)",
          file=sys.stderr)
    return 1


if __name__ == '__main__':
    sys.exit(main())
