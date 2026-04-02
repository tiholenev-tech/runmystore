<?php
// ai-helper.php — PHP proxy за AI без запис в chat_messages
// Използва Gemini API (същото като chat-send.php)
session_start();
session_write_close();

if (!isset($_SESSION['tenant_id'])) { http_response_code(401); exit; }

require_once 'config/config.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'generate';

// ═══════════════════════════════════════
// GEMINI API CALL
// ═══════════════════════════════════════
function callGemini($system, $userPrompt, $maxTokens = 1024) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

    $payload = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $userPrompt]]]
        ],
        'system_instruction' => ['parts' => [['text' => $system]]],
        'generationConfig'   => ['temperature' => 0.4, 'maxOutputTokens' => $maxTokens]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) { error_log('Gemini cURL error: ' . $err); return ''; }
    $decoded = json_decode($response, true);
    if (!$decoded) { error_log('Gemini invalid JSON: ' . substr($response, 0, 500)); return ''; }
    return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

// ═══════════════════════════════════════
// GEMINI MULTIMODAL (снимка + текст)
// ═══════════════════════════════════════
function callGeminiVision($system, $userPrompt, $imageBase64, $mimeType = 'image/jpeg') {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

    $payload = [
        'contents' => [[
            'role'  => 'user',
            'parts' => [
                ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageBase64]],
                ['text' => $userPrompt]
            ]
        ]],
        'system_instruction' => ['parts' => [['text' => $system]]],
        'generationConfig'   => ['temperature' => 0.2, 'maxOutputTokens' => 512]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($response, true);
    return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

