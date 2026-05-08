# 20260508_001_marketing_schema — Marketing AI + Online Store

**Session:** S111.MARKETING_SCHEMA · 2026-05-08
**Source:** `docs/marketing/MARKETING_BIBLE_TECHNICAL_v1.md` v1.0 (lines 105–1116)

---

## SCOPE

| Operation | Count | Detail |
|-----------|------:|--------|
| `CREATE TABLE` | **25** | 18 × `mkt_*` (Marketing AI) + 7 × `online_*` (Ecwid) |
| `ALTER TABLE` (ADD COLUMN) | 9 tables, **49 columns** | tenants(7), customers(5), sales(6), products(11), users(2), inventory(4), stores(3), promotions(2), loyalty_points_log(1) |
| New indexes | ~17 | per Bible §3 |
| New foreign keys | 4 (cross-table) | customers→mkt_campaigns, sales→mkt_campaigns, promotions→mkt_campaigns, loyalty→mkt_campaigns |

---

## PRE-CONDITIONS

The migration **assumes existence of**:
- `tenants`, `customers`, `sales`, `products`, `users`, `inventory`, `stores` (core tables — must exist)
- MySQL **5.7.18+** for partitioning + InnoDB native partitioning + generated columns

**Conditional tables** (the migration tolerates absence via `s111_add_column` table-existence guard):
- `promotions` — likely missing (Phase D / post-beta module). ALTER 8 will silently skip if absent.
- `loyalty_points_log` — may or may not exist. ALTER 9 will silently skip if absent.

If you need ALTER 8/9 to actually fire later, re-run this migration after creating those tables — it's idempotent.

---

## ORDER OF OPERATIONS (up.sql)

### Part 1 — Create 18 `mkt_*` tables (FK-safe order)
1. `mkt_prompt_templates` (no FKs)
2. `mkt_tenant_config` → tenants
3. `mkt_channel_auth` → tenants
4. `mkt_audiences` → tenants    *(referenced by mkt_campaigns)*
5. `mkt_creatives` → tenants    *(referenced by mkt_campaigns)*
6. **`mkt_campaigns`** → tenants, mkt_audiences, mkt_creatives
7. `mkt_campaign_decisions` → tenants, mkt_campaigns
8. `mkt_approval_queue` → tenants, mkt_campaigns
9. `mkt_attribution_codes` → tenants, mkt_campaigns
10. `mkt_attribution_events` *(partitioned, no FKs — MySQL limitation)*
11. `mkt_attribution_matches` → tenants, mkt_campaigns, sales
12. `mkt_budget_limits` → tenants
13. `mkt_spend_log` → tenants, mkt_campaigns
14. `mkt_learnings` → tenants, mkt_campaigns
15. `mkt_benchmark_data` *(no FKs)*
16. `mkt_user_overrides` → tenants, mkt_campaign_decisions, mkt_campaigns
17. `mkt_scheduled_jobs` → tenants
18. `mkt_triggers` → tenants

### Part 2 — Create 7 `online_*` tables
19. `online_stores` → tenants
20. `online_store_products` → online_stores, products
21. `online_store_orders` → tenants, online_stores, mkt_campaigns
22. `online_store_inventory_locks` → tenants, online_store_orders, products
23. `online_store_routing_log` → tenants, online_store_orders
24. `online_store_sync_log` *(partitioned, no FKs)*
25. `online_store_settings` → tenants, stores

### Part 3 — 9 ALTER TABLE
Idempotency via temporary stored procedures (`s111_add_column`, `s111_add_index`, `s111_add_fk`)
that consult `INFORMATION_SCHEMA` before issuing each ADD. Procedures are dropped at the end.

ALTER 6 (`inventory`) requires column ordering: `reserved_quantity` MUST be added before
`available_for_online_quantity` (the latter is a generated column referencing the former).

---

## IDEMPOTENCY GUARANTEE

- All `CREATE TABLE` statements use `IF NOT EXISTS`.
- All `ALTER TABLE` operations check `INFORMATION_SCHEMA.{COLUMNS,STATISTICS,TABLE_CONSTRAINTS}`
  before adding columns/indexes/FKs.
- All `DROP TABLE` (in down.sql) use `IF EXISTS`.
- All `DROP COLUMN/INDEX/FOREIGN KEY` (in down.sql) check existence first.

You may run **up.sql twice** with no errors. Same for down.sql. up→down→up→down round-trips
must leave the DB in a stable state.

---

## SPECIAL CASES

### 1. Partitioned tables (`mkt_attribution_events`, `online_store_sync_log`)

Both use `PARTITION BY RANGE (UNIX_TIMESTAMP(...))` with partitions:
- `p_2026` (events through 2026-12-31)
- `p_2027`
- `p_max` (catch-all)

**MySQL constraint:** the partitioning column must be part of every UNIQUE/PRIMARY key. Hence
the PRIMARY KEY is `(id, event_timestamp)` / `(id, synced_at)` instead of just `(id)`.

**MySQL constraint:** partitioned tables cannot have foreign keys. The Bible's spec used FKs,
but they are intentionally omitted here. Tenant isolation is enforced at the application layer
(every query must include `WHERE tenant_id = :t`).

### 2. Generated column on `inventory`

```sql
ALTER TABLE inventory ADD COLUMN available_for_online_quantity
  INT UNSIGNED GENERATED ALWAYS AS (GREATEST(quantity - reserved_quantity, 0)) STORED;
```

Requires:
- `inventory.quantity` exists (verified — core schema)
- `reserved_quantity` added in the prior step (the migration handles ordering correctly)

### 3. ALTER 8 / ALTER 9 conditional

