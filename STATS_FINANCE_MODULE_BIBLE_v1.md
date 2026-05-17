# 📊 STATS + FINANCE MODULE BIBLE — RunMyStore.AI

**Версия:** v1.1 (ETAP 1+2 от 4)
**Дата:** 17.05.2026 (S148)
**Статус:** Living document
**Обхват:** Модул "Справки" (Stats + Finance)

---

## 📑 СЪДЪРЖАНИЕ

**ETAP 1:** §1-§6 — Foundation + DB Schema ✅
**ETAP 2 (текущ):** §7-§9b — Метрики Tier 1/2/3 + AI Topics mapping ✅
**ETAP 3 (следва):** §10-§17 — UI спецификация
**ETAP 4 (следва):** §18-§27 — Exports, s82, Migration, Roadmap

---

# §1. EXECUTIVE SUMMARY

## 1.1 Цел на модула

Модул "Справки" (`stats.php`) е централният **аналитичен и финансов** изглед на RunMyStore.AI. Той отговаря на 6 фундаментални въпроса:

1. **Колко правя?** (оборот, транзакции, среден чек)
2. **Колко печеля?** (марж, нетна печалба, P&L)
3. **Какво се продава?** (топ артикули, категории, сезонност)
4. **Какво не се продава?** (мъртви артикули, замразен капитал)
5. **Колко пари имам?** (cash flow, касова наличност, банкови сметки)
6. **Какво ми струва?** (разходи, наем, заплати, ДДС)

Въпроси 1-4 = Stats sub-domain (Phase B beta-ready).
Въпроси 5-6 = Finance sub-domain (Phase 8, post-beta).

Двата sub-domain-а живеят в **един модул**, **един файл**, **един таб** "Справки".

## 1.2 Защо един модул

В `partials/bottom-nav.php`:
```php
$isStats = in_array($rms_current_module, ['stats','finance'], true);
```

Двата модула споделят: един entry point, един backend, една кохерентна история, един AI engine (170+ темите).

## 1.3 Phased delivery

| Фаза | Какво | Кога |
|---|---|---|
| **Phase B** (юни-юли 2026) | Преглед + Артикули + Финанси sub-tab 12.1 | Beta |
| **Phase 8** (post-beta) | Финанси sub-tabs 12.2-12.5 | После |

## 1.4 Ключова диференциация

Конкурентите (Lightspeed, Vend, Loyverse, MicroInvest) дават сурови числа. RunMyStore прави:
1. AI наратив върху числата
2. Substitution graph awareness
3. Voice query достъп
4. Bilingual currency (€ + лв) до 8.8.2026

## 1.5 Размер и сложност

| Параметър | Стойност |
|---|---|
| Текущ stats.php | 1371 реда |
| Прогноза след refactor | ~2800-3500 реда (split partials) |
| Нови DB таблици | 15 |
| AI теми | 170 |
| KPI метрики (Tier 1+2+3) | 20 |

---

# §2. ПЕРСОНИ И ROLES

## 2.1 Персони

**👴 Пешо (Simple Mode):** Не отваря stats.php. Вижда финансови данни само през s82-dash в life-board.

**👨‍💼 Митко (Detailed Mode):** Главен потребител. 3-5 пъти на ден. Иска drill-down. Mobile-first 375px (Z Flip6).

## 2.2 Roles

| Role | stats.php | Финанси таб |
|---|---|---|
| **OWNER 👑** | Пълен | Пълен |
| **MANAGER 🔑** | Преглед + Артикули | НЯМА |
| **SELLER 💼** | НЯМА | НЯМА |

---

# §3. ДВУСЛОЙНА АРХИТЕКТУРА

## 3.1 Mode като body class

`<body class="mode-detailed">` или `<body class="mode-simple">`.

**За stats.php Simple Mode НЕ съществува.** Цялото съдържание е под mode-detailed. Simple Mode достъп → само през s82-dash.

## 3.2 Backend split

```
stats.php                                  (entry + routing)
├── partials/stats-overview.php            (Tab 1: Преглед)
├── partials/stats-products.php            (Tab 2: Артикули)
├── partials/stats-finance.php             (Tab 3: Финанси router)
│   ├── stats-finance-profit.php           (12.1 Phase B)
│   ├── stats-finance-cashflow.php         (12.2 Phase 8)
│   ├── stats-finance-expenses.php         (12.3 Phase 8)
│   ├── stats-finance-receivables.php      (12.4 Phase 8)
│   └── stats-finance-exports.php          (12.5 Phase 8)
├── lib/stats-engine.php                   (SQL queries + caching)
├── lib/stats-ai-engine.php                (AI topic selector + Gemini)
└── lib/stats-formulas.php                 (Math: margin, P&L, fallbacks)
```

## 3.3 Routing logic

```php
$tab = $_GET['tab'] ?? 'overview';
$sub = $_GET['sub'] ?? null;

switch ($tab) {
    case 'overview':  require 'partials/stats-overview.php'; break;
    case 'products':  require 'partials/stats-products.php'; break;
    case 'finance':
        if ($role !== 'owner') {
            header('Location: stats.php?tab=overview'); exit;
        }
        $sub = $sub ?? 'profit';
        require "partials/stats-finance-{$sub}.php";
        break;
    default: require 'partials/stats-overview.php';
}
```

---

# §4. ИНФОРМАЦИОННА АРХИТЕКТУРА

## 4.1 Топ-ниво (3 таба)

```
┌─────────────────────────────────────────┐
│ [← back]  Справки    [☀]  [🛒 ПРОДАЖБА] │  header Тип Б
├─────────────────────────────────────────┤
│ [Преглед]  [Артикули]  [Финанси] 👑     │  tab bar
├─────────────────────────────────────────┤
│ [Днес] [7д] [30д] [90д] [Избор]        │  period pills (sticky)
├─────────────────────────────────────────┤
│ ⚠ 2 аномалии — N42 stockout, dead €340 │  alert ribbon (sticky)
├─────────────────────────────────────────┤
│  TAB CONTENT (scrollable)               │
└─────────────────────────────────────────┘
```

**3 таба, не 5** — на 375px × 105px (with padding) = 315px перфектно се събират. Аномалии = sticky ribbon, не отделен таб.

## 4.2 Финанси sub-tabs

При `?tab=finance` втори ред под главните:

```
[Печалба] [Cash] [Разходи] [Дължими] [Експорти]
```

- **12.1 Печалба** (Phase B) — P&L, margin, profit
- **12.2 Cash** (Phase 8) — balance, burn, break-even
- **12.3 Разходи** (Phase 8) — expenses, budget vs actual
- **12.4 Дължими** (Phase 8) — receivables, invoice overdue
- **12.5 Експорти** (Phase 8) — CSV/PDF, Microinvest, Z-reports

## 4.3 Period selector

```
[Днес]  [7д]  [30д]  [90д]  [Избор...]
```

- **Днес** — `DATE(created_at) = CURDATE()`
- **7д/30д/90д** — rolling, не calendar
- **Избор** — date picker, max 365 дни
- Period state preserve на tab switch (URL param)

## 4.4 Store selector

Multi-store tenant → store toggle в rms-subbar (както в life-board).

- Един магазин: filter на store_id
- Всички: aggregate sum + cross-store comparison
- Manager: fixed на неговия store_id

## 4.5 Alert ribbon

Sticky 32px лента с до 3 най-важни аномалии:
```
⚠ 3 аномалии — N42 stockout · €340 dead · просрочена Фактура #88
```
Tap → drawer със списък. Когато 0 аномалии → height: 0.

## 4.6 Drill-down модел

Tap на KPI карта → slide-up drawer с:
1. Голяма цифра
2. Sparkline (30 дни)
3. Top 5 контрибутори
4. AI insight (1-2 изречения)
5. Линк "Виж в Артикули →"

Drawer-и не са fullscreen — 64px видимост на header отзад.

---

# §5. DB SCHEMA — СЪЩЕСТВУВАЩИ ТАБЛИЦИ

Релевантни колони (full schemas в DOC_05_DB_FUNDAMENT.md).

## 5.1 `sales`
```sql
id, tenant_id, store_id, user_id, total, subtotal, discount_amount,
vat_amount, payment_method, status, customer_id, invoice_id,
created_at, canceled_at
```
**Критично:** `status='completed'` за всички KPI заявки.

## 5.2 `sale_items`
```sql
id, sale_id, product_id, quantity, unit_price, cost_at_sale,
discount_pct, line_total
```
**`cost_at_sale`** е snapshot — критично за исторически марж.

## 5.3 `products`
Релевантни: `cost_price`, `retail_price`, `wholesale_price`, `supplier_id`, `category_id`, `created_at`.

## 5.4 `inventory`
`tenant_id, store_id, product_id, quantity, min_quantity, max_quantity`.

## 5.5 `stock_movements`
`type` ENUM включва: sale, delivery, transfer_in, transfer_out, adjustment, return, damage. `MAX(created_at)` дава last_movement_date за dead stock.

## 5.6 `stores`
Препоръчвани добавки: `area DECIMAL(8,2)`, `rent_monthly DECIMAL(10,2)`.

## 5.7 `users`
Препоръчвани добавки: `commission_pct`, `hourly_rate`.

## 5.8 `tenants`
`country`, `currency`, `vat_number`, `plan`.

## 5.9 `ai_insights`
Вече съществува от S87:
```sql
id, tenant_id, store_id, type, severity, title, body,
retrieved_facts JSON, confidence DECIMAL(3,2),
shown_at, dismissed_at, action_taken
```

---

# §6. DB SCHEMA — НОВИ ТАБЛИЦИ

15 нови таблици. Phase B (3 beta-critical) + Phase 8 (12 post-beta).

## 6.1 PHASE B — Beta-critical

### M-001: `vat_rates`
```sql
CREATE TABLE vat_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    country VARCHAR(2) NOT NULL,
    rate_pct DECIMAL(5,2) NOT NULL,
    category_type ENUM('standard','reduced','zero','exempt') DEFAULT 'standard',
    name VARCHAR(50),
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_country_active (country, valid_to)
);

INSERT INTO vat_rates (country, rate_pct, category_type, name, valid_from) VALUES
('BG', 20.00, 'standard', 'Стандартна', '2007-01-01'),
('BG',  9.00, 'reduced', 'Намалена', '2007-01-01'),
('BG',  0.00, 'zero', 'Нулева', '2007-01-01');
```

### M-002: `z_reports`
```sql
CREATE TABLE z_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    report_date DATE NOT NULL,
    cash_total DECIMAL(10,2) DEFAULT 0,
    card_total DECIMAL(10,2) DEFAULT 0,
    transfer_total DECIMAL(10,2) DEFAULT 0,
    wholesale_total DECIMAL(10,2) DEFAULT 0,
    discount_total DECIMAL(10,2) DEFAULT 0,
    vat_breakdown JSON,
    transaction_count INT DEFAULT 0,
    cancel_count INT DEFAULT 0,
    generated_at DATETIME NOT NULL,
    generated_by INT,
    printed_at DATETIME NULL,
    cash_drawer_open DECIMAL(10,2),
    cash_drawer_close DECIMAL(10,2),
    cash_diff DECIMAL(10,2),
    notes TEXT,
    UNIQUE KEY uniq_tenant_store_date (tenant_id, store_id, report_date),
    INDEX idx_tenant_date (tenant_id, report_date)
);
```

### M-003: `store_balances`
```sql
CREATE TABLE store_balances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    balance_date DATE NOT NULL,
    cash_balance DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'EUR',
    snapshot_at DATETIME NOT NULL,
    snapshot_type ENUM('opening','closing','adjustment') DEFAULT 'closing',
    notes TEXT,
    UNIQUE KEY uniq_store_date_type (store_id, balance_date, snapshot_type),
    INDEX idx_tenant_date (tenant_id, balance_date)
);
```

## 6.2 PHASE 8 — Full Finance

### M-004: `expense_categories`
```sql
CREATE TABLE expense_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20),
    is_fixed BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES expense_categories(id),
    INDEX idx_tenant (tenant_id)
);
-- Default seed: Наем, Заплати, Електричество, Вода, Интернет, Доставки,
-- Реклама, Транспорт, Поддръжка, Канцеларски, Други
```

### M-005: `expenses`
```sql
CREATE TABLE expenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NULL,
    category_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    expense_date DATE NOT NULL,
    payment_method ENUM('cash','card','transfer','direct_debit') DEFAULT 'transfer',
    bank_account_id INT UNSIGNED NULL,
    description VARCHAR(500),
    receipt_path VARCHAR(255),
    invoice_number VARCHAR(50),
    supplier_id INT NULL,
    is_recurring BOOLEAN DEFAULT FALSE,
    recurring_id BIGINT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id),
    INDEX idx_tenant_date (tenant_id, expense_date),
    INDEX idx_tenant_store_date (tenant_id, store_id, expense_date)
);
```

### M-006: `fixed_costs`
```sql
CREATE TABLE fixed_costs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NULL,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    monthly_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    day_of_month TINYINT DEFAULT 1,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    auto_create BOOLEAN DEFAULT TRUE,
    last_generated DATE NULL,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id)
);
```

### M-007: `budgets`
```sql
CREATE TABLE budgets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NULL,
    category_id INT UNSIGNED NULL,
    period_type ENUM('month','quarter','year') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    planned_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id)
);
```

### M-008: `bank_accounts`
```sql
CREATE TABLE bank_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NULL,
    name VARCHAR(100) NOT NULL,
    bank_name VARCHAR(100),
    iban VARCHAR(34),
    swift VARCHAR(11),
    currency VARCHAR(3) DEFAULT 'EUR',
    account_type ENUM('checking','savings','card','cash_register','other') DEFAULT 'checking',
    current_balance DECIMAL(12,2) DEFAULT 0,
    balance_updated_at DATETIME,
    balance_source ENUM('manual','import','api') DEFAULT 'manual',
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### M-009: `bank_transactions`
```sql
CREATE TABLE bank_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    direction ENUM('in','out') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    transaction_date DATE NOT NULL,
    description VARCHAR(500),
    counterparty VARCHAR(200),
    reference VARCHAR(100),
    related_type ENUM('sale','expense','invoice_b2b','tax','transfer','adjustment') NULL,
    related_id BIGINT NULL,
    balance_after DECIMAL(12,2),
    import_source VARCHAR(50),
    imported_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES bank_accounts(id),
    INDEX idx_account_date (account_id, transaction_date),
    INDEX idx_tenant_date (tenant_id, transaction_date)
);
```

### M-010: `b2b_customers`
```sql
CREATE TABLE b2b_customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    eik VARCHAR(20),
    vat_number VARCHAR(20),
    address VARCHAR(500),
    city VARCHAR(100),
    country VARCHAR(2) DEFAULT 'BG',
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(50),
    credit_limit DECIMAL(10,2) DEFAULT 0,
    payment_terms_days INT DEFAULT 30,
    discount_default DECIMAL(5,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### M-011: `invoices_b2b`
```sql
CREATE TABLE invoices_b2b (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    sale_id BIGINT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    vat_amount DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    status ENUM('draft','issued','paid','partial','overdue','canceled') DEFAULT 'issued',
    paid_amount DECIMAL(10,2) DEFAULT 0,
    paid_at DATETIME NULL,
    notes TEXT,
    pdf_path VARCHAR(255),
    sent_email VARCHAR(100),
    sent_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (customer_id) REFERENCES b2b_customers(id),
    UNIQUE KEY uniq_tenant_number (tenant_id, invoice_number)
);
```

### M-012: `invoice_items`
```sql
CREATE TABLE invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT NULL,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    discount_pct DECIMAL(5,2) DEFAULT 0,
    vat_rate DECIMAL(5,2) NOT NULL,
    line_subtotal DECIMAL(10,2) NOT NULL,
    line_vat DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices_b2b(id) ON DELETE CASCADE
);
```

### M-013: `invoice_payments`
```sql
CREATE TABLE invoice_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    payment_date DATE NOT NULL,
    payment_method ENUM('cash','card','transfer','check') DEFAULT 'transfer',
    bank_account_id INT UNSIGNED NULL,
    reference VARCHAR(100),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (invoice_id) REFERENCES invoices_b2b(id)
);
```

### M-014: `tax_payments`
```sql
CREATE TABLE tax_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    tax_type ENUM('vat','income','social','property','other') NOT NULL,
    period_type ENUM('month','quarter','year') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    declared_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    payment_date DATE NULL,
    due_date DATE NOT NULL,
    status ENUM('estimated','declared','paid','overdue') DEFAULT 'estimated',
    payment_method ENUM('transfer','direct_debit','cash') NULL,
    reference VARCHAR(100),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### M-015: `accountant_exports`
```sql
CREATE TABLE accountant_exports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    export_type ENUM('sales','expenses','invoices','full_period','z_reports') NOT NULL,
    format ENUM('csv','excel','microinvest','sigma','ajur','pdf') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    file_path VARCHAR(255),
    file_size_kb INT,
    row_count INT,
    status ENUM('generating','ready','sent','failed') DEFAULT 'generating',
    sent_to_email VARCHAR(100),
    sent_at DATETIME NULL,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    generated_by INT
);
```

## 6.3 Обобщение

| # | Таблица | Phase | Записи (год.) | Критичност |
|---|---|---|---|---|
| M-001 | vat_rates | B | ~15 seed | 🔴 |
| M-002 | z_reports | B | ~365/store | 🔴 |
| M-003 | store_balances | B | ~365/store | 🟡 |
| M-004 | expense_categories | 8 | ~15 | 🟢 |
| M-005 | expenses | 8 | ~500/store | 🟢 |
| M-006 | fixed_costs | 8 | ~10 | 🟢 |
| M-007 | budgets | 8 | ~50 | 🟢 |
| M-008 | bank_accounts | 8 | ~5 | 🟢 |
| M-009 | bank_transactions | 8 | ~2000 | 🟢 |
| M-010 | b2b_customers | 8 | ~50 | 🟢 |
| M-011 | invoices_b2b | 8 | ~300 | 🟢 |
| M-012 | invoice_items | 8 | ~1500 | 🟢 |
| M-013 | invoice_payments | 8 | ~300 | 🟢 |
| M-014 | tax_payments | 8 | ~12 | 🟢 |
| M-015 | accountant_exports | 8 | ~30 | 🟢 |

## 6.4 Indices (composite за heavy queries)

```sql
CREATE INDEX idx_sm_product_date ON stock_movements(product_id, created_at);
CREATE INDEX idx_bt_account_date_dir ON bank_transactions(account_id, transaction_date, direction);
CREATE INDEX idx_si_product_cost ON sale_items(product_id, cost_at_sale);
CREATE INDEX idx_sales_tenant_status_date ON sales(tenant_id, status, created_at);
CREATE INDEX idx_expenses_tenant_date_cat ON expenses(tenant_id, expense_date, category_id);
```

---

═══════════════════════════════════════════════════════════════
# ETAP 2 — НОВИ СЕКЦИИ §7-§9b
═══════════════════════════════════════════════════════════════

# §7. МЕТРИКИ TIER 1 — MUST-HAVE

7 метрики, всеки магазин ги ползва. FREE+ (с ограничения).

За всяка:
- Бизнес дефиниция
- SQL формула (executable)
- Период вариации
- RBAC + Plan gate
- UI location
- AI insight потенциал
- PHP fallback wording

## 7.1 НЕТЕН ОБОРОТ (Net Revenue)

### Бизнес дефиниция
Сума на завършените продажби за периода, **след** отстъпки, **с** ДДС. Пулсът на магазина.

### SQL

```sql
SELECT SUM(s.total) AS net_revenue
FROM sales s
WHERE s.tenant_id = :tenant_id
  AND s.store_id IN (:store_ids)
  AND s.status = 'completed'
  AND s.created_at BETWEEN :period_start AND :period_end;
```

### Период вариации
- **Днес:** `DATE(created_at) = CURDATE()`
- **7д/30д/90д:** `created_at >= NOW() - INTERVAL N DAY` (rolling)
- **YTD:** `created_at >= CONCAT(YEAR(NOW()),'-01-01')`
- **Custom:** user-selected, max 365д

### Сравнения (винаги поне ЕДНО)

```sql
-- vs вчера (за period=today)
SELECT SUM(total) AS rev_yesterday FROM sales WHERE status='completed'
  AND tenant_id=:t AND DATE(created_at) = CURDATE() - INTERVAL 1 DAY;

-- vs същия ден миналата седмица (по-добро от vs вчера)
SELECT SUM(total) AS rev_same_dow_lw FROM sales WHERE status='completed'
  AND tenant_id=:t AND DATE(created_at) = CURDATE() - INTERVAL 7 DAY;

-- YoY (ако 13+ месеца данни)
SELECT SUM(total) AS rev_yoy FROM sales WHERE status='completed'
  AND tenant_id=:t
  AND YEAR(created_at) = YEAR(NOW()) - 1
  AND MONTH(created_at) = MONTH(NOW());
```

### % delta

```php
$pct = ($current > 0 && $previous > 0)
    ? round(($current - $previous) / $previous * 100, 1)
    : null;
```

### RBAC + Plan
- ✅ Owner, Manager
- ✅ FREE (само "Днес" без сравнения), START+ (всичко), PRO+ (+ YoY), BUSINESS (+ multi-store breakdown)

### UI location
- **Tab 1 Преглед** — голяма KPI карта (38px font, hue qd/q3/q1 според delta)
- **s82-dash** — главно число
- **Tab 3.1 Финанси > Печалба** — част от P&L

### AI insight потенциал — **Силен**

Темите от каталога:
| ID | Тема | Trigger |
|---|---|---|
| biz_revenue_001 | "4-тият петък над €800" | Pattern same-DOW 4+ |
| biz_revenue_002 | "Рекорден ден за месеца" | MAX(daily) in month |
| claude_021 | "YoY revenue comparison" | 365+ дни |
| cash_350 | "Monthly P&L" | End of month |

**Prompt fragment:**
```
Today's revenue: €847.
Same-DOW history (last 4 weeks): €756, €712, €805, €690.
4 consecutive Fridays > €700.

Compose ONE sentence (max 60 chars BG, conversational).
Owner: Митко.
```
Expected: *"Това е 4-тият петък над €700 — сезонът тръгва."*

### PHP fallback
```php
if ($pct === null) return "{$current}€ днес";
if ($pct >= 10)  return "{$current}€ — над миналия {$dow}";
if ($pct >= -10) return "{$current}€ — като миналия {$dow}";
return "{$current}€ — под миналия {$dow}";
```

---

## 7.2 БРОЙ ТРАНЗАКЦИИ (Transaction Count)

### Бизнес дефиниция
Завършени продажби. Клиентски поток. Ако оборотът расте, но транзакциите падат — бутик за богати. Обратното — евтин битак.

### SQL
```sql
SELECT COUNT(*) AS transaction_count
FROM sales
WHERE tenant_id = :tenant_id AND store_id IN (:store_ids)
  AND status = 'completed'
  AND created_at BETWEEN :period_start AND :period_end;
