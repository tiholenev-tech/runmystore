#!/usr/bin/env python3
"""
tools/stress/cron/nightly_robot.py

Cron 02:00 — нощен симулатор. Играе ролята на 5 виртуални продавачи в 7
магазина + онлайн на STRESS Lab tenant.

От STRESS_BUILD_PLAN.md ред 178 ("Какво прави на нощ"):

  - 200-300 пъти отваря app, гледа Life Board
  - 150-400 fake продажби (за всичките 7 магазина)
  - 50-100 гласови търсения
  - 20-30 lost demand сценарии (search → 0 results)
  - 30-50 AI brain въпроси
  - 20-30 ambiguous AI questions (hallucination probe)
  - 50-100 pill taps + детайл view
  - 20-30 action button натискания
  - 5-10 fake доставки
  - 3-5 трансфери между магазини
  - 1-2 fake инвентаризации
  - 30-50 bluetooth етикети сканирания
  - 5-10 връщания/брак

Файлове записва:
  - tools/stress/data/dry_run_logs/nightly_robot_<ts>.json (план)
  - stress_runs DB ред (ако таблицата съществува)
  - stress_scenarios_log DB записи (per S001-S012)
  - heartbeat към admin/health.php

ABSOLUTE GUARDS:
  * Само върху STRESS Lab tenant (assert_stress_tenant)
  * --dry-run по default (нищо не пише)

Usage:
    python3 nightly_robot.py --dry-run
    python3 nightly_robot.py --apply --tenant <id>
"""

import argparse
import json
import os
import random
import sys
import time
import urllib.parse
import urllib.request
from datetime import datetime
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

# Phase M2 integration — Telegram alerts (best-effort, never raises)
try:
    from alerts.telegram_bot import send_alert as _tg_send
except Exception:
    _tg_send = None


def _alert_run_outcome(run_id: int | None, pass_n: int, fail_n: int,
                       skip_n: int, duration_ms: int) -> None:
    """Telegram алерт за нощен робот. fail>0 = warning, fail>5 = critical."""
    if _tg_send is None:
        return
    if fail_n == 0:
        return  # quiet success
    severity = "critical" if fail_n > 5 else "warning"
    try:
        _tg_send(severity,
                 f"nightly_robot: {fail_n} scenario failures",
                 topic="nightly_robot",
                 context={
                     "run_id": run_id,
                     "pass": pass_n,
                     "fail": fail_n,
                     "skip": skip_n,
                     "duration_ms": duration_ms,
                 })
    except Exception:
        pass

ACTION_TARGETS = {
    "lifeboard_views":      (200, 300),
    "sales":                (150, 400),
    "voice_searches":       (50, 100),
    "lost_demand":          (20, 30),
    "ai_brain_questions":   (30, 50),
    "ambiguous_ai":         (20, 30),
    "pill_taps":            (50, 100),
    "action_button_taps":   (20, 30),
    "deliveries":           (5, 10),
    "transfers":            (3, 5),
    "inventory_counts":     (1, 2),
    "bluetooth_scans":      (30, 50),
    "returns":              (5, 10),
}

SCENARIO_DIR = Path(__file__).resolve().parent.parent / "scenarios"


def heartbeat(cron_name: str, status: str, message: str = "", duration_ms: int = 0) -> None:
    """POST /admin/health.php heartbeat. Best-effort — никога не throws."""
    token = os.getenv("CRON_HEALTH_TOKEN")
    if not token:
        return
    base = os.getenv("CRON_HEALTH_URL", "https://runmystore.ai/admin/health.php")
    data = urllib.parse.urlencode({
        "cron": cron_name, "status": status, "message": message[:500], "duration_ms": duration_ms,
    }).encode()
    req = urllib.request.Request(base, data=data, method="POST",
                                 headers={"Authorization": f"Bearer {token}"})
    try:
        urllib.request.urlopen(req, timeout=5).read()
    except Exception as e:
        print(f"[WARN] heartbeat fail: {e}", file=sys.stderr)


