# DELIVERIES_BETA_READINESS — Audit Report (S98)

**Date:** 2026-05-03
**Author:** Code Code #2 (read-only audit)
**Beta target:** ENI Tiholov, 14-15.05.2026 (10 days from audit)
**Scope:** delivery.php (1073 LOC) · deliveries.php (455 LOC) · services/ocr-router.php (495 LOC)
**Mode:** read-only — no code changes, no DB writes.

---

## 1. Executive Summary

End-to-end delivery flow is **functional and complete**: hub → camera → AI parse → review → commit. ~2023 LOC across 3 files, all dated 1 May (recent, not stale). Spec compliance with `DELIVERY_ORDERS_DECISIONS_FINAL.md` is partial — sections **§A-B (UX), §F (duplicates), §K (Simple hub), §L (Detailed hub)** are implemented; sections **§C (auto-pricing), §D (4 mismatch scenarios), §E (defective workflow proactive prompt)** are **gapped**.

Overall verdict: **shippable for beta** if 3 P0 fixes land (~50 LOC). 5 P1 items are spec-required but recoverable if Eni hits them as bugs. P2 items are quality-of-life.

---

## 2. File Mapping

### 2.1 `services/ocr-router.php` (495 LOC) — OCRRouter class

| Section | LOC | Purpose |
|---|---|---|
| Constants | 39-43 | Confidence thresholds (AUTO 0.92, SMART 0.75, REJECT 0.50), math tolerance ±0.02, max file 10 MB |
| `process()` | 59-147 | Public entrypoint — runs all 3 levels + confidence merge + override 1 (variation_pending) + override 3 (voice fallback < 0.5) |
| Level 0 — `fileQualityGate()` | 152-168 | Size/mime/dim guards. Allowed: jpg, png, webp, heic, heif, **pdf** |
| Level 1 — `aiVisionExtract()` | 178-230 | Multipart Gemini call, model from `GEMINI_MODEL` const (default `gemini-2.5-flash`), `responseMimeType: application/json`, temp 0.1, timeout 60s |
| `systemPrompt()` | 232-281 | BG prompt — strict JSON schema, null-not-invent rule, confidence per item, uncertain_fields path list, has_variations_hint heuristic |
| `normalizeVisionResponse()` | 283-325 | Coerces Gemini response to canonical shape (header + items + confidence + uncertain_fields) |
| Level 2 — `mathValidator()` | 330-367 | base+vat=total ±0.02, qty×unit=line_total, items_sum vs base ±0.10 |
| Level 3 — `detectInvoiceType()` | 372-383 | clean / semi / manual based on confidence + has_variations_hint |
| `markVariationPending()` | 388-418 | Per-item override 1: existing parent with `has_variations='true'` → row stays manual review |
| `computeMergedConfidence()` | 423-429 | Penalize -0.10 per math issue (capped at 3) |
| **HTTP endpoint** | 449-494 | POST mode at end of file — own multipart accept + tenant from session. **Note:** delivery.php doesn't use this endpoint; it instantiates OCRRouter directly. |

### 2.2 `delivery.php` (1073 LOC) — single-delivery wizard

| Section | LOC | Purpose |
|---|---|---|
| Bootstrap + auth | 20-56 | Session, user/tenant load, mode resolution (seller→simple, owner→ui_mode) |
| AJAX router | 58-84 | Switch on `?api=` — 7 endpoints |
| View load | 86-122 | If `?id=N` → fetch delivery+items, suppliers list |
| `api_ocr_upload()` | 127-268 | Multipart → OCRRouter → if not REJECTED → resolve supplier (LIKE) → duplicate check (`F1-F2`) → DB::tx insert deliveries + delivery_items + delivery_events('ocr_imported') → return redirect |
| `resolveOrCreateProduct()` | 270-305 | Match by `(supplier_id, supplier_product_code)` → exact name → INSERT new with `has_variations='unknown'`. **Bug:** L294 ternary is dead code (both branches return `'unknown'`) |
| `api_update_item()` | 307-351 | Per-row qty/cost/retail edit. If retail provided + product_id known → updates products + auditPriceChange |
| `api_approve_item()` | 353-365 | Sets `received_condition='new'` |
| `api_add_defective()` | 367-401 | Sets received_condition + INSERT supplier_defectives (status='pending') |
| `api_commit()` | 403-476 | Commits non-defective rows: inventory upsert + stock_movements('delivery') + delivery_events('committed') + computes payment_due_date from supplier.payment_terms_days + locks delivery |
| `api_voice_capture()` | 478-480 | **Stub:** `return ['ok'=>false, 'error'=>'voice flow not implemented yet — use camera']` |
| `api_manual_create()` | 482-495 | Empty draft INSERT for ручен path |
| HTML/CSS | 503-674 | Module-specific styles (mod-del-*) on top of design-kit; entry screen + review screen + bottom sheet + loading overlay |
| Review render | 682-822 | Detailed-mode VAT header + progress bar + rows (approved/uncertain/defective styling) + commit/draft buttons |
| Entry screen | 824-883 | 3 CTAs (camera/voice/manual) + supplier override dropdown |
| JS | 920-1071 | Camera handler (multipart POST → redirect), voice stub (toast only), manual create, bottom-sheet edit/save/defective, commit |

