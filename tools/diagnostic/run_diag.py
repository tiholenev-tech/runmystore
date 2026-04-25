#!/usr/bin/env python3
"""
run_diag.py — главен CLI entry point за tools/diagnostic/.

Workflow:
  1. Parse args (--module, --trigger, --pristine, --tenant, --orchestrated, --scenario)
  2. assert_safe_tenant — refuse production
  3. (optional) pristine wipe
  4. Fetch active scenarios from seed_oracle
  5. Seed each fixture (transactional per-scenario)
  6. Trigger compute-insights.php
  7. Verify each scenario via verify_engine
  8. Insert diagnostic_log row
  9. (if Cat A/D < 100%) trigger alert_sender.send_telegram_critical
 10. Output (human or JSON)

Exit codes:
  0 = всички A+D PASS
  1 = A или D fail (rollback signal)
  2 = B или C fail (warning)
  3 = gap detected (липсват oracle entries)
"""

import sys
import json
import time
import argparse
import subprocess
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from core.db_helpers import (  # noqa: E402
    transaction, fetchall, fetchone, conn_ctx,
    assert_safe_tenant, ALLOWED_TENANTS,
)
from core.seed_runner import (  # noqa: E402
    fetch_active_scenarios, seed_scenario,
    cleanup_test_data, trigger_compute_insights,
    fetch_actual_insights,
)
from core.verify_engine import verify  # noqa: E402
from core.gap_detector import detect_gaps  # noqa: E402
from core.alert_sender import maybe_alert_critical  # noqa: E402


def get_git_sha() -> str:
    """Текущ HEAD commit SHA — за audit trail."""
    try:
        r = subprocess.run(
            ['git', 'rev-parse', 'HEAD'],
            capture_output=True, text=True, timeout=5,
            cwd='/var/www/runmystore'
        )
        return r.stdout.strip()[:40] if r.returncode == 0 else ''
    except Exception:
        return ''


def insert_diag_log(metrics: dict) -> int:
    """Insert row в diagnostic_log. Връща LAST_INSERT_ID."""
    with transaction() as c:
        cur = c.cursor()
        cur.execute("""
            INSERT INTO diagnostic_log
              (trigger_type, module_name, git_commit_sha,
               total_scenarios, passed, failed, skipped,
               category_a_pass_rate, category_b_pass_rate,
               category_c_pass_rate, category_d_pass_rate,
               failures_json, duration_seconds, notes)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """, (
            metrics['trigger_type'], metrics['module_name'], metrics['git_commit_sha'],
            metrics['total_scenarios'], metrics['passed'], metrics['failed'], metrics['skipped'],
            metrics['cat_a_rate'], metrics['cat_b_rate'],
            metrics['cat_c_rate'], metrics['cat_d_rate'],
            json.dumps(metrics['failures'], ensure_ascii=False) if metrics['failures'] else None,
            metrics['duration'], metrics.get('notes'),
        ))
        log_id = cur.lastrowid
        cur.close()
        return log_id


def calc_category_rate(results: list, category: str) -> float | None:
    """Pass rate (0-100) за конкретна категория."""
    cat_results = [r for r in results if r['category'] == category]
    if not cat_results:
        return None
    passed = sum(1 for r in cat_results if r['passed'])
    return round(100.0 * passed / len(cat_results), 2)


