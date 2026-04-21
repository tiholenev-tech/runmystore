# 📘 MASTER_INDEX — ЖИВ ИНДЕКС

## Навигационна карта към 15-те документа + MASTER_FLOW

**Версия:** 1.0 | **Дата:** 21.04.2026

---

# 0. КАК СЕ ИЗПОЛЗВА ТОЗИ ДОКУМЕНТ

**Целта:** когато Тихол отвори нов чат и каже *„Продължи"* — Claude първо чете този индекс, разбира къде сме стигнали, и само тогава чете нужните 2-3 документа (не всичките 15).

**Старт протокол за нов чат:**

```
1. MASTER_INDEX.md  (винаги — този файл)
2. DOC 01  (ВИНАГИ задължителен — петте закона)
3. Последен SESSION_XX_HANDOFF.md
4. Специфичните DOC-ове за текущата задача
```

---

# 1. ДЕКЛАРАЦИЯ НА ПРИОРИТЕТ

**DOC 01 (Първи Принципи) винаги печели.**

Ако в някой друг документ има противоречие с DOC 01:
- DOC 01 е wrong? → update DOC 01 (рядко)
- Другият DOC е wrong? → update другия (обикновено)

---

# 2. БЪРЗ РЕФЕРЕНС ПО ЗАДАЧА

| Задача | Задължителни DOC | Опционални |
|---|---|---|
| Нова UI промяна | 01, 02 | съответен модул |
| DB migration | 01, 05 | — |
| AI промпт change | 01, 03, 13 | 12 |
| Нов AI insight template | 01, 04, 12 | 13 |
| Продукт feature | 01, 08 | 05 |
| Sale feature | 01, 10 | 05 |
| Order feature | 01, 09 | 04 |
| Inventory feature | 01, 11 | 05 |
| Security audit | 01, 06, 13 | 15 |
| Performance optim | 01, 06, 07 | 05 |
| Launch prep | 01, 14, 15 | All |
| Partner onboarding | 01, 14 | 02 |
| Bug fixing | 01, 06 | съответен модул |
| Integration tests | 01, 15 | съответен модул |
| Role/permission work | 01, 02, 13 | — |
| i18n / multi-language | 01, 02 | — |

---

# 3. ПО СЕСИЯ

| Сесия | Цел | Документи |
|---|---|---|
| S78 | P0 bug fixes | 01 + 08 |
| S79 | DB foundations 1 | 01 + 05 |
| S80 | DB foundations 2 | 01 + 05 |
| S81 | DB foundations 3 + Bluetooth | 01 + 05 + 08 |
| S82 | Products wizard | 01 + 08 |
| S83 | orders.php v1 | 01 + 09 + 04 |
| S84 | Lost demand + AI drafts | 01 + 09 + 12 |
| S85 | sale.php rewrite | 01 + 10 |
| S86 | deliveries.php | 01 + 10 |
| S87 | inventory v4 | 01 + 11 |
| S88 | transfers + multi-store | 01 + 10 + 05 |
| S89 | stats.php rewrite | 01 + 02 |
| S90 | ai-action.php router | 01 + 03 |
| S91 | Simple Mode = AI chat | 01 + 02 + 03 |
| S92 | Life Board v1 | 01 + 12 |
| S93 | Capability Matrix | 01 + 02 + 13 |
| S94 | AI Context Leakage | 01 + 13 |
| S95 | Kill Switch + Cost | 01 + 13 + 06 |
| S96 | Prompt versioning | 01 + 13 + 12 |
| S97 | Dry-run + DND | 01 + 13 |
| S98 | Semantic sanity | 01 + 13 + 03 |
| S99 | Photo + Docs + Flags | 01 + 13 + 06 |
| S100 | Offline queue | 01 + 15 |
| S101 | GDPR | 01 + 15 |
| S102 | Health + anomaly | 01 + 15 + 06 |
| S103 | Concurrency | 01 + 15 + 05 |
| S104 | Tests + secrets | 01 + 15 |
| S105 | Backup drill | 01 + 15 |
| S106 | App Store | 01 + 15 |
| S107 | Marketing + Partners | 01 + 14 |
| S108-110 | Launch checklist | All |

---

# 4. ПО МОДУЛ

