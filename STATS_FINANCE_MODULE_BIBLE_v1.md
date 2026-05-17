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


═══════════════════════════════════════════════════════════════
# ETAP 5 — POCKET CFO + RUNMYSTORE DUAL-PRODUCT ARCHITECTURE
# Версия v1.1 (S148 → S149 transition)
# Дата: 17.05.2026
═══════════════════════════════════════════════════════════════

# §28. SINGLE CODEBASE — DUAL PRODUCT АРХИТЕКТУРА

## 28.1 Концептуална рамка

**Pocket CFO** е personal finance tracker за свободни професии. **RunMyStore.AI** е retail management система. И двата продукта **споделят 80% от codebase-а** — един engine, два UI shell-а.

```
┌──────────────────────────────────────────────────────────┐
│              ОБЩ ENGINE (един codebase)                   │
│  ┌────────────────────────────────────────────────────┐  │
│  │ Voice → AI Parser → Money Movement → Analytics      │  │
│  │ Photo → Vision → Receipt Parse → Money Movement     │  │
│  │                                                      │  │
│  │ CORE TABLES:                                         │  │
│  │ - money_movements (универсална, replaces             │  │
│  │   cash_drawer_movements + sales)                     │  │
│  │ - tenants (с plan ENUM)                              │  │
│  │ - users (multi-role)                                 │  │
│  │ - ai_insights (унифицирано)                          │  │
│  │ - ai_topics_catalog (modules: cfo, retail, hybrid)   │  │
│  │ - categories (универсални)                           │  │
│  │ - goals (savings + reinvestment)                     │  │
│  │ - bank_accounts                                      │  │
│  │ - reconciliations                                    │  │
│  └────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────┘
              ▲                              ▲
              │                              │
   ┌──────────┴──────────┐         ┌────────┴──────────┐
   │ POCKET CFO          │         │ RunMyStore.AI     │
   │ (personal UI)       │         │ (retail UI)       │
   │                     │         │                   │
   │ Bottom nav:         │         │ Bottom nav:       │
   │ [AI] [Записи]      │         │ [AI] [Склад]     │
   │ [Анализ] [Цели]    │         │ [Финанси] [Прод.]│
   │                     │         │                   │
   │ Цена: €4.99/мес    │         │ €19/49/109        │
   │ tenants.plan='cfo'  │         │ start/pro/business│
   │ modules='finance'   │         │ +retail,multistore│
   │                     │         │                   │
   │ Personal categories │         │ Products+inventory│
   │ Operating profit    │         │ Sales+POS         │
   │ Net cash position   │         │ Deliveries        │
   └─────────────────────┘         └───────────────────┘
```

## 28.2 Tenant types и plans

Базата дискриминира потребители чрез `tenants.plan` ENUM. Всеки план отключва конкретни modules:

```sql
ALTER TABLE tenants
  ADD COLUMN plan ENUM('cfo','start','pro','business') DEFAULT 'cfo',
  ADD COLUMN modules_unlocked SET('finance','retail','multistore','b2b') 
    DEFAULT 'finance',
  ADD COLUMN profession_template VARCHAR(50) NULL,
  -- За CFO: 'driver','psychologist','freelancer','beautician','tutor',
  --         'instructor','craftsman','courier','small_trader'
  ADD COLUMN tenant_category VARCHAR(100) NULL,
  -- За retail: 'clothing','shoes','jewelry','accessories' (от biz-compositions)
  ADD COLUMN expected_monthly_income DECIMAL(10,2) NULL,
  ADD COLUMN ai_training_consent BOOLEAN DEFAULT FALSE,
  ADD COLUMN benchmark_optin BOOLEAN DEFAULT TRUE,
  ADD COLUMN service_currency VARCHAR(3) DEFAULT 'EUR';
```

**Plan → modules mapping (data-driven):**

```php
function getModulesForPlan(string $plan): array {
    return match($plan) {
        'cfo'      => ['finance'],
        'start'    => ['finance', 'retail'],
        'pro'      => ['finance', 'retail', 'multistore'],
        'business' => ['finance', 'retail', 'multistore', 'b2b'],
    };
}
```

**Upgrade path е безопасен** — всички данни остават, само се отключват нови modules. Pocket CFO user отваря магазин → upgrade на START → запазва историята си.

## 28.3 UI shell routing

Един и същ login + route. Bootstrap избира shell според `tenants.plan`:

```php
// index.php (entry point)
session_start();
$tenant = getTenant($_SESSION['tenant_id']);

if ($tenant->plan === 'cfo') {
    // Pocket CFO shell
    header('Location: cfo/home.php');
} else {
    // RunMyStore shell
    header('Location: life-board.php');
}
```

**Файлова структура:**

```
/var/www/runmystore/
├── cfo/                              ← НОВО — Pocket CFO shell
│   ├── home.php                      ← Personal dashboard
│   ├── records.php                   ← Money movements feed
│   ├── analysis.php                  ← Charts + insights
│   ├── goals.php                     ← Savings goals
│   ├── settings.php                  ← Profile, plan, consents
│   └── partials/
│       ├── home-voice-bar.php
│       ├── home-quick-stats.php
│       └── analysis-charts.php
│
├── life-board.php                    ← RunMyStore home (existing)
├── stats.php                         ← RunMyStore Финанси (existing)
├── partials/
│   ├── stats-finance.php             ← Sub-tab router
│   ├── stats-finance-profit.php      ← Phase B
│   ├── stats-finance-cashflow.php    ← Phase 8
│   ├── stats-finance-expenses.php    ← Phase 8
│   ├── stats-finance-controller.php  ← Phase 8 (НОВО, used by CFO too)
│   └── stats-finance-exports.php     ← Phase 8
│
├── lib/
│   ├── money-engine.php              ← НОВО — universal CRUD за movements
│   ├── voice-parser.php              ← НОВО — Gemini/Whisper integration
│   ├── photo-receipt-parser.php      ← НОВО — Vision API
│   ├── ai-engine.php                 ← Универсална (extends existing)
│   ├── ai-topics-loader.php          ← Универсална
│   ├── stats-formulas.php            ← Универсална (cash split, op profit)
│   ├── plan-gate.php                 ← Plan + module gating
│   └── currency.php                  ← Pure EUR (БГ вече в евро)
│
└── ai-topics-catalog.json            ← Extended: + cfo module topics
```

## 28.4 Reuse map от RunMyStore

Това което Pocket CFO **директно ползва** без промяна:

| RunMyStore компонент | Pocket CFO ползва | Промяна |
|---|---|---|
| Sacred Glass canon (CSS) | ✅ 100% | Никаква |
| Aurora background | ✅ 100% | Никаква |
| Header Тип Б | ✅ Adapted | "Сметки" вместо "ПРОДАЖБА" |
| Voice STT engine | ✅ 100% | Whisper paid за GDPR compliance |
| `ai_insights` table | ✅ 100% | + module='cfo' filter |
| `ai_topics_catalog` структура | ✅ 100% | + 30 нови CFO теми |
| Confidence routing (Закон №8) | ✅ 100% | Никаква |
| Audit trail (Закон №7) | ✅ 100% | Никаква |
| 3-layer caching | ✅ 100% | Никаква |
| Anti-repetition (`ai_shown`) | ✅ 100% | Никаква |
| `build-prompt.php` Layers | ✅ Subset | Само Layers 1, 4, 6, 7 (без 2A-2H retail) |
| Stripe Connect | ✅ 100% | Нов плановой product |
| i18n architecture | ✅ 100% | БГ-first, готов за RO/GR |
| Plan-based gating UI | ✅ 100% | Нов plan='cfo' |
| Onboarding wizard pattern | ✅ Adapted | Различни въпроси |
| Bichromatic light/dark | ✅ 100% | Никаква |
| Mobile-first 375px | ✅ 100% | Никаква |
| Currency formatting | ⚠ Pure EUR | Без двойна валута (БГ вече в €) |

**Не ползваме (retail-only):**
- ❌ Products + inventory
- ❌ Deliveries + suppliers
- ❌ Sales/POS логика
- ❌ B2B invoicing
- ❌ Z-reports
- ❌ Weather integration (освен за outdoor професии бъдеще)
- ❌ Wholesale logic

## 28.5 Architecture decision records (ADR)

### ADR-1: Single DB с tenant_id RLS

**Решение:** Един schema, един DB, разделяме потребителите чрез `tenant_id` колоната във всяка таблица.

**Алтернативи отхвърлени:**
- Separate DB per tenant — твърде скъпо, сложно DevOps
- Schema-per-tenant — не scale-ва над 100 tenants

**Confirmation:** Industry standard (Monarch, YNAB, Klarna ползват същия pattern). От Research 3.

### ADR-2: Voice — Whisper API paid tier, не Web Speech

**Решение:** OpenAI Whisper API на платена тарифа (zero-data retention в DPA).

**Алтернативи отхвърлени:**
- Web Speech API — изпраща аудио към Google без DPA = GDPR violation
- Self-hosted Whisper — изисква GPU, ROI едва над 10K users
- Free Whisper tier — използва данни за training, забранено за финансови данни

**Cost:** $0.006/min × 5 min/user/мес = **$0.03/user/мес**

### ADR-3: Photo receipt parsing — Gemini Vision

**Решение:** Gemini 2.5 Flash multimodal приема directly image + извежда structured JSON.

**Алтернативи отхвърлени:**
- Tesseract OCR — низка accuracy за БГ касови бележки
- AWS Textract — скъпо, не БГ-локализирано
- Google Cloud Vision — старо API, manual OCR

**Cost:** ~$0.001 per receipt × 30 receipts/мес = **$0.03/user/мес**

### ADR-4: Currency — pure EUR (без BGN dual display)

**Решение:** БГ е вече в евро (1.1.2026). Всички цени, transactions, exports — само в евро.

**Защо:** Двойната валута € + лв беше задължителна 8.8.2025 - 31.12.2026, но за нов продукт от 2026 нататък — само EUR опростява всичко.

### ADR-5: Anonymization за training, не pseudonymization

**Решение:** Voice transcripts → NER pipeline (изтрива имена, IBAN, локации) → anonymized текст за AI training.

**Защо:** Anonymized = извън GDPR обхвата. Pseudonymized = под GDPR. От Research 3.

## 28.6 Code reuse percentage

Реалистично, по обем код:

```
RunMyStore текущ codebase:           ~150 000 редa PHP + JS + CSS

POCKET CFO ще ползва директно:
  - lib/* (engine functions)              ~25 000 редa  ← REUSE
  - includes/* (UI partials)              ~12 000 редa  ← REUSE
  - CSS canon (DESIGN_SYSTEM)             ~8 000 редa   ← REUSE
  - Stripe + auth + i18n                  ~15 000 редa  ← REUSE
  - AI engine + topics                    ~20 000 редa  ← REUSE (+ нови теми)
                                          ─────────
  Общо REUSE:                            ~80 000 редa  (53%)

POCKET CFO нов code:
  - cfo/* нови php файлове                ~8 000 редa
  - voice-parser.php                      ~1 500 редa
  - photo-receipt-parser.php              ~1 200 редa
  - profession_templates seed             ~500 редa
  - 30 нови AI теми (catalog + functions) ~3 500 редa
  - Onboarding wizard CFO версия          ~2 000 редa
  - cfo settings + GDPR consent           ~1 500 редa
                                          ─────────
  Общо НОВО:                             ~18 200 редa  (12% extra)

Total:                                   ~98 200 редa за двата продукта
(vs ~150K + ~150K = 300K ако бяха отделни)
```

**Реална икономия: 67%.** Един codebase спестява ~200K реда maintainance.

---

# §29. ONBOARDING — DUAL FLOW

## 29.1 Shared entry point

Един и същ landing page → разклонява се на стъпка 2.

```
landing.runmymoney.bg или landing.runmystore.bg
         ↓
    Single signup form
    (email + парола + телефон)
         ↓
    EMAIL VERIFICATION
         ↓
    STEP 1: "Какво искаш да правиш?"
    ┌─────────────────────────────────┐
    │ ● Личните си финанси            │  → plan='cfo'
    │   (свободна практика, фрилансер,│
    │    или просто следя харчовете)  │
    │                                 │
    │ ● Магазин                       │  → plan='start' (default)
    │   (имам физически магазин или    │
    │    онлайн продажби)             │
    └─────────────────────────────────┘
         ↓
    Различни pathways оттук нататък
```

## 29.2 POCKET CFO Onboarding (3 minutes)

```
STEP 1: Какво правиш? (избран)
   ↓
STEP 2: Каква е твоята професия / дейност?
   
   ┌─────────────────────────────────────────┐
   │ Свободни професии:                      │
   │                                         │
   │ 💻 IT/Дизайнер/Маркетолог              │
   │ ✂️ Козметичка/Фризьорка/Маникюрист    │
   │ 🛵 Куриер (Glovo/Speedy/Еконт)         │
   │ 🔧 Майстор (електро/ВиК/климатик)      │
   │ 🚕 Uber/Bolt/Такси                     │
   │ 📚 Частен учител                       │
   │ 🧠 Психолог/Терапевт                   │
   │ 🧘 Йога/Фитнес инструктор             │
   │ 🌱 Земеделски производител             │
   │ ─────────────                           │
   │ 👤 Просто следя личните си харчове    │
   │    (не работя на свободна практика)     │
   │ ─────────────                           │
   │ ⚙️ Друго (опиши кратко)                │
   └─────────────────────────────────────────┘
   
   → Запазва се в tenants.profession_template

   ↓

STEP 3: Какъв е средният ти месечен приход?
   (за персонализирани съвети — никой друг не вижда)
   
   [Slider]  €500 ─────●─────── €5000+
            (default base на template)
   
   ✓ €0-500
   ✓ €500-1000
   ✓ €1000-2000
   ✓ €2000-3500
   ✓ €3500-5000
   ✓ €5000+
   ✓ Не искам да казвам
   
   → Запазва се в tenants.expected_monthly_income

   ↓

STEP 4: Първи запис (демо)
   
   ┌─────────────────────────────────────────┐
   │ Опитай гласовия запис сега:             │
   │                                         │
   │ [🎤] Натисни и кажи:                    │
   │                                         │
   │ "Взех 50 лева за обяд"                  │
   │  или                                    │
   │ "Получих 300 евро от клиент"            │
   │                                         │
   │ AI ще го разпознае и запише.            │
   │                                         │
   │ [Прескочи] [Опитай сега]                │
   └─────────────────────────────────────────┘

   ↓

STEP 5: Privacy & Consent
   
   ┌─────────────────────────────────────────┐
   │ Преди да започнем:                      │
   │                                         │
   │ ☑ Съгласен съм с Правилата за ползване │
   │   и Политиката за поверителност        │
   │   (задължително)                        │
   │                                         │
   │ ☐ Съгласен съм моите анонимизирани    │
   │   данни да се ползват за подобряване    │
   │   на AI препоръките                     │
   │   (опционално, може да оттеглиш         │
   │   по всяко време от Настройки)          │
   │                                         │
   │ ☑ Съгласен съм да получавам         │
   │   персонализирани сравнения с други     │
   │   потребители от моята професия         │
   │   (минимум 5 души в групата)            │
   │                                         │
   │ [Продължи] [Назад]                      │
   └─────────────────────────────────────────┘
   
   ↓
   
STEP 6: Free trial
   
   ┌─────────────────────────────────────────┐
   │ 🎁 7 ДНИ БЕЗПЛАТНО                      │
   │                                         │
   │ Достъп до всичко:                       │
   │ ✓ Неограничени гласови записи           │
   │ ✓ Снимки на касови бележки              │
   │ ✓ Месечни и годишни анализи             │
   │ ✓ Цели и спестявания                    │
   │ ✓ Експорт за счетоводител               │
   │                                         │
   │ След 7 дни: €4.99/мес или €34.99/год  │
   │            (42% по-евтино с годишен)   │
   │                                         │
   │ Можеш да откажеш по всяко време.       │
   │ Без карта — само email потвърждение.   │
   │                                         │
   │ [Започни →]                             │
   └─────────────────────────────────────────┘
```

## 29.3 RunMyStore Onboarding (existing, unchanged)

Това вече е built в `onboarding.php` (existing). 4 стъпки според MASTER_TRACKER:
1. Име на магазин + категория
2. Локация + площ
3. Брой потребители + роли
4. Plan избор (START/PRO/BUSINESS)

## 29.4 Cross-product migration (CFO → Start)

Сценарий: Pocket CFO user реши да отвори физически магазин.

```
[В Settings на CFO]
   ↓
"Искам да добавя магазин"
   ↓
ВНИМАНИЕ: Това променя плана от €4.99/мес на €19/мес
   ↓
Onboarding STEP 2: Каква категория магазин?
   (същия flow като нов RunMyStore user)
   ↓
Onboarding STEP 3: Колко магазина имаш?
   ↓
UPGRADE:
   - tenants.plan: 'cfo' → 'start'
   - tenants.modules_unlocked: 'finance' → 'finance,retail'
   - Stripe billing update
   - Запазват се ВСИЧКИ existing money_movements
   - Личните транзакции продължават да live в Контролер sub-tab
   - Новите бизнес транзакции отиват в Финанси таб
   ↓
След upgrade: 
   - Bottom nav преминава от CFO към RunMyStore version
   - Личните harчове видими в "Контролер" sub-tab
   - Бизнес harчове в "Разходи" sub-tab
```

**Key insight:** Никаква data migration. Същата `money_movements` таблица, само различен UI shell.

## 29.5 Professional templates

Всеки от 9-те templates preconfigure-ва:
- Default categories
- Tax mode hints (за UI hints, не за advice)
- Expected expense patterns
- Income frequency expectations

### Template: 💻 IT/Дизайнер/Маркетолог

```php
[
    'name' => 'it_freelancer',
    'name_bg' => 'IT/Дизайнер/Маркетолог',
    'default_categories' => [
        ['name_bg' => 'Клиентски проекти', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Заплати/договори', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Кафе/обяд работа', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Софтуер/абонаменти', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Хардуер/техника', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Курс/конференция', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Транспорт лично', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Хранителни', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Развлечения', 'type' => 'expense', 'is_business' => false],
    ],
    'income_pattern' => 'irregular_monthly',  // от 1 до 5 големи плащания
    'typical_payment_methods' => ['bank_transfer'],
    'currency_default' => 'EUR',  // могат да имат USD/EUR клиенти
    'expected_income_range' => [2300, 5620],  // от Research 1
    'ai_hints' => [
        'income_variance_alert' => 'Доходите на IT freelancer варират значително',
        'recommend_reserve_months' => 6,  // по-голям резерв заради variance
    ],
]
```

### Template: ✂️ Козметичка/Фризьорка/Маникюрист

```php
[
    'name' => 'beautician',
    'name_bg' => 'Козметичка/Фризьорка/Маникюрист',
    'default_categories' => [
        ['name_bg' => 'Клиенти кеш', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Клиенти карта/Revolut', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Наем работно място', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Козметика/материали', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Инструменти', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Хранителни', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Транспорт лично', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Облекло/обувки', 'type' => 'expense', 'is_business' => false],
    ],
    'income_pattern' => 'daily_micro',  // много малки трансакции на ден
    'typical_payment_methods' => ['cash', 'card'],
    'expected_income_range' => [1020, 2300],
    'ai_hints' => [
        'cash_heavy_warning' => 'Високо ниво на кеш — внимание с reconciliation',
        'recommend_daily_tracking' => true,
    ],
]
```

### Template: 🛵 Куриер (Glovo/Speedy/Еконт)

```php
[
    'name' => 'courier',
    'name_bg' => 'Куриер',
    'default_categories' => [
        ['name_bg' => 'Поръчки Glovo', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Поръчки Speedy', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Поръчки Еконт', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Бонуси', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Гориво', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Поддръжка кола/мотор', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Амортизация', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Телефон/Data', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Храна по време работа', 'type' => 'expense', 'is_business' => false],
    ],
    'income_pattern' => 'weekly_aggregate',  // обикновено weekly settlement
    'typical_payment_methods' => ['bank_transfer'],
    'expected_income_range' => [920, 1630],
    'ai_hints' => [
        'fuel_ratio_track' => true,
        'recommend_fuel_pct_of_income' => 15,  // ако >25% = inefficient
        'weather_aware' => true,  // прогноза от weather-cache.php
    ],
]
```

### Template: 🔧 Майстор (електро/ВиК/климатик)

```php
[
    'name' => 'craftsman',
    'name_bg' => 'Майстор/Сервиз',
    'default_categories' => [
        ['name_bg' => 'Услуги клиенти', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Спешни поправки', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Материали/части', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Инструменти', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Гориво транспорт', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Реклама OLX/FB', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Хранителни', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Развлечения', 'type' => 'expense', 'is_business' => false],
    ],
    'income_pattern' => 'per_job',  // 1-3 големи плащания на седмица
    'typical_payment_methods' => ['cash', 'bank_transfer'],
    'expected_income_range' => [1790, 3580],
    'ai_hints' => [
        'materials_pct_track' => true,
        'recommend_materials_pct' => 30,  // ако >50% = lossymaking
        'cash_heavy' => true,
    ],
]
```

### Template: 🚕 Uber/Bolt/Такси

```php
[
    'name' => 'driver',
    'name_bg' => 'Uber/Bolt/Такси шофьор',
    'default_categories' => [
        ['name_bg' => 'Пътувания Uber', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Пътувания Bolt', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Бонуси платформа', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Гориво', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Сервиз кола', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Гуми/масло', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Застраховка КАСКО', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Платена цена кола (leasing)', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Хранителни', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Дом/семейство', 'type' => 'expense', 'is_business' => false],
    ],
    'income_pattern' => 'daily_high_variance',
    'typical_payment_methods' => ['bank_transfer'],
    'expected_income_range' => [1280, 2300],
    'ai_hints' => [
        'fuel_critical_track' => true,
        'recommend_fuel_pct' => 25,  // ако >40% = лошо
        'depreciation_aware' => true,
    ],
]
```

### Template: 📚 Частен учител

```php
[
    'name' => 'tutor',
    'name_bg' => 'Частен учител',
    'default_categories' => [
        ['name_bg' => 'Уроци', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Курсове', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Учебни материали', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Софтуер (Zoom/Office)', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Транспорт до учебни места', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Хранителни', 'type' => 'expense', 'is_business' => false],
    ],
    'income_pattern' => 'seasonal',  // септ-юни peak, юли-авг slowdown
    'typical_payment_methods' => ['cash', 'bank_transfer', 'revolut'],
    'expected_income_range' => [770, 1790],
    'ai_hints' => [
        'seasonal_pattern' => 'school_year',
        'summer_reserve_alert' => true,  // напомняй за резерв преди лятото
    ],
]
```

### Template: 🧠 Психолог/Терапевт

```php
[
    'name' => 'psychologist',
    'name_bg' => 'Психолог/Терапевт',
    'default_categories' => [
        ['name_bg' => 'Сесии частни клиенти', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Корпоративни клиенти', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Наем кабинет', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Супервизия (професионално развитие)', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Книги/курсове', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Хранителни', 'type' => 'expense', 'is_business' => false],
    ],
    'income_pattern' => 'recurring_weekly',  // клиенти ходят редовно
    'typical_payment_methods' => ['cash', 'bank_transfer'],
    'expected_income_range' => [1020, 2300],
    'ai_hints' => [
        'fixed_clients_track' => true,
        'recommend_session_count_target' => 'weekly',
    ],
]
```

