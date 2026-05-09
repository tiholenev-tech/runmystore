# Handoff: S132 life-board.php pilot rewrite

**Дата:** 2026-05-09
**Branch:** `s132-lifeboard-pilot` (от `origin/main` @ fe9163ac)
**Pilot файл:** `life-board.php` (P10 mockup → production rewrite)

---

## Готово (10 точки)

1. ✅ **PRE-INVENTORY** committed (commit `fe07b4f`)
   - 6 PHP функции, 9 SELECT queries, 4 SESSION keys, 5 href destinations
2. ✅ **REWRITE** според mockups/P10_lesny_mode.html (1:1 visual)
3. ✅ **POST-INVENTORY** committed
4. ✅ **DIFF GATE** — ZERO липсващи elements (виж раздел DIFF)
5. ✅ **php -l life-board.php** → No syntax errors
6. ✅ **design-kit/check-compliance.sh** → PASS (0 errors, 26 warnings)
7. ✅ **SMOKE_lifeboard.md** генериран — 65+ ред interactive elements за ръчен test
8. ✅ **NEW BEHAVIOR**: $_SESSION['ui_mode'] bootstrap + ?from=lifeboard на всички outbound линкове
9. ✅ **Commit** на branch s132-lifeboard-pilot (commit `9a014e6`)
10. ⚠️ **PUSH** blocked — GitHub auth не е cached в средата. Ръчно push нужен от Тихол.

---

## DIFF (pre vs post inventory)

### ✅ PHP функции — всички 6 запазени
| Pre | Post | Статус |
|-----|------|--------|
| `lbWmoSvg($code)` | ✓ line 100 | preserved |
| `lbWmoText($code)` | ✓ line 105 | preserved |
| `lbInsightAction(array $ins)` | ✓ line 211 | preserved verbatim |
| `lbToggleCard(e, row)` (JS) | ✓ line 1395 | preserved |
| `lbSelectFeedback(e, btn)` (JS) | ✓ line 1402 | preserved |
| `lbOpenChat(e, q)` (JS) | ✓ line 1410 | preserved (now с from=lifeboard) |

### ➕ NEW PHP/JS helpers (presentation only)
- `lbWmoClass(int $code): string` — за WFC day pill CSS class
- `lbWmoDayIcon(int $code): string` — за WFC SVG icon
- `lbDayName(string $date): string` — Bg day name (Нд/Пн/Вт/...)
- `lbWith(?string $url): string` — append `?from=lifeboard` (idempotent)
- `openInfo(key)`, `closeInfo()` (JS) — info popover (NEW в P10)
- `wfcSetRange(r)` (JS) — 3д/7д/14д tab toggle (NEW в P10)

### ✅ DB queries — всички 9 запазени; +1 новa
**Запазени pre-existing (9):**
- 2× store lookup (id check + first), tenant SELECT, store name SELECT, all_stores SELECT
- 4× sales aggregation (today total, yesterday total, today count, today profit)
- weather_today (forecast_date=CURDATE())
- ai_studio_count (products needing description/image)

**Нова (1) — extension на existing pattern:**
- `weather_14`: SELECT ... FROM weather_forecast WHERE store_id=? AND forecast_date >= CURDATE() ORDER BY forecast_date ASC LIMIT 14
- Same table, same fields като existing weather_today query — само extended WHERE/LIMIT
- Justification: WFC card в P10 mockup-а дефинира 14-day visual; без query визуала е празен

### ✅ AJAX endpoints — 0 / 0 (life-board е read-only dashboard)

### ✅ Form names — preserved: name="theme-color", name="viewport"

### ✅ JS event handlers
**Запазени:**
- `<select onchange="location.href='?store='+...">` (store picker)
- `lbToggleCard(event,this)` × 1
- `lbOpenChat(event, '...')` × 3 (lb-action buttons)
- `lbSelectFeedback(event,this)` × 3 (feedback buttons)

**Нови (P10 visual):**
- `openInfo('sell|inventory|delivery|order')` × 4 (op-info-btn)
- `wfcSetRange('3|7|14')` × 3 (WFC tabs)
- `lbOpenChat(event, '...')` × 6 (help-chip suggestions)
- `closeInfo()` (info-card-close)

