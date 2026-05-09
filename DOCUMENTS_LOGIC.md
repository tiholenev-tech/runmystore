# 📄 DOCUMENTS_LOGIC.md — Поведенческа спецификация на 16-те типа документи

**Версия:** v1.1
**Дата:** 09.05.2026 (v1.1 — decisions baked)
**Статус:** FINALIZED — ready for implementation
**Свързан с:** RWQ-88..94, миграция `db/migrations/2026_05_documents.up.sql`
**Прочети заедно с:** `MASTER_COMPASS.md` Standing Rules, `SIMPLE_MODE_BIBLE_v1.3`, `DESIGN_SYSTEM_v4.0_BICHROMATIC.md`

---

## 0. ЗАКОНИ (read first, never forget)

**Закон №1 — ЗДДС / ППЗДДС чл. 78 е sacred.** 10-разрядна номерация без пропуски без дублиране. Една серия за фактури + кредитни известия + дебитни известия + протоколи. При commit на нов данъчен документ системата НЕ позволява пропуски (race condition защита със SELECT FOR UPDATE).

**Закон №2 — Никога не изтриваме данъчен документ.** Анулирането е status промяна (`status='annulled'`), не DELETE. Audit trail в `documents.cancelled_at` + `cancel_reason`. Дори анулирани, номерата им не се преизползват.

**Закон №3 — Snapshot на партньора.** При издаване на данъчен документ (фактура, известие, протокол), `documents.partner_legal_name` + `partner_eik` + `partner_address` се копират от `partners` като snapshot. Ако партньорът се преименува след години, документът показва историческото име.

**Закон №4 — Auto-detect преди manual choice.** Системата първо познава кой документ трябва (Layer 0/2 от RWQ-91). Manual choice е fallback, не дефолт. Пешо никога не вижда 16 типа в меню.

**Закон №5 — Документите живеят централно (`documents` таблица), но се раждат контекстно.** sale.php / deliveries.php / returns.php са creation points. `documents.php` + partner детайл са recording points.

**Закон №6 — Транзакционна цялост.** Всеки документ който генерира финансов запис (фактура, проформа платена, кредитно известие) се пише в една transaction с свързаните `sales` / `inventory_events` / `payments`. При fail → ROLLBACK всичко.

**Закон №7 — PDF е immutable artifact.** Веднъж генериран PDF (`documents.pdf_path`) не се модифицира. Корекция = нов документ (кредитно известие).

---

## 1. 16-те типа документи — пълна референция

| # | doc_type | БГ име | Категория | Trigger | Кой издава | Кой получава | UI слой |
|---|---|---|---|---|---|---|---|
| 1 | `cash_receipt` | Касов/фискален бон | tax | Auto при retail продажба | Системата (СУПТО) | Физическо лице | Layer 0 |
| 2 | `invoice` | Фактура | tax | Auto при wholesale ИЛИ при бутон „ФАКТУРА" в retail | Системата | B2B / институция | Layer 1/2 |
| 3 | `credit_note` | Кредитно известие | tax | Auto при връщане на B2B стока | Системата | Същия купувач | Layer 0/2 |
| 4 | `debit_note` | Дебитно известие | tax | AI команда / manual в documents.php | Owner/Manager | Същия купувач | Layer 3/4 |
| 5 | `storno_receipt` | Сторно касова бележка | cash_receipt | Auto при retail връщане | Системата (СУПТО) | Физическо лице | Layer 0 |
| 6 | `protocol_117` | Протокол по чл. 117 ЗДДС | tax | Manual при ВОП / услуга от чужбина | Owner | Самоиздаване | Layer 4 |
| 7 | `proforma` | Проформа фактура | proforma | AI команда „проформа" / B2B preview | Системата | B2B клиент | Layer 1/3 |
| 8 | `goods_note` | Стокова разписка | goods_note | Auto при доставка / B2B продажба | Системата | Купувач/доставчик | Layer 0 |
| 9 | `storage_note` | Складова разписка | storage_note | Auto при приемане в склад | Системата | Materially responsible | Layer 0 |
| 10 | `cash_order_in` | Приходен касов ордер | cash_order | Manual / cash deposit | Owner | Касова отчетност | Layer 4 |
| 11 | `cash_order_out` | Разходен касов ордер | cash_order | Manual / cash withdrawal | Owner | Касова отчетност | Layer 4 |
| 12 | `transfer_protocol` | Приемо-предавателен протокол | transfer_protocol | Manual / при доставка с late receipt | Manager | Получател | Layer 4 |
| 13 | `warranty` | Гаранционна карта | warranty | Auto при продажба на `products.has_warranty=1` | Системата | Купувач | Layer 0/1 |
| 14 | `shipping_note` | Експедиционна бележка | shipping_note | Auto при онлайн поръчка → expedition | Системата (Ecwid hook) | Куриер | Layer 4 |
| 15 | `offer` | Оферта | offer | Manual / B2B inquiry | Manager | B2B клиент | Layer 4 |
| 16 | `order_confirmation` | Потвърждение на поръчка | offer | Auto при приет offer | Системата | B2B клиент | Layer 0 |