### Template: 🧘 Йога/Фитнес инструктор

```php
[
    'name' => 'instructor',
    'name_bg' => 'Йога/Фитнес инструктор',
    'default_categories' => [
        ['name_bg' => 'Зала 1 - часове', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Зала 2 - часове', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Частни клиенти', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Workshops/Retreats', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Облекло спортно', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Сертификации/обучения', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Транспорт между зали', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Хранителни', 'type' => 'expense', 'is_business' => false],
    ],
    'income_pattern' => 'weekly_classes',
    'typical_payment_methods' => ['cash', 'bank_transfer', 'revolut'],
    'expected_income_range' => [770, 1530],
    'ai_hints' => [
        'multi_location' => true,
        'seasonal_pattern' => 'jan_resolutions',  // janurari peak
    ],
]
```

### Template: 🌱 Земеделски производител

```php
[
    'name' => 'farmer',
    'name_bg' => 'Земеделски производител',
    'default_categories' => [
        ['name_bg' => 'Продажби пазар', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Продажби директни', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Субсидии ДФ', 'type' => 'income', 'is_business' => true],
        ['name_bg' => 'Семена/разсад', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Тор/препарати', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Гориво техника', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Заплати помощници', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Транспорт стока', 'type' => 'expense', 'is_business' => true],
        ['name_bg' => 'Хранителни', 'type' => 'expense', 'is_business' => false],
    ],
    'income_pattern' => 'highly_seasonal',
    'typical_payment_methods' => ['cash', 'bank_transfer'],
    'expected_income_range' => [770, 2040],
    'ai_hints' => [
        'seasonal_extreme' => true,
        'subsidy_track' => true,
        'weather_critical' => true,
    ],
]
```

### Template: 👤 Личен бюджет

```php
[
    'name' => 'personal',
    'name_bg' => 'Личен бюджет',
    'default_categories' => [
        ['name_bg' => 'Заплата', 'type' => 'income', 'is_business' => false],
        ['name_bg' => 'Други доходи', 'type' => 'income', 'is_business' => false],
        ['name_bg' => 'Хранителни', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Транспорт', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Сметки (ток/вода/инет)', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Наем/ипотека', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Развлечения', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Ресторанти', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Здраве', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Облекло', 'type' => 'expense', 'is_business' => false],
        ['name_bg' => 'Подаръци', 'type' => 'expense', 'is_business' => false],
    ],
    'income_pattern' => 'monthly_fixed',
    'typical_payment_methods' => ['bank_transfer', 'card'],
    'expected_income_range' => [500, 5000],
    'ai_hints' => [
        'recommend_50_30_20' => true,  // правило 50/30/20
    ],
]
```

## 29.6 Custom template

Опция "⚙️ Друго" → user пише кратко описание → AI генерира personalized categories през Gemini Flash:

```php
function generateCustomCategories(string $profession_description, string $lang = 'bg'): array {
    $prompt = <<<PROMPT
Потребител описва своята професия: "{$profession_description}"

Генерирай 6-10 категории за финансово проследяване в JSON формат.

Изисквания:
- Категории за приходи (поне 2)
- Категории за разходи (поне 4 бизнес + 3 лични)
- Имена на български
- За всяка маркирай дали е бизнес (true) или лично (false)
- Реалистично за БГ контекст

Output ONLY valid JSON array. Example:
[
  {"name_bg": "Услуги клиенти", "type": "income", "is_business": true},
  {"name_bg": "Гориво", "type": "expense", "is_business": true},
  ...
]
PROMPT;

    return parseGeminiJSON(callGemini($prompt));
}
```

User преглежда → може да edit-не → запазва.


# §30. MONEY_MOVEMENTS — UNIVERSAL CORE TABLE

## 30.1 Преди и сега

Преди (RunMyStore-only):
- `sales` (за продажби)
- `cash_drawer_movements` (planned)
- `expenses` (за разходи)
- `bank_transactions` (planned)

**Сега (унифицирано):**

ВСЯКО движение на пари (приход или разход) минава през **една таблица**:

```sql
CREATE TABLE money_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    
    -- === DIRECTION + AMOUNT ===
    direction ENUM('in', 'out') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    
    -- === LOCATION (where money lives) ===
    location ENUM('cash', 'bank', 'card', 'wallet', 'savings', 'crypto') NOT NULL,
    bank_account_id INT UNSIGNED NULL,        -- FK ако location='bank'
    store_id INT NULL,                         -- ако retail (NULL за CFO)
    
    -- === REASON ===
    reason ENUM(
      -- INCOME types
      'sale',                  -- retail: продажба
      'service_income',        -- personal: hourly клиент платил
      'platform_income',       -- Uber/Glovo/Bolt платформа
      'salary_received',
      'subscription_income',   -- monthly recurring
      'subsidy_received',      -- държавна помощ
      'gift_received',
      'refund_received',
      'owner_inject',          -- личен капитал → бизнес
      'transfer_in',           -- движение между сметки
      'other_income',
      
      -- OUTCOME types
      'supplier_payment',      -- retail: доставка
      'expense_payment',       -- ВСЕКИ разход (ток, наем, гориво, материали...)
      'personal_expense',      -- CFO: лични харчове (храна, развлечения)
      'salary_paid',           -- retail: заплати на персонал
      'tax_paid',
      'rent_paid',
      'utility_paid',
      'transfer_out',          -- движение между сметки
      'owner_withdrawal',      -- бизнес → лично
      'refund_given',
      'adjustment'             -- ръчна корекция
    ) NOT NULL,
    
    -- === CATEGORIZATION ===
    category_id INT UNSIGNED NULL,             -- FK to categories
    is_business BOOLEAN DEFAULT TRUE,           -- retail=true default
    business_pct DECIMAL(5,2) DEFAULT 100.00,  -- ако mixed (телефон 70% бизнес)
    
    -- === LINK ===
    related_type ENUM('sale','delivery','expense','invoice','goal','transfer','none') 
        DEFAULT 'none',
    related_id BIGINT NULL,
    
    -- === VOICE / AI ===
    input_method ENUM('voice','photo','manual','api','import') DEFAULT 'manual',
    voice_transcript TEXT NULL,
    ai_parsed JSON NULL,                       -- raw AI output
    ai_confidence DECIMAL(3,2) NULL,
    needs_review BOOLEAN DEFAULT FALSE,         -- ако confidence < 0.85
    
    -- === RECEIPTS ===
    receipt_photo_path VARCHAR(255) NULL,
    vendor_name VARCHAR(200) NULL,             -- от photo parse
    vendor_eik VARCHAR(20) NULL,                -- от photo parse (бг касова)
    note VARCHAR(500),
    
    -- === APPROVAL (multi-user retail) ===
    requires_approval BOOLEAN DEFAULT FALSE,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    
    -- === RECONCILIATION ===
    reconciliation_id BIGINT NULL,
    
    -- === TIMESTAMPS ===
    occurred_at DATETIME NOT NULL,             -- когато реално стана
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- === SOFT DELETE (GDPR right to erasure) ===
    is_deleted BOOLEAN DEFAULT FALSE,
    anonymized_at DATETIME NULL,
    
    INDEX idx_tenant_occurred (tenant_id, occurred_at),
    INDEX idx_tenant_reason (tenant_id, reason),
    INDEX idx_tenant_location (tenant_id, location, occurred_at),
    INDEX idx_user (user_id),
    INDEX idx_category (category_id),
    INDEX idx_related (related_type, related_id),
    INDEX idx_needs_review (tenant_id, needs_review),
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
```

## 30.2 Migration от съществуващите таблици

За RunMyStore tenants (start/pro/business), `money_movements` се popula-ва от existing `sales`, `expenses`, `stock_movements (sale type)`:

```sql
-- Migration script (run веднъж при upgrade)

-- 1. Sales → money_movements (income)
INSERT INTO money_movements (
    tenant_id, user_id, direction, amount, currency,
    location, store_id, reason, is_business,
    related_type, related_id, occurred_at, created_at
)
SELECT
    s.tenant_id,
    s.user_id,
    'in' AS direction,
    s.total AS amount,
    'EUR' AS currency,
    CASE 
      WHEN s.payment_method = 'cash' THEN 'cash'
      WHEN s.payment_method = 'card' THEN 'bank'
      ELSE 'bank'
    END AS location,
    s.store_id,
    'sale' AS reason,
    TRUE AS is_business,
    'sale' AS related_type,
    s.id AS related_id,
    s.created_at AS occurred_at,
    s.created_at AS created_at
FROM sales s
WHERE s.status = 'completed';

-- 2. Expenses → money_movements (out) [когато таблицата съществува]
-- ... аналогично

-- 3. Stock movements (sale type) — НЕ дублираме, имаме ги от sales
```

**Backward compatibility:** `sales` и `expenses` таблиците остават като **views** или като **детайл** таблици. money_movements е универсалният summary.

## 30.3 Categories таблица (унифицирана)

```sql
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    parent_id INT UNSIGNED NULL,
    
    name VARCHAR(100) NOT NULL,
    name_bg VARCHAR(100),
    icon VARCHAR(10),                          -- emoji или icon name
    color_hue INT,                              -- 0-360 за UI hue
    
    type ENUM('income','expense','transfer') NOT NULL,
    is_business BOOLEAN DEFAULT FALSE,
    is_fixed BOOLEAN DEFAULT FALSE,             -- recurring (наем/абонамент)
    
    -- За custom categories от user
    is_system BOOLEAN DEFAULT FALSE,            -- preset от template
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    
    -- За AI matching (по време на voice parse)
    keywords JSON,                              -- ["обяд","хапка","нахраних"]
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_type (tenant_id, type, is_active),
    FOREIGN KEY (parent_id) REFERENCES categories(id)
);
```

При onboarding с template → се seed-ват default категориите (виж §29.5).

## 30.4 Reconciliations table

```sql
CREATE TABLE reconciliations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    
    -- Scope
    type ENUM('cash','bank','total') NOT NULL,
    bank_account_id INT UNSIGNED NULL,         -- ако type='bank'
    store_id INT NULL,                          -- ако retail
    
    -- Period
    period_start DATETIME NOT NULL,
    period_end DATETIME NOT NULL,
    
    -- Calculated values
    opening_balance DECIMAL(12,2) NOT NULL,
    total_in DECIMAL(12,2) NOT NULL,
    total_out DECIMAL(12,2) NOT NULL,
    expected_close DECIMAL(12,2) NOT NULL,
    actual_close DECIMAL(12,2) NULL,            -- manual count
    diff DECIMAL(12,2) GENERATED ALWAYS AS (actual_close - expected_close) STORED,
    
    -- Metadata
    performed_by INT NOT NULL,
    performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    
    INDEX idx_tenant_period (tenant_id, period_end),
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id)
);
```

## 30.5 Goals table

```sql
CREATE TABLE goals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    
    name VARCHAR(200) NOT NULL,
    type ENUM('savings','reserve','reinvestment','custom') DEFAULT 'savings',
    
    target_amount DECIMAL(12,2) NOT NULL,
    current_amount DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'EUR',
    
    deadline_date DATE NULL,                    -- optional
    
    -- Linked savings account
    savings_account_id INT UNSIGNED NULL,
    
    -- AI hints
    monthly_recommended DECIMAL(10,2) NULL,     -- AI calc от income vs expenses
    
    status ENUM('active','paused','achieved','abandoned') DEFAULT 'active',
    achieved_at DATETIME NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_user (tenant_id, user_id),
    INDEX idx_status (status)
);
```

## 30.6 Bank accounts (existing, но reuse)

Същата структура от RunMyStore Bible §6.2 M-008. Pocket CFO просто я ползва без промяна.

## 30.7 Helper functions

```php
// lib/money-engine.php

/**
 * Insert money movement (universal)
 * @param array $data — all relevant fields
 * @return int — inserted ID
 */
function insertMoneyMovement(array $data): int {
    // Validation
    if (!isset($data['amount']) || $data['amount'] <= 0) {
        throw new \InvalidArgumentException('Amount must be positive');
    }
    
    // Default occurred_at to now
    $data['occurred_at'] = $data['occurred_at'] ?? date('Y-m-d H:i:s');
    
    // Confidence routing
    if (isset($data['ai_confidence']) && $data['ai_confidence'] < 0.85) {
        $data['needs_review'] = true;
    }
    
    // Build INSERT
    $sql = "INSERT INTO money_movements (...) VALUES (...)";
    $id = DB::insert($sql, $data);
    
    // Invalidate cache
    invalidateMovementCache($data['tenant_id']);
    
    // Trigger AI insights re-compute (async)
    queueInsightRecompute($data['tenant_id'], $data['reason']);
    
    return $id;
}

/**
 * Get current balance for location
 */
function getBalance(int $tenant_id, string $location, ?int $bank_account_id = null): float {
    $sql = "SELECT 
              SUM(CASE WHEN direction='in' THEN amount ELSE -amount END) AS balance
            FROM money_movements
            WHERE tenant_id = ?
              AND location = ?
              AND is_deleted = FALSE";
    
    $params = [$tenant_id, $location];
    
    if ($bank_account_id) {
        $sql .= " AND bank_account_id = ?";
        $params[] = $bank_account_id;
    }
    
    return DB::query($sql, $params)->fetchColumn() ?: 0;
}

/**
 * Working capital split (cash drawer)
 */
function calcCashSplit(int $tenant_id, ?int $store_id = null): array {
    $total = getBalance($tenant_id, 'cash');
    
    $working_capital = 
        getUnpaidObligationsInDays($tenant_id, 7) +    // доставки + разходи дължими в 7д
        getAvgWeeklyExpenseObligation($tenant_id) * 1.5 +
        getFixedCostsMonthly($tenant_id) / 4;
    
    $free_money = max(0, $total - $working_capital);
    
    return [
        'total' => $total,
        'working_capital' => $working_capital,
        'free_money' => $free_money,
        'free_pct' => $total > 0 ? round($free_money / $total * 100) : 0,
        'is_overdrawn' => ($working_capital > $total),
    ];
}

/**
 * Operating profit (revenue - business expenses)
 */
function calcOperatingProfit(int $tenant_id, string $period_start, string $period_end): array {
    $income = DB::query(
        "SELECT SUM(amount) FROM money_movements
         WHERE tenant_id = ? AND direction = 'in'
           AND is_business = TRUE
           AND occurred_at BETWEEN ? AND ?
           AND is_deleted = FALSE",
        [$tenant_id, $period_start, $period_end]
    )->fetchColumn() ?: 0;
    
    $expenses = DB::query(
        "SELECT SUM(amount) FROM money_movements
         WHERE tenant_id = ? AND direction = 'out'
           AND is_business = TRUE
           AND occurred_at BETWEEN ? AND ?
           AND is_deleted = FALSE",
        [$tenant_id, $period_start, $period_end]
    )->fetchColumn() ?: 0;
    
    return [
        'income' => $income,
        'expenses' => $expenses,
        'operating_profit' => $income - $expenses,
        'margin_pct' => $income > 0 ? round(($income - $expenses) / $income * 100, 1) : 0,
    ];
}

/**
 * Net cash position (after personal withdrawals)
 */
function calcNetCashPosition(int $tenant_id, string $period_start, string $period_end): array {
    $op = calcOperatingProfit($tenant_id, $period_start, $period_end);
    
    $personal = DB::query(
        "SELECT SUM(amount) FROM money_movements
         WHERE tenant_id = ? AND direction = 'out'
           AND is_business = FALSE
           AND occurred_at BETWEEN ? AND ?
           AND is_deleted = FALSE",
        [$tenant_id, $period_start, $period_end]
    )->fetchColumn() ?: 0;
    
    return [
        'operating_profit' => $op['operating_profit'],
        'personal_withdrawals' => $personal,
        'net_cash_position' => $op['operating_profit'] - $personal,
        'burn_pct_of_income' => $op['income'] > 0 ? round($personal / $op['income'] * 100) : 0,
    ];
}
```

---

# §31. VOICE + PHOTO INPUT

## 31.1 Voice flow — полный pipeline

```
[User натиска 🎤]
        ↓
[Recording 0.5-30 sec]
        ↓
[STT — Whisper API (paid, GDPR-compliant)]
        ↓
[Transcript: "Взех 50 лева за обяд"]
        ↓
[Voice Parser — Gemini Flash structured output]
        ↓
[JSON parsed: {
    amount: 50,
    currency: 'BGN',
    direction: 'out',
    reason: 'personal_expense',
    category_guess: 'Хранителни',
    is_business: false,
    confidence: 0.93,
    transcript_clean: 'Взех 50 лева за обяд'
  }]
        ↓
[NER — извлича и изтрива PII]
[Transcript cleaned: "Взех [AMOUNT] за [CATEGORY]"]
        ↓
[Confirmation Card (2-4 sec auto-dismiss)]
        ↓
[INSERT money_movements]
        ↓
[Update cache + trigger AI insights]
        ↓
[Show in feed]
```

## 31.2 Photo receipt flow

```
[User натиска 📷]
        ↓
[Camera opens — preview frame для касова бележка]
        ↓
[Capture]
        ↓
[Photo uploaded — local /tmp/ → optional R2 storage]
        ↓
[Gemini 2.5 Flash Vision — structured output]
        ↓
[JSON parsed: {
    total: 23.45,
    currency: 'EUR',
    vendor_name: 'Билла',
    vendor_eik: '202103973',
    date: '2026-05-15',
    items: [
      {name: 'Хляб', price: 1.30},
      {name: 'Мляко', price: 2.85},
      ...
    ],
    vat_total: 3.91,
    confidence: 0.91
  }]
        ↓
[Confirmation Card with edit option]
        ↓
[User confirms or edits]
        ↓
[INSERT money_movements]
[Photo saved to GDPR-compliant storage (anonymized path)]
        ↓
[Show in feed]
```

## 31.3 STT engine selection

```php
// lib/voice-parser.php

class VoiceParser {
    private const WHISPER_API = 'https://api.openai.com/v1/audio/transcriptions';
    private const COST_PER_MIN = 0.006;  // USD
    
    /**
     * Transcribe audio file
     */
    public function transcribe(string $audio_path, string $lang = 'bg'): array {
        // Check audio duration
        $duration = $this->getAudioDuration($audio_path);
        
        if ($duration > 30) {
            throw new \Exception('Audio too long, max 30 seconds');
        }
        
        // Choose engine based on lang + cost optimization
        if ($lang === 'bg') {
            // Whisper API (paid tier) — GDPR compliant
            return $this->whisperAPI($audio_path, 'bg');
        } else {
            // Multi-lang support
            return $this->whisperAPI($audio_path, $lang);
        }
    }
    
    private function whisperAPI(string $audio_path, string $lang): array {
        $ch = curl_init(self::WHISPER_API);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . getenv('OPENAI_API_KEY'),
                'Content-Type: multipart/form-data',
            ],
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFile($audio_path),
                'model' => 'whisper-1',
                'language' => $lang,
                'response_format' => 'verbose_json',
                'temperature' => 0,  // deterministic
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new \Exception("Whisper API failed: HTTP $http_code");
        }
        
        $data = json_decode($response, true);
        
        return [
            'text' => $data['text'],
            'duration' => $data['duration'],
            'language' => $data['language'],
            'cost_usd' => $data['duration'] / 60 * self::COST_PER_MIN,
        ];
    }
    
    /**
     * Parse transcribed text into structured money movement
     */
    public function parseToMovement(
        string $transcript, 
        int $tenant_id,
        array $tenant_categories
    ): array {
        $categoriesJSON = json_encode($tenant_categories);
        
        $prompt = <<<PROMPT
Ти си AI парсер за финансово приложение. Анализирай това изречение на български
и върни СТРИКТЕН JSON.

Изречение: "{$transcript}"

Налични категории:
{$categoriesJSON}

Извлечи:
- amount (число)
- currency (EUR по подразбиране; "лева" → BGN converted to EUR by 1.95583; 
  "евро"→ EUR; "долара"→ USD)
- direction ("in" ако получено, "out" ако харчено)
- reason (от ENUM: sale, service_income, personal_expense, expense_payment, 
  supplier_payment, transfer_in, transfer_out, salary_received, salary_paid, 
  rent_paid, utility_paid, tax_paid, refund_received, refund_given, 
  owner_inject, owner_withdrawal, gift_received, other_income)
- category_id (ID от наличните категории, най-близка по смисъл)
- is_business (true ако е свързано с работа/клиенти; false ако лично)
- vendor_name (име на търговец/доставчик ако споменато, иначе null)
- confidence (0.0-1.0, твоята увереност в parse-а)

ВАЖНО:
- Ако не разпознаваш сумата → confidence < 0.5
- "Лева" винаги се конвертира в EUR
- Без guessing — ако не си сигурен, понижи confidence

Output ONLY valid JSON. No markdown, no explanation.
PROMPT;
        
        $response = $this->callGemini($prompt);
        return json_decode($response, true);
    }
    
    /**
     * Estimate cost for current usage
     */
    public function estimateMonthlyCost(int $tenant_id): float {
        // Average usage profile
        $voice_count = 100;
        $avg_duration_sec = 3;
        $total_minutes = ($voice_count * $avg_duration_sec) / 60;
        
        return $total_minutes * self::COST_PER_MIN;
    }
}
```

## 31.4 Photo receipt parser

```php
// lib/photo-receipt-parser.php

class PhotoReceiptParser {
    private const GEMINI_VISION_API = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    private const COST_PER_IMAGE = 0.001;  // USD approx
    
    public function parse(string $image_path): array {
        // Read image as base64
        $image_data = base64_encode(file_get_contents($image_path));
        $mime_type = mime_content_type($image_path);
        
        $prompt = <<<PROMPT
Анализирай тази снимка на касова бележка на български или европейски магазин.
Извлечи структурирана информация в JSON.

Извлечи:
- total (общата сума, число)
- currency (EUR по подразбиране за БГ касови след 1.1.2026)
- vendor_name (име на магазина/доставчика)
- vendor_eik (ЕИК ако видим, формат XXXXXXXXX или XXXXXXXXXXX)
- date (YYYY-MM-DD)
- time (HH:MM ако видимо)
- payment_method ('cash', 'card', 'transfer', или null)
- items (масив с {name, qty, price} ако видими, иначе празен)
- vat_total (общо ДДС ако видимо)
- vat_breakdown (масив {rate, base, vat} ако видимо)
- confidence (0.0-1.0)

ВАЖНО:
- БГ касови бележки имат "Общо" в края
- ЕИК е до 13 цифри обикновено
- Дата формат в БГ: DD.MM.YYYY (превърни в YYYY-MM-DD)
- Ако числата са замазани/нечетливи → confidence < 0.6

Output ONLY valid JSON.
PROMPT;
        
        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => [
                        'mime_type' => $mime_type,
                        'data' => $image_data
                    ]]
                ]
            ]],
            'generationConfig' => [
                'temperature' => 0,
                'responseMimeType' => 'application/json'
            ]
        ];
        
        $ch = curl_init(self::GEMINI_VISION_API . '?key=' . getenv('GEMINI_API_KEY'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 15,
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        return json_decode($text, true);
    }
    
    /**
     * Save receipt photo to GDPR-compliant storage
     */
    public function saveReceiptPhoto(string $temp_path, int $tenant_id): string {
        $year = date('Y');
        $month = date('m');
        
        // Path: /storage/receipts/2026/05/{tenant_id}/{uuid}.jpg
        $relative_path = "receipts/{$year}/{$month}/{$tenant_id}/" . 
                          generateUUID() . '.' . pathinfo($temp_path, PATHINFO_EXTENSION);
        $full_path = STORAGE_ROOT . '/' . $relative_path;
        
        // Ensure directory exists
        @mkdir(dirname($full_path), 0755, true);
        
        // Move and set perms
        rename($temp_path, $full_path);
        chmod($full_path, 0640);
        
        return $relative_path;
    }
}
```

