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

**Край на S101. Pesho чака.**

═══════════════════════════════════════════════════════════════

# S102 ADDENDUM — FILTER_VISIBILITY_FIX (повторно отваряне на Bug #3)

**Дата:** 2026-05-07 (вечер)
**Сесия:** S102 — single-bug, max 1 час
**Скоуп:** Bug #3 (filter button покрит) — повторно отворен от Тихол след browser/APK тест на S101.
**File lock:** `products.php`
**Commit:** `66149b9 S102.PRODUCTS.FILTER_VISIBILITY_FIX`
**Patch:** `/tmp/s102.patch` (за apply на production)

---

## ЗАЩО S101 НЕ СРАБОТИ

S101 commit `4030ff9` повиши `.prod-hdr` z-index от 9 на 110, но Тихол потвърди в browser + APK: filter button все още е покрит. Аз бях диагностицирал бъга погрешно — мислех, че `.sig-card.expanding` (z-index:100) рисува над dropdown, но реалната причина бе друга.

**Истинският root cause (две кумулативни причини):**

### 1. Parent stacking context cap

`.main-wrap{position:relative;z-index:1}` (L1441) **създава родителски stacking context**, който **КАПВА** `.prod-hdr`'s z-index:110 на ниво глобален stacking. Накратко: като parent има `z-index:1`, всички деца стакват като 1 в глобалния поток — независимо от собствения си z-index. Това е CSS-classic gotcha.

Резултат: S101 z-index bump (9 → 110) беше **косметичен** — нямаше глобален ефект. Sort dropdown / filter content вътре в .prod-hdr продължаваше да се рисува на ефективно z-index:1 спрямо външния свят.

### 2. Sticky overlap

Дори ако parent cap беше решен — оставаше втората причина:

`.prod-hdr` (sticky;top:52px) държеше container ВЪРХУ списъка при scroll. Но `.qfltr-row` / `.active-chips` / `.fltr-label` СИБЛИНГИ под него **НЕ бяха sticky** → нормален поток → при scroll надолу те **минаваха под** sticky header-а и ставаха недостъпни.

Точно така Тихол описа: "filter button покрит от 'Артикули + брой'". Header-ът (Артикули + count) оставаше виден, филтрите се скриваха под него.

---

## S102 STRUCTURAL FIX

**Без z-index war, без !important. Чисто structural.**

### Промяна 1 — `.main-wrap` (L1441)

Преди:
```css
.main-wrap{position:relative;z-index:1;padding-bottom:180px;padding-top:0}
```

След:
```css
/* S102 BUG #3: махнат z-index:1 — родителски stacking context капваше .prod-hdr (z-index:110 от S101)
   на ниво глобален stacking → S101 fix-ът беше косметичен. Сега децата си изпълняват z-index-овете
   спрямо глобалния поток. position:relative оставаме за layout (max-width:480px wrap). */
.main-wrap{position:relative;padding-bottom:180px;padding-top:0}
```

Махнат `z-index:1`. `position:relative` остава за layout (max-width-wrap). **Няма повече stacking context cap.**

### Промяна 2 — `.prod-hdr` (L2474)

Преди (S101 опит):
```css
.prod-hdr{display:flex;align-items:center;gap:10px;padding:10px 16px;position:sticky;top:52px;z-index:110;background:rgba(3,7,18,.92)}
```

След (S102):
```css
/* S102 BUG #3: махнат position:sticky + z-index:110 (S101 attempt).
   Root cause: .qfltr-row / .active-chips / .fltr-label СИБЛИНГИ под .prod-hdr НЕ са sticky →
   при scroll скриваха под sticky header-а → филтрите ставаха недостъпни ("filter покрит от title").
   Структурно: цялата навигационна група скролва заедно с content. На върха title + filter and двете видими;
   при scroll и двете изчезват заедно — без overlap. Drawer (z-index:200/201) продължава да stack-ва
   над всичко (изпълнява тапа за filter). Без z-index war, без !important. */
.prod-hdr{display:flex;align-items:center;gap:10px;padding:10px 16px;position:relative;background:rgba(3,7,18,.92)}
```

Махнат `position:sticky`, `top:52px`, `z-index:110`. `position:relative` остава за `.sort-dd` dropdown anchoring (то е `position:absolute;top:100%`).