**Layer reference (RWQ-91):**
- Layer 0 = AUTO без избор от Пешо
- Layer 1 = 1 ВЪПРОС (bottom sheet)
- Layer 2 = AUTO-DETECT (системата избира базиран на context)
- Layer 3 = AI КОМАНДА
- Layer 4 = DETAILED MODULE (само owner/manager)

---

## 2. ТРИГЕР FLOWS (кога кой документ се генерира)

### 2.1. Retail продажба (95% случаи)

```
Пешо → сканира 3 продукта → „Плати" натиснато
  ↓
TRANSACTION BEGIN
  ↓ INSERT sales (sale_mode='retail', partner_id=NULL)
  ↓ INSERT documents (doc_type='cash_receipt', auto_generated=1)
  ↓ INSERT document_items (3 реда)
  ↓ UPDATE document_series.current_number += 1 (категория='cash_receipt')
  ↓ INSERT inventory_events (3 sale events)
  ↓ AKO any product has_warranty=1:
    → INSERT documents (doc_type='warranty', parent_doc_id=cash_receipt.id, auto_generated=1)
TRANSACTION COMMIT
  ↓
Bottom sheet → [ФАКТУРА] [ГАРАНЦИЯ] [ВРЪЩАНЕ] [НЕ] (default focus НЕ)
  ↓ ако Пешо натисне „ФАКТУРА" в рамките на 5 мин:
    → AUTO-CONVERT: INSERT documents (doc_type='invoice', parent_doc_id=cash_receipt.id, partner_id=...)
    → UPDATE sales.primary_document_id = invoice.id
    → original cash_receipt остава (двата документа за същата продажба)
```

### 2.2. Wholesale продажба (B2B)

```
Пешо превключва mode → „На едро" (color change)
  ↓
Задължителен partner search преди checkout (ЕИК / име)
  ↓ ако ЕИК не е в partners → AUTO-FETCH от eik_cache → ако кеш miss → fetch от Български търговски регистър
  ↓ INSERT partners (is_b2b=1, is_customer=1) ИЛИ UPDATE existing
  ↓
Активна invoice серия се показва в header
  ↓ Пешо сканира 5 продукта → „Плати"
  ↓
TRANSACTION BEGIN
  ↓ SELECT ... FOR UPDATE document_series WHERE document_category='tax' AND is_active=1
  ↓ INSERT sales (sale_mode='wholesale', partner_id=...)
  ↓ INSERT documents (doc_type='invoice', auto_generated=1, partner_id=..., partner_legal_name=snapshot)
  ↓ INSERT document_items (5 реда с VAT calc)
  ↓ INSERT documents (doc_type='goods_note', parent_doc_id=invoice.id) — auto stokova razpiska
  ↓ UPDATE document_series.current_number += 1
  ↓ UPDATE sales.primary_document_id = invoice.id
  ↓ INSERT inventory_events
TRANSACTION COMMIT
  ↓
Bottom sheet → [ПРОФОРМА] [ГАРАНЦИЯ] [ВРЪЩАНЕ] [НЕ] (default focus НЕ)
  ↓ фактурата вече е генерирана; първият бутон е ПРОФОРМА (за бъдеща поръчка)
```

### 2.3. Връщане (returns)

#### 2.3.1. Retail return
```
Пешо: „искам да върна нещо"
  ↓ AI: „сканирай касовия бон или ми го прочети"
  ↓ AI разпознава касов бон номер → fetch documents WHERE doc_type='cash_receipt'
  ↓
TRANSACTION BEGIN
  ↓ INSERT documents (doc_type='storno_receipt', parent_doc_id=cash_receipt.id, auto_generated=1)
  ↓ INSERT inventory_events (return events)
  ↓ UPDATE original cash_receipt.status — НЕ се сменя (storno е separate document)
TRANSACTION COMMIT
  ↓ Cash refund от каса
```

