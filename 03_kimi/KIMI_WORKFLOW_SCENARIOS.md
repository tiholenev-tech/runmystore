# 🎯 KIMI_WORKFLOW_SCENARIOS — 10-те Production сценария

## Добавка към DOC 07 — ежедневни сценарии които системата трябва да handle-ва

**Източник:** Kimi AI consultation + Тихол review  
**Версия:** 1.0  
**Дата:** 21.04.2026  
**Статус:** Integration target = S85 (sale.php rewrite)

---

## ЗАЩО ТОЗИ ДОКУМЕНТ

DOC 07 (Power + Workflow Защити) покрива 10 ежедневни сценария. Kimi добави 4 нови нюанса + 1 нов сценарий (5.11 — Conversation State Machine) + отделна критика на trial UX ("призрачна карта").

**Този файл съдържа ПЪЛНИЯ expanded set** — за когато правим sale.php rewrite (S85) или AI chat rewrite (S95), чета този файл интегрално.

---

## СЦЕНАРИЙ 1: Грешен бутон при бързане

**Ситуация:** Пешо бърза, тапа "Поръчка" вместо "Продажба".

**Защита:**
- Back бутон винаги видим
- AI detect: "Понякога тапваш Поръчка вместо Продажба. Да ги преместя?"
- **Undo action винаги в последните 30 сек**

---

## СЦЕНАРИЙ 2: Voice грешка в шум

**Ситуация:** Пешо казва "две черни тениски", AI чува "двадесет".

**Защита:**
- Под-закон №1A (показване на транскрипция)
- Confidence < 0.8 → warning yellow
- Fuzzy match на числа (2 ≠ 20 — warning)
- **Sanity check на общата сума:** ако qty × цена е нереалистично (напр. 20 × 40 лв = 800 лв за една артикулна линия) → AI пита: "Сигурен ли си? Това е 20 броя × 40 лв = 800 лв."
- **Threshold:** ако total > 10× средна продажба на tenant → задължителен confirm

---

## СЦЕНАРИЙ 3: Бързащ клиент на касата (Quick Sale Mode)

**Ситуация:** Клиент бърза. Пешо не иска да чака.

**Стандартни защити:**
- Sale е чист SQL — 100ms response
- AI toast е async — след продажбата, не блокира
- При battery-low — AI toast disabled

**Quick Sale режим:**
- **Double tap на [Продай]** = мигновена продажба на последния артикул с последна цена
  - Полезно при repeat клиенти
  - Записва се в `sale_type='quick_repeat'` за аудит
- **Barcode scan = auto-add в кошница** без потвърждение
  - Scan → веднага кошница → scan → scan → [Плати]
  - Не изисква tap "Добави" след всяко
- **Swipe to confirm** (алтернатива на tap)
  - Ляво = отказ, дясно = потвърждение
  - По-бързо с палец на ръба

---

## СЦЕНАРИЙ 4: Стари данни (offline)

**Ситуация:** Телефонът е offline от 2 часа. Пешо не знае.

**Защита:**
- Offline detection → banner "Работиш офлайн"
- Sales работят, queued за sync
- Stats показват (cached) индикатор
- При online → auto-sync + confirm

---

## СЦЕНАРИЙ 5: Подобна стока (duplicate)

**Ситуация:** Пешо добавя "Nike 42 черна" но вече има "Nike 42 черен".

**Защита:**
- AI fuzzy match на добавяне → "Имаш ли предвид 'Nike 42 черен'?"
- Merge option
- ≥85% similarity → auto-merge, <85% → pita once, remembers forever

---

## СЦЕНАРИЙ 6: Matrix picker грешка (Big Tap + Recent Bias)

**Ситуация:** Пешо добавя артикул с размери, случайно въвежда 10 броя вместо 1 за XS.

**Защита:**
- Preview на matrix с totals
- Warning ако total > 100 за един артикул
- Undo last edit
- **Big tap buttons за variation selection:**
  - В sale flow когато Пешо избира размер/цвят — grid с thumbnails, не dropdown
  - Min tap target 60px × 60px (iOS HIG × 1.5)
- **Recent bias sorting:**
  - Ако Пешо продава черното XL по-често → то е първо в grid
  - Формула: `ORDER BY sale_count_30d DESC, alphabetical`

---

## СЦЕНАРИЙ 7: Нов служител

**Ситуация:** Seller за първи път, не знае как работи.

**Защита:**
- First login → AI voice onboarding (2 минути)
- "Как искаш да питаш за помощ? Кажи 'помогни' по всяко време"
- Всеки screen има "Как?" бутон

---

## СЦЕНАРИЙ 8: Сезонна промяна

**Ситуация:** Зима → пролет. Цените на якета падат. Пешо не променя.

**Защита:**
- AI seasonal detector: "Януарски артикули на склад, пролет идва. Обмисли намаление."
- Автоматичен отчет "застояла зимна стока"
- AI архивира стари като 'seasonal_archive'

---

## СЦЕНАРИЙ 9: 2 каси едновременно (+ Multi-Store)

**Ситуация:** Пешо + продавачка + N магазини + online работят паралелно. Race condition?

