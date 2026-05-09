# 2026_05_documents — миграция за партньори + документи + фактурни серии

## Какво прави

Създава 7 нови таблици и добавя 3 колони в `sales`:

| Таблица | Цел | Свързано RWQ |
|---|---|---|
| `partners` | Унифицирани доставчици/клиенти/B2B/институции | RWQ-89 |
| `partner_aliases` | Исторически имена за search | RWQ-89 |
| `eik_cache` | Кеш на лookups от търговски регистър | RWQ-88 |
| `document_series` | Multi-серии номерация (10-разрядни по ЗДДС) | RWQ-90 |
| `document_series_changes` | Audit trail на ръчни смени | RWQ-90 |
| `documents` | Централна таблица — 16 типа документи | RWQ-92 |
| `document_items` | Line items per документ | RWQ-92 |

ALTER на `sales`: `partner_id`, `primary_document_id`, `sale_mode ENUM('retail','wholesale')` — за integration с redesigned sale.php (RWQ-93).

## Прилагане в SANDBOX

```bash
mysql -u root runmystore_sandbox < db/migrations/2026_05_documents.up.sql
mysql -u root runmystore_sandbox -e "SHOW TABLES LIKE '%document%'; SHOW TABLES LIKE 'partner%'; DESCRIBE sales;" 
```

Очаквани резултати: 7 нови таблици + 3 нови колони в `sales`.

## Прилагане в PRODUCTION (само след ENI готовност за rewrite на sale.php)

```bash
mysqldump -u root runmystore > /root/backup_pre_documents_$(date +%Y%m%d_%H%M).sql
mysql -u root runmystore < db/migrations/2026_05_documents.up.sql
```

## Rollback

```bash
mysql -u root runmystore < db/migrations/2026_05_documents.down.sql
```

ВНИМАНИЕ: down.sql изтрива всички записи от 7-те таблици. Преди rollback — backup.

## 16 типа документи в ENUM `documents.doc_type`

**Задължителни данъчни (ЗДДС):**
1. `cash_receipt` — Касов/фискален бон
2. `invoice` — Фактура
3. `credit_note` — Кредитно известие
4. `debit_note` — Дебитно известие
5. `storno_receipt` — Сторно касова бележка
6. `protocol_117` — Протокол по чл. 117 ЗДДС

**Помощни (ЗС, ТЗ):**
7. `proforma` — Проформа фактура
8. `goods_note` — Стокова разписка
9. `storage_note` — Складова разписка
10. `cash_order_in` — Приходен касов ордер
11. `cash_order_out` — Разходен касов ордер
12. `transfer_protocol` — Приемо-предавателен протокол
13. `warranty` — Гаранционна карта
14. `shipping_note` — Експедиционна бележка
15. `offer` — Оферта
16. `order_confirmation` — Потвърждение на поръчка

## 10 категории в ENUM `document_series.document_category`

`tax` обединява фактури + кредитни/дебитни известия + протоколи (ЗДДС изисква споделена номерация без пропуски). Останалите типове имат отделни серии (касов бон, проформа, стокова разписка, складова разписка, касов ордер, приемо-предавателен протокол, гаранция, експедиция, оферта).

## Зависимости преди прилагане

- ENI tenant_id=7 backup (mysqldump)
- Миграция на старите `suppliers` + `customers` записи в `partners` — отделен ETL скрипт (предстои в RWQ-89 implementation сесия)
- Първоначална initial серия за tenant_id=7 (предстои със setup wizard)