### 2.3 `deliveries.php` (455 LOC) — module hub

| Section | LOC | Purpose |
|---|---|---|
| Bootstrap | 13-38 | Session, user/tenant load, plan resolution |
| Recent + KPI queries | 43-87 | Last 10 deliveries with payment state, week/year totals, mismatch count, defectives count + value |
| Insights load | 89-105 | Group `ai_insights` by 6 fundamental questions (loss / loss_cause / gain / gain_cause / order / anti_order) |
| Filter tab | 107-138 | `?filter=all|mismatch|unpaid|reviewing` |
| Reliability | 140-152 | Top 5 suppliers by reliability_score |
| HTML | 169-454 | Hero CTA → `/delivery.php?action=new` · Detailed-mode KPI grid + tabs · Simple-mode payment proactive lb-card · 6 briefing sections · Reliability table (Detailed) · Recent list · Secondary tiles to defectives.php + orders.php |

---

## 3. End-to-End Flow Trace

```
┌─────────────────────────────────────────────────────────────┐
│ 1. /deliveries.php (hub)                                     │
│    Hero CTA → /delivery.php?action=new                       │
└─────────────────┬───────────────────────────────────────────┘
                  ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. /delivery.php?action=new (entry screen)                   │
│    3 CTAs: Camera · Voice (stub) · Manual                    │
│    + supplier override dropdown                              │
└─────────────────┬───────────────────────────────────────────┘
                  ▼ camera tap
┌─────────────────────────────────────────────────────────────┐
│ 3. <input type="file" capture="environment" multiple>        │
│    onchange → FormData(file[], supplier_id?)                 │
│    showLoading("Чета фактурата..." 3-5s)                     │
└─────────────────┬───────────────────────────────────────────┘
                  ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. POST /delivery.php?api=ocr_upload                         │
│    └─ api_ocr_upload()                                       │
│       ├─ OCRRouter::process(files, tenant, opts)             │
│       │  ├─ Level 0: file size/mime gate                     │
│       │  ├─ Level 1: Gemini 2.5 Flash JSON extract           │
│       │  ├─ Level 2: math validation                         │
│       │  ├─ Level 3: invoice_type detect                     │
│       │  └─ confidence merge                                 │
│       ├─ If REJECTED → return error + suggest_voice_fallback │
│       ├─ Resolve supplier by LIKE %name%                     │
│       ├─ checkDuplicate('delivery', ...) → hard block if dup │
│       ├─ DB::tx:                                             │
│       │  ├─ INSERT deliveries (status='reviewing', ...)      │
│       │  ├─ FOREACH item:                                    │
│       │  │  ├─ resolveOrCreateProduct() (exact match or new) │
│       │  │  └─ INSERT delivery_items                         │
│       │  └─ INSERT delivery_events('ocr_imported')           │
│       └─ return {ok:true, redirect_to: '/delivery.php?id=N'} │
└─────────────────┬───────────────────────────────────────────┘
                  ▼ window.location = redirect_to
┌─────────────────────────────────────────────────────────────┐
│ 5. /delivery.php?id=N (review screen)                        │
│    Loads delivery + items                                    │
│    Renders: VAT header (Detailed) + progress bar + rows      │
│    Per row: approved (green) / uncertain (yellow) / defective│
│    Tap row → bottom sheet: qty / cost / retail / defective   │
│    Save → POST update_item + approve_item                    │
│    Defective → POST add_defective                            │
│    Bottom: "Заприходи всичко" CTA                            │
└─────────────────┬───────────────────────────────────────────┘
                  ▼ commit tap
┌─────────────────────────────────────────────────────────────┐
│ 6. POST /delivery.php?api=commit                             │
│    └─ api_commit()                                           │
│       └─ DB::tx:                                             │
│          ├─ FOR row WHERE received_condition='new':          │
│          │  ├─ inventory.quantity += qty (upsert)            │
│          │  └─ stock_movements('delivery', qty, cost, ...)   │
│          ├─ payment_due_date = NOW() + supplier.terms_days   │
│          ├─ UPDATE deliveries SET status='committed', ...    │
│          └─ INSERT delivery_events('committed')              │
└─────────────────┬───────────────────────────────────────────┘
                  ▼
                  redirect to /deliveries.php
```

