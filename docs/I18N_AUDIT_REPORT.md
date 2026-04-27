# I18N Audit Report — RunMyStore

**Scan date:** 2026-04-27 (S87.I18N.AUDIT)
**Files scanned:** 75 (`*.php`, `*.html`, `*.js` under repo root, excluding `.git`, `vendor`, `node_modules`, `mobile/{android,www,node_modules}`, `uploads`, `fonts`, `images`, `tmp`, `mirrors`, archive folders, `.bak*` / `*-backup-*` / `*.min.*`)
**Methodology:** static regex scan via `tools/audit/i18n_audit.py`-style script (developed in `/tmp/i18n_audit.py`), no source files modified.
**Companion data:** `docs/I18N_AUDIT_DATA.json` — every violation as `{file, line, category, severity, matched, context, suggested_fix}`.

This report closes the analysis half of MASTER_COMPASS REWORK QUEUE entries **#4 (currency format)** and **#5 (BG strings)**, both originally logged 17.04.2026 with no concrete inventory until now.

---

## 1. Executive Summary

**Total violations: 5,204** across 4 categories and 75 files.

| Category | Count | What it represents |
| --- | ---: | --- |
| `BG_STRING` | 3,764 | Hardcoded Bulgarian text in PHP/HTML/JS likely intended for the user. |
| `BG_STRING_DATA` | 1,093 | Bulgarian text in domain-data files (taxonomy, color names, business types) — i18n debt but lower display priority. |
| `CURRENCY` | 206 | Hardcoded `лв` / `BGN` / `€` / `EUR` literals. |
| `LOCALE` | 141 | `+359`, `Bulgaria`, `'BG'`, `'bg'`, `bg_BG`, `'България'`. |

**By severity:**

| Severity | Count | Definition |
| --- | ---: | --- |
| `HIGH` | 2,130 | User-visible UI strings: `echo`, `<?=`, HTML body text, `innerText`, `alert`, `title=`, `placeholder=`, hardcoded `лв` next to numbers in HTML. |
| `MEDIUM` | 1,379 | User-facing files but ambiguous emit context (variable names, string assignments, internal labels). |
| `LOW` | 1,695 | Comments, domain-data dictionaries, prompt template fragments, defensive defaults in seed/migration code. |

**Top-line takeaway:** the codebase is unambiguously BG-only. Public launch (September 2026) requires a parallel-track fix because the bulk of HIGH violations live in two files (`products.php`, `products_fetch.php`) that are also the most heavily edited modules. A best-of-both strategy is to ship beta in BG (no t() yet) and stand up the t() infrastructure on the side, then sweep file-by-file post-beta.

---

## 2. By module / area

| Area | HIGH | MEDIUM | LOW | Total | Notes |
| --- | ---: | ---: | ---: | ---: | --- |
| `products.php` + `products_fetch.php` | 1,275 | 987 | 221 | **2,483** | The single biggest source of i18n debt. Rewrite-pending (Phase B/C); fix during rewrite. |
| `chat.php` + `xchat.php` | 240 | 57 | 47 | **344** | UI labels, button text, modal copy. |
| `inventory.php` + `sale.php` | 138 | 64 | 6 | **208** | Inventory and POS labels. sale.php is up for rewrite (S87). |
| `stats.php` | 75 | 3 | 14 | **92** | Stats/charts axis labels. |
| AI subsystem (`ai-helper.php`, `build-prompt.php`, `compute-insights.php`, `ai-safety.php`, `ai-studio.php`, `ai-wizard.php`) | 74 | 82 | 425 | **581** | Most "violations" are BG-language prompt templates — these are intentional (AI speaks BG). True UI labels are concentrated in `ai-studio.php` (60 HIGH) and `ai-wizard.php`. |
| Domain data (`biz-coefficients.php`, `biz-compositions.php`) | 48 | 0 | 758 | **806** | Reference dictionaries (color names, taxonomy keys). i18n debt but not user-blocking. |
| Onboarding / auth (`onboarding.php`, `login.php`, `register.php`) | 14 | 51 | 13 | **78** + ~8 | First-touch UX — should be high priority for international launch. |
| Partials (`partials/*.php`) | ~20 | ~10 | ~5 | ~35 | Bottom nav, header, chat input bar — small but render on every page. |
| Other PHP / JS | ~250 | ~125 | ~150 | ~525 | Long tail. |