```

### RBAC + Plan
✅ Всички (които имат stats.php достъп). FREE+.

### UI location
- Tab 1 Преглед — KPI карта (втора)
- s82-dash — meta ред "7 продажби · vs 4 вчера"

### AI insight потенциал
**Ограничено.** Само в комбинация:
- "Оборот расте, транзакции падат" → AOV спекулация
- "Тих ден — 3 транзакции до 14ч"

### PHP fallback
```php
"{$count} продажби · vs {$prev} вчера"
```

---

## 7.3 СРЕДЕН ЧЕК (AOV)

### Бизнес дефиниция
Средно на касата. Най-бърза метрика за операционна ефективност. По-лесно е да вдигнеш AOV отколкото да привлечеш нови клиенти.

### SQL
```sql
SELECT
  CASE WHEN COUNT(*) = 0 THEN 0
       ELSE SUM(total) / COUNT(*) END AS avg_ticket
FROM sales
WHERE tenant_id = :t AND store_id IN (:s)
  AND status = 'completed'
  AND created_at BETWEEN :start AND :end;
```

### Сравнения
- vs предходен период
- vs 90-дневен avg (норма vs шум)

### RBAC + Plan
✅ Owner, Manager. START+ (FREE: само днес без сравнения).

### UI location
- Tab 1 Преглед — KPI карта (трета)
- Tab 2 Артикули — drill-down "AOV by category"

### AI insight
**Да.** "AOV падна 15% седмично" → discount leakage. "AOV расте 3 поредни седмици" → upsell success.

---

## 7.4 ТОП 5 АРТИКУЛА

### Бизнес дефиниция
Артикулите движещи бизнеса. **Два списъка** (бройки vs оборот) — често различни. **Трети** (печалба) — само owner.

### SQL — Топ 5 по бройки
```sql
SELECT p.id, p.name, p.code, p.retail_price,
       SUM(si.quantity) AS qty_sold,
       SUM(si.line_total) AS revenue_generated,
       SUM(si.quantity * (si.unit_price - COALESCE(si.cost_at_sale, 0))) AS profit_generated
FROM sale_items si
JOIN sales s ON s.id = si.sale_id
JOIN products p ON p.id = si.product_id
WHERE s.tenant_id = :t AND s.store_id IN (:s)
  AND s.status = 'completed'
  AND s.created_at BETWEEN :start AND :end
GROUP BY si.product_id
ORDER BY qty_sold DESC
LIMIT 5;
```

**По оборот:** същият query с `ORDER BY revenue_generated DESC`.
**По печалба:** `ORDER BY profit_generated DESC` (owner only).

### RBAC + Plan
- Бройки + оборот: owner, manager. FREE+.
- Печалба: **owner only**. PRO+.

### UI location
- Tab 1 Преглед — horizontal bar chart с toggle "Бройки/Оборот/Печалба"
- Tab 2 Артикули — пълен top 10 + drill-down
- s82-dash — top 1 в meta слот

### AI insight — **Силен**
- "Nike 42 продава 3× повече от Adidas — увеличи поръчката"
- "Top по оборот ≠ top по печалба"

### PHP fallback (top 1 за s82-dash)
```php
"Топ: {$top1['name']} · {$top1['qty_sold']} бр · {$top1['revenue']}€"
```

---

## 7.5 БРУТНА ПЕЧАЛБА И МАРЖ %

### Бизнес дефиниция
**Gross Profit** = Revenue − COGS. **Margin %** = Gross Profit / Revenue × 100.
Най-важната метрика за оцеляване. Оборот €50K @ 10% марж < €20K @ 50% марж.

### SQL
```sql
SELECT
  SUM(si.quantity * si.unit_price) AS gross_revenue,
  SUM(si.quantity * COALESCE(si.cost_at_sale, 0)) AS total_cogs,
  SUM(si.quantity * (si.unit_price - COALESCE(si.cost_at_sale, 0))) AS gross_profit,
  CASE WHEN SUM(si.quantity * si.unit_price) = 0 THEN 0
       ELSE SUM(si.quantity * (si.unit_price - COALESCE(si.cost_at_sale, 0)))
            / SUM(si.quantity * si.unit_price) * 100 END AS margin_pct
FROM sale_items si
JOIN sales s ON s.id = si.sale_id
WHERE s.tenant_id = :t AND s.store_id IN (:s)
  AND s.status = 'completed'
  AND s.created_at BETWEEN :start AND :end;
```

### Confidence warning
```php
$with_cost = COUNT(si.cost_at_sale IS NOT NULL);
$total = COUNT(si.id);
$confidence_pct = ($with_cost / $total) * 100;

if ($confidence_pct < 100) {
    echo "⚠ Приблизителна печалба — данни за {$confidence_pct}% от артикулите";
}
```

Тази логика **вече съществува** в текущия stats.php (виж §3.3, redove 1537-1543).

### RBAC + Plan
✅ **Само owner**. PRO+.

### UI location
- Tab 1 Преглед — KPI карта (owner only)
- Tab 3.1 Финанси > Печалба — главна метрика
- НЕ в s82-dash

### AI insight — **Много силен**
- "Маржът е 18% при норма 28% — провери отстъпките"
- "Премиум артикулите носят 60% от печалбата при 20% от оборота"

### PHP fallback
```php
"печалба: {$profit}€ (марж {$margin_pct}%)"
```

---

## 7.6 МЪРТЪВ КАПИТАЛ (Dead Stock)

### Бизнес дефиниция
Стойност на стока (cost_price) без продажба последните N дни. **Скрити пари** — №1 silent killer.

### Параметри
- Default: 90 дни
- Tenant override: 60/90/120/180

### SQL
```sql
SELECT p.id, p.name, p.code,
       SUM(i.quantity) AS qty_on_hand,
       p.cost_price,
       SUM(i.quantity) * p.cost_price AS dead_value,
       DATEDIFF(NOW(), COALESCE(MAX(sm.created_at), p.created_at)) AS days_idle
FROM inventory i
JOIN products p ON p.id = i.product_id
LEFT JOIN stock_movements sm
    ON sm.product_id = i.product_id
    AND sm.store_id = i.store_id
    AND sm.type = 'sale'
WHERE i.tenant_id = :t AND i.store_id IN (:s)
  AND i.quantity > 0 AND p.cost_price > 0
GROUP BY i.product_id, i.store_id
HAVING days_idle >= :zombie_days
ORDER BY dead_value DESC;
```

### Aggregate
```sql
SELECT SUM(qty_on_hand * cost_price) AS total_dead_capital FROM (...) subq;
```

### RBAC + Plan
- ✅ Owner (full + €)
- ⚠ Manager (брой без €)
- ✅ START+ (FREE: само брой "teaser")

### UI location
- Tab 1 Преглед — KPI "Замразени пари €340"
- Tab 2 Артикули > Мъртви — пълен списък 30/60/90/180
- Tab 3.1 Финанси > Печалба — P&L breakdown
- s82-dash — alert ако > €200 (rotation slot 4)

### AI insight — **Силен**
- "€340 в 5 артикула. Намали N42 с 20%?"
- "Зимна стока стои до пролет — отдай я"

### PHP fallback
```php
"{$count} артикула · €{$total_value} замразени"
```

---

## 7.7 LOW STOCK ALERT

### Бизнес дефиниция
Артикули под минимума **И** продавани последните 30 дни (не зомби). Предотвратява lost sales.

### SQL
```sql
SELECT p.id, p.name, p.code, i.store_id, st.name AS store_name,
       i.quantity AS current_qty, i.min_quantity,
       (SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id=si.sale_id
        WHERE si.product_id = p.id AND s.tenant_id = :t
          AND s.created_at >= NOW() - INTERVAL 30 DAY) AS sales_last_30d,
       (SELECT SUM(si.quantity) / 30.0 FROM sale_items si JOIN sales s ON s.id=si.sale_id
        WHERE si.product_id = p.id AND s.tenant_id = :t
          AND s.created_at >= NOW() - INTERVAL 30 DAY) AS avg_daily_velocity,
       CASE WHEN avg_daily_velocity > 0 THEN i.quantity / avg_daily_velocity
            ELSE 999 END AS days_until_zero
FROM inventory i
JOIN products p ON p.id = i.product_id
JOIN stores st ON st.id = i.store_id
WHERE i.tenant_id = :t AND i.store_id IN (:s)
  AND i.min_quantity > 0 AND i.quantity <= i.min_quantity
  AND p.id IN (SELECT DISTINCT si.product_id FROM sale_items si
               JOIN sales s ON s.id = si.sale_id
               WHERE s.tenant_id = :t
                 AND s.created_at >= NOW() - INTERVAL 30 DAY)
ORDER BY days_until_zero ASC, sales_last_30d DESC
LIMIT 20;
```

### RBAC + Plan
✅ Owner, Manager. FREE+.

### UI location
- Tab 1 Преглед — KPI "5 артикула близо до изчерпване"
- Tab 2 Артикули > Low stock — пълен с "Поръчай"
- Alert ribbon — ако days_until_zero < 3
- s82-dash — rotation slot 3

### AI insight — **Силен, action-oriented**
- "Nike 42: остават 2 бр, 0.8/ден → 2-3 дни. Поръчай 15."
- "Levi's 32 свърши, 12 продажби миналата седмица. Spешно."

### PHP fallback
```php
"{$name}: {$qty} бр (мин {$min}) · ~{$days_until_zero} дни"
```

---

## 7.8 Обобщение Tier 1

| # | Метрика | Owner | Manager | FREE | START | PRO | BUS |
|---|---|---|---|---|---|---|---|
| 1 | Net Revenue | ✅ | ✅ | ✅ днес | ✅ + сравн. | ✅ + YoY | ✅ + multi |
| 2 | Transactions | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 3 | AOV | ✅ | ✅ | ✅ днес | ✅ | ✅ | ✅ |
| 4 | Top 5 (units/rev) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 4b | Top 5 (profit) | ✅ | — | — | — | ✅ | ✅ |
| 5 | Margin/Profit | ✅ | — | — | — | ✅ | ✅ |
| 6 | Dead Stock | ✅ | ⚠ | ⚠ брой | ✅ +€ | ✅ | ✅ |
| 7 | Low Stock | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

---

# §8. МЕТРИКИ TIER 2 — ВАЖНИ

7 метрики за магазин с 2-3 месеца история. START+ unlock.

## 8.1 DISCOUNT RATE %

### Бизнес
Колко от потенциалния оборот е "изядено" от отстъпки. БГ феномен: продавачите масово отстъпват за "наши хора".

### SQL — aggregate
```sql
SELECT
  SUM(s.discount_amount) AS total_discount,
  SUM(s.total + s.discount_amount) AS gross_potential,
  CASE WHEN SUM(s.total + s.discount_amount) = 0 THEN 0
       ELSE SUM(s.discount_amount) / SUM(s.total + s.discount_amount) * 100
       END AS discount_rate_pct
FROM sales s
WHERE s.tenant_id = :t AND s.store_id IN (:s)
  AND s.status = 'completed'
  AND s.created_at BETWEEN :start AND :end;
```

### SQL — per seller (owner only)
```sql
SELECT u.id, u.name AS seller_name,
       COUNT(s.id) AS sales_count,
       SUM(s.discount_amount) AS total_discount,
       SUM(s.discount_amount) / SUM(s.total + s.discount_amount) * 100 AS seller_discount_pct
FROM sales s
JOIN users u ON u.id = s.user_id
WHERE s.tenant_id = :t AND s.status = 'completed'
  AND s.discount_amount > 0
  AND s.created_at >= NOW() - INTERVAL 30 DAY
GROUP BY s.user_id
ORDER BY total_discount DESC;
```

### RBAC + Plan
✅ Owner (full + per seller), ⚠ Manager (aggregate). PRO+.

### UI
- Tab 1 — KPI карта
- Tab 3.1 Финанси > Печалба — discount erosion

### AI темите
- price_031 "Discounts eat margin"
- price_032 "One seller most discounts"

---

## 8.2 SALES BY HOUR

### Бизнес
В кои часове идват пари. Оптимизация на смените.

### SQL — hour breakdown
```sql
SELECT HOUR(s.created_at) AS hour_of_day,
       COUNT(*) AS transaction_count,
       SUM(s.total) AS hour_revenue,
       AVG(s.total) AS hour_avg_ticket
FROM sales s
WHERE s.tenant_id = :t AND s.store_id IN (:s)
  AND s.status = 'completed'
  AND s.created_at >= NOW() - INTERVAL :days DAY
GROUP BY HOUR(s.created_at)
ORDER BY hour_of_day;
```

### SQL — DoW × Hour matrix (PRO+ heatmap)
```sql
SELECT DAYOFWEEK(created_at) AS dow,
       HOUR(created_at) AS hour_of_day,
       COUNT(*) AS cnt, SUM(total) AS rev
FROM sales
WHERE tenant_id = :t AND status='completed'
  AND created_at >= NOW() - INTERVAL 90 DAY
GROUP BY DAYOFWEEK(created_at), HOUR(created_at);
```

### RBAC + Plan
✅ Owner, Manager. PRO+.

### UI
- Tab 1 — vertical bar chart (8-20ч)
- Tab 2 — full 24h + heatmap 24×7 (drill-down drawer на 375px)

### AI темите
- pos_xxx "Peak hour"
- claude_016 "Hour of day peak"

---

## 8.3 SALES BY CATEGORY

### SQL
```sql
SELECT c.id, c.name,
       COUNT(DISTINCT si.sale_id) AS sales_count,
       SUM(si.quantity) AS items_sold,
       SUM(si.line_total) AS category_revenue,
       SUM(si.quantity * COALESCE(si.cost_at_sale, 0)) AS category_cogs,
       SUM(si.quantity * (si.unit_price - COALESCE(si.cost_at_sale, 0))) AS category_profit,
       AVG(si.unit_price - COALESCE(si.cost_at_sale, 0)) / NULLIF(AVG(si.unit_price), 0) * 100 AS avg_margin_pct
FROM sale_items si
JOIN sales s ON s.id = si.sale_id
JOIN products p ON p.id = si.product_id
JOIN categories c ON c.id = p.category_id
WHERE s.tenant_id = :t AND s.store_id IN (:s)
  AND s.status = 'completed'
  AND s.created_at BETWEEN :start AND :end
GROUP BY p.category_id
ORDER BY category_revenue DESC;
```

### % calc (PHP)
```php
$total = array_sum(array_column($categories, 'category_revenue'));
foreach ($categories as &$c) {
    $c['pct_of_total'] = $total > 0 ? round($c['category_revenue'] / $total * 100, 1) : 0;
}
```

### RBAC + Plan
✅ Owner (full + profit), ⚠ Manager (без profit). PRO+.

### UI
- Tab 1 — donut (≤3 категории) ИЛИ horizontal bar (4+)
- Tab 2 — пълна таблица + drill-down

---

## 8.4 SELLER PERFORMANCE

### SQL
```sql
SELECT u.id, u.name, u.commission_pct,
       COUNT(s.id) AS sales_count,
       SUM(s.total) AS total_revenue,
       AVG(s.total) AS avg_ticket,
       SUM(s.discount_amount) AS discounts_given,
       SUM(si.quantity) AS items_sold,
       SUM(si.quantity * (si.unit_price - COALESCE(si.cost_at_sale, 0))) AS profit_generated,
       u.commission_pct * SUM(s.total) / 100 AS estimated_commission
FROM users u
LEFT JOIN sales s ON s.user_id = u.id
    AND s.tenant_id = :t AND s.status = 'completed'
    AND s.created_at BETWEEN :start AND :end
LEFT JOIN sale_items si ON si.sale_id = s.id
WHERE u.tenant_id = :t AND u.role IN ('seller','manager','owner')
GROUP BY u.id
ORDER BY total_revenue DESC;
```

### RBAC + Plan
- ✅ **Owner only** (incl. profit, commission)
- ❌ Manager (вижда list, не revenue/profit)
- ✅ PRO+

### Privacy
Manager НЕ вижда compensation детайли.

---

## 8.5 WoW COMPARISON

### Бизнес
Сравнение **същия ден от седмицата**. Премахва дневния шум.

### SQL
```sql
-- Текущ петък
SELECT SUM(total) FROM sales WHERE status='completed' AND tenant_id=:t
  AND DATE(created_at) = CURDATE();

-- Миналия петък
SELECT SUM(total) FROM sales WHERE status='completed' AND tenant_id=:t
  AND DATE(created_at) = CURDATE() - INTERVAL 7 DAY;

-- 4 предишни петъка (pattern detection)
SELECT WEEK(created_at) AS week_num, SUM(total) AS week_revenue
FROM sales WHERE status='completed' AND tenant_id=:t
  AND DAYOFWEEK(created_at) = DAYOFWEEK(NOW())
  AND created_at >= NOW() - INTERVAL 5 WEEK
  AND DATE(created_at) <= CURDATE() - INTERVAL 7 DAY
GROUP BY WEEK(created_at)
ORDER BY week_num DESC LIMIT 4;
```

### RBAC + Plan
✅ Non-seller. START+ (7d); PRO+ (30/90d).

### UI
- Tab 1 — % индикатор под голямата цифра
- s82-dash — meta "като миналия петък"

---

## 8.6 BASKET SIZE

### SQL
```sql
SELECT AVG(items_per_sale) AS avg_basket_size FROM (
  SELECT s.id, SUM(si.quantity) AS items_per_sale
  FROM sales s JOIN sale_items si ON si.sale_id = s.id
  WHERE s.tenant_id = :t AND s.status = 'completed'
    AND s.created_at BETWEEN :start AND :end
  GROUP BY s.id
) sub;
```

### Trend SQL
```sql
SELECT YEARWEEK(created_at) AS yw, AVG(items_count) AS weekly_basket FROM (
  SELECT s.id, s.created_at, SUM(si.quantity) AS items_count
  FROM sales s JOIN sale_items si ON si.sale_id = s.id
  WHERE s.tenant_id = :t AND s.status = 'completed'
    AND s.created_at >= NOW() - INTERVAL 12 WEEK
  GROUP BY s.id
) sub GROUP BY YEARWEEK(created_at) ORDER BY yw DESC LIMIT 12;
```

### RBAC + Plan
✅ Owner, Manager. START+.

### AI темите
- basket_xxx
- claude_024 "Basket size declining"

---

## 8.7 RETURNS RATE

### SQL
```sql
SELECT
  COUNT(*) AS total_sales,
  SUM(CASE WHEN s.status = 'returned' THEN 1 ELSE 0 END) AS returned_count,
  SUM(CASE WHEN s.status = 'returned' THEN ABS(s.total) ELSE 0 END) AS returned_value,
  SUM(s.total) AS gross_sales,
  CASE WHEN COUNT(*) = 0 THEN 0
       ELSE SUM(CASE WHEN s.status='returned' THEN 1 ELSE 0 END) / COUNT(*) * 100
       END AS return_rate_pct
FROM sales s
WHERE s.tenant_id = :t AND s.created_at BETWEEN :start AND :end;
```

### RBAC + Plan
✅ Owner, Manager. START+.

### AI темите
- ret_xxx (rate per category, per supplier)

---

## 8.8 Обобщение Tier 2

| # | Метрика | Plan | Owner | Manager |
|---|---|---|---|---|
| 8 | Discount Rate | PRO+ | ✅ + per seller | ⚠ aggregate |
| 9 | Sales by Hour | PRO+ | ✅ | ✅ |
| 10 | Category | PRO+ | ✅ + profit | ⚠ без profit |
| 11 | Seller Performance | PRO+ | ✅ full | ⚠ без revenue |
| 12 | WoW | START+ | ✅ | ✅ |
| 13 | Basket Size | START+ | ✅ | ✅ |
| 14 | Returns Rate | START+ | ✅ | ✅ |

---

# §9. МЕТРИКИ TIER 3 — ADVANCED

6 метрики за зрял dataset (90+ дни) или multi-store. PRO/BUSINESS.

## 9.1 STOCK TURNOVER

### Бизнес
Колко пъти складът се "обръща" (продава + презарежда).

### SQL
```sql
-- COGS за периода
SELECT SUM(si.quantity * COALESCE(si.cost_at_sale, 0)) AS period_cogs
FROM sale_items si JOIN sales s ON s.id = si.sale_id
WHERE s.tenant_id = :t AND s.status='completed'
  AND s.created_at BETWEEN :start AND :end;

-- Avg inventory cost (изисква cron snapshots)
SELECT AVG(daily_inventory_value) AS avg_inventory_cost FROM (
  SELECT DATE(snapshot_at) AS snap_date,
         SUM(quantity * p.cost_price) AS daily_inventory_value
  FROM inventory_daily_snapshots ids
  JOIN products p ON p.id = ids.product_id
  WHERE ids.tenant_id = :t
    AND ids.snapshot_at BETWEEN :start AND :end
  GROUP BY DATE(snapshot_at)
) sub;

-- Turnover
$turnover = $period_cogs / $avg_inventory_cost;
$dii = $days_in_period / $turnover;  -- Days of Inventory
```

**Изисква:** nightly cron snapshot за `inventory_daily_snapshots` (Phase 8.6).

### RBAC + Plan
✅ **Owner only**. BUSINESS.

---

## 9.2 GMROI

### Бизнес
**GMROI** = Gross Profit / Average Inventory Cost.
> 2 = здрав retail. < 1 = губиш пари.

### Per category SQL
```sql
SELECT c.name AS category,
       SUM(si.quantity * (si.unit_price - si.cost_at_sale)) AS profit,
       AVG(i.quantity * p.cost_price) AS avg_inv_cost,
       SUM(si.quantity * (si.unit_price - si.cost_at_sale)) / AVG(i.quantity * p.cost_price) AS cat_gmroi
FROM sale_items si
JOIN sales s ON s.id = si.sale_id
JOIN products p ON p.id = si.product_id
JOIN categories c ON c.id = p.category_id
JOIN inventory i ON i.product_id = p.id
WHERE s.tenant_id = :t AND s.status='completed'
  AND s.created_at >= NOW() - INTERVAL 90 DAY
GROUP BY p.category_id
ORDER BY cat_gmroi DESC;
```

### RBAC + Plan
✅ **Owner only**. BUSINESS.

---

## 9.3 SUPPLIER PERFORMANCE

### SQL
```sql
SELECT sup.id, sup.name,
       COUNT(DISTINCT p.id) AS sku_count,
       SUM(si.quantity) AS units_sold,
       SUM(si.line_total) AS supplier_revenue,
       SUM(si.quantity * (si.unit_price - si.cost_at_sale)) AS supplier_profit,
       AVG((si.unit_price - si.cost_at_sale) / si.unit_price * 100) AS avg_margin_pct,
       (SELECT AVG(DATEDIFF(d.received_at, d.ordered_at)) FROM deliveries d
        WHERE d.supplier_id = sup.id) AS avg_lead_days,
       (SELECT COUNT(*) FROM deliveries d
        WHERE d.supplier_id = sup.id
          AND d.received_at > d.expected_at + INTERVAL 3 DAY) AS late_deliveries
