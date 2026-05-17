# 🗄️ WALLET PHASE 1 — DB SCHEMA BRIEF (за Claude Code)

**Версия:** 1.0  
**Дата:** 17.05.2026 (S150 prep)  
**Engineer:** Claude Code в tmux сесия  
**Цел:** Създай 8 idempotent migrations + seed скрипт за RunMyWallet  
**Изпълнение:** Локално в droplet, БЕЗ commit/push в края (друг CC работи паралелно).

---

## ⚠️ КРИТИЧНИ ИНСТРУКЦИИ — ПРОЧЕТИ ПЪРВО

```
1. ВСИЧКИ migrations да са ИДЕМПОТЕНТНИ
   - MySQL 8 НЕ поддържа `ADD COLUMN IF NOT EXISTS`
   - Използвай PREPARE/EXECUTE pattern с information_schema check
   - Виж примера в /var/www/runmystore/i18n-foundation/migrations/001_i18n_schema.sql

2. БЕЗ git commit или push в края
   - Друг Claude Code работи паралелно по различни файлове
   - Само създай файловете в /var/www/runmystore/wallet/migrations/
   - Тихол ще ги ревю и commit-не ръчно

3. Парични стойности като DECIMAL(12,2) — НЕ в cents
   - БГ конвенция — DB stores 1437.50 не 143750
   - Колоната `currency CHAR(3)` за idempotence (EUR default)

4. Audit trail задължителен на всяка таблица:
   - created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   - updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

5. Foreign keys с ON DELETE CASCADE за tenant_id (където подходящо)

6. utf8mb4_unicode_ci ВИНАГИ — за БГ + emoji safety

7. Indexes за тежки queries:
   - Period aggregations: (tenant_id, user_id, occurred_at DESC)
   - Category filters: (category_id, direction)
```

---



---

## 🏷️ NAMESPACE CONVENTION (S150 decision)

**ВСИЧКИ Wallet таблици ползват `wallet_` prefix:**

```
wallet_money_movements        (главната таблица)
wallet_categories             (с translations)
wallet_goals
wallet_goal_contributions
wallet_notifications
wallet_ai_topics
wallet_recurring_rules
wallet_ai_audit_log
```

**Защо:**
- Избягва collisions с RMS таблиците (`categories`, `notifications` вече съществуват за RMS)
- Matches `mkt_*` pattern (marketing module)
- Бъдеще-proof: други продукти ще ползват своя prefix
- Migrations + foreign keys + stored procedures трябва да отразяват prefix-а

**RMS таблици (НЕ ПИПАЙ):**
- `categories` (RMS product categories — Пешо го ползва)
- `notifications` (RMS notifications — активни)

---

## 📁 СТРУКТУРА НА ФАЙЛОВЕТЕ

```
/var/www/runmystore/wallet/
├── migrations/
│   ├── 010_wallet_users_extend.sql
│   ├── 011_money_movements.sql
│   ├── 012_categories.sql
│   ├── 013_goals.sql
│   ├── 014_notifications.sql
│   ├── 015_ai_topics.sql
│   ├── 016_recurring_rules.sql
│   ├── 017_audit_log.sql
│   └── 018_seed_test_data.sql
├── docs/
│   └── ERD.md          ← markdown с описание на връзките
└── README.md           ← как се изпълняват migrations
```

---

## 🏛️ СЪЩЕСТВУВАЩИ ТАБЛИЦИ — НЕ МОДИФИЦИРАЙ

Тези вече съществуват от Phase 0:

```sql
-- От 001_i18n_schema.sql (вече deployed):
tenants                  -- с locale, country_code, currency, timezone, tax_jurisdiction, product
country_config           -- 9 държави, BG.active=TRUE
ui_translations          -- (key_name, locale) overrides
profession_templates     -- 14 шаблона с name_translations JSON

-- От RunMyStore (вече deployed):
users                    -- staff (Пешо/Митко за RMS) — РАЗШИРИ с wallet полета в Migration 010
```

---

## 📋 MIGRATION 010 — wallet_users_extend.sql

**Цел:** Разшири `users` table с wallet-specific полета. Един user може да е RMS staff ИЛИ wallet user ИЛИ и двете.

