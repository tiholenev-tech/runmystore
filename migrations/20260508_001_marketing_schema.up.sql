-- ════════════════════════════════════════════════════════════════════
-- S111.MARKETING_SCHEMA · 2026-05-08
-- Source: docs/marketing/MARKETING_BIBLE_TECHNICAL_v1.md v1.0
-- Scope: 25 CREATE (18 mkt_* + 7 online_*) + 9 ALTER on existing
-- Idempotent: всички CREATE с IF NOT EXISTS, ALTER с information_schema guard
-- Compatible with: MySQL 5.7.18+ (partitioning + InnoDB native)
-- ════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_NOTES = 0;

-- ════════════════════════════════════════════════════════════════════
-- PART 1: 18 mkt_* CREATE TABLE (FK-safe order)
-- ════════════════════════════════════════════════════════════════════

-- Order rationale: tables referenced by other mkt_* tables come FIRST.
-- mkt_campaigns references mkt_audiences (B4) and mkt_creatives (B3),
-- so we create them before mkt_campaigns.

-- ── A3: mkt_prompt_templates (no FKs to mkt tables) ──────────────────
CREATE TABLE IF NOT EXISTS mkt_prompt_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL COMMENT 'strategist|creative|budget|recovery|performance|attribution',
  version VARCHAR(20) NOT NULL,
  language VARCHAR(5) DEFAULT 'bg',
  category VARCHAR(50) DEFAULT 'universal' COMMENT 'universal|fashion|footwear|jewelry|lingerie',

  system_prompt TEXT NOT NULL,
  model_preference ENUM('haiku','flash','sonnet','opus') DEFAULT 'sonnet',
  max_tokens SMALLINT UNSIGNED DEFAULT 2000,
  temperature DECIMAL(3,2) DEFAULT 0.7,

  active BOOLEAN DEFAULT FALSE,
  notes TEXT,

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_code_active (code, active, language),
  UNIQUE KEY uk_code_version (code, version, language, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Marketing AI agent prompt templates';

-- ── A1: mkt_tenant_config (FK to tenants) ────────────────────────────
CREATE TABLE IF NOT EXISTS mkt_tenant_config (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  tier ENUM('disabled','shadow','lite','standard','pro','enterprise') DEFAULT 'disabled',

  inventory_accuracy_score TINYINT UNSIGNED DEFAULT 0 COMMENT '0-100, computed daily',
  inventory_accuracy_days_above_95 SMALLINT UNSIGNED DEFAULT 0,
  loyalty_active BOOLEAN DEFAULT FALSE,
  pos_history_days SMALLINT UNSIGNED DEFAULT 0,
  marketing_active BOOLEAN DEFAULT FALSE COMMENT 'Computed gate result',
  gate_reason TEXT COMMENT 'Why disabled if not active',

  daily_spend_cap DECIMAL(10,2) DEFAULT 0,
  monthly_spend_cap DECIMAL(10,2) DEFAULT 0,
  per_campaign_cap DECIMAL(10,2) DEFAULT 0,
  current_daily_spent DECIMAL(10,2) DEFAULT 0,
  current_monthly_spent DECIMAL(10,2) DEFAULT 0,

  kill_switch_active BOOLEAN DEFAULT FALSE,
  kill_switch_reason TEXT,
  kill_switch_at DATETIME NULL,

  default_approval_level ENUM('auto','tinder','manual') DEFAULT 'tinder',

  brand_voice_config JSON COMMENT 'tone, hashtags, languages',
  language VARCHAR(5) DEFAULT 'bg',
  country VARCHAR(2) DEFAULT 'BG',

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  activated_at DATETIME NULL,
  deleted_at DATETIME NULL,

  INDEX idx_tenant_active (tenant_id, marketing_active),
  INDEX idx_gate (inventory_accuracy_score, loyalty_active),
  CONSTRAINT fk_mkt_config_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  UNIQUE KEY uk_tenant (tenant_id, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Marketing AI per-tenant configuration and gates';

-- ── A2: mkt_channel_auth (FK to tenants) ─────────────────────────────
CREATE TABLE IF NOT EXISTS mkt_channel_auth (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  channel ENUM('meta','tiktok','google_ads','gmb','email','sms') NOT NULL,

  oauth_access_token VARBINARY(2048) COMMENT 'AES encrypted',
  oauth_refresh_token VARBINARY(2048) COMMENT 'AES encrypted',
  expires_at DATETIME NULL,

  account_id VARCHAR(255) COMMENT 'FB ad account ID, TikTok BC ID, etc',
  account_name VARCHAR(255),
  account_currency VARCHAR(3),
  mcp_server_url VARCHAR(500),

  status ENUM('active','expired','revoked','error') DEFAULT 'active',
  last_used_at DATETIME NULL,
  last_error TEXT,
  error_count INT UNSIGNED DEFAULT 0,

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,

  INDEX idx_tenant_channel (tenant_id, channel, status),
  CONSTRAINT fk_mkt_auth_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  UNIQUE KEY uk_tenant_channel (tenant_id, channel, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OAuth credentials for marketing channels';

-- ── B4: mkt_audiences (FK to tenants only — must precede mkt_campaigns) ──
CREATE TABLE IF NOT EXISTS mkt_audiences (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  audience_uuid CHAR(36) NOT NULL,

  name VARCHAR(255) NOT NULL,
  description TEXT,
  type ENUM('custom','lookalike','retargeting','vip','dormant','seasonal','interests') NOT NULL,
  source ENUM('loyalty','sales_history','ad_engagement','pixel','manual') NOT NULL,

  customer_ids JSON COMMENT 'array of customer IDs',
  filters_used JSON,
  external_audience_id VARCHAR(255) COMMENT 'Meta/TikTok ID',
  size_estimate INT UNSIGNED,

  synced_at DATETIME NULL,
  synced_to_channels JSON,

  status ENUM('active','syncing','expired','archived') DEFAULT 'active',

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,

  INDEX idx_tenant_type (tenant_id, type, status),
  CONSTRAINT fk_mkt_aud_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  UNIQUE KEY uk_uuid (audience_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Marketing audiences';

-- ── B3: mkt_creatives (FK to tenants only — must precede mkt_campaigns) ──
CREATE TABLE IF NOT EXISTS mkt_creatives (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  creative_uuid CHAR(36) NOT NULL,

  type ENUM('image','video','carousel','text_only','story') NOT NULL,
  source ENUM('uploaded','ai_generated','symphony','imagestudio','user_provided') NOT NULL,

  file_url VARCHAR(500),
  file_size_bytes BIGINT UNSIGNED,
  dimensions VARCHAR(50) COMMENT 'WIDTHxHEIGHT',
  duration_sec DECIMAL(5,2) NULL,

  ai_label_required BOOLEAN DEFAULT FALSE COMMENT 'EU AI Act',
  ai_disclosure_text VARCHAR(255),
  language VARCHAR(5) DEFAULT 'bg',

  headline VARCHAR(255),
  description TEXT,
  cta_text VARCHAR(100),

  prompt_used TEXT COMMENT 'For AI generated creatives',
  used_in_campaigns_count INT UNSIGNED DEFAULT 0,
  performance_score DECIMAL(5,2) NULL COMMENT 'computed from campaign metrics',

  status ENUM('draft','approved','rejected','archived') DEFAULT 'draft',

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,

  INDEX idx_tenant_status (tenant_id, status),
  INDEX idx_performance (performance_score DESC),
  CONSTRAINT fk_mkt_creative_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  UNIQUE KEY uk_uuid (creative_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Marketing creatives library';

-- ── B1: mkt_campaigns (FK to mkt_audiences, mkt_creatives, tenants) ──
CREATE TABLE IF NOT EXISTS mkt_campaigns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  campaign_uuid CHAR(36) NOT NULL,

  name VARCHAR(255) NOT NULL,
  description TEXT,
  channel ENUM('meta','tiktok','google_ads','gmb','email','sms') NOT NULL,
  external_campaign_id VARCHAR(255) COMMENT 'Meta/TikTok/Google ID',

  status ENUM('draft','pending_approval','active','paused','completed','failed','cancelled','killed') DEFAULT 'draft',
  objective ENUM('sales','traffic','awareness','recovery','zombie_clearance','retargeting','lookalike') NOT NULL,

  budget_total DECIMAL(10,2) NOT NULL,
  budget_daily DECIMAL(10,2),
  currency VARCHAR(3) DEFAULT 'EUR',
  spent_total DECIMAL(10,2) DEFAULT 0,

  start_date DATETIME NULL,
  end_date DATETIME NULL,

  target_product_ids JSON COMMENT 'array of product IDs',
  target_audience_id INT UNSIGNED NULL,
  creative_id INT UNSIGNED NULL,

  triggered_by ENUM('manual','auto_zombie','auto_lost_demand','auto_recovery','auto_seasonal','scheduled') DEFAULT 'manual',
  triggered_signal_id INT UNSIGNED NULL,

  confidence_score TINYINT UNSIGNED COMMENT '0-100',
  expected_revenue DECIMAL(10,2) COMMENT 'AI sandbox prediction',
  actual_revenue DECIMAL(10,2) DEFAULT 0,
  roi_score DECIMAL(8,2) NULL,

  kill_switched BOOLEAN DEFAULT FALSE,
  kill_reason TEXT,

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  approved_by INT UNSIGNED NULL,
  deleted_at DATETIME NULL,

  INDEX idx_tenant_status (tenant_id, status, created_at),
  INDEX idx_channel (channel, status),
  INDEX idx_external (external_campaign_id),
  CONSTRAINT fk_mkt_camp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_mkt_camp_audience FOREIGN KEY (target_audience_id) REFERENCES mkt_audiences(id) ON DELETE SET NULL,
  CONSTRAINT fk_mkt_camp_creative FOREIGN KEY (creative_id) REFERENCES mkt_creatives(id) ON DELETE SET NULL,
  UNIQUE KEY uk_uuid (campaign_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Marketing campaigns master table';

-- ── B2: mkt_campaign_decisions (FK to mkt_campaigns) ─────────────────
CREATE TABLE IF NOT EXISTS mkt_campaign_decisions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  campaign_id INT UNSIGNED NULL,

  decision_type ENUM('create','scale_up','scale_down','pause','kill','optimize','recommend_no_action') NOT NULL,
  agent_used VARCHAR(50) COMMENT 'strategist|creative|budget|...',
  model_used ENUM('rules_only','flash','haiku','sonnet','opus') NOT NULL,

  input_context JSON COMMENT 'data given to AI',
  output_decision JSON COMMENT 'what AI decided',
  reasoning TEXT COMMENT 'Bulgarian explanation',
  confidence TINYINT UNSIGNED COMMENT '0-100',

  cost_in_credits DECIMAL(8,4) DEFAULT 0 COMMENT 'AI API cost',

  applied BOOLEAN DEFAULT FALSE,
  applied_at DATETIME NULL,
  override_by_user BOOLEAN DEFAULT FALSE,
  override_reason TEXT,

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_tenant_created (tenant_id, created_at DESC),
  INDEX idx_campaign (campaign_id, created_at),
  INDEX idx_agent (agent_used, model_used),
  CONSTRAINT fk_mkt_dec_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_mkt_dec_campaign FOREIGN KEY (campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Audit log of all AI marketing decisions';

-- ── B5: mkt_approval_queue (FK to mkt_campaigns) ─────────────────────
CREATE TABLE IF NOT EXISTS mkt_approval_queue (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  campaign_id INT UNSIGNED NOT NULL,

  approval_level ENUM('auto','tinder','manual_review') NOT NULL,
  requires_user_id INT UNSIGNED NULL,

  presented_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME NULL,
  decision ENUM('approve','reject','snooze','expired') NULL,
  decision_method ENUM('tap','voice','api','auto') NULL,

  voice_feedback TEXT COMMENT 'Bulgarian text of voice rejection',
  voice_feedback_audio_url VARCHAR(500),
  learning_recorded BOOLEAN DEFAULT FALSE,
  auto_decision_reason TEXT,

  expires_at DATETIME COMMENT 'Auto-snooze if not decided',

  INDEX idx_tenant_pending (tenant_id, decision, presented_at),
  INDEX idx_campaign (campaign_id),
  CONSTRAINT fk_mkt_appr_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_mkt_appr_campaign FOREIGN KEY (campaign_id) REFERENCES mkt_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Approval queue for Tinder UX';

-- ── C1: mkt_attribution_codes (FK to mkt_campaigns) ──────────────────
CREATE TABLE IF NOT EXISTS mkt_attribution_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  campaign_id INT UNSIGNED NOT NULL,

  code VARCHAR(50) NOT NULL COMMENT 'auto-generated unique',
  discount_type ENUM('percent','fixed','free_item','none') DEFAULT 'percent',
  discount_value DECIMAL(8,2) DEFAULT 0,

  valid_from DATETIME NOT NULL,
  valid_until DATETIME NOT NULL,
  usage_limit INT UNSIGNED NULL,
  usage_count INT UNSIGNED DEFAULT 0,

  auto_applied_at_pos BOOLEAN DEFAULT FALSE COMMENT 'Voice prompt at checkout',
  qr_code_url VARCHAR(500),

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_code (code),
  INDEX idx_tenant_valid (tenant_id, valid_from, valid_until),
  CONSTRAINT fk_mkt_code_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_mkt_code_campaign FOREIGN KEY (campaign_id) REFERENCES mkt_campaigns(id) ON DELETE CASCADE,
  UNIQUE KEY uk_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Attribution promo codes';

-- ── C2: mkt_attribution_events (partitioned, FK to tenants only) ──────
CREATE TABLE IF NOT EXISTS mkt_attribution_events (
  id BIGINT UNSIGNED AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  campaign_id INT UNSIGNED NULL,

  event_type ENUM('impression','click','landing_view','store_visit','add_to_cart','purchase','voice_answer') NOT NULL,
  customer_id INT UNSIGNED NULL,
  external_event_id VARCHAR(255),

  device_id VARCHAR(255),
  ip_hash VARBINARY(64),
  creative_id INT UNSIGNED NULL,
  audience_id INT UNSIGNED NULL,

  revenue_attributed DECIMAL(10,2) DEFAULT 0,
  cost_attributed DECIMAL(10,2) DEFAULT 0,
  event_data JSON,

  event_timestamp DATETIME NOT NULL,
  processed BOOLEAN DEFAULT FALSE,

  PRIMARY KEY (id, event_timestamp),
  INDEX idx_tenant_time (tenant_id, event_timestamp DESC),
  INDEX idx_customer (customer_id, event_timestamp DESC),
  INDEX idx_campaign (campaign_id, event_type),
  INDEX idx_unprocessed (processed, event_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Attribution events (high volume - partitioned)'
PARTITION BY RANGE (UNIX_TIMESTAMP(event_timestamp)) (
  PARTITION p_2026 VALUES LESS THAN (UNIX_TIMESTAMP('2027-01-01')),
  PARTITION p_2027 VALUES LESS THAN (UNIX_TIMESTAMP('2028-01-01')),
  PARTITION p_max VALUES LESS THAN MAXVALUE
);
-- NOTE: PRIMARY KEY redefined to include event_timestamp (MySQL partition rule:
-- partitioning column must be part of every unique/primary key). FK to tenants
-- is omitted because partitioned tables cannot have FKs (MySQL limitation).

-- ── C3: mkt_attribution_matches (FK to campaigns, sales) ─────────────
CREATE TABLE IF NOT EXISTS mkt_attribution_matches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  campaign_id INT UNSIGNED NOT NULL,
  sale_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,

  match_method ENUM('promo_code','loyalty_match','voice_asked','qr_scan','timing','pixel','manual') NOT NULL,
  match_confidence TINYINT UNSIGNED COMMENT '0-100',
  match_window_minutes INT UNSIGNED COMMENT 'Time from click to sale',

  revenue_attributed DECIMAL(10,2) NOT NULL,
  verified BOOLEAN DEFAULT FALSE,
  verified_by INT UNSIGNED NULL,

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_tenant_campaign (tenant_id, campaign_id, created_at),
  INDEX idx_sale (sale_id),
  INDEX idx_customer (customer_id, created_at),
  CONSTRAINT fk_mkt_match_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_mkt_match_campaign FOREIGN KEY (campaign_id) REFERENCES mkt_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_mkt_match_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Ad-to-sale attribution matches';

-- ── D1: mkt_budget_limits (FK to tenants) ────────────────────────────
CREATE TABLE IF NOT EXISTS mkt_budget_limits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  channel ENUM('meta','tiktok','google_ads','gmb','email','sms','total') NOT NULL,

  daily_cap DECIMAL(10,2) NOT NULL DEFAULT 0,
  weekly_cap DECIMAL(10,2) DEFAULT 0,
  monthly_cap DECIMAL(10,2) DEFAULT 0,
  per_campaign_cap DECIMAL(10,2) DEFAULT 0,
  currency VARCHAR(3) DEFAULT 'EUR',

  current_daily_spent DECIMAL(10,2) DEFAULT 0,
  current_weekly_spent DECIMAL(10,2) DEFAULT 0,
  current_monthly_spent DECIMAL(10,2) DEFAULT 0,

  alert_at_percent TINYINT UNSIGNED DEFAULT 80,
  auto_pause_at_percent TINYINT UNSIGNED DEFAULT 100,

  last_alert_sent_at DATETIME NULL,
  last_auto_pause_at DATETIME NULL,

  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_tenant_channel (tenant_id, channel),
  CONSTRAINT fk_mkt_budget_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Hard budget caps and current spend';

-- ── D2: mkt_spend_log (FK to tenants, mkt_campaigns) ─────────────────
CREATE TABLE IF NOT EXISTS mkt_spend_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  channel ENUM('meta','tiktok','google_ads','gmb','email','sms') NOT NULL,
  campaign_id INT UNSIGNED NULL,

  date DATE NOT NULL,
  currency VARCHAR(3) DEFAULT 'EUR',

  amount_spent DECIMAL(10,2) DEFAULT 0,
  amount_budgeted DECIMAL(10,2) DEFAULT 0,
  impressions INT UNSIGNED DEFAULT 0,
  clicks INT UNSIGNED DEFAULT 0,
  conversions INT UNSIGNED DEFAULT 0,

  external_data JSON COMMENT 'Raw data from Meta/TikTok API',
  synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_tenant_date (tenant_id, date DESC),
  INDEX idx_campaign_date (campaign_id, date),
  UNIQUE KEY uk_tenant_channel_campaign_date (tenant_id, channel, campaign_id, date),
  CONSTRAINT fk_mkt_spend_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_mkt_spend_campaign FOREIGN KEY (campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Daily spend tracking';

-- ── E1: mkt_learnings (FK to mkt_campaigns) ──────────────────────────
CREATE TABLE IF NOT EXISTS mkt_learnings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  campaign_id INT UNSIGNED NULL,

  outcome ENUM('success','partial','fail','inconclusive') NOT NULL,
  summary TEXT COMMENT 'Bulgarian explanation',
  lessons_learned JSON COMMENT 'array of insights',
  category ENUM('creative','audience','timing','budget','product_fit','channel','other') NOT NULL,

  applied_to_future BOOLEAN DEFAULT FALSE,
  model_feedback_score TINYINT UNSIGNED COMMENT '1-5 user rating of usefulness',

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_tenant_outcome (tenant_id, outcome, created_at),
  INDEX idx_category (category, outcome),
  CONSTRAINT fk_mkt_learn_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_mkt_learn_campaign FOREIGN KEY (campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Teaching moments for learning loop';

-- ── E2: mkt_benchmark_data (no FKs) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS mkt_benchmark_data (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(50) COMMENT 'fashion|footwear|jewelry|...',
  country VARCHAR(2) NOT NULL,
  month_year VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',

  avg_ctr DECIMAL(5,4),
  avg_cpa DECIMAL(8,2),
  avg_roas DECIMAL(5,2),
  best_channel VARCHAR(20),
  best_audience_type VARCHAR(50),
  seasonal_factor DECIMAL(4,2),

  sample_size INT UNSIGNED COMMENT 'Number of tenants contributed',
  anonymized BOOLEAN DEFAULT TRUE,

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uk_category_country_month (category, country, month_year),
  INDEX idx_country_month (country, month_year DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Anonymous cross-tenant benchmarks';

-- ── E3: mkt_user_overrides (FK to decisions, campaigns) ──────────────
CREATE TABLE IF NOT EXISTS mkt_user_overrides (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  decision_id INT UNSIGNED NULL,
  campaign_id INT UNSIGNED NULL,

  original_recommendation JSON,
  user_override JSON,
  override_reason TEXT COMMENT 'Voice transcript',

  outcome_after_override ENUM('worse','same','better','unknown') DEFAULT 'unknown',
  pattern_detected TEXT COMMENT 'AI-discovered pattern',

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_tenant_created (tenant_id, created_at),
  CONSTRAINT fk_mkt_over_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_mkt_over_decision FOREIGN KEY (decision_id) REFERENCES mkt_campaign_decisions(id) ON DELETE SET NULL,
  CONSTRAINT fk_mkt_over_campaign FOREIGN KEY (campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User overrides as training data';

-- ── F1: mkt_scheduled_jobs (FK to tenants) ───────────────────────────
CREATE TABLE IF NOT EXISTS mkt_scheduled_jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  job_type ENUM('zombie_scan','dormant_check','seasonal_alert','budget_pacing','accuracy_check','daily_report') NOT NULL,

  frequency ENUM('hourly','daily','weekly','monthly','event_based') NOT NULL,
  next_run_at DATETIME NOT NULL,
  last_run_at DATETIME NULL,
  last_result JSON,
  last_error TEXT,

  active BOOLEAN DEFAULT TRUE,

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_next_run (active, next_run_at),
  INDEX idx_tenant (tenant_id, job_type),
  CONSTRAINT fk_mkt_job_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Scheduled marketing jobs';

-- ── F2: mkt_triggers (FK to tenants) ─────────────────────────────────
CREATE TABLE IF NOT EXISTS mkt_triggers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  trigger_name VARCHAR(255) NOT NULL,

  condition_type ENUM('zombie_30d','zombie_60d','lost_demand','dormant_60d','low_stock','birthday','seasonal','custom') NOT NULL,
  condition_params JSON,

  action ENUM('notify_owner','draft_campaign','auto_pause','recommend_only','auto_publish_zero_budget') DEFAULT 'draft_campaign',
  cooldown_hours SMALLINT UNSIGNED DEFAULT 24,

  last_fired_at DATETIME NULL,
  fired_count INT UNSIGNED DEFAULT 0,
  active BOOLEAN DEFAULT TRUE,

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_tenant_active (tenant_id, active),
  CONSTRAINT fk_mkt_trig_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Auto-trigger rules';

-- ════════════════════════════════════════════════════════════════════
-- PART 2: 7 online_* CREATE TABLE
-- ════════════════════════════════════════════════════════════════════

-- ── H1: online_stores (FK to tenants) ────────────────────────────────
CREATE TABLE IF NOT EXISTS online_stores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  store_uuid CHAR(36) NOT NULL,

  ecwid_store_id VARCHAR(50) NOT NULL,
  ecwid_api_token VARBINARY(2048) COMMENT 'AES encrypted',
  ecwid_plan VARCHAR(50),

  domain VARCHAR(255) COMMENT 'subdomain or custom',
  custom_domain VARCHAR(255) NULL,
  ssl_active BOOLEAN DEFAULT FALSE,

  status ENUM('provisioning','active','paused','failed','migrating','deleted') DEFAULT 'provisioning',
  installation_status_message TEXT,

  template_id INT UNSIGNED NULL COMMENT 'FK to template chosen',

  language VARCHAR(5) DEFAULT 'bg',
  currency VARCHAR(3) DEFAULT 'EUR',
  country VARCHAR(2) DEFAULT 'BG',

  payment_methods_active JSON,
  shipping_methods_active JSON,

  monthly_traffic INT UNSIGNED DEFAULT 0,
  monthly_orders INT UNSIGNED DEFAULT 0,
  monthly_revenue DECIMAL(10,2) DEFAULT 0,

  setup_started_at DATETIME NULL,
  setup_completed_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,

  INDEX idx_tenant_status (tenant_id, status),
  INDEX idx_ecwid (ecwid_store_id),
  CONSTRAINT fk_online_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  UNIQUE KEY uk_uuid (store_uuid),
  UNIQUE KEY uk_tenant_active (tenant_id, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Online store (Ecwid) per tenant';

-- ── H2: online_store_products (FK to online_stores, products) ────────
CREATE TABLE IF NOT EXISTS online_store_products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,

  runmystore_product_id INT UNSIGNED NOT NULL,
  ecwid_product_id VARCHAR(50) NOT NULL,

  is_variation BOOLEAN DEFAULT FALSE,
  parent_product_id INT UNSIGNED NULL COMMENT 'For variations',
  ecwid_variation_id VARCHAR(50) NULL,

  online_published BOOLEAN DEFAULT FALSE,
  online_url VARCHAR(500),

  last_synced_at DATETIME NULL,
  sync_status ENUM('synced','pending','syncing','failed','conflict') DEFAULT 'pending',
  sync_error TEXT,
  sync_attempts INT UNSIGNED DEFAULT 0,

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_store_status (store_id, sync_status),
  INDEX idx_runmystore_product (runmystore_product_id),
  INDEX idx_ecwid_product (ecwid_product_id),
  CONSTRAINT fk_online_prod_store FOREIGN KEY (store_id) REFERENCES online_stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_online_prod_product FOREIGN KEY (runmystore_product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY uk_store_product (store_id, runmystore_product_id, ecwid_variation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Product mapping RunMyStore ↔ Ecwid';

-- ── H3: online_store_orders (FK to online_stores, mkt_campaigns, tenants) ──
CREATE TABLE IF NOT EXISTS online_store_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  store_id INT UNSIGNED NOT NULL,

  ecwid_order_id VARCHAR(50) NOT NULL,
  runmystore_order_id INT UNSIGNED NULL COMMENT 'FK to internal orders if created',

  customer_id INT UNSIGNED NULL,
  customer_email VARCHAR(255),
  customer_phone VARCHAR(50),
  customer_name VARCHAR(255),

  total_amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'EUR',

  assigned_store_id INT UNSIGNED NULL COMMENT 'Which physical store handles this',
  routing_reason ENUM('base_warehouse','most_quantity','manual','closest','only_available') NULL,
  routing_decision_at DATETIME NULL,
  routing_attempts INT UNSIGNED DEFAULT 0,

  status ENUM('received','reserved','processing','packed','shipped','delivered','cancelled','refunded') DEFAULT 'received',

  shipping_method VARCHAR(100),
  shipping_tracking_number VARCHAR(255),

  promo_code_used VARCHAR(50) NULL,
  attribution_campaign_id INT UNSIGNED NULL,

  raw_order_data JSON COMMENT 'Full Ecwid order JSON',

  ordered_at DATETIME NOT NULL,
  received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  shipped_at DATETIME NULL,
  delivered_at DATETIME NULL,

  INDEX idx_tenant_status (tenant_id, status, ordered_at),
  INDEX idx_assigned_store (assigned_store_id, status),
  INDEX idx_ecwid_order (ecwid_order_id),
  INDEX idx_promo (promo_code_used),
  CONSTRAINT fk_online_ord_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_online_ord_store FOREIGN KEY (store_id) REFERENCES online_stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_online_ord_campaign FOREIGN KEY (attribution_campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL,
  UNIQUE KEY uk_ecwid_order (ecwid_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Online orders from Ecwid';

-- ── H4: online_store_inventory_locks (FK to online_store_orders, products) ──
CREATE TABLE IF NOT EXISTS online_store_inventory_locks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,

  product_id INT UNSIGNED NOT NULL,
  variation_id INT UNSIGNED NULL,
  physical_store_id INT UNSIGNED NOT NULL COMMENT 'Which store has the locked item',

  quantity_locked INT UNSIGNED NOT NULL,

  locked_for_order_id INT UNSIGNED NOT NULL COMMENT 'FK to online_store_orders',

  locked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL COMMENT 'Default: locked_at + 30 min',
  released_at DATETIME NULL,

  status ENUM('active','converted_to_sale','expired','released_manually','overridden') DEFAULT 'active',
  override_reason TEXT,
  override_by INT UNSIGNED NULL,

  INDEX idx_tenant_active (tenant_id, status, expires_at),
  INDEX idx_product (product_id, variation_id, status),
  INDEX idx_physical_store (physical_store_id, status),
  INDEX idx_order (locked_for_order_id),
  CONSTRAINT fk_lock_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_lock_order FOREIGN KEY (locked_for_order_id) REFERENCES online_store_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_lock_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Inventory locks for online orders (30 min default)';

-- ── H5: online_store_routing_log (FK to online_store_orders) ─────────
CREATE TABLE IF NOT EXISTS online_store_routing_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,

  decision_made_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  candidate_stores JSON COMMENT 'array of {store_id, available_qty, distance_km}',
  chosen_store_id INT UNSIGNED NOT NULL,
  chosen_reason ENUM('base_warehouse','most_quantity','manual','closest','only_available') NOT NULL,

  override_by_user BOOLEAN DEFAULT FALSE,
  override_user_id INT UNSIGNED NULL,
  override_reason TEXT,

  INDEX idx_tenant_order (tenant_id, order_id),
  INDEX idx_decision_time (decision_made_at),
  CONSTRAINT fk_routing_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_routing_order FOREIGN KEY (order_id) REFERENCES online_store_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Routing decisions audit';

-- ── H6: online_store_sync_log (partitioned, FK to online_stores) ─────
CREATE TABLE IF NOT EXISTS online_store_sync_log (
  id BIGINT UNSIGNED AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  store_id INT UNSIGNED NOT NULL,

  sync_type ENUM('product','order','inventory','customer','category','price') NOT NULL,
  direction ENUM('push_to_ecwid','pull_from_ecwid','bidirectional') NOT NULL,

  entity_id INT UNSIGNED COMMENT 'RunMyStore entity ID',
  external_id VARCHAR(100) COMMENT 'Ecwid entity ID',

  status ENUM('success','conflict','failed','retried') NOT NULL,
  payload JSON,
  response JSON,
  error_message TEXT,

  duration_ms INT UNSIGNED,
  synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id, synced_at),
  INDEX idx_tenant_time (tenant_id, synced_at DESC),
  INDEX idx_store_status (store_id, status, sync_type),
  INDEX idx_entity (entity_id, sync_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Sync events log'
PARTITION BY RANGE (UNIX_TIMESTAMP(synced_at)) (
  PARTITION p_2026 VALUES LESS THAN (UNIX_TIMESTAMP('2027-01-01')),
  PARTITION p_2027 VALUES LESS THAN (UNIX_TIMESTAMP('2028-01-01')),
  PARTITION p_max VALUES LESS THAN MAXVALUE
);
-- NOTE: PRIMARY KEY redefined to include synced_at. FKs omitted (partitioning limitation).

-- ── H7: online_store_settings (FK to tenants, stores) ────────────────
CREATE TABLE IF NOT EXISTS online_store_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,

  base_warehouse_store_id INT UNSIGNED NULL COMMENT 'FK to stores - primary fulfillment',
  routing_strategy ENUM('base_first','most_qty','closest','manual_only') DEFAULT 'base_first',
  fallback_strategy ENUM('most_qty','closest','reject') DEFAULT 'most_qty',

  lock_duration_minutes SMALLINT UNSIGNED DEFAULT 30,
  auto_reserve BOOLEAN DEFAULT TRUE,
  allow_physical_override BOOLEAN DEFAULT FALSE COMMENT 'Can physical sale override online lock?',

  sync_interval_minutes TINYINT UNSIGNED DEFAULT 5,

  online_published_default BOOLEAN DEFAULT FALSE,

  notification_email VARCHAR(255),
  notification_phone VARCHAR(50),

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_settings_store FOREIGN KEY (base_warehouse_store_id) REFERENCES stores(id) ON DELETE SET NULL,
  UNIQUE KEY uk_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Per-tenant online store settings';

-- ════════════════════════════════════════════════════════════════════
-- PART 3: 9 ALTER TABLE — idempotent via INFORMATION_SCHEMA guards
-- ════════════════════════════════════════════════════════════════════
-- Each ADD COLUMN/INDEX/CONSTRAINT is wrapped in a column-existence check.
-- Pattern (single column): SET @x := (SELECT COUNT(*) FROM info_schema.COLUMNS ...);
--                          PREPARE stmt FROM IF(@x=0,'ALTER...','SELECT 1'); EXEC; DEALLOCATE.

DELIMITER //

DROP PROCEDURE IF EXISTS s111_add_column//
CREATE PROCEDURE s111_add_column(
  IN p_table VARCHAR(64),
  IN p_column VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  DECLARE col_exists INT DEFAULT 0;
  DECLARE tbl_exists INT DEFAULT 0;
  SELECT COUNT(*) INTO tbl_exists FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table;
  IF tbl_exists = 0 THEN
    SELECT CONCAT('SKIP: table ', p_table, ' does not exist') AS s111_log;
  ELSE
    SELECT COUNT(*) INTO col_exists FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column;
    IF col_exists = 0 THEN
      SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
      PREPARE stmt FROM @sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;
  END IF;
END//

DROP PROCEDURE IF EXISTS s111_add_index//
CREATE PROCEDURE s111_add_index(
  IN p_table VARCHAR(64),
  IN p_index VARCHAR(64),
  IN p_columns TEXT
)
BEGIN
  DECLARE idx_exists INT DEFAULT 0;
  DECLARE tbl_exists INT DEFAULT 0;
  SELECT COUNT(*) INTO tbl_exists FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table;
  IF tbl_exists > 0 THEN
    SELECT COUNT(*) INTO idx_exists FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index;
    IF idx_exists = 0 THEN
      SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_columns, ')');
      PREPARE stmt FROM @sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;
  END IF;
END//

DROP PROCEDURE IF EXISTS s111_add_fk//
CREATE PROCEDURE s111_add_fk(
  IN p_table VARCHAR(64),
  IN p_constraint VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  DECLARE fk_exists INT DEFAULT 0;
  DECLARE tbl_exists INT DEFAULT 0;
  SELECT COUNT(*) INTO tbl_exists FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table;
  IF tbl_exists > 0 THEN
    SELECT COUNT(*) INTO fk_exists FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
        AND CONSTRAINT_NAME = p_constraint AND CONSTRAINT_TYPE = 'FOREIGN KEY';
    IF fk_exists = 0 THEN
      SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_constraint, '` ', p_definition);
      PREPARE stmt FROM @sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;
  END IF;
END//

DELIMITER ;

-- ── ALTER 1: tenants ────────────────────────────────────────────────
CALL s111_add_column('tenants', 'marketing_tier', "ENUM('disabled','shadow','lite','standard','pro','enterprise') DEFAULT 'disabled'");
CALL s111_add_column('tenants', 'marketing_activated_at', 'DATETIME NULL');
CALL s111_add_column('tenants', 'marketing_total_spend_lifetime', 'DECIMAL(12,2) DEFAULT 0');
CALL s111_add_column('tenants', 'marketing_total_revenue_attributed', 'DECIMAL(12,2) DEFAULT 0');
CALL s111_add_column('tenants', 'online_store_active', 'BOOLEAN DEFAULT FALSE');
CALL s111_add_column('tenants', 'online_store_id', 'INT UNSIGNED NULL');
CALL s111_add_column('tenants', 'online_store_tier', "ENUM('none','lite','standard','pro') DEFAULT 'none'");
CALL s111_add_index('tenants', 'idx_marketing_tier', 'marketing_tier');

-- ── ALTER 2: customers ───────────────────────────────────────────────
CALL s111_add_column('customers', 'last_seen_in_ad_at', 'DATETIME NULL');
CALL s111_add_column('customers', 'acquired_via_campaign_id', 'INT UNSIGNED NULL');
CALL s111_add_column('customers', 'total_attributed_revenue', 'DECIMAL(10,2) DEFAULT 0');
CALL s111_add_column('customers', 'marketing_consent', 'BOOLEAN DEFAULT FALSE');
CALL s111_add_column('customers', 'marketing_consent_at', 'DATETIME NULL');
CALL s111_add_index('customers', 'idx_acquired_campaign', 'acquired_via_campaign_id');
CALL s111_add_index('customers', 'idx_consent', 'marketing_consent');
CALL s111_add_fk('customers', 'fk_cust_campaign',
  'FOREIGN KEY (acquired_via_campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL');

-- ── ALTER 3: sales ───────────────────────────────────────────────────
CALL s111_add_column('sales', 'attribution_campaign_id', 'INT UNSIGNED NULL');
CALL s111_add_column('sales', 'attribution_method', 'VARCHAR(50) NULL');
CALL s111_add_column('sales', 'promo_code_used', 'VARCHAR(50) NULL');
CALL s111_add_column('sales', 'attribution_confidence', 'TINYINT UNSIGNED NULL');
CALL s111_add_column('sales', 'source', "ENUM('physical','online','phone','social') DEFAULT 'physical'");
CALL s111_add_column('sales', 'ecwid_order_id', 'VARCHAR(50) NULL');
CALL s111_add_index('sales', 'idx_campaign', 'attribution_campaign_id');
CALL s111_add_index('sales', 'idx_source', 'source');
CALL s111_add_index('sales', 'idx_ecwid_order', 'ecwid_order_id');
CALL s111_add_fk('sales', 'fk_sales_campaign',
  'FOREIGN KEY (attribution_campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL');

-- ── ALTER 4: products ────────────────────────────────────────────────
CALL s111_add_column('products', 'last_promoted_at', 'DATETIME NULL');
CALL s111_add_column('products', 'total_ad_spend', 'DECIMAL(10,2) DEFAULT 0');
CALL s111_add_column('products', 'total_attributed_revenue', 'DECIMAL(10,2) DEFAULT 0');
CALL s111_add_column('products', 'promotion_eligibility_score', 'TINYINT UNSIGNED DEFAULT 0');
CALL s111_add_column('products', 'is_zombie', 'BOOLEAN DEFAULT FALSE');
CALL s111_add_column('products', 'zombie_since', 'DATETIME NULL');
CALL s111_add_column('products', 'online_published', 'BOOLEAN DEFAULT FALSE');
CALL s111_add_column('products', 'online_url', 'VARCHAR(500) NULL');
CALL s111_add_column('products', 'online_seo_title', 'VARCHAR(255) NULL');
CALL s111_add_column('products', 'online_seo_description', 'TEXT NULL');
CALL s111_add_column('products', 'online_first_published_at', 'DATETIME NULL');
CALL s111_add_index('products', 'idx_zombie', 'is_zombie, zombie_since');
CALL s111_add_index('products', 'idx_online_published', 'online_published');

-- ── ALTER 5: users ───────────────────────────────────────────────────
CALL s111_add_column('users', 'marketing_role', "ENUM('none','viewer','approver','admin') DEFAULT 'none'");
CALL s111_add_column('users', 'voice_feedback_count', 'INT UNSIGNED DEFAULT 0');
CALL s111_add_index('users', 'idx_marketing_role', 'marketing_role');

-- ── ALTER 6: inventory ───────────────────────────────────────────────
-- IMPORTANT: reserved_quantity MUST be added BEFORE available_for_online_quantity
-- (the latter is a generated column referencing the former).
CALL s111_add_column('inventory', 'reserved_quantity', "INT UNSIGNED DEFAULT 0 COMMENT 'Locked for online orders'");
CALL s111_add_column('inventory', 'available_for_online_quantity',
  'INT UNSIGNED GENERATED ALWAYS AS (GREATEST(quantity - reserved_quantity, 0)) STORED');
CALL s111_add_column('inventory', 'accuracy_last_check_at', 'DATETIME NULL');
CALL s111_add_column('inventory', 'accuracy_score', 'TINYINT UNSIGNED DEFAULT 100');
CALL s111_add_index('inventory', 'idx_available_online', 'available_for_online_quantity');

-- ── ALTER 7: stores ──────────────────────────────────────────────────
CALL s111_add_column('stores', 'is_base_warehouse', 'BOOLEAN DEFAULT FALSE');
CALL s111_add_column('stores', 'online_orders_enabled', 'BOOLEAN DEFAULT FALSE');
CALL s111_add_column('stores', 'average_processing_time_min', 'SMALLINT UNSIGNED DEFAULT 30');
CALL s111_add_index('stores', 'idx_base_warehouse', 'is_base_warehouse, online_orders_enabled');

-- ── ALTER 8: promotions (FAIL_GRACE — table may not exist post-beta) ──
-- s111_add_column already guards table existence; if 'promotions' is missing,
-- these calls log SKIP and move on.
CALL s111_add_column('promotions', 'marketing_campaign_id', 'INT UNSIGNED NULL');
CALL s111_add_column('promotions', 'auto_generated_for_attribution', 'BOOLEAN DEFAULT FALSE');
CALL s111_add_index('promotions', 'idx_marketing_campaign', 'marketing_campaign_id');
CALL s111_add_fk('promotions', 'fk_promo_campaign',
  'FOREIGN KEY (marketing_campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL');

-- ── ALTER 9: loyalty_points_log (FAIL_GRACE — table may not exist) ────
CALL s111_add_column('loyalty_points_log', 'attributed_to_campaign_id', 'INT UNSIGNED NULL');
CALL s111_add_index('loyalty_points_log', 'idx_attribution', 'attributed_to_campaign_id');
CALL s111_add_fk('loyalty_points_log', 'fk_loyalty_campaign',
  'FOREIGN KEY (attributed_to_campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL');

-- ── Cleanup helper procedures ────────────────────────────────────────
DROP PROCEDURE IF EXISTS s111_add_column;
DROP PROCEDURE IF EXISTS s111_add_index;
DROP PROCEDURE IF EXISTS s111_add_fk;

SET FOREIGN_KEY_CHECKS = 1;
SET SQL_NOTES = 1;

-- ════════════════════════════════════════════════════════════════════
-- DONE: 25 tables created (or already present) + 9 ALTERs applied
-- (or 8 if promotions/loyalty_points_log do not exist on this DB)
-- ════════════════════════════════════════════════════════════════════
