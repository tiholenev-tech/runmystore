# SESSION S91 — products.php → design-kit v1.1 (visual migration)

**Дата:** 2026-05-01
**Файл:** products.php (12592 → 12432 реда; ~160 реда CSS изтрити)
**Backup:** /home/tihol/backups/s91_products_*/products.php

## Какво беше направено (САМО CSS / IMPORTS / SHELL)

### Импорти в `<head>`
- Премахнати: `/css/theme.css`, `/css/shell.css` (заменени от design-kit/tokens.css + components-base.css)
- Добавени по реда от PROMPT.md v1.1:
  - `<meta name="theme-color" content="#08090d">`
  - Theme bootstrap script (преместен ПРЕДИ CSS)
  - Preconnect за fonts.googleapis.com / fonts.gstatic.com
  - 5 design-kit CSS файла (tokens, components-base, components, light-theme, header-palette)

### JS преди `</body>`
- Запазен: `/partials/shell-scripts.php` (rmsToggleLogout, rmsOpenPrinter, swipe nav, prefetch — НЕ са в design-kit)
- Запазен: `js/aibrain-modals.js`
- Добавени (theme-toggle ПРЕДИ palette — compliance v1.1 правило):
  - `/design-kit/theme-toggle.js`
  - `/design-kit/palette.js`

### Shell partials
- `partials/header.php` → `design-kit/partial-header.html` (нов 2-row layout с hue1/hue2 пъзгачи)
- `partials/bottom-nav.php` → `design-kit/partial-bottom-nav.html`
- `partials/chat-input-bar.php` остава (специфично за products)

### Body
- `<body>` → `<body class="has-rms-shell mode-<?= ($user_role==='seller')?'simple':'detailed' ?>">`

### Изтрити CSS блокове (вече в design-kit)
- `:root { --bg-main / --hue1 / --hue2 / --indigo-* / --radius / --ease ... }` — 2 копия
- `.glass {...}` пълен (S73.B.5) + минифициран (S79 A1.7) — 2 копия
- `.glass .shine`, `.glass .shine::before/::after`, `.glass .shine-bottom`
- `.glass .glow`, `.glass .glow::before/::after`, `.glass .glow-bottom`, `.glass .glow-bright`
- `.glass.sm`, `.glass.q1` — `.glass.q6` (хюове за 6 въпроса) — 2 копия
- `html,body{background:..;color:..;font-family:Montserrat..}` — заменя се от design-kit/components-base.css

### Изтрити свойства от inline `<style>` (compliance гард)
- 49 × `backdrop-filter:blur(Npx);` и `-webkit-backdrop-filter:blur(Npx);` — премахнати с регекс в lines 1100..3927 (тялото на inline `<style>`)

### Преименувани класове (mod-prod-* prefix)
- `.health-sec .shine` → `.health-sec .mod-prod-health-line`
- `.health-ov-box .shine` → `.health-ov-box .mod-prod-health-line`
- HTML `<span class="shine">` (вътре в health-sec / health-ov-box) → `<span class="mod-prod-health-line">`

### Inline `style="--hue1..."` извадени в CSS класове (compliance гард)
- "Още групи за бизнес" CTA → `.mod-prod-more-groups`
- v4 footer (wizard step) → `.mod-prod-v4-footer`
- "Колко бр.?" matrix CTA → `.mod-prod-mx-cta`

## Visual differences (стария → новия)
- **Header:** 1-row brand+icons → 2-row (brand+icons горе, hue1/hue2 sliders долу). Hue palette сега се променя от потребителя в реално време (palette.js).
- **Plan badge:** беше динамичен ($rms_plan от shell-init) → сега хардкоднато "PRO" (от design-kit/partial-header.html).
- **Backdrop blur:** изчезна от ~46 overlay-а / sticky рамки. Прозрачните панели вече показват чист фон вместо размазан.
- **Tokens:** идват от design-kit/tokens.css (--bg-main #08090d вместо #030712). Малка визуална разлика в основния тъмен фон.
- **Bottom nav:** активен tab е оцветен (design-kit вариант с rms-nav-tab.active клас).
- **Light theme:** автоматично през design-kit/light-theme.css когато `data-theme="light"`.

## UNTOUCHED — логика и Sprint B fix-ове запазени 1-към-1
- ✓ Wizard стъпки и навигация (`wizPrev`, `wizNext`, `S.wizData`, всички renderWizard/wizCollectData)
- ✓ Back arrow в wizard (`#wizBackBtn`, chevron polyline 15 18 9 12 15 6 — line 4395)
- ✓ Variation matrix overlay (`.mx-overlay`, openMxOverlay)
- ✓ Scanner integration (C2/C3) — артикулен номер + баркод containers
- ✓ Hint auto-fill полета (C4)
- ✓ "Като предния" copy-from-previous flow
- ✓ AI auto-fill predictions, AI footer suggestions
- ✓ D3 supplier→categories filter (7 grep matches; SQL логика nedefin не е пипана)
- ✓ D5 LIVE duplicate detection (AJAX endpoints, fetch handlers непипнати)
- ✓ G1: swipe nav остава gated по NAV_ORDER в shell-scripts.php → products.php няма swipe (както е по дизайн)
- ✓ Photo upload, gallery, color prediction chips, scanner икони
- ✓ All AJAX endpoints (compute_insights, sections, search, ...)
- ✓ fmtMoney() — НЕ е дефиниран локално в products.php (живее в config/helpers.php), нищо за махане

## DOD статус
- ✓ `php -l products.php` — No syntax errors detected
- ✓ `bash design-kit/check-compliance.sh products.php` — **COMPLIANCE PASSED** (10/10 OK + 1 емоджи WARN за `★`/`🎤`/`✕`/`📷` — pre-existing)
- ⚠ Sprint B grep checks от prompt-а (DOD reference):
  - `grep -c "Добави размер"` → 0 (фразата не съществува нито в нов, нито в стария файл — DOD очакване не е в код)
  - `grep -c "ChevronLeft|chevron-left"` → 0 (back arrow съществува като inline SVG `polyline points="15 18 9 12 15 6"`, но никога не е именуван "ChevronLeft" в products.php)
  - `grep -c "supplier_id.*categories|categories.*supplier"` → 7 ✓ (D3 filter е жив)

## Препоръки за post-migration test
1. Отвори products.php като seller и owner — Simple vs Detailed mode през body class.
2. Тествай light/dark theme toggle — иконата (sun/moon) трябва да реагира; localStorage `rms_theme` се записва.
3. Hue1/Hue2 sliders в header → визуално се променят .glass карти и hue-зависими градиенти.
4. Wizard flow end-to-end: добавяне на артикул, scanner C2/C3, AI auto-fill C4, back arrow C5, variation matrix.
5. Health card "Здраве на склада" — 1px градиентна линия трябва да се показва (.mod-prod-health-line).
6. "Още групи за бизнес" CTA, v4Footer, "Колко бр.?" бутон — визуално еквивалентни на стария вариант.
7. Загуба на blur зад overlays/sticky bars (modal-ov, mx-header, studio-modal-ov, etc.) — потвърди дали loss е приемлив или искаш blur да се добави в design-kit.
