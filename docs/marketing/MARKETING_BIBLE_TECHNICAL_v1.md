# MARKETING_BIBLE_TECHNICAL_v1.md

# RunMyStore.ai — Marketing AI & Online Store Module
## Техническа Библия v1.0 — Schema, API, Архитектура

**Дата на финализиране:** 03 май 2026
**Статус:** APPROVED за добавяне в roadmap
**Owner:** Тихол Енев
**Reviewer:** Шеф (за добавяне в график)
**Свързан документ:** MARKETING_BIBLE_LOGIC_v1.md

---

## СЪДЪРЖАНИЕ

1. Архитектурни принципи
2. Технически стек
3. Schema — Пълен план (25 таблици + 9 ALTER)
4. API интеграции
5. Inventory Sync Engine
6. Multi-store Routing Logic
7. AI Cost Optimization
8. Migration План
9. Code Examples за критични модули
10. Monitoring & Alerts
11. Testing Strategy
12. Compliance (GDPR, EU AI Act, PCI)

---

# 1. АРХИТЕКТУРНИ ПРИНЦИПИ

## RunMyStore Закони (наследени от core продукта)

**Закон №1:** Пешо не пише нищо. Само глас, снимка, едно натискане.
**Закон №2:** PHP смята, AI говори. Числата идват от SQL.
**Закон №3:** AI мълчи, PHP продължава. AI failure не блокира продукта.
**Закон №6:** Inventory Gate — PHP е truth, AI е форма.
**Закон №7:** Audit Trail (`retrieved_facts` + `mkt_campaign_decisions`).
**Закон №8:** Confidence Routing (>0.85 auto, 0.5-0.85 confirm, <0.5 block).

## Допълнителни Marketing AI принципи

**Закон №9 (нов):** Marketing AI се активира **само** когато inventory accuracy ≥95% за 30 последователни дни.
**Закон №10 (нов):** Всяко AI решение записва confidence + reasoning + cost. Без audit — без действие.
**Закон №11 (нов):** Hard spend caps са non-negotiable. AI не може да ги override.

## Multi-tenant изолация

- Всяка таблица има `tenant_id` (FK to `tenants.id`)
- Foreign keys с `ON DELETE SET NULL` за audit safety
- Soft delete с `deleted_at NULL` навсякъде
- Tenant-level config gate (`mkt_tenant_config.marketing_active`)

## i18n compliance (задължително)

- Всички текстове през `priceFormat($amount, $tenant)`
- НИКОГА hardcoded "лв" / "BGN" / "€"
- Текстове за потребител през `tenant.lang ($lang)` функцията
- BG двойно показване (€+лв) до 08.08.2026 (curs 1.95583)

---

# 2. ТЕХНИЧЕСКИ СТЕК

## Backend
- **PHP 8.2** (existing)
- **MySQL 8** (existing)
- **Cron jobs** (за scheduled triggers)

## AI Models (Cost-Optimized)

| Модел | Use Case | Cost/call (≈) |
|-------|----------|---------------|
| Gemini 2.5 Flash | Routing, scanning, reports | €0.001 |
| Claude Haiku 4.5 | Quick decisions, classification | €0.005 |
| Claude Sonnet 4.6 | Complex decisions, copy | €0.012 |
| Claude Opus 4.7 | Strategic analysis (rare) | €0.075 |

**Cost target:** €4-8 per tenant/мес при 1000+ tenants.

## External APIs

| Service | Purpose | API Type |
|---------|---------|----------|
| Ecwid REST API | Online store provisioning + sync | REST + Webhooks |
| Stripe API | Payments | REST + Webhooks |
| Meta Graph API (via MCP) | Facebook + Instagram ads | MCP |
| TikTok Ads API (via MCP) | TikTok campaigns | MCP |
| Google Ads API (via MCP) | Google PMax + Search | MCP |
| Google My Business API | Local SEO | REST |
| Speedy/Econt API | Доставки за БГ | REST |
| Borica API | БГ payments (when needed) | REST |

## Encryption

- OAuth tokens: MySQL AES_ENCRYPT с key от `/etc/runmystore/encryption.key`
- Stripe credentials: aplikatsionen layer encryption (sodium)
- Customer PII: tokenization for marketing data

---

# 3. SCHEMA — ПЪЛЕН ПЛАН

## Общо: 25 нови таблици + 9 ALTER операции

### Префикс `mkt_` за Marketing AI таблици
### Префикс `online_` за Online Store таблици

---

## ГРУПА A: КОНФИГУРАЦИЯ & АДМИНИСТРАЦИЯ (3 таблици)

