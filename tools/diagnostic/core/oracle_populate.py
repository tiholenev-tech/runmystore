"""
oracle_populate.py — bulk insert/update в seed_oracle.

UPSERT pattern по scenario_code (UNIQUE):
  - INSERT ... ON DUPLICATE KEY UPDATE
  - Не трие съществуващи rows (никога DROP scenarios — само deprecate чрез is_active=0)

Workflow:
  scenarios = list of dicts от modules/<X>/scenarios.py
  populate(scenarios, module_name) → upsert всички
  backfill_missing_categories() → попълва category=DEFAULT_CATEGORY за scenarios без category
"""

import json
import sys
from pathlib import Path
from typing import List

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from core.db_helpers import transaction, fetchall, execute  # noqa: E402
from modules.insights.oracle_rules import DEFAULT_CATEGORY  # noqa: E402


REQUIRED_FIELDS = (
    'scenario_code', 'expected_topic', 'category',
    'expected_should_appear', 'verification_type', 'scenario_description'
)


def validate_scenario(s: dict) -> str | None:
    """Връща error message ако сценарият е невалиден, иначе None."""
    for f in REQUIRED_FIELDS:
        if f not in s:
            return f"missing required field: {f}"
    if s['category'] not in ('A', 'B', 'C', 'D'):
        return f"invalid category: {s['category']}"
    if s['expected_should_appear'] not in (0, 1, True, False):
        return f"invalid expected_should_appear: {s['expected_should_appear']}"
    if not s['scenario_code'] or not isinstance(s['scenario_code'], str):
        return "scenario_code must be non-empty string"
    return None


def populate(scenarios: List[dict], module_name: str = 'insights') -> dict:
    """
    Bulk upsert в seed_oracle.
    Връща counts: {inserted: N, updated: N, errors: [...]}
    """
    inserted = 0
    updated = 0
    errors = []

    sql = """
        INSERT INTO seed_oracle
          (scenario_code, module_name, expected_topic, category,
           expected_should_appear, verification_type, verification_payload,
           scenario_description, fixture_sql, is_active)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, 1)
        ON DUPLICATE KEY UPDATE
          module_name=VALUES(module_name),
          expected_topic=VALUES(expected_topic),
          category=VALUES(category),
          expected_should_appear=VALUES(expected_should_appear),
          verification_type=VALUES(verification_type),
          verification_payload=VALUES(verification_payload),
          scenario_description=VALUES(scenario_description),
          fixture_sql=VALUES(fixture_sql),
          is_active=1,
          deprecated_at=NULL
    """

    with transaction() as c:
        cur = c.cursor()
        for s in scenarios:
            err = validate_scenario(s)
            if err:
                errors.append({'scenario_code': s.get('scenario_code', '?'), 'error': err})
                continue
            payload_json = json.dumps(s.get('verification_payload', {}), ensure_ascii=False) \
                if s.get('verification_payload') is not None else None
            try:
                cur.execute(sql, (
                    s['scenario_code'],
                    module_name,
                    s['expected_topic'],
                    s['category'],
                    int(bool(s['expected_should_appear'])),
                    s['verification_type'],
                    payload_json,
                    s['scenario_description'],
                    s.get('fixture_sql', '') or '',
                ))
                # rowcount: 1 = INSERT, 2 = UPDATE (mysql.connector convention)
                if cur.rowcount == 1:
                    inserted += 1
                elif cur.rowcount == 2:
                    updated += 1
            except Exception as e:
                errors.append({
                    'scenario_code': s['scenario_code'],
                    'error': f"{type(e).__name__}: {e}"
                })
        cur.close()

    return {'inserted': inserted, 'updated': updated, 'errors': errors}


def backfill_missing_categories(module_name: str = 'insights') -> int:
    """
    RQ-S79-3: backfill category за scenarios които нямат explicit category.
    Използва DEFAULT_CATEGORY от oracle_rules.py.
    Връща брой обновени rows.
    """
    rows = fetchall("""
        SELECT id, expected_topic
        FROM seed_oracle
        WHERE module_name = %s
          AND (category IS NULL OR category = '')
    """, (module_name,))

    updated = 0
    for r in rows:
        cat = DEFAULT_CATEGORY.get(r['expected_topic'], 'B')
        execute(
            "UPDATE seed_oracle SET category=%s WHERE id=%s",
            (cat, r['id'])
        )
        updated += 1
    return updated


def deprecate_scenario(scenario_code: str) -> bool:
    """
    Soft-delete: маркира сценарий като is_active=0 + deprecated_at=NOW().
    Никога не правим хард DELETE — пазим историята.
    """
    n = execute("""
        UPDATE seed_oracle
        SET is_active = 0, deprecated_at = NOW()
        WHERE scenario_code = %s AND is_active = 1
    """, (scenario_code,))
    return n > 0


def report_oracle_status(module_name: str = 'insights') -> dict:
    """Quick status: count by category + active/deprecated."""
    rows = fetchall("""
        SELECT category, COALESCE(is_active, 1) AS active, COUNT(*) AS cnt
        FROM seed_oracle
        WHERE module_name = %s
        GROUP BY category, active
        ORDER BY category, active DESC
    """, (module_name,))
    return {
        'by_category': rows,
        'total': sum(r['cnt'] for r in rows),
    }
