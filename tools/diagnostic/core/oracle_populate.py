"""
oracle_populate.py — populate seed_oracle от scenarios.py.

Маппва scenarios verification_payload → реалните DB колони:
  payload['product_id']        → expected_product_id
  payload['user_id']           → expected_user_id
  payload['customer_id']       → expected_customer_id
  payload['a'], payload['b']   → expected_product_id, expected_partner_product_id
  payload['rank_max']          → expected_rank_within
  payload['min'], payload['max'] → expected_value_min, expected_value_max

verification_type mapping (6 типа в DB):
  'rank_within'  → 'product_in_items' (запазваме семантиката чрез expected_rank_within)
  останалите се запазват
"""

import json
import sys
sys.path.insert(0, '/var/www/runmystore')
from tools.diagnostic.core.db_helpers import transaction, fetchall, fetchone


# Реалните 6 типа в DB ENUM
DB_VERIFICATION_TYPES = {
    'product_in_items', 'seller_match', 'pair_match',
    'value_range', 'exists_only', 'not_exists',
}

# Translation map: scenario verification_type → DB verification_type
VTYPE_TRANSLATE = {
    'product_in_items': 'product_in_items',
    'rank_within':      'product_in_items',  # rank се запазва в expected_rank_within
    'seller_match':     'seller_match',
    'pair_match':       'pair_match',
    'value_range':      'value_range',
    'exists_only':      'exists_only',
    'not_exists':       'not_exists',
    'count_match':      'exists_only',  # fallback (не очакваме този тип)
}


def map_payload_to_columns(payload: dict, vtype: str) -> dict:
    """Превръща verification_payload → отделни DB колони."""
    if not payload:
        payload = {}
    cols = {
        'expected_product_id':         payload.get('product_id'),
        'expected_partner_product_id': None,
        'expected_user_id':            payload.get('user_id'),
        'expected_customer_id':        payload.get('customer_id'),
        'expected_rank_within':        payload.get('rank_max'),
        'expected_value_min':          None,
        'expected_value_max':          None,
    }
    # pair_match: a и b
    if vtype == 'pair_match':
        cols['expected_product_id']         = payload.get('a')
        cols['expected_partner_product_id'] = payload.get('b')
    # value_range: min/max
    if vtype == 'value_range':
        cols['expected_value_min'] = payload.get('min')
        cols['expected_value_max'] = payload.get('max')
    return cols


def populate(scenarios: list, module_name: str = 'insights', tenant_id: int = 7) -> dict:
    """
    INSERT/UPDATE сценарии в seed_oracle. Връща {'inserted', 'updated', 'errors'}.

    Idempotent: scenario_code е UNIQUE → ON DUPLICATE KEY UPDATE.
    """
    inserted = 0
    updated = 0
    errors = []

    with transaction() as c:
        cur = c.cursor()
        for sc in scenarios:
            try:
                code = sc['scenario_code']
                topic = sc['expected_topic']
                cat = sc.get('category', 'B')
                vtype_raw = sc.get('verification_type', 'product_in_items')
                vtype = VTYPE_TRANSLATE.get(vtype_raw, 'product_in_items')
                payload = sc.get('verification_payload', {}) or {}
                should_appear = int(sc.get('expected_should_appear', 1))
                desc = sc.get('scenario_description', '')

                cols = map_payload_to_columns(payload, vtype)

                # Сборен may_also_appear_in от raw vtype (за audit)
                may_also = json.dumps({'orig_vtype': vtype_raw, 'orig_payload': payload})

                cur.execute("""
                    INSERT INTO seed_oracle (
                        tenant_id, scenario_code, module_name,
                        expected_topic, category,
                        expected_product_id, expected_partner_product_id,
                        expected_user_id, expected_customer_id,
                        expected_should_appear, expected_rank_within,
                        expected_value_min, expected_value_max,
                        verification_type, scenario_description,
                        expected_may_also_appear_in, is_active
                    ) VALUES (
                        %s, %s, %s, %s, %s,
                        %s, %s, %s, %s,
                        %s, %s, %s, %s,
                        %s, %s, %s, 1
                    )
                    ON DUPLICATE KEY UPDATE
                        module_name = VALUES(module_name),
                        expected_topic = VALUES(expected_topic),
                        category = VALUES(category),
                        expected_product_id = VALUES(expected_product_id),
                        expected_partner_product_id = VALUES(expected_partner_product_id),
                        expected_user_id = VALUES(expected_user_id),
                        expected_customer_id = VALUES(expected_customer_id),
                        expected_should_appear = VALUES(expected_should_appear),
                        expected_rank_within = VALUES(expected_rank_within),
                        expected_value_min = VALUES(expected_value_min),
                        expected_value_max = VALUES(expected_value_max),
                        verification_type = VALUES(verification_type),
                        scenario_description = VALUES(scenario_description),
                        expected_may_also_appear_in = VALUES(expected_may_also_appear_in),
                        is_active = 1
                """, (
                    tenant_id, code, module_name,
                    topic, cat,
                    cols['expected_product_id'], cols['expected_partner_product_id'],
                    cols['expected_user_id'], cols['expected_customer_id'],
                    should_appear, cols['expected_rank_within'],
                    cols['expected_value_min'], cols['expected_value_max'],
                    vtype, desc, may_also,
                ))
                # rowcount: 1=INSERT, 2=UPDATE
                if cur.rowcount == 1:
                    inserted += 1
                elif cur.rowcount == 2:
                    updated += 1
            except Exception as e:
                errors.append({'code': sc.get('scenario_code', '?'), 'error': str(e)})
        cur.close()

    return {'inserted': inserted, 'updated': updated, 'errors': errors}


def backfill_missing_categories(module_name: str = 'insights') -> int:
    """
    Заpълва category за S79.INSIGHTS scenarios които са с default 'B'.
    Използва oracle_rules.py за expected_topic → category mapping.
    """
    try:
        from tools.diagnostic.modules.insights.oracle_rules import topic_to_category
    except ImportError:
        return -1

    rows = fetchall("""
        SELECT id, expected_topic, scenario_code
        FROM seed_oracle
        WHERE module_name = %s AND COALESCE(is_active, 1) = 1
    """, (module_name,))

    updated = 0
    with transaction() as c:
        cur = c.cursor()
        for r in rows:
            topic = r['expected_topic']
            new_cat = topic_to_category(topic) if topic else 'B'
            if new_cat:
                cur.execute("""
                    UPDATE seed_oracle SET category = %s WHERE id = %s
                """, (new_cat, r['id']))
                if cur.rowcount > 0:
                    updated += 1
        cur.close()
    return updated


def get_summary(module_name: str = 'insights') -> dict:
    """Преглед — колко scenarios по категории."""
    rows = fetchall("""
        SELECT category, COUNT(*) AS cnt
        FROM seed_oracle
        WHERE module_name = %s AND COALESCE(is_active, 1) = 1
        GROUP BY category
        ORDER BY category
    """, (module_name,))
    total = sum(int(r['cnt']) for r in rows)
    return {
        'total': total,
        'by_category': {r['category']: int(r['cnt']) for r in rows},
    }


if __name__ == '__main__':
    print("oracle_populate self-test (preview)")
    summary = get_summary('insights')
    print(f"  Current state: {summary}")
