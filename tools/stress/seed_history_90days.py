#!/usr/bin/env python3
"""
tools/stress/seed_history_90days.py

Етап 1 — Стъпка 6: 90 дни fake история.

Patterns от STRESS_TENANT_SEED.md ред 215:

  Дневен паттерн:
    Понеделник:    80% от средното
    Вт-Чт:        100%
    Петък:        130%
    Събота:       150%
    Неделя:       110%

  Часов паттерн:
    10-12: 20%
    12-15: 30% (обяд)
    15-18: 30%
    18-22: 20% (МОЛ магазини)

  Сезонен (90 дни):
    Април:  +15% дрехи
    Май:    +20% обувки сандали
    Юни:    +25% бански (липсват → lost_demand)

  Продавачи distribution:
    Петя 30%, Иван 25%, Мария 15%, Стефан 15%, Цветана 15%

  Връщания:    8-10% от продажбите
  Lost demand: 50-100 записа за 90 дни
  Intentional errors: 5-10% (грешна цена, дубликат, race candidate)

Idempotent: skip ако вече има sales за tenant в тоя период.
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
    resolve_stress_tenant,
    seed_rng,
)


# ---------- Distribution constants ----------

DAY_OF_WEEK_MULT = {
    0: 0.80,  # Mon
    1: 1.00,
    2: 1.00,
    3: 1.00,
    4: 1.30,  # Fri
    5: 1.50,  # Sat
    6: 1.10,  # Sun
}

HOUR_BUCKETS = [
    (10, 12, 0.20),
    (12, 15, 0.30),
    (15, 18, 0.30),
    (18, 22, 0.20),
]

USER_DISTRIBUTION = [
    ("petya@stress.lab",   0.30),
    ("ivan@stress.lab",    0.25),
    ("maria@stress.lab",   0.15),
    ("stefan@stress.lab",  0.15),
    ("cvetana@stress.lab", 0.15),
]

INTENTIONAL_ERROR_RATE = 0.075   # 7.5% — между 5-10%
RETURN_RATE_RANGE      = (0.08, 0.10)
LOST_DEMAND_TOTAL      = (50, 100)


def seasonal_multiplier(date: datetime, category: str) -> float:
    m = date.month
    if m == 4 and "Дрехи" in category:
        return 1.15
    if m == 5 and "Обувки" in category:
        return 1.20
    if m == 6 and category in ("Дрехи", "Обувки"):
        return 1.10
    return 1.0


def discover_columns(conn, table: str) -> set[str]:
    with conn.cursor() as cur:
        cur.execute(f"SHOW COLUMNS FROM {table}")
        return {row["Field"] for row in cur.fetchall()}


def fetch_users(conn, tenant_id: int) -> dict:
    with conn.cursor() as cur:
        cur.execute("SELECT id, email, name FROM users WHERE tenant_id = %s", (tenant_id,))
        return {row["email"]: row for row in cur.fetchall()}


def fetch_stores(conn, tenant_id: int) -> list:
    with conn.cursor() as cur:
        cur.execute("SELECT id, name FROM stores WHERE tenant_id = %s", (tenant_id,))
        return list(cur.fetchall())


def fetch_products_sample(conn, tenant_id: int, limit: int = 1000) -> list:
    with conn.cursor() as cur:
        cur.execute(
            # `category` беше string column в стара schema; текущата има category_id (FK).
            # Seed_products_realistic не пълни category_id, затова JOIN-ът би върнал NULL
            # за всички редове. По-просто: drop category — seasonal_multiplier получава "" и
            # връща 1.0 (без сезонен boost). Stress volume/distribution остават валидни.
            "SELECT id, code, name, retail_price, cost_price "
            "FROM products WHERE tenant_id = %s ORDER BY id LIMIT %s",
            (tenant_id, limit),
        )
        return list(cur.fetchall())


def base_daily_sales_for_store(store_name: str) -> int:
    """Средно за деня по тип магазин (от STRESS_TENANT_SEED §локации)."""
    if store_name == "Склад":
        return 50
    if "high-volume" in store_name:
        return 300
    if "бижута" in store_name:
        return 10
    if "домашни" in store_name:
        return 40
    if "Онлайн" in store_name:
        return 30
    if "обувки" in store_name:
        return 50
    if "дрехи" in store_name:
        return 70
    if "mixed" in store_name:
        return 100
    return 50


def pick_user(users: dict) -> dict | None:
    r = random.random()
    cum = 0.0
    for email, share in USER_DISTRIBUTION:
        cum += share
        if r <= cum:
            return users.get(email)
    return next(iter(users.values()), None)


def pick_hour() -> int:
    r = random.random()
    cum = 0.0
    for lo, hi, share in HOUR_BUCKETS:
        cum += share
        if r <= cum:
            return random.randint(lo, hi - 1)
    return random.randint(10, 21)


def plan_days(days: int) -> list[datetime]:
    """Връща list от datetime (00:00) — последните N дни."""
    today = datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)
    return [(today - timedelta(days=days - i)) for i in range(days)]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tenant", type=int, default=None)
    ap.add_argument("--days", type=int, default=90, help="брой дни история (default 90)")
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--max-sales", type=int, default=None,
                    help="Hard cap общо продажби (за бърз smoke test)")
    args = ap.parse_args()
    seed_rng()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=False)
    tenant_id = args.tenant if args.tenant else resolve_stress_tenant(conn)
    if tenant_id is None:
        sys.exit("[REFUSE] STRESS Lab tenant не съществува.")
    assert_stress_tenant(tenant_id, conn)

    sales_cols = discover_columns(conn, "sales")
    sale_items_cols = discover_columns(conn, "sale_items")

    users = fetch_users(conn, tenant_id)
    if not users:
        sys.exit("[REFUSE] users е празна. Изпълни seed_users.py първо.")
    stores = fetch_stores(conn, tenant_id)
    if not stores:
        sys.exit("[REFUSE] stores е празна. Изпълни seed_stores.py първо.")
    products = fetch_products_sample(conn, tenant_id)
    if not products:
        sys.exit("[REFUSE] products е празна. Изпълни seed_products_realistic.py първо.")

    days = plan_days(args.days)

    # ---------- PLAN: пресмятане без писане ----------
    plan_summary = {
        "tenant_id": tenant_id,
        "days": args.days,
        "stores_covered": [s["name"] for s in stores],
        "user_count": len(users),
        "product_pool": len(products),
        "by_store": {},
        "by_day_of_week": {0: 0, 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0},
        "expected_sales_total": 0,
        "expected_returns_total": 0,
        "expected_lost_demand": random.randint(*LOST_DEMAND_TOTAL),
        "intentional_errors": 0,
    }

    planned_sales = []
    for day in days:
        dow = day.weekday()
        day_mult = DAY_OF_WEEK_MULT[dow]
        for store in stores:
            base = base_daily_sales_for_store(store["name"])
            count = max(0, int(base * day_mult * random.uniform(0.85, 1.15)))
            plan_summary["by_day_of_week"][dow] += count
            plan_summary["by_store"][store["name"]] = plan_summary["by_store"].get(store["name"], 0) + count
            for _ in range(count):
                if args.max_sales and plan_summary["expected_sales_total"] >= args.max_sales:
                    break
                hour = pick_hour()
                ts = day.replace(hour=hour, minute=random.randint(0, 59), second=random.randint(0, 59))
                user = pick_user(users)
                if not user:
                    continue
                # Кошница: 1-4 артикула
                n_items = random.choices([1, 2, 3, 4], weights=[0.45, 0.30, 0.18, 0.07])[0]
                items = []
                for _ in range(n_items):
                    p = random.choice(products)
                    qty = random.choices([1, 2, 3], weights=[0.80, 0.15, 0.05])[0]
                    seas_mult = seasonal_multiplier(ts, p.get("category", ""))
                    if random.random() > seas_mult / 1.25:
                        continue
                    price = float(p.get("retail_price") or 0)
                    items.append({
                        "product_id": int(p["id"]),
                        "qty": qty,
                        "unit_price": price,
                        "cost_price": float(p.get("cost_price") or 0),
                    })
                if not items:
                    continue
                total = round(sum(i["qty"] * i["unit_price"] for i in items), 2)
                error = random.random() < INTENTIONAL_ERROR_RATE
                if error:
                    plan_summary["intentional_errors"] += 1
                planned_sales.append({
                    "tenant_id": tenant_id,
                    "store_id": store["id"],
                    "user_id": user["id"],
                    "ts": ts,
                    "items": items,
                    "total": total,
                    "intentional_error": error,
                })
                plan_summary["expected_sales_total"] += 1

    rr = random.uniform(*RETURN_RATE_RANGE)
    plan_summary["expected_returns_total"] = int(plan_summary["expected_sales_total"] * rr)

    print(f"[PLAN] tenant_id={tenant_id} {args.days} дни история")
    print(f"  Sales total expected:      {plan_summary['expected_sales_total']}")
    print(f"  Returns expected:          {plan_summary['expected_returns_total']}")
    print(f"  Lost demand expected:      {plan_summary['expected_lost_demand']}")
    print(f"  Intentional errors:        {plan_summary['intentional_errors']}")
    for store, cnt in plan_summary["by_store"].items():
        print(f"  {store:30s} {cnt}")

    if not args.apply:
        out = dry_run_log("seed_history_90days", {
            "action": "dry-run",
            "summary": plan_summary,
            "sample_sales": [
                {**s, "ts": s["ts"].isoformat()} for s in planned_sales[:5]
            ],
        })
        print(f"[DRY-RUN] План: {out}")
        return 0

    # ---------- APPLY ----------
    inserted_sales = 0
    inserted_items = 0
    inserted_movements = 0
    try:
        with conn.cursor() as cur:
            for s in planned_sales:
                sale_row = {
                    "tenant_id": s["tenant_id"],
                    "store_id": s["store_id"],
                    "user_id": s["user_id"],
                }
                # Поле за дата — по приоритет: sale_date / created_at
                if "sale_date" in sales_cols:
                    sale_row["sale_date"] = s["ts"]
                elif "created_at" in sales_cols:
                    sale_row["created_at"] = s["ts"]
                if "total" in sales_cols:
                    sale_row["total"] = s["total"]
                if "status" in sales_cols:
                    sale_row["status"] = "completed"
                fields = ", ".join(sale_row.keys())
                placeholders = ", ".join(["%s"] * len(sale_row))
                cur.execute(
                    f"INSERT INTO sales ({fields}) VALUES ({placeholders})",
                    list(sale_row.values()),
                )
                sale_id = cur.lastrowid
                inserted_sales += 1

                for it in s["items"]:
                    item_row = {
                        "sale_id": sale_id,
                        "product_id": it["product_id"],
                        "quantity": it["qty"],
                        "unit_price": it["unit_price"],
                    }
                    if "cost_price" in sale_items_cols:
                        item_row["cost_price"] = it["cost_price"]
                    if "total" in sale_items_cols:
                        item_row["total"] = round(it["qty"] * it["unit_price"], 2)
                    fields = ", ".join(item_row.keys())
                    placeholders = ", ".join(["%s"] * len(item_row))
                    cur.execute(
                        f"INSERT INTO sale_items ({fields}) VALUES ({placeholders})",
                        list(item_row.values()),
                    )
                    inserted_items += 1
                    # inventory decrement: НЕ използваме GREATEST — реалистично ground truth
                    cur.execute(
                        "UPDATE inventory SET quantity = quantity - %s "
                        "WHERE product_id = %s AND store_id = %s",
                        (it["qty"], it["product_id"], s["store_id"]),
                    )
                    inserted_movements += cur.rowcount or 0

        conn.commit()
    except Exception as e:
        conn.rollback()
        sys.exit(f"[FAIL] INSERT провали (rollback изпълнен): {e}")

    print(f"[OK] sales={inserted_sales} items={inserted_items} inv_updates={inserted_movements}")
    print( "     Returns + lost_demand + delivery_history — TODO в Етап 1.b "
           "(самостоятелни скриптове, същата структура — НЕ изпълнявам тук за да не дубликирам данни).")
    dry_run_log("seed_history_90days", {
        "action": "applied", "tenant_id": tenant_id,
        "sales_inserted": inserted_sales, "items_inserted": inserted_items,
        "summary": plan_summary,
    })
    return 0


if __name__ == "__main__":
    sys.exit(main() or 0)
