# 🧭 DETAILED_MODE_DECISION.md

**Решение взе:** Тихол · 08.05.2026
**Контекст:** Шеф-чат сесия · обсъждане има ли смисъл от detailed mode за beta launch
**Свързани файлове:** `P10_lesny_mode.html` (v3 с weather), `P11_detailed_mode.html` (нов)

---

## 0. РЕЗЮМЕ

Detailed mode (`chat.php`) **се запазва** за beta launch, но е **СВЕДЕН до 2 разлики** спрямо lesny mode:

| # | Промяна | Lesny | Detailed |
|---|---|---|---|
| 1 | **Bottom nav** (4 tabs: AI / Склад / Справки / Продажба) | ❌ няма | ✅ долу |
| 2 | **4 ops buttons** (Продай / Стоката / Доставка / Поръчка) ГОРЕ | ✅ горе | ❌ махнати (дублират bottom nav) |
| 3 | **Filter pills** на Life Board (по модули) | ❌ няма | ✅ |
| 4 | **Брой сигнали** в Life Board | 5 | **12+** (по 1+ на модул) |

**Всичко друго е същото** — header, mode toggle, top row, AI Studio, Weather Forecast, AI Help, chat input bar.

---

## 1. ЗАЩО detailed mode СЕ ЗАПАЗВА (а не се премахва)

**Аргумент 1: Bible §1.3 + §11.8 + §22.3** дефинират detailed mode като ясна product feature. Премахването = голяма Bible промяна за 7 дни до beta.

**Аргумент 2: Manager persona** (от memory тагове) очаква detailed-style таблица със справки. Бета може да има 1+ Manager user в ENI (Митко) → frustration ако махнем.

**Аргумент 3: Reports tab** (4-ти от bottom nav) е единственото място където power users могат да гледат таблици/charts/exports. Lesny mode няма достъп до Reports без bottom nav.

**Аргумент 4: 80% reuse** — почти 0 dev effort за запазване. Премахване би изисквало refactor на /chat.php (1642 реда production).

---

## 2. ЗАЩО detailed mode БЕ ОПРОСТЕН

**Тихолова логика (директна цитата):**

> "единствената разлика с разширение режим е да е че отдолу излиза bottom nav. Друго не виждам никакъв смисъл да има разлика. Може Всъщност да ги няма четирите бутони защото те се дублират с долното bottom меню."

**Преводът на това в product решение:**

1. **4 ops buttons се махат от detailed** — bottom nav вече има Склад / Справки / Продажба, които точно дублират 3 от 4-те ops. (4-тият — "Поръчка" — се достъпва от Life Board сигнал "Поръчай" или от Склад → Поръчки tab.)

2. **Detailed = повече сигнали** — освободеното пространство (от ops grid removal) се използва за **разширен Life Board с 12+ сигнала** (по 1+ на модул).

3. **Filter pills** (Всички / Финанси / Продажби / Склад / Поръчки / Доставки / Трансфери / Клиенти) — за да може Митко/Owner да филтрира бързо без да скролва през всички 12+.

---

## 3. КАРТА НА СИГНАЛИТЕ ПО МОДУЛ

Beta launch (текущи модули):

| Модул | Сигнал пример (ENI demo) | Hue | Q-категория |
|---|---|---|---|
| **Финанси** | "Cash flow негативен — −820 € последни 7 дни" | q1 (red) | Какво губиш |
| **Финанси** | "Бельо под себестойност — 12 артикула, −68 €" | q1 | Какво губиш |
| **Финанси** | "ДДС период — остават 6 дни до 25-ти" | q1 | Какво губиш |
| **Продажби** | "Passionata +35% топ печалба тази седмица" | q3 (green) | Какво печелиш |
| **Продажби** | "Петък 15:00 — пик. Сложи 2-ри продавач" | q5 (amber) | От какво печелиш |
| **Склад** | "Nike Air Max 42 — 60 дни, 180 € замразени" | q1 | Какво губиш |
| **Склад** | "Точност 94% — почти готово за AI Marketing" | q3 | От какво печелиш |
| **Поръчки** | "Tommy Jeans 32 — под минимум, поръчай 12 бр" | q5 | Поръчай |
| **Поръчки** | "Nike размери 38, 39, 40 свършват тази седмица" | q5 | Поръчай |
| **Доставки** | "Иватекс забавя — 4 дни средно повече" | q2 (violet) | От какво губиш |
| **Трансфери** | "Магазин 3 има 8 дамски летни рокли излишни" | q3 | От какво печелиш |
| **Клиенти** | "8 нови повторни клиенти този месец" | q3 | Какво печелиш |

