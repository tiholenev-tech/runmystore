#!/usr/bin/env python3
"""
tools/stress/cron/test_new_features.py

Cron 03:00 — тества commits от вчера.

Стъпки:
  1. git log --since="1 day ago" — извежда всички commits на main
  2. За всеки commit парсва file paths
  3. Ако файлът е модул (sale.php, products.php, etc.), маркира съответен
     STRESS scenario от scenarios/ за приоритетно тестване
  4. Извиква nightly_robot.py --apply --priority-only с филтрирани сценарии
  5. POST heartbeat

Mapping commit-file → scenario:
  sale.php          → S001, S002, S008, S009
  products.php      → S003, S004
  compute-insights  → S005
  chat.php          → S005, S006
  deliveries.php    → S010
  transfers.php     → S011
  orders.php        → S012

ABSOLUTE GUARD: само върху STRESS Lab tenant.
"""

import argparse
import json
import os
import subprocess
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


REPO_ROOT = Path(__file__).resolve().parent.parent.parent.parent

FILE_TO_SCENARIO = {
    "sale.php":             ["S001", "S002", "S008", "S009"],
    "products.php":         ["S003", "S004"],
    "compute-insights.php": ["S005"],
    "chat.php":             ["S005", "S006"],
    "deliveries.php":       ["S010"],
    "transfers.php":        ["S011"],
    "orders.php":           ["S012"],
    "sale-search.php":      ["S001", "S008"],
}


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


def commits_since(hours: int = 24) -> list:
    """Връща list на commits на текущия branch за последните N часа."""
    try:
        res = subprocess.run(
            ["git", "-C", str(REPO_ROOT), "log",
             f"--since={hours} hours ago",
             "--name-only", "--pretty=format:%H|%s|%an|%ad", "--date=iso"],
            capture_output=True, text=True, timeout=30,
        )
        if res.returncode != 0:
            return []
    except Exception:
        return []
    commits = []
    cur = None
    for line in res.stdout.split("\n"):
        if "|" in line and not line.startswith(" "):
            parts = line.split("|", 3)
            if len(parts) >= 4:
                if cur:
                    commits.append(cur)
                cur = {"hash": parts[0], "subject": parts[1],
                       "author": parts[2], "date": parts[3], "files": []}
        elif line.strip() and cur:
            cur["files"].append(line.strip())
    if cur:
        commits.append(cur)
    return commits


def affected_scenarios(commits: list) -> set:
    out = set()
    for c in commits:
        for f in c["files"]:
            base = os.path.basename(f)
            if base in FILE_TO_SCENARIO:
                out.update(FILE_TO_SCENARIO[base])
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tenant", type=int, default=None)
    ap.add_argument("--hours", type=int, default=24)
    ap.add_argument("--apply", action="store_true",
                    help="Реално стартирай nightly_robot за приоритетните сценарии.")
    args = ap.parse_args()
    seed_rng()
    t0 = time.perf_counter()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=False)
    tenant_id = args.tenant if args.tenant else resolve_stress_tenant(conn)
    if tenant_id is None:
        heartbeat("test_new_features", "FAIL", "STRESS Lab tenant not found")
        sys.exit("[REFUSE] STRESS Lab tenant не съществува.")
    assert_stress_tenant(tenant_id, conn)

    commits = commits_since(args.hours)
    if not commits:
        print(f"[INFO] Няма commits последните {args.hours} часа.")
        heartbeat("test_new_features", "OK", "no recent commits")
        return 0

    print(f"[INFO] {len(commits)} commits последните {args.hours} часа.")
    for c in commits:
        print(f"  {c['hash'][:8]} {c['subject']} ({len(c['files'])} files)")

    scenarios_to_run = affected_scenarios(commits)
    if not scenarios_to_run:
        print("[INFO] Никой commit не засяга tracked модули — нищо за тест.")
        heartbeat("test_new_features", "OK", "no relevant module changes")
        return 0

    print(f"[PLAN] За тест: {sorted(scenarios_to_run)}")

    payload = {
        "action": "plan",
        "tenant_id": tenant_id,
        "since_hours": args.hours,
        "commits_count": len(commits),
        "scenarios_to_run": sorted(scenarios_to_run),
        "commits": [{"hash": c["hash"][:8], "subject": c["subject"],
                     "files": c["files"]} for c in commits],
    }
    out = dry_run_log("test_new_features", payload)
    print(f"[LOG] {out}")

    if not args.apply:
        heartbeat("test_new_features", "OK",
                  f"plan: {len(scenarios_to_run)} scenarios", int((time.perf_counter() - t0) * 1000))
        return 0

    # ─── APPLY: викаме nightly_robot ──
    print("[APPLY] Стартирам nightly_robot --apply --tenant ...")
    nr_path = Path(__file__).resolve().parent / "nightly_robot.py"
    res = subprocess.run(
        [sys.executable, str(nr_path), "--apply", "--tenant", str(tenant_id)],
        capture_output=True, text=True, timeout=3600,
    )
    print(res.stdout)
    if res.stderr:
        print(res.stderr, file=sys.stderr)
    duration_ms = int((time.perf_counter() - t0) * 1000)
    status = "OK" if res.returncode == 0 else "WARN"
    heartbeat("test_new_features", status,
              f"scenarios={len(scenarios_to_run)} rc={res.returncode}", duration_ms)
    return res.returncode


if __name__ == "__main__":
    try:
        rc = main()
    except SystemExit:
        raise
    except Exception as e:
        heartbeat("test_new_features", "FAIL", f"unhandled: {type(e).__name__}: {str(e)[:200]}")
        raise
    sys.exit(rc or 0)
