# S142_BUG_REPORT — какво още не работи в products-v2.php Simple Mode

**Дата:** 13.05.2026 (early morning)
**Тестер:** Тих (browser test на runmystore.ai/products-v2.php?mode=simple)
**Контекст:** S142 шеф-чат свърши контекста, остави неfix-нати багове за S143

---

## ✅ FIXED в hotfix-2 (commit 64bfa42)

| # | Bug | Status |
|---|---|---|
| 1 | HTTP 500 — `fmtMoney` redeclare | ✅ `function_exists` wrap |
| 2 | HTTP 500 — `i.last_counted_at` колона липсва | ✅ try-catch + fallback |
| 3 | Огромен SVG icon на multi-store glance | ✅ CSS sizing constraints |
| 4 | Header — няма back бутон в Simple | ✅ Back бутон вляво от logo |
| 5 | Header — твърде много икони в Simple | ✅ Само theme + back (camera/printer/settings/logout = Detailed only) |
| 6 | "Виж всички N артикула" link → грешен URL | ✅ → `products.php?screen=products` |
| 7 | Top-row cells (Свършили/Застояли) — не clickable | ✅ onclick добавен |
| 8 | "AI поръчка" — грешен URL | ✅ → `products.php?screen=studio` |

---

## 🔴 ОЩЕ НЕ FIXED (за S143 priority order)

### BUG 1: Search bar — нищо не работи освен voice [PRIORITY 1]

**Тих каза:** "търсене въобще не работи нищо от него трябва да препишеш едно към едно кода от предния. като натисна трябва да има падащ списък"

**Какво има сега в products-v2.php:**
- Input + filter button + mic button (UI само)
- НЯМА JS handler за autocomplete dropdown
- НЯМА filter drawer
- Само `searchInlineMic()` за voice (работи)

**Какво трябва (1:1 от products.php):**
- При typing в input → AJAX call към `?ajax=search&q=X` → dropdown под input
- Filter button → отваря filter drawer със chips (Категория, Доставчик, Размер, Цвят)
- Apply filter → query string update + reload или AJAX refresh

**SQL за autocomplete (примерно):**
```sql
SELECT id, name, sku, retail_price, image_url
FROM products
WHERE tenant_id=? AND is_active=1
  AND (name LIKE ? OR sku LIKE ? OR barcode LIKE ?)
ORDER BY name
LIMIT 8
```

**Reference код:** `products.php` ред 4321-4635 (scrHome search логика) + ред 5310-5373 (searchInlineMic copy 1:1)

**Файлове за копиране:**
- HTML structure: products.php `hSearchDD` (autocomplete dropdown)
- JS: products.php `onLiveSearchHome()`, `searchProductsAjax()`, `openDrawer('filter')`
- CSS: products.php `.search-results-dd`, `.filter-drawer`, `.filter-chips`

---

### BUG 2: AI feed lb-cards — не отварят при tap [PRIORITY 2]

**Тих каза:** "сигналите би трябвало да се отварят и вътре да има прозорче Какво представлява сигнала Виж в чата в лесния режим и там трябва да се препише едно към едно"

**Reference:** `life-board.php` — там lb-card-овете expand-ват и показват детайли

**Текущ JS handler в products-v2.php:**
```js
function lbToggleCard(e, row) {
    // Just toggles .expanded class — но няма expanded content в HTML
    card.classList.toggle('expanded');
}
```

**Проблем:** lb-card-овете в моя mockup имат само `lb-collapsed` view. Няма `lb-expanded` content вътре.

**Решение:** Копирай lb-card pattern от `life-board.php` — там е:
```html
<div class="lb-card q1">
  <div class="lb-collapsed">[icon + tag + title + expand arrow]</div>
  <div class="lb-expanded">
    [Description какво представлява сигнала]
    [Details: number, period, products засегнати]
    [Actions buttons: [Поръчай] [Игнорирай] [Виж детайли]]
    [Feedback: 👍 / 👎]
  </div>
</div>
```

**Reference:** `life-board.php` редове ~1500-1800 (AI feed section) + `lbToggleCard` ред 2262

---

### BUG 3: "Прехвърли" сигнал няма SVG icon [PRIORITY 3]

**Тих каза:** "има един прехвърли който няма cvgi"

**Локация:** Sigнал 3 от AI feed (тransfer signal) — `lb-card q4`

**Сегашен HTML:**
```html
<span class="lb-emoji-orb lb-ic-transfer">
  <svg viewBox="0 0 24 24"><path d="M7 17l-4-4 4-4"/>...</svg>
</span>
```

