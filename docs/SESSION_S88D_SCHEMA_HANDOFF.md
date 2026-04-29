# SESSION S88D.DELIVERY.SCHEMA — HANDOFF

**Date:** 2026-04-29
**Source spec:** `DELIVERY_ORDERS_DECISIONS_FINAL.md` v3 (560 lines, 165 решения, секции A–X)
**Authority law:** `BIBLE_v3_0_TECH §14.9` (LIVE SCHEMA AUTHORITY) — live wins on drift
**Target DB:** `runmystore` (MySQL 8.0.45)
**Scope:** DB schema only. PHP / UI / business logic = out of scope (следващи сесии).

---

## 1. Summary

| Метрика | Брой |
|---|---|
| Нови таблици | **5** |
| ALTER tables | **5** |
| Нови колони общо | **39** |
| Нови индекси | **8** (4 на deliveries, 4 на delivery_items) + 1 на ai_insights = **9** |
| Нови FK constraints | **3** на delivery_items + 11 на 5-те нови таблици = **14** |
| ENUM modifications | **1** (purchase_orders.status) |
| Decisions cross-referenced | **38 от 165** (DB-scope; останалите 127 са код/UI/бизнес логика — out of scope) |

Migration files:
- `migrations/s88d_delivery_schema.sql` — forward
- `migrations/s88d_delivery_schema_ROLLBACK.sql` — reverse (dev-only)

Backup: `/var/backups/runmystore_pre_s88d_20260429_1006.sql` (2.27 MB)

---

## 2. Нови таблици (5)

| Таблица | Цел | Източник |
|---|---|---|
| `delivery_events` | Audit trail (D1-D6 reconciliation, L6 edit history, всеки state change) | N2, L6 |
| `supplier_defectives` | Pool за връщане към supplier; counter за натрупване | E1-E7 |
| `price_change_log` | Auto-pricing история (cost variance, retail nudges, manual corrections) | C5, C9, G7 |
| `pricing_patterns` | Per-category multiplier + ending pattern learning | C2-C5, N3 |
| `voice_synonyms` | Per-tenant lang/dialect transcription corrections | B6, H3, N3, SIMPLE_MODE_BIBLE §16.1 |

Всички с `tenant_id`, `store_id` (където уместно), `currency_code` snapshot. FK ON DELETE RESTRICT за финансови записи (supplier_defectives → suppliers/deliveries); CASCADE само където spec позволява (pricing_patterns → tenants).

---

## 3. ALTER existing tables (5)

### 3.1 `deliveries` — +18 колони, +4 индекси

| Колона | Тип | Решение |
|---|---|---|
| `currency_code` | CHAR(3) NOT NULL DEFAULT 'EUR' | N6 |
| `status` | ENUM('draft','pending','reviewing','committed','voided','superseded') DEFAULT 'draft' | X3+X7 ('draft' за offline), M5 ('superseded' за reconciliation auto-merge) |
| `invoice_type` | ENUM('clean','semi','manual') NULL | N7, I6 |
| `invoice_number` | VARCHAR(100) NULL | N7 (OCR-извлечен; различен от вече съществуващото `number`) |
| `pack_size` | INT DEFAULT 1 | N7 (default factor; per-item override в delivery_items) |
| `ocr_raw_json` | JSON NULL | N7 |
| `source_media_urls` | JSON NULL | N7 (snimkata на фактурите) |
| `reviewed_by` / `reviewed_at` | INT UNSIGNED / TIMESTAMP | N7 |
| `committed_by` / `committed_at` | INT UNSIGNED / TIMESTAMP | N7 |
| `locked_at` | TIMESTAMP NULL | N7 |
| `auto_close_reason` | ENUM('user_committed','auto_after_session','imported','merged_with_next','voided') | N7 |
| `has_mismatch` | TINYINT(1) DEFAULT 0 | N7, D1-D4 |
| `mismatch_summary` | JSON NULL | N7 |
| `has_unfactured_excess` | TINYINT(1) DEFAULT 0 | N7, D3 |
| `has_unreceived_paid` | TINYINT(1) DEFAULT 0 | N7, D4 |
| `content_signature` | CHAR(64) NULL | F6 (duplicate detection hash) |

**Indexes:**
- `idx_d_tenant_supplier_time` (tenant_id, supplier_id, created_at) — N11
- `idx_d_tenant_status_time` (tenant_id, status, created_at) — N11
- `idx_d_has_mismatch` (has_mismatch) — N11 partial-equivalent
- `idx_d_content_signature` (tenant_id, content_signature) — F6 dup detection lookup

### 3.2 `delivery_items` — +18 колони, +4 индекси, +3 FK

