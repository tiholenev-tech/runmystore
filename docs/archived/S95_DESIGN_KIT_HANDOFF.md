---
# ⚠️ ARCHIVED — НЕ ИЗПОЛЗВАЙ

**Архивиран на 2026-05-07.** Заместен от `DESIGN_SYSTEM_v4.0_BICHROMATIC.md`.
**Не следвай инструкциите тук** — те са САМО за исторически референции.
---

# 🎨 DESIGN_KIT COMPLIANCE — НОВА ШЕФ-ЧАТ ЗАДАЧА

**За:** Нов Claude Opus 4.7 шеф-чат  
**От:** Tihol (frustrated след 3 неуспешни опита 05.05.2026)  
**Beta launch:** 14-15 май 2026 (9 дни)

═══════════════════════════════════════════════════════════════
## ИСТОРИЯ НА ПРОБЛЕМА
═══════════════════════════════════════════════════════════════

Цялото `products.php` (14,000 реда) — главно wizard модал за добавяне на артикул — НЕ съответства на DESIGN_KIT visual standard на проекта. Останалите страници (home.php, sale.php, etc.) са compliant — wizard е outlier.

**Какво е DESIGN_KIT:**
- Файл: `/var/www/runmystore/DESIGN_LAW.md` + папка `/var/www/runmystore/design-kit/`
- Components: `/var/www/runmystore/design-kit/components.css`
- Pattern: **Neon Glass** — всяка карта е `<div class="glass">` + `<span class="shine"></span><span class="glow"></span>`
- Hue класове (`q1`-`q6` + semantic aliases `q-default`/`q-magic`/`q-loss`/`q-amber`/`q-gain`/`q-jewelry`)
- Typography: Montserrat single font
- Mobile-first 480px max-width

**Какво се опита 05.05.2026:**

| Опит | Commit | Резултат |
|---|---|---|
| Round 4 | `b94f07a` | Footer button dynamic labels — функционално OK, но визия не consistent |
| Option B (CSS infrastructure) | `d9c6036` | Добавени глобални q-* класове + .glass fallback. **Никой HTML не ги use** → no visual change. |
| Option A (surgical apply) | `09911c5` | Apply на q-default + glow-top/glow-bottom на 11 wizard cards. Tihol HARD CACHE CLEARED — no visual change. |
| Glass override fix | quick fix | Премахнат `.s2-section.glass{background:transparent}` правило което блокираше визуалния ефект. **Нужен retest.** |

**Hypothesis след DevTools test (от Tihol's screenshot):**
- `getComputedStyle(.s2-section.q-amber)` показа: `--hue1=38`, `--hue2=28` ✅ (CSS variables работят)
- Но `background: rgba(0, 0, 0, 0)` — **прозрачен** → glass effect неактивен
- Quick fix `S95.WIZARD.GLASS_FIX` push-нат — НЕ е тестван още визуално

═══════════════════════════════════════════════════════════════
## ЗАДАЧАТА
═══════════════════════════════════════════════════════════════

**Първо** — потвърди че quick fix `S95.WIZARD.GLASS_FIX` (премахни `.s2-section.glass{background:transparent}` override) даде ефект. Tihol clear-ва cache на phone, прави screenshot/console log.

**Ако quick fix не дава ефект** → следвай Option C (multi-session full migration):

### Phase 0 — DEEP AUDIT (15-30 мин)

1. Прочети `/var/www/runmystore/DESIGN_LAW.md` ПЪЛНО
2. Прочети `/var/www/runmystore/design-kit/components.css` (canonical .glass + .shine + .glow rules)
3. Прочети `/var/www/runmystore/design-kit/REFERENCE.html` (visual reference)
4. Run `bash design-kit/check-compliance.sh` за products.php — kak се rates currently
5. Map ВСИЧКИ wizard render functions в products.php (renderWizPhotoStep, renderWizStep2, renderWizPage(N), renderWizPriceStep, etc.)
6. List всички custom CSS classes които overlap-ват с canonical (`.v4-glass-pro`, `.s2-section`, `.wiz-page`, `.s4ai-prompt`, etc.)

### Phase 1 — STRATEGY DECISION

Pick една от 3:

**A) Option C — Full migration (rewrite wizard CSS layer)**
- 50-80 LOC delete (custom classes)
- 100-150 LOC adapt (HTML wrappers)
- 3-5 commits across 2-3 sessions
- Browser test между всяка
- Риск: regression на wizard функционалност

