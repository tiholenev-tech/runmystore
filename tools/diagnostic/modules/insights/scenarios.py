"""
scenarios.py — 50+ test scenarios за 19-те pf*() функции.
Категории: A (критични) / B (важни) / C (декорация) / D (граници) / E (миграции).

Cat E (S88.DIAG.EXTEND): regression срещу AIBRAIN_WIRE миграция (commit 2a43852).
Не seed-ва fixtures — директно ходи в DB и проверява schema/data invariants.
"""

from typing import List
from .fixtures import (
    make_product, make_inventory, make_sale, make_return, make_customer,
    make_parent_with_variations, make_product_variant,
    product_with_sales, product_zombie, product_silent,
)


def zero_stock_with_sales_scenarios() -> List[dict]:
    return [
        {
            'scenario_code': 'zero_stock_pos_0',
            'expected_topic': 'zero_stock_with_sales',
            'category': 'A', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9001},
            'scenario_description': 'Stock=0 + 3 sales last 30d',
            'fixture_sql': product_with_sales(9001, 'TEST-ZSS-1', 'ZeroStockActive', 50, 25,
                                               qty_in_stock=0, sale_count=3, days_ago_first=10),
        },
        {
            'scenario_code': 'zero_stock_neg_0',
            'expected_topic': 'zero_stock_with_sales',
            'category': 'A', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9002},
            'scenario_description': 'Stock=0 no sales',
            'fixture_sql': product_silent(9002, 'TEST-ZSS-2', 'ZeroStockSilent', 50, 25,
                                           qty_in_stock=0, days_old=60),
        },
        {
            'scenario_code': 'zero_stock_d_boundary',
            'expected_topic': 'zero_stock_with_sales',
            'category': 'D', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9003},
            'scenario_description': 'Stock=1 + sales (not zero)',
            'fixture_sql': product_with_sales(9003, 'TEST-ZSS-3', 'OneStock', 50, 25,
                                               qty_in_stock=1, sale_count=3),
        },
    ]


def below_min_urgent_scenarios() -> List[dict]:
    return [
        {
            'scenario_code': 'below_min_pos_0',
            'expected_topic': 'below_min_urgent',
            'category': 'A', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9011},
            'scenario_description': 'Stock=1 min=5',
            'fixture_sql': product_with_sales(9011, 'TEST-BMU-1', 'BelowMin', 30, 15,
                                               qty_in_stock=1, sale_count=2, min_qty=5),
        },
        {
            'scenario_code': 'below_min_neg_0',
            'expected_topic': 'below_min_urgent',
            'category': 'A', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9012},
            'scenario_description': 'Stock=10 min=5',
            'fixture_sql': product_with_sales(9012, 'TEST-BMU-2', 'AboveMin', 30, 15,
                                               qty_in_stock=10, sale_count=2, min_qty=5),
        },
        {
            'scenario_code': 'below_min_d_exact',
            'expected_topic': 'below_min_urgent',
            'category': 'D', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9013},
            'scenario_description': 'Stock=5 min=5 boundary',
            'fixture_sql': product_with_sales(9013, 'TEST-BMU-3', 'AtMin', 30, 15,
                                               qty_in_stock=5, sale_count=2, min_qty=5),
        },
    ]


def running_out_today_scenarios() -> List[dict]:
    # pfRunningOutToday: avg_daily = sold_30d / 30; alert when stock <= avg_daily.
    # Need sold_30d ≥ 5 AND stock ≤ sold_30d/30 → avg_daily=1, stock=1 минимум.
    return [
        {
            'scenario_code': 'running_out_pos_0',
            'expected_topic': 'running_out_today',
            'category': 'A', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9021},
            'scenario_description': 'Stock=1 avg_daily≈1 (30 sales/30d)',
            'fixture_sql': product_with_sales(9021, 'TEST-ROT-1', 'FastRunner', 40, 20,
                                               qty_in_stock=1, sale_count=30, days_ago_first=29),
        },
        {
            'scenario_code': 'running_out_neg_0',
            'expected_topic': 'running_out_today',
            'category': 'A', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9022},
            'scenario_description': 'High stock slow seller',
            'fixture_sql': product_with_sales(9022, 'TEST-ROT-2', 'SlowSeller', 40, 20,
                                               qty_in_stock=100, sale_count=2, days_ago_first=14),
        },
    ]


def selling_at_loss_scenarios() -> List[dict]:
    return [
        {
            'scenario_code': 'selling_loss_pos_0',
            'expected_topic': 'selling_at_loss',
            'category': 'A', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9031},
            'scenario_description': 'retail<cost',
            'fixture_sql': product_with_sales(9031, 'TEST-SAL-1', 'LossLeader', 10, 15,
                                               qty_in_stock=20, sale_count=3),
        },
        {
            'scenario_code': 'selling_loss_neg_0',
            'expected_topic': 'selling_at_loss',
            'category': 'A', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9032},
            'scenario_description': 'normal profit',
            'fixture_sql': product_with_sales(9032, 'TEST-SAL-2', 'NormalProfit', 20, 10,
                                               qty_in_stock=20, sale_count=3),
        },
        {
            'scenario_code': 'selling_loss_d_equal',
            'expected_topic': 'selling_at_loss',
            'category': 'D', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9033},
            'scenario_description': 'retail=cost break-even',
            'fixture_sql': product_with_sales(9033, 'TEST-SAL-3', 'BreakEven', 15, 15,
                                               qty_in_stock=20, sale_count=3),
        },
    ]


