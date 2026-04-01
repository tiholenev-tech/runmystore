<?php
session_start();
if (!isset($_SESSION['tenant_id'])) { echo json_encode([]); exit; }

require_once 'config/database.php';

$tenant_id = $_SESSION['tenant_id'];
$store_id  = $_SESSION['store_id'];
$q         = trim($_GET['q'] ?? '');
$barcode   = isset($_GET['barcode']);

if (empty($q)) { echo json_encode([]); exit; }

if ($barcode) {
    // Exact barcode/QR match
    $stmt = $pdo->prepare("
        SELECT p.id, p.code, p.name, p.retail_price, p.wholesale_price,
               COALESCE(i.quantity, 0) as stock,
               (SELECT COUNT(*) FROM stock_movements sm WHERE sm.product_id = p.id AND sm.tenant_id = p.tenant_id AND sm.type = 'out') as sold_count
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
        WHERE p.tenant_id = ? AND (p.barcode = ? OR p.alt_codes LIKE ?) AND p.is_active = 1
        LIMIT 5
    ");
    $stmt->execute([$store_id, $tenant_id, $q, '%' . $q . '%']);
} else {
    // Code prefix search + name search, sorted by sold_count DESC
    $stmt = $pdo->prepare("
        SELECT p.id, p.code, p.name, p.retail_price, p.wholesale_price,
               COALESCE(i.quantity, 0) as stock,
               (SELECT COUNT(*) FROM stock_movements sm WHERE sm.product_id = p.id AND sm.tenant_id = p.tenant_id AND sm.type = 'out') as sold_count
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
        WHERE p.tenant_id = ? AND p.is_active = 1
          AND (p.code LIKE ? OR p.name LIKE ? OR p.barcode LIKE ?)
        ORDER BY sold_count DESC
        LIMIT 10
    ");
    $like = $q . '%';
    $nameLike = '%' . $q . '%';
    $stmt->execute([$store_id, $tenant_id, $like, $nameLike, $like]);
}

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results);
