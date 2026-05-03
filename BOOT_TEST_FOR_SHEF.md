# 🧪 BOOT_TEST_FOR_SHEF v3 — Шеф-чат IQ test

**Версия:** 3 (04.05.2026)
**Replaces:** v2 (02.05.2026); v1 (project-knowledge only, never in repo)
**Цел:** Тихол да открие "тъп шеф-чат" в първите 3 минути, не след 30 минути работа.

**Употреба:** Когато отвориш нов шеф-чат, ПОСЛЕ като прочете задължителните файлове, paste-ваш този тест. Ако шеф-чат отговори ≥9/11 правилно на Tier 1 + 5/5 на Tier 2 + **1/1 на Tier 3 INVENTORY (задължителен!)** → продължаваш. Иначе RESTART.

---

## ИНСТРУКЦИЯ ЗА ШЕФ-ЧАТ (paste-ваш горните 4 реда + следното):

```
След като си прочел SHEF_RESTORE_PROMPT v2.5 + STATE + COMPASS + STRESS_BOARD + PRIORITY_TODAY + DAILY_RHYTHM,
И след като си извлякъл Phase 0.5 BUG/TASK INVENTORY,
отговори на тези 11 въпроса.

Tier 1 + Tier 2 = ДА / НЕ / ЧАСТИЧНО + 1 изречение обяснение.
Tier 3 (В11) = точен брой + полен списък.

Използвай САМО STATE_OF_THE_PROJECT.md като източник за статус.
Ако нещо в userMemories казва различно от STATE → STATE wins, flag-ни.

ВЪПРОСИ Tier 1 (Status):

1. Печата ли се от APK на Bluetooth принтер DTM-5811 в момента?
2. Има ли live `life-board.php` файл в production?
3. Applied ли е DB schema migration за AI Studio (3 нови таблици)?
4. Diagnostic Cat A=100% / D=100% ли е сега?
5. Wizard step 5 в products.php има ли новия AI Studio modal с 5 категории?
6. Real product entry на tenant=7 завършен ли е (50+ продукта)?
7. Дата на ENI launch?
8. sale.php rewrite-нат ли е с voice + camera + numpad?
9. Колко паралелни Claude Code сесии са оптимални без collision risk?
10. Кой обновява MASTER_COMPASS.md — шеф-чат, работни чатове, или и двете?

ВЪПРОС Tier 3 (Inventory) — ЗАДЪЛЖИТЕЛЕН:

11. Колко P0 (blocker) items има в днешния BUG/TASK INVENTORY?
    Дай ги по име/код, БЕЗ "приблизително", БЕЗ "около".
    Включи всички 5 източника:
    - PRIORITY_TODAY P0
    - COMPASS Sprint pending P0
    - REWORK QUEUE pending P0
    - STRESS_BOARD ГРАФА 3 P0
    - STATE LIVE BUG INVENTORY P0

    Ако пропуснеш дори едно P0 item → автоматично 0/1 на този въпрос → RESTART.
```

---

## KNOWN ANSWERS — Tier 1 (тези са истините):