### A1. `mkt_tenant_config`
Marketing настройки на ниво tenant + gate logic.

```sql
CREATE TABLE mkt_tenant_config (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  tier ENUM('disabled','shadow','lite','standard','pro','enterprise') DEFAULT 'disabled',
  
  -- Gate logic
  inventory_accuracy_score TINYINT UNSIGNED DEFAULT 0 COMMENT '0-100, computed daily',
  inventory_accuracy_days_above_95 SMALLINT UNSIGNED DEFAULT 0,
  loyalty_active BOOLEAN DEFAULT FALSE,
  pos_history_days SMALLINT UNSIGNED DEFAULT 0,
  marketing_active BOOLEAN DEFAULT FALSE COMMENT 'Computed gate result',
  gate_reason TEXT COMMENT 'Why disabled if not active',
  
  -- Spend limits
  daily_spend_cap DECIMAL(10,2) DEFAULT 0,
  monthly_spend_cap DECIMAL(10,2) DEFAULT 0,
  per_campaign_cap DECIMAL(10,2) DEFAULT 0,
  current_daily_spent DECIMAL(10,2) DEFAULT 0,
  current_monthly_spent DECIMAL(10,2) DEFAULT 0,
  
  -- Safety
  kill_switch_active BOOLEAN DEFAULT FALSE,
  kill_switch_reason TEXT,
  kill_switch_at DATETIME NULL,
  
  -- Approval
  default_approval_level ENUM('auto','tinder','manual') DEFAULT 'tinder',
  
  -- Brand
  brand_voice_config JSON COMMENT 'tone, hashtags, languages',
  language VARCHAR(5) DEFAULT 'bg',
  country VARCHAR(2) DEFAULT 'BG',
  
  -- Audit
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  activated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  
  INDEX idx_tenant_active (tenant_id, marketing_active),
  INDEX idx_gate (inventory_accuracy_score, loyalty_active),
  CONSTRAINT fk_mkt_config_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  UNIQUE KEY uk_tenant (tenant_id, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Marketing AI per-tenant configuration and gates';
```

### A2. `mkt_channel_auth`
OAuth credentials за Meta/TikTok/Google MCP.

```sql
CREATE TABLE mkt_channel_auth (
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
```

### A3. `mkt_prompt_templates`
6-те агент-промпта (system prompts), versioned.

```sql
CREATE TABLE mkt_prompt_templates (
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
```

---

## ГРУПА B: КАМПАНИИ & ИЗПЪЛНЕНИЕ (5 таблици)

### B1. `mkt_campaigns`
Главна таблица за кампании.

```sql
CREATE TABLE mkt_campaigns (
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
```

### B2. `mkt_campaign_decisions`
Audit log на всяко AI решение (Закон №7).

```sql
CREATE TABLE mkt_campaign_decisions (
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
```

### B3. `mkt_creatives`
AI-генерирани/uploaded креативи.

```sql
CREATE TABLE mkt_creatives (
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
```

### B4. `mkt_audiences`
Custom audiences (lookalike, retargeting, VIP).

```sql
CREATE TABLE mkt_audiences (
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
```

### B5. `mkt_approval_queue`
Tinder UX workflow.

```sql
CREATE TABLE mkt_approval_queue (
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
```

---

## ГРУПА C: ATTRIBUTION (3 таблици) — НАЙ-КРИТИЧНАТА

### C1. `mkt_attribution_codes`
Promo codes за attribution.

```sql
CREATE TABLE mkt_attribution_codes (
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
```

### C2. `mkt_attribution_events`
Touch points (impression, click, view, visit).

```sql
CREATE TABLE mkt_attribution_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
  
  INDEX idx_tenant_time (tenant_id, event_timestamp DESC),
  INDEX idx_customer (customer_id, event_timestamp DESC),
  INDEX idx_campaign (campaign_id, event_type),
  INDEX idx_unprocessed (processed, event_timestamp),
  CONSTRAINT fk_mkt_evt_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 PARTITION BY RANGE (UNIX_TIMESTAMP(event_timestamp)) (
  PARTITION p_2026 VALUES LESS THAN (UNIX_TIMESTAMP('2027-01-01')),
  PARTITION p_2027 VALUES LESS THAN (UNIX_TIMESTAMP('2028-01-01')),
  PARTITION p_max VALUES LESS THAN MAXVALUE
) COMMENT='Attribution events (high volume - partitioned)';
```

### C3. `mkt_attribution_matches`
Reconciliation: реклама ↔ продажба.