**OCR response shape** (per `api_ocr_upload` L257-267):
```json
{
  "ok": true,
  "delivery_id": 42,
  "redirect_to": "/delivery.php?id=42",
  "ocr": {
    "status": "REVIEW_NEEDED",
    "confidence": 0.87,
    "invoice_type": "semi",
    "uncertain_fields": ["items.2.unit_cost"]
  }
}
```

**Database writes per delivery** (full happy path):
- `deliveries` × 1 (draft → committed)
- `delivery_items` × N (one per OCR line)
- `delivery_events` × 2 (ocr_imported, committed)
- `products` × M (where M = unmatched OCR names — created with cost from invoice, retail=0, has_variations='unknown')
- `inventory` × N (upsert per item on commit)
- `stock_movements` × N (one per committed item)
- `supplier_defectives` × K (where K = items marked defective)
- `audit_log` × M (price changes, when retail manually edited during review)

---

## 4. Gap Analysis

### A. What works ✓

1. **OCR pipeline** — Gemini 2.5 Flash with JSON-strict response, BG prompt, confidence routing, math validation. Production-ready.
2. **File quality gate** — size, mime (incl. PDF + HEIC), dimensions enforced upstream.
3. **Duplicate detection** — F1-F2 implemented via `checkDuplicate()`. Hard block on `(supplier_id, invoice_number)` exact match.
4. **Variation override (override 1)** — products with `has_variations='true'` flagged `variation_pending=1` per row, blocking auto-accept until reviewed.
5. **Voice fallback signal (override 3)** — when confidence < 0.5, OCR returns `suggest_voice_fallback=true`. Signal exists; **consumer is stub** (see P0-1).
6. **Review UI** — bottom sheet for qty/cost/retail edit + defective marking + per-row condition styling (approved/uncertain/defective).
7. **Commit transaction** — single DB::tx covers inventory upsert + stock_movements + delivery status + payment_due_date computation + event log.
8. **Audit trail** — delivery_events for ocr_imported + committed; auditPriceChange for retail edits.
9. **Hub KPIs (Detailed Mode)** — week/year totals, mismatch count, defectives count + value, supplier reliability ranking.
10. **Hub briefing (Simple Mode)** — 6 fundamental-question cards from ai_insights table (loss/cause/gain/cause/order/anti_order).
11. **Payment proactive lb-card** — Simple mode shows next-due unpaid invoice with days countdown.
12. **Filter tabs (Detailed)** — all / mismatch / unpaid / reviewing routes work.
13. **Currency formatting** — `fmtMoney()` helper consistent across hub + review.
14. **Design-kit compliance** — both files use `/design-kit/*.css` 1:1 (no own .glass / .briefing / .pill).

### B. Risks / Gaps

#### P0 (blocker — fix pre-beta)

**P0-1. Voice path is a 2-toast dead-end.**
`api_voice_capture()` (delivery.php L478) returns `{ok:false, error:'voice flow not implemented yet — use camera'}`. UI handler `modDelStartVoice` (L970) shows toast `"Гласовата диктовка скоро"`. When OCR rejects with confidence<0.5 it sets `suggest_voice_fallback=true` and L957 calls `modDelStartVoice` after 1.2s — Eни ще види две toast-а в редица: error toast + warn toast — без actionable next step.
**Fix:** either wire GROQ STT proper (large), OR remove the second toast trigger and rewrite the rejected error message to "Снимката е неясна — опитай пак с по-добра светлина или въведи ръчно".
**LOC estimate:** ~10 LOC (delete fallback trigger + improve error copy) OR ~150 LOC (full voice flow — out of scope for beta).
**Recommended:** strip fallback trigger now; reintroduce in S99 with full STT.

