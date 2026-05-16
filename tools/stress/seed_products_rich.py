#!/usr/bin/env python3
"""
tools/stress/seed_products_rich.py

Phase 2+3 на rich reseed (S148-rich):

  - DELETE → INSERT 3000 products with persona-driven signal coverage
  - 7 categories: Бельо/Обувки/Дрехи/Чорапи/Аксесоари/Бижута/Други
  - 15 brands, 12 colors, sizes per category
  - Gender/Season distributions per category
  - Personas tag each product so seed_history_rich.py knows what sales
    pattern to apply (zero_stock, top_sales, aging, zombie, etc.)
  - Data-quality overlays: no_barcode (~15%), no_photo (~30%),
    no_supplier (~3%)

Coverage targets (signal pills + compute-insights FQs):
  zero_stock=200, critical_low=150, below_min=100, at_loss=50,
  low_margin=150, no_cost=150, top_sales=50, top_profit=50,
  aging=200, zombie=250, slow_mover=200, new_week=30,
  no_barcode≈450, no_photo≈900, no_supplier≈90

Idempotent: --wipe deletes all tenant=7 product/history data before insert.
Random seed = 42 (deterministic).

Usage:
    python3 tools/stress/seed_products_rich.py --tenant 7                  # dry-run
    python3 tools/stress/seed_products_rich.py --tenant 7 --apply --wipe   # реално
"""
import argparse
import hashlib
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

# ─────────────────────── DOMAIN MODEL ───────────────────────

CATEGORIES_SPEC = [
    # (name, count, markup_mult, ending, gender_weights, sizes, season_pool)
    ("Бельо",      600, 2.5, 0.99,
        {"female": 60, "male": 30, "kids": 10},
        ["XS","S","M","L","XL","XXL"],
        ["all_year","summer","winter"]),
    ("Обувки",     500, 2.2, 0.90,
        {"female": 40, "male": 40, "kids": 20},
        [str(s) for s in range(36, 47)],
        ["spring_summer","autumn_winter","summer","winter"]),
    ("Дрехи",      800, 2.0, 0.90,
        {"female": 45, "male": 35, "kids": 20},
        ["XS","S","M","L","XL","XXL"],
        ["spring_summer","autumn_winter","summer","winter"]),
    ("Чорапи",     300, 1.8, 0.50,
        {"female": 25, "male": 25, "kids": 25, "unisex": 25},
        ["35-38","39-42","43-46"],
        ["all_year","winter"]),
    ("Аксесоари",  400, 2.5, 0.99,
        {"female": 40, "male": 30, "unisex": 30},
        ["One Size"],
        ["all_year"]),
    ("Бижута",     200, 3.0, 0.99,
        {"female": 80, "unisex": 20},
        ["One Size"],
        ["all_year"]),
    ("Други",      200, 2.0, 0.90,
        {"unisex": 100},
        ["One Size"],
        ["all_year"]),
]

BRANDS = [
    "Nike", "Adidas", "Calvin Klein", "Mango", "H&M", "Zara",
    "Tommy Jeans", "Levi's", "Puma", "Reebok", "Lacoste",
    "Tommy Hilfiger", "GUESS", "Pull&Bear", "Bershka",
]

COLORS = [
    ("Бял",       "#ffffff"),
    ("Черен",     "#1a1a1a"),
    ("Червен",    "#ef4444"),
    ("Син",       "#3b82f6"),
    ("Зелен",     "#10b981"),
    ("Розов",     "#ec4899"),
    ("Жълт",      "#facc15"),
    ("Кафяв",     "#92400e"),
    ("Сив",       "#6b7280"),
    ("Бежов",     "#d4a574"),
    ("Лилав",     "#8b5cf6"),
    ("Тъмносин",  "#1e3a8a"),
]

