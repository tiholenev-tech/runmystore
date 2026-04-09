<?php
/**
 * products.php — RunMyStore.ai
 * S43 FULL REWRITE — 09.04.2026
 * Fixes: detail drawer, goScreenWithHistory, openCSVImport, openLabels,
 *   openImageStudio, dup filePickerInput, ai-chat-overlay include,
 *   filterBySignal real logic, openQuickFilter drawers, extended products
 *   AJAX with signal+QF filters, 8-category signals, padding-bottom
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'config/database.php';
require_once 'config/config.php';

$pdo = DB::get();
$user_id    = $_SESSION['user_id'];
$tenant_id  = $_SESSION['tenant_id'];
$store_id   = $_SESSION['store_id'] ?? null;
$user_role  = $_SESSION['role'] ?? 'seller';
$user_name  = $_SESSION['name'] ?? '';

$is_owner   = ($user_role === 'owner');
$is_manager = ($user_role === 'manager');
$can_add    = ($is_owner || $is_manager);
$can_see_cost   = $is_owner;
$can_see_margin = $is_owner;

$tenant = DB::run("SELECT * FROM tenants WHERE id = ?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
$business_type = $tenant['business_type'] ?? '';
$currency = htmlspecialchars($tenant['currency'] ?? 'лв');
$lang = $tenant['lang'] ?? 'bg';
$skip_wholesale = (int)($tenant['skip_wholesale_price'] ?? 0);
$ai_bg = (int)($tenant['ai_credits_bg'] ?? 0);
$ai_tryon = (int)($tenant['ai_credits_tryon'] ?? 0);

$user = DB::run("SELECT * FROM users WHERE id = ?", [$user_id])->fetch(PDO::FETCH_ASSOC);
$stores = DB::run("SELECT s.id, s.name FROM stores s WHERE s.company_id = (SELECT company_id FROM stores WHERE id = ?) ORDER BY s.name", [$store_id])->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// AJAX ENDPOINTS
// ============================================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ajax = $_GET['ajax'];

    // ─── SEARCH ───
    if ($ajax === 'search') {
        $q = trim($_GET['q'] ?? '');
        $sid = (int)($_GET['store_id'] ?? $store_id);
        if (strlen($q) < 1) { echo json_encode([]); exit; }
        $like = "%{$q}%";
        $rows = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.cost_price, p.image_url, p.supplier_id,
                   s.name AS supplier_name, c.name AS category_name,
                   COALESCE(SUM(i.quantity), 0) AS total_stock
            FROM products p
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND p.is_active = 1
              AND (p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)
            GROUP BY p.id ORDER BY p.name LIMIT 30
        ", [$sid, $tenant_id, $like, $like, $like])->fetchAll(PDO::FETCH_ASSOC);
        if (!$can_see_cost) { foreach ($rows as &$r) unset($r['cost_price']); }
        echo json_encode($rows); exit;
    }

    // ─── BARCODE ───
    if ($ajax === 'barcode') {
        $code = trim($_GET['code'] ?? '');
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $row = DB::run("
            SELECT p.id, p.name, p.code, p.barcode, p.retail_price, p.cost_price, p.image_url,
                   p.supplier_id, p.category_id, p.parent_id, p.description, p.unit,
                   p.discount_pct, p.discount_ends_at, p.min_quantity,
                   s.name AS supplier_name, c.name AS category_name,
                   COALESCE(inv.quantity, 0) AS store_stock
            FROM products p
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory inv ON inv.product_id = p.id AND inv.store_id = ?
            WHERE p.tenant_id = ? AND (p.barcode = ? OR p.code = ?)
            LIMIT 1
        ", [$sid, $tenant_id, $code, $code])->fetch(PDO::FETCH_ASSOC);
        if ($row && !$can_see_cost) unset($row['cost_price']);
        echo json_encode($row ?: ['error' => 'not_found']); exit;
    }

    // ─── PRODUCT DETAIL ───
    if ($ajax === 'product_detail') {
        $pid = (int)($_GET['id'] ?? 0);
        $product = DB::run("
            SELECT p.*, s.name AS supplier_name, c.name AS category_name
            FROM products p LEFT JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id = ? AND p.tenant_id = ?
        ", [$pid, $tenant_id])->fetch(PDO::FETCH_ASSOC);
        if (!$product) { echo json_encode(['error' => 'not_found']); exit; }
        if (!$can_see_cost) unset($product['cost_price']);
        $stocks = DB::run("
            SELECT st.id AS store_id, st.name AS store_name, COALESCE(i.quantity, 0) AS qty
            FROM stores st LEFT JOIN inventory i ON i.store_id = st.id AND i.product_id = ?
            WHERE st.company_id = (SELECT company_id FROM stores WHERE id = ?) ORDER BY st.name
        ", [$pid, $store_id])->fetchAll(PDO::FETCH_ASSOC);
        $variations = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.barcode, p.size, p.color,
                   COALESCE(SUM(i.quantity), 0) AS total_stock
            FROM products p LEFT JOIN inventory i ON i.product_id = p.id
            WHERE p.parent_id = ? AND p.tenant_id = ? AND p.is_active = 1
            GROUP BY p.id ORDER BY p.name
        ", [$pid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        $price_history = DB::run("
            SELECT old_price, new_price, changed_at FROM price_history WHERE product_id = ? ORDER BY changed_at DESC LIMIT 10
        ", [$pid])->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['product'=>$product,'stocks'=>$stocks,'variations'=>$variations,'price_history'=>$price_history]);
        exit;
    }

    // ─── HOME STATS ───
    if ($ajax === 'home_stats') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $capital = DB::run("SELECT COALESCE(SUM(i.quantity * p.retail_price), 0) AS retail_value, COALESCE(SUM(i.quantity * p.cost_price), 0) AS cost_value FROM inventory i JOIN products p ON p.id = i.product_id WHERE p.tenant_id = ? AND i.store_id = ? AND i.quantity > 0", [$tenant_id, $sid])->fetch(PDO::FETCH_ASSOC);
        $avg_margin = 0;
        if ($can_see_margin && $capital['cost_value'] > 0) {
            $avg_margin = round((($capital['retail_value'] - $capital['cost_value']) / $capital['retail_value']) * 100, 1);
        }
        $zombies = DB::run("SELECT p.id, p.name, p.code, p.retail_price, p.image_url, COALESCE(i.quantity,0) AS qty, DATEDIFF(NOW(), COALESCE((SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=p.id), p.created_at)) AS days_stale FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity>0 AND p.parent_id IS NULL HAVING days_stale>45 ORDER BY days_stale DESC LIMIT 10", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        $low_stock = DB::run("SELECT p.id, p.name, p.code, p.retail_price, p.image_url, p.min_quantity, COALESCE(i.quantity,0) AS qty FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND p.min_quantity>0 AND i.quantity<=p.min_quantity AND i.quantity>0 ORDER BY (i.quantity/p.min_quantity) ASC LIMIT 10", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        $out_of_stock = DB::run("SELECT p.id, p.name, p.code, p.retail_price, p.image_url FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity=0 AND p.parent_id IS NULL ORDER BY p.name LIMIT 20", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        $top_sellers = DB::run("SELECT p.id, p.name, p.code, p.retail_price, p.image_url, SUM(si.quantity) AS sold_qty, SUM(si.quantity*si.unit_price) AS revenue FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id WHERE p.tenant_id=? AND s.store_id=? AND s.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND s.status='completed' GROUP BY p.id ORDER BY sold_qty DESC LIMIT 5", [$tenant_id, $sid])->fetchAll(PDO::FETCH_ASSOC);
        $slow_movers = DB::run("SELECT p.id, p.name, p.code, p.retail_price, p.image_url, COALESCE(i.quantity,0) AS qty, DATEDIFF(NOW(), COALESCE((SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=p.id), p.created_at)) AS days_stale FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity>0 AND p.parent_id IS NULL HAVING days_stale BETWEEN 25 AND 45 ORDER BY days_stale DESC LIMIT 10", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        $counts = DB::run("SELECT COUNT(DISTINCT p.id) AS total_products, COALESCE(SUM(i.quantity),0) AS total_units FROM products p LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL", [$sid, $tenant_id])->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['capital'=>$can_see_margin?round($capital['retail_value'],2):null,'avg_margin'=>$can_see_margin?$avg_margin:null,'zombies'=>$zombies,'low_stock'=>$low_stock,'out_of_stock'=>$out_of_stock,'top_sellers'=>$top_sellers,'slow_movers'=>$slow_movers,'counts'=>$counts]);
        exit;
    }

    // ─── SUPPLIERS ───
    if ($ajax === 'suppliers') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $suppliers = DB::run("SELECT s.id, s.name, s.phone, s.email, COUNT(DISTINCT p.id) AS product_count, COALESCE(SUM(i.quantity),0) AS total_stock, SUM(CASE WHEN i.quantity=0 THEN 1 ELSE 0 END) AS out_count, SUM(CASE WHEN i.quantity>0 AND i.quantity<=p.min_quantity AND p.min_quantity>0 THEN 1 ELSE 0 END) AS low_count FROM suppliers s JOIN products p ON p.supplier_id=s.id AND p.tenant_id=? AND p.is_active=1 LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE s.tenant_id=? GROUP BY s.id ORDER BY s.name", [$tenant_id, $sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($suppliers); exit;
    }

    // ─── CATEGORIES ───
    if ($ajax === 'categories') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $sup = isset($_GET['sup']) ? (int)$_GET['sup'] : null;
        if ($sup) {
            $categories = DB::run("SELECT c.id, c.name, c.parent_id FROM categories c JOIN supplier_categories sc ON sc.category_id=c.id AND sc.supplier_id=? AND sc.tenant_id=? WHERE c.tenant_id=? ORDER BY c.name", [$sup, $tenant_id, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $categories = DB::run("SELECT c.id, c.name, c.parent_id, COUNT(DISTINCT p.id) AS product_count, COUNT(DISTINCT p.supplier_id) AS supplier_count, COALESCE(SUM(i.quantity),0) AS total_stock FROM categories c JOIN products p ON p.category_id=c.id AND p.tenant_id=? AND p.is_active=1 LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE c.tenant_id=? GROUP BY c.id ORDER BY c.name", [$tenant_id, $sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($categories); exit;
    }

    // ─── SUBCATEGORIES ───
    if ($ajax === 'subcategories') {
        $parent_id = (int)($_GET['parent_id'] ?? 0);
        echo json_encode(DB::run("SELECT id, name FROM categories WHERE tenant_id=? AND parent_id=? ORDER BY name", [$tenant_id, $parent_id])->fetchAll(PDO::FETCH_ASSOC)); exit;
    }

    // ─── SUPPLIER CATEGORIES (get/save) ───
    if ($ajax === 'get_supplier_categories') {
        $sup_id = (int)($_GET['supplier_id'] ?? 0);
        echo json_encode(DB::run("SELECT category_id FROM supplier_categories WHERE tenant_id=? AND supplier_id=?", [$tenant_id, $sup_id])->fetchAll(PDO::FETCH_COLUMN)); exit;
    }
    if ($ajax === 'save_supplier_categories') {
        $input = json_decode(file_get_contents('php://input'), true);
        $sup_id = (int)($input['supplier_id'] ?? 0);
        $cat_ids = $input['category_ids'] ?? [];
        if (!$sup_id) { echo json_encode(['error'=>'No supplier']); exit; }
        DB::run("DELETE FROM supplier_categories WHERE tenant_id=? AND supplier_id=?", [$tenant_id, $sup_id]);
        foreach ($cat_ids as $cid) { $cid=(int)$cid; if($cid>0) DB::run("INSERT IGNORE INTO supplier_categories (tenant_id,supplier_id,category_id) VALUES (?,?,?)", [$tenant_id,$sup_id,$cid]); }
        echo json_encode(['ok'=>true,'count'=>count($cat_ids)]); exit;
    }

    // ─── CATEGORY GROUPS (from JSON) ───
    if ($ajax === 'category_groups') {
        $jsonFile = __DIR__ . '/category-groups.json';
        if (!file_exists($jsonFile)) { echo json_encode([]); exit; }
        $all = json_decode(file_get_contents($jsonFile), true);
        $bt = $_GET['business_type'] ?? $business_type;
        foreach ($all as $item) { if ($item['business_type'] === $bt) { echo json_encode($item['category_groups']); exit; } }
        echo json_encode([]); exit;
    }

    // ─── PRODUCTS LIST (S43: extended with signal + quick filters) ───
    if ($ajax === 'products') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $sup = isset($_GET['sup']) ? (int)$_GET['sup'] : null;
        $cat = isset($_GET['cat']) ? (int)$_GET['cat'] : null;
        $flt = $_GET['filter'] ?? 'all';
        $sort = $_GET['sort'] ?? 'name';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 30; $offset = ($page-1)*$per_page;
        $where = ["p.tenant_id = ?","p.is_active = 1","p.parent_id IS NULL"]; $params = [$tenant_id];
        $needHaving = false; $havingClauses = [];
        if ($sup) { $where[] = "p.supplier_id = ?"; $params[] = $sup; }
        if ($cat) { $where[] = "p.category_id = ?"; $params[] = $cat; }
        if ($flt==='low') { $where[] = "i.quantity > 0 AND i.quantity <= p.min_quantity AND p.min_quantity > 0"; }
        elseif ($flt==='out') { $where[] = "(i.quantity = 0 OR i.quantity IS NULL)"; }
        elseif ($flt==='zombie') { $where[] = "i.quantity > 0"; $needHaving=true; $havingClauses[] = "days_stale > 45"; }
        elseif ($flt==='aging') { $where[] = "i.quantity > 0"; $needHaving=true; $havingClauses[] = "days_stale > 90"; }
        elseif ($flt==='slow_mover') { $where[] = "i.quantity > 0"; $needHaving=true; $havingClauses[] = "days_stale BETWEEN 25 AND 45"; }
        elseif ($flt==='at_loss') { $where[] = "p.cost_price > 0 AND p.retail_price < p.cost_price"; }
        elseif ($flt==='low_margin') { $where[] = "p.cost_price > 0 AND p.retail_price > p.cost_price AND ((p.retail_price-p.cost_price)/p.retail_price*100) < 15"; }
        elseif ($flt==='critical_low') { $where[] = "i.quantity BETWEEN 1 AND 2"; }
        elseif ($flt==='zero_stock') { $where[] = "i.quantity = 0"; $where[] = "p.id IN (SELECT si2.product_id FROM sale_items si2 JOIN sales s2 ON s2.id=si2.sale_id WHERE s2.store_id=? AND s2.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND s2.status='completed')"; $params[] = $sid; }
        elseif ($flt==='below_min') { $where[] = "p.min_quantity > 0 AND i.quantity > 0 AND i.quantity <= p.min_quantity"; }
        elseif ($flt==='top_sales') { $where[] = "p.id IN (SELECT si3.product_id FROM sale_items si3 JOIN sales s3 ON s3.id=si3.sale_id WHERE s3.store_id=? AND s3.status='completed' AND s3.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY si3.product_id ORDER BY SUM(si3.quantity) DESC LIMIT 10)"; $params[] = $sid; }
        elseif ($flt==='top_profit') { $where[] = "p.cost_price > 0"; $sort='margin_desc'; }
        elseif ($flt==='no_photo') { $where[] = "(p.image_url IS NULL OR p.image_url='')"; }
        elseif ($flt==='no_barcode') { $where[] = "(p.barcode IS NULL OR p.barcode='')"; }
        elseif ($flt==='no_supplier') { $where[] = "p.supplier_id IS NULL"; }
        elseif ($flt==='no_cost') { $where[] = "(p.cost_price IS NULL OR p.cost_price=0)"; }
        elseif ($flt==='new_week') { $where[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; }
        // Quick filter params
        if (isset($_GET['price_min']) && $_GET['price_min']!=='') { $where[] = "p.retail_price >= ?"; $params[] = (float)$_GET['price_min']; }
        if (isset($_GET['price_max']) && $_GET['price_max']!=='') { $where[] = "p.retail_price <= ?"; $params[] = (float)$_GET['price_max']; }
        if (isset($_GET['stock_min']) && $_GET['stock_min']!=='') { $where[] = "COALESCE(i.quantity,0) >= ?"; $params[] = (int)$_GET['stock_min']; }
        if (isset($_GET['stock_max']) && $_GET['stock_max']!=='') { $where[] = "COALESCE(i.quantity,0) <= ?"; $params[] = (int)$_GET['stock_max']; }
        if (isset($_GET['margin_min']) && $_GET['margin_min']!=='' && $can_see_margin) { $where[] = "p.cost_price>0 AND ((p.retail_price-p.cost_price)/p.retail_price*100) >= ?"; $params[] = (float)$_GET['margin_min']; }
        if (isset($_GET['margin_max']) && $_GET['margin_max']!=='' && $can_see_margin) { $where[] = "p.cost_price>0 AND ((p.retail_price-p.cost_price)/p.retail_price*100) <= ?"; $params[] = (float)$_GET['margin_max']; }
        if (isset($_GET['date_from']) && $_GET['date_from']!=='') { $where[] = "p.created_at >= ?"; $params[] = $_GET['date_from']; }
        if (isset($_GET['date_to']) && $_GET['date_to']!=='') { $where[] = "p.created_at <= ?"; $params[] = $_GET['date_to'].' 23:59:59'; }

        $where_sql = implode(' AND ', $where);
        $order = match($sort) { 'price_asc'=>'p.retail_price ASC','price_desc'=>'p.retail_price DESC','stock_asc'=>'store_stock ASC','stock_desc'=>'store_stock DESC','newest'=>'p.created_at DESC','margin_desc'=>'((p.retail_price-p.cost_price)/p.retail_price) DESC', default=>'p.name ASC' };
        $dse = "DATEDIFF(NOW(), COALESCE((SELECT MAX(s99.created_at) FROM sale_items si99 JOIN sales s99 ON s99.id=si99.sale_id WHERE si99.product_id=p.id AND s99.store_id={$sid}), p.created_at))";

        if ($needHaving) {
            $hSQL = implode(' AND ', str_replace('days_stale', $dse, $havingClauses));
            $products = DB::run("SELECT p.id,p.name,p.code,p.barcode,p.retail_price,p.cost_price,p.image_url,p.supplier_id,p.category_id,p.parent_id,p.discount_pct,p.discount_ends_at,p.min_quantity,p.unit,s.name AS supplier_name,c.name AS category_name,COALESCE(i.quantity,0) AS store_stock,{$dse} AS days_stale FROM products p LEFT JOIN suppliers s ON s.id=p.supplier_id LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE {$where_sql} HAVING {$hSQL} ORDER BY {$order} LIMIT ? OFFSET ?", array_merge([$sid], $params, [$per_page, $offset]))->fetchAll(PDO::FETCH_ASSOC);
            $total = DB::run("SELECT COUNT(*) FROM (SELECT p.id,{$dse} AS days_stale FROM products p LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE {$where_sql} HAVING {$hSQL}) sub", array_merge([$sid], $params))->fetchColumn();
        } else {
            $products = DB::run("SELECT p.id,p.name,p.code,p.barcode,p.retail_price,p.cost_price,p.image_url,p.supplier_id,p.category_id,p.parent_id,p.discount_pct,p.discount_ends_at,p.min_quantity,p.unit,s.name AS supplier_name,c.name AS category_name,COALESCE(i.quantity,0) AS store_stock FROM products p LEFT JOIN suppliers s ON s.id=p.supplier_id LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE {$where_sql} ORDER BY {$order} LIMIT ? OFFSET ?", array_merge([$sid], $params, [$per_page, $offset]))->fetchAll(PDO::FETCH_ASSOC);
            $total = DB::run("SELECT COUNT(DISTINCT p.id) FROM products p LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE {$where_sql}", array_merge([$sid], $params))->fetchColumn();
        }
        if (!$can_see_cost) { foreach ($products as &$pr) unset($pr['cost_price']); }
        echo json_encode(['products'=>$products,'total'=>(int)$total,'page'=>$page,'pages'=>max(1,ceil($total/$per_page))]);
        exit;
    }

    // ─── AI ANALYZE ───
    if ($ajax === 'ai_analyze') {
        $pid = (int)($_GET['id'] ?? 0);
        $product = DB::run("SELECT * FROM products WHERE id=? AND tenant_id=?", [$pid, $tenant_id])->fetch(PDO::FETCH_ASSOC);
        if (!$product) { echo json_encode(['error'=>'not_found']); exit; }
        $sales_30d = DB::run("SELECT COALESCE(SUM(si.quantity),0) AS qty, COALESCE(SUM(si.quantity*si.unit_price),0) AS revenue FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=? AND s.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)", [$pid])->fetch(PDO::FETCH_ASSOC);
        $stock = DB::run("SELECT COALESCE(SUM(quantity),0) FROM inventory WHERE product_id=?", [$pid])->fetchColumn();
        $days_supply = ($sales_30d['qty']>0) ? round(($stock/($sales_30d['qty']/30)),0) : 999;
        $analysis = [];
        if ($days_supply>90 && $stock>0) $analysis[] = ['type'=>'zombie','icon'=>'💀','text'=>"Стока за {$days_supply} дни. Намали с 30% или пакет.",'severity'=>'high'];
        elseif ($days_supply>45 && $stock>0) $analysis[] = ['type'=>'slow','icon'=>'🐌','text'=>"Бавно движеща се — {$days_supply} дни запас.",'severity'=>'medium'];
        if ($stock<=$product['min_quantity'] && $stock>0 && $product['min_quantity']>0) $analysis[] = ['type'=>'low','icon'=>'⚠️','text'=>"Остават {$stock} бр. (мин. {$product['min_quantity']}). Поръчай!",'severity'=>'high'];
        elseif ($stock==0) $analysis[] = ['type'=>'out','icon'=>'🔴','text'=>"ИЗЧЕРПАН! Губиш продажби.",'severity'=>'critical'];
        if ($can_see_margin && $product['cost_price']>0) { $margin=round((($product['retail_price']-$product['cost_price'])/$product['retail_price'])*100,1); if($margin<20) $analysis[]=['type'=>'margin','icon'=>'💰','text'=>"Марж само {$margin}%.",'severity'=>'medium']; }
        if ($sales_30d['qty']>0) $analysis[] = ['type'=>'sales','icon'=>'📊','text'=>"30 дни: {$sales_30d['qty']} бр. / ".number_format($sales_30d['revenue'],2,',','.')." {$currency}",'severity'=>'info'];
        echo json_encode(['analysis'=>$analysis,'days_supply'=>$days_supply,'sales_30d'=>$sales_30d]); exit;
    }

    // ─── AI CREDITS ───
    if ($ajax === 'ai_credits') {
        echo json_encode(DB::run("SELECT ai_credits_bg, ai_credits_tryon FROM tenants WHERE id=?", [$tenant_id])->fetch(PDO::FETCH_ASSOC)); exit;
    }

    // ─── AI SCAN (Gemini Vision) ───
    if ($ajax === 'ai_scan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $image_data = $input['image'] ?? '';
        if (!$image_data) { echo json_encode(['error'=>'no_image']); exit; }
        $cats = DB::run("SELECT name FROM categories WHERE tenant_id=?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $sups = DB::run("SELECT name FROM suppliers WHERE tenant_id=?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $prompt = "Анализирай тази снимка на продукт. Върни САМО JSON без markdown:\n{\"name\":\"\",\"retail_price\":0,\"category\":\"\",\"supplier\":\"\",\"sizes\":[],\"colors\":[],\"code\":\"\",\"description\":\"\",\"unit\":\"бр\"}\nКатегории: ".implode(', ',$cats)."\nДоставчици: ".implode(', ',$sups)."\nНЕ измисляй цени. description = SEO. code = 6-8 символа. Само JSON.";
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/'.GEMINI_MODEL.':generateContent?key='.GEMINI_API_KEY;
        $payload = ['contents'=>[['parts'=>[['inlineData'=>['mimeType'=>'image/jpeg','data'=>$image_data]],['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.3,'maxOutputTokens'=>500]];
        $ch = curl_init($api_url); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
        $resp = curl_exec($ch); curl_close($ch);
        $data = json_decode($resp, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = preg_replace('/```json\s*|\s*```/', '', $text);
        echo json_encode(json_decode($text, true) ?: ['error'=>'parse_failed']); exit;
    }

    // ─── AI DESCRIPTION ───
    if ($ajax === 'ai_description' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? '';
        if (!$name) { echo json_encode(['error'=>'no_name']); exit; }
        $prompt = "Напиши кратко SEO описание (2-3 изречения) за: \"{$name}\".";
        if (!empty($input['category'])) $prompt .= " Категория: {$input['category']}.";
        if (!empty($input['supplier'])) $prompt .= " Марка: {$input['supplier']}.";
        $prompt .= " Подходящо за Google и e-commerce. Само описанието.";
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/'.GEMINI_MODEL.':generateContent?key='.GEMINI_API_KEY;
        $payload = ['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.5,'maxOutputTokens'=>200]];
        $ch = curl_init($api_url); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
        $resp = curl_exec($ch); curl_close($ch);
        $data = json_decode($resp, true);
        echo json_encode(['description'=>trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '')]); exit;
    }

    // ─── AI CODE ───
    if ($ajax === 'ai_code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? '';
        if (!$name) { echo json_encode(['code'=>strtoupper(substr(md5(time()),0,6))]); exit; }
        $words = preg_split('/\s+/', $name);
        $code = ''; foreach ($words as $w) { $code .= mb_strtoupper(mb_substr($w,0,2)); }
        $code = substr($code,0,6).'-'.rand(10,99);
        if (DB::run("SELECT id FROM products WHERE tenant_id=? AND code=?", [$tenant_id,$code])->fetch()) $code .= rand(1,9);
        echo json_encode(['code'=>$code]); exit;
    }

    // ─── AI ASSIST ───
    if ($ajax === 'ai_assist' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $question = $input['question'] ?? '';
        if (!$question) { echo json_encode(['error'=>'no_question']); exit; }
        $stats = DB::run("SELECT COUNT(*) AS cnt, COALESCE(SUM(i.quantity),0) AS total_qty FROM products p LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1", [$store_id,$tenant_id])->fetch(PDO::FETCH_ASSOC);
        $low = DB::run("SELECT COUNT(*) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.min_quantity>0 AND i.quantity<=p.min_quantity AND i.quantity>0", [$store_id,$tenant_id])->fetchColumn();
        $out = DB::run("SELECT COUNT(*) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND i.quantity=0", [$store_id,$tenant_id])->fetchColumn();
        $zombie = DB::run("SELECT COUNT(*) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND i.quantity>0 AND DATEDIFF(NOW(),COALESCE((SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=p.id),p.created_at))>45", [$store_id,$tenant_id])->fetchColumn();
        $cats = DB::run("SELECT name FROM categories WHERE tenant_id=?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $sups = DB::run("SELECT name FROM suppliers WHERE tenant_id=?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $top5 = DB::run("SELECT p.name, SUM(si.quantity) AS sold FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id WHERE p.tenant_id=? AND s.store_id=? AND s.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY p.id ORDER BY sold DESC LIMIT 5", [$tenant_id,$store_id])->fetchAll(PDO::FETCH_ASSOC);
        $top5str = implode(', ', array_map(fn($t)=>$t['name'].'('.$t['sold'].'бр)', $top5));
        $memories = DB::run("SELECT memory_text FROM tenant_ai_memory WHERE tenant_id=? ORDER BY created_at DESC LIMIT 10", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $role_bg = match($user_role) { 'owner'=>'собственик','manager'=>'управител',default=>'продавач' };
        $systemPrompt = "Ти си AI асистент в модул 'Артикули' на RunMyStore.ai.\nМагазин: {$stats['cnt']} артикула, {$stats['total_qty']} бройки, {$low} ниска наличност, {$out} изчерпани, {$zombie} zombie.\nПотребител: {$user_name}, роля: {$role_bg}.\nКатегории: ".implode(', ',$cats)."\nДоставчици: ".implode(', ',$sups)."\nТоп 5 (30 дни): {$top5str}\n".($memories?"Памет: ".implode('; ',$memories):"")."\nПРАВИЛА: Кратко (2-3 изречения), конкретно, с числа. Формула: Число + Защо + Какво да направиш.\nВЪРНИ САМО JSON: {\"message\":\"...\",\"action\":\"...\",\"data\":{},\"buttons\":[]}\nДЕЙСТВИЯ: search, add_product, show_zombie, show_low, show_top, navigate, product_detail, null";
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/'.GEMINI_MODEL.':generateContent?key='.GEMINI_API_KEY;
        $payload = ['contents'=>[['parts'=>[['text'=>$question]]]],'systemInstruction'=>['parts'=>[['text'=>$systemPrompt]]],'generationConfig'=>['temperature'=>0.3,'maxOutputTokens'=>500]];
        $ch = curl_init($api_url); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
        $resp = curl_exec($ch); curl_close($ch);
        $text = trim(json_decode($resp,true)['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $text = preg_replace('/```json\s*|\s*```/', '', $text);
        $parsed = json_decode($text, true);
        echo json_encode($parsed && isset($parsed['message']) ? $parsed : ['message'=>$text?:'Не разбрах.','action'=>null,'data'=>new \stdClass(),'buttons'=>[]]); exit;
    }

    // ─── ADD SUPPLIER ───
    if ($ajax === 'add_supplier' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) { echo json_encode(['error'=>'Въведи име']); exit; }
        $exists = DB::run("SELECT id FROM suppliers WHERE tenant_id=? AND name=?", [$tenant_id,$name])->fetch();
        if ($exists) { echo json_encode(['id'=>$exists['id'],'name'=>$name,'duplicate'=>true]); exit; }
        DB::run("INSERT INTO suppliers (tenant_id,name,is_active) VALUES (?,?,1)", [$tenant_id,$name]);
        echo json_encode(['id'=>DB::get()->lastInsertId(),'name'=>$name]); exit;
    }

    // ─── ADD CATEGORY ───
    if ($ajax === 'add_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $parent = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        if (!$name) { echo json_encode(['error'=>'Въведи име']); exit; }
        $exists = $parent ? DB::run("SELECT id FROM categories WHERE tenant_id=? AND name=? AND parent_id=?",[$tenant_id,$name,$parent])->fetch() : DB::run("SELECT id FROM categories WHERE tenant_id=? AND name=? AND parent_id IS NULL",[$tenant_id,$name])->fetch();
        if ($exists) { echo json_encode(['id'=>$exists['id'],'name'=>$name,'duplicate'=>true]); exit; }
        DB::run("INSERT INTO categories (tenant_id,name,parent_id) VALUES (?,?,?)", [$tenant_id,$name,$parent]);
        echo json_encode(['id'=>DB::get()->lastInsertId(),'name'=>$name]); exit;
    }

    // ─── ADD SUBCATEGORY ───
    if ($ajax === 'add_subcategory' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $parent_id = (int)($_POST['parent_id'] ?? 0);
        if (!$name || !$parent_id) { echo json_encode(['error'=>'Въведи име и категория']); exit; }
        $exists = DB::run("SELECT id FROM categories WHERE tenant_id=? AND name=? AND parent_id=?",[$tenant_id,$name,$parent_id])->fetch();
        if ($exists) { echo json_encode(['id'=>$exists['id'],'name'=>$name,'duplicate'=>true]); exit; }
        DB::run("INSERT INTO categories (tenant_id,name,parent_id) VALUES (?,?,?)", [$tenant_id,$name,$parent_id]);
        echo json_encode(['id'=>DB::get()->lastInsertId(),'name'=>$name]); exit;
    }

    // ─── ADD UNIT ───
    if ($ajax === 'add_unit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $unit = trim($_POST['unit'] ?? '');
        if (!$unit) { echo json_encode(['error'=>'Въведи единица']); exit; }
        $t = DB::run("SELECT units_config FROM tenants WHERE id=?", [$tenant_id])->fetch();
        $units = json_decode($t['units_config'] ?? '[]', true) ?: [];
        if (!in_array($unit,$units)) { $units[]=$unit; DB::run("UPDATE tenants SET units_config=? WHERE id=?", [json_encode($units,JSON_UNESCAPED_UNICODE),$tenant_id]); }
        echo json_encode(['units'=>$units,'added'=>$unit]); exit;
    }

    // ─── SKIP WHOLESALE ───
    if ($ajax === 'skip_wholesale' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        DB::run("UPDATE tenants SET skip_wholesale_price=1 WHERE id=?", [$tenant_id]);
        echo json_encode(['ok'=>true]); exit;
    }

    // ─── AI IMAGE ───
    if ($ajax === 'ai_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? 'bg_removal';
        if ($type === 'bg_removal') { $cr = DB::run("SELECT ai_credits_bg FROM tenants WHERE id=?",[$tenant_id])->fetchColumn(); if ($cr<=0) { echo json_encode(['error'=>'Нямаш кредити за бял фон']); exit; } }
        else { $cr = DB::run("SELECT ai_credits_tryon FROM tenants WHERE id=?",[$tenant_id])->fetchColumn(); if ($cr<=0) { echo json_encode(['error'=>'Нямаш кредити за AI Магия']); exit; } }
        echo json_encode(['status'=>'pending','message'=>'AI обработва снимката...']); exit;
    }

    // ─── SAVE LABELS ───
    if ($ajax === 'save_labels' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        foreach ($input['variations'] ?? [] as $v) { $vid=(int)($v['id']??0); $mq=(int)($v['min_quantity']??0); if($vid>0) DB::run("UPDATE products SET min_quantity=? WHERE id=? AND tenant_id=?",[$mq,$vid,$tenant_id]); }
        echo json_encode(['ok'=>true]); exit;
    }

    // ─── EXPORT LABELS ───
    if ($ajax === 'export_labels') {
        $product_id = (int)($_GET['product_id'] ?? 0);
        $format = $_GET['format'] ?? 'csv';
        $variations = DB::run("SELECT p.id, p.name, p.code, p.barcode, p.size, p.color, p.min_quantity, COALESCE(SUM(i.quantity),0) AS stock FROM products p LEFT JOIN inventory i ON i.product_id=p.id WHERE (p.id=? OR p.parent_id=?) AND p.tenant_id=? AND p.is_active=1 GROUP BY p.id ORDER BY p.name", [$product_id,$product_id,$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        if ($format==='csv') {
            header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="labels_'.$product_id.'.csv"');
            $out=fopen('php://output','w'); fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out,['Име','Код','Баркод','Размер','Цвят','Мин.кол.','Наличност']);
            foreach ($variations as $v) fputcsv($out,[$v['name'],$v['code'],$v['barcode'],$v['size'],$v['color'],$v['min_quantity'],$v['stock']]);
            fclose($out);
        } else { echo json_encode($variations); }
        exit;
    }

    // ─── IMPORT CSV ───
    if ($ajax === 'import_csv' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['file'])) { echo json_encode(['error'=>'Няма файл']); exit; }
        $file=$_FILES['file']['tmp_name']; $rows=[];
        if (strtolower(pathinfo($_FILES['file']['name'],PATHINFO_EXTENSION))==='csv') {
            $handle=fopen($file,'r'); $header=fgetcsv($handle);
            while (($line=fgetcsv($handle))!==false) { $row=[]; foreach($header as $i=>$col) $row[trim($col)]=$line[$i]??''; $rows[]=$row; }
            fclose($handle);
        }
        echo json_encode(['columns'=>array_keys($rows[0]??[]),'preview'=>array_slice($rows,0,10),'total'=>count($rows),'all_rows'=>$rows]); exit;
    }

    // ─── SIGNALS (S43: 8 groups with filter field) ───
    if ($ajax === 'signals') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $signals = [];
        // НАЛИЧНОСТ (red)
        $v = DB::run("SELECT COUNT(DISTINCT p.id) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL AND i.quantity=0 AND p.id IN (SELECT si.product_id FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.store_id=? AND s.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND s.status='completed')", [$sid,$tenant_id,$sid])->fetchColumn();
        if ($v>0) $signals[]=['type'=>'zero_stock','group'=>'stock','color'=>'red','count'=>(int)$v,'label'=>'на нула с продажби','desc'=>$v.' арт. с продажби — 0 бр.','question'=>'Кои артикули с продажби са на нула?','filter'=>'zero_stock'];
        $v = DB::run("SELECT COUNT(DISTINCT p.id) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL AND i.quantity BETWEEN 1 AND 2", [$sid,$tenant_id])->fetchColumn();
        if ($v>0) $signals[]=['type'=>'critical_low','group'=>'stock','color'=>'red','count'=>(int)$v,'label'=>'критично ниски (1-2)','desc'=>$v.' арт. с 1-2 бр.','question'=>'Кои артикули имат само 1-2 броя?','filter'=>'critical_low'];
        $v = DB::run("SELECT COUNT(DISTINCT p.id) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND p.min_quantity>0 AND i.quantity>0 AND i.quantity<=p.min_quantity", [$sid,$tenant_id])->fetchColumn();
        if ($v>0) $signals[]=['type'=>'below_min','group'=>'stock','color'=>'red','count'=>(int)$v,'label'=>'под минимално','desc'=>$v.' арт. под минимум','question'=>'Кои артикули са под минимално количество?','filter'=>'below_min'];
        $v = DB::run("SELECT COUNT(DISTINCT p.id) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL AND i.quantity=0", [$sid,$tenant_id])->fetchColumn();
        if ($v>0) $signals[]=['type'=>'out_total','group'=>'stock','color'=>'red','count'=>(int)$v,'label'=>'всички на нула','desc'=>$v.' арт. с 0 бр.','question'=>'Покажи всички артикули с нулева наличност.','filter'=>'out'];
        // ПАРИ (purple, owner)
        if ($can_see_margin) {
            $v = DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL AND cost_price>0 AND retail_price<cost_price", [$tenant_id])->fetchColumn();
            if ($v>0) $signals[]=['type'=>'at_loss','group'=>'money','color'=>'purple','count'=>(int)$v,'label'=>'под себестойност','desc'=>$v.' арт. на загуба','question'=>'Кои артикули се продават под себестойност?','filter'=>'at_loss'];
            $v = DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL AND cost_price>0 AND retail_price>cost_price AND ((retail_price-cost_price)/retail_price*100)<15", [$tenant_id])->fetchColumn();
            if ($v>0) $signals[]=['type'=>'low_margin','group'=>'money','color'=>'purple','count'=>(int)$v,'label'=>'нисък марж <15%','desc'=>$v.' арт. марж под 15%','question'=>'Кои артикули имат нисък марж?','filter'=>'low_margin'];
            $v = DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL AND (cost_price IS NULL OR cost_price=0)", [$tenant_id])->fetchColumn();
            if ($v>0) $signals[]=['type'=>'no_cost','group'=>'money','color'=>'purple','count'=>(int)$v,'label'=>'без себестойност','desc'=>$v.' арт. без доставна цена','question'=>'Кои артикули нямат себестойност?','filter'=>'no_cost'];
            $signals[]=['type'=>'top_profit','group'=>'money','color'=>'purple','count'=>10,'label'=>'най-печеливши','desc'=>'Топ 10 по марж','question'=>'Кои артикули печелят най-много?','filter'=>'top_profit'];
        }
        // ПРОДАЖБИ (green)
        $v = DB::run("SELECT COUNT(DISTINCT si.product_id) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.store_id=? AND s.status='completed' AND s.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)", [$sid])->fetchColumn();
        if ($v>0) $signals[]=['type'=>'top_sales','group'=>'sales','color'=>'green','count'=>min(10,(int)$v),'label'=>'топ продажби','desc'=>'Най-продавани 30 дни','question'=>'Кои са топ 10 по продажби?','filter'=>'top_sales'];
        // ZOMBIE (yellow)
        $z = DB::run("SELECT COUNT(DISTINCT p.id) AS cnt, COALESCE(SUM(i.quantity*p.retail_price),0) AS val FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL AND i.quantity>0 AND DATEDIFF(NOW(),COALESCE((SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=p.id AND s.store_id=?),p.created_at))>45", [$sid,$tenant_id,$sid])->fetch(PDO::FETCH_ASSOC);
        if ($z['cnt']>0) $signals[]=['type'=>'zombie','group'=>'zombie','color'=>'yellow','count'=>(int)$z['cnt'],'label'=>'zombie 45+ дни','desc'=>number_format($z['val'],0,',','.').' '.$currency.' замразени','question'=>'Кои артикули са zombie стока?','filter'=>'zombie'];
        $v = DB::run("SELECT COUNT(DISTINCT p.id) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL AND i.quantity>0 AND DATEDIFF(NOW(),COALESCE((SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=p.id AND s.store_id=?),p.created_at))>90", [$sid,$tenant_id,$sid])->fetchColumn();
        if ($v>0) $signals[]=['type'=>'aging','group'=>'zombie','color'=>'yellow','count'=>(int)$v,'label'=>'остаряваща 90+ дни','desc'=>$v.' арт. над 90 дни','question'=>'Кои артикули са без движение над 90 дни?','filter'=>'aging'];
        $v = DB::run("SELECT COUNT(DISTINCT p.id) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL AND i.quantity>0 AND DATEDIFF(NOW(),COALESCE((SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=p.id AND s.store_id=?),p.created_at)) BETWEEN 25 AND 45", [$sid,$tenant_id,$sid])->fetchColumn();
        if ($v>0) $signals[]=['type'=>'slow_mover','group'=>'zombie','color'=>'yellow','count'=>(int)$v,'label'=>'бавно движещи 25-45д','desc'=>$v.' арт. с малко продажби','question'=>'Кои артикули са бавно движещи?','filter'=>'slow_mover'];
        // НОВИ (blue)
        $v = DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)", [$tenant_id])->fetchColumn();
        if ($v>0) $signals[]=['type'=>'new_week','group'=>'info','color'=>'blue','count'=>(int)$v,'label'=>'нови тази седмица','desc'=>$v.' арт. последните 7 дни','question'=>'Кои артикули бяха добавени тази седмица?','filter'=>'new_week'];
        // КАЧЕСТВО (orange)
        $v = DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL AND (image_url IS NULL OR image_url='')", [$tenant_id])->fetchColumn();
        if ($v>0) $signals[]=['type'=>'no_photo','group'=>'data','color'=>'orange','count'=>(int)$v,'label'=>'без снимка','desc'=>$v.' арт. без снимка','question'=>'Кои артикули нямат снимка?','filter'=>'no_photo'];
        $v = DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL AND (barcode IS NULL OR barcode='')", [$tenant_id])->fetchColumn();
        if ($v>0) $signals[]=['type'=>'no_barcode','group'=>'data','color'=>'orange','count'=>(int)$v,'label'=>'без баркод','desc'=>$v.' арт. без баркод','question'=>'Кои артикули нямат баркод?','filter'=>'no_barcode'];
        $v = DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL AND supplier_id IS NULL", [$tenant_id])->fetchColumn();
        if ($v>0) $signals[]=['type'=>'no_supplier','group'=>'data','color'=>'orange','count'=>(int)$v,'label'=>'без доставчик','desc'=>$v.' арт. без доставчик','question'=>'Кои артикули нямат доставчик?','filter'=>'no_supplier'];
        echo json_encode($signals); exit;
    }

    echo json_encode(['error'=>'unknown_action']); exit;
}

// Biz-coefficients
if (file_exists(__DIR__.'/biz-coefficients.php')) { require_once __DIR__.'/biz-coefficients.php'; $bizVars = findBizVariants($business_type ?: 'магазин'); } else { $bizVars = []; }

// Page data
$all_suppliers = DB::run("SELECT id, name FROM suppliers WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$all_categories = DB::run("SELECT id, name, parent_id FROM categories WHERE tenant_id=? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$tenant_cfg = DB::run("SELECT units_config FROM tenants WHERE id=?", [$tenant_id])->fetch();
$onboarding_units = json_decode($tenant_cfg['units_config'] ?? '[]', true) ?: ['бр','чифт','к-кт'];
$COLOR_PALETTE = [['name'=>'Черен','hex'=>'#1a1a1a'],['name'=>'Бял','hex'=>'#f5f5f5'],['name'=>'Сив','hex'=>'#6b7280'],['name'=>'Червен','hex'=>'#ef4444'],['name'=>'Син','hex'=>'#3b82f6'],['name'=>'Зелен','hex'=>'#22c55e'],['name'=>'Жълт','hex'=>'#eab308'],['name'=>'Розов','hex'=>'#ec4899'],['name'=>'Оранжев','hex'=>'#f97316'],['name'=>'Лилав','hex'=>'#8b5cf6'],['name'=>'Кафяв','hex'=>'#92400e'],['name'=>'Navy','hex'=>'#1e40af'],['name'=>'Бежов','hex'=>'#d4b896'],['name'=>'Бордо','hex'=>'#7f1d1d'],['name'=>'Тюркоаз','hex'=>'#14b8a6'],['name'=>'Графит','hex'=>'#374151'],['name'=>'Пудра','hex'=>'#f9a8d4'],['name'=>'Маслинен','hex'=>'#65a30d'],['name'=>'Корал','hex'=>'#fb923c'],['name'=>'Екрю','hex'=>'#fef3c7']];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="utf-8">
<title>Артикули — RunMyStore.ai</title>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="business-type" content="<?= htmlspecialchars($business_type) ?>">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════
   PRODUCTS MODULE — Sale.php/Warehouse.php Design System
   ═══════════════════════════════════════════════════════════ */
