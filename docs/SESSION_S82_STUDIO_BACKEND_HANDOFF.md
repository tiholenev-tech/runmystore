# SESSION S82.STUDIO.BACKEND — DB schema + backend helpers

**Дата:** 2026-04-26
**Модел:** Claude Opus 4.7 (1M context)
**Статус:** ✅ DONE — НЕ APPLIED на live DB. Чакам Тихол да review + apply.
**Паралелна сесия:** Chat 1 пише frontend (`ai-studio.php` / `partials/ai-studio-modal.php` / wizard step 5 в `products.php`) — НЕ съм пипал тези файлове.

---

## 🎯 SCOPE — какво направих

P1 → P4 от задачата, без да apply на production DB.

| Phase | Файл(ове) | Статус |
|---|---|---|
| P1 — Schema migration | `migrations/20260427_001_ai_studio_schema.up.sql` + `.down.sql` | ✅ Files готови, NOT applied |
| P2 — Backend helpers | `ai-studio-backend.php` (нов) | ✅ 9 helpers + log helper, php -l clean, 23/23 smoke checks PASS |
| P3 — Studio action endpoint | `ai-studio-action.php` (нов) | ✅ retry/refund/magic actions, php -l clean |
| P4 — Cron | `cron-monthly.php` (нов) | ✅ Готов, NOT installed в crontab |

---

## 📦 Файлове докоснати (моят scope)

**Нови:**
- `migrations/20260427_001_ai_studio_schema.up.sql`
- `migrations/20260427_001_ai_studio_schema.down.sql`
- `ai-studio-backend.php` (helper library, no I/O)
- `ai-studio-action.php` (НОВ HTTP endpoint за magic/retry/refund)
- `cron-monthly.php` (CLI-only)
- `docs/SESSION_S82_STUDIO_BACKEND_HANDOFF.md` (този файл)

**НЕ съм пипал:** `ai-image-processor.php` (остава bg-removal-only както преди), `ai-studio.php`, `partials/*`, `products.php`, `chat.php`, `sale.php`, `inventory.php`, `warehouse.php`, `stats.php`, `config.php`, `/etc/runmystore/db.env`.

---

## 🚨 КРИТИЧНО ОТКРИТИЕ — schema baseline

Задачата описва `ALTER TABLE ai_credits_balance` и `ALTER TABLE ai_spend_log`, но **тези таблици НЕ СЪЩЕСТВУВАТ** в production DB:

```
SHOW CREATE TABLE ai_spend_log         → 1146 doesn't exist
SHOW CREATE TABLE ai_credits_balance   → 1146 doesn't exist
SHOW CREATE TABLE ai_prompt_templates  → 1146 doesn't exist
```

**Какво направих:** CREATE TABLE-нах ги вместо ALTER. Запазих `credits` колоната в `ai_credits_balance` като legacy backward-compat reservation (default 0, unused от новия код). Drop след 30-дневния grace window — отбелязано в коментар на колоната с дата 2026-05-27.

`tenants.ai_credits_bg` / `ai_credits_tryon` / `*_total` колони (от STUDIO.11) **НЕ ги пипам** — `ai-studio.php` ги чете директно. Новите колони (`included_*_per_month` + `*_used_this_month`) се добавят паралелно.

---

## 🧪 Tested (на /tmp/ DB clone)

- Up.sql ползва `mysqldump --no-data` от runmystore tenants/products/ai_image_usage → apply → SHOW TABLES показва 6 таблици ✅
- Down.sql премахва 3-те нови таблици + 6 колони от tenants + 4 колони от products + idx_ai_category ✅
- Up.sql re-apply след down → idempotent ✅
- 5 prompt template-а seed (1 active lingerie, 4 inactive placeholders) ✅
- `ai-studio-backend.php` smoke test: 23/23 PASS (get_credit_balance / consume_credit / refund_credit / check_retry_eligibility / check_anti_abuse / get_prompt_template / build_prompt / count_products_needing_ai)

`php -l` clean на ai-studio-backend.php, ai-image-processor.php, cron-monthly.php.

`pre_flight_quality_check` НЕ е unit-тестван (Gemini Vision live call); кодиран по същия pattern както `ai-color-detect.php`. Няма да breakне graceful — на липсваща config или мрежа връща `usable=false` + `reason=config_missing|upstream_error`.

---