# Personas (mutually exclusive). count, age_days_range, stock_init_range,
# cost_logic, min_qty, sales_volume tag
PERSONAS = [
    # signal → seed                     count age_lo age_hi  stock        cost_logic   min  sales
    ("zero_stock",                       200,   5,   30,  (0, 0),         "normal",      0, "normal"),
    ("critical_low",                     150,   5,   30,  (1, 2),         "normal",      0, "normal"),
    ("below_min",                        100,   5,   30,  (1, 4),         "normal",      5, "normal"),
    ("at_loss",                           50,   5,   30,  (10, 50),       "at_loss",     0, "normal"),
    ("low_margin",                       150,   5,   30,  (10, 50),       "low_margin",  0, "low"),
    ("no_cost",                          150,   5,   30,  (10, 50),       "no_cost",     0, "low"),
    ("top_sales",                         50,  30,   90,  (50, 200),      "normal",      0, "top"),
    ("top_profit",                        50,  30,   90,  (20, 100),      "high_margin", 0, "high"),
    ("aging",                            200, 180,  365,  (5, 30),        "normal",      0, "none"),
    ("zombie",                           250,  45,   90,  (5, 30),        "normal",      0, "none"),
    ("slow_mover",                       200,  25,   45,  (5, 30),        "normal",      0, "low"),
    ("new_week",                          30,   0,    7,  (10, 50),       "normal",      0, "none"),
    ("normal",                          1420,   7,   25,  (5, 50),        "normal",      0, "normal"),
]

# Data quality overlay probabilities (independent of persona)
OVERLAY = {
    "no_barcode_pct": 15.0,
    "no_photo_pct":   30.0,
    "no_supplier_pct": 3.0,
}

SEASON_WEIGHTS = {
    "summer":         35,
    "winter":         35,
    "spring_summer":  10,
    "autumn_winter":  10,
    "all_year":       10,
}

# Sales tag → expected sales count over 180d (used by seed_history_rich.py via metadata)
SALES_VOLUME_30D = {
    "none":   (0,    0),
    "low":    (0,    2),
    "normal": (2,   15),
    "high":   (15,  40),
    "top":    (40, 100),
}

# Stores we'll use (resolved by name)
PREFERRED_STORE_BY_CATEGORY = {
    "Бельо":      ["Магазин дрехи", "Магазин mixed"],
    "Обувки":     ["Магазин обувки", "Магазин mixed"],
    "Дрехи":      ["Магазин дрехи", "Магазин high-volume"],
    "Чорапи":     ["Магазин дрехи", "Магазин mixed"],
    "Аксесоари":  ["Магазин mixed", "Онлайн магазин"],
    "Бижута":     ["Магазин бижута", "Онлайн магазин"],
    "Други":      ["Магазин домашни потреби", "Магазин mixed"],
}

# ─────────────────────── HELPERS ───────────────────────


def weighted_choice(weights: dict):
    total = sum(weights.values())
    r = random.uniform(0, total)
    cum = 0
    for k, w in weights.items():
        cum += w
        if r <= cum:
            return k
    return list(weights.keys())[-1]


def gen_barcode() -> str:
    """EAN-13 starting with 380... (Bulgaria)."""
    base = "380" + "".join(str(random.randint(0, 9)) for _ in range(9))
    # checksum
    s = sum(int(d) * (1 if i % 2 == 0 else 3) for i, d in enumerate(base))
    check = (10 - s % 10) % 10
    return base + str(check)


def gen_image_url(idx: int) -> str:
    return f"https://placehold.co/600x600/cccccc/333333?text=Product+{idx:05d}"


def apply_markup(cost: float, mult: float, ending: float) -> float:
    """retail = floor(cost*mult) + ending"""
    return float(int(cost * mult)) + ending


def random_created_at(age_lo: int, age_hi: int) -> datetime:
    age_days = random.randint(age_lo, age_hi)
    hour = random.randint(8, 22)
    minute = random.randint(0, 59)
    return datetime.now() - timedelta(days=age_days, hours=hour, minutes=minute)


def stable_code(category: str, idx: int) -> str:
    h = hashlib.sha1(f"{category}|{idx}|S148rich".encode("utf-8")).hexdigest()[:8].upper()
    return f"P{h}"


