"""
fixtures.py — SQL templates за seed_oracle сценарии за compute-insights.php

Convention:
  - test products в range id=9000..9999
  - test users в range 8000..8099
  - test customers в range 7000..7099
  - {{tenant_id}} placeholder — replace в runtime
"""

PRODUCT_TPL = """
INSERT INTO products (id, tenant_id, code, name, retail_price, cost_price,
                     wholesale_price, min_quantity, is_active, created_at)
VALUES ({pid}, {{{{tenant_id}}}}, '{code}', '{name}', {retail}, {cost},
        {wholesale}, {min_quantity}, 1, NOW() - INTERVAL {days_old} DAY)
ON DUPLICATE KEY UPDATE retail_price=VALUES(retail_price), cost_price=VALUES(cost_price),
                       min_quantity=VALUES(min_quantity), is_active=1;
"""

PRODUCT_VARIANT_TPL = """
INSERT INTO products (id, tenant_id, parent_id, code, name, retail_price, cost_price,
                     wholesale_price, min_quantity, size, color, is_active, created_at)
VALUES ({pid}, {{{{tenant_id}}}}, {parent_id}, '{code}', '{name}', {retail}, {cost},
        {wholesale}, {min_quantity}, {size_sql}, {color_sql}, 1, NOW() - INTERVAL {days_old} DAY)
ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id), retail_price=VALUES(retail_price),
                       cost_price=VALUES(cost_price), size=VALUES(size), color=VALUES(color),
                       is_active=1;
"""

INVENTORY_TPL = """
INSERT INTO inventory (tenant_id, product_id, store_id, quantity, min_quantity)
VALUES ({{{{tenant_id}}}}, {pid}, {store_id}, {qty}, {min_qty})
ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), min_quantity=VALUES(min_quantity);
"""

SALE_TPL = """
INSERT INTO sales (id, tenant_id, store_id, total, status, user_id, customer_id, created_at)
VALUES ({sale_id}, {{{{tenant_id}}}}, {store_id}, {sale_total}, '{status}', {user_id}, {customer_id},
        NOW() - INTERVAL {days_ago} DAY)
ON DUPLICATE KEY UPDATE total=VALUES(total), status=VALUES(status);

INSERT INTO sale_items (sale_id, product_id, unit_price, quantity, discount_pct, total)
VALUES ({sale_id}, {pid}, {unit_price}, {qty}, {discount_pct}, {item_total})
ON DUPLICATE KEY UPDATE unit_price=VALUES(unit_price), quantity=VALUES(quantity),
                       discount_pct=VALUES(discount_pct), total=VALUES(total);
"""

RETURN_TPL = """
INSERT INTO returns (id, tenant_id, sale_id, product_id, quantity, created_at)
VALUES ({return_id}, {{{{tenant_id}}}}, {sale_id}, {pid}, {qty}, NOW() - INTERVAL {days_ago} DAY)
ON DUPLICATE KEY UPDATE quantity=VALUES(quantity);
"""

CUSTOMER_TPL = """
INSERT INTO customers (id, tenant_id, name, phone, is_wholesale, created_at)
VALUES ({cid}, {{{{tenant_id}}}}, '{name}', '{phone}', {is_wholesale}, NOW() - INTERVAL {days_old} DAY)
ON DUPLICATE KEY UPDATE name=VALUES(name);
"""


def make_product(pid, code, name, retail, cost, days_old=30, wholesale=None, min_quantity=0):
    if wholesale is None:
        wholesale = round(retail * 0.8, 2)
    return PRODUCT_TPL.format(
        pid=pid, code=code, name=name,
        retail=retail, cost=cost, wholesale=wholesale,
        min_quantity=min_quantity,
        days_old=days_old,
    )


def make_product_variant(pid, parent_id, code, name, retail, cost, size=None, color=None,
                         days_old=30, wholesale=None, min_quantity=0):
    """Размер/цвят вариант на parent product (used от size_leader сценарии)."""
    if wholesale is None:
        wholesale = round(retail * 0.8, 2)
    size_sql = f"'{size}'" if size else 'NULL'
    color_sql = f"'{color}'" if color else 'NULL'
    return PRODUCT_VARIANT_TPL.format(
        pid=pid, parent_id=parent_id, code=code, name=name,
        retail=retail, cost=cost, wholesale=wholesale,
        min_quantity=min_quantity,
        size_sql=size_sql, color_sql=color_sql,
        days_old=days_old,
    )


