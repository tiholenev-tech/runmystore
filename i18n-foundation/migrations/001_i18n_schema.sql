-- ═══════════════════════════════════════════════════════════
-- Migration: i18n-ready foundation (S149)
-- Дата: 17.05.2026
-- Описание: добавя локализационни полета + tax jurisdiction routing
-- Изпълнение в MySQL клиент или phpMyAdmin
-- ═══════════════════════════════════════════════════════════

-- ───── 1. Tenant локализация ─────
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='locale') = 0,
  'ALTER TABLE tenants ADD COLUMN locale VARCHAR(5) NOT NULL DEFAULT ''bg-BG'' AFTER name',
  'SELECT ''locale exists'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='country_code') = 0,
  'ALTER TABLE tenants ADD COLUMN country_code CHAR(2) NOT NULL DEFAULT ''BG'' AFTER locale',
  'SELECT ''country_code exists'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='currency') = 0,
  'ALTER TABLE tenants ADD COLUMN currency CHAR(3) NOT NULL DEFAULT ''EUR'' AFTER country_code',
  'SELECT ''currency exists'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='timezone') = 0,
  'ALTER TABLE tenants ADD COLUMN timezone VARCHAR(50) NOT NULL DEFAULT ''Europe/Sofia'' AFTER currency',
  'SELECT ''timezone exists'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='tax_jurisdiction') = 0,
  'ALTER TABLE tenants ADD COLUMN tax_jurisdiction CHAR(2) NOT NULL DEFAULT ''BG'' AFTER timezone',
  'SELECT ''tax_jurisdiction exists'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='product') = 0,
  'ALTER TABLE tenants ADD COLUMN product ENUM(''store'',''wallet'') NOT NULL DEFAULT ''store'' AFTER tax_jurisdiction',
  'SELECT ''product exists'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;


