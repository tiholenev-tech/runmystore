<?php
// D520 simulator endpoint — receives raw bytes that JS would send to printer,
// renders to PNG via sim_render.py, returns JSON with viewer URL.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-D520-Path');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$bytes = file_get_contents('php://input');
$size  = strlen($bytes);
if ($size === 0) {
    echo json_encode(['error' => 'empty body']);
    exit;
}

$path = $_SERVER['HTTP_X_D520_PATH'] ?? 'unknown';
$ts   = date('Ymd_His');
$tag  = substr(md5($bytes), 0, 6);
$base = $ts . '_' . $path . '_' . $tag;

$bin_path  = "/var/www/runmystore/sim/$base.bin";
$png_base  = "/var/www/runmystore/sim/$base";
$rel_url   = "/sim/$base.png";

file_put_contents($bin_path, $bytes);

$out = []; $rc = 0;
exec("python3 /var/www/runmystore/sim_render.py " . escapeshellarg($bin_path) . " " . escapeshellarg($png_base) . " 2>&1", $out, $rc);

echo json_encode([
    'ok' => ($rc === 0),
    'size' => $size,
    'path' => $path,
    'png_url' => $rel_url,
    'log_url' => "/sim/$base.txt",
    'bin_url' => "/sim/$base.bin",
    'viewer_url' => "/sim/?last=1",
    'render_log' => $out,
    'render_rc' => $rc
]);
