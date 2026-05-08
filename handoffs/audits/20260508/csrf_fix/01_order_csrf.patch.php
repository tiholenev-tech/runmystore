<?php
/**
 * S118 PATCH 01/11 — order.php CSRF guard
 *
 * SEVERITY: HIGH (mutates orders — create/cancel/send/mark_received)
 * TARGET FILE: order.php
 * REPRO: see TEST_PLAN.md §1
 *
 * APPLY INSTRUCTIONS:
 *   1. Open order.php in editor
 *   2. After line 23 (`require_once __DIR__ . '/config/helpers.php';`) and BEFORE
 *      line 26 (`if (!isset($_SESSION['user_id'])) ...`), insert the SERVER GUARD block below.
 *   3. Find any `<form method="POST">` in the HTML render section — order.php is mainly
 *      AJAX-driven from chat.php, so likely NO traditional forms. Verify with:
 *        grep -n '<form' order.php
 *      If matches → add the FORM HIDDEN INPUT block to each.
 *   4. Find every `fetch('/order.php?api=...', { method:'POST' ... })` callsite — these
 *      are likely in inline <script> blocks within order.php OR in chat.php / products.php.
 *      Add `'X-CSRF-Token': window.RMS_CSRF || ''` to the headers object.
 *   5. Add the JS TOKEN BOOTSTRAP block in the page <head> (or before first inline <script>).
 *   6. Verify: TEST_PLAN.md §1 (curl POST without token → 403; with token → 200).
 */

// ═══════════════════════════════════════════════════════════════════════
// SERVER GUARD — insert at order.php line 24 (between requires and auth check)
// ═══════════════════════════════════════════════════════════════════════

// S118.CSRF — reject cross-origin POST without valid token.
// Source order: X-CSRF-Token header (XHR/fetch) > $_POST['_csrf'] (HTML form).
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
// JS TOKEN BOOTSTRAP — emit once in page <head> or top of <body>
// ═══════════════════════════════════════════════════════════════════════
?>
<script>window.RMS_CSRF = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php

// ═══════════════════════════════════════════════════════════════════════
// FETCH CALLSITE PATCH — apply to every existing fetch('/order.php?api=...')
// ═══════════════════════════════════════════════════════════════════════
//
// BEFORE:
//   fetch('/order.php?api=cancel', { method: 'POST', body: fd })
//
// AFTER:
//   fetch('/order.php?api=cancel', {
//       method: 'POST',
//       headers: { 'X-CSRF-Token': window.RMS_CSRF || '' },
//       body: fd
//   })


// ═══════════════════════════════════════════════════════════════════════
// FORM HIDDEN INPUT — add inside every <form method="POST"> (if any)
// ═══════════════════════════════════════════════════════════════════════
?>
<input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<?php

// END OF PATCH 01
