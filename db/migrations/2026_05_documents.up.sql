-- ════════════════════════════════════════════════════════════════════════════
-- MIGRATION: 2026_05_documents.up.sql
-- Дата: 09.05.2026
-- Цел: Унифицирана база за партньори (доставчици+клиенти+B2B), 16 типа документи
--      по ЗДДС, фактурни серии с 10-разрядна номерация, EIK lookup кеш.
-- Свързани RWQ: 88, 89, 90, 91, 92, 93
-- ════════════════════════════════════════════════════════════════════════════

-- ─── 1. partners (RWQ-89) ────────────────────────────────────────────────────
-- Един контрагент може да е едновременно доставчик, клиент, B2B, институция.
CREATE TABLE IF NOT EXISTS partners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  legal_name VARCHAR(255) NOT NULL,
  display_name VARCHAR(255),
  -- Идентификатори (БГ-специфика)
  eik VARCHAR(20),
  vat_number VARCHAR(20),
  egn VARCHAR(20),
  lnch VARCHAR(20),
  national_id_type ENUM('eik','vat','egn','lnch','foreign','other') DEFAULT 'eik',
  -- Контакт
  address TEXT,
  city VARCHAR(100),
  postal_code VARCHAR(20),
  country_code CHAR(2) DEFAULT 'BG',
  representative VARCHAR(255),
  phone VARCHAR(50),
  email VARCHAR(255),
  -- Роли (множествени)
  is_supplier TINYINT(1) DEFAULT 0,
  is_customer TINYINT(1) DEFAULT 0,
  is_b2b TINYINT(1) DEFAULT 0,
  is_institution TINYINT(1) DEFAULT 0,
  is_natural_person TINYINT(1) DEFAULT 0,
  -- Търговски параметри
  payment_terms VARCHAR(50),
  reliability_score TINYINT,
  credit_limit DECIMAL(12,2),
  -- ДДС статус
  is_vat_registered TINYINT(1) DEFAULT 0,
  vat_registration_date DATE NULL,
  -- Кеш и метаданни
  notes TEXT,
  eik_cache_updated_at DATETIME NULL,
  status ENUM('active','inactive','blocked') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED,
  -- Индекси
  INDEX idx_tenant_eik (tenant_id, eik),
  INDEX idx_tenant_vat (tenant_id, vat_number),
  INDEX idx_tenant_supplier (tenant_id, is_supplier, status),
  INDEX idx_tenant_customer (tenant_id, is_customer, status),
  INDEX idx_tenant_search (tenant_id, legal_name(64)),
  CONSTRAINT chk_partner_role CHECK (is_supplier=1 OR is_customer=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 2. partner_aliases ──────────────────────────────────────────────────────
-- Историческо име, DBA, неформално — за search.
CREATE TABLE IF NOT EXISTS partner_aliases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id INT UNSIGNED NOT NULL,
  alias VARCHAR(255) NOT NULL,
  alias_type ENUM('historical_name','dba','informal') DEFAULT 'informal',
  INDEX idx_partner (partner_id),
  INDEX idx_alias (alias(64)),
  FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 3. eik_cache (RWQ-88) ───────────────────────────────────────────────────
-- Кеш на резултатите от Български търговски регистър API.
CREATE TABLE IF NOT EXISTS eik_cache (
  eik VARCHAR(20) PRIMARY KEY,
  legal_name VARCHAR(255),
  vat_number VARCHAR(20),
  address TEXT,
  city VARCHAR(100),
  postal_code VARCHAR(20),
  representative VARCHAR(255),
  registration_date DATE,
  status ENUM('active','liquidated','suspended','unknown') DEFAULT 'unknown',
  source VARCHAR(50),
  raw_response JSON,
  cached_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME,
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 4. document_series (RWQ-90) ─────────────────────────────────────────────
-- Multi-серии per tenant per store per категория документи.
-- ЗДДС: данъчните (фактури + известия + протоколи) споделят една серия.
CREATE TABLE IF NOT EXISTS document_series (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  store_id INT UNSIGNED NULL,
  document_category ENUM(
    'tax',
    'cash_receipt',
    'proforma',
    'goods_note',
    'storage_note',
    'cash_order',
    'transfer_protocol',
    'warranty',
    'shipping_note',
    'offer'
  ) NOT NULL,
  series_name VARCHAR(50) NOT NULL,
  prefix VARCHAR(10) DEFAULT '',
  start_number BIGINT UNSIGNED NOT NULL,
  end_number BIGINT UNSIGNED NOT NULL,
  current_number BIGINT UNSIGNED NOT NULL,
  number_padding TINYINT DEFAULT 10,
  is_active TINYINT(1) DEFAULT 0,
  is_emergency TINYINT(1) DEFAULT 0,
  is_locked TINYINT(1) DEFAULT 0,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_by INT UNSIGNED,
  INDEX idx_tenant_active (tenant_id, store_id, document_category, is_active),
  CONSTRAINT chk_series_range CHECK (start_number <= end_number),
  CONSTRAINT chk_series_current CHECK (current_number BETWEEN start_number AND end_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 5. document_series_changes ──────────────────────────────────────────────
-- Audit trail за всяка ръчна смяна на номерация (ЗДДС изискване — без пропуски).
CREATE TABLE IF NOT EXISTS document_series_changes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  series_id INT UNSIGNED NOT NULL,
  changed_field VARCHAR(50) NOT NULL,
  old_value TEXT,
  new_value TEXT,
  reason TEXT,
  changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  changed_by INT UNSIGNED,
  ip_address VARCHAR(45),
  INDEX idx_series_date (series_id, changed_at),
  FOREIGN KEY (series_id) REFERENCES document_series(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 6. documents (RWQ-92) ───────────────────────────────────────────────────
-- Централна таблица — всички 16 типа документи живеят тук.
CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  store_id INT UNSIGNED NULL,
  series_id INT UNSIGNED NULL,
  doc_type ENUM(
    'cash_receipt','invoice','credit_note','debit_note','storno_receipt','protocol_117',
    'proforma','goods_note','storage_note',
    'cash_order_in','cash_order_out','transfer_protocol',
    'warranty','shipping_note','offer','order_confirmation'
  ) NOT NULL,
  full_number VARCHAR(20) NOT NULL,
  numeric_number BIGINT UNSIGNED NOT NULL,
  -- Партньор (snapshot — партньорът може да се преименува след години)
  partner_id INT UNSIGNED NULL,
  partner_legal_name VARCHAR(255),
  partner_eik VARCHAR(20),
  partner_vat_number VARCHAR(20),
  partner_address TEXT,
  -- Йерархия (parent → child: invoice → credit_note, cash_receipt → storno)
  parent_doc_id INT UNSIGNED NULL,
  -- Свързано бизнес-събитие
  sale_id INT UNSIGNED NULL,
  delivery_id INT UNSIGNED NULL,
  return_id INT UNSIGNED NULL,
  transfer_id INT UNSIGNED NULL,
  order_id INT UNSIGNED NULL,
  -- Финансови суми
  subtotal DECIMAL(12,2) DEFAULT 0,
  vat_rate DECIMAL(5,2) DEFAULT 0,
  vat_amount DECIMAL(12,2) DEFAULT 0,
  total_amount DECIMAL(12,2) DEFAULT 0,
  currency CHAR(3) DEFAULT 'EUR',
  -- Status + плащане
  status ENUM('draft','issued','sent','paid','cancelled','annulled') DEFAULT 'draft',
  payment_status ENUM('unpaid','partial','paid','overdue','refunded') DEFAULT 'unpaid',
  payment_terms VARCHAR(50),
  due_date DATE NULL,
  -- ДДС-специфика
  vat_basis_text TEXT,
  is_reverse_charge TINYINT(1) DEFAULT 0,
  -- Метаданни
  issued_at DATETIME NOT NULL,
  issued_by INT UNSIGNED,
  cancelled_at DATETIME NULL,
  cancelled_by INT UNSIGNED NULL,
  cancel_reason TEXT,
  notes TEXT,
  -- Auto-detect (Layer 0/2 от UX)
  auto_generated TINYINT(1) DEFAULT 0,
  trigger_source VARCHAR(50),
  -- PDF / печат
  pdf_path VARCHAR(255) NULL,
  printed_count INT DEFAULT 0,
  emailed_to TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  -- Indexes + constraints
  UNIQUE KEY uk_tenant_full (tenant_id, doc_type, full_number),
  INDEX idx_tenant_partner (tenant_id, partner_id),
  INDEX idx_tenant_type_date (tenant_id, doc_type, issued_at),
  INDEX idx_tenant_sale (tenant_id, sale_id),
  INDEX idx_tenant_delivery (tenant_id, delivery_id),
  INDEX idx_parent (parent_doc_id),
  INDEX idx_status (tenant_id, status, payment_status),
  FOREIGN KEY (series_id) REFERENCES document_series(id),
  FOREIGN KEY (parent_doc_id) REFERENCES documents(id) ON DELETE SET NULL,
  FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 7. document_items (line items) ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS document_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name VARCHAR(255) NOT NULL,
  product_code VARCHAR(50),
  unit_of_measure VARCHAR(20) DEFAULT 'бр',
  quantity DECIMAL(12,3) NOT NULL,
  unit_price DECIMAL(12,4) NOT NULL,
  discount_percent DECIMAL(5,2) DEFAULT 0,
  discount_amount DECIMAL(12,2) DEFAULT 0,
  vat_rate DECIMAL(5,2) DEFAULT 20,
  vat_amount DECIMAL(12,2) DEFAULT 0,
  line_subtotal DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  position INT DEFAULT 0,
  notes TEXT,
  INDEX idx_document (document_id),
  INDEX idx_product (product_id),
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── 8. ALTER на existing таблици (idempotent) ───────────────────────────────
-- sales: добавяме partner_id + document_id за B2B mode integration
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME='partner_id');
SET @sql = IF(@col_exists=0, 'ALTER TABLE sales ADD COLUMN partner_id INT UNSIGNED NULL, ADD INDEX idx_sales_partner (tenant_id, partner_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME='primary_document_id');
SET @sql = IF(@col_exists=0, 'ALTER TABLE sales ADD COLUMN primary_document_id INT UNSIGNED NULL, ADD INDEX idx_sales_doc (primary_document_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME='sale_mode');
SET @sql = IF(@col_exists=0, "ALTER TABLE sales ADD COLUMN sale_mode ENUM('retail','wholesale') DEFAULT 'retail'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

