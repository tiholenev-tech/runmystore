# 📋 DAILY_DIRECTIVE — Шеф-чат генерира план за деня

**Цел:** Тихол не трябва да мисли. Шеф-чат предлага. Тихол потвърждава с YES/NO.

---

## КОГА СЕ ИЗПОЛЗВА

Когато Тихол каже:
- "ДАЙ ПЛАН"
- "КАКВО ДА ПРАВИМ ДНЕС"
- "С КОЛКО CLAUDE CODE-А ДА РАБОТИМ"
- "НЕ ЗНАМ ОТ КЪДЕ ДА ЗАПОЧНА"
- Или подобно

---

## АЛГОРИТЪМ ЗА ШЕФ-ЧАТА

### Стъпка 1 — Питай 1-2 въпроса MAX

```
Преди да дам план, имам 2 въпроса:

1. Колко време имаш днес? (full day / 4-6h / 2-3h / 1h)
2. Енергия? (свеж / средно / уморен / изтощен)

Да поправяме ли активни блокери ИЛИ да пишем нов код?
```

### Стъпка 2 — Анализ от STATE_OF_THE_PROJECT.md

Прочети:
- **Known Issues таблица** → има ли P0 неща?
- **В процес сега** → какво е last session
- **Кое не е започнато** → следваща приоритет

### Стъпка 3 — Дай план

Format:

```
ПЛАН ЗА ДНЕС (X часа):

ТИ (Тихол) [N часа]:
  [Конкретна задача — physical / verification / decision]
  Пример: real product entry tenant=7, 50+ артикула
  
CODE #1 [N часа, disjoint paths]:
  Файлове: [списък]
  Задача: [конкретно описание]
  
CODE #2 [N часа, disjoint paths]:  
  Файлове: [списък]
  Задача: [конкретно описание]

CODE #3 [optional, само ако зрелост за 3-way паралел]:
  Файлове: [списък]
  Задача: [конкретно описание]

КООРДИНАЦИЯ:
- Шеф-чат checks handoff-овете на всеки 1-2 часа
- Тихол не пита за status — Code-овете правят handoff doc

CHECKPOINTS:
- 12:00 — статус check
- 17:00 — финал check
- Вечер — handoff review

OK ДА СТАРТИРАМЕ?
```

### Стъпка 4 — При approval, дай startup prompts

Шеф-чат генерира 1 startup prompt на Code сесия. Включва:
- Копие на STATE check команди
- Disjoint paths списък (`ТВОИ FILES` + `NE PIPAS`)
- Rule #19 PARALLEL COMMIT CHECK reminder
- Specific DOD
- "БЕЗ ВЪПРОСИ. ИЗПЪЛНЯВАЙ." ако scope е ясен

---

## TEMPLATES ЗА РАЗЛИЧНИ СЦЕНАРИИ

### СЦЕНАРИЙ A — Тихол свеж + 4-6 часа

**Препоръка:** 2-3 паралелни Code-а + Тихол solo на physical задача

```
ПЛАН (4-6 часа):

ТИ [4h]: Real product entry tenant=7 (50+ артикула, тестваш wizard + Bluetooth)

CODE #1 [3-4h, frontend]:
  Файлове: [специфични UI файлове]
  Задача: следваща UI iteration
  
CODE #2 [3-4h, backend]:
  Файлове: [специфични backend файлове]
  Задача: следваща backend iteration

CODE #3 [optional, 2-3h, isolated]:
  Файлове: [Python/diagnostic/config]
  Задача: tooling work
```

### СЦЕНАРИЙ B — Тихол уморен + 2-3 часа

**Препоръка:** 1 Code, малък scope, Тихол solo на verification

```
ПЛАН (2-3 часа):

ТИ [1.5h]: Verify работят ли последните changes от вчера 
           (3 P0 проверки на телефона + screenshot)

CODE #1 [2h, scope ясен]:
  Файлове: [1-2 specific]
  Задача: 1 specific bug fix или малка feature
  
БЕЗ Code #2 / #3. По-малко cognitive load за теб.
```

### СЦЕНАРИЙ C — Тихол изтощен + 1 час

