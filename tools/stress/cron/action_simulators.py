#!/usr/bin/env python3
"""
tools/stress/cron/action_simulators.py — Real action implementations for nightly_robot.

Всеки simulator:
  - приема (conn, tenant_id, target_count) → връща {action, attempted, succeeded, failed, samples}
  - пише ДИРЕКТНО в DB (не през HTTP) — sandbox/STRESS Lab tenant only
  - НЕ модифицира schema (използва само вече съществуващи таблици)
  - silently skip-ва ако таблиците не съществуват (защита при partial deploy)
  - random.seed(42) deterministic чрез _db.seed_rng()

GUARDS:
  - tenant_id трябва да е минал assert_stress_tenant() (caller отговаря)
  - Никой simulator не пише в tenants, products, stores, suppliers, users
  - Само транзакционни таблици: sales, sale_items, inventory, stock_movements,
    deliveries, transfers, search_log, lost_demand, voice_log, ai_insights

Usage (от nightly_robot.py):
    from action_simulators import SIMULATORS
    for action, fn in SIMULATORS.items():
        target = plan[action]
        result = fn(conn, tenant_id, target)
        results[action] = result
"""

import random
import time
from datetime import datetime, timedelta


def _table_exists(conn, name: str) -> bool:
    with conn.cursor() as cur:
        cur.execute("SHOW TABLES LIKE %s", (name,))
        return cur.fetchone() is not None


def _columns(conn, table: str) -> set:
    with conn.cursor() as cur:
        cur.execute(f"SHOW COLUMNS FROM {table}")
        return {row["Field"] for row in cur.fetchall()}


def _stress_user_ids(conn, tenant_id: int, limit: int = 5) -> list:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id FROM users WHERE tenant_id = %s LIMIT %s",
            (tenant_id, limit),
        )
        return [int(r["id"]) for r in cur.fetchall()]


def _stress_store_ids(conn, tenant_id: int) -> list:
    if not _table_exists(conn, "stores"):
        return []
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id FROM stores WHERE tenant_id = %s",
            (tenant_id,),
        )
        return [int(r["id"]) for r in cur.fetchall()]


def _random_product_with_stock(conn, tenant_id: int, store_id: int, min_qty: int = 1) -> dict | None:
    """Find an inventory row with quantity >= min_qty for given store/tenant."""
    with conn.cursor() as cur:
        cur.execute("""
            SELECT i.product_id, i.store_id, i.quantity, p.name, p.price
            FROM inventory i
            JOIN products p ON p.id = i.product_id
            WHERE p.tenant_id = %s AND i.store_id = %s AND i.quantity >= %s
            ORDER BY RAND() LIMIT 1
        """, (tenant_id, store_id, min_qty))
        return cur.fetchone()


def _empty_result(action: str) -> dict:
    return {"action": action, "attempted": 0, "succeeded": 0, "failed": 0, "samples": []}


# ─── Simulators ────────────────────────────────────────────────────────────────

def sim_lifeboard_views(conn, tenant_id: int, target: int) -> dict:
    """No DB write — pure read simulating life-board.php landing.
    Reads recent ai_insights (same query as life-board) and counts response time.
    """
    res = _empty_result("lifeboard_views")
    if not _table_exists(conn, "ai_insights"):
        return res
    samples = []
    for _ in range(target):
        t0 = time.perf_counter()
        try:
            with conn.cursor() as cur:
                cur.execute("""
                    SELECT id, fundamental_question, urgency, action_type
                    FROM ai_insights
                    WHERE tenant_id = %s AND status = 'live' AND module = 'home'
                    ORDER BY urgency DESC, created_at DESC LIMIT 10
                """, (tenant_id,))
                cur.fetchall()
            res["succeeded"] += 1
            samples.append(round((time.perf_counter() - t0) * 1000, 1))
        except Exception:
            res["failed"] += 1
        res["attempted"] += 1
    res["samples"] = samples[:5]
    if samples:
        res["p95_ms"] = sorted(samples)[max(0, int(len(samples) * 0.95) - 1)]
    return res