:root {
    --bg-main: #030712;
    --bg-card: rgba(15, 15, 40, 0.75);
    --bg-card-hover: rgba(23, 28, 58, 0.9);
    --border-subtle: rgba(99, 102, 241, 0.15);
    --border-glow: rgba(99, 102, 241, 0.4);
    --indigo-600: #4f46e5;
    --indigo-500: #6366f1;
    --indigo-400: #818cf8;
    --indigo-300: #a5b4fc;
    --text-primary: #f1f5f9;
    --text-secondary: #6b7280;
    --danger: #ef4444;
    --warning: #f59e0b;
    --success: #22c55e;
    --purple: #8b5cf6;
    --teal: #14b8a6;
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{
    background:var(--bg-main);color:var(--text-primary);
    font-family:'Montserrat',Inter,system-ui,sans-serif;
    min-height:100vh;overflow-x:hidden;
    -webkit-user-select:none;user-select:none;
}
body::before{
    content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);
    width:700px;height:400px;
    background:radial-gradient(ellipse,rgba(99,102,241,0.1) 0%,transparent 70%);
    pointer-events:none;z-index:0;
}
.main-wrap{position:relative;z-index:1;padding-bottom:180px;padding-top:0}

/* ═══ HEADER ═══ */
.top-header{
    position:sticky;top:0;z-index:50;padding:10px 16px;
    backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
    background:rgba(3,7,18,0.88);border-bottom:1px solid var(--border-subtle);
}
.header-row{display:flex;align-items:center;justify-content:space-between}
.header-title{
    font-size:17px;font-weight:800;margin:0;
    background:linear-gradient(to right,#f1f5f9,#c7d2fe,#f1f5f9);
    background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;
    animation:gradShift 6s linear infinite;
}
@keyframes gradShift{0%{background-position:0% center}100%{background-position:200% center}}
.store-select{
    background:rgba(99,102,241,0.12);border:1px solid var(--border-subtle);
    color:var(--indigo-300);padding:3px 10px;border-radius:99px;
    font-size:10px;font-weight:700;font-family:inherit;outline:none;
    -webkit-appearance:none;
}

/* ═══ SEARCH BAR ═══ */
.search-wrap{display:flex;align-items:center;gap:6px;padding:8px 16px;background:rgba(3,7,18,0.5)}
.search-display{
    flex:1;height:38px;display:flex;align-items:center;padding:0 12px;gap:6px;
    background:rgba(99,102,241,0.06);border:1px solid var(--border-subtle);
    border-radius:12px;font-size:13px;color:var(--text-primary);overflow:hidden;white-space:nowrap;
    cursor:pointer;
}
.search-display .ph{color:var(--text-secondary);font-size:12px}
.search-display svg{flex-shrink:0;color:var(--text-secondary)}
.srch-btn{
    width:38px;height:38px;border-radius:10px;border:1px solid var(--border-subtle);
    background:rgba(99,102,241,0.06);color:var(--indigo-300);
    display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;
    transition:all 0.15s;
}
.srch-btn:active{background:rgba(99,102,241,0.2);transform:scale(0.92)}

/* ═══ TABS ═══ */
.tabs-row{display:flex;gap:6px;padding:8px 16px 0;overflow-x:auto;scrollbar-width:none}
.tabs-row::-webkit-scrollbar{display:none}
.tab-pill{
    padding:6px 14px;border-radius:99px;border:1px solid var(--border-subtle);
    background:transparent;color:var(--text-secondary);font-size:11px;font-weight:600;
    white-space:nowrap;cursor:pointer;font-family:inherit;flex-shrink:0;transition:all 0.2s;
}
.tab-pill.active{
    background:linear-gradient(135deg,var(--indigo-500),var(--indigo-600));
    color:#fff;border-color:transparent;box-shadow:0 0 12px rgba(99,102,241,0.3);
}
.tab-pill .cnt{
    display:inline-block;min-width:16px;height:16px;line-height:16px;text-align:center;
    border-radius:8px;background:rgba(255,255,255,0.15);font-size:9px;margin-left:4px;padding:0 4px;
}

/* ═══ STAT CARDS ═══ */
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:10px 16px 0}
.stat-card{
    padding:12px 14px;border-radius:12px;background:var(--bg-card);
    border:1px solid var(--border-subtle);position:relative;overflow:hidden;
}
.stat-card .st-label{font-size:9px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;font-weight:700}
.stat-card .st-value{font-size:20px;font-weight:800;margin-top:3px}
.stat-card .st-icon{
    position:absolute;top:10px;right:10px;width:30px;height:30px;border-radius:8px;
    background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;font-size:15px;
}

/* ═══ COLLAPSE SECTIONS ═══ */
.collapse-sec{margin:6px 16px 0;border-radius:12px;background:var(--bg-card);border:1px solid var(--border-subtle);overflow:hidden}
.collapse-hdr{
    display:flex;align-items:center;justify-content:space-between;padding:9px 12px;
    cursor:pointer;user-select:none;transition:background 0.15s;
}
.collapse-hdr:active{background:rgba(99,102,241,0.06)}
.ch-left{display:flex;align-items:center;gap:7px}
.ch-icon{font-size:14px}
.ch-title{font-size:12px;font-weight:700}
.ch-count{font-size:9px;color:var(--text-secondary);background:rgba(99,102,241,0.1);padding:1px 7px;border-radius:99px;font-weight:600}
.ch-arrow{transition:transform 0.3s;color:var(--text-secondary);font-size:9px}
.collapse-hdr.open .ch-arrow{transform:rotate(180deg)}
.collapse-body{max-height:0;overflow:hidden;transition:max-height 0.35s ease}
.collapse-body.open{max-height:2000px}
.collapse-inner{padding:0 10px 10px}