#### 2.3.2. Wholesale return (B2B)
```
Пешо/Митко: „клиент Х връща стока от фактура Y"
  ↓ Search documents WHERE doc_type='invoice' AND partner_legal_name LIKE 'Х%'
  ↓ Select invoice → choose lines being returned + quantities
  ↓
TRANSACTION BEGIN
  ↓ SELECT ... FOR UPDATE document_series (категория='tax')
  ↓ INSERT documents (doc_type='credit_note', parent_doc_id=invoice.id, auto_generated=0)
  ↓ INSERT document_items (negative quantities за върнатите редове)
  ↓ UPDATE document_series.current_number += 1
  ↓ INSERT inventory_events (return)
  ↓ UPDATE invoice.payment_status — изчислява се (paid - credit_note total)
TRANSACTION COMMIT
```

### 2.4. Доставка (deliveries.php)

```
Доставчик пристига → Пешо/Митко в deliveries.php → нова доставка
  ↓ partner search (доставчика, who is_supplier=1)
  ↓ Сканират/въвеждат продукти от accompanying invoice от доставчика
  ↓ „Запази"
  ↓
TRANSACTION BEGIN
  ↓ INSERT deliveries (partner_id=...)
  ↓ INSERT delivery_items
  ↓ INSERT documents (doc_type='goods_note', auto_generated=1, partner_id=..., delivery_id=...)
  ↓ INSERT documents (doc_type='storage_note', auto_generated=1, parent_doc_id=goods_note.id)
  ↓ INSERT inventory_events (delivery events)
TRANSACTION COMMIT
  ↓
Owner може да закачи на ръка фактурата от доставчика като PDF (external_invoice_pdf_path)
  → нова таблица supplier_invoices (post-beta enhancement)
```

### 2.5. Проформа → платена → конверсия

```
B2B клиент пита: „искам оферта/проформа"
  ↓ AI: „за какво?" → Пешо описва или сканира
  ↓
INSERT documents (doc_type='proforma', auto_generated=1, status='draft', partner_id=...)
INSERT document_items
documents.due_date = today + 30 days
  ↓ Print/email към клиент
  ↓
[Дни по-късно] клиент плаща → собственикът маркира proforma.payment_status='paid'
  ↓ AUTO-CONVERT trigger:
    → INSERT documents (doc_type='invoice', parent_doc_id=proforma.id, всички items копирани)
    → UPDATE proforma.status='converted'
```

### 2.6. AI команди (Layer 3)

| User natural language | Intent recognition | Action |
|---|---|---|
| „издай фактура за тая продажба" | issue_invoice(sale_id) | Layer 1 conversion |
| „издай дебитно известие към фактура 123" | issue_debit_note(invoice_id=123) | INSERT debit_note + UI prompt за reason + amount |
| „издай проформа за партньор Х" | issue_proforma(partner_name='Х') | Start proforma wizard |
| „анулирай фактура 456" | annul_invoice(456) | UPDATE status='annulled' + reason prompt |
| „върни тия 3 неща от фактура 789" | return_invoice_items(789) | Start credit_note flow |
| „превключи на едро" | switch_sale_mode('wholesale') | Mode toggle in sale.php |

---

## 3. STATE MACHINES

### 3.1. `documents.status`

```
draft → issued → sent → paid
  ↓        ↓
  cancelled (преди issue, free)
           ↓
         annulled (след issue, audit kept, не се преизползва номер)
```

Преходи:
- `draft → issued`: Първи print или send или explicit „Издай"
- `issued → sent`: Email-нат / handed to recipient
- `issued/sent → paid`: payment_status='paid' set
- `draft → cancelled`: Free delete-able
- `issued/sent → annulled`: Audit-only, номерът остава blocked

### 3.2. `documents.payment_status` (за фактури/проформи/credit_notes)

```
unpaid → partial → paid
   ↓        ↓
   overdue (auto при due_date < today)
   ↓
  refunded (при credit_note total >= invoice total)
```

---

## 4. RACE CONDITION ЗАЩИТИ

### 4.1. Number assignment (CRITICAL)

**Без защита:** Двa concurrent B2B sale-а в 2 store-a получават еднакъв номер → дублиране → ЗДДС нарушение.