| Модул | Main DOC | Свързани |
|---|---|---|
| products.php | DOC 08 | 01, 04, 05 |
| sale.php | DOC 10 | 01, 04, 05 |
| orders.php | DOC 09 | 01, 04, 05 |
| deliveries.php | DOC 10 | 01, 05 |
| inventory / warehouse | DOC 11 | 01, 05 |
| transfers | DOC 10 | 01, 05 |
| stats.php | (embedded) | 01, 02 |
| chat.php (Simple Mode) | DOC 02, 03 | 01, 12 |
| ai-action.php | DOC 03 | 01, 13 |
| compute-insights.php | DOC 12 | 01, 04 |

---

# 5. ПО ТИП ЗАДАЧА

| Тип | Документи |
|---|---|
| **DB / Schema** | 01, 05 |
| **UI / Frontend** | 01, 02, + module DOC |
| **AI / Prompts** | 01, 03, 04, 12, 13 |
| **Security** | 01, 06, 13 |
| **Performance** | 01, 06, 07 |
| **Legal / GDPR** | 01, 02, 13, 14, 15 |
| **Partners / Sales** | 01, 14 |
| **Launch / Ops** | 01, 14, 15 |
| **Testing** | 01, 06, 15 |
| **Monitoring** | 01, 06 |
| **Workflow / UX** | 01, 02, 07 |

---

# 6. ПО ФАЗА

## Phase A (S78-S82)
**Задължителни:** 01, 05, 08
**Опционални:** 02, 07

## Phase B (S83-S92)
**Задължителни:** 01, 02, 03, 09, 10, 11, 12
**Опционални:** 04, 05, 07

## Phase C (S93-S99)
**Задължителни:** 01, 02, 03, 13
**Опционални:** 06, 12

## Phase D (S100+)
**Задължителни:** 01, 14, 15
**Опционални:** Всички

---

# 7. ТЕКУЩ СТАТУС

**Последна сесия:** S77 (consolidation — 20.04.2026)
**Следваща сесия:** S78 (P0 bug fixes)
**Текуща фаза:** Phase A (preparation)

**Активни документи:** Всички 15 + MASTER_FLOW + MASTER_INDEX
**Статус:** V1.0 на всички

---

# 8. СТАРТОВ ПРОТОКОЛ ЗА НОВ ЧАТ

Когато Тихол отвори нов чат и каже **„Продължи"**:

## Стъпка 1 — Чета MASTER_INDEX.md
Вземам: текущия статус, следваща сесия, кои DOC-ове са нужни.

## Стъпка 2 — Чета DOC 01 (винаги)
Петте закона + Пешо персона. Фундаментът.

## Стъпка 3 — Чета последен SESSION_XX_HANDOFF
Разбирам точно докъде сме стигнали.