/* ═══ PRODUCT CARDS ═══ */
.p-card{
    display:flex;align-items:center;gap:10px;padding:9px 10px 9px 14px;
    border-radius:10px;background:rgba(17,24,44,0.5);border:1px solid var(--border-subtle);
    margin-bottom:5px;cursor:pointer;position:relative;overflow:hidden;
    transition:all 0.2s;
}
.p-card:active{transform:scale(0.98);background:var(--bg-card-hover)}
.stock-bar{position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:3px 0 0 3px}
.stock-bar.green{background:var(--success);box-shadow:0 0 8px rgba(34,197,94,0.6)}
.stock-bar.yellow{background:var(--warning);box-shadow:0 0 8px rgba(234,179,8,0.6)}
.stock-bar.red{background:var(--danger);box-shadow:0 0 8px rgba(239,68,68,0.6)}
.p-thumb{
    width:38px;height:38px;border-radius:8px;background:rgba(99,102,241,0.08);
    display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;
}
.p-thumb img{width:100%;height:100%;object-fit:cover;border-radius:8px}
.p-info{flex:1;min-width:0}
.p-name{font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.p-meta{font-size:9px;color:var(--text-secondary);margin-top:2px;display:flex;gap:5px}
.p-right{text-align:right;flex-shrink:0}
.p-price{font-size:13px;font-weight:700;color:var(--indigo-300)}
.p-stock{font-size:9px;margin-top:2px}
.p-stock.ok{color:var(--success)} .p-stock.low{color:var(--warning)} .p-stock.out{color:var(--danger)}
.p-discount{position:absolute;top:3px;right:3px;font-size:8px;padding:1px 5px;border-radius:4px;background:var(--danger);color:#fff;font-weight:700}

/* ═══ SUPPLIER CARDS (horizontal scroll) ═══ */
.swipe-row{padding:10px 16px;overflow-x:auto;display:flex;gap:10px;scroll-snap-type:x mandatory;scrollbar-width:none}
.swipe-row::-webkit-scrollbar{display:none}
.sup-card{
    min-width:240px;max-width:280px;flex-shrink:0;scroll-snap-align:start;
    border-radius:14px;padding:14px;background:var(--bg-card);border:1px solid var(--border-subtle);
    cursor:pointer;transition:all 0.2s;position:relative;
}
.sup-card:active{transform:scale(0.97)}
.sup-card .sc-name{font-size:14px;font-weight:700}
.sup-card .sc-count{font-size:10px;color:var(--text-secondary);margin-top:2px}
.sup-card .sc-badges{display:flex;gap:5px;margin-top:8px;flex-wrap:wrap}
.sc-badge{font-size:9px;padding:2px 7px;border-radius:99px;font-weight:600}
.sc-badge.ok{background:rgba(34,197,94,0.15);color:var(--success)}
.sc-badge.low{background:rgba(234,179,8,0.15);color:var(--warning)}
.sc-badge.out{background:rgba(239,68,68,0.15);color:var(--danger)}
.sup-card .sc-arrow{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text-secondary);opacity:0.4}

/* ═══ CATEGORY LIST ═══ */
.cat-item{
    display:flex;align-items:center;justify-content:space-between;padding:12px 14px;
    border-bottom:1px solid rgba(99,102,241,0.06);cursor:pointer;transition:background 0.15s;
}
.cat-item:active{background:rgba(99,102,241,0.06)}
.cat-item:last-child{border-bottom:none}
.cat-card{
    min-width:160px;flex-shrink:0;scroll-snap-align:start;border-radius:12px;padding:12px;
    background:var(--bg-card);border:1px solid var(--border-subtle);cursor:pointer;
    transition:all 0.2s;
}
.cat-card:active{transform:scale(0.97)}

/* ═══ SECTION TITLE ═══ */
.sec-title{
    padding:12px 16px 4px;font-size:10px;font-weight:700;color:var(--text-secondary);
    text-transform:uppercase;letter-spacing:0.8px;display:flex;align-items:center;gap:8px;
}
.sec-title::before{content:'';display:inline-block;width:20px;height:1px;background:linear-gradient(to right,transparent,var(--indigo-500))}
.sec-title::after{content:'';flex:1;height:1px;background:linear-gradient(to left,transparent,var(--border-subtle))}

/* ═══ PAGINATION ═══ */
.pagination{display:flex;align-items:center;justify-content:center;gap:4px;padding:12px 16px}
.pg-btn{
    width:28px;height:28px;border-radius:7px;border:1px solid var(--border-subtle);
    background:transparent;color:var(--text-secondary);font-size:11px;font-weight:600;
    cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:inherit;
}
.pg-btn.active{background:var(--indigo-500);color:#fff;border-color:transparent}

/* ═══ QUICK ACTIONS PILL BAR ═══ */
.qa-bar{
    position:fixed;bottom:100px;left:50%;transform:translateX(-50%);z-index:41;
    display:flex;gap:5px;
    background:rgba(8,8,24,0.92);backdrop-filter:blur(16px);
    padding:5px 6px;border-radius:14px;
    border:1px solid var(--border-glow);
    box-shadow:0 4px 30px rgba(99,102,241,0.15),0 0 50px rgba(0,0,0,0.4);
}
.qa-btn{
    display:flex;align-items:center;gap:5px;border:none;cursor:pointer;
    font-family:inherit;border-radius:10px;transition:all 0.15s;
}
.qa-btn:active{transform:scale(0.92)}
.qa-btn span{font-size:9px;font-weight:800;letter-spacing:0.3px}
.qa-ai{
    padding:7px 14px;
    background:linear-gradient(135deg,rgba(99,102,241,0.25),rgba(139,92,246,0.15));
    box-shadow:0 0 10px rgba(99,102,241,0.15);
}
.qa-ai span{color:var(--indigo-300)}
.qa-ai svg{filter:drop-shadow(0 0 4px rgba(99,102,241,0.5))}
.qa-other{padding:7px 10px;background:transparent}
.qa-teal{border:1px solid rgba(20,184,166,0.2);background:rgba(20,184,166,0.06)}
.qa-teal span{color:rgba(20,184,166,0.85)}
.qa-green{border:1px solid rgba(34,197,94,0.2);background:rgba(34,197,94,0.06)}
.qa-green span{color:rgba(34,197,94,0.85)}
.qa-yellow{border:1px solid rgba(234,179,8,0.2);background:rgba(234,179,8,0.06)}
.qa-yellow span{color:rgba(234,179,8,0.85)}

/* ═══ SCREEN NAV ═══ */
.screen-nav{
    position:fixed;bottom:56px;left:50%;transform:translateX(-50%);
    width:calc(100% - 24px);max-width:400px;z-index:40;
}
.screen-nav-inner{
    display:flex;gap:2px;background:rgba(3,7,18,0.9);backdrop-filter:blur(12px);
    border-radius:11px;padding:3px;border:1px solid var(--border-subtle);
}
.sn-btn{
    flex:1;display:flex;flex-direction:column;align-items:center;gap:1px;
    padding:5px 2px;border-radius:8px;border:none;background:transparent;
    color:var(--text-secondary);font-size:8px;font-weight:600;cursor:pointer;font-family:inherit;
    transition:all 0.2s;
}
.sn-btn.active{background:rgba(99,102,241,0.18);color:#fff;box-shadow:0 0 10px rgba(99,102,241,0.15)}
.sn-btn:active{transform:scale(0.95)}
.sn-btn svg{width:13px;height:13px}

/* ═══ BOTTOM NAV ═══ */






/* ═══ VOICE OVERLAY — EXACTLY sale.php ═══ */
.rec-ov{
    position:fixed;inset:0;z-index:300;
    background:rgba(3,7,18,0.6);backdrop-filter:blur(8px);
    display:none;align-items:flex-end;justify-content:center;
    padding:0 16px 100px;
}
.rec-ov.open{display:flex}
.rec-box{
    width:100%;max-width:400px;
    background:rgba(15,15,40,0.95);
    border:1px solid var(--border-glow);
    border-radius:20px;padding:20px;
    box-shadow:0 -12px 50px rgba(99,102,241,0.25),0 0 40px rgba(0,0,0,0.5);
    animation:recSlideUp 0.25s ease;
}
@keyframes recSlideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
.rec-status{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.rec-dot{
    width:16px;height:16px;border-radius:50%;background:var(--danger);flex-shrink:0;
    box-shadow:0 0 12px var(--danger),0 0 24px rgba(239,68,68,0.4);
    animation:recPulse 1s ease infinite;
}
.rec-dot.ready{background:var(--success);box-shadow:0 0 12px var(--success),0 0 24px rgba(34,197,94,0.4);animation:none}
@keyframes recPulse{
    0%,100%{opacity:1;box-shadow:0 0 8px var(--danger),0 0 16px rgba(239,68,68,0.3)}
    50%{opacity:0.5;box-shadow:0 0 20px var(--danger),0 0 40px rgba(239,68,68,0.6)}
}
.rec-label{font-size:15px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px}
.rec-label.recording{color:var(--danger)}
.rec-label.ready{color:var(--success)}
.rec-transcript{
    min-height:44px;padding:10px 14px;margin-bottom:14px;
    background:rgba(99,102,241,0.06);border:1px solid var(--border-subtle);
    border-radius:12px;font-size:15px;font-weight:500;
    color:var(--text-primary);line-height:1.4;word-wrap:break-word;
}
.rec-transcript.empty{color:var(--text-secondary);font-style:italic}
.rec-hint{font-size:11px;color:var(--text-secondary);margin-bottom:14px;text-align:center;line-height:1.4}
.rec-actions{display:flex;gap:8px}
.rec-btn-cancel{
    flex:1;height:44px;border-radius:12px;border:1px solid var(--border-subtle);
    background:var(--bg-card);color:var(--indigo-300);font-size:14px;font-weight:600;
    cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;
}
.rec-btn-cancel:active{background:rgba(99,102,241,0.12)}
.rec-btn-send{
    flex:2;height:44px;border-radius:12px;border:none;
    background:linear-gradient(135deg,var(--indigo-600),var(--indigo-500));
    color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;
    display:flex;align-items:center;justify-content:center;gap:6px;
    box-shadow:0 4px 16px rgba(99,102,241,0.35);transition:all 0.2s;
}
.rec-btn-send:active{transform:scale(0.97)}
.rec-btn-send:disabled{opacity:0.3;pointer-events:none}

/* ═══ DRAWERS (sale.php style) ═══ */
.drawer-ov{
    position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:200;
    opacity:0;pointer-events:none;transition:opacity 0.25s;backdrop-filter:blur(4px);
}
.drawer-ov.open{opacity:1;pointer-events:all}
.drawer{
    position:fixed;bottom:0;left:0;right:0;z-index:201;
    background:#080818;border-top:1px solid var(--border-glow);
    border-radius:22px 22px 0 0;padding:0 16px 40px;
    transform:translateY(100%);transition:transform 0.32s cubic-bezier(0.32,0,0.67,0);
    max-height:90vh;overflow-y:auto;-webkit-overflow-scrolling:touch;
    box-shadow:0 -20px 60px rgba(99,102,241,0.2);
}
.drawer.open{transform:translateY(0)}
.drawer-handle{width:36px;height:4px;background:rgba(99,102,241,0.3);border-radius:2px;margin:14px auto 10px}
.drawer-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.drawer-hdr h3{font-size:15px;font-weight:800;margin:0}
.drawer-close{
    width:32px;height:32px;border-radius:10px;background:rgba(99,102,241,0.1);
    border:1px solid var(--border-subtle);color:var(--indigo-300);font-size:16px;
    display:flex;align-items:center;justify-content:center;cursor:pointer;
}

/* ═══ MODAL (fullscreen wizard) ═══ */
.modal-ov{
    position:fixed;inset:0;z-index:250;background:var(--bg-main);
    opacity:0;pointer-events:none;transition:opacity 0.3s;
    display:flex;flex-direction:column;
}
.modal-ov.open{opacity:1;pointer-events:auto}
.modal-hdr{
    display:flex;align-items:center;justify-content:space-between;padding:12px 16px;
    border-bottom:1px solid var(--border-subtle);flex-shrink:0;
}
.modal-hdr h2{font-size:16px;font-weight:800;margin:0}
.modal-body{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch}
.wiz-steps{display:flex;gap:3px;padding:8px 16px}
.wiz-step{flex:1;height:3px;border-radius:2px;background:var(--border-subtle);transition:all 0.3s}
.wiz-step.active{background:linear-gradient(to right,var(--indigo-500),var(--purple));box-shadow:0 0 6px rgba(99,102,241,0.3)}
.wiz-step.done{background:var(--indigo-500)}
.wiz-label{font-size:9px;color:var(--text-secondary);padding:0 16px 6px}
.wiz-label b{color:var(--indigo-300)}
.wiz-page{display:none;padding:16px}
.wiz-page.active{display:block;animation:wizFade 0.2s ease}
@keyframes wizFade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

/* ═══ FORM ELEMENTS ═══ */
.fg{margin-bottom:10px}
.fl{display:block;font-size:9px;font-weight:700;color:var(--text-secondary);margin-bottom:3px;text-transform:uppercase;letter-spacing:0.3px}
.fl .hint{color:rgba(107,114,128,0.7);font-weight:400;text-transform:none;letter-spacing:0}
.fl .fl-add{float:right;color:var(--indigo-300);font-weight:700;cursor:pointer;text-transform:none;letter-spacing:0;font-size:12px;padding:4px 10px;border-radius:8px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.3)}
.fc{
    width:100%;padding:9px 12px;border-radius:10px;border:1px solid var(--border-subtle);
    background:rgba(30,35,50,0.9);color:var(--text-primary);font-size:14px;outline:none;
    font-family:inherit;transition:border-color 0.2s;
}
.fc:focus{border-color:var(--border-glow);box-shadow:0 0 12px rgba(99,102,241,0.1)}
.fc::placeholder{color:var(--text-secondary)}
select.fc{
    appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23818cf8'%3E%3Cpath d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 12px center;padding-right:30px;
}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.inline-add{
    background:rgba(99,102,241,0.04);border:1px solid rgba(99,102,241,0.12);
    border-radius:8px;padding:8px;margin-top:4px;display:none;gap:6px;align-items:center;
}
.inline-add.open{display:flex;animation:inlGlow 0.4s ease;border-color:rgba(34,197,94,0.4);background:rgba(34,197,94,0.08);box-shadow:0 0 6px rgba(34,197,94,0.15)}
@keyframes inlGlow{0%{box-shadow:0 0 0 rgba(34,197,94,0)}50%{box-shadow:0 0 18px rgba(34,197,94,0.4)}100%{box-shadow:0 0 6px rgba(34,197,94,0.15)}}
.preset-ov{position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(8px);display:flex;align-items:flex-end;justify-content:center;animation:fadeIn 0.2s ease}
.preset-box{background:var(--bg-card);border-radius:20px 20px 0 0;width:100%;max-width:480px;max-height:85vh;overflow-y:auto;padding:20px;border:1px solid var(--border-subtle);border-bottom:none}
.preset-chip{display:inline-block;padding:8px 16px;margin:4px;border-radius:10px;border:1.5px solid var(--border-subtle);background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:14px;font-weight:600;cursor:pointer;transition:all 0.15s;user-select:none}
.preset-chip.sel{border-color:var(--indigo-500);background:rgba(99,102,241,0.2);color:var(--indigo-300);box-shadow:0 0 8px rgba(99,102,241,0.2)}
.preset-chip:active{transform:scale(0.95)}
.preset-cat{font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin:12px 0 6px;letter-spacing:0.5px}
.inline-add input{flex:1;padding:7px 10px;border-radius:6px;border:1px solid var(--border-subtle);background:var(--bg-card);color:var(--text-primary);font-size:13px;outline:none;font-family:inherit}
.inline-add button{padding:7px 12px;border-radius:6px;border:none;background:var(--indigo-500);color:#fff;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap}

/* ═══ ACTION BUTTONS ═══ */
.abtn{
    padding:11px;border-radius:12px;border:1px solid var(--border-subtle);
    background:var(--bg-card);color:var(--text-primary);font-size:14px;font-weight:600;
    cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;
    transition:all 0.15s;font-family:inherit;width:100%;
}
.abtn:active{transform:scale(0.97)}
.abtn.primary{background:linear-gradient(135deg,var(--indigo-600),var(--indigo-500));border-color:transparent;color:#fff;box-shadow:0 4px 16px rgba(99,102,241,0.3)}
.abtn.save{background:linear-gradient(135deg,var(--success),#16a34a);border-color:transparent;color:#fff;box-shadow:0 4px 16px rgba(34,197,94,0.25)}
.abtn.danger{border-color:rgba(239,68,68,0.3);color:var(--danger)}
.abtn-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}

/* ═══ TOAST ═══ */
.toast-c{position:fixed;top:16px;left:16px;right:16px;z-index:500;display:flex;flex-direction:column;gap:6px;pointer-events:none}
.toast{
    padding:10px 16px;border-radius:12px;background:rgba(15,15,40,0.95);
    border:1px solid var(--border-glow);backdrop-filter:blur(12px);
    color:var(--text-primary);font-size:13px;font-weight:600;
    transform:translateY(-20px);opacity:0;transition:all 0.3s;
    pointer-events:auto;display:flex;align-items:center;gap:6px;
}
.toast.show{transform:translateY(0);opacity:1}
.toast.success{border-color:rgba(34,197,94,0.4)}
.toast.error{border-color:rgba(239,68,68,0.4)}

/* ═══ AI INSIGHT CARDS ═══ */
.ai-insight{padding:10px 12px;border-radius:10px;margin-bottom:6px;display:flex;align-items:flex-start;gap:8px}
.ai-insight.critical{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2)}
.ai-insight.high{background:rgba(234,179,8,0.08);border:1px solid rgba(234,179,8,0.2)}
.ai-insight.medium{background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2)}
.ai-insight.info{background:rgba(99,102,241,0.04);border:1px solid var(--border-subtle)}
.ai-insight .ai-icon{font-size:16px;flex-shrink:0}
.ai-insight .ai-text{font-size:12px;line-height:1.4}

/* ═══ DETAIL ROW ═══ */
.d-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid rgba(99,102,241,0.06)}
.d-row:last-child{border-bottom:none}
.d-label{font-size:11px;color:var(--text-secondary)}
.d-value{font-size:13px;font-weight:600}
.store-stock-row{display:flex;justify-content:space-between;align-items:center;padding:5px 10px;border-radius:8px;margin-bottom:3px;background:rgba(17,24,44,0.5)}

/* ═══ CAMERA OVERLAY ═══ */
.camera-ov{position:fixed;inset:0;z-index:350;background:#000;display:none;flex-direction:column}
.camera-ov.open{display:flex}
.camera-video{flex:1;object-fit:cover}
.cam-controls{padding:16px;display:flex;justify-content:center;gap:16px;background:rgba(0,0,0,0.8)}
.cam-btn{width:56px;height:56px;border-radius:50%;border:3px solid #fff;background:transparent;color:#fff;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.cam-btn.capture{background:#fff;color:#000}
.cam-btn.close-cam{border-color:var(--danger);color:var(--danger)}
.scan-line{
    position:absolute;left:10%;right:10%;height:2px;background:var(--success);
    box-shadow:0 0 12px var(--success);top:50%;animation:scanAnim 2s ease-in-out infinite;
}
@keyframes scanAnim{0%,100%{transform:translateY(-40px)}50%{transform:translateY(40px)}}
.green-flash{position:fixed;inset:0;background:rgba(34,197,94,0.3);z-index:360;pointer-events:none;animation:flashOut 0.5s ease-out forwards}
@keyframes flashOut{to{opacity:0}}

/* ═══ EMPTY STATE ═══ */
.empty-st{text-align:center;padding:40px 20px}
.empty-st .es-icon{font-size:48px;margin-bottom:10px;opacity:0.4}
.empty-st .es-text{font-size:13px;color:var(--text-secondary)}

/* ═══ BREADCRUMB ═══ */
.breadcrumb{display:flex;align-items:center;gap:4px;padding:6px 16px;font-size:11px;color:var(--text-secondary);flex-wrap:wrap}
.breadcrumb a{color:var(--indigo-400);text-decoration:none}

/* ═══ SORT DROPDOWN ═══ */
.sort-wrap{position:relative}
.sort-btn{background:transparent;border:1px solid var(--border-subtle);color:var(--text-secondary);padding:4px 10px;border-radius:8px;font-size:10px;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:4px}
.sort-dd{position:absolute;top:100%;right:0;margin-top:4px;background:#080818;border:1px solid var(--border-subtle);border-radius:10px;padding:4px;min-width:160px;z-index:60;display:none;box-shadow:0 8px 32px rgba(0,0,0,0.5)}
.sort-dd.open{display:block}
.sort-opt{padding:8px 12px;border-radius:8px;font-size:12px;cursor:pointer;color:var(--text-secondary);transition:background 0.15s}
.sort-opt.active{background:rgba(99,102,241,0.12);color:var(--indigo-300)}
.sort-opt:active{background:rgba(99,102,241,0.08)}

/* ═══ FILTER DRAWER ═══ */
.filter-chips{display:flex;flex-wrap:wrap;gap:5px}
.f-chip{padding:5px 11px;border-radius:8px;border:1px solid var(--border-subtle);background:transparent;color:var(--text-secondary);font-size:11px;cursor:pointer;font-family:inherit;transition:all 0.15s}
.f-chip.sel{background:rgba(99,102,241,0.15);color:var(--indigo-300);border-color:var(--border-glow)}

/* ═══ IMAGE STUDIO ═══ */
.studio-option{
    display:flex;align-items:center;gap:14px;padding:14px 16px;
    border-radius:14px;cursor:pointer;margin-bottom:8px;transition:all 0.15s;
}
.studio-option:active{transform:scale(0.98)}
.studio-icon{
    width:48px;height:48px;border-radius:14px;display:flex;align-items:center;
    justify-content:center;font-size:24px;flex-shrink:0;
}
.studio-price{padding:5px 12px;border-radius:10px;font-size:14px;font-weight:800;flex-shrink:0}

/* ═══ LABELS SCREEN ═══ */
.label-var{
    padding:10px 12px;border-radius:10px;background:rgba(17,24,44,0.5);
    border:1px solid var(--border-subtle);margin-bottom:6px;
}
.label-var .lv-name{font-size:12px;font-weight:600;margin-bottom:2px}
.label-var .lv-code{font-size:9px;color:var(--text-secondary)}
.label-var .lv-fields{display:flex;gap:8px;margin-top:6px}
.label-var .lv-field{flex:1}
.label-var .lv-field label{font-size:8px;color:var(--text-secondary);text-transform:uppercase}
.label-var .lv-field input{
    width:100%;padding:6px 8px;border-radius:6px;border:1px solid var(--border-subtle);
    background:var(--bg-card);color:var(--text-primary);font-size:13px;text-align:center;
    outline:none;font-family:inherit;
}
.format-chips{display:flex;gap:6px;margin:10px 0}
.fmt-chip{
    padding:6px 14px;border-radius:8px;border:1px solid var(--border-subtle);
    background:transparent;color:var(--text-secondary);font-size:11px;font-weight:600;
    cursor:pointer;font-family:inherit;transition:all 0.15s;
}
.fmt-chip.sel{background:rgba(99,102,241,0.15);color:var(--indigo-300);border-color:var(--border-glow)}

/* ═══ SKELETON ═══ */
.skeleton{background:linear-gradient(90deg,var(--bg-card) 25%,rgba(99,102,241,0.08) 50%,var(--bg-card) 75%);background-size:200% 100%;animation:shimmer 1.5s infinite;border-radius:8px}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

/* ═══ CREDITS BAR ═══ */
.credits-bar{
    display:flex;justify-content:center;gap:14px;padding:8px 12px;margin:10px 16px;
    border-radius:10px;background:rgba(99,102,241,0.05);border:1px solid var(--border-subtle);
}
.credits-bar .cr-item{font-size:11px;color:var(--text-secondary);font-weight:600}
.credits-bar .cr-item b{color:var(--text-primary)}
.credits-bar .cr-sep{width:1px;background:var(--border-subtle)}

/* ═══ ANIMATIONS ═══ */
@keyframes cardIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes blink{0%,50%{opacity:1}51%,100%{opacity:0}}

/* ═══ MISC ═══ */
.screen-section{display:none}
.screen-section.active{display:block}
.hidden{display:none!important}
input[type=file]{display:none}

/* ═══ INFO AI BUTTON + PANEL ═══ */
.info-ai-btn{
    width:30px;height:30px;flex-shrink:0;border-radius:50%;
    background:linear-gradient(135deg,rgba(99,102,241,0.2),rgba(139,92,246,0.15));
    border:1px solid rgba(99,102,241,0.3);display:flex;align-items:center;justify-content:center;
    cursor:pointer;font-size:16px;box-shadow:0 0 14px rgba(99,102,241,0.2);
    animation:infoPulse 3s ease-in-out infinite;transition:all 0.2s;
}
.info-ai-btn:active{transform:scale(0.9)}
@keyframes infoPulse{0%,100%{box-shadow:0 0 10px rgba(99,102,241,0.2)}50%{box-shadow:0 0 22px rgba(99,102,241,0.4)}}
.info-panel{
    position:fixed;top:0;right:0;bottom:0;width:min(320px,85vw);z-index:250;
    background:rgba(8,8,24,0.97);backdrop-filter:blur(20px);
    border-left:1px solid var(--border-glow);
    transform:translateX(100%);transition:transform 0.3s cubic-bezier(0.32,0,0.67,0);
    display:flex;flex-direction:column;
    box-shadow:-10px 0 40px rgba(0,0,0,0.5);
}
.info-panel.open{transform:translateX(0)}
.info-panel-ov{position:fixed;inset:0;z-index:249;background:rgba(0,0,0,0.5);display:none}
.info-panel-ov.open{display:block}
.info-panel-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;
    border-bottom:1px solid var(--border-subtle);flex-shrink:0}
.info-panel-hdr h3{font-size:14px;font-weight:800;margin:0;
    background:linear-gradient(135deg,#f1f5f9,#a5b4fc);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.info-panel-close{width:28px;height:28px;border-radius:8px;background:rgba(99,102,241,0.1);
    border:1px solid var(--border-subtle);color:var(--indigo-300);font-size:14px;
    display:flex;align-items:center;justify-content:center;cursor:pointer}
.info-panel-body{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch;padding:10px 12px}
.info-q{display:flex;align-items:center;gap:8px;padding:10px 12px;margin-bottom:4px;
    border-radius:10px;background:rgba(99,102,241,0.04);border:1px solid var(--border-subtle);
    cursor:pointer;transition:all 0.15s}
.info-q:active{background:rgba(99,102,241,0.12);transform:scale(0.98)}
.info-q .iq-icon{font-size:16px;flex-shrink:0}
.info-q .iq-text{font-size:12px;font-weight:600;color:var(--text-primary)}
.info-q .iq-arrow{color:var(--text-secondary);font-size:10px;margin-left:auto;flex-shrink:0}
.info-answer{padding:10px 12px;margin:-2px 0 6px;border-radius:0 0 10px 10px;
    background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.15);border-top:none;
    font-size:12px;color:rgba(241,245,249,0.8);line-height:1.5;display:none}
.info-answer.open{display:block;animation:fadeUp 0.2s ease}
.info-section-title{font-size:9px;font-weight:800;color:var(--indigo-300);text-transform:uppercase;
    letter-spacing:1px;padding:10px 0 4px}
.info-free-wrap{padding:10px 12px;border-top:1px solid var(--border-subtle);flex-shrink:0}
.info-free-btn{width:100%;padding:10px;border-radius:12px;border:1px solid rgba(99,102,241,0.25);
    background:rgba(99,102,241,0.06);color:var(--indigo-300);font-size:13px;font-weight:700;
    cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;
    transition:all 0.15s}
.info-free-btn:active{background:rgba(99,102,241,0.2)}


/* ═══ WIZARD INFO SYSTEM ═══ */
.wiz-info-btn{display:inline-flex;width:24px;height:24px;border-radius:50%;background:rgba(99,102,241,0.15);border:1.5px solid rgba(99,102,241,0.4);align-items:center;justify-content:center;font-size:13px;font-weight:800;cursor:pointer;margin-left:6px;vertical-align:middle;transition:all 0.15s;flex-shrink:0;color:var(--indigo-300)}
.wiz-info-btn:active{background:rgba(99,102,241,0.2);transform:scale(0.9)}
.wiz-info-overlay{position:fixed;inset:0;z-index:400;background:rgba(3,7,18,0.7);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:20px}
.wiz-info-box{background:#080818;border:1px solid var(--border-glow);border-radius:16px;padding:16px;max-width:320px;width:100%;box-shadow:0 10px 40px rgba(99,102,241,0.2)}

/* ═══ NEW HEADER (chat.php matching) ═══ */
.hdr-row1{display:flex;align-items:center;justify-content:space-between;margin-bottom:2px}
.hdr-logo{font-size:11px;font-weight:700;color:rgba(165,180,252,.6);letter-spacing:.5px}
.hdr-right{display:flex;align-items:center;gap:8px}
.hdr-badge{font-size:8px;font-weight:800;letter-spacing:.8px;padding:3px 8px;border-radius:6px;background:linear-gradient(135deg,rgba(34,197,94,.12),rgba(34,197,94,.04));border:1px solid rgba(34,197,94,.25);color:#4ade80}
.hdr-ico{width:26px;height:26px;border-radius:50%;background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.12);display:flex;align-items:center;justify-content:center;text-decoration:none}
.hdr-row2{display:flex;align-items:center;justify-content:space-between;margin-top:8px}
.hdr-page-title{font-size:15px;font-weight:800;color:#e2e8f0;font-family:'Montserrat',system-ui}
.hdr-count{font-size:10px;color:rgba(165,180,252,.5);font-weight:600}
.top-header{position:sticky;top:0;z-index:50;padding:10px 16px;backdrop-filter:blur(16px);background:rgba(3,7,18,.95);border-bottom:1px solid var(--border-subtle)}

/* ═══ NEW SEARCH ═══ */
.new-search-sec{display:flex;align-items:center;gap:6px;padding:12px 16px 0}
.new-search-bar{display:flex;align-items:center;gap:10px;background:rgba(15,15,40,.6);border:1px solid rgba(99,102,241,.12);border-radius:14px;padding:10px 14px;flex:1;cursor:pointer}
.new-search-bar svg{flex-shrink:0;opacity:.5}
.new-search-ph{font-size:13px;color:rgba(165,180,252,.4);font-weight:500;flex:1}
.new-search-mic{width:28px;height:28px;border-radius:50%;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.new-info-btn{width:16px;height:16px;border-radius:50%;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.18);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0}

/* ═══ CASCADE FILTERS ═══ */
.fltr-label{display:flex;align-items:center;gap:6px;padding:0 16px;margin-top:10px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:rgba(165,180,252,.35)}
.fltr-label span{white-space:nowrap}
.fltr-hint{font-size:8px;color:rgba(165,180,252,.2);font-weight:500;text-transform:none;letter-spacing:0}
.fltr-info{width:15px;height:15px;border-radius:50%;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.18);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0}
.fltr-row{display:flex;gap:6px;padding:8px 16px 0;overflow-x:auto;-ms-overflow-style:none;scrollbar-width:none}
.fltr-row::-webkit-scrollbar{display:none}
.fltr-btn{padding:6px 13px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap;border:1px solid rgba(99,102,241,.1);background:rgba(15,15,40,.4);color:rgba(165,180,252,.45);flex-shrink:0;cursor:pointer;transition:all .2s}
.fltr-btn.active{background:rgba(99,102,241,.12);border-color:rgba(99,102,241,.3);color:#a5b4fc}
.fltr-btn .fc{font-size:9px;opacity:.6;margin-left:3px}

/* ═══ QUICK FILTER PILLS ═══ */
.qfltr-row{display:flex;gap:5px;padding:6px 16px 0;overflow-x:auto;-ms-overflow-style:none;scrollbar-width:none}
.qfltr-row::-webkit-scrollbar{display:none}
.qfltr-pill{padding:4px 10px;border-radius:16px;font-size:9px;font-weight:700;white-space:nowrap;flex-shrink:0;display:flex;align-items:center;gap:3px;cursor:pointer;background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.15);color:rgba(165,180,252,.5);transition:all .2s}
.qfltr-pill.active{background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.35);color:#a5b4fc}
.qfltr-pill svg{opacity:.5}
/* Signal pills with colors */
.qfltr-pill.sig-red{background:rgba(239,68,68,.06);border-color:rgba(239,68,68,.15);color:rgba(248,113,113,.7)}
.qfltr-pill.sig-yellow{background:rgba(251,191,36,.06);border-color:rgba(251,191,36,.15);color:rgba(251,191,36,.7)}
.qfltr-pill.sig-green{background:rgba(34,197,94,.06);border-color:rgba(34,197,94,.15);color:rgba(134,239,172,.7)}
.qfltr-pill.sig-purple{background:rgba(192,132,252,.06);border-color:rgba(192,132,252,.15);color:rgba(216,180,254,.7)}
.qfltr-pill.sig-orange{background:rgba(251,146,60,.06);border-color:rgba(251,146,60,.15);color:rgba(251,146,60,.7)}

/* ═══ INDIGO SEPARATOR ═══ */
.indigo-sep{height:1px;background:linear-gradient(to right,transparent,rgba(99,102,241,.2),transparent);margin:14px 16px}

/* ═══ ADD SECTION ═══ */
.add-sec{padding:0 16px 8px}
.add-sec-hdr{display:flex;align-items:center;gap:6px;margin-bottom:10px}
.add-sec-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#818cf8}
.add-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.add-btn{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 4px 12px;border-radius:14px;background:rgba(15,15,40,.75);border:1px solid rgba(99,102,241,.08);cursor:pointer;position:relative}
.add-btn span{font-size:10px;font-weight:600;color:#9ca3af}
.add-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center}
.add-ai .add-icon{background:linear-gradient(135deg,rgba(99,102,241,.15),rgba(99,102,241,.05));border:1px solid rgba(99,102,241,.25)}
.add-manual .add-icon{background:linear-gradient(135deg,rgba(34,197,94,.12),rgba(34,197,94,.04));border:1px solid rgba(34,197,94,.2)}
.add-scan .add-icon{background:linear-gradient(135deg,rgba(251,191,36,.12),rgba(251,191,36,.04));border:1px solid rgba(251,191,36,.2)}
.add-file .add-icon{background:linear-gradient(135deg,rgba(192,132,252,.12),rgba(192,132,252,.04));border:1px solid rgba(192,132,252,.2)}

/* ═══ SIGNALS ═══ */
.signals-sec{padding:12px 16px}
.signals-hdr{display:flex;align-items:center;gap:6px;margin-bottom:8px}
.signals-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#818cf8}
.signals-count{font-size:9px;color:rgba(165,180,252,.3);font-weight:600}
.sig-group{margin-bottom:14px}
.sig-group-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;margin:0 0 6px;padding-left:2px}
.sig-group-label.sg-red{color:rgba(248,113,113,.6)}
.sig-group-label.sg-yellow{color:rgba(251,191,36,.5)}
.sig-group-label.sg-green{color:rgba(134,239,172,.5)}
.sig-group-label.sg-purple{color:rgba(216,180,254,.5)}
.sig-group-label.sg-blue{color:rgba(165,180,252,.4)}
.sig-group-label.sg-orange{color:rgba(251,146,60,.5)}
.sig-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;background:rgba(15,15,40,.7);cursor:pointer;margin-bottom:6px}
.sig-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.sig-info{flex:1}
.sig-label{font-size:11px;font-weight:700}
.sig-desc{font-size:10px;color:#9ca3af;margin-top:1px}
.sig-action{font-size:10px;color:#818cf8;font-weight:600;flex-shrink:0}
.sig-card.s-red{border:1px solid rgba(239,68,68,.12)}.sig-card.s-red .sig-dot{background:#ef4444;box-shadow:0 0 6px rgba(239,68,68,.5);animation:pulseR 2s infinite}.sig-card.s-red .sig-label{color:#fca5a5}
.sig-card.s-yellow{border:1px solid rgba(251,191,36,.1)}.sig-card.s-yellow .sig-dot{background:#fbbf24}.sig-card.s-yellow .sig-label{color:#fde68a}
.sig-card.s-green{border:1px solid rgba(34,197,94,.1)}.sig-card.s-green .sig-dot{background:#4ade80}.sig-card.s-green .sig-label{color:#86efac}
.sig-card.s-purple{border:1px solid rgba(192,132,252,.1)}.sig-card.s-purple .sig-dot{background:#c084fc}.sig-card.s-purple .sig-label{color:#d8b4fe}
.sig-card.s-blue{border:1px solid rgba(99,102,241,.1)}.sig-card.s-blue .sig-dot{background:#818cf8}.sig-card.s-blue .sig-label{color:#a5b4fc}
.sig-card.s-orange{border:1px solid rgba(251,146,60,.1)}.sig-card.s-orange .sig-dot{background:#fb923c}.sig-card.s-orange .sig-label{color:#fdba74}
@keyframes pulseR{0%,100%{opacity:1}50%{opacity:.4}}

/* ═══ PRODUCTS LIST HEADER ═══ */
.prod-hdr{display:flex;align-items:center;gap:10px;padding:10px 16px;position:sticky;top:52px;z-index:9;background:rgba(3,7,18,.92);backdrop-filter:blur(12px)}
.prod-back{width:30px;height:30px;border-radius:10px;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.12);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0}
.prod-title{font-size:14px;font-weight:800;color:#e2e8f0;font-family:'Montserrat',system-ui;flex:1}
.prod-cnt{font-size:10px;color:rgba(165,180,252,.5);font-weight:600;flex-shrink:0}
.prod-sort{width:30px;height:30px;border-radius:10px;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.12);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0}
.active-chips{display:flex;gap:5px;padding:6px 16px 0;flex-wrap:wrap}
.act-chip{display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:8px;font-size:10px;font-weight:600;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.25);color:#a5b4fc}
.act-chip .chip-x{width:14px;height:14px;border-radius:50%;background:rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:8px;color:#818cf8;font-weight:800}

/* ═══ FLOATING AI BUTTON ═══ */






/* ═══ BOTTOM NAV SVG ═══ */







/* ═══ BOTTOM NAV — matching chat.php ═══ */
.bottom-nav {
    position: fixed; bottom: 0; left: 0; right: 0;
    height: 56px; background: rgba(3,7,18,0.95);
    backdrop-filter: blur(15px); border-top: 0.5px solid rgba(99,102,241,0.2);
    display: flex; z-index: 100;
}
.bottom-nav-tab {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 3px;
    font-size: 9px; font-weight: 600; color: rgba(165,180,252,0.4);
    text-decoration: none;
}
.bottom-nav-tab.active { color: #c7d2fe; }
.bottom-nav-tab svg { width: 18px; height: 18px; }

/* ═══ FLOATING AI BUTTON — matching chat.php ═══ */
.ai-float-btn {
    position: fixed; bottom: 62px; left: 50%; transform: translateX(-50%);
    display: flex; align-items: center; gap: 12px;
    padding: 10px 24px; border-radius: 20px;
    background: rgba(15,15,40,0.75); border: 1px solid rgba(99,102,241,0.2);
    cursor: pointer; backdrop-filter: blur(12px); z-index: 41; white-space: nowrap;
}
.ai-float-btn span { font-size: 13px; font-weight: 600; color: #a5b4fc; }
.ai-waves { display: flex; align-items: flex-end; gap: 2px; height: 18px; }
.ai-wave-bar { width: 3px; border-radius: 2px; background: currentColor; animation: wave-anim 1s ease-in-out infinite; }
@keyframes wave-anim { 0%, 100% { transform: scaleY(0.35); } 50% { transform: scaleY(1); } }

</style>
</head>
<body>

<div class="toast-c" id="toasts"></div>

<!-- ═══ VOICE OVERLAY (sale.php rec-ov/rec-box) ═══ -->
<div class="rec-ov" id="recOv">
    <div class="rec-box">
        <div class="rec-status">
            <div class="rec-dot" id="recDot"></div>
            <span class="rec-label recording" id="recLabel">● ЗАПИСВА</span>
        </div>
        <div class="rec-transcript empty" id="recTranscript">Слушам...</div>
        <div class="rec-hint" id="recHint">Кажете артикул или команда</div>
        <div class="rec-actions">
            <button class="rec-btn-cancel" id="recCancel">Затвори</button>
            <button class="rec-btn-send" id="recSend" disabled>🎤 Изпрати →</button>
        </div>
    </div>
</div>

<!-- ═══ CAMERA OVERLAY ═══ -->
<div class="camera-ov" id="cameraOv">
    <video class="camera-video" id="camVideo" playsinline autoplay></video>
    <div class="scan-line" id="scanLine" style="display:none"></div>
    <canvas id="camCanvas" style="display:none"></canvas>
    <div class="cam-controls">
        <button class="cam-btn close-cam" onclick="closeCamera()">✕</button>
        <button class="cam-btn capture" id="captureBtn" onclick="capturePhoto()" style="display:none">📷</button>
    </div>
</div>

<!-- ═══ MAIN WRAP ═══ -->
<div class="main-wrap">

    <!-- ═══ HEADER matching chat.php ═══ -->
    <div class="top-header">
        <div class="hdr-row1">
            <div class="hdr-logo">RUNMYSTORE.AI</div>
            <div class="hdr-right">
                <?php if (count($stores) > 1): ?>
                <select class="store-select" id="storeSelect" onchange="switchStore(this.value)">
                    <?php foreach ($stores as $st): ?>
                    <option value="<?= $st['id'] ?>" <?= $st['id'] == $store_id ? 'selected' : '' ?>><?= htmlspecialchars($st['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <div class="hdr-badge"><?= strtoupper(htmlspecialchars($tenant['plan'] ?? 'FREE')) ?></div>
                <a href="settings.php" class="hdr-ico"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></a>
                <a href="logout.php" class="hdr-ico"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
            </div>
        </div>
        <div class="hdr-row2">
            <div class="hdr-page-title">Артикули</div>
            <div class="hdr-count" id="hdrCount">— артикула</div>
        </div>
    </div>

    <!-- ═══ SCREEN: HOME ═══ -->
    <section id="scrHome" class="screen-section active">

        <!-- ТЪРСЕНЕ -->
        <div class="new-search-sec">
            <div class="new-search-bar" onclick="focusSearch()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <span class="new-search-ph" id="searchPh">Търси по име, код, баркод, цена...</span>
                <div class="new-search-mic" onclick="event.stopPropagation();openVoiceSearch()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><rect x="9" y="1" width="6" height="12" rx="3"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
                </div>
            </div>
            <div class="new-info-btn" onclick="toggleInfoPanel()"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></div>
        </div>

        <!-- CASCADE: ДОСТАВЧИЦИ → КАТЕГОРИИ -->
        <div class="fltr-label"><span>Доставчици</span><div class="fltr-info" onclick="showWizInfo('supplier')"><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></div></div>
        <div class="fltr-row" id="supFilterRow">
            <div class="fltr-btn active" data-sup="0" onclick="setCascadeSup(0,this)">Всички</div>
            <?php foreach ($all_suppliers as $sup): ?>
            <div class="fltr-btn" data-sup="<?= $sup['id'] ?>" onclick="setCascadeSup(<?= $sup['id'] ?>,this)"><?= htmlspecialchars($sup['name']) ?></div>
            <?php endforeach; ?>
        </div>

        <div class="fltr-label"><span>Категории</span><span class="fltr-hint" id="catHint">глобални</span><div class="fltr-info" onclick="showWizInfo('category')"><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></div></div>
        <div class="fltr-row" id="catFilterRow">
            <div class="fltr-btn active" data-cat="0" onclick="setCascadeCat(0,this)">Всички</div>
            <!-- Populated dynamically by JS based on selected supplier -->
        </div>

      <!-- БЪРЗИ ФИЛТРИ — ще се добавят в S42 с реални dropdown-ове -->
        <!-- БУТОН ТЪРСИ -->
<div style="padding:14px 16px 0">
    <div class="abtn primary" onclick="goFilteredList()" style="font-size:14px;padding:13px;border-radius:14px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        Търси артикули
    </div>
</div>
        <div class="indigo-sep"></div>

        <!-- ДОБАВИ НОВ АРТИКУЛ -->
        <?php if ($can_add): ?>
        <div class="add-sec">
            <div class="add-sec-hdr"><span class="add-sec-title">Добави нов артикул</span><div class="fltr-info" onclick="showWizInfo('photo')"><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></div></div>
            <div class="add-grid">
                <div class="add-btn add-ai" onclick="openVoiceWizard()">
                    <div class="add-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><rect x="9" y="1" width="6" height="12" rx="3"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg></div>
                    <span style="color:#a5b4fc">AI</span>
                </div>
                <div class="add-btn add-manual" onclick="openManualWizard()">
                    <div class="add-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div>
                    <span>Ръчно</span>
                </div>
                <div class="add-btn add-scan" onclick="openCamera('scan')">
                    <div class="add-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="4" y1="12" x2="20" y2="12"/></svg></div>
                    <span>Скан</span>
                </div>
                <div class="add-btn add-file" onclick="openCSVImport()">
                    <div class="add-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c084fc" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div>
                    <span>Файл</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="indigo-sep"></div>

        <!-- СИГНАЛИ -->
        <div class="signals-sec">
            <div class="signals-hdr"><span class="signals-title">Сигнали</span><span class="signals-count" id="signalsCount"></span><div class="fltr-info" onclick="showWizInfo('description')"><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></div></div>
            <div id="signalsList"></div>
        </div>

    </section>

    <!-- ═══ SCREEN: SUPPLIERS (preserved) ═══ -->
    <section id="scrSuppliers" class="screen-section">
        <div class="sec-title">Доставчици</div>
        <div class="swipe-row" id="supCards"></div>
    </section>

    <!-- ═══ SCREEN: CATEGORIES (preserved) ═══ -->
    <section id="scrCategories" class="screen-section">
        <div class="sec-title">Категории</div>
        <div id="catContent" style="padding:0 16px"></div>
    </section>

    <!-- ═══ SCREEN: PRODUCTS ═══ -->
    <section id="scrProducts" class="screen-section">
        <!-- Back + title -->
        <div class="prod-hdr">
            <div class="prod-back" onclick="goScreen('home')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg></div>
            <div class="prod-title" id="prodTitle">Артикули</div>
            <div class="prod-cnt" id="prodCnt"></div>
            <div class="prod-sort" onclick="toggleSort()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="9" y2="18"/></svg></div>
            <div class="sort-dd" id="sortDD">
                <div class="sort-opt active" data-sort="name" onclick="setSort('name')">Име А-Я</div>
                <div class="sort-opt" data-sort="price_asc" onclick="setSort('price_asc')">Цена нагоре</div>
                <div class="sort-opt" data-sort="price_desc" onclick="setSort('price_desc')">Цена надолу</div>
                <div class="sort-opt" data-sort="stock_asc" onclick="setSort('stock_asc')">Наличност нагоре</div>
                <div class="sort-opt" data-sort="stock_desc" onclick="setSort('stock_desc')">Наличност надолу</div>
                <div class="sort-opt" data-sort="newest" onclick="setSort('newest')">Най-нови</div>
            </div>
        </div>
        <!-- Active filters chips -->
        <div class="active-chips" id="activeChips"></div>
        <!-- Subcategory row (appears when category selected) -->
        <div class="fltr-label" id="subcatLabel" style="display:none"><span>Подкатегория</span></div>
        <div class="fltr-row" id="subcatFilterRow" style="display:none"></div>
        <!-- Quick filters -->
        <div class="fltr-label"><span>Филтри</span></div>
        <div class="qfltr-row">
            <div class="qfltr-pill" onclick="openQuickFilter('price')"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/></svg>Цена</div>
            <div class="qfltr-pill" onclick="openQuickFilter('stock')"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>Наличност</div>
            <?php if ($can_see_margin): ?><div class="qfltr-pill" onclick="openQuickFilter('margin')"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m16 12-4-4-4 4"/><path d="M12 16V8"/></svg>Марж</div><?php endif; ?>
            <div class="qfltr-pill" onclick="openQuickFilter('date')"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>Дата</div>
        </div>
        <!-- Signal filter pills -->
        <div class="fltr-label"><span>По сигнал</span></div>
        <div class="qfltr-row" id="signalFilterRow"></div>
        <div class="indigo-sep"></div>
        <!-- Product list -->
        <div id="prodList" style="padding:0 12px;margin-top:6px"></div>
        <div id="prodPag" class="pagination"></div>
    </section>

</div><!-- /main-wrap -->



<!-- ═══ INFO AI PANEL ═══ -->
<div class="info-panel-ov" id="infoPanelOv" onclick="closeInfoPanel()"></div>
<div class="info-panel" id="infoPanel">
    <div class="info-panel-hdr">
        <h3>✦ Помощ — Артикули</h3>
        <button class="info-panel-close" onclick="closeInfoPanel()">✕</button>
    </div>
    <div class="info-panel-body" id="infoPanelBody"></div>
    <div class="info-free-wrap">
        <button class="info-free-btn" onclick="openInfoFreeChat()">🎤 Питай AI свободно</button>
    </div>
</div>


<!-- Quick Actions now integrated into home screen add-sec -->


<!-- ═══ FLOATING AI BUTTON ═══ -->
<div class="ai-float-btn" id="aiFloatBtn" onclick="openAIChatOverlay()">
    <div class="ai-waves">
      <div class="ai-wave-bar" style="color:#6366f1;height:18px;animation-delay:0s"></div>
      <div class="ai-wave-bar" style="color:#818cf8;height:18px;animation-delay:.15s"></div>
      <div class="ai-wave-bar" style="color:#a5b4fc;height:18px;animation-delay:.3s"></div>
      <div class="ai-wave-bar" style="color:#818cf8;height:18px;animation-delay:.45s"></div>
      <div class="ai-wave-bar" style="color:#6366f1;height:18px;animation-delay:.6s"></div>
    </div>
    <span>Попитай AI</span>
</div>

<!-- S43: Detail drawer (was missing) -->
<div class="drawer-ov" id="detailOv" onclick="closeDrawer('detail')"></div>
<div class="drawer" id="detailDr" style="max-height:92vh">
    <div class="drawer-handle"></div>
    <div class="drawer-hdr"><h3 id="detailTitle">Артикул</h3><button class="drawer-close" onclick="closeDrawer('detail')">✕</button></div>
    <div id="detailBody" style="padding:0 4px 20px"></div>
</div>

<div class="drawer-ov" id="aiOv" onclick="closeDrawer('ai')"></div>
<div class="drawer" id="aiDr"><div class="drawer-handle"></div><div class="drawer-hdr"><h3>✦ AI Анализ</h3><button class="drawer-close" onclick="closeDrawer('ai')">✕</button></div><div id="aiBody"></div></div>

<div class="drawer-ov" id="filterOv" onclick="closeDrawer('filter')"></div>
<div class="drawer" id="filterDr"><div class="drawer-handle"></div><div class="drawer-hdr"><h3>Филтри</h3><button class="drawer-close" onclick="closeDrawer('filter')">✕</button></div>
    <div style="padding-bottom:20px">
        <div style="margin-bottom:14px"><div style="font-size:12px;font-weight:700;color:var(--indigo-300);margin-bottom:6px">Категория</div><div class="filter-chips" id="fCats"><?php foreach ($all_categories as $cat): ?><button class="f-chip" data-cat="<?= $cat['id'] ?>" onclick="toggleFChip(this)"><?= htmlspecialchars($cat['name']) ?></button><?php endforeach; ?></div></div>
        <div style="margin-bottom:14px"><div style="font-size:12px;font-weight:700;color:var(--indigo-300);margin-bottom:6px">Доставчик</div><div class="filter-chips" id="fSups"><?php foreach ($all_suppliers as $sup): ?><button class="f-chip" data-sup="<?= $sup['id'] ?>" onclick="toggleFChip(this)"><?= htmlspecialchars($sup['name']) ?></button><?php endforeach; ?></div></div>
        <button class="abtn primary" onclick="applyFilters()" style="margin-top:10px">Приложи</button>
        <button class="abtn" onclick="clearFilters()" style="margin-top:6px">Изчисти</button>
    </div>
</div>

<div class="drawer-ov" id="studioOv" onclick="closeDrawer('studio')"></div>
<div class="drawer" id="studioDr"><div class="drawer-handle"></div><div class="drawer-hdr"><h3>✨ AI Image Studio</h3><button class="drawer-close" onclick="closeDrawer('studio')">✕</button></div><div id="studioBody"></div></div>

<div class="drawer-ov" id="labelsOv" onclick="closeDrawer('labels')"></div>
<div class="drawer" id="labelsDr"><div class="drawer-handle"></div><div class="drawer-hdr"><h3>🏷 Етикети и наличност</h3><button class="drawer-close" onclick="closeDrawer('labels')">✕</button></div><div id="labelsBody"></div></div>

<div class="drawer-ov" id="csvOv" onclick="closeDrawer('csv')"></div>
<div class="drawer" id="csvDr"><div class="drawer-handle"></div><div class="drawer-hdr"><h3>📄 Импорт от файл</h3><button class="drawer-close" onclick="closeDrawer('csv')">✕</button></div><div id="csvBody"></div></div>

<!-- ═══ MANUAL WIZARD MODAL ═══ -->
<div class="modal-ov" id="wizModal">
    <div class="modal-hdr">
        <button onclick="closeWizard()" style="background:transparent;border:none;color:var(--text-secondary);font-size:18px;cursor:pointer">✕</button>
        <h2 id="wizTitle">Нов артикул</h2>
        <div style="width:28px"></div>
    </div>
    <div class="wiz-steps" id="wizSteps"></div>
    <div class="wiz-label" id="wizLabel"></div>
    <div class="modal-body" id="wizBody"></div>
</div>

<!-- Hidden file inputs -->
<input type="file" id="photoInput" accept="image/*" capture="environment">
<input type="file" id="filePickerInput" accept="image/*,*/*">
<!-- S43: removed dup filePickerInput -->
<input type="file" id="csvInput" accept=".csv,.xlsx,.xls">

<script>
// ═══ PHP → JS CONFIG ═══
const CFG = {
    storeId: <?= (int)$store_id ?>,
    canAdd: <?= $can_add ? 'true' : 'false' ?>,
    canSeeCost: <?= $can_see_cost ? 'true' : 'false' ?>,
    canSeeMargin: <?= $can_see_margin ? 'true' : 'false' ?>,
    skipWholesale: <?= $skip_wholesale ? 'true' : 'false' ?>,
    aiBg: <?= $ai_bg ?>,
    aiTryon: <?= $ai_tryon ?>,
    currency: '<?= $currency ?>',
    lang: '<?= $lang ?>',
    businessType: '<?= htmlspecialchars($business_type) ?>',
    suppliers: <?= json_encode($all_suppliers, JSON_UNESCAPED_UNICODE) ?>,
    categories: <?= json_encode($all_categories, JSON_UNESCAPED_UNICODE) ?>,
    units: <?= json_encode($onboarding_units, JSON_UNESCAPED_UNICODE) ?>,
    colors: <?= json_encode($COLOR_PALETTE, JSON_UNESCAPED_UNICODE) ?>,
};
window._bizVariants=<?= json_encode($bizVars ?: [], JSON_UNESCAPED_UNICODE) ?>;
window._sizePresets={clothing:['XS','S','M','L','XL','2XL','3XL','4XL'],shoes:['36','37','38','39','40','41','42','43','44','45','46'],clothing_eu:['34','36','38','40','42','44','46','48','50','52','54','56'],kids:['80','86','92','98','104','110','116','122','128','134','140','146','152','158','164'],pants:['W28','W29','W30','W31','W32','W33','W34','W36','W38'],rings:['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'],socks:['35-38','39-42','43-46'],hats:['S/M','L/XL','One Size'],bra:['70A','70B','75A','75B','75C','80A','80B','80C','80D','85B','85C','85D']};

// ═══════════════════════════════════════════════════════════
// PART 3: JS CORE — Navigation, Home, Search, Drawers, Camera
// ═══════════════════════════════════════════════════════════

// ─── STATE ───
const S = {
    screen: 'home', sort: 'name', filter: 'all', page: 1,
    homeTab: 'all', homePage: 1,
    supId: null, catId: null,
    searchText: '', searchTO: null,
    cameraMode: null, cameraStream: null, barcodeDetector: null, barcodeInterval: null,
    recognition: null, isListening: false, lastTranscript: '',
    wizStep: 0, wizData: {}, wizType: null, wizEditId: null,
    aiWizMode: false, aiWizConversation: [], aiWizCollected: {},
};

// ─── UTILS ───
function esc(s){if(!s)return'';const d=document.createElement('div');d.textContent=s;return d.innerHTML}
function fmtPrice(v){if(v==null)return'—';return parseFloat(v).toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2})+' €'}
function fmtNum(v){if(v==null)return'—';return parseInt(v).toLocaleString('de-DE')}
function stockClass(q,m){if(q<=0)return'out';if(m>0&&q<=m)return'low';return'ok'}
function stockBar(q,m){if(q<=0)return'red';if(m>0&&q<=m)return'yellow';return'green'}

function showToast(msg, type=''){
    const c=document.getElementById('toasts');
    const e=document.createElement('div');
    e.className='toast '+(type||'');
    e.innerHTML=(type==='success'?'✓ ':type==='error'?'✕ ':'ℹ ')+esc(msg);
    c.appendChild(e);
    requestAnimationFrame(()=>e.classList.add('show'));
    setTimeout(()=>{e.classList.remove('show');setTimeout(()=>e.remove(),300)},3000);
}

async function api(url, opts={}){
    try{const r=await fetch(url, opts);return await r.json()}
    catch(e){console.error(e);showToast('Мрежова грешка','error');return null}
}

function productCardHTML(p){
    const q=p.store_stock||p.qty||0;
    const sc=stockClass(q,p.min_quantity||0);
    const bc=stockBar(q,p.min_quantity||0);
    const thumb=p.image_url?`<img src="${p.image_url}">`:`<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="rgba(99,102,241,0.3)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>`;
    const disc=(p.discount_pct&&p.discount_pct>0)?`<div class="p-discount">-${p.discount_pct}%</div>`:'';
    return `<div class="p-card" onclick="openProductDetail(${p.id})">
        <div class="stock-bar ${bc}"></div>
        <div class="p-thumb">${thumb}</div>
        <div class="p-info"><div class="p-name">${esc(p.name)}</div><div class="p-meta">${p.code?`<span>${esc(p.code)}</span>`:''}${p.supplier_name?`<span>${esc(p.supplier_name)}</span>`:''}</div></div>
        <div class="p-right"><div class="p-price">${fmtPrice(p.retail_price)}</div><div class="p-stock ${sc}">${q} ${p.unit||'бр.'}</div></div>${disc}</div>`;
}

// ─── NAVIGATION ───
function goScreen(scr, params={}){
    S.screen=scr; S.supId=params.sup||null; S.catId=params.cat||null; S.page=1;
    document.querySelectorAll('.screen-section').forEach(el=>el.classList.remove('active'));
    const map={home:'scrHome',suppliers:'scrSuppliers',categories:'scrCategories',products:'scrProducts'};
    document.getElementById(map[scr])?.classList.add('active');
    document.querySelectorAll('.sn-btn').forEach(b=>b.classList.toggle('active',b.dataset.scr===scr));
    loadScreen();
}
function switchStore(id){CFG.storeId=parseInt(id);loadScreen()}

// S43: goScreenWithHistory — was missing
function goScreenWithHistory(scr, params={}) {
    history.pushState({scr:scr, ...params}, '', '#'+scr);
    goScreen(scr, params);
}


function loadScreen(){
    switch(S.screen){
        case'home':loadHome();break;
        case'suppliers':loadSuppliers();break;
        case'categories':loadCategories();break;
        case'products':loadProducts();break;
    }
}

// ─── HOME SCREEN ───
async function loadHome(){
    // New design: cascade filters + signals
    const d=await api(`products.php?ajax=home_stats&store_id=${CFG.storeId}`);
    if(d && d.counts){
        document.getElementById('hdrCount').textContent=(d.counts.total_products||0)+' артикула';
    }
    // Init cascade categories (all global)
    setCascadeSup(0, null);
    // Load signals
    loadSignals();
}

function renderCollapse(id,icon,title,items,renderFn){
    const el=document.getElementById(id);
    if(!items||!items.length){el.classList.add('hidden');return}
    el.classList.remove('hidden');
    el.innerHTML=`<div class="collapse-hdr open" onclick="toggleCollapse(this)"><div class="ch-left"><span class="ch-icon">${icon}</span><span class="ch-title">${title}</span><span class="ch-count">${items.length}</span></div><span class="ch-arrow">▼</span></div><div class="collapse-body open"><div class="collapse-inner">${items.map((p,i)=>renderFn(p,i)).join('')}</div></div>`;
}

function toggleCollapse(hdr){
    hdr.classList.toggle('open');
    hdr.nextElementSibling.classList.toggle('open');
}

async function loadHomeProducts(){
    const f=S.homeTab==='all'?'':`&filter=${S.homeTab}`;
    const d=await api(`products.php?ajax=products&store_id=${CFG.storeId}${f}&page=${S.homePage}&sort=${S.sort}`);
    if(!d)return;
    const el=document.getElementById('homeList');
    el.innerHTML=d.products.length===0?`<div class="empty-st"><div class="es-icon">📦</div><div class="es-text">Няма артикули</div></div>`:d.products.map(productCardHTML).join('');
    renderPag('homePag',d.page,d.pages,p=>{S.homePage=p;loadHomeProducts()});
}

function setHomeTab(t,btn){
    S.homeTab=t;S.homePage=1;
    document.querySelectorAll('.tabs-row .tab-pill').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    loadHomeProducts();
}

// ─── SUPPLIERS ───
async function loadSuppliers(){
    const d=await api(`products.php?ajax=suppliers&store_id=${CFG.storeId}`);
    if(!d)return;
    const el=document.getElementById('supCards');
    if(!d.length){el.innerHTML=`<div class="empty-st" style="width:100%"><div class="es-icon">📦</div><div class="es-text">Няма доставчици</div></div>`;return}
    el.innerHTML=d.map(s=>{
        const ok=s.product_count-s.low_count-s.out_count;
        return`<div class="sup-card" onclick="goScreenWithHistory('categories',{sup:${s.id}})"><div class="sc-name">${esc(s.name)}</div><div class="sc-count">${s.product_count} арт. · ${fmtNum(s.total_stock)} бр.</div><div class="sc-badges">${ok>0?`<span class="sc-badge ok">✓ ${ok}</span>`:''}${s.low_count>0?`<span class="sc-badge low">↓ ${s.low_count}</span>`:''}${s.out_count>0?`<span class="sc-badge out">✕ ${s.out_count}</span>`:''}</div><div class="sc-arrow">›</div></div>`;
    }).join('');
}

// ─── CATEGORIES ───
async function loadCategories(){
    const sp=S.supId?`&sup=${S.supId}`:'';
    const d=await api(`products.php?ajax=categories&store_id=${CFG.storeId}${sp}`);
    if(!d)return;
    const el=document.getElementById('catContent');
    if(S.supId){
        el.innerHTML=d.length===0?`<div class="empty-st"><div class="es-text">Няма категории</div></div>`:
        `<div style="background:var(--bg-card);border-radius:12px;border:1px solid var(--border-subtle);overflow:hidden">`+
        d.map(c=>`<div class="cat-item" onclick="goScreenWithHistory('products',{sup:${S.supId},cat:${c.id}})"><div style="display:flex;align-items:center;gap:10px"><div style="width:32px;height:32px;border-radius:8px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;font-size:14px">🏷</div><div><div style="font-size:13px;font-weight:600">${esc(c.name)}</div><div style="font-size:10px;color:var(--text-secondary)">${c.product_count} арт. · ${fmtNum(c.total_stock)} бр.</div></div></div><span style="color:var(--text-secondary)">›</span></div>`).join('')+`</div>`;
    }else{
        el.innerHTML=d.length===0?`<div class="empty-st"><div class="es-text">Няма категории</div></div>`:
        `<div class="swipe-row">`+d.map(c=>`<div class="cat-card" onclick="goScreenWithHistory('products',{cat:${c.id}})"><div style="font-size:13px;font-weight:600">${esc(c.name)}</div><div style="font-size:10px;color:var(--text-secondary);margin-top:3px">${c.product_count} арт.${c.supplier_count?' · '+c.supplier_count+' дост.':''}</div></div>`).join('')+`</div>`;
    }
}

// ─── PRODUCTS LIST ───
async function loadProducts(){
    // Update title and active chips
    let titleParts = [];
    let chipsHtml = '';
    if(S.catId){
        const cat = CFG.categories.find(c=>c.id===S.catId);
        if(cat) { titleParts.push(cat.name); chipsHtml += `<div class="act-chip">${esc(cat.name)} <div class="chip-x" onclick="S.catId=null;loadProducts()">x</div></div>`; }
    }
    if(S.supId){
        const sup = CFG.suppliers.find(s=>s.id===S.supId);
        if(sup) { titleParts.push(sup.name); chipsHtml += `<div class="act-chip">${esc(sup.name)} <div class="chip-x" onclick="S.supId=null;loadProducts()">x</div></div>`; }
    }
    const titleEl = document.getElementById('prodTitle');
    if(titleEl) titleEl.textContent = titleParts.length ? titleParts.join(' · ') : 'Артикули';
    const chipsEl = document.getElementById('activeChips');
    if(chipsEl) chipsEl.innerHTML = chipsHtml;

    // Load subcategories if category selected
    if(S.catId) loadSubcategories(S.catId);
    else { const sr=document.getElementById('subcatFilterRow'); if(sr)sr.style.display='none'; const sl=document.getElementById('subcatLabel'); if(sl)sl.style.display='none'; }

    let p=`store_id=${CFG.storeId}&sort=${S.sort}&page=${S.page}`;
    if(S.supId)p+=`&sup=${S.supId}`;
    if(S.catId)p+=`&cat=${S.catId}`;
    if(S.filter!=='all')p+=`&filter=${S.filter}`;
    // S43: Quick filter params
    if(_qfState.price_min)p+='&price_min='+_qfState.price_min;
    if(_qfState.price_max)p+='&price_max='+_qfState.price_max;
    if(_qfState.stock_min!==undefined&&_qfState.stock_min!=='')p+='&stock_min='+_qfState.stock_min;
    if(_qfState.stock_max!==undefined&&_qfState.stock_max!=='')p+='&stock_max='+_qfState.stock_max;
    if(_qfState.margin_min)p+='&margin_min='+_qfState.margin_min;
    if(_qfState.margin_max)p+='&margin_max='+_qfState.margin_max;
    if(_qfState.date_from)p+='&date_from='+_qfState.date_from;
    if(_qfState.date_to)p+='&date_to='+_qfState.date_to;
    const d=await api(`products.php?ajax=products&${p}`);
    if(!d)return;
    const cntEl = document.getElementById('prodCnt');
    if(cntEl) cntEl.textContent = d.total + ' артикула';
    document.getElementById('prodList').innerHTML=d.products.length===0?
        `<div class="empty-st"><div class="es-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="rgba(99,102,241,.3)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div><div class="es-text">Няма артикули</div></div>`:
        d.products.map(productCardHTML).join('');
    renderPag('prodPag',d.page,d.pages,pg=>{S.page=pg;loadProducts()});
}

// ─── PAGINATION ───
function renderPag(id,cur,tot,cb){
    const el=document.getElementById(id);
    if(tot<=1){el.innerHTML='';return}
    let h='';
    if(cur>1)h+=`<button class="pg-btn" data-p="${cur-1}">‹</button>`;
    for(let i=Math.max(1,cur-2);i<=Math.min(tot,cur+2);i++)
        h+=`<button class="pg-btn ${i===cur?'active':''}" data-p="${i}">${i}</button>`;
    if(cur<tot)h+=`<button class="pg-btn" data-p="${cur+1}">›</button>`;
    el.innerHTML=h;
    el.querySelectorAll('.pg-btn').forEach(b=>b.onclick=()=>cb(parseInt(b.dataset.p)));
}

// ─── SORT ───
function toggleSort(){document.getElementById('sortDD').classList.toggle('open')}
function setSort(s){
    S.sort=s;
    document.querySelectorAll('.sort-opt').forEach(o=>o.classList.toggle('active',o.dataset.sort===s));
    document.getElementById('sortDD').classList.remove('open');
    if(S.screen==='products')loadProducts();else loadHomeProducts();
}
document.addEventListener('click',e=>{if(!e.target.closest('.sort-wrap'))document.getElementById('sortDD')?.classList.remove('open')});

// ─── SEARCH ───
function focusSearch(){
    const text=prompt('Търси:','');
    if(text===null)return;
    S.searchText=text.trim();
    if(!S.searchText){updateSearchDisplay();loadScreen();return}
    updateSearchDisplay();doSearch(S.searchText);
}
function updateSearchDisplay(){
    const el=document.getElementById('searchPh');
    if(S.searchText)el.textContent='🔍 '+S.searchText;
    else el.textContent='Търси по име, код, баркод, цена...';
}
async function doSearch(q){
    const d=await api(`products.php?ajax=search&q=${encodeURIComponent(q)}&store_id=${CFG.storeId}`);
    if(!d)return;
    // Always show search results in products screen
    if(S.screen!=='products') goScreen('products');
    document.getElementById('prodList').innerHTML=d.length===0?
        `<div class="empty-st"><div class="es-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="rgba(99,102,241,.3)" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div><div class="es-text">Нищо за "${esc(q)}"</div></div>`:
        d.map(p=>productCardHTML({...p,store_stock:p.total_stock})).join('');
}

function openVoiceSearch(){
    openVoice('Кажи какво търсиш',text=>{
        S.searchText=text;updateSearchDisplay();doSearch(text);
    });
}

// ─── DRAWERS ───
function openDrawer(name){
    history.pushState({drawer:name}, '', '#'+name);
    document.getElementById(name+'Ov')?.classList.add('open');
    document.getElementById(name+'Dr')?.classList.add('open');
    document.body.style.overflow='hidden';
}
function closeDrawer(name){
    document.getElementById(name+'Ov')?.classList.remove('open');
    document.getElementById(name+'Dr')?.classList.remove('open');
    document.body.style.overflow='';
}
// Swipe-to-close drawers
['detail','ai','filter','studio','labels','csv'].forEach(name=>{
    const dr=document.getElementById(name+'Dr');
    if(!dr)return;
    let sy=0,dy=0,drag=false;
    dr.addEventListener('touchstart',e=>{if(dr.scrollTop>5)return;sy=e.touches[0].clientY;drag=true},{passive:true});
    dr.addEventListener('touchmove',e=>{if(!drag)return;dy=e.touches[0].clientY-sy;if(dy>0)dr.style.transform=`translateY(${dy}px)`},{passive:true});
    dr.addEventListener('touchend',()=>{if(!drag)return;drag=false;if(dy>80)closeDrawer(name);dr.style.transform='';dy=0});
});


// S43: openCSVImport — was missing
function openCSVImport() {
    openDrawer('csv');
    document.getElementById('csvBody').innerHTML =
        '<div style="padding:16px;text-align:center">' +
        '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="1.5" style="margin-bottom:10px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>' +
        '<div style="font-size:14px;font-weight:600;margin-bottom:6px">Импорт от CSV файл</div>' +
        '<div style="font-size:11px;color:var(--text-secondary);margin-bottom:14px">Поддържа .csv — колони: Име, Код, Цена, Баркод, Категория, Доставчик</div>' +
        '<button class="abtn primary" onclick="document.getElementById(\'csvInput\').click()">📄 Избери файл</button></div>';
}

// S43: openLabels — was missing
async function openLabels(productId) {
    openDrawer('labels');
    document.getElementById('labelsBody').innerHTML = '<div style="text-align:center;padding:20px">Зареждам...</div>';
    const d = await api('products.php?ajax=export_labels&product_id='+productId+'&format=json');
    if (!d||!d.length) { document.getElementById('labelsBody').innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-secondary)">Няма вариации</div>'; return; }
    let h = '<div style="padding:0 8px">';
    d.forEach((v,i) => {
        h += '<div class="label-var"><div class="lv-name">'+esc(v.name)+'</div><div class="lv-code">'+esc(v.code||'')+(v.size?' · '+esc(v.size):'')+(v.color?' · '+esc(v.color):'')+'</div>';
        h += '<div class="lv-fields"><div class="lv-field"><label>Мин.кол.</label><input type="number" value="'+(v.min_quantity||0)+'" data-vid="'+v.id+'" data-field="min_quantity"></div>';
        h += '<div class="lv-field"><label>Наличност</label><input type="number" value="'+(v.stock||0)+'" disabled style="opacity:0.5"></div></div></div>';
    });
    h += '<div style="display:flex;gap:6px;margin-top:12px"><button class="abtn primary" onclick="saveLabelsFromDrawer('+productId+')">✓ Запази</button>';
    h += '<button class="abtn" onclick="location.href=\'products.php?ajax=export_labels&product_id='+productId+'&format=csv\'">📥 CSV</button></div></div>';
    document.getElementById('labelsBody').innerHTML = h;
}
async function saveLabelsFromDrawer(pid) {
    const inputs = document.querySelectorAll('#labelsBody [data-vid]');
    const variations = [];
    inputs.forEach(inp => { if(inp.dataset.field==='min_quantity') variations.push({id:parseInt(inp.dataset.vid),min_quantity:parseInt(inp.value)||0}); });
    const d = await api('products.php?ajax=save_labels',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({variations})});
    if (d?.ok) showToast('Запазено ✓','success');
    else showToast('Грешка','error');
}

// S43: openImageStudio — was missing
function openImageStudio(productId) {
    openDrawer('studio');
    document.getElementById('studioBody').innerHTML =
        '<div style="padding:12px">' +
        '<div style="text-align:center;margin-bottom:12px"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="1.5"><path d="M12 2L9 9H2l5.5 4-2 7L12 16l6.5 4-2-7L22 9h-7z"/></svg></div>' +
        '<div style="font-size:14px;font-weight:700;text-align:center;margin-bottom:4px">AI Image Studio</div>' +
        '<div style="font-size:11px;color:var(--text-secondary);text-align:center;margin-bottom:14px">Отвори артикула с бутон Редактирай → Снимка → AI Studio</div>' +
        '<div class="credits-bar"><div class="cr-item">Бял фон: <b>'+CFG.aiBg+'</b> кредита</div><div class="cr-sep"></div><div class="cr-item">AI Магия: <b>'+CFG.aiTryon+'</b> кредита</div></div>' +
        '</div>';
}

// ─── PRODUCT DETAIL ───
async function openProductDetail(id){
    openDrawer('detail');
    document.getElementById('detailBody').innerHTML='<div style="text-align:center;padding:20px"><div class="skeleton" style="width:60%;height:18px;margin:0 auto 10px"></div></div>';
    const d=await api(`products.php?ajax=product_detail&id=${id}`);
    if(!d||d.error){showToast('Грешка','error');closeDrawer('detail');return}
    const p=d.product;
    document.getElementById('detailTitle').textContent=p.name;
    let h='';
    if(p.image_url)h+=`<div style="text-align:center;margin-bottom:12px"><img src="${p.image_url}" style="max-width:180px;border-radius:12px;border:1px solid var(--border-subtle)"></div>`;
    h+=`<div class="d-row"><span class="d-label">Код</span><span class="d-value">${esc(p.code)||'—'}</span></div>`;
    h+=`<div class="d-row"><span class="d-label">Цена</span><span class="d-value">${fmtPrice(p.retail_price)}</span></div>`;
    if(CFG.canSeeCost)h+=`<div class="d-row"><span class="d-label">Доставна</span><span class="d-value">${fmtPrice(p.cost_price)}</span></div>`;
    h+=`<div class="d-row"><span class="d-label">Доставчик</span><span class="d-value">${esc(p.supplier_name)||'—'}</span></div>`;
    h+=`<div class="d-row"><span class="d-label">Категория</span><span class="d-value">${esc(p.category_name)||'—'}</span></div>`;
    if(p.description)h+=`<div class="d-row"><span class="d-label">Описание</span><span class="d-value" style="font-size:11px;max-width:60%;text-align:right">${esc(p.description)}</span></div>`;

    h+=`<div style="margin-top:12px;font-size:9px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin-bottom:5px">Наличност по обекти</div>`;
    d.stocks.forEach(s=>h+=`<div class="store-stock-row"><span style="font-size:12px">${esc(s.store_name)}</span><span style="font-size:13px;font-weight:700;color:${s.qty>0?'var(--success)':'var(--danger)'}">${s.qty} бр.</span></div>`);

    if(d.variations?.length>0){
        h+=`<div style="margin-top:12px;font-size:9px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin-bottom:5px">Вариации</div>`;
        d.variations.forEach(v=>h+=`<div class="p-card" onclick="openProductDetail(${v.id})" style="margin-bottom:3px"><div class="stock-bar ${stockBar(v.total_stock,0)}"></div><div class="p-info" style="margin-left:10px"><div class="p-name" style="font-size:11px">${esc(v.name)}</div><div class="p-meta">${v.size?`<span>${esc(v.size)}</span>`:''}${v.color?`<span>${esc(v.color)}</span>`:''}</div></div><div class="p-right"><div class="p-price">${fmtPrice(v.retail_price)}</div><div class="p-stock ${stockClass(v.total_stock,0)}">${v.total_stock} бр.</div></div></div>`);
    }

    h+=`<div class="abtn-grid" style="margin-top:14px">`;
    if(CFG.canAdd)h+=`<button class="abtn" onclick="editProduct(${p.id})">✏️ Редактирай</button>`;
    h+=`<button class="abtn" onclick="openAIAnalysis(${p.id})">✦ AI Съвет</button>`;
    h+=`<button class="abtn" onclick="openImageStudio(${p.id})">✨ AI Снимка</button>`;
    h+=`<button class="abtn primary" onclick="location.href='sale.php?product=${p.id}'">💰 Продажба</button>`;
    h+=`<button class="abtn" onclick="openLabels(${p.id})">🏷 Етикет</button>`;
    h+=`</div>`;
    document.getElementById('detailBody').innerHTML=h;
}

// ─── AI ANALYSIS DRAWER ───
async function openAIAnalysis(id){
    closeDrawer('detail');
    setTimeout(()=>openDrawer('ai'),300);
    document.getElementById('aiBody').innerHTML='<div style="text-align:center;padding:20px">✦ Анализирам...</div>';
    const d=await api(`products.php?ajax=ai_analyze&id=${id}`);
    if(!d)return;
    let h='';
    if(!d.analysis.length)h=`<div class="ai-insight info"><span class="ai-icon">✓</span><span class="ai-text">Всичко е наред.</span></div>`;
    else d.analysis.forEach(a=>h+=`<div class="ai-insight ${a.severity}"><span class="ai-icon">${a.icon}</span><span class="ai-text">${a.text}</span></div>`);
    document.getElementById('aiBody').innerHTML=h;
}

// ─── FILTER ───
function toggleFChip(el){el.classList.toggle('sel')}
function applyFilters(){
    const cat=document.querySelector('#fCats .f-chip.sel');
    const sup=document.querySelector('#fSups .f-chip.sel');
    closeDrawer('filter');
    goScreenWithHistory('products',{sup:sup?.dataset.sup,cat:cat?.dataset.cat});
}
function clearFilters(){document.querySelectorAll('.f-chip').forEach(c=>c.classList.remove('sel'))}

// ─── CAMERA & BARCODE ───
async function openCamera(mode){
    S.cameraMode=mode;
    history.pushState({camera:true}, "", "#camera");
    document.getElementById('cameraOv').classList.add('open');
    try{
        const stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment',width:{ideal:1280}}});
        S.cameraStream=stream;
        document.getElementById('camVideo').srcObject=stream;
        if(mode==='scan'){
            document.getElementById('scanLine').style.display='block';
            document.getElementById('captureBtn').style.display='none';
            startBarcodeScanning();
        }else{
            document.getElementById('scanLine').style.display='none';
            document.getElementById('captureBtn').style.display='';
        }
    }catch(e){showToast('Камерата не е достъпна','error');closeCamera()}
}
function closeCamera(){
    document.getElementById('cameraOv').classList.remove('open');
    if(S.cameraStream){S.cameraStream.getTracks().forEach(t=>t.stop());S.cameraStream=null}
    if(S.barcodeInterval){clearInterval(S.barcodeInterval);S.barcodeInterval=null}
    document.getElementById('scanLine').style.display='none';
}
function startBarcodeScanning(){
    if(!('BarcodeDetector' in window)){showToast('Баркод скенерът не е поддържан','error');closeCamera();return}
    S.barcodeDetector=new BarcodeDetector({formats:['ean_13','ean_8','code_128','code_39','qr_code','upc_a','upc_e']});
    const vid=document.getElementById('camVideo');
    S.barcodeInterval=setInterval(async()=>{
        try{
            const codes=await S.barcodeDetector.detect(vid);
            if(codes.length>0){
                clearInterval(S.barcodeInterval);
                playBeep();
                const code=codes[0].rawValue;
                const d=await api(`products.php?ajax=barcode&code=${encodeURIComponent(code)}&store_id=${CFG.storeId}`);
                closeCamera();
                if(d&&!d.error)openProductDetail(d.id);
                else showToast(`Баркод ${code} не е намерен`);
            }
        }catch(e){}
    },300);
}
function capturePhoto(){document.getElementById('photoInput').click();closeCamera()}
function playBeep(){try{const c=new(window.AudioContext||window.webkitAudioContext)();const o=c.createOscillator();const g=c.createGain();o.connect(g);g.connect(c.destination);o.frequency.value=1200;g.gain.value=0.3;o.start();o.stop(c.currentTime+0.15)}catch(e){}}

// ─── VOICE OVERLAY (sale.php style) ───
function openVoice(hint, callback){
    const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(!SR){showToast('Гласовото не е поддържано','error');return}
    history.pushState({voice:true}, '', '#voice');

    const ov=document.getElementById('recOv');
    const dot=document.getElementById('recDot');
    const label=document.getElementById('recLabel');
    const transcript=document.getElementById('recTranscript');
    const hintEl=document.getElementById('recHint');
    const sendBtn=document.getElementById('recSend');

    dot.className='rec-dot';
    label.className='rec-label recording';
    label.textContent='● ЗАПИСВА';
    transcript.textContent='Слушам...';
    transcript.classList.add('empty');
    hintEl.textContent=hint||'Кажете артикул или команда';
    sendBtn.disabled=true;
    S.lastTranscript='';
    ov.classList.add('open');

    const rec=new SR();
    rec.lang='bg-BG';
    rec.continuous=false;
    rec.interimResults=false;
    S.recognition=rec;

    rec.onresult=(e)=>{
        S.lastTranscript=e.results[0][0].transcript;
        dot.classList.add('ready');
        label.className='rec-label ready';
        label.textContent='✓ ГОТОВО';
        transcript.textContent=S.lastTranscript;
        transcript.classList.remove('empty');
        sendBtn.disabled=false;
    };
    rec.onerror=()=>{closeVoice()};
    rec.onend=()=>{
        if(!S.lastTranscript){
            dot.classList.add('ready');
            label.className='rec-label';
            label.textContent='ЧАКАМ';label.style.color='var(--text-secondary)';
        }
    };

    // Send button
    sendBtn.onclick=()=>{closeVoice();if(S.lastTranscript&&callback)callback(S.lastTranscript)};
    document.getElementById('recCancel').onclick=()=>{if(rec)try{rec.abort()}catch(e){}closeVoice()};

    try{rec.start()}catch(e){closeVoice()}
}
function closeVoice(){
    if(S.recognition)try{S.recognition.stop()}catch(e){}
    S.recognition=null;
    document.getElementById('recOv').classList.remove('open');
    const label=document.getElementById('recLabel');
    if(label)label.style.color='';
}
// Tap backdrop = close
document.getElementById('recOv').addEventListener('click',e=>{if(e.target===e.currentTarget)closeVoice()});

// ─── SWIPE NAVIGATION — DISABLED ───
// Removed: swipe between pages was accidentally triggering on normal scroll



// ═══════════════════════════════════════════════════════════
// ═══════════════════════════════════════════════════════════
// CASCADE FILTERS + SIGNALS — New for redesign
// ═══════════════════════════════════════════════════════════

let _cascadeSup = 0;
let _cascadeCat = 0;
let _cascadeSubcat = 0;
let _signalsData = [];
let _qfState = {};

// ─── CASCADE: Supplier selected ───
async function setCascadeSup(supId, el) {
    _cascadeSup = supId;
    _cascadeCat = 0;
    _cascadeSubcat = 0;
    // Update active state
    document.querySelectorAll('#supFilterRow .fltr-btn').forEach(b => b.classList.toggle('active', parseInt(b.dataset.sup) === supId));
    // Reload categories for this supplier
    const catRow = document.getElementById('catFilterRow');
    catRow.innerHTML = '<div class="fltr-btn active" data-cat="0" onclick="setCascadeCat(0,this)">Всички</div>';
    const hint = document.getElementById('catHint');
    if (supId === 0) {
        hint.textContent = 'глобални';
        // Show all global categories (parent_id IS NULL)
        CFG.categories.filter(c => !c.parent_id).sort((a,b) => a.name.localeCompare(b.name,'bg')).forEach(c => {
            catRow.innerHTML += `<div class="fltr-btn" data-cat="${c.id}" onclick="setCascadeCat(${c.id},this)">${esc(c.name)}</div>`;
        });
    } else {
        const sup = CFG.suppliers.find(s => s.id === supId);
        hint.textContent = sup ? sup.name : '';
        // Fetch categories for this supplier
        const cats = await api('products.php?ajax=categories&store_id=' + CFG.storeId + '&sup=' + supId);
        if (cats && cats.length) {
            cats.filter(c => !c.parent_id).sort((a,b) => a.name.localeCompare(b.name,'bg')).forEach(c => {
                catRow.innerHTML += `<div class="fltr-btn" data-cat="${c.id}" onclick="setCascadeCat(${c.id},this)">${esc(c.name)}</div>`;
            });
        }
    }
}

// ─── CASCADE: Category selected ───
function setCascadeCat(catId, el) {
    _cascadeCat = catId;
    _cascadeSubcat = 0;
    document.querySelectorAll('#catFilterRow .fltr-btn').forEach(b => b.classList.toggle('active', parseInt(b.dataset.cat) === catId));
}

// ─── Navigate to product list with current cascade ───
function goFilteredList() {
    S.supId = _cascadeSup || null;
    S.catId = _cascadeCat || null;
    goScreen('products', { sup: S.supId, cat: S.catId });
}

// ─── Load subcategories for product list screen ───
async function loadSubcategories(catId) {
    const row = document.getElementById('subcatFilterRow');
    const label = document.getElementById('subcatLabel');
    if (!catId) { row.style.display = 'none'; label.style.display = 'none'; return; }
    const subs = await api('products.php?ajax=subcategories&parent_id=' + catId);
    if (subs && subs.length > 0) {
        row.style.display = 'flex';
        label.style.display = 'flex';
        row.innerHTML = '<div class="fltr-btn active" data-subcat="0" onclick="setSubcat(0,this)">Всички</div>';
        subs.forEach(s => {
            row.innerHTML += `<div class="fltr-btn" data-subcat="${s.id}" onclick="setSubcat(${s.id},this)">${esc(s.name)}</div>`;
        });
    } else {
        row.style.display = 'none';
        label.style.display = 'none';
    }
}

function setSubcat(id, el) {
    _cascadeSubcat = id;
    document.querySelectorAll('#subcatFilterRow .fltr-btn').forEach(b => b.classList.toggle('active', parseInt(b.dataset.subcat) === id));
    S.catId = id || _cascadeCat;
    S.page = 1;
    loadProducts();
}

// ─── SIGNALS ───
async function loadSignals() {
    const data = await api('products.php?ajax=signals&store_id=' + CFG.storeId);
    if (!data) return;
    _signalsData = data;
    renderSignals(data);
}

function renderSignals(signals) {
    const el = document.getElementById('signalsList');
    const countEl = document.getElementById('signalsCount');
    if (!signals || !signals.length) { el.innerHTML = ''; countEl.textContent = ''; return; }
    countEl.textContent = '· ' + signals.length;

    // Group by group
    const groups = { stock: [], money: [], sales: [], zombie: [], info: [], data: [] };
    const groupLabels = { stock: 'Наличност', money: 'Пари', sales: 'Продажби', zombie: 'Zombie стока', info: 'Информация', data: 'Качество на данни' };
    const groupColors = { stock: 'red', money: 'purple', sales: 'green', zombie: 'yellow', info: 'blue', data: 'orange' };

    signals.forEach(s => { if (groups[s.group]) groups[s.group].push(s); });

    let html = '';
    for (const [gk, items] of Object.entries(groups)) {
        if (!items.length) continue;
        html += `<div class="sig-group"><div class="sig-group-label sg-${groupColors[gk]}">${groupLabels[gk]}</div>`;
        items.forEach(s => {
            html += `<div class="sig-card s-${s.color}" onclick="askAISignal('${esc(s.question)}')" data-filter="${s.filter||s.type}">
                <div class="sig-dot"></div>
                <div class="sig-info"><div class="sig-label">${esc(s.label)}</div><div class="sig-desc">${esc(s.desc)}</div></div>
                <div class="sig-action">AI</div>
            </div>`;
        });
        html += '</div>';
    }
    el.innerHTML = html;

    // Also populate signal filter pills on products list screen
    const sfRow = document.getElementById('signalFilterRow');
    if (sfRow) {
        let pills = '';
        signals.forEach(s => {
            pills += `<div class="qfltr-pill sig-${s.color}" onclick="filterBySignal('${s.filter||s.type}')">${esc(s.label)} <span style="opacity:.5;font-size:8px">${s.count}</span></div>`;
        });
        sfRow.innerHTML = pills;
    }
}

function askAISignal(question) {
    // S43: Open AI overlay and auto-send question
    openAIChatOverlay();
    setTimeout(function(){
        if (typeof sendAutoQuestion === 'function') sendAutoQuestion(question);
    }, 400);
}

function filterBySignal(type) {
    // S43: real signal-based filtering via AJAX
    S.filter = type;
    S.page = 1;
    goScreen('products');
}

function openQuickFilter(type) {
    openDrawer('qf');
    document.getElementById('qfTitle').textContent = {price:'Филтър по цена',stock:'Филтър по наличност',margin:'Филтър по марж',date:'Филтър по дата',sales:'Филтър по продажби'}[type] || 'Филтър';
    let h = '<div style="padding:0 8px 16px">';
    if (type==='price') {
        h += '<div style="font-size:11px;color:var(--text-secondary);margin-bottom:10px">Бързи диапазони:</div>';
        h += '<div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px">';
        [{l:'до 20',min:0,max:20},{l:'20-50',min:20,max:50},{l:'50-100',min:50,max:100},{l:'100-200',min:100,max:200},{l:'над 200',min:200,max:''}].forEach(p=>{
            h += '<div class="f-chip" onclick="_qfApply({price_min:'+p.min+',price_max:'+(p.max||99999)+'})">'+p.l+' '+CFG.currency+'</div>';
        });
        h += '</div><div class="form-row"><div class="fg"><label class="fl">От</label><input type="number" class="fc" id="qfPriceMin" placeholder="0"></div><div class="fg"><label class="fl">До</label><input type="number" class="fc" id="qfPriceMax" placeholder="∞"></div></div>';
        h += '<button class="abtn primary" onclick="_qfApply({price_min:document.getElementById(\'qfPriceMin\').value,price_max:document.getElementById(\'qfPriceMax\').value})" style="margin-top:10px">Приложи</button>';
    } else if (type==='stock') {
        h += '<div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px">';
        [{l:'На нула',min:0,max:0},{l:'1-5 бр.',min:1,max:5},{l:'6-20 бр.',min:6,max:20},{l:'Над 20',min:21,max:''}].forEach(p=>{
            h += '<div class="f-chip" onclick="_qfApply({stock_min:'+p.min+',stock_max:'+(p.max||99999)+'})">'+p.l+'</div>';
        });
        h += '</div><div class="form-row"><div class="fg"><label class="fl">От</label><input type="number" class="fc" id="qfStockMin" placeholder="0"></div><div class="fg"><label class="fl">До</label><input type="number" class="fc" id="qfStockMax" placeholder="∞"></div></div>';
        h += '<button class="abtn primary" onclick="_qfApply({stock_min:document.getElementById(\'qfStockMin\').value,stock_max:document.getElementById(\'qfStockMax\').value})" style="margin-top:10px">Приложи</button>';
    } else if (type==='margin') {
        h += '<div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px">';
        [{l:'Под 10%',min:0,max:10},{l:'10-20%',min:10,max:20},{l:'20-40%',min:20,max:40},{l:'Над 40%',min:40,max:''}].forEach(p=>{
            h += '<div class="f-chip" onclick="_qfApply({margin_min:'+p.min+',margin_max:'+(p.max||999)+'})">'+p.l+'</div>';
        });
        h += '</div><div class="form-row"><div class="fg"><label class="fl">Марж от %</label><input type="number" class="fc" id="qfMarginMin" placeholder="0"></div><div class="fg"><label class="fl">Марж до %</label><input type="number" class="fc" id="qfMarginMax" placeholder="∞"></div></div>';
        h += '<button class="abtn primary" onclick="_qfApply({margin_min:document.getElementById(\'qfMarginMin\').value,margin_max:document.getElementById(\'qfMarginMax\').value})" style="margin-top:10px">Приложи</button>';
    } else if (type==='date') {
        h += '<div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px">';
        h += '<div class="f-chip" onclick="_qfApply({date_from:_daysAgo(7)})">Последните 7 дни</div>';
        h += '<div class="f-chip" onclick="_qfApply({date_from:_daysAgo(30)})">Последните 30 дни</div>';
        h += '<div class="f-chip" onclick="_qfApply({date_from:_daysAgo(90)})">Последните 90 дни</div>';
        h += '</div><div class="form-row"><div class="fg"><label class="fl">От</label><input type="date" class="fc" id="qfDateFrom"></div><div class="fg"><label class="fl">До</label><input type="date" class="fc" id="qfDateTo"></div></div>';
        h += '<button class="abtn primary" onclick="_qfApply({date_from:document.getElementById(\'qfDateFrom\').value,date_to:document.getElementById(\'qfDateTo\').value})" style="margin-top:10px">Приложи</button>';
    }
    h += '<button class="abtn" onclick="closeDrawer(\'qf\')" style="margin-top:6px">Затвори</button></div>';
    document.getElementById('qfBody').innerHTML = h;
}

let _qfState = {};
function _daysAgo(n) { const d=new Date(); d.setDate(d.getDate()-n); return d.toISOString().split('T')[0]; }
function _qfApply(params) {
    _qfState = {..._qfState, ...params};
    closeDrawer('qf');
    S.page = 1;
    loadProducts();
}

function openAIChatOverlay() {
    // S43: use shared ai-chat-overlay.php include
    const ov = document.getElementById('aiChatOverlay');
    if (ov) { ov.classList.add('open'); return; }
    // Fallback: try the drawer
    const dr = document.getElementById('aiChatOv');
    if (dr) { dr.classList.add('open'); return; }
    showToast('AI чат скоро...', '');
}

// ─── Override loadHome to use new design ───
async function loadHomeNew() {
    // Update header count
    const stats = await api('products.php?ajax=home_stats&store_id=' + CFG.storeId);
    if (stats && stats.counts) {
        document.getElementById('hdrCount').textContent = (stats.counts.total_products || 0) + ' артикула';
    }
    // Init cascade categories (all global)
    setCascadeSup(0, null);
    // Load signals
    loadSignals();
}

// ═══════════════════════════════════════════════════════════
// WIZARD REWRITE — 8 стъпки, info бутони, voice-compatible
// ═══════════════════════════════════════════════════════════

const WIZ_LABELS=['Вид','Снимка','AI обработка','Основна информация','Вариации','Детайли','Преглед и запис','Етикети'];

const WIZ_INFO={
    type_single:'Единичен артикул без варианти — например една чанта, едно бижу, или артикул който се продава само в един вид.',
    type_variant:'Артикул с варианти — различни размери, цветове или комбинации. Например тениска в S/M/L/XL и Черен/Бял.',
    photo:'Снимката помага на AI да разпознае артикула, генерира описание и обработи снимката. Сложи продукта на равна светла повърхност, без други предмети, с добро осветление.',
    studio:'AI обработва снимката ти — махане на фон, обличане на модел, студийна снимка за бижута и предмети. Снимката се използва и за AI описание на артикула.',
    name:'Наименованието е как клиентите ще виждат артикула. Бъди конкретен: "Nike Air Max 90 Черни" е по-добре от "Маратонки".',
    code:'Артикулният номер е уникален код за вътрешно ползване. AI го генерира автоматично ако е празен.',
    price:'Цената на дребно е крайната цена за клиента с ДДС.',
    wholesale:'Цена на едро — за клиенти които купуват на количество.',
    barcode:'Баркодът (EAN/UPC) се генерира автоматично ако е празен. Може да сканираш съществуващ с камерата.',
    supplier:'Доставчикът е от кого купуваш тази стока.',
    category:'Категорията помага да организираш стоката — напр. "Тениски", "Обувки", "Бижута".',
    subcategory:'Подкатегорията е по-тесен филтър — напр. "Спортни" в "Обувки".',
    variations:'Вариациите са различните версии на артикула — размер, цвят, материал и др. AI предлага типични за твоя бизнес.',
    unit:'Мерната единица определя как се брои стоката — бройка, чифт, комплект, метър и др.',
    min_qty:'Минималното количество е границата под която AI те предупреждава да поръчаш. Напр. 3 означава: под 3 бройки = "Свършва!"',
    description:'SEO описанието помага артикулът да се намира в Google. AI го генерира от снимката, името и вариациите.',
    bg_removal:'Премахва фона на снимката и го заменя с чисто бяло. Идеално за онлайн магазин, Instagram или етикети.',
    tryon_clothes:'AI облича артикула на модел. Избери тип модел и AI генерира реалистична снимка. Запазва точните пропорции на дрехата.',
    tryon_objects:'AI създава студийна снимка на предмета — бижута, обувки, чанти, аксесоари. Избери стил на снимката.',
    credits:'Безплатните кредити са включени в месечния ти план. Бял фон: 0.05 EUR/бр, AI Магия: 0.50 EUR/бр. Когато свършат, можеш да купиш допълнителни.'
};

function showWizInfo(key){
    const text=WIZ_INFO[key]||'Информация не е налична.';
    const el=document.createElement('div');
    el.className='wiz-info-overlay';
    el.onclick=function(e){if(e.target===el)el.remove()};
    el.innerHTML='<div class="wiz-info-box"><div style="font-size:13px;color:var(--text-primary);line-height:1.6">'+esc(text)+'</div><button class="abtn" onclick="this.closest(\'.wiz-info-overlay\').remove()" style="margin-top:10px">Разбрах ✓</button></div>';
    document.body.appendChild(el);
}

function infoBtn(key,color){
    color=color||'var(--indigo-400)';
    return '<div class="wiz-info-btn" onclick="event.stopPropagation();showWizInfo(\''+key+'\')" style="color:'+color+'">i</div>';
}

function fieldLabel(text,key,extra){
    extra=extra||'';
    return '<label class="fl">'+text+' '+infoBtn(key)+extra+'</label>';
}

// ─── MANUAL WIZARD ───
function openManualWizard(){
    S.wizStep=0;S.wizData={};S.wizType=null;S.wizEditId=null;
    S.wizVoiceMode=false;
    document.getElementById('wizTitle').textContent='Нов артикул';
    renderWizard();
    history.pushState({modal:'wizard'},'','#wizard');
    document.getElementById('wizModal').classList.add('open');
    document.body.style.overflow='hidden';
}

// ─── VOICE WIZARD — same steps, with skip buttons ───
function openVoiceWizard(){
    S.wizStep=0;S.wizData={};S.wizType=null;S.wizEditId=null;
    S.wizVoiceMode=true;
    document.getElementById('wizTitle').textContent='Нов артикул (с глас)';
    renderWizard();
    history.pushState({modal:'wizard'},'','#wizard');
    document.getElementById('wizModal').classList.add('open');
    document.body.style.overflow='hidden';
    // Auto voice for step 0
    setTimeout(()=>voiceForStep(0),500);
}

function voiceForStep(step){
    if(!S.wizVoiceMode)return;
    const hints={
        0:'Кажи: единичен или с варианти',
        1:null, // photo - manual
        2:null, // studio - manual
        3:'Кажи име, цена и доставчик',
        4:'Кажи размерите, после цветовете',
        5:null, // details - skip
        6:null, // preview - confirm
        7:null  // labels - manual
    };
    if(hints[step]){
        openVoice(hints[step],text=>handleVoiceStep(step,text));
    }
}

function handleVoiceStep(step,text){
    const t=text.toLowerCase();
    if(step===0){
        if(t.includes('вариант')||t.includes('размер')||t.includes('цвят'))S.wizType='variant';
        else S.wizType='single';
        showToast(S.wizType==='variant'?'С варианти ✓':'Единичен ✓','success');
        wizGo(1);
    }else if(step===3){
        parseVoiceToFields(text);
        renderWizard();
        showToast('Попълнено ✓','success');
    }else if(step===4){
        if(!S.wizData.axes)S.wizData.axes=[];
        const vals=text.split(/[\s,]+/).filter(Boolean);
        if(vals.length){
            const hasSize=S.wizData.axes.find(a=>a.name.toLowerCase().includes('размер'));
            if(!hasSize){
                S.wizData.axes.push({name:'Размер',values:vals});
                showToast('Размери добавени ✓','success');
                renderWizard();
                setTimeout(()=>openVoice('Цветове? Или кажи "без"',text2=>{
                    const t2=text2.toLowerCase();
                    if(!t2.includes('без')&&!t2.includes('няма')){
                        const v2=text2.split(/[\s,]+/).filter(Boolean);
                        if(v2.length)S.wizData.axes.push({name:'Цвят',values:v2});
                    }
                    renderWizard();
                }),600);
            }else{
                S.wizData.axes.push({name:'Цвят',values:vals});
                renderWizard();
            }
        }
    }
}

function parseVoiceToFields(text){
    const priceMatch=text.match(/(\d+[.,]?\d*)\s*(лева|лв|евро|€|eur)?/i);
    if(priceMatch)S.wizData.retail_price=parseFloat(priceMatch[1].replace(',','.'));
    const tl=text.toLowerCase();
    for(const s of CFG.suppliers){if(tl.includes(s.name.toLowerCase())){S.wizData.supplier_id=s.id;break}}
    for(const c of CFG.categories){if(tl.includes(c.name.toLowerCase())){S.wizData.category_id=c.id;break}}
    if(!S.wizData.name){
        let name=text.replace(/(\d+[.,]?\d*)\s*(лева|лв|евро|€|eur)?/gi,'').trim();
        for(const s of CFG.suppliers)name=name.replace(new RegExp(s.name,'gi'),'');
        for(const c of CFG.categories)name=name.replace(new RegExp(c.name,'gi'),'');
        name=name.replace(/\s+/g,' ').trim();
        if(name.length>2)S.wizData.name=name;
    }
}

function closeWizard(){
    document.getElementById('wizModal').classList.remove('open');
    document.body.style.overflow='';
}

function wizGo(step){
    wizCollectData();
    if(step===2&&!S.wizData._hasPhoto){step=3;}
    // Skip AI Studio if no photo
    if(step===2&&!S.wizData._hasPhoto){step=3;}
    S.wizStep=step;
    renderWizard();
    if(S.wizVoiceMode)setTimeout(()=>voiceForStep(step),400);
}

function renderWizard(){
    let sb='';
    for(let i=0;i<8;i++){
        let cls=i<S.wizStep?'done':i===S.wizStep?'active':'';
        sb+='<div class="wiz-step '+cls+'"></div>';
    }
    document.getElementById('wizSteps').innerHTML=sb;
    document.getElementById('wizLabel').innerHTML=(S.wizStep+1)+' · <b>'+WIZ_LABELS[S.wizStep]+'</b>';
    document.getElementById('wizBody').innerHTML=renderWizPage(S.wizStep);
    document.getElementById('wizBody').scrollTop=0;
    // Subcategory loader + Supplier→Category filter for step 3
    if(S.wizStep===3){
        // Force restore all fields from saved data (belt-and-suspenders)
        const _el=id=>document.getElementById(id);
        if(_el('wName')&&S.wizData.name)_el('wName').value=S.wizData.name;
        if(_el('wCode')&&S.wizData.code)_el('wCode').value=S.wizData.code;
        if(_el('wPrice')&&S.wizData.retail_price)_el('wPrice').value=S.wizData.retail_price;
        if(_el('wWprice')&&S.wizData.wholesale_price)_el('wWprice').value=S.wizData.wholesale_price;
        if(_el('wBarcode')&&S.wizData.barcode)_el('wBarcode').value=S.wizData.barcode;
        if(_el('wSup')&&S.wizData.supplier_id)_el('wSup').value=S.wizData.supplier_id;
        const wSup=document.getElementById('wSup');
        const wCat=document.getElementById('wCat');
        // When supplier changes → reload categories for this supplier
        if(wSup){wSup.onchange=async function(){
            const supId=this.value;
            const sel=document.getElementById('wCat');
            const subsel=document.getElementById('wSubcat');
            sel.innerHTML='<option value="">— Избери —</option>';
            if(subsel)subsel.innerHTML='<option value="">— Няма —</option>';
            if(!supId){
                // No supplier — show all categories
                CFG.categories.filter(c=>!c.parent_id).sort((a,b)=>a.name.localeCompare(b.name,'bg')).forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.name;sel.appendChild(o)});
                if(S.wizData.category_id)sel.value=S.wizData.category_id;
                if(sel.value&&wCat)wCat.onchange();
                return;
            }
            const d=await api('products.php?ajax=categories&store_id='+CFG.storeId+'&sup='+supId);
            if(d&&d.length){
                d.filter(c=>!c.parent_id).sort((a,b)=>a.name.localeCompare(b.name,'bg')).forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.name;sel.appendChild(o)});
            }
            // No fallback — show only supplier's categories (+ Нова via inline add)
            // Re-select saved category after rebuild
            if(S.wizData.category_id)sel.value=S.wizData.category_id;
            // Trigger subcategory load for saved category
            if(sel.value&&wCat)wCat.onchange();
        };if(S.wizData.supplier_id)wSup.onchange()}
        // When category changes → reload subcategories
        if(wCat){wCat.onchange=async function(){
            const id=this.value;const sel=document.getElementById('wSubcat');
            sel.innerHTML='<option value="">\u2014 Няма \u2014</option>';
            if(!id)return;
            const d=await api('products.php?ajax=subcategories&parent_id='+id);
            if(d&&d.length)d.forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.name;sel.appendChild(o)});
            // Re-select saved subcategory
            if(S.wizData.subcategory_id)sel.value=S.wizData.subcategory_id;
        };if(S.wizData.category_id&&!S.wizData.supplier_id)wCat.onchange()}
    }
}

function renderWizPage(step){
    const vskip=S.wizVoiceMode?'<button class="abtn" onclick="wizGo('+(step+1)+')" style="margin-top:6px;border-color:rgba(245,158,11,0.2);color:#fbbf24">⏭ Пропусни</button>':'';

    // ═══ STEP 0: ВИД ═══
    if(step===0){
        const ss=S.wizType==='single'?'border-color:var(--indigo-500);background:rgba(99,102,241,0.1)':'';
        const vs=S.wizType==='variant'?'border-color:var(--indigo-500);background:rgba(99,102,241,0.1)':'';
        return '<div class="wiz-page active"><div style="display:flex;align-items:center;gap:6px;margin-bottom:10px"><div style="font-size:15px;font-weight:700">Какъв е артикулът?</div>'+infoBtn('type_single')+'</div>'+
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">'+
        '<div style="padding:18px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);text-align:center;cursor:pointer;'+ss+'" onclick="S.wizType=\'single\';wizGo(1)"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="1.5" style="margin-bottom:4px"><rect x="3" y="3" width="18" height="18" rx="3"/></svg><div style="font-size:13px;font-weight:600">Единичен</div><div style="font-size:10px;color:var(--text-secondary)">Без варианти</div>'+infoBtn('type_single')+'</div>'+
        '<div style="padding:18px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);text-align:center;cursor:pointer;'+vs+'" onclick="S.wizType=\'variant\';wizGo(1)"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="1.5" style="margin-bottom:4px"><rect x="2" y="2" width="9" height="9" rx="2"/><rect x="13" y="2" width="9" height="9" rx="2"/><rect x="2" y="13" width="9" height="9" rx="2"/><rect x="13" y="13" width="9" height="9" rx="2"/></svg><div style="font-size:13px;font-weight:600">С варианти</div><div style="font-size:10px;color:var(--text-secondary)">Размери, цветове...</div>'+infoBtn('type_variant')+'</div></div>'+
        '<button class="abtn" onclick="closeWizard()" style="margin-top:10px">← Затвори</button>';
    }

    // ═══ STEP 1: СНИМКА ═══
    if(step===1){
        return '<div class="wiz-page active" style="text-align:center">'+
        '<div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:4px"><div style="font-size:15px;font-weight:600">Снимай артикула</div>'+infoBtn('photo')+'</div>'+
        '<div style="font-size:11px;color:var(--text-secondary);margin-bottom:14px">Силно препоръчително — AI използва снимката за описание и обработка</div>'+
        '<div style="background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.2);border-radius:10px;padding:10px;margin-bottom:14px;text-align:left"><div style="font-size:9px;font-weight:700;color:#fbbf24;margin-bottom:4px">СЪВЕТИ ЗА СНИМКА</div><div style="font-size:10px;color:#d4d4d8;line-height:1.6">✓ Сложи на равна светла повърхност<br>✓ Без други предмети около<br>✓ Добро осветление<br>✓ Ясна, неразмазана снимка<br>✓ Максимално добро качество</div></div>'+
        '<div style="display:flex;gap:8px;margin-bottom:14px">'+
        '<div style="flex:1;padding:16px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);cursor:pointer" onclick="document.getElementById(\'photoInput\').click()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="1.5" style="margin-bottom:4px"><path d="M15 10l4.5-4.5M20 4l-1 1"/><rect x="3" y="8" width="18" height="13" rx="2"/><circle cx="12" cy="15" r="3"/></svg><div style="font-size:11px;font-weight:600">Снимай</div></div>'+
        '<div style="flex:1;padding:16px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);cursor:pointer" onclick="document.getElementById(\'photoInput\').click()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="1.5" style="margin-bottom:4px"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg><div style="font-size:11px;font-weight:600">Галерия</div></div></div>'+
        '<div id="wizPhotoPreview">'+(S.wizData._photoDataUrl?'<img src="'+S.wizData._photoDataUrl+'" style="max-width:100%;max-height:150px;border-radius:10px;border:1px solid var(--border-subtle);margin-top:8px">':'')+'</div><div id="wizScanResult"></div>'+
        '<button class="abtn primary" onclick="wizGo(2)" style="margin-top:10px">Напред →</button>'+
        '<button class="abtn" onclick="S.wizData._hasPhoto=false;wizGo(3)" style="margin-top:6px;color:var(--text-secondary)">Пропусни снимката →</button>'+
        '<button class="abtn" onclick="wizGo(0)" style="margin-top:6px">← Назад</button>'+
        vskip+'</div>';
    }

    // ═══ STEP 2: AI IMAGE STUDIO ═══
    if(step===2){
        return renderStudioStep();
    }

    // ═══ STEP 3: ОСНОВНА ИНФОРМАЦИЯ ═══
    if(step===3){
        const nm=S.wizData.name||'';const pr=S.wizData.retail_price||'';const wp=S.wizData.wholesale_price||'';
        let supO='<option value="">— Избери —</option>';
        CFG.suppliers.slice().sort((a,b)=>a.name.localeCompare(b.name,'bg')).forEach(s=>supO+='<option value="'+s.id+'" '+(S.wizData.supplier_id==s.id?'selected':'')+'>'+esc(s.name)+'</option>');
        let catO='<option value="">— Избери —</option>';
        CFG.categories.filter(c=>!c.parent_id).sort((a,b)=>a.name.localeCompare(b.name,'bg')).forEach(c=>catO+='<option value="'+c.id+'" '+(S.wizData.category_id==c.id?'selected':'')+'>'+esc(c.name)+'</option>');
        const wpHidden=CFG.skipWholesale?'display:none':'';
        return '<div class="wiz-page active">'+
        '<div class="fg">'+fieldLabel('Наименование *','name')+'<input type="text" class="fc" id="wName" oninput="S.wizData.name=this.value.trim()" value="'+esc(nm)+'" placeholder="напр. Nike Air Max 90 Черни"></div>'+
        '<div class="fg">'+fieldLabel('Артикулен номер *','code','<span class="hint">(AI генерира ако е празно)</span>')+'<input type="text" class="fc" id="wCode" oninput="S.wizData.code=this.value.trim()" value="'+esc(S.wizData.code||'')+'" placeholder="автоматично"></div>'+
        '<div class="form-row">'+
        '<div class="fg">'+fieldLabel('Цена дребно *','price')+'<input type="number" step="0.01" class="fc" id="wPrice" oninput="S.wizData.retail_price=parseFloat(this.value)||0" value="'+pr+'" placeholder="0,00"></div>'+
        '<div class="fg" style="'+wpHidden+'">'+fieldLabel('Цена едро','wholesale')+'<input type="number" step="0.01" class="fc" id="wWprice" oninput="S.wizData.wholesale_price=parseFloat(this.value)||0" value="'+wp+'" placeholder="0,00"></div></div>'+
        '<div class="fg">'+fieldLabel('Баркод','barcode','<span class="hint">(автоматично ако е празно)</span>')+'<div style="display:flex;gap:6px;align-items:center"><input type="text" class="fc" id="wBarcode" oninput="S.wizData.barcode=this.value.trim()" value="'+esc(S.wizData.barcode||'')+'" placeholder="сканирай или въведи" style="flex:1"><button type="button" class="abtn" onclick="wizScanBarcode()" style="width:auto;padding:8px 12px;background:rgba(99,102,241,0.1);border-color:var(--indigo-500)" title="Сканирай"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="7" y1="7" x2="7" y2="17"/><line x1="10" y1="7" x2="10" y2="17"/><line x1="13" y1="7" x2="13" y2="14"/><line x1="16" y1="7" x2="16" y2="17"/></svg></button></div></div>'+
        '<div class="fg">'+fieldLabel('Доставчик','supplier','<span class="fl-add" onclick="toggleInl(\'inlSup\')">Добави нов</span>')+'<select class="fc" id="wSup" onchange="S.wizData.supplier_id=this.value||null">'+supO+'</select><div class="inline-add" id="inlSup"><input type="text" placeholder="Име" id="inlSupName"><button onclick="wizAddInline(\'supplier\')">Запази</button></div></div>'+
        '<div class="fg">'+fieldLabel('Категория','category','<span class="fl-add" onclick="toggleInl(\'inlCat\')">Добави нова</span>')+'<input type="text" class="fc" id="wCatSearch" placeholder="🔍 Търси категория..." style="margin-bottom:4px;font-size:12px" oninput="wizFilterSelect(\'wCat\',this.value)"><select class="fc" id="wCat" onchange="S.wizData.category_id=this.value||null">'+catO+'</select><div class="inline-add" id="inlCat"><input type="text" placeholder="Име" id="inlCatName"><button onclick="wizAddInline(\'category\')">Запази</button></div></div>'+
        '<div class="fg">'+fieldLabel('Подкатегория','subcategory','<span class="fl-add" onclick="toggleInl(\'inlSubcat\')">Добави нова</span>')+'<select class="fc" id="wSubcat" onchange="S.wizData.subcategory_id=this.value||null"><option value="">— Няма —</option></select><div class="inline-add" id="inlSubcat"><input type="text" placeholder="Име" id="inlSubcatName"><button onclick="wizAddSubcat()">Запази</button></div></div>'+
        '<button class="abtn primary" onclick="wizGo(4)">Напред →</button>'+
        '<button class="abtn" onclick="wizGo(S.wizData._hasPhoto?2:1)" style="margin-top:6px">← Назад</button>'+
        vskip+'</div>';
    }
    return renderWizPagePart2(step);
}
function renderWizPagePart2(step){
    const vskip=S.wizVoiceMode?'<button class="abtn" onclick="wizGo('+(step+1)+')" style="margin-top:6px;border-color:rgba(245,158,11,0.2);color:#fbbf24">⏭ Пропусни</button>':'';

    // ═══ STEP 4: ВАРИАЦИИ ═══
    if(step===4){
        if(S.wizType==='single')return '<div class="wiz-page active"><div style="font-size:13px;color:var(--text-secondary);margin-bottom:14px">Единичен артикул — без вариации.</div><button class="abtn primary" onclick="wizGo(5)">Напред →</button><button class="abtn" onclick="wizGo(3)" style="margin-top:6px">← Назад</button></div>';

        // Pre-load from biz-coefficients if no axes yet
        if(!S.wizData.axes||S.wizData.axes.length===0){
            S.wizData.axes=[];
            // Try to get from biz-coefficients via PHP-injected data
            if(window._bizVariants&&window._bizVariants.variant_fields){
                window._bizVariants.variant_fields.forEach(f=>{
                    const presets=window._bizVariants.variant_presets?.[f]||[];
                    S.wizData.axes.push({name:f,values:[...presets]});
                });
            }
            if(S.wizData.axes.length===0){
                S.wizData.axes.push({name:'Размер',values:[]});
                S.wizData.axes.push({name:'Цвят',values:[]});
            }
        }

        let axesH='';
        S.wizData.axes.forEach((ax,i)=>{
            const isSize=ax.name.toLowerCase().includes('размер')||ax.name.toLowerCase().includes('size');
            const isColor=ax.name.toLowerCase().includes('цвят')||ax.name.toLowerCase().includes('color');
            const hasPresets=isSize||isColor;
            const vals=ax.values.map((v,vi)=>'<span style="display:inline-block;padding:4px 10px;border-radius:8px;background:rgba(99,102,241,0.15);color:var(--indigo-300);font-size:12px;font-weight:600;margin:2px;cursor:pointer" onclick="S.wizData.axes['+i+'].values.splice('+vi+',1);renderWizard()">'+esc(v)+' ✕</span>').join('');
            axesH+='<div style="margin-bottom:10px;padding:12px;border-radius:12px;background:rgba(17,24,44,0.5);border:1px solid var(--border-subtle)">'+
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><span style="font-size:13px;font-weight:700;color:var(--indigo-300)">'+esc(ax.name)+'</span>'+
            '<div style="display:flex;gap:8px;align-items:center">'+
            (ax.values.length>0?'<span style="font-size:10px;color:rgba(245,158,11,0.8);cursor:pointer;font-weight:600" onclick="S.wizData.axes['+i+'].values=[];renderWizard()">Изчисти</span>':'')+
            '<span style="font-size:10px;color:var(--danger);cursor:pointer;font-weight:600" onclick="if(confirm(\'Премахни вариация?\')){S.wizData.axes.splice('+i+',1);renderWizard()}">✕ Премахни</span></div></div>'+
            '<div style="margin-bottom:8px;min-height:24px">'+(vals||'<span style="font-size:11px;color:var(--text-secondary)">Няма избрани стойности</span>')+'</div>'+
            (hasPresets?'<button type="button" class="abtn" style="width:100%;padding:10px;font-size:12px;font-weight:700;border-color:rgba(99,102,241,0.3);background:rgba(99,102,241,0.06);margin-bottom:6px" onclick="openPresetPicker('+i+','+(isSize?'true':'false')+')">Избери от списък</button>':'')+
            '<div style="display:flex;gap:6px"><input type="text" class="fc" id="axVal'+i+'" placeholder="Или въведи ръчно..." style="font-size:12px;padding:8px 10px" onkeydown="if(event.key===\'Enter\'){event.preventDefault();wizAddAxisValue('+i+')}"><button class="abtn" style="width:auto;padding:8px 14px;font-size:12px" onclick="wizAddAxisValue('+i+')">+</button></div></div>';
        });

        const combos=wizCountCombinations();
        return '<div class="wiz-page active">'+
        '<div style="display:flex;align-items:center;gap:6px;margin-bottom:10px"><div style="font-size:14px;font-weight:700">Вариации на артикула</div>'+infoBtn('variations')+'</div>'+
        axesH+
        '<div style="padding:12px;border-radius:12px;border:1px dashed var(--border-subtle);margin-bottom:12px">'+
        '<div style="font-size:10px;font-weight:600;color:var(--text-secondary);margin-bottom:6px">ДОБАВИ НОВА ВАРИАЦИЯ</div>'+
        '<div style="font-size:10px;color:var(--text-secondary);margin-bottom:8px">Напр: Материал, Форма, Дължина, Модел...</div>'+
        '<div style="display:flex;gap:6px"><input type="text" class="fc" id="newAxisName" placeholder="Име на вариация" style="font-size:12px;padding:8px 10px" onkeydown="if(event.key===\'Enter\'){event.preventDefault();wizAddAxis()}"><button class="abtn" style="width:auto;padding:8px 14px;font-size:12px;background:rgba(99,102,241,0.1);border-color:var(--indigo-500)" onclick="wizAddAxis()">+ Добави</button></div></div>'+
        (combos>0?'<div style="font-size:11px;color:var(--text-secondary);margin-bottom:12px;padding:8px 12px;border-radius:8px;background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.1)">Кръстоска: <b style="color:var(--indigo-300)">'+combos+'</b> комбинации</div>':'')+
        '<button class="abtn primary" onclick="wizGo(5)">Напред →</button>'+
        '<button class="abtn" onclick="wizGo(3)" style="margin-top:6px">← Назад</button>'+vskip+'</div>';
    }

    // ═══ STEP 5: ДЕТАЙЛИ ═══
    if(step===5){
        let unitO='';CFG.units.forEach(u=>unitO+='<option value="'+u+'" '+(S.wizData.unit===u?'selected':'')+'>'+u+'</option>');
        return '<div class="wiz-page active">'+
        '<div class="fg">'+fieldLabel('Мерна единица','unit','<span class="fl-add" onclick="toggleInl(\'inlUnit\')">Добави друга</span>')+
        '<select class="fc" id="wUnit" onchange="S.wizData.unit=this.value">'+unitO+'</select>'+
        '<div class="inline-add" id="inlUnit"><input type="text" placeholder="напр. метър, кг..." id="inlUnitName"><button onclick="wizAddUnit()">Запази</button></div></div>'+
        '<div class="fg">'+fieldLabel('Минимално количество','min_qty')+
        '<input type="number" class="fc" id="wMinQty" oninput="S.wizData.min_quantity=parseInt(this.value)||0" value="'+(S.wizData.min_quantity||0)+'" placeholder="0"></div>'+
        '<button class="abtn primary" onclick="wizGoPreview()">Напред →</button>'+
        '<button class="abtn" onclick="wizGo(4)" style="margin-top:6px">← Назад</button>'+vskip+'</div>';
    }

    // ═══ STEP 6: ПРЕГЛЕД + AI ОПИСАНИЕ + ЗАПИС ═══
    if(step===6){
        wizCollectData();
        const combos=wizBuildCombinations();
        let combosH='';
        if(combos.length<=1&&!combos[0]?.axisValues){
            combosH='<div class="form-row"><div class="fg"><label class="fl">Начална наличност</label><input type="number" class="fc" id="wSingleQty" value="1"></div><div class="fg"><label class="fl">Мин. наличност</label><input type="number" class="fc" id="wSingleMin" value="'+(S.wizData.min_quantity||0)+'"></div></div>';
        }else{
            combosH='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><div style="font-size:10px;color:var(--text-secondary);font-weight:700;text-transform:uppercase">'+combos.length+' вариации</div><div style="font-size:9px;color:var(--text-secondary)">БРОЙКА</div></div>';
            combos.forEach((v,i)=>{
                const label=v.axisValues||v.label||'';
                const parts=v.parts||[];
                let labelH='';
                parts.forEach(p=>{
                    const isSize=p.axis.toLowerCase().includes('размер')||p.axis.toLowerCase().includes('size');
                    const isColor=p.axis.toLowerCase().includes('цвят')||p.axis.toLowerCase().includes('color');
                    if(isColor){
                        const c=CFG.colors.find(cc=>cc.name===p.value);
                        const hex=c?c.hex:'#666';
                        labelH+='<span style="display:inline-flex;align-items:center;gap:3px;margin-right:6px"><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:'+hex+';border:1px solid rgba(255,255,255,0.2)"></span><span style="font-size:12px">'+esc(p.value)+'</span></span>';
                    }else if(isSize){
                        labelH+='<span style="font-size:13px;font-weight:800;margin-right:6px">'+esc(p.value)+'</span>';
                    }else{
                        labelH+='<span style="font-size:12px;margin-right:6px">'+esc(p.value)+'</span>';
                    }
                });
                if(!labelH)labelH='<span style="font-size:12px">'+esc(label)+'</span>';
                combosH+='<div style="display:flex;gap:6px;align-items:center;margin-bottom:4px;padding:6px 10px;border-radius:8px;background:rgba(17,24,44,0.3);border:1px solid var(--border-subtle)" id="comboRow'+i+'">'+
                '<div style="flex:1;display:flex;align-items:center;flex-wrap:wrap">'+labelH+'</div>'+
                '<div style="display:flex;align-items:center;gap:2px">'+
                '<button type="button" onclick="wizComboQty('+i+',-1)" style="width:28px;height:28px;border:1px solid var(--border-subtle);border-radius:6px;background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center">−</button>'+
                '<input type="number" class="fc" style="width:48px;padding:6px;text-align:center;font-size:14px;font-weight:700;border-radius:6px" value="1" min="0" data-combo="'+i+'">'+
                '<button type="button" onclick="wizComboQty('+i+',1)" style="width:28px;height:28px;border:1px solid var(--border-subtle);border-radius:6px;background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center">+</button>'+
                '</div>'+
                '<button type="button" onclick="if(confirm(\'Премахни тази вариация?\')){document.getElementById(\'comboRow'+i+'\').remove()}" style="width:24px;height:24px;border:none;background:none;color:var(--danger);font-size:14px;cursor:pointer;padding:0" title="Премахни">✕</button>'+
                '</div>';
            });
        }

        // AI description
        let descH='<div class="fg" style="margin-top:10px">'+fieldLabel('AI SEO описание','description')+
        '<textarea class="fc" id="wDesc" rows="3" placeholder="AI генерира...">'+(S.wizData.description?esc(S.wizData.description):'')+'</textarea>'+
        '<button class="abtn" onclick="wizGenDescription()" style="margin-top:4px;font-size:11px">✦ Генерирай AI описание</button></div>';

        return '<div class="wiz-page active">'+
        '<div style="font-size:14px;font-weight:700;margin-bottom:2px">'+esc(S.wizData.name||'Артикул')+'</div>'+
        '<div style="font-size:11px;color:var(--text-secondary);margin-bottom:10px">Цена: '+fmtPrice(S.wizData.retail_price)+' · Код: '+esc(S.wizData.code||'AI генерира')+'</div>'+
        (S.wizData.studioResult?'<div style="margin-bottom:10px;text-align:center"><img src="'+S.wizData.studioResult+'" style="max-width:120px;border-radius:10px;border:1px solid var(--border-subtle)"></div>':'')+
        combosH+
        descH+
        '<button class="abtn save" style="margin-top:14px;font-size:15px;padding:14px" onclick="wizSave()">✓ Запази артикула</button>'+
        '<button class="abtn" onclick="wizGo(5)" style="margin-top:6px">← Назад</button></div>';
    }

    // ═══ STEP 7: ЕТИКЕТИ ═══
    if(step===7){
        return '<div class="wiz-page active"><div style="text-align:center;padding:20px"><div style="font-size:18px;margin-bottom:6px">✓</div><div style="font-size:13px;font-weight:600;color:var(--success)">Артикулът е записан!</div><div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Зареждам етикети...</div></div></div>';
    }

    return '';
}

// ═══ AI IMAGE STUDIO STEP ═══
function renderStudioStep(){
    const vskip=S.wizVoiceMode?'<button class="abtn" onclick="wizGo(3)" style="margin-top:6px;border-color:rgba(245,158,11,0.2);color:#fbbf24">⏭ Пропусни</button>':'';

    // Screen 1: Photo info + credits + Screen 2: Options
    // We combine in one scrollable view with clear sections
    return '<div class="wiz-page active">'+

    // Section header
    '<div style="display:flex;align-items:center;gap:6px;margin-bottom:6px"><div style="font-size:14px;font-weight:600">AI Image Studio</div>'+infoBtn('studio')+'</div>'+
    '<div style="font-size:10px;color:var(--text-secondary);margin-bottom:10px">AI обработва снимката — махане на фон, обличане на модел, студийна снимка за бижута и предмети. Снимката се използва и за AI описание.</div>'+

    // Credits
    '<div style="padding:8px 12px;border-radius:8px;background:rgba(34,197,94,0.04);border:1px solid rgba(34,197,94,0.15);margin-bottom:8px">'+
    '<div style="font-size:9px;color:#6b7280;margin-bottom:4px">БЕЗПЛАТНИ КРЕДИТИ (ВКЛЮЧЕНИ В ПЛАНА)</div>'+
    '<div style="display:flex;gap:16px;align-items:center">'+
    '<div><span style="font-size:18px;font-weight:700;color:#22c55e">'+CFG.aiBg+'</span> <span style="font-size:10px;color:#6b7280">бял фон (0.05€)</span></div>'+
    '<div style="width:1px;height:20px;background:rgba(99,102,241,0.15)"></div>'+
    '<div><span style="font-size:18px;font-weight:700;color:#a78bfa">'+CFG.aiTryon+'</span> <span style="font-size:10px;color:#6b7280">магия (0.50€)</span></div></div></div>'+

    // Buy credits
    (CFG.aiBg<=0||CFG.aiTryon<=0?'<div style="padding:6px 10px;border-radius:8px;background:rgba(239,68,68,0.04);border:1px solid rgba(239,68,68,0.15);margin-bottom:8px;display:flex;align-items:center;gap:8px"><div style="flex:1;font-size:10px;color:#fca5a5">Кредитите свършиха!</div><button class="abtn" style="width:auto;padding:4px 12px;font-size:10px;background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff;border:none" onclick="location.href=\'settings.php?buy_credits=1\'">Купи</button></div>':'')+

    // ─── OPTION 1: Бял фон ───
    '<div style="padding:10px;border-radius:12px;background:rgba(34,197,94,0.04);border:1px solid rgba(34,197,94,0.2);margin-bottom:6px;cursor:pointer" onclick="doStudioWhiteBg()">'+
    '<div style="display:flex;align-items:center;gap:8px">'+
    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>'+
    '<div style="flex:1"><div style="font-size:13px;font-weight:500;color:#e2e8f0">Бял фон</div><div style="font-size:10px;color:#6b7280">Махва фона, чисто бяло</div></div>'+
    '<span style="font-size:11px;font-weight:500;color:#22c55e">0.05€</span>'+infoBtn('bg_removal','#22c55e')+'</div></div>'+

    // ─── OPTION 2: Дрехи на модел ───
    '<div style="padding:10px;border-radius:12px;background:rgba(139,92,246,0.04);border:1px solid rgba(139,92,246,0.2);margin-bottom:6px">'+
    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">'+
    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="1.5"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>'+
    '<div style="flex:1"><div style="font-size:13px;font-weight:500;color:#e2e8f0">AI Магия — дрехи</div><div style="font-size:10px;color:#6b7280">Облечи на модел</div></div>'+
    '<span style="font-size:11px;font-weight:500;color:#a78bfa">0.50€</span>'+infoBtn('tryon_clothes','#a78bfa')+'</div>'+
    // 6 models
    '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px;margin-bottom:6px">'+
    studioModelBtn('woman','Жена',true)+studioModelBtn('man','Мъж',false)+studioModelBtn('girl','Момиче',false)+
    studioModelBtn('boy','Момче',false)+studioModelBtn('teen_f','Тийн F',false)+studioModelBtn('teen_m','Тийн M',false)+'</div>'+
    '<div style="margin-bottom:6px"><input type="text" class="fc" id="studioPromptClothes" placeholder="допълни: стояща поза, профил..." style="font-size:11px;padding:6px 10px"></div>'+
    '<button class="abtn" onclick="doStudioTryon()" style="background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;border:none;font-size:11px">Генерирай на модел</button></div>'+

    // ─── OPTION 3: Предмети ───
    '<div style="padding:10px;border-radius:12px;background:rgba(234,179,8,0.04);border:1px solid rgba(234,179,8,0.2);margin-bottom:6px">'+
    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">'+
    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="1.5"><path d="M12 2L9 9H2l5.5 4-2 7L12 16l6.5 4-2-7L22 9h-7z"/></svg>'+
    '<div style="flex:1"><div style="font-size:13px;font-weight:500;color:#e2e8f0">AI Магия — предмети</div><div style="font-size:10px;color:#6b7280">Бижута, обувки, чанти, аксесоари</div></div>'+
    '<span style="font-size:11px;font-weight:500;color:#fbbf24">0.50€</span>'+infoBtn('tryon_objects','#fbbf24')+'</div>'+
    // 8 presets
    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:6px">'+
    studioPreset('Бижу на ръка')+studioPreset('На кадифе')+studioPreset('На мрамор')+studioPreset('Макро близък план')+
    studioPreset('На дърво')+studioPreset('Lifestyle сцена')+studioPreset('Обувка на крак')+studioPreset('Чанта на рамо')+'</div>'+
    '<div style="margin-bottom:6px"><input type="text" class="fc" id="studioPromptObjects" placeholder="или опиши: пръстен в кутийка..." style="font-size:11px;padding:6px 10px"></div>'+
    '<button class="abtn" onclick="doStudioObjects()" style="background:linear-gradient(135deg,#b45309,#d97706);color:#fff;border:none;font-size:11px">Генерирай студийна снимка</button></div>'+

    // Skip
    '<div style="padding:8px;border-radius:10px;border:1px dashed rgba(255,255,255,0.08);text-align:center;margin-bottom:6px;cursor:pointer" onclick="wizGo(3)"><span style="font-size:11px;color:#4b5563">Пропусни →</span></div>'+

    '<button class="abtn" onclick="wizGo(1)" style="margin-top:4px">← Назад</button>'+
    vskip+'</div>';
}

function studioModelBtn(key,label,sel){
    const bg=sel?'rgba(139,92,246,0.12);border:1px solid rgba(139,92,246,0.35)':'rgba(99,102,241,0.05);border:0.5px solid rgba(99,102,241,0.15)';
    return '<div style="text-align:center;padding:7px 2px;border-radius:7px;background:'+bg+';cursor:pointer" onclick="selectStudioModel(\''+key+'\',this)">'+
    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="'+(sel?'#c4b5fd':'#a5b4fc')+'" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M5 20c0-3.87 3.13-7 7-7s7 3.13 7 7"/></svg>'+
    '<div style="font-size:9px;color:'+(sel?'#c4b5fd':'#a5b4fc')+';font-weight:500">'+label+'</div></div>';
}

function studioPreset(label){
    return '<div style="padding:5px 8px;border-radius:6px;background:rgba(234,179,8,0.06);border:0.5px solid rgba(234,179,8,0.15);cursor:pointer;font-size:10px;color:#fcd34d" onclick="selectStudioPreset(\''+label+'\',this)">'+label+'</div>';
}

S.studioModel='woman';
S.studioPreset='';

function selectStudioModel(key,el){
    S.studioModel=key;
    el.parentElement.querySelectorAll('div').forEach(d=>{d.style.background='rgba(99,102,241,0.05)';d.style.border='0.5px solid rgba(99,102,241,0.15)'});
    el.style.background='rgba(139,92,246,0.12)';el.style.border='1px solid rgba(139,92,246,0.35)';
}

function selectStudioPreset(label,el){
    S.studioPreset=label;
    el.parentElement.querySelectorAll('div').forEach(d=>{d.style.background='rgba(234,179,8,0.06)';d.style.border='0.5px solid rgba(234,179,8,0.15)'});
    el.style.background='rgba(234,179,8,0.12)';el.style.border='1px solid rgba(234,179,8,0.35)';
    document.getElementById('studioPromptObjects').value=label;
}

async function doStudioWhiteBg(){
    showToast('AI обработва... 5-15 сек','');
    // TODO: fal.ai birefnet call via ai-image-processor.php
    const d=await api('products.php?ajax=ai_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'bg_removal'})});
    if(d?.error)showToast(d.error,'error');
    else{showToast('Бял фон приложен ✓','success');wizGo(3)}
}

async function doStudioTryon(){
    const prompt=document.getElementById('studioPromptClothes')?.value||'';
    showToast('AI генерира... 10-20 сек','');
    const d=await api('products.php?ajax=ai_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'tryon_'+S.studioModel,prompt})});
    if(d?.error)showToast(d.error,'error');
    else{showToast('Генерирано ✓','success');wizGo(3)}
}

async function doStudioObjects(){
    const prompt=document.getElementById('studioPromptObjects')?.value||S.studioPreset||'';
    showToast('AI генерира... 10-20 сек','');
    const d=await api('products.php?ajax=ai_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'object_studio',prompt})});
    if(d?.error)showToast(d.error,'error');
    else{showToast('Генерирано ✓','success');wizGo(3)}
}

// ─── HELPERS ───

function wizScanBarcode(){
    const ov=document.createElement('div');ov.className='preset-ov';ov.id='barcodeScanOv';
    ov.innerHTML='<div class="preset-box" style="text-align:center;padding:16px">'+
    '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><span style="font-size:15px;font-weight:700">Сканирай баркод</span><span style="font-size:22px;cursor:pointer" onclick="closeBarcodeScan()">✕</span></div>'+
    '<video id="wizBcVid" autoplay playsinline muted style="width:100%;max-height:250px;border-radius:12px;background:#000;object-fit:cover"></video>'+
    '<div style="margin-top:8px;font-size:11px;color:var(--text-secondary)">Насочи камерата към баркода</div>'+
    '<button class="abtn" onclick="closeBarcodeScan()" style="margin-top:10px">Затвори</button></div>';
    document.body.appendChild(ov);
    navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}).then(stream=>{
        const vid=document.getElementById('wizBcVid');
        if(!vid){stream.getTracks().forEach(t=>t.stop());return}
        vid.srcObject=stream;vid._stream=stream;
        if('BarcodeDetector' in window){
            const det=new BarcodeDetector({formats:['ean_13','ean_8','code_128','code_39','upc_a','upc_e']});
            vid._bcInterval=setInterval(async()=>{
                try{const codes=await det.detect(vid);
                if(codes.length){clearInterval(vid._bcInterval);const val=codes[0].rawValue;
                const el=document.getElementById('wBarcode');if(el)el.value=val;
                S.wizData.barcode=val;showToast('Баркод: '+val,'success');closeBarcodeScan();}
                }catch(e){}
            },300);
        }else{showToast('Браузърът не поддържа сканиране','error')}
    }).catch(()=>{showToast('Няма достъп до камерата','error');closeBarcodeScan()});
}
function closeBarcodeScan(){
    const ov=document.getElementById('barcodeScanOv');if(!ov)return;
    const vid=document.getElementById('wizBcVid');
    if(vid){if(vid._bcInterval)clearInterval(vid._bcInterval);if(vid._stream)vid._stream.getTracks().forEach(t=>t.stop())}
    ov.remove();
}

function openPresetPicker(axIdx,isSize){
    const ax=S.wizData.axes[axIdx];if(!ax)return;
    const existing=new Set(ax.values);
    let presets=[];
    if(isSize){
        const groups=[
            {label:'Букви (XS-4XL)',vals:['XS','S','M','L','XL','2XL','3XL','4XL']},
            {label:'EU номера (дрехи)',vals:['34','36','38','40','42','44','46','48','50','52','54','56']},
            {label:'Обувки (36-46)',vals:['36','37','38','39','40','41','42','43','44','45','46']},
            {label:'Детски',vals:['80','86','92','98','104','110','116','122','128','134','140','146','152','158','164']},
            {label:'Панталони',vals:['W28','W29','W30','W31','W32','W33','W34','W36','W38']},
            {label:'Чорапи',vals:['35-38','39-42','43-46']},
            {label:'Шапки',vals:['S/M','L/XL','One Size']},
            {label:'Пръстени',vals:['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII']},
        ];
        if(window._bizVariants?.variant_presets){
            for(const[k,v] of Object.entries(window._bizVariants.variant_presets)){
                if(k.toLowerCase().includes('размер')||k.toLowerCase().includes('size')){
                    if(v.length)groups.unshift({label:'За твоя бизнес ⭐',vals:v});
                }
            }
        }
        presets=groups;
    }else{
        presets=[{label:'Основни цветове',vals:CFG.colors.map(c=>c.name)}];
    }
    let html='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;position:sticky;top:0;background:var(--bg-card);padding:4px 0;z-index:1"><span style="font-size:16px;font-weight:700" id="presetTitle">Избери '+esc(ax.name)+'</span><span style="font-size:24px;cursor:pointer;padding:4px 8px" onclick="closePresetPicker()">✕</span></div>';
    html+='<div style="font-size:11px;color:var(--text-secondary);margin-bottom:12px">Натисни за избор. Натисни отново за махане.</div>';
    presets.forEach(g=>{
        html+='<div class="preset-cat">'+g.label+'</div><div style="margin-bottom:8px">';
        g.vals.forEach(v=>{
            const sel=existing.has(v)?'sel':'';
            let sw='';
            if(!isSize){const c=CFG.colors.find(cc=>cc.name===v);if(c)sw='<span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:'+c.hex+';margin-right:5px;vertical-align:middle;border:1px solid rgba(255,255,255,0.2)"></span>'}
            html+='<span class="preset-chip '+sel+'" onclick="togglePresetVal(this,'+axIdx+',\''+v.replace(/'/g,"\\'")+'\')">' +sw+esc(v)+'</span>';
        });
        html+='</div>';
    });
    html+='<div style="margin-top:14px;position:sticky;bottom:0;background:var(--bg-card);padding:8px 0"><button class="abtn primary" onclick="closePresetPicker()" style="width:100%;font-size:14px;padding:12px">Готово ✓</button></div>';
    const ov=document.createElement('div');ov.className='preset-ov';ov.id='presetPickerOv';
    ov.innerHTML='<div class="preset-box">'+html+'</div>';
    ov.addEventListener('click',function(e){if(e.target===this)closePresetPicker()});
    document.body.appendChild(ov);
}
function togglePresetVal(chip,axIdx,val){
    const ax=S.wizData.axes[axIdx];if(!ax)return;
    const idx=ax.values.indexOf(val);
    if(idx>=0){ax.values.splice(idx,1);chip.classList.remove('sel')}
    else{ax.values.push(val);chip.classList.add('sel')}
    const t=document.getElementById('presetTitle');
    if(t)t.textContent='Избери '+ax.name+' ('+ax.values.length+')';
}
function closePresetPicker(){
    const ov=document.getElementById('presetPickerOv');if(ov)ov.remove();
    renderWizard();
}

function wizAddAxis(){
    const inp=document.getElementById('newAxisName');
    const name=inp?.value.trim();
    if(!name)return;
    if(!S.wizData.axes)S.wizData.axes=[];
    S.wizData.axes.push({name,values:[]});
    renderWizard();
}

function wizAddAxisValue(axIdx){
    const inp=document.getElementById('axVal'+axIdx);
    const val=inp?.value.trim();
    if(!val)return;
    S.wizData.axes[axIdx].values.push(val);
    renderWizard();
}

function wizComboQty(idx,delta){
    const inp=document.querySelector('[data-combo="'+idx+'"]');
    if(!inp)return;
    let v=parseInt(inp.value)||0;
    v=Math.max(0,v+delta);
    inp.value=v;
}

function wizCountCombinations(){
    if(!S.wizData.axes||!S.wizData.axes.length)return 0;
    return S.wizData.axes.filter(a=>a.values.length>0).reduce((acc,ax)=>acc*ax.values.length,1);
}

function wizBuildCombinations(){
    if(!S.wizData.axes||!S.wizData.axes.length)return[{label:'Единичен',qty:0}];
    const axes=S.wizData.axes.filter(a=>a.values.length>0);
    if(!axes.length)return[{label:'Единичен',qty:0}];
    let combos=[{parts:[]}];
    for(const ax of axes){
        const next=[];
        for(const combo of combos){for(const val of ax.values){next.push({parts:[...combo.parts,{axis:ax.name,value:val}]})}}
        combos=next;
    }
    return combos.map(c=>({
        axisValues:c.parts.map(p=>p.value).join(' / '),
        parts:c.parts,qty:0
    }));
}


// ═══ SUPPLIER CATEGORY PICKER ═══
let _supCatSupplierId = null;

async function openSupCatModal(supplierId, supplierName) {
    _supCatSupplierId = supplierId;
    document.getElementById('supCatTitle').textContent = 'Категории на ' + supplierName;
    document.getElementById('supCatSearch').value = '';
    const body = document.getElementById('supCatBody');
    body.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-secondary)">Зарежда...</div>';
    document.getElementById('supCatModal').style.display = '';

    // Load category groups for this business type
    const groups = await api('products.php?ajax=category_groups&business_type=' + encodeURIComponent(CFG.businessType));
    // Load existing selected categories for this supplier
    const existing = await api('products.php?ajax=get_supplier_categories&supplier_id=' + supplierId);
    const existingSet = new Set((existing||[]).map(Number));
    // Load all tenant categories to match by name
    const allCats = CFG.categories.filter(c => !c.parent_id);
    const catByName = {};
    allCats.forEach(c => catByName[c.name.toLowerCase()] = c);

    let html = '';
    if (groups && groups.length) {
        groups.forEach(g => {
            html += '<div class="scp-group" style="margin-bottom:10px"><div style="font-size:13px;font-weight:700;color:var(--indigo-300);margin-bottom:6px">' + g.icon + ' ' + esc(g.group) + '</div>';
            g.categories.forEach(catName => {
                const match = catByName[catName.toLowerCase()];
                const catId = match ? match.id : 'new:' + catName;
                const checked = match && existingSet.has(match.id) ? 'checked' : '';
                html += '<label class="scp-item" style="display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:8px;cursor:pointer;font-size:12px" data-name="' + esc(catName) + '">';
                html += '<input type="checkbox" value="' + catId + '" ' + checked + ' style="accent-color:var(--indigo-500);width:18px;height:18px">';
                html += '<span>' + esc(catName) + '</span>';
                if (!match) html += '<span style="font-size:9px;color:rgba(245,158,11,0.8);margin-left:auto">нова</span>';
                html += '</label>';
            });
            html += '</div>';
        });
    } else {
        // No groups for this business type — show all existing categories
        html += '<div style="font-size:12px;color:var(--text-secondary);margin-bottom:8px">Всички категории:</div>';
        allCats.sort((a,b) => a.name.localeCompare(b.name,'bg')).forEach(c => {
            const checked = existingSet.has(c.id) ? 'checked' : '';
            html += '<label class="scp-item" style="display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:8px;cursor:pointer;font-size:12px" data-name="' + esc(c.name) + '">';
            html += '<input type="checkbox" value="' + c.id + '" ' + checked + ' style="accent-color:var(--indigo-500);width:18px;height:18px">';
            html += '<span>' + esc(c.name) + '</span></label>';
        });
    }
    body.innerHTML = html;
}

function filterSupCatModal(q) {
    const lq = q.toLowerCase().trim();
    document.querySelectorAll('#supCatBody .scp-item').forEach(el => {
        el.style.display = (!lq || el.dataset.name.toLowerCase().includes(lq)) ? '' : 'none';
    });
    document.querySelectorAll('#supCatBody .scp-group').forEach(g => {
        const visible = g.querySelectorAll('.scp-item[style=""], .scp-item:not([style])');
        g.style.display = visible.length > 0 ? '' : 'none';
    });
}

function closeSupCatModal() {
    document.getElementById('supCatModal').style.display = 'none';
}

async function saveSupCatModal() {
    const checks = document.querySelectorAll('#supCatBody input[type=checkbox]:checked');
    const catIds = [];
    const newCats = [];
    checks.forEach(cb => {
        const v = cb.value;
        if (v.startsWith('new:')) {
            newCats.push(v.substring(4));
        } else {
            catIds.push(parseInt(v));
        }
    });

    // Create new categories first
    for (const name of newCats) {
        const d = await api('products.php?ajax=add_category', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'name=' + encodeURIComponent(name)
        });
        if (d?.id) {
            catIds.push(d.id);
            CFG.categories.push({id: d.id, name: d.name});
        }
    }

    // Save supplier_categories
    await api('products.php?ajax=save_supplier_categories', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({supplier_id: _supCatSupplierId, category_ids: catIds})
    });

    showToast(catIds.length + ' категории записани ✓', 'success');
    closeSupCatModal();
    renderWizard();
}

function wizFilterSelect(selId,q){
    const sel=document.getElementById(selId);if(!sel)return;
    const lq=q.toLowerCase().trim();
    for(const o of sel.options){
        if(!o.value){o.style.display='';continue}
        o.style.display=(!lq||o.textContent.toLowerCase().includes(lq))?'':'none';
    }
}

function wizCollectData(){
    const el=id=>document.getElementById(id);
    if(el('wName')){const v=el('wName').value.trim();if(v)S.wizData.name=v}
    if(el('wCode'))S.wizData.code=el('wCode').value.trim();
    if(el('wPrice')){const v=parseFloat(el('wPrice').value);if(v)S.wizData.retail_price=v}
    if(el('wWprice'))S.wizData.wholesale_price=parseFloat(el('wWprice').value)||0;
    if(el('wBarcode'))S.wizData.barcode=el('wBarcode').value.trim();
    if(el('wSup'))S.wizData.supplier_id=el('wSup').value||null;
    if(el('wCat'))S.wizData.category_id=el('wCat').value||null;
    if(el('wSubcat'))S.wizData.subcategory_id=el('wSubcat').value||null;
    if(el('wUnit'))S.wizData.unit=el('wUnit').value||'бр';
    if(el('wMinQty'))S.wizData.min_quantity=parseInt(el('wMinQty').value)||0;
    if(el('wDesc'))S.wizData.description=el('wDesc').value;
}

async function wizGoPreview(){
    wizCollectData();
    if(!S.wizData.name){showToast('Въведи наименование','error');wizGo(3);return}
    if(!S.wizData.retail_price){showToast('Въведи цена','error');wizGo(3);return}
    if(!S.wizData.code&&S.wizData.name){
        const d=await api('products.php?ajax=ai_code',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:S.wizData.name})});
        if(d?.code)S.wizData.code=d.code;
    }
    wizGo(6);
}

async function wizGenDescription(){
    const name=S.wizData.name||document.getElementById('wName')?.value||'';
    if(!name){showToast('Въведи име първо','error');return}
    showToast('AI генерира описание...','');
    const cat=document.getElementById('wCat')?.selectedOptions[0]?.text||'';
    const sup=document.getElementById('wSup')?.selectedOptions[0]?.text||'';
    const d=await api('products.php?ajax=ai_description',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name,category:cat,supplier:sup})});
    if(d?.description){
        document.getElementById('wDesc').value=d.description;
        S.wizData.description=d.description;
        showToast('Описание генерирано ✓','success');
    }
}

