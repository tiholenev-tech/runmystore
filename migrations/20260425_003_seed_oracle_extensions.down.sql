-- Conditional drops
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND INDEX_NAME = 'idx_module_category');
SET @sql := IF(@col_exists > 0, 'DROP INDEX idx_module_category ON seed_oracle', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND INDEX_NAME = 'idx_topic');
SET @sql := IF(@col_exists > 0, 'DROP INDEX idx_topic ON seed_oracle', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'deprecated_at');
SET @sql := IF(@col_exists > 0, 'ALTER TABLE seed_oracle DROP COLUMN deprecated_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'is_active');
SET @sql := IF(@col_exists > 0, 'ALTER TABLE seed_oracle DROP COLUMN is_active', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'category');
SET @sql := IF(@col_exists > 0, 'ALTER TABLE seed_oracle DROP COLUMN category', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seed_oracle' AND COLUMN_NAME = 'module_name');
SET @sql := IF(@col_exists > 0, 'ALTER TABLE seed_oracle DROP COLUMN module_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
