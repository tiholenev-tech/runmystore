# MARKETING_BIBLE_LOGIC_v1.md

# RunMyStore.ai — Marketing AI & Online Store Module
## Логическа Библия v1.0 — Стратегия и Философия

**Дата на финализиране:** 03 май 2026
**Статус:** APPROVED за добавяне в roadmap
**Owner:** Тихол Енев
**Reviewer:** Шеф (за добавяне в график)
**Приложен документ:** MARKETING_BIBLE_TECHNICAL_v1.md

---

## СЪДЪРЖАНИЕ

1. Executive Summary
2. Откритието което променя всичко
3. Стратегическа философия
4. Marketing AI Module — какво е
5. Online Store Module — какво е
6. 6-те Промпт-Маркетолога
7. Партньори (избор и обосновка)
8. Pricing Strategy (пожелателно)
9. Multi-store Inventory Routing
10. Risk Register
11. Beta Plan & Timeline
12. 5-те AI мнения — синтеза
13. Какво **не** правим (изрично отхвърлено)
14. Success Metrics

---

# 1. EXECUTIVE SUMMARY

## Какво строим

Два нови модула към RunMyStore.ai които **превръщат продукта от ROI tool в category-defining platform** за SMB retail в EU:

### Marketing AI Module
AI който познава склада + клиентите + lost demand на магазина и **взима маркетинг решения вместо собственика**. Работи над Meta Advantage+, TikTok Symphony, Google PMax — не дублира тяхната работа, а **диригентира** какво, кога и колко да се рекламира.

### Online Store Module
Един бутон → готов онлайн магазин за 5-10 минути. Пешо/Митко не настройва нищо сам. Multi-store inventory sync с физическите магазини. Партньор: **Ecwid by Lightspeed**.

## Защо тези два модула заедно

Те са **interdependent**:
- Marketing AI без онлайн магазин = губим 40-60% от reach (online clicks → no destination)
- Онлайн магазин без Marketing AI = просто витрина без трафик
- **Заедно** = пълен закономен loop: AI вижда болката → пуска реклама → купувачът отива онлайн → продажбата затваря loop в нашия inventory

## Финансово влияние

При правилно изпълнение:
- ARPU расте 2.4-4× (от €19-49 само на core до €100-200 с Marketing AI)
- Total addressable revenue Year 5: €14-17M (срещу текущата проекция €4-6M)
- Marketing AI margin: 78-94%

## Ключово стратегическо прозрение

> **„Не сме инструмент за пускане на реклами. Не дублираме Meta. Ние сме AI маркетолог който знае склада ти по-добре от всеки външен маркетолог."**

Никой друг (Shopify, Square, Lightspeed) не свързва inventory + loyalty + ad execution + multi-store routing в едно. Имаме 18-24 месечен timing window преди enterprise player да го направи.

---

# 2. ОТКРИТИЕТО КОЕТО ПРОМЕНЯ ВСИЧКО

## Какво научихме (май 2026)

### Meta MCP е публичен
Meta Business вече предлага официален MCP (Model Context Protocol) connector. Claude (и всеки AI) може да управлява **пълни ad accounts** през API — създаване кампании, ad sets, ads, targeting, бюджет, reporting.

### TikTok Symphony Creative Studio безплатен
TikTok прави AI видеа от снимки за под 1 минута. Безплатно с TikTok for Business. Image-to-video, text-to-video, digital avatars, AI dubbing на 15+ езика.

### Google Performance Max e end-to-end
URL + бюджет → Google AI прави всичко (creative, audience, placement, optimization).

### Защо това променя стратегията
**Преди:** Marketing модулът беше Phase 6/7 (Year 3-5). Custom integrations с Meta/TikTok/Google = 6+ месеца разработка на канал.

**Сега:** MCP сървърите елиминират 80% от тази работа. Нашата задача става **тънка обвивка** над тяхната AI.