**12 сигнала · 7 модула · 4 hue класа.**

---

## 4. БЪДЕЩИ МОДУЛИ — РАЗШИРЕНИЕ

При активиране на нови модули се добавят още filter pills + сигнали:

| Модул | Период активиране | Пример сигнал |
|---|---|---|
| **Лоялност** (LOYALTY_BIBLE) | Beta + 30 дни | "Мария — 4-та покупка, прати SMS промо" |
| **Online store** (Ecwid) | Phase B (S90+) | "12 поръчки от сайта чакат потвърждение" |
| **Marketing AI** | Q4 2026 (Marketing Bible) | "Кампания 'Лятна разпродажба' — ROI 4.2x" |
| **Wholesale** (B2B) | Phase D | "Бизнес клиент 'Decathlon' — забавя 18 дни" |
| **HR / Смени** | Phase 5 | "Иван — 3-та смяна без продажби, провери" |
| **Финанси разширени** (счетоводство) | Phase 8 | "ДДС месечен — 1 240 €, готов за подаване" |
| **Аналитика** | Phase 9 | "Категория 'обувки' — спадане за 3 месеца" |

При всяко добавяне на модул → 1+ нов signal type + 1 нов filter pill в detailed mode.

---

## 5. ИМПЛЕМЕНТАЦИОННИ ПРОМЕНИ ЗА CLAUDE CODE

### 5.1 Файлови промени

```
UPDATED:
  /var/www/runmystore/chat.php (1642 реда → ~600 реда след rewrite)
    - Премахни render-а на 4 ops buttons (само в lesny остават)
    - Добави .fp-row + filter pills HTML
    - Зареждай 12+ insights вместо 5 (премахни slice към 5)
    - Запази bottom-nav include (вече е там)

  /var/www/runmystore/life-board.php
    - Добави Weather Forecast Card section (between AI Studio row и AI Help)
    - Hard-coded 14-day weather data + AI rec generator (PHP function)
    - JS toggle range 3/7/14

  /var/www/runmystore/partials/header.php
    - Без промяна — toggle-ът "Подробен/Опростен" вече ползва ui_mode

NEW:
  /var/www/runmystore/cron/weather-recs-generator.php
    - Cron веднъж дневно (06:00)
    - INPUT: weather forecast (open-meteo) + tenant business type + top inventory categories
    - OUTPUT: 3 препоръки (window/order/transfer) → INSERT INTO weather_recs
    - Запазва се 1 ред на ден на tenant

  /var/www/runmystore/db/migrations/20260509_001_weather_recs.sql:
    CREATE TABLE weather_recs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      tenant_id INT NOT NULL,
      forecast_date DATE NOT NULL,
      window_text TEXT,
      order_text TEXT,
      transfer_text TEXT,
      generated_at DATETIME,
      KEY (tenant_id, forecast_date)
    );
```

### 5.2 i18n keys (нови, ~25 ключа)

Weather card:
- T_WEATHER_FORECAST, T_AI_RECS_FOR_WEEK, T_3_DAYS, T_7_DAYS, T_14_DAYS
- T_TODAY_SHORT, T_DAY_FRI/SAT/SUN/MON/TUE/WED/THU
- T_AI_RECS, T_REC_WINDOW, T_REC_ORDER, T_REC_TRANSFER, T_UPDATED_TIME

