# 🌅 MORNING_REPORT_TEMPLATE.md — ШАБЛОН ЗА CLAUDE CODE 06:30

**Версия:** 1.0  
**Дата:** 08.05.2026  
**Цел:** Точна структура на `MORNING_REPORT.md` който Claude Code пише всяка сутрин. Когато Тихол отвори Шеф чата в 08:00, Шеф чатът чете готов структуриран отчет — не суров SQL output.

---

## 🎯 КОГА СЕ ПИШЕ

Cron 06:30 — след като:
- 02:00 cron е пуснал стрес симулация
- 03:00 cron е тествал нови commits
- 06:00 cron е събрал raw статистики

Claude Code 06:30 чете raw статистики и пише структуриран отчет за Шеф чата.

---

## 📋 ШАБЛОН — ПОЛЕТА КОИТО CLAUDE CODE ПОПЪЛВА

Claude Code пише `/var/www/runmystore/MORNING_REPORT.md`. Файлът се commit-ва в repo автоматично за достъпност от Шеф чата.

```markdown
# 🌅 СУТРЕШЕН ОТЧЕТ — [ДАТА]

**Време на писане:** [HH:MM]  
**Анализатор:** Claude Code (06:30 cron)

---

## 📊 ОБЩА СТАТИСТИКА (от 02:00 пробег)

- Симулирани действия: [N]
- Сценарии пуснати: [N pass / M fail / K skip]
- Изпълнение: [минути]
- AI calls (стрес симулация): [N]
- DB queries: [N]
- Slow queries (>2s): [N]

---

## 🔴 КРИТИЧНИ (P0)

Списък на fail-нали сценарии с приоритет P0:

| # | Сценарий | Причина | Бъг ID | Тенденция |
|---|---|---|---|---|
| 1 | S002 — race condition | Stock падна -1 (отрицателен) | BUG-007 | 3-та нощ подред |
| 2 | ... | | | |

**Анализ:**
- Бъг 007 е същия като преди седмица — фиксът от commit X не е работещ
- Препоръка: Шеф чат → Code 2 (модули) → S87E sale.php hardening

---

## 🟡 WARNING (P1)

| # | Сценарий | Причина | Препоръка |
|---|---|---|---|
| 1 | Slow query в stats.php | YoY заявка 3.2s | Index missing — Code 2 |
| 2 | ... | | |

---

## 🤖 AI BRAIN ЗДРАВЕ

- AI hallucinations открити: [N] от [M] AI calls (X%)
- Fact verifier rejects: [N] (когато активен)
- Confidence > 0.85 (auto): [%]
- Confidence 0.5-0.85 (confirm): [%]
- Confidence < 0.5 (block): [%]

**Топ 3 халюцинации:**
1. AI каза „имаш 5 Nike 42" но в DB има 0 — сценарий S006
2. ...

---

## 📈 ТЕНДЕНЦИИ (vs миналата седмица)

| Метрика | Тази седмица | Миналата | Тренд |
|---|---|---|---|
| Pass rate сценарии | 87% | 82% | ↗️ |
| AI hallucination rate | 3.2% | 4.1% | ↘️ (по-добре) |
| Slow queries | 8 | 12 | ↘️ |
| Race conditions | 3 | 5 | ↘️ |

---

## 🎯 ИЗПЪЛНИ ДНЕС

Препоръчителни действия на база на снощни резултати:

### Code 1 (AI Brain)
- [Задача 1] — [защо]
- [Задача 2] — [защо]

### Code 2 (модули/бъгове)
- [Задача 1] — [защо, обикновено fix на P0 бъг]
- [Задача 2] — [защо]

### Opus 4.7 (нови модули + дизайн)
- [Задача] — [защо]

### Стрес чат
- Добави нов сценарий S0XX за [feature]
- Премахни сценарий SXXX (deprecated)

### Тихол лично
- Тествай wizard на телефона — провери дали [X] е оправен
- Логически решение: [въпрос за теб]

---

## 🚨 ВЪЗМОЖНИ ESCALATIONS

- Бъг 007: 3-та нощ подред — ESCALATION (P0)
- AI hallucination в S006 — ескалация ако се появи 5-та нощ
- Slow query: над праг (>2s) — анализ нужен

---

## 📅 ИСТОРИЧЕСКИ КОНТЕКСТ (от STRESS_SCENARIOS_LOG.md)

- Сценарий S002 се появява като fail в 7 от последните 10 пробега
- Сценарий S001 е стабилен — pass 30 дни подред
- Нов сценарий S012 (поръчка от lost_demand) — добавен преди 3 дни, още няма достатъчно история

---

## ⏰ ВРЕМЕНА (за telemetry)

- 02:00 cron stresss simulation: [start time] - [end time], продължителност [X min]
- 03:00 cron new commits test: [start time] - [end time]
- 06:00 cron stats collect: [start time] - [end time]
- 06:30 cron analysis (този отчет): [start time] - [end time]

Ако някой cron не е стартирал → червен ред:

```
🚨 ⚠️ Cron 02:00 не е стартирал.
Възможни причини:
- Сървърен рестарт
- Disk full
- Database down
Действие: Тихол проверява cron status веднага.
```

---

## 📦 ARTIFACTS (файлове генерирани от тази нощ)

- `/var/log/runmystore/stress_simulator_[date].log`
- `/var/log/runmystore/morning_report_[date].md` (този файл)
- DB записи: `stress_runs.id = [X]`

---

**КРАЙ НА MORNING_REPORT — [ДАТА]**
```

