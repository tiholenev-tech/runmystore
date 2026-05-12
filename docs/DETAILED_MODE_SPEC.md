# 📊 DETAILED MODE SPEC — products.php разширен режим

**Версия:** v1.0
**Дата:** 2026-05-12 (S141 шеф-чат EOD)
**За кого:** Следващ чат (S142+) който прави Step 3 на products-v2.php
**Source mockup:** `mockups/P2_v2_detailed_home.html` (1853 реда — canonical)

---

## ⚡ TL;DR

products-v2.php в **разширен режим** (?mode=detailed) има **4 таба**:

| Tab | Какво показва | Размер |
|---|---|---|
| **Преглед** | KPI dashboard + AI сигнали | ~250 реда |
| **Графики** | 6 chart типа | ~100 реда |
| **Управление** | Доставчици + multi-store + saved views + bulk | ~60 реда |
| **Артикули** | Quick filter chips → отива в P3 list | ~150 реда |

**Общо detailed content:** ~560 реда HTML + PHP queries за всеки.

---

## §0 ФИЛОСОФИЯ — DETAILED vs SIMPLE (Закон №6 от Bible)

**Detailed Mode не е "Pro версия на Simple Mode".** Двата режима имат различни цели, потребители и взаимодействия.

### Кой го гледа

- **Detailed:** Митко (собственик / мениджър). Иска да види **защо** нещо се случва. Експлорира данни. Прави стратегически решения.
- **Simple:** Пешо (продавач / non-tech owner). Иска да види **какво да направи сега**. Реагира на AI-curated сигнали.

### Какво вижда

| | Detailed | Simple |
|---|---|---|
| **Информационен слой** | Raw data — KPI, графики, таблици | Curated signals — алерти, тенденции, действия |
| **Интеракция** | Pull — сам търси, филтрира, експлорира | Push — AI казва кое е важно |
| **Cognitive load** | Висок (250+ числа достъпни) | Нисък (10-30 сигнала per ден) |
| **Brain mode** | Analytical | Reactive |

### Симетрия — двата режима НЕ са разделени

**Всеки сигнал в Simple ← пълни данни в Detailed.**

Пример:
- Simple: "🔴 Nike Air Max 42 свърши · 7 продажби тази седмица"
- Tap → Detailed Tab Артикули, filtered на N42, отворен на History sub-tab → виждаш доставки, multi-store, sales pattern

Audit trail = задължителен (Закон №7). Без него Пешо губи доверие в AI.

### Какво е в Detailed но НЕ е в Simple

**Структурни / аналитични insights:**
- Pareto 80/20 разпределение
- Sezonalnost heatmap
- ABC класификация
- Margin distribution histogram
- Multi-store comparison bar chart
- Доставчик performance breakdown
- Saved views & bulk actions

Тези **не са actionable** в момента — те са фон за стратегически решения. Митко ги използва когато прави плана за следващия квартал. Пешо никога не ги вижда.

### Какво е в Simple но НЕ е в Detailed (нищо)

Detailed съдържа **superset** от информацията. Всичко в Simple е достъпно в Detailed (като raw query). Просто в Detailed данните не са pre-filtered от AI.

### Implications за дизайна на Detailed

1. **Богат — не пести данни.** Митко иска да види всичко. 5 KPI вместо 3. 7 charts вместо 4. Multi-store breakdown навсякъде.

2. **Filter-heavy** — Митко работи с филтри (period, store, supplier, category, sub-category, color, size). Всеки filter е "опитах ли този cut".

3. **Manual control винаги достъпен** — search, sort, bulk actions. Митко не иска "AI choose-те за мен" (това е Simple).

4. **AI insights видими, но не натрапени** — в Графики таб има "AI откри Sezonnost", но Митко може да го пропусне. В Simple същият insight е централен.

5. **Comparative views** — versus миналата седмица/месец/година. Versus други магазини. Versus конкуренти (Phase 5+ DUAL-AUDIENCE).

### Implications за дизайна на Simple

1. **Минимум 5-10 сигнала visible** при стартиране на app-а. Никога празно.

