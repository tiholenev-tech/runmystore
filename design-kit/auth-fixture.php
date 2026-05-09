<?php
/**
 * VISUAL GATE auth-fixture v1.1 — sets fake $_SESSION for headless rendering.
 *
 * SAFETY:
 *  - Loaded ONLY by design-kit/visual-gate-router.php in the temporary
 *    `php -S 127.0.0.1:8765` process spawned by visual-gate.sh.
 *  - The php -S process owns its own session storage path (the OS default
 *    /tmp/sess_* of that process tree). Production (Apache + real users)
 *    is never touched.
 *  - Refuses to run if SAPI is not "cli-server" — defence-in-depth so this
 *    file cannot be hit accidentally from a real Apache vhost.
 *  - Refuses to run unless VG_AUTH=1 — no implicit fixture activation.
 *
 * Inputs (env vars):
 *   VG_USER_ID    int   default 1
 *   VG_ROLE       str   default "admin"  (also written to legacy "role" key
 *                                          which chat.php / products.php read)
 *   VG_TENANT_ID  int   default 1
 *   VG_STORE_ID   int   default 1        (chat.php / life-board.php read this)
 *   VG_USER_NAME  str   default "Visual Gate Tester"
 *
 * Notes:
 *  - Spec section 13 calls for $_SESSION['user_role']; existing protected
 *    files (chat.php:23, life-board.php:24, products.php:23) read $_SESSION['role'].
 *    We populate BOTH so the fixture works regardless of which key the file
 *    consults.
 *  - This file MUST be require'd before the target script's session_start()
 *    runs — the router does this.
 */

if (PHP_SAPI !== 'cli-server') {
    http_response_code(500);
    error_log('auth-fixture refused: SAPI=' . PHP_SAPI . ' (cli-server only)');
    exit('auth-fixture: refused (wrong SAPI)');
}

if (getenv('VG_AUTH') !== '1') {
    return;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['user_id']    = (int)(getenv('VG_USER_ID') ?: 1);
$_SESSION['tenant_id']  = (int)(getenv('VG_TENANT_ID') ?: 1);
$_SESSION['store_id']   = (int)(getenv('VG_STORE_ID') ?: 1);

$role = getenv('VG_ROLE') ?: 'admin';
$_SESSION['user_role']  = $role;
$_SESSION['role']       = $role;

$name = getenv('VG_USER_NAME') ?: 'Visual Gate Tester';
$_SESSION['user_name']  = $name;
$_SESSION['name']       = $name;

$_SESSION['ui_mode']    = 'detailed';
$_SESSION['logged_in']  = true;
$_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$_SESSION['login_time'] = time();

error_log(sprintf(
    'auth-fixture init: user_id=%d role=%s tenant=%d store=%d',
    $_SESSION['user_id'],
    $_SESSION['role'],
    $_SESSION['tenant_id'],
    $_SESSION['store_id']
));
