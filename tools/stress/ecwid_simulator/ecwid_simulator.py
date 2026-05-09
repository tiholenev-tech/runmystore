#!/usr/bin/env python3
"""
tools/stress/ecwid_simulator/ecwid_simulator.py

Phase L1-L2 (S130 extension). Генерира fake Ecwid-style онлайн поръчки за
STRESS Lab tenant. Записва ги в JSON spool (data/ecwid_orders/) — после
ecwid_to_runmystore_sync.py ги взима и ги превръща в sales + inventory_events.

От STRESS_BUILD_PLAN.md ред 220 ("Етап 5 — Онлайн магазин симулатор"):
  - 20-40 поръчки/ден distribution
  - Night-time продажби 22:00-02:00 (50% concentration)
  - Black Friday spike mode (5x normal volume, --mode=blackfriday)
  - Return rate 15-20% post-order (отложено: refund job в sync скрипта)
  - Email-style customer data (за GDPR consent тест)

ABSOLUTE GUARDS:
  * Симулираните поръчки сe записват в JSON spool, НЕ директно в DB.
    Това позволява dry-run / preview преди sync.
  * Random seed = 42 (deterministic).

Usage:
    python3 ecwid_simulator.py --dry-run
    python3 ecwid_simulator.py --apply --orders 30
    python3 ecwid_simulator.py --apply --mode blackfriday
    python3 ecwid_simulator.py --apply --date 2026-11-29
"""

import argparse
import json
import random
import sys
from datetime import datetime, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from _db import (
    assert_stress_tenant,
    connect,
    dry_run_log,
    load_db_config,
    resolve_stress_tenant,
    seed_rng,
)

SPOOL_DIR = Path(__file__).resolve().parent.parent / "data" / "ecwid_orders"

# Distribution: 20-40 поръчки/ден normal, 5x за blackfriday
NORMAL_RANGE = (20, 40)
BLACKFRIDAY_MULTIPLIER = 5

# Night-time concentration: 50% от поръчките падат в 22:00-02:00
NIGHT_HOURS = {22, 23, 0, 1}
NIGHT_RATIO = 0.5

# Return rate след поръчка
RETURN_RATE = (0.15, 0.20)

# Realistic distribution на статуси при сметката (преди sync)
STATUS_WEIGHTS = [
    ("PAID",        70),  # успешни
    ("PROCESSING",  15),  # чакащи payment
    ("CANCELLED",    5),  # клиент отказа
    ("PAYMENT_FAIL", 5),  # неуспешно плащане (S066)
    ("PARTIALLY_FULFILLED", 3),  # частично изпратено (S067)
    ("AWAITING_PICKUP", 2),  # cross-store pickup (S069)
]

# Fake email domain pool — никога не „реален" клиент
EMAIL_DOMAINS = [
    "stress.test", "fake.local", "fake-customers.dev",
    "ecwid-sim.test", "stresslab.invalid",
]
FIRST_NAMES = [
    "Александър", "Бистра", "Веселин", "Галина", "Димитър", "Елена",
    "Жулиен", "Зорница", "Илия", "Калина", "Любомир", "Мария",
    "Николай", "Олга", "Петър", "Радослав", "Снежана", "Тодор",
    "Цвети", "Юлиян",
]
LAST_NAMES = [
    "Иванов", "Петров", "Георгиев", "Димитров", "Стоянов", "Николов",
    "Михайлов", "Тодоров", "Колев", "Йорданов", "Стефанов", "Костадинов",
    "Атанасов", "Маринов", "Влахов", "Енчев", "Денчев", "Аврамов",
]

# Order types за разнообразие
ORDER_TYPES = [
    ("REGULAR",  60),  # обикновена поръчка
    ("GIFT",      8),  # gift order (S068)
    ("B2B",       7),  # wholesale (S069)
    ("PICKUP",   15),  # cross-store pickup (S069)
    ("ABANDONED", 10),  # abandoned cart (S070)
]


def weighted_choice(weighted: list[tuple]) -> str:
    """[(value, weight), ...] → value."""
    total = sum(w for _, w in weighted)
    r = random.uniform(0, total)
    upto = 0
    for value, weight in weighted:
        upto += weight
        if r <= upto:
            return value
    return weighted[-1][0]


