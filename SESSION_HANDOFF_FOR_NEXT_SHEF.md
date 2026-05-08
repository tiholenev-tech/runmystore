# 🎯 SESSION_HANDOFF_FOR_NEXT_SHEF — Шеф-чат transition document

**Дата:** 08.05.2026 (петък сутрин EEST)
**Beta countdown:** 6-7 дни до 14-15.05.2026 ENI launch
**Author:** Claude Sonnet 4.5 (предходен шеф-чат, 08.05 нощна сесия)
**Replaces:** stale PRIORITY_TODAY.md (04.05) + STATE_OF_THE_PROJECT.md (03.05 EOD)
**Cross-reference:** SESSION_HANDOFF_CONSOLIDATED.md (Опус mockups handoff, 08.05)

---

## 0 · TL;DR за следващия шеф-чат

В тази 24-часова Шеф-чат сесия (07.05 вечер → 08.05 сутрин EEST) се случи МНОГО. Кратко:

1. **Code Code 1+2 миграция provаl 21 модула към v4.1 BICHROMATIC → counter-test показа shallow migration → REVERTED.** Не повтаряй.
2. **3 audit Code Code сесии генерираха 4 audit reports в /tmp/** (security, performance, i18n, AI Brain). Всички read-only.
3. **dev-exec.php КРИТИЧНА RCE уязвимост open от месеци → QUARANTINED** (commit 91b04c4).
4. **S119 session security applied → live на main** (50a7451) — session_regenerate_id + secure cookie flags.
5. **Опус помощен чат финализира 12 mockups + 5 docs за products.php + life-board.php + chat.php + AI Studio rewrite.**
6. **products.php е "забранена зона" за Code Code rewrite** — proven 3 пъти (S104/S105/S113 disasters). Само ръчен assembly.
7. **Code Code OK за малки файлове** (life-board, chat, ai-studio, нови partials) ако следва DELETE+INSERT (не MERGE) approach.

---

## 1 · STATE OF THE WORLD — какво има на main сега

### 1.1 Production state

| Layer | Status |
|---|---|
| Сайтът | 🟢 Работи (HTTP 200/302, smoke tests passing) |
| 21 module files | 🟢 Pre-migration state (commit 5f36cbd revert) |
| Design system v4.1 | 🟢 Locked (S96 closure 07.05) |
| `life-board.php` | 🟢 Etalon, working dark+light (но скоро ще се replace с P10) |
| `dev-exec.php` | ✅ QUARANTINED (commit 91b04c4) |
| Session security | ✅ S119 deployed (50a7451) — secure+httponly+samesite=Strict |
| Mirror auto-sync | 🟢 Push-ва за tihol user (root user push-ва direct) |
| Pre-commit hook v4.1 | 🟢 Active (15 BICHROMATIC checks + audit) |
| Marketing schema | ⚠️ Migrations готови (uncommitted on disk → дa се преместят в migrations/) — sandbox apply pending DB access |
| TESTING_LOOP cron | 🟡 Installed (S110 — /etc/cron.d/runmystore @ 03:30, sales_pulse @ 03:00) |

### 1.2 Latest commits на main (chronological, last 24h)

```
dd23855 S96.CLEANUP: stage P10/P11 root deletions + gitignore archives
0b636db S96.LESNY+DETAILED: P10 lesny v3 + P11 detailed mode (mockups/)
447c74b S96.LESNY+DETAILED: P10 + P11 + DETAILED_MODE_DECISION + SESSION_HANDOFF_CONSOLIDATED
40d0203 S118 CSRF + S120 PERF audits — драфтове в /tmp/, не committed
50a7451 S119.SEC: session fixation fix + secure cookie flags ⚡ LIVE
4c4921d S113.MOCKUPS: add P4 P4b P5 P6 approved mockups
4eebfe8 S113.MOCKUPS: handoffs from Opus chat (P4 + consolidated)
07fac0b S116.SEC: gitignore backups + logs + install scripts
91b04c4 S116.SEC: quarantine dev-exec.php RCE vulnerability ⚡
80ba9f2 (auto-mirror sync)
9cfdbf5 S116/S117/S115: append findings backlog → next shef
5220d1e Revert "S113.PRODUCTS.REWRITE..." (back to pre-migration)
072520f Revert "S113.EOD: products handoff doc"
5a29aae S113.EOD: handoff doc (REVERTED)
415ecd6 S113.PRODUCTS.REWRITE: scrHome + scrProducts (REVERTED — visual disaster)
0dc3d1a (S110.STRESS_FRESH push)
a6e286d (S110.STRESS push)
1804b8c S111.MARKETING_SCHEMA: 25 CREATE + 9 ALTER (sandbox PENDING DB access)
5f36cbd S112.REVERT: 21 modules → pre-migration (01a0704) ⚡ key revert
1b0b2e1 S96: v4.1 compliance script + pre-commit hook (sole-developer railguards)
```

### 1.3 Approved mockups (12) — в `mockups/`

| Mockup | Lines | Production target |
|---|---|---|
| P2_home_v2.html | 1413 | products.php scrHome (вече revert-нат) |
| P3_list_v2.html | 1605 | products.php scrProducts (вече revert-нат) |
| P4_wizard_step1.html | 1957 | products.php renderWizPhotoStep (~ред 11355+) |
| P4b_photo_states.html | 1102 | products.php camera states (~8225+) |
| P5_step4_variations.html | 1477 | products.php renderWizPagePart2 step===4 (~7115+) |
| P6_matrix_overlay.html | 714 | products.php mxOverlay (~4825+) |
| P7_recommended.html | ?? | products.php renderWizStep2 (~8022+) |
| P8_studio_main.html | ?? | ai-studio.php (standalone) |
| P8b_studio_modal.html ×5 | ?? | partials/ai-studio-modal.php (НОВ) |
| P8c_studio_queue.html | ?? | partials/ai-studio-queue-overlay.php (НОВ) |
| P9_print.html | ?? | products.php Step 6 print preview (~7615+) |
| **P10_lesny_mode.html** | 1354 | **life-board.php REPLACE** (Weather Forecast + AI Studio row + ops горе) |
| **P11_detailed_mode.html** | 1577 | **chat.php REPLACE** (filter pills + 12 signals + bottom nav) |

⚠️ **P1 (Empty state)** не направен от Опус. Питай дали трябва.

### 1.4 Documentation в repo

**Bible & process:**
- `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` — Bible v4.1 (2748 реда), single source of truth
- `CLAUDE_CODE_DESIGN_PROMPT.md` — задължителен wrapper за дизайн задачи (92 реда)
- `MASTER_COMPASS.md` — orchestrator
- `STATE_OF_THE_PROJECT.md` — STALE (03.05 EOD), нужен update
- `PRIORITY_TODAY.md` — STALE (04.05), нужен update
- `SHEF_RESTORE_PROMPT.md` v2.4 — replace с v3.0

**Mockup handoffs (08.05):**
- `HANDOFF_CONSOLIDATED.md` — old handoff (P2-P6 + decisions)
- `HANDOFF_P4_P4b_FIND_COPY.md` — P4 + P4b + Find&Copy logic + 3 нови AJAX
- `SESSION_HANDOFF_CONSOLIDATED.md` — newest (12 mockups + 3 docs, by Опус 08.05)
- `DETAILED_MODE_DECISION.md` — защо detailed mode се запазва (4 аргумента)
- `AI_STUDIO_LOGIC_DELTA.md` — промени в AI Studio (3-екранен flow, bulk safe-only) — **дa се verify че е в repo**

### 1.5 Audit reports в /tmp/ (НЕ в repo, READ-ONLY генерирани)

```
/tmp/aibrain_audit/    (S114, 6 files, 30 min)
  Critical bug: pill class-name mismatch (aibrain-pill vs .ai-brain-pill)
  ai_brain_queue table вече съществува, no producers
  4 deliveries triggers spec-нати (RWQ #81)

/tmp/perf_audit/       (S115, 5 files)
  10 P0 slow queries (correlated subqueries 300-1500ms)
  
/tmp/sec_audit/        (S116, 8 files)
  CRITICAL: dev-exec.php RCE → ✅ QUARANTINED 91b04c4
  HIGH: 11/12 POST endpoints без CSRF
  HIGH: Session fixation → ✅ FIXED 50a7451
  MEDIUM: 12 stored XSS

/tmp/i18n_audit/       (S117, 6 files)
  t() функция не съществува (0% i18n readiness)
  50+ hardcoded BG strings
  60+ hardcoded date formats
  POST-BETA priority

/tmp/csrf_fix/         (S118, 12 files — 11 patches + HANDOFF)
  Ready to apply, batch A/B/C/D order

/tmp/perf_fix/         (S120, 6 files — 4 patches + INDEXES.sql + HANDOFF)
  Ready to apply, 6-stage order
  Estimated 600-1500ms peeled от worst-case
```

⚠️ Тези файлове са в /tmp/ и **изтриват се при reboot**. Преди reboot — копирай ги в `handoffs/audits/`.

---

## 2 · DECISIONS made — финални, не re-solve

### 2.1 Code Code use policy

| File type | Code Code OK? | Why |
|---|---|---|
| products.php (16K LOC) | ❌ НИКОГА | S104/S105/S113 proven disaster (3 опита) |
| life-board.php (~1K LOC) | ✅ С ULTRA-STRICT | Малък, изолиран |
| chat.php (~1.5K LOC) | ✅ С ULTRA-STRICT | Среден, изолиран |
| ai-studio.php (~1K LOC) | ✅ С ULTRA-STRICT | Среден |
| Нови partials (P8b, P8c) | ✅ ОК | Нови файлове, no risk |
| Backend (AJAX endpoints) | ✅ ОК | Малки isolated changes |
| Audit/draft sessions | ✅ ОК | Read-only, output в /tmp/ |
| DB migrations | ✅ ОК (sandbox first) | Apply от Тихол manual production |

### 2.2 Mockup → Production rewrite policy

**ЖЕЛЕЗНИ ПРАВИЛА за Code Code когато прави visual rewrite:**

1. **Mockup = ground truth, не reference.** Никаква интерпретация.
2. **DELETE + INSERT, не MERGE.** Локализирай target секция → DELETE целия HTML/CSS блок → INSERT mockup body innerHTML 1:1.
3. **Запази JS handlers + PHP backend INTACT** (те са извън target секция).
4. **Mockup ID-та = production DOM ID-та** (handlers wired към същите IDs).
5. **6 backups** (session-start, mid-css, mid-html, mid-js, pre-commit, post-test).
6. **Visual smoke test от Z Flip6** преди commit (ти manual).
7. **Compliance pre-commit hook = 0 errors** (без --no-verify никога — Standing Rule #30).
8. **1 файл = 1 сесия** (никога 2 файла batch).

### 2.3 Sequential rewrite plan (не паралел!)

```
Ден 1 (днес 08.05): life-board.php pilot (P10) — proof of concept
Ден 2 (09.05):       chat.php (P11)
Ден 3 (10.05):       ai-studio.php (P8) + partials/* (P8b, P8c)
Ден 4-5 (11-12.05):  products.php scrHome + scrProducts (P2 + P3) — РЪЧНО, не Code Code
Ден 6 (13.05):       products.php wizard (P4-P7+P9) — РЪЧНО
Ден 7 (14.05):       Smoke test + final polish + apply audit fixes (CSRF + perf)
14-15.05:            BETA LAUNCH ENI 🚀
```

### 2.4 Audits to apply (post-mockup, before beta)

| Audit | Status | When apply |
|---|---|---|
| S116.SEC dev-exec.php | ✅ DONE 91b04c4 | — |
| S119.SESSION_FIX | ✅ DONE 50a7451 | — |
| S118.CSRF (11 endpoints) | 📋 Ready /tmp/csrf_fix/ | Ден 7 (13.05) batch |
| S120.PERF (4 patches + indexes) | 📋 Ready /tmp/perf_fix/ | Ден 7 (13.05) off-peak |
| S114.AIBRAIN pill class fix | 📋 Ready /tmp/aibrain_audit/ | Ден 6 (12.05) с chat.php |
| S117.i18n | 📋 POST-BETA (15-20 days) | Post 15.05 |

---

## 3 · STANDING RULES — нови от тази сесия (#31-34)

**Add тези в COMPASS Standing Rules section:**

### #31 — products.php е FORBIDDEN ZONE за Code Code rewrite
S104+S105+S113 = 3 disaster sessions. 16K LOC monolith не е safe за automated rewrite. **РЪЧЕН assembly only** за products.php визуален rewrite. Code Code OK САМО за backend AJAX endpoints или малки isolated patches.

### #32 — Mockup = ground truth, DELETE+INSERT not MERGE
Когато Code Code прави визуален rewrite срещу одобрен mockup:
- DELETE целия target HTML/CSS блок първо
- INSERT mockup 1:1 (body innerHTML, не header/footer-те които idват от partials/)
- НИКАКВА интерпретация, оптимизация, "preserve old"
- Prompt-ът трябва EXPLICIT: "ZERO MERGE. ZERO PRESERVATION."

### #33 — Sequential pilot pattern за visual rewrite
Никога не пускай 2+ Code Code сесии паралелно за visual rewrite на различни modules. Ред:
1. Pilot 1 файл (smallest, isolated) → review → ако OK template установен
2. Sequential per file: 1 файл = 1 ден = 1 commit
3. Backup на всеки → ако счупим 1, revert е 30 секунди
4. Никога batch commit на 2+ файла

### #34 — Iron Law gate (proposed, not yet implemented)
Pre-commit hook трябва да проверява **6 layers** (current = 1 layer design only):
1. Design compliance (current check-compliance.sh) ✅ active
2. PHP syntax (php -l) ✅ active
3. Backend integrity (required handlers, AJAX cases, DOM IDs)
4. HTTP smoke test (curl 200/302)
5. Mockup conformance (structural diff: count of .lb-card / .shine / .glow / .glass / .fc-pill)
6. DB schema canonical (no products.sku, no inventory.qty, no status='cancelled')

Implementation: design-kit/iron-law.sh (TODO).

---

## 4 · Open questions от Опус mockups (10)

Pending Тихол отговор. Всички документирани в `HANDOFF_CONSOLIDATED.md`:

1. AI Studio entry point: Step 5 ИЛИ post-creation от life-board nudge?
2. Color → hex map: партиал `partials/color-map.php` спецификация?
3. Експорт format (P3 drawer): CSV само ИЛИ CSV + XLSX? UTF-8 BOM?
4. "Печатай всички" ред: код / цвят-размер / DB?
5. Health bar threshold: 80/50 ОК?
6. **Find&Copy tap behavior:** мигом + Toast undo (5s) ИЛИ preview confirm?
7. **Filter "Същ доставчик/категория":** context-aware (от current S.wizData) ИЛИ global?
8. **Empty state в Find&Copy drawer когато 0 history:** info card?
9. **Voice search behavior в drawer:** BG → Web Speech, други → Whisper Groq?
10. **P1 (Empty state) mockup нужен ли е?** Опус не го направи.

---

## 5 · Next session checklist (старт)

При отваряне на нов шеф-чат, **start protocol:**

1. **Read** (full read no skim):
   - SHEF_RESTORE_PROMPT.md v3.0 (когато се запише)
   - MASTER_COMPASS.md (с new LOGIC LOG entry за 08.05)
   - STATE_OF_THE_PROJECT.md (с new EOD update)
   - PRIORITY_TODAY.md (за 09.05)
   - **SESSION_HANDOFF_FOR_NEXT_SHEF.md** (този файл)
   - SESSION_HANDOFF_CONSOLIDATED.md (Опус)
   - последен active HANDOFF_*.md

2. **Verify state:**
   ```bash
   git log --oneline -15
   ls mockups/P*.html  # очаквам 12 файла
   ls /tmp/{csrf_fix,perf_fix,aibrain_audit,sec_audit,perf_audit,i18n_audit}/HANDOFF.md 2>/dev/null
   curl -sk -o /dev/null -w "%{http_code}\n" https://runmystore.ai/login.php
   curl -sk -I https://runmystore.ai/login.php 2>/dev/null | grep -i secure
   ```

3. **Phase 0.5 INVENTORY GATE** — extract P0 от 5 sources (PRIORITY/COMPASS/STATE/REWORK/STRESS)

4. **IQ test 16 questions** (виж SHEF_RESTORE_PROMPT v3.0)

5. **Status report** със:
   - Прочетени docs ✓
   - INVENTORY P0 (deduplicated)
   - IQ score
   - Status (Phase, ENI countdown, latest commit)
   - Top 3 priority (derived from inventory)
   - Blockers
   - Чакам команда

---

## 6 · Ако нещо се счупи — emergency protocol

| Проблем | Първа стъпка | Команда |
|---|---|---|
| Сайтът върна 500 | Check Apache logs | `tail -50 /var/log/apache2/error.log` |
| Counter-test от Z Flip6 показва regression | Revert последен commit | `git revert HEAD && git push origin main` |
| Code Code сесия se confused | STOP сесия + handoff | `tmux kill-session -t <name>` |
| Pre-commit hook fail | Check което error | `bash design-kit/check-compliance.sh <file>` |
| Auth 401 от Code Code | Login refresh | (като tihol user, не root): `claude /login` |
| Push fails (no credentials) | Switch към root | `exit; git push origin main` |
| dev-exec.php още достъпен | Verify quarantine | `ls -la dev-exec.php* && curl -sk https://runmystore.ai/dev-exec.php` |

---

## 7 · Какво НЕ е свършено (carry-over)

### 7.1 Mockup pending
- [ ] P1 Empty state (не направен)
- [ ] AI_STUDIO_LOGIC_DELTA.md verify че е в repo
- [ ] P9 Print preview — final review

### 7.2 Production rewrite (по приоритет)
- [ ] life-board.php → P10 (PILOT, ден 1)
- [ ] chat.php → P11 (ден 2)
- [ ] ai-studio.php → P8 (ден 3)
- [ ] partials/ai-studio-modal.php → P8b (ден 3)
- [ ] partials/ai-studio-queue-overlay.php → P8c (ден 3)
- [ ] products.php → P2-P9 (РЪЧНО, дни 4-6)

### 7.3 Audits to apply (ден 7, batch)
- [ ] S118 CSRF: 11 patches batch A/B/C/D
- [ ] S120 PERF: indexes (off-peak) → 4 query patches incremental
- [ ] S114 AIBRAIN pill class fix

### 7.4 Documentation update needed
- [ ] STATE_OF_THE_PROJECT.md → fresh truth (08.05 EOD)
- [ ] PRIORITY_TODAY.md → 09.05.2026 (петък)
- [ ] MASTER_COMPASS.md → нов LOGIC LOG 08.05 EOD entry
- [ ] SHEF_RESTORE_PROMPT.md → v3.0 (replace v2.4)
- [ ] Standing Rules #31-34 → COMPASS

### 7.5 Post-beta (НЕ преди 15.05)
- [ ] Marketing schema apply на live DB (sandbox first)
- [ ] AI Brain Phase 2 (queue producers + worker)
- [ ] AI Brain в deliveries.php (RWQ #81 — voice OCR + defective detection)
- [ ] Promotions module
- [ ] i18n framework (15-20 days)
- [ ] dev-exec.php cleanup retention (+ delete .QUARANTINED file post 30 days)
- [ ] Rotate API keys (Gemini + Groq + fal + Ecwid)
- [ ] kernel reboot 6.8.0-111-generic (post-beta)

---

## 8 · Critical contacts & tools

| What | Where |
|---|---|
| Production droplet | 164.90.217.120 (DigitalOcean Frankfurt) |
| Repo | github.com/tiholenev-tech/runmystore (public) |
| Path | /var/www/runmystore/ |
| DB credentials | /etc/runmystore/db.env (chmod 640, owner www-data) |
| API keys | /etc/runmystore/api.env (chmod 640) |
| GitHub access from sandbox | github.com/blob/main/<FILE>?plain=1 → parse rawLines (raw blocked) |
| Test device | Samsung Z Flip6 (~373px viewport) |
| Apache vhost | runmystore.ai (HTTPS via Let's Encrypt) |
| Mirror auto-sync | Cron на www-data, every few min |

---

**КРАЙ.** Ако се загубите при start — върнете се към Section 0 (TL;DR).
