"""
oracle_rules.py — mapping pf*() function name → expected_topic + default category.

Базиран на реалния compute-insights.php (1236 реда, 19 pf*() insight functions).
Ползва се от:
  - gap_detector.py (вижда coverage gaps)
  - oracle_populate.py (assign експектация при bulk insert)
  - report_writer.py (human-readable названия)
"""

# 19-те реални pf*() функции от compute-insights.php
# (helper функции pfDB/pfTableExists/etc. са изключени — те не са insight producers)

PF_FUNCTION_TO_TOPIC = {
    # LOSS (Cat A — критични)
    'pfZeroStockWithSales':    'zero_stock_with_sales',
    'pfBelowMinUrgent':        'below_min_urgent',
    'pfRunningOutToday':       'running_out_today',

    # LOSS_CAUSE (Cat A — критични)
    'pfSellingAtLoss':         'selling_at_loss',
    'pfNoCostPrice':           'no_cost_price',
    'pfMarginBelow15':         'margin_below_15',
    'pfSellerDiscountKiller':  'seller_discount_killer',

    # GAIN (Cat B — важни)
    'pfTopProfit30d':          'top_profit_30d',
    'pfProfitGrowth':          'profit_growth',

    # GAIN_CAUSE (Cat B/C — важни/декорация)
    'pfHighestMargin':         'highest_margin',
    'pfTrendingUp':            'trending_up',
    'pfLoyalCustomers':        'loyal_customers',
    'pfBasketDriver':          'basket_driver',
    'pfSizeLeader':            'size_leader',

    # ORDER (Cat A — критични)
    'pfBestsellerLowStock':    'bestseller_low_stock',
    'pfLostDemandMatch':       'lost_demand_match',

    # ANTI_ORDER (Cat A/B — критични/важни)
    'pfZombie45d':             'zombie_45d',
    'pfDecliningTrend':        'declining_trend',
    'pfHighReturnRate':        'high_return_rate',  # Cartesian fix c9a49f5
}


# Default category за всеки топик (когато сценарий няма explicit override).
# Използва се ако oracle row липсва category и backfill е нужен.
DEFAULT_CATEGORY = {
    'zero_stock_with_sales':   'A',
    'below_min_urgent':        'A',
    'running_out_today':       'A',
    'selling_at_loss':         'A',
    'no_cost_price':           'A',
    'margin_below_15':         'A',
    'seller_discount_killer':  'A',
    'top_profit_30d':          'B',
    'profit_growth':           'B',
    'highest_margin':          'B',
    'trending_up':             'B',
    'loyal_customers':         'B',
    'basket_driver':           'B',
    'size_leader':             'B',
    'bestseller_low_stock':    'A',
    'lost_demand_match':       'A',
    'zombie_45d':              'A',
    'declining_trend':         'B',
    'high_return_rate':        'A',
}


# Fundamental question (BIBLE §6 — 6-те въпроса)
TOPIC_TO_FUNDAMENTAL = {
    'zero_stock_with_sales':   'loss',
    'below_min_urgent':        'loss',
    'running_out_today':       'loss',
    'selling_at_loss':         'loss_cause',
    'no_cost_price':           'loss_cause',
    'margin_below_15':         'loss_cause',
    'seller_discount_killer':  'loss_cause',
    'top_profit_30d':          'gain',
    'profit_growth':           'gain',
    'highest_margin':          'gain_cause',
    'trending_up':             'gain_cause',
    'loyal_customers':         'gain_cause',
    'basket_driver':           'gain_cause',
    'size_leader':             'gain_cause',
    'bestseller_low_stock':    'order',
    'lost_demand_match':       'order',
    'zombie_45d':              'anti_order',
    'declining_trend':         'anti_order',
    'high_return_rate':        'anti_order',
}


# Human-readable label (за email/Telegram/dashboard)
TOPIC_LABEL_BG = {
    'zero_stock_with_sales':   'Нулева наличност с продажби',
    'below_min_urgent':        'Под минимума спешно',
    'running_out_today':       'Свършва днес',
    'selling_at_loss':         'Продава се на загуба',
    'no_cost_price':           'Без доставна цена',
    'margin_below_15':         'Марж под 15%',
    'seller_discount_killer':  'Продавач с убийствени отстъпки',
    'top_profit_30d':          'Топ печалба 30 дни',
    'profit_growth':           'Растяща печалба',
    'highest_margin':          'Най-висок марж',
    'trending_up':             'Тренд нагоре',
    'loyal_customers':         'Лоялни клиенти',
    'basket_driver':           'Двойка в кошницата',
    'size_leader':             'Лидер по размер',
    'bestseller_low_stock':    'Хит с малко наличност',
    'lost_demand_match':       'Изгубено търсене (мач)',
    'zombie_45d':              'Зомби 45+ дни',
    'declining_trend':         'Намаляващ тренд',
    'high_return_rate':        'Висок процент върнати',
}


def all_topics() -> list:
    return list(PF_FUNCTION_TO_TOPIC.values())


def all_pf_functions() -> list:
    return list(PF_FUNCTION_TO_TOPIC.keys())


def topic_for_function(pf_name: str) -> str:
    return PF_FUNCTION_TO_TOPIC.get(pf_name, '')


def category_for_topic(topic: str) -> str:
    return DEFAULT_CATEGORY.get(topic, 'B')


def fundamental_for_topic(topic: str) -> str:
    return TOPIC_TO_FUNDAMENTAL.get(topic, '')


def label_for_topic(topic: str) -> str:
    return TOPIC_LABEL_BG.get(topic, topic)


# S81 alias — oracle_populate.py търси това име
topic_to_category = category_for_topic
