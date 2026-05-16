#!/usr/bin/env python3
"""
daily_report_writer.py — чете последния stress_runs запис от DB и пише
STRESS_DAILY_REPORT.md в repo root.

При FAIL на който и да е target scenario (S001/S002/S007/S009) — touch-ва
/etc/runmystore/stress.disabled (за бъдещите cron-ове да спрат).

Usage:
    python3 tools/stress/daily_report_writer.py
"""
import json
import os
import sys
from datetime import datetime, timezone, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _db import connect, load_db_config

REPO_ROOT = Path(__file__).resolve().parent.parent.parent
REPORT_PATH = REPO_ROOT / "STRESS_DAILY_REPORT.md"
DISABLE_FILE = Path("/etc/runmystore/stress.disabled")
TARGET_SCENARIOS = ("S001", "S002", "S007", "S009")

EEST = timezone(timedelta(hours=3))


def fetch_latest(conn) -> dict | None:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, tenant_id, started_at, ended_at, duration_ms, "
            "scenarios_pass, scenarios_fail, scenarios_skip, summary_json "
            "FROM stress_runs ORDER BY id DESC LIMIT 1"
        )
        run = cur.fetchone()
        if not run:
            return None
        cur.execute(
            "SELECT scenario_id, outcome, duration_ms, fail_reason "
            "FROM stress_scenarios_log WHERE run_id=%s ORDER BY scenario_id",
            (run["id"],),
        )
        run["scenarios"] = cur.fetchall()
        return run


def build_md(run: dict) -> tuple[str, list[str]]:
    targets = {r["scenario_id"]: r for r in run["scenarios"]
               if r["scenario_id"] in TARGET_SCENARIOS}
    failed_targets = [sid for sid in TARGET_SCENARIOS
                      if targets.get(sid, {}).get("outcome") == "fail"]
    overall = "🔴 ПРОБЛЕМ" if failed_targets else "✅ OK"

    md = [
        f"# 🌙 STRESS DAILY REPORT",
        "",
        f"**Статус:** {overall}",
        f"**Run id:** {run['id']}",
        f"**Tenant:** {run['tenant_id']} (Тихол пробен — per FACT_TENANT_7.md)",
        f"**Started:** {run['started_at']}  •  Duration: {run['duration_ms']} ms",
        f"**Report generated:** {datetime.now(EEST):%Y-%m-%d %H:%M EEST}",
        "",
        "## 🎯 Target scenarios (S001, S002, S007, S009)",
        "",
        "| ID | Outcome | Duration | Note |",
        "|---|---|---|---|",
    ]
    for sid in TARGET_SCENARIOS:
        r = targets.get(sid)
        if not r:
            md.append(f"| {sid} | ⚪ missing | — | scenario file not found at runtime |")
            continue
        icon = {"pass": "✅", "fail": "🔴", "skip": "🟡"}.get(r["outcome"], "⚪")
        reason = (r["fail_reason"] or "").replace("|", "\\|")[:120] or "—"
        md.append(f"| {sid} | {icon} {r['outcome']} | {r['duration_ms']} ms | {reason} |")

    md += [
        "",
        f"## Aggregate",
        "",
        f"- Total scenarios: {len(run['scenarios'])}",
        f"- Pass: **{run['scenarios_pass']}**  •  Fail: **{run['scenarios_fail']}**  •  Skip: **{run['scenarios_skip']}**",
        "",
    ]

    all_fails = [r for r in run["scenarios"] if r["outcome"] == "fail"]
    if all_fails:
        md += [
            f"<details><summary>All {len(all_fails)} fails (non-target scenarios) — click</summary>",
            "",
            "| ID | Reason |",
            "|---|---|",
        ]
        for r in all_fails:
            reason = (r["fail_reason"] or "").replace("|", "\\|")[:140]
            md.append(f"| {r['scenario_id']} | {reason} |")
        md += ["", "</details>", ""]

    if failed_targets:
        md += [
            "## ⚠️ Crons SPRENI",
            "",
            f"Failed target scenarios: {', '.join(failed_targets)}",
            "",
            "Cron-овете няма да рестартират докато проблемът не е fix-нат.",
            "За resume след fix:",
            "",
            "```bash",
            "bash /var/www/runmystore/tools/stress_resume.sh",
            "```",
            "",
        ]

    return "\n".join(md), failed_targets


def main():
    cfg = load_db_config()
    conn = connect(cfg)
    try:
        run = fetch_latest(conn)
    finally:
        conn.close()

    if run is None:
        print("[FAIL] няма stress_runs запис — manual run-ни nightly_robot --apply --tenant 7", file=sys.stderr)
        return 1

    md, failed_targets = build_md(run)
    REPORT_PATH.write_text(md, encoding="utf-8")
    print(f"[OK] {REPORT_PATH}  ({len(md)} chars)")
    if failed_targets:
        try:
            DISABLE_FILE.parent.mkdir(parents=True, exist_ok=True)
            DISABLE_FILE.touch()
            print(f"[WARN] Target FAIL → {DISABLE_FILE} touched")
        except PermissionError:
            print(f"[WARN] Cannot touch {DISABLE_FILE} (permission). Cron-овете не са активирани още, така че няма реален impact.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