async function wizSave(){
    wizCollectData();
    if(!S.wizData.name){showToast('Въведи наименование','error');return}
    const combos=wizBuildCombinations();
    document.querySelectorAll('[data-combo]').forEach(inp=>{
        const idx=parseInt(inp.dataset.combo);
        if(combos[idx])combos[idx].qty=parseInt(inp.value)||0;
    });
    const singleQty=parseInt(document.getElementById('wSingleQty')?.value)||0;

    let sizes=[],colors=[],extraAxes=[];
    (S.wizData.axes||[]).forEach(ax=>{
        const n=ax.name.toLowerCase();
        if(n.includes('размер')||n.includes('size'))sizes=ax.values;
        else if(n.includes('цвят')||n.includes('color'))colors=ax.values;
        else extraAxes.push(ax);
    });

    const variants=combos.map(c=>{
        const sizeVal=c.parts?.find(p=>p.axis.toLowerCase().includes('размер')||p.axis.toLowerCase().includes('size'))?.value||null;
        const colorVal=c.parts?.find(p=>p.axis.toLowerCase().includes('цвят')||p.axis.toLowerCase().includes('color'))?.value||null;
        const extras=c.parts?.filter(p=>{const n=p.axis.toLowerCase();return !n.includes('размер')&&!n.includes('size')&&!n.includes('цвят')&&!n.includes('color')}).map(p=>p.value)||[];
        const finalSize=[sizeVal,...extras].filter(Boolean).join(' / ')||null;
        return{size:finalSize,color:colorVal,qty:c.qty||0};
    });

    const payload={
        name:S.wizData.name,barcode:S.wizData.barcode,
        retail_price:S.wizData.retail_price,wholesale_price:S.wizData.wholesale_price,
        cost_price:0,supplier_id:S.wizData.supplier_id,category_id:S.wizData.category_id,
        code:S.wizData.code,unit:S.wizData.unit,min_quantity:S.wizData.min_quantity,
        description:S.wizData.description,
        product_type:S.wizType==='variant'?'variant':'simple',
        sizes,colors,variants:S.wizType==='variant'?variants:[{size:null,color:null,qty:singleQty}],
        initial_qty:singleQty,
        id:S.wizEditId||undefined,action:S.wizEditId?'edit':'create'
    };

    showToast('Запазвам...','');
    try{
        const r=await api('product-save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        if(r&&(r.success||r.id)){
            showToast('Артикулът е добавен!','success');
            S.wizSavedId=r.id;
            wizGo(7);
            setTimeout(()=>openLabels(r.id),500);
            setTimeout(()=>closeWizard(),600);
            loadScreen();
        }else{showToast(r?.error||'Грешка','error')}
    }catch(e){showToast('Мрежова грешка','error')}
}

