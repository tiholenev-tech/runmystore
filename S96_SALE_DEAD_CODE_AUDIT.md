---
title: sale.php — Dead Code Inventory (Read-Only Audit)
sprint: S96.SALE.STRESS_SWEEP — Phase 10
date: 2026-05-04
author: Code Code #2 (Claude Opus 4.7)
mode: READ-ONLY. No deletions performed. Tihol decides cleanup commit (separate sprint).
note: intended path was eod_drafts/SALE_DEAD_CODE_AUDIT.md but eod_drafts/ is root-owned (tihol cannot write); relocated to repo root.
---

## SCOPE

sale.php (3,817 LOC, post S96.HARDEN_A/B/E + TENANT_GUARD_SWEEP). Inventory of:
1. JS functions defined but with 0 actual callers (definition-only references)
2. Dead wrappers (function exists only to delegate to another)
3. Suspicious includes, vars, or CSS classes — flagged for Tihol review

## 1. CONFIRMED DEAD JS FUNCTIONS (safe to delete)

### F-DEAD-1 — `updatePmCardActive` (sale.php:2778)
```js
function updatePmCardActive() { /* no-op: legacy V5 pkg-card replaced by simple pills */ }
```
- **Refs:** 1 (definition only)
- **Status:** Self-marked no-op leftover from V5 pkg-card → simple-pills migration.
- **LOC saved:** 1.

### F-DEAD-2 — `openQtyModal` (sale.php:3027)
```js
function openQtyModal(idx) { return openQtyEditor(idx); }
```
- **Refs:** 1 (definition only).
- **Status:** Dead wrapper. `openQtyEditor` (L3019) is called directly everywhere else.
- **LOC saved:** 1.

### F-DEAD-3 — `openLpPopup` (sale.php:3110)
```js
function openLpPopup(idx) { return openQtyEditor(idx); }
```
- **Refs:** 2 — but one is a comment at L3018 (`// S87G.B2 — Qty edit modal (replaces openQtyEditor / openLpPopup native prompt)`), the other is the definition. **Net: 0 callers.**
- **Status:** Dead wrapper. Same delegation pattern as F-DEAD-2.
- **LOC saved:** 1.

**Total confirmed-dead: 3 functions, ~3 LOC.** Trivial savings — delete in a one-liner cleanup commit when convenient.

## 2. PROBABLY-LIVE-BUT-WORTH-VERIFYING

### F-CHECK-1 — `debugLog` (sale.php:1961)
- **Refs:** 12 — 1 definition + 11 call sites.
- **Status:** Used. But `debugLog` typically writes to console / hidden DOM panel — verify it doesn't ship console.log noise to production. **Consider gating behind `STATE.debug` flag if not already.**

### F-CHECK-2 — recOv legacy wireup (sale.php:3290-3310, IIFE `wireRecOvLegacy`)
- **Status:** Comment self-describes it as legacy:
  > rec-ov is no longer opened by sale-page voice button (kept in DOM for future modules)
- **DOM elements `recOv` / `recSend` / `recCancel`:** if these IDs no longer exist in current sale.php DOM, the IIFE is no-op (event handlers attached to null = silently skipped). No harm but **20 LOC of legacy** that could be removed if Tihol confirms the DOM is gone.

## 3. PHP / SERVER-SIDE — CLEAN

- 3 `require_once`: database.php, config.php, helpers.php — all used.
- 3 `include` partials: design-kit/partial-header.html, partials/header.php, design-kit/partial-bottom-nav.html — all rendered.
- 0 standalone PHP functions defined in sale.php (all logic inline). No PHP dead code.

## 4. CSS — SAMPLE CHECK (low priority, cosmetic only)

CSS dead-class detection is expensive (would need full DOM/JS render trace). Spot-check on suspicious-looking classes:
- `.cart-empty` / `.cart-empty-icon` / `.cart-empty-text` — used at L1683-1685 ✓
- `.success-hero` / `.success-circle` (L1230-1231) — search for usage in JS DOM building. **Not verified in this pass — flag for full audit if/when CSS minification sprint runs.**

## 5. SUMMARY

| Category | Count | LOC saved | Risk |
|----------|-------|-----------|------|
| Confirmed dead JS functions | 3 | ~3 | Low (no callers) |
| Probably-dead legacy IIFE | 1 | ~20 | Low (event handlers on null = no-op) |
| Probably-live but verify | 1 (`debugLog`) | 0 (just gate it) | Console noise |
| PHP dead code | 0 | 0 | — |
| CSS dead classes | not audited | unknown | Cosmetic |

**Recommendation:** queue a one-line cleanup commit `S96.SALE.DEADCODE_TRIM` deleting the 3 confirmed dead functions (~3 LOC). Defer recOv legacy IIFE pending Tihol confirmation that `recOv`/`recSend`/`recCancel` DOM elements are gone. CSS audit for separate sprint.

**Outside scope of this audit:** template strings, inline event handlers (92 `onclick=`), CSS keyframes, vendor prefixes — too large for read-only sweep.
