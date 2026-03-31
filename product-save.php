<?php
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['tenant_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {

    $name         = trim($_POST['name'] ?? '');
    $code         = trim($_POST['code'] ?? '') ?: null;
    $barcode      = trim($_POST['barcode'] ?? '') ?: null;
    $category_id  = (int)($_POST['category_id'] ?? 0) ?: null;
    $supplier_id  = (int)($_POST['supplier_id'] ?? 0) ?: null;
    $cost_price   = (float)($_POST['cost_price'] ?? 0);
    $retail_price = (float)($_POST['retail_price'] ?? 0);
    $unit         = $_POST['unit'] ?? 'бр';
    $location     = trim($_POST['location'] ?? '') ?: null;
    $variants_raw = trim($_POST['variants_raw'] ?? '');

    if ($name === '') {
        header('Location: products.php?error=1');
        exit;
    }

    // VAT rate от country на tenant-а
    $vat = DB::run(
        "SELECT v.standard_rate FROM tenants t LEFT JOIN vat_rates v ON v.country = t.country WHERE t.id = ?",
        [$tenant_id]
    )->fetchColumn();
    $vat_rate = $vat ?: 20.00;

    // Генерираме код ако няма
    if (!$code) {
        $code = 'ART-' . strtoupper(substr(md5($name . time()), 0, 6));
    }

    try {
        DB::beginTransaction();

        $has_variants = ($variants_raw !== '');

        // Вмъкваме parent артикул
        DB::run("
            INSERT INTO products 
            (tenant_id, parent_id, category_id, supplier_id, code, name, barcode, unit, cost_price, retail_price, vat_rate, location, is_active)
            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ", [$tenant_id, $category_id, $supplier_id, $code, $name, $barcode, $unit, $cost_price, $retail_price, $vat_rate, $location]);

        $parent_id = DB::lastInsertId();

        // Audit log
        DB::run(
            "INSERT INTO audit_log (tenant_id, user_id, table_name, record_id, action, new_values) VALUES (?,?,?,?,?,?)",
            [$tenant_id, $user_id, 'products', $parent_id, 'create', json_encode(['name' => $name])]
        );

        // Всички магазини на tenant-а
        $all_stores = DB::run("SELECT id FROM stores WHERE tenant_id = ? AND is_active = 1", [$tenant_id])->fetchAll();

        if ($has_variants) {
            $variants = parseVariants($variants_raw);
            foreach ($variants as $v) {
                $child_name = $name . ' / ' . $v['label'];
                $child_code = $code . '-' . strtoupper(substr(md5($v['label']), 0, 4));

                DB::run("
                    INSERT INTO products 
                    (tenant_id, parent_id, category_id, supplier_id, code, name, barcode, unit, cost_price, retail_price, vat_rate, location, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, 1)
                ", [$tenant_id, $parent_id, $category_id, $supplier_id, $child_code, $child_name, $unit, $cost_price, $retail_price, $vat_rate, $location]);

                $child_id = DB::lastInsertId();

                foreach ($all_stores as $st) {
                    DB::run(
                        "INSERT IGNORE INTO inventory (tenant_id, store_id, product_id, quantity) VALUES (?,?,?,0)",
                        [$tenant_id, $st['id'], $child_id]
                    );
                }
            }
        } else {
            foreach ($all_stores as $st) {
                DB::run(
                    "INSERT IGNORE INTO inventory (tenant_id, store_id, product_id, quantity) VALUES (?,?,?,0)",
                    [$tenant_id, $st['id'], $parent_id]
                );
            }
        }

        DB::commit();
        header('Location: products.php?saved=1');
        exit;

    } catch (Exception $e) {
        DB::rollback();
        header('Location: products.php?error=1');
        exit;
    }
}

header('Location: products.php');
exit;

// Парсва "Червена S-3, M-5" → масив от варианти
function parseVariants(string $raw): array {
    $variants = [];
    $parts = preg_split('/[,;\n]+/', $raw);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        if (preg_match('/^(.+)-(\d+)$/', $part, $m)) {
            $variants[] = ['label' => trim($m[1]), 'quantity' => (int)$m[2]];
        } else {
            $variants[] = ['label' => $part, 'quantity' => 0];
        }
    }
    return $variants;
}
