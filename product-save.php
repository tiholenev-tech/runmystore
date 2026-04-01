<?php
// product-save.php
// POST action=create → създава артикул + batch варианти (размери × цветове)
// POST action=edit   → редактира артикул
// GET  ?get=id       → JSON за редактиране
// GET  ?stock=id     → JSON наличности по обекти
// GET  ?delete=id    → soft delete (POST)
// GET  ?variants     → JSON активни варианти от onboarding на tenant-а
// GET  ?categories   → JSON категории на tenant-а

require_once 'config/database.php';
session_start();

if (!isset($_SESSION['tenant_id'])) {
    header('Location: login.php'); exit;
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$role      = $_SESSION['role'] ?? 'seller';

if (!in_array($role, ['owner', 'manager'])) {
    header('Location: products.php'); exit;
}

// ── GET ?get=id ───────────────────────────────────────────
if (isset($_GET['get'])) {
    $p = DB::run(
        "SELECT * FROM products WHERE id=? AND tenant_id=?",
        [(int)$_GET['get'], $tenant_id]
    )->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($p ?: [], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── GET ?stock=id — наличности по обекти ─────────────────
if (isset($_GET['stock'])) {
    $rows = DB::run("
        SELECT s.name, s.id AS store_id,
               COALESCE(i.quantity,0) AS qty,
               COALESCE(i.min_quantity,0) AS min_qty
        FROM stores s
        LEFT JOIN inventory i ON i.store_id=s.id
            AND i.product_id=? AND i.tenant_id=s.tenant_id
        WHERE s.tenant_id=? AND s.is_active=1
        ORDER BY s.name
    ", [(int)$_GET['stock'], $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── GET ?delete=id ────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Soft delete — parent + children
    DB::run("UPDATE products SET is_active=0 WHERE (id=? OR parent_id=?) AND tenant_id=?",
        [$id, $id, $tenant_id]);
    DB::run("INSERT INTO audit_log (tenant_id,user_id,table_name,record_id,action,new_values) VALUES (?,?,?,?,?,?)",
        [$tenant_id, $user_id, 'products', $id, 'delete', json_encode(['soft'=>true])]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

// ── GET ?variants — активни варианти от onboarding ───────
if (isset($_GET['variants'])) {
    $t = DB::run("SELECT variants_config FROM tenants WHERE id=?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
    $config = json_decode($t['variants_config'] ?? '[]', true) ?: [];
    // Само активните
    $active = array_values(array_filter($config, fn($v) => !empty($v['active'])));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($active, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── GET ?categories — категории на tenant-а ───────────────
if (isset($_GET['categories'])) {
    $cats = DB::run(
        "SELECT id, name, variant_type FROM categories WHERE tenant_id=? AND is_active=1 ORDER BY name",
        [$tenant_id]
    )->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($cats, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php'); exit;
}

$action = $_POST['action'] ?? 'create';

// ── Общи полета ───────────────────────────────────────────
$id              = (int)($_POST['id'] ?? 0);
$name            = trim($_POST['name'] ?? '');
$code            = trim($_POST['code'] ?? '') ?: null;
$barcode         = trim($_POST['barcode'] ?? '') ?: null;
$category_id     = (int)($_POST['category_id'] ?? 0) ?: null;
$supplier_id     = (int)($_POST['supplier_id'] ?? 0) ?: null;
$cost_price      = (float)($_POST['cost_price'] ?? 0);
$retail_price    = (float)($_POST['retail_price'] ?? 0);
$wholesale_price = (float)($_POST['wholesale_price'] ?? 0);
$unit            = $_POST['unit'] ?? 'бр';
$location        = trim($_POST['location'] ?? '') ?: null;
$description     = trim($_POST['description'] ?? '') ?: null;
$color_single    = trim($_POST['color'] ?? '') ?: null;
$size_single     = trim($_POST['size'] ?? '') ?: null;

// Batch варианти от новия UI
$has_variants    = !empty($_POST['has_variants']) && $_POST['has_variants'] === '1';
$variants_json   = json_decode($_POST['variants_batch'] ?? '[]', true) ?: [];
// Всеки елемент: {size, color, price, barcode}

// Стар формат (backward compat)
$variants_raw    = trim($_POST['variants_raw'] ?? '');

if ($name === '') {
    header('Location: products.php?error=1'); exit;
}

// VAT rate
$vat = DB::run(
    "SELECT v.standard_rate FROM tenants t
     LEFT JOIN vat_rates v ON v.country=t.country
     WHERE t.id=?",
    [$tenant_id]
)->fetchColumn();
$vat_rate = $vat ?: 20.00;

// Автогенериране на код
if (!$code) {
    $code = 'ART-' . strtoupper(substr(md5($name . microtime()), 0, 6));
}

// Автогенериране на баркод ако е единичен без баркод
if (!$barcode && !$has_variants && empty($variants_raw) && empty($variants_json)) {
    $barcode = generateEAN13($tenant_id);
}

// ── EDIT ──────────────────────────────────────────────────
if ($action === 'edit' && $id > 0) {
    try {
        DB::run("
            UPDATE products SET
                name=?, code=?, barcode=?, category_id=?, supplier_id=?,
                cost_price=?, retail_price=?, wholesale_price=?, unit=?,
                location=?, description=?, size=?, color=?, vat_rate=?,
                updated_at=NOW()
            WHERE id=? AND tenant_id=?
        ", [$name, $code, $barcode, $category_id, $supplier_id,
            $cost_price, $retail_price, $wholesale_price, $unit,
            $location, $description, $size_single, $color_single, $vat_rate,
            $id, $tenant_id]);

        DB::run("INSERT INTO audit_log (tenant_id,user_id,table_name,record_id,action,new_values) VALUES (?,?,?,?,?,?)",
            [$tenant_id, $user_id, 'products', $id, 'edit', json_encode(['name'=>$name])]);

        header('Location: products.php?saved=1'); exit;
    } catch (Exception $e) {
        header('Location: products.php?error=1'); exit;
    }
}

// ── CREATE ────────────────────────────────────────────────
if ($action !== 'create') {
    header('Location: products.php'); exit;
}

try {
    DB::beginTransaction();

    // Всички магазини
    $all_stores = DB::run(
        "SELECT id FROM stores WHERE tenant_id=? AND is_active=1",
        [$tenant_id]
    )->fetchAll(PDO::FETCH_ASSOC);

    // ── ЕДИНИЧЕН АРТИКУЛ ──
    if (!$has_variants && empty($variants_raw) && empty($variants_json)) {
        $pid = insertProduct($tenant_id, null, $category_id, $supplier_id,
            $code, $name, $barcode, $unit, $cost_price, $retail_price,
            $wholesale_price, $vat_rate, $location, $description,
            $size_single, $color_single);

        foreach ($all_stores as $st) {
            DB::run("INSERT IGNORE INTO inventory (tenant_id,store_id,product_id,quantity) VALUES (?,?,?,0)",
                [$tenant_id, $st['id'], $pid]);
        }

        DB::run("INSERT INTO audit_log (tenant_id,user_id,table_name,record_id,action,new_values) VALUES (?,?,?,?,?,?)",
            [$tenant_id, $user_id, 'products', $pid, 'create', json_encode(['name'=>$name])]);

        DB::commit();
        header('Location: products.php?saved=1'); exit;
    }

    // ── АРТИКУЛ С ВАРИАНТИ (нов batch формат) ──
    if ($has_variants && !empty($variants_json)) {
        // Parent артикул — без баркод, без размер/цвят
        $pid = insertProduct($tenant_id, null, $category_id, $supplier_id,
            $code, $name, null, $unit, $cost_price, $retail_price,
            $wholesale_price, $vat_rate, $location, $description, null, null);

        DB::run("INSERT INTO audit_log (tenant_id,user_id,table_name,record_id,action,new_values) VALUES (?,?,?,?,?,?)",
            [$tenant_id, $user_id, 'products', $pid, 'create', json_encode(['name'=>$name,'variants'=>count($variants_json)])]);

        foreach ($variants_json as $v) {
            $v_size    = trim($v['size'] ?? '') ?: null;
            $v_color   = trim($v['color'] ?? '') ?: null;
            $v_price   = (float)($v['price'] ?? $retail_price);
            $v_barcode = trim($v['barcode'] ?? '') ?: generateEAN13($tenant_id);

            // Наименование на варианта
            $parts = array_filter([$v_size, $v_color]);
            $v_name = $name . (count($parts) ? ' / ' . implode(' / ', $parts) : '');
            $v_code = $code . '-' . strtoupper(substr(md5(($v_size??'') . ($v_color??'')), 0, 4));

            $cid = insertProduct($tenant_id, $pid, $category_id, $supplier_id,
                $v_code, $v_name, $v_barcode, $unit, $cost_price, $v_price,
                $wholesale_price, $vat_rate, $location, null, $v_size, $v_color);

            foreach ($all_stores as $st) {
                DB::run("INSERT IGNORE INTO inventory (tenant_id,store_id,product_id,quantity) VALUES (?,?,?,0)",
                    [$tenant_id, $st['id'], $cid]);
            }
        }

        DB::commit();
        // Връщаме JSON с parent_id за serial scanner
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>true, 'parent_id'=>$pid, 'redirect'=>'products.php?saved=1'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── СТАР ФОРМАТ variants_raw (backward compat) ──
    if (!empty($variants_raw)) {
        $pid = insertProduct($tenant_id, null, $category_id, $supplier_id,
            $code, $name, null, $unit, $cost_price, $retail_price,
            $wholesale_price, $vat_rate, $location, $description, null, null);

        DB::run("INSERT INTO audit_log (tenant_id,user_id,table_name,record_id,action,new_values) VALUES (?,?,?,?,?,?)",
            [$tenant_id, $user_id, 'products', $pid, 'create', json_encode(['name'=>$name])]);

        $variants = parseVariantsRaw($variants_raw);
        foreach ($variants as $v) {
            $child_name = $name . ' / ' . $v['label'];
            $child_code = $code . '-' . strtoupper(substr(md5($v['label']), 0, 4));
            $child_bc   = generateEAN13($tenant_id);

            $cid = insertProduct($tenant_id, $pid, $category_id, $supplier_id,
                $child_code, $child_name, $child_bc, $unit, $cost_price, $retail_price,
                $wholesale_price, $vat_rate, $location, null, null, null);

            foreach ($all_stores as $st) {
                DB::run("INSERT IGNORE INTO inventory (tenant_id,store_id,product_id,quantity) VALUES (?,?,?,0)",
                    [$tenant_id, $st['id'], $cid]);
            }
        }

        DB::commit();
        header('Location: products.php?saved=1'); exit;
    }

    DB::rollback();
    header('Location: products.php?error=1'); exit;

} catch (Exception $e) {
    DB::rollback();
    error_log('product-save error: ' . $e->getMessage());
    header('Location: products.php?error=1'); exit;
}

// ── HELPERS ───────────────────────────────────────────────

function insertProduct(
    int $tenant_id, ?int $parent_id, ?int $category_id, ?int $supplier_id,
    string $code, string $name, ?string $barcode, string $unit,
    float $cost_price, float $retail_price, float $wholesale_price,
    float $vat_rate, ?string $location, ?string $description,
    ?string $size, ?string $color
): int {
    DB::run("
        INSERT INTO products
        (tenant_id, parent_id, category_id, supplier_id, code, name, barcode,
         unit, cost_price, retail_price, wholesale_price, vat_rate,
         location, description, size, color, is_active)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)
    ", [$tenant_id, $parent_id, $category_id, $supplier_id, $code, $name, $barcode,
        $unit, $cost_price, $retail_price, $wholesale_price, $vat_rate,
        $location, $description, $size, $color]);
    return (int)DB::lastInsertId();
}

function generateEAN13(int $tenant_id): string {
    // Генерира уникален EAN13-подобен баркод
    $base = str_pad($tenant_id, 3, '0', STR_PAD_LEFT) .
            str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
    // Check digit
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$base[$i] * ($i % 2 === 0 ? 1 : 3);
    }
    $check = (10 - ($sum % 10)) % 10;
    return $base . $check;
}

function parseVariantsRaw(string $raw): array {
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
