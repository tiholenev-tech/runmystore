-- S82.STUDIO.BACKEND: AI Studio schema foundation (3-credit-type + retry/refund + per-category prompts).
-- Backward-compatible: existing tenants.ai_credits_bg / ai_credits_tryon columns + ai_image_usage table remain untouched.
-- 30-day grace period before legacy `ai_credits_balance.credits` column is dropped.
--
-- Discovery (S82.STUDIO.13 baseline): tables ai_spend_log, ai_credits_balance, ai_prompt_templates
-- did NOT exist on disk. They are CREATEd here (task spec uses ALTER but baseline has no rows yet).
-- ai-studio.php (STUDIO.11) keeps reading tenants.ai_credits_bg directly — not broken by this migration.
--
-- Apply order: products -> tenants -> 3 new tables -> plan-defaults seed -> prompt-template seeds.

-- ───────────────────────────────────────────────────────────────────────
-- 1. products: AI Studio category + content fields
-- ───────────────────────────────────────────────────────────────────────
ALTER TABLE products
  ADD COLUMN ai_category    VARCHAR(20)  NULL DEFAULT NULL COMMENT 'clothes|lingerie|jewelry|acc|other',
  ADD COLUMN ai_subtype     VARCHAR(30)  NULL DEFAULT NULL COMMENT 'free-form subtype (bra, dress, ring, ...)',
  ADD COLUMN ai_description TEXT         NULL,
  ADD COLUMN ai_magic_image VARCHAR(500) NULL COMMENT 'AI-generated hero image URL (e.g. nano-banana magic)',
  ADD INDEX idx_ai_category (ai_category);

-- ───────────────────────────────────────────────────────────────────────
-- 2. tenants: included monthly allowances + monthly used counters
--    Existing ai_credits_bg / ai_credits_tryon / *_total stay (frontend reads them).
-- ───────────────────────────────────────────────────────────────────────
ALTER TABLE tenants
  ADD COLUMN included_bg_per_month    INT NOT NULL DEFAULT 0  COMMENT 'Monthly bg-removal allowance from plan',
  ADD COLUMN included_desc_per_month  INT NOT NULL DEFAULT 0  COMMENT 'Monthly description allowance from plan',
  ADD COLUMN included_magic_per_month INT NOT NULL DEFAULT 0  COMMENT 'Monthly magic-image allowance from plan',
  ADD COLUMN bg_used_this_month       INT NOT NULL DEFAULT 0  COMMENT 'Reset by cron-monthly.php on day 1',
  ADD COLUMN desc_used_this_month     INT NOT NULL DEFAULT 0,
  ADD COLUMN magic_used_this_month    INT NOT NULL DEFAULT 0;