2. **1-tap action** на всеки сигнал. Без второ ниво. Без modal-и за избор.

3. **AI обобщава** — "Свършили: 5 артикула" е по-добре от 5 отделни сигнала за всеки артикул. Tap → разкрива списъка.

4. **Confidence > 0.85 only auto-show.** Lower confidence sigнали остават в Detailed (за curious Митко).

5. **Voice винаги достъпен** — search bar има микрофон. Закон №1.

---

## 1. ОБЩА СТРУКТУРА

### Header (Тип Б)
- Brand "RunMyStore.ai" + PRO badge
- Spacer
- Модулен бутон: **📷 Камера** (за scan на баркод)
- Принтер | Настройки | Изход | Тема

### Subbar
```
[Магазин ▾]   СКЛАД   [← Лесен]
```

### Глобален inv nudge (Закон №10)
**Под subbar-а, преди табовете:**
```
⏳ 23 артикула не са броени · 12 дни →
```
- Tap → отваря inventory.php zone walk
- Persistent на всеки модул

### Tab bar
```
[Преглед] [Графики] [Управление] [Артикули]
```
- Sticky под inv nudge
- Default active: Преглед

---

## 2. TAB 1 — ПРЕГЛЕД (KPI dashboard)

### 2.1 Period compare toggle
```
[Тази] [Минала седмица] [30 дни] [90 дни]
```
- Default: Тази седмица
- Сменя контекст на всички долу числа

### 2.2 Quick Actions (3 бутона)
```
[+ Добави артикул]   [📋 Като предния]   [✨ AI поръчка]
```
- "Добави" → отваря wizard (sacred zone)
- "Като предния" → копира last entry
- "AI поръчка" → AI Studio entry

### 2.3 Quick Stats Row — 3 KPI cards

| Card 1 | Card 2 | Card 3 |
|---|---|---|
| **Приход** 12,450€ | **Продадени** 234 бр | **Среден марж** 28% |
| ▲ +12% спрямо мин. | ▲ +5% | ▼ -2% |
| Sparkline 7 дни | Sparkline 7 дни | Sparkline 7 дни |

**PHP query needed:**
```sql
-- Приход за период
SELECT SUM(total) FROM sales WHERE tenant_id=? AND store_id=?
  AND DATE(created_at) BETWEEN ? AND ? AND status!='canceled'

-- Продадени бройки
SELECT SUM(si.quantity) FROM sale_items si
  JOIN sales s ON s.id=si.sale_id
  WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at) BETWEEN ? AND ?

-- Среден марж
SELECT AVG((si.unit_price - COALESCE(si.cost_price,0)) / si.unit_price * 100)
  FROM sale_items si JOIN sales s ON s.id=si.sale_id
  WHERE s.tenant_id=? AND si.unit_price > 0 ...

-- Sparkline (7 дни): array от 7 числа
SELECT DATE(created_at) d, SUM(total) v FROM sales
  WHERE tenant_id=? AND store_id=? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
  GROUP BY DATE(created_at)
```

### 2.4 Тревоги row (3 cards като P15)

| Свършили | Застояли 60+ | Без снимка |
|---|---|---|
| **45 бр** | **123 бр** | **12 бр** |
| −340 €/седмица | 1,180 € замразени | -- |
| → Поръчай → | → Намали → | → Снимай → |

### 2.5 СЪСТОЯНИЕ НА СКЛАДА (Закон №11 — rebrand)

**НЕ плосък %.** Breakdown по 5 метрики:

```
СЪСТОЯНИЕ НА СКЛАДА                                82% общо
├─ 🟢 Снимки           78%  (12 без)        →
├─ 🟢 Цени едро        91%  (5 без)         →
├─ 🟡 Броено < 30 дни  34%  (165 застояли)  →
├─ ✅ Доставчик        100% (всички ОК)     ✓
└─ 🟢 Категория        88%  (7 без)         →
```

- Всеки ред tap → отваря filtered list (P3) с конкретен филтър
- Visual coding: 🟢 >75%, 🟡 50-75%, 🔴 <50%

