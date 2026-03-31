<?php
// chat-send.php — приема съобщение, праща към Claude API, връща JSON
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$tenant_id   = $_SESSION['tenant_id'];
$user_id     = $_SESSION['user_id'];
$store_id    = $_SESSION['store_id'];
$supato_mode = $_SESSION['supato_mode'] ?? 0;
$currency    = $_SESSION['currency'] ?? 'EUR';
$language    = $_SESSION['language'] ?? 'bg';

// Четем input
$body    = json_decode(file_get_contents('php://input'), true);
$message = trim($body['message'] ?? '');

if (!$message) {
    echo json_encode(['error' => 'Empty message']);
    exit;
}

// ── КОНТЕКСТ ЗА AI ──────────────────────────────────────────

// Основна информация за магазина
$store = DB::run(
    'SELECT s.name, t.currency, t.supato_mode, t.language
     FROM stores s JOIN tenants t ON t.id = s.tenant_id
     WHERE s.id = ? LIMIT 1',
    [$store_id]
)->fetch();

// Топ 10 артикула по наличност
$products = DB::run(
    'SELECT p.name, p.code, p.retail_price, i.quantity, i.min_quantity, p.unit
     FROM inventory i
     JOIN products p ON p.id = i.product_id
     WHERE i.store_id = ? AND i.tenant_id = ?
     ORDER BY i.quantity DESC LIMIT 10',
    [$store_id, $tenant_id]
)->fetchAll();

// Последни 10 движения
$movements = DB::run(
    'SELECT sm.type, sm.quantity, p.name, sm.created_at, sm.note
     FROM stock_movements sm
     JOIN products p ON p.id = sm.product_id
     WHERE sm.store_id = ? AND sm.tenant_id = ?
     ORDER BY sm.created_at DESC LIMIT 10',
    [$store_id, $tenant_id]
)->fetchAll();

// Артикули с ниска наличност
$low_stock = DB::run(
    'SELECT p.name, i.quantity, i.min_quantity, p.unit
     FROM inventory i
     JOIN products p ON p.id = i.product_id
     WHERE i.store_id = ? AND i.tenant_id = ? AND i.quantity <= i.min_quantity AND i.min_quantity > 0',
    [$store_id, $tenant_id]
)->fetchAll();

// Строим системния промпт
$store_name    = $store['name'] ?? 'Магазин';
$movement_term = $supato_mode ? 'изходящо движение' : 'продажба';

$system = "Ти си AI бизнес асистент за магазин '{$store_name}'.
Валута: {$currency}. Език: {$language}.
Отговаряй само на езика на потребителя.
Бъди кратък и конкретен. Максимум 3-4 изречения.

ВАЖНО: В България всяко намаление на наличност се нарича '{$movement_term}', НЕ 'продажба' — това е юридическо изискване.

ТЕКУЩИ НАЛИЧНОСТИ (топ 10):
";

if ($products) {
    foreach ($products as $p) {
        $system .= "- {$p['name']}: {$p['quantity']} {$p['unit']}, цена {$p['retail_price']} {$currency}";
        if ($p['quantity'] <= $p['min_quantity'] && $p['min_quantity'] > 0) {
            $system .= " ⚠️ НИСКА НАЛИЧНОСТ";
        }
        $system .= "\n";
    }
} else {
    $system .= "- Няма въведени артикули още.\n";
}

if ($low_stock) {
    $system .= "\nАРТИКУЛИ С НИСКА НАЛИЧНОСТ:\n";
    foreach ($low_stock as $l) {
        $system .= "- {$l['name']}: остават {$l['quantity']} {$l['unit']} (минимум: {$l['min_quantity']})\n";
    }
}

if ($movements) {
    $system .= "\nПОСЛЕДНИ ДВИЖЕНИЯ:\n";
    foreach ($movements as $m) {
        $system .= "- {$m['type']}: {$m['quantity']} x {$m['name']} ({$m['created_at']})\n";
    }
}

// ── ИСТОРИЯ НА РАЗГОВОРА (последни 10) ──────────────────────
$history = DB::run(
    'SELECT role, content FROM chat_messages
     WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 10',
    [$tenant_id]
)->fetchAll();

$history = array_reverse($history);

$claude_messages = [];
foreach ($history as $h) {
    $claude_messages[] = ['role' => $h['role'], 'content' => $h['content']];
}
$claude_messages[] = ['role' => 'user', 'content' => $message];

// ── CLAUDE API ───────────────────────────────────────────────
$payload = [
    'model'      => 'claude-sonnet-4-20250514',
    'max_tokens' => 1000,
    'system'     => $system,
    'messages'   => $claude_messages,
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . CLAUDE_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $http_code !== 200) {
    echo json_encode(['error' => 'Claude API error: ' . $http_code]);
    exit;
}

$result = json_decode($response, true);
$reply  = $result['content'][0]['text'] ?? '';

if (!$reply) {
    echo json_encode(['error' => 'Empty response from Claude']);
    exit;
}

// ── ЗАПИСВАМЕ И ДВЕТЕ СЪОБЩЕНИЯ В БД ────────────────────────
DB::run(
    'INSERT INTO chat_messages (tenant_id, user_id, store_id, role, content) VALUES (?, ?, ?, ?, ?)',
    [$tenant_id, $user_id, $store_id, 'user', $message]
);
DB::run(
    'INSERT INTO chat_messages (tenant_id, user_id, store_id, role, content) VALUES (?, ?, ?, ?, ?)',
    [$tenant_id, null, $store_id, 'assistant', $reply]
);

echo json_encode(['reply' => $reply]);