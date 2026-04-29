-- =============================================================================
-- S88D.DELIVERY.SCHEMA — DELIVERY + ORDERS schema migration
-- =============================================================================
-- Source:   DELIVERY_ORDERS_DECISIONS_FINAL.md v3 (165 решения, sections A–X)
-- Date:     2026-04-29
-- Authority: BIBLE_v3_0_TECH §14.9 (LIVE SCHEMA AUTHORITY) — live wins on drift
-- Scope:    DB schema only. PHP/UI code = out of scope.
-- Idempotent: re-run safe. Each ALTER guarded by INFORMATION_SCHEMA check;
--           CREATE TABLE IF NOT EXISTS. Re-run = 0 errors, 0 changes.
-- Drift resolutions documented in docs/SESSION_S88D_SCHEMA_HANDOFF.md
-- =============================================================================

-- -----------------------------------------------------------------------------
-- SECTION A — NEW TABLES
-- -----------------------------------------------------------------------------

-- A1. delivery_events — audit trail per N2/L6
CREATE TABLE IF NOT EXISTS `delivery_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `store_id` INT UNSIGNED NOT NULL,
  `delivery_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `payload` JSON DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_de_tenant_time` (`tenant_id`, `created_at` DESC),
  KEY `idx_de_delivery` (`delivery_id`, `created_at`),
  KEY `idx_de_event_type` (`tenant_id`, `event_type`, `created_at`),
  CONSTRAINT `fk_de_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_de_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_de_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_de_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A2. supplier_defectives — pool за връщане към supplier (E2)
CREATE TABLE IF NOT EXISTS `supplier_defectives` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `store_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
  `delivery_id` INT UNSIGNED DEFAULT NULL,
  `delivery_item_id` INT UNSIGNED DEFAULT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `quantity` DECIMAL(12,4) NOT NULL,
  `unit_cost` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_cost` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `currency_code` CHAR(3) NOT NULL DEFAULT 'EUR',
  `reason` ENUM('damaged','expired','wrong_item','quality_issue','other') NOT NULL DEFAULT 'damaged',
  `note` TEXT DEFAULT NULL,
  `status` ENUM('pending','returned','written_off','credited','resolved') NOT NULL DEFAULT 'pending',
  `photo_urls` JSON DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `resolved_by` INT UNSIGNED DEFAULT NULL,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sd_lookup` (`tenant_id`, `supplier_id`, `status`),
  KEY `idx_sd_delivery` (`delivery_id`),
  KEY `idx_sd_product` (`product_id`),
  CONSTRAINT `fk_sd_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sd_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sd_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sd_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sd_delivery_item` FOREIGN KEY (`delivery_item_id`) REFERENCES `delivery_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sd_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sd_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sd_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A3. price_change_log — auto-pricing история (C5)
CREATE TABLE IF NOT EXISTS `price_change_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `store_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `delivery_id` INT UNSIGNED DEFAULT NULL,
  `delivery_item_id` INT UNSIGNED DEFAULT NULL,
  `old_cost` DECIMAL(12,4) DEFAULT NULL,
  `new_cost` DECIMAL(12,4) DEFAULT NULL,
  `old_retail` DECIMAL(12,4) DEFAULT NULL,
  `new_retail` DECIMAL(12,4) DEFAULT NULL,
  `cost_variance_pct` DECIMAL(8,2) DEFAULT NULL,
  `currency_code` CHAR(3) NOT NULL DEFAULT 'EUR',
  `change_source` ENUM('onboarding','manual','auto_pattern','sales_velocity','cost_variance','import') NOT NULL DEFAULT 'manual',
  `confidence` DECIMAL(3,2) DEFAULT NULL,
  `auto_applied` TINYINT(1) NOT NULL DEFAULT '0',
  `user_id` INT UNSIGNED DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pcl_product_time` (`tenant_id`, `product_id`, `created_at` DESC),
  KEY `idx_pcl_delivery` (`delivery_id`),
  CONSTRAINT `fk_pcl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pcl_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pcl_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pcl_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pcl_delivery_item` FOREIGN KEY (`delivery_item_id`) REFERENCES `delivery_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pcl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A4. pricing_patterns — per-category multiplier + ending learning (C2-C5, N3)