FROM suppliers sup
LEFT JOIN products p ON p.supplier_id = sup.id
LEFT JOIN sale_items si ON si.product_id = p.id
LEFT JOIN sales s ON s.id = si.sale_id AND s.status='completed'
    AND s.created_at >= NOW() - INTERVAL 90 DAY
WHERE sup.tenant_id = :t
GROUP BY sup.id
ORDER BY supplier_profit DESC;
```

**Зависи от:** `deliveries` таблица (0% статус в момента — Phase A).

### RBAC + Plan
✅ **Owner only**. BUSINESS.

---

## 9.4 CROSS-STORE COMPARISON

### SQL
```sql
SELECT st.id, st.name, st.area, st.rent_monthly,
       COUNT(DISTINCT s.id) AS sales_count,
       SUM(s.total) AS store_revenue,
       AVG(s.total) AS store_avg_ticket,
       SUM(si.quantity * (si.unit_price - COALESCE(si.cost_at_sale, 0))) AS store_profit,
       SUM(s.total) / NULLIF(st.area, 0) AS revenue_per_sqm,
       (SELECT COUNT(DISTINCT u.id) FROM users u
        WHERE u.tenant_id = :t AND u.role IN ('seller','manager')) AS active_sellers
FROM stores st
LEFT JOIN sales s ON s.store_id = st.id
    AND s.status = 'completed'
    AND s.created_at BETWEEN :start AND :end
LEFT JOIN sale_items si ON si.sale_id = s.id
WHERE st.tenant_id = :t
GROUP BY st.id
ORDER BY store_revenue DESC;
```

### RBAC + Plan
✅ **Owner only**. **BUSINESS only** (multi-store flag).

### UI
- Tab 1 — секция "Магазини" (multi-store only)
- Tab 3.1 — store breakdown

---

## 9.5 ABC ANALYSIS

### Бизнес
- **A** = 80% revenue (типично 20% SKU)
- **B** = 15% revenue
- **C** = 5% revenue (dead zone)

### SQL
```sql
WITH product_revenue AS (
    SELECT p.id, p.name,
           SUM(si.line_total) AS rev,
           SUM(si.quantity) AS qty
    FROM sale_items si JOIN sales s ON s.id = si.sale_id
    JOIN products p ON p.id = si.product_id
    WHERE s.tenant_id = :t AND s.status = 'completed'
      AND s.created_at >= NOW() - INTERVAL 90 DAY
    GROUP BY si.product_id
),
ranked AS (
    SELECT *,
           SUM(rev) OVER () AS total_rev,
           SUM(rev) OVER (ORDER BY rev DESC) AS cumulative_rev,
           RANK() OVER (ORDER BY rev DESC) AS rev_rank
    FROM product_revenue
)
SELECT *, cumulative_rev / total_rev * 100 AS cum_pct,
       CASE
           WHEN cumulative_rev / total_rev <= 0.80 THEN 'A'
           WHEN cumulative_rev / total_rev <= 0.95 THEN 'B'
           ELSE 'C'
       END AS abc_class
FROM ranked
ORDER BY rev DESC;
```

### RBAC + Plan
✅ Owner, Manager. BUSINESS.

---

## 9.6 SEASONAL INDEX / YoY

### SQL
```sql
SELECT
  SUM(CASE WHEN YEAR(created_at) = YEAR(NOW())
           AND MONTH(created_at) = MONTH(NOW()) THEN total ELSE 0 END) AS current_month,
  SUM(CASE WHEN YEAR(created_at) = YEAR(NOW()) - 1
           AND MONTH(created_at) = MONTH(NOW()) THEN total ELSE 0 END) AS last_year_month
FROM sales WHERE tenant_id = :t AND status = 'completed';
```

**Изисквания:** 13+ месеца история (иначе "Недостатъчно данни").

### RBAC + Plan
✅ Owner. PRO+.

---

## 9.7 Обобщение Tier 3

| # | Метрика | Plan | Зависи от |
|---|---|---|---|
| 15 | Stock Turnover | BUSINESS | Daily inventory snapshots (cron) |
| 16 | GMROI | BUSINESS | Stock Turnover |
| 17 | Supplier Performance | BUSINESS | `deliveries` таблица |
| 18 | Cross-store | BUSINESS | Multi-store tenant |
| 19 | ABC Analysis | BUSINESS | 90+ дни |
| 20 | Seasonal/YoY | PRO+ | 13+ месеца |

---

# §9b. AI TOPICS MAPPING КЪМ UI СЛОТОВЕ

170 теми → **слот-базиран модел**. Не всички наведнъж. `stats-ai-engine.php` избира **3-5 най-релевантни на слот**.

## 9b.1 Слотове

| Таб | Слотове | Капацитет |
|---|---|---|
| Tab 1 Преглед | 1 главен + 1 alert ribbon + 2 drill-down | 4 |
| Tab 2 Артикули | 1 главен + 1 anomaly | 2 |
| Tab 3 Финанси (per sub-tab) | 1 главен + 1 trend | 2 |
| s82-dash | 3 ротиращи | 3 |

**Общо ~18 концурентни AI insights.**

## 9b.2 Selection algorithm

```php
function selectInsights($module='stats', $sub_tab='overview', $context=[]) {
    // 1. Load topics
    $topics = loadTopics(['module' => $module]);

    // 2. Filter RBAC
    $topics = filterByRole($topics, $context['role']);

    // 3. Filter Plan
    $topics = filterByPlan($topics, $context['plan']);

    // 4. Filter data availability
    // (cash topics се скриват ако bank_accounts празно)
    $topics = filterByDataAvailability($topics, $context);

    // 5. Score:
    //    - priority (1=critical, 10=passive)
    //    - recency relevance (Monthly VAT — само 20-25 ден)
    //    - confidence boost (≥0.85)
    $topics = scoreTopics($topics, $context);

    // 6. Take top N
    $capacity = SLOT_CAPACITY[$sub_tab] ?? 2;
    return array_slice($topics, 0, $capacity);
}
```

## 9b.3 Mapping по таб

### Tab 1 Преглед — главен слот

**Кандидати:** biz_revenue_*, biz_profit_*, biz_season_*, claude_021-024, pos_xxx peak hour.

**Приоритет:**
1. Anomaly (p=1-2) — "Discount rate скочи от 8% на 18%"
2. Pattern (p=3-4) — "4-тият петък над €800"
3. Operational (p=5-6) — "Пиков час 17-19ч"
4. Passive (p=7-10) — "Най-силен месец"

### Tab 2 Артикули

**Кандидати:** stock, zombie, new, size, fashion, shoes (валидни тук дори с module="products").

### Tab 3.1 Финанси > Печалба

**Кандидати:** profit (25), price (24).
- profit_001 "Маржът е 18% при норма 28%"
- profit_002 "Печалба пада при растящ оборот"
- price_031 "Discounts eat margin"

### Tab 3.2 Финанси > Cash

**Кандидати:** cash (24).
- cash_330 "Дневен cash flow"
- cash_338 "Cash vs monthly expenses"
- cash_335 "Break-even point"
- cash_346 "Seasonal cash crunch"

### Tab 3.3 Финанси > Разходи

**Кандидати (нови, ще се каталогизират в Phase 8):**
- exp_001 "Наемът е 18% (норма 12-15%)"
- exp_002 "Заплати + наем = 65% (норма 45-55%)"
- exp_003 "Над-бюджет: Реклама с €200"

### Tab 3.4 Финанси > Дължими

**Кандидати:** tax (23), cash (receivables).
- tax_002 "Monthly VAT filing (ден 20-25)"
- tax_007 "Invoice overdue 60+"
- cash_339 "WS receivables aging"

## 9b.4 AI prompt template (Stats generic)

```text
<system>
Ти си AI бизнес консултант в RunMyStore.AI. Анализираш данните на магазин
{store_name} ({country}) за {role_label} {owner_name}.

ПРАВИЛА:
- БГ език. Conversational. Max 60 chars per изречение.
- НЕ показвай числа които вече са на екрана.
- Добавяй КОНТЕКСТ или ДЕЙСТВИЕ.
- НЕ "оборотът е X" — Митко вижда X.
- Кажи "4-тият петък над X" или "поръчай Y".
- При confidence <0.85 — "приблизително" или премълчи.
</system>

<context>
period: {period_label}
metrics:
  revenue_now: {revenue}
  revenue_same_dow_last_week: {prev_dow}
  revenue_yoy: {yoy}
  margin_pct: {margin}
  top_product: {top_product_name} ({top_qty} бр)
  dead_capital: €{dead_capital}
  low_stock_top: {low_stock_top_name} (остават {low_stock_qty})

retrieved_facts: {json_dump}
</context>

<task>
Генерирай ЕДНО изречение insight за главния слот на Tab 1.
Приоритет:
1. Аномалия (>2σ) → опиши я
2. Pattern (3+ поредни same-DOW) → опиши го
3. Top product outlier → препоръчай action
4. Passive context (най-силен петък в месеца)

Отговор: само текстa, без quotes, без префикси.
</task>
```

## 9b.5 Confidence routing

```php
function routeByConfidence($insight) {
    if ($insight->confidence >= 0.85) return 'display';
    if ($insight->confidence >= 0.50) return 'display_with_marker';
    return 'suppress';
}
```

## 9b.6 Retrieved facts audit (Закон №7)

Всеки insight записва **точно SQL резултатите**:

```json
{
  "insight_id": 12345,
  "topic_id": "biz_revenue_001",
  "rendered_text": "Това е 4-тият петък над €700.",
  "confidence": 0.92,
  "retrieved_facts": {
    "query_1": {
      "sql": "SELECT SUM(total) FROM sales WHERE...",
      "params": {"tenant_id": 7, "date": "2026-05-15"},
      "result": 847.00
    },
    "query_2": {
      "sql": "SELECT WEEK(...), SUM(total) FROM sales WHERE DAYOFWEEK=...",
      "result": [847, 756, 712, 805, 690]
    },
    "logic": "4 consecutive Fridays > 700"
  },
  "shown_at": "2026-05-17 09:32:14"
}
```

Записва се в `ai_insights.retrieved_facts JSON` (вече съществуваща колона от S87).

## 9b.7 PHP fallback wording

```php
function fallbackText($metric, $data) {
    switch ($metric) {
        case 'revenue':
            $pct = $data['delta_pct'] ?? null;
            if ($pct === null) return "{$data['current']}€ днес";
            $dir = $pct > 0 ? 'над' : ($pct < 0 ? 'под' : 'като');
            return "{$data['current']}€ — {$dir} миналия {$data['dow']}";

        case 'top_product':
            return "Топ: {$data['name']} · {$data['qty']} бр · {$data['rev']}€";

        case 'dead_capital':
            return "Замразени: €{$data['total']} в {$data['count']} артикула";

        case 'low_stock':
            return "Свършва: {$data['name']} — {$data['qty']} бр";

        default: return "";
    }
}
```

**"AI мълчи, PHP продължава":** при AI fail, UI не показва spinner forever; fallback моментално.

---

# 🏁 КРАЙ НА ETAP 2

**Покрити секции:** §7 + §8 + §9 + §9b
**Общ обем досега:** ~2300 реда

**Какво е готово:**
- 20 KPI метрики (Tier 1+2+3) с пълни SQL формули
- AI Topics mapping към 18 UI слота
- AI prompt template
- Confidence routing
- PHP fallback за всеки KPI

**Следваща стъпка (ETAP 3):** §10-§17 — UI спецификация на 3-те таба + 5 sub-секции на Финанси + RBAC matrix + plan gating UI.

═══════════════════════════════════════════════════════════════
# ETAP 3 — UI СПЕЦИФИКАЦИЯ §10-§17
═══════════════════════════════════════════════════════════════

# §10. TAB 1 "ПРЕГЛЕД" — UI СПЕЦИФИКАЦИЯ

## 10.1 Wireframe (375px viewport)

```
┌─────────────────────────────────────────┐  0px
│ [←] Справки           [☀] [🛒 ПРОДАЖБА] │  56px (header Тип Б)
├─────────────────────────────────────────┤  56px
│ [Цариградско ▼] | Справки | [Лесен →]  │  40px (subbar sticky)
├─────────────────────────────────────────┤  96px
│ [Преглед]  [Артикули]  [Финанси] 👑    │  44px (tab bar)
├─────────────────────────────────────────┤  140px
│ [Днес] [7д] [30д] [90д] [Избор]        │  36px (period pills sticky)
├─────────────────────────────────────────┤  176px
│ ⚠ 2 аномалии — N42 stockout · €340     │  32px (alert ribbon, ако > 0)
├─────────────────────────────────────────┤  208px (или 176px ако няма ribbon)
│                                         │
│  ┌─────────────────────────────────┐   │  card 1: HERO REVENUE
│  │ ОБОРОТ ДНЕС · Цариградско   +12%│   │  glass qd, 120px
│  │ 847 € / 1 656.46 лв             │   │  38px font
│  │ 7 продажби · vs 6 миналия петък │   │
│  │ ✨ 4-тият петък над €700        │   │  AI insight slot (1)
│  └─────────────────────────────────┘   │
│                                         │
│  ┌────────────┐  ┌────────────┐        │  cards 2-3 (2 col grid)
│  │ 7 ПРОДАЖБИ │  │ СРЕДЕН ЧЕК │        │  glass q3 / q4, 88px
│  │ vs 6 вчера │  │ 121 € +8%  │        │
│  └────────────┘  └────────────┘        │
│                                         │
│  ┌────────────┐  ┌────────────┐        │  cards 4-5
│  │ МАРЖ 28% 👑│  │ ПЕЧАЛБА    │        │  q3 (gain hue)
│  │ ~237€ днес │  │ €237 +€26  │        │  (owner only)
│  └────────────┘  └────────────┘        │
│                                         │
│  ┌─────────────────────────────────┐   │  card 6: TOP 5 (toggle)
│  │ ТОП ПРОДАВАНО              [⚙] │   │  glass qd, ~280px
│  │ ┌──[Бройки]─[Оборот]─[Печалба]┐│   │  segmented control
│  │ │                              ││   │
│  │ │ Nike 42      ████████  3 бр  ││   │  horizontal bars
│  │ │ Levi's 501   ██████    2 бр  ││   │
│  │ │ H&M Tshirt   █████     2 бр  ││   │
│  │ │ Adidas 44    ███       1 бр  ││   │
│  │ │ Hugo колан   ██        1 бр  ││   │
│  │ └──────────────────────────────┘│   │
│  │ Виж всички 47 →                 │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  card 7: SPARKLINE
│  │ ТРЕНД 7 ДНИ              847€  │   │  glass qd, 110px
│  │ ╱╲                    ╱╲       │   │  inline svg
│  │   ╲╱╲    ╱╲       ╱╲╱          │   │
│  │      ╲╱╲╱  ╲    ╱╲╱            │   │
│  │ Пон Вт Ср Чт Пт Сб Нд          │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  card 8: HOURLY (PRO+)
│  │ ПО ЧАСОВЕ ДНЕС                 │   │  glass q4, 120px
│  │  ▁ ▁ ▂ ▄ ▅ ▆ ▇ █ █ ▇ ▆ ▄ ▂ ▁  │   │  bar chart (8-20ч)
│  │  9 10 11 12 13 14 15 16 17 18 19│   │
│  │ Пиков час: 17:00 — €180        │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  card 9: DEAD CAPITAL
│  │ ЗАМРАЗЕНИ ПАРИ             ⚠  │   │  glass q1 (loss), 96px
│  │ €340 в 5 артикула              │   │
│  │ Намали цените? →               │   │  action link
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  card 10: LOW STOCK
│  │ БЛИЗО ДО ИЗЧЕРПВАНЕ        ⚠  │   │  glass q5 (amber), 88px
│  │ 3 артикула — N42 свършва ~2дни │   │
│  │ Поръчай →                       │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  card 11: STORE COMPARE
│  │ МАГАЗИНИ ДНЕС (BUSINESS) 👑    │   │  glass qd, 140px
│  │ Цариградско   ████████  €847   │   │  multi-store only
│  │ Студентски    █████     €620   │   │
│  │ Витоша        ███       €410   │   │
│  └─────────────────────────────────┘   │
│                                         │
└─────────────────────────────────────────┘
  bottom-nav (56px sticky)
```

## 10.2 Компоненти

### 10.2.1 HERO REVENUE CARD (KPI #1)

**HTML структура:**
```html
<div class="glass kpi-hero qd">
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <span class="glow"></span><span class="glow glow-bottom"></span>

  <div class="kpi-label">Оборот днес · Цариградско</div>
  <div class="kpi-pct positive">+12%</div>

  <div class="kpi-number-row">
    <span class="kpi-num">847</span>
    <span class="kpi-cur">€</span>
    <span class="kpi-num-secondary">/ 1 656.46 лв</span>
  </div>

  <div class="kpi-meta">7 продажби · vs 6 миналия петък</div>

  <div class="kpi-ai-slot" data-slot="overview-main">
    ✨ <span class="ai-text">4-тият петък над €700</span>
  </div>
</div>
```

**CSS specs:**
- Width: 100% - 24px margin (gutters 12px)
- Height: ~120px (без AI slot) или ~148px (със)
- Padding: 16px 18px 14px
- Font: Montserrat
- `.kpi-num` — 38px, font-weight 900, letter-spacing -0.03em
- `.kpi-cur` — 14px mono, color text-muted
- `.kpi-num-secondary` — 13px mono, text-faint
- `.kpi-pct.positive` — bg hsl(145 50% 12%), color hsl(145 70% 65%), 12px mono
- `.kpi-pct.negative` — bg hsl(0 50% 12%), color hsl(0 80% 70%)
- `.kpi-pct.zero` — bg neutral, color text-muted
- AI slot — 28px height, sparkle SVG 14px + text
- Hue: `qd` (default indigo) — но **dynamic**: `q3` ако delta > +20%, `q1` ако delta < -10%

### 10.2.2 SECONDARY KPI CARDS (2-column grid)

**Grid layout:**
```css
.kpi-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 12px;
}
```

**Card structure (всяка от 4-те):**
```html
<div class="glass kpi-mini q3">
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <span class="glow"></span><span class="glow glow-bottom"></span>
  <div class="kpi-mini-label">СРЕДЕН ЧЕК</div>
  <div class="kpi-mini-value">121 €</div>
  <div class="kpi-mini-delta positive">+8%</div>
</div>
```

**Размери:** ~88px height, 16px padding.

**Hue assignment:**
- Transactions card: `q4` (cyan, neutral data)
- AOV card: `q3` (gain, ако > avg) ИЛИ `qd`
- Margin card: `q3` (gain) — **owner only**, hide за manager
- Profit card: `q3` — **owner only**

### 10.2.3 TOP 5 CARD с toggle

**HTML:**
```html
<div class="glass top5-card qd">
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <span class="glow"></span><span class="glow glow-bottom"></span>

  <div class="card-header">
    <h3>Топ продавано</h3>
    <button class="card-action-btn" data-action="settings">⚙</button>
  </div>

  <div class="segmented-control">
    <button class="seg-btn active" data-mode="units">Бройки</button>
    <button class="seg-btn" data-mode="revenue">Оборот</button>
    <button class="seg-btn" data-mode="profit" data-owner-only>Печалба</button>
  </div>

  <ul class="top5-list">
    <li class="top5-item">
      <span class="top5-name">Nike 42</span>
      <span class="top5-bar" style="--pct: 100%"></span>
      <span class="top5-value">3 бр</span>
    </li>
    <!-- ... 4 more -->
  </ul>

  <a href="stats.php?tab=products" class="card-link">Виж всички 47 →</a>
</div>
```

**Bar logic:**
```css
.top5-bar {
  width: var(--pct);   /* нормализирано спрямо max value */
  height: 6px;
  background: linear-gradient(90deg, hsl(var(--hue1) 60% 55%), hsl(var(--hue2) 60% 60%));
  border-radius: var(--radius-pill);
}
```

### 10.2.4 SPARKLINE CARD

**SVG inline (responsive):**
```html
<div class="glass sparkline-card qd">
  <!-- shine/glow spans -->
  <div class="card-header">
    <h3>Тренд 7 дни</h3>
    <span class="sparkline-current">847€</span>
  </div>
  <svg class="sparkline-svg" viewBox="0 0 280 50" preserveAspectRatio="none">
    <!-- area fill -->
    <path d="M0,40 L40,30 L80,42 L120,25 L160,35 L200,15 L240,28 L280,10 L280,50 L0,50 Z"
          fill="url(#sparkline-grad)" opacity="0.2"/>
    <!-- line -->
    <path d="M0,40 L40,30 L80,42 L120,25 L160,35 L200,15 L240,28 L280,10"
          stroke="hsl(var(--hue1) 60% 55%)" stroke-width="2" fill="none"/>
    <!-- dot on today -->
    <circle cx="280" cy="10" r="3" fill="hsl(var(--hue1) 60% 55%)"/>
    <defs>
      <linearGradient id="sparkline-grad" x1="0" x2="0" y1="0" y2="1">
        <stop offset="0%" stop-color="hsl(var(--hue1) 60% 55%)"/>
        <stop offset="100%" stop-color="hsl(var(--hue2) 60% 60%)"/>
      </linearGradient>
    </defs>
  </svg>
  <div class="sparkline-axis">
    <span>Пон</span><span>Вт</span><span>Ср</span><span>Чт</span>
    <span>Пт</span><span>Сб</span><span>Нд</span>
  </div>
</div>
```

### 10.2.5 HOURLY BARS CARD

```html
<div class="glass hourly-card q4">
  <div class="card-header"><h3>По часове днес</h3></div>
  <div class="hourly-bars">
    <!-- 12 bars (9:00-20:00) -->
    <div class="hourly-bar" style="--h: 15%"><span>9</span></div>
    <div class="hourly-bar" style="--h: 25%"><span>10</span></div>
    <!-- ... -->
    <div class="hourly-bar active" style="--h: 100%"><span>17</span></div>
    <div class="hourly-bar" style="--h: 85%"><span>18</span></div>
  </div>
  <div class="hourly-meta">Пиков час: 17:00 — €180</div>
</div>
```

**Bar styling:**
```css
.hourly-bar {
  flex: 1;
  height: var(--h);
  min-height: 4px;
  background: linear-gradient(to top, hsl(var(--hue1) 60% 45%), hsl(var(--hue1) 60% 65%));
  border-radius: 2px 2px 0 0;
  position: relative;
}
.hourly-bar.active {
  box-shadow: 0 0 12px hsl(var(--hue1) 60% 55% / 0.6);
}
.hourly-bar span {
  position: absolute;
  bottom: -16px;
  font-size: 9px;
  color: var(--text-muted);
}
```

### 10.2.6 ALERT CARDS (Dead capital, Low stock)

```html
<div class="glass alert-card q1">
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <span class="glow"></span><span class="glow glow-bottom"></span>
  <div class="alert-icon"><!-- SVG warning --></div>
  <div class="alert-body">
    <div class="alert-title">Замразени пари</div>
    <div class="alert-value">€340 в 5 артикула</div>
  </div>
  <a href="stats.php?tab=products&filter=dead" class="alert-action">Намали цените →</a>
