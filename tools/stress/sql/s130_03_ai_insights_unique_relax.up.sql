-- s130_03_ai_insights_unique_relax.up.sql
-- Bugfix 3 (от STRESS_BUILD_PLAN ред 56) — UNIQUE relax с date bucket.
-- Безопасно за прилагане върху production runmystore И sandbox runmystore_stress_sandbox.
--
-- Цел: ON DUPLICATE KEY-ът да се hit-ва само в рамките на 1 ден,
-- за да позволим нови insights per topic per ден.
--
-- ROLLBACK: s130_03_ai_insights_unique_relax.down.sql
--
-- ИДЕМПОТЕНТНОСТ (S133.STRESS):
--   - Стъпка 1: ADD COLUMN created_at_bucket — пропуска ако вече съществува.
--   - Стъпка 2: DROP INDEX за двете възможни имена (uniq_* и uq_*) — пропуска ако
--     не съществува. Това решава bug-а в S130 версията: DROP INDEX за име,
--     което не съществува, abort-ваше batch-а и финалните CREATE INDEX-и не се
--     изпълняваха. Сега използваме prepared statement + information_schema.
--   - Стъпки 3-4: ADD INDEX — пропуска ако вече съществува.

-- ─── Стъпка 1: created_at_bucket колона (idempotent) ──────────────────────
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'ai_insights'
      AND column_name = 'created_at_bucket'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE ai_insights ADD COLUMN created_at_bucket DATE GENERATED ALWAYS AS (DATE(created_at)) STORED AFTER created_at',
    'SELECT 1 AS noop_created_at_bucket_exists'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── Стъпка 2a: DROP стария UNIQUE с име uniq_tenant_store_topic (ако exists) ──
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'ai_insights'
      AND index_name = 'uniq_tenant_store_topic'
);
SET @sql := IF(@idx_exists > 0,
    'ALTER TABLE ai_insights DROP INDEX uniq_tenant_store_topic',
    'SELECT 1 AS noop_uniq_tenant_store_topic_absent'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── Стъпка 2b: DROP алтернативното име uq_tenant_store_topic (ако exists) ──
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'ai_insights'
      AND index_name = 'uq_tenant_store_topic'
);
SET @sql := IF(@idx_exists > 0,
    'ALTER TABLE ai_insights DROP INDEX uq_tenant_store_topic',
    'SELECT 1 AS noop_uq_tenant_store_topic_absent'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── Стъпка 3: ADD UNIQUE INDEX uniq_tenant_store_topic_day (idempotent) ──
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'ai_insights'
      AND index_name = 'uniq_tenant_store_topic_day'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE ai_insights ADD UNIQUE INDEX uniq_tenant_store_topic_day (tenant_id, store_id, topic_id, created_at_bucket)',
    'SELECT 1 AS noop_uniq_day_exists'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── Стъпка 4: ADD INDEX idx_tenant_store_topic_bucket (idempotent) ──
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'ai_insights'
      AND index_name = 'idx_tenant_store_topic_bucket'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE ai_insights ADD INDEX idx_tenant_store_topic_bucket (tenant_id, store_id, topic_id, created_at_bucket)',
    'SELECT 1 AS noop_idx_bucket_exists'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