### 2.6 AI вижда — 6 сигнала

**3 expanded (default visible):**
- Q1 — ГУБИШ (червен сигнал)
- Q2 — ПРИЧИНА (виолетов)
- Q3 — ПЕЧЕЛИШ (зелен)

**3 collapsed:**
- Q4 — ОТ КАКВО (тюркоаз)
- Q5 — ПОРЪЧАЙ (амбър)
- Q6 — НЕ ПОРЪЧВАЙ (сив)

Всеки сигнал = `.lb-card` от chat.php pattern. Има:
- Icon orb + title + сума
- Expand → body + 3 actions (Виж / Намери / Поръчай) + 3 feedback (👍 👎 ❓)
- Data attr `data-insight-id` за feedback DB save (still pending — KNOWN_BUGS #2)

**PHP query:** `compute-insights.php` (вече съществува)

---

## 3. TAB 2 — ГРАФИКИ (6 chart типа)

### 3.1 Sparklines Top 5 артикули · 30 дни
```
1. Adidas Superstar 40        ▁▂▃▆█▆▃▂▁▂▃▄▅▆▅▄  18 бр  +840 лв
2. Рокля Zara черна M         ▂▁▂▃▄▆█▇▆▄▃▂▁▂▃   11 бр  +568 лв
3. Яке Tommy Hilfiger L       ▃▄▅▆▇█▇▆▅▄▃▂▁▂▃    4 бр  +576 лв
...
```

**Why:** Визуално усещане за тренд (расте/пада/стабилно) без числа.

**PHP query:**
```sql
SELECT p.name, p.id,
       JSON_ARRAYAGG(JSON_OBJECT('d', d, 'v', v)) sparkline,
       SUM(qty) total_qty, SUM(profit) total_profit
FROM (
  SELECT p.id, p.name,
         DATE(s.created_at) d,
         SUM(si.quantity) qty,
         SUM(si.quantity * (si.unit_price - COALESCE(si.cost_price, 0))) profit,
         qty v
  FROM products p
  JOIN sale_items si ON si.product_id=p.id
  JOIN sales s ON s.id=si.sale_id
  WHERE p.tenant_id=? AND s.store_id=?
    AND s.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND s.status!='canceled'
  GROUP BY p.id, DATE(s.created_at)
) daily
GROUP BY id, name
ORDER BY total_profit DESC
LIMIT 5
```

**Render:** SVG inline sparkline (без library), всеки point = `<rect>` или `<polyline>`.

### 3.2 Парето 80/20

**Цел:** Колко % артикули правят 80% от приходите.

```
┌───────────────────────────────────────────────┐
│  Всичко                                       │
│  ████████████████████████████████████████  100% │
│                                               │
│  Топ 20%                                      │
│  ██████████████████████████████              78% │
│  Тези 47 артикула правят 78% от приходите!    │
└───────────────────────────────────────────────┘
```

**PHP query:** sort by revenue, cumsum, find до 80%.

```php
$products_sorted = DB::run(
    'SELECT p.id, p.name, SUM(si.quantity * si.unit_price) revenue
     FROM products p
     JOIN sale_items si ON si.product_id=p.id
     JOIN sales s ON s.id=si.sale_id
     WHERE p.tenant_id=? AND s.store_id=?
       AND s.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
     GROUP BY p.id
     ORDER BY revenue DESC',
    [$tenant_id, $store_id]
)->fetchAll();

$total = array_sum(array_column($products_sorted, 'revenue'));
$cumsum = 0;
$top20_count = 0;
foreach ($products_sorted as $i => $p) {
    $cumsum += $p['revenue'];
    if ($cumsum >= $total * 0.8) { $top20_count = $i + 1; break; }
}
$top20_pct = round($top20_count / count($products_sorted) * 100);
```

### 3.3 Heatmap Календар продажби · 28 дни

```
Sep  Oct
────────
П В С Ч П С Н   ← week labels (1 ред)
░ ░ █ █ ░ ░ ░    ← week 1
░ █ █ █ ▒ ░ ░    ← week 2
█ █ ▒ ▒ ░ ░ ░    ← week 3
░ ▒ █ █ ░ ░ ░    ← week 4
```

- 4 weeks × 7 days = 28 cells
- Цвят на cell зависи от бройка продажби (░ <5 / ▒ 5-15 / █ >15)

**PHP query:**
```sql
SELECT DATE(created_at) d, COUNT(*) cnt
FROM sales
WHERE tenant_id=? AND store_id=?
  AND created_at > DATE_SUB(NOW(), INTERVAL 28 DAY)
  AND status!='canceled'
GROUP BY DATE(created_at)
```

### 3.4 Марж тренд · 90 дни

Линия график — среден марж за всеки ден.

```
40% ┤      ╭─╮  ╭─╮
35% ┤    ╭─╯ ╰──╯ ╰─╮
30% ┤  ╭─╯           ╰─╮
25% ┤──╯               ╰──
    └──────────────────────
    Day 1                 90
```

**Цел:** Виж дали маржът пада → indicates обсъстие на маркъп или лоши доставки.

### 3.5 Donut: Приход по доставчик

```
        Други 12%
         ████
      ████████
    ███ A&B   ███
   ███  35%    ███
  ███          ███
   ███ Zara   ███
    ███ 28%  ███
      ████████
     Adidas 25%
```

**PHP query:**
```sql
SELECT supplier_name, SUM(si.quantity * si.unit_price) revenue
FROM products p
JOIN sale_items si ON si.product_id=p.id
JOIN sales s ON s.id=si.sale_id
WHERE p.tenant_id=? AND s.store_id=?
  AND s.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY supplier_name
ORDER BY revenue DESC
LIMIT 5
```

### 3.6 Сезонност · AI откри

Категории със явен сезонен pattern.

```
┌─────────────────────────────────────────┐
│ ☀ Лятна обувка                          │
│   Юни-Август: 78% от годишния приход    │
│   Сега: септември — bещ пика?            │
│                                         │
│ ❄ Зимни якета                           │
│   Ноември-Февруари: 92%                 │
│   Сега: септември — стартирай поръчки?  │
└─────────────────────────────────────────┘
```

**Важно:** AI казва **qualitatively** "тук има sezonen pattern", **PHP дава числата** от групировка по месец.

**НЕ AI прогноза с конкретни числа за следващ месец.** Това нарушава Закон №2.

---

## 4. TAB 3 — УПРАВЛЕНИЕ (operational)

### 4.1 Доставчици grid

Wide cards с топ доставчици + статистики.

```
┌─────────────────────────────────────┐
│ Zara                                 │
│ 142 артикула · 23 продажби/седм      │
│ Среден lead time: 5 дни              │
│ Последна поръчка: преди 12 дни       │
│ [Поръчай отново] [Виж артикули →]    │
└─────────────────────────────────────┘
```

### 4.2 Multi-store comparison (само ако >1 магазин)

Таблица:

| Магазин | Артикули | Стойност | Свършили | Застояли |
|---|---|---|---|---|
| ENI Витоша | 1,234 | 45,200€ | 23 | 89 |
| ENI Скайтия | 987 | 38,400€ | 18 | 112 |
| ENI Бургас | 1,567 | 52,100€ | 45 | 67 |

### 4.3 Saved views

User-defined филтри запазени.

```
┌─────────────────────────────────────┐
│ Моите изгледи           [+ нов]      │
├─────────────────────────────────────┤
│ 📌 Топ продавачи август              │
│ 📌 Под минимум · Бургас              │
│ 📌 Без снимки                        │
└─────────────────────────────────────┘
```

**PHP table:**
```sql
CREATE TABLE user_views (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  name VARCHAR(100),
  filters JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 4.4 Bulk actions hint

```
Избери много артикули → действия върху всичките:
[Промени цена] [Промени категория] [Премести] [Изтрий]
```

Tap → отива в P3 list с selection mode active.

---

## 5. TAB 4 — АРТИКУЛИ (quick filter chips)

### 5.1 По сигнал

```
[🔴 Свършили (45)] [⚠ Под минимум (18)] [💤 Застояли 60+ (123)]
[📈 Топ продавачи (47)] [💰 Висок марж (89)] [📷 Без снимка (12)]
```

Tap chip → отива в P3 list с filter applied.

### 5.2 ABC класификация

```
Категория A — 80% revenue (~12% от артикули) — 247 артикула  →
Категория B — 15% revenue (~28% от артикули) — 567 артикула  →
Категория C —  5% revenue (~60% от артикули) — 1,210 артикули →
```

**Цел:** Кои да защитаваш на склад. C категория — намалявай.

### 5.3 Dead stock breakdown

```
> 90 дни без продажба:   45 артикула · 2,340 €
> 180 дни:                23 артикула · 1,180 €
> 365 дни:                12 артикула ·   780 €
```

### 5.4 По доставчик

Quick chips за всеки доставчик с count.

### 5.5 По категория

Quick chips за всяка категория с count.

---

## 6. 17 ОДОБРЕНИ ИДЕИ (от Тих, 12.05.2026)

| # | Идея | В кой Tab | Статус |
|---|---|---|---|
| 1 | Period compare toggle (тази/мин/30/90) | Преглед | ✅ Описано |
| 2 | 3 KPI cards с sparklines | Преглед | ✅ |
| 3 | Тревоги row (свършили/застояли/без снимка) | Преглед | ✅ |
| 4 | СЪСТОЯНИЕ НА СКЛАДА breakdown | Преглед | ✅ Закон №11 |
| 5 | AI вижда — 6 сигнала (3 expanded + 3 collapsed) | Преглед | ✅ |
| 6 | Sparklines Top 5 артикули | Графики | ✅ |
| 7 | Парето 80/20 | Графики | ✅ |
| 8 | Heatmap календар | Графики | ✅ |
| 9 | Марж тренд линия | Графики | ✅ |
| 10 | Donut по доставчик | Графики | ✅ |
| 11 | Сезонност AI qualitative | Графики | ✅ |
| 12 | Multi-store comparison | Управление | ✅ |
| 13 | Saved views | Управление | ✅ |
| 14 | Bulk actions hint | Управление | ✅ |
| 15 | ABC класификация | Артикули | ✅ |
| 16 | Dead stock breakdown | Артикули | ✅ |
| 17 | Quick filter chips (сигнал/доставчик/категория) | Артикули | ✅ |

**Идея #15 (AI прогноза с числа за следващ месец) — ОТХВЪРЛЕНА** от Тих (нарушава Закон №2 "PHP смята, AI говори").

---

## 7. ЗАКОНИ ПРИЛОЖИМИ КЪМ DETAILED MODE

| Закон | Какво означава за detailed |
|---|---|
| **Закон №2** — PHP смята, AI говори | Всички числа от PHP queries. AI само qualitative ("има sezonен pattern"), не числа. |
| **Закон №10** — Inv nudge | Persistent pill горе на ВСЕКИ tab |
| **Закон №11** — Състояние склада breakdown | НЕ плосък % — 5 компонента |
| **Закон №12** — SWAP стратегия | Нов файл products-v2.php |
| **Закон №13** — chat.php е реалност | Inline CSS, БЕЗ design-kit/ |
| **Bible §5.2** — Simple без bottom-nav | Detailed може да има bottom-nav (опционално) |
| **Sacred** — wizard 1:1 | "Добави артикул" бутон отваря wizard (не вграждаме wizard логика) |

---

## 8. PHP QUERIES — пълен списък

### 8.1 За Tab Преглед

```php
// $tenant_id, $store_id, $period_from, $period_to (от toggle)

// Quick stats
$revenue = (float)DB::run('SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at) BETWEEN ? AND ? AND status!="canceled"',
    [$tenant_id, $store_id, $period_from, $period_to])->fetchColumn();

$sold_qty = (int)DB::run('SELECT COALESCE(SUM(si.quantity),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at) BETWEEN ? AND ? AND s.status!="canceled"',
    [$tenant_id, $store_id, $period_from, $period_to])->fetchColumn();

$avg_margin = (float)DB::run('SELECT AVG((si.unit_price - COALESCE(si.cost_price,0)) / si.unit_price * 100) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.tenant_id=? AND s.store_id=? AND si.unit_price > 0 AND s.status!="canceled"',
    [$tenant_id, $store_id])->fetchColumn();

// Sparklines (7 дни array)
$rev_spark = DB::run('SELECT DATE(created_at) d, COALESCE(SUM(total),0) v FROM sales WHERE tenant_id=? AND store_id=? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d',
    [$tenant_id, $store_id])->fetchAll();

// Тревоги (already в shell)
// $out_of_stock, $stale_60d, $total_products (вече в Step 1)

// Без снимка
$no_photo = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND (photo_url IS NULL OR photo_url = "")',
    [$tenant_id])->fetchColumn();

