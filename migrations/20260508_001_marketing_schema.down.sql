-- ════════════════════════════════════════════════════════════════════
-- S111.MARKETING_SCHEMA · ROLLBACK · 2026-05-08
-- Reverts: 25 CREATE TABLE + 9 ALTER from 20260508_001_marketing_schema.up.sql
-- ════════════════════════════════════════════════════════════════════
-- ⚠️ EMERGENCY USE ONLY. Will:
--   1. Drop columns added to 9 existing tables (data loss for those columns)
--   2. Drop 25 new mkt_*/online_* tables (full data loss)
-- Idempotent: every DROP is guarded.
-- ════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_NOTES = 0;

-- ════════════════════════════════════════════════════════════════════
-- PART 1: REVERT 9 ALTERs (drop columns + indexes + FKs added by up.sql)
-- ════════════════════════════════════════════════════════════════════

DELIMITER //

DROP PROCEDURE IF EXISTS s111_drop_column//
CREATE PROCEDURE s111_drop_column(
  IN p_table VARCHAR(64),
  IN p_column VARCHAR(64)
)
BEGIN
  DECLARE col_exists INT DEFAULT 0;
  DECLARE tbl_exists INT DEFAULT 0;
  SELECT COUNT(*) INTO tbl_exists FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table;
  IF tbl_exists > 0 THEN
    SELECT COUNT(*) INTO col_exists FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column;
    IF col_exists > 0 THEN
      SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP COLUMN `', p_column, '`');
      PREPARE stmt FROM @sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;
  END IF;
END//

DROP PROCEDURE IF EXISTS s111_drop_index//
CREATE PROCEDURE s111_drop_index(
  IN p_table VARCHAR(64),
  IN p_index VARCHAR(64)
)
BEGIN
  DECLARE idx_exists INT DEFAULT 0;
  DECLARE tbl_exists INT DEFAULT 0;
  SELECT COUNT(*) INTO tbl_exists FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table;
  IF tbl_exists > 0 THEN
    SELECT COUNT(*) INTO idx_exists FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index;
    IF idx_exists > 0 THEN
      SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP INDEX `', p_index, '`');
      PREPARE stmt FROM @sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;
  END IF;
END//

DROP PROCEDURE IF EXISTS s111_drop_fk//
CREATE PROCEDURE s111_drop_fk(
  IN p_table VARCHAR(64),
  IN p_constraint VARCHAR(64)
)
BEGIN
  DECLARE fk_exists INT DEFAULT 0;
  DECLARE tbl_exists INT DEFAULT 0;
  SELECT COUNT(*) INTO tbl_exists FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table;
  IF tbl_exists > 0 THEN
    SELECT COUNT(*) INTO fk_exists FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
        AND CONSTRAINT_NAME = p_constraint AND CONSTRAINT_TYPE = 'FOREIGN KEY';
    IF fk_exists > 0 THEN
      SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP FOREIGN KEY `', p_constraint, '`');
      PREPARE stmt FROM @sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;
  END IF;
END//

DELIMITER ;

-- ── ALTER 9 revert: loyalty_points_log ───────────────────────────────
CALL s111_drop_fk('loyalty_points_log', 'fk_loyalty_campaign');
CALL s111_drop_index('loyalty_points_log', 'idx_attribution');
CALL s111_drop_column('loyalty_points_log', 'attributed_to_campaign_id');

-- ── ALTER 8 revert: promotions ───────────────────────────────────────
CALL s111_drop_fk('promotions', 'fk_promo_campaign');
CALL s111_drop_index('promotions', 'idx_marketing_campaign');
CALL s111_drop_column('promotions', 'auto_generated_for_attribution');
CALL s111_drop_column('promotions', 'marketing_campaign_id');

-- ── ALTER 7 revert: stores ───────────────────────────────────────────
CALL s111_drop_index('stores', 'idx_base_warehouse');
CALL s111_drop_column('stores', 'average_processing_time_min');
CALL s111_drop_column('stores', 'online_orders_enabled');
CALL s111_drop_column('stores', 'is_base_warehouse');

-- ── ALTER 6 revert: inventory ────────────────────────────────────────
-- Drop generated column FIRST (depends on reserved_quantity)
CALL s111_drop_index('inventory', 'idx_available_online');
CALL s111_drop_column('inventory', 'available_for_online_quantity');
CALL s111_drop_column('inventory', 'reserved_quantity');
CALL s111_drop_column('inventory', 'accuracy_last_check_at');
CALL s111_drop_column('inventory', 'accuracy_score');

-- ── ALTER 5 revert: users ────────────────────────────────────────────
CALL s111_drop_index('users', 'idx_marketing_role');
CALL s111_drop_column('users', 'voice_feedback_count');
CALL s111_drop_column('users', 'marketing_role');