## 31.5 NER pipeline (anonymization преди AI training)

```php
// lib/ner-anonymizer.php

class NERAnonymizer {
    private const REPLACEMENTS = [
        // Имена (capitalized words + БГ patterns)
        '/\\b[А-Я][а-я]+\\s+[А-Я][а-я]+\\b/u' => '[ИМЕ]',
        
        // IBAN (БГ formato BG + 22 chars)
        '/BG\\d{2}\\s?\\w{4}\\s?\\d{4}\\s?\\d{4}\\s?\\d{4}\\s?\\d{2}/' => '[IBAN]',
        
        // Кредитни карти (16 digits)
        '/\\b\\d{4}[\\s-]?\\d{4}[\\s-]?\\d{4}[\\s-]?\\d{4}\\b/' => '[КАРТА]',
        
        // Email
        '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}/' => '[ИМЕЙЛ]',
        
        // Телефонни номера БГ
        '/(\\+359|0)\\s?\\d{2}\\s?\\d{3}\\s?\\d{4}/' => '[ТЕЛЕФОН]',
        
        // ЕГН (10 digits with checksum pattern)
        '/\\b\\d{10}\\b/' => '[ЕГН]',
        
        // Адреси (улица + номер pattern)
        '/(ул\\.|улица|бул\\.|булевард)\\s+[А-Я][а-я]+(\\s+\\d+)?/iu' => '[АДРЕС]',
    ];
    
    public function anonymize(string $text): string {
        $clean = $text;
        foreach (self::REPLACEMENTS as $pattern => $replacement) {
            $clean = preg_replace($pattern, $replacement, $clean);
        }
        return $clean;
    }
    
    /**
     * Анonymize с keep important context
     * Например: запазваме "обяд", "гориво", "доставчик" — общи термини
     * Изтриваме конкретни идентификатори
     */
    public function anonymizeForTraining(string $text): array {
        $cleaned = $this->anonymize($text);
        
        // Допълнително: заменяме конкретни магазини с категории
        $vendor_categories = [
            'Билла' => '[СУПЕРМАРКЕТ]',
            'Кауфланд' => '[СУПЕРМАРКЕТ]',
            'Лидл' => '[СУПЕРМАРКЕТ]',
            'Метро' => '[ГОЛЯМ_МАГАЗИН]',
            'OMV' => '[БЕНЗИНОСТАНЦИЯ]',
            'Lukoil' => '[БЕНЗИНОСТАНЦИЯ]',
            'Shell' => '[БЕНЗИНОСТАНЦИЯ]',
            // ... extensible
        ];
        
        foreach ($vendor_categories as $vendor => $category) {
            $cleaned = str_replace($vendor, $category, $cleaned);
        }
        
        return [
            'original_hash' => hash('sha256', $text),  // за reference, не идентификация
            'anonymized_text' => $cleaned,
        ];
    }
}
```

## 31.6 Voice + Photo training data pipeline

```php
// lib/ai-training-pipeline.php

class AITrainingPipeline {
    /**
     * Process consented data for model improvement
     * Run nightly via cron
     */
    public function processNightly(): void {
        // Само от tenants със consent
        $tenants = DB::query(
            "SELECT id FROM tenants WHERE ai_training_consent = TRUE"
        )->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tenants)) return;
        
        foreach ($tenants as $tenant_id) {
            $movements = DB::query(
                "SELECT id, voice_transcript, ai_parsed, ai_confidence
                 FROM money_movements
                 WHERE tenant_id = ?
                   AND voice_transcript IS NOT NULL
                   AND created_at >= NOW() - INTERVAL 1 DAY
                   AND ai_confidence >= 0.85
                   AND is_deleted = FALSE",
                [$tenant_id]
            )->fetchAll();
            
            foreach ($movements as $m) {
                $anonymizer = new NERAnonymizer();
                $anonymized = $anonymizer->anonymizeForTraining($m['voice_transcript']);
                
                // Save to training corpus (S3 bucket, isolated)
                $this->saveToTrainingCorpus([
                    'date' => date('Y-m-d'),
                    'text' => $anonymized['anonymized_text'],
                    'expected_parse' => $m['ai_parsed'],
                    'confidence' => $m['ai_confidence'],
                ]);
            }
        }
        
        // Корпусът се ползва месечно за prompt tuning, не за weights training
    }
}
```


# §32. AI ADVISOR — 30 ТЕМИ БЕЗ ХАЛЮЦИНАЦИИ

## 32.1 Принцип "PHP смята, AI говори"

ВСЕКИ AI insight в Pocket CFO следва Закон №2 от RunMyStore: **числата идват от PHP/SQL, AI само форматира текста**. Това гарантира zero halucination.

```
WRONG (halucination риск):
prompt = "Анализирай харчовете на user X и дай съвет"
→ AI може да измисли цифри

RIGHT (zero halucination):
data = sqlQuery("SELECT SUM(amount) FROM money_movements WHERE...")
prompt = "Опиши тези факти в едно изречение БГ: {data}"
→ AI връща текст с РЕАЛНИТЕ числа
```

## 32.2 Три типа AI изхода

### Тип 1: ФАКТ
AI описва само това което виждаме в данните.

```
SQL: SELECT SUM(amount) WHERE direction='out' AND occurred_at >= NOW() - INTERVAL 7 DAY
Result: 380.50

Prompt: "Опиши: тази седмица харчиш €380.50"
Output: "Тази седмица харчиш €380.50."

Halucination risk: ZERO
```

### Тип 2: МАТЕМАТИЧЕСКО СРАВНЕНИЕ
AI сравнява две числа известни.

```
SQL #1: SUM(out) this_week = 380
SQL #2: SUM(in) this_week = 310
Calc:   delta = -70

Prompt: "Опиши: this_week_spend=€380, this_week_income=€310. Разлика €70."
Output: "Тази седмица харчиш €70 повече от колкото изкарваш."

Halucination risk: ZERO
```

### Тип 3: ОБЩИ ФИНАНСОВИ ПРИНЦИПИ
AI прилага общоприети правила (50/30/20, резерв 3-6 месеца).

```
SQL: savings_rate = 8%

Prompt: "User спестява 8%. Финансовите експерти препоръчват 20%. 
         Кратко обясни без conkretni съвети."
Output: "Спестяваш 8% от приходите. Финансовите експерти 
         препоръчват около 20%."

Halucination risk: LOW (principles са public knowledge)
```

## 32.3 Каталог: 30 AI ТЕМИ ЗА POCKET CFO

### КАТЕГОРИЯ A: SPENDING PATTERNS (10 теми)

#### A.1 — Burn rate alert (cfo_001)

```php
[
    'id' => 'cfo_001',
    'cat' => 'spending',
    'name_bg' => 'Харчиш повече отколкото изкарваш',
    'trigger' => function($context) {
        return $context['weekly_spend'] > $context['weekly_income'];
    },
    'sql' => "
        SELECT 
            (SELECT SUM(amount) FROM money_movements 
             WHERE tenant_id=:t AND direction='out' AND is_business=FALSE
               AND occurred_at >= NOW() - INTERVAL 7 DAY) AS weekly_spend,
            (SELECT SUM(amount) FROM money_movements 
             WHERE tenant_id=:t AND direction='in' AND is_business=FALSE
               AND occurred_at >= NOW() - INTERVAL 7 DAY) AS weekly_income
    ",
    'prompt_template' => <<<P
Опиши в едно изречение БГ, максимум 60 символа:
- Тази седмица харчиш: €{weekly_spend}
- Тази седмица получаваш: €{weekly_income}
- Разлика: €{delta}

Без съвети. Само факт + кратко "време за анализ".
P,
    'urgency' => 'warning',
    'cooldown_hours' => 168,  // 1 week
    'plan' => ['cfo','start','pro','business'],
]
```

#### A.2 — 3-месечен burn streak (cfo_002)

```php
[
    'id' => 'cfo_002',
    'cat' => 'spending',
    'name_bg' => '3 поредни месеца с burn',
    'trigger' => function($ctx) {
        return $ctx['consecutive_negative_months'] >= 3;
    },
    'sql' => "
        WITH monthly AS (
            SELECT 
                DATE_FORMAT(occurred_at, '%Y-%m') AS month,
                SUM(CASE WHEN direction='in' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN direction='out' THEN amount ELSE 0 END) AS spend
            FROM money_movements
            WHERE tenant_id = :t AND is_business = FALSE
              AND occurred_at >= NOW() - INTERVAL 6 MONTH
            GROUP BY DATE_FORMAT(occurred_at, '%Y-%m')
            ORDER BY month DESC
        )
        SELECT 
            COUNT(*) AS negative_count,
            (SELECT month FROM monthly WHERE income < spend ORDER BY month DESC LIMIT 1) AS last_red
        FROM monthly
        WHERE income < spend
    ",
    'prompt_template' => <<<P
User има {negative_count} поредни месеца с харчове > приходи.
Последен такъв: {last_red}.

Кратко (max 70 chars БГ): сигнализирай че време за анализ. 
Без специфични съвети. Спомени възможността да говори с финансов 
консултант (без да рекламираш конкретен).
P,
    'urgency' => 'critical',
    'cooldown_hours' => 336,  // 2 weeks
    'plan' => ['cfo','start','pro','business'],
]
```

#### A.3 — Category leak (cfo_003)

```php
[
    'id' => 'cfo_003',
    'cat' => 'spending',
    'name_bg' => 'Една категория e >30% от дохода',
    'sql' => "
        SELECT 
            c.name_bg AS category,
            SUM(mm.amount) AS cat_total,
            (SELECT SUM(amount) FROM money_movements 
             WHERE tenant_id=:t AND direction='in' AND is_business=FALSE
               AND occurred_at >= NOW() - INTERVAL 30 DAY) AS monthly_income,
            ROUND(SUM(mm.amount) / (SELECT SUM(amount) FROM money_movements 
                                     WHERE tenant_id=:t AND direction='in' 
                                       AND occurred_at >= NOW() - INTERVAL 30 DAY) * 100, 1) AS pct
        FROM money_movements mm
        JOIN categories c ON c.id = mm.category_id
        WHERE mm.tenant_id = :t
          AND mm.direction = 'out'
          AND mm.is_business = FALSE
          AND mm.occurred_at >= NOW() - INTERVAL 30 DAY
        GROUP BY mm.category_id
        HAVING pct > 30
        ORDER BY pct DESC
        LIMIT 1
    ",
    'prompt_template' => <<<P
Категория "{category}" е {pct}% от месечния доход на user.
Стойност: €{cat_total} от €{monthly_income} приход.

Кратко (max 70 chars БГ): кажи факта. 
Не давай съвет освен "наблюдавай тенденцията".
P,
    'urgency' => 'info',
    'plan' => ['cfo','start','pro','business'],
]
```

#### A.4 — Spending acceleration (cfo_004)

```php
[
    'id' => 'cfo_004',
    'cat' => 'spending',
    'name_bg' => 'Този месец харчиш с +20% повече',
    'sql' => "
        SELECT
            (SELECT SUM(amount) FROM money_movements 
             WHERE tenant_id=:t AND direction='out' 
               AND occurred_at >= DATE_SUB(NOW(), INTERVAL DAYOFMONTH(NOW())-1 DAY)) AS this_month,
            (SELECT SUM(amount)/3 FROM money_movements 
             WHERE tenant_id=:t AND direction='out'
               AND occurred_at >= NOW() - INTERVAL 4 MONTH
               AND occurred_at < DATE_SUB(NOW(), INTERVAL DAYOFMONTH(NOW())-1 DAY)) AS avg_3mo
    ",
    'trigger' => function($ctx) {
        return $ctx['this_month'] > $ctx['avg_3mo'] * 1.20;
    },
    'prompt_template' => <<<P
Този месец user харчи €{this_month}.
3-месечен avg: €{avg_3mo}.

Кратко (max 60 chars БГ): факт за повишение, без съвет.
P,
    'urgency' => 'info',
    'cooldown_hours' => 168,
]
```

#### A.5 — Restaurant ratio (cfo_005)

```php
[
    'id' => 'cfo_005',
    'cat' => 'spending',
    'name_bg' => 'Ресторанти > 15% от харчовете',
    'sql' => "
        SELECT 
            SUM(CASE WHEN c.name_bg IN ('Ресторанти','Хапки','Заведения') 
                THEN mm.amount ELSE 0 END) AS dining_out,
            SUM(mm.amount) AS total_spend,
            ROUND(SUM(CASE WHEN c.name_bg IN ('Ресторанти','Хапки','Заведения') 
                THEN mm.amount ELSE 0 END) / SUM(mm.amount) * 100, 1) AS pct
        FROM money_movements mm
        LEFT JOIN categories c ON c.id = mm.category_id
        WHERE mm.tenant_id = :t AND mm.direction = 'out'
          AND mm.occurred_at >= NOW() - INTERVAL 30 DAY
        HAVING pct > 15
    ",
    'prompt_template' => <<<P
User харчи €{dining_out} за ресторанти ({pct}% от месечните харчове).
Средно за хора от профила: 10-15%.

Кратко (max 70 chars БГ): факт + сравнение с benchmark.
P,
    'urgency' => 'info',
]
```

#### A.6 — Subscription creep (cfo_006)

```sql
SELECT category_id, COUNT(*) as cnt, SUM(amount) as total
FROM money_movements
WHERE tenant_id = :t 
  AND direction='out'
  AND reason='utility_paid'
  AND occurred_at >= NOW() - INTERVAL 30 DAY
  AND amount BETWEEN 3 AND 50  -- typical subscription range
GROUP BY category_id
HAVING cnt >= 5
```
**Trigger:** 5+ малки регулярни плащания в месеца.
**Output:** "Имаш {cnt} абонамента общо €{total}/мес. Прегледай ги."

#### A.7 — Impulse cluster (cfo_007)

```sql
SELECT DATE(occurred_at) AS day, COUNT(*) AS cnt, SUM(amount) AS daily_total
FROM money_movements
WHERE tenant_id = :t AND direction='out'
  AND occurred_at >= NOW() - INTERVAL 7 DAY
  AND amount BETWEEN 1 AND 30
GROUP BY DATE(occurred_at)
HAVING cnt >= 5
```
**Output:** "На {day}: {cnt} малки покупки = €{daily_total}. Импулс?"

#### A.8 — Cash leak (cfo_008)

```sql
SELECT 
    SUM(CASE WHEN location='cash' AND note IS NULL THEN amount ELSE 0 END) AS unlabeled_cash,
    SUM(amount) AS total
FROM money_movements
WHERE tenant_id = :t AND direction='out'
  AND occurred_at >= NOW() - INTERVAL 30 DAY
HAVING unlabeled_cash / total > 0.20
```
**Output:** "{pct}% от харчовете в кеш без описание. Добави бележки."

#### A.9 — Weekend spike (cfo_009)

```sql
SELECT
    SUM(CASE WHEN DAYOFWEEK(occurred_at) IN (1,7) THEN amount ELSE 0 END) AS weekend,
    SUM(amount) AS week_total
FROM money_movements
WHERE tenant_id = :t AND direction='out'
  AND occurred_at >= NOW() - INTERVAL 7 DAY
HAVING weekend / week_total > 0.50
```
**Output:** "60% от харчовете ти са в weekend."

#### A.10 — Day-of-week pattern (cfo_010)

Detect ако определен ден от седмицата има 2× по-високи харчове от средното.
**Output:** "Неделите харчиш 2× повече — навик за внимание."

---

### КАТЕГОРИЯ B: INCOME ANALYSIS (5 теми)

#### B.1 — Income drop (cfo_011)

```sql
SELECT
    (SELECT SUM(amount) FROM money_movements 
     WHERE tenant_id=:t AND direction='in' 
       AND occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS this_30d,
    (SELECT SUM(amount) FROM money_movements 
     WHERE tenant_id=:t AND direction='in'
       AND occurred_at BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) 
                            AND DATE_SUB(NOW(), INTERVAL 30 DAY)) AS prev_30d
```
**Trigger:** this_30d < prev_30d * 0.80
**Output:** "Този месец €{this_30d} vs миналия €{prev_30d} (-{delta}%)"

#### B.2 — Income consistency (cfo_012)

Variance check на monthly income.
**Trigger:** stddev(monthly_income) / avg_monthly_income > 0.40
**Output:** "Доходите варират {pct}% — нужен е по-голям резерв."

#### B.3 — Best month detected (cfo_013)

```sql
SELECT 
    DATE_FORMAT(occurred_at, '%Y-%m') AS month,
    SUM(amount) AS month_income
FROM money_movements
WHERE tenant_id=:t AND direction='in'
  AND occurred_at >= NOW() - INTERVAL 6 MONTH
GROUP BY DATE_FORMAT(occurred_at, '%Y-%m')
ORDER BY month_income DESC
LIMIT 1
```
**Trigger:** current_month == top_month и not_first_month_of_data
**Output:** "Това е най-силният ти месец за последните 6."

#### B.4 — Income source concentration (cfo_014)

```sql
SELECT vendor_name, SUM(amount) AS source_income, 
       SUM(amount)/total * 100 AS pct
FROM money_movements
WHERE tenant_id=:t AND direction='in'
  AND occurred_at >= NOW() - INTERVAL 90 DAY
GROUP BY vendor_name
ORDER BY pct DESC LIMIT 1
HAVING pct > 70
```
**Output:** "{pct}% от приходите идват от един източник ({vendor})."

#### B.5 — Day of income pattern (cfo_015)

Pattern detection — обикновено заплати на 1-2 число.
**Output:** "Парите идват на 1-2 число — планирай harчовете."

---

### КАТЕГОРИЯ C: CASH FLOW & RESERVE (5 теми)

#### C.1 — Free money split (cfo_016)

```php
$split = calcCashSplit($tenant_id);
// Output: 
// "Свободни: €{free_money}. Работен капитал: €{working_capital}."
```

#### C.2 — Reserve adequacy (cfo_017)

```php
$total_savings = getBalance($tenant_id, 'savings');
$monthly_expenses = getAvgMonthlyExpenses($tenant_id);
$reserve_months = $total_savings / $monthly_expenses;

// Trigger: reserve_months < 3
// Output: "Резерв: {months} месеца. Финансовите експерти 
//          препоръчват 3-6 месеца."
```

#### C.3 — Working capital warning (cfo_018) — само retail

```php
if ($cash_split['is_overdrawn']) {
    // Output: "Касата не покрива предстоящите плащания. €{shortage} разлика."
}
```

#### C.4 — Cash vs Bank ratio (cfo_019)

```php
$cash = getBalance($tenant_id, 'cash');
$bank = getBalance($tenant_id, 'bank');
$ratio = $cash / ($cash + $bank);

// Trigger: ratio > 0.50
// Output: "60% от парите ти са в кеш — обмисли да внасяш в банка."
```

#### C.5 — Surplus signal (cfo_020)

```php
$op = calcOperatingProfit($tenant_id, $month_start, $today);
$personal = getPersonalWithdrawals($tenant_id, $month_start, $today);
$surplus = $op['operating_profit'] - $personal;

// Trigger: end-of-month AND surplus > 0
// Output: "Този месец €{surplus} излишък."
```

---

### КАТЕГОРИЯ D: SAVINGS & GOALS (5 теми)

#### D.1 — Savings rate (cfo_021)

```sql
SELECT 
    SUM(CASE WHEN reason='transfer_to_savings' THEN amount ELSE 0 END) AS saved,
    SUM(CASE WHEN direction='in' THEN amount ELSE 0 END) AS income,
    ROUND(SUM(CASE WHEN reason='transfer_to_savings' THEN amount ELSE 0 END) 
          / SUM(CASE WHEN direction='in' THEN amount ELSE 0 END) * 100, 1) AS savings_rate
FROM money_movements
WHERE tenant_id=:t
  AND occurred_at >= NOW() - INTERVAL 30 DAY
```
**Output:** "Спестяваш {pct}% от приходите. Препоръка: 20%+."

#### D.2 — Goal progress (cfo_022)

Auto-track goals от `goals` таблица.
**Output:** "До '{goal_name}': още €{remaining} ({pct}% постигнато)."

#### D.3 — No savings 3 months (cfo_023)

```sql
SELECT COUNT(*) FROM money_movements 
WHERE tenant_id=:t AND reason='transfer_to_savings'
  AND occurred_at >= NOW() - INTERVAL 90 DAY
```
**Trigger:** count = 0
**Output:** "3 месеца без спестявания. Малки стъпки помагат."

#### D.4 — Goal achievable timeline (cfo_024)

```php
$goal = getGoal($goal_id);
$monthly_savings = getAvgMonthlySavings($tenant_id);
$months_left = ($goal['target'] - $goal['current']) / $monthly_savings;

// Output: "При €{monthly_savings}/мес ще достигнеш целта за {months} мес."
```

#### D.5 — Reinvest opportunity (cfo_025) — само за retail/CFO с business movements

```php
$surplus = calcMonthEndSurplus($tenant_id);
$tenant = getTenant($tenant_id);

if ($surplus > 100 && $tenant->modules_contains('retail')) {
    // Output: "Имаш €{surplus} излишък — обмисли реинвестиция в стока."
}
```

---

### КАТЕГОРИЯ E: COMPARISON & BENCHMARKS (5 теми)

ВАЖНО: Активират се само ако `tenants.benchmark_optin = TRUE` И има поне k=5 similar users.

#### E.1 — Professional cohort comparison (cfo_026)

```sql
-- Cohort: same profession_template, similar income range, last 30d
WITH cohort_metrics AS (
    SELECT
        AVG(monthly_expenses) AS avg_expenses,
        AVG(savings_rate) AS avg_savings_rate,
        COUNT(*) AS cohort_size
    FROM tenant_monthly_metrics
    WHERE profession_template = :tpl
      AND income_bucket = :bucket
      AND month = DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m')
)
SELECT * FROM cohort_metrics
WHERE cohort_size >= 5  -- k-anonymity
```

**Output:** "Куриери в твоя приходен диапазон харчат средно €{avg_expenses}. 
            Ти: €{my_expenses}."

#### E.2 — Geographic cohort (cfo_027)

Подобно но по `tenants.country + tenants.city`.

#### E.3 — Spending benchmarks (cfo_028)

Category-level comparison.
**Output:** "Транспорт: €{my_transport} (cohort avg €{cohort_transport})"

#### E.4 — Saving benchmarks (cfo_029)

**Output:** "Спестяваш {my_rate}% (cohort avg {cohort_rate}%)"

#### E.5 — Inflation tracker (cfo_030)

```sql
-- Compare same category 6 mo ago vs now
SELECT 
    (SELECT AVG(amount) FROM money_movements 
     WHERE tenant_id=:t AND category_id=:c
       AND occurred_at BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW()) AS now_avg,
    (SELECT AVG(amount) FROM money_movements 
     WHERE tenant_id=:t AND category_id=:c
       AND occurred_at BETWEEN DATE_SUB(NOW(), INTERVAL 7 MONTH) 
                            AND DATE_SUB(NOW(), INTERVAL 6 MONTH)) AS old_avg
```