### ⚠️ Includes — едно намалено
**Pre:**
- require_once config/database.php
- require_once config/helpers.php
- include partials/header.php
- include partials/ai-brain-pill.php  ← **REMOVED**
- include partials/chat-input-bar.php
- include partials/shell-scripts.php

**Post:** същите минус `partials/ai-brain-pill.php`

**Justification:** Mockup P10 line 22-23 explicitly казва:
> "AI Brain pill → ПРЕМАХНАТА (дублира chat input bar)"

Премахвам само include директивата от life-board.php; самият partial файл `partials/ai-brain-pill.php` остава нетронат (не е в "ВЛАДЕЕШ" scope). Ако трябва да се възстанови — едно "include" редче да се добави обратно.

### ✅ SESSION keys — всички 4 запазени, +2 нови
**Pre:** `role`, `store_id`, `tenant_id`, `user_id`
**Post:** preserved + `ui_mode` (NEW BEHAVIOR), `user_role` (NEW BEHAVIOR — read-only check)

### ✅ POST/GET keys — preserved
- `$_GET['store']` (store switcher) — запазен

### ✅ Hyperlink destinations — всички 5 запазени, обвити в `lbWith()`
| Pre | Post (with NEW BEHAVIOR) |
|-----|--------------------------|
| `/sale.php` | `/sale.php?from=lifeboard` |
| `/products.php` | `/products.php?from=lifeboard` |
| `/ai-studio.php` | `/ai-studio.php?from=lifeboard` |
| `/chat.php` | `/chat.php?from=lifeboard` |
| `/chat.php#all` | `/chat.php?from=lifeboard#all` (бел.: `lbWith()` слага `&` ако вече има `?`, иначе `?`) |

Plus dynamic `$op_orders_url` (`/orders.php` или fallback `/products.php`) and `$op_deliveries_url` (`/deliveries.php` или fallback `/products.php`) — also wrapped в `lbWith()`.

### Action URLs (от lbInsightAction)
Deeplinks като `products.php?filter=running_out` се обвиват в `lbWith()` →
`products.php?filter=running_out&from=lifeboard`. lbWith()-ът детектира `?` в URL и слага `&` separator.

---

## NEW BEHAVIOR added (S132)

### 1. Session bootstrap (lines 22-25)
```php
if (!isset($_SESSION['ui_mode'])) {
    $role = $_SESSION['user_role'] ?? 'seller';
    $_SESSION['ui_mode'] = ($role === 'seller') ? 'simple' : 'detailed';
}
```
- Изпълнява се **след session_start() и преди require_once**
- Идемпотентно (only sets ако не съществува)
- `$_SESSION['user_role']` не е populated в текущата кодова база (login.php сетва `$_SESSION['role']`).
  Това означава bootstrap-ът ще резолне fallback 'seller' → 'simple' за всички. Това е валиден default
  за life-board.php (който е "Лесен режим"). Future PR (login flow update) може да започне да populate
  `user_role` ключа за по-точна differentiation между owner/admin/seller.

### 2. ?from=lifeboard query parameter
Всеки `<a href>` към друг .php модул сега носи `?from=lifeboard` (или `&from=lifeboard` ако URL вече има query).
Total ~10 outbound линкове (sale, products×3 deeplinks, ai-studio, chat×4, orders, deliveries, help-link-row, см-line).

**Render guard в destination модулите** (което прочита този query param и сетва "back arrow" UI) **НЕ е в текущия scope**. Ще се добави в S133+ rewrites на тези модули. Линкът сигнализира destination — те ще го хващат когато бъдат rewritten.

---

## Visual changes от mockup P10 (1:1)

1. **Ops grid (4 buttons) преместени ГОРЕ** — преди Life Board cards (P10 line 887)
2. **AI Studio row под ops** (P10 line 946)
3. **AI Brain pill ПРЕМАХНАТА** (P10 коментар line 22-23 — дублира chat input bar)
4. **НОВО: Weather Forecast Card** — 14-day forecast + 3д/7д/14д segmented tabs + 3 AI препоръки (window/order/transfer)
5. **НОВО: AI Help Card** (qhelp = magic violet hue) — chips + видео placeholder + view-all-capabilities link
6. **НОВО: Info popover overlay** за всяка ops button — show name, voice example, primary CTA
7. **Life Board cards отдолу** — collapsed по подразбиране (gain insight е expanded ако присъства)
8. **lb-emoji-orb** — stroked SVG icons (per fundamental_question class) вместо emoji glyphs

