<?php
// ai-helper.php — PHP proxy за AI без запис в chat_messages
// Използва се за: generateAIDescription, web_search, wow moment, onboarding

session_start();
if (!isset($_SESSION['tenant_id'])) { http_response_code(401); exit; }

require_once 'config/config.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'generate';

// ═══════════════════════════════════════
// ACTION: web_search — търси в нета
// ═══════════════════════════════════════
if ($action === 'web_search') {
    $query = trim($input['query'] ?? '');
    if (!$query) { echo json_encode(['result' => '']); exit; }

    $payload = [
        'model'      => 'claude-sonnet-4-20250514',
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

    // Извличаме текст от response
    $text = '';
    if (isset($result['content'])) {
        foreach ($result['content'] as $block) {
            if ($block['type'] === 'text') $text .= $block['text'];
        }
    }

    echo json_encode(['result' => $text]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: wow — генерира 5 УАУ съобщения
// ═══════════════════════════════════════
if ($action === 'wow') {
    $prompt = trim($input['prompt'] ?? '');
    if (!$prompt) { echo json_encode(['messages' => []]); exit; }

    $payload = [
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 1024,
        'messages'   => [[
            'role'    => 'user',
            'content' => $prompt
        ]]
    ];

    $result = callClaude($payload);

    $text = '';
    if (isset($result['content'])) {
        foreach ($result['content'] as $block) {
            if ($block['type'] === 'text') $text .= $block['text'];
        }
    }

    // Парсваме JSON от отговора
    $messages = [];
    $match = [];
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $parsed = json_decode($match[0], true);
        if (isset($parsed['messages']) && is_array($parsed['messages'])) {
            $messages = $parsed['messages'];
        }
    }

    // Fallback ако не успее
    if (empty($messages)) {
        $messages = [
            "Магазини като твоя губят средно €200-400 на месец от залежала стока.\n→ Аз следя всеки артикул и те питам преди да е станало.\nЗагуба: €200-400/месец.",
            "Стока в грешен размер или модел — замразени пари.\n→ Аз следя кое върви и кое стои.\nЗагуба: €150-300/месец.",
            "Изчерпан артикул = пропусната продажба.\n→ Аз те будя преди да е свършил.\nЗагуба: €100-200/месец.",
            "Без данни не знаеш кое да поръчаш следващия път.\n→ Аз помня всичко и ти казвам точно.\nЗагуба: €100-150/месец.",
            "Само тези 4 проблема ти струват ~€6,600-12,600 на година.\nRunMyStore.ai е €588 на година.\nРазликата — над €6,000 — остава в джоба ти."
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
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 512,
        'system'     => 'Ти генерираш кратки, продаващи описания на продукти за малки магазини в България. Отговаряй само на български. Максимум 2-3 изречения.',
        'messages'   => [[
            'role'    => 'user',
            'content' => $message
        ]]
    ];

    $result = callClaude($payload);

    $text = '';
    if (isset($result['content'])) {
        foreach ($result['content'] as $block) {
            if ($block['type'] === 'text') $text .= $block['text'];
        }
    }

    echo json_encode(['reply' => trim($text)]);
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
