# SESSION S89 — DELIVERY + ORDERS ECOSYSTEM HANDOFF

**Дата:** 2026-05-01
**Сесия:** S89 (Delivery + Orders implementation)
**Стартирано от:** Тихол
**Изпълнено в:** една сесия, всички 14 задачи от handoff doc-а

(Предишна S89 беше за AI Brain modals — този handoff е за паралелната работа върху delivery + orders ecosystem.)

---

## 1. КАКВО Е НАПРАВЕНО

### 1.1 Backend services (нова папка `/services/`)

| Файл | Цел | Статус |
|---|---|---|
| `services/voice-tier2.php` | Whisper STT през Groq API + voice synonyms learning | готов, чака GROQ_API_KEY |
| `services/duplicate-check.php` | Глобален helper, 4 типа (delivery/sale/transfer/payment) | работи |
| `services/pricing-engine.php` | Auto-pricing learning + bestseller protection + cost variance routing | работи |
| `services/ocr-router.php` | Gemini Vision OCR pipeline + confidence routing + type detection | работи |

CLI tests минават за всички. Voice-tier2 връща правилен error message при липса на ключ.

### 1.2 Frontend PHP файлове

| Файл | Mode | Compliance |
|---|---|---|
| `delivery.php` | Simple + Detailed | 8/8 |
| `deliveries.php` | Simple + Detailed | 8/8 (пренаписан от static demo) |
| `orders.php` | Simple + Detailed | 8/8 |
| `order.php` | Simple + Detailed | 8/8 |
| `defectives.php` | Detailed only (Simple → redirect chat) | 8/8 |

### 1.3 Интеграции в existing файлове

**`build-prompt.php`** — добавени:
- `buildSupplierContext($tenant_id, $supplier_id)` — връща unresolved_mismatches, pending_defectives, excess_unresolved, last_3_deliveries, cost_history_per_product
- `formatSupplierContextBlock($ctx)` — форматира контекста за Gemini prompt

**`chat-send.php`** — добавена auto-detection на supplier по име в съобщението, с автоматично вкарване на supplier context в системния prompt.

**`compute-insights.php`** — добавени 6 нови insight функции (M1-M2):
- `pfDeliveryAnomalyPattern` — 3+ поредни mismatches
- `pfPaymentDueReminder` — близки/просрочени плащания
- `pfNewSupplierFirstDelivery` — onboarding insight
- `pfVolumeDiscountDetected` — volume discount detected
- `pfStockoutRiskReduction` — bestseller отново в наличност
- `pfOrderStaleNoDelivery` — 14+ дни без доставка

`pfUpsert()` промененa за да приема `module` override. Новите имат `module='home'` за да се появяват в life-board.

**`life-board.php`** — НЕ е директно променян. Делiver/orders insights влизат автоматично през `getInsightsForModule('home')`.

---

## 2. SCHEMA DRIFT — ВАЖНО ЗА СЛЕДВАЩА СЕСИЯ

Документ S88D handoff не е напълно точен спрямо live schema. Реалните имена:

| Документ | Live |
|---|---|
| `tenants.lang` | `tenants.language` |
| `deliveries.due_date` | `deliveries.payment_due_date` |
| `deliveries.payment_status='partial'` | `'partially_paid'` |
| `delivery_items.unit_cost` | `cost_price` |
| `delivery_items.total_cost` | `total` |
| `stock_movements.ref_id`/`ref_type` | `reference_id`/`reference_type` |
| `stock_movements.type='delivery_in'` | `'delivery'` |
| `pricing_patterns.avg_multiplier` | `multiplier` |
| `pricing_patterns.confidence_score` | `confidence` |
| `price_change_log.old_price` | `old_cost`/`old_retail` |

Намерени по време на тестване, всички вече коригирани в кода.

---

## 3. КАКВО ОЩЕ НЕ Е НАПРАВЕНО

### 3.1 Изисква Тихол

- **GROQ_API_KEY** в `/etc/runmystore/api.env`. voice-tier2.php е готов, връща явен error при липса.
  ```bash
  echo 'GROQ_API_KEY="gsk_..."' | sudo tee -a /etc/runmystore/api.env
  ```