**Резултат:** Marketing AI може да слезе от Phase 6 (2028) към **Phase 2-3 (Q4 2026 - Q1 2027)**. 12+ месеца по-рано.

---

# 3. СТРАТЕГИЧЕСКА ФИЛОСОФИЯ

## Финално позициониране

**„Inventory-aware revenue engine"**

> *„Единствената система която пуска реклами САМО когато има какво да продаде и на кого, и спира преди да изгориш бюджета."*

## 3-те слоя на стойността

### Слой 1: ПОЗНАНИЕ (нашата уникална данна)
Никой друг няма:
- Какво стои на склада 30+ дни (zombie стока)
- Какво се продаде вчера/седмица/месец
- Кой клиент не е идвал 60+ дни (dormant)
- Margin на всеки артикул
- Lost demand (търсения за изчерпани размери)
- Top sellers + bottom sellers
- Витрина performance (продажби в първи час)

### Слой 2: РЕШЕНИЕ (нашия moat)
6 специализирани AI агента, всеки фокусиран върху:
- Какво да рекламираме
- Как да го кажем
- Колко и къде
- Кои клиенти да върнем
- Дали работи / защо не
- Как доказваме ROI

### Слой 3: ИЗПЪЛНЕНИЕ (платформите го правят)
Meta Advantage+ / TikTok Symphony / Google PMax поемат creative + targeting + bidding. Ние **не строим нищо специфично** — само превеждаме решения на API calls.

## Принципът

**Не правим това което Meta вече прави добре.** Правим това което **никой друг не може да направи** — свързваме бизнес контекста на магазина с маркетинг изпълнението.

## Service-as-a-Software философия (от Gemini)

SaaS пише „abonament за софтуер".
Service-as-a-Software пише **„заплата за служител"**.

Marketing AI не е tool. Това е виртуален маркетинг служител който:
- Работи 24/7
- Никога не спи
- Знае всичко за магазина
- Не пита глупави въпроси
- Носи реални пари

---

# 4. MARKETING AI MODULE — КАКВО Е

## Конкретен пример (понеделник 9:00 ч)

> AI пише на Митко:
>
> **„Митко, тази седмица имаш 3 решения за маркетинг:**
>
> **1.** Бели рокли модел S-1294 — 12 чифта, 35 дни без продажба, замразени €480. **Стратегът ми казва:** TikTok Symphony видео + €25 бюджет → 70% шанс за разпродажба за 7 дни. Одобрявам ли?
>
> **2.** Nike 42 свърши вчера. Lost demand: 4 търсения за седмица. **Стратегът:** ⚠️ НЕ рекламираме нищо Nike тази седмица. Първо поръчай.
>
> **3.** Мария (VIP, не идвала 67 дни). **Recovery експертът:** SMS + Instagram retargeting +€8 бюджет. Видяла е 4 нови артикули last visit. Одобрявам ли?"**

Това е **истинска стойност** която не съществува никъде другаде.

## 8-слойна архитектура (логически)

**Слой 0: Inventory Accuracy Gate**
Без 95%+ POS точност за 30 дни — Marketing AI **не се активира**.

**Слой 1: Knowledge Layer**
PHP скрипт събира за всяко решение: inventory + клиенти + lost demand + margin + sales history.

**Слой 2: Cost-Control Routing**
80% от решенията: PHP правила + Gemini Flash (евтино).
20%: Claude Sonnet за стратегически.
0.5%: Opus само 2-5 пъти/месец.

**Слой 3: 6-те Агента (виж секция 6)**

**Слой 4: Sandbox Simulation**
Преди реално пускане → projection: „Ако дадем €25 → expected outcome: X продажби, Y приход".

**Слой 5: Approval Workflow (Tinder UX)**
- Зелено (<€10): auto-execute с opt-out
- Жълто (€10-50): one-tap Tinder approval
- Червено (>€50): explicit преглед + voice feedback

**Слой 6: Изпълнение**
Meta MCP + TikTok MCP + Google MCP. Ние не строим creative engines — те го правят.

