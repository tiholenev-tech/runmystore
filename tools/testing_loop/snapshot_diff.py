#!/usr/bin/env python3
"""
snapshot_diff.py — Compare two daily snapshots → status JSON.

Thresholds (TESTING_LOOP_PROTOCOL §Алертинг прагове):
  🟢 healthy : insights в ±20% range · 6/6 questions covered · cron ≤ 30 min ago
  🟡 warning : drop 20-40% · 1-2 questions = 0 · cron 30-60 min ago
  🔴 critical: cron failed/missing · drop > 40% · 3+ questions = 0

Output (stdout, valid JSON):
  {
    "status":          "healthy" | "warning" | "critical" | "no_baseline" | "error",
    "reason":          str,
    "recommendations": [str, ...],
    "today_summary":   {...},
    "yesterday_summary": {...},
    "checks":          {...}                # per-rule outcome, debugging
  }

Usage:
  python3 snapshot_diff.py --today TODAY.json --yesterday YESTERDAY.json
"""

from __future__ import annotations

import argparse
import datetime as dt
import json
import sys
from pathlib import Path
from typing import Any

FQ_KEYS = ["loss", "loss_cause", "gain", "gain_cause", "order", "anti_order"]

WARN_DROP_PCT     = 20.0          # >= 20% drop → warning
CRIT_DROP_PCT     = 40.0          # >= 40% drop → critical
CRON_WARN_MIN     = 30
CRON_CRIT_MIN     = 60

def _load(path: Path) -> dict[str, Any]:
    with path.open("r", encoding="utf-8") as f:
        return json.load(f)

def _summary(snap: dict[str, Any]) -> dict[str, Any]:
    fq = snap.get("per_fundamental_question") or {}
    return {
        "date":         snap.get("snapshot_date"),
        "total_live":   int(snap.get("ai_insights_total_live") or 0),
        "questions_covered": sum(1 for k in FQ_KEYS if int(fq.get(k) or 0) > 0),
        "fq":           {k: int(fq.get(k) or 0) for k in FQ_KEYS},
        "cron_run_at":  snap.get("cron_run_at"),
        "cron_status":  snap.get("cron_last_status"),
    }

def _cron_age_minutes(snap: dict[str, Any]) -> float | None:
    raw = snap.get("cron_run_at")
    if not raw:
        return None
    try:
        run_at = dt.datetime.fromisoformat(str(raw))
    except ValueError:
        return None
    return max(0.0, (dt.datetime.now() - run_at).total_seconds() / 60.0)

def _drop_pct(today: int, yesterday: int) -> float:
    if yesterday <= 0:
        return 0.0
    return max(0.0, (yesterday - today) * 100.0 / yesterday)

def _rank(level: str) -> int:
    return {"healthy": 0, "warning": 1, "critical": 2}.get(level, 0)

def _worst(*levels: str) -> str:
    return max(levels, key=_rank) if levels else "healthy"

def diff(today: dict[str, Any], yesterday: dict[str, Any]) -> dict[str, Any]:
    today_sum = _summary(today)
    yest_sum  = _summary(yesterday)

    drop = _drop_pct(today_sum["total_live"], yest_sum["total_live"])
    cron_age = _cron_age_minutes(today)

    checks: dict[str, Any] = {
        "drop_pct":            round(drop, 1),
        "questions_covered":   today_sum["questions_covered"],
        "cron_age_min":        round(cron_age, 1) if cron_age is not None else None,
        "cron_status":         today_sum["cron_status"],
    }

    reasons: list[str] = []
    recommendations: list[str] = []
    levels: list[str] = []

    # ── Rule: cron freshness / health ──
    if cron_age is None or today_sum["cron_status"] in (None, "error"):
        levels.append("critical")
        reasons.append("cron_compute_insights_15min has no recent successful heartbeat")
        recommendations.append("Verify www-data crontab and `cron_heartbeats` table state.")
    elif cron_age > CRON_CRIT_MIN:
        levels.append("critical")
        reasons.append(f"cron last ran {cron_age:.0f} min ago (> {CRON_CRIT_MIN})")
        recommendations.append("Inspect /var/log/syslog and `cron_heartbeats` for failures.")
    elif cron_age > CRON_WARN_MIN:
        levels.append("warning")
        reasons.append(f"cron last ran {cron_age:.0f} min ago (> {CRON_WARN_MIN})")

    # ── Rule: insights drop ──
    if drop >= CRIT_DROP_PCT:
        levels.append("critical")
        reasons.append(f"live insights dropped {drop:.0f}% "
                       f"({yest_sum['total_live']} → {today_sum['total_live']})")
        recommendations.append("Check seed pipeline (sales_populate.py) and pf*() functions.")
    elif drop >= WARN_DROP_PCT:
        levels.append("warning")
        reasons.append(f"live insights dropped {drop:.0f}% "
                       f"({yest_sum['total_live']} → {today_sum['total_live']})")

    # ── Rule: questions coverage ──
    missing_qs = [k for k in FQ_KEYS if today_sum["fq"][k] == 0]
    if len(missing_qs) >= 3:
        levels.append("critical")
        reasons.append(f"{len(missing_qs)} fundamental_question buckets empty: {', '.join(missing_qs)}")
        recommendations.append("Top up via `tools/seed/insights_populate.py --tenant 99`.")
    elif len(missing_qs) >= 1:
        levels.append("warning")
        reasons.append(f"{len(missing_qs)} fundamental_question buckets empty: {', '.join(missing_qs)}")

    status = _worst(*levels) if levels else "healthy"
    if status == "healthy":
        reason = (f"all checks passed: drop {drop:.1f}%, "
                  f"6/6 questions covered, cron {cron_age:.0f}m ago"
                  if cron_age is not None else "all checks passed")
    else:
        reason = "; ".join(reasons)

    return {
        "status":            status,
        "reason":            reason,
        "recommendations":   recommendations,
        "today_summary":     today_sum,
        "yesterday_summary": yest_sum,
        "checks":            checks,
    }

def main() -> int:
    p = argparse.ArgumentParser(description="Diff two TESTING_LOOP snapshots")
    p.add_argument("--today",     required=True, help="Path to today's snapshot JSON")
    p.add_argument("--yesterday", required=True, help="Path to baseline snapshot JSON")
    a = p.parse_args()

    today_p = Path(a.today)
    yest_p  = Path(a.yesterday)
    if not today_p.is_file():
        print(json.dumps({"status": "error", "reason": f"missing today: {today_p}"}))
        return 1
    if not yest_p.is_file():
        print(json.dumps({"status": "no_baseline", "reason": f"missing baseline: {yest_p}"}))
        return 0
    try:
        today = _load(today_p)
        yest  = _load(yest_p)
    except Exception as e:                              # noqa: BLE001
        print(json.dumps({"status": "error", "reason": f"load failed: {e}"}))
        return 1

    out = diff(today, yest)
    print(json.dumps(out, ensure_ascii=False))
    return 0

if __name__ == "__main__":
    sys.exit(main())
