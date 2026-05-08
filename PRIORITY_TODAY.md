# 🎯 PRIORITY_TODAY — 09.05.2026 (Петък)

**Beta countdown:** 5-6 дни до 14-15.05.2026 ENI launch
**Last EOD:** 08.05.2026 — design system v4.1 finalized + 12 mockups approved + audit batch ready
**Шеф-чат carry-over:** central transition в `SESSION_HANDOFF_FOR_NEXT_SHEF.md`

---

## 📋 PRE-FLIGHT (10 мин преди СЕСИЯ 1)

### Verify yesterday's commits

```bash
cd /var/www/runmystore
git log --oneline -15
# Top expected (08.05): dd23855 → 0b636db → 447c74b → 40d0203 → 50a7451 → 4c4921d
```

### Verify mockups + handoffs в repo

```bash
ls mockups/P*.html | wc -l  # очаквам 8 файла (P2, P3, P4, P4b, P5, P6, P10, P11)
ls handoffs/HANDOFF_S*.md   # история на сесии
ls SESSION_HANDOFF_*.md     # transition docs
```

### Verify сайтът е жив

```bash
curl -sk -o /dev/null -w "login: %{http_code}\n" https://runmystore.ai/login.php
curl -sk -o /dev/null -w "products: %{http_code}\n" https://runmystore.ai/products.php
curl -sk -I https://runmystore.ai/login.php 2>/dev/null | grep -i "secure\|httponly\|samesite"
# Очаквам 200/302 + Set-Cookie с secure+HttpOnly+SameSite=Strict
```

### Verify dev-exec.php quarantined

```bash
curl -sk -o /dev/null -w "dev-exec: %{http_code}\n" https://runmystore.ai/dev-exec.php
ls -la dev-exec.php* 2>/dev/null
# Очаквам 404 + dev-exec.php.QUARANTINED със chmod 000
```

### Verify TESTING_LOOP cron

```bash
crontab -l -u www-data 2>/dev/null
cat /etc/cron.d/runmystore 2>/dev/null
ls -la /var/www/runmystore/logs/
# Очаквам cron entries + logs/ директория с www-data ownership
```

### Verify audit reports запазени

```bash
ls /tmp/{csrf_fix,perf_fix,aibrain_audit,sec_audit,perf_audit,i18n_audit}/HANDOFF.md 2>/dev/null
# Ако /tmp/ е изтрит при reboot — pull от 8.05 EOD backup или regenerate
```

---

## 🥇 TOP 3 PRIORITY (derived from STATE LIVE BUG INVENTORY P0)

### #1 — life-board.php pilot rewrite по P10 (СЕСИЯ 1, 2-3ч)

**Time:** 2-3ч
**Source:** UI-REWRITE-LIFEBOARD P0 (mockups/P10_lesny_mode.html, 1354 реда)
**Code Code session:** S121.LIFEBOARD.P10_REWRITE
**Predecessor:** последен commit dd23855

**Goal:** PILOT за DELETE+INSERT pattern. Ако работи 1:1 → шаблон валиден за всички следващи rewrite-и.

**Spec:**
- Backup: cp -p life-board.php life-board.php.bak.S121_<TS>
- Open mockups/P10_lesny_mode.html
- Identify scope: вътре в life-board.php → DELETE целия `<style>` блок + `<body>` content (между `<?php require 'partials/header.php'; ?>` и `<?php require 'partials/bottom-nav.php'; ?>` ako bottom nav е там; иначе life-board.php е без bottom nav per "lesny mode правило")
- INSERT mockup `<style>` + body innerHTML 1:1 (без header/bottom-nav defs)
- Запази INTACT: PHP `<?php require 'partials/*'; ?>` includes + всички PHP variables (за store picker)
- Iron Law verify (8 layers — TODO design-kit/iron-law.sh)
- Visual smoke test от Z Flip6: light + dark двата режима OK
- Commit: "S121.LIFEBOARD: P10 BICHROMATIC rewrite (Weather + AI Studio row + ops горе)"

**Risk:**
- ⚠️ life-board.php е sacred etalon — но Тихол одобри replace (потвърдено в `SESSION_HANDOFF_CONSOLIDATED.md`)
- След rewrite — новата life-board.php става новия etalon
- Ако счупи → revert от backup, 30 секунди

**DOD:**
- ✓ php -l → 0 errors
- ✓ check-compliance.sh → exit 0
- ✓ HTTP 200/302 на /life-board.php
- ✓ Light + Dark двата режима render правилно
- ✓ Z Flip6 (~373px) responsive OK
- ✓ Theme toggle работи (sun/moon)
- ✓ Weather Forecast Card показва (3/7/14 дни segmented)
- ✓ AI Studio entry button visible
- ✓ Aurora background + brand shimmer работят