---

## 🔧 ИНСТРУКЦИИ ЗА CLAUDE CODE (cron job)

Скриптът на 06:30 трябва да:

1. **Чете raw данни** от:
   - `stress_runs` DB таблица (резултати от 02:00 cron)
   - `stress_scenarios_log` DB таблица (history)
   - `error_log` DB таблица (PHP errors последните 24h)
   - `slow_queries.log` MySQL slow query log

2. **Анализира тенденции** — сравнява тази нощ с миналата седмица

3. **Идентифицира escalations** — сценарии fail-нали 3+ нощи подред

4. **Генерира препоръки** — въз основа на бъг → модул mapping

5. **Пише `/var/www/runmystore/MORNING_REPORT.md`**

6. **Commit + push** в repo:
   ```bash
   cd /var/www/runmystore
   git add MORNING_REPORT.md
   git commit -m "MORNING_REPORT: $(date +%Y-%m-%d)"
   git push origin main
   ```

7. **Telegram alert** ако има P0 escalation:
   ```bash
   curl -X POST "https://api.telegram.org/bot[TOKEN]/sendMessage" \
     -d "chat_id=[TIHOL_ID]&text=🚨 P0 escalation: [бъг]"
   ```

---

## 📜 ПРАВИЛА ЗА CLAUDE CODE СКРИПТА

- **Никога не пропускай escalation rules** — 3+ нощи подред задължително влизат в червените флагове
- **Винаги пиши на български** — отчетът е за Шеф чат + Тихол
- **Бъди специфичен** — не „има бъг", а „S002 fail-на защото race condition не е поправен"
- **Свързвай тенденции** — „AI hallucinations паднаха от 4.1% на 3.2% — фиксът от снощи работи"
- **Не дублирай** — ако сценарий S002 е main escalation, не повтаряй го в други секции

---

## 🚨 АКО CRON 06:30 FAIL-НЕ

Шеф чат се справя без MORNING_REPORT.md:
1. Чете STRESS_SCENARIOS_LOG.md ръчно (последния запис)
2. Анализира сам резултатите
3. Дава ръчни задачи
4. Tihol получава Telegram alert „06:30 cron fail"

---

**КРАЙ НА MORNING_REPORT_TEMPLATE.md v1.0**
