<?php
// onboarding-save.php
session_start();
if (!isset($_SESSION['tenant_id'])) { http_response_code(401); exit; }

require_once 'config/database.php';
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'];
$data = json_decode(file_get_contents('php://input'), true);

$biz = trim($data['biz'] ?? '');
$segment = trim($data['segment'] ?? '');
$stores = trim($data['stores'] ?? '');
$products = trim($data['products'] ?? '');
$employees = trim($data['employees'] ?? '');
$freq = trim($data['loyalty_freq'] ?? '');
$reward = trim($data['loyalty_reward'] ?? '');
$competition = trim($data['loyalty_competition'] ?? '');

// Логика за сегментиране
$store_count = 1;
if (preg_match('/(\d+)/', $stores, $m)) $store_count = (int)$m[1];
$wholesale = (int)preg_match('/строит|авто|едро|wholesale/i', $biz);
$perishable = (int)preg_match('/хран|цвет|аптек|фарм/i', $biz);
$tax_group = preg_match('/аптек|фарм|книж|детск/i', $biz) ? 9 : 20;

$biz_full = $biz . ($segment ? ' — ' . $segment : '');

// Обновяване на профила
DB::run(
"UPDATE tenants SET business_type=?, onboarding_done=1, wholesale_enabled=?, is_perishable=?, tax_group=? WHERE id=?",
[$biz_full, $wholesale, $perishable, $tax_group, $tenant_id]
);

// ГЕМИННИ ПАМЕТ: Записваме ги като ключови факти
$memories = [
['БИЗНЕС ТИП', $biz_full, 'core_fact'],
['МАГАЗИНИ БРОЙ', $store_count, 'core_fact'],
['ЛОЯЛНОСТ СТРАТЕГИЯ', "Честота: $freq, Награда: $reward", 'strategy']
];

foreach ($memories as [$key, $val, $ctx]) {
DB::run(
"INSERT INTO tenant_ai_memory (tenant_id, key_phrase, value, context) VALUES (?,?,?,?)",
[$tenant_id, $key, $val, $ctx]
);
}

$_SESSION['onboarding_done'] = 1;
echo json_encode(['ok' => true]);
