"""
verify_engine.py — 8 типа проверки от DIAGNOSTIC_PROTOCOL.md §"ВИДОВЕ ПРОВЕРКИ"

Всеки handler приема:
    actual_data: dict — JSON payload от ai_insights.data_json (decoded)
    payload:     dict — verification_payload от seed_oracle (decoded)
    expected_should_appear: int — 1 (трябва) или 0 (не трябва)
Връща:
    (passed: bool, reason: str)
"""

from typing import Tuple


def verify_product_in_items(actual_data, payload, expected_should_appear) -> Tuple[bool, str]:
    """
    Проверка: конкретен product_id трябва (1) или не трябва (0) да е в items[].
    Optionally: rank_max — продуктът трябва да е в top-N.
    """
    items = (actual_data or {}).get('items', []) or []
    pid = int(payload.get('product_id', 0))
    rank_max = payload.get('rank_max')

    found_idx = -1
    for i, it in enumerate(items):
        if int(it.get('product_id', 0)) == pid:
            found_idx = i
            break

    if expected_should_appear == 1:
        if found_idx < 0:
            return False, f"product_id={pid} не е в items (брой items={len(items)})"
        if rank_max is not None and found_idx >= int(rank_max):
            return False, f"product_id={pid} е на ранк {found_idx+1}, трябва ≤{rank_max}"
        return True, f"product_id={pid} намерен на ранк {found_idx+1}"
    else:
        if found_idx >= 0:
            return False, f"product_id={pid} НЕ трябваше да се появи, но е на ранк {found_idx+1}"
        return True, f"product_id={pid} коректно отсъства"


def verify_pair_match(actual_data, payload, expected_should_appear) -> Tuple[bool, str]:
    """
    Проверка: двойка продукти заедно (basket_driver).
    payload: {a: <pid>, b: <pid>}.
    Поддържаме две shapes:
      • items с явни pair полета: product_a_id/product_b_id или a/b
      • PHP pfBasketDriver текущо emit-ва индивидуални продукти — приемаме pair-а
        за намерен, ако и двата pid-а са в items[*].product_id.
    """
    items = (actual_data or {}).get('items', []) or []
    a = int(payload.get('a', 0))
    b = int(payload.get('b', 0))

    # Shape 1: explicit pair fields
    found = False
    for it in items:
        pa = int(it.get('product_a_id', it.get('a', 0)))
        pb = int(it.get('product_b_id', it.get('b', 0)))
        if pa and pb and ((pa, pb) == (a, b) or (pa, pb) == (b, a)):
            found = True
            break

    # Shape 2: individual products (basket_driver flat list)
    if not found:
        flat_pids = {int(it.get('product_id', 0)) for it in items}
        if a in flat_pids and b in flat_pids:
            found = True

    if expected_should_appear == 1:
        return (found, f"pair ({a},{b}) {'намерена' if found else 'липсва'}")
    return (not found, f"pair ({a},{b}) {'грешно появила се' if found else 'коректно отсъства'}")


def verify_seller_match(actual_data, payload, expected_should_appear) -> Tuple[bool, str]:
    """payload: {user_id: <int>}"""
    items = (actual_data or {}).get('items', []) or []
    uid = int(payload.get('user_id', 0))
    found = any(int(it.get('user_id', 0)) == uid for it in items)
    if expected_should_appear == 1:
        return (found, f"seller user_id={uid} {'flagged' if found else 'не е flagged'}")
    return (not found, f"seller user_id={uid} {'грешно flagged' if found else 'коректно не е flagged'}")


def verify_value_range(actual_data, payload, expected_should_appear) -> Tuple[bool, str]:
    """payload: {field: 'profit_growth_pct', min: 50, max: 150, [product_id: N]}.
    Ако payload има product_id — стойността се чете от item-а на този product.
    Иначе fallback към items[0]."""
    field = payload.get('field')
    vmin = payload.get('min')
    vmax = payload.get('max')
    pid = payload.get('product_id')
    items = (actual_data or {}).get('items', []) or []
    if not items:
        if expected_should_appear == 0:
            return True, "няма items (коректно отсъства)"
        return False, f"очаквах {field} в [{vmin},{vmax}], но items е празен"

    target = None
    if pid is not None:
        for it in items:
            if int(it.get('product_id', 0)) == int(pid):
                target = it
                break
        if target is None:
            if expected_should_appear == 0:
                return True, f"product_id={pid} коректно отсъства"
            return False, f"product_id={pid} не е в items (брой items={len(items)})"
    else:
        target = items[0]

    val = target.get(field) if field else None
    if val is None:
        return False, f"field={field} липсва в item"
    val = float(val)
    in_range = (vmin is None or val >= vmin) and (vmax is None or val <= vmax)
    if expected_should_appear == 1:
        return (in_range, f"{field}={val} {'в' if in_range else 'извън'} [{vmin},{vmax}]")
    return (not in_range, f"{field}={val} {'грешно в' if in_range else 'коректно извън'} range")