**С защита:**
```sql
BEGIN;
SELECT id, current_number FROM document_series 
  WHERE tenant_id=? AND document_category='tax' AND is_active=1 
  FOR UPDATE;
-- (тук другата connection чака)
INSERT INTO documents (..., numeric_number = current_number, full_number = LPAD(current_number, 10, '0')) ...;
UPDATE document_series SET current_number = current_number + 1 WHERE id=?;
COMMIT;
```

При ROLLBACK numerator се връща → не се пропуска номер.

### 4.2. Range exhaustion

Когато `current_number > end_number` за активна серия → системата блокира нови документи + alert на owner: „Активната серия се изчерпа, превключи на нова". Owner влиза в settings → маркира друга `is_active=1`.

### 4.3. Concurrent series locking

`document_series.is_locked=1` се set-ва автоматично след първия INSERT. След това `start_number` не може backwards. UI показва lock indicator. Само master tenant_admin role може unlock с reason audit.

---

## 5. FACT VERIFIER ПРАВИЛА

Преди INSERT на данъчен документ, AI brain валидира:

| Rule | Check | Action на fail |
|---|---|---|
| FV-001 | partner_eik passes ЕИК checksum | Block + ask Пешо да re-confirm |
| FV-002 | invoice_total != 0 | Block — empty invoice не се позволява |
| FV-003 | sum(document_items.line_total) == documents.total_amount | Block — math drift |
| FV-004 | vat_amount == subtotal * vat_rate / 100 (±0.01 tolerance) | Block — VAT calc bug |
| FV-005 | parent_doc_id (за credit/debit) реално съществува и не е annulled | Block — orphan note |
| FV-006 | credit_note total <= invoice total - sum(прилож. credit_notes) | Block — over-credit |
| FV-007 | Партньорът е VAT registered ако фактурата е BG-to-BG B2B | Soft warn (legal but unusual) |

Всеки fail се записва в `documents.notes` (за отказан INSERT) или logs.

---

## 6. EDGE CASES

### 6.1. Същият партньор е и доставчик и клиент

`partners.is_supplier=1 AND is_customer=1`. При nav в partners.php детайл — показва 2 секции (история на покупки от тях + история на продажби към тях).

### 6.2. Фактура за смесена кошница (някои с гаранция, други без)

Един invoice + множество warranty документи (по един per продукт с has_warranty=1), всички parent_doc_id=invoice.id.

### 6.3. Частично връщане на B2B продажба

Multiple credit_notes може да висят на един invoice. Validation: SUM(credit_note totals) <= invoice total.

### 6.4. Фактурна серия се изчерпва при checkout

Системата при INSERT прави check `current_number <= end_number`. Ако next number би надхвърлил → REJECT с user-friendly message: „Активната серия е изчерпана. Отиди в Настройки → Документни серии за да активираш нова."

### 6.5. ENI миграция — последен номер от хартиен кочан

Setup wizard пита owner: „Какъв беше последният номер от хартиените фактури?". След отговор → `document_series` се създава с `start_number = last_paper + 1`, `current_number = start_number`, `end_number = start_number + 999_999_999`. Хартиените отиват в emergency серия.

### 6.6. Анулиране на фактура която вече има credit_note

Блокирано — owner трябва първо да анулира credit_note-а, после фактурата. Иначе orphan reference.

### 6.7. Multi-store с shared partners но различни активни серии

Един партньор може да получава фактури от store A (серия 1) и store B (серия 2). `document_series.store_id` е NULL за shared, или specific store_id за per-store.

### 6.8. Корекция на грешен документ (не е storno, не е credit/debit)

ЗДДС не позволява корекция на грешен данъчен документ — само анулиране и preиздаване. UI workflow: „Анулирай → Издай нов" с автоматично копиране на items за editing.

---

## 7. PDF GENERATION

### 7.1. Path convention

```
/var/www/runmystore/storage/documents/
  └── {tenant_id}/
      └── {YYYY}/
          └── {MM}/
              └── {doc_type}_{full_number}.pdf
```

Пример: `/var/www/runmystore/storage/documents/7/2026/05/invoice_0000000123.pdf`

### 7.2. Templates

Шаблоните са в `/var/www/runmystore/templates/documents/{doc_type}.html` (Twig или PHP heredoc, TBD в implementation сесия). Multi-language (BG/EN) per `tenants.lang`.

### 7.3. Mandatory fields per doc_type (примери)

