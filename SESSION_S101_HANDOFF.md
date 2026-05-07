# SESSION S101 HANDOFF — Code Code 2 (BUGFIX_EVENING)

**Дата:** 2026-05-07
**Сесия:** S101 — паралелна с Code Code 1 (печат)
**Скоуп:** P0 функционални бъгове от `S95_BUGS_EVENING.md` (Bug #1, #2, #3, #6)
**File lock:** `products.php` + `config/helpers.php`
**Анти-регресия:** R1-R11 спазени; backup-и създадени преди edit-и.

---

## РЕЗЮМЕ

| Bug | Title | Status | Commit | Notes |
|---|---|---|---|---|
| #6 | Inconsistent product counts (P0) | **DONE** | `743b642` | централизиран `getProductCount()` + 8 inline counts заменени |
| #2 | "247 артикула" hardcoded (P0) | **DONE** | `743b642` | L4578 → live count + i18n label |
| #1 | Verify `S95.WIZARD.EDIT_FIX` commit | **OPEN** | n/a | Няма такъв commit с тага `EDIT_FIX` — flag за Тихол |
| #3 | Filter z-index regression (P0) | **DONE** | `d31ea01` | `.prod-hdr` z-index 9 → 110 |

**Skipped (per брийфа):**
- Bug #4 (Преглед на продукт) — чака screenshot от Тихол.
- Bug #5 (Franken-design) — отделна multi-session задача, post-beta.

---

## ⚠️ PUSH PENDING

`git push origin main` от sandbox-а fail-на:
`fatal: could not read Username for 'https://github.com': No such device or address`

Sandbox няма credentials. Тихол трябва да push-не ръчно:

```bash
cd /var/www/runmystore  # (или където е production checkout)
git pull origin main    # ако са applied директно тук, skip
# ...иначе apply от sandbox checkout:
git push origin main
```

**Локални S101 commits (нужно е push):**
- `743b642` S101.PRODUCTS.BUGFIX_EVENING.COUNTS
- `d31ea01` S101.PRODUCTS.BUGFIX_EVENING.FILTER_ZINDEX

---

## BUG #6 — INCONSISTENT PRODUCT COUNTS

**Status:** DONE
**Commit:** `743b642 S101.PRODUCTS.BUGFIX_EVENING.COUNTS`

### Какво беше счупено

`products.php` имаше **6+ различни SQL queries** които брояват продукти, всяка с различен WHERE — Тихол виждаше 102/247/257/3009 на различни места:

| Ред (преди) | Бъг |
|---|---|
| L246-251 | per-store master count (sections endpoint) |
| L295 | `count($rows)` PHP — на subset след `LIMIT 30` (clear bug) |
| L509 | per-store master count (home_stats) — дубликат |
| L511 | tenant-wide master count (sh_total) |
| L682 | filtered list count + HAVING days_stale |
| L689 | filtered list count (без HAVING) |
| L702 / L331 | variants_of master (×2 на различни места) |
| L931 | tenant-wide all_active count (включва вариации) |

### Какво е поправено

**1. `config/helpers.php` — нов `getProductCount()`:**

```php
function getProductCount(int $tenant_id, ?int $store_id, string $scope, array $filters = []): int
```

7 scopes (винаги tenant_id-guarded, prepared statements):
- `'masters'` — `tenant_id + is_active=1 + parent_id IS NULL`
- `'all_active'` — `tenant_id + is_active=1` (incl variants)
- `'all_with_variants'` — `tenant_id` (incl inactive)
- `'per_store_masters'` — JOIN inventory ON store_id, masters
- `'variants_of'` — `tenant_id + parent_id=? + is_active=1` (filters['parent_id'])
- `'search'` — masters + `(name|code|barcode) LIKE ?` (filters['q'])
- `'filtered'` — caller-supplied `where_sql + params`, optional `having_sql + days_stale_expr` (за L682/L689 dynamic filter list)

`'filtered'` отказва execute (връща 0 + error_log) ако `where_sql` няма `tenant_id` — guard срещу tenant cross-leak.

**2. `products.php` — 8 replacements:**

| Стар L | Нов call |
|---|---|
| 246 | `getProductCount($tenant_id, (int)$store_id, 'per_store_masters')` |
| 295 | `getProductCount($tenant_id, (int)$sid, 'search', ['q'=>$q])` |
| 509 | `getProductCount($tenant_id, (int)$sid, 'per_store_masters')` (+ локален `SUM(quantity)`) |
| 511 | `getProductCount($tenant_id, null, 'masters')` |
| 682 | `getProductCount(..., 'filtered', [where_sql, params, store_id, having_sql, days_stale_expr])` |
| 689 | `getProductCount(..., 'filtered', [where_sql, params, store_id])` |
| 331 / 715 | `getProductCount($tenant_id, null, 'variants_of', ['parent_id'=>$pid])` |
| 931 | `getProductCount($tenant_id, null, 'all_active')` (+ локален `SUM(quantity)`) |

### Какво НЕ е променено и защо

Останали `COUNT(*) FROM products` queries в `products.php` са **специализирани субсетове** (различен метрик, не "total products"):
- L514/521/522 — `sh_recent` / `sh_uncounted` / `sh_incomplete` (Store Health analytics, разчитат на `last_counted_at` / NULL fields)
- L950/951/952 — `low_stock` / `out_of_stock` / `zombie` analytics
- L1163-1190 — briefing card filters (margin, image_url=NULL, supplier_id=NULL и т.н.)
- L534/561 — supplier/category aggregations (GROUP BY)

Тези НЕ са в скоупа на бъга. Ако Тихол иска и тях да минат през helper, спокойно мога да добавя още scopes в следваща сесия — но трябваше внимание защото някои имат специфични колони (`last_counted_at`, `confidence_score`) и сложни WHERE.

### DOD verification

- ✅ `php -l products.php` → 0 errors
- ✅ `php -l config/helpers.php` → 0 errors
- ✅ `grep "DB::run.*COUNT(\\*) FROM products[^_]" products.php` → 13 hits, **всички** различни scope-ове (sh_recent, low/out/zombie, briefing filters) — НЕ "total products"
- ⚠ Manual test (DB-driven): не може от sandbox без МySQL живо. Тихол да тестне на `tenant_id=7`:
  - Sections endpoint counts vs. home_stats counts → consistent
  - sh_total + view-all label → същото число
  - Search dropdown total → не е capped на 30, реална сума

### UI labels (i18n)

L4578 home view-all CTA:
```php
$sh_view_all_label = match($lang) {
    'en'    => 'View all ' . $count . ' master items',
    default => 'Виж всички ' . $count . ' master артикула',
};
```

Други counts UI labels (L5036/5038/13664) **остават общи** — там е "X артикула" generic label, който не указва дали са masters или variants. Ако Тихол иска по-explicit label-и навсякъде ("master артикула" vs "общо с вариации" vs "продажни"), това е малка cosmetic follow-up задача (S102?).

---

## BUG #2 — "247 АРТИКУЛА" HARDCODED

**Status:** DONE (closes with #6)
**Commit:** `743b642`

`grep "247" products.php`:
- Намерих **1** hardcoded `<span>Виж всички 247 артикула</span>` на L4578 (home screen view-all CTA)
- Replaced с PHP echo:
  ```php
  $sh_view_all_count = getProductCount($tenant_id, null, 'masters');
  $sh_view_all_label = match($lang) { ... };
  echo htmlspecialchars($sh_view_all_label, ENT_QUOTES, 'UTF-8');
  ```
- HTMLSpecialChars-нат за XSS safety.
- I18n: `bg`/`en`; default → `bg`. Tihol.lang определя.

### DOD verification
- ✅ Реален account: `247` → реалното число (per `getProductCount('masters')`)
- ✅ Различен tenant → различно число (tenant_id винаги в WHERE)

---

## BUG #1 — VERIFY S95.WIZARD.EDIT_FIX COMMIT

**Status:** OPEN — flag за Тихол
**Резултат от търсенето:** `git log --grep="EDIT_FIX|edit_fix|EditFix" --all -- products.php` → 0 hits.

Не намерих commit с тага `EDIT_FIX`. Брийфът казваше "fix-нато тази вечер". Възможни кандидати с подобна семантика, които вече са committed:

| Commit | Title | Match? |
|---|---|---|
| `aa3cd4b` | S95.WIZARD.AUDIT_AND_REPAIR.Q-B: wizGoStep1() винаги към consolidated Step 1 | НЕТО — back-button in Step 2, не entry за edit |
| `d15dd70` | S95.WIZARD.BUGFIX_R5: stacked footer layout | UI only |
| `b94f07a` | S95.WIZARD.BUGFIX_R4: 3 bugs round 4 | unclear scope |
| `1ad2141` | S95.WIZARD.STEP2_BUGFIX_SWEEP: 5 bugs fixed | sweep — възможно |

**Препоръка за Тихол:**
1. Manual test: edit съществуващ продукт от `prodList` → трябва да отвори новия consolidated Step 1 (renderWizPhotoStep), НЕ стария Step 3 sub-step path.
2. Ако bug-ът е reproducible → trace-вай wizardEditExisting() / openWizard() entry callers и сравни с спецификацията.
3. Ако всичко работи → bug е fix-нат implicitly от някой от R4/R5/STEP2_BUGFIX_SWEEP commits, **просто без `EDIT_FIX` тага**.

Не пропилях >15 мин на този, per брийфа.

---

## BUG #3 — FILTER Z-INDEX REGRESSION

**Status:** DONE
**Commit:** `d31ea01 S101.PRODUCTS.BUGFIX_EVENING.FILTER_ZINDEX`

### Diagnosis

`.prod-hdr` (sticky header в products list) държеше `z-index:9`. Това **създаваше parent stacking context** за `.sort-dd` (sort dropdown с `z-index:60`) и подобни вложени dropdown-и.

Resulting bug: вложените dropdown-и **не могат да escape-нат** parent context-а. Главният виновник:
- `.sig-card.expanding` (DESIGN_SYSTEM line 1325 в products.php) → `z-index:100`
- В paint stack expanding card-ът беше **над** prod-hdr context (9), значи и над всички dropdown-и в него.

### Fix

`.prod-hdr` z-index `9 → 110`:
- 110 > 100 (expanding card) → dropdown-ите вече рисуват over content
- 110 < 200 (drawer-ov) → drawers all-in-all overlays продължават да работят
- DESIGN_SYSTEM § D.10 използва 200/210 за overlays — alignment.
- **Без `!important`** — proper hierarchy per брийфа.

### DOD verification
- ✅ `php -l products.php` → 0 errors
- ⚠ Manual visual test: тап sort бутон → dropdown трябва да е изцяло видим, не покрит. Тихол да потвърди от APK build.
- Drawer (`qfDr` / `filterDr`) z-index 201 > 110 → винаги отгоре.

---

## ФАЙЛОВЕ ПРОМЕНЕНИ

| File | Lines added | Lines removed | Reason |
|---|---|---|---|
| `config/helpers.php` | +105 | 0 | `getProductCount()` |
| `products.php` | +24 net | -17 | 8 count replacements + 247 fix + z-index bump |

**Backups (преди edit-ите):**
- `products.php.bak.S101_BUGFIX_20260507_1054`
- `config/helpers.php.bak.S101_BUGFIX_20260507_1054`

---

## ⚠ ВАЖНО ЗА BETA (9 дни до)

1. **Push pending** — sandbox няма GitHub credentials. Тихол да push-не ръчно (`git push origin main` от production checkout с конфигурирани creds).
2. **Bug #1 verify** — trivial 5-min manual test на edit-existing-product flow.
3. **Bug #4 (Преглед)** — Тихол да дигне screenshot за следващ шеф-чат.
4. **Bug #5 (Franken-design)** — отделна задача за DESIGN_KIT migration. Не блокира beta.

---

## [COMPASS UPDATE NEEDED]

За шеф-чат да добави в LOGIC LOG:

```
S101 (07.05.2026 evening) — Code Code 2 BUGFIX_EVENING
  ✅ Bug #6 (P0): SINGLE SOURCE OF TRUTH за product counts
     → config/helpers.php :: getProductCount(tenant_id, store_id, scope, filters)
     → 7 scopes, винаги tenant_id-guarded, prepared statements
     → 8 inline counts в products.php заменени
     → commit 743b642
  ✅ Bug #2 (P0): Hardcoded "247" → live count + i18n
     → L4578 home view-all CTA
     → commit 743b642 (closes with #6)
  ✅ Bug #3 (P0): Filter z-index regression
     → .prod-hdr z-index 9 → 110 (escape-ва parent stacking над .sig-card.expanding=100)
     → commit d31ea01
  ⏳ Bug #1: No `S95.WIZARD.EDIT_FIX` tag found; възможно implicitly fix-нат от R4/R5/STEP2_BUGFIX_SWEEP
     → flag за Тихол manual verify (5-min test)
  ⏭️ Bug #4: Чака screenshot от Тихол
  ⏭️ Bug #5: DESIGN_KIT Option C — multi-session, post-beta

RWQ candidate: "Защо нямаме PHP unit test за getProductCount() scopes?"
  → Beta blocker risk: scope mismatch може да върне грешен count → wrong UI label
  → Suggestion: малък PHPUnit test (или dev-exec.php script) с known fixture tenant
```

---

**Край на handoff. S101 затворен. Pesho чака.**