-- ───── 2. Country config table ─────
CREATE TABLE IF NOT EXISTS country_config (
  country_code CHAR(2) NOT NULL PRIMARY KEY,
  name_en VARCHAR(60) NOT NULL,
  currency CHAR(3) NOT NULL,
  default_locale VARCHAR(5) NOT NULL,
  default_timezone VARCHAR(50) NOT NULL,
  
  -- VAT / tax
  vat_threshold_eur DECIMAL(10,2) DEFAULT NULL,
  vat_rate_standard DECIMAL(4,2) DEFAULT NULL,
  vat_rate_reduced DECIMAL(4,2) DEFAULT NULL,
  npr_rates_json JSON DEFAULT NULL,
  patent_tax_available BOOLEAN DEFAULT FALSE,
  income_tax_rate DECIMAL(4,2) DEFAULT NULL,
  
  -- Social security
  social_security_min_eur DECIMAL(10,2) DEFAULT NULL,
  social_security_max_eur DECIMAL(10,2) DEFAULT NULL,
  social_security_rate DECIMAL(4,3) DEFAULT NULL,
  
  -- Calendar
  declaration_deadline_md VARCHAR(5) DEFAULT NULL,
  quarterly_advance_deadlines JSON DEFAULT NULL,
  
  -- Status
  tax_engine_class VARCHAR(50) NOT NULL DEFAULT 'GenericTaxEngine',
  active BOOLEAN DEFAULT FALSE,
  launched_at DATE NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ───── 3. UI translations override table ─────
CREATE TABLE IF NOT EXISTS ui_translations (
  key_name VARCHAR(150) NOT NULL,
  locale VARCHAR(5) NOT NULL,
  value TEXT NOT NULL,
  context VARCHAR(50) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (key_name, locale),
  KEY idx_locale (locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ───── 4. Profession templates с translations ─────
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profession_templates' AND COLUMN_NAME='name_translations') = 0,
  'ALTER TABLE profession_templates ADD COLUMN name_translations JSON DEFAULT NULL AFTER name_en',
  'SELECT ''name_translations exists'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profession_templates' AND COLUMN_NAME='sub_categories_translations') = 0,
  'ALTER TABLE profession_templates ADD COLUMN sub_categories_translations JSON DEFAULT NULL AFTER sub_categories',
  'SELECT ''sub_categories_translations exists'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;


-- ───── 5. AI topics с translations ─────
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_topics') > 0,
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS 
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_topics' AND COLUMN_NAME='prompt_translations') = 0,
     'ALTER TABLE ai_topics ADD COLUMN prompt_translations JSON DEFAULT NULL',
     'SELECT ''prompt_translations exists'''),
  'SELECT ''ai_topics table not found, skipping'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;


-- ───── 6. SEED: Country config (BG ден 1 + другите prepared) ─────
INSERT IGNORE INTO country_config 
  (country_code, name_en, currency, default_locale, default_timezone,
   vat_threshold_eur, vat_rate_standard, vat_rate_reduced, npr_rates_json,
   patent_tax_available, income_tax_rate,
   social_security_min_eur, social_security_max_eur, social_security_rate,
   declaration_deadline_md, quarterly_advance_deadlines,
   tax_engine_class, active, launched_at)
VALUES
-- ДЕН 1: BG ACTIVE
('BG', 'Bulgaria', 'EUR', 'bg-BG', 'Europe/Sofia',
 51130.00, 20.00, 9.00,
 '{"freelance":25,"craft":40,"agri":60}',
 TRUE, 10.00,
 551.00, 1918.00, 0.278,
 '04-30', '["04-15","07-15","10-15","01-15"]',
 'BGTaxEngine', TRUE, '2026-06-01'),

-- PHASE 2: prepared, не active
('RO', 'Romania', 'RON', 'ro-RO', 'Europe/Bucharest',
 60000.00, 19.00, 9.00,
 '{"freelance":40,"craft":40,"agri":40}',
 FALSE, 10.00,
 NULL, NULL, NULL,
 '05-25', '["05-25","09-25","12-25","03-25"]',
 'ROTaxEngine', FALSE, NULL),

('GR', 'Greece', 'EUR', 'el-GR', 'Europe/Athens',
 10000.00, 24.00, 13.00,
 '{"freelance":30,"craft":30,"agri":30}',
 FALSE, 22.00,
 NULL, NULL, NULL,
 '06-30', '["07-31","10-31","02-28"]',
 'GRTaxEngine', FALSE, NULL),

-- PHASE 3: EN markets с GenericTaxEngine (no tax features)
('US', 'United States', 'USD', 'en-US', 'America/New_York',
 NULL, NULL, NULL, NULL, FALSE, NULL,
 NULL, NULL, NULL, NULL, NULL,
 'GenericTaxEngine', FALSE, NULL),

('GB', 'United Kingdom', 'GBP', 'en-GB', 'Europe/London',
 NULL, 20.00, 5.00, NULL, FALSE, NULL,
 NULL, NULL, NULL, NULL, NULL,
 'GenericTaxEngine', FALSE, NULL),

('DE', 'Germany', 'EUR', 'de-DE', 'Europe/Berlin',
 22000.00, 19.00, 7.00, NULL, FALSE, NULL,
 NULL, NULL, NULL, NULL, NULL,
 'DETaxEngine', FALSE, NULL),

('ES', 'Spain', 'EUR', 'es-ES', 'Europe/Madrid',
 NULL, 21.00, 10.00, NULL, FALSE, NULL,
 NULL, NULL, NULL, NULL, NULL,
 'GenericTaxEngine', FALSE, NULL),

('IT', 'Italy', 'EUR', 'it-IT', 'Europe/Rome',
 65000.00, 22.00, 10.00, NULL, FALSE, NULL,
 NULL, NULL, NULL, NULL, NULL,
 'GenericTaxEngine', FALSE, NULL),

('FR', 'France', 'EUR', 'fr-FR', 'Europe/Paris',
 36800.00, 20.00, 5.50, NULL, FALSE, NULL,
 NULL, NULL, NULL, NULL, NULL,
 'GenericTaxEngine', FALSE, NULL);


-- ───── 7. Index за performance ─────
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND INDEX_NAME='idx_locale') = 0,
  'ALTER TABLE tenants ADD INDEX idx_locale (locale)',
  'SELECT ''idx_locale exists'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND INDEX_NAME='idx_country_code') = 0,
  'ALTER TABLE tenants ADD INDEX idx_country_code (country_code)',
  'SELECT ''idx_country_code exists'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;


-- ═══════════════════════════════════════════════════════════
-- DONE. Verify:
SELECT 'tenants' AS tbl, COUNT(*) AS rows_count FROM tenants
UNION ALL SELECT 'country_config', COUNT(*) FROM country_config
UNION ALL SELECT 'ui_translations', COUNT(*) FROM ui_translations;
-- ═══════════════════════════════════════════════════════════