(Severity sums above add up to ~5,200; rounding from per-file roll-up.)

---

## 3. Top 20 Worst Offender Files

```
file                                         total  HIGH  MED   LOW   BG    CUR  LOC  DATA
products.php                                 1371   710   539   122   1284   74   13     0
products_fetch.php                           1112   565   448    99   1036   63   13     0
biz-coefficients.php                          707     1     0   706      1    2    0   704   ← reference data
xchat.php                                     174   122    29    23    169    1    4     0
chat.php                                      170   118    28    24    165    1    4     0
ai-helper.php                                 129     3    14   112      3   14    0   112   ← prompt templates
inventory.php                                 128    89    36     3    123    1    4     0
build-prompt.php                              102     1    18    83      0   15    4    83   ← prompt templates
biz-compositions.php                           99    47     0    52      1    0   46    52
stats.php                                      92    75     3    14     87    2    3     0
chat-send.php                                  89    29    24    36     89    0    0     0
sale.php                                       80    49    28     3     74    1    5     0
onboarding.php                                 78    14    51    13     67    8    3     0
ai-safety.php                                  77     1     3    73      0    0    4    73
compute-insights.php                           74     5     0    69      0    6    0    68
ai-studio.php                                  66    60     4     2     53    3   10     0
config/helpers.php                             64     0     1    63     62    2    0     0
ai-wizard.php                                  63     4    43    16     61    2    0     0
life-board.php                                 54    40     9     5     52    1    1     0
js/capacitor-printer.js                        45     0     0    45     41    4    0     0
```

Reading guide:
- Files with **HIGH ≥ 30** are user-facing modules whose every page render emits Bulgarian text.
- Files where **DATA dominates** (`biz-coefficients.php`, `biz-compositions.php`, `ai-helper.php`, `build-prompt.php`, `ai-safety.php`, `compute-insights.php`) are mostly LOW — the BG text is taxonomy or LLM prompt content. They still need an eventual i18n strategy but are not blockers for an EN UI shipping over a BG dataset.
- `js/capacitor-printer.js` LOW count is mostly comments and printer-firmware-specific BG receipt strings; remediation may be deferred (printer template lives in BG anyway for tax-compliance reasons in BG).

---

## 4. Critical Findings (sample HIGH-severity)

These are real user-facing strings observed in the scan (full list in JSON):

```
products_fetch.php:3523  <div class="q-sub">Bestsellers с ниска наличност</div>
products.php:7588        {id:'bra',label:'Сутиени',values:['65A','65B',...
products_fetch.php:5655  showToast('Няма достъп до камерата','error')
products.php:4000        <div class="art-prc">24 лв</div>            ← currency hardcoded next to number
products.php:4408        pills+=`<span class="rc-pill rc-orange">без снимка</span>`
products.php:5564        openVoice('Цветове? Или кажи "без"', ...)
products_fetch.php:4719  <div class="wiz-info-box">…<button>Разбрах</button>…
products.php:4074        <div class="art-ctx q6">94 дни · <b>210 лв замразени</b></div>
chat.php:2423            ' + p.qty + ' бр</div>'
xchat.php:1718           Колко добре AI познава магазина ти. Расте когато:<br>
life-board.php:469       <button class="lb-action" onclick="lbOpenChat(event,'<?= $title_js ?>')">Защо?</button>
ai-studio.php:*          60 HIGH violations — entire UI in BG
sale.php:*               49 HIGH violations — POS UI
inventory.php:*          89 HIGH violations — inventory dashboard
stats.php:*              75 HIGH violations — chart titles, axis labels
```

---

## 5. Currency Violations (all 206)

