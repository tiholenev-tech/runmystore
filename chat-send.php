<?php
/**
 * chat-send.php — AI Chat Handler
 *
 * ЛОГИКА:
 *   1. Взима съобщение от потребителя
 *   2. Събира контекст: магазин, роля, tenant_ai_memory (последните 20)
 *   3. Праща до Gemini (основен). При грешка → Claude (резервен)
 *   4. Записва и двете съобщения в chat_messages
 *
 * FALLBACK: HTTP 429/5xx или timeout 15сек → Claude. 4xx → skip.
 *           Всички фейлнат → "Опитай пак след минута."
 *
 * П9:  supato_mode → различна терминология (без "продажба" в България)
 * П11: Код спрямо реалната БД схема
 * П13: DB::run() за заявки
 * П14: Валута EU формат
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Не сте оторизиран']);
    exit;
}

$tenant_id   = $_SESSION['tenant_id'];
$user_id     = $_SESSION['user_id'];
$store_id    = $_SESSION['store_id'];
$role        = $_SESSION['role'] ?? 'owner';
$supato_mode = (int)($_SESSION['supato_mode'] ?? 0);
$currency    = $_SESSION['currency'] ?? 'EUR';
$language    = $_SESSION['language'] ?? 'bg';
$user_name   = $_SESSION['user_name'] ?? '';

$body    = json_decode(file_get_contents('php://input'), true);
$message = trim($body['message'] ?? '');

if ($message === '') {
    echo json_encode(['error' => 'Празно съобщение']);
    exit;
}

// ── КОНТЕКСТ ────────────────────────────────────────────────
$store = DB::run('SELECT name FROM stores WHERE id = ? AND tenant_id = ?', [$store_id, $tenant_id])->fetch();
$store_name = $store['name'] ?? 'Обект';

// Tenant AI Memory — последните 20 записа (FIFO)
$memories = DB::run(
    'SELECT content FROM tenant_ai_memory WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 20',
    [$tenant_id]
)->fetchAll(PDO::FETCH_COLUMN);
$memory_block = '';
if ($memories) {
    $memory_block = "\n\nЗАПОМНЕНО ОТ СОБСТВЕНИКА:\n" . implode("\n", array_reverse($memories));
}

// ── SYSTEM PROMPT (по AI_BRAIN_v3_3) ────────────────────────
$sale_term = $supato_mode
    ? 'изходящо движение (НИКОГА не казвай "продажба" — по закон за СУПТО в България)'
    : 'продажба';

$role_context = match($role) {
    'manager' => 'Управител — вижда доставни цени, НЕ вижда печалби, марж или дългове към доставчици.',
    'seller'  => 'Продавач — вижда само своя обект, НЕ вижда доставни цени, печалби или справки.',
    default   => 'Собственик — вижда всичко: печалби, марж, дългове, всички обекти.'
};

$system = "Ти си AI бизнес асистент на RunMyStore.ai за обект \"{$store_name}\".
Потребител: {$user_name} ({$role_context})
Валута: {$currency} (формат: 1.234,50 €)

ПРАВИЛА:
1. Умен приятел търговец. Конкретно: суми, бройки, дати. Кратко: 2-3 изречения.
2. При {$sale_term} — отговаряй с 1 изречение.
3. НИКОГА не казвай \"Грешка\", \"Как мога да помогна?\" или \"Имаш ли предвид X?\".
4. Разпознавай жаргон: \"дреги\"=дрехи, \"офки\"=обувки, \"якита\"=якета.
5. При неясна команда — 1 уточняващ въпрос, никога повече.
6. При деструктивно действие — пита преди изпълнение.
7. Не поправяй правописа на потребителя.
8. Филтрирай информацията по роля: " . match($role) {
    'seller'  => "НЕ показвай доставни цени, печалби, марж, справки.",
    'manager' => "НЕ показвай печалби, марж, дългове към доставчици.",
    default   => "Показвай всичко."
} . "
{$memory_block}";

// ── ИСТОРИЯ (последните 15 съобщения) ────────────────────────
$history_rows = DB::run(
    'SELECT role, content FROM chat_messages WHERE tenant_id = ? AND store_id = ? ORDER BY created_at DESC LIMIT 15',
    [$tenant_id, $store_id]
)->fetchAll();

// ── GEMINI (ОСНОВЕН) ────────────────────────────────────────
$reply = null;
$gemini_failed = false;

if (defined('GEMINI_API_KEY') && GEMINI_API_KEY) {
    $contents = [];
    foreach (array_reverse($history_rows) as $row) {
        $contents[] = [
            'role'  => ($row['role'] === 'assistant' ? 'model' : 'user'),
            'parts' => [['text' => $row['content']]]
        ];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

    $api_url = "https://generativelanguage.googleapis.com/v1beta/models/"
             . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;

    $payload = [
        'contents'          => $contents,
        'system_instruction' => ['parts' => [['text' => $system]]],
        'generationConfig'  => ['temperature' => 0.7, 'maxOutputTokens' => 800]
    ];

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_errno($ch);
    curl_close($ch);

    if ($curl_err || $http_code >= 500 || $http_code === 429) {
        // Timeout, server error, rate limit → fallback to Claude
        $gemini_failed = true;
    } elseif ($http_code >= 400) {
        // 4xx (invalid key, bad request) → skip Gemini permanently this request
        $gemini_failed = true;
    } else {
        $result = json_decode($response, true);
        $reply  = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$reply) $gemini_failed = true;
    }
} else {
    $gemini_failed = true;
}

// ── CLAUDE (РЕЗЕРВЕН) ───────────────────────────────────────
if ($gemini_failed && defined('CLAUDE_API_KEY') && CLAUDE_API_KEY) {
    $messages = [];
    foreach (array_reverse($history_rows) as $row) {
        $messages[] = [
            'role'    => $row['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => $row['content']
        ];
    }
    $messages[] = ['role' => 'user', 'content' => $message];

    $claude_payload = [
        'model'      => 'claude-sonnet-4-5-20250514',
        'max_tokens' => 800,
        'system'     => $system,
        'messages'   => $messages
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($claude_payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $result = json_decode($response, true);
        $reply  = $result['content'][0]['text'] ?? null;
    }
}

// ── ВСИЧКО ФЕЙЛНА ──────────────────────────────────────────
if (!$reply) {
    $reply = 'Системата е натоварена. Опитай пак след минута.';
}

// ── ЗАПИС В БАЗАТА ──────────────────────────────────────────
DB::run(
    'INSERT INTO chat_messages (tenant_id, user_id, store_id, role, content) VALUES (?,?,?,?,?)',
    [$tenant_id, $user_id, $store_id, 'user', $message]
);
DB::run(
    'INSERT INTO chat_messages (tenant_id, user_id, store_id, role, content) VALUES (?,?,?,?,?)',
    [$tenant_id, null, $store_id, 'assistant', $reply]
);

echo json_encode(['reply' => $reply]);