**Output:** "Храна: +{pct}% за 6 месеца (типично 8%)."

---

## 32.4 AI Engine flow

```php
// lib/ai-engine.php

class AIEngine {
    private const TOPICS = [
        // 30 темите от §32.3
    ];
    
    /**
     * Compute insights for tenant (cron, hourly)
     */
    public function computeInsights(int $tenant_id): array {
        $tenant = getTenant($tenant_id);
        $context = $this->buildContext($tenant_id);
        $insights = [];
        
        // 1. Filter topics by plan + modules
        $eligible = array_filter(self::TOPICS, function($topic) use ($tenant) {
            return in_array($tenant->plan, $topic['plan']);
        });
        
        // 2. Filter by data availability
        $eligible = $this->filterByDataAvailability($eligible, $context);
        
        // 3. Filter by anti-repetition (ai_shown table)
        $eligible = $this->filterByAntiRepetition($eligible, $tenant_id);
        
        // 4. For each eligible, run trigger
        foreach ($eligible as $topic) {
            $data = DB::query($topic['sql'], [':t' => $tenant_id])->fetch();
            
            if (!$topic['trigger']($data)) continue;
            
            // 5. Generate AI text (or PHP fallback)
            $insight = $this->generateInsightText($topic, $data, $tenant);
            
            // 6. Confidence routing
            if ($insight['confidence'] < 0.50) continue;  // suppress
            
            // 7. Save to ai_insights
            $insight_id = DB::insert('ai_insights', [
                'tenant_id' => $tenant_id,
                'topic_id' => $topic['id'],
                'module' => 'cfo',
                'rendered_text' => $insight['text'],
                'urgency' => $topic['urgency'],
                'confidence' => $insight['confidence'],
                'retrieved_facts' => json_encode($data),
            ]);
            
            $insights[] = $insight;
        }
        
        return $insights;
    }
    
    private function generateInsightText(array $topic, array $data, $tenant): array {
        // Build prompt с реалните числа
        $prompt = $this->renderTemplate($topic['prompt_template'], $data);
        
        try {
            $response = $this->callGemini($prompt, [
                'temperature' => 0.3,  // ниска температура за consistency
                'max_tokens' => 100,
                'timeout' => 3,
            ]);
            
            return [
                'text' => trim($response),
                'confidence' => 0.90,  // PHP-driven numbers = high confidence
                'source' => 'ai',
            ];
        } catch (\Exception $e) {
            // PHP fallback
            return [
                'text' => $this->phpFallback($topic, $data),
                'confidence' => 1.0,
                'source' => 'php_fallback',
            ];
        }
    }
    
    /**
     * Build context for tenant
     */
    private function buildContext(int $tenant_id): array {
        return [
            'days_of_data' => $this->getDaysOfData($tenant_id),
            'movements_count' => $this->getMovementsCount($tenant_id),
            'has_business_movements' => $this->hasBusinessMovements($tenant_id),
            'has_goals' => $this->hasGoals($tenant_id),
            // ... 15-20 contextual flags
        ];
    }
}
```

## 32.5 Anti-repetition (Закон №7 от RunMyStore)

```sql
CREATE TABLE ai_shown (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    topic_id VARCHAR(50) NOT NULL,
    shown_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    cooldown_until DATETIME NOT NULL,
    INDEX idx_tenant_topic (tenant_id, topic_id, cooldown_until)
);
```

```php
function filterByAntiRepetition(array $topics, int $tenant_id): array {
    return array_filter($topics, function($topic) use ($tenant_id) {
        $last = DB::query(
            "SELECT cooldown_until FROM ai_shown
             WHERE tenant_id = ? AND topic_id = ?
               AND cooldown_until > NOW()
             ORDER BY shown_at DESC LIMIT 1",
            [$tenant_id, $topic['id']]
        )->fetchColumn();
        
        return $last === false;  // ако нямa active cooldown
    });
}
```

## 32.6 Cron schedule

```bash
# /etc/cron.d/runmystore-cfo

# Realtime insights — hourly
0 * * * * www-data php /var/www/runmystore/cron/cfo-insights-hourly.php

# Heavy insights — nightly
0 3 * * * www-data php /var/www/runmystore/cron/cfo-insights-heavy.php

# Monthly aggregations
0 4 1 * * www-data php /var/www/runmystore/cron/cfo-monthly-aggregates.php

# Cohort metrics (for benchmarks) — weekly
0 5 * * 0 www-data php /var/www/runmystore/cron/cfo-cohort-metrics.php
```

---

# §33. КОНТРОЛЕР SUB-TAB

## 33.1 Концепция

Контролер е специален UI който живее в **двата продукта**:
- **Pocket CFO:** като главна страница "Анализ"
- **RunMyStore:** като sub-tab в Финанси модул

Показва трите критични метрики:
1. **Working capital split** (cash split от §30.7)
2. **Operating Profit** (revenue - business expenses)
3. **Net Cash Position** (operating profit - personal withdrawals)

## 33.2 UI wireframe (375px)

