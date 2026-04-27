# 🕒 DAILY_RHYTHM_PROTOCOL — 3-фазен дневен ритъм

**Версия:** v1.0  
**Активиран:** 27.04.2026 (S87.DAILY_RHYTHM)  
**Принцип:** **1 шеф-чат носи целия ден.** Тихол не сменя шеф-чатове между фазите. Це́лия контекст е в един разговор + `daily_logs/DAILY_LOG_YYYY-MM-DD.md`.

---

## 1. Цел и философия

Ad-hoc решения изтощават Тихол и създават drift. Преди да започнеш ден, трябва да знаеш:

- какво ще се build-ва (Code-овете),
- какво ще се test-ва (resultati от build-а),
- какво ще се fix-ва (P0/P1/P2 от теста).

3 phase / day, fixed time slots, един шеф-чат, append-only daily log.
Сравнено с предишния pattern (отвaряш нов чат на всяка задача): **drift намалява ~80%**, защото шеф-чатът помни какво се build-на сутринта когато bug-ът се появи вечерта.

**Trigger думи (case-insensitive):**

- `СЕСИЯ 1` / `СЕСИЯ 2` / `СЕСИЯ 3` — Тихол стартира фаза.
- `КРАЙ НА СЕСИЯ X` — фаза приключила, шеф-чат прави summary в daily log.
- `КРАЙ НА ДЕНЯ` — шеф-чат прави EOD wrap-up + STATE/COMPASS update + git commit.

**Времеви slot-ове (preferred, не задължителни):**

| Phase | Slot | Cognitive load | Output |
|---|---|---|---|
| SESSION 1 BUILD | 08:00–12:00 | High focus, fresh | Up to 3 паралелни Claude Code commits |
| SESSION 2 TEST | 13:00–17:00 | Medium, post-lunch | Bug list (P0/P1/P2) |
| SESSION 3 FIX  | 18:00–21:00 | Lower, end-of-day | P0/P1 fixes; P2 deferred to tomorrow |

Извън slot-ове: Тихол може да каже „СЕСИЯ 1" в 14:00 — шеф-чатът не отказва, само забелязва в log-а.

---

## 2. SESSION 1 — BUILD (08:00–12:00)

### 2.1 Boot flow

