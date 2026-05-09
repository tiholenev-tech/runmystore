# 📋 HANDOFF — Deliveries Design Spec v3

**Дата:** 09.05.2026 (петък вечер)
**Шеф-чат:** деsign session за модул "Доставки"
**Beta deadline:** 14-15.05.2026 (5 дни)
**Commit:** `62a0a0c` на `main`

---

## 🎯 КАКВО БЕШЕ НАПРАВЕНО

Финализиран **DELIVERIES_FINAL_v3_COMPLETE.md** (385KB, 10 552 реда, 50 секции, 11 части) — canonical design + logic + connections spec за модул "Доставки".

Документът обединява:
- Лесен режим (Пешо) — Inbox style + 3 AI карти + 4-options sheet (OCR/Voice/Manual/Import)
- Разширен режим (Митко) — KPI strip + status tabs + 5 view modes + 17-section filter accordion + 40 AI signal темы
- Detail на доставка (4 таба: Артикули/История/Финанси/Документи)
- Detail на доставчик (shared компонент с orders/suppliers)
- Bulk операции (long-press) + audit append-only
- Връзки с 10 модула (двупосочни)
- 14 DB таблици + 20 edge cases + perf budgets + a11y + beta checklist
- 20 SACRED принципи

---

## 📁 ФАЙЛОВЕ

| Файл | Локация | Размер |
|---|---|---|
| `DELIVERIES_FINAL_v3_COMPLETE.md` | `/var/www/runmystore/` (committed) | 385KB |
| `DELIVERIES_FINAL_v2.md` | `/mnt/user-data/outputs/` (предишна версия, НЕ committed) | 146KB |
| `DELIVERIES_EASY_MODE_v1.md` | `/mnt/user-data/outputs/` (фрагмент, НЕ committed) | 31KB |

**v3 заменя v1 + v2.** Те остават само като reference в outputs (не в git).

---

## ✅ ГОТОВО

- [x] Философия + 5 закона + UX принципи (Част 1)
- [x] Глобален Connection Map с DB полета и deep links (Част 1)
- [x] API endpoint mapping — 22 endpoints (Част 1)
- [x] Цветова семантика mapping към 6 hue класа (Част 1)
- [x] Лесен режим — главен екран, AI карти, op-button, empty/loading (Част 2A)
- [x] OCR 6-stupkov flow с Confidence Routing >92%/75-92%/<75% (Част 2B)
- [x] Voice flow с STT engine choice LOCKED (Част 2B)
- [x] Manual flow Lightspeed/Microinvest pattern + 4 paths (Част 3A)
- [x] Импорт + email forward + Mini-wizard + Smart Pricing (Част 3B)
- [x] Разширен главен — KPI + tabs + view modes + sort + chips (Част 4A)
- [x] Filter drawer 17 секции + Search + 40 AI signals + role gate (Част 4B)
- [x] Detail на доставка 4 таба + Detail на доставчик shared (Част 5A)
- [x] Меню (☰) + bulk + audit + notifications (Част 5B)
- [x] Multi-store split + offline 4 нива + cost change + lost demand + +40% boost + TSPL print (Част 6A)
- [x] DB schema 14 таблици + 20 edge cases + perf + a11y + beta checklist (Част 6B)
- [x] Git commit `62a0a0c` push на main

---

## 🚧 ОСТАВА (преди ENI beta 14-15.05)

### Deliveries module — 0% implementation
- [ ] `deliveries.php` главен (лесен + разширен templates)
- [ ] `deliveries.php?id=X` детайл с 4 таба
- [ ] `partials/supplier-detail.php` shared компонент
- [ ] `partials/deliveries-easy.php` rendering
- [ ] `partials/deliveries-extended.php` rendering
- [ ] 22 API endpoints (`api/deliveries/*`, `api/ocr/*`, `api/voice/parse`, `api/products/quick-create`, `api/suppliers/match`, `api/orders/match`)
- [ ] OCR worker (Gemini Vision)
- [ ] Email forward poller (cron 5min, IMAP)
- [ ] AI signal Selection Engine (5 стъпки PHP)
- [ ] Service Worker за offline cache (5MB)
- [ ] Bluetooth printer integration (DTM-5811 TSPL)
- [ ] Push notification setup (FCM)