CREATE TABLE IF NOT EXISTS `pricing_patterns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `store_id` INT UNSIGNED DEFAULT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `category_name` VARCHAR(100) DEFAULT NULL,
  `multiplier` DECIMAL(6,3) NOT NULL DEFAULT '2.000',
  `ending_pattern` VARCHAR(20) DEFAULT NULL,
  `confidence` DECIMAL(3,2) NOT NULL DEFAULT '0.50',
  `sample_count` INT UNSIGNED NOT NULL DEFAULT '0',
  `learned_from` ENUM('onboarding','manual_corrections','sales_velocity','mixed') NOT NULL DEFAULT 'onboarding',
  `last_updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pp_scope` (`tenant_id`, `store_id`, `category_id`),
  KEY `idx_pp_category_name` (`tenant_id`, `category_name`),
  CONSTRAINT `fk_pp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pp_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A5. voice_synonyms — per-tenant lang/dialect (B6, H3, N3, SIMPLE_MODE_BIBLE §16.1)
CREATE TABLE IF NOT EXISTS `voice_synonyms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `lang` CHAR(2) NOT NULL DEFAULT 'bg',
  `synonym` VARCHAR(100) NOT NULL,
  `canonical` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) DEFAULT NULL,
  `usage_count` INT UNSIGNED NOT NULL DEFAULT '0',
  `created_by` ENUM('seed','user_correction','ai_learned') NOT NULL DEFAULT 'seed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vs_lookup` (`tenant_id`, `lang`, `synonym`),
  CONSTRAINT `fk_vs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- SECTION B — ALTER existing tables (idempotent via INFORMATION_SCHEMA guards)
-- -----------------------------------------------------------------------------

DROP PROCEDURE IF EXISTS `s88d_apply`;
DELIMITER $$

CREATE PROCEDURE `s88d_apply`()
BEGIN

  -- ===========================================================================
  -- B1. deliveries — ADD missing columns (N6, N7, S, T, F, X3+X7)
  -- ===========================================================================

  -- N6. currency_code snapshot
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'currency_code') THEN
    ALTER TABLE `deliveries` ADD COLUMN `currency_code` CHAR(3) NOT NULL DEFAULT 'EUR' AFTER `total`;
  END IF;

  -- X3+X7. status (draft for offline; superseded for M5 reconciliation auto-merge)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'status') THEN
    ALTER TABLE `deliveries`
      ADD COLUMN `status` ENUM('draft','pending','reviewing','committed','voided','superseded')
      NOT NULL DEFAULT 'draft' AFTER `note`;
  END IF;

  -- N7. invoice_type (auto-determined по OCR confidence — I6)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'invoice_type') THEN
    ALTER TABLE `deliveries` ADD COLUMN `invoice_type` ENUM('clean','semi','manual') DEFAULT NULL AFTER `status`;
  END IF;

  -- N7. invoice_number — OCR-извлечен номер на фактурата (different from internal `number`)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'invoice_number') THEN
    ALTER TABLE `deliveries` ADD COLUMN `invoice_number` VARCHAR(100) DEFAULT NULL AFTER `number`;
  END IF;

  -- N7. pack_size factor (default за всички items в тази доставка)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'pack_size') THEN
    ALTER TABLE `deliveries` ADD COLUMN `pack_size` INT NOT NULL DEFAULT '1' AFTER `landed_cost_extras`;
  END IF;

  -- N7. ocr_raw_json (debug)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'ocr_raw_json') THEN
    ALTER TABLE `deliveries` ADD COLUMN `ocr_raw_json` JSON DEFAULT NULL;
  END IF;

  -- N7. source_media_urls (snimkata на фактурите)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'source_media_urls') THEN
    ALTER TABLE `deliveries` ADD COLUMN `source_media_urls` JSON DEFAULT NULL;
  END IF;

  -- N7. reviewed_by / reviewed_at
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'reviewed_by') THEN
    ALTER TABLE `deliveries` ADD COLUMN `reviewed_by` INT UNSIGNED DEFAULT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'reviewed_at') THEN
    ALTER TABLE `deliveries` ADD COLUMN `reviewed_at` TIMESTAMP NULL DEFAULT NULL;
  END IF;

  -- N7. committed_by / committed_at
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'committed_by') THEN
    ALTER TABLE `deliveries` ADD COLUMN `committed_by` INT UNSIGNED DEFAULT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'committed_at') THEN
    ALTER TABLE `deliveries` ADD COLUMN `committed_at` TIMESTAMP NULL DEFAULT NULL;
  END IF;

  -- N7. locked_at
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'locked_at') THEN
    ALTER TABLE `deliveries` ADD COLUMN `locked_at` TIMESTAMP NULL DEFAULT NULL;
  END IF;

  -- N7. auto_close_reason
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'auto_close_reason') THEN
    ALTER TABLE `deliveries`
      ADD COLUMN `auto_close_reason` ENUM('user_committed','auto_after_session','imported','merged_with_next','voided') DEFAULT NULL;
  END IF;

  -- N7. has_mismatch + mismatch_summary (D1-D4 reconciliation)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'has_mismatch') THEN
    ALTER TABLE `deliveries` ADD COLUMN `has_mismatch` TINYINT(1) NOT NULL DEFAULT '0';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'mismatch_summary') THEN
    ALTER TABLE `deliveries` ADD COLUMN `mismatch_summary` JSON DEFAULT NULL;
  END IF;

  -- N7. has_unfactured_excess (D3 сценарий 3)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'has_unfactured_excess') THEN
    ALTER TABLE `deliveries` ADD COLUMN `has_unfactured_excess` TINYINT(1) NOT NULL DEFAULT '0';
  END IF;

  -- N7. has_unreceived_paid (D4 сценарий 4)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'has_unreceived_paid') THEN
    ALTER TABLE `deliveries` ADD COLUMN `has_unreceived_paid` TINYINT(1) NOT NULL DEFAULT '0';
  END IF;

  -- F6. content_signature hash (за duplicate detection)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'content_signature') THEN
    ALTER TABLE `deliveries` ADD COLUMN `content_signature` CHAR(64) DEFAULT NULL;
  END IF;

  -- N11. indexes на deliveries
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND INDEX_NAME = 'idx_d_tenant_supplier_time') THEN
    ALTER TABLE `deliveries` ADD INDEX `idx_d_tenant_supplier_time` (`tenant_id`, `supplier_id`, `created_at` DESC);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND INDEX_NAME = 'idx_d_tenant_status_time') THEN
    ALTER TABLE `deliveries` ADD INDEX `idx_d_tenant_status_time` (`tenant_id`, `status`, `created_at`);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND INDEX_NAME = 'idx_d_has_mismatch') THEN
    ALTER TABLE `deliveries` ADD INDEX `idx_d_has_mismatch` (`has_mismatch`);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND INDEX_NAME = 'idx_d_content_signature') THEN
    ALTER TABLE `deliveries` ADD INDEX `idx_d_content_signature` (`tenant_id`, `content_signature`);
  END IF;

  -- ===========================================================================
  -- B2. delivery_items — ADD missing columns (N6, N8, T, V, W, N5)
  -- ===========================================================================

  -- N6. tenant_id, store_id, currency_code (data isolation)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'tenant_id') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `id`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'store_id') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `store_id` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `tenant_id`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'supplier_id') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `supplier_id` INT UNSIGNED DEFAULT NULL AFTER `store_id`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'currency_code') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `currency_code` CHAR(3) NOT NULL DEFAULT 'EUR' AFTER `total`;
  END IF;

  -- N8. line_number
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'line_number') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `line_number` INT UNSIGNED DEFAULT NULL;
  END IF;

  -- N8. snapshots на момента на доставка (за audit ако product се преименува по-късно)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'product_name_snapshot') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `product_name_snapshot` VARCHAR(255) DEFAULT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'barcode_snapshot') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `barcode_snapshot` VARCHAR(64) DEFAULT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'sku_snapshot') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `sku_snapshot` VARCHAR(100) DEFAULT NULL;
  END IF;

  -- W1-W7. supplier_product_code („златен ключ")
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'supplier_product_code') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `supplier_product_code` VARCHAR(100) DEFAULT NULL;
  END IF;

  -- T1-T7. pack_size factor per item
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'pack_size') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `pack_size` INT NOT NULL DEFAULT '1';
  END IF;

  -- N8. vat_rate_applied (V4 — bonus = 0)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'vat_rate_applied') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `vat_rate_applied` DECIMAL(5,2) DEFAULT NULL;
  END IF;

  -- N8. received_condition
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'received_condition') THEN
    ALTER TABLE `delivery_items`
      ADD COLUMN `received_condition` ENUM('new','damaged','expired','wrong_item') NOT NULL DEFAULT 'new';
  END IF;

  -- N8. original_ocr_text
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'original_ocr_text') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `original_ocr_text` TEXT DEFAULT NULL;
  END IF;

  -- V1-V6. is_bonus
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'is_bonus') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `is_bonus` TINYINT(1) NOT NULL DEFAULT '0';
  END IF;

  -- J3. variation_pending
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'variation_pending') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `variation_pending` TINYINT(1) NOT NULL DEFAULT '0';
  END IF;

  -- N8. parent_product_id (за вариационни редове — К7 matrix flow)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'parent_product_id') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `parent_product_id` INT UNSIGNED DEFAULT NULL;
  END IF;

  -- N5. purchase_order_item_id (many-to-many delivery↔orders на ред-ниво)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'purchase_order_item_id') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `purchase_order_item_id` INT UNSIGNED DEFAULT NULL;
  END IF;

  -- created_at (нужен за N11 index "tenant_id, product_id, created_at")
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND COLUMN_NAME = 'created_at') THEN
    ALTER TABLE `delivery_items` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
  END IF;

  -- FKs за parent_product_id и purchase_order_item_id
  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND CONSTRAINT_NAME = 'fk_di_parent_product') THEN
    ALTER TABLE `delivery_items`
      ADD CONSTRAINT `fk_di_parent_product` FOREIGN KEY (`parent_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND CONSTRAINT_NAME = 'fk_di_po_item') THEN
    ALTER TABLE `delivery_items`
      ADD CONSTRAINT `fk_di_po_item` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`) ON DELETE SET NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND CONSTRAINT_NAME = 'fk_di_supplier') THEN
    ALTER TABLE `delivery_items`
      ADD CONSTRAINT `fk_di_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;
  END IF;

  -- N11 + W6 indexes на delivery_items
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND INDEX_NAME = 'idx_di_tenant_product_time') THEN
    ALTER TABLE `delivery_items` ADD INDEX `idx_di_tenant_product_time` (`tenant_id`, `product_id`, `created_at`);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND INDEX_NAME = 'idx_di_variation_pending') THEN
    ALTER TABLE `delivery_items` ADD INDEX `idx_di_variation_pending` (`variation_pending`);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND INDEX_NAME = 'idx_di_supplier_product_code') THEN
    ALTER TABLE `delivery_items` ADD INDEX `idx_di_supplier_product_code` (`supplier_product_code`);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_items' AND INDEX_NAME = 'idx_di_w6_lookup') THEN
    ALTER TABLE `delivery_items` ADD INDEX `idx_di_w6_lookup` (`tenant_id`, `supplier_id`, `supplier_product_code`);
  END IF;

  -- ===========================================================================
  -- B3. suppliers — ADD reliability_score, payment_terms_days
  -- ===========================================================================

  -- N4. reliability_score (0-100)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'reliability_score') THEN
    ALTER TABLE `suppliers` ADD COLUMN `reliability_score` TINYINT UNSIGNED DEFAULT NULL;
  END IF;

  -- S2. payment_terms_days (0 = cash on delivery)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'payment_terms_days') THEN
    ALTER TABLE `suppliers` ADD COLUMN `payment_terms_days` INT NOT NULL DEFAULT '0';
  END IF;

  -- ===========================================================================
  -- B4. ai_insights — ADD linked_brain_queue_id (FK deferred to S91)
  -- ===========================================================================

  -- N4 / M4. linked_brain_queue_id (no FK yet — ai_brain_queue table deferred)
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_insights' AND COLUMN_NAME = 'linked_brain_queue_id') THEN
    ALTER TABLE `ai_insights` ADD COLUMN `linked_brain_queue_id` INT UNSIGNED DEFAULT NULL;
    ALTER TABLE `ai_insights` ADD INDEX `idx_ai_brain_queue` (`linked_brain_queue_id`);
  END IF;

  -- ===========================================================================
  -- B5. purchase_orders — MODIFY status ENUM да включва 'stale' (U1)
  -- ===========================================================================

  -- Live: ('draft','sent','partial','received','cancelled') — добавя 'stale'
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders'
                   AND COLUMN_NAME = 'status' AND COLUMN_TYPE LIKE '%''stale''%') THEN
    ALTER TABLE `purchase_orders`
      MODIFY COLUMN `status` ENUM('draft','sent','partial','received','cancelled','stale')
      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft';
  END IF;

END$$

DELIMITER ;

CALL `s88d_apply`();
DROP PROCEDURE `s88d_apply`;
