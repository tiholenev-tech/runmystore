<?php
/**
 * S118 PATCH 07/11 — ai-color-detect.php CSRF guard
 *
 * SEVERITY: MEDIUM (1 POST handler; multipart file upload, consumes Gemini Vision
 *           credits per S116 §27)
 * TARGET FILE: ai-color-detect.php (296 lines)
 *
 * APPLY INSTRUCTIONS:
 *   1. Open ai-color-detect.php in editor.
 *   2. After line 16 (`session_start();`) and BEFORE line 17 (`header('Content-Type: ...')`),
 *      insert:
 *        require_once __DIR__ . '/config/helpers.php';
 *   3. After line 33 (`$user_id = ...`) and BEFORE line 35 (`$quota = rms_image_check_quota`),
 *      insert SERVER GUARD.
 *   4. Add `_csrf` to FormData in caller (likely products.php / products_fetch.php under
 *      "Color detect" button in AI Studio drawer):
 *        fd.append('_csrf', window.RMS_CSRF || '');
 *      AND header `X-CSRF-Token`.
 *   5. Verify: TEST_PLAN.md §7.
 */

// ═══════════════════════════════════════════════════════════════════════
// SERVER GUARD — insert at ai-color-detect.php line 34 (after $user_id, before quota)
// ═══════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/config/helpers.php';   // ← add at top with other requires

// S118.CSRF — endpoint is POST-only (line 25-27 enforces); guard fires unconditionally.
$__csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
if (!csrfCheck($__csrf)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'reason' => 'csrf']);
    exit;
}


// ═══════════════════════════════════════════════════════════════════════
// CALLER PATCH (multipart file upload from products.php / products_fetch.php)
// ═══════════════════════════════════════════════════════════════════════
//
// BEFORE:
//   var fd = new FormData();
//   fd.append('image', file);
//   fetch('ai-color-detect.php', { method: 'POST', body: fd })
//
// AFTER:
//   var fd = new FormData();
//   fd.append('image', file);
//   fd.append('_csrf', window.RMS_CSRF || '');           // ← NEW
//   fetch('ai-color-detect.php', {
//       method: 'POST',
//       headers: { 'X-CSRF-Token': window.RMS_CSRF || '' },  // ← NEW
//       body: fd
//   })
//
// Multi-image branch (?multi=1) — ALSO add `_csrf` once (one per request, not per image):
//   fd.append('count', count);
//   fd.append('image_0', file0);
//   fd.append('image_1', file1);
//   fd.append('_csrf', window.RMS_CSRF || '');           // ← NEW (one)

// END OF PATCH 07
