#!/usr/bin/env python3
"""
daily_runner.py — Orchestrator за TESTING_LOOP_PROTOCOL (S87).

Steps:
  1. (optional) Run tools/seed/sales_populate.py --tenant=99 --count=15
     — graceful skip ако файлът още не съществува (Code #3 dependency).
  2. Trigger compute-insights за tenant=99 (php -r computeProductInsights(99)).
  3. Query DB → counters (live insights, per fundamental_question, sales seeded,
     products count, last cron run).
  4. Write daily_snapshots/YYYY-MM-DD.json (atomic).
  5. Atomic update на latest.json symlink → today.
  6. Run snapshot_diff.py vs yesterday → append в ANOMALY_LOG.md само за 🟡/🔴.
  7. git add tools/testing_loop/ → git commit --only → git push (graceful retry 1×).

Никога не raise-ва наружу: всички грешки → log в stderr + exit 0.
Cron job това винаги да върви успешно от crontab perspective.

Usage:
  python3 daily_runner.py
  python3 daily_runner.py --snapshot-only          # skip seed + cron + diff + git
  python3 daily_runner.py --no-push                # skip git push (commit OK)
  python3 daily_runner.py --no-git                 # skip commit + push
"""

from __future__ import annotations

import argparse
import datetime as dt
import json
import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path
from typing import Any

import pymysql
import pymysql.cursors

# ─── PATHS ──────────────────────────────────────────────────────────────
REPO_ROOT       = Path("/var/www/runmystore")
LOOP_DIR        = REPO_ROOT / "tools" / "testing_loop"
SNAPSHOTS_DIR   = LOOP_DIR / "daily_snapshots"
LATEST_SYMLINK  = LOOP_DIR / "latest.json"
ANOMALY_LOG     = LOOP_DIR / "ANOMALY_LOG.md"
DIFF_SCRIPT     = LOOP_DIR / "snapshot_diff.py"
SEEDER_SCRIPT   = REPO_ROOT / "tools" / "seed" / "sales_populate.py"
DB_ENV          = Path("/etc/runmystore/db.env")

TENANT          = 99
SEED_COUNT      = 15
SUBPROCESS_TIMEOUT = 120          # seconds

# ─── LOGGING ────────────────────────────────────────────────────────────
def _log(level: str, msg: str) -> None:
    ts = dt.datetime.now().isoformat(timespec="seconds")
    print(f"[{ts}] {level:5s} {msg}", file=sys.stderr, flush=True)

def info(msg: str)  -> None: _log("INFO",  msg)
def warn(msg: str)  -> None: _log("WARN",  msg)
def error(msg: str) -> None: _log("ERROR", msg)

# ─── DB ─────────────────────────────────────────────────────────────────
def _db_creds() -> dict[str, str]:
    if not DB_ENV.is_file():
        raise RuntimeError(f"DB env missing: {DB_ENV}")
    creds: dict[str, str] = {}
    for line in DB_ENV.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        creds[k.strip()] = v.strip().strip('"').strip("'")
    return creds

def _connect() -> pymysql.connections.Connection:
    c = _db_creds()
    return pymysql.connect(
        host=c["DB_HOST"], user=c["DB_USER"], password=c["DB_PASS"],
        database=c["DB_NAME"], charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor, autocommit=True,
    )