| # | Въпрос | Правилен отговор | Защо това е важно |
|---|---|---|---|
| 1 | Bluetooth APK печата? | **ДА** (DTM-5811, TSPL, 50×30mm, BG cyrillic) | Тъп чат ще каже "блокер" заради outdated COMPASS history |
| 2 | life-board.php live? | **ДА** (S82.VISUAL Phase 2, 580 реда per STATE) | Тъп чат ще каже "не съществува" заради userMemories |
| 3 | AI Studio DB applied? | **ДА** (S82.STUDIO.APPLY 27.04, 3 таблици + 10 колони) | Тъп чат ще каже "pending review" |
| 4 | Diagnostic 100%? | **ДА от 27.04** (S85.DIAG.FIX 51/51 PASS; S88C 57/57 PASS). RWQ-47 ✅ closed 03.05.2026 EOD per Rule #3 (STATE wins). | Тъп чат ще цитира outdated 47/21% или ще каже "RWQ-47 still pending" — той е closed. |
| 5 | Wizard step 5 нов modal? | **ЧАСТИЧНО** (mockup approval, code merge UNVERIFIED) | Реалността е verify pending |
| 6 | Real entry tenant=7 done? | **НЕ** (S83 беше планиран 27.04; статус днес не explicit "done" в STATE) | Без verify не приемаме |
| 7 | ENI launch дата? | **14-15 май 2026 (FIXED)** — countdown от 04.05 = **10 дни**. Винаги пресмятай дни-до-14.05 от today. | Тъп чат ще каже септември (объркване с public launch) или ще даде stale countdown. |
| 8 | sale.php rewrite? | **ДА** (S87G.SALE.UX_BATCH 7 bugs done 29.04 + S91.MIGRATE.SALE 01.05) | userMemories казва outdated "TBD S87 май" |
| 9 | Колко паралелни Code optimal? | **2 Code Code (max 4 общо: 2 Code + 1 Opus + Тихол)** per Rule #7 | Не "колкото искаш" |
| 10 | Кой update-ва COMPASS? | **САМО шеф-чат** (Rule #22) | Code пишат `[COMPASS UPDATE NEEDED]` в handoff |

---

## KNOWN ANSWER — Tier 3 (Inventory, в11)

**Това НЕ е статичен отговор.** Inventory се променя ежедневно. Тихол прави live verification:

1. Шеф-чат дава своя отговор: "В днешния inventory имам N P0 items: A, B, C, ..."
2. Тихол отваря TODAY-ZA-ден inventory snapshot и сравнява
3. Ако шеф-чатът пропуска item който Тихол вижда в STATE/PRIORITY/STRESS → **0/1**
4. Ако грешен брой → **0/1**

**Honest signal:** ако шеф-чат каже "не съм 100% уверен в броя" → ОК, кажи "RE-EXTRACT" → той re-extract-ва Phase 0.5 → дава отново.

**RESTART trigger (zero tolerance):** ако шеф-чат **самоуверено** даде грешен/непълен списък → restart процес наново (нов шеф-чат, fresh boot).

---

## SCORING

| Score | Action |
|---|---|
| **15-16/16** | Перфектен шеф. Дай му пълно доверие. |
| **12-14/16** | Добър. Verify слабите 1-2. |
| **9-11/16** | Mediocre. Старият state. Принуди го да чете STATE ред по ред. |
| **<9/16** | Тъп чат — restart. |
| **Tier 3 = 0/1** | **АВТОМАТИЧЕН RESTART** независимо от другите точки. |

---

## DEEP TEST (опционално, ако шеф-чат отговори 16/16)

11+. Колко commits в S82.STUDIO marathon? (Отговор: ~9-12 в 3 паралелни Code, последен COMPASS update eba086b)
12+. Защо AI описание €0.02? (BG/RO cyrillic = 2-3× повече tokens; 500 клиенти × 100 описания/мес = €250-€400/мес чиста загуба)
13+. Защо tenants.ai_credits_* остават след S82.STUDIO.APPLY? (30-дневен grace, drop ~2026-05-27)
14+. Защо beta launch е 14-15 май? (Real entry + BT integration + sale rewrite + ENI on-boarding не се ускоряват)
15+. Top-3 риска в днешния P0 inventory? (от Phase 0.5 output)

---

## КАК ДА ИЗПОЛЗВАШ ТАЗИ ПРОЦЕДУРА

1. Отвориш нов шеф-чат
2. Paste-ваш SHEF_RESTORE_PROMPT v2.5
3. Чакаш шефът да каже "ШЕФ ЧАТ X — v2.5 ACTIVE" с inventory секция
4. Paste-ваш горната инструкция (11 въпроса)
5. Чакаш отговорите
6. Сравняваш с known answers + verify Tier 3 inventory
7. Решаваш — пас или fail

**Ако пас:** "ОК, давам ти задача — [твоята задача]"
**Ако fail:** "STATE-ът е outdated ИЛИ ти пропусна inventory. RESTART."

---

## ВАЖНО ЗА ТЕБ (Тихол)

**Не очаквай perfect шефове.** Дори умни шефове ще fail-ват 1-2 от Tier 1 заради unclear данни. Това е OK.

**Tier 3 (Inventory) обаче е zero-tolerance.** Ако шеф-чат пропусне P0 item — той ще го пропусне в plan-а → ти губиш ден. Restart е по-евтин.

**5+ грешки на Tier 1 = SIGNAL за drift.** Решение:
- Update STATE_OF_THE_PROJECT.md
- Update userMemories
- Затвори тъп чат, отвори нов

**Не работи 30 минути с тъп чат.** Спестяваш си frustration.
