<?php
/**
 * ai-studio-action.php — AI Studio control plane endpoint (S82.STUDIO.BACKEND).
 *
 * Separate from ai-image-processor.php (which stays bg-removal-only). This endpoint
 * routes AI Studio actions that consume / refund magic credits and orchestrate retries.
 *
 * POST `type=`:
 *   tryon  / studio / magic — generate hero image via nano-banana (consumes 1 magic credit)
 *   retry  — re-run a previous attempt by parent_log_id, NO credit consumed (Quality Guarantee)
 *   refund — flip ai_spend_log row to refunded_loss + restore 1 credit
 *
 * Anti-abuse: hard cap 30 retries / 24h, soft warning at retry_rate > 0.6 (5+ retries today).
 *
 * UI rule: never surface "Gemini" / "fal.ai" / "nano-banana" in user-facing strings.
 * Backend log lines are exempt — useful for debugging upstream failures.
 *
 * Requires FAL_API_KEY in /etc/runmystore/api.env. Without it the magic / retry paths
 * return 503 with a clear "setup required" message so Tihol sees the gap.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/ai-image-credits.php';
require_once __DIR__ . '/ai-studio-backend.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

function studio_out(int $http, array $data): void {
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    studio_out(405, ['ok' => false, 'reason' => 'POST only.']);
}
if (!isset($_SESSION['tenant_id'])) {
    studio_out(401, ['ok' => false, 'reason' => 'Не сте влезли.']);
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

$type_raw = isset($_POST['type']) ? strtolower(trim((string)$_POST['type'])) : '';
if ($type_raw === '') studio_out(400, ['ok' => false, 'reason' => 'Липсва тип на действието.']);
$type = in_array($type_raw, ['studio', 'tryon'], true) ? 'magic' : $type_raw;

// ─────────────────────────────────────────────────────────────────────────
// REFUND — control-only, no upload.
// ─────────────────────────────────────────────────────────────────────────
if ($type === 'refund') {
    $log_id = isset($_POST['log_id']) ? (int)$_POST['log_id'] : 0;
    if ($log_id <= 0) studio_out(400, ['ok' => false, 'reason' => 'Липсва log_id.']);

    $owner = (int)DB::run("SELECT tenant_id FROM ai_spend_log WHERE id = ?", [$log_id])->fetchColumn();
    if ($owner !== $tenant_id) studio_out(403, ['ok' => false, 'reason' => 'Нямаш достъп до този запис.']);

    $r = refund_credit($log_id);
    if (!$r['ok']) studio_out(409, ['ok' => false, 'reason' => $r['reason']]);
    studio_out(200, ['ok' => true, 'log_id' => $log_id]);
}

// ─────────────────────────────────────────────────────────────────────────
// RETRY — re-run upstream by parent's feature, NO credit consumed.
// ─────────────────────────────────────────────────────────────────────────
if ($type === 'retry') {
    $parent_id = isset($_POST['parent_log_id']) ? (int)$_POST['parent_log_id'] : 0;
    if ($parent_id <= 0) studio_out(400, ['ok' => false, 'reason' => 'Липсва parent_log_id.']);

    $parent = DB::run(
        "SELECT id, tenant_id, feature, product_id, category FROM ai_spend_log WHERE id = ?",
        [$parent_id]
    )->fetch();
    if (!$parent) studio_out(404, ['ok' => false, 'reason' => 'Записът не е намерен.']);
    if ((int)$parent['tenant_id'] !== $tenant_id) studio_out(403, ['ok' => false, 'reason' => 'Нямаш достъп до този запис.']);

    $abuse = check_anti_abuse($tenant_id);
    if ($abuse['blocked']) studio_out(429, ['ok' => false, 'reason' => 'Дневен лимит за повторения изчерпан.']);

    $elig = check_retry_eligibility($parent_id);
    if (!$elig['eligible']) {
        // Quality Guarantee: budget exhausted -> auto-refund parent.
        $rr = refund_credit($parent_id);
        studio_out(409, [
            'ok'            => false,
            'reason'        => $elig['reason'],
            'retries_used'  => $elig['retries_used'],
            'auto_refunded' => $rr['ok'],
        ]);
    }

    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        studio_out(400, ['ok' => false, 'reason' => 'Липсва снимка за повторение.']);
    }
    $file = studio_validate_upload($_FILES['image']);

    $result = studio_run_image_op((string)$parent['feature'], $file);
    rms_studio_log_spend([
        'tenant_id'      => $tenant_id,
        'user_id'        => $user_id,
        'product_id'     => $parent['product_id'] ? (int)$parent['product_id'] : null,
        'feature'        => (string)$parent['feature'],
        'category'       => $parent['category'] ?? null,
        'cost_eur'       => 0,
        'status'         => 'retry_free',
        'parent_log_id'  => $parent_id,
        'attempt_number' => $elig['retries_used'] + 2,
        'meta'           => ['result_url' => $result['url'] ?? null, 'error' => $result['error'] ?? null],
    ]);

    if (!$result['ok']) {
        studio_out($result['http'] ?? 502, [
            'ok'                => false,
            'reason'            => $result['error'] ?? 'AI грешка',
            'retries_remaining' => $elig['retries_remaining'] - 1,
        ]);
    }

    studio_out(200, [
        'ok'                => true,
        'url'               => $result['url'],
        'retries_used'      => $elig['retries_used'] + 1,
        'retries_remaining' => $elig['retries_remaining'] - 1,
        'soft_warning'      => $abuse['soft_warning'],
    ]);
}

// ─────────────────────────────────────────────────────────────────────────
// MAGIC (alias: studio / tryon) — consume 1 magic credit, call upstream.
// ─────────────────────────────────────────────────────────────────────────
if ($type !== 'magic') {
    studio_out(400, ['ok' => false, 'reason' => 'Непознато действие.']);
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    studio_out(400, ['ok' => false, 'reason' => 'Липсва или невалидна снимка.']);
}
$file = studio_validate_upload($_FILES['image']);

$abuse = check_anti_abuse($tenant_id);
if ($abuse['blocked']) studio_out(429, ['ok' => false, 'reason' => 'Дневен лимит за повторения изчерпан.']);

$cc = consume_credit($tenant_id, 'magic', 1);
if (!$cc['ok']) {
    studio_out(402, ['ok' => false, 'reason' => 'Недостатъчни магически кредити.', 'code' => $cc['reason']]);
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
$category   = isset($_POST['category'])   ? trim((string)$_POST['category']) : null;
if ($category === '') $category = null;

$result = studio_run_image_op('magic', $file);

$log_id = rms_studio_log_spend([
    'tenant_id'  => $tenant_id,
    'user_id'    => $user_id,
    'product_id' => $product_id,
    'feature'    => 'magic',
    'category'   => $category,
    'model'      => AI_MAGIC_MODEL,
    'cost_eur'   => AI_MAGIC_PRICE,
    'status'     => 'completed_paid',
    'meta'       => ['result_url' => $result['url'] ?? null, 'error' => $result['error'] ?? null],
]);

if (!$result['ok']) {
    // Quality Guarantee: refund immediately on upstream failure.
    refund_credit($log_id);
    studio_out($result['http'] ?? 502, [
        'ok'     => false,
        'reason' => $result['error'] ?? 'AI грешка. Кредитът е върнат.',
        'log_id' => $log_id,
    ]);
}

$bal = get_credit_balance($tenant_id, 'magic');
studio_out(200, [
    'ok'           => true,
    'url'          => $result['url'],
    'log_id'       => $log_id,
    'remaining'    => $bal['total'],
    'soft_warning' => $abuse['soft_warning'],
]);

// ═════════════════════════════════════════════════════════════════════════
// Helpers — local to this endpoint.
// ═════════════════════════════════════════════════════════════════════════

/**
 * studio_validate_upload — guards size + mime, returns file array unchanged or short-circuits with HTTP error.
 * @return array $_FILES['image'] entry
 */