**Слой 7: Attribution**
Promo codes (auto-generated), QR кодове, voice prompt при checkout, loyalty match.

**Слой 8: Learning Loop**
Override logging → training data. Teaching moments при fail. Cross-tenant federated learning.

## Какво AI **НЕ** прави

- Не пише creatives ръчно (Symphony/Advantage+ го правят)
- Не таргетира audiences ръчно (Smart+/Advantage+ ги правят)
- Не управлява bidding (платформите го правят)
- Не пуска реклами без одобрение (освен зелени <€10)
- Не препоръчва без attribution (всичко measurable)
- Не работи без active loyalty модул (за attribution)

## Какво AI **ДА** прави

- Решава **какво** да рекламира (inventory awareness)
- Решава **кой** канал (routing logic)
- Решава **колко** (budget optimization)
- Решава **кога** (timing — никога Nike ако е out of stock)
- Решава **кога да спре** (kill switch)
- Превежда резултати на български човешки език
- Генерира teaching moments при провал

---

# 5. ONLINE STORE MODULE — КАКВО Е

## Проблемът който решава

Пешо/Григоре/Зорба:
- Не може да си направи онлайн магазин сам
- Не може да настрои hosting/домейни
- Не може да настрои plащания
- Не може да настрои доставки
- Чувал е че онлайн се продава много, но е било твърде сложно/скъпо

## Решението: Един бутон

```
Митко: "Искам онлайн магазин"
↓
AI пита 5 въпроса с глас:
1. Как се казва магазинът?
2. Какво продаваш? (показва иконки)
3. Цвят? (3 опции)
4. Имаш ли лого?
5. Адрес/телефон?
↓
RunMyStore прави всичко автоматично:
- Регистрира Ecwid акаунт
- Импортира продукти от inventory
- Настройва Stripe плащания
- Настройва Speedy/Econt доставки
- SSL + domain
- Multi-store inventory sync
↓
Готов магазин за 5-10 минути
Линк: peshostore.runmystore.shop
```

**Митко не вижда Ecwid никъде. За него RunMyStore прави всичко.**

## Защо този модул е критичен за Marketing AI

Marketing AI без онлайн магазин = губим:
- 40-60% от reach (онлайн clicks → no destination)
- Attribution с promo codes (нужен е checkout)
- Cross-channel retargeting
- Customer behavior data

**Online Store не е "nice to have". Той е foundation за Marketing AI.**

---

# 6. 6-ТЕ ПРОМПТ-МАРКЕТОЛОГА

Първоначално бяха 15. След 5 AI анализа консенсусът беше 3-12. Финално: **6 ядра**.

## 1. СТРАТЕГ
**Цел:** "Какво да рекламирам тази седмица?"
**Вижда:** inventory + sales velocity + zombie analysis + seasonal patterns
**Излиза:** приоритизиран списък от 1-3 кампании с обосновка
**Модел:** Sonnet 4.6 (стратегически)

## 2. КРЕАТИВ
**Цел:** "Какъв message и tone за този продукт?"
**Вижда:** product context + audience profile + brand voice + успешни предишни creatives
**Излиза:** copy за headline + description + CTA + creative brief за Symphony/Advantage+
**Модел:** Sonnet 4.6

## 3. БЮДЖЕТЕН ОПТИМИЗАТОР
**Цел:** "Колко да дадем и на кой канал?"
**Вижда:** historical ROAS + current spend + remaining budget + channel performance
**Излиза:** budget split per channel + cap recommendations
**Модел:** Gemini Flash (тактически) + Sonnet за reallocations

## 4. RECOVERY ЕКСПЕРТ
**Цел:** "Кои клиенти да върнем и как?"
**Вижда:** dormant customers + last visit + last purchase + favorite categories + LTV
**Излиза:** segmented re-engagement campaigns (SMS, retargeting, email)
**Модел:** Sonnet 4.6

