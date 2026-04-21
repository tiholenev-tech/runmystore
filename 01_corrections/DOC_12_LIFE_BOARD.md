# 📘 DOC 12 — LIFE BOARD (SIMPLE MODE HOME)

## 857 AI теми, Selection Engine, Tonal Diversity

**Версия:** 1.0 | **Дата:** 21.04.2026 | **Част от:** Група 5: AI BRAIN

---

## 📑 СЪДЪРЖАНИЕ

1. Какво е Life Board
2. Selection Engine
3. 857 AI теми — 6 групи
4. Anti-Hallucination (7 слоя)
5. Tonal Diversity
6. Daily Rhythm
7. Evening Wrap
8. Multi-Role Visibility
9. Feedback & Actions
10. Engagement Tracking
11. Shadow Testing
12. Reorder Precision

---

# 1. КАКВО Е LIFE BOARD

Life Board е **сърцето на Simple Mode**. Това което Пешо вижда когато отваря AI чата.

**Не е dashboard.** Не е „dashboard със stats".

Е **3-5 карти с insights** — всеки от които казва:
- Число (какво се случва)
- Защо (причина)
- Какво да направиш (action)

Примери:

```
┌───────────────────────────┐
│ Nike Air Max 42           │
│ 60 дни без продажба       │
│ €180 замразени            │
│                           │
│ [Защо?] [Покажи] [AI реши]│
│ 👍 👎 🤔                  │
└───────────────────────────┘
```

---

# 2. SELECTION ENGINE

От 857 възможни теми → 3-5 се показват в определен момент.

## 2.1 Фактори

```php
function scoreInsight($insight, $user_context) {
    $score = 0;
    $score += $insight['financial_impact_eur'] / 10;

    $age_days = (time() - $insight['created_at']) / 86400;
    $score += max(0, 30 - $age_days);

    $past_action_rate = $insight['category_action_rate'];
    $score += $past_action_rate * 20;

    if (isSeasonalMatch($insight, $user_context['current_season'])) {
        $score += 15;
    }

    if ($insight['tone'] === $user_context['last_tone']) {
        $score -= 10;
    }

    if (insightShownRecently($insight['id'], $user_context['user_id'])) {
        $score -= 100;
    }

    if (!roleCanSee($user_context['role'], $insight['min_role'])) {
        $score = -999;
    }

    if (!planAtLeast($user_context['plan'], $insight['min_plan'])) {
        $score = -999;
    }

    return $score;
}
```

## 2.2 Selection

Top 5 с най-висок score показват се. `ai_shown` таблица track-ва:

```sql
CREATE TABLE ai_shown (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    insight_id BIGINT NOT NULL,
    shown_at DATETIME NOT NULL,
    INDEX idx_user_insight (user_id, insight_id)
);
```

## 2.3 1/4 тишина

```php
if (!shouldSpeakToday($user_id)) {
    return [];
}
```

