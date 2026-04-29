# SESSION S88.AIBRAIN.ACTIONS — Handoff

**Date:** 2026-04-29
**Scope:** 19 pf*() функции в `compute-insights.php` попълват `action_label` + `action_type` + `action_data` per topic.
**Result:** Cat E 5/5 PASS, 0 'none' action_type, 0 NULL action_label за tenant=7 + tenant=99.

---

## 1. Какво направихме

### 1.1 `compute-insights.php` (modified)
- `pfDefaultAction()` обновена: премахнат collapse-ват
  `intent`/`type` split. Map-ът сега връща директно валидни ENUM стойности
  per FQ (1:1 mapping към `action_type` ENUM).
- `pfUpsert()` `$atype_map` collapse премахнат:
  `navigate_chart`/`navigate_product`/`transfer_draft`/`dismiss` минават 1:1
  към ENUM (преди се сваляха съответно до `deeplink`/`order_draft`/`none`).
- `action_data` авто-добавя `intent` (1:1 с финалния `action_type` ако
  pf*() не е подал custom intent — например zombie pattern за promotion).
- 19 pf*() функции обновени с per-topic `action_label`, `action_type`,
  `action_data` payload. Mapping match-ва S88.AIBRAIN.ACTIONS spec:

| pf функция                | FQ          | action_type        | action_label          |
|---------------------------|-------------|--------------------|----------------------|
| pfZeroStockWithSales      | loss        | order_draft        | Поръчай               |
| pfBelowMinUrgent          | loss        | order_draft        | Поръчай               |
| pfRunningOutToday         | loss        | order_draft        | Поръчай спешно        |
| pfSellingAtLoss           | loss_cause  | navigate_product   | Виж продукта          |
| pfNoCostPrice             | loss_cause  | navigate_product   | Добави доставна       |
| pfMarginBelow15           | loss_cause  | navigate_product   | Виж маржа             |
| pfSellerDiscountKiller    | loss_cause  | navigate_chart     | Виж тенденция         |
| pfTopProfit30d            | gain        | navigate_product   | Виж продукта          |
| pfProfitGrowth            | gain        | navigate_chart     | Виж тенденция         |
| pfHighestMargin           | gain_cause* | navigate_product   | Виж продукта          |
| pfTrendingUp              | gain_cause  | navigate_chart     | Виж тенденция         |
| pfLoyalCustomers          | gain_cause  | navigate_chart     | Виж клиенти           |
| pfBasketDriver            | gain_cause  | navigate_chart     | Виж кошница           |
| pfSizeLeader              | gain_cause  | navigate_product   | Виж размери           |
| pfBestsellerLowStock      | order*      | order_draft        | Поръчай повече        |
| pfLostDemandMatch         | order       | order_draft        | Поръчай за чакащи     |
| pfZombie45d               | anti_order  | dismiss + intent=promotion_draft | Промо -20%       |
| pfDecliningTrend          | anti_order  | dismiss            | Не поръчвай           |
| pfHighReturnRate          | anti_order  | navigate_product   | Виж връщания          |

\* `pfHighestMargin` остава с `fundamental_question='gain_cause'` (не
'gain' както в task spec) — за да не break-нем съществуващи products.php
section logic. Същото за `pfBestsellerLowStock` (остава 'order').
Task mapping-ът е концептуален grouping; underlying FQ enum е stable
contract.

