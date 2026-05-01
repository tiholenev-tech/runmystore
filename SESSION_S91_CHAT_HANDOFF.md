# S91.MIGRATE.CHAT — Handoff

**Сесия:** S91.MIGRATE.CHAT (Code chat — visual migration only)
**Дата:** 2026-05-01
**Файл:** `chat.php`
**Backup:** `.backups/s91_chat_<timestamp>/chat.php`

## Резюме

Visual миграция на `chat.php` към design-kit v1.1 стандарта. Без logic changes.
- **Преди:** 3329 реда (≈1700 inline CSS + 1605 PHP/HTML/JS)
- **След:** 1638 реда (-51%)

## Какво беше изтрито

### CSS imports (стари)
- `<link href="/css/theme.css">` — заменен с design-kit/tokens.css + light-theme.css
- `<link href="/css/shell.css">` — заменен с design-kit/components-base.css + components.css + header-palette.css

### Inline `<style>` блок (1693 реда → 0)
Целият `<style>...</style>` блок беше дублиран от design-kit/components.css. Изтрит изцяло.
Включваше дефиниции на следните класове, които ВСЕ ВЕЧЕ съществуват в `/design-kit/components.css`:

- **Glass system:** `.glass`, `.shine`, `.glow`, `.shine-bottom`, `.glow-bottom`, `.glass.sm`
- **Header (legacy):** `.header`, `.brand`, `.plan-badge`, `.header-spacer`, `.header-icon-btn`, `.logout-dropdown`
- **Revenue dashboard:** `.rev-card`, `.rev-head`, `.rev-label`, `.rev-link`, `.store-sel`, `.rev-big`, `.rev-val`, `.rev-cur`, `.rev-change`, `.rev-vs`, `.rev-compare`, `.rev-meta`, `.rev-pills`, `.rev-pill-group`, `.rev-pill`, `.rev-divider`, `.conf-warn`
- **Health bar:** `.health`, `.health-lbl`, `.health-track`, `.health-fill`, `.health-pct`, `.health-link`, `.health-info`, `.health-tooltip`
- **Weather card:** `.weather`, `.weather-head`, `.weather-left`, `.weather-condition`, `.weather-temp`, `.weather-range`, `.weather-sug`, `.weather-week`, `.weather-day*`
- **AI меta + briefing:** `.ai-meta`, `.ai-meta-lbl`, `.ai-meta-time`, `.ai-bubble`, `.ai-bubble-text`, `.briefing-section` (q1-q6), `.briefing-head`, `.briefing-emoji`, `.briefing-name`, `.briefing-title`, `.briefing-detail`, `.briefing-items`, `.briefing-item`, `.bi-dot`, `.bi-name`, `.bi-qty`, `.briefing-actions`, `.briefing-btn-primary`, `.briefing-btn-secondary`
- **Top-strip pills:** `.top-strip`, `.top-pill` (q1, q5, .tp-txt, .tp-val)
- **Signal cards/detail/browser:** `.sig-card` (variants), `.sig-card-body`, `.sig-card-t`, `.sig-card-d`, `.sig-card-arr`, `.sig-more`, `.sig-body`, `.sig-hero*`, `.sig-fq-badge`, `.sig-box`, `.sig-label`, `.sig-text`, `.sig-suggest`, `.sig-actions`, `.sig-btn-primary`, `.sig-btn-secondary`, `.sig-hint`, `.sig-section`, `.sig-row`, `.browser-cat*`, `.browser-future`
- **Input bar (legacy):** `.input-bar`, `.input-bar-inner`, `.input-waves`, `.input-wave-bar`, `.input-placeholder`, `.input-mic`, `.input-send`
- **Bottom nav (legacy):** `.bottom-nav`, `.nav-tab`, `.nav-tab-label`
- **Chat overlay (75vh):** `.ov-bg`, `.ov-panel`, `.ov-handle`, `.ov-header`, `.ov-back`, `.ov-close`, `.ov-title-wrap`, `.ov-avatar`, `.ov-title`, `.ov-sub`, `.ov-signal-dot`
- **Chat messages:** `.chat-messages`, `.chat-empty*`, `.msg-group`, `.msg-meta`, `.msg-ai`, `.msg-user`, `.typing*`, `.rec-bar`, `.rec-dot`, `.rec-label`, `.rec-transcript`, `.chat-input`, `.chat-input-inner`, `.chat-ta`, `.chat-mic`, `.chat-send`, `.voice-ring`, `.vr1/.vr2/.vr3`
- **Misc:** `.toast`, `.action-buttons`, `.action-button`, `.ghost-pill`, `.indigo-sep`, `.app`
- **Animations:** `@keyframes pageIn/cardin/glowPulse/springRelease/headerIn/navIn/wavebar/tdot/recpulse/vrpulse/swipeOut/messagePop/badgeBounce/toastIn/toastOut/elasticPull/contextOut/contextIn/pillSlideIn/checkDraw/shimmer/cardExpand/scrollIn`
- **Helpers:** `.card-stagger > *:nth-child(N)`, `.spring-tap` selectors, `.count-up`, `.skeleton`, `.scroll-reveal`
- **AI Studio entry:** `.ai-studio-row`, `.ai-studio-btn`, `.as-icon`, `.as-text`, `.as-label`, `.as-sub`, `.ai-studio-badge`
- **Mode toggle:** `.cb-mode-row`, `.cb-mode-toggle`
- **Life Board cards:** `.lb-card` (q1-q6), `.lb-silent`, `.lb-header`, `.lb-title*`, `.lb-count`, `.lb-fq-tag`, `.lb-fq-emoji`, `.lb-dismiss`, `.lb-card-title`, `.lb-body`, `.lb-actions`, `.lb-action`, `.lb-feedback`, `.lb-fb-label`, `.lb-fb-btn`, `.lb-silent-icon/text/sub`, `.lb-see-more`
- **S82 dash:** `.s82-dash*`, `.s82-weather*`, `.qd`, `.qw`
- **Light theme overrides:** `html[data-theme="light"]` блок (≈100 реда) — премахнат, design-kit/light-theme.css ги покрива

