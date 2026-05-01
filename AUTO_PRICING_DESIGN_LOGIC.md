# 🎯 AUTO-PRICING — DESIGN LOGIC

**Версия:** 1.0
**Дата:** 29.04.2026 (S88E шеф-чат)
**Автор:** Тихол + Claude (синтез от delivery архитектурната сесия)
**Статус:** Архитектурна спецификация. Готов за имплементационен chat.
**Свързани документи:** DELIVERY_ORDERS_DECISIONS_FINAL.md (sec C), SIMPLE_MODE_BIBLE.md v1.3, DESIGN_LAW.md, BIBLE_v3_0_TECH.md

---

## 1. MISSION & PHILOSOPHY

### 1.1 Концепция в едно изречение

**Пешо никога не въвежда продажна цена. Системата сама я предлага по неговия стил, той само одобрява.**

### 1.2 Защо това е ревулюционно

Никой друг POS / inventory продукт не прави това. Стандартът:
- Owner ръчно въвежда retail цена за всеки нов артикул
- Магазин с 500 нови артикула месечно = 500 решения за цена
- Всяко решение = "колко?" + "пиша 8 или 8.90?" + ментална математика

С RunMyStore.ai:
- Пешо снима фактура → AI парсва doostavната цена
- AI предлага retail цена според стила на Пешо (per-category multiplier + ending preference)
- Пешо tap-ва "Прие" → 50 цени готови за секунди
- Цел: 95% acceptance rate без промяна

**Това е разликата между продукт който Пешо ползва, и продукт без който не може.**

### 1.3 Marketing фраза (positioning)

> „Не въвеждаш цени. Те ги намират."

### 1.4 Връзка със ЗАКОН №1 (Пешо не пише)

Auto-pricing е директно приложение на ЗАКОН №1 — без него Пешо би трябвало да пише цена 50 пъти на доставка. С auto-pricing → 50 пъти tap [Прие].

---

## 2. PHASE PLAN — КЪДЕ СЕ ИМПЛЕМЕНТИРА

**Phase 1 — DELIVERY МОДУЛ (S91 или по-късно):**
Auto-pricing се имплементира ПЪРВО в delivery review screen. Това е revolutionary moment-ът — 50 артикула влизат в магазина с auto-цени за секунди. Magic = visible.

**Phase 2 — products.php "Като предния" wizard (S92+):**
След като работи в delivery → копираме същата логика в wizard. Tap "Като предния" → cost се копира, retail се **REPLACES** с AI suggestion.

**Phase 3 — Sales velocity learning (post-beta, S100+):**
Cron daily job анализира продажби и обновява patterns. Не е критично за beta launch.

**Phase 4 — Bulk repricing (BIZ план only):**
Detailed Mode UI за Митко да обновява цени масово при категория промяна.

---

## 3. COLD START FLOW

### 3.1 Първа доставка onboarding (30 секунди)

При първа доставка на нов tenant — модал преди review screen:

**Въпрос 1 — multiplier:**
```
„Първа доставка. Каква ти е наценката?"

🎤 Кажи или избери:
[×1.5]  [×1.8]  [×2]  [×2.5]  [×3]  [Друго]
```

**Въпрос 2 — ending pattern:**
```
„Кръгли ли ги цените?"

[X.90]  [X.99]  [X.50]  [Точни]
```

### 3.2 Защо точно тези въпроси

- Multiplier = baseline. Pattern after това се само-калибрира per category.
- Ending = културно решение (BG: .90 премиум, .99 дискаунт, .50 хранителни)
- 30 секунди = ниска бариера за nehoн

### 3.3 Cold start fallback под threshold

Ако магазин има < 5 артикула на първа доставка → patterns са unstable. AI казва:
```
"Имам нужда от повече примери да науча твоя стил.
След 20 артикула ще предложа цени автоматично."
```

