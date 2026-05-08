# Phase 3 — `ai_brain_queue` Phase 2 Schema Enhancement

**Session:** S114.AIBRAIN_AUDIT
**Companion file:** `03_queue_design.sql` (DRAFT — not applied)
**Reference:** `migrations/s92_aibrain_up.sql` (existing table), `SIMPLE_MODE_BIBLE.md §4.5.3`, S92 handoff RWQ #53.

---

## 1. Context — table already exists

Production already has `ai_brain_queue` (created S92.AIBRAIN.PHASE1, applied manually by Тихол per the S92 handoff). Phase 1 = REACTIVE only — the table is **created but unused** (no producer writes, no reader fetches, no TTL cron). Phase 2 turns it on.

The brief asked for a CREATE TABLE draft, but **a fresh CREATE would conflict with the live table**. Instead this design proposes ADDITIVE `ALTER TABLE` migrations that:
1. Extend the `type` ENUM with new producer types.
2. Add async-job lifecycle fields (worker pattern needs `attempts`, `started_at`, `error_msg`, `result_data`).
3. Add `store_id` (currently the table is scoped only by `(tenant_id, user_id)` — store-scoping matters for deliveries / inventory).
4. Add generic `source_table` + `source_id` for traceability (avoids one FK column per producer).
5. Add new indexes for Phase 2 access patterns.

Every change is **additive and reversible**, satisfying the "DO NOT touch ai_insights existing data" constraint by extension to its sibling table.

---

## 2. Why this structure (rationale)

### 2.1 Why ENUM extension instead of a new `task_type` column