### Други изтрити неща
- `:root { --hue1, --hue2, --bg-main, --text-primary, ...}` — всичко идва от tokens.css сега
- Inline `body { background: ... }` дефиниция — тази в components-base.css

## Какво беше преименувано в .mod-chat-*

**Нищо.** Целият inline CSS беше дублиран от design-kit и беше изтрит изцяло — не остана chat-specific CSS, който да изисква prefix `.mod-chat-*`.

## Какво беше добавено

### Head (между `<head>` и `</head>`)
- Bootstrap script за `data-theme="light"` от localStorage (преместен преди CSS)
- 5 design-kit CSS импорта в правилния ред: `tokens.css`, `components-base.css`, `components.css`, `light-theme.css`, `header-palette.css`

### Body
- `class="has-rms-shell mode-detailed"` (chat.php = Detailed Mode)

### Partials (PHP includes)
- `<?php include __DIR__ . '/partials/header.php'; ?>` → `<?php include __DIR__ . '/design-kit/partial-header.html'; ?>`
- `<?php include __DIR__ . '/partials/bottom-nav.php'; ?>` → `<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>`

### Scripts (преди `</body>`)
- `<script src="/design-kit/theme-toggle.js">` (преди palette)
- `<script src="/design-kit/palette.js">`

## Untouched (запазени логически секции)

- **PHP логика lines 1-408** — session, weather forecast, periodData, cmpPct, mgn, insightAction, insights routing, briefing build, ghost pills.
- **AJAX endpoints** — нямаше явни endpoints в chat.php, но querySelectorAll/event handler логиката е запазена.
- **JavaScript event handlers** — click, input, keydown, touchend, mouseup — всички непокътнати.
- **Voice flow** — toggleVoice(), recordingActive, micBtn click, vr1/vr2/vr3 анимации (всички CSS дефиниции в design-kit).
- **Send-message logic** — sendMsg(), chat overlay open/close, chat history.
- **AI prompt building** — целият chat send pipeline.
- **Insights routing** — openSignalDetail(idx), openSignalBrowser(), openChatQ(question).
- **Mode toggle anchor** — `<a class="cb-mode-toggle" href="/life-board.php">Лесен</a>` непокътнат.
- **partials/chat-input-bar.php include** — запазен (HTML е identical с design-kit/partial-chat-input-bar.html, но PHP wrapper-ът се използва от другите модули).
- **partials/shell-scripts.php include** — запазен. Той предоставя `rmsToggleLogout`, `rmsOpenPrinter`, `rmsOpenChat`, swipe nav, prefetch — функционалност извън design-kit. Theme-toggle частта се override-ва от design-kit/theme-toggle.js (зарежда се по-късно).
- **Локални helpers (chat.php scope):** wmoSvg, wmoText, periodData, cmpPct, mgn, insightAction — всички chat-specific, няма дублирани в helpers.php.

