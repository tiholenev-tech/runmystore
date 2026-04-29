-- =============================================================================
-- S88D.DELIVERY.SCHEMA ROLLBACK
-- =============================================================================
-- Reverses migrations/s88d_delivery_schema.sql
--
-- ⚠ Use only in dev/staging. На production data → forward fix вместо rollback.
-- ⚠ DROP TABLE supplier_defectives, price_change_log etc.: ако има реални
--   данни, те се ГУБЯТ. Backup първо.
--
-- Идемпотентен — re-run safe.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- B5. purchase_orders.status — премахва 'stale' от ENUM
-- -----------------------------------------------------------------------------

DROP PROCEDURE IF EXISTS `s88d_rollback`;
DELIMITER $$

CREATE PROCEDURE `s88d_rollback`()
BEGIN

  -- B5. revert purchase_orders.status ENUM (премахва 'stale')
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders'
               AND COLUMN_NAME = 'status' AND COLUMN_TYPE LIKE '%''stale''%') THEN
    -- Първо UPDATE 'stale' rows към 'cancelled' за да не се счупи при ENUM свиване
    UPDATE `purchase_orders` SET `status` = 'cancelled' WHERE `status` = 'stale';
    ALTER TABLE `purchase_orders`
      MODIFY COLUMN `status` ENUM('draft','sent','partial','received','cancelled')
      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft';
  END IF;

  -- B4. ai_insights — DROP linked_brain_queue_id
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_insights' AND INDEX_NAME = 'idx_ai_brain_queue') THEN
    ALTER TABLE `ai_insights` DROP INDEX `idx_ai_brain_queue`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_insights' AND COLUMN_NAME = 'linked_brain_queue_id') THEN
    ALTER TABLE `ai_insights` DROP COLUMN `linked_brain_queue_id`;
  END IF;

  -- B3. suppliers — DROP reliability_score, payment_terms_days
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'reliability_score') THEN
    ALTER TABLE `suppliers` DROP COLUMN `reliability_score`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'payment_terms_days') THEN
    ALTER TABLE `suppliers` DROP COLUMN `payment_terms_days`;
  END IF;

  -- B2. delivery_items — DROP всички добавени колони / FK / indexes
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND INDEX_NAME = 'idx_di_w6_lookup') THEN
    ALTER TABLE `delivery_items` DROP INDEX `idx_di_w6_lookup`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND INDEX_NAME = 'idx_di_supplier_product_code') THEN
    ALTER TABLE `delivery_items` DROP INDEX `idx_di_supplier_product_code`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND INDEX_NAME = 'idx_di_variation_pending') THEN
    ALTER TABLE `delivery_items` DROP INDEX `idx_di_variation_pending`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND INDEX_NAME = 'idx_di_tenant_product_time') THEN
    ALTER TABLE `delivery_items` DROP INDEX `idx_di_tenant_product_time`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND CONSTRAINT_NAME = 'fk_di_supplier') THEN
    ALTER TABLE `delivery_items` DROP FOREIGN KEY `fk_di_supplier`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND CONSTRAINT_NAME = 'fk_di_po_item') THEN
    ALTER TABLE `delivery_items` DROP FOREIGN KEY `fk_di_po_item`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND CONSTRAINT_NAME = 'fk_di_parent_product') THEN
    ALTER TABLE `delivery_items` DROP FOREIGN KEY `fk_di_parent_product`;
  END IF;

  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='created_at') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `created_at`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='purchase_order_item_id') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `purchase_order_item_id`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='parent_product_id') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `parent_product_id`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='variation_pending') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `variation_pending`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='is_bonus') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `is_bonus`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='original_ocr_text') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `original_ocr_text`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='received_condition') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `received_condition`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='vat_rate_applied') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `vat_rate_applied`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='pack_size') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `pack_size`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='supplier_product_code') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `supplier_product_code`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='sku_snapshot') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `sku_snapshot`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='barcode_snapshot') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `barcode_snapshot`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='product_name_snapshot') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `product_name_snapshot`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='line_number') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `line_number`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='currency_code') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `currency_code`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='supplier_id') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `supplier_id`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='store_id') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `store_id`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_items' AND COLUMN_NAME='tenant_id') THEN
    ALTER TABLE `delivery_items` DROP COLUMN `tenant_id`;
  END IF;

  -- B1. deliveries — DROP всички добавени колони / indexes
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND INDEX_NAME = 'idx_d_content_signature') THEN
    ALTER TABLE `deliveries` DROP INDEX `idx_d_content_signature`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND INDEX_NAME = 'idx_d_has_mismatch') THEN
    ALTER TABLE `deliveries` DROP INDEX `idx_d_has_mismatch`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND INDEX_NAME = 'idx_d_tenant_status_time') THEN
    ALTER TABLE `deliveries` DROP INDEX `idx_d_tenant_status_time`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND INDEX_NAME = 'idx_d_tenant_supplier_time') THEN
    ALTER TABLE `deliveries` DROP INDEX `idx_d_tenant_supplier_time`;
  END IF;

  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='content_signature') THEN
    ALTER TABLE `deliveries` DROP COLUMN `content_signature`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='has_unreceived_paid') THEN
    ALTER TABLE `deliveries` DROP COLUMN `has_unreceived_paid`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='has_unfactured_excess') THEN
    ALTER TABLE `deliveries` DROP COLUMN `has_unfactured_excess`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='mismatch_summary') THEN
    ALTER TABLE `deliveries` DROP COLUMN `mismatch_summary`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='has_mismatch') THEN
    ALTER TABLE `deliveries` DROP COLUMN `has_mismatch`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='auto_close_reason') THEN
    ALTER TABLE `deliveries` DROP COLUMN `auto_close_reason`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='locked_at') THEN
    ALTER TABLE `deliveries` DROP COLUMN `locked_at`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='committed_at') THEN
    ALTER TABLE `deliveries` DROP COLUMN `committed_at`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='committed_by') THEN
    ALTER TABLE `deliveries` DROP COLUMN `committed_by`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='reviewed_at') THEN
    ALTER TABLE `deliveries` DROP COLUMN `reviewed_at`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='reviewed_by') THEN
    ALTER TABLE `deliveries` DROP COLUMN `reviewed_by`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='source_media_urls') THEN
    ALTER TABLE `deliveries` DROP COLUMN `source_media_urls`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='ocr_raw_json') THEN
    ALTER TABLE `deliveries` DROP COLUMN `ocr_raw_json`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='pack_size') THEN
    ALTER TABLE `deliveries` DROP COLUMN `pack_size`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='invoice_number') THEN
    ALTER TABLE `deliveries` DROP COLUMN `invoice_number`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='invoice_type') THEN
    ALTER TABLE `deliveries` DROP COLUMN `invoice_type`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='status') THEN
    ALTER TABLE `deliveries` DROP COLUMN `status`;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deliveries' AND COLUMN_NAME='currency_code') THEN
    ALTER TABLE `deliveries` DROP COLUMN `currency_code`;
  END IF;

END$$

DELIMITER ;

CALL `s88d_rollback`();
DROP PROCEDURE `s88d_rollback`;

-- A. DROP NEW TABLES (last → FK references gone)
DROP TABLE IF EXISTS `voice_synonyms`;
DROP TABLE IF EXISTS `pricing_patterns`;
DROP TABLE IF EXISTS `price_change_log`;
DROP TABLE IF EXISTS `supplier_defectives`;
DROP TABLE IF EXISTS `delivery_events`;