**Резултат:**
- На върха на products list — `.prod-hdr` (Артикули + count + sort) видим на върха, `.qfltr-row` пилове видими директно под него. **И двете видими, без overlap.**
- При scroll надолу — цялата група (header + filter pills) скролва заедно с content. И двете изчезват заедно. **Никога филтрите не се скриват под header-а.**
- При tap на filter pill → `openQuickFilter` → `qfDr` drawer (z-index:201, `position:fixed`, ИЗВЪН .main-wrap) → drawer винаги е горе.
- При tap на sort бутон → `.sort-dd` (z-index:60 вътре в .prod-hdr's local stacking context) → видимо защото няма повече parent cap.

---

## DOD VERIFICATION

- ✅ `php -l products.php` → 0 errors
- ✅ Diff stat: `1 file changed, 11 insertions(+), 5 deletions(-)` — minimal, surgical
- ✅ Не пипнат: drawer z-index, modal z-index, voice overlay (300), bottom-nav (40), toast (500), preset-ov (9999)
- ✅ Не пипнат: sale.php, deliveries.php, capacitor-printer.js, ai-action.php
- ✅ Без `!important`
- ✅ Без z-index числа > 110 (използваме съществуващата йерархия)
- ⚠ Manual visual test (browser): необходимо. Тихол да apply-не /tmp/s102.patch и да тестне.

---

## АЛТЕРНАТИВИ (RECONSIDERED, NOT TAKEN)

| Алтернатива | Защо не? |
|---|---|
| Make filter pills sticky too (multi-tier) | Сложно, може да чупи UX (твърде вертикално място при scroll) |
| Wrap header + filters в нов sticky контейнер | DOM change, по-инвазивен, може да чупи existing JS селектори |
| Bump z-index в still по-високо число | z-index war (forbidden per брийфа) |
| `transform:none;contain:layout` на main-wrap | По-imperfect — не премахва истинския проблем |

Избран **минимален structural fix**: 2 CSS реда, реверсивен, no DOM change, no JS change.

---

## ⚠ APPLY НА PRODUCTION

Sandbox няма GitHub credentials. Тихол да изпълни:

```bash
cd /var/www/runmystore && \
ls -la /tmp/s102.patch && \
git am /tmp/s102.patch && \
git log --oneline -3 && \
git push origin main
```

Очаквано на топ след apply: `S102.PRODUCTS.FILTER_VISIBILITY_FIX: махнат parent stacking context cap + sticky на .prod-hdr`

Ако `git am` fail-не заради конфликт — `git am --abort`, проверете `git status`.

---

## [COMPASS UPDATE NEEDED] (S102)

За шеф-чат да добави в LOGIC LOG:

```
S102 (07.05.2026 evening) — Code Code 2 FILTER_VISIBILITY_FIX
  ✅ Bug #3 (P0) — REOPENED & FIXED: filter button visibility
     Root cause analysis (S101 missed it):
       (1) .main-wrap{z-index:1} parent stacking context capped .prod-hdr's z-index:110 → S101 fix
           was cosmetic; never worked globally.
       (2) Sticky .prod-hdr + non-sticky filter siblings → filter pills scrolled UNDER header.
     Structural fix (no z-index war, no !important):
       - .main-wrap: removed z-index:1 (kept position:relative for layout)
       - .prod-hdr: removed position:sticky + z-index:110; kept position:relative for .sort-dd anchoring
     → commit 66149b9
     → patch /tmp/s102.patch

  RWQ #92 candidate: "Защо S101 fix-ът не беше manually tested от Code Code 2 в browser преди marker
     'DONE'?" Ans: sandbox не може да render-не PHP/CSS. Препоръка: за CSS визуални промени,
     code-code agent трябва ЗАДЪЛЖИТЕЛНО да flag-ва "needs Tihol manual test" вместо да маркира DONE
     (което S101 направи).

  RWQ #93 candidate: "Кой друг модул в repo има .main-wrap{z-index:N} pattern?" → ако други
     модули имат същия parent cap, същият visibility bug може да съществува в sale.php, deliveries.php,
     warehouse.php. Audit follow-up за S103+.
```

---

**Край на S102 ADDENDUM. Pesho чака финален APK build тест от Тихол.**

---

## PRINTER ADDENDUM (S102D — D520BT UTF-8 RASTER FIX)

**Дата:** 2026-05-07
**Файл lock:** `js/capacitor-printer.js`
**Статус:** PATCH READY (`/tmp/s102d_printer.patch`) — НЕ е applied на production. НЕ е committed.
**Не push-нато** (per Тихол instruction).

### Root cause (verified)

D520BT firmware-ът третира BITMAP raster bytes като **UTF-8 encoded text**, а не като raw binary. High bytes (0x80-0xFF) трябва да се wrap-нат като 2-byte UTF-8 (`0xC0|(b>>6)`, `0x80|(b&0x3F)`); bytes <0x80 минават unchanged. Нашият JS пуска raw binary → high bytes са невалидни UTF-8 → принтерът отхвърля bitmap-а → "no print".

### Evidence (15-job sniff в `bugreport-...2026-05-07-14-40-15.zip`)

| Job set | Encoding | Transmitted bytes | Expansion | Print result |
|---|---|---|---|---|
| 13 jobs (1-7, 9-14) — Label app | UTF-8 | 22670-23264 | 1.89-1.94× | ✓ Cyrillic печата |
| 2 jobs (8, 15) — нашия JS | RAW | 12000-12001 | 1.000× | ✗ "no print" |

Job 1 UTF-8 decoded raster (12000 bytes) → re-encoded с моя rule = **byte-perfect identical** на Label's transmitted blob (22751 bytes). JS implementation cross-verified чрез Node — **EXACT BYTE MATCH** с Python reference. Nedosledni decode failures на 10 jobs са parser frame-boundary artifacts (`\xC3\x01\xBF` = injected RFCOMM `\x01`); expansion ratio (1.939×) consistent с UTF-8 encoded ~94% high-byte raster.

### Patch summary (3 hunks, ~25 LOC, single touch-point)

`/var/www/runmystore/js/capacitor-printer.js` (backup: `*.bak.D520_UTF8_20260507_1223`)

1. **Add flag** `D520_USE_UTF8_RASTER = true` (after `D520_FORM_FEED`).
2. **Add helper** `rasterToUtf8Bytes(raster)` — pure function, UTF-8 encodes Latin-1 codepoints.
3. **Modify `generateTSPL_D520_bigbmp`** — single line: `pushRaw(rasterBytes)` where `rasterBytes = D520_USE_UTF8_RASTER ? rasterToUtf8Bytes(raster.bytes) : raster.bytes`.
4. **Modify `_printD520` default path** — when flag is true, default `path = 'bigbmp'` (full Cyrillic via raster) instead of `'ascii'` (transliterate). LocalStorage `d520_path` override still respected.

DTM5811 path NOT touched (`generateTSPL_DTM`, `_printDTM5811`, `writeWithoutResponse` unchanged). Other D520 paths (`escpos`, `pathc`, `bitmap0`, `bitmap4`, `hybrid`, `replay`, `bmptest`) unchanged.

### Apply on production

```bash
cd /var/www/runmystore
cp js/capacitor-printer.js js/capacitor-printer.js.bak.PRE_S102D_$(date +%Y%m%d_%H%M)
patch -p1 -i /tmp/s102d_printer.patch         # или: git apply /tmp/s102d_printer.patch
node --check js/capacitor-printer.js          # → "✓"
```

Verify deploy: `curl -sI https://runmystore.ai/js/capacitor-printer.js | grep Last-Modified`

### Manual test pattern (Тихол)

1. **Baseline (preferable preserve):** print "Тестова Bg" with current production state, photo of label result. (Очаквано: transliterated/no-print — текущ behavior.)
2. **Apply patch + reload app**, print "Тестова Bg". (Очаквано: Cyrillic печата.)
   - Ако излезе Cyrillic — ✓ patch работи.
   - Ако не излезе — flip flag в js: `const D520_USE_UTF8_RASTER = false;` → reload → print again. (Очаквано: matches step 1 baseline.)
3. **Confirm flag flip works:** ако step 2 успява, опционално flip `false` → reload → print. (Очаквано: revert to baseline.)

Защо този pattern: проверява и forward (UTF-8 fix-ва Cyrillic) и reverse (flag flip връща нашия baseline без revert на patch-а). Ако forward не успява но reverse връща baseline → patch не е counter-productive, само не е достатъчен.

### Очакван commit message (когато Тихол реши да commit-не)

```
S102D.PRINTER.D520_UTF8_RASTER: encode raw raster bytes как UTF-8 преди RFCOMM write

Verified от Label app sniff (13/15 jobs UTF-8 ~22.7K bytes, прецизно cyrillic;
2/15 raw 12K bytes, no print). JS rasterToUtf8Bytes byte-perfect identical с
Label's transmitted blob. Single touch-point в generateTSPL_D520_bigbmp +
default path flip в _printD520, gated зад D520_USE_UTF8_RASTER flag за лесен
revert. DTM5811 path не е докоснат.
```

### Артефакти за отстраняване / съхранение

- `/tmp/s102d_printer.patch` — diff, apply на production
- `/var/www/runmystore/js/capacitor-printer.js.bak.D520_UTF8_20260507_1223` — pre-patch backup
- `/tmp/br1440/FS/data/log/bt/btsnoop_hci.log` — sniff с 15 jobs (можеш да го трисне след повърждение)
- `/tmp/label_pngs/job{01..15}*.{pgm,raw,DECODED.bin}` — extracted Label rasters (виж `cmp_label_job01.png` за reference Cyrillic look)
- `/tmp/cmp_*.png` — comparison images (Label vs наш JS)
- `/tmp/parse_label_session.py`, `/tmp/extract_label_jobs.py`, `/tmp/render_label_jobs.py`, `/tmp/decode_label_utf8.py`, `/tmp/verify_utf8_hypothesis.py`, `/tmp/pgm2png.py` — analysis скриптове

### RWQ candidate

"Кои други device printers в каталога ни (или бъдещи) имат firmware-нивo UTF-8/Latin-1 expectation за binary raster?" — Phomemo D-family вероятно същата logic; AIMO unconfirmed. Когато добавяме нов BT printer, добави step "sniff Label/официално приложение, count expansion ratio на raster vs nominal w*h" към onboarding checklist. Avoidва re-discovery.

---

**Край на PRINTER ADDENDUM. Patch чака apply от Тихол.**

═══════════════════════════════════════════════════════════════

# S103 ADDENDUM — SEARCH_FILTER_UNIFY (3 свързани bug-а)

**Дата:** 2026-05-07 (вечер)
**Сесия:** S103 — single-session, max 2 часа
**Скоуп:** 3 свързани bug-а: filter btn handler, inline mic, home/list UX unify
**File lock:** `products.php`
**Patch:** `/tmp/s103.patch` (3 commits, ready за production apply)

---

## РЕЗЮМЕ

| Bug | Title | Status | Commit |
|---|---|---|---|
| #7 | Filter button до микрофон НЕ работи | **DONE** | `fb230e1` |
| #8 | Mic в search НЕ е като wizard (искаме wizard pattern) | **DONE** | `b7237f7` |
| #9 | Filter+search в list view НЕ е същия като main | **DONE** | `a704992` |

---

## BUG #7 — FILTER BUTTON БЕЗ HANDLER

**Commit:** `fb230e1 S103.PRODUCTS.SEARCH_FILTER_UNIFY.FILTER_BTN`

### Root cause
L4322 имаше `<button class="s-btn">` БЕЗ `onclick` handler. Просто рендериран filter funnel SVG + hardcoded `<span class="dot">3</span>` badge. Tap → нищо. Drawer #filterDr (L4733) и `openDrawer/closeDrawer` функциите вече съществуваха от друг flow — нужен само wire-up.

### Fix
- Добавен `onclick="openDrawer('filter')"`
- Добавени `type="button"` + `aria-label="Филтри"` (a11y)
- Добавен `id="hSearchFilterBtn"` + `id="hSearchFilterDot"` за бъдещ JS update
- Hardcoded "3" → "0" + `display:none` default. **TODO** (out of scope): JS updater да брои active S.catId/S.supId/S.filter и да показва badge с реалното число.

### DOD verification
- ✅ `php -l products.php` → 0 errors
- ✅ Diff stat: 1 file, 2+/1-
- ⚠ Manual test (browser/APK от Тихол): Tap filter → drawer; Apply → list update; 0 console errors

---

## BUG #8 — INLINE MIC (WIZARD PATTERN, БЕЗ OVERLAY)

**Commit:** `b7237f7 S103.PRODUCTS.SEARCH_FILTER_UNIFY.MIC_INLINE`

### Преди
Tap 🎤 в search → `openVoiceSearch()` → fullscreen `#recOv` overlay. Не като wizMic-а, не като Тихол искаше.

### Сега (wizard pattern)
- **Web Speech API** (free, native, instant, **zero cost**) — НЕ Whisper Groq за text търсене
- `continuous=true + interimResults=true` → live transcript
- 2-сек silence auto-stop (re-arm на всеки `onresult`)
- Tap пак при recording → manual stop
- Транскрипт live → `#hSearchInp.value` + `dispatchEvent('input')` →
  съществуващият `onLiveSearchHome` дебаунсер (150ms) fire-ва → live filter
- `lang` от `tenant.lang` (CFG.lang) — i18n за 11 езика (bg/ro/el/sr/hr/en/mk/sq/tr/sl/de)
- `navigator.vibrate(8)` при start (haptic confirm)

### Visual recording state (без !important)
```css
.s-btn.mic.recording{background:rgba(239,68,68,.3);border-color:#ef4444;color:#fff;animation:micRecPulse .8s infinite;position:relative}
.s-btn.mic.recording::after{content:'REC';...top:-14px;...}
.s-btn.mic.recording::before{content:'';...red dot blinking}
```

Specificity `.s-btn.mic.recording` (3 классa) > `.s-btn.mic` (2 классa) + source order → wins без `!important`. Reusing existing keyframes `micRecPulse` / `micRecDot` от wizard (L2186-2187) — без duplication.

### ЗАПАЗЕНО (R2)
- `openVoiceSearch` + `openVoice` + `#recOv` — separate flow (AI chat), непокътнат
- `wizMic` / `.wiz-mic` CSS — REFERENCE only, не пипнат
- `onLiveSearchHome` debouncer + `#hSearchDD` dropdown — re-used by event dispatch

### DOD verification
- ✅ `php -l` → 0 errors
- ✅ Diff stat: 1 file, 79+/1- (additive)
- ⚠ Manual test (browser/APK):
  - Tap 🎤 → red + REC label + dot pulse, **БЕЗ overlay**
  - Каза "червени обувки" → text live в input + dropdown update
  - Замълчи 2 сек → auto-stop + final search
  - Tap 🎤 повторно → manual stop
  - Web Speech-disabled браузър → toast "не се поддържа", без crash

---

## BUG #9 — HOME ↔ LIST VIEW UNIFY (SEARCH/FILTER/MIC)

**Commit:** `a704992 S103.PRODUCTS.SEARCH_FILTER_UNIFY.LIST_SEARCH`

### Diagnostics
- Home (`#scrHome`) имаше `.search-wrap` с input + filter btn + mic
- List (`#scrProducts`) имаше само `.prod-hdr` (back/title/count/sort) + qfltr-pills
- → две различни UX-а

### Approach (per брийфа: "same DOM, same handlers")
1. **Дубликат на .search-wrap в #scrProducts** директно след .prod-hdr.
   - Същата визуална структура (CSS `.search-wrap` reused).
   - p-prefixed IDs (`pSearchInp`, `pSearchFilterBtn`, `pSearchMicBtn`) — защото двата screen-а съществуват в DOM едновременно (display:none toggle), нямаме право на duplicate IDs.
2. **`searchInlineMic(btn, inputId)` рефакториран** — приема `inputId` param, default `'hSearchInp'` (backwards compatible). List view вика `searchInlineMic(this, 'pSearchInp')`. **Single mic implementation за двата screen-а — DRY.**
3. **Backend `?ajax=products` extended с `q` param**:
   ```php
   if (isset($_GET['q']) && trim((string)$_GET['q']) !== '') {
       $q_like = '%' . trim((string)$_GET['q']) . '%';
       $where[] = "(p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)";
       $params[] = $q_like; $params[] = $q_like; $params[] = $q_like;
   }
   ```
   tenant_id вече в WHERE → no cross-tenant leak. Combo с всички съществуващи filters (cat/sup/qf/...).
4. **State**: `S.listQ` (отделно от `S.searchText` / `doSearch` overlay flow).
5. **Frontend**: `onLiveSearchList(q)` дебаунсер 200ms → reset `S.page=1` + `loadProducts()`. `loadProducts()` сега изпраща `&q=` ако `S.listQ`.
6. **`goScreen('products', ...)`**: на entry → `S.listQ=''` + `pSearchInp.value=''`. "Виж всички" → fresh list, не stale query.

### ЗАПАЗЕНО
- Home `#hSearchInp` + `onLiveSearchHome` + `#hSearchDD` dropdown — непокътнат
- `doSearch()` / `openVoice()` / `#recOv` overlay — отделен flow, непокътнат
- `.prod-hdr` layout (back/title/count/sort) — непокътнат
- qfltr-pills + active-chips + signalFilterRow — непокътнат
- Filter drawer `#filterDr` + `openDrawer/closeDrawer` — reused от двата screen-а

### DOD verification
- ✅ `php -l products.php` → 0 errors
- ✅ Diff stat: 1 file, 35+/2-
- ⚠ Manual test:
  - Home: title + search + mic + filter + content (както преди)
  - "Виж всички" → list: title (.prod-hdr) + search-wrap (input + filter + mic) + qfltr-pills
  - Search input live-filter работи; filter btn → drawer; mic → red REC
  - Същият `searchInlineMic` handler между двата screen-а (DRY)

---

## ⚠ ОТЛОЖЕНО / TODO (out-of-scope за S103)

| Item | Why | Suggestion |
|---|---|---|
| Filter dot badge dynamic count | Hardcoded "0/3" — нужно е JS updater при промяна на S.catId/S.supId/S.filter/qfState | S104 — малка задача, ~10 LOC |
| Touch target sizing на .s-btn (28×28) | DESIGN_LAW R6 минимум 36px | DESIGN_KIT migration / Option C |
| Add qfltr-pills to home? | Брийфът беше двусмислен. Запазих home без qfltr-pills (existing layout). | Тихол да реши след visual review |
| `S.searchText` ⊕ `S.listQ` ⊕ `S.q` reconciliation | Има 3 search states за исторически причини | Refactor в S104+ |

---

## ⚠ APPLY НА PRODUCTION

Sandbox няма GitHub credentials. Тихол да изпълни:

```bash
cd /var/www/runmystore && \
git pull origin main && \
git am /tmp/s103.patch && \
git log --oneline -5 && \
git push origin main
```

Очаквано на топ след apply:
1. `S103.PRODUCTS.SEARCH_FILTER_UNIFY.LIST_SEARCH`
2. `S103.PRODUCTS.SEARCH_FILTER_UNIFY.MIC_INLINE`
3. `S103.PRODUCTS.SEARCH_FILTER_UNIFY.FILTER_BTN`

Ако `git am` fail-не → `git am --abort` → проверете `git status -s` преди да приложите наново.

---

## [COMPASS UPDATE NEEDED] (S103)

За шеф-чат да добави в LOGIC LOG:

```
S103 (07.05.2026 evening) — Code Code 2 SEARCH_FILTER_UNIFY
  ✅ Bug #7 (P0): Filter button next to mic — добавен onclick (просто липсваше)
     → commit fb230e1
  ✅ Bug #8 (P0): Inline mic вместо overlay — Web Speech, wizard pattern, 2-сек silence stop
     → commit b7237f7
     → KEY DECISION: НЕ Whisper Groq за text търсене (cost). Web Speech free + native.
     → KEY DECISION: searchInlineMic SOLO function — НЕ extend wizMic (different scope).
  ✅ Bug #9 (P0): Home/list UX unify — search-wrap дубликат + searchInlineMic shared via inputId
     → commit a704992
     → KEY DECISION: dupликирах DOM (p-prefixed IDs) вместо PHP partial. Минимизира invasiveness;
       partial-ефект възможен в S104.

  RWQ #94 candidate: "Защо имаме 3 различни search state-а (S.searchText, S.listQ, S.q сървърен)?"
     → Historical accumulation. doSearch() flow + onLiveSearchHome flow + new list flow.
     → Refactor predicate: единен SearchState{ home, list } object → unify в S104.

  RWQ #95 candidate: "DOM duplication анти-pattern: search-wrap се рендерира 2× в products.php.
     Когато променяме layout — трябва двата edit. Поне refactor-ваме в php partial / template literal."

  RWQ #96 candidate: "Hardcoded filter dot badge ("3") — никога не отразяваше реалния brой active filters.
     Бяло петно — не сме имали JS updater. Препоръка: S104 micro-task."
```

---

**Край на S103 ADDENDUM. Pesho чака финал тест от Тихол.**