// ═══════════════════════════════════════
// ACTION: analyze_biz_segment
// ═══════════════════════════════════════
if ($action === 'analyze_biz_segment') {
    $biz = trim($input['biz'] ?? '');
    if (!$biz) { echo json_encode(['question' => 'Продаваш ли предимно масови артикули или залагаш на по-скъпи стоки?']); exit; }

    $system = 'Ти си умен бизнес асистент. Разбираш разговорен български и АВТОМАТИЧНО разпознаваш правописни грешки. Примери: "дреги"="дрехи", "офки"="обувки", "ехи"="дрехи". Никога не питаш "имаш ли предвид X?" — просто разпознаваш логически и продължаваш. Отговаряш САМО с един кратък въпрос без кавички.';
    $prompt = "Клиентът каза, че продава: '$biz'. Разпознай бизнеса логически. Задай МУ ЕДИН кратък приятелски въпрос за ценовия клас или спецификата. Примери: за дрехи→'Ежедневна мода или по-луксозен бутик?', за обувки→'Спортни или официални обувки предимно?'. Върни САМО въпроса.";

    $text = callGemini($system, $prompt, 100);
    if (!$text) $text = 'Продаваш ли предимно масови артикули или залагаш на по-скъпи стоки?';

    echo json_encode(['question' => trim($text)]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: wow — 5 УАУ съобщения
// ═══════════════════════════════════════
if ($action === 'wow') {
    $prompt = trim($input['prompt'] ?? '');
    if (!$prompt) { echo json_encode(['messages' => []]); exit; }

    $system = 'Ти си AI асистент на RunMyStore.ai. Говориш конкретно като бизнес консултант — суми, проценти, факти. Без телешоп език. Отговаряш САМО с валиден JSON.';
    $text = callGemini($system, $prompt, 1024);

    $messages = [];
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $parsed = json_decode($match[0], true);
        if (isset($parsed['messages']) && is_array($parsed['messages'])) {
            $messages = $parsed['messages'];
        }
    }

    echo json_encode(['messages' => $messages]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: loyalty_options
// ═══════════════════════════════════════
if ($action === 'loyalty_options') {
    $biz     = trim($input['biz'] ?? '');
    $segment = trim($input['segment'] ?? '');
    $name    = trim($input['name'] ?? '');

    $system = 'Ти си експерт по лоялни програми за малкия ритейл. Познаваш 50+ вида бизнеси. Говориш топъл разговорен български. Отговаряш САМО с валиден JSON без Markdown.';
    $prompt = "Бизнес: $biz | Сегмент: $segment | Собственик: $name\n\nГенерирай ТОЧНО 3 лоялни програми за ТОЗИ бизнес.\n- Програма 1: класическа (точки/отстъпки)\n- Програма 2: креативна за индустрията\n- Програма 3: referral или комбо\n\nВърни САМО JSON:\n{\"options\":[{\"title\":\"...\",\"desc\":\"...\",\"emoji\":\"...\"},{\"title\":\"...\",\"desc\":\"...\",\"emoji\":\"...\"},{\"title\":\"...\",\"desc\":\"...\",\"emoji\":\"...\"}]}";

    $text = callGemini($system, $prompt, 1200);

    $options = [];
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $parsed = json_decode($match[0], true);
        if (isset($parsed['options']) && is_array($parsed['options'])) {
            $options = $parsed['options'];
        }
    }

    if (empty($options)) {
        $options = [
            ['emoji'=>'⭐','title'=>'Точки за всяка покупка','desc'=>'1 EUR = 1 точка. 100 точки → 5 EUR отстъпка. Рожден ден = двойни точки.'],
            ['emoji'=>'🎯','title'=>'VIP нива Silver/Gold','desc'=>'При 300 EUR ставаш Silver (-5%). При 700 EUR — Gold (-10%) + ранен достъп.'],
            ['emoji'=>'🤝','title'=>'Доведи приятел','desc'=>'Доведеш приятел → ти 10 EUR кредит, той 5 EUR отстъпка на първата покупка.']
        ];
    }

    echo json_encode(['options' => $options]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: generate — AI описание на артикул
// ═══════════════════════════════════════
if ($action === 'generate') {
    $message = trim($input['message'] ?? '');
    if (!$message) { echo json_encode(['reply' => '']); exit; }

    $system = 'Ти генерираш кратки продаващи описания на продукти за малки магазини в България. Отговаряй само на български. Максимум 2-3 изречения.';
    $text = callGemini($system, $message, 256);

    echo json_encode(['reply' => trim($text)]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: scan_product — разпознаване на артикул от снимка ⭐ НОВО
// ═══════════════════════════════════════
if ($action === 'scan_product') {
    $imageBase64 = $input['image'] ?? '';
    $mimeType    = $input['mime']  ?? 'image/jpeg';
    if (!$imageBase64) { echo json_encode(['error' => 'No image']); exit; }

    $system = 'Ти си складов асистент. Анализираш снимки на продукти и извличаш информация. Отговаряш САМО с валиден JSON без Markdown.';
    $prompt = 'Анализирай тази снимка на продукт. Извлечи: name (наименование на български), retail_price (прогнозна цена в EUR, ако се вижда), barcode (ако се вижда), color (цвят), size (размер), description (кратко описание 1-2 изр. на български). Върни САМО JSON: {"name":"...","retail_price":null,"barcode":null,"color":"...","size":null,"description":"..."}';

    $text = callGeminiVision($system, $prompt, $imageBase64, $mimeType);

    $product = [];
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $product = json_decode($match[0], true) ?? [];
    }

    echo json_encode(['product' => $product]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: web_search — търси пазарни данни
// Gemini няма вграден web search като Claude,
// затова правим опростена версия с grounding
// ═══════════════════════════════════════
if ($action === 'web_search') {
    $query = trim($input['query'] ?? '');
    if (!$query) { echo json_encode(['result' => '']); exit; }

    // Gemini с Google Search grounding
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

    $payload = [
        'contents' => [['role' => 'user', 'parts' => [['text' => "Намери данни за: $query\nВърни само кратки факти за загуби от залежала стока в този ритейл сектор. Макс 80 думи. Само числа и факти."]]]],
        'tools' => [['google_search' => new stdClass()]],
        'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 256]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($response, true);
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

    echo json_encode(['result' => trim($text)]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
