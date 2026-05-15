# 🚀 CLAUDE CODE — STRESS LAB FULL IMPLEMENTATION

**Date:** 16 May 2026 (planned, утре)
**Duration:** 4-6 часа в tmux session
**Target:** tenant_id=7 (Тих's пробен профил)

---

## КОНТЕКСТ

Тих е basicallа founder на RunMyStore.AI. Той НЕ е developer. tenant_id=7 е пробен профил — фиктивни данни разрешени. Защитата `assert_stress_tenant()` за tenant=7 МОЖЕ да се пропусне (вече е премахната в S144).

**Какво беше направено в S144 (фундамент):**
- 1004 fake sales seeded чрез `tools/seed/sales_smart_seed.php` (commit c990e70)
- 25 generic AI insights в compute-insights.php → 13 активни
- tenant=7 има 387 products, 30 stores

**Какво ще направиш ти (S145+):**
- 8 stores config (стандартизирани)
- 11 suppliers с lead times
- 90 дни история
- 10-те S142 типа сигнали имплементирани (НОВО — критично)
- Weather signal integration
- 4-те cron-а активирани

---

## ЗАДЪЛЖИТЕЛНО ПРЕДИ РАБОТА

**Прочети В ТОЧНО ТОЗИ РЕД:**

1. **`SESSION_S144_FULL_HANDOFF.md`** — пълен handoff, всичко важно
2. **`DESIGN_SYSTEM_v4.0_BICHROMATIC.md` §3** — header (3 форми) + bottom-nav (session-based)
3. **`docs/SIGNALS_CATALOG_v1.md`** — 25-те имплементирани сигнала + body templates
4. **`tools/seed/sales_smart_seed.php`** — pattern за seed (от S144)
5. **`compute-insights.php`** — backbone на AI insights (НЕ ПИПАЙ логиката, само добавяй нови pf*() функции)
6. **`config/helpers.php`** § `getInsightsForModule()` + `insightVisible()` + `shouldShowInsight()`
7. **`build-prompt.php`** § Layer 8 — Weather Context (вече имплементиран)
8. **`docs/PRODUCTS_DESIGN_LOGIC.md`** §1 — 6 fundamental sections (структура)
9. **`SESSION_S142_FULL_HANDOFF.md`** редове 738-760 — финалните 10 типа сигнали
10. **`STRESS_BUILD_PLAN.md`** — пълен план на 8 stores + 11 suppliers + 5 sellers

---

## КЛЮЧОВИ ПРАВИЛА (НИКОГА НЕ СЕ НАРУШАВАТ)

### Дизайн (§3 DESIGN_SYSTEM_v4.0_BICHROMATIC.md):

**HEADER (3 форми):**
- **Форма А** — `chat.php`: brand + plan-badge + spacer + Print + Settings + Logout + Theme
- **Форма Б** — ВСИЧКИ ОСТАНАЛИ: brand + spacer + Theme + Продажба pill
- **Форма В** — `sale.php`: БЕЗ header (камерата е горната част)

**BOTTOM-NAV** (session-based):
- Чети `$_SESSION['active_mode']`
- Simple → chat-input-bar (НЕ 4 tabs)
- Detailed → 4 tabs (AI/Склад/Справки/Продажба)

### Закон #1 — Пешо НЕ пише
Всеки UI елемент = voice / снимка / 1 натискане. Никога keyboard за текст.

### Закон #2 — PHP смята, AI говори
Числата идват от PHP+SQL → AI ги вокализира. Никога директни AI изчисления.

### Закон #3 — AI мълчи, PHP продължава
При AI failure системата не се блокира.

### Закон #6 — Simple=signals, Detailed=data
Всяка Detailed функция → задължително има еквивалентен сигнал в Simple.

### Tенант писане
- tenant_id=7 = OK (пробен, защитите свалени)
- НЕ пиши в други tenants без изрично разрешение

### Технически
- `DB::run()` навсякъде (не директно `$pdo`)
- DB поле имена: `products.code` (не sku), `products.retail_price` (не sell_price)
- `priceFormat($amount, $tenant)` за пари
- Всеки тeкст през `tenant.lang` (i18n)
- MySQL 8: НЕ поддържа `ADD COLUMN IF NOT EXISTS` → използвай PREPARE/EXECUTE с information_schema
- Python xz+base64 ≤11KB за deploy в droplet конзола

---

## ЗАДАЧИ ПО ПРИОРИТЕТ

### ⭐ ПРИОРИТЕТ 1 — 10-те S142 типа сигнали в compute-insights.php (3 часа)

**Цел:** Превърни Detailed Mode данните в action-oriented сигнали за Simple Mode.

За всеки от 10-те типа добави **нова pf*() функция** в `compute-insights.php`. НЕ пипай съществуващите 25 функции — само добавяй нови.

**Файл за писане:** Добавяй секции в `compute-insights.php` под секцията за existing insights.

#### 10-те типа:

1. **`pfAlertStockout()` — 🔴 Alert (q1 red)**
   - Hardcoded "Свърши Nike Air Max 42 · 7 продажби тази седмица"
   - **Реална логика:** артикули с `qty=0` И `sold_7d > 5` (recent sales pattern)
   - data_json: `{items: [{name, sold_7d, lost_revenue}], top1}`
   - fundamental_question: 'loss', urgency: 'critical'

2. **`pfWeatherWarming()` — 🌤 Weather (q5 amber)**
   - **Логика:** Чети `getWeatherForecast()` (вече е в build-prompt.php Layer 8).
   - Ако temp ↑ 5°C за 7 дни напред → препоръчай летни артикули (`season='лято'` или `season='summer'`)
   - data_json: `{weather: {temp_now, temp_forecast, days}, recommended_products: [...]}`
   - fundamental_question: 'order', urgency: 'warning'

3. **`pfTransferRecommend()` — 🔄 Transfer (q4 cyan)**
   - **Логика:** Артикул с qty=0 в Store A, но qty>5 в Store B, със продажби в A
   - data_json: `{from_store: B, to_store: A, product: {...}, qty: 5}`
   - fundamental_question: 'gain_cause', urgency: 'warning'

4. **`pfCashTrapped()` — 💰 Cash trapped (q2 purple)**
   - **Логика:** Замразен капитал = SUM(qty × cost_price) за артикули без продажби 60+ дни
   - data_json: `{total_eur, items: [...]}`
   - fundamental_question: 'anti_order', urgency: 'info'

5. **`pfSizeRun()` — 📏 Size run (q5 amber)**
   - **Логика:** Parent артикул с variants. Един размер (е.g. M) на 0, останалите (S, L) имат stock. С продажби в M.
   - data_json: `{parent: {...}, broken_size: 'M', remaining: ['S', 'L'], sold_30d: X}`
   - fundamental_question: 'order', urgency: 'warning'

6. **`pfSupplierLate()` — 📦 Supplier (q1 red)**
   - **Логика:** ЧАКА deliveries module — за сега skip или mockup
   - Стартирай функцията но return [] ако deliveries module = 0%
   - fundamental_question: 'loss_cause', urgency: 'warning'

7. **`pfCashVariance()` — 💸 Cash variance (q1 red)**
   - **Логика:** ЧАКА Z отчети (cashbook). За сега skip.
   - fundamental_question: 'loss_cause', urgency: 'critical'

8. **`pfSellThrough()` — 📈 Sell-through (q5 amber)**
   - **Логика:** Артикули добавени през последните 30 дни. Колко % са продадени?
   - Ако < 25% → markdown препоръка
   - data_json: `{items: [...], sell_through_pct, target_pct: 25, age_days}`
   - fundamental_question: 'order', urgency: 'warning'

9. **`pfProfitTrend()` — 🟡 Trend (q3 green)**
   - **Логика:** profit_7d vs profit_30d_avg. Ако >10% growth → trend up.
   - data_json: `{growth_pct, period: '7d', revenue_7d, revenue_30d_avg}`
   - fundamental_question: 'gain', urgency: 'info'

10. **`pfWinRecord()` — 🟢 Win (q3 green)**
    - **Логика:** Дневни продажби > max(30d). Рекорден ден.
    - data_json: `{day, sales_count, revenue, prev_max}`
    - fundamental_question: 'gain', urgency: 'info'

**Verify след всяка функция:**
```sql
SELECT COUNT(*) FROM ai_insights 
WHERE tenant_id=7 AND topic_id LIKE 'pf_alert_stockout%' 
AND created_at > NOW() - INTERVAL 5 MINUTE;
```

### ⭐ ПРИОРИТЕТ 2 — STRESS Lab seed (2 часа)

**Файл за писане:** `tools/seed/stress_lab_seed.php`

#### 1. 8 stores (ако не съществуват):
```php
$stores = [
    ['name'=>'Склад централа', 'type'=>'warehouse'],
    ['name'=>'Магазин Дрехи Витоша', 'type'=>'shop'],
    ['name'=>'Магазин Обувки Бургас', 'type'=>'shop'],
    ['name'=>'Магазин Mixed Пловдив', 'type'=>'shop'],
    ['name'=>'Магазин High-volume Варна', 'type'=>'shop'],
    ['name'=>'Магазин Бижута Скайтия', 'type'=>'shop'],
    ['name'=>'Магазин Дом Стара Загора', 'type'=>'shop'],
    ['name'=>'Онлайн магазин', 'type'=>'online'],
];
```

#### 2. 11 доставчика с lead times (5-15 дни):
```php
$suppliers = [
    ['name'=>'Verona Italia', 'lead_time_days'=>14, 'reliability'=>62],
    ['name'=>'Marina Bulgaria', 'lead_time_days'=>5, 'reliability'=>95],
    // ... 9 still
];
```

#### 3. 5 продавача (sellers):
```php
$sellers = [
    ['name'=>'Иван Иванов', 'role'=>'seller'],
    ['name'=>'Мария Петрова', 'role'=>'seller'],
    // ...
];
```

#### 4. 90 дни история:
- 200-400 продажби на ден разпределени по 7-те физически stores
- 20-40 онлайн продажби на ден
- Random sezonal patterns (лятна/зимна стока)
- 1-3 доставки на седмица per supplier
- Inventory намалявай след всяка продажба

#### 5. ATAKI — за тестване AI логики:
- **5 артикула под себестойност** → `selling_at_loss`
- **5 артикула с марж <15%** → `margin_below_15`
- **10 бестселъра до 0 stock** → `zero_stock_with_sales`
- **20 артикула 90+ дни без продажба** → `zombie_45d`
- **3 size runs broken** (parent с variant М на 0, S+L има) → `pfSizeRun`
- **2 multi-store gaps** (Store A=0, Store B=5+) → `pfTransferRecommend`

#### 6. is_test_data=1 МАРКЕР:
ВСИЧКИ insertions с `is_test_data=1` за лесен rollback.

### ⭐ ПРИОРИТЕТ 3 — Cron activation (30 мин)

```bash
ls /etc/cron.d/stress-*.disabled
# Активирай 4-те (ако стресс защита е свалена за tenant=7):
sudo mv /etc/cron.d/stress-nightly.disabled /etc/cron.d/stress-nightly
sudo mv /etc/cron.d/stress-morning.disabled /etc/cron.d/stress-morning  
sudo mv /etc/cron.d/stress-newfeat.disabled /etc/cron.d/stress-newfeat
sudo mv /etc/cron.d/stress-sanity.disabled /etc/cron.d/stress-sanity
sudo systemctl reload cron
```

### ⭐ ПРИОРИТЕТ 4 — Pre-flight checks

```sql
-- Total insights
SELECT COUNT(*) FROM ai_insights WHERE tenant_id=7;

-- New 10 types — verify
SELECT topic_id, urgency, fundamental_question, title 
FROM ai_insights 
WHERE tenant_id=7 
AND created_at > NOW() - INTERVAL 1 HOUR
ORDER BY urgency;

-- Insights per fundamental_question
SELECT fundamental_question, COUNT(*) 
FROM ai_insights 
WHERE tenant_id=7 AND (expires_at IS NULL OR expires_at > NOW())
GROUP BY fundamental_question;
```

**Очаквани резултати:**
- Total insights ~50-100
- Всичките 10 нови типа active
- 6 fundamental_questions всички populated (loss, loss_cause, gain, gain_cause, order, anti_order)

---

## ROLLBACK PLAN (ако нещо счупи)

```sql
-- Махни всички test data
DELETE FROM sale_items WHERE sale_id IN (
    SELECT id FROM sales WHERE tenant_id=7 AND is_test_data=1
);
DELETE FROM sales WHERE tenant_id=7 AND is_test_data=1;

DELETE FROM ai_insights WHERE tenant_id=7 
AND topic_id IN (
    'pf_alert_stockout', 'pf_weather_warming', 'pf_transfer_recommend',
    'pf_cash_trapped', 'pf_size_run', 'pf_supplier_late',
    'pf_cash_variance', 'pf_sell_through', 'pf_profit_trend', 'pf_win_record'
);

-- compute-insights.php → git revert на промените
cd /var/www/runmystore
git log --oneline | head -5
git revert <commit_hash>
```

---

## GIT WORKFLOW

```bash
cd /var/www/runmystore
git pull origin main

# Работата ти — в малки commits
git add compute-insights.php
git commit -m "S145.1: pfAlertStockout — Alert signal (q1 critical)"
git push origin main

# Repeat за всяка функция (10 commits общо)

git add tools/seed/stress_lab_seed.php
git commit -m "S145.2: stress_lab_seed.php — 8 stores + 11 suppliers + 90д история"
git push origin main
```

---

## ⛔ ЗАБРАНИ

1. **НЕ пипай** existing compute-insights функции — само добавяй нови
2. **НЕ пипай** chat.php, products.php, products-v2.php, sale.php — само backend (compute-insights, seed)
3. **НЕ въвеждай** нови composer dependencies
4. **НЕ ALTER-вай** schema на ai_insights — съществуващите колони стигат
5. **НЕ commit-вай** direct-to-main без pull --rebase първо
6. **НЕ премахвай** is_test_data=1 от съществуващи records
7. **НЕ генерирай** insights за други tenants освен 7
8. **ПИТАЙ ТИХ** ако не разбираш design choice — БЕЗ предположения

---

## ⏰ TIMELINE

- **Hour 1:** Прочит на 10-те файла (горе) + setup
- **Hour 2-3:** 10-те pf*() функции в compute-insights.php
- **Hour 3-4:** stress_lab_seed.php + run
- **Hour 4:** Cron activation + pre-flight checks
- **Hour 4-5:** Bug fixes + verification
- **Hour 5-6:** Final review + git push всичко + handoff back to Тих

---

## КАК ДА ПРИКЛЮЧИШ

Когато си готов → push commit-овете → напиши в tmux session:

```
═══════════════════════════════════════════
✅ STRESS LAB COMPLETE
═══════════════════════════════════════════
- 10 нови сигнал типа активни в compute-insights.php
- 8 stores + 11 suppliers + 5 sellers seeded
- 90 дни история + atak data
- 4 cron-ове активирани  
- Total insights: ~XX
- Commits pushed: N

Pull-ни Тих:
cd /var/www/runmystore && git pull origin main
═══════════════════════════════════════════
```

Тих ще се върне в чата → проверка → продължаваме нататък.

---

## КОНТЕКСТ ЗА ТЕБЕ КАТО CLAUDE CODE

- Тих ще те включи в tmux session и ще те остави да работиш сам
- ВСИЧКИ техникски решения (методи, файлове, библиотеки) → ТИ решаваш
- ВСИЧКИ продуктови решения (UX, имена, текстове) → ПИТАЙ ТИХ
- Ако нещо те бута да правиш промяна извън scope-а → СПРИ и питай

**Тих очаква професионален резултат — ти си working sand. Действай със confidence но и предпазливост.**

---

END PROMPT
