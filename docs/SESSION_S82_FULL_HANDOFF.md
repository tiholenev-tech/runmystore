# S82 SESSION — FULL HANDOFF

**Date:** 2026-04-25 → 2026-04-26
**Tenant tested:** #7 (Ени Тихолов = god mode), #99 (eval framework)
**Final tag:** `v0.7.30-s82-studio-11-standalone`
**Latest commit on main:** `98f4126`

Two sub-streams shipped in this session:
1. **S82.COLOR** — variant-photo wizard (camera + AI color detect + photo mode toggle)
2. **S82.STUDIO** — AI Studio modal (per-product) + standalone /ai-studio.php + supporting wizard fixes

---

## 1. WHAT'S LIVE

### A. New file
- **`ai-studio.php`** (48 KB) — standalone AI Studio main page. Mockup-faithful glass design, 5 categories, bulk ops, history, settings, FAB, plan-aware lock for FREE.

### B. Modified files
- **`products.php`** — variant photo wizard, AI Studio modal in step 4, MKP cell stepper, draft auto-save, native camera + loading animations, swipe blocked on sub-modules, AI prompt card always visible
- **`ai-color-detect.php`** — multi-image support (`?multi=1`), Gemini Vision prompt focused on central object
- **`ai-image-credits.php`** — god-mode tenant whitelist (`RMS_IMAGE_GOD_TENANTS` env, default `7`)
- **`partials/shell-scripts.php`** — `isSwipeAllowedHere()` gate restricts swipe nav to 4 root tabs only

### C. New endpoints in products.php
- `?ajax=ai_credits` — returns plan + bg/tryon balances + is_locked flag for the AI Studio header

---

## 2. FEATURE SUMMARY (user-visible)

### Variant photo wizard (S82.COLOR.4 → COLOR.17)
- Photo mode toggle (Една снимка / Различни цветове) in step 3
- Multi-photo carousel with swipe (2 cards visible, snap-to-card)
- Native phone camera per shot (real Samsung HDR, full sensor quality)
- Drawer tip: "Ако се отвори селфи камерата — обърни в Camera-та"
- Per-photo AI color detect (Gemini Vision, central object, multi-image batch)
- Auto-populate step 4 axes from detected colors
- Client-side downscale 1000px @ q=0.80 (~80 KB per photo)

### Step 4 wizard (S82.STUDIO.2 → STUDIO.10)
- Empty-axis tab no longer blocks save (works with colors-only)
- "Колко бр.?" matrix overlay also works for single-axis
- Per-cell **МКП** stepper (Минимално Количество за Поръчка) auto-calculated
- AI prompt card at bottom of step 4:
  - Always visible when at least one axis has values
  - Amber warning + greyed-out buttons when no qty entered yet
  - Green summary "X комбинации · общо Y бр." when qty present
  - 4-icon visual feature grid (replaces bullet list)
  - Yes / No save buttons
- Step 5 effectively bypassed for variant flow

### AI Studio per-product modal (S82.STUDIO.1 → STUDIO.4)
- Opens on "Да, отвори AI Studio" → wizSave success → modal slides up
- Skips Step 6 print flash when going to AI Studio
- Plan-aware lock for FREE tenants
- Live credits bar (PRO / START / GOD pill)
- 4 sections from mockup:
  - Image compare strip (before/after)
  - Bg removal (single + bulk, real fal.ai birefnet, working)
  - AI Magic — 6 model grid (UI ready, nano-banana-pro endpoint needed)
  - Studio — 8 preset chips (UI ready, endpoint needed)
  - SEO description (Gemini, working via existing ?ajax=ai_description)
- Export grid: Etiket (CapPrinter BLE), CSV (WooCommerce), PDF (print window)
- Buy credits modal — 3 packs €5/€15/€40 (Stripe stub for S88)

### AI Studio standalone (S82.STUDIO.11)
- New `/ai-studio.php` page accessible from menu
- Header (rms-header) + bottom-nav (production partials)
- Hero + credits bar + bulk ops + 5 categories + history + settings + FAB
- Plan lock for FREE
- All interactive stubs alert "идва в STUDIO.x" referencing future phases