# ─────────────────────── WIPE ───────────────────────


def wipe_tenant_data(conn, tenant_id: int) -> dict:
    """DELETE in FK-safe order."""
    counts = {}
    with conn.cursor() as cur:
        # children before parents
        cur.execute("DELETE FROM stock_movements WHERE tenant_id=%s", (tenant_id,))
        counts["stock_movements"] = cur.rowcount

        cur.execute(
            "DELETE si FROM sale_items si "
            "JOIN sales s ON si.sale_id = s.id WHERE s.tenant_id=%s",
            (tenant_id,),
        )
        counts["sale_items"] = cur.rowcount

        cur.execute("DELETE FROM sales WHERE tenant_id=%s", (tenant_id,))
        counts["sales"] = cur.rowcount

        cur.execute(
            "DELETE di FROM delivery_items di "
            "JOIN deliveries d ON di.delivery_id = d.id WHERE d.tenant_id=%s",
            (tenant_id,),
        )
        counts["delivery_items"] = cur.rowcount

        cur.execute("DELETE FROM deliveries WHERE tenant_id=%s", (tenant_id,))
        counts["deliveries"] = cur.rowcount

        cur.execute("DELETE FROM inventory WHERE tenant_id=%s", (tenant_id,))
        counts["inventory"] = cur.rowcount

        cur.execute("DELETE FROM ai_insights WHERE tenant_id=%s", (tenant_id,))
        counts["ai_insights"] = cur.rowcount

        # Best-effort optional tables
        for t in ("returns", "lost_demand", "search_log", "ai_snapshots"):
            try:
                cur.execute(f"DELETE FROM {t} WHERE tenant_id=%s", (tenant_id,))
                counts[t] = cur.rowcount
            except Exception:
                conn.rollback()
                counts[t] = "skip"

        cur.execute("DELETE FROM products WHERE tenant_id=%s", (tenant_id,))
        counts["products"] = cur.rowcount

        cur.execute("DELETE FROM categories WHERE tenant_id=%s", (tenant_id,))
        counts["categories"] = cur.rowcount

    conn.commit()
    return counts


# ─────────────────────── SEED ───────────────────────


def create_categories(conn, tenant_id: int) -> dict[str, int]:
    """Create 7 main categories. Returns name → id map."""
    name_to_id = {}
    with conn.cursor() as cur:
        for (name, _count, _mult, _ending, _gender, _sizes, _seasons) in CATEGORIES_SPEC:
            cur.execute(
                "INSERT INTO categories (tenant_id, parent_id, name, variant_type) "
                "VALUES (%s, NULL, %s, %s)",
                (tenant_id, name, "size_color" if name not in ("Бижута","Аксесоари","Други") else "none"),
            )
            name_to_id[name] = int(cur.lastrowid)
    conn.commit()
    return name_to_id


def resolve_stores(conn, tenant_id: int) -> dict[str, int]:
    with conn.cursor() as cur:
        cur.execute("SELECT id, name FROM stores WHERE tenant_id=%s", (tenant_id,))
        return {row["name"]: int(row["id"]) for row in cur.fetchall()}


def resolve_suppliers(conn, tenant_id: int) -> list[int]:
    with conn.cursor() as cur:
        cur.execute("SELECT id FROM suppliers WHERE tenant_id=%s", (tenant_id,))
        return [int(row["id"]) for row in cur.fetchall()]


