<?php
// ai-helper.php — PHP proxy за AI без запис в chat_messages
// Използва се за: generateAIDescription, web_search, wow moment, onboarding, analyze_biz

session_start();
session_write_close(); // КРИТИЧНО ВАЖНО: Отключва сесията веднага, за да не замръзва чатът!

if (!isset($_SESSION['tenant_id'])) { http_response_code(401); exit; }

require_once 'config/config.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'generate';

// Помощна функция за извличане на текст от Claude
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
// ACTION: analyze_biz_segment — Разпознава бизнеса и поправя грешки
// ═══════════════════════════════════════
if ($action === 'analyze_biz_segment') {
    $biz = trim($input['biz'] ?? '');
    if (!$biz) { echo json_encode(['question' => 'Продаваш ли предимно масови артикули или залагаш на по-скъпи стоки?']); exit; }

    $payload = [
        'model'      => 'claude-3-5-sonnet-20241022',
        'max_tokens' => 150,
        'system'     => 'Ти си умен бизнес асистент. Разбираш разговорен български и автоматично разпознаваш правописни грешки (напр. "ехи" = дрехи, "офки" = обувки).',
        'messages'   => [[
            'role'    => 'user',
            'content' => "Клиентът каза, че продава: '$biz'. 1. Поправи грешката логически. 2. Задай му ЕДИН кратък, приятелски въпрос на български, за да разбереш ценовия клас или спецификата на стоката му (напр. за дрехи: 'Ежедневна мода ли е или луксозен бутик?'). Върни САМО въпроса, без кавички и обяснения."
        ]]
    ];

    $result = callClaude($payload);
    $text = extractClaudeText($result);
    if (!$text) $text = 'Продаваш ли предимно масови артикули или залагаш на по-скъпи стоки?';

    echo json_encode(['question' => trim($text)]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: web_search — търси в нета
// ═══════════════════════════════════════
if ($action === 'web_search') {
    $query = trim($input['query'] ?? '');
    if (!$query) { echo json_encode(['result' => '']); exit; }

    $payload = [
        'model'      => 'claude-3-5-sonnet-20241022', // Използваме стабилен модел
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
        'model'      => 'claude-3-5-sonnet-20241022',
        'max_tokens' => 1024,
        'messages'   => [[
            'role'    => 'user',
            'content' => $prompt
        ]]
    ];

    $result = callClaude($payload);
    $text = extractClaudeText($result);

    // Парсваме JSON от отговора
    $messages = [];
    $match = [];
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $parsed = json_decode($match[0], true);
        if (isset($parsed['messages']) && is_array($parsed['messages'])) {
            $messages = $parsed['messages'];
        }
    }

    // Fallback ако не успее (вече с "предупреждавам")
    if (empty($messages)) {
        $messages = [
            "Залежала стока (Zombie Stock): Магазини като твоя губят средно €200-400 на месец.\n→ Аз засичам мъртвата стока и ти предлагам как да освободиш кеша веднага.",
            "Грешна номерация (Size-Curve): Стока в грешен размер са замразени пари.\n→ Аз анализирам историята и ти казвам точно каква пропорция да заредиш.",
            "Изпуснати продажби (Lost Revenue): Изчерпан топ артикул = загубени пари.\n→ Аз те предупреждавам преди стоката да е свършила напълно.",
            "Изпуснати комбинации (Basket Analysis): Знаеш ли кое с кое се купува?\n→ Аз казвам на екипа ти какво да предложи (Upsell), за да вдигне касовия бон.",
            "Само тези 4 проблема ти струват хиляди на година.\nRunMyStore.ai е €588 на година.\nРазликата остава изцяло в твоя джоб."
        ];
    }

    echo json_encode(['messages' => $messages]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: generate — AI описание на артикул
// ═══════════════════════════════════════
if ($action === 'generate' || !$action) {
    $message = trim($input['message'] ?? '');
    if (!$message) { echo json_encode(['reply' => '']); exit; }

    $payload = [
        'model'      => 'claude-3-5-sonnet-20241022',
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
        CURLOPT_TIMEOUT        => 30
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}

echo json_encode(['error' => 'Unknown action']);
