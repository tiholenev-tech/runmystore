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
  0 = всички A+D PASS, Cat E 100%
  1 = A или D fail (rollback signal)
  2 = B/C/E < threshold (warning, 🟡)
  3 = gap detected (липсват oracle entries)

S88.DIAG.EXTEND: Cat E (Migration & ENUM regression) се изпълнява извън seed/verify
pipeline-а — директни DB checks от scenarios.run_cat_e_scenarios(). При --category E
само Cat E се изпълнява (skip seed + compute-insights).
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


def _run_cat_e_only(args, tenant_id: int) -> int:
    """S88.DIAG.EXTEND: --category E shortcut — bypass seed/verify, само DB checks."""
    t0 = time.time()
    sys.path.insert(0, '/var/www/runmystore')
    from tools.diagnostic.modules.insights.scenarios import run_cat_e_scenarios
    cat_e_results = run_cat_e_scenarios(tenant_id)
    passed = sum(1 for r in cat_e_results if r['status'] == 'PASS')
    failed = len(cat_e_results) - passed
    rate = round(100.0 * passed / len(cat_e_results), 2) if cat_e_results else None
    duration = int(time.time() - t0)
    if args.orchestrated:
        print(json.dumps({
            'category_filter': 'E',
            'metrics': {
                'total_scenarios': len(cat_e_results),
                'passed': passed, 'failed': failed,
                'cat_e_rate': rate, 'duration': duration,
            },
            'results': cat_e_results,
        }, ensure_ascii=False))
    else:
        print()
        print(f"═══ DIAGNOSTIC RUN (Cat E only) — tenant={tenant_id} ═══")
        print(f"Total: {len(cat_e_results)} | PASS: {passed} | FAIL: {failed}")
        icon_e = '✅' if (rate or 0) >= 100 else '🟡'
        print(f"Категория E: {rate if rate is not None else '—'}%  {icon_e}")
        print()
        for r in cat_e_results:
            mark = '✅' if r['status'] == 'PASS' else '❌'
            print(f"  {mark} {r['name']} — {r['details']}")
        print(f"\nDuration: {duration}s")
    return 0 if failed == 0 else 2


