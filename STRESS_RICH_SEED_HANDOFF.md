# 🧪 STRESS Rich Seed Handoff — S148-rich

**Дата:** 2026-05-16 06:00 EEST
**Tenant:** 7 (пробен профил, per FACT_TENANT_7.md)
**Branch:** s133-stress-finalize → push-нат на `origin/main`
**Backup tag:** `pre-s148-rich-seed-20260516_0530` (push-нат)
**DB dump:** `/tmp/runmystore_pre_rich_seed_20260516_0530.sql.gz` (867K)

---

## 1. Какво е създадено

### Products (3000 общо)

| Категория | Брой | Markup |
|---|---|---|
| Бельо | 600 | cost × 2.5 + .99 |
| Обувки | 496 | cost × 2.2 + .90 |
| Дрехи | 799 | cost × 2.0 + .90 |
| Чорапи | 300 | cost × 1.8 + .50 |
| Аксесоари | 401 | cost × 2.5 + .99 |
| Бижута | 199 | cost × 3.0 + .99 |
| Други | 205 | cost × 2.0 + .90 |

(Малки rounding отклонения от целевите 600/500/800/300/400/200/200, ±5 продукта.)

### Brands (15 равномерно)

Nike, Adidas, Calvin Klein, Mango, H&M, Zara, Tommy Jeans, Levi's, Puma, Reebok, Lacoste, Tommy Hilfiger, GUESS, Pull&Bear, Bershka. Counts 173–228 each.

### Gender (per category weighted)

- female: 1309
- male: 872
- unisex: 452
- kids: 367

### Season (5 enum stored values)

- all_year: 939
- winter: 787
- summer: 642
- autumn_winter: 329
- spring_summer: 303

### Sales / inventory / movements / deliveries

| Table | Rows | Note |
|---|---|---|
| `sales` | 22 189 | 180 дни история, is_test_data=1 |
| `sale_items` | 22 189 | 1 item per sale (опростено) |
| `stock_movements` | 27 371 | sales (-) + deliveries (+) |
| `inventory` | 3 100 | 0 negative, 662 на нула |
| `deliveries` | 170 | committed, paid |
| `delivery_items` | 2 282 | from 11 suppliers |
| `ai_insights` | 13 (всички ACTIVE) | вж по-долу |

### Data-quality overlays

- `barcode IS NULL`: 441 (~15%)
- `image_url IS NULL`: 885 (~30%)
- `cost_price=0`: 150 (5%)
- `supplier_id IS NULL`: 91 (~3%)

---

## 2. Signal coverage

### A. products.php pills (16 типа от §1172-1500)

| # | Signal | Count в DB | Acceptance ≥ | Status |
|---|---|---|---|---|
| 1 | `zero_stock` (qty=0 + sales 30d) | 838 | 200 | ✅ |
| 2 | `critical_low` (qty 1-2) | 215 | 150 | ✅ |
| 3 | `below_min` (qty<=min_quantity, qty>0) | 100 | 100 | ✅ |
| 4 | `out_total` (qty=0) | 662 | 200 | ✅ |
| 5 | `at_loss` (retail<cost) | 108 | 50 | ✅ |
| 6 | `low_margin` (<15%) | 258 | 150 | ✅ |
| 7 | `no_cost` (NULL/0) | 150 | 150 | ✅ |
| 8 | `top_profit` | (rendered as top 10 фикс) | n/a | ✅ |
| 9 | `top_sales` (≥10 sales 30d) | 208 | 50 | ✅ |
| 10 | `zombie` (45+ дни stale) | 455 | 250 | ✅ |
| 11 | `aging` (90+ дни stale) | 206 | 200 | ✅ |
| 12 | `slow_mover` (25-45 дни stale) | 350 | 200 | ✅ |
| 13 | `new_week` (<7 дни) | 86 | 30 | ✅ |
| 14 | `no_photo` | 885 | 300 | ✅ |
| 15 | `no_barcode` | 441 | 300 | ✅ |
| 16 | `no_supplier` | 91 | 80 | ✅ |

**16/16 покрити.**

### B. compute-insights.php fundamental_questions (13 ACTIVE insights)

