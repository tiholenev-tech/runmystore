# sales_populate.py — Sales Seeder for tenant=99

Generates realistic historical sales for tenant=99 so that
`compute-insights.php` produces non-trivial organic insights against an
isolated test tenant. Designed to be called by `tools/testing_loop/`
once per day, but also runnable on-demand for one-off backfills.

## Quick start

```bash
# 15 sales right now (last 30 minutes window)
python3 tools/seed/sales_populate.py --tenant 99 --count 15

# Backfill 60 days at default density (~8 sales/day, weekend-boosted)
python3 tools/seed/sales_populate.py --tenant 99 --backfill-days 60

# Backfill 30 days with explicit total
python3 tools/seed/sales_populate.py --tenant 99 --backfill-days 30 --count 240

# Dry-run (plan only, no writes)
python3 tools/seed/sales_populate.py --tenant 99 --count 15 --dry-run

# Reproducible run via fixed RNG seed
python3 tools/seed/sales_populate.py --tenant 99 --count 15 --seed 42
```

## Realistic patterns

| Pattern | Value |
| --- | --- |
| Peak hours | 11-13 + 17-19 (60% of sales) |
| Off-peak hours | 09-10, 14-16, 20-21 (40%) |
| Weekend boost | Sat/Sun = 1.5× weekday weight |
| Avg basket | ~1.88 items (1=45%, 2=32%, 3=15%, 4=6%, 5=2%) |
| Item qty per line | 1=85%, 2=12%, 3=3% |
| Discount mix | 70% none / 25% 5-15% / 5% 30-50% |
| Return rate | 5% (status='returned', no inventory decrement) |
| Customer mix | 30% repeat (existing customer) / 70% walk-in (NULL) |
| User mix | 80% sellers / 20% owner |
| Payment mix | cash 60% / card 35% / bank_transfer 4% / deferred 1% |

## Safety

- `ALLOWED_TENANTS = {7, 99}` — anything else → exit 2.
- Default `--tenant 99` (so accidental run never targets live).
- `--confirm` flag required to seed tenant=7.
- All writes wrapped in a single transaction; rollback on any error.
- Dry-run path issues `INSERT` 0 times and finishes with `rollback()`.

## Idempotency

Every seeded `sales` row has `note = '[seed-s87]'`. Re-runs append more
seeded rows (the seeder is additive, not replacing). Use this marker
to locate or clean seed data:

```sql
SELECT id, created_at, total, status FROM sales
WHERE tenant_id = 99 AND note = '[seed-s87]'
ORDER BY created_at DESC LIMIT 50;

-- destructive cleanup (CASCADE drops sale_items):
DELETE FROM sales WHERE tenant_id = 99 AND note = '[seed-s87]';
-- ⚠ does NOT restore inventory; run a manual top-up if needed.
```

## What the script touches

| Table | Operation |
| --- | --- |
| `sales` | INSERT one row per seeded sale (`status` = `completed` or `returned`) |
| `sale_items` | INSERT 1-5 rows per sale |
| `inventory` | UPDATE `quantity = quantity - sold_qty` (only for non-returned sales) |

The script does **not** touch products, customers, users, stores, or any
other table. Inventory updates are scoped to the rows hit by sale items
(by `inventory.id`).

## Exit codes

| Code | Meaning |
| --- | --- |
| 0 | Seeded all planned sales |
| 1 | Partial — fewer sales seeded than planned (insufficient inventory) |
| 2 | Refused or fatal (bad tenant, missing `--confirm`, no products, no users, DB error) |

## CLI reference

| Flag | Description |
| --- | --- |
| `--tenant N` | Tenant id. Allowed: 7 or 99. Default: 99. |
| `--count N` | Sales to seed. With `--backfill-days`, this is the total spread across the window. Without it, sales cluster within ±30 minutes of now. |
| `--backfill-days D` | Distribute sales across the past D days (weekend-boosted). Default total = `D × 8`. |
| `--dry-run` | Plan only, no INSERT/UPDATE. |
| `--confirm` | Required to seed tenant=7 (live tenant). |
| `--seed N` | Reproducible RNG seed (otherwise system-random). |

## Companion / consumers

- `tools/testing_loop/daily_runner.py` calls this script with
  `--tenant 99 --count 15` once per day before snapshotting insights.
- Insights Oracle (`tools/diagnostic/`) reads tenant=99 data — keep
  this seeder's behavior aligned with diag fixtures so synthetic +
  oracle paths stay coherent.

## Limitations / known caveats

- Returns model: a returned sale records line items but does **not**
  restock inventory. Net effect on stock = 0 vs. a never-sold scenario.
- New "walk-in" customers use `customer_id = NULL`. The 3 existing
  customers on tenant=99 carry the entire repeat-customer signal.
- Live mode (`--count` only) clusters timestamps in the last 30 min;
  outside business hours the timestamps will reflect server clock,
  not synthesized peak hours. Use `--backfill-days` for hour-of-day
  realism.
