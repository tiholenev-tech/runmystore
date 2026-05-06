# 🗺️ RUNMYSTORE.AI — ROADMAP

**Версия:** 1.1 (19.04.2026)  
**Философия:** Без точни срокове. Всяка фаза завършва когато е готова, не по календар.

---

## 📖 КАК СЕ ЧЕТЕ ТОЗИ ДОКУМЕНТ

**Roadmap = жив документ.** Чете се в стартовия протокол на ВСЯКА сесия (заедно с BIBLE и handoff-а).

**Claude прави проверка преди всяка задача:**

1. **Валидна ли е текущата фаза?** (Фаза 1 = Основни модули — още ли е така?)
2. **Задачата на сесията попада ли в сегашната фаза?** (S78 = P0 бъгове + DB миграция → да, Фаза 1)
3. **Следващите 10 сесии актуални ли са?** (S78–S87 план)

**Ако Roadmap-ът е остарял или не отговаря на реалността:**
- Claude **НЕ** мълчи
- Claude **НЕ** променя сам
- Claude **КАЗВА на Тихол**: „Roadmap предвижда X в S82, но направихме Y вместо. Предложение: обнови Фаза 1 → ..."
- Тихол одобрява → Claude прави промените → commit към GitHub

**Кога задължително се обновява:**
- В края на всяка фаза (S87 = край на Фаза 1, S100, S120, S140)
- Ако се добави нов модул който не е в плана
- Ако се промени архитектурно решение
- Ако Тихол каже „смени посоката"

---

## 📍 СЕГА: ФАЗА 1 — ОСНОВНИ МОДУЛИ (ядрото)

**Цел:** Работещ минимум за 1 магазин. ЕНИ Тихолов = beta.

### Какво се прави:
- ✅ chat.php (готов)
- ✅ products.php (функционалност готова, rewrite на UI в S80)
- ⏳ Phase 0 Engine (ai_insights система) — S78-S80
- ⏳ orders.php (изцяло нов) — S81-S83
- ⏳ deliveries.php (ръчно + OCR) — S84-S86
- ⏳ sale.php rewrite — S87-S89
- ⏳ transfers.php — S90
- ⏳ inventory.php (Zone Walk) — S91-S93
- ⏳ warehouse.php (hub) — S94
- ⏳ **Импорт адаптер — универсален CSV + Microinvest** (S96+, преди или по време на бета) — auto-detect (encoding+разделител+header fingerprint), AI групиране на вариации
- ⏳ **Фискална Bluetooth интеграция (Елтрейд/Датекс/Тремол/Daisy)** — преди пускане в БГ; стандартен подход, същият като Microinvest/Детелина

**Завършва когато:** Пешо в ЕНИ може да изкара ден работа изцяло от RunMyStore — продажби, доставки, поръчки, броене.

---

## 📍 ФАЗА 2 — AI МОЗЪК НА ПЪЛНИ ОБОРОТИ

**Цел:** AI спира да е „инструмент", става „втори мозък".

### Какво се прави:
- compute-insights.php от 30 → 100+ функции
- 857 AI теми активирани
- Fact Verifier layer (anti-hallucination)
- Confidence class A-E per отговор
- Selection Engine — MMR λ=0.75 алгоритъм
- Tonal diversity — 8 варианта per topic
- 15 proactive triggers (bestseller на нула, закъсняла доставка, zombie, VIP изчезнал, etc.)
- AI Chat capabilities: actions (не само reads) — сменя цени, създава поръчки, потвърждава доставки

**Завършва когато:** 80% от откриванията на Пешо идват от AI (не той ги намира).

---

## 📍 ФАЗА 3 — MULTI-STORE + STAFF КПД

**Цел:** От 1 магазин → 5 магазина (ЕНИ вериги).

### Какво се прави:
- Multi-tenant isolation в DB
- Cross-store агрегация (lost demand, transfers между магазини, cash flow)
- Staff КПД — 19 метрики (margin killer, upsell ability, retention, cancel rate, lost demand value)
- Multi-role visibility (owner/manager/seller)
- Per-store filtering в AI context
- Manager view — „моят магазин", owner view — „всички"