**invoice** (ППЗДДС чл. 78):
- Гриф „ОРИГИНАЛ"
- Наименование: „ФАКТУРА"
- Номер 10-разряден
- Дата на издаване
- Доставчик: фирма + ЕИК + ВАТ + адрес
- Получател: фирма + ЕИК + ВАТ + адрес
- Items: код, наименование, мярка, к-во, ед.цена без ДДС, отстъпка, данъчна основа, ДДС%, ДДС сума
- Обща данъчна основа + Обща ДДС + Обща сума
- Основание за неначисляване (ако ДДС=0)
- QR код (по Н-18 за СУПТО — ако ENI стане СУПТО)

**credit_note**: всички полета на invoice + „Известие към фактура №X от дата Y" + основание за корекция

**proforma**: всички полета на invoice + „ПРОФОРМА ФАКТУРА — Не е данъчен документ" + due_date

### 7.4. Generation hooks

- При `documents.status='issued'` (първи print) → trigger PDF generation
- PDF се пази immutable в storage; повторни print-ове чета same file
- Email integration: `sendDocumentEmail($doc_id, $recipient)` → attaches PDF + body template
- printed_count + emailed_to се update-ват при action

---

## 8. AUDIT REQUIREMENTS

Всяко от тези събития се audit-ва:

| Събитие | Where logged | Retention |
|---|---|---|
| Document INSERT (новиздаване) | `documents.created_at` + `issued_by` | Forever |
| Status промяна | новa колона `document_status_log` (post-beta) | 7 години (ЗДДС) |
| `document_series` modification | `document_series_changes` | Forever |
| PDF print | `documents.printed_count` + log file | 1 година |
| Email send | `documents.emailed_to` (CSV append) | 1 година |
| Annul | `documents.cancelled_at` + `cancelled_by` + `cancel_reason` | Forever |

---

## 9. INTEGRATION POINTS

### 9.1. sale.php (RWQ-93)
- Mode toggle wholesale/retail
- B2B partner search блок
- Active series indicator в header
- Bottom sheet после „Плати" с 4 бутона
- AI command listener в чата

### 9.2. deliveries.php (post-beta module)
- Auto-creates goods_note + storage_note
- Manual attach на доставчиковата фактура

### 9.3. partners.php (нов модул per RWQ-89)
- Tab „Документи" в детайл — показва всички свързани documents
- Quick action „Издай проформа" / „Виж фактури"

### 9.4. AI brain
- New intents: issue_invoice, issue_credit_note, issue_proforma, annul_document, return_items
- Confidence routing: >0.85 auto-execute, 0.5-0.85 confirm, <0.5 ask
- Snapshot обогатяване: всеки issue включва partner snapshot

### 9.5. Stress testing (post-beta)
- Сценарии за всеки flow в 2.1-2.6
- Race condition tests (concurrent invoice issue)
- Number sequence integrity check (no gaps in tax category)

---

## 10. POST-BETA EXTENSIONS

| Item | Описание | RWQ |
|---|---|---|
| Recurring invoices | Месечни фактури за абонаменти | TBD |
| Електронно подписване | КЕП на PDF преди send | TBD |
| Касова отчетност | Cash_orders с pull от sale.php cash payments | TBD |
| Standardized Audit File | XML export за НАП | TBD |
| OSS фактури | Distance selling в EU | TBD |
| СУПТО декларация | Регистрация в НАП публичен списък | TBD |

---

## 11. CHECKLIST ПРЕДИ IMPLEMENTATION SESSION

- [ ] DB миграция приложена в sandbox
- [ ] ETL скрипт за миграция на старите `suppliers` + `customers` → `partners`
- [ ] Initial setup wizard за първа серия (ENI) — попитва за last_paper_number
- [ ] PDF templates създадени (16 типа, BG + EN)
- [ ] Storage директория `/var/www/runmystore/storage/documents/` създадена с правилни perms
- [ ] AI brain intents добавени в `$MODULE_ACTIONS`
- [ ] sale.php mockup P11 разширен с B2B mode UI
- [ ] partners.php mockup готов
- [ ] documents.php mockup готов (списък + детайл + filter UI)
- [ ] Stress сценарии за 16-те типа

---

## 12. DECISIONS (резолюции от 09.05.2026)

