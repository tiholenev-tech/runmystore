# PRODUCTS_HARDENING_PHASE1 — Internal hardening sweep before live wizard testing

**Spec:** `S97.PRODUCTS.HARDENING_PHASE1` (Standing Rule #29 — Module Hardening Protocol Phase 1)
**Date:** 2026-05-05
**Branch:** `main`
**Range:** `7b52853..5dec72c` (S97.PRODUCTS commits)
**Modules touched:** `products.php`, `product-save.php`, `inventory.php` (+ reuse of `config/helpers.php` CSRF helpers shipped in S97.SALE.HARDEN_PH4)
**Cumulative LOC:** +434 / −67 across 4 files

---

## Phase status

| # | Phase | Status | Commit | LOC ± |
| - | --- | --- | --- | --- |
| 0 | Audit | DONE (read-only) | — | — |
| 1 | Image upload hardening | **DONE** | `33914a8` | +95 / −13 |
| 2 | Price/qty guards | **DONE** | `cc048fb` | +28 / −0 |
| 3 | CSRF sweep | **DONE** | `d2edd97` | +54 / −10 |
| 4 | Rate limiting | **DONE** | `502eb66` | +48 / −0 |
| 5 | Audit log sweep | **DONE** | `730690f` | +23 / −3 |
| 6 | Barcode uniqueness | **DONE (app-level)** + flagged | `475dc39` | +28 / −0 |
| 7 | XSS hardening | **VERIFIED**, no code change | — | 0 |
| 8 | Input validation sweep | **DONE** | `5dec72c` | +74 / −0 |
| 9 | Documentation | **DONE** (this file) | (next) | — |

`products.php`: 13468 → 13628. `product-save.php`: 595 → 756. `inventory.php`: 458 → 461.

---

## Audit findings (Phase 0)

| Защита | Status before | Action taken |
| --- | --- | --- |
| CSRF | ❌ none on products.php / product-save.php (only ai-brain endpoints had it) | reuse `csrfToken/csrfCheck` from `config/helpers.php`; gate every POST + `?delete` |
| File size limits | ❌ none on `?ajax=upload_image`; ❌ none on `?ajax=import_csv` | layered checks (raw / base64 / decoded); 10MB CSV cap |
| MIME server-side check | ❌ relied on data: prefix only | `finfo::buffer()` over decoded blob + ext whitelist + getimagesizefromstring |
| Image dimension sanity | ❌ none | 1..8000 px each side |
| Filename sanitization | ✅ structural (server-derived path only) | none needed |
| Rate limiting | ❌ none | sliding-window per session: save 30/min, search 100/min, image 10/min, csv 5/min, AI 20/min |
| Audit log calls | 5 raw INSERTs across both files | added `upload_image` + `save_labels` (rolled-up) |
| Tenant guards in product-save | ✅ 63 SQL queries reference `tenant_id` | added FK tenant guards on `category_id` / `supplier_id` |
| Negative price/qty | ❌ none — straight `(float)` / `(int)` cast | reject negatives, cap > 1,000,000 |
| EAN UNIQUE constraint | ⚠️ app-level soft-warning only | hard 409 on duplicate barcode (per-tenant); DB UNIQUE flagged for Tihol |
| `esc()` coverage | ✅ 114 calls; auto-escape via `option.textContent`, input `.value` | verified, no change |

---

## What changed

### Phase 1 — Image upload hardening (`33914a8`)

`products.php?ajax=upload_image` receives a base64 data: URI in JSON, **not** `$_FILES`, so the spec's `$_FILES['image']['size']` checks were adapted. Three layered size checks gate each allocation:

* raw `php://input` → 8MB cap (rejects before `json_decode`)
* base64 body → 7MB cap (rejects before `base64_decode`)
* decoded blob → 5MB cap (the real "image too big" line)

Then defence-in-depth on content:

* Declared ext (`data:image/X;base64,`) whitelisted to `{jpg,png,webp,gif}`
* `finfo::buffer()` over the decoded bytes — rejects polyglots that lie in their data: header
* `getimagesizefromstring()` — must be a real image with sane dims (1..8000 px each side)
* Disk write failures now log to `error_log` and return 500 (was silent — UI got `ok=true` with an empty file)

`products.php?ajax=import_csv` was completely unguarded:

* `UPLOAD_ERR_OK` guard (was treating partials as success)
* 10MB file size cap
* `.csv` extension whitelist
* 100K row cap to bound memory of `all_rows[]` returned to the wizard

### Phase 2 — Price/qty guards (`cc048fb`)

Voice flow, AI scan and the occasional manual misclick can deliver `-1` or `9.9e6` values that would silently corrupt margin reports and the inventory ledger. Adds an early-exit guard right after the "name required" check:

* cost / retail / wholesale price < 0 → 422 `negative_price`
* `min_quantity` < 0 → 422 `negative_qty`
* `initial_qty` < 0 → 422 `negative_qty`
* any price > 1,000,000 → 422 `price_too_high`
* any qty > 1,000,000 → 422 `qty_too_high`

Each rejection carries a machine-readable error code so the wizard can show a per-field hint.

### Phase 3 — CSRF sweep (`d2edd97`)

Wires the generic `csrfToken()` / `csrfCheck()` helpers (shipped in `config/helpers.php` during S97.SALE.HARDEN_PH4) into every product mutation path.

* `product-save.php`: gate POSTs and `?delete=id` on `X-CSRF-Token` header. Read-only GETs (`?get`, `?stock`, `?variants`, `?categories`) stay open.
* `products.php`: single check at the top of the AJAX block — every POST to `?ajax=*` must carry a valid token.
* Mismatch → 403 + `{error:'csrf'}`.

Client side:

* `products.php` mints `window.RMS_CSRF` from `csrfToken()`. The `api()` wrapper auto-attaches `X-CSRF-Token` on every non-GET and handles the 403 rebound (toast + reload). Five raw `fetch()` callers that bypass `api()` (`add_color × 2`, `delete_color × 2`, `revert_change`) now also attach the header explicitly.
* `inventory.php`: requires `config/helpers.php`, mints `window.RMS_CSRF`, and attaches the token to all four cross-page POSTs (`add_supplier`, `add_category`, `add_subcategory`, `product-save`).

`products_fetch.php` is an orphan (no callers found in repo) and was left untouched — flagged.

### Phase 4 — Rate limiting (`502eb66`)

Per-session sliding-window limiter; caps tuned so legit POS/inventory work never trips them:

* `product-save.php` (create/edit/delete): **30/min**
* `upload_image`: **10/min**
* search + barcode lookup: **100/min** combined
* `import_csv`: **5/min** (each file up to 10MB)
* `ai_scan` / `ai_description` / `ai_code` / `ai_assist` / `ai_image` / `ai_analyze`: **20/min** combined (these cost real money)

429 + `Retry-After` + `{error:'rate_limit', retry_after}` envelope.

### Phase 5 — Audit log sweep (`730690f`)

Two product-mutation paths were silent in the audit log:

* `upload_image`: `products.image_url` change had no audit row at all. Now snapshots old → new URL plus the detected MIME and `size_bytes` so a reviewer can spot suspicious uploads (e.g. an image flapping every request) without needing the file system.
* `save_labels`: bulk `min_quantity` update wrote N rows without any audit trail. Now writes a single rolled-up audit row per request with `{bulk:true, count, changes:[...]}` in `new_values` — keeps the log readable when a wizard pass touches dozens of variants.

CREATE / EDIT / DELETE in product-save.php already had audit rows so no change there.

### Phase 6 — Barcode uniqueness (`475dc39`)

The existing S88.BUG#6 soft-duplicate guard at `product-save.php:223-278` returns `{duplicate:true, matches:[...]}` and lets the user confirm-through with `confirm_duplicate=true`. That's appropriate for name/code matches but **wrong** for barcode: two SKUs sharing one EAN-13 silently misroute sales (`barcode_lookup()` at sale.php returns the first match).

Adds a HARD uniqueness check after the soft-duplicate block:

* Runs on both create AND edit (excludes self via `id<>?`)
* Ignores `confirm_duplicate` flag — barcode must always be unique
* 409 + `{error:'duplicate_barcode', existing_id, existing_name}`

### Phase 7 — XSS hardening (verified, no code change)

Spot-check sweep:

* All 5 templated `${p.field}` uses with text content already pass through `esc()` (line 5473).
* Two `${p.image_url}` interpolations use server-generated paths only (`/uploads/products/T/PID_TIME.EXT`) — `<img src>` injection is structurally impossible.
* `showToast()` (line 4769) escapes its arg.
* `option.textContent = d.name` and `descEl.value = d.description` use auto-escaping DOM properties.
* AI-generated fields go through DB then back out via `esc()` on render — stored XSS via prompt injection cannot escape the DOM.

### Phase 8 — Input validation sweep (`5dec72c`)

Three layered checks per field: NUL-byte strip, length cap matching the DB column, format gate where the field has structure.

Lengths:

* `name` 500 / `code` 64 / `barcode` 100 / `composition` 500 / `origin_country` 100 / `description` 5000 / `location` 100 / `unit` 16
* `variants_batch` 1MB before `json_decode`

Format gates:

* `barcode`: printable-ASCII (alnum + `_-+/.`)
* `vat_rate`: must be 0..100 if provided
* `category_id` / `supplier_id`: re-checked against tenant_id (DevTools could otherwise tag with foreign-tenant FK)

NUL bytes stripped from every text field — protects against SQL/log truncation attacks.

---

## Outstanding items / flags for Tihol

### Schema gaps (not changed in auto-mode per R4)
1. **`UNIQUE (tenant_id, barcode)` on `products`** — closes the SELECT-vs-INSERT race window left after Phase 6's app-level check. Will fail if duplicates already exist; clean those first:
   ```sql
   -- Run this first to find existing duplicates:
   SELECT tenant_id, barcode, COUNT(*) AS n
   FROM products WHERE barcode IS NOT NULL AND barcode <> ''
   GROUP BY tenant_id, barcode HAVING n > 1;

   -- Then, after cleanup:
   ALTER TABLE products ADD UNIQUE KEY uk_tenant_barcode (tenant_id, barcode);
   ```

### Code references still open
2. **`products_fetch.php`** — orphan file with the same five raw POST fetches that products.php has. No caller found, but if it gets wired in later it'll need the same CSRF + rate limit treatment. Either delete or harden.
3. **AI endpoint cost ceiling** — Phase 4 caps AI calls at 20/min/session, but doesn't track token cost. A separate spend-cap (per tenant per day) would be a better terminal guard.
4. **CSP header on products.php** — same situation as sale.php (inline script + Capacitor APK + Google Fonts). Deferred for a separate nonce-based design pass.

### Push status
- **All commits live on `main` locally.**
- **Push to `origin main` was denied by the harness** during the earlier S97.SALE pass; same policy in effect here. Tihol/operator should run `git push origin main` manually after review.

---

## Browser test plan

> Run after pulling these commits on staging. Each test below assumes a logged-in seller/manager session.

### Image upload (Phase 1)

1. **Upload 6MB image** — open wizard, select a 6MB JPG.
   *Expect:* 413 + toast "Снимката е твърде голяма (макс. 5MB)". No file written.
2. **Upload .exe renamed to .jpg** — base64-encode any binary, set data:image/jpeg prefix, send via DevTools.
   *Expect:* 415 + "Само JPEG/PNG/WebP/GIF снимки" — caught by `finfo` even though declared MIME was image/jpeg.
3. **Upload 9000×9000 PNG** — generate via Photoshop/canvas.
   *Expect:* 422 + "Невалидна снимка или размери > 8000px".
4. **Upload 11MB CSV** to import_csv.
   *Expect:* 413 + "CSV-ът е твърде голям (макс. 10MB)".

### Price/qty (Phase 2)

5. **Save product with `retail_price: -10`** — DevTools override `S.wizData.retail_price = -10`, submit.
   *Expect:* 422 + `negative_price`.
6. **Save product with `min_quantity: 9999999`**.
   *Expect:* 422 + `qty_too_high`.

### CSRF (Phase 3)

7. **Missing token** — DevTools intercept fetch and strip X-CSRF-Token before submit.
   *Expect:* 403 + `{error:'csrf'}` + auto-reload.
8. **Inventory.php → product-save** — verify token ships from inventory.php (look for `X-CSRF-Token` header in DevTools Network tab).

### Rate limit (Phase 4)

9. **Save 31 products in 60s** via DevTools console `for` loop.
   *Expect:* first 30 succeed, the rest 429 with `Retry-After`.
10. **AI scan flood** — call `?ajax=ai_scan` 21 times.
    *Expect:* 21st returns 429.

### Audit log (Phase 5)

11. After uploading a new image, run:
    ```sql
    SELECT * FROM audit_log WHERE table_name='products'
    AND action='update' AND source_detail='upload_image'
    ORDER BY id DESC LIMIT 1;
    ```
    *Expect:* row with `old_values.image_url` = previous URL, `new_values.image_url` = new URL + `mime` + `size_bytes`.

### Barcode (Phase 6)

12. **Duplicate barcode** — create product with barcode `1234567890123`. Try to create a second product with the same barcode in the same tenant.
    *Expect:* 409 + `duplicate_barcode` + `existing_id` and `existing_name` in payload.

### Input validation (Phase 8)

13. **Name 600 chars long** — paste a long string into wizard name.
    *Expect:* 422 + `name_too_long`.
14. **Foreign-tenant `category_id`** — DevTools override `S.wizData.category_id = 99999`.
    *Expect:* 422 + `invalid_category`.
15. **Variant blob > 1MB** — DevTools `S.wizData.variants_batch = "x".repeat(1100000)` then submit.
    *Expect:* 413 + `variants_too_large`.