def sim_sales(conn, tenant_id: int, target: int) -> dict:
    """Realistic sale insert: pick random store + product with stock,
    INSERT sales + sale_items + UPDATE inventory (decrement) + stock_movements.
    Each sale wrapped in own transaction; rollback on stock conflict.
    """
    res = _empty_result("sales")
    stores = _stress_store_ids(conn, tenant_id)
    users = _stress_user_ids(conn, tenant_id, 5)
    if not stores or not users:
        return res
    if not _table_exists(conn, "sales") or not _table_exists(conn, "sale_items"):
        return res

    sale_cols = _columns(conn, "sales")
    has_status = "status" in sale_cols
    has_total = "total" in sale_cols
    has_user_id = "user_id" in sale_cols
    has_store_id = "store_id" in sale_cols
    has_payment_method = "payment_method" in sale_cols

    samples = []
    for _ in range(target):
        store_id = random.choice(stores)
        user_id = random.choice(users)
        prod = _random_product_with_stock(conn, tenant_id, store_id, min_qty=1)
        if not prod:
            res["attempted"] += 1
            res["failed"] += 1
            continue
        qty = random.randint(1, min(3, int(prod["quantity"])))
        unit_price = float(prod.get("price") or 0)
        total = round(unit_price * qty, 2)
        # Random timestamp last 8h (simulating night-of work spread over evening shift)
        ts = datetime.now() - timedelta(minutes=random.randint(0, 480))
        try:
            with conn.cursor() as cur:
                cols = ["tenant_id", "created_at"]
                vals = [tenant_id, ts]
                if has_store_id:
                    cols.append("store_id"); vals.append(store_id)
                if has_user_id:
                    cols.append("user_id"); vals.append(user_id)
                if has_total:
                    cols.append("total"); vals.append(total)
                if has_status:
                    cols.append("status"); vals.append("completed")
                if has_payment_method:
                    cols.append("payment_method"); vals.append(random.choice(["cash", "card"]))
                placeholders = ",".join(["%s"] * len(vals))
                cur.execute(
                    f"INSERT INTO sales ({','.join(cols)}) VALUES ({placeholders})",
                    vals,
                )
                sale_id = cur.lastrowid
                cur.execute("""
                    INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, created_at)
                    VALUES (%s, %s, %s, %s, %s)
                """, (sale_id, prod["product_id"], qty, unit_price, ts))
                # Decrement inventory ATOMIC with quantity >= guard (simulates S87H race fix)
                cur.execute("""
                    UPDATE inventory SET quantity = quantity - %s
                    WHERE product_id = %s AND store_id = %s AND quantity >= %s
                """, (qty, prod["product_id"], store_id, qty))
                if cur.rowcount == 0:
                    raise RuntimeError(f"stock conflict product={prod['product_id']}")
                if _table_exists(conn, "stock_movements"):
                    cur.execute("""
                        INSERT INTO stock_movements
                        (tenant_id, product_id, store_id, type, quantity, reference_type, reference_id, created_at)
                        VALUES (%s, %s, %s, 'out', %s, 'sale', %s, %s)
                    """, (tenant_id, prod["product_id"], store_id, qty, sale_id, ts))
            conn.commit()
            res["succeeded"] += 1
            if len(samples) < 5:
                samples.append({"sale_id": sale_id, "product_id": prod["product_id"], "qty": qty, "total": total})
        except Exception as e:
            conn.rollback()
            res["failed"] += 1
            if len(samples) < 5:
                samples.append({"error": str(e)[:80]})
        res["attempted"] += 1
    res["samples"] = samples
    return res