### Wizard quality of life (S82.STUDIO.5 → STUDIO.10)
- Auto-save draft to `localStorage._rms_wizDraft_<tenantId>` on every renderWizard
- Restore prompt on wizard re-open: "Намерих незавършен артикул 'X' · 12 мин назад"
- Cleared on wizSave success, 7-day expiry
- Swipe nav disabled on all sub-modules (only chat / warehouse / stats / sale)
- 0-qty popup smarter — only fires when matrix is genuinely empty

---

## 3. KNOWN MOCKS / PLACEHOLDERS (await STUDIO.13 schema)

| What | Where | Real source after STUDIO.13 |
|---|---|---|
| Description credits balance (100/100) | ai-studio.php credits bar | `tenants.ai_credits_desc` |
| Category counts (40/27/17/10/6 % distribution) | ai-studio.php categories card | `products.ai_category` real GROUP BY |
| Category sub-text ("8 тениски · 3 рокли · 1 сако") | ai-studio.php | `ai_subtype` aggregation |
| AI Magic generation | products.php AI Studio modal | nano-banana-pro endpoint (Tihol decision: keep nb-pro €0.50) |
| Studio (preset) generation | products.php AI Studio modal | nano-banana-pro endpoint |
| Bulk bg / desc operations | ai-studio.php buttons | background queue + jobs |
| Buy credits modal in standalone | ai-studio.php credits-bar tap | volume-pack modal + Stripe |
| Queue list overlay | ai-studio.php category tap | new overlay UI |

---

## 4. DB STATE

**No schema changes in this session.** Only data writes:
- `tenants` (id=7) → `plan='pro'` set in S82.STUDIO.1.a
- `ai_image_usage` → entries from real bg removal calls (god-mode tenant skipped via `RMS_IMAGE_GOD_TENANTS`)

**Pending schema for next sessions (STUDIO.13):**
```sql
ALTER TABLE products
  ADD COLUMN ai_category VARCHAR(20) NULL,
  ADD COLUMN ai_subtype VARCHAR(30) NULL;

ALTER TABLE tenants
  ADD COLUMN ai_credits_desc INT NOT NULL DEFAULT 0,
  ADD COLUMN ai_credits_desc_total INT NOT NULL DEFAULT 0,
  ADD COLUMN included_bg_per_month INT NOT NULL DEFAULT 0,
  ADD COLUMN included_desc_per_month INT NOT NULL DEFAULT 0,
  ADD COLUMN included_magic_per_month INT NOT NULL DEFAULT 0,
  ADD COLUMN bg_used_this_month INT NOT NULL DEFAULT 0,
  ADD COLUMN desc_used_this_month INT NOT NULL DEFAULT 0,
  ADD COLUMN magic_used_this_month INT NOT NULL DEFAULT 0;

CREATE TABLE ai_prompt_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(20) NOT NULL,
  subtype VARCHAR(30) NULL,
  template TEXT NOT NULL,
  success_rate DECIMAL(5,2) DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (category, is_active)
);

ALTER TABLE ai_spend_log
  MODIFY COLUMN status ENUM('completed_paid','retry_free','refunded_loss')
    NOT NULL DEFAULT 'completed_paid',
  ADD COLUMN parent_log_id INT NULL,
  ADD COLUMN attempt_number INT NOT NULL DEFAULT 1,
  ADD INDEX (parent_log_id);
```

---

## 5. ENV / CONFIG

`/etc/runmystore/api.env` — same. New optional key:
```ini
# Comma-separated tenant IDs that bypass AI image quota (default: 7)
RMS_IMAGE_GOD_TENANTS=7
```

---

## 6. OPEN PHASES (ordered priority for next session)

1. **STUDIO.12** — Replace wizard step 5 modal with `ai-studio-categories.html` design (per-product). Currently STUDIO.4 modal is functional but doesn't match the new mockup with toggle Стандартно/Настрой.
2. **STUDIO.13** — DB schema migration (above SQL). Adds the foundation for real category counts + 3-credit-type tracking.
3. **STUDIO.14** — Toggle Стандартно/Настрой mode in modal + queue list overlay from category tap on standalone page + 5 categories with subtypes.
4. **STUDIO.15** — Quality Guarantee (2 free retries + refund button + parent_log_id chain). Backend changes in `ai-image-processor.php`.
5. **STUDIO.16** — Bulk bg removal + bulk desc generation (background queue, anti-abuse counters).
6. **STUDIO.17** — Volume packs UI (€5/€15/€30/€50/€100), Stripe stub for S88.
7. **STUDIO.18+** — Pre-flight Gemini Vision quality check, prompt template seeding for the 5 categories.

