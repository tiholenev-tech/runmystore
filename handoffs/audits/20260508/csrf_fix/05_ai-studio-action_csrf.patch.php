<?php
/**
 * S118 PATCH 05/11 — ai-studio-action.php CSRF guard
 *
 * SEVERITY: HIGH (5 POST handlers: tryon, studio, magic, retry, refund — all
 *           consume/refund magic credits + write to ai_spend_log)
 * TARGET FILE: ai-studio-action.php (262 lines)
 *
 * NOTE: This file does NOT include `config/helpers.php` currently — must add the
 *       require_once for `csrfCheck()` to be available.
 *
 * APPLY INSTRUCTIONS:
 *   1. Open ai-studio-action.php in editor.
 *   2. After line 24 (`require_once __DIR__ . '/ai-studio-backend.php';`) and BEFORE
 *      line 25 (`session_start();`), insert:
 *        require_once __DIR__ . '/config/helpers.php';
 *   3. After `session_start();` (line 25 originally — now 26 after the require),
 *      and BEFORE `header('Content-Type: ...')` (line 26 → 27), insert SERVER GUARD.
 *   4. Find all caller sites — likely products.php / products_fetch.php inline JS
 *      under "AI Studio drawer" (look for `fetch('/ai-studio-action.php'`).
 *      Add `'X-CSRF-Token': window.RMS_CSRF` header. For multipart (image uploads),
 *      ALSO append `_csrf` to FormData.
 *   5. Verify: TEST_PLAN.md §5.
 */

// ═══════════════════════════════════════════════════════════════════════
// SERVER GUARD — insert at ai-studio-action.php line 26 (after session_start, before header())
// ═══════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/config/helpers.php';   // ← add this line if not already imported

// S118.CSRF — reject cross-origin POST without valid token.
// All entries to this endpoint are POST (line 33-35 enforces); guard fires unconditionally.
$__csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
if (!csrfCheck($__csrf)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}


// ═══════════════════════════════════════════════════════════════════════
// CALLER PATCH (in products.php / products_fetch.php / chat.php inline JS)
// ═══════════════════════════════════════════════════════════════════════
//
// AI Studio drawer typically uses FormData multipart for image upload, e.g.:
//   var fd = new FormData();
//   fd.append('type', 'tryon');
//   fd.append('image', file);
//   fd.append('_csrf', window.RMS_CSRF || '');           // ← NEW
//   fetch('/ai-studio-action.php', {
//       method: 'POST',
//       headers: { 'X-CSRF-Token': window.RMS_CSRF || '' },  // ← NEW
//       body: fd
//   })
//
// For retry/refund (form-encoded, no file):
//   var fd = new FormData();
//   fd.append('type', 'retry');
//   fd.append('parent_log_id', N);
//   fd.append('_csrf', window.RMS_CSRF || '');
//   fetch('/ai-studio-action.php', {
//       method: 'POST',
//       headers: { 'X-CSRF-Token': window.RMS_CSRF || '' },
//       body: fd
//   })

// ═══════════════════════════════════════════════════════════════════════
// AJAX ROUTE LIST (5 POST types — all guarded by the single top-level check)
// ═══════════════════════════════════════════════════════════════════════
//
// type=tryon   — generate try-on image (consumes 1 magic credit)
// type=studio  — generate studio shot (1 credit)
// type=magic   — generate magic image (1 credit)
// type=retry   — re-run prior attempt (NO credit; Quality Guarantee)
// type=refund  — flip ai_spend_log row to refunded_loss + restore 1 credit

// END OF PATCH 05
