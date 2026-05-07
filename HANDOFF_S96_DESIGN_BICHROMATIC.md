# HANDOFF S96 — DESIGN_SYSTEM v4.1 BICHROMATIC

**Дата:** 2026-05-07 (вечер)
**Сесия:** S96 — Design System refactor + life-board.php migration
**Status:** ✅ WORKING in production (browser tested, Capacitor APK pending)
**File lock:** `DESIGN_SYSTEM_v4.0_BICHROMATIC.md`, `life-board.php`, `partials/shell-scripts.php`, `css/shell.css`, `CLAUDE_CODE_DESIGN_PROMPT.md`

---

## РЕЗЮМЕ — какво стана и защо

**Проблем (Tihol):** Дизайнът на проекта беше Frankenstein — всеки модул имаше различна интерпретация на Neon Glass. Старите документи (DESIGN_SYSTEM_v1, v3, DESIGN_LAW.md, kato-predniq-law) описваха само dark Neon Glass; светъл режим нямаше канонична спецификация → всяка нова Claude сесия импровизираше.

**Решение:** Един единствен canonical документ — `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` (v4.1 след S96 patches) — който описва **двата режима** (light default + dark Neon Glass option) с continuity matrix.

---

## КАКВО Е НАПРАВЕНО

### 1. DESIGN_SYSTEM_v4.0_BICHROMATIC.md (нов Bible — 2510+ реда)

**22 части** покриващи:
- Token system (continuity + light + dark)
- Header & Bottom nav 1:1 от partials
- Background system (light plain + dark radial + global aurora)
- SACRED Glass component (.glass + .shine + .glow с conic mask)
- 9 effects detailed code
- Animation library
- Typography (Montserrat + DM Mono)
- 13 component recipes
- 6 fundamental questions colors (q1-q6)
- Spacing/sizing tables
- Theme toggle logic (JS + PHP + cookie)
- Forbidden patterns
- Adoption checklist (10 sections)
- Migration guide
- **Part 22.5 (S96 ADDITIONS, v4.1):** brand animation, store picker recipe, засилени op-buttons shadows, dark ops neon, anti-flicker rule, theme toggle JS spec, fonts requirement
- Continuity matrix
- Quick reference (cheat sheet)

**Source of truth:** ТОЗИ ДОКУМЕНТ + life-board.php (reference impl).

### 2. Архивиране на стари дизайн документи (с DEPRECATED banner)

```
docs/archived/
├── DESIGN_SYSTEM_v3_archived.md
├── DESIGN_LAW.md (44KB, Apr 29 — стария Neon Glass-only)
├── S95_DESIGN_KIT_HANDOFF.md (10KB)
├── kato-predniq-law.html
└── SALE_V5_MOCKUP.html
```

Всеки има banner `⚠️ ARCHIVED — НЕ ИЗПОЛЗВАЙ. Активен Bible: ...`.

**Изтрити изцяло:** `products.php.bak.DESIGN_LAW_20260505_0923` (970KB), `docs/S95_DESIGN_KIT_HANDOFF.md` (dublicate).

`DESIGN_SYSTEM.md` е симлинк → `DESIGN_SYSTEM_v4.0_BICHROMATIC.md`.

### 3. life-board.php — eталонна миграция към v4.1

**Promени:**
- Цял `<style>` блок заменен (252→992 реда CSS)
- `data-theme` attribute на `<html>` (с `$_COOKIE['rms_theme'] ?? 'light'`)
- `<div class="aurora">` блок след `header.php` include (3 blobs)
- Google Fonts link (Montserrat + DM Mono) — без него browser default = грозно
- Star SVG → conic-gradient orb (за Life Board заглавие)
- Store picker в "Днес" cell-а (адаптиран от chat.php) — `lb-store-picker` class
- `$all_stores` SQL зареден (за picker)
- Засилени op-buttons shadows (8→12px convex; 4→6px inset на op-icon)
- Dark mode: outer glow + inner highlight + svg drop-shadow на op-buttons (неон ефект)
- Body НЯМА transition (anti-flicker)