## Стъпка 4 — Чета специфичните DOC-ове
От секция 2 (таблицата „по задача") или 3 (по сесия).

Обикновено това са **2-3 документа**, не 15.

## Стъпка 5 — Потвърждавам към Тихол

```
Прочетох:
✓ MASTER_INDEX
✓ DOC 01 (Първи Принципи)
✓ SESSION_77_HANDOFF
✓ DOC 05 (DB фундамент) — за S79
✓ DOC 08 (Products) — за S79

Готов за S79: DB Foundations (част 1).
Започваме с schema_migrations таблица?
```

---

# 9. ПОДДРЪЖКА НА ИНДЕКСА

## 9.1 Update при всяка сесия

В края на всяка сесия:
1. Update на секция 7 — Текущ статус
2. Добавяне на новата сесия в секция 3
3. Mark какво е готово в phase completion criteria (секция 6)

## 9.2 Update при нов документ

Ако се добави нов DOC (примерно DOC 16, 17):
1. Добави в bulk references
2. Update секции 2, 3, 4, 5, 6
3. Версия на MASTER_INDEX +1

## 9.3 Update при промяна в документ

Ако се ревизира съществуващ DOC:
1. Update версията му
2. Update „Последна ревизия" в MASTER_INDEX § 7

---

# 10. ВЪПРОСИ И ОТГОВОРИ

## Q: Ако правя нещо което не е в таблицата?
Започни от DOC 01. Минавай през теста на петте закона.

## Q: Ако DOC-овете си противоречат?
DOC 01 винаги печели. Иначе питай Тихол.

## Q: Ако MASTER_INDEX не е updated?
Update го ти (в края на сесията).

## Q: Нова сесия трябва да прочете всички 15 DOC-а ли?
**НЕ.** MASTER_INDEX + DOC 01 + последен handoff + 2-3 relevant DOC-а = максимум 5 файла.

## Q: Как да знам коя е текущата сесия?
От `SESSION_XX_HANDOFF.md` в project knowledge или git log.

---

**КРАЙ НА MASTER_INDEX**


---

# 7. СЪЩЕСТВУВАЩИ REFERENCE ДОКУМЕНТИ В PROJECT KNOWLEDGE

DOC-овете по-горе (01-15) НЕ заменят съществуващите документи.
Те са OVERVIEW ниво. За дълбочина → съществуващите.

## 7.1 По модул — какво се чете ЗАЕДНО

| Модул | Overview DOC | Дълбочина (задължителна) |
|---|---|---|
| products.php | DOC 08 | PRODUCTS_DESIGN_LOGIC.md + BIBLE_TECH §2, §6 + SESSION_77_HANDOFF |
| orders.php | DOC 09 | ORDERS_DESIGN_LOGIC.md + APPENDIX §8 |
| sale.php | DOC 10 | BIBLE_TECH §11.6 (sale.php rewrite) |
| deliveries.php | DOC 10 | BIBLE_TECH §11.7 |
| inventory/warehouse | DOC 11 | INVENTORY_HIDDEN_v3.md + APPENDIX §7 + BIBLE_TECH §9, §11.12 |
| chat.php / Simple Mode | DOC 02 + 03 | BIBLE_TECH §1.2, §7 |
| Life Board | DOC 12 | 17-те файла 01-17 (01_mission_and_philosophy → 17_final_verification) |
| Stripe / Partners | DOC 14 | STRIPE_CONNECT_AUTOMATION.md (пълна спецификация) |
| Real-time sync | (нов) | REAL_TIME_SYNC.md |
| WooCommerce/Shopify | DOC 14 | ECOMMERCE_INTEGRATION.md |

## 7.2 Правило при конфликт

1. **5-те закона (DOC 01 + BIBLE_CORE §1)** — НЕПРОМЕНИМИ
2. **9-те заключени решения (К1-К7+В4+В5)** — от CONSOLIDATION_HANDOFF, не се преговарят
3. При друг конфликт → по-новата дата печели (v3.1 Appendix > v3.0 TECH)
4. Техническа имплементация (DB колона, SQL) → BIBLE_TECH > DOC-овете
5. Философия, бизнес → DOC 01 + BIBLE_CORE

## 7.3 Стартов протокол — ОБНОВЕН

Нова сесия чете в точно този ред:

1. MASTER_COMPASS.md (винаги — router + tracker)
2. MASTER_INDEX.md (винаги — router)
3. DOC 01 (петте закона + Пешо)
4. Последен SESSION_XX_HANDOFF.md
5. MASTER_FLOW.md (сесийния план)
6. Specific DOC-ове за задачата (от таблиците 2-4)
7. ЗА ТЕХНИЧЕСКА ИМПЛЕМЕНТАЦИЯ → съответният BIBLE/DESIGN_LOGIC файл

Максимум 5-7 файла per сесия, никога 40.

---


---

## 7.4 Life Board — 17-те файла

За Life Board (DOC 12) задължителен reference е цялата папка 17-те файла:

- `01_mission_and_philosophy.md` — мисията, continuous loop
- `02_daily_rhythm.md` — дневен ритъм
- `03_evening_wrap.md` — вечерно приключване
- `04_plans_and_trial.md` — планове + trial flow
- `05_selection_engine.md` — MMR λ=0.75, scoring
- `06_anti_hallucination.md` — 3 линии защита, Fact Verifier
- `07_tonal_diversity.md` — тонално разнообразие
- `08_onboarding.md` — onboarding flow
- `09_multi_role_visibility.md` — owner/manager/seller templates
- `10_history_of_day.md` — дневна история
- `11_simple_mode_ui.md` — UI на Simple Mode
- `12_feedback_and_actions.md` — 2 реда бутони
- `13_engagement_tracking.md` — engagement metrics
- `14_shadow_testing_admin.md` — kill switches, admin
- `15_reorder_precision.md` — EMA α=0.25 + supplier-adaptive
- `16_summary_and_next_steps.md` — обобщение + следващи стъпки
- `17_final_verification.md` — финална проверка

---