def load_scenarios() -> list:
    if not SCENARIO_DIR.exists():
        return []
    out = []
    for path in sorted(SCENARIO_DIR.glob("S*.json")):
        try:
            with open(path) as f:
                out.append(json.load(f))
        except Exception as e:
            print(f"[WARN] scenario {path} не се чете: {e}", file=sys.stderr)
    return out


def discover_columns(conn, table: str) -> set[str]:
    with conn.cursor() as cur:
        cur.execute(f"SHOW COLUMNS FROM {table}")
        return {row["Field"] for row in cur.fetchall()}


def table_exists(conn, name: str) -> bool:
    with conn.cursor() as cur:
        cur.execute("SHOW TABLES LIKE %s", (name,))
        return cur.fetchone() is not None


def ensure_stress_tables(conn) -> None:
    """Авто-създаване на stress_runs + stress_scenarios_log ако липсват."""
    with conn.cursor() as cur:
        cur.execute("""
            CREATE TABLE IF NOT EXISTS stress_runs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                started_at DATETIME NOT NULL,
                ended_at DATETIME NULL,
                duration_ms INT NULL,
                actions_total INT NOT NULL DEFAULT 0,
                scenarios_pass INT NOT NULL DEFAULT 0,
                scenarios_fail INT NOT NULL DEFAULT 0,
                scenarios_skip INT NOT NULL DEFAULT 0,
                summary_json LONGTEXT NULL,
                INDEX idx_tenant_started (tenant_id, started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """)
        cur.execute("""
            CREATE TABLE IF NOT EXISTS stress_scenarios_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                run_id INT NOT NULL,
                scenario_id VARCHAR(16) NOT NULL,
                outcome ENUM('pass','fail','skip') NOT NULL,
                duration_ms INT NULL,
                fail_reason TEXT NULL,
                metadata_json LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_run (run_id),
                INDEX idx_scenario (scenario_id, outcome)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """)
    conn.commit()


def plan_actions() -> dict:
    """Генерира план за нощта (без mutации)."""
    plan = {}
    for action, (lo, hi) in ACTION_TARGETS.items():
        plan[action] = random.randint(lo, hi)
    plan["total"] = sum(v for k, v in plan.items() if k != "total")
    return plan