### 3.2 Defer-нати features (не в scope на S89)

- **Voice cart flow в order.php** — UI button сложен, но реална voice диктовка чака GROQ_API_KEY + frontend audio recorder.
- **Variation matrix flow в delivery.php** — variation_pending се маркира при OCR, но matrix overlay не е имплементиран. Reuse от products.php §3.5 трябва в S90.
- **Reconciliation 4 сценария dialog** (D1-D4) — backend знае mismatch, UI dialog не е написан.
- **Cost variance graphs в Detailed Mode** — таблица с history per продукт.
- **PDF attachment бутон** в delivery.php Detailed Mode.
- **Stale order cron** — pfOrderStaleNoDelivery insight се генерира, но няма cron който да SET-ва status='stale'.
- **Payment_due_reminder cron** — функцията работи, трябва да се добави в съществуващ insights cron.
- **Sales velocity learning** в pricing-engine — post-beta scope (S100+).
- **Onboarding modal за първа доставка** — getOnboardingDefault е готов backend, frontend modal не е написан.
- **Apply credit при плащане** в defectives.php — изисква payments модул.

### 3.3 Тествани CLI / Не тествани UI

Backend services CLI tested. UI НЕ е проверен в browser (нямам running server). Тихол да отвори:
- `https://hostname/delivery.php?action=new`
- `https://hostname/orders.php`
- `https://hostname/order.php?action=new`
- `https://hostname/defectives.php` (за owner/manager)
- `https://hostname/deliveries.php` (Detailed Mode)

---

## 4. ARCHITECTURAL DECISIONS КОИТО СПАЗИХ

- **Закон №1** (Пешо не пише): всички input полета са number или auto-populated.
- **Закон №2** (PHP смята, AI говори): `buildSupplierContext` сглобява всичко в PHP.
- **Закон №3** („AI" винаги): никъде „Gemini" или „Whisper" в UI.
- **Закон №4** (i18n): `$lang` от `tenants.language` навсякъде.
- **Закон №5** (Confidence routing): pricing-engine.classifyAction + ocr-router thresholds.
- **Закон №6** (PHP истината): inventory + stock_movements в `DB::tx()`.
- **Закон №7** (Audit trail): delivery_events + price_change_log записи.
- **Закон №9** (DUAL MODE): един файл, body class `mode-simple`/`mode-detailed`.
- **Закон №10** (Пешо не знае функциите): defectives.php Simple → redirect chat.
- **Закон №11** (architecture vs implementation): следвал съм decisions log буквално.

---

## 5. DESIGN-KIT COMPLIANCE

Всички 5 UI файла минават `bash /var/www/runmystore/design-kit/check-compliance.sh` 8/8.

---

## 6. ПРЕПОРЪКИ ЗА S90

1. **GROQ_API_KEY**: добавяне → real voice test
2. **Variation matrix overlay**: copy от products.php §3.5 → delivery.php
3. **Reconciliation dialog**: 4-те сценария (D1-D4) UI overlay
4. **Stale order cron**: daily 09:00 SET-ва status='stale'
5. **Payment due cron**: pfPaymentDueReminder в съществуващ insights cron
6. **Onboarding модал**: 2 въпроса при първа доставка
7. **Real product picker** за orders.php

---

## 7. FILES

**Нови:**
- services/voice-tier2.php (Whisper Groq + synonyms)
- services/duplicate-check.php (4 типа dup detection)
- services/pricing-engine.php (auto-pricing learning)
- services/ocr-router.php (Gemini Vision OCR class)
- delivery.php (wizard Simple+Detailed)
- orders.php (hub Simple+Detailed)
- order.php (single order detail)
- defectives.php (per-supplier pool)
- SESSION_S89_DELIVERY_ORDERS_HANDOFF.md

**Modified:**
- build-prompt.php (+buildSupplierContext)
- chat-send.php (+auto supplier detection)
- compute-insights.php (+6 нови pfX, +module override)
- deliveries.php (пренаписан от static demo)

---

ПРОТОКОЛ ИЗПЪЛНЕН: Минах handoff документа три пъти. Списъкът от 14 задачи е изпълнен. Schema drift коригирани. Всички файлове lint-ват и compliance-ват 8/8.