function wizAddSubcat(){
    const name=document.getElementById('inlSubcatName')?.value.trim();
    const parentId=document.getElementById('wCat')?.value;
    if(!name||!parentId){showToast('Избери категория и въведи име','error');return}
    api('products.php?ajax=add_subcategory',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'name='+encodeURIComponent(name)+'&parent_id='+parentId}).then(d=>{
        if(d?.id){
            if(d.duplicate){showToast('Подкатегория "'+d.name+'" вече съществува','error')}
            else{showToast('Подкатегория добавена ✓','success')}
            const sel=document.getElementById('wSubcat');
            const o=document.createElement('option');o.value=d.id;o.textContent=d.name;o.selected=true;
            sel.appendChild(o);
            S.wizData.subcategory_id=d.id;
            document.getElementById('inlSubcat').classList.remove('open');
        }
    });
}

function wizAddUnit(){
    const unit=document.getElementById('inlUnitName')?.value.trim();
    if(!unit)return;
    api('products.php?ajax=add_unit',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'unit='+encodeURIComponent(unit)}).then(d=>{
        if(d?.units){
            CFG.units=d.units;
            S.wizData.unit=d.added;
            renderWizard();
            showToast('Мерна единица добавена ✓','success');
        }
    });
}

// Photo handlers

document.getElementById('filePickerInput').addEventListener('change',async function(){
    document.getElementById('photoInput').files = this.files;
    document.getElementById('photoInput').dispatchEvent(new Event('change'));
    this.value='';
});
// S43: removed duplicate filePickerInput listener
document.getElementById('photoInput').addEventListener('change',async function(){
    if(!this.files?.[0])return;
    const preview=document.getElementById('wizPhotoPreview');
    const result=document.getElementById('wizScanResult');
    if(preview)preview.innerHTML='<div style="font-size:12px;color:var(--text-secondary);margin-top:8px">Снимка заредена ✓</div>';
    if(result)result.innerHTML='<div style="font-size:12px;color:var(--indigo-300);margin-top:6px">✦ AI анализира...</div>';
    const reader=new FileReader();
    reader.onload=async e=>{
        S.wizData._photoDataUrl=e.target.result;
        S.wizData._hasPhoto=true;
        if(document.getElementById('wizPhotoPreview'))document.getElementById('wizPhotoPreview').innerHTML='<img src="'+e.target.result+'" style="max-width:100%;max-height:150px;border-radius:10px;border:1px solid var(--border-subtle);margin-top:8px">';
        const base64=e.target.result.split(',')[1];
        const d=await api('products.php?ajax=ai_scan',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({image:base64})});
        if(d&&!d.error){
            S.wizData={...S.wizData,...d};
            if(d.sizes?.length){
                S.wizData.axes=S.wizData.axes||[];
                if(!S.wizData.axes.find(a=>a.name.toLowerCase().includes('размер')))
                    S.wizData.axes.push({name:'Размер',values:d.sizes});
                if(!S.wizType)S.wizType='variant';
            }
            if(d.colors?.length){
                S.wizData.axes=S.wizData.axes||[];
                if(!S.wizData.axes.find(a=>a.name.toLowerCase().includes('цвят')))
                    S.wizData.axes.push({name:'Цвят',values:d.colors});
            }
            S.wizData._hasPhoto=true;
            if(result)result.innerHTML='<div style="font-size:12px;color:var(--success);margin-top:6px">✓ AI разпозна — данните попълнени</div>';
            showToast('AI разпозна ✓','success');
            setTimeout(()=>wizGo(2),800);
        }else{
            S.wizData._hasPhoto=true;
            if(result)result.innerHTML='<div style="font-size:12px;color:var(--warning);margin-top:6px">AI не разпозна — продължи ръчно</div>';
        }
    };
    reader.readAsDataURL(this.files[0]);
    this.value='';
});

