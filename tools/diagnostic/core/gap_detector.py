"""
gap_detector.py — намира pf*() insight функции в compute-insights.php
които НЯМАТ oracle сценарии в seed_oracle.

Workflow в TRIGGER 6 (Claude Code orchestration):
  1. Преди всяко "AI DIAG ПУСНИ" — Claude Code пуска gap_detector
  2. Output: списък функции без coverage
  3. Ако > 0 → Claude Code ПИТА Тихол преди да продължи

Exit code 3 ако има gaps (per DIAGNOSTIC_PROTOCOL conventions).
"""

import re
import sys
import json
import argparse
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from core.db_helpers import fetchall  # noqa: E402
from modules.insights.oracle_rules import (  # noqa: E402
    PF_FUNCTION_TO_TOPIC,
    all_topics,
)


PHP_PATH_DEFAULT = '/var/www/runmystore/compute-insights.php'

# pf*() helper functions (NOT insights — exclude from gap detection)
NON_INSIGHT_PF = {
    'pfDB', 'pfTableExists', 'pfColumnExists', 'pfDefaultStoreId',
    'pfCategoryFor', 'pfPlanGateFor', 'pfExpiresAt', 'pfRoleGateFor',
    'pfUpsert',
}


def find_pf_functions_in_php(php_path: str) -> set:
    """Grep всички pf*() функции от PHP source (insights only — без helpers)."""
    if not Path(php_path).exists():
        raise FileNotFoundError(f"PHP file not found: {php_path}")
    code = Path(php_path).read_text(encoding='utf-8', errors='ignore')
    matches = re.findall(r'^function (pf[A-Z][A-Za-z0-9_]+)\s*\(', code, re.MULTILINE)
    all_pf = set(matches)
    return all_pf - NON_INSIGHT_PF


def find_oracle_topics_in_db(module: str = 'insights') -> set:
    """Чете distinct expected_topic от seed_oracle (само active rows)."""
    rows = fetchall(
        "SELECT DISTINCT expected_topic FROM seed_oracle "
        "WHERE module_name = %s AND COALESCE(is_active, 1) = 1",
        (module,)
    )
    return {r['expected_topic'] for r in rows if r.get('expected_topic')}


def detect_gaps(php_path: str = PHP_PATH_DEFAULT, module: str = 'insights') -> dict:
    """
    Връща:
      {
        'pf_functions_in_code': [list of names],
        'topics_in_oracle': [list],
        'topics_expected_from_code': [list],  # по mapping от oracle_rules.py
        'unmapped_pf_functions': [pf_name които нямат entry в PF_FUNCTION_TO_TOPIC],
        'topics_without_oracle': [topic_id които са в кода, но не в seed_oracle],
        'oracle_orphans': [topic_id в seed_oracle, но не в кода — възможен deprecated]
      }
    """
    pf_in_code = find_pf_functions_in_php(php_path)

    # Map к-их функции имат known mapping
    mapped = {pf for pf in pf_in_code if pf in PF_FUNCTION_TO_TOPIC}
    unmapped = pf_in_code - mapped
    expected_topics_from_code = {PF_FUNCTION_TO_TOPIC[pf] for pf in mapped}

    topics_in_oracle = find_oracle_topics_in_db(module=module)

    return {
        'pf_functions_in_code': sorted(pf_in_code),
        'topics_in_oracle': sorted(topics_in_oracle),
        'topics_expected_from_code': sorted(expected_topics_from_code),
        'unmapped_pf_functions': sorted(unmapped),
        'topics_without_oracle': sorted(expected_topics_from_code - topics_in_oracle),
        'oracle_orphans': sorted(topics_in_oracle - expected_topics_from_code),
    }


def main():
    ap = argparse.ArgumentParser(description="Detect gaps in oracle coverage of pf*() insight functions")
    ap.add_argument('--php', default=PHP_PATH_DEFAULT, help="Path to compute-insights.php")
    ap.add_argument('--module', default='insights', help="seed_oracle.module_name to check against")
    ap.add_argument('--json', action='store_true', help="Output JSON instead of human format")
    args = ap.parse_args()

    try:
        result = detect_gaps(php_path=args.php, module=args.module)
    except Exception as e:
        if args.json:
            print(json.dumps({'error': str(e)}))
        else:
            print(f"ERROR: {e}", file=sys.stderr)
        sys.exit(2)

    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        print(f"pf*() insight функции в кода:        {len(result['pf_functions_in_code'])}")
        print(f"Оракулирани топики в DB:             {len(result['topics_in_oracle'])}")
        print()
        if result['unmapped_pf_functions']:
            print(f"⚠️  pf*() БЕЗ mapping в oracle_rules.py ({len(result['unmapped_pf_functions'])}):")
            for f in result['unmapped_pf_functions']:
                print(f"    - {f}")
        if result['topics_without_oracle']:
            print(f"❌ Топики БЕЗ сценарии в seed_oracle ({len(result['topics_without_oracle'])}):")
            for t in result['topics_without_oracle']:
                print(f"    - {t}")
        if result['oracle_orphans']:
            print(f"ℹ️  Топики в seed_oracle, но не в кода ({len(result['oracle_orphans'])}) — възможни deprecated:")
            for t in result['oracle_orphans']:
                print(f"    - {t}")
        if not (result['unmapped_pf_functions'] or result['topics_without_oracle']):
            print("✅ Покритие пълно. Никакви gaps.")

    has_gaps = bool(result['unmapped_pf_functions'] or result['topics_without_oracle'])
    sys.exit(3 if has_gaps else 0)


if __name__ == '__main__':
    main()
