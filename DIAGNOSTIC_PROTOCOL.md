# DIAGNOSTIC_PROTOCOL.md — RunMyStore.ai Testing Standard

**Версия:** 1.0  
**Създаден:** 24 април 2026 (S79 closure)  
**Автор:** Тихол + Claude (S79 session)  
**Статус:** ACTIVE — задължителен стандарт

---

## 🎯 ЗАЩО СЪЩЕСТВУВА ТОЗИ ДОКУМЕНТ

RunMyStore.ai има AI-мозък, който генерира insights (препоръки) на базата на SQL логики. **Без систематично тестване е невъзможно да знаем кога една логика е счупена.** Можем да пуснем обновление и да мислим че работи — а всъщност да даваме грешни препоръки на собственика на магазина.

В S79 открихме и поправихме реален bug (`pfHighReturnRate` показваше 100% върнати вместо 10%). Бугът беше там **от деня на създаване** на функцията. Никой не знаеше. Бихме го открили след месеци.

**Това НЕ трябва да се случва отново. НИКОГА.**

---

## 📚 ТЕРМИНОЛОГИЯ (на прост език)

| Термин | Какво значи |
|---|---|
| **AI модул** | Функция/група функции които правят AI препоръки (напр. `compute-insights.php` с 19 функции) |
| **Сценарий** | Нарочно създадена ситуация в магазина ("продукт замрял 46 дни") |
| **Оракул** | Списъкът с очаквани отговори (като отговорник в учебник) |
| **Тест** | Сравнение: какво *очаквахме* vs какво AI-то *реално* върна |
| **PASS/FAIL** | Дали отговорът на AI съвпада с очаквания |
| **Seed** | Пълнене на DB с нарочни данни за тестването |
| **Regression test** | Повторно пускане на стари тестове за да хванем ново счупване |

---

## 🏷 КАТЕГОРИИ ТЕСТОВЕ

Всеки тест принадлежи на **ЕДНА** от тези 4 категории:

### 🔴 CATEGORY A — CRITICAL (блокиращ)
**Определение:** Тест който проверява основна бизнес логика. Грешка тук значи AI дава погрешни препоръки на собственика → реални финансови загуби.

**Примери:**
- `selling_at_loss` алармира ли когато retail < cost?
- `below_min_urgent` хваща ли недостиг на стока?
- `margin_below_15` не дава ли false positive?
- `pfHighReturnRate` не брои ли повторено (Cartesian bug)?

**Правило:** **Commit не се приема без PASS на A тестове.**

### 🟡 CATEGORY B — IMPORTANT (желателен)
**Определение:** Тест за второстепенна логика или UX подобрение.

**Примери:**
- `size_leader` намира ли best-selling размер?
- `basket_driver` намира ли двойки продукти?
- `loyal_customers` хваща ли 3+ покупки?

**Правило:** Commit може да мине с FAIL в B, но **задължително документирано** в компаса.

### 🟢 CATEGORY C — NICE-TO-HAVE (декорация)
**Определение:** Тест за добавъчна функционалност (nice-to-have insight).

**Примери:**
- `highest_margin` ordering точно ли е?
- `top_profit_30d` прецизен ли е на 1 продукт?

**Правило:** FAIL допустим, тракаме тренд.

### ⚪ CATEGORY D — BOUNDARY (граничен)
**Определение:** Тест за гранични случаи (off-by-one, NULL, timezone).

**Примери:**
- Замрял на ден 45 точно — включва ли се?
- Stock=min (точно на границата) — алармира ли?
- NULL cost_price — не дава ли infinite margin?

**Правило:** Грешка в D = SQL bug. Commit не се приема.

---

## 🕐 КОГА СЕ ПРИЛАГАТ ТЕСТОВЕТЕ

### 🔁 ТРИГЕРИ ЗА ПУСКАНЕ

**TRIGGER 1 — Нов AI модул (задължителен)**  
Когато се добавя нов AI-powered модул:
- Преди commit-а: разработчикът пише сценарии за новия модул
- Пуска се diagnostic → всички A+D тестове трябва да PASS
- Без PASS → commit НЕ се приема

**TRIGGER 2 — Регулярен weekly cron (автоматичен)**  
Всеки **понеделник в 03:00 Europe/Sofia**:
- Пълен wipe на test tenant (tenant=7)
- Пълен seed (всички сценарии)
- Compute + verify за всички модули
- Резултат в `diagnostic_log` + email на Тихол