```sql
-- Profile image
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='profile_image_url') = 0,
  'ALTER TABLE users ADD COLUMN profile_image_url VARCHAR(500) DEFAULT NULL',
  'SELECT ''profile_image_url exists'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Профил template (за wallet self-employed users)
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS 
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='profession_template_id') = 0,
  'ALTER TABLE users ADD COLUMN profession_template_id INT DEFAULT NULL',
  'SELECT ''profession_template_id exists'''
));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Lifestyle (car, kids, pet, etc)
-- ... (повтори pattern за всички колони)

КОЛОНИ ЗА ДОБАВЯНЕ:
  profile_image_url VARCHAR(500) NULL
  profession_template_id INT NULL  (FK към profession_templates)
  lifestyle_flags JSON NULL          -- {"car":true,"kids":2,"pet":true,"own_home":true,"self_employed":true,"private_insurance":false}
  income_tier ENUM('t1','t2','t3','t4','t5') NULL  -- t1=<500€, t2=500-1500, t3=1500-3000, t4=3000-6000, t5=>6000
  onboarding_completed_at TIMESTAMP NULL
  
  -- Apps access (Тихол решение: един user може RMS + Wallet)
  apps_enabled JSON DEFAULT '["rms"]'  -- ["rms"] / ["wallet"] / ["rms","wallet"]
  
  -- Plan per app (RMS PRO се продава отделно от Wallet START)
  wallet_plan VARCHAR(20) DEFAULT 'free'    -- 'free' | 'start' | 'pro' (за Wallet)
  rms_plan VARCHAR(20) DEFAULT NULL         -- 'free' | 'pro_49' | 'enterprise' (за RMS)
  wallet_trial_ends_at TIMESTAMP NULL
  rms_trial_ends_at TIMESTAMP NULL
  
  -- ДДС / VAT (Тихол решение: pитаме в onboarding)
  vat_registered BOOLEAN DEFAULT FALSE
  vat_number VARCHAR(20) NULL
  vat_registered_at DATE NULL              -- кога се регистрира (за back-calc)
  vat_threshold_alert_pct INT DEFAULT 70   -- при какъв % да алармираме

ИНДЕКСИ:
  INDEX idx_wallet_plan (wallet_plan)
  INDEX idx_rms_plan (rms_plan)
  INDEX idx_profession (profession_template_id)
  INDEX idx_vat (vat_registered)

ЗАБЕЛЕЖКА:
  Пешо (RMS staff) ще е apps_enabled=["rms"] и rms_plan='free' (тенант плаща за него)
  Митко (RMS owner) ще е apps_enabled=["rms","wallet"] и rms_plan='pro_49'
  ENI (Wallet only) ще е apps_enabled=["wallet"] и wallet_plan='start'
```

---

## 📋 MIGRATION 011 — money_movements.sql

**Цел:** Главната таблица. Всеки запис на приход/разход.

```sql
CREATE TABLE IF NOT EXISTS money_movements (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  
  -- Movement core
  direction ENUM('in','out') NOT NULL,
  amount DECIMAL(12,2) NOT NULL CHECK (amount >= 0),
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  
  -- Category (FK към categories)
  category_id INT DEFAULT NULL,
  sub_category_id INT DEFAULT NULL,
  
  -- Context
  vendor VARCHAR(200) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  method ENUM('voice','photo','manual','bank','recurring','imported') NOT NULL,
  
  -- Business / Personal split (Bible §44 critical)
  is_business BOOLEAN DEFAULT FALSE,
  is_tax_deductible BOOLEAN DEFAULT FALSE,
  
  -- AI audit trail (Bible §44 LAW №7)
  ai_confidence DECIMAL(4,3) DEFAULT NULL,    -- 0.000 to 1.000
  raw_transcript TEXT DEFAULT NULL,            -- оригинален voice text
  retrieved_facts JSON DEFAULT NULL,           -- {category_match:"fuel", vendor_match:"Лукойл", confidence_breakdown:{}}
  ai_model_used VARCHAR(50) DEFAULT NULL,      -- 'gemini-2.5-flash' | 'whisper-1' | NULL за manual
  
  -- Photo evidence
  photo_url VARCHAR(500) DEFAULT NULL,         -- S3/local path към снимка
  photo_thumbnail_url VARCHAR(500) DEFAULT NULL,
  receipt_items_json JSON DEFAULT NULL,        -- [{name:"Бензин",qty:38.1,unit:"л",amount:34.20}, ...]
  
  -- Recurring
  recurring_rule_id INT DEFAULT NULL,          -- FK към recurring_rules
  
  -- Demo marker (Тихол решение: persona-based demo per onboarding)
  is_demo BOOLEAN DEFAULT FALSE,               -- TRUE = seed data, auto-clears after 5 real records
  
  -- Status
  status ENUM('confirmed','pending_review','cancelled') DEFAULT 'confirmed',
  
  -- Time
  occurred_at TIMESTAMP NOT NULL,              -- WHEN it happened (user-set or auto)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  
  INDEX idx_tenant_user_date (tenant_id, user_id, occurred_at DESC),
  INDEX idx_category (category_id),
  INDEX idx_direction (direction),
  INDEX idx_business (is_business),
  INDEX idx_method (method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 📋 MIGRATION 012 — categories.sql

**Цел:** Категории + sub-categories с translations.

```sql
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT DEFAULT NULL,                  -- NULL = top-level
  code VARCHAR(50) NOT NULL UNIQUE,            -- 'fuel', 'restaurants', 'salary' и т.н.
  name_en VARCHAR(100) NOT NULL,
  name_translations JSON DEFAULT NULL,         -- {"bg":"Гориво","ro":"Combustibil"}
  
  -- Type
  direction ENUM('in','out','both') NOT NULL,  -- категория за приход/разход/двете
  
  -- Tax classification (per country)
  tax_class_json JSON DEFAULT NULL,            -- {"BG":{"npr_pct":25,"deductible":true},"RO":{...}}
  
  -- Visual
  icon_name VARCHAR(50) NOT NULL DEFAULT 'circle', -- 'fuel', 'restaurant', 'home', etc (matches mockup icon picker)
  color_hue INT DEFAULT NULL,                  -- HSL hue 0-360 за UI orb (loss=0, gain=145, magic=280, amber=38)
  
  -- Order in UI
  display_order INT DEFAULT 100,
  
  -- Status
  is_active BOOLEAN DEFAULT TRUE,
  is_system BOOLEAN DEFAULT TRUE,              -- TRUE = built-in, FALSE = user-created
  tenant_id INT DEFAULT NULL,                  -- NULL = global, иначе user-specific
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  
  INDEX idx_parent (parent_id),
  INDEX idx_code (code),
  INDEX idx_direction (direction),
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEED 20 system categories (top-level):
INSERT IGNORE INTO categories (code, name_en, name_translations, direction, icon_name, color_hue, display_order) VALUES
-- ИЗХОДЯЩИ
('materials','Materials','{"bg":"Материали"}','out','box',0,10),
('fuel','Fuel','{"bg":"Гориво"}','out','fuel',280,20),
('restaurants','Restaurants','{"bg":"Ресторанти"}','out','restaurant',38,30),
('health','Health','{"bg":"Здраве"}','out','heart',145,40),
('services','Services','{"bg":"Услуги"}','out','briefcase',0,50),
('transport','Transport','{"bg":"Транспорт"}','out','car',280,60),
('subscriptions','Subscriptions','{"bg":"Абонаменти"}','out','repeat',180,70),
('insurance','Insurance','{"bg":"Застраховки"}','out','shield',0,80),
('education','Education','{"bg":"Образование"}','out','book',145,90),
('entertainment','Entertainment','{"bg":"Развлечения"}','out','smile',38,100),
('utilities','Utilities','{"bg":"Сметки"}','out','zap',280,110),
('rent','Rent','{"bg":"Наем"}','out','home',0,120),
('groceries','Groceries','{"bg":"Хранителни стоки"}','out','shopping-cart',38,130),
('tax_social','Tax & Social Security','{"bg":"Данъци и осигуровки"}','out','calendar',0,140),
('other_expense','Other','{"bg":"Други разходи"}','out','more-horizontal',222,150),

