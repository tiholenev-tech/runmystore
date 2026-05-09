#!/usr/bin/env python3
"""Run all regression tests (Phase H of S130 stress build).

Usage:
    sudo -u www-data python3 tools/stress/regression_tests/runner.py

Output:
    - Stdout per-test summary
    - tools/stress/data/sandbox_runs/regression_YYYYMMDD_HHMMSS.json
"""
import importlib
import json
import sys
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

TESTS = [
    "test_01_sale_race",
    "test_02_compute_insights_module",
    "test_03_ai_insights_unique",
    "test_04_should_show_insight_test_flag",
    "test_05_urgency_limits",
    "test_06_sales_pulse_history",
]

OUTDIR = ROOT / "data" / "sandbox_runs"
OUTDIR.mkdir(parents=True, exist_ok=True)


def main():
    results = []
    for t in TESTS:
        try:
            mod = importlib.import_module(f"regression_tests.{t}")
            r = mod.run()
        except SystemExit as e:
            r = {"fix_id": t, "status": "fail", "evidence": f"SystemExit: {e}"}
        except Exception as e:
            r = {"fix_id": t, "status": "fail", "evidence": f"{type(e).__name__}: {str(e)[:200]}"}
        results.append(r)
        marker = {"pass": "✅", "fail": "❌", "skip": "⏭️", "advisory": "ℹ️"}.get(r["status"], "?")
        print(f"{marker} {r['fix_id']:40s} {r['status']:8s} {r.get('evidence','')[:80]}")

    summary = {
        "ran_at": datetime.now().isoformat(),
        "totals": {
            "pass": sum(1 for r in results if r["status"] == "pass"),
            "fail": sum(1 for r in results if r["status"] == "fail"),
            "skip": sum(1 for r in results if r["status"] == "skip"),
            "advisory": sum(1 for r in results if r["status"] == "advisory"),
        },
        "results": results,
    }
    out = OUTDIR / f"regression_{datetime.now():%Y%m%d_%H%M%S}.json"
    out.write_text(json.dumps(summary, ensure_ascii=False, indent=2, default=str))
    print(f"\n[LOG] {out}")
    return 0 if summary["totals"]["fail"] == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
