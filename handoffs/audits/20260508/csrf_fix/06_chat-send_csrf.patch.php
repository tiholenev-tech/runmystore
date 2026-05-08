<?php
/**
 * S118 PATCH 06/11 — chat-send.php CSRF guard
 *
 * SEVERITY: MEDIUM (3 POST handlers; JSON body via php://input — partial protection
 *           via Content-Type=application/json forcing CORS preflight, BUT bypassable
 *           via XSS or wildcard CORS. See S116 §40-47.)
 * TARGET FILE: chat-send.php (582 lines)
 *
 * SPECIAL: This endpoint reads JSON via `file_get_contents('php://input')`, so
 *          $_POST['_csrf'] is EMPTY for normal callers. Token MUST come via
 *          X-CSRF-Token header. JS callers in chat.php must be patched to send it.
 *
 * APPLY INSTRUCTIONS:
 *   1. Open chat-send.php in editor.
 *   2. After line 17 (`require_once __DIR__ . '/ai-safety.php';`) and BEFORE
 *      line 19 (`header('Content-Type: ...')`), insert:
 *        require_once __DIR__ . '/config/helpers.php';
 *   3. After the auth check (line 26 — `if (empty($_SESSION['user_id']))`) and BEFORE
 *      `$body = json_decode(...)` at line 33, insert SERVER GUARD.
 *      NOTE: Header-only check — JSON body has no `_csrf` field.
 *   4. Patch JS callers in chat.php (and any other caller) — the rmsOpenChat / chat
 *      submit flow currently sends raw JSON; needs `X-CSRF-Token: window.RMS_CSRF`.
 *   5. Verify: TEST_PLAN.md §6.
 */

// ═══════════════════════════════════════════════════════════════════════
// SERVER GUARD — insert at chat-send.php line 27 (after auth, before json_decode)
// ═══════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/config/helpers.php';   // ← add this line at top with other requires

// S118.CSRF — header-only (JSON body has no $_POST['_csrf']).
$__csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrfCheck($__csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'csrf']);
    exit;
}


// ═══════════════════════════════════════════════════════════════════════
// CALLER PATCH (chat.php inline JS — main chat send flow)
// ═══════════════════════════════════════════════════════════════════════
//
// BEFORE:
//   fetch('chat-send.php', {
//       method: 'POST',
//       headers: { 'Content-Type': 'application/json' },
//       body: JSON.stringify({ message: ..., supplier_focus: ... })
//   })
//
// AFTER:
//   fetch('chat-send.php', {
//       method: 'POST',
//       headers: {
//           'Content-Type': 'application/json',
//           'X-CSRF-Token': window.RMS_CSRF || ''                  // ← NEW
//       },
//       body: JSON.stringify({ message: ..., supplier_focus: ... })
//   })
//
// WHERE: chat.php has multiple chat-send.php fetch sites (search input submit,
//        retry, voice mic, AI follow-up). All must be patched. Use:
//          grep -nE "fetch\(['\"]chat-send\.php" chat.php
//        Each site needs the X-CSRF-Token header added.

// ═══════════════════════════════════════════════════════════════════════
// JS TOKEN BOOTSTRAP — in chat.php (or shell-init.php for app-wide reuse)
// ═══════════════════════════════════════════════════════════════════════
//
// chat.php emits the token in <head>:
?>
<script>window.RMS_CSRF = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php
//
// (csrfToken() needs session_start() before it. chat.php already starts session
//  via require chain — verify with `grep session_start chat.php`.)

// ═══════════════════════════════════════════════════════════════════════
// JSON-BODY POST HANDLER LIST (3 — all guarded by single top-level check)
// ═══════════════════════════════════════════════════════════════════════
//
// (Single endpoint; routing is internal via $body['action'] or message intent.)

// END OF PATCH 06