```
┌─────────────────────────────────────────┐
│ КОНТРОЛЕР · Личен AI финансов съветник │  hero header
├─────────────────────────────────────────┤
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ ОБЩА КАСОВА НАЛИЧНОСТ          €2 400│ │  KPI hero
│ │                                      │ │
│ │ ┌────────────────────────────────┐  │ │
│ │ │ 🔒 Работен капитал    €1 680   │  │ │  q4 cyan
│ │ │    "Това НЕ е за тебе"         │  │ │
│ │ │    Доставки + наем (7д)         │  │ │
│ │ ├────────────────────────────────┤  │ │
│ │ │ 🟢 Свободни пари       €720    │  │ │  q3 green
│ │ │    "Можеш да харчиш"            │  │ │
│ │ └────────────────────────────────┘  │ │
│ │                                      │ │
│ │ ✨ "Имаш €720 свободни. Половината │ │
│ │   за себе си, половината резерв?"  │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ ТОЗИ МЕСЕЦ                          │ │
│ │                                      │ │
│ │ Приходи (Gross):       €4 237 ✓    │ │
│ │ ──────────────────                  │ │
│ │ − Бизнес разходи:      €2 800       │ │
│ │ ──────────────────                  │ │
│ │ = OPERATING PROFIT:    €1 437 ✓    │ │  q3 green
│ │                                      │ │
│ │ − Лични тегления:      €1 500 ⚠    │ │  q5 amber
│ │ ──────────────────                  │ │
│ │ = NET CASH POSITION:   −€63   ✗    │ │  q1 red
│ │                                      │ │
│ │ ✨ "Този месец гориш реално €63    │ │
│ │   повече отколкото оперативно       │ │
│ │   изкарваш."                        │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ 📊 6-МЕСЕЧЕН ТРЕНД                  │ │  line chart
│ │                                      │ │
│ │   €5K ╱╲    ╱╲                       │ │
│ │       ╱  ╲╱   ╲   ╱╲                 │ │  operating profit
│ │   €0  ────────────────              │ │  baseline
│ │       ╲╱╲           ╲╱               │ │  net position
│ │  −€1K                                │ │
│ │   Дек Яну Фев Мар Апр Май             │ │
│ │                                      │ │
│ │ ✨ "2 от последните 6 месеца си     │ │
│ │   гори. Резерв за криза: 1.2 мес." │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ 💸 ЛИЧНИ ТЕГЛЕНИЯ — ТОП КАТЕГОРИИ    │ │
│ │ Ресторанти      ████ €420  28%      │ │
│ │ Бензин личен    ███  €280  19%      │ │
│ │ Дрехи семейство ██   €180  12%      │ │
│ │ Подаръци        ██   €170  11%      │ │
│ │ Други           ██   €450  30%      │ │
│ │                                      │ │
│ │ ✨ "Ресторантите ти са 28% от       │ │
│ │   личните и 9.9% от месечната       │ │
│ │   ти печалба."                      │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ 🎯 РАЗПРЕДЕЛЕНИЕ                    │ │  bar chart
│ │ (По правило 50/30/20)               │ │
│ │                                      │ │
│ │       Income €4 237                 │ │
│ │ ████████████████████ 100%           │ │
│ │                                      │ │
│ │ Бизнес  €2 800        Лично €1 500  │ │
│ │ ████████████ 66%    ███████  35%    │ │
│ │                                      │ │
│ │ ⚠ Total burn 101% от income         │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ 💡 АВТОМАТИЧНО РАЗПРЕДЕЛЕНИЕ        │ │
│ │ (При свободни в края на месец)      │ │
│ │                                      │ │
│ │ Свободни сега: €720                 │ │
│ │                                      │ │
│ │ ┌──────────────────────────────┐    │ │
│ │ │ 🛍 Реинвестиция в стока 30%  │    │ │
│ │ │    €216                       │    │ │  само ако retail
│ │ ├──────────────────────────────┤    │ │
│ │ │ 💰 Резерв (emergency)    40%  │    │ │
│ │ │    €288 → спестовна сметка    │    │ │
│ │ │    Текущ резерв: 1.2 мес.     │    │ │
│ │ │    Цел: 3 мес. = €4 800       │    │ │
│ │ ├──────────────────────────────┤    │ │
│ │ │ 👤 За тебе                30% │    │ │
│ │ │    €216                       │    │ │
│ │ └──────────────────────────────┘    │ │
│ │                                      │ │
│ │ [Промени разпределението]            │ │
│ │ [Прехвърли в банка]                 │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

## 33.3 RBAC + Plan gating

| Audience | Контролер | Personal categorization |
|---|---|---|
| Pocket CFO user | ✅ Main page | ✅ Default mode |
| RunMyStore Owner | ✅ Sub-tab "Контролер" | ✅ Optional flag |
| RunMyStore Manager | ❌ Hidden | ❌ |
| RunMyStore Seller | ❌ Hidden | ❌ |

**За RunMyStore:** Контролер sub-tab е owner-only + PRO+. CFO users получават го по default.

## 33.4 Distribution Preferences

```sql
CREATE TABLE owner_distribution_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL UNIQUE,
    
    reinvestment_pct DECIMAL(5,2) DEFAULT 30.00,
    reserve_pct DECIMAL(5,2) DEFAULT 40.00,
    personal_pct DECIMAL(5,2) DEFAULT 30.00,
    
    reserve_target_months DECIMAL(3,1) DEFAULT 3.0,
    
    -- За retail
    auto_alert_threshold_eur DECIMAL(10,2) DEFAULT 100,
    seller_spend_limit_daily_eur DECIMAL(10,2) DEFAULT 100,
    
    -- За CFO
    notify_overdraft BOOLEAN DEFAULT TRUE,
    notify_burn_streak BOOLEAN DEFAULT TRUE,
    
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```


# §34. RUNMYSTORE → POCKET CFO REUSE MAP

Подробен mapping какво да копираме директно vs какво да адаптираме vs какво е нов код.

## 34.1 ✅ Direct reuse (без промяна)

| RunMyStore component | File path | Pocket CFO usage |
|---|---|---|
| Sacred Glass CSS canon | `partials/css/glass.css` | Same | 
| Aurora background | `partials/aurora.php` | Same |
| Bichromatic light/dark | `partials/css/theme.css` | Same |
| 6 hue класа (q1-q6) | `partials/css/hues.css` | Same |
| `priceFormat()` | `lib/currency.php` | Само € (БГ в евро) |
| `auth.php` | `lib/auth.php` | Same |
| `db.php` | `lib/db.php` | Same |
| Confidence routing | `lib/ai-routing.php` | Same |
| Audit trail (retrieved_facts) | `lib/audit-trail.php` | Same |
| Anti-repetition (ai_shown) | `lib/anti-repetition.php` | Same |
| Voice button styling | `includes/voice-button.php` | Same |
| Microphone permissions | `js/mic-permissions.js` | Same |
| Capacitor mobile shell | `mobile/capacitor.config.js` | Same |
| Stripe Connect subscription | `lib/stripe-connect.php` | Same (нов plan) |
| Email service | `lib/email.php` | Same |
| Push notifications | `lib/push.php` | Same |
| i18n framework | `lib/i18n.php` + `lang/*.json` | Same |
| `build-prompt.php` Layers 1, 4, 6, 7 | `build-prompt.php` | Same |

## 34.2 ⚠️ Adapted (нужна е промяна)

### Voice STT engine

**RunMyStore текущ stack:**
```
БГ price field → Web Speech API (free, instant)
Non-БГ → Groq Whisper API
```

**Pocket CFO change за GDPR compliance:**
```
БГ всеки voice input → OpenAI Whisper API (paid, zero-data retention)
Non-БГ → OpenAI Whisper API (paid)
NEVER Web Speech API (audio leaves device без DPA)
```

**Защо:** RunMyStore Web Speech API е valid за price-only (numbers без personal context). Pocket CFO записва free-form personal info (имена, адреси, лични харчове) — задължително zero-data retention.

**File промени:**
- `lib/voice-stt.php` → нова версия v2 само Whisper API
- `lib/voice-stt-legacy.php` → kept за RunMyStore Web Speech price fields

### Onboarding wizard

**RunMyStore wizard (existing 4 стъпки):**
1. Име на магазин + категория
2. Локация + площ
3. Брой потребители + роли
4. Plan избор

**Pocket CFO wizard (нов flow):**
1. Single signup form
2. "Какво искаш?" (personal/business)
3. Profession template (9 options)
4. Expected income range
5. Demo voice record
6. Privacy & consent
7. 7-day free trial

**File:** `cfo/onboarding.php` (нов) extends shell от `onboarding.php`.

### Bottom navigation

**RunMyStore:** [AI] [Склад] [Финанси] [Продажба] [Магазини*]

**Pocket CFO:** [AI] [Записи] [Анализ] [Цели]

**File:** `partials/bottom-nav.php` → дискриминира според `tenants.plan`:

```php
if ($tenant->plan === 'cfo') {
    require 'partials/bottom-nav-cfo.php';
} else {
    require 'partials/bottom-nav-retail.php';
}
```

### `build-prompt.php`

**Layers reused:**
- Layer 1: Identity + Rules ✅
- Layer 4: Business Signals ✅ (adapted за CFO context)
- Layer 6: Language ✅
- Layer 7: Topics ✅ (filter за module='cfo')

**Layers NOT used in CFO:**
- Layer 2A-2H: Retail-specific data (products, sales, suppliers, customers, sellers)
- Layer 3: Memory (нужно ще е, но не сега)
- Layer 5: Seasonal Context (само за outdoor професии)

**Адаптация:** `cfo/build-prompt-cfo.php` използва `lib/build-prompt-shared.php` (extract на shared logic) + добавя CFO-specific layers:

```
Layer CFO-2A: Last 30 days money_movements summary
Layer CFO-2B: Top categories breakdown
Layer CFO-2C: Income sources
Layer CFO-2D: Goals progress
Layer CFO-2E: Recent insights (от ai_insights WHERE module='cfo')
```

### `ai-topics-catalog.json`

**Current state:** 649 теми за RunMyStore (modules: home, products, warehouse, stats, sale).

**Promised:** + 30 нови теми с `module: 'cfo'` (от §32.3).

**File:** Same `ai-topics-catalog.json`, added entries.

```json
{
  "version": "1.1",
  "topics": [
    // ... existing 649 теми ...
    
    // НОВИ 30 CFO теми
    {
      "id": "cfo_001",
      "module": "cfo",
      "category": "spending",
      "name_bg": "Burn rate alert",
      "urgency": "warning",
      "plan": ["cfo", "start", "pro", "business"],
      "trigger_sql_id": "weekly_spend_vs_income",
      "cooldown_hours": 168
    },
    // ... cfo_002 to cfo_030
  ]
}
```

## 34.3 🆕 Нов код (специфичен за CFO)

### Нови файлове

```
/var/www/runmystore/
├── cfo/
│   ├── home.php                          ← Главна CFO страница
│   ├── records.php                       ← Money movements feed
│   ├── analysis.php                      ← Charts + AI insights
│   ├── goals.php                         ← Savings goals
│   ├── settings.php                      ← Profile, plan, GDPR consents
│   ├── onboarding.php                    ← CFO onboarding wizard
│   ├── partials/
│   │   ├── home-voice-bar.php            ← Always-visible voice button
│   │   ├── home-quick-stats.php          ← This month overview
│   │   ├── home-feed.php                 ← Recent movements list
│   │   ├── records-filter.php            ← Filter by category/period
│   │   ├── records-list.php              ← Paginated movements
│   │   ├── analysis-monthly.php          ← Monthly breakdown
│   │   ├── analysis-yearly.php           ← Annual view
│   │   ├── analysis-comparisons.php      ← Cohort benchmarks
│   │   ├── goals-active.php              ← Active goals
│   │   ├── goals-history.php             ← Achieved goals
│   │   ├── settings-profile.php          ← User info
│   │   ├── settings-categories.php       ← Manage categories
│   │   ├── settings-consent.php          ← GDPR controls
│   │   └── settings-plan.php             ← Subscription mgmt
│   └── api/
│       ├── voice-parse.php               ← Voice input endpoint
│       ├── photo-parse.php               ← Photo receipt endpoint
│       ├── movement-create.php           ← INSERT money_movements
│       ├── movement-update.php           ← Edit existing
│       ├── movement-delete.php           ← Soft delete + anonymize
│       └── insights-fetch.php            ← Get AI insights
│
├── lib/
│   ├── money-engine.php                  ← Universal money_movements CRUD
│   ├── voice-parser.php                  ← Whisper API + Gemini parse
│   ├── photo-receipt-parser.php          ← Gemini Vision
│   ├── ner-anonymizer.php                ← PII removal
│   ├── cohort-benchmarks.php             ← K-anonymity aggregations
│   ├── profession-templates.php          ← 9 templates seed
│   └── gdpr-compliance.php               ← Consent, deletion, export
│
└── cron/
    ├── cfo-insights-hourly.php           ← Realtime insights
    ├── cfo-insights-heavy.php            ← Heavy aggregations
    ├── cfo-monthly-aggregates.php        ← Month-end summary
    ├── cfo-cohort-metrics.php            ← Update cohort benchmarks
    ├── cfo-training-pipeline.php         ← Anonymize for AI training (opt-in only)
    └── cfo-gdpr-cleanup.php              ← Right to erasure execution
```

### Нови DB таблици (extended от §30)

- `money_movements` (универсална)
- `categories` (универсална)
- `reconciliations` (универсална)
- `goals` (нова за CFO)
- `owner_distribution_preferences` (универсална)
- `cohort_metrics` (нова за benchmarks)
- `consent_log` (GDPR audit trail)

### Нови AI теми

- 30 CFO теми в `ai-topics-catalog.json` (§32.3)
- 30 нови `selectXxx()` функции в `ai-engine.php`
- 30 prompt templates в `lib/ai-prompts/cfo/`

## 34.4 Estimated effort breakdown

```
DIRECT REUSE (0 effort):                    ~80 000 редa код
                                            ────────────────
ADAPTED COMPONENTS:
  Voice STT v2 (Whisper-only):             ~600 редa
  Onboarding CFO wizard:                   ~1 800 редa
  Bottom navigation CFO version:           ~250 редa
  build-prompt-cfo.php:                    ~800 редa
  ai-topics-catalog.json (+30):             ~200 редa
                                            ────────────────
                                          ~3 650 редa
NEW CODE:
  cfo/* (all pages + partials):            ~5 500 редa
  lib/money-engine.php:                    ~1 200 редa
  lib/voice-parser.php:                    ~1 500 редa
  lib/photo-receipt-parser.php:            ~1 100 редa
  lib/ner-anonymizer.php:                  ~400 редa
  lib/cohort-benchmarks.php:               ~600 редa
  lib/profession-templates.php:            ~700 редa
  lib/gdpr-compliance.php:                 ~500 редa
  ai-engine.php нови функции (30):         ~3 500 редa
  ai-prompts/cfo/* (30 templates):         ~1 500 редa
  cron/cfo-*.php (6 scripts):              ~1 800 редa
                                            ────────────────
                                          ~18 300 редa

TOTAL NEW + ADAPTED:                       ~21 950 редa
TOTAL CODEBASE (with reuse):              ~101 950 редa
```

**Реална икономия:** Ако строим CFO като отделен product → ~120K реда. С reuse → ~22K реда нов код. **82% икономия.**

## 34.5 Timeline

```
═══ ФАЗА B1 — JUNE 2026 (3 weeks) ═══
Week 1:
  - DB migrations (money_movements + dependencies)
  - lib/money-engine.php
  - cfo/ скелет (home, records, analysis, goals, settings)
  - profession-templates seed

Week 2:
  - voice-parser.php (Whisper integration)
  - photo-receipt-parser.php (Gemini Vision)
  - ner-anonymizer.php
  - Onboarding CFO wizard

Week 3:
  - 30 AI теми (selectXxx() functions + prompts)
  - Controller sub-tab UI
  - GDPR consent flows
  - Beta deploy

═══ ФАЗА B2 — JULY 2026 (2 weeks) ═══
Week 1:
  - Cohort benchmarks (k-anonymity infrastructure)
  - cron scripts (hourly, heavy, monthly)
  - AI training pipeline (anonymization)

Week 2:
  - Mobile Capacitor build
  - App Store + Google Play submission
  - Polish + user testing

═══ ФАЗА B3 — AUGUST 2026 (Public Launch) ═══
  - TikTok + Instagram ads
  - Press release
  - Influencer outreach
  - First 100 paying users target
```

---

# §35. GDPR ARCHITECTURE

## 35.1 Legal базисна структура

### Лица в системата

| Role | Description |
|---|---|
| **Data Controller** | RunMyStore.AI Ltd. (юридическо лице на Tihol) |
| **Data Subject** | Всеки tenant (user) |
| **Data Processor (под-обработващ)** | OpenAI (Whisper), Google (Gemini), Stripe |

### Legal basis за обработка

| Дейност | Legal basis (GDPR чл. 6) |
|---|---|
| Запис money_movements | (b) Performance of contract |
| Voice STT processing | (b) Performance of contract |
| Receipt photo OCR | (b) Performance of contract |
| AI insights generation | (b) Performance of contract |
| Anonymized benchmarking | (a) Consent (opt-in) |
| AI training pipeline | (a) Consent (separate opt-in) |
| Marketing emails | (a) Consent (separate) |
| Audit logs retention | (c) Legal obligation (БГ Закон) |

## 35.2 Consent management

```sql
CREATE TABLE consent_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    
    consent_type ENUM(
        'privacy_policy',         -- ТРЪБА (за account)
        'terms_of_service',       -- ТРЪБА
        'benchmarking',           -- Optional (k-anonymity comparisons)
        'ai_training',            -- Optional (anonymized data for prompt tuning)
        'marketing_emails',       -- Optional
        'product_updates',        -- Optional
        'data_export',            -- За data portability requests
        'data_deletion'           -- За right to erasure
    ) NOT NULL,
    
    action ENUM('granted','revoked') NOT NULL,
    
    consent_text_version VARCHAR(20),  -- За legal proof
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    
    granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,           -- ако consent има срок
    
    INDEX idx_tenant_type (tenant_id, consent_type, action)
);
```

## 35.3 Right to Erasure (чл. 17)

```php
// lib/gdpr-compliance.php

class GDPRCompliance {
    /**
     * Execute right to erasure
     * Per чл. 17 GDPR + Research 3 best practice:
     * - Hard delete personal identifiers
     * - Anonymize transaction data (preserves aggregate stats)
     */
    public function rightToErasure(int $tenant_id, int $user_id): array {
        DB::beginTransaction();
        
        try {
            // 1. Anonymize money_movements (NOT delete)
            DB::query("
                UPDATE money_movements
                SET 
                    voice_transcript = NULL,
                    receipt_photo_path = NULL,
                    vendor_name = NULL,
                    note = NULL,
                    ai_parsed = NULL,
                    user_id = -1,  -- de-link from user
                    anonymized_at = NOW(),
                    is_deleted = FALSE  -- keep for aggregate stats
                WHERE tenant_id = ?
            ", [$tenant_id]);
            
            // 2. Delete receipt photos from disk
            $photos = DB::query(
                "SELECT receipt_photo_path FROM money_movements WHERE tenant_id = ?",
                [$tenant_id]
            )->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($photos as $photo) {
                if ($photo && file_exists(STORAGE_ROOT . '/' . $photo)) {
                    unlink(STORAGE_ROOT . '/' . $photo);
                }
            }
            
            // 3. Hard delete user record
            DB::query("DELETE FROM users WHERE id = ?", [$user_id]);
            
            // 4. Hard delete tenant
            DB::query("DELETE FROM tenants WHERE id = ?", [$tenant_id]);
            
            // 5. Delete goals
            DB::query("DELETE FROM goals WHERE tenant_id = ?", [$tenant_id]);
            
            // 6. Delete consent_log (keep last entry for audit)
            DB::query("
                DELETE FROM consent_log 
                WHERE tenant_id = ?
                  AND id NOT IN (SELECT MAX(id) FROM consent_log WHERE tenant_id = ?)
            ", [$tenant_id, $tenant_id]);
            
            // 7. Cancel Stripe subscription
            $this->cancelStripeSubscription($tenant_id);
            
            // 8. Log final consent (audit)
            DB::insert('consent_log', [
                'tenant_id' => $tenant_id,
                'user_id' => $user_id,
                'consent_type' => 'data_deletion',
                'action' => 'granted',
                'granted_at' => date('Y-m-d H:i:s'),
            ]);
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Личните данни са анонимизирани. Account изтрит.',
            ];
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
    
    /**
     * Data portability (чл. 20)
     * Export all user data in machine-readable format
     */
    public function exportUserData(int $tenant_id): string {
        $data = [
            'export_date' => date('c'),
            'tenant_info' => DB::query("SELECT * FROM tenants WHERE id = ?", [$tenant_id])->fetch(),
            'movements' => DB::query("
                SELECT * FROM money_movements 
                WHERE tenant_id = ? AND is_deleted = FALSE
                ORDER BY occurred_at DESC
            ", [$tenant_id])->fetchAll(),
            'goals' => DB::query("SELECT * FROM goals WHERE tenant_id = ?", [$tenant_id])->fetchAll(),
            'categories' => DB::query("SELECT * FROM categories WHERE tenant_id = ?", [$tenant_id])->fetchAll(),
            'consents' => DB::query("SELECT * FROM consent_log WHERE tenant_id = ?", [$tenant_id])->fetchAll(),
        ];
        
        $filename = "user_data_export_{$tenant_id}_" . date('Ymd_His') . ".json";
        $path = STORAGE_ROOT . '/exports/' . $filename;
        
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $path;
    }
}
```

## 35.4 K-anonymity for benchmarks

```php
// lib/cohort-benchmarks.php

class CohortBenchmarks {
    const MIN_K = 5;  // Минимум 5 users в cohort (Research 3 confirm)
    
    public function getProfessionBenchmark(string $profession, string $metric): ?array {
        $cohort_size = DB::query("
            SELECT COUNT(*) FROM tenants 
            WHERE profession_template = ?
              AND benchmark_optin = TRUE
        ", [$profession])->fetchColumn();
        
        if ($cohort_size < self::MIN_K) {
            return null;  // Suppress for privacy
        }
        
        return DB::query("
            SELECT 
                AVG({$metric}) AS avg_value,
                COUNT(*) AS cohort_size
            FROM cohort_metrics
            WHERE profession_template = ?
              AND month = DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m')
        ", [$profession])->fetch();
    }
    
    /**
     * Aggregate metrics nightly (cron)
     */
    public function computeCohortMetrics(): void {
        // Only tenants with opt-in
        $tenants = DB::query("
            SELECT id, profession_template, expected_monthly_income
            FROM tenants 
            WHERE benchmark_optin = TRUE
        ")->fetchAll();
        
        foreach ($tenants as $tenant) {
            $income_bucket = $this->getIncomeBucket($tenant['expected_monthly_income']);
            $month = date('Y-m', strtotime('-1 month'));
            
            // Calc metrics for this tenant last month
            $metrics = $this->calcTenantMonthly($tenant['id'], $month);
            
            // Insert anonymized aggregate
            DB::insert('tenant_monthly_metrics', [
                'tenant_id_hash' => hash('sha256', $tenant['id']),  // pseudonymized
                'profession_template' => $tenant['profession_template'],
                'income_bucket' => $income_bucket,
                'month' => $month,
                'monthly_expenses' => $metrics['expenses'],
                'savings_rate' => $metrics['savings_rate'],
                'top_category' => $metrics['top_category'],
                // ... other metrics
            ]);
        }
    }
}
```

## 35.5 AI training data anonymization

```php
// cron/cfo-training-pipeline.php
// Run nightly

$pipeline = new AITrainingPipeline();

// Only tenants със consent
$consented = DB::query("
    SELECT id FROM tenants WHERE ai_training_consent = TRUE
")->fetchAll(PDO::FETCH_COLUMN);

foreach ($consented as $tenant_id) {
    $movements = DB::query("
        SELECT id, voice_transcript, ai_parsed, ai_confidence
        FROM money_movements
        WHERE tenant_id = ?
          AND voice_transcript IS NOT NULL
          AND occurred_at >= NOW() - INTERVAL 1 DAY
          AND ai_confidence >= 0.85
          AND is_deleted = FALSE
    ", [$tenant_id])->fetchAll();
    
    foreach ($movements as $m) {
        $anonymizer = new NERAnonymizer();
        $clean = $anonymizer->anonymizeForTraining($m['voice_transcript']);
        
        // Save to isolated training corpus (S3, no read access from prod)
        saveToTrainingCorpus([
            'date' => date('Y-m-d'),
            'text_hash' => $clean['original_hash'],  // не original text!
            'anonymized_text' => $clean['anonymized_text'],
            'expected_parse' => $m['ai_parsed'],
        ]);
    }
}
```

## 35.6 Data residency

**Confirmed:** DigitalOcean Frankfurt, EU region.

| Data type | Location | Compliance |
|---|---|---|
| MySQL DB | DO Frankfurt | EU ✅ |
| Receipt photos | DO Spaces Frankfurt | EU ✅ |
| AI training corpus | AWS S3 eu-central-1 | EU ✅ |
| Backups | DO Spaces Frankfurt | EU ✅ |
| Logs | DO Frankfurt | EU ✅ |
| Stripe payments | Stripe EU | EU ✅ |
| OpenAI Whisper | OpenAI US | DPA covers (SCCs) ✅ |
| Google Gemini | Google US | DPA covers (SCCs) ✅ |

**SCCs (Standard Contractual Clauses)** са вписани в DPA-овете с OpenAI Stripe и Google за всеки трансфер извън ЕС.

## 35.7 DPO requirement check

Според Research 3:
- DPO задължителен при > 10 000 users в БГ.
- Pocket CFO startup → < 10K за първите 6-12 месеца.
- Действие: **Назначи DPO при достигане на 8 000 users** (буфер).

Cost: External DPO service в БГ ~€2 500-5 000/year. Affordable когато имаме >5K users.

## 35.8 Privacy policy & ToS structure

(За юрист да направи финални версии)

**Privacy Policy задължителни секции:**
1. Кой сме (data controller info)
2. Какви данни събираме (and why)
3. Legal basis за всеки тип обработка
4. Кога делим данни и с кого (Stripe, OpenAI, Google)
5. Колко дълго пазим (retention periods)
6. Вашите права (access, erasure, portability, objection)
7. Cookies и tracking
8. Сигурност мерки
9. Trans-border transfers (SCCs)
10. DPO contact / supervisory authority

**Terms of Service задължителни:**
1. Service description
2. Subscription & payment
3. Trial period (7 дни)
4. Cancellation
5. Acceptable use
6. AI disclaimer ("AI не дава финансови съвети")
7. Limitations of liability
8. Governing law (БГ)

---

# §36. PRICING STRATEGY (FINAL)

## 36.1 Plan matrix

```
┌─────────────────┬─────────┬──────────┬──────────┬───────────┐
│ Feature         │ POCKET  │  START   │   PRO    │ BUSINESS  │
│                 │  CFO    │          │          │           │
├─────────────────┼─────────┼──────────┼──────────┼───────────┤
│ Monthly price   │ €4.99   │  €19     │  €49     │  €109     │
│ Annual price    │ €34.99  │  €159    │  €399    │  €899     │
│ Annual savings  │  42%    │  30%     │  32%     │  31%      │
├─────────────────┼─────────┼──────────┼──────────┼───────────┤
│ Voice records   │ ∞       │ ∞        │ ∞        │ ∞         │
│ Photo receipts  │ ∞       │ ∞        │ ∞        │ ∞         │
│ Categories      │ ∞       │ ∞        │ ∞        │ ∞         │
│ Personal track  │ ✅      │ ✅       │ ✅       │ ✅        │
│ Goals + savings │ ✅      │ ✅       │ ✅       │ ✅        │
│ Контролер       │ ✅      │ ❌       │ ✅       │ ✅        │
│ Cohort benchmark│ ✅      │ ❌       │ ✅       │ ✅        │
├─────────────────┼─────────┼──────────┼──────────┼───────────┤
│ Products inventory │ ❌   │ ✅       │ ✅       │ ✅        │
│ POS / Sales     │ ❌      │ ✅       │ ✅       │ ✅        │
│ 1 магазин       │ ❌      │ ✅       │ ✅       │ ✅        │
│ Multi-store     │ ❌      │ ❌       │ ✅ (3)   │ ✅ (∞)    │
│ Deliveries      │ ❌      │ ❌       │ ✅       │ ✅        │
│ B2B Invoicing   │ ❌      │ ❌       │ ❌       │ ✅        │
│ Wholesale       │ ❌      │ ❌       │ ❌       │ ✅        │
│ Accountant exp. │ Basic   │ Basic    │ Full     │ Full + API│
├─────────────────┼─────────┼──────────┼──────────┼───────────┤
│ Users included  │ 1       │ 1        │ 3        │ ∞         │
│ Trial period    │ 7 дни   │ 14 дни   │ 14 дни   │ 30 дни    │
└─────────────────┴─────────┴──────────┴──────────┴───────────┘
```

## 36.2 Trial structure

**Hard paywall с 7-day trial:**
- Immediately след install/signup
- 78% conversion rate (vs 45% freemium) — Research 2
- Card not required upfront for CFO (email enough)
- Reminder на ден 5: "2 дни остават от безплатния период"

**Reverse trial fallback:**
- Ако не плати на ден 7 → НЕ блокираме напълно
- Преминава към lite mode:
  - 15 voice records / month max
  - 5 photo receipts / month max
  - Charts работят (без AI insights)
  - "Upgrade за пълни функции" prompt

**Защо reverse trial:**
- Запазва user-а в системата (не churn-ва напълно)
- Възможност за later conversion при life event (нов магазин, увеличаване на разходи)
- LTV remains positive (Research 2)

## 36.3 Upgrade paths

```
POCKET CFO (€4.99) → 
    AUTO triggers за upgrade:
    1. User записва > 5 retail-related движения/седмица
       (купуване на стока за препродажба, доставки, т.н.)
       → Suggest: "Изглежда продаваш стока. START план €19 ти дава 
         склад, POS, и продажби."
    
    2. User казва "магазин", "стока", "клиенти" 5+ пъти в voice
       → Suggest: "Изглежда имаш магазин. Опитай START."
    
    3. User add-ва >50 категории с retail context
       → Suggest START upgrade.

START (€19) →
    Triggers за PRO upgrade:
    1. > 80 продукта в inventory
    2. Иска multi-store
    3. Иска deliveries module

PRO (€49) →
    Triggers за BUSINESS:
    1. > 4-ти потребител
    2. B2B клиенти с invoicing
    3. Wholesale customer
```

## 36.4 Lifetime offer (limited)

**Strategy:** Не предлагаме отначало. Запазваме за марketing pushes.

**When activated:**
- Black Friday (последен петък ноември)
- New Year promotion
- Productehunt launch day
- 1 year anniversary

**Offer:**
- €99 Lifetime Pocket CFO
- 100 spots only per promotion
- Fair Use Policy:
  - 200 voice records / month max (anti-abuse)
  - 100 photo receipts / month max
- Same features as monthly subscriber

**Research 2 finding:** Lifetime offers ARE sustainable IF rate-limited (Fair Use Policy). Без это могат да fail-нат при continued AI costs.

## 36.5 Revenue projections

### Conservative scenario (B1 launch + 12 months)

```
Month 1-2:    50 paid users      → €250 MRR
Month 3-4:   200 paid users      → €1 000 MRR
Month 5-6:   500 paid users      → €2 500 MRR
Month 7-9:  1 500 paid users     → €7 500 MRR
Month 10-12: 3 500 paid users    → €17 500 MRR

End of year 1:    €210K ARR
```

### Realistic scenario (with proper marketing)

```
Month 1-2:    300 paid users     → €1 500 MRR
Month 3-4:   1 000 paid users    → €5 000 MRR
Month 5-6:   2 500 paid users    → €12 500 MRR
Month 7-9:   5 000 paid users    → €25 000 MRR
Month 10-12: 8 350 paid users    → €41 750 MRR

End of year 1:    €500K ARR (target пазар 5% conversion)
```

### Aggressive scenario (viral TikTok)

```
End of year 1:   20 000 paid users → €100K MRR → €1.2M ARR
```

## 36.6 Cost structure per user

```
PER USER MONTH:
  Revenue (Pocket CFO):           €4.99
  - Apple/Google fee 15%:        -€0.75
  - ДДС VAT 20%:                 -€0.83
  - Stripe fee 2.9% + €0.30:     -€0.44
  - OpenAI Whisper:              -€0.03
  - Gemini Vision (receipts):    -€0.03
  - DigitalOcean infrastructure: -€0.05
  - Support tools:               -€0.10
  ────────────────────────────────────
  NET MARGIN:                     €2.76 (55%)
```

**LTV/CAC:**
- Average customer lifetime (LT): 18 months
- LTV = €2.76 × 18 = **€49.68**
- CAC (BG TikTok ad): **€10-15**
- LTV/CAC ratio: **3.3-5.0x** (healthy, Research 1 confirms)

---

# 🏁 КРАЙ НА BIBLE V1.1

**Bible v1.1 финализиран. Готов за код-имплементация.**

**Общ обем:** ~8800 редa (от 5371 → 8800, +3500 нови)

**Покрити секции:**
- §1-§27 (original v1.0 Stats+Finance Bible)
- **§28** — Dual-product architecture (Pocket CFO + RunMyStore)
- **§29** — Onboarding (9 професионални templates)
- **§30** — money_movements universal table
- **§31** — Voice + Photo input (Whisper + Gemini Vision)
- **§32** — 30 AI теми с zero halucination
- **§33** — Контролер sub-tab (UI + logic)
- **§34** — RunMyStore → CFO reuse map
- **§35** — GDPR architecture
- **§36** — Pricing strategy

**Следваща стъпка:** Mockups + код implementation.

═══════════════════════════════════════════════════════════════
# ETAP 6 — POCKET CFO v1.2: SELF-LEARNING + ENRICHMENTS
# Версия v1.2 (S148 → S149)
# Дата: 17.05.2026
═══════════════════════════════════════════════════════════════

# §37. SELF-LEARNING ENGINE

## 37.1 Принципът

Pocket CFO **намалява AI разходите** чрез непрекъснато учене. Първите 2 месеца — Gemini парсва 90% от voice inputs. След 6 месеца — PHP парсва 75% от inputs **без AI call**. 

Това намалява **AI cost от €0.10/user/мес → €0.03/user/мес** (66% спестено).

## 37.2 Vendor aliases — learn user vocabulary

Всеки tenant има уникален речник за свои vendors:

```sql
CREATE TABLE vendor_aliases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    
    canonical_name VARCHAR(200) NOT NULL,
    user_aliases JSON,                       -- ["Иван", "при Иван", "на Иван"]
    
    typical_category_id INT UNSIGNED NULL,
    typical_amount_avg DECIMAL(10,2) NULL,
    typical_amount_min DECIMAL(10,2) NULL,
    typical_amount_max DECIMAL(10,2) NULL,
    
    direction ENUM('in','out') NULL,        -- обикновено приход или разход
    is_business BOOLEAN DEFAULT TRUE,
    
    occurrences_count INT DEFAULT 1,
    confidence DECIMAL(3,2) DEFAULT 0.50,   -- 0.0-1.0
    
    last_seen DATETIME,
    is_subscription BOOLEAN DEFAULT FALSE,
    
    -- Cross-tenant: ако е универсален (Билла, Netflix)
    is_universal BOOLEAN DEFAULT FALSE,
    promoted_to_universal_at DATETIME NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_tenant (tenant_id, canonical_name),
    INDEX idx_universal (is_universal, canonical_name),
    INDEX idx_aliases (tenant_id, (CAST(user_aliases AS CHAR(1000))))
);

-- Universal vendor knowledge (cross-tenant)
CREATE TABLE vendor_aliases_universal (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    canonical_name VARCHAR(200) NOT NULL UNIQUE,
    
    typical_category_hint VARCHAR(100),     -- "Супермаркет", "Бензиностанция"
    typical_amount_avg DECIMAL(10,2),
    
    detected_in_tenants INT DEFAULT 0,      -- колко users имат вендора
    confidence DECIMAL(3,2) DEFAULT 0.0,
    
    -- Categorization
    is_subscription BOOLEAN DEFAULT FALSE,
    is_bg_specific BOOLEAN DEFAULT FALSE,
    
    keywords JSON,                          -- alternative spellings
    -- ["Билла", "BILLA", "била", "biлla"]
    
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_canonical (canonical_name)
);
```

### Seed данни (БГ-specific универсални vendors):

```sql
INSERT INTO vendor_aliases_universal (canonical_name, typical_category_hint, is_subscription, is_bg_specific, keywords) VALUES
-- Супермаркети
('Билла', 'Хранителни', FALSE, TRUE, '["Билла","BILLA","била"]'),
('Кауфланд', 'Хранителни', FALSE, TRUE, '["Кауфланд","Kaufland"]'),
('Лидл', 'Хранителни', FALSE, TRUE, '["Лидл","LIDL"]'),
('Метро', 'Хранителни едрос търговия', FALSE, TRUE, '["Метро","METRO"]'),
('Фантастико', 'Хранителни', FALSE, TRUE, '["Фантастико","Fantastico"]'),
('T-Маркет', 'Хранителни', FALSE, TRUE, '["Т-маркет","T-Market"]'),

-- Бензиностанции
('OMV', 'Гориво', FALSE, TRUE, '["OMV","ОМВ"]'),
('Lukoil', 'Гориво', FALSE, TRUE, '["Lukoil","Лукойл"]'),
('Shell', 'Гориво', FALSE, TRUE, '["Shell","Шел"]'),
('Petrol', 'Гориво', FALSE, TRUE, '["Petrol","Петрол"]'),
('EKO', 'Гориво', FALSE, TRUE, '["EKO","ЕКО"]'),

-- Телекоми (subscription)
('А1', 'Телефон/Интернет', TRUE, TRUE, '["А1","A1","Мтел","МТЕЛ"]'),
('Yettel', 'Телефон/Интернет', TRUE, TRUE, '["Yettel","Йетел","Теленор"]'),
('Vivacom', 'Телефон/Интернет', TRUE, TRUE, '["Vivacom","Виваком","БТК"]'),

-- Streaming/SaaS (subscription)
('Netflix', 'Развлечения', TRUE, FALSE, '["Netflix","Нетфликс"]'),
('Spotify', 'Музика', TRUE, FALSE, '["Spotify","Спотифай"]'),
('YouTube Premium', 'Развлечения', TRUE, FALSE, '["YouTube Premium","ютуб"]'),
('HBO Max', 'Развлечения', TRUE, FALSE, '["HBO","ХБО"]'),
('Apple iCloud', 'Cloud storage', TRUE, FALSE, '["iCloud","Apple Storage","apple.com/bill"]'),
('Google One', 'Cloud storage', TRUE, FALSE, '["Google One","Google Storage"]'),
('Adobe', 'Софтуер', TRUE, FALSE, '["Adobe","Adobe Creative","Photoshop"]'),
('Microsoft 365', 'Софтуер', TRUE, FALSE, '["Microsoft","M365","Office 365"]'),
('Canva', 'Софтуер', TRUE, FALSE, '["Canva","Канва"]'),
('ChatGPT', 'AI tools', TRUE, FALSE, '["ChatGPT","OpenAI"]'),
('Claude', 'AI tools', TRUE, FALSE, '["Claude","Anthropic"]'),
('NordVPN', 'VPN', TRUE, FALSE, '["NordVPN","Nord","ВПН"]'),

-- Куриерски (за онлайн търговци)
('Еконт', 'Куриерска услуга', FALSE, TRUE, '["Еконт","Econt","еконт"]'),
('Спиди', 'Куриерска услуга', FALSE, TRUE, '["Спиди","Speedy","спиди"]'),
('БГ Пост', 'Куриерска услуга', FALSE, TRUE, '["БГ Пост","Bulgarian Post"]'),

-- Платформи (за freelancers/couriers)
('Glovo', 'Поръчки/Доходи', FALSE, TRUE, '["Glovo","Гльово"]'),
('Bolt', 'Транспорт/Доходи', FALSE, TRUE, '["Bolt","Болт"]'),
('Uber', 'Транспорт/Доходи', FALSE, FALSE, '["Uber","Юбер"]'),
('Foodpanda', 'Поръчки/Доходи', FALSE, TRUE, '["Foodpanda","Фуудпанда"]'),

-- Държавни (БГ)
('НАП', 'Данъци/Осигуровки', TRUE, TRUE, '["НАП","Национална Агенция","НОИ"]'),
('Топлофикация', 'Парно', TRUE, TRUE, '["Топлофикация","Toplofikacia"]'),
('ЕВН', 'Електричество', TRUE, TRUE, '["ЕВН","EVN","Електроснабдяване"]'),
('ЧЕЗ', 'Електричество', TRUE, TRUE, '["ЧЕЗ","CEZ"]'),
('Енерго-Про', 'Електричество', TRUE, TRUE, '["Енерго-Про","Energo-Pro"]'),
('Софийска Вода', 'Вода', TRUE, TRUE, '["Софийска Вода","Sofia Water"]'),

-- Хранителни вериги
('McDonald''s', 'Ресторанти', FALSE, FALSE, '["МакДоналдс","McDonald","Mac"]'),
('KFC', 'Ресторанти', FALSE, FALSE, '["KFC","КФС"]'),
('Subway', 'Ресторанти', FALSE, FALSE, '["Subway","Събуей"]'),
('Domino''s', 'Ресторанти', FALSE, FALSE, '["Domino","Доминос"]');
```

## 37.3 Category keywords — learn БГ language patterns

```sql
CREATE TABLE category_keywords (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,                     -- NULL = universal
    
    keyword_bg VARCHAR(100) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    
    confidence DECIMAL(3,2) DEFAULT 0.50,
    occurrences_count INT DEFAULT 1,
    
    context_modifier_business JSON NULL,    -- ["при доставчик","за работа"]
    context_modifier_personal JSON NULL,    -- ["за себе си","лично"]
    
    is_universal BOOLEAN DEFAULT FALSE,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_keyword (keyword_bg),
    INDEX idx_universal_kw (is_universal, keyword_bg)
);

-- Seed Universal БГ keywords:
INSERT INTO category_keywords (keyword_bg, category_id, confidence, is_universal) VALUES
-- Храна
('обяд', 'food_id', 0.95, TRUE),
('вечеря', 'food_id', 0.95, TRUE),
('закуска', 'food_id', 0.90, TRUE),
('хапка', 'food_id', 0.85, TRUE),
('кафе', 'food_id', 0.80, TRUE),
('ресторант', 'restaurant_id', 0.95, TRUE),
('заведение', 'restaurant_id', 0.85, TRUE),
('меню', 'restaurant_id', 0.80, TRUE),

-- Транспорт
('гориво', 'transport_id', 0.98, TRUE),
('бензин', 'transport_id', 0.98, TRUE),
('дизел', 'transport_id', 0.98, TRUE),
('такси', 'transport_id', 0.95, TRUE),
('паркинг', 'transport_id', 0.95, TRUE),
('винетка', 'transport_id', 0.99, TRUE),

-- Услуги (за себе си)
('фризьор', 'grooming_id', 0.95, TRUE),
('маникюр', 'grooming_id', 0.95, TRUE),
('педикюр', 'grooming_id', 0.95, TRUE),
('масаж', 'grooming_id', 0.90, TRUE),
('козметичка', 'grooming_id', 0.95, TRUE),

-- Бизнес материали
('материали', 'business_supplies_id', 0.85, TRUE),
('консуматив', 'business_supplies_id', 0.80, TRUE),
('софтуер', 'software_id', 0.90, TRUE),

-- Здраве
('лекар', 'health_id', 0.95, TRUE),
('зъбен', 'health_id', 0.90, TRUE),
('аптека', 'health_id', 0.95, TRUE),
('лекарство', 'health_id', 0.95, TRUE),
('преглед', 'health_id', 0.85, TRUE),

-- Семейство
('детска градина', 'childcare_id', 0.95, TRUE),
('училище', 'childcare_id', 0.85, TRUE),
('подарък', 'gifts_id', 0.85, TRUE),

-- Дом
('наем', 'housing_id', 0.98, TRUE),
('ток', 'utilities_id', 0.95, TRUE),
('вода', 'utilities_id', 0.85, TRUE),
('парно', 'utilities_id', 0.95, TRUE),
('интернет', 'utilities_id', 0.90, TRUE),
('боклук', 'utilities_id', 0.85, TRUE),

-- Финансови
('такса', 'bank_fees_id', 0.70, TRUE),
('теглене', 'bank_fees_id', 0.75, TRUE),
('лихва', 'financial_id', 0.85, TRUE);
```

## 37.4 PHP-first parsing — намалява AI calls

```php
// lib/voice-parser-v2.php

class VoiceParserV2 {
    
    /**
     * Главна функция за parsing с PHP-first approach
     * Връща parse result + дали AI беше викнат
     */
    public function parse(
        string $transcript, 
        int $tenant_id
    ): array {
        // 1. PHP-first: проверка дали можем да парснем без AI
        $php_result = $this->tryPhpParse($transcript, $tenant_id);
        
        if ($php_result['confidence'] >= 0.85) {
            // PHP се справи → НЯМА AI call
            $this->trackPhpHit($tenant_id, $transcript);
            return [
                'result' => $php_result,
                'source' => 'php_only',
                'cost_usd' => 0,
            ];
        }
        
        // 2. AI fallback
        $ai_result = $this->callGeminiParse($transcript, $tenant_id);
        
        // 3. Update learning data от AI result
        $this->learnFromAIResult($tenant_id, $transcript, $ai_result);
        
        return [
            'result' => $ai_result,
            'source' => 'gemini',
            'cost_usd' => 0.001,  // approx
        ];
    }
    
    /**
     * PHP-only parser — без AI
     */
    private function tryPhpParse(string $transcript, int $tenant_id): array {
        $result = [
            'amount' => null,
            'direction' => null,
            'currency' => 'EUR',
            'category_id' => null,
            'vendor_name' => null,
            'reason' => null,
            'confidence' => 0,
        ];
        
        // 1. Extract amount (REGEX)
        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(лева|лв|евро|€|euro|долара|usd|\$)/iu', $transcript, $m)) {
            $amount = (float) str_replace(',', '.', $m[1]);
            $currency_raw = strtolower($m[2]);
            
            if (in_array($currency_raw, ['лева','лв'])) {
                $amount = $amount / 1.95583;  // BGN → EUR
                $result['currency'] = 'EUR';
            } elseif (in_array($currency_raw, ['евро','€','euro'])) {
                $result['currency'] = 'EUR';
            } elseif (in_array($currency_raw, ['долара','usd','$'])) {
                $result['currency'] = 'USD';
            }
            
            $result['amount'] = round($amount, 2);
            $result['confidence'] += 0.30;
        }
        
        // 2. Direction (income/outcome)
        $income_keywords = ['получих','взех','спечелих','платиха ми','дадоха ми','клиент'];
        $outcome_keywords = ['платих','купих','дадох','харчих','за','платих за'];
        
        $transcript_lower = mb_strtolower($transcript);
        
        foreach ($income_keywords as $kw) {
            if (mb_strpos($transcript_lower, $kw) !== false) {
                $result['direction'] = 'in';
                $result['confidence'] += 0.20;
                break;
            }
        }
        
        if (!$result['direction']) {
            foreach ($outcome_keywords as $kw) {
                if (mb_strpos($transcript_lower, $kw) !== false) {
                    $result['direction'] = 'out';
                    $result['confidence'] += 0.20;
                    break;
                }
            }
        }
        
        // 3. Vendor matching (DB lookup)
        $vendor = $this->matchVendor($transcript, $tenant_id);
        if ($vendor) {
            $result['vendor_name'] = $vendor['canonical_name'];
            $result['category_id'] = $vendor['typical_category_id'];
            $result['confidence'] += 0.30;
            
            // Inherit direction от vendor history
            if (!$result['direction'] && $vendor['direction']) {
                $result['direction'] = $vendor['direction'];
                $result['confidence'] += 0.10;
            }
        }
        
        // 4. Category keyword matching (ако не от vendor)
        if (!$result['category_id']) {
            $category = $this->matchCategoryByKeyword($transcript, $tenant_id);
            if ($category) {
                $result['category_id'] = $category['id'];
                $result['confidence'] += 0.25;
            }
        }
        
        // 5. Reason derivation
        if ($result['direction'] && $result['category_id']) {
            $result['reason'] = $this->deriveReason(
                $result['direction'], 
                $result['category_id'],
                $tenant_id
            );
        }
        
        return $result;
    }
    
    /**
     * Match vendor от tenant + universal таблици
     */
    private function matchVendor(string $transcript, int $tenant_id): ?array {
        // 1. Tenant-specific aliases (highest priority)
        $tenant_vendors = DB::query(
            "SELECT * FROM vendor_aliases 
             WHERE tenant_id = ?",
            [$tenant_id]
        )->fetchAll();
        
        foreach ($tenant_vendors as $v) {
            $aliases = json_decode($v['user_aliases'], true) ?: [];
            $aliases[] = $v['canonical_name'];
            
            foreach ($aliases as $alias) {
                if (mb_stripos($transcript, $alias) !== false) {
                    return $v;
                }
            }
        }
        
        // 2. Universal vendors
        $universal_vendors = DB::query(
            "SELECT * FROM vendor_aliases_universal"
        )->fetchAll();
        
        foreach ($universal_vendors as $v) {
            $keywords = json_decode($v['keywords'], true) ?: [$v['canonical_name']];
            
            foreach ($keywords as $kw) {
                if (mb_stripos($transcript, $kw) !== false) {
                    return [
                        'canonical_name' => $v['canonical_name'],
                        'typical_category_id' => $this->getCategoryByHint($v['typical_category_hint'], $tenant_id),
                        'direction' => null,
                        'confidence' => $v['confidence'],
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Match category by БГ keyword
     */
    private function matchCategoryByKeyword(string $transcript, int $tenant_id): ?array {
        $transcript_lower = mb_strtolower($transcript);
        
        $matches = DB::query(
            "SELECT * FROM category_keywords 
             WHERE (tenant_id = ? OR is_universal = TRUE)
             ORDER BY confidence DESC, occurrences_count DESC",
            [$tenant_id]
        )->fetchAll();
        
        foreach ($matches as $m) {
            if (mb_strpos($transcript_lower, mb_strtolower($m['keyword_bg'])) !== false) {
                return [
                    'id' => $m['category_id'],
                    'confidence' => $m['confidence'],
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Update learning data след AI parse
     */
    private function learnFromAIResult(
        int $tenant_id, 
        string $transcript, 
        array $ai_result
    ): void {
        // 1. Save vendor alias ако имаме
        if (!empty($ai_result['vendor_name'])) {
            $this->upsertVendorAlias(
                $tenant_id,
                $ai_result['vendor_name'],
                $transcript,
                $ai_result
            );
        }
        
        // 2. Save keyword association ако сме сигурни в категорията
        if (!empty($ai_result['category_id']) && $ai_result['confidence'] >= 0.85) {
            $this->upsertKeywordAssociation(
                $tenant_id,
                $transcript,
                $ai_result['category_id']
            );
        }
    }
}
```

## 37.5 Confidence accumulation algorithm

Vendor confidence се увеличава с всяко повторение:

```php
function updateVendorConfidence(int $vendor_alias_id): void {
    DB::query("
        UPDATE vendor_aliases SET
            occurrences_count = occurrences_count + 1,
            confidence = LEAST(0.99, confidence + 0.05),
            last_seen = NOW()
        WHERE id = ?
    ", [$vendor_alias_id]);
    
    // Auto-promote to universal ако 5+ tenants имат vendor
    DB::query("
        UPDATE vendor_aliases_universal SET
            detected_in_tenants = (
                SELECT COUNT(DISTINCT tenant_id) 
                FROM vendor_aliases 
                WHERE canonical_name = vendor_aliases_universal.canonical_name
            ),
            confidence = LEAST(0.99, 
                CASE 
                  WHEN detected_in_tenants >= 50 THEN 0.95
                  WHEN detected_in_tenants >= 20 THEN 0.90
                  WHEN detected_in_tenants >= 10 THEN 0.85
                  WHEN detected_in_tenants >= 5 THEN 0.75
                  ELSE 0.50
                END
            )
        WHERE canonical_name IN (
            SELECT canonical_name FROM vendor_aliases WHERE id = ?
        )
    ", [$vendor_alias_id]);
}
```

## 37.6 Cron jobs

```bash
# /etc/cron.d/cfo-learning

# Hourly: update confidence based на recent activity
0 * * * * www-data php /var/www/runmystore/cron/cfo-confidence-update.php

# Nightly: promote vendors към universal ако 5+ tenants
0 2 * * * www-data php /var/www/runmystore/cron/cfo-universal-promotion.php

# Weekly: rebuild keyword associations за all tenants
0 4 * * 0 www-data php /var/www/runmystore/cron/cfo-keyword-rebuild.php
```

## 37.7 Tracking metrics — за измерване на learning success

```sql
CREATE TABLE learning_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    
    date DATE NOT NULL,
    
    total_voice_inputs INT DEFAULT 0,
    php_only_parses INT DEFAULT 0,         -- PHP се справи сам
    ai_required_parses INT DEFAULT 0,       -- AI беше викнат
    
    php_hit_rate DECIMAL(5,2) GENERATED ALWAYS AS (
        IF(total_voice_inputs = 0, 0, php_only_parses / total_voice_inputs * 100)
    ) STORED,
    
    avg_confidence DECIMAL(3,2),
    
    daily_ai_cost_usd DECIMAL(10,4),
    
    UNIQUE INDEX idx_tenant_date (tenant_id, date)
);
```

**Target metrics:**
- Month 1: PHP hit rate ~20-30%
- Month 3: PHP hit rate ~50-60%
- Month 6: PHP hit rate ~70-80%
- Month 12: PHP hit rate ~85-90% (steady state)

---

# §38. DATA FLYWHEEL — BIDIRECTIONAL LEARNING

## 38.1 Концепция

Pocket CFO и RunMyStore **се хранят един друг** със знание:

```
POCKET CFO data → enrich RunMyStore:
   • БГ vendor knowledge graph (4000+ vendors)
   • Subscription patterns (universal)
   • Personal expense categories
   • Geographic pricing benchmarks
   • Inflation tracking (real-time БГ)

RunMyStore data → enrich Pocket CFO:
   • 4000+ retail vendor names (доставчици)
   • Categories knowledge (облекло/обувки/...)
   • Seasonal patterns (зима/лято)
   • Wholesale vs retail pricing
   • EIK validation (verified БГ юр. лица)
```

**Single shared codebase = ZERO data sync overhead.**

## 38.2 Cross-product validation

```php
// lib/cross-validation.php

class CrossProductValidator {
    /**
     * Validate vendor от RunMyStore database
     */
    public function validateVendor(string $vendor_name): array {
        // 1. Check в RunMyStore suppliers table (verified EIK)
        $rms_match = DB::query(
            "SELECT id, name, eik, verified_at FROM suppliers
             WHERE name LIKE CONCAT('%', ?, '%')
                OR JSON_CONTAINS(aliases, JSON_QUOTE(?))
             LIMIT 1",
            [$vendor_name, $vendor_name]
        )->fetch();
        
        if ($rms_match) {
            return [
                'verified' => true,
                'source' => 'runmystore_suppliers',
                'canonical_name' => $rms_match['name'],
                'eik' => $rms_match['eik'],
                'confidence' => 0.98,
            ];
        }
        
        // 2. Check в universal vendors (cross-tenant learned)
        $universal = DB::query(
            "SELECT * FROM vendor_aliases_universal
             WHERE canonical_name = ?
                OR JSON_CONTAINS(keywords, JSON_QUOTE(?))",
            [$vendor_name, $vendor_name]
        )->fetch();
        
        if ($universal && $universal['detected_in_tenants'] >= 5) {
            return [
                'verified' => true,
                'source' => 'universal_learned',
                'canonical_name' => $universal['canonical_name'],
                'confidence' => $universal['confidence'],
            ];
        }
        
        return [
            'verified' => false,
            'source' => 'unknown',
            'confidence' => 0,
        ];
    }
}
```

## 38.3 Anonymized cohort sharing

K-anonymity guards (k≥5) гарантират че индивидуални users НИКОГА не могат да бъдат идентифицирани:

```php
// lib/cohort-data-share.php

class CohortDataShare {
    const MIN_K = 5;
    
    /**
     * Compute БГ inflation metrics (cross-tenant aggregation)
     */
    public function computeBGInflation(string $category): ?array {
        // Aggregate ТОЛКОВА tenants със consent
        $data = DB::query("
            SELECT 
                AVG(amount) AS avg_this_month,
                COUNT(DISTINCT tenant_id) AS cohort_size,
                STDDEV(amount) AS variance
            FROM money_movements mm
            JOIN tenants t ON t.id = mm.tenant_id
            WHERE t.benchmark_optin = TRUE
              AND mm.category_id = (SELECT id FROM categories WHERE name = ?)
              AND mm.direction = 'out'
              AND mm.occurred_at >= NOW() - INTERVAL 30 DAY
        ", [$category])->fetch();
        
        if ($data['cohort_size'] < self::MIN_K) {
            return null;  // suppress
        }
        
        // Compare с предишен период
        $old_data = DB::query("
            SELECT AVG(amount) AS avg_six_months_ago
            FROM money_movements mm
            JOIN tenants t ON t.id = mm.tenant_id
            WHERE t.benchmark_optin = TRUE
              AND mm.category_id = (SELECT id FROM categories WHERE name = ?)
              AND mm.direction = 'out'
              AND mm.occurred_at BETWEEN NOW() - INTERVAL 7 MONTH 
                                    AND NOW() - INTERVAL 6 MONTH
        ", [$category])->fetch();
        
        $inflation_pct = $old_data['avg_six_months_ago'] > 0 
            ? round(($data['avg_this_month'] / $old_data['avg_six_months_ago'] - 1) * 100, 1)
            : 0;
        
        return [
            'category' => $category,
            'inflation_pct' => $inflation_pct,
            'cohort_size' => $data['cohort_size'],
            'current_avg' => $data['avg_this_month'],
            'previous_avg' => $old_data['avg_six_months_ago'],
        ];
    }
}
```

## 38.4 Knowledge graph - тangible benefits

```
МЕСЕЦ 1 (cold start):
   AI cost: €0.10/user/мес
   Vendor knowledge: 50 universal vendors (seeded)
   Confidence avg: 0.45

МЕСЕЦ 6 (mature):
   AI cost: €0.03/user/мес (-70%)
   Vendor knowledge: 800+ universal vendors  
   Confidence avg: 0.85
   Coverage of БГ retail: ~80%

МЕСЕЦ 12 (mature data flywheel):
   AI cost: €0.025/user/мес (-75%)
   Vendor knowledge: 2000+ vendors
   Confidence avg: 0.92
   Coverage of БГ retail: ~95%
   RunMyStore benefits: faster vendor recognition при доставки
```

## 38.5 Inflation tracker — public AI insight (за всички)

```php
function generateInflationInsight(int $tenant_id, string $category): ?string {
    $data = (new CohortDataShare())->computeBGInflation($category);
    
    if (!$data || $data['cohort_size'] < 5) return null;
    
    $tenant_data = DB::query("
        SELECT 
            AVG(amount) AS my_avg
        FROM money_movements
        WHERE tenant_id = ? 
          AND category_id = (SELECT id FROM categories WHERE name = ?)
          AND occurred_at >= NOW() - INTERVAL 30 DAY
    ", [$tenant_id, $category])->fetchColumn();
    
    if (!$tenant_data) return null;
    
    $prompt = <<<P
БГ инфлация за категория "{$data['category']}":
- Тази година: средно €{$data['current_avg']}
- Преди 6 месеца: €{$data['previous_avg']}
- Промяна: {$data['inflation_pct']}%

Потребителят харчи: €{$tenant_data} тази година.

В едно изречение БГ (max 70 chars):
Опиши факта + сравни с тенденцията.
P;
    
    return callGemini($prompt);
    // Output: "Гориво +14% за 6 месеца (БГ ср.). Ти плащаш €83/мес."
}
```

---

# §39. COST OPTIMIZATION TIMELINE

## 39.1 AI cost lifecycle (per user, per month)

```
═══════════════════════════════════════════════════
МЕСЕЦ 1-2: COLD START
═══════════════════════════════════════════════════
   PHP hit rate:     20%
   Gemini calls:    ~120/user/мес
   Cost:            €0.10/user/мес
   
   Стратегия: Build vendor/keyword knowledge

═══════════════════════════════════════════════════
МЕСЕЦ 3-4: LEARNING ACCELERATING
═══════════════════════════════════════════════════
   PHP hit rate:     45%
   Gemini calls:    ~80/user/мес
   Cost:            €0.06/user/мес (-40%)
   
   Стратегия: Universal promotions започват

═══════════════════════════════════════════════════
МЕСЕЦ 5-6: MATURE LEARNING
═══════════════════════════════════════════════════
   PHP hit rate:     65%
   Gemini calls:    ~50/user/мес
   Cost:            €0.04/user/мес (-60%)
   
   Стратегия: Cross-tenant data flywheel

═══════════════════════════════════════════════════
МЕСЕЦ 7-12: STEADY STATE
═══════════════════════════════════════════════════
   PHP hit rate:     80%
   Gemini calls:    ~25/user/мес
   Cost:            €0.025/user/мес (-75%)
   
   Стратегия: Marginal improvements, monitor cost
```

## 39.2 Финални margins per user

```
Revenue:                   €4.99
Apple/Google fee (15%):   -€0.75
VAT (20%):                -€0.83
                          ─────────
Net Revenue:               €3.41

AI cost (mature, 80%+):   -€0.03
Infrastructure:           -€0.045
Support tools:            -€0.134
Compliance:               -€0.07
Stripe/payment processing:-€0.02
                          ─────────
Total costs:              -€0.299

NET MARGIN:                €3.11 (62%) ← Mature state
NET MARGIN cold start:     €3.06 (60%) ← Month 1-2
```

## 39.3 Cost projections at scale

```
1 000 users mature:
   Revenue: €4 990/мес
   AI cost: €30/мес
   Total costs: €299/мес
   Profit: €3 110/мес = €37 320/year

5 000 users mature:
   Revenue: €24 950/мес
   AI cost: €150/мес
   Total costs: €1 495/мес  
   Profit: €15 550/мес = €186 600/year

10 000 users mature:
   Revenue: €49 900/мес
   AI cost: €300/мес
   Total costs: €2 990/мес
   Profit: €31 100/мес = €373 200/year
   + DPO cost: -€333/мес = €30 767/мес net
```

---

# §40. ANTI-HALUCINATION FRAMEWORK (UNIVERSAL)

## 40.1 Петте закона — задължителни

Прилагат се за **ВЕКИ AI output** в Pocket CFO:

### Закон #1: PHP смята, AI говори
ВСЕКИ insight number идва от SQL/PHP. AI никога не генерира числа.

```php
// ✅ ПРАВИЛНО
$revenue = DB::query("SELECT SUM(amount)...")->fetchColumn();
$prompt = "Опиши: тази седмица €{$revenue}";

// ❌ НЕПРАВИЛНО
$prompt = "Анализирай tenant {$id} харчовете";
```

### Закон #2: AI is silent, PHP continues

```php
try {
    $insight_text = callGemini($prompt, [
        'timeout' => 3,
        'temperature' => 0.1,
    ]);
} catch (\Exception $e) {
    // PHP fallback — НЕ показва грешка на user
    $insight_text = phpFallbackTemplate($topic_id, $data);
}
```

### Закон #3: Audit trail задължителен

```sql
INSERT INTO ai_insights (
    tenant_id, topic_id, rendered_text,
    retrieved_facts  -- JSON със ВСИЧКИ SQL queries + results
);
```

User може да тапне "Защо?" → вижда **точните данни**.

### Закон #4: Confidence routing

```
confidence >= 0.85  → auto display
0.50 - 0.85         → "Изглежда..." prefix marker
< 0.50              → SUPPRESS (не показва)
```

### Закон #5: Anti-repetition

```sql
CREATE TABLE ai_shown (
    tenant_id INT,
    topic_id VARCHAR(50),
    shown_at DATETIME,
    cooldown_until DATETIME,
    INDEX idx_cooldown (tenant_id, topic_id, cooldown_until)
);
```

Същата тема не се показва преди `cooldown_until > NOW()`.

## 40.2 Cross-product validation

```php
function generateAnyInsight(string $topic_id, int $tenant_id, array $data): array {
    // 1. Verify numbers come from SQL
    if (empty($data['retrieved_facts'])) {
        throw new \LogicException("Закон #1 нарушен: insight без SQL data");
    }
    
    // 2. Cross-validate против RunMyStore data (ако е приложимо)
    if (isset($data['vendor_name'])) {
        $validation = (new CrossProductValidator())->validateVendor($data['vendor_name']);
        $data['vendor_verified'] = $validation['verified'];
        $data['vendor_confidence'] = $validation['confidence'];
    }
    
    // 3. Build prompt със sealed numbers
    $prompt = $this->renderTemplate($topic_id, $data);
    
    // 4. AI call с fallback
    try {
        $text = callGemini($prompt, ['timeout' => 3, 'temperature' => 0.1]);
        $source = 'ai';
    } catch (\Exception) {
        $text = phpFallback($topic_id, $data);
        $source = 'php_fallback';
    }
    
    // 5. Confidence routing
    $confidence = $data['confidence'] ?? 0.90;
    if ($confidence < 0.50) {
        return ['suppress' => true];
    }
    
    if ($confidence < 0.85) {
        $text = "Изглежда " . lcfirst($text);
    }
    
    // 6. Anti-repetition check
    $last_shown = DB::query("
        SELECT cooldown_until FROM ai_shown
        WHERE tenant_id = ? AND topic_id = ?
        ORDER BY shown_at DESC LIMIT 1
    ", [$tenant_id, $topic_id])->fetchColumn();
    
    if ($last_shown && strtotime($last_shown) > time()) {
        return ['suppress' => true, 'reason' => 'cooldown'];
    }
    
    // 7. Save с full audit trail
    $insight_id = DB::insert('ai_insights', [
        'tenant_id' => $tenant_id,
        'topic_id' => $topic_id,
        'rendered_text' => $text,
        'confidence' => $confidence,
        'source' => $source,
        'retrieved_facts' => json_encode($data),
    ]);
    
    DB::insert('ai_shown', [
        'tenant_id' => $tenant_id,
        'topic_id' => $topic_id,
        'cooldown_until' => date('Y-m-d H:i:s', strtotime('+' . $this->getCooldown($topic_id) . ' hours')),
    ]);
    
    return [
        'id' => $insight_id,
        'text' => $text,
        'source' => $source,
        'confidence' => $confidence,
    ];
}
```


# §29.7 HIDDEN COSTS DISCOVERY FLOW (Onboarding extension)

## 29.7.1 Lifestyle interview — критичен компонент

В onboarding **STEP 3** (след profession template + expected income), добавяме voice-driven lifestyle interview за да uncover-нем hidden recurring costs **veднага**.

```
┌─────────────────────────────────────────┐
│ 📋 РЕДОВНИ РАЗХОДИ                       │
│                                          │
│ Кажи ми какво плащаш редовно — за       │
│ да следим всичко автоматично.            │
│                                          │
│ 🎤 [Голям микрофон бутон]                │
│                                          │
│ 💡 Примери:                              │
│ • Наем работно място / офис              │
│ • Интернет / телефон (А1/Yettel/...)    │
│ • Електричество / парно / вода           │
│ • Subscriptions (Netflix, iCloud, ...)   │
│ • Застраховки (ГО, КАСКО)                │
│ • Винетка                                │
│ • Счетоводител                           │
│ • Реклама                                │
│ • Подкрепа за родители / семейство       │
│                                          │
│ Записани досега:                         │
│ ✓ Наем €153/мес                          │
│ ✓ А1 €20.45/мес                          │
│ ✓ Netflix €4.99/мес                      │
│                                          │
│ [Готов съм] [Прескочи засега]            │
└─────────────────────────────────────────┘
```

## 29.7.2 AI Interview Voice Flow

```
🤖 "Чакай! Преди да започнем, питам те важно нещо.
    Кажи ми за редовните си месечни разходи —
    мога да ти помогна да ги следим автоматично."

👤 "Ами... плащам наем на работното място..."

🤖 "Колко на месец?"

👤 "300 лева"

🤖 ✓ Записах: Наем €153/мес
    "А какво друго плащаш редовно?"

👤 "А1 ми е 40 лева, Netflix 10..."

🤖 ✓ А1 €20.45/мес
    ✓ Netflix €5.11/мес
    "Има ли още? Помисли за:
     • Електричество, ток
     • Counter (зали, кабинет)
     • Софтуер / cloud
     • Subscriptions (Spotify, iCloud)"

👤 "Аха, имам Spotify 6 лева, iCloud 3 лева, и плащам 
   500 лева за счетоводител годишно"

🤖 ✓ Spotify €3.07/мес
    ✓ iCloud €1.53/мес
    ✓ Счетоводител €255.65/год (€21/мес)
    
    "Перфектно. Кажи 'готов' като свърши."

👤 "Готов"

🤖 "Записах 6 редовни разхода за общо €204/мес.
    Сега AI ще ги следи и ще те предупреди ако 
    нещо се промени."
```

## 29.7.3 Lifestyle context capture (questions)

В края на onboarding, питаме за **lifestyle flags** които активират съответните calendar reminders:

```
┌─────────────────────────────────────────┐
│ Кое от изброеното важи за теб?           │
│ (multi-select)                           │
│                                          │
│ □ 🚗 Имам кола                           │
│ □ 🏠 Притежавам жилище                   │
│ □ 👶 Деца в детска градина / училище     │
│ □ 🐕 Домашен любимец                     │
│ □ 💼 Работа на свободна практика         │
│ □ 🏥 Имам частни здравни осигуровки      │
│ □ 🎓 Уча се (курсове, university)        │
│ □ 🌱 Семейство в провинцията              │
│                                          │
│ [Продължи]                                │
└─────────────────────────────────────────┘
```

Тези flags се пазят в `tenants` таблицата:

```sql
ALTER TABLE tenants
  ADD COLUMN lifestyle_flags SET(
    'has_car', 'owns_home', 'has_kids', 'has_pet',
    'self_employed', 'private_health', 'student',
    'family_outside_city'
  ) DEFAULT '';
```

## 29.7.4 Hidden costs schema

```sql
CREATE TABLE detected_recurring_costs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    
    -- What
    name VARCHAR(200) NOT NULL,
    vendor_name VARCHAR(200),
    category_id INT UNSIGNED,
    
    -- Recurring info
    typical_amount DECIMAL(10,2) NOT NULL,
    frequency ENUM('weekly','monthly','quarterly','annual') NOT NULL,
    next_expected_date DATE,
    last_charged_date DATE,
    
    -- Detection source
    detected_in ENUM(
        'onboarding_interview',    -- от lifestyle interview
        'auto_pattern',             -- 2-3 поredни срещания
        'bg_calendar',              -- от хардкодиран календар
        'manual_entry'
    ) NOT NULL,
    
    -- State
    is_confirmed BOOLEAN DEFAULT FALSE,
    is_cancelled BOOLEAN DEFAULT FALSE,
    cancelled_at DATETIME NULL,
    cancellation_reason VARCHAR(200) NULL,
    
    -- Tracking
    occurrences_count INT DEFAULT 1,
    last_seen DATETIME,
    
    -- Notifications
    notify_before_days INT DEFAULT 3,
    last_notified DATETIME NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_active (tenant_id, is_cancelled),
    INDEX idx_next_expected (next_expected_date)
);
```

---

# §29.8 БГ CALENDAR REMINDERS — 60+ EVENTS

## 29.8.1 Hardcoded БГ recurring events

```sql
CREATE TABLE bg_recurring_calendar (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    name VARCHAR(150) NOT NULL,
    name_en VARCHAR(150),
    category VARCHAR(50) NOT NULL,
    
    -- Кога обикновено идва
    typical_month INT NULL,        -- 1-12, NULL ако varies
    typical_day INT NULL,           -- 1-31, NULL ако varies
    frequency ENUM('monthly','quarterly','annual','one_time') DEFAULT 'annual',
    
    -- За кого важи (lifestyle flag matching)
    requires_lifestyle_flag VARCHAR(50) NULL,
    requires_template VARCHAR(50) NULL,
    
    -- Стойност
    typical_amount_min DECIMAL(10,2),
    typical_amount_max DECIMAL(10,2),
    typical_amount_avg DECIMAL(10,2),
    
    -- Reminder тон
    reminder_days_before INT DEFAULT 7,
    importance ENUM('critical','high','medium','low') DEFAULT 'medium',
    
    -- БГ специфика
    legal_consequence TEXT,
    nra_relevant BOOLEAN DEFAULT FALSE,
    description_bg TEXT,
    
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_active_month (is_active, typical_month, typical_day)
);
```

## 29.8.2 Seed данни — пълен БГ календар

```sql
INSERT INTO bg_recurring_calendar 
(name, category, typical_month, typical_day, frequency, requires_lifestyle_flag, 
 typical_amount_min, typical_amount_max, importance, description_bg) VALUES

-- ═══════════════════════════════════════════════
-- 🚗 АВТОМОБИЛНИ РАЗХОДИ
-- ═══════════════════════════════════════════════
('Винетка', 'car', 1, 5, 'annual', 'has_car', 50, 97, 'critical',
 'Годишна винетка за пътна инфраструктура'),
('Гражданска отговорност (ГО)', 'car', NULL, NULL, 'annual', 'has_car', 150, 400, 'critical',
 'Задължителна автомобилна застраховка'),
('КАСКО', 'car', NULL, NULL, 'annual', 'has_car', 300, 1200, 'medium',
 'Доброволна автомобилна застраховка'),
('Технически преглед', 'car', NULL, NULL, 'annual', 'has_car', 30, 60, 'high',
 'Задължителен годишен технически преглед'),
('Данък МПС', 'car', NULL, NULL, 'annual', 'has_car', 50, 500, 'high',
 'Общински данък върху превозните средства'),
('Смяна гуми (зимни/летни)', 'car', 3, 15, 'annual', 'has_car', 20, 80, 'medium',
 'Сезонна смяна гуми, два пъти годишно (март + октомври)'),
('Сервиз кола (плановен)', 'car', NULL, NULL, 'annual', 'has_car', 100, 500, 'medium',
 'Годишен сервиз/поддръжка'),

-- ═══════════════════════════════════════════════
-- 🏠 ЖИЛИЩЕ
-- ═══════════════════════════════════════════════
('Данък сгради', 'home', 3, 31, 'annual', 'owns_home', 50, 500, 'high',
 'Годишен данък върху недвижими имоти'),
('Такса смет', 'home', 3, 31, 'quarterly', 'owns_home', 30, 150, 'medium',
 'Тримесечна такса за битови отпадъци'),
('Имуществена застраховка', 'home', NULL, NULL, 'annual', 'owns_home', 50, 200, 'medium',
 'Доброволна застраховка на имущество'),
('Етажна собственост', 'home', NULL, 5, 'monthly', 'owns_home', 10, 50, 'medium',
 'Месечна такса за поддръжка на сгради в режим етажна собственост'),

-- ═══════════════════════════════════════════════
-- 💼 САМОНАЕТИ / НАП
-- ═══════════════════════════════════════════════
('Здравни осигуровки (НОИ)', 'tax', NULL, 25, 'monthly', 'self_employed', 25, 100, 'critical',
 'Задължителни здравни осигуровки до 25-то число всеки месец'),
('Социални осигуровки', 'tax', NULL, 25, 'monthly', 'self_employed', 50, 200, 'critical',
 'Задължителни социални осигуровки'),
('Годишна данъчна декларация (чл. 50)', 'tax', 4, 30, 'annual', 'self_employed', NULL, NULL, 'critical',
 'Срок: 30 април. Деклариране на доходи от самонаемане'),
('Квартален аванс НАП', 'tax', 4, 15, 'quarterly', 'self_employed', NULL, NULL, 'high',
 'Тримесечни аванси: 15 април, 15 юли, 15 октомври, 15 януари'),
('Патентен данък', 'tax', 1, 31, 'annual', NULL, 50, 5000, 'high',
 'Годишен патентен данък за определени дейности'),
('ДДС декларация', 'tax', NULL, 14, 'monthly', NULL, NULL, NULL, 'critical',
 'Месечна ДДС декларация — за ДДС регистрирани, до 14-то число'),

-- ═══════════════════════════════════════════════
-- 👶 ДЕЦА
-- ═══════════════════════════════════════════════
('Такса детска градина', 'kids', NULL, 10, 'monthly', 'has_kids', 30, 250, 'high',
 'Месечна такса за детска градина'),
('Такса частно училище', 'kids', NULL, 10, 'monthly', 'has_kids', 100, 800, 'high',
 'Месечна такса за частно училище'),
('Учебници / помагала', 'kids', 9, 1, 'annual', 'has_kids', 50, 300, 'medium',
 'Подготовка за нова учебна година'),
('Извънкласни дейности', 'kids', NULL, NULL, 'monthly', 'has_kids', 20, 200, 'low',
 'Месечни такси за спорт, езици, школи'),

-- ═══════════════════════════════════════════════
-- 🏥 ЗДРАВЕ
-- ═══════════════════════════════════════════════
('Допълнителни здравни осигуровки', 'health', NULL, 1, 'monthly', 'private_health', 20, 80, 'medium',
 'Месечни ДЗО (Bulstrad, ДЗИ, и др.)'),
('Профилактичен преглед', 'health', NULL, NULL, 'annual', NULL, 50, 200, 'medium',
 'Годишен профилактичен преглед'),
('Стоматолог', 'health', NULL, NULL, 'annual', NULL, 80, 300, 'low',
 'Препоръчителни 2 прегледа годишно'),

-- ═══════════════════════════════════════════════
-- 🐕 ДОМАШНИ ЛЮБИМЦИ
-- ═══════════════════════════════════════════════
('Ветеринар (годишен преглед)', 'pet', NULL, NULL, 'annual', 'has_pet', 30, 100, 'medium',
 'Годишен профилактичен преглед на любимец'),
('Ваксини (домашен любимец)', 'pet', NULL, NULL, 'annual', 'has_pet', 30, 150, 'medium',
 'Задължителни ваксини, бесило и т.н.'),

-- ═══════════════════════════════════════════════
-- 🌐 ДИГИТАЛНИ (CRITICAL за всеки)
-- ═══════════════════════════════════════════════
('Домейн (.com/.bg)', 'digital', NULL, NULL, 'annual', NULL, 10, 50, 'medium',
 'Годишно подновяване на домейн'),
('Хостинг', 'digital', NULL, NULL, 'annual', NULL, 30, 300, 'medium',
 'Годишен хостинг план'),
('VPN', 'digital', NULL, NULL, 'annual', NULL, 30, 100, 'low',
 'Годишен VPN абонамент (NordVPN, ExpressVPN)'),
('Антивирусен софтуер', 'digital', NULL, NULL, 'annual', NULL, 20, 80, 'low',
 'Годишен антивирусен абонамент'),

-- ═══════════════════════════════════════════════
-- 🎁 СОЦИАЛНИ / СЕМЕЙНИ
-- ═══════════════════════════════════════════════
('Коледни подаръци', 'gifts', 12, 15, 'annual', NULL, 100, 1000, 'low',
 'Сезонни покупки за Коледа'),
('Великденски разходи', 'gifts', 4, 1, 'annual', NULL, 50, 200, 'low',
 '8-те май — Великденски разходи'),
('Майчиния ден', 'gifts', 5, 8, 'annual', NULL, 20, 100, 'low',
 '8 май — подарък за мама'),
('Бащиния ден', 'gifts', 6, 8, 'annual', NULL, 20, 100, 'low',
 '8 юни — подарък за татко'),
('Рождени дни близки', 'gifts', NULL, NULL, 'annual', NULL, 20, 200, 'low',
 'Подаръци за близки през годината'),

-- ═══════════════════════════════════════════════
-- 💳 ФИНАНСОВИ
-- ═══════════════════════════════════════════════
('Годишна такса карта', 'finance', 12, NULL, 'annual', NULL, 10, 80, 'medium',
 'Годишна такса за поддръжка на банкова карта'),
('Такса спестовна сметка', 'finance', NULL, NULL, 'annual', NULL, 5, 50, 'low',
 'Годишна такса за спестовна сметка'),

-- ═══════════════════════════════════════════════
-- 🌱 ЗЕМЕДЕЛИЕ (за farmer template)
-- ═══════════════════════════════════════════════
('Субсидии ДФ Земеделие', 'farming', 12, 1, 'annual', NULL, NULL, NULL, 'high',
 'Годишно подаване за субсидии — декември'),
('Семена/разсад (пролет)', 'farming', 3, 1, 'annual', NULL, 100, 5000, 'medium',
 'Пролетна подготовка'),
('Семена/разсад (есен)', 'farming', 9, 1, 'annual', NULL, 100, 3000, 'medium',
 'Есенна подготовка');
```

## 29.8.3 Reminder cron job

```php
// cron/cfo-bg-calendar-daily.php
// Run daily at 09:00

$today = date('Y-m-d');
$today_month = (int) date('n');
$today_day = (int) date('j');

// За всеки event с typical_month + typical_day
$events = DB::query("
    SELECT * FROM bg_recurring_calendar
    WHERE is_active = TRUE
      AND typical_month IS NOT NULL
      AND typical_day IS NOT NULL
")->fetchAll();

foreach ($events as $event) {
    $reminder_date = date('Y-m-d', mktime(
        0, 0, 0,
        $event['typical_month'],
        $event['typical_day'] - $event['reminder_days_before'],
        date('Y')
    ));
    
    if ($reminder_date !== $today) continue;
    
    // Find eligible tenants (match lifestyle flags)
    $sql_filter = "";
    $params = [];
    
    if ($event['requires_lifestyle_flag']) {
        $sql_filter = "AND FIND_IN_SET(?, lifestyle_flags) > 0";
        $params[] = $event['requires_lifestyle_flag'];
    }
    
    if ($event['requires_template']) {
        $sql_filter .= " AND profession_template = ?";
        $params[] = $event['requires_template'];
    }
    
    $tenants = DB::query(
        "SELECT id FROM tenants 
         WHERE plan IN ('cfo','start','pro','business')
           {$sql_filter}",
        $params
    )->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tenants as $tenant_id) {
        // Check дали вече е платил тази година (avoid duplicate reminder)
        $already_paid = DB::query("
            SELECT 1 FROM money_movements
            WHERE tenant_id = ?
              AND note LIKE ?
              AND YEAR(occurred_at) = YEAR(NOW())
            LIMIT 1
        ", [$tenant_id, '%' . $event['name'] . '%'])->fetchColumn();
        
        if ($already_paid) continue;
        
        // Send push notification
        sendPushNotification($tenant_id, [
            'title' => $event['importance'] === 'critical' ? '🚨 Важно!' : '📅 Напомняне',
            'body' => "Време е за: {$event['name']}. " . 
                      ($event['typical_amount_avg'] 
                        ? "Обикновено ~€{$event['typical_amount_avg']}." 
                        : ""),
            'action' => 'open_record',
            'category_hint' => $event['category'],
            'amount_hint' => $event['typical_amount_avg'],
        ]);
    }
}
```

## 29.8.4 Personalized calendar generation

В Settings, user вижда **своя** personalized calendar:

```
┌─────────────────────────────────────────┐
│ 📅 ТВОИЯТ ФИНАНСОВ КАЛЕНДАР              │
│                                          │
│ ЯНУАРИ                                   │
│ ━━━━━━━━━                                │
│ 5 ян   • Винетка (~€97)                  │
│ 25 ян  • Здравни осигуровки (~€50)       │
│ 31 ян  • Патентен данък                  │
│                                          │
│ ФЕВРУАРИ                                 │
│ ━━━━━━━━━                                │
│ 25 фев • Здравни осигуровки              │
│                                          │
│ МАРТ                                     │
│ ━━━━━━━━━                                │
│ 1 мар  • Семена/разсад (пролет)          │
│ 31 мар • Данък сгради                    │
│ 31 мар • Такса смет Q1                   │
│                                          │
│ АПРИЛ                                    │
│ ━━━━━━━━━                                │
│ 15 апр • Квартален аванс НАП             │
│ 30 апр • 🚨 Годишна декларация (чл. 50) │
│                                          │
│ ...                                      │
│                                          │
│ [Експорт като iCal/Google Calendar]      │
└─────────────────────────────────────────┘
```

---

# §30.8 EXTENDED MONEY_MOVEMENTS — REASONS BREAKDOWN

## 30.8.1 Пълен ENUM на reasons

Разширява §30.1 с допълнителни reasons за personal/family финанси:

```sql
ALTER TABLE money_movements MODIFY COLUMN reason ENUM(
    -- ═══════ INCOME ═══════
    -- Бизнес доходи
    'sale',                        -- retail продажба
    'service_income',              -- service платен от клиент
    'platform_income',             -- Uber/Glovo/Bolt
    'subscription_income',         -- recurring client
    'subsidy_received',            -- държавна помощ (ДФ Земеделие)
    'refund_received',             -- refund от доставчик
    'owner_inject',                -- собствен капитал → бизнес
    
    -- Лични доходи
    'salary_received',             -- заплата
    'gift_received',               -- подарък
    'loan_received',               -- получен заем
    'debt_returned_to_me',         -- връщат ми заем
    'investment_return',           -- дивиденти, лихви
    'inheritance',                 -- наследство
    
    -- Трансфери (in)
    'transfer_in',                 -- от друга своя сметка
    
    'other_income',
    
    -- ═══════ OUTCOME ═══════
    -- Бизнес разходи
    'supplier_payment',            -- доставка на стока
    'expense_payment',             -- общи бизнес разходи
    'salary_paid',                 -- заплати на персонал
    'wage_to_helper',              -- хонорар на freelancer/помощник
    'rent_paid',                   -- наем работно място
    'utility_paid',                -- ток/вода/интернет (бизнес)
    'tax_paid',                    -- НАП, осигуровки
    'vat_paid',                    -- ДДС платен
    'insurance_paid',              -- ГО, КАСКО, имуществена
    'advertising',                 -- реклама
    'software_subscription',       -- бизнес софтуер
    'professional_services',       -- счетоводител, юрист
    
    -- Лични разходи
    'personal_expense',            -- общи лични харчове
    'gift_given',                  -- подарък на някого
    'charity_donation',            -- дарение
    'support_to_family',           -- издръжка, алименти, помощ
    'loan_given',                  -- заех на някого
    'loan_repayment',              -- връщам заем (банка/приятел)
    'tuition_fee',                 -- учебни такси
    'medical_expense',             -- здравни разходи
    
    -- Трансфери (out)
    'transfer_to_savings',         -- към спестовна
    'transfer_to_investment',      -- към инвестиции
    'transfer_to_crypto',          -- към крипто wallet
    'transfer_between_own',        -- между собствени сметки
    'transfer_out',                -- generic
    
    'owner_withdrawal',            -- бизнес → лично
    'refund_given',                -- refund на клиент
    
    'adjustment',                  -- ръчна корекция
    'other_expense'
) NOT NULL;
```

## 30.8.2 Voice flow примери за нови reasons

```
🎤 "Платих 200 на Иван за помощ днес"
   → reason: 'wage_to_helper'
   → is_business: true
   → AI: "Това месечно ли е?"

🎤 "Заех 500 на брат ми"
   → reason: 'loan_given'
   → AI: "Кога очакваш да върне?"
   → Creates тracking: "Брат ми връща 500€"

🎤 "Майка ми ми даде 100 лева"
   → reason: 'gift_received'
   → is_business: false
   → AI: "Подарък за специален повод?"

🎤 "Платих 300 издръжка на децата"
   → reason: 'support_to_family'
   → AI: "Месечна редовна?"
   → marks as recurring

🎤 "Внесох 200 в спестовната"
   → reason: 'transfer_to_savings'
   → AI: "Обновявам прогреса на 'Резерв 3 месеца' цел"

🎤 "Дарих 50 лева на бабе Илия"
   → reason: 'charity_donation'
   → AI: "Запазвам в категория Дарения"

🎤 "Платих 800 лева на ДЗИ за каско"
   → reason: 'insurance_paid'
   → AI: "Годишен или месечен план?"
   → Auto-split annual → monthly view
```


# §32.7 ADDITIONAL AI TOPICS — Business Intelligence Layer

Bible v1.1 имаше 30 AI теми (cfo_001-030). v1.2 добавя още 15 теми (cfo_031-045) за business intelligence — нещо което счетоводителят НЕ дава.

## 32.7.1 NEW Category F: ADVANCED BUSINESS METRICS (15 теми)

### F.1 — cfo_031: Margin trend tracking

```sql
SELECT 
    DATE_FORMAT(occurred_at, '%Y-%m') AS month,
    SUM(CASE WHEN direction='in' AND is_business=TRUE THEN amount ELSE 0 END) AS income,
    SUM(CASE WHEN direction='out' AND is_business=TRUE THEN amount ELSE 0 END) AS expenses,
    ROUND(
      (SUM(CASE WHEN direction='in' AND is_business=TRUE THEN amount ELSE 0 END) - 
       SUM(CASE WHEN direction='out' AND is_business=TRUE THEN amount ELSE 0 END)) /
      NULLIF(SUM(CASE WHEN direction='in' AND is_business=TRUE THEN amount ELSE 0 END), 0) * 100,
    1) AS margin_pct
FROM money_movements
WHERE tenant_id = :t
  AND occurred_at >= NOW() - INTERVAL 6 MONTH
GROUP BY DATE_FORMAT(occurred_at, '%Y-%m')
ORDER BY month
```

**Trigger:** Margin pct падна >5% за 3 поredни месеца.

**Output:** "Маржът ти пада: 32% → 28% → 25% за 3 месеца. Време за анализ."

### F.2 — cfo_032: Top performers (services/categories)

```sql
SELECT 
    c.name_bg AS category,
    SUM(mm.amount) AS revenue,
    COUNT(*) AS transactions,
    ROUND(SUM(mm.amount) / (SELECT SUM(amount) FROM money_movements 
                            WHERE tenant_id=:t AND direction='in' 
                              AND occurred_at >= NOW() - INTERVAL 30 DAY) * 100, 1) AS pct_of_total
FROM money_movements mm
JOIN categories c ON c.id = mm.category_id
WHERE mm.tenant_id = :t
  AND mm.direction = 'in'
  AND mm.is_business = TRUE
  AND mm.occurred_at >= NOW() - INTERVAL 30 DAY
GROUP BY mm.category_id
ORDER BY revenue DESC
LIMIT 3
```

**Output:** "Топ услуга: 'Балеаж' €820 (32% от приходите). Маникюр прост: €120 (5%)."

### F.3 — cfo_033: Bottom performers (под-performing services)

**Trigger:** Service/category носи <5% от приходите но заема >15% от времето/бройки.

**Output:** "'Маникюр прост' носи €120 (5%) но е 22 бройки (35% от поръчките). Време ли си струва?"

### F.4 — cfo_034: Average transaction size

```sql
SELECT 
    AVG(amount) AS avg_per_tx,
    AVG(amount) FILTER (WHERE DAYOFWEEK(occurred_at) IN (1,7)) AS avg_weekend,
    AVG(amount) FILTER (WHERE DAYOFWEEK(occurred_at) BETWEEN 2 AND 6) AS avg_weekday
FROM money_movements
WHERE tenant_id = :t
  AND direction = 'in'
  AND occurred_at >= NOW() - INTERVAL 30 DAY
```

**Output:** "Среден приход на ден: €127. Уикендите: €245 (1.9x). Делници: €98."

### F.5 — cfo_035: Period comparison WoW

```sql
SELECT 
    SUM(CASE WHEN occurred_at >= NOW() - INTERVAL 7 DAY 
        THEN amount ELSE 0 END) AS this_week,
    SUM(CASE WHEN occurred_at BETWEEN NOW() - INTERVAL 14 DAY 
                                  AND NOW() - INTERVAL 7 DAY
        THEN amount ELSE 0 END) AS last_week
FROM money_movements
WHERE tenant_id = :t AND direction = 'in'
```

**Output:** "+18% спрямо миналата седмица. €1 240 vs €1 050."

### F.6 — cfo_036: Period comparison MoM

**Output:** "Този месец €4 280 vs миналия €3 920 (+9.2%). Тренд нагоре."

### F.7 — cfo_037: Period comparison YoY (когато data > 12 месеца)

**Trigger:** Има данни за същия месец миналата година.

**Output:** "Май 2026: €4 280. Май 2025: €3 850. Расте 11% YoY."

### F.8 — cfo_038: Anomaly detection — spike day

```sql
SELECT 
    occurred_at::date AS day,
    SUM(amount) AS daily_total,
    (SELECT AVG(daily_sum) FROM (
        SELECT DATE(occurred_at) AS d, SUM(amount) AS daily_sum
        FROM money_movements 
        WHERE tenant_id=:t AND direction='in'
          AND occurred_at >= NOW() - INTERVAL 30 DAY
        GROUP BY DATE(occurred_at)
    ) avg_calc) AS avg_daily
FROM money_movements
WHERE tenant_id = :t
  AND direction = 'in'
  AND occurred_at >= CURRENT_DATE
GROUP BY occurred_at::date
HAVING daily_total > avg_daily * 2.5
```

**Output:** "Днешният ден е 2.8x над средното. €340 vs ср. €120. Какво стана?"

### F.9 — cfo_039: Anomaly detection — negative day

**Trigger:** Day with expenses > income & net negative > €100.

**Output:** "Вчера харчи €280, изкара €0. -€280 net. Голяма покупка?"

### F.10 — cfo_040: Discount erosion tracking (за services)

```sql
SELECT 
    SUM(amount) AS discounts_given,
    SUM(CASE WHEN direction='in' THEN amount ELSE 0 END) AS gross_revenue,
    ROUND(SUM(amount) / NULLIF(SUM(CASE WHEN direction='in' THEN amount ELSE 0 END), 0) * 100, 1) AS pct
FROM money_movements
WHERE tenant_id = :t
  AND reason = 'refund_given'  -- или специална 'discount' категория
  AND occurred_at >= NOW() - INTERVAL 30 DAY
```

**Output:** "Дадох €280 отстъпки този месец (6.6% от приходите). Норма: 5%."

### F.11 — cfo_041: Sparkline trend display

**Output:** "Седмицата: ▁▂▃▅▇▆▅ Тренд: нагоре. Петък пиков."

### F.12 — cfo_042: Hourly pattern (за service users)

```sql
SELECT 
    HOUR(occurred_at) AS hour,
    COUNT(*) AS tx_count,
    SUM(amount) AS hour_revenue
FROM money_movements
WHERE tenant_id = :t
  AND direction = 'in'
  AND is_business = TRUE
  AND occurred_at >= NOW() - INTERVAL 30 DAY
GROUP BY HOUR(occurred_at)
ORDER BY hour_revenue DESC
```

**Output:** "Пик: 14-17ч (45% от приходите). Сутрин: тихо."

### F.13 — cfo_043: Seasonal pattern (когато data > 12 месеца)

**Output:** "Септ-Ноем + Март-Май са пикови. Лятото слабо (-30%)."

### F.14 — cfo_044: Utilization rate (за service capacity)

```php
// За инструктори, психолози, учители: 
// Колко % от capacity-та е използвано?
function calcUtilization(int $tenant_id): float {
    $sessions_completed = countMovementsThisWeek($tenant_id, 'service_income');
    $estimated_capacity = $tenant->expected_weekly_sessions ?? 20;
    return ($sessions_completed / $estimated_capacity) * 100;
}
```

**Output:** "Тази седмица: 12/20 часа (60% utilization). Място за +8 клиента."

### F.15 — cfo_045: Inflation creep alert

**Trigger:** Same category +10%+ за 6 месеца.

**Output:** "Гориво: €83/мес (+14% за 6 мес.). БГ средно: +12%."

---

# §31.7 ACTUAL VALUES FROM ACCOUNTING SOFTWARE

## 31.7.1 Концепция

User-и които ползват счетоводен софтуер (Sigma, Microinvest, Ajur) могат да **подават точни числа** в Pocket CFO — voice или photo. Системата калибрира приблизителните оценки с реалните данни.

## 31.7.2 Onboarding flag

```
ОНБОРДИНГ СТЪПКА 4 (само за template "Малък търговец"):

"Ползваш ли счетоводна програма за бизнеса си?"

○ Не ползвам
○ Да — Microinvest
○ Да — Sigma  
○ Да — Ajur
○ Да — друга

[Продължи]
```

Запазва в:

```sql
ALTER TABLE tenants
  ADD COLUMN has_accounting_software BOOLEAN DEFAULT FALSE,
  ADD COLUMN accounting_software_name VARCHAR(50) NULL,
  ADD COLUMN actual_values_reminder_day INT DEFAULT 5;
```

## 31.7.3 Actual values log table

```sql
CREATE TABLE actual_values_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    
    -- За кой период
    period_year INT NOT NULL,
    period_month INT NULL,           -- NULL ако quarterly/annual
    period_quarter INT NULL,         -- 1-4 ако quarterly
    
    -- Какво се update-ва
    metric ENUM(
      'actual_revenue',              -- точен оборот
      'actual_expenses',             -- точни разходи
      'actual_profit',               -- точна печалба
      'actual_margin_pct',           -- точен марж %
      'actual_discount_total',       -- точни отстъпки
      'actual_cogs',                 -- точна себестойност
      'actual_vat_paid',             -- точен ДДС
      'actual_tax_paid',             -- точен данък
      'actual_social_security',      -- осигуровки
      'actual_other'
    ) NOT NULL,
    
    value DECIMAL(12,2),
    
    -- Източник
    source ENUM(
      'voice_input',                 -- гласово
      'photo_software_screen',       -- snap on Sigma/Microinvest screen
      'photo_accountant_doc',        -- snap на счетоводен документ
      'pdf_upload',                  -- upload of PDF
      'manual_entry'                 -- ръчно
    ) NOT NULL,
    
    voice_transcript TEXT NULL,
    photo_path VARCHAR(255) NULL,
    source_software VARCHAR(50) NULL,
    
    -- Audit
    confidence DECIMAL(3,2) DEFAULT 1.0,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE INDEX idx_unique_period (tenant_id, period_year, period_month, period_quarter, metric)
);
```

## 31.7.4 Voice flow

```
🎤 User гласово (обикновено на 5-6 число всеки месец след счетоводен отчет):
   "Оборотът за май беше 4280, маржът 32 процента, 
    отстъпки за 280 евро"
   ↓
AI parsва (3 числа):
   - actual_revenue: 4280 EUR (period: May 2026)
   - actual_margin_pct: 32 (period: May 2026)
   - actual_discount_total: 280 EUR (period: May 2026)
   ↓
Confirmation card:
   ✅ Оборот май: €4 280
   ✅ Марж май: 32%
   ✅ Отстъпки: €280
   
   Изчислена точна печалба: €1 370
   (вместо приблизителната €1 437)
   
   [Запази] [Поправи]
```

## 31.7.5 Photo flow — snap on accounting screen

```
📷 User отваря Sigma на компютъра → snap-ва extract screen
   ↓
[Camera in Pocket CFO]
   ↓
Gemini Vision parsва extracted screen:
   {
     "software_detected": "Sigma",
     "period": "May 2026",
     "revenue": 4280.00,
     "expenses": 2910.00,
     "profit": 1370.00,
     "vat": 856.00,
     "confidence": 0.94
   }
   ↓
Confirmation card → Save към actual_values_log
```

## 31.7.6 Annual reminder (счетоводител workflow)

```
В януари-март:
   Push notification:
   "📅 Време е за годишния отчет от счетоводителя!
    Когато получиш декларацията чл. 50, snap или 
    кажи 4 числа:
    1. Годишен оборот
    2. Признати разходи  
    3. Данъчна печалба
    4. Платен данък
    
    Това ще калибрира всичките ни оценки за {year}."
```

Voice input:
```
🎤 "Годишен оборот 48 000, разходи 32 000, печалба 16 000, данък 1 600"
   ↓
INSERT 4 records в actual_values_log
   ↓
Auto-recalibration:
   - actual_markup_pct = (revenue - expenses) / expenses * 100
                        = (48000 - 32000) / 32000 * 100 = 50%
   ↓
"Реалният ти markup за 2025 е 50%.
 Onboarding default беше 65%.
 Да обновя ли default-а за 2026?" [Да] [Не]
```

## 31.7.7 UI promenia — verified vs estimated

```
ПРЕДИ input (estimated):
   Прибл. печалба май: €1 437 ⓘ
                              ↑ tap → "При markup 65%"

СЛЕД input (actual):
   ✓ Печалба май: €1 370 (от Сигма)
   ↑ зелена иконка = verified

ГОДИШЕН ОБОБЩЕН VIEW:
   2026:
   Я   ⓘ €1 250  Прибл.
   Ф   ⓘ €1 320  Прибл.
   М   ⓘ €1 380  Прибл.
   А   ✓ €1 415  Verified (от Sigma)
   М   ✓ €1 370  Verified (от Sigma)
```

## 31.7.8 Reconciliation insight

```sql
-- AI insight cfo_046: Estimate vs Actual variance

SELECT 
    period_month,
    actual.value AS actual_profit,
    estimated.estimated_profit AS our_estimate,
    ABS(actual.value - estimated.estimated_profit) AS variance,
    ROUND(ABS(actual.value - estimated.estimated_profit) / actual.value * 100, 1) AS variance_pct
FROM actual_values_log actual
LEFT JOIN (
    SELECT 
        DATE_FORMAT(occurred_at, '%m') AS month,
        SUM(estimated_profit) AS estimated_profit
    FROM money_movements
    WHERE tenant_id = :t
    GROUP BY DATE_FORMAT(occurred_at, '%m')
) estimated ON estimated.month = actual.period_month
WHERE actual.tenant_id = :t
  AND actual.metric = 'actual_profit'
  AND actual.period_year = YEAR(NOW())
HAVING variance_pct > 10
```

**Output:** "Реалните числа от Sigma за май: €1 370. Ние сметнахме €1 437. Разлика 4.9%. Markup-ът ти е по-нисък."

---

# §29.9 ACCOUNTANT INTEGRATION (LIGHTWEIGHT, ANNUAL)

## 29.9.1 Принципът

Обикновеният БГ счетоводител прави **минимум** — само това което е задължително за НАП:
- Месечна декларация-образец 6 (за ДДС регистрирани)
- Годишна декларация по чл. 50

Той НЕ дава:
- ❌ Margin по категории
- ❌ Top performers
- ❌ Trends
- ❌ Business intelligence

**Pocket CFO попълва тази дупка.** Не дублираме счетоводителя — допълваме го.

## 29.9.2 От счетоводителя ни трябва само 4 числа (годишно)

```
В януари-март всеки година:

1. Годишен оборот               (€48 000)
2. Признати разходи             (€32 000)
3. Данъчна печалба              (€16 000)
4. Платен данък                 (€1 600)
```

Това е достатъчно за **пълна калибрация** на всички оценки в Pocket CFO.

## 29.9.3 Onboarding flag

```
"Имаш ли счетоводител?"
○ Не — сам си водя
○ Да — праща ми годишна декларация
○ Да — праща ми месечни справки

ALTER TABLE tenants
  ADD COLUMN has_accountant BOOLEAN DEFAULT FALSE,
  ADD COLUMN accountant_frequency ENUM('annual_only','monthly_too') DEFAULT 'annual_only';
```

## 29.9.4 Smart reminder timing

```php
// cron/cfo-accountant-annual-reminder.php
// Run daily

$today = date('Y-m-d');
$year = (int) date('Y');

// През януари-март: напомняй за годишен отчет от миналата година
if (in_array((int)date('n'), [1, 2, 3])) {
    $tenants = DB::query("
        SELECT t.id FROM tenants t
        WHERE t.has_accountant = TRUE
          AND NOT EXISTS (
            SELECT 1 FROM actual_values_log a
            WHERE a.tenant_id = t.id
              AND a.period_year = ? - 1
              AND a.metric = 'actual_revenue'
          )
    ", [$year])->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tenants as $tenant_id) {
        // Reminder once a week през януари-март
        $last_reminded = DB::query("
            SELECT MAX(sent_at) FROM notifications
            WHERE tenant_id = ? AND type = 'accountant_annual'
        ", [$tenant_id])->fetchColumn();
        
        if ($last_reminded && (time() - strtotime($last_reminded)) < 7*24*3600) {
            continue;  // sent within last 7 days
        }
        
        sendPushNotification($tenant_id, [
            'title' => '📊 Време за годишен отчет',
            'body' => "Когато получиш декларацията за " . ($year - 1) . 
                      ", snap или кажи 4 числа. Ще калибрирам всичко."
        ]);
    }
}
```

## 29.9.5 Photo upload на годишна декларация чл. 50

```
📷 User snapsва годишната декларация
   ↓
Gemini Vision чете specifically чл. 50 формат:
   {
     "year": 2025,
     "total_revenue": 48000.00,
     "recognized_expenses": 32000.00,
     "taxable_profit": 16000.00,
     "tax_amount": 1600.00,
     "confidence": 0.92
   }
   ↓
Save 4 records в actual_values_log
   ↓
Auto-calibration trigger:
   actual_markup_2025 = (48000-32000) / 32000 = 50%
   ↓
Update tenant_template:
   "Реалният markup за 2025 е 50%.
    Onboarding default беше 65%.
    Да обновя ли за 2026?" [Да] [Не]
```


# §41. FINAL v1.2 SUMMARY

## 41.1 Какво добави v1.2 спрямо v1.1

| Секция | Описание | Реда |
|---|---|---|
| §37 | Self-Learning Engine | ~600 |
| §38 | Data Flywheel | ~400 |
| §39 | Cost Optimization | ~200 |
| §40 | Anti-Halucination Framework | ~400 |
| §29.7 | Hidden Costs Discovery (lifestyle interview) | ~300 |
| §29.8 | БГ Calendar (60+ events) | ~500 |
| §29.9 | Accountant Integration (lightweight) | ~150 |
| §30.8 | Money movements reasons breakdown | ~250 |
| §31.7 | Actual Values from accounting software | ~300 |
| §32.7 | 15 нови AI теми (BI layer) | ~400 |
| **TOTAL** | **All v1.2 additions** | **~3 500** |

## 41.2 Total Bible size

```
v1.0 (S148 first):    5 371 редa
v1.1 (Pocket CFO):    8 552 реда (+3 181)
v1.2 (this update):  ~12 000 реда (+~3 500)
```

## 41.3 Финалeн scope на Pocket CFO

### ✅ INCLUDED (Phase B Launch)

**Core engine:**
- money_movements universal table
- Voice + Photo input (Whisper + Gemini Vision)
- 45 AI теми с anti-halucination
- Self-learning engine (PHP-first parsing)
- Confidence routing + audit trail

**Onboarding:**
- 9 professional templates
- Hidden costs lifestyle interview
- БГ calendar activation
- Accounting software flag
- Accountant integration flag

**Business intelligence (вече):**
- Operating profit
- Net cash position  
- Working capital split
- Top categories по приходи
- Bottom performers
- Period comparisons (WoW, MoM, YoY)
- Anomaly detection
- Sparkline trends
- Hourly patterns (за services)
- Utilization rate (за capacity-based)
- Discount tracking
- ДДС tracking (optional)

**Hidden costs:**
- 3-layer detection (onboarding + auto + БГ calendar)
- 60+ БГ calendar events
- Subscription auto-detection (2-3 occurrences)
- Annual cost amortization (split per month view)

**Accountant flow:**
- Annual reminder January-March
- Photo upload of declaration (chl. 50)
- 4-number calibration
- Markup auto-update

**Self-learning:**
- Vendor aliases (tenant + universal)
- Category keywords (БГ)
- Universal vendor promotion (5+ tenants)
- Cross-product validation (RunMyStore suppliers)

**Cost optimization:**
- Month 1: €0.10/user AI cost
- Month 6: €0.04/user (60% reduction)
- Month 12: €0.025/user (75% reduction)

**Anti-halucination:**
- PHP смята, AI говори
- AI silent, PHP continues
- Audit trail mandatory
- Confidence routing
- Anti-repetition

### ❌ EXCLUDED (Phase D+ или RunMyStore territory)

- Products + inventory
- POS / касов апарат / Z-отчет
- Sales detailed tracking (sale_items)
- Deliveries + suppliers (нов стокa flow)
- B2B / wholesale invoicing
- Multi-store
- Multi-user roles (Owner/Manager/Seller)
- Bank statement upload (Phase 2)
- PSD2 Open Banking (Phase 3+)
- Forced upgrade prompts
- COGS calculation от cost_at_sale

## 41.4 Готови за имплементация

Bible v1.2 е **финалeн blueprint** за code-имплементация.

Следваща стъпка: **Mockups + код**.

```
═══ ФАЗА B1: ENGINE ═══ (1 седмица)
- DB migrations
- money-engine.php
- voice-parser-v2.php (PHP-first)
- photo-receipt-parser.php
- ner-anonymizer.php

═══ ФАЗА B2: UI ═══ (1 седмица)
- cfo/home.php
- cfo/records.php
- cfo/analysis.php
- cfo/goals.php
- cfo/onboarding.php (със hidden costs interview)
- cfo/settings.php

═══ ФАЗА B3: AI ═══ (3 дни)
- 45 AI теми (selectXxx functions)
- Learning loop cron jobs
- БГ calendar reminders
- Anti-halucination framework

═══ ФАЗА B4: MOBILE ═══ (2 дни)
- Capacitor build
- Voice permissions
- Photo camera integration

═══ TOTAL: ~2-3 седмици active dev ═══
═══ + 2 седмици beta тест                ═══
═══ + 1 седмица Google Play submission   ═══
═══ ───────────────────────────────────  ═══
═══ = 5-6 седмици до production launch   ═══
```

---

# 🏁 КРАЙ НА BIBLE v1.2

**Pocket CFO е готов за имплементация.**

Документът е САМОДОСТАТЪЧЕН — не изисква външни референции освен:
- DESIGN_SYSTEM_v4.0_BICHROMATIC.md (Sacred Glass canon)
- mockups/wizard_v6_INTERACTIVE.html (design pattern reference)
- TECHNICAL_ARCHITECTURE_v1.md (RunMyStore brain reference)

Следваща задача: **POCKET_CFO_TRACKER.md** + **POCKET_CFO_BRIEF_CC-A.md** + **POCKET_CFO_BRIEF_CC-B.md** за start на work.
