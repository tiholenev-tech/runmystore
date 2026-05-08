# Phase 4 — Deliveries × AI Brain Integration Spec (RWQ #81)

**Session:** S114.AIBRAIN_AUDIT
**Reference:** `docs/DELIVERIES_BETA_READINESS.md`, MASTER_COMPASS RWQ #81 + RWQ #82, Bible §4.5.3, this audit `03_queue_design.*`.
**Target session:** **S117.DELIVERIES_AIBRAIN** (depends on S98.DELIVERIES + S116.AIBRAIN_QUEUE_BUILD).

---

## 1. Why deliveries first

`docs/DELIVERIES_BETA_READINESS.md` confirms the deliveries pipeline is end-to-end functional today (`delivery.php` → OCR → review → commit) but **§E defective workflow proactive prompt is GAPPED** (RWQ #81). Plus `api_voice_capture()` is a stub returning `voice flow not implemented yet`. These are exactly the use-cases that justify an async AI queue.

Each trigger below maps to a `type` value in the Phase 2 enum extension (see `03_queue_design.sql`).

---

## 2. Trigger 1 — Voice OCR at receive (`type='voice_transcribe'`)

**Use case:** Pешо receives a delivery, can't open the camera (hands full, dim warehouse), says into AI Brain pill: *"Marina ми достави 47 чифта чорапи Nike 42, 30 чифта Adidas 41, и 5 повредени Nike 42."*

**Endpoint design:**

```
POST /ai-brain-record.php
Body: {
  csrf, text: <STT-output OR voice blob>, source: 'delivery_voice',
  context: { delivery_id?: int, supplier_id?: int }
}

Response: {
  reply: "Записах. Обработвам гласа — ще ти кажа след минута.",
  queue_id: 12345,            ← NEW Phase 2 field
  poll_url: '/ai-brain-fetch.php?queue_id=12345'
}
```

**Server flow:**
1. `ai-brain-record.php` accepts text **or** multipart audio blob.
2. If blob: store to `/var/www/runmystore/uploads/voice/{tenant}/{ts}.webm` (existing pattern), enqueue:
   ```sql
   INSERT INTO ai_brain_queue
     (tenant_id, store_id, user_id, type, message_text,
      source_table, source_id, action_data)
   VALUES (?, ?, ?, 'voice_transcribe', 'Async STT for delivery voice',
           'voice_blob', ?, JSON_OBJECT('audio_path', ?, 'context', ?));
   ```
3. Worker picks the row, calls Whisper / fal.ai, writes transcript to `result_data.transcript`, status → `done`.
4. Worker chains a second queue row of `type='reorder_suggest'` or directly creates a `delivery_items` draft (depends on context).

**Cost (Whisper):** €0.005/min × ~30 sec/delivery ≈ **€0.0025 per voice delivery**.

**RWQ #81 P0-1 fix:** replace `api_voice_capture()` stub with `enqueue voice_transcribe`. UI shows "AI обработва" with poll-based reveal.

---

## 3. Trigger 2 — Defective detection (`type='defective_detected'`)

**Use case:** Pешо takes a photo of the delivery box, OCR misses subtle damage (dent, broken seal). AI vision pass flags suspicious items, generates a queue item: *"На снимка 3 виждам разкъсан плик. Marina 47/50 — 3 повредени?"*

**Endpoint design:**

```
POST /ai-brain-record.php
Body: { csrf, source: 'delivery_image', context: { delivery_id, image_paths: [...] } }
```

**Server flow:**
1. After existing OCR commit (`api_commit`), if **any** image upload was attached, enqueue:
   ```sql
   INSERT INTO ai_brain_queue (… type='image_analyze' …)
   VALUES (… JSON_OBJECT('image_paths', ?, 'task', 'detect_defective_visual') …);
   ```
2. Worker calls Gemini Vision with prompt: *"Identify damaged/torn/dented items in this delivery photo. Return JSON: {damaged_count, evidence: [{box, reason}, …]}".*
3. If `damaged_count > 0`, worker chains a `defective_detected` queue item with `priority=80` (high) so the AI Brain pill pulses immediately.
4. Pешо taps pill → AI speaks: *"На снимка съм видяла 3 разкъсани опаковки. Да ги маркирам като дефектни?"*
5. "Да" → existing `aibrain-modal-actions.php?action=defective_draft` (NEW handler) → INSERT `supplier_defectives` rows linked to delivery.

**Cost (Gemini Vision):** ~€0.001/check × 1-3 photos/delivery ≈ **€0.001-0.003 per delivery**.

**RWQ #81 P0-3 fix:** "defective proactive prompt missing" → this trigger IS that prompt.

---

## 4. Trigger 3 — Proactive reorder suggestion (`type='reorder_suggest'`)

**Use case:** A nightly cron analyzes 90-day sales velocity per (product, supplier). For supplier-product pairs trending below safety stock, AI computes a suggested reorder qty and enqueues a queue item.

**Producer (cron, NOT realtime):**
- New script `cron/ai-brain-reorder-suggest.php`, runs nightly (e.g. 02:30, before TTL cron at 03:00).
- For each tenant, query products with: `velocity_7d_avg`, `velocity_30d_avg`, `current_stock`, `last_supplier_lead_time_days`, `incoming_qty`.
- Heuristic (NO AI call needed for v1, just SQL):
  ```
  if (current_stock + incoming_qty) / velocity_7d_avg < lead_time_days × 1.3:
    suggested_qty = ceil(velocity_30d_avg × (lead_time_days × 2))
    enqueue 'reorder_suggest' with priority = 60-80 based on stockout risk
  ```
- For Phase 2.5: add Gemini call to refine qty based on seasonality. Optional, costs more.

**Queue row:**
```sql
INSERT INTO ai_brain_queue
  (tenant_id, store_id, user_id, type, message_text, action_data, priority)
VALUES
  (?, ?, ?, 'reorder_suggest',
   'Nike 42 ще свърши след 4 дни. Поръчам ли 30 чифта от Marina?',
   JSON_OBJECT('product_id', ?, 'supplier_id', ?, 'qty', 30,
               'lead_time_days', 5, 'velocity_7d', 7.2),
   75);
```

**User select → "Да":** AI Brain modal posts to `aibrain-modal-actions.php?action=order_draft_submit` (existing handler) with `items=[{product_id, qty}]` — creates a `purchase_orders` row in `status='draft'`. Existing handler line 94-137 covers this.

**Cost:** €0 (pure SQL); €0.001 per item if AI refinement enabled.

---

## 5. Trigger 4 — Price anomaly (`type='price_anomaly'`)

**Use case:** Marina has historically delivered Nike 42 at €18-€20/pair. New delivery came in at €27/pair. Either suppliers raised prices, or Pешо/scanner mis-read a digit.

**Producer (synchronous on commit):**
- During `delivery.php api_commit()`, after the `INSERT INTO delivery_items` loop, run a price-comparison query:
  ```sql
  SELECT product_id, AVG(cost_price) AS hist_avg, STDDEV(cost_price) AS hist_std
    FROM delivery_items di JOIN deliveries d ON d.id = di.delivery_id
   WHERE d.tenant_id = ? AND di.product_id IN (?, ?, …)
     AND d.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
   GROUP BY product_id
  HAVING COUNT(*) >= 3;
  ```
- For each new delivery_items row whose `cost_price > hist_avg + 2*hist_std` (or `< hist_avg - 2*hist_std`), enqueue:
  ```sql
  INSERT INTO ai_brain_queue (…) VALUES (…, 'price_anomaly', 'Nike 42 досега е бил €19, тази доставка показва €27. Грешка ли е?', 70);
  ```
- Result delivery: pill pulse + on tap, AI speaks the anomaly. Tihol/Pешо can answer: "поправи на 19" → modal triggers UPDATE on delivery_items + audit_log entry.

**Cost:** €0 (pure SQL, no AI).

---

## 6. Cross-cutting: result delivery to UI

Three options, ranked by complexity:

| Option | How | Latency | Infra cost |
|---|---|---|---|
| **A. Polling (recommended for v1)** | Frontend polls `/ai-brain-fetch.php` every 5 sec while overlay open | 5 sec worst | Zero (existing infra) |
| B. SSE | `/ai-brain-stream.php` keeps connection open, pushes results | <1 sec | Long-running PHP — needs FPM tuning |
| C. WebSocket broadcast | Ratchet/Soketi sidecar | <100ms | New service to operate |

**Recommendation:** Option A for S117. Option B/C only if user-perceived latency complaints surface during beta.

---

## 7. Cost projection (per Pro-tier tenant, per month)

Assumptions (mid-size active store):
- 30 deliveries/month
- 50% with photos (15 vision calls)
- 20% with voice (6 STT calls @ ~30 sec each)
- 200 reorder suggestions checked nightly (mostly pure SQL, ~5% trigger AI refinement = 10 AI calls)
- 100 price anomaly checks (all SQL, €0)

| Trigger | Calls | Unit cost | Sub-total |
|---|---|---|---|
| Voice transcribe (Whisper) | 6 × 0.5 min | €0.005/min | €0.015 |
| Image analyze (Gemini Vision) | 15 × 2 imgs | €0.001/img | €0.030 |
| Reorder suggest refinement | 10 | €0.001 | €0.010 |
| Price anomaly | 100 | €0 | €0.000 |
| **Total per tenant/month** | | | **≈ €0.06** |

At 200 tenants (Phase 2 scaling target per MASTER_COMPASS): **≈ €12/month** AI vendor cost. Trivial relative to $200/mo infra spend at that tier.

**Caveat:** if Тихол turns ON Gemini refinement for ALL reorder suggestions (200 nightly × 30 days = 6000 calls/tenant = **€6/tenant/month = €1200/month at 200 tenants**) — that's the order-of-magnitude jump. Default OFF, opt-in per tenant.

---

## 8. Implementation checklist for S117

Anticipated commits (linear, can split):

1. `S117.DELIVERIES_AIBRAIN.01` — schema additions (already drafted in `03_queue_design.sql`).
2. `S117.DELIVERIES_AIBRAIN.02` — `cron/ai_brain_worker.php` skeleton with type dispatcher.
3. `S117.DELIVERIES_AIBRAIN.03` — `ai-brain-fetch.php` GET endpoint for polling.
4. `S117.DELIVERIES_AIBRAIN.04` — `delivery.php` defective trigger wire-up (`type='image_analyze'`).
5. `S117.DELIVERIES_AIBRAIN.05` — `cron/ai-brain-reorder-suggest.php` nightly producer.
6. `S117.DELIVERIES_AIBRAIN.06` — `delivery.php api_voice_capture()` real impl with `voice_transcribe` enqueue (RWQ #81 P0-1).
7. `S117.DELIVERIES_AIBRAIN.07` — `delivery.php api_commit()` price anomaly check.
8. `S117.DELIVERIES_AIBRAIN.08` — `aibrain-modal-actions.php` add `defective_draft` handler.

**Dependencies:** S98.DELIVERIES (RWQ #82 auto-pricing — gives us `has_mismatch` compute and fuzzy product matching that the reorder producer reuses) + S116.AIBRAIN_QUEUE_BUILD (worker infrastructure + fetch endpoint).

---

## 9. Risk register for S117

| Risk | Mitigation |
|---|---|
| Gemini Vision rate limits | Per-tenant token bucket (see 03_queue_design.md §2.3) |
| Voice blob storage growth | TTL on uploads/voice/* (delete after `result_data.transcript` written + 7 days) |
| Whisper Bulgarian accuracy | Phase 2.1: A/B Whisper vs. Web Speech API; user can vote in voice-overlay |
| Price-anomaly false positives | Require 3+ historical samples (`HAVING COUNT(*) >= 3`); 2σ threshold; user dismiss feeds back as `priority -= 30` next run |
| Owner blindness to silently-failed jobs | Admin view in `admin/beta-readiness.php` for `status='failed' AND attempts >= max_attempts` rows; Тихол daily check |
