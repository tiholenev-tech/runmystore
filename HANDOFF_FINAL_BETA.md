# HANDOFF FINAL BETA — RunMyStore.AI

**Дата:** 08.05.2026
**Beta launch:** 14-15.05.2026 (≈7 дни)
**Tenant:** ENI (5 магазина, BG)
**Test tenant:** `tenant_id=7`
**Author:** Шеф-чат (Claude) → Claude Code

---

## 1. Status към 08.05.2026

### Завършено в тази сесия (Шеф-чат)

| Артефакт | Файл | Статус |
|---|---|---|
| **P12 — Matrix overlay** | `mockups/P12_matrix.html` | ✅ APPROVED |
| **P13 — Добави артикул (accordion)** | `mockups/P13_bulk_entry.html` | ✅ APPROVED |
| **PRODUCTS_BULK_ENTRY_LOGIC.md** | (този handoff + sister doc) | ✅ |

### Активни mockups в repo (всички canonical references)

| Файл | Цел | Статус |
|---|---|---|
| `mockups/P2_home_v2.html` | Стар home (legacy reference) | остава |
| `mockups/P3_list_v2.html` | **products.php list view** (главна страница артикули) | остава |
| `mockups/P8_studio_main.html` | AI Studio standalone (`ai-studio.php`) | остава |
| `mockups/P8b_studio_modal.html` + 5 advanced | AI Studio per-product modal | остават |
| `mockups/P8c_studio_queue.html` | AI Studio queue overlay | остава |
| `mockups/P10_lesny_mode.html` | `life-board.php` (Лесен режим) | остава |
| `mockups/P11_detailed_mode.html` | `chat.php` (Подробен режим) | остава |
| **`mockups/P12_matrix.html`** | **Matrix overlay (fullscreen)** — нов | ✅ нов |
| **`mockups/P13_bulk_entry.html`** | **products.php "Добави артикул" wizard** — нов | ✅ нов |

### Документи за изтриване

| Файл | Защо |
|---|---|
| `mockups/P4_wizard_step1.html` | Заместен от P13 |
| `mockups/P4b_photo_states.html` | Логиката е в P13 Section 4 |
| `mockups/P5_step4_variations.html` | Заместен от P13 Section 2 |
| `mockups/P6_matrix_overlay.html` | Заместен от P12 v3 (CSS Grid) |
| `mockups/P7_recommended.html` | Заместен от P13 Section 3 + Section 5 |
| `mockups/P9_print.html` | Печатът сега е inline в P13 (per-section) |
| `DETAILED_MODE_DECISION.md` | Architecture e settled, документът не е нужен |
| `SESSION_HANDOFF_CONSOLIDATED.md` | Старо handoff — заменен от този |

### Запазени документи

| Файл | Защо |
|---|---|
| `AI_STUDIO_LOGIC_DELTA.md` | Логика за P8/P8b/P8c (AI Studio standalone) |
| `MASTER_COMPASS.md` | Coordination doc (винаги остава) |
| `SHEF_RESTORE_PROMPT.md` | Boot prompt за Шеф-чат |
| `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` | Bible (sacred) |
| `INVENTORY_HIDDEN_v3.md` | Hidden Inventory философия |
| `PRIORITY_TODAY.md` `DAILY_RHYTHM.md` `DOCUMENT_PROTOCOL.md` `CLAUDE_GITHUB_ACCESS.md` | Operational docs |

---

## 2. Cclaude Code mission

### Cel
Replace целия "Добави артикул" wizard в `products.php` с new accordion design (P13). Integrate matrix overlay (P12). Implement Hidden Inventory + bulk session continuation + photo AI recognition.

### Файлове засегнати

