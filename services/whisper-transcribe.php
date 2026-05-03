<?php
/**
 * whisper-transcribe.php — Tier 2 Whisper endpoint with logging + cost tracking.
 *
 * Thin wrapper around services/voice-tier2.php (S93). Adds:
 *   - tenant/session auth check
 *   - file size + mime type validation
 *   - INSERT into voice_command_log (per PRODUCTS_WIZARD_v4_SPEC §11)
 *   - UPDATE tenants.ai_voice_cost_month_eur (best-effort)
 *
 * voice-tier2.php is NOT modified. transcribeWithWhisper() + normalizeWithSynonyms() reused.
 *
 * S95.WIZARD.PART1_2.A — Future fallback for low-confidence Web Speech.
 * NOT actively wired in this commit; voice-engine.js currently uses Web Speech only.
 */

require_once __DIR__ . '/voice-tier2.php';

if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "whisper-transcribe.php: HTTP only" . PHP_EOL);
    exit(1);
}

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$tenant_id = (int)$_SESSION['tenant_id'];

if ($user_id <= 0 || $tenant_id <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'invalid session']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method not allowed']);
    exit;
}

if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'audio file missing']);
    exit;
}

$size = (int)$_FILES['audio']['size'];
if ($size <= 0 || $size > 5 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'audio size out of range (1..5MB)']);
    exit;
}

$mime = $_FILES['audio']['type'] ?? 'audio/webm';
$allowed_mimes = ['audio/webm', 'audio/wav', 'audio/wave', 'audio/x-wav', 'audio/mp4', 'audio/m4a', 'audio/mpeg', 'audio/ogg', 'audio/x-m4a'];
if (!in_array($mime, $allowed_mimes, true)) {
    http_response_code(415);
    echo json_encode(['ok' => false, 'error' => 'unsupported mime: ' . $mime]);
    exit;
}

$audio_data = @file_get_contents($_FILES['audio']['tmp_name']);
if ($audio_data === false || $audio_data === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'failed reading upload']);
    exit;
}

$lang = $_POST['lang'] ?? 'bg';
if (!preg_match('/^[a-z]{2}$/', $lang)) $lang = 'bg';

$field_type = (string)($_POST['field'] ?? '');
if (mb_strlen($field_type) > 50) $field_type = mb_substr($field_type, 0, 50);

$hints = [];
if (!empty($_POST['hints'])) {
    $decoded = json_decode((string)$_POST['hints'], true);
    if (is_array($decoded)) $hints = $decoded;
    elseif (is_string($_POST['hints'])) $hints = [$_POST['hints']];
}

$result = transcribeWithWhisper($audio_data, $lang, [
    'hints' => $hints,
    'mime' => $mime,
    'filename' => $_FILES['audio']['name'] ?: 'recording.webm',
]);

if ($result['error'] === null && $result['transcript'] !== '') {
    $result['transcript_normalized'] = normalizeWithSynonyms($result['transcript'], $tenant_id, $lang);
} else {
    $result['transcript_normalized'] = $result['transcript'];
}

// Cost: Groq whisper-large-v3 ~ $0.111/hour. duration_ms → cost_usd.
$cost_usd = round(($result['duration_ms'] / 1000.0 / 3600.0) * 0.111, 6);
$cost_eur = round($cost_usd * 0.92, 6);

try {
    DB::run(
        "INSERT INTO voice_command_log
         (tenant_id, user_id, field_type, engine, transcript, confidence, duration_ms, audio_size_bytes, cost_usd)
         VALUES (?, ?, ?, 'whisper', ?, ?, ?, ?, ?)",
        [$tenant_id, $user_id, $field_type, $result['transcript'], $result['confidence'], $result['duration_ms'], $size, $cost_usd]
    );
} catch (Throwable $e) {
    error_log('whisper-transcribe: voice_command_log INSERT failed: ' . $e->getMessage());
}

if ($cost_eur > 0) {
    try {
        DB::run(
            "UPDATE tenants SET ai_voice_cost_month_eur = COALESCE(ai_voice_cost_month_eur, 0) + ? WHERE id = ?",
            [$cost_eur, $tenant_id]
        );
    } catch (Throwable $e) {
        error_log('whisper-transcribe: tenants cost update failed: ' . $e->getMessage());
    }
}

unset($result['raw']);

echo json_encode([
    'ok' => $result['error'] === null,
    'text' => $result['transcript_normalized'],
    'transcript_raw' => $result['transcript'],
    'confidence' => $result['confidence'],
    'duration_ms' => $result['duration_ms'],
    'cost_usd' => $cost_usd,
    'cost_eur' => $cost_eur,
    'error' => $result['error'],
]);
