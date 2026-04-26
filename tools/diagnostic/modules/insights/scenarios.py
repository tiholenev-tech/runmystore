"""
scenarios.py — 50+ test scenarios за 19-те pf*() функции.
Категории: A (критични) / B (важни) / C (декорация) / D (граници).
"""

from typing import List
from .fixtures import (
    make_product, make_inventory, make_sale, make_return, make_customer,
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
    return [
        {
            'scenario_code': 'running_out_pos_0',
            'expected_topic': 'running_out_today',
            'category': 'A', 'expected_should_appear': 1,
            'verification_type': 'product_in_items',
            'verification_payload': {'product_id': 9021},
            'scenario_description': 'Stock=2 high velocity',
            'fixture_sql': product_with_sales(9021, 'TEST-ROT-1', 'FastRunner', 40, 20,
                                               qty_in_stock=2, sale_count=7, days_ago_first=7),
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
            'scenario_description': 'exactly 15pct boundary',
            'fixture_sql': product_with_sales(9053, 'TEST-MB15-3', 'BorderMargin', 11.5, 10,
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
    user_id_bad = 8050
    user_id_good = 8051
    sql_bad = (
        make_product(9061, 'TEST-SDK-1', 'DiscountedItem', 100, 50)
        + make_inventory(9061, 50)
        + make_sale(90611, 9061, 60, qty=1, days_ago=2, user_id=user_id_bad)
        + make_sale(90612, 9061, 60, qty=1, days_ago=3, user_id=user_id_bad)
        + make_sale(90613, 9061, 60, qty=1, days_ago=4, user_id=user_id_bad)
    )
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
            'fixture_sql': (
                make_product(9072, 'TEST-TP30-2', 'TooOld', 200, 50)
                + make_inventory(9072, 30)
                + make_sale(90720, 9072, 200, qty=5, days_ago=31)
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
    sql_trend = make_product(9101, 'TEST-TU-1', 'Trending', 80, 40) + make_inventory(9101, 50)
    for i in range(20):
        sql_trend += make_sale(91010 + i, 9101, 80, qty=1, days_ago=20 - i)
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
            'verification_payload': {},
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
        sql_pair += "INSERT INTO sale_items (sale_id, product_id, unit_price, quantity) VALUES (" + str(sale_id) + ", 9122, 30, 1) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity);\n"
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
    parent_sql = make_product(9131, 'TEST-SL-PARENT', 'Sneaker', 100, 50) + make_inventory(9131, 0)
    children_sql = ''
    children_sql += make_product(9132, 'TEST-SL-S', 'SneakerS', 100, 50) + make_inventory(9132, 20)
    children_sql += make_product(9133, 'TEST-SL-M', 'SneakerM', 100, 50) + make_inventory(9133, 20)
    children_sql += make_product(9134, 'TEST-SL-L', 'SneakerL', 100, 50) + make_inventory(9134, 20)
    for i in range(8):
        children_sql += make_sale(91340 + i, 9133, 100, qty=1, days_ago=10 - i if i < 10 else 1)
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
            'verification_payload': {},
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
    sql_match = (
        make_product(9151, 'TEST-LDM-1', 'MatchedProduct', 70, 35)
        + make_inventory(9151, 0)
    )
    sql_match += "INSERT IGNORE INTO lost_demand (tenant_id, query, matched_product_id, times, created_at) VALUES ({{tenant_id}}, 'matched product', 9151, 3, NOW() - INTERVAL 3 DAY);\n"
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
    sql_decline = make_product(9171, 'TEST-DT-1', 'Declining', 80, 40) + make_inventory(9171, 30)
    for i in range(15):
        sql_decline += make_sale(91710 + i, 9171, 80, qty=1, days_ago=55 - i * 2)
    sql_decline += make_sale(91730, 9171, 80, qty=1, days_ago=20)
    sql_decline += make_sale(91731, 9171, 80, qty=1, days_ago=8)
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
    sql_cart = make_product(9183, 'TEST-HRR-CART', 'CartesianCheck', 100, 50) + make_inventory(9183, 30)
    sale_id = 91830
    sql_cart += make_sale(sale_id, 9183, 100, qty=1, days_ago=10)
    sql_cart += make_return(91960, sale_id, 9183, qty=1, days_ago=9)
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
            'verification_payload': {'field': 'return_rate_pct', 'min': 99, 'max': 101},
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
    by_cat = {'A': 0, 'B': 0, 'C': 0, 'D': 0}
    by_topic = {}
    for sc in s:
        by_cat[sc['category']] = by_cat.get(sc['category'], 0) + 1
        t = sc['expected_topic']
        by_topic[t] = by_topic.get(t, 0) + 1
    return {
        'total': len(s),
        'by_category': by_cat,
        'by_topic': by_topic,
        'topics_covered': len(by_topic),
    }