```sql
CREATE TABLE mkt_attribution_matches (
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
```

---

## ГРУПА D: BUDGET & GUARDRAILS (2 таблици)

### D1. `mkt_budget_limits`
Hard caps per tenant per channel.

```sql
CREATE TABLE mkt_budget_limits (
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
```

### D2. `mkt_spend_log`
Daily spend per channel per campaign.

```sql
CREATE TABLE mkt_spend_log (
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
```

---

## ГРУПА E: LEARNING & OPTIMIZATION (3 таблици)

### E1. `mkt_learnings`
Teaching moments при fail/success.

```sql
CREATE TABLE mkt_learnings (
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
```

### E2. `mkt_benchmark_data`
Cross-tenant patterns (federated learning, anonymized).

```sql
CREATE TABLE mkt_benchmark_data (
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
```

### E3. `mkt_user_overrides`
Когато Митко override-ва AI препоръка.

```sql
CREATE TABLE mkt_user_overrides (
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
```

---

## ГРУПА F: SCHEDULING & TRIGGERS (2 таблици)

### F1. `mkt_scheduled_jobs`
Cron-based marketing decisions.

```sql
CREATE TABLE mkt_scheduled_jobs (
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
```

### F2. `mkt_triggers`
Auto-trigger definitions.

```sql
CREATE TABLE mkt_triggers (
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
```

---

## ГРУПА H: ONLINE STORE (7 таблици)

### H1. `online_stores`
Ecwid магазин на tenant.

```sql
CREATE TABLE online_stores (
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
```

### H2. `online_store_products`
Mapping RunMyStore products ↔ Ecwid products.

```sql
CREATE TABLE online_store_products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  
  runmystore_product_id INT UNSIGNED NOT NULL,
  ecwid_product_id VARCHAR(50) NOT NULL,
  
  -- For variations
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
```

### H3. `online_store_orders`
Поръчки от Ecwid.

```sql
CREATE TABLE online_store_orders (
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
  
  -- Routing
  assigned_store_id INT UNSIGNED NULL COMMENT 'Which physical store handles this',
  routing_reason ENUM('base_warehouse','most_quantity','manual','closest','only_available') NULL,
  routing_decision_at DATETIME NULL,
  routing_attempts INT UNSIGNED DEFAULT 0,
  
  status ENUM('received','reserved','processing','packed','shipped','delivered','cancelled','refunded') DEFAULT 'received',
  
  shipping_method VARCHAR(100),
  shipping_tracking_number VARCHAR(255),
  
  -- Attribution
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
```

### H4. `online_store_inventory_locks`
Резервации (30-минутни локове).

```sql
CREATE TABLE online_store_inventory_locks (
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
```

### H5. `online_store_routing_log`
Audit на всеки routing decision.

```sql
CREATE TABLE online_store_routing_log (
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
```

### H6. `online_store_sync_log`
Audit на sync events.

```sql
CREATE TABLE online_store_sync_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
  
  INDEX idx_tenant_time (tenant_id, synced_at DESC),
  INDEX idx_store_status (store_id, status, sync_type),
  INDEX idx_entity (entity_id, sync_type),
  CONSTRAINT fk_sync_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_sync_store FOREIGN KEY (store_id) REFERENCES online_stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 PARTITION BY RANGE (UNIX_TIMESTAMP(synced_at)) (
  PARTITION p_2026 VALUES LESS THAN (UNIX_TIMESTAMP('2027-01-01')),
  PARTITION p_2027 VALUES LESS THAN (UNIX_TIMESTAMP('2028-01-01')),
  PARTITION p_max VALUES LESS THAN MAXVALUE
) COMMENT='Sync events log';
```

### H7. `online_store_settings`
Multi-store sync настройки.

```sql
CREATE TABLE online_store_settings (
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
```

---

## ALTER ОПЕРАЦИИ (9 СЪЩЕСТВУВАЩИ ТАБЛИЦИ)

⚠️ **Важно:** MySQL не поддържа `ADD COLUMN IF NOT EXISTS`. Изпълнявайте отделни ALTER операции с проверка преди това.

### ALTER 1: `tenants`

