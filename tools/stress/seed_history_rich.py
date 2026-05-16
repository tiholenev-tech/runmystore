#!/usr/bin/env python3
"""
tools/stress/seed_history_rich.py

Phase 4 на rich reseed (S148-rich):

  - 180 дни sales history с persona-aware distribution
  - 150-200 deliveries spread across периода
  - stock_movements за всяка sale (out) и delivery (in)
  - Recompute inventory = SUM(movements) per (product, store)

Sales volume per persona (от rich_persona_index.json sales_tag):
  none   → 0
  low    → 0-2 sales / 180д
  normal → 2-15 sales / 180д
  high   → 15-40 sales / 180д (повече в последните 60д)
  top    → 40-100 sales / 180д (60% в последните 30д)

Special handling:
  - zero_stock persona: sales concentrated в last 30 days,
    delivery 35-60 дни ago запълва stock, sales го изчерпват до 0
  - top_sales: 60% в last 30d → triggers top_sales signal
  - aging/zombie/new_week: 0 sales

Idempotent: чете /tools/stress/data/rich_persona_index.json
(трябва seed_products_rich.py да е run-нат първо).

Usage:
    python3 tools/stress/seed_history_rich.py --tenant 7              # dry-run
    python3 tools/stress/seed_history_rich.py --tenant 7 --apply
"""
import argparse
import json
import random
import sys
from datetime import datetime, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _db import (
    assert_stress_tenant,
    connect,
    dry_run_log,
    load_db_config,
    seed_rng,
)

HISTORY_DAYS = 180

# (lo, hi) total sales over HISTORY_DAYS
SALES_PROFILE = {
    "none":   (0, 0),
    "low":    (0, 2),
    "normal": (2, 15),
    "high":   (15, 40),
    "top":    (40, 100),
}

# Fraction of sales that should land in last 30 days
RECENT_30D_BIAS = {
    "none":   0.0,
    "low":    0.3,
    "normal": 0.3,
    "high":   0.5,
    "top":    0.6,
}

# zero_stock persona override: minimum 5 sales in last 30d to trigger
# zero_stock_with_sales signal
ZERO_STOCK_MIN_30D_SALES = 5
ZERO_STOCK_MIN_TOTAL = 8  # 5 in last 30d + 3 earlier

# Discounts — 20% of sales have a discount; 5% have >20% to trigger seller_discount_killer
def random_discount() -> float:
    r = random.random()
    if r < 0.05:
        return round(random.uniform(20.0, 40.0), 2)  # seller_discount_killer trigger
    if r < 0.20:
        return round(random.uniform(5.0, 19.0), 2)
    return 0.0


def random_sale_date_for_persona(persona: str, sales_tag: str) -> datetime:
    """Returns datetime within history window, biased per persona."""
    now = datetime.now()
    if persona == "zero_stock":
        # All sales last 30 days
        days_ago = random.randint(0, 29)
        return now - timedelta(days=days_ago, hours=random.randint(8, 22))
    bias = RECENT_30D_BIAS.get(sales_tag, 0.3)
    if random.random() < bias:
        days_ago = random.randint(0, 29)
    else:
        days_ago = random.randint(30, HISTORY_DAYS - 1)
    return now - timedelta(days=days_ago, hours=random.randint(8, 22))


def load_persona_index() -> list[dict]:
    p = Path(__file__).resolve().parent / "data" / "rich_persona_index.json"
    if not p.exists():
        sys.exit(f"[REFUSE] {p} липсва. Run seed_products_rich.py първо.")
    return json.loads(p.read_text())


def resolve_product_metadata(conn, tenant_id: int) -> dict:
    """code → (product_id, cost_price, retail_price, supplier_id)"""
    out = {}
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, code, cost_price, retail_price, supplier_id "
            "FROM products WHERE tenant_id=%s",
            (tenant_id,),
        )
        for r in cur.fetchall():
            out[r["code"]] = {
                "id": int(r["id"]),
                "cost": float(r["cost_price"] or 0),
                "retail": float(r["retail_price"] or 0),
                "supplier_id": r["supplier_id"],
            }
    return out