Първите 20 артикула — Пешо въвежда manual (или AI suggest-ва от global default 2× с .90).

### 3.4 Onboarding skip

Ако Пешо tap-ва "после":
- AI ползва globaln default (2× multiplier, .90 ending)
- Pattern се учи 100% от ръчни корекции
- След 20 corrections → стабилен pattern

---

## 4. AUTO-LEARNING FROM 3 ИЗТОЧНИКА

### 4.1 Източник 1 — Onboarding

Стартова baseline. Sample_count започва от 1. Confidence = 0.5 (баса knowledge).

### 4.2 Източник 2 — Manual corrections

Ако Пешо смени €5.90 на €6.50 за бельо → AI разбира:
- За category=бельо multiplier е по-висок от текущия baseline
- Update `pricing_patterns.avg_multiplier` per category
- INSERT в `price_change_log` с reason='manual_edit'
- Sample_count++, confidence се обновява (виж 4.5)

**Weighted learning:** новите примери имат тегло 1, старите avg-ва се запазва. Avg_multiplier_new = (avg_multiplier_old × sample_count + new_multiplier) / (sample_count + 1).

### 4.3 Източник 3 — Sales velocity feedback

**Cron daily job** анализира продажби last 30 дни:

- Продукт с предложена €5.90 → 30 дни нула продажби → надценен → AI намалява multiplier за category с малко (0.05× стъпка)
- Продукт с предложена €5.90 → пламва за 3 дни → подценен → AI увеличава multiplier (но flag-ва "проверете цената на bestseller-ите ви")
- Праг: продукт трябва > 30 дни в магазина за velocity sample (recent имат по-малко тегло)

### 4.4 Per-category patterns

**Не глобален multiplier.** Различни категории имат различни patterns:

| Категория | Multiplier | Ending | Confidence after 30д |
|---|---|---|---|
| Бельо | 2.5× | .99 | 0.92 |
| Чорапи | 1.8× | .50 | 0.88 |
| Тениски | 2.5× | .90 | 0.95 |
| Бижута евтини | 3× | точни | 0.85 |
| Бижута скъпи | 1.8× | .00 | 0.78 |

AI учи всяка категория самостоятелно.

### 4.5 Confidence scoring

Confidence се изчислява на база:
- Sample_count (брой наблюдения за категорията)
- Variance на multiplier-ите (ако примерите са разпръснати → ниска confidence)
- Recency (последен пример < 7 дни → +0.05; > 90 дни → -0.10)

Формула:
```
confidence = min(1.0, 
    0.3                                          # baseline
  + 0.4 * tanh(sample_count / 20)                # sample richness  
  + 0.3 * (1 - variance / max_variance)          # consistency
  + recency_bonus
)
```

---

## 5. CONFIDENCE ROUTING (LAW №8)

При всяко auto-предложение:

| Confidence | Action | UX |
|---|---|---|
| > 0.85 | Auto-apply | Toast „✓ €5.90 (×2 + .90)" |
| 0.5 - 0.85 | Confirm dialog | „AI препоръчва €5.90. Да? [Прие] [Друга цена]" |
| < 0.5 | Manual entry | „Нова категория, въведи цена" |

### 5.1 Bestseller protection (override)

**Продукт с > 5 продажби/седмица последните 4 седмици → ВИНАГИ confirm dialog. Никога auto-apply.**

Защото:
- Bestseller има стабилна продажна цена → промяната е рискована
- Ако AI auto-update вдига от €6 на €7 → продажбите може да паднат
- Митко трябва да се одобри ръчно

UI: bestseller има ⭐ icon вдясно от item-а в review screen. Tap → "Това е bestseller (47 продажби/месец). Промяна на цена? [Да] [Не]"

### 5.2 Cost variance integration

Marina вдига cost от €3 на €3.50:

| Variance | Action |
|---|---|
| < 10% | Тих insight в life-board, никакъв action |
| 10-20% + confidence > 0.85 | Auto-update retail (€6 → €7) + toast |
| > 20% | ВИНАГИ confirm dialog |
| Bestseller | ВИНАГИ confirm dialog (override-ва variance threshold) |

Toast: „Marina вдигна цените 16%. Промених твоята продажна от €6 на €7 за същия марж."

---

## 6. UX FLOW В DELIVERY REVIEW SCREEN (PHASE 1)

### 6.1 Per-row pricing UI

```
┌────────────────────────────────────────┐
│ ✅ Потници м.12 · 50 бр · cost €3.00   │
│    AI препоръчва: €5.90                │
│    (×2 + .90, confidence 92%)          │
│    [Прие]   [Друга цена]               │
└────────────────────────────────────────┘
```

- Зелена рамка = > 0.85 confidence (auto-apply)
- Жълта рамка = 0.5-0.85 (confirm)
- Червена рамка = < 0.5 (manual)
- ⭐ ico = bestseller protection
- ⚠ ico = cost variance > 20% (визуален warning)

### 6.2 Bulk approve

```
[✓ Прие всички зелени] - активен ако >= 1 ред с confidence > 0.85
```

Tap → всички зелени retail цени applied + toast „47 цени готови".

### 6.3 Single row tap

Tap на ред → bottom sheet:
- Edit retail (numpad)
- Edit cost (rare, ако OCR е грешен)
- Quick Add ако непознат продукт
- AI обяснява: „Защо €5.90? × 2 от cost €3.00 за бельо твоят стандарт"

### 6.4 Voice override

Пешо казва voice: "не, направи го 6.50" → triggers learnFromCorrection() → pattern update.

### 6.5 Empty state — нова категория

```
"Нова категория: Чанти. Въведи първа цена → ще науча стила."
[manual numpad]
```

След 5 случая → AI стартира предлагане.

---

## 7. UX FLOW В PRODUCTS WIZARD "КАТО ПРЕДНИЯ" (PHASE 2)

### 7.1 След tap "Като предния"

В copied-card collapsible:
- cost = от source-product
- retail = AI suggestion (НЕ от source-product) — current pattern за category
- AI preview под полето: „AI препоръчва €5.90 (×2 + .90)"
- Confidence indicator (green/yellow/red)

### 7.2 Override-ване

Tap на retail → numpad → ако стойността различава от AI suggest → triggers learnFromCorrection() при save.

### 7.3 Защо не директен copy от source

Source-product retail може да е стара или manually edited. AI suggestion е винаги fresh based on текущ pattern. Source.retail може да show as reference хover.

---

## 8. DB SCHEMA (вече в S88D)

### 8.1 pricing_patterns (вече create-нaта)

```sql
CREATE TABLE pricing_patterns (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  store_id INT NOT NULL,
  category_id INT NOT NULL,
  avg_multiplier DECIMAL(5,3) DEFAULT 2.0,
  ending_preference ENUM('point_90','point_99','point_50','exact','round_50') DEFAULT 'point_90',
  rounding_direction ENUM('down','nearest','up') DEFAULT 'nearest',
  sample_count INT DEFAULT 0,
  confidence_score DECIMAL(3,2) DEFAULT 0.50,
  learning_source ENUM('onboarding','manual_correction','sales_velocity','admin_override') DEFAULT 'onboarding',
  variance_score DECIMAL(4,3) DEFAULT 0.000,
  last_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (tenant_id, store_id, category_id),
  INDEX (tenant_id, last_updated_at)
);
```

### 8.2 price_change_log (вече create-нaта)

```sql
CREATE TABLE price_change_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  product_id INT NOT NULL,
  old_price DECIMAL(10,2),
  new_price DECIMAL(10,2),
  reason ENUM('cost_variance_auto','manual_edit','bulk_update','onboarding','sales_velocity_correction','first_delivery_auto') NOT NULL,
  confidence_score DECIMAL(3,2),
  delivery_id INT NULL,
  user_id INT,
  ai_suggested_price DECIMAL(10,2) NULL,
  was_overridden TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (tenant_id, product_id, created_at),
  INDEX (delivery_id),
  FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE SET NULL
);
```

