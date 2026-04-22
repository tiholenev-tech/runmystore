<?php
/**
 * S79.CHAT_INTEGRATION — mark-insight-shown.php
 * AJAX endpoint: записва действие на потребителя върху insight pill/signal.
 * Cooldown: 6h от момента на първи shown/tapped (NOT EXISTS в chat.php query).
 *
 * POST: topic_id, action (shown|tapped|dismissed|snoozed), category, product_id
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'err' => 'unauth']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'err' => 'method']);
    exit;
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)($_SESSION['store_id'] ?? 0);

$topic_id   = trim($_POST['topic_id'] ?? '');
$action     = $_POST['action'] ?? 'shown';
$category   = trim($_POST['category'] ?? '');
$product_id = (int)($_POST['product_id'] ?? 0);

if (!in_array($action, ['shown', 'tapped', 'dismissed', 'snoozed'], true)) {
    $action = 'shown';
}

if ($topic_id === '') {
    echo json_encode(['ok' => false, 'err' => 'no_topic']);
    exit;
}

try {
    DB::run(
        "INSERT INTO ai_shown (tenant_id, user_id, store_id, topic_id, category,
                               product_id, shown_at, action, action_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, NOW())",
        [$tenant_id, $user_id, $store_id, $topic_id,
         $category, $product_id, $action]
    );
    echo json_encode(['ok' => true, 'action' => $action]);
} catch (Exception $e) {
    error_log('mark-insight-shown: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'err' => 'db']);
}