```sql
ALTER TABLE tenants 
  ADD COLUMN marketing_tier ENUM('disabled','shadow','lite','standard','pro','enterprise') DEFAULT 'disabled',
  ADD COLUMN marketing_activated_at DATETIME NULL,
  ADD COLUMN marketing_total_spend_lifetime DECIMAL(12,2) DEFAULT 0,
  ADD COLUMN marketing_total_revenue_attributed DECIMAL(12,2) DEFAULT 0,
  ADD COLUMN online_store_active BOOLEAN DEFAULT FALSE,
  ADD COLUMN online_store_id INT UNSIGNED NULL,
  ADD COLUMN online_store_tier ENUM('none','lite','standard','pro') DEFAULT 'none',
  ADD INDEX idx_marketing_tier (marketing_tier);
```

### ALTER 2: `customers`

```sql
ALTER TABLE customers
  ADD COLUMN last_seen_in_ad_at DATETIME NULL,
  ADD COLUMN acquired_via_campaign_id INT UNSIGNED NULL,
  ADD COLUMN total_attributed_revenue DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN marketing_consent BOOLEAN DEFAULT FALSE,
  ADD COLUMN marketing_consent_at DATETIME NULL,
  ADD INDEX idx_acquired_campaign (acquired_via_campaign_id),
  ADD INDEX idx_consent (marketing_consent),
  ADD CONSTRAINT fk_cust_campaign FOREIGN KEY (acquired_via_campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL;
```

### ALTER 3: `sales`

```sql
ALTER TABLE sales
  ADD COLUMN attribution_campaign_id INT UNSIGNED NULL,
  ADD COLUMN attribution_method VARCHAR(50) NULL,
  ADD COLUMN promo_code_used VARCHAR(50) NULL,
  ADD COLUMN attribution_confidence TINYINT UNSIGNED NULL,
  ADD COLUMN source ENUM('physical','online','phone','social') DEFAULT 'physical',
  ADD COLUMN ecwid_order_id VARCHAR(50) NULL,
  ADD INDEX idx_campaign (attribution_campaign_id),
  ADD INDEX idx_source (source),
  ADD INDEX idx_ecwid_order (ecwid_order_id),
  ADD CONSTRAINT fk_sales_campaign FOREIGN KEY (attribution_campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL;
```

### ALTER 4: `products`

```sql
ALTER TABLE products
  ADD COLUMN last_promoted_at DATETIME NULL,
  ADD COLUMN total_ad_spend DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN total_attributed_revenue DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN promotion_eligibility_score TINYINT UNSIGNED DEFAULT 0,
  ADD COLUMN is_zombie BOOLEAN DEFAULT FALSE,
  ADD COLUMN zombie_since DATETIME NULL,
  ADD COLUMN online_published BOOLEAN DEFAULT FALSE,
  ADD COLUMN online_url VARCHAR(500) NULL,
  ADD COLUMN online_seo_title VARCHAR(255) NULL,
  ADD COLUMN online_seo_description TEXT NULL,
  ADD COLUMN online_first_published_at DATETIME NULL,
  ADD INDEX idx_zombie (is_zombie, zombie_since),
  ADD INDEX idx_online_published (online_published);
```

### ALTER 5: `users`

```sql
ALTER TABLE users
  ADD COLUMN marketing_role ENUM('none','viewer','approver','admin') DEFAULT 'none',
  ADD COLUMN voice_feedback_count INT UNSIGNED DEFAULT 0,
  ADD INDEX idx_marketing_role (marketing_role);
```

### ALTER 6: `inventory`

```sql
ALTER TABLE inventory
  ADD COLUMN reserved_quantity INT UNSIGNED DEFAULT 0 COMMENT 'Locked for online orders',
  ADD COLUMN available_for_online_quantity INT UNSIGNED GENERATED ALWAYS AS (GREATEST(quantity - reserved_quantity, 0)) STORED,
  ADD COLUMN accuracy_last_check_at DATETIME NULL,
  ADD COLUMN accuracy_score TINYINT UNSIGNED DEFAULT 100,
  ADD INDEX idx_available_online (available_for_online_quantity);
```

### ALTER 7: `stores`

```sql
ALTER TABLE stores
  ADD COLUMN is_base_warehouse BOOLEAN DEFAULT FALSE,
  ADD COLUMN online_orders_enabled BOOLEAN DEFAULT FALSE,
  ADD COLUMN average_processing_time_min SMALLINT UNSIGNED DEFAULT 30,
  ADD INDEX idx_base_warehouse (is_base_warehouse, online_orders_enabled);
```

### ALTER 8: `promotions` (когато е създадена в Phase D)

```sql
ALTER TABLE promotions
  ADD COLUMN marketing_campaign_id INT UNSIGNED NULL,
  ADD COLUMN auto_generated_for_attribution BOOLEAN DEFAULT FALSE,
  ADD INDEX idx_marketing_campaign (marketing_campaign_id),
  ADD CONSTRAINT fk_promo_campaign FOREIGN KEY (marketing_campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL;
```