def run(args) -> int:
    """Main runner. Връща exit code."""
    t0 = time.time()
    tenant_id = int(args.tenant)
    assert_safe_tenant(tenant_id)

    # Step 1: Gap detection (early exit ако има gaps and not --skip-gap-check)
    if not args.skip_gap_check:
        gaps = detect_gaps()
        if gaps['unmapped_pf_functions'] or gaps['topics_without_oracle']:
            if args.orchestrated:
                print(json.dumps({'error': 'gaps_detected', 'gaps': gaps}, ensure_ascii=False))
            else:
                print("⚠️  Gap detection failed:")
                if gaps['unmapped_pf_functions']:
                    print(f"  Unmapped pf*(): {gaps['unmapped_pf_functions']}")
                if gaps['topics_without_oracle']:
                    print(f"  Topics без oracle: {gaps['topics_without_oracle']}")
                print("\nSpri и добави сценарии. Or use --skip-gap-check за bypass.")
            return 3

    # Step 2: Pristine wipe (optional)
    if args.pristine:
        if not args.orchestrated:
            print(f"Pristine wipe на tenant_id={tenant_id} (test products в range 9000-9999)...")
        counts = cleanup_test_data(tenant_id)
        if not args.orchestrated:
            print(f"  изтрити: {counts}")

    # Step 3: Fetch scenarios
    scenarios = fetch_active_scenarios(module=args.module)
    if args.scenario:
        scenarios = [s for s in scenarios if s['scenario_code'] == args.scenario]
        if not scenarios:
            print(f"Scenario {args.scenario} не е намерен или is_active=0", file=sys.stderr)
            return 1

    if not args.orchestrated:
        print(f"Заредени {len(scenarios)} активни сценария от seed_oracle.")

    # Step 4: Seed all fixtures
    seed_errors = []
    for s in scenarios:
        ok, err = seed_scenario(s, tenant_id)
        if not ok:
            seed_errors.append({'scenario_code': s['scenario_code'], 'error': err})
    if seed_errors and not args.orchestrated:
        print(f"⚠️  Seed errors: {len(seed_errors)} (continuing — verify ще покаже какви insights все пак излязоха)")

    # Step 5: Trigger compute-insights.php
    if not args.orchestrated:
        print("Извиквам compute-insights.php...")
    rc, out, err, dur = trigger_compute_insights(tenant_id)
    if rc != 0 and not args.orchestrated:
        print(f"⚠️  compute-insights.php returncode={rc}: {err[:300]}")

    # Step 6: Verify each scenario
    results = []
    for s in scenarios:
        actual = fetch_actual_insights(tenant_id, s['expected_topic'])
        payload = s.get('verification_payload')
        if isinstance(payload, (bytes, bytearray)):
            payload = json.loads(payload.decode('utf-8'))
        elif isinstance(payload, str) and payload:
            try:
                payload = json.loads(payload)
            except:
                payload = {}
        elif payload is None:
            payload = {}
        passed, reason = verify(
            s['verification_type'], actual, payload, int(s['expected_should_appear'])
        )
        results.append({
            'scenario_code': s['scenario_code'],
            'expected_topic': s['expected_topic'],
            'category': s['category'],
            'passed': passed,
            'reason': reason,
        })

    # Step 7: Aggregate
    passed_count = sum(1 for r in results if r['passed'])
    failed_count = len(results) - passed_count
    failures = [
        {'scenario_code': r['scenario_code'], 'expected_topic': r['expected_topic'],
         'category': r['category'], 'reason': r['reason']}
        for r in results if not r['passed']
    ]

    metrics = {
        'trigger_type': args.trigger,
        'module_name': args.module,
        'git_commit_sha': get_git_sha(),
        'total_scenarios': len(results),
        'passed': passed_count,
        'failed': failed_count,
        'skipped': 0,
        'cat_a_rate': calc_category_rate(results, 'A'),
        'cat_b_rate': calc_category_rate(results, 'B'),
        'cat_c_rate': calc_category_rate(results, 'C'),
        'cat_d_rate': calc_category_rate(results, 'D'),
        'failures': failures,
        'duration': int(time.time() - t0),
        'notes': f"compute-insights rc={rc}; seed_errors={len(seed_errors)}",
    }

    # Step 8: Insert log row
    log_id = insert_diag_log(metrics)
    metrics['log_id'] = log_id

    # Step 9: Trigger alert ако Cat A/D < 100%
    log_row_for_alert = {
        'category_a_pass_rate': metrics['cat_a_rate'],
        'category_d_pass_rate': metrics['cat_d_rate'],
        'failures_json': metrics['failures'],
        'trigger_type': metrics['trigger_type'],
    }
    alert = maybe_alert_critical(log_row_for_alert)

    # Step 10: Output
    if args.orchestrated:
        print(json.dumps({
            'log_id': log_id,
            'metrics': metrics,
            'alert': alert,
            'results': results if args.verbose else [],
        }, ensure_ascii=False))
    else:
        a = metrics['cat_a_rate']; d = metrics['cat_d_rate']
        b = metrics['cat_b_rate']; c = metrics['cat_c_rate']
        print()
        print(f"═══ DIAGNOSTIC RUN #{log_id} — tenant={tenant_id} ═══")
        print(f"Total: {metrics['total_scenarios']} | PASS: {passed_count} | FAIL: {failed_count}")
        print(f"Категория A: {a if a is not None else '—'}%   {'✅' if (a or 0) >= 100 else '❌'}")
        print(f"Категория B: {b if b is not None else '—'}%")
        print(f"Категория C: {c if c is not None else '—'}%")
        print(f"Категория D: {d if d is not None else '—'}%   {'✅' if (d or 0) >= 100 else '❌'}")
        if failures:
            print(f"\nFailures ({len(failures)}):")
            for f in failures[:15]:
                print(f"  ❌ [{f['category']}] {f['scenario_code']} — {f['reason']}")
            if len(failures) > 15:
                print(f"  ... +{len(failures)-15} още (виж diagnostic_log.failures_json)")
        if alert.get('is_critical'):
            print(f"\n🚨 Telegram alert: {'sent' if alert['sent'] else 'FAILED'}")
            if not alert['sent']:
                print(f"   {alert.get('detail', '')}")
        print(f"\nDuration: {metrics['duration']}s. Log id: {log_id}")

    # Exit code
    a = metrics['cat_a_rate']; d = metrics['cat_d_rate']
    if a is not None and a < 100: return 1
    if d is not None and d < 100: return 1
    if metrics['cat_b_rate'] is not None and metrics['cat_b_rate'] < 60: return 2
    if metrics['cat_c_rate'] is not None and metrics['cat_c_rate'] < 60: return 2
    return 0


def main():
    ap = argparse.ArgumentParser(description="RunMyStore Diagnostic Framework runner")
    ap.add_argument('--module', default='insights')
    ap.add_argument('--trigger', default='manual',
                    choices=['manual','cron_weekly','cron_monthly',
                             'module_commit','user_command','milestone','suspicion'])
    ap.add_argument('--tenant', default='7', type=str)
    ap.add_argument('--pristine', action='store_true', help="Wipe test products преди seed (RQ-S79-4)")
    ap.add_argument('--scenario', default=None, help="Run single scenario by code")
    ap.add_argument('--orchestrated', action='store_true', help="JSON output for Claude Code")
    ap.add_argument('--skip-gap-check', action='store_true', help="Bypass gap detector (override only)")
    ap.add_argument('--verbose', action='store_true')
    ap.add_argument('--report-only', action='store_true', help="Skip seed/run, just verify last results")
    args = ap.parse_args()

    sys.exit(run(args))


if __name__ == '__main__':
    main()