def resolve_inventory(conn, tenant_id: int) -> dict:
    """product_id → list of (store_id, inv_id, initial_qty)"""
    out = {}
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, store_id, product_id, quantity FROM inventory WHERE tenant_id=%s",
            (tenant_id,),
        )
        for r in cur.fetchall():
            pid = int(r["product_id"])
            out.setdefault(pid, []).append((int(r["store_id"]), int(r["id"]), float(r["quantity"])))
    return out


def resolve_users(conn, tenant_id: int) -> list[int]:
    with conn.cursor() as cur:
        cur.execute("SELECT id FROM users WHERE tenant_id=%s", (tenant_id,))
        return [int(r["id"]) for r in cur.fetchall()]


def resolve_suppliers(conn, tenant_id: int) -> list[int]:
    with conn.cursor() as cur:
        cur.execute("SELECT id FROM suppliers WHERE tenant_id=%s", (tenant_id,))
        return [int(r["id"]) for r in cur.fetchall()]


def plan_sales(persona_index: list[dict]) -> list[dict]:
    """Return per-product target sales count + date strategy."""
    plan = []
    for entry in persona_index:
        persona = entry["persona"]
        sales_tag = entry["sales_tag"]
        # Special case: zero_stock — force 5-12 sales in last 30d
        if persona == "zero_stock":
            total = random.randint(ZERO_STOCK_MIN_TOTAL, 12)
        else:
            lo, hi = SALES_PROFILE[sales_tag]
            total = random.randint(lo, hi)
        if total == 0:
            continue
        plan.append({**entry, "target_sales": total})
    return plan


def insert_sales(conn, tenant_id: int, plan: list[dict],
                 product_meta: dict, inventory: dict,
                 stores: list[int], users: list[int]) -> dict:
    """
    For each product in plan, generate target_sales sale rows.
    Sales group into actual sale baskets — each basket has 1-4 items.
    Simpler: 1 sale per item (1 sale_item per sale) — keeps it simple and fast.
    """
    sales_inserted = 0
    items_inserted = 0
    movements_inserted = 0
    skipped_no_inv = 0
    skipped_no_meta = 0

    with conn.cursor() as cur:
        for entry in plan:
            meta = product_meta.get(entry["code"])
            if not meta:
                skipped_no_meta += 1
                continue
            inv_rows = inventory.get(meta["id"]) or []
            if not inv_rows:
                skipped_no_inv += 1
                continue
            for _ in range(entry["target_sales"]):
                # pick a store with this product
                store_id, inv_id, _initial = random.choice(inv_rows)
                created_at = random_sale_date_for_persona(entry["persona"], entry["sales_tag"])
                qty = random.randint(1, 3)
                if entry["persona"] == "top_sales":
                    qty = random.randint(1, 5)
                unit_price = meta["retail"]
                discount_pct = random_discount()
                discount_amount = round(unit_price * qty * discount_pct / 100, 2)
                subtotal = round(unit_price * qty, 2)
                total = round(subtotal - discount_amount, 2)
                user_id = random.choice(users) if users else None

                cur.execute(
                    """
                    INSERT INTO sales
                        (tenant_id, store_id, user_id, type, payment_method,
                         subtotal, discount_pct, discount_amount, total, paid_amount,
                         status, is_test_data, created_at, updated_at)
                    VALUES (%s,%s,%s,'retail',%s,
                            %s,%s,%s,%s,%s,
                            'completed',1,%s,%s)
                    """,
                    (
                        tenant_id, store_id, user_id,
                        random.choice(["cash", "card", "card", "card"]),
                        subtotal, discount_pct, discount_amount, total, total,
                        created_at, created_at,
                    ),
                )
                sale_id = int(cur.lastrowid)
                sales_inserted += 1

                cur.execute(
                    """
                    INSERT INTO sale_items
                        (sale_id, is_test_data, product_id, quantity,
                         unit_price, cost_price, discount_pct, total)
                    VALUES (%s,1,%s,%s,%s,%s,%s,%s)
                    """,
                    (sale_id, meta["id"], qty, unit_price, meta["cost"],
                     discount_pct, total),
                )
                items_inserted += 1

                cur.execute(
                    """
                    INSERT INTO stock_movements
                        (tenant_id, store_id, product_id, user_id, type,
                         quantity, price, reference_id, reference_type, created_at)
                    VALUES (%s,%s,%s,%s,'sale',%s,%s,%s,'sales',%s)
                    """,
                    (tenant_id, store_id, meta["id"], user_id, qty,
                     unit_price, sale_id, created_at),
                )
                movements_inserted += 1

                if sales_inserted % 1000 == 0:
                    conn.commit()
                    print(f"  ... {sales_inserted} sales")
    conn.commit()
    return {
        "sales": sales_inserted,
        "sale_items": items_inserted,
        "movements_from_sales": movements_inserted,
        "skipped_no_inv": skipped_no_inv,
        "skipped_no_meta": skipped_no_meta,
    }


