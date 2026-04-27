# 🔧 SESSION 3 — FIX (template)

**Slot:** 18:00–21:00 | **Owner:** шеф-чат | **Trigger:** „СЕСИЯ 3"

> Append-only — копирай в daily log, не редактирай оригинала.

---

## Pre-flight checks

- [ ] S2 е closed (`Closed: HH:MM` ред + severity counts).
- [ ] Bug list е сортиран по severity (P0 → P1 → P2).
- [ ] Disjoint paths verified — никакви 2 P0 на един файл при паралелни Code-ове.
- [ ] Cognitive load assessment: късно вечерта → low-risk fixes preferred за Code #2/3.
- [ ] TESTING_LOOP не regressed during S2 (повторен `latest.json` peek).

---

## Routing decision tree

```
P0 count > 0?
├─ YES → S3 фокус = САМО P0; P1/P2 deferred (по подразбиране)
│        ├─ Single Code-ов capacity? → 1 P0 sequential, останалите defer
│        └─ Multi-Code (disjoint)?   → max 3 паралелни P0 fixes
└─ NO  → P0 = 0 → продължи към P1
        ├─ P1 count ≤ 5? → fix-вай в S3
        ├─ P1 count > 5? → top-3 fix, останалите → tomorrow
        └─ P2 ако време остава
```

**Rule #21:** ако fix touch-ва AI logic → диагностиката MUST run преди commit.

---

## Fix prompt template (per bug)

```
ROLE: Code #N
SESSION: SXX.MODULE.FIX
BUG ID: B-XXX (от daily_logs/DAILY_LOG_YYYY-MM-DD.md S2 секция)

CONTEXT:
- Severity: 🔴 P0 / 🟡 P1
- Reproduces: <%>
- Description: <text от bug log>
- Expected: <expected behavior>
- Actual: <actual behavior>

LOCATION:
  /var/www/runmystore/<file>:<line>

ТВОИ ФАЙЛОВЕ:
  /var/www/runmystore/<file>             [PATCH]

NE PIPAS:
  - все други файлове засегнати в S1 build (FILE LOCK)
  - tools/testing_loop/* (production-active loop)

DOD:
  ✅ php -l = OK / py_compile = OK
  ✅ Bug reproduces → fix → не reproduces (manual smoke)
  ✅ Никаква regression в близки сценарии

GIT:
  - git status + git log -5 (Rule #19)
  - git commit --only <file> (mirror race protection)
  - Commit: "SXX.MODULE.FIX P0#N: <one-liner>"
  - Push origin main

БЕЗ ВЪПРОСИ. ИЗПЪЛНЯВАЙ.
```

---

## Status icons (Bug fixes section)

| Icon | Meaning |
|---|---|
| ✅ | Fixed + verified (smoke pass + commit hash) |
| ⏳ | In progress (Code working) |
| ❌ | Attempted but incomplete (root cause TBD or regression introduced) |
| ⏸ | Deferred to tomorrow (with reason) |
| 🔄 | Re-opened (initial fix didn't fully resolve) |

---

## Common pitfalls

| Pitfall | Lesson | Mitigation |
|---|---|---|
| P0 fix introduces regression в close код | Wide blast radius | Code тества adjacent flows, не само the bug itself |
| 3 паралелни Code на P0-ове → 2 пишат в STATE едновременно | merge conflict | Шеф-чат serialize-ва STATE updates ИЛИ Code-овете оставят за EOD |
| P1 fix touch-ва файл който S1 commit changed → race | FILE LOCK retroactive | Pre-flight: проверка дали S1 commit още не е merged remotely |
| „Quick fix" грешен (3-line patch без test) | Quality drift при умора | Mandatory smoke test даже на 1-line fix |
| Forget Rule #21 → Cat A regresses overnight | AI logic не диагностицирана | Mandatory diagnostic re-run за всеки AI-touch fix |
| Fix prompt без BUG ID → Code не знае кой bug-а fix-ва | Confusion | Винаги cite `B-XXX` от daily log |
| Late-night → Code-ът фабриква DOD success | Quality degrade | Шеф-чат spot-checks 1 random fix preди commit |

---

## Examples (hypothetical EOD на ден със S87.SALE rewrite)

**S2 bugs found:**

```
| 🔴 P0 | B-001 | 100% | INSERT INTO sales total_amount column не съществува      | sale.php:94   | Code #1 |
| 🔴 P0 | B-002 | 100% | INSERT INTO sale_items subtotal column не съществува    | sale.php:106  | Code #1 |
| 🟡 P1 | B-003 | 100% | payment_method='transfer' enum mismatch                 | sale.php:1064 | Code #2 |
| 🟡 P1 | B-004 | 80%  | camera fallback toast missing на Safari iOS             | sale.php:1972 | Code #2 |
| 🟢 P2 | B-005 | 30%  | parked sales animation flicker                          | sale.php:1854 | (defer)|
```

**S3 routing:**

- Code #1 — sequential B-001 → B-002 (file LOCK на sale.php; не може parallel).
- Code #2 — sequential B-003 → B-004 (също sale.php — Code #2 trябва да чакат Code #1 да push-не).
- B-005 → defer (P2 cosmetic).

⚠️ FILE LOCK insight: P0+P1 на същия файл → серriallne, не parallel. Шеф-чатът маркира това в plan: „B-003 ще започне след Code #1 push".

**Fix flow:**

```
18:00 Code #1 → B-001 commit `aaa1111` ✅ (smoke: tenant=99 sale insert OK)
18:30 Code #1 → B-002 commit `bbb2222` ✅ (smoke: line_total persisted)
18:45 Code #2 starts → pulls latest, B-003 commit `ccc3333` ✅
19:30 Code #2 → B-004 commit `ddd4444` ⏳ (Capacitor on-device test pending)
20:00 Rule #21 — diagnostic run: Cat A=100%, D=100% ✅
20:15 STATE update preview → Тихол approves
20:30 КРАЙ НА СЕСИЯ 3 → шеф-чат логва ✅×3, ⏳×1, ⏸×1
21:00 КРАЙ НА ДЕНЯ → STATE/COMPASS/git commit (виж DAILY_RHYTHM §5)
```

---

## Closing checklist (на „КРАЙ НА СЕСИЯ 3")

- [ ] `Closed: HH:MM` в daily log.
- [ ] Всички P0 имат ✅ или explicit `⏸` с reason (не „празно").
- [ ] Diagnostic re-run if AI-logic-touch P0 fixed; result logged.
- [ ] `### Deferred:` секция попълнена с bug-ID + reason + tomorrow priority.
- [ ] Bridge към EOD: шеф-чат подготвя STATE diff + (ако има) COMPASS LOGIC CHANGE entry.
- [ ] Final commit message draft в template format: `EOD YYYY-MM-DD: N sessions, M commits, K P0 open`.