def build_persona_plan() -> list[dict]:
    """
    Flatten PERSONAS × CATEGORIES into per-product blueprints.
    Each persona's count is distributed across categories by CATEGORIES_SPEC count proportions.
    """
    total_cat = sum(c[1] for c in CATEGORIES_SPEC)
    cat_weights = {c[0]: c[1] / total_cat for c in CATEGORIES_SPEC}
    cat_lookup = {c[0]: c for c in CATEGORIES_SPEC}

    products: list[dict] = []
    persona_seq = 0
    for persona_name, count, age_lo, age_hi, stock_range, cost_logic, min_qty, sales_tag in PERSONAS:
        # Distribute this persona across categories
        per_cat = []
        accum = 0.0
        for cat_name in cat_weights:
            share = count * cat_weights[cat_name]
            n = int(share)
            accum += share - n
            if accum >= 1.0:
                n += 1
                accum -= 1.0
            per_cat.append((cat_name, n))
        # Fix rounding: total may be off by ±1
        delta = count - sum(n for _, n in per_cat)
        if delta != 0:
            cat_name, n = per_cat[0]
            per_cat[0] = (cat_name, n + delta)

        for cat_name, n in per_cat:
            cat = cat_lookup[cat_name]
            for _ in range(n):
                persona_seq += 1
                p = {
                    "persona": persona_name,
                    "category": cat_name,
                    "age_lo": age_lo,
                    "age_hi": age_hi,
                    "stock_lo": stock_range[0],
                    "stock_hi": stock_range[1],
                    "cost_logic": cost_logic,
                    "min_qty": min_qty,
                    "sales_tag": sales_tag,
                    "seq": persona_seq,
                }
                products.append(p)
    return products


def materialize_product(p: dict, idx_in_cat: int) -> dict:
    """Fill in name, code, prices, dimensions for a planned product."""
    cat = next(c for c in CATEGORIES_SPEC if c[0] == p["category"])
    _, _count, mult, ending, gender_w, sizes, season_pool = cat

    # Pricing
    if p["cost_logic"] == "no_cost":
        cost = 0.0
        retail = round(random.uniform(10, 60), 2)
    elif p["cost_logic"] == "at_loss":
        cost = round(random.uniform(15, 80), 2)
        retail = round(cost * random.uniform(0.55, 0.85), 2)
    elif p["cost_logic"] == "low_margin":
        cost = round(random.uniform(10, 40), 2)
        retail = round(cost / random.uniform(0.86, 0.95), 2)  # margin 5-14%
    elif p["cost_logic"] == "high_margin":
        cost = round(random.uniform(8, 30), 2)
        retail = round(cost * random.uniform(3.0, 5.0), 2)
    else:  # normal
        cost = round(random.uniform(5, 60), 2)
        retail = apply_markup(cost, mult, ending)
        # 5% wrong-price (intentional)
        if random.random() < 0.05:
            retail = round(retail * random.choice([0.1, 10.0]), 2)

    color_name, color_hex = random.choice(COLORS)
    size = random.choice(sizes)
    gender = weighted_choice({k: v for k, v in gender_w.items()})
    season = random.choice(season_pool) if random.random() < 0.7 else weighted_choice(SEASON_WEIGHTS)
    brand = random.choice(BRANDS)
    created_at = random_created_at(p["age_lo"], p["age_hi"])

    # Quality overlays (random independent)
    no_barcode = random.random() * 100 < OVERLAY["no_barcode_pct"]
    no_photo = random.random() * 100 < OVERLAY["no_photo_pct"]
    no_supplier = random.random() * 100 < OVERLAY["no_supplier_pct"]

    name = f"{brand} {p['category']} {color_name} {size}"
    code = stable_code(p["category"], p["seq"])

    return {
        **p,
        "name": name,
        "code": code,
        "cost_price": cost,
        "retail_price": retail,
        "wholesale_price": round(retail * 0.85, 2),
        "size": size,
        "color": color_name,
        "color_hex": color_hex,
        "gender": gender,
        "season": season,
        "brand": brand,
        "created_at": created_at,
        "barcode": None if no_barcode else gen_barcode(),
        "image_url": None if no_photo else gen_image_url(p["seq"]),
        "no_supplier": no_supplier,
        "description": f"{brand} {color_name.lower()} {p['category'].lower()} — STRESS Lab seed.",
    }


