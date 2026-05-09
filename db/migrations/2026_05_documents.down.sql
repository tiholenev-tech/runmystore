-- ════════════════════════════════════════════════════════════════════════════
-- ROLLBACK: 2026_05_documents.down.sql
-- ВНИМАНИЕ: триене на всички документи и партньори. Backup задължителен!
-- ════════════════════════════════════════════════════════════════════════════

-- Reverse ALTER (sales)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME='sale_mode');
SET @sql = IF(@col_exists=1, 'ALTER TABLE sales DROP COLUMN sale_mode', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME='primary_document_id');
SET @sql = IF(@col_exists=1, 'ALTER TABLE sales DROP COLUMN primary_document_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME='partner_id');
SET @sql = IF(@col_exists=1, 'ALTER TABLE sales DROP COLUMN partner_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Drop tables (reverse FK order)
DROP TABLE IF EXISTS document_items;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS document_series_changes;
DROP TABLE IF EXISTS document_series;
DROP TABLE IF EXISTS partner_aliases;
DROP TABLE IF EXISTS partners;
DROP TABLE IF EXISTS eik_cache;