def sim_voice_searches(conn, tenant_id: int, target: int) -> dict:
    """Симулира voice search → search_log INSERT (source='voice')."""
    res = _empty_result("voice_searches")
    if not _table_exists(conn, "search_log"):
        return res
    queries = ["Дафи 36", "Адидас 42", "Найк 40", "пижама детска", "чорапи Sonic",
               "бельо дамско S", "халат мъжки L", "Royal Tiger 38", "Ивон 75C"]
    cols = _columns(conn, "search_log")
    has_source = "source" in cols
    has_results_count = "results_count" in cols

    for _ in range(target):
        q = random.choice(queries)
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT COUNT(*) AS n FROM products WHERE tenant_id = %s AND name LIKE %s",
                    (tenant_id, f"%{q.split()[0]}%"),
                )
                rc = int(cur.fetchone()["n"])
                fields = ["tenant_id", "query_text", "created_at"]
                vals = [tenant_id, q, datetime.now() - timedelta(minutes=random.randint(0, 480))]
                if has_source:
                    fields.append("source"); vals.append("voice")
                if has_results_count:
                    fields.append("results_count"); vals.append(rc)
                cur.execute(
                    f"INSERT INTO search_log ({','.join(fields)}) VALUES ({','.join(['%s']*len(vals))})",
                    vals,
                )
            conn.commit()
            res["succeeded"] += 1
        except Exception:
            conn.rollback()
            res["failed"] += 1
        res["attempted"] += 1
    return res


def sim_lost_demand(conn, tenant_id: int, target: int) -> dict:
    """Симулира search → 0 results → INSERT lost_demand."""
    res = _empty_result("lost_demand")
    if not _table_exists(conn, "lost_demand"):
        return res
    queries = ["Бели маратонки 38", "Спортно горнище XXL", "Конче за бебе",
               "Шейничка зимна", "Гащи мъжки 44", "Чорапи коледни"]
    cols = _columns(conn, "lost_demand")
    has_source = "source" in cols
    for _ in range(target):
        q = random.choice(queries)
        try:
            with conn.cursor() as cur:
                fields = ["tenant_id", "query_text", "created_at"]
                vals = [tenant_id, q, datetime.now() - timedelta(minutes=random.randint(0, 480))]
                if has_source:
                    fields.append("source"); vals.append(random.choice(["search", "voice"]))
                cur.execute(
                    f"INSERT INTO lost_demand ({','.join(fields)}) VALUES ({','.join(['%s']*len(vals))})",
                    vals,
                )
            conn.commit()
            res["succeeded"] += 1
        except Exception:
            conn.rollback()
            res["failed"] += 1
        res["attempted"] += 1
    return res


def sim_ai_brain_questions(conn, tenant_id: int, target: int) -> dict:
    """No DB write — issues read-only SELECTs imitating AI brain query path."""
    res = _empty_result("ai_brain_questions")
    if not _table_exists(conn, "ai_insights"):
        return res
    samples = []
    for _ in range(target):
        t0 = time.perf_counter()
        try:
            with conn.cursor() as cur:
                cur.execute("""
                    SELECT category, COUNT(*) AS n
                    FROM ai_insights
                    WHERE tenant_id = %s AND status = 'live'
                    GROUP BY category
                """, (tenant_id,))
                cur.fetchall()
            res["succeeded"] += 1
            samples.append(round((time.perf_counter() - t0) * 1000, 1))
        except Exception:
            res["failed"] += 1
        res["attempted"] += 1
    if samples:
        res["p95_ms"] = sorted(samples)[max(0, int(len(samples) * 0.95) - 1)]
    return res


def sim_ambiguous_ai(conn, tenant_id: int, target: int) -> dict:
    """No DB write — placeholder for hallucination probes (skipped without AI runner)."""
    return {"action": "ambiguous_ai", "attempted": target, "succeeded": 0,
            "failed": 0, "skipped": target, "samples": [],
            "note": "AI runner required (Gemini API)"}


