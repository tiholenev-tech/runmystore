
# 🎯 ПЛАН ЗА СЛЕДВАЩИТЕ СЕСИИ — RUNMYSTORE BETA

**Създаден:** 27.04.2026 (нощта след S82.STUDIO marathon)  
**Target:** ЕНИ first sale 10-15 май (~3 седмици)  
**Workflow:** 90% Claude Code (паралелни инстанции), 10% browser chat (само за дизайнерски одобрения / startup prompts)

---

## 🧭 ПРИНЦИПИ (научени от S82.STUDIO marathon)

1. **Disjoint paths > role split.** 2-3 паралелни Claude Code сесии работят само ако файловете не се пресичат.
2. **Шеф-чат не пише код.** Само startup prompts + dependency tree + handoff review.
3. **1 Claude Code = 3-5h работа на сесия.** Над това → грешки. Стартирай свеж terminal.
4. **DB schema apply само със backup + clone test + rollback готов.** Code #2 patternът работи — повтаряме.
5. **Diagnostic Cat A=100%/D=100% преди schema apply (Rule #21).** S82 нарушихме това — не повтаряме.
6. **БЕЗ ВЪПРОСИ режим** работи когато scope е ясен. Когато scope е размит → да задават въпроси.
7. **Mockup approval ПРЕДИ код.** S82 показа: 4 mockups → 4 phases → 0 rework.

---

## 📅 ROADMAP (15 сесии до beta launch)

### **СЕДМИЦА 1 — STABILIZATION (27.04 - 03.05)**

#### S83 — REAL ENTRY DAY (27.04, понеделник)
**Тип:** Тихол solo (без Claude Code)  
**Време:** ~3-4h  
**Цел:** Въведи 50-100 реални продукта на tenant=7 (Тихол's магазини)  
**Защо:** Stress test на products.php wizard + AI Studio modal с реална стока. Bug-ове ще изскочат.  
**DOD:**
- [ ] 50+ продукта влезли в products
- [ ] Wizard step 5 (AI Studio) тестван — Стандартно режим
- [ ] Списък bug-ове записан в `BUGS_FROM_REAL_ENTRY.md`
- [ ] Скрийншоти на counter-intuitive UX моменти

**Никакви паралелни Claude Code сесии този ден.** Тихол focused.

---

#### S84 — BUGFIX BATCH + STUDIO.REWIRE (28.04, вторник)
**Тип:** 2 паралелни Claude Code  
**Време:** ~4-5h  

**Code #1 — bug fixes от real entry:**
- Прочита `BUGS_FROM_REAL_ENTRY.md`
- Fix-ва P0 bug-ове в `products.php` (3-те known + новите от entry)
- Disjoint paths: `products.php` + малки helper-и

**Code #2 — STUDIO.REWIRE:**
- Rewire `ai-studio.php` да чете от `get_credit_balance()` (нови helper-и) вместо `tenants.ai_credits_bg`
- Rewire wizard step 5 modal да вика `/ai-studio-action.php` за magic/tryon (вместо mock)
- Disjoint paths: `ai-studio.php` + `partials/ai-studio-modal.php`

**DOD:**
- [ ] All P0 bugs от entry closed
- [ ] AI Studio показва реални числа (не mock)
- [ ] First end-to-end test: tap "Махни фон" → fal.ai призва → credit consumed → result saved

---

#### S85 — DIAGNOSTIC FIX (29.04, сряда)
**Тип:** 1 Claude Code (sequential, изисква фокус)  
**Време:** ~3-4h  
**Цел:** Затвори S81/S82 diagnostic debt  

**Scope:**
- `lost_demand_pos` schema mismatch fix
- `basket_pair_b_pos` inline INSERT total col fix
- Negative scenarios overlap (10+ Cat A FAIL) — fixture isolation pattern
- Positive scenarios "items=0" (5+ FAIL) — per-pf-function debugging

**DOD:**
- [ ] Cat A = 100% (23/23)
- [ ] Cat D = 100% (14/14)
- [ ] Cat B+C ≥ 80%
- [ ] Tag: `v0.6.1-s85-diag-dod-met`

---

#### S86 — BLUETOOTH PRINT INTEGRATION (30.04, четвъртък)
**Тип:** 1 Claude Code (Capacitor + Java/Kotlin native plugin)  
**Време:** ~5-6h (голям scope)  
**Цел:** Native DTM-5811 print без QU Printing app  

**Scope:**
- Capacitor Bluetooth Classic plugin (BT 2.1.1)
- TSPL protocol implementation (`buildTSPL()` от `/root/printer.js.bak.*`)
- Codepage 1251 за кирилица
- 50×30mm етикет layout (beta) + 40×30mm (готов за post-beta)
- Test на реален принтер (DC:0D:51:AC:51:D9, PIN 0000)

**DOD:**
- [ ] APK печата от приложението без external app
- [ ] Кирилица работи (не йероглифи)
- [ ] 5 различни products → 5 етикета без грешка

**Risk:** ⚠️ Native Capacitor plugin разработка изисква Android Studio локално. Ако Тихол няма setup → S86 става S87 (ден по-нататък).

---

### **СЕДМИЦА 2 — CORE OPERATIONS (04.05 - 10.05)**

#### S87 — SALE.PHP REWRITE (понеделник 04.05)
**Тип:** 1 Claude Code (голям scope, sequential)  
**Време:** ~6-8h (МОЖЕ ДА Е 2 СЕСИИ)  
**Цел:** Voice + camera always-live + numpad POS  

**Mockup:** Тихол одобрява ПРЕДИ Code #1 стартира (1h browser chat в неделя)  

**Scope:**
- Voice primary input (BG, continuous=false)
- Camera always-live за barcode scan
- Numpad за numeric input
- Wholesale/retail toggle
- Auto-create продукт при unknown баркод
- Quick-add от history
- Receipt preview

**DOD:**
- [ ] Транзакция от 5 артикула за <30 сек
- [ ] Voice вход тестван
- [ ] Bluetooth print etiket за продаден артикул

**Може да става S87.A + S87.B (split на 2 дни) ако scope-ът надскочи 6h.**

---

#### S88 — TRANSFERS.PHP NEW MODULE (вторник 05.05)
**Тип:** 1 Claude Code  
**Време:** ~4-5h  
**Цел:** Inter-store transfers (5-те магазина на Тихол + ЕНИ multi-store)  

**Mockup:** одобрен от Тихол ПРЕДИ session  

**Scope:**
- New table: `transfers` (from_store, to_store, status, items[])
- Status flow: draft → in_transit → received
- Multi-store routing
- Voice вход за списъка артикули
- Bluetooth print transfer slip

**DOD:**
- [ ] Transfer от store_id=1 към store_id=2 → стока намалява source, расте target
- [ ] Audit log entries
- [ ] Test на 2 магазина на tenant=7

---

#### S89 — INVENTORY V4 + SMART RESOLVER (сряда 06.05)
**Тип:** 1 Claude Code (голям scope)  
**Време:** ~6-8h (може 2 сесии)  
**Цел:** Event-sourced inventory с offline support  

**Scope:**
- New table: `inventory_events` (append-only ledger)
- Smart Resolver (gap detection, conflicts)
- Offline mode (queue, sync on reconnect)
- "Category of the Day" auto-suggestion (PHP логика, не Gemini)
- Barcode count flow

**DOD:**
- [ ] Брой 50 продукта offline → reconnect → sync
- [ ] Conflict detected на 1 артикул → resolver предлага fix
- [ ] CoD logic дава правилна категория за днес

---

#### S90 — DELIVERIES.PHP NEW MODULE (четвъртък 07.05)
**Тип:** 1 Claude Code  
**Време:** ~4h  
**Цел:** Доставки (от доставчик към магазин)  

**Mockup:** одобрен ПРЕДИ session  

**Scope:**
- New table: `deliveries`
- Voice + photo wizard
- OCR invoice scanning (deferred — manual entry за beta)
- Auto-create products при unknown barcode
- Cost price update

**DOD:**
- [ ] Доставка от 20 артикула за <2 мин
- [ ] Стока расте в правилния магазин
- [ ] Audit log

---

#### S91 — ORDERS.PHP NEW MODULE (петък 08.05)
**Тип:** 1 Claude Code  
**Време:** ~4-5h  
**Цел:** Поръчки към доставчици (12 entry points, 11 types, 8 statuses от S77 spec)  

**Mockup:** одобрен  

**Scope:**
- New tables: `supplier_orders`, `supplier_order_items`
- Status flow: draft → confirmed → sent → acked → partial → received → cancelled/overdue
- 6 fundamental questions embedded
- Auto-suggest from low-stock + AI insights

**DOD:**
- [ ] Поръчка създадена и пратена (test)
- [ ] Status flow работи

---

### **СЕДМИЦА 3 — POLISH + ENI ONBOARDING (11.05 - 15.05)**

#### S92 — PROMOTIONS MODULE PHASE B (понеделник 11.05)
**Тип:** 1 Claude Code  
**Време:** ~4-5h  
**Цел:** Phase B = basic auto-apply promo  

**Spec:** `PROMO SPEC FINALIZED` (от 25.04.2026 LOGIC CHANGE LOG)  
- Stack per-item NO, per-cart YES
- Greedy <50ms
- Margin floor 15%
- Касов бон отделен ОТСТЪПКА line
- DB: `promotions` + `promotion_rules` + `promotion_applications`

**DOD:**
- [ ] 1 promo (3+1) auto-applies в sale.php
- [ ] Receipt показва ОТСТЪПКА line

**Phase C (AI suggest) и Phase D (combo+B2B+loyalty) → POST-BETA.**

---

#### S93 — SUPPLIERS.PHP NEW MODULE (вторник 12.05)
**Тип:** 1 Claude Code  
**Време:** ~3-4h  
**Цел:** Supplier management + BRRA API stub  

**Scope:**
- New table: `suppliers`
- Voice вход за нов доставчик
- BRRA API integration (само skeleton за beta)
- Linked to deliveries.php + orders.php

**DOD:**
- [ ] 5 доставчика въведени
- [ ] Поръчка / доставка с supplier_id

---

#### S94 — STRIPE CONNECT VOLUME PACKS (сряда 13.05)
**Тип:** 1 Claude Code + Тихол (Stripe dashboard работа)  
**Време:** ~3-4h  
**Цел:** AI credits packs (€5/€15/€30/€50/€100)  

**Spec:** `AI_CREDITS_PRICING_v2.md` секция Volume пакети  

**Scope:**
- 5 нови Stripe products
- Webhook handler за purchase
- "Купи още кредити" модал в `ai-studio.php`
- INCREMENT в `ai_credits_balance.{bg,desc,magic}_credits`

**DOD:**
- [ ] Test purchase на пакет Mini (€5)
- [ ] Credits увеличени
- [ ] Stripe webhook log

---

#### S95 — ENI ON-BOARDING DAY (четвъртък 14.05)
**Тип:** Тихол + ЕНИ (без Claude Code)  
**Време:** ~4h на място в магазина  
**Цел:** ЕНИ tenant=52 setup + първи реални продажби  

**Activities:**
- Setup tenant=52 + потребители (Ани = seller)
- Import на 100 артикула (CSV или voice)
- Bluetooth printer pair
- Първа реална продажба
- Тренинг на Ани (Лесен режим / life-board.php)

**DOD:**
- [ ] ЕНИ магазин работи end-to-end
- [ ] Ани прави 5 продажби сама
- [ ] 0 critical bugs

---

#### S96 — POST-LAUNCH BUGFIX (петък 15.05)
**Тип:** 2 паралелни Claude Code  
**Време:** ~4-6h  
**Цел:** Fix всичко от S95 ЕНИ feedback  

**Code #1:** UX fixes (Лесен режим)  
**Code #2:** Backend / DB fixes  

**DOD:**
- [ ] All S95 bugs closed
- [ ] ЕНИ магазин stable за weekend

---

## 🛡 RISK MITIGATION

| Risk | Mitigation |
|---|---|
| Code #1 + #2 collision на същ файл | Шеф-чат генерира startup prompt с **explicit "ТВОИ FILES" + "NE PIPAS"** списък |
| Session > 6h → грешки | Стартирай свеж Claude Code terminal на всеки ~5h |
| Migration apply break | Винаги: backup → /tmp/ clone test → up → down → re-up → live (Code #2 pattern) |
| Real entry открива critical bug | S84 е buffer ден за fixes |
| Bluetooth не работи | CSV workaround stays — beta launch без native print, добавяме post-beta |
| ЕНИ launch delay | S96 е buffer; ако S95 fail → push до 18-20.05 |

---

## 📋 PARALLEL SESSIONS PROTOCOL

**1 паралелен chat:**  
- Простота. По default.

**2 паралелни:**  
- ✅ ОК ако файловете 100% disjoint
- ⚠️ Шеф-чат генерира startup prompt с explicit paths
- ⚠️ Rule #19 PARALLEL COMMIT CHECK задължителен

**3 паралелни:**  
- ❌ Само в емergencies (e.g. S82.STUDIO marathon)
- ❌ Висок collision risk
- ❌ Шеф-чат capacity може да не догони 3 потоци

**Препоръка:** S83-S96 = **80% solo Claude Code, 20% 2 паралелни.** Без 3-way паралел.

---

## 🎯 BETA LAUNCH CRITERIA (15.05.2026)

**МАЧНИ преди стартиране:**
- [ ] sale.php работи voice + barcode + Bluetooth print
- [ ] products.php P0 bugs затворени
- [ ] life-board.php / chat.php Лесен/Подробен работят
- [ ] AI Studio backend connected end-to-end
- [ ] transfers.php / deliveries.php / orders.php живи
- [ ] DB schema freeze + diagnostic Cat A=100%
- [ ] Stripe Connect volume packs живи
- [ ] ЕНИ tenant=52 working end-to-end
- [ ] 0 critical bugs от S95

**МОЖЕ ДА ЧАКАТ POST-BETA:**
- Promotions Phase C/D
- OCR invoice scanning (manual entry за beta)
- AI Advisor activation (изисква 90% diagnostic + 1 година data)
- chat.php Detailed advanced features
- inventory CoD AI suggestions
- Schema migrations cleanup (DROP legacy `credits` колона)

---

## 💡 PRINCIPLES за Тихол

1. **1 mockup approval / ден max.** Иначе scope creep.
2. **Спи преди 1:00.** Уморен Тихол → грешни решения. S82 marathon беше изключение, не правило.
3. **1 видим результат / ден.** Real entry, real sale, real transfer — не "12 commits".
4. **Buffer days в schedule.** S84 + S96 са buffers — не пренатоварвай.
5. **ЕНИ launch е fixed.** 14.05 на place. Всичко преди това = preparation.

---

## 📝 ACTIONS ЗА ТИХОЛ ЗА УТРЕ (27.04)

1. ⏰ Спи до 8:00
2. ☕ Кафе
3. 📱 Force-quit RunMyStore app + clear cache на телефона (за да види нощните промени)
4. 🛒 Започни real product entry на tenant=7 — **минимум 50 продукта**
5. 📓 Записвай bugs / counter-intuitive moments в текстов файл
6. 🚫 НЕ стартирай Claude Code сесии докато entry не е приключило
7. 📷 Screenshot на проблеми
8. 📤 Изпращай ми (шеф-чат) bug list когато си готов

---

**КРАЙ НА ПЛАНА**
