-- S79.AUDIT.EXT + CRON_HEARTBEATS
-- 1) Extend audit_log: store_id, source, source_detail, user_agent
ALTER TABLE audit_log ADD COLUMN store_id INT UNSIGNED NULL AFTER user_id;
ALTER TABLE audit_log ADD COLUMN source ENUM('ui','ai','api','cron','system') NOT NULL DEFAULT 'ui' AFTER action;
ALTER TABLE audit_log ADD COLUMN source_detail VARCHAR(200) NULL AFTER source;
ALTER TABLE audit_log ADD COLUMN user_agent VARCHAR(255) NULL AFTER source_detail;
ALTER TABLE audit_log ADD INDEX idx_source (source);
ALTER TABLE audit_log ADD INDEX idx_store (store_id);
UPDATE audit_log SET source='ui' WHERE source IS NULL OR source='';

-- 2) Extend action ENUM with cron_run, ai_action, system_event
ALTER TABLE audit_log MODIFY COLUMN action ENUM('create','update','delete','cron_run','ai_action','system_event') NOT NULL;

-- 3) cron_heartbeats table
CREATE TABLE IF NOT EXISTS cron_heartbeats (
  job_name VARCHAR(100) NOT NULL PRIMARY KEY,
  last_run_at DATETIME NOT NULL,
  last_status ENUM('ok','error') NOT NULL,
  last_error TEXT NULL,
  last_duration_ms INT NOT NULL DEFAULT 0,
  expected_interval_minutes INT NOT NULL DEFAULT 15,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