def sim_pill_taps(conn, tenant_id: int, target: int) -> dict:
    """Симулира pill tap → INSERT в insight_seen или ai_insight_actions ако таблицата съществува."""
    res = _empty_result("pill_taps")
    table = None
    for cand in ("ai_insight_actions", "insight_seen", "insight_taps"):
        if _table_exists(conn, cand):
            table = cand
            break
    if not table:
        # Read-only fallback
        for _ in range(target):
            try:
                with conn.cursor() as cur:
                    cur.execute("""
                        SELECT id FROM ai_insights
                        WHERE tenant_id = %s AND status = 'live'
                        ORDER BY RAND() LIMIT 1
                    """, (tenant_id,))
                    cur.fetchone()
                res["succeeded"] += 1
            except Exception:
                res["failed"] += 1
            res["attempted"] += 1
        return res

    cols = _columns(conn, table)
    for _ in range(target):
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT id FROM ai_insights WHERE tenant_id = %s AND status = 'live' ORDER BY RAND() LIMIT 1",
                    (tenant_id,),
                )
                row = cur.fetchone()
                if not row:
                    raise RuntimeError("no insight available")
                fields = ["tenant_id", "insight_id", "created_at"]
                vals = [tenant_id, int(row["id"]), datetime.now() - timedelta(minutes=random.randint(0, 480))]
                if "user_id" in cols:
                    fields.append("user_id"); vals.append(0)
                if "topic_id" in cols:
                    fields.append("topic_id"); vals.append("nightly_robot")
                if "store_id" in cols:
                    fields.append("store_id"); vals.append(0)
                if "action" in cols:
                    fields.append("action"); vals.append("tap")
                fields_existing = [f for f in fields if f in cols or f in ("created_at",)]
                vals_existing = [v for f, v in zip(fields, vals) if f in cols or f in ("created_at",)]
                if not fields_existing:
                    raise RuntimeError(f"no compatible columns in {table}")
                cur.execute(
                    f"INSERT INTO {table} ({','.join(fields_existing)}) "
                    f"VALUES ({','.join(['%s']*len(vals_existing))})",
                    vals_existing,
                )
            conn.commit()
            res["succeeded"] += 1
        except Exception:
            conn.rollback()
            res["failed"] += 1
        res["attempted"] += 1
    return res


def sim_action_button_taps(conn, tenant_id: int, target: int) -> dict:
    """Read-only — counts insights with action_type set (proxy for tappable actions)."""
    res = _empty_result("action_button_taps")
    if not _table_exists(conn, "ai_insights"):
        return res
    for _ in range(target):
        try:
            with conn.cursor() as cur:
                cur.execute("""
                    SELECT id, action_type FROM ai_insights
                    WHERE tenant_id = %s AND status = 'live' AND action_type IS NOT NULL
                    ORDER BY RAND() LIMIT 1
                """, (tenant_id,))
                cur.fetchone()
            res["succeeded"] += 1
        except Exception:
            res["failed"] += 1
        res["attempted"] += 1
    return res