`promotions` and `loyalty_points_log` may not exist on this DB. The migration uses
`s111_add_column` which silently `SELECT 'SKIP'` if the table is missing, so the script
continues without error. Re-run after those tables are created to apply the missing ALTERs.

---

## HOW TO APPLY (PRODUCTION)

⚠️ **Тихол manualлно изпълнение в no-traffic window** — NOT through automation.

```bash
# 1. Full backup
mysqldump --defaults-extra-file=/etc/runmystore/db.env \
  --routines --triggers --single-transaction --quick \
  runmystore > /var/backups/runmystore_pre_s111_$(date +%Y%m%d_%H%M).sql

ls -la /var/backups/runmystore_pre_s111_*.sql  # verify backup ≥ ~50MB

# 2. Apply migration
mysql --defaults-extra-file=/etc/runmystore/db.env runmystore \
  < migrations/20260508_001_marketing_schema.up.sql 2>&1 | tee /tmp/s111_up.log

# 3. Verify
mysql --defaults-extra-file=/etc/runmystore/db.env runmystore <<'SQL'
  SELECT COUNT(*) AS new_tables FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = 'runmystore'
     AND (TABLE_NAME LIKE 'mkt_%' OR TABLE_NAME LIKE 'online_%');
  -- expected: 25

  SELECT COUNT(*) AS new_columns FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = 'runmystore'
     AND TABLE_NAME IN ('tenants','customers','sales','products','users','inventory','stores')
     AND COLUMN_NAME IN (
       'marketing_tier','marketing_activated_at','online_store_active',
       'last_seen_in_ad_at','marketing_consent','attribution_campaign_id',
       'source','is_zombie','online_published','marketing_role',
       'reserved_quantity','available_for_online_quantity','is_base_warehouse'
     );
  -- expected: ~13 (subset of 49 if you check just these representatives)

  SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = 'runmystore' AND TABLE_NAME = 'inventory'
     AND COLUMN_NAME IN ('reserved_quantity','available_for_online_quantity');
  -- expected: 2 rows
SQL

# 4. Smoke-test: insert a row into mkt_tenant_config to confirm FK works
# (replace 1 with a real tenant_id)
mysql --defaults-extra-file=/etc/runmystore/db.env runmystore <<'SQL'
  INSERT INTO mkt_tenant_config (tenant_id, tier) VALUES (1, 'shadow');
  SELECT id, tenant_id, tier FROM mkt_tenant_config WHERE tenant_id = 1;
  DELETE FROM mkt_tenant_config WHERE tenant_id = 1 AND tier = 'shadow';
SQL
```

---

## ROLLBACK PROCEDURE

⚠️ **Drops 25 tables (data loss) + 49 columns from existing tables (data loss).**

```bash
# 1. Re-snapshot before rollback
mysqldump --defaults-extra-file=/etc/runmystore/db.env \
  --single-transaction runmystore \
  > /var/backups/runmystore_pre_s111_rollback_$(date +%Y%m%d_%H%M).sql

# 2. Apply rollback
mysql --defaults-extra-file=/etc/runmystore/db.env runmystore \
  < migrations/20260508_001_marketing_schema.down.sql 2>&1 | tee /tmp/s111_down.log

# 3. Verify revert
mysql --defaults-extra-file=/etc/runmystore/db.env runmystore <<'SQL'
  SELECT COUNT(*) AS remaining_new_tables FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = 'runmystore'
     AND (TABLE_NAME LIKE 'mkt_%' OR TABLE_NAME LIKE 'online_%');
  -- expected: 0
SQL
```

---

## DOWNTIME ESTIMATE

| Operation | Estimate |
|-----------|----------|
| Backup (~runmystore size, single-transaction) | 1–5 min |
| 25 × CREATE TABLE | < 5 sec total |
| 9 × ALTER TABLE on existing tables (with data) | depends on table size |
| - tenants, users, stores, settings tables | < 5 sec each |
| - customers, sales | 30 sec – 5 min depending on row count |
| - products, inventory | 30 sec – 5 min |
| - inventory generated column rebuild | 1 – 10 min (full table rewrite) |

**Estimated total downtime:** **5–20 minutes** for typical tenant DB; longer if `inventory` /
`sales` tables are very large. Recommend executing with `pt-online-schema-change` or via
read-replica swap if downtime is unacceptable.

---

## RISKS

| Risk | Severity | Mitigation |
|------|---------:|------------|
| `promotions` missing → ALTER 8 silent skip | LOW | Documented; re-run after promotions creation |
| `loyalty_points_log` missing → ALTER 9 silent skip | LOW | Same as above |
| `inventory` generated column rewrites whole table | MEDIUM | Plan downtime; ~1 min per million rows |
| Partitioned tables cannot have FKs (Bible spec ignored) | MEDIUM | Application-level tenant isolation enforced |
| Cross-table FKs (customers/sales/promotions/loyalty → mkt_campaigns) | MEDIUM | New FK validation can fail if orphan rows exist; this DB has none yet (mkt_campaigns is fresh) |
| Idempotency stored procs persist after error | LOW | up.sql drops them at the end; if interrupted, drop manually: `DROP PROCEDURE IF EXISTS s111_add_column; ...` |

---

## RELATED FILES

- `migrations/20260508_001_marketing_schema.up.sql` — forward migration
- `migrations/20260508_001_marketing_schema.down.sql` — rollback
- `docs/marketing/MARKETING_BIBLE_TECHNICAL_v1.md` — source of truth (Бог Bible)
- `docs/marketing/MARKETING_BIBLE_LOGIC_v1.md` — semantic context
- `handoffs/HANDOFF_S111_<TS>.md` — session handoff (committed alongside)
- `handoffs/MARKETING_SCHEMA_REPORT_<TS>.md` — sandbox test report (committed alongside)
