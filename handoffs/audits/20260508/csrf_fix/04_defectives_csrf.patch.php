<?php
/**
 * S118 PATCH 04/11 — defectives.php CSRF guard
 *
 * SEVERITY: HIGH (3 POST handlers: return_all, write_off, mark_credit_received —
 *           all mutate supplier_defectives + supplier credit ledger)
 * TARGET FILE: defectives.php (391 lines)
 *
 * APPLY INSTRUCTIONS:
 *   1. Open defectives.php in editor.
 *   2. After line 16 (`require_once __DIR__ . '/config/helpers.php';`) and BEFORE
 *      line 18 (`$pdo = DB::get();`), insert the SERVER GUARD block below.
 *   3. defectives.php has NO traditional <form>. POSTs come from inline JS at
 *      lines 350 / 366 (`fetch('/defectives.php?api=return_all'...)`).
 *   4. Add JS TOKEN BOOTSTRAP after `<body>` open OR in <head>.
 *   5. Patch the 2-3 fetch() callsites — add `'X-CSRF-Token': window.RMS_CSRF`.
 *   6. Verify: TEST_PLAN.md §4.
 */

// ═══════════════════════════════════════════════════════════════════════
// SERVER GUARD — insert at defectives.php line 17 (between helpers.php and DB::get)
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
// JS TOKEN BOOTSTRAP — emit once near <body> top
// ═══════════════════════════════════════════════════════════════════════
?>
<script>window.RMS_CSRF = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php

// ═══════════════════════════════════════════════════════════════════════
// FETCH CALLSITE PATCHES (defectives.php inline JS, 2 sites)
// ═══════════════════════════════════════════════════════════════════════
//
// LINE 350 — BEFORE:
//   fetch('/defectives.php?api=return_all', { method: 'POST', body: fd })
// LINE 350 — AFTER:
//   fd.append('_csrf', window.RMS_CSRF || '');
//   fetch('/defectives.php?api=return_all', {
//       method: 'POST',
//       headers: { 'X-CSRF-Token': window.RMS_CSRF || '' },
//       body: fd
//   })
//
// LINE 366 — same pattern for `?api=write_off`.

// ═══════════════════════════════════════════════════════════════════════
// AJAX ROUTE LIST
// ═══════════════════════════════════════════════════════════════════════
//
// ?api=return_all — bulk-return defective items to supplier
// ?api=write_off  — write off as loss
// ?api=mark_credit_received — supplier credit reconciliation (если съществува)

// END OF PATCH 04
