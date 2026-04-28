-- S88.BUG#4: Rollback material column.
-- Idempotent: skips if column missing.

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'material'
);
SET @sql := IF(@col_exists = 1,
  'ALTER TABLE products DROP COLUMN material',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