**Outside S82.STUDIO scope** (separate sessions per Tihol):
- `chat-detailed-GLASS.html` redesign of chat.php
- `simple-mode-GLASS.html` redesign of simple.php / life-board.php
- `add-product-step3-step4.html` Step 3 & 4 visual redesign (mockup is here, work pending)

---

## 7. REVERT MAP (per phase)

Every phase has its own tag — `git revert <tag>` un-does just that phase:

| Phase | Tag | Reverts |
|---|---|---|
| Variant photo wizard base | `v0.7.2-s82-color-photo-wizard` | photo mode toggle + AI color detect |
| Camera (final) | `v0.7.15-s82-color-drawer-tip` | native camera with drawer tip |
| AI Studio modal scaffold | `v0.7.20-s82-studio-1a-scaffold` | basic modal + bg removal |
| Step 4 → step 5 routing | `v0.7.21-s82-studio-1b-step5-routing` | "Към запис" routing |
| Wizard bug fixes | `v0.7.22-s82-studio-2-phaseA` | empty-axis allow + AI prompt in step 4 |
| AI Studio full mockup | `v0.7.23-s82-studio-D-full-mockup` | image compare + sections + exports |
| МКП terminology | `v0.7.28-s82-studio-9-mkp-terminology` | МКП naming + visual feature grid |
| МКП cell + draft cache | `v0.7.29-s82-studio-10-mkp-cell-and-cache` | per-cell stepper + auto-save |
| Standalone /ai-studio.php | `v0.7.30-s82-studio-11-standalone` | new file deletion |

---

## 8. BACKUPS ON DROPLET

Time-stamped manual backups in `/root/`:
- `products.S82.COLOR.{4..16}.bak.HHMMSS` — every camera iteration
- `products.S82.STUDIO.{1b,2,9}.bak.HHMMSS` — major studio refactors
- `ai-color-detect.S82.COLOR.{4,6,7}.bak.HHMMSS`

These are point-in-time snapshots independent of git, useful if a revert series gets messy.

---

## 9. WHAT WAS DELIBERATELY NOT DONE (per Tihol's directives in this session)

- ❌ DB schema changes (STUDIO.13's ALTER deferred)
- ❌ Stripe integration (S88 reserved)
- ❌ Cron jobs (`cron-monthly.php` for usage reset deferred)
- ❌ Touching `chat.php`, `sale.php`, other root pages
- ❌ Touching `tools/diagnostic/` (parallel S81 session was working there)
- ❌ Touching `partials/header.php` / `bottom-nav.php` (production partials reused)
- ❌ Per-product modal redesign with new mockup (STUDIO.12 next)
- ❌ Per-category prompt templates beyond lingerie (S82.PROMPTS)
- ❌ AI Magic / Studio backend (nano-banana endpoints deferred to STUDIO.14+)

---

## 10. PARALLEL SESSION COURTESY

Throughout this session a parallel **S81.DIAG.VERIFY** Claude was committing in `tools/diagnostic/`. Honored their CHECKPOINT request mid-session, never `git pull` / `push` / `stash` / `reset` while they were committing, never staged `tools/diagnostic/*` paths. Selective `git add <specific files>` used everywhere.

---

## 11. TODO FOR NEXT CLAUDE SESSION

If picking this up:

1. **Read** `/root/ai-studio-categories.html` (STUDIO.12 mockup)
2. **Read** `/var/www/runmystore/products.php` lines 6800–7100 (current AI Studio modal)
3. **Replace** `studioRenderSections()` body with the new mockup structure
4. **Add** Стандартно/Настрой toggle + 5 category chips + 6 model grid (when Настрой mode)
5. **Wire** generate buttons to call `ai-image-processor.php` (will need new endpoint when nano-banana is added)
6. **Test** on tenant=7 (god mode), commit, tag v0.7.31-s82-studio-12

Backup before STUDIO.12 starts: `cp products.php /root/products.S82.STUDIO.12.bak.HHMMSS`
