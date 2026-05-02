-- ════════════════════════════════════════════════════════════
-- S92.AIBRAIN.PHASE1 — ai_brain_queue UP migration
-- Reference: SIMPLE_MODE_BIBLE.md §4.5.3 + §16.1
-- Phase 1 = Reactive only; Phase 2 will read/write rows here.
-- Idempotent: CREATE TABLE IF NOT EXISTS + INSERT IGNORE.
-- ════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS ai_brain_queue (
  id               INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id        INT NOT NULL,
  user_id          INT NOT NULL,
  insight_id       INT NULL                    COMMENT 'FK to ai_insights when item came from insights pipeline',
  type             ENUM(
                     'variation_reconcile',
                     'confidence_nudge',
                     'reconciliation',
                     'stock_alert',
                     'order_suggestion',
                     'review_check'
                   ) NOT NULL,
  priority         TINYINT          DEFAULT 50  COMMENT '1-100; higher = surface first',
  message_text     TEXT NOT NULL                COMMENT 'What AI speaks to Пешо',
  action_data      JSON NULL                    COMMENT 'Payload executed when user accepts',
  status           ENUM('pending','snoozed','dismissed','done')
                                  DEFAULT 'pending',
  snooze_until     DATETIME NULL,
  created_at       DATETIME         DEFAULT CURRENT_TIMESTAMP,
  ttl_hours        INT              DEFAULT 48  COMMENT 'Auto-dismiss after created_at + ttl_hours (cron 03:00)',
  escalation_level TINYINT          DEFAULT 0   COMMENT '0/1/2 — drives pulse rate on the pill',
  INDEX idx_aibq_tenant_user_status_priority (tenant_id, user_id, status, priority),
  INDEX idx_aibq_created_ttl                  (created_at, ttl_hours)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Track this migration even though the file name doesn't match the
-- YYYYMMDD_NNN pattern auto-scanned by lib/Migrator.php (manually applied).
INSERT IGNORE INTO schema_migrations
  (version, name, checksum, applied_at, applied_by, execution_time_ms, rollback_sql)
VALUES
  ('s92_aibrain',
   'S92.AIBRAIN.PHASE1: ai_brain_queue table',
   '',
   NOW(),
   'manual',
   0,
   NULL);