### ALTER 9: `loyalty_points_log` (ако съществува)

```sql
ALTER TABLE loyalty_points_log
  ADD COLUMN attributed_to_campaign_id INT UNSIGNED NULL,
  ADD INDEX idx_attribution (attributed_to_campaign_id),
  ADD CONSTRAINT fk_loyalty_campaign FOREIGN KEY (attributed_to_campaign_id) REFERENCES mkt_campaigns(id) ON DELETE SET NULL;
```

---

# 4. API ИНТЕГРАЦИИ

## Ecwid REST API

**Base URL:** `https://app.ecwid.com/api/v3/{store_id}`

**Authentication:** OAuth Bearer token

**Key Endpoints:**

```
POST /products              → Create product
PUT  /products/{id}         → Update product
GET  /products              → List products
DELETE /products/{id}       → Delete product

POST /products/{id}/variations  → Add variation
PUT  /products/{id}/variations/{vid}  → Update variation

GET  /orders                → List orders
PUT  /orders/{id}           → Update order status

POST /discount_coupons      → Create promo code
PUT  /discount_coupons/{id} → Update

GET  /products?inStock=true → Inventory check
PUT  /products/{id}?quantity={n} → Update inventory
```

**Webhooks (incoming):**

```
order.created       → Trigger routing logic
order.updated       → Update internal status
product.updated     → Sync back to RunMyStore
inventory.adjusted  → Reconcile с physical POS
```

**Rate Limit:** 600 requests/minute per token (sufficient for 5-min sync)

## Meta Graph API (via MCP)

**MCP Server URL:** Predоставен от Meta

**Capabilities:**
- Create campaign / ad set / ad
- Update budget / status
- Pull metrics (impressions, clicks, conversions, spend)
- Manage product catalog
- Generate audiences (lookalike, custom)
- Create creatives (Advantage+)

## TikTok Ads API (via MCP)

**MCP Server:** Pipeboard или Composio

**Capabilities:**
- Symphony Creative Studio integration (генериране на видеа)
- Smart+ campaigns
- Pull metrics
- Audience management

## Google Ads API (via MCP)

**Capabilities:**
- Performance Max campaigns
- Search campaigns
- Google My Business posts
- Reporting

## Stripe Connect

**Setup:** Stripe Connect Standard — клиентите свързват своите Stripe акаунти.

**Key features:**
- 3D Secure
- SCA compliance
- Automated payouts
- Multi-currency

---

# 5. INVENTORY SYNC ENGINE

## Sync интервал: 5 минути

**Cron job:** `*/5 * * * * /usr/bin/php /var/www/runmystore/cron/ecwid_sync.php`

## Flow

### Push (RunMyStore → Ecwid)

```
1. Find all products with sync_status = 'pending' OR (last_synced_at < NOW() - 5min)
2. For each product:
   - Build Ecwid payload (name, description, price, images, variations)
   - Apply priceFormat($amount, $tenant) for currency
   - Call Ecwid API: PUT /products/{ecwid_id}
   - On success: update sync_status='synced', last_synced_at=NOW()
   - On failure: increment sync_attempts, log error, retry next cycle
3. Bulk update inventory:
   - For each variation with quantity changes:
     - Call: PUT /products/{id}/variations/{vid} with new quantity
4. Log all operations to online_store_sync_log
```

### Pull (Ecwid → RunMyStore via Webhook)

```
Webhook: order.created
↓
1. Validate signature
2. Insert into online_store_orders (status='received')
3. Trigger routing engine (см. Section 6)
4. Reduce inventory across all stores via lock
5. Notify chosen physical store (push notification)
6. Insert into mkt_attribution_events (event_type='purchase')
7. Match to campaign via promo_code → insert into mkt_attribution_matches
```

### Conflict Resolution

```
Сценарий: Клиент купи онлайн в 14:32:00, Пешо продаде в магазина в 14:32:02
↓
Last write wins, но:
- Online order вече има 30-min lock
- Physical sale ще получи "INSUFFICIENT_STOCK" грешка
- Pешo вижда: "Тази стока е резервирана за онлайн поръчка #1234. Override?"
- Ако настройката allow_physical_override = TRUE → може да override
- Ако FALSE → не може, очаква expiration на lock
```

---

# 6. MULTI-STORE ROUTING LOGIC

## Алгоритъм (от твоето решение)