| Файл | Тип work | Lines (estimated) |
|---|---|---|
| `products.php` | Major rewrite — wizard section | ~3000-4000 lines (целия "Добави артикул" блок) |
| `assets/css/products-bulk.css` | New — extracted styles от P13 | ~800 lines |
| `assets/js/products-bulk.js` | New — accordion + voice + matrix + photo AI | ~600 lines |
| `api/products-bulk-save.php` | New endpoint — save per section + confidence calc | ~250 lines |
| `api/photo-ai-detect.php` | New endpoint — AI color detection | ~150 lines |
| `api/products-search-copy.php` | New endpoint — "Намери и копирай" | ~120 lines |
| `templates/wizard-search-sheet.php` | New partial — search bottom sheet | ~80 lines |
| `cron/photo-ai-detect-worker.php` | New — async AI detection | ~80 lines |

### Основни ограничения (Bible §1-§16, never violate)

1. **Design Kit v1.1 compliance.** `bash /var/www/runmystore/design-kit/check-compliance.sh products.php` MUST exit 0.
2. **Bible §5 (sacred):** Neon Glass borders в dark mode = `oklch` + `mix-blend-mode: plus-lighter` + 4 sacred spans. **Never simplify.**
3. **0 emoji in UI** (Bible §14). SVG only.
4. **Никога "Gemini" в UI** — винаги "AI". (ЗАКОН №2)
5. **PHP смята, AI говори** — confidence score, margin, SKU summary, total qty се изчисляват в PHP. AI само вокализира.
6. **DB field names canonical:**
   - `products.code` (не sku)
   - `products.retail_price` (не sell_price)
   - `products.image_url` (не image)
   - `inventory.quantity` (не qty)
   - `inventory.min_quantity` (не min_stock)
7. **Никога `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`** — MySQL 8 не поддържа. Use PREPARE/EXECUTE с information_schema check.
8. **Никога `sed`** за file edits — Python scripts only (`/tmp/sXX_*.py`).
9. **All prices via `priceFormat($amount, $tenant)`** — never hardcoded "лв"/"BGN"/"€".
10. **i18n: всички UI strings via `t('key')`** using `tenant.lang`. Never hardcoded Bulgarian.
11. **Bulgarian dual pricing** (€ + лв at rate `1.95583`) required by law until 08.08.2026 за `tenant.country_code='BG'`.
12. **Voice integration LOCKED commits** `4222a66` + `1b80106` — НЕ РУШИ. Voice parser `_wizPriceParse` остава непроменен.
13. **tmux session mandatory** за CC: `tmux new -s cc_p13` → `cd /var/www/runmystore` → CC.

### Verification gates (must pass before commit)

- [ ] `php -l products.php` exit 0
- [ ] `bash design-kit/check-compliance.sh products.php` exit 0
- [ ] Visual diff vs `mockups/P13_bulk_entry.html` ≤ 1% pixel divergence (test in browser at 375px viewport)
- [ ] Mode toggle Единичен/Вариации работи
- [ ] Save per section запазва в DB с правилен `confidence_score`
- [ ] Matrix expand → отваря `P12_matrix.html` overlay
- [ ] Photo AI detect → закача снимка на правилен цвят (>85% автоматично, <85% потвърждение)
- [ ] AI Studio link → отваря `P8b_studio_modal.html` модал
- [ ] Bottom bar Undo маха последна стъпка
- [ ] "Запази · следващ" dropdown показва "Като предния" + "Празно"

---

## 3. Phasing (deployment order)

### Phase A — Mockup deploy & cleanup (тази сесия, веднага)
- Изтриване на стари mockups (Phase A1)
- Upload `P12_matrix.html` + `P13_bulk_entry.html` + `HANDOFF_FINAL_BETA.md` + `PRODUCTS_BULK_ENTRY_LOGIC.md`
- Commit `S96.MOCKUPS_FINAL`

**Time:** 5 min (Тихол прави сам с командите долу)

### Phase B — DB schema (Claude Code, day 1)
- Добавя `products.confidence_score` TINYINT (0-100)
- Добавя `products.has_variations` TINYINT(1)
- Добавя `products.last_counted_at` DATETIME nullable
- Добавя `products.counted_via` ENUM('manual','barcode','rfid','ai') nullable
- Добавя `products.first_sold_at` DATETIME nullable
- Добавя `products.first_delivered_at` DATETIME nullable
- Добавя `products.zone_id` INT nullable
- Добавя `products.subcategory_id` INT nullable
- Добавя `bulk_sessions` table (`id`, `tenant_id`, `user_id`, `started_at`, `ended_at`, `template_product_id`, `total_saved`, `total_sku`)
- Добавя `bulk_session_items` table (`id`, `session_id`, `product_id`, `saved_at`, `position`)

