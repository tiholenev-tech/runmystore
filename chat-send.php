<?php
// chat-send.php — ВЕРСИЯ: ДИНАМИЧЕН БИЗНЕС КОНСУЛТАНТ
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
$supato_mode = $_SESSION['supato_mode'] ?? 0;
$currency    = $_SESSION['currency'] ?? 'EUR';
$language    = $_SESSION['language'] ?? 'bg';

$body    = json_decode(file_get_contents('php://input'), true);
$message = trim($body['message'] ?? '');

if (!$message) {
    echo json_encode(['error' => 'Празно съобщение']);
    exit;
}

// ── СЪБИРАНЕ НА КОНТЕКСТ ────────────────────────────────────
$store = DB::run('SELECT s.name FROM stores s WHERE s.id = ?', [$store_id])->fetch();
$store_name = $store['name'] ?? 'Твоят обект';

// ── ИНТЕЛИГЕНТНА СИСТЕМНА ИНСТРУКЦИЯ ────────────────────────
$system_instruction = "Ти си елитен бизнес стратег и консултант за RunMyStore.ai. 
Твоята мисия е да проведеш ОНБОРДИНГ разговор с Ники.

ПРАВИЛА ЗА ПОВЕДЕНИЕ:
1. ЗАБРАВИ ЗА АНКЕТИТЕ: Не следвай фиксиран списък. Води жив, професионален разговор.
2. АНАЛИЗИРАЙ ВИНАГИ: Преди да зададеш следващ въпрос, коментирай предишния отговор на Ники.
3. БИЗНЕС ЛОГИКА: 
   - Ако Ники продава 'Домашни потреби', говори за предизвикателството с хилядите дребни артикули.
   - Ако стоките са СКЪПИ, фокусирай се върху ревизии, липси и сигурност.
   - Ако са ЕВТИНИ, фокусирай се върху бързина на обслужване и голям оборот.
4. КОНТЕКСТНИ ВЪПРОСИ: Не питай просто 'Колко магазина имаш?'. Кажи: 'Ники, при този тип стока контролът между обектите е най-труден. Колко магазина управляваш, за да ти покажа как да ги следиш от телефона?'
5. УАУ МОМЕНТ: Целта ти е накрая да изчислиш 'Lost Revenue' (Загубен оборот) и да го впечатлиш.
6. СТИЛ: Умен приятел търговец. Кратко, но съдържателно.";

// ── ИСТОРИЯ НА РАЗГОВОРА ────────────────────────────────────
$history_rows = DB::run('SELECT role, content FROM chat_messages WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 15', [$tenant_id])->fetchAll();
$contents = [];
foreach (array_reverse($history_rows) as $row) {
    $contents[] = [
        'role' => ($row['role'] === 'assistant' ? 'model' : 'user'),
        'parts' => [['text' => $row['content']]]
    ];
}
$contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

// ── ИЗВИКВАНЕ НА GEMINI API ─────────────────────────────────
$api_url = "https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;

$payload = [
    'contents' => $contents,
    'system_instruction' => ['parts' => [['text' => $system_instruction]]],
    'generationConfig' => [
        'temperature' => 0.7, // Малко по-висока за по-естествен разговор
        'maxOutputTokens' => 1000
    ]
];

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$reply = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Хюстън, имаме проблем. Опитай пак.';

// ── ЗАПИС В БАЗАТА ──────────────────────────────────────────
DB::run('INSERT INTO chat_messages (tenant_id, user_id, store_id, role, content) VALUES (?,?,?,?,?)', [$tenant_id, $user_id, $store_id, 'user', $message]);
DB::run('INSERT INTO chat_messages (tenant_id, user_id, store_id, role, content) VALUES (?,?,?,?,?)', [$tenant_id, null, $store_id, 'assistant', $reply]);

echo json_encode(['reply' => $reply]);
