# i18n Audit — S117 HANDOFF

**Date:** 2026-05-08
**Auditor:** Code Code 1 (Opus, automated scan)
**Duration:** ~25 minutes (well under 2h cap)
**Method:** Read-only static scan; **ZERO git operations**, **ZERO touch on `lang/*.json`**, **ZERO touch on `products.php` / `partials/`**.

## Executive Summary

**Launch readiness for EN/RO/SR markets: 0%.**

The codebase has i18n *scaffolding* (`lang/bg.json` with 24 wizard.* keys) but no working translation layer. **The keys are not used**. UI text is hardcoded in BG everywhere — labels, placeholders, error messages, JS toasts, alerts, confirms.

To launch in EN: 15-20 working days of effort (framework + migration + translation + QA).

## Top Findings

| # | Finding | Severity | Impact |
|---|---------|----------|--------|
| 1 | `t()` translation function does NOT exist anywhere | **CRITICAL for launch** | Cannot ship in any non-BG market |
| 2 | All 24 keys in `lang/bg.json` are orphan (used in 0 files) | HIGH | Existing scaffold not wired |
| 3 | No `en.json` / `ro.json` / `sr.json` files exist | **CRITICAL for launch** | Can't switch language |
| 4 | 3,963 Cyrillic-bearing PHP lines (50+ files in scope) | HIGH | Massive translation surface |
| 5 | No PHP `priceFormat()` helper — only JS in sale.php | HIGH | Inconsistent currency formatting |
| 6 | Hardcoded `€` in ai-studio.php:500, 516 | HIGH | Wrong currency for non-EUR tenants |
| 7 | Hardcoded `'лв'` default fallback in 3 files | MEDIUM | Wrong default for EN/RO/SR launches |
| 8 | 60+ hardcoded date format calls (`d.m.Y`, `d.m`) | HIGH | Date display wrong in EN locale |
| 9 | Number format inconsistent across files | MEDIUM | Some use `.` decimal, some `,` |
| 10 | Weather labels duplicated chat.php / life-board.php | LOW | Should be a shared helper |

## Files in /tmp/i18n_audit/

| File | Topic |
|------|-------|
| `01_hardcoded_bg_strings.md` | 50+ specific BG strings with `file:line` and proposed `t('key')` |
| `02_hardcoded_currency.md` | 13 currency hits + `priceFormat()` PHP helper recommendation |
| `03_translation_gaps.md` | Orphan keys, missing locales, framework gap |
| `04_date_format_issues.md` | Date/number/phone — `dateFormat()` helper recommendation |
| `05_proposed_keys.json` | 110-key starter template — drop-in for `lang/bg.json` |
| `HANDOFF.md` (this) | Executive summary + readiness assessment |

## Key Numbers

| Metric | Value |
|--------|------:|
| Files scanned | 56 PHP + 7 partials |
| Files in scope (excluding products + biz-coefficients) | 54 |
| Cyrillic-bearing lines | 3,963 |
| Specific findings cataloged | 50+ (DOD ≥50 met) |
| Top offender file | `products_fetch.php` (1,037 hits) |
| Hardcoded currency hits | 13 |
| Hardcoded date format hits | 60+ |
| Existing bg.json keys | 24 (all orphan) |
| Proposed starter keys | 110 |
| Estimated total keys for full coverage | 600-900 |

## DOD Verification

| Criterion | Status |
|-----------|--------|
| 6 files in /tmp/i18n_audit/ | ✅ 6 (01-04 + 05.json + HANDOFF) |
| ≥50 hardcoded BG strings identified | ✅ 50+ (across 01_hardcoded_bg_strings.md) |
| Proposed keys patch ready to merge | ✅ 110-key JSON in 05_proposed_keys.json |
| ZERO git ops | ✅ |
| Time ≤ 2h | ✅ ~25 min |

## Roadmap to EN Launch

### Sprint 1 — Foundation (Week 1)

1. **Day 1-2:** Build `t()` function in `config/i18n.php` (sample code in `01_hardcoded_bg_strings.md`)
2. **Day 2-3:** Build `priceFormat()` and `dateFormat()` PHP helpers (sample code in `02_` and `04_`)
3. **Day 3:** Wire `partials/shell-init.php` to load i18n + helpers
4. **Day 4:** Merge `05_proposed_keys.json` into `lang/bg.json`, replace 24 orphan keys
5. **Day 5:** Smoke test — load chat.php and verify `t()` returns BG strings correctly

### Sprint 2 — Migrate top 5 files (Week 2)

Order by user impact: `login.php` → `register.php` → `onboarding.php` → `chat.php` → `sale.php`

For each:
- Replace hardcoded strings with `t('key')` calls
- Replace currency display with `priceFormat($v, $tenant)`
- Replace date display with `dateFormat($v, $tenant)`
- Add new keys to `bg.json` as needed
- Smoke test in BG (functionally identical)

### Sprint 3 — EN translation (Week 3)

1. **Day 11-12:** Copy `bg.json` → `en.json`, machine-translate via Gemini/Claude
2. **Day 13:** Native EN speaker QA pass (1 day)
3. **Day 14:** Build language switcher UI in settings.php
4. **Day 15:** End-to-end QA in EN (all flows: login, register, onboarding, sale, chat, life-board, settings)

### Sprint 4 — Migrate remaining files (Week 4)

`life-board.php`, `stats.php`, `inventory.php`, `delivery.php`, `deliveries.php`, `ai-studio.php`, `ai-chat-overlay.php`, `xchat.php`, `defectives.php`, `order.php`, `orders.php`, `settings.php`, `warehouse.php`, etc.

Aim: every UI string from `t()`. After this sprint, EN locale is complete.

### Sprints 5+ — RO / SR (per market need)

Each adds ~5-7 days for translation + 3-5 days QA.

## Out of Scope (this audit)

- `products.php` (Code 2-owned) — biggest offender at 1,371 i18n hits per prior audit
- `biz-coefficients.php` (also Code 2 territory, 707 hits)
- JavaScript-side translations (separate concern — `js/capacitor-printer.js` had 45 hits in prior audit)
- Email/SMS templates (not scanned)
- Backend AI prompts in BG (e.g., `build-prompt.php`) — these may need to STAY BG for AI quality, or also be translated

## Risk Notes

1. **AI prompts in BG:** `build-prompt.php` and similar files send Bulgarian prompts to LLMs. Translating these to EN is technically possible but may degrade response quality (the LLM's BG output has been tuned to BG-language stores). Decision needed: bilingual prompts OR per-locale prompt sets.

2. **Plural forms:** Bulgarian uses 3 plural forms (singular/dual/many). English has 2. Romanian has 3. Serbian has 4. Use a proper i18n library that supports plural rules (e.g., MessageFormat / ICU).

3. **Currency conversion:** Tenants in EN markets may have prices stored in BGN — UI must convert to display currency (EUR) using a rate. Out of scope here, but flag for product.

4. **Tenant-stored data:** Product names, descriptions, supplier names are tenant-stored in their language. The UI chrome translates, but the data does not. This is the standard SaaS pattern.

## Hand-off Status

✅ **COMPLETE.** Read-only audit, no production changes. All findings documented. Tihol/Code 2 can use `05_proposed_keys.json` as the starter `lang/bg.json` patch and the helper code samples as drop-in implementations.