</div>
```

## 10.3 Drill-down drawer (универсален pattern)

При tap на която и да е KPI карта → отваря slide-up drawer от долу:

```
┌─────────────────────────────────────────┐
│                                         │  (header остава видим, 64px)
│ ▬▬▬▬ (drag handle)                     │
│ ┌─────────────────────────────────┐    │
│ │ ОБОРОТ ДНЕС               [✕]  │    │
│ │ €847                            │    │
│ │ ╱╲    ╱╲╱╲     ╱╲╱╲             │    │  sparkline 30 дни
│ │ Топ контрибутори:               │    │
│ │ • Nike 42        €196 (23%)     │    │
│ │ • Levi's 501     €177 (21%)     │    │
│ │ • H&M Tshirt     €134 (16%)     │    │
│ │ ✨ Без аномалии. Стандартен ден.│    │  AI insight (drill slot 1)
│ │                                 │    │
│ │ [Виж в Артикули] [Експорт CSV] │    │
│ └─────────────────────────────────┘    │
└─────────────────────────────────────────┘
```

CSS:
```css
.kpi-drawer {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  height: calc(100vh - 64px);
  background: var(--surface);
  border-radius: 24px 24px 0 0;
  transform: translateY(100%);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 1000;
}
.kpi-drawer.open { transform: translateY(0); }
```

## 10.4 Empty state (нов магазин, 0 продажби)

```
┌─────────────────────────────────────────┐
│ [Преглед]  [Артикули]  [Финанси]       │
├─────────────────────────────────────────┤
│ [Днес] [7д] [30д] [90д]                │
├─────────────────────────────────────────┤
│                                         │
│         ┌───────────────┐               │
│         │   📊 SVG icon │               │
│         └───────────────┘               │
│                                         │
│    Още няма продажби днес               │
│                                         │
│    Като направиш първата продажба,     │
│    тук ще видиш числата.                │
│                                         │
│    [Започни продажба →]                │
│                                         │
└─────────────────────────────────────────┘
```

## 10.5 Loading state

При първоначално зареждане — **skeleton screens** (не spinners):

```html
<div class="glass kpi-hero qd skeleton">
  <div class="skeleton-line" style="width: 60%; height: 12px;"></div>
  <div class="skeleton-line" style="width: 80%; height: 38px; margin-top: 12px;"></div>
  <div class="skeleton-line" style="width: 50%; height: 11px; margin-top: 8px;"></div>
</div>
```

```css
.skeleton-line {
  background: linear-gradient(90deg,
    hsl(var(--hue1) 20% 30%) 0%,
    hsl(var(--hue1) 30% 40%) 50%,
    hsl(var(--hue1) 20% 30%) 100%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.5s ease infinite;
  border-radius: 6px;
}
@keyframes skeleton-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
```

## 10.6 Error state

При SQL failure:
```html
<div class="error-state">
  <svg><!-- warning icon --></svg>
  <div class="error-title">Грешка при зареждане</div>
  <div class="error-msg">Опитай отново след момент.</div>
  <button onclick="location.reload()">Презареди</button>
</div>
```

---

# §11. TAB 2 "АРТИКУЛИ" — UI СПЕЦИФИКАЦИЯ

## 11.1 Wireframe

```
┌─────────────────────────────────────────┐
│ [Преглед]  [Артикули✓]  [Финанси]      │  active tab indicator
├─────────────────────────────────────────┤
│ [Днес] [7д] [30д] [90д]                │
├─────────────────────────────────────────┤
│                                         │
│  ┌─────────────────────────────────┐   │  AI insight (slot 1)
│  │ ✨ Nike 42 продава 3× повече от │   │  glass q2 (magic), 64px
│  │   Adidas. Поръчай повече.       │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  TOP 10 (full list)
│  │ ТОП 10 АРТИКУЛА          [⚙]   │   │  glass qd
│  │ ┌──[Бр]─[€]─[Печалба]─────────┐│   │  segmented
│  │ │                              ││   │
│  │ │ Nike 42   ████████   3 бр   ││   │  taps → product detail
│  │ │ Levi's    ██████     2 бр   ││   │
│  │ │ ... (top 10 общо)           ││   │
│  │ └──────────────────────────────┘│   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  CATEGORIES
│  │ КАТЕГОРИИ                       │   │  glass qd
│  │ Облекло        ████████  62%   │   │  horizontal bars
│  │ Обувки         ████      28%   │   │
│  │ Аксесоари      ██         8%   │   │
│  │ Бижута         ▌          2%   │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  DEAD STOCK
│  │ МЪРТВИ АРТИКУЛИ            ⚠   │   │  glass q1
│  │ [30д][60д][90д✓][180д] фильтър │   │  filter pills
│  │ ─────────────────────────────── │   │
│  │ Adidas 38  €87  · 120 дни       │   │  scrollable list
│  │ Hugo NP    €65  · 95 дни        │   │
│  │ Levi's 50  €48  · 92 дни        │   │
│  │ ... (5 общо, scroll)            │   │
│  │ Общо: €340 замразени            │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  LOW STOCK
│  │ БЛИЗО ДО ИЗЧЕРПВАНЕ        ⚠  │   │  glass q5
│  │ Nike 42    2 бр · ~2 дни        │   │
│  │ Levi's 32  3 бр · ~4 дни        │   │
│  │ H&M XL     5 бр · ~6 дни        │   │
│  │ [Поръчай всички →]              │   │  CTA
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  PRODUCTS BY HOUR (PRO+)
│  │ КОГА КАКВО СЕ ПРОДАВА          │   │  glass q4, expandable
│  │ Сутрин (9-12): Аксесоари        │   │
│  │ Обед (12-15):  Облекло          │   │
│  │ След (15-18):  Обувки           │   │
│  │ Вечер (18-20): Аксесоари        │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  ABC ANALYSIS (BUSINESS)
│  │ ABC АНАЛИЗ 👑                   │   │  glass qd, accordion
│  │ A (80% оборот): 23 артикула     │   │
│  │ B (15%):        47 артикула     │   │
│  │ C (5%):        180 артикула  ▼  │   │
│  └─────────────────────────────────┘   │
│                                         │
└─────────────────────────────────────────┘
```

## 11.2 Drill-down: Product Detail Drawer

Tap на артикул в който и да е списък → drawer с пълни данни:

```
┌─────────────────────────────────────────┐
│  ▬▬▬▬                                  │
│  Nike Air Max 42                  [✕]  │
│  ┌─────────┐  Код: NK-AM-42            │
│  │ [image] │  Доставчик: Nike BG       │
│  │  120×120│  Категория: Обувки        │
│  └─────────┘  Цена: €65 / 127.13 лв    │
│                                         │
│  ПРОДАЖБИ 30 ДНИ                       │
│  ╱╲   ╱╲     ╱╲                        │  sparkline
│  ─────────────────                     │
│  Общо: 24 бр · €1 560                  │
│                                         │
│  МАРЖ: 35% · печалба/бр: €23           │
│                                         │
│  НАЛИЧНОСТ:                            │
│  • Цариградско: 2 бр (мин 5) ⚠        │
│  • Студентски:  8 бр                    │
│  • Витоша:      4 бр                    │
│                                         │
│  ✨ Свършва след ~2 дни. Поръчай 15.   │  AI insight
│                                         │
│  [Поръчай] [Трансфер] [Виж история]    │
└─────────────────────────────────────────┘
```

## 11.3 Dead Stock филтри

Sub-pills вътре в картата:
```css
.deadstock-filter-pills {
  display: flex;
  gap: 6px;
  margin: 8px 0;
}
.deadstock-pill {
  height: 22px; padding: 0 10px;
  font-size: 10px; font-weight: 700;
  border-radius: var(--radius-pill);
  background: var(--surface-2);
  cursor: pointer;
}
.deadstock-pill.active {
  background: linear-gradient(135deg, hsl(0 60% 55%), hsl(15 60% 60%));
  color: white;
}
```

## 11.4 Action handlers

| Action | Контекст | Resulting URL |
|---|---|---|
| Tap top product | Tab 2 top list | `products.php?id={pid}` (detail page) |
| Tap "Поръчай" | Low stock card | `orders.php?new&product_id={pid}` |
| Tap "Трансфер" | Product drawer | `transfers.php?new&product_id={pid}&from={sid}` |
| Tap "Виж в Артикули" | KPI drawer | `stats.php?tab=products&filter={...}` |

---

# §12. TAB 3 "ФИНАНСИ" — 5 SUB-СЕКЦИИ

## 12.0 Финанси taab navigation

При `?tab=finance` се появява втори ред tabs:

```
┌─────────────────────────────────────────┐
│ [Преглед]  [Артикули]  [Финанси✓] 👑   │
├─────────────────────────────────────────┤
│ [Печалба✓][Cash][Разходи][Дължими][Експ]│  sub-tab bar
├─────────────────────────────────────────┤
│ [Днес] [7д] [30д] [90д]                │
└─────────────────────────────────────────┘
```

**Bar styling (горно ниво):**
- 5 sub-tabs × ~70px = 350px
- На 375px viewport → horizontal scroll ако не се събират
- `data-tab` attribute за активен state
- Default `sub=profit`

**Owner-only gate:**
```php
if ($role !== 'owner') {
    header('Location: stats.php?tab=overview');
    exit;
}
```

## 12.1 SUB-SECTION: ПЕЧАЛБА (Phase B beta-ready)

### Wireframe

```
┌─────────────────────────────────────────┐
│  ┌─────────────────────────────────┐   │  HERO PROFIT CARD
│  │ ПЕЧАЛБА 30 ДНИ            +18% │   │  glass q3, 130px
│  │ €4 237 / 8 285.41 лв            │   │  38px num
│  │ Марж: 28% · Оборот: €15 132     │   │
│  │ ✨ Маржът пада 3 поредни седмици│   │  AI insight
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  P&L BREAKDOWN
│  │ PROFIT BREAKDOWN                │   │  glass qd, ~200px
│  │ ┌──────────────────────────────┐│   │  stacked bar
│  │ │ Оборот     ████████████ €15K││   │
│  │ │ COGS (−)   ████████ −€10.9K  ││   │  red bar
│  │ │ Печалба    ████ €4.2K        ││   │  green bar
│  │ └──────────────────────────────┘│   │
│  │ Марж: 28%                       │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  MARGIN TREND
│  │ ТРЕНД НА МАРЖА 12 СЕДМИЦИ      │   │  glass qd, 140px
│  │   30% ╱╲                        │   │  line chart
│  │       ╱  ╲╱╲     ╱╲╱╲   ╱─     │   │
│  │   25% ─────────────────────     │   │
│  │   20%                           │   │
│  │  S1 S2 S3 S4 S5 ... S12         │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  TOP PROFIT PRODUCTS
│  │ ТОП 5 ПО ПЕЧАЛБА                │   │
│  │ Hugo колан    ███████  €420     │   │
│  │ Levi's 501    █████    €280     │   │
│  │ Nike Air Max  ████     €236     │   │
│  │ ...                             │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  CATEGORY MARGIN
│  │ МАРЖ ПО КАТЕГОРИИ              │   │
│  │ Бижута       ██████████  62%   │   │  horizontal bars
│  │ Аксесоари    ████████    48%   │   │
│  │ Облекло      █████       28%   │   │
│  │ Обувки       ████        22%   │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  DISCOUNT EROSION
│  │ ОТСТЪПКИ          €1 120 (7.4%) │   │  glass q5
│  │ ✨ Над нормата (5%) — провери  │   │  AI alert
│  │ По продавачи:                   │   │
│  │ Мария    12% от продажбите     │   │
│  │ Иван     6%                     │   │
│  │ Пешо     2%                     │   │
│  └─────────────────────────────────┘   │
│                                         │
└─────────────────────────────────────────┘
```

### Confidence warning банер (top на sub-section)

Ако `confidence_pct < 100`:
```html
<div class="confidence-warning q5">
  <svg class="warn-icon"><!-- triangle --></svg>
  <span>Приблизителна печалба — данни за {pct}% от артикулите
  ({with_cost}/{total}). <a href="inventory.php">Инвентаризация →</a></span>
</div>
```

## 12.2 SUB-SECTION: CASH (Phase 8)

### Wireframe

```
┌─────────────────────────────────────────┐
│  ┌─────────────────────────────────┐   │  CASH BALANCE HERO
│  │ ОБЩА КАСОВА НАЛИЧНОСТ          │   │  glass qd, 140px
│  │ €18 432 / 36 053.78 лв          │   │
│  │ +€847 днес                      │   │
│  │ ✨ Стигат за ~3.2 месеца разходи│   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  ACCOUNTS BREAKDOWN
│  │ ПО СМЕТКИ                      │   │
│  │ ┌─ Каса магазин 1   €1 247    │   │
│  │ ├─ Каса магазин 2   €862      │   │
│  │ ├─ ДСК сметка       €14 320   │   │
│  │ └─ Картова касетка  €2 003    │   │
│  │ [+ Добави сметка]              │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  CASH FLOW 30D
│  │ CASH FLOW 30 ДНИ               │   │  line chart
│  │  ╱─╲                           │   │
│  │ ╱   ╲      ╱╲                  │   │
│  │ ──────────────────  €18K       │   │
│  │ Постъпления: +€15 132          │   │
│  │ Изхарчени:   −€11 248          │   │
│  │ Нетно:       +€3 884           │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  BURN RATE
│  │ СЕДМИЧЕН BURN              q5  │   │
│  │ Харчиш €2 812/седмица          │   │
│  │ ✨ Реклама +30% този месец     │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  BREAK-EVEN
│  │ BREAK-EVEN ТОЗИ МЕСЕЦ          │   │  glass q3
│  │ Постигнат на 18 ден (от 30)    │   │  progress bar
│  │ ████████████░░░░░░             │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  CASH GAP ALERT
│  │ CASH GAP                  ⚠   │   │  glass q1
│  │ Дължиш доставчици: €4 200      │   │
│  │ Дължат ти клиенти: €1 800      │   │
│  │ Разлика: −€2 400 (опасност)    │   │
│  │ ✨ Натисни клиентите за плащане│   │
│  └─────────────────────────────────┘   │
└─────────────────────────────────────────┘
```

## 12.3 SUB-SECTION: РАЗХОДИ (Phase 8)

### Wireframe

```
┌─────────────────────────────────────────┐
│  ┌─────────────────────────────────┐   │  EXPENSES HERO
│  │ РАЗХОДИ ТОЗИ МЕСЕЦ        −18% │   │  glass q1
│  │ €4 247 / 8 305.61 лв           │   │
│  │ ✨ €820 под бюджета            │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  CATEGORIES BREAKDOWN
│  │ ПО КАТЕГОРИИ                   │   │
│  │ Заплати   ████████  €2 400 56%│   │  horizontal bars
│  │ Наем      ████      €1 200 28%│   │
│  │ Доставки  ██        €400   9%  │   │
│  │ Ток       ▌         €150   3%  │   │
│  │ Реклама   ▌         €97    2%  │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  RENT % OF REVENUE
│  │ НАЕМ КАТО %                    │   │  glass qd
│  │ 12% от оборота                  │   │
│  │ Норма: 8-15% · ОК ✓             │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  PAYROLL %
│  │ ЗАПЛАТИ КАТО %                 │   │
│  │ 24% от оборота                  │   │
│  │ Норма: 20-30% · ОК ✓            │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  BUDGET VS ACTUAL
│  │ БЮДЖЕТ vs РЕАЛНО               │   │
│  │                                 │   │
│  │ Заплати                         │   │
│  │ ████████████░  €2 400 / 2 500  │   │  -100€ under
│  │ Наем                            │   │
│  │ ████████████   €1 200 / 1 200  │   │  exact
│  │ Реклама                         │   │
│  │ ████████████████ €97 / 80      │   │  +17€ over ⚠
│  │ ...                             │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  ADD EXPENSE BUTTON
│  │ [+ Добави разход]              │   │  modal trigger
│  └─────────────────────────────────┘   │
└─────────────────────────────────────────┘
```

### Add Expense Modal

```
┌─────────────────────────────────────────┐
│ Нов разход                       [✕]  │
│                                         │
│ Сума *                                  │
│ [_____________] €                       │
│                                         │
│ Категория *                             │
│ [Заплати ▼]                             │
│                                         │
│ Дата                                    │
│ [17.05.2026 ▼]                          │
│                                         │
│ Описание                                │
│ [_______________________________]       │
│                                         │
│ Начин на плащане                        │
│ ○ Кеш  ● Превод  ○ Карта  ○ Дир. дебит │
│                                         │
│ От сметка (ако не е кеш)                │
│ [ДСК сметка ▼]                          │
│                                         │
│ Прикачи фактура [📎]                   │
│                                         │
│ □ Повтарящ се (всеки месец)            │
│                                         │
│ [Запази]  [Отказ]                      │
└─────────────────────────────────────────┘
```

## 12.4 SUB-SECTION: ДЪЛЖИМИ (Phase 8)

### Wireframe

```
┌─────────────────────────────────────────┐
│  ┌─────────────────────────────────┐   │  RECEIVABLES HERO
│  │ ДЪЛЖАТ ТИ                      │   │  glass q3
│  │ €4 820 / 9 425.18 лв            │   │
│  │ 8 клиента · 12 фактури         │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  AGING BREAKDOWN
│  │ ПО ВЪЗРАСТ                     │   │
│  │ 0-30 дни    ███████  €2 800    │   │  q3 green
│  │ 31-60 дни   ████     €1 200    │   │  q5 amber
│  │ 61-90 дни   ██       €620      │   │  q5 amber
│  │ 90+ дни     ▌        €200  ⚠   │   │  q1 red
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  OVERDUE INVOICES
│  │ ПРОСРОЧЕНИ ФАКТУРИ        ⚠   │   │  glass q1
│  │ #1042  Спорт Груп  €450  +15д  │   │  list
│  │ #1038  Marina      €620  +28д  │   │
│  │ #1031  Eko ООД     €890  +45д  │   │
│  │ [Изпрати напомняне]            │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  BIGGEST DEBTOR
│  │ НАЙ-ГОЛЯМ ДЛЪЖНИК              │   │  glass qd
│  │ Eko ООД        €1 247          │   │
│  │ 3 фактури, най-стара +45 дни   │   │
│  │ Credit limit: €1 500 (83%)     │   │
│  │ [Виж клиент]                   │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  ALL B2B CUSTOMERS
│  │ КЛИЕНТИ НА ЕДРО          [+]  │   │
│  │ Eko ООД          €1 247 ⚠     │   │
│  │ Marina           €620          │   │
│  │ Спорт Груп       €450 ⚠       │   │
│  │ ...                            │   │
│  │ [Виж всички 8 →]               │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  TAX UPCOMING
│  │ ДДС ПРЕДСТОЯЩО ПЛАЩАНЕ    ⚠  │   │
│  │ Месечно: €2 340                │   │
│  │ Падеж: 25 май (8 дни)          │   │
│  │ [Декларирай]                   │   │
│  └─────────────────────────────────┘   │
└─────────────────────────────────────────┘
```

## 12.5 SUB-SECTION: ЕКСПОРТИ (Phase 8)

### Wireframe

```
┌─────────────────────────────────────────┐
│  ┌─────────────────────────────────┐   │  QUICK EXPORT
│  │ БЪРЗ ЕКСПОРТ                   │   │
│  │ Период: [Този месец ▼]          │   │
│  │                                 │   │
│  │ Формат:                         │   │
│  │ ┌─────────┬─────────┬─────────┐│   │
│  │ │   CSV   │  Excel  │   PDF   ││   │  3 cards
│  │ │ ──────  │ ──────  │ ──────  ││   │
│  │ │ Сурови  │ Готов   │ Краен   ││   │
│  │ │ данни   │ за чет. │ репорт  ││   │
│  │ └─────────┴─────────┴─────────┘│   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  ACCOUNTANT INTEGRATION
│  │ КЪМ СЧЕТОВОДНА СОФТУЕР         │   │
│  │ ○ Microinvest  ○ Sigma  ○ Ajur │   │  radio
│  │ Email счетоводител:             │   │
│  │ [accountant@ekosch.bg]          │   │
│  │ [Изпрати]                       │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  Z-REPORT
│  │ ДНЕВЕН Z-ОТЧЕТ                 │   │
│  │ За магазин: [Цариградско ▼]    │   │
│  │ Дата: [Днес]                    │   │
│  │ [Виж] [Принтирай] [Email]       │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │  HISTORY
│  │ ИСТОРИЯ НА ЕКСПОРТИ            │   │
│  │ 15.05  CSV · Май месец          │   │
│  │        → accountant@ekosch.bg  │   │
│  │ 30.04  PDF · Април месец        │   │
│  │ 30.04  Microinvest · Април      │   │
│  │ ...                             │   │
│  └─────────────────────────────────┘   │
└─────────────────────────────────────────┘
```

---

# §13. AI INSIGHTS ENGINE — DETAILED

## 13.1 Engine architecture

```
┌──────────────────────────────────────────┐
│ stats-ai-engine.php                      │
├──────────────────────────────────────────┤
│                                          │
│ ┌──────────────────────────────────────┐ │
│ │ 1. TOPIC LOADER                      │ │
│ │ - Loads ai-topics-catalog.json       │ │
│ │ - Filters by module/role/plan        │ │
│ └──────────────┬───────────────────────┘ │
│                ▼                          │
│ ┌──────────────────────────────────────┐ │
│ │ 2. DATA AVAILABILITY CHECK           │ │
│ │ - Skip cash topics ако bank=empty    │ │
│ │ - Skip YoY ако <13 месеца            │ │
│ └──────────────┬───────────────────────┘ │
│                ▼                          │
│ ┌──────────────────────────────────────┐ │
│ │ 3. SQL FACT GATHERING                │ │
│ │ - Изпълнява queries за topic         │ │
│ │ - Записва retrieved_facts            │ │
│ └──────────────┬───────────────────────┘ │
│                ▼                          │
│ ┌──────────────────────────────────────┐ │
│ │ 4. SCORING                           │ │
│ │ - priority × recency × confidence    │ │
│ └──────────────┬───────────────────────┘ │
│                ▼                          │
│ ┌──────────────────────────────────────┐ │
│ │ 5. GEMINI PROMPT BUILDER             │ │
│ │ - Inserts retrieved_facts            │ │
│ │ - Wraps with system rules            │ │
│ └──────────────┬───────────────────────┘ │
│                ▼                          │
│ ┌──────────────────────────────────────┐ │
│ │ 6. GEMINI CALL (3s timeout)          │ │
│ │ - On success: parse rendered text    │ │
│ │ - On fail: PHP fallback              │ │
│ └──────────────┬───────────────────────┘ │
│                ▼                          │
│ ┌──────────────────────────────────────┐ │
│ │ 7. CONFIDENCE ROUTING                │ │
│ │ - >0.85: display                     │ │
│ │ - 0.5-0.85: display with marker      │ │
│ │ - <0.5: suppress                     │ │
│ └──────────────┬───────────────────────┘ │
│                ▼                          │
│ ┌──────────────────────────────────────┐ │
│ │ 8. CACHE & PERSIST                   │ │
│ │ - Cache 30 min (Redis)               │ │
│ │ - Insert ai_insights table           │ │
│ └──────────────────────────────────────┘ │
└──────────────────────────────────────────┘
```

## 13.2 Caching strategy

```php
// stats-ai-engine.php
$cache_key = "ai_insights:t={$tenant}:m={$module}:tab={$sub_tab}:p={$period}";
$cached = Redis::get($cache_key);
if ($cached) return json_decode($cached);

$insights = generateInsights($module, $sub_tab, $context);
Redis::setex($cache_key, 1800, json_encode($insights)); // 30 min
```

Cache invalidation triggers:
- Нова продажба → invalidate `m=stats:tab=overview` + `m=stats:tab=finance:sub=profit`
- Нов разход → invalidate `m=stats:tab=finance:sub=expenses` + `sub=cash`
- Нова доставка → invalidate `m=stats:tab=overview` (top products)

## 13.3 AI Insight slot rendering

```html
<div class="kpi-ai-slot" data-slot="overview-main">
  <!-- LOADING STATE -->
  <span class="ai-skeleton">...</span>

  <!-- SUCCESS STATE (confidence ≥0.85) -->
  <svg class="ai-spark"><!-- ✨ icon --></svg>
  <span class="ai-text">4-тият петък над €700 — сезонът тръгва.</span>

  <!-- MEDIUM CONFIDENCE (0.5-0.85) -->
  <svg class="ai-spark dim"><!-- ✨ icon faded --></svg>
  <span class="ai-text">
    Изглежда петъците са по-силни.
    <span class="confidence-marker" title="Все още изучавам данните">?</span>
  </span>

  <!-- FALLBACK (PHP wording, AI fail) -->
  <span class="ai-text fallback">€847 — над миналия петък</span>
</div>
```

## 13.4 Gemini prompt templates per slot

### Overview Main slot

```
<system>
Ти си AI бизнес консултант RunMyStore.
Магазин: {store_name} ({country}).
Тип: {business_type} (напр. "облекло, обувки").
Owner: {owner_name}. Тип карта: {card_type}.

ПРАВИЛА:
- БГ език. Conversational.
- Max 60 chars (1 изречение, 1 ред на 375px).
- НЕ повтаряй числа които вече са на екрана.
- Добавяй КОНТЕКСТ ("4-тият петък", "сезонът тръгва")
  или ДЕЙСТВИЕ ("поръчай повече", "намали цената").
- При confidence <0.85: добави "изглежда", "вероятно".
- Без emoji. Sparkle icon се добавя от UI.
</system>

<context>
PERIOD: {period_label}
METRICS:
  revenue_now: {revenue}€
  revenue_same_dow_last_week: {prev_dow}€
  revenue_same_dow_4w_history: [{w1}, {w2}, {w3}, {w4}]
  transactions: {tx_count} (prev: {tx_prev})
  aov: {aov}€
  margin_pct: {margin}%
  top_product: {top_name} ({top_qty}бр, {top_rev}€)
  dead_capital: {dead_value}€ in {dead_count} items
  low_stock_top: {low_name} (qty {low_qty}, days_until_zero {dtz})

ANOMALIES_DETECTED:
  {anomaly_list}

PATTERN_FLAGS:
  consecutive_same_dow_above_threshold: {n_consecutive}
  yoy_delta_pct: {yoy_pct}
  basket_trend_3w: {trend}
</context>

<task>
Генерирай ЕДНО изречение insight, max 60 chars.
Приоритет (избери ПЪРВИЯ матч):
1. CRITICAL anomaly (>3σ) — описание + action
2. PATTERN (3+ consecutive same-DOW) — pattern + outlook
3. TOP_PRODUCT outlier (3× avg) — recommendation
4. DEAD_CAPITAL trigger (>€200) — action
5. PASSIVE context (best week/month) — celebration

Output: ТЕКСТ САМО. Без quotes. Без префикси.
</task>
```

### Profit sub-tab slot

```
<system>
Stats > Финанси > Печалба taab.
Owner вижда детайлни финансови данни.
Фокус на: маржин erosion, profit divergence, discount leakage.
</system>

<context>
margin_now: {m}%
margin_30d_avg: {m_avg}%
margin_3mo_trend: [{m1}, {m2}, {m3}]
discount_rate_now: {d}%
discount_rate_norm: 5%
gross_profit_now: {gp}€
gross_profit_prev: {gp_prev}€
revenue_now: {r}€
revenue_prev: {r_prev}€

DIVERGENCE_CHECK:
  revenue_direction: {dir_r} (up/down/flat)
  profit_direction: {dir_p}
  divergence_flag: {true/false}
</context>

<task>
Генерирай ЕДНО изречение за Profit sub-tab insight.
Приоритет:
1. Divergence (revenue up + profit down) — провери защо
2. Margin pad 3+ седмици — discount check
3. Discount rate over normal — действие
4. Подсещане (positive: маржът се качва 3 поредни седмици)

Output: ТЕКСТ САМО.
</task>
```

## 13.5 Anomaly detection rules

PHP-based, без AI:

```php
// stats-anomaly-detector.php
function detectAnomalies($context) {
    $anomalies = [];

    // 1. Discount rate spike (>2× norm)
    if ($context['discount_rate_now'] > $context['discount_rate_avg_30d'] * 2) {
        $anomalies[] = [
            'severity' => 'warning',
            'type' => 'discount_spike',
            'text' => "Discount rate {$context['discount_rate_now']}% (норма {$context['discount_rate_avg_30d']}%)"
        ];
    }

    // 2. Negative stock (sold but quantity=0)
    if ($context['negative_stock_count'] > 0) {
        $anomalies[] = [
            'severity' => 'critical',
            'type' => 'negative_stock',
            'text' => "{$context['negative_stock_count']} артикула с отрицателна наличност"
        ];
    }

    // 3. Sales drop (>30% WoW same-DOW)
    if ($context['revenue_now'] < $context['revenue_same_dow_lw'] * 0.7) {
        $anomalies[] = [
            'severity' => 'warning',
            'type' => 'sales_drop',
            'text' => "Продажбите паднаха с 30%+ спрямо миналата седмица"
        ];
    }

    // 4. Cash gap (payables > receivables × 1.5)
    if (isset($context['payables'], $context['receivables'])
        && $context['payables'] > $context['receivables'] * 1.5) {
        $anomalies[] = [
            'severity' => 'critical',
            'type' => 'cash_gap',
            'text' => "Дължиш повече отколкото ти дължат — възможен cash gap"
        ];
    }

    // ... (16+ rules общо)

    return $anomalies;
}
```

## 13.6 Alert ribbon priority

Top 3 anomalies → alert ribbon. Sort:
1. severity = 'critical' (red icon)
2. severity = 'warning' (amber)
3. severity = 'info' (blue)

Max 3 показани. Tap → drawer с пълен списък.

---

# §14. VISUALIZATION LIBRARY

## 14.1 Allowed chart types на 375px

| Type | Use case | Min width | Verdict |
|---|---|---|---|
| Sparkline | Trend (7/30 дни) | 80px | ✅ ВСИЧКИ KPI карти |
| Horizontal bar | Top 5/10, categories | 200px | ✅ Default за списъци |
| Vertical bar | Hourly (12 часа), WoW | 280px | ✅ Само 7-12 bars |
| Donut/pie | 2-3 segmenta only | 150px | ⚠ Само ако ≤3 категории |
| Line chart | Margin trend | 280px | ✅ Max 2 серии |
| Heatmap 24×7 | DoW × Hour | 320px | ⚠ Само в drawer (PRO+) |
| Stacked bar | P&L breakdown | 280px | ✅ Max 3 сегмента |
| Bullet chart | Budget vs Actual | 200px | ✅ Финанси sub-tabs |
| Sankey | — | 600px+ | ❌ ЗАБРАНЕН на mobile |
| Treemap | — | 400px+ | ❌ ЗАБРАНЕН на mobile |

## 14.2 Sparkline component

**Inline SVG, no library dependency:**

```html
<svg class="sparkline" viewBox="0 0 100 30" preserveAspectRatio="none">
  <path d="M0,20 L10,18 L20,22 L30,15 L40,17 L50,12 L60,14 L70,10 L80,8 L90,12 L100,6"
        stroke="hsl(var(--hue1) 60% 55%)"
        stroke-width="1.5"
        fill="none"/>
  <circle cx="100" cy="6" r="2" fill="hsl(var(--hue1) 60% 55%)"/>
</svg>
```

**PHP generator:**
```php
function renderSparkline(array $values, string $hue = 'hue1'): string {
    $max = max($values) ?: 1;
    $min = min($values);
    $range = $max - $min ?: 1;
    $points = [];
    foreach ($values as $i => $v) {
        $x = $i * (100 / (count($values) - 1));
        $y = 30 - (($v - $min) / $range) * 30;
        $points[] = "{$x},{$y}";
    }
    $path = 'M' . implode(' L', $points);
    return "<svg class='sparkline' viewBox='0 0 100 30' preserveAspectRatio='none'>
              <path d='{$path}' stroke='hsl(var(--{$hue}) 60% 55%)' stroke-width='1.5' fill='none'/>
            </svg>";
}
```

## 14.3 Horizontal bar list

```html
<ul class="hbar-list">
  <li class="hbar-item">
    <span class="hbar-label">Nike Air 42</span>
    <span class="hbar-bar" style="--pct: 100%"></span>
    <span class="hbar-value">4 бр</span>
  </li>
  <!-- ... -->
</ul>
```

```css
.hbar-list { list-style: none; padding: 0; margin: 0; }
.hbar-item {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 8px;
  padding: 6px 0;
  position: relative;
}
.hbar-label { font-size: 13px; font-weight: 600; }
.hbar-value { font-size: 12px; font-weight: 700; font-family: var(--font-mono); }
.hbar-bar {
  grid-column: 1 / -1;
  height: 4px;
  width: var(--pct);
  background: linear-gradient(90deg, hsl(var(--hue1) 60% 55%), hsl(var(--hue2) 60% 60%));
  border-radius: var(--radius-pill);
  margin-top: 2px;
}
```

## 14.4 Bullet chart (Budget vs Actual)

```html
<div class="bullet-chart">
  <div class="bullet-label">Реклама</div>
  <div class="bullet-track">
    <div class="bullet-target" style="--target: 80%"></div>
    <div class="bullet-actual" style="--actual: 121%"></div>
  </div>
  <div class="bullet-values">€97 / €80 (+21% над бюджета)</div>
</div>
```

```css
.bullet-track {
  position: relative;
  height: 10px;
  background: var(--surface-2);
  border-radius: var(--radius-pill);
  overflow: hidden;
}
.bullet-target {
  position: absolute;
  height: 100%;
  width: var(--target);
  background: hsl(var(--hue1) 30% 40% / 0.5);
}
.bullet-actual {
  position: absolute;
  height: 100%;
  width: var(--actual);
  background: linear-gradient(90deg, hsl(var(--hue1) 60% 55%), hsl(var(--hue2) 60% 60%));
}
/* if actual > target → over budget red overlay */
.bullet-actual.over {
  background: linear-gradient(90deg, hsl(0 60% 55%), hsl(15 60% 60%));
}
```

## 14.5 Stacked bar (P&L)

```html
<div class="stacked-bar">
  <div class="sb-segment sb-revenue" style="--w: 100%">
    <span>Оборот €15K</span>
  </div>
  <div class="sb-segment sb-cogs" style="--w: 72%">
    <span>COGS −€10.9K</span>
  </div>
  <div class="sb-segment sb-profit" style="--w: 28%">
    <span>Печалба €4.2K</span>
  </div>
</div>
```

## 14.6 Hourly bars 12-segment

Pure CSS, no SVG:

```html
<div class="hourly-bars-12">
  <?php foreach ($hours as $h): ?>
    <div class="hb-item" style="--h: <?= $h['pct'] ?>%; --hour: '<?= $h['hour'] ?>'">
      <div class="hb-fill"></div>
    </div>
  <?php endforeach; ?>
</div>
```

```css
.hourly-bars-12 {
  display: flex;
  align-items: flex-end;
  gap: 4px;
  height: 80px;
  padding-bottom: 18px;
}
.hb-item {
  flex: 1;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  position: relative;
}
.hb-fill {
  height: var(--h);
  background: linear-gradient(to top, hsl(var(--hue1) 60% 45%), hsl(var(--hue1) 60% 65%));
  border-radius: 2px 2px 0 0;
  min-height: 4px;
}
.hb-item::after {
  content: var(--hour);
  position: absolute;
  bottom: -16px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 9px;
  color: var(--text-muted);
}
```

## 14.7 Heatmap 24×7 (PRO+, само в drawer)

Само на drill-down drawer, не на главния view. Cells 12×8px:

```css
.heatmap {
  display: grid;
  grid-template-columns: 32px repeat(24, 12px);
  grid-template-rows: 16px repeat(7, 8px);
  gap: 1px;
}
.heatmap-cell[data-intensity="0"] { background: hsl(var(--hue1) 20% 12%); }
.heatmap-cell[data-intensity="1"] { background: hsl(var(--hue1) 30% 25%); }
.heatmap-cell[data-intensity="2"] { background: hsl(var(--hue1) 40% 40%); }
.heatmap-cell[data-intensity="3"] { background: hsl(var(--hue1) 60% 55%); }
.heatmap-cell[data-intensity="4"] { background: hsl(var(--hue1) 70% 65%); }
```

## 14.8 Color usage rules

**Hue mapping per контекст:**
- `qd` (255/222 indigo default) — neutral data, revenue, transactions
- `q1` (0/15 red) — loss, dead stock, anomalies, cash gap
- `q2/qm` (280/305 violet) — AI magic insights, predictions
- `q3` (145/165 green) — profit, margin, positive growth, paid invoices
- `q4` (180/195 cyan) — secondary data, hourly, time-based
- `q5` (38/28 amber) — alerts, warnings, low stock, budgets

**Dynamic hue assignment:**
```php
function chooseHue($delta_pct, $context = 'revenue') {
    if ($context === 'loss' || $delta_pct < -10) return 'q1';
    if ($context === 'profit' || $delta_pct > 10) return 'q3';
    if ($context === 'alert' || $delta_pct < 0) return 'q5';
    return 'qd';
}
```

---

# §15. SACRED NEON GLASS — ПРИЛОЖЕНИЕ В STATS

## 15.1 Mandatory 4 spans

**ВСЯКА** glass карта в stats модула има точно 4 spans:

```html
<div class="glass [hue-class] [variant]">
  <span class="shine"></span>          <!-- top shine -->
  <span class="shine shine-bottom"></span>  <!-- bottom shine -->
  <span class="glow"></span>            <!-- top glow -->
  <span class="glow glow-bottom"></span>    <!-- bottom glow -->

  <!-- content -->
</div>
```

**ЗАБРАНЕНО:**
- `overflow: hidden` на `.glass` (изрязва shine span-овете)
- Single shine span (без bottom)
- Без `.glow` (карта без outer glow = "грозно")

## 15.2 Light vs Dark mode

В light mode — spans `display: none`:
```css
[data-theme="light"] .glass .shine,
[data-theme="light"] .glass .glow {
    display: none;
}
[data-theme="light"] .glass {
    background: var(--surface);
    box-shadow: var(--shadow-card);
}
```

В dark mode — spans visible:
```css
[data-theme="dark"] .glass {
    background: hsl(220 25% 8% / 0.5);
    backdrop-filter: blur(12px);
    border: 1px solid hsl(var(--hue1) 30% 30% / 0.2);
}
[data-theme="dark"] .glass .shine { /* gradient overlay */ }
[data-theme="dark"] .glass .glow {
    /* outer box-shadow effect via pseudo */
}
```

## 15.3 Variants

```css
.glass.sm    { padding: 12px 14px; }
.glass.md    { padding: 16px 18px 14px; }  /* default */
.glass.lg    { padding: 20px 22px 18px; }
```

## 15.4 Aurora background

`stats.php` (както life-board.php) има aurora blobs:

```html
<div class="aurora">
  <div class="aurora-blob aurora-blob-1"></div>
  <div class="aurora-blob aurora-blob-2"></div>
  <div class="aurora-blob aurora-blob-3"></div>
  <div class="aurora-blob aurora-blob-4"></div>
</div>
```

```css
.aurora { position: fixed; inset: 0; z-index: -1; pointer-events: none; }
.aurora-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.45;
    mix-blend-mode: screen;
}
.aurora-blob-1 { /* top-left, hue1 */ }
.aurora-blob-2 { /* top-right, hue2 */ }
.aurora-blob-3 { /* bottom-left, hue3 */ }
.aurora-blob-4 { /* bottom-right, hue1 */ }
```

В light mode — opacity намалява до 0.15.

## 15.5 Sacred CSS блок (копира от wizard_v6_INTERACTIVE.html редове 108-133)

```css
.glass {
    position: relative;
    background: var(--surface);
    border-radius: var(--radius-card);
    overflow: visible;  /* NEVER hidden */
}