**Завършва когато:** 5-те магазина на ЕНИ работят paralelno.

---

## 📍 ФАЗА 4 — БЕТА ТЕСТОВЕ

**Цел:** 5 ЕНИ магазина + 10 външни beta клиента.

### Какво се прави:
- Stabilization — bug fixing на reports
- Onboarding optimization (drop-off triggers)
- Life Board никога не е празен — fallback chain
- Trial strategy 4 месеца PRO → избор
- Feedback loop: in-app feedback, Tihol разглежда всеки ден
- WOW Tiers activation (Tier 1/2/3 wow moments)
- Store Health Score eval
- Performance: page transitions < 1 сек
- **AI Cost оптимизации — динамичен контекст + PHP summary** (преди 100 клиента) — категоризация на въпроса в `build-prompt-integration.php` (зарежда САМО нужните слоеве), `buildProductSummary()` PHP функция (200 токена вместо 6000 при големи магазини), per-store контекст при multi-store. Спестяване 57-67%, бруто марж 80-85%.

**Завършва когато:** 10/10 beta клиенти казват „не мога без това". NPS > 50.

---

## 📍 ФАЗА 5 — SaaS ГОТОВНОСТ

**Цел:** Публичен launch, Stripe payments.

### Какво се прави:
- Stripe Connect (3-ролев модел: Head 15% + Regional 15% + Head override 5% + Sub-Affiliate 50%×5)
- Ledger-first billing
- Онбординг до 95% (5 екрана — Welcome Hook → Clip → Договор → Signup → Main)
- Партньорска мрежа — Territory License + Referral програма
- Supplier Portal (нов модул §26)
- Legal: антиMLM защита, audit trail
- Launch landing page + marketing site
- Help docs + video tutorials
- **AI рекламен агент на runmystore.ai** — Gemini 2.5 Flash, разговаря с посетителя, персонализира демо по тип бизнес (дрехи/обувки/козметика), плаващ бутон Neon Glass, public_chat_leads таблица, rate limit 30 съобщения/сесия, ~€0.01-0.02 per сесия
- **Демо страница с интерактивен AI тур** — централната точка на дигиталния маркетинг, всички канали (Facebook/TikTok/Google/LinkedIn) водят към нея, демо данни per тип бизнес, запомня разговора между сесии, събира имейл естествено
- **TTS voice режим (Режим 3 — AI говори обратно)** — Google Cloud Text-to-Speech (WaveNet $16/1M симв., Chirp 3 HD $30/1M), пълен hands-free цикъл (Whisper → AI → TTS), избираем (НЕ по подразбиране), безплатно за бета (5 магазина = €0)
- **Stripe Tap to Pay интеграция** (Година 1-2) — Stripe Terminal SDK + Tap to Pay на Android, поддържа Visa/Mastercard/AmEx/Apple Pay/Google Pay/Samsung Pay, 0.5% платформена комисионна чрез Stripe Connect, onboarding 2-3 минути (Stripe Connect Express → KYC → IBAN)

**Завършва когато:** Публично достъпен product-market fit.

---

## 📍 ФАЗА 6 — SCALE

**Цел:** International launch, App Store + Play Store.

### Какво се прави:
- i18n infrastructure активирана (DOCUMENT_2 §20)
- 2-3 market launch (Bulgaria първо, после Румъния / Сърбия / Гърция)
- Capacitor mobile apps (iOS + Android)
- App Store + Google Play submission
- Push notifications (Firebase)
- Localization: UI strings, AI responses, voice recognition
- Offline mode (IndexedDB queue + Service Worker)
- Retrospective preceding всеки major release
- **Импорт адаптери — SmartBill, Sedona, SAGA C** (при навлизане в Румъния) — SmartBill REST API (най-лесен в RO), Sedona Retail (силна поддръжка на вариации за дрехи/обувки), SAGA C (DBF/Excel за счетоводители)
- **Импорт адаптери — SoftOne, PRISMA Win, Pylon** (при навлизане в Гърция)
- **Импорт адаптери — Loyverse, Shopify POS, Lightspeed** (международни облачни POS, глобално покритие)

