# S82.STUDIO.11 — Standalone /ai-studio.php main page

**Status:** ✅ Shipped
**Commit:** (see git log)
**Tag:** v0.7.30-s82-studio-11-standalone
**Date:** 2026-04-26
**Mockup source:** `/root/ai-studio-main-v2.html` (approved by Tihol)

## Scope (delivered)

A standalone `/ai-studio.php` page, mobile-first 480px, that gives the tenant
an overview of all AI image work pending across their catalogue. Reuses
production rms-header + bottom-nav + glass design system.

### Sections
- **rms-header** (production partial)
- **Hero banner** glass card with star icon + "Преобрази каталога си"
- **Credits bar** (3 types: бял фон / описания / AI магия) — clickable opens
  `aiStudioBuyCredits()` stub
- **Бързи действия** card with 2 deterministic ops (only shown when count > 0):
  - 🖼 Махни фон (€0.05 × count)
  - 📝 Генерирай описания (€0.02 × count)
  - Empty state when both = 0: "Всички продукти имат бял фон и описание ✓"
- **AI магия по категории** — 5 cards:
  - Дрехи (indigo) · Бельо/бански (pink) · Бижута (amber) · Аксесоари (teal) · Друго (purple)
- **Последно генерирано** strip (last 8 from ai_image_usage, empty state if none)
- **Стандартни настройки** — 3 settings rows (Model · Background · Quality Guarantee)
- **FAB** "Попитай AI" (links to chat.php)
- **Plan lock card** when tenant.plan === 'free' (replaces all sections)
- **rms-bottom-nav** (production partial)

## Constraints honoured

- ✅ NO database schema changes (no ALTER TABLE)
- ✅ NO touching of products.php, sale.php, chat.php, partials/*
- ✅ NO touching of ai-image-processor.php (read for context only)
- ✅ NO Stripe / cron / migrations
- ✅ Reuses existing partials (header.php, bottom-nav.php, shell-init.php, shell-scripts.php)
- ✅ Mobile-first 480px max-width
- ✅ Glass system 1:1 with mockup
- ✅ Specific git add (ai-studio.php only)

## Backend stubs

All interactive elements log alerts referencing the future STUDIO phase:

- `aiStudioBuyCredits()` → STUDIO.17 (3-pack modal + Stripe S88)
- `aiStudioBulkBg()` → STUDIO.16 (background-removal queue)
- `aiStudioBulkDesc()` → STUDIO.16 (bulk Gemini desc)
- `aiStudioOpenCategory(cat)` → STUDIO.14 (queue list overlay) → STUDIO.12 (per-product modal)

## Mock data points

These are visible to the user but rely on future schema (STUDIO.13):

1. **Description credits balance** — hardcoded 100 / 100 (no `ai_credits_desc`
   column yet; will be added in STUDIO.13).
2. **Category counts** — distributed pseudo-randomly (40/27/17/10/6 of total
   active products) since `products.ai_category` doesn't exist yet. Real
   counts will be wired in STUDIO.13.
3. **Category sub-text** ("8 тениски · 3 рокли · 1 сако") — hardcoded
   placeholder strings until subtypes column exists.

## Real data points (already live)

1. **bg credit balance** — `tenants.ai_credits_bg` / `ai_credits_bg_total`
2. **magic credit balance** — `tenants.ai_credits_tryon` / `ai_credits_tryon_total`
3. **Plan** — `effectivePlan($tenant)` honors trial_ends_at
4. **god mode** — tenant#7 still gets unlimited credits (from STUDIO env)
5. **Bulk bg count** — products WHERE image_url IS NULL OR data: URL
6. **Bulk desc count** — products WHERE description IS NULL or empty
7. **History** — last 8 from `ai_image_usage` (empty state when fresh tenant)

## Files touched

- `ai-studio.php` (NEW, 48 KB)
- `docs/SESSION_S82_STUDIO_11_HANDOFF.md` (NEW, this doc)

## Test verified on tenant=99

- ✅ Page renders without PHP notices/errors
- ✅ All 5 category cards present
- ✅ Hero banner + credits bar + bulk + history + settings + FAB + bottom-nav present
- ✅ HTML output size 48 KB (well under any limit)
- ✅ php -l clean

## Next steps (NOT in STUDIO.11)

- **STUDIO.12** → Replace wizard step 5 modal with `ai-studio-categories.html` mockup
- **STUDIO.13** → DB migration: ai_category / ai_subtype / ai_prompt_templates / 3-credit-type refactor
- **STUDIO.14** → Toggle Стандартно/Настрой + 5-category UI in per-product modal
- **STUDIO.15** → Quality Guarantee + retry/refund logic
- **STUDIO.16** → Bulk bg + bulk desc operations (background queue)
- **STUDIO.17** → Volume packs UI + Stripe stub for S88
