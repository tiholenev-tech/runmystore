-- S143: Добавяне на 4 нови колони за богатите филтри в products-v2.php
-- 1. products.gender    — Пол (Мъжко/Женско/Детско/Унисекс)
-- 2. products.season    — Сезон (Лято/Зима/Преходен/Целогодишно)
-- 3. products.brand     — Марка (Nike, Adidas, Zara...)
-- 4. inventory.last_counted_at — Дата на последно преброяване (за Преброен/Непреброен филтър)
--
-- Идемпотентно — ако вече съществуват, прескача.
-- Безопасно — само ADD, без DROP/MODIFY.

-- ─── 1. products.gender ───
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'gender'
);
SET @sql := IF(@col_exists = 0,
  "ALTER TABLE products ADD COLUMN gender ENUM('male','female','kids','unisex') DEFAULT NULL AFTER composition",
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── 2. products.season ───
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'season'
);
SET @sql := IF(@col_exists = 0,
  "ALTER TABLE products ADD COLUMN season ENUM('summer','winter','transitional','all_year') DEFAULT NULL AFTER gender",
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── 3. products.brand ───
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'brand'
);
SET @sql := IF(@col_exists = 0,
  "ALTER TABLE products ADD COLUMN brand VARCHAR(80) DEFAULT NULL AFTER season",
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── 4. inventory.last_counted_at ───
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'inventory'
    AND COLUMN_NAME = 'last_counted_at'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE inventory ADD COLUMN last_counted_at DATETIME DEFAULT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── Индекси за филтриране (по-бързи queries) ───
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'idx_products_gender'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX idx_products_gender ON products(tenant_id, gender)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'idx_products_season'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX idx_products_season ON products(tenant_id, season)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'idx_products_brand'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX idx_products_brand ON products(tenant_id, brand)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── Готово ───
SELECT 'S143 миграция: gender + season + brand + last_counted_at + 3 индекса добавени' AS status;