def insert_products(conn, tenant_id: int, products: list[dict], stores: dict, suppliers: list[int], category_ids: dict[str, int]) -> dict:
    """Bulk INSERT products + inventory + initial stock_movements."""
    if not stores:
        sys.exit("[REFUSE] няма stores за tenant_id=7 — run seed_stores.py първо")
    warehouse_id = stores.get("Склад") or list(stores.values())[0]

    # Track barcode uniqueness — UNIQUE KEY (tenant_id, barcode)
    seen_barcodes = set()

    inserted = 0
    inv_rows = 0
    mvmt_rows = 0
    persona_counts: dict[str, int] = {}
    quality_counts = {"no_barcode": 0, "no_photo": 0, "no_supplier": 0}

    with conn.cursor() as cur:
        for p in products:
            # dedupe barcode within batch
            barcode = p["barcode"]
            if barcode is not None and barcode in seen_barcodes:
                barcode = None
                p["barcode"] = None  # promote to no_barcode
            if barcode is not None:
                seen_barcodes.add(barcode)
            else:
                quality_counts["no_barcode"] += 1
            if p["image_url"] is None:
                quality_counts["no_photo"] += 1

            supplier_id = None if p["no_supplier"] else (random.choice(suppliers) if suppliers else None)
            if supplier_id is None:
                quality_counts["no_supplier"] += 1

            cur.execute(
                """
                INSERT INTO products
                    (tenant_id, category_id, supplier_id, code, name, barcode,
                     size, color, gender, season, brand, description,
                     cost_price, retail_price, wholesale_price,
                     min_quantity, image_url, unit, is_active,
                     created_via, created_at, updated_at)
                VALUES (%s,%s,%s,%s,%s,%s,
                        %s,%s,%s,%s,%s,%s,
                        %s,%s,%s,
                        %s,%s,'бр',1,
                        'import',%s,%s)
                """,
                (
                    tenant_id, category_ids[p["category"]], supplier_id,
                    p["code"], p["name"], barcode,
                    p["size"], p["color"], p["gender"], p["season"],
                    p["brand"], p["description"],
                    p["cost_price"] if p["cost_logic"] != "no_cost" else 0,
                    p["retail_price"], p["wholesale_price"],
                    p["min_qty"], p["image_url"],
                    p["created_at"], p["created_at"],
                ),
            )
            product_id = int(cur.lastrowid)
            inserted += 1
            persona_counts[p["persona"]] = persona_counts.get(p["persona"], 0) + 1

            # Inventory: place in 1-2 stores
            store_pool = [stores[name] for name in PREFERRED_STORE_BY_CATEGORY.get(p["category"], [])
                          if name in stores] or [warehouse_id]
            # Always also warehouse for top_sales/top_profit
            place_in = list({random.choice(store_pool), warehouse_id} if p["persona"] in ("top_sales", "top_profit")
                            else {random.choice(store_pool)})
            initial_qty = random.randint(p["stock_lo"], max(p["stock_lo"], p["stock_hi"]))

            for st_id in place_in:
                cur.execute(
                    """
                    INSERT INTO inventory (tenant_id, store_id, product_id, quantity, min_quantity)
                    VALUES (%s,%s,%s,%s,%s)
                    """,
                    (tenant_id, st_id, product_id, initial_qty, p["min_qty"]),
                )
                inv_rows += 1

                # Initial stock_movement (delivery) if qty > 0 — gives audit trail
                if initial_qty > 0:
                    cur.execute(
                        """
                        INSERT INTO stock_movements
                            (tenant_id, store_id, product_id, type, quantity, price,
                             reference_type, note, created_at)
                        VALUES (%s,%s,%s,'delivery',%s,%s,'seed_initial','Initial seed stock',%s)
                        """,
                        (tenant_id, st_id, product_id, initial_qty, p["cost_price"], p["created_at"]),
                    )
                    mvmt_rows += 1

            if inserted % 500 == 0:
                conn.commit()
                print(f"  ... inserted {inserted}/{len(products)}")
    conn.commit()
    return {
        "products_inserted": inserted,
        "inventory_rows": inv_rows,
        "stock_movements_rows": mvmt_rows,
        "persona_counts": persona_counts,
        "quality_overlay_counts": quality_counts,
    }


