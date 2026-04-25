# Claude Code Orchestration — TRIGGER 6 (manual milestone batch)

## Кога се пуска

- Тихол отваря Claude Code и пише: `AI DIAG ПУСНИ`
- Или след 5+ AI commit-а от последния recorded run
- Или при съмнение за регресия

## Стъпки за Claude Code

1. **Read latest state:**
   ```bash
   cd /var/www/runmystore
   git log --oneline | head -5
   ```

2. **Check last diagnostic run:**
   ```bash
   mysql --defaults-extra-file=<(printf "[client]\nuser=$(grep DB_USER /etc/runmystore/db.env|cut -d= -f2)\npassword=$(grep DB_PASS /etc/runmystore/db.env|cut -d= -f2)\n") \
     -e "SELECT id, run_timestamp, trigger_type, module_name, passed, failed, category_a_pass_rate, category_d_pass_rate FROM diagnostic_log ORDER BY id DESC LIMIT 5;" runmystore
   ```

3. **Detect new pf*() functions without oracle entries:**
   ```bash
   python3 tools/diagnostic/core/gap_detector.py
   ```

   - Ако output != 0 gaps → **ПИТАЙ Тихол**:
     "Намерих X нови функции без oracle сценарии: [list]. Да ги добавя ли в seed_oracle преди run?"
   - Чакай ОК преди да продължиш.

4. **Run diagnostic:**
   ```bash
   python3 tools/diagnostic/run_diag.py \
     --module=insights \
     --trigger=milestone \
     --pristine \
     --orchestrated
   ```

   `--orchestrated` дава structured JSON output което Claude Code parsва лесно.

5. **Format Bulgarian report за Тихол:**

   ```
   📊 ДИАГНОСТИКА — [ДАТА], [ВРЕМЕ]

   Категория A (критични):  [%] ([passed]/[total]) [✅ или ❌]
   Категория D (граници):   [%] ([passed]/[total]) [✅ или ❌]
   Категория B (важни):     [%] ([passed]/[total])
   Категория C (декорация): [%] ([passed]/[total])

   Промени от последния run ([дата]):
   - [function]: [%] → [%] [↑/↓]
   - Нови pf*() функции: [count] (всички с oracle)

   ⚠️ Failures (ако има):
   - [scenario_code]: [reason]

   Подробности: https://runmystore.ai/admin/diagnostics.php
   ```

6. **Flag regression candidates:**

   Ако Category A < 100% или Category D < 100%:
   - Compare with last `git_commit_sha` от `diagnostic_log`
   - List commits between → suggest rollback кандидат
   - **Не commit-вай rollback автоматично** — само предложи

## Phase-aware behavior

| Phase | Mandatory triggers in Phase | TRIGGER 6 (manual) |
|---|---|---|
| **A — Foundation** | 1 (new module), 4 (manual), 5 (suspicion) | ✅ active |
| **B-D — Stabilization** | 1, 4, 5, 6 | ✅ active |
| **E — Beta + клиенти** | 1, 2 (weekly), 3 (monthly), 4, 5, 6 | ✅ active |
| **F — Production** | All + alert thresholds | ✅ active |

**Текуща Phase:** A. Cron остава ON (Тихол потвърди 25.04.2026) — TRIGGER 2/3 паралелно с TRIGGER 6.

## Safety guards (никога не нарушавай)

- **Никога tenant=47** (ЕНИ production) — `assert_safe_tenant()` в `db_helpers.py`
- **Никога `git reset --hard` без одобрение от Тихол**
- **Никога автоматичен rollback** — само предложи
- **Никога не игнорирай Cat A/D fail** — flag и спирай

## Връзки

- Главен протокол: `DIAGNOSTIC_PROTOCOL.md` (root) v1.0+
- Live tracker: `MASTER_COMPASS.md`
- Last completed milestone: see `diagnostic_log` table
