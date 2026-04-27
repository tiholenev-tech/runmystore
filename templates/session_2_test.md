# 🧪 SESSION 2 — TEST (template)

**Slot:** 13:00–17:00 | **Owner:** шеф-чат | **Trigger:** „СЕСИЯ 2"

> Append-only — копирай в daily log, не редактирай оригинала.

---

## Pre-flight checks

- [ ] S1 е closed в daily log (има `Closed: HH:MM` ред).
- [ ] `### Commits:` секция има поне 1 commit.
- [ ] Шеф-чат прочел `git log --since="08:00" --pretty="%h %s"`.
- [ ] За всеки commit: identifying touched modules → mapping към test sources:
  - `sale.php` → `docs/SALE_REWRITE_PLAN.md §6 Test Scenarios`
  - `compute-insights.php` или pf*() → `tools/diagnostic/modules/insights/`
  - `chat.php` / `life-board.php` → manual UX flow
  - `tools/seed/*` → run seeder + verify counts
  - `admin/beta-readiness.php` → browser load + section visibility
- [ ] TESTING_LOOP `latest.json` не е regressed от S1 build.

---

## Test brief generation prompt (шеф → Тихол)

> Прочетох S1 commits + RULES от COMPASS per modul. Ето тест brief:
>
> ## Brief за commit `abc1234` (S87.MODULE.STAGE)
> - [ ] Scenario 1: [happy path] → expect …
> - [ ] Scenario 2: [edge] → expect …
> - [ ] Scenario 3: [race] → expect …
>
> Тихол: копирай това в браузер/телефон, изпълнявай. Bug-овете дай тук
> като „bug: <text>" — аз ги логвам по severity.

---

## Bug logging template

Шеф-чат на всеки „bug: …" appendва ред:

```markdown
| 🔴 P0 | B-XXX | 100% | <description> | <file>:<line> | Code #N (S3 owner) |
```

ID convention: `B-001`, `B-002`, … (per-day, не глобален).

---

## Severity decision tree

```
Гудещи данни? → 🔴 P0
   ↓ no
Payment / inventory write счупен? → 🔴 P0
   ↓ no
Beta launch blocker? → 🔴 P0
   ↓ no
Flow счупен (workaround съществува)? → 🟡 P1
   ↓ no
Силен UX degrade? → 🟡 P1
   ↓ no
Cosmetic / perf < 100ms / edge < 1% users? → 🟢 P2
```

**P0 примери:** sale.php INSERT broken column, inventory race, auth bypass,
TESTING_LOOP 🔴, diagnostic Cat A < 95%.

**P1 примери:** voice fallback, camera toast missing, discount overflow.

**P2 примери:** sparkline color, theme flicker, parked sales animation timing.

---

## Diagnostic harness run (Rule #21)

Ако S1 touch-на AI logic (`compute-insights.php`, селекция, pf*(), seed):

```bash
cd /var/www/runmystore
python3 tools/diagnostic/run_diag.py --module=insights --pristine
```

Очаквания:
- Cat A pass rate ≥ 100% (target) или ≥ 95% (acceptable for warning).
- Cat D pass rate ≥ 100%.

Ако не → log като 🔴 P0 със текст: „Diagnostic Cat A regressed — N/M failed scenarios".

---

## Common pitfalls

| Pitfall | Lesson | Mitigation |
|---|---|---|
| Тихол маркира bug като „cosmetic" но ефектът е data loss | Severity gut feeling грешен | Шеф-чат re-classify-ва ако описание споменава „loss/skip/wrong"  |
| Bug дублиран от вчера (вечерта marked deferred) | Memory drift | Шеф-чат search в `daily_logs/` за същия pattern → флагва duplicate |
| Diagnostic не пуснат when AI logic touched | Rule #21 violation | Pre-flight checklist помни го |
| Test brief е твърде дълъг → Тихол губи focus | Cognitive overload | Max 5 scenarios per commit; ако > → split на bundles |
| Bug discovery happens by accident (не от brief) | Free testing | Append `### Incidental bugs:` sub-section, не блокира brief progress |

---

## Examples (от 27.04.2026 hypothetical S2)

S1 build-на: `S87.SEED.SALES` (sales_populate.py), `S87.TESTING_LOOP`, `S87.SALE.PLAN`.

**Test brief:**

```
## Brief за S87.SEED.SALES (commit 372e590)
- [ ] python3 tools/seed/sales_populate.py --tenant=99 --count=15 → exit 0
- [ ] tenant=99 sales count расте с 15 (не 0, не 30)
- [ ] sale_items rows = sum(item_count_per_sale)
- [ ] inventory drops correctly per item
- [ ] no foreign-key errors

## Brief за S87.TESTING_LOOP (commit 9d31780)
- [ ] python3 tools/testing_loop/daily_runner.py --snapshot-only → exit 0
- [ ] daily_snapshots/YYYY-MM-DD.json exists
- [ ] latest.json symlink → today
- [ ] admin/beta-readiness.php Section 7 рендира (browser test)

## Brief за S87.SALE.PLAN (commit 05af627)
- [ ] docs/SALE_REWRITE_PLAN.md ≥ 300 lines (503 actual)
- [ ] All 10 sections present
- [ ] No file changes outside docs/ (read-only analysis)
```

---

## Closing checklist (на „КРАЙ НА СЕСИЯ 2")

- [ ] `Closed: HH:MM` в daily log.
- [ ] Severity counts: `P0 = N | P1 = M | P2 = K`.
- [ ] Bug rows pre-routed (preliminary `Owner` column).
- [ ] Diagnostic run-нат ако applicable; result logged.
- [ ] Препоръка за S3 routing (P0 first, disjoint paths).
- [ ] Bridge към S3: шеф-чат чете `### Bugs found:` за fix prompts.
