-- s130_03_ai_insights_unique_relax.up.sql
-- Bugfix 3 (от STRESS_BUILD_PLAN ред 56) — UNIQUE relax с date bucket.
-- ПРИЛАГА СЕ САМО ВЪРХУ runmystore_stress_sandbox (НЕ production).
--
-- Цел: ON DUPLICATE KEY-ът да се hit-ва само в рамките на 1 ден,
-- за да позволим нови insights per topic per ден.
--
-- ROLLBACK: s130_03_ai_insights_unique_relax.down.sql

ALTER TABLE ai_insights
    ADD COLUMN created_at_bucket DATE
        GENERATED ALWAYS AS (DATE(created_at)) STORED AFTER created_at;

-- Drop стария UNIQUE (ако е останал) — IGNORE ако вече няма
ALTER TABLE ai_insights
    DROP INDEX uniq_tenant_store_topic;

ALTER TABLE ai_insights
    ADD UNIQUE INDEX uniq_tenant_store_topic_day
        (tenant_id, store_id, topic_id, created_at_bucket);

ALTER TABLE ai_insights
    ADD INDEX idx_tenant_store_topic_bucket
        (tenant_id, store_id, topic_id, created_at_bucket);
