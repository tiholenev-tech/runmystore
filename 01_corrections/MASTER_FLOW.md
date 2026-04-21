# 📘 MASTER_FLOW — ЛИНЕЙНА КАРТА ОТ ДНЕС ДО LAUNCH

## Единна стъпка-по-стъпка рамка

**Версия:** 1.0 | **Дата:** 21.04.2026

---

## 📑 СЪДЪРЖАНИЕ

1. Философия на MASTER_FLOW
2. Phase A — DB Foundation + Products (S78-S82)
3. Phase B — Module Ecosystem (S83-S92)
4. Phase C — AI Safety (S93-S99)
5. Phase D — Launch (S100+)
6. Критични milestone-и
7. Timeline
8. Готовност критерии

---

# 1. ФИЛОСОФИЯ

15-те документа описват **какво** правим и **защо**. MASTER_FLOW описва **кога** и **в какъв ред**.

Всяка стъпка има:
- Какво строим (task)
- Защо сега (dependency)
- Кои документи влизат (references)
- Какво да не забравим (gotchas)
- Готово когато (acceptance criteria)

---

# 2. PHASE A — DB FOUNDATION + PRODUCTS (S78-S82)

**Цел:** продуктите работят в ЕНИ магазина, DB е фундаментирана, принтерът работи.

## Session S78 — P0 Bug Fixes + DB Migration + compute-insights skeleton

**Какво:**
1. DB миграция — всички S77 таблици:
   - ai_insights + fundamental_question ENUM колона
   - ai_shown, search_log
   - lost_demand с нови колони (suggested_supplier_id, matched_product_id, resolved_order_id, times)
   - supplier_orders + supplier_order_items + supplier_order_events

2. P0 бъгове в products.php (от SESSION_71-73A):
   - Bug #5: AI Studio `_hasPhoto` не се сетва
   - Bug #6: renderWizard нулира бройки (wizCollectData)
   - Bug #7: sold_30d = 0 (липсва tenant_id в LEFT JOIN)

3. compute-insights.php skeleton — 15 функции за products marked with fundamental_question

**Защо сега:** DB трябва да е готова за всичко (products главна, orders, lost_demand).
P0 бъговете не могат да чакат — блокират добавяне на стока.

**Документи:**
- DOC 05 §§ 2-7 (migrations)
- DOC 08 §§ 11-12 (P0 + compute-insights)
- APPENDIX §11 (S77 migration скрипт)
- SESSION_77_HANDOFF.md (компутна спецификация)

**Готово когато:**
- Всички S77 таблици съществуват
- 3/3 P0 bugs verified fixed в production
- compute-insights.php има 15 skeleton функции
- Git tag: `v0.5.0-s78-foundation`

**Gotchas (от предишни сесии):**
- НИКОГА sed — само Python scripts в /tmp/sXX_xxx.py
- След всеки fix git commit+push без питане
- `inventory.quantity` (не qty), `products.retail_price` (не sell_price), `sales.status='canceled'` (едно L)

**P1 Backlog (не блокиращи S78, fix в Phase A):**
- Product delete cascade (soft delete с FK handling)
- Variation edit duplicate (при редакция се появяват 2 копия на същата вариация)
- CSV import BOM (UTF-8 BOM разбива parsing при Excel export)
- Label print encoding (кирилицата не се печата правилно на DTM-5811)

## Session S79 — DB Foundations (част 1)

**Какво:**
- `schema_migrations` таблица
- Money cents миграция
- Audit log таблица
- Transaction wrapper `tx()` helper
- Soft delete pattern

**Документи:** DOC 05 §§ 2-7.

**Готово когато:**
- Migration runner работи
- Всички €-колони са `_cents BIGINT`
- Audit log записва всяка промяна
- `DB::tx()` използван в sale.php

## Session S80 — DB Foundations (част 2)

**Какво:**
- Negative stock guard
- Cached stock reconciliation cron
- Timezone UTC migration
- Parked sale `allocated_millis`
- Tenant isolation composite FK
- Idempotency keys

**Документи:** DOC 05 §§ 8-14.

**Готово когато:**
- Concurrent sale test не дава negative stock
- `allocated_millis` работи
- Idempotency keys защитава от double-submit

## Session S81 — DB Foundations (част 3) + Bluetooth печат

**Какво:**
- Stock movements append-only ledger
- `operation_id` + Global Undo
- Event queue + DLQ
- State machines
- FK + CHECK constraints
- Cron heartbeat
- **Bluetooth print integration (DTM-5811)**

**Документи:** DOC 05 §§ 15-20, DOC 08 § 9.

**Готово когато:**
- Undo на продажба работи
- Event queue процесва събития
- Bluetooth печат на 50×30mm етикети в ЕНИ

## Session S82 — Products Wizard Complete

**Какво:**
- Wizard rewrite (4 стъпки)
- AI Wizard voice add
- AI Image Studio integration
- CSV import
- Expanded filter drawer

**Документи:** DOC 08.

**Готово когато:**
- Пешо може да добави артикул с глас за < 45 секунди
- 487 артикула импортирани в ЕНИ

**🎯 PHASE A COMPLETION:**
- products.php production-ready
- DB foundation solid
- Bluetooth print работи
- Първа реална продажба в ЕНИ (~10-15 май)

---

# 3. PHASE B — MODULE ECOSYSTEM (S83-S92)

**Цел:** всички operational модули работят. ЕНИ стартира реални продажби.

## S83 — orders.php v1
**Какво:** 3 входни точки, 3 типа, 4 статуса, 6 табове.
**Документи:** DOC 09 §§ 1-6.