**Завършва когато:** 10 000+ активни tenants в поне 3 държави.

---

## 📍 ФАЗА 7 — ХАРДУЕРНА ЛИНИЯ + ПЪЛНА ВЕРТИКАЛНА ИНТЕГРАЦИЯ (Година 2-3)

**Цел:** RunMyStore не е приложение — е завършен POS продукт.

### Какво се прави:
- **RunMyStore Terminal — хардуерна линия (Година 2-3)** — Android POS терминал OEM от Китай (Android 13, 5.5" IPS, 5MP камера + LED, NFC, вграден 58mm термален принтер, WiFi/BT/4G LTE, 5000mAh, GPS, ~400g). Себестойност $65-95 при OEM мащаб. RunMyStore APK предварително инсталиран. Цена €149 еднократно ИЛИ безплатен при 12-месечен PRO абонамент. Nespresso модел.
- Per-държава фискална стратегия:
  - БГ/РО/ГР/Сърбия/Словакия/Унгария — терминал + Bluetooth към фискален принтер (хардуерна фискализация)
  - Хърватия/Испания/Полша — софтуерна фискализация (терминалът Е всичко в едно)
  - UK/IE/Дания/Финландия/Холандия/Люксембург — без фискализация
- Първа държава за пълен all-in-one terminal: **Хърватия или UK/IE**
- Импорт адаптери — JTL (DE), Danea Easyfatt (IT), Factusol (ES), Subiekt GT (PL) — per държава при навлизане
- Stripe Tap to Pay приходен модел — 0.5% × среден €3,000 оборот/мес × N клиенти

**Завършва когато:** Първите 1,000 терминала пуснати, картови плащания генерират >€10k/мес комисионна.

---

## 🎯 ФОКУС НА СЛЕДВАЩИТЕ 10 СЕСИИ (S78–S87)

Това е текущият critical path. Всичко друго чака.

| Сесия | Какво | Модел |
|---|---|---|
| **S78** | P0 бъгове + DB миграция S77 (Phase 0 + orders таблици) | Opus 4.7 |
| **S79** | compute-insights.php — 30 функции + fundamental_question | Opus 4.7 |
| **S80** | products.php главна UI rewrite (6-те въпроса) | Opus 4.7 |
| **S80.5** | Expanded filter drawer | Opus 4.7 |
| **S81** | orders.php backend + compute-orders.php | Opus 4.7 |
| **S82** | orders.php UI primary view (по доставчик) | Opus 4.7 |
| **S83** | orders.php draft detail + alt views + menu | Opus 4.7 |
| **S84** | P1 fixes + supplier address UI + categories dedup | Sonnet |
| **S85** | Page transition performance investigation | Opus |
| **S86** | Batch photo upload + AI auto-match | Opus |
| **S87** | sale.php rewrite start | Opus |

След S87 ще направим **нов Roadmap** със следващите 10 сесии. Предсказване по-напред = fiction.

---

## ⚠️ НЕПРОМЕНИМИ ПРИНЦИПИ

(Преди всяка фаза провери, че са спазени)

1. **5-те закона** (CORE §1) — не се променят НИКОГА
2. **6-те фундаментални въпроса** (Appendix §6) — закон от S77
3. **Artikул-центричност + profit** (Appendix §10.1-10.2)
4. **Склад Hub архитектура** (Appendix §7)
5. **Никога sed/regex** — само Python скриптове (OPERATING_MANUAL §1)
6. **Пълен файл** — никога частичен код
7. **DB field naming** — `products.code`, `products.retail_price`, `inventory.quantity`, `sale_items.unit_price`
8. **Никога „Gemini" в UI** — винаги „AI"
9. **i18n** — никога hardcoded български

---

**КРАЙ НА ROADMAP**
