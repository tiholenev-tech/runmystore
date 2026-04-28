-- S88.BUG#4: Add material column to products for fuzzy-match category in wizard.
-- Idempotent: skips if column already exists. Uses INFORMATION_SCHEMA guard.

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'material'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE products ADD COLUMN material VARCHAR(50) DEFAULT NULL AFTER composition',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