def write_persona_index(products: list[dict]) -> Path:
    """Save persona→product_seq map за да го прочете seed_history_rich.py."""
    out_dir = Path(__file__).resolve().parent / "data"
    out_dir.mkdir(parents=True, exist_ok=True)
    out = out_dir / "rich_persona_index.json"
    payload = [
        {"seq": p["seq"], "persona": p["persona"], "sales_tag": p["sales_tag"],
         "category": p["category"], "code": p["code"]}
        for p in products
    ]
    out.write_text(json.dumps(payload, ensure_ascii=False))
    return out


# ─────────────────────── MAIN ───────────────────────


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--tenant", type=int, required=True)
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--wipe", action="store_true",
                    help="DELETE products/inventory/sales/etc за tenant_id ПРЕДИ INSERT.")
    ap.add_argument("--limit", type=int, default=None,
                    help="Override total за бърз тест (proportional).")
    args = ap.parse_args()
    seed_rng()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=False)
    assert_stress_tenant(args.tenant, conn)

    products = build_persona_plan()
    if args.limit and args.limit < len(products):
        scale = args.limit / len(products)
        scaled = []
        for p in products:
            if random.random() < scale:
                scaled.append(p)
        products = scaled

    # Materialize blueprints
    idx_in_cat: dict[str, int] = {}
    materialized = []
    for p in products:
        idx_in_cat[p["category"]] = idx_in_cat.get(p["category"], 0) + 1
        materialized.append(materialize_product(p, idx_in_cat[p["category"]]))

    persona_summary: dict[str, int] = {}
    cat_summary: dict[str, int] = {}
    for p in materialized:
        persona_summary[p["persona"]] = persona_summary.get(p["persona"], 0) + 1
        cat_summary[p["category"]] = cat_summary.get(p["category"], 0) + 1

    print(f"[PLAN] tenant_id={args.tenant} — {len(materialized)} продукта")
    print(f"  by category:")
    for cat, n in cat_summary.items():
        print(f"    {cat:<14} {n}")
    print(f"  by persona:")
    for persona, n in persona_summary.items():
        print(f"    {persona:<14} {n}")

    if not args.apply:
        out = dry_run_log("seed_products_rich", {
            "action": "dry-run", "tenant_id": args.tenant,
            "total": len(materialized),
            "by_category": cat_summary, "by_persona": persona_summary,
            "sample": [{"persona": p["persona"], "category": p["category"],
                        "code": p["code"], "name": p["name"],
                        "cost": p["cost_price"], "retail": p["retail_price"],
                        "barcode": p["barcode"], "image": p["image_url"][:60] if p["image_url"] else None}
                       for p in materialized[:5]],
        })
        print(f"[DRY-RUN] План: {out}")
        return 0

    # APPLY
    if args.wipe:
        print(f"[WIPE] tenant_id={args.tenant}")
        counts = wipe_tenant_data(conn, args.tenant)
        for tbl, n in counts.items():
            print(f"  {tbl:<18} -{n}")

    print("[CATEGORIES] creating 7 main categories ...")
    category_ids = create_categories(conn, args.tenant)
    for name, cid in category_ids.items():
        print(f"  {name:<14} id={cid}")

    stores = resolve_stores(conn, args.tenant)
    suppliers = resolve_suppliers(conn, args.tenant)
    print(f"[CTX] stores={len(stores)} suppliers={len(suppliers)}")

    print(f"[INSERT] {len(materialized)} products ...")
    result = insert_products(conn, args.tenant, materialized, stores, suppliers, category_ids)

    idx_path = write_persona_index(materialized)
    result["persona_index"] = str(idx_path)
    print(f"[OK] {result['products_inserted']} products + "
          f"{result['inventory_rows']} inventory + "
          f"{result['stock_movements_rows']} initial movements")
    print(f"  persona index: {idx_path}")
    print(f"  data quality overlays: {result['quality_overlay_counts']}")

    dry_run_log("seed_products_rich", {"action": "applied", "tenant_id": args.tenant,
                                       **result})
    return 0


if __name__ == "__main__":
    sys.exit(main() or 0)