.glass .shine {
    position: absolute;
    top: -1px; left: -1px; right: -1px;
    height: 50%;
    background: linear-gradient(to bottom,
        hsl(var(--hue1) 100% 80% / 0.3),
        transparent);
    border-radius: var(--radius-card) var(--radius-card) 0 0;
    pointer-events: none;
}

.glass .shine.shine-bottom {
    top: auto;
    bottom: -1px;
    background: linear-gradient(to top,
        hsl(var(--hue2) 100% 70% / 0.2),
        transparent);
    border-radius: 0 0 var(--radius-card) var(--radius-card);
}

.glass .glow {
    position: absolute;
    top: -8px; left: 10%; right: 10%;
    height: 40px;
    background: hsl(var(--hue1) 80% 60% / 0.4);
    filter: blur(20px);
    pointer-events: none;
    z-index: -1;
}

.glass .glow.glow-bottom {
    top: auto;
    bottom: -8px;
    background: hsl(var(--hue2) 80% 60% / 0.4);
}
```

**Това е свещен блок** — никога не се променя, копира се 1:1.

---

# §16. RBAC MATRIX — ПЪЛНА ТАБЛИЦА

## 16.1 По таб

| Достъп | OWNER 👑 | MANAGER 🔑 | SELLER 💼 |
|---|---|---|---|
| Tab "Преглед" | ✅ Full | ✅ Без profit карта | ❌ Redirect |
| Tab "Артикули" | ✅ Full | ✅ Без profit колоната | ❌ Redirect |
| Tab "Финанси" | ✅ Full (5 sub) | ❌ Hidden | ❌ Hidden |

## 16.2 По метрика

| Метрика | OWNER | MANAGER | SELLER |
|---|---|---|---|
| Revenue | ✅ | ✅ | — |
| Transactions | ✅ | ✅ | — |
| AOV | ✅ | ✅ | — |
| Top 5 by units | ✅ | ✅ | — |
| Top 5 by revenue | ✅ | ✅ | — |
| Top 5 by profit | ✅ | ❌ | — |
| Gross Profit | ✅ | ❌ | — |
| Margin % | ✅ | ❌ | — |
| Dead Stock value (€) | ✅ | ⚠ count only | — |
| Low Stock | ✅ | ✅ | — |
| Categories (revenue) | ✅ | ✅ | — |
| Categories (profit) | ✅ | ❌ | — |
| Discount rate aggregate | ✅ | ✅ | — |
| Discount per seller | ✅ | ❌ | — |
| Sales by hour | ✅ | ✅ | — |
| WoW comparison | ✅ | ✅ | — |
| Seller performance (revenue) | ✅ | ❌ | — |
| Seller performance (profit) | ✅ | ❌ | — |
| Returns rate | ✅ | ✅ | — |
| Basket size | ✅ | ✅ | — |
| Cross-store comparison | ✅ | ❌ | — |
| ABC Analysis | ✅ | ✅ | — |
| YoY / Seasonal | ✅ | ❌ | — |
| **FINANCE: Cash flow** | ✅ | ❌ | — |
| **FINANCE: Expenses** | ✅ | ❌ | — |
| **FINANCE: Receivables** | ✅ | ❌ | — |
| **FINANCE: Bank accounts** | ✅ | ❌ | — |
| **FINANCE: Tax payments** | ✅ | ❌ | — |
| **FINANCE: Exports** | ✅ | ❌ | — |

## 16.3 Имплементация

### CSS class на body

```html
<body class="mode-detailed role-<?= $role ?> plan-<?= $plan ?>">
```

```css
body.role-manager .owner-only { display: none !important; }
body.role-seller .owner-only,
body.role-seller .manager-up { display: none !important; }
```

### Server-side guard

```php
// stats.php при request за финанси
if ($tab === 'finance' && $role !== 'owner') {
    header('Location: stats.php?tab=overview');
    exit;
}

// Sub-tab guards
if ($sub === 'profit' && !in_array($role, ['owner'])) {
    header('Location: stats.php?tab=finance');
    exit;
}
```

### SQL guards

Margin/profit columns в SQL — only за owner:

```php
$profit_select = $role === 'owner'
    ? "SUM(si.quantity * (si.unit_price - COALESCE(si.cost_at_sale,0))) AS profit,"
    : "NULL AS profit,";
```

## 16.4 Seller redirect

```php
if ($role === 'seller') {
    header('Location: life-board.php');
    exit;
}
```

Bottom nav за seller има **stats таб скрит**:
```php
if ($role === 'seller') {
    // bottom-nav.php
    // skip stats tab entirely
}
```

---

# §17. PLAN GATING — UI SIGNALING

## 17.1 Plan tier signaling

**FREE** карти — пълен достъп, но **сравнения** не работят:
```html
<div class="kpi-mini">
  <div class="kpi-mini-label">СРЕДЕН ЧЕК</div>
  <div class="kpi-mini-value">121 €</div>
  <div class="kpi-mini-delta locked">
    <svg class="lock-icon"><!-- lock --></svg>
    Сравнения в START
  </div>
