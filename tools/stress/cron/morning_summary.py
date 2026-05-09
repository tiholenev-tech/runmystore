#!/usr/bin/env python3
"""
tools/stress/cron/morning_summary.py

Cron 06:00 — събира raw статистики от последния пробег (02:00 cron) и пише
структурирани данни за code_analyzer.sh (06:30) и STRESS_BOARD.md update.

Output:
  - tools/stress/data/dry_run_logs/morning_summary_<ts>.json (raw stats)
  - DB ред в stress_runs (вече попълнен от nightly_robot)
  - heartbeat към admin/health.php

Линкове:
  - STRESS_COMPASS.md ред 43 (06:00 cron — Сутрешен отчет)
  - STRESS_SCENARIOS_LOG.md ред 102-110 (Auto-population)
"""

import argparse
import json
import os
import sys
import time
import urllib.parse
import urllib.request
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


def heartbeat(cron_name: str, status: str, message: str = "", duration_ms: int = 0) -> None:
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
    except Exception:
        pass


def latest_run(conn, tenant_id: int) -> dict | None:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT * FROM stress_runs WHERE tenant_id = %s "
            "ORDER BY started_at DESC LIMIT 1",
            (tenant_id,),
        )
        return cur.fetchone()


def scenario_log(conn, run_id: int) -> list:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT scenario_id, outcome, duration_ms, fail_reason "
            "FROM stress_scenarios_log WHERE run_id = %s ORDER BY scenario_id",
            (run_id,),
        )
        return list(cur.fetchall())


def consecutive_failures(conn, tenant_id: int, scenario_id: str) -> int:
    """Колко поредни нощи сценарият е fail-нал."""
    with conn.cursor() as cur:
        cur.execute("""
            SELECT l.outcome, r.started_at
            FROM stress_scenarios_log l
            JOIN stress_runs r ON r.id = l.run_id
            WHERE r.tenant_id = %s AND l.scenario_id = %s
            ORDER BY r.started_at DESC LIMIT 14
        """, (tenant_id, scenario_id))
        rows = cur.fetchall()
    streak = 0
    for r in rows:
        if r["outcome"] == "fail":
            streak += 1
        else:
            break
    return streak


def errors_24h(conn, tenant_id: int) -> int:
    try:
        with conn.cursor() as cur:
            cur.execute("SHOW TABLES LIKE 'error_log'")
            if not cur.fetchone():
                return -1
            cur.execute(
                "SELECT COUNT(*) AS c FROM error_log "
                "WHERE created_at >= NOW() - INTERVAL 24 HOUR AND tenant_id = %s",
                (tenant_id,),
            )
            return int(cur.fetchone()["c"])
    except Exception:
        return -1


def slow_queries_24h(conn) -> int:
    try:
        with conn.cursor() as cur:
            cur.execute("SHOW TABLES LIKE 'slow_queries'")
            if not cur.fetchone():
                return -1
            cur.execute(
                "SELECT COUNT(*) AS c FROM slow_queries "
                "WHERE occurred_at >= NOW() - INTERVAL 24 HOUR AND duration_ms > 2000"
            )
            return int(cur.fetchone()["c"])
    except Exception:
        return -1


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tenant", type=int, default=None)
    ap.add_argument("--apply", action="store_true",
                    help="Default = пише summary файл; --apply допълва STRESS_BOARD.md секция Графа 2")
    args = ap.parse_args()
    seed_rng()
    t0 = time.perf_counter()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=True)  # read-only — autocommit ok
    tenant_id = args.tenant if args.tenant else resolve_stress_tenant(conn)
    if tenant_id is None:
        heartbeat("morning_summary", "FAIL", "STRESS Lab tenant not found")
        sys.exit("[REFUSE] STRESS Lab tenant не съществува.")
    assert_stress_tenant(tenant_id, conn)

    run = latest_run(conn, tenant_id)
    if not run:
        heartbeat("morning_summary", "WARN", "no stress_runs records yet")
        print("[WARN] Няма stress_runs записи — възможно nightly_robot не е стартирал.")
        out = dry_run_log("morning_summary", {
            "action": "no-runs", "tenant_id": tenant_id,
        })
        return 0

    log = scenario_log(conn, int(run["id"]))

    fails = []
    for entry in log:
        if entry["outcome"] != "fail":
            continue
        streak = consecutive_failures(conn, tenant_id, entry["scenario_id"])
        fails.append({
            "scenario_id": entry["scenario_id"],
            "duration_ms": entry["duration_ms"],
            "fail_reason": entry["fail_reason"],
            "consecutive_failures": streak,
            "escalation": "P0" if streak >= 3 else ("P1" if streak >= 2 else "P2"),
        })

    summary = {
        "tenant_id": tenant_id,
        "run_id": int(run["id"]),
        "started_at": run["started_at"].isoformat() if run.get("started_at") else None,
        "ended_at": run["ended_at"].isoformat() if run.get("ended_at") else None,
        "duration_ms": int(run.get("duration_ms") or 0),
        "actions_total": int(run.get("actions_total") or 0),
        "scenarios_pass": int(run.get("scenarios_pass") or 0),
        "scenarios_fail": int(run.get("scenarios_fail") or 0),
        "scenarios_skip": int(run.get("scenarios_skip") or 0),
        "fails": fails,
        "errors_24h": errors_24h(conn, tenant_id),
        "slow_queries_24h": slow_queries_24h(conn),
    }

    print(f"[SUMMARY] run #{summary['run_id']} pass={summary['scenarios_pass']} "
          f"fail={summary['scenarios_fail']} skip={summary['scenarios_skip']}")
    for f in fails:
        print(f"  ❌ {f['scenario_id']:6s} [{f['escalation']}] streak={f['consecutive_failures']} :: {f['fail_reason'][:80] if f['fail_reason'] else '-'}")

    out = dry_run_log("morning_summary", {"action": "summary", "summary": summary})
    print(f"[OK] Summary записан: {out}")

    duration_ms = int((time.perf_counter() - t0) * 1000)
    status = "OK"
    if summary["scenarios_fail"] > 0:
        status = "WARN"
    if any(f.get("consecutive_failures", 0) >= 3 for f in fails):
        status = "CRIT"
    heartbeat("morning_summary", status,
              f"run={summary['run_id']} fails={summary['scenarios_fail']}",
              duration_ms)

    return 0


if __name__ == "__main__":
    try:
        rc = main()
    except SystemExit:
        raise
    except Exception as e:
        heartbeat("morning_summary", "FAIL", f"unhandled: {type(e).__name__}: {str(e)[:200]}")
        raise
    sys.exit(rc or 0)
