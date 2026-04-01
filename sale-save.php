<?php
session_start();
if (!isset($_SESSION['tenant_id'])) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

require_once 'config/database.php';

$tenant_id = $_SESSION['tenant_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['items'])) {
    echo json_encode(['success'=>false,'error'=>'Няма артикули']);
    exit;
}

$store_id       = (int)($data['store_id'] ?? $_SESSION['store_id']);
$user_id        = (int)($data['user_id'] ?? $_SESSION['user_id']);
$payment_method = $data['payment_method'] ?? 'cash';
$total          = (float)($data['total'] ?? 0);
$global_disc    = (float)($data['global_discount'] ?? 0);
$client_id      = !empty($data['client_id']) ? (int)$data['client_id'] : null;
$supato_mode    = (int)($data['supato_mode'] ?? 0);
$deferred_date  = !empty($data['deferred_date']) ? $data['deferred_date'] : null;

try {
    $pdo->beginTransaction();

    // 1. Insert sale
    $stmt = $pdo->prepare("
        INSERT INTO sales (tenant_id, store_id, user_id, customer_id, total_amount, discount_amount, payment_method, payment_status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $disc_amount = 0;
    foreach ($data['items'] as $item) {
        $disc_amount += (float)$item['price'] * (int)$item['qty'] * ((float)($item['discount'] ?? 0) / 100);
    }
    $disc_amount += $total * ($global_disc / 100);

    $payment_status = ($payment_method === 'deferred') ? 'pending' : 'paid';
    $stmt->execute([$tenant_id, $store_id, $user_id, $client_id, $total, $disc_amount, $payment_method, $payment_status]);
    $sale_id = $pdo->lastInsertId();

    // 2. Insert sale items + stock movements
    foreach ($data['items'] as $item) {
        $product_id = (int)$item['id'];
        $qty        = (int)$item['qty'];
        $price      = (float)$item['price'];
        $disc       = (float)($item['discount'] ?? 0);
        $line_total = $price * $qty * (1 - $disc / 100);

        // sale_items
        $stmt2 = $pdo->prepare("
            INSERT INTO sale_items (tenant_id, sale_id, product_id, quantity, unit_price, discount_pct, line_total)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt2->execute([$tenant_id, $sale_id, $product_id, $qty, $price, $disc, $line_total]);

        // stock_movements — supato_mode: type='out', no price for BG
        $movement_type  = 'out';
        $movement_price = $supato_mode ? null : $price;
        $stmt3 = $pdo->prepare("
            INSERT INTO stock_movements (tenant_id, store_id, product_id, type, quantity, unit_price, reference_id, reference_type, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'sale', ?, NOW())
        ");
        $stmt3->execute([$tenant_id, $store_id, $product_id, $movement_type, $qty, $movement_price, $sale_id, $user_id]);

        // Update inventory
        $stmt4 = $pdo->prepare("
            UPDATE inventory SET quantity = quantity - ?
            WHERE tenant_id = ? AND store_id = ? AND product_id = ?
        ");
        $stmt4->execute([$qty, $tenant_id, $store_id, $product_id]);
    }

    // 3. Deferred payment date
    if ($deferred_date && $payment_method === 'deferred') {
        $pdo->prepare("UPDATE sales SET due_date = ? WHERE id = ?")->execute([$deferred_date, $sale_id]);
    }

    // 4. Audit log
    $pdo->prepare("
        INSERT INTO audit_log (tenant_id, user_id, action, entity_type, entity_id, created_at)
        VALUES (?, ?, 'create', 'sale', ?, NOW())
    ")->execute([$tenant_id, $user_id, $sale_id]);

    $pdo->commit();
    echo json_encode(['success'=>true, 'sale_id'=>$sale_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