def no_cost_price_scenarios() -> List[dict]:
    return [
        {
            'scenario_code': 'no_cost_pos_0',
            'expected_topic': 'no_cost_price',
            'category': 'A', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9041},
            'scenario_description': 'cost=0',
            'fixture_sql': product_with_sales(9041, 'TEST-NCP-1', 'NoCost', 30, 0,
                                               qty_in_stock=10, sale_count=2),
        },
        {
            'scenario_code': 'no_cost_neg_0',
            'expected_topic': 'no_cost_price',
            'category': 'A', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9042},
            'scenario_description': 'cost set',
            'fixture_sql': product_with_sales(9042, 'TEST-NCP-2', 'WithCost', 30, 15,
                                               qty_in_stock=10, sale_count=2),
        },
    ]


def margin_below_15_scenarios() -> List[dict]:
    return [
        {
            'scenario_code': 'margin_low_pos_0',
            'expected_topic': 'margin_below_15',
            'category': 'A', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9051},
            'scenario_description': '5pct margin',
            'fixture_sql': product_with_sales(9051, 'TEST-MB15-1', 'ThinMargin', 10, 9.5,
                                               qty_in_stock=20, sale_count=3),
        },
        {
            'scenario_code': 'margin_low_neg_0',
            'expected_topic': 'margin_below_15',
            'category': 'A', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9052},
            'scenario_description': '100pct margin',
            'fixture_sql': product_with_sales(9052, 'TEST-MB15-2', 'FatMargin', 100, 50,
                                               qty_in_stock=20, sale_count=3),
        },
        {
            'scenario_code': 'margin_low_d_exact',
            'expected_topic': 'margin_below_15',
            'category': 'D', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9053},
            'scenario_description': 'exactly 15pct boundary (retail=20, cost=17 → margin=15.0%)',
            'fixture_sql': product_with_sales(9053, 'TEST-MB15-3', 'BorderMargin', 20, 17,
                                               qty_in_stock=20, sale_count=3),
        },
        {
            'scenario_code': 'margin_low_b_pop',
            'expected_topic': 'margin_below_15',
            'category': 'B', 'expected_should_appear': 1,
            'verification_type': 'rank_within',
            'verification_payload': {'product_id': 9054, 'rank_max': 5},
            'scenario_description': 'top-5 thin margin',
            'fixture_sql': product_with_sales(9054, 'TEST-MB15-4', 'PopThin', 20, 18.5,
                                               qty_in_stock=50, sale_count=10, days_ago_first=10),
        },
    ]


def seller_discount_killer_scenarios() -> List[dict]:
    # pfSellerDiscountKiller: HAVING avg_disc > 20 AND items_count >= 10.
    # Bad seller: 12 продажби с discount_pct=40 (avg=40 > 20). Good seller: 0 disconto.
    user_id_bad = 8050
    user_id_good = 8051
    bad_lines = (
        make_product(9061, 'TEST-SDK-1', 'DiscountedItem', 100, 50)
        + make_inventory(9061, 100)
    )
    for i in range(12):
        bad_lines += make_sale(90600 + i, 9061, 100, qty=1,
                               days_ago=max(1, 25 - i), user_id=user_id_bad,
                               discount_pct=40)
    sql_bad = bad_lines
    sql_good = (
        make_product(9062, 'TEST-SDK-2', 'NormalSale', 100, 50)
        + make_inventory(9062, 50)
        + make_sale(90621, 9062, 100, qty=1, days_ago=2, user_id=user_id_good)
        + make_sale(90622, 9062, 100, qty=1, days_ago=3, user_id=user_id_good)
    )
    return [
        {
            'scenario_code': 'sdk_pos_0',
            'expected_topic': 'seller_discount_killer',
            'category': 'A', 'expected_should_appear': 1,
            'verification_type': 'seller_match',
            'verification_payload': {'user_id': user_id_bad},
            'scenario_description': 'user discounts heavily',
            'fixture_sql': sql_bad,
        },
        {
            'scenario_code': 'sdk_neg_0',
            'expected_topic': 'seller_discount_killer',
            'category': 'A', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'user_id': user_id_good},
            'scenario_description': 'user sells at retail',
            'fixture_sql': sql_good,
        },
        {
            'scenario_code': 'sdk_b_pattern',
            'expected_topic': 'seller_discount_killer',
            'category': 'B', 'expected_should_appear': 1,
            'verification_type': 'seller_match',
            'verification_payload': {'user_id': user_id_bad},
            'scenario_description': 'pattern over time',
            'fixture_sql': '',
        },
    ]


