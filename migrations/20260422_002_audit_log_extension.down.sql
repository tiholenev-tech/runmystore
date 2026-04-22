-- Rollback S79.AUDIT.EXT + CRON_HEARTBEATS
DROP TABLE IF EXISTS cron_heartbeats;
ALTER TABLE audit_log MODIFY COLUMN action ENUM('create','update','delete') NOT NULL;
ALTER TABLE audit_log DROP INDEX idx_store;
ALTER TABLE audit_log DROP INDEX idx_source;
ALTER TABLE audit_log DROP COLUMN user_agent;
ALTER TABLE audit_log DROP COLUMN source_detail;
ALTER TABLE audit_log DROP COLUMN source;
ALTER TABLE audit_log DROP COLUMN store_id;