-- ── ALTER 4 revert: products ─────────────────────────────────────────
CALL s111_drop_index('products', 'idx_online_published');
CALL s111_drop_index('products', 'idx_zombie');
CALL s111_drop_column('products', 'online_first_published_at');
CALL s111_drop_column('products', 'online_seo_description');
CALL s111_drop_column('products', 'online_seo_title');
CALL s111_drop_column('products', 'online_url');
CALL s111_drop_column('products', 'online_published');
CALL s111_drop_column('products', 'zombie_since');
CALL s111_drop_column('products', 'is_zombie');
CALL s111_drop_column('products', 'promotion_eligibility_score');
CALL s111_drop_column('products', 'total_attributed_revenue');
CALL s111_drop_column('products', 'total_ad_spend');
CALL s111_drop_column('products', 'last_promoted_at');

-- ── ALTER 3 revert: sales ────────────────────────────────────────────
CALL s111_drop_fk('sales', 'fk_sales_campaign');
CALL s111_drop_index('sales', 'idx_ecwid_order');
CALL s111_drop_index('sales', 'idx_source');
CALL s111_drop_index('sales', 'idx_campaign');
CALL s111_drop_column('sales', 'ecwid_order_id');
CALL s111_drop_column('sales', 'source');
CALL s111_drop_column('sales', 'attribution_confidence');
CALL s111_drop_column('sales', 'promo_code_used');
CALL s111_drop_column('sales', 'attribution_method');
CALL s111_drop_column('sales', 'attribution_campaign_id');

-- ── ALTER 2 revert: customers ────────────────────────────────────────
CALL s111_drop_fk('customers', 'fk_cust_campaign');
CALL s111_drop_index('customers', 'idx_consent');
CALL s111_drop_index('customers', 'idx_acquired_campaign');
CALL s111_drop_column('customers', 'marketing_consent_at');
CALL s111_drop_column('customers', 'marketing_consent');
CALL s111_drop_column('customers', 'total_attributed_revenue');
CALL s111_drop_column('customers', 'acquired_via_campaign_id');
CALL s111_drop_column('customers', 'last_seen_in_ad_at');

-- ── ALTER 1 revert: tenants ──────────────────────────────────────────
CALL s111_drop_index('tenants', 'idx_marketing_tier');
CALL s111_drop_column('tenants', 'online_store_tier');
CALL s111_drop_column('tenants', 'online_store_id');
CALL s111_drop_column('tenants', 'online_store_active');
CALL s111_drop_column('tenants', 'marketing_total_revenue_attributed');
CALL s111_drop_column('tenants', 'marketing_total_spend_lifetime');
CALL s111_drop_column('tenants', 'marketing_activated_at');
CALL s111_drop_column('tenants', 'marketing_tier');

-- ── Cleanup helpers ──────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS s111_drop_column;
DROP PROCEDURE IF EXISTS s111_drop_index;
DROP PROCEDURE IF EXISTS s111_drop_fk;

-- ════════════════════════════════════════════════════════════════════
-- PART 2: DROP 25 NEW TABLES (reverse FK dependency order)
-- ════════════════════════════════════════════════════════════════════
-- Order: leaves first (no incoming FKs), roots last.

-- online_* leaves (sync_log, routing_log, locks reference orders)
DROP TABLE IF EXISTS online_store_sync_log;
DROP TABLE IF EXISTS online_store_routing_log;
DROP TABLE IF EXISTS online_store_inventory_locks;
DROP TABLE IF EXISTS online_store_settings;
-- online_store_orders references online_stores AND mkt_campaigns
DROP TABLE IF EXISTS online_store_orders;
-- online_store_products references online_stores AND products (external)
DROP TABLE IF EXISTS online_store_products;
DROP TABLE IF EXISTS online_stores;

-- mkt_* leaves
DROP TABLE IF EXISTS mkt_user_overrides;
DROP TABLE IF EXISTS mkt_attribution_matches;
DROP TABLE IF EXISTS mkt_attribution_events;
DROP TABLE IF EXISTS mkt_attribution_codes;
DROP TABLE IF EXISTS mkt_approval_queue;
DROP TABLE IF EXISTS mkt_spend_log;
DROP TABLE IF EXISTS mkt_learnings;
DROP TABLE IF EXISTS mkt_campaign_decisions;

-- mkt_campaigns referenced by many mkt_* + online_store_orders
-- (online_store_orders already dropped above)
DROP TABLE IF EXISTS mkt_campaigns;

-- mkt_campaigns dependencies: audiences, creatives
DROP TABLE IF EXISTS mkt_audiences;
DROP TABLE IF EXISTS mkt_creatives;

-- mkt_* roots (no FKs to other new tables)
DROP TABLE IF EXISTS mkt_budget_limits;
DROP TABLE IF EXISTS mkt_triggers;
DROP TABLE IF EXISTS mkt_scheduled_jobs;
DROP TABLE IF EXISTS mkt_benchmark_data;
DROP TABLE IF EXISTS mkt_channel_auth;
DROP TABLE IF EXISTS mkt_tenant_config;
DROP TABLE IF EXISTS mkt_prompt_templates;

SET FOREIGN_KEY_CHECKS = 1;
SET SQL_NOTES = 1;

-- ════════════════════════════════════════════════════════════════════
-- ROLLBACK COMPLETE: 25 tables dropped, 9 ALTERs reverted
-- ════════════════════════════════════════════════════════════════════
