# S114.AIBRAIN_AUDIT — Final Handoff

**Date:** 2026-05-08
**Author:** Claude Opus 4.7 (1M context) — audit-only session
**Time spent:** ~2.5 h / 4 h budget
**Output location:** `/tmp/aibrain_audit/` (5 markdown + 1 SQL draft)
**Commits / pushes / DB writes:** **NONE.** Pure audit.

---

## 0. Executive summary (3 paragraphs)

The AI Brain Phase 1 (Reactive) is **production-functional** as of S92.AIBRAIN.PHASE1 (committed 2026-05-02). The pill renders under the 4 ops buttons in `life-board.php`, taps open `partials/voice-overlay.php`, voice → `ai-brain-record.php` loops back to `chat-send.php`, AI replies. `ai_brain_queue` table EXISTS on prod (created s92) but is **unused** — no producer writes, no reader fetches, no TTL cron. Phase 2 turns it on; this audit is the design-readiness package.

The audit surfaced **1 critical UI bug** (class-name mismatch — Bible v4.1 Effect #9 CSS in `life-board.php:1022-1080` is dead code because the partial renders `.aibrain-pill` not `.ai-brain-pill`), **5 manual Bible v4.1 violations** in the pill (hardcoded hsl literals, missing `[data-theme="light"]` branch, missing iridescent shimmer), and **1 mis-framed STRESS_BOARD entry** (the "ai_insights UNIQUE blocks INSERT" comment misreads intentional UPSERT idempotency as a bug). The auto compliance script reports only 2 trivial warnings — its regexes don't catch the deeper issues.

The recommended next sessions are **S115.AIBRAIN_PILL_MIGRATE** (45 min — pure CSS, no logic), **S116.AIBRAIN_QUEUE_BUILD** (worker infrastructure, ~6 h, depends on draft schema in this audit), and **S117.DELIVERIES_AIBRAIN** (4 triggers wired into `delivery.php`, ~5 h, depends on S98.DELIVERIES + S116). Cost projection at 200 tenants is ~€12/month vendor spend (Whisper + Gemini Vision), trivial. Single biggest cost lever is whether AI-refined reorder suggestions are default-on (they should not be).

---

## 1. Phase 1 — Pill compliance findings

**Source file:** `partials/ai-brain-pill.php` (105 LOC) — **read-only**, NOT modified.
**Detail:** [`/tmp/aibrain_audit/01_pill_compliance.md`](./01_pill_compliance.md)

| Severity | Count | Gist |
|---|---|---|
| ❌ ERROR | 0 | Auto script reports zero blocking errors |
| ⚠ WARN (script) | 2 | Legacy `s87v3-tap` class, lines 23 + 37 |
| 🟡 Bible v4.1 manual | 5 | 28× hardcoded `hsl(...)`, hardcoded `box-shadow`, no `[data-theme="light"]` branch, missing Effect #9 shimmer (`::before` conic + `::after` slide), `s87v3-tap` legacy |
| 🚨 CRITICAL | 1 | `life-board.php:1022-1080` already has Bible-compliant `.ai-brain-pill` CSS (with hyphen) but the partial renders `class="aibrain-pill"` (no hyphen) — the compliant CSS is **dead code** |

**Recommendation:** Fix in **S115.AIBRAIN_PILL_MIGRATE** (after S113 lands). Estimated ~45 min CSS-only work + 15 min visual review. Migration order: tokens → partial CSS → Effect #9 wires → cleanup orphan in life-board.php.

---

## 2. Phase 2 — Data flow + bottlenecks

**Detail:** [`/tmp/aibrain_audit/02_data_flow.md`](./02_data_flow.md)

**Today's flow (Phase 1 reactive):**
```
cron-insights (15min)
  → compute-insights.php (1745 LOC, ~30 pf*() probes)
  → ai_insights table (UNIQUE on tenant+store+topic_id, idempotent UPSERT)
  → life-board.php reads, picks 4 cards (loss-heavy)
  → render top section + ai-brain-pill partial
  → user tap pill → voice-overlay → ai-brain-record.php → loopback chat-send.php → AI reply
```

**Bottleneck reality check:** the flow is fundamentally sound. **No latency or correctness blockers in Phase 1.** Specific findings:
- `ai_insights` UNIQUE constraint is **intentional idempotency** (per inline comment lines 241-251), not a bug. STRESS_BOARD entry P1 ГРАФА 3 mis-frames it.
- The actual Phase 2 readiness gap is **absence of any producer** writing to `ai_brain_queue`. Table exists, queries don't.
- ~15ms session-lock overhead in `ai-brain-record.php` loopback — known, planned for Phase 2 refactor.

---

## 3. Phase 3 — `ai_brain_queue` schema enhancements (additive)

**SQL draft:** [`/tmp/aibrain_audit/03_queue_design.sql`](./03_queue_design.sql) — **DO NOT APPLY** without S116 review.
**Rationale:** [`/tmp/aibrain_audit/03_queue_design.md`](./03_queue_design.md)

**Important:** the brief asked for a CREATE TABLE draft, but the table **already exists on prod** (s92_aibrain_up.sql). This audit's draft is a series of additive `ALTER TABLE` statements that:

| Change | Reason |
|---|---|
| `type` ENUM +7 values | Phase 2 producer types (`defective_detected`, `voice_transcribe`, `image_analyze`, `reorder_suggest`, `category_drift`, `price_anomaly`, `batch_summary`) |
| `status` ENUM +3 values | Worker pattern needs `processing`, `failed`, `skipped` |
| `+ store_id` (NULL) | Currently scoped only to (tenant, user) — store-scoping needed for deliveries |
| `+ attempts`, `+ max_attempts` | Retry pattern with exponential backoff |
| `+ started_at`, `+ completed_at`, `+ error_msg`, `+ result_data JSON` | Worker observability + AI output capture |
| `+ scheduled_at` (default NOW) | Delayed retry / backoff |
| `+ source_table`, `+ source_id` | Generic FK to producer (deliveries / voice_blob / image_upload / etc.) |
| 3 new indexes | Worker pull, source traceability, store-scoped fetch |

**Net:** 13 cols → 25 cols, 2 indexes → 5 indexes. All reversible. Worker pattern is **pull-based via cron** (no Gearman / Redis dependency).

---

## 4. Phase 4 — Deliveries × AI Brain integration (RWQ #81)

**Detail:** [`/tmp/aibrain_audit/04_deliveries_aibrain.md`](./04_deliveries_aibrain.md)

Four triggers, each maps to a queue type:

| # | Trigger | Type | Producer | Cost/event |
|---|---|---|---|---|
| 1 | Voice OCR at receive (replaces `api_voice_capture` stub — RWQ #81 P0-1) | `voice_transcribe` | `delivery.php api_voice_capture` | €0.0025 (Whisper) |
| 2 | Defective detection from photo (RWQ #81 P0-3) | `image_analyze` → chains `defective_detected` | `delivery.php api_commit` | €0.001-0.003 (Gemini Vision) |
| 3 | Proactive reorder suggestion | `reorder_suggest` | nightly `cron/ai-brain-reorder-suggest.php` | €0 (SQL) or €0.001 (AI refined) |
| 4 | Price anomaly | `price_anomaly` | synchronous in `api_commit` | €0 (SQL) |

**Per-tenant monthly cost (mid-size store):** ~€0.06. **At 200 tenants:** ~€12/month AI vendor spend.

**Beta of the cost lever:** if AI-refined reorder is on for ALL items (vs. opt-in per tenant), monthly spend jumps from €12 → €1200 at 200 tenants. **Recommendation:** keep refinement OFF by default; expose as Pro-plan toggle.

---

## 5. Recommended next sessions

| Session ID | Title | Depends on | Effort | Risk |
|---|---|---|---|---|
| **S115.AIBRAIN_PILL_MIGRATE** | Fix pill class name + tokens + Effect #9 + light theme | S113 close (Code 1's `products.php` work) | ~1 h | Low — pure CSS |
| **S116.AIBRAIN_QUEUE_BUILD** | Apply queue schema, build worker, build fetch endpoint | This audit closed | ~6 h | Medium — first cron worker |
| **S117.DELIVERIES_AIBRAIN** | Wire 4 triggers in `delivery.php` | S98.DELIVERIES + S116 | ~5 h | Medium — touches prod delivery flow |

**Suggested sequence:** S115 first (cheap visual wins, unblocked by anything else as soon as S113 closes) → S116 (infra) → S117 (features). S117 must wait on both S116 and S98.

**Optional follow-ups (not P1):**
- **S118.AIBRAIN_PHASE2_UI** — pulse rate + "AI speaks first" path in voice-overlay.php (Bible §4.5.2.B). Depends on S116.
- **S119.AIBRAIN_PHASE3_FAB** — wire mini-FAB in `sale.php`, `products.php`, etc. (Bible §4.5.2.C). Depends on S115 (so the FAB inherits compliant tokens) and S118.

---

## 6. Risks and dependencies

### 6.1 Hard blockers for S117

- **MySQL 8.0 vs 5.7:** worker uses `FOR UPDATE SKIP LOCKED` (8.0+ only). Тихол to confirm prod version. If 5.7, fall back to optimistic-lock pattern (documented in `03_queue_design.md §2.2`).
- **Vendor decisions:** Whisper provider (OpenAI / fal.ai / openai-compat). Gemini Vision tier. Тихол to pick before S117 kicks off.

### 6.2 Soft risks

| Risk | Mitigation |
|---|---|
| Pill rename (S115) collides with S113 if S113 also touches `partials/ai-brain-pill.php` | This audit confirms only `partials/products.php` is Code 1's domain per session brief; pill is Opus's. Verify before S115. |
| Voice transcription PII leak (audio blobs persisted) | TTL: delete `uploads/voice/{tenant}/*.webm` after `result_data.transcript` written + 7 days. Document in S116. |
| Failed queue rows accumulating silently | Add admin view in `admin/beta-readiness.php` for `status='failed' AND attempts >= max_attempts`. P1 in S116. |
| AI refinement cost runaway | Default OFF. Toggle in tenant settings. Monitor via `audit_log` source='ai_brain_queue_summary'. |

### 6.3 Dependencies on other workstreams

- **STRESS_BOARD ГРАФА 3 P1 entry** (ai_insights UNIQUE) needs **edit** to clarify it's intentional, not a bug. **Owner:** Тихол. (Not blocking; documentation-only.)
- **MASTER_COMPASS RWQ #81 (deliveries P0)** is partially addressed by S117 triggers 1 + 2. RWQ #82 (P1: auto-pricing C8 + has_mismatch + fuzzy match) is **prerequisite** for trigger 3 (reorder suggest) to compute correctly.

---

## 7. Cost projection summary

| Item | Per-tenant/month | At 200 tenants/month |
|---|---|---|
| Whisper STT (voice deliveries) | €0.015 | €3 |
| Gemini Vision (defective detection) | €0.030 | €6 |
| Reorder refinement (Gemini, default OFF) | €0.010 (if ON for sample) | €2 (sample) — **€1200 if ON for all** |
| Price anomaly (SQL only) | €0 | €0 |
| **Default config total** | **~€0.06** | **~€12** |

Vendor spend stays **under 1% of infra budget** at 200 tenants ($200/mo infra). No business-model concern.

---

## 8. Files produced by this audit

```
/tmp/aibrain_audit/
├── 01_pill_compliance.md      — Phase 1 pill audit (10 KB)
├── 02_data_flow.md            — Phase 2 flow + bottlenecks (8 KB)
├── 03_queue_design.sql        — Phase 3 schema draft (4 KB)
├── 03_queue_design.md         — Phase 3 rationale (8 KB)
├── 04_deliveries_aibrain.md   — Phase 4 deliveries spec (10 KB)
├── HANDOFF.md                 — this file
└── _pill_compliance_raw.txt   — raw `check-compliance.sh` output
```

**Тихол:** copy this directory to `/var/www/runmystore/docs/audits/S114_AIBRAIN/` after review, commit on a separate branch (`audits/s114-aibrain`) so future sessions can reference.

---

## 9. DOD scorecard

| Item | Status |
|---|---|
| 5 markdown documents in `/tmp/aibrain_audit/` | ✅ (01, 02, 03_md, 04, HANDOFF) |
| 1 SQL schema draft (`ai_brain_queue` Phase 2) | ✅ `03_queue_design.sql` |
| `HANDOFF.md` final summary | ✅ |
| ZERO git operations (commit/push/add) | ✅ — none performed |
| ZERO mutations on `products.php` / `partials/*` / live DB | ✅ — read-only access throughout |
| `ai_insights` table NOT touched | ✅ |
| Time ≤ 4 hours | ✅ ~2.5 h |
