# Phase 2 — AI Brain Data Flow Mapping

**Session:** S114.AIBRAIN_AUDIT
**Reference:** `compute-insights.php`, `cron-insights.php`, `life-board.php:108-172`, `partials/ai-brain-pill.php`, `ai-brain-record.php`, `aibrain-modal-actions.php`, `mark-insight-shown.php`, STRESS_BOARD.md ГРАФА 3.

---

## 1. Current production flow (sequence)

```
┌────────────┐
│ cron host  │  every 15min (heartbeat: expected_interval_minutes=15)
└─────┬──────┘
      │ /usr/bin/php /var/www/runmystore/cron-insights.php
      ▼
┌──────────────────────────────────────────────────────────────────┐
│ cron-insights.php (120 LOC)                                       │
│                                                                    │
│   foreach tenant in active+plan(start|pro|trial):                  │
│      computeProductInsights(tenant_id)   ← compute-insights.php    │
│      log heartbeat, audit_log summary                              │
└─────┬────────────────────────────────────────────────────────────┘
      │
      ▼
┌──────────────────────────────────────────────────────────────────┐
│ compute-insights.php (1745 LOC)                                   │
│                                                                    │
│   ~30 pf*() probe functions:                                       │
│     pfZeroStockWithSales, pfBelowMinUrgent, pfRunningOutToday,    │
│     pfSellingAtLoss, pfNoCostPrice, pfMarginBelow15,              │
│     pfSellerDiscountKiller, pfTopProfit30d, pfProfitGrowth, …      │
│                                                                    │
│   each probe → pfUpsert(tenant_id, $insight_array)                 │
│                                                                    │
│   pfUpsert():                                                      │
│     INSERT INTO ai_insights (...) VALUES (...)                     │
│     ON DUPLICATE KEY UPDATE                                        │
│       title=VALUES(title), urgency=VALUES(urgency), …,             │
│       expires_at=VALUES(expires_at), created_at=NOW()              │
│                                                                    │
│     UNIQUE KEY: (tenant_id, store_id, topic_id)                    │
└─────┬────────────────────────────────────────────────────────────┘
      │
      ▼
┌──────────────────────────────────────────────────────────────────┐
│ ai_insights table (existing data — DO NOT TOUCH)                  │
│   topic_id namespace ≈ 30 fixed strings                           │
│   row count per (tenant, store) ≤ ~30 (one per topic_id)          │
│   expires_at gates active rows; created_at refreshed each cron     │
└─────┬────────────────────────────────────────────────────────────┘
      │
      ▼  (read on every life-board.php pageload)
┌──────────────────────────────────────────────────────────────────┐
│ life-board.php:108-172                                            │
│                                                                    │
│   IF plan ≥ pro:                                                   │
│     getInsightsForModule(tenant, store, user, 'home', plan, role)  │
│       → SELECT * FROM ai_insights                                  │
│         WHERE tenant_id=? AND expires_at > NOW()                   │
│         AND role_gate matches AND plan_gate matches                │
│         AND NOT EXISTS (ai_shown last 6h cooldown)                 │
│                                                                    │
│   bucket by fundamental_question (loss/gain/order/…)                │
│   pick 4 cards (loss-heavy: 2× loss + 1× gain + 1× order)          │
│                                                                    │
│   render top section (insight cards)                               │
│   render ops grid (4 buttons)                                      │
│   include partials/ai-brain-pill.php  ← line 1457                  │
└─────┬────────────────────────────────────────────────────────────┘
      │
      ▼  (user tap)
┌──────────────────────────────────────────────────────────────────┐
│ partials/ai-brain-pill.php → onclick="window.aibrainOpen()"        │
│   → partials/voice-overlay.php opens (REC pulse + textarea)        │
│   → user speaks (Web Speech API, bg-BG)                            │
│   → POST /ai-brain-record.php  { csrf, text, source }              │
└─────┬────────────────────────────────────────────────────────────┘
      │
      ▼
┌──────────────────────────────────────────────────────────────────┐
│ ai-brain-record.php (153 LOC) — Phase 1 REACTIVE only              │
│                                                                    │
│   1. session auth (user_id, tenant_id ≥ 1)                         │
│   2. CSRF (body + X-AI-Brain-CSRF header, hash_equals)             │
│   3. text validation (mb_substr 2000)                              │
│   4. cURL loopback POST → /chat-send.php (same session cookie)     │
│   5. parse reply, return { reply, source, phase: 1 }               │
└─────┬────────────────────────────────────────────────────────────┘
      │
      ▼
┌──────────────────────────────────────────────────────────────────┐
│ chat-send.php (existing — Gemini/Claude/etc. via build-prompt.php) │
│   returns AI reply                                                 │
└─────┬────────────────────────────────────────────────────────────┘
      │
      ▼  (back to overlay; AI speaks reply via TTS)
   user closes overlay
```

