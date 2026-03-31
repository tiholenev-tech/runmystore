<?php
// store-chat-send.php
session_start();
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Не сте влезли']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$message     = trim($data['message'] ?? '');
$to_store_id = (int)($data['to_store_id'] ?? 0);

if (!$message || !$to_store_id) {
    echo json_encode(['ok' => false, 'error' => 'Липсват данни']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$store_id  = $_SESSION['store_id'];

// Проверка че to_store_id принадлежи на същия tenant
$check = DB::run('SELECT id FROM stores WHERE id = ? AND tenant_id = ?', [$to_store_id, $tenant_id])->fetch();
if (!$check) {
    echo json_encode(['ok' => false, 'error' => 'Невалиден обект']);
    exit;
}

DB::run(
    'INSERT INTO store_messages (tenant_id, from_store_id, from_user_id, to_store_id, message) VALUES (?, ?, ?, ?, ?)',
    [$tenant_id, $store_id, $user_id, $to_store_id, $message]
);

echo json_encode(['ok' => true]);
