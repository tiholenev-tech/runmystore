-- Rollback за s130_03_ai_insights_unique_relax.up.sql

ALTER TABLE ai_insights DROP INDEX uniq_tenant_store_topic_day;
ALTER TABLE ai_insights DROP INDEX idx_tenant_store_topic_bucket;
ALTER TABLE ai_insights DROP COLUMN created_at_bucket;

ALTER TABLE ai_insights
    ADD UNIQUE INDEX uniq_tenant_store_topic
        (tenant_id, store_id, topic_id);