def run(args) -> int:
    """Main runner. Връща exit code."""
    if args.category == 'E':
        tenant_id = int(args.tenant)
        assert_safe_tenant(tenant_id)
        return _run_cat_e_only(args, tenant_id)

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
    seed_ok = 0
    for s in scenarios:
        ok, err = seed_scenario(s, tenant_id)
        if ok:
            seed_ok += 1
        else:
            seed_errors.append({'scenario_code': s['scenario_code'], 'error': err})
    if not args.orchestrated:
        print(f"Seed: {seed_ok} ok, {len(seed_errors)} errors")
        if seed_errors:
            for e in seed_errors[:5]:
                print(f"  ⚠ {e['scenario_code']}: {e['error'][:100]}")

    # Step 5: Trigger compute-insights.php
    if not args.orchestrated:
        print("Извиквам compute-insights.php...")
    rc, out, err, dur = trigger_compute_insights(tenant_id)
    if rc != 0 and not args.orchestrated:
        print(f"⚠️  compute-insights.php returncode={rc}: {err[:300]}")

    # S85.DIAG.FIX: build a lookup so verification_payload from scenarios.py може
    # да попълни идентичности (customer_id и т.н.) когато seed_oracle колони липсват.
    scenario_py_payloads = {}
    try:
        import sys as _sys
        _project_root = '/var/www/runmystore'
        if _project_root not in _sys.path:
            _sys.path.insert(0, _project_root)
        from tools.diagnostic.modules.insights.scenarios import all_scenarios as _all_sc
        for _sc in _all_sc():
            scenario_py_payloads[_sc['scenario_code']] = _sc.get('verification_payload', {}) or {}
    except Exception:
        pass

    # Step 6: Verify each scenario
    results = []
    for s in scenarios:
        actual = fetch_actual_insights(tenant_id, s['expected_topic'])
        # S80: Reconstruct payload от отделните DB колони (real schema)
        payload = {}
        if s.get('expected_product_id') is not None:
            payload['product_id'] = int(s['expected_product_id'])
        if s.get('expected_partner_product_id') is not None:
            payload['a'] = int(s.get('expected_product_id') or 0)
            payload['b'] = int(s['expected_partner_product_id'])
        if s.get('expected_user_id') is not None:
            payload['user_id'] = int(s['expected_user_id'])
        if s.get('expected_customer_id') is not None:
            payload['customer_id'] = int(s['expected_customer_id'])
        if s.get('expected_rank_within') is not None:
            payload['rank_max'] = int(s['expected_rank_within'])
        if s.get('expected_value_min') is not None:
            payload['min'] = float(s['expected_value_min'])
        if s.get('expected_value_max') is not None:
            payload['max'] = float(s['expected_value_max'])
        # Recover original 'field' за value_range от may_also_appear_in
        if s.get('verification_type') == 'value_range' and s.get('expected_may_also_appear_in'):
            try:
                meta = json.loads(s['expected_may_also_appear_in'])
                if isinstance(meta, dict) and isinstance(meta.get('orig_payload'), dict):
                    if meta['orig_payload'].get('field'):
                        payload['field'] = meta['orig_payload']['field']
            except Exception:
                pass

        # S85.DIAG.FIX: добави липсващи идентичности от scenarios.py (single source of truth
        # за нови сценарии); seed_oracle колоните остават приоритетни.
        py_payload = scenario_py_payloads.get(s['scenario_code'], {})
        for _k in ('product_id', 'user_id', 'customer_id', 'a', 'b', 'rank_max', 'field', 'min', 'max'):
            if _k not in payload and _k in py_payload and py_payload[_k] is not None:
                payload[_k] = py_payload[_k]
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

    # S88.DIAG.EXTEND: Cat E (Migration & ENUM regression) — DB-direct checks,
    # няма seed/verify. Изпълняват се ВИНАГИ след стандартния pipeline.
    cat_e_results = []
    cat_e_rate = None
    cat_e_failures = []
    try:
        sys.path.insert(0, '/var/www/runmystore')
        from tools.diagnostic.modules.insights.scenarios import run_cat_e_scenarios
        cat_e_results = run_cat_e_scenarios(tenant_id)
        cat_e_passed = sum(1 for r in cat_e_results if r['status'] == 'PASS')
        if cat_e_results:
            cat_e_rate = round(100.0 * cat_e_passed / len(cat_e_results), 2)
        cat_e_failures = [
            {'scenario_code': r['name'], 'category': 'E', 'reason': r['details']}
            for r in cat_e_results if r['status'] != 'PASS'
        ]
    except Exception as _e:
        cat_e_failures = [{'scenario_code': 'cat_e_runner', 'category': 'E',
                           'reason': f'runner exception: {type(_e).__name__}: {_e}'}]

    failures.extend(cat_e_failures)
    total_with_e = len(results) + len(cat_e_results)
    passed_with_e = passed_count + (len(cat_e_results) - len(cat_e_failures))
    failed_with_e = failed_count + len(cat_e_failures)

    metrics = {
        'trigger_type': args.trigger,
        'module_name': args.module,
        'git_commit_sha': get_git_sha(),
        'total_scenarios': total_with_e,
        'passed': passed_with_e,
        'failed': failed_with_e,
        'skipped': 0,
        'cat_a_rate': calc_category_rate(results, 'A'),
        'cat_b_rate': calc_category_rate(results, 'B'),
        'cat_c_rate': calc_category_rate(results, 'C'),
        'cat_d_rate': calc_category_rate(results, 'D'),
        'cat_e_rate': cat_e_rate,
        'category_e': cat_e_results,
        'failures': failures,
        'duration': int(time.time() - t0),
        'notes': f"compute-insights rc={rc}; seed_errors={len(seed_errors)}; cat_e={len(cat_e_results)}",
    }

    # Step 8: Insert log row (note: cat_e_rate НЕ се пише в diagnostic_log —
    # ZERO touched live DB schema; Cat E живее само в snapshot/console output).
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
        e = metrics['cat_e_rate']
        print()
        print(f"═══ DIAGNOSTIC RUN #{log_id} — tenant={tenant_id} ═══")
        print(f"Total: {metrics['total_scenarios']} | PASS: {passed_with_e} | FAIL: {failed_with_e}")
        print(f"Категория A: {a if a is not None else '—'}%   {'✅' if (a or 0) >= 100 else '❌'}")
        print(f"Категория B: {b if b is not None else '—'}%")
        print(f"Категория C: {c if c is not None else '—'}%")
        print(f"Категория D: {d if d is not None else '—'}%   {'✅' if (d or 0) >= 100 else '❌'}")
        print(f"Категория E: {e if e is not None else '—'}%   {'✅' if (e or 0) >= 100 else '🟡'}")
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
    if metrics['cat_e_rate'] is not None and metrics['cat_e_rate'] < 100: return 2
    return 0


def main():
    ap = argparse.ArgumentParser(description="RunMyStore Diagnostic Framework runner")
    ap.add_argument('--module', default='insights')
    ap.add_argument('--trigger', default='manual',
                    choices=['manual','cron_weekly','cron_monthly',
                             'module_commit','user_command','milestone','suspicion'])
    ap.add_argument('--tenant', default='99', type=str)
    ap.add_argument('--pristine', action='store_true', help="Wipe test products преди seed (RQ-S79-4)")
    ap.add_argument('--scenario', default=None, help="Run single scenario by code")
    ap.add_argument('--category', default=None, choices=['E'],
                    help="Filter to category. Currently only 'E' (Cat E only — Migration/ENUM regression)")
    ap.add_argument('--orchestrated', action='store_true', help="JSON output for Claude Code")
    ap.add_argument('--skip-gap-check', action='store_true', help="Bypass gap detector (override only)")
    ap.add_argument('--verbose', action='store_true')
    ap.add_argument('--report-only', action='store_true', help="Skip seed/run, just verify last results")
    args = ap.parse_args()

    sys.exit(run(args))


if __name__ == '__main__':
    main()
