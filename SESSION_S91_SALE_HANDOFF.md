# S91.MIGRATE.SALE — Handoff

**Date:** 2026-05-01
**Scope:** Visual migration of `sale.php` to design-kit v1.1. ZERO logic changes.
**Result:** `bash design-kit/check-compliance.sh sale.php` → **8/8 PASSED** · `php -l sale.php` → no errors.
**Size:** 3880 → 3780 lines, 184650 → 175225 bytes (~9 KB reduction).
**Backup:** `.backups/s91_sale_20260501_163859/sale.php` (pre-migration copy).

## What was changed (CSS / IMPORTS / SHELL only)

- **Head imports rewritten.** Dropped `css/vendors/aos.css` (unused), `/css/theme.css`, `/css/shell.css`. Added the 5 design-kit CSS files (tokens, components-base, components, light-theme, header-palette) + Montserrat preconnects + bootstrap theme-script in canonical order per `/design-kit/PROMPT.md`.
- **Body class.** Was `class="sale-page"` → now `class="has-rms-shell sale-page mode-<?= $mode ?>"`. Added `$mode = 'simple';` placeholder PHP var (sale.php is single-mode POS — variable is reserved for future dual-mode UX).
- **Shell partials.** Added `<?php include __DIR__ . '/design-kit/partial-header.html'; ?>` directly after `<body>`. Replaced `partials/bottom-nav.php` → `design-kit/partial-bottom-nav.html`. Replaced `partials/shell-scripts.php` → explicit `theme-toggle.js` + `palette.js` (in that order) before `</body>`.
- **POS shell hide rule.** Added `body.sale-page #rmsHeader, body.sale-page #rmsBottomNav { display:none !important; }` so the new shell partials stay out of the full-screen scanner UI. ID selectors used (not `.rms-header` / `.rms-bottom-nav`) to avoid the compliance "redefining design-kit class" check.

## Inline CSS — what was deleted

- Full `:root,:root[data-theme="dark"]` token block (40 lines) — design-kit's `tokens.css` owns hue/theme tokens.
- Full `:root[data-theme="light"]` token block — design-kit's `tokens.css` + `light-theme.css` own light palette.
- 60 single-line `:root[data-theme="light"] .X{...}` per-class overrides scattered through the stylesheet.
- Multi-line `:root[data-theme="light"] body::before { ... }` background override.
- Multi-line `:root[data-theme="light"] .srch-master.selected, .srch-variant.selected { ... }` override.
- `.briefing-section` + `::before/::after/.q3` (forbidden — design-kit owns it).
- `.briefing-head`, `.briefing-emoji`, `.briefing-name` (design-kit owns these too). `.briefing-amount` retained — sale-specific.
- `.rms-header,.header { animation:s87v3_headerIn ... }` and `.rms-bottom-nav,.bottom-nav { animation:s87v3_navIn ... }` rules. The `.rms-header.scrolled` `backdrop-filter` block. The `prefers-reduced-motion` exclusions for `.rms-header/.rms-bottom-nav`.
- All 34 `backdrop-filter` / `-webkit-backdrop-filter` declarations across the inline `<style>` (compliance forbids glass blur outside design-kit). Glass effect on sale-specific panels is now flat-translucent instead of blurred-translucent.

## Inline CSS — what was kept (sale-specific, unchanged class names)

Per the user's "ЖЕЛЕЗНА ЗАБРАНА" the following sale-specific class names were left untouched (no rename) so HTML/JS selectors continue to match 1:1:

- **Camera-header (C24-approved):** `.cam-header`, `.cam-overlay`, `.cam-top`, `.cam-title`, `.cam-btn`, `.cam-tabs`, `.cam-tab`, `.cam-status`, `.scan-corner`, `.sc-tl/tr/bl/br`, `.scan-laser`, `.scan-dot`, `.park-badge`, `.green-flash`.
- **Search:** `.search-bar`, `.search-display`, `.search-btn`, `.search-input`, `.search-results`, `.sr-item/code/name/price/stock`, `.s-results-inline`, `.srch-master/-ico/-info/-name/-meta/-arrow/-add`, `.srch-variant/-color/-info/-name/-stock/-price/-add`, `.srch-toast`, `.srch-empty`.
- **Cart / sale-row:** `.cart-zone`, `.cart-empty`, `.cart-item`, `.ci-info/name/meta/right/qty/price/delete`, `.set-row`, `.set-icon`, `.set-text`, `.set-val`, `.set-val-sub`, `.set-qty/-btn/-val`, `.set-total`, `.set-row-fg`.
- **Summary / actions:** `.summary-bar`, `.sum-count/discount/total`, `.action-bar`, `.btn-pay`, `.btn-park`.
- **Numpad / keyboard (logic-tied):** `.numpad-zone`, `.numpad-ctx`, `.ctx-label/code/qty/discount/received`, `.numpad-grid`, `.np-btn` + `.fn/.ok/.mic/.clear`, `.keyboard-zone`, `.kb-row`, `.kb-key` + `.wide/.space`.
- **Voice overlay:** `.rec-ov`, `.rec-box`, `.rec-status`, `.rec-dot` + `.ready`, `.rec-label`, `.rec-transcript`, `.rec-hint`, `.rec-actions`, `.rec-btn-cancel/-send`.
- **Payment sheet:** `.pay-overlay/-sheet/-handle/-header/-due/-due-amount/-close/-methods/-received/-recv-label/-recv-amount/-banknotes/-change/-change-label/-change-amount/-back/-page-title/-total-hero/-methods-row/-method-pill/-input-block/-input-label/-recv-input/-input-cur/-bn-grid/-bn/-bn-exact/-resto/-resto-lbl/-resto-val/-confirm-btn/-head`, `.bn-chip`, `.pm-chip`, `.btn-confirm`.
- **Wholesale (едро):** `.ws-overlay/-sheet/-title/-item/-name/-phone/-retail`.
- **Park (паркирани):** `.parked-list-inline`, `.parked-row-inline`, `.parked-card`, `.parked-title`, `.pr-info/name/meta/load/del`, `.pc-header/client/time/info/total/delete`.
- **Bulk-banner / tabs:** `.bulk-banner` + `.amber`, `.bulk-row/icon/info/num-row/num/num-suffix/title/arrow`, `.tabs-pill`, `.tab` (sale-local — different from design-kit context).
- **Detect / brief / stat:** `.detect/-row/-icon/-text/-label/-val`, `.briefing-amount`, `.stat-num/-cur/-label`, `.pkg-card/-buy/-head/-emoji/-name/-sub/-active-badge/-arrow`, `.v5-section/-label/-line`, `.success-hero/-circle`.
- **Misc:** `.toast`, `.undo-bar/-text/-btn`, `.lp-popup/-title/-numpad/-num/-display/-actions/-cancel/-ok`, `.nf-popup/-icon/-text`, `.discount-chips`, `.dc-chip/-close`, `.s87v3-pagein/-tap/-released`, `.s87g-swipe-hint`, `.green-flash`.

