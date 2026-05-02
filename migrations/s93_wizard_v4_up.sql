-- ════════════════════════════════════════════════════════════════════
-- S93.WIZARD.V4 — schema additions
-- Spec: PRODUCTS_WIZARD_v4_SPEC.md §11 (DB migrations)
--
-- Idempotent (IF NOT EXISTS guards). Safe to re-run.
-- Apply order matters: 1 → 2 → 3 → 4. DOWN reverses 4 → 1.
-- ════════════════════════════════════════════════════════════════════

-- 1. products: confidence_score + source_template_id + created_via
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS confidence_score TINYINT UNSIGNED NOT NULL DEFAULT 95,
    ADD COLUMN IF NOT EXISTS source_template_id INT NULL,
    ADD COLUMN IF NOT EXISTS created_via ENUM(
        'wizard_v4', 'wizard_legacy', 'quick_add', 'import', 'api'
    ) NOT NULL DEFAULT 'wizard_v4';

ALTER TABLE products
    ADD INDEX IF NOT EXISTS idx_confidence (tenant_id, confidence_score);

-- 2. voice_command_log — analytics + cost tracking
CREATE TABLE IF NOT EXISTS voice_command_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    field_type VARCHAR(50) NULL,
    engine ENUM('web_speech', 'whisper', 'hybrid') NOT NULL,
    transcript TEXT NULL,
    confidence DECIMAL(4,3) NULL,
    duration_ms INT UNSIGNED NULL,
    audio_size_bytes INT UNSIGNED NULL,
    cost_usd DECIMAL(8,5) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_user (tenant_id, user_id, created_at),
    INDEX idx_engine (engine, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. tenants: short_code (за SKU prefix)
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS short_code VARCHAR(8) NULL;

-- 4. Backfill short_code за existing tenants (NULL only)
UPDATE tenants
SET short_code = UPPER(LEFT(REGEXP_REPLACE(name, '[^A-Za-zА-Яа-я]', ''), 3))
WHERE short_code IS NULL OR short_code = '';