def run_scenario(conn, scenario: dict) -> dict:
    """
    Изпълнява един сценарий и връща {outcome, duration_ms, reason, metadata}.

    Това е skeleton — реалните action handlers (sale flow, race condition,
    AI brain probe) са TODO. За сега всеки сценарий == „smoke check на DB
    view" (read-only) → outcome = pass/fail според дали query успява.
    """
    sid = scenario.get("id", "?")
    t0 = time.perf_counter()
    smoke_sql = scenario.get("smoke_sql")
    expects_at_least = int(scenario.get("expects_at_least", 0))
    if not smoke_sql:
        return {
            "outcome": "skip",
            "duration_ms": 0,
            "reason": "no smoke_sql defined (waiting for module)",
            "metadata": {"id": sid},
        }
    try:
        with conn.cursor() as cur:
            cur.execute(smoke_sql)
            rows = cur.fetchall()
        elapsed = round((time.perf_counter() - t0) * 1000, 2)
        if len(rows) >= expects_at_least:
            return {"outcome": "pass", "duration_ms": elapsed, "reason": None,
                    "metadata": {"id": sid, "row_count": len(rows)}}
        return {"outcome": "fail", "duration_ms": elapsed,
                "reason": f"expected >= {expects_at_least} rows, got {len(rows)}",
                "metadata": {"id": sid, "row_count": len(rows)}}
    except Exception as e:
        elapsed = round((time.perf_counter() - t0) * 1000, 2)
        return {"outcome": "fail", "duration_ms": elapsed,
                "reason": f"{type(e).__name__}: {str(e)[:200]}",
                "metadata": {"id": sid}}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true",
                    help="Реално изпълнение. Default = dry-run.")
    ap.add_argument("--tenant", type=int, default=None)
    args = ap.parse_args()
    seed_rng()

    started_at = datetime.now()
    t0 = time.perf_counter()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=False)
    tenant_id = args.tenant if args.tenant else resolve_stress_tenant(conn)
    if tenant_id is None:
        heartbeat("nightly_robot", "FAIL", "STRESS Lab tenant not found")
        sys.exit("[REFUSE] STRESS Lab tenant не съществува.")
    assert_stress_tenant(tenant_id, conn)

    plan = plan_actions()
    scenarios = load_scenarios()

    print(f"[PLAN] tenant_id={tenant_id} — {len(scenarios)} сценария + {plan['total']} действия")

    if not args.apply:
        out = dry_run_log("nightly_robot", {
            "action": "dry-run", "tenant_id": tenant_id, "plan": plan,
            "scenarios_loaded": [s.get("id") for s in scenarios],
        })
        print(f"[DRY-RUN] План: {out}")
        return 0

    # ---------- APPLY ----------
    ensure_stress_tables(conn)

    # Insert stress_runs ред
    run_id = None
    with conn.cursor() as cur:
        cur.execute(
            "INSERT INTO stress_runs (tenant_id, started_at, actions_total) VALUES (%s, %s, %s)",
            (tenant_id, started_at, plan["total"]),
        )
        run_id = cur.lastrowid
    conn.commit()

    pass_n = fail_n = skip_n = 0
    scenario_results = []
    for sc in scenarios:
        result = run_scenario(conn, sc)
        outcome = result["outcome"]
        if outcome == "pass": pass_n += 1
        elif outcome == "fail": fail_n += 1
        else: skip_n += 1
        scenario_results.append({"id": sc.get("id"), **result})
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "INSERT INTO stress_scenarios_log "
                    "(run_id, scenario_id, outcome, duration_ms, fail_reason, metadata_json) "
                    "VALUES (%s, %s, %s, %s, %s, %s)",
                    (run_id, sc.get("id"), outcome, int(result["duration_ms"]),
                     result["reason"], json.dumps(result["metadata"], ensure_ascii=False)),
                )
            conn.commit()
        except Exception as e:
            print(f"[WARN] log insert fail: {e}", file=sys.stderr)

    # NOTE: Реалните action simulators (fake продажби, voice searches и т.н.)
    # са TODO. Те трябва да викнат същите PHP endpoints (sale.php POST,
    # search.php?q= и т.н.) или директно DB writes. За сега nightly_robot
    # изпълнява само scenario smoke checks; action_count в plan е target
    # за бъдещата имплементация.

    duration_ms = int((time.perf_counter() - t0) * 1000)
    summary = {"plan": plan, "scenarios": scenario_results}
    with conn.cursor() as cur:
        cur.execute(
            "UPDATE stress_runs SET ended_at = NOW(), duration_ms = %s, "
            "scenarios_pass = %s, scenarios_fail = %s, scenarios_skip = %s, summary_json = %s "
            "WHERE id = %s",
            (duration_ms, pass_n, fail_n, skip_n,
             json.dumps(summary, ensure_ascii=False), run_id),
        )
    conn.commit()

    print(f"[OK] run_id={run_id} pass={pass_n} fail={fail_n} skip={skip_n} duration={duration_ms}ms")
    dry_run_log("nightly_robot", {
        "action": "applied", "tenant_id": tenant_id, "run_id": run_id,
        "pass": pass_n, "fail": fail_n, "skip": skip_n, "duration_ms": duration_ms,
        "summary": summary,
    })

    status = "OK" if fail_n == 0 else "WARN"
    heartbeat("nightly_robot", status,
              f"run={run_id} pass={pass_n} fail={fail_n} skip={skip_n}",
              duration_ms)
    _alert_run_outcome(run_id, pass_n, fail_n, skip_n, duration_ms)
    return 0 if fail_n == 0 else 1


if __name__ == "__main__":
    try:
        rc = main()
    except SystemExit:
        raise
    except Exception as e:
        heartbeat("nightly_robot", "FAIL", f"unhandled: {type(e).__name__}: {str(e)[:200]}")
        if _tg_send is not None:
            try:
                _tg_send("critical",
                         f"nightly_robot CRASH: {type(e).__name__}",
                         topic="nightly_robot",
                         context={"error": str(e)[:300]})
            except Exception:
                pass
        raise
    sys.exit(rc or 0)