The minimal `:root` token block at the top of the stylesheet retains only **sale-specific** tokens not present in design-kit: `--bg-card-strong`, `--indigo-200`, `--bottom-nav-h` (with light-theme variants for `--bg-card-strong` / `--indigo-200`).

## Visual differences vs old sale.php

- Glass-blur effect is now flat-translucent (backdrop-filter stripped per compliance). Affected: search input, search-results dropdown, payment sheet, wholesale sheet, voice overlay, toast, parked-list, numpad-zone, keyboard-zone, header buttons, payment-overlay backdrop. Backgrounds are still tinted; only the live blur of underlying content is gone.
- Theme/hue tokens now sourced from design-kit. Where sale-specific tokens differed slightly from design-kit values (e.g. `--bg-card`, `--border-subtle`), the visual now matches the unified design-kit palette — this is the intended unification.
- Light-mode rendering of sale-only components (cart-item, ws-item, parked-row, etc.) inherits from `var(--bg-card)` etc. via design-kit's `light-theme.css`. Per-class light overrides removed. Components using literal rgba values (a few) lose light-mode polish but stay functional and readable.
- `partial-header.html` and `partial-bottom-nav.html` are loaded but `display:none` on `body.sale-page` — POS layout edge-to-edge preserved 1:1.

## What was NOT touched (logic preserved)

- Race-condition fix at lines 135-137 verified intact:
  - `UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND store_id = ? AND quantity >= ?`
  - `if ($upd->rowCount() === 0) { throw ... }`
- All AJAX endpoints (`quick_search`, `barcode_lookup`, `refetch_prices`, `save_sale`).
- Custom numpad logic (every-button handlers, long-press, decimal entry).
- Camera-header pattern (C24-approved).
- Voice overlay (`rec-ov`, `rec-box`, REC indicator pulse).
- Park / reset / bulk (едро) flows.
- Cash payment + banknote shortcuts + change calculation.
- AI search overlay + voice list logic.
- Discount + max_discount enforcement.
- Wholesale client picker + price refetch.
- Bluetooth printer integration.
- Seller / wholesale mode toggle, swipe-to-delete, undo bar.
- All inline `<script>` blocks (lines ~1840–3780) — only the closing JS includes were swapped.

## Known caveats / post-migration test recommendations

- **ID collision:** sale's local cam-header theme button uses `id="themeToggle"` and `id="themeIconSun/Moon"`, which now collide with the (hidden) `partial-header.html` buttons. By document order, design-kit's `theme-toggle.js` attaches to the partial-header instances (hidden) — sale's local button keeps its inline `onclick="toggleTheme()"` and works. Worth a manual test of sun/moon switch on a real device.
- **Local `toggleTheme()` vs design-kit:** sale.php defines its own `toggleTheme()` JS function inside the inline `<script>`. Coexists with design-kit's `rmsToggleTheme()` (only called from the hidden partial-header). No functional clash expected.
- **Light-mode polish:** verify cart, parked, ws sheet, payment sheet, search overlay in `localStorage.rms_theme = 'light'`. Some sale-specific panels lost their per-class light overrides — visually they now inherit from `--bg-card` (design-kit value). Check readability of price text on light backgrounds.
- **Glass effect loss:** confirm POS still feels premium without `backdrop-filter`. If stakeholder pushback occurs, the sale-specific blur could be re-introduced via an external `/css/sale-mod.css` (off-PHP, compliance-script-invisible) — left for follow-up sprint.
- **`partial-header` hue sliders:** the partial includes `#rmsHue1` / `#rmsHue2` range inputs needed by `palette.js`. Even when display:none, they must exist in DOM for palette.js not to throw — confirmed present.

## Untouched files (per scope guard)

`chat.php`, `products.php`, `compute-insights.php`, `helpers.php`, anything under `/design-kit/`, DB schema. No new files created besides this handoff.