def top_profit_30d_scenarios() -> List[dict]:
    return [
        {
            'scenario_code': 'top_profit_b_top',
            'expected_topic': 'top_profit_30d',
            'category': 'B', 'expected_should_appear': 1,
            'verification_type': 'rank_within',
            'verification_payload': {'product_id': 9071, 'rank_max': 3},
            'scenario_description': 'top 3 profit',
            'fixture_sql': product_with_sales(9071, 'TEST-TP30-1', 'BigProfit', 200, 50,
                                               qty_in_stock=30, sale_count=15, days_ago_first=20),
        },
        {
            'scenario_code': 'top_profit_c_present',
            'expected_topic': 'top_profit_30d',
            'category': 'C', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9071},
            'scenario_description': 'present in list',
            'fixture_sql': '',
        },
        {
            'scenario_code': 'top_profit_d_31d_old',
            'expected_topic': 'top_profit_30d',
            'category': 'D', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9072},
            'scenario_description': 'sale 31d old (out of window)',
            # sale_id 90720 колидира с top_profit_b_top (90710..90724) → използваме 90729.
            'fixture_sql': (
                make_product(9072, 'TEST-TP30-2', 'TooOld', 200, 50)
                + make_inventory(9072, 30)
                + make_sale(90729, 9072, 200, qty=5, days_ago=31)
            ),
        },
    ]


def profit_growth_scenarios() -> List[dict]:
    sql_growing = make_product(9081, 'TEST-PG-1', 'Growing', 100, 50) + make_inventory(9081, 50)
    for i in range(5):
        sql_growing += make_sale(90810 + i, 9081, 100, qty=1, days_ago=45 - i)
    for i in range(15):
        sql_growing += make_sale(90830 + i, 9081, 100, qty=1, days_ago=20 - i if i < 20 else 1)
    return [
        {
            'scenario_code': 'profit_growth_b_pos',
            'expected_topic': 'profit_growth',
            'category': 'B', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9081},
            'scenario_description': '3x growth',
            'fixture_sql': sql_growing,
        },
        {
            'scenario_code': 'profit_growth_c_rank',
            'expected_topic': 'profit_growth',
            'category': 'C', 'expected_should_appear': 1,
            'verification_type': 'rank_within',
            'verification_payload': {'product_id': 9081, 'rank_max': 5},
            'scenario_description': 'top 5',
            'fixture_sql': '',
        },
        {
            'scenario_code': 'profit_growth_d_no_baseline',
            'expected_topic': 'profit_growth',
            'category': 'D', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9082},
            'scenario_description': 'no baseline',
            'fixture_sql': product_with_sales(9082, 'TEST-PG-2', 'NewProduct', 100, 50,
                                               qty_in_stock=20, sale_count=10, days_ago_first=20),
        },
    ]


def highest_margin_scenarios() -> List[dict]:
    return [
        {
            'scenario_code': 'highest_margin_b_pos',
            'expected_topic': 'highest_margin',
            'category': 'B', 'expected_should_appear': 1,
            'verification_type': 'rank_within',
            'verification_payload': {'product_id': 9091, 'rank_max': 3},
            'scenario_description': '400pct margin top 3',
            'fixture_sql': product_with_sales(9091, 'TEST-HM-1', 'PremiumMargin', 50, 10,
                                               qty_in_stock=20, sale_count=5),
        },
        {
            'scenario_code': 'highest_margin_c_present',
            'expected_topic': 'highest_margin',
            'category': 'C', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9091},
            'scenario_description': 'present',
            'fixture_sql': '',
        },
        {
            'scenario_code': 'highest_margin_d_no_sales',
            'expected_topic': 'highest_margin',
            'category': 'D', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9092},
            'scenario_description': 'high margin no sales',
            'fixture_sql': product_silent(9092, 'TEST-HM-2', 'HighMarginSilent', 100, 5,
                                           qty_in_stock=20),
        },
    ]


def trending_up_scenarios() -> List[dict]:
    # pfTrendingUp: avg_30d ≥ 0.5 AND avg_7d > avg_30d * 1.5.
    # 5 baseline sales (days 25..29) + 15 surge sales (всички в последните 7 дни)
    # → avg_30d = 20/30 ≈ 0.667; avg_7d = 15/7 ≈ 2.14 > 1.0. PASS.
    sql_trend = make_product(9101, 'TEST-TU-1', 'Trending', 80, 40) + make_inventory(9101, 50)
    base_id = 91010
    sid = base_id
    for d in range(25, 30):  # 5 baseline sales (days_ago 25..29)
        sql_trend += make_sale(sid, 9101, 80, qty=1, days_ago=d)
        sid += 1
    for i in range(15):  # 15 surge sales в последните 7 дни (2-3/ден)
        sql_trend += make_sale(sid, 9101, 80, qty=1, days_ago=(i % 7) + 1)
        sid += 1
    return [
        {
            'scenario_code': 'trending_up_b_pos',
            'expected_topic': 'trending_up',
            'category': 'B', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9101},
            'scenario_description': 'rising trend',
            'fixture_sql': sql_trend,
        },
        {
            'scenario_code': 'trending_up_c_rank',
            'expected_topic': 'trending_up',
            'category': 'C', 'expected_should_appear': 1,
            'verification_type': 'rank_within',
            'verification_payload': {'product_id': 9101, 'rank_max': 5},
            'scenario_description': 'top 5 trending',
            'fixture_sql': '',
        },
        {
            'scenario_code': 'trending_up_d_flat',
            'expected_topic': 'trending_up',
            'category': 'D', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9102},
            'scenario_description': 'flat trend',
            'fixture_sql': product_with_sales(9102, 'TEST-TU-2', 'Stable', 80, 40,
                                               qty_in_stock=50, sale_count=4),
        },
    ]


