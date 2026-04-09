<?php
// САМО ЗА РАЗРАБОТКА — само от localhost или с ключ
$key = $_SERVER['HTTP_X_EXEC_KEY'] ?? '';
if ($key !== 'RMS_DEV_2026_CLAUDE') {
    http_response_code(403);
    die('Forbidden');
}
$cmd = $_POST['cmd'] ?? '';
if (!$cmd) die('No command');
// Само безопасни команди
$allowed = ['grep','ls','cat','tail','head','find','php','mysql','systemctl status','git'];
$safe = false;
foreach ($allowed as $a) {
    if (str_starts_with(trim($cmd), $a)) { $safe = true; break; }
}
if (!$safe) { http_response_code(400); die('Command not allowed'); }
$output = shell_exec($cmd . ' 2>&1');
header('Content-Type: text/plain');
echo $output;