### Side-channel: insight tap (NOT through pill)

```
life-board insight card tap
  → POST /mark-insight-shown.php { topic_id, action, category, product_id }
  → INSERT INTO ai_shown (tenant_id, user_id, store_id, topic_id, …, action='tapped')
  → 6h cooldown enforced via NOT EXISTS in life-board insights query
```

### Side-channel: insight modal action (order draft / transfer draft / dismiss)

```
modal opens (frontend) → POST /aibrain-modal-actions.php?action=…
  → CSRF check (per-session token from $_SESSION['aibrain_csrf'])
  → handleOrderDraft / handleTransferDraft / handleDismiss
    - INSERT purchase_orders + items (status='draft')
    - INSERT transfers + items (status='pending', note='AI draft')
    - UPDATE ai_insights SET expires_at=NOW(), action_data={dismissed_at, …}
      + INSERT ai_shown (action='dismissed')
```

---

## 2. Bottleneck analysis

### 2.1 ai_insights UNIQUE on (tenant_id, store_id, topic_id) is **by design**

STRESS_BOARD.md ГРАФА 3 P1 entry frames this as a problem ("блокира нови записи, само UPDATE на съществуващи — броят сигнали остава фиксиран независимо колко пъти cron работи"), but the comment block in `compute-insights.php:241-251` makes it explicit:

> "Преди: SELECT-then-UPDATE-or-INSERT — две туристически query-та + race window…
> Сега: единично statement, idempotent чрез UNIQUE (tenant_id, store_id, topic_id)."

**This is intentional idempotency, not a bug.** The probe functions emit deterministic `topic_id` strings (e.g. `'zero_stock_with_sales'`, `'below_min_urgent'`, `'top_profit_30d'`). The same probe → same topic_id → row collapses on UNIQUE → UPSERT semantics.

**The real constraint:** `topic_id` is a finite namespace (~30 strings). To add a new signal type you must mint a new `topic_id` string — and the ENUM-like dispatch in `pfCategoryFor()` (compute-insights.php:84) needs an entry, plus `pfPlanGateFor()`, `pfRoleGateFor()`, `pfDefaultAction()`. **Adding signals = code change, not data change.**

**Implication for Phase 2 queue:** `ai_brain_queue` items are NOT subject to this constraint — they're append-only with TTL. Queue can carry **per-product, per-event** rows (e.g. one row per defective delivery line) which `ai_insights` cannot.

### 2.2 cron-insights.php cadence vs heartbeat mismatch

- File header / behavior: code uses `$job_name = 'compute_insights_15min'` and writes heartbeat with `expected_interval_minutes=15`.
- Bible §4.5.3 says queue TTL cron runs at 03:00 daily.
- Insights cron is the right cadence for product-level aggregates (15 min granularity matches "running_out_today" probes). No issue here.

**Latency points:**
- `computeProductInsights(tenant)` runs ~30 probes per tenant. Each probe is 1-2 SELECT/JOIN against `products`, `inventory`, `sales`, `sale_items`, `purchase_orders`, `transfers`. With Pro tenant = ~5-10k SKUs, full sweep ≈ **2-5 sec/tenant** (rough estimate; needs profiling).
- N tenants serial → 15 min cron supports ~150-450 tenants before hitting cron tail. Stop signal: heartbeat `last_duration_ms` exceeds 600000ms.

### 2.3 life-board.php read latency

