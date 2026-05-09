<?php
/**
 * Visual Gate render_helper — convenience wrapper that points the existing
 * design-kit/auth-fixture.php at the test tenant (id=999) seeded by
 * seed_test_tenant.sql in this same directory.
 *
 * S135 (2026-05-09).
 *
 * WHY THIS EXISTS
 *   design-kit/auth-fixture.php (S134, v1.1) already sets $_SESSION for the
 *   isolated `php -S` render process. Its defaults are tenant_id=1 / store_id=1
 *   which collide with live production data. This helper re-exports VG_*
 *   environment variables pointing at tenant_id=999 / store_id=9990 — the
 *   reserved test rows in seed_test_tenant.sql — and then defers to the
 *   stock auth-fixture for actual session priming.
 *
 * INVOCATION (from a shell wrapper, not from a request):
 *   The visual-gate.sh orchestrator does NOT consume render_helper.php
 *   directly — it consumes auth-fixture.php via visual-gate-router.php.
 *   Use this file as documentation + as a thin CLI bootstrap when you
 *   want to render a target file once for ad-hoc inspection:
 *
 *     VG_TENANT_ID=999 VG_STORE_ID=9990 VG_USER_ID=9990 VG_ROLE=owner \
 *       php -S 127.0.0.1:8765 -t /home/tihol/rms-visual-gate \
 *           design-kit/visual-gate-router.php &
 *     curl -s http://127.0.0.1:8765/life-board.php > /tmp/render.html
 *     kill %1
 *
 * SAFETY MODEL
 *   Same as auth-fixture.php (refuses non-cli-server SAPI). This file
 *   piggybacks on those guards by require'ing the auth-fixture rather
 *   than duplicating its $_SESSION writes.
 */

if (PHP_SAPI !== 'cli-server' && PHP_SAPI !== 'cli') {
    http_response_code(500);
    error_log('render_helper refused: SAPI=' . PHP_SAPI);
    exit('render_helper: refused (cli-server/cli only)');
}

// Force the test tenant/store IDs unless caller already overrode them.
if (getenv('VG_TENANT_ID') === false) putenv('VG_TENANT_ID=999');
if (getenv('VG_STORE_ID')  === false) putenv('VG_STORE_ID=9990');
if (getenv('VG_USER_ID')   === false) putenv('VG_USER_ID=9990');
if (getenv('VG_ROLE')      === false) putenv('VG_ROLE=owner');
if (getenv('VG_USER_NAME') === false) putenv('VG_USER_NAME=VG Test Owner');
if (getenv('VG_AUTH')      === false) putenv('VG_AUTH=1');

// Mirror putenv into $_ENV so getenv() inside the fixture sees them.
foreach (['VG_TENANT_ID','VG_STORE_ID','VG_USER_ID','VG_ROLE','VG_USER_NAME','VG_AUTH'] as $k) {
    $_ENV[$k] = getenv($k);
}

$fixture = realpath(__DIR__ . '/../../../design-kit/auth-fixture.php');
if ($fixture === false || !is_file($fixture)) {
    http_response_code(500);
    exit('render_helper: auth-fixture.php not found at expected path');
}
require $fixture;
