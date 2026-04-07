-- ============================================================
-- RunMyStore.ai — S31 DB SCHEMA FREEZE
-- Дата: 07.04.2026
-- СЛЕД ТОЗИ ФАЙЛ: DB Е FROZEN. Промени САМО с ALTER TABLE миграции.
-- ============================================================

DELIMITER //

-- Helper: safe ADD COLUMN (skip if exists)
DROP PROCEDURE IF EXISTS safe_add_column//
CREATE PROCEDURE safe_add_column(
    IN tbl VARCHAR(64),
    IN col VARCHAR(64),
    IN col_def VARCHAR(512)
)
BEGIN
    SET @exists = (
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    );
    IF @exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', col_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

-- ============================================================
-- 1. PRODUCTS — нови полета за инвентаризация + едро
-- ============================================================
CALL safe_add_column('products', 'confidence_score',    'TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT "0-100, инвентаризация confidence"');
CALL safe_add_column('products', 'has_physical_count',  'TINYINT(1) NOT NULL DEFAULT 0 COMMENT "1 = физически преброен"');
CALL safe_add_column('products', 'wholesale_price',     'DECIMAL(10,2) DEFAULT NULL COMMENT "Цена на едро"');
CALL safe_add_column('products', 'has_variations',      "ENUM('true','false','unknown') NOT NULL DEFAULT 'unknown'");
CALL safe_add_column('products', 'variations_tracked',  'TINYINT(1) NOT NULL DEFAULT 0');
CALL safe_add_column('products', 'first_sold_at',       'TIMESTAMP NULL DEFAULT NULL COMMENT "Попълва се автоматично при първа продажба"');
CALL safe_add_column('products', 'first_delivered_at',  'TIMESTAMP NULL DEFAULT NULL COMMENT "Попълва се автоматично при първа доставка"');

-- ============================================================
-- 2. SALES — едро флаг
-- ============================================================
CALL safe_add_column('sales', 'is_wholesale',  'TINYINT(1) NOT NULL DEFAULT 0 COMMENT "1 = продажба на едро"');
CALL safe_add_column('sales', 'customer_id',   'INT UNSIGNED DEFAULT NULL COMMENT "FK → customers.id за едро клиенти"');

-- ============================================================
-- 3. TENANTS — accountant email
-- ============================================================
CALL safe_add_column('tenants', 'accountant_email', 'VARCHAR(255) DEFAULT NULL COMMENT "Имейл на счетоводител за авто-изпращане"');

-- ============================================================
-- 4. НОВА ТАБЛИЦА: inventory_checks (категория на ден)
-- ============================================================
CREATE TABLE IF NOT EXISTS `inventory_checks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `checked_by` INT UNSIGNED NOT NULL COMMENT 'FK → users.id',
    `products_checked` INT UNSIGNED NOT NULL DEFAULT 0,
    `confidence_before` TINYINT UNSIGNED DEFAULT NULL,
    `confidence_after` TINYINT UNSIGNED DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ic_tenant` (`tenant_id`),
    INDEX `idx_ic_store` (`store_id`),
    INDEX `idx_ic_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. НОВА ТАБЛИЦА: invoice_templates (OCR KB кеш)
-- ============================================================
CREATE TABLE IF NOT EXISTS `invoice_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `supplier_eik` VARCHAR(13) NOT NULL COMMENT 'ЕИК на доставчик (9 или 13 цифри)',
    `template_json` JSON DEFAULT NULL COMMENT 'Gemini OCR шаблон за кеширане',
    `account_mapping` JSON DEFAULT NULL COMMENT 'Mapping към счетоводни сметки',
    `confidence_score` DECIMAL(3,2) DEFAULT NULL,
    `uses_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_used_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_it_tenant_eik` (`tenant_id`, `supplier_eik`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. Проверка: sale_items.cost_price (С14 миграция)
-- ============================================================
CALL safe_add_column('sale_items', 'cost_price', 'DECIMAL(10,2) DEFAULT NULL COMMENT "Snapshot доставна цена при продажба"');

-- ============================================================
-- 7. Проверка: products.image (С16)
-- ============================================================
CALL safe_add_column('products', 'image', 'VARCHAR(500) DEFAULT NULL');

-- ============================================================
-- 8. Проверка: ai_image_jobs (С16)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ai_image_jobs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `job_type` ENUM('bg_removal','tryon_man','tryon_woman','tryon_child','tryon_teen_m','tryon_teen_f') NOT NULL,
    `fal_request_id` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `original_image` VARCHAR(500) DEFAULT NULL,
    `result_image` VARCHAR(500) DEFAULT NULL,
    `cost_usd` DECIMAL(8,4) DEFAULT NULL,
    `price_eur` DECIMAL(8,4) DEFAULT NULL,
    `prompt_used` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_aij_tenant` (`tenant_id`),
    INDEX `idx_aij_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. Проверка: tenants AI credits (С16)
-- ============================================================
CALL safe_add_column('tenants', 'ai_credits_bg',        'INT NOT NULL DEFAULT 50');
CALL safe_add_column('tenants', 'ai_credits_tryon',     'INT NOT NULL DEFAULT 5');
CALL safe_add_column('tenants', 'ai_credits_bg_total',  'INT NOT NULL DEFAULT 50');
CALL safe_add_column('tenants', 'ai_credits_tryon_total','INT NOT NULL DEFAULT 5');

-- ============================================================
-- CLEANUP
-- ============================================================
DROP PROCEDURE IF EXISTS safe_add_column;

-- ============================================================
-- SCHEMA FROZEN. От тук нататък — САМО ALTER TABLE миграции.
-- ============================================================