def sim_deliveries(conn, tenant_id: int, target: int) -> dict:
    """Симулира доставка → INSERT delivery + увеличава inventory + stock_movements."""
    res = _empty_result("deliveries")
    if not _table_exists(conn, "deliveries"):
        res["skipped"] = target
        res["note"] = "deliveries table not deployed yet"
        return res
    stores = _stress_store_ids(conn, tenant_id)
    if not stores:
        return res
    cols = _columns(conn, "deliveries")
    suppliers = []
    if _table_exists(conn, "suppliers"):
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM suppliers WHERE tenant_id = %s LIMIT 11", (tenant_id,))
            suppliers = [int(r["id"]) for r in cur.fetchall()]

    for _ in range(target):
        store_id = random.choice(stores)
        try:
            with conn.cursor() as cur:
                fields = ["tenant_id", "store_id", "created_at"]
                vals = [tenant_id, store_id, datetime.now() - timedelta(minutes=random.randint(0, 480))]
                if "supplier_id" in cols and suppliers:
                    fields.append("supplier_id"); vals.append(random.choice(suppliers))
                if "status" in cols:
                    fields.append("status"); vals.append("received")
                if "total_value" in cols:
                    fields.append("total_value"); vals.append(round(random.uniform(50, 500), 2))
                cur.execute(
                    f"INSERT INTO deliveries ({','.join(fields)}) VALUES ({','.join(['%s']*len(vals))})",
                    vals,
                )
                delivery_id = cur.lastrowid
                # Pick 1-3 products at this store and increment them
                cur.execute("""
                    SELECT product_id FROM inventory
                    WHERE store_id = %s
                    ORDER BY RAND() LIMIT 3
                """, (store_id,))
                products = [int(r["product_id"]) for r in cur.fetchall()]
                for pid in products:
                    qty = random.randint(5, 30)
                    cur.execute("""
                        UPDATE inventory SET quantity = quantity + %s
                        WHERE product_id = %s AND store_id = %s
                    """, (qty, pid, store_id))
                    if _table_exists(conn, "stock_movements"):
                        cur.execute("""
                            INSERT INTO stock_movements
                            (tenant_id, product_id, store_id, type, quantity, reference_type, reference_id, created_at)
                            VALUES (%s, %s, %s, 'in', %s, 'delivery', %s, %s)
                        """, (tenant_id, pid, store_id, qty, delivery_id, vals[2]))
            conn.commit()
            res["succeeded"] += 1
        except Exception as e:
            conn.rollback()
            res["failed"] += 1
            if len(res["samples"]) < 3:
                res["samples"].append({"error": str(e)[:80]})
        res["attempted"] += 1
    return res


def sim_transfers(conn, tenant_id: int, target: int) -> dict:
    """Симулира трансфер между store-и (skip ако transfers таблица липсва)."""
    res = _empty_result("transfers")
    if not _table_exists(conn, "transfers"):
        res["skipped"] = target
        res["note"] = "transfers table not deployed yet"
        return res
    stores = _stress_store_ids(conn, tenant_id)
    if len(stores) < 2:
        return res
    for _ in range(target):
        a, b = random.sample(stores, 2)
        try:
            with conn.cursor() as cur:
                cur.execute("""
                    SELECT product_id, quantity FROM inventory
                    WHERE store_id = %s AND quantity >= 5
                    ORDER BY RAND() LIMIT 1
                """, (a,))
                row = cur.fetchone()
                if not row:
                    raise RuntimeError("no source product")
                qty = random.randint(1, min(3, int(row["quantity"])))
                cur.execute("""
                    INSERT INTO transfers (tenant_id, from_store_id, to_store_id, product_id, quantity, status, created_at)
                    VALUES (%s, %s, %s, %s, %s, 'completed', %s)
                """, (tenant_id, a, b, row["product_id"], qty, datetime.now()))
                cur.execute("UPDATE inventory SET quantity = quantity - %s WHERE store_id = %s AND product_id = %s AND quantity >= %s",
                            (qty, a, row["product_id"], qty))
                cur.execute("""
                    INSERT INTO inventory (tenant_id, store_id, product_id, quantity)
                    VALUES (%s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                """, (tenant_id, b, row["product_id"], qty))
            conn.commit()
            res["succeeded"] += 1
        except Exception:
            conn.rollback()
            res["failed"] += 1
        res["attempted"] += 1
    return res


def sim_inventory_counts(conn, tenant_id: int, target: int) -> dict:
    """Open + close empty count session per attempt."""
    res = _empty_result("inventory_counts")
    if not _table_exists(conn, "inventory_count_sessions"):
        res["skipped"] = target
        return res
    stores = _stress_store_ids(conn, tenant_id)
    if not stores:
        return res
    cols = _columns(conn, "inventory_count_sessions")
    for _ in range(target):
        store_id = random.choice(stores)
        try:
            with conn.cursor() as cur:
                fields = ["tenant_id", "store_id", "started_at"]
                vals = [tenant_id, store_id, datetime.now() - timedelta(minutes=random.randint(0, 240))]
                if "status" in cols:
                    fields.append("status"); vals.append("closed")
                if "ended_at" in cols:
                    fields.append("ended_at"); vals.append(vals[2] + timedelta(minutes=random.randint(5, 30)))
                cur.execute(
                    f"INSERT INTO inventory_count_sessions ({','.join(fields)}) VALUES ({','.join(['%s']*len(vals))})",
                    vals,
                )
            conn.commit()
            res["succeeded"] += 1
        except Exception:
            conn.rollback()
            res["failed"] += 1
        res["attempted"] += 1
    return res