function studio_validate_upload(array $file): array {
    if ($file['size'] > 10 * 1024 * 1024) {
        studio_out(413, ['ok' => false, 'reason' => 'Снимката е по-голяма от 10 MB.']);
    }
    $mime = mime_content_type($file['tmp_name']);
    $allowed_mimes = ['image/jpeg','image/png','image/webp'];
    if (!in_array($mime, $allowed_mimes, true)) {
        studio_out(415, ['ok' => false, 'reason' => 'Поддържат се само JPG, PNG, WebP.']);
    }
    return $file;
}

/**
 * studio_run_image_op — dispatch a single image to the right upstream.
 * @return array{ok:bool,url?:string,error?:string,http?:int}
 */
function studio_run_image_op(string $feature, array $file): array {
    $key = rms_api_env('FAL_API_KEY');
    if (!$key) {
        error_log('S82.STUDIO.BACKEND: FAL_API_KEY missing for feature=' . $feature);
        return ['ok' => false, 'error' => 'AI Studio: липсва конфигурация. Свържи се с поддръжка.', 'http' => 503];
    }

    $mime = mime_content_type($file['tmp_name']);
    $b64  = base64_encode((string)file_get_contents($file['tmp_name']));
    $uri  = 'data:' . $mime . ';base64,' . $b64;

    if ($feature === 'bg_remove') {
        $endpoint = 'https://fal.run/fal-ai/birefnet/v2';
        $payload  = ['image_url' => $uri, 'model' => 'General Use (Light)', 'output_format' => 'png'];
    } elseif ($feature === 'magic' || $feature === 'tryon') {
        // AI_MAGIC_MODEL flips between 'nano-banana-2' (€0.30) and 'nano-banana-pro' (€0.50).
        $endpoint = 'https://fal.run/fal-ai/' . AI_MAGIC_MODEL . '/edit';
        $payload  = ['image_urls' => [$uri], 'output_format' => 'jpeg'];
    } else {
        return ['ok' => false, 'error' => 'Непозната операция.', 'http' => 400];
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Key ' . $key],
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_errno($ch);
    $cmsg = curl_error($ch);
    curl_close($ch);

    if ($cerr) {
        error_log("S82.STUDIO.BACKEND $feature curl error: $cmsg");
        return ['ok' => false, 'error' => 'AI услугата не отговаря. Опитай отново.', 'http' => 502];
    }
    if ($http !== 200) {
        error_log("S82.STUDIO.BACKEND $feature HTTP $http body=" . substr((string)$body, 0, 300));
        return ['ok' => false, 'error' => 'AI грешка (' . $http . '). Опитай пак.', 'http' => 502];
    }

    $data = json_decode((string)$body, true);
    $url  = $data['image']['url'] ?? $data['images'][0]['url'] ?? null;
    if (!$url) {
        error_log("S82.STUDIO.BACKEND $feature no image url: " . substr((string)$body, 0, 300));
        return ['ok' => false, 'error' => 'Празен отговор от AI.', 'http' => 502];
    }
    return ['ok' => true, 'url' => (string)$url];
}
