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
// S88B-1 / Q2: subcategory IS a category (categories.parent_id). When wizard sends both,
// subcategory wins — it's the leaf the user actually selected.
$subcategory_id  = (int)($data['subcategory_id'] ?? 0) ?: null;
if ($subcategory_id) {
    $category_id = $subcategory_id;
}
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

// S93.WIZARD.V4.SESSION_2: confidence_score + source_template_id + created_via.
// confidence_score: 40 = step1 save, 70 = step2, 95 = step3 (full data).
// Default 95 за backward compat (legacy 8-step wizard винаги пълна форма).
// Range: clamp [0..100].
$confidence_score    = max(0, min(100, (int)($data['confidence_score'] ?? 95)));
$source_template_id  = ((int)($data['source_template_id'] ?? 0)) ?: null;
$created_via         = (string)($data['created_via'] ?? 'wizard_legacy');
$_allowed_via        = ['wizard_v4','wizard_legacy','quick_add','import','api'];
if (!in_array($created_via, $_allowed_via, true)) { $created_via = 'wizard_legacy'; }
// Track дали code/barcode са user-provided (за да знаем дали да auto-gen post-INSERT).
$_user_provided_code    = ($code !== null);
$_user_provided_barcode = ($barcode !== null);

// Legacy form format
$has_variants    = !empty($data['has_variants']) && $data['has_variants'] === '1';
$variants_json   = json_decode($data['variants_batch'] ?? '[]', true) ?: [];
$variants_raw    = trim($data['variants_raw'] ?? '');

if ($name === '') {
    echo json_encode(['error' => 'Въведи наименование']); exit;
}

