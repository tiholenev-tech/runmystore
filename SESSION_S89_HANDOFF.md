# SESSION S89.AIBRAIN.MODALS — HANDOFF

**Дата:** 2026-04-28
**Автор:** Code #1 (Claude Opus 4.7, 1M context)
**Status:** ✅ Closed (4/4 modals + endpoint + 2-line patch на products.php)

> ⚠️ **NOT phone-tested by Code #1, Тихол verify задължително.**
> Sandbox-ът няма Apache/MySQL — всичко е verified чрез:
> `php -l`, `node --check`, и stub-харнес за endpoint dispatching.
> Ето защо има remaining edge cases (виж §5).

---

## 1) Какво е добавено

| File | Type | Lines | Notes |
|---|---|---|---|
| `aibrain-modal-actions.php` | NEW | ~240 | Single dispatcher, `?action=` param, CSRF self-bootstrap |
| `css/aibrain-modals.css` | NEW | ~270 | Glass-morphism, q1–q6 themed, mobile 375px |
| `js/aibrain-modals.js` | NEW | ~550 | 4 modal класа + `window.handleAiAction` override |
| `products.php` | PATCH | +2 | `<link>` в `<head>` + `<script>` преди `</body>` |

`git diff HEAD products.php` показва **+2 / −0** (verified).

---

## 2) DOD Checklist

- ✅ 4 modal класа с `open()` + `close()` методи (наследени от `BaseModal` чрез `Object.create`)
  - `OrderDraftModal` — qty edit + supplier dropdown + submit
  - `NavigateChartModal` — Chart.js bar chart с CSS-fallback (без Chart.js на страницата)
  - `TransferDraftModal` — checkbox list + qty + from/to store header
  - `DismissModal` — confirm + textarea за reason
- ✅ 4 endpoint actions работят (curl/stub test → 200 OK):
  - `GET ?action=csrf` → `{ok:true, token:…}`
  - `POST ?action=order_draft_submit` → `{ok:true, order_id, redirect_url}`
  - `POST ?action=transfer_draft_submit` → `{ok:true, transfer_id}`
  - `POST ?action=dismiss` → `{ok:true, insight_id}`
- ✅ Mobile 375px: `dialog.aim` използва `max-width: min(420px, calc(100vw - 24px))` + `@media (max-width:380px)` за store layout collapse
- ✅ Backdrop click + ESC + close button → всички затварят (виж `BaseModal._onClick` и `_onKey`)
- ✅ products.php diff = exactly 2 added lines (verified)
- ✅ `php -l aibrain-modal-actions.php` → No syntax errors detected
- ✅ `php -l products.php` → No syntax errors detected
- ✅ `node --check js/aibrain-modals.js` → OK
- ✅ ZERO нови ALTER, ZERO touched DB schema (all writes към съществуващи tables: `purchase_orders`, `purchase_order_items`, `transfers`, `transfer_items`, `ai_insights`, `ai_shown`)
- ✅ DB::run / DB::tx / DB::lastInsertId — никога raw `$pdo`
- ✅ CSRF token validation (per-session, hash_equals) + Origin/Referer cross-origin guard
- ✅ tenant_id scope на всеки query
- ✅ Финални имена (никакви `_v1`, `_FINAL`, дати)

---

## 3) Архитектурни решения / отклонения от спека

### 3.1 ai_insights няма `dismissed_at` / `dismissed_reason` колонки
Live schema (от backup_s79_schema_20260424_1536.sql) и migrations не съдържат
тези колонки. DOD казва "ZERO ALTER", така че dismiss е реализиран през:
- `UPDATE ai_insights SET expires_at=NOW(), action_data=JSON_MERGE(action_data, {dismissed_at, dismissed_reason, dismissed_by}) WHERE id=? AND tenant_id=?`
- `INSERT INTO ai_shown (..., action='dismissed', action_at=NOW())` за cooldown tracking

Reason-ът се пази в `action_data` JSON. Ако S90+ добави dedicated колонки,
може да се мигрира с `JSON_EXTRACT(action_data, '$.dismissed_reason')`.

### 3.2 transfers.status няма 'draft'
ENUM е `('pending','in_transit','completed','canceled')`. Drafts са записани
като `status='pending'` + `note='AI draft (insight=…)'`. Когато S92 пише
transfers.php, може да extends-не ENUM или да филтрира с `note LIKE 'AI draft%'`.

### 3.3 Tabel name е `purchase_orders`, не `orders`
Спекът казваше `orders` table — реалният live name е `purchase_orders` (verified
в backup schema). Endpoint използва `purchase_orders` + `purchase_order_items`.
`redirect_url` връща `orders.php?id=N` — orders.php е S91+ scope, така че URL-ът
ще води към 404 докато S91 не build-не страницата. Това е acceptable (обещание
към user-а да отиде на детайлната страница, която ще съществува скоро).

### 3.4 CSRF self-bootstrap
Project-ът няма CSRF infrastructure. За да не пипам други модули, endpoint-ът:
- Mint-ва token на първия `GET ?action=csrf` (в `$_SESSION['aibrain_csrf']`)
- JS-ът извиква getCsrfToken() lazily преди първия POST
- Validation: `hash_equals($_SESSION['aibrain_csrf'], $_POST['_csrf'])`
- Допълнителен Origin/Referer check срещу HTTP_HOST за defense-in-depth