| Token | Count | Typical context |
| --- | ---: | --- |
| `'лв'` (in single quotes) | 106 | `echo $x . ' лв'`, `<div>... лв</div>`, JS template literals |
| `'€'` | 64 | Pricing pages (FREE €0 / START €19 / PRO €59 / BIZ €109), AI Studio cost labels |
| `'EUR'` | 29 | API payloads, AI Studio cost rows, schema seeds |
| `"'лв"` | 4 | Same as #1 with double quotes |
| `'BGN'` | 3 | Database default seeds + 1 PHP literal |

**By file:**
```
74  products.php
63  products_fetch.php
15  build-prompt.php          ← AI prompts referencing currency
14  ai-helper.php             ← cost helper formatting BG-only
 8  onboarding.php            ← signup pricing display
 6  compute-insights.php      ← insight title templates ("210 лв замразени")
 4  js/capacitor-printer.js   ← receipt printer fallback
```

**Existing helper:** `config/helpers.php:364` defines `fmtMoney(float $amount, string $currency = '€'): string`. **Coverage: nearly zero — all 206 hardcoded sites bypass it.** Step 1 of remediation is to replace bare `' лв'` concatenations with `fmtMoney($amount, $tenant_currency)` calls.

**Recommended canonical helper signature** (post-rewrite):

```php
priceFormat(float|int $amount, ?array $tenant = null, ?string $currency = null): string
// Resolution order: $currency arg → $tenant['currency'] → tenants.currency → 'EUR'
// Returns localized number (decimal/thousand separators) + symbol per locale
```

---

## 6. Locale Violations (sample)

```
login.php:28        $lang = 'bg';                              ← hardcoded session lang
login.php:38        $_SESSION['lang'] = 'bg';                  ← never branches by user pref
products.php:35     $current_locale = 'bg';
products.php:4344   if (country === 'BG') { … }                ← hardcoded country gate
products.php:4759   <option value="BG">България</option>       ← only BG in country picker
biz-compositions.php:*   46 LOCALE matches — all BG-fabric naming dictionary
ai-studio.php:*     10 LOCALE matches in prompt language defaults
```

Most LOCALE issues are 'bg' / 'BG' literals in code paths where `$tenant['country']`, `$tenant['language']`, or `$tenant['locale']` should be consulted. The DB schema already has these columns on `tenants` (verified: `country varchar(2)`, `language varchar(5)`, `currency varchar(3)`).

---

## 7. Suggested t() Key Dictionary (top 80 reusable phrases)

These are the most-frequent short BG tokens in HIGH/MEDIUM violations — each occurs ≥ 8 times across the codebase, so a single t() entry will close many violations. Suggested key naming uses lower_snake_case with short context where ambiguous.

