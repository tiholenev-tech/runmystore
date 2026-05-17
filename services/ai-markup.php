<?php
/**
 * ai-markup.php — markup recommendation endpoint (cost_price → retail_price)
 * S148 ФАЗА 2d — 2026-05-17
 *
 * Spec: WIZARD_v6_IMPLEMENTATION_HANDOFF.md §5.3 (логика) + §14.2 (JSON schema).
 *
 * Логика:
 *   1) Find pricing_patterns row за tenant + category + subcategory
 *   2) Fallback на category-only (subcategory_id IS NULL)
 *   3) Global default 2.0 × .90 (cold start или ако таблицата липсва — Q4)
 *   4) Apply ending pattern (.99 / .90 / .50 / exact)
 *   5) Routing per confidence: auto / confirm / manual
 *
 * Sacred status: НЕ пипа voice-tier2 / price-ai / ai-color-detect / products.php /
 * capacitor-printer. Само DB read + изчисление.
 *
 * Auth: PHP session ($_SESSION['user_id']).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

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
// Input (JSON)
// ────────────────────────────────────────────────────────────
$body = json_decode((string)file_get_contents('php://input'), true) ?: [];
$cost_price     = (float)($body['cost_price'] ?? 0);
$category_id    = isset($body['category_id'])    ? (int)$body['category_id']    : null;
$subcategory_id = isset($body['subcategory_id']) ? (int)$body['subcategory_id'] : null;

if ($cost_price <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'INVALID_INPUT', 'detail' => 'cost_price must be > 0']);
    exit;
}

// ────────────────────────────────────────────────────────────
// Lookup pricing_patterns (graceful degrade per Q4)
// ────────────────────────────────────────────────────────────
$pattern         = null;
$table_missing   = false;
$category_name   = null;
$subcategory_name= null;
$sample_size     = 0;

try {
    if ($subcategory_id !== null) {
        $row = DB::run("SELECT * FROM pricing_patterns
                        WHERE tenant_id = ? AND category_id = ? AND subcategory_id = ?
                        LIMIT 1", [$tenant_id, $category_id, $subcategory_id])->fetch(PDO::FETCH_ASSOC);
        if ($row) $pattern = $row;
    }
    if (!$pattern && $category_id !== null) {
        $row = DB::run("SELECT * FROM pricing_patterns
                        WHERE tenant_id = ? AND category_id = ? AND subcategory_id IS NULL
                        LIMIT 1", [$tenant_id, $category_id])->fetch(PDO::FETCH_ASSOC);
        if ($row) $pattern = $row;
    }
} catch (Throwable $e) {
    // Q4: ако таблицата липсва (или друг DB error) → graceful global default
    error_log('ai-markup pricing_patterns lookup: ' . $e->getMessage());
    $table_missing = true;
}

if (!$pattern) {
    $pattern = [
        'multiplier'      => 2.0,
        'ending_pattern'  => '.90',
        'confidence'      => 0.5,
        'sample_size'     => 0,
    ];
}

// Optional: достъп до category/subcategory names (best-effort)
if ($category_id !== null) {
    try {
        $c = DB::run("SELECT name FROM categories WHERE id = ? LIMIT 1", [$category_id])->fetch(PDO::FETCH_ASSOC);
        if ($c) $category_name = $c['name'];
    } catch (Throwable $e) { /* ignore */ }
}
if ($subcategory_id !== null) {
    try {
        $c = DB::run("SELECT name FROM categories WHERE id = ? LIMIT 1", [$subcategory_id])->fetch(PDO::FETCH_ASSOC);
        if ($c) $subcategory_name = $c['name'];
    } catch (Throwable $e) { /* ignore */ }
}

// ────────────────────────────────────────────────────────────
// Compute retail_price
// ────────────────────────────────────────────────────────────
function applyEnding(float $raw, string $ending): float {
    $floor = floor($raw);
    switch ($ending) {
        case '.99':   return $floor + 0.99;
        case '.90':   return $floor + 0.90;
        case '.50':   return $floor + 0.50;
        case 'exact': return round($raw, 2);
        default:      return round($raw, 2);
    }
}

$multiplier = (float)$pattern['multiplier'];
$ending     = (string)$pattern['ending_pattern'];
$confidence = (float)$pattern['confidence'];
$sample     = (int)($pattern['sample_size'] ?? 0);
$raw        = $cost_price * $multiplier;
$retail     = applyEnding($raw, $ending);

$routing = $confidence > 0.85 ? 'auto'
         : ($confidence >= 0.5 ? 'confirm' : 'manual');

// ────────────────────────────────────────────────────────────
// Response
// ────────────────────────────────────────────────────────────
$out = [
    'ok'             => true,
    'retail_price'   => $retail,
    'multiplier'     => $multiplier,
    'ending'         => $ending,
    'category_name'  => $category_name,
    'subcategory_name'=> $subcategory_name,
    'confidence'     => $confidence,
    'sample_size'    => $sample,
    'routing'        => $routing,
];

if ($table_missing) {
    $out['source']      = 'global_default_fallback';
    $out['explanation']= 'pricing_patterns таблицата липсва — global default 2.0 × .90';
} elseif ($sample === 0) {
    $out['source']      = 'global_default_cold_start';
    $out['explanation']= 'Няма pattern за тази категория — global default 2.0 × .90';
} else {
    $out['source']      = 'tenant_pattern';
    $out['explanation']= "На база на $sample продажби в категория '" . ($category_name ?: '?') . "'";
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