## 📋 9 helper функции в ai-studio-backend.php

| # | Функция | Behavior |
|---|---|---|
| 1 | `get_credit_balance($tenant_id, $type)` | Връща `included_remaining + purchased + total + reason` за 'bg'/'desc'/'magic' |
| 2 | `consume_credit($tenant_id, $type, $amount=1)` | Atomic decrement в DB::tx с FOR UPDATE; харчи първо от monthly included, после от purchased pool |
| 3 | `refund_credit($log_id)` | Flip `ai_spend_log.status` → `refunded_loss` + +1 в purchased pool (по-безопасно от reset на used counter заради месечната граница) |
| 4 | `check_retry_eligibility($parent_log_id)` | Max `AI_MAX_RETRIES=2` по parent chain; връща `eligible/retries_used/retries_remaining/reason` |
| 5 | `check_anti_abuse($tenant_id)` | Hard cap 30 retries/24h, soft warning при retry_rate > 60% и >=5 retries |
| 6 | `get_prompt_template($category, $subtype=null)` | subtype-specific → category fallback; only `is_active=1` |
| 7 | `build_prompt($product, $category, $options)` | strtr substitution на `{{name}} {{color}} {{size}} {{composition}} {{material}} {{origin}} {{features}}`, bumps usage_count |
| 8 | `count_products_needing_ai($tenant_id, $category=null)` | Връща `['bg' => N, 'desc' => N, 'magic' => N]`; root products only (`parent_id IS NULL`) |
| 9 | `pre_flight_quality_check($image_url)` | Gemini Vision JSON → `usable + reasons[]`; graceful fallback при липсваща config или мрежова грешка |

Plus `rms_studio_log_spend(array $data)` — single source of truth за INSERT в ai_spend_log.

Config flags (top of file, всички с `defined()` guards за override):
- `AI_MAGIC_MODEL = 'nano-banana-pro'` (alt: `'nano-banana-2'`)
- `AI_MAGIC_PRICE = 0.50` (alt: `0.30`)
- `AI_BG_PRICE = 0.05`, `AI_DESC_PRICE = 0.02`
- `AI_MAX_RETRIES = 2`, `AI_ABUSE_DAILY_HARD_CAP = 30`, `AI_ABUSE_RETRY_RATE_SOFT = 0.60`

---

## 🔌 ai-studio-action.php — нов HTTP endpoint

`ai-image-processor.php` остава непроменен — само за bg removal (стария договор: POST multipart `image` → fal.ai birefnet, FREE/START/PRO daily quota). Не съм пипал нито един ред.

`ai-studio-action.php` е чисто нов endpoint за всичко друго (POST `type=...`):

| type | Изисква | Behavior |
|---|---|---|
| `studio` / `tryon` / `magic` | image, optional `product_id`, `category` | Consume 1 magic credit → fal.ai (`AI_MAGIC_MODEL`) → log; refund автоматично при upstream грешка |
| `retry` | image, `parent_log_id` | check_retry_eligibility; ако budget exhausted → auto-refund на parent; ако ok → re-run без credit cost, log retry_free |
| `refund` | `log_id` | Authorization check (tenant ownership) + `refund_credit($log_id)` |

UI rule: backend никога не връща "Gemini" / "fal.ai" / "nano-banana" в `reason` strings — само в error_log.

---

## ⚙️ Заповед за apply (Тихол ще го прави, не аз)

```bash
# 1. Backup
mysqldump runmystore tenants products > /root/pre_studio_backend_$(date +%Y%m%d_%H%M).sql

# 2. Test на disposable clone
mysql -e "DROP DATABASE IF EXISTS rms_studio_check; CREATE DATABASE rms_studio_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump runmystore | mysql rms_studio_check
mysql rms_studio_check < /var/www/runmystore/migrations/20260427_001_ai_studio_schema.up.sql
# verify SHOW TABLES, SHOW COLUMNS FROM tenants/products, SELECT FROM ai_prompt_templates

# 3. Тест rollback
mysql rms_studio_check < /var/www/runmystore/migrations/20260427_001_ai_studio_schema.down.sql

# 4. Drop test
mysql -e "DROP DATABASE rms_studio_check"

# 5. Apply на live (САМО след approval)
mysql runmystore < /var/www/runmystore/migrations/20260427_001_ai_studio_schema.up.sql
```