---

## 9. PHP SERVICE ARCHITECTURE

### 9.1 services/pricing-engine.php (нов файл)

**Public API:**

```php
// Главен endpoint за prediction
predictRetailPrice($cost, $category_id, $tenant_id, $store_id) 
  → returns ['suggestion' => 5.90, 'confidence' => 0.92, 'explanation' => '×2 + .90 за бельо']

// Learning от корекции
learnFromCorrection($product_id, $old_suggestion, $actual_price, $context)
  → updates pricing_patterns + INSERT в price_change_log

// Daily cron — sales velocity feedback
learnFromVelocity($tenant_id) 
  → analyses last 30d sales, updates patterns

// Bulk approve в review screen
applyBulkPricing($delivery_id, $row_ids[]) 
  → applies AI suggestions, returns count

// Cost variance auto-update
handleCostVariance($product_id, $new_cost, $old_cost) 
  → returns ['action' => 'auto_update' | 'confirm' | 'tih_insight', 'new_retail' => 7.00]

// Bestseller check
isBestseller($product_id) 
  → returns true if > 5 sales/week last 4 weeks

// Cold start helper
saveOnboardingPattern($tenant_id, $multiplier, $ending) 
  → seeds pricing_patterns за всички съществуващи категории
```

### 9.2 Internal helpers

```php
calculateMultiplier($cost, $retail) → 1.967
applyEnding($price, $ending_preference) → rounds 5.967 to 5.99 / 5.90 / 6.00
computeConfidence($sample_count, $variance, $recency_days) → 0.85
detectCategory($product_name, $supplier_id) → ID (използва pattern matching от $BIZ_VARIANTS)
```

### 9.3 Cron job

`tools/cron/pricing_velocity_daily.php` — runs 03:00 daily:
1. За всеки tenant с активен PRO/BIZ план
2. За всяка category в pricing_patterns
3. Анализирай sales last 30d на products в тази category
4. Изчисли actual avg sale price vs предложена
5. Update avg_multiplier с малки стъпки (0.05×)
6. Log в /var/log/runmystore/pricing_velocity.log

---

## 10. AI BRAIN INTEGRATION

### 10.1 buildPricingContext($tenant_id, $category_id)

Helper в build-prompt.php — дава на AI цялата информация за pricing разговори:

```json
{
  "category": "бельо",
  "current_pattern": {
    "multiplier": 2.5,
    "ending": ".99",
    "confidence": 0.92
  },
  "last_5_corrections": [...],
  "velocity_feedback": "30d avg: -3% от предложение",
  "bestseller_count": 12,
  "recent_changes": [...]
}
```

### 10.2 Chat въпроси които AI отговаря

- „Защо предлагаш €6.90?" → „×2 от cost €3.45 + ending .90, за бельо твоят стандарт"
- „Промени всички цени с +10%" → bulk update през Detailed Mode confirmation
- „Marina вдигна цените, какво да правя?" → списък с засегнати продукти + recommendations
- „Кои са bestseller-ите ми?" → lista
- „Колко съм заработил тази седмица?" → standard sales reporting (separate)

### 10.3 Proactive insights

AI Brain queue items генерирани от pricing-engine:

- `price_change_pending_review` — variance > 20%, чака owner approval
- `bestseller_under_priced` — продукт с високо velocity → vidicum suggesting raise
- `pattern_drift_detected` — recent corrections отдалечават от текущ pattern → confirm
- `category_unstable` — много corrections in short period → нужна Митко интервенция

---

## 11. RISK ANALYSIS (5 RISK-А)

### 11.1 Cold start под-data за специфични магазини

