# MARKETING_SCHEMA_REPORT — S111 (PENDING SANDBOX TEST)

**Sесия:** S111.MARKETING_SCHEMA · 2026-05-08
**Status:** SQL artifacts COMPLETE · Sandbox test BLOCKED (DB access not yet granted)

---

## Tables created (25)

### `mkt_*` (18) — Marketing AI
| # | Table | Columns | FK | Notes |
|---|-------|--------:|----|-------|
| 1 | `mkt_prompt_templates` | 13 | — | Versioned agent prompts |
| 2 | `mkt_tenant_config` | 24 | tenants | Per-tenant gate + caps |
| 3 | `mkt_channel_auth` | 17 | tenants | OAuth tokens (AES) |
| 4 | `mkt_audiences` | 16 | tenants | Custom audiences |
| 5 | `mkt_creatives` | 22 | tenants | Creative library |
| 6 | `mkt_campaigns` | 33 | tenants, audiences, creatives | **Hub table** |
| 7 | `mkt_campaign_decisions` | 16 | tenants, campaigns | AI decision audit |
| 8 | `mkt_approval_queue` | 14 | tenants, campaigns | Tinder UX queue |
| 9 | `mkt_attribution_codes` | 13 | tenants, campaigns | Promo codes |
| 10 | `mkt_attribution_events` | 16 | none ⚠️ | **Partitioned** (2026/2027/max) |
| 11 | `mkt_attribution_matches` | 13 | tenants, campaigns, sales | Reconciliation |
| 12 | `mkt_budget_limits` | 15 | tenants | Hard caps |
| 13 | `mkt_spend_log` | 12 | tenants, campaigns | Daily spend |
| 14 | `mkt_learnings` | 11 | tenants, campaigns | Teaching moments |
| 15 | `mkt_benchmark_data` | 12 | — | Cross-tenant benchmarks |
| 16 | `mkt_user_overrides` | 10 | tenants, decisions, campaigns | User overrides |
| 17 | `mkt_scheduled_jobs` | 11 | tenants | Cron triggers |
| 18 | `mkt_triggers` | 11 | tenants | Auto-triggers |

### `online_*` (7) — Online Store
| # | Table | Columns | FK | Notes |
|---|-------|--------:|----|-------|
| 19 | `online_stores` | 23 | tenants | Ecwid mapping |
| 20 | `online_store_products` | 16 | online_stores, products | Product sync |
| 21 | `online_store_orders` | 26 | tenants, online_stores, mkt_campaigns | Ecwid orders |
| 22 | `online_store_inventory_locks` | 14 | tenants, online_store_orders, products | 30-min locks |
| 23 | `online_store_routing_log` | 11 | tenants, online_store_orders | Routing audit |
| 24 | `online_store_sync_log` | 13 | none ⚠️ | **Partitioned** |
| 25 | `online_store_settings` | 14 | tenants, stores | Per-tenant config |

⚠️ Partitioned tables cannot have FKs (MySQL limitation) — tenant isolation done at app layer.

---

## ALTER applied (9 tables, 49 new columns + 17 indexes + 4 cross-table FKs)

| # | Table | New columns | New indexes | New FKs |
|---|-------|------------:|-----------:|---------:|
| 1 | `tenants` | 7 (marketing_tier, marketing_activated_at, marketing_total_spend_lifetime, marketing_total_revenue_attributed, online_store_active, online_store_id, online_store_tier) | 1 (idx_marketing_tier) | 0 |
| 2 | `customers` | 5 (last_seen_in_ad_at, acquired_via_campaign_id, total_attributed_revenue, marketing_consent, marketing_consent_at) | 2 | 1 (→ mkt_campaigns) |
| 3 | `sales` | 6 (attribution_campaign_id, attribution_method, promo_code_used, attribution_confidence, source, ecwid_order_id) | 3 | 1 (→ mkt_campaigns) |
| 4 | `products` | 11 (last_promoted_at, total_ad_spend, total_attributed_revenue, promotion_eligibility_score, is_zombie, zombie_since, online_published, online_url, online_seo_title, online_seo_description, online_first_published_at) | 2 | 0 |
| 5 | `users` | 2 (marketing_role, voice_feedback_count) | 1 | 0 |
| 6 | `inventory` | 4 (reserved_quantity, available_for_online_quantity STORED GENERATED, accuracy_last_check_at, accuracy_score) | 1 | 0 |
| 7 | `stores` | 3 (is_base_warehouse, online_orders_enabled, average_processing_time_min) | 1 | 0 |
| 8 | `promotions` ⚠️ FAIL_GRACE | 2 (marketing_campaign_id, auto_generated_for_attribution) | 1 | 1 (→ mkt_campaigns) |
| 9 | `loyalty_points_log` ⚠️ FAIL_GRACE | 1 (attributed_to_campaign_id) | 1 | 1 (→ mkt_campaigns) |
| **TOTAL** | | **41 base + 8 conditional = 49** | **13 + 4 conditional = 17** | **2 base + 2 conditional = 4** |

⚠️ ALTER 8/9 silently skip if `promotions`/`loyalty_points_log` tables do not exist.

---

## Sandbox test results

**🚫 PENDING — DB access not yet granted. See HANDOFF for status.**

When sandbox testing runs, results will populate:

| Test | Expected | Actual | Status |
|------|---------|--------|--------|
| Schema-only dump backup | 1 file in `/tmp/runmystore_schema_*.sql` | — | ⏳ |
| Create runmystore_sandbox DB | exists | — | ⏳ |
| Apply up.sql | 0 errors, 25 new tables | — | ⏳ |
| Verify 9 ALTER columns present | per-table row counts match plan | — | ⏳ |
| Sample INSERT/SELECT per new table | succeeds, FKs enforced | — | ⏳ |
| Apply down.sql | 0 errors, sandbox restored | — | ⏳ |
| Re-apply up.sql (idempotency) | 0 errors on 2nd run | — | ⏳ |
| Re-apply down.sql (idempotency) | 0 errors on 2nd run | — | ⏳ |

Test command set (ready to execute when DB access lands):

```bash
# Sandbox setup
mysqldump --defaults-extra-file=/etc/runmystore/db.env --no-data --routines --triggers \
  runmystore > /tmp/runmystore_schema_$(date +%Y%m%d_%H%M).sql
mysql --defaults-extra-file=/etc/runmystore/db.env \
  -e "DROP DATABASE IF EXISTS runmystore_sandbox; CREATE DATABASE runmystore_sandbox CHARACTER SET utf8mb4;"
mysqldump --defaults-extra-file=/etc/runmystore/db.env runmystore | \
  mysql --defaults-extra-file=/etc/runmystore/db.env runmystore_sandbox

# Verify ALTER target tables
for t in tenants customers sales products users inventory stores promotions loyalty_points_log; do
  mysql --defaults-extra-file=/etc/runmystore/db.env -e "SHOW TABLES LIKE '$t';" runmystore_sandbox
done

# 1. Apply up
mysql --defaults-extra-file=/etc/runmystore/db.env runmystore_sandbox \
  < migrations/20260508_001_marketing_schema.up.sql 2>&1 | tee /tmp/up_test.log
# 2. Verify 25 new tables
mysql --defaults-extra-file=/etc/runmystore/db.env -e \
  "SELECT COUNT(*) FROM information_schema.tables \
   WHERE table_schema='runmystore_sandbox' AND \
     (table_name LIKE 'mkt_%' OR table_name LIKE 'online_%');" runmystore_sandbox
# expected: 25

# 3. Apply down
mysql --defaults-extra-file=/etc/runmystore/db.env runmystore_sandbox \
  < migrations/20260508_001_marketing_schema.down.sql 2>&1 | tee /tmp/down_test.log
# 4. Verify revert
mysql --defaults-extra-file=/etc/runmystore/db.env -e \
  "SELECT COUNT(*) FROM information_schema.tables \
   WHERE table_schema='runmystore_sandbox' AND \
     (table_name LIKE 'mkt_%' OR table_name LIKE 'online_%');" runmystore_sandbox
# expected: 0

# 5. Idempotency: re-apply up twice
mysql ... < up.sql; mysql ... < up.sql 2>&1 | tee /tmp/up_idemp.log
# expected: 0 errors on second run

# Cleanup
mysql --defaults-extra-file=/etc/runmystore/db.env \
  -e "DROP DATABASE IF EXISTS runmystore_sandbox;"
```

---

## Bytes added to schema (estimate)

- 25 new tables, ~13 columns avg, ~50 bytes/column metadata = **~16 KB schema overhead**
- Indexes: ~17 × ~4 KB metadata = **~68 KB**
- Generated column on `inventory` (STORED): ~4 bytes/row + index = ~5 MB per million inventory rows
- Partitioned tables grow on insert; estimated **negligible** at-rest before traffic

---

## Index count (for query optimization later)

Per Bible §3 indexes are designed for these query patterns:
- Tenant-scoped lookups: `idx_tenant_*` on most tables
- Time-range queries: `idx_*_time`, `idx_*_date`
- Campaign-aware: `idx_campaign_*` on attribution + spend
- Status filters: `idx_*_status`

Total **~64 new indexes** across 25 new tables + 13 new on existing tables. Most are
multi-column composite to support common filter combos (e.g., `(tenant_id, status, created_at)`).

---

## Production application instructions

See `migrations/20260508_001_marketing_schema_README.md` § HOW TO APPLY for full Тихол runbook.

**TL;DR:**
1. Backup full DB (mysqldump --single-transaction)
2. `mysql ... < up.sql 2>&1 | tee /tmp/s111_up.log`
3. Verify 25 tables + 49 columns
4. Smoke-test: insert+delete row in `mkt_tenant_config`

---

## Risks summary

| Risk | Severity | Mitigation |
|------|---------:|------------|
| `promotions` / `loyalty_points_log` missing | LOW | FAIL_GRACE — silent skip + re-run later |
| `inventory` generated column rebuild downtime | MEDIUM-HIGH | Plan no-traffic window; ~1 min per million rows |
| Partitioned tables lack FKs | MEDIUM | Application-layer tenant isolation (existing convention) |
| Cross-table FK validation against orphan rows | LOW | mkt_campaigns is fresh — no orphans yet |
| Idempotency stored procs persist on interrupt | LOW | Manual cleanup: `DROP PROCEDURE IF EXISTS s111_add_*` |
| Schema migration window estimate 5-20 min | MEDIUM | Use `pt-online-schema-change` if downtime unacceptable |
