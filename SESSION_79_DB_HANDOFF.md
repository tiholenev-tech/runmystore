# 📋 SESSION 79.DB — HANDOFF

**Дата:** 22.04.2026  
**Тип:** DB foundation (CHAT 2 — паралелна сесия с S79.FIX.B)  
**Модел:** Opus 4.7  
**Статус:** ✅ COMPLETE  
**Tag:** `v0.5.1-s79-db`  
**Commit:** `eca6506`

---

## 🎯 МИСИЯ

Изграждане на DB foundation за всички бъдещи модули. Нищо visible на user — всичко infrastructure. Целта: следващите модули (orders.php, sale rewrite, inventory v4) да могат да разчитат на:
- Versioned migrations (никакви ръчни ALTER)
- Audit trail (всяка промяна логната)
- Atomic transactions (multi-table операции safe)
- Soft delete (sales history не се чупи при изтриване)

---

## ✅ ЗАВЪРШЕНО (5 ETAP-а + finalize)

### ETAP 1 — Discovery
Скрипт инспектира текущото DB състояние. **Находка:** `audit_log` структурата (от S78) е различна от DOC_05 §6.2 — липсват `store_id`, `source`, `source_detail`, `user_agent`. Helper-ът от ETAP 3 е адаптиран към реалната структура. Разширяването → **S79.AUDIT.EXT** (REWORK QUEUE).

### ETAP 2 — schema_migrations + Migrator + CLI
- `DB::exec()` добавен в `config/database.php`
- Папки `/var/www/runmystore/migrations/` + `/var/www/runmystore/lib/`
- `schema_migrations` таблица (version PK, checksum, applied_at, applied_by, execution_time_ms, rollback_sql)
- `lib/Migrator.php` — checksum enforcement (anti-tamper), idempotent skip за `Duplicate column name`/`Duplicate key name`/`already exists`
- `migrate.php` CLI — `status` / `up` / `down <version>`

### ETAP 3 — auditLog() helper
Добавен в `config/helpers.php` (секция 7). Signature:
```php
auditLog(array $user, string $action, string $table, int $recordId, ?array $old = null, ?array $new = null): void
```
- Action валидация: само `create`/`update`/`delete` (ENUM constraint)
- Невалидно action или липсващ `tenant_id` → `error_log` + skip (НИКОГА throw — за да не чупи бизнес транзакции)
- 5/5 теста PASS

### ETAP 4 — DB::tx() wrapper
Добавен в `config/database.php`. DOC_05 §7.2 чист (БЕЗ deadlock retry — S80).
- `catch Throwable` (не само Exception — Error в PHP 8+ обхванат)
- `$pdo->inTransaction()` guard преди rollBack
- Връща стойността от callback
- 5/5 теста PASS (success commit, throw rollback, return value, nested throws PDOException, outer rolls back)

### ETAP 5 — Soft delete (5 таблици)
Първа реална Migrator миграция: `migrations/20260422_001_soft_delete_5_tables.{up,down}.sql`
- Tables: `suppliers`, `customers`, `users`, `stores`, `categories`
- Columns: `deleted_at DATETIME NULL`, `deleted_by INT UNSIGNED NULL`, `delete_reason VARCHAR(200) NULL`
- Index: `idx_deleted_at` на всяка таблица
- Apply: 2089ms
- 5/5 verify ✅, E2E (insert→soft delete→active filter→restore→cleanup) PASS

### ETAP 6 — Finalize
Selective commit (само S79.DB файлове, products.php непипнат — CHAT 1 територия). Push + tag pushed.

---

## 📊 DELIVERABLES

| Файл | Тип | Описание |
|---|---|---|
| `config/database.php` | edit | + `DB::exec()` + `DB::tx()` |
| `config/helpers.php` | edit | + секция 7 `auditLog()` |
| `lib/Migrator.php` | new | Migrator class (121 lines) |
| `migrate.php` | new | CLI runner (chmod +x, 37 lines) |
| `migrations/20260422_001_soft_delete_5_tables.up.sql` | new | First migration (1461 bytes) |
| `migrations/20260422_001_soft_delete_5_tables.down.sql` | new | Rollback SQL (1048 bytes) |
| `SESSION_79_DB_HANDOFF.md` | new | Този handoff |
| `MASTER_COMPASS.md` | edit | DB foundation status updates + REWORK QUEUE additions |

**Backup-и (всички в /root/, пази 7 дни):**
- `backup_s79db_20260422_*.sql` (full DB pre-ETAP)
- `backup_database_s79db_20260422_0640.php`
- `backup_helpers_s79db_20260422_0637.php`

---

## 🗄️ DB CHANGES SUMMARY

**New tables (1):** `schema_migrations`

**Altered tables (5):** `suppliers`, `customers`, `users`, `stores`, `categories` — each: +3 cols + 1 index

---

## ⚠️ ИЗВЕСТНИ ОГРАНИЧЕНИЯ (за REWORK QUEUE)

### audit_log без store_id/source/user_agent → S79.AUDIT.EXT
Реалната таблица има 9 колони. DOC_05 §6.2 иска 13. Helper работи с минималния набор. P1.

### DB::tx() без nested transactions → S80
PDO native поведение. SAVEPOINT support → S80. P2.

### DB::tx() без deadlock retry → S80
DOC_05 §7.2 чист. Deadlock retry ladder (3 опита, exponential backoff) → S80. P1.

### DB password в публично репо → S79.SECURITY (P0)
`config/database.php` съдържа `'pass' => '0okm9ijnSklad!'` plain text в публично GitHub репо. **БЛОКЕР преди следваща сесия.**

---

## 🔗 СЛЕДВАЩА СЕСИЯ

**S79.SECURITY (P0)** — преди което и да е друго:
1. Премести DB credentials в `/etc/runmystore/db.env` (chmod 600)
2. `config/database.php` чете от env vars
3. Добави `.env.example` в репото
4. Git history scrub (BFG Repo-Cleaner или `git filter-repo`)
5. Force push + force re-clone от Тихол навсякъде

После: **S80** (negative stock guard + composite FK + idempotency keys + cents migration + DB::tx() deadlock retry + SAVEPOINT).

---

## 🧪 VERIFY КОМАНДИ

```bash
# Migrator работи
cd /var/www/runmystore && php migrate.php status
# → 20260422_001  applied  soft_delete_5_tables  2026-04-22 06:48:25

# Soft delete columns
MYSQL_PWD='...' mysql -u runmystore -e "SHOW COLUMNS FROM suppliers LIKE 'deleted_%'" runmystore

# DB::tx() roundtrip
php -r "require '/var/www/runmystore/config/database.php'; \$x = DB::tx(fn() => 42); echo \$x;"
# → 42
```

---

**КРАЙ НА S79.DB HANDOFF**
