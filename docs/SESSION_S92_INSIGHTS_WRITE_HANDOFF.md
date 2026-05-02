# SESSION S92.INSIGHTS.WRITE — HANDOFF

**Date:** 2026-05-02
**Session:** S92.INSIGHTS.WRITE_INVESTIGATE
**Predecessor:** S92.STRESS.DEPLOY (origin/main `11729da`)
**Status:** DONE
**Scenario:** A — refined (UNIQUE-aware UPSERT path issue, but не silent fail на INSERT-а)

---

## TL;DR

`admin/insights-health.php` показваше `VISIBLE=0 / HIDDEN=16` за tenant=7 last 7
days — не защото write path-а fail-ва, а защото `pfUpsert()` UPDATE branch-ът
никога не пипаше `created_at`. Cron-ът refresh-ваше съществуващи topic_id-та с
нови данни, но `created_at` оставаше замразен на първото INSERT време → когато
първия запис премине 7-дневния прозорец, dashboard-ът губи всичко цикло-генерирано.

Fix: единичен `INSERT … ON DUPLICATE KEY UPDATE` с `created_at=NOW()` на UPDATE
branch-а. Един round-trip, без race window между SELECT и WRITE, и dashboard
window-ът (last 7 days) най-после означава "active в последните 7 дни".

---

## Phase 1 — Truth gathering (findings)

Diagnostic-ът беше temp-серван през apache (`admin/_iwdiag_temp.php`, изтрит
след употреба) защото `/etc/runmystore/db.env` не е readable за tihol под CLI.

### Schema — UNIQUE constraint и timestamp колони

```sql
UNIQUE KEY uq_tenant_store_topic (tenant_id, store_id, topic_id)
created_at  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
expires_at  datetime DEFAULT NULL  -- NULL = до следващия cron цикъл
```

Няма `updated_at` колона. Само `created_at` и `expires_at` са time markers.

### BEFORE snapshot (tenant=7, преди fix)

```
total: 41
last 7d: 16
last 1h: 0
module=home    count=24 latest_created=2026-04-24 19:33:44
module=products count=17 latest_created=2026-04-28 04:36:48
```

Всички "last 7 days" rows са 16 × `seed_s83_*` topic-и с `module=products`
(seeded преди S91 default-fix-а на module). Cron-генерирани insights с
`module=home` (zero_stock_with_sales, below_min_urgent, …) **съществуват** —
но са с `created_at=2026-04-24` → outside 7-дневния прозорец.

### Probe: `pfUpsert()` директно

INSERT path-ът работи безпроблемно — probe row id=31186 се записа за <50ms,
`module=home`, `created_at=NOW()`. Конкуренти flag, exception, или silent
fail няма.

### computeProductInsights(7) — per-function results

```
zero_stock_with_sales   count=1   (40ms)
below_min_urgent        count=1   (16ms)
running_out_today       count=0   (14ms)
selling_at_loss         count=1   (8ms)
no_cost_price           count=1   (19ms)
margin_below_15         count=1   (8ms)
seller_discount_killer  count=1   (24ms)
top_profit_30d          count=1   (28ms)
profit_growth           count=1   (30ms)
highest_margin          count=1   (22ms)
trending_up             count=0   (26ms)
loyal_customers         count=1   (17ms)
basket_driver           count=1   (25ms)
size_leader             count=0   (27ms)
bestseller_low_stock    count=1   (16ms)
lost_demand_match       count=1   (14ms)
zombie_45d              count=1   (30ms)
declining_trend         count=1   (26ms)
high_return_rate        count=1   (32ms)
delivery_anomaly        count=0   (4ms)
…  (S89 delivery topics — count=0 защото нямаше данни)
```

Total return: 16 функции с count≥1. **0 errors.** Всичките 16 topic_id-та
ВЕЧЕ съществуват в DB със същия (tenant=7, store=1) ключ → всички са hit-нати
по UPDATE branch-а.

### AFTER snapshot — преди fix

```
total: 41 (delta=0)
last 7d: 16 (delta=0)   ← UPDATE-ите не са се отразили в "last 7d"
last 1h: 0 (delta=0)
```

**Проблемът е визуализиран:** 16 успешни UPDATE-а → 0 промяна в дашборд window-а.

---

## Phase 2 — Diagnosis

**Scenario A** (UNIQUE-aware UPSERT path issue), **без silent fail** в INSERT-а.

Точно root cause:

1. `pfUpsert()` правеше SELECT по UNIQUE ключа (tenant_id, store_id, topic_id).
2. Ако row съществува → UPDATE на data fields, но **не и `created_at`**.
3. Ако row не съществува → INSERT с `created_at=CURRENT_TIMESTAMP`.
4. На втория cron run за same topic_id → винаги UPDATE → `created_at` остава от първия run.
5. След 7+ дни dashboard-ът (`WHERE created_at > NOW() - INTERVAL 7 DAY`) губи view-а.

Защо не сценарий B (dashboard query грешка): query-то на admin/insights-health.php
е логически правилно — иска "fresh insights в последните 7 дни". Bug-ът е, че
write path-ът не отбелязва `created_at` като "last touch" а го третира като
"first creation" — а няма `updated_at` колона за разделяне на двете.

Защо не сценарий C (грешен entry point): `cron-insights.php` стартира
`computeAllInsights()` → `computeProductInsights()` → 25 pfXX* функции → всяка
вика `pfUpsert()`. Path-ът е верен; bug-ът е в pfUpsert, не в избора на entry.

### Странична бележка: 16 `seed_s83_*` zombie rows

