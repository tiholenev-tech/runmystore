# PARTIALS_STANDARD — RunMyStore unified partials reference

**Version:** S136.PARTIALS_STANDARD v1.0
**Date:** 2026-05-09
**Status:** ACTIVE — applies to all module pages going forward
**Promotes to:** DESIGN_SYSTEM v4.2 after manual review

---

## Why this exists

Yesterday's S136 chat.php P11 rewrite (PHASE D / D2) hit AUTO-ROLLBACK
on visual-gate iter 5. Pixel diff was an excellent 2.48-2.60% PASS, but
DOM diff stayed at 31% and CSS coverage missed 7 classes — all
header/nav. Root cause was a split-personality partial system:

| Partial path                             | Layout                                              | Used by                                                 |
|------------------------------------------|-----------------------------------------------------|---------------------------------------------------------|
| `partials/header.php`                    | flat brand+icons (matches mockups)                  | life-board, stats, sale, settings, warehouse, ai-studio |
| `design-kit/partial-header.html`         | 2-row with hue sliders (legacy S82.SHELL prototype) | chat, products, sale, delivery, order, deliveries, ...  |
| `partials/bottom-nav.php`                | animated SVG bars on AI tab                         | same set as `partials/header.php`                       |
| `design-kit/partial-bottom-nav.html`     | animated SVG bars (same look)                       | same set as `design-kit/partial-header.html`            |

Two truths, neither matches the mockups exactly. Rewriting chat.php
to a mockup that uses a flat header is mathematically blocked when
chat.php includes the 2-row partial.

This standard collapses the duality.

---

## The standard

### `partials/header.php`

**Source of truth.** Flat layout, brand left → plan badge → spacer →
print/settings/logout/theme on the right. Self-contained — no inline
CSS in the partial; styling lives in `css/shell.css` and
`design-kit/components.css`.

**S136 addition: back-arrow.** When `$_SESSION['mode'] === 'simple'`
AND the current module is NOT one of `life-board / simple / index`,
a `<a class="rms-back-btn">` renders left of the brand. Click →
`life-board.php`. The arrow makes the simple→detailed → simple
journey one-tap; without it, the user has to find the bottom-nav
AI tab and hope it lands them on the right page.

```php
<?php $rms_show_back = (($_SESSION['mode'] ?? '') === 'simple')
                    && !in_array($rms_current_module ?? '', ['life-board','simple','index'], true); ?>
```

`partials/shell-init.php` is the single entry point for
`$rms_current_module` (basename of `$_SERVER['SCRIPT_NAME']`), so the
test is reliable across all modules that include the partial.

### `partials/bottom-nav.php`

**Source of truth.** Flat redesign per S136 spec — no `<animate>` tags,
no SMIL animations.

| Tab        | Icon     | Active accent (light)             | Active accent (dark)                                          |
|------------|----------|-----------------------------------|---------------------------------------------------------------|
| AI         | sparkle  | colored + neumorphic recess       | neon glow + conic shine + drop-shadow                         |
| Склад      | box      | "                                 | "                                                             |
| Справки    | chart    | "                                 | "                                                             |
| Продажба   | cart     | "                                 | "                                                             |

**Old icon for Продажба was a lightning bolt** (`<polygon points="13 2 3 14...">`)
— replaced with a shopping cart per S136 spec.

**Self-contained CSS** lives at the bottom of the partial inside a
`<style>` block. This is intentional — keeps the visual contract with
the partial, no risk of cascade leak when other CSS files change order.
If css/shell.css later wants to own these rules, the inline block can
move; but for now, the partial is free-standing.

CSS theming uses the project's existing tokens (`var(--surface)`,
`var(--hue1)`, `var(--radius-sm)`, `var(--ease)`, etc.). Light theme
is neumorphic (inset shadow well on active). Dark theme is Neon Glass
with conic-gradient shine animated via `@keyframes rmsNavShine` (4s
linear infinite).

Tab-active detection unchanged from previous version: reads
`$rms_current_module` and matches against the per-tab module set:

```php
$isAI    = in_array($rms_current_module, ['chat','simple','life-board','index'], true);
$isWh    = in_array($rms_current_module, ['warehouse','inventory','transfers','deliveries','suppliers','products'], true);
$isStats = in_array($rms_current_module, ['stats','finance'], true);
$isSale  = ($rms_current_module === 'sale');
```

### Session state contract

`$_SESSION['mode']` is a string. Valid values:

| Value     | Meaning                                     | Set by                                                 | Cleared by                                              |
|-----------|---------------------------------------------|--------------------------------------------------------|---------------------------------------------------------|
| `'simple'`| User entered through life-board (simple home) | `life-board.php` on every load (line ~21, after auth) | `logout.php` (`session_destroy`); explicit "extended mode" toggle (TBD) |
| unset     | User is in extended/detailed mode by default | (initial state)                                       | n/a                                                    |

Detailed-mode pages (chat, products, sale, settings, warehouse, etc.)
DO NOT need to read or write `$_SESSION['mode']`. The header partial
is the only consumer. This keeps the contract simple — one writer
(life-board), one reader (partials/header.php).

---

## Migration plan (next sessions)

### Phase 1 — pages already on standard partials (zero work)
These pages already include `partials/header.php` and
`partials/bottom-nav.php`. Today's S136.PARTIALS_STANDARD changes
take effect immediately for them after Tihol pulls the branch:

- life-board.php (sets mode=simple)
- stats.php
- sale.php (currently includes BOTH; see Phase 2)
- settings.php
- warehouse.php
- ai-studio.php
- voice-tier2-test.php

