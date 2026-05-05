# 📜 COMPASS APPEND — S95 SESSION (05.05.2026)

**Тихол:** paste следния блок в КРАЯ на `MASTER_COMPASS.md` (преди „LOGIC CHANGE LOG" секция, или най-долу), commit + push.

═══════════════════════════════════════════════════════════════
COPY START
═══════════════════════════════════════════════════════════════

## 🔒 S95 SESSION (05.05.2026) — products wizard + sale UX + hardening marathon

**Продължителност:** 8:13 сутринта → 13:00+ (cca 5 часа). 129 git commits.
**Status:** Beta launch след 9 дни (14-15 май). Tihol тества real product entry.

### ✅ ЗАВЪРШЕНО

**Products wizard (rounds 1-5):**
- Round 1 (`b01802b`): qty=0 в DB fix, search delay, CSRF retry, variant labels, numeric keypad, dead Детайли page, save loading
- Round 2 (`d749270`): „Препоръчителни ›" в variant flow, Назад force-route, matrix descriptive labels
- Round 3 (`b94f07a`): footer dynamic labels, „Препоръчителни" не clip
- Round 4 (`d15dd70`): stacked footer layout (2 малки горе + 1 голям „Допълнителни данни (препоръчително)" долу)
- Round 5 (`d9c6036` + `09911c5`): DESIGN_LAW Option B (CSS infrastructure) + Option A (q-* class + glow spans на key cards)
- Quick fix (`S95.WIZARD.GLASS_FIX`): премахнат `.s2-section.glass{background:transparent}` override който блокираше визуалния ефект

**Sale.php UX (rounds 2-5):**
- Round 2 (S87G commits): search без Enter, ranking case-insensitive + word-boundary, +/- vibrate+sound, ПОТВЪРДИ 2-sec hold
- Round 3 (S87H): ПЛАТИ button restore, swipe-to-delete event delegation, search „Виж всички", stock_neg log
- Round 4 (S87I — `eafb8be` + `185badf`): ПЛАТИ visibility Capacitor APK fix, stock warning не блокира add-to-cart
- Round 5 (S87J — `c44af59` + `a89bf96`): stock=0 confirm modal, glass success card вместо fullscreen flash
- Park sale fix: `showSaleSuccess` numeric total (was NaN EUR)

**Hardening (Standing Rule #29 Phase 1):**
- sale.php — 9 commits S97 (`72afd06..3a7acd5`): stock guard FOR UPDATE, race conditions DB::tx, CSRF, rate limit, audit log, security headers
- products.php — 9 commits S97 (`33914a8..557cbd1`): image upload caps (5MB+MIME+dim), price/qty guards (negative+1M cap), CSRF, rate limit, audit log backfill, barcode unique app+DDL, input validation

**Voice STT (от 04.05 session):**
- Cyrillic-aware parser (`1b80106`)
- BG → Web Speech instant, non-BG → Whisper (`8a26785`)
- Mic error hint при price field (`845ad1f`)

### 🆕 SCHEMA UPDATES (registered 05.05.2026)

ALTER-и applied:
- `tenants.max_discount_pct` DECIMAL(5,2) NOT NULL DEFAULT 30.00
- `tenants.allow_negative_stock` TINYINT(1) NOT NULL DEFAULT 0
- `stock_movements.reason` VARCHAR(32) NULL
- `stock_movements.notes` TEXT NULL
- `products` UNIQUE KEY uk_tenant_barcode (tenant_id, barcode) — изисква pre-cleanup на дубликати

CREATE TABLE applied (commit `9c0665d` STATE + `2922c70` COMPASS):
- `inventory_adjustments` — списък за чакаща инвентаризация (negative stock sales, manual corrections, damage, theft, returns)
- Used by: sale.php (sale_negative INSERT), inventory.php (UI list, future build), transfers.php (manual corrections, future), ai-action.php (reports query)
- Schema: id, tenant_id, product_id, type ENUM, quantity, reason, sale_id, user_id, status ENUM, resolved_at, resolved_by, notes, created_at

### 🔴 OUTSTANDING (P0 за следваща сесия)

**1. Wizard DESIGN_KIT compliance — НЕ работи**
- 3 опита направени (Option B + Option A + glass override fix), Tihol тества и НЕ виждa разлика
- Capacitor WebView кеширане + Code 1 предупреди че custom CSS на s4ai-prompt/s82-finalprompt може да use hard-coded цветове (не hsl(var(--hue1)...))
- Wizard модал имa 3000+ редa custom CSS които противоречат на canonical .glass + .shine + .glow
- Препоръчителен подход: Option C (multi-session full migration) — отделна задача за нов шеф-чат
- Документ: `S95_DESIGN_KIT_HANDOFF.md` (отделен файл за новия шеф-чат)

**2. Variant matrix labels „Вариация 2" → „Кои цветове?"**
- Текстът се update-ва в matrix headers (round 3) но не в долен footer button
- Tihol screenshot потвърди

**3. Beta-blocker модули на 0%:**
- deliveries.php — 455 LOC skeleton, Phase 2-4 (receive flow + OCR + voice + AI Brain) НЕ build-нати
- transfers.php — НЕ съществува
- inventory.php — 458 LOC skeleton, 0 audit_log calls

### 🛡️ STANDING RULE #29 — MODULE HARDENING PROTOCOL

След всеки завършен модул задължително:
- Phase 1: Internal hardening sweep (tenant guards, audit log, stock guard, XSS, race conditions, file size limits, negative value guards, EAN UNIQUE, MIME server-side check, CSRF, rate limit, security headers)
- Phase 2: AI fan-out (Tihol manually питам Gemini 2.5 Pro + ChatGPT-5 + Claude (друга сесия) + Kimi с template — какви защити пропуснах)
- Phase 3: Integration commits като MODULE_HARDENED tag
- Phase 4: Document в `docs/hardening/[MODULE]_HARDENING_AUDIT.md`

**Order на applying:**
- ✅ products (05.05) — Phase 1 done
- ✅ sale (05.05) — Phase 1 done
- ⏳ deliveries — след build complete (~07.05)
- ⏳ transfers — след build (~10.05)
- ⏳ inventory — след build (~10.05)

### 🖨️ D520BT PRINTER — HANDOFF КЪМ НОВ OPUS CHAT

DTM-5811 ✅ работи (TSPL, BDA `DC:0D:51:AC:51:D9`, PIN 0000, firmware 2.1.20241127, codepage CP437→CP1251, density 6, speed 3, 50×30mm). **НЕ ПИПАЙ настройките — много време загубено за откриване.**

D520BT ❌ — Phomemo OEM (Zhuhai Quin Technology), BLE protocol, затворен Labelife. Нужен reverse engineering.
- Промпт за нов Opus chat: `D520BT_OPUS_PROMPT.md`
- Цел: open-source pyphomemo / Phomemo D family BLE specs research → working JS код → НЕ конфликт с DTM-5811

### 📐 ROADMAP REVISION (към 05.05.2026)

**Седмица 1 (05-09 май):** sequential module testing
- Понеделник: Products real entry (5-10 артикула single, 3-5 с вариации)
- Вторник-сряда: Deliveries module BUILD + test
- Четвъртък: Sale real test (без inventory link)
- Петък: Transfers + Inventory build + sync

**Седмица 2 (12-15 май):** интегриран beta
- Понеделник: интегриран test (всички модули заедно)
- Сряда-петък: ENI BETA LAUNCH

═══════════════════════════════════════════════════════════════
COPY END
═══════════════════════════════════════════════════════════════