**TRIGGER 3 — Месечен пълен сканер (автоматичен)**  
Всеки **1-ви ден на месеца в 04:00**:
- Същото като weekly + performance metrics (колко време отнема всяка функция)
- Исторически тренд report

**TRIGGER 4 — Ръчно пускане (по заявка)**  
Командата `AI DIAG ПУСНИ` (от Тихол към който и да е Claude) → пуска пълен diagnostic веднага.

**TRIGGER 5 — При съмнение за bug**  
Ако Тихол или клиент (ЕНИ) забележи странно поведение → Claude задължително пуска diagnostic ПРЕДИ да разследва.

### ❌ КОГА НЕ СЕ ПУСКА

- На production tenants (ЕНИ = tenant=47, future clients)
- По време на активна сесия с променени файлове
- При нестабилна DB (recent errors, active transactions)

---

## 👤 КОЙ ПИШЕ ТЕСТОВЕ

| Роля | Отговорност |
|---|---|
| **Claude (разработчик)** | Пише сценарии за нов модул ПРЕДИ да пише кода. Test-Driven подход. |
| **Тихол (product owner)** | Одобрява кой сценарии са Category A vs B vs C. Решава за boundary behavior. |
| **Claude Code (bulk)** | Изпълнява масивни тестове, оптимизация на seed scripts. |
| **Diagnostic cron** | Пуска автоматично, без човек. |

---

## 📐 СТРУКТУРА НА ТЕСТ

Всеки тест има **6 задължителни полета** (в `seed_oracle` таблица):

```sql
CREATE TABLE seed_oracle (
    scenario_code       -- Уникален ID: 'zombie_pos_0'
    expected_topic      -- Кой insight топик: 'zombie_45d'
    category            -- A/B/C/D
    expected_should_appear  -- 1 (трябва да се появи) или 0
    verification_type   -- product_in_items / pair_match / ...
    scenario_description -- Човешко описание
);
```

---

## 🎯 ПОЛИТИКА ЗА PASS RATES

| Module maturity | Required A+D PASS | B+C PASS допуск |
|---|---|---|
| **New (в разработка)** | 100% | min 60% |
| **Beta (в ЕНИ тест)** | 100% | min 80% |
| **Stable (production)** | 100% | min 90% |
| **Frozen (няма промени)** | 100% | min 95% |

**Non-negotiable:** Category A или D < 100% → **rollback** последния commit и re-fix.

---

## 🗂 КЪДЕ ЖИВЕЯТ ТЕСТОВЕТЕ (архитектура)

```
/var/www/runmystore/
├── tools/
│   └── diagnostic/
│       ├── core/
│       │   ├── seed_runner.py      # Universal runner
│       │   ├── db_helpers.py       # DB connect, mysql helpers
│       │   ├── oracle_populate.py  # Write expectations
│       │   └── verify_engine.py    # Compare actual vs expected
│       ├── modules/
│       │   ├── insights/           # За compute-insights.php
│       │   │   ├── scenarios.py
│       │   │   ├── fixtures.py
│       │   │   └── oracle_rules.py
│       │   ├── onboarding/         # Future: onboarding AI flow tests
│       │   ├── chat/               # Future: AI chat response tests
│       │   └── <new_module>/       # 1 folder per AI module
│       ├── cron/
│       │   └── weekly.sh
│       └── run_diag.py             # Entry point
└── admin/
    └── diagnostics.php             # UI dashboard
```

---

## 📊 DIAGNOSTIC_LOG — ИСТОРИЧЕСКИ ТРАКЕР

Нова DB таблица:

```sql
CREATE TABLE diagnostic_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_timestamp DATETIME NOT NULL,
    trigger_type ENUM('manual','cron_weekly','cron_monthly','module_commit','user_command'),
    module_name VARCHAR(60) NOT NULL,    -- 'insights', 'onboarding', etc.
    git_commit_sha VARCHAR(40),
    total_scenarios INT,
    passed INT,
    failed INT,
    skipped INT,
    category_a_pass_rate DECIMAL(5,2),
    category_b_pass_rate DECIMAL(5,2),
    category_c_pass_rate DECIMAL(5,2),
    category_d_pass_rate DECIMAL(5,2),
    failures_json JSON,       -- detailed FAIL reasons
    duration_seconds INT,
    notes TEXT,
    INDEX idx_module_time (module_name, run_timestamp)
) ENGINE=InnoDB;
```

