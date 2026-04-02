<?php
// onboarding-save.php — записва онбординг данните в БД
session_start();
if (!isset($_SESSION['tenant_id'])) { http_response_code(401); exit; }

require_once 'config/database.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'];
$data = json_decode(file_get_contents('php://input'), true);

$biz        = trim($data['biz'] ?? '');
$segment    = trim($data['segment'] ?? '');
$stores     = trim($data['stores'] ?? '');
$products   = trim($data['products'] ?? '');
$employees  = trim($data['employees'] ?? '');
$freq       = trim($data['loyalty_freq'] ?? '');
$reward     = trim($data['loyalty_reward'] ?? '');
$competition= trim($data['loyalty_competition'] ?? '');

// Определяме брой магазини
$store_count = 1;
if (preg_match('/(\d+)/', $stores, $m)) $store_count = (int)$m[1];
elseif (preg_match('/два|две|2/i', $stores)) $store_count = 2;
elseif (preg_match('/три|3/i', $stores)) $store_count = 3;
elseif (preg_match('/четири|4/i', $stores)) $store_count = 4;
elseif (preg_match('/пет|5/i', $stores)) $store_count = 5;

// Wholesale ако бизнесът го изисква
$wholesale = (int)preg_match('/строит|авто|едро|wholesale/i', $biz);

// Perishable
$perishable = (int)preg_match('/хран|цвет|аптек|фарм/i', $biz);

// Tax group
$tax_group = preg_match('/аптек|фарм|книж|детск/i', $biz) ? 9 : 20;

// Business type string
$biz_full = $biz . ($segment ? ' — ' . $segment : '');

DB::run(
  "UPDATE tenants SET
    business_type=?,
    onboarding_done=1,
    wholesale_enabled=?,
    is_perishable=?,
    tax_group=?
   WHERE id=?",
  [$biz_full, $wholesale, $perishable, $tax_group, $tenant_id]
);

// Записваме AI памет за онбординга
$memories = [
  ['бизнес тип', $biz_full, 'onboarding'],
  ['брой магазини', $stores, 'onboarding'],
  ['брой артикули', $products, 'onboarding'],
  ['служители', $employees, 'onboarding'],
  ['лоялност честота', $freq, 'onboarding'],
  ['лоялност награда', $reward, 'onboarding'],
  ['конкуренция', $competition, 'onboarding'],
];

foreach ($memories as [$key, $val, $ctx]) {
  if (!$val) continue;
  DB::run(
    "INSERT INTO tenant_ai_memory (tenant_id, key_phrase, value, context) VALUES (?,?,?,?)",
    [$tenant_id, $key, $val, $ctx]
  );
}

$_SESSION['onboarding_done'] = 1;

echo json_encode(['ok' => true]);