# ─── STEP 1: SEED ───────────────────────────────────────────────────────
def step_seed() -> dict[str, Any]:
    """
    Run tools/seed/sales_populate.py if it exists. Graceful skip otherwise —
    Code #3 owns this seeder and it may not be merged yet.
    """
    out: dict[str, Any] = {"ran": False, "exit_code": None, "stdout": "", "stderr": "", "skipped_reason": None}
    if not SEEDER_SCRIPT.is_file():
        out["skipped_reason"] = "sales_populate.py not present (Code #3 dependency pending)"
        warn(out["skipped_reason"])
        return out
    cmd = [sys.executable, str(SEEDER_SCRIPT), f"--tenant={TENANT}", f"--count={SEED_COUNT}"]
    info(f"step_seed: running {' '.join(cmd)}")
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=SUBPROCESS_TIMEOUT)
        out["ran"] = True
        out["exit_code"] = r.returncode
        out["stdout"] = (r.stdout or "")[-2000:]
        out["stderr"] = (r.stderr or "")[-2000:]
        if r.returncode != 0:
            warn(f"step_seed: seeder exited {r.returncode} (graceful continue)")
    except subprocess.TimeoutExpired:
        out["skipped_reason"] = "timeout"
        error("step_seed: timeout")
    except Exception as e:                              # noqa: BLE001
        out["skipped_reason"] = f"exception: {e}"
        error(f"step_seed: {e}")
    return out

# ─── STEP 2: TRIGGER INSIGHTS COMPUTE ───────────────────────────────────
def step_cron_compute() -> dict[str, Any]:
    """
    Invoke compute-insights.php::computeProductInsights(99) via php -r,
    bypassing CLI auto-run (COMPUTE_INSIGHTS_NO_CLI guard).
    """
    out: dict[str, Any] = {"ran": False, "exit_code": None, "stdout": "", "stderr": ""}
    php_snippet = (
        f"define('COMPUTE_INSIGHTS_NO_CLI', true); "
        f"require '{REPO_ROOT}/config/database.php'; "
        f"require '{REPO_ROOT}/config/helpers.php'; "
        f"require '{REPO_ROOT}/compute-insights.php'; "
        f"$r = computeProductInsights({TENANT}); "
        f"echo json_encode(['ok'=>true,'result_keys'=>array_keys((array)$r)]);"
    )
    cmd = ["/usr/bin/php", "-r", php_snippet]
    info(f"step_cron_compute: invoking computeProductInsights({TENANT})")
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=SUBPROCESS_TIMEOUT)
        out["ran"] = True
        out["exit_code"] = r.returncode
        out["stdout"] = (r.stdout or "")[-2000:]
        out["stderr"] = (r.stderr or "")[-2000:]
        if r.returncode != 0:
            error(f"step_cron_compute: php exited {r.returncode}")
    except Exception as e:                              # noqa: BLE001
        out["error"] = str(e)
        error(f"step_cron_compute: {e}")
    return out

# ─── STEP 3: DB SNAPSHOT ────────────────────────────────────────────────
def step_snapshot() -> dict[str, Any]:
    """Compose snapshot dict — counters that diff cares about."""
    info(f"step_snapshot: querying tenant={TENANT}")
    today_str = dt.date.today().isoformat()
    snap: dict[str, Any] = {
        "snapshot_date": today_str,
        "snapshot_at":   dt.datetime.now().isoformat(timespec="seconds"),
        "tenant":        TENANT,
    }
    fq_keys = ["loss", "loss_cause", "gain", "gain_cause", "order", "anti_order"]
    snap["per_fundamental_question"] = dict.fromkeys(fq_keys, 0)
    try:
        with _connect() as cnx, cnx.cursor() as cur:
            # live insights total
            cur.execute(
                "SELECT COUNT(*) AS c FROM ai_insights "
                "WHERE tenant_id=%s AND module='products' "
                "AND (expires_at IS NULL OR expires_at > NOW())",
                (TENANT,),
            )
            snap["ai_insights_total_live"] = int((cur.fetchone() or {}).get("c", 0))

            # per fundamental_question
            cur.execute(
                "SELECT fundamental_question, COUNT(*) AS c FROM ai_insights "
                "WHERE tenant_id=%s AND module='products' "
                "AND (expires_at IS NULL OR expires_at > NOW()) "
                "GROUP BY fundamental_question",
                (TENANT,),
            )
            for row in cur.fetchall():
                k = (row.get("fundamental_question") or "")
                if k in snap["per_fundamental_question"]:
                    snap["per_fundamental_question"][k] = int(row["c"])

            # last insight created (proxy за "last cron run" за tenant=99)
            cur.execute("SELECT MAX(created_at) AS m FROM ai_insights WHERE tenant_id=%s", (TENANT,))
            row = cur.fetchone() or {}
            m = row.get("m")
            snap["ai_insights_last_created"] = m.isoformat() if m else None

            # cron heartbeat (global, не per-tenant)
            cur.execute(
                "SELECT last_run_at, last_status, last_duration_ms FROM cron_heartbeats "
                "WHERE job_name='compute_insights_15min'"
            )
            row = cur.fetchone() or {}
            snap["cron_run_at"]      = row["last_run_at"].isoformat() if row.get("last_run_at") else None
            snap["cron_last_status"] = row.get("last_status")
            snap["cron_last_ms"]     = row.get("last_duration_ms")

            # sales seeded today (proxy: completed sales за tenant=99 от 00:00 насам)
            cur.execute(
                "SELECT COUNT(*) AS c FROM sales "
                "WHERE tenant_id=%s AND status='completed' AND DATE(created_at)=CURDATE()",
                (TENANT,),
            )
            snap["sales_seeded_today"] = int((cur.fetchone() or {}).get("c", 0))

            # active product count (sanity: tenant=99 не е празен)
            cur.execute(
                "SELECT COUNT(*) AS c FROM products WHERE tenant_id=%s AND is_active=1",
                (TENANT,),
            )
            snap["products_active"] = int((cur.fetchone() or {}).get("c", 0))
    except Exception as e:                              # noqa: BLE001
        error(f"step_snapshot: DB error: {e}")
        snap["snapshot_error"] = str(e)
    return snap