def fake_customer() -> dict:
    """Симулира customer data — Email-style за GDPR consent тест (S064)."""
    fn = random.choice(FIRST_NAMES)
    ln = random.choice(LAST_NAMES)
    domain = random.choice(EMAIL_DOMAINS)
    handle = f"{fn.lower()}.{ln.lower()}.{random.randint(100, 9999)}"
    return {
        "first_name": fn,
        "last_name": ln,
        "email": f"{handle}@{domain}",
        "phone": f"+3598{random.randint(0, 9)}{random.randint(1000000, 9999999)}",
        "gdpr_consent": random.random() > 0.05,  # 95% consented
        "marketing_opt_in": random.random() > 0.4,
    }


def pick_hour_for_order() -> int:
    """50% от поръчките падат 22-01h. Останалите равномерно."""
    if random.random() < NIGHT_RATIO:
        return random.choice(list(NIGHT_HOURS))
    other = [h for h in range(24) if h not in NIGHT_HOURS]
    return random.choice(other)


def fetch_catalog(conn, tenant_id: int, limit: int = 200) -> list[dict]:
    """Чете малък каталог от STRESS Lab — продукти + цени.

    Гледа двете най-чести имена на колоните. Ако нищо не работи,
    връща празен списък — caller-ът решава.
    """
    candidates = [
        ("products", "id, name, price"),
        ("products", "id, name, sale_price AS price"),
        ("products", "id, name, retail_price AS price"),
    ]
    for table, cols in candidates:
        try:
            with conn.cursor() as cur:
                cur.execute(
                    f"SELECT {cols} FROM {table} "
                    f"WHERE tenant_id = %s LIMIT %s",
                    (tenant_id, limit),
                )
                rows = cur.fetchall()
            if rows:
                return rows
        except Exception:
            conn.rollback()
            continue
    return []


def synthesize_catalog(n: int = 50) -> list[dict]:
    """Fallback ако реалният каталог липсва — генерира stub продукти."""
    return [
        {
            "id": 10_000 + i,
            "name": f"StressProduct #{i:03d}",
            "price": round(random.uniform(5.0, 199.0), 2),
        }
        for i in range(1, n + 1)
    ]


def build_order(order_id: int, day: datetime, catalog: list[dict],
                tenant_id: int) -> dict:
    """Един fake Ecwid-style поръчков ред."""
    hour = pick_hour_for_order()
    order_dt = day.replace(hour=hour, minute=random.randint(0, 59),
                           second=random.randint(0, 59), microsecond=0)
    n_items = random.choices([1, 2, 3, 4, 5, 6], weights=[35, 25, 20, 10, 6, 4])[0]
    line_items = []
    for _ in range(n_items):
        prod = random.choice(catalog)
        qty = random.choices([1, 2, 3, 5], weights=[70, 20, 7, 3])[0]
        unit_price = float(prod["price"])
        line_items.append({
            "product_id": prod["id"],
            "name": prod["name"],
            "quantity": qty,
            "unit_price": unit_price,
            "subtotal": round(unit_price * qty, 2),
        })
    subtotal = round(sum(li["subtotal"] for li in line_items), 2)
    discount = 0.0
    if random.random() < 0.15:  # 15% от поръчките имат отстъпка
        discount = round(subtotal * random.uniform(0.05, 0.30), 2)
    shipping = round(random.choice([0, 0, 4.99, 6.99, 9.99]), 2)
    total = round(subtotal - discount + shipping, 2)

    status = weighted_choice(STATUS_WEIGHTS)
    order_type = weighted_choice(ORDER_TYPES)

    return {
        "order_id": f"ECWID-{order_id:08d}",
        "external_id": f"ext-{random.randint(10**9, 10**10 - 1)}",
        "tenant_id": tenant_id,
        "status": status,
        "type": order_type,
        "placed_at": order_dt.isoformat(),
        "customer": fake_customer(),
        "line_items": line_items,
        "subtotal": subtotal,
        "discount": discount,
        "shipping": shipping,
        "total": total,
        "currency": "BGN",
        "fulfillment_store_id": None,  # sync скриптът решава
        "_meta": {
            "synthetic": True,
            "seed": 42,
            "generator": "ecwid_simulator.py",
            "phase": "L",
        },
    }


def generate_day(target_date: datetime, mode: str, catalog: list[dict],
                 tenant_id: int, override_count: int | None = None) -> list[dict]:
    """Генерира всички поръчки за един ден."""
    if override_count is not None:
        n_orders = override_count
    else:
        lo, hi = NORMAL_RANGE
        n_orders = random.randint(lo, hi)
        if mode == "blackfriday":
            n_orders *= BLACKFRIDAY_MULTIPLIER
    base_id = int(target_date.strftime("%Y%m%d")) * 1000
    return [build_order(base_id + i, target_date, catalog, tenant_id)
            for i in range(1, n_orders + 1)]