## 5. PERFORMANCE АНАЛИЗАТОР
**Цел:** "Защо работи / защо не работи?"
**Вижда:** campaign metrics + conversions + creative variants + audience segments
**Излиза:** root cause analysis + 3 hypotheses за тест + scaling/killing recommendation
**Causal reasoning + counterfactuals** (от ChatGPT критиката)
**Модел:** Opus 4.7 за дълбок анализ (rare), Sonnet за регулярен monitoring

## 6. ATTRIBUTION ENGINE
**Цел:** "Как доказваме че работи?"
**Вижда:** promo codes used + QR scans + loyalty matches + voice answers + timing patterns
**Излиза:** ROI report на български човешки език: "€25 → €87 приход → €43 печалба"
**Без този агент Marketing AI няма стойност** (от Gemini критиката)
**Модел:** PHP правила + Gemini Flash (90% от работата е изчислителна)

---

# 7. ПАРТНЬОРИ — ИЗБОР И ОБОСНОВКА

## Online Store: Ecwid by Lightspeed

### Защо Ecwid (победител след 2 проучвания)

**За малки магазини (Пешо, до 5,000 SKU):**
- ✅ Бърз
- ✅ Евтин
- ✅ Лесен setup

**За големи магазини (ENI, 30,000+ SKU):**
- ✅ Поддържа 70,000 продукта
- ✅ Неограничени вариации (не се броят към лимита)
- ✅ 600 API calls/мин (4× повече от Duda)
- ✅ Headless архитектура
- ✅ AWS Frankfurt

**Дисквалифицирани:**
- ❌ Duda — твърд лимит 20,000 продукта (Тихол има 30k)
- ❌ Shopify — изключено в стратегическата фаза (5-AI consensus)
- ❌ WooCommerce self-hosted — твърде много support burden
- ❌ Wix Studio — partner program не е за SaaS, B2C oriented
- ❌ Webflow — слаб eCommerce
- ❌ 10Web/Pressable — WordPress отдолу = подверsно на чупене

### Какво поема Ecwid (не наша работа)
- Hosting (AWS Frankfurt)
- SSL сертификати
- Backups
- Security
- Updates
- DDoS защита
- 99.95% uptime SLA

### Какво поемаме ние
- Един бутон provisioning
- Inventory sync (RunMyStore POS ↔ Ecwid)
- Multi-store routing logic
- Inventory locks
- Marketing AI integration
- AI чат-бот за support (80% автоматизация)

## Plащане Gateways

**Primary: Stripe**
- Работи в цяла EU
- BG, RO, GR, PL, CZ, HU и т.н.
- Карти, Apple Pay, Google Pay, SEPA
- 3D Secure
- Multi-currency

**Secondary (when needed):**
- Borica (БГ — за някои клиенти)
- Netopia (RO)
- JCC (GR)

**Cash on Delivery:** Speedy/Econt API (не plащане gateway, а доставка)

## Marketing Channels

**Primary партньори чрез MCP:**
- Meta (Facebook + Instagram + Messenger + WhatsApp)
- TikTok Ads + Symphony Creative
- Google Ads (PMax + Search)
- Google My Business

**Secondary:**
- Email (built-in в RunMyStore)
- SMS (existing integration)

---

# 8. PRICING STRATEGY (ПОЖЕЛАТЕЛНО — ЗА ОТДЕЛНА ДИСКУСИЯ)

⚠️ **ВАЖНО:** Цените тук са **пожелателни**. Не са финални. Ще се обсъждат отделно при бизнес планиране.

## Marketing AI Tiers

| Tier | България | EU | Маржин (target) |
|------|---------|-----|-----------------|
| Lite | €99/мес | €149/мес | 90%+ |
| Standard | €149/мес | €249/мес | 85%+ |
| Pro | €249/мес | €399/мес | 80%+ |
| Enterprise | €499/мес | €799/мес | 80%+ |

## Online Store Tiers