// Състояние склада breakdown
$photo_pct = round((1 - $no_photo / max(1, $total_products)) * 100);
$cost_set = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price > 0', [$tenant_id])->fetchColumn();
$cost_pct = round($cost_set / max(1, $total_products) * 100);
// ... etc

// AI insights (използваме existing compute-insights.php)
$insights = computeProductInsights((int)$tenant_id, (int)$store_id, $cur);
// returns array of 6 insights (q1-q6)
```

### 8.2 За Tab Графики

Виж §3.1-3.6 за конкретните queries.

### 8.3 За Tab Управление

```php
// Suppliers
$suppliers = DB::run('SELECT supplier_name, COUNT(*) cnt, SUM(...) FROM products WHERE tenant_id=? GROUP BY supplier_name', [$tenant_id])->fetchAll();

// Multi-store
$stores_compare = DB::run('SELECT store_id, COUNT(*) products_cnt, SUM(...) FROM ... GROUP BY store_id', [$tenant_id])->fetchAll();

// Saved views
$views = DB::run('SELECT * FROM user_views WHERE user_id=? ORDER BY created_at DESC', [$user_id])->fetchAll();
```

### 8.4 За Tab Артикули

```php
// Chip counts (за всеки филтър)
$signal_counts = [
    'zero_stock' => $out_of_stock,
    'stale_60d' => $stale_60d,
    'high_margin' => /* query */,
    'top_sellers' => /* query */,
    'no_photo' => $no_photo,
    'below_min' => /* query */
];

