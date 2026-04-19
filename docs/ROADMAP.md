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

**Завършва когато:** 10 000+ активни tenants в поне 3 държави.

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
