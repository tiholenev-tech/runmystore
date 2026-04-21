# 📘 DOC 14 — STRIPE + ПАРТНЬОРИ + ФИНАНСИ

## Subscription, Trial, Affiliate, Partnership

**Версия:** 1.0 | **Дата:** 21.04.2026 | **Част от:** Група 6

---

## 📑 СЪДЪРЖАНИЕ

1. Планове — FREE / START / PRO
2. 4-месечен Trial — капанът
3. Stripe Connect архитектура
4. ISR модел (не MLM)
5. Territory Partners
6. Affiliate програма
7. Monthly statements
8. Payment flow
9. Edge cases — refunds, downgrades
10. Unit economics
11. Lock-in
12. Legal защита

---

# 0. 🔒 ЗАКЛЮЧЕНИ РЕШЕНИЯ (CONSOLIDATION_HANDOFF, 18.04.2026)

Тези 9 решения НЕ СЕ ПРЕГОВАРЯТ. Заключени след многобройни дебати.

| # | Тема | Финално решение |
|---|---|---|
| К1 | Trial дължина | **4 месеца** — месец 1 PRO безплатно; месеци 2-4 PRO функции на START цена (€19); край на месец 4 = окончателен избор. Карта се иска на ден 29. Месец 7 = "капан" (3 месеца след избор). |
| К2 | Ghost pills | **OFF — премахнати изцяло**. Манипулативни. Заместител: **PRO седмица** — 2 седмици безплатен PRO на всеки 3-4 месеца за FREE/START. Макс 4 пъти годишно. |
| К3 | AI персона | **УПРАВИТЕЛ** (Пешо е ШЕФ). Професионално, не фамилиарно. Забранени императиви, забранени прогнози. |
| К4 | Simple Mode главен екран | **life-board.php замества simple.php**. Прогресивно разкриване: 1 въпрос → 6 въпроса. Постоянен nudge bar. Progress bar = `materialized / adjusted_total × 100`. |
| К5 | Onboarding progress | **ENUM в DB + Life Board етапи в UI**. `onboarding_status ENUM('new','in_progress','core_unlocked','operating')`. |
| К6 | Партньори | **FLAT терминология** (legal-safe ISR). Territory Partner 15% forever за регион + Referral Partner 50%×6 мес. Stripe Separate Charges + Transfers. |
| К7 | NARACHNIK файл | Остава **отделен** документ. Не влиза в BIBLE-тата. |
| В4 | DB spelling | `'canceled'` (едно L) навсякъде. |
| В5 | inventory_events schema | **event-sourced печели** (asserted_quantity, baseline_before_event, quantity_delta, event_type). Offline conflict resolution. |

---

> **⚠️ ТОЗИ ДОКУМЕНТ Е OVERVIEW.**
> За пълна Stripe спецификация виж **`STRIPE_CONNECT_AUTOMATION.md`**:
> - Ledger-First философия
> - Separate Charges and Transfers (не Destination)
> - Express onboarding, Payment flow детайли
> - Event queue + executePendingTransfers
> - Edge cases (refund, downgrade, chargeback), VAT
> - Monthly statements + Partner Dashboard
> - Implementation plan S-PAYMENTS-01..06

---

# 1. ПЛАНОВЕ — FREE / START / PRO

## 1.1 FREE
- 1 магазин
- 1 потребител (owner only)
- До 50 артикула
- Basic AI (ЧЗВ + 30 insights)
- **БЕЗ voice primary**
- БЕЗ proactive messages
- БЕЗ pills/signals
- БЕЗ vечерен отчет
- Daily AI limit: 20 заявки

**Цел:** try-before-buy. Пешо вижда че работи.

## 1.2 START — €19/месец
- 1 магазин
- До 3 потребителя (owner + 2 seller)
- Без лимит на артикули
- Full AI **инструменти** (voice, image studio, OCR)
- AI **мълчи** — не съветва, само изпълнява
- Без pills/signals
- Без evening wrap
- Daily AI limit: 50 заявки

**Философия:** AI е **джаджа**, не мозък.

## 1.3 PRO — €49/месец (€9.99/доп. магазин)
- Unlimited магазини (pay-per-store)
- Unlimited потребителя
- Всичко от START
- **AI е мозък:**
  - Pills и signals
  - Proactive recommendations
  - Evening wrap
  - Morning briefing
  - Lost demand analysis
  - Staff КПД
  - Basket analysis
  - Cross-store intelligence
- Daily AI limit: 200 + 50/доп. магазин

**Философия:** AI е **бизнес партньор**.

---

# 2. 4-МЕСЕЧЕН TRIAL — КАПАНЪТ

## 2.1 Стратегия

**Месец 1:** БЕЗПЛАТЕН с PRO ниво
- Регистрация само с телефон или email (БЕЗ кредитна карта)
- Промокод от счетоводител / агент / инфлуенсър / Viber referral
- Пълен PRO достъп
- AI трупа данни за бизнеса