// Inline add helpers
function toggleInl(id){document.getElementById(id)?.classList.toggle('open')}

async function wizAddInline(type){
    if(type==='supplier'){
        const n=document.getElementById('inlSupName')?.value.trim();
        if(!n)return;
        const d=await api('products.php?ajax=add_supplier',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'name='+encodeURIComponent(n)});
        if(d?.id){if(d.duplicate){showToast('Доставчик "'+d.name+'" вече съществува','error');S.wizData.supplier_id=d.id;const ss=document.getElementById('wSup');if(ss)ss.value=d.id;document.getElementById('inlSup')?.classList.remove('open')}else{CFG.suppliers.push({id:d.id,name:d.name});S.wizData.supplier_id=d.id;showToast('Добавен ✓','success');renderWizard();openSupCatModal(d.id,d.name)}}
    }else{
        const n=document.getElementById('inlCatName')?.value.trim();
        if(!n)return;
        const d=await api('products.php?ajax=add_category',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'name='+encodeURIComponent(n)});
        if(d?.id){if(d.duplicate){showToast('Категория "'+d.name+'" вече съществува','error');S.wizData.category_id=d.id;const cs=document.getElementById('wCat');if(cs)cs.value=d.id;document.getElementById('inlCat')?.classList.remove('open')}else{CFG.categories.push({id:d.id,name:d.name});S.wizData.category_id=d.id;showToast('Добавена ✓','success');renderWizard()}}
    }
}

