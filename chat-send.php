<?php
/**
 * chat-send.php — AI Chat Endpoint
 * Системен промпт от buildSystemPrompt() — 7 слоя
 * Gemini system_instruction формат (правилен)
 * RunMyStore.ai С26
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/build-prompt.php';

header('Content-Type: application/json; charset=utf-8');

// ── AUTH ──────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$tenant_id   = (int)$_SESSION['tenant_id'];
$user_id     = (int)$_SESSION['user_id'];
$store_id    = (int)$_SESSION['store_id'];
$role        = $_SESSION['role'] ?? 'seller';
$supato_mode = (int)($_SESSION['supato_mode'] ?? 0);

// ── INPUT ─────────────────────────────────────────────────────
$body    = json_decode(file_get_contents('php://input'), true);
$message = trim($body['message'] ?? '');

if ($message === '') {
    echo json_encode(['error' => 'Празно съобщение']); exit;
}
$message = mb_substr(strip_tags($message), 0, 2000);

// ── "ЗАПОМНИ" КОМАНДА ─────────────────────────────────────────
$is_remember = false;
if (preg_match('/^запомни[\s:,]+(.+)/iu', $message, $m)) {
    $remember_text = trim($m[1]);
    if (mb_strlen($remember_text) >= 3 && mb_strlen($remember_text) <= 500) {
        $is_remember = true;
        $count = (int)DB::run(
            'SELECT COUNT(*) FROM tenant_ai_memory WHERE tenant_id=?',
            [$tenant_id]
        )->fetchColumn();
        if ($count >= 100) {
            DB::run(
                'DELETE FROM tenant_ai_memory WHERE tenant_id=? ORDER BY created_at ASC LIMIT ?',
                [$tenant_id, $count - 99]
            );
        }
        DB::run(
            'INSERT INTO tenant_ai_memory (tenant_id, store_id, `key`, `value`, created_at)
             VALUES (?,?,?,?,NOW())',
            [$tenant_id, $store_id, 'user_note', $remember_text]
        );
        DB::run('INSERT INTO chat_messages (tenant_id, store_id, user_id, role, content, created_at) VALUES (?,?,?,?,?,NOW())',
            [$tenant_id, $store_id, $user_id, 'user', $message]);
        $reply = "Запомних: \"{$remember_text}\" ✅";
        DB::run('INSERT INTO chat_messages (tenant_id, store_id, user_id, role, content, created_at) VALUES (?,?,?,?,?,NOW())',
            [$tenant_id, $store_id, $user_id, 'assistant', $reply]);
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

// ── ИСТОРИЯ ───────────────────────────────────────────────────
$history_rows = DB::run(
    'SELECT role, content FROM chat_messages
     WHERE tenant_id=? AND store_id=?
     ORDER BY created_at DESC LIMIT 20',
    [$tenant_id, $store_id]
)->fetchAll();
$history_rows = array_reverse($history_rows);

// ── ЗАПИС НА СЪОБЩЕНИЕТО ──────────────────────────────────────
DB::run(
    'INSERT INTO chat_messages (tenant_id, store_id, user_id, role, content, created_at) VALUES (?,?,?,?,?,NOW())',
    [$tenant_id, $store_id, $user_id, 'user', $message]
);

// ── GEMINI CALL ───────────────────────────────────────────────
$reply      = null;
$model_used = 'gemini';

if (defined('GEMINI_API_KEY') && GEMINI_API_KEY) {
    try {
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
                'maxOutputTokens' => 1024,
                'topP'            => 0.9,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_errno($ch);
        curl_close($ch);

        if (!$cerr && $code === 200) {
            $data  = json_decode($res, true);
            $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        }

        // 429 → опитай втори ключ
        if ((!$reply || $code === 429) && defined('GEMINI_API_KEY_2') && GEMINI_API_KEY_2) {
            $url2 = 'https://generativelanguage.googleapis.com/v1beta/models/'
                  . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY_2;
            $ch2 = curl_init($url2);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $res2  = curl_exec($ch2);
            $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            if ($code2 === 200) {
                $data2 = json_decode($res2, true);
                $reply = $data2['candidates'][0]['content']['parts'][0]['text'] ?? $reply;
            }
        }

        if (!$reply) throw new Exception("Gemini empty response: HTTP {$code}");

    } catch (Throwable $e) {
        error_log("Gemini error: " . $e->getMessage());
        $reply = null;
    }
}

// ── CLAUDE FALLBACK ───────────────────────────────────────────
if (!$reply && defined('CLAUDE_API_KEY') && CLAUDE_API_KEY) {
    $model_used    = 'claude';
    $claude_msgs   = [];
    foreach ($history_rows as $row) {
        $claude_msgs[] = [
            'role'    => $row['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => $row['content'],
        ];
    }
    $claude_msgs[] = ['role' => 'user', 'content' => $message];

    // Осигуряваме редуване user/assistant
    $fixed = [];
    $last  = null;
    foreach ($claude_msgs as $cm) {
        if ($cm['role'] === $last) continue;
        $fixed[] = $cm;
        $last    = $cm['role'];
    }
    if (empty($fixed) || $fixed[0]['role'] !== 'user') {
        array_unshift($fixed, ['role' => 'user', 'content' => '...']);
    }

    try {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'model'      => 'claude-sonnet-4-5-20250514',
                'max_tokens' => 1024,
                'system'     => $system_prompt,
                'messages'   => $fixed,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . CLAUDE_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            $data  = json_decode($res, true);
            $reply = $data['content'][0]['text'] ?? null;
        }
    } catch (Throwable $e) {
        error_log("Claude error: " . $e->getMessage());
    }
}

if (!$reply) {
    $reply = 'Системата е натоварена. Опитай пак след минута.';
}

// ── ПАРСВАНЕ НА DEEPLINKS ─────────────────────────────────────
$deeplink_map = [
    'продукт' => 'products.php',  'склад'    => 'products.php',
    'артикул' => 'products.php',  'zombie'   => 'products.php?filter=zombie',
    'зомби'   => 'products.php?filter=zombie',
    'ниска'   => 'products.php?filter=low',
    'справк'  => 'stats.php',     'статистик' => 'stats.php',
    'финанс'  => 'stats.php?tab=finance',
    'продажб' => 'sale.php',      'поръч'    => 'purchase-orders.php',
    'трансфер'=> 'transfers.php', 'доставк'  => 'deliveries.php',
];
$actions = [];
preg_replace_callback('/\[([^\]]+?)→\]/u', function($m) use (&$actions, $deeplink_map) {
    $label = trim($m[1]);
    $href  = '#';
    foreach ($deeplink_map as $kw => $url) {
        if (str_contains(mb_strtolower($label), $kw)) { $href = $url; break; }
    }
    $actions[] = ['label' => $label, 'url' => $href];
    return $m[0];
}, $reply);

// ── MEMORY SAVE ОТ AI ─────────────────────────────────────────
if (preg_match_all('/SAVE_MEMORY\|([^|]+)\|([^\n]+)/u', $reply, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $mx) {
        $k = trim($mx[1]); $v = trim($mx[2]);
        if ($k && $v) {
            DB::run(
                'INSERT INTO tenant_ai_memory (tenant_id, store_id, `key`, `value`, created_at)
                 VALUES (?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), created_at=NOW()',
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

// ── ЗАПИС НА ОТГОВОРА ─────────────────────────────────────────
DB::run(
    'INSERT INTO chat_messages (tenant_id, store_id, user_id, role, content, created_at) VALUES (?,?,?,?,?,NOW())',
    [$tenant_id, $store_id, $user_id, 'assistant', $reply]
);

// Пази само последните 100 съобщения
DB::run(
    'DELETE FROM chat_messages WHERE tenant_id=? AND store_id=? AND id NOT IN (
        SELECT id FROM (SELECT id FROM chat_messages WHERE tenant_id=? AND store_id=? ORDER BY created_at DESC LIMIT 100) AS sub
    )',
    [$tenant_id, $store_id, $tenant_id, $store_id]
);

// ── RESPONSE ──────────────────────────────────────────────────
$out = ['reply' => $reply, 'model' => $model_used];
if (!empty($actions))     $out['actions'] = $actions;
if ($action_data)         $out['action']  = $action_data;

echo json_encode($out, JSON_UNESCAPED_UNICODE);