// ABC класификация
// (виж 5.2 алгоритъм)
```

---

## 9. КАКВО НЕ Е В DETAILED MODE

### 9.1 ОТХВЪРЛЕНА — AI прогноза с числа за бъдеще
"Идея #15" от 17-те — Тих отхвърли защото:
- Нарушава Закон №2 (PHP смята, AI говори)
- Изисква предсказване което AI може да сбърка
- Тих не иска fake числа

**Алтернатива:** AI открива historical patterns (sezonality) → PHP смята тренд → AI описва qualitatively.

### 9.2 Wizard логика
Wizard НЕ е в products-v2.php directly. Само бутон "Добави артикул" → `<?php include 'partials/products-wizard.php' ?>`. Sacred zone — не модифициран.

### 9.3 Voice / Color detect
Sacred — стара логика в `services/voice-tier2.php` и `ai-color-detect.php`. Достъпни през wizard.

---

## 10. КАК СЕ ИМПЛЕМЕНТИРА (Step 3 на products-v2.php)

### 10.1 Подреденост на HTML в `<main class="app">` за detailed mode

```php
<?php if (!$is_simple_view): ?>

<!-- 1. Inv nudge pill -->
<a class="inv-nudge" href="inventory.php">
  ⏳ <?= $uncounted ?> артикула не са броени · <?= $days_since_count ?> дни →
