<?php
require_once 'config/database.php';
require_once 'config/config.php';
session_start();
if (!isset($_SESSION['tenant_id'])) { http_response_code(403); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$prompt = trim($input['prompt'] ?? '');
if (!$prompt) { echo json_encode(['text'=>'']); exit; }

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . CLAUDE_API_KEY,
        'anthropic-version: 2023-06-01'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'claude-sonnet-4-20250514',
        'max_tokens' => 200,
        'messages' => [['role'=>'user','content'=>$prompt]]
    ])
]);
$res = curl_exec($ch);
curl_close($ch);
$data = json_decode($res, true);
echo json_encode(['text' => $data['content'][0]['text'] ?? '']);