**Risk:** Магазин с 5 артикула на първа доставка → patterns са unstable.

**Митигация:** Threshold N=20. Под него → не auto-suggest, manual entry. AI казва „Имам нужда от 15 повече примери".

### 11.2 Sales velocity bias на запас

**Risk:** Ако всички продукти зациклят защото магазинът е празен (Пешо отваря 1ч/ден лятото) → AI понижава всички цени излишно.

**Митигация:** Corrective layer — ако > 80% от продукти zацикли → flagva се „business issue, не pricing". Cron skip-ва velocity update този период. Pattern-ите се запазват.

### 11.3 Бижута variance

**Risk:** Бижута имат огромен variance (евтини pendant-и €5 vs скъпи пръстени €500). Един multiplier за цялата category е грешен.

**Митигация:** Per-cost-bracket sub-patterns. Ако category има variance > 0.5 → автоматично се split-ва на sub-categories per cost bracket (€0-€20, €20-€100, €100+).

### 11.4 Ending pattern .99 vs .90 културна разлика

**Risk:** В BG: .99 = дискаунт усет (Lidl style), .90 = премиум усет (boutique style). AI не знае контекста на магазина.

**Митигация:**
- Onboarding question е критичен — Пешо трябва да реши съзнателно
- Settings page → owner може да променя на ниво tenant
- AI не променя ending без explicit instruction

### 11.5 Tenant data leakage

**Risk:** Pattern learning trябва strictly per tenant_id. Никакво cross-tenant learning.

**Митигация:**
- DB schema има tenant_id колона ✅
- pricing-engine.php ВСЯКА query задължително WHERE tenant_id = ?
- Code review правило в PR template
- Diagnostic Cat F (нов) — verify isolation

---

## 12. KPI METRICS

След beta launch:

| KPI | Цел | Измерване |
|---|---|---|
| Acceptance rate | 90%+ | % случаи Пешо приема предложена цена без промяна (track в price_change_log) |
| Pattern stability | < 5% промени/месец | След 30 дни pattern не drift-ва значително |
| Time to price | < 2 секунди средно | От ddoacceptance на cost до commit на retail |
| Bestseller protection trigger | 100% за bestsellers | confirm dialog винаги се появява |
| Cost variance auto-update success | 80%+ | % cases където auto-update не прави следваща корекция |

---

## 13. PRICING PLANS DIFFERENTIATION

| Plan | Auto-pricing функция |
|---|---|
| **FREE** | Само показва cost, не предлага retail |
| **START (€19)** | Базов auto-pricing с глобален pattern (един multiplier за всички categories) |
| **PRO (€49)** | Per-category learning + sales velocity feedback + cost variance auto-update + bestseller protection |
| **BIZ (€109)** | Всичко + bulk repricing + cross-store comparison + admin overrides |

**Auto-pricing е единствената причина клиент да upgrade-не от START на PRO.**

---

## 14. EDGE CASES

### 14.1 Нов продукт без category
AI пита Пешо при quick_add: „В коя категория е?" → след това AI прилага pattern.

### 14.2 Multi-supplier същ продукт
Cost varies между доставки (Marina €3, друг supplier €3.20). Pattern се прилага на текущия cost от текущата доставка, не на product-level avg.

### 14.3 Bonus / мостри (cost = 0)
AI suggestion = AI use последен retail на продукта (ако съществува), или skip prediction (manual entry за bonus).

### 14.4 Manual cost entry (без OCR)
Същият flow — AI pred-ва retail според cost. Cost source не влияе на logic.

### 14.5 Бижута цени точни (без ending)
Onboarding answer "точни" → ending_preference='exact' → no rounding applied.

### 14.6 Confidence пада след много corrections
Ако Пешо коригира 10 пъти подред → variance расте → confidence < 0.5 → AI спира да предлага → moderate колко manual entries → когато variance се успокои → confidence се вдига отново.

---

