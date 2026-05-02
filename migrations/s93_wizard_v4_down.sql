-- ════════════════════════════════════════════════════════════════════
-- S93.WIZARD.V4 — rollback
-- Reverses s93_wizard_v4_up.sql in opposite order: 4 → 3 → 2 → 1.
-- Note: short_code backfilled values се загубват — irreversible by design.
-- ════════════════════════════════════════════════════════════════════

-- 3. Drop tenants.short_code
ALTER TABLE tenants
    DROP COLUMN IF EXISTS short_code;

-- 2. Drop voice_command_log table
DROP TABLE IF EXISTS voice_command_log;

-- 1. Drop products columns + index
ALTER TABLE products
    DROP INDEX IF EXISTS idx_confidence;

ALTER TABLE products
    DROP COLUMN IF EXISTS created_via,
    DROP COLUMN IF EXISTS source_template_id,
    DROP COLUMN IF EXISTS confidence_score;