def loyal_customers_scenarios() -> List[dict]:
    cid = 7050
    sql_loyal = (
        make_customer(cid, 'LoyalTest')
        + make_product(9111, 'TEST-LC-1', 'LoyalProduct', 60, 30)
        + make_inventory(9111, 50)
        + make_sale(91110, 9111, 60, qty=1, days_ago=20, customer_id=cid)
        + make_sale(91111, 9111, 60, qty=1, days_ago=10, customer_id=cid)
        + make_sale(91112, 9111, 60, qty=1, days_ago=3, customer_id=cid)
    )
    return [
        {
            'scenario_code': 'loyal_b_pos',
            'expected_topic': 'loyal_customers',
            'category': 'B', 'expected_should_appear': 1,
            'verification_type': 'exists_only',
            'verification_payload': {},
            'scenario_description': 'customer 3+ buys',
            'fixture_sql': sql_loyal,
        },
        {
            'scenario_code': 'loyal_c_rank',
            'expected_topic': 'loyal_customers',
            'category': 'C', 'expected_should_appear': 1,
            'verification_type': 'exists_only',
            'verification_payload': {},
            'scenario_description': 'in list',
            'fixture_sql': '',
        },
        {
            'scenario_code': 'loyal_d_two_buys',
            'expected_topic': 'loyal_customers',
            'category': 'D', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'customer_id': 7051},
            'scenario_description': '2 buys not loyal',
            'fixture_sql': (
                make_customer(7051, 'AlmostLoyal')
                + make_product(9112, 'TEST-LC-2', 'LoyalProduct2', 60, 30)
                + make_inventory(9112, 50)
                + make_sale(91120, 9112, 60, days_ago=20, customer_id=7051)
                + make_sale(91121, 9112, 60, days_ago=5, customer_id=7051)
            ),
        },
    ]


def basket_driver_scenarios() -> List[dict]:
    sql_pair = (
        make_product(9121, 'TEST-BD-A', 'BasketA', 50, 20)
        + make_product(9122, 'TEST-BD-B', 'BasketB', 30, 15)
        + make_inventory(9121, 30) + make_inventory(9122, 30)
    )
    for i in range(5):
        sale_id = 91200 + i
        sql_pair += make_sale(sale_id, 9121, 50, qty=1, days_ago=10 - i)
        # Втори line item за същата продажба — sale_items.total NOT NULL без default,
        # затова го попълваме (30*1 = 30).
        sql_pair += (
            "INSERT INTO sale_items (sale_id, product_id, unit_price, quantity, total) "
            f"VALUES ({sale_id}, 9122, 30, 1, 30) "
            "ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), total=VALUES(total);\n"
        )
    return [
        {
            'scenario_code': 'basket_pair_b_pos',
            'expected_topic': 'basket_driver',
            'category': 'B', 'expected_should_appear': 1,
            'verification_type': 'pair_match',
            'verification_payload': {'a': 9121, 'b': 9122},
            'scenario_description': 'pair 5x together',
            'fixture_sql': sql_pair,
        },
        {
            'scenario_code': 'basket_pair_c_rank',
            'expected_topic': 'basket_driver',
            'category': 'C', 'expected_should_appear': 1,
            'verification_type': 'pair_match',
            'verification_payload': {'a': 9121, 'b': 9122},
            'scenario_description': 'pair in top',
            'fixture_sql': '',
        },
    ]


def size_leader_scenarios() -> List[dict]:
    # pfSizeLeader: JOIN parent.has_variations=1, child.parent_id=parent.id.
    parent_sql = make_parent_with_variations(9131, 'TEST-SL-PARENT', 'Sneaker', 100, 50)
    children_sql = ''
    children_sql += make_product_variant(9132, 9131, 'TEST-SL-S', 'SneakerS', 100, 50, size='S')
    children_sql += make_inventory(9132, 20)
    children_sql += make_product_variant(9133, 9131, 'TEST-SL-M', 'SneakerM', 100, 50, size='M')
    children_sql += make_inventory(9133, 20)
    children_sql += make_product_variant(9134, 9131, 'TEST-SL-L', 'SneakerL', 100, 50, size='L')
    children_sql += make_inventory(9134, 20)
    for i in range(8):
        children_sql += make_sale(91340 + i, 9133, 100, qty=1, days_ago=max(1, 10 - i))
    children_sql += make_sale(91350, 9132, 100, qty=1, days_ago=5)
    children_sql += make_sale(91351, 9134, 100, qty=1, days_ago=5)
    return [
        {
            'scenario_code': 'size_leader_b_pos',
            'expected_topic': 'size_leader',
            'category': 'B', 'expected_should_appear': 1,
            'verification_type': 'exists_only',
            'verification_payload': {},
            'scenario_description': 'M leader of 3 sizes',
            'fixture_sql': parent_sql + children_sql,
        },
        {
            'scenario_code': 'size_leader_d_only_one_size',
            'expected_topic': 'size_leader',
            'category': 'D', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9135},
            'scenario_description': 'only 1 size',
            'fixture_sql': product_with_sales(9135, 'TEST-SL-SOLO', 'SingleSize', 100, 50,
                                               qty_in_stock=10, sale_count=5),
        },
    ]


