<?php
session_start();
if (!isset($_SESSION['tenant_id'])) { echo json_encode(['items'=>[]]); exit; }

require_once 'config/database.php';
require_once 'config/config.php';

$tenant_id = $_SESSION['tenant_id'];
$data      = json_decode(file_get_contents('php://input'), true);
$text      = trim($data['text'] ?? '');
$store_id  = (int)($data['store_id'] ?? $_SESSION['store_id']);

if (empty($text)) { echo json_encode(['items'=>[]]); exit; }

// Load products for context (top 100 by sold count)
$stmt = $pdo->prepare("
    SELECT p.id, p.code, p.name, p.retail_price, p.wholesale_price,
           COALESCE(i.quantity, 0) as stock
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
    WHERE p.tenant_id = ? AND p.is_active = 1
    ORDER BY (SELECT COUNT(*) FROM stock_movements sm WHERE sm.product_id = p.id AND sm.type = 'out') DESC
    LIMIT 100
");
$stmt->execute([$store_id, $tenant_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$products_context = json_encode($products, JSON_UNESCAPED_UNICODE);

$system_prompt = <<<PROMPT
Ти си асистент за продажби в магазин. Получаваш гласова команда от продавач на български.
Трябва да разпознаеш кои артикули са споменати и в какво количество.

Наличните артикули:
{$products_context}

Върни САМО валиден JSON без никакви обяснения:
{
  "items": [
    {"id": 123, "name": "...", "qty": 2, "retail_price": 25.00, "wholesale_price": 20.00}
  ]
}

Ако не можеш да идентифицираш артикул → не го включвай.
Разбирай грешен правопис, съкращения, диалект и синоними.
PROMPT;

$payload = [
    'model'      => CLAUDE_MODEL,
    'max_tokens' => 500,
    'system'     => $system_prompt,
    'messages'   => [
        ['role' => 'user', 'content' => 'Гласова команда: "' . $text . '"']
    ]
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . CLAUDE_API_KEY,
        'anthropic-version: 2023-06-01'
    ]
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$content = $result['content'][0]['text'] ?? '{}';

// Strip markdown if present
$content = preg_replace('/```json|```/', '', $content);
$parsed  = json_decode(trim($content), true);

echo json_encode($parsed ?? ['items' => []]);
