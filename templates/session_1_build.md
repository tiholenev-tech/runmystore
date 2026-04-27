# 🔨 SESSION 1 — BUILD (template)

**Slot:** 08:00–12:00 | **Owner:** шеф-чат | **Trigger:** „СЕСИЯ 1"

> Този template зарежда шеф-чата за build phase. Append-only — копирай в daily log,
> не редактирай оригинала.

---

## Pre-flight checks (mandatory преди генериране на план)

- [ ] STATE_OF_THE_PROJECT.md прочетен; `Phase` + `% completion` известни.
- [ ] Последен `daily_logs/DAILY_LOG_*.md` прочетен (за P0 carry-over от вчера).
- [ ] `tools/testing_loop/latest.json` прочетен — **ако `status=critical` → S1 P0 = fix loop, не build**.
- [ ] `git -C /var/www/runmystore log --oneline -5` свеж в context.
- [ ] Известни блокери от STATE `⚠️ KNOWN ISSUES` секцията.
- [ ] ENI countdown от 14.05.2026 (от STATE).

---

## Standard plan-generation prompt (шеф → Тихол)

> Прочетох STATE + COMPASS + последен daily log + testing_loop status.
>
> **СЪСТОЯНИЕ:**
> - Phase: A1 (~65%) → A2 преди ENI (NN дни остават)
> - Последна сесия: SXX.YYY (commit `abc1234`)
> - Testing Loop: 🟢/🟡/🔴
> - P0 overnight: 0 / N
>
> **Преди да дам план — 2 въпроса:**
> 1. Колко часа имаш до обяд?
> 2. Енергия 1–10?
>
> След отговора → конкретен план + 3 startup prompts за Code #1/2/3.

---

## Decision tree: how to split work

```
┌─ Има P0 от вчера? ──────────────┐
│   ├─ YES → Code #1 = P0 fix    │ (Rule #21 — ако AI logic, run Cat A+D)
│   └─ NO  → продължи            │
└──────────────────────────────────┘
            ▼
┌─ ≥ 2 паралелни задачи имат disjoint paths? ──────┐
│   ├─ YES → 2–3 Code-ове паралелно                │
│   └─ NO  → 1 Code; останалите задачи solo Тихол  │
└──────────────────────────────────────────────────┘
            ▼
┌─ Една задача > 4h? ────────────┐
│   ├─ YES → split на 2 commit-able stъpки │
│   └─ NO  → 1 startup prompt    │
└────────────────────────────────┘
```

**FILE LOCK rule (S81 lesson):** никога 2 Code-ове на същия файл. Ако трябва
паралелно → split по responsibility (frontend/backend) или sequential.

---

## Standard startup prompt template

```
ROLE: Code #N
SESSION: SXX.MODULE.STAGE
TASK: <какво>

ЧЕТИ преди start: STATE_OF_THE_PROJECT.md + Rule #19, #21, #22

ТВОИ ФАЙЛОВЕ (САМО ТЕЗИ):
  /var/www/runmystore/<file>             [PATCH | NEW]
  /var/www/runmystore/<dir>/             [NEW dir]

NE PIPAS (ABSOLUTE FORBIDDEN):
  - <list>

DOD:
  ✅ php -l = OK (за PHP)
  ✅ py_compile = OK (за Python)
  ✅ <verifiable smoke test>

GIT:
  - git status + git log -5 (Rule #19)
  - git commit --only <paths> (mirror race protection)
  - Commit: "SXX.MODULE: <one-liner>"
  - Push origin main
  - Update STATE_OF_THE_PROJECT.md под "✅ КОЕ РАБОТИ"

БЕЗ ВЪПРОСИ. ИЗПЪЛНЯВАЙ.
```

---

## Common pitfalls (от minali sесии)

| Pitfall | Lesson | Mitigation |
|---|---|---|
| Code използва `git add -A` → захвана untracked файлове от паралелна сесия | a44ee2d incident (S82.SHELL) | Винаги `git add` explicit paths |
| Mirror cron (`/usr/local/bin/sync-md-mirrors.sh`) push race с Code commit | e5c2929 incident | Винаги `git commit --only <paths>` |
| Code не чете STATE → препоръчва `transfers.php нов файл` когато вече exists | drift bug | Pre-flight: paste 1-line "what exists" в startup prompt |
| 3 паралелни Code на един и същ файл → conflicts | S82 marathon разкри | FILE LOCK rule в plan |
| Prompt без BEZ ВЪПРОСИ → Code пита, Тихол губи 10 min context-switching | UX regression | Всеки prompt ends с „БЕЗ ВЪПРОСИ. ИЗПЪЛНЯВАЙ." |
| Code не update-ва STATE → шеф-чат утре не знае какво е готово | drift compounding | DOD задължително: STATE update ред |

---

## Examples (от 27.04.2026 — реален ден)

**Plan:**
- Code #1 → S87.SEED.SALES (`tools/seed/sales_populate.py` нов 480 реда seeder).
- Code #2 → S87.TESTING_LOOP (`tools/testing_loop/` continuous validation).
- Code #3 → S87.SALE.PLAN (`docs/SALE_REWRITE_PLAN.md` 503-line analysis).
- Тихол solo → products.php UX polish.

**Disjoint paths:** ✅ — нула collision (3 различни directorии).

**Commits завъзвани от 1 шеф-чат за деня:** `372e590`, `9d31780`, `bdafd04`, `05af627`, `b3ce2c1` + EOD STATE update.

---

## Closing checklist (на „КРАЙ НА СЕСИЯ 1")

- [ ] Записан `Closed: HH:MM` в daily log.
- [ ] Списък на commits във `### Commits:` секция.
- [ ] 1-line handoff per Code във `### Handoffs:` секция.
- [ ] Никакъв STATE/COMPASS update сега — отлага се за EOD (§5).
- [ ] Bridge към S2: шеф-чатът чете `git log --since="08:00"` за да генерира test brief.
