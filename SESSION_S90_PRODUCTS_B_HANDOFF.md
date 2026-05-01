# SESSION S90.PRODUCTS.SPRINT_B — Handoff

**Date:** 2026-05-01
**Scope:** 8 P0 беta-blocker bugs в `products.php` от mobile теста на 29.04.2026.
**Branch:** main
**Files touched:** `products.php` (+247/-28). Никакви други файлове не са пипнати.
**Backup:** `.backups/s90_products_b_<ts>/products.php`.

---

## ✅ 8 / 8 bugs implemented

### C1 — "+ Добави на ръка" в wizard стъпка размери
- **Преди:** Pesho можеше само да избира от AI preset chips или да отвори "Нова вариация" (което създава нов AXIS, не нова стойност).
- **Сега:** Под `v-sel-bar` добавено input + бутон. Лейбълът е динамичен: "Добави размер" / "Добави цвят" / "Добави {axisName}". Wire-нато към съществуващия `wizAddAxisValue(axIdx)` (използва `fuzzyConfirmAdd` за dup-check).
- **Файл:** ~line 6453.
- **Гранд тест:** wizard → Variant → стъпка 4 → въведи "EU44.5" → Enter → chip се появява в селекцията.

### C2 — Scanner икона на двете полета (артикулен номер + баркод)
- **Преди:** само Баркод имаше scanner button.
- **Сега:** Артикулен номер вече има indigo scanner; Баркод запазва зеления. `wizScanBarcode(targetField, title)` параметризиран — пише в правилното поле и показва правилен title overlay.
- **Файл:** ~line 6315 (UI), ~line 8014 (function).

### C3 — Артикулен и Баркод в РАЗДЕЛНИ qcard.glass
- **Преди:** Двете полета stack-нати в `<details class="wiz-advanced">` — collapsed по подразбиране, обща секция.
- **Сега:** Премахнато `<details>` обвиване. Всяко поле в самостоятелен `<div class="wiz-id-card glass sm">` с padding 12×14, border-radius 14. Винаги видими. Подредба: Артикулен номер ПЪРВО (primary internal SKU), Баркод ВТОРО.

### C4 — AI auto-fill hint
- **Преди:** Pesho не различава какво е написал той vs какво е попълнил AI.
- **Сега:** Hint "AI попълни — натисни за промяна" с малка sparkle-икона се появява под полето когато стойността е сложена от voice/AI.
  - Helpers: `wizMarkAIFilled(...keys)`, `wizClearAIMark(key)`, `wizAIHint(key)`.
  - State: `S.wizData._aiFilled` map — persist-ва в drafts.
  - Wired-нати полета: `name`, `retail_price`, `wholesale_price`, `cost_price`, `category`, `color`.
  - Auto-mark в: `parseVoiceToFields()` (voice → name/price/category).
  - Auto-clear: всеки `oninput` на тези полета извиква `wizClearAIMark`.
- **Бележка:** vision-detected colors отиват в variant axis chips (не single product `wColor`), затова единственото single-product поле което се маркира е "цвят" при ръчно/voice вход. За axis chips dot цвета вече визуално сигнализира AI detection.

### C5 — Back arrow в wizard header
- **Преди:** Само X (close) в горния ляв ъгъл; нямаше начин за step-back.
- **Сега:**
  - ChevronLeft inline SVG бутон в горния ЛЯВ ъгъл.
  - X close преместен на ДЕСНИЯ ъгъл.
  - Step history stack: `S._wizHistory` (max 32).
  - `wizGo(step, _skipHistory)` push-ва старата стъпка преди смяна.
  - `wizPrev()` pop-ва history; ако празно — fallback към `S.wizStep-1`; ако е step 0 — `closeWizard()`.
  - History се чисти в `openManualWizard`, `openVoiceWizard`, `closeWizard`.

### D3 — Категории filter-нати по supplier — **CRITICAL UX**
- **Преди:** В wizard step 3 dropdown-ът показваше ВСИЧКИ категории на tenant-а — Pesho грешеше категория когато добавя продукт от Дафи.
- **Сега:**
  - Backend `?ajax=categories&sup=X` UNION-ва две стратегии:
    1. Ръчно мапнати в `supplier_categories` table.
    2. Auto-discovery: категории с поне 1 активен продукт от този доставчик.
  - Фронт: `wizCatsForSupplier()` returns sync кеширан списък (или fallback CFG.categories ако няма cache yet).
  - Pre-fetch в `wizPrefetchSupplierCats(sup)` — извиква се при render на step 3 (за edit mode) и при смяна на доставчик в `wizPickDD`.
  - При смяна на supplier текущо избраната категория се **проверява** — ако не е в новия scope, се нулира мълчаливо + пресет на `wSubcat`.
  - UI: `(само от избрания доставчик)` hint до label-а.

