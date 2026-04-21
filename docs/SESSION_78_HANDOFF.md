# SESSION 78 HANDOFF
## RunMyStore.ai | 21.04.2026
## Тип: FOUNDATION (DB migrations + compute-insights skeleton + P0 bug verification)
## Модели: Claude Opus 4.7 (CHAT 1 + CHAT 2 паралелно)

---

# 🎯 ОБОБЩЕНИЕ

S78 = **ФУНДАМЕНТ ГОТОВ**. Базата данни има всички S77 таблици. compute-insights.php skeleton е на място. P0 bugs от S71-S77 проверени.

**Git tag:** `v0.5.0-s78-foundation`

---

# ✅ ЗАВЪРШЕНО

## DB (CHAT 1 → CHAT 2)
- **Commit `2eb2a6f`** (CHAT 1): 7 таблици — ai_insights, ai_shown, search_log, lost_demand, supplier_orders, supplier_order_items, supplier_order_events + 6 нови колонки в lost_demand (suggested_supplier_id, matched_product_id, resolved_order_id, times, first_asked_at, last_asked_at) + 7 нови колонки в ai_insights (fundamental_question, product_id, supplier_id, action_label, action_type, action_url, action_data)
- **Commit `6100b06`** (CHAT 2): 2 допълнителни таблици — idempotency_log, user_devices + 5 колони в други таблици
- Backup: `/root/backup_s78_20260421_1247.sql`

## compute-insights.php (CHAT 2)
- **Commit `eaf9466`**: Skeleton с 19 функции × 6 fundamental questions
- Тест run: `php compute-insights.php 7` → `{inserted:0, skipped:19, errors:[]}` ✅ (skipped = skeleton TODO-bodies, очаквано)

## P0 Bugs (CHAT 1 verification)
- **Bug #5 `_hasPhoto`**: ✅ вече fix-нат. Ред **7177** в products.php: `S.wizData._hasPhoto=true;` след `FileReader.onload`.
- **Bug #7 `sold_30d=0`**: ✅ вече fix-нат. Редове **412, 419**: LEFT JOIN subquery със `SUM(si99.quantity)` от sale_items за 30 дни + tenant_id + store_id filter.

---

# ⏸ ОТЛОЖЕНО

## Bug #6 `renderWizard нулира бройки`

**Причина:** Не може да се тества. S79 главна rewrite (направен от друг чат преди S78 в реалния timeline) е счупил 10 event handlers на products.php главна — включително бутона "+ Добави". Wizard-ът не се отваря, следователно не може да достигне до step 4/5/6 където е бъгът.

**Засегнат документ:** `PRODUCTS_MAIN_BUGS_S80.md` (при Тихол)

**Re-try:** S79.FIX, след като "+ Добави" проработи.

---

# 🔴 НОВА СЕСИЯ — S79.FIX (преди S80 wizard rewrite)

От `PRODUCTS_MAIN_BUGS_S80.md` — 10 счупени бутона на products.php главна:

**P0 (блокира ЕНИ тест май 2026):**
- Добави артикул [+] — не работи
- Моливче ✏️ manual — не работи
- Q-секции → отварят EDIT вместо LIST (data corruption risk)
- "Виж всички N артикула" — не работи
- Search autocomplete — липсва

**P1:**
- хамбургер, voice main, filter drawer (може S82), voice wizard

**P2:**
- три точки (CSV placeholder)

**ETA:** 6-8 часа (1 сесия). Финален tag: `v0.5.1-s79fix-buttons`.

---

# 📦 СЛЕД S79.FIX — S80 (wizard rewrite)

Чак след като "+ Добави" отваря wizard, S80 може да започне:
1. Retry Bug #6 (renderWizard нулира бройки)
2. Wizard 4 стъпки + fullscreen matrix overlay
3. Минимален запис
4. Печат overlay от всяка стъпка

---

# 🗂 GIT ЛОГ (последни за S78)
---

**КРАЙ НА SESSION 78 HANDOFF**