Detailed filter pills:
- T_FP_ALL, T_FP_FINANCE, T_FP_SALES, T_FP_INVENTORY
- T_FP_ORDERS, T_FP_DELIVERIES, T_FP_TRANSFERS, T_FP_CUSTOMERS

### 5.3 PHP changes за filter pills

```php
// chat.php (detailed mode)
$active_module = $_GET['module'] ?? 'all';

$insights_query = "SELECT * FROM ai_insights WHERE tenant_id=? AND status='active'";
if ($active_module !== 'all') {
    $insights_query .= " AND module=?";
    $params[] = $active_module;
}
$insights_query .= " ORDER BY priority DESC LIMIT 20";  // 12+ for detailed
```

### 5.4 ai_insights table промяна

Ако още няма `module` колона:
```sql
ALTER TABLE ai_insights 
  ADD COLUMN module ENUM('finance','sales','inventory','orders','deliveries','transfers','customers','loyalty','marketing','wholesale','hr') DEFAULT 'sales';
```

При генериране на сигнал → попълни `module` от template.

---

## 6. РИСКОВЕ + MITIGATION

| Риск | Severity | Mitigation |
|---|---|---|
| Beta потребителите не ползват detailed mode | Низък | Tracking чрез `ui_mode` field. Ако след 30 дни <5% са detailed → премахваме post-beta. |
| Filter pills претрупват UI на телефон | Среден | Horizontal scroll + ясни labels. Pills скриват icon-а на 320px екран. |
| Бавен Life Board load (12+ insights) | Среден | LIMIT 20 + lazy expand body. Backend cache (Redis) за 5 мин. |
| Weather AI recs хлуцират | Висок | Hardcoded prompt template + GPT-4o-mini fallback. Sample-ове ревюирани от Тихол преди auto-deploy. |
| Open-Meteo rate limit | Низък | 10K calls/day free tier. Бета 1 tenant × 1 location × 1 call/day = 1 call. Достатъчно за 9999 tenants. |

---

## 7. METRICS ЗА POST-BETA РЕВЮ

След 30 дни от launch проследяваме:

```sql
-- Колко % time spent в detailed mode
SELECT 
    SUM(CASE WHEN ui_mode='detailed' THEN duration ELSE 0 END) / SUM(duration) AS pct_detailed
FROM session_logs
WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Кои filter pills се ползват най-много
SELECT module, COUNT(*) 
FROM signal_filter_clicks
GROUP BY module
ORDER BY 2 DESC;

-- Switching честота между режимите
SELECT user_id, COUNT(*) AS toggles
FROM ui_mode_changes
GROUP BY user_id
HAVING toggles > 5;
```

**Решение след 30 дни:**
- Pct detailed > 30% + active toggling → запази и развивай
- Pct detailed < 10% → премахни в Phase B
- Pct detailed 10-30% → анализ + маркетингов research

---

## 8. ЗАКЛЮЧЕНИЕ

Detailed mode е **минимален delta над lesny** — 80% reuse, 20% delta:

```diff
  - Header (canonical)
  - Mode toggle
  - Top row (Днес + Времето)
- + Ops grid (4 buttons + info)        ← LESNY ONLY
  - AI Studio row
  - Weather Forecast Card (3/7/14 дни + 3 AI recs)
  - AI Help card
  - Life Board header
+ + Filter pills (модули)              ← DETAILED ONLY
  - Life Board cards (5 lesny / 12+ detailed)
  - Chat input bar
+ + Bottom nav (4 tabs)                 ← DETAILED ONLY
```

При beta launch — **двата режима работят паралелно**, потребителят избира с tap. Метриките след 30 дни решават съдбата на detailed mode.

---

**КРАЙ.**

*Този документ е обвързващ за Claude Code сесията при rewrite на chat.php + life-board.php.*
