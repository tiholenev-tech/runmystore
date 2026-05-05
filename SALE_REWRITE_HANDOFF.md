# 🎨 SALE.PHP REWRITE — HANDOFF ЗА CLAUDE CODE

**Сесия:** S88.SALE.UI_REWRITE
**Дата:** 2026-04-28
**Файл:** `/var/www/runmystore/sale.php` (текущо 2297 реда)
**Mockup:** `sale-mockup-v4.html` (приложен в същата папка)
**Estimated time:** 4-6 часа

---

## 🚨 КРИТИЧНИ ОГРАНИЧЕНИЯ (READ FIRST)

### Закон №0 — Scope lock
- **ПИПАШ САМО** `/var/www/runmystore/sale.php`
- **НЕ ПИПАШ** `products.php` (друг Claude Code работи там паралелно — git conflict ще е fatal)
- **НЕ ПИПАШ** `partials/header.php`, `partials/bottom-nav.php`, `css/shell.css`, `css/theme.css` (те са shared и работят правилно)
- **НЕ ПИПАШ** `product-save.php`, `compute-insights.php`, `selection-engine.php`, `chat.php`
- **НЕ git pull** ако друг session работи паралелно — провери `git status` преди commit

### Закон №1 — Координация с другия Claude Code
- Преди всеки commit: `git status` за да провериш дали има неcommit-нати промени от другия chat
- При неясност — **не commit-вай**, попитай Тихол
- Commit message формат: `S88.SALE: [описание]` — за да се отличава от products работата

### Закон №2 — DESIGN_SYSTEM v2.0 е еталон
- Чети `/var/www/runmystore/DESIGN_SYSTEM.md` ПРЕДИ работа
- Чети `/var/www/runmystore/chat.php` около ред 260-500 за Glass + shine + glow CSS pattern
- НЕ измисляй CSS — копирай от референтните файлове

### Закон №3 — Backup ПРЕДИ всяка промяна
```bash
cp /var/www/runmystore/sale.php /var/www/runmystore/sale.php.bak.s88_$(date +%H%M)
```

### Закон №4 — Запази PHP блоковете 1:1
- Lines 1-150 (auth, AJAX endpoints, `quick_search`, `add_sale`, `wholesale`, `parking`)
- НЕ пипай PHP логиката — само CSS + HTML body + adapt JS селекторите ако са се променили

---

## 📋 ЗАДАЧАТА — 2 ФАЗИ

### ФАЗА 1: VISUAL REWRITE по mockup-а

Замени CSS секцията + HTML body на `sale.php` спрямо `sale-mockup-v4.html`. Запази PHP + JS логиката.

**Mockup hue:** indigo (`--hue1:255` / `--hue2:222`) — НЕ зелено (145/165). Mockup-ът вече е с правилния hue.

**Mockup layout:**
1. Header (`<?php include 'partials/header.php'; ?>`) — 1:1 запазено от текущ
2. Camera (`.cam` 80px) — laser, corners, badge "СКЕНЕР АКТИВЕН", mode "ДРЕБНО/ЕДРО"
3. Search bar — `.search-box` (placeholder) + `.s-btn.mic` + `.s-btn.kbd`
4. Parked row — `.parked` chips
5. Cart (`.cart` flex:1 scrollable) — `.glass.sm.ci` items с shine
6. Pay row — `.pay-main` + `.park-btn`
7. Numpad 4×4 — `.numpad` + `.np` keys
8. Bottom nav (`<?php include 'partials/bottom-nav.php'; ?>`) — 1:1

**2 теми:** dark (default) + light (data-theme="light"). Mockup-ът показва и двете.

### ФАЗА 2: ПЪЛЕН BUG AUDIT на sale.php

След visual rewrite, провери ВСЯКА функция за бъгове. Списък по-долу — потвърди статус на всеки bug, fix-вай namerените.

---

## 🔄 DOM ID MAPPING (V4 → ТЕКУЩ sale.php)