// Edit existing product
async function editProduct(id){
    closeDrawer('detail');
    const d=await api('products.php?ajax=product_detail&id='+id);
    if(!d||d.error)return;
    const p=d.product;
    S.wizEditId=id;S.wizType='single';S.wizStep=3;
    S.wizData={name:p.name,code:p.code,retail_price:p.retail_price,wholesale_price:p.wholesale_price,
        barcode:p.barcode,supplier_id:p.supplier_id,category_id:p.category_id,
        description:p.description,unit:p.unit,min_quantity:p.min_quantity,axes:[]};
    S.wizVoiceMode=false;
    document.getElementById('wizTitle').textContent='Редактирай';
    renderWizard();
    history.pushState({modal:'wizard'},'','#wizard');
    document.getElementById('wizModal').classList.add('open');
    document.body.style.overflow='hidden';
}


// ─── INIT ───
document.addEventListener('DOMContentLoaded',()=>{
    history.replaceState({scr:'home'}, '', '#home');
    goScreen('home');
});

// S43: Back button support for drawers and screens
window.addEventListener('popstate', function(e) {
    const state = e.state;
    if (!state) { goScreen('home'); return; }
    if (state.scr) { goScreen(state.scr, state); return; }
    // Close any open drawers/modals
    ['detail','ai','filter','studio','labels','csv','qf'].forEach(n => closeDrawer(n));
    closeWizard();
    const recOv = document.getElementById('recOv');
    if (recOv && recOv.classList.contains('open')) closeVoice();
});

