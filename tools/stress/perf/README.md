# tools/stress/perf/

**Phase O (S130 extension) — Performance harness.**

3 инструмента за измерване и анализ на производителност на STRESS Lab tenant + 5 perf сценария (S071-S075).

---

## 📂 Файлове

| Файл | Роля |
|---|---|
| `load_test.py` | Concurrent load test срещу sale.php (5-50 workers). Записва p50/p95/p99 + error rate + сравнение с baseline. |
| `db_query_profiler.py` | Парсва MySQL slow_query_log. Групира заявки по нормализиран pattern. Top 20 + recommendations. |
| `index_advisor.py` | От top slow queries генерира `suggested_indexes.sql` с CREATE INDEX statements. **НЕ apply-ва.** |
| `last_baseline.json` | Записан baseline (от `--baseline` flag на load_test.py). Auto-loaded за сравнение. |
| `suggested_indexes.sql` | Auto-generated SQL — не е committed (gitignore-ed). |
| `__init__.py` | Маркер. |
| `README.md` | Този файл. |

---

## 🚦 Поведение по подразбиране

- **`load_test.py --apply`** — реално прави HTTP заявки. Default = dry-run (печата план).
- **`db_query_profiler.py`** — read-only анализ; никога не пипа DB.
- **`index_advisor.py`** — генерира SQL файл; НЕ изпълнява statement-ите.
- **Random seed** = 42.

---

## 🛠 Workflow

### 1. Load test срещу sale.php

```bash
# Dry-run (печата план без HTTP)
python3 tools/stress/perf/load_test.py

# Apply: 100 заявки, 10 concurrent
python3 tools/stress/perf/load_test.py --apply --concurrent 10 --requests 100

# Запиши текущия резултат като baseline
python3 tools/stress/perf/load_test.py --apply --concurrent 10 --requests 100 --baseline

# Stress test: 50 concurrent
python3 tools/stress/perf/load_test.py --apply --concurrent 50 --requests 500
```

Output:
```json
{
  "url": "https://stress-lab.runmystore.ai/sale.php",
  "concurrent": 10,
  "total_requests": 100,
  "duration_s": 12.34,
  "rps": 8.10,
  "p50_ms": 145.2,
  "p95_ms": 412.8,
  "p99_ms": 678.1,
  "errors_rate_pct": 0.0,
  "5xx_count": 0
}
```

Регресия се флагва ако:
- p50/p95/p99 > **20%** baseline
- errors_rate_pct > baseline + 1.0% или > 1.5x
- rps < **0.95x** baseline

Exit code = 1 при regression.

### 2. Slow query анализ

```bash
# Парсни slow log -> JSON отчет
python3 tools/stress/perf/db_query_profiler.py /var/log/mysql/slow.log \
    --top 20 --output /tmp/slow_report.json

# От stdin (за pipe от ssh / log shipper)
ssh prod 'tail -1000 /var/log/mysql/slow.log' | \
    python3 tools/stress/perf/db_query_profiler.py --stdin
```

Output:
```
=== TOP 20 SLOW QUERIES (ranked by count*avg_time) ===

#1  count=145  avg=2.3s  max=8.1s  rows_examined=12,000,000
    select * from products where tenant_id = ? order by name limit ?

=== RECOMMENDATIONS ===

  ⚠ select * from products … — възможен липсващ index
  📋 SELECT * — изброй колоните
```

### 3. Index suggestions

```bash
# От report.json (рекомендуем)
python3 tools/stress/perf/index_advisor.py --report /tmp/slow_report.json

# Директно от slow log (one-shot)
python3 tools/stress/perf/index_advisor.py --slow-log /var/log/mysql/slow.log

# Custom output path
python3 tools/stress/perf/index_advisor.py --report /tmp/slow_report.json \
    --output /tmp/my_indexes.sql
```

Output: `tools/stress/perf/suggested_indexes.sql` с готови CREATE INDEX statements + `-- Reason:` коментари.

**ВАЖНО:** прегледай ръчно преди apply. `index_advisor` използва regex-base извличане, не AST — възможни false positives.

---

## 📊 Heuristics

### load_test регресия thresholds

| Метрика | Regression условие |
|---|---|
| `p50_ms`, `p95_ms`, `p99_ms` | current > baseline × 1.20 |
| `errors_rate_pct` | current > baseline + 1.0% или > 1.5× |
| `rps` | current < baseline × 0.95 |

### db_query_profiler ranking

```
rank_score = count × avg_time_s
```

Top заявки по rank_score = най-голямото общо натоварване (не само най-бавната).

### index_advisor филтри

- Не предлага индекс на `id`, `created_at`, `updated_at` (noise колони).
- Композитни индекси за WHERE+ORDER BY combinations (max 3 cols).
- Dedup по `table::columns` ключ.

---

## 🔗 Свързани сценарии

- **S071** — 10 concurrent sales (load_test smoke)
- **S072** — slow query > 5s (db_query_profiler trigger)
- **S073** — missing index (index_advisor follow-up)
- **S074** — lock contention (concurrency stress)
- **S075** — connection pool exhaustion (50 concurrent)

---

## ⚠️ Известни ограничения

1. **load_test.py** — sale.php payload-ът е symbolic. Реалният sale.php може да изисква CSRF token + session cookie. Тествай първо в STRESS Lab с auth disabled или с pre-stored session.

2. **db_query_profiler** — regex-base SQL парсинг. Заявки с inline comments / nested subqueries може да се normalize-ват грешно.

3. **index_advisor** — не проверява дали индексът ВЕЧЕ съществува. Прегледай `SHOW INDEX FROM <table>` преди apply.

4. **Baseline drift** — `last_baseline.json` се обновява само с `--baseline` флаг. Не забравяй да го refresh-неш след голям code change който подобрява performance, иначе ще флагваш false regressions.

5. **STRESS Lab dependency** — `load_test.py` НЕ удря production. URL-ът е `stress-lab.runmystore.ai` по default. Ако endpoint-ът не съществува, заявките ще fail-нат с DNS error и това ще се отрази в errors_rate_pct.

---

## 🛡 Iron Law

- **load_test.py никога не удря production.** Default URL е `stress-lab.runmystore.ai`. Override via `--base-url` изисква expicit заявка.
- **db_query_profiler.py read-only.** Никога не модифицира лог-овете или DB.
- **index_advisor.py пише само SQL файл.** Никога не EXECUTE-ва CREATE INDEX автоматично.
- **--concurrent максимум 50** — по-високи стойности са блокирани с REFUSE.