**Месеци 2, 3, 4:** Плаща €19 (START цена), ползва PRO
- На 29-ия ден от месец 1 → иска карта
- €19 таксуване, но PRO функции
- AI свиква с Пешо, Пешо свиква с AI
- Складът расте — confidence от 20% към 70%+

**Месец 5:** ИЗБОРЪТ
```
Пешо, промоцията свърши.
Може да продължиш с PRO за €49/месец
или остани на START за €19/месец.

[PRO €49] [START €19]
```

Ако PRO €49 → **печелим** (target: 30-40% conversion).
Ако START €19 → AI мозъкът изчезва.

## 2.2 Призрачни карти (ghost pills) за downgrad-нали

Веднъж на ден, само при реален insight:

```
🔒 AI има препоръка за Nike 42... Отключи с PRO
🔒 AI откри нещо за клиент Мария... Отключи с PRO
```

Не spam. Максимум 1 на ден.

**Психология:** Пешо вижда че AI **знае** нещо. Но не му казва. Loss aversion → upgrade.

---

# 3. STRIPE CONNECT АРХИТЕКТУРА

## 3.1 Защо Connect

Имаме **partner revenue sharing**:
- RunMyStore (ние) → 70%
- Regional partner → 15%
- Head office → 5% override
- Sub-affiliate → 10%

Stripe Connect позволява **automated splits** без да минаваме през наш bank account.

## 3.2 Setup

```
┌─────────────────────────┐
│ RunMyStore Platform     │
└──────────┬──────────────┘
           │
    ┌──────┴──────┬──────────┬──────────┐
    ▼             ▼          ▼          ▼
┌────────┐   ┌────────┐ ┌────────┐ ┌────────┐
│Regional│   │Regional│ │Head    │ │Sub-aff │
│Bulgaria│   │Romania │ │Global  │ │Local   │
└────────┘   └────────┘ └────────┘ └────────┘
```

## 3.3 Separate Charges and Transfers

```php
$charge = \Stripe\Charge::create([
    'amount' => 4900,
    'currency' => 'eur',
    'source' => $token,
    'description' => 'RunMyStore PRO - April 2026',
]);

\Stripe\Transfer::create([
    'amount' => 735,
    'currency' => 'eur',
    'destination' => $regional_partner_account,
    'transfer_group' => $charge_id,
]);

\Stripe\Transfer::create([
    'amount' => 245,
    'currency' => 'eur',
    'destination' => $head_office_account,
    'transfer_group' => $charge_id,
]);
```

---

# 4. ISR МОДЕЛ (НЕ MLM)

**ISR** = Independent Sales Representative. Legal в EU.

## 4.1 Разлика с MLM

| MLM | ISR |
|---|---|
| Multi-level (A → B → C → D → E) | 2 levels max |
| Income from recruiting | Income from real sales |
| Pyramid schemes | Legal sales reps |
| Може да бъде забранено | Съответства на EU law |

## 4.2 Правила

- **Max 2 levels:** Regional partner → Sub-affiliate
- **No recruiting bonuses:** доход идва от реални продажби
- **Transparency:** всеки partner вижда commission structure
- **Contract:** legally reviewed за EU

## 4.3 Commission structure

Per new customer:
- **Regional partner:** 15% lifetime
- **Head office override:** 5% lifetime
- **Sub-affiliate:** 10% lifetime (за 12 месеца only)

**Total payout:** 30% in worst case.
**RunMyStore keeps:** 70% минимум.

---

# 5. TERRITORY PARTNERS

Bulgaria, Romania, Serbia, Greece — всеки с **territory partner**.

**Territory partner отговорности:**
- Onboarding на нови tenants
- Local marketing
- Customer support (first line)
- Translation / localization feedback

**Territory partner commission:** 15% от subscription revenue в territory.

Един territory = един partner. Exclusive rights.

---

# 6. AFFILIATE ПРОГРАМА

## 6.1 Разлика от Territory Partners

| Aspect | Affiliate | Territory Partner |
|---|---|---|
| Scope | Individual signup | Whole territory |
| Commission | 10% за 12м | 15% lifetime |
| Exclusive? | No | Yes |
| Contract | Simple T&C | Full contract |

## 6.2 Как работи

1. Affiliate регистрира → получава unique код
2. Споделя кода с приятели, колеги
3. Нов tenant използва кода → affiliate получава 10% commission за 12 месеца

---

# 7. MONTHLY STATEMENTS

Всеки 1-ви от месеца — automated statements.

## 7.1 За Tenant

```
RunMyStore — Резюме за април 2026

Магазин: ЕНИ Тихолов
План: PRO
Потребители: 3
Артикули: 487
Продажби: 340 (€12,640)

Плащане: €49 на 01.05.2026
Метод: Visa ending 4242
```

## 7.2 За Partner