# ─── STEP 3b: CAT E — Migration & ENUM regression (S88.DIAG.EXTEND) ────
def step_cat_e() -> dict[str, Any]:
    """
    Run Cat E (5 DB-direct checks) от scenarios.run_cat_e_scenarios. Failure тук
    е 🟡 (yellow) per S88.DIAG.EXTEND — не fatal за runner-а.
    """
    out: dict[str, Any] = {"ran": False, "rate": None, "results": [], "summary": ""}
    try:
        sys.path.insert(0, str(REPO_ROOT))
        from tools.diagnostic.modules.insights.scenarios import run_cat_e_scenarios
        results = run_cat_e_scenarios(TENANT)
        passed = sum(1 for r in results if r.get('status') == 'PASS')
        total = len(results)
        out["ran"] = True
        out["results"] = results
        out["rate"] = round(100.0 * passed / total, 2) if total else None
        out["summary"] = f"{passed}/{total} PASS"
        info(f"step_cat_e: {out['summary']} (rate={out['rate']}%)")
    except Exception as e:                              # noqa: BLE001
        out["error"] = f"{type(e).__name__}: {e}"
        error(f"step_cat_e: {e}")
    return out

# ─── STEP 4: WRITE SNAPSHOT FILE (atomic) ──────────────────────────────
def step_write_snapshot(snap: dict[str, Any]) -> Path:
    SNAPSHOTS_DIR.mkdir(parents=True, exist_ok=True)
    out_path = SNAPSHOTS_DIR / f"{snap['snapshot_date']}.json"
    tmp_fd, tmp_path = tempfile.mkstemp(prefix=".snap.", dir=SNAPSHOTS_DIR, suffix=".tmp")
    try:
        with os.fdopen(tmp_fd, "w", encoding="utf-8") as f:
            json.dump(snap, f, ensure_ascii=False, indent=2, sort_keys=True)
            f.write("\n")
        os.replace(tmp_path, out_path)
        info(f"step_write_snapshot: wrote {out_path}")
    except Exception:
        try: os.unlink(tmp_path)
        except OSError: pass
        raise
    return out_path

