<?php
/**
 * chat-send.php — AI Chat Handler с Context Injection
 *
 * ЛОГИКА:
 *   1. Взима съобщение от потребителя
 *   2. Проверява за "Запомни" команда → tenant_ai_memory (FIFO 100)
 *   3. Събира РЕАЛЕН контекст от БД (8 заявки, филтрирани по роля)
 *   4. Праща до Gemini (основен). При грешка → Claude (резервен)
 *   5. Парсва AI отговор за JSON actions (трансфер, навигация)
 *   6. Записва и двете съобщения в chat_messages
 *
 * FALLBACK: Gemini ключ 1 → 429/5xx → Claude. Всички фейлнат → "Опитай пак след минута."
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

// ══════════════════════════════════════════════════════════════
// "ЗАПОМНИ" КОМАНДА — детекция + INSERT + FIFO 100
// ══════════════════════════════════════════════════════════════
$is_remember = false;
$remember_text = '';

// Проверяваме дали съобщението започва с "запомни" (case-insensitive, UTF-8)
if (preg_match('/^запомни[\s:,]+(.+)/iu', $message, $remMatch)) {
    $remember_text = trim($remMatch[1]);
    if (mb_strlen($remember_text) >= 3 && mb_strlen($remember_text) <= 500) {
        $is_remember = true;

        // Брой текущи записи
        $count = (int)DB::run(
            'SELECT COUNT(*) FROM tenant_ai_memory WHERE tenant_id = ?',
            [$tenant_id]
        )->fetchColumn();

        // FIFO — трий най-старите ако >= 100
        if ($count >= 100) {
            $excess = $count - 99; // освобождаваме 1 място
            DB::run(
                'DELETE FROM tenant_ai_memory WHERE tenant_id = ? ORDER BY created_at ASC LIMIT ?',
                [$tenant_id, $excess]
            );
        }

        // Записваме
        DB::run(
            'INSERT INTO tenant_ai_memory (tenant_id, user_id, content) VALUES (?, ?, ?)',
            [$tenant_id, $user_id, $remember_text]
        );
    }
}

// ══════════════════════════════════════════════════════════════
// КОНТЕКСТ — РЕАЛНИ ДАННИ ОТ БД (8 заявки)
// ══════════════════════════════════════════════════════════════

$store = DB::run(
    'SELECT name FROM stores WHERE id = ? AND tenant_id = ?',
    [$store_id, $tenant_id]
)->fetch();
$store_name = $store['name'] ?? 'Обект';

// ── 1. Продажби днес (всички роли) ──────────────────────────
$today_sales = DB::run(
    'SELECT COUNT(*) as cnt, COALESCE(SUM(total), 0) as total_sum
     FROM sales
     WHERE tenant_id = ? AND store_id = ? AND DATE(created_at) = CURDATE() AND status = "completed"',
    [$tenant_id, $store_id]
)->fetch();

$ctx_sales = "Продажби днес: {$today_sales['cnt']} бр. за {$today_sales['total_sum']} {$currency}.";

// ── 2. Печалба днес (само owner) ────────────────────────────
$ctx_profit = '';
if ($role === 'owner') {
    $profit_data = DB::run(
        'SELECT COALESCE(SUM(si.total - (si.cost_price * si.quantity)), 0) as profit
         FROM sale_items si
         JOIN sales s ON s.id = si.sale_id
         WHERE s.tenant_id = ? AND s.store_id = ? AND DATE(s.created_at) = CURDATE() AND s.status = "completed"',
        [$tenant_id, $store_id]
    )->fetch();
    $ctx_profit = "Печалба днес: {$profit_data['profit']} {$currency}.";
}

// ── 3. Ниски наличности (qty > 0 AND qty <= min_quantity) ───
$low_stock = DB::run(
    'SELECT p.name, p.size, p.color, i.quantity, i.min_quantity
     FROM inventory i
     JOIN products p ON p.id = i.product_id
     WHERE i.tenant_id = ? AND i.store_id = ? AND i.quantity > 0 AND i.min_quantity > 0 AND i.quantity <= i.min_quantity AND p.is_active = 1
     ORDER BY i.quantity ASC
     LIMIT 10',
    [$tenant_id, $store_id]
)->fetchAll();

$ctx_low = '';
if ($low_stock) {
    $items = [];
    foreach ($low_stock as $ls) {
        $label = $ls['name'];
        if ($ls['size']) $label .= ' ' . $ls['size'];
        if ($ls['color']) $label .= ' ' . $ls['color'];
        $items[] = "{$label}: {$ls['quantity']} бр. (мин. {$ls['min_quantity']})";
    }
    $ctx_low = "Ниски наличности (" . count($low_stock) . " артикула): " . implode('; ', $items) . ".";
}

// ── 4. Нулеви наличности (qty = 0, имали продажби последните 30 дни) ──
$zero_stock = DB::run(
    'SELECT p.name, p.size, p.color
     FROM inventory i
     JOIN products p ON p.id = i.product_id
     WHERE i.tenant_id = ? AND i.store_id = ? AND i.quantity = 0 AND p.is_active = 1
       AND p.id IN (
         SELECT si.product_id FROM sale_items si
         JOIN sales s ON s.id = si.sale_id
         WHERE s.tenant_id = ? AND s.store_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND s.status = "completed"
       )
     LIMIT 10',
    [$tenant_id, $store_id, $tenant_id, $store_id]
)->fetchAll();

$ctx_zero = '';
if ($zero_stock) {
    $items = [];
    foreach ($zero_stock as $zs) {
        $label = $zs['name'];
        if ($zs['size']) $label .= ' ' . $zs['size'];
        if ($zs['color']) $label .= ' ' . $zs['color'];
        $items[] = $label;
    }
    $ctx_zero = "НУЛА в наличност (имали продажби): " . implode(', ', $items) . ".";
}

// ── 5. Дългове към доставчици (само owner) ──────────────────
$ctx_debts = '';
if ($role === 'owner') {
    $debts = DB::run(
        'SELECT s.name as supplier_name, SUM(d.total) as debt_total, MIN(d.payment_due_date) as nearest_due
         FROM deliveries d
         JOIN suppliers s ON s.id = d.supplier_id
         WHERE d.tenant_id = ? AND d.payment_status != "paid"
         GROUP BY d.supplier_id, s.name
         ORDER BY nearest_due ASC
         LIMIT 5',
        [$tenant_id]
    )->fetchAll();

    if ($debts) {
        $items = [];
        foreach ($debts as $debt) {
            $due = $debt['nearest_due'] ? " (падеж: {$debt['nearest_due']})" : '';
            $items[] = "{$debt['supplier_name']}: {$debt['debt_total']} {$currency}{$due}";
        }
        $ctx_debts = "Дължимо към доставчици: " . implode('; ', $items) . ".";
    }
}

// ── 6. Последни трансфери (owner + manager) ─────────────────
$ctx_transfers = '';
if (in_array($role, ['owner', 'manager'])) {
    $transfers = DB::run(
        'SELECT t.status, fs.name as from_name, ts.name as to_name, t.created_at
         FROM transfers t
         JOIN stores fs ON fs.id = t.from_store_id
         JOIN stores ts ON ts.id = t.to_store_id
         WHERE t.tenant_id = ? AND t.status IN ("pending","in_transit")
         ORDER BY t.created_at DESC
         LIMIT 5',
        [$tenant_id]
    )->fetchAll();

    if ($transfers) {
        $items = [];
        foreach ($transfers as $tr) {
            $st = $tr['status'] === 'pending' ? 'чакащ' : 'в транзит';
            $items[] = "{$tr['from_name']}→{$tr['to_name']} ({$st})";
        }
        $ctx_transfers = "Активни трансфери: " . implode('; ', $items) . ".";
    }
}

// ── 7. Чакащи поръчки (само owner) ─────────────────────────
$ctx_orders = '';
if ($role === 'owner') {
    $orders = DB::run(
        'SELECT po.status, s.name as supplier_name, po.expected_date
         FROM purchase_orders po
         LEFT JOIN suppliers s ON s.id = po.supplier_id
         WHERE po.tenant_id = ? AND po.status IN ("draft","sent","partial")
         ORDER BY po.expected_date ASC
         LIMIT 5',
        [$tenant_id]
    )->fetchAll();

    if ($orders) {
        $items = [];
        foreach ($orders as $ord) {
            $st_map = ['draft' => 'чернова', 'sent' => 'изпратена', 'partial' => 'частично получена'];
            $st = $st_map[$ord['status']] ?? $ord['status'];
            $exp = $ord['expected_date'] ? " (очаквана: {$ord['expected_date']})" : '';
            $items[] = "{$ord['supplier_name']}: {$st}{$exp}";
        }
        $ctx_orders = "Чакащи поръчки: " . implode('; ', $items) . ".";
    }
}

// ── 8. Списък обекти (owner + manager виждат всички) ────────
$ctx_stores = '';
if (in_array($role, ['owner', 'manager'])) {
    $all_stores = DB::run(
        'SELECT id, name FROM stores WHERE tenant_id = ? AND is_active = 1',
        [$tenant_id]
    )->fetchAll();

    if (count($all_stores) > 1) {
        $names = array_column($all_stores, 'name');
        $ctx_stores = "Обекти (" . count($all_stores) . "): " . implode(', ', $names) . ".";
    }
}

// ── Tenant AI Memory (последните 20) ────────────────────────
$memories = DB::run(
    'SELECT content FROM tenant_ai_memory WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 20',
    [$tenant_id]
)->fetchAll(PDO::FETCH_COLUMN);

$memory_block = '';
if ($memories) {
    $memory_block = "\n\nЗАПОМНЕНО ОТ СОБСТВЕНИКА:\n" . implode("\n", array_reverse($memories));
}

// ══════════════════════════════════════════════════════════════
// SYSTEM PROMPT (по AI_BRAIN_v3_3)
// ══════════════════════════════════════════════════════════════

$sale_term = $supato_mode
    ? 'изходящо движение (НИКОГА не казвай "продажба" — по закон за СУПТО в България)'
    : 'продажба';

$role_context = match($role) {
    'manager' => 'Управител — вижда доставни цени, НЕ вижда печалби, марж или дългове към доставчици.',
    'seller'  => 'Продавач — вижда само своя обект, НЕ вижда доставни цени, печалби или справки.',
    default   => 'Собственик — вижда всичко: печалби, марж, дългове, всички обекти.'
};

// Сглобяваме контекст блок
$data_context = implode("\n", array_filter([
    $ctx_sales,
    $ctx_profit,
    $ctx_low,
    $ctx_zero,
    $ctx_debts,
    $ctx_transfers,
    $ctx_orders,
    $ctx_stores
]));

$system = "Ти си AI бизнес асистент на RunMyStore.ai за обект \"{$store_name}\".
Потребител: {$user_name} ({$role_context})
Валута: {$currency} (формат: 1.234,50 €)

АКТУАЛНИ ДАННИ ОТ БАЗАТА:
{$data_context}

ПРАВИЛА:
1. Умен приятел търговец. Конкретно: суми, бройки, дати. Кратко: 2-3 изречения.
2. При {$sale_term} — отговаряй с 1 изречение.
3. НИКОГА не казвай \"Грешка\", \"Как мога да помогна?\" или \"Имаш ли предвид X?\".
4. Разпознавай жаргон: \"дреги\"=дрехи, \"офки\"=обувки, \"якита\"=якета.
5. При неясна команда — 1 уточняващ въпрос, никога повече.
6. При деструктивно действие — пита преди изпълнение.
7. Не поправяй правописа на потребителя.
8. Ползвай АКТУАЛНИТЕ ДАННИ — не измисляй числа!
9. Филтрирай по роля: " . match($role) {
    'seller'  => "НЕ показвай доставни цени, печалби, марж, справки, дългове.",
    'manager' => "НЕ показвай печалби, марж, дългове към доставчици.",
    default   => "Показвай всичко."
} . "
10. Когато предлагаш действие, добави deeplink: [📦 Виж артикулите →], [⚠️ Поръчай сега →], [📊 Статистика →], [💰 Продажба →], [🔄 Трансфер →].
11. Ако потребителят иска действие (трансфер, поръчка), върни JSON в края: {\"action\":\"transfer\",\"details\":\"описание\"} — системата ще поиска потвърждение.
{$memory_block}";

// ══════════════════════════════════════════════════════════════
// ПРОВЕРКА ЗА "ЗАПОМНИ" — бърз отговор без AI call
// ══════════════════════════════════════════════════════════════
if ($is_remember && $remember_text) {
    $reply = "Запомних: \"{$remember_text}\"";

    DB::run(
        'INSERT INTO chat_messages (tenant_id, user_id, store_id, role, content) VALUES (?,?,?,?,?)',
        [$tenant_id, $user_id, $store_id, 'user', $message]
    );
    DB::run(
        'INSERT INTO chat_messages (tenant_id, user_id, store_id, role, content) VALUES (?,?,?,?,?)',
        [$tenant_id, null, $store_id, 'assistant', $reply]
    );

    echo json_encode(['reply' => $reply]);
    exit;
}

// ══════════════════════════════════════════════════════════════
// ИСТОРИЯ (последните 15 съобщения)
// ══════════════════════════════════════════════════════════════
$history_rows = DB::run(
    'SELECT role, content FROM chat_messages WHERE tenant_id = ? AND store_id = ? ORDER BY created_at DESC LIMIT 15',
    [$tenant_id, $store_id]
)->fetchAll();

// ══════════════════════════════════════════════════════════════
// GEMINI (ОСНОВЕН)
// ══════════════════════════════════════════════════════════════
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
        'contents'           => $contents,
        'system_instruction' => ['parts' => [['text' => $system]]],
        'generationConfig'   => ['temperature' => 0.7, 'maxOutputTokens' => 800]
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
        $gemini_failed = true;
    } elseif ($http_code >= 400) {
        $gemini_failed = true;
    } else {
        $result = json_decode($response, true);
        $reply  = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$reply) $gemini_failed = true;
    }
} else {
    $gemini_failed = true;
}

// ══════════════════════════════════════════════════════════════
// CLAUDE (РЕЗЕРВЕН)
// ══════════════════════════════════════════════════════════════
if ($gemini_failed && defined('CLAUDE_API_KEY') && CLAUDE_API_KEY) {
    $claude_msgs = [];
    foreach (array_reverse($history_rows) as $row) {
        $claude_msgs[] = [
            'role'    => $row['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => $row['content']
        ];
    }
    $claude_msgs[] = ['role' => 'user', 'content' => $message];

    $claude_payload = [
        'model'      => 'claude-sonnet-4-5-20250514',
        'max_tokens' => 800,
        'system'     => $system,
        'messages'   => $claude_msgs
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

// ══════════════════════════════════════════════════════════════
// ВСИЧКО ФЕЙЛНА
// ══════════════════════════════════════════════════════════════
if (!$reply) {
    $reply = 'Системата е натоварена. Опитай пак след минута.';
}

// ══════════════════════════════════════════════════════════════
// ПАРСВАНЕ НА ACTION ОТ AI ОТГОВОР
// ══════════════════════════════════════════════════════════════
$action_data = null;
if (preg_match('/\{"action"\s*:\s*"[^"]+"/u', $reply, $actionMatch)) {
    // Намираме пълния JSON блок
    $json_start = strpos($reply, $actionMatch[0]);
    $json_candidate = substr($reply, $json_start);

    // Опитваме да парснем JSON
    $bracket_count = 0;
    $json_end = 0;
    for ($i = 0; $i < strlen($json_candidate); $i++) {
        if ($json_candidate[$i] === '{') $bracket_count++;
        if ($json_candidate[$i] === '}') $bracket_count--;
        if ($bracket_count === 0) { $json_end = $i + 1; break; }
    }

    if ($json_end > 0) {
        $json_str = substr($json_candidate, 0, $json_end);
        $parsed_action = json_decode($json_str, true);
        if ($parsed_action && isset($parsed_action['action'])) {
            $action_data = $parsed_action;
            // Махаме JSON-а от текстовия отговор (потребителят не трябва да го вижда)
            $reply = trim(str_replace($json_str, '', $reply));
        }
    }
}

// ══════════════════════════════════════════════════════════════
// ЗАПИС В БАЗАТА
// ══════════════════════════════════════════════════════════════
DB::run(
    'INSERT INTO chat_messages (tenant_id, user_id, store_id, role, content) VALUES (?,?,?,?,?)',
    [$tenant_id, $user_id, $store_id, 'user', $message]
);
DB::run(
    'INSERT INTO chat_messages (tenant_id, user_id, store_id, role, content) VALUES (?,?,?,?,?)',
    [$tenant_id, null, $store_id, 'assistant', $reply]
);

// ══════════════════════════════════════════════════════════════
// RESPONSE
// ══════════════════════════════════════════════════════════════
$response_data = ['reply' => $reply];
if ($action_data) {
    $response_data['action'] = $action_data;
}

echo json_encode($response_data);