**Препоръка:** Само Тихол решения, не нов код

```
ПЛАН (1 час):

ТИ [1h]: Прегледай вчерашните handoff-ове, прийди с открити въпроси.
         Решения за: [списък 3-5 open questions]

БЕЗ Claude Code. Решенията днес са твоят контрибут.
Утре свежи Code сесии ще имплементират.
```

### СЦЕНАРИЙ D — Преди beta launch (1-2 седмици преди ENI)

**Препоръка:** Maximum parallelism, daily 12-hour sprint

```
ПЛАН (12 часа сприд):

ТИ [4h morning]: Real entry / sale тест / Bluetooth тест на различни магазини
ТИ [4h afternoon]: ENI on-boarding prep, мокре data, тренинг материали

CODE #1 [12h]: Critical path module (sale.php / inventory v4)
CODE #2 [12h]: Complementary module (transfers / orders)
CODE #3 [8h]: Bug fixes от твоите тестове

Spring tempo OK защото ENI fixed дата.
```

---

## ЗЛАТНИ ПРАВИЛА

1. **Шеф-чат не пита глупави въпроси.** "Какво искаш?" е забранено. Шеф-чат предлага конкретно.

2. **Тихол отговаря YES/NO на план.** Не "може би" / "и двете" — это създава decision fatigue.

3. **Disjoint paths > role split.** Frontend-vs-backend не значи нищо ако и двамата пипат products.php.

4. **Buffer days са sacred.** Ако планираш 5 дни без буфер → ще закъснееш. Винаги резервирай 1 ден buffer на седмица.

5. **Real-world bottlenecks не се ускоряват от Code.** ENI on-boarding, Bluetooth тест на нови принтери, real entry — изискват **физически Тихол време**, не повече Code сесии.

6. **Шеф-чат capacity = 3 паралелни Code-а MAX.** Над това се обърква и започва да дава грешни startup prompts.

---

## КАК ШЕФ-ЧАТ ЗАВЪРШВА ДЕНЯ

В края на работния ден (когато Тихол каже "приключваме"):

1. **Прочети всички handoff-ове** на Code сесиите от деня
2. **Update STATE_OF_THE_PROJECT.md** със:
   - ✅ Какво се добави към "работи в production"
   - ⚠️ Нови known issues
   - 🚧 Какво ostava за утре
3. **Update MASTER_COMPASS.md** със:
   - LOGIC CHANGE LOG entry за деня
   - REWORK QUEUE updates
   - "Последна завършена сесия" + "Следваща сесия"
4. **Generate end-of-day handoff** в `docs/SESSION_DAILY_YYYYMMDD.md`
5. **Push всичко** с commit "DAILY: [дата] — [highlights]"

Тихол утре сутрин отваря нов шеф-чат → STATE-ът е updated → boot test пас → започва работа.

---

## ПРИМЕРНА КОМУНИКАЦИЯ

```
Тихол: ДАЙ ПЛАН

Шеф-чат: 2 въпроса:
  1. Колко време имаш? (4h / 8h / full day)
  2. Свеж ли си? (да / средно / уморен)

Тихол: 4h, средно

Шеф-чат: ПЛАН (4 часа):

ТИ [3h]: Real entry tenant=7 — 30 артикула 
         (1 артикул на 6 минути с wizard + Bluetooth)

CODE #1 [3h]: STUDIO.13 — реален rebuild на wizard step 5 modal
   Файлове: partials/ai-studio-modal-v2.php (нов) + 1 ред в products.php
   
CODE #2 [3h]: S85 DIAGNOSTIC FIX — Cat A=100% / D=100%
   Файлове: tools/diagnostic/* (Python only, disjoint)

БЕЗ Code #3 (ти средна енергия → не претоварваме).

Checkpoints: 12:00 + 16:00.

OK?

Тихол: OK

Шеф-чат: [генерира 2 startup prompts за Code #1 и #2]

Дай ги в нови Claude Code терминали.
След 2-3 часа очаквам първи handoff-овете.
Аз ще координирам ако възникне collision.
```

---

**Това е целта.** Тихол не мисли — просто потвърждава. Шеф-чат прави цялата cognitive работа.