def step_update_latest_symlink(target: Path) -> None:
    """latest.json → relative symlink в same dir, atomic."""
    target_rel = target.name and Path("daily_snapshots") / target.name
    tmp_link = LOOP_DIR / f".latest.{os.getpid()}.tmp"
    try:
        if tmp_link.is_symlink() or tmp_link.exists():
            tmp_link.unlink()
        os.symlink(str(target_rel), str(tmp_link))
        os.replace(str(tmp_link), str(LATEST_SYMLINK))
        info(f"step_update_latest_symlink: latest.json → {target_rel}")
    except Exception as e:                              # noqa: BLE001
        error(f"step_update_latest_symlink: {e}")
        try:
            if tmp_link.exists() or tmp_link.is_symlink():
                tmp_link.unlink()
        except OSError: pass

# ─── STEP 5: DIFF + ANOMALY LOG ─────────────────────────────────────────
def _previous_snapshot(today_path: Path) -> Path | None:
    files = sorted(p for p in SNAPSHOTS_DIR.glob("*.json") if p.is_file() and p != today_path)
    return files[-1] if files else None

def step_diff(today_path: Path) -> dict[str, Any]:
    if not DIFF_SCRIPT.is_file():
        return {"status": "no_baseline", "reason": "snapshot_diff.py missing"}
    yesterday = _previous_snapshot(today_path)
    if not yesterday:
        return {"status": "no_baseline", "reason": "no prior snapshot to compare against"}
    cmd = [sys.executable, str(DIFF_SCRIPT),
           "--today", str(today_path), "--yesterday", str(yesterday)]
    info(f"step_diff: {' '.join(cmd)}")
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
        if r.returncode != 0:
            warn(f"step_diff: exit {r.returncode}: {r.stderr.strip()[:300]}")
            return {"status": "error", "reason": f"diff exit {r.returncode}"}
        return json.loads(r.stdout)
    except json.JSONDecodeError as e:
        return {"status": "error", "reason": f"diff JSON parse: {e}"}
    except Exception as e:                              # noqa: BLE001
        return {"status": "error", "reason": f"diff exception: {e}"}

def step_anomaly_log(diff: dict[str, Any]) -> None:
    status = (diff.get("status") or "").lower()
    if status not in {"warning", "critical"}:
        return
    icon = {"warning": "🟡", "critical": "🔴"}.get(status, "⚪")
    ts = dt.datetime.now().isoformat(timespec="seconds")
    block = (
        f"\n## {ts} — {icon} {status.upper()}\n"
        f"- **Reason:** {diff.get('reason', '?')}\n"
    )
    recs = diff.get("recommendations") or []
    if recs:
        block += "- **Recommendations:**\n"
        for rec in recs:
            block += f"  - {rec}\n"
    today = diff.get("today_summary")
    yest  = diff.get("yesterday_summary")
    if today is not None and yest is not None:
        block += f"- **Today:** {json.dumps(today, ensure_ascii=False)}\n"
        block += f"- **Yesterday:** {json.dumps(yest, ensure_ascii=False)}\n"
    try:
        existed = ANOMALY_LOG.is_file()
        with ANOMALY_LOG.open("a", encoding="utf-8") as f:
            if not existed:
                f.write("# 🚨 TESTING_LOOP — ANOMALY LOG\n\n"
                        "Append-only лог. 🟢 healthy дни не се записват.\n")
            f.write(block)
        info(f"step_anomaly_log: appended {status} entry")
    except Exception as e:                              # noqa: BLE001
        error(f"step_anomaly_log: {e}")

# ─── STEP 6: GIT COMMIT + PUSH (graceful) ──────────────────────────────
def _git(args: list[str], allow_fail: bool = False) -> subprocess.CompletedProcess:
    base = ["git", "-C", str(REPO_ROOT),
            "-c", "user.name=runmystore-testing-loop",
            "-c", "user.email=testing-loop@runmystore.local"]
    r = subprocess.run(base + args, capture_output=True, text=True, timeout=60)
    if r.returncode != 0 and not allow_fail:
        warn(f"git {' '.join(args)} → exit {r.returncode}: {r.stderr.strip()[:300]}")
    return r