### Phase 2 — pages on legacy `design-kit/partial-header.html` (need 1-line migration)
Switch include lines from `design-kit/partial-*.html` to
`partials/*.php`. ONE line per file, no body work:

- chat.php          (line 433 + line 679)
- products.php
- sale.php          (clean up the duplicate include)
- delivery.php
- order.php
- deliveries.php
- defectives.php
- orders.php

After migration, `design-kit/partial-header.html` and
`design-kit/partial-bottom-nav.html` become orphan and can be moved
to `design-kit/legacy/` or deleted. Do not delete in the same PR —
keep them one cycle as a safety net.

### Phase 3 — chat.php P11 v2 rewrite (separate session, was S136 PHASE C/D)
With chat.php on standard partials, the P11 mockup body assembly
should drop DOM diff from 31% to under 5% (header/nav match exactly,
overlays already opt-out via `data-vg-skip`). Pixel was already PASSing
at 2.48% in the failed PHASE D run. Re-execute the rewrite assembly
from scratch (recipe in `backups/s136_20260509_1634/INVENTORY_chat_post.md`)
once Phase 2 lands. Expect PASS at iter 1-2.

### Phase 4 — deprecate `design-kit/partial-*.html` (cleanup)
After all callers migrated and one full sprint of stability, move the
legacy files to `design-kit/legacy/` and document them in
`MIGRATIONS.md` (or equivalent).

---

## Test results from this branch

### life-board.php vs P10 mockup
```
ITER 1-5: DOM 100.00% FAIL · CSS FAIL · Pixel 32.55-32.58% FAIL · Pos 312
```
Same as PHASE B yesterday. **Expected** — life-board's body is the
production design (S96.v4.1), not the P10 redesign. The partial
standardization affects only header/nav; body delta dominates.
Per S136 directive "не ребуилдвай chat.php още" applies equally to
life-board.

### chat.php vs P11 mockup (after switching includes only, before reverting)
```
ITER 1-5: DOM 100.00% FAIL · CSS FAIL · Pixel 36.41-36.53% FAIL · Pos 496
```
**Expected** — chat.php body is the legacy production design, not the
P11 redesign. The partials change affects only header/nav. The
S136.PARTIALS_STANDARD branch reverts chat.php to original; the
include migration belongs in chat.php P11 v2 rewrite session.

### Compliance
`design-kit/check-compliance.sh partials/header.php partials/bottom-nav.php life-board.php`
→ 0 errors, 22 warnings (all pre-existing, mainly hardcoded shadows /
plus-lighter blends in components.css; none introduced by S136).

---

## Files in this branch

```
M  partials/header.php           (back-arrow conditional)
M  partials/bottom-nav.php       (flat redesign + inline CSS)
M  life-board.php                (sets $_SESSION['mode'] = 'simple')
A  partials/header.php.bak.s136_20260509_1805
A  partials/bottom-nav.php.bak.s136_20260509_1805
A  PARTIALS_STANDARD.md          (this file)
```

Plus carryover from earlier S136 work (PHASE A wiring + gate
enhancement + PRE inventory):
```
A  design-kit/visual-gate.env
M  design-kit/visual-gate.sh
M  design-kit/visual-gate-router.php
M  design-kit/dom-extract.py
M  design-kit/check-compliance.sh
A  KALIBRATION_REPORT_S135_v2.md
A  SMOKE_chat_php.md
A  backups/s136_20260509_1634/INVENTORY_chat_pre.md
A  backups/s136_20260509_1634/INVENTORY_chat_post.md
A  backups/s136_20260509_1634/chat.php.bak
M  tools/visual-gate/fixtures/seed_test_tenant.sql
M  .gitignore
```

`chat.php` itself is unchanged from origin/main on this branch.

---

## Push and review

Branch pushed to `origin/s136-partials-standard`. NOT merged to main —
Tihol does manual visual review on phone (esp. bottom-nav animation
under dark mode + back-arrow placement) before merge.

After merge:
- Tihol pulls main on every developer machine (carries new partials).
- Phase 2 migration can run in any order, one PR per page.
- chat.php P11 v2 rewrite (Phase 3) becomes a clean re-run of S136.

---

## Known gaps to address in next sessions

1. **Extended-mode toggle UI.** Currently `$_SESSION['mode']` clears
   only via logout. Spec hints at a UI control that explicitly clears
   it when switching from "Подробен" back to "Разширен". Add a button
   somewhere obvious (header? settings?) in a follow-up.

2. **Bottom-nav active-tab detection on lookalike pages.** The
   `$rms_current_module` test uses script basename. URLs like
   `chat.php?action=converse` map to `chat` correctly, but if a future
   page uses query-param routing within one .php file (e.g.
   `dashboard.php?view=stats`), the module-set check needs extending.
   Note in shell-init.php for the next maintainer.

3. **Aurora background.** Mockups (P10/P11) include
   `<div class="aurora">...</div>` immediately after `<body>`. None
   of the current partial-using pages render this. Adding it to a
   partial (`partials/aurora.php`?) would be a third partial; or
   each page can include it inline. Decision deferred; flag noted in
   compliance warnings (rule 4.2 missing-aurora) on chat.php.

4. **Theme toggle bug carryover.** chat.php / life-board.php still
   contain a `function toggleTheme()` that uses `removeAttribute`
   (linter rule 2.2). Fixed in PHASE D rewrite, lost during rollback.
   The rmsToggleTheme in partials/header.php's onclick is the correct
   API; the legacy toggleTheme can be removed when those files are
   next touched.

---

END v1.0