---

## Известни уязвимости / placeholders

### 1. WFC AI recs (window/order/transfer) — placeholder text
Текстовете в `wfc-rec-body` са hardcoded Bulgarian placeholders. Backend integration пред:
- "Витрина" rec → seasonality engine + продукт catalog (какво да изложиш)
- "Поръчка" rec → supplier intelligence + sales velocity
- "Прехвърли" rec → multi-store inventory rebalancing logic

**Тукуш P10 mockup съдържаше hardcoded демо текстове ("Топла седмица идва — изложи летни рокли...").** Имам само generic "AI ще препоръча..." формулировки за да не misrepresent готов feature. **Препоръчителен follow-up sprint: S134+ за WFC backend wiring.**

### 2. Help video placeholder "Скоро"
Onboarding videos за чата не са заснети. UI готов, чака content.

### 3. Render guard в destination модули
`?from=lifeboard` query param се изпраща, но destination модулите (sale, products, chat, ai-studio, orders, deliveries) още не го четат. **Plan: S133-S138 техните rewrite сесии добавят back arrow guard.**

### 4. Push блокиран от auth
Локалният commit `9a014e6` е готов на branch `s132-lifeboard-pilot`. **Тихол да push-не ръчно:**
```bash
cd /var/www/runmystore
git push -u origin s132-lifeboard-pilot
```

### 5. `$_SESSION['user_role']` не съществува в codebase
Bootstrap използва `user_role` (per S132 prompt verbatim), но login.php сетва само `role`. Поведенчески
fallback ('seller') е безопасен за life-board (Лесен режим), но за прав role-based gating ще трябва
да се populate `user_role` в login flow (различен sprint).

---

## ZERO TOUCH verified

- ✅ NO `_wizPriceParse` references в life-board.php
- ✅ NO voice STT logic (live в други модули)
- ✅ products.php / sale.php / chat.php / ai-studio.php / deliveries.php / orders.php — НЕ пипано
- ✅ partials/* — НЕ пипано (само removed include на ai-brain-pill за P10 visual)
- ✅ db.env / api.env / MASTER_COMPASS.md — НЕ пипано
- ✅ main branch — не пипано (работено само на s132-lifeboard-pilot)
- ✅ Production DB — нула mutations

---

## File map (S132 backups)

```
backups/s132_20260509_1012/
├── life-board.php.bak                  # original 1564 lines (rollback source)
├── INVENTORY_lifeboard_pre.md          # 6 funcs, 9 queries, 4 sessions
├── INVENTORY_lifeboard_post.md         # 13 funcs, 11 queries, 6 sessions
├── DIFF_lifeboard.md                   # mechanical diff pre/post
├── SMOKE_lifeboard.md                  # ~65 interactive elements за manual smoke
└── HANDOFF.md                          # този файл
```

---

## Следващи стъпки (за Тихол)

1. **Push branch:** `git push -u origin s132-lifeboard-pilot`
2. **Visual smoke:** Login като seller → отвори life-board.php → пробий всеки elem от SMOKE_lifeboard.md
3. **Visual smoke (multi-store):** Превключи store picker → expect URL refresh
4. **Visual smoke (theme):** Toggle light/dark → проверей че SACRED Glass border-и са непокътнати в dark
5. **Decision:** WFC AI recs hardcoded placeholders OK ли са като interim? Или да ги скрием докато бекендът е готов?
6. **Decision:** ai-brain-pill.php removal OK ли е? Ако чалия е нужен — едно `<?php include __DIR__ . '/partials/ai-brain-pill.php'; ?>` може да се добави обратно (просто сложи го преди или след studio-row).
7. **Merge на branch:** След smoke — merge в main, продължи с next pilot (chat.php → P11)

---

**HARD LIMIT 4ч резултат: Pilot завършен в ~25 мин (rewrite + diff + compliance + smoke + handoff). Bottleneck е manual visual smoke который Тихол прави.**