-- ───────────────────────────────────────────────────────────────────────
-- 3. ai_credits_balance: purchased / topped-up credits (separate from monthly included).
--    Legacy `credits` column kept as backward-compat reservation (drop after 30 days grace).
-- ───────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ai_credits_balance (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  credits       INT NOT NULL DEFAULT 0 COMMENT 'LEGACY single-pool counter — drop 2026-05-27 (30-day grace)',
  bg_credits    INT NOT NULL DEFAULT 0,
  desc_credits  INT NOT NULL DEFAULT 0,
  magic_credits INT NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tenant (tenant_id),
  CONSTRAINT fk_acb_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────
-- 4. ai_spend_log: per-call cost + status + retry chain.
--    status semantics:
--      completed_paid  — successful, credit consumed
--      retry_free      — retry of an earlier failure, NO credit consumed (Quality Guarantee)
--      refunded_loss   — original failed beyond retry budget; credit refunded; loss eaten by us
-- ───────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ai_spend_log (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NULL,
  product_id      INT UNSIGNED NULL,
  feature         VARCHAR(50)  NOT NULL COMMENT 'bg_remove | description | magic | tryon | color_detect',
  category        VARCHAR(20)  NULL     COMMENT 'lingerie|clothes|jewelry|acc|other (when feature=magic)',
  model           VARCHAR(50)  NULL     COMMENT 'birefnet | nano-banana-2 | nano-banana-pro | gemini-2.5-flash',
  cost_eur        DECIMAL(8,4) NOT NULL DEFAULT 0,
  status          ENUM('completed_paid','retry_free','refunded_loss') NOT NULL DEFAULT 'completed_paid',
  parent_log_id   INT UNSIGNED NULL COMMENT 'FK to first attempt; chains retries to the original',
  attempt_number  INT NOT NULL DEFAULT 1 COMMENT '1=original, 2/3=retries (Quality Guarantee max 2 retries)',
  meta_json       JSON NULL    COMMENT 'free-form payload (input ref, output url, error code, etc.)',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tenant_status_date (tenant_id, status, created_at),
  KEY idx_parent             (parent_log_id),
  KEY idx_tenant_feature     (tenant_id, feature, created_at),
  KEY idx_product            (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────
-- 5. ai_prompt_templates: per-category Gemini prompt with A/B-testing support.
-- ───────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ai_prompt_templates (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category     VARCHAR(20)  NOT NULL COMMENT 'clothes|lingerie|jewelry|acc|other',
  subtype      VARCHAR(30)  NULL     COMMENT 'optional further specialization',
  template     TEXT         NOT NULL COMMENT 'placeholders: {{name}} {{color}} {{size}} {{composition}} {{features}}',
  success_rate DECIMAL(5,2) NULL     COMMENT 'rolling success ratio in % (updated by cron / app-side)',
  usage_count  INT          NOT NULL DEFAULT 0,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  is_ab_test   TINYINT(1)   NOT NULL DEFAULT 0,
  notes        VARCHAR(500) NULL     COMMENT 'why this template, who approved, when',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cat_active (category, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────
-- 6. Plan defaults seed — populate included_*_per_month for existing tenants.
--    BIZ tier mentioned in task spec but not in tenants.plan ENUM; skipped here,
--    will be wired when ENUM is extended in a future migration.
-- ───────────────────────────────────────────────────────────────────────
UPDATE tenants SET
  included_bg_per_month    = 0,
  included_desc_per_month  = 0,
  included_magic_per_month = 0
WHERE plan = 'free';

UPDATE tenants SET
  included_bg_per_month    = 50,
  included_desc_per_month  = 100,
  included_magic_per_month = 5
WHERE plan = 'start';

UPDATE tenants SET
  included_bg_per_month    = 300,
  included_desc_per_month  = 500,
  included_magic_per_month = 20
WHERE plan = 'pro';

-- ───────────────────────────────────────────────────────────────────────
-- 7. Prompt template seeds.
--    Lingerie = real, conservative-language template (Тихол to refine from AI_CREDITS_PRICING_v2.md).
--    Other 4 categories = placeholders awaiting Тихол approval — UI must NOT call magic for them
--    until success_rate IS NULL is replaced with a real value (signal of unverified template).
-- ───────────────────────────────────────────────────────────────────────
INSERT INTO ai_prompt_templates (category, subtype, template, is_active, notes) VALUES
('lingerie', NULL,
 'Generate a tasteful product description for a lingerie / swimwear item. Use elegant, modest language; avoid suggestive or explicit phrasing. Focus on fabric, fit, comfort, and styling occasion. Keep to 2-3 short sentences.\n\nName: {{name}}\nColor: {{color}}\nSize: {{size}}\nComposition: {{composition}}\nFeatures: {{features}}\n\nReturn plain text only — no markdown, no emojis.',
 1,
 'S82.STUDIO.BACKEND seed (placeholder — confirm wording vs AI_CREDITS_PRICING_v2.md before promoting)'),

('clothes', NULL,
 '[PLACEHOLDER — awaiting Тихол approval] Generate a product description for a clothing item.\n\nName: {{name}}\nColor: {{color}}\nSize: {{size}}\nComposition: {{composition}}\nFeatures: {{features}}\n\nReturn plain text only.',
 0,
 'PLACEHOLDER — is_active=0 until approved'),

('jewelry', NULL,
 '[PLACEHOLDER — awaiting Тихол approval] Generate a product description for a jewelry item.\n\nName: {{name}}\nColor / metal: {{color}}\nMaterial: {{composition}}\nFeatures: {{features}}\n\nReturn plain text only.',
 0,
 'PLACEHOLDER — is_active=0 until approved'),

('acc', NULL,
 '[PLACEHOLDER — awaiting Тихол approval] Generate a product description for an accessory (bag, shoe, belt, ...).\n\nName: {{name}}\nColor: {{color}}\nMaterial: {{composition}}\nFeatures: {{features}}\n\nReturn plain text only.',
 0,
 'PLACEHOLDER — is_active=0 until approved'),

('other', NULL,
 '[PLACEHOLDER — awaiting Тихол approval] Generate a generic product description.\n\nName: {{name}}\nColor: {{color}}\nFeatures: {{features}}\n\nReturn plain text only.',
 0,
 'PLACEHOLDER — is_active=0 until approved');