**Защита:**
- Row-level locking (`SELECT FOR UPDATE`)
- Idempotency keys
- Negative stock guard
- **Pusher/Ably broadcast** към tenant channel → всички устройства update < 1s

**User-facing message при конфликт:**
- Ако 2 каси продават последната бройка едновременно:
  - Първата каса → успех
  - Втората каса → toast: "Съжалявам, артикулът току-що беше продаден. Има ли подобен?"
  - AI автоматично предлага подобни (fuzzy match size/color/category)

**Store-level pusher channel** за realtime sync:
- При sale в каса А → каса Б получава update < 1 сек
- Намалява race conditions от 0.1% на 0.01%

⚠️ За пълна multi-device архитектура → `REAL_TIME_SYNC.md`.

---

## СЦЕНАРИЙ 10: Празник / промоция (8-ми март, BF, Коледа)

**Ситуация:** Разпродажба, 200 продажби в деня.

**Защита:**
- Promotion engine — правила (20% off, 50% off)
- Margin warning ако > 30% отстъпка
- AI alert след промо: "Продажбите +120% — проверявай stock всеки час"
- Cart abandonment tracking
- **Degraded mode:** при peak load, AI chat disabled, pills cached 15 мин
- **Batch operations:** 10 продажби → 1 AI toast (обобщение)

---

## СЦЕНАРИЙ 11 (NEW): Voice последователност — "Още едно"

**Ситуация:** Пешо казва: "Продай черна тениска L". AI продава. Пешо казва: "Още едно".

**Проблем:** Ако AI ходи към Gemini за "още едно" → латенция 1-3 сек, cost.

**Защита — Conversation State Machine:**
- PHP помни последния intent в `$_SESSION['last_voice_intent']`
- "още едно", "пак", "и още" → recognized by PHP без AI call
- `$_SESSION['last_product_id']` → +1 към кошницата
- **0 AI cost, < 50ms response**

Това е ключова **Закон №2 optimization** — месечно AI cost намалява с 15-20%.

---

## 🔋 BATTERY THRESHOLDS (Kimi конкретизация)

**3-нивова battery policy** с конкретни стойности:

### Ниво 1: > 40% — Normal
- Всички features активни
- Camera auto-start в sale.php
- Live voice transcription
- Animation на pills

### Ниво 2: 15-40% — Low
- Camera не стартира автоматично (ръчно tap)
- Animations off
- Refresh interval: 5 min вместо 30 sec
- Push notifications по-нисък приоритет

### Ниво 3: < 15% — Critical
- Camera disabled напълно
- Voice disabled (user може да force)
- Само cached data
- Background sync off
- AI chat disabled — само pills/signals от cache
- Голям warning banner: "Батерия критична. Заредете телефона."

**Fail metric:** Ако RunMyStore тегли > 5% батерия на час при активна употреба → fail.

---

## 🎪 "ПРИЗРАЧНА КАРТА" КРИТИКА НА TRIAL (Kimi worry)

**Рискът:** Пешо въвежда карта на ден 29 → минава от месец 1 (PRO безплатно) към месец 2-4 (PRO функции на START цена). На ден 120 (край trial) → ако избере START → губи PRO функции на които е свикнал → **разочарование**.

**Mitigation стратегии:**

1. **Transparent countdown:** От ден 100 нататък, Life Board показва:
   > "Имаш 20 дни PRO. След това: START (€19) или PRO (€49)."

2. **Feature preview removal:** От ден 90, някои PRO функции започват да се показват с "ще бъде START функция". Пешо се адаптира постепенно.

3. **PRO седмица като retention:** На 3-4 месеца интервал, дава 2 седмици PRO функции безплатно на START/FREE tenants. Напомня какво липсва.

4. **НЕ правим ghost pills** (заключено решение К2) — те влошат доверие повече.

**Метрика:** `trial_end_to_downgrade_to_free_days` per tenant. Ако > 30% от cohort-а downgrade в първите 14 дни след trial → preview removal е болезнен, адаптираме.

**Не е рисково ако:**
- Ден 29 capture на карта е explicit
- Feature differences ясни от ден 1 в Plans screen
- Downgrade path прост и не манипулативен

---

## 📋 ОБОБЩЕНИЕ — WORKFLOW ЗАЩИТИ

| Защита | Покрива |
|---|---|
| Undo last 30s | Грешни тапове |
| Voice transcript display | Voice грешки |
| Voice sanity check (qty × price) | Unrealistic voice numbers |
| Async AI toasts | Бавен UX |
| Offline queue (IndexedDB) | Мрежови проблеми |
| Fuzzy match duplicates (≥85%) | Data hygiene |
| MySQL FOR UPDATE | Race conditions |
| Idempotency keys | Double submits |
| Pusher broadcast | Multi-device sync |
| Quick Sale (double tap, swipe) | Бързащ клиент |
| Big tap buttons + recent bias | Mobile UX |
| Conversation State Machine | Voice cost optimization |
| Battery-aware features | Telephone life |
| Transparent trial countdown | Retention без призрачна карта |

---

**КРАЙ НА KIMI_WORKFLOW_SCENARIOS.md**
