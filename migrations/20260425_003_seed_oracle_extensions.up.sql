-- ═══════════════════════════════════════════════════════════════
-- 20260425_001_seed_oracle_extensions (UP)
-- S80.DIAG.STEP1 — defensive ALTER на seed_oracle
-- Добавя само липсващите колони (idempotent, използва INFORMATION_SCHEMA)
-- Не пипа съществуващи S79.INSIGHTS колони (scenario_code, expected_topic,
-- scenario_description, fixture_sql, ...).
-- ═══════════════════════════════════════════════════════════════

DELIMITER $$

DROP PROCEDURE IF EXISTS s80_alter_seed_oracle$$
CREATE PROCEDURE s80_alter_seed_oracle()
BEGIN
    DECLARE col_exists INT DEFAULT 0;

    -- module_name (multi-module support: 'insights', 'onboarding', 'chat', ...)
    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'module_name';
    IF col_exists = 0 THEN
        ALTER TABLE seed_oracle
          ADD COLUMN module_name VARCHAR(60) NOT NULL DEFAULT 'insights' AFTER scenario_code;
    END IF;

    -- category (A/B/C/D)
    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'category';
    IF col_exists = 0 THEN
        ALTER TABLE seed_oracle
          ADD COLUMN category ENUM('A','B','C','D') NOT NULL DEFAULT 'B' AFTER expected_topic;
    END IF;

    -- expected_should_appear (1 = трябва да се появи, 0 = не трябва)
    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'expected_should_appear';
    IF col_exists = 0 THEN
        ALTER TABLE seed_oracle
          ADD COLUMN expected_should_appear TINYINT(1) NOT NULL DEFAULT 1 AFTER category;
    END IF;

    -- verification_type (8 видове проверки от DIAGNOSTIC_PROTOCOL.md)
    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'verification_type';
    IF col_exists = 0 THEN
        ALTER TABLE seed_oracle
          ADD COLUMN verification_type ENUM(
              'product_in_items','rank_within','pair_match','seller_match',
              'value_range','exists_only','not_exists','count_match'
          ) NOT NULL DEFAULT 'exists_only' AFTER expected_should_appear;
    END IF;

    -- verification_payload (JSON: {product_id, rank_max, min, max, ...})
    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'verification_payload';
    IF col_exists = 0 THEN
        ALTER TABLE seed_oracle
          ADD COLUMN verification_payload JSON NULL AFTER verification_type;
    END IF;

    -- is_active (soft-deprecate pattern, никога не трием сценарии)
    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'is_active';
    IF col_exists = 0 THEN
        ALTER TABLE seed_oracle ADD COLUMN is_active TINYINT(1) DEFAULT 1;
    END IF;

    -- deprecated_at
    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'deprecated_at';
    IF col_exists = 0 THEN
        ALTER TABLE seed_oracle ADD COLUMN deprecated_at DATETIME NULL;
    END IF;

    -- created_at (ако липсва)
    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'created_at';
    IF col_exists = 0 THEN
        ALTER TABLE seed_oracle ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
    END IF;

    -- Indexes (defensive — IF NOT EXISTS работи за индекси в MySQL 8.0+)
    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND INDEX_NAME = 'idx_module_category';
    IF col_exists = 0 THEN
        ALTER TABLE seed_oracle ADD INDEX idx_module_category (module_name, category);
    END IF;

    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND INDEX_NAME = 'idx_topic';
    IF col_exists = 0 THEN
        ALTER TABLE seed_oracle ADD INDEX idx_topic (expected_topic);
    END IF;
END$$

DELIMITER ;

CALL s80_alter_seed_oracle();
DROP PROCEDURE s80_alter_seed_oracle;

-- Verify (резултатът отива в migrate.php log):
SELECT
    COUNT(*) AS total_rows,
    SUM(CASE WHEN category IS NOT NULL THEN 1 ELSE 0 END) AS rows_with_category,
    SUM(CASE WHEN verification_type IS NOT NULL THEN 1 ELSE 0 END) AS rows_with_verify_type
FROM seed_oracle;