**Migration файл:** `/var/www/runmystore/db/migrations/2026_05_p13_bulk_entry.sql`

### Phase C — products.php wizard rewrite (Claude Code, days 2-4)
**See `PRODUCTS_BULK_ENTRY_LOGIC.md` for железна спецификация.**

### Phase D — i18n keys (Claude Code, day 4)
~70 нови keys (виж `PRODUCTS_BULK_ENTRY_LOGIC.md` § "i18n keys").

### Phase E — Photo AI detection endpoint (Claude Code, day 5)
- Endpoint `api/photo-ai-detect.php`
- Cron worker за async (>500ms) processing
- Confidence threshold: ≥85% auto, 60-85% confirm, <60% block

### Phase F — APK rebuild + ENI smoke test (Тихол, day 6)
- Capacitor APK build
- Install на Z Flip6
- Walk through P13 на real device

### Phase G — Beta launch (день 7, 14-15.05.2026)
- ENI 5 магазина live
- Real artikuli въвеждане
- Real-time monitoring

---

## 4. Risks & contingencies

| Риск | Mitigation |
|---|---|
| Claude Code прави wizard "по-просто" от P13 | **Refuse**. P13 е canonical. Всеки опит за simplification = STOP, виж mockup. |
| Photo AI detection не работи добре <85% | Fallback: **manual color picker** dropdown в photo result row (override чрез "размени" бутон) |
| Voice не работи на Z Flip6 | Pesho ползва keyboard fallback (mic бутон → отваря native keyboard ако voice fail) |
| Performance — accordion с 5 секции = bavna animation на по-стари devices | Virtual scroll или collapse default за все секции освен 1-вата (вече е така) |
| Bulk session crash → загубени артикули | Auto-save към `bulk_sessions` при всеки section save. Resume bulk session при reopen. |
| Сложен matrix (5×5+) на тесен screen | Auto-bypass to `P12_matrix.html` fullscreen при ≥4×4 cells |

---

## 5. Sign-off checklist

Преди commit на products.php rewrite:

- [ ] Прочетен `PRODUCTS_BULK_ENTRY_LOGIC.md` целия (≥3 пъти)
- [ ] Прочетен `mockups/P13_bulk_entry.html` целия CSS + HTML структура
- [ ] Прочетен `mockups/P12_matrix.html` целия
- [ ] Прочетен `INVENTORY_HIDDEN_v3.md` § confidence_score
- [ ] Прочетен `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` § "Sacred elements"
- [ ] All compliance gates пасват
- [ ] Smoke test на test tenant (`tenant_id=7`) — създаден 1 артикул single mode + 1 артикул variations mode + 1 bulk session с 3 артикула
- [ ] Commit message: `S97.P13_BULK_ENTRY: products.php "Добави артикул" rewrite (accordion)` — single commit, no piecemeal

---

## 6. Sister documents (read in order)

1. `HANDOFF_FINAL_BETA.md` ← **YOU ARE HERE**
2. **`PRODUCTS_BULK_ENTRY_LOGIC.md`** ← железна спецификация за всички sections
3. `mockups/P13_bulk_entry.html` ← canonical visual reference (1:1 implementation)
4. `mockups/P12_matrix.html` ← matrix overlay reference
5. `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` § Sacred neon glass
6. `INVENTORY_HIDDEN_v3.md` § confidence_score logic

---

## 7. Communication

**Тихол** = boss, не разработчик. Дава instructions на български. Никога pas-вай "ти разработчик ли си" въпрос.

**Шеф-чат (тази сесия)** = mockups + logic specs. Не commit-ва, не deploy-ва.

**Claude Code** = пише production code. Този handoff + logic doc е твоят contract.

End of HANDOFF_FINAL_BETA.md