**P0-2. Raw OCR/HTTP errors leak to user toast.**
`OCRRouter::reject()` returns reasons like `"AI Vision call failed (HTTP 503)"`, `"GEMINI_API_KEY not configured"`, `"file too large (> 10MB)"`. These hit `toast(j.error || 'Грешка при четене', 'error')` directly (delivery.php L956). Eни вижда `HTTP 503` за 3.5 секунди.
**Fix:** map `errors[]` to BG user-friendly text in `api_ocr_upload` before returning. Examples: "AI Vision call failed" → "AI системата не отговаря. Опитай след минута.", "file too large" → "Снимката е твърде голяма (>10MB)", "GEMINI_API_KEY not configured" → "Service unavailable" (log details, not surface).
**LOC estimate:** ~15 LOC (mapping table + lookup).

**P0-3. Defective proactive prompt absent (§E1).**
Spec §E1: *"AI активно пита в края на review: Има ли нещо счупено, скъсано или дефектно?"*. Currently no UI prompt before `modDelCommit()` fires. Eни маркира дефектни само ако сам отвори bottom sheet и tap-не "Дефектен". Common case (всичко OK) → пропуска check.
**Fix:** confirm dialog before commit: *"Има ли нещо счупено или дефектно?"* with Yes (cancel commit, scroll to top) / No (proceed). Single confirm() acceptable for beta.
**LOC estimate:** ~12 LOC.

#### P1 (high — fix if time allows)

**P1-1. Auto-pricing C6 routing not wired.**
`pricing-engine.php` is required at delivery.php L24 but never invoked in `api_update_item` or in the bottom-sheet UI. Spec §C8: *"AI предлага САМО retail. Cost идва от фактурата."* — UI placeholder `modDelSheetSuggest` (L897, L998) is empty by default. Eни not seeing AI retail suggestions.
**Fix:** call pricing engine when bottom sheet opens, populate `modDelSheetSuggest` with suggested retail. Apply C6 routing (>0.85 auto-fill, 0.5-0.85 suggest, <0.5 leave blank).
**LOC estimate:** ~30 LOC (assuming pricing-engine has callable suggestRetail($cost, $category_id, $tenant_id)).

**P1-2. Mismatch scenarios D1-D4 not computed.**
`deliveries.has_mismatch` column drives KPI grid + filter tab + review banner — but I see no code path that **sets** `has_mismatch=1`. Without compute logic, KPI always shows 0, banner never shows, "С разлика" tab is permanently empty.
**Fix:** at end of `api_commit`, compare delivery.total against sum of qty×cost for committed rows. If diff > 0.10 → `has_mismatch=1` + populate `mismatch_summary`. Spec D5 also wants order-level mismatch (poръчал X, дошло Y) — that requires linked purchase order which is out of scope for beta.
**LOC estimate:** ~25 LOC for invoice-level mismatch only (post-beta: order-level).

**P1-3. Fuzzy product matching is exact-string only.**
`resolveOrCreateProduct` (L270) matches by `name = ?` (case-sensitive, exact). OCR variants like *"Бяла рокля М"* vs existing *"Бяла рокля размер М"* → creates duplicate product. Over a 50-item invoice this creates ~10-20 phantom products. Cleanup is painful.
**Fix:** add LIKE / Levenshtein / mb_strtolower normalization layer before INSERT. Soundex isn't BG-friendly; trigram or simple `LOWER(REPLACE(name, ' ', ''))` comparison sufficient for beta.
**LOC estimate:** ~20 LOC.

**P1-4. No OCR retry button.**
On Gemini failure, user gets toast and is stranded on entry screen with no retry CTA. Must re-tap camera + reselect. On flaky 3G this is 3-4 retries minimum.
**Fix:** add "Опитай пак" button next to error toast that re-submits the same FormData.
**LOC estimate:** ~15 LOC (cache last FormData + retry handler).

**P1-5. Loading overlay underestimates time.**
Subtitle `"обикновено отнема 3-5 секунди"` (L912). Real Gemini latency on mobile + multi-page invoice: 8-15s. After 5s users assume it's frozen and tap back. No progress indicator beyond spinner.
**Fix:** change subtitle to `"обикновено 5-15 секунди"` + indeterminate progress bar with elapsed-time counter at 8s+.
**LOC estimate:** ~10 LOC.