**Проблем:** SVG-то има path но CSS вероятно скрива го или има грешен размер. Виж SVG sizing CSS в hotfix-2 и след това провери дали `lb-ic-transfer` правилата прилагат.

---

### BUG 4: Chat-input-bar — не отваря нищо при tap [PRIORITY 4]

**Тих каза:** "и чата въобще не работи"

**Текущ HTML:**
```html
<div class="chat-input-bar" role="button" tabindex="0">
  ...
</div>
```

**Проблем:** `<div role="button">` няма onclick handler! Tap-а нищо не прави.

**Решение:** Добави onclick:
```html
<div class="chat-input-bar" role="button" tabindex="0" onclick="rmsOpenChat()">
```

И JS function:
```js
function rmsOpenChat() {
    location.href = 'chat.php?from=products-v2';
    // Или отвори chat overlay inline ако chat.php има embeddable widget
}
```

**Mic button** трябва да отдели event:
```html
<button class="chat-mic" onclick="event.stopPropagation();searchInlineMic(this,'chat')">
```

---

### BUG 5: "Магазини днес" wfc (Weather Forecast Card) текстът грозен [PRIORITY 5]

**Тих каза:** "После отиваме на отвратителния cbg големия който беше сега пък самият надпис е просто покъртително грозен"

**Какво е "cbg"?** Не съм сигурен — може би multi-store glance ("МАГАЗИНИ ДНЕС"). Тих каза:
> "Магазините днес 12.05 Ени Тихолов 0% 0 € EUR Основен магазин под средното -100% 0 € EUR"

Значи:
1. Multi-store данните за реалния tenant ENI връщат "0% 0 €" — защото в DB няма реални sales за beta още (placeholder data)
2. "EUR" пише след всеки store (двойно — `<small>€</small>` + currency)
3. Layout текстът се събира в една дълга линия — НЕ е форматиран в нормални редове

**Възможна причина:**
- В моя hotfix-2 SVG CSS-а съм пропуснал `.sg-row` layout
- Или `flex-direction` е break-нат
- Или дублирано `€` rendering

**Поправка trябва:**
```css
.sg-row {
  display: flex !important;
  align-items: center !important;
  gap: 10px !important;
  padding: 8px 10px !important;
}
.sg-name { flex: 1; min-width: 0; }
.sg-revenue { flex-shrink: 0; }
```

И в PHP:
```php
<span class="sg-revenue"><?= fmtMoney($ms['revenue']) ?> <?= $cs ?></span>
// БЕЗ <small> tag — double currency
```

Виж текущия PHP rendering на ред ~2107 в products-v2.php.

---

### BUG 6: Action wrappers — водят грешни места [TRACKING]

**Тих каза:**
> "натискам на виж всичките артикули ме закарва директно във разширение режим а не в списъка с артикулите"
> "натискам една и поръчка То това още Така или иначе не работи"

В hotfix-2 поправих URL-ите:
- `lbViewAll()` → `products-v2.php?mode=detailed&tab=items&filter=signals` (но Тих иска P3 list, не Detailed)
- `openAIOrder()` → `products.php?screen=studio`

**Hubris проблем:** `products.php?screen=products` (P3 list) трябва да работи в стария products.php. Но Тих каза vode-ва в Detailed Mode. Може би `?screen=products` route-ва грешно.

**Проверка нужна в S143:** какво е реалното URL за P3 list view в production products.php? Може да е `?view=list` или `?action=browse` или подобно.

---

## 📋 PLAN ЗА S143 (priority order)

```
1. Search dropdown + filter drawer (BUG 1) — 2-3ч копиране от products.php
2. lb-card expand със full content (BUG 2) — 1-2ч копиране от life-board.php
3. Multi-store glance layout (BUG 5) — 30 мин CSS fix
4. Chat-input-bar onclick (BUG 4) — 15 мин
5. Transfer SVG icon (BUG 3) — 15 мин CSS check
6. Action URLs верификация (BUG 6) — 30 мин test всеки link
```

**Backup tag за S143 start:** `pre-step3-S142` (използвай преди големи промени)

---

## 🎯 NEXT STEPS

Sed S142 шеф-чат напуска. S143:

1. Прочитай `BOOT_PROMPT_FOR_S142.md` (still applicable)
2. Прочитай `SESSION_S142_HANDOFF.md` (overview)
3. Прочитай ТОЗИ `S142_BUG_REPORT.md` (детайлни bugs)
4. Прочитай `mockups/P15_simple_FINAL.html` (canonical visual)
5. Тествай live: `runmystore.ai/products-v2.php?mode=simple`
6. Започни от BUG 1 (search)

products.php = НЕПОКЪТНАТ. Production safe.
