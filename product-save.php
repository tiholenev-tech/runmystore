<?php
/**
 * product-save.php — С18 FIX
 * Поддържа И JSON (от wizard), И form POST (backward compat)
 * Fix: DB::get()->lastInsertId(), DB::get()->beginTransaction()
 */
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['tenant_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$role      = $_SESSION['role'] ?? 'seller';

if (!in_array($role, ['owner', 'manager'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'no_permission']);
    exit;
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
               p.min_quantity AS min_qty
        FROM stores s
        LEFT JOIN inventory i ON i.store_id=s.id
            AND i.product_id=?
        LEFT JOIN products p ON p.id=?
        WHERE s.company_id = (SELECT company_id FROM stores WHERE id = ?)
          AND s.is_active=1
        ORDER BY s.name
    ", [(int)$_GET['stock'], (int)$_GET['stock'], $_SESSION['store_id'] ?? 0])->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── GET ?delete=id ────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    DB::run("UPDATE products SET is_active=0 WHERE (id=? OR parent_id=?) AND tenant_id=?",
        [$id, $id, $tenant_id]);
    DB::run("INSERT INTO audit_log (tenant_id,user_id,table_name,record_id,action,new_values) VALUES (?,?,?,?,?,?)",
        [$tenant_id, $user_id, 'products', $id, 'delete', json_encode(['soft'=>true])]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

// ── GET ?variants ─────────────────────────────────────────
if (isset($_GET['variants'])) {
    $t = DB::run("SELECT variants_config FROM tenants WHERE id=?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
    $config = json_decode($t['variants_config'] ?? '[]', true) ?: [];
    $active = array_values(array_filter($config, fn($v) => !empty($v['active'])));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($active, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── GET ?categories ───────────────────────────────────────
if (isset($_GET['categories'])) {
    $cats = DB::run(
        "SELECT id, name FROM categories WHERE tenant_id=? ORDER BY name",
        [$tenant_id]
    )->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($cats, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php'); exit;
}

// ── DETECT INPUT FORMAT: JSON or form POST ────────────────
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isJSON = (stripos($contentType, 'application/json') !== false);

if ($isJSON) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'invalid_json']);
        exit;
    }
} else {
    $data = $_POST;
}

header('Content-Type: application/json; charset=utf-8');


// ── Common fields ─────────────────────────────────────────
$action          = $data['action'] ?? 'create';
$id              = (int)($data['id'] ?? 0);
$name            = trim($data['name'] ?? '');
$code            = trim($data['code'] ?? '') ?: null;
$barcode         = trim($data['barcode'] ?? '') ?: null;
$category_id     = (int)($data['category_id'] ?? 0) ?: null;
$supplier_id     = (int)($data['supplier_id'] ?? 0) ?: null;
$cost_price      = (float)($data['cost_price'] ?? 0);
$retail_price    = (float)($data['retail_price'] ?? 0);
$wholesale_price = (float)($data['wholesale_price'] ?? 0);
$unit            = $data['unit'] ?? 'бр';
$min_quantity    = (int)($data['min_quantity'] ?? 0);
$location        = trim($data['location'] ?? '') ?: null;
$description     = trim($data['description'] ?? '') ?: null;
$origin_country  = trim($data['origin_country'] ?? '') ?: null;
$composition     = trim($data['composition'] ?? '') ?: null;
$is_domestic     = (int)($data['is_domestic'] ?? 0);
$color_single    = trim($data['color'] ?? '') ?: null;
$size_single     = trim($data['size'] ?? '') ?: null;

// From wizard JSON
$product_type    = $data['product_type'] ?? 'simple';
$sizes           = $data['sizes'] ?? [];
$colors          = $data['colors'] ?? [];
$variants        = $data['variants'] ?? [];

// Legacy form format
$has_variants    = !empty($data['has_variants']) && $data['has_variants'] === '1';
$variants_json   = json_decode($data['variants_batch'] ?? '[]', true) ?: [];
$variants_raw    = trim($data['variants_raw'] ?? '');

if ($name === '') {
    echo json_encode(['error' => 'Въведи наименование']); exit;
}

// VAT rate
$vat = DB::run(
    "SELECT v.standard_rate FROM tenants t
     LEFT JOIN vat_rates v ON v.country=t.country
     WHERE t.id=?",
    [$tenant_id]
)->fetchColumn();
$vat_rate = $vat ?: 20.00;

// Auto-generate code
if (!$code) {
    $words = preg_split('/\s+/', $name);
    $code = '';
    foreach ($words as $w) { $code .= mb_strtoupper(mb_substr($w, 0, 2)); }
    $code = substr($code, 0, 6) . '-' . rand(10,99);
    $exists = DB::run("SELECT id FROM products WHERE tenant_id=? AND code=?", [$tenant_id, $code])->fetch();
    if ($exists) $code .= rand(1,9);
}

// Auto-generate barcode for single product
$needBarcode = (!$barcode && $product_type === 'simple' && empty($sizes) && empty($colors) && !$has_variants && empty($variants_json) && empty($variants_raw));
if ($needBarcode) {
    $barcode = generateEAN13($tenant_id);
}

// ── EDIT ──────────────────────────────────────────────────
if ($action === 'edit' && $id > 0) {
    try {
        DB::run("
            UPDATE products SET
                name=?, code=?, barcode=?, category_id=?, supplier_id=?,
                cost_price=?, retail_price=?, wholesale_price=?, unit=?,
                min_quantity=?, location=?, description=?, size=?, color=?,
                vat_rate=?, origin_country=?, composition=?, is_domestic=?, updated_at=NOW()
            WHERE id=? AND tenant_id=?
        ", [$name, $code, $barcode, $category_id, $supplier_id,
            $cost_price, $retail_price, $wholesale_price, $unit,
            $min_quantity, $location, $description, $size_single, $color_single, $vat_rate,
            $origin_country, $composition, $is_domestic,
            $id, $tenant_id]);

        DB::run("INSERT INTO audit_log (tenant_id,user_id,table_name,record_id,action,new_values) VALUES (?,?,?,?,?,?)",
            [$tenant_id, $user_id, 'products', $id, 'edit', json_encode(['name'=>$name])]);

        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    } catch (Exception $e) {
        error_log('product-save edit error: ' . $e->getMessage());
        echo json_encode(['error' => 'Грешка при запис']); exit;
    }
}

// ── CREATE ────────────────────────────────────────────────
if ($action !== 'create') {
    echo json_encode(['error' => 'unknown_action']); exit;
}

try {
    $pdo = DB::get();
    $pdo->beginTransaction();

    $all_stores = DB::run(
        "SELECT id FROM stores WHERE company_id = (SELECT company_id FROM stores WHERE id = ?) AND is_active=1",
        [$_SESSION['store_id'] ?? 0]
    )->fetchAll(PDO::FETCH_ASSOC);

    // ── Determine if variants from wizard ──
    $hasWizardVariants = ($product_type === 'variant' && (!empty($sizes) || !empty($colors)));

    // ── SINGLE PRODUCT (no variants) ──
    if (!$hasWizardVariants && !$has_variants && empty($variants_json) && empty($variants_raw)) {
        $pid = insertProduct($tenant_id, null, $category_id, $supplier_id,
            $code, $name, $barcode, $unit, $cost_price, $retail_price,
            $wholesale_price, $vat_rate, $min_quantity, $location, $description,
            $size_single, $color_single, $origin_country, $composition, $is_domestic);

        // Initial inventory for each store
        foreach ($all_stores as $st) {
            DB::run("INSERT IGNORE INTO inventory (tenant_id, store_id, product_id, quantity) VALUES (?,?,?,0)",
                [$tenant_id, $st['id'], $pid]);
        }

        // Set initial qty if provided (for current store)
        $initQty = (int)($data['initial_qty'] ?? 0);
        if ($initQty > 0 && !empty($_SESSION['store_id'])) {
            DB::run("UPDATE inventory SET quantity=? WHERE tenant_id=? AND store_id=? AND product_id=?",
                [$initQty, $tenant_id, $_SESSION['store_id'], $pid]);
        }

        DB::run("INSERT INTO audit_log (tenant_id,user_id,table_name,record_id,action,new_values) VALUES (?,?,?,?,?,?)",
            [$tenant_id, $user_id, 'products', $pid, 'create', json_encode(['name'=>$name])]);

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $pid]);
        exit;
    }

    // ── VARIANT PRODUCT FROM WIZARD (sizes × colors) ──
    if ($hasWizardVariants) {
        // Parent product
        $pid = insertProduct($tenant_id, null, $category_id, $supplier_id,
            $code, $name, null, $unit, $cost_price, $retail_price,
            $wholesale_price, $vat_rate, $min_quantity, $location, $description, null, null, $origin_country, $composition, $is_domestic);

        DB::run("INSERT INTO audit_log (tenant_id,user_id,table_name,record_id,action,new_values) VALUES (?,?,?,?,?,?)",
            [$tenant_id, $user_id, 'products', $pid, 'create', json_encode(['name'=>$name,'type'=>'variant'])]);

        // Build variant combinations from wizard data
        $combos = [];
        if (!empty($variants) && is_array($variants)) {
            // Wizard sends pre-built variants array with {size, color, qty}
            $combos = $variants;
        } else if (!empty($sizes) && !empty($colors)) {
            foreach ($colors as $c) {
                foreach ($sizes as $s) {
                    $combos[] = ['size' => $s, 'color' => $c, 'qty' => 0];
                }
            }
        } else if (!empty($sizes)) {
            foreach ($sizes as $s) { $combos[] = ['size' => $s, 'color' => null, 'qty' => 0]; }
        } else {
            foreach ($colors as $c) { $combos[] = ['size' => null, 'color' => $c, 'qty' => 0]; }
        }

        foreach ($combos as $v) {
            $v_size  = trim($v['size'] ?? '') ?: null;
            $v_color = trim($v['color'] ?? '') ?: null;
            $v_qty   = (int)($v['qty'] ?? 0);
            $v_barcode = generateEAN13($tenant_id);

            $parts = array_filter([$v_size, $v_color]);
            $v_name = $name . (count($parts) ? ' / ' . implode(' / ', $parts) : '');
            $v_code = $code . '-' . strtoupper(substr(md5(($v_size??'') . ($v_color??'')), 0, 4));

            $cid = insertProduct($tenant_id, $pid, $category_id, $supplier_id,
                $v_code, $v_name, $v_barcode, $unit, $cost_price, $retail_price,
                $wholesale_price, $vat_rate, $min_quantity, $location, null, $v_size, $v_color, $origin_country, $composition, $is_domestic);

            foreach ($all_stores as $st) {
                $qty_to_set = ($st['id'] == ($_SESSION['store_id'] ?? 0)) ? $v_qty : 0;
                DB::run("INSERT INTO inventory (tenant_id, store_id, product_id, quantity) VALUES (?,?,?,?)
                         ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)",
                    [$tenant_id, $st['id'], $cid, $qty_to_set]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $pid, 'variants' => count($combos)]);
        exit;
    }

    // ── LEGACY: has_variants + variants_json ──
    if ($has_variants && !empty($variants_json)) {
        $pid = insertProduct($tenant_id, null, $category_id, $supplier_id,
            $code, $name, null, $unit, $cost_price, $retail_price,
            $wholesale_price, $vat_rate, $min_quantity, $location, $description, null, null, $origin_country, $composition, $is_domestic);

        foreach ($variants_json as $v) {
            $v_size    = trim($v['size'] ?? '') ?: null;
            $v_color   = trim($v['color'] ?? '') ?: null;
            $v_price   = (float)($v['price'] ?? $retail_price);
            $v_barcode = trim($v['barcode'] ?? '') ?: generateEAN13($tenant_id);

            $parts = array_filter([$v_size, $v_color]);
            $v_name = $name . (count($parts) ? ' / ' . implode(' / ', $parts) : '');
            $v_code = $code . '-' . strtoupper(substr(md5(($v_size??'') . ($v_color??'')), 0, 4));

            $cid = insertProduct($tenant_id, $pid, $category_id, $supplier_id,
                $v_code, $v_name, $v_barcode, $unit, $cost_price, $v_price,
                $wholesale_price, $vat_rate, $min_quantity, $location, null, $v_size, $v_color, $origin_country, $composition, $is_domestic);

            foreach ($all_stores as $st) {
                DB::run("INSERT IGNORE INTO inventory (tenant_id,store_id,product_id,quantity) VALUES (?,?,?,0)",
                    [$tenant_id, $st['id'], $cid]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $pid, 'variants' => count($variants_json)]);
        exit;
    }

    // ── LEGACY: variants_raw ──
    if (!empty($variants_raw)) {
        $pid = insertProduct($tenant_id, null, $category_id, $supplier_id,
            $code, $name, null, $unit, $cost_price, $retail_price,
            $wholesale_price, $vat_rate, $min_quantity, $location, $description, null, null, $origin_country, $composition, $is_domestic);

        $parsed = parseVariantsRaw($variants_raw);
        foreach ($parsed as $v) {
            $child_name = $name . ' / ' . $v['label'];
            $child_code = $code . '-' . strtoupper(substr(md5($v['label']), 0, 4));
            $child_bc   = generateEAN13($tenant_id);

            $cid = insertProduct($tenant_id, $pid, $category_id, $supplier_id,
                $child_code, $child_name, $child_bc, $unit, $cost_price, $retail_price,
                $wholesale_price, $vat_rate, $min_quantity, $location, null, null, null);

            foreach ($all_stores as $st) {
                DB::run("INSERT IGNORE INTO inventory (tenant_id,store_id,product_id,quantity) VALUES (?,?,?,0)",
                    [$tenant_id, $st['id'], $cid]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $pid, 'variants' => count($parsed)]);
        exit;
    }

    $pdo->rollBack();
    echo json_encode(['error' => 'Не можах да определя типа']);
    exit;

} catch (Exception $e) {
    try { DB::get()->rollBack(); } catch(Exception $e2) {}
    error_log('product-save error: ' . $e->getMessage());
    echo json_encode(['error' => 'Грешка: ' . $e->getMessage()]);
    exit;
}

// ── HELPERS ───────────────────────────────────────────────

function insertProduct(
    int $tenant_id, ?int $parent_id, ?int $category_id, ?int $supplier_id,
    string $code, string $name, ?string $barcode, string $unit,
    float $cost_price, float $retail_price, float $wholesale_price,
    float $vat_rate, int $min_quantity, ?string $location, ?string $description,
    ?string $size, ?string $color,
    ?string $p_origin_country = null, ?string $p_composition = null, int $p_is_domestic = 0
): int {
    DB::run("
        INSERT INTO products
        (tenant_id, parent_id, category_id, supplier_id, code, name, barcode,
         unit, cost_price, retail_price, wholesale_price, vat_rate,
         min_quantity, location, description, size, color, is_active,
         origin_country, composition, is_domestic)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?)
    ", [$tenant_id, $parent_id, $category_id, $supplier_id, $code, $name, $barcode,
        $unit, $cost_price, $retail_price, $wholesale_price, $vat_rate,
        $min_quantity, $location, $description, $size, $color,
        $p_origin_country, $p_composition, $p_is_domestic]);
    return (int)DB::get()->lastInsertId();
}

function generateEAN13(int $tenant_id): string {
    $base = str_pad($tenant_id, 3, '0', STR_PAD_LEFT) .
            str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
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
