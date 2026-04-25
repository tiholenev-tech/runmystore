-- ═══════════════════════════════════════════════════════════════
-- 20260425_001_seed_oracle_extensions (DOWN)
-- WARNING: drop колоните само ако rollback е абсолютно нужен.
-- Това ще загуби category/verification_type/payload данни.
-- ═══════════════════════════════════════════════════════════════

DELIMITER $$

DROP PROCEDURE IF EXISTS s80_drop_seed_oracle_cols$$
CREATE PROCEDURE s80_drop_seed_oracle_cols()
BEGIN
    DECLARE col_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND INDEX_NAME = 'idx_module_category';
    IF col_exists > 0 THEN ALTER TABLE seed_oracle DROP INDEX idx_module_category; END IF;

    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND INDEX_NAME = 'idx_topic';
    IF col_exists > 0 THEN ALTER TABLE seed_oracle DROP INDEX idx_topic; END IF;

    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'deprecated_at';
    IF col_exists > 0 THEN ALTER TABLE seed_oracle DROP COLUMN deprecated_at; END IF;

    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'is_active';
    IF col_exists > 0 THEN ALTER TABLE seed_oracle DROP COLUMN is_active; END IF;

    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'verification_payload';
    IF col_exists > 0 THEN ALTER TABLE seed_oracle DROP COLUMN verification_payload; END IF;

    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'verification_type';
    IF col_exists > 0 THEN ALTER TABLE seed_oracle DROP COLUMN verification_type; END IF;

    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'expected_should_appear';
    IF col_exists > 0 THEN ALTER TABLE seed_oracle DROP COLUMN expected_should_appear; END IF;

    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'category';
    IF col_exists > 0 THEN ALTER TABLE seed_oracle DROP COLUMN category; END IF;

    SELECT COUNT(*) INTO col_exists FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'module_name';
    IF col_exists > 0 THEN ALTER TABLE seed_oracle DROP COLUMN module_name; END IF;

    -- created_at оставяме (никога не вреди)
END$$

DELIMITER ;

CALL s80_drop_seed_oracle_cols();
DROP PROCEDURE s80_drop_seed_oracle_cols;