```php
function routeOnlineOrder($order) {
    $tenant_id = $order->tenant_id;
    $settings = OnlineStoreSettings::forTenant($tenant_id);
    
    foreach ($order->items as $item) {
        // ШТЪПКА 1: Проверка на базов склад
        if ($settings->base_warehouse_store_id) {
            $base_qty = Inventory::available(
                $settings->base_warehouse_store_id, 
                $item->product_id, 
                $item->variation_id
            );
            
            if ($base_qty >= $item->quantity) {
                return assignToStore(
                    $settings->base_warehouse_store_id, 
                    'base_warehouse'
                );
            }
        }
        
        // ШТЪПКА 2: Кой магазин има най-много количество
        $candidates = Inventory::storesWithProduct(
            $tenant_id, 
            $item->product_id, 
            $item->variation_id, 
            $item->quantity
        );
        
        if (empty($candidates)) {
            // НИКОЙ няма стоката → fail
            return [
                'status' => 'failed', 
                'reason' => 'out_of_stock_all_stores'
            ];
        }
        
        // Sort by quantity DESC
        usort($candidates, fn($a, $b) => $b['quantity'] - $a['quantity']);
        $chosen = $candidates[0];
        
        return assignToStore($chosen['store_id'], 'most_quantity');
    }
}

function assignToStore($store_id, $reason) {
    // Create lock
    InventoryLock::create([
        'product_id' => $product_id,
        'physical_store_id' => $store_id,
        'quantity_locked' => $quantity,
        'expires_at' => now()->addMinutes(30),
        'status' => 'active'
    ]);
    
    // Update order
    Order::update([
        'assigned_store_id' => $store_id,
        'routing_reason' => $reason,
        'status' => 'reserved'
    ]);
    
    // Update inventory.reserved_quantity
    Inventory::increment(
        $store_id, 
        $product_id, 
        'reserved_quantity', 
        $quantity
    );
    
    // Log
    RoutingLog::insert([...]);
    
    // Notify physical store
    PushNotification::toStore($store_id, [
        'title' => 'Нова онлайн поръчка',
        'body' => '...'
    ]);
}
```

## Lock Expiration Cron

```
Cron: */1 * * * * (every minute)
↓
SELECT * FROM online_store_inventory_locks 
WHERE status='active' AND expires_at < NOW();

For each expired lock:
  - Update inventory.reserved_quantity -= quantity_locked
  - Update lock.status = 'expired'
  - If order still in 'reserved' status:
    - Re-route to another store
    - If no store available → cancel order
    - Notify customer + tenant
```

---

# 7. AI COST OPTIMIZATION

## 80/20 Rule (Cost Architecture)

**80% — PHP Rules + Gemini Flash (€0.001-0.005/call):**
- Inventory scanning (zombie detection)
- Lost demand detection
- Dormant customer identification
- Daily reports
- Routing decisions

**20% — Sonnet 4.6 (€0.012/call):**
- Campaign creation decisions
- Creative briefs
- Performance analysis
- Recovery strategies

**0.5% — Opus 4.7 (€0.075/call):**
- Strategic monthly review
- Complex performance diagnostics
- Cross-tenant pattern detection

## Hard Cap Implementation

```php
// Преди всеки Opus call:
$current_month_opus_calls = MktDecision::where([
    'tenant_id' => $tenant_id,
    'model_used' => 'opus',
    'created_at' => '>=' . startOfMonth()
])->count();

if ($current_month_opus_calls >= $tenant->max_opus_per_month) {
    // Fallback to Sonnet
    $model = 'sonnet';
} else {
    $model = 'opus';
}
```

## Caching Strategy

```php
// Context analysis caching
$cache_key = "mkt_context_{$tenant_id}_{$date}";
$context = Cache::remember($cache_key, 3600, function() use ($tenant_id) {
    return ContextEngine::buildFor($tenant_id);
});
```

**Cache durations:**
- Inventory snapshot: 5 минути
- Customer segments: 1 час
- Sales patterns: 6 часа
- Product margins: 24 часа
- Brand voice config: 7 дни

## Total Cost Estimation

При 1000 tenants:

| Operation | Frequency | Total/мес | Cost/мес |
|-----------|-----------|-----------|----------|
| Daily scanning (Flash) | 30 × 1000 | 30,000 calls | €30 |
| Decisions (Sonnet) | 5 × 1000 | 5,000 calls | €60 |
| Strategy (Opus) | 2 × 1000 | 2,000 calls | €150 |
| Routing (PHP) | unlimited | - | €0 |
| **Total** | | | **€240/мес** |