</div>
```

**Locked state** styling:
```css
.kpi-mini-delta.locked {
  background: var(--surface-2);
  color: var(--text-muted);
  font-size: 10px;
  padding: 2px 6px;
  border-radius: var(--radius-pill);
  cursor: pointer;
}
.lock-icon {
  width: 10px; height: 10px;
  display: inline-block;
  margin-right: 2px;
  vertical-align: -1px;
}
```

Tap на locked → upgrade модал (виж §17.4).

## 17.2 Plan-gated cards

Цели карти заключени за по-долни планове:

```html
<div class="glass kpi-card locked" data-required-plan="pro">
  <div class="lock-overlay">
    <svg class="lock-icon-lg"><!-- big lock --></svg>
    <div class="lock-title">Hourly Sales</div>
    <div class="lock-msg">Налично в PRO</div>
    <button class="lock-cta">Разгледай PRO</button>
  </div>
  <!-- preview content blurred -->
  <div class="lock-preview" style="filter: blur(8px); opacity: 0.3;">
    [content]
  </div>
</div>
```

## 17.3 Plan badges в navigation

Sub-tab "Финанси" винаги има 👑 badge ако owner-only:

```html
<button class="tab-btn" data-tab="finance">
  Финанси
  <?php if ($role !== 'owner'): ?>
    <span class="plan-badge owner-only">👑</span>
  <?php endif; ?>
</button>
```

Финанси sub-tabs с Phase 8 features:
```html
<button class="sub-tab-btn locked" data-sub="cashflow" data-phase="8">
  Cash
  <span class="phase-badge">Скоро</span>
</button>
```

## 17.4 Upgrade modal

Tap на locked → bottom sheet:

```
┌─────────────────────────────────────────┐
│ ▬▬▬▬                                  │
│ Тази функция е в PRO            [✕]   │
│                                         │
│ С PRO ($49/мес) получаваш:             │
│                                         │
│ ✓ Печалба и марж по артикул            │
│ ✓ Категории breakdown                  │
│ ✓ Sales by hour                        │
│ ✓ Seller performance                   │
│ ✓ AI insights advanced                 │
│ ✓ Exports (CSV, PDF, Excel)            │
│ ✓ + още 12 функции                     │
│                                         │
│ [Активирай PRO]  [По-късно]            │
│                                         │
│ Кратък filemoun е, отказваш по         │
│ всяко време.                            │
└─────────────────────────────────────────┘
```

## 17.5 Plan logic в PHP

```php
// lib/plan-gate.php
function isAllowed(string $feature, string $plan): bool {
    $matrix = [
        'revenue_basic'        => ['free', 'start', 'pro', 'business'],
        'revenue_comparisons'  => ['start', 'pro', 'business'],
        'revenue_yoy'          => ['pro', 'business'],
        'margin_profit'        => ['pro', 'business'],  // also requires role=owner
        'hourly_sales'         => ['pro', 'business'],
        'seller_performance'   => ['pro', 'business'],
        'category_breakdown'   => ['pro', 'business'],
        'cross_store'          => ['business'],
        'finance_full'         => ['pro', 'business'],
        'stock_turnover'       => ['business'],
        'gmroi'                => ['business'],
        'supplier_performance' => ['business'],
        'abc_analysis'         => ['business'],
        'forecasting'          => ['business'],
        'email_scheduling'     => ['business'],
        'exports_csv'          => ['start', 'pro', 'business'],
        'exports_pdf'          => ['pro', 'business'],
        'exports_microinvest'  => ['pro', 'business'],
    ];
    return isset($matrix[$feature]) && in_array($plan, $matrix[$feature]);
}
```

Usage:
```php
<?php if (isAllowed('margin_profit', $plan) && $role === 'owner'): ?>
    <div class="glass kpi-mini q3">
      <div class="kpi-mini-label">МАРЖ</div>
      <div class="kpi-mini-value"><?= $margin ?>%</div>
    </div>
<?php elseif ($role === 'owner'): ?>
    <div class="glass kpi-mini locked" data-required-plan="pro">
      <!-- locked state -->
    </div>
<?php endif; ?>
```

## 17.6 Free tier "teaser" pattern

FREE plan вижда **brой dead capital артикули но не €**:

```php
if ($plan === 'free') {
    echo "5 артикула не са се продавали 90+ дни";
    echo '<a class="upgrade-link">Виж колко пари са замразени → START</a>';
} else {
    echo "€340 в 5 артикула замразени";
}
```

## 17.7 Plan settings overview

| Метрика | FREE | START | PRO | BUSINESS |
|---|---|---|---|---|
| Revenue (today) | ✓ | ✓ | ✓ | ✓ |
| Revenue + comparisons | — | ✓ 7д | ✓ 30/90д | ✓ + YoY |
| Top 5 (units) | ✓ | ✓ | ✓ | ✓ |
| Top 5 (revenue) | — | ✓ | ✓ | ✓ |
| Top 5 (profit) | — | — | ✓ owner | ✓ owner |
| Margin/Profit | — | — | ✓ owner | ✓ owner |
| Dead Stock (count) | ✓ | ✓ | ✓ | ✓ |
| Dead Stock (€) | — | ✓ | ✓ | ✓ |
| Low Stock | ✓ | ✓ | ✓ | ✓ |
| Categories | — | — | ✓ | ✓ |
| Sales by hour | — | — | ✓ | ✓ |
| Seller performance | — | — | ✓ | ✓ |
| Discount tracking | — | — | ✓ | ✓ |
| WoW comparison | — | ✓ | ✓ | ✓ |
| Cross-store | — | — | — | ✓ |
| Stock Turnover/GMROI | — | — | — | ✓ |
| Supplier Performance | — | — | — | ✓ |
| ABC Analysis | — | — | — | ✓ |
| **FINANCE: Profit sub-tab** | — | — | ✓ | ✓ |
| **FINANCE: Cash flow** | — | — | — | ✓ |
| **FINANCE: Expenses** | — | — | — | ✓ |
| **FINANCE: Receivables** | — | — | — | ✓ |
| **FINANCE: Bank accounts** | — | — | — | ✓ |
| **FINANCE: Tax tracking** | — | — | — | ✓ |
| **FINANCE: B2B Invoices** | — | — | ✓ basic | ✓ full |
| Exports (CSV)| — | ✓ 7д | ✓ 90д | ✓ unlimited |
| Exports (PDF) | — | — | ✓ месечен | ✓ + Z-report |
| Exports (Microinvest/Sigma/Ajur) | — | — | ✓ | ✓ |
| Email scheduling | — | — | — | ✓ |
| AI insights (Stats) | ⚠ 3/мес | ✓ 10/мес | ✓ unlimited | ✓ + custom |

---

# 🏁 КРАЙ НА ETAP 3

**Покрити секции:** §10-§17

**Какво е готово:**
- Tab 1 Преглед — пълен wireframe + 11 компонента + drill-down drawer + empty/loading/error states
- Tab 2 Артикули — wireframe + product detail drawer + filter pills + action handlers
- Tab 3 Финанси — 5 sub-секции (12.1-12.5) с пълни wireframes + Add Expense modal
- AI Insights Engine — пълна архитектура с 8 stages + caching + prompt templates per slot + anomaly detection rules
- Visualization library — 10 chart типа с CSS + PHP generators
- Sacred Neon Glass — приложение в stats модула + Aurora background
- RBAC Matrix — пълна таблица per метрика (28 редa) + CSS/SQL/PHP imp guards
- Plan Gating UI — locked states + upgrade modal + teaser pattern + пълна матрица 27 функции × 4 плана

**Следваща стъпка (ETAP 4):** §18-§27 — Exports (Microinvest/Sigma/Ajur) + s82-dash интеграция + двойна валута + caching strategy + audit trail + migration plan от текущия stats.php + roadmap.

**Чакам approval за ETAP 4.**

═══════════════════════════════════════════════════════════════
# ETAP 4 — EXPORTS, S82, MIGRATION, ROADMAP §18-§27
═══════════════════════════════════════════════════════════════

# §18. EXPORTS — БАЗОВИ ФОРМАТИ

## 18.1 Export endpoint architecture

```
GET /stats-export.php?format={fmt}&type={type}&period={period}&from={d}&to={d}
```

**Параметри:**
- `format`: csv | excel | pdf | microinvest | sigma | ajur
- `type`: sales | expenses | invoices | full_period | z_report
- `period`: today | week | month | quarter | year | custom
- `from`, `to`: за custom period

**Flow:**
```
1. Validate role + plan + feature gate
2. Build query (sales/expenses/etc.)
3. Generate file in /tmp/exports/{tenant}/
4. Insert accountant_exports record (status='generating')
5. Stream file to client с Content-Disposition: attachment
6. Update status='ready' + file_path
7. Optionally email if sent_to_email param provided
```

## 18.2 CSV — Sales export

**Filename:** `sales_{tenant_name}_{from}_{to}.csv`

**Header row (UTF-8 BOM + ; delimiter за БГ Excel):**
```csv
Дата;Час;Бон №;Магазин;Продавач;Артикул код;Артикул име;Категория;Бр;Ед.цена;Отстъпка;Тотал;ДДС;Плащане;Клиент EIK;Клиент име
```

**SQL за заявката:**
```sql
SELECT
    DATE(s.created_at) AS date,
    TIME(s.created_at) AS time,
    s.id AS receipt_no,
    st.name AS store,
    u.name AS seller,
    p.code AS product_code,
    p.name AS product_name,
    c.name AS category,
    si.quantity AS qty,
    si.unit_price AS unit_price,
    si.discount_pct AS discount,
    si.line_total AS line_total,
    si.line_total * 0.1667 AS vat_amount,
    s.payment_method AS payment,
    COALESCE(bc.eik, '') AS customer_eik,
    COALESCE(bc.name, '') AS customer_name
FROM sales s
JOIN sale_items si ON si.sale_id = s.id
JOIN products p ON p.id = si.product_id
LEFT JOIN categories c ON c.id = p.category_id
JOIN stores st ON st.id = s.store_id
JOIN users u ON u.id = s.user_id
LEFT JOIN b2b_customers bc ON bc.id = s.customer_id
WHERE s.tenant_id = :tenant_id
  AND s.status = 'completed'
  AND s.created_at BETWEEN :from AND :to
ORDER BY s.created_at, s.id, si.id;
```

**PHP generator (streaming за големи datasets):**

```php
// stats-export.php :: exportSalesCSV($tenant_id, $from, $to)
function exportSalesCSV($tenant_id, $from, $to) {
    $filename = "sales_{$tenant_id}_{$from}_{$to}.csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename={$filename}");
    
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
    
    // Header
    fputcsv($out, [
        'Дата','Час','Бон №','Магазин','Продавач',
        'Артикул код','Артикул име','Категория',
        'Бр','Ед.цена','Отстъпка','Тотал','ДДС',
        'Плащане','Клиент EIK','Клиент име'
    ], ';');
    
    // Stream rows
    $stmt = DB::query($sql, [':tenant_id' => $tenant_id, ':from' => $from, ':to' => $to]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $row, ';');
    }
    
    fclose($out);
}
```

## 18.3 Excel — Monthly Summary

**Filename:** `summary_{tenant_name}_{year}_{month}.xlsx`

**Sheets:**
1. **Преглед** — KPI summary (revenue, profit, margin, transactions, AOV)
2. **Продажби по дни** — daily breakdown
3. **Топ артикули** — top 50 by revenue
4. **Категории** — category breakdown
5. **Продавачи** — performance per seller (owner only)
6. **ДДС** — VAT breakdown по ставки

**Library:** PhpSpreadsheet

```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function exportMonthlyExcel($tenant_id, $year, $month) {
    $spreadsheet = new Spreadsheet();
    
    // Sheet 1: Преглед
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Преглед');
    $sheet->setCellValue('A1', 'KPI');
    $sheet->setCellValue('B1', 'Стойност');
    
    $kpis = getMonthlyKPIs($tenant_id, $year, $month);
    $row = 2;
    foreach ($kpis as $name => $value) {
        $sheet->setCellValue("A{$row}", $name);
        $sheet->setCellValue("B{$row}", $value);
        $row++;
    }
    
    // ... другите sheets
    
    $writer = new Xlsx($spreadsheet);
    $filename = "summary_{$tenant_id}_{$year}_{$month}.xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename={$filename}");
    $writer->save('php://output');
}
```

## 18.4 PDF — Monthly Executive Summary

**Filename:** `report_{tenant_name}_{year}_{month}.pdf`

**Structure (1 страница A4 portrait):**

```
┌────────────────────────────────────────────────────┐
│  RunMyStore.AI                          ENI ООД    │
│                                       Май 2026     │
├────────────────────────────────────────────────────┤
│  MONTHLY EXECUTIVE SUMMARY                         │
│                                                    │
│  ┌──────────────┐  ┌──────────────┐                │
│  │ ОБОРОТ       │  │ ПЕЧАЛБА      │                │
│  │ €15 132      │  │ €4 237       │                │
│  │ +18% YoY     │  │ Марж 28%     │                │
│  └──────────────┘  └──────────────┘                │
│                                                    │
│  ┌──────────────┐  ┌──────────────┐                │
│  │ ТРАНЗАКЦИИ   │  │ СРЕДЕН ЧЕК   │                │
│  │ 124          │  │ €122         │                │
│  └──────────────┘  └──────────────┘                │
│                                                    │
│  ТРЕНД 30 ДНИ:                                    │
│  [sparkline chart]                                 │
│                                                    │
│  ТОП 5 АРТИКУЛА:                                  │
│  1. Nike Air Max 42 ........... €1 247 (24 бр)    │
│  2. Levi's 501 ............... €890 (18 бр)       │
│  3. ...                                           │
│                                                    │
│  ТОП 3 КАТЕГОРИИ:                                 │
│  • Облекло    62%  €9 382                         │
│  • Обувки     28%  €4 237                         │
│  • Аксесоари   8%  €1 213                         │
│                                                    │
│  ЗАМРАЗЕНИ ПАРИ: €340 в 5 артикула                │
│                                                    │
│  AI ОБОБЩЕНИЕ:                                    │
│  Май беше най-силният месец от началото на       │
│  годината. Маржът се запази на 28% въпреки       │
│  ръста в обема на отстъпки до 7.4%.              │
│                                                    │
│  Препоръка: следи Adidas 38 (120 дни без          │
│  продажба, €87 замразени).                        │
│                                                    │
├────────────────────────────────────────────────────┤
│  Генериран: 17.05.2026 09:32                      │
│                       RunMyStore.AI · runmystore.bg│
└────────────────────────────────────────────────────┘
```

**Library:** dompdf или mPDF

```php
use Dompdf\Dompdf;

function exportMonthlyPDF($tenant_id, $year, $month) {
    $data = getMonthlySummaryData($tenant_id, $year, $month);
    $html = renderTemplate('templates/monthly_report.html.twig', $data);
    
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $filename = "report_{$tenant_id}_{$year}_{$month}.pdf";
    header('Content-Type: application/pdf');
    header("Content-Disposition: attachment; filename={$filename}");
    echo $dompdf->output();
}
```

## 18.5 Z-Report — Daily Cash Report

**Format:** Thermal printer 58mm/80mm ESC/POS OR PDF A4.

**Z-report content (legal requirement БГ):**

```
================================
     RunMyStore ENI ООД
     ЦК "Цариградско"
     ЕИК: 123456789
================================
ДНЕВЕН Z-ОТЧЕТ
17.05.2026  20:14:00
================================
Брой касови бележки:      24
Анулирани:                 1
--------------------------------
Брой плащане:
  В брой:           €847.00
  С карта:          €312.50
  Превод:           €0.00
  Общо:           €1 159.50
--------------------------------
ДДС:
  20% база:         €966.25
  20% ДДС:          €193.25
  9% база:           €0.00
  9% ДДС:            €0.00
  Общо ДДС:         €193.25
--------------------------------
Отстъпки дадени:    €87.50
--------------------------------
Каса начало:        €120.00
+ продажби кеш:     €847.00
- разходи:          €0.00
- внесено в банка:  €0.00
= Каса край:        €967.00
================================
Касиер: Пешо Петров
Z-отчет № 1247
================================
```

**Generation logic:**
```php
function generateZReport($store_id, $date) {
    // 1. Aggregate sales for date
    $data = aggregateDailySales($store_id, $date);
    
    // 2. Insert z_reports record
    $report_id = DB::insert('z_reports', [...]);
    
    // 3. Return printable format
    return renderZReport($data, $report_id);
}
```

**Print to thermal printer (Bluetooth):**
```javascript
// frontend
async function printZReport(reportId) {
    const text = await fetch(`/z-report.php?id=${reportId}&format=tspl`).then(r => r.text());
    // capacitor-printer.js
    await ThermalPrinter.printText(text);
}
```

## 18.6 Print-friendly view (media: print)

За на-екран print без PDF generation:

```css
@media print {
    body {
        background: white !important;
        color: black !important;
    }
    .aurora, .glow, .shine, .bottom-nav, .rms-header, .rms-subbar,
    .tab-bar, .alert-ribbon, .ai-spark, button {
        display: none !important;
    }
    .glass {
        background: white !important;
        box-shadow: none !important;
        border: 1px solid #ccc !important;
    }
    .kpi-num, .kpi-mini-value {
        color: black !important;
    }
    .page-break-before {
        page-break-before: always;
    }
}
```

Бутон в Toolbar:
```html
<button onclick="window.print()">🖨 Печат</button>
```

## 18.7 Email scheduling (BUSINESS only)

Конфигурация в settings:
```
Месечен отчет:
  □ Изпращай PDF на 1-ви всеки месец
  Получател: [accountant@xyz.bg]
  Включи:
  ☑ Sales summary
  ☑ Margin breakdown
  ☑ Top products
  ☐ Detailed transactions CSV
```

Cron job (1-ви ден на месеца, 06:00):
```php
// cron/monthly-report-cron.php
foreach (getTenantsWithEmailScheduling() as $tenant) {
    $pdf = exportMonthlyPDF($tenant->id, $last_month_year, $last_month);
    sendEmail($tenant->report_email, "Отчет за {$last_month}/{$last_month_year}", $pdf);
    insertAccountantExportLog($tenant->id, 'pdf', 'sent', $tenant->report_email);
}
```

---

# §19. EXPORT КЪМ СЧЕТОВОДНИ СОФТУЕРИ

БГ счетоводители ползват предимно: **Microinvest, Sigma, Ajur**. Всеки има свой CSV формат.

## 19.1 Microinvest format

**Format:** CSV; разделител `;`; UTF-8 BOM; CRLF line endings.

**Headers (Microinvest Sales Import):**
```csv
Номер;Дата;Час;Партньор;ЕИК;ДДС №;Тип документ;Стока;Мярка;Количество;Ед.цена без ДДС;Ед.цена с ДДС;% ДДС;Сума без ДДС;Сума ДДС;Сума с ДДС;Плащане;Магазин
```

**SQL + transform:**
```php
function exportMicroinvest($tenant_id, $from, $to) {
    $sql = "SELECT s.id, DATE(s.created_at) d, TIME(s.created_at) t,
                   COALESCE(bc.name, 'Краен потребител') partner,
                   COALESCE(bc.eik, '') eik,
                   COALESCE(bc.vat_number, '') vat_num,
                   IF(s.invoice_id, 'фактура', 'бон') doc_type,
                   p.name product, 'бр' unit, si.quantity qty,
                   si.unit_price / 1.20 price_no_vat,
                   si.unit_price price_with_vat,
                   20.00 vat_pct,
                   si.line_total / 1.20 sum_no_vat,
                   si.line_total - si.line_total / 1.20 sum_vat,
                   si.line_total sum_with_vat,
                   s.payment_method payment,
                   st.name store
            FROM sales s
            JOIN sale_items si ON si.sale_id = s.id
            JOIN products p ON p.id = si.product_id
            JOIN stores st ON st.id = s.store_id
            LEFT JOIN b2b_customers bc ON bc.id = s.customer_id
            WHERE s.tenant_id = :t AND s.status = 'completed'
              AND s.created_at BETWEEN :from AND :to";
    
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    
    fputcsv($out, [
        'Номер','Дата','Час','Партньор','ЕИК','ДДС №','Тип документ',
        'Стока','Мярка','Количество','Ед.цена без ДДС','Ед.цена с ДДС',
        '% ДДС','Сума без ДДС','Сума ДДС','Сума с ДДС','Плащане','Магазин'
    ], ';');
    
    foreach (DB::query($sql, [...]) as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
}
```

## 19.2 Sigma format

**Different schema:** Sigma ползва XML или Excel template.

**Sigma Excel Sales Import (.xlsx):**
- Sheet 1: "Продажби"
- Columns: Дата, Документ №, Контрагент, ЕИК, Сума без ДДС, ДДС, Сума с ДДС, Магазин, Касиер
- Cell А1 е fixed header

```php
function exportSigma($tenant_id, $from, $to) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Продажби');
    
    // Headers
    $headers = ['Дата','Документ №','Контрагент','ЕИК',
                'Сума без ДДС','ДДС','Сума с ДДС','Магазин','Касиер'];
    foreach ($headers as $col => $h) {
        $sheet->setCellValue(chr(65 + $col) . '1', $h);
    }
    
    // Data rows
    $row = 2;
    foreach (getSalesAggregated($tenant_id, $from, $to) as $sale) {
        $sheet->fromArray([
            $sale['date'],
            $sale['doc_no'],
            $sale['customer'] ?: 'Краен потребител',
            $sale['eik'] ?: '',
            $sale['sum_no_vat'],
            $sale['vat'],
            $sale['sum_with_vat'],
            $sale['store'],
            $sale['cashier']
        ], null, "A{$row}");
        $row++;
    }
    
    $writer = new Xlsx($spreadsheet);
    $filename = "sigma_{$tenant_id}_{$from}_{$to}.xlsx";
    header('Content-Disposition: attachment; filename=' . $filename);
    $writer->save('php://output');
}
```

## 19.3 Ajur format

**Format:** CSV `,` separated, UTF-8 без BOM, LF endings.

**Headers:**
```csv
date,doc_number,partner_name,partner_id,partner_vat,doc_type,total,vat_total,store_id,user_id
```

**Aggregated** (1 row per sale, not per item):
```sql
SELECT DATE(s.created_at) date,
       s.id doc_number,
       COALESCE(bc.name, '') partner_name,
       COALESCE(bc.eik, '') partner_id,
       COALESCE(bc.vat_number, '') partner_vat,
       IF(s.invoice_id, 'INV', 'RCP') doc_type,
       s.total,
       s.vat_amount,
       s.store_id,
       s.user_id
FROM sales s
LEFT JOIN b2b_customers bc ON bc.id = s.customer_id
WHERE s.tenant_id = :t AND s.status = 'completed'
  AND s.created_at BETWEEN :from AND :to