**Backups запазени:**
- `life-board.php.bak.S96_V4_20260507_1605` (preMigration)
- `life-board.php.bak.S96_PATCH_20260507_1619` (преди fonts/depth/toggle)
- `life-board.php.bak.S96_PATCH2_20260507_1638` (преди store picker)
- `life-board.php.bak.S96_PATCH3_20260507_1643` (преди orb fix)

### 4. partials/shell-scripts.php — theme toggle fix

**Преди (BUG):**
```javascript
if (nxt === 'light') document.documentElement.setAttribute('data-theme', 'light');
else document.documentElement.removeAttribute('data-theme');  // ❌ активира :root:not([data-theme]) → винаги light
```

**Сега (FIX):**
```javascript
document.documentElement.setAttribute('data-theme', nxt);  // ✅ явно set
```

**Плюс init block (нов):**
```javascript
(function () {
  try {
    var saved = localStorage.getItem('rms_theme');
    var initial = saved || 'light';
    document.documentElement.setAttribute('data-theme', initial);
  } catch (_) {
    document.documentElement.setAttribute('data-theme', 'light');
  }
})();
```

Default = light (преди беше неявно dark поради липса на attribute).

### 5. css/shell.css — animated brand logo (`.rms-brand`)

**Преди:** Static text 11px, color hsl, simple text-shadow.
**Сега:**
- Font 15px (mobile 13px)
- Background gradient 200% width — anime shimmer 4s loop
- text-clip + transparent fill
- ::after pseudo с radial pulse glow (3s loop)
- `prefers-reduced-motion: reduce` → animation off

Brand се ползва **САМО от `partials/header.php`** → автоматично навсякъде.

### 6. CLAUDE_CODE_DESIGN_PROMPT.md (нов)

Прост промпт който Tihol поставя в Claude Code когато даваш дизайн задача.
- Пълна версия (~50 реда) — за нов модул / корекция
- Кратка версия (~4 реда) — за bug fix без visual промяна
- 7 задължителни правила: light=default, SACRED list, CSS vars only, continuity, fonts, aurora, no body transition

---

## 9-ТЕ EFFECTS — STATUS (light + dark)

| # | Effect | Light | Dark | Note |
|---|--------|-------|------|------|
| 1 | Aurora blobs | ✅ multiply blend | ✅ plus-lighter blend | работи |
| 2 | `.shine` (SACRED) | ❌ display:none | ✅ conic + mask-composite | правилно |
| 3 | `.glow` (SACRED) | ❌ display:none | ✅ noise mask + plus-lighter | правилно |
| 4 | Conic ring (PRO badge) | ✅ | ✅ | работи |
| 5 | Conic orb (Life Board) | ✅ | ✅ | работи (бъг fixed S96.PATCH3) |
| 6 | Glow ring (card expand) | ✅ | ✅ | работи |
| 7 | Conic shimmer (CTA) | ✅ | ✅ | работи |
| 8 | Glow halo (op hover) | ✅ multiply | ✅ plus-lighter | работи |
| 9 | Iridescent shimmer (AI Brain) | ✅ | ✅ | работи (двоен) |

---

## ИЗВЕСТНИ ПРОБЛЕМИ / PENDING

### 1. Capacitor APK — НЕ е тестиран