JS логиката в текущия sale.php използва тези ID-та. **При rewrite задължително ги запази**, иначе целият JS се чупи.

### Header / Camera section:
| Текущ ID | V4 mockup еквивалент | Запази ли? |
|---|---|---|
| `#camHeader` | `.cam` (top section) | ✅ Преименувай към `.cam` или дай ID `id="cam"` |
| `#cameraVideo` | няма в mockup-а | ✅ Запази `<video>` element за scanner |
| `#camTitle` | `.cam-badge` или нов `.cam-title` | ✅ Запази ID за PHP `$page_title` |
| `#btnParkedBadge` | `.cam-btn` за паркирани | ✅ |
| `#parkedCount` | badge число вътре | ✅ |
| `#btnWholesale` | `.cam-btn` 👤 | ✅ |
| `#themeToggle` | `.cam-btn` 🌙/☀️ | ✅ |
| `#greenFlash` | overlay flash effect | ✅ |

### Search section:
| Текущ ID | V4 mockup еквивалент | Запази ли? |
|---|---|---|
| `#searchDisplay` | `.search-box-txt` | ✅ Преименувай или сложи ID |
| `#searchInput` | hidden input в search-box | ✅ |
| `#searchResults` | dropdown под search-bar | ✅ |
| `#btnVoiceSearch` | `.s-btn.mic` | ✅ |
| `#btnKeyboard` | `.s-btn.kbd` | ✅ |
| `#nfPopup` | "няма такъв артикул" toast | ✅ |
| `#discountChips` | discount overlay | ✅ |

### Cart / Pay section:
| Текущ ID | V4 mockup еквивалент | Запази ли? |
|---|---|---|
| `#cartZone` | `.cart` container | ✅ |
| `#cartEmpty` | empty state inside cart | ✅ |
| `#actionBar` | `.pay-row` | ✅ |
| `#btnPay` | `.pay-main` | ✅ |
| `#btnConfirm` | OK button за qty | ✅ |
| `#payAmount` | total в pay-main | ✅ |

### Payment overlay:
| Текущ ID | V4 mockup payment screen еквивалент |
|---|---|
| `#payOverlay` | `.app.pay` container |
| `#paySheet` | wrapper |
| `#payDueAmount` | `.total-val` |
| `#payRecvAmount` | `.field.gave input` |
| `#payChangeAmount` | `.resto-val` |
| `#payChangeBox` | `.field.resto` |
| `#cashSection` | `.banknotes` row |

### Numpad / Keyboard:
| Текущ ID | V4 mockup еквивалент |
|---|---|
| `#numpadZone` | `.numpad` |
| `#keyboardZone` | АБВ keyboard overlay (toggle от kbd бутона) |
| `#ctxLabel` | контекст labelа над numpad |

### Voice / Recording:
| Текущ ID | V4 mockup еквивалент |
|---|---|
| `#recOv` | voice overlay container |
| `#recDot` | червена точка pulse |
| `#recLabel` | "● ЗАПИСВА" |
| `#recHint` | hint text |
| `#recTranscript` | rec-trans box |
| `#recSend` | "Изпрати →" button |
| `#recCancel` | cancel X |

### Parking / Loyalty:
| Текущ ID | V4 mockup еквивалент |
|---|---|
| `#parkedOverlay` | overlay при tap на 🅿️ |
| `#parkedContainer` | списък на паркирани |
| `#lpPopup` | loyalty point popup |
| `#lpDisplay` | LP display |
| `#lpTitle` | LP заглавие |

### Wrapper:
| Текущ ID | V4 еквивалент |
|---|---|
| `#saleWrap` | `.app` или `.app .screen-sale` |

---

## 🎯 JS HANDLERS КОИТО ТРЯБВА ДА РАБОТЯТ СЛЕД REWRITE

Тествай всяка функция — всички трябва да работят:

| Функция | Какво прави | Тест |
|---|---|---|
| `addToCart(p)` | Добавя продукт в кошницата | Сканирай или избери → се появява |
| `removeItem(idx)` | Маха item от кошницата | Tap на × до item |
| `numPress(key)` | Numpad button click | Натисни цифра — пише се в search/qty/received |
| `numOk()` | OK на numpad | Натисни OK с код "ABC-1042" — намира продукт |
| `kbPress(key)` | Keyboard letter press | АБВ режим, пиши име |
| `toggleKeyboard()` | Toggle АБВ ↔ numpad | Tap АБВ — keyboard излиза |
| `doSearch(q)` | AJAX call към quick_search | Пиши код — резултати се появяват |
| `triggerSearch()` | Debounce search 300ms | Пиши и спри — извиква doSearch |
| `closeSearchResults()` | Скрива dropdown | Click извън |
| `setNumpadCtx(ctx)` | Превключва code/qty/received | Auto при отваряне на разни overlay |
| `selectCartItem(idx)` | Избира item за edit qty | Tap item — qty mode |
| `applyDiscount(pct)` | Прилага % отстъпка | Tap 10% chip |
| `closeDiscount()` | Затваря discount chips | × |
| `openPayment()` | Отваря payment overlay | Tap ПЛАТИ |
| `closePayment()` | Затваря payment | Back / esc |
| `confirmPayment()` | Финализира продажба | ПРОДАЙ И ОТПЕЧАТАЙ |
| `payBanknote(amt)` | Добавя банкнота | Tap "50" |
| `parkSale()` | Паркира кошницата | Tap 🅿️ park |
| `saveParked(idx)` | Записва паркирана | Auto при park |
| `openParked()` | Отваря паркирани | Tap 🅿️ badge |
| `closeParked()` | Затваря паркирани | × |
| `openWholesale()` | Едро mode toggle | Tap 👤 |
| `closeWholesale()` | Връща дребно | × |
| `selectClient(id)` | Избира клиент за едро | Tap client |
| `handleBarcode(code)` | Сканиран баркод | Camera scan |
| `scanLoop()` | Camera scanner loop | Фон |
| `flashCamScan()` | Зелена светкавица при scan | Auto |
| `greenFlash()` | Голям green flash | След successful add |
| `handleVoiceResult(text)` | Voice STT result | После voice mic press |
| `openLpPopup(...)` | Loyalty popup | Auto след продажба |
| `closeLpPopup()` | Затваря LP | × |
| `confirmLpPopup()` | LP confirm | OK |
| `lpNum(n)` | LP numpad input | Numpad press |
| `initTheme()` | Theme init | Page load |
| `s87v3_init()` | S87 v3 animations | Page load |
| `render()` | Re-render cart | Auto след промени |
| `getTotal()` | Сума на кошницата | Display logic |
| `getItemCount()` | Брой items | Display logic |
| `fmtPrice(amt)` | Currency format | Всякъде |
| `esc(s)` | HTML escape | Безопасност |
| `beep(freq, dur)` | Audio beep | След scan |
| `ching()` | Cash register sound | След продажба |

---

## 🐛 BUG LIST — провери всеки

### Първостепенен bug (потвърден от Тихол):

**Bug #1: ТЪРСАЧКАТА НЕ РЕАГИРА**
- Симптом: tap на search полето или numpad натискане → нищо не се случва
- Възможни причини:
  - JS грешка преди setup на handlers (отвори console и виж error)
  - `quick_search` AJAX endpoint връща 500 (test: `curl 'http://164.90.217.120/sale.php?action=quick_search&q=test'` с auth cookie)
  - Z-index conflict — друг element покрива search-bar (DevTools inspect)
  - Capacitor specific — `e.preventDefault()` blocks tap?