## 15. IMPLEMENTATION PHASES (РЕЗЮМЕ)

| Phase | Какво | Когато | Estimate |
|---|---|---|---|
| **0** | Schema (pricing_patterns + price_change_log) | ✅ DONE (S88D commit `30b6518`) | — |
| **1** | pricing-engine.php + cold start onboarding + delivery review screen integration | Когато стартира delivery модул (S91+) | 6-8h |
| **2** | products.php "Като предния" wizard integration | След Phase 1 verified | 1-2h |
| **3** | Sales velocity cron daily job | Post-beta (S100+) | 3h |
| **4** | Bulk repricing UI (Detailed Mode, BIZ plan) | Post-launch | 4h |
| **5** | AI chat integration (buildPricingContext) | Паралелно с Phase 1-2 | 2h |

**Total остава: ~16-19 часа имплементация след днешния schema commit.**

---

## 16. OPEN QUESTIONS (зa бъдещи sessions)

**Q1.** Първа доставка onboarding — модал или wizard стъпка?
- Препоръка: модал, който се показва ПРЕДИ review screen, само веднъж. След това settings page за update.

**Q2.** Confidence threshold за bulk approve — 0.85 ОК?
- Може да тестваме с 0.80 за beta, после tune на real data.

**Q3.** Bestseller threshold — > 5/седмица × 4 седмици = 20+ продажби/месец. Нисък ли е?
- За малки магазини (ENI) може да е твърде висок. Дискуттаме при beta launch.

**Q4.** Какво ако category няма pattern (нова) — fallback на global default?
- Препоръка: yes. Global default = onboarding answer × 1.0.

**Q5.** Sales velocity learning rate — 0.05× стъпка добре ли е?
- За beta — fixed. После може dynamic adjustment.

---

## 17. SELF-CRITIQUE (60% pro / 40% con)

### 17.1 Какво е силно (60%)

1. **Решава real pain point** — owner-ите мразят да въвеждат цени
2. **Per-category learning** е архитектурно правилно (не глобален multiplier)
3. **Bestseller protection** елиминира най-голямата risk
4. **Cost variance integration** прави система proactive
5. **Voice override → learning** = self-improving
6. **Marketing фразата** е силна за beta продажби

### 17.2 Какво е слабо (40%)

1. **Cold start cliff** — първите 20 артикула нямат intelligence. Manual entry е grant. Защо да платя €49 за функция която не работи първите 2 седмици?
2. **Pattern stability през сезонност** — лятна разпродажба → multiplier пада → есенно завръщане → trябва да се вдигне обратно. Cron-ът не разбира seasons.
3. **Supplier-side variance не се хваща директно** — ако Marina има 3 различни цени за същ продукт между доставки, pattern се counfounds. Нужно е product-level history, не само category-level.
4. **Bulk repricing risk** — owner с 1000 артикула + button "+10%" → грешка → 1000 продукта с грешна цена. Audit trail задължителен (price_change_log) но recovery е bulk операция.
5. **Velocity bias на запас** — risk описан в 11.2. Без external signals (weather, holidays, marketing campaigns) AI не може да отдели "lo.w sales = wrong price" от "low sales = closed shop".
6. **No A/B testing built-in** — не можем да тестваме два multipliers на същ продукт. Owner просто verifies via gut feeling. Future enhancement.

---

## 18. ВЕРСИЯ HISTORY

| Версия | Дата | Промени | Автор |
|---|---|---|---|
| 1.0 | 29.04.2026 | Първа версия. Извлечена от delivery архитектурната сесия (DELIVERY_ORDERS_DECISIONS_FINAL sec C). Phase 1 = delivery, Phase 2 = wizard. 5 risk-а с митигации. | Тихол + Claude шеф-чат |

---

**КРАЙ НА AUTO_PRICING_DESIGN_LOGIC.md v1.0**

Готов за следващия имплементационен chat (когато стартира delivery модул).