Когато Тихол отваря шеф-чат-а сутринта (или каже „СЕСИЯ 1"):

1. Шеф-чат прочита (per `SHEF_BOOT_INSTRUCTIONS.md` Phase 1):
   - `STATE_OF_THE_PROJECT.md` (ground truth)
   - `MASTER_COMPASS.md` (logic change log + dependency tree)
   - последен `daily_logs/DAILY_LOG_YYYY-MM-DD.md` (ако ден е продължение)
   - `tools/testing_loop/latest.json` (boot health gate)
   - последен `docs/SESSION_S*_HANDOFF.md`

2. Status report (per Phase 2 от SHEF_BOOT_INSTRUCTIONS.md) — задължително включва:
   - Phase, % completion
   - Последна завършена сесия + commit
   - **TESTING LOOP status** (от `latest.json`) — ако 🔴 critical → **fix loop first** преди build
   - Active code сесии (брой)

3. Ако `daily_logs/DAILY_LOG_YYYY-MM-DD.md` НЕ съществува → шеф-чат го създава от `daily_logs/DAILY_LOG_TEMPLATE.md`, попълва header (Phase, ENI countdown, TESTING_LOOP status), маркира `## SESSION 1 BUILD` `Started: HH:MM`.

### 2.2 Plan generation

Шеф-чат пита Тихол **maximum 2 въпроса** (време/енергия/блокери) и дава план:

> Тихол: „ДАЙ ПЛАН"  
> Шеф: „**Време?** 4h до обяд. **Енергия?** 1–10. **Блокери?** Има ли нещо което се чупи."

После предлага:

> „Code #1 правят X (~2h). Code #2 правят Y (~3h, блокиран от X stage 3). Тихол правиш Z (UX work products.php)."

### 2.3 3 startup prompts

Шеф-чат генерира 3 ready-to-paste startup prompts (по един за всеки Code), всеки следва STARTUP_PROMPT.md format:

```
ROLE: Code #N
SESSION: S87.X.Y
TASK: [конкретно]
ТВОИ ФАЙЛОВЕ: [explicit list]
NE PIPAS: [explicit forbidden list]
DOD: [verifiable]
GIT: [commit message + push]
БЕЗ ВЪПРОСИ. ИЗПЪЛНЯВАЙ.
```

### 2.4 Handoff collection (12:00 close)

Когато Тихол каже „КРАЙ НА СЕСИЯ 1":

- Шеф-чат пита всеки Code за commit hash + 1-line summary.
- Запис в `## SESSION 1 BUILD` секция на daily log:
  - `Closed: HH:MM`
  - `### Code assignments:` (Code #N → task → commit hash)
  - `### Commits:` (списък с full hashes от `git log --since="08:00"` или manual)
  - `### Handoffs:` (1-line summaries)
- Шеф-чат **не** прави STATE update сега — отлага за EOD.

---

## 3. SESSION 2 — TEST (13:00–17:00)

### 3.1 Test brief generation

Когато Тихол каже „СЕСИЯ 2":

- Шеф-чат чете S1 commits от daily log.
- За всеки commit генерира test scenarios от:
  - `docs/SALE_REWRITE_PLAN.md §6` (ако sale.php touch)
  - `MASTER_COMPASS.md` per-module RULES
  - `tools/diagnostic/modules/<module>/` (ако Cat A scenarios съществуват)
- Test brief format:
  ```
  ## Test Brief — S87.X.Y (commit abc1234)
  - [ ] Scenario 1: barcode happy path → expect ...
  - [ ] Scenario 2: race condition → expect ...
  ```
- Тихол копира това в браузер/телефон, изпълнява, връща се с резултати.

### 3.2 Bug logging (live)

Тихол пише „bug: barcode 999 не показва toast" → шеф-чат добавя в `## SESSION 2 TEST` секцията:

```
### Bugs found:
- 🔴 P0  | barcode unknown → no toast | sale.php line 2012 | reproduces 100%
- 🟡 P1  | discount chip 25% prevent overflow | sale.php line 1635 | edge
- 🟢 P2  | wholesale toggle — flicker on Slow 3G | cosmetic
```

Severity rubric:

- 🔴 **P0** — данни губят, payment блок, security, beta blocker.
- 🟡 **P1** — flow счупен но workaround съществува; UX силно дегрейд.
- 🟢 **P2** — cosmetic, performance < 100ms, edge cases <1% users.

### 3.3 Diagnostic harness run

Ако S1 touch-на AI logic (compute-insights.php, селекция, pf*()) → Rule #21 кара шеф-чатът да напомни:

> „S1 touch-на AI logic. Преди КРАЙ НА СЕСИЯ 2 пусни `tools/diagnostic/run_diag.py --module=insights --pristine`. Очаквам Cat A=100%/D=100%."

Резултатът се записва като bug ако не 100%.

### 3.4 Closing (17:00)

„КРАЙ НА СЕСИЯ 2" → шеф-чат:

- `Closed: HH:MM`
- Total bugs by severity (`P0: N | P1: M | P2: K`)
- Препоръка за S3 routing (виж §4).

---

## 4. SESSION 3 — FIX (18:00–21:00)

### 4.1 Categorization & routing

Шеф-чат разпределя bugs по 3 правила:

1. **P0 first.** Винаги. Ако P0 ≥ 1 → S3 фокусира само на P0; P1/P2 deferred.
2. **Disjoint paths.** Никога 2 Code-ове на същия файл (FILE LOCK rule, S81 lesson).
3. **Match severity to focus level.** Late-day cognitive load по-нисък → дава P1 cosmetic-и на Code #2 (по-leniency permitted), P0 critical-ите на Code #1 (high focus).

Пример (live от 27.04.2026):

```
P0 list (2):
  1. sale.php INSERT INTO sales (total_amount → total)  → Code #1
  2. inventory race condition (no FOR UPDATE)           → Code #1
P1 list (3):
  3. payment_method='transfer' → 'bank_transfer'        → Code #2
  4. camera fallback toast missing                      → Code #2
  5. wholesale price refetch noop                       → Code #2 (chained after #4)
P2 deferred to tomorrow:
  6. cart render perf 50+ items
  7. theme toggle micro-flicker
```

### 4.2 Fix prompts

Per bug, шеф-чат генерира fix prompt (similar to S1 startup but bug-specific):

```
ROLE: Code #1
TASK: P0 BUG #1 — sale.php sales.total_amount → total schema fix
LOCATION: sale.php line 94–96
EXPECTED: INSERT executes, no "Unknown column" error
TEST: tenant=99 smoke → verify row in sales table
GIT: commit "S87.SALE.FIX P0#1: schema column rename"
```

### 4.3 Fix logging

```
### Bug fixes:
- ✅ P0#1 fixed (commit abc1234) → verified tenant=99 smoke pass
- ✅ P0#2 fixed (commit def5678) → DB::tx + FOR UPDATE wrapped
- ❌ P1#3 incomplete — schema OK но JS still sends 'transfer'
- ⏸ P1#4 deferred — Capacitor camera fallback needs Тихол on-device test
```

### 4.4 Closing (21:00)

„КРАЙ НА СЕСИЯ 3":

- `Closed: HH:MM`
- All P0 must be `✅` или explicitly escalated to next day with reason.
- `### Deferred:` (списък с reason).

---

## 5. END-OF-DAY (21:00 или „КРАЙ НА ДЕНЯ")

### 5.1 STATE update

Шеф-чат предлага diff към `STATE_OF_THE_PROJECT.md`:

- ✅ КОЕ РАБОТИ — добави нови working modules.
- ⚠️ KNOWN ISSUES — добави P0/P1 escalated, маркирай `RESOLVED` за fixed.
- 🚧 КОЕ В ПРОЦЕС — update текущ session ID.

Тихол approve → шеф-чат пише.  
**Rule #22 reminder:** Само шеф-чат update-ва COMPASS — работните Code не пишат там.

### 5.2 COMPASS update

Ако днес имало `LOGIC CHANGE` (нов pattern, нов module, нов rule, нов rework decision) → шеф-чат пише `## YYYY-MM-DD — <change>` най-горе в LOGIC CHANGE LOG.

### 5.3 Daily log finalize

`## END OF DAY` секция в daily log:

```
Sessions:    3 | (или 1, 2 ако ден приключил рано)
Commits:     N (от `git log --since="midnight" --oneline | wc -l`)
P0 open:     K (escalated)
STATE updates: ✅
COMPASS updates: ✅ (entry hash) или N/A
Tomorrow priority: [next plan]
```

### 5.4 Git commit

```bash
git -C /var/www/runmystore add \
  daily_logs/DAILY_LOG_YYYY-MM-DD.md \
  STATE_OF_THE_PROJECT.md
# (+ MASTER_COMPASS.md ако touched)

git commit --only \
  daily_logs/DAILY_LOG_YYYY-MM-DD.md \
  STATE_OF_THE_PROJECT.md \
  -m "EOD YYYY-MM-DD: N sessions, M commits, K P0 open"

git push origin main
```

`--only` за mirror cron race protection (incident e5c2929).

---

## 6. Failure modes

| Symptom | Cause | Recovery |
|---|---|---|
| Тихол пропуска цял ден (off-day, болест) | Real life | Шеф-чат създава празен daily log, маркира `## END OF DAY — DAY SKIPPED (reason: ...)`. Tomorrow priority = вчерашната. |
| Context overflow в шеф-чат-а | Дълъг ден, много handoffs | Шеф-чатът прави `/compact` (Claude UI feature). Преди compact: paste daily log в нов tab като резервно копие. |
| Шеф-чат crash / connection lost | Network | Тихол отваря нов чат, paste-ва SHEF_BOOT_INSTRUCTIONS.md prompt. Шеф-чатът чете последния daily log, продължава от там. |
| TESTING_LOOP 🔴 на boot | Cron failed / DB issue | S1 НЕ започва build. P0 = fix loop. Виж `tools/testing_loop/ANOMALY_LOG.md`. |
| Code #1 + Code #2 collision (file lock violated) | Лошо planning | Шеф-чат рестартира S1 plan с disjoint paths. Reverts collision commit ако вече committed. |
| Daily log corrupt (manually edited & saved broken markdown) | Тихол natbutka | Шеф-чат възстановява от template + git history (`git show HEAD~1:daily_logs/DAILY_LOG_*.md`). |
| Slow internet → 3 паралелни Code-ове freeze-ват | Network | Преминава към 1 Code; останалите задачи → утре. |
| `КРАЙ НА ДЕНЯ` без S3 (skip P0 fix) | Тихол изтощен | Шеф-чат логва P0 в `## END OF DAY — DEFERRED P0` секция, alert: „⚠️ P0 open overnight, fix utre as priority 1". |

---

## 7. TESTING_LOOP integration

`tools/testing_loop/` (S87) предоставя continuous AI insights validation за tenant=99. DAILY_RHYTHM го интегрира в S1 boot:

1. **S1 boot** (Phase 2 STATUS REPORT) включва:
   ```
   TESTING LOOP: <status> · last run <ts> · <N> live insights tenant=99
   ```
2. **🟢 healthy** → продължи с build план.
3. **🟡 warning** → шеф-чат споменава в plan но не блокира.
4. **🔴 critical** → S1 P0 = „fix testing loop"; build план се отлага.

EOD wrap-up също чете `latest.json` — ако днес имало 🔴 анъмлия, маркира в `## END OF DAY` секция.

Loop sample (от 27.04.2026 day-1):

```json
{
  "snapshot_date": "2026-04-27",
  "ai_insights_total_live": 19,
  "diff": {"status": "healthy", "reason": "all checks passed"}
}
```

---

## 8. Manual overrides

| Команда | Effect |
|---|---|
| `СЕСИЯ N` (където N ∈ {1,2,3}) | Започва фаза N. Ако предишна не е closed → шеф-чат пита „да закрия ли S(N-1)?" |
| `КРАЙ НА СЕСИЯ N` | Closes phase N, summary в daily log. |
| `КРАЙ НА ДЕНЯ` | EOD wrap-up (виж §5). |
| `SKIP СЕСИЯ N` | Маркира фазата като skipped в daily log (e.g. „S2 пропусната — Тихол на лекар"). Преминава към следваща. |
| `MERGE СЕСИИ 1+2` | Build + test в общ slot. Шеф-чат създава combined section. |
| `EXTEND СЕСИЯ N до HH:MM` | Шеф-чат отбелязва extension. Не блокира следваща. |
| `RESTART СЕСИЯ N` | Closes текущата, отваря нова със същия номер (rare; escape hatch при критичен restart). |
| `STATUS` | Бърз grep на daily log → "S1 closed (3 commits), S2 in progress (12 bugs found), S3 not started." |
| `BUG: <text>` | Append към текущата SESSION's bug list (S2 norm; може и в S3 ако discovery по време на fix). |
| `DEFER: <bug-id>` | Move bug to next day's priority list. |

---

## 📌 Quick reference card (pin-able)

```
08–12  S1 BUILD   →  „СЕСИЯ 1"  →  ДАЙ ПЛАН  →  Code #1/2/3 startup prompts  →  „КРАЙ НА СЕСИЯ 1"
13–17  S2 TEST    →  „СЕСИЯ 2"  →  test brief from S1 commits  →  bug log  →  „КРАЙ НА СЕСИЯ 2"
18–21  S3 FIX     →  „СЕСИЯ 3"  →  P0 routing  →  fix prompts  →  „КРАЙ НА СЕСИЯ 3"
21:00  EOD        →  „КРАЙ НА ДЕНЯ"  →  STATE diff + COMPASS (ако промяна) + git commit
```

**Дневен log:** `daily_logs/DAILY_LOG_YYYY-MM-DD.md` — append-only, 1 файл/ден.  
**Templates:** `templates/session_{1_build,2_test,3_fix}.md` — pre-flight + decision trees.  
**Status за шеф-чат при boot:** `STATE_OF_THE_PROJECT.md` § STANDING PROTOCOLS → DAILY_RHYTHM ред.

---

**Document version:** v1.0 — 27.04.2026  
**Owner:** шеф-чат (only chat that writes to this file)  
**Reviewers:** Тихол  
**Promotion path:** При 7 поредни дни без skip → STANDING_RULE_#24 (повишава DAILY_RHYTHM до iron protocol).
