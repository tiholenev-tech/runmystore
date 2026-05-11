# COMPETITOR INSIGHTS — Trade Master (БГ-СОФТ)

**Дата:** 2026-05-11
**Източник:** Trade Master User Manual (анализиран от Тих, дискутиран в S140)
**Цел:** Да не забравяме полезни features от established БГ retail software когато стигаме до съответните модули.

---

## КАК ДА ПОЛЗВАШ ТОЗИ ДОКУМЕНТ

Когато започваш редизайн / нова имплементация на модул от **Column 2** (Module),
**SCAN този документ** за features свързани с модула. Виж приоритет и фаза.
Имплементирай 🟢 като част от Beta plan, 🟡 като част от Post-Beta polish.

---

## 🟢 PRIORITY 1 — В Beta или скоро след нея

| # | Feature | Module | Phase | Implementation note |
|---|---------|--------|-------|---------------------|
| 1 | **Кредитен лимит + отложено плащане в дни** | `customers.php` (нов) + `sale.php` | A2/A3 (PRO+) | Полета `credit_limit`, `payment_terms_days` на партньор. При sale.php auto-пресмята `due_date = created_at + payment_terms_days`. AI warning при превишен лимит. |
| 2 | **Ценова група per партньор** | `customers.php` + `sale.php` | A2 (beta extension) | `partner.price_group` ENUM('retail','wholesale','custom_N'). При sale.php auto-избира правилната цена. Schema: `products.price_retail`, `products.price_wholesale`, `partner_custom_prices` table. |
| 3 | **Лица за контакт + рожден ден reminder** | `customers.php` + AI brain | A3 | `partner_contacts(id, partner_id, name, role, egn, phone, email, dob)`. AI insight: "Иван от ENI има рожден ден утре — изпрати поздрав". |
| 4 | **Алтернативна мярка (кашон/опаковка)** | `products.php` + `sale.php` + `deliveries.php` | A2 (за wholesale tenants beta) | `products.alt_unit_name`, `products.alt_unit_ratio` (12 = 12 бр/кашон). Sale.php numpad pill "× кашон" автоматично × ratio. Deliveries.php приема в кашони, auto-convert към бр. |
| 5 | **Гаранционен срок per артикул** | `products.php` (wizard) + `sale.php` (касов бон) + `warranty.php` (нов) | B | `products.warranty_months` (default per category). При продажба auto-печат на гаранционна карта с QR код. Нов модул warranty.php за tracking. |

---

## 🟡 PRIORITY 2 — Post-Beta (Phase C/D)

| # | Feature | Module | Phase | Implementation note |
|---|---------|--------|-------|---------------------|
| 6 | **Неприключени продажби (B2B monthly billing)** | `sale.php` + `customers.php` | B/C | `sales.status` ENUM добавя `'open'` (държиш отворен документ, добавяш позиции през деня/месеца). UI: "Започни нова или продължи open за този клиент?" Финализация = fact generation. |
| 7 | **3 типа разходи (държавна такса / признат / непризнат)** | `expenses.php` (нов) | C | `expense_type` ENUM. За tax справки. Разходни групи в дървовидна структура. |
| 8 | **Каса multi-close per day** | `cash_register.php` (нов) | A3/B | Касата може да се приключва безброй пъти/ден. История на shift-ове. Полезно за смени с различни касиери. |
| 9 | **VIP флаг на партньор** | `customers.php` | A3 | `partner.is_vip` BOOLEAN. UI показва VIP отгоре в списъци, със звездичка. |
| 10 | **Холдинг полета** | `customers.php` | C | `partner.parent_partner_id` (self-ref). За reporting: "ABC ООД холдинг → всички 12 subsidiaries". |
| 11 | **Резервации на стока с deadline** | `reservations.php` (нов, след online) | D | `reservations(id, product_id, partner_id, qty, reserved_until)`. След online магазина — UI за блокиране на склад докато клиент плати онлайн. |

---

## 🔴 ОТКАЗВАМЕ (защо)

