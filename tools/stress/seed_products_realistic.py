#!/usr/bin/env python3
"""
tools/stress/seed_products_realistic.py

Етап 1 — Стъпка 5: 2-3K артикула с реалистична distribution.

Distribution от STRESS_TENANT_SEED.md ред 198 ("2 000-3 000 АРТИКУЛА"):

  Дамско бельо           400  €5-50    XS-XXL, цветове
  Мъжко бельо            300  €5-40    S-XXL
  Пижами дамски          200  €15-80   S-XXL
  Пижами мъжки           100  €20-70   M-XXL
  Чорапи                 250  €2-15    multi-pack
  Дрехи (магазин 2)      500  €20-200
  Обувки (магазин 3)     300  €30-300  размери 36-46
  Аксесоари (магазин 4)  200  €5-100
  Бижута (магазин 6)     150  €50-1500 малки бройки 1-3
  Домашни (магазин 7)    600  €5-200

Total: ~3000 артикула. С variations × size × color = ~12-15K SKU.

5% intentional грешна цена (за да тестваме AI fact verifier).
2% липсваща стока (initial quantity = 0 → AI insights).
1% дубликати (за wizard test).

Idempotent: пропуска ако code вече съществува.

Random seed = 42 → deterministic.
"""

import argparse
import hashlib
import json
import random
import sys
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


CATEGORIES = [
    {"name": "Дамско бельо",   "supplier_pool": ["Дафи", "Ивон", "Статера"], "count": 400, "price_eur": (5, 50),    "sizes": ["XS", "S", "M", "L", "XL", "XXL"], "colors": ["черно", "бяло", "розово", "червено", "телесно", "синьо"], "season": "all"},
    {"name": "Мъжко бельо",    "supplier_pool": ["Lord", "Royal Tiger", "Диекс"], "count": 300, "price_eur": (5, 40),    "sizes": ["S", "M", "L", "XL", "XXL"], "colors": ["черно", "бяло", "сиво", "тъмно синьо"], "season": "all"},
    {"name": "Пижами дамски",  "supplier_pool": ["Петков", "Пико", "Иватекс"], "count": 200, "price_eur": (15, 80),   "sizes": ["S", "M", "L", "XL", "XXL"], "colors": ["розово", "виолетово", "червено", "синьо"], "season": "winter+summer"},
    {"name": "Пижами мъжки",   "supplier_pool": ["Петков", "Ареал"], "count": 100, "price_eur": (20, 70),   "sizes": ["M", "L", "XL", "XXL"], "colors": ["синьо", "сиво", "зелено"], "season": "winter+summer"},
    {"name": "Чорапи",         "supplier_pool": ["Sonic"], "count": 250, "price_eur": (2, 15),    "sizes": ["35-38", "39-42", "43-46"], "colors": ["черно", "бяло", "сиво", "цветни"], "multipack": True},
    {"name": "Дрехи",          "supplier_pool": ["Ареал"], "count": 500, "price_eur": (20, 200),  "sizes": ["XS", "S", "M", "L", "XL"], "colors": ["разнообразни"], "season": "all"},
    {"name": "Обувки",         "supplier_pool": ["Ареал"], "count": 300, "price_eur": (30, 300),  "sizes": [str(s) for s in range(36, 47)], "colors": ["черно", "кафяво", "бяло", "цветни"], "season": "winter+summer"},
    {"name": "Аксесоари",      "supplier_pool": ["Ареал"], "count": 200, "price_eur": (5, 100),   "sizes": ["one-size"], "colors": ["разнообразни"], "season": "all"},
    {"name": "Бижута",         "supplier_pool": ["Ареал"], "count": 150, "price_eur": (50, 1500), "sizes": ["one-size"], "colors": ["сребро", "злато", "розово злато"], "small_qty": True},
    {"name": "Домашни потреби","supplier_pool": ["Ареал"], "count": 600, "price_eur": (5, 200),   "sizes": ["one-size"], "colors": ["разнообразни"], "season": "all"},
]

ERROR_RATES = {
    "wrong_price_pct": 5.0,    # 5% intentional грешна цена
    "missing_stock_pct": 2.0,  # 2% initial quantity = 0
    "duplicates_pct": 1.0,     # 1% дубликати (за wizard test)
}