</a>

<!-- 2. Tab bar -->
<div class="tabs-bar">
  <button class="tab-btn active" data-tab="overview">Преглед</button>
  <button class="tab-btn" data-tab="charts">Графики</button>
  <button class="tab-btn" data-tab="manage">Управление</button>
  <button class="tab-btn" data-tab="items">Артикули</button>
</div>

<!-- 3. Tab Преглед -->
<section class="tab-panel active" data-tab-content="overview">
  <!-- Period toggle -->
  <!-- Quick actions -->
  <!-- 3 KPI cards -->
  <!-- Тревоги row -->
  <!-- Състояние склада -->
  <!-- AI вижда 6 сигнала -->
</section>

<!-- 4. Tab Графики -->
<section class="tab-panel" data-tab-content="charts" style="display:none">
  <!-- 6 charts -->
</section>

<!-- 5. Tab Управление -->
<section class="tab-panel" data-tab-content="manage" style="display:none">
  <!-- Suppliers + multi-store + saved views + bulk -->
</section>

<!-- 6. Tab Артикули -->
<section class="tab-panel" data-tab-content="items" style="display:none">
  <!-- Quick filter chips + ABC + dead stock + by supplier + by category -->
</section>

<?php endif; ?>
```

### 10.2 Tab switching JS

```javascript
function setTab(tabId) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tabId));
  document.querySelectorAll('.tab-panel').forEach(p => {
    p.style.display = p.dataset.tabContent === tabId ? '' : 'none';
    p.classList.toggle('active', p.dataset.tabContent === tabId);
  });
  // Sticky tab bar — scroll to top when switch
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
```

### 10.3 Charts rendering

Опции:
- **A. Inline SVG** (no external library — chat.php pattern). За sparklines, donut, bar charts. ~50 реда JS за всеки.
- **B. Chart.js CDN.** Външна dependency, но богат API. Размер: +60 KB.

**Препоръка:** A (inline SVG) за следване на chat.php standalone pattern.

Пример sparkline:
```javascript
function renderSparkline(svgId, data) {
  const svg = document.getElementById(svgId);
  if (!data.length) return;
  const max = Math.max(...data);
  const w = 200, h = 30;
  const step = w / (data.length - 1);
  const points = data.map((v, i) => `${i * step},${h - (v / max) * h}`).join(' ');
  svg.innerHTML = `<polyline points="${points}" stroke="var(--accent)" fill="none" stroke-width="2"/>`;
}
```

---

## 11. EXISTING ASSETS (от products.php, repurpose)

### 11.1 Existing AJAX endpoints (copy в products-v2.php)
- `?ajax=insights` — Life Board сигнали (compute-insights.php вече работи)
- `?ajax=storeStats` — Store stats
- `?ajax=search` — Live search артикули
- `?ajax=load_products` — Pagination за P3 list

### 11.2 New AJAX endpoints (за detailed)
- `?ajax=kpi_period` — Quick stats за period
- `?ajax=sparkline` — Sparkline data за конкретен артикул
- `?ajax=pareto` — Pareto 80/20 data
- `?ajax=heatmap` — Heatmap calendar data
- `?ajax=margin_trend` — Margin trend 90 days
- `?ajax=supplier_donut` — Donut by supplier
- `?ajax=multi_store` — Multi-store comparison
- `?ajax=saved_views` — User views CRUD
- `?ajax=abc` — ABC classification
- `?ajax=dead_stock` — Dead stock breakdown

---

## 12. PHASE-Б — какво остава за бъдеще (НЕ за S141)

- **AI прогноза qualitative** (бъдещ модул, не сега)
- **Predictive analytics** (S150+)
- **Voice-driven detailed view** (Phase D)
- **Comparison across periods** (paralleл прес години)
- **Stock optimization recommendations** (AI + business rules)

---

## 13. FINAL CHECKLIST

Преди да започне Step 3 (детайлно съдържание):

- [ ] Прочетен `mockups/P2_v2_detailed_home.html` (1853 реда — canonical)
- [ ] Прочетен този документ
- [ ] Прочетен `PRODUCTS_MASTER.md` §16 (одобрени решения)
- [ ] Step 2 (P15 simple) е готов и тестван
- [ ] AJAX endpoints в products-v2.php базови имат тест
- [ ] PHP queries за всеки KPI са verified (return non-zero за tenant_id=7)

---

**End of DETAILED_MODE_SPEC v1.0.**