| Колона | Тип | Решение |
|---|---|---|
| `tenant_id` | INT UNSIGNED DEFAULT 0 | N6 |
| `store_id` | INT UNSIGNED DEFAULT 0 | N6 |
| `supplier_id` | INT UNSIGNED NULL | N6 (за W6 index) |
| `currency_code` | CHAR(3) DEFAULT 'EUR' | N6 |
| `line_number` | INT UNSIGNED NULL | N8 |
| `product_name_snapshot` | VARCHAR(255) NULL | N8 |
| `barcode_snapshot` | VARCHAR(64) NULL | N8 |
| `sku_snapshot` | VARCHAR(100) NULL | N8 |
| `supplier_product_code` | VARCHAR(100) NULL | W1-W7 („златен ключ") |
| `pack_size` | INT DEFAULT 1 | T1-T7 |
| `vat_rate_applied` | DECIMAL(5,2) NULL | N8, V4 (bonus = 0) |
| `received_condition` | ENUM('new','damaged','expired','wrong_item') DEFAULT 'new' | N8 |
| `original_ocr_text` | TEXT NULL | N8 |
| `is_bonus` | TINYINT(1) DEFAULT 0 | V1-V6 |
| `variation_pending` | TINYINT(1) DEFAULT 0 | J3 |
| `parent_product_id` | INT UNSIGNED NULL FK→products SET NULL | N8, K7 |
| `purchase_order_item_id` | INT UNSIGNED NULL FK→purchase_order_items SET NULL | N5 |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | за N11 idx |

**Indexes:**
- `idx_di_tenant_product_time` (tenant_id, product_id, created_at) — N11
- `idx_di_variation_pending` (variation_pending) — N11
- `idx_di_supplier_product_code` (supplier_product_code) — N11
- `idx_di_w6_lookup` (tenant_id, supplier_id, supplier_product_code) — W6

**FK:** `fk_di_supplier`, `fk_di_po_item`, `fk_di_parent_product`. Existing `fk delivery_items_ibfk_1` (delivery_id → deliveries CASCADE) **запазен** — N10 satisfied.

### 3.3 `suppliers` — +2 колони

- `reliability_score` TINYINT UNSIGNED NULL (0-100) — N4
- `payment_terms_days` INT NOT NULL DEFAULT 0 — S2 (0 = cash on delivery)

### 3.4 `ai_insights` — +1 колона + 1 индекс

- `linked_brain_queue_id` INT UNSIGNED NULL — N4, M4 (FK **deferred** до S91 когато ai_brain_queue се създаде)
- `idx_ai_brain_queue` (linked_brain_queue_id)

### 3.5 `purchase_orders` — MODIFY status ENUM

- Преди: `('draft','sent','partial','received','cancelled')`
- След: `('draft','sent','partial','received','cancelled','stale')` — U1

---

## 4. DRIFT findings (live wins per BIBLE §14.9)

| # | Spec казва | Live държи | Resolution |
|---|---|---|---|
| 1 | `deliveries.payment_status` ENUM('unpaid','partial','paid') (S1) | ENUM('unpaid','**partially_paid**','paid') | KEEP live. S1 текстът ползва 'partial' колоквиално; кодът трябва да чете 'partially_paid'. BIBLE update необходим. |
| 2 | `deliveries.due_date` (S3) | `payment_due_date` вече съществува | KEEP live name. Code references трябва `payment_due_date`. |
| 3 | `purchase_orders.status` упоменава 'partially_received' (U5) | live: 'partial' | KEEP 'partial'. Добавено 'stale' (U1). U5 текстът трябва да се reformulate-не. |
| 4 | `products.has_variations` ENUM (N4) | вече ENUM('true','false','unknown') | SKIP ALTER — N4 satisfied. |
| 5 | `ai_insights.type` ENUM additions (M1, M2, U3) | НЯМА `type` колона; има VARCHAR(80) `topic_id` | SKIP ENUM changes. Нови topic_id values слотват като strings (M1: 'reconciliation_mismatch', 'cost_variance', и т.н.). |
| 6 | `deliveries.matched_order_id` (N11 index) | N5 казва **НЕ** matched_order_id (използвай delivery_items.purchase_order_item_id) | FOLLOW N5 (по-конкретно решение). Skip matched_order_id index. |
| 7 | `delivery_items.total_cost` denormalized (N8) | live `total` DECIMAL(12,2) вече запълва тази роля | KEEP `total`, no new column. |
| 8 | `supplier_orders` legacy table | exists в parallel with `purchase_orders` | **DO NOT DROP.** Маркирано deprecated; defer S91 merge (виж §6 deferred). |

---

## 5. DEFERRED (out of S88D scope)

| Item | Защо отлагаме | Кога |
|---|---|---|
| `ai_brain_queue` table | UI proactive AI Brain все още не е имплементирана; FK target не е критичен за beta | S91 (AI Brain UI phase) |
| `accounts_payable` table | R1 финансов модул не е в beta scope | S91+ (платежен модул) |
| `supplier_orders` legacy DROP | Има callers които пишат там — да не пукне production | S91 merge с purchase_orders |
| `supplier_bonus_history` | V3 — bъдеща версия | Defer indefinitely |
| `supplier_product_code_history` | W4 — multiple codes per product | Defer indefinitely |
| `payments` table (per-delivery partial payments) | S8 — beta има само full payment toggle | Defer post-beta |
| **X10 Pending sync IndexedDB** | Frontend/client-side (не MySQL) | Frontend phase (S90+) |

---

## 6. Decisions cross-reference (38 / 165 в S88D scope)

DB-scope:
- **N1** — purchase_orders wins ✅ (live)
- **N2** — 5 нови tables ✅
- **N3** — pricing_patterns, voice_synonyms ✅
- **N4** — suppliers.reliability_score, ai_insights.linked_brain_queue_id, products.has_variations (вече) ✅
- **N5** — delivery_items.purchase_order_item_id (no matched_order_id) ✅
- **N6** — tenant_id, store_id, currency_code на всичко ново ✅
- **N7** — 18 deliveries колони ✅
- **N8** — 18 delivery_items колони ✅
- **N9** — `previous_cost`/`cost_variance_pct` НЕ в delivery_items (живеят в price_change_log) ✅
- **N10** — FK ON DELETE RESTRICT, CASCADE само delivery_items→deliveries ✅
- **N11 + W6** — всички indexes ✅
- **S1** — payment_status (live drift, kept) ✅
- **S2** — suppliers.payment_terms_days ✅
- **S3** — payment_due_date (live name) ✅
- **T1-T7** — delivery_items.pack_size + deliveries.pack_size default ✅
- **U1** — purchase_orders.status += 'stale' ✅
- **V1-V6** — delivery_items.is_bonus, vat_rate_applied ✅
- **W1-W7** — delivery_items.supplier_product_code + W6 index ✅
- **F6** — deliveries.content_signature (hash duplicate detection) ✅
- **D1-D4** — deliveries.has_mismatch, mismatch_summary, has_unfactured_excess, has_unreceived_paid ✅
- **E2** — supplier_defectives table ✅
- **C5** — price_change_log table ✅
- **L6** — delivery_events table (audit) ✅
- **M4** — ai_insights.linked_brain_queue_id ✅
- **X3+X7** — deliveries.status включва 'draft' ✅

Out of scope (запис в код/UI):
- A (закони), B (voice infra), D5-D6 (UI tone), G (сценарии — application logic), H (voice flows), I (OCR pipeline), J (variation matrix UI), K (Simple UX), L1-L5 (Detailed UX), M1-M3, M5-M6 (insight semantic — application), O (memory map helpers), P (beta strategy), Q (chat scope), R (downstream integrations), S4-S8 (cron/UI), T-application (UX), U2-U6 (cron + insight), X1-X11 (frontend offline) ≈ **127 решения**.

---

## 7. Verify queries (за следваща сесия)

```bash
# 5 нови таблици
mysql -u<user> -p<pass> runmystore -e "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES \
  WHERE TABLE_SCHEMA='runmystore' AND TABLE_NAME IN ('delivery_events','supplier_defectives','price_change_log','pricing_patterns','voice_synonyms')"

# deliveries 18 нови колони
mysql -u<user> -p<pass> runmystore -e "SHOW COLUMNS FROM deliveries"
# Очакваме 18 нови: currency_code, status, invoice_type, invoice_number, pack_size, ocr_raw_json,
#   source_media_urls, reviewed_by, reviewed_at, committed_by, committed_at, locked_at,
#   auto_close_reason, has_mismatch, mismatch_summary, has_unfactured_excess,
#   has_unreceived_paid, content_signature

# delivery_items 18 нови колони
mysql -u<user> -p<pass> runmystore -e "SHOW COLUMNS FROM delivery_items"
# Очакваме: tenant_id, store_id, supplier_id, currency_code, line_number,
#   product_name_snapshot, barcode_snapshot, sku_snapshot, supplier_product_code,
#   pack_size, vat_rate_applied, received_condition, original_ocr_text, is_bonus,
#   variation_pending, parent_product_id, purchase_order_item_id, created_at

# suppliers + ai_insights + purchase_orders
mysql -u<user> -p<pass> runmystore -e "SHOW COLUMNS FROM suppliers WHERE Field IN ('reliability_score','payment_terms_days')"
mysql -u<user> -p<pass> runmystore -e "SHOW COLUMNS FROM ai_insights WHERE Field='linked_brain_queue_id'"
mysql -u<user> -p<pass> runmystore -e "SHOW COLUMNS FROM purchase_orders WHERE Field='status'"  # Очакваме 'stale' в ENUM

# Idempotency (re-run trябва 0 errors, 0 changes)
mysql -u<user> -p<pass> runmystore < migrations/s88d_delivery_schema.sql
```

**Actual results (2026-04-29):**
- 5/5 нови таблици → 0 редове ✅
- deliveries: 18/18 колони ✅
- delivery_items: 18/18 колони ✅
- suppliers: 2/2 колони ✅
- ai_insights: linked_brain_queue_id ✅
- purchase_orders.status: ENUM включва 'stale' ✅
- Idempotency: re-run 1, re-run 2, re-run 3 — 0 errors, 0 schema changes ✅
- Rollback test: rollback → re-apply — 0 errors, schema restored ✅

---

## 8. Rollback strategy (production)

⚠ **На production: НИКОГА direct rollback. Forward fix только.**

Rollback script (`migrations/s88d_delivery_schema_ROLLBACK.sql`) е **dev/staging only**:
- DROP-ва 5 нови таблици (data loss ако има реални records).
- Връща purchase_orders.status към ENUM без 'stale' (UPDATE-ва съществуващи 'stale' rows към 'cancelled' първо).
- Маха всички 39 нови колони + 9 индекси + 3 нови FK.

**На production scenario "трябва да върна schema":**
1. Backup actual production data **първо** (`mysqldump`).
2. Forward-fix migration (нова `s88e_*` файл) която adds compensating ALTER (например MODIFY column → NULL, или DROP COLUMN ако няма data).
3. Никога не run-вай ROLLBACK script на live runmystore.

Backup before this migration: `/var/backups/runmystore_pre_s88d_20260429_1006.sql` (2.27MB, full mysqldump с --routines --triggers --events --single-transaction).

---

## 9. Known issues / next steps

### За следващите имплементационни сесии:

1. **BIBLE_v3_0_TECH §14.1 update** — да отрази:
   - `deliveries.payment_status` ENUM ('unpaid','**partially_paid**','paid') — не 'partial'
   - `deliveries.payment_due_date` (не 'due_date')
   - `purchase_orders.status` ENUM включва 'stale'
   - 5 нови tables в §14.x

2. **DELIVERY_ORDERS_DECISIONS_FINAL.md** — следващ append трябва да:
   - Update S1 текст: 'partial' → 'partially_paid'
   - Update S3 текст: 'due_date' → 'payment_due_date'
   - Update U5 текст: 'partially_received' → 'partial'
   - Изричен deprecation note за `supplier_orders` legacy

3. **PHP code** (S91+):
   - `deliveries` INSERT/UPDATE callers трябва да пишат `tenant_id`, `store_id`, `currency_code`, `status` (default 'draft')
   - `delivery_items` INSERT callers трябва да пишат `tenant_id`, `store_id`, `currency_code`, `pack_size`, `received_condition` (default стойностите safe)
   - Нови pf функции в compute-insights.php за M1-M2 insight types (пишат към ai_insights с new topic_id strings)

4. **ai_brain_queue** (S91 при AI Brain UI phase):
   - Schema е специфицирана в `SIMPLE_MODE_BIBLE.md §16.1`
   - При създаване → ADD FK constraint за `ai_insights.linked_brain_queue_id` REFERENCES `ai_brain_queue` (id) ON DELETE SET NULL

5. **accounts_payable** (S91+ финансов модул):
   - R1 — всяка committed delivery трябва да генерира запис
   - Schema TBD от платежния модул

6. **Frontend offline** (X1-X11) — отделна сесия:
   - IndexedDB queue за pending deliveries
   - localStorage 30min TTL recovery
   - Service Worker за reconnect sync
   - X11 ai_insight за sync conflicts → пише в already-existing `ai_insights` таблицата

### Verification gate за beta launch (14-15.05.2026):
- Diagnostic framework (`tools/diagnostic/`) трябва да получи нови сценарии за:
  - Cat A: deliveries с status='draft' остаряват → ai_insight
  - Cat B: supplier_defectives prag (20+ бройки или €50) → ai_insight
  - Cat D: pack_size=1 boundary
  - Cat E: ENUM whitelist regression check за всички нови ENUM (status, invoice_type, auto_close_reason, received_condition, reason, change_source, learned_from, created_by) — добавя в `run_cat_e_scenarios`

---

## 10. Files committed in S88D

```
migrations/s88d_delivery_schema.sql            (560 lines, 5 CREATE + 5 ALTER, fully idempotent)
migrations/s88d_delivery_schema_ROLLBACK.sql   (210 lines, dev-only)
docs/SESSION_S88D_SCHEMA_HANDOFF.md            (this file)
```

Backup (NOT committed; lives на сървъра):
```
/var/backups/runmystore_pre_s88d_20260429_1006.sql   (2.27 MB)
```
