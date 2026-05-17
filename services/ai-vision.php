<?php
/**
 * ai-vision.php — Gemini 2.5 Flash multimodal product analysis endpoint
 * S148 ФАЗА 2c — 2026-05-17
 *
 * Цел: 1 обаждане към Gemini → JSON с всичко за продукта
 * (category, subcategory, colors, material, gender, season, brand, description_short).
 *
 * Spec: WIZARD_v6_IMPLEMENTATION_HANDOFF.md §5.2 (логика) + §14.1 (JSON schema).
 *
 * 3-level cache:
 *   L1: barcode lookup в products (graceful — колоните може още да липсват)
 *   L2: perceptual hash (8×8 aHash) в ai_snapshots
 *   L3: callGeminiVision() от ai-helper.php (sacred-grade wrapper)
 *
 * Sacred status: НЕ пипа voice-tier2 / price-ai / ai-color-detect / products.php / printer.js.
 * Само include + call на ai-helper.php (което не е sacred).
 *
 * Auth: PHP session ($_SESSION['user_id']).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../ai-helper.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$tenant_id = (int)($_SESSION['tenant_id'] ?? 0);
if ($tenant_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'no tenant']);
    exit;
}

// ────────────────────────────────────────────────────────────
// Input: image (multipart) + optional barcode
// ────────────────────────────────────────────────────────────
$image_data = '';
$mime = 'image/jpeg';
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $image_data = file_get_contents($_FILES['image']['tmp_name']);
    $mime = $_FILES['image']['type'] ?: 'image/jpeg';
} elseif (!empty($_POST['image_b64'])) {
    $image_data = base64_decode((string)$_POST['image_b64'], true);
    if ($image_data === false) $image_data = '';
    $mime = (string)($_POST['mime'] ?? 'image/jpeg');
}
if ($image_data === '' || strlen($image_data) < 100) {
    echo json_encode(['ok' => false, 'error' => 'INVALID_IMAGE', 'fallback' => 'manual']);
    exit;
}

$barcode = trim((string)($_POST['barcode'] ?? ''));

// ────────────────────────────────────────────────────────────
// LEVEL 1: barcode lookup в products
// ────────────────────────────────────────────────────────────
if ($barcode !== '') {
    try {
        $row = DB::run("SELECT * FROM products WHERE barcode = ? LIMIT 1", [$barcode])->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $same_tenant = ((int)($row['tenant_id'] ?? 0) === $tenant_id);
            $r = [
                'category'              => $row['category'] ?? null,
                'category_id'           => $row['category_id'] ?? null,
                'category_confidence'   => 0.99,
                'subcategory'           => $row['subcategory'] ?? null,
                'subcategory_id'        => $row['subcategory_id'] ?? null,
                'subcategory_confidence'=> 0.99,
                'material'              => $row['material'] ?? null,
                'gender'                => $row['gender'] ?? null,
                'season'                => $row['season'] ?? null,
                'brand'                 => $row['brand'] ?? null,
                'description_short'     => $row['description_short'] ?? null,
            ];
            if ($same_tenant) {
                $r['_inherits_price'] = true;
                $r['retail_price']   = isset($row['retail_price']) ? (float)$row['retail_price'] : null;
                $r['cost_price']     = isset($row['cost_price'])   ? (float)$row['cost_price']   : null;
            }
            echo json_encode(['ok' => true, 'cache_hit' => 'barcode', 'result' => $r], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } catch (Throwable $e) {
        error_log('ai-vision L1 barcode lookup: ' . $e->getMessage());
        // graceful — продължаваме към L2
    }
}

// ────────────────────────────────────────────────────────────
// aHash 8×8 (perceptual hash, ~25 реда pure PHP/GD)
// Q5 одобрено от Тих, 17.05.2026.
// ────────────────────────────────────────────────────────────
function aHash8x8(string $imageData): string {
    $img = @imagecreatefromstring($imageData);
    if (!$img) return 'sha:' . substr(hash('sha256', $imageData), 0, 13);
    $small = imagecreatetruecolor(8, 8);
    imagecopyresampled($small, $img, 0, 0, 0, 0, 8, 8, imagesx($img), imagesy($img));
    imagefilter($small, IMG_FILTER_GRAYSCALE);
    $px = []; $sum = 0;
    for ($y = 0; $y < 8; $y++) {
        for ($x = 0; $x < 8; $x++) {
            $v = imagecolorat($small, $x, $y) & 0xFF;
            $px[] = $v; $sum += $v;
        }
    }
    imagedestroy($small);
    imagedestroy($img);
    $avg = $sum / 64;
    $bits = '';
    foreach ($px as $v) $bits .= ($v >= $avg) ? '1' : '0';
    $hex = '';
    for ($i = 0; $i < 64; $i += 4) $hex .= dechex(bindec(substr($bits, $i, 4)));
    return $hex;
}

$phash = aHash8x8($image_data);

// ────────────────────────────────────────────────────────────
// LEVEL 2: phash lookup в ai_snapshots
// ────────────────────────────────────────────────────────────
try {
    $cached = DB::run("
        SELECT result_json FROM ai_snapshots
        WHERE tenant_id = ? AND phash = ? AND confidence > 0.7
        ORDER BY last_used DESC LIMIT 1
    ", [$tenant_id, $phash])->fetch(PDO::FETCH_ASSOC);
    if ($cached) {
        try {
            DB::run("UPDATE ai_snapshots SET used_count = used_count + 1, last_used = NOW()
                     WHERE tenant_id = ? AND phash = ?", [$tenant_id, $phash]);
        } catch (Throwable $e) { /* best-effort */ }
        $r = json_decode($cached['result_json'], true);
        if (is_array($r)) {
            echo json_encode(['ok' => true, 'phash' => $phash, 'cache_hit' => 'phash', 'result' => $r], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
} catch (Throwable $e) {
    error_log('ai-vision L2 phash lookup: ' . $e->getMessage());
    // graceful — продължаваме към L3
}

// ────────────────────────────────────────────────────────────
// LEVEL 3: Gemini 2.5 Flash call (sacred wrapper)
// ────────────────────────────────────────────────────────────
$system = "You are an expert at analyzing product photos for a Bulgarian retail store. "
        . "Given an image of a product, return a JSON object with these EXACT keys: "
        . "category (string in Bulgarian, broad like 'Бикини', 'Тениска', 'Дънки'), "
        . "category_confidence (0.0-1.0), "
        . "subcategory (string in Bulgarian, more specific like 'Дамски бикини'), "
        . "subcategory_confidence (0.0-1.0), "
        . "color_primary (object: {name (BG string), hex (#RRGGBB), confidence (0-1)}), "
        . "color_secondary (array of similar objects, may be empty), "
        . "material (BG string like 'Памук', 'Памук с ластан', or null), "
        . "material_confidence (0.0-1.0), "
        . "gender (one of: 'male', 'female', 'kid', 'unisex'), "
        . "gender_confidence (0.0-1.0), "
        . "season (one of: 'summer', 'winter', 'transition', 'year_round'), "
        . "season_confidence (0.0-1.0), "
        . "brand (string from visible logo, or null), "
        . "brand_confidence (0.0-1.0), "
        . "description_short (BG, 1-3 кратки изречения с конкретни характеристики). "
        . "Return ONLY raw JSON. No markdown wrappers, no explanations.";

$user_prompt = "Analyze this product photo and return the JSON described in the system prompt.";

$started = microtime(true);
$response_text = callGeminiVision($system, $user_prompt, base64_encode($image_data), $mime);
$duration_ms = (int)((microtime(true) - $started) * 1000);

// Strip markdown if Gemini wrapped
$cleaned = trim((string)$response_text);
$cleaned = preg_replace('/^```(?:json)?\s*/m', '', $cleaned);
$cleaned = preg_replace('/\s*```\s*$/m', '', $cleaned);
$cleaned = trim($cleaned);

$parsed = json_decode($cleaned, true);
if (!is_array($parsed)) {
    echo json_encode([
        'ok'       => false,
        'error'    => 'GEMINI_DOWN',
        'fallback' => 'manual',
        'raw'      => mb_substr($cleaned, 0, 200),
    ]);
    exit;
}

// Cache в ai_snapshots (best-effort)
$confidence = (float)($parsed['category_confidence'] ?? 0.8);
try {
    DB::run("INSERT INTO ai_snapshots (tenant_id, phash, result_json, confidence)
             VALUES (?, ?, ?, ?)",
            [$tenant_id, $phash, json_encode($parsed, JSON_UNESCAPED_UNICODE), $confidence]);
} catch (Throwable $e) {
    error_log('ai-vision cache write: ' . $e->getMessage());
    // graceful — main response продължава
}

echo json_encode([
    'ok'         => true,
    'phash'      => $phash,
    'cache_hit'  => false,
    'result'     => $parsed,
    'duration_ms'=> $duration_ms,
], JSON_UNESCAPED_UNICODE);