**B) Pragmatic — leave custom CSS but fix all hard-coded colors**
- find `rgba(99,102,241,...)` → replace with `hsl(var(--hue1) ...)`
- find `rgba(15,18,36,...)` → replace with theme tokens
- Запазва съществуваща visual structure но adapts to hue cascade
- ~150-300 LOC change
- По-нисък риск

**C) Hybrid — Phase 1 quick wins + Phase 2 full rewrite post-beta**
- Day 1: fix top 3 most-visible cards (Step 1 wrapper, Step 2 sections, mini print success)
- Beta launch with current state
- Post-beta: full migration

Препоръка: **C** ако beta е приоритет, **A** ако Tihol иска clean baseline.

### Phase 2 — IMPLEMENTATION

Ако Option A:
1. Rewrite renderWizPhotoStep — apply canonical glass pattern
2. Rewrite renderWizStep2 — same
3. Rewrite variant matrix step — same
4. Rewrite mini print success overlay — same
5. Rewrite AI Studio prompt overlay — same
6. Удалить `.s2-section`, `.v4-glass-pro`, etc. custom rules

Ако Option B:
1. Find/replace hard-coded RGBA → hsl(var(--hue1)...)
2. Verify --hue1 cascade works (should after `S95.WIZARD.GLASS_FIX`)
3. Apply правилни q-* класове на всички wizard sections (q-default, q-amber, q-magic, etc.)

### Phase 3 — TESTING

1. Capacitor APK clear cache + clear data на phone
2. Screenshot Step 1 → glass cards с halo glow?
3. Screenshot Step 2 → q-amber tint на цени?
4. AI Studio prompt → q-magic violet?
5. Mini print success → q-gain green?

═══════════════════════════════════════════════════════════════
## НЕ-ПРАВИ
═══════════════════════════════════════════════════════════════

- НЕ пипай wizard JS логика (event handlers, data flow, navigation)
- НЕ премахвай съществуващи DOM IDs (потребяват се от JS)
- НЕ пипай sale.php — отделен модул
- НЕ commit преди browser test (R8 verify)
- НЕ overpromise — Tihol беше изгорян от прехвърлени фикс-проб-fail цикли

═══════════════════════════════════════════════════════════════
## КОНТЕКСТ ЗА BETA
═══════════════════════════════════════════════════════════════

- ENI client beta launch 14-15 май
- Tihol тества real product entry от утре (06.05) — design polish може да изчака до 09-10.05
- Beta-blocker модули: deliveries (0%), transfers (0%), inventory (0%) — те трябва build първо
- Design kit compliance е „nice-to-have" за beta, не „must-have"

═══════════════════════════════════════════════════════════════
## РЕСУРСИ
═══════════════════════════════════════════════════════════════

**GitHub access:**
- Public repo: github.com/tiholenev-tech/runmystore
- raw.githubusercontent.com BLOCKED
- Метод: `curl https://github.com/tiholenev-tech/runmystore/blob/main/FILE?plain=1`
- Helper: `tools/gh_fetch.py`

**Server:**
- DigitalOcean Frankfurt 164.90.217.120
- `/var/www/runmystore/`
- mysql `runmystore` database

**Critical context (от userMemories):**
- Tihol не е developer — paste команди в droplet console, review резултати
- Capacitor WebView кешира агресивно — clear cache + clear data ЗАДЪЛЖИТЕЛНО след всеки CSS промяна
- Samsung Z Flip6 — 373px cover display target
- Sacred elements в DESIGN_LAW: .glass + .shine + .glow spans, header, bottom nav — НИКОГА не се опростяват

═══════════════════════════════════════════════════════════════
## CODE_CODE_PREFIX (R1-R11) — за всеки spec към Code 1/Code 2
═══════════════════════════════════════════════════════════════

R1 ESCALATE | R2 NO REGRESSION | R3 SAFE MODE (additive-only за >500KB файлове) | R4 NO DESTRUCTIVE (no rm/sed/DROP/ALTER без confirm) | R5 DB CONVENTIONS (products.code, retail_price, inventory.quantity, sales.status='canceled') | R6 UI INVARIANTS (.glass+.shine+.glow свещени) | R7 COMMIT DISCIPLINE (1 atomic per fix) | R8 VERIFY BEFORE DONE (php -l + git diff --stat) | R9 ZERO EDITS извън scope | R10 PROGRESS TRANSPARENCY | R11 DESIGN LAW (прочети /var/www/runmystore/DESIGN_LAW.md преди UI работа)

═══════════════════════════════════════════════════════════════

**Когато получиш този документ → потвърди че разбираш scope, питай Tihol какъв подход (A/B/C) предпочита, не започвай build без негово потвърждение.**
