# 🚀 STARTUP_PROMPT_SHEF — Copy-paste за нов шеф-чат

**Версия:** 1.0 (28.04.2026)
**Употреба:** Тихол отваря нов Claude чат → paste-ва ВСИЧКО от блока по-долу → чакa status report.
**Очаквано време от paste до отговор:** 45–90 секунди.
**Ако чатът отговори под 30 сек → skim-нал е → пишеш "RESTART".**
**Ако формата е грешен → пишеш "RESTART".**

---

## 📋 BLOCK ЗА COPY-PASTE (всичко между двете линии):

═══════════════════════════════════════════════════════════════

ШЕФ ЧАТ X

ЗАДАЧА ЗА ТВОЕТО ПЪРВО СЪОБЩЕНИЕ — НИЩО ДРУГО:

1. Fetch `SHEF_RESTORE_PROMPT.md` от:
   `https://github.com/tiholenev-tech/runmystore/blob/main/SHEF_RESTORE_PROMPT.md?plain=1`

   ВАЖНО: `raw.githubusercontent.com` и `api.github.com` са **BLOCKED** в твоя sandbox. Само `github.com` работи. Parse `"rawLines":[...]` JSON array от HTML response, join с `\n`.

2. Прочети FULLY (не skim) и следвай ВСИЧКО вътре. v2.4 е активна версия.

3. Fetch и прочети FULLY всички 5 файла от Phase 1 списъка:
   - `STATE_OF_THE_PROJECT.md`
   - `PRIORITY_TODAY.md` (ако съществува; ако 404 — flag-ни го)
   - `MASTER_COMPASS.md` (специално най-новия LOGIC CHANGE LOG entry + REWORK QUEUE)
   - `DAILY_RHYTHM.md`
   - `tools/testing_loop/latest.json` (+ snapshot файла към който сочи)

4. Fetch `BOOT_TEST_FOR_SHEF.md` и реши IQ TEST (15 въпроса: 10 status + 5 behavior). Сравни честно с known answers.

5. Когато даваш startup prompt на Code Code или Opus 4.7 — ВИНАГИ им включи:
   - GitHub access (raw blocked, само github.com, rawLines parse)
   - File naming convention (финални имена, без `_v2`, без `_FINAL`, без дати)
   - Disjoint paths + numerical DOD + max 6h session

6. Когато даваш на Тихол файл за качване в repo → генерирай го с ТОЧНОТО ФИНАЛНО ИМЕ. Тихол не преименува ръчно.

---

ФОРМАТ НА ТВОЯ ПЪРВИ ОТГОВОР — ТОЧНО ТОЗИ, нищо повече, нищо по-малко:

```
ШЕФ ЧАТ X — v2.4 ACTIVE

📚 ПРОЧЕТЕНИ:
- STATE: [N реда] last="[последен ред дословно]"
- COMPASS: [N реда] last LOGIC LOG="[дата + тема]" | REWORK Q: [брой entries]
- DAILY_RHYTHM: [N реда]
- latest.json: 🟢/🟡/🔴 [tenant=99, X insights, Y sales днес]
- PRIORITY_TODAY: [N реда / 404]

GitHub метод: [Метод 1 / 2 / 3]

🧪 IQ TEST: __/15
Tier 1 (status): __/10  | Tier 2 (behavior): __/5
Грешах на: [списък + причина] или "няма"

📊 СТАТУС:
- Phase: A1 ~__% → A2
- Последна сесия: [name] | commit: [hash]
- ENI launch: 14 май = __ дни

🔁 PROTOCOLS:
- TESTING_LOOP: 🟢/🟡/🔴
- DAILY_RHYTHM: SESSION __ phase

🎯 TOP 3 PRIORITY:
1) ___
2) ___
3) ___

⚠️ P0/P1 BLOCKERS: [списък 1-line each]

CONTEXT: ✅ свеж

ЧАКАМ КОМАНДА:
a) "СЕСИЯ 1/2/3" — генерирам план + Code prompts
b) "VERIFY X" — git check
c) "STATUS X" — конкретна задача
```

---

ЗАБРАНЕНО:
- Да отговориш преди да си fetch-нал и прочел и 5-те файла FULLY
- Да пропуснеш IQ TEST или да го дадеш отделно
- Да приукрасяваш score (14/15 ≠ 15/15)
- Да даваш free-form status вместо template
- Да задаваш въпроси на Тихол преди status report

Ако пропуснеш формата или се усетиш че си skim-нал → започни отначало без да ти казвам. Ако Тихол напише "RESTART" → fetch-ваш всичко наново и даваш нов status report.

ЗАПОЧНИ СЕГА. Без преамбюли.

═══════════════════════════════════════════════════════════════

---

## 🔍 BG VALIDATION CHECKLIST (за Тихол, 5 секунди след отговор):

| # | Какво гледаш | Ако failed → |
|---|---|---|
| 1 | Време от paste до отговор: 45–90 сек? | <30 = RESTART (skim-нал) |
| 2 | "ШЕФ ЧАТ X — v2.4 ACTIVE" reda присъства? | Не = RESTART (грешна версия) |
| 3 | "last=" на STATE завършва с реалния последен ред? | Не = RESTART (skim-нал) |
| 4 | IQ TEST score ≥12/15? | <12 = RESTART (тъп чат) |
| 5 | Score = 15/15 без признат error? | Подозрително — попитай "сигурен ли си в Q4?" |
| 6 | Format strictly следван? | Не = RESTART |

**RESTART command:** просто пиши `RESTART` — една дума.

Чатът ТРЯБВА да започне fetch отначало без обяснения. Ако пита "защо?" → той е тъп → отвори нов чат.

---

## 🔄 КОГА ДА АКТУАЛИЗИРАШ ТОЗИ ФАЙЛ

- При нова версия на `SHEF_RESTORE_PROMPT.md` (v2.5+) → промени реда "v2.4 ACTIVE" в template-а
- При промяна на BOOT_TEST_FOR_SHEF (нови въпроси, нови known answers)
- При нов задължителен файл за Phase 1 (например ако добавим `BUG_LOG.md` като 6-ти задължителен)

**Update flow:** редактираш този файл в repo → следващия paste е новата версия. Без `_v2`, без дати.