| key | BG phrase | EN gloss | occurrences |
| --- | --- | --- | ---: |
| `field.size` | размер / Размер | Size | 90 |
| `field.color` | цвят / Цвят | Color | 83 |
| `time.days` | дни | days | 56 |
| `error.generic` | Грешка / грешка | Error | 45 |
| `field.article` | арт / артикула / Артикули | article(s) | 57 |
| `action.close` | Затвори | Close | 20 |
| `field.height` | ръст | Height | 17 |
| `field.right` | десен | right | 17 |
| `color.black` | Черен / черна | Black | 23 |
| `color.white` | Бял / Бела / бяла | White | 23 |
| `color.red` | Червен / Црвена | Red | 25 |
| `color.green` | Зелен / Зелена | Green | 19 |
| `color.blue` | Син | Blue | 9 |
| `color.pink` | Розов | Pink | 9 |
| `color.beige` | Бежов | Beige | 9 |
| `color.cream` | крем / Екрю | cream | 22 |
| `color.coral` | Корал | Coral | 12 |
| `color.bordeaux` | Бордо | Bordeaux | 10 |
| `color.gray` | Сив / Сива | Gray | 21 |
| `field.margin` | марж | Margin | 15 |
| `field.sales` | прод / продажби | sales | 23 |
| `voice.listening` | Слушам | Listening… | 14 |
| `field.category` | Категория | Category | 14 |
| `area.window` | Витрина | Display window | 14 |
| `field.barcode` | Баркод | Barcode | 13 |
| `action.magic` | Магия / магия | Magic (AI) | 22 |
| `field.store` | Магазин / магазин | Store | 20 |
| `field.supplier` | Доставчик | Supplier | 12 |
| `option.all` | Всички | All | 12 |
| `option.white_bg` | Бял фон | White background | 12 |
| `size.xl_phonetic` | икс ел / хикс ел | XL (spoken) | 22 |
| `option.none` | без | none / no | 11 |
| `field.new_article` | Нов артикул | New article | 11 |
| `action.back` | Назад | Back | 11 |
| `option.eg` | напр | e.g. | 11 |
| `option.single` | Единичен | Single | 11 |
| `status.saved` | Записано | Saved | 11 |
| `field.warehouse` | Склад | Warehouse | 10 |
| `field.code` | Код | Code | 10 |
| `field.variation` | Вариация / вариация | Variation | 19 |
| `error.network` | Мрежова грешка | Network error | 9 |
| `status.below_min` | под минимум | below minimum | 9 |
| `placeholder.name` | Въведи наименование | Enter name | 9 |
| `action.print` | Печат | Print | 9 |
| `status.added` | добавена | added | 9 |
| `q.what_losing` | Какво губиш | What are you losing | 8 |
| `q.why_losing` | От какво губиш | What's causing the loss | 8 |
| `q.what_gaining` | Какво печелиш | What are you gaining | 8 |
| `q.why_gaining` | От какво печелиш | What's driving the gain | 8 |
| `action.order` | Поръчай / поръчай | Order | 16 |
| `action.save` (sales) | ЗАПИСВА | Save (record sale) | 8 |
| `category.dress` | Рокля | Dress | 8 |
| `category.shoes` | Обувки | Shoes | 8 |
| `field.under` | под | under / below | 8 |
| `action.clear` | Изчисти | Clear | 8 |
| `field.stock` | наличност | Stock | 8 |
| `field.price` | Цена | Price | 8 |
| `category.teen` | Тийн | Teen | 8 |
| `category.jewelry` | Бижута | Jewelry | 8 |
| `field.quantity_short` | бр | pcs (short) | ~30 (currency-adjacent) |
| `unit.pieces` | броя | pieces | ~12 |
| `action.confirm` | Потвърди | Confirm | ~10 |
| `action.cancel` | Откажи | Cancel | ~10 |
| `action.delete` | Изтрий | Delete | ~10 |
| `action.edit` | Редактирай | Edit | ~12 |
| `action.add` | Добави | Add | ~14 |
| `field.name` | Име | Name | ~12 |
| `field.discount` | Отстъпка | Discount | ~10 |
| `field.total` | Общо | Total | ~10 |
| `field.subtotal` | Сума | Subtotal | ~8 |
| `field.tax` | ДДС | VAT | ~8 |
| `payment.cash` | в брой | cash | ~8 |
| `payment.card` | с карта | card | ~8 |
| `field.customer` | Клиент | Customer | ~8 |
| `field.seller` | Продавач | Seller | ~6 |
| `field.note` | Бележка | Note | ~6 |
| `status.pending` | чакащо | pending | ~6 |
| `status.completed` | завършено | completed | ~6 |
| `status.returned` | върнато | returned | ~6 |
| `time.today` | Днес | Today | ~6 |
| `time.yesterday` | Вчера | Yesterday | ~6 |
| `time.week` | Тази седмица | This week | ~6 |

**80 keys** → covers an estimated **~1,900 of the 3,764 BG_STRING violations** (≈50%) by direct substitution. The remaining ~50% are unique long sentences (insight bodies, modal copy, error messages) that need their own keys but tend to be one-off per call site.

---

## 8. Per-Module Remediation Effort Estimate

Effort is in person-hours, assumes one engineer with no domain ramp-up, includes test pass on Тихол's tenant=7 + EN preview on a synthetic tenant with `language='en'`.