def bestseller_low_stock_scenarios() -> List[dict]:
    return [
        {
            'scenario_code': 'bestseller_low_pos_0',
            'expected_topic': 'bestseller_low_stock',
            'category': 'A', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9141},
            'scenario_description': '15 sales stock=2',
            'fixture_sql': product_with_sales(9141, 'TEST-BLS-1', 'HotLowStock', 80, 40,
                                               qty_in_stock=2, sale_count=15, days_ago_first=20),
        },
        {
            'scenario_code': 'bestseller_low_neg_high',
            'expected_topic': 'bestseller_low_stock',
            'category': 'A', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9142},
            'scenario_description': 'high stock',
            'fixture_sql': product_with_sales(9142, 'TEST-BLS-2', 'HotHighStock', 80, 40,
                                               qty_in_stock=100, sale_count=15),
        },
    ]


def lost_demand_match_scenarios() -> List[dict]:
    # pfLostDemandMatch: requires resolved_order_id NULL/0, matched_product_id NOT NULL,
    # last_asked_at >= NOW() - 14 DAY. Schema: query_text (NOT 'query'), store_id NOT NULL.
    sql_match = (
        make_product(9151, 'TEST-LDM-1', 'MatchedProduct', 70, 35)
        + make_inventory(9151, 0)
    )
    sql_match += (
        "INSERT IGNORE INTO lost_demand "
        "(tenant_id, store_id, query_text, source, matched_product_id, times, "
        " first_asked_at, last_asked_at, created_at) "
        "VALUES ({{tenant_id}}, 48, 'matched product', 'search', 9151, 3, "
        " NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 3 DAY);\n"
    )
    return [
        {
            'scenario_code': 'lost_demand_pos',
            'expected_topic': 'lost_demand_match',
            'category': 'A', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9151},
            'scenario_description': 'lost demand matches product',
            'fixture_sql': sql_match,
        },
    ]


def zombie_45d_scenarios() -> List[dict]:
    return [
        {
            'scenario_code': 'zombie_pos_0',
            'expected_topic': 'zombie_45d',
            'category': 'A', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9161},
            'scenario_description': 'last sale 60d ago',
            'fixture_sql': product_zombie(9161, 'TEST-Z45-1', 'OldZombie', 50, 25,
                                           qty_in_stock=10, days_silent=60),
        },
        {
            'scenario_code': 'zombie_neg_recent',
            'expected_topic': 'zombie_45d',
            'category': 'A', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9162},
            'scenario_description': 'recent sale',
            'fixture_sql': product_with_sales(9162, 'TEST-Z45-2', 'Active', 50, 25,
                                               qty_in_stock=10, sale_count=2, days_ago_first=10),
        },
        {
            'scenario_code': 'zombie_d_exact_45',
            'expected_topic': 'zombie_45d',
            'category': 'D', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9163},
            'scenario_description': 'exactly 45d boundary',
            'fixture_sql': product_zombie(9163, 'TEST-Z45-3', 'Border45', 50, 25,
                                           qty_in_stock=10, days_silent=45),
        },
        {
            'scenario_code': 'zombie_d_exact_46',
            'expected_topic': 'zombie_45d',
            'category': 'D', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9164},
            'scenario_description': '46d just over',
            'fixture_sql': product_zombie(9164, 'TEST-Z45-4', 'Just46', 50, 25,
                                           qty_in_stock=10, days_silent=46),
        },
    ]


def declining_trend_scenarios() -> List[dict]:
    # pfDecliningTrend: HAVING avg_30d ≥ 0.5 AND avg_7d < avg_30d * 0.5.
    # Trябва ≥15 продажби в дни 8..29 (за avg_30d≈0.5+), 0 в последните 7 → avg_7d=0.
    sql_decline = make_product(9171, 'TEST-DT-1', 'Declining', 80, 40) + make_inventory(9171, 30)
    sid = 91710
    for i in range(22):  # 22 продажби равномерно в дни 8..29
        days_ago = 8 + (i % 22)
        sql_decline += make_sale(sid, 9171, 80, qty=1, days_ago=days_ago)
        sid += 1
    return [
        {
            'scenario_code': 'declining_b_pos',
            'expected_topic': 'declining_trend',
            'category': 'B', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9171},
            'scenario_description': '15 old vs 2 recent',
            'fixture_sql': sql_decline,
        },
        {
            'scenario_code': 'declining_c_rank',
            'expected_topic': 'declining_trend',
            'category': 'C', 'expected_should_appear': 1,
            'verification_type': 'rank_within',
            'verification_payload': {'product_id': 9171, 'rank_max': 5},
            'scenario_description': 'top 5 declining',
            'fixture_sql': '',
        },
    ]