- Action: добави **on-screen overlay logging** като в S87 (Тихол не може Chrome inspect на телефон):
  ```js
  function debugLog(msg){
    const dbg = document.getElementById('dbgOverlay') || (() => {
      const d = document.createElement('div');
      d.id='dbgOverlay';
      d.style.cssText='position:fixed;top:50px;left:8px;right:8px;max-height:200px;overflow-y:auto;background:rgba(0,0,0,.8);color:#0f0;font:10px monospace;padding:8px;z-index:9999;border-radius:8px';
      document.body.appendChild(d);
      return d;
    })();
    const t = new Date().toTimeString().slice(0,8);
    dbg.innerHTML += `<div>[${t}] ${msg}</div>`;
    dbg.scrollTop = dbg.scrollHeight;
  }
  // Използвай: debugLog('search tap'), debugLog('numPress: ' + key), debugLog('AJAX response: ' + JSON.stringify(r).slice(0,80))
  ```
- Toggle с long-press на брандa за да не пречи в production

### Други bugs за проверка (от SESSION_S88_FULL_HANDOFF.md):

**Bug #2: 3 broken DB columns в sale.php** (commit `9f0d2bc` твърди че е fixed — verify)
- Тест: направи 1 продажба → провери че `sales` ред има правилни:
  - `total` (не `total_amount`)
  - `status='canceled'` (не `cancelled`)
  - `unit_price` в sale_items (не `price`)
  - `quantity` в sale_items (не `qty`)
- DB columns ground truth (от userMemories на Тихол):
  - `products.code` (NOT sku)
  - `products.retail_price` (NOT sell_price)
  - `inventory.quantity` (NOT qty)
  - `inventory.min_quantity` (NOT min_stock)
  - `sales.status='canceled'` (one L)
  - `sales.total` (NOT total_amount)
  - `sale_items.unit_price` (NOT price)
- Винаги `DB::run()` / `DB::get()` — НЕ raw `$pdo`

**Bug #3: Camera scanner stop/start lifecycle**
- Test: отвори sale.php → camera работи → отиди на друг tab → върни се → camera още работи?
- Test: scan barcode → се добавя в cart → сканирай същия пак → qty +1 (не дублиран item)

**Bug #4: Voice recognition language**
- Test: tap mic → говори "найк" → STT recognise-ва на bg-BG?
- Code: `recognition.lang = 'bg-BG'`, `recognition.continuous = false`
- `innerText` НЕ `innerHTML` за `#recTranscript` (memory rule)

**Bug #5: Parking — паркирана кошница се връща правилно**
- Test: добави 3 артикула → park → отвори друга кошница → върни park 1 → същите 3 артикула, същите цени, същите qty?
- Verify: `localStorage` или DB persistence между reload-и

**Bug #6: Wholesale toggle**
- Test: tap 👤 → избери client едро → продажба използва `wholesale_price`
- Bug: понякога остава едро mode когато трябва да е дребно (memory от паметта на Тихол)

**Bug #7: Discount chips**
- Test: 5%/10%/15%/20% → правилно намалява total
- Test: tap × → discount се маха
- Edge case: 100% discount = 0 EUR — позволено?

**Bug #8: Numpad context switching**
- Test: tap qty button → numpad context = qty → numpad работи за qty
- Test: tap pay → context = received
- Test: clear → връща се на code

**Bug #9: Print bridge към DTM-5811**
- Test: завърши продажба → принтерът отпечатва бележка
- Code: TSPL команди, codepage 1251 за BG
- Address: `DC:0D:51:AC:51:D9`

**Bug #10: EUR/BGN dual display**
- Закон №4 (BIBLE): до 8.8.2026 mandatory dual display
- Test: всички цени показват `€X.XX` (а ако е до Aug 8 — и `(Y.YY лв)` отдолу)
- Function: `priceFormat($amount, $tenant)` — НЕ hardcoded "лв"/"€"
- Rate: 1.95583

**Bug #11: i18n compliance**
- Закон №2: всички UI текстове през `t('key', $tenant->lang)` или `$tenant['language']` check
- Hardcoded "Продажба", "ПЛАТИ", "Бързи банкноти" — обвий в `t()`

**Bug #12: Theme toggle persistence**
- Test: switch theme → reload → същата тема остава
- localStorage key: `rms-theme` или подобно