| Tier | България | EU | Маржин |
|------|---------|-----|--------|
| Online Lite | €19/мес | €29/мес | 60% |
| Online Standard | €39/мес | €59/мес | 70% |
| Online Pro | €79/мес | €119/мес | 75% |

## Performance Guarantee

> **„Ако за 90 дни Marketing AI не ти спести/донесе поне €X, връщаме парите."**

Това решава 4 проблема:
- Willingness-to-pay в БГ
- Trust building (особено първите 50 клиента)
- Принуждава ни да не пускаме преди да работи
- Marketing съобщение пише се само

---

# 9. MULTI-STORE INVENTORY ROUTING

## Стратегия (финализирана)

```
ОНЛАЙН ПРОДАЖБА:
Клиент купува онлайн → Ecwid webhook → RunMyStore
↓
Routing алгоритъм:
1. ПЪРВО — базовият (складов) магазин има ли стоката?
   → ДА → дърпаме оттам
   → НЕ → стъпка 2
2. ТЪРСИМ кой магазин има най-много количество
   → Намираме → резервираме (lock 30 мин)
3. Магазинът потвърждава → SOLD
4. Inventory намалява за всичките 5 магазина
↓
ФИЗИЧЕСКА ПРОДАЖБА:
Пешо в магазин 3 продава Nike 42 → RunMyStore
→ API call към Ecwid → онлайн каталог обновява
→ Онлайн купувач не може да поръча този размер
```

## Race Condition Handling

**Проблем:** 2 продажби едновременно за същия артикул.

**Решение:**
- Онлайн поръчка LOCK-ва за 30 минути
- В физически POS на всичките магазини показва "RESERVED — онлайн поръчка"
- Пешо вижда статуса
- След 30 мин ако не е потвърдено → unlock

## Многомагазинна логика

**Кой обслужва онлайн поръчка:**
1. Базов склад първо
2. Магазин с най-много количество от тази стока
3. Override възможност за Митко

**Какво ако:**
- Стоката е в магазин 1, но клиентът е в радиус на магазин 5 → магазин 1 я обслужва (има стоката)
- Никой няма стоката → не може да се поръча онлайн (Ecwid go показва "out of stock")

---

# 10. RISK REGISTER

## Топ 10 рискове и mitigations

### 1. Pesho-in-the-Middle (POS точност)
**Риск:** Пешо забравя да маркира продажба → AI рекламира изчерпана стока → бесен клиент → ban на ad account
**Mitigation:**
- Inventory Accuracy Gate (95%+ за 30 дни)
- sale.php hardening — не може да се затвори продажба без всички продукти маркирани
- Voice barut в края на деня
- Per-Пешо accuracy score → cap on marketing budget

### 2. Attribution провал
**Риск:** AI прави препоръки, никой не доказва ROI → клиенти се отказват за 90 дни
**Mitigation:**
- Promo codes built-in от ден 1
- Voice prompt при checkout: "Имате ли код?"
- VIP loyalty match за automated attribution
- ROI report на български: "€25 → €87"

### 3. MCP API нестабилност
**Риск:** Meta/TikTok/Google променят API → системата се чупи
**Mitigation:**
- Multi-MCP fallback strategy
- Manual mode при downtime
- Не строим целия бизнес върху 1 канал

### 4. AI cost при scale
**Риск:** 1000 клиенти × Opus calls = bankruptcy
**Mitigation:**
- 80/20 PHP/LLM split
- Caching на context
- Multi-tenant batch processing
- Hard cap на Opus calls

### 5. Race conditions при онлайн поръчки
**Риск:** Двойна продажба → клиент бесен → reputation damage
**Mitigation:**
- 30-минутен inventory lock
- "Reserved" status в POS
- Conflict resolution алгоритъм

### 6. Vendor lock-in (Ecwid)
**Риск:** Ecwid вдига цените 2× → залегнали сме
**Mitigation:**
- Standard product formats за миграция
- Backup партньор (не activated, но готов)
- По-нисък lock-in от Duda/Shopify