def sim_bluetooth_scans(conn, tenant_id: int, target: int) -> dict:
    """No DB write — pure read of products by barcode (proxy for BLE scan)."""
    res = _empty_result("bluetooth_scans")
    for _ in range(target):
        try:
            with conn.cursor() as cur:
                cur.execute("""
                    SELECT id, name, barcode FROM products
                    WHERE tenant_id = %s AND barcode IS NOT NULL
                    ORDER BY RAND() LIMIT 1
                """, (tenant_id,))
                cur.fetchone()
            res["succeeded"] += 1
        except Exception:
            res["failed"] += 1
        res["attempted"] += 1
    return res


def sim_returns(conn, tenant_id: int, target: int) -> dict:
    """Симулира refund — ако refunds или sales.status = 'refunded' възможно."""
    res = _empty_result("returns")
    if not _table_exists(conn, "sales"):
        return res
    sale_cols = _columns(conn, "sales")
    has_status = "status" in sale_cols
    has_total = "total" in sale_cols

    if not has_status:
        res["skipped"] = target
        res["note"] = "sales.status missing — cannot mark refunded"
        return res
    for _ in range(target):
        try:
            with conn.cursor() as cur:
                cur.execute("""
                    SELECT s.id, s.total FROM sales s
                    WHERE s.tenant_id = %s AND s.status = 'completed'
                      AND s.created_at >= NOW() - INTERVAL 14 DAY
                    ORDER BY RAND() LIMIT 1
                """, (tenant_id,))
                sale = cur.fetchone()
                if not sale:
                    raise RuntimeError("no eligible sale")
                cur.execute("UPDATE sales SET status = 'refunded' WHERE id = %s", (sale["id"],))
                # Restock items
                cur.execute("""
                    SELECT si.product_id, si.quantity, s.store_id
                    FROM sale_items si JOIN sales s ON s.id = si.sale_id
                    WHERE si.sale_id = %s
                """, (sale["id"],))
                items = cur.fetchall()
                for it in items:
                    if it.get("store_id"):
                        cur.execute("""
                            UPDATE inventory SET quantity = quantity + %s
                            WHERE product_id = %s AND store_id = %s
                        """, (int(it["quantity"]), int(it["product_id"]), int(it["store_id"])))
                        if _table_exists(conn, "stock_movements"):
                            cur.execute("""
                                INSERT INTO stock_movements
                                (tenant_id, product_id, store_id, type, quantity, reference_type, reference_id, created_at)
                                VALUES (%s, %s, %s, 'in', %s, 'refund', %s, NOW())
                            """, (tenant_id, int(it["product_id"]), int(it["store_id"]),
                                  int(it["quantity"]), int(sale["id"])))
            conn.commit()
            res["succeeded"] += 1
        except Exception:
            conn.rollback()
            res["failed"] += 1
        res["attempted"] += 1
    return res


SIMULATORS = {
    "lifeboard_views":    sim_lifeboard_views,
    "sales":              sim_sales,
    "voice_searches":     sim_voice_searches,
    "lost_demand":        sim_lost_demand,
    "ai_brain_questions": sim_ai_brain_questions,
    "ambiguous_ai":       sim_ambiguous_ai,
    "pill_taps":          sim_pill_taps,
    "action_button_taps": sim_action_button_taps,
    "deliveries":         sim_deliveries,
    "transfers":          sim_transfers,
    "inventory_counts":   sim_inventory_counts,
    "bluetooth_scans":    sim_bluetooth_scans,
    "returns":            sim_returns,
}