// Override rec-ov backdrop click for wizard mode
document.getElementById('recOv').addEventListener('click',function(e){
    if(e.target===this){
        if(S.aiWizMode)closeAIWizardOverlay();
        else closeVoice();
    }
});
</script>

<!-- Supplier Category Picker Modal -->
<div id="supCatModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);overflow-y:auto;padding:16px">
<div style="max-width:480px;margin:20px auto;background:var(--bg-card);border-radius:16px;border:1px solid var(--border-subtle);padding:16px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
<div style="font-size:15px;font-weight:700" id="supCatTitle">Категории на доставчика</div>
<button onclick="closeSupCatModal()" style="background:none;border:none;color:var(--text-secondary);font-size:20px;cursor:pointer">✕</button>
</div>
<div style="font-size:11px;color:var(--text-secondary);margin-bottom:12px">Избери какви категории носи този доставчик:</div>
<input type="text" class="fc" id="supCatSearch" placeholder="🔍 Търси..." style="margin-bottom:10px;font-size:12px" oninput="filterSupCatModal(this.value)">
<div id="supCatBody" style="max-height:55vh;overflow-y:auto"></div>
<div style="display:flex;gap:8px;margin-top:12px">
<button class="abtn primary" onclick="saveSupCatModal()" style="flex:1">✓ Запази</button>
<button class="abtn" onclick="closeSupCatModal()" style="flex:1">Затвори</button>
</div>
</div>
</div>

<!-- S43: Quick Filter drawer -->
<div class="drawer-ov" id="qfOv" onclick="closeDrawer('qf')"></div>
<div class="drawer" id="qfDr">
    <div class="drawer-handle"></div>
    <div class="drawer-hdr"><h3 id="qfTitle">Филтър</h3><button class="drawer-close" onclick="closeDrawer('qf')">✕</button></div>
    <div id="qfBody" style="padding:0 4px 20px"></div>
</div>
<nav class="bottom-nav">
  <a href="chat.php" class="bottom-nav-tab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>AI
  </a>
  <a href="warehouse.php" class="bottom-nav-tab active">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>Склад
  </a>
  <a href="stats.php" class="bottom-nav-tab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Справки
  </a>
  <a href="sale.php" class="bottom-nav-tab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>Продажба
  </a>
</nav>

<?php if (file_exists(__DIR__ . "/includes/ai-chat-overlay.php")) { include __DIR__ . "/includes/ai-chat-overlay.php"; } ?>
</body>
</html>
