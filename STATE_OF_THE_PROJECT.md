# 🎯 STATE_OF_THE_PROJECT — Live Snapshot

**Дата на последен update:** 08.05.2026 EOD (петък сутрин EEST) — replaces 03.05.2026 EOD
**Версия:** v2.0 (major refresh — 16 sessions log + audit results + S96 design migration phase)
**Update протокол:** ВСЕКИ Claude Code в края на сесията update-ва САМО този файл (не COMPASS, не handoff). Шеф-чат update-ва секция `📋 LIVE BUG INVENTORY` в EOD протокола.
**Шеф-чат първо чете ТОЗИ файл, после COMPASS, после SESSION_HANDOFF_FOR_NEXT_SHEF.md.**

---

## 📋 LIVE BUG INVENTORY — single source of truth ⭐ v2.5

> **ПРАВИЛО:** Тази секция е първото нещо което всеки шеф-чат чете в Phase 0.5. Aggregate-ва P0/P1/P2 от 5-те източника в едно място. Шеф-чат update-ва в EOD: добавя нови, marks completed.
>
> **Aggregated дата:** 08.05.2026 сутрин EEST

### 🔴 P0 — ACTIVE (по приоритет, 12 items)

| # | Bug code | Module | Описание | Source | Target session |
|---|---|---|---|---|---|
| 1 | UI-REWRITE-LIFEBOARD | life-board.php | Replace с P10 mockup (Weather Forecast + AI Studio row + ops горе) | mockups/P10 | Code Code pilot, ден 1 (08.05) |
| 2 | UI-REWRITE-CHAT | chat.php | Replace с P11 mockup (filter pills + 12 signals + bottom nav) | mockups/P11 | Code Code, ден 2 (09.05) |
| 3 | UI-REWRITE-AISTUDIO | ai-studio.php + 2 partials | Replace с P8/P8b/P8c mockups | mockups/P8* | Code Code, ден 3 (10.05) |
| 4 | UI-REWRITE-PRODUCTS | products.php (16K LOC) | Manual assembly P2-P9 (Standing Rule #31 — НЕ Code Code) | mockups/P2-P9 | Manual ръчно, дни 4-6 (11-13.05) |
| 5 | CSRF-BATCH | 11 POST endpoints | S118 patches ready /tmp/csrf_fix/ | sec_audit | Apply ден 7 (13.05) batch A/B/C/D |
| 6 | PERF-INDEXES | DB schema | INDEXES.sql (off-peak) — sale-voice/search/stats/products_fetch | perf_audit | Apply ден 7 (13.05) off-peak |
| 7 | PERF-PATCHES | 4 query rewrites | S120 patches ready /tmp/perf_fix/ | perf_audit | Apply incremental след indexes |
| 8 | AIBRAIN-PILL | partials/ai-brain-pill.php | Class-name mismatch fix (aibrain-pill → ai-brain-pill) | aibrain_audit | С chat.php migration (ден 2-3) |
| 9 | RWQ-72 | products.php voice | S99.VOICE.PROPER_REWRITE (Whisper + trigger words) | REWORK QUEUE | post-mockup S99 |
| 10 | NAME_INPUT_DEAD | ?? | TRACK 2 finding spec needed | TRACK 2 | post-wizard finish |
| 11 | D12_REGRESSION | products.php | TRACK 2 finding spec needed | TRACK 2 | post-wizard finish |
| 12 | WHOLESALE_NO_CURRENCY | sale.php или products.php | TRACK 2 finding spec needed | TRACK 2 | post-wizard finish |

### 🟢 CLOSED 07-08.05.2026 (12 items)

| # | Bug code | Module | Closed by | Commit |
|---|---|---|---|---|
| C1 | DESIGN-FRANKENSTEIN | DESIGN_SYSTEM | S96.DESIGN.BICHROMATIC v4.1 (Опус 07.05) | 478eb4d → 01a0704 |
| C2 | PRINTER-CYRILLIC | js/capacitor-printer.js | S103D.PRINTER.CYRILLIC hybrid path | 7c2a13f |
| C3 | DEV-EXEC-RCE | dev-exec.php | S116.SEC quarantine | 91b04c4 |
| C4 | SESSION-FIXATION | login.php + .htaccess | S119.SEC applied | 50a7451 |
| C5 | INSECURE-COOKIES | login.php + .htaccess | S119.SEC (secure+httponly+samesite=Strict) | 50a7451 |
| C6 | S104-S106-MIGRATION | 21 modules | REVERTED (shallow disaster) | 5f36cbd |
| C7 | S113-PRODUCTS-REWRITE | products.php | REVERTED (visual disaster) | 072520f + 5220d1e |
| C8 | GIT-LEAKED-BACKUPS | repo | .gitignore + cleanup 196K deletions | 07fac0b |
| C9 | LOGS-DIR-MISSING | /var/www/runmystore/logs/ | S110 mkdir + chown www-data | manual |
| C10 | CRON-INSTALL | /etc/cron.d/runmystore | S110 cron template | manual |
| C11 | DB.ENV-PERMS | /etc/runmystore/db.env | chmod 640 | manual |
| C12 | CHROMIUM-INSTALL | apt | snap → deb | manual |

### 🟡 P1 — SECONDARY (post-beta или batch later)

| Bug code | Description | Defer to |
|---|---|---|
| XSS-12 | 12 stored XSS findings (htmlspecialchars on tenant data) | Post-beta batch |
| AIBRAIN-QUEUE | ai_brain_queue table existing, no producers wired | S116.AIBRAIN_QUEUE_BUILD post-beta |
| DELIVERIES-AIBRAIN | RWQ #81 — voice OCR + defective detection | S117.DELIVERIES_AIBRAIN post-beta |
| MARKETING-SCHEMA-APPLY | 25 mkt_* + 9 ALTER на live DB | Post-beta sandbox first |
| KERNEL-REBOOT | 6.8.0-111-generic pending | Post 15.05 |

### 🟣 P2 — POST-BETA / OPTIONAL

| Bug code | Description |
|---|---|
| I18N-FRAMEWORK | t() function + 110 keys + 60 date formats (15-20 days) |
| API-KEY-ROTATE | Gemini + Groq + fal + Ecwid (preventive) |
| PROMOTIONS-MODULE | Phase D (deferred) |
| LOYALTY-MIGRATION | RWQ #67 |
| DEV-EXEC-CLEANUP | Delete .QUARANTINED file post 30 days retention |

---

## 🌍 CURRENT PRODUCTION STATE

### Sites & Modules

| Module | Status | Notes |
|---|---|---|
| https://runmystore.ai/login.php | 🟢 200 OK | S119 hardened |
| https://runmystore.ai/products.php | 🟢 302 (auth gate) | Pre-S113 state, 14617 LOC |
| https://runmystore.ai/sale.php | 🟢 OK | Pre-migration state |
| https://runmystore.ai/life-board.php | 🟢 OK | v4.1 etalon (about to be replaced by P10) |
| https://runmystore.ai/dev-exec.php | ✅ 404 | QUARANTINED |
| Mirror auto-sync cron | 🟢 Working | tihol user pushes via mirror; root direct push |
| Pre-commit hook v4.1 | 🟢 Active | 15 BICHROMATIC checks + audit |

### Sessions cron status (08.05.2026)

```
0 3  * * *   www-data  /var/www/runmystore/tools/diagnostic/cron/sales_pulse.sh
30 3 * * *   www-data  /var/www/runmystore/tools/stress/run_full_stress.sh
0 3  * * 1   www-data  /var/www/runmystore/tools/diagnostic/cron/diagnostic_weekly.sh
0 4  1 * *   www-data  /var/www/runmystore/tools/diagnostic/cron/diagnostic_monthly.sh
30 8 * * *   www-data  /var/www/runmystore/tools/diagnostic/cron/daily_summary.sh
5 *  * * *   www-data  /usr/bin/php /var/www/runmystore/cron-insights.php
0 2  1 * *   www-data  /usr/bin/php /var/www/runmystore/cron-monthly.php
```

---

## 📊 PROGRESS BY PHASE

### Phase A1 — Foundation (~85%)

✅ Design system v4.1 BICHROMATIC locked (S96 07.05)
✅ Pre-commit hook v4.1 + check-compliance.sh
✅ Audit infrastructure (audit.sh + INDEXES.sql draft)
✅ Standing Rules #30-34 documented
✅ Mockup design phase finalized (12 approved mockups)
✅ Security hardening: dev-exec quarantine + S119 session fix
🟡 Pending: visual rewrite per mockup (life-board, chat, ai-studio, products)

### Phase A2 — Operations Core (~30%)

🟡 Beta-blocker модули:
  - life-board.php — etalon, ще се replace
  - chat.php — pending P11 rewrite
  - sale.php — pre-migration, hardened S87E
  - products.php — pre-migration, awaiting manual rewrite
  - inventory.php / warehouse.php — pre-migration
  - deliveries.php / delivery.php — 0% real implementation
  - orders.php / order.php — 0% real implementation
  - transfers.php — 0% (post-beta candidate)
  - settings.php / stats.php / printer-setup.php — pre-migration

🟡 Audits ready to apply (post-mockup):
  - CSRF (11 patches)
  - Performance (4 patches + indexes)
  - AI Brain pill class fix

### Phase B — Advanced (~5%, mostly post-beta)

⏳ Marketing schema migration (25 mkt_* + 9 ALTER) — sandbox tested, prod apply post-beta
⏳ AI Brain Phase 2 (queue producers + worker)
⏳ Promotions module
⏳ Loyalty migration
⏳ AI Studio queue + 5 categories (P8b/P8c mockups готови)

---

## 🛠 INFRASTRUCTURE

### Server
- DigitalOcean Frankfurt droplet (164.90.217.120)
- Ubuntu 24, kernel 6.8.0-107 (pending reboot to 6.8.0-111 post-beta)
- Apache + PHP 8.3 + MySQL 8

### Git
- Repo: github.com/tiholenev-tech/runmystore (public)
- Push: root user direct, tihol user via mirror auto-sync
- Pre-commit hook: design compliance v4.1 (45+ patterns)
- Latest commits visible: до 08.05 09:34 dd23855

### DB
- Database: runmystore (live)
- Sandbox capability: pending DB access for tihol user (S111 marketing migration test)
- Schema migrations: migrations/ directory (s92_aibrain + ai_studio + 20260508_marketing draft)

### Hardware
- Test device: Samsung Z Flip6 (~373px viewport)
- Label printer: DTM-5811 BT thermal (TSPL, 50×30mm) — working 07.05 closure
- D520BT printer (S103D session): hybrid BAR-RLE path live

### External services
- Gemini 2.5 Flash (text/vision) — 2 API keys rotation
- Groq Whisper (voice tier 2)
- fal.ai (image generation, FAL_API_KEY in api.env)
- Ecwid (online store partner)

---

## 📦 MOCKUPS REPOSITORY (12 approved, 08.05.2026)

```
mockups/
├── ai-studio-categories.html    (Apr 26, legacy)
├── ai-studio-main-v2.html       (Apr 26, legacy)
├── ai_studio_FINAL_v5.html      (Apr 27, legacy)
├── P2_home_v2.html              (May 8, 1413 lines) — products home
├── P3_list_v2.html              (May 8, 1605 lines) — products list
├── P4_wizard_step1.html         (May 8, 1957 lines) — wizard consolidated
├── P4b_photo_states.html        (May 8, 1102 lines) — 6 photo states
├── P5_step4_variations.html     (May 8, 1477 lines) — variations matrix
├── P6_matrix_overlay.html       (May 8, 714 lines) — fullscreen matrix
├── P10_lesny_mode.html          (May 8, 1354 lines) — life-board.php REPLACE
└── P11_detailed_mode.html       (May 8, 1577 lines) — chat.php REPLACE

Pending (Опус не направи):
- P1_empty_state.html
- P7_recommended.html (renderWizStep2)
- P8_studio_main.html (ai-studio.php standalone)
- P8b_studio_modal.html × 5 (per-product modal)
- P8c_studio_queue.html (queue overlay)
- P9_print.html (Step 6 print preview)
```

---

## 📚 DOCUMENTATION INDEX

### Authoritative (single source of truth)
- `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` — Bible v4.1 (2748 реда)
- `MASTER_COMPASS.md` — orchestrator (3000+ реда)
- `STATE_OF_THE_PROJECT.md` — this file
- `CLAUDE_CODE_DESIGN_PROMPT.md` — design wrapper (92 реда)

### Active handoffs (08.05.2026)
- `SESSION_HANDOFF_FOR_NEXT_SHEF.md` — central transition doc (NEW 08.05)
- `SESSION_HANDOFF_CONSOLIDATED.md` — Опус 12 mockups handoff (337 реда)
- `HANDOFF_CONSOLIDATED.md` — older P2-P6 decisions (223 реда)
- `HANDOFF_P4_P4b_FIND_COPY.md` — P4 + Find&Copy (360 реда)
- `DETAILED_MODE_DECISION.md` — detailed mode rationale (238 реда)
- `AI_STUDIO_LOGIC_DELTA.md` — AI Studio changes (verify в repo)

### Reference
- `docs/AI_STUDIO_LOGIC.md` — original spec v1.0 (886 реда)
- `docs/PRODUCTS_DESIGN_LOGIC.md` — wizard logic
- `PRODUCTS_WIZARD_v4_SPEC.md` — wizard spec
- `WIZARD_FIELDS_AUDIT.md` — DB columns
- `docs/marketing/MARKETING_BIBLE_LOGIC_v1.md` + `MARKETING_BIBLE_TECHNICAL_v1.md`
- `INVENTORY_v4.md` — inventory logic + race conditions
- `TECHNICAL_REFERENCE_v1.md` — DB schema canonical

### Process
- `SHEF_RESTORE_PROMPT_v3.md` — start protocol (NEW 08.05, replaces v2.4)
- `BOOT_TEST_FOR_SHEF.md` — IQ test (16 questions)
- `DAILY_RHYTHM.md` — daily flow
- `STRESS_BOARD.md` — stress test 4-graf protocol
- `DOCUMENT_PROTOCOL.md` — document creation rules

---

## 🔐 STANDING RULES (active 08.05.2026, #1-34)

Виж `MASTER_COMPASS.md` Standing Rules section. Last 4 NEW (08.05):
- **#31** — products.php FORBIDDEN ZONE за Code Code rewrite
- **#32** — Mockup = ground truth, DELETE+INSERT not MERGE
- **#33** — Sequential pilot pattern (1 file/session)
- **#34** — Iron Law gate proposal (6 layers, design-kit/iron-law.sh TODO)

---

**Status:** ✅ Created/updated 08.05.2026 EOD by previous Шеф-чат transition.
