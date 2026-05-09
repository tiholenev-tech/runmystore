# 📋 STRESS_BOARD.md — ЦЕНТРАЛНА ДЪСКА НА СТРЕС СИСТЕМАТА

**Версия:** 2.0  
**Дата:** 08.05.2026  
**Заменя:** v1.0 от 30.04.2026  
**Принцип:** Един файл, всички чатове четат, всички пишат когато трябва

---

## 📊 ГРАФА 1 — ТЕКУЩО СЪСТОЯНИЕ

### Среди

| Tenant | Тип | Email | Магазини | Статус |
|---|---|---|---|---|
| **tenant_id=7** | РЕАЛЕН — ENI Тихолов | tiholenev@gmail.com | 5 + онлайн | Beta product, чисти се преди старт |
| **STRESS Lab** | ЛАБОРАТОРИЯ | stress@runmystore.ai | 7 + онлайн | Pending setup |

### Магазини в ENI (tenant_id=7)

1. Склад
2. Васил Левски
3. Лукс
4. Сан Стефано
5. Ростов
6. Онлайн магазин (Ecwid интеграция, post-beta)

### Магазини в STRESS Lab

1. Склад (приема, раздава, едро+дребно)
2. Магазин дрехи (fashion-only)
3. Магазин обувки (shoes-only)
4. Магазин mixed (дрехи + обувки + аксесоари)
5. Магазин high-volume (200-400 продажби/ден)
6. Магазин бижута (висок марж, малки бройки)
7. Магазин домашни потреби (голям асортимент, нисък марж)
8. Онлайн магазин (Ecwid симулация)

### Доставчици в STRESS Lab (11)

| Категория | Доставчици |
|---|---|
| Дамско бельо | Дафи, Ивон, Статера |
| Мъжко бельо | Lord, Royal Tiger, Диекс |
| Пижами (дамски + мъжки) | Петков |
| Пижами (само дамски) | Пико, Иватекс |
| Всякакъв вид | Ареал |
| Чорапи | Sonic |

### Продавачи в STRESS Lab (5)

- 4 в склада
- По 1 във всеки от 5-те физически обекта

---

## 📅 ГРАФА 2 — РЕЗУЛТАТИ ОТ СНОЩИ

*Тази секция се попълва автоматично от 06:00 cron + Claude Code 06:30*

```
[Празно — първи нощен пробег предстои]
```

Последен пробег: **никога**  
Очакван следващ: след стартиране на Етап 1 (юни 2026)

---

## 🌙 ГРАФА 3 — ЗА ТЕСТ ТАЗИ ВЕЧЕР (02:00 cron)

*Тази секция се попълва от Шеф чата при EOD протокол*

```
[Празно — попълва се от Шеф чат при затваряне на деня]
```

**Формат на инструкцията:**
- Кои сценарии от STRESS_SCENARIOS.md да се пуснат (ID-та)
- Кои нови commits от деня да се проверят
- Какви данни да се генерират (ако е Етап 3)
- Кои метрики да се запишат

---

## 🐛 ГРАФА 4 — ОТКРИТИ БЪГОВЕ ОТ СТРЕС ТЕСТОВЕ

*Растяща таблица. Обновява се от Шеф чата след преглед на MORNING_REPORT.md*

| Бъг ID | Дата | Сценарий | Описание | Приоритет | Назначен | Статус |
|---|---|---|---|---|---|---|
| [празно] | | | | | | |

---

## ✅ ГРАФА 5 — ОПРАВЕНИ БЪГОВЕ (за повторен тест)

*Шеф чатът пише тук когато Code чат каже „оправено"*

| Бъг ID | Дата фикс | Commit | Тества тази вечер? | Резултат |
|---|---|---|---|---|
| [празно] | | | | |

---

## 🎯 ГРАФА 6 — ОТВОРЕНИ ВЪПРОСИ (за решение от Тихол)

| ID | Въпрос | Дата задаване | Статус |
|---|---|---|---|
| OQ-01 | Telegram бот за status alerts — да или не? | 08.05.2026 | Pending Тихол |
| OQ-02 | Beta Acceptance Checklist — Шеф пише draft, Тихол полира? | 08.05.2026 | Pending Тихол |
| OQ-03 | „Ревизия" подмодул — концепция, post-beta развитие | 08.05.2026 | Записан, не приоритет |

**Решени въпроси (от 30.04 и 01.05):**

| ID | Въпрос | Решение | Дата |
|---|---|---|---|
| OQ-A | Tenant за стрес — изолиран или ENI+виртуални? | Нов tenant `stress@runmystore.ai`, отделен от ENI | 08.05.2026 |
| OQ-B | Cron часове | 02:00 / 03:00 / 06:00 / 06:30 | 01.05.2026 |
| OQ-C | Дни история | 90 (промяна от 60) | 08.05.2026 |
| OQ-D | Етап 2 преди Етап 1? | Да — admin отчет преди свят | 01.05.2026 |
| OQ-E | Cron-овете старт преди или след модулите? | Етап 1+2 веднага, Етап 3 чака модулите | 01.05.2026 |

