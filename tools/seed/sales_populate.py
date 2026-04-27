#!/usr/bin/env python3
"""
sales_populate.py — realistic sales seeder for tenant=99 (S87.SEED.SALES).

Generates historical sales with patterns close enough to organic flow
that compute-insights cron produces non-trivial signals on tenant=99:

    - Peak hours 11-13 + 17-19 (60% of sales)
    - Weekend boost: Sat/Sun = 1.5× weekday volume
    - Avg basket ~1.8 items
    - Return rate ~5% (status='returned')
    - Discount distribution 70% none / 25% 5-15% / 5% 30-50%
    - Customer mix 30% repeat / 70% walk-in (customer_id NULL)

Companion to TESTING_LOOP (tools/testing_loop/, Code #2) which runs this
daily for organic insight generation on tenant=99.

Usage:
    python3 sales_populate.py --tenant 99 --count 15
    python3 sales_populate.py --tenant 99 --backfill-days 60
    python3 sales_populate.py --tenant 99 --count 15 --dry-run
    python3 sales_populate.py --tenant 99 --backfill-days 30 --count 240

Safety:
    - tenant guard: refuses anything outside ALLOWED_TENANTS = {7, 99}
    - default --tenant=99
    - --confirm required to seed tenant=7 (live tenant)
    - all writes wrapped in a single transaction (commit or rollback)

Idempotency:
    sales.note is set to '[seed-s87]' on every row this script writes,
    so seeded data can be located + cleaned by a follow-up script.
"""
from __future__ import annotations

import argparse
import random
import sys
from datetime import datetime, timedelta

import pymysql
import pymysql.cursors

ENV_PATH = "/etc/runmystore/db.env"
ALLOWED_TENANTS = {7, 99}
NOTE_MARKER = "[seed-s87]"

# Realistic distributions ----------------------------------------------------
PEAK_HOURS = (11, 12, 13, 17, 18, 19)
PEAK_SHARE = 0.60          # share of sales falling in peak hours
BUSINESS_HOURS = list(range(9, 22))  # 09:00 - 21:59
WEEKEND_BOOST = 1.5        # Sat/Sun multiplier for backfill day weights
RETURN_RATE = 0.05
REPEAT_CUSTOMER_RATE = 0.30
SELLER_BIAS = 0.80         # 80% sales by sellers, 20% by owner

BASKET_DIST = [(1, 0.45), (2, 0.32), (3, 0.15), (4, 0.06), (5, 0.02)]  # avg ≈ 1.88
ITEM_QTY_DIST = [(1, 0.85), (2, 0.12), (3, 0.03)]                      # avg ≈ 1.18
DISCOUNT_DIST = [
    ("none", 0.70, 0.0, 0.0),
    ("low",  0.25, 5.0, 15.0),
    ("high", 0.05, 30.0, 50.0),
]
PAYMENT_DIST = [
    ("cash", 0.60),
    ("card", 0.35),
    ("bank_transfer", 0.04),
    ("deferred", 0.01),
]


