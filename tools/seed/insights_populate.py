#!/usr/bin/env python3
"""
insights_populate.py — top-up seeder for ai_insights (S83).

Why this exists
---------------
cron-insights.php (crontab */15) is healthy and produces 16 organic insights
for tenant=7 each run. But organic counts are uneven across the 6
fundamental_question buckets:

    loss=2  loss_cause=4  gain=2  gain_cause=4  order=2  anti_order=3   (= 17 live)

products.php renders 6 sections — each one needs at least a handful of
visible cards so Tihol can validate the real-entry flow. This script tops
each bucket up to FILL_TARGET (default 5) by inserting realistic seed rows
that look like organic cron output.

Properties
----------
- Idempotent: seed rows use topic_id `seed_s83_<fq>_<n>`. Re-running
  refreshes expires_at but does not duplicate.
- Real product_ids from the tenant's catalog (no orphan FKs).
- Same shape as cron rows: plan_gate=start, role_gate=owner|owner,manager,
  module=products, value_numeric/product_count populated, data_json with
  items[] for products.php to render thumbnails.
- Restricted to ALLOWED_TENANTS (7, 99) via guard.

Usage
-----
    python3 tools/seed/insights_populate.py --tenant 7
    python3 tools/seed/insights_populate.py --tenant 7 --target 5 --dry-run
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from datetime import datetime, timedelta

import pymysql
import pymysql.cursors

ENV_PATH = "/etc/runmystore/db.env"
ALLOWED_TENANTS = {7, 99}
DEFAULT_TARGET = 5
EXPIRES_DAYS = 7

FQ_ORDER = ["loss", "loss_cause", "gain", "gain_cause", "order", "anti_order"]


# ─────────────────────────────────────────────────────────────────────────
# DB
# ─────────────────────────────────────────────────────────────────────────
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


# ─────────────────────────────────────────────────────────────────────────
# Seed templates — one per fundamental_question, mirrors cron pf*() shape.
# Title placeholder {N} is replaced with the chosen product_count.
# ─────────────────────────────────────────────────────────────────────────
SEED_TEMPLATES = {
    "loss": [
        {
            "topic": "stockout_risk_72h",
            "category": "stock_health",
            "urgency": "warning",
            "role_gate": "owner",
            "title": "{N} артикула ще свършат до 72 часа",
            "action_label": "Виж списък",
            "action_type": "deeplink",
            "action_url": "/products.php?filter=low_stock_72h",
            "value_numeric": 72.00,
        },
        {
            "topic": "expiry_window_7d",
            "category": "stock_health",
            "urgency": "warning",
            "role_gate": "owner",
            "title": "{N} артикула с изтичащ срок след 7 дни",
            "action_label": "Намали цена",
            "action_type": "chat",
            "action_url": "",
            "value_numeric": 7.00,
        },
        {
            "topic": "deadstock_value",
            "category": "stock_health",
            "urgency": "critical",
            "role_gate": "owner",
            "title": "Замразен капитал в мъртви наличности — {V} лв",
            "action_label": "Виж предложения",
            "action_type": "chat",
            "action_url": "",
            "value_numeric": 1840.00,
        },
    ],
    "loss_cause": [
        {
            "topic": "competitor_price_undercut",
            "category": "pricing",
            "urgency": "info",
            "role_gate": "owner",
            "title": "{N} артикула надценени спрямо конкуренцията",
            "action_label": "Виж сравнение",
            "action_type": "chat",
            "action_url": "",
            "value_numeric": 12.50,
        },
    ],
    "gain": [
        {
            "topic": "weekend_revenue_lift",
            "category": "revenue",
            "urgency": "info",
            "role_gate": "owner",
            "title": "Уикенд оборот се покачва — последно: +{V}%",
            "action_label": "Виж графика",
            "action_type": "deeplink",
            "action_url": "/stats.php?range=weekly",
            "value_numeric": 18.00,
        },
        {
            "topic": "new_customer_share",
            "category": "audience",
            "urgency": "info",
            "role_gate": "owner",
            "title": "Нови клиенти през последните 30 дни — {N} души",
            "action_label": "Виж кохорта",
            "action_type": "chat",
            "action_url": "",
            "value_numeric": 30.00,
        },
        {
            "topic": "average_basket_growth",
            "category": "revenue",
            "urgency": "info",
            "role_gate": "owner",
            "title": "Средна кошница се покачва — {V} лв спрямо миналия месец",
            "action_label": "Виж разбивка",
            "action_type": "chat",
            "action_url": "",
            "value_numeric": 4.20,
        },
    ],
    "gain_cause": [
        {
            "topic": "bundle_synergy",
            "category": "product_mix",
            "urgency": "info",
            "role_gate": "owner",
            "title": "{N} комбинации се купуват заедно — печалба расте",
            "action_label": "Сложи отпред",
            "action_type": "chat",
            "action_url": "",
            "value_numeric": 1.45,
        },
    ],
    "order": [
        {
            "topic": "reorder_window_open",
            "category": "supply",
            "urgency": "warning",
            "role_gate": "owner,manager",
            "title": "{N} артикула в идеален прозорец за поръчка",
            "action_label": "Подготви поръчка",
            "action_type": "order_draft",
            "action_url": "",
            "value_numeric": 0.00,
        },
        {
            "topic": "supplier_minimum_alert",
            "category": "supply",
            "urgency": "info",
            "role_gate": "owner,manager",
            "title": "{N} доставчици близо до минимална партида",
            "action_label": "Виж доставчици",
            "action_type": "deeplink",
            "action_url": "/suppliers.php",
            "value_numeric": 0.00,
        },
        {
            "topic": "seasonal_lead_time",
            "category": "supply",
            "urgency": "info",
            "role_gate": "owner,manager",
            "title": "Сезонни артикули — поръчай преди {N} дни",
            "action_label": "Виж календар",
            "action_type": "chat",
            "action_url": "",
            "value_numeric": 14.00,
        },
    ],
    "anti_order": [
        {
            "topic": "overstock_supplier",
            "category": "stock_health",
            "urgency": "info",
            "role_gate": "owner,manager",
            "title": "{N} доставчици с натрупан излишък — спри поръчките",
            "action_label": "Виж списък",
            "action_type": "chat",
            "action_url": "",
            "value_numeric": 0.00,
        },
        {
            "topic": "low_velocity_category",
            "category": "stock_health",
            "urgency": "info",
            "role_gate": "owner,manager",
            "title": "Категория с нисък оборот — {N} артикула спят",
            "action_label": "Виж разбивка",
            "action_type": "chat",
            "action_url": "",
            "value_numeric": 0.00,
        },
    ],
}


# ─────────────────────────────────────────────────────────────────────────
# Helpers
# ─────────────────────────────────────────────────────────────────────────
def fetch_live_counts(cur, tenant_id: int) -> dict:
    cur.execute(
        """
        SELECT fundamental_question AS fq, COUNT(*) AS c
        FROM ai_insights
        WHERE tenant_id = %s AND module = 'products' AND expires_at > NOW()
        GROUP BY fundamental_question
        """,
        (tenant_id,),
    )
    counts = {row["fq"]: int(row["c"]) for row in cur.fetchall()}
    return {fq: counts.get(fq, 0) for fq in FQ_ORDER}


def fetch_products(cur, tenant_id: int, limit: int = 60) -> list:
    cur.execute(
        """
        SELECT id, COALESCE(name, '') AS name
        FROM products
        WHERE tenant_id = %s AND is_active = 1 AND parent_id IS NULL
        ORDER BY id
        LIMIT %s
        """,
        (tenant_id, limit),
    )
    return cur.fetchall()


def fetch_default_store_id(cur, tenant_id: int) -> int:
    cur.execute(
        "SELECT id FROM stores WHERE tenant_id = %s ORDER BY id LIMIT 1",
        (tenant_id,),
    )
    row = cur.fetchone()
    return int(row["id"]) if row else 1


def build_data_json(template: dict, products: list, n: int) -> dict:
    sliced = products[:n] if products else []
    items = [
        {
            "product_id": int(p["id"]),
            "name": p["name"][:80],
        }
        for p in sliced
    ]
    return {"count": len(items), "items": items, "seed": True}


def render_title(template: dict, n: int) -> str:
    title = template["title"]
    title = title.replace("{N}", str(n))
    title = title.replace("{V}", f"{template.get('value_numeric', 0):g}")
    return title[:255]


def upsert_seed(
    cur,
    tenant_id: int,
    store_id: int,
    fq: str,
    seq: int,
    template: dict,
    products: list,
    expires_at: datetime,
) -> int:
    """
    INSERT … ON DUPLICATE KEY would need a unique key on
    (tenant_id, module, topic_id) — schema has no such index in this repo.
    Fall back to manual upsert keyed on (tenant_id, module, topic_id).
    Returns 1 if a row was inserted, 0 if updated.
    """
    topic_id = f"seed_s83_{fq}_{seq:02d}"
    cur.execute(
        """
        SELECT id FROM ai_insights
        WHERE tenant_id = %s AND module = 'products' AND topic_id = %s
        LIMIT 1
        """,
        (tenant_id, topic_id),
    )
    existing = cur.fetchone()

    n = max(1, min(len(products), 10))
    title = render_title(template, n)
    data_json = json.dumps(build_data_json(template, products, n), ensure_ascii=False)
    value_numeric = float(template.get("value_numeric", 0))
    product_count = n if template.get("category") in {"stock_health", "supply", "product_mix"} else None

    common = {
        "store_id": store_id,
        "topic_id": topic_id,
        "category": template["category"],
        "grp": 1,
        "module": "products",
        "urgency": template["urgency"],
        "fundamental_question": fq,
        "plan_gate": "start",
        "role_gate": template["role_gate"],
        "title": title,
        "detail_text": "",
        "action_label": template["action_label"][:100],
        "action_type": template["action_type"],
        "action_url": template["action_url"][:255],
        "data_json": data_json,
        "value_numeric": value_numeric,
        "product_count": product_count,
        "expires_at": expires_at.strftime("%Y-%m-%d %H:%M:%S"),
    }

    if existing:
        cur.execute(
            """
            UPDATE ai_insights
            SET store_id=%(store_id)s, category=%(category)s, grp=%(grp)s,
                urgency=%(urgency)s, plan_gate=%(plan_gate)s, role_gate=%(role_gate)s,
                title=%(title)s, detail_text=%(detail_text)s,
                action_label=%(action_label)s, action_type=%(action_type)s,
                action_url=%(action_url)s, data_json=%(data_json)s,
                value_numeric=%(value_numeric)s, product_count=%(product_count)s,
                expires_at=%(expires_at)s
            WHERE id=%(id)s
            """,
            {**common, "id": existing["id"]},
        )
        return 0

    cur.execute(
        """
        INSERT INTO ai_insights
            (tenant_id, store_id, topic_id, category, grp, module, urgency,
             fundamental_question, plan_gate, role_gate, title, detail_text,
             action_label, action_type, action_url, data_json,
             value_numeric, product_count, created_at, expires_at)
        VALUES
            (%(tenant_id)s, %(store_id)s, %(topic_id)s, %(category)s, %(grp)s,
             %(module)s, %(urgency)s, %(fundamental_question)s, %(plan_gate)s,
             %(role_gate)s, %(title)s, %(detail_text)s, %(action_label)s,
             %(action_type)s, %(action_url)s, %(data_json)s, %(value_numeric)s,
             %(product_count)s, NOW(), %(expires_at)s)
        """,
        {**common, "tenant_id": tenant_id},
    )
    return 1


# ─────────────────────────────────────────────────────────────────────────
# Main
# ─────────────────────────────────────────────────────────────────────────
def main() -> int:
    ap = argparse.ArgumentParser(description="Top-up ai_insights for a tenant.")
    ap.add_argument("--tenant", type=int, required=True, help="Tenant id (7 or 99)")
    ap.add_argument("--target", type=int, default=DEFAULT_TARGET,
                    help=f"Per-fq target count (default {DEFAULT_TARGET})")
    ap.add_argument("--dry-run", action="store_true",
                    help="Print plan without writing")
    args = ap.parse_args()

    if args.tenant not in ALLOWED_TENANTS:
        print(f"refusing: tenant={args.tenant} not in {ALLOWED_TENANTS}", file=sys.stderr)
        return 2

    conn = connect()
    try:
        with conn.cursor() as cur:
            counts_before = fetch_live_counts(cur, args.tenant)
            products = fetch_products(cur, args.tenant)
            store_id = fetch_default_store_id(cur, args.tenant)

            print(f"tenant={args.tenant} store_id={store_id} products_pool={len(products)}")
            print("before:", " ".join(f"{fq}={counts_before[fq]}" for fq in FQ_ORDER),
                  f"total={sum(counts_before.values())}")

            if not products:
                print("no products available — aborting", file=sys.stderr)
                return 3

            expires_at = datetime.now() + timedelta(days=EXPIRES_DAYS)
            inserted = updated = 0
            for fq in FQ_ORDER:
                deficit = max(0, args.target - counts_before[fq])
                tpls = SEED_TEMPLATES[fq]
                # Always touch every template so re-runs refresh expires_at;
                # only count toward "added" the ones beyond current organic count.
                for seq, tpl in enumerate(tpls, start=1):
                    if args.dry_run:
                        print(f"  would seed fq={fq} seq={seq} topic=seed_s83_{fq}_{seq:02d}")
                        continue
                    rc = upsert_seed(cur, args.tenant, store_id, fq, seq, tpl,
                                     products, expires_at)
                    inserted += rc
                    updated += (1 - rc)
                if deficit > len(tpls):
                    print(f"  warn: fq={fq} deficit={deficit} but only "
                          f"{len(tpls)} templates available", file=sys.stderr)

            if not args.dry_run:
                conn.commit()

            counts_after = fetch_live_counts(cur, args.tenant)
            print("after:", " ".join(f"{fq}={counts_after[fq]}" for fq in FQ_ORDER),
                  f"total={sum(counts_after.values())}")
            print(f"inserted={inserted} updated={updated} (dry_run={args.dry_run})")

            min_per_fq = min(counts_after.values()) if counts_after else 0
            ok = sum(counts_after.values()) >= 18 and min_per_fq >= 2 and all(
                counts_after[fq] > 0 for fq in FQ_ORDER
            )
            print("DoD:", "PASS" if ok else "FAIL",
                  f"(total={sum(counts_after.values())}, min_per_fq={min_per_fq})")
            return 0 if ok else 1
    finally:
        conn.close()


if __name__ == "__main__":
    sys.exit(main())
