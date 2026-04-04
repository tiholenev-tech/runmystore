<?php
/**
 * products.php — RunMyStore.ai
 * 4-екранен модул: Начало | Доставчици | Категории | Артикули
 * Cruip Open Pro Dark тема, AI-first, voice-first
 * Сесия 18 — CLEAN REWRITE — 04.04.2026
 */
session_start();
require_once 'config/database.php';
require_once 'config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id    = $_SESSION['user_id'];
$tenant_id  = $_SESSION['tenant_id'];
$store_id   = $_SESSION['store_id'] ?? null;
$user_role  = $_SESSION['role'] ?? 'seller';
$user_name  = $_SESSION['name'] ?? 'Потребител';

$is_owner   = ($user_role === 'owner');
$is_manager = ($user_role === 'manager');
$can_add    = ($is_owner || $is_manager);
$can_see_cost = $is_owner;
$can_see_margin = $is_owner;

$screen = $_GET['screen'] ?? 'home';
$sup_id = isset($_GET['sup']) ? (int)$_GET['sup'] : null;
$cat_id = isset($_GET['cat']) ? (int)$_GET['cat'] : null;
$filter = $_GET['filter'] ?? null;

// ============================================================
// AJAX ENDPOINTS
// ============================================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_GET['ajax'] === 'search') {
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
        if (!$can_see_cost) { foreach ($rows as &$r) { unset($r['cost_price']); } }
        echo json_encode($rows); exit;
    }

    if ($_GET['ajax'] === 'barcode') {
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
        if ($row && !$can_see_cost) { unset($row['cost_price']); }
        echo json_encode($row ?: ['error' => 'not_found']); exit;
    }

    if ($_GET['ajax'] === 'product_detail') {
        $pid = (int)($_GET['id'] ?? 0);
        $product = DB::run("
            SELECT p.*, s.name AS supplier_name, c.name AS category_name
            FROM products p
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id = ? AND p.tenant_id = ?
        ", [$pid, $tenant_id])->fetch(PDO::FETCH_ASSOC);
        if (!$product) { echo json_encode(['error' => 'not_found']); exit; }
        if (!$can_see_cost) { unset($product['cost_price']); }

        $stocks = DB::run("
            SELECT st.id AS store_id, st.name AS store_name, COALESCE(i.quantity, 0) AS qty
            FROM stores st
            LEFT JOIN inventory i ON i.store_id = st.id AND i.product_id = ?
            WHERE st.company_id = (SELECT company_id FROM stores WHERE id = ?)
            ORDER BY st.name
        ", [$pid, $store_id])->fetchAll(PDO::FETCH_ASSOC);

        $variations = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.barcode,
                   COALESCE(SUM(i.quantity), 0) AS total_stock
            FROM products p
            LEFT JOIN inventory i ON i.product_id = p.id
            WHERE p.parent_id = ? AND p.tenant_id = ?
            GROUP BY p.id ORDER BY p.name
        ", [$pid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

        $price_history = DB::run("
            SELECT old_price, new_price, changed_at, changed_by
            FROM price_history WHERE product_id = ? ORDER BY changed_at DESC LIMIT 10
        ", [$pid])->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['product' => $product, 'stocks' => $stocks, 'variations' => $variations, 'price_history' => $price_history]);
        exit;
    }

    if ($_GET['ajax'] === 'home_stats') {
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
            WHERE p.tenant_id = ? AND i.quantity > 0 HAVING days_stale > 45 ORDER BY days_stale DESC LIMIT 10
        ", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

        $low_stock = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image, p.min_quantity, COALESCE(i.quantity, 0) AS qty
            FROM products p JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND p.min_quantity > 0 AND i.quantity <= p.min_quantity AND i.quantity > 0
            ORDER BY (i.quantity / p.min_quantity) ASC LIMIT 10
        ", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

        $out_of_stock = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image
            FROM products p JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND i.quantity = 0 ORDER BY p.name LIMIT 20
        ", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

        $top_sellers = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image, SUM(si.quantity) AS sold_qty, SUM(si.quantity * si.unit_price) AS revenue
            FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
            WHERE p.tenant_id = ? AND s.store_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY p.id ORDER BY sold_qty DESC LIMIT 5
        ", [$tenant_id, $sid])->fetchAll(PDO::FETCH_ASSOC);

        $slow_movers = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image, COALESCE(i.quantity, 0) AS qty,
                   DATEDIFF(NOW(), COALESCE((SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_id = p.id), p.created_at)) AS days_stale
            FROM products p JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND i.quantity > 0 HAVING days_stale BETWEEN 25 AND 45 ORDER BY days_stale DESC LIMIT 10
        ", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

        $counts = DB::run("
            SELECT COUNT(DISTINCT p.id) AS total_products, COALESCE(SUM(i.quantity), 0) AS total_units,
                   COUNT(DISTINCT p.supplier_id) AS total_suppliers, COUNT(DISTINCT p.category_id) AS total_categories
            FROM products p LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND p.is_active = 1
        ", [$sid, $tenant_id])->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'capital' => $can_see_margin ? round($capital['retail_value'], 2) : null,
            'cost_value' => $can_see_cost ? round($capital['cost_value'], 2) : null,
            'avg_margin' => $can_see_margin ? $avg_margin : null,
            'zombies' => $zombies, 'low_stock' => $low_stock, 'out_of_stock' => $out_of_stock,
            'top_sellers' => $top_sellers, 'slow_movers' => $slow_movers, 'counts' => $counts
        ]);
        exit;
    }

    if ($_GET['ajax'] === 'suppliers') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $suppliers = DB::run("
            SELECT s.id, s.name, s.phone, s.email, COUNT(DISTINCT p.id) AS product_count,
                   COALESCE(SUM(i.quantity), 0) AS total_stock,
                   SUM(CASE WHEN i.quantity = 0 THEN 1 ELSE 0 END) AS out_count,
                   SUM(CASE WHEN i.quantity > 0 AND i.quantity <= p.min_quantity THEN 1 ELSE 0 END) AS low_count
            FROM suppliers s JOIN products p ON p.supplier_id = s.id AND p.tenant_id = ?
            LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE s.tenant_id = ? GROUP BY s.id ORDER BY s.name
        ", [$tenant_id, $sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($suppliers); exit;
    }

    if ($_GET['ajax'] === 'categories') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $sup = isset($_GET['sup']) ? (int)$_GET['sup'] : null;
        if ($sup) {
            $categories = DB::run("
                SELECT c.id, c.name, c.parent_id, COUNT(DISTINCT p.id) AS product_count, COALESCE(SUM(i.quantity), 0) AS total_stock
                FROM categories c JOIN products p ON p.category_id = c.id AND p.supplier_id = ? AND p.tenant_id = ?
                LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
                WHERE c.tenant_id = ? GROUP BY c.id ORDER BY c.name
            ", [$sup, $tenant_id, $sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $categories = DB::run("
                SELECT c.id, c.name, c.parent_id, COUNT(DISTINCT p.id) AS product_count,
                       COUNT(DISTINCT p.supplier_id) AS supplier_count, COALESCE(SUM(i.quantity), 0) AS total_stock
                FROM categories c JOIN products p ON p.category_id = c.id AND p.tenant_id = ?
                LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
                WHERE c.tenant_id = ? GROUP BY c.id ORDER BY c.name
            ", [$tenant_id, $sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($categories); exit;
    }

    if ($_GET['ajax'] === 'products') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $sup = isset($_GET['sup']) ? (int)$_GET['sup'] : null;
        $cat = isset($_GET['cat']) ? (int)$_GET['cat'] : null;
        $flt = $_GET['filter'] ?? 'all';
        $sort = $_GET['sort'] ?? 'name';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 30; $offset = ($page - 1) * $per_page;
        $where = ["p.tenant_id = ?", "p.is_active = 1"]; $params = [$tenant_id];
        if ($sup) { $where[] = "p.supplier_id = ?"; $params[] = $sup; }
        if ($cat) { $where[] = "p.category_id = ?"; $params[] = $cat; }
        if ($flt === 'low') { $where[] = "i.quantity > 0 AND i.quantity <= p.min_quantity AND p.min_quantity > 0"; }
        elseif ($flt === 'out') { $where[] = "(i.quantity = 0 OR i.quantity IS NULL)"; }
        $where_sql = implode(' AND ', $where);
        $order = match($sort) {
            'price_asc' => 'p.retail_price ASC', 'price_desc' => 'p.retail_price DESC',
            'stock_asc' => 'store_stock ASC', 'stock_desc' => 'store_stock DESC',
            'margin_desc' => $can_see_margin ? '((p.retail_price - p.cost_price) / NULLIF(p.retail_price, 0)) DESC' : 'p.name ASC',
            'newest' => 'p.created_at DESC', default => 'p.name ASC'
        };
        $products = DB::run("
            SELECT p.id, p.name, p.code, p.barcode, p.retail_price, p.cost_price, p.image, p.supplier_id, p.category_id,
                   p.parent_id, p.discount_pct, p.discount_ends_at, p.min_quantity, p.unit,
                   s.name AS supplier_name, c.name AS category_name,
                   COALESCE(i.quantity, 0) AS store_stock,
                   (SELECT COALESCE(SUM(i2.quantity), 0) FROM inventory i2 WHERE i2.product_id = p.id) AS total_stock
            FROM products p LEFT JOIN suppliers s ON s.id = p.supplier_id LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE {$where_sql} ORDER BY {$order} LIMIT ? OFFSET ?
        ", array_merge([$sid], $params, [$per_page, $offset]))->fetchAll(PDO::FETCH_ASSOC);
        $total = DB::run("SELECT COUNT(DISTINCT p.id) AS cnt FROM products p LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ? WHERE {$where_sql}", array_merge([$sid], $params))->fetchColumn();
        if (!$can_see_cost) { foreach ($products as &$pr) { unset($pr['cost_price']); } }
        echo json_encode(['products' => $products, 'total' => (int)$total, 'page' => $page, 'pages' => ceil($total / $per_page)]);
        exit;
    }

    if ($_GET['ajax'] === 'ai_analyze') {
        $pid = (int)($_GET['id'] ?? 0);
        $product = DB::run("SELECT * FROM products WHERE id = ? AND tenant_id = ?", [$pid, $tenant_id])->fetch(PDO::FETCH_ASSOC);
        if (!$product) { echo json_encode(['error' => 'not_found']); exit; }
        $sales_30d = DB::run("SELECT COALESCE(SUM(si.quantity), 0) AS qty, COALESCE(SUM(si.quantity * si.unit_price), 0) AS revenue FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$pid])->fetch(PDO::FETCH_ASSOC);
        $stock = DB::run("SELECT COALESCE(SUM(quantity), 0) AS total FROM inventory WHERE product_id = ?", [$pid])->fetchColumn();
        $days_supply = ($sales_30d['qty'] > 0) ? round(($stock / ($sales_30d['qty'] / 30)), 0) : 999;
        $analysis = [];
        if ($days_supply > 90 && $stock > 0) $analysis[] = ['type'=>'zombie','icon'=>'💀','text'=>"Стока за {$days_supply} дни. Намали с 30% или пакет.",'severity'=>'high'];
        elseif ($days_supply > 45 && $stock > 0) $analysis[] = ['type'=>'slow','icon'=>'🐌','text'=>"Бавно движеща се — {$days_supply} дни запас. Промоция -20%?",'severity'=>'medium'];
        if ($stock <= $product['min_quantity'] && $stock > 0 && $product['min_quantity'] > 0) $analysis[] = ['type'=>'low','icon'=>'⚠️','text'=>"Остават {$stock} бр. (мин. {$product['min_quantity']}). Поръчай!",'severity'=>'high'];
        elseif ($stock == 0) $analysis[] = ['type'=>'out','icon'=>'🔴','text'=>"ИЗЧЕРПАН! Губиш продажби.",'severity'=>'critical'];
        if ($can_see_margin && $product['cost_price'] > 0) { $margin = round((($product['retail_price'] - $product['cost_price']) / $product['retail_price']) * 100, 1); if ($margin < 20) $analysis[] = ['type'=>'margin','icon'=>'💰','text'=>"Марж само {$margin}%. Увеличи цената?",'severity'=>'medium']; }
        if ($sales_30d['qty'] > 0) $analysis[] = ['type'=>'sales','icon'=>'📊','text'=>"Продажби 30 дни: {$sales_30d['qty']} бр. / " . number_format($sales_30d['revenue'], 2, ',', '.') . " €",'severity'=>'info'];
        echo json_encode(['analysis' => $analysis, 'days_supply' => $days_supply, 'sales_30d' => $sales_30d]);
        exit;
    }

    if ($_GET['ajax'] === 'ai_credits') {
        $credits = DB::run("SELECT ai_credits_bg, ai_credits_tryon FROM tenants WHERE id = ?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
        echo json_encode($credits); exit;
    }

    if ($_GET['ajax'] === 'search_sizes') {
        $q = trim($_GET['q'] ?? '');
        $rows = DB::run("SELECT DISTINCT size FROM products WHERE tenant_id=? AND size IS NOT NULL AND size!='' AND size LIKE ? ORDER BY size LIMIT 50", [$tenant_id, "%$q%"])->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($rows); exit;
    }

    if ($_GET['ajax'] === 'search_colors') {
        $q = trim($_GET['q'] ?? '');
        $rows = DB::run("SELECT DISTINCT color FROM products WHERE tenant_id=? AND color IS NOT NULL AND color!='' AND color LIKE ? ORDER BY color LIMIT 50", [$tenant_id, "%$q%"])->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($rows); exit;
    }

    if ($_GET['ajax'] === 'add_supplier') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) { echo json_encode(['error'=>'Въведи име']); exit; }
        $exists = DB::run("SELECT id FROM suppliers WHERE tenant_id=? AND name=?", [$tenant_id, $name])->fetch();
        if ($exists) { echo json_encode(['id'=>$exists['id'], 'name'=>$name]); exit; }
        DB::run("INSERT INTO suppliers (tenant_id, name, is_active) VALUES (?,?,1)", [$tenant_id, $name]);
        echo json_encode(['id'=>DB::get()->lastInsertId(), 'name'=>$name]); exit;
    }

    if ($_GET['ajax'] === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) { echo json_encode(['error'=>'Въведи име']); exit; }
        $exists = DB::run("SELECT id FROM categories WHERE tenant_id=? AND name=?", [$tenant_id, $name])->fetch();
        if ($exists) { echo json_encode(['id'=>$exists['id'], 'name'=>$name]); exit; }
        DB::run("INSERT INTO categories (tenant_id, name) VALUES (?,?)", [$tenant_id, $name]);
        echo json_encode(['id'=>DB::get()->lastInsertId(), 'name'=>$name]); exit;
    }

    if ($_GET['ajax'] === 'add_unit') {
        $unit = trim($_POST['unit'] ?? '');
        if (!$unit) { echo json_encode(['error'=>'Въведи единица']); exit; }
        $tenant = DB::run("SELECT units_config FROM tenants WHERE id=?", [$tenant_id])->fetch();
        $units = json_decode($tenant['units_config'] ?? '[]', true) ?: [];
        if (!in_array($unit, $units)) { $units[] = $unit; DB::run("UPDATE tenants SET units_config=? WHERE id=?", [json_encode($units, JSON_UNESCAPED_UNICODE), $tenant_id]); }
        echo json_encode(['units'=>$units, 'added'=>$unit]); exit;
    }

    if ($_GET['ajax'] === 'ai_scan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $image_data = $input['image'] ?? '';
        if (!$image_data) { echo json_encode(['error' => 'no_image']); exit; }
        $memories = DB::run("SELECT memory_text FROM tenant_ai_memory WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 20", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $memory_ctx = implode("\n", $memories);
        $cats = DB::run("SELECT name FROM categories WHERE tenant_id = ?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $sups = DB::run("SELECT name FROM suppliers WHERE tenant_id = ?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $prompt = "Анализирай тази снимка на продукт. Върни САМО JSON без markdown:\n";
        $prompt .= "{\"name\":\"\",\"retail_price\":0,\"category\":\"\",\"supplier\":\"\",\"sizes\":[],\"colors\":[],\"code\":\"\",\"description\":\"\",\"unit\":\"бр\"}\n";
        $prompt .= "Категории: " . implode(', ', $cats) . "\nДоставчици: " . implode(', ', $sups) . "\n";
        if ($memory_ctx) $prompt .= "AI памет: {$memory_ctx}\n";
        $prompt .= "description = SEO оптимизирано. code = кратък 6-8 символа. Само JSON.";
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
        $payload = ['contents'=>[['parts'=>[['inlineData'=>['mimeType'=>'image/jpeg','data'=>$image_data]],['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.3,'maxOutputTokens'=>500]];
        $ch = curl_init($api_url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
        $resp = curl_exec($ch); curl_close($ch);
        $data = json_decode($resp, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = preg_replace('/```json\s*|\s*```/', '', $text);
        echo json_encode(json_decode($text, true) ?: ['error'=>'parse_failed','raw'=>$text]);
        exit;
    }

    if ($_GET['ajax'] === 'ai_description') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? '';
        if (!$name) { echo json_encode(['error'=>'no_name']); exit; }
        $prompt = "Напиши кратко SEO описание (2-3 изречения) за: \"{$name}\".";
        if (!empty($input['category'])) $prompt .= " Категория: {$input['category']}.";
        if (!empty($input['sizes'])) $prompt .= " Размери: {$input['sizes']}.";
        if (!empty($input['colors'])) $prompt .= " Цветове: {$input['colors']}.";
        if (!empty($input['supplier'])) $prompt .= " Марка: {$input['supplier']}.";
        $prompt .= " Подходящо за Google и e-commerce. Само описанието.";
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
        $payload = ['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.5,'maxOutputTokens'=>200]];
        $ch = curl_init($api_url); curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
        $resp = curl_exec($ch); curl_close($ch);
        $data = json_decode($resp, true);
        $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
        echo json_encode(['description'=>$text]); exit;
    }

    if ($_GET['ajax'] === 'ai_voice_fill' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $voice_text = $input['text'] ?? '';
        if (!$voice_text) { echo json_encode(['error' => 'no_text']); exit; }
        $cats = DB::run("SELECT name FROM categories WHERE tenant_id = ?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $sups = DB::run("SELECT name FROM suppliers WHERE tenant_id = ?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $prompt = "Потребителят описа продукт: \"{$voice_text}\"\nВърни JSON: {\"name\":\"\",\"category\":\"\",\"supplier\":\"\",\"sizes\":[],\"colors\":[],\"retail_price\":0,\"description\":\"\"}\n";
        $prompt .= "Категории: " . implode(', ', $cats) . "\nДоставчици: " . implode(', ', $sups) . "\n";
        $prompt .= "Разбирай: дреги=дрехи, офки=обувки, якита=якета. САМО JSON.";
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
        $payload = ['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.3,'maxOutputTokens'=>500]];
        $ch = curl_init($api_url); curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
        $resp = curl_exec($ch); curl_close($ch);
        $data = json_decode($resp, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = preg_replace('/```json\s*|\s*```/', '', $text);
        echo json_encode(json_decode($text, true) ?: ['error'=>'parse_failed']);
        exit;
    }

    if ($_GET['ajax'] === 'ai_code') {
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

   if ($_GET['ajax'] === 'ai_assist' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $question = $input['question'] ?? '';
        if (!$question) { echo json_encode(['error'=>'no_question']); exit; }

        // Събираме богат контекст
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
Магазин: {$stats['cnt']} артикула, {$stats['total_qty']} бройки, {$low} с ниска наличност, {$out} изчерпани, {$zombie} zombie (45+ дни без продажба).
Потребител: {$user_name}, роля: {$role_bg}.
Категории: " . implode(', ', $cats) . "
Доставчици: " . implode(', ', $sups) . "
Топ 5 (30 дни): {$top5str}
" . ($memStr ? "Памет: {$memStr}" : "") . "

ПРАВИЛА:
- Говори кратко (2-3 изречения), конкретно, с числа
- Без технически термини
- Разбирай жаргон: дреги=дрехи, офки=обувки, якита=якета
- НИКОГА не питай 'Имаш ли предвид X?' — разпознавай и действай

ВЪРНИ ОТГОВОР САМО КАТО JSON (без markdown, без ```):
{
  \"message\": \"текст който потребителят вижда\",
  \"action\": \"тип действие или null\",
  \"data\": {},
  \"buttons\": []
}

ВЪЗМОЖНИ ДЕЙСТВИЯ (action):
- \"search\" + data:{\"query\":\"текст\"} — търси артикул
- \"add_product\" + data:{\"name\":\"\",\"supplier\":\"\",\"sizes\":[],\"colors\":[],\"retail_price\":0} — добави нов артикул с попълнени полета
- \"show_zombie\" — покажи zombie секцията
- \"show_low\" — покажи ниска наличност
- \"show_top\" — покажи топ продавани
- \"navigate\" + data:{\"url\":\"страница.php\"} — навигирай
- \"product_detail\" + data:{\"query\":\"име за търсене\"} — отвори конкретен артикул
- null — само информация, без действие

БУТОНИ (buttons) — масив от:
{\"label\": \"текст на бутона\", \"action\": \"тип\", \"data\": {}}

ПРИМЕРИ:
Въпрос: 'добави бикини nike размер M цена 25'
Отговор: {\"message\":\"Добавям бикини Nike, размер M, 25 €. Потвърди данните.\",\"action\":\"add_product\",\"data\":{\"name\":\"Бикини\",\"supplier\":\"Nike\",\"sizes\":[\"M\"],\"colors\":[],\"retail_price\":25},\"buttons\":[]}

Въпрос: 'какво свършва'
Отговор: {\"message\":\"{$low} артикула свършват.\",\"action\":\"show_low\",\"data\":{},\"buttons\":[{\"label\":\"Виж списъка\",\"action\":\"show_low\",\"data\":{}}]}

Въпрос: 'покажи nike'
Отговор: {\"message\":\"Търся Nike...\",\"action\":\"search\",\"data\":{\"query\":\"Nike\"},\"buttons\":[]}

Въпрос: 'колко чифта маратонки имам'
Отговор: (търси в контекста и отговаря с число + бутон за търсене)";

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
        $payload = [
            'contents' => [['parts' => [['text' => $question]]]],
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 500]
        ];

        $ch = curl_init($api_url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
        $resp = curl_exec($ch); curl_close($ch);
        $data = json_decode($resp, true);
        $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $text = preg_replace('/```json\s*|\s*```/', '', $text);

        $parsed = json_decode($text, true);
        if ($parsed && isset($parsed['message'])) {
            echo json_encode($parsed);
        } else {
            // Fallback ако Gemini не върне JSON
            echo json_encode(['message' => $text ?: 'Не разбрах. Опитай пак.', 'action' => null, 'data' => new \stdClass(), 'buttons' => []]);
        }
        exit;
    }

    echo json_encode(['error' => 'unknown_action']); exit;
}

// ============================================================
// Data loading
// ============================================================
$stores = DB::run("SELECT s.id, s.name FROM stores s WHERE s.company_id = (SELECT company_id FROM stores WHERE id = ?) ORDER BY s.name", [$store_id])->fetchAll(PDO::FETCH_ASSOC);
$current_store = null;
foreach ($stores as $st) { if ($st['id'] == $store_id) { $current_store = $st; break; } }
$all_suppliers = DB::run("SELECT id, name FROM suppliers WHERE tenant_id = ? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$all_categories = DB::run("SELECT id, name, parent_id FROM categories WHERE tenant_id = ? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$tenant_cfg = DB::run("SELECT units_config, ai_credits_bg, ai_credits_tryon FROM tenants WHERE id=?", [$tenant_id])->fetch();
$onboarding_units = json_decode($tenant_cfg['units_config'] ?? '[]', true) ?: ['бр','чифт','к-кт'];
$ai_bg = (int)($tenant_cfg['ai_credits_bg'] ?? 0);
$ai_tryon = (int)($tenant_cfg['ai_credits_tryon'] ?? 0);

$COLOR_PALETTE = [
    ['name'=>'Черен','hex'=>'#1a1a1a'],['name'=>'Бял','hex'=>'#f5f5f5'],
    ['name'=>'Сив','hex'=>'#6b7280'],['name'=>'Червен','hex'=>'#ef4444'],
    ['name'=>'Син','hex'=>'#3b82f6'],['name'=>'Зелен','hex'=>'#22c55e'],
    ['name'=>'Жълт','hex'=>'#eab308'],['name'=>'Розов','hex'=>'#ec4899'],
    ['name'=>'Оранжев','hex'=>'#f97316'],['name'=>'Лилав','hex'=>'#8b5cf6'],
    ['name'=>'Кафяв','hex'=>'#92400e'],['name'=>'Navy','hex'=>'#1e40af'],
    ['name'=>'Бежов','hex'=>'#d4b896'],['name'=>'Бордо','hex'=>'#7f1d1d'],
    ['name'=>'Тюркоаз','hex'=>'#14b8a6'],['name'=>'Графит','hex'=>'#374151'],
];

$supplier_name = '';
$category_name = '';
if ($sup_id) { $supplier_name = DB::run("SELECT name FROM suppliers WHERE id = ? AND tenant_id = ?", [$sup_id, $tenant_id])->fetchColumn() ?: ''; }
if ($cat_id) { $category_name = DB::run("SELECT name FROM categories WHERE id = ? AND tenant_id = ?", [$cat_id, $tenant_id])->fetchColumn() ?: ''; }
$cross_link = null;
if ($sup_id && $cat_id && $screen === 'products') {
    $cl = DB::run("SELECT COUNT(DISTINCT p.id) AS cnt, COUNT(DISTINCT p.supplier_id) AS sup_cnt FROM products p WHERE p.category_id = ? AND p.tenant_id = ? AND p.is_active = 1", [$cat_id, $tenant_id])->fetch(PDO::FETCH_ASSOC);
    if ($cl['sup_cnt'] > 1) { $cross_link = ['count' => $cl['cnt'], 'suppliers' => $cl['sup_cnt'], 'cat_name' => $category_name]; }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <title>Артикули — RunMyStore.ai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link href="./css/vendors/aos.css" rel="stylesheet">
    <link href="./style.css" rel="stylesheet">
    <style>
        :root {
            --bg-main: #0b0f1a; --bg-card: rgba(17, 24, 44, 0.85); --bg-card-hover: rgba(23, 32, 58, 0.95);
            --border-subtle: rgba(99, 102, 241, 0.15); --border-glow: rgba(99, 102, 241, 0.4);
            --indigo-500: #6366f1; --indigo-400: #818cf8; --indigo-300: #a5b4fc; --indigo-200: #c7d2fe;
            --text-primary: #e5e7eb; --text-secondary: rgba(165, 180, 252, 0.65);
            --green-glow: rgba(34, 197, 94, 0.6); --yellow-glow: rgba(234, 179, 8, 0.6); --red-glow: rgba(239, 68, 68, 0.6);
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { background: var(--bg-main); color: var(--text-primary); font-family: Inter, system-ui, sans-serif; margin: 0; padding: 0; min-height: 100vh; overflow-x: hidden; }
        .bg-decoration { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; overflow: hidden; }
        .bg-decoration::before { content: ''; position: absolute; top: -10%; left: 50%; transform: translateX(-50%); width: 1200px; height: 800px; background: url('./images/page-illustration.svg') no-repeat center; background-size: contain; opacity: 0.15; }
        .bg-decoration::after { content: ''; position: absolute; top: 20%; right: -20%; width: 600px; height: 600px; background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%); border-radius: 50%; }
        .bg-blur-shape { position: fixed; width: 400px; height: 400px; border-radius: 50%; filter: blur(100px); opacity: 0.06; pointer-events: none; z-index: 0; }
        .bg-blur-1 { top: 10%; left: -10%; background: var(--indigo-500); }
        .bg-blur-2 { bottom: 20%; right: -10%; background: #4f46e5; }
        .main-wrap { position: relative; z-index: 1; padding-bottom: 160px; padding-top: 8px; }

        /* Header */
        .top-header { position: sticky; top: 0; z-index: 50; padding: 8px 16px; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); background: rgba(11, 15, 26, 0.8); border-bottom: 1px solid var(--border-subtle); }
        .top-header h1 { font-family: Nacelle, Inter, sans-serif; font-size: 1.25rem; font-weight: 700; margin: 0; background: linear-gradient(to right, var(--text-primary), var(--indigo-200), #f9fafb, var(--indigo-300), var(--text-primary)); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; animation: gradient 6s linear infinite; }
        @keyframes gradient { 0% { background-position: 0% center; } 100% { background-position: 200% center; } }
        .header-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .store-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 999px; background: rgba(99,102,241,0.15); color: var(--indigo-300); border: 1px solid var(--border-subtle); white-space: nowrap; }

        /* Search */
        .search-wrap { margin: 8px 16px 0; position: relative; }
        .search-input { width: 100%; padding: 10px 80px 10px 40px; border-radius: 12px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.9rem; outline: none; transition: all 0.3s; }
        .search-input:focus { border-color: var(--border-glow); box-shadow: 0 0 20px rgba(99,102,241,0.15); }
        .search-input::placeholder { color: var(--text-secondary); }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); pointer-events: none; }
        .search-actions { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); display: flex; gap: 2px; }
        .search-btn { width: 36px; height: 36px; border-radius: 8px; border: none; background: transparent; color: var(--indigo-300); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .search-btn:active { background: rgba(99,102,241,0.2); transform: scale(0.9); }
        .search-btn.listening { background: rgba(239,68,68,0.2); color: #ef4444; animation: pulse-mic 1s infinite; }
        @keyframes pulse-mic { 0%,100% { box-shadow: none; } 50% { box-shadow: 0 0 12px rgba(239,68,68,0.4); } }

        /* Tabs */
        .tabs-row { display: flex; gap: 6px; padding: 10px 16px 0; overflow-x: auto; scrollbar-width: none; }
        .tabs-row::-webkit-scrollbar { display: none; }
        .tab-btn { padding: 6px 14px; border-radius: 999px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.8rem; white-space: nowrap; cursor: pointer; transition: all 0.2s; flex-shrink: 0; }
        .tab-btn.active { background: linear-gradient(135deg, var(--indigo-500), #4f46e5); color: #fff; border-color: transparent; box-shadow: 0 0 12px rgba(99,102,241,0.3); }
        .tab-btn .count { display: inline-block; min-width: 18px; height: 18px; line-height: 18px; text-align: center; border-radius: 9px; background: rgba(255,255,255,0.15); font-size: 0.65rem; margin-left: 4px; padding: 0 4px; }

        /* Quick stats */
        .quick-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 12px 16px 0; }
        .stat-card { position: relative; padding: 14px; border-radius: 12px; background: var(--bg-card); overflow: hidden; border: 1px solid var(--border-subtle); box-shadow: 0 4px 20px rgba(99,102,241,0.08); }
        .stat-card::before { content: ''; position: absolute; inset: 0; border-radius: inherit; border: 1px solid transparent; background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(79,70,229,0.05)) border-box; mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0); mask-composite: exclude; -webkit-mask-composite: xor; pointer-events: none; }
        .stat-card .stat-label { font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-value { font-size: 1.3rem; font-weight: 700; font-family: Nacelle, Inter, sans-serif; margin-top: 4px; }
        .stat-card .stat-icon { position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; border-radius: 8px; background: rgba(99,102,241,0.12); display: flex; align-items: center; justify-content: center; font-size: 1rem; filter: drop-shadow(0 0 8px rgba(99,102,241,0.5)); }

        /* Collapse sections */
        .collapse-section { margin: 10px 16px 0; border-radius: 12px; background: var(--bg-card); border: 1px solid var(--border-subtle); overflow: hidden; box-shadow: 0 4px 16px rgba(99,102,241,0.06); }
        .collapse-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; cursor: pointer; user-select: none; transition: background 0.2s; }
        .collapse-header:active { background: rgba(99,102,241,0.08); }
        .collapse-header .ch-left { display: flex; align-items: center; gap: 8px; }
        .collapse-header .ch-icon { font-size: 1.1rem; filter: drop-shadow(0 0 8px rgba(99,102,241,0.5)); }
        .collapse-header .ch-title { font-size: 0.85rem; font-weight: 600; }
        .collapse-header .ch-count { font-size: 0.7rem; color: var(--text-secondary); background: rgba(99,102,241,0.1); padding: 2px 8px; border-radius: 999px; }
        .collapse-header .ch-arrow { transition: transform 0.3s; color: var(--text-secondary); }
        .collapse-header.open .ch-arrow { transform: rotate(180deg); }
        .collapse-body { max-height: 0; overflow: hidden; transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .collapse-body.open { max-height: 2000px; }
        .collapse-body-inner { padding: 0 14px 14px; }

        /* Product cards */
        .product-card { display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 10px; background: rgba(17, 24, 44, 0.5); border: 1px solid var(--border-subtle); margin-bottom: 8px; cursor: pointer; transition: all 0.25s; position: relative; overflow: hidden; }
        .product-card:active { transform: scale(0.98); background: var(--bg-card-hover); box-shadow: 0 4px 20px rgba(99,102,241,0.12); }
        .product-card .stock-bar { position: absolute; left: 0; top: 0; bottom: 0; width: 3px; border-radius: 3px 0 0 3px; }
        .stock-bar.green { background: #22c55e; box-shadow: 0 0 8px var(--green-glow); }
        .stock-bar.yellow { background: #eab308; box-shadow: 0 0 8px var(--yellow-glow); }
        .stock-bar.red { background: #ef4444; box-shadow: 0 0 8px var(--red-glow); }
        .product-card .pc-thumb { width: 48px; height: 48px; border-radius: 8px; background: rgba(99,102,241,0.08); flex-shrink: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-left: 6px; }
        .product-card .pc-thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
        .product-card .pc-info { flex: 1; min-width: 0; }
        .product-card .pc-name { font-size: 0.85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .product-card .pc-meta { font-size: 0.7rem; color: var(--text-secondary); margin-top: 2px; display: flex; gap: 6px; flex-wrap: wrap; }
        .product-card .pc-right { text-align: right; flex-shrink: 0; }
        .product-card .pc-price { font-size: 0.9rem; font-weight: 700; color: var(--indigo-300); }
        .product-card .pc-stock { font-size: 0.7rem; margin-top: 2px; }
        .pc-stock.ok { color: #22c55e; } .pc-stock.low { color: #eab308; } .pc-stock.out { color: #ef4444; }
        .pc-discount { position: absolute; top: 4px; right: 4px; font-size: 0.6rem; padding: 1px 6px; border-radius: 4px; background: #ef4444; color: #fff; font-weight: 600; }

        /* Supplier / Category cards */
        .swipe-container { padding: 12px 16px; overflow-x: auto; display: flex; gap: 12px; scroll-snap-type: x mandatory; scrollbar-width: none; }
        .swipe-container::-webkit-scrollbar { display: none; }
        .supplier-card { min-width: 260px; max-width: 300px; flex-shrink: 0; scroll-snap-align: start; border-radius: 14px; padding: 16px; background: var(--bg-card); border: 1px solid var(--border-subtle); position: relative; overflow: hidden; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 16px rgba(99,102,241,0.06); }
        .supplier-card:active { transform: scale(0.97); }
        .supplier-card::before { content: ''; position: absolute; inset: 0; border-radius: inherit; border: 1px solid transparent; background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(79,70,229,0.05)) border-box; mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0); mask-composite: exclude; -webkit-mask-composite: xor; pointer-events: none; }
        .supplier-card .sc-name { font-size: 1rem; font-weight: 700; font-family: Nacelle, Inter, sans-serif; }
        .supplier-card .sc-count { font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px; }
        .supplier-card .sc-badges { display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap; }
        .sc-badge { font-size: 0.65rem; padding: 2px 8px; border-radius: 999px; font-weight: 600; }
        .sc-badge.ok { background: rgba(34,197,94,0.15); color: #22c55e; } .sc-badge.low { background: rgba(234,179,8,0.15); color: #eab308; } .sc-badge.out { background: rgba(239,68,68,0.15); color: #ef4444; }
        .supplier-card .sc-arrow { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); opacity: 0.5; }
        .cat-list-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-subtle); cursor: pointer; transition: background 0.2s; }
        .cat-list-item:active { background: rgba(99,102,241,0.08); }
        .cat-list-item:last-child { border-bottom: none; }
        .cat-list-item .cli-left { display: flex; align-items: center; gap: 10px; }
        .cat-list-item .cli-icon { width: 36px; height: 36px; border-radius: 8px; background: rgba(99,102,241,0.12); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; filter: drop-shadow(0 0 6px rgba(99,102,241,0.4)); }
        .cat-list-item .cli-name { font-size: 0.9rem; font-weight: 600; }
        .cat-list-item .cli-count { font-size: 0.7rem; color: var(--text-secondary); }
        .category-card { min-width: 180px; flex-shrink: 0; scroll-snap-align: start; border-radius: 12px; padding: 14px; background: var(--bg-card); border: 1px solid var(--border-subtle); cursor: pointer; transition: all 0.3s; position: relative; overflow: hidden; box-shadow: 0 4px 16px rgba(99,102,241,0.06); }
        .category-card::before { content: ''; position: absolute; inset: 0; border-radius: inherit; border: 1px solid transparent; background: linear-gradient(135deg, rgba(99,102,241,0.12), transparent) border-box; mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0); mask-composite: exclude; -webkit-mask-composite: xor; pointer-events: none; }
        .category-card:active { transform: scale(0.97); }
        .category-card .cc-name { font-size: 0.85rem; font-weight: 600; }
        .category-card .cc-info { font-size: 0.7rem; color: var(--text-secondary); margin-top: 4px; }
        .breadcrumb { display: flex; align-items: center; gap: 4px; padding: 8px 16px 0; font-size: 0.75rem; color: var(--text-secondary); flex-wrap: wrap; }
        .breadcrumb a { color: var(--indigo-400); text-decoration: none; }
        .cross-link { margin: 6px 16px 0; padding: 8px 12px; border-radius: 8px; background: rgba(99,102,241,0.08); border: 1px dashed var(--border-glow); font-size: 0.75rem; color: var(--indigo-300); cursor: pointer; }

        /* ====== QUICK ACTIONS BAR (4 small buttons above screen-nav) ====== */
        .quick-actions { position: fixed; bottom: 120px; left: 0; right: 0; z-index: 41; display: flex; justify-content: center; padding: 0 16px; }
        .qa-inner { display: flex; gap: 8px; width: 100%; max-width: 400px; }
        .qa-btn { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; padding: 10px 4px; border-radius: 12px; border: none; cursor: pointer; transition: all 0.2s; position: relative; overflow: hidden; }
        .qa-btn:active { transform: scale(0.93); }
        .qa-btn .qa-icon { font-size: 1rem; line-height: 1; }
        .qa-btn .qa-label { font-size: 0.55rem; font-weight: 600; letter-spacing: 0.2px; }

        /* AI button — glowing indigo */
        .qa-btn.qa-ai { background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(139,92,246,0.15)); border: 1px solid rgba(99,102,241,0.3); box-shadow: 0 0 16px rgba(99,102,241,0.15), inset 0 1px 0 rgba(255,255,255,0.05); }
        .qa-btn.qa-ai .qa-icon { color: var(--indigo-300); filter: drop-shadow(0 0 6px rgba(99,102,241,0.6)); }
        .qa-btn.qa-ai .qa-label { color: var(--indigo-300); }
        .qa-btn.qa-ai::before { content: ''; position: absolute; inset: -1px; border-radius: 12px; background: linear-gradient(135deg, rgba(99,102,241,0.3), rgba(139,92,246,0.1)); z-index: -1; animation: qa-glow 3s ease-in-out infinite; }
        @keyframes qa-glow { 0%,100% { opacity: 0.5; } 50% { opacity: 1; } }

        /* Scan button — subtle cyan */
        .qa-btn.qa-scan { background: rgba(17,24,44,0.8); border: 1px solid rgba(20,184,166,0.2); }
        .qa-btn.qa-scan .qa-icon { color: #14b8a6; filter: drop-shadow(0 0 4px rgba(20,184,166,0.5)); }
        .qa-btn.qa-scan .qa-label { color: rgba(20,184,166,0.8); }

        /* Add button — green accent */
        .qa-btn.qa-add { background: rgba(17,24,44,0.8); border: 1px solid rgba(34,197,94,0.2); }
        .qa-btn.qa-add .qa-icon { color: #22c55e; filter: drop-shadow(0 0 4px rgba(34,197,94,0.5)); font-size: 1.2rem; }
        .qa-btn.qa-add .qa-label { color: rgba(34,197,94,0.8); }

        /* Info button — subtle */
        .qa-btn.qa-info { background: rgba(17,24,44,0.8); border: 1px solid var(--border-subtle); }
        .qa-btn.qa-info .qa-icon { color: var(--text-secondary); }
        .qa-btn.qa-info .qa-label { color: var(--text-secondary); }

        /* Screen nav — REVERSED order */
        .screen-nav { position: fixed; bottom: 56px; left: 0; right: 0; z-index: 40; display: flex; justify-content: center; padding: 0 12px; }
        .screen-nav-inner { display: flex; gap: 4px; background: rgba(11, 15, 26, 0.9); backdrop-filter: blur(12px); border-radius: 14px; padding: 4px; border: 1px solid var(--border-subtle); box-shadow: 0 -4px 24px rgba(0,0,0,0.3); width: 100%; max-width: 400px; }
        .snav-btn { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 8px 4px; border-radius: 10px; border: none; background: transparent; color: var(--text-secondary); font-size: 0.6rem; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
        .snav-btn .snav-icon { font-size: 1.1rem; }
        .snav-btn.active { background: linear-gradient(135deg, rgba(99,102,241,0.25), rgba(79,70,229,0.15)); color: #fff; box-shadow: 0 0 12px rgba(99,102,241,0.2); }
        .snav-btn:active { transform: scale(0.95); }

        /* Bottom nav */
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 45; background: rgba(11, 15, 26, 0.95); backdrop-filter: blur(12px); border-top: 1px solid var(--border-subtle); display: flex; height: 56px; }
        .bnav-tab { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; font-size: 0.6rem; color: var(--text-secondary); text-decoration: none; transition: color 0.2s; }
        .bnav-tab.active { color: var(--indigo-400); text-shadow: 0 0 14px rgba(99,102,241,0.9); }
        .bnav-tab .bnav-icon { font-size: 1.2rem; }

        /* Drawers */
        .drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 100; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .drawer-overlay.open { opacity: 1; pointer-events: auto; }
        .drawer { position: fixed; bottom: 0; left: 0; right: 0; z-index: 101; background: var(--bg-main); border-radius: 20px 20px 0 0; transform: translateY(100%); transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1); max-height: 90vh; display: flex; flex-direction: column; }
        .drawer.open { transform: translateY(0); }
        .drawer-handle { width: 36px; height: 4px; border-radius: 2px; background: rgba(255,255,255,0.2); margin: 10px auto 0; flex-shrink: 0; }
        .drawer-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px 10px; border-bottom: 1px solid var(--border-subtle); flex-shrink: 0; }
        .drawer-header h3 { font-family: Nacelle, Inter, sans-serif; font-size: 1.05rem; font-weight: 700; margin: 0; }
        .drawer-close { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .drawer-body { flex: 1; overflow-y: auto; padding: 14px 16px 24px; -webkit-overflow-scrolling: touch; }

        /* AI Assist drawer */
        .ai-assist-input { display: flex; gap: 6px; margin-top: 12px; }
        .ai-assist-input input { flex: 1; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.85rem; outline: none; }
        .ai-assist-input button { padding: 10px 14px; border-radius: 10px; border: none; background: linear-gradient(135deg, var(--indigo-500), #4f46e5); color: #fff; font-size: 0.8rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .ai-assist-msg { padding: 10px 12px; border-radius: 10px; margin-bottom: 8px; font-size: 0.8rem; line-height: 1.5; }
        .ai-assist-msg.user { background: rgba(99,102,241,0.1); border: 1px solid var(--border-subtle); color: var(--text-primary); text-align: right; }
        .ai-assist-msg.ai { background: rgba(99,102,241,0.05); border: 1px solid rgba(99,102,241,0.08); color: var(--text-primary); }

        /* Info overlay */
        .info-overlay { position: fixed; inset: 0; z-index: 500; background: rgba(11,15,26,0.75); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); display: none; align-items: center; justify-content: center; padding: 20px; }
        .info-overlay.open { display: flex; }
        .info-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 24px 20px; max-width: 340px; width: 100%; max-height: 80vh; overflow-y: auto; box-shadow: 0 8px 40px rgba(99,102,241,0.2); }
        .info-card h3 { font-family: Nacelle, Inter, sans-serif; font-size: 1.1rem; margin: 0 0 12px; color: var(--indigo-300); }
        .info-item { margin-bottom: 12px; }
        .info-item .ii-title { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); margin-bottom: 3px; display: flex; align-items: center; gap: 6px; }
        .info-item .ii-title span { filter: drop-shadow(0 0 6px rgba(99,102,241,0.5)); }
        .info-item .ii-text { font-size: 0.75rem; color: var(--text-secondary); line-height: 1.5; }
        .info-close-btn { width: 100%; margin-top: 8px; padding: 10px; border-radius: 10px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.8rem; cursor: pointer; }

        /* Voice overlay */
        .voice-overlay { position: fixed; inset: 0; z-index: 400; background: rgba(11,15,26,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); display: none; flex-direction: column; align-items: center; justify-content: center; }
        .voice-overlay.open { display: flex; }
        /* ====== AI Voice Overlay (Module A) ====== */
        .rec-ov{position:fixed;inset:0;background:rgba(3,7,18,.5);z-index:400;display:none;align-items:flex-end;justify-content:center;padding:0 16px 24px;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
        .rec-ov.show{display:flex}
        .rec-box{width:100%;max-width:420px;background:rgba(17,24,39,.95);border:1px solid rgba(99,102,241,.3);border-radius:20px;padding:16px;box-shadow:0 20px 60px rgba(0,0,0,.8);animation:recFadeUp .3s ease both;max-height:70vh;display:flex;flex-direction:column}
        .rec-head{display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-shrink:0}
        .rec-dot{width:10px;height:10px;border-radius:50%;background:#6366f1;box-shadow:0 0 12px rgba(99,102,241,0.6);flex-shrink:0;transition:background .3s,box-shadow .3s}
        .rec-dot.recording{background:#ef4444;box-shadow:0 0 12px #ef4444;animation:recPulse 1.5s ease-out infinite}
        .rec-dot.done{background:#22c55e;box-shadow:0 0 12px #22c55e;animation:none}
        .rec-label{font-size:14px;font-weight:700;color:#a5b4fc;flex:1}
        .rec-x{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#9ca3af;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .rec-chat{flex:1;overflow-y:auto;margin-bottom:12px;max-height:40vh;min-height:0;scrollbar-width:thin;scrollbar-color:rgba(99,102,241,0.3) transparent}
        .rec-msg{padding:10px 12px;border-radius:12px;margin-bottom:8px;font-size:.8rem;line-height:1.5;max-width:88%;word-break:break-word}
        .rec-msg.user{background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.2);color:#e5e7eb;margin-left:auto;text-align:right}
        .rec-msg.ai{background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.1);color:#e5e7eb}
        .rec-msg.ai.thinking{color:rgba(165,180,252,0.6);font-style:italic}
        .rec-transcript{min-height:44px;max-height:100px;overflow-y:auto;padding:10px 14px;background:rgba(0,0,0,.3);border:1px solid rgba(99,102,241,.15);border-radius:12px;color:#e5e7eb;font-size:14px;line-height:1.5;font-family:inherit;outline:none;margin-bottom:10px;word-break:break-word;flex-shrink:0}
        .rec-transcript:focus{border-color:rgba(99,102,241,.35);box-shadow:0 0 12px rgba(99,102,241,0.1)}
        .rec-transcript:empty::before{content:attr(placeholder);color:rgba(165,180,252,.3);pointer-events:none}
        .rec-foot{display:flex;gap:8px;flex-shrink:0}
        .rec-mic{width:44px;height:44px;border-radius:12px;background:transparent;border:1px solid rgba(255,255,255,.1);color:#9ca3af;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
        .rec-mic.active{background:rgba(239,68,68,0.15);border-color:rgba(239,68,68,0.35);color:#ef4444;animation:recMicPulse 1.5s ease-out infinite}
        @keyframes recMicPulse{0%,100%{box-shadow:none}50%{box-shadow:0 0 12px rgba(239,68,68,0.3)}}
        .rec-send{flex:1;padding:12px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;box-shadow:0 4px 14px rgba(99,102,241,.35);transition:opacity .2s}
        .rec-send:disabled{opacity:.4;cursor:default}
        @keyframes recPulse{0%{box-shadow:0 0 0 0 rgba(239,68,68,.6)}70%{box-shadow:0 0 0 18px rgba(239,68,68,0)}100%{box-shadow:0 0 0 0 rgba(239,68,68,0)}}
        @keyframes recFadeUp{from{opacity:0;transform:translateY(15px)}to{opacity:1;transform:translateY(0)}}
        /* Modal, wizard, forms, sort, filter, pagination, toast, skeleton, etc */
        .modal-overlay { position: fixed; inset: 0; background: var(--bg-main); z-index: 200; opacity: 0; pointer-events: none; transition: opacity 0.3s; display: flex; flex-direction: column; }
        .modal-overlay.open { opacity: 1; pointer-events: auto; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-subtle); flex-shrink: 0; }
        .modal-header h2 { font-family: Nacelle, Inter, sans-serif; font-size: 1.1rem; font-weight: 700; margin: 0; }
        .modal-body { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; }
        .wizard-steps { display: flex; gap: 3px; padding: 10px 16px; }
        .wiz-step { flex: 1; height: 3px; border-radius: 2px; background: var(--border-subtle); transition: all 0.3s; }
        .wiz-step.active { background: linear-gradient(to right, var(--indigo-500), #8b5cf6); box-shadow: 0 0 6px rgba(99,102,241,0.3); }
        .wiz-step.done { background: var(--indigo-500); }
        .wiz-label { font-size: 0.65rem; color: var(--text-secondary); padding: 0 16px 6px; }
        .wiz-label span { color: var(--indigo-300); }
        .wizard-page { display: none; padding: 16px; }
        .wizard-page.active { display: block; animation: wiz-fade 0.2s ease; }
        @keyframes wiz-fade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        .form-group { margin-bottom: 12px; }
        .form-label { display: block; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.3px; }
        .form-label .fl-hint { color: rgba(107,114,128,0.8); font-weight: 400; text-transform: none; letter-spacing: 0; }
        .form-label .fl-ai { color: var(--indigo-500); }
        .form-label .fl-add { float: right; color: var(--indigo-500); font-weight: 600; cursor: pointer; text-transform: none; letter-spacing: 0; }
        .form-control { width: 100%; padding: 9px 12px; border-radius: 10px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.85rem; outline: none; transition: all 0.3s; font-family: Inter, system-ui, sans-serif; }
        .form-control:focus { border-color: var(--border-glow); box-shadow: 0 0 16px rgba(99,102,241,0.12); }
        .form-control::placeholder { color: var(--text-secondary); }
        .form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23818cf8'%3E%3Cpath d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .inline-add { background: rgba(99,102,241,0.04); border: 1px solid rgba(99,102,241,0.12); border-radius: 8px; padding: 8px; margin-top: 4px; display: none; gap: 6px; align-items: center; }
        .inline-add.open { display: flex; }
        .inline-add input { flex: 1; padding: 7px 10px; border-radius: 6px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.8rem; outline: none; }
        .inline-add button { padding: 7px 12px; border-radius: 6px; border: none; background: var(--indigo-500); color: #fff; font-size: 0.75rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .action-btn { padding: 10px; border-radius: 10px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s; font-family: Inter, system-ui, sans-serif; }
        .action-btn:active { background: var(--bg-card-hover); transform: scale(0.97); }
        .action-btn.primary { background: linear-gradient(135deg, var(--indigo-500), #4f46e5); border-color: transparent; color: #fff; box-shadow: 0 4px 14px rgba(99,102,241,0.25); }
        .action-btn.wide { width: 100%; }
        .action-btn.save { background: linear-gradient(to bottom, #22c55e, #16a34a); border-color: transparent; color: #fff; box-shadow: 0 4px 16px rgba(34,197,94,0.25); }
        .action-btn.danger { border-color: rgba(239,68,68,0.3); color: #ef4444; }
        .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }

        .sort-dropdown { position: absolute; top: 100%; right: 0; margin-top: 4px; background: var(--bg-main); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 4px; min-width: 180px; z-index: 60; display: none; box-shadow: 0 8px 32px rgba(0,0,0,0.4); }
        .sort-dropdown.open { display: block; }
        .sort-option { padding: 8px 12px; border-radius: 8px; font-size: 0.8rem; cursor: pointer; color: var(--text-secondary); }
        .sort-option.active { background: rgba(99,102,241,0.12); color: var(--indigo-300); }

        .filter-section { margin-bottom: 16px; }
        .filter-section .fs-title { font-size: 0.8rem; font-weight: 600; margin-bottom: 8px; color: var(--indigo-300); }
        .filter-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .filter-chip { padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .filter-chip.selected { background: rgba(99,102,241,0.2); color: var(--indigo-300); border-color: var(--border-glow); }

        .pagination { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 16px; }
        .page-btn { width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .page-btn.active { background: var(--indigo-500); color: #fff; border-color: transparent; }

        .empty-state { text-align: center; padding: 40px 20px; }
        .empty-state .es-icon { font-size: 3rem; margin-bottom: 12px; opacity: 0.5; }
        .empty-state .es-text { font-size: 0.9rem; color: var(--text-secondary); }
        .section-title { padding: 14px 16px 6px; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px; }
        .section-title::before { content: ''; display: inline-block; width: 24px; height: 1px; background: linear-gradient(to right, transparent, var(--indigo-500)); }
        .section-title::after { content: ''; flex: 1; height: 1px; background: linear-gradient(to left, transparent, var(--border-subtle)); }
        .indigo-line { height: 1px; background: linear-gradient(to right, transparent, var(--border-glow), transparent); margin: 12px 16px; }
        .stats-section { padding: 14px 16px; }
        .stats-section h4 { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; }
        .detail-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(99,102,241,0.08); }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-size: 0.75rem; color: var(--text-secondary); }
        .detail-value { font-size: 0.85rem; font-weight: 600; }
        .store-stock-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; border-radius: 8px; margin-bottom: 4px; background: rgba(17,24,44,0.5); }
        .ai-insight { padding: 10px 12px; border-radius: 10px; margin-bottom: 8px; display: flex; align-items: flex-start; gap: 10px; }
        .ai-insight.critical { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); }
        .ai-insight.high { background: rgba(234,179,8,0.08); border: 1px solid rgba(234,179,8,0.2); }
        .ai-insight.medium { background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2); }
        .ai-insight.info { background: rgba(99,102,241,0.05); border: 1px solid var(--border-subtle); }
        .ai-insight .ai-icon { font-size: 1.2rem; flex-shrink: 0; }
        .ai-insight .ai-text { font-size: 0.8rem; line-height: 1.4; }
        .ai-deeplink { display: inline-flex; align-items: center; gap: 4px; margin-top: 6px; padding: 4px 10px; border-radius: 6px; background: rgba(99,102,241,0.12); color: var(--indigo-300); font-size: 0.7rem; text-decoration: none; }

        .toast-container { position: fixed; top: 16px; left: 16px; right: 16px; z-index: 500; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
        .toast { padding: 12px 16px; border-radius: 10px; background: rgba(17, 24, 44, 0.95); border: 1px solid var(--border-subtle); backdrop-filter: blur(8px); color: var(--text-primary); font-size: 0.8rem; transform: translateY(-20px); opacity: 0; transition: all 0.3s; pointer-events: auto; display: flex; align-items: center; gap: 8px; }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.success { border-color: rgba(34,197,94,0.4); } .toast.error { border-color: rgba(239,68,68,0.4); }

        .skeleton { background: linear-gradient(90deg, var(--bg-card) 25%, rgba(99,102,241,0.08) 50%, var(--bg-card) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 8px; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        .camera-overlay { position: fixed; inset: 0; z-index: 300; background: #000; display: none; flex-direction: column; }
        .camera-overlay.open { display: flex; }
        .camera-video { flex: 1; object-fit: cover; }
        .camera-controls { padding: 16px; display: flex; justify-content: center; gap: 16px; background: rgba(0,0,0,0.8); }
        .camera-btn { width: 56px; height: 56px; border-radius: 50%; border: 3px solid #fff; background: transparent; color: #fff; font-size: 1.2rem; cursor: pointer; }
        .camera-btn.capture { background: #fff; color: #000; }
        .camera-btn.close-cam { border-color: #ef4444; color: #ef4444; }
        .scan-line { position: absolute; left: 10%; right: 10%; height: 2px; background: var(--indigo-500); box-shadow: 0 0 12px var(--indigo-500); top: 50%; animation: scan-anim 2s ease-in-out infinite; }
        @keyframes scan-anim { 0%, 100% { transform: translateY(-40px); } 50% { transform: translateY(40px); } }
        .green-flash { position: fixed; inset: 0; background: rgba(34,197,94,0.3); z-index: 301; pointer-events: none; animation: flash-out 0.5s ease-out forwards; }
        @keyframes flash-out { to { opacity: 0; } }
        @keyframes pulse-glow { 0%, 100% { opacity: 0.3; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.05); } }
    </style>
</head>
<body>
    <div class="bg-decoration"></div>
    <div class="bg-blur-shape bg-blur-1"></div>
    <div class="bg-blur-shape bg-blur-2"></div>
    <div class="toast-container" id="toasts"></div>

    <!-- Info Overlay -->
    <div class="info-overlay" id="infoOverlay" onclick="closeInfoOverlay()">
        <div class="info-card" onclick="event.stopPropagation()">
            <h3>📖 Как работи модулът?</h3>
            <div class="info-item"><div class="ii-title"><span>✦</span> AI Асистент</div><div class="ii-text">Натисни бутона и попитай каквото искаш — какво свършва, какво се продава добре, какво да поръчаш. AI знае всичко за артикулите ти.</div></div>
            <div class="info-item"><div class="ii-title"><span>⬡</span> Сканирай</div><div class="ii-text">Насочи камерата към баркода на артикула и той автоматично ще се намери. Бързо и без писане.</div></div>
            <div class="info-item"><div class="ii-title"><span>＋</span> Добави артикул</div><div class="ii-text">Можеш да снимаш артикула и AI ще попълни всичко вместо теб. Или продиктувай с глас. Или попълни на ръка.</div></div>
            <div class="info-item"><div class="ii-title"><span>🔍</span> Търсене</div><div class="ii-text">Пиши в полето горе — търси по име, код или баркод. Или натисни микрофона и кажи какво търсиш.</div></div>
            <div class="info-item"><div class="ii-title"><span>💀</span> Zombie / ⚠️ Ниска / 🔥 Топ</div><div class="ii-text">На Начало виждаш секции за стока която стои, свършва или се продава добре. AI ти казва какво да направиш.</div></div>
            <div class="info-item"><div class="ii-title"><span>📦</span> 4 екрана</div><div class="ii-text">Артикули, Категории, Доставчици и Начало — навигирай бързо от лентата отдолу.</div></div>
            <button class="info-close-btn" onclick="closeInfoOverlay()">Разбрах, затвори</button>
        </div>
    </div>

    <!-- Voice Overlay -->
    <div class="voice-overlay" id="voiceOverlay" onclick="closeVoiceOverlay()">
        <svg width="260" height="60" viewBox="0 0 260 60" onclick="event.stopPropagation()" style="margin-bottom:16px;">
            <path d="M0,30 Q30,10 60,30 T120,30 T180,30 T240,30 T260,30" stroke="rgba(99,102,241,.7)" stroke-width="2" fill="none"><animate attributeName="d" dur="1.5s" repeatCount="indefinite" values="M0,30 Q30,10 60,30 T120,30 T180,30 T240,30 T260,30;M0,30 Q30,50 60,30 T120,30 T180,30 T240,30 T260,30;M0,30 Q30,10 60,30 T120,30 T180,30 T240,30 T260,30"/></path>
            <path d="M0,30 Q40,45 80,30 T160,30 T240,30 T260,30" stroke="rgba(139,92,246,.4)" stroke-width="1.5" fill="none"><animate attributeName="d" dur="2s" repeatCount="indefinite" values="M0,30 Q40,45 80,30 T160,30 T240,30 T260,30;M0,30 Q40,15 80,30 T160,30 T240,30 T260,30;M0,30 Q40,45 80,30 T160,30 T240,30 T260,30"/></path>
        </svg>
        <div style="font-size:1rem;color:var(--text-primary);font-weight:500;margin-bottom:4px;" onclick="event.stopPropagation()">Слушам...</div>
        <div style="font-size:0.75rem;color:var(--indigo-300);margin-bottom:16px;" onclick="event.stopPropagation()">Кажи какво търсиш</div>
        <div id="voiceTranscript" style="background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.12);border-radius:12px;padding:10px 16px;max-width:280px;margin-bottom:16px;font-size:0.85rem;color:var(--text-primary);text-align:center;min-height:40px;" onclick="event.stopPropagation()"></div>
        <div style="font-size:0.7rem;color:var(--text-secondary);">Натисни навсякъде за да затвориш</div>
    </div>

    <div class="main-wrap">
        <!-- HEADER -->
        <div class="top-header">
            <div class="header-row">
                <h1>Артикули</h1>
                <select id="storeSelector" class="store-badge" onchange="switchStore(this.value)" style="background:rgba(99,102,241,0.15);border:1px solid var(--border-subtle);color:var(--indigo-300);padding:4px 8px;border-radius:999px;font-size:0.7rem;">
                    <?php foreach ($stores as $st): ?>
                        <option value="<?= $st['id'] ?>" <?= $st['id'] == $store_id ? 'selected' : '' ?>><?= htmlspecialchars($st['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- SEARCH -->
        <div class="search-wrap">
            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Търси артикул, код, баркод..." autocomplete="off">
            <div class="search-actions">
                <button class="search-btn" id="voiceBtn" onclick="toggleVoice()" title="Диктувай">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
                </button>
                <button class="search-btn" onclick="openFilterDrawer()" title="Филтри">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                </button>
            </div>
        </div>

        <!-- SCREEN: HOME -->
        <section id="screenHome" class="screen-section" style="display:<?= $screen === 'home' ? 'block' : 'none' ?>;">
            <div class="tabs-row">
                <button class="tab-btn active" data-tab="all" onclick="setHomeTab('all', this)">Всички <span class="count" id="countAll">-</span></button>
                <button class="tab-btn" data-tab="low" onclick="setHomeTab('low', this)">Ниска нал. <span class="count" id="countLow">-</span></button>
                <button class="tab-btn" data-tab="out" onclick="setHomeTab('out', this)">Изчерпани <span class="count" id="countOut">-</span></button>
            </div>
            <div class="quick-stats" id="quickStats">
                <?php if ($can_see_margin): ?>
                <div class="stat-card"><div class="stat-label">Капитал</div><div class="stat-value" id="statCapital">—</div><div class="stat-icon">💰</div></div>
                <div class="stat-card"><div class="stat-label">Ср. марж</div><div class="stat-value" id="statMargin">—</div><div class="stat-icon">📊</div></div>
                <?php else: ?>
                <div class="stat-card"><div class="stat-label">Артикули</div><div class="stat-value" id="statProducts">—</div><div class="stat-icon">📦</div></div>
                <div class="stat-card"><div class="stat-label">Общо бройки</div><div class="stat-value" id="statUnits">—</div><div class="stat-icon">📋</div></div>
                <?php endif; ?>
            </div>
            <!-- Collapse sections - ALWAYS shown, auto-open if data exists -->
            <div class="collapse-section" id="collapseZombie" style="display:none;">
                <div class="collapse-header" onclick="toggleCollapse('zombie')"><div class="ch-left"><span class="ch-icon">💀</span><span class="ch-title">Zombie стока</span><span class="ch-count" id="zombieCount">0</span></div><svg class="ch-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="collapse-body" id="collapseZombieBody"><div class="collapse-body-inner" id="zombieList"></div></div>
            </div>
            <div class="collapse-section" id="collapseLow" style="display:none;">
                <div class="collapse-header" onclick="toggleCollapse('low')"><div class="ch-left"><span class="ch-icon">⚠️</span><span class="ch-title">Свършват скоро</span><span class="ch-count" id="lowCount">0</span></div><svg class="ch-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="collapse-body" id="collapseLowBody"><div class="collapse-body-inner" id="lowList"></div></div>
            </div>
            <div class="collapse-section" id="collapseTop" style="display:none;">
                <div class="collapse-header" onclick="toggleCollapse('top')"><div class="ch-left"><span class="ch-icon">🔥</span><span class="ch-title">Топ 5 хитове</span><span class="ch-count" id="topCount">0</span></div><svg class="ch-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="collapse-body" id="collapseTopBody"><div class="collapse-body-inner" id="topList"></div></div>
            </div>
            <div class="collapse-section" id="collapseSlow" style="display:none;">
                <div class="collapse-header" onclick="toggleCollapse('slow')"><div class="ch-left"><span class="ch-icon">🐌</span><span class="ch-title">Бавно движещи се</span><span class="ch-count" id="slowCount">0</span></div><svg class="ch-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="collapse-body" id="collapseSlowBody"><div class="collapse-body-inner" id="slowList"></div></div>
            </div>
            <div id="homeProductsList" style="padding:0 16px;margin-top:8px;"></div>
            <div id="homePagination" class="pagination"></div>
        </section>

        <!-- SCREEN: SUPPLIERS -->
        <section id="screenSuppliers" class="screen-section" style="display:<?= $screen === 'suppliers' ? 'block' : 'none' ?>;">
            <div class="section-title">Доставчици</div>
            <div class="swipe-container" id="supplierCards"></div>
            <div class="indigo-line"></div>
            <div class="stats-section"><h4>Статистики доставчици</h4><div id="supplierStats"></div></div>
        </section>

        <!-- SCREEN: CATEGORIES -->
        <section id="screenCategories" class="screen-section" style="display:<?= $screen === 'categories' ? 'block' : 'none' ?>;">
            <?php if ($sup_id): ?>
                <div class="breadcrumb"><a href="products.php?screen=suppliers">Доставчици</a><span class="sep">›</span><span><?= htmlspecialchars($supplier_name) ?></span></div>
                <div class="section-title">Категории на <?= htmlspecialchars($supplier_name) ?></div>
                <div id="categoryList" style="background:var(--bg-card);margin:0 16px;border-radius:12px;border:1px solid var(--border-subtle);overflow:hidden;"></div>
            <?php else: ?>
                <div class="section-title">Всички категории</div>
                <div class="swipe-container" id="categoryCards"></div>
            <?php endif; ?>
        </section>

        <!-- SCREEN: PRODUCTS -->
        <section id="screenProducts" class="screen-section" style="display:<?= $screen === 'products' ? 'block' : 'none' ?>;">
            <?php if ($sup_id || $cat_id): ?>
                <div class="breadcrumb">
                    <?php if ($sup_id): ?><a href="products.php?screen=suppliers">Дост.</a><span class="sep">›</span><a href="products.php?screen=categories&sup=<?= $sup_id ?>"><?= htmlspecialchars($supplier_name) ?></a><?php endif; ?>
                    <?php if ($cat_id): ?><?php if ($sup_id): ?><span class="sep">›</span><?php else: ?><a href="products.php?screen=categories">Категории</a><span class="sep">›</span><?php endif; ?><span><?= htmlspecialchars($category_name) ?></span><?php endif; ?>
                </div>
                <?php if ($cross_link): ?><div class="cross-link" onclick="window.location='products.php?screen=products&cat=<?= $cat_id ?>'">📂 Виж всички <?= htmlspecialchars($cross_link['cat_name']) ?> (<?= $cross_link['suppliers'] ?> дост. · <?= $cross_link['count'] ?> арт.)</div><?php endif; ?>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 16px 0;position:relative;">
                <div class="section-title" style="padding:0;">Артикули</div>
                <button onclick="toggleSort()" style="background:transparent;border:1px solid var(--border-subtle);color:var(--text-secondary);padding:4px 10px;border-radius:8px;font-size:0.7rem;cursor:pointer;display:flex;align-items:center;gap:4px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5h10"/><path d="M11 9h7"/><path d="M11 13h4"/><path d="m3 17 3 3 3-3"/><path d="M6 18V4"/></svg>Сортирай
                </button>
                <div class="sort-dropdown" id="sortDropdown">
                    <div class="sort-option active" data-sort="name" onclick="setSort('name')">Име А→Я</div>
                    <div class="sort-option" data-sort="price_asc" onclick="setSort('price_asc')">Цена ↑</div>
                    <div class="sort-option" data-sort="price_desc" onclick="setSort('price_desc')">Цена ↓</div>
                    <div class="sort-option" data-sort="stock_asc" onclick="setSort('stock_asc')">Наличност ↑</div>
                    <div class="sort-option" data-sort="stock_desc" onclick="setSort('stock_desc')">Наличност ↓</div>
                    <?php if ($can_see_margin): ?><div class="sort-option" data-sort="margin_desc" onclick="setSort('margin_desc')">Марж ↓</div><?php endif; ?>
                    <div class="sort-option" data-sort="newest" onclick="setSort('newest')">Най-нови</div>
                </div>
            </div>
            <div id="productsList" style="padding:0 16px;margin-top:8px;"></div>
            <div id="productsPagination" class="pagination"></div>
        </section>
    </div><!-- /main-wrap -->

    <!-- QUICK ACTIONS BAR (4 small buttons) — above screen-nav -->
    <?php if ($can_add): ?>
    <div class="quick-actions">
        <div class="qa-inner">
            <button class="qa-btn qa-ai" onclick="openAIAssist()"><span class="qa-icon">✦</span><span class="qa-label">AI</span></button>
            <button class="qa-btn qa-scan" onclick="openCamera('scan')"><span class="qa-icon">⬡</span><span class="qa-label">Скан</span></button>
            <button class="qa-btn qa-add" onclick="openAddModal()"><span class="qa-icon">＋</span><span class="qa-label">Добави</span></button>
            <button class="qa-btn qa-info" onclick="openInfoOverlay()"><span class="qa-icon">ℹ</span><span class="qa-label">Инфо</span></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Screen nav — REVERSED: Артикули | Категории | Доставчици | Начало -->
    <div class="screen-nav"><div class="screen-nav-inner">
        <button class="snav-btn <?= $screen === 'products' ? 'active' : '' ?>" onclick="goScreen('products')"><span class="snav-icon">📋</span><span>Артикули</span></button>
        <button class="snav-btn <?= ($screen === 'categories') ? 'active' : '' ?>" onclick="goScreen('categories')"><span class="snav-icon">🏷</span><span>Категории</span></button>
        <button class="snav-btn <?= $screen === 'suppliers' ? 'active' : '' ?>" onclick="goScreen('suppliers')"><span class="snav-icon">📦</span><span>Доставчици</span></button>
        <button class="snav-btn <?= $screen === 'home' ? 'active' : '' ?>" onclick="goScreen('home')"><span class="snav-icon">🏠</span><span>Начало</span></button>
    </div></div>

    <!-- Bottom nav -->
    <nav class="bottom-nav">
        <a href="chat.php" class="bnav-tab"><span class="bnav-icon">✦</span>AI</a>
        <a href="warehouse.php" class="bnav-tab active"><span class="bnav-icon">📦</span>Склад</a>
        <a href="stats.php" class="bnav-tab"><span class="bnav-icon">📊</span>Справки</a>
        <a href="sale.php" class="bnav-tab"><span class="bnav-icon">⚡</span>Въвеждане</a>
    </nav>

    <!-- DRAWERS -->
    <div class="drawer-overlay" id="detailOverlay" onclick="closeDrawer('detail')"></div>
    <div class="drawer" id="detailDrawer"><div class="drawer-handle"></div><div class="drawer-header"><h3 id="detailTitle">Артикул</h3><button class="drawer-close" onclick="closeDrawer('detail')">✕</button></div><div class="drawer-body" id="detailBody"></div></div>

    <div class="drawer-overlay" id="aiOverlay" onclick="closeDrawer('ai')"></div>
    <div class="drawer" id="aiDrawer"><div class="drawer-handle"></div><div class="drawer-header"><h3>✦ AI Анализ</h3><button class="drawer-close" onclick="closeDrawer('ai')">✕</button></div><div class="drawer-body" id="aiBody"></div></div>

    <div class="drawer-overlay" id="filterOverlay" onclick="closeDrawer('filter')"></div>
    <div class="drawer" id="filterDrawer"><div class="drawer-handle"></div><div class="drawer-header"><h3>Филтри</h3><button class="drawer-close" onclick="closeDrawer('filter')">✕</button></div>
        <div class="drawer-body">
            <div class="filter-section"><div class="fs-title">Категория</div><div class="filter-chips" id="filterCats"><?php foreach ($all_categories as $cat): ?><button class="filter-chip" data-cat="<?= $cat['id'] ?>" onclick="toggleFilterChip(this, 'cat')"><?= htmlspecialchars($cat['name']) ?></button><?php endforeach; ?></div></div>
            <div class="filter-section"><div class="fs-title">Доставчик</div><div class="filter-chips" id="filterSups"><?php foreach ($all_suppliers as $sup): ?><button class="filter-chip" data-sup="<?= $sup['id'] ?>" onclick="toggleFilterChip(this, 'sup')"><?= htmlspecialchars($sup['name']) ?></button><?php endforeach; ?></div></div>
            <div class="filter-section"><div class="fs-title">Наличност</div><div class="filter-chips"><button class="filter-chip" data-stock="all" onclick="toggleFilterChip(this, 'stock')">Всички</button><button class="filter-chip" data-stock="in" onclick="toggleFilterChip(this, 'stock')">В наличност</button><button class="filter-chip" data-stock="low" onclick="toggleFilterChip(this, 'stock')">Ниска</button><button class="filter-chip" data-stock="out" onclick="toggleFilterChip(this, 'stock')">Изчерпани</button></div></div>
            <div class="filter-section"><div class="fs-title">Ценови диапазон</div><div style="display:flex;gap:8px;align-items:center;"><input type="number" class="form-control" id="priceFrom" placeholder="От" style="width:48%;"><span style="color:var(--text-secondary);">—</span><input type="number" class="form-control" id="priceTo" placeholder="До" style="width:48%;"></div></div>
            <button class="action-btn primary wide" onclick="applyFilters()" style="margin-top:12px;">Приложи</button>
            <button class="action-btn wide" onclick="clearFilters()" style="margin-top:6px;">Изчисти</button>
        </div>
    </div>

   <!-- AI Voice Overlay (Module A) -->
    <div class="rec-ov" id="recOverlay" onclick="closeAIOverlay()">
        <div class="rec-box" onclick="event.stopPropagation()">
            <div class="rec-head">
                <div class="rec-dot" id="recDot"></div>
                <span class="rec-label" id="recLabel">✦ AI Помощник</span>
                <button class="rec-x" onclick="closeAIOverlay()">✕</button>
            </div>
            <div class="rec-chat" id="recChat"></div>
            <div class="rec-transcript" id="recTranscript" contenteditable="true" placeholder="Попитай или продиктувай..."></div>
            <div class="rec-foot">
                <button class="rec-mic" id="recMic" onclick="toggleAIVoice()">🎤</button>
                <button class="rec-send" id="recSendBtn" onclick="sendAIText()">Изпрати →</button>
            </div>
        </div>
    </div>

    <!-- ADD MODAL (6-step wizard) -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-header">
            <button onclick="closeAddModal()" style="background:transparent;border:none;color:var(--text-secondary);font-size:1.1rem;cursor:pointer;">✕</button>
            <h2 id="modalTitle">Нов артикул</h2>
            <div style="width:28px;"></div>
        </div>
        <div class="wizard-steps" id="wizardSteps"></div>
        <div class="wiz-label" id="wizLabel"></div>
        <div class="modal-body" id="wizardBody"></div>
    </div>

    <!-- Camera overlay -->
    <div class="camera-overlay" id="cameraOverlay">
        <video class="camera-video" id="cameraVideo" playsinline autoplay></video>
        <div class="scan-line" id="scanLine" style="display:none;"></div>
        <canvas id="cameraCanvas" style="display:none;"></canvas>
        <div class="camera-controls">
            <button class="camera-btn close-cam" onclick="closeCamera()">✕</button>
            <button class="camera-btn capture" id="captureBtn" onclick="capturePhoto()" style="display:none;">📷</button>
        </div>
    </div>
    <input type="file" id="aiScanFileInput" accept="image/*" capture="environment" style="display:none;" onchange="handleAIScanFile(this)">

    <script>
    // ============================================================
    // STATE
    // ============================================================
    const STATE = {
        storeId: <?= (int)$store_id ?>, screen: '<?= $screen ?>', supId: <?= $sup_id ? $sup_id : 'null' ?>, catId: <?= $cat_id ? $cat_id : 'null' ?>,
        canAdd: <?= $can_add ? 'true' : 'false' ?>, canSeeCost: <?= $can_see_cost ? 'true' : 'false' ?>, canSeeMargin: <?= $can_see_margin ? 'true' : 'false' ?>,
        currentSort: 'name', currentFilter: 'all', currentPage: 1, editProductId: null, productType: null,
        selectedSizes: {}, selectedColors: [], sizePrices: {}, colorPrices: {}, variantCombs: [], aiScanData: null,
        wizStep: 0, printFormat: 'qr',
        cameraMode: null, cameraStream: null, barcodeDetector: null, barcodeInterval: null,
        recognition: null, isListening: false
    };
    const WIZ_LABELS = ['Вид','AI разпознаване','Основна информация','Варианти','Детайли','Преглед и запис'];
    const COLOR_PALETTE = <?= json_encode($COLOR_PALETTE, JSON_UNESCAPED_UNICODE) ?>;
    const ALL_SUPPLIERS_JSON = <?= json_encode($all_suppliers, JSON_UNESCAPED_UNICODE) ?>;
    const ALL_CATEGORIES_JSON = <?= json_encode($all_categories, JSON_UNESCAPED_UNICODE) ?>;
    const UNITS_JSON = <?= json_encode($onboarding_units, JSON_UNESCAPED_UNICODE) ?>;

    // ============================================================
    // UTILITIES
    // ============================================================
    function fmtPrice(v){if(v===null||v===undefined)return'—';return parseFloat(v).toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2})+' €';}
    function fmtNum(v){if(v===null||v===undefined)return'—';return parseInt(v).toLocaleString('de-DE');}
    function stockClass(q,m){if(q<=0)return'out';if(m>0&&q<=m)return'low';return'ok';}
    function stockBarColor(q,m){if(q<=0)return'red';if(m>0&&q<=m)return'yellow';return'green';}
    function escHTML(s){if(!s)return'';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
    function showToast(m,t='info'){const c=document.getElementById('toasts');const e=document.createElement('div');e.className=`toast ${t}`;e.innerHTML=`<span>${t==='success'?'✓':t==='error'?'✕':'ℹ'}</span> ${m}`;c.appendChild(e);requestAnimationFrame(()=>e.classList.add('show'));setTimeout(()=>{e.classList.remove('show');setTimeout(()=>e.remove(),300);},3000);}
    async function fetchJSON(u,o={}){try{const r=await fetch(u,o);return await r.json();}catch(e){console.error(e);showToast('Грешка','error');return null;}}
    function productCardHTML(p){const sc=stockClass(p.store_stock||p.qty||0,p.min_quantity||0);const bc=stockBarColor(p.store_stock||p.qty||0,p.min_quantity||0);const q=p.store_stock||p.qty||0;const thumb=p.image?`<img src="${p.image}">`:`<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="rgba(99,102,241,0.4)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>`;const disc=(p.discount_pct&&p.discount_pct>0)?`<div class="pc-discount">-${p.discount_pct}%</div>`:'';return`<div class="product-card" onclick="openProductDetail(${p.id})"><div class="stock-bar ${bc}"></div><div class="pc-thumb">${thumb}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta">${p.code?`<span>${escHTML(p.code)}</span>`:''}${p.supplier_name?`<span>${escHTML(p.supplier_name)}</span>`:''}</div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.retail_price)}</div><div class="pc-stock ${sc}">${q} ${p.unit||'бр.'}</div></div>${disc}</div>`;}

    // ============================================================
    // NAVIGATION
    // ============================================================
    function goScreen(s,p={}){let u=`products.php?screen=${s}`;if(p.sup)u+=`&sup=${p.sup}`;if(p.cat)u+=`&cat=${p.cat}`;window.location.href=u;}
    function switchStore(s){STATE.storeId=parseInt(s);loadCurrentScreen();}

    // ============================================================
    // HOME SCREEN
    // ============================================================
    let homeTab='all',homePageNum=1;
    async function loadHomeScreen(){const d=await fetchJSON(`products.php?ajax=home_stats&store_id=${STATE.storeId}`);if(!d)return;
    if(STATE.canSeeMargin){document.getElementById('statCapital').textContent=fmtPrice(d.capital);document.getElementById('statMargin').textContent=d.avg_margin!==null?d.avg_margin+'%':'—';}else{const sp=document.getElementById('statProducts'),su=document.getElementById('statUnits');if(sp)sp.textContent=fmtNum(d.counts?.total_products);if(su)su.textContent=fmtNum(d.counts?.total_units);}
    document.getElementById('countAll').textContent=d.counts?.total_products||0;document.getElementById('countLow').textContent=d.low_stock?.length||0;document.getElementById('countOut').textContent=d.out_of_stock?.length||0;
    // Auto-show and auto-open collapse sections
    if(d.zombies?.length>0){const el=document.getElementById('collapseZombie');el.style.display='block';document.getElementById('zombieCount').textContent=d.zombies.length;document.getElementById('zombieList').innerHTML=d.zombies.map(p=>`<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;"><div class="stock-bar red"></div><div class="pc-thumb">${p.image?`<img src="${p.image}">`:'💀'}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta"><span>${p.days_stale} дни без продажба</span></div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.retail_price)}</div><div class="pc-stock out">${p.qty} бр.</div></div></div>`).join('');el.querySelector('.collapse-header').classList.add('open');el.querySelector('.collapse-body').classList.add('open');}
    if(d.low_stock?.length>0){const el=document.getElementById('collapseLow');el.style.display='block';document.getElementById('lowCount').textContent=d.low_stock.length;document.getElementById('lowList').innerHTML=d.low_stock.map(p=>`<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;"><div class="stock-bar yellow"></div><div class="pc-thumb">${p.image?`<img src="${p.image}">`:'⚠️'}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta"><span>Мин: ${p.min_quantity}</span></div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.retail_price)}</div><div class="pc-stock low">${p.qty} бр.</div></div></div>`).join('');el.querySelector('.collapse-header').classList.add('open');el.querySelector('.collapse-body').classList.add('open');}
    if(d.top_sellers?.length>0){const el=document.getElementById('collapseTop');el.style.display='block';document.getElementById('topCount').textContent=d.top_sellers.length;document.getElementById('topList').innerHTML=d.top_sellers.map((p,i)=>`<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;"><div class="stock-bar green"></div><div class="pc-thumb" style="font-size:1.2rem;font-weight:700;color:var(--indigo-300);">#${i+1}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta"><span>${p.sold_qty} продадени</span></div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.revenue)}</div></div></div>`).join('');el.querySelector('.collapse-header').classList.add('open');el.querySelector('.collapse-body').classList.add('open');}
    if(d.slow_movers?.length>0){const el=document.getElementById('collapseSlow');el.style.display='block';document.getElementById('slowCount').textContent=d.slow_movers.length;document.getElementById('slowList').innerHTML=d.slow_movers.map(p=>`<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;"><div class="stock-bar yellow"></div><div class="pc-thumb">${p.image?`<img src="${p.image}">`:'🐌'}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta"><span>${p.days_stale} дни</span></div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.retail_price)}</div><div class="pc-stock low">${p.qty} бр.</div></div></div>`).join('');el.querySelector('.collapse-header').classList.add('open');el.querySelector('.collapse-body').classList.add('open');}
    loadHomeProducts();}
    async function loadHomeProducts(){const f=homeTab==='all'?'':`&filter=${homeTab}`;const d=await fetchJSON(`products.php?ajax=products&store_id=${STATE.storeId}${f}&page=${homePageNum}&sort=${STATE.currentSort}`);if(!d)return;const el=document.getElementById('homeProductsList');el.innerHTML=d.products.length===0?`<div class="empty-state"><div class="es-icon">📦</div><div class="es-text">Няма артикули</div></div>`:d.products.map(productCardHTML).join('');renderPagination('homePagination',d.page,d.pages,p=>{homePageNum=p;loadHomeProducts();});}
    function setHomeTab(t,b){homeTab=t;homePageNum=1;document.querySelectorAll('.tabs-row .tab-btn').forEach(x=>x.classList.remove('active'));b.classList.add('active');loadHomeProducts();}

    // ============================================================
    // SUPPLIERS, CATEGORIES, PRODUCTS
    // ============================================================
    async function loadSuppliers(){const d=await fetchJSON(`products.php?ajax=suppliers&store_id=${STATE.storeId}`);if(!d)return;const el=document.getElementById('supplierCards');if(!d.length){el.innerHTML=`<div class="empty-state" style="width:100%;"><div class="es-icon">📦</div><div class="es-text">Няма доставчици</div></div>`;return;}el.innerHTML=d.map(s=>{const ok=s.product_count-s.low_count-s.out_count;return`<div class="supplier-card" onclick="goScreen('categories',{sup:${s.id}})"><div class="sc-name">${escHTML(s.name)}</div><div class="sc-count">${s.product_count} арт. · ${fmtNum(s.total_stock)} бр.</div><div class="sc-badges">${ok>0?`<span class="sc-badge ok">✓ ${ok}</span>`:''}${s.low_count>0?`<span class="sc-badge low">↓ ${s.low_count}</span>`:''}${s.out_count>0?`<span class="sc-badge out">✕ ${s.out_count}</span>`:''}</div><div class="sc-arrow">›</div></div>`;}).join('');const st=document.getElementById('supplierStats');st.innerHTML=[...d].sort((a,b)=>b.total_stock-a.total_stock).slice(0,5).map((s,i)=>`<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(99,102,241,0.08);font-size:0.8rem;"><span><span style="color:var(--indigo-400);font-weight:700;">#${i+1}</span> ${escHTML(s.name)}</span><span style="font-weight:600;">${fmtNum(s.total_stock)} бр.</span></div>`).join('');}
    async function loadCategories(){const sp=STATE.supId?`&sup=${STATE.supId}`:'';const d=await fetchJSON(`products.php?ajax=categories&store_id=${STATE.storeId}${sp}`);if(!d)return;if(STATE.supId){const el=document.getElementById('categoryList');el.innerHTML=d.length===0?`<div class="empty-state" style="padding:20px;"><div class="es-text">Няма категории</div></div>`:d.map(c=>`<div class="cat-list-item" onclick="goScreen('products',{sup:${STATE.supId},cat:${c.id}})"><div class="cli-left"><div class="cli-icon">🏷</div><div><div class="cli-name">${escHTML(c.name)}</div><div class="cli-count">${c.product_count} арт. · ${fmtNum(c.total_stock)} бр.</div></div></div><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-secondary);"><polyline points="9 18 15 12 9 6"/></svg></div>`).join('');}else{const el=document.getElementById('categoryCards');el.innerHTML=d.length===0?`<div class="empty-state" style="width:100%;"><div class="es-text">Няма категории</div></div>`:d.map(c=>`<div class="category-card" onclick="goScreen('products',{cat:${c.id}})"><div class="cc-name">${escHTML(c.name)}</div><div class="cc-info">${c.product_count} арт.${c.supplier_count?' · '+c.supplier_count+' дост.':''} · ${fmtNum(c.total_stock)} бр.</div></div>`).join('');}}
    async function loadProductsList(){let p=`store_id=${STATE.storeId}&sort=${STATE.currentSort}&page=${STATE.currentPage}`;if(STATE.supId)p+=`&sup=${STATE.supId}`;if(STATE.catId)p+=`&cat=${STATE.catId}`;if(STATE.currentFilter!=='all')p+=`&filter=${STATE.currentFilter}`;const d=await fetchJSON(`products.php?ajax=products&${p}`);if(!d)return;const el=document.getElementById('productsList');el.innerHTML=d.products.length===0?`<div class="empty-state"><div class="es-icon">📋</div><div class="es-text">Няма артикули</div></div>`:d.products.map(productCardHTML).join('');renderPagination('productsPagination',d.page,d.pages,pg=>{STATE.currentPage=pg;loadProductsList();});}

    // ============================================================
    // PAGINATION, COLLAPSE, SORT, SEARCH
    // ============================================================
    function renderPagination(cid,cur,tot,cb){const el=document.getElementById(cid);if(tot<=1){el.innerHTML='';return;}let h='';if(cur>1)h+=`<button class="page-btn" data-p="${cur-1}">‹</button>`;for(let i=Math.max(1,cur-2);i<=Math.min(tot,cur+2);i++)h+=`<button class="page-btn ${i===cur?'active':''}" data-p="${i}">${i}</button>`;if(cur<tot)h+=`<button class="page-btn" data-p="${cur+1}">›</button>`;el.innerHTML=h;el.querySelectorAll('.page-btn').forEach(b=>b.addEventListener('click',()=>cb(parseInt(b.dataset.p))));}
    function toggleCollapse(id){const m={zombie:'collapseZombie',low:'collapseLow',top:'collapseTop',slow:'collapseSlow'};const s=document.getElementById(m[id]);s.querySelector('.collapse-header').classList.toggle('open');s.querySelector('.collapse-body').classList.toggle('open');}
    function toggleSort(){document.getElementById('sortDropdown').classList.toggle('open');}
    function setSort(s){STATE.currentSort=s;document.querySelectorAll('.sort-option').forEach(o=>o.classList.toggle('active',o.dataset.sort===s));document.getElementById('sortDropdown').classList.remove('open');STATE.screen==='products'?loadProductsList():loadHomeProducts();}
    let searchTimeout;document.getElementById('searchInput').addEventListener('input',function(){clearTimeout(searchTimeout);const q=this.value.trim();if(q.length<1){loadCurrentScreen();return;}searchTimeout=setTimeout(async()=>{const d=await fetchJSON(`products.php?ajax=search&q=${encodeURIComponent(q)}&store_id=${STATE.storeId}`);if(!d)return;const el=document.getElementById(STATE.screen==='products'?'productsList':'homeProductsList');el.innerHTML=d.length===0?`<div class="empty-state"><div class="es-icon">🔍</div><div class="es-text">Нищо за "${escHTML(q)}"</div></div>`:d.map(p=>productCardHTML({...p,store_stock:p.total_stock})).join('');},300);});

    // ============================================================
    // PRODUCT DETAIL & AI DRAWER
    // ============================================================
    async function openProductDetail(id){openDrawer('detail');document.getElementById('detailBody').innerHTML='<div style="text-align:center;padding:20px;"><div class="skeleton" style="width:60%;height:20px;margin:0 auto 12px;"></div></div>';const d=await fetchJSON(`products.php?ajax=product_detail&id=${id}`);if(!d||d.error){showToast('Грешка','error');closeDrawer('detail');return;}const p=d.product;document.getElementById('detailTitle').textContent=p.name;let h='';if(p.image)h+=`<div style="text-align:center;margin-bottom:12px;"><img src="${p.image}" style="max-width:200px;border-radius:12px;border:1px solid var(--border-subtle);"></div>`;h+=`<div class="detail-row"><span class="detail-label">Код</span><span class="detail-value">${escHTML(p.code)||'—'}</span></div>`;h+=`<div class="detail-row"><span class="detail-label">Цена</span><span class="detail-value">${fmtPrice(p.retail_price)}</span></div>`;if(STATE.canSeeCost)h+=`<div class="detail-row"><span class="detail-label">Доставна</span><span class="detail-value">${fmtPrice(p.cost_price)}</span></div>`;h+=`<div class="detail-row"><span class="detail-label">Доставчик</span><span class="detail-value">${escHTML(p.supplier_name)||'—'}</span></div>`;h+=`<div class="detail-row"><span class="detail-label">Категория</span><span class="detail-value">${escHTML(p.category_name)||'—'}</span></div>`;h+=`<div style="margin-top:14px;font-size:0.7rem;font-weight:600;color:var(--text-secondary);text-transform:uppercase;margin-bottom:6px;">Наличност по обекти</div>`;d.stocks.forEach(s=>h+=`<div class="store-stock-row"><span style="font-size:0.8rem;">${escHTML(s.store_name)}</span><span style="font-size:0.85rem;font-weight:700;color:${s.qty>0?'#22c55e':'#ef4444'};">${s.qty} бр.</span></div>`);if(d.variations?.length>0){h+=`<div style="margin-top:14px;font-size:0.7rem;font-weight:600;color:var(--text-secondary);text-transform:uppercase;margin-bottom:6px;">Вариации</div>`;d.variations.forEach(v=>h+=`<div class="product-card" onclick="openProductDetail(${v.id})" style="margin-bottom:4px;"><div class="stock-bar ${stockBarColor(v.total_stock,0)}"></div><div class="pc-info" style="margin-left:10px;"><div class="pc-name" style="font-size:0.8rem;">${escHTML(v.name)}</div></div><div class="pc-right"><div class="pc-price">${fmtPrice(v.retail_price)}</div><div class="pc-stock ${stockClass(v.total_stock,0)}">${v.total_stock} бр.</div></div></div>`);}h+=`<div class="action-grid">`;if(STATE.canAdd)h+=`<button class="action-btn" onclick="editProduct(${p.id})">✏️ Редактирай</button>`;h+=`<button class="action-btn" onclick="openAIDrawer(${p.id})">✦ AI Съвет</button><button class="action-btn primary" onclick="window.location='sale.php?product=${p.id}'">💰 Продажба</button></div>`;document.getElementById('detailBody').innerHTML=h;}
    async function openAIDrawer(id){closeDrawer('detail');setTimeout(()=>openDrawer('ai'),350);document.getElementById('aiBody').innerHTML='<div style="text-align:center;padding:20px;">✦ Анализирам...</div>';const d=await fetchJSON(`products.php?ajax=ai_analyze&id=${id}`);if(!d)return;let h='';if(!d.analysis.length)h=`<div class="ai-insight info"><span class="ai-icon">✓</span><span class="ai-text">Всичко е наред.</span></div>`;else d.analysis.forEach(a=>h+=`<div class="ai-insight ${a.severity}"><span class="ai-icon">${a.icon}</span><span class="ai-text">${a.text}</span></div>`);h+=`<div style="margin-top:12px;"><a class="ai-deeplink" href="chat.php?ctx=product&id=${id}">💬 Попитай AI</a></div>`;document.getElementById('aiBody').innerHTML=h;}

    // ============================================================
    // DRAWERS
    // ============================================================
    function openDrawer(n){document.getElementById(n+'Overlay').classList.add('open');document.getElementById(n+'Drawer').classList.add('open');document.body.style.overflow='hidden';}
    function closeDrawer(n){document.getElementById(n+'Overlay').classList.remove('open');document.getElementById(n+'Drawer').classList.remove('open');document.body.style.overflow='';}
    ['detail','ai','filter'].forEach(n=>{const d=document.getElementById(n+'Drawer');if(!d)return;let sy=0,cy=0,drag=false;d.addEventListener('touchstart',e=>{if(e.target.closest('.drawer-body')?.scrollTop>0)return;sy=e.touches[0].clientY;drag=true;},{passive:true});d.addEventListener('touchmove',e=>{if(!drag)return;cy=e.touches[0].clientY-sy;if(cy>0)d.style.transform=`translateY(${cy}px)`;},{passive:true});d.addEventListener('touchend',()=>{if(!drag)return;drag=false;if(cy>100)closeDrawer(n);d.style.transform='';cy=0;});});

    // ============================================================
    // AI VOICE OVERLAY (Module A)
    // ============================================================
    var aiVoiceRec = null;
    var aiIsRec = false;

    function openAIAssist() {
        var overlay = document.getElementById('recOverlay');
        var chat = document.getElementById('recChat');
        var transcript = document.getElementById('recTranscript');
        var dot = document.getElementById('recDot');
        var label = document.getElementById('recLabel');
        var sendBtn = document.getElementById('recSendBtn');

        overlay.classList.add('show');
        chat.innerHTML = '';
        transcript.innerText = '';
        dot.className = 'rec-dot';
        label.textContent = '✦ AI Помощник';
        sendBtn.disabled = false;
        document.body.style.overflow = 'hidden';

        // Авто-старт voice след кратка пауза
        setTimeout(function() { toggleAIVoice(); }, 400);
    }

    function closeAIOverlay() {
        stopAIVoice();
        document.getElementById('recOverlay').classList.remove('show');
        document.body.style.overflow = '';
        // Restore chat visibility (за съвместимост с wizard mode)
        var chatEl = document.getElementById('recChat');
        if (chatEl) chatEl.style.display = '';
        var sendBtn = document.getElementById('recSendBtn');
        if (sendBtn) sendBtn.onclick = function() { sendAIText(); };
    }

    function toggleAIVoice() {
        if (aiIsRec) { stopAIVoice(); return; }
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SR) { showToast('Гласовото въвеждане не е поддържано', 'error'); return; }

        aiIsRec = true;
        var micBtn = document.getElementById('recMic');
        var dot = document.getElementById('recDot');
        var label = document.getElementById('recLabel');
        var transcript = document.getElementById('recTranscript');

        micBtn.classList.add('active');
        dot.className = 'rec-dot recording';
        label.textContent = 'Слушам...';
        transcript.innerText = '';

        aiVoiceRec = new SR();
        aiVoiceRec.lang = 'bg-BG';
        aiVoiceRec.interimResults = true;
        aiVoiceRec.continuous = false;

        aiVoiceRec.onresult = function(e) {
            var finalText = '', interimText = '';
            for (var i = 0; i < e.results.length; i++) {
                if (e.results[i].isFinal) finalText += e.results[i][0].transcript;
                else interimText += e.results[i][0].transcript;
            }
            // ВАЖНО: innerText, НИКОГА innerHTML
            transcript.innerText = finalText + (interimText ? ' ' + interimText : '');
            if (interimText) label.textContent = 'Слушам...';
            else if (finalText.trim()) label.textContent = 'Готово — редактирай или изпрати';
        };

        aiVoiceRec.onerror = function(e) { console.log('AI voice error:', e.error); stopAIVoice(); };

        aiVoiceRec.onend = function() {
            if (aiIsRec) {
                dot.className = 'rec-dot done';
                label.textContent = 'Готово — редактирай или изпрати';
                aiIsRec = false;
                micBtn.classList.remove('active');
            }
        };

        try { aiVoiceRec.start(); } catch(e) { console.log('AI voice start error:', e); stopAIVoice(); }
    }

    function stopAIVoice() {
        aiIsRec = false;
        if (aiVoiceRec) { try { aiVoiceRec.stop(); } catch(e) {} aiVoiceRec = null; }
        var micBtn = document.getElementById('recMic');
        var dot = document.getElementById('recDot');
        if (micBtn) micBtn.classList.remove('active');
        if (dot) dot.className = 'rec-dot';
    }

    function sendAIText() {
        var transcript = document.getElementById('recTranscript');
        var txt = transcript.innerText.trim();
        if (!txt) return;
        stopAIVoice();

        var chat = document.getElementById('recChat');
        var label = document.getElementById('recLabel');
        var sendBtn = document.getElementById('recSendBtn');

        // Покажи въпроса
        var userDiv = document.createElement('div');
        userDiv.className = 'rec-msg user';
        userDiv.textContent = txt;
        chat.appendChild(userDiv);

        // Изчисти
        transcript.innerText = '';

        // Покажи "мисля..."
        var thinkDiv = document.createElement('div');
        thinkDiv.className = 'rec-msg ai thinking';
        thinkDiv.textContent = '✦ Мисля...';
        chat.appendChild(thinkDiv);
        chat.scrollTop = chat.scrollHeight;

        label.textContent = '✦ Обработвам...';
        sendBtn.disabled = true;

        // POST към AI
        fetch('products.php?ajax=ai_assist', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ question: txt })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            thinkDiv.className = 'rec-msg ai';
            thinkDiv.textContent = d.response || 'Не успях да отговоря.';
            label.textContent = '✦ AI Помощник';
            sendBtn.disabled = false;
            chat.scrollTop = chat.scrollHeight;
        })
        .catch(function(err) {
            console.error('AI assist error:', err);
            thinkDiv.className = 'rec-msg ai';
            thinkDiv.textContent = 'Грешка при свързване.';
            label.textContent = '✦ AI Помощник';
            sendBtn.disabled = false;
            chat.scrollTop = chat.scrollHeight;
        });
    }

    // Enter за изпращане, focus спира записа
    (function() {
        var el = document.getElementById('recTranscript');
        if (el) {
            el.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendAIText(); }
            });
            el.addEventListener('focus', function() {
                if (aiIsRec) {
                    stopAIVoice();
                    document.getElementById('recDot').className = 'rec-dot done';
                    document.getElementById('recLabel').textContent = 'Готово — редактирай или изпрати';
                }
            });
        }
    })();

    // ============================================================
    // INFO OVERLAY
    // ============================================================
    function openInfoOverlay(){document.getElementById('infoOverlay').classList.add('open');}
    function closeInfoOverlay(){document.getElementById('infoOverlay').classList.remove('open');}

    // ============================================================
    // FILTER
    // ============================================================
    function openFilterDrawer(){openDrawer('filter');}
    function toggleFilterChip(el,t){if(t==='stock'){el.parentElement.querySelectorAll('.filter-chip').forEach(c=>c.classList.remove('selected'));el.classList.add('selected');}else el.classList.toggle('selected');}
    function applyFilters(){const c=document.querySelector('#filterCats .filter-chip.selected');const s=document.querySelector('#filterSups .filter-chip.selected');let u='products.php?screen=products';if(s)u+=`&sup=${s.dataset.sup}`;if(c)u+=`&cat=${c.dataset.cat}`;const stk=document.querySelector('[data-stock].selected');if(stk&&stk.dataset.stock!=='all')u+=`&filter=${stk.dataset.stock}`;window.location.href=u;}
    function clearFilters(){document.querySelectorAll('.filter-chip').forEach(c=>c.classList.remove('selected'));document.getElementById('priceFrom').value='';document.getElementById('priceTo').value='';}

    // ============================================================
    // VOICE SEARCH (fixed)
    // ============================================================
    function toggleVoice(){
        if(STATE.isListening){closeVoiceOverlay();return;}
        const SRClass = window.SpeechRecognition || window.webkitSpeechRecognition;
        if(!SRClass){showToast('Гласовото търсене не е поддържано в този браузър','error');return;}
        STATE.recognition = new SRClass();
        STATE.recognition.lang = 'bg-BG';
        STATE.recognition.continuous = false;
        STATE.recognition.interimResults = true;
        STATE.isListening = true;
        document.getElementById('voiceBtn').classList.add('listening');
        document.getElementById('voiceOverlay').classList.add('open');
        document.getElementById('voiceTranscript').textContent = '';
        STATE.recognition.onresult = function(e){
            let transcript = '';
            for(let i=0;i<e.results.length;i++) transcript += e.results[i][0].transcript;
            document.getElementById('voiceTranscript').textContent = '"'+transcript+'"';
            if(e.results[0].isFinal){
                document.getElementById('searchInput').value = transcript;
                document.getElementById('searchInput').dispatchEvent(new Event('input'));
                setTimeout(()=>closeVoiceOverlay(),600);
            }
        };
        STATE.recognition.onerror = function(e){console.log('Speech error:',e.error);closeVoiceOverlay();};
        STATE.recognition.onend = function(){if(STATE.isListening)closeVoiceOverlay();};
        try{STATE.recognition.start();}catch(e){closeVoiceOverlay();}
    }
    function closeVoiceOverlay(){
        if(STATE.recognition){try{STATE.recognition.stop();}catch(e){}}
        STATE.isListening = false;
        document.getElementById('voiceBtn').classList.remove('listening');
        document.getElementById('voiceOverlay').classList.remove('open');
    }

    // ============================================================
    // ADD MODAL (wizard — simplified for stability)
    // ============================================================
    function openAddModal(){STATE.editProductId=null;STATE.wizStep=0;STATE.productType=null;STATE.selectedSizes={};STATE.selectedColors=[];STATE.aiScanData=null;STATE.variantCombs=[];document.getElementById('modalTitle').textContent='Нов артикул';renderWizard();document.getElementById('addModal').classList.add('open');document.body.style.overflow='hidden';}
    function closeAddModal(){document.getElementById('addModal').classList.remove('open');document.body.style.overflow='';}
    function renderWizard(){let sb='';for(let i=0;i<6;i++){let cls=i<STATE.wizStep?'done':i===STATE.wizStep?'active':'';sb+=`<div class="wiz-step ${cls}"></div>`;}document.getElementById('wizardSteps').innerHTML=sb;document.getElementById('wizLabel').innerHTML=`${STATE.wizStep+1} · <span>${WIZ_LABELS[STATE.wizStep]}</span>`;document.getElementById('wizardBody').innerHTML=renderWizPage(STATE.wizStep);document.getElementById('wizardBody').scrollTop=0;}
    function wizGo(n){STATE.wizStep=n;renderWizard();}

    function renderWizPage(step){
        if(step===0){const ss=STATE.productType==='single'?'border-color:var(--indigo-500);background:rgba(99,102,241,0.1);box-shadow:0 0 16px rgba(99,102,241,0.15);':'';const vs=STATE.productType==='variant'?'border-color:var(--indigo-500);background:rgba(99,102,241,0.1);box-shadow:0 0 16px rgba(99,102,241,0.15);':'';return`<div class="wizard-page active" style="padding:16px;"><div style="font-size:1rem;font-weight:700;margin-bottom:4px;">Какъв е артикулът?</div><div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:14px;">Изборът определя следващите стъпки</div><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;"><div style="padding:18px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);text-align:center;cursor:pointer;${ss}" onclick="STATE.productType='single';renderWizard();"><div style="font-size:2rem;margin-bottom:4px;">📦</div><div style="font-size:0.85rem;font-weight:600;">Единичен</div></div><div style="padding:18px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);text-align:center;cursor:pointer;${vs}" onclick="STATE.productType='variant';renderWizard();"><div style="font-size:2rem;margin-bottom:4px;">🎨</div><div style="font-size:0.85rem;font-weight:600;">С варианти</div></div></div>${STATE.productType?`<button class="action-btn primary wide" onclick="wizGo(1)">Напред →</button>`:''}</div>`;}
        if(step===1){return`<div class="wizard-page active" style="padding:16px;text-align:center;"><div style="font-size:1rem;font-weight:600;margin-bottom:4px;">Снимай и AI попълва</div><div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:16px;">Камера → AI разпознава → предпопълва</div><div style="width:80px;height:80px;margin:0 auto 14px;border-radius:50%;background:linear-gradient(135deg,var(--indigo-500),#4f46e5);display:flex;align-items:center;justify-content:center;box-shadow:0 0 30px rgba(99,102,241,0.4);cursor:pointer;" onclick="document.getElementById('aiScanFileInput').click();"><span style="font-size:1.5rem;">✦</span></div><div id="aiScanPreview"></div><button class="action-btn wide" onclick="wizGo(2)" style="margin-top:12px;">Пропусни → ръчно</button><button class="action-btn wide" onclick="wizGo(0)" style="margin-top:6px;">← Назад</button></div>`;}
        if(step===2){const nm=STATE.aiScanData?.name||'';const pr=STATE.aiScanData?.retail_price||'';let supO='<option value="">— Избери —</option>';ALL_SUPPLIERS_JSON.forEach(s=>supO+=`<option value="${s.id}">${escHTML(s.name)}</option>`);let catO='<option value="">— Избери —</option>';ALL_CATEGORIES_JSON.forEach(c=>catO+=`<option value="${c.id}">${escHTML(c.name)}</option>`);return`<div class="wizard-page active" style="padding:16px;"><div class="form-group"><label class="form-label">Наименование *</label><input type="text" class="form-control" id="wiz_name" value="${escHTML(nm)}" placeholder="напр. Nike Air Max"></div><div class="form-row"><div class="form-group"><label class="form-label">Продажна цена *</label><input type="number" step="0.01" class="form-control" id="wiz_price" value="${pr}" placeholder="0,00"></div><div class="form-group"><label class="form-label">Цена едро</label><input type="number" step="0.01" class="form-control" id="wiz_wprice" placeholder="0,00"></div></div><div class="form-group"><label class="form-label">Баркод <span class="fl-hint">(празно = автоматично)</span></label><input type="text" class="form-control" id="wiz_barcode" placeholder="Сканирай или въведи"></div><div class="form-group"><label class="form-label">Доставчик <span class="fl-add" onclick="toggleInline('wiz_inlSup')">+ Нов</span></label><select class="form-control form-select" id="wiz_sup">${supO}</select><div class="inline-add" id="wiz_inlSup"><input type="text" placeholder="Име" id="wiz_inlSupName"><button onclick="wizSaveInline('supplier')">Запази</button></div></div><div class="form-group"><label class="form-label">Категория <span class="fl-add" onclick="toggleInline('wiz_inlCat')">+ Нова</span></label><select class="form-control form-select" id="wiz_cat">${catO}</select><div class="inline-add" id="wiz_inlCat"><input type="text" placeholder="Име" id="wiz_inlCatName"><button onclick="wizSaveInline('category')">Запази</button></div></div><button class="action-btn primary wide" onclick="wizGo(3)">Напред →</button><button class="action-btn wide" onclick="wizGo(1)" style="margin-top:6px;">← Назад</button></div>`;}
        if(step===3){if(STATE.productType==='single')return`<div class="wizard-page active" style="padding:16px;"><div style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:14px;">Единичен артикул — без варианти.</div><button class="action-btn primary wide" onclick="wizGo(4)">Напред →</button><button class="action-btn wide" onclick="wizGo(2)" style="margin-top:6px;">← Назад</button></div>`;let colorsH='';COLOR_PALETTE.forEach(c=>{const sel=STATE.selectedColors.includes(c.name);colorsH+=`<div style="display:flex;flex-direction:column;align-items:center;gap:2px;width:36px;cursor:pointer;" onclick="wizToggleColor('${c.name}')"><div style="width:26px;height:26px;border-radius:50%;background:${c.hex};border:2px solid ${sel?'#fff':'transparent'};${sel?'box-shadow:0 0 0 2px var(--indigo-500),0 0 8px rgba(99,102,241,0.3);':''}"></div><div style="font-size:0.5rem;color:${sel?'var(--indigo-300)':'var(--text-secondary)'};">${c.name}</div></div>`;});let sizesH='';Object.keys(STATE.selectedSizes).forEach(s=>sizesH+=`<span style="display:inline-block;padding:4px 10px;border-radius:6px;background:rgba(99,102,241,0.15);color:var(--indigo-300);font-size:0.75rem;font-weight:600;margin:2px;cursor:pointer;" onclick="delete STATE.selectedSizes['${s}'];renderWizard();">${s} ✕</span>`);return`<div class="wizard-page active" style="padding:16px;"><div style="font-size:0.7rem;font-weight:600;color:var(--indigo-500);margin-bottom:6px;">РАЗМЕРИ</div><div style="display:flex;gap:6px;margin-bottom:8px;"><input type="text" class="form-control" id="wizSizeInput" placeholder="Добави размер..." style="font-size:0.8rem;"><button class="action-btn" onclick="wizAddSize()" style="flex-shrink:0;">+</button></div><div style="margin-bottom:14px;">${sizesH||'<span style="font-size:0.75rem;color:var(--text-secondary);">Няма избрани</span>'}</div><div style="font-size:0.7rem;font-weight:600;color:var(--indigo-500);margin-bottom:6px;">ЦВЕТОВЕ</div><div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">${colorsH}</div><button class="action-btn primary wide" onclick="wizGo(4)">Напред →</button><button class="action-btn wide" onclick="wizGo(2)" style="margin-top:6px;">← Назад</button></div>`;}
        if(step===4){let unitO='';UNITS_JSON.forEach(u=>unitO+=`<option value="${u}">${u}</option>`);return`<div class="wizard-page active" style="padding:16px;"><div class="form-group"><label class="form-label">Артикулен код <span class="fl-hint">AI генерира ако е празно</span></label><input type="text" class="form-control" id="wiz_code" placeholder="NAM90-BLK"></div><div class="form-group"><label class="form-label">Мерна единица</label><select class="form-control form-select" id="wiz_unit">${unitO}</select></div><div class="form-group"><label class="form-label">Мин. наличност</label><input type="number" class="form-control" id="wiz_minqty" value="0"></div><div class="form-group"><label class="form-label">SEO описание <span class="fl-ai">✦ AI генерира</span></label><textarea class="form-control" id="wiz_desc" rows="3" placeholder="AI ще попълни..."></textarea></div><button class="action-btn primary wide" onclick="wizGoFinal()">Напред →</button><button class="action-btn wide" onclick="wizGo(3)" style="margin-top:6px;">← Назад</button></div>`;}
        if(step===5){const name=document.getElementById('wiz_name')?.value||'Артикул';const price=parseFloat(document.getElementById('wiz_price')?.value||0);wizBuildCombs();const total=STATE.variantCombs.reduce((s,v)=>s+(v.qty||0),0);let batchH='';if(STATE.variantCombs.length<=1&&!STATE.variantCombs[0]?.size){batchH=`<div class="form-row"><div class="form-group"><label class="form-label">Начална наличност</label><input type="number" class="form-control" id="wiz_singleQty" value="0"></div><div class="form-group"><label class="form-label">Мин. наличност</label><input type="number" class="form-control" id="wiz_singleMin" value="0"></div></div>`;}else{batchH='<div style="font-size:0.65rem;color:var(--text-secondary);margin-bottom:4px;">Вариации:</div>';STATE.variantCombs.forEach((v,i)=>{batchH+=`<div style="display:flex;gap:4px;align-items:center;margin-bottom:3px;"><span style="font-size:0.75rem;flex:1;">${v.size||''} ${v.color||''}</span><input type="number" class="form-control" style="width:50px;padding:4px;text-align:center;font-size:0.8rem;" value="${v.qty||0}" onchange="STATE.variantCombs[${i}].qty=parseInt(this.value)||0"></div>`;});}return`<div class="wizard-page active" style="padding:16px;"><div style="font-size:0.9rem;font-weight:600;margin-bottom:2px;">${escHTML(name)}</div><div style="font-size:0.7rem;color:var(--text-secondary);margin-bottom:10px;">Цена: ${fmtPrice(price)} · ${STATE.variantCombs.length} вариации</div>${batchH}<button class="action-btn save wide" style="margin-top:12px;font-size:1rem;padding:14px;" onclick="wizSave()">✓ Запази</button><button class="action-btn wide" onclick="wizGo(4)" style="margin-top:6px;">← Назад</button></div>`;}
        return '';
    }

    function wizToggleColor(c){const i=STATE.selectedColors.indexOf(c);if(i>-1)STATE.selectedColors.splice(i,1);else STATE.selectedColors.push(c);renderWizard();}
    function wizAddSize(){const inp=document.getElementById('wizSizeInput');const v=inp?.value.trim();if(!v)return;STATE.selectedSizes[v]=null;renderWizard();}
    function toggleInline(id){document.getElementById(id)?.classList.toggle('open');}
    async function wizSaveInline(type){if(type==='supplier'){const n=document.getElementById('wiz_inlSupName')?.value.trim();if(!n)return;const d=await fetchJSON('products.php?ajax=add_supplier',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`name=${encodeURIComponent(n)}`});if(d?.id){ALL_SUPPLIERS_JSON.push({id:d.id,name:d.name});showToast('Добавен ✓','success');renderWizard();setTimeout(()=>{const sel=document.getElementById('wiz_sup');if(sel)sel.value=d.id;},50);}}else if(type==='category'){const n=document.getElementById('wiz_inlCatName')?.value.trim();if(!n)return;const d=await fetchJSON('products.php?ajax=add_category',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`name=${encodeURIComponent(n)}`});if(d?.id){ALL_CATEGORIES_JSON.push({id:d.id,name:d.name});showToast('Добавена ✓','success');renderWizard();setTimeout(()=>{const sel=document.getElementById('wiz_cat');if(sel)sel.value=d.id;},50);}}}
    async function wizGoFinal(){const name=document.getElementById('wiz_name')?.value.trim();if(!name){showToast('Въведи наименование','error');wizGo(2);return;}const price=parseFloat(document.getElementById('wiz_price')?.value||0);if(!price){showToast('Въведи цена','error');wizGo(2);return;}const code=document.getElementById('wiz_code')?.value;const desc=document.getElementById('wiz_desc')?.value;if(!code&&name){const d=await fetchJSON('products.php?ajax=ai_code',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name})});if(d?.code)document.getElementById('wiz_code').value=d.code;}if(!desc&&name){showToast('AI генерира описание...','info');const cat=document.getElementById('wiz_cat')?.selectedOptions[0]?.text||'';const sup=document.getElementById('wiz_sup')?.selectedOptions[0]?.text||'';const d=await fetchJSON('products.php?ajax=ai_description',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name,category:cat,supplier:sup,sizes:Object.keys(STATE.selectedSizes).join(', '),colors:STATE.selectedColors.join(', ')})});if(d?.description){document.getElementById('wiz_desc').value=d.description;showToast('Описание ✓','success');}}wizGo(5);}
    function wizBuildCombs(){const sizes=Object.keys(STATE.selectedSizes);const colors=STATE.selectedColors;STATE.variantCombs=[];if(STATE.productType==='single'||(!sizes.length&&!colors.length)){STATE.variantCombs=[{size:null,color:null,qty:0}];}else if(sizes.length&&colors.length){for(const c of colors)for(const s of sizes)STATE.variantCombs.push({size:s,color:c,qty:0});}else if(sizes.length){STATE.variantCombs=sizes.map(s=>({size:s,color:null,qty:0}));}else{STATE.variantCombs=colors.map(c=>({size:null,color:c,qty:0}));}}
    async function wizSave(){const name=document.getElementById('wiz_name')?.value.trim();if(!name){showToast('Въведи наименование','error');return;}showToast('Запазвам...','info');const payload={name,barcode:document.getElementById('wiz_barcode')?.value||'',retail_price:parseFloat(document.getElementById('wiz_price')?.value)||0,wholesale_price:parseFloat(document.getElementById('wiz_wprice')?.value)||0,cost_price:0,supplier_id:document.getElementById('wiz_sup')?.value||null,category_id:document.getElementById('wiz_cat')?.value||null,code:document.getElementById('wiz_code')?.value||'',unit:document.getElementById('wiz_unit')?.value||'бр',min_quantity:parseInt(document.getElementById('wiz_minqty')?.value)||0,description:document.getElementById('wiz_desc')?.value||'',product_type:STATE.productType||'simple',sizes:Object.keys(STATE.selectedSizes),colors:STATE.selectedColors,variants:STATE.variantCombs};try{const r=await fetch('product-save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});const res=await r.json();if(res.success||res.id){showToast('Артикулът е добавен ✓','success');closeAddModal();loadCurrentScreen();}else showToast(res.error||'Грешка','error');}catch(e){showToast('Мрежова грешка','error');}}

    // AI Scan handler
    async function handleAIScanFile(input){if(!input.files?.[0])return;showToast('AI анализира...','info');const reader=new FileReader();reader.onload=async e=>{const base64=e.target.result.split(',')[1];const d=await fetchJSON('products.php?ajax=ai_scan',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({image:base64})});if(d&&!d.error){STATE.aiScanData=d;showToast('AI разпозна ✓','success');if(d.sizes?.length){d.sizes.forEach(s=>{STATE.selectedSizes[s]=null;});if(!STATE.productType)STATE.productType='variant';}if(d.colors?.length)STATE.selectedColors=d.colors;wizGo(2);}else{showToast('AI не разпозна — попълни ръчно','error');wizGo(2);}};reader.readAsDataURL(input.files[0]);}

    // Edit
    async function editProduct(id){closeDrawer('detail');const d=await fetchJSON(`products.php?ajax=product_detail&id=${id}`);if(!d||d.error)return;const p=d.product;STATE.editProductId=id;STATE.productType='single';STATE.aiScanData={name:p.name,retail_price:p.retail_price,code:p.code,description:p.description};document.getElementById('modalTitle').textContent='Редактирай';STATE.wizStep=2;renderWizard();document.getElementById('addModal').classList.add('open');document.body.style.overflow='hidden';setTimeout(()=>{const bc=document.getElementById('wiz_barcode');if(bc)bc.value=p.barcode||'';const sel=document.getElementById('wiz_sup');if(sel)sel.value=p.supplier_id||'';const cat=document.getElementById('wiz_cat');if(cat)cat.value=p.category_id||'';},50);}

    // ============================================================
    // CAMERA & BARCODE
    // ============================================================
    async function openCamera(mode){STATE.cameraMode=mode;document.getElementById('cameraOverlay').classList.add('open');try{const stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment',width:{ideal:1280}}});STATE.cameraStream=stream;document.getElementById('cameraVideo').srcObject=stream;if(mode==='scan'){document.getElementById('scanLine').style.display='block';document.getElementById('captureBtn').style.display='none';startBarcodeScanning(document.getElementById('cameraVideo'));}else{document.getElementById('scanLine').style.display='none';document.getElementById('captureBtn').style.display='';}}catch(e){showToast('Камерата не е достъпна','error');closeCamera();}}
    function closeCamera(){document.getElementById('cameraOverlay').classList.remove('open');if(STATE.cameraStream){STATE.cameraStream.getTracks().forEach(t=>t.stop());STATE.cameraStream=null;}if(STATE.barcodeInterval){clearInterval(STATE.barcodeInterval);STATE.barcodeInterval=null;}document.getElementById('scanLine').style.display='none';}
    function startBarcodeScanning(vid){if(!('BarcodeDetector' in window)){showToast('Баркод скенерът не е поддържан','error');closeCamera();return;}STATE.barcodeDetector=new BarcodeDetector({formats:['ean_13','ean_8','code_128','code_39','qr_code','upc_a','upc_e']});STATE.barcodeInterval=setInterval(async()=>{try{const bc=await STATE.barcodeDetector.detect(vid);if(bc.length>0){clearInterval(STATE.barcodeInterval);playBeep();showGreenFlash();const code=bc[0].rawValue;const d=await fetchJSON(`products.php?ajax=barcode&code=${encodeURIComponent(code)}&store_id=${STATE.storeId}`);closeCamera();if(d&&!d.error)openProductDetail(d.id);else showToast(`Баркод ${code} не е намерен`,'info');}}catch(e){}},300);}
    function capturePhoto(){document.getElementById('aiScanFileInput').click();closeCamera();}
    function playBeep(){try{const c=new(window.AudioContext||window.webkitAudioContext)();const o=c.createOscillator();const g=c.createGain();o.connect(g);g.connect(c.destination);o.frequency.value=1200;g.gain.value=0.3;o.start();o.stop(c.currentTime+0.15);}catch(e){}}
    function showGreenFlash(){const f=document.createElement('div');f.className='green-flash';document.body.appendChild(f);setTimeout(()=>f.remove(),500);}

    // ============================================================
    // LOAD
    // ============================================================
    function loadCurrentScreen(){switch(STATE.screen){case'home':loadHomeScreen();break;case'suppliers':loadSuppliers();break;case'categories':loadCategories();break;case'products':loadProductsList();break;}}
    document.addEventListener('click',e=>{if(!e.target.closest('[onclick*="toggleSort"]')&&!e.target.closest('.sort-dropdown'))document.getElementById('sortDropdown')?.classList.remove('open');});
    document.addEventListener('DOMContentLoaded',loadCurrentScreen);
    </script>
</body>
</html>