def step_git_publish(no_push: bool) -> dict[str, Any]:
    out: dict[str, Any] = {"committed": False, "pushed": False, "reason": None}
    paths = [
        "tools/testing_loop/daily_snapshots",
        "tools/testing_loop/latest.json",
        "tools/testing_loop/ANOMALY_LOG.md",
    ]
    add = _git(["add", "--"] + paths, allow_fail=True)
    if add.returncode != 0:
        out["reason"] = f"git add failed: {add.stderr.strip()[:200]}"
        return out
    status = _git(["diff", "--cached", "--name-only"], allow_fail=True)
    if not status.stdout.strip():
        out["reason"] = "no changes to commit"
        info(out["reason"])
        return out
    msg = f"S87.TESTING_LOOP: daily snapshot {dt.date.today().isoformat()}"
    commit = _git(["commit", "--only"] + paths + ["-m", msg], allow_fail=True)
    if commit.returncode != 0:
        out["reason"] = f"git commit failed: {commit.stderr.strip()[:200]}"
        return out
    out["committed"] = True
    if no_push:
        out["reason"] = "no-push flag"
        return out
    push = _git(["push", "origin", "main"], allow_fail=True)
    if push.returncode != 0:
        warn("step_git_publish: push failed, retrying after pull --rebase")
        _git(["pull", "--rebase", "origin", "main"], allow_fail=True)
        push = _git(["push", "origin", "main"], allow_fail=True)
    if push.returncode == 0:
        out["pushed"] = True
    else:
        out["reason"] = f"git push failed (after retry): {push.stderr.strip()[:200]}"
    return out

# ─── MAIN ───────────────────────────────────────────────────────────────
def main() -> int:
    p = argparse.ArgumentParser(description="TESTING_LOOP daily runner")
    p.add_argument("--snapshot-only", action="store_true",
                   help="Skip seed + cron + diff + git; just write today's snapshot")
    p.add_argument("--no-push", action="store_true", help="Skip git push (commit OK)")
    p.add_argument("--no-git",  action="store_true", help="Skip git commit + push")
    args = p.parse_args()

    info(f"daily_runner start (tenant={TENANT}, snapshot-only={args.snapshot_only})")

    seed_result   = None
    cron_result   = None
    diff_result   = None
    git_result    = None

    if not args.snapshot_only:
        seed_result = step_seed()
        cron_result = step_cron_compute()

    snap = step_snapshot()

    # S88.DIAG.EXTEND: Cat E (Migration & ENUM regression) — 5 direct DB checks.
    cat_e_result = step_cat_e()
    snap["category_e"] = cat_e_result

    if seed_result is not None: snap["seed_step"] = seed_result
    if cron_result is not None: snap["cron_step"] = cron_result

    out_path = step_write_snapshot(snap)
    step_update_latest_symlink(out_path)

    if not args.snapshot_only:
        diff_result = step_diff(out_path)
        snap["diff"] = diff_result
        # Re-write snapshot now that we have diff result baked in (so latest.json
        # has status visible to beta-readiness.php without a second file).
        try:
            with out_path.open("w", encoding="utf-8") as f:
                json.dump(snap, f, ensure_ascii=False, indent=2, sort_keys=True)
                f.write("\n")
        except Exception as e:                          # noqa: BLE001
            warn(f"snapshot rewrite (with diff) failed: {e}")
        step_anomaly_log(diff_result)

    if not args.no_git and not args.snapshot_only:
        git_result = step_git_publish(no_push=args.no_push)

    summary = {
        "snapshot": str(out_path),
        "diff_status": (diff_result or {}).get("status") if diff_result else "skipped",
        "seed_ran":  bool((seed_result or {}).get("ran")),
        "cron_ran":  bool((cron_result or {}).get("ran")),
        "cat_e":     cat_e_result.get("summary") or "skipped",
        "git":       git_result or "skipped",
    }
    info(f"daily_runner end: {json.dumps(summary, default=str)}")
    return 0

if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as exc:                            # noqa: BLE001
        error(f"daily_runner FATAL: {exc}")
        sys.exit(0)  # cron не трябва да се ядоса — exit clean дори при fatal