def write_spool(orders: list[dict], target_date: datetime) -> Path:
    """Записва поръчките в JSON spool за последващ sync."""
    SPOOL_DIR.mkdir(parents=True, exist_ok=True)
    fname = f"orders_{target_date.strftime('%Y%m%d')}.json"
    path = SPOOL_DIR / fname
    payload = {
        "generated_at": datetime.now().isoformat(),
        "target_date": target_date.strftime("%Y-%m-%d"),
        "count": len(orders),
        "orders": orders,
    }
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2))
    return path


def main():
    ap = argparse.ArgumentParser(description="Ecwid online order simulator (Phase L)")
    ap.add_argument("--apply", action="store_true",
                    help="Реално записва в spool. Default = dry-run.")
    ap.add_argument("--orders", type=int, default=None,
                    help="Override броя поръчки (иначе 20-40).")
    ap.add_argument("--mode", choices=["normal", "blackfriday"], default="normal")
    ap.add_argument("--date", type=str, default=None,
                    help="Целеви ден YYYY-MM-DD. Default = днес.")
    ap.add_argument("--tenant", type=int, default=None)
    args = ap.parse_args()
    seed_rng()

    if args.date:
        target_date = datetime.strptime(args.date, "%Y-%m-%d")
    else:
        target_date = datetime.now().replace(hour=0, minute=0, second=0,
                                             microsecond=0)

    cfg = None
    conn = None
    catalog: list[dict] = []
    tenant_id = args.tenant
    try:
        cfg = load_db_config()
        conn = connect(cfg, autocommit=False)
        if tenant_id is None:
            tenant_id = resolve_stress_tenant(conn)
        if tenant_id is None:
            print("[WARN] STRESS Lab tenant не намерен — ползвам synthetic каталог.")
            tenant_id = 0
        else:
            assert_stress_tenant(tenant_id, conn)
            catalog = fetch_catalog(conn, tenant_id)
    except (FileNotFoundError, PermissionError) as e:
        print(f"[WARN] DB недостъпна ({e}) — режим offline (synthetic каталог).")
        tenant_id = tenant_id or 0
    except SystemExit:
        raise
    except Exception as e:
        print(f"[WARN] DB грешка: {e} — fallback към synthetic каталог.")
        tenant_id = tenant_id or 0
    finally:
        if conn is not None:
            try: conn.close()
            except Exception: pass

    if not catalog:
        catalog = synthesize_catalog(50)

    orders = generate_day(target_date, args.mode, catalog, tenant_id,
                          override_count=args.orders)

    summary = {
        "target_date": target_date.strftime("%Y-%m-%d"),
        "mode": args.mode,
        "tenant_id": tenant_id,
        "orders_count": len(orders),
        "catalog_size": len(catalog),
        "by_status": {},
        "by_type": {},
        "night_orders_pct": 0,
        "total_revenue": 0.0,
    }
    night_n = 0
    for o in orders:
        summary["by_status"][o["status"]] = summary["by_status"].get(o["status"], 0) + 1
        summary["by_type"][o["type"]] = summary["by_type"].get(o["type"], 0) + 1
        h = datetime.fromisoformat(o["placed_at"]).hour
        if h in NIGHT_HOURS:
            night_n += 1
        if o["status"] == "PAID":
            summary["total_revenue"] += o["total"]
    summary["total_revenue"] = round(summary["total_revenue"], 2)
    summary["night_orders_pct"] = round(100 * night_n / max(1, len(orders)), 1)

    if not args.apply:
        out = dry_run_log("ecwid_simulator", {"action": "dry-run", "summary": summary})
        print(f"[DRY-RUN] План: {out}")
        print(f"[DRY-RUN] {len(orders)} orders / night={summary['night_orders_pct']}% "
              f"/ revenue={summary['total_revenue']} BGN")
        return 0

    spool_path = write_spool(orders, target_date)
    print(f"[OK] Spool: {spool_path} ({len(orders)} orders)")
    print(f"[OK] By status: {summary['by_status']}")
    print(f"[OK] By type: {summary['by_type']}")
    print(f"[OK] Night-time concentration: {summary['night_orders_pct']}%")
    dry_run_log("ecwid_simulator", {
        "action": "applied", "spool": str(spool_path), "summary": summary,
    })
    return 0


if __name__ == "__main__":
    sys.exit(main())
