<?php
/**
 * price-ai.php — Tier 2 fallback price parser via Gemini 2.5 Flash
 *
 * Strict deterministic config: temperature=0, JSON Mode, response schema enforced.
 * Викa се само когато _wizPriceParse (frontend) не разпознава transcript-а.
 *
 * Input (POST JSON):  {text: "<voice transcript>", lang: "bg"}
 * Output (success):   {ok: true, data: {price: <float|null>, confidence: <0..1>, engine: "gemini-flash"}}
 * Output (error):     {ok: false, error: "..."}
 *
 * Auth: PHP session ($_SESSION['user_id']).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

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
if (GEMINI_API_KEY === '') {
    echo json_encode(['ok' => false, 'error' => 'GEMINI_API_KEY not configured']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────
// Strict system prompt — Bulgarian shop talk price parser
// ─────────────────────────────────────────────────────────────────────
$lang_names = [
    'bg' => 'Bulgarian', 'ro' => 'Romanian', 'el' => 'Greek',
    'sr' => 'Serbian', 'hr' => 'Croatian', 'mk' => 'Macedonian',
    'sq' => 'Albanian', 'tr' => 'Turkish', 'sl' => 'Slovenian',
    'de' => 'German', 'en' => 'English'
];
$lang_name = $lang_names[$lang] ?? 'Bulgarian';

$system = "Ти си безчувствен JSON парсър за български търговски обекти. "
        . "Единствената ти задача е да извлечеш цена от суров глас текст ($lang_name).\n\n"
        . "ПРАВИЛА ЗА ЦЕНИ:\n"
        . "- 'и' между две числа е десетичен разделител: 'две и двайсет' = 2.20, 'четири и петдесет' = 4.50.\n"
        . "- 'педесе' = 50, 'двайсе' = 20, 'трийсе' = 30, 'четирсе' = 40, 'шейсе' = 60, 'седемсе' = 70, 'осемсе' = 80, 'деветсе' = 90.\n"
        . "- 'педесе и пет' = 55. 'четирсе и три' = 43.\n"
        . "- 'сто и петдесет' (без 'стотинки') = 150 (combine, не 1.50).\n"
        . "- 'сто лева и петдесет стотинки' = 100.50.\n"
        . "- Игнорирай: 'лева', 'лев', 'лв', 'кинта', 'стотинки', 'ст', 'около', 'примерно'.\n"
        . "- 'запетая'/'точка' между числа = десетичен разделител.\n"
        . "- 'едно'/'една'/'един' = 1.\n\n"
        . "CONFIDENCE:\n"
        . "- 1.0 = напълно сигурен (ясно число)\n"
        . "- 0.7-0.9 = вероятен (с малка двусмислица)\n"
        . "- < 0.7 = ниска сигурност → върни {\"price\": null, \"confidence\": 0, \"error\": \"low_confidence\"}\n\n"
        . "ЗАБРАНА:\n"
        . "- Никога не връщай текст извън JSON.\n"
        . "- Без markdown, без обяснения, без коментари.\n\n"
        . "Few-shot:\n"
        . "Вход: 'четирсе и пет и деветдесет' → {\"price\": 45.90, \"confidence\": 0.85}\n"
        . "Вход: 'три тениски по десет и педесе' → {\"price\": 10.50, \"confidence\": 0.9}\n"
        . "Вход: 'около пет' → {\"price\": 5, \"confidence\": 0.7}\n"
        . "Вход: 'едно петдесет и пет' → {\"price\": 1.55, \"confidence\": 0.9}\n"
        . "Вход: 'двадесет лв' → {\"price\": 20, \"confidence\": 1.0}\n"
        . "Вход: 'не знам нещо' → {\"price\": null, \"confidence\": 0, \"error\": \"low_confidence\"}";

$user_prompt = "Транскрипт: \"$text\"";

// ─────────────────────────────────────────────────────────────────────
// Gemini 2.5 Flash call — strict deterministic JSON Mode
// ─────────────────────────────────────────────────────────────────────
$payload = [
    'contents' => [
        ['role' => 'user', 'parts' => [['text' => $user_prompt]]]
    ],
    'system_instruction' => ['parts' => [['text' => $system]]],
    'generationConfig' => [
        'temperature'        => 0.0,
        'maxOutputTokens'    => 80,
        'response_mime_type' => 'application/json',
        'response_schema'    => [
            'type' => 'object',
            'properties' => [
                'price'      => ['type' => 'number', 'nullable' => true],
                'confidence' => ['type' => 'number'],
                'error'      => ['type' => 'string', 'nullable' => true],
            ],
            'required' => ['price', 'confidence'],
        ],
    ],
];

$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 8,
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err || $http_code < 200 || $http_code >= 300) {
    echo json_encode(['ok' => false, 'error' => "gemini http $http_code", 'curl_err' => $curl_err ?: null]);
    exit;
}

$decoded = json_decode($response, true);
$out_text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

// JSON Mode гарантира clean JSON, но за всеки случай trim markdown
$cleaned = trim((string)$out_text);
$cleaned = preg_replace('/^```(?:json)?\s*/m', '', $cleaned);
$cleaned = preg_replace('/\s*```\s*$/m', '', $cleaned);
$cleaned = trim($cleaned);

$parsed = json_decode($cleaned, true);

if (!is_array($parsed) || !array_key_exists('price', $parsed)) {
    echo json_encode(['ok' => false, 'error' => 'invalid AI response', 'raw' => $cleaned]);
    exit;
}

$price = $parsed['price'];
$confidence = isset($parsed['confidence']) ? (float)$parsed['confidence'] : 0.5;
$err_flag = $parsed['error'] ?? null;

if ($err_flag === 'low_confidence' || $price === null) {
    echo json_encode([
        'ok' => true,
        'data' => [
            'price'      => null,
            'confidence' => $confidence,
            'engine'     => 'gemini-flash',
            'error'      => 'low_confidence',
        ],
    ]);
    exit;
}

if (!is_numeric($price)) {
    echo json_encode(['ok' => false, 'error' => 'price not numeric', 'raw' => $cleaned]);
    exit;
}

echo json_encode([
    'ok' => true,
    'data' => [
        'price'      => (float)$price,
        'confidence' => $confidence,
        'engine'     => 'gemini-flash',
    ],
]);