Bible §4.5.3 binds `type` to a fixed semantic vocabulary, and PHP code already pattern-matches on it (see S92 handoff RWQ #53 anticipated `compute-insights.php` writes). Adding new ENUM values is forward-compatible — old code keeps working, new code reads new values. Replacing with a free-text `task_type` would invalidate every existing consumer.

The brief's proposed types (`compute_insight`, `detect_defective`, `voice_to_text`, etc.) are renamed to fit the existing snake_case + 1-2 word convention:

| Brief proposal | Adopted name (existing convention) |
|---|---|
| `compute_insight` | (skip — covered by Bible's `confidence_nudge` / `review_check`) |
| `detect_defective` | `defective_detected` |
| `voice_to_text` | `voice_transcribe` |
| `image_analysis` | `image_analyze` |
| `reorder_suggestion` | `reorder_suggest` (already conceptually = Bible's `order_suggestion`, but Phase 2 reorder_suggest is **AI-computed quantities** vs. existing rule-based `order_suggestion`) |
| `category_drift` | `category_drift` ✓ |
| `price_anomaly` | `price_anomaly` ✓ |
| `batch_summary` | `batch_summary` ✓ |

### 2.2 Worker pattern — pull-based via cron, not gearman push

**Recommendation: pull-based.** Reasons:
- The codebase has zero queue infrastructure today. Gearman / Redis / RabbitMQ adds operational surface area before a single feature ships.
- Phase 1 already deploys a `cron-insights.php` pattern (every 15 min). Reusing the same shape for `cron-aibrain-worker.php` keeps deployment unchanged (just edit `/etc/cron.d/runmystore`).
- Pull pattern with `SELECT … FOR UPDATE SKIP LOCKED` (MySQL 8.0) gives mutual exclusion across N workers without any new infra. (Fallback for MySQL 5.7: row update with `WHERE status='pending' AND attempts < max_attempts` and check affected rows.)

**Worker loop (sketch — NOT implemented in this audit):**

```php
// cron/ai_brain_worker.php — runs every 60s; processes up to 20 rows per tick
$rows = DB::run("
  SELECT id, tenant_id, store_id, user_id, type, payload := action_data
    FROM ai_brain_queue
   WHERE status='pending'
     AND scheduled_at <= NOW()
     AND attempts < max_attempts
   ORDER BY priority DESC, scheduled_at ASC
   LIMIT 20
   FOR UPDATE SKIP LOCKED
")->fetchAll();

foreach ($rows as $r) {
  DB::run("UPDATE ai_brain_queue SET status='processing', started_at=NOW(),
                                     attempts=attempts+1 WHERE id=?", [$r['id']]);
  try {
    $result = dispatchByType($r);   // → Gemini / fal.ai / Whisper / pure SQL
    DB::run("UPDATE ai_brain_queue SET status='done', completed_at=NOW(),
                                       result_data=? WHERE id=?",
            [json_encode($result), $r['id']]);
  } catch (Throwable $e) {
    $next = $r['attempts'] < $r['max_attempts'] ? 'pending' : 'failed';
    $delay = pow(2, $r['attempts']) * 60;  // exp backoff: 2,4,8 min
    DB::run("UPDATE ai_brain_queue SET status=?, error_msg=?,
                                       scheduled_at=DATE_ADD(NOW(), INTERVAL ? SECOND)
             WHERE id=?", [$next, $e->getMessage(), $delay, $r['id']]);
  }
}
```

### 2.3 Rate limiting

**Two layers:**

1. **Per-tenant token bucket** (in-app): cap each tenant to ≤ N AI calls per minute. Prevents one runaway tenant from starving others.
   ```sql
   -- New table; not part of this draft (out of scope, S116):
   CREATE TABLE ai_rate_limits (
     tenant_id INT PRIMARY KEY,
     tokens INT NOT NULL DEFAULT 60,
     refill_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
   );
   ```
2. **Per-type concurrency cap**: vision jobs are slower than transcription jobs. The worker reads `MAX_CONCURRENT[type]` from a config and skips if exceeded. Implement with `SELECT COUNT(*) WHERE status='processing' AND type=?` before pick.

### 2.4 Failure handling — exponential backoff

- `attempts` increments on each pick.
- On failure: row goes back to `pending` with `scheduled_at = NOW() + 2^attempts × 60 sec` (2 min → 4 min → 8 min).
- After `attempts ≥ max_attempts` (default 3): status flips to `failed`, `error_msg` retained for ops.
- `failed` rows are NOT shown to Pешо (status filter excludes). They're operational dead letters.

### 2.5 Cleanup policy

Bible §4.5.3 already specifies 03:00 daily cron for TTL. Phase 2 extends:

```
DELETE FROM ai_brain_queue
 WHERE status IN ('done','dismissed','failed')
   AND completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

**Why 30 days:** long enough for ops audit / "why did the AI suggest X 2 weeks ago", short enough that the table doesn't bloat. `result_data` JSON is the size driver — image analysis output ≈ 2-5KB/row.

---

## 3. Reconciliation with S92 schema

| Aspect | s92 (existing) | This draft (Phase 2 additive) |
|---|---|---|
| `type` ENUM | 6 values | **+7** = 13 values |
| `status` ENUM | 4 values | **+3** = 7 values (adds processing/failed/skipped) |
| `store_id` | absent | **NEW** — NULL for tenant-wide |
| `attempts` / `max_attempts` | absent | **NEW** for retry pattern |
| `started_at` / `completed_at` | absent | **NEW** for worker observability |
| `error_msg` / `result_data` | absent | **NEW** — error trail + worker output |
| `scheduled_at` | absent | **NEW** — delayed pick / backoff |
| `source_table` / `source_id` | only `insight_id` (FK to ai_insights) | **NEW** — generic source tracker |
| Indexes | 2 | **+3** = 5 |

**Total column count:** 13 → 25.

**Storage impact (rough):** worst-case row growth from ~200 bytes → ~1-2KB once `result_data` populated. At expected volumes (≤ 100 active items per tenant per day, ~30 days retention) → ~3MB per Pro tenant per month. Negligible.

---

## 4. Apply order (proposed for S116)

1. Run `pre-flight` query: `SHOW CREATE TABLE ai_brain_queue` — verify s92 baseline.
2. Apply `03_queue_design.sql` step-by-step (each `ALTER TABLE` is its own statement; can be applied sequentially with `--single-transaction` for InnoDB DDL safety on supported MySQL).
3. Verify `SHOW CREATE TABLE` shows 25 columns.
4. Insert a smoke-test row, fetch via worker query, delete.
5. Roll forward to S116 worker scaffold.

**ABSOLUTE NO-GO for this audit:** the SQL file lives in `/tmp/aibrain_audit/` and is NOT applied. Тихол will copy and run manually after S113 closes (per session brief).

---

## 5. Open questions for S116 chef-chat

- **MySQL version:** `FOR UPDATE SKIP LOCKED` requires 8.0+. If prod is 5.7 we need optimistic-lock fallback. **Action:** Тихол to confirm.
- **Per-type worker split:** single worker for all types, or split (e.g. `cron/ai_brain_worker_vision.php` for slow image jobs)? Decision deferred — start with single, split if vision starves chat-style items.
- **Whisper vs. Web Speech API:** Phase 1 uses browser Web Speech for STT. Phase 2 `voice_transcribe` task assumes server-side Whisper (or fal.ai/openai-compat). **Cost:** ~€0.005/min. **Action:** Тихол to pick provider.
- **Vision provider:** Gemini Vision (€0.001/check) preferred for cost; fal.ai cheaper for batched. **Action:** decision parked at S117.

---

## 6. Anti-patterns avoided

- ❌ **NOT** dropping the existing s92 schema — pure additive.
- ❌ **NOT** breaking the existing `(tenant_id, user_id, status, priority)` index — kept as-is.
- ❌ **NOT** applying on live DB — file is a draft.
- ❌ **NOT** adding a separate `tasks` or `jobs` table — single source of truth in `ai_brain_queue`.
- ❌ **NOT** introducing Redis/Gearman without need — pull-based cron is sufficient for v1.
