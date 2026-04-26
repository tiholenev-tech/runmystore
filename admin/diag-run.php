<?php
/**
 * /admin/diag-run.php — POST handler за ръчен diagnostic run.
 * Auth: tenant=7 only. Streams output на client-а.
 */

require_once __DIR__ . '/../config/database.php';
session_start();

if (($_SESSION['tenant_id'] ?? 0) !== 7) {
    http_response_code(403);
    exit('Forbidden');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no');  // disable nginx buffering за streaming
@ini_set('output_buffering', '0');
@ini_set('zlib.output_compression', '0');

$cmd = 'cd /var/www/runmystore && '
     . 'python3 tools/diagnostic/run_diag.py '
     . '--module=insights --trigger=user_command --pristine 2>&1';

$ph = popen($cmd, 'r');
if (!$ph) {
    http_response_code(500);
    exit('Cannot start diagnostic process');
}
while (!feof($ph)) {
    $line = fgets($ph);
    if ($line !== false) {
        echo $line;
        @ob_flush();
        flush();
    }
}
pclose($ph);
