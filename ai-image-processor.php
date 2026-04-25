<?php
/**
 * ai-image-processor.php — Background removal via fal.ai birefnet (S82.AI_STUDIO)
 *
 * Accepts: POST multipart with `image` file (jpg/png/webp, max 10 MB).
 * Returns: JSON {ok, url, used, remaining, plan} or {ok:false, reason, http}.
 *
 * Plan limits (FREE 0 / START 3 / PRO 10 per day) enforced via ai-image-credits.php.
 * Records usage atomically only on success.
 *
 * Requires FAL_API_KEY in /etc/runmystore/api.env. If missing, returns 503 with
 * a clear setup-required message so Tihol sees the issue.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/ai-image-credits.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

function out(int $http, array $data): void {
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(405, ['ok' => false, 'reason' => 'POST only.']);
}
if (!isset($_SESSION['tenant_id'])) {
    out(401, ['ok' => false, 'reason' => 'Не сте влезли.']);
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

$quota = rms_image_check_quota($tenant_id);
if (!$quota['allowed']) {
    out(429, ['ok' => false, 'reason' => $quota['reason'], 'plan' => $quota['plan'], 'used' => $quota['used'], 'limit' => $quota['limit']]);
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    out(400, ['ok' => false, 'reason' => 'Липсва или невалидна снимка.']);
}
$file = $_FILES['image'];
if ($file['size'] > 10 * 1024 * 1024) {
    out(413, ['ok' => false, 'reason' => 'Снимката е по-голяма от 10 MB.']);
}
$mime = mime_content_type($file['tmp_name']);
$allowed_mimes = ['image/jpeg','image/png','image/webp'];
if (!in_array($mime, $allowed_mimes, true)) {
    out(415, ['ok' => false, 'reason' => 'Поддържат се само JPG, PNG, WebP.']);
}

$fal_key = rms_api_env('FAL_API_KEY');
if (!$fal_key) {
    error_log('S82.AI_STUDIO: FAL_API_KEY missing in /etc/runmystore/api.env');
    out(503, ['ok' => false, 'reason' => 'AI Studio: липсва конфигурация. Свържи се с поддръжка.', 'setup_required' => true]);
}

$image_b64 = base64_encode(file_get_contents($file['tmp_name']));
$data_uri  = 'data:' . $mime . ';base64,' . $image_b64;

$payload = [
    'image_url' => $data_uri,
    'model'     => 'General Use (Light)',
    'output_format' => 'png',
];

$ch = curl_init('https://fal.run/fal-ai/birefnet/v2');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Key ' . $fal_key,
    ],
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 8,
]);
$response = curl_exec($ch);
$http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr     = curl_errno($ch);
$cmsg     = curl_error($ch);
curl_close($ch);

if ($cerr) {
    error_log("S82.AI_STUDIO bg_remove curl error: $cmsg");
    out(502, ['ok' => false, 'reason' => 'AI услугата не отговаря. Опитай отново.', 'http' => $http]);
}
if ($http !== 200) {
    error_log("S82.AI_STUDIO bg_remove fal.ai HTTP $http body=" . substr($response, 0, 300));
    out(502, ['ok' => false, 'reason' => 'AI грешка (' . $http . '). Опитай пак.', 'http' => $http]);
}

$data = json_decode($response, true);
$result_url = $data['image']['url'] ?? null;
if (!$result_url) {
    error_log('S82.AI_STUDIO bg_remove no image url in response: ' . substr($response, 0, 300));
    out(502, ['ok' => false, 'reason' => 'Празен отговор от AI.']);
}

rms_image_record_usage($tenant_id, $user_id, 'bg_remove');
$quota_after = rms_image_check_quota($tenant_id);

out(200, [
    'ok'        => true,
    'url'       => $result_url,
    'plan'      => $quota_after['plan'],
    'used'      => $quota_after['used'],
    'remaining' => $quota_after['remaining'],
]);