def discover_columns(conn, table: str) -> set[str]:
    with conn.cursor() as cur:
        cur.execute(f"SHOW COLUMNS FROM {table}")
        return {row["Field"] for row in cur.fetchall()}


def supplier_id_map(conn, tenant_id: int) -> dict:
    with conn.cursor() as cur:
        cur.execute("SELECT id, name FROM suppliers WHERE tenant_id = %s", (tenant_id,))
        return {row["name"]: int(row["id"]) for row in cur.fetchall()}


def stable_code(category: str, idx: int) -> str:
    """Деterministic product code: SHA1(category+idx)[:8] uppercase."""
    h = hashlib.sha1(f"{category}|{idx}".encode("utf-8")).hexdigest()[:8].upper()
    return f"P{h}"


def existing_codes(conn, tenant_id: int) -> set[str]:
    with conn.cursor() as cur:
        cur.execute("SELECT code FROM products WHERE tenant_id = %s AND code IS NOT NULL", (tenant_id,))
        return {row["code"] for row in cur.fetchall() if row["code"]}


def generate_products(suppliers: dict, force_count: int | None = None):
    """Generator на planned products. Без DB writes — само Python обекти."""
    duplicates_to_emit = []
    products = []

    for cat in CATEGORIES:
        target = force_count or cat["count"]
        # Ако force_count, разпределяме пропорционално
        if force_count:
            total_default = sum(c["count"] for c in CATEGORIES)
            target = max(1, round(force_count * cat["count"] / total_default))
        for i in range(target):
            base_name = f"{cat['name']} модел {i+1:04d}"
            size = random.choice(cat["sizes"])
            color = random.choice(cat["colors"])
            name = f"{base_name} {size} {color}"
            code = stable_code(cat["name"], i)

            # Цена — реалистична, но 5% намеренo грешна (×10 или /10)
            lo, hi = cat["price_eur"]
            price = round(random.uniform(lo, hi), 2)
            wrong_price = random.random() * 100 < ERROR_RATES["wrong_price_pct"]
            if wrong_price:
                # типична грешка: запетая на грешно място
                price = round(price * random.choice([0.1, 10.0]), 2)

            # Cost price = retail × random 50-70% (margin 30-50%)
            margin = random.uniform(0.50, 0.70)
            cost_price = round(price * margin, 2)

            # Initial quantity
            missing = random.random() * 100 < ERROR_RATES["missing_stock_pct"]
            if cat.get("small_qty"):
                qty = random.randint(1, 3)
            else:
                qty = random.randint(5, 50)
            if missing:
                qty = 0

            supplier_name = random.choice(cat["supplier_pool"])
            supplier_id = suppliers.get(supplier_name)

            products.append({
                "code": code,
                "name": name,
                "category": cat["name"],
                "supplier_id": supplier_id,
                "supplier_name": supplier_name,
                "retail_price": price,
                "cost_price": cost_price,
                "wholesale_price": round(cost_price * 1.20, 2),
                "size": size,
                "color": color,
                "initial_qty": qty,
                "wrong_price": wrong_price,
                "missing_stock": missing,
                "season": cat.get("season", "all"),
            })

            # 1% дубликати (за wizard test)
            if random.random() * 100 < ERROR_RATES["duplicates_pct"]:
                dup = dict(products[-1])
                dup["code"] = code + "D"  # leak безсмислен суфикс
                dup["name"] = name + " (DUP)"
                duplicates_to_emit.append(dup)

    products.extend(duplicates_to_emit)
    return products


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tenant", type=int, default=None)
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--limit", type=int, default=None,
                    help="Override total продукти за по-бърз dry-run (default по distribution).")
    args = ap.parse_args()
    seed_rng()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=False)
    tenant_id = args.tenant if args.tenant else resolve_stress_tenant(conn)
    if tenant_id is None:
        sys.exit("[REFUSE] STRESS Lab tenant не съществува.")
    assert_stress_tenant(tenant_id, conn)

    cols = discover_columns(conn, "products")
    inv_cols = discover_columns(conn, "inventory")
    suppliers = supplier_id_map(conn, tenant_id)
    if not suppliers:
        sys.exit("[REFUSE] suppliers таблицата е празна за този tenant. Изпълни seed_suppliers.py първо.")

    existing = existing_codes(conn, tenant_id)
    products = generate_products(suppliers, force_count=args.limit)
    new_products = [p for p in products if p["code"] not in existing]
    skipped = len(products) - len(new_products)

    summary = {
        "tenant_id": tenant_id,
        "total_planned": len(products),
        "new": len(new_products),
        "skipped_already_exists": skipped,
        "by_category": {},
        "wrong_price_count": sum(1 for p in new_products if p["wrong_price"]),
        "missing_stock_count": sum(1 for p in new_products if p["missing_stock"]),
    }
    for p in new_products:
        summary["by_category"][p["category"]] = summary["by_category"].get(p["category"], 0) + 1

    print(f"[PLAN] tenant_id={tenant_id} — {len(new_products)} нови продукта.")
    for cat, cnt in summary["by_category"].items():
        print(f"  {cat:20s} {cnt}")
    print(f"  wrong_price = {summary['wrong_price_count']} ({ERROR_RATES['wrong_price_pct']}%)")
    print(f"  missing_stock = {summary['missing_stock_count']} ({ERROR_RATES['missing_stock_pct']}%)")

    if not args.apply:
        # Не пишем целия списък в JSON — твърде голям. Записваме summary + sample.
        out = dry_run_log("seed_products_realistic", {
            "action": "dry-run",
            "summary": summary,
            "sample_first_5": new_products[:5],
            "sample_last_5": new_products[-5:],
        })
        print(f"[DRY-RUN] План: {out}")
        return 0

    inserted = 0
    inv_inserted = 0
    try:
        with conn.cursor() as cur:
            # default store: Склад
            cur.execute(
                "SELECT id FROM stores WHERE tenant_id = %s AND name = %s LIMIT 1",
                (tenant_id, "Склад"),
            )
            row = cur.fetchone()
            warehouse_id = int(row["id"]) if row else None

            for p in new_products:
                prod_row = {"tenant_id": tenant_id, "code": p["code"], "name": p["name"]}
                if "retail_price" in cols:
                    prod_row["retail_price"] = p["retail_price"]
                if "wholesale_price" in cols:
                    prod_row["wholesale_price"] = p["wholesale_price"]
                if "cost_price" in cols:
                    prod_row["cost_price"] = p["cost_price"]
                if "supplier_id" in cols and p.get("supplier_id"):
                    prod_row["supplier_id"] = p["supplier_id"]
                if "category" in cols:
                    prod_row["category"] = p["category"]
                if "is_active" in cols:
                    prod_row["is_active"] = 1
                if "metadata" in cols:
                    prod_row["metadata"] = json.dumps({
                        "size": p["size"], "color": p["color"], "season": p["season"],
                        "wrong_price": p["wrong_price"], "missing_stock": p["missing_stock"],
                    }, ensure_ascii=False)

                fields = ", ".join(prod_row.keys())
                placeholders = ", ".join(["%s"] * len(prod_row))
                cur.execute(
                    f"INSERT INTO products ({fields}) VALUES ({placeholders})",
                    list(prod_row.values()),
                )
                product_id = cur.lastrowid
                inserted += 1

                if warehouse_id and {"product_id", "store_id", "quantity"}.issubset(inv_cols):
                    inv_row = {
                        "tenant_id": tenant_id,
                        "product_id": product_id,
                        "store_id": warehouse_id,
                        "quantity": p["initial_qty"],
                    }
                    if "min_quantity" in inv_cols:
                        inv_row["min_quantity"] = max(1, round(p["initial_qty"] / 2.5))
                    fields = ", ".join(inv_row.keys())
                    placeholders = ", ".join(["%s"] * len(inv_row))
                    cur.execute(
                        f"INSERT INTO inventory ({fields}) VALUES ({placeholders})",
                        list(inv_row.values()),
                    )
                    inv_inserted += 1

        conn.commit()
    except Exception as e:
        conn.rollback()
        sys.exit(f"[FAIL] INSERT провали (rollback изпълнен): {e}")

    print(f"[OK] Създадени {inserted} продукта + {inv_inserted} inventory реда.")
    dry_run_log("seed_products_realistic", {
        "action": "applied", "tenant_id": tenant_id,
        "products_inserted": inserted, "inventory_inserted": inv_inserted,
        "summary": summary,
    })
    return 0


if __name__ == "__main__":
    sys.exit(main() or 0)