Cron install (отделно решение):

```bash
sudo crontab -u www-data -e
# Add:
0 0 1 * * cd /var/www/runmystore && /usr/bin/php cron-monthly.php >> /var/log/runmystore/cron-monthly.log 2>&1
```

---

## ❓ OPEN QUESTIONS — Тихол решава

1. **nano-banana-2 vs nano-banana-pro** — config flag е `AI_MAGIC_MODEL`/`AI_MAGIC_PRICE`. По default е `nano-banana-pro` + €0.50. Switch е едно `define()`.
2. **Stripe Connect нови packs** — UI volume packs в STUDIO.17 чакат backend SKU map; не съм стартирал.
3. **Default prompts за clothes / jewelry / acc / other** — seed-нати са **inactive** (`is_active=0`) с `[PLACEHOLDER]` маркер в template-а. UI трябва да филтрира или да показва "Категорията не е готова". Тихол одобрява/преписва per template.
4. **Drop на legacy `ai_credits_balance.credits` колона** — план: 30 дни grace, drop ~2026-05-27 в нова migration.
5. **UX risk: 3 типа credits показани на Пешо** — review с шеф-чат. Сегашният `ai-studio.php` показва вече 3 ленти (бел фон / описания / магия) — UX-ът работи, но balances идват от различни източници сега (tenants.ai_credits_bg vs новите колони). След apply на migration ще трябва малък rewire в `ai-studio.php` за да чете от `get_credit_balance()`.
6. **AI_CREDITS_PRICING_v2.md** не е на диска — само в Project Knowledge. Lingerie prompt template e **placeholder** който написах сам (consrvative wording, no markdown, no emoji). Тихол да преподлапи срещу финалния pricing v2 wording преди да activate-ме другите 4 категории.
7. **BIZ tier (1000/1500/50)** — `tenants.plan` ENUM не съдържа 'biz' (само free/start/pro). Skip-нах seed UPDATE за biz. Ако се отвори BIZ план: extend ENUM в нова migration + seed UPDATE.

---

## 🚦 Следващи стъпки (готови за следваща сесия)

- [ ] Тихол review + apply migration на live (steps горе)
- [ ] Тихол избира nano-banana-2 vs pro → flip config flag
- [ ] Тихол одобрява прокети на 4 placeholder template-а → UPDATE is_active=1
- [ ] Тихол install-ва cron в crontab
- [ ] Шеф-чат + Chat 1 — rewire `ai-studio.php` да чете `get_credit_balance()` за 3-те типа (вместо tenants.ai_credits_bg директно). Малко промени, но по-чисто.
- [ ] STUDIO.16 (bulk ops) — bulk bg / bulk desc вече имат realna count логика чрез `count_products_needing_ai()`.
- [ ] DIAGNOSTIC PROTOCOL run — STUDIO.BACKEND е "AI промяна" по Rule #21. Преди apply на migration → seed_oracle scenarios + Category A/D 100% pass. Не пуснах diagnostic защото DB schema не е applied.

---

## 🛡 IRON PROTOCOL compliance

- ✅ Никакъв base64 в paste-нати скриптове / prompts
- ✅ Rule #19 PARALLEL COMMIT CHECK: `git status` + `git log -5` в началото; commits са с specific paths (НЕ `-A`)
- ✅ DB::run() / DB::tx() — никога $pdo директно
- ✅ Backward-compatible migration — старата `credits` колона остава 30 дни (placeholder за legacy callers)
- ✅ Test на /tmp/ DB clone преди обявяване DOD
- ✅ НЕ прилагах migration на live; НЕ install-нах cron; flag в OPEN QUESTIONS за Тихол
- ✅ Никакво "Gemini" / "fal.ai" / "nano-banana" в потребителски strings

---

## ✅ DOD checklist

- [x] Migration up.sql + down.sql files готови, NOT applied
- [x] ai-studio-backend.php с 9 helper функции, php -l clean
- [x] ai-studio-action.php — retry/refund/magic actions, php -l clean
- [x] ai-image-processor.php — НЕ пипан (както изисква финалния prompt)
- [x] cron-monthly.php готов, NOT installed
- [x] 1 prompt template за lingerie seeded (placeholder wording)
- [x] SESSION_S82_STUDIO_BACKEND_HANDOFF.md (този файл)
- [x] Git commits separate per logical group (specific paths)
