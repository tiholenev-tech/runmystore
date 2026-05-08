-- ════════════════════════════════════════════════════════════════════
-- S114.AIBRAIN_AUDIT — ai_brain_queue Phase 2 schema enhancement
-- DRAFT ONLY — DO NOT APPLY ON LIVE DB.
--
-- IMPORTANT CONTEXT:
--   The base `ai_brain_queue` table ALREADY EXISTS on production
--   (created S92.AIBRAIN.PHASE1, see migrations/s92_aibrain_up.sql).
--   This file proposes ADDITIVE enhancements for Phase 2 producers
--   (deliveries integration, voice→text async, image analysis).
--
-- Migration plan:
--   1. ALTER TABLE adds new ENUM values (additive — safe)
--   2. ALTER TABLE adds new columns (NULLable — safe)
--   3. CREATE INDEX for new query patterns
--   4. INSERT IGNORE into schema_migrations for tracking
--
-- All steps are idempotent and reversible (see s116_aibrain_queue_p2_down.sql
-- companion file — to be drafted in S116).
-- ════════════════════════════════════════════════════════════════════

-- ──────────────────────────────────────────────────────────────────
-- 1. EXTEND `type` ENUM with Phase 2 producers
--
-- Existing values (s92): variation_reconcile, confidence_nudge,
--                        reconciliation, stock_alert, order_suggestion,
--                        review_check
--
-- Adding (Phase 2):     defective_detected   — proactive defective flag
--                       voice_transcribe     — async STT job
--                       image_analyze        — async vision (AI Studio + deliveries)
--                       reorder_suggest      — auto-suggest reorder qty (RWQ #82)
--                       category_drift       — product mis-categorization
--                       price_anomaly        — delivery cost vs historical
--                       batch_summary        — nightly digest
-- ──────────────────────────────────────────────────────────────────
ALTER TABLE ai_brain_queue
  MODIFY COLUMN type ENUM(
    -- Phase 1 (s92, existing)
    'variation_reconcile',
    'confidence_nudge',
    'reconciliation',
    'stock_alert',
    'order_suggestion',
    'review_check',
    -- Phase 2 (s116 + s117)
    'defective_detected',
    'voice_transcribe',
    'image_analyze',
    'reorder_suggest',
    'category_drift',
    'price_anomaly',
    'batch_summary'
  ) NOT NULL;

-- ──────────────────────────────────────────────────────────────────
-- 2. ADD store_id (NULL when tenant-wide; else scoped to a store)
-- Existing schema scopes by (tenant_id, user_id) only. For deliveries
-- triggers and inventory anomalies the relevant unit is the store.
-- ──────────────────────────────────────────────────────────────────
ALTER TABLE ai_brain_queue
  ADD COLUMN store_id INT NULL AFTER tenant_id;

-- ──────────────────────────────────────────────────────────────────
-- 3. ADD async-job lifecycle fields
-- Phase 1 jobs are 'pending'/'snoozed'/'dismissed'/'done' — fine for
-- proactive items the AI tells Pешо. But Phase 2 adds JOBS the queue
-- itself owns (voice_transcribe, image_analyze) where a worker has to
-- pick the row, lock it, run, and write a result back.
-- ──────────────────────────────────────────────────────────────────
ALTER TABLE ai_brain_queue
  MODIFY COLUMN status ENUM(
    'pending','snoozed','dismissed','done',
    -- Phase 2 worker states
    'processing','failed','skipped'
  ) NOT NULL DEFAULT 'pending',
  ADD COLUMN attempts      TINYINT  NOT NULL DEFAULT 0    AFTER status,
  ADD COLUMN max_attempts  TINYINT  NOT NULL DEFAULT 3    AFTER attempts,
  ADD COLUMN started_at    DATETIME NULL                  AFTER max_attempts,
  ADD COLUMN completed_at  DATETIME NULL                  AFTER started_at,
  ADD COLUMN error_msg     TEXT     NULL                  AFTER completed_at,
  ADD COLUMN result_data   JSON     NULL
       COMMENT 'Worker output (transcript text, vision tags, suggested qty, etc.)'
       AFTER error_msg;

-- ──────────────────────────────────────────────────────────────────
-- 4. ADD scheduled_at for delayed/retry execution
-- Today queue items are immediately visible. Phase 2 needs scheduled
-- execution: e.g. retry with exponential backoff, or delay an
-- "image_analyze" job until the upload completes.
-- ──────────────────────────────────────────────────────────────────
ALTER TABLE ai_brain_queue
  ADD COLUMN scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
       COMMENT 'Earliest time worker may pick this row'
       AFTER created_at;

-- ──────────────────────────────────────────────────────────────────
-- 5. ADD source FK for traceability
-- Existing column `insight_id` ties queue item back to ai_insights.
-- For deliveries/voice/image producers we need:
--   source_table  ENUM('deliveries','voice_blob','image_upload',
--                      'sale_item','inventory_count_line',
--                      'purchase_order','ai_insight')
--   source_id     INT
-- This avoids a wide schema with N FK columns.
-- ──────────────────────────────────────────────────────────────────
ALTER TABLE ai_brain_queue
  ADD COLUMN source_table VARCHAR(48) NULL AFTER insight_id,
  ADD COLUMN source_id    INT         NULL AFTER source_table;

-- ──────────────────────────────────────────────────────────────────
-- 6. NEW INDEXES for Phase 2 access patterns
-- ──────────────────────────────────────────────────────────────────
-- Worker pull pattern: pick highest-priority pending|processing rows
-- whose scheduled_at ≤ NOW(), per type (workers may be type-specific).
ALTER TABLE ai_brain_queue
  ADD INDEX idx_aibq_worker_pull (status, scheduled_at, priority, type);

-- Source traceability: find all queue items born from a given deliveries row
ALTER TABLE ai_brain_queue
  ADD INDEX idx_aibq_source (source_table, source_id);

-- Store-scoped fetch: life-board mini-dashboard "today's tasks" per store
ALTER TABLE ai_brain_queue
  ADD INDEX idx_aibq_store_status (tenant_id, store_id, status, priority);

-- ──────────────────────────────────────────────────────────────────
-- 7. SCHEMA_MIGRATIONS tracking
-- ──────────────────────────────────────────────────────────────────
INSERT IGNORE INTO schema_migrations
  (version, name, checksum, applied_at, applied_by, execution_time_ms, rollback_sql)
VALUES
  ('s116_aibrain_queue_p2',
   'S116.AIBRAIN.QUEUE.P2: type enum + worker fields + source traceability + indexes',
   '',
   NOW(),
   'manual',
   0,
   NULL);

-- ════════════════════════════════════════════════════════════════════
-- POST-MIGRATION VERIFICATION QUERIES
-- (run after apply, NOT part of migration itself)
-- ════════════════════════════════════════════════════════════════════
-- SHOW CREATE TABLE ai_brain_queue\G
--
-- Expected: 25 columns (was 13 in s92), 5 indexes (was 2 in s92).
--
-- Smoke-test row insert (rolls back via SAVEPOINT in caller):
-- INSERT INTO ai_brain_queue
--   (tenant_id, store_id, user_id, type, message_text, source_table, source_id)
-- VALUES (1, 1, 1, 'voice_transcribe', 'Async STT smoke',
--         'voice_blob', 99999);
--
-- Worker pick simulation:
-- SELECT id, type, priority, scheduled_at
-- FROM ai_brain_queue
-- WHERE status='pending' AND scheduled_at <= NOW()
-- ORDER BY priority DESC, scheduled_at ASC
-- LIMIT 10
-- FOR UPDATE SKIP LOCKED;   -- requires MySQL 8.0+; document fallback for 5.7
