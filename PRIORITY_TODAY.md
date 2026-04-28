# 🔴 ПРИОРИТЕТИ — 28.04.2026 (СЕСИЯ 1 BUILD)

**Дата:** 27.04.2026 края на ден
**Чете се при boot на нов шеф-чат сутринта на 28.04**
**Произход:** Lessons learned от 27.04.2026

---

## 🎯 ПРИОРИТЕТ #1 — products.php БЕЗ БЪГОВЕ

**Това е #1 над всичко друго.** Тихол изрично подчерта: "Утре найстина приоритет ни е да подкараме продуктс без бъгове."

### Контекст

На 27.04 Тихол работи цял ден с Opus 4.7 за бъгове в products.php. **Резултат: само 1 commit (#5 цвят chips). Останалите 6 бъга — само описани в SESSION_88_HANDOFF.md, не имплементирани.**

Денят беше загубен на:
- Дискусии вместо implementation
- Handoff документи без код
- Шеф-чат маркираше ✅ без verify

С v2.3 protocol това утре няма да се повтори.

---

### 7-те бъга (ground truth статус след 27.04)

| # | Бъг | Файлове | Статус |
|---|-----|---------|--------|
| **#1** | Снимки на вариации не се запазват в detail view | products.php, product-save.php, product_variations | 🟡 **DONE in tree, NOT COMMITTED** — quick win |
| **#2** | Сигнали празни (q1-q6) — demo placeholder cards вместо реални insights | products.php loadSections() + ai_insights | ❌ NOT STARTED — нужен on-screen debug |
| **#3** | "..." бутон → "📋 Като предния" (auto-increment code, празен barcode, qty=0 default + checkbox copy quantity, copy snimka) | products.php wizard | ❌ NOT STARTED |
| **#4** | Universal fuzzy match 80% Levenshtein на всички "Добави" бутони (цвят, размер, категория, подкатегория, доставчик, материя) | products.php + ALTER TABLE products ADD COLUMN material VARCHAR(50) | ❌ NOT STARTED — ИЗИСКВА migration |
| **#5** | Цвят prediction chips се отрязват | CSS .photo-color-input | ✅ **DONE (commit 5802655)** |
| **#6** | Дубликати по име/код/баркод при save → modal с 3 опции (Запази въпреки това / Отвори съществуващия / Откас) | products.php + product-save.php | ❌ NOT STARTED |
| **#7** | "Върни и презапиши" history с timeline + per-change revert (използва audit_log) | products.php product detail + ajax revert_change | ❌ NOT STARTED |

---

## 📋 ПЛАН ЗА СЕСИЯ 1 BUILD (08:00-12:00, 4 часа)

### Стъпка 1 — Reconnaissance (15 min)

Шеф-чат първо проверява:

```sql
SELECT name, COUNT(*) FROM products 
WHERE tenant_id=7 
GROUP BY LOWER(TRIM(name)) 
HAVING COUNT(*)>1;
```

**Защо:** Тихол е въвеждал артикули БЕЗ duplicate protection вчера → може вече да има дубликати в DB. Cleanup trябва да предхожда implementation на бъг #6.

**Output:** документирай findings в commit message.

---

### Стъпка 2 — Free Win: Commit Bug #1 (5 min)

Bug #1 е готов в дървото, само не е commit-нат.

```bash
cd /var/www/runmystore
git status
# Identify changes от вчера (snimki на вариации)
git add [конкретни файлове]
git commit -m "S88.PRODUCTS.BUG#1: variant photos persist в detail view"
git push origin main
```

**1 от 7 бъга закрит за 5 минути. Easy win.**

---

### Стъпка 3 — Failure Thresholds (Rule #10) — 5 min

Шеф-чат preset-ва прагове ПРЕДИ да пуска Code Code:

```
ПРАГ ЗА ДНЕС:
- Завършени 6/6 bugs до 17:00 → продължаваме с S84 AI Studio утре
- Завършени 4-5/6 bugs → утре сутрин довършваме, S84 push 1 ден
- Завършени <4/6 bugs → re-evaluate scope, бъгове #4 и #7 отлагаме
- 5+ NEW bugs открити в SESSION 2 TEST → нови features pause до Friday
```

Праг се удари → **изпълняваш предварително решение, не re-debate**.

---

### Стъпка 4 — Implementation (3-4 часа, 2 паралелни Code Code)

**Code Code #1 — Backend bugs (#2 + #6 + #4 migration):**
- Файлове: products.php (loadSections logic), product-save.php (duplicate check), DB migration ALTER products
- Order: #4 migration first (с Rule #9 8-step protocol) → #6 duplicate check → #2 sigali debug
- Numerical DOD: 3 bugs closed, 0 regressions

**Code Code #2 — UI bugs (#3 + #7):**
- Файлове: products.php (wizard "Като предния" + product detail history)
- Disjoint от Code #1 (различни секции на products.php)
- Numerical DOD: 2 bugs closed, mobile-tested на 375px

**Disjoint paths verified:** двата Code Code пипат различни секции на products.php. Възможен conflict само при git merge → Rule #1 verify хваща.

**Code Code #3 — STANDBY** за emergency.

---

### Стъпка 5 — Verify Each Handoff (Rule #1)

При всеки handoff от Code Code, **шеф-чат прави:**

```bash
git log -3 --oneline
git diff HEAD~1 --stat -- products.php product-save.php
# Match с handoff claim?
```

**Несъответствие → STOP + ask Тихол. Не маркирай ✅.**

---

## 🟡 ПРИОРИТЕТ #2 — S84 AI Studio Implementation

**Стартира САМО след products.php е безбъгов** (вечерта на 28.04 или сутринта на 29.04).

### Контекст

S83 на 27.04 финализира пълната архитектура (SESSION_83_HANDOFF.md, 1289 реда). Mockup ai_studio_FINAL_v5.html. **Production code: 0%.** Wizard в момента показва старата S82 версия.

### План (3 дни)

**Phase 1 (Ден 1):** DB + Backend
- 8 DB migrations (с **Rule #9 8-step protocol**)
- ⚠️ Phantom names alert: spec казва tenant_ai_credits, реално в LIVE е ai_credits_balance. Reconcile преди migration.
- Create 4 нови файла: ai-studio-vision.php, ai-studio-buy-credits.php, ai-studio-stripe-webhook.php, csv-export.php

**Phase 2 (Ден 2):** UI rewrite
- products.php: махане renderStudioStep, WIZ_LABELS 5→4, CTA card success екран
- ai-studio.php: пълен rewrite (3 режима: Лесен / Разширен / Купи)

**Phase 3 (Ден 3):** Stripe + Pricing
- Stripe Checkout integration
- Webhook handler
- 5 пакета × 3 типа кредити

**Phase 4:** Testing на tenant=7 + edge cases

### Deadline

ENI launch 14 май = 16 дни от 28.04. Buffer = 13 дни след AI Studio готов = достатъчно.

---

## 🟢 ПРИОРИТЕТ #3 — Animation v3 Rollout (когато products.php е чист)

5 модула вече с v3 (chat, life-board, stats, warehouse, sale). Останалите:
- products.php → след bugs fix-нати, ~30 min apply
- ai-studio.php → автоматично с S84 implementation

---

## 🔴 OPEN P0 BLOCKERS ЗА BETA (от 27.04 findings)

Тези трябва решени преди ENI launch 14 май:

1. **sale-save.php phantom columns** (REWORK #48, #49):
   - L29 INSERT-ва sales.payment_status — НЕ съществува
   - L52 INSERT-ва sale_items.tenant_id — НЕ съществува
   - Не блокира production защото никой не извиква sale-save.php
   - Timebomb — delete файла или integrate в S87 sale rewrite

2. **sale.php + sale-save.php divergent stock_movements field order** (REWORK #50):
   - Един от двата има грешен ред на полета
   - Verify в S87 sale rewrite

3. **Diagnostic Cat A/D 100%/100%** — fixed днес, но трябва remain healthy
   - TESTING_LOOP cron трябва да продължи да работи

---

## 📊 EOD METRICS (попълват се вечерта на 28.04)

```
PLANNED FOR 28.04:
- products.php: 6 bugs → 0 bugs (Bug #5 already done, Bug #1 quick commit)
- S84 AI Studio: започват ли днес?

ACTUAL (verified чрез git log):
- products.php bugs closed: ___ / 6
- New issues discovered: ___
- Code Code sessions used: ___
- Time spent: ___ hours
- Commits pushed: ___

DELTA:
- Не направено: ___
- Направено непланирано: ___
- Total delta: ~___%

ROOT CAUSES (ако delta >30%):
- ___

LESSONS:
- ___

TOMORROW (29.04) PRIORITY:
- ___
```

---

## ⚙️ ДОКУМЕНТАЦИЯ КОЯТО ШЕФ-ЧАТ ТРЯБВА ДА ПОМНИ

### Code Code сесия време-бюджет

- 1 Code Code сесия = max 6 часа (Rule #6)
- 1 час работа = 1+ commits + 50-200 lines code минимум
- Подозрителни сигнали: 0 commits след 1+ час, документ вместо код

### Parallelism

- Max 2 Code Code + 1 Opus 4.7 + Тихол = 4 общо (Rule #7)
- Disjoint paths задължително преди паралел
- Код #3 STANDBY за emergency

### Verify pattern

- Преди всеки ✅: `git log -3 --oneline` + `git diff HEAD~1 --stat -- [файлове]`
- Match handoff claims с git реалност
- Несъответствие → STOP + ask

---

**Край на PRIORITY_28_04_2026.md**

*Записан: 27.04.2026 края на ден.*
*Чете се при boot на нов шеф-чат сутринта на 28.04.*
*Място в repo: /var/www/runmystore/PRIORITY_TODAY.md (replace)*