### DB migration (14 таблици/ALTER-а)
- [ ] `deliveries`, `delivery_items`, `delivery_item_stores`, `delivery_events`
- [ ] `scanner_documents`, `inventory_confidence`, `lost_demand`
- [ ] `saved_filters`, `recent_searches`, `pricing_rules`, `cost_history`, `notifications`
- [ ] ALTER `suppliers` (12 нови полета), ALTER `products` (3 нови полета)

### ENI tenant setup
- [ ] 5 stores (Витоша/Цариградско/Студентски/Овча купел/Банкя)
- [ ] Default pricing rule (50% margin, .90 rounding)
- [ ] Email forward `eni-deliveries@runmystore.ai`
- [ ] Bluetooth printer paired (MAC DC:0D:51:AC:51:D9)
- [ ] BG dual pricing ON
- [ ] AI signals Phase 1 (0% AI, 100% template)

### Други модули в beta scope
- [ ] `orders.php` — 0% (трябва ORDERS_FINAL_v1.md spec)
- [ ] `transfers.php` — 0% (post-beta)
- [ ] sale.php S87E patch (8 bugs — отделна сесия)
- [ ] products.php S95 wizard ČÁST 1.2 voice nav

---

## 🔑 КЛЮЧОВИ РЕШЕНИЯ ВЗЕТИ В СЕСИЯТА

| # | Решение |
|---|---|
| 1 | **Toggle per-модул**, не глобален; localStorage `rms_mode_deliveries` |
| 2 | **Inbox style** в лесен — НЕ Dashboard; max 3 AI карти + 1 op-button |
| 3 | **4 entry points** за получаване: 📷 OCR · 🎤 Voice · ⚡ Scan · 📥 Import |
| 4 | **OCR Confidence Routing**: >92% AUTO_ACCEPT / 75-92% smart UI / <75% fallback |
| 5 | **Нов артикул в доставка** = Опция Б (Ghost product, `is_complete=0`, mini-wizard inline) |
| 6 | **Терминология "Печалба %"** — НИКОГА "наценка"; формула `(retail-cost)/retail*100` |
| 7 | **Status tabs 3 мета** (Чакат/Готови/История) от 6 native статуса |
| 8 | **Primary view "По дата"**, не "По доставчик" |
| 9 | **17 секции filter accordion** drawer (right edge side-sheet) |
| 10 | **40 AI signal темы** в 4 категории (track/receive/after/analysis) с plan+role gate |
| 11 | **Detail с 4 таба**: Артикули/История/Финанси/Документи |
| 12 | **Detail на доставчик** = shared компонент `partials/supplier-detail.php` |
| 13 | **Bulk = long-press 500ms** → q-magic header + 3 actions (Цена/Бройка/Още) |
| 14 | **Audit append-only** `delivery_events` — никога DELETE/UPDATE; 19 event_type ENUM |
| 15 | **Offline 4 нива**: Online → Limited → Cached → Blind receive |
| 16 | **Hidden Inventory +0.40 boost** при finalize; никога visible на Митко (Закон №6) |
| 17 | **TSPL print** автоматичен с BG dual pricing (€ + лв @ 1.95583) до 08.08.2026 |
| 18 | **БЕЗ beta/post-beta разделяне** в кода — правим всичко сега |
| 19 | **Phased AI rollout**: Фаза 1 (бета) = 0% AI, Фаза 2 = 30%, Фаза 3 = 80% |
| 20 | **STT LOCKED** — commits 4222a66 + 1b80106; не пипай `_wizPriceParse` |

---

