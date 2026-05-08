<?php
/**
 * S118 PATCH 08/11 — register.php CSRF guard
 *
 * SEVERITY: MEDIUM (creates tenant + user; pre-auth so abuse vector is "register
 *           a tenant on victim's behalf" rather than "act as victim". Still worth
 *           protecting per S116 §51 to prevent pre-auth state pollution + login-CSRF.)
 * TARGET FILE: register.php (201 lines)
 *
 * APPLY INSTRUCTIONS:
 *   1. Open register.php in editor.
 *   2. After line 3 (`require_once __DIR__ . '/config/database.php';`), add:
 *        require_once __DIR__ . '/config/helpers.php';
 *        $__reg_csrf = csrfToken();   // mints into $_SESSION['app_csrf']
 *   3. AT THE START of the `if ($_SERVER['REQUEST_METHOD'] === 'POST') {` block
 *      (line 12), insert SERVER GUARD as FIRST statement.
 *   4. Inside the form rendered at line 115 (`<form class="..." method="POST" action="register.php">`),
 *      add the FORM HIDDEN INPUT just after the opening `<form>` tag.
 *   5. Verify: TEST_PLAN.md §8.
 */

// ═══════════════════════════════════════════════════════════════════════
// SERVER GUARD — insert as FIRST line inside the POST branch (line 13)
// ═══════════════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // S118.CSRF — pre-auth login-CSRF protection.
    $__csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
    if (!csrfCheck($__csrf)) {
        http_response_code(403);
        // For HTML forms, render the registration page with an error rather than JSON.
        $error = 'Сесията изтече. Презареди страницата и опитай отново.';
        // (continue to render path; fall through past the validation logic)
        goto render_form;
    }
    // ... existing registration logic continues below ...
}

render_form:


// ═══════════════════════════════════════════════════════════════════════
// FORM HIDDEN INPUT — insert inside <form> at line ~116
// ═══════════════════════════════════════════════════════════════════════
?>
<form class="mx-auto max-w-[400px]" method="POST" action="register.php">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <!-- ... existing form fields ... -->
</form>
<?php

// ═══════════════════════════════════════════════════════════════════════
// SIMPLER ALTERNATIVE — without `goto`
// ═══════════════════════════════════════════════════════════════════════
//
// If `goto` feels heavy, restructure the POST branch to fall through cleanly:
//
//   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//       $__csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
//       if (!csrfCheck($__csrf)) {
//           $error = 'Сесията изтече. Презареди страницата и опитай отново.';
//       } else {
//           // ... existing registration logic (validation, INSERT INTO tenants, etc.) ...
//       }
//   }
//
// (Wrap the existing POST body in `else` instead of using `goto`.)

// END OF PATCH 08