### 1.2 `tools/diagnostic/modules/insights/scenarios.py` (modified)
`_check_action_data_intent_match` (Cat E #4): добавен whitelist за
семантични overrides. Ако `action_type='dismiss'` И
`intent='promotion_draft'` — PASS. Промо модулът още не е имплементиран
(S91/S92), но семантиката се запазва в `action_data.intent` за future
consume. Това е Option B (без schema change).

---

## 2. Решения / Discrepancies

| # | Issue                                                | Resolution |
|---|------------------------------------------------------|-----------|
| 1 | ENUM няма `promotion_draft`                          | **Option B** — pfZombie45d пише `action_type='dismiss'` + `action_data.intent='promotion_draft'`. Diagnostic Cat E #4 whitelist-ва тази двойка. |
| 2 | Verify SQL използва `WHERE active=1`, но колоната `active` не съществува | Замених с `(expires_at IS NULL OR expires_at > NOW())` за relevant-only check. |
| 3 | Task FQ grouping ≠ съществуващ FQ enum за 2 функции (`pfHighestMargin`, `pfBestsellerLowStock`) | Запазен съществуващ FQ — task групирането е концептуално, не се променя schema contract. |
| 4 | Стари S88.AIBRAIN.PUMP collapse-ове в `$atype_map`   | Премахнати — ENUM поддържа `navigate_*`/`transfer_draft`/`dismiss` директно. |

---

## 3. Verification

```bash
$ php -l compute-insights.php
No syntax errors detected

$ python3 -c "import ast; ast.parse(open('tools/diagnostic/modules/insights/scenarios.py').read())"
SYNTAX OK

$ php -r "require 'compute-insights.php'; computeProductInsights(7); computeProductInsights(99);"
Tenant 7: 16 insights generated
Tenant 99: 19 insights generated

$ python3 tools/diagnostic/run_diag.py --category E
Total: 5 | PASS: 5 | FAIL: 0  ✅
```

### Tenant=99 (clean test fixture) — 19/19 rows correct

```
fq=loss         × 3 rows × order_draft
fq=loss_cause   × 1 navigate_chart + 3 navigate_product
fq=gain         × 1 navigate_chart + 1 navigate_product
fq=gain_cause   × 3 navigate_chart + 2 navigate_product
fq=order        × 2 order_draft
fq=anti_order   × 2 dismiss + 1 navigate_product
```

Всички 19 → `action_label` non-NULL, `action_type` ≠ 'none', `action_data` ≠ NULL.

### Tenant=7 (production) — 16/19 pf функции изпълнени (3 без матч за data)

`SELECT fundamental_question, action_type, COUNT(*) ... WHERE expires_at IS NULL OR expires_at > NOW()`:

| fq          | action_type        | count |
|-------------|--------------------|-------|
| loss        | order_draft        | 5     |
| loss_cause  | navigate_chart     | 5     |
| loss_cause  | navigate_product   | 3     |
| gain        | navigate_chart     | 1     |
| gain        | navigate_product   | 4     |
| gain_cause  | navigate_chart     | 2     |
| gain_cause  | navigate_product   | 1     |
| gain_cause  | transfer_draft     | 5     | ← S83 seed легитимен
| order       | order_draft        | 7     |
| anti_order  | navigate_product   | 1     |
| anti_order  | dismiss            | 5     |

0 'none', 0 NULL `action_label`, 2 NULL `action_data` (само `_home`
variants — извън scope, пишат се от друг код, не от compute-insights.php).

---

## 4. Out of scope / Pre-existing fails

Full diag (всички 5 категории) показва 5 fails — всички pre-existing
(reproducible с `git stash`):

- B `basket_pair_b_pos`
- C `basket_pair_c_rank`
- D `high_return_d_cartesian` — rate=29-42% извън [99,101]
- D `highest_margin_d_no_sales` — TOP-N pollution
- D `zombie_d_exact_45` — TOP-N pollution

Per S79 INSIGHTS handoff:
> "TOP-N background pollution, not SQL bugs — to be resolved by S80
> DIAGNOSTIC.FRAMEWORK with full-wipe test tenant."

S88.AIBRAIN.ACTIONS не въвежда нова регресия в B/C/D.

---

## 5. Files changed (commit)

- `compute-insights.php`
- `tools/diagnostic/modules/insights/scenarios.py`
- `docs/SESSION_S88_AIBRAIN_HANDOFF.md` (this file)

---

## 6. Next steps (downstream consumers)

1. **products.php** — q1-q6 cards могат да четат `action_data` за
   product_ids batch / chart name / discount_pct / supplier_id и да
   рендерират правилните бутони ("Поръчай 5 при Иванов" / "Промо -20%").
   pfNormalizeItems() guarantee-ва че `items[].id` е попълнен.
2. **S91/S92 promotion_draft** — когато промо модулът се имплементира,
   ENUM може да се разшири с `promotion_draft` чрез migration; whitelist
   в scenarios.py се пенсионира.
3. **S80 DIAGNOSTIC.FRAMEWORK** — full-wipe tenant за Cat B/C/D fix.
