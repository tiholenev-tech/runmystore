# Hardcoded BG Strings Audit — S117

**Date:** 2026-05-08
**Scope:** All `.php` in repo root + partials, EXCLUDING `products.php` and `biz-coefficients.php` (per scope rule).

## Summary

**Total Cyrillic-bearing lines:** 3,963 across 50+ files (excluding products & biz-coefficients).
**`t()` translation function:** ❌ does NOT exist anywhere in the codebase.
**Bulgarian is hardcoded throughout** — placeholder, title, alt, aria-label, return values, echo statements, JS toasts/alerts/confirms, error messages, status labels, UI copy.

For perspective: the prior `docs/I18N_AUDIT_DATA.json` (2026-04-27) baseline reported 5,204 i18n violations across 75 files including products. The numbers have not improved since.

## Top 20 Offender Files

| File | Cyrillic-bearing lines |
|------|----------------------:|
| products_fetch.php | 1037 |
| sale.php | 303 |
| chat.php | 177 |
| xchat.php | 169 |
| inventory.php | 128 |
| compute-insights.php | 125 |
| ai-helper.php | 115 |
| build-prompt.php | 107 |
| chat-send.php | 90 |
| stats.php | 87 |
| ai-safety.php | 73 |
| onboarding.php | 67 |
| delivery.php | 67 |
| life-board.php | 62 |
| ai-wizard.php | 61 |
| deliveries.php | 55 |
| biz-compositions.php | 53 |
| ai-studio.php | 53 |
| order.php | 47 |

## Sample Findings (≥50 listed below)

### chat.php

| Line | Hardcoded BG | Suggested key |
|-----:|--------------|---------------|
| 48   | `$store_name = $store['name'] ?? 'Магазин';` | `t('common.default_store_name')` |
| 78   | `'Витрина: летни артикули отпред — рокли, сандали, шапки. Пуснати ли са зимните на намаление?'` | `t('chat.weather_suggestion.hot')` |
| 79   | `'Витрина: леки рокли и сандали. Ако имаш пролетни остатъци — време за намаление'` | `t('chat.weather_suggestion.warm')` |
| 80   | `'Витрина: тениски, къси панталони...'` | `t('chat.weather_suggestion.mild')` |
| 81-84 | (5 more weather suggestions, hardcoded BG) | `t('chat.weather_suggestion.cool|cold|frost|winter')` |
| 119  | `return 'Слънчево';` (weather code 0-3) | `t('weather.sunny')` |
| 120  | `return 'Облачно';` (weather code 4-48) | `t('weather.cloudy')` |
| 121  | `return 'Ръми';` (weather code 49-57) | `t('weather.drizzle')` |
| 1121 | `showToast('Добавено към чернова поръчка');` | `t('toast.added_to_draft_order')` |

### life-board.php