-- ВХОДЯЩИ
('salary','Salary','{"bg":"Заплата"}','in','dollar-sign',145,200),
('freelance','Freelance income','{"bg":"Хонорар"}','in','briefcase',145,210),
('rental_income','Rental income','{"bg":"Наем (приход)"}','in','home',145,220),
('investments','Investments','{"bg":"Инвестиции"}','in','trending-up',145,230),
('other_income','Other income','{"bg":"Други приходи"}','in','plus-circle',145,240);
```

---

## 📋 MIGRATION 013 — goals.sql

**Цел:** Цели (savings/limits/recurring) + история на contributions.

```sql
CREATE TABLE IF NOT EXISTS goals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  
  -- Type (виж P29 mockup)
  type ENUM('savings','limit','recurring') NOT NULL,
  
  -- Identity
  name VARCHAR(150) NOT NULL,
  icon_name VARCHAR(50) DEFAULT 'star',          -- 'car','home','plane' etc от P29 picker
  color_hue INT DEFAULT NULL,                    -- override default за цел
  
  -- Target
  target_amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) DEFAULT 'EUR',
  
  -- For savings: current accumulated
  current_amount DECIMAL(12,2) DEFAULT 0.00,
  
  -- For limits: linked category + period
  category_id INT DEFAULT NULL,                  -- ako е лимит за категория
  limit_period ENUM('daily','weekly','monthly','yearly') DEFAULT NULL,
  
  -- For recurring: rule reference
  recurring_rule_id INT DEFAULT NULL,
  
  -- Deadlines
  starts_at DATE DEFAULT NULL,
  ends_at DATE DEFAULT NULL,                     -- deadline
  
  -- Status
  status ENUM('active','paused','achieved','cancelled') DEFAULT 'active',
  achieved_at TIMESTAMP NULL,
  is_primary BOOLEAN DEFAULT FALSE,              -- main goal shown в hero (само 1 per user)
  
  -- Demo marker (auto-clears after 5 real movements)
  is_demo BOOLEAN DEFAULT FALSE,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  
  INDEX idx_tenant_user_status (tenant_id, user_id, status),
  INDEX idx_type (type),
  INDEX idx_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS goal_contributions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  goal_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,                 -- positive = добави, negative = взе
  note VARCHAR(200) DEFAULT NULL,
  
  -- Linked movement (ако contribution идва от movement)
  movement_id BIGINT DEFAULT NULL,
  
  contributed_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
  FOREIGN KEY (movement_id) REFERENCES money_movements(id) ON DELETE SET NULL,
  
  INDEX idx_goal_date (goal_id, contributed_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 📋 MIGRATION 014 — notifications.sql

**Цел:** Notifications + delivery log.

```sql
CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  
  -- Type (виж P30 mockup — 6 variants)
  type ENUM('tax','ai','alert','reminder','success','system') NOT NULL,
  
  -- Content
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  
  -- Optional rich content
  icon_name VARCHAR(50) DEFAULT NULL,
  action_url VARCHAR(500) DEFAULT NULL,          -- deep link в app
  action_label_key VARCHAR(150) DEFAULT NULL,    -- i18n key за CTA text
  
  -- Linked entities (за context)
  related_movement_id BIGINT DEFAULT NULL,
  related_goal_id INT DEFAULT NULL,
  
  -- Status
  is_read BOOLEAN DEFAULT FALSE,
  read_at TIMESTAMP NULL,
  is_archived BOOLEAN DEFAULT FALSE,
  
  -- Delivery
  delivered_push BOOLEAN DEFAULT FALSE,
  delivered_email BOOLEAN DEFAULT FALSE,
  delivered_sms BOOLEAN DEFAULT FALSE,
  delivered_at TIMESTAMP NULL,
  
  -- Time
  scheduled_for TIMESTAMP NULL,                  -- за future reminders
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (related_movement_id) REFERENCES money_movements(id) ON DELETE SET NULL,
  FOREIGN KEY (related_goal_id) REFERENCES goals(id) ON DELETE SET NULL,
  
  INDEX idx_user_unread (user_id, is_read, created_at DESC),
  INDEX idx_type (type),
  INDEX idx_scheduled (scheduled_for)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 📋 MIGRATION 015 — ai_topics.sql

**Цел:** Catalog на AI prompts с locale variants per topic.

```sql
CREATE TABLE IF NOT EXISTS ai_topics (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,              -- 'home_summary','tax_estimate','category_breakdown',...
  description TEXT DEFAULT NULL,
  
  -- Per locale prompt template
  prompt_translations JSON NOT NULL,             -- {"bg":"...","en":"..."}
  
  -- Model preference
  preferred_model VARCHAR(50) DEFAULT 'gemini-2.5-flash',
  max_response_chars INT DEFAULT 200,
  
  -- Confidence routing (Bible §44 ЗАКОН №8)
  min_confidence_auto DECIMAL(4,3) DEFAULT 0.85,
  min_confidence_confirm DECIMAL(4,3) DEFAULT 0.50,
  
  -- Frequency (как често може да се generate-не)
  cooldown_hours INT DEFAULT 24,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEED 10 initial topics (Тихол ще ги развие):
INSERT IGNORE INTO ai_topics (code, description, prompt_translations, max_response_chars) VALUES
('home_insight','Insight на главния home екран — какво се промени',
 '{"bg":"Опиши какво се промени в месечния резултат: {data}. Отговори на български, максимум 70 знака.","en":"Describe monthly change: {data}. Reply in English, max 70 chars."}',
 100),
('category_savings_tip','Препоръка как да спестим в категория',
 '{"bg":"Анализирай разходите в {category}: {data}. Дай 1 препоръка как да спести 5-20%.","en":"Analyze {category} spending: {data}. Suggest 5-20% saving."}',
 150),
-- ... (CC да добави още 8 базови topics)
('vat_threshold_warning','ДДС праг предупреждение','{"bg":"...","en":"..."}',120),
('seasonal_pattern','Сезонност открита','{"bg":"...","en":"..."}',150),
('goal_progress','Прогрес на цел','{"bg":"...","en":"..."}',100);
```

---

## 📋 MIGRATION 016 — recurring_rules.sql

**Цел:** За auto goal contributions, subscriptions, salary, рента.

```sql
CREATE TABLE IF NOT EXISTS recurring_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  
  -- What
  type ENUM('income','expense','goal_contribution') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) DEFAULT 'EUR',
  
  -- Linked entities
  category_id INT DEFAULT NULL,
  goal_id INT DEFAULT NULL,
  
  -- Description
  name VARCHAR(150) NOT NULL,                    -- 'Заплата', 'Netflix', 'Спортна зала'
  vendor VARCHAR(200) DEFAULT NULL,
  
  -- Recurrence pattern
  frequency ENUM('daily','weekly','monthly','quarterly','yearly') NOT NULL,
  day_of_month INT DEFAULT NULL,                 -- 1-31, NULL за weekly/daily
  day_of_week INT DEFAULT NULL,                  -- 1-7, NULL за monthly+
  next_due_date DATE NOT NULL,
  last_executed_date DATE NULL,
  
  -- Status
  is_active BOOLEAN DEFAULT TRUE,
  ends_on DATE NULL,                             -- optional end date
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
  
  INDEX idx_next_due (next_due_date, is_active),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 📋 MIGRATION 017 — audit_log.sql

**Цел:** Tracking на всички AI calls + cost.

```sql
CREATE TABLE IF NOT EXISTS ai_audit_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  
  -- Request
  topic_code VARCHAR(50) DEFAULT NULL,           -- FK към ai_topics.code (логически)
  model VARCHAR(50) NOT NULL,                    -- 'gemini-2.5-flash', 'whisper-1', etc.
  endpoint VARCHAR(100) DEFAULT NULL,            -- '/api/voice/parse'
  
  -- Tokens / size
  input_tokens INT DEFAULT NULL,
  output_tokens INT DEFAULT NULL,
  audio_seconds DECIMAL(8,2) DEFAULT NULL,       -- за Whisper
  image_count INT DEFAULT NULL,                  -- за vision
  
  -- Cost
  cost_usd DECIMAL(8,4) DEFAULT NULL,
  
  -- Result
  success BOOLEAN DEFAULT TRUE,
  error_message TEXT DEFAULT NULL,
  duration_ms INT DEFAULT NULL,
  
  -- Linked entity
  related_movement_id BIGINT DEFAULT NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (related_movement_id) REFERENCES money_movements(id) ON DELETE SET NULL,
  
  INDEX idx_tenant_date (tenant_id, created_at DESC),
  INDEX idx_model (model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 📋 MIGRATION 018 — seed_test_data.sql + persona-based demo logic

**Цел:** 
1. Test tenant_id=8 + user_id=100 за разработка (Стефан, IT freelancer)
2. **PERSONA-BASED DEMO** — функция която при signup на нов user генерира demo data според професията

### 18.1 Test data (както досега, за CC dev):

```sql
-- TEST TENANT за RunMyWallet
INSERT IGNORE INTO tenants (id, name, locale, country_code, currency, timezone, tax_jurisdiction, product)
VALUES (8, 'RunMyWallet Test', 'bg-BG', 'BG', 'EUR', 'Europe/Sofia', 'BG', 'wallet');

-- TEST USER (Стефан, programmer)
INSERT IGNORE INTO users (id, tenant_id, name, email, password_hash, role,
  profession_template_id, lifestyle_flags, income_tier, onboarding_completed_at,
  apps_enabled, wallet_plan, vat_registered)
SELECT 
  100, 8, 'Стефан Тонев', 'stefan@test.runmywallet.ai',
  '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ12',  -- password: "test123"
  'wallet_user',
  pt.id, '{"car":true,"kids":0,"pet":false,"own_home":true,"self_employed":true}', 't3',
  CURRENT_TIMESTAMP,
  '["wallet"]', 'start', FALSE
FROM profession_templates pt WHERE pt.code = 'it_digital' LIMIT 1;

-- Demo data за тестовия user (with is_demo=TRUE flag)
-- ... (виж 18.2 за схемата)
```

### 18.2 Persona-based demo seeding function

**ЦЕЛ:** Когато нов user приключи онбординга, AUTOMATIC generation на demo data базирано на `profession_template_id` + `lifestyle_flags`.

```sql
-- DELIMITER за stored procedure
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS sp_seed_demo_data(IN p_user_id INT)
BEGIN
  DECLARE v_tenant_id INT;
  DECLARE v_profession_code VARCHAR(50);
  DECLARE v_lifestyle JSON;
  
  -- Load user context
  SELECT tenant_id INTO v_tenant_id FROM users WHERE id = p_user_id;
  SELECT pt.code, u.lifestyle_flags INTO v_profession_code, v_lifestyle
  FROM users u JOIN profession_templates pt ON u.profession_template_id = pt.id
  WHERE u.id = p_user_id;
  
  -- Generate 5-7 demo movements based on profession (последните 14 дни)
  CASE v_profession_code
    WHEN 'it_digital' THEN
      INSERT INTO money_movements (tenant_id, user_id, direction, amount, currency, 
        category_id, vendor, description, method, is_business, is_demo, occurred_at)
      VALUES
        (v_tenant_id, p_user_id, 'in', 1500.00, 'EUR', 
          (SELECT id FROM categories WHERE code='freelance'), 'Клиент A', 
          'Хонорар за уебсайт', 'voice', TRUE, TRUE, NOW() - INTERVAL 12 DAY),
        (v_tenant_id, p_user_id, 'out', 10.00, 'EUR', 
          (SELECT id FROM categories WHERE code='subscriptions'), 'Slack', 
          'Месечен абонамент', 'recurring', TRUE, TRUE, NOW() - INTERVAL 8 DAY),
        (v_tenant_id, p_user_id, 'out', 85.00, 'EUR', 
          (SELECT id FROM categories WHERE code='materials'), 'AliExpress', 
          'USB хъб и кабели', 'photo', TRUE, TRUE, NOW() - INTERVAL 5 DAY),
        (v_tenant_id, p_user_id, 'out', 4.50, 'EUR', 
          (SELECT id FROM categories WHERE code='restaurants'), 'Co-working', 
          'Кафе', 'voice', FALSE, TRUE, NOW() - INTERVAL 3 DAY),
        (v_tenant_id, p_user_id, 'out', 50.00, 'EUR', 
          (SELECT id FROM categories WHERE code='fuel'), 'Лукойл', 
          'Бензин', 'photo', TRUE, TRUE, NOW() - INTERVAL 1 DAY);
    
    WHEN 'beauty_care' THEN
      INSERT INTO money_movements (tenant_id, user_id, direction, amount, currency,
        category_id, vendor, description, method, is_business, is_demo, occurred_at)
      VALUES
        (v_tenant_id, p_user_id, 'in', 35.00, 'EUR',
          (SELECT id FROM categories WHERE code='freelance'), 'Клиент', 
          'Маникюр', 'voice', TRUE, TRUE, NOW() - INTERVAL 10 DAY),
        (v_tenant_id, p_user_id, 'in', 65.00, 'EUR',
          (SELECT id FROM categories WHERE code='freelance'), 'Клиент', 
          'Боя и подстригване', 'voice', TRUE, TRUE, NOW() - INTERVAL 7 DAY),
        (v_tenant_id, p_user_id, 'out', 12.00, 'EUR',
          (SELECT id FROM categories WHERE code='materials'), 'L Oreal', 
          'Лак за нокти', 'photo', TRUE, TRUE, NOW() - INTERVAL 5 DAY),
        (v_tenant_id, p_user_id, 'out', 95.00, 'EUR',
          (SELECT id FROM categories WHERE code='utilities'), 'EVN', 
          'Електричество за салон', 'manual', TRUE, TRUE, NOW() - INTERVAL 3 DAY);
    
    WHEN 'craftsman_tech' THEN
      INSERT INTO money_movements (tenant_id, user_id, direction, amount, currency,
        category_id, vendor, description, method, is_business, is_demo, occurred_at)
      VALUES
        (v_tenant_id, p_user_id, 'in', 180.00, 'EUR',
          (SELECT id FROM categories WHERE code='freelance'), 'Клиент', 
          'Ремонт мивка', 'voice', TRUE, TRUE, NOW() - INTERVAL 8 DAY),
        (v_tenant_id, p_user_id, 'out', 42.00, 'EUR',
          (SELECT id FROM categories WHERE code='materials'), 'Praktiker', 
          'Болтове и тръби', 'photo', TRUE, TRUE, NOW() - INTERVAL 6 DAY),
        (v_tenant_id, p_user_id, 'out', 60.00, 'EUR',
          (SELECT id FROM categories WHERE code='fuel'), 'OMV', 
          'Гориво за камион', 'photo', TRUE, TRUE, NOW() - INTERVAL 2 DAY);
    
    ELSE  -- DEFAULT: personal/other (минимални)
      INSERT INTO money_movements (tenant_id, user_id, direction, amount, currency,
        category_id, vendor, description, method, is_business, is_demo, occurred_at)
      VALUES
        (v_tenant_id, p_user_id, 'in', 1200.00, 'EUR',
          (SELECT id FROM categories WHERE code='salary'), 'Работодател', 
          'Заплата', 'manual', FALSE, TRUE, NOW() - INTERVAL 5 DAY),
        (v_tenant_id, p_user_id, 'out', 45.00, 'EUR',
          (SELECT id FROM categories WHERE code='groceries'), 'Lidl', 
          'Хранителни', 'photo', FALSE, TRUE, NOW() - INTERVAL 3 DAY);
  END CASE;
  
  -- Generate 3 demo goals (същия pattern)
  CASE v_profession_code
    WHEN 'it_digital' THEN
      INSERT INTO goals (tenant_id, user_id, type, name, icon_name, target_amount, current_amount, ends_at, is_primary, is_demo, status)
      VALUES
        (v_tenant_id, p_user_id, 'savings', 'Резерв 3 месеца', 'shield', 7500.00, 0.00, DATE_ADD(CURDATE(), INTERVAL 12 MONTH), TRUE, TRUE, 'active'),
        (v_tenant_id, p_user_id, 'savings', 'Нов лаптоп', 'monitor', 2400.00, 0.00, DATE_ADD(CURDATE(), INTERVAL 6 MONTH), FALSE, TRUE, 'active'),
        (v_tenant_id, p_user_id, 'savings', 'Отпуска лято', 'plane', 1500.00, 0.00, DATE_ADD(CURDATE(), INTERVAL 4 MONTH), FALSE, TRUE, 'active');
    
    WHEN 'beauty_care' THEN
      INSERT INTO goals (tenant_id, user_id, type, name, icon_name, target_amount, current_amount, ends_at, is_primary, is_demo, status)
      VALUES
        (v_tenant_id, p_user_id, 'savings', 'Резерв', 'shield', 3000.00, 0.00, DATE_ADD(CURDATE(), INTERVAL 12 MONTH), TRUE, TRUE, 'active'),
        (v_tenant_id, p_user_id, 'savings', 'Ремонт салон', 'home', 5000.00, 0.00, DATE_ADD(CURDATE(), INTERVAL 8 MONTH), FALSE, TRUE, 'active'),
        (v_tenant_id, p_user_id, 'savings', 'Курс надграждане', 'book', 800.00, 0.00, DATE_ADD(CURDATE(), INTERVAL 6 MONTH), FALSE, TRUE, 'active');
    
    WHEN 'craftsman_tech' THEN
      INSERT INTO goals (tenant_id, user_id, type, name, icon_name, target_amount, current_amount, ends_at, is_primary, is_demo, status)
      VALUES
        (v_tenant_id, p_user_id, 'savings', 'Резерв', 'shield', 5000.00, 0.00, DATE_ADD(CURDATE(), INTERVAL 12 MONTH), TRUE, TRUE, 'active'),
        (v_tenant_id, p_user_id, 'savings', 'Нов комплект инструменти', 'wrench', 2000.00, 0.00, DATE_ADD(CURDATE(), INTERVAL 6 MONTH), FALSE, TRUE, 'active'),
        (v_tenant_id, p_user_id, 'savings', 'Камион', 'truck', 15000.00, 0.00, DATE_ADD(CURDATE(), INTERVAL 24 MONTH), FALSE, TRUE, 'active');
    
    ELSE
      INSERT INTO goals (tenant_id, user_id, type, name, icon_name, target_amount, current_amount, ends_at, is_primary, is_demo, status)
      VALUES
        (v_tenant_id, p_user_id, 'savings', 'Резерв', 'shield', 3000.00, 0.00, DATE_ADD(CURDATE(), INTERVAL 12 MONTH), TRUE, TRUE, 'active');
  END CASE;
END //

DELIMITER ;
```

### 18.3 Auto-clear trigger 

Когато потребителят натрупа 5 РЕАЛНИ записа (is_demo=FALSE), демо данните се изтриват автоматично:

```sql
DELIMITER //

CREATE TRIGGER IF NOT EXISTS trg_clear_demo_after_5_real
AFTER INSERT ON money_movements
FOR EACH ROW
BEGIN
  IF NEW.is_demo = FALSE THEN
    -- Брой реални записи на user-а
    DECLARE real_count INT;
    SELECT COUNT(*) INTO real_count 
      FROM money_movements 
      WHERE user_id = NEW.user_id AND is_demo = FALSE;
    
    -- При 5+ реални → изтрий всички demo
    IF real_count >= 5 THEN
      DELETE FROM money_movements WHERE user_id = NEW.user_id AND is_demo = TRUE;
      DELETE FROM goals WHERE user_id = NEW.user_id AND is_demo = TRUE;
    END IF;
  END IF;
END //

DELIMITER ;
```

### 18.4 ДДС onboarding integration

В onboarding flow, след избор на профил/lifestyle:

```sql
-- Когато user отговори "Да" на "По ДДС регистриран ли си?":
UPDATE users 
SET vat_registered = TRUE, 
    vat_number = '<from_input>',
    vat_registered_at = CURDATE()
WHERE id = ?;

-- Когато отговори "Не":
-- НИЩО НЕ ПРАВИМ (default vat_registered = FALSE)
-- Cron автоматично следи прага през BGTaxEngine
```

### 18.5 Auto-upgrade ДДС режим (Bible §44 ЗАКОН №8)

Cron job изпълнява веднъж дневно:

```sql
-- Pseudo-SQL за clarity, истинският код е в PHP cron script
SELECT u.id, SUM(mm.amount) as revenue_ytd
FROM users u
JOIN money_movements mm ON mm.user_id = u.id AND mm.direction = 'in' AND mm.is_business = TRUE
WHERE u.vat_registered = FALSE
  AND YEAR(mm.occurred_at) = YEAR(CURDATE())
GROUP BY u.id
HAVING revenue_ytd >= 51130 * (u.vat_threshold_alert_pct / 100);
-- → за всеки user изпрати notification.type='alert' с message_key='tax.vat_threshold_crossed'
```

---

## 📋 docs/ERD.md

Markdown файл с описание на връзките:

```markdown
# RunMyWallet ERD

## Core entities

tenants (1) ─── (N) users
users (1) ─── (N) money_movements
users (1) ─── (N) goals
users (1) ─── (N) notifications
users (1) ─── (N) recurring_rules

## Categories

categories (self-referential parent_id)
categories (1) ─── (N) money_movements
categories (1) ─── (N) goals (limits)

## Goals

goals (1) ─── (N) goal_contributions
goal_contributions (N) ─── (1) money_movements (optional)

## AI

money_movements has audit fields:
  - raw_transcript
  - retrieved_facts
  - ai_confidence
  - ai_model_used

ai_audit_log tracks every AI call separately.

## Recurring

recurring_rules (1) ─── (N) money_movements (auto-created)
recurring_rules (1) ─── (N) goals (auto-contributions)
```

---

## 📋 README.md

```markdown
# RunMyWallet — Phase 1 DB Schema

## Изпълнение

cd /var/www/runmystore/wallet
for m in migrations/*.sql; do
  echo "Running $m..."
  mysql -u root runmystore < "$m"
done

## Verification

mysql -u root runmystore -e "
SELECT TABLE_NAME, TABLE_ROWS 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA='runmystore' 
  AND TABLE_NAME IN ('money_movements','goals','goal_contributions','notifications','categories','ai_topics','recurring_rules','ai_audit_log')
ORDER BY TABLE_NAME;
"

## Rollback

Migrations НЕ rollback автоматично. За clean reset:

DROP TABLE IF EXISTS ai_audit_log;
DROP TABLE IF EXISTS recurring_rules;
DROP TABLE IF EXISTS ai_topics;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS goal_contributions;
DROP TABLE IF EXISTS goals;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS money_movements;

ALTER TABLE users 
  DROP COLUMN profile_image_url,
  DROP COLUMN profession_template_id,
  -- ... (other added columns)
;
```

---

## ✅ CC CHECKLIST (изпълни в този ред)

```
[ ] 1. Прочети ЦЕЛИЯ brief преди да започнеш
[ ] 2. Виж /var/www/runmystore/i18n-foundation/migrations/001_i18n_schema.sql за idempotent pattern
[ ] 3. Създай /var/www/runmystore/wallet/ структурата
[ ] 4. Migration 010 (extend users)
[ ] 5. Migration 011 (money_movements) — главната
[ ] 6. Migration 012 (categories) + 20 seed records
[ ] 7. Migration 013 (goals + goal_contributions)
[ ] 8. Migration 014 (notifications)
[ ] 9. Migration 015 (ai_topics) + 10 seed topics
[ ] 10. Migration 016 (recurring_rules)
[ ] 11. Migration 017 (ai_audit_log)
[ ] 12. Migration 018 (seed test data — tenant_id=8, user_id=100, 30 movements, 3 goals, 5 notifications)
[ ] 13. docs/ERD.md
[ ] 14. README.md с execute/rollback instructions
[ ] 15. ТЕСТВАЙ всеки migration 2 пъти (трябва да minat идемпотентно)
[ ] 16. ВЕРИФИЦИРАЙ с SELECT TABLE_ROWS заявка от README
[ ] 17. НЕ commit, НЕ push — само файлове локално
[ ] 18. Кажи на Тихол "ГОТОВО — 8 migrations + ERD + README, чакам ревю"
```

---

## ✅ TIHOL DECISIONS (всички решения взети — НЕ ПИТАЙ ОТНОВО)

```
A1. tenant_id=8 за Wallet test ✓
A2. EXTEND users (apps_enabled JSON: ["rms"]/["wallet"]/["rms","wallet"]) ✓
A3. ЕДИННА categories с direction ENUM ('in'/'out'/'both') ✓
A4. БЕЗ multi-currency conversion за Phase 1 (само EUR) ✓
A5. AI confidence defaults в КОД (0.85/0.50), override per topic в ai_topics ✓

B1. DigitalOcean Spaces $5/мес = 250GB ≈ 2 700 users/година capacity ✓
B2. STRICT JSON schema за retrieved_facts (validated в PHP) ✓
B3. cost_usd ВКЛЮЧЕН в audit_log (business analytics) ✓
B4. recurring_rules ОТДЕЛНА таблица (clean separation от goals) ✓
B5. Daily mysqldump → DigitalOcean Spaces ($0 допълнително) ✓

V1. ON DELETE: SET NULL за categories, CASCADE за tenant/user ✓
V2. Test password "test123" с pre-generated bcrypt ✓
V3. seed_test_data САМО за dev (НЕ production) ✓
    Persona-based demo data само при signup на нов user ✓
V4. ENUM кодове = вътрешни, UI винаги превежда през t() ✓
V5. run_migrations.sh скрипт + README ✓

CORE BUSINESS:
- Пешо (RMS staff): apps_enabled=["rms"], wallet_plan="free" ✓
- Митко (owner): apps_enabled=["rms","wallet"], rms_plan="pro_49" ✓  
- ENI (wallet only): apps_enabled=["wallet"], wallet_plan="start" ✓

DEMO STRATEGY (Bible §8 RMS Решение 8.6 extended):
- Persona-based demo при signup (5-7 movements + 3 goals) ✓
- is_demo flag в DB ✓
- Auto-clear след 5 РЕАЛНИ записа (trigger) ✓
- ЕДИНИ FB ads ще използват тези същите demo screenshots ✓

VAT STRATEGY:
- Pитаме в onboarding "По ДДС регистриран ли си?" ✓
- Ако НЕ → cron следи 70% прага и алармира ✓
- Ако ДА → автоматичен 20% VAT track + декларация reminders ✓
- При преминаване на прага автоматично → critical notification + Settings → активирай ДДС режим ✓
```

---

## ✅ CC CHECKLIST (изпълни в този ред)

```
[ ] 1. Прочети ЦЕЛИЯ brief преди да започнеш
[ ] 2. Виж /var/www/runmystore/i18n-foundation/migrations/001_i18n_schema.sql за idempotent pattern
[ ] 3. Създай /var/www/runmystore/wallet/ структурата
[ ] 4. Migration 010 (extend users) с apps_enabled JSON + VAT fields
[ ] 5. Migration 011 (money_movements) — главната + is_demo flag
[ ] 6. Migration 012 (categories) + 20 seed records
[ ] 7. Migration 013 (goals + goal_contributions) + is_demo flag
[ ] 8. Migration 014 (notifications)
[ ] 9. Migration 015 (ai_topics) + 10 seed topics
[ ] 10. Migration 016 (recurring_rules)
[ ] 11. Migration 017 (ai_audit_log)
[ ] 12. Migration 018 (seed_test_data + sp_seed_demo_data stored procedure + trigger)
[ ] 13. docs/ERD.md
[ ] 14. README.md + run_migrations.sh скрипт
[ ] 15. ТЕСТВАЙ всеки migration 2 пъти (трябва да минат идемпотентно)
[ ] 16. ВЕРИФИЦИРАЙ с SELECT TABLE_ROWS заявка от README
[ ] 17. ТЕСТВАЙ sp_seed_demo_data(100) → виж 5 movements + 3 goals създадени
[ ] 18. ТЕСТВАЙ trigger: добави 5 INSERT с is_demo=FALSE → проверявай че demo data е изтрита
[ ] 19. НЕ commit, НЕ push — само файлове локално
[ ] 20. Кажи на Тихол "ГОТОВО — 8 migrations + ERD + README + tests passed, чакам ревю"
```

---

## ⚠️ ИЗОЛАЦИЯ ОТ ДРУГ CC

Друг Claude Code работи **паралелно** върху:

```
/var/www/runmystore/wallet/landing/        ← CC-B (НЕ ПИПАЙ)
/var/www/runmystore/marketing/             ← CC-B (НЕ ПИПАЙ)
```

Ти работиш изключително върху:

```
/var/www/runmystore/wallet/migrations/     ← ТВОЯ ЗОНА
/var/www/runmystore/wallet/docs/           ← ТВОЯ ЗОНА
/var/www/runmystore/wallet/README.md       ← ТВОЯ ЗОНА
/var/www/runmystore/wallet/run_migrations.sh  ← ТВОЯ ЗОНА
```

DB tables — твоя зона. Никой друг не пипа DB schema в тази фаза.

---

**END OF BRIEF v2.0** (всички въпроси отговорени, готов за CC изпълнение)