### 7. EU AI Act compliance
**Риск:** AI-генерирани реклами без disclosure → fines
**Mitigation:**
- AI label автоматично от платформите
- Audit log на всяко AI решение
- Human-in-the-loop framework

### 8. Quality control недостатъчен
**Риск:** AI препоръчва глупост → собственик губи €100 → отказва се
**Mitigation:**
- Confidence scoring 1-5
- Tinder UX (one-tap reject + voice feedback)
- Sandbox simulation
- Daily/monthly spend caps
- Kill switch с 1 клик

### 9. Cognitive overload на Митко
**Риск:** 10+ препоръки/седмица → Митко не чете нищо
**Mitigation:**
- Hard cap: max 3 решения/седмица
- Приоритизация по ROI impact
- Push notifications с 1-tap action

### 10. Data sparsity при малки магазини
**Риск:** Магазин с 3 продажби/ден → AI няма statistical significance
**Mitigation:**
- Cross-tenant federated learning (anonymized)
- External signals (seasonal trends, weather)
- Lower confidence threshold за малки

---

# 11. BETA PLAN & TIMELINE

## Phase 0: Pre-conditions (МАЙ-ЮЛИ 2026)
**ENI core stable, schema migrated, partner contracts signed**

✅ ENI beta launch (14-15 май)
✅ Inventory accuracy gate работи
✅ Ecwid partner contract signed
✅ Stripe Connect настроен
✅ Promotions модул built (за attribution)
✅ Schema migration завършен (празни таблици готови)

## Phase 1: Shadow на твоите магазини (ЮЛИ-СЕПТЕМВРИ 2026)
**Само Тихол. AI наблюдава. Не пуска нищо.**

- Marketing AI scans inventory + sales daily
- Записва решения в `mkt_campaign_decisions` (без execute)
- Сравнява прогнозите с реалността
- Тества прецизността на 6-те агента
- Намира edge cases преди реални клиенти

## Phase 2: Live на твоите магазини (ОКТОМВРИ-ДЕКЕМВРИ 2026)
**Тихол е единствен tenant с реален бюджет**

- Marketing AI пуска реални кампании
- Реален Stripe + реален Meta ad spend
- Online Store на 1-2 от твоите магазини
- Atribution validation
- Time-to-value measurement (<7 дни KPI)

## Phase 3: Closed Beta (Q1 2027)
**5-10 партньорски магазина, ръчно избрани**

- Tinder approval workflow
- 90-day Performance Guarantee
- Per-tenant accuracy gate
- Daily monitoring от нашия екип
- Iteration на промптите

## Phase 4: Public Launch (Q2 2027)
**Marketing AI add-on отваря за всички PRO+ клиенти**

- Tier-овете активирани
- Performance Guarantee на masse
- Marketing като growth driver

---

# 12. 5-ТЕ AI МНЕНИЯ — СИНТЕЗА

## Кой какво каза (кратко)

### Claude 1
- Attribution е смъртна заплаха
- Pesho-in-the-Middle е екзистенциален риск
- 15→12 агента
- Quick product photo flow
- Default-to-action за зелени

### Gemini
- "Service-as-a-Software" reframing
- 15→3 агента (твърде агресивно за нас)
- Tinder UX за approval
- Cost-control layer (евтини модели за scanning)
- Q2 2027 timing

### ChatGPT
- "AI маркетингов партньор, не маркетолог"
- Causal reasoning + counterfactuals
- Shadow mode onboarding
- Confidence scoring 1-5
- Backtesting capability

### 4-тото мнение
- "Inventory-aware revenue engine" → финално позициониране
- Sandbox simulation
- Negative decisions (AI казва "НЕ рекламирай")
- Time-to-value <7 дни

### Kimi
- Hard spend caps non-negotiable
- Kill switch с 1 клик
- One-tap approve (1 решение/седмица)
- Performance Guarantee 90-day