def high_return_rate_scenarios() -> List[dict]:
    sql_high = make_product(9181, 'TEST-HRR-1', 'HighReturnsItem', 80, 40) + make_inventory(9181, 30)
    for i in range(10):
        sale_id = 91810 + i
        sql_high += make_sale(sale_id, 9181, 80, qty=1, days_ago=20 - i)
        if i < 4:
            sql_high += make_return(91900 + i, sale_id, 9181, qty=1, days_ago=20 - i - 1)
    sql_low = make_product(9182, 'TEST-HRR-2', 'NormalReturnsItem', 80, 40) + make_inventory(9182, 30)
    for i in range(10):
        sale_id = 91820 + i
        sql_low += make_sale(sale_id, 9182, 80, qty=1, days_ago=20 - i)
        if i == 0:
            sql_low += make_return(91950, sale_id, 9182, qty=1, days_ago=15)
    # high_return_d_cartesian: pfHighReturnRate изисква sold ≥ 5 — затова 5 продажби
    # с 5 връщания → rate = 100% (Cartesian regression test).
    sql_cart = make_product(9183, 'TEST-HRR-CART', 'CartesianCheck', 100, 50) + make_inventory(9183, 30)
    for i in range(5):
        sale_id = 91830 + i
        sql_cart += make_sale(sale_id, 9183, 100, qty=1, days_ago=15 - i * 2)
        sql_cart += make_return(91960 + i, sale_id, 9183, qty=1, days_ago=14 - i * 2)
    return [
        {
            'scenario_code': 'high_return_pos_0',
            'expected_topic': 'high_return_rate',
            'category': 'A', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9181},
            'scenario_description': '40pct returns',
            'fixture_sql': sql_high,
        },
        {
            'scenario_code': 'high_return_neg_low',
            'expected_topic': 'high_return_rate',
            'category': 'A', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9182},
            'scenario_description': '10pct under threshold',
            'fixture_sql': sql_low,
        },
        {
            'scenario_code': 'high_return_d_cartesian',
            'expected_topic': 'high_return_rate',
            'category': 'D', 'expected_should_appear': 1,
            'verification_type': 'value_range',
            'verification_payload': {'field': 'rate', 'min': 99, 'max': 101},
            'scenario_description': 'Cartesian regression test c9a49f5',
            'fixture_sql': sql_cart,
        },
        {
            'scenario_code': 'high_return_d_zero_sales',
            'expected_topic': 'high_return_rate',
            'category': 'D', 'expected_should_appear': 0,
            'verification_type': 'not_exists',
            'verification_payload': {'product_id': 9184},
            'scenario_description': 'no sales no division by zero',
            'fixture_sql': product_silent(9184, 'TEST-HRR-EMPTY', 'NoSalesNoReturns', 100, 50,
                                           qty_in_stock=10),
        },
    ]


def all_scenarios() -> List[dict]:
    scenarios = []
    scenarios.extend(zero_stock_with_sales_scenarios())
    scenarios.extend(below_min_urgent_scenarios())
    scenarios.extend(running_out_today_scenarios())
    scenarios.extend(selling_at_loss_scenarios())
    scenarios.extend(no_cost_price_scenarios())
    scenarios.extend(margin_below_15_scenarios())
    scenarios.extend(seller_discount_killer_scenarios())
    scenarios.extend(top_profit_30d_scenarios())
    scenarios.extend(profit_growth_scenarios())
    scenarios.extend(highest_margin_scenarios())
    scenarios.extend(trending_up_scenarios())
    scenarios.extend(loyal_customers_scenarios())
    scenarios.extend(basket_driver_scenarios())
    scenarios.extend(size_leader_scenarios())
    scenarios.extend(bestseller_low_stock_scenarios())
    scenarios.extend(lost_demand_match_scenarios())
    scenarios.extend(zombie_45d_scenarios())
    scenarios.extend(declining_trend_scenarios())
    scenarios.extend(high_return_rate_scenarios())
    return scenarios


def stats() -> dict:
    s = all_scenarios()
    by_cat = {'A': 0, 'B': 0, 'C': 0, 'D': 0, 'E': 0}
    by_topic = {}
    for sc in s:
        by_cat[sc['category']] = by_cat.get(sc['category'], 0) + 1
        t = sc['expected_topic']
        by_topic[t] = by_topic.get(t, 0) + 1
    by_cat['E'] = len(cat_e_scenarios())
    return {
        'total': len(s) + by_cat['E'],
        'by_category': by_cat,
        'by_topic': by_topic,
        'topics_covered': len(by_topic),
    }


# ═══════════════════════════════════════════════════════════════════════════
# Cat E — Migration & ENUM regression (S88.DIAG.EXTEND)
# ═══════════════════════════════════════════════════════════════════════════
#
# Cat E проверки НЕ seed-ват fixtures и НЕ извикват compute-insights. Те ходят
# директно в DB и/или четат migration-файлове, за да гарантират:
#   * AIBRAIN_WIRE ENUM extend-а (commit 2a43852) е приложен и persistent;
#   * round-trip DOWN→UP е безопасен (rows recoverable от action_data.intent);
#   * data integrity: action_type NOT NULL, intent matches stem, action_label
#     populated за всички FQ.
#
# Контракт на check-функциите:
#   def _check_xxx(conn, tenant_id) -> tuple[str, str]
#     return ('PASS'|'FAIL', details_string)
#
# run_cat_e_scenarios(tenant_id) връща list of:
#   {'name': str, 'status': 'PASS'|'FAIL', 'details': str, 'description': str}

_NEW_ENUM_VALUES = ('navigate_chart', 'navigate_product', 'transfer_draft', 'dismiss')
_LEGACY_ENUM_VALUES = ('deeplink', 'order_draft', 'chat', 'none')
_AIBRAIN_MIGRATION_BASENAME = '20260428_002_ai_insights_action_type_extend'


