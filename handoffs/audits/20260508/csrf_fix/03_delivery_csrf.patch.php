<?php
/**
 * S118 PATCH 03/11 — delivery.php CSRF guard
 *
 * SEVERITY: HIGH (10 POST handlers: ocr_upload [file], update_item, approve_item,
 *           add_defective, commit — final write to inventory + audit_log)
 * TARGET FILE: delivery.php (1073 lines)
 *
 * APPLY INSTRUCTIONS:
 *   1. Open delivery.php in editor.
 *   2. After line 22 (`require_once __DIR__ . '/config/helpers.php';`) and BEFORE
 *      line 23 (`require_once __DIR__ . '/services/duplicate-check.php';`), insert
 *      the SERVER GUARD block below.
 *   3. delivery.php has NO traditional <form> — all POSTs are AJAX from inline JS
 *      (`fetch('delivery.php?api=...')`). Add the JS TOKEN BOOTSTRAP near top of <body>.
 *   4. Patch every `fetch('delivery.php?api=...')` callsite. For `ocr_upload`
 *      (multipart file upload), use BOTH header AND `_csrf` form field:
 *        fd.append('_csrf', window.RMS_CSRF || '');
 *        fetch(..., { headers: { 'X-CSRF-Token': window.RMS_CSRF || '' }, body: fd })
 *   5. Verify: TEST_PLAN.md §3.
 */

// ═══════════════════════════════════════════════════════════════════════
// SERVER GUARD — insert at delivery.php line 23 (between helpers.php and services/*)
// ═══════════════════════════════════════════════════════════════════════

// S118.CSRF — reject cross-origin POST without valid token.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $__csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
    if (!csrfCheck($__csrf)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'csrf']);
        exit;
    }
}


// ═══════════════════════════════════════════════════════════════════════
// JS TOKEN BOOTSTRAP — emit once after <body> opens
// ═══════════════════════════════════════════════════════════════════════
?>
<script>window.RMS_CSRF = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php

// ═══════════════════════════════════════════════════════════════════════
// FETCH CALLSITE PATCHES — apply to each `fetch('delivery.php?api=...')`
// ═══════════════════════════════════════════════════════════════════════
//
// Multipart upload (ocr_upload):
//   var fd = new FormData();
//   fd.append('file', input.files[0]);
//   fd.append('_csrf', window.RMS_CSRF || '');           // ← NEW
//   fetch('delivery.php?api=ocr_upload', {
//       method: 'POST',
//       headers: { 'X-CSRF-Token': window.RMS_CSRF || '' },  // ← NEW
//       body: fd
//   })
//
// JSON or form-encoded:
//   fetch('delivery.php?api=commit', {
//       method: 'POST',
//       headers: { 'X-CSRF-Token': window.RMS_CSRF || '', 'Content-Type': 'application/json' },
//       body: JSON.stringify({ delivery_id: 42 })
//   })

// ═══════════════════════════════════════════════════════════════════════
// AJAX ROUTE LIST (10 POST handlers — all guarded by single top-level guard)
// ═══════════════════════════════════════════════════════════════════════
//
// ?api=ocr_upload    — multipart file
// ?api=update_item   — qty/cost/retail edit
// ?api=approve_item  — mark approved
// ?api=add_defective — move to supplier_defectives
// ?api=commit        — final commit to inventory + audit_log
// + 5 more (see delivery.php docblock §10)

// END OF PATCH 03