## Какво всички казват (consensus)

1. **15 агента е катастрофа** → намалено на 6
2. **Attribution е най-голямата дупка** → built-in от ден 1
3. **Beta план твърде амбициозен** → Shadow → Advisory → Production
4. **Q4 2026 рано** → Q1-Q2 2027 за широка публика
5. **Quality control недописан** → confidence + caps + kill switch
6. **Inventory accuracy = gate** → 95%+ за 30 дни

---

# 13. КАКВО **НЕ** ПРАВИМ (ИЗРИЧНО ОТХВЪРЛЕНО)

## Изключени партньори
- ❌ **Shopify** — отхвърлено единодушно от 5 AI-та
- ❌ **WooCommerce self-hosted** — твърде много support burden
- ❌ **Duda** — лимит 20,000 продукта (ENI има 30k)
- ❌ **Wix/Squarespace** — B2C oriented
- ❌ **WordPress-based hosting** — patches/plugins се чупят

## Изключени стратегии
- ❌ **Shopify Partner program** — отхвърлено в стратегическата фаза
- ❌ **Двойна архитектура** (Ecwid + Duda) — двойна dev работа, отхвърлено след Gemini benchmark
- ❌ **Affiliate-only модел** — Пешо не може сам, ние правим всичко
- ❌ **15 агента** — over-engineering

## Изключени pricing подходи
- ❌ **Под €99 за Marketing AI** — обезценява стойността
- ❌ **Pure usage-based pricing** — твърде сложно за Митко
- ❌ **Free tier за Marketing AI** — само за core продукт

## Изключени технически избори
- ❌ **Ние да хостваме онлайн магазини** — outside core competency
- ❌ **Custom WordPress theme development** — поглъща време
- ❌ **Building marketing creative engines** — Symphony/Advantage+ ги правят
- ❌ **Manual approval за всичко** — кill business case

---

# 14. SUCCESS METRICS

## Phase 1-2 Success Criteria (Тихол собствени магазини)
- [ ] Time-to-Value <7 дни на първа реклама
- [ ] AI accuracy ≥80% на препоръките
- [ ] Inventory accuracy ≥95% на ENI
- [ ] Attribution ≥70% на онлайн поръчки proved to ad source
- [ ] Zero ad account bans
- [ ] Първа кампания печеливша

## Phase 3 Success Criteria (Closed Beta)
- [ ] 5-10 платящи клиенти на €99-249
- [ ] Average ROI ≥2× на ad spend
- [ ] Net Revenue Retention ≥110%
- [ ] Performance Guarantee invoked < 10%
- [ ] Customer satisfaction ≥4.2/5

## Phase 4 Success Criteria (Public Launch — Year 1)
- [ ] 100+ Marketing AI клиенти
- [ ] €15K-30K MRR от Marketing tier
- [ ] Multi-store routing < 1% conflict rate
- [ ] AI cost <15% от revenue
- [ ] Churn rate <5%/мес

## Year 5 Vision
- [ ] 5,000+ Marketing AI клиенти
- [ ] €10M+ ARR от Marketing alone
- [ ] Category-defining product за SMB retail в EU
- [ ] EU expansion: BG, RO, GR, PL, CZ, HU, SK, SI

---

# КРАЙ НА ЛОГИЧЕСКАТА БИБЛИЯ

**Технически детайли:** виж `MARKETING_BIBLE_TECHNICAL_v1.md`

**Schema детайли:** включени в техническия документ (25 нови таблици + 9 ALTER)

**За включване в roadmap:** да, веднага в `MASTER_COMPASS.md` и `ROADMAP.md`

**Pricing статус:** пожелателно, отделна дискусия

**Beta start:** Phase 0 започва незабавно (паралелно с ENI core beta)

---

*Документ финализиран: 03 май 2026*
*Версия: 1.0*
*Owner: Тихол Енев*
*Reviewer: Шеф*
