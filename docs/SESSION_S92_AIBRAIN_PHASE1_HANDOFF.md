# S92.AIBRAIN.PHASE1 HANDOFF — 02.05.2026

**Status:** ✅ DONE
**Scope:** Reactive behavior only (BIBLE 4.5.2.A) — pill под 4-те ops бутона на life-board → voice overlay → loopback към chat-send.php.
**Phase 2 (proactive queue + escalation) и Phase 3 (mini-FAB в Simple Mode модули) → REWORK QUEUE #53 / #54.**

---

## Commits (origin/main)

| Hash | Message |
|---|---|
| `dca672b` | S92.AIBRAIN.PHASE1.01: ai_brain_queue migrations + i18n shim |
| `4126b2e` | S92.AIBRAIN.PHASE1.02: voice overlay + pill partials + record stub |
| `2c3cb4d` | S92.AIBRAIN.PHASE1.03: include AI Brain pill in life-board |
| `8f8d49c` | S92.AIBRAIN.PHASE1.04: STATE_OF_THE_PROJECT note for AI Brain pill |

HEAD = `8f8d49c` verified pushed по Тихол.

---

## Files changed (7)

**New (6):**
- `migrations/s92_aibrain_up.sql` — `CREATE TABLE IF NOT EXISTS ai_brain_queue` per BIBLE 4.5.3 (13 cols + 2 indexes + 6-value `type` ENUM + 4-value `status` ENUM); `INSERT IGNORE INTO schema_migrations` for tracking despite non-standard filename.
- `migrations/s92_aibrain_down.sql` — `DROP TABLE IF EXISTS` + `DELETE FROM schema_migrations WHERE version='s92_aibrain'`. Round-trip safe.
- `config/i18n_aibrain.php` — `t_aibrain($key, $fallback)` shim with AI Brain UI strings; `aibrain_csrf_token()` per-session token. Owns ONLY AI Brain copy → no collision with concurrent module work.
- `partials/voice-overlay.php` — floating bottom rec-box, `backdrop-filter: blur(8px)`, REC pulse (red recording → green ready → magenta thinking), readonly textarea (no native keyboard), Web Speech API for `bg-BG` STT with graceful fallback. `.aibrain-*` scoped classes — no collision with sale.php's `.rec-ov`. Exposes `window.aibrainOpen()` / `window.aibrainClose()`.
- `partials/ai-brain-pill.php` — `~80×44px` q-magic pill (hue 280/310 per BIBLE) using existing `.glass + .shine + .glow` from life-board.php. Includes `voice-overlay.php` once (idempotent guard). Conditional `42×42px` mini-FAB version (`$aibrain_mode = 'fab'`) ready for Phase 3.
- `ai-brain-record.php` — POST endpoint. Validates session `user_id`+`tenant_id`, double-CSRF (body + `X-AI-Brain-CSRF` header, `hash_equals`). Loopback POST to `/chat-send.php` carrying session cookie → returns `{ reply, source, phase }`.

**Modified (1):**
- `life-board.php` — single-line `<?php include __DIR__ . '/partials/ai-brain-pill.php'; ?>` between `.ops-grid` and `.studio-row-bottom` (line 589). Nothing else touched.

---

## DB changes

- `ai_brain_queue` CREATED on live DB (Тихол applied).
- 8-step migration safety NOT executed by Claude (tihol user has no read on `/etc/runmystore/db.env`). Manual apply by Тихол verified.

Schema highlights:
- `priority TINYINT DEFAULT 50` (1-100)
- `ttl_hours INT DEFAULT 48` (cron at 03:00 will auto-dismiss; cron not yet wired)
- `escalation_level TINYINT DEFAULT 0` (0/1/2 → drives pulse rate on the pill)
- 2 indexes: `(tenant_id, user_id, status, priority)` for fetch-by-user and `(created_at, ttl_hours)` for the future TTL cron.

---

## DOD scorecard

| Item | Status | Note |
|---|---|---|
| L4 commits (3-5) | ✅ | 4 commits, all `S92.AIBRAIN.PHASE1.NN:` format |
| `ai_brain_queue` table EXISTS on live | ✅ | Applied by Тихол |
| `partials/voice-overlay.php` opens at pill tap | ✅ | `window.aibrainOpen()` wired to pill `onclick`; browser test by Тихол confirms |
| `partials/ai-brain-pill.php` in DOM via include | ✅ | `life-board.php:589` include verified |
| 0 native `prompt()` / `alert()` / `confirm()` | ✅ | grep verified across all new files |
| 0 hardcoded `"лв"` / `"€"` | ✅ | grep verified |
| 0 hardcoded BG strings outside `t_aibrain()` | ✅ | All copy lives in `config/i18n_aibrain.php` |
| `design-kit/check-compliance.sh` PASS | ⚠ N/A | Script designed for full modules (`<html>`, `<body>`, header/bottom-nav partials). Partials and backend endpoints fail trivially on those structural checks. Visual compliance achieved via reuse of life-board.php's `.glass/.shine/.glow` and q-magic hue 280/310 per BIBLE. life-board.php itself has 14 pre-existing violations — none added. |
| PHP lint | ✅ | All 5 new/edited PHP files clean |