**Open Questions преди старт:**
1. Кой е default theme на life-board (light от Bible v4.1)?
2. Weather API — Open-Meteo direct или cached?
3. AI Studio entry — ВЕДНАГА ли отваря ai-studio.php или modal?

---

### #2 — chat.php pilot rewrite по P11 (СЕСИЯ 2, 2-3ч)

**Time:** 2-3ч (ако #1 OK template установен)
**Source:** UI-REWRITE-CHAT P0 (mockups/P11_detailed_mode.html, 1577 реда)
**Code Code session:** S122.CHAT.P11_REWRITE

**Spec:**
- 12 signals + filter pills + bottom nav (4 tabs: AI / Склад / Справки / Продажба)
- Без ops grid (дублира bottom nav per `DETAILED_MODE_DECISION.md`)
- 80% reuse от P10 patterns

**DOD:** Same Iron Law structure as #1.

---

### #3 — ai-studio.php + 2 partials (СЕСИЯ 3, 3-4ч)

**Time:** 3-4ч
**Source:** UI-REWRITE-AISTUDIO P0
**Code Code session:** S123.AISTUDIO.P8_REWRITE

**Files:**
- `ai-studio.php` (existing) → P8 mockup rewrite
- `partials/ai-studio-modal.php` (НОВ файл) → P8b mockup × 5 категории
- `partials/ai-studio-queue-overlay.php` (НОВ файл) → P8c mockup

**DOD:** All 3 files pass Iron Law + visual smoke test.

---

## 📋 SUCCESS CRITERIA за 09.05

✅ life-board.php (P10) rewritten + smoke test + commit + push
✅ DELETE+INSERT pattern proven (или знаем че не работи)
✅ Backup на life-board.php запазен в backups/s121_*/
✅ Beta countdown: 5-6 дни

❌ FAILURE if:
- life-board.php counter-test показва visual regression → REVERT + manual rewrite
- Code Code се обърква със scope → STOP + handoff (Standing Rule #33)

---

## 🔁 BLOCKERS / OPEN QUESTIONS

| # | Blocker | Owner | Action |
|---|---|---|---|
| 1 | P1 (Empty state) mockup не направен | Опус | Питай дали трябва за beta |
| 2 | P7/P8/P9 mockups не направени | Опус | Чакай или ползвай spec от HANDOFF_CONSOLIDATED §3.1 |
| 3 | TRACK 2 P0 specs unclear | TRACK 2 помощник | Get file:line refs за NAME_INPUT_DEAD/D12/WHOLESALE |
| 4 | Iron Law gate (6 layers) не написан | Шеф-чат | Implement design-kit/iron-law.sh |
| 5 | Marketing schema sandbox apply pending | Тихол | DB access + sandbox test ден 7 |

---

## ⚠️ CARRY-OVER FROM 08.05

### S116/S117/S115/S114 audit findings backlog

**Apply ден 7 (13.05) batch:**

S118 CSRF (11 endpoints):
- /tmp/csrf_fix/01-11.patch.php
- Apply order: Batch A (HIGH, 5 patches) → B (MEDIUM, 3) → C (LOW, 3)
- Time: ~85 min total
- Test: TEST_PLAN.md curl checks

S120 PERF (4 patches + indexes):
- /tmp/perf_fix/INDEXES.sql (3 indexes на off-peak)
- 4 query rewrites: sale-voice, sale-search, stats, products_fetch
- Apply order: indexes → patch 01 → 02 → 03 → 04 sites 1-6 incremental
- Estimated impact: 600-1500ms peeled от worst-case

S114 AIBRAIN pill class fix:
- partials/ai-brain-pill.php
- Change `class="aibrain-pill"` → `class="ai-brain-pill"` (с тире, per Bible)
- Restores Effect #9 shimmer in dark mode

S117 i18n: POST-BETA (15-20 days framework + migration + translation)

---

## 🔐 SECURITY HARDENING DONE 08.05

✅ S116 dev-exec.php QUARANTINED (commit 91b04c4)
✅ S119 session_regenerate_id + secure cookies (commit 50a7451)
✅ HTTP smoke test confirms: Set-Cookie: PHPSESSID=...; secure; HttpOnly; SameSite=Strict

**Pending (post-beta):**
- Rotate API keys (Gemini + Groq + fal + Ecwid) — assume leaked през dev-exec window
- 12 stored XSS findings (htmlspecialchars batch)
- 2FA implementation
- delivery.php OCR upload MIME validation

---

PENDING REBOOT: kernel 6.8.0-111-generic — изпълни само при чиста сесия (tmux ls празно + git clean) + СЛЕД beta launch (15.05+)

---

**Beta deadline:** 14-15.05.2026 — **5-6 дни остават.** Не отлагай rewrite work.
