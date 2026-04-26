-- S80.DIAG: добавя missing колони в seed_oracle.
-- Idempotent чрез INFORMATION_SCHEMA проверки, без IF NOT EXISTS, без DELIMITER.

-- module_name
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'seed_oracle'
                      AND COLUMN_NAME = 'module_name');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE seed_oracle ADD COLUMN module_name VARCHAR(60) NOT NULL DEFAULT ''insights'' AFTER scenario_code',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- category
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'seed_oracle'
                      AND COLUMN_NAME = 'category');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE seed_oracle ADD COLUMN category ENUM(''A'',''B'',''C'',''D'') NOT NULL DEFAULT ''B'' AFTER expected_topic',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- is_active
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'seed_oracle'
                      AND COLUMN_NAME = 'is_active');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE seed_oracle ADD COLUMN is_active TINYINT(1) DEFAULT 1',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deprecated_at
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'seed_oracle'
                      AND COLUMN_NAME = 'deprecated_at');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE seed_oracle ADD COLUMN deprecated_at DATETIME NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index idx_module_category
SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'seed_oracle'
                      AND INDEX_NAME = 'idx_module_category');
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_module_category ON seed_oracle(module_name, category)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index idx_topic
SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'seed_oracle'
                      AND INDEX_NAME = 'idx_topic');
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_topic ON seed_oracle(expected_topic)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