def verify_exists_only(actual_data, payload, expected_should_appear) -> Tuple[bool, str]:
    """Проверка: insight row съществува (не гледаме съдържание)."""
    has_data = bool(actual_data) and bool((actual_data or {}).get('items'))
    if expected_should_appear == 1:
        return (has_data, f"insight {'съществува' if has_data else 'липсва'}")
    return (not has_data, f"insight {'грешно съществува' if has_data else 'коректно липсва'}")


def verify_not_exists(actual_data, payload, expected_should_appear) -> Tuple[bool, str]:
    """Отрицателен случай — конкретната същност НЕ трябва да участва в insight-а.

    Тъй като compute-insights.php прави UPSERT с ключ (tenant_id, store_id, topic_id),
    в данните на топика обикновено вече има позиции от позитивни сценарии. Затова
    проверката е entity-aware: ако payload съдържа product_id / user_id / pair / customer_id,
    проверяваме отсъствието на тази същност, не отсъствието на самия insight.

    Forecastvame multiple item shapes — pfSizeLeader пише `parent_id`/`child_id`
    вместо `product_id`, pfBasketDriver пише `product_a_id`/`product_b_id` и т.н."""
    items = (actual_data or {}).get('items', []) or []

    if payload.get('product_id') is not None:
        pid = int(payload['product_id'])
        keys = ('product_id', 'parent_id', 'child_id', 'product_a_id', 'product_b_id')
        for it in items:
            for k in keys:
                if k in it and int(it.get(k) or 0) == pid:
                    return (False,
                            f"product_id={pid} НЕ трябваше да се появи (намерен в поле {k})")
        return (True, f"product_id={pid} коректно отсъства")
    if payload.get('user_id') is not None:
        return verify_seller_match(actual_data, payload, 0)
    if payload.get('a') is not None and payload.get('b') is not None:
        return verify_pair_match(actual_data, payload, 0)
    if payload.get('customer_id') is not None:
        cid = int(payload['customer_id'])
        found = any(int(it.get('customer_id', 0)) == cid for it in items)
        return (not found, f"customer_id={cid} {'грешно flagged' if found else 'коректно не е flagged'}")

    has_data = bool(actual_data) and bool(items)
    return (not has_data, f"insight {'грешно съществува' if has_data else 'коректно липсва'}")


def verify_count_match(actual_data, payload, expected_should_appear) -> Tuple[bool, str]:
    """payload: {count: 3} — точен брой items."""
    expected_count = int(payload.get('count', 0))
    items = (actual_data or {}).get('items', []) or []
    actual_count = len(items)
    matches = (actual_count == expected_count)
    if expected_should_appear == 1:
        return (matches, f"count={actual_count}, очаквано={expected_count}")
    return (not matches, f"count={actual_count} {'грешно равен на' if matches else 'различен от'} {expected_count}")


def verify_rank_within(actual_data, payload, expected_should_appear) -> Tuple[bool, str]:
    """Wrapper around verify_product_in_items когато rank_max е задължителен."""
    return verify_product_in_items(actual_data, payload, expected_should_appear)


# ═══════════════════════════════════════════════════════════════
# DISPATCHER
# ═══════════════════════════════════════════════════════════════

VERIFICATION_HANDLERS = {
    'product_in_items': verify_product_in_items,
    'rank_within':       verify_rank_within,
    'pair_match':        verify_pair_match,
    'seller_match':      verify_seller_match,
    'value_range':       verify_value_range,
    'exists_only':       verify_exists_only,
    'not_exists':        verify_not_exists,
    'count_match':       verify_count_match,
}


def verify(verification_type: str, actual_data, payload, expected_should_appear) -> Tuple[bool, str]:
    """Главен dispatcher — извиква правилния handler по verification_type."""
    handler = VERIFICATION_HANDLERS.get(verification_type)
    if not handler:
        return False, f"UNKNOWN verification_type='{verification_type}'"
    try:
        return handler(actual_data, payload or {}, expected_should_appear)
    except Exception as e:
        return False, f"handler exception: {type(e).__name__}: {e}"
