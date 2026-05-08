<?php
/**
 * S118 PATCH 10/11 — mark-insight-shown.php CSRF guard
 *
 * SEVERITY: LOW (4 actions: shown / tapped / dismissed / snoozed — idempotent
 *           bookkeeping; no destructive impact; cooldown logic prevents duplicate
 *           writes anyway)
 * TARGET FILE: mark-insight-shown.php (57 lines)
 *
 * Audit §24 lists this as LOW. Patching for parity (every state-mutating endpoint
 * gets CSRF protection — leaves no inconsistencies for future auditors).
 *
 * APPLY INSTRUCTIONS:
 *   1. Open mark-insight-shown.php in editor.
 *   2. Line 11 already has `require_once __DIR__ . '/config/helpers.php';` ✓
 *   3. After the auth check (line 18 — `if (empty($_SESSION['user_id']))`) and BEFORE
 *      the method check (line 21 — `if ($_SERVER['REQUEST_METHOD'] !== 'POST')`),
 *      insert SERVER GUARD.
 *      OR equivalently: AFTER line 25 (`echo json_encode(['ok'=>false,'err'=>'method'])` exit)
 *      and BEFORE line 27 (`$tenant_id = (int)$_SESSION['tenant_id']`).
 *   4. Patch caller in chat.php / products.php inline JS — add `_csrf` to FormData.
 *   5. Verify: TEST_PLAN.md §10.
 */

// ═══════════════════════════════════════════════════════════════════════
// SERVER GUARD — insert at mark-insight-shown.php line 26 (after method check, before $tenant_id)
// ═══════════════════════════════════════════════════════════════════════

// S118.CSRF — guard idempotent bookkeeping endpoint for parity.
$__csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
if (!csrfCheck($__csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'csrf']);
    exit;
}


// ═══════════════════════════════════════════════════════════════════════
// CALLER PATCH (chat.php — pill click handler)
// ═══════════════════════════════════════════════════════════════════════
//
// BEFORE:
//   var fd = new FormData();
//   fd.append('topic_id', t);
//   fd.append('action', 'tapped');
//   fetch('mark-insight-shown.php', { method: 'POST', body: fd })
//
// AFTER:
//   var fd = new FormData();
//   fd.append('topic_id', t);
//   fd.append('action', 'tapped');
//   fd.append('_csrf', window.RMS_CSRF || '');           // ← NEW
//   fetch('mark-insight-shown.php', {
//       method: 'POST',
//       headers: { 'X-CSRF-Token': window.RMS_CSRF || '' },  // ← NEW
//       body: fd
//   })

// END OF PATCH 10