#### P2 (medium — nice to fix)

**P2-1. Dead code at delivery.php L294.** `$hv = !empty($item['has_variations_hint']) ? 'unknown' : 'unknown';` — both branches identical. The OCR's `has_variations_hint` is ignored. Should set `'true'` for true branch (or stay 'unknown' deliberately and remove ternary).

**P2-2. Undo button claimed but missing.** Toast says `"Заприходено · 5 sec undo"` (L1061) but no undo button exists. Either implement (api_uncommit) or remove "5 sec undo" from toast.

**P2-3. Currency hard-coded EUR.** L148: `'expected_currency' => 'EUR'`. Bulgaria pre-EUR (until 8.8.2026 per BIBLE) handles BGN invoices. OCRRouter prompts Gemini with "Очаквана валута: EUR" → may misread BGN amounts.

**P2-4. Multiple separate invoices conflated.** File picker accepts `multiple` and OCRRouter does multi-page auto-stitch. Two separate invoices in one upload → merged into one delivery. No detection.

**P2-5. resolveOrCreateProduct's supplier_product_code lookup queries `delivery_items` table (L275-282), not products. This caches the product_id from prior delivery — works but bypasses products.code which is the canonical SKU field.**

**P2-6. No rate-limit / abuse guard on `?api=ocr_upload`.** Each call costs ~$0.001-0.003 (Gemini Flash). Malicious tenant could spam. Add per-tenant daily quota.

**P2-7. `api_ocr_upload` doesn't honor `expected_currency` from POST body.** L482 only honors it in OCRRouter HTTP endpoint mode. delivery.php hard-codes EUR.

#### Voice fallback / Mobile UX gaps

- `modDelStartVoice` — стуб, виж P0-1.
- Camera input has `capture="environment"` — opens rear camera on iOS/Android. Good. No UX for photo retake before submit (user must cancel + re-pick).
- Bottom sheet has no swipe-to-dismiss handle (just grip visual).
- No optimistic UI on approve/defective — page reloads on each save.
- File size is checked server-side (>10MB rejected post-upload). On 3G a 9MB upload + reject = 30s wasted. Add client-side check.

#### Schema completeness

Current code consumes these columns (assumed live per BIBLE §14.x):
- `deliveries`: id, tenant_id, store_id, supplier_id, user_id, invoice_number, total, currency_code, status (`reviewing|committed|voided|superseded|draft|pending`), invoice_type (`clean|semi|manual`), ocr_raw_json, content_signature, delivered_at, payment_status, payment_due_date, payment_terms_days (joined from suppliers), has_mismatch, mismatch_summary, committed_by, committed_at, locked_at, auto_close_reason, created_at
- `delivery_items`: tenant_id, store_id, supplier_id, delivery_id, product_id, quantity, cost_price, total, currency_code, line_number, product_name_snapshot, supplier_product_code, pack_size, vat_rate_applied, original_ocr_text, variation_pending, received_condition (`new|damaged|expired|wrong_item`)
- `delivery_events`: tenant_id, store_id, delivery_id, user_id, event_type, payload (JSON)
- `supplier_defectives`: + reason (`damaged|expired|wrong_item|quality_issue|other`), status (`pending|...`), created_by

**Schema gap:** `BIBLE_v3_0_TECH.md` does **not** contain explicit `CREATE TABLE deliveries / delivery_items / delivery_events / supplier_defectives` statements (grep returns zero matches). Schema is implicit — used by code but not documented. Pre-beta should sync BIBLE §14 with `SHOW COLUMNS FROM deliveries` to prevent next session's drift (analogous to S87.BIBLE.SYNC for sales table).

### C. Recommended Pre-Beta Fixes