## ⚠ SACRED — НИКОГА ДА НЕ СЕ ПРОМЕНЯТ

1. Закон №1 — Пешо не пише
2. Закон №2 — PHP смята, AI вокализира
3. Закон №3 — Никога "Gemini" в UI
4. Закон №6 — `confidence_score` НИКОГА visible на Митко
5. Закон №11 — DB names canonical
6. Bottom nav 4 икони locked
7. 6 hue класа only
8. Sacred Neon Glass dark mode
9. Mobile-first 375px (Z Flip6)
10. 0 emoji в UI (SVG only)
11. BG dual pricing до 08.08.2026
12. No `ALTER ADD COLUMN IF NOT EXISTS` в MySQL 8
13. Audit append-only
14. Soft delete only
15. priceFormat() + t() винаги
16. STT LOCKED
17. Phased rollout AI

---

## 💡 ПРЕПОРЪКИ ЗА СЛЕДВАЩА СЕСИЯ

### Приоритет 1 — DB migration (1-2 часа Claude Code)
Създай `migrations/2026_05_10_deliveries_schema.sql` с всички 14 таблици/ALTER-а. Тествай на staging първо. Pattern: `PREPARE/EXECUTE` с `information_schema` check (не `IF NOT EXISTS`).

### Приоритет 2 — `partials/supplier-detail.php` (3-4 часа)
Shared компонент = първи. Ще се ползва в orders, deliveries, suppliers. Build it once, use everywhere.

### Приоритет 3 — `deliveries.php` главен скелет (4-6 часа)
- Header + voice-bar + mode-toggle + bottom nav (всичко вече канонично)
- Лесен mode: empty state + AI signals placeholder + op-button
- Разширен mode: KPI placeholder + status tabs + cards list
- БЕЗ детайл, БЕЗ filters, БЕЗ AI logic още
- Цел: roundtrip — отвори, тапни op-button, виж sheet, затвори

### Приоритет 4 — OCR flow (8-12 часа Claude Code)
- Camera view + capture
- Upload към Gemini Vision (2 keys rotation)
- Confidence Routing 3 пътя
- Smart Review UI
- Finalize PHP transaction

### Приоритет 5 — ORDERS spec (4-5 часа шеф-чат)
Преди да пишем код — ORDERS_FINAL_v1.md по същия 20-точков шаблон от Част 50 на v3 документа. Без това deliveries няма с какво да се връзва (auto-link на supplier_orders → deliveries).

---

## 📂 ВРЪЗКИ КЪМ ДОКУМЕНТА

- **Локално:** `/var/www/runmystore/DELIVERIES_FINAL_v3_COMPLETE.md`
- **GitHub:** https://github.com/tiholenev-tech/runmystore/blob/main/DELIVERIES_FINAL_v3_COMPLETE.md
- **Commit:** `62a0a0c`

---

## 🗂 РЕFERENCE-И КЪМ ДРУГИ ДОКУМЕНТИ

При имплементация — чети **в този ред**:

1. `DESIGN_PROMPT_v2_BICHROMATIC.md` (16 закона)
2. `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` (Bible, tokens, components)
3. `mockups/P13_bulk_entry.html` (visual canon за wizard/accordion)
4. `mockups/P10_lesny_mode.html` (visual canon за лесен режим)
5. **`DELIVERIES_FINAL_v3_COMPLETE.md`** (този, логика + връзки)
6. `INVENTORY_HIDDEN_v3.md` (Закон №6 confidence rules)
7. `MASTER_COMPASS.md` (parallel sessions координация)

---

**Време изхарчено:** ~4 часа шеф-чат (от 16:40 до 19:55).
**Резултат:** Production-ready spec за beta launch.
**Готовност:** Документът покрива абсолютно всичко — design, logic, DB, perf, a11y, edge cases, beta checklist. Може да започне Claude Code имплементация веднага.

---

**Шеф-чат session приключи. Бъди здрав.** 🫡
