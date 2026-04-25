-- S82.AI_STUDIO: per-day per-tenant counter for AI image operations.
-- One row per (tenant, day, operation) bucket. Enforces FREE 0 / START 3 / PRO 10 limits in app code.

CREATE TABLE IF NOT EXISTS ai_image_usage (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  operation ENUM('bg_remove','color_detect','tryon') NOT NULL,
  day DATE NOT NULL,
  count INT UNSIGNED NOT NULL DEFAULT 0,
  last_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tenant_day_op (tenant_id, day, operation),
  INDEX idx_tenant_day (tenant_id, day),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