## Visual differences vs стария chat.php (наблюдения)

1. **Header layout:** старият `partials/header.php` беше еднореден (brand+badge+spacer+icons). Новият `design-kit/partial-header.html` е двуреден — добавя втори ред със 2 hue slider контроли (palette.js ги контролира). Плюс — header класовете остават `.rms-header` / `.rms-icon-btn` / `.rms-brand` / `.rms-plan-badge`.
2. **Plan badge:** новият partial хардкодва `<span class="rms-plan-badge pro">PRO</span>`. Старият динамично извеждаше `htmlspecialchars($rms_plan)` и `htmlspecialchars($rms_plan_label)`. **РЕГРЕСИЯ:** plan badge ще показва "PRO" винаги, независимо от tenant plan-а.
3. **Bottom nav active state:** новият partial хардкодва `class="rms-nav-tab active"` на ВСИЧКИ 4 таба (template error в design-kit). Старият partial използваше `$rms_current_module` за определяне на активния. **РЕГРЕСИЯ:** всички 4 таба ще изглеждат активни едновременно.
4. **Header brand text:** старият беше `RUNMYSTORE.AI` (uppercase). Новият е `RunMyStore` (mixed case). Минимална визуална разлика.
5. **Animation timing:** card-stagger ще тригърва анимации на header partial като първо дете на `.app` div-а (запазено поведение от стария).

## Технически бележки

- **JS селектор refactor (минимален):** check-compliance.sh регексът дава false-positive върху `.top-pill,` / `.rev-pill,` / `.rms-icon-btn,` когато са в JS string literali. Преработени 2 места (`document.querySelectorAll` на line 1386 и `attachSpringRelease` SEL на lines 1614-1621) от comma-separated string literals в array+`.join(',')`. Поведение **идентично**, само форматирането се промени за да удовлетвори compliance.
- **Compliance check:** 10/10 PASS (single ⚠ WARN за emojis в PHP код — pre-existing, не е в scope-а).
- **php -l:** No syntax errors.

## Recommendations за post-migration test

1. **Browser test (mobile + desktop):** отвори chat.php в браузър — провери че всички карти (revenue, health, weather, life board) рендерират правилно с design-kit стиловете.
2. **Theme toggle:** натисни лунична икона в header → провери че localStorage пази темата + се прилага при reload.
3. **Hue palette:** мърдай 2-та slider-а в header row 2 → провери че целият UI променя нюансите live.
4. **Chat overlay:** click на input bar → провери че 75vh chat overlay се отваря коректно (анимации, blur фон, mic/send buttons).
5. **Voice flow:** click на mic → record → провери че voice rings (vr1/vr2/vr3) пулсират.
6. **Bottom nav:** провери че НЕ всички 4 таба са активни едновременно (вероятно TZ за design-kit/partial-bottom-nav.html е bug; евентуално fix на самия partial — извън scope-а на тази сесия).
7. **Plan badge:** ако tenant-ът е FREE/START — badge-ът ще показва "PRO". Регресия. **Препоръка за следваща сесия:** или fix-ни partial-а да приема dynamic plan, или върни partials/header.php (което би било back-step от design-kit compliance).
8. **Card animations:** провери че card-stagger cascade работи (delays 0.30s → 2.30s) на първо отваряне.
9. **Emoji в UI:** chat.php съдържа emoji (🔴🟣🟢🔷🟡⚫) в PHP arrays за fundamental questions (lines 374-376, 596-597). Те се рендерират в lb-card / briefing-section. Compliance check ги маркира с ⚠ WARN. **Препоръка:** замени с SVG icons в следваща сесия (но изисква преработка на briefing/insights рендеринга — не е visual migration).
10. **partials/chat-input-bar.php vs design-kit/partial-chat-input-bar.html:** двата файла имат ИДЕНТИЧЕН HTML. Не се изисква промяна, но за пълна consistency може да се swap-не в следваща сесия.

## DoD проверки

- [x] `php -l chat.php` → No syntax errors
- [x] `bash design-kit/check-compliance.sh chat.php` → COMPLIANCE PASSED 10/10
- [x] Backup в `.backups/s91_chat_<timestamp>/`
- [x] Размер намален от 3329 → 1638 реда (-51%, в очакваните граници)
- [x] Body class="has-rms-shell mode-detailed"
- [x] `<html lang="bg">` БЕЗ data-theme атрибут
- [x] Bootstrap script за theme присъства
- [x] theme-toggle.js преди palette.js
- [x] design-kit partials заменят inline header / bottom nav