```
RunMyStore Affiliates — April 2026

Активни tenants: 24
Нови този месец: 3

Комисионни:
• 24 × €49 × 15% = €176.40
• Override payments: €58.80
• Total: €235.20

Плащане: 10.05.2026
```

---

# 8. PAYMENT FLOW

## 8.1 Happy path

```
1-ви на месеца:
  → Stripe auto-charge subscription
  → Success webhook
  → Extend subscription 1 месец
  → Distribute commissions (async)
  → Email receipt
```

## 8.2 Failed charge

```
1-ви: charge failed
  ↓
2-ри: retry 1
  ↓
5-ти: retry 2 + email warning
  ↓
10-ти: retry 3 + SMS warning
  ↓
15-ти: suspend account (downgrade to FREE)
  ↓
30 дни grace → deletion warning
  ↓
60 дни: GDPR deletion
```

---

# 9. EDGE CASES

## 9.1 Refund

```php
function refund($sale_id, $reason) {
    \Stripe\Refund::create([
        'charge' => $sale['stripe_charge_id'],
        'amount' => $sale['amount'],
    ]);

    foreach ($sale['commissions'] as $c) {
        \Stripe\Transfer::createReversal($c['transfer_id']);
    }

    auditLog(null, 'subscription.refund', 'sale', $sale_id,
        ['reason' => $reason, 'amount' => $sale['amount']]);
}
```

## 9.2 Downgrade mid-cycle

- 15.04: Downgrade request
- Запази PRO до края на billing cycle (30.04)
- На 01.05: прехвърли на START, €19 charge

## 9.3 Upgrade mid-cycle

- 15.04: Upgrade request
- Immediately PRO features
- Prorated charge: €(49-19) × 15/30 = €15
- На 01.05: regular €49

---

# 10. UNIT ECONOMICS

## 10.1 Revenue per PRO tenant

```
Month 1 (Trial): €0
Months 2-4: €19 × 3 = €57
Months 5-12: €49 × 8 = €392 (if stays PRO)

Year 1 per tenant (PRO):
  = €0 + €57 + €392
  = €449 (estimated)
```

## 10.2 Costs per tenant

```
AI costs (Gemini): ~€0.45/month
Server cost: €0.10/month
Stripe fees: €0.30 + 1.4% = €1.00/month
Support: €1.50/month avg

Total cost: ~€3.00/month = €36/year
```

## 10.3 Margin

```
Revenue: €449
Cost: €36
Gross margin: €413 (92%)
```

**At 10,000 active PRO tenants:**
- Revenue: €4.49M/year
- Costs: €360K/year
- Gross profit: €4.13M/year

---

# 11. LOCK-IN

## 11.1 Soft lock-in (правилен)

- 4-месечен trial създава habit
- Data в системата — switch cost
- AI insights which are personalized
- Voice training за speech patterns

## 11.2 Export on demand

GDPR изисква data portability:
- Full CSV export
- API access за migration tools
- Support при export

---

# 12. LEGAL ЗАЩИТА

## 12.1 Общи условия

- Clear subscription terms
- Cancellation rights (14 дни cool-off)
- Refund policy
- Data processing agreement (GDPR)
- Limitation of liability (€1000 max)

## 12.2 Privacy Policy

- GDPR compliant
- Data retention: 7 години (tax requirement)
- User rights: access, rectify, delete, portability
- Cookies policy
- Third-party services disclosure

## 12.3 Legal review budget

- Initial setup: €5,000 (one-time, EU law firm)
- Annual review: €1,500
- Per market expansion: €2,000

---

**КРАЙ НА DOC 14**


---

## 2.5 Gotcha: "Призрачната карта" ефект (Kimi worry)

**Рискът:** Пешо въвежда карта на ден 29 → минава от месец 1 (PRO безплатно)
към месец 2-4 (PRO функции на START цена). На ден 120 (край на trial) →
ако избере START → губи PRO функции които е свикнал → **разочарование**.

**Mitigation стратегии:**

1. **Transparent countdown:** От ден 100 нататък, Life Board показва:
   > "Имаш 20 дни PRO. След това: START (€19) или PRO (€49)."

2. **Feature preview removal:** От ден 90, някои PRO функции започват да се
   показват с "ще бъде START функция". Пешо се адаптира постепенно.

3. **PRO седмица като retention:** На 3-4 месеца интервал, дава 2 седмици
   PRO функции безплатно на START/FREE tenants. Напомня какво им липсва.

4. **НЕ правим ghost pills** (заключено решение К2).

**Метрика за мониторинг:**
- `trial_end_to_downgrade_to_free_days` per tenant
- Ако > 30% от cohort-а downgrade в първите 14 дни след trial →
  signal че preview removal е болезнен, адаптираме.

**Не е рисково ако:**
- Ден 29 capture на карта е explicit (Пешо знае че ще плати)
- Feature differences са ясни от ден 1 в Plans screen
- Downgrade path е прост и не манипулативен