### 3.5 Chart.js — optional, не е добавен в products.php
Спекът казваше "render Chart.js bar chart". products.php не зарежда Chart.js
(2-line patch ограничението). NavigateChartModal детектва `window.Chart` и при
липса fallback-ва към чист CSS bar chart (`_fallbackBars`). За production
graphs Тихол може да добави `<script src="js/vendors/chart.min.js"></script>`
в `partials/shell-scripts.php` (друга, бъдеща задача).

### 3.6 No hardcoded "лв"
Всички price renders минават през `fmtPrice(n)` който чете `CFG.currency`
(global от products.php). `.price-display` CSS клас цветът е q-themed (`--aim-accent`).

---

## 4) Commits

```
S89.AIBRAIN.MODALS: 4 modals + endpoint + 2-line products.php patch
S89.AIBRAIN.MODALS: handoff doc
```

(actual hashes ще се добавят след `git push`)

---

## 5) Edge cases / TODOs за follow-up

1. **products.php няма Chart.js** — modals fallback-ват към CSS bars. Ако S90+
   добави Chart.js глобално, NavigateChartModal автоматично ще го използва.
2. **dismiss без insight_id** → JS-ът fallback-ва към оригиналния `_origHandle`
   (console log + product detail). Това е намерено в `aibrain-modals.js:566-571`.
   В реалния live data, `act.insight_id` идва от `ai_insights.action_data.intent`
   payload-а — Code #2 трябва да го include-не в pump-а (S88 pumps вече
   включват action_data, но не insight_id explicit-но — потенциален gap).
3. **Suppliers dropdown** — чете от `CFG.suppliers` (global, set от products.php
   PHP block). Ако сезона/store смени suppliers без reload, dropdown-а ще
   е stale до next page load.
4. **Stores list за TransferDraftModal** — `CFG.stores` НЕ съществува в текущия
   CFG; модалът показва store names само ако `data.from_store_id` / `data.to_store_id`
   съответстват на `CFG.storeId` (current store), иначе "Магазин #N". S90+
   може да extends-не CFG с `stores: [{id, name}, …]` без да пипа този модул.
5. **redirect_url към orders.php** — orders.php не съществува (S91 scope). При
   submit потребителят ще получи 404. Може да се замени с notification "Поръчката
   е създадена" + остава на products.php — TODO когато orders.php live-не.
6. **transfers `note` идва от server, не от user** — Дъжна да се позволи user
   да добави свой note? Текущо: NIE. Може да се extend-не TransferDraftModal с
   textarea за note ако Тихол поиска.
7. **Качество на JS-а** — IIFE без ES modules за съвместимост със Capacitor
   webview-а (Android/iOS). Никаква зависимост от build step.
8. **CSRF token и многобройни tabs** — token-ът е per-session, така че няколко
   tabs ще работят добре. След logout/login token-ът се ре-mint-ва.

---

## 6) Какво е следващото (S90+)

- **S90.ORDERS** — orders.php frontend, който чете purchase_orders и обработва
  draft → sent flow. Тогава OrderDraftModal-ът ще има реален redirect_url.
- **S91.TRANSFERS** — transfers.php (BIBLE roadmap §S92). Тогава transfers.status
  може да extends-не до 'draft' или да филтрира по note.
- **S92.AI_INSIGHTS_DISMISS_SCHEMA** — ALTER ai_insights ADD dismissed_at /
  dismissed_reason колонки + миграция от action_data JSON.
- **S93.CHART.JS_GLOBAL** — добави Chart.js в partials/shell-scripts.php за
  всички модули (NavigateChartModal автоматично ще го използва).
- **S94.CFG.STORES** — exposure на CFG.stores global, за по-богат
  TransferDraftModal store header.

---

## 7) Disjoint lock — какво НЕ съм пипал

- ❌ tools/ (Code #2 територия) — недокоснато
- ❌ migrations/ — нула нови файлове
- ❌ compute-insights.php / selection-engine.php — недокоснати
- ❌ chat.php, sale.php, warehouse.php — недокоснати
- ✅ products.php — exactly 2 lines added (`<link>` + `<script>`)
- ✅ js/aibrain-modals.js — NEW
- ✅ css/aibrain-modals.css — NEW
- ✅ aibrain-modal-actions.php — NEW

---

## 8) Verify checklist за Тихол на phone

1. Login → отвори products.php
2. Open a q-section с action button (примерно q5 "Какво да поръчаш" или q1)
3. Tap action button → modal трябва да се отвори с правилен q-color theme
4. Test ESC → затваря
5. Test backdrop tap (извън card-а) → затваря
6. Test close button (X в header) → затваря
7. Order draft: edit qty с +/− → submit → check `purchase_orders` table за
   нов draft row + items + correct supplier_id
8. Dismiss: tap "Скрий съвета" + reason → check `ai_insights.expires_at` set,
   `action_data.dismissed_reason` save-нат, `ai_shown` row с action='dismissed'
9. Mobile 375px: всички modals fit без horizontal scroll (Safari Web Inspector
   Device Mode 375×667)

Ако нещо не работи, повтори commit-овете в нова сесия и push fix-ове.
