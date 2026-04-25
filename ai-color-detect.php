<?php
/**
 * ai-color-detect.php — Detect product colors via Gemini Vision (S82.AI_STUDIO)
 *
 * Accepts: POST multipart with `image` file (jpg/png/webp, max 10 MB).
 * Returns: JSON {ok, colors:[{name,hex,confidence}], used, remaining, plan} or {ok:false, reason}.
 *
 * Why Gemini Vision: same key/wallet as the rest of the AI brain (chat-send.php).
 * UX-wise the user sees only "AI" — never "Gemini".
 *
 * Plan limits same as bg removal (FREE 0 / START 3 / PRO 10 per day).
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/ai-image-credits.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

function cd_out(int $http, array $data): void {
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cd_out(405, ['ok' => false, 'reason' => 'POST only.']);
}
if (!isset($_SESSION['tenant_id'])) {
    cd_out(401, ['ok' => false, 'reason' => 'Не сте влезли.']);
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

$quota = rms_image_check_quota($tenant_id);
if (!$quota['allowed']) {
    cd_out(429, ['ok' => false, 'reason' => $quota['reason'], 'plan' => $quota['plan'], 'used' => $quota['used'], 'limit' => $quota['limit']]);
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    cd_out(400, ['ok' => false, 'reason' => 'Липсва или невалидна снимка.']);
}
$file = $_FILES['image'];
if ($file['size'] > 10 * 1024 * 1024) {
    cd_out(413, ['ok' => false, 'reason' => 'Снимката е по-голяма от 10 MB.']);
}
$mime = mime_content_type($file['tmp_name']);
$allowed_mimes = ['image/jpeg','image/png','image/webp'];
if (!in_array($mime, $allowed_mimes, true)) {
    cd_out(415, ['ok' => false, 'reason' => 'Поддържат се само JPG, PNG, WebP.']);
}

$gemini_key = rms_api_env('GEMINI_API_KEY') ?: rms_api_env('GEMINI_API_KEY_2');
if (!$gemini_key) {
    error_log('S82.AI_STUDIO: GEMINI_API_KEY missing in /etc/runmystore/api.env');
    cd_out(503, ['ok' => false, 'reason' => 'AI Studio: липсва конфигурация. Свържи се с поддръжка.', 'setup_required' => true]);
}
$gemini_model = rms_api_env('GEMINI_MODEL') ?: 'gemini-2.5-flash';

$image_b64 = base64_encode(file_get_contents($file['tmp_name']));

// Strict JSON-only response — color names in Bulgarian + hex, sorted by surface area.
$prompt = <<<TXT
Анализирай снимката на този артикул (дреха, обувка или аксесоар).
Върни МАКСИМУМ 4 основни цвята на самия артикул, сортирани по доминиране.
Игнорирай фон, ръце на модела, сенки.
Отговори САМО с валиден JSON масив, БЕЗ markdown:
[{"name":"черен","hex":"#0a0a0a","confidence":0.95}, {"name":"бял","hex":"#fafafa","confidence":0.7}]
TXT;

$payload = [
    'contents' => [[
        'parts' => [
            ['text' => $prompt],
            ['inline_data' => ['mime_type' => $mime, 'data' => $image_b64]],
        ],
    ]],
    'generationConfig' => [
        'temperature'      => 0.2,
        'maxOutputTokens'  => 512,
        'responseMimeType' => 'application/json',
    ],
];

$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($gemini_model) . ':generateContent?key=' . $gemini_key;
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$response = curl_exec($ch);
$http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr     = curl_errno($ch);
$cmsg     = curl_error($ch);
curl_close($ch);

if ($cerr) {
    error_log("S82.AI_STUDIO color_detect curl error: $cmsg");
    cd_out(502, ['ok' => false, 'reason' => 'AI услугата не отговаря.']);
}
if ($http !== 200) {
    error_log("S82.AI_STUDIO color_detect Gemini HTTP $http body=" . substr($response, 0, 300));
    cd_out(502, ['ok' => false, 'reason' => 'AI грешка (' . $http . ').']);
}

$data = json_decode($response, true);
$raw  = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
if (!$raw) {
    error_log('S82.AI_STUDIO color_detect empty AI response: ' . substr($response, 0, 300));
    cd_out(502, ['ok' => false, 'reason' => 'Празен отговор от AI.']);
}

// Strip optional ```json fence the model sometimes adds despite responseMimeType.
$raw = trim($raw);
$raw = preg_replace('/^```(?:json)?\s*/', '', $raw);
$raw = preg_replace('/\s*```$/', '', $raw);

$colors = json_decode($raw, true);
if (!is_array($colors)) {
    error_log('S82.AI_STUDIO color_detect not-JSON AI text: ' . substr($raw, 0, 200));
    cd_out(502, ['ok' => false, 'reason' => 'AI върна неразпознаваем формат.']);
}

// Normalize and clamp to 4
$out_colors = [];
foreach (array_slice($colors, 0, 4) as $c) {
    if (!isset($c['name'])) continue;
    $hex = isset($c['hex']) ? preg_replace('/[^#0-9a-fA-F]/', '', (string)$c['hex']) : '';
    if ($hex && $hex[0] !== '#') $hex = '#' . $hex;
    if (!$hex || strlen($hex) !== 7) $hex = '#888888';
    $conf = isset($c['confidence']) ? (float)$c['confidence'] : 0.0;
    $conf = max(0, min(1, $conf));
    $out_colors[] = [
        'name'       => trim((string)$c['name']),
        'hex'        => strtolower($hex),
        'confidence' => $conf,
    ];
}

if (!$out_colors) {
    cd_out(502, ['ok' => false, 'reason' => 'AI не разпозна цветове.']);
}

rms_image_record_usage($tenant_id, $user_id, 'color_detect');
$quota_after = rms_image_check_quota($tenant_id);

cd_out(200, [
    'ok'        => true,
    'colors'    => $out_colors,
    'plan'      => $quota_after['plan'],
    'used'      => $quota_after['used'],
    'remaining' => $quota_after['remaining'],
]);
