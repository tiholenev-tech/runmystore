-- Rollback S79.DB Soft Delete migration (reverse order)

-- categories
ALTER TABLE categories DROP INDEX idx_deleted_at;
ALTER TABLE categories DROP COLUMN delete_reason;
ALTER TABLE categories DROP COLUMN deleted_by;
ALTER TABLE categories DROP COLUMN deleted_at;

-- stores
ALTER TABLE stores DROP INDEX idx_deleted_at;
ALTER TABLE stores DROP COLUMN delete_reason;
ALTER TABLE stores DROP COLUMN deleted_by;
ALTER TABLE stores DROP COLUMN deleted_at;

-- users
ALTER TABLE users DROP INDEX idx_deleted_at;
ALTER TABLE users DROP COLUMN delete_reason;
ALTER TABLE users DROP COLUMN deleted_by;
ALTER TABLE users DROP COLUMN deleted_at;

-- customers
ALTER TABLE customers DROP INDEX idx_deleted_at;
ALTER TABLE customers DROP COLUMN delete_reason;
ALTER TABLE customers DROP COLUMN deleted_by;
ALTER TABLE customers DROP COLUMN deleted_at;

-- suppliers
ALTER TABLE suppliers DROP INDEX idx_deleted_at;
ALTER TABLE suppliers DROP COLUMN delete_reason;
ALTER TABLE suppliers DROP COLUMN deleted_by;
ALTER TABLE suppliers DROP COLUMN deleted_at;