---

## 🔄 ГРАФА 7 — ПРОГРЕС ПО ЕТАПИТЕ
<!-- STRESS-BOARD-AUTO:graph7:start (do not edit between these markers) -->

**Авто-генерирано** от `tools/stress/sync_board_progress.py`. Не редактирай ръчно.

| # | Етап | Статус | Evidence (файлове) | Цел |
|---|---|---|---|---|
| 1 | Подготовка на свят (STRESS Lab tenant + 7 магазина + 90 дни история) | ✅ готов | `tools/stress/setup_stress_tenant.py`, `tools/stress/seed_history_90days.py`, `tools/stress/seed_stores.py` | Юни 2026 |
| 2 | /admin/stress-board.php — admin отчет | ✅ готов | `admin/stress-board.php`, `admin/health.php` | След beta (16-22 май) |
| 3 | Нощен робот (cron 02:00 пълна симулация) | ✅ готов | `tools/stress/cron/nightly_robot.py`, `tools/stress/cron/action_simulators.py` | След модулите |
| 4 | Авто-ловец на бъгове (sanity checks) | ✅ готов | `tools/stress/cron/sanity_checker.py`, `tools/stress/cron/balance_validator.py` | След Етап 3 |
| 5 | Онлайн магазин симулатор (Ecwid orders) | ✅ готов | `tools/stress/ecwid_simulator/ecwid_simulator.py`, `tools/stress/ecwid_simulator/ecwid_to_runmystore_sync.py` | След Ecwid интеграция |

### Handoff документи

- `STRESS_HANDOFF_20260508.md`
- `STRESS_HANDOFF_20260509_extension.md`

### Последни STRESS commits (last 20)

- 35474c9 S131.STRESS.P2: beta_acceptance/README.md + BETA_ACCEPTANCE_REPORT.md
- 188dacd S131.STRESS.P1: beta_acceptance/checklist.py — 30 automated checks
- 16dcabc S131.STRESS.O5: perf/README.md + STRESS_BOARD.md refresh
- 15ee9d9 S131.STRESS.O4: scenarios S071-S075 — 5 perf сценария
- 048ffd7 S131.STRESS.O3: perf/index_advisor.py — CREATE INDEX suggestions
- d643347 S131.STRESS.O2: perf/db_query_profiler.py — slow_query_log analyzer
- 4160ed6 S131.STRESS.O1: perf/load_test.py — concurrent users vs sale.php
- 2a20429 S131.STRESS.N5: tools/stress/ci/ — GitHub Actions workflow placeholder
- 7ae7d85 S131.STRESS.N4: STRESS_BOARD.md — auto-generated ГРАФА 7
- 53e5234 S131.STRESS.N3: tools/stress/sync_board_progress.py — auto STRESS_BOARD.md ГРАФА 7
- 94ccf99 S131.STRESS.N1: tools/stress/sync_registries.py — auto STRESS_SCENARIOS.md
- e44293b S131.STRESS.M4: alerts/README.md — setup + integration patches
- 55f2994 S131.STRESS.M3: alerts/test_telegram.py — dry-run smoke test
- 852e005 S131.STRESS.M2: alerts/cron_hooks.py — wrapper helpers за integration
- 6dbe522 S131.STRESS.M1: alerts/telegram_bot.py — central Telegram alerter
- f80332f S131.STRESS.L5: ecwid_simulator/README.md — usage + distribution
- c5b3888 S131.STRESS.L4: scenarios S061-S070 — 10 online sale flow сценария
- 259849e S131.STRESS.L3: ecwid_to_runmystore_sync.py — spool to sales/inventory_events
- 7ca888e S131.STRESS.L2: ecwid_simulator.py — fake online order generator
- 6d21ccc S131.STRESS.L1: ecwid_simulator/__init__.py — package marker

<!-- STRESS-BOARD-AUTO:graph7:end -->


## 📝 ИНСТРУКЦИЯ ЗА ВСЕКИ ЧАТ ПРИ СТАРТ

При отваряне на нов чат — копирай и прочети целия `STRESS_BOARD.md`:

1. **Чата чете дъската** — Закон №1
2. **Чатът потвърждава какво вижда** — текущо състояние, отворени въпроси, бъгове
3. **Чатът чака команда от Тихол** — не започва сам

При затваряне на деня:
- Шеф чат изпълнява **EOD протокол v3.0** (виж `END_OF_DAY_PROTOCOL.md`)
- Стъпка 4 от EOD пише в `STRESS_BOARD.md` секция „За тест тази вечер"

---

**КРАЙ НА STRESS_BOARD.md v2.0**