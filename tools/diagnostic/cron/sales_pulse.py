#!/usr/bin/env python3
"""
sales_pulse.py — генерира 5-15 random продажби на tenant=7.
"""

import sys
import random
sys.path.insert(0, '/var/www/runmystore')
from tools.diagnostic.core.db_helpers import (
    transaction, fetchall, fetchone, assert_safe_tenant,
)


TENANT_ID = 7
MIN_SALES = 5
MAX_SALES = 15
RETURN_RATE = 0.10


def pick_random_products(tenant_id, n):
    return fetchall("""
        SELECT p.id, p.retail_price, COALESCE(i.quantity, 0) AS qty
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = 1
        WHERE p.tenant_id = %s
          AND p.is_active = 1
          AND p.retail_price > 0
          AND (p.id < 9000 OR p.id > 9999)
        ORDER BY RAND()
        LIMIT %s
    """, (tenant_id, n))


def get_default_user_customer(tenant_id):
    u = fetchone("SELECT id FROM users WHERE tenant_id=%s LIMIT 1", (tenant_id,))
    c = fetchone("SELECT id FROM customers WHERE tenant_id=%s LIMIT 1", (tenant_id,))
    return (u['id'] if u else None, c['id'] if c else None)


def random_hour():
    weights = {
        10:11, 11:11, 12:13, 13:14, 14:14, 15:15, 16:16,
        17:16, 18:14, 19:12, 9:8, 8:5, 20:4, 7:2, 21:1,
    }
    pool = [h for h, w in weights.items() for _ in range(w)]
    return random.choice(pool)


def main():
    assert_safe_tenant(TENANT_ID)

    products = pick_random_products(TENANT_ID, 50)
    if not products:
        print("Няма products на tенант=7 — abort", file=sys.stderr)
        return 1

    user_id, customer_id = get_default_user_customer(TENANT_ID)
    n_sales = random.randint(MIN_SALES, MAX_SALES)
    inserted = 0
    returns = 0

    with transaction() as c:
        cur = c.cursor()
        for _ in range(n_sales):
            p = random.choice(products)
            pid = int(p['id'])
            unit_price = float(p['retail_price'])
            if random.random() < 0.20:
                unit_price = round(unit_price * (0.85 + random.random() * 0.15), 2)
            qty = 1 if random.random() > 0.15 else random.randint(2, 4)
            total = round(unit_price * qty, 2)
            hour = random_hour()

            cur.execute("""
                INSERT INTO sales (tenant_id, store_id, total, status, user_id, customer_id, created_at)
                VALUES (%s, 1, %s, 'completed', %s, %s,
                        DATE_ADD(DATE(NOW()), INTERVAL %s HOUR))
            """, (TENANT_ID, total, user_id, customer_id, hour))
            sale_id = cur.lastrowid

            cur.execute("""
                INSERT INTO sale_items (sale_id, product_id, unit_price, quantity)
                VALUES (%s, %s, %s, %s)
            """, (sale_id, pid, unit_price, qty))

            cur.execute("""
                UPDATE inventory SET quantity = GREATEST(quantity - %s, 0)
                WHERE product_id = %s AND store_id = 1
            """, (qty, pid))

            inserted += 1

            if random.random() < RETURN_RATE:
                try:
                    cur.execute("""
                        INSERT INTO returns (sale_id, product_id, quantity, created_at)
                        VALUES (%s, %s, %s, DATE_ADD(DATE(NOW()), INTERVAL %s HOUR))
                    """, (sale_id, pid, qty, min(hour + 1, 23)))
                    returns += 1
                except Exception:
                    pass
        cur.close()

    print("sales_pulse: " + str(inserted) + " sales, " + str(returns) + " returns @ tenant=" + str(TENANT_ID))
    return 0


if __name__ == '__main__':
    sys.exit(main())