| # | Item | Priority | LOC | Risk if skipped |
|---|---|---|---|---|
| 1 | Fix voice fallback dead-end (P0-1) | P0 | ~10 | First user with blurry photo gets stuck |
| 2 | Sanitize OCR error messages (P0-2) | P0 | ~15 | Eни видя `HTTP 503` and stops trusting AI |
| 3 | Defective proactive prompt §E1 (P0-3) | P0 | ~12 | Defectives undercounted → AR aging wrong |
| 4 | Compute has_mismatch on commit (P1-2) | P1 | ~25 | Hub KPI/filter shows fake zeros |
| 5 | Fuzzy product matching (P1-3) | P1 | ~20 | 10-20 phantom products per invoice |
| 6 | Auto-pricing wire-up §C8 (P1-1) | P1 | ~30 | Eни sets retail by guess |
| 7 | OCR retry button (P1-4) | P1 | ~15 | 3G users abandon flow |
| 8 | Loading time copy + progress (P1-5) | P1 | ~10 | Perceived freeze at 5s+ |
| 9 | BIBLE §14 schema sync | P1 | doc-only | Next session schema drift risk |
| 10 | Remove L294 dead code, undo claim, etc. (P2) | P2 | ~5 | Cosmetic |

**Pre-beta commit budget:** P0 alone = ~37 LOC, 1 commit. P0 + P1 = ~117 LOC, 4-5 commits. Both are achievable in 10 days alongside other modules.

### D. Post-Beta Nice-to-Haves

1. **Voice path full implementation** — GROQ Whisper STT + structured row-by-row dictation (`"Коз 5 чифта по 28"` → parser). ~150 LOC + STT integration.
2. **Order-level mismatch (§D)** — link delivery to purchase order, compute qty diffs, show 4 dialog scenarios (D1-D4). Requires `purchase_orders` table + matching logic. ~200 LOC.
3. **Multi-currency support** — read `tenants.currency` instead of hard-coded EUR; per-tenant prompt template variant.
4. **Multi-invoice detection** — when multiple files uploaded, ask if same invoice (multi-page) or separate (split into N deliveries).
5. **Optimistic UI** — no page reload on approve/defective.
6. **Client-side image compression** — reduce 9MB → 2MB before upload on slow networks.
7. **Photo retake on entry** — preview captured image, allow retake before OCR call.
8. **Gemini key rotation** — `GEMINI_API_KEY_2` is defined in config but unused; add round-robin / fallback on 429.
9. **Per-tenant OCR quota** — daily/monthly limit, dashboard for cost tracking.
10. **Confidence routing in client** — if `status='AUTO_ACCEPTED'` skip review screen (one-tap commit). Currently every OCR goes to review regardless of confidence.
11. **Trigram fuzzy match** — pg_trgm-style for products, suppliers.
12. **Real undo** — `api_uncommit` reversing inventory + stock_movements within 5s window.
13. **Delivery edit after commit** — currently locked; spec doesn't forbid edit-with-audit but UI is read-only.
14. **PDF page-by-page UI** — when multi-page PDF uploaded, show page indicator + per-page confidence.
15. **Field-level uncertainty highlight** — `uncertain_fields` is returned but never rendered. Could yellow-tint the specific cells in review.

---

## 5. Confidence Levels of This Audit

- **High confidence:** file mapping, code flow trace, P0/P1 identifications, schema column inventory.
- **Medium confidence:** LOC estimates for fixes (assume helpers exist; if they don't, +20-50%).
- **Low confidence (could not verify):**
  - Whether `GEMINI_API_KEY` actually has a valid value in `/etc/runmystore/api.env` (file is mode 640, not readable to audit user).
  - Whether `deliveries` / `delivery_items` tables actually exist with the assumed schema (no DB access during audit; code is consistent so very likely yes).
  - Whether `pricing-engine.php::suggestRetail()` (or equivalent) exists with the signature P1-1 assumes.
  - Real Gemini latency on Bulgarian mobile networks — assertion in P1-5 is based on general 2.5-flash performance, not measured.

---

## 6. Open Questions for Тихол

1. **P0-1 voice fix:** strip fallback trigger now (10 LOC) or invest in full STT before beta (150 LOC, S99 territory)?
2. **Currency:** is Eни a EUR tenant by 14.05? If yes, ignore P2-3. If BGN tenant exists pre-EUR-migration, P2-3 becomes P1.
3. **Defective prompt §E1:** acceptable as `confirm()` browser dialog, or design a full glass-card prompt? Confirm is 5 LOC; full UI is ~30.
4. **Mismatch compute (P1-2):** invoice-level only (header.total vs sum) is straightforward. Order-level requires purchase_orders linkage — is that wired in beta scope?
5. **AUTO_ACCEPTED short-circuit:** spec wants confidence>0.92 to skip review (post-beta D6 gives Eни an "AI handled it" tile). Should we add it pre-beta or stay safer with always-review?

---

**End of audit.**