def insert_deliveries(conn, tenant_id: int, persona_index: list[dict],
                      product_meta: dict, stores_by_name: dict,
                      suppliers: list[int], users: list[int]) -> dict:
    """
    150-200 deliveries spread over HISTORY_DAYS. Each delivery has 5-20
    product items. Also creates a special delivery for zero_stock products
    (35-60d ago) ensuring they had stock before being sold out.
    """
    if not suppliers:
        return {"deliveries": 0, "delivery_items": 0, "movements_from_deliveries": 0}

    warehouse_id = stores_by_name.get("Склад") or list(stores_by_name.values())[0]
    store_ids = list(stores_by_name.values())

    deliveries_n = 0
    items_n = 0
    movements_n = 0

    # ─── Special: zero_stock resupply (35-60d ago) ─────────────────
    zero_stock_codes = [e["code"] for e in persona_index if e["persona"] == "zero_stock"]
    zs_meta = [product_meta[c] for c in zero_stock_codes if c in product_meta]
    if zs_meta:
        # Split into ~5 deliveries
        batch_size = max(20, len(zs_meta) // 5)
        with conn.cursor() as cur:
            for batch_idx in range(0, len(zs_meta), batch_size):
                batch = zs_meta[batch_idx:batch_idx + batch_size]
                d_date = datetime.now() - timedelta(days=random.randint(35, 60))
                supplier_id = random.choice(suppliers)
                cur.execute(
                    """
                    INSERT INTO deliveries
                        (tenant_id, store_id, supplier_id, user_id, number,
                         total, status, payment_status, delivered_at, created_at)
                    VALUES (%s,%s,%s,%s,%s,%s,'committed','paid',%s,%s)
                    """,
                    (tenant_id, warehouse_id, supplier_id,
                     random.choice(users) if users else None,
                     f"ZS-{batch_idx:04d}", 0, d_date, d_date),
                )
                d_id = int(cur.lastrowid)
                deliveries_n += 1

                total = 0.0
                for m in batch:
                    qty = random.randint(10, 30)  # enough to absorb future sales
                    cost = m["cost"] if m["cost"] > 0 else random.uniform(5, 30)
                    line_total = round(qty * cost, 2)
                    total += line_total
                    cur.execute(
                        """
                        INSERT INTO delivery_items
                            (tenant_id, store_id, supplier_id, delivery_id, product_id,
                             quantity, cost_price, total, currency_code, created_at)
                        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,'EUR',%s)
                        """,
                        (tenant_id, warehouse_id, supplier_id, d_id, m["id"],
                         qty, cost, line_total, d_date),
                    )
                    items_n += 1
                    cur.execute(
                        """
                        INSERT INTO stock_movements
                            (tenant_id, store_id, product_id, type, quantity, price,
                             reference_id, reference_type, created_at)
                        VALUES (%s,%s,%s,'delivery',%s,%s,%s,'deliveries',%s)
                        """,
                        (tenant_id, warehouse_id, m["id"], qty, cost, d_id, d_date),
                    )
                    movements_n += 1
                cur.execute("UPDATE deliveries SET total=%s WHERE id=%s", (total, d_id))
        conn.commit()
        print(f"  zero_stock resupply: {len(zs_meta)} products in {deliveries_n} deliveries")

    # ─── Regular deliveries (150 total spread) ─────────────────────
    all_products = list(product_meta.values())
    target_extra = random.randint(140, 180)
    with conn.cursor() as cur:
        for i in range(target_extra):
            d_date = datetime.now() - timedelta(days=random.randint(7, HISTORY_DAYS - 1))
            supplier_id = random.choice(suppliers)
            store_id = random.choice(store_ids)
            cur.execute(
                """
                INSERT INTO deliveries
                    (tenant_id, store_id, supplier_id, user_id, number,
                     total, status, payment_status, delivered_at, created_at)
                VALUES (%s,%s,%s,%s,%s,%s,'committed','paid',%s,%s)
                """,
                (tenant_id, store_id, supplier_id,
                 random.choice(users) if users else None,
                 f"D-{i:05d}", 0, d_date, d_date),
            )
            d_id = int(cur.lastrowid)
            deliveries_n += 1

            items_in_delivery = random.randint(5, 20)
            batch = random.sample(all_products, min(items_in_delivery, len(all_products)))
            total = 0.0
            for m in batch:
                qty = random.randint(5, 20)
                cost = m["cost"] if m["cost"] > 0 else random.uniform(5, 30)
                line_total = round(qty * cost, 2)
                total += line_total
                cur.execute(
                    """
                    INSERT INTO delivery_items
                        (tenant_id, store_id, supplier_id, delivery_id, product_id,
                         quantity, cost_price, total, currency_code, created_at)
                    VALUES (%s,%s,%s,%s,%s,%s,%s,%s,'EUR',%s)
                    """,
                    (tenant_id, store_id, supplier_id, d_id, m["id"],
                     qty, cost, line_total, d_date),
                )
                items_n += 1
                cur.execute(
                    """
                    INSERT INTO stock_movements
                        (tenant_id, store_id, product_id, type, quantity, price,
                         reference_id, reference_type, created_at)
                    VALUES (%s,%s,%s,'delivery',%s,%s,%s,'deliveries',%s)
                    """,
                    (tenant_id, store_id, m["id"], qty, cost, d_id, d_date),
                )
                movements_n += 1
            cur.execute("UPDATE deliveries SET total=%s WHERE id=%s", (total, d_id))
    conn.commit()
    return {
        "deliveries": deliveries_n,
        "delivery_items": items_n,
        "movements_from_deliveries": movements_n,
    }


def enforce_persona_inventory(conn, tenant_id: int, persona_index: list[dict]) -> dict:
    """
    Override inventory.quantity за persona-specific targets ПОСЛЕ recompute,
    защото sales могат да изчерпят малките initial qty (1-4). Гарантира че
    critical_low / below_min signal pills имат предвидено coverage.
    """
    targets = {
        "critical_low": (1, 2),       # qty 1 or 2 → triggers critical_low pill
        "below_min":    (3, 3),       # qty=3 with min_quantity=5 → triggers below_min
    }
    code_to_persona = {e["code"]: e["persona"] for e in persona_index}
    fixed = {"critical_low": 0, "below_min": 0}
    with conn.cursor() as cur:
        for persona, (lo, hi) in targets.items():
            codes = [c for c, p in code_to_persona.items() if p == persona]
            for code in codes:
                cur.execute(
                    """SELECT i.id FROM inventory i JOIN products p ON p.id=i.product_id
                       WHERE p.tenant_id=%s AND p.code=%s ORDER BY i.id LIMIT 1""",
                    (tenant_id, code),
                )
                row = cur.fetchone()
                if not row:
                    continue
                target = random.randint(lo, hi)
                if persona == "below_min":
                    cur.execute(
                        """UPDATE products p JOIN inventory i ON p.id=i.product_id
                           SET p.min_quantity=5, i.quantity=%s WHERE i.id=%s""",
                        (target, row["id"]),
                    )
                else:
                    cur.execute(
                        "UPDATE inventory SET quantity=%s WHERE id=%s",
                        (target, row["id"]),
                    )
                fixed[persona] += 1
    conn.commit()
    return fixed


def recompute_inventory(conn, tenant_id: int) -> dict:
    """
    inventory.quantity = SUM(movements.quantity_signed) per (store, product).
    Movements 'sale','out','transfer_out','scrap','waste' = negative.
    Movements 'in','delivery','transfer_in','return' = positive.
    Initial seed used type='delivery' so it's positive.

    NOTE: floor at 0 — никога negative.
    """
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE inventory inv
            JOIN (
                SELECT store_id, product_id,
                       SUM(CASE
                           WHEN type IN ('sale','out','transfer_out','scrap','waste') THEN -quantity
                           ELSE quantity
                       END) AS net
                FROM stock_movements
                WHERE tenant_id=%s
                GROUP BY store_id, product_id
            ) m ON m.store_id=inv.store_id AND m.product_id=inv.product_id
            SET inv.quantity = GREATEST(m.net, 0)
            WHERE inv.tenant_id=%s
            """,
            (tenant_id, tenant_id),
        )
        affected = cur.rowcount
    conn.commit()
    return {"inventory_rows_recomputed": affected}


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--tenant", type=int, required=True)
    ap.add_argument("--apply", action="store_true")
    args = ap.parse_args()
    seed_rng()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=False)
    assert_stress_tenant(args.tenant, conn)

    persona_index = load_persona_index()
    plan = plan_sales(persona_index)
    target_sales = sum(p["target_sales"] for p in plan)
    print(f"[PLAN] tenant_id={args.tenant} — {len(persona_index)} products → "
          f"{len(plan)} with sales, target ≈{target_sales} sales over {HISTORY_DAYS}д")

    if not args.apply:
        dist = {}
        for p in plan:
            dist[p["persona"]] = dist.get(p["persona"], 0) + p["target_sales"]
        for persona, n in dist.items():
            print(f"  {persona:<14} sales: {n}")
        out = dry_run_log("seed_history_rich", {
            "action": "dry-run",
            "tenant_id": args.tenant,
            "target_sales": target_sales,
            "by_persona": dist,
            "history_days": HISTORY_DAYS,
        })
        print(f"[DRY-RUN] {out}")
        return 0

    print("[INSERT] sales + sale_items + movements ...")
    product_meta = resolve_product_metadata(conn, args.tenant)
    inventory = resolve_inventory(conn, args.tenant)
    users = resolve_users(conn, args.tenant)
    suppliers = resolve_suppliers(conn, args.tenant)
    with conn.cursor() as cur:
        cur.execute("SELECT id, name FROM stores WHERE tenant_id=%s", (args.tenant,))
        stores_rows = cur.fetchall()
    stores_by_name = {r["name"]: int(r["id"]) for r in stores_rows}
    store_ids = list(stores_by_name.values())

    sales_result = insert_sales(conn, args.tenant, plan, product_meta, inventory,
                                store_ids, users)
    print(f"[OK] sales={sales_result['sales']} items={sales_result['sale_items']} "
          f"sale-movements={sales_result['movements_from_sales']}")

    print("[INSERT] deliveries + delivery_items + movements ...")
    deliv_result = insert_deliveries(conn, args.tenant, persona_index,
                                     product_meta, stores_by_name, suppliers, users)
    print(f"[OK] deliveries={deliv_result['deliveries']} "
          f"items={deliv_result['delivery_items']} "
          f"deliv-movements={deliv_result['movements_from_deliveries']}")

    print("[RECOMPUTE] inventory.quantity = SUM(movements) ...")
    rec = recompute_inventory(conn, args.tenant)
    print(f"[OK] {rec['inventory_rows_recomputed']} inventory rows updated")

    print("[ENFORCE] critical_low / below_min persona targets ...")
    fixed = enforce_persona_inventory(conn, args.tenant, persona_index)
    print(f"[OK] enforced critical_low={fixed['critical_low']} below_min={fixed['below_min']}")

    dry_run_log("seed_history_rich", {
        "action": "applied", "tenant_id": args.tenant,
        **sales_result, **deliv_result, **rec, "persona_enforced": fixed,
    })
    return 0


if __name__ == "__main__":
    sys.exit(main() or 0)
