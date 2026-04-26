"""seed_runner.py — S81 v3 (positional argv + cross-tenant test cleanup)."""

import sys, json, time, subprocess
from pathlib import Path
from typing import Optional
import sqlparse

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from core.db_helpers import (
    fetchall, fetchone, transaction, conn_ctx,
    assert_safe_tenant, ALLOWED_TENANTS,
)

TEST_PRODUCT_ID_RANGE = (9000, 9999)
TEST_SALE_ID_RANGE = (90000, 99999)
TEST_USER_ID_RANGE = (8000, 8099)
TEST_CUSTOMER_ID_RANGE = (7000, 7099)


def fetch_active_scenarios(module: str = 'insights') -> list:
    db_rows = fetchall("""
        SELECT id, scenario_code, module_name, expected_topic, category,
               expected_should_appear, verification_type,
               expected_product_id, expected_partner_product_id,
               expected_user_id, expected_customer_id,
               expected_rank_within, expected_value_min, expected_value_max,
               expected_may_also_appear_in, scenario_description
        FROM seed_oracle
        WHERE module_name = %s AND COALESCE(is_active, 1) = 1
        ORDER BY category, expected_topic, scenario_code
    """, (module,))
    fixture_map = {}
    if module == 'insights':
        # S81 fix: ensure /var/www/runmystore in sys.path before import
        import sys as _sys
        _project_root = '/var/www/runmystore'
        if _project_root not in _sys.path:
            _sys.path.insert(0, _project_root)
        try:
            from tools.diagnostic.modules.insights.scenarios import all_scenarios
            for sc in all_scenarios():
                fixture_map[sc['scenario_code']] = sc.get('fixture_sql', '')
        except Exception as _e:
            # S81: log instead of silent pass
            print(f"[fetch_active_scenarios] WARNING: failed to load Python fixtures: {_e}", file=_sys.stderr)
    for row in db_rows:
        row['fixture_sql'] = fixture_map.get(row['scenario_code'], '')
    return db_rows


def cleanup_test_data(tenant_id: int) -> dict:
    """S81 v3: wipe ALL test rows in dedicated ID ranges (cross-tenant safe).
    ai_insights se trie SAMO за specified tenant_id (защитено от assert_safe_tenant)."""
    assert_safe_tenant(tenant_id)
    pmin, pmax = TEST_PRODUCT_ID_RANGE
    smin, smax = TEST_SALE_ID_RANGE
    cmin, cmax = TEST_CUSTOMER_ID_RANGE
    counts = {}
    with transaction() as c:
        cur = c.cursor()
        cur.execute(
            "DELETE FROM sale_items WHERE product_id BETWEEN %s AND %s OR sale_id BETWEEN %s AND %s",
            (pmin, pmax, smin, smax))
        counts['sale_items'] = cur.rowcount
        try:
            cur.execute(
                "DELETE FROM returns WHERE product_id BETWEEN %s AND %s OR sale_id BETWEEN %s AND %s",
                (pmin, pmax, smin, smax))
            counts['returns'] = cur.rowcount
        except Exception:
            counts['returns'] = 0
        cur.execute("DELETE FROM sales WHERE id BETWEEN %s AND %s", (smin, smax))
        counts['sales'] = cur.rowcount
        cur.execute("DELETE FROM inventory WHERE product_id BETWEEN %s AND %s", (pmin, pmax))
        counts['inventory'] = cur.rowcount
        cur.execute("DELETE FROM products WHERE id BETWEEN %s AND %s", (pmin, pmax))
        counts['products'] = cur.rowcount
        try:
            cur.execute("DELETE FROM customers WHERE id BETWEEN %s AND %s", (cmin, cmax))
            counts['customers'] = cur.rowcount
        except Exception:
            counts['customers'] = 0
        cur.execute("DELETE FROM ai_insights WHERE tenant_id = %s", (tenant_id,))
        counts['ai_insights'] = cur.rowcount
        cur.close()
    return counts


def seed_scenario(scenario_row: dict, tenant_id: int) -> tuple:
    assert_safe_tenant(tenant_id)
    fixture = scenario_row.get('fixture_sql', '') or ''
    if not fixture.strip():
        return True, None
    fixture = fixture.replace('{{tenant_id}}', str(tenant_id))
    try:
        with transaction() as c:
            cur = c.cursor()
            for stmt in sqlparse.split(fixture):
                stmt_s = stmt.strip().rstrip(';').strip()
                if stmt_s:
                    cur.execute(stmt_s)
            cur.close()
        return True, None
    except Exception as e:
        return False, f"{type(e).__name__}: {e}"


def trigger_compute_insights(tenant_id: int, php_path: str = '/var/www/runmystore') -> tuple:
    """S81 v3: positional argv (compute-insights/cron-insights both use $argv[1])."""
    assert_safe_tenant(tenant_id)
    cron_php = f'{php_path}/cron-insights.php'
    if not Path(cron_php).exists():
        cron_php = f'{php_path}/compute-insights.php'
    t0 = time.time()
    try:
        r = subprocess.run(
            ['php', cron_php, str(tenant_id)],
            capture_output=True, text=True, timeout=120, cwd=php_path)
        return r.returncode, r.stdout, r.stderr, time.time() - t0
    except subprocess.TimeoutExpired:
        return 124, '', 'TIMEOUT after 120s', time.time() - t0
    except Exception as e:
        return 1, '', f"{type(e).__name__}: {e}", time.time() - t0


def fetch_actual_insights(tenant_id: int, expected_topic: str) -> Optional[dict]:
    row = fetchone("""
        SELECT data_json FROM ai_insights
        WHERE tenant_id = %s AND topic_id = %s
        ORDER BY created_at DESC LIMIT 1
    """, (tenant_id, expected_topic))
    if not row or not row.get('data_json'):
        return None
    try:
        if isinstance(row['data_json'], (bytes, bytearray)):
            return json.loads(row['data_json'].decode('utf-8'))
        if isinstance(row['data_json'], str):
            return json.loads(row['data_json'])
        return row['data_json']
    except Exception:
        return None