---

## REWORK QUEUE additions

### #53 — S92.AIBRAIN.PHASE2: Proactive queue + escalation
**Spec:** BIBLE 4.5.2.B + 4.5.3
- Read `ai_brain_queue` rows where `status='pending' AND (snooze_until IS NULL OR snooze_until <= NOW())` ordered by `priority DESC, created_at ASC`.
- Pulse rate on pill: 1/2.5s idle → 2/2.5s when queue ≥1 → 3/2.5s when queue ≥3 (escalation).
- Voice phrasing per BIBLE 4.5.2.B: AI speaks first without user input — "Имаш N неща за днес: 1) … 2) … 3) …".
- User voice intents:
  - "първото"/"второто"/"третото" → open relevant module with `action_data` context.
  - "пропусни" → `priority -= 20` (lower-bound 1), don't surface again same day.
  - "после" → `snooze_until = NOW() + INTERVAL 2 HOUR`, reset escalation TTL.
- Cron at 03:00 daily: `UPDATE ai_brain_queue SET status='dismissed' WHERE status='pending' AND created_at + INTERVAL ttl_hours HOUR < NOW();` and escalation bumps (24h → level=1, 48h → level=2 + mirror to life-board insights).
- TTL per type: default 48h, `stock_alert`=24h, `review_check`=168h.
- 3+ items at level=2 → strong nudge: "Имаш N неща които не сме обсъдили от 2 дни. Сега ли?"
- `ai_brain_record.php` extends to support `intent` field (`select`, `skip`, `later`) for queue actions.
- Insight → queue pipeline: `compute-insights.php` writes to `ai_brain_queue` for items needing conversation (does NOT duplicate as life-board insight).

**Files (anticipated):**
- `ai-brain-record.php` — extend with `intent` handling.
- `ai-brain-fetch.php` — new GET endpoint returning the active queue for a user.
- `cron/ai_brain_ttl.php` — new daily cron at 03:00.
- `compute-insights.php` — additive write to `ai_brain_queue` for conversational items.
- `partials/ai-brain-pill.php` — JS reads queue count → adjusts CSS variable for pulse rate.
- `partials/voice-overlay.php` — handle the proactive "AI speaks first" path (auto TTS on open when queue non-empty).

### #54 — S92.AIBRAIN.PHASE3: Conversational mini-FAB in Simple Mode modules
**Spec:** BIBLE 4.5.2.C
- Mini-FAB (`$aibrain_mode='fab'`) is already coded in `partials/ai-brain-pill.php` — Phase 3 is purely the wiring.
- Include with `<?php $aibrain_mode='fab'; include __DIR__ . '/partials/ai-brain-pill.php'; ?>` in:
  - `sale.php` (`?mode=simple`)
  - `products.php` (`?mode=simple`)
  - `delivery.php` / `deliveries.php` (Simple)
  - `orders.php` (Simple)
  - any future Simple Mode module
- Context-awareness: when overlay opens, JS attaches a `context` payload to the POST (cart contents, current product, current filter) so AI can answer with module context.
- `ai-brain-record.php` extends to accept and forward `context` to `chat-send.php` via `build-prompt.php` system prompt augmentation.
- AI must NOT navigate away from the host module (sale.php during a sale). Apply requested change inline (e.g. cart 10% discount → recompute totals → keep user in `sale.php`).
- Skip mini-FAB if AI Brain is the host (life-board.php) — pill is enough.

**Open Q for chef-chat:** position of mini-FAB in `sale.php` cam-header view (collides with cart/parked badges?) — needs visual review.

---

## Architecture notes for next chat

- **CSRF model:** per-session token in `$_SESSION['aibrain_csrf']`, validated as `hash_equals` against BOTH body field and `X-AI-Brain-CSRF` header. Token is generated lazily by `aibrain_csrf_token()` and re-used across page loads in the same session.
- **i18n shim is intentionally minimal.** When a real `t()` system arrives, the strings table in `config/i18n_aibrain.php` becomes a translations source for the `aibrain.*` namespace; rename `t_aibrain` → `t` and merge.
- **Voice STT is Web Speech API only.** No server-side STT in Phase 1. Phase 2 may add audio blob upload + Whisper if Tихол wants offline-capable Bulgarian dictation.
- **Loopback to chat-send.php** is server-side cURL with session cookie pass-through. Adds ~5-15ms vs direct integration. Phase 2 will likely refactor to call `build-prompt.php` + Gemini directly to avoid the double session_start.
- **Pill placement** is between `.ops-grid` and `.studio-row-bottom` inside `.ops-section`. AI Studio entry remains BELOW the AI Brain pill — secondary attention preserved per BIBLE 4.5.5.

---

## Time

~1.5h / 4h budget.