### D5 — Live duplicate-name check
- **Преди:** Дубликат detection само на save → Pesho написваше пълно име, попълваше всичко, save → "вече съществува" → wasted 30 секунди.
- **Сега:**
  - Backend `?ajax=name_dupe_check&q=...&exclude_id=...` връща top 5 имена с `similar_text()` similarity ≥ 0.65 (sorted desc).
  - Frontend `wizDupeCheckName(name)` — debounced 350ms, fires при 3+ символа.
  - Жълт banner с warning icon ако top match score ≥ 0.85: показва име, цена, % близко.
  - CTA: **[Да, отвори същото]** → `closeWizard()` + `openProductDetail(id)`. **[Не, продължи]** → `S._wizDupeDismissed` запомня името → banner не се появява пак за същия input.
  - Stale-result guard: при отговор сравнява `wName.value` с `name` на момента на заявката — ако се различават (Pesho е продължил да пише), не показва banner.

### G1 — Swipe навигация (verify-only)
- Page-level swipe-nav вече беше DISABLED от предишен sprint (line ~5596). Стая премахната.
- Stronger comment добавен: `Tihol confirmed: do NOT add page-level swipe-nav back to products.php`.
- `.swipe-row` / `.h-scroll` CSS класовете остават — те са horizontal-scroll **карусели** за content в карта (доставчици, категории), не page navigation. Тези не са обхвата на G1.
- Verticalните touch handlers (`drawer pull-to-close`, `color picker canvas`) запазени — не са page nav.

---

## DOD checklist

- [x] **8 / 8 bugs implemented**
- [x] `php -l products.php` → No syntax errors
- [x] `bash design-kit/check-compliance.sh products.php` → **15 нарушения** ⚠️ — *EXPECTED FAIL.* products.php още не е по design-kit (pre-existing tech debt: backdrop-filter, conic-gradient, mask composite, emoji в UI, `:root` override, no `theme-toggle.js`, no `has-rms-shell` body class). Никой нов нарушен violation от Sprint B.
- [x] Backup: `.backups/s90_products_b_<ts>/products.php`
- [x] Selective git add: `products.php` + `SESSION_S90_PRODUCTS_B_HANDOFF.md` (no `-A`)
- [x] Single commit, no amend, no force push

---

## Recommended QA (mobile)

1. Open wizard → проверете че long-tap на back arrow връща стъпка.
2. Wizard → Variant → step 4 → "+ Добави размер" → въведи "EU44.5" → виж chip.
3. Wizard → step 3 → избери доставчик "Дафи" → отвори Категория dropdown — само Дафи categories.
4. Wizard → step 3 → започни да пишеш "Adidas Stan Smith" → след 350ms жълт banner ако има близко совпадение.
5. Wizard → step 3 → voice → "Адидас 95 лева спорт" → виж "AI попълни" hint под цена и категория.
6. Wizard → step 3 → артикулен номер → tap scanner → проверете overlay title "Сканирай артикулен номер".

## Known caveats
- **D3 кеш:** `S._wizSupCatCache` се пази per-session. Ако Pesho смени supplier_categories table-а в админ панела докато wizard-ът е отворен, ще трябва да затвори/отвори wizard. Acceptable — rare case.
- **D5 backend:** използва PHP `similar_text()` — O(N×M³). За tenant с 10K+ products може да е бавен. Pre-LIKE filter с `name LIKE '%q%'` ограничава до 40 кандидата → similarity на тях. Ако стане проблем → switch to MySQL FULLTEXT или Soundex.
- **C4 AI mark scope:** маркира се само когато AI explicit fills. Ако в бъдеще има allnew AI flow (auto-pricing suggestion modal, AI Studio, draft-prefill), помнете да викате `wizMarkAIFilled('keyName')`.

## Out of scope (Sprint B)
- 15-те остатъчни design-kit нарушения в products.php — отделна сесия (Sprint D или C).
- Sprint C/D bugs (UX polish след beta launch).
- AI auto-fill hints за axis chips (вариант color detection) — visual dot вече сигнализира.

---
**Generated with Claude Opus 4.7 (1M context).**