- `getInsightsForModule()` runs one SELECT with `tenant_id` index + `expires_at > NOW()` filter + role_gate + plan_gate + 6h NOT EXISTS join on `ai_shown`. Should be < 50ms for any tenant (≤30 candidate rows per (tenant, store)).
- Bucket-by-FQ + pick-4 logic is in-memory PHP, O(N) ≤ 30 → trivial.
- Pill include adds ~4KB inline CSS + voice-overlay partial — first-load only.

**No latency bottleneck on the read path.** Pill render is essentially free.

### 2.4 ai-brain-record.php loopback overhead

- Server-side cURL → `/chat-send.php` → session_start (already started in record.php and chat-send.php both call session_start).
- Session lock contention: PHP default file-based session locks. The loopback **blocks** on the same session lock as the originating request. **Practical impact:** if user navigates away mid-AI-response, the session is held for the full chat-send.php duration (≤30 sec timeout in record.php).
- ~5-15ms overhead vs. direct integration (per S92 handoff).

**Phase 2 fix (S92 handoff suggests):** refactor to call `build-prompt.php` + Gemini directly inside `ai-brain-record.php`, dropping the loopback. Saves ~15ms and eliminates the session-lock double-grab.

### 2.5 Known bugs (from STRESS_BOARD ГРАФА 3 + S92 handoff)

| # | Severity | Component | Issue |
|---|---|---|---|
| 1 | P1 | `ai_insights` | Mis-framed as "UNIQUE blocking" — actually intentional idempotency. **Recommendation:** edit STRESS_BOARD entry to clarify it's by-design, move discussion of "richer signal volume" to ai_brain_queue capacity (Phase 2). |
| 2 | P2 | `ai-brain-record.php` | Session lock held during loopback (~15ms blocking on same session). Phase 2 refactor planned. |
| 3 | P2 | check-compliance.sh | Rule 1.3 `box-shadow` regex misses `hsl(...)` shadows. Affects pill audit accuracy. |
| 4 | P3 | `partials/ai-brain-pill.php` | Class-name mismatch with `life-board.php` Effect #9 CSS (see 01_pill_compliance.md §2.8). |
| 5 | P2 | `ai_brain_queue` | Table exists but **no producer writes to it** in Phase 1; **no reader** consumes it; **no TTL cron** runs. Phase 2 work item. |

---

## 3. Data-flow gaps for Phase 2 (proactive)

The current pipeline supports **only Reactive** (Pешо taps → AI replies). For Proactive (`ai_brain_queue`):

| Gap | Component to add |
|---|---|
| Producer for queue items | `compute-insights.php` extension OR new `services/ai-brain-queue-producer.php` |
| Reader endpoint for active queue | `ai-brain-fetch.php` (GET, returns rows where `status='pending' AND (snooze_until IS NULL OR snooze_until ≤ NOW())` ordered by priority desc) |
| Action endpoint for select/skip/later | extend `ai-brain-record.php` with `intent` field |
| TTL + escalation cron | `cron/ai_brain_ttl.php` daily 03:00 |
| Pulse rate UI hook | `partials/ai-brain-pill.php` JS reads queue count, sets CSS variable for pulse cadence |
| "AI speaks first" path | `partials/voice-overlay.php` auto-TTS on open when queue non-empty |

Per S92 handoff (RWQ #53), all of the above are **anticipated for S116.AIBRAIN_QUEUE_BUILD**.

---

## 4. Summary

**Today (Phase 1, S92 done):** Reactive-only; pill → loopback to chat-send → reply. `ai_insights` table is the only signal source, displayed on life-board top section. `ai_brain_queue` table exists but unused.

**Bottleneck reality check:** The flow is **fundamentally sound** for Phase 1. No dataflow blockers. The "ai_insights UNIQUE blocks INSERT" framing in STRESS_BOARD is misleading — that's intentional idempotency. The actual Phase 2 readiness gap is the **absence of a producer** for `ai_brain_queue`, not a problem with `ai_insights`.

**Critical next step:** Phase 3 (this audit) drafts a producer-friendly schema enhancement for `ai_brain_queue`. Phase 4 wires concrete producers (deliveries triggers).