def make_parent_with_variations(pid, code, name, retail, cost, days_old=30, wholesale=None):
    """Parent product за variations — задължително has_variations=1."""
    if wholesale is None:
        wholesale = round(retail * 0.8, 2)
    return f"""
INSERT INTO products (id, tenant_id, code, name, retail_price, cost_price,
                     wholesale_price, has_variations, is_active, created_at)
VALUES ({pid}, {{{{tenant_id}}}}, '{code}', '{name}', {retail}, {cost},
        {wholesale}, 1, 1, NOW() - INTERVAL {days_old} DAY)
ON DUPLICATE KEY UPDATE retail_price=VALUES(retail_price), cost_price=VALUES(cost_price),
                       has_variations=1, is_active=1;
"""


def make_inventory(pid, qty, min_qty=2, store_id=48):
    return INVENTORY_TPL.format(pid=pid, qty=qty, min_qty=min_qty, store_id=store_id)


def make_sale(sale_id, pid, unit_price, qty=1, days_ago=5,
              status='completed', store_id=48, user_id=60, customer_id=181,
              discount_pct=0):
    """discount_pct (0-100) се записва в sale_items.discount_pct.
    item_total reflektira disconto (line total = unit*qty*(1-disc/100)).
    sale_total = sum на line totals."""
    line_total = round(unit_price * qty * (1.0 - discount_pct / 100.0), 2)
    return SALE_TPL.format(
        sale_id=sale_id, pid=pid, unit_price=unit_price, qty=qty,
        item_total=line_total, sale_total=line_total,
        status=status, days_ago=days_ago,
        store_id=store_id, user_id=user_id, customer_id=customer_id,
        discount_pct=discount_pct,
    )


def make_return(return_id, sale_id, pid, qty=1, days_ago=4):
    return RETURN_TPL.format(
        return_id=return_id, sale_id=sale_id, pid=pid, qty=qty, days_ago=days_ago,
    )


def make_customer(cid, name, phone='0000000000', is_wholesale=0, days_old=90):
    return CUSTOMER_TPL.format(
        cid=cid, name=name, phone=phone, is_wholesale=is_wholesale, days_old=days_old,
    )


def product_with_sales(pid, code, name, retail, cost, qty_in_stock,
                       sale_count=5, sale_unit_price=None, days_ago_first=5,
                       min_qty=2, store_id=48, product_min_quantity=None):
    """product_min_quantity → колоната products.min_quantity (за below_min_urgent rule).
    Ако None, fallback към min_qty (inventory.min_quantity) за двойна симетрия."""
    if product_min_quantity is None:
        product_min_quantity = min_qty
    sql = []
    sql.append(make_product(pid, code, name, retail, cost,
                            min_quantity=product_min_quantity))
    sql.append(make_inventory(pid, qty_in_stock, min_qty=min_qty, store_id=store_id))
    unit_price = sale_unit_price if sale_unit_price is not None else retail
    base_sale_id = pid * 10
    for i in range(sale_count):
        sql.append(make_sale(base_sale_id + i, pid, unit_price,
                             qty=1, days_ago=max(1, days_ago_first - i)))
    return ''.join(sql)


def product_zombie(pid, code, name, retail, cost, qty_in_stock, days_silent=46,
                   ancient_sale_unit_price=None):
    sql = []
    sql.append(make_product(pid, code, name, retail, cost, days_old=days_silent + 30))
    sql.append(make_inventory(pid, qty_in_stock))
    unit_price = ancient_sale_unit_price if ancient_sale_unit_price is not None else retail
    sql.append(make_sale(pid * 10, pid, unit_price, qty=1, days_ago=days_silent))
    return ''.join(sql)


def product_silent(pid, code, name, retail, cost, qty_in_stock, days_old=60):
    return make_product(pid, code, name, retail, cost, days_old=days_old) + \
           make_inventory(pid, qty_in_stock)