`mask-composite: exclude` (Effect #2 .shine) и `mix-blend-mode: plus-lighter` (Effect #3 .glow) **може да не работят** на Android < 11 WebView. Преди beta launch (14-15 май) трябва:
- APK rebuild
- Z Flip6 test (light + dark)
- Ako .shine/.glow не работят → fallback стратегия (по-прости shadows)

### 2. Само life-board.php е migrated

Останалите модули са СТАРИ (Frankenstein):
- `chat.php` — Detailed mode, използва s82-dash (стар Neon Glass)
- `products.php` (14000 реда!) — стар design
- `sale.php`, `orders.php`, `deliveries.php`, `inventory.php`, `transfers.php`, `stats.php`, `ai-studio.php`, `register.php`, `login.php`, `settings.php` — стар design

**Migration ред (по приоритет):**
1. chat.php (Detailed Mode home) — следва веднага
2. sale.php (S87E pending — 8 bug fix-а)
3. products.php (S95 wizard работа)
4. deliveries.php
5. orders.php
6. warehouse.php / inventory.php
7. stats.php
8. transfers.php
9. register.php / login.php / settings.php
10. ai-studio.php

### 3. design-kit/check-compliance.sh — НЕ е обновен

Стария check-compliance.sh валидира v3.0 Neon Glass-only patterns. Трябва update да валидира v4.1 BICHROMATIC. **Висок приоритет** преди следващия модул migration.

### 4. Bottom-nav SACRED — потвърди

При life-board.php migration не съм пипал `partials/bottom-nav.php` (4 tabs locked). Добре е така — bottom nav стилове идват от `css/shell.css` и са унифицирани в Bible-а Част 3.4.

---

## ФАЙЛОВИ ПРОМЕНИ — quick reference

| Файл | Действие | LOC delta |
|------|----------|-----------|
| `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` | created | +2510 |
| `DESIGN_SYSTEM.md` | symlink → v4.0 | symlink |
| `life-board.php` | full migration | +1504 / -260 |
| `partials/shell-scripts.php` | theme toggle fix + init | +14 |
| `css/shell.css` | brand animation | +25 / -3 |
| `CLAUDE_CODE_DESIGN_PROMPT.md` | created | +92 |
| `docs/archived/*` | 5 старите файлове + banners | renamed |
| `products.php.bak.DESIGN_LAW_20260505_0923` | deleted | -970KB |
| `docs/S95_DESIGN_KIT_HANDOFF.md` | deleted (dup) | -10KB |
| `MASTER_COMPASS.md` | updated | +5 |
| `HANDOFF_S96_DESIGN_BICHROMATIC.md` | created | +new |

**Commits в S96:**
- `478eb4d` — DESIGN_SYSTEM_v4.0 created
- `3345c83` — archive + symlink
- `dd95c74` — DEPRECATED banners (old)
- `29d8d21` — archive remaining + delete .bak
- `c492b57` — fix HTML banner corruption
- `c64a18f` — life-board → BICHROMATIC + brand + theme fix (5 файла, +1504)
- `54ff51c` — gitignore (.bak, patch скриптове)
- `45a0917` — остатъци (MASTER_COMPASS, products.php, capacitor-printer, banners)
- (next) — COMPASS update + S96 handoff

---

## СЛЕДВАЩА СЕСИЯ (S97 предложения)

**Опция A:** chat.php migration (Detailed Mode → v4.1 BICHROMATIC) — да unify-нем главните entry points
**Опция B:** Capacitor APK rebuild + Z Flip6 test — критично преди beta launch (14-15 май)
**Опция C:** check-compliance.sh update + pre-commit hook — гарантира compliance в бъдещи commits
**Опция D:** sale.php migration (S87E bug fixes + v4.1 styling) — beta-critical модул

**Препоръка:** B → C → A → D ред (test first, lock down compliance, after-that migrate).

---

## REFERENCE FILES

- Active Bible: `/var/www/runmystore/DESIGN_SYSTEM.md` → `DESIGN_SYSTEM_v4.0_BICHROMATIC.md`
- Reference impl: `/var/www/runmystore/life-board.php`
- Claude Code prompt: `/var/www/runmystore/CLAUDE_CODE_DESIGN_PROMPT.md`
- Header partial: `/var/www/runmystore/partials/header.php` (canonical, 7 елемента)
- Bottom nav partial: `/var/www/runmystore/partials/bottom-nav.php` (canonical, 4 tabs)
- Shell scripts: `/var/www/runmystore/partials/shell-scripts.php` (rmsToggleTheme + init)
- Global CSS: `/var/www/runmystore/css/shell.css` (.rms-brand + .rms-bottom-nav + .rms-icon-btn)

---

**Beta launch:** ENI store, 14-15 май 2026 (FIXED).
**Days remaining:** ~7-8 дни.
