# 🐛 TIHOL BUGS — 05.05.2026 (вечер) — preserved за следваща сесия

**Обстановка:** Tihol не е пред компютър, диктува bugs за по-късно.

═══════════════════════════════════════════════════════════════
## P0 BUGS
═══════════════════════════════════════════════════════════════

### BUG #1 — "247 артикула" pisha vinagi
- На страница „Артикули" винаги пише **247 артикула**
- Без значение в кой tenant/profile
- Hardcoded стойност вероятно
- **Find:** `grep -nE "247|countAll|productCount" products.php`
- **Fix:** използвай live SELECT COUNT(*) FROM products WHERE tenant_id=?

### BUG #2 — Филтър не се вижда (покрит)
- Когато си в списъка на артикули
- Filter button/панел е **покрит от надпис** (вероятно z-index или position issue)
- DESIGN_KIT regression
- **Find:** `grep -nE "filter|act-chip|product-list-header" products.php`
- **Fix:** z-index hierarchy + position check

### BUG #3 — Search filter не работи в products list (главна страница)
- В главната `home.php` или `products.php` search в горната bar
- Tihol типе → нищо не филтрира
- Possibly broken event listener или AJAX endpoint
- Audit: `grep -nE "searchInput|doSearch|filterProducts" products.php`

### BUG #4 — "Преглед на продукт" — каква е тази история, върни?
- Detail/preview page на артикул
- Tihol каза „каква е тази история, върни" — значи има wrong/strange UI
- Не е ясно exactly какво вижда — нужен screenshot за следващ шеф-чат
- Possibly стара detail drawer запазена от предишна iteration

### BUG #5 — Franken-design — wizard НЕ е unified
- Tihol confirm: „трябва всичко да е в един дизайн досега все едно е Франкенщайн"
- Edit existing product → влиза в стария wizard (Step 3 layout) — fix-нато тази вечер
- Но и други pages (preview, list, filter) не са consistent
- **Това потвърждава DESIGN_KIT задача за нов шеф-чат** — Option C (full migration)

═══════════════════════════════════════════════════════════════
## NEXT SHEF-CHAT PRIORITY ORDER (сутрин 06.05)
═══════════════════════════════════════════════════════════════

1. **P0 — Edit fix verify** (commit `S95.WIZARD.EDIT_FIX` от тази вечер)
2. **P0 — 247 артикула hardcoded** (5 мин fix)
3. **P0 — Filter покрит** (z-index fix)
4. **P0 — Search filter broken** (debug listener)
5. **P0 — Preview page audit** + screenshot от Tihol
6. **P1 — DESIGN_KIT full migration** (Option C, multi-session)

═══════════════════════════════════════════════════════════════
## TIHOL EMOTIONAL CONTEXT
═══════════════════════════════════════════════════════════════

Tihol е дезочарован от franken-design. Много модули са layered след итерации без cleanup. Beta е след 9 дни. **Нов шеф-чат:** spend първи 30 мин на P0 bugs + visual unification preview screen + filter, **не вреди работа** на DESIGN_KIT full migration ако beta-blocker модули (deliveries, transfers, inventory) са на 0%.

**Препоръка:** prioritize FUNCTIONALITY > VISUAL за beta. Design polish post-beta.

═══════════════════════════════════════════════════════════════
## ADDENDUM — INCONSISTENT PRODUCT COUNTS
═══════════════════════════════════════════════════════════════

**Tihol confirm:** „На едно място пише 102 артикула, на друго 3009, на друго 257 — навсякъде различни числа."

**Root cause (от code audit):**

products.php има **минимум 6 различни SQL queries** които брояват продукти, всяка с различен WHERE:

| Ред | Query | Scope |
|---|---|---|
| 255/295 | `count($rows)` | Returned subset (8 items per page) — **очевиден бъг** |
| 509 | per-store count (с inventory store_id JOIN) | Per-store |
| 511 | tenant_id + parent_id IS NULL | Master products only (без вариации) |
| 689 | DISTINCT p.id with filter | Filtered subset |
| 702 | parent_id=? per master | Variants на 1 master |
| 931 | tenant_id + is_active=1 | All active |

**Fix предложение:**
1. **Един source of truth** — централизирана функция `getProductCount($tenant_id, $store_id, $scope)` 
2. UI label-ите ясно да describe какво се брои:
   - „247 master артикула"
   - „1230 общи варианти"
   - „3009 общо включително вариации"
3. Премахни ред 295 (показва subset count като total) — **clear bug**

**Priority:** P0 за beta. Tihol не може да управлява inventory ако counts не са consistent.

