# Translation Gaps — S117

**Date:** 2026-05-08

## Summary

| Aspect | Count |
|--------|------:|
| `lang/bg.json` keys total | 24 |
| `lang/bg.json` keys ACTUALLY USED in code | **0** (orphan) |
| `lang/en.json` exists | ❌ NO |
| `lang/ro.json` exists | ❌ NO |
| `lang/sr.json` exists | ❌ NO |
| `t()` function defined anywhere | ❌ NO |

**Conclusion:** The i18n scaffolding (`lang/bg.json`) was created but never wired up. The codebase is mono-locale (BG-only) with no translation layer.

## bg.json — Current State (24 keys, all wizard.*)

```
wizard.copy_from_last
wizard.copy_field_from_last
wizard.make_main_photo
wizard.type_single
wizard.type_variant
wizard.type_variants
wizard.skip_photo
wizard.markup_pct
wizard.category
wizard.subcategory
wizard.supplier
wizard.origin
wizard.no_previous
wizard.copied_from_last
wizard.field_empty_in_last
wizard.photo_main_set
wizard.step1_title
wizard.step2_title
wizard.step3_title
wizard.step4_title
wizard.zone_label
wizard.zone_hint
wizard.autogen_barcode
wizard.autogen_sku
```

**All 24 keys are orphaned — `grep`ing the entire repo for any of these literal keys returns 0 hits.** They were intended for `ai-wizard.php` or the products wizard but never migrated.

## Missing Locales

There is **no `en.json` / `ro.json` / `sr.json`** in `/var/www/runmystore/lang/`. For EN/RO/SR launch readiness:

1. Define complete `bg.json` first (keys for ALL UI strings — currently 24, target ~600-1000 based on the 3,963 Cyrillic-bearing lines and assuming dedup).
2. Translate to en.json (machine + human review).
3. Same for ro/sr if entering those markets.

## Estimated Translation Volume

Based on hardcoded BG audit:
- ~3,963 Cyrillic-bearing PHP lines
- Estimated unique strings (after dedup of common phrases): **600-900 strings**
- Estimated translation effort:
  - Machine translation (Gemini/Claude): 1 hour to run
  - Human QA per language: 1-2 weeks for native speaker (especially for technical retail/POS terms)

## Recommendation

### Step 1 — Define complete bg.json (target Day 1-3)

Replace the 24 wizard keys with the full ~700 keys covering all UI text. Use the proposed-keys.json from `05_proposed_keys.json` as a starting template.

### Step 2 — Build the t() function (Day 3-4)

See `01_hardcoded_bg_strings.md` §Recommendation Phase 1.

### Step 3 — Migrate all hardcoded → t() in code (Day 4-10)

Per-file migration. Validate with running app in BG locale (functionally identical).

### Step 4 — Translate bg.json → en.json (Day 11-15)

Use AI for first pass, human QA pass. Special attention to:
- Currency (EUR vs USD vs GBP)
- Number format (1,234.56 vs 1.234,56 vs 1 234,56)
- Date format (MM/DD/YYYY vs DD.MM.YYYY)
- Address format
- Plural forms (English has 2, Bulgarian has 3, Russian-style — cover with proper i18n library)

### Step 5 — RO/SR (after EN is solid)

Same pattern. Estimate 5-7 days each.

## DOD for Launch Readiness

For EN launch:
- [ ] `t()` function in production
- [ ] `lang/bg.json` complete (~700 keys, 0 orphans, 0 missing)
- [ ] `lang/en.json` complete with same key set
- [ ] Language switcher in settings (changes `$_SESSION['lang']` + cookie)
- [ ] All hardcoded BG migrated to `t()` calls
- [ ] Smoke test in EN: every screen, every button, every error message
- [ ] Currency switching: `EUR` works for EN tenants
- [ ] Date format: MM/DD/YYYY for EN, DD.MM.YYYY for BG