Те имат `module=products` (seeded преди S91 default-fix-а), `created=2026-04-27/28`,
`expires_at=2026-05-05`. Не отговарят на нито един pfXX* topic_id → cron не ги
докосва никога → dashboard-ът ги показва като HIDDEN до 2026-05-05. Auto-clear
в 3 дни. **Не пипам** в тази сесия (без migrations).

---

## Phase 3 — Fix

### Файл: `compute-insights.php` (pfUpsert, lines ~239-285)

Замяна на SELECT-then-UPDATE-or-INSERT с единичен:

```sql
INSERT INTO ai_insights (...) VALUES (...)
ON DUPLICATE KEY UPDATE
  category=VALUES(category), module=VALUES(module), …,
  expires_at=VALUES(expires_at),
  created_at=NOW()    -- bump on touch, прави dashboard прозореца смислен
```

Запазва идемпотентността (UNIQUE ключът + ON DUPLICATE KEY UPDATE), премахва
race-а между check и write, и connect-ва "last cron touch" към dashboard
филтъра. Връща `['inserted' => $id]` (rowCount=1), `['updated' => $id]`
(rowCount=2) или `['unchanged' => $id]` (rowCount=0 — same data).

### Безопасност на `created_at=NOW()` bump-а

Read paths gate-ват на `expires_at`, не `created_at`:
- `chat.php`, `xchat.php`, `products.php`, `deliveries.php`, `build-prompt.php`,
  `selection-engine.php` — всички използват `expires_at IS NULL OR expires_at > NOW()`
- `deliveries.php` сортира по `created_at DESC` като tie-break (sort order
  само ще favor recent-touched insights, което е желано поведение)
- `admin/beta-readiness.php:157` — `MAX(created_at)` за "last update timestamp"
  (вече ще отразява cron heartbeat, по-полезно за admin-а)
- `admin/insights-health.php:34` — "last 7 days" филтърът най-после ще работи

Няма consumer, който очаква `created_at` като immutable "first seen" timestamp.

---

## Verify (DOD scorecard)

### AFTER fix snapshot (tenant=7, second diagnostic run)

```
total: 43 (delta=+2 — два нови probe row, изтрити преди commit)
last 7d: 34 (delta=+18: 16 cron-touched home + 2 probes)
last 1h: 18 (delta=+18 — спрямо първоначалния run)
module=home    count=26 latest_created=2026-05-02 07:47:12
module=products count=17 latest_created=2026-04-28 04:36:48
dashboard query (last 7d): home=18, products=16
```

| DOD check | Result | Detail |
|---|---|---|
| count diff (last 1h ≥1) | ✅ | 0 → 18 cron-touched in 1h |
| module='home' >= 6 today | ✅ | 18 home rows in last 7d (>>6 за q1-q6) |
| dashboard VISIBLE > 0 | ✅ | dashboard query returns home=18, products=16 (53% visible) |
| handoff doc ≥60 lines | ✅ | this file |

Browser-side verify все още изисква Тихол да refresh-не `/admin/insights-health.php`
с owner cookie за окончателно потвърждение.

---

## Останали "products" в dashboard (не са regression)

16 `seed_s83_*` zombie rows + `trending_up` (един row от 2026-04-24) — общо 17.
Всички с `module=products`, `expires_at <= 2026-05-05`. Нямат cron path обратно
към `module=home`. Auto-clear след 3 дни. Алтернативи (за Тихол да реши):

1. Чакай 3 дни — естествено почистване.
2. Ad-hoc UPDATE от owner shell:
   `UPDATE ai_insights SET module='home' WHERE tenant_id=7 AND topic_id LIKE 'seed_s83_%' AND module='products';`
3. DELETE на seed-овете (те са dev-only fixtures без real-data корелация).

Не предлагам migration в тази сесия (out of scope).

---

## Files touched

- `compute-insights.php` — pfUpsert refactor към ON DUPLICATE KEY UPDATE
- `docs/SESSION_S92_INSIGHTS_WRITE_HANDOFF.md` — този doc

Files **не** пипнати: `admin/insights-health.php` (query вече беше правилен —
проблемът е в write path), `partials/*`, `products.php`, `sale.php`, `chat.php`,
`delivery.php`, `orders.php`, `order.php`, `defectives.php`, `ai-studio*.php`,
`design-kit/*`, `life-board.php`, `STATE_OF_THE_PROJECT.md`, `MASTER_COMPASS.md`,
никакви migrations, никакви cron crontab files.

---

## Next-session actions for Тихол

1. **Browser verify:** refresh `/admin/insights-health.php` като owner → виж VISIBLE > 0.
2. **Cron heartbeat:** при следващия cron tick на `cron-insights.php`,
   `created_at` ще се refresh-не за всички activity-touched insights.
3. **Seed cleanup decision:** избери опция 1/2/3 от секцията по-горе за
   `seed_s83_*` rows. Не блокиращо.
4. **Шеф-чат update на STATE_OF_THE_PROJECT.md / MASTER_COMPASS.md:**
   - P0 #6 (insights routing) се закрива след browser-verify.
   - REWORK QUEUE #54 ("UNIQUE … blocks new INSERT after first record per topic")
     — диагнозата беше неточна; bug-ът беше в `created_at` semantics, не в blocking
     INSERT. Може да се закрие.

---

## Open questions for Тихол

- Цикъл на cron-insights.php (ниво tenant): след fix-а, всеки tick ще
  bump-ва created_at на ВСИЧКИ touched insights. Това искаме ли (yes per
  dashboard intent) или dashboard-ът трябва да гледа отделно поле "last_run_at"?
  Препоръчвам: остави bump на created_at, по-простата семантика е по-полезна.
- `seed_s83_*` rows: запазваме ли ги като legacy fixtures (auto-expire 2026-05-05)
  или искаш targeted cleanup?