| Feature | Защо НЕ го взимаме |
|---------|---------------------|
| LIFO | Не препоръчителен в БГ за fashion. FIFO е стандарт. Сложност без полза. |
| "Фиктивен документ" flag | Tax avoidance enabler. Принципно отказваме. |
| 2 паралелни склада (счетоводен + действителен) | Overkill за малки магазини. Прави UX confusing. Имаме един клад = truth. |
| Многофирмена интегрираност в един склад | Имаме multi-tenant архитектура вместо това (по-чисто). |
| Глобален символ `*` за търсене | Нашият AI прави natural language search ("намери черни обувки 42"). По-добре. |
| Drag-to-group, drag-to-filter в гридове | Desktop-era UX. Mobile-first ние сме. |
| Edit директно в грид | Опасно UX без undo. Trade Master може защото има transaction log; ние имаме AI confirm pattern. |
| Multiple валути в каса | БГ е €/лв само (S73+ dual currency до 8.8.2026, после само €). |
| Експорт към Excel за всичко | AI чат replace-ва това. "Дай ми оборота на Adidas от март" → AI отговаря в чата. |
| F1-F12 функционални клавиши | Mobile/voice-first — клавиши не existují. |

---

## 🏆 СТРАТЕГИЧЕСКИ УРОК

Trade Master казва: *"Програмата дава оперативна счетоводна информация, която да помага при вземането на управленски решения, а не за справки пред НАП."* — **точно нашата позиция.**

**Те са създадени преди 15 години, ние идваме с AI:**
- Te имат "оборот по период" → Ние имаме *"Adidas 42 ще свърши след 6 дни — поръчай"*
- Те имат "задължения по партньор" → Ние имаме *"ENI е на ръба на кредитния лимит, не пускай нова доставка"*
- Те имат "стойност на склад" → Ние имаме *"45 000 € замразен капитал в zombie артикули, ето листа за намаление"*

**Бием ги на проактивност.** Те reactive (питаш → отговаря). Ние proactive (показваме без да питаш).

---

## TABLE SCHEMA EXTENSIONS NEEDED (за Priority 1)

```sql
-- За #1 (кредитен лимит + отложено плащане)
ALTER TABLE tenant_partners
  ADD COLUMN credit_limit DECIMAL(12,2) NULL,
  ADD COLUMN payment_terms_days SMALLINT UNSIGNED DEFAULT 0;

ALTER TABLE sales
  ADD COLUMN due_date DATE NULL,
  ADD COLUMN credit_status ENUM('within','warning','exceeded') DEFAULT 'within';

-- За #2 (ценова група per партньор)
ALTER TABLE tenant_partners
  ADD COLUMN price_group ENUM('retail','wholesale','custom') DEFAULT 'retail';

ALTER TABLE products
  ADD COLUMN price_wholesale DECIMAL(10,2) NULL;

CREATE TABLE partner_custom_prices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    partner_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    valid_from DATE NULL,
    valid_to DATE NULL,
    UNIQUE KEY uniq_partner_product (partner_id, product_id),
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (partner_id) REFERENCES tenant_partners(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- За #3 (лица за контакт)
CREATE TABLE partner_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    partner_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    role VARCHAR(60) NULL,           -- 'МОЛ', 'Получаващ', 'Snabdetel', и т.н.
    egn CHAR(10) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(120) NULL,
    dob DATE NULL,                   -- birth date (auto от ЕГН ако е popullenо)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_partner (partner_id),
    INDEX idx_dob_mmdd ((MONTH(dob)), (DAY(dob))),  -- за birthday queries
    FOREIGN KEY (partner_id) REFERENCES tenant_partners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- За #4 (алтернативна мярка)
ALTER TABLE products
  ADD COLUMN alt_unit_name VARCHAR(20) NULL,           -- 'кашон', 'пакет', 'комплект'
  ADD COLUMN alt_unit_ratio DECIMAL(8,3) NULL;         -- 12.000 = 12 бр/кашон

-- За #5 (гаранционен срок)
ALTER TABLE products
  ADD COLUMN warranty_months SMALLINT UNSIGNED NULL;

CREATE TABLE warranties (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    sale_item_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    serial_number VARCHAR(80) NULL,
    sold_at DATE NOT NULL,
    expires_at DATE NOT NULL,
    status ENUM('active','expired','claimed','void') DEFAULT 'active',
    qr_code VARCHAR(120) NULL,                          -- public URL за клиент
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at, status),
    INDEX idx_customer (customer_id),
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## NOTES

- Имплементацията може да става incremental — едно по едно от 🟢.
- При нов модул който НЕ е в списъка → отвори този документ, виж дали Trade Master има свързан feature.
- За документация на full functionality виж оригиналния manual в attachments или http://www.bg-soft.com/forums/tm/

---

**Created:** 2026-05-11 (S140 EOD)
**Last reviewed:** —