| # | Въпрос | Решение | Импликация |
|---|---|---|---|
| 1 | СУПТО декларация на ENI | **БЕЗ СУПТО** | Касов бон без QR код, без ежедневно подаване към НАП. По-проста интеграция с фискалното устройство. |
| 2 | Plащане на фактури | **Ръчно засега, post-beta hybrid** | Owner маркира ръчно `payment_status='paid'`. Stripe е само за платформа billing (тенантите плащат месечно), не за in-store. |
| 3 | Гаранционна карта при `has_warranty=1` | **Опционално (Layer 1)** | В bottom sheet след „Плати" има бутон „ГАРАНЦИЯ"; Пешо избира. Не auto-print. |
| 4 | Multi-country готовност | **Готови от старта** | `tenants.country_code` контролира видимост на БГ-специфични документи (`protocol_117`, дуално EUR/лв). Универсалните (фактура, проформа, касов бон) работят за всички country_code. |
| 5 | PDF backup | **DB + storage** | PDF-ите се пазят като файлове в `/var/www/runmystore/storage/documents/{tenant_id}/{YYYY}/{MM}/`. Backup-ът пази и DB, и storage. ~500MB/year/tenant ENI размер. |
| 6 | Number_padding гъвкавост | **Flexible с tax-restriction** | `document_series.number_padding` (TINYINT, дефолт 10) — owner може да намалява до 4 при създаване на нова серия за помощни документи. **ЗАБРАНЕНО за `document_category='tax'`** — там padding=10 е заключен по ЗДДС/ППЗДДС чл. 78. UI блокира промяна с user message. |
| 7 | Anti-fraud alerts | **Telegram + Email и двете** | При подозрителна смяна (current_number намален, lock премахнат, серия overlap) → instant Telegram chat alert + email към owner. Записва се и в `document_series_changes`. Реализацията използва `tools/stress/alerts/telegram_bot.py` от RWQ phase M. |

---

## 13. IMPLEMENTATION TIMELINE

### PRE-BETA (само абсолютните минимуми за ENI ден 1):

| Кога | Какво | Trigger |
|---|---|---|
| Преди beta launch | DB migration apply (sandbox → production) | Ръчно от owner с backup |
| Преди beta launch | ETL: миграция на старите `suppliers` + `customers` → `partners` | Скрипт от CC сесия |
| Преди beta launch | Initial setup wizard за първа `document_series` (ENI tenant_id=7, last_paper_number prompt) | Ръчно от Тихол |
| Преди beta launch | sale.php redesign с B2B mode + invoice generation (RWQ-93) | CC сесия с P11 mockup като ground truth |
| Преди beta launch | PDF templates: само 4 типа — `cash_receipt`, `invoice`, `credit_note`, `storno_receipt` | CC сесия |
| Преди beta launch | Race condition защита (SELECT ... FOR UPDATE) | Включено в sale.php save endpoint |

### EARLY POST-BETA (седмица 1-2 след 14-15.05):

| Кога | Какво | Зависимост |
|---|---|---|
| Седмица 1 | `partners.php` нов модул (RWQ-89 пълен UI) | Schema готова |
| Седмица 1 | `documents.php` нов централен модул (списък + детайл + filter) (RWQ-92) | sale.php integration работи |
| Седмица 1 | EIK lookup интеграция (RWQ-88) | partners.php готов |
| Седмица 2 | PDF templates: добавяне на `proforma`, `warranty`, `goods_note`, `storage_note` | Templates система готова |
| Седмица 2 | AI brain intents (issue_invoice, issue_credit_note, issue_proforma, annul_document) | Documents модул работи |

### LATER POST-BETA (седмица 3-6):

| Кога | Какво | Why later |
|---|---|---|
| Седмица 3 | `debit_note` UI flow в documents.php | Rare use case |
| Седмица 3 | `protocol_117` manual issue (само BG country_code) | Rare, само за внос |
| Седмица 4 | `cash_order_in/out` + касова отчетност reports | Изисква cash flow tracking infrastructure |
| Седмица 4 | `transfer_protocol` + експедиционна бележка (Ecwid hook) | Изисква orders.php + Ecwid integration |
| Седмица 5 | Анти-fraud Telegram + email alerts (decision #7) | Изисква telegram_bot.py от stress system |
| Седмица 6 | `offer` + `order_confirmation` workflow | Sales pipeline за B2B |

### Q3-Q4 2026:

| Кога | Какво |
|---|---|
| Q3 2026 | Recurring invoices (subscription billing) |
| Q3 2026 | КЕП (квалифициран електронен подпис) на PDF |
| Q4 2026 | Standardized Audit File за НАП (XML export) |
| Q4 2026 | OSS фактури (distance selling в EU) |

---

**END OF DOCUMENTS_LOGIC.md v1.1 (decisions baked, timeline added)**