**Per tenant: €0.24/мес AI cost. При €99-249 цена → 96-99% gross margin на AI alone.**

---

# 8. MIGRATION ПЛАН

## Phase 1: ПРАЗНИ таблици (преди ENI beta - до 14 май 2026)

**Защо сега:**
- Schema е замразена → не пренаписваме при активиране
- ALTER на празни tenants = instant
- ALTER на ENI таблици след beta = риск (real data)

**Стъпки:**

```bash
# 1. Backup
mysqldump runmystore > /root/backup_pre_marketing_$(date +%Y%m%d).sql

# 2. Изпълни schema (от файла marketing_schema.sql)
mysql runmystore < /var/www/runmystore/migrations/marketing_schema.sql

# 3. Validate
mysql -e "SHOW TABLES LIKE 'mkt_%';" runmystore
mysql -e "SHOW TABLES LIKE 'online_%';" runmystore

# 4. Insert default data
mysql runmystore < /var/www/runmystore/migrations/marketing_seed.sql

# 5. Commit to git
cd /var/www/runmystore
git add migrations/
git commit -m "S100: Marketing AI + Online Store schema (empty tables)"
git push origin main
```

## Phase 2: ENI Beta (14 май - 31 юли 2026)

- `mkt_tenant_config` за ENI: `tier='disabled'`, `marketing_active=FALSE`
- Inventory accuracy scoring започва (passive)
- `mkt_attribution_events` collect-ва pixel данни (passive)

## Phase 3: Shadow за Тихол (август-септември 2026)

- `tier='shadow'` за магазините на Тихол
- AI пише в `mkt_campaign_decisions` без да изпълнява
- Validation на промптите

## Phase 4: Live за Тихол (октомври-декември 2026)

- `tier='pro'` за Тихол
- Реални campaigns
- Attribution validation

## Phase 5: Closed Beta (Q1 2027)

- 5-10 партньорски tenants
- Production data в реални таблици

---

# 9. CODE EXAMPLES за критични модули

## Inventory Accuracy Gate

```php
// /var/www/runmystore/lib/MarketingGate.php

class MarketingGate {
    public static function checkActivation($tenant_id) {
        $config = MktTenantConfig::forTenant($tenant_id);
        
        // Check 1: Inventory accuracy
        $accuracy = self::calculateAccuracy($tenant_id, days: 30);
        if ($accuracy < 95) {
            return [
                'active' => false,
                'reason' => "Inventory accuracy: {$accuracy}% (нужни 95%+)"
            ];
        }
        
        // Check 2: Loyalty active
        if (!$config->loyalty_active) {
            return [
                'active' => false,
                'reason' => 'Loyalty модул не е активен (нужен за attribution)'
            ];
        }
        
        // Check 3: POS history
        $pos_days = self::posHistoryDays($tenant_id);
        if ($pos_days < 60) {
            return [
                'active' => false,
                'reason' => "POS история: {$pos_days} дни (нужни 60+)"
            ];
        }
        
        return ['active' => true, 'reason' => null];
    }
}
```

## Attribution Match Engine

```php
// /var/www/runmystore/lib/AttributionEngine.php

class AttributionEngine {
    public static function matchSale($sale) {
        $matches = [];
        
        // Method 1: Promo code
        if ($sale->promo_code_used) {
            $code = MktAttributionCode::byCode($sale->promo_code_used);
            if ($code) {
                $matches[] = [
                    'campaign_id' => $code->campaign_id,
                    'method' => 'promo_code',
                    'confidence' => 95
                ];
            }
        }
        
        // Method 2: Loyalty match
        if ($sale->customer_id) {
            $recent_clicks = MktAttributionEvent::recentClicksFor(
                $sale->customer_id, 
                hours: 72
            );
            
            foreach ($recent_clicks as $click) {
                $matches[] = [
                    'campaign_id' => $click->campaign_id,
                    'method' => 'loyalty_match',
                    'confidence' => 70 - ($click->hours_ago * 5)
                ];
            }
        }
        
        // Method 3: Voice asked
        if ($sale->attribution_method === 'voice_asked') {
            $matches[] = [
                'campaign_id' => $sale->attribution_campaign_id,
                'method' => 'voice_asked',
                'confidence' => 90
            ];
        }
        
        // Save best match
        if (!empty($matches)) {
            usort($matches, fn($a, $b) => $b['confidence'] - $a['confidence']);
            MktAttributionMatch::create([
                'tenant_id' => $sale->tenant_id,
                'campaign_id' => $matches[0]['campaign_id'],
                'sale_id' => $sale->id,
                'match_method' => $matches[0]['method'],
                'match_confidence' => $matches[0]['confidence'],
                'revenue_attributed' => $sale->total
            ]);
        }
    }
}
```