// ── S88.BUG#6: pre-INSERT duplicate guard (skipped if user already confirmed) ──
// Only on create (edit explicitly updates a known id). Compares against parent
// products of the same tenant. Skipped when confirm_duplicate flag is set.
$confirm_duplicate = !empty($data['confirm_duplicate']);
if (($action ?? 'create') === 'create' && !$confirm_duplicate) {
    $dup_matches = [];
    $dup_fields  = [];

    // Name match (case-insensitive, trimmed)
    $rows = DB::run(
        "SELECT id, name, code, barcode FROM products
         WHERE tenant_id=? AND parent_id IS NULL AND is_active=1
           AND LOWER(TRIM(name)) = LOWER(TRIM(?))
         LIMIT 5",
        [$tenant_id, $name]
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        $dup_fields[] = 'name';
        foreach ($rows as $r) { $r['by'] = 'name'; $dup_matches[$r['id']] = $r; }
    }

    // Code match (exact, only if user provided a code)
    $code_in = trim((string)($data['code'] ?? ''));
    if ($code_in !== '') {
        $rows = DB::run(
            "SELECT id, name, code, barcode FROM products
             WHERE tenant_id=? AND parent_id IS NULL AND is_active=1 AND code=?
             LIMIT 5",
            [$tenant_id, $code_in]
        )->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $dup_fields[] = 'code';
            foreach ($rows as $r) { $r['by'] = 'code'; $dup_matches[$r['id']] = $r; }
        }
    }

    // Barcode match (exact, only if user provided a non-empty barcode)
    $bc_in = trim((string)($data['barcode'] ?? ''));
    if ($bc_in !== '') {
        $rows = DB::run(
            "SELECT id, name, code, barcode FROM products
             WHERE tenant_id=? AND is_active=1 AND barcode=?
             LIMIT 5",
            [$tenant_id, $bc_in]
        )->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $dup_fields[] = 'barcode';
            foreach ($rows as $r) { $r['by'] = 'barcode'; $dup_matches[$r['id']] = $r; }
        }
    }

    if ($dup_matches) {
        echo json_encode([
            'duplicate' => true,
            'fields'    => array_values(array_unique($dup_fields)),
            'matches'   => array_values($dup_matches),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
// ── /S88.BUG#6 ──

// VAT rate
$vat = DB::run(
    "SELECT v.standard_rate FROM tenants t
     LEFT JOIN vat_rates v ON v.country=t.country
     WHERE t.id=?",
    [$tenant_id]
)->fetchColumn();
$vat_rate = $vat ?: 20.00;

// Auto-generate code (legacy fallback). Wizard_v4 пропуска това и ползва
// post-INSERT generateSKU за deterministic ENI-2026-NNNN format.
if (!$code && $created_via !== 'wizard_v4') {
    $words = preg_split('/\s+/', $name);
    $code = '';
    foreach ($words as $w) { $code .= mb_strtoupper(mb_substr($w, 0, 2)); }
    $code = substr($code, 0, 6) . '-' . rand(10,99);
    $exists = DB::run("SELECT id FROM products WHERE tenant_id=? AND code=?", [$tenant_id, $code])->fetch();
    if ($exists) $code .= rand(1,9);
}

// Auto-generate barcode for single product (legacy fallback). Wizard_v4 защо
// използва deterministic generateEAN13($tenant, $pid, $store) post-INSERT —
// тук просто оставяме barcode=null до подходящия момент.
$needBarcode = (!$barcode && $product_type === 'simple' && empty($sizes) && empty($colors) && !$has_variants && empty($variants_json) && empty($variants_raw));
if ($needBarcode && $created_via !== 'wizard_v4') {
    $barcode = generateEAN13($tenant_id);
}

// ── EDIT ──────────────────────────────────────────────────
if ($action === 'edit' && $id > 0) {
    try {
        // S88.BUG#7: snapshot OLD product for revert history.
        $old = DB::run(
            "SELECT name, code, barcode, category_id, supplier_id,
                    cost_price, retail_price, wholesale_price, unit,
                    min_quantity, location, description, size, color,
                    vat_rate, origin_country, composition, is_domestic
             FROM products WHERE id=? AND tenant_id=?",
            [$id, $tenant_id]
        )->fetch(PDO::FETCH_ASSOC) ?: [];

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

        // S88.BUG#7: write DEEP audit (old + new) so revert can restore. action='update' (enum).
        $new = [
            'name'=>$name, 'code'=>$code, 'barcode'=>$barcode,
            'category_id'=>$category_id, 'supplier_id'=>$supplier_id,
            'cost_price'=>$cost_price, 'retail_price'=>$retail_price,
            'wholesale_price'=>$wholesale_price, 'unit'=>$unit,
            'min_quantity'=>$min_quantity, 'location'=>$location,
            'description'=>$description, 'size'=>$size_single, 'color'=>$color_single,
            'vat_rate'=>$vat_rate, 'origin_country'=>$origin_country,
            'composition'=>$composition, 'is_domestic'=>$is_domestic,
        ];
        DB::run("INSERT INTO audit_log (tenant_id,user_id,table_name,record_id,action,old_values,new_values) VALUES (?,?,?,?,?,?,?)",
            [$tenant_id, $user_id, 'products', $id, 'update',
             json_encode($old, JSON_UNESCAPED_UNICODE),
             json_encode($new, JSON_UNESCAPED_UNICODE)]);

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

        // S93.WIZARD.V4.SESSION_2: post-INSERT update — confidence_score, created_via,
        // source_template_id, и (за wizard_v4) auto-gen barcode/SKU използвайки
        // deterministic formula с реален product_id.
        $store_id_for_codes = (int)($_SESSION['store_id'] ?? 0);
        $autogen = postInsertProductCodes(
            $pid, $tenant_id, $store_id_for_codes,
            $_user_provided_barcode ? $barcode : null,
            $_user_provided_code ? $code : null,
            $confidence_score, $created_via, $source_template_id
        );

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
        echo json_encode([
            'success' => true,
            'id' => $pid,
            'confidence_score' => $confidence_score,
            'autogen' => array_intersect_key($autogen, array_flip(['barcode','code'])),
        ]);
        exit;
    }

    // ── VARIANT PRODUCT FROM WIZARD (sizes × colors) ──
    if ($hasWizardVariants) {
        // Parent product
        $pid = insertProduct($tenant_id, null, $category_id, $supplier_id,
            $code, $name, null, $unit, $cost_price, $retail_price,
            $wholesale_price, $vat_rate, $min_quantity, $location, $description, null, null, $origin_country, $composition, $is_domestic);

        // S93.WIZARD.V4.SESSION_2: post-INSERT update parent — confidence_score,
        // created_via, source_template_id, и (за wizard_v4) auto-gen SKU за parent.
        // Parent няма барcode (NULL), деца ще получат deterministic — see foreach по-долу.
        $store_id_for_codes = (int)($_SESSION['store_id'] ?? 0);
        $parent_autogen = postInsertProductCodes(
            $pid, $tenant_id, $store_id_for_codes,
            null, // parent НЕ носи barcode (NULL винаги)
            $_user_provided_code ? $code : null,
            $confidence_score, $created_via, $source_template_id
        );
        // Ако wizard_v4 е генерирал нов SKU за parent → използваме него за деца.
        if (!empty($parent_autogen['code'])) { $code = $parent_autogen['code']; }

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

        $variant_ids_by_color = [];
        foreach ($combos as $v) {
            $v_size  = trim($v['size'] ?? '') ?: null;
            $v_color = trim($v['color'] ?? '') ?: null;
            $v_qty   = (int)($v['qty'] ?? 0);

            $parts = array_filter([$v_size, $v_color]);
            $v_name = $name . (count($parts) ? ' / ' . implode(' / ', $parts) : '');
            $v_code = $code . '-' . strtoupper(substr(md5(($v_size??'') . ($v_color??'')), 0, 4));

            // S93.WIZARD.V4.SESSION_2: insert with NULL barcode → post-INSERT generate
            // deterministic EAN-13 използвайки real $cid. Wizard_v4 only — legacy
            // получава random barcode pre-INSERT (старо поведение).
            $v_barcode = ($created_via === 'wizard_v4') ? null : generateEAN13($tenant_id);

            $cid = insertProduct($tenant_id, $pid, $category_id, $supplier_id,
                $v_code, $v_name, $v_barcode, $unit, $cost_price, $retail_price,
                $wholesale_price, $vat_rate, $min_quantity, $location, null, $v_size, $v_color, $origin_country, $composition, $is_domestic);

            // Post-INSERT: deterministic barcode + confidence_score за деца.
            postInsertProductCodes(
                $cid, $tenant_id, $store_id_for_codes,
                $v_barcode, // ако beше pre-genned, ще го запази; ако null → ще генерира
                $v_code,    // child code вече е derived; не auto-gen-ваме SKU
                $confidence_score, $created_via, $source_template_id
            );

            if ($v_color) {
                $key = mb_strtolower($v_color);
                if (!isset($variant_ids_by_color[$key])) $variant_ids_by_color[$key] = [];
                $variant_ids_by_color[$key][] = $cid;
            }

            foreach ($all_stores as $st) {
                $qty_to_set = ($st['id'] == ($_SESSION['store_id'] ?? 0)) ? $v_qty : 0;
                DB::run("INSERT INTO inventory (tenant_id, store_id, product_id, quantity) VALUES (?,?,?,?)
                         ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)",
                    [$tenant_id, $st['id'], $cid, $qty_to_set]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $pid, 'variants' => count($combos), 'variant_ids_by_color' => $variant_ids_by_color]);
        exit;
    }

    // ── LEGACY: has_variants + variants_json ──
    if ($has_variants && !empty($variants_json)) {
        $pid = insertProduct($tenant_id, null, $category_id, $supplier_id,
            $code, $name, null, $unit, $cost_price, $retail_price,
            $wholesale_price, $vat_rate, $min_quantity, $location, $description, null, null, $origin_country, $composition, $is_domestic);

        // S93.WIZARD.V4.SESSION_2: legacy flow също пише confidence + created_via,
        // за да не bevme схема default 'wizard_v4' върху не-wizard_v4 products.
        $store_id_for_codes = (int)($_SESSION['store_id'] ?? 0);
        postInsertProductCodes($pid, $tenant_id, $store_id_for_codes, null, $code, $confidence_score, $created_via, $source_template_id);

        $variant_ids_by_color = [];
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

            if ($v_color) {
                $key = mb_strtolower($v_color);
                if (!isset($variant_ids_by_color[$key])) $variant_ids_by_color[$key] = [];
                $variant_ids_by_color[$key][] = $cid;
            }

            foreach ($all_stores as $st) {
                DB::run("INSERT IGNORE INTO inventory (tenant_id,store_id,product_id,quantity) VALUES (?,?,?,0)",
                    [$tenant_id, $st['id'], $cid]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $pid, 'variants' => count($variants_json), 'variant_ids_by_color' => $variant_ids_by_color]);
        exit;
    }

    // ── LEGACY: variants_raw ──
    if (!empty($variants_raw)) {
        $pid = insertProduct($tenant_id, null, $category_id, $supplier_id,
            $code, $name, null, $unit, $cost_price, $retail_price,
            $wholesale_price, $vat_rate, $min_quantity, $location, $description, null, null, $origin_country, $composition, $is_domestic);

        // S93.WIZARD.V4.SESSION_2: legacy flow също пише confidence + created_via.
        $store_id_for_codes = (int)($_SESSION['store_id'] ?? 0);
        postInsertProductCodes($pid, $tenant_id, $store_id_for_codes, null, $code, $confidence_score, $created_via, $source_template_id);

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

/**
 * Generate EAN-13 barcode.
 *
 * S93.WIZARD.V4.SESSION_2: extended signature for deterministic generation per
 * SPEC §6: TT (2) + PPPPPPP (7) + CC (2) + D (1 checksum) = 13 digits.
 * - TT: tenant_id LPAD 2 (truncated last 2 if >2 digits)
 * - PPPPPPP: product_id LPAD 7 (truncated last 7 if >7 digits)
 * - CC: store_id LPAD 2 (default 00)
 * - D: EAN-13 checksum digit
 *
 * Backward compat: ако product_id=0 (legacy callers без id) — fallback към
 * старата формула (3-digit tenant + 9 random + checksum). Никога не
 * презаписваме existing barcodes — ensureProductCodes () проверява empty().
 */
function generateEAN13(int $tenant_id, int $product_id = 0, int $store_id = 0): string {
    if ($product_id === 0) {
        // Legacy fallback (random middle digits — non-traceable но deduplicates).
        $base = str_pad((string)$tenant_id, 3, '0', STR_PAD_LEFT) .
                str_pad((string)mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
    } else {
        $tt = str_pad((string)$tenant_id, 2, '0', STR_PAD_LEFT);
        if (strlen($tt) > 2) $tt = substr($tt, -2);
        $pp = str_pad((string)$product_id, 7, '0', STR_PAD_LEFT);
        if (strlen($pp) > 7) $pp = substr($pp, -7);
        $cc = str_pad((string)$store_id, 2, '0', STR_PAD_LEFT);
        if (strlen($cc) > 2) $cc = substr($cc, -2);
        $base = $tt . $pp . $cc;
    }
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$base[$i] * ($i % 2 === 0 ? 1 : 3);
    }
    $check = (10 - ($sum % 10)) % 10;
    return $base . $check;
}

/**
 * Generate tenant-specific SKU per SPEC §6.
 *
 * Format: {SHORT}-{YYYY}-{NNNN}
 * - SHORT: tenants.short_code (UPPER, fallback първи 3 chars от name).
 * - YYYY: година на създаване (current year).
 * - NNNN: tenant-specific брой products this year + 1, zero-padded 4 digits.
 *
 * Race condition: 2 паралелни save-а в същата секунда могат да получат същ NNNN.
 * Mitigation: проверяваме за collision и инкрементираме до уникален. Това е малка
 * window (~ms) — full sequence таблица е out of scope за S2.
 */
function generateSKU(int $tenant_id, int $product_id): string {
    $row = DB::run("SELECT short_code, name FROM tenants WHERE id=?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
    $short = '';
    if ($row) {
        $short = trim((string)($row['short_code'] ?? ''));
        if ($short === '') {
            $short = mb_strtoupper(mb_substr((string)($row['name'] ?? 'X'), 0, 3));
        }
    }
    if ($short === '') $short = 'X';
    $short = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $short) ?: 'X');
    $year = date('Y');

    $n = (int)DB::run(
        "SELECT COUNT(*) FROM products WHERE tenant_id=? AND YEAR(created_at)=?",
        [$tenant_id, $year]
    )->fetchColumn();

    // Probe-and-bump на минимална race window. Max 20 опита.
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $candidate = $short . '-' . $year . '-' . str_pad((string)($n + $attempt), 4, '0', STR_PAD_LEFT);
        $exists = DB::run("SELECT id FROM products WHERE tenant_id=? AND code=?", [$tenant_id, $candidate])->fetchColumn();
        if (!$exists) return $candidate;
    }
    // Fallback: append product_id за абсолютна уникалност.
    return $short . '-' . $year . '-' . str_pad((string)$product_id, 4, '0', STR_PAD_LEFT);
}

/**
 * Post-INSERT update: confidence_score, created_via, source_template_id,
 * + auto-gen barcode/SKU за wizard_v4 ако user не е предоставил.
 *
 * Никога не презаписва existing barcode/code на user-provided values.
 *
 * @return array Keys updated (за response payload).
 */
function postInsertProductCodes(
    int $product_id,
    int $tenant_id,
    int $store_id,
    ?string $current_barcode,
    ?string $current_code,
    int $confidence_score,
    string $created_via,
    ?int $source_template_id
): array {
    $updates = [
        'confidence_score' => $confidence_score,
        'created_via'      => $created_via,
    ];
    if ($source_template_id) {
        $updates['source_template_id'] = $source_template_id;
    }
    if ($created_via === 'wizard_v4') {
        if (empty($current_barcode)) {
            $updates['barcode'] = generateEAN13($tenant_id, $product_id, $store_id);
        }
        if (empty($current_code)) {
            $updates['code'] = generateSKU($tenant_id, $product_id);
        }
    }
    if ($updates) {
        $sets = [];
        $vals = [];
        foreach ($updates as $col => $val) {
            $sets[] = "`$col`=?";
            $vals[] = $val;
        }
        $vals[] = $product_id;
        $vals[] = $tenant_id;
        DB::run("UPDATE products SET " . implode(',', $sets) . " WHERE id=? AND tenant_id=?", $vals);
    }
    return $updates;
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
