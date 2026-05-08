<?php
/**
 * S118 PATCH 02/11 — products_fetch.php CSRF guard
 *
 * SEVERITY: HIGH (10 POST handlers: state mutations + file uploads)
 * TARGET FILE: products_fetch.php  (8394 lines — large; ALL POST routes go through ?ajax=)
 *
 * NOTE: This file is structurally a stale clone/companion of products.php. products.php
 * already has the CSRF guard at line 52. products_fetch.php lacks it entirely.
 * Per S114.CLEANUP / S118 scope: add IDENTICAL guard so behavior matches.
 *
 * APPLY INSTRUCTIONS:
 *   1. Open products_fetch.php in editor.
 *   2. After line 14 (`require_once 'compute-insights.php';`) and BEFORE line 16
 *      (`$pdo = DB::get();`), insert the SERVER GUARD block below.
 *   3. Add `require_once __DIR__ . '/config/helpers.php';` if not already present
 *      (currently products_fetch.php uses config/database.php + config/config.php only).
 *   4. Add the JS TOKEN BOOTSTRAP near top of <body> in HTML render area
 *      (find the first `<body>` tag; insert immediately after).
 *   5. Patch every `fetch('products_fetch.php?ajax=*', { method: 'POST' })` callsite:
 *      add `headers: { 'X-CSRF-Token': window.RMS_CSRF || '' }`.
 *   6. For multipart `FormData` uploads (e.g. ai_scan, upload_image, ai_image), append
 *      `fd.append('_csrf', window.RMS_CSRF || '');` BEFORE the fetch — this is the
 *      fallback when the browser strips custom headers (rare but seen on old WebViews).
 *   7. Verify: TEST_PLAN.md §2.
 */

// ═══════════════════════════════════════════════════════════════════════
// SERVER GUARD — insert at products_fetch.php line 15 (between requires + DB::get)
// ═══════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/config/helpers.php';

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
// JS TOKEN BOOTSTRAP — emit once near <body> top (or in <head> after fonts link)
// ═══════════════════════════════════════════════════════════════════════
?>
<script>window.RMS_CSRF = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php

// ═══════════════════════════════════════════════════════════════════════
// FETCH CALLSITE PATCHES — global find-and-fix for ALL fetch() POSTs
// ═══════════════════════════════════════════════════════════════════════
//
// Use a regex sweep:
//   grep -nE "fetch\(['\"]products_fetch\.php\?ajax=" products_fetch.php
//   → expect 10+ hits inside inline <script>
//
// For JSON body POSTs:
//   BEFORE:
//     fetch('products_fetch.php?ajax=ai_assist', {
//         method: 'POST', body: JSON.stringify({...})
//     })
//   AFTER:
//     fetch('products_fetch.php?ajax=ai_assist', {
//         method: 'POST',
//         headers: { 'Content-Type':'application/json', 'X-CSRF-Token': window.RMS_CSRF || '' },
//         body: JSON.stringify({...})
//     })
//
// For FormData multipart POSTs:
//   BEFORE:
//     var fd = new FormData();
//     fd.append('image', file);
//     fetch('products_fetch.php?ajax=ai_scan', { method:'POST', body: fd })
//   AFTER:
//     var fd = new FormData();
//     fd.append('image', file);
//     fd.append('_csrf', window.RMS_CSRF || '');           // ← NEW
//     fetch('products_fetch.php?ajax=ai_scan', {
//         method:'POST',
//         headers: { 'X-CSRF-Token': window.RMS_CSRF || '' },  // ← NEW
//         body: fd
//     })

// ═══════════════════════════════════════════════════════════════════════
// AJAX HANDLER LIST (10 POST routes — verify all guarded by the single top-level
// SERVER GUARD above; no per-route patching required)
// ═══════════════════════════════════════════════════════════════════════
//
// Line  Route
// ----  -----
// 487   ?ajax=upload_image
// 520   ?ajax=ai_scan
// 538   ?ajax=ai_description
// 577   ?ajax=ai_code
// 589   ?ajax=ai_assist
// 615   ?ajax=add_supplier
// 625   ?ajax=add_category
// 636   ?ajax=add_subcategory
// 647   ?ajax=add_unit
// 656   ?ajax=delete_unit

// END OF PATCH 02
