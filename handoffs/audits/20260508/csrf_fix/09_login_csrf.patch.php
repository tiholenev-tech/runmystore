<?php
/**
 * S118 PATCH 09/11 — login.php CSRF guard
 *
 * SEVERITY: LOW (login itself doesn't typically need CSRF; protection here is
 *           defense-in-depth against login-CSRF attack — attacker logs victim
 *           into attacker's account → harvests data victim enters)
 * TARGET FILE: login.php (366 lines)
 *
 * APPLY INSTRUCTIONS:
 *   1. Open login.php in editor.
 *   2. After line 3 (`session_start();`), add:
 *        require_once __DIR__ . '/config/helpers.php';
 *        $__login_csrf = csrfToken();   // mint token in fresh session
 *   3. AT THE START of the POST branch (line 14, `if ($_SERVER['REQUEST_METHOD'] === 'POST')`),
 *      insert the SERVER GUARD before any `$email = trim($_POST['email'])` parsing.
 *   4. Inside `<form method="POST" action="">` at line 294, add the FORM HIDDEN INPUT
 *      as first child element.
 *   5. Verify: TEST_PLAN.md §9.
 */

// ═══════════════════════════════════════════════════════════════════════
// SERVER GUARD — insert as FIRST line inside the POST branch (line 15)
// ═══════════════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // S118.CSRF — login-CSRF defense (S116 §51 / §83).
    $__csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
    if (!csrfCheck($__csrf)) {
        $error = 'Сесията изтече. Презареди страницата и опитай отново.';
        // Fall through to form re-render below; do NOT process credentials.
    } else {
        // ── EXISTING LOGIN LOGIC ──
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($email && $password) {
            $tenant = DB::run("SELECT * FROM tenants WHERE email = ? AND is_active = 1", [$email])->fetch();
            if ($tenant && password_verify($password, $tenant['password'])) {
                $user = DB::run("SELECT * FROM users WHERE tenant_id = ? AND email = ? AND is_active = 1", [$tenant['id'], $email])->fetch();
                $_SESSION['tenant_id']   = $tenant['id'];
                $_SESSION['user_id']     = $user['id'] ?? null;
                $_SESSION['role']        = $user['role'] ?? 'owner';
                $_SESSION['store_id']    = $user['store_id'] ?? null;
                $_SESSION['supato_mode'] = $tenant['supato_mode'] ?? 1;
                $_SESSION['currency']    = $tenant['currency'] ?? 'EUR';
                $_SESSION['language']    = $tenant['language'] ?? 'bg';
                DB::run("UPDATE tenants SET updated_at = NOW() WHERE id = ?", [$tenant['id']]);
                header('Location: ' . ($tenant['onboarding_done'] ? 'chat.php' : 'onboarding.php'));
                exit;
            }
        }
        $error = 'Грешен имейл или парола.';
    }
}


// ═══════════════════════════════════════════════════════════════════════
// FORM HIDDEN INPUT — insert inside <form method="POST" action=""> at line 294
// ═══════════════════════════════════════════════════════════════════════
?>
<form method="POST" action="">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <!-- ... existing email/password inputs ... -->
</form>
<?php

// ═══════════════════════════════════════════════════════════════════════
// IMPORTANT — session_regenerate_id() AFTER successful login (defense in depth)
// ═══════════════════════════════════════════════════════════════════════
//
// Add `session_regenerate_id(true);` immediately after setting $_SESSION['user_id']
// and BEFORE the `header('Location: ...')`. This prevents session fixation: an
// attacker can't pre-set a session cookie and then trick the user into logging in
// (which would inherit the attacker's session ID).
//
// Suggested insertion (line ~32, after $_SESSION['language'] = ...):
//   session_regenerate_id(true);
//   DB::run("UPDATE tenants SET updated_at = NOW() WHERE id = ?", [$tenant['id']]);
//
// (Out of strict S118 scope — note for S119 hardening sweep.)

// END OF PATCH 09
