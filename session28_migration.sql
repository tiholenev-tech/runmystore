-- ═══════════════════════════════════════════════════════════
-- ai_advice_log — Лог на AI съвети + реакция на потребителя
-- RunMyStore.ai С28 — SafetyNet
-- Пусни на сървъра: mysql -u runmystore -p runmystore < session28_migration.sql
-- ═══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `ai_advice_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Кой
    `tenant_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    
    -- Какво каза Пешо
    `user_message` TEXT NOT NULL,
    
    -- Какво каза AI (суров отговор от Gemini)
    `ai_response_raw` TEXT NOT NULL,
    
    -- Какво видя Пешо (след SafetyNet филтрите)
    `ai_response_filtered` TEXT NOT NULL,
    
    -- Какви филтри са приложени
    -- JSON масив, напр: ["discount_capped","panic_word_replaced"]
    `filters_applied` JSON NULL,
    
    -- Тип съвет (за mute логика и месечен отчет)
    -- restock, zombie, margin, price_change, promotion, delivery, vip, general
    `advice_type` VARCHAR(50) DEFAULT 'general',
    
    -- За кой артикул (NULL ако е общ въпрос)
    `product_id` INT UNSIGNED NULL,
    
    -- Какво направи Пешо
    `user_action` ENUM('pending','accepted','rejected','muted') DEFAULT 'pending',
    `user_action_at` TIMESTAMP NULL DEFAULT NULL,
    
    -- Feedback: cron попълва след 3-7 дни за accepted съвети
    `outcome_status` ENUM('pending','success','failed','irrelevant') DEFAULT 'pending',
    `baseline_value` DECIMAL(10,2) NULL,
    `result_value` DECIMAL(10,2) NULL,
    `outcome_checked_at` TIMESTAMP NULL DEFAULT NULL,
    
    -- Mute логика: MD5 на (advice_type + product_id или advice_type alone)
    `topic_hash` VARCHAR(64) NULL,
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Индекси
    INDEX `idx_tenant_date` (`tenant_id`, `created_at`),
    INDEX `idx_tenant_topic` (`tenant_id`, `topic_hash`, `user_action`),
    INDEX `idx_outcome_pending` (`outcome_status`, `created_at`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
