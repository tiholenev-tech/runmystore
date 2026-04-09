<?php
/**
 * chat-send.php — AI Chat Endpoint
 * RunMyStore.ai С31
 *
 * Промени спрямо С28:
 * - maxOutputTokens: 1024 → 4096 (fix за отрязани отговори)
 * - Claude fallback МАХНАТ — само 2 Gemini ключа
 * - ai-safety.php интегриран (preValidate + postValidate + logAdvice)
 * - DB колони коригирани (total, canceled, inventory.quantity, inventory.min_quantity)
 * - Analysis rule добавено в build-prompt.php
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/build-prompt.php';
require_once __DIR__ . '/ai-safety.php';

header('Content-Type: application/json; charset=utf-8');

// ── AUTH ──────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)$_SESSION['store_id'];
$role      = $_SESSION['role'] ?? 'seller';

// ── INPUT ─────────────────────────────────────────────────────
$body    = json_decode(file_get_contents('php://input'), true);
$message = trim($body['message'] ?? '');

if ($message === '') {
    echo json_encode(['error' => 'Празно съобщение']);
    exit;
}
$message = mb_substr(strip_tags($message), 0, 2000);

// ── "ЗАПОМНИ" КОМАНДА (бърз път, без Gemini) ─────────────────
if (preg_match('/^запомни[\s:,]+(.+)/iu', $message, $m)) {
    $remember_text = trim($m[1]);
    if (mb_strlen($remember_text) >= 3 && mb_strlen($remember_text) <= 500) {

        // Лимит 100 записа — изтрий най-старите ако е нужно
        $count = (int)DB::run(
            'SELECT COUNT(*) FROM tenant_ai_memory WHERE tenant_id = ?',
            [$tenant_id]
        )->fetchColumn();
        if ($count >= 100) {
            $del = max(1, $count - 99);
            DB::run(
                "DELETE FROM tenant_ai_memory WHERE tenant_id = ? ORDER BY created_at ASC LIMIT {$del}",
                [$tenant_id]
            );
        }

        DB::run(
            'INSERT INTO tenant_ai_memory (tenant_id, store_id, `key`, `value`, created_at)
             VALUES (?, ?, ?, ?, NOW())',
            [$tenant_id, $store_id, 'user_note', $remember_text]
        );

        // Запис на двете съобщения
        DB::run(
            'INSERT INTO chat_messages (tenant_id, store_id, user_id, role, content, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [$tenant_id, $store_id, $user_id, 'user', $message]
        );

        $reply = "Запомних: \"{$remember_text}\" ✅";

        DB::run(
            'INSERT INTO chat_messages (tenant_id, store_id, user_id, role, content, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [$tenant_id, $store_id, $user_id, 'assistant', $reply]
        );

        logAdvice($tenant_id, $store_id, $user_id, $message, $reply, $reply);

        echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ── СИСТЕМЕН ПРОМПТ ───────────────────────────────────────────
try {
    $system_prompt = buildSystemPrompt($tenant_id, $store_id, $role);
} catch (Throwable $e) {
    error_log("buildSystemPrompt error: " . $e->getMessage());
    $system_prompt = "You are a helpful AI assistant for a retail store. Respond in Bulgarian. Be concise.";
}

// ── SAFETY СЛОЙ 1: PRE-VALIDATION ─────────────────────────────
$safety = preValidate($tenant_id, $store_id, $role);
$system_prompt .= $safety['constraints'];

// ── ИСТОРИЯ ───────────────────────────────────────────────────
$history_rows = DB::run(
    'SELECT role, content FROM chat_messages
     WHERE tenant_id = ? AND store_id = ?
     ORDER BY created_at DESC LIMIT 20',
    [$tenant_id, $store_id]
)->fetchAll();
$history_rows = array_reverse($history_rows);

// ── ЗАПИС НА ПОТРЕБИТЕЛСКОТО СЪОБЩЕНИЕ ────────────────────────
DB::run(
    'INSERT INTO chat_messages (tenant_id, store_id, user_id, role, content, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())',
    [$tenant_id, $store_id, $user_id, 'user', $message]
);

// ── GEMINI CALL (2 ключа, без Claude fallback) ────────────────
$raw_reply = null;

$contents = [];
foreach ($history_rows as $row) {
    $contents[] = [
        'role'  => $row['role'] === 'assistant' ? 'model' : 'user',
        'parts' => [['text' => $row['content']]]
    ];
}
$contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

$payload = [
    'contents'           => $contents,
    'system_instruction' => ['parts' => [['text' => $system_prompt]]],
    'generationConfig'   => [
        'temperature'     => 0.7,
        'maxOutputTokens' => 4096,
        'topP'            => 0.9,
    ],
    'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
    ],
];

$keys_to_try = [];
if (defined('GEMINI_API_KEY') && GEMINI_API_KEY)     $keys_to_try[] = GEMINI_API_KEY;
if (defined('GEMINI_API_KEY_2') && GEMINI_API_KEY_2) $keys_to_try[] = GEMINI_API_KEY_2;

foreach ($keys_to_try as $api_key) {
    try {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . GEMINI_MODEL . ':generateContent?key=' . $api_key;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_errno($ch);
        curl_close($ch);

        if (!$cerr && $code === 200) {
            $data      = json_decode($res, true);
            $raw_reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($raw_reply) break;
        }

        error_log("Gemini HTTP {$code} key=" . substr($api_key, 0, 8) . " body=" . substr($res, 0, 200));

    } catch (Throwable $e) {
        error_log("Gemini error: " . $e->getMessage());
    }
}

if (!$raw_reply) {
    $raw_reply = 'AI обработва друга заявка — опитай след 30 секунди.';
}

// ── SAFETY СЛОЙ 2: POST-VALIDATION ────────────────────────────
$reply = postValidate($raw_reply);

// ── ПАРСВАНЕ НА DEEPLINKS ─────────────────────────────────────
$deeplink_map = [
    'продукт'   => 'products.php',
    'склад'     => 'products.php',
    'артикул'   => 'products.php',
    'zombie'    => 'products.php?filter=zombie',
    'зомби'     => 'products.php?filter=zombie',
    'ниска'     => 'products.php?filter=low',
    'справк'    => 'stats.php',
    'статистик' => 'stats.php',
    'финанс'    => 'stats.php?tab=finance',
    'продажб'   => 'sale.php',
    'поръч'     => 'purchase-orders.php',
    'трансфер'  => 'transfers.php',
    'доставк'   => 'deliveries.php',
];

$actions = [];
preg_replace_callback('/\[([^\]]+?)→\]/u', function ($m) use (&$actions, $deeplink_map) {
    $label = trim($m[1]);
    $href  = '#';
    foreach ($deeplink_map as $kw => $url) {
        if (str_contains(mb_strtolower($label), $kw)) {
            $href = $url;
            break;
        }
    }
    $actions[] = ['label' => $label, 'url' => $href];
    return $m[0];
}, $reply);

// ── MEMORY SAVE ОТ AI ─────────────────────────────────────────
if (preg_match_all('/SAVE_MEMORY\|([^|]+)\|([^\n]+)/u', $reply, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $mx) {
        $k = trim($mx[1]);
        $v = trim($mx[2]);
        if ($k && $v) {
            DB::run(
                'INSERT INTO tenant_ai_memory (tenant_id, store_id, `key`, `value`, created_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), created_at = NOW()',
                [$tenant_id, $store_id, $k, $v]
            );
        }
    }
    $reply = trim(preg_replace('/SAVE_MEMORY\|[^\n]+\n?/u', '', $reply));
}

// ── ПАРСВАНЕ НА JSON ACTION ───────────────────────────────────
$action_data = null;
if (preg_match('/\{[^{}]*"action"\s*:\s*"[^"]+[^{}]*\}/u', $reply, $aj)) {
    $parsed = json_decode($aj[0], true);
    if ($parsed && isset($parsed['action'])) {
        $action_data = $parsed;
        $reply = trim(str_replace($aj[0], '', $reply));
    }
}

// ── ЗАПИС НА AI ОТГОВОРА ──────────────────────────────────────
DB::run(
    'INSERT INTO chat_messages (tenant_id, store_id, user_id, role, content, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())',
    [$tenant_id, $store_id, $user_id, 'assistant', $reply]
);

// Пазим последните 100 чат съобщения — безопасно с 2 заявки
$cutoff_msg = DB::run(
    'SELECT id FROM chat_messages
     WHERE tenant_id = ? AND store_id = ?
     ORDER BY created_at DESC
     LIMIT 1 OFFSET 100',
    [$tenant_id, $store_id]
)->fetchColumn();

if ($cutoff_msg) {
    DB::run(
        'DELETE FROM chat_messages WHERE tenant_id = ? AND store_id = ? AND id < ?',
        [$tenant_id, $store_id, (int)$cutoff_msg]
    );
}

// ── SAFETY СЛОЙ 3: LOGGING ────────────────────────────────────
logAdvice($tenant_id, $store_id, $user_id, $message, $raw_reply, $reply);

// ── RESPONSE ──────────────────────────────────────────────────
$out = ['reply' => $reply];
if (!empty($actions)) $out['actions'] = $actions;
if ($action_data)     $out['action']  = $action_data;

echo json_encode($out, JSON_UNESCAPED_UNICODE);
