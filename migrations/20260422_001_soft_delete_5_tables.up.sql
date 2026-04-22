-- S79.DB Soft Delete migration (5 tables)
-- Adds: deleted_at, deleted_by, delete_reason + idx_deleted_at
-- Idempotent via Migrator's idempotentSkip (Duplicate column name)

-- suppliers
ALTER TABLE suppliers ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE suppliers ADD COLUMN deleted_by INT UNSIGNED NULL;
ALTER TABLE suppliers ADD COLUMN delete_reason VARCHAR(200) NULL;
ALTER TABLE suppliers ADD INDEX idx_deleted_at (deleted_at);

-- customers
ALTER TABLE customers ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE customers ADD COLUMN deleted_by INT UNSIGNED NULL;
ALTER TABLE customers ADD COLUMN delete_reason VARCHAR(200) NULL;
ALTER TABLE customers ADD INDEX idx_deleted_at (deleted_at);

-- users
ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE users ADD COLUMN deleted_by INT UNSIGNED NULL;
ALTER TABLE users ADD COLUMN delete_reason VARCHAR(200) NULL;
ALTER TABLE users ADD INDEX idx_deleted_at (deleted_at);

-- stores
ALTER TABLE stores ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE stores ADD COLUMN deleted_by INT UNSIGNED NULL;
ALTER TABLE stores ADD COLUMN delete_reason VARCHAR(200) NULL;
ALTER TABLE stores ADD INDEX idx_deleted_at (deleted_at);

-- categories
ALTER TABLE categories ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE categories ADD COLUMN deleted_by INT UNSIGNED NULL;
ALTER TABLE categories ADD COLUMN delete_reason VARCHAR(200) NULL;
ALTER TABLE categories ADD INDEX idx_deleted_at (deleted_at);

