<?php
/**
 * price-ai.php — Tier 2 fallback price parser via Gemini Flash
 *
 * Викa се само когато _wizPriceParse (frontend) не разпознава transcript-а.
 * 95% от случаите се решават локално — този endpoint е за edge cases.
 *
 * Input (POST JSON): {text: "<voice transcript>", lang: "bg"}
 * Output: {ok: true, data: {price: <float|null>, engine: "gemini-flash"}}
 *         or {ok: false, error: "..."}
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

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$text = trim((string)($body['text'] ?? ''));
$lang = preg_match('/^[a-z]{2}$/', $body['lang'] ?? '') ? $body['lang'] : 'bg';

if ($text === '') {
    echo json_encode(['ok' => false, 'error' => 'empty text']);
    exit;
}
if (mb_strlen($text) > 200) {
    echo json_encode(['ok' => false, 'error' => 'text too long']);
    exit;
}

$lang_names = [
    'bg' => 'Bulgarian', 'ro' => 'Romanian', 'el' => 'Greek',
    'sr' => 'Serbian', 'hr' => 'Croatian', 'mk' => 'Macedonian',
    'sq' => 'Albanian', 'tr' => 'Turkish', 'sl' => 'Slovenian',
    'de' => 'German', 'en' => 'English'
];
$lang_name = $lang_names[$lang] ?? 'Bulgarian';

$system = "You are an expert at parsing prices spoken in $lang_name shop talk. "
        . "Convert the spoken text to a decimal number representing the price (whole units . fractional units, e.g. leva.stotinki for BG). "
        . "Examples (BG): 'четири и петдесет' → 4.50, 'едно петдесет и пет' → 1.55, "
        . "'двадесет лева' → 20, 'два запетая пет' → 2.5, 'пет лева и двайсет стотинки' → 5.20, "
        . "'около пет' → 5, 'сто и петдесет' → 150 (no stotinki mentioned → combine), "
        . "'сто лева и петдесет стотинки' → 100.50. "
        . "Return ONLY valid JSON: {\"price\": <number>} or {\"price\": null} if unparseable. "
        . "No explanations, no markdown wrappers, just raw JSON.";

$user_prompt = "Parse this price: \"$text\"";

$response_text = callGemini($system, $user_prompt, 60);

// Strip possible markdown wrappers (Gemini sometimes wraps JSON in ```json ... ```)
$cleaned = trim((string)$response_text);
$cleaned = preg_replace('/^```(?:json)?\s*/m', '', $cleaned);
$cleaned = preg_replace('/\s*```\s*$/m', '', $cleaned);
$cleaned = trim($cleaned);

$parsed = json_decode($cleaned, true);

if (!is_array($parsed) || !array_key_exists('price', $parsed)) {
    echo json_encode(['ok' => false, 'error' => 'invalid AI response', 'raw' => $cleaned]);
    exit;
}

$price = $parsed['price'];
if (!is_numeric($price) && !is_null($price)) {
    echo json_encode(['ok' => false, 'error' => 'price not numeric', 'raw' => $cleaned]);
    exit;
}

echo json_encode([
    'ok' => true,
    'data' => [
        'price' => is_null($price) ? null : (float)$price,
        'engine' => 'gemini-flash',
    ],
]);