| Line | Hardcoded BG | Suggested key |
|-----:|--------------|---------------|
| 43   | `$store_name = $store['name'] ?? 'Магазин';` | `t('common.default_store_name')` |
| 87-93 | 7 weather labels: 'Слънчево', 'Облачно', 'Ръми', 'Дъжд', 'Сняг', 'Порой', 'Буря' | `t('weather.*')` (DUPLICATED with chat.php — extract to shared helper) |
| 136-141 | `'name'=>'Какво губиш'`, `'От какво губиш'`, `'Какво печелиш'` etc (Bible's q1-q6 names) | `t('fundamental.q1.name')` ... `t('fundamental.q6.name')` |
| 1310 | `<a href="/chat.php" class="lb-mode-toggle s87v3-tap" title="Подробен режим">` | `t('mode.detailed')` |
| 1326 | `<select class="lb-store-picker" ... aria-label="Магазин">` | `t('common.store')` |
| 1391 | `<button ... aria-label="Разгъни">` | `t('action.expand')` |

### sale.php

| Line | Hardcoded BG | Suggested key |
|-----:|--------------|---------------|
| 29   | `$currency = htmlspecialchars($tenant['currency'] ?? 'лв');` | `t('common.currency_default')` (fallback) |
| 41   | `$store_name = $store['name'] ?? 'Магазин';` | `t('common.default_store_name')` |
| 47   | `$page_title = $supato_mode ? 'Изходящо движение' : 'Продажба';` | `t('sale.title.outgoing|sale')` |
| 164  | `'error' => 'Невалиден CSRF токен. Презареди страницата.'` | `t('error.csrf_invalid')` |
| 188  | `'error' => "Твърде много заявки. Изчакай $retry сек."` | `t('error.rate_limit', {retry})` (with placeholder) |
| 1983 | `<button ... aria-label="Паркирани">` | `t('sale.parked')` |
| 1987 | `<button ... aria-label="Светла/тъмна тема">` | `t('action.toggle_theme')` |
| 2035 | `<input ... placeholder="Код, име или баркод">` | `t('sale.search_placeholder')` |
| 2062 | `<span class="sum-total">Общо: <span ...>0,00</span> ${currency}</span>` | `t('sale.total_label')` |
| 2068 | `ПЛАТИ <span ...>0</span> ${currency}` | `t('sale.pay_button')` |
| 3167 | `showToast('Максимум ' + STATE.maxDiscount + '%');` | `t('toast.max_discount', {pct})` |
| 3397 | `setTimeout(() => showToast('Отстъпка ограничена до ' + cap + '%', '', 4000), 800);` | `t('toast.discount_capped', {cap})` |

### stats.php

| Line | Hardcoded BG | Suggested key |
|-----:|--------------|---------------|
| 115  | `$hlabel = $health_score >= 70 ? 'Бизнесът е в добро здраве' : ($health_score >= 40 ? 'Има неща за подобрение' : 'Нужно е внимание');` | `t('stats.health.{good|moderate|poor}')` |
| 949  | `'От – До'` | `t('stats.from_to')` |
| 964  | `['overview'=>'Обзор','sales'=>'Продажби','products'=>'Стоки','finance'=>'Финанси','anomalies'=>'Аномалии']` | `t('stats.tab.{overview|sales|products|finance|anomalies}')` |
| 1101 | `<div ...><?= $l['quantity'] ?> бр.</div>...мин: <?= $l['min_quantity'] ?>` | `t('common.qty_unit')`, `t('common.min')` |
| 1175 | `'title'=>'Топ артикул изчерпан'`, `'desc'=>$zb['name'].' — продаван но с 0 наличност'` | `t('anomaly.top_out_of_stock.{title|desc}', {name})` |
| 1176 | `'title'=>'Ниска наличност'`, `'desc'=>$l['name'].' — '.$l['quantity'].' бр. (мин: '.$l['min_quantity'].')'` | `t('anomaly.low_stock.{title|desc}', {name, qty, min})` |

### login.php

| Line | Hardcoded BG | Suggested key |
|-----:|--------------|---------------|
| 312 | `placeholder="твоят@имейл.com"` | `t('login.email_placeholder')` |
| 334 | `<button ... aria-label="Покажи парола">` | `t('login.show_password')` |
| (general) | `'Грешен имейл или парола.'` | `t('login.error.invalid_credentials')` |

### register.php

| Line | Hardcoded BG | Suggested key |
|-----:|--------------|---------------|
| 131 | `placeholder="напр. Модна Къща Иванови"` | `t('register.tenant_name_placeholder')` |
| 143 | `placeholder="твоят@имейл.com"` | `t('register.email_placeholder')` |
| 157 | `placeholder="Поне 8 символа"` | `t('register.password_placeholder')` |

### onboarding.php

| Line | Hardcoded BG | Suggested key |
|-----:|--------------|---------------|
| 128 | `<textarea ... placeholder="Пиши тук...">` | `t('onboarding.input_placeholder')` |
| 142 | `<div ... placeholder="Говори...">` | `t('onboarding.voice_placeholder')` |

### inventory.php

| Line | Hardcoded BG | Suggested key |
|-----:|--------------|---------------|
| 16   | `$currency=...??'лв');` | `t('common.currency_default')` |
| 22   | `'error'=>'Липсва име'` | `t('error.missing_name')` |
| 25   | `'error'=>'Грешка'` (file upload) | `t('error.generic')` |
| 25   | `'error'=>'Невалиден формат'` | `t('error.invalid_format')` |
| 25   | `'error'=>'Файлът е голям'` | `t('error.file_too_large')` |
| 78-83 | `'магазин'`, `'размер'/'size'/'ръст'`, `'цвят'/'color'/'десен'` | (these are PHP business-logic keywords, NOT user-facing — leave alone) |
| 89   | `['бр','чифт','к-кт']` (units) | `t('units.{piece|pair|set}')` |
| 91   | `<title>Скрити пари</title>` | `t('inventory.title')` |
| 100  | `<h1>Скрити пари</h1>`, `Артикула`, `Преброени`, `Места`, `Броенето продължава`, `Продължи броенето`, `Започни броене`, `+ Добави` | many distinct keys |
| 101  | (welcome screen) `Скрити пари`, `Мисля, че имаш пари скрити в магазина.`, `Стока, за която не знаеш точно колко имаш.`, `Искаш ли да видим заедно?`, `Да, да започваме`, `Обхождаме магазина стъпка по стъпка`, `Имам файл с артикули`, `CSV или Excel`, `По-късно`, `Ще се върна когато имам повече време` | `t('inventory.welcome.*')` |
| 185  | `toast('Грешка','err');` | `t('toast.error_generic')` |

### settings.php

| Line | Hardcoded BG | Suggested key |
|-----:|--------------|---------------|
| 67 | `<div class="row-label">Потребител: ...</div>` | `t('settings.user_label', {name})` |
| 68 | `<div class="row-sub">роля: ...</div>` | `t('settings.role_label', {role})` |
| 75 | `<div class="row-sub">валута: ...</div>` | `t('settings.currency_label', {cs})` |

### delivery.php

| Line | Hardcoded BG | Suggested key |
|-----:|--------------|---------------|
| 964  | `toast('Мрежова грешка', 'error');` | `t('error.network')` |
| 971  | `toast('Гласовата диктовка скоро — засега снимай фактурата.', 'warn');` | `t('delivery.voice_coming_soon')` |

### Other rep'd patterns (≥40 more across files)

- `'Магазин'` default fallback (chat.php:48, sale.php:41, life-board.php:43)
- `confirm('Премахни този ред?')` order.php:452
- `toast('Копирано');` order.php:484
- `confirm('Маркирай всички дефектни от ' + ... + ' като върнати?')` defectives.php:347
- `confirm('Отписвам всички pending като загуба. Сигурен?')` defectives.php:363
- `showToast('Гласът не се поддържа','error')` ai-chat-overlay.php:252
- `alert('Купи кредити — модалът идва в STUDIO.17 (3 пакета €5/€15/€40).')` ai-studio.php:675
- `confirm('Ще махна фона на всички продукти. Продължи? (заявката се изпраща в опашка)')` ai-studio.php:678
- `showToast('Включи PRO за AI съвети')` chat.php:655, xchat.php:1870
- `showToast('Мрежова грешка','error')` products_fetch.php:3832
- `showToast('Копиране... (скоро)','')` products_fetch.php:3913

## Severity Distribution

| Severity | Count | Description |
|----------|------:|-------------|
| HIGH | ~2,100 | User-facing labels in shipping flows (toast/alert/confirm/placeholder) |
| MEDIUM | ~1,400 | Internal labels (error messages, settings rows) |
| LOW | ~470 | PHP business logic keywords (e.g., `'магазин'` mode detection) — likely should stay BG anyway |

## Recommendation

### Phase 1 — Build the i18n framework (1-2 days)

1. Create `config/i18n.php` with `t($key, $params = [])` function:
   ```php
   function t(string $key, array $params = []): string {
       global $rms_lang_data;
       if ($rms_lang_data === null) {
           $lang = $_SESSION['lang'] ?? $_COOKIE['rms_lang'] ?? 'bg';
           $file = __DIR__ . '/../lang/' . $lang . '.json';
           $rms_lang_data = file_exists($file)
               ? json_decode(file_get_contents($file), true) ?? []
               : [];
       }
       $value = $rms_lang_data[$key] ?? $key;  // fallback: return key
       foreach ($params as $k => $v) {
           $value = str_replace('{' . $k . '}', (string)$v, $value);
       }
       return $value;
   }
   ```
2. Wire `partials/shell-init.php` to load `i18n.php` so `t()` is globally available.
3. Add `escape-and-translate` helper: `function te(string $key, array $p = []): string { return htmlspecialchars(t($key, $p), ENT_QUOTES, 'UTF-8'); }`

### Phase 2 — Migrate top 5 user-facing files (3-5 days)

In order: `login.php` → `register.php` → `onboarding.php` → `chat.php` → `sale.php`

Per file:
1. Identify all hardcoded BG → propose key
2. Update bg.json with new keys
3. Replace `'Магазин'` → `t('common.default_store_name')`
4. Smoke test each in BG (functionally identical)

### Phase 3 — Build EN locale (1 week)

1. Copy bg.json → en.json
2. Translate values (use AI assistance — Gemini/Claude/ChatGPT)
3. QA pass with native speaker
4. Implement language switcher in settings

### Phase 4 — RO/SR locales (per market need)

After EN is solid.

## Estimated effort

| Phase | Days |
|-------|-----:|
| 1 — Framework | 1-2 |
| 2 — Top 5 files migrate | 3-5 |
| 3 — EN locale | 5-7 |
| 4 — RO/SR | 2-3 each |
| **Total to launch-readiness** | **15-20 days** |
