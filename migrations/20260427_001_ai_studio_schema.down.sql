-- S82.STUDIO.BACKEND rollback. Reverses 20260427_001_ai_studio_schema.up.sql.
-- Apply order is the reverse of up: drop seed/templates -> drop new tables -> revert ALTERs.
-- Existing tenants.ai_credits_bg / ai_credits_tryon columns + ai_image_usage table are NOT touched
-- (they predate this migration).

-- ───────────────────────────────────────────────────────────────────────
-- 1. Drop new tables (cascades dependent rows in this migration).
-- ───────────────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS ai_prompt_templates;
DROP TABLE IF EXISTS ai_spend_log;
DROP TABLE IF EXISTS ai_credits_balance;

-- ───────────────────────────────────────────────────────────────────────
-- 2. tenants — drop monthly counters + included allowances.
-- ───────────────────────────────────────────────────────────────────────
ALTER TABLE tenants
  DROP COLUMN included_bg_per_month,
  DROP COLUMN included_desc_per_month,
  DROP COLUMN included_magic_per_month,
  DROP COLUMN bg_used_this_month,
  DROP COLUMN desc_used_this_month,
  DROP COLUMN magic_used_this_month;

-- ───────────────────────────────────────────────────────────────────────
-- 3. products — drop AI category + content fields + index.
-- ───────────────────────────────────────────────────────────────────────
ALTER TABLE products
  DROP INDEX idx_ai_category,
  DROP COLUMN ai_category,
  DROP COLUMN ai_subtype,
  DROP COLUMN ai_description,
  DROP COLUMN ai_magic_image;
