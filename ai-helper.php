<?php
// ai-helper.php — PHP proxy за AI без запис в chat_messages
session_start();
session_write_close();

if (!isset($_SESSION['tenant_id'])) { http_response_code(401); exit; }

require_once 'config/config.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'generate';

function extractClaudeText($result) {
    $text = '';
    if (isset($result['content'])) {
        foreach ($result['content'] as $block) {
            if ($block['type'] === 'text') $text .= $block['text'];
        }
    }
    return $text;
}

// ═══════════════════════════════════════
// ACTION: analyze_biz_segment
// ═══════════════════════════════════════
if ($action === 'analyze_biz_segment') {
    $biz = trim($input['biz'] ?? '');
    if (!$biz) { echo json_encode(['question' => 'Продаваш ли предимно масови артикули или залагаш на по-скъпи стоки?']); exit; }

    $payload = [
        'model'      => 'claude-sonnet-4-5',
        'max_tokens' => 200,
        'system'     => 'Ти си умен бизнес асистент. Разбираш разговорен български и АВТОМАТИЧНО разпознаваш правописни грешки. Примери: "дреги"="дрехи", "офки"="обувки", "ехи"="дрехи", "коозметика"="козметика". Никога не питаш "имаш ли предвид X?" — просто разпознаваш логически и продължаваш.',
        'messages'   => [[
            'role'    => 'user',
            'content' => "Клиентът каза, че продава: '$biz'. Разпознай бизнеса (поправи грешките логически без да питаш за потвърждение). Задай МУ ЕДИН кратък, приятелски въпрос на български за ценовия клас или спецификата. Примери: за дрехи→'Ежедневна мода или по-луксозен бутик?', за обувки→'Спортни или официални обувки предимно?', за козметика→'Масова козметика или по-скъпи марки?'. Върни САМО въпроса, без кавички."
        ]]
    ];

    $result = callClaude($payload);
    $text = extractClaudeText($result);
    if (!$text) $text = 'Продаваш ли предимно масови артикули или залагаш на по-скъпи стоки?';

    echo json_encode(['question' => trim($text)]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: web_search
// ═══════════════════════════════════════
if ($action === 'web_search') {
    $query = trim($input['query'] ?? '');
    if (!$query) { echo json_encode(['result' => '']); exit; }

    $payload = [
        'model'      => 'claude-sonnet-4-5',
        'max_tokens' => 1024,
        'tools'      => [
            ['type' => 'web_search_20250305', 'name' => 'web_search']
        ],
        'messages'   => [[
            'role'    => 'user',
            'content' => "Search for: $query\nReturn ONLY a brief summary of key findings about dead stock losses, percentages, and typical monthly losses for this type of retailer. Max 100 words. Numbers and facts only."
        ]]
    ];

    $result = callClaude($payload);
    echo json_encode(['result' => extractClaudeText($result)]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: wow — генерира 5 УАУ съобщения
// ═══════════════════════════════════════
if ($action === 'wow') {
    $prompt = trim($input['prompt'] ?? '');
    if (!$prompt) { echo json_encode(['messages' => []]); exit; }

    $payload = [
        'model'      => 'claude-sonnet-4-5',
        'max_tokens' => 1024,
        'messages'   => [[
            'role'    => 'user',
            'content' => $prompt
        ]]
    ];

    $result = callClaude($payload);
    $text = extractClaudeText($result);

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
// ACTION: loyalty_options — 3 персонализирани лоялни програми
// ═══════════════════════════════════════
if ($action === 'loyalty_options') {
    $biz     = trim($input['biz'] ?? '');
    $segment = trim($input['segment'] ?? '');
    $name    = trim($input['name'] ?? '');

    $payload = [
        'model'      => 'claude-sonnet-4-5',
        'max_tokens' => 1200,
        'system'     => 'Ти си експерт по лоялни програми за малкия ритейл в България. Познаваш 50+ вида бизнеси. Говориш на разговорен, топъл български. Разбираш правописни грешки автоматично.',
        'messages'   => [[
            'role'    => 'user',
            'content' => "Бизнес: $biz | Сегмент: $segment | Собственик: $name\n\nГенерирай ТОЧНО 3 различни лоялни програми, много подходящи за ТОЗИ конкретен тип бизнес.\n\nПравила:\n- Програма 1: класическа (точки/отстъпки)\n- Програма 2: креативна за тази индустрия (напр. за дрехи: Early Access за нови колекции; за кафе: 6-то кафе безплатно; за аптека: точки само за козметика/добавки; за мебели: VIP интериорна консултация)\n- Програма 3: комбо или referral\n\nВсяка програма: кратко заглавие (до 5 думи) + 2-3 изречения описание.\n\nВърни САМО JSON:\n{\"options\":[{\"title\":\"...\",\"desc\":\"...\",\"emoji\":\"...\"},{\"title\":\"...\",\"desc\":\"...\",\"emoji\":\"...\"},{\"title\":\"...\",\"desc\":\"...\",\"emoji\":\"...\"}]}"
        ]]
    ];

    $result = callClaude($payload);
    $text = extractClaudeText($result);

    $options = [];
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $parsed = json_decode($match[0], true);
        if (isset($parsed['options']) && is_array($parsed['options'])) {
            $options = $parsed['options'];
        }
    }

    // Fallback
    if (empty($options)) {
        $options = [
            ['emoji'=>'⭐','title'=>'Точки за всяка покупка','desc'=>'€1 = 1 точка. На 100 точки получаваш €5 отстъпка. Рожден ден = двойни точки.'],
            ['emoji'=>'🎯','title'=>'VIP нива Silver/Gold','desc'=>'При €300 похарчени ставаш Silver (-5%). При €700 — Gold (-10%) + ранен достъп до новото.'],
            ['emoji'=>'🤝','title'=>'Доведи приятел','desc'=>'Доведеш приятел → ти вземаш €10 кредит, той вземаш €5 отстъпка на първата покупка.']
        ];
    }

    echo json_encode(['options' => $options]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: generate — AI описание на артикул
// ═══════════════════════════════════════
if ($action === 'generate' || !$action) {
    $message = trim($input['message'] ?? '');
    if (!$message) { echo json_encode(['reply' => '']); exit; }

    $payload = [
        'model'      => 'claude-sonnet-4-5',
        'max_tokens' => 512,
        'system'     => 'Ти генерираш кратки, продаващи описания на продукти за малки магазини в България. Отговаряй само на български. Максимум 2-3 изречения.',
        'messages'   => [[
            'role'    => 'user',
            'content' => $message
        ]]
    ];

    $result = callClaude($payload);
    echo json_encode(['reply' => trim(extractClaudeText($result))]);
    exit;
}

// ═══════════════════════════════════════
// CLAUDE API CALL
// ═══════════════════════════════════════
function callClaude($payload) {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01',
            'anthropic-beta: web-search-2025-03-05'
        ],
        CURLOPT_TIMEOUT        => 45
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) { error_log('Claude cURL error: ' . $err); return []; }
    $decoded = json_decode($response, true);
    if (!$decoded) { error_log('Claude invalid JSON: ' . substr($response, 0, 500)); return []; }
    return $decoded;
}

echo json_encode(['error' => 'Unknown action']);