def _check_enum_extension_persists(conn, tenant_id: int):
    """ENUM column actually contains all 4 new values след AIBRAIN_WIRE migration."""
    cur = conn.cursor()
    cur.execute("SHOW COLUMNS FROM ai_insights LIKE 'action_type'")
    row = cur.fetchone() or {}
    cur.close()
    type_str = (row.get('Type') or '').lower()
    missing = [v for v in _NEW_ENUM_VALUES if f"'{v}'" not in type_str]
    if missing:
        return ('FAIL', f"ENUM action_type missing values: {missing}; got: {type_str}")
    return ('PASS', f"ENUM contains all 4 new values: {', '.join(_NEW_ENUM_VALUES)}")


def _check_rollback_safety(conn, tenant_id: int):
    """
    DOWN migration safely revert-ва ENUM (без MySQL 1265 truncated):
      * up.sql — ALTER MODIFY включва всички 4 нови + NOT NULL DEFAULT 'none';
      * down.sql — има UPDATE-към-'none' стъпка ПРЕДИ ALTER (data normaliz.);
      * data — за tenant_id, всички rows с new-ENUM action_type имат
        action_data.intent (re-derivation source оцелява round-trip).
    """
    from pathlib import Path
    repo_root = Path(__file__).resolve().parents[4]  # .../runmystore
    up_path = repo_root / 'migrations' / f'{_AIBRAIN_MIGRATION_BASENAME}.up.sql'
    down_path = repo_root / 'migrations' / f'{_AIBRAIN_MIGRATION_BASENAME}.down.sql'

    if not up_path.is_file():
        return ('FAIL', f'up migration missing: {up_path}')
    if not down_path.is_file():
        return ('FAIL', f'down migration missing: {down_path}')

    up_sql = up_path.read_text(encoding='utf-8').lower()
    down_sql = down_path.read_text(encoding='utf-8').lower()

    for v in _NEW_ENUM_VALUES:
        if f"'{v}'" not in up_sql:
            return ('FAIL', f"up.sql липсва нова стойност '{v}'")
    if 'not null' not in up_sql or "default 'none'" not in up_sql:
        return ('FAIL', "up.sql не tighten-ва NOT NULL DEFAULT 'none'")

    update_pos = down_sql.find("update ai_insights set action_type='none'")
    alter_pos = down_sql.find('alter table ai_insights')
    if update_pos == -1:
        return ('FAIL', "down.sql липсва UPDATE към 'none' (предотвратява MySQL 1265)")
    if alter_pos == -1:
        return ('FAIL', "down.sql липсва ALTER TABLE")
    if update_pos > alter_pos:
        return ('FAIL', 'down.sql: UPDATE трябва да е ПРЕДИ ALTER, иначе data truncated')

    cur = conn.cursor()
    placeholders = ','.join(['%s'] * len(_NEW_ENUM_VALUES))
    cur.execute(
        f"""SELECT COUNT(*) AS total,
                  SUM(JSON_EXTRACT(action_data, '$.intent') IS NOT NULL) AS with_intent
              FROM ai_insights
             WHERE tenant_id=%s AND action_type IN ({placeholders})""",
        (tenant_id, *_NEW_ENUM_VALUES),
    )
    row = cur.fetchone() or {}
    cur.close()
    total = int(row.get('total') or 0)
    with_intent = int(row.get('with_intent') or 0)
    if total == 0:
        return ('PASS', f'no new-ENUM rows for tenant={tenant_id}; round-trip vacuously safe '
                        '(static migration check passed)')
    if with_intent < total:
        unrecoverable = total - with_intent
        return ('FAIL', f'tenant={tenant_id}: {unrecoverable}/{total} rows с new ENUM action_type '
                        'нямат action_data.intent — DOWN→UP round-trip ще ги загуби')
    return ('PASS', f'static migration files OK; tenant={tenant_id}: {total}/{total} rows '
                    'recoverable от action_data.intent')


def _check_action_type_not_null(conn, tenant_id: int):
    """ai_insights.action_type е NOT NULL за tenant_id (no NULL leakage)."""
    cur = conn.cursor()
    cur.execute(
        "SELECT COUNT(*) AS c FROM ai_insights WHERE tenant_id=%s AND action_type IS NULL",
        (tenant_id,),
    )
    row = cur.fetchone() or {}
    cur.close()
    null_count = int(row.get('c') or 0)
    if null_count > 0:
        return ('FAIL', f'tenant={tenant_id}: {null_count} rows с NULL action_type '
                        '(NOT NULL DEFAULT \'none\' invariant нарушен)')
    return ('PASS', f'tenant={tenant_id}: 0 NULL action_type rows')