ORDER BY s.created_at;
```

## 19.4 Export profile UI

```
┌─────────────────────────────────────────┐
│ КЪМ СЧЕТОВОДЕН СОФТУЕР                 │
│                                         │
│ ○ Microinvest   ● Sigma   ○ Ajur       │  radio
│ ○ Stock CSV     ○ XLSX                  │
│                                         │
│ Период:                                 │
│ [Този месец ▼]                          │
│                                         │
│ Включи:                                 │
│ ☑ Продажби                              │
│ ☑ Фактури B2B                           │
│ ☐ Връщания                              │
│ ☐ Сторно операции                       │
│                                         │
│ Получател:                              │
│ [accountant@xyz.bg________________]     │
│                                         │
│ [Изпрати]  [Изтегли локално]            │
└─────────────────────────────────────────┘
```

## 19.5 История на експортите

`accountant_exports` таблица (M-015) запазва audit trail:

```sql
SELECT * FROM accountant_exports
WHERE tenant_id = :t
ORDER BY generated_at DESC
LIMIT 30;
```

UI:
```html
<table class="exports-history">
<tr><th>Дата</th><th>Формат</th><th>Период</th><th>Статус</th><th>Получател</th></tr>
<tr>
  <td>15.05 14:32</td>
  <td>Microinvest CSV</td>
  <td>Апр 2026</td>
  <td>✓ Изпратен</td>
  <td>accountant@ekosch.bg</td>
</tr>
<!-- ... -->
</table>
```

---

# §20. ДВОЙНА ВАЛУТА (€ + лв) ДО 8.8.2026

## 20.1 Legal context

България в евро от **1 януари 2026**. Преходен период: **до 8 август 2026** (8 месеца) — задължително двойно обозначаване на всички цени.

**Фиксиран курс:** `1 EUR = 1.95583 BGN`

## 20.2 Универсална функция

```php
// lib/price-format.php
function priceFormat(float $amount, string $primary = 'EUR', bool $dual = true, int $decimals = 2): string {
    $rate = 1.95583;
    
    if ($primary === 'EUR') {
        $main = number_format($amount, $decimals, '.', ' ') . ' €';
        $sec  = number_format($amount * $rate, $decimals, '.', ' ') . ' лв';
    } else {
        $main = number_format($amount, $decimals, '.', ' ') . ' лв';
        $sec  = number_format($amount / $rate, $decimals, '.', ' ') . ' €';
    }
    
    // Tenant override (settings.dual_currency = 0/1)
    if (!$dual || isAfter('2026-08-08')) {
        return $main;
    }
    
    return "{$main} / {$sec}";
}
```

## 20.3 Wherever displayed

**ВСЯКА** цена в stats модула минава през `priceFormat()`:

```php
// ✅ ПРАВИЛНО
echo priceFormat($revenue);  // "847 € / 1 656.46 лв"

// ❌ ЗАБРАНЕНО
echo "{$revenue}€";          // hardcoded, не обновява при cutoff
echo number_format($revenue, 2) . ' BGN';  // wrong currency
```

## 20.4 KPI карта rendering

```html
<div class="kpi-num-row">
    <span class="kpi-num">847</span>
    <span class="kpi-cur">€</span>
    <span class="kpi-num-secondary">/ 1 656.46 лв</span>
</div>
```

```css
.kpi-num { font-size: 38px; font-weight: 900; }
.kpi-cur { font-size: 14px; color: var(--text-muted); margin-left: 4px; }
.kpi-num-secondary {
    font-size: 13px;
    color: var(--text-faint);
    margin-left: 6px;
}
```

## 20.5 Auto-cutoff на 8.8.2026

```php
function shouldShowDualCurrency(): bool {
    $cutoff = strtotime('2026-08-08 23:59:59');
    return time() <= $cutoff;
}
```

След 9.8.2026 — само € показвано. Двойната валута automatically се изключва без code change.

## 20.6 Tenant override

Owner може **рано** да изключи двойната валута в settings:

```sql
ALTER TABLE tenants ADD COLUMN show_dual_currency BOOLEAN DEFAULT TRUE;
```

UI в settings:
```
Валута:
☑ Показвай € + лв до 8.8.2026 (препоръчително)
```

## 20.7 Export файлове

CSV/PDF — двойна валута винаги в Excel/PDF като колона:

```csv
...;Сума €;Сума лв;...
...;847.00;1656.46;...
```

В PDF reports — главната цифра е €, секундарната малка отдолу.

---

# §21. PERFORMANCE & CACHING

## 21.1 Query budget

**Performance targets:**
- Tab 1 Преглед initial load: < 300ms
- Tab switch: < 150ms
- Drill-down drawer open: < 200ms (data prefetched)
- Export CSV (30-day period): < 2s
- AI insight generation (cached): instant
- AI insight generation (fresh, Gemini): < 3s (with 3s timeout)

## 21.2 Caching layers

### Layer 1: Redis (hot data, 5-30 min TTL)

```php
// stats-engine.php :: getCachedKPI
function getCachedKPI(string $metric, string $context_hash): ?array {
    $key = "stats:{$tenant}:m={$metric}:ctx={$context_hash}";
    $cached = Redis::get($key);
    if ($cached) return json_decode($cached, true);
    
    $data = computeKPI($metric);
    Redis::setex($key, getTTLForMetric($metric), json_encode($data));
    return $data;
}

function getTTLForMetric(string $m): int {
    return match($m) {
        'revenue_today' => 300,       // 5 min
        'revenue_7d', 'revenue_30d' => 900,   // 15 min
        'revenue_90d', 'revenue_365d' => 3600, // 1 hour
        'dead_stock' => 1800,         // 30 min
        'top_products' => 600,        // 10 min
        'ai_insight' => 1800,         // 30 min
        default => 600
    };
}
```

### Layer 2: Materialized views (daily aggregates)

Изграждат се **nightly cron** в 03:00:

```sql
CREATE TABLE stats_daily_aggregates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    agg_date DATE NOT NULL,
    revenue DECIMAL(10,2) DEFAULT 0,
    transactions INT DEFAULT 0,
    avg_ticket DECIMAL(10,2),
    cogs DECIMAL(10,2),
    gross_profit DECIMAL(10,2),
    margin_pct DECIMAL(5,2),
    discount_total DECIMAL(10,2),
    vat_total DECIMAL(10,2),
    top_product_id BIGINT,
    top_product_qty INT,
    items_sold INT,
    unique_skus INT,
    UNIQUE KEY uniq_tenant_store_date (tenant_id, store_id, agg_date)
);