25% от отварянията — Life Board е празен (с encouraging message: „Всичко върви добре. Няма нищо спешно.").

---

# 3. 857 AI ТЕМИ — 6 ГРУПИ

## 3.1 Group 1: Stock Management (234 теми)
Zombie, zero stock, size curve, category balance, seasonal out-of-stock, overstocking, supplier delays, slow movers.

## 3.2 Group 2: Revenue & Margin (187 теми)
Revenue trends, margin erosion, discount abuse, price elasticity, cross-sell opportunities, upsell patterns, bundle suggestions.

## 3.3 Group 3: Customer Behavior (156 теми)
Lost demand (client left without buy), VIP customer absence, customer returning patterns, demographic shifts, basket size trends.

## 3.4 Group 4: Staff Performance (95 теми)
Per-seller performance, margin killer detection, discount abuse by staff, time-of-day productivity, weekend vs weekday.

## 3.5 Group 5: Operations (128 теми)
Floor arrangement, register area, display quality, customer flow, peak hours, staffing gaps.

## 3.6 Group 6: Strategic (57 теми)
Competitor pricing, seasonal planning, new category opportunities, supplier diversification, cash flow warnings.

## 3.7 JSON catalog

`ai-topics-catalog.json` — 857 записа със структура:

```json
{
  "id": "zombie_001",
  "cat": "zombie",
  "group": "stock",
  "name": "Zombie artikul 60+ дни",
  "what": "{product_name} — {days} дни без продажба. €{frozen} замразени.",
  "when": "stock>0 AND days_since_last_sale>=60",
  "data": "products+sales",
  "type": "loss",
  "cc": "*",
  "roles": "owner,mgr",
  "plan": "pro",
  "p": 8,
  "module": "products",
  "tone": "serious",
  "fundamental_question": "anti_order"
}
```

---

# 4. ANTI-HALLUCINATION (7 СЛОЯ)

Всеки AI response преминава през 7 слоя защита:

## Layer 0: Template-first
80% от отговорите са template-based (PHP). AI не се вика.

## Layer 1: Structured PHP data
AI получава готов JSON с числа. Не прави аритметика.

## Layer 2: System prompt (8 слоя)
Identity, data, signals, confidence, time context, plan, memory, safety.

## Layer 3: Fact Verifier
Всеки AI output се проверява срещу source data. Вижте DOC 03.

## Layer 4: GlossaryGuardian
Санитизация на термини:
- „продажба" → „движение на наличности" (за BG)
- „ERP" → не използваме (не сме ERP)
- „cloud" → „облачна платформа"

## Layer 5: Red-line phrases
Забранени фрази:
- „препоръчвам да уволниш"
- „изтрий всичко"
- „спри магазина"

## Layer 6: Semantic sanity
Артикулите съществуват ли в DB? Цените разумни ли са? Клиентите реални ли?

## Layer 7: User confirmation (за destructive actions)
Преди delete/bulk-update → preview → confirm.

---

# 5. TONAL DIVERSITY

**Не всички insights са „сериозни"**. Mixed tones:

| Tone | Процент | Пример |
|---|---|---|
| Serious | 40% | „Nike 42 свърши. Губиш €420/седм" |
| Encouraging | 25% | „Браво! Passionata +35% — rekord" |
| Curious | 15% | „Защо петъчните клиенти +42%?" |
| Playful | 10% | „Zombie ловен: 12 артикула на 60+ дни" |
| Alarming | 5% | „⚠ Моминекс не е доставил от 45 дни" |
| Silent | 5% | *(празен Life Board)* |

Selection Engine следи `user_context['last_tone']` и избягва повторения.

---

# 6. DAILY RHYTHM

Различни insights според часа:

## 6.1 Сутрин (07:00-11:00)
- Yesterday's summary
- Today's plan
- Overnight alerts
- Weather impact (ако е relevant)

## 6.2 Обяд (11:00-15:00)
- Morning performance
- Top products today
- Staff performance
- Customer flow

## 6.3 Следобед (15:00-18:00)
- Peak hours data
- Real-time stock alerts
- Upsell opportunities

## 6.4 Вечер (18:00-22:00)
- Evening wrap (21:00 push)
- Tomorrow preparation
- Restock reminders
- Weekly patterns

## 6.5 Нощ (22:00-07:00)
- Silent (DND window)
- Exception: critical alerts only

---

# 7. EVENING WRAP

21:00 всеки ден — push notification.

```
🌙 Вечерен отчет — 22.04

Днес:
• €847 оборот (+12% vs вчера)
• 18 продажби
• Топ: Nike Air Max (4 бр)
• Проблем: 2 клиента без размер 38

Утре:
• Очаквай Моминекс доставка
• Петък — пик 14-17ч

👋
```

**3-5 реда. Пешо чете в леглото.** За първи път в 20 години вижда деня си в числа.

---

# 8. MULTI-ROLE VISIBILITY

Един и същ insight се показва различно per role:

## 8.1 Owner
```
Nike Air Max 42 — 60 дни без продажба.
€180 замразени. Обмисли намаление.
```

## 8.2 Manager
```
Nike Air Max 42 — 60 дни без движение.
3 броя заети на рафта.
```

## 8.3 Seller
*(не се показва — не е relevant)*

## 8.4 Evening wrap per role

**Owner:** *„Днес: €840 оборот, 12 продажби, peak 15:00. Топ: обувки."*
**Manager:** *„Днес: 12 продажби. Peak 15:00. Върнати: 2. Inventory alert: Nike 42."*
**Seller:** *„Твоята смяна: 8 продажби, €320. Добра работа!"*

---

# 9. FEEDBACK & ACTIONS

## 9.1 3 бутона под всеки insight

- **[Защо?]** → expand chat panel с обяснение
- **[Покажи ми]** → small modal с детайли (артикул snapshot)
- **[AI реши]** → AI предлага action, Пешо confirms

## 9.2 Feedback emoji

- 👍 (useful)
- 👎 (not useful)
- 🤔 (interesting but not sure)

Feedback → template health scoring:

```sql
CREATE TABLE insight_feedback (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    insight_id BIGINT NOT NULL,
    reaction ENUM('thumb_up','thumb_down','thinking') NOT NULL,
    created_at DATETIME NOT NULL
);
```

Templates с > 15% action rate → boost в selection.
Templates с > 30% thumb_down → demote или remove.

## 9.3 Post-action feedback loop

7 дни след action:

```
AI: Преди 7 дни намали Nike 42 на €48.
    Продадени: 2 броя. Работи ли?

    [Да] [Не] [Средно]
```

Учим от **резултати**, не само от clicks.

---

# 10. ENGAGEMENT TRACKING

Collect:
- Кои insights Пешо отваря (click rate)
- Кои actions изпълнява (action rate)
- Колко време гледа (time on insight)
- Кога се връща в приложението (retention)
- Кога „dismisses" (swipe away)

Обработка: дневни reports за Тихол. Template health scoring. A/B testing.

---

# 11. SHADOW TESTING

Преди да пуснем нов insight template публично → **shadow mode** 2 седмици:

1. AI генерира insight
2. Save в `ai_shadow_log`, НЕ показва на user
3. Тихол разглежда shadow log
4. Ако >90% insights са accurate → promote на production
5. Ако <90% → rewrite template

```sql
CREATE TABLE ai_shadow_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    template_id VARCHAR(50) NOT NULL,
    tenant_id INT NOT NULL,
    generated_text TEXT NOT NULL,
    would_show TINYINT DEFAULT 0,
    tihol_reviewed TINYINT DEFAULT 0,
    tihol_verdict ENUM('approve','reject','rewrite') NULL,
    created_at DATETIME NOT NULL
);
```

---

# 12. REORDER PRECISION

За insight „предложи поръчка" — AI е особено прецизен. Overstock или understock = реални пари.

## 12.1 Formula

```php
function suggestReorderQty($product) {
    $daily_sales = $product['avg_daily_sales_30d'];
    $lead_time_days = $product['supplier_lead_time_days'] ?? 14;
    $safety_stock = $daily_sales * 7;
    $target_stock = ($daily_sales * $lead_time_days) + $safety_stock;
    $current_stock = $product['quantity'];

    return max(0, ceil($target_stock - $current_stock));
}
```

## 12.2 Диапазон

Not „поръчай 23". А „поръчай 20-25".

Пешо обича **рамки**, не exact numbers. Рамката оставя място за преценка.

---

**КРАЙ НА DOC 12**