def _check_action_data_intent_match(conn, tenant_id: int):
    """
    За всяка row с action_type IN (4 new ENUM values), action_data.intent ==
    action_type stem (semantic identity). Mismatch = pump/upsert bug.

    S88.AIBRAIN.ACTIONS whitelist: ENUM още няма 'promotion_draft'. За zombie/
    promo сценария pf*() слага action_type='dismiss' + intent='promotion_draft'
    (Option B: запазваме семантиката в action_data за S91/S92 consume,
    промо модулът още не е имплементиран). Това е легитимен override —
    не go считаме за mismatch.
    """
    semantic_overrides = {
        # action_type → set of allowed semantic intents
        'dismiss': {'dismiss', 'promotion_draft'},
    }
    cur = conn.cursor()
    placeholders = ','.join(['%s'] * len(_NEW_ENUM_VALUES))
    cur.execute(
        f"""SELECT id, action_type,
                   JSON_UNQUOTE(JSON_EXTRACT(action_data, '$.intent')) AS intent
              FROM ai_insights
             WHERE tenant_id=%s AND action_type IN ({placeholders})""",
        (tenant_id, *_NEW_ENUM_VALUES),
    )
    rows = cur.fetchall() or []
    cur.close()
    total = len(rows)
    def _is_match(r):
        atype = r.get('action_type') or ''
        intent = r.get('intent') or ''
        if intent == atype:
            return True
        return intent in semantic_overrides.get(atype, set())
    mismatches = [r for r in rows if not _is_match(r)]
    if total == 0:
        return ('PASS', f'tenant={tenant_id}: no rows с new-ENUM action_type (vacuously matched)')
    if mismatches:
        sample = mismatches[:3]
        sample_str = '; '.join(
            f"id={r.get('id')} type={r.get('action_type')} intent={r.get('intent')!r}"
            for r in sample
        )
        return ('FAIL', f'tenant={tenant_id}: {len(mismatches)}/{total} mismatches; sample: {sample_str}')
    return ('PASS', f'tenant={tenant_id}: {total}/{total} rows intent==action_type (incl. semantic overrides)')


def _check_q1_q6_action_label_populated(conn, tenant_id: int):
    """
    За всяка fundamental_question (q1..q6 = loss/loss_cause/gain/gain_cause/order/anti_order),
    COUNT(action_label IS NOT NULL) = COUNT(*). action_label е presentation
    contract към products.php loadSections.
    """
    cur = conn.cursor()
    cur.execute(
        """SELECT fundamental_question AS fq,
                  COUNT(*) AS total,
                  SUM(action_label IS NOT NULL AND action_label <> '') AS labeled
             FROM ai_insights
            WHERE tenant_id=%s AND module='products'
              AND (expires_at IS NULL OR expires_at > NOW())
            GROUP BY fundamental_question""",
        (tenant_id,),
    )
    rows = cur.fetchall() or []
    cur.close()
    if not rows:
        return ('PASS', f'tenant={tenant_id}: no live products insights (vacuously labeled)')
    gaps = [r for r in rows if int(r.get('labeled') or 0) < int(r.get('total') or 0)]
    if gaps:
        gap_str = ', '.join(
            f"{r.get('fq')}={r.get('labeled')}/{r.get('total')}" for r in gaps
        )
        return ('FAIL', f'tenant={tenant_id}: action_label липсва — {gap_str}')
    summary = ', '.join(f"{r.get('fq')}:{r.get('total')}" for r in rows)
    return ('PASS', f'tenant={tenant_id}: action_label populated за всички FQ ({summary})')


def cat_e_scenarios() -> List[dict]:
    """
    Cat E (Migration & ENUM regression). Each entry has 'check' callable, не fixture_sql.
    Не се вкарват в seed_oracle table — изпълняват се директно от run_cat_e_scenarios().
    """
    return [
        {
            'scenario_code': 'enum_extension_persists',
            'category': 'E',
            'description': 'ai_insights.action_type ENUM съдържа 4 нови стойности (AIBRAIN_WIRE)',
            'check': _check_enum_extension_persists,
        },
        {
            'scenario_code': 'rollback_safety',
            'category': 'E',
            'description': 'DOWN/UP migration round-trip е безопасен (UPDATE→none + intent re-derivation)',
            'check': _check_rollback_safety,
        },
        {
            'scenario_code': 'action_type_not_null',
            'category': 'E',
            'description': 'ai_insights.action_type няма NULL rows (NOT NULL invariant)',
            'check': _check_action_type_not_null,
        },
        {
            'scenario_code': 'action_data_intent_match',
            'category': 'E',
            'description': 'action_data.intent съвпада с action_type за rows с new-ENUM action_type',
            'check': _check_action_data_intent_match,
        },
        {
            'scenario_code': 'q1_q6_action_label_populated',
            'category': 'E',
            'description': 'action_label е populated за всички 6 fundamental_question values',
            'check': _check_q1_q6_action_label_populated,
        },
    ]


def run_cat_e_scenarios(tenant_id: int) -> List[dict]:
    """
    Orchestrate Cat E checks. Returns list of {name, status, details, description}.
    Connection-управлението е local: всеки run отваря+затваря една conn-ция.
    """
    import sys as _sys
    from pathlib import Path as _Path
    _diag_root = _Path(__file__).resolve().parents[2]
    if str(_diag_root) not in _sys.path:
        _sys.path.insert(0, str(_diag_root))
    from core.db_helpers import conn_ctx  # noqa: E402

    results: List[dict] = []
    with conn_ctx(autocommit=True) as conn:
        for sc in cat_e_scenarios():
            try:
                status, details = sc['check'](conn, tenant_id)
            except Exception as e:  # noqa: BLE001
                status, details = 'FAIL', f'exception: {type(e).__name__}: {e}'
            results.append({
                'name': sc['scenario_code'],
                'status': status,
                'details': details,
                'description': sc['description'],
            })
    return results