CREATE TABLE stats_inventory_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    store_id INT NOT NULL,
    snapshot_date DATE NOT NULL,
    product_id BIGINT NOT NULL,
    quantity INT,
    cost_price DECIMAL(10,2),
    inventory_value DECIMAL(10,2),
    UNIQUE KEY uniq_t_s_p_d (tenant_id, store_id, product_id, snapshot_date)
);
```

Cron skript:
```bash
# /etc/cron.d/runmystore-stats
0 3 * * * www-data /var/www/runmystore/cron/aggregate-stats.php
```

```php
// cron/aggregate-stats.php
foreach (getActiveTenants() as $tenant) {
    foreach (getTenantStores($tenant->id) as $store) {
        // Aggregate yesterday
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        aggregateDay($tenant->id, $store->id, $yesterday);
        
        // Snapshot today's inventory
        snapshotInventory($tenant->id, $store->id, date('Y-m-d'));
    }
}
```

### Layer 3: Browser cache (frontend)

```html
<meta http-equiv="Cache-Control" content="private, max-age=300">
```

Period pills clicks — frontend cache last fetched data в `sessionStorage`:
```javascript
const cacheKey = `stats_${tab}_${period}_${storeId}`;
const cached = sessionStorage.getItem(cacheKey);
if (cached && Date.now() - JSON.parse(cached).ts < 300000) {
    renderFromCache(JSON.parse(cached).data);
} else {
    fetch(...).then(...);
}
```

## 21.3 Query optimization patterns

### Pattern 1: Use daily aggregates за дълги периоди

```php
function getRevenueForPeriod($tenant_id, $store_ids, $from, $to) {
    $days = (strtotime($to) - strtotime($from)) / 86400;
    
    if ($days > 7) {
        // Use materialized view
        return DB::query("SELECT SUM(revenue) FROM stats_daily_aggregates
                          WHERE tenant_id = ? AND store_id IN (?)
                            AND agg_date BETWEEN ? AND ?", ...);
    } else {
        // Realtime aggregation (sales table)
        return DB::query("SELECT SUM(total) FROM sales WHERE ...", ...);
    }
}
```

### Pattern 2: Lazy loading on tab switch

```javascript
let tabsLoaded = { overview: false, products: false, finance: false };

function switchTab(tab) {
    if (!tabsLoaded[tab]) {
        showSkeleton(tab);
        fetch(`/stats-api.php?tab=${tab}`).then(r => {
            renderTab(tab, r);
            tabsLoaded[tab] = true;
        });
    } else {
        showCachedTab(tab);
    }
}
```

### Pattern 3: Batch queries

Не **3 отделни заявки** за revenue/transactions/AOV → **1 заявка**:

```sql
SELECT
    SUM(total) AS revenue,
    COUNT(*) AS transactions,
    AVG(total) AS aov,
    SUM(discount_amount) AS discount_total,
    COUNT(DISTINCT user_id) AS active_sellers
FROM sales
WHERE tenant_id = :t AND status='completed'
  AND created_at BETWEEN :from AND :to;
```

## 21.4 Cache invalidation triggers

**Events that invalidate cache:**

| Event | Invalidated keys |
|---|---|
| Нова продажба | `stats:{t}:m=revenue_*`, `stats:{t}:m=top_products`, `stats:{t}:m=transactions_*` |
| Нов разход (Phase 8) | `stats:{t}:m=cashflow`, `stats:{t}:m=expenses_*`, `stats:{t}:m=margin_*` |
| Нова доставка | `stats:{t}:m=cogs`, `stats:{t}:m=margin_*`, `stats:{t}:m=top_profit` |
| Касиране (close shift) | `stats:{t}:m=z_report`, `stats:{t}:m=daily_*` |
| Cost price update | `stats:{t}:m=margin_*`, `stats:{t}:m=dead_capital`, `stats:{t}:m=gmroi` |

Implementation:
```php
// При запис на продажба
function invalidateStatsCacheOnSale($tenant_id, $store_id) {
    Redis::del([
        "stats:{$tenant_id}:m=revenue_today",
        "stats:{$tenant_id}:m=revenue_7d",
        "stats:{$tenant_id}:m=revenue_30d",
        "stats:{$tenant_id}:m=top_products_today",
        "stats:{$tenant_id}:m=transactions_today",
        "ai_insights:t={$tenant_id}:m=stats:tab=overview:p=today"
    ]);
}
```

## 21.5 Index hot paths

Composite indices необходими (виж §6.5):

```sql
-- Hot query: revenue for period
CREATE INDEX idx_sales_tenant_status_date ON sales(tenant_id, status, created_at);

-- Hot query: top products for period
CREATE INDEX idx_si_sale_product ON sale_items(sale_id, product_id);

-- Hot query: dead stock
CREATE INDEX idx_sm_product_type_date ON stock_movements(product_id, type, created_at);

-- Hot query: cash flow
CREATE INDEX idx_bt_account_date_dir ON bank_transactions(account_id, transaction_date, direction);
```

---

# §22. FALLBACK ПОВЕДЕНИЕ

## 22.1 Gemini timeout / failure

**Закон "AI мълчи, PHP продължава":** при AI fail UI не показва spinner forever; PHP fallback моментално.

```php
function getAIInsight($context, $timeout_ms = 3000) {
    try {
        $response = $geminiClient->generate([
            'prompt' => buildPrompt($context),
            'timeout' => $timeout_ms / 1000,
        ]);
        
        if ($response->confidence >= 0.85) {
            return ['text' => $response->text, 'source' => 'ai', 'confidence' => $response->confidence];
        } elseif ($response->confidence >= 0.50) {
            return ['text' => $response->text, 'source' => 'ai_marker', 'confidence' => $response->confidence];
        } else {
            return getPHPFallback($context);
        }
    } catch (TimeoutException $e) {
        logAIFailure('timeout', $context);
        return getPHPFallback($context);
    } catch (\Exception $e) {
        logAIFailure('error', $context, $e);
        return getPHPFallback($context);
    }
}

function getPHPFallback($context) {
    return [
        'text' => stats_formulas::fallbackText($context['metric'], $context['data']),
        'source' => 'php_fallback',
        'confidence' => 1.0  // PHP formulas are deterministic
    ];
}
```

## 22.2 Partial data handling

При липсващи cost_at_sale (старите продажби преди миграцията):

```php
function calcMarginWithConfidence($sale_items) {
    $with_cost = 0;
    $without_cost = 0;
    $total_revenue = 0;
    $total_profit = 0;
    
    foreach ($sale_items as $si) {
        $total_revenue += $si->line_total;
        if ($si->cost_at_sale !== null && $si->cost_at_sale > 0) {
            $total_profit += $si->quantity * ($si->unit_price - $si->cost_at_sale);
            $with_cost++;
        } else {
            $without_cost++;
        }
    }
    
    $confidence_pct = ($with_cost + $without_cost) > 0
        ? round($with_cost / ($with_cost + $without_cost) * 100, 1)
        : 100;
    
    return [
        'gross_profit' => $total_profit,
        'margin_pct' => $total_revenue > 0 ? round($total_profit / $total_revenue * 100, 1) : 0,
        'confidence_pct' => $confidence_pct,
        'with_cost' => $with_cost,
        'total' => $with_cost + $without_cost
    ];
}
```

UI warning (вече в текущия stats.php):
```html
<?php if ($margin_data['confidence_pct'] < 100): ?>
<div class="conf-warn" style="...">
  <svg><!-- warn --></svg>
  <span>Приблизителна печалба — данни за <?= $margin_data['confidence_pct'] ?>%
  от артикулите (<?= $margin_data['with_cost'] ?>/<?= $margin_data['total'] ?>).
  <a href="inventory.php">Инвентаризация →</a></span>
</div>
<?php endif; ?>
```

## 22.3 SQL failure

```php
try {
    $data = DB::query($sql, $params);
} catch (PDOException $e) {
    logSQLFailure($e, $sql, $params);
    
    // UI shows error state, suggests retry
    return [
        'error' => true,
        'message' => 'Грешка при зареждане. Опитай отново.',
        'retry_url' => $_SERVER['REQUEST_URI']
    ];
}
```

## 22.4 Empty data state

Различни scenarios:

| Scenario | UI behavior |
|---|---|
| Нов магазин (0 sales) | Empty state с CTA "Започни продажба" |
| Нов период (днес 0 sales) | Hero card "0 € — още няма продажби днес" |
| Filter result 0 (търсиш мъртви артикули, няма такива) | "✓ Няма мъртви артикули — всичко се движи" |
| Manager view без profit | Скрита карта (CSS hide) |
| FREE plan заключена карта | Locked overlay с upgrade CTA |

## 22.5 Network offline

Capacitor mobile може да е offline:

```javascript
// frontend
if (!navigator.onLine) {
    showOfflineBanner();
    loadFromIndexedDB();
}
```

Stats модул не е critical offline — показваме последния cached snapshot:
```
┌────────────────────────────────┐
│ ⚠ Няма интернет — данни от    │
│   17.05 09:32 (преди 2 часа)   │
└────────────────────────────────┘
```

---

# §23. AUDIT TRAIL — retrieved_facts JSON SCHEMA

## 23.1 Закон №7

Всеки AI insight ЗАДЪЛЖИТЕЛНО записва **точно кои SQL резултати** са го генерирали. Това позволява:
- Reproducibility (същият контекст → същият insight)
- Debugging (защо AI каза това?)
- Auditing (юридическо: показано на потребителя че се основава на реални данни)

## 23.2 Schema

```json
{
  "insight_id": 12345,
  "tenant_id": 7,
  "topic_id": "biz_revenue_001",
  "module": "stats",
  "sub_tab": "overview",
  "rendered_text": "Това е 4-тият петък над €700.",
  "confidence": 0.92,
  "source": "ai",
  "ai_model": "gemini-2.5-flash",
  "ai_latency_ms": 1247,
  "retrieved_facts": {
    "queries": [
      {
        "name": "revenue_today",
        "sql": "SELECT SUM(total) AS revenue FROM sales WHERE tenant_id = ? AND status='completed' AND DATE(created_at) = CURDATE()",
        "params": {"tenant_id": 7},
        "result": 847.00,
        "executed_at": "2026-05-17T09:32:14Z"
      },
      {
        "name": "revenue_same_dow_history",
        "sql": "SELECT WEEK(created_at), SUM(total) FROM sales WHERE DAYOFWEEK=DAYOFWEEK(NOW()) AND DATE(created_at)<CURDATE() AND created_at >= NOW() - INTERVAL 5 WEEK GROUP BY WEEK ORDER BY WEEK DESC LIMIT 4",
        "params": {"tenant_id": 7},
        "result": [
          {"week": 20, "revenue": 756.00},
          {"week": 19, "revenue": 712.00},
          {"week": 18, "revenue": 805.00},
          {"week": 17, "revenue": 690.00}
        ]
      }
    ],
    "computed": {
      "consecutive_above_700": 4,
      "all_above_threshold": true,
      "trend_direction": "up"
    },
    "logic": "4 consecutive Fridays where revenue > €700 (current + last 3). Pattern detected."
  },
  "shown_at": "2026-05-17T09:32:15Z",
  "user_action": null,
  "dismissed_at": null
}
```

## 23.3 Storage в DB

В таблицата `ai_insights` (вече съществуваща от S87):

```sql
INSERT INTO ai_insights (
    tenant_id, topic_id, module, rendered_text, confidence,
    retrieved_facts, shown_at
) VALUES (
    7,
    'biz_revenue_001',
    'stats',
    'Това е 4-тият петък над €700.',
    0.92,
    '{...JSON above...}',
    NOW()
);
```

## 23.4 Drill-down "Защо?" UI

При tap на AI insight → user може да попита **"Защо?"**:

```
┌────────────────────────────────┐
│ ▬▬▬▬                          │
│ Защо AI каза това?       [✕]  │
│                                │
│ "Това е 4-тият петък над €700"│
│                                │
│ AI се основава на:             │
│                                │
│ Днешен оборот:    €847        │
│ Минал петък:      €756        │
│ Преди 2 седмици:  €712        │
│ Преди 3 седмици:  €805        │
│ Преди 4 седмици:  €690        │
│                                │
│ Извод: 4 поредни петъка с     │
│ оборот над €700 — pattern      │
│ detection.                     │
│                                │
│ Confidence: 92%                │
│                                │
│ [Закрий]                       │
└────────────────────────────────┘
```

```php
// /api/ai-insight-explain.php
function explainInsight($insight_id) {
    $insight = DB::query("SELECT * FROM ai_insights WHERE id = ?", [$insight_id])->fetch();
    $facts = json_decode($insight['retrieved_facts'], true);
    
    return [
        'rendered_text' => $insight['rendered_text'],
        'confidence_pct' => round($insight['confidence'] * 100),
        'data_sources' => $facts['queries'],
        'computed_logic' => $facts['logic']
    ];
}
```

## 23.5 Retention policy

Audit логове се пазят **7 години** (БГ данъчни изисквания — виж DOC_05 §audit_log).

```sql
-- Nightly archival (older than 90 days → cold storage)
INSERT INTO ai_insights_archive
SELECT * FROM ai_insights
WHERE shown_at < NOW() - INTERVAL 90 DAY;

DELETE FROM ai_insights WHERE shown_at < NOW() - INTERVAL 90 DAY;
```

---

# §24. S82-DASH ИНТЕГРАЦИЯ — МИНИ СПРАВКИ

## 24.1 Концепция (Тих, S148)

> *"s82 е мини справки със същото което прави AI чата на Simple режим за сигналите, само че има някакви бутони."*

Превод:
- **s82-dash** в life-board.php = миниатюрна версия на Detailed Stats модула
- Показва **същите AI сигнали** като AI chat card (от ai_brain_queue)
- ПЛЮС: action бутони за **директна интеракция** със сигнала (не само passive view)

## 24.2 Архитектурно положение

```
LIFE-BOARD.PHP (Simple Mode home)
│
├── s82-dash (мини Stats)
│   ├── Главно число (Оборот днес)
│   ├── 3 ротиращи слота (AI сигнали + action бутони)
│   └── Period pills [Днес][7д][30д][365д]
│
├── 4 op-buttons (Продай/Стоката/Доставка/Поръчка)
├── AI Studio entry
├── Weather card
└── AI chat card (signals stream)

       ↓ tap на цялата s82 карта → отваря stats.php
       ↓ tap на конкретен слот → отваря relevant sub-section в stats.php
```

## 24.3 Текущо състояние (за reference)

```
┌──────────────────────────────────┐
│ Днес · Цариградско          +12%│
│ 847 EUR                          │
│ 7 продажби · vs 4 вчера         │
│ [Днес][7д][30д][365д] | [Об][Пе]│
└──────────────────────────────────┘
```

= Само едно число + период pills + mode toggle (Оборот/Печалба). **Нула AI.**

## 24.4 Новата визия (s82 v2)

```
┌──────────────────────────────────────┐
│ Днес · Цариградско  ●●○  [👑]      │  rotation indicator + role badge
├──────────────────────────────────────┤
│ 847 €  / 1 656.46 лв         +12%   │  главно число
│ 7 продажби · vs 4 вчера              │  meta
├──────────────────────────────────────┤
│ ✨ 4-тият петък над €700 — стабилно │  SLOT 1 (AI context)
│                                      │
│ Топ: Nike 42 · 3 бр · 360€    →     │  SLOT 2 (top product)
│                                      │
│ ⚠ Свършва: Levi's 32 · ~2 дни  [📋]│  SLOT 3 (low stock + action)
├──────────────────────────────────────┤
│ [Днес][7д][30д][365д] | [Об][Пе]   │  period pills
└──────────────────────────────────────┘
```

## 24.5 3 ротиращи слота — приоритет

AI engine избира **3 от 5 възможни типа** според приоритет:

| Slot type | Trigger | Priority |
|---|---|---|
| **A. AI context** | Винаги (1 patern detection insight) | 1 (винаги показва) |
| **B. Top product** | Винаги при > 1 sale today | 2 |
| **C. Reorder signal** | Low stock + sales velocity > 0 | 3 (ако има) |
| **D. Dead capital alert** | dead_value > €200 | 4 (rotated) |
| **E. WoW context** | period != 'today' | 5 (rotated) |

**Винаги показва:** A + B + (C OR D OR E според relevance).

## 24.6 Slot rendering

```html
<div class="glass sm s82-dash qd">
  <span class="shine"></span><span class="shine shine-bottom"></span>
  <span class="glow"></span><span class="glow glow-bottom"></span>
  
  <!-- HEADER ROW -->
  <div class="s82-dash-top">
    <span class="s82-dash-period-label">Днес · Цариградско</span>
    <span class="s82-rotation-dots">●●○</span>
  </div>
  
  <!-- MAIN NUMBER ROW -->
  <div class="s82-dash-numrow">
    <span class="s82-dash-num">847</span>
    <span class="s82-dash-cur">€ / 1 656.46 лв</span>
    <span class="s82-dash-pct positive">+12%</span>
  </div>
  <div class="s82-dash-meta">7 продажби · vs 4 вчера</div>
  
  <!-- SLOT 1: AI CONTEXT -->
  <div class="s82-slot s82-slot-ai" data-slot="ai_context">
    <svg class="s82-spark"><!-- ✨ --></svg>
    <span class="s82-slot-text">4-тият петък над €700 — стабилно</span>
  </div>
  
  <!-- SLOT 2: TOP PRODUCT (tappable) -->
  <button class="s82-slot s82-slot-product" data-slot="top_product"
          onclick="navigateToProduct(12345)">
    <span class="s82-slot-label">Топ:</span>
    <span class="s82-slot-text">Nike 42 · 3 бр · 360€</span>
    <svg class="s82-slot-arrow"><!-- → --></svg>
  </button>
  
  <!-- SLOT 3: ACTION (low stock) -->
  <div class="s82-slot s82-slot-action q5" data-slot="low_stock">
    <svg class="s82-slot-icon"><!-- warning --></svg>
    <span class="s82-slot-text">Свършва: Levi's 32 · ~2 дни</span>
    <button class="s82-slot-action-btn"
            onclick="quickOrder(67890)">
      <svg><!-- 📋 --></svg>
    </button>
  </div>
  
  <!-- PERIOD PILLS (existing) -->
  <div class="s82-dash-pills">
    <button class="s82-dash-pill rev-pill active" data-period="today">Днес</button>
    <button class="s82-dash-pill rev-pill" data-period="7d">7 дни</button>
    <!-- ... -->
  </div>
</div>
```

## 24.7 CSS на новите слотове

```css
.s82-slot {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  margin: 6px -2px;
  border-radius: var(--radius-sm);
  background: hsl(var(--hue1) 20% 12% / 0.3);
  font-size: 12px;
  font-weight: 600;
  color: var(--text);
  text-align: left;
  border: none;
  width: calc(100% + 4px);
  cursor: pointer;
  transition: all 0.2s;
}

.s82-slot:hover {
  background: hsl(var(--hue1) 20% 15% / 0.4);
}

.s82-slot-ai {
  background: linear-gradient(135deg,
    hsl(280 50% 25% / 0.3),
    hsl(305 50% 25% / 0.3));
}

.s82-slot-action.q5 {
  background: hsl(38 50% 20% / 0.3);
  border: 1px solid hsl(38 60% 40% / 0.3);
}

.s82-spark {
  width: 14px; height: 14px;
  flex-shrink: 0;
  fill: hsl(280 70% 70%);
}

.s82-slot-text {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.s82-slot-arrow {
  width: 12px; height: 12px;
  opacity: 0.5;
  flex-shrink: 0;
}

.s82-slot-action-btn {
  background: hsl(38 70% 50%);
  border-radius: 50%;
  width: 26px; height: 26px;
  display: grid;
  place-items: center;
  border: none;
  cursor: pointer;
  flex-shrink: 0;
}
.s82-slot-action-btn svg { width: 14px; height: 14px; fill: white; }

.s82-rotation-dots {
  font-size: 8px;
  letter-spacing: 2px;
  color: var(--text-faint);
}
```

## 24.8 Slot rotation logic

3 слота × 5 возможни types = AI engine избира top 3:

```php
// life-board.php :: getS82Slots
function getS82Slots($context) {
    $candidates = [];
    
    // A. AI context (always present)
    $candidates[] = [
        'type' => 'ai_context',
        'priority' => 1,
        'render' => generateAIContext($context),
        'action' => null
    ];
    
    // B. Top product (always if sales > 0)
    if ($context['sales_today'] > 0) {
        $top = getTopProductToday($context);
        $candidates[] = [
            'type' => 'top_product',
            'priority' => 2,
            'render' => "Топ: {$top['name']} · {$top['qty']} бр · {$top['rev']}€",
            'action' => "navigateToProduct({$top['id']})"
        ];
    }
    
    // C. Reorder signal (if low stock with velocity)
    $low = getLowStockTop($context);
    if ($low && $low['days_until_zero'] < 7) {
        $candidates[] = [
            'type' => 'low_stock',
            'priority' => 3,
            'render' => "Свършва: {$low['name']} · ~{$low['days_until_zero']} дни",
            'action' => "quickOrder({$low['id']})"
        ];
    }
    
    // D. Dead capital (if > €200)
    if ($context['dead_capital'] > 200) {
        $candidates[] = [
            'type' => 'dead_capital',
            'priority' => 4,
            'render' => "Замразени: €{$context['dead_capital']} в {$context['dead_count']} артикула",
            'action' => "openStats('products', 'dead')"
        ];
    }
    
    // E. WoW context (passive)
    if ($context['period'] !== 'today') {
        $candidates[] = [
            'type' => 'wow',
            'priority' => 5,
            'render' => $context['wow_pct'] > 0
                ? "+{$context['wow_pct']}% спрямо миналата седмица"
                : "{$context['wow_pct']}% спрямо миналата седмица",
            'action' => null
        ];
    }
    
    // Sort by priority and take top 3
    usort($candidates, fn($a, $b) => $a['priority'] - $b['priority']);
    return array_slice($candidates, 0, 3);
}
```

## 24.9 Tap behaviors

| Slot | Tap → |
|---|---|
| AI context | nothing (passive) OR drawer "Защо?" (виж §23.4) |
| Top product | `products.php?id={product_id}` |
| Low stock | `orders.php?new&product_id={pid}&qty_suggested={n}` |
| Dead capital | `stats.php?tab=products&filter=dead` |
| WoW context | `stats.php?tab=overview` |
| Action бутон (📋) | Direct create draft order |

## 24.10 Period pills behavior

При смяна на period — main number се обновява, **но слотовете остават**:

```javascript
function s82SetPeriod(period) {
    fetch(`/life-board-api.php?action=s82&period=${period}`)
        .then(r => r.json())
        .then(data => {
            // Update main number
            $('.s82-dash-num').text(data.revenue);
            $('.s82-dash-pct').text(`${data.pct > 0 ? '+' : ''}${data.pct}%`);
            $('.s82-dash-meta').text(data.meta);
            
            // Slots остават same (AI rotation independent от period)
            // OR ако period != 'today' → re-generate slots с different context
        });
}
```

## 24.11 ENTIRE s82 card tap (на празно място)

Tap на самата карта (не на слот) → отваря `stats.php?tab=overview`:

```html
<div class="glass sm s82-dash qd" onclick="openStatsFromS82(event)">
  <!-- ... -->
</div>

<script>
function openStatsFromS82(event) {
    // Не отваря ако клик на слот (има свой handler) или на period pill
    if (event.target.closest('.s82-slot, .s82-dash-pills, button')) return;
    location.href = 'stats.php?tab=overview';
}
</script>
```

## 24.12 Бутоните в s82 vs AI chat card

> Тих: *"същото което прави AI чата, само че има някакви бутони"*

Разлика:

| Aspect | AI chat card | s82-dash |
|---|---|---|
| **Главна задача** | Conversation (signals stream) | Headline number + 3 quick signals |
| **Размер** | ~140-220px (expanded) | ~280-340px (fixed) |
| **Слотове** | 1 active signal | 3 signals (rotation) |
| **Бутоните** | "Да, кажи" / "После" | Direct actions (Поръчай / Виж артикул) |
| **Position** | Long-form, scrollable | Above the fold, always visible |
| **Voice** | Yes (chat bar internal) | No (only display) |

## 24.13 ROLE-BASED rendering в s82

Пешо (seller) → НЕ вижда:
- Margin/profit числа (никога)
- Dead capital в €
- "Колко печелим" insights

Owner/manager → виждат всичко.

```php
if ($role === 'seller') {
    // Hide profit-related slots
    $candidates = array_filter($candidates, fn($c) => !in_array($c['type'], ['margin', 'profit', 'dead_capital_value']));
}
```

---

# §25. TESTING СЦЕНАРИИ

## 25.1 Unit tests (PHPUnit)

Тестове за `stats-formulas.php`:

```php
class StatsFormulasTest extends TestCase {
    public function testRevenueCalculation() {
        $sales = [
            ['total' => 100.00],
            ['total' => 50.50],
            ['total' => 75.25],
        ];
        $this->assertEquals(225.75, calculateRevenue($sales));
    }
    
    public function testMarginCalculation() {
        $items = [
            ['quantity' => 2, 'unit_price' => 50.00, 'cost_at_sale' => 30.00],
            ['quantity' => 1, 'unit_price' => 80.00, 'cost_at_sale' => 50.00],
        ];
        $result = calcMarginWithConfidence($items);
        $this->assertEquals(70.00, $result['gross_profit']);  // (2*20) + (1*30)
        $this->assertEquals(38.89, $result['margin_pct']);   // 70 / 180 * 100
    }
    
    public function testMarginWithMissingCostPrice() {
        $items = [
            ['quantity' => 1, 'unit_price' => 50.00, 'cost_at_sale' => 30.00],
            ['quantity' => 1, 'unit_price' => 50.00, 'cost_at_sale' => null], // legacy
        ];
        $result = calcMarginWithConfidence($items);
        $this->assertEquals(50.00, $result['confidence_pct']);
    }
    
    public function testDualCurrencyFormat() {
        $this->assertEquals('847.00 € / 1 656.46 лв', priceFormat(847.00));
    }
    
    public function testFallbackTextRevenue() {
        $data = ['current' => 847, 'delta_pct' => 12, 'dow' => 'петък'];
        $this->assertEquals('847€ — над миналия петък',
                            fallbackText('revenue', $data));
    }
}
```

## 25.2 Integration tests — Persona scenarios

### Scenario 1: Пешо отваря life-board → s82

```
1. Login като seller
2. Експектация: бутон "Справки" в bottom nav е скрит
3. Експектация: s82-dash показва 3 слота (без margin/profit info)
4. Tap на period pill "7д" → main number се обновява
5. Tap на slot "Top product" → отваря products.php?id=X
```

### Scenario 2: Митко проверява финанси

```
1. Login като owner
2. Експектация: bottom nav показва "Справки" таб
3. Тап → отваря stats.php?tab=overview
4. Експектация: 4 KPI карти (Revenue/Transactions/AOV/Margin)
5. Тап на таб "Финанси" → отваря stats.php?tab=finance&sub=profit
6. Експектация: показва P&L breakdown (Phase B)
7. Тап на sub "Cash" → ако Phase B → показва "Скоро" badge
                    → ако Phase 8 → показва cash flow data
```

### Scenario 3: Manager без profit достъп

```
1. Login като manager
2. Експектация: stats.php?tab=finance → redirect към ?tab=overview
3. Експектация: KPI "Марж 28%" карта скрита
4. Експектация: Top 5 toggle показва само "Бройки" и "Оборот", не "Печалба"
5. Експектация: Seller performance таблица показва имена и брой продажби,
                но НЕ revenue/profit колоните
```

## 25.3 Edge cases

| Edge case | Expected behavior |
|---|---|
| Нов магазин (0 sales) | Empty state с CTA |
| Един product без cost_price | Confidence warning + margin партиал |
| Multi-store без cross-store достъп | Скрита cross-store карта |
| Legacy data преди cost_at_sale миграция | Confidence warning + partial calc |
| Custom period > 365 дни | Грешка "Максимум 365 дни" |
| Period с future dates | "Невалиден период" |
| Tenant в FREE → opens stats finance | Redirect към ?tab=overview + upgrade modal |
| AI Gemini timeout | PHP fallback показва за < 50ms |
| SQL fail на 1 query | Other карти рендерват, само failed-ата показва error state |
| Voice query "колко продадох" в s82 | Open AI chat card с pre-filled prompt |

## 25.4 Performance tests

```php
class StatsPerformanceTest extends TestCase {
    public function testTab1LoadUnder300ms() {
        $start = microtime(true);
        $response = $this->httpGet('/stats.php?tab=overview&period=today');
        $elapsed = (microtime(true) - $start) * 1000;
        $this->assertLessThan(300, $elapsed, "Tab 1 load should be < 300ms");
    }
    
    public function testExportCSVUnder2sFor30Days() {
        $start = microtime(true);
        $this->httpGet('/stats-export.php?format=csv&type=sales&period=month');
        $this->assertLessThan(2000, (microtime(true) - $start) * 1000);
    }
    
    public function testAIInsightUnder3sWithGeminiTimeout() {
        $start = microtime(true);
        getAIInsight($context, $timeout_ms = 3000);
        $this->assertLessThan(3000, (microtime(true) - $start) * 1000);
    }
}
```

## 25.5 Visual regression tests

При промяна на CSS — screenshot diff на 5 viewport-а:

```javascript
// Playwright e2e
test('Tab 1 visual on Z Flip6 (375px)', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await page.goto('/stats.php?tab=overview&period=today');
    await page.screenshot({ path: 'tests/screenshots/stats_tab1_375.png' });
    expect(await page.screenshot()).toMatchSnapshot('stats_tab1_375.png');
});

test('Sacred Glass effects on dark mode', async ({ page }) => {
    await page.goto('/stats.php?tab=overview&theme=dark');
    const shine = await page.$$('.glass .shine');
    expect(shine.length).toBeGreaterThan(0);
    
    const computed = await page.evaluate(() => {
        const el = document.querySelector('.glass .glow');
        return window.getComputedStyle(el).display;
    });
    expect(computed).not.toBe('none');
});
```

## 25.6 Acceptance criteria за beta

Преди beta (Phase B) — следните MUST pass:

✓ Owner може да види Tab 1 + Tab 2 + Tab 3.1 (Печалба)
✓ Manager може да види Tab 1 + Tab 2 (без profit карти)
✓ Seller не може да види stats.php (redirect към life-board)
✓ s82-dash показва 3 ротиращи слота за owner
✓ s82-dash показва 2 слота (без profit) за manager
✓ Двойна валута € + лв работи на ВСЯКА цена
✓ Export CSV работи за период до 90 дни
✓ Z-report се генерира автоматично при close shift
✓ AI insight се генерира с PHP fallback при Gemini fail
✓ Confidence warning се показва при cost_price < 100%
✓ Mobile-first 375px — никакъв horizontal scroll
✓ Sacred Glass canon приложен — никаква карта без 4 spans
✓ RBAC matrix приложена на ВСЯКА метрика

---

# §26. MIGRATION PLAN — ОТ ТЕКУЩИЯ STATS.PHP

## 26.1 Текущ stats.php състояние

- **Файл:** `/var/www/runmystore/stats.php`
- **Размер:** 1371 реда (PHP+HTML+CSS+JS в един файл)
- **Структура:** 5 таба (Обзор/Продажби/Стоки/Финанси/Аномалии)
- **Status:** Functional, но непълно документиран

## 26.2 Migration phases

### Phase 26.1: Backup + Bible publish (Day 1, 30 min)

```bash
# Backup
cp /var/www/runmystore/stats.php /var/www/runmystore/stats.php.bak.before_v2.S148

# Tag в git
cd /var/www/runmystore
git add STATS_FINANCE_MODULE_BIBLE_v1.md
git commit -m "S148: Stats+Finance Module Bible v1 (4 ETAP-а)"
git push origin main
```

### Phase 26.2: DB migrations (Day 1-2, 2 hours)

Run migrations M-001, M-002, M-003 (Phase B critical):

```bash
mysql -u root runmystore < migrations/20260518_001_finance_vat_rates.up.sql
mysql -u root runmystore < migrations/20260518_002_finance_z_reports.up.sql
mysql -u root runmystore < migrations/20260518_003_finance_store_balances.up.sql

# Verify
mysql -u root runmystore -e "SHOW TABLES LIKE '%vat%';"
mysql -u root runmystore -e "SELECT * FROM vat_rates WHERE country='BG';"
```

### Phase 26.3: Backend split (Day 3-5, 1 day)

Split на текущия `stats.php` (1371 реда) на:

```
stats.php                              (~200 реда, entry + routing)
lib/stats-engine.php                   (~500 реда, SQL queries)
lib/stats-ai-engine.php                (~300 реда, AI logic)
lib/stats-formulas.php                 (~200 реда, math + fallbacks)
partials/stats-overview.php            (~400 реда, Tab 1 HTML)
partials/stats-products.php            (~300 реда, Tab 2)
partials/stats-finance.php             (~100 реда, Tab 3 router)
partials/stats-finance-profit.php      (~400 реда, 12.1)
```

**Steps:**

1. Extract SQL queries → `lib/stats-engine.php`
2. Extract AI logic → `lib/stats-ai-engine.php`
3. Split tabs → `partials/stats-{tab}.php`
4. Test all 5 tabs работят (legacy URLs със ?tab=обзор etc.)
5. Map старите 5 таба → новите 3:
   - "Обзор" + "Продажби" → "Преглед"
   - "Стоки" → "Артикули"
   - "Финанси" + "Аномалии" → "Финанси > Печалба" (Phase B)
6. Anomalies → sticky alert ribbon (вместо отделен таб)

### Phase 26.4: UI rebuild (Day 6-10, 5 days)

Pe each tab:

```
Day 6:  Tab 1 "Преглед" — 11 компонента (виж §10)
Day 7:  Tab 2 "Артикули" — 6 компонента + product drawer (§11)
Day 8:  Tab 3.1 "Финанси > Печалба" — 6 компонента (§12.1)
Day 9:  Backend split refactor + integration tests
Day 10: Polish, animations, edge cases
```

### Phase 26.5: s82-dash v2 (Day 11, 1 day)

В `life-board.php`:
- Запази съществуващия `.s82-dash` CSS
- Добави 3 нови slot елементи под main number
- Добави slot rotation logic в backend
- Запази period pills behavior

Виж §24 за пълна спецификация.

### Phase 26.6: AI engine integration (Day 12-13, 2 days)

```php
// lib/stats-ai-engine.php
require_once 'lib/ai-topics-loader.php';
require_once 'lib/gemini-client.php';

class StatsAIEngine {
    public function getInsightsForTab($tab, $sub_tab = null) { ... }
    public function getS82Slots($context) { ... }
    public function detectAnomalies($context) { ... }
}
```

Integration testing с tenant=7 (test profile).

### Phase 26.7: QA + Beta deploy (Day 14-15, 2 days)

- Run all acceptance criteria от §25.6
- Test с реален Iван (ENI магазин, нов tenant_id)
- Visual regression tests на 5 viewports
- Performance benchmarks (виж §21.1)
- Deploy на production

## 26.3 Rollback plan

```bash
# Restore stats.php (всичко в един файл както беше)
cp /var/www/runmystore/stats.php.bak.before_v2.S148 /var/www/runmystore/stats.php

# Rollback миграции (Phase B tables)
mysql -u root runmystore < migrations/20260518_001_finance_vat_rates.down.sql
mysql -u root runmystore < migrations/20260518_002_finance_z_reports.down.sql
mysql -u root runmystore < migrations/20260518_003_finance_store_balances.down.sql

# Remove new partials
rm /var/www/runmystore/partials/stats-*.php
rm /var/www/runmystore/lib/stats-*.php

# Git revert ако needed
git revert HEAD~5
```

## 26.4 Verification checklist

След пълна migration:

- [ ] Legacy URL `/stats.php?tab=обзор` → redirect към `/stats.php?tab=overview`
- [ ] Legacy URL `/stats.php?tab=аномалии` → redirect към `/stats.php?tab=overview` (alert ribbon видим)
- [ ] Всички 20 KPI метрики работят
- [ ] RBAC guards функционални за owner/manager/seller
- [ ] Plan gating UI работи за FREE/START/PRO/BUSINESS
- [ ] s82-dash показва 3 слота с правилни данни
- [ ] Двойната валута се показва навсякъде
- [ ] Sacred Glass canon приложен на всяка карта
- [ ] Export CSV работи за всеки период
- [ ] Z-report генерира при close shift
- [ ] Mobile 375px — нула horizontal scroll
- [ ] AI insight slots показват PHP fallback при Gemini timeout
- [ ] Confidence warning се появява при cost_price < 100%

---

# §27. ROADMAP

## 27.1 Phase B — Beta-Critical (юни-юли 2026)

**Цел:** Stats модул се ползва от реални клиенти, не technical demo.

**Включва:**
- Tab 1 "Преглед" пълно функционален
- Tab 2 "Артикули" пълно функционален
- Tab 3.1 "Финанси > Печалба" функционален
- Tab 3.2-3.5 (Cash/Разходи/Дължими/Експорти) — placeholder с "Скоро" badge
- s82-dash v2 в life-board.php
- DB migrations M-001, M-002, M-003
- Export CSV (sales)
- Z-report generation
- 20 KPI метрики (Tier 1+2+3 partial)
- AI insights с PHP fallback
- Confidence warning logic

**Acceptance:** Иван (ENI магазин) може да следи бизнеса си 30 дни без external Excel.

## 27.2 Phase 8 — Finance Sub-domain (Q4 2026)

**Цел:** Митко не отваря Excel за финансови справки.

**Включва:**
- Tab 3.2 "Cash" — cash flow, balance, burn rate, break-even
- Tab 3.3 "Разходи" — expenses tracking, budget vs actual, fixed costs
- Tab 3.4 "Дължими" — receivables aging, B2B invoices, overdue
- Tab 3.5 "Експорти" — Microinvest/Sigma/Ajur integrations, email scheduling
- DB migrations M-004 → M-015 (12 нови таблици)
- B2B invoicing module (separate sub-system)
- Bank statement CSV import
- Tax payment tracking
- AI insight темите за финанси (cash 24, tax 23, expenses 15+)

**Зависи от:** Bible Phase 8 extension (нов doc или v2 на този).

## 27.3 Phase C+ — Advanced (2027)

**Възможни добавки:**
- Forecasting submodule (само stockout dates, не оборот)
- ML-based anomaly detection
- Real-time alerts через push notifications
- Open banking API integration (auto bank reconciliation)
- Multi-currency support (за RO/GR експанзия)
- Comparative benchmarking с anonymous tenant cohorts
- AI-generated monthly executive summary (PDF auto-email)
- Voice query "as if you spoke to your accountant"

## 27.4 Open questions за бъдещи etap-и

1. **Inventory snapshots cron** — daily snapshot 03:00? Или change-data-capture при stock_movement?
2. **Multi-currency** — Кога? БГ → ЕС експанзия → multi-currency.
3. **Anonymous benchmarks** — Може ли да показваме "магазини с твоя профил имат средно 28% марж — ти си над"?
4. **AI Counselor** — отделен модул за proactive advice?
5. **POS integration** — кога е готов real-time sync с external POS?

---

# 🏁 КРАЙ НА BIBLE v1

**Всички 4 ETAP-а покрити.** Документ е готов за:
- Mockup creation (ETAP 5 follow-up)
- Backend implementation (Phase 26.3-26.7)
- DB migration (Phase 26.2)
- Beta deploy (Phase 26.7)

**Общ обем:** ~5000 реда

**Покрити секции:** §1-§27 — пълна спецификация на модул "Справки" (Stats + Finance)

**Файл:** `STATS_FINANCE_MODULE_BIBLE_v1.md`
**Owner:** Тихол
**Live document — попълва се при изменения**