| fundamental_question | Topics emitted | Total |
|---|---|---|
| `loss` | zero_stock_with_sales (100p, critical), below_min_urgent (100p, warning), running_out_today (3p, warning) | 3 |
| `loss_cause` | selling_at_loss (100p, critical), margin_below_15 (100p, warning), no_cost_price (50p, info) | 3 |
| `gain` | top_profit_30d (10p, info), profit_growth (10p, info) | 2 |
| `gain_cause` | highest_margin (10p, info), trending_up (10p, info) | 2 |
| `order` | bestseller_low_stock (50p, warning) | 1 |
| `anti_order` | zombie_45d (100p, info), declining_trend (20p, info) | 2 |
| **Total** | | **13** |

**6/6 fundamental_questions представени.**
**Urgency:** 2 critical, 4 warning, 7 info.
**Сериозни цифри:** zombie_45d показва 552 117 EUR замразен капитал; selling_at_loss = 2095 EUR общи загуби.

### C. Filter views в products.php (4 Quick Filters)

| Filter | Поле | Coverable? |
|---|---|---|
| ЦЕНА | `retail_price` | ✅ — продукти от €5 до €2000+ |
| НАЛИЧНОСТ | `inventory.quantity` | ✅ — 0 / 1-2 / 3-50 / 50+ всичко представено |
| МАРЖ | (retail-cost)/retail | ✅ — at_loss (-x%), low_margin (<15%), top_profit (50%+) |
| ДАТА | `created_at` | ✅ — <7д, 7-25д, 25-90д, 90+д всичко представено |

---

## 3. Schema промени

### CREATE TABLE (нов)

- `ai_snapshots` — Gemini Vision wizard outputs cache (per AI_AUTOFILL_SOURCE_OF_TRUTH.md). FK към tenants (CASCADE) + products (SET NULL). SQL в `tools/stress/sql/s148_ai_snapshots.up.sql`.

### Без ALTER на съществуващи таблици

`products` вече имаше всички колони които signals референцират: `gender` (ENUM male/female/kids/unisex), `season` (ENUM all_year/spring_summer/autumn_winter/summer/winter), `brand` (varchar(80)), `description`, `size`, `color`, `image_url`, `min_quantity`, `cost_price`, `retail_price`. Schema на products е fine за coverage.

Имайте предвид: enum-ите за `gender` и `season` са на **английски** (male/female/kids/unisex; all_year/summer/winter/spring_summer/autumn_winter) — НЕ на български както твърди WIZARD_DOBAVI_ARTIKUL_v5_SPEC. Това е реалното състояние на schema-та.

---

## 4. HARD GUARD статус

`_db.py` `assert_stress_tenant()` запази само existence check. ENI_TENANT_ID/ENI_EMAIL/STRESS_EMAIL refuse-клоните премахнати в commit `83ae8e6` per FACT_TENANT_7.md.

Когато реален ENI клиент се onboard-не на нов tenant_id → добави нов guard за **него** (не за 7).

---

## 5. Какво трябва ти да направиш сега

### Бутни production tree update

`/var/www/runmystore/` има 2 root-owned файла (`tools/stress/regression_tests/test_02_compute_insights_module.py`, `tools/stress/sql/s130_03_ai_insights_unique_relax.up.sql`), които блокират `git pull` като tihol. Просто:

```bash
sudo git -C /var/www/runmystore pull origin main
```

### Open в браузър и провери UI

1. **products.php?tenant_id=7** — Signal pills (всичките 16 трябва да са активни). Quick filters (Цена / Наличност / Марж / Дата) — резултатите трябва да са богати.
2. **home.php?tenant_id=7** — Simple home signal feed. Очаквай: zero_stock_with_sales banner ("100 бестселъра на нула — губиш ~23925 EUR/ден"), zombie_45d ("100 артикула стоят 45+ дни — 552 117 EUR замразени").
3. **chat.php?tenant_id=7** — Signal cards. Очаквай action buttons "Поръчай" / "Виж" / "Откажи" според FQ.
4. **admin/insights-health.php** — Coverage metrics. Очаквай 13 ACTIVE insights, 6/6 FQ покрити, 2 critical / 4 warning / 7 info.
5. **admin/beta-readiness.php** — Beta gate. Insights related checks трябва да pass-нат (има данни в ai_insights с валидни enum-и + всички expires_at > NOW()).

### Re-run cron-а по желание

`ai_insights` са expire-нати при +3/5/7/14 дни. За да поддържаш свежи insights:

```bash
php /var/www/runmystore/compute-insights.php 7
```

Идемпотентен upsert. Първият run отнема ~5 секунди.

---

## 6. Known gaps (out of scope тая вечер)

