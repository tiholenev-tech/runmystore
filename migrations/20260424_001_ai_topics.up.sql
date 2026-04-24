-- S79.SELECTION_ENGINE: MMR topic rotation system
-- ai_topics_catalog  = master каталог (1000+ теми от JSON)
-- ai_topic_rotation  = per-tenant history + suppression

CREATE TABLE IF NOT EXISTS ai_topics_catalog (
  id VARCHAR(50) NOT NULL PRIMARY KEY,
  category VARCHAR(30) NOT NULL,
  name VARCHAR(200) NOT NULL,
  what TEXT NOT NULL,
  trigger_condition VARCHAR(200) DEFAULT NULL,
  data_source VARCHAR(50) DEFAULT NULL,
  topic_type ENUM('fact','reminder','discovery','comparison') NOT NULL DEFAULT 'fact',
  country_codes VARCHAR(100) NOT NULL DEFAULT '*',
  roles VARCHAR(100) NOT NULL DEFAULT 'owner',
  plan VARCHAR(50) NOT NULL DEFAULT 'business',
  priority TINYINT UNSIGNED NOT NULL DEFAULT 5,
  module VARCHAR(30) NOT NULL DEFAULT 'home',
  embedding JSON DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_category (category),
  INDEX idx_module (module),
  INDEX idx_priority (priority),
  INDEX idx_plan (plan),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_topic_rotation (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  topic_id VARCHAR(50) NOT NULL,
  last_shown_at DATETIME NOT NULL,
  shown_count INT UNSIGNED NOT NULL DEFAULT 1,
  last_module VARCHAR(30) DEFAULT NULL,
  suppressed_until DATETIME DEFAULT NULL,
  UNIQUE KEY uk_tenant_topic (tenant_id, topic_id),
  INDEX idx_tenant_shown (tenant_id, last_shown_at),
  INDEX idx_suppressed (suppressed_until),
  CONSTRAINT fk_rotation_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_rotation_topic FOREIGN KEY (topic_id) REFERENCES ai_topics_catalog(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
