-- ============================================
-- RunMyStore.ai — Phase 0 SQL Migration
-- Session 52 | 12.04.2026
-- ALTER tenants + CREATE 4 нови таблици
-- RUN: mysql -u runmystore -p'0okm9ijnSklad!' runmystore < s52_phase0_migration.sql
-- ============================================

-- ══════════════════════════════════════
-- 1. ALTER tenants
-- ══════════════════════════════════════

-- Смяна на plan ENUM: start/growth/pro → free/start/pro
UPDATE tenants SET plan = 'start' WHERE plan = 'growth';

ALTER TABLE tenants 
    MODIFY COLUMN `plan` ENUM('free','start','pro') 
    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free';

-- Ефективен план (trial дава pro)
ALTER TABLE tenants 
    ADD COLUMN `plan_effective` ENUM('free','start','pro') 
    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pro' 
    AFTER `plan`;

-- UI режим
ALTER TABLE tenants 
    ADD COLUMN `ui_mode` ENUM('simple','detailed') 
    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'simple' 
    AFTER `plan_effective`;

-- Допълнителни магазини (PRO: +€9.99/магазин)
ALTER TABLE tenants 
    ADD COLUMN `extra_stores` INT UNSIGNED NOT NULL DEFAULT 0 
    AFTER `ui_mode`;

-- ══════════════════════════════════════
-- 2. ai_insights — Pills/Signals кеш
-- ══════════════════════════════════════
CREATE TABLE IF NOT EXISTS `ai_insights` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `topic_id` VARCHAR(80) NOT NULL COMMENT 'stock_001, promo_when_003, claude_001...',
    `category` VARCHAR(50) NOT NULL COMMENT 'stock, zombie, promo_when, biz_revenue...',
    `grp` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1-6: стока/пари/промо/поръчки/бизнес/операции',
    `module` ENUM('home','products','warehouse','stats','sale') NOT NULL DEFAULT 'home',
    `urgency` ENUM('critical','warning','info','passive') NOT NULL DEFAULT 'info',
    `plan_gate` ENUM('free','start','pro') NOT NULL DEFAULT 'pro',
    `role_gate` VARCHAR(30) NOT NULL DEFAULT 'owner,manager' COMMENT 'owner / owner,manager / all',
    `title` VARCHAR(255) NOT NULL COMMENT 'Pill текст, кратко',
    `detail_text` VARCHAR(500) NULL COMMENT 'По-дълго обяснение за tap/expand',
    `data_json` JSON NULL COMMENT '{"items":[], "product_ids":[], "value":1234.50}',
    `value_numeric` DECIMAL(12,2) NULL COMMENT 'За сортиране по стойност',
    `product_count` INT UNSIGNED NULL COMMENT 'Брой засегнати артикули',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NULL COMMENT 'NULL = до следващия cron цикъл',
    INDEX idx_tenant_store_module (`tenant_id`, `store_id`, `module`, `urgency`),
    INDEX idx_tenant_category (`tenant_id`, `category`),
    INDEX idx_expires (`expires_at`),
    UNIQUE KEY uq_tenant_store_topic (`tenant_id`, `store_id`, `topic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════
-- 3. ai_shown — Дедупликация + cooldown
-- ══════════════════════════════════════
CREATE TABLE IF NOT EXISTS `ai_shown` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `topic_id` VARCHAR(80) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `product_id` INT UNSIGNED NULL COMMENT 'Per-product cooldown',
    `shown_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `action` ENUM('shown','tapped','dismissed','snoozed') NOT NULL DEFAULT 'shown',
    `action_at` DATETIME NULL,
    INDEX idx_cooldown (`tenant_id`, `user_id`, `topic_id`, `shown_at`),
    INDEX idx_category (`tenant_id`, `category`, `action`, `shown_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════
-- 4. search_log — Всяко търсене
-- ══════════════════════════════════════
CREATE TABLE IF NOT EXISTS `search_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `query` VARCHAR(255) NOT NULL,
    `results_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `source` ENUM('products','sale','warehouse') NOT NULL DEFAULT 'products',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_zero (`tenant_id`, `results_count`, `created_at`),
    INDEX idx_tenant_store (`tenant_id`, `store_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════
-- 5. lost_demand — Какво търсят и го нямаме
-- ══════════════════════════════════════
CREATE TABLE IF NOT EXISTS `lost_demand` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL COMMENT 'Кой продавач го записа',
    `query_text` VARCHAR(500) NOT NULL COMMENT 'Свободен текст: бели маратонки 38',
    `source` ENUM('search','voice','manual','barcode_miss') NOT NULL DEFAULT 'search',
    `times` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Колко пъти е търсено',
    `matched_product_id` INT UNSIGNED NULL COMMENT 'Попълва се когато поръчат',
    `resolved` TINYINT(1) NOT NULL DEFAULT 0,
    `resolved_at` DATETIME NULL,
    `first_asked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_asked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_unresolved (`tenant_id`, `resolved`, `last_asked_at`),
    INDEX idx_tenant_store (`tenant_id`, `store_id`, `created_at`),
    INDEX idx_aggregation (`tenant_id`, `query_text`(100), `store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