**Bug #13: Safe-area insets (notch / home indicator)**
- Test: на iPhone X+ — header не е под notch, bottom nav не е под home indicator
- CSS: `env(safe-area-inset-top)`, `env(safe-area-inset-bottom)`

**Bug #14: Animations performance**
- Test: cardin animation при добавяне на item — плавно, не lag
- Test: scanner laser — плавно, не jerky
- Test: theme switch — плавна transition, не flash

---

## ✅ FINAL TEST CHECKLIST

Преди commit преминаваш през всичко това:

### Visual:
- [ ] Mockup hue: индиго (255/222), не зелен
- [ ] 4 spans на всеки glass card (shine + shine-bottom + glow + glow-bottom)
- [ ] Pills 100px на: pay-main, parked, banknotes, search-box
- [ ] Body 3-layer radial gradient + noise overlay
- [ ] Montserrat font 400/500/600/700/800/900
- [ ] cardin animation на cart items
- [ ] 2 теми (dark default + light) — toggle работи
- [ ] Header (rms-header) 1:1 от partials/header.php
- [ ] Bottom nav (rms-bottom-nav) 1:1 от partials/bottom-nav.php
- [ ] Safe-area insets навсякъде (`env(safe-area-inset-top/bottom)`)

### Functional (всеки JS handler):
- [ ] Numpad → search → AJAX → results → tap → addToCart
- [ ] Voice mic → STT → search → addToCart
- [ ] Camera scanner → barcode → addToCart
- [ ] АБВ keyboard toggle → пиши имена
- [ ] Cart qty +/- бутони
- [ ] Cart × за премахване на item
- [ ] Discount 5/10/15/20%
- [ ] Pay → overlay → банкнота → ресто → продай → принт
- [ ] Park → отвори park → върни
- [ ] Edро ↔ дребно toggle
- [ ] Theme switch persists на reload

### DB integrity:
- [ ] sales.total попълнено правилно
- [ ] sale_items.unit_price + quantity
- [ ] inventory.quantity намалява при продажба
- [ ] sales.status='canceled' при отказ

### Performance:
- [ ] No lag при добавяне на item
- [ ] No memory leak при rapid scan (10+ артикула)
- [ ] Camera lifecycle clean (stop при unmount)

---

## 🚀 DEPLOY ИНСТРУКЦИИ

```bash
# 1. Backup
cp /var/www/runmystore/sale.php /var/www/runmystore/sale.php.bak.s88_$(date +%Y%m%d_%H%M)

# 2. Edit sale.php (apply rewrite)
nano /var/www/runmystore/sale.php  # или твоя tool

# 3. Lint check
php -l /var/www/runmystore/sale.php

# 4. Координация — провери дали друг чат работи
cd /var/www/runmystore && git status
# Ако има неcommit-нати промени които НЕ са твои → STOP и попитай Тихол

# 5. Test на телефон (Capacitor APK)
# Тихол ще тества → потвърди че всичко работи

# 6. Commit + push (САМО след Тихол OK)
cd /var/www/runmystore
git add sale.php
git commit -m "S88.SALE: Visual rewrite по mockup + bug audit. Hue=indigo, 4 spans glass, search overlay logging."
git push origin main
```

**КРИТИЧНО:** НЕ `git add .` — само `git add sale.php`. Друг чат има промени по products.php.

---

## 📞 ПРИ ПРОБЛЕМ

- При unclear UX/logic решение → попитай Тихол (НЕ измисляй)
- При conflict с памет на Тихол → live wins, попитай за update
- При git conflict с другия чат → STOP, попитай Тихол
- При DB schema confusion → `SHOW COLUMNS FROM table_name` → live е истината (BIBLE може да е outdated)

---

## 📦 ФАЙЛОВЕ В ТАЗИ ПАПКА

1. `sale-mockup-v4.html` — финален одобрен mockup (V4, индиго hue, 2 теми)
2. `SALE_REWRITE_HANDOFF.md` — този документ

---

**Sign-off:** Тихол одобри V4 mockup на 2026-04-28. Започвай работа.
