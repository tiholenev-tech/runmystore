# SESSION 78 HANDOFF
## RunMyStore.ai | 19.04.2026
## Тип: IMPLEMENTATION — DB фундамент + P0 бъгове + compute-insights skeleton
## Модел: Claude Opus 4.7 (1M context)

---

# 🎯 ОБОБЩЕНИЕ

S78 = фундамент за products.php пълно завършване (S78 → S82 план).

**Deliverables:**
1. S77 DB миграция пусната и верифицирана (7/7 обекта стоят).
2. P0 бъгове #5, #6, #7 от products.php затворени.
3. Wizard step 4 UX fix — празен axis позволява Запиши.
4. compute-insights.php разширен с 19 skeleton функции по 6-те фундаментални въпроса + hook в products.php.

---

# 📦 DB СТАТУС (след миграция)

**Backup преди миграция:** `/root/backup_s78_20260419_1829.sql` (1.37 MB)

## Нови таблици (3)
- `supplier_orders`
- `supplier_order_items`
- `supplier_order_events`

## Обновени таблици
- `ai_insights` +3 колони:
  - `fundamental_question` ENUM('loss','loss_cause','gain','gain_cause','order','anti_order') NULL
  - `product_id` INT NULL (index `idx_product`)
  - `supplier_id` INT NULL
  - + `idx_question` на fundamental_question
- `lost_demand` +2 колони:
  - `suggested_supplier_id` INT NULL (index `idx_supplier`)
  - `resolved_order_id` INT NULL

## Верификация на дата 2026-04-19 19:35 UTC

| Проверка | Резултат |
|---|---|
| SHOW TABLES LIKE 'supplier%' | supplier_orders, supplier_order_items, supplier_order_events (и трите) ✅ |
| DESCRIBE ai_insights колони | fundamental_question ENUM ✅ · product_id INT MUL ✅ · supplier_id INT ✅ |
| DESCRIBE lost_demand колони | suggested_supplier_id MUL ✅ · resolved_order_id ✅ |
| SELECT COUNT(*) FROM ai_insights | 37 реда ✅ |

---

# 🔴 P0 БЪГОВЕ — затворени

## Бъг #7 — sold_30d винаги = 0 за артикули с варианти ✅

**Симптом:** списъкът на артикули показваше 0 продажби за parent артикули с варианти, дори когато варианти се продават.

**Причина:** correlated subquery `WHERE si99.product_id = p.id` — parent-ите имат `parent_id IS NULL` и продажбите се пишат в `sale_items.product_id = child.id`.

**Fix (финална версия — commit `ba4ff1d`):** замяна с JOIN + UNION условие:
```sql
(SELECT SUM(si99.quantity) 
 FROM sale_items si99 
 JOIN sales s99 ON s99.id = si99.sale_id 
 JOIN products cp2 ON cp2.id = si99.product_id 
 WHERE (cp2.id = p.id OR cp2.parent_id = p.id)
   AND s99.store_id = {$sid} 
   AND s99.status != 'canceled' 
   AND s99.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
```

**Първият опит** (CASE WHEN EXISTS children → children ELSE parent) беше регресивен — връщаше 0 за parent-и с children когато продажбите още са записани на parent ID. Refine-нат в commit `ba4ff1d`.

**Верификация:** 5 топ артикула тествани срещу reference query — 1:1 съвпадение.

## Бъг #6 — renderWizard нулира бройки (step 6 Печат) ✅

**Симптом:** при смяна на tab (€+лв / само € / Без цена) на step 6 бройките за печат се нулираха.

**Причина:** tab onclick викаше `renderWizard()` директно → `innerHTML` се пренаписваше → input values в `lblQty*` се губеха, защото `S.wizData._printCombos[i].printQty` не беше синхронизирана с inputs.

**Fix (commit `18efa48`):**
1. `wizCollectData()` разширена — при `S.wizStep===6` копира `lblQty*` → `_printCombos[i].printQty`.
2. `renderWizard()` вика `wizCollectData()` в началото при `S.wizStep===6`.

## Бъг #5 — AI Studio `_hasPhoto` не се сетва ✅

**Статус:** beше вече поправен преди S78 (ред 6554 на products.php). Не изисква нов code — tasks маркирани completed.

---

# 🎨 WIZARD UX FIX

## Step 4 — празен axis третиран като несъществуващ (commit `e8ef499`)

**Симптом:** default wizard създава 2 axes (Вариация 1 + Вариация 2) с празни values. Ако user избере стойности само в axis 1, footer-ът показваше "Избери вариация 2" вместо "Запиши" — user се блокираше.

**Fix:** `_v4ComputeFooter` — премахната логиката за търсене на `nextEmptyIdx`. Празен axis се игнорира. Save button се показва веднага щом активният axis има стойности. User все още може да добави стойности в други axes чрез tab-овете.

---

# 🧩 COMPUTE-INSIGHTS.PHP — SKELETON

**Commit:** `20736b2`

## Нови функции по S77 6-те въпроса (19 skeleton)