---

## 📧 ALERT СИСТЕМА

**Тревожни сигнали** (email/Slack на Тихол):

🚨 **CRITICAL (веднага):** Category A или D < 100%  
⚠️ **WARNING (dnevno):** Drop в PASS rate > 5% между последователни runs  
ℹ️ **INFO (седмично):** Regular weekly report с тренд

---

## 🔍 ВИДОВЕ ПРОВЕРКИ (verification_type)

| Type | За какво |
|---|---|
| `product_in_items` | Конкретен продукт трябва/не трябва да е в `data_json.items[]` |
| `product_in_items` + `rank_within` | Продукт трябва да е в top-N |
| `pair_match` | Двойка продукти заедно (basket_driver) |
| `seller_match` | Конкретен user_id е flagged |
| `value_range` | Числова стойност в интервал (profit_growth %) |
| `exists_only` | Insight row трябва да съществува (не проверяваме съдържание) |
| `not_exists` | Insight row НЕ трябва да съществува |
| `count_match` | Точен брой items |

---

## 📝 ПРОТОКОЛ ПРИ НАМЕРЕН BUG

Когато diagnostic намери FAIL:

1. **Изолирай** — дай му уникален ID: `BUG-YYYYMMDD-<module>-<scenario>`
2. **Категоризирай** — A (критичен), B, C, D
3. **Репортни** — в `diagnostic_log.failures_json` + MASTER_COMPASS.md
4. **Поправи** — Category A/D → веднага; B → в следващата сесия; C → приоритизира Тихол
5. **Re-verify** — след fix пусни пак тази конкретна сценария
6. **Документирай** — добавай нов scenario ако bug-ът дойде от случай който не е бил покрит

---

## 🧪 TEST-DRIVEN DEVELOPMENT ПРОЦЕС

За всеки нов AI модул:

### Стъпка 1 — Дефиниция (Тихол)
Тихол описва "какво прави модулът" с обикновени думи.

### Стъпка 2 — Сценарии (Claude)
Claude превежда описанието в 10-30 сценария (6-те фундаментални въпроса).

### Стъпка 3 — Одобрение (Тихол)
Тихол категоризира A/B/C/D за всеки сценарий.

### Стъпка 4 — Oracle (Claude)
Claude пише `seed_oracle` фикстури — очаквани отговори.

### Стъпка 5 — SQL/PHP (Claude)
Claude имплементира истинските функции.

### Стъпка 6 — Verify
Пусни diagnostic → всички A+D PASS → commit.

**Без този ред — не се започва код писане.**

---

## 🛡 НЕПРИКОСНОВЕНИ ПРАВИЛА

1. **Никога не commit-вай код без diagnostic run** ако модулът има AI логика
2. **Никога не изтривай сценарий** — само маркирай като deprecated
3. **Никога не променяй Category A сценарий** — само добавяй нови
4. **Никога не пускай diagnostic на production tenant**
5. **Никога не игнорирай FAIL в A или D** — дори "да е за после"

---

## 📅 РАЗВИТИЕ НА ДОКУМЕНТА

| Версия | Дата | Промени |
|---|---|---|
| 1.0 | 2026-04-24 | Първоначална версия (S79) — 4 категории, 5 тригера, 8 verification types |

Следващи планирани версии:
- **v1.1** (след S80) — добавяне на cron setup, dashboard screenshots
- **v2.0** (при 5+ модула) — revision base на реален опит

---

## 🎓 КРАТЪК GUIDE (за бързо припомняне)

```
НОВ МОДУЛ?
  → Пиши сценарии ПРЕДИ кода
  → Category A+D = 100% PASS
  → Document в MASTER_COMPASS

ПОНЕДЕЛНИК 03:00?
  → Cron пуска автоматично
  → Email отчет на Тихол

АЛАРМА?
  → A<100% = rollback
  → D<100% = SQL bug
  → B/C FAIL = документирай, fix следваща сесия

СЪМНЕНИЕ?
  → "AI DIAG ПУСНИ"
  → Виж dashboard /admin/diagnostics.php
```

---

**КРАЙ НА DIAGNOSTIC_PROTOCOL.md v1.0**