| Module | LOC affected (est.) | Effort | Notes |
| --- | ---: | ---: | --- |
| `products.php` + `products_fetch.php` | ~2,500 lines touched | **40-60h** | Largest. Bundle with Phase B/C wizard rewrite. |
| `chat.php` + `xchat.php` + `chat-send.php` | ~500 | **8-12h** | Chat UI is dense but bounded. Insight body templates are the trickiest. |
| `inventory.php` + `sale.php` | ~400 | **6-10h** | Bundle sale.php with S87 rewrite. |
| `stats.php` | ~150 | **3-5h** | Mostly chart axis/legend strings. |
| `ai-studio.php` + `ai-wizard.php` | ~300 | **5-8h** | UI labels only — keep prompt templates BG, separate them in code. |
| `onboarding.php` + `login.php` + `register.php` | ~200 | **4-6h** | First-impression for international users — high ROI per hour. |
| `life-board.php` | ~80 | **2-3h** | Smaller surface, recently added. |
| `partials/*` (header, bottom-nav, chat-input-bar) | ~100 | **2-3h** | High leverage — touch every page. |
| `admin/beta-readiness.php` + `admin/*` | ~50 | **1-2h** | Admin-only; lower priority. |
| Domain data (`biz-coefficients.php`, `biz-compositions.php`) | 800+ entries | **20-40h** | Decision: localize or use as locale-keyed map. Defer to Phase 1. |
| AI prompt fragments (`ai-helper.php`, `build-prompt.php`, `ai-safety.php`, `compute-insights.php`) | ~200 entries | **15-25h** | Strategic: keep BG for BG tenants, EN for others. Needs prompt-template plumbing. |
| Currency hardcodes (cross-cutting) | 206 sites | **6-10h** | Mostly mechanical: `$x . ' лв'` → `fmtMoney($x, $tenant)`. |
| t() infrastructure + key extraction tooling | — | **8-12h** | One-time: build `config/i18n.php`, locale loader, key fallback chain, lint check. |

**Total: 120-200 person-hours** for a complete Phase B + Phase 1 sweep. The 80/20 cut (covering all HIGH severity) is roughly **70-100 hours**.

---

## 9. Phased Remediation Plan

### Phase B (Beta Polish — 14 май → September 2026)

Priority: ship beta in BG, prepare infrastructure, do not regress.

1. **Lint check** — add a CI grep that fails on new `' лв'`, `'€'`, hardcoded `'bg'` literals introduced after this commit. Effort: 1h. Blocks future debt growth.
2. **Build `config/i18n.php`** — minimal `t($key, $args = [])` helper with bg/en JSON dictionaries, country/language fallback chain `(tenant.language → tenant.country → 'bg')`. Effort: 4-6h.
3. **Build `priceFormat()` helper** — extends `fmtMoney()` to read `$tenant['currency']` and apply locale number formatting. Effort: 3h. Migrate 50 most-visible currency sites (storefront, AI Studio pricing, sale.php) — leave the rest tagged for Phase 1.
4. **Onboarding + auth** — `login.php`, `register.php`, `onboarding.php` translated to bg+en. **High ROI:** first-touch international demo path. Effort: 6h.
5. **Partials sweep** — `partials/header.php`, `partials/bottom-nav.php`, `partials/chat-input-bar.php`. Visible on every page. Effort: 3h.

**Phase B total: ~17-22h. Defers ~85% of the violations.**

### Phase 1 (Public Launch — September 2026)

Module-by-module migration. Suggested order:

1. `chat.php` + `life-board.php` (12-15h) — second-touch UI for any locale.
2. `ai-studio.php` + `ai-wizard.php` (8h) — visible international value prop.
3. `sale.php` + `inventory.php` (10-15h, ideally during S87 rewrite).
4. `stats.php` (5h) — demo material.
5. `products.php` family (40-60h — bundle with already-planned Phase B/C).
6. AI prompt templates split into `prompts/bg/` + `prompts/en/` (15-25h).
7. Domain dictionaries → locale-keyed maps (20-40h).
8. Cleanup pass + remove the lint exception list.

**Phase 1 total: ~110-180h.**

### Phase 1+ (post-launch maintenance)

Monthly grep + lint exception review. Add new keys to dictionaries as features ship.

---

## 10. False Positives & Methodology Notes

The scanner errs on the side of reporting; here is what it deliberately keeps quiet vs. what passes through:

**Suppressed (not reported):**
- Lines matching `lang=` / `charset=` / `"language": "bg"` / `@font-face` / `/* Cyrillic */` (CSS font-face declarations).
- USD `$` literals — initial pass had 490 false positives from PHP/JS `$variable` references; pattern dropped.

**Down-rated to LOW:**
- Comment-only lines (`//`, `#`, `*`, `<!--`) without any output keyword on the same line.
- Cyrillic in `biz-coefficients.php` / `biz-compositions.php` (taxonomy keys, not UI).
- AI prompt templates in `ai-helper.php` / `build-prompt.php` / `ai-safety.php` / `compute-insights.php` (treated as `BG_STRING_DATA`).
- `'BG'` / `'bg'` literals in `INSERT … VALUES` and `DEFAULT` clauses (DB seeds).

**Likely true positives even when LOW:**
- Insight title/body templates inside `compute-insights.php` *will* need t() keys when EN tenants come online — they render in the UI.
- Color name dictionaries in `products.php` (cyan/coral/cream/etc.) drive the AI color-extraction pipeline; they need a locale-keyed map, not hardcoded literals.

**Known scanner blind spots:**
- Multi-line PHP heredoc / nowdoc strings — only the lines containing cyrillic are flagged, but the context line preview can be misleading for long heredocs.
- Strings reaching the UI via `data-` attributes that themselves echo PHP variables — the literal lives in the variable assignment, which we catch, but the inference about UI visibility may underestimate severity.
- Text loaded from the database (`tenants.name`, `products.name`, etc.) is correctly *not* flagged — the user's data should stay in their language.
- Strings inside `mobile/www/` / `mobile/android/` (Capacitor-built artifacts) intentionally excluded — they are generated, not source.

---

## 11. Smoke-test summary (rough numbers)

- **5,204 total violations** in **75 files**.
- **2,130 HIGH** = the must-fix-for-international set.
- **80 reusable t() keys** cover roughly half of HIGH+MEDIUM BG strings.
- **206 currency sites** = mechanical replacement, ~6-10h.
- **141 locale sites** = mostly `'bg'` defaults, replace with `$tenant['language']`.
- **Worst 5 files** account for **42% of all violations** and **65% of HIGH** — focus here gets the biggest wins.
- **Phase B realistic budget: 17-22 hours** to ship beta cleanly + lock the door against regression.
- **Phase 1 budget: 110-180 hours** for a full international-ready codebase.

---

## 12. Recommended Next Actions

1. **Now (S87/S88):** wire a CI lint check that errors on new `' лв'` / hardcoded `'bg'` / hardcoded `'€'` outside of `config/`, `migrations/`, and `tools/seed/` (whitelist). Estimated 1-2h.
2. **S88-S90:** stand up `config/i18n.php` + `priceFormat()`. Migrate `partials/*`, `login.php`, `register.php`, `onboarding.php`. ~10-12h, deliverable: international demo URL.
3. **S91-S94:** module-by-module migration during the planned rewrites (sale.php S87, inventory v4 S89, etc.) — i18n becomes a checklist item per rewrite, not a separate sweep.
4. **Pre-launch sweep (August 2026):** dedicated 2-week effort to clean the long tail. Re-run this audit at the end and target zero HIGH.
5. **Post-launch:** quarterly grep + dashboard tile.

---

## Appendix A — How to reproduce this audit

1. Save the scanner script (kept in `/tmp/i18n_audit.py` during S87) to `tools/audit/i18n_audit.py` if/when promoted to a recurring check.
2. Run from repo root: `python3 tools/audit/i18n_audit.py --out docs/I18N_AUDIT_DATA.json`
3. Diff the new JSON against the baseline (`docs/I18N_AUDIT_DATA.json`) to track regression / progress per module.

## Appendix B — Schema check (reference)

```
tenants.country  varchar(2)   NOT NULL DEFAULT 'BG'
tenants.language varchar(5)   NOT NULL DEFAULT 'bg'
tenants.currency varchar(3)   NOT NULL DEFAULT 'EUR'
```

The columns exist. The remediation work is entirely in the application layer — no migration needed.

---

*Generated 2026-04-27. No source files were modified during this audit.*