# ─── DB ────────────────────────────────────────────────────────────────────
def parse_env(path: str = ENV_PATH) -> dict:
    cfg: dict = {}
    with open(path, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            k, _, v = line.partition("=")
            cfg[k.strip()] = v.strip().strip('"').strip("'")
    cfg.setdefault("DB_HOST", "127.0.0.1")
    cfg.setdefault("DB_NAME", "runmystore")
    cfg.setdefault("DB_PORT", "3306")
    return cfg


def connect():
    cfg = parse_env()
    return pymysql.connect(
        host=cfg["DB_HOST"],
        port=int(cfg["DB_PORT"]),
        user=cfg["DB_USER"],
        password=cfg["DB_PASS"],
        database=cfg["DB_NAME"],
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False,
    )


# ─── Weighted choice helpers ───────────────────────────────────────────────
def weighted_pick(rng: random.Random, choices):
    """choices: iterable of (value, weight) — returns value."""
    pairs = list(choices)
    total = sum(w for _, w in pairs)
    if total <= 0:
        return pairs[0][0]
    r = rng.uniform(0, total)
    cum = 0.0
    for v, w in pairs:
        cum += w
        if r <= cum:
            return v
    return pairs[-1][0]


def pick_basket_size(rng):     return weighted_pick(rng, BASKET_DIST)
def pick_item_qty(rng):        return weighted_pick(rng, ITEM_QTY_DIST)
def pick_payment(rng):         return weighted_pick(rng, PAYMENT_DIST)


def pick_discount_pct(rng) -> float:
    bucket = weighted_pick(rng, [(name, w) for name, w, _, _ in DISCOUNT_DIST])
    for name, _w, lo, hi in DISCOUNT_DIST:
        if name == bucket:
            return 0.0 if hi == 0 else round(rng.uniform(lo, hi), 2)
    return 0.0


# ─── Timestamp distribution ────────────────────────────────────────────────
def pick_hour(rng: random.Random) -> int:
    if rng.random() < PEAK_SHARE:
        return rng.choice(PEAK_HOURS)
    off_peak = [h for h in BUSINESS_HOURS if h not in PEAK_HOURS]
    return rng.choice(off_peak)


def make_timestamp(rng, day) -> datetime:
    h = pick_hour(rng)
    m = rng.randint(0, 59)
    s = rng.randint(0, 59)
    return datetime.combine(day, datetime.min.time()).replace(
        hour=h, minute=m, second=s
    )


def distribute_across_days(rng, total_count: int, days: int) -> dict:
    today = datetime.now().date()
    weights = []
    day_list = []
    for off in range(days):
        d = today - timedelta(days=off)
        wt = WEEKEND_BOOST if d.weekday() in (5, 6) else 1.0
        day_list.append(d)
        weights.append(wt)
    counts = {d: 0 for d in day_list}
    total_w = sum(weights)
    for _ in range(total_count):
        r = rng.uniform(0, total_w)
        cum = 0.0
        for d, w in zip(day_list, weights):
            cum += w
            if r <= cum:
                counts[d] += 1
                break
    return counts


# ─── Catalog fetchers ──────────────────────────────────────────────────────
def fetch_default_store(cur, tenant_id: int) -> int:
    cur.execute(
        """
        SELECT id FROM stores
        WHERE tenant_id = %s AND deleted_at IS NULL AND is_active = 1
        ORDER BY id LIMIT 1
        """,
        (tenant_id,),
    )
    row = cur.fetchone()
    if not row:
        raise RuntimeError(f"no active store for tenant={tenant_id}")
    return int(row["id"])


def fetch_users(cur, tenant_id: int) -> list:
    cur.execute(
        """
        SELECT id, role FROM users
        WHERE tenant_id = %s AND is_active = 1 AND deleted_at IS NULL
        """,
        (tenant_id,),
    )
    return list(cur.fetchall())


def fetch_customers(cur, tenant_id: int) -> list:
    cur.execute(
        """
        SELECT id FROM customers
        WHERE tenant_id = %s AND is_active = 1 AND deleted_at IS NULL
        """,
        (tenant_id,),
    )
    return [int(r["id"]) for r in cur.fetchall()]


def fetch_eligible_products(cur, tenant_id: int, store_id: int) -> list:
    """Products with positive inventory in the target store."""
    cur.execute(
        """
        SELECT p.id, p.retail_price, p.cost_price, p.discount_pct,
               i.id AS inv_id, i.quantity
        FROM products p
        JOIN inventory i
          ON i.product_id = p.id
         AND i.store_id   = %s
         AND i.tenant_id  = %s
        WHERE p.tenant_id = %s
          AND p.is_active = 1
          AND p.parent_id IS NULL
          AND i.quantity > 0
        """,
        (store_id, tenant_id, tenant_id),
    )
    rows = []
    for r in cur.fetchall():
        rows.append({
            "id":          int(r["id"]),
            "inv_id":      int(r["inv_id"]),
            "retail":      float(r["retail_price"]),
            "cost":        float(r["cost_price"] or 0),
            "prod_disc":   float(r["discount_pct"] or 0),
            "qty":         float(r["quantity"]),
        })
    return rows


# ─── Pickers ───────────────────────────────────────────────────────────────
def pick_user(rng: random.Random, users: list) -> int:
    sellers = [u["id"] for u in users if u["role"] == "seller"]
    owners  = [u["id"] for u in users if u["role"] == "owner"]
    if sellers and rng.random() < SELLER_BIAS:
        return rng.choice(sellers)
    if owners:
        return rng.choice(owners)
    return users[0]["id"]


def pick_customer(rng, customers):
    if customers and rng.random() < REPEAT_CUSTOMER_RATE:
        return rng.choice(customers)
    return None


def pick_product(rng: random.Random, eligible: list):
    """Weighted by current quantity — proxy for popularity."""
    weights = [p["qty"] for p in eligible]
    total = sum(weights)
    if total <= 0:
        return None
    r = rng.uniform(0, total)
    cum = 0.0
    for p, w in zip(eligible, weights):
        cum += w
        if r <= cum:
            return p
    return eligible[-1]


# ─── Sale generation ───────────────────────────────────────────────────────
def build_sale(rng, pool, store_id, user_id, customer_id, ts):
    """
    Returns (sale_dict, items_list, is_returned) or None if no items
    could be picked. Mutates `pool` in-memory by decrementing qty so
    successive sales reflect updated stock.
    """
    target_n = pick_basket_size(rng)
    items = []
    seen = set()
    for _ in range(target_n):
        live = [p for p in pool if p["id"] not in seen and p["qty"] > 0]
        if not live:
            break
        p = pick_product(rng, live)
        if p is None:
            break
        qty = pick_item_qty(rng)
        qty = min(qty, int(p["qty"]))
        if qty < 1:
            continue
        seen.add(p["id"])
        line_total = round(p["retail"] * qty * (1 - p["prod_disc"] / 100), 2)
        items.append({
            "product_id":   p["id"],
            "inv_id":       p["inv_id"],
            "quantity":     qty,
            "unit_price":   round(p["retail"], 4),
            "cost_price":   round(p["cost"], 2),
            "discount_pct": p["prod_disc"],
            "total":        line_total,
        })
        p["qty"] -= qty

    if not items:
        return None

    subtotal = round(sum(it["total"] for it in items), 2)
    sale_disc = pick_discount_pct(rng)
    discount_amount = round(subtotal * sale_disc / 100, 2)
    total = round(subtotal - discount_amount, 2)
    is_returned = rng.random() < RETURN_RATE
    payment = pick_payment(rng)
    paid = 0.0 if (payment == "deferred" and not is_returned) else total

    sale = {
        "store_id":        store_id,
        "user_id":         user_id,
        "customer_id":     customer_id,
        "type":            "retail",
        "payment_method":  payment,
        "subtotal":        subtotal,
        "discount_pct":    sale_disc,
        "discount_amount": discount_amount,
        "total":           total,
        "paid_amount":     paid,
        "status":          "returned" if is_returned else "completed",
        "note":            NOTE_MARKER,
        "created_at":      ts.strftime("%Y-%m-%d %H:%M:%S"),
    }
    return sale, items, is_returned


# ─── Persistence ───────────────────────────────────────────────────────────
def insert_sale(cur, tenant_id: int, sale: dict, items: list) -> int:
    cur.execute(
        """
        INSERT INTO sales
            (tenant_id, store_id, user_id, customer_id, type, payment_method,
             subtotal, discount_pct, discount_amount, total, paid_amount,
             status, note, created_at)
        VALUES
            (%(tenant_id)s, %(store_id)s, %(user_id)s, %(customer_id)s,
             %(type)s, %(payment_method)s, %(subtotal)s, %(discount_pct)s,
             %(discount_amount)s, %(total)s, %(paid_amount)s, %(status)s,
             %(note)s, %(created_at)s)
        """,
        {**sale, "tenant_id": tenant_id},
    )
    sale_id = cur.lastrowid
    for it in items:
        cur.execute(
            """
            INSERT INTO sale_items
                (sale_id, product_id, quantity, unit_price, cost_price,
                 discount_pct, total)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            """,
            (sale_id, it["product_id"], it["quantity"], it["unit_price"],
             it["cost_price"], it["discount_pct"], it["total"]),
        )
    return sale_id


def apply_inventory(cur, items: list) -> None:
    for it in items:
        cur.execute(
            "UPDATE inventory SET quantity = quantity - %s WHERE id = %s",
            (it["quantity"], it["inv_id"]),
        )


# ─── Main ──────────────────────────────────────────────────────────────────
def parse_args(argv=None):
    ap = argparse.ArgumentParser(
        description="Realistic sales seeder for tenant=99 (S87.SEED.SALES)."
    )
    ap.add_argument("--tenant", type=int, default=99,
                    help="Tenant id (allowed: 7 or 99). Default: 99")
    ap.add_argument("--count", type=int, default=None,
                    help="Number of sales to seed. With --backfill-days, "
                         "this is the total spread across the window.")
    ap.add_argument("--backfill-days", type=int, default=None,
                    help="Distribute sales across the past N days "
                         "(weekend-boosted). Default total = days × 8.")
    ap.add_argument("--dry-run", action="store_true",
                    help="Plan only — no INSERTs, no inventory updates")
    ap.add_argument("--confirm", action="store_true",
                    help="Required to seed tenant=7 (live)")
    ap.add_argument("--seed", type=int, default=None,
                    help="Seed RNG for reproducibility")
    return ap.parse_args(argv)


def plan_timestamps(rng, args) -> list:
    """Returns chronologically-sorted list of datetime objects."""
    if args.backfill_days:
        days = args.backfill_days
        total = args.count if args.count else days * 8
        per_day = distribute_across_days(rng, total, days)
        out = []
        for d, n in per_day.items():
            for _ in range(n):
                out.append(make_timestamp(rng, d))
        out.sort()
        return out

    # Live mode — N sales clustered within last 30 minutes
    now = datetime.now()
    n = args.count or 0
    out = [
        now - timedelta(minutes=rng.randint(0, 30),
                        seconds=rng.randint(0, 59))
        for _ in range(n)
    ]
    out.sort()
    return out


def main(argv=None) -> int:
    args = parse_args(argv)

    if args.tenant not in ALLOWED_TENANTS:
        print(f"refusing: tenant={args.tenant} not in {sorted(ALLOWED_TENANTS)}",
              file=sys.stderr)
        return 2
    if args.tenant == 7 and not args.confirm:
        print("refusing: tenant=7 requires --confirm flag (live data)",
              file=sys.stderr)
        return 2
    if not args.count and not args.backfill_days:
        print("error: provide --count or --backfill-days (or both)",
              file=sys.stderr)
        return 2

    rng = random.Random(args.seed) if args.seed is not None else random.Random()

    timestamps = plan_timestamps(rng, args)
    if not timestamps:
        print("nothing to do (zero timestamps planned)")
        return 0

    target = len(timestamps)
    inserted = items_total = returns = inv_units = 0
    inv_products: set = set()
    conn = connect()
    try:
        with conn.cursor() as cur:
            store_id = fetch_default_store(cur, args.tenant)
            users = fetch_users(cur, args.tenant)
            customers = fetch_customers(cur, args.tenant)
            pool = fetch_eligible_products(cur, args.tenant, store_id)

            if not users:
                print(f"no active users for tenant={args.tenant}",
                      file=sys.stderr)
                return 2
            if not pool:
                print(f"no eligible products (positive inventory) for "
                      f"tenant={args.tenant} store={store_id}", file=sys.stderr)
                return 2

            for ts in timestamps:
                u = pick_user(rng, users)
                c = pick_customer(rng, customers)
                result = build_sale(rng, pool, store_id, u, c, ts)
                if not result:
                    continue
                sale, items, is_returned = result
                if not args.dry_run:
                    insert_sale(cur, args.tenant, sale, items)
                    if not is_returned:
                        apply_inventory(cur, items)
                inserted += 1
                items_total += len(items)
                if is_returned:
                    returns += 1
                else:
                    for it in items:
                        inv_units += it["quantity"]
                        inv_products.add(it["product_id"])

            if args.dry_run:
                conn.rollback()
            else:
                conn.commit()
    except Exception as e:
        conn.rollback()
        print(f"ERROR: {type(e).__name__}: {e}", file=sys.stderr)
        return 2
    finally:
        conn.close()

    tmin = min(timestamps).strftime("%Y-%m-%d %H:%M")
    tmax = max(timestamps).strftime("%Y-%m-%d %H:%M")
    label = "DRY-RUN" if args.dry_run else "WROTE"
    print(f"[{label}] Seeded {inserted} sales ({items_total} items, "
          f"{returns} returns) for tenant={args.tenant}")
    print(f"Time range: {tmin} to {tmax}")
    print(f"Inventory delta: -{inv_units} units across "
          f"{len(inv_products)} products")
    if inserted < target:
        print(f"note: planned {target} sales but only seeded {inserted} "
              f"(insufficient inventory)", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
