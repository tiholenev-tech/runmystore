<?php
/**
 * products.php — RunMyStore.ai
 * ПЪЛЕН REWRITE — Сесия 26
 * 4-екранен модул: Начало | Доставчици | Категории | Артикули
 * Дизайн: warehouse.php/sale.php система (#030712)
 * Voice: sale.php rec-ov/rec-box (16px пулсираща червена точка)
 * 3 пътя добавяне: AI Wizard (глас) | Ръчен Wizard (6 стъпки) | CSV Import
 * AI Image Studio | Етикети/Наличност екран
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

// Tenant info
$tenant = DB::run("SELECT * FROM tenants WHERE id = ?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
$business_type = $tenant['business_type'] ?? '';
$currency = htmlspecialchars($tenant['currency'] ?? 'лв');
$lang = $tenant['lang'] ?? 'bg';
$skip_wholesale = (int)($tenant['skip_wholesale_price'] ?? 0);
$ai_bg = (int)($tenant['ai_credits_bg'] ?? 0);
$ai_tryon = (int)($tenant['ai_credits_tryon'] ?? 0);

// User info
$user = DB::run("SELECT * FROM users WHERE id = ?", [$user_id])->fetch(PDO::FETCH_ASSOC);

// Stores
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
            SELECT p.id, p.name, p.code, p.retail_price, p.cost_price, p.image, p.supplier_id,
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
            SELECT p.id, p.name, p.code, p.barcode, p.retail_price, p.cost_price, p.image,
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
            FROM products p
            LEFT JOIN suppliers s ON s.id = p.supplier_id
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
        echo json_encode(['product' => $product, 'stocks' => $stocks, 'variations' => $variations, 'price_history' => $price_history]);
        exit;
    }

    // ─── HOME STATS ───
    if ($ajax === 'home_stats') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $capital = DB::run("
            SELECT COALESCE(SUM(i.quantity * p.retail_price), 0) AS retail_value,
                   COALESCE(SUM(i.quantity * p.cost_price), 0) AS cost_value
            FROM inventory i JOIN products p ON p.id = i.product_id
            WHERE p.tenant_id = ? AND i.store_id = ? AND i.quantity > 0
        ", [$tenant_id, $sid])->fetch(PDO::FETCH_ASSOC);
        $avg_margin = 0;
        if ($can_see_margin && $capital['cost_value'] > 0) {
            $avg_margin = round((($capital['retail_value'] - $capital['cost_value']) / $capital['retail_value']) * 100, 1);
        }
        $zombies = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image, COALESCE(i.quantity, 0) AS qty,
                   DATEDIFF(NOW(), COALESCE((SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_id = p.id), p.created_at)) AS days_stale
            FROM products p JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND p.is_active = 1 AND i.quantity > 0 AND p.parent_id IS NULL
            HAVING days_stale > 45 ORDER BY days_stale DESC LIMIT 10
        ", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        $low_stock = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image, p.min_quantity, COALESCE(i.quantity, 0) AS qty
            FROM products p JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND p.is_active = 1 AND p.min_quantity > 0 AND i.quantity <= p.min_quantity AND i.quantity > 0
            ORDER BY (i.quantity / p.min_quantity) ASC LIMIT 10
        ", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        $out_of_stock = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image
            FROM products p JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND p.is_active = 1 AND i.quantity = 0 AND p.parent_id IS NULL
            ORDER BY p.name LIMIT 20
        ", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        $top_sellers = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image, SUM(si.quantity) AS sold_qty, SUM(si.quantity * si.unit_price) AS revenue
            FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
            WHERE p.tenant_id = ? AND s.store_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND s.status = 'completed'
            GROUP BY p.id ORDER BY sold_qty DESC LIMIT 5
        ", [$tenant_id, $sid])->fetchAll(PDO::FETCH_ASSOC);
        $slow_movers = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image, COALESCE(i.quantity, 0) AS qty,
                   DATEDIFF(NOW(), COALESCE((SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_id = p.id), p.created_at)) AS days_stale
            FROM products p JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND p.is_active = 1 AND i.quantity > 0 AND p.parent_id IS NULL
            HAVING days_stale BETWEEN 25 AND 45 ORDER BY days_stale DESC LIMIT 10
        ", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        $counts = DB::run("
            SELECT COUNT(DISTINCT p.id) AS total_products, COALESCE(SUM(i.quantity), 0) AS total_units
            FROM products p LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
        ", [$sid, $tenant_id])->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'capital' => $can_see_margin ? round($capital['retail_value'], 2) : null,
            'avg_margin' => $can_see_margin ? $avg_margin : null,
            'zombies' => $zombies, 'low_stock' => $low_stock, 'out_of_stock' => $out_of_stock,
            'top_sellers' => $top_sellers, 'slow_movers' => $slow_movers, 'counts' => $counts
        ]);
        exit;
    }

    // ─── SUPPLIERS ───
    if ($ajax === 'suppliers') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $suppliers = DB::run("
            SELECT s.id, s.name, s.phone, s.email, COUNT(DISTINCT p.id) AS product_count,
                   COALESCE(SUM(i.quantity), 0) AS total_stock,
                   SUM(CASE WHEN i.quantity = 0 THEN 1 ELSE 0 END) AS out_count,
                   SUM(CASE WHEN i.quantity > 0 AND i.quantity <= p.min_quantity AND p.min_quantity > 0 THEN 1 ELSE 0 END) AS low_count
            FROM suppliers s JOIN products p ON p.supplier_id = s.id AND p.tenant_id = ? AND p.is_active = 1
            LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE s.tenant_id = ? GROUP BY s.id ORDER BY s.name
        ", [$tenant_id, $sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($suppliers); exit;
    }

    // ─── CATEGORIES ───
    if ($ajax === 'categories') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $sup = isset($_GET['sup']) ? (int)$_GET['sup'] : null;
        $where = $sup ? "AND p.supplier_id = ?" : "";
        $params = $sup ? [$tenant_id, $sid, $sup, $tenant_id] : [$tenant_id, $sid, $tenant_id];
        $sql = $sup
            ? "SELECT c.id, c.name, c.parent_id, COUNT(DISTINCT p.id) AS product_count, COALESCE(SUM(i.quantity), 0) AS total_stock
               FROM categories c JOIN products p ON p.category_id = c.id AND p.tenant_id = ? AND p.is_active = 1
               LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
               WHERE p.supplier_id = ? AND c.tenant_id = ? GROUP BY c.id ORDER BY c.name"
            : "SELECT c.id, c.name, c.parent_id, COUNT(DISTINCT p.id) AS product_count,
                      COUNT(DISTINCT p.supplier_id) AS supplier_count, COALESCE(SUM(i.quantity), 0) AS total_stock
               FROM categories c JOIN products p ON p.category_id = c.id AND p.tenant_id = ? AND p.is_active = 1
               LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
               WHERE c.tenant_id = ? GROUP BY c.id ORDER BY c.name";
        $categories = DB::run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($categories); exit;
    }

    // ─── SUBCATEGORIES ───
    if ($ajax === 'subcategories') {
        $parent_id = (int)($_GET['parent_id'] ?? 0);
        $rows = DB::run("SELECT id, name FROM categories WHERE tenant_id = ? AND parent_id = ? ORDER BY name", [$tenant_id, $parent_id])->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows); exit;
    }

    // ─── PRODUCTS LIST ───
    if ($ajax === 'products') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $sup = isset($_GET['sup']) ? (int)$_GET['sup'] : null;
        $cat = isset($_GET['cat']) ? (int)$_GET['cat'] : null;
        $flt = $_GET['filter'] ?? 'all';
        $sort = $_GET['sort'] ?? 'name';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 30; $offset = ($page - 1) * $per_page;
        $where = ["p.tenant_id = ?", "p.is_active = 1", "p.parent_id IS NULL"]; $params = [$tenant_id];
        if ($sup) { $where[] = "p.supplier_id = ?"; $params[] = $sup; }
        if ($cat) { $where[] = "p.category_id = ?"; $params[] = $cat; }
        if ($flt === 'low') { $where[] = "i.quantity > 0 AND i.quantity <= p.min_quantity AND p.min_quantity > 0"; }
        elseif ($flt === 'out') { $where[] = "(i.quantity = 0 OR i.quantity IS NULL)"; }
        $where_sql = implode(' AND ', $where);
        $order = match($sort) {
            'price_asc' => 'p.retail_price ASC', 'price_desc' => 'p.retail_price DESC',
            'stock_asc' => 'store_stock ASC', 'stock_desc' => 'store_stock DESC',
            'newest' => 'p.created_at DESC', default => 'p.name ASC'
        };
        $products = DB::run("
            SELECT p.id, p.name, p.code, p.barcode, p.retail_price, p.cost_price, p.image,
                   p.supplier_id, p.category_id, p.parent_id, p.discount_pct, p.discount_ends_at,
                   p.min_quantity, p.unit, s.name AS supplier_name, c.name AS category_name,
                   COALESCE(i.quantity, 0) AS store_stock
            FROM products p LEFT JOIN suppliers s ON s.id = p.supplier_id LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE {$where_sql} ORDER BY {$order} LIMIT ? OFFSET ?
        ", array_merge([$sid], $params, [$per_page, $offset]))->fetchAll(PDO::FETCH_ASSOC);
        $total = DB::run("SELECT COUNT(DISTINCT p.id) AS cnt FROM products p LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ? WHERE {$where_sql}", array_merge([$sid], $params))->fetchColumn();
        if (!$can_see_cost) { foreach ($products as &$pr) unset($pr['cost_price']); }
        echo json_encode(['products' => $products, 'total' => (int)$total, 'page' => $page, 'pages' => ceil($total / $per_page)]);
        exit;
    }

    // ─── AI ANALYZE ───
    if ($ajax === 'ai_analyze') {
        $pid = (int)($_GET['id'] ?? 0);
        $product = DB::run("SELECT * FROM products WHERE id = ? AND tenant_id = ?", [$pid, $tenant_id])->fetch(PDO::FETCH_ASSOC);
        if (!$product) { echo json_encode(['error' => 'not_found']); exit; }
        $sales_30d = DB::run("SELECT COALESCE(SUM(si.quantity), 0) AS qty, COALESCE(SUM(si.quantity * si.unit_price), 0) AS revenue FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$pid])->fetch(PDO::FETCH_ASSOC);
        $stock = DB::run("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE product_id = ?", [$pid])->fetchColumn();
        $days_supply = ($sales_30d['qty'] > 0) ? round(($stock / ($sales_30d['qty'] / 30)), 0) : 999;
        $analysis = [];
        if ($days_supply > 90 && $stock > 0) $analysis[] = ['type'=>'zombie','icon'=>'💀','text'=>"Стока за {$days_supply} дни. Намали с 30% или пакет.",'severity'=>'high'];
        elseif ($days_supply > 45 && $stock > 0) $analysis[] = ['type'=>'slow','icon'=>'🐌','text'=>"Бавно движеща се — {$days_supply} дни запас.",'severity'=>'medium'];
        if ($stock <= $product['min_quantity'] && $stock > 0 && $product['min_quantity'] > 0) $analysis[] = ['type'=>'low','icon'=>'⚠️','text'=>"Остават {$stock} бр. (мин. {$product['min_quantity']}). Поръчай!",'severity'=>'high'];
        elseif ($stock == 0) $analysis[] = ['type'=>'out','icon'=>'🔴','text'=>"ИЗЧЕРПАН! Губиш продажби.",'severity'=>'critical'];
        if ($can_see_margin && $product['cost_price'] > 0) { $margin = round((($product['retail_price'] - $product['cost_price']) / $product['retail_price']) * 100, 1); if ($margin < 20) $analysis[] = ['type'=>'margin','icon'=>'💰','text'=>"Марж само {$margin}%.",'severity'=>'medium']; }
        if ($sales_30d['qty'] > 0) $analysis[] = ['type'=>'sales','icon'=>'📊','text'=>"30 дни: {$sales_30d['qty']} бр. / " . number_format($sales_30d['revenue'], 2, ',', '.') . " €",'severity'=>'info'];
        echo json_encode(['analysis' => $analysis, 'days_supply' => $days_supply, 'sales_30d' => $sales_30d]);
        exit;
    }

    // ─── AI CREDITS ───
    if ($ajax === 'ai_credits') {
        $credits = DB::run("SELECT ai_credits_bg, ai_credits_tryon FROM tenants WHERE id = ?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
        echo json_encode($credits); exit;
    }

    // ─── AI SCAN (Gemini Vision) ───
    if ($ajax === 'ai_scan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $image_data = $input['image'] ?? '';
        if (!$image_data) { echo json_encode(['error' => 'no_image']); exit; }
        $cats = DB::run("SELECT name FROM categories WHERE tenant_id = ?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $sups = DB::run("SELECT name FROM suppliers WHERE tenant_id = ?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $prompt = "Анализирай тази снимка на продукт. Върни САМО JSON без markdown:\n";
        $prompt .= "{\"name\":\"\",\"retail_price\":0,\"category\":\"\",\"supplier\":\"\",\"sizes\":[],\"colors\":[],\"code\":\"\",\"description\":\"\",\"unit\":\"бр\"}\n";
        $prompt .= "Категории: " . implode(', ', $cats) . "\nДоставчици: " . implode(', ', $sups) . "\n";
        $prompt .= "НЕ измисляй цени. description = SEO. code = 6-8 символа. Само JSON.";
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;
        $payload = ['contents'=>[['parts'=>[['inlineData'=>['mimeType'=>'image/jpeg','data'=>$image_data]],['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.3,'maxOutputTokens'=>500]];
        $ch = curl_init($api_url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
        $resp = curl_exec($ch); curl_close($ch);
        $data = json_decode($resp, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = preg_replace('/```json\s*|\s*```/', '', $text);
        echo json_encode(json_decode($text, true) ?: ['error'=>'parse_failed']);
        exit;
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
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;
        $payload = ['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.5,'maxOutputTokens'=>200]];
        $ch = curl_init($api_url); curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
        $resp = curl_exec($ch); curl_close($ch);
        $data = json_decode($resp, true);
        $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
        echo json_encode(['description'=>$text]); exit;
    }

    // ─── AI CODE (auto-generate article number) ───
    if ($ajax === 'ai_code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? '';
        if (!$name) { echo json_encode(['code'=>strtoupper(substr(md5(time()),0,6))]); exit; }
        $words = preg_split('/\s+/', $name);
        $code = ''; foreach ($words as $w) { $code .= mb_strtoupper(mb_substr($w, 0, 2)); }
        $code = substr($code, 0, 6) . '-' . rand(10,99);
        $exists = DB::run("SELECT id FROM products WHERE tenant_id=? AND code=?", [$tenant_id, $code])->fetch();
        if ($exists) $code .= rand(1,9);
        echo json_encode(['code'=>$code]); exit;
    }

    // ─── AI ASSIST (Gemini with rich context) ───
    if ($ajax === 'ai_assist' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $question = $input['question'] ?? '';
        if (!$question) { echo json_encode(['error'=>'no_question']); exit; }
        $stats = DB::run("SELECT COUNT(*) AS cnt, COALESCE(SUM(i.quantity),0) AS total_qty FROM products p LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1", [$store_id, $tenant_id])->fetch(PDO::FETCH_ASSOC);
        $low = DB::run("SELECT COUNT(*) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.min_quantity>0 AND i.quantity<=p.min_quantity AND i.quantity>0", [$store_id, $tenant_id])->fetchColumn();
        $out = DB::run("SELECT COUNT(*) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND i.quantity=0", [$store_id, $tenant_id])->fetchColumn();
        $zombie = DB::run("SELECT COUNT(*) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND i.quantity>0 AND DATEDIFF(NOW(), COALESCE((SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=p.id), p.created_at)) > 45", [$store_id, $tenant_id])->fetchColumn();
        $cats = DB::run("SELECT name FROM categories WHERE tenant_id=?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $sups = DB::run("SELECT name FROM suppliers WHERE tenant_id=?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $top5 = DB::run("SELECT p.name, SUM(si.quantity) AS sold FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id WHERE p.tenant_id=? AND s.store_id=? AND s.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY p.id ORDER BY sold DESC LIMIT 5", [$tenant_id, $store_id])->fetchAll(PDO::FETCH_ASSOC);
        $top5str = implode(', ', array_map(fn($t) => $t['name'].'('.$t['sold'].'бр)', $top5));
        $memories = DB::run("SELECT memory_text FROM tenant_ai_memory WHERE tenant_id=? ORDER BY created_at DESC LIMIT 10", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $memStr = implode('; ', $memories);
        $role_bg = match($user_role) { 'owner'=>'собственик', 'manager'=>'управител', default=>'продавач' };
        $systemPrompt = "Ти си AI асистент в модул 'Артикули' на RunMyStore.ai.
Магазин: {$stats['cnt']} артикула, {$stats['total_qty']} бройки, {$low} с ниска наличност, {$out} изчерпани, {$zombie} zombie.
Потребител: {$user_name}, роля: {$role_bg}.
Категории: " . implode(', ', $cats) . "
Доставчици: " . implode(', ', $sups) . "
Топ 5 (30 дни): {$top5str}
" . ($memStr ? "Памет: {$memStr}" : "") . "
ПРАВИЛА: Кратко (2-3 изречения), конкретно, с числа. Без технически термини. Разбирай жаргон: дреги=дрехи, офки=обувки, якита=якета.
ВЪРНИ САМО JSON: {\"message\":\"...\",\"action\":\"...\",\"data\":{},\"buttons\":[]}
ДЕЙСТВИЯ: search, add_product, show_zombie, show_low, show_top, navigate, product_detail, null";
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;
        $payload = ['contents'=>[['parts'=>[['text'=>$question]]]],'systemInstruction'=>['parts'=>[['text'=>$systemPrompt]]],'generationConfig'=>['temperature'=>0.3,'maxOutputTokens'=>500]];
        $ch = curl_init($api_url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
        $resp = curl_exec($ch); curl_close($ch);
        $data = json_decode($resp, true);
        $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $text = preg_replace('/```json\s*|\s*```/', '', $text);
        $parsed = json_decode($text, true);
        if ($parsed && isset($parsed['message'])) { echo json_encode($parsed); }
        else { echo json_encode(['message' => $text ?: 'Не разбрах.', 'action' => null, 'data' => new \stdClass(), 'buttons' => []]); }
        exit;
    }

    // ─── ADD SUPPLIER ───
    if ($ajax === 'add_supplier' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) { echo json_encode(['error'=>'Въведи име']); exit; }
        $exists = DB::run("SELECT id FROM suppliers WHERE tenant_id=? AND name=?", [$tenant_id, $name])->fetch();
        if ($exists) { echo json_encode(['id'=>$exists['id'], 'name'=>$name]); exit; }
        DB::run("INSERT INTO suppliers (tenant_id, name, is_active) VALUES (?,?,1)", [$tenant_id, $name]);
        echo json_encode(['id'=>DB::get()->lastInsertId(), 'name'=>$name]); exit;
    }

    // ─── ADD CATEGORY ───
    if ($ajax === 'add_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $parent = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        if (!$name) { echo json_encode(['error'=>'Въведи име']); exit; }
        $exists = DB::run("SELECT id FROM categories WHERE tenant_id=? AND name=? AND (parent_id=? OR (parent_id IS NULL AND ? IS NULL))", [$tenant_id, $name, $parent, $parent])->fetch();
        if ($exists) { echo json_encode(['id'=>$exists['id'], 'name'=>$name]); exit; }
        DB::run("INSERT INTO categories (tenant_id, name, parent_id) VALUES (?,?,?)", [$tenant_id, $name, $parent]);
        echo json_encode(['id'=>DB::get()->lastInsertId(), 'name'=>$name]); exit;
    }

    // ─── ADD SUBCATEGORY ───
    if ($ajax === 'add_subcategory' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $parent_id = (int)($_POST['parent_id'] ?? 0);
        if (!$name || !$parent_id) { echo json_encode(['error'=>'Въведи име и категория']); exit; }
        $exists = DB::run("SELECT id FROM categories WHERE tenant_id=? AND name=? AND parent_id=?", [$tenant_id, $name, $parent_id])->fetch();
        if ($exists) { echo json_encode(['id'=>$exists['id'], 'name'=>$name]); exit; }
        DB::run("INSERT INTO categories (tenant_id, name, parent_id) VALUES (?,?,?)", [$tenant_id, $name, $parent_id]);
        echo json_encode(['id'=>DB::get()->lastInsertId(), 'name'=>$name]); exit;
    }

    // ─── ADD UNIT ───
    if ($ajax === 'add_unit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $unit = trim($_POST['unit'] ?? '');
        if (!$unit) { echo json_encode(['error'=>'Въведи единица']); exit; }
        $t = DB::run("SELECT units_config FROM tenants WHERE id=?", [$tenant_id])->fetch();
        $units = json_decode($t['units_config'] ?? '[]', true) ?: [];
        if (!in_array($unit, $units)) { $units[] = $unit; DB::run("UPDATE tenants SET units_config=? WHERE id=?", [json_encode($units, JSON_UNESCAPED_UNICODE), $tenant_id]); }
        echo json_encode(['units'=>$units, 'added'=>$unit]); exit;
    }

    // ─── SKIP WHOLESALE ───
    if ($ajax === 'skip_wholesale' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        DB::run("UPDATE tenants SET skip_wholesale_price = 1 WHERE id = ?", [$tenant_id]);
        echo json_encode(['ok' => true]); exit;
    }

    // ─── AI IMAGE (fal.ai proxy) ───
    if ($ajax === 'ai_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $product_id = (int)($input['product_id'] ?? 0);
        $type = $input['type'] ?? 'bg_removal'; // bg_removal | tryon_*
        $model = $input['model'] ?? '';
        $prompt = $input['prompt'] ?? '';
        // Check credits
        if ($type === 'bg_removal') {
            $cr = DB::run("SELECT ai_credits_bg FROM tenants WHERE id=?", [$tenant_id])->fetchColumn();
            if ($cr <= 0) { echo json_encode(['error' => 'Нямаш кредити за бял фон']); exit; }
        } else {
            $cr = DB::run("SELECT ai_credits_tryon FROM tenants WHERE id=?", [$tenant_id])->fetchColumn();
            if ($cr <= 0) { echo json_encode(['error' => 'Нямаш кредити за AI Магия']); exit; }
        }
        // TODO: fal.ai integration via ai-image-processor.php
        echo json_encode(['status' => 'pending', 'message' => 'AI обработва снимката...']); exit;
    }

    // ─── SAVE LABELS (min_quantity per variation) ───
    if ($ajax === 'save_labels' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $variations = $input['variations'] ?? [];
        foreach ($variations as $v) {
            $vid = (int)($v['id'] ?? 0);
            $min_qty = (int)($v['min_quantity'] ?? 0);
            if ($vid > 0) {
                DB::run("UPDATE products SET min_quantity = ? WHERE id = ? AND tenant_id = ?", [$min_qty, $vid, $tenant_id]);
            }
        }
        echo json_encode(['ok' => true]); exit;
    }

    // ─── EXPORT LABELS ───
    if ($ajax === 'export_labels') {
        $product_id = (int)($_GET['product_id'] ?? 0);
        $format = $_GET['format'] ?? 'csv';
        $variations = DB::run("
            SELECT p.id, p.name, p.code, p.barcode, p.size, p.color, p.min_quantity,
                   COALESCE(SUM(i.quantity), 0) AS stock
            FROM products p LEFT JOIN inventory i ON i.product_id = p.id
            WHERE (p.id = ? OR p.parent_id = ?) AND p.tenant_id = ? AND p.is_active = 1
            GROUP BY p.id ORDER BY p.name
        ", [$product_id, $product_id, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="labels_' . $product_id . '.csv"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM
            fputcsv($out, ['Име', 'Код', 'Баркод', 'Размер', 'Цвят', 'Мин.кол.', 'Наличност']);
            foreach ($variations as $v) {
                fputcsv($out, [$v['name'], $v['code'], $v['barcode'], $v['size'], $v['color'], $v['min_quantity'], $v['stock']]);
            }
            fclose($out);
        } else {
            echo json_encode($variations);
        }
        exit;
    }

    // ─── IMPORT CSV ───
    if ($ajax === 'import_csv' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['file'])) { echo json_encode(['error' => 'Няма файл']); exit; }
        $file = $_FILES['file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $rows = [];
        if ($ext === 'csv') {
            $handle = fopen($file, 'r');
            $header = fgetcsv($handle);
            while (($line = fgetcsv($handle)) !== false) {
                $row = [];
                foreach ($header as $i => $col) {
                    $row[trim($col)] = $line[$i] ?? '';
                }
                $rows[] = $row;
            }
            fclose($handle);
        }
        // Return preview (first 10 rows + detected columns)
        echo json_encode([
            'columns' => array_keys($rows[0] ?? []),
            'preview' => array_slice($rows, 0, 10),
            'total' => count($rows),
            'all_rows' => $rows
        ]);
        exit;
    }

    echo json_encode(['error' => 'unknown_action']); exit;
}

// ============================================================
// PAGE DATA LOADING
// ============================================================
$all_suppliers = DB::run("SELECT id, name FROM suppliers WHERE tenant_id = ? AND is_active = 1 ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$all_categories = DB::run("SELECT id, name, parent_id FROM categories WHERE tenant_id = ? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$tenant_cfg = DB::run("SELECT units_config FROM tenants WHERE id=?", [$tenant_id])->fetch();
$onboarding_units = json_decode($tenant_cfg['units_config'] ?? '[]', true) ?: ['бр','чифт','к-кт'];

$COLOR_PALETTE = [
    ['name'=>'Черен','hex'=>'#1a1a1a'],['name'=>'Бял','hex'=>'#f5f5f5'],
    ['name'=>'Сив','hex'=>'#6b7280'],['name'=>'Червен','hex'=>'#ef4444'],
    ['name'=>'Син','hex'=>'#3b82f6'],['name'=>'Зелен','hex'=>'#22c55e'],
    ['name'=>'Жълт','hex'=>'#eab308'],['name'=>'Розов','hex'=>'#ec4899'],
    ['name'=>'Оранжев','hex'=>'#f97316'],['name'=>'Лилав','hex'=>'#8b5cf6'],
    ['name'=>'Кафяв','hex'=>'#92400e'],['name'=>'Navy','hex'=>'#1e40af'],
    ['name'=>'Бежов','hex'=>'#d4b896'],['name'=>'Бордо','hex'=>'#7f1d1d'],
    ['name'=>'Тюркоаз','hex'=>'#14b8a6'],['name'=>'Графит','hex'=>'#374151'],
    ['name'=>'Пудра','hex'=>'#f9a8d4'],['name'=>'Маслинен','hex'=>'#65a30d'],
    ['name'=>'Корал','hex'=>'#fb923c'],['name'=>'Екрю','hex'=>'#fef3c7'],
];
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
.main-wrap{position:relative;z-index:1;padding-bottom:170px;padding-top:0}

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
.bottom-nav{
    position:fixed;bottom:0;left:0;right:0;z-index:45;height:56px;
    background:rgba(3,7,18,0.95);backdrop-filter:blur(16px);
    border-top:1px solid var(--border-subtle);display:flex;align-items:center;
}
.bnav-tab{
    flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:2px;font-size:9px;font-weight:600;color:rgba(165,180,252,0.45);
    text-decoration:none;transition:all 0.3s;height:100%;
}
.bnav-tab.active{color:var(--indigo-400);text-shadow:0 0 12px rgba(99,102,241,0.8)}
.bnav-tab .bn-icon{font-size:18px;transition:all 0.3s;filter:drop-shadow(0 0 4px rgba(99,102,241,0.3))}
.bnav-tab.active .bn-icon{transform:translateY(-2px);filter:drop-shadow(0 0 10px rgba(129,140,248,0.8))}

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
.fl .fl-add{float:right;color:var(--indigo-500);font-weight:700;cursor:pointer;text-transform:none;letter-spacing:0}
.fc{
    width:100%;padding:9px 12px;border-radius:10px;border:1px solid var(--border-subtle);
    background:var(--bg-card);color:var(--text-primary);font-size:14px;outline:none;
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
.inline-add.open{display:flex}
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

    <!-- HEADER -->
    <div class="top-header">
        <div class="header-row">
            <h1 class="header-title">Артикули</h1>
            <select class="store-select" id="storeSelect" onchange="switchStore(this.value)">
                <?php foreach ($stores as $st): ?>
                <option value="<?= $st['id'] ?>" <?= $st['id'] == $store_id ? 'selected' : '' ?>><?= htmlspecialchars($st['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- SEARCH BAR -->
    <div class="search-wrap">
        <div class="search-display" id="searchDisplay" onclick="focusSearch()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <span class="ph" id="searchPh">Търси артикул, код, баркод...</span>
        </div>
        <button class="srch-btn" id="btnVoiceSearch" onclick="openVoiceSearch()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
        </button>
        <button class="srch-btn" onclick="openDrawer('filter')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
        </button>
    </div>

    <!-- ═══ SCREEN: HOME ═══ -->
    <section id="scrHome" class="screen-section active">
        <div class="tabs-row">
            <button class="tab-pill active" data-tab="all" onclick="setHomeTab('all',this)">Всички <span class="cnt" id="cntAll">-</span></button>
            <button class="tab-pill" data-tab="low" onclick="setHomeTab('low',this)">Ниска нал. <span class="cnt" id="cntLow">-</span></button>
            <button class="tab-pill" data-tab="out" onclick="setHomeTab('out',this)">Изчерпани <span class="cnt" id="cntOut">-</span></button>
        </div>
        <?php if ($can_see_margin): ?>
        <div class="stats-grid">
            <div class="stat-card"><div class="st-label">Капитал</div><div class="st-value" id="stCapital">—</div><div class="st-icon">💰</div></div>
            <div class="stat-card"><div class="st-label">Ср. марж</div><div class="st-value" id="stMargin">—</div><div class="st-icon">📊</div></div>
        </div>
        <?php else: ?>
        <div class="stats-grid">
            <div class="stat-card"><div class="st-label">Артикули</div><div class="st-value" id="stProducts">—</div><div class="st-icon">📦</div></div>
            <div class="stat-card"><div class="st-label">Общо бройки</div><div class="st-value" id="stUnits">—</div><div class="st-icon">📋</div></div>
        </div>
        <?php endif; ?>
        <div id="collapseZombie" class="collapse-sec hidden"></div>
        <div id="collapseLow" class="collapse-sec hidden"></div>
        <div id="collapseTop" class="collapse-sec hidden"></div>
        <div id="collapseSlow" class="collapse-sec hidden"></div>
        <div class="sec-title">Всички артикули</div>
        <div id="homeList" style="padding:0 16px"></div>
        <div id="homePag" class="pagination"></div>
    </section>

    <!-- ═══ SCREEN: SUPPLIERS ═══ -->
    <section id="scrSuppliers" class="screen-section">
        <div class="sec-title">Доставчици</div>
        <div class="swipe-row" id="supCards"></div>
    </section>

    <!-- ═══ SCREEN: CATEGORIES ═══ -->
    <section id="scrCategories" class="screen-section">
        <div class="sec-title">Категории</div>
        <div id="catContent" style="padding:0 16px"></div>
    </section>

    <!-- ═══ SCREEN: PRODUCTS ═══ -->
    <section id="scrProducts" class="screen-section">
        <div id="prodBreadcrumb"></div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 16px 0">
            <div class="sec-title" style="padding:0">Артикули</div>
            <div class="sort-wrap">
                <button class="sort-btn" onclick="toggleSort()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5h10"/><path d="M11 9h7"/><path d="M11 13h4"/><path d="m3 17 3 3 3-3"/><path d="M6 18V4"/></svg>Сортирай
                </button>
                <div class="sort-dd" id="sortDD">
                    <div class="sort-opt active" data-sort="name" onclick="setSort('name')">Име А→Я</div>
                    <div class="sort-opt" data-sort="price_asc" onclick="setSort('price_asc')">Цена ↑</div>
                    <div class="sort-opt" data-sort="price_desc" onclick="setSort('price_desc')">Цена ↓</div>
                    <div class="sort-opt" data-sort="stock_asc" onclick="setSort('stock_asc')">Наличност ↑</div>
                    <div class="sort-opt" data-sort="stock_desc" onclick="setSort('stock_desc')">Наличност ↓</div>
                    <div class="sort-opt" data-sort="newest" onclick="setSort('newest')">Най-нови</div>
                </div>
            </div>
        </div>
        <div id="prodList" style="padding:0 16px;margin-top:6px"></div>
        <div id="prodPag" class="pagination"></div>
    </section>

</div><!-- /main-wrap -->

<!-- ═══ QUICK ACTIONS PILL BAR ═══ -->
<?php if ($can_add): ?>
<div class="qa-bar" id="qaBar">
    <button class="qa-btn qa-ai" onclick="openAIWizard()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="2" stroke-linecap="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
        <span>AI Модул Артикули</span>
    </button>
    <button class="qa-btn qa-other qa-teal" onclick="openManualWizard()">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--teal)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
        <span>Ръчно</span>
    </button>
    <button class="qa-btn qa-other qa-green" onclick="openCSVImport()">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <span>CSV</span>
    </button>
    <button class="qa-btn qa-other qa-yellow" onclick="openCamera('scan')">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2" stroke-linecap="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="4" y1="12" x2="20" y2="12"/></svg>
        <span>Скан</span>
    </button>
</div>
<?php endif; ?>

<!-- ═══ SCREEN NAV ═══ -->
<div class="screen-nav"><div class="screen-nav-inner">
    <button class="sn-btn" data-scr="products" onclick="goScreenWithHistory('products')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        <span>Артикули</span>
    </button>
    <button class="sn-btn" data-scr="categories" onclick="goScreenWithHistory('categories')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        <span>Категории</span>
    </button>
    <button class="sn-btn" data-scr="suppliers" onclick="goScreenWithHistory('suppliers')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        <span>Доставчици</span>
    </button>
    <button class="sn-btn active" data-scr="home" onclick="goScreenWithHistory('home')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        <span>Начало</span>
    </button>
</div></div>

<!-- ═══ BOTTOM NAV ═══ -->
<nav class="bottom-nav">
    <a href="chat.php" class="bnav-tab"><span class="bn-icon">✦</span>AI</a>
    <a href="warehouse.php" class="bnav-tab active"><span class="bn-icon">📦</span>Склад</a>
    <a href="stats.php" class="bnav-tab"><span class="bn-icon">📊</span>Справки</a>
    <a href="sale.php" class="bnav-tab"><span class="bn-icon">⚡</span>Въвеждане</a>
</nav>

<!-- ═══ DRAWERS ═══ -->
<div class="drawer-ov" id="detailOv" onclick="closeDrawer('detail')"></div>
<div class="drawer" id="detailDr"><div class="drawer-handle"></div><div class="drawer-hdr"><h3 id="detailTitle">Артикул</h3><button class="drawer-close" onclick="closeDrawer('detail')">✕</button></div><div id="detailBody"></div></div>

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
<div class="drawer" id="csvDr"><div class="drawer-handle"></div><div class="drawer-hdr"><h3>📄 CSV / Excel Import</h3><button class="drawer-close" onclick="closeDrawer('csv')">✕</button></div><div id="csvBody"></div></div>

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
    const thumb=p.image?`<img src="${p.image}">`:`<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="rgba(99,102,241,0.3)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>`;
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
    const d=await api(`products.php?ajax=home_stats&store_id=${CFG.storeId}`);
    if(!d)return;
    if(CFG.canSeeMargin){
        document.getElementById('stCapital').textContent=fmtPrice(d.capital);
        document.getElementById('stMargin').textContent=d.avg_margin!=null?d.avg_margin+'%':'—';
    }else{
        const sp=document.getElementById('stProducts'),su=document.getElementById('stUnits');
        if(sp)sp.textContent=fmtNum(d.counts?.total_products);
        if(su)su.textContent=fmtNum(d.counts?.total_units);
    }
    document.getElementById('cntAll').textContent=d.counts?.total_products||0;
    document.getElementById('cntLow').textContent=d.low_stock?.length||0;
    document.getElementById('cntOut').textContent=d.out_of_stock?.length||0;

    renderCollapse('collapseZombie','💀','Zombie стока',d.zombies,p=>`<div class="p-card" onclick="openProductDetail(${p.id})" style="margin-bottom:4px"><div class="stock-bar red"></div><div class="p-thumb">${p.image?`<img src="${p.image}">`:'💀'}</div><div class="p-info"><div class="p-name">${esc(p.name)}</div><div class="p-meta"><span>${p.days_stale}д без продажба</span></div></div><div class="p-right"><div class="p-price">${fmtPrice(p.retail_price)}</div><div class="p-stock out">${p.qty} бр.</div></div></div>`);
    renderCollapse('collapseLow','⚠️','Свършват скоро',d.low_stock,p=>`<div class="p-card" onclick="openProductDetail(${p.id})" style="margin-bottom:4px"><div class="stock-bar yellow"></div><div class="p-thumb">${p.image?`<img src="${p.image}">`:'⚠️'}</div><div class="p-info"><div class="p-name">${esc(p.name)}</div><div class="p-meta"><span>Мин: ${p.min_quantity}</span></div></div><div class="p-right"><div class="p-price">${fmtPrice(p.retail_price)}</div><div class="p-stock low">${p.qty} бр.</div></div></div>`);
    renderCollapse('collapseTop','🔥','Топ 5 хитове',d.top_sellers,(p,i)=>`<div class="p-card" onclick="openProductDetail(${p.id})" style="margin-bottom:4px"><div class="stock-bar green"></div><div class="p-thumb" style="font-size:13px;font-weight:800;color:var(--indigo-300)">#${i+1}</div><div class="p-info"><div class="p-name">${esc(p.name)}</div><div class="p-meta"><span>${p.sold_qty} продадени</span></div></div><div class="p-right"><div class="p-price">${fmtPrice(p.revenue)}</div></div></div>`);
    renderCollapse('collapseSlow','🐌','Бавни',d.slow_movers,p=>`<div class="p-card" onclick="openProductDetail(${p.id})" style="margin-bottom:4px"><div class="stock-bar yellow"></div><div class="p-thumb">${p.image?`<img src="${p.image}">`:'🐌'}</div><div class="p-info"><div class="p-name">${esc(p.name)}</div><div class="p-meta"><span>${p.days_stale}д</span></div></div><div class="p-right"><div class="p-price">${fmtPrice(p.retail_price)}</div><div class="p-stock low">${p.qty} бр.</div></div></div>`);
    loadHomeProducts();
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
    let p=`store_id=${CFG.storeId}&sort=${S.sort}&page=${S.page}`;
    if(S.supId)p+=`&sup=${S.supId}`;
    if(S.catId)p+=`&cat=${S.catId}`;
    if(S.filter!=='all')p+=`&filter=${S.filter}`;
    const d=await api(`products.php?ajax=products&${p}`);
    if(!d)return;
    document.getElementById('prodList').innerHTML=d.products.length===0?
        `<div class="empty-st"><div class="es-icon">📋</div><div class="es-text">Няма артикули</div></div>`:
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
    else el.textContent='Търси артикул, код, баркод...';
}
async function doSearch(q){
    const d=await api(`products.php?ajax=search&q=${encodeURIComponent(q)}&store_id=${CFG.storeId}`);
    if(!d)return;
    const target=S.screen==='products'?'prodList':'homeList';
    document.getElementById(target).innerHTML=d.length===0?
        `<div class="empty-st"><div class="es-icon">🔍</div><div class="es-text">Нищо за "${esc(q)}"</div></div>`:
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

// ─── PRODUCT DETAIL ───
async function openProductDetail(id){
    openDrawer('detail');
    document.getElementById('detailBody').innerHTML='<div style="text-align:center;padding:20px"><div class="skeleton" style="width:60%;height:18px;margin:0 auto 10px"></div></div>';
    const d=await api(`products.php?ajax=product_detail&id=${id}`);
    if(!d||d.error){showToast('Грешка','error');closeDrawer('detail');return}
    const p=d.product;
    document.getElementById('detailTitle').textContent=p.name;
    let h='';
    if(p.image)h+=`<div style="text-align:center;margin-bottom:12px"><img src="${p.image}" style="max-width:180px;border-radius:12px;border:1px solid var(--border-subtle)"></div>`;
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

// ─── SWIPE NAVIGATION ───
let touchStartX=0;
const swipePages=['chat.php','warehouse.php','stats.php','sale.php'];
const curPageIdx=1; // warehouse/products = index 1
document.addEventListener('touchstart',e=>{
    if(e.target.closest('.drawer,.modal-ov,.rec-ov,.camera-ov,.swipe-row'))return;
    touchStartX=e.touches[0].clientX;
},{passive:true});
document.addEventListener('touchend',e=>{
    if(!touchStartX)return;
    const dx=e.changedTouches[0].clientX-touchStartX;
    touchStartX=0;
    if(Math.abs(dx)<80)return;
    if(dx>0&&curPageIdx>0)location.href=swipePages[curPageIdx-1];
    if(dx<0&&curPageIdx<swipePages.length-1)location.href=swipePages[curPageIdx+1];
});


// ═══════════════════════════════════════════════════════════
// PART 4: AI WIZARD + MANUAL WIZARD + CSV + IMAGE STUDIO + LABELS
// ═══════════════════════════════════════════════════════════

// ─── AI WIZARD (voice overlay, hint-based Q&A via ai-wizard.php) ───
function openAIWizard(){
    S.aiWizMode=true;
    S.aiWizConversation=[];
    S.aiWizCollected={};

    const ov=document.getElementById('recOv');
    const dot=document.getElementById('recDot');
    const label=document.getElementById('recLabel');
    const transcript=document.getElementById('recTranscript');
    const hint=document.getElementById('recHint');
    const sendBtn=document.getElementById('recSend');

    dot.className='rec-dot';
    label.className='rec-label recording';
    label.textContent='● ЗАПИСВА';
    transcript.textContent='Слушам...';
    transcript.classList.add('empty');
    hint.textContent='Какво добавяме? Кажи или снимай';
    sendBtn.disabled=true;
    S.lastTranscript='';

    // Override send to wizard mode
    sendBtn.onclick=()=>{sendAIWizardVoice()};
    document.getElementById('recCancel').onclick=()=>{closeAIWizardOverlay()};

    ov.classList.add('open');

    // Start voice
    setTimeout(()=>startAIWizardVoice(),400);
}

function closeAIWizardOverlay(){
    S.aiWizMode=false;
    if(S.recognition)try{S.recognition.stop()}catch(e){}
    S.recognition=null;
    document.getElementById('recOv').classList.remove('open');
    // Restore default handlers
    document.getElementById('recSend').onclick=null;
    document.getElementById('recCancel').onclick=()=>{closeVoice()};
}

function startAIWizardVoice(){
    const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(!SR){showToast('Гласовото не е поддържано','error');return}

    const dot=document.getElementById('recDot');
    const label=document.getElementById('recLabel');
    const transcript=document.getElementById('recTranscript');
    const sendBtn=document.getElementById('recSend');

    dot.className='rec-dot';
    label.className='rec-label recording';
    label.textContent='● ЗАПИСВА';
    transcript.textContent='Слушам...';
    transcript.classList.add('empty');
    sendBtn.disabled=true;
    S.lastTranscript='';

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
        transcript.innerText=S.lastTranscript;
        transcript.classList.remove('empty');
        sendBtn.disabled=false;
    };
    rec.onerror=()=>{
        dot.classList.add('ready');
        label.textContent='ГРЕШКА';
    };
    rec.onend=()=>{
        if(!S.lastTranscript){
            dot.classList.add('ready');
            label.className='rec-label';
            label.textContent='ЧАКАМ';
        }
    };
    try{rec.start()}catch(e){}
}

async function sendAIWizardVoice(){
    const txt=S.lastTranscript;
    if(!txt)return;
    if(S.recognition)try{S.recognition.stop()}catch(e){}

    const hint=document.getElementById('recHint');
    const label=document.getElementById('recLabel');
    const transcript=document.getElementById('recTranscript');
    const sendBtn=document.getElementById('recSend');

    // Show user text briefly
    label.textContent='✦ AI мисли...';
    sendBtn.disabled=true;

    S.aiWizConversation.push({role:'user',text:txt});

    try{
        const d=await api('ai-wizard.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({
                business_type:CFG.businessType,
                image_base64:null,
                image_type:null,
                conversation:S.aiWizConversation,
                collected:S.aiWizCollected
            })
        });

        if(d.collected)S.aiWizCollected=d.collected;

        if(d.done){
            // Save product
            hint.textContent='Записвам...';
            label.textContent='✦ ЗАПИС';
            await doAIWizardSave(d);
            return;
        }

        // Show AI response in hint, prepare for next answer
        S.aiWizConversation.push({role:'ai',text:d.message||''});
        hint.textContent=d.message||'Продължи...';
        transcript.textContent='Слушам...';
        transcript.classList.add('empty');
        label.textContent='● ЗАПИСВА';
        sendBtn.disabled=true;

        // Auto-start next voice
        setTimeout(()=>startAIWizardVoice(),500);

    }catch(e){
        hint.textContent='Грешка. Опитай пак.';
        label.textContent='✦ AI Wizard';
        sendBtn.disabled=false;
    }
}

async function doAIWizardSave(d){
    const c=d.collected||{};
    const payload={
        name:c.name||'',barcode:c.barcode||'',
        retail_price:parseFloat(c.retail_price)||0,
        wholesale_price:parseFloat(c.wholesale_price)||0,
        cost_price:0,supplier_id:d.supplier_id||null,category_id:d.category_id||null,
        code:c.code||'',unit:c.unit||'бр',min_quantity:parseInt(c.min_quantity)||0,
        description:c.description||'',
        product_type:(d.variants_preview&&d.variants_preview.length>1)?'variant':'simple',
        sizes:[],colors:[],variants:d.variants_preview||[{size:null,color:null,qty:0}]
    };
    if(d.variants_preview){
        const ss={},cs={};
        d.variants_preview.forEach(v=>{if(v.size)ss[v.size]=1;if(v.color)cs[v.color]=1});
        payload.sizes=Object.keys(ss);payload.colors=Object.keys(cs);
    }
    try{
        const r=await api('product-save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        if(r&&(r.success||r.id)){
            showToast('Артикулът е добавен!','success');
            closeAIWizardOverlay();
            // Generate AI description
            if(c.name){
                api('products.php?ajax=ai_description',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:c.name,category:c.category||'',supplier:c.supplier||''})});
            }
            // Open labels screen
            setTimeout(()=>openLabels(r.id),500);
            loadScreen();
        }else{
            showToast(r?.error||'Грешка при запис','error');
            closeAIWizardOverlay();
        }
    }catch(e){showToast('Мрежова грешка','error')}
}

// ─── MANUAL WIZARD (6 steps) ───
const WIZ_LABELS=['Вид','Снимка + AI','Основна информация','Варианти','Детайли','Преглед и запис'];

function openManualWizard(){
    S.wizStep=0;S.wizData={};S.wizType=null;S.wizEditId=null;
    document.getElementById('wizTitle').textContent='Нов артикул';
    renderWizard();
    history.pushState({modal:'wizard'}, '', '#wizard');
    document.getElementById('wizModal').classList.add('open');
    document.body.style.overflow='hidden';
}
function closeWizard(){
    document.getElementById('wizModal').classList.remove('open');
    document.body.style.overflow='';
}
function wizGo(step){if(S.wizStep>=2&&S.wizStep<=4)wizCollectData();S.wizStep=step;renderWizard()}

function renderWizard(){
    let sb='';
    for(let i=0;i<6;i++){
        let cls=i<S.wizStep?'done':i===S.wizStep?'active':'';
        sb+=`<div class="wiz-step ${cls}"></div>`;
    }
    document.getElementById('wizSteps').innerHTML=sb;
    document.getElementById('wizLabel').innerHTML=`${S.wizStep+1} · <b>${WIZ_LABELS[S.wizStep]}</b>`;
    document.getElementById('wizBody').innerHTML=renderWizPage(S.wizStep);
    document.getElementById('wizBody').scrollTop=0;
    // Subcategory loader for step 2
    if(S.wizStep===2){const wCat=document.getElementById('wCat');if(wCat){wCat.onchange=async function(){const id=this.value;const sel=document.getElementById('wSubcat');sel.innerHTML='<option value="">\u2014 \u041d\u044f\u043c\u0430 \u2014</option>';if(!id)return;const d=await api('products.php?ajax=subcategories&parent_id='+id);if(d&&d.length)d.forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.name;sel.appendChild(o)})};if(S.wizData.category_id)wCat.onchange()}}
}

function renderWizPage(step){
    if(step===0){
        const ss=S.wizType==='single'?'border-color:var(--indigo-500);background:rgba(99,102,241,0.1);box-shadow:0 0 14px rgba(99,102,241,0.15)':'';
        const vs=S.wizType==='variant'?'border-color:var(--indigo-500);background:rgba(99,102,241,0.1);box-shadow:0 0 14px rgba(99,102,241,0.15)':'';
        return`<div class="wiz-page active"><div style="font-size:15px;font-weight:700;margin-bottom:4px">Какъв е артикулът?</div><div style="font-size:12px;color:var(--text-secondary);margin-bottom:14px">Изборът определя следващите стъпки</div><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px"><div style="padding:18px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);text-align:center;cursor:pointer;${ss}" onclick="S.wizType='single';renderWizard()"><div style="font-size:28px;margin-bottom:4px">📦</div><div style="font-size:13px;font-weight:600">Единичен</div></div><div style="padding:18px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);text-align:center;cursor:pointer;${vs}" onclick="S.wizType='variant';renderWizard()"><div style="font-size:28px;margin-bottom:4px">🎨</div><div style="font-size:13px;font-weight:600">С варианти</div></div></div>${S.wizType?`<button class="abtn primary" onclick="wizGo(1)">Напред →</button>`:''}</div>`;
    }
    if(step===1){
        return`<div class="wiz-page active" style="text-align:center"><div style="font-size:15px;font-weight:600;margin-bottom:4px">Снимай артикула</div><div style="font-size:12px;color:var(--text-secondary);margin-bottom:16px">AI разпознава от снимка</div><div style="width:80px;height:80px;margin:0 auto 14px;border-radius:50%;background:linear-gradient(135deg,var(--indigo-500),var(--indigo-600));display:flex;align-items:center;justify-content:center;box-shadow:0 0 30px rgba(99,102,241,0.4);cursor:pointer" onclick="wizTakePhoto()"><span style="font-size:24px">📷</span></div><div id="wizPhotoPreview"></div><div id="wizScanResult"></div><button class="abtn" onclick="wizGo(2)" style="margin-top:12px">Пропусни → ръчно</button><button class="abtn" onclick="wizGo(0)" style="margin-top:6px">← Назад</button></div>`;
    }
    if(step===2){
        const nm=S.wizData.name||'';
        const pr=S.wizData.retail_price||'';
        const wp=S.wizData.wholesale_price||'';
        const bc=S.wizData.barcode||'';
        let supO='<option value="">— Избери —</option>';
        CFG.suppliers.forEach(s=>supO+=`<option value="${s.id}" ${S.wizData.supplier_id==s.id?'selected':''}>${esc(s.name)}</option>`);
        let catO='<option value="">— Избери —</option>';
        CFG.categories.filter(c=>!c.parent_id).forEach(c=>catO+=`<option value="${c.id}" ${S.wizData.category_id==c.id?'selected':''}>${esc(c.name)}</option>`);
        const wpHidden=CFG.skipWholesale?'display:none':'';
        return`<div class="wiz-page active">
            <div class="fg"><label class="fl">Наименование *</label><input type="text" class="fc" id="wName" value="${esc(nm)}" placeholder="напр. Nike Air Max"></div>
            <div class="fg"><label class="fl">Артикулен номер * <span class="hint">(AI генерира ако е празно)</span></label><input type="text" class="fc" id="wCode" value="${esc(S.wizData.code||'')}" placeholder="ROK-EL-01"></div>
            <div class="form-row">
                <div class="fg"><label class="fl">Цена дребно *</label><input type="number" step="0.01" class="fc" id="wPrice" value="${pr}" placeholder="0,00"></div>
                <div class="fg" style="${wpHidden}"><label class="fl">Цена едро</label><input type="number" step="0.01" class="fc" id="wWprice" value="${wp}" placeholder="0,00"></div>
            </div>
            <div class="fg"><label class="fl">Баркод <span class="hint">(празно = автоматично)</span></label><input type="text" class="fc" id="wBarcode" value="${esc(bc)}" placeholder="Сканирай или въведи"></div>
            <div class="fg"><label class="fl">Доставчик <span class="fl-add" onclick="toggleInl('inlSup')">+ Нов</span></label><select class="fc" id="wSup">${supO}</select><div class="inline-add" id="inlSup"><input type="text" placeholder="Име" id="inlSupName"><button onclick="wizAddInline('supplier')">Запази</button></div></div>
            <div class="fg"><label class="fl">Категория <span class="fl-add" onclick="toggleInl('inlCat')">+ Нова</span></label><select class="fc" id="wCat">${catO}</select><div class="inline-add" id="inlCat"><input type="text" placeholder="Име" id="inlCatName"><button onclick="wizAddInline('category')">Запази</button></div></div>
            <div class="fg"><label class="fl">Подкатегория <span class="hint">(не задължителна)</span></label><select class="fc" id="wSubcat"><option value="">— Няма —</option></select></div>
            <button class="abtn primary" onclick="wizGo(3)">Напред →</button>
            <button class="abtn" onclick="wizGo(1)" style="margin-top:6px">← Назад</button></div>`;
    }
    if(step===3){
        if(S.wizType==='single')return`<div class="wiz-page active"><div style="font-size:13px;color:var(--text-secondary);margin-bottom:14px">Единичен артикул — без варианти.</div><button class="abtn primary" onclick="wizGo(4)">Напред →</button><button class="abtn" onclick="wizGo(2)" style="margin-top:6px">← Назад</button></div>`;
        // Variants page - unlimited axes
        S.wizData.axes=S.wizData.axes||[];
        let axesH='';
        S.wizData.axes.forEach((ax,i)=>{
            const vals=ax.values.map((v,vi)=>`<span style="display:inline-block;padding:3px 9px;border-radius:6px;background:rgba(99,102,241,0.12);color:var(--indigo-300);font-size:11px;font-weight:600;margin:2px;cursor:pointer" onclick="S.wizData.axes[${i}].values.splice(${vi},1);renderWizard()">${esc(v)} ✕</span>`).join('');
            axesH+=`<div style="margin-bottom:12px;padding:10px;border-radius:10px;background:rgba(17,24,44,0.5);border:1px solid var(--border-subtle)"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><span style="font-size:12px;font-weight:700;color:var(--indigo-300)">${esc(ax.name)}</span><span style="font-size:10px;color:var(--danger);cursor:pointer" onclick="S.wizData.axes.splice(${i},1);renderWizard()">✕ Махни</span></div><div style="margin-bottom:6px">${vals||'<span style="font-size:10px;color:var(--text-secondary)">Няма стойности</span>'}</div><div style="display:flex;gap:6px"><input type="text" class="fc" id="axVal${i}" placeholder="Добави стойност..." style="font-size:12px;padding:6px 10px"><button class="abtn" style="width:auto;padding:6px 12px;font-size:11px" onclick="wizAddAxisValue(${i})">+</button></div></div>`;
        });
        return`<div class="wiz-page active"><div style="font-size:9px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin-bottom:8px">Оси на вариация</div>${axesH}<div style="display:flex;gap:6px;margin-bottom:14px"><input type="text" class="fc" id="newAxisName" placeholder="Нова ос (напр. Материал)" style="font-size:12px;padding:6px 10px"><button class="abtn" style="width:auto;padding:6px 12px;font-size:11px" onclick="wizAddAxis()">+ Добави ос</button></div><div style="font-size:10px;color:var(--text-secondary);margin-bottom:14px">Кръстоска: ${wizCountCombinations()} вариации</div><button class="abtn primary" onclick="wizGo(4)">Напред →</button><button class="abtn" onclick="wizGo(2)" style="margin-top:6px">← Назад</button></div>`;
    }
    if(step===4){
        let unitO='';CFG.units.forEach(u=>unitO+=`<option value="${u}" ${S.wizData.unit===u?'selected':''}>${u}</option>`);
        return`<div class="wiz-page active">
            <div class="fg"><label class="fl">Мерна единица</label><select class="fc" id="wUnit">${unitO}</select></div>
            <div class="fg"><label class="fl">Мин. наличност</label><input type="number" class="fc" id="wMinQty" value="${S.wizData.min_quantity||0}"></div>
            <div class="fg"><label class="fl">SEO описание <span class="hint">✦ AI генерира</span></label><textarea class="fc" id="wDesc" rows="3" placeholder="AI ще попълни...">${esc(S.wizData.description||'')}</textarea></div>
            <button class="abtn primary" onclick="wizGoPreview()">Напред →</button>
            <button class="abtn" onclick="wizGo(3)" style="margin-top:6px">← Назад</button></div>`;
    }
    if(step===5){
        wizCollectData();
        const combos=wizBuildCombinations();
        let combosH='';
        if(combos.length<=1&&!combos[0]?.axisValues){
            combosH=`<div class="form-row"><div class="fg"><label class="fl">Начална наличност</label><input type="number" class="fc" id="wSingleQty" value="0"></div><div class="fg"><label class="fl">Мин. наличност</label><input type="number" class="fc" id="wSingleMin" value="${S.wizData.min_quantity||0}"></div></div>`;
        }else{
            combosH='<div style="font-size:9px;color:var(--text-secondary);margin-bottom:4px;font-weight:700;text-transform:uppercase">'+combos.length+' вариации</div>';
            combos.forEach((v,i)=>{
                const label=v.axisValues||v.label||'';
                combosH+=`<div style="display:flex;gap:4px;align-items:center;margin-bottom:3px;padding:4px 8px;border-radius:6px;background:rgba(17,24,44,0.3)"><span style="font-size:11px;flex:1">${esc(label)}</span><input type="number" class="fc" style="width:50px;padding:4px;text-align:center;font-size:12px" value="0" data-combo="${i}"></div>`;
            });
        }
        return`<div class="wiz-page active">
            <div style="font-size:14px;font-weight:700;margin-bottom:2px">${esc(S.wizData.name||'Артикул')}</div>
            <div style="font-size:11px;color:var(--text-secondary);margin-bottom:10px">Цена: ${fmtPrice(S.wizData.retail_price)} · Код: ${esc(S.wizData.code||'AI генерира')}</div>
            ${combosH}
            <button class="abtn save" style="margin-top:14px;font-size:15px;padding:14px" onclick="wizSave()">✓ Запази артикула</button>
            <button class="abtn" onclick="wizGo(4)" style="margin-top:6px">← Назад</button></div>`;
    }
    return'';
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
function wizCountCombinations(){
    if(!S.wizData.axes||!S.wizData.axes.length)return 0;
    return S.wizData.axes.reduce((acc,ax)=>acc*(ax.values.length||1),1);
}
function wizBuildCombinations(){
    if(!S.wizData.axes||!S.wizData.axes.length)return[{label:'Единичен',qty:0}];
    const axes=S.wizData.axes.filter(a=>a.values.length>0);
    if(!axes.length)return[{label:'Единичен',qty:0}];
    let combos=[{parts:[]}];
    for(const ax of axes){
        const next=[];
        for(const combo of combos){
            for(const val of ax.values){
                next.push({parts:[...combo.parts,{axis:ax.name,value:val}]});
            }
        }
        combos=next;
    }
    return combos.map(c=>({
        axisValues:c.parts.map(p=>p.value).join(' / '),
        parts:c.parts,qty:0
    }));
}

function wizCollectData(){
    const name=document.getElementById('wName')?.value.trim();
    if(name)S.wizData.name=name;
    S.wizData.code=document.getElementById('wCode')?.value.trim()||'';
    S.wizData.retail_price=parseFloat(document.getElementById('wPrice')?.value)||0;
    S.wizData.wholesale_price=parseFloat(document.getElementById('wWprice')?.value)||0;
    S.wizData.barcode=document.getElementById('wBarcode')?.value.trim()||'';
    S.wizData.supplier_id=document.getElementById('wSup')?.value||null;
    S.wizData.category_id=document.getElementById('wCat')?.value||null;
    S.wizData.unit=document.getElementById('wUnit')?.value||'бр';
    S.wizData.min_quantity=parseInt(document.getElementById('wMinQty')?.value)||0;
    S.wizData.description=document.getElementById('wDesc')?.value||'';
}

async function wizGoPreview(){
    wizCollectData();
    if(!S.wizData.name){showToast('Въведи наименование','error');wizGo(2);return}
    if(!S.wizData.retail_price){showToast('Въведи цена','error');wizGo(2);return}
    // Auto-generate code if empty
    if(!S.wizData.code&&S.wizData.name){
        const d=await api('products.php?ajax=ai_code',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:S.wizData.name})});
        if(d?.code)S.wizData.code=d.code;
    }
    // Auto-generate description if empty
    if(!S.wizData.description&&S.wizData.name){
        showToast('AI генерира описание...','');
        const cat=document.getElementById('wCat')?.selectedOptions[0]?.text||'';
        const sup=document.getElementById('wSup')?.selectedOptions[0]?.text||'';
        const d=await api('products.php?ajax=ai_description',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:S.wizData.name,category:cat,supplier:sup})});
        if(d?.description){S.wizData.description=d.description;showToast('Описание ✓','success')}
    }
    wizGo(5);
}

async function wizSave(){
    wizCollectData();
    if(!S.wizData.name){showToast('Въведи наименование','error');return}
    const combos=wizBuildCombinations();
    // Collect quantities from preview inputs
    document.querySelectorAll('[data-combo]').forEach(inp=>{
        const idx=parseInt(inp.dataset.combo);
        if(combos[idx])combos[idx].qty=parseInt(inp.value)||0;
    });
    const singleQty=parseInt(document.getElementById('wSingleQty')?.value)||0;

    // Build sizes/colors from axes for backward compat
    // Оси "размер/size" → sizes[], "цвят/color" → colors[]
    // Останалите оси се конкатенират в size поле (напр. "42 / Памук")
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
        // Extra axes concat into size field: "42 / Памук"
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
            closeWizard();
            setTimeout(()=>openLabels(r.id),500);
            loadScreen();
        }else{showToast(r?.error||'Грешка','error')}
    }catch(e){showToast('Мрежова грешка','error')}
}

// Wizard photo
function wizTakePhoto(){document.getElementById('photoInput').click()}
document.getElementById('photoInput').addEventListener('change',async function(){
    if(!this.files?.[0])return;
    const preview=document.getElementById('wizPhotoPreview');
    const result=document.getElementById('wizScanResult');
    if(preview)preview.innerHTML='<div style="font-size:12px;color:var(--text-secondary);margin-top:8px">📷 Снимка заредена</div>';
    if(result)result.innerHTML='<div style="font-size:12px;color:var(--indigo-300);margin-top:6px">✦ AI анализира...</div>';

    const reader=new FileReader();
    reader.onload=async e=>{
        const base64=e.target.result.split(',')[1];
        const d=await api('products.php?ajax=ai_scan',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({image:base64})});
        if(d&&!d.error){
            S.wizData={...S.wizData,...d};
            if(d.sizes?.length){
                S.wizData.axes=S.wizData.axes||[];
                if(!S.wizData.axes.find(a=>a.name.toLowerCase().includes('размер'))){
                    S.wizData.axes.push({name:'Размер',values:d.sizes});
                }
                if(!S.wizType)S.wizType='variant';
            }
            if(d.colors?.length){
                S.wizData.axes=S.wizData.axes||[];
                if(!S.wizData.axes.find(a=>a.name.toLowerCase().includes('цвят'))){
                    S.wizData.axes.push({name:'Цвят',values:d.colors});
                }
            }
            if(result)result.innerHTML='<div style="font-size:12px;color:var(--success);margin-top:6px">✓ AI разпозна — данните са попълнени</div>';
            showToast('AI разпозна ✓','success');
            setTimeout(()=>wizGo(2),800);
        }else{
            if(result)result.innerHTML='<div style="font-size:12px;color:var(--warning);margin-top:6px">AI не разпозна — попълни ръчно</div>';
        }
    };
    reader.readAsDataURL(this.files[0]);
    this.value='';
});

// Inline add supplier/category
function toggleInl(id){document.getElementById(id)?.classList.toggle('open')}
async function wizAddInline(type){
    if(type==='supplier'){
        const n=document.getElementById('inlSupName')?.value.trim();
        if(!n)return;
        const d=await api('products.php?ajax=add_supplier',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`name=${encodeURIComponent(n)}`});
        if(d?.id){CFG.suppliers.push({id:d.id,name:d.name});S.wizData.supplier_id=d.id;showToast('Добавен ✓','success');renderWizard()}
    }else{
        const n=document.getElementById('inlCatName')?.value.trim();
        if(!n)return;
        const d=await api('products.php?ajax=add_category',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`name=${encodeURIComponent(n)}`});
        if(d?.id){CFG.categories.push({id:d.id,name:d.name});S.wizData.category_id=d.id;showToast('Добавена ✓','success');renderWizard()}
    }
}

// Edit existing product
async function editProduct(id){
    closeDrawer('detail');
    const d=await api(`products.php?ajax=product_detail&id=${id}`);
    if(!d||d.error)return;
    const p=d.product;
    S.wizEditId=id;S.wizType='single';S.wizStep=2;
    S.wizData={name:p.name,code:p.code,retail_price:p.retail_price,wholesale_price:p.wholesale_price,
        barcode:p.barcode,supplier_id:p.supplier_id,category_id:p.category_id,
        description:p.description,unit:p.unit,min_quantity:p.min_quantity,axes:[]};
    document.getElementById('wizTitle').textContent='Редактирай';
    renderWizard();
    history.pushState({modal:'wizard'}, '', '#wizard');
    document.getElementById('wizModal').classList.add('open');
    document.body.style.overflow='hidden';
}

// ─── CSV IMPORT ───
function openCSVImport(){
    openDrawer('csv');
    document.getElementById('csvBody').innerHTML=`
        <div style="text-align:center;padding:20px">
            <div style="font-size:36px;margin-bottom:8px">📄</div>
            <div style="font-size:14px;font-weight:600;margin-bottom:4px">Качи CSV или Excel файл</div>
            <div style="font-size:11px;color:var(--text-secondary);margin-bottom:16px">AI ще разпознае колоните автоматично</div>
            <button class="abtn primary" onclick="document.getElementById('csvInput').click()">📁 Избери файл</button>
        </div>`;
}
document.getElementById('csvInput').addEventListener('change',async function(){
    if(!this.files?.[0])return;
    const fd=new FormData();
    fd.append('file',this.files[0]);
    document.getElementById('csvBody').innerHTML='<div style="text-align:center;padding:20px">✦ Парсвам файла...</div>';
    const d=await api('products.php?ajax=import_csv',{method:'POST',body:fd});
    if(!d||d.error){showToast(d?.error||'Грешка','error');return}
    // Show preview
    let h=`<div style="font-size:13px;font-weight:700;margin-bottom:8px">${d.total} реда намерени</div>`;
    h+=`<div style="font-size:10px;color:var(--text-secondary);margin-bottom:8px">Колони: ${d.columns.join(', ')}</div>`;
    h+=`<div style="overflow-x:auto;margin-bottom:14px"><table style="width:100%;font-size:10px;border-collapse:collapse">`;
    h+=`<tr>${d.columns.map(c=>`<th style="padding:4px 6px;border-bottom:1px solid var(--border-subtle);color:var(--indigo-300);text-align:left;white-space:nowrap">${esc(c)}</th>`).join('')}</tr>`;
    d.preview.forEach(row=>{
        h+=`<tr>${d.columns.map(c=>`<td style="padding:4px 6px;border-bottom:1px solid rgba(99,102,241,0.06);white-space:nowrap">${esc(row[c]||'')}</td>`).join('')}</tr>`;
    });
    h+=`</table></div>`;
    h+=`<button class="abtn save" onclick="doCSVImport()">✓ Импортирай ${d.total} артикула</button>`;
    document.getElementById('csvBody').innerHTML=h;
    // Store for import
    S.csvData=d;
    this.value='';
});

async function doCSVImport(){
    if(!S.csvData)return;
    showToast('Импортирам...','');
    let imported=0,errors=0;
    for(const row of S.csvData.all_rows){
        const name=row['Наименование']||row['Име']||row['name']||row['Name']||'';
        if(!name){errors++;continue}
        const payload={
            name,
            code:row['Код']||row['code']||row['SKU']||'',
            barcode:row['Баркод']||row['barcode']||row['EAN']||'',
            retail_price:parseFloat((row['Цена']||row['price']||row['Цена дребно']||'0').replace(',','.'))||0,
            wholesale_price:parseFloat((row['Цена едро']||row['wholesale']||'0').replace(',','.'))||0,
            cost_price:0,unit:row['Единица']||row['unit']||'бр',
            min_quantity:parseInt(row['Мин.кол.']||row['min_quantity']||'0')||0,
            description:row['Описание']||row['description']||'',
            product_type:'simple',sizes:[],colors:[],variants:[{size:null,color:null,qty:0}]
        };
        try{
            const r=await api('product-save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
            if(r&&(r.success||r.id))imported++;else errors++;
        }catch(e){errors++}
    }
    showToast(`✓ ${imported} добавени, ${errors} грешки`,'success');
    closeDrawer('csv');
    loadScreen();
}

// ─── AI IMAGE STUDIO ───
function openImageStudio(productId){
    closeDrawer('detail');
    setTimeout(()=>{
        openDrawer('studio');
        document.getElementById('studioBody').innerHTML=renderStudioMain(productId);
    },300);
}

function renderStudioMain(pid){
    return`
    <div class="credits-bar"><div class="cr-item">🤍 Бял фон: <b>${CFG.aiBg}</b></div><div class="cr-sep"></div><div class="cr-item">✨ AI Магия: <b>${CFG.aiTryon}</b></div></div>
    <div style="font-size:13px;font-weight:700;color:var(--indigo-300);margin-bottom:12px;text-align:center">Как искаш да обработя снимката?</div>
    <div class="studio-option" style="background:rgba(34,197,94,0.04);border:1px solid rgba(34,197,94,0.2)" onclick="doStudioAction(${pid},'bg_removal')">
        <div class="studio-icon" style="background:linear-gradient(135deg,rgba(34,197,94,0.15),rgba(34,197,94,0.05))">🤍</div>
        <div style="flex:1"><div style="font-size:15px;font-weight:700">Бял фон</div><div style="font-size:11px;color:var(--text-secondary);margin-top:2px;line-height:1.4">Премахва фона, оставя артикула на чисто бяло.</div></div>
        <div class="studio-price" style="background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);color:var(--success)">€0.05</div>
    </div>
    <div class="studio-option" style="background:rgba(139,92,246,0.04);border:1px solid rgba(139,92,246,0.2)" onclick="showMagicModels(${pid})">
        <div class="studio-icon" style="background:linear-gradient(135deg,rgba(139,92,246,0.2),rgba(99,102,241,0.1));box-shadow:0 0 16px rgba(139,92,246,0.15)">✨</div>
        <div style="flex:1"><div style="font-size:15px;font-weight:700">AI Магия</div><div style="font-size:11px;color:var(--text-secondary);margin-top:2px;line-height:1.4">Облечи артикула на модел — жена, мъж, момиче, момче.</div></div>
        <div style="display:flex;align-items:center;gap:6px"><div class="studio-price" style="background:rgba(139,92,246,0.1);border:1px solid rgba(139,92,246,0.25);color:var(--purple)">€0.50</div><span style="color:var(--text-secondary)">›</span></div>
    </div>
    <div class="studio-option" style="border:1px dashed rgba(255,255,255,0.1);justify-content:center;gap:6px" onclick="closeDrawer('studio')">
        <span style="font-size:13px;font-weight:600;color:var(--text-secondary)">→ Запази оригинала, пропусни</span>
    </div>`;
}

const MAGIC_MODELS=[
    {key:'tryon_woman',icon:'👗',label:'На жена',age:'Възрастен модел',desc:'Елегантна жена модел, бял фон, неутрална поза, близък план.'},
    {key:'tryon_man',icon:'👔',label:'На мъж',age:'Възрастен модел',desc:'Стилен мъж модел, бял фон, неутрална поза, близък план.'},
    {key:'tryon_child_f',icon:'👧',label:'На момиче',age:'6-10 години',desc:'Момиче модел 6-10г, бял фон, неутрална поза.'},
    {key:'tryon_child_m',icon:'👦',label:'На момче',age:'6-10 години',desc:'Момче модел 6-10г, бял фон, неутрална поза.'},
    {key:'tryon_teen_f',icon:'👩',label:'Тийнейджърка',age:'14-17 години',desc:'Тийнейджърка модел, бял фон, неутрална поза.'},
    {key:'tryon_teen_m',icon:'🧒',label:'Тийнейджър',age:'14-17 години',desc:'Тийнейджър модел, бял фон, неутрална поза.'},
];

function showMagicModels(pid){
    let h=`<div class="credits-bar"><div class="cr-item">🤍 Бял фон: <b>${CFG.aiBg}</b></div><div class="cr-sep"></div><div class="cr-item">✨ AI Магия: <b>${CFG.aiTryon}</b></div></div>`;
    h+=`<div style="font-size:13px;font-weight:700;color:var(--indigo-300);margin-bottom:10px;text-align:center">Избери модел</div>`;
    MAGIC_MODELS.forEach((m,i)=>{
        const defPrompt=m.label.replace('На ','')+' модел, бял фон, неутрална поза, близък план';
        h+=`<div id="mm${i}" style="margin-bottom:6px">
            <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:14px;cursor:pointer;background:rgba(15,15,40,0.4);border:1px solid var(--border-subtle);transition:all 0.2s" onclick="toggleMagicModel(${i},${pid},'${m.key}')">
                <span style="font-size:28px">${m.icon}</span>
                <div style="flex:1"><div style="font-size:14px;font-weight:700">${m.label}</div><div style="font-size:10px;color:var(--text-secondary)">${m.age}</div></div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="2.5" stroke-linecap="round" id="mmArrow${i}" style="transition:transform 0.25s"><polyline points="9 18 15 12 9 6"/></svg>
            </div>
            <div id="mmDetail${i}" style="display:none;padding:14px;border-radius:0 0 14px 14px;background:rgba(139,92,246,0.04);border:1px solid rgba(139,92,246,0.35);border-top:none">
                <div style="padding:8px 10px;border-radius:8px;background:rgba(99,102,241,0.04);border:1px solid var(--border-subtle);margin-bottom:10px"><div style="font-size:9px;font-weight:800;color:var(--indigo-300);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Какво ще получиш</div><div style="font-size:12px;color:rgba(241,245,249,0.7);line-height:1.5">${m.desc}</div></div>
                <div style="font-size:9px;font-weight:800;color:var(--text-secondary);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Промпт към AI <span style="color:rgba(165,180,252,0.5);font-weight:400;text-transform:none">(по желание)</span></div>
                <div style="font-size:11px;color:rgba(165,180,252,0.4);padding:6px 10px;border-radius:6px;background:rgba(0,0,0,0.15);margin-bottom:6px;font-style:italic">По подразбиране: ${defPrompt}</div>
                <textarea class="fc" id="mmPrompt${i}" rows="2" placeholder="Допълни... напр. 'стояща поза, с колан'" style="font-size:12px;margin-bottom:10px"></textarea>
                <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;background:rgba(139,92,246,0.06);border:1px solid rgba(139,92,246,0.15)">
                    <div style="display:flex;align-items:center;gap:6px;flex:1"><span style="font-size:18px;font-weight:900;color:var(--purple)">€0.50</span><div style="width:1px;height:14px;background:rgba(139,92,246,0.2)"></div><span style="font-size:10px;color:var(--text-secondary)">Оставащи: <b style="color:var(--text-primary);font-size:13px">${CFG.aiTryon}</b></span></div>
                    <button class="abtn primary" style="width:auto;padding:8px 18px;font-size:13px;background:linear-gradient(135deg,#7c3aed,#6366f1);box-shadow:0 4px 20px rgba(139,92,246,0.4)" onclick="doStudioAction(${pid},'${m.key}',document.getElementById('mmPrompt${i}').value)">✨ Генерирай</button>
                </div>
            </div>
        </div>`;
    });
    h+=`<button class="abtn" onclick="document.getElementById('studioBody').innerHTML=renderStudioMain(${pid})" style="margin-top:10px">← Назад</button>`;
    document.getElementById('studioBody').innerHTML=h;
}

function toggleMagicModel(idx,pid,key){
    const detail=document.getElementById('mmDetail'+idx);
    const arrow=document.getElementById('mmArrow'+idx);
    const isOpen=detail.style.display!=='none';
    // Close all
    MAGIC_MODELS.forEach((_,i)=>{
        document.getElementById('mmDetail'+i).style.display='none';
        document.getElementById('mmArrow'+i).style.transform='none';
    });
    if(!isOpen){
        detail.style.display='block';
        arrow.style.transform='rotate(90deg)';
    }
}

async function doStudioAction(pid,type,prompt){
    showToast('AI обработва снимката... 5-15 секунди','');
    const d=await api('products.php?ajax=ai_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({product_id:pid,type,model:type,prompt:prompt||''})});
    if(d?.error){showToast(d.error,'error')}
    else{showToast('Обработката е стартирана','success')}
}

// ─── LABELS SCREEN ───
async function openLabels(productId){
    openDrawer('labels');
    document.getElementById('labelsBody').innerHTML='<div style="text-align:center;padding:20px">Зареждам...</div>';
    const d=await api(`products.php?ajax=product_detail&id=${productId}`);
    if(!d||d.error){closeDrawer('labels');return}
    const p=d.product;
    const vars=d.variations?.length?d.variations:[{id:p.id,name:p.name,code:p.code,barcode:p.barcode,size:null,color:null,min_quantity:p.min_quantity,total_stock:0}];

    let h=`<div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.25);border-radius:12px;padding:12px 14px;margin-bottom:12px;display:flex;align-items:center;gap:8px"><span style="font-size:18px">✓</span><div><div style="font-size:13px;font-weight:700;color:var(--success)">Артикулът е записан!</div><div style="font-size:11px;color:var(--text-secondary)">AI генерира описание... готово ✓</div></div></div>`;
    h+=`<div style="font-size:12px;color:var(--text-secondary);margin-bottom:10px">${vars.length} вариации — попълни мин. количество и бр. етикети</div>`;

    vars.forEach((v,i)=>{
        const label=[v.size,v.color].filter(Boolean).join(' / ');
        h+=`<div class="label-var"><div class="lv-name">${esc(v.name)}${label?' — '+esc(label):''}</div><div class="lv-code">Код: ${esc(v.code||'—')}</div><div class="lv-fields"><div class="lv-field"><label>Мин. кол.</label><input type="number" value="${v.min_quantity||0}" data-vid="${v.id}" class="lbl-minqty"></div><div class="lv-field"><label>Бр. етикети</label><input type="number" value="0" data-vid="${v.id}" class="lbl-count"></div></div></div>`;
    });

    h+=`<div style="font-size:11px;font-weight:700;color:var(--text-secondary);margin:10px 0 6px;text-transform:uppercase">Формат етикет</div>`;
    h+=`<div class="format-chips"><button class="fmt-chip sel" data-fmt="barcode" onclick="setLblFormat(this)">Баркод</button><button class="fmt-chip" data-fmt="code" onclick="setLblFormat(this)">Арт. номер</button><button class="fmt-chip" data-fmt="both" onclick="setLblFormat(this)">Баркод + Арт.номер</button></div>`;

    h+=`<div class="abtn-grid" style="margin-top:14px">
        <button class="abtn" onclick="saveLabelMinQty(${productId})">💾 Запази мин.кол.</button>
        <button class="abtn" onclick="printLabels(${productId})">🖨 Печатай</button>
        <button class="abtn" onclick="exportLabels(${productId},'csv')">📄 CSV</button>
        <button class="abtn" onclick="exportLabels(${productId},'pdf')">📑 PDF</button>
    </div>`;
    h+=`<button class="abtn save" style="margin-top:10px" onclick="closeDrawer('labels')">✓ Готово</button>`;

    document.getElementById('labelsBody').innerHTML=h;
}

function setLblFormat(btn){
    document.querySelectorAll('.fmt-chip').forEach(c=>c.classList.remove('sel'));
    btn.classList.add('sel');
}

async function saveLabelMinQty(pid){
    const variations=[];
    document.querySelectorAll('.lbl-minqty').forEach(inp=>{
        variations.push({id:parseInt(inp.dataset.vid),min_quantity:parseInt(inp.value)||0});
    });
    await api('products.php?ajax=save_labels',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({variations})});
    showToast('Мин. количества записани ✓','success');
}

function printLabels(pid){
    showToast('Печатане скоро...','');
    // TODO: Generate PDF and print
}

function exportLabels(pid,format){
    window.open(`products.php?ajax=export_labels&product_id=${pid}&format=${format}`,'_blank');
}

// ─── HISTORY (back button stays in module) ───
function goScreenWithHistory(scr, params={}){
    history.pushState({scr,params}, '', '#'+scr);
    goScreen(scr, params);
}
window.addEventListener('popstate', (e)=>{
    // 1. Close camera
    if(document.getElementById('cameraOv').classList.contains('open')){closeCamera();return}
    // 2. Close voice overlay
    if(document.getElementById('recOv').classList.contains('open')){if(S.aiWizMode)closeAIWizardOverlay();else closeVoice();return}
    // 3. Close wizard modal
    if(document.getElementById('wizModal').classList.contains('open')){closeWizard();return}
    // 4. Close any drawer
    const openDr=document.querySelector('.drawer.open');
    if(openDr){const n=openDr.id.replace('Dr','');closeDrawer(n);return}
    // 5. Navigate between screens
    if(e.state && e.state.scr){goScreen(e.state.scr, e.state.params||{});return}
    // 6. If not on home, go home
    if(S.screen !== 'home'){goScreen('home');history.replaceState({scr:'home'},'','#home');return}
    // 7. Actually leave page (real back)
    window.removeEventListener('popstate',arguments.callee);
    history.back();
});

// ─── INIT ───
document.addEventListener('DOMContentLoaded',()=>{
    history.replaceState({scr:'home'}, '', '#home');
    goScreen('home');
});

// Override rec-ov backdrop click for wizard mode
document.getElementById('recOv').addEventListener('click',function(e){
    if(e.target===this){
        if(S.aiWizMode)closeAIWizardOverlay();
        else closeVoice();
    }
});
</script>
</body>
</html>