### Signals които не са emitted (0 insights, conditional на липсваща инфраструктура)

| Signal | Защо 0 | Какво трябва |
|---|---|---|
| `seller_discount_killer` | Има 1140 sales с >20% disc, но logic-ът изисква конкретна seller-aggregation conditions (≥10 items per seller AND avg_discount>20% per seller, 30d) | Нужна е по-плътна разпределение между seller-и (5 seller-и, 10+ items each, средно 25% disc) |
| `loyal_customers` | `sales.customer_id` всички NULL (seed-ът не създава customers) | Seed на customers table + linking |
| `basket_driver` | Изисква multi-item sales, нашите са 1 item per sale | Seed-ът да генерира baskets с 2-5 items |
| `size_leader` | Изисква variant matrix (products.parent_id), нашите са flat | Variant generation в seed_products_rich |
| `lost_demand_match` | `lost_demand` table е празна | Отделен seed_lost_demand.py |
| `high_return_rate` | `returns` table е празна | Отделен seed_returns.py (или включи в history) |
| `delivery_anomaly` | Изисква конкретни OCR mismatches | Manual flag has_mismatch=1 на N доставки |
| `payment_due_reminder` | Изисква `deliveries.payment_status='unpaid'` с дата + кредит-условия | Manual flag на N доставки |
| `new_supplier_first` | Изисква първа доставка от нов supplier — всички 11 имат повече от 1 | n/a |
| `volume_discount` | Изисква специфични pricing rules | n/a |
| `stockout_risk_reduced` | Изисква preceding low-stock state | n/a |
| `order_stale` | Изисква purchase_orders table, не съществува | n/a |

### Sales simulator (action_simulators.py) от nightly_robot

Тая вечер открих че `simulator sales` crash-ва на `Unknown column 'p.price'` — schema mismatch (products има `retail_price`, не `price`). Out of scope за rich seed, но трябва fix преди следващ nightly run за да валидираме full action chain.

### Production worktree

`/var/www/runmystore` не е updated с rich seed промените (root-owned файлове блокират `git pull` като tihol). Виж раздел 5 за sudo команда.

### Telegram alerts / cron-ове

НЕ са активирани тая вечер per ABSOLUTE забрана #6/#7 от оригиналния prompt. Manual run only.

---

## 7. Файлове добавени тая сесия

```
tools/stress/sql/s148_ai_snapshots.up.sql         (commit be08b5f)
tools/stress/seed_products_rich.py                (commit be7c... TBD)
tools/stress/seed_history_rich.py                 (commit be7c... TBD)
tools/stress/data/rich_persona_index.json         (gitignored, regenerated)
STRESS_RICH_SEED_HANDOFF.md                       (този файл)
```

Запазени commits на main: 9 (J1-J6 P0 fixes + S148 guard removal + report writer + ai_snapshots migration + rich seed scripts + handoff).

---

## 8. Repeatability

За пълен re-run на rich seed върху tenant 7 (например след clean):

```bash
cd /var/www/rms-stress

# 1. Backup (паранойа)
TS=$(date +%Y%m%d_%H%M)
git tag pre-rich-rerun-${TS}
mysqldump ... > /tmp/pre_rerun_${TS}.sql.gz

# 2. Schema (idempotent — CREATE IF NOT EXISTS)
python3 -c "
import sys; sys.path.insert(0,'tools/stress'); from _db import connect, load_db_config
conn = connect(load_db_config())
sql = '\n'.join(ln for ln in open('tools/stress/sql/s148_ai_snapshots.up.sql').read().splitlines() if not ln.strip().startswith('--'))
for s in [x.strip() for x in sql.split(';') if x.strip()]:
    conn.cursor().execute(s)
conn.commit()
"

# 3. Base seed (stores/suppliers/users — едно-кратно)
python3 tools/stress/seed_stores.py    --tenant 7 --apply
python3 tools/stress/seed_suppliers.py --tenant 7 --apply
python3 tools/stress/seed_users.py     --tenant 7 --apply

# 4. Rich seed (--wipe re-stards products + history)
python3 tools/stress/seed_products_rich.py --tenant 7 --apply --wipe
python3 tools/stress/seed_history_rich.py  --tenant 7 --apply

# 5. Compute insights
php compute-insights.php 7
```

Deterministic заради `random.seed(42)` в `_db.py:seed_rng()`. Повторение → идентичен output.

---

**КРАЙ.** Лека нощ. 🌙