## S84 — Lost Demand + AI draft
**Какво:** `search_log`, weekly analysis, AI auto-draft, notifications.
**Документи:** DOC 09 §§ 7-9.

## S85 — sale.php rewrite
**Какво:** Always-live camera, voice primary, numpad, parked sales, 3 типа, offline queue.
**Документи:** DOC 10 §§ 1-3.

## S86 — deliveries.php Sacred
**Какво:** OCR фактура, voice delivery add, manual wizard, delivery-triggered category count.
**Документи:** DOC 10 §§ 5-6, DOC 11 § 5.

## S87 — inventory v4 + warehouse hub
**Какво:** Hub, confidence model, event-sourced, Smart Resolver, Zone Walk, Store Health.
**Документи:** DOC 11.

## S88 — transfers + multi-store
**Какво:** transfers.php, multi-store resolver, `store_id` колони, store switcher UI.
**Документи:** DOC 10 § 7, DOC 05 § 13.

## S89 — stats.php rewrite
**Какво:** 5 таба, role-based visibility, drawer при click, AI препоръки.
**Документи:** DOC 02 §§ 4-5.

## S90 — ai-action.php router
**Какво:** Hybrid router, `$MODULE_ACTIONS`, security validation, audit log.
**Документи:** DOC 03 §§ 3-4.

## S91 — Simple Mode = AI chat
**Какво:** chat.php = главен екран, AI pull-ва данни, shortcut chips, voice + ЧЗВ.
**Документи:** DOC 02 § 3.

## S92 — Life Board v1
**Какво:** Selection Engine, 100 приоритетни теми, Tonal Diversity, Evening Wrap.
**Документи:** DOC 12.

**🎯 PHASE B COMPLETION:**
- ЕНИ магазин оперира 30+ дни
- Втори beta tenant започва
- Всички модули работят

---

# 4. PHASE C — AI SAFETY (S93-S99)

**Цел:** AI е safe for scale. Beta → Public transition готов.

## S93 — Capability Matrix + Access Control
**Документи:** DOC 02 § 6, DOC 13 § 2.

## S94 — AI Context Leakage Prevention
**Документи:** DOC 13 § 3.

## S95 — Kill Switch + Cost Guard
**Документи:** DOC 13 §§ 4-5.

## S96 — Prompt Versioning + Shadow Mode
**Документи:** DOC 13 §§ 6-7, DOC 12 § 11.

## S97 — Dry-run + DND + Trust
**Документи:** DOC 13 §§ 8-10.

## S98 — Semantic Sanity + Full Audit
**Документи:** DOC 13 §§ 11-12.

## S99 — Photo Security + Document Ledger + Feature Flags
**Документи:** DOC 13 §§ 13-15.

**🎯 PHASE C COMPLETION:**
- AI Safety audit passed
- GDPR compliance documented
- Beta 2 running 30+ дни
- Kill switches tested live

---

# 5. PHASE D — LAUNCH (S100+)

**Цел:** public launch ready.

## S100 — Capacitor Offline Queue
## S101 — GDPR Compliance
## S102 — Anomaly Detection + Health Checks
## S103 — Advanced Concurrency
## S104 — Secrets + Integration Tests
## S105 — RTO/RPO Testing
## S106 — App Store Submission
## S107 — Marketing + Landing
## S108-110 — Launch Checklist

**🎯 PHASE D COMPLETION — PUBLIC LAUNCH:**
- App Store + Play Store live
- 2+ beta tenants в production 60+ дни
- Zero critical bugs last 30 дни
- Legal compliance confirmed
- Marketing site live
- Partner network active

---

# 6. КРИТИЧНИ MILESTONE-И

| Milestone | Session | Date est. |
|---|---|---|
| First real sale в ЕНИ | S82 | 10-15.05.2026 |
| Втори beta tenant | S90-91 | 01.06.2026 |
| AI Safety audit | S99 | 15.07.2026 |
| Public launch | S110 | 01-15.09.2026 |

---

# 7. TIMELINE

```
Phase A ████████████░░░░░░░░░░░░░░░░░░  May 2026
Phase B ░░░░░░░░████████████████░░░░░░  Jun-Jul 2026
Phase C ░░░░░░░░░░░░░░░░████████░░░░░░  Jul-Aug 2026
Phase D ░░░░░░░░░░░░░░░░░░░░████████░░  Aug-Sep 2026

                    Public Launch: Sep 2026
```

---

# 8. ГОТОВНОСТ КРИТЕРИИ

## Phase A done
- [ ] 7 P0 bugs fixed
- [ ] 20 new tables created
- [ ] Migrations system operational
- [ ] Products wizard rewritten
- [ ] Bluetooth print работи
- [ ] ЕНИ first sale

## Phase B done
- [ ] orders.php v1
- [ ] sale.php rewrite
- [ ] deliveries.php
- [ ] inventory v4
- [ ] transfers
- [ ] stats rewrite
- [ ] /ai-action.php router
- [ ] Simple Mode = AI chat
- [ ] Life Board v1
- [ ] ЕНИ 30+ days stable

## Phase C done
- [ ] Capability matrix
- [ ] GDPR compliance
- [ ] Kill switches
- [ ] Cost guards
- [ ] Shadow testing
- [ ] Full audit
- [ ] Beta 2 running

## Phase D done
- [ ] Offline queue
- [ ] Tenant export
- [ ] Anomaly detection
- [ ] Integration tests 100%
- [ ] App Store approved
- [ ] Play Store approved
- [ ] Marketing site
- [ ] Legal cleared

**Public launch happens only when all 4 checklists are 100%.**

---

**КРАЙ НА MASTER_FLOW**