| Категория | Функции | fundamental_question |
|---|---|---|
| LOSS (3) | pfZeroStockWithSales, pfBelowMinUrgent, pfRunningOutToday | `loss` |
| LOSS_CAUSE (4) | pfSellingAtLossFQ, pfNoCostPriceFQ, pfMarginBelow15, pfSellerDiscountKiller | `loss_cause` |
| GAIN (2) | pfTopProfit30d, pfProfitGrowth | `gain` |
| GAIN_CAUSE (5) | pfHighestMargin, pfTrendingUp, pfLoyalCustomers, pfBasketDriver, pfSizeLeader | `gain_cause` |
| ORDER (2) | pfBestsellerLowStock, pfLostDemandMatch | `order` |
| ANTI_ORDER (3) | pfZombie45d, pfDecliningTrend, pfHighReturnRate | `anti_order` |

Всяка е **празна skeleton** с TODO коментар за S79. Тялото (SQL + upsertInsight) се напълва в S79.

## Wrapper

`computeProductInsights(int $tid, int $sid, string $cur)` — извиква всички 19. Добавен в `computeAllInsights()`.

## upsertInsight разширен

Добавени колони в INSERT/UPDATE: `fundamental_question`, `product_id`, `supplier_id`. Съвместим със старите викове — всички нови параметри са default NULL.

## Hook в products.php

Нов AJAX endpoint:
```
GET products.php?ajax=compute_insights
→ извиква computeProductInsights()
→ връща {"ok":true,"computed":19}
```

---

# 📝 COMMITS (S78, по ред)

```
8fe8584  S78: Fix Bug #7 — sold_30d aggregates child variations to parent
18efa48  S78: Fix Bug #6 — persist print qty across re-renders on step 6
20736b2  S78: compute-insights.php skeleton (19 product functions, 6 questions)
ba4ff1d  S78: Refine Bug #7 fix — union parent + child sales instead of either/or
e8ef499  S78: Wizard step 4 — empty axis treated as non-existent (allow Save)
```

Всички push-нати в `origin/main`.

---

# 🚀 S79 СТАРТ

## Контекст

След S78 DB фундаментът е напълно готов. compute-insights.php има структура и hook-ове, но тялото е празно. products.php запазва старата главна (списък с филтри) — НЕ е rewrite-нат по S77 6-секции дизайн.

## Приоритети в S79 (според S77 ROADMAP)

### 1. products главна rewrite (6 секции h-scroll)
- Нов HTML layout: 6 секции по 162px карти с horizontal scroll
- Neon Glass CSS за 6 hue (q1-q6): red/violet/green/teal/amber/grey
- Q-head badge + total pill per секция
- Контекстни text-ове на divider (число + защо + profit)
- Tap артикул → edit flow

### 2. AJAX endpoint `ajax=sections`
- Зарежда данни per секция от `ai_insights` WHERE `fundamental_question=?`
- Pagination/limit 8 артикула per секция
- JOIN products за кoмплектен card data

### 3. Напълване на 19-те pf функции
Реален SQL за всяка от 19-те skeleton функции:
- Всяка задължително извиква `upsertInsight` с правилно `fundamental_question` + `product_id` (където е релевантно) + `supplier_id`
- `expires_at` = NOW() + 30 MINUTE (вече default в upsertInsight)

### 4. Reference HTML mockup
`products-home-6-questions.html` (от S77 design session) — 1:1 стилове.

## Ред на имплементация (препоръка)

1. Backup-ни DB (MYSQL_PWD env, `/root/backup_s79_YYYYMMDD_HHMM.sql`)
2. Напълни pf функции (по две-три на commit, verify SQL срещу данните на tenant 7)
3. Добави `ajax=sections` endpoint
4. Rewrite на главния HTML view (запази стария като fallback зад query param `?legacy=1` ако е необходимо)
5. CSS: новите Neon Glass 6-hue класове в DESIGN_SYSTEM.md
6. Test срещу localhost tenant=7, store=47

## Важни референции

- `docs/SESSION_77_HANDOFF.md` — пълна спецификация на 6-те секции
- `docs/BIBLE_v3_0_APPENDIX.md` §6 — фундаменталните въпроси закон
- `docs/PRODUCTS_DESIGN_LOGIC.md` — UX детайли
- `DESIGN_SYSTEM.md` — Neon Glass компоненти
- **BIBLE_v3_0_CORE.md и BIBLE_v3_0_TECH.md ЛИПСВАТ** в /docs — NARACHNIK_TIHOL_v1_1.md ги посочва като задължителни. Ако са необходими в S79, трябва да се създадат/възстановят.

## Test данни

- tenant_id=7, store_id=47
- Parents с children: 32
- Sale items last 30d: 219 (всички на parent ID, 0 на child ID — данни модел за вариант продажби още не съществува в тест средата)

---

# ⚠ ОТВОРЕНИ ВЪПРОСИ ЗА S79

1. **BIBLE_v3_0_CORE.md / TECH.md** — липсват в репото. Трябват ли? Ако да — кой ги пише: Claude ги възстановява от appendix + NARACHNIK, или Тихол ги предоставя?

2. **days_stale subquery** — на ред 254 products.php `$dse` има същия parent-only проблем като бившия sold_30d: `WHERE si99.product_id=p.id`. Не е в S78 scope, но заслужава fix в S79 (същия pattern — UNION по children). Тихол да потвърди.

3. **Бъг #5 UI тест** — Тихол не потвърди дали "_hasPhoto не се сетва" все още се проявява в реалния UI. Кодът според S78 анализа вече е коректен. Ако UI проблем остане — не е този бъг, а friend бъг за друг code path.

---

**КРАЙ НА SESSION 78 HANDOFF**