---

# 10. MONITORING & ALERTS

## Critical Metrics (Grafana/CloudWatch)

| Metric | Alert Threshold | Action |
|--------|----------------|--------|
| Ecwid API errors | >10/мин | Pause sync, notify ops |
| Inventory sync delay | >15 мин | Investigate |
| Marketing AI cost | >€50/tenant/day | Auto-pause |
| Attribution match rate | <40% | Investigate |
| Lock expiration rate | >30% | Investigate routing |
| AI confidence avg | <0.6 | Review prompts |
| Inventory accuracy | <90% | Disable Marketing AI |

## Daily Health Report

Cron 8:00 ежедневно праща email до Тихол:

```
RunMyStore — Marketing AI Health
==============================

Active tenants: 142
Total spend yesterday: €2,847
Total revenue attributed: €12,392
ROI yesterday: 4.35×

Top performers:
- Tenant #47 (ENI): ROI 8.2×
- Tenant #112: ROI 6.7×

Issues:
⚠️ 3 tenants below 95% inventory accuracy
⚠️ 1 tenant kill-switched (manual)

Cost yesterday: €4.20 (1,800 AI calls)
```

---

# 11. TESTING STRATEGY

## Unit Tests
- Routing algorithm (10 scenarios)
- Attribution matching (15 scenarios)
- Lock expiration handling
- Gate activation logic
- Confidence scoring

## Integration Tests
- Ecwid sync round-trip
- Meta MCP campaign creation
- Webhook handling
- Promo code generation

## Load Tests
- 1000 concurrent inventory updates
- 100 simultaneous online orders
- 5-minute sync cycle с 30k SKU
- Database performance under load

## End-to-end Tests
- Pешo продава → Ecwid обновява за <30 сек
- Online поръчка → routing → store notification → fulfillment
- Marketing AI препоръка → одобрение → Meta campaign live
- Promo code → checkout → attribution match

---

# 12. COMPLIANCE

## GDPR

- Customer data tokenized в `mkt_attribution_events.customer_id`
- IP hashes (не raw)
- Right to delete: cascade през всички marketing таблици
- Consent tracked в `customers.marketing_consent`
- Data residency: AWS Frankfurt (EU)

## EU AI Act (от август 2026)

- AI label на всички AI-генерирани креативи (`mkt_creatives.ai_label_required`)
- Audit log на decisions (`mkt_campaign_decisions`)
- Human-in-the-loop framework (Tinder approval)
- Transparency на automated decisions (reasoning column)
- SME carve-out: ще приложим за облекчения

## PCI Compliance

- Не съхраняваме card data — Stripe handles
- Stripe Connect Standard за делегиране на отговорност
- All payment events през Stripe webhooks

## Bulgarian Specific

- Двойна валута до 08.08.2026 (EUR + BGN)
- Курс 1.95583
- ДДС: 20% по подразбиране
- Касови бонове integration (където нужно)

---

# КРАЙ НА ТЕХНИЧЕСКАТА БИБЛИЯ

## SQL файл за изпълнение

**Файл:** `/var/www/runmystore/migrations/marketing_schema.sql`
**Размер:** ~25 нови таблици + 9 ALTER операции
**Изпълнение:** Преди ENI beta (до 14 май 2026)
**Backup задължителен:** `mysqldump preди`

## Implementation последователност

1. **Седмица 1 (4-10 май):** Backup + schema migration (празни таблици)
2. **Седмица 2-4 (11 май - 7 юни):** Ecwid integration foundation
3. **Седмица 5-8 (8 юни - 5 юли):** Marketing AI shadow mode
4. **Седмица 9-16 (юли-август):** Live тестване на Тихол магазини
5. **Q4 2026:** Полирaane + готовност за closed beta

## За Шеф чата

**Включи в roadmap:**
- S100-S110: Marketing AI core implementation
- S111-S115: Online Store provisioning
- S116-S120: Inventory routing engine
- S121-S125: Attribution engine
- S126-S130: AI agents implementation

**Зависимости:**
- Loyalty модул трябва да е готов
- Promotions модул (Phase D) трябва да е готов
- Inventory accuracy gate трябва да е работещ

---

*Документ финализиран: 03 май 2026*
*Версия: 1.0*
*Owner: Тихол Енев*
*За включване в RunMyStore документация*
