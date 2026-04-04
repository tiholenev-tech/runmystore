<?php
/**
 * products.php — RunMyStore.ai
 * 4-екранен модул: Начало | Доставчици | Категории | Артикули
 * Cruip Open Pro Dark тема, AI-first, voice-first
 * Сесия 18 — 04.04.2026
 * Промени С18: 6-стъпков Add wizard, 4 големи бутона Home, AI in-module, glow, info overlay
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

// Ролеви достъп
$is_owner   = ($user_role === 'owner');
$is_manager = ($user_role === 'manager');
$can_add    = ($is_owner || $is_manager);
$can_see_cost = $is_owner;
$can_see_margin = $is_owner;

// Текущ екран
$screen = $_GET['screen'] ?? 'home';
$sup_id = isset($_GET['sup']) ? (int)$_GET['sup'] : null;
$cat_id = isset($_GET['cat']) ? (int)$_GET['cat'] : null;
$filter = $_GET['filter'] ?? null;

// ============================================================
// AJAX ENDPOINTS (unchanged from S17 + new S18 endpoints)
// ============================================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    // --- Търсене на артикули ---
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

    // --- Баркод търсене ---
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

    // --- Детайли на артикул + наличност по обекти ---
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

    // --- Статистики за начален екран ---
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

    // --- Доставчици списък ---
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

    // --- Категории ---
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

    // --- Артикули списък ---
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

    // --- AI анализ ---
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

    // --- AI Credits ---
    if ($_GET['ajax'] === 'ai_credits') {
        $credits = DB::run("SELECT ai_credits_bg, ai_credits_tryon FROM tenants WHERE id = ?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
        echo json_encode($credits); exit;
    }

    // --- S18 NEW: Търсене размери от БД ---
    if ($_GET['ajax'] === 'search_sizes') {
        $q = trim($_GET['q'] ?? '');
        $rows = DB::run("SELECT DISTINCT size FROM products WHERE tenant_id=? AND size IS NOT NULL AND size!='' AND size LIKE ? ORDER BY size LIMIT 50", [$tenant_id, "%$q%"])->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($rows); exit;
    }

    // --- S18 NEW: Търсене цветове от БД ---
    if ($_GET['ajax'] === 'search_colors') {
        $q = trim($_GET['q'] ?? '');
        $rows = DB::run("SELECT DISTINCT color FROM products WHERE tenant_id=? AND color IS NOT NULL AND color!='' AND color LIKE ? ORDER BY color LIMIT 50", [$tenant_id, "%$q%"])->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($rows); exit;
    }

    // --- S18 NEW: Добави доставчик inline ---
    if ($_GET['ajax'] === 'add_supplier') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) { echo json_encode(['error'=>'Въведи име']); exit; }
        $exists = DB::run("SELECT id FROM suppliers WHERE tenant_id=? AND name=?", [$tenant_id, $name])->fetch();
        if ($exists) { echo json_encode(['id'=>$exists['id'], 'name'=>$name]); exit; }
        DB::run("INSERT INTO suppliers (tenant_id, name, is_active) VALUES (?,?,1)", [$tenant_id, $name]);
        echo json_encode(['id'=>DB::get()->lastInsertId(), 'name'=>$name]); exit;
    }

    // --- S18 NEW: Добави категория inline ---
    if ($_GET['ajax'] === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) { echo json_encode(['error'=>'Въведи име']); exit; }
        $exists = DB::run("SELECT id FROM categories WHERE tenant_id=? AND name=?", [$tenant_id, $name])->fetch();
        if ($exists) { echo json_encode(['id'=>$exists['id'], 'name'=>$name]); exit; }
        DB::run("INSERT INTO categories (tenant_id, name) VALUES (?,?)", [$tenant_id, $name]);
        echo json_encode(['id'=>DB::get()->lastInsertId(), 'name'=>$name]); exit;
    }

    // --- S18 NEW: Добави мерна единица ---
    if ($_GET['ajax'] === 'add_unit') {
        $unit = trim($_POST['unit'] ?? '');
        if (!$unit) { echo json_encode(['error'=>'Въведи единица']); exit; }
        $tenant = DB::run("SELECT units_config FROM tenants WHERE id=?", [$tenant_id])->fetch();
        $units = json_decode($tenant['units_config'] ?? '[]', true) ?: [];
        if (!in_array($unit, $units)) { $units[] = $unit; DB::run("UPDATE tenants SET units_config=? WHERE id=?", [json_encode($units, JSON_UNESCAPED_UNICODE), $tenant_id]); }
        echo json_encode(['units'=>$units, 'added'=>$unit]); exit;
    }

    // --- S18 NEW: AI Scan (Gemini camera) ---
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

    // --- S18 NEW: AI SEO описание ---
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

    // --- S18 NEW: AI Voice Fill ---
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

    // --- S18 NEW: AI генериране артикулен код ---
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

    // --- S18 NEW: AI асистент в модула ---
    if ($_GET['ajax'] === 'ai_assist' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $question = $input['question'] ?? '';
        if (!$question) { echo json_encode(['error'=>'no_question']); exit; }

        // Контекст за артикули
        $stats = DB::run("SELECT COUNT(*) AS cnt, COALESCE(SUM(i.quantity),0) AS total_qty FROM products p LEFT JOIN inventory i ON i.product_id=p.id WHERE p.tenant_id=? AND p.is_active=1", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
        $low = DB::run("SELECT COUNT(*) AS cnt FROM products p JOIN inventory i ON i.product_id=p.id WHERE p.tenant_id=? AND p.min_quantity>0 AND i.quantity<=p.min_quantity AND i.quantity>0", [$tenant_id])->fetchColumn();
        $out = DB::run("SELECT COUNT(*) AS cnt FROM products p JOIN inventory i ON i.product_id=p.id WHERE p.tenant_id=? AND i.quantity=0", [$tenant_id])->fetchColumn();

        $ctx = "Контекст: {$stats['cnt']} артикула, {$stats['total_qty']} бройки общо, {$low} с ниска наличност, {$out} изчерпани.";
        $prompt = "Ти си AI асистент за управление на артикули в магазин. {$ctx}\n\nВъпрос: {$question}\n\nОтговори кратко (2-3 изречения), конкретно, с числа. Без технически термини.";

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
        $payload = ['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.5,'maxOutputTokens'=>300]];
        $ch = curl_init($api_url); curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
        $resp = curl_exec($ch); curl_close($ch);
        $data = json_decode($resp, true);
        $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? 'Не успях да отговоря.');
        echo json_encode(['response'=>$text]); exit;
    }

    echo json_encode(['error' => 'unknown_action']); exit;
}

// ============================================================
// Зареждане на данни (unchanged from S17)
// ============================================================
$stores = DB::run("
    SELECT s.id, s.name FROM stores s
    WHERE s.company_id = (SELECT company_id FROM stores WHERE id = ?)
    ORDER BY s.name
", [$store_id])->fetchAll(PDO::FETCH_ASSOC);

$current_store = null;
foreach ($stores as $st) {
    if ($st['id'] == $store_id) { $current_store = $st; break; }
}

$all_suppliers = DB::run("SELECT id, name FROM suppliers WHERE tenant_id = ? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$all_categories = DB::run("SELECT id, name, parent_id FROM categories WHERE tenant_id = ? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);

// S18: Мерни единици + AI кредити
$tenant_cfg = DB::run("SELECT units_config, ai_credits_bg, ai_credits_tryon FROM tenants WHERE id=?", [$tenant_id])->fetch();
$onboarding_units = json_decode($tenant_cfg['units_config'] ?? '[]', true) ?: ['бр','чифт','к-кт'];
$ai_bg = (int)($tenant_cfg['ai_credits_bg'] ?? 0);
$ai_tryon = (int)($tenant_cfg['ai_credits_tryon'] ?? 0);

// S18: Цветова палитра
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
        /* ====== CRUIP DARK — UNCHANGED FROM S17 ====== */
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
        .main-wrap { position: relative; z-index: 1; padding-bottom: 140px; padding-top: 8px; }

        /* Header — S18: removed add button */
        .top-header { position: sticky; top: 0; z-index: 50; padding: 8px 16px; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); background: rgba(11, 15, 26, 0.8); border-bottom: 1px solid var(--border-subtle); }
        .top-header h1 { font-family: Nacelle, Inter, sans-serif; font-size: 1.25rem; font-weight: 700; margin: 0; background: linear-gradient(to right, var(--text-primary), var(--indigo-200), #f9fafb, var(--indigo-300), var(--text-primary)); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; animation: gradient 6s linear infinite; }
        @keyframes gradient { 0% { background-position: 0% center; } 100% { background-position: 200% center; } }
        .header-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .store-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 999px; background: rgba(99,102,241,0.15); color: var(--indigo-300); border: 1px solid var(--border-subtle); white-space: nowrap; }

        /* Search — S18: only mic + filter, no scanner, no add */
        .search-wrap { margin: 8px 16px 0; position: relative; }
        .search-input { width: 100%; padding: 10px 64px 10px 40px; border-radius: 12px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.9rem; outline: none; transition: all 0.3s; }
        .search-input:focus { border-color: var(--border-glow); box-shadow: 0 0 20px rgba(99,102,241,0.15); }
        .search-input::placeholder { color: var(--text-secondary); }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); pointer-events: none; }
        .search-actions { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); display: flex; gap: 2px; }
        .search-btn { width: 36px; height: 36px; border-radius: 8px; border: none; background: transparent; color: var(--indigo-300); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .search-btn:active { background: rgba(99,102,241,0.2); transform: scale(0.9); }
        .search-btn.active { background: rgba(99,102,241,0.25); color: #fff; }

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

        /* ====== S18 NEW: 4 Big Action Buttons ====== */
        .home-actions { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; padding: 14px 16px 0; position: relative; }
        .home-action-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 18px 8px; border-radius: 14px; background: var(--bg-card); border: 1px solid var(--border-subtle); cursor: pointer; transition: all 0.25s; position: relative; overflow: hidden; }
        .home-action-btn::before { content: ''; position: absolute; inset: 0; border-radius: inherit; border: 1px solid transparent; background: linear-gradient(135deg, rgba(99,102,241,0.15), transparent) border-box; mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0); mask-composite: exclude; -webkit-mask-composite: xor; pointer-events: none; }
        .home-action-btn:active { transform: scale(0.95); background: var(--bg-card-hover); }
        .home-action-btn .ha-icon { font-size: 1.6rem; filter: drop-shadow(0 0 10px rgba(99,102,241,0.6)); }
        .home-action-btn .ha-label { font-size: 0.7rem; font-weight: 600; color: var(--indigo-300); }
        .home-action-btn.ai-btn-glow { box-shadow: 0 0 24px rgba(99,102,241,0.15); }
        .home-action-btn.ai-btn-glow .ha-icon { position: relative; }
        .home-info-btn { position: absolute; top: 14px; right: 16px; width: 32px; height: 32px; border-radius: 50%; background: rgba(99,102,241,0.08); border: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-secondary); font-size: 0.8rem; font-weight: 700; transition: all 0.2s; z-index: 2; }
        .home-info-btn:active { background: rgba(99,102,241,0.2); }

        /* AI waves animation for AI button */
        .ai-waves-wrap { position: relative; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; }
        .ai-waves-wrap::before, .ai-waves-wrap::after { content: ''; position: absolute; inset: -4px; border-radius: 50%; border: 1.5px solid var(--indigo-500); animation: ai-wave-ring 2.5s ease-in-out infinite; opacity: 0; }
        .ai-waves-wrap::after { inset: -10px; border-width: 1px; border-color: var(--indigo-400); animation-delay: 0.6s; }
        @keyframes ai-wave-ring { 0% { opacity: 0.6; transform: scale(0.85); } 100% { opacity: 0; transform: scale(1.3); } }
        .ai-waves-inner { width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, var(--indigo-500), #4f46e5); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: #fff; box-shadow: 0 0 20px rgba(99,102,241,0.4); }

        /* SVG Scan icon */
        .scan-icon-svg { filter: drop-shadow(0 0 8px rgba(99,102,241,0.5)); }

        /* ====== S18 NEW: Info overlay ====== */
        .info-overlay { position: fixed; inset: 0; z-index: 500; background: rgba(11,15,26,0.75); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); display: none; align-items: center; justify-content: center; padding: 20px; }
        .info-overlay.open { display: flex; }
        .info-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 18px; max-width: 340px; width: 100%; padding: 24px 20px; max-height: 80vh; overflow-y: auto; box-shadow: 0 8px 40px rgba(99,102,241,0.2); }
        .info-card h3 { font-family: Nacelle, Inter, sans-serif; font-size: 1.1rem; font-weight: 700; margin: 0 0 12px; color: var(--indigo-300); }
        .info-item { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 14px; }
        .info-item .ii-icon { font-size: 1.2rem; flex-shrink: 0; filter: drop-shadow(0 0 6px rgba(99,102,241,0.4)); margin-top: 2px; }
        .info-item .ii-title { font-size: 0.85rem; font-weight: 600; color: var(--text-primary); margin-bottom: 2px; }
        .info-item .ii-desc { font-size: 0.75rem; color: var(--text-secondary); line-height: 1.5; }
        .info-close { width: 100%; padding: 10px; border-radius: 10px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.85rem; cursor: pointer; margin-top: 8px; }

        /* ====== S18 NEW: Voice overlay (blur, not fullscreen) ====== */
        .voice-overlay { position: fixed; inset: 0; z-index: 400; background: rgba(11,15,26,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); display: none; flex-direction: column; align-items: center; justify-content: center; }
        .voice-overlay.open { display: flex; }
        .voice-waves svg { margin-bottom: 14px; }
        .voice-title { font-size: 1rem; color: var(--text-primary); font-weight: 600; margin-bottom: 4px; }
        .voice-sub { font-size: 0.75rem; color: var(--indigo-300); margin-bottom: 16px; }
        .voice-transcript { background: rgba(99,102,241,0.06); border: 1px solid rgba(99,102,241,0.12); border-radius: 12px; padding: 10px 16px; max-width: 280px; min-height: 36px; font-size: 0.85rem; color: var(--text-primary); text-align: center; margin-bottom: 16px; }
        .voice-hint { font-size: 0.7rem; color: var(--text-secondary); }

        /* ====== S18 NEW: AI Assist Drawer (in-module) ====== */
        .ai-assist-input { display: flex; gap: 6px; margin-top: 12px; }
        .ai-assist-input input { flex: 1; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.85rem; outline: none; }
        .ai-assist-input button { padding: 10px 16px; border-radius: 10px; border: none; background: linear-gradient(135deg, var(--indigo-500), #4f46e5); color: #fff; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
        .ai-response { padding: 12px; border-radius: 10px; background: rgba(99,102,241,0.06); border: 1px solid var(--border-subtle); margin-top: 10px; font-size: 0.85rem; line-height: 1.6; color: var(--text-primary); }

        /* Collapse sections — S17 unchanged + S18 glow on icons */
        .collapse-section { margin: 10px 16px 0; border-radius: 12px; background: var(--bg-card); border: 1px solid var(--border-subtle); overflow: hidden; box-shadow: 0 4px 20px rgba(99,102,241,0.06); }
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

        /* Product cards — S17 unchanged + S18 hover glow */
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

        /* Supplier cards, Category cards, Breadcrumb — ALL UNCHANGED from S17 */
        .swipe-container { padding: 12px 16px; overflow-x: auto; display: flex; gap: 12px; scroll-snap-type: x mandatory; scrollbar-width: none; }
        .swipe-container::-webkit-scrollbar { display: none; }
        .supplier-card { min-width: 260px; max-width: 300px; flex-shrink: 0; scroll-snap-align: start; border-radius: 14px; padding: 16px; background: var(--bg-card); border: 1px solid var(--border-subtle); position: relative; overflow: hidden; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 20px rgba(99,102,241,0.06); }
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

        /* Screen nav, Bottom nav, FAB — UNCHANGED from S17 */
        .screen-nav { position: fixed; bottom: 68px; left: 0; right: 0; z-index: 40; display: flex; justify-content: center; padding: 0 12px; }
        .screen-nav-inner { display: flex; gap: 4px; background: rgba(11, 15, 26, 0.9); backdrop-filter: blur(12px); border-radius: 14px; padding: 4px; border: 1px solid var(--border-subtle); box-shadow: 0 -4px 24px rgba(0,0,0,0.3); width: 100%; max-width: 400px; }
        .snav-btn { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 8px 4px; border-radius: 10px; border: none; background: transparent; color: var(--text-secondary); font-size: 0.6rem; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
        .snav-btn .snav-icon { font-size: 1.1rem; filter: drop-shadow(0 0 6px rgba(99,102,241,0.3)); }
        .snav-btn.active { background: linear-gradient(135deg, rgba(99,102,241,0.25), rgba(79,70,229,0.15)); color: #fff; box-shadow: 0 0 12px rgba(99,102,241,0.2); }
        .snav-btn:active { transform: scale(0.95); }
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 45; background: rgba(11, 15, 26, 0.95); backdrop-filter: blur(12px); border-top: 1px solid var(--border-subtle); display: flex; height: 56px; }
        .bnav-tab { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; font-size: 0.6rem; color: var(--text-secondary); text-decoration: none; transition: color 0.2s; }
        .bnav-tab.active { color: var(--indigo-400); text-shadow: 0 0 10px rgba(99,102,241,0.6); }
        .bnav-tab .bnav-icon { font-size: 1.2rem; }

        /* Drawers — UNCHANGED from S17 */
        .drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 100; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .drawer-overlay.open { opacity: 1; pointer-events: auto; }
        .drawer { position: fixed; bottom: 0; left: 0; right: 0; z-index: 101; background: var(--bg-main); border-radius: 20px 20px 0 0; transform: translateY(100%); transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1); max-height: 90vh; display: flex; flex-direction: column; }
        .drawer.open { transform: translateY(0); }
        .drawer-handle { width: 36px; height: 4px; border-radius: 2px; background: rgba(255,255,255,0.2); margin: 10px auto 0; flex-shrink: 0; }
        .drawer-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px 10px; border-bottom: 1px solid var(--border-subtle); flex-shrink: 0; }
        .drawer-header h3 { font-family: Nacelle, Inter, sans-serif; font-size: 1.05rem; font-weight: 700; margin: 0; }
        .drawer-close { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .drawer-body { flex: 1; overflow-y: auto; padding: 14px 16px 24px; -webkit-overflow-scrolling: touch; }

        /* Modal, wizard, forms — UNCHANGED structure, S18 wizard content changed in JS */
        .modal-overlay { position: fixed; inset: 0; background: var(--bg-main); z-index: 200; opacity: 0; pointer-events: none; transition: opacity 0.3s; display: flex; flex-direction: column; }
        .modal-overlay.open { opacity: 1; pointer-events: auto; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-subtle); flex-shrink: 0; }
        .modal-header h2 { font-family: Nacelle, Inter, sans-serif; font-size: 1.1rem; font-weight: 700; margin: 0; }
        .modal-body { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; }
        .wizard-steps { display: flex; align-items: center; justify-content: center; gap: 4px; padding: 12px 16px; }
        .wiz-step { height: 3px; border-radius: 2px; background: var(--border-subtle); transition: all 0.3s; flex: 1; }
        .wiz-step.active { background: linear-gradient(to right, var(--indigo-500), #8b5cf6); box-shadow: 0 0 6px rgba(99,102,241,0.3); }
        .wiz-step.done { background: var(--indigo-400); }
        .wiz-step-label { font-size: 0.65rem; color: var(--text-secondary); text-align: center; padding: 0 16px 4px; }
        .wiz-step-label span { color: var(--indigo-300); }
        .wizard-page { display: none; padding: 16px; }
        .wizard-page.active { display: block; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.3px; }
        .form-label .fl-hint { color: rgba(107,114,128,0.8); font-weight: 400; text-transform: none; letter-spacing: 0; }
        .form-label .fl-ai { color: var(--indigo-500); }
        .form-label .fl-right { float: right; color: var(--indigo-500); cursor: pointer; font-weight: 600; text-transform: none; letter-spacing: 0; }
        .form-control { width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.9rem; outline: none; transition: all 0.3s; font-family: Inter, system-ui, sans-serif; }
        .form-control:focus { border-color: var(--border-glow); box-shadow: 0 0 16px rgba(99,102,241,0.12); }
        .form-control::placeholder { color: rgba(107,114,128,0.6); }
        .form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23818cf8'%3E%3Cpath d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; }
        .inline-add { background: rgba(99,102,241,0.04); border: 1px solid rgba(99,102,241,0.12); border-radius: 8px; padding: 8px; margin-top: 4px; display: none; gap: 6px; align-items: center; }
        .inline-add.open { display: flex; }
        .inline-add input { flex: 1; padding: 7px 10px; border-radius: 6px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.8rem; outline: none; }
        .inline-add button { padding: 7px 12px; border-radius: 6px; border: none; background: var(--indigo-500); color: #fff; font-size: 0.75rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .action-btn { padding: 10px; border-radius: 10px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s; font-family: Inter, system-ui, sans-serif; }
        .action-btn:active { background: var(--bg-card-hover); transform: scale(0.97); }
        .action-btn.primary { background: linear-gradient(135deg, var(--indigo-500), #4f46e5); border-color: transparent; color: #fff; box-shadow: 0 4px 14px rgba(99,102,241,0.25); }
        .action-btn.danger { border-color: rgba(239,68,68,0.3); color: #ef4444; }
        .action-btn.wide { width: 100%; }
        .action-btn.success { background: linear-gradient(to bottom, #22c55e, #16a34a); border-color: transparent; color: #fff; box-shadow: 0 4px 16px rgba(34,197,94,0.25); }
        .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }

        /* Sort, filter, pagination, toast, skeleton, empty — UNCHANGED from S17 */
        .sort-dropdown { position: absolute; top: 100%; right: 0; margin-top: 4px; background: var(--bg-main); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 4px; min-width: 180px; z-index: 60; display: none; box-shadow: 0 8px 32px rgba(0,0,0,0.4); }
        .sort-dropdown.open { display: block; }
        .sort-option { padding: 8px 12px; border-radius: 8px; font-size: 0.8rem; cursor: pointer; color: var(--text-secondary); transition: all 0.2s; }
        .sort-option:active, .sort-option.active { background: rgba(99,102,241,0.12); color: var(--indigo-300); }
        .filter-section { margin-bottom: 16px; }
        .filter-section .fs-title { font-size: 0.8rem; font-weight: 600; margin-bottom: 8px; color: var(--indigo-300); }
        .filter-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .filter-chip { padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .filter-chip.selected { background: rgba(99,102,241,0.2); color: var(--indigo-300); border-color: var(--border-glow); }
        .price-range { display: flex; align-items: center; gap: 8px; }
        .price-range input { width: 100px; }
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
        .stats-section h4 { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .toast-container { position: fixed; top: 16px; left: 16px; right: 16px; z-index: 500; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
        .toast { padding: 12px 16px; border-radius: 10px; background: rgba(17, 24, 44, 0.95); border: 1px solid var(--border-subtle); backdrop-filter: blur(8px); color: var(--text-primary); font-size: 0.8rem; transform: translateY(-20px); opacity: 0; transition: all 0.3s; pointer-events: auto; display: flex; align-items: center; gap: 8px; }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.success { border-color: rgba(34,197,94,0.4); } .toast.error { border-color: rgba(239,68,68,0.4); } .toast.info { border-color: rgba(99,102,241,0.4); }
        .skeleton { background: linear-gradient(90deg, var(--bg-card) 25%, rgba(99,102,241,0.08) 50%, var(--bg-card) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 8px; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
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
        .camera-overlay { position: fixed; inset: 0; z-index: 300; background: #000; display: none; flex-direction: column; }
        .camera-overlay.open { display: flex; }
        .camera-video { flex: 1; object-fit: cover; }
        .camera-controls { padding: 16px; display: flex; justify-content: center; gap: 16px; background: rgba(0,0,0,0.8); }
        .camera-btn { width: 56px; height: 56px; border-radius: 50%; border: 3px solid #fff; background: transparent; color: #fff; font-size: 1.2rem; cursor: pointer; }
        .camera-btn.capture { background: #fff; color: #000; } .camera-btn.close-cam { border-color: #ef4444; color: #ef4444; }
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

    <div class="main-wrap">
        <!-- ====== HEADER (S18: removed scanner + add button from here) ====== -->
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

        <!-- ====== SEARCH (S18: only mic + filter) ====== -->
        <div class="search-wrap">
            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Търси артикул, код, баркод..." autocomplete="off">
            <div class="search-actions">
                <button class="search-btn" id="voiceBtn" onclick="toggleVoiceSearch()" title="Диктувай">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
                </button>
                <button class="search-btn" id="filterBtn" onclick="openFilterDrawer()" title="Филтри">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                </button>
            </div>
        </div>

        <!-- ====== SCREEN: HOME ====== -->
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

            <!-- ====== S18 NEW: 4 Big Action Buttons ====== -->
            <?php if ($can_add): ?>
            <div class="home-actions">
                <!-- AI Assistant (in-module, with waves) -->
                <div class="home-action-btn ai-btn-glow" onclick="openAIAssist()">
                    <div class="ai-waves-wrap">
                        <div class="ai-waves-inner">✦</div>
                    </div>
                    <div class="ha-label">AI Помощник</div>
                </div>
                <!-- Scan (SVG crosshair viewfinder) -->
                <div class="home-action-btn" onclick="openCamera('scan')">
                    <div class="ha-icon">
                        <svg width="36" height="36" viewBox="0 0 36 36" fill="none" class="scan-icon-svg">
                            <path d="M4 10V6a2 2 0 012-2h4" stroke="var(--indigo-400)" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M26 4h4a2 2 0 012 2v4" stroke="var(--indigo-400)" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M32 26v4a2 2 0 01-2 2h-4" stroke="var(--indigo-400)" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M10 32H6a2 2 0 01-2-2v-4" stroke="var(--indigo-400)" stroke-width="1.8" stroke-linecap="round"/>
                            <line x1="8" y1="18" x2="28" y2="18" stroke="var(--indigo-500)" stroke-width="1.5" stroke-dasharray="3 2" opacity="0.6"/>
                            <circle cx="18" cy="18" r="3" stroke="var(--indigo-400)" stroke-width="1.2" fill="none" opacity="0.5"/>
                            <circle cx="18" cy="18" r="1" fill="var(--indigo-400)" opacity="0.8"/>
                        </svg>
                    </div>
                    <div class="ha-label">Сканирай</div>
                </div>
                <!-- Add product -->
                <div class="home-action-btn" onclick="openAddModal()">
                    <div class="ha-icon" style="font-size:1.8rem; filter: drop-shadow(0 0 10px rgba(34,197,94,0.5)); color: #22c55e;">＋</div>
                    <div class="ha-label" style="color:#22c55e;">Добави</div>
                </div>
                <!-- Info button (absolute positioned) -->
                <div class="home-info-btn" onclick="openInfoOverlay()">ℹ</div>
            </div>
            <?php endif; ?>

            <!-- Collapse sections — UNCHANGED from S17 -->
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

        <!-- ====== SCREEN: SUPPLIERS (UNCHANGED from S17) ====== -->
        <section id="screenSuppliers" class="screen-section" style="display:<?= $screen === 'suppliers' ? 'block' : 'none' ?>;">
            <div class="section-title">Доставчици</div>
            <div class="swipe-container" id="supplierCards"></div>
            <div class="indigo-line"></div>
            <div class="stats-section"><h4>Статистики доставчици</h4><div id="supplierStats"></div></div>
        </section>

        <!-- ====== SCREEN: CATEGORIES (UNCHANGED from S17) ====== -->
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

        <!-- ====== SCREEN: PRODUCTS (UNCHANGED from S17) ====== -->
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
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5h10"/><path d="M11 9h7"/><path d="M11 13h4"/><path d="m3 17 3 3 3-3"/><path d="M6 18V4"/></svg>
                    Сортирай
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
    </div>

    <!-- Screen nav + Bottom nav — UNCHANGED from S17 -->
    <div class="screen-nav"><div class="screen-nav-inner">
        <button class="snav-btn <?= $screen === 'home' ? 'active' : '' ?>" onclick="goScreen('home')"><span class="snav-icon">🏠</span><span>Начало</span></button>
        <button class="snav-btn <?= $screen === 'suppliers' ? 'active' : '' ?>" onclick="goScreen('suppliers')"><span class="snav-icon">📦</span><span>Доставчици</span></button>
        <button class="snav-btn <?= ($screen === 'categories') ? 'active' : '' ?>" onclick="goScreen('categories')"><span class="snav-icon">🏷</span><span>Категории</span></button>
        <button class="snav-btn <?= $screen === 'products' ? 'active' : '' ?>" onclick="goScreen('products')"><span class="snav-icon">📋</span><span>Артикули</span></button>
    </div></div>
    <nav class="bottom-nav">
        <a href="chat.php" class="bnav-tab"><span class="bnav-icon">✦</span>AI</a>
        <a href="warehouse.php" class="bnav-tab active"><span class="bnav-icon">📦</span>Склад</a>
        <a href="stats.php" class="bnav-tab"><span class="bnav-icon">📊</span>Справки</a>
        <a href="sale.php" class="bnav-tab"><span class="bnav-icon">⚡</span>Въвеждане</a>
    </nav>

// ============================================================
// Зареждане на данни (unchanged from S17)
// ============================================================
$stores = DB::run("
    SELECT s.id, s.name FROM stores s
    WHERE s.company_id = (SELECT company_id FROM stores WHERE id = ?)
    ORDER BY s.name
", [$store_id])->fetchAll(PDO::FETCH_ASSOC);

$current_store = null;
foreach ($stores as $st) {
    if ($st['id'] == $store_id) { $current_store = $st; break; }
}

$all_suppliers = DB::run("SELECT id, name FROM suppliers WHERE tenant_id = ? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$all_categories = DB::run("SELECT id, name, parent_id FROM categories WHERE tenant_id = ? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);

$supplier_name = '';
$category_name = '';
if ($sup_id) { $supplier_name = DB::run("SELECT name FROM suppliers WHERE id = ? AND tenant_id = ?", [$sup_id, $tenant_id])->fetchColumn() ?: ''; }
if ($cat_id) { $category_name = DB::run("SELECT name FROM categories WHERE id = ? AND tenant_id = ?", [$cat_id, $tenant_id])->fetchColumn() ?: ''; }

$cross_link = null;
if ($sup_id && $cat_id && $screen === 'products') {
    $cl = DB::run("SELECT COUNT(DISTINCT p.id) AS cnt, COUNT(DISTINCT p.supplier_id) AS sup_cnt FROM products p WHERE p.category_id = ? AND p.tenant_id = ? AND p.is_active = 1", [$cat_id, $tenant_id])->fetch(PDO::FETCH_ASSOC);
    if ($cl['sup_cnt'] > 1) { $cross_link = ['count' => $cl['cnt'], 'suppliers' => $cl['sup_cnt'], 'cat_name' => $category_name]; }
}

// S18: Мерни единици и AI кредити
$tenant_cfg = DB::run("SELECT units_config, ai_credits_bg, ai_credits_tryon FROM tenants WHERE id=?", [$tenant_id])->fetch();
$onboarding_units = json_decode($tenant_cfg['units_config'] ?? '[]', true) ?: ['бр','чифт','к-кт'];
$ai_bg = (int)($tenant_cfg['ai_credits_bg'] ?? 0);
$ai_tryon = (int)($tenant_cfg['ai_credits_tryon'] ?? 0);

// S18: Цветова палитра
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
        /* ====== S17 CRUIP DARK STYLES (unchanged) ====== */
        :root {
            --bg-main: #0b0f1a;
            --bg-card: rgba(17, 24, 44, 0.85);
            --bg-card-hover: rgba(23, 32, 58, 0.95);
            --border-subtle: rgba(99, 102, 241, 0.15);
            --border-glow: rgba(99, 102, 241, 0.4);
            --indigo-500: #6366f1;
            --indigo-400: #818cf8;
            --indigo-300: #a5b4fc;
            --indigo-200: #c7d2fe;
            --text-primary: #e5e7eb;
            --text-secondary: rgba(165, 180, 252, 0.65);
            --green-glow: rgba(34, 197, 94, 0.6);
            --yellow-glow: rgba(234, 179, 8, 0.6);
            --red-glow: rgba(239, 68, 68, 0.6);
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { background: var(--bg-main); color: var(--text-primary); font-family: Inter, system-ui, sans-serif; margin: 0; padding: 0; min-height: 100vh; overflow-x: hidden; }
        .bg-decoration { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; overflow: hidden; }
        .bg-decoration::before { content: ''; position: absolute; top: -10%; left: 50%; transform: translateX(-50%); width: 1200px; height: 800px; background: url('./images/page-illustration.svg') no-repeat center; background-size: contain; opacity: 0.15; }
        .bg-decoration::after { content: ''; position: absolute; top: 20%; right: -20%; width: 600px; height: 600px; background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%); border-radius: 50%; }
        .bg-blur-shape { position: fixed; width: 400px; height: 400px; border-radius: 50%; filter: blur(100px); opacity: 0.06; pointer-events: none; z-index: 0; }
        .bg-blur-1 { top: 10%; left: -10%; background: var(--indigo-500); }
        .bg-blur-2 { bottom: 20%; right: -10%; background: #4f46e5; }
        .main-wrap { position: relative; z-index: 1; padding-bottom: 140px; padding-top: 8px; }

        /* Header — S18: без бутон Добави */
        .top-header { position: sticky; top: 0; z-index: 50; padding: 8px 16px; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); background: rgba(11, 15, 26, 0.8); border-bottom: 1px solid var(--border-subtle); }
        .top-header h1 { font-family: Nacelle, Inter, sans-serif; font-size: 1.25rem; font-weight: 700; margin: 0; background: linear-gradient(to right, var(--text-primary), var(--indigo-200), #f9fafb, var(--indigo-300), var(--text-primary)); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; animation: gradient 6s linear infinite; }
        @keyframes gradient { 0% { background-position: 0% center; } 100% { background-position: 200% center; } }
        .header-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .store-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 999px; background: rgba(99,102,241,0.15); color: var(--indigo-300); border: 1px solid var(--border-subtle); white-space: nowrap; }

        /* Search — S18: само mic + filter */
        .search-wrap { margin: 8px 16px 0; position: relative; }
        .search-input { width: 100%; padding: 10px 80px 10px 40px; border-radius: 12px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.9rem; outline: none; transition: all 0.3s; }
        .search-input:focus { border-color: var(--border-glow); box-shadow: 0 0 20px rgba(99,102,241,0.15); }
        .search-input::placeholder { color: var(--text-secondary); }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); pointer-events: none; }
        .search-actions { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); display: flex; gap: 2px; }
        .search-btn { width: 36px; height: 36px; border-radius: 8px; border: none; background: transparent; color: var(--indigo-300); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .search-btn:active { background: rgba(99,102,241,0.2); transform: scale(0.9); }
        .search-btn.active { background: rgba(99,102,241,0.25); color: #fff; }

        /* Tabs */
        .tabs-row { display: flex; gap: 6px; padding: 10px 16px 0; overflow-x: auto; scrollbar-width: none; }
        .tabs-row::-webkit-scrollbar { display: none; }
        .tab-btn { padding: 6px 14px; border-radius: 999px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.8rem; white-space: nowrap; cursor: pointer; transition: all 0.2s; flex-shrink: 0; }
        .tab-btn.active { background: linear-gradient(135deg, var(--indigo-500), #4f46e5); color: #fff; border-color: transparent; box-shadow: 0 0 12px rgba(99,102,241,0.3); }
        .tab-btn .count { display: inline-block; min-width: 18px; height: 18px; line-height: 18px; text-align: center; border-radius: 9px; background: rgba(255,255,255,0.15); font-size: 0.65rem; margin-left: 4px; padding: 0 4px; }

        /* Quick stats — S18: glow на иконки */
        .quick-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 12px 16px 0; }
        .stat-card { position: relative; padding: 14px; border-radius: 12px; background: var(--bg-card); overflow: hidden; border: 1px solid var(--border-subtle); box-shadow: 0 4px 20px rgba(99,102,241,0.08); }
        .stat-card::before { content: ''; position: absolute; inset: 0; border-radius: inherit; border: 1px solid transparent; background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(79,70,229,0.05)) border-box; mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0); mask-composite: exclude; -webkit-mask-composite: xor; pointer-events: none; }
        .stat-card .stat-label { font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-value { font-size: 1.3rem; font-weight: 700; font-family: Nacelle, Inter, sans-serif; margin-top: 4px; }
        .stat-card .stat-icon { position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; border-radius: 8px; background: rgba(99,102,241,0.12); display: flex; align-items: center; justify-content: center; font-size: 1rem; filter: drop-shadow(0 0 8px rgba(99,102,241,0.5)); }

        /* ====== S18 NEW: 4 Big Action Buttons ====== */
        .home-actions { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; padding: 14px 16px 0; position: relative; }
        .home-act-btn { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 18px 8px; border-radius: 14px; border: 1px solid var(--border-subtle); background: var(--bg-card); cursor: pointer; transition: all 0.2s; overflow: hidden; }
        .home-act-btn:active { transform: scale(0.96); }
        .home-act-btn::before { content: ''; position: absolute; inset: 0; border-radius: inherit; border: 1px solid transparent; background: linear-gradient(135deg, rgba(99,102,241,0.15), transparent) border-box; mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0); mask-composite: exclude; -webkit-mask-composite: xor; pointer-events: none; }
        .home-act-btn .ha-icon { font-size: 1.6rem; filter: drop-shadow(0 0 10px rgba(99,102,241,0.6)); }
        .home-act-btn .ha-label { font-size: 0.7rem; font-weight: 600; color: var(--indigo-300); }
        .home-act-btn.ai-btn-glow { border-color: rgba(99,102,241,0.3); box-shadow: 0 0 24px rgba(99,102,241,0.15); }
        .home-act-btn.ai-btn-glow .ha-icon { position: relative; }

        /* AI waves animation */
        .ai-waves { position: absolute; bottom: 0; left: 0; right: 0; height: 30px; opacity: 0.3; overflow: hidden; }
        .ai-waves svg { width: 100%; height: 100%; }

        /* Info button (small, bottom-right of grid) */
        .info-btn { position: absolute; bottom: -6px; right: 16px; width: 28px; height: 28px; border-radius: 50%; background: rgba(99,102,241,0.1); border: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.7rem; color: var(--indigo-300); z-index: 2; transition: all 0.2s; }
        .info-btn:active { background: rgba(99,102,241,0.25); transform: scale(0.9); }

        /* SVG Scan crosshair icon */
        .scan-crosshair { width: 28px; height: 28px; filter: drop-shadow(0 0 8px rgba(99,102,241,0.6)); }

        /* ====== S18 NEW: Info Overlay ====== */
        .info-overlay { position: fixed; inset: 0; z-index: 500; background: rgba(11,15,26,0.75); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); display: none; align-items: center; justify-content: center; padding: 20px; }
        .info-overlay.open { display: flex; }
        .info-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 24px 20px; max-width: 340px; width: 100%; max-height: 80vh; overflow-y: auto; box-shadow: 0 8px 40px rgba(99,102,241,0.2); }
        .info-card h3 { font-family: Nacelle, Inter, sans-serif; font-size: 1.1rem; margin: 0 0 12px; color: var(--indigo-300); }
        .info-item { margin-bottom: 12px; }
        .info-item .ii-title { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); margin-bottom: 3px; display: flex; align-items: center; gap: 6px; }
        .info-item .ii-title span { filter: drop-shadow(0 0 6px rgba(99,102,241,0.5)); }
        .info-item .ii-text { font-size: 0.75rem; color: var(--text-secondary); line-height: 1.5; }
        .info-close-btn { width: 100%; margin-top: 8px; padding: 10px; border-radius: 10px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.8rem; cursor: pointer; }

        /* ====== S18 NEW: Voice Overlay (blur + waves) ====== */
        .voice-overlay { position: fixed; inset: 0; z-index: 400; background: rgba(11,15,26,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); display: none; flex-direction: column; align-items: center; justify-content: center; }
        .voice-overlay.open { display: flex; }
        .voice-ov-text { font-size: 1rem; color: var(--text-primary); font-weight: 500; margin-bottom: 4px; }
        .voice-ov-sub { font-size: 0.75rem; color: var(--indigo-300); margin-bottom: 16px; }
        .voice-ov-transcript { background: rgba(99,102,241,0.06); border: 1px solid rgba(99,102,241,0.12); border-radius: 12px; padding: 10px 16px; max-width: 280px; margin-bottom: 16px; font-size: 0.85rem; color: var(--text-primary); text-align: center; min-height: 40px; }
        .voice-ov-hint { font-size: 0.7rem; color: var(--text-secondary); }

        /* ====== S18 NEW: AI Assistant Drawer (in-module) ====== */
        .ai-assist-input { display: flex; gap: 6px; margin-top: 12px; }
        .ai-assist-input input { flex: 1; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.85rem; outline: none; }
        .ai-assist-input button { padding: 10px 14px; border-radius: 10px; border: none; background: linear-gradient(135deg, var(--indigo-500), #4f46e5); color: #fff; font-size: 0.8rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .ai-assist-msg { padding: 10px 12px; border-radius: 10px; margin-bottom: 8px; font-size: 0.8rem; line-height: 1.5; }
        .ai-assist-msg.user { background: rgba(99,102,241,0.1); border: 1px solid var(--border-subtle); color: var(--text-primary); text-align: right; }
        .ai-assist-msg.ai { background: rgba(99,102,241,0.05); border: 1px solid rgba(99,102,241,0.08); color: var(--text-primary); }

        /* Collapse sections — S18: glow на иконки */
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

        /* Product cards — S18: glow on hover */
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
        .pc-stock.ok { color: #22c55e; }
        .pc-stock.low { color: #eab308; }
        .pc-stock.out { color: #ef4444; }
        .pc-discount { position: absolute; top: 4px; right: 4px; font-size: 0.6rem; padding: 1px 6px; border-radius: 4px; background: #ef4444; color: #fff; font-weight: 600; }

        /* Supplier & category cards (unchanged) */
        .swipe-container { padding: 12px 16px; overflow-x: auto; display: flex; gap: 12px; scroll-snap-type: x mandatory; scrollbar-width: none; }
        .swipe-container::-webkit-scrollbar { display: none; }
        .supplier-card { min-width: 260px; max-width: 300px; flex-shrink: 0; scroll-snap-align: start; border-radius: 14px; padding: 16px; background: var(--bg-card); border: 1px solid var(--border-subtle); position: relative; overflow: hidden; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 16px rgba(99,102,241,0.06); }
        .supplier-card:active { transform: scale(0.97); }
        .supplier-card::before { content: ''; position: absolute; inset: 0; border-radius: inherit; border: 1px solid transparent; background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(79,70,229,0.05)) border-box; mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0); mask-composite: exclude; -webkit-mask-composite: xor; pointer-events: none; }
        .supplier-card .sc-name { font-size: 1rem; font-weight: 700; font-family: Nacelle, Inter, sans-serif; }
        .supplier-card .sc-count { font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px; }
        .supplier-card .sc-badges { display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap; }
        .sc-badge { font-size: 0.65rem; padding: 2px 8px; border-radius: 999px; font-weight: 600; }
        .sc-badge.ok { background: rgba(34,197,94,0.15); color: #22c55e; }
        .sc-badge.low { background: rgba(234,179,8,0.15); color: #eab308; }
        .sc-badge.out { background: rgba(239,68,68,0.15); color: #ef4444; }
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

        /* Breadcrumb, cross-link */
        .breadcrumb { display: flex; align-items: center; gap: 4px; padding: 8px 16px 0; font-size: 0.75rem; color: var(--text-secondary); flex-wrap: wrap; }
        .breadcrumb a { color: var(--indigo-400); text-decoration: none; }
        .cross-link { margin: 6px 16px 0; padding: 8px 12px; border-radius: 8px; background: rgba(99,102,241,0.08); border: 1px dashed var(--border-glow); font-size: 0.75rem; color: var(--indigo-300); cursor: pointer; }

        /* Screen nav, bottom nav, FAB (unchanged) */
        .screen-nav { position: fixed; bottom: 68px; left: 0; right: 0; z-index: 40; display: flex; justify-content: center; padding: 0 12px; }
        .screen-nav-inner { display: flex; gap: 4px; background: rgba(11, 15, 26, 0.9); backdrop-filter: blur(12px); border-radius: 14px; padding: 4px; border: 1px solid var(--border-subtle); box-shadow: 0 -4px 24px rgba(0,0,0,0.3); width: 100%; max-width: 400px; }
        .snav-btn { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 8px 4px; border-radius: 10px; border: none; background: transparent; color: var(--text-secondary); font-size: 0.6rem; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
        .snav-btn .snav-icon { font-size: 1.1rem; }
        .snav-btn.active { background: linear-gradient(135deg, rgba(99,102,241,0.25), rgba(79,70,229,0.15)); color: #fff; box-shadow: 0 0 12px rgba(99,102,241,0.2); }
        .snav-btn:active { transform: scale(0.95); }
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 45; background: rgba(11, 15, 26, 0.95); backdrop-filter: blur(12px); border-top: 1px solid var(--border-subtle); display: flex; height: 56px; }
        .bnav-tab { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; font-size: 0.6rem; color: var(--text-secondary); text-decoration: none; transition: color 0.2s; }
        .bnav-tab.active { color: var(--indigo-400); text-shadow: 0 0 14px rgba(99,102,241,0.9); }
        .bnav-tab .bnav-icon { font-size: 1.2rem; }
        .fab-add { position: fixed; bottom: 134px; right: 16px; z-index: 42; width: 52px; height: 52px; border-radius: 14px; border: none; background: linear-gradient(135deg, var(--indigo-500), #4f46e5); color: #fff; font-size: 1.5rem; cursor: pointer; box-shadow: 0 4px 24px rgba(99,102,241,0.4); transition: all 0.3s; display: flex; align-items: center; justify-content: center; }
        .fab-add:active { transform: scale(0.9); }
        .fab-add::after { content: ''; position: absolute; inset: -3px; border-radius: 17px; background: linear-gradient(135deg, var(--indigo-400), var(--indigo-500)); z-index: -1; opacity: 0.4; animation: pulse-glow 2s ease-in-out infinite; }
        @keyframes pulse-glow { 0%, 100% { opacity: 0.3; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.05); } }

        /* Drawers (unchanged) */
        .drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 100; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .drawer-overlay.open { opacity: 1; pointer-events: auto; }
        .drawer { position: fixed; bottom: 0; left: 0; right: 0; z-index: 101; background: var(--bg-main); border-radius: 20px 20px 0 0; transform: translateY(100%); transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1); max-height: 90vh; display: flex; flex-direction: column; }
        .drawer.open { transform: translateY(0); }
        .drawer-handle { width: 36px; height: 4px; border-radius: 2px; background: rgba(255,255,255,0.2); margin: 10px auto 0; flex-shrink: 0; }
        .drawer-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px 10px; border-bottom: 1px solid var(--border-subtle); flex-shrink: 0; }
        .drawer-header h3 { font-family: Nacelle, Inter, sans-serif; font-size: 1.05rem; font-weight: 700; margin: 0; }
        .drawer-close { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .drawer-body { flex: 1; overflow-y: auto; padding: 14px 16px 24px; -webkit-overflow-scrolling: touch; }

        /* Modal overlay (unchanged structure) */
        .modal-overlay { position: fixed; inset: 0; background: var(--bg-main); z-index: 200; opacity: 0; pointer-events: none; transition: opacity 0.3s; display: flex; flex-direction: column; }
        .modal-overlay.open { opacity: 1; pointer-events: auto; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-subtle); flex-shrink: 0; }
        .modal-header h2 { font-family: Nacelle, Inter, sans-serif; font-size: 1.1rem; font-weight: 700; margin: 0; }
        .modal-body { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; }

        /* S18: Wizard 6 steps */
        .wizard-steps { display: flex; gap: 3px; padding: 10px 16px; }
        .wiz-step { flex: 1; height: 3px; border-radius: 2px; background: var(--border-subtle); transition: all 0.3s; }
        .wiz-step.active { background: linear-gradient(to right, var(--indigo-500), #8b5cf6); box-shadow: 0 0 6px rgba(99,102,241,0.3); }
        .wiz-step.done { background: var(--indigo-500); }
        .wiz-label { font-size: 0.65rem; color: var(--text-secondary); padding: 0 16px 6px; }
        .wiz-label span { color: var(--indigo-300); }
        .wizard-page { display: none; padding: 16px; }
        .wizard-page.active { display: block; animation: wiz-fade 0.2s ease; }
        @keyframes wiz-fade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        /* Form elements */
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

        /* Action buttons */
        .action-btn { padding: 10px; border-radius: 10px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s; font-family: Inter, system-ui, sans-serif; }
        .action-btn:active { background: var(--bg-card-hover); transform: scale(0.97); }
        .action-btn.primary { background: linear-gradient(135deg, var(--indigo-500), #4f46e5); border-color: transparent; color: #fff; box-shadow: 0 4px 14px rgba(99,102,241,0.25); }
        .action-btn.wide { grid-column: 1 / -1; width: 100%; }
        .action-btn.save { background: linear-gradient(to bottom, #22c55e, #16a34a); border-color: transparent; color: #fff; box-shadow: 0 4px 16px rgba(34,197,94,0.25); }
        .action-btn.danger { border-color: rgba(239,68,68,0.3); color: #ef4444; }
        .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }

        /* Toast (unchanged) */
        .toast-container { position: fixed; top: 16px; left: 16px; right: 16px; z-index: 500; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
        .toast { padding: 12px 16px; border-radius: 10px; background: rgba(17, 24, 44, 0.95); border: 1px solid var(--border-subtle); backdrop-filter: blur(8px); color: var(--text-primary); font-size: 0.8rem; transform: translateY(-20px); opacity: 0; transition: all 0.3s; pointer-events: auto; display: flex; align-items: center; gap: 8px; }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.success { border-color: rgba(34,197,94,0.4); }
        .toast.error { border-color: rgba(239,68,68,0.4); }

        /* Indigo decorations */
        .indigo-line { height: 1px; background: linear-gradient(to right, transparent, var(--border-glow), transparent); margin: 12px 16px; }
        .section-title { padding: 14px 16px 6px; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px; }
        .section-title::before { content: ''; display: inline-block; width: 24px; height: 1px; background: linear-gradient(to right, transparent, var(--indigo-500)); }
        .section-title::after { content: ''; flex: 1; height: 1px; background: linear-gradient(to left, transparent, var(--border-subtle)); }
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

        /* Camera (unchanged) */
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

        /* Pagination, sort, filter, empty state, skeleton */
        .pagination { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 16px; }
        .page-btn { width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .page-btn.active { background: var(--indigo-500); color: #fff; border-color: transparent; }
        .sort-dropdown { position: absolute; top: 100%; right: 0; margin-top: 4px; background: var(--bg-main); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 4px; min-width: 180px; z-index: 60; display: none; box-shadow: 0 8px 32px rgba(0,0,0,0.4); }
        .sort-dropdown.open { display: block; }
        .sort-option { padding: 8px 12px; border-radius: 8px; font-size: 0.8rem; cursor: pointer; color: var(--text-secondary); }
        .sort-option.active { background: rgba(99,102,241,0.12); color: var(--indigo-300); }
        .filter-section { margin-bottom: 16px; }
        .filter-section .fs-title { font-size: 0.8rem; font-weight: 600; margin-bottom: 8px; color: var(--indigo-300); }
        .filter-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .filter-chip { padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .filter-chip.selected { background: rgba(99,102,241,0.2); color: var(--indigo-300); border-color: var(--border-glow); }
        .empty-state { text-align: center; padding: 40px 20px; }
        .empty-state .es-icon { font-size: 3rem; margin-bottom: 12px; opacity: 0.5; }
        .empty-state .es-text { font-size: 0.9rem; color: var(--text-secondary); }
        .skeleton { background: linear-gradient(90deg, var(--bg-card) 25%, rgba(99,102,241,0.08) 50%, var(--bg-card) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 8px; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    </style>
</head>
<body>
    <div class="bg-decoration"></div>
    <div class="bg-blur-shape bg-blur-1"></div>
    <div class="bg-blur-shape bg-blur-2"></div>
    <div class="toast-container" id="toasts"></div>

    <!-- ====== S18 NEW: Info Overlay ====== -->
    <div class="info-overlay" id="infoOverlay" onclick="if(event.target===this)closeInfoOverlay()">
        <div class="info-card" onclick="event.stopPropagation()">
            <h3>📖 Как работи модулът?</h3>
            <div class="info-item"><div class="ii-icon">✦</div><div><div class="ii-title">AI Помощник</div><div class="ii-desc">Попитай AI за съвет — кое да поръчаш, кое залежава, кое се продава най-добре. AI знае всичко за артикулите ти.</div></div></div>
            <div class="info-item"><div class="ii-icon"><svg width="20" height="20" viewBox="0 0 36 36" fill="none"><path d="M4 10V6a2 2 0 012-2h4" stroke="var(--indigo-400)" stroke-width="2" stroke-linecap="round"/><path d="M26 4h4a2 2 0 012 2v4" stroke="var(--indigo-400)" stroke-width="2" stroke-linecap="round"/><path d="M32 26v4a2 2 0 01-2 2h-4" stroke="var(--indigo-400)" stroke-width="2" stroke-linecap="round"/><path d="M10 32H6a2 2 0 01-2-2v-4" stroke="var(--indigo-400)" stroke-width="2" stroke-linecap="round"/><circle cx="18" cy="18" r="2" fill="var(--indigo-400)"/></svg></div><div><div class="ii-title">Сканирай код</div><div class="ii-desc">Насочи камерата към баркод или QR код. Автоматично открива артикула и показва наличност, цена, детайли.</div></div></div>
            <div class="info-item"><div class="ii-icon" style="color:#22c55e;filter:drop-shadow(0 0 6px rgba(34,197,94,0.5))">＋</div><div><div class="ii-title">Добави артикул</div><div class="ii-desc">Снимай продукта и AI попълва всичко автоматично — име, цена, размери, описание. Или попълни ръчно стъпка по стъпка.</div></div></div>
            <div class="info-item"><div class="ii-icon">💀</div><div><div class="ii-title">Zombie стока</div><div class="ii-desc">Артикули без продажба повече от 45 дни. AI предлага намаление или пакетна продажба.</div></div></div>
            <div class="info-item"><div class="ii-icon">⚠️</div><div><div class="ii-title">Свършват скоро</div><div class="ii-desc">Артикули под минималната наличност. Натисни „Поръчай" и AI генерира поръчка.</div></div></div>
            <div class="info-item"><div class="ii-icon">🔥</div><div><div class="ii-title">Топ хитове</div><div class="ii-desc">Най-продаваните за последните 30 дни. Провери дали имаш достатъчно наличност.</div></div></div>
            <div class="info-item"><div class="ii-icon">🎤</div><div><div class="ii-title">Гласово търсене</div><div class="ii-desc">Натисни микрофона и кажи какво търсиш. Работи и с диалект — „дреги" = дрехи, „офки" = обувки.</div></div></div>
            <button class="info-close" onclick="closeInfoOverlay()">Разбрах, затвори</button>
        </div>
    </div>

    <!-- ====== S18 NEW: Voice Overlay (blur, waves, one tap close) ====== -->
    <div class="voice-overlay" id="voiceOverlay" onclick="closeVoiceOverlay()">
        <div class="voice-waves" onclick="event.stopPropagation()">
            <svg width="260" height="60" viewBox="0 0 260 60">
                <path d="M0,30 Q30,10 60,30 T120,30 T180,30 T240,30 T260,30" stroke="rgba(99,102,241,.7)" stroke-width="2" fill="none">
                    <animate attributeName="d" dur="1.5s" repeatCount="indefinite" values="M0,30 Q30,10 60,30 T120,30 T180,30 T240,30 T260,30;M0,30 Q30,50 60,30 T120,30 T180,30 T240,30 T260,30;M0,30 Q30,10 60,30 T120,30 T180,30 T240,30 T260,30"/>
                </path>
                <path d="M0,30 Q40,45 80,30 T160,30 T240,30 T260,30" stroke="rgba(139,92,246,.4)" stroke-width="1.5" fill="none">
                    <animate attributeName="d" dur="2s" repeatCount="indefinite" values="M0,30 Q40,45 80,30 T160,30 T240,30 T260,30;M0,30 Q40,15 80,30 T160,30 T240,30 T260,30;M0,30 Q40,45 80,30 T160,30 T240,30 T260,30"/>
                </path>
            </svg>
        </div>
        <div class="voice-title" onclick="event.stopPropagation()">Слушам...</div>
        <div class="voice-sub" onclick="event.stopPropagation()">Кажи какво търсиш</div>
        <div class="voice-transcript" id="voiceTranscript" onclick="event.stopPropagation()"></div>
        <div class="voice-hint">Натисни навсякъде за да затвориш</div>
    </div>

    <!-- ====== Drawers (UNCHANGED from S17) ====== -->
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
            <div class="filter-section"><div class="fs-title">Ценови диапазон</div><div class="price-range"><input type="number" class="form-control" id="priceFrom" placeholder="От" style="width:48%;"><span style="color:var(--text-secondary);">—</span><input type="number" class="form-control" id="priceTo" placeholder="До" style="width:48%;"></div></div>
            <button class="action-btn primary wide" onclick="applyFilters()" style="margin-top:12px;">Приложи филтри</button>
            <button class="action-btn wide" onclick="clearFilters()" style="margin-top:6px;">Изчисти</button>
        </div>
    </div>

    <!-- ====== S18 NEW: AI Assist Drawer (in-module) ====== -->
    <div class="drawer-overlay" id="aiAssistOverlay" onclick="closeDrawer('aiAssist')"></div>
    <div class="drawer" id="aiAssistDrawer">
        <div class="drawer-handle"></div>
        <div class="drawer-header"><h3>✦ AI Помощник</h3><button class="drawer-close" onclick="closeDrawer('aiAssist')">✕</button></div>
        <div class="drawer-body">
            <div style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:12px;">Попитай каквото искаш за артикулите, наличностите, цените. AI знае всичко за склада ти.</div>
            <div id="aiAssistMessages" style="min-height:100px;"></div>
            <div class="ai-assist-input">
                <input type="text" id="aiAssistQuestion" placeholder="напр. Кое залежава най-много?" onkeydown="if(event.key==='Enter')askAI()">
                <button onclick="askAI()">Питай</button>
            </div>
        </div>
    </div>

    <!-- ====== ADD/EDIT MODAL — S18: 6-step wizard ====== -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-header">
            <button onclick="closeAddModal()" style="background:transparent;border:none;color:var(--text-secondary);font-size:1.1rem;cursor:pointer;padding:4px;">✕</button>
            <h2 id="modalTitle">Нов артикул</h2>
            <div style="width:28px;"></div>
        </div>
        <div class="wizard-steps" id="wizardSteps"></div>
        <div class="wiz-step-label" id="wizStepLabel"></div>
        <div class="modal-body" id="wizardBody">
            <!-- Wizard pages generated by JS -->
        </div>
    </div>

    <!-- Camera overlay — UNCHANGED from S17 -->
    <div class="camera-overlay" id="cameraOverlay">
        <video class="camera-video" id="cameraVideo" playsinline autoplay></video>
        <div class="scan-line" id="scanLine" style="display:none;"></div>
        <canvas id="cameraCanvas" style="display:none;"></canvas>
        <div class="camera-controls">
            <button class="camera-btn close-cam" onclick="closeCamera()">✕</button>
            <button class="camera-btn capture" id="captureBtn" onclick="capturePhoto()" style="display:none;">✦</button>
        </div>
    </div>
    <input type="file" id="aiScanFileInput" accept="image/*" capture="environment" style="display:none" onchange="handleAIScanFile(this)">

    <script>
    // ============================================================
    // STATE (S17 base + S18 additions)
    // ============================================================
    const STATE = {
        storeId: <?= (int)$store_id ?>, screen: '<?= $screen ?>', supId: <?= $sup_id ? $sup_id : 'null' ?>, catId: <?= $cat_id ? $cat_id : 'null' ?>,
        canAdd: <?= $can_add ? 'true' : 'false' ?>, canSeeCost: <?= $can_see_cost ? 'true' : 'false' ?>, canSeeMargin: <?= $can_see_margin ? 'true' : 'false' ?>,
        currentSort: 'name', currentFilter: 'all', currentPage: 1, editProductId: null,
        // S18 wizard state
        wizStep: 0, productType: null, selectedSizes: {}, selectedColors: [], sizePrices: {}, colorPrices: {},
        priceLevel: null, variantCombs: [], aiScanData: null, selectedAIModel: null, printFormat: 'qr',
        // S18 other
        cameraMode: null, cameraStream: null, barcodeDetector: null, barcodeInterval: null,
        recognition: null, isListening: false
    };
    const WIZ_LABELS = ['Вид','AI разпознаване','Основна информация','Варианти','Детайли','Преглед и запис'];
    const COLOR_PALETTE = <?= json_encode($COLOR_PALETTE, JSON_UNESCAPED_UNICODE) ?>;
    const ALL_SUPPLIERS_JSON = <?= json_encode($all_suppliers, JSON_UNESCAPED_UNICODE) ?>;
    const ALL_CATEGORIES_JSON = <?= json_encode($all_categories, JSON_UNESCAPED_UNICODE) ?>;
    const UNITS_JSON = <?= json_encode($onboarding_units, JSON_UNESCAPED_UNICODE) ?>;

    // ============================================================
    // UTILITIES (UNCHANGED from S17)
    // ============================================================
    function fmtPrice(v){if(v===null||v===undefined)return'—';return parseFloat(v).toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2})+' €';}
    function fmtNum(v){if(v===null||v===undefined)return'—';return parseInt(v).toLocaleString('de-DE');}
    function stockClass(q,m){if(q<=0)return'out';if(m>0&&q<=m)return'low';return'ok';}
    function stockBarColor(q,m){if(q<=0)return'red';if(m>0&&q<=m)return'yellow';return'green';}
    function escHTML(s){if(!s)return'';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
    function showToast(m,t='info'){const c=document.getElementById('toasts');const el=document.createElement('div');el.className=`toast ${t}`;el.innerHTML=`<span>${t==='success'?'✓':t==='error'?'✕':'ℹ'}</span> ${m}`;c.appendChild(el);requestAnimationFrame(()=>el.classList.add('show'));setTimeout(()=>{el.classList.remove('show');setTimeout(()=>el.remove(),300);},3000);}
    async function fetchJSON(url,opts={}){try{const r=await fetch(url,opts);return await r.json();}catch(e){console.error(e);showToast('Грешка при зареждане','error');return null;}}

    function productCardHTML(p){const sc=stockClass(p.store_stock||p.qty||0,p.min_quantity||0);const bc=stockBarColor(p.store_stock||p.qty||0,p.min_quantity||0);const q=p.store_stock||p.qty||0;const th=p.image?`<img src="${p.image}">`:`<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="rgba(99,102,241,0.4)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>`;const disc=(p.discount_pct&&p.discount_pct>0)?`<div class="pc-discount">-${p.discount_pct}%</div>`:'';return`<div class="product-card" onclick="openProductDetail(${p.id})"><div class="stock-bar ${bc}"></div><div class="pc-thumb">${th}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta">${p.code?`<span>${escHTML(p.code)}</span>`:''}${p.supplier_name?`<span>${escHTML(p.supplier_name)}</span>`:''}</div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.retail_price)}</div><div class="pc-stock ${sc}">${q} ${p.unit||'бр.'}</div></div>${disc}</div>`;}

    // ============================================================
    // SCREEN NAVIGATION (UNCHANGED)
    // ============================================================
    function goScreen(s,p={}){let u=`products.php?screen=${s}`;if(p.sup)u+=`&sup=${p.sup}`;if(p.cat)u+=`&cat=${p.cat}`;window.location.href=u;}
    function switchStore(s){STATE.storeId=parseInt(s);loadCurrentScreen();}

    // ============================================================
    // HOME SCREEN (UNCHANGED from S17)
    // ============================================================
    let homeTab='all',homePageNum=1;
    async function loadHomeScreen(){const d=await fetchJSON(`products.php?ajax=home_stats&store_id=${STATE.storeId}`);if(!d)return;
    if(STATE.canSeeMargin){document.getElementById('statCapital').textContent=fmtPrice(d.capital);document.getElementById('statMargin').textContent=d.avg_margin!==null?d.avg_margin+'%':'—';}else{const sp=document.getElementById('statProducts');const su=document.getElementById('statUnits');if(sp)sp.textContent=fmtNum(d.counts?.total_products);if(su)su.textContent=fmtNum(d.counts?.total_units);}
    document.getElementById('countAll').textContent=d.counts?.total_products||0;document.getElementById('countLow').textContent=d.low_stock?.length||0;document.getElementById('countOut').textContent=d.out_of_stock?.length||0;
    if(d.zombies?.length>0){document.getElementById('collapseZombie').style.display='block';document.getElementById('zombieCount').textContent=d.zombies.length;document.getElementById('zombieList').innerHTML=d.zombies.map(p=>`<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;"><div class="stock-bar red"></div><div class="pc-thumb">${p.image?`<img src="${p.image}">`:'💀'}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta"><span>${p.days_stale} дни</span></div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.retail_price)}</div><div class="pc-stock out">${p.qty} бр.</div></div></div>`).join('');}
    if(d.low_stock?.length>0){document.getElementById('collapseLow').style.display='block';document.getElementById('lowCount').textContent=d.low_stock.length;document.getElementById('lowList').innerHTML=d.low_stock.map(p=>`<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;"><div class="stock-bar yellow"></div><div class="pc-thumb">${p.image?`<img src="${p.image}">`:'⚠️'}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta"><span>Мин: ${p.min_quantity}</span></div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.retail_price)}</div><div class="pc-stock low">${p.qty} бр.</div></div></div>`).join('');}
    if(d.top_sellers?.length>0){document.getElementById('collapseTop').style.display='block';document.getElementById('topCount').textContent=d.top_sellers.length;document.getElementById('topList').innerHTML=d.top_sellers.map((p,i)=>`<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;"><div class="stock-bar green"></div><div class="pc-thumb" style="font-size:1.2rem;font-weight:700;color:var(--indigo-300);">#${i+1}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta"><span>${p.sold_qty} продадени</span></div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.revenue)}</div></div></div>`).join('');}
    if(d.slow_movers?.length>0){document.getElementById('collapseSlow').style.display='block';document.getElementById('slowCount').textContent=d.slow_movers.length;document.getElementById('slowList').innerHTML=d.slow_movers.map(p=>`<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;"><div class="stock-bar yellow"></div><div class="pc-thumb">${p.image?`<img src="${p.image}">`:'🐌'}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta"><span>${p.days_stale} дни</span></div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.retail_price)}</div><div class="pc-stock low">${p.qty} бр.</div></div></div>`).join('');}
    loadHomeProducts();}
    async function loadHomeProducts(){const f=homeTab==='all'?'':`&filter=${homeTab}`;const d=await fetchJSON(`products.php?ajax=products&store_id=${STATE.storeId}${f}&page=${homePageNum}&sort=${STATE.currentSort}`);if(!d)return;const el=document.getElementById('homeProductsList');el.innerHTML=d.products.length===0?`<div class="empty-state"><div class="es-icon">📦</div><div class="es-text">Няма артикули</div></div>`:d.products.map(productCardHTML).join('');renderPagination('homePagination',d.page,d.pages,p=>{homePageNum=p;loadHomeProducts();});}
    function setHomeTab(t,b){homeTab=t;homePageNum=1;document.querySelectorAll('.tabs-row .tab-btn').forEach(x=>x.classList.remove('active'));b.classList.add('active');loadHomeProducts();}

    // ============================================================
    // SUPPLIERS, CATEGORIES, PRODUCTS LIST (UNCHANGED from S17)
    // ============================================================
    async function loadSuppliers(){const d=await fetchJSON(`products.php?ajax=suppliers&store_id=${STATE.storeId}`);if(!d)return;const el=document.getElementById('supplierCards');if(!d.length){el.innerHTML=`<div class="empty-state" style="width:100%;"><div class="es-icon">📦</div><div class="es-text">Няма доставчици</div></div>`;return;}el.innerHTML=d.map(s=>{const ok=s.product_count-s.low_count-s.out_count;return`<div class="supplier-card" onclick="goScreen('categories',{sup:${s.id}})"><div class="sc-name">${escHTML(s.name)}</div><div class="sc-count">${s.product_count} арт. · ${fmtNum(s.total_stock)} бр.</div><div class="sc-badges">${ok>0?`<span class="sc-badge ok">✓ ${ok}</span>`:''}${s.low_count>0?`<span class="sc-badge low">↓ ${s.low_count}</span>`:''}${s.out_count>0?`<span class="sc-badge out">✕ ${s.out_count}</span>`:''}</div><div class="sc-arrow">›</div></div>`;}).join('');document.getElementById('supplierStats').innerHTML=[...d].sort((a,b)=>b.total_stock-a.total_stock).slice(0,5).map((s,i)=>`<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(99,102,241,0.08);"><span style="font-size:0.8rem;"><span style="color:var(--indigo-400);font-weight:700;">#${i+1}</span> ${escHTML(s.name)}</span><span style="font-size:0.8rem;font-weight:600;">${fmtNum(s.total_stock)} бр.</span></div>`).join('');}
    async function loadCategories(){const sp=STATE.supId?`&sup=${STATE.supId}`:'';const d=await fetchJSON(`products.php?ajax=categories&store_id=${STATE.storeId}${sp}`);if(!d)return;if(STATE.supId){const el=document.getElementById('categoryList');el.innerHTML=d.length===0?`<div class="empty-state" style="padding:20px;"><div class="es-icon">🏷</div><div class="es-text">Няма категории</div></div>`:d.map(c=>`<div class="cat-list-item" onclick="goScreen('products',{sup:${STATE.supId},cat:${c.id}})"><div class="cli-left"><div class="cli-icon">🏷</div><div><div class="cli-name">${escHTML(c.name)}</div><div class="cli-count">${c.product_count} арт. · ${fmtNum(c.total_stock)} бр.</div></div></div><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-secondary);"><polyline points="9 18 15 12 9 6"/></svg></div>`).join('');}else{const el=document.getElementById('categoryCards');el.innerHTML=d.length===0?`<div class="empty-state" style="width:100%;"><div class="es-icon">🏷</div><div class="es-text">Няма категории</div></div>`:d.map(c=>`<div class="category-card" onclick="goScreen('products',{cat:${c.id}})"><div class="cc-name">${escHTML(c.name)}</div><div class="cc-info">${c.product_count} арт.${c.supplier_count?` · ${c.supplier_count} дост.`:''}</div><div class="cc-info">${fmtNum(c.total_stock)} бр.</div></div>`).join('');}}
    async function loadProductsList(){let p=`store_id=${STATE.storeId}&sort=${STATE.currentSort}&page=${STATE.currentPage}`;if(STATE.supId)p+=`&sup=${STATE.supId}`;if(STATE.catId)p+=`&cat=${STATE.catId}`;if(STATE.currentFilter!=='all')p+=`&filter=${STATE.currentFilter}`;const d=await fetchJSON(`products.php?ajax=products&${p}`);if(!d)return;const el=document.getElementById('productsList');el.innerHTML=d.products.length===0?`<div class="empty-state"><div class="es-icon">📋</div><div class="es-text">Няма артикули</div></div>`:d.products.map(productCardHTML).join('');renderPagination('productsPagination',d.page,d.pages,pg=>{STATE.currentPage=pg;loadProductsList();});}
    function renderPagination(cid,cur,tot,cb){const el=document.getElementById(cid);if(tot<=1){el.innerHTML='';return;}let h='';if(cur>1)h+=`<button class="page-btn" data-p="${cur-1}">‹</button>`;for(let i=Math.max(1,cur-2);i<=Math.min(tot,cur+2);i++)h+=`<button class="page-btn ${i===cur?'active':''}" data-p="${i}">${i}</button>`;if(cur<tot)h+=`<button class="page-btn" data-p="${cur+1}">›</button>`;el.innerHTML=h;el.querySelectorAll('.page-btn').forEach(b=>b.addEventListener('click',()=>cb(parseInt(b.dataset.p))));}
    function toggleCollapse(id){const map={zombie:'collapseZombie',low:'collapseLow',top:'collapseTop',slow:'collapseSlow'};const s=document.getElementById(map[id]);s.querySelector('.collapse-header').classList.toggle('open');s.querySelector('.collapse-body').classList.toggle('open');}
    function toggleSort(){document.getElementById('sortDropdown').classList.toggle('open');}
    function setSort(s){STATE.currentSort=s;document.querySelectorAll('.sort-option').forEach(o=>o.classList.toggle('active',o.dataset.sort===s));document.getElementById('sortDropdown').classList.remove('open');if(STATE.screen==='products')loadProductsList();else loadHomeProducts();}

    // Search
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input',function(){clearTimeout(searchTimeout);const q=this.value.trim();if(q.length<1){loadCurrentScreen();return;}searchTimeout=setTimeout(async()=>{const d=await fetchJSON(`products.php?ajax=search&q=${encodeURIComponent(q)}&store_id=${STATE.storeId}`);if(!d)return;const el=document.getElementById(STATE.screen==='products'?'productsList':'homeProductsList');el.innerHTML=d.length===0?`<div class="empty-state"><div class="es-icon">🔍</div><div class="es-text">Нищо за "${escHTML(q)}"</div></div>`:d.map(p=>productCardHTML({...p,store_stock:p.total_stock})).join('');},300);});

    // ============================================================
    // PRODUCT DETAIL & AI DRAWERS (UNCHANGED from S17)
    // ============================================================
    async function openProductDetail(id){openDrawer('detail');document.getElementById('detailBody').innerHTML='<div style="text-align:center;padding:20px;"><div class="skeleton" style="width:60%;height:20px;margin:0 auto 12px;"></div></div>';const d=await fetchJSON(`products.php?ajax=product_detail&id=${id}`);if(!d||d.error){showToast('Грешка','error');closeDrawer('detail');return;}const p=d.product;document.getElementById('detailTitle').textContent=p.name;let h='';
    if(p.image)h+=`<div style="text-align:center;margin-bottom:12px;"><img src="${p.image}" style="max-width:200px;border-radius:12px;border:1px solid var(--border-subtle);"></div>`;
    h+=`<div class="detail-row"><span class="detail-label">Код</span><span class="detail-value">${escHTML(p.code)||'—'}</span></div>`;
    h+=`<div class="detail-row"><span class="detail-label">Баркод</span><span class="detail-value">${escHTML(p.barcode)||'—'}</span></div>`;
    h+=`<div class="detail-row"><span class="detail-label">Цена</span><span class="detail-value">${fmtPrice(p.retail_price)}</span></div>`;
    if(STATE.canSeeCost){h+=`<div class="detail-row"><span class="detail-label">Доставна</span><span class="detail-value">${fmtPrice(p.cost_price)}</span></div>`;if(p.cost_price>0){const m=(((p.retail_price-p.cost_price)/p.retail_price)*100).toFixed(1);h+=`<div class="detail-row"><span class="detail-label">Марж</span><span class="detail-value" style="color:${m>30?'#22c55e':m>15?'#eab308':'#ef4444'};">${m}%</span></div>`;}}
    h+=`<div class="detail-row"><span class="detail-label">Доставчик</span><span class="detail-value">${escHTML(p.supplier_name)||'—'}</span></div>`;
    h+=`<div class="detail-row"><span class="detail-label">Категория</span><span class="detail-value">${escHTML(p.category_name)||'—'}</span></div>`;
    h+=`<div style="margin-top:14px;"><div class="section-title" style="padding:0 0 8px;">Наличност по обекти</div>`;
    d.stocks.forEach(s=>{h+=`<div class="store-stock-row"><span style="font-size:0.8rem;">${escHTML(s.store_name)}</span><span style="font-size:0.85rem;font-weight:700;color:${s.qty>0?'#22c55e':'#ef4444'};">${s.qty} бр.</span></div>`;});h+=`</div>`;
    if(d.variations?.length>0){h+=`<div style="margin-top:14px;"><div class="section-title" style="padding:0 0 8px;">Вариации</div>`;d.variations.forEach(v=>{h+=`<div class="product-card" onclick="openProductDetail(${v.id})" style="margin-bottom:4px;"><div class="stock-bar ${stockBarColor(v.total_stock,0)}"></div><div class="pc-info" style="margin-left:10px;"><div class="pc-name" style="font-size:0.8rem;">${escHTML(v.name)}</div></div><div class="pc-right"><div class="pc-price">${fmtPrice(v.retail_price)}</div><div class="pc-stock ${stockClass(v.total_stock,0)}">${v.total_stock} бр.</div></div></div>`;});h+=`</div>`;}
    h+=`<div class="action-grid">`;if(STATE.canAdd)h+=`<button class="action-btn" onclick="editProduct(${p.id})">✏️ Редактирай</button>`;
    h+=`<button class="action-btn" onclick="openAIDrawer(${p.id})">✦ AI Съвет</button>`;
    h+=`<button class="action-btn primary" onclick="window.location='sale.php?product=${p.id}'">💰 Продажба</button>`;
    h+=`</div>`;document.getElementById('detailBody').innerHTML=h;}

    async function openAIDrawer(id){closeDrawer('detail');setTimeout(()=>openDrawer('ai'),350);document.getElementById('aiBody').innerHTML='<div style="text-align:center;padding:20px;">✦ Анализирам...</div>';const d=await fetchJSON(`products.php?ajax=ai_analyze&id=${id}`);if(!d||d.error){document.getElementById('aiBody').innerHTML='<div style="padding:20px;color:#ef4444;">Грешка</div>';return;}let h='';if(!d.analysis.length){h=`<div class="ai-insight info"><span class="ai-icon">✓</span><span class="ai-text">Всичко е наред. Без предупреждения.</span></div>`;}else{d.analysis.forEach(a=>{h+=`<div class="ai-insight ${a.severity}"><span class="ai-icon">${a.icon}</span><span class="ai-text">${a.text}</span></div>`;});}
    h+=`<div style="margin-top:12px;display:flex;gap:6px;"><a class="ai-deeplink" href="chat.php?ctx=product&id=${id}">💬 Попитай AI</a><a class="ai-deeplink" href="stats.php?product=${id}">📊 Статистики</a></div>`;document.getElementById('aiBody').innerHTML=h;}

    // ============================================================
    // DRAWER MANAGEMENT (UNCHANGED + S18 aiAssist)
    // ============================================================
    function openDrawer(n){document.getElementById(n+'Overlay').classList.add('open');document.getElementById(n+'Drawer').classList.add('open');document.body.style.overflow='hidden';}
    function closeDrawer(n){document.getElementById(n+'Overlay').classList.remove('open');document.getElementById(n+'Drawer').classList.remove('open');document.body.style.overflow='';}
    ['detail','ai','filter','aiAssist'].forEach(n=>{const d=document.getElementById(n+'Drawer');if(!d)return;let sy=0,cy=0,dr=false;d.addEventListener('touchstart',e=>{if(e.target.closest('.drawer-body')?.scrollTop>0)return;sy=e.touches[0].clientY;dr=true;},{passive:true});d.addEventListener('touchmove',e=>{if(!dr)return;cy=e.touches[0].clientY-sy;if(cy>0)d.style.transform=`translateY(${cy}px)`;},{passive:true});d.addEventListener('touchend',()=>{if(!dr)return;dr=false;if(cy>100)closeDrawer(n);d.style.transform='';cy=0;});});

    // Filter drawer (UNCHANGED)
    function openFilterDrawer(){openDrawer('filter');}
    function toggleFilterChip(el,t){if(t==='stock'){el.parentElement.querySelectorAll('.filter-chip').forEach(c=>c.classList.remove('selected'));el.classList.add('selected');}else{el.classList.toggle('selected');}}
    function applyFilters(){const sc=document.querySelector('#filterCats .filter-chip.selected');const ss=document.querySelector('#filterSups .filter-chip.selected');let u='products.php?screen=products';if(ss)u+=`&sup=${ss.dataset.sup}`;if(sc)u+=`&cat=${sc.dataset.cat}`;const sk=document.querySelector('[data-stock].selected');if(sk&&sk.dataset.stock!=='all')u+=`&filter=${sk.dataset.stock}`;closeDrawer('filter');window.location.href=u;}
    function clearFilters(){document.querySelectorAll('.filter-chip').forEach(c=>c.classList.remove('selected'));document.getElementById('priceFrom').value='';document.getElementById('priceTo').value='';}

    <div class="main-wrap">

        <!-- ====== HEADER — S18: без Добави бутон, без скенер ====== -->
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

        <!-- ====== SEARCH — S18: само mic + filter ====== -->
        <div class="search-wrap">
            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Търси артикул, код, баркод..." autocomplete="off">
            <div class="search-actions">
                <button class="search-btn" id="voiceBtn" onclick="toggleVoice()" title="Диктувай">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
                </button>
                <button class="search-btn" id="filterBtn" onclick="openFilterDrawer()" title="Филтри">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                </button>
            </div>
        </div>

        <!-- ====== SCREEN: HOME ====== -->
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

            <!-- ====== S18 NEW: 4 Big Action Buttons ====== -->
            <?php if ($can_add): ?>
            <div class="home-actions">
                <!-- AI Assistant (in-module) -->
                <div class="home-act-btn ai-btn-glow" onclick="openAIAssist()">
                    <div class="ha-icon">✦</div>
                    <div class="ha-label">AI Асистент</div>
                    <div class="ai-waves">
                        <svg viewBox="0 0 200 30" preserveAspectRatio="none">
                            <path d="M0,15 Q25,5 50,15 T100,15 T150,15 T200,15" stroke="rgba(99,102,241,0.5)" stroke-width="1.5" fill="none">
                                <animate attributeName="d" dur="2s" repeatCount="indefinite" values="M0,15 Q25,5 50,15 T100,15 T150,15 T200,15;M0,15 Q25,25 50,15 T100,15 T150,15 T200,15;M0,15 Q25,5 50,15 T100,15 T150,15 T200,15"/>
                            </path>
                            <path d="M0,15 Q40,22 80,15 T160,15 T200,15" stroke="rgba(139,92,246,0.3)" stroke-width="1" fill="none">
                                <animate attributeName="d" dur="2.5s" repeatCount="indefinite" values="M0,15 Q40,22 80,15 T160,15 T200,15;M0,15 Q40,8 80,15 T160,15 T200,15;M0,15 Q40,22 80,15 T160,15 T200,15"/>
                            </path>
                        </svg>
                    </div>
                </div>

                <!-- Scan (SVG crosshair) -->
                <div class="home-act-btn" onclick="openCamera('scan')">
                    <svg class="scan-crosshair" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" style="color:var(--indigo-300);">
                        <path d="M4 10V6a2 2 0 012-2h4" stroke-linecap="round"/>
                        <path d="M22 4h4a2 2 0 012 2v4" stroke-linecap="round"/>
                        <path d="M28 22v4a2 2 0 01-2 2h-4" stroke-linecap="round"/>
                        <path d="M10 28H6a2 2 0 01-2-2v-4" stroke-linecap="round"/>
                        <circle cx="16" cy="16" r="3" stroke-width="1.5" opacity="0.6"/>
                        <circle cx="16" cy="16" r="1" fill="currentColor" opacity="0.8">
                            <animate attributeName="opacity" dur="1.5s" repeatCount="indefinite" values="0.8;0.3;0.8"/>
                        </circle>
                    </svg>
                    <div class="ha-label">Сканирай</div>
                </div>

                <!-- Add -->
                <div class="home-act-btn" onclick="openAddModal()">
                    <div class="ha-icon" style="font-size:1.8rem;">＋</div>
                    <div class="ha-label">Добави</div>
                </div>

                <!-- Info (small, absolute) -->
                <div class="info-btn" onclick="openInfoOverlay()" title="Как работи?">ℹ</div>
            </div>
            <?php endif; ?>

            <!-- Collapse sections (unchanged structure) -->
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

        <!-- ====== SCREEN: SUPPLIERS (unchanged) ====== -->
        <section id="screenSuppliers" class="screen-section" style="display:<?= $screen === 'suppliers' ? 'block' : 'none' ?>;">
            <div class="section-title">Доставчици</div>
            <div class="swipe-container" id="supplierCards"></div>
            <div class="indigo-line"></div>
            <div class="stats-section"><h4>Статистики доставчици</h4><div id="supplierStats"></div></div>
        </section>

        <!-- ====== SCREEN: CATEGORIES (unchanged) ====== -->
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

        <!-- ====== SCREEN: PRODUCTS (unchanged) ====== -->
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

    <!-- Screen nav (unchanged) -->
    <div class="screen-nav"><div class="screen-nav-inner">
        <button class="snav-btn <?= $screen === 'home' ? 'active' : '' ?>" onclick="goScreen('home')"><span class="snav-icon">🏠</span><span>Начало</span></button>
        <button class="snav-btn <?= $screen === 'suppliers' ? 'active' : '' ?>" onclick="goScreen('suppliers')"><span class="snav-icon">📦</span><span>Доставчици</span></button>
        <button class="snav-btn <?= ($screen === 'categories') ? 'active' : '' ?>" onclick="goScreen('categories')"><span class="snav-icon">🏷</span><span>Категории</span></button>
        <button class="snav-btn <?= $screen === 'products' ? 'active' : '' ?>" onclick="goScreen('products')"><span class="snav-icon">📋</span><span>Артикули</span></button>
    </div></div>

    <!-- FAB (unchanged) -->
    <?php if ($can_add): ?><button class="fab-add" onclick="openAddModal()" title="Добави артикул">+</button><?php endif; ?>

    <!-- Bottom nav (unchanged) -->
    <nav class="bottom-nav">
        <a href="chat.php" class="bnav-tab"><span class="bnav-icon">✦</span>AI</a>
        <a href="warehouse.php" class="bnav-tab active"><span class="bnav-icon">📦</span>Склад</a>
        <a href="stats.php" class="bnav-tab"><span class="bnav-icon">📊</span>Справки</a>
        <a href="sale.php" class="bnav-tab"><span class="bnav-icon">⚡</span>Въвеждане</a>
    </nav>

    <!-- ====== DRAWERS (unchanged structure) ====== -->
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

    <!-- ====== S18 NEW: AI ASSISTANT DRAWER (in-module) ====== -->
    <div class="drawer-overlay" id="aiAssistOverlay" onclick="closeDrawer('aiAssist')"></div>
    <div class="drawer" id="aiAssistDrawer" style="z-index:102;">
        <div class="drawer-handle"></div>
        <div class="drawer-header"><h3>✦ AI Асистент</h3><button class="drawer-close" onclick="closeDrawer('aiAssist')">✕</button></div>
        <div class="drawer-body">
            <div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:12px;">Попитай каквото и да е за артикулите, наличностите, цените. AI знае всичко за склада ти.</div>
            <div id="aiAssistChat"></div>
            <div class="ai-assist-input">
                <input type="text" id="aiAssistQ" placeholder="Попитай AI..." onkeydown="if(event.key==='Enter')askAI()">
                <button onclick="askAI()">✦ Питай</button>
            </div>
        </div>
    </div>

    <!-- ====== S18 NEW: INFO OVERLAY ====== -->
    <div class="info-overlay" id="infoOverlay" onclick="closeInfoOverlay()">
        <div class="info-card" onclick="event.stopPropagation()">
            <h3>📖 Как работи модулът?</h3>
            <div class="info-item"><div class="ii-title"><span>✦</span> AI Асистент</div><div class="ii-text">Натисни бутона и попитай каквото искаш — какво свършва, какво се продава добре, какво да поръчаш. AI знае всичко за артикулите ти.</div></div>
            <div class="info-item"><div class="ii-title"><span>⬡</span> Сканирай</div><div class="ii-text">Насочи камерата към баркода на артикула и той автоматично ще се намери. Бързо и без писане.</div></div>
            <div class="info-item"><div class="ii-title"><span>＋</span> Добави артикул</div><div class="ii-text">Можеш да снимаш артикула и AI ще попълни всичко вместо теб — име, цена, доставчик, размери. Или продиктувай с глас. Или попълни на ръка — ти решаваш.</div></div>
            <div class="info-item"><div class="ii-title"><span>🔍</span> Търсене</div><div class="ii-text">Пиши в полето горе — търси по име, код или баркод. Или натисни микрофона и кажи какво търсиш.</div></div>
            <div class="info-item"><div class="ii-title"><span>📦</span> 4 екрана</div><div class="ii-text">Долните бутони те водят на различни изгледи — Начало (всичко на едно място), Доставчици, Категории и Артикули с детайлни филтри.</div></div>
            <div class="info-item"><div class="ii-title"><span>💀</span> AI препоръки</div><div class="ii-text">На Начало виждаш секции за стока която стои, свършва или се продава добре. AI ти казва какво да направиш — натисни и виж.</div></div>
            <button class="info-close-btn" onclick="closeInfoOverlay()">Разбрах, затвори</button>
        </div>
    </div>

    <!-- ====== S18 NEW: VOICE OVERLAY (blur + waves) ====== -->
    <div class="voice-overlay" id="voiceOverlay" onclick="closeVoiceOverlay()">
        <svg width="260" height="60" viewBox="0 0 260 60" onclick="event.stopPropagation()" style="margin-bottom:16px;">
            <path d="M0,30 Q30,10 60,30 T120,30 T180,30 T240,30 T260,30" stroke="rgba(99,102,241,.7)" stroke-width="2" fill="none"><animate attributeName="d" dur="1.5s" repeatCount="indefinite" values="M0,30 Q30,10 60,30 T120,30 T180,30 T240,30 T260,30;M0,30 Q30,50 60,30 T120,30 T180,30 T240,30 T260,30;M0,30 Q30,10 60,30 T120,30 T180,30 T240,30 T260,30"/></path>
            <path d="M0,30 Q40,45 80,30 T160,30 T240,30 T260,30" stroke="rgba(139,92,246,.4)" stroke-width="1.5" fill="none"><animate attributeName="d" dur="2s" repeatCount="indefinite" values="M0,30 Q40,45 80,30 T160,30 T240,30 T260,30;M0,30 Q40,15 80,30 T160,30 T240,30 T260,30;M0,30 Q40,45 80,30 T160,30 T240,30 T260,30"/></path>
        </svg>
        <div class="voice-ov-text" onclick="event.stopPropagation()">Слушам...</div>
        <div class="voice-ov-sub" onclick="event.stopPropagation()">Кажи какво търсиш</div>
        <div class="voice-ov-transcript" id="voiceTranscript" onclick="event.stopPropagation()"></div>
        <div class="voice-ov-hint">Натисни навсякъде за да затвориш</div>
    </div>

    <!-- Camera (unchanged) -->
    <div class="camera-overlay" id="cameraOverlay">
        <video class="camera-video" id="cameraVideo" playsinline autoplay></video>
        <div class="scan-line" id="scanLine" style="display:none;"></div>
        <canvas id="cameraCanvas" style="display:none;"></canvas>
        <div class="camera-controls">
            <button class="camera-btn close-cam" onclick="closeCamera()">✕</button>
            <button class="camera-btn capture" id="captureBtn" onclick="capturePhoto()" style="display:none;">📷</button>
        </div>
    </div>

    <!-- ====== S18 NEW: ADD MODAL (6-step wizard) ====== -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-header">
            <button onclick="closeAddModal()" style="background:transparent;border:none;color:var(--text-secondary);font-size:1.1rem;cursor:pointer;">✕</button>
            <h2 id="modalTitle">Нов артикул</h2>
            <div style="width:28px;"></div>
        </div>
        <div class="wizard-steps" id="wizardSteps">
            <?php for($i=0;$i<6;$i++): ?><div class="wiz-step <?= $i===0?'active':'' ?>" data-step="<?= $i ?>"></div><?php endfor; ?>
        </div>
        <div class="wiz-label" id="wizLabel">1 · <span>Вид</span></div>
        <div class="modal-body">

            <!-- Step 0: Тип -->
            <div class="wizard-page active" data-page="0">
                <div style="font-size:1rem;font-weight:700;color:var(--text-primary);margin-bottom:4px;">Какъв е артикулът?</div>
                <div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:14px;">Изборът определя следващите стъпки</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                    <div class="home-act-btn" id="tcSingle" onclick="selectType('single')" style="padding:20px 12px;"><div class="ha-icon" style="font-size:2rem;">📦</div><div class="ha-label">Единичен</div></div>
                    <div class="home-act-btn" id="tcVariant" onclick="selectType('variant')" style="padding:20px 12px;"><div class="ha-icon" style="font-size:2rem;">🎨</div><div class="ha-label">С варианти</div></div>
                </div>
                <button class="action-btn primary wide" id="btn0n" onclick="goWizardStep(1)" style="display:none;">Напред →</button>
            </div>

            <!-- Step 1: AI Scan -->
            <div class="wizard-page" data-page="1">
                <div style="text-align:center;padding:8px 0;">
                    <div style="font-size:0.95rem;font-weight:600;color:var(--text-primary);margin-bottom:3px;">Снимай и AI попълва всичко</div>
                    <div style="font-size:0.75rem;color:var(--text-secondary);margin-bottom:16px;">Камера → AI разпознава → предпопълва</div>
                    <div class="ai-pulse-btn" onclick="openCamera('ai')" style="margin:0;border:none;padding:16px;">
                        <div class="ai-pulse-icon">✦</div>
                    </div>
                    <div style="background:rgba(99,102,241,0.05);border:1px solid var(--border-subtle);border-radius:10px;padding:10px;margin-top:14px;text-align:left;">
                        <div style="font-size:0.75rem;font-weight:600;color:var(--indigo-300);margin-bottom:4px;">💡 За най-добър резултат:</div>
                        <div style="font-size:0.72rem;color:var(--text-secondary);line-height:1.5;">Сложи артикула на <strong style="color:var(--text-primary);">равна светла повърхност</strong>, на добра светлина. Махни другите предмети наоколо — само артикулът в кадъра.</div>
                    </div>
                    <div id="aiScanPreview" style="display:none;margin-top:12px;"></div>
                </div>
                <button class="action-btn wide" onclick="goWizardStep(2)" style="margin-top:12px;color:var(--text-secondary);">Пропусни → ръчно</button>
                <button class="action-btn wide" onclick="goWizardStep(0)" style="margin-top:6px;">← Назад</button>
            </div>

            <!-- Step 2: Основна инфо -->
            <div class="wizard-page" data-page="2">
                <div class="form-group"><label class="form-label">Наименование *</label><input type="text" class="form-control" id="addName" placeholder="напр. Nike Air Max 90"></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Продажна цена *</label><input type="number" step="0.01" class="form-control" id="addRetailPrice" placeholder="0,00"></div>
                    <div class="form-group"><label class="form-label">Цена едро</label><input type="number" step="0.01" class="form-control" id="addWholesalePrice" placeholder="0,00"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Баркод <span class="fl-hint">(празно = автоматично)</span></label>
                    <div style="display:flex;gap:6px;"><input type="text" class="form-control" id="addBarcode" placeholder="Сканирай или въведи" style="flex:1;"><button class="action-btn" onclick="openCamera('barcode_add')" style="flex-shrink:0;width:42px;padding:0;"><svg width="18" height="18" viewBox="0 0 32 32" fill="none" stroke="var(--indigo-300)" stroke-width="1.8"><path d="M4 10V6a2 2 0 012-2h4" stroke-linecap="round"/><path d="M22 4h4a2 2 0 012 2v4" stroke-linecap="round"/><path d="M28 22v4a2 2 0 01-2 2h-4" stroke-linecap="round"/><path d="M10 28H6a2 2 0 01-2-2v-4" stroke-linecap="round"/><circle cx="16" cy="16" r="3" stroke-width="1.5" opacity="0.6"/></svg></button></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Доставчик <span class="fl-add" onclick="toggleInline('inlSup')">+ Нов</span></label>
                    <select class="form-control form-select" id="addSupplier"><option value="">— Избери —</option><?php foreach ($all_suppliers as $sup): ?><option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option><?php endforeach; ?></select>
                    <div class="inline-add" id="inlSup"><input type="text" placeholder="Име на доставчик" id="inlSupName"><button onclick="saveInline('supplier')">Запази</button></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Категория <span class="fl-add" onclick="toggleInline('inlCat')">+ Нова</span></label>
                    <select class="form-control form-select" id="addCategory"><option value="">— Избери —</option><?php foreach ($all_categories as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?></select>
                    <div class="inline-add" id="inlCat"><input type="text" placeholder="Име на категория" id="inlCatName"><button onclick="saveInline('category')">Запази</button></div>
                </div>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <button class="action-btn" onclick="goWizardStep(1)" style="flex:1;">← Назад</button>
                    <button class="action-btn primary" onclick="goWizardStep(3)" style="flex:1;">Напред →</button>
                </div>
            </div>

            <!-- Step 3: Варианти (TODO: ще бъде разширен с price override + search from DB) -->
            <div class="wizard-page" data-page="3">
                <div id="variantSection">
                    <div class="form-group">
                        <label class="form-label">Размери <span class="fl-hint">задръж = различна цена</span></label>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;" id="sizeChips">
                            <?php foreach (['XS','S','M','L','XL','XXL','3XL','36','37','38','39','40','41','42','43','44','45','46'] as $sz): ?>
                                <button class="size-chip" onclick="toggleSize(this)" data-size="<?= $sz ?>"><?= $sz ?></button>
                            <?php endforeach; ?>
                        </div>
                        <div style="display:flex;gap:6px;"><input type="text" class="form-control" id="customSize" placeholder="Друг размер" style="flex:1;"><button class="action-btn" onclick="addCustomSize()" style="flex-shrink:0;">+</button></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Цветове <span class="fl-hint">задръж = различна цена</span></label>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;" id="colorDots">
                            <?php foreach ($COLOR_PALETTE as $clr): ?>
                                <div style="display:flex;flex-direction:column;align-items:center;gap:2px;cursor:pointer;width:36px;" onclick="toggleColor(this)" data-color="<?= $clr['name'] ?>">
                                    <div style="width:26px;height:26px;border-radius:50%;background:<?= $clr['hex'] ?>;border:2px solid transparent;transition:all 0.2s;<?= $clr['hex']==='#f5f5f5'?'border-color:rgba(255,255,255,0.3);':'' ?>"></div>
                                    <div style="font-size:0.5rem;color:var(--text-secondary);text-align:center;"><?= $clr['name'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;margin-top:12px;">
                    <button class="action-btn" onclick="goWizardStep(2)" style="flex:1;">← Назад</button>
                    <button class="action-btn primary" onclick="goWizardStep(4)" style="flex:1;">Напред →</button>
                </div>
            </div>

            <!-- Step 4: Детайли -->
            <div class="wizard-page" data-page="4">
                <div class="form-group"><label class="form-label">Артикулен код <span class="fl-hint">ако не попълниш — AI генерира автоматично</span></label><input type="text" class="form-control" id="addCode" placeholder="NAM90-BLK"></div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Мерна единица <span class="fl-add" onclick="toggleInline('inlUnit')">+ Нова</span></label>
                        <select class="form-control form-select" id="addUnit"><?php foreach($onboarding_units as $u): ?><option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option><?php endforeach; ?></select>
                        <div class="inline-add" id="inlUnit"><input type="text" placeholder="напр. кашон" id="inlUnitName"><button onclick="saveInline('unit')">Запази</button></div>
                    </div>
                    <div class="form-group"><label class="form-label">Мин. наличност</label><input type="number" class="form-control" id="addMinQty" placeholder="0" value="0"></div>
                </div>
                <div class="form-group"><label class="form-label">Локация</label><input type="text" class="form-control" id="addLocation" placeholder="Рафт А3"></div>
                <div class="form-group">
                    <label class="form-label">SEO Описание <span class="fl-ai">✦ AI генерира с пълна картина</span></label>
                    <textarea class="form-control" id="addDescription" rows="3" placeholder="AI ще попълни автоматично..."></textarea>
                    <div style="font-size:0.65rem;color:var(--text-secondary);margin-top:3px;">AI знае: име + размери + цветове + категория → пълно SEO</div>
                </div>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <button class="action-btn" onclick="goWizardStep(3)" style="flex:1;">← Назад</button>
                    <button class="action-btn primary" onclick="goWizardStep(5)" style="flex:1;">Напред →</button>
                </div>
            </div>

            <!-- Step 5: Batch + AI Image + Print + Export + Save -->
            <div class="wizard-page" data-page="5">
                <div id="batchSummary"></div>
                <div id="batchGrid"></div>
                <!-- AI Image Studio section will be here -->
                <div id="aiStudioSection" style="margin-top:12px;"></div>
                <!-- Print + Export + Save -->
                <div id="finalActions" style="margin-top:12px;"></div>
                <button class="action-btn save wide" onclick="saveProduct()" id="saveProductBtn" style="margin-top:8px;">✓ Запази</button>
                <button class="action-btn wide" onclick="goWizardStep(4)" style="margin-top:6px;">← Назад</button>
            </div>

        </div>
    </div>

    <!-- AI Scan camera input -->
    <input type="file" id="aiScanFileInput" accept="image/*" capture="environment" style="display:none;" onchange="handleAIScanFile(this)">

    // ============================================================
    // S18 NEW: AI ASSIST (in-module, not chat.php)
    // ============================================================
    function openAIAssist(){openDrawer('aiAssist');}
    async function askAI(){const q=document.getElementById('aiAssistQuestion').value.trim();if(!q)return;const el=document.getElementById('aiAssistMessages');el.innerHTML+=`<div style="text-align:right;margin-bottom:8px;"><span style="background:rgba(99,102,241,0.15);padding:6px 12px;border-radius:10px 10px 2px 10px;font-size:0.8rem;display:inline-block;">${escHTML(q)}</span></div>`;document.getElementById('aiAssistQuestion').value='';el.innerHTML+=`<div style="margin-bottom:8px;" id="aiLoading"><span style="font-size:0.8rem;color:var(--text-secondary);">✦ Мисля...</span></div>`;const d=await fetchJSON('products.php?ajax=ai_assist',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({question:q})});document.getElementById('aiLoading')?.remove();if(d?.response){el.innerHTML+=`<div class="ai-response">${escHTML(d.response)}</div>`;}}

    // ============================================================
    // S18 NEW: INFO OVERLAY
    // ============================================================
    function openInfoOverlay(){document.getElementById('infoOverlay').classList.add('open');}
    function closeInfoOverlay(){document.getElementById('infoOverlay').classList.remove('open');}

    // ============================================================
    // S18 NEW: VOICE OVERLAY (blur, not fullscreen)
    // ============================================================
    function toggleVoiceSearch(){if(STATE.isListening){closeVoiceOverlay();return;}if(!('webkitSpeechRecognition' in window)&&!('SpeechRecognition' in window)){showToast('Гласовото не е поддържано','error');return;}const SR=window.SpeechRecognition||window.webkitSpeechRecognition;STATE.recognition=new SR();STATE.recognition.lang='bg-BG';STATE.recognition.continuous=false;document.getElementById('voiceOverlay').classList.add('open');document.getElementById('voiceTranscript').textContent='';STATE.recognition.onresult=e=>{const t=e.results[0][0].transcript;document.getElementById('voiceTranscript').textContent='"'+t+'"';document.getElementById('searchInput').value=t;document.getElementById('searchInput').dispatchEvent(new Event('input'));setTimeout(()=>closeVoiceOverlay(),800);};STATE.recognition.onerror=()=>closeVoiceOverlay();STATE.recognition.onend=()=>{STATE.isListening=false;};STATE.recognition.start();STATE.isListening=true;}
    function closeVoiceOverlay(){if(STATE.recognition)try{STATE.recognition.stop();}catch(e){}STATE.isListening=false;document.getElementById('voiceOverlay').classList.remove('open');}

    // ============================================================
    // S18 NEW: 6-STEP ADD WIZARD
    // ============================================================
    function openAddModal(){STATE.editProductId=null;STATE.wizStep=0;STATE.productType=null;STATE.selectedSizes={};STATE.selectedColors=[];STATE.sizePrices={};STATE.colorPrices={};STATE.priceLevel=null;STATE.variantCombs=[];STATE.aiScanData=null;STATE.selectedAIModel=null;document.getElementById('modalTitle').textContent='Нов артикул';renderWizard();document.getElementById('addModal').classList.add('open');document.body.style.overflow='hidden';}
    function closeAddModal(){document.getElementById('addModal').classList.remove('open');document.body.style.overflow='';}

    function renderWizard(){
        // Steps bar
        let sb='';for(let i=0;i<6;i++){let cls=i<STATE.wizStep?'done':i===STATE.wizStep?'active':'';sb+=`<div class="wiz-step ${cls}"></div>`;}
        document.getElementById('wizardSteps').innerHTML=sb;
        document.getElementById('wizStepLabel').innerHTML=`${STATE.wizStep+1} · <span>${WIZ_LABELS[STATE.wizStep]}</span>`;
        // Page content
        const body=document.getElementById('wizardBody');
        body.innerHTML=renderWizardPage(STATE.wizStep);
        body.scrollTop=0;
    }

    function wizGo(n){STATE.wizStep=n;renderWizard();}

    function renderWizardPage(step){
        if(step===0) return renderStep0();
        if(step===1) return renderStep1();
        if(step===2) return renderStep2();
        if(step===3) return renderStep3();
        if(step===4) return renderStep4();
        if(step===5) return renderStep5();
        return '';
    }

    // Step 0: Type
    function renderStep0(){
        const sSel=STATE.productType==='single'?'border-color:var(--indigo-500);background:rgba(99,102,241,0.1);box-shadow:0 0 20px rgba(99,102,241,0.15);':'';
        const vSel=STATE.productType==='variant'?'border-color:var(--indigo-500);background:rgba(99,102,241,0.1);box-shadow:0 0 20px rgba(99,102,241,0.15);':'';
        return `<div class="wizard-page active"><div style="padding:0 16px;"><div style="font-size:1rem;font-weight:700;color:var(--text-primary);margin-bottom:4px;">Какъв е артикулът?</div><div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:16px;">Изборът определя следващите стъпки</div><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;"><div style="padding:18px 14px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);text-align:center;cursor:pointer;${sSel}" onclick="STATE.productType='single';renderWizard();"><div style="font-size:2rem;margin-bottom:6px;filter:drop-shadow(0 0 8px rgba(99,102,241,0.4));">📦</div><div style="font-size:0.85rem;font-weight:600;">Единичен</div></div><div style="padding:18px 14px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);text-align:center;cursor:pointer;${vSel}" onclick="STATE.productType='variant';renderWizard();"><div style="font-size:2rem;margin-bottom:6px;filter:drop-shadow(0 0 8px rgba(99,102,241,0.4));">🎨</div><div style="font-size:0.85rem;font-weight:600;">С варианти</div></div></div>${STATE.productType?`<button class="action-btn primary wide" onclick="wizGo(1)">Напред →</button>`:''}</div></div>`;
    }

    // Step 1: AI Scan
    function renderStep1(){
        return `<div class="wizard-page active"><div style="padding:0 16px;text-align:center;"><div style="font-size:1rem;font-weight:600;color:var(--text-primary);margin-bottom:4px;">Снимай и AI попълва всичко</div><div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:16px;">Камера → AI разпознава → предпопълва</div><div style="width:90px;height:90px;margin:0 auto 14px;border-radius:50%;background:linear-gradient(135deg,var(--indigo-500),#4f46e5);display:flex;align-items:center;justify-content:center;box-shadow:0 0 40px rgba(99,102,241,0.4),0 0 80px rgba(99,102,241,0.12);cursor:pointer;animation:pulse-glow 2.5s infinite;" onclick="document.getElementById('aiScanFileInput').click();"><span style="font-size:1.8rem;filter:drop-shadow(0 0 10px rgba(255,255,255,0.5));">✦</span></div><div style="background:rgba(99,102,241,0.05);border:1px solid var(--border-subtle);border-radius:10px;padding:12px;text-align:left;margin-bottom:14px;"><div style="font-size:0.75rem;font-weight:600;color:var(--indigo-300);margin-bottom:4px;">💡 За най-добър резултат:</div><div style="font-size:0.75rem;color:var(--text-secondary);line-height:1.5;">Сложи артикула на <strong style="color:var(--text-primary);">равна светла повърхност</strong>, на добра светлина. Махни другите предмети наоколо — само артикулът в кадъра.</div></div><div id="aiScanPreview"></div><div class="action-btn wide" style="margin-bottom:8px;" onclick="wizGo(2)">Пропусни → ръчно</div><button class="action-btn wide" onclick="wizGo(0)">← Назад</button></div></div>`;
    }

    // Step 2: Basic info
    function renderStep2(){
        const nm=STATE.aiScanData?.name||'';const pr=STATE.aiScanData?.retail_price||'';
        let supOpts='<option value="">— Избери —</option>';ALL_SUPPLIERS_JSON.forEach(s=>{const sel=STATE.aiScanData?.supplier&&s.name.toLowerCase()===STATE.aiScanData.supplier.toLowerCase()?'selected':'';supOpts+=`<option value="${s.id}" ${sel}>${escHTML(s.name)}</option>`;});
        let catOpts='<option value="">— Избери —</option>';ALL_CATEGORIES_JSON.forEach(c=>{const sel=STATE.aiScanData?.category&&c.name.toLowerCase()===STATE.aiScanData.category.toLowerCase()?'selected':'';catOpts+=`<option value="${c.id}" ${sel}>${escHTML(c.name)}</option>`;});
        return `<div class="wizard-page active"><div style="padding:0 16px;">
        <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:rgba(99,102,241,0.05);border:1px solid rgba(99,102,241,0.1);border-radius:10px;margin-bottom:10px;cursor:pointer;" onclick="openWizardVoice()"><div style="width:24px;height:24px;border-radius:50%;background:rgba(99,102,241,0.15);display:flex;align-items:center;justify-content:center;box-shadow:0 0 8px rgba(99,102,241,0.25);"><div style="width:7px;height:7px;border-radius:50%;background:var(--indigo-300);box-shadow:0 0 6px rgba(165,180,252,0.7);"></div></div><span style="font-size:0.75rem;color:var(--indigo-300);font-weight:600;">Диктувай на AI</span></div>
        <div class="form-group"><label class="form-label">Наименование *</label><input type="text" class="form-control" id="wiz_name" value="${escHTML(nm)}" placeholder="напр. Nike Air Max"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div class="form-group"><label class="form-label">Продажна цена *</label><input type="number" step="0.01" class="form-control" id="wiz_price" value="${pr}" placeholder="0,00"></div>
            <div class="form-group"><label class="form-label">Цена едро</label><input type="number" step="0.01" class="form-control" id="wiz_wprice" placeholder="0,00"></div>
        </div>
        <div class="form-group"><label class="form-label">Баркод <span class="fl-hint">(празно = автоматично)</span></label><div style="display:flex;gap:6px;"><input type="text" class="form-control" id="wiz_barcode" placeholder="Сканирай или въведи" style="flex:1;"><button class="action-btn" onclick="openCamera('barcode_wiz')" style="flex-shrink:0;width:42px;"><svg width="18" height="18" viewBox="0 0 36 36" fill="none"><path d="M4 10V6a2 2 0 012-2h4" stroke="var(--indigo-400)" stroke-width="2" stroke-linecap="round"/><path d="M26 4h4a2 2 0 012 2v4" stroke="var(--indigo-400)" stroke-width="2" stroke-linecap="round"/><path d="M32 26v4a2 2 0 01-2 2h-4" stroke="var(--indigo-400)" stroke-width="2" stroke-linecap="round"/><path d="M10 32H6a2 2 0 01-2-2v-4" stroke="var(--indigo-400)" stroke-width="2" stroke-linecap="round"/><circle cx="18" cy="18" r="2" fill="var(--indigo-400)"/></svg></button></div></div>
        <div class="form-group"><label class="form-label">Доставчик <span class="fl-right" onclick="toggleInline('wiz_inlSup')">+ Нов</span></label><select class="form-control form-select" id="wiz_sup">${supOpts}</select><div class="inline-add" id="wiz_inlSup"><input type="text" placeholder="Име на доставчик" id="wiz_inlSupName"><button onclick="wizSaveInline('supplier')">Запази</button></div></div>
        <div class="form-group"><label class="form-label">Категория <span class="fl-right" onclick="toggleInline('wiz_inlCat')">+ Нова</span></label><select class="form-control form-select" id="wiz_cat">${catOpts}</select><div class="inline-add" id="wiz_inlCat"><input type="text" placeholder="Име на категория" id="wiz_inlCatName"><button onclick="wizSaveInline('category')">Запази</button></div></div>
        <button class="action-btn primary wide" onclick="wizGo(3)">Напред →</button>
        <button class="action-btn wide" onclick="wizGo(1)" style="margin-top:6px;">← Назад</button>
        </div></div>`;
    }

    // Step 3: Variants — TODO: full implementation with size/color search, price override
    function renderStep3(){
        if(STATE.productType==='single')return `<div class="wizard-page active"><div style="padding:0 16px;"><div style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:14px;">Единичен артикул — без варианти.</div><button class="action-btn primary wide" onclick="wizGo(4)">Напред →</button><button class="action-btn wide" onclick="wizGo(2)" style="margin-top:6px;">← Назад</button></div></div>`;
        // Variant mode
        let sizesHtml='';Object.keys(STATE.selectedSizes).forEach(s=>{const hp=STATE.selectedSizes[s]!==null;sizesHtml+=`<button class="size-chip selected ${hp?'has-price':''}" onclick="wizToggleSize('${s}')">${s}${hp?` <small style="color:#f59e0b;">€${STATE.selectedSizes[s]}</small>`:''}</button>`;});
        let colorsHtml='';COLOR_PALETTE.forEach(c=>{const sel=STATE.selectedColors.includes(c.name);colorsHtml+=`<div style="display:flex;flex-direction:column;align-items:center;gap:2px;width:36px;cursor:pointer;" onclick="wizToggleColor('${c.name}')"><div style="width:26px;height:26px;border-radius:50%;background:${c.hex};border:2px solid ${sel?'#fff':'transparent'};${sel?'box-shadow:0 0 0 2px var(--indigo-500),0 0 8px rgba(99,102,241,0.3);':''}"></div><div style="font-size:0.5rem;color:${sel?'var(--indigo-300)':'var(--text-secondary)'};">${c.name}</div></div>`;});
        return `<div class="wizard-page active"><div style="padding:0 16px;">
        <div style="font-size:0.7rem;color:var(--text-secondary);margin-bottom:10px;">Задръж размер/цвят = различна цена</div>
        <div style="margin-bottom:14px;"><div style="font-size:0.7rem;font-weight:600;color:var(--indigo-500);margin-bottom:6px;display:flex;align-items:center;gap:6px;">РАЗМЕР<div style="flex:1;height:1px;background:linear-gradient(to right,rgba(99,102,241,0.3),transparent);"></div></div>
        <div style="display:flex;gap:6px;margin-bottom:8px;"><input type="text" class="form-control" id="wizSizeSearch" placeholder="Търси или добави размер..." oninput="wizSearchSizes(this.value)" style="font-size:0.8rem;padding:8px 10px;"><button class="action-btn" onclick="wizAddCustomSize()" style="flex-shrink:0;">+</button></div>
        <div style="display:flex;flex-wrap:wrap;gap:5px;" id="wizSizeGrid">${sizesHtml}</div></div>
        <div style="margin-bottom:14px;"><div style="font-size:0.7rem;font-weight:600;color:var(--indigo-500);margin-bottom:6px;display:flex;align-items:center;gap:6px;">ЦВЯТ<div style="flex:1;height:1px;background:linear-gradient(to right,rgba(99,102,241,0.3),transparent);"></div></div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">${colorsHtml}</div></div>
        <button class="action-btn primary wide" onclick="wizGo(4)">Напред →</button>
        <button class="action-btn wide" onclick="wizGo(2)" style="margin-top:6px;">← Назад</button>
        </div></div>`;
    }

    // Step 4: Details
    function renderStep4(){
        const code=STATE.aiScanData?.code||'';const desc=STATE.aiScanData?.description||'';
        let unitOpts='';UNITS_JSON.forEach(u=>{unitOpts+=`<option value="${u}">${u}</option>`;});
        return `<div class="wizard-page active"><div style="padding:0 16px;">
        <div class="form-group"><label class="form-label">Артикулен код <span class="fl-hint">ако не попълниш — AI генерира</span></label><input type="text" class="form-control" id="wiz_code" value="${escHTML(code)}" placeholder="NAM90-BLK"></div>
        <div class="form-group"><label class="form-label">Мерна единица <span class="fl-right" onclick="toggleInline('wiz_inlUnit')">+ Нова</span></label><select class="form-control form-select" id="wiz_unit">${unitOpts}</select><div class="inline-add" id="wiz_inlUnit"><input type="text" placeholder="напр. кашон" id="wiz_inlUnitName"><button onclick="wizSaveInline('unit')">Запази</button></div></div>
        <div class="form-group"><label class="form-label">Локация</label><input type="text" class="form-control" id="wiz_loc" placeholder="Рафт А3"></div>
        <div class="form-group"><label class="form-label">SEO описание <span class="fl-ai">✦ AI генерира</span></label><textarea class="form-control" id="wiz_desc" rows="3" placeholder="AI ще попълни...">${escHTML(desc)}</textarea><div style="font-size:0.65rem;color:var(--text-secondary);margin-top:3px;">AI знае: име + размери + цветове + категория → пълно SEO</div></div>
        <button class="action-btn primary wide" onclick="wizGoToFinal()">Напред →</button>
        <button class="action-btn wide" onclick="wizGo(3)" style="margin-top:6px;">← Назад</button>
        </div></div>`;
    }

    // Step 5: Batch + AI Image + Print + Export + Save
    function renderStep5(){
        const name=document.getElementById('wiz_name')?.value||'Артикул';
        const price=parseFloat(document.getElementById('wiz_price')?.value||'0');
        const unit=document.getElementById('wiz_unit')?.value||'бр';
        const total=STATE.variantCombs.reduce((s,v)=>s+(v.qty||0),0);

        let batchHtml='';
        if(STATE.variantCombs.length<=1&&!STATE.variantCombs[0]?.size&&!STATE.variantCombs[0]?.color){
            batchHtml=`<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;"><div class="form-group"><label class="form-label">Начална наличност</label><input type="number" class="form-control" id="wiz_singleQty" min="0" value="0"></div><div class="form-group"><label class="form-label">Мин. наличност</label><input type="number" class="form-control" id="wiz_singleMin" min="0" value="0"></div></div>`;
        } else {
            batchHtml=`<div style="display:grid;grid-template-columns:1fr 42px 42px 42px;gap:3px;padding:4px 0;"><div style="font-size:0.6rem;color:var(--text-secondary);font-weight:600;">ВАРИАЦИЯ</div><div style="font-size:0.6rem;color:var(--text-secondary);font-weight:600;text-align:center;">БР.</div><div style="font-size:0.6rem;color:var(--text-secondary);font-weight:600;text-align:center;">МИН.</div><div style="font-size:0.6rem;color:#f59e0b;font-weight:600;text-align:center;">€</div></div><div style="height:1px;background:var(--border-subtle);margin-bottom:3px;"></div>`;
            let lastColor='';
            STATE.variantCombs.forEach((v,idx)=>{
                if(v.color&&v.color!==lastColor){const hex=COLOR_PALETTE.find(p=>p.name===v.color)?.hex||'#9ca3af';batchHtml+=`<div style="font-size:0.6rem;font-weight:600;color:var(--text-primary);padding:4px 0 2px;display:flex;align-items:center;gap:4px;"><div style="width:9px;height:9px;border-radius:50%;background:${hex};border:1px solid rgba(255,255,255,0.2);"></div>${v.color}</div>`;lastColor=v.color;}
                const hp=v.price!==null;
                batchHtml+=`<div style="display:grid;grid-template-columns:1fr 42px 42px 42px;gap:3px;padding:2px 0;align-items:center;"><div style="font-size:0.75rem;color:${hp?'#fbbf24':'var(--text-primary)'};padding-left:13px;${hp?'font-weight:600;':''}">${v.size||'—'}</div><input type="number" class="form-control" style="padding:4px 2px;text-align:center;font-size:0.8rem;" min="0" value="${v.qty||0}" onchange="STATE.variantCombs[${idx}].qty=parseInt(this.value)||0"><input type="number" class="form-control" style="padding:4px 2px;text-align:center;font-size:0.75rem;border-color:rgba(245,158,11,0.1);color:#f59e0b;" min="0" value="${v.min||0}" onchange="STATE.variantCombs[${idx}].min=parseInt(this.value)||0">${hp?`<input type="number" class="form-control" style="padding:4px 2px;text-align:center;font-size:0.7rem;border-color:rgba(245,158,11,0.15);color:#fbbf24;background:rgba(245,158,11,0.04);" value="${v.price}" onchange="STATE.variantCombs[${idx}].price=parseFloat(this.value)||null">`:`<div style="text-align:center;font-size:0.6rem;color:var(--text-secondary);">—</div>`}</div>`;
            });
            batchHtml+=`<div style="height:1px;background:var(--border-subtle);margin:6px 0;"></div><div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span style="font-size:0.75rem;color:var(--text-secondary);">Общо:</span><span style="font-size:0.9rem;font-weight:600;">${total} ${unit}</span></div>`;
        }

        return `<div class="wizard-page active"><div style="padding:0 16px;">
        <div style="font-size:0.9rem;font-weight:600;margin-bottom:2px;">${escHTML(name)}</div>
        <div style="font-size:0.7rem;color:var(--text-secondary);margin-bottom:10px;">Основна: <span style="color:var(--indigo-300);">${fmtPrice(price)}</span> · <span style="color:#8b5cf6;">${STATE.variantCombs.length} вариации</span></div>
        ${batchHtml}
        <div style="height:1px;background:linear-gradient(to right,transparent,var(--border-glow),transparent);margin:10px 0;"></div>
        <div style="display:flex;gap:4px;margin-bottom:6px;"><button class="action-btn" style="flex:1;font-size:0.65rem;${STATE.printFormat==='qr'?'background:rgba(99,102,241,0.1);border-color:var(--indigo-500);color:var(--indigo-300);':''}" onclick="STATE.printFormat='qr';renderWizard();">QR</button><button class="action-btn" style="flex:1;font-size:0.65rem;${STATE.printFormat==='barcode'?'background:rgba(99,102,241,0.1);border-color:var(--indigo-500);color:var(--indigo-300);':''}" onclick="STATE.printFormat='barcode';renderWizard();">Баркод</button><button class="action-btn" style="flex:1;font-size:0.65rem;${STATE.printFormat==='code'?'background:rgba(99,102,241,0.1);border-color:var(--indigo-500);color:var(--indigo-300);':''}" onclick="STATE.printFormat='code';renderWizard();">Арт. код</button></div>
        <button class="action-btn wide" onclick="showToast('Печат ${total} етикета (${STATE.printFormat})');" style="font-size:0.75rem;margin-bottom:8px;">🖨 Печат етикети (${total})</button>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px;margin-bottom:4px;"><button class="action-btn" style="font-size:0.65rem;color:#22c55e;" onclick="wizExportCSV()">CSV</button><button class="action-btn" style="font-size:0.65rem;color:#22c55e;" onclick="showToast('Excel скоро')">Excel</button><button class="action-btn" style="font-size:0.65rem;color:#ef4444;" onclick="showToast('PDF скоро')">PDF</button></div>
        <div style="font-size:0.6rem;color:var(--text-secondary);text-align:center;margin-bottom:10px;">Снимка + описание + размери → Shopify, WooCommerce</div>
        <button class="action-btn success wide" style="font-size:1rem;padding:14px;" onclick="wizSaveProduct()">✓ Запази</button>
        <button class="action-btn wide" onclick="wizGo(4)" style="margin-top:6px;">← Назад</button>
        </div></div>`;
    }

    // ============================================================
    // S18: WIZARD HELPERS
    // ============================================================
    function wizToggleSize(s){if(STATE.selectedSizes[s]!==undefined){delete STATE.selectedSizes[s];delete STATE.sizePrices[s];}else{STATE.selectedSizes[s]=null;}renderWizard();}
    function wizToggleColor(c){const idx=STATE.selectedColors.indexOf(c);if(idx>-1)STATE.selectedColors.splice(idx,1);else STATE.selectedColors.push(c);renderWizard();}
    function wizAddCustomSize(){const inp=document.getElementById('wizSizeSearch');const v=inp?.value.trim();if(!v)return;if(STATE.selectedSizes[v]===undefined)STATE.selectedSizes[v]=null;inp.value='';renderWizard();}
    async function wizSearchSizes(q){if(q.length<1)return;const d=await fetchJSON(`products.php?ajax=search_sizes&q=${encodeURIComponent(q)}`);if(d)d.forEach(s=>{if(STATE.selectedSizes[s]===undefined){STATE.selectedSizes[s]=null;}});renderWizard();}
    function toggleInline(id){document.getElementById(id)?.classList.toggle('open');}
    async function wizSaveInline(type){
        if(type==='supplier'){const n=document.getElementById('wiz_inlSupName')?.value.trim();if(!n)return;const d=await fetchJSON('products.php?ajax=add_supplier',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`name=${encodeURIComponent(n)}`});if(d?.id){ALL_SUPPLIERS_JSON.push({id:d.id,name:d.name});showToast('Доставчик добавен ✓','success');renderWizard();setTimeout(()=>{const sel=document.getElementById('wiz_sup');if(sel)sel.value=d.id;},100);}}
        else if(type==='category'){const n=document.getElementById('wiz_inlCatName')?.value.trim();if(!n)return;const d=await fetchJSON('products.php?ajax=add_category',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`name=${encodeURIComponent(n)}`});if(d?.id){ALL_CATEGORIES_JSON.push({id:d.id,name:d.name});showToast('Категория добавена ✓','success');renderWizard();setTimeout(()=>{const sel=document.getElementById('wiz_cat');if(sel)sel.value=d.id;},100);}}
        else if(type==='unit'){const u=document.getElementById('wiz_inlUnitName')?.value.trim();if(!u)return;const d=await fetchJSON('products.php?ajax=add_unit',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`unit=${encodeURIComponent(u)}`});if(d?.added){UNITS_JSON.push(d.added);showToast('Мерна единица добавена ✓','success');renderWizard();setTimeout(()=>{const sel=document.getElementById('wiz_unit');if(sel)sel.value=d.added;},100);}}
    }

    function wizGoToFinal(){
        const name=document.getElementById('wiz_name')?.value.trim();if(!name){showToast('Въведи наименование','error');wizGo(2);return;}
        const price=parseFloat(document.getElementById('wiz_price')?.value||'0');if(!price){showToast('Въведи цена','error');wizGo(2);return;}
        // Auto-generate code + SEO if empty
        wizAutoGenerate().then(()=>{wizBuildCombinations();wizGo(5);});
    }

    async function wizAutoGenerate(){
        const code=document.getElementById('wiz_code')?.value;const desc=document.getElementById('wiz_desc')?.value;const name=document.getElementById('wiz_name')?.value;
        if(!code&&name){try{const d=await fetchJSON('products.php?ajax=ai_code',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name})});if(d?.code)document.getElementById('wiz_code').value=d.code;}catch(e){}}
        if(!desc&&name){try{showToast('AI генерира описание...','info');const sizes=Object.keys(STATE.selectedSizes).join(', ');const colors=STATE.selectedColors.join(', ');const cat=document.getElementById('wiz_cat')?.selectedOptions[0]?.text||'';const sup=document.getElementById('wiz_sup')?.selectedOptions[0]?.text||'';const d=await fetchJSON('products.php?ajax=ai_description',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name,category:cat,sizes,colors,supplier:sup})});if(d?.description){document.getElementById('wiz_desc').value=d.description;showToast('Описание ✓','success');}}catch(e){}}
    }

    function wizBuildCombinations(){
        const sizes=Object.keys(STATE.selectedSizes);const colors=STATE.selectedColors;STATE.variantCombs=[];
        if(STATE.productType==='single'||(!sizes.length&&!colors.length)){STATE.variantCombs=[{size:null,color:null,price:null,qty:0,min:0}];return;}
        if(sizes.length&&colors.length){for(const c of colors)for(const s of sizes)STATE.variantCombs.push({size:s,color:c,price:STATE.sizePrices[s]||STATE.colorPrices[c]||null,qty:0,min:0});}
        else if(sizes.length){STATE.variantCombs=sizes.map(s=>({size:s,color:null,price:STATE.sizePrices[s]||null,qty:0,min:0}));}
        else{STATE.variantCombs=colors.map(c=>({size:null,color:c,price:STATE.colorPrices[c]||null,qty:0,min:0}));}
    }

    async function wizSaveProduct(){
        const name=document.getElementById('wiz_name')?.value.trim();if(!name){showToast('Въведи наименование','error');return;}
        showToast('Запазвам...','info');
        const payload={name,barcode:document.getElementById('wiz_barcode')?.value||'',retail_price:parseFloat(document.getElementById('wiz_price')?.value)||0,wholesale_price:parseFloat(document.getElementById('wiz_wprice')?.value)||0,cost_price:0,supplier_id:document.getElementById('wiz_sup')?.value||null,category_id:document.getElementById('wiz_cat')?.value||null,code:document.getElementById('wiz_code')?.value||'',unit:document.getElementById('wiz_unit')?.value||'бр',min_quantity:0,description:document.getElementById('wiz_desc')?.value||'',location:document.getElementById('wiz_loc')?.value||'',product_type:STATE.productType||'simple',sizes:Object.keys(STATE.selectedSizes),colors:STATE.selectedColors,variants:STATE.variantCombs};
        try{const r=await fetch('product-save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});const res=await r.json();if(res.success||res.id){showToast('Артикулът е добавен ✓','success');closeAddModal();loadCurrentScreen();}else{showToast(res.error||'Грешка','error');}}catch(e){showToast('Мрежова грешка','error');}
    }

    function wizExportCSV(){const name=document.getElementById('wiz_name')?.value||'';const code=document.getElementById('wiz_code')?.value||'';const desc=document.getElementById('wiz_desc')?.value||'';const price=document.getElementById('wiz_price')?.value||'0';const unit=document.getElementById('wiz_unit')?.value||'бр';const cat=document.getElementById('wiz_cat')?.selectedOptions[0]?.text||'';const sup=document.getElementById('wiz_sup')?.selectedOptions[0]?.text||'';let csv='Код,Наименование,Размер,Цвят,Цена,Мерна ед.,Категория,Доставчик,Описание,Бройки,Мин.нал.\n';STATE.variantCombs.forEach(v=>{csv+=`"${code}","${name}","${v.size||''}","${v.color||''}","${v.price||price}","${unit}","${cat}","${sup}","${desc}","${v.qty||0}","${v.min||0}"\n`;});const blob=new Blob(['\ufeff'+csv],{type:'text/csv;charset=utf-8;'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=`${code||'export'}.csv`;document.body.appendChild(a);a.click();a.remove();showToast('CSV изтеглен ✓','success');}

    // S18: AI Scan file handler
    async function handleAIScanFile(input){if(!input.files?.[0])return;showToast('AI анализира...','info');const reader=new FileReader();reader.onload=async e=>{const base64=e.target.result.split(',')[1];const d=await fetchJSON('products.php?ajax=ai_scan',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({image:base64})});if(d&&!d.error){STATE.aiScanData=d;showToast('AI разпозна ✓','success');document.getElementById('aiScanPreview').innerHTML=`<div style="background:rgba(99,102,241,0.05);border:1px solid var(--border-subtle);border-radius:12px;padding:12px;margin-bottom:12px;"><div style="font-size:0.65rem;font-weight:600;color:var(--indigo-500);margin-bottom:8px;">✦ AI РАЗПОЗНА</div><div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 10px;">${d.name?`<div><div style="font-size:0.6rem;color:var(--text-secondary);">Име</div><div style="font-size:0.8rem;font-weight:500;">${escHTML(d.name)}</div></div>`:''} ${d.retail_price?`<div><div style="font-size:0.6rem;color:var(--text-secondary);">Цена</div><div style="font-size:0.8rem;font-weight:500;">${fmtPrice(d.retail_price)}</div></div>`:''} ${d.category?`<div><div style="font-size:0.6rem;color:var(--text-secondary);">Категория</div><div style="font-size:0.8rem;color:var(--indigo-300);">${escHTML(d.category)}</div></div>`:''} ${d.supplier?`<div><div style="font-size:0.6rem;color:var(--text-secondary);">Доставчик</div><div style="font-size:0.8rem;color:var(--indigo-300);">${escHTML(d.supplier)}</div></div>`:''}</div>${d.description?`<div style="margin-top:6px;padding-top:6px;border-top:1px solid rgba(99,102,241,0.08);font-size:0.75rem;color:var(--text-secondary);line-height:1.5;">${escHTML(d.description)}</div>`:''}<div style="display:flex;gap:8px;margin-top:10px;"><button class="action-btn primary" style="flex:1;" onclick="wizGo(2)">✓ Потвърди</button><button class="action-btn" style="flex:1;" onclick="wizGo(2)">✏ Коригирай</button></div></div>`;if(d.sizes?.length){d.sizes.forEach(s=>{STATE.selectedSizes[s]=null;});if(!STATE.productType)STATE.productType='variant';}if(d.colors?.length)STATE.selectedColors=d.colors;}else{showToast('AI не разпозна — попълни ръчно','error');}};reader.readAsDataURL(input.files[0]);}

    // S18: Voice for wizard
    function openWizardVoice(){if(!('webkitSpeechRecognition' in window)&&!('SpeechRecognition' in window)){showToast('Не се поддържа','error');return;}document.getElementById('voiceOverlay').classList.add('open');document.getElementById('voiceTranscript').textContent='';const SR=window.SpeechRecognition||window.webkitSpeechRecognition;const rec=new SR();rec.lang='bg-BG';rec.continuous=false;rec.onresult=async e=>{const t=e.results[0][0].transcript;document.getElementById('voiceTranscript').textContent='"'+t+'"';showToast('AI обработва...','info');const d=await fetchJSON('products.php?ajax=ai_voice_fill',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({text:t})});closeVoiceOverlay();if(d&&!d.error){STATE.aiScanData=d;showToast('AI попълни ✓','success');renderWizard();}};rec.onerror=()=>closeVoiceOverlay();rec.start();}

    // ============================================================
    // CAMERA & BARCODE (S17 base + S18 barcode_wiz mode)
    // ============================================================
    async function openCamera(mode){STATE.cameraMode=mode;const ov=document.getElementById('cameraOverlay');const vid=document.getElementById('cameraVideo');ov.classList.add('open');try{const stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment',width:{ideal:1280}}});STATE.cameraStream=stream;vid.srcObject=stream;if(mode==='scan'||mode==='barcode_wiz'){document.getElementById('scanLine').style.display='block';document.getElementById('captureBtn').style.display='none';startBarcodeScanning(vid);}else{document.getElementById('scanLine').style.display='none';document.getElementById('captureBtn').style.display='';}}catch(e){showToast('Камерата не е достъпна','error');closeCamera();}}
    function closeCamera(){document.getElementById('cameraOverlay').classList.remove('open');if(STATE.cameraStream){STATE.cameraStream.getTracks().forEach(t=>t.stop());STATE.cameraStream=null;}if(STATE.barcodeInterval){clearInterval(STATE.barcodeInterval);STATE.barcodeInterval=null;}document.getElementById('scanLine').style.display='none';}
    function startBarcodeScanning(vid){if(!('BarcodeDetector' in window)){showToast('Баркод скенерът не е поддържан','error');closeCamera();return;}STATE.barcodeDetector=new BarcodeDetector({formats:['ean_13','ean_8','code_128','code_39','qr_code','upc_a','upc_e']});STATE.barcodeInterval=setInterval(async()=>{try{const bc=await STATE.barcodeDetector.detect(vid);if(bc.length>0){clearInterval(STATE.barcodeInterval);playBeep();showGreenFlash();const code=bc[0].rawValue;if(STATE.cameraMode==='barcode_wiz'){document.getElementById('wiz_barcode').value=code;closeCamera();}else{const d=await fetchJSON(`products.php?ajax=barcode&code=${encodeURIComponent(code)}&store_id=${STATE.storeId}`);closeCamera();if(d&&!d.error)openProductDetail(d.id);else showToast(`Баркод ${code} не е намерен`,'info');}}}catch(e){}},300);}
    function playBeep(){try{const c=new(window.AudioContext||window.webkitAudioContext)();const o=c.createOscillator();const g=c.createGain();o.connect(g);g.connect(c.destination);o.frequency.value=1200;g.gain.value=0.3;o.start();o.stop(c.currentTime+0.15);}catch(e){}}
    function showGreenFlash(){const f=document.createElement('div');f.className='green-flash';document.body.appendChild(f);setTimeout(()=>f.remove(),500);}
    function capturePhoto(){/* Used for AI scan via camera — fallback */document.getElementById('aiScanFileInput').click();closeCamera();}

    // Edit product (reuses wizard)
    async function editProduct(id){closeDrawer('detail');const d=await fetchJSON(`products.php?ajax=product_detail&id=${id}`);if(!d||d.error)return;const p=d.product;STATE.editProductId=id;STATE.productType='simple';STATE.aiScanData={name:p.name,retail_price:p.retail_price,code:p.code,description:p.description,supplier:p.supplier_name,category:p.category_name};document.getElementById('modalTitle').textContent='Редактирай';STATE.wizStep=2;renderWizard();document.getElementById('addModal').classList.add('open');document.body.style.overflow='hidden';setTimeout(()=>{const bc=document.getElementById('wiz_barcode');if(bc)bc.value=p.barcode||'';const wp=document.getElementById('wiz_wprice');if(wp)wp.value=p.wholesale_price||'';const loc=document.getElementById('wiz_loc');if(loc)loc.value=p.location||'';const unit=document.getElementById('wiz_unit');if(unit)unit.value=p.unit||'бр';},100);}

    // ============================================================
    // LOAD + INIT
    // ============================================================
    function loadCurrentScreen(){switch(STATE.screen){case'home':loadHomeScreen();break;case'suppliers':loadSuppliers();break;case'categories':loadCategories();break;case'products':loadProductsList();break;}}
    document.addEventListener('click',e=>{if(!e.target.closest('[onclick*="toggleSort"]')&&!e.target.closest('.sort-dropdown'))document.getElementById('sortDropdown')?.classList.remove('open');});
    document.addEventListener('DOMContentLoaded',loadCurrentScreen);
    </script>
</body>
</html>

    <script>
    // ============================================================
    // STATE (S17 base + S18 additions)
    // ============================================================
    const STATE = {
        storeId: <?= (int)$store_id ?>,
        screen: '<?= $screen ?>',
        supId: <?= $sup_id ? $sup_id : 'null' ?>,
        catId: <?= $cat_id ? $cat_id : 'null' ?>,
        canAdd: <?= $can_add ? 'true' : 'false' ?>,
        canSeeCost: <?= $can_see_cost ? 'true' : 'false' ?>,
        canSeeMargin: <?= $can_see_margin ? 'true' : 'false' ?>,
        currentSort: 'name', currentFilter: 'all', currentPage: 1,
        editProductId: null, productType: null,
        selectedSizes: [], selectedColors: [],
        sizePrices: {}, colorPrices: {}, priceLevel: null,
        cameraMode: null, cameraStream: null, barcodeDetector: null, barcodeInterval: null,
        recognition: null, isListening: false, studioProductId: null,
        aiAssistHistory: []
    };
    const WIZ_LABELS = ['Вид','AI разпознаване','Основна информация','Варианти','Детайли','Преглед и запис'];

    // ============================================================
    // UTILITIES (S17 unchanged)
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
    // SCREEN NAVIGATION (S17 unchanged)
    // ============================================================
    function goScreen(s,p={}){let u=`products.php?screen=${s}`;if(p.sup)u+=`&sup=${p.sup}`;if(p.cat)u+=`&cat=${p.cat}`;window.location.href=u;}
    function switchStore(s){STATE.storeId=parseInt(s);loadCurrentScreen();}

    // ============================================================
    // HOME SCREEN (S17 unchanged)
    // ============================================================
    let homeTab='all',homePageNum=1;
    async function loadHomeScreen(){const d=await fetchJSON(`products.php?ajax=home_stats&store_id=${STATE.storeId}`);if(!d)return;if(STATE.canSeeMargin){document.getElementById('statCapital').textContent=fmtPrice(d.capital);document.getElementById('statMargin').textContent=d.avg_margin!==null?d.avg_margin+'%':'—';}else{const sp=document.getElementById('statProducts'),su=document.getElementById('statUnits');if(sp)sp.textContent=fmtNum(d.counts?.total_products);if(su)su.textContent=fmtNum(d.counts?.total_units);}document.getElementById('countAll').textContent=d.counts?.total_products||0;document.getElementById('countLow').textContent=d.low_stock?.length||0;document.getElementById('countOut').textContent=d.out_of_stock?.length||0;
    if(d.zombies?.length>0){document.getElementById('collapseZombie').style.display='block';document.getElementById('zombieCount').textContent=d.zombies.length;document.getElementById('zombieList').innerHTML=d.zombies.map(p=>`<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;"><div class="stock-bar red"></div><div class="pc-thumb">${p.image?`<img src="${p.image}">`:'💀'}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta"><span>${p.days_stale} дни без продажба</span></div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.retail_price)}</div><div class="pc-stock out">${p.qty} бр.</div></div></div>`).join('');}
    if(d.low_stock?.length>0){document.getElementById('collapseLow').style.display='block';document.getElementById('lowCount').textContent=d.low_stock.length;document.getElementById('lowList').innerHTML=d.low_stock.map(p=>`<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;"><div class="stock-bar yellow"></div><div class="pc-thumb">${p.image?`<img src="${p.image}">`:'⚠️'}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta"><span>Минимум: ${p.min_quantity}</span></div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.retail_price)}</div><div class="pc-stock low">${p.qty} бр.</div></div></div>`).join('');}
    if(d.top_sellers?.length>0){document.getElementById('collapseTop').style.display='block';document.getElementById('topCount').textContent=d.top_sellers.length;document.getElementById('topList').innerHTML=d.top_sellers.map((p,i)=>`<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;"><div class="stock-bar green"></div><div class="pc-thumb" style="font-size:1.2rem;font-weight:700;color:var(--indigo-300);">#${i+1}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta"><span>${p.sold_qty} продадени</span></div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.revenue)}</div></div></div>`).join('');}
    if(d.slow_movers?.length>0){document.getElementById('collapseSlow').style.display='block';document.getElementById('slowCount').textContent=d.slow_movers.length;document.getElementById('slowList').innerHTML=d.slow_movers.map(p=>`<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;"><div class="stock-bar yellow"></div><div class="pc-thumb">${p.image?`<img src="${p.image}">`:'🐌'}</div><div class="pc-info"><div class="pc-name">${escHTML(p.name)}</div><div class="pc-meta"><span>${p.days_stale} дни</span></div></div><div class="pc-right"><div class="pc-price">${fmtPrice(p.retail_price)}</div><div class="pc-stock low">${p.qty} бр.</div></div></div>`).join('');}
    loadHomeProducts();}
    async function loadHomeProducts(){const f=homeTab==='all'?'':`&filter=${homeTab}`;const d=await fetchJSON(`products.php?ajax=products&store_id=${STATE.storeId}${f}&page=${homePageNum}&sort=${STATE.currentSort}`);if(!d)return;const el=document.getElementById('homeProductsList');el.innerHTML=d.products.length===0?`<div class="empty-state"><div class="es-icon">📦</div><div class="es-text">Няма артикули</div></div>`:d.products.map(productCardHTML).join('');renderPagination('homePagination',d.page,d.pages,p=>{homePageNum=p;loadHomeProducts();});}
    function setHomeTab(t,b){homeTab=t;homePageNum=1;document.querySelectorAll('.tabs-row .tab-btn').forEach(x=>x.classList.remove('active'));b.classList.add('active');loadHomeProducts();}

    // ============================================================
    // SUPPLIERS, CATEGORIES, PRODUCTS (S17 unchanged)
    // ============================================================
    async function loadSuppliers(){const d=await fetchJSON(`products.php?ajax=suppliers&store_id=${STATE.storeId}`);if(!d)return;const el=document.getElementById('supplierCards');if(!d.length){el.innerHTML=`<div class="empty-state" style="width:100%;"><div class="es-icon">📦</div><div class="es-text">Няма доставчици</div></div>`;return;}el.innerHTML=d.map(s=>{const ok=s.product_count-s.low_count-s.out_count;return`<div class="supplier-card" onclick="goScreen('categories',{sup:${s.id}})"><div class="sc-name">${escHTML(s.name)}</div><div class="sc-count">${s.product_count} арт. · ${fmtNum(s.total_stock)} бр.</div><div class="sc-badges">${ok>0?`<span class="sc-badge ok">✓ ${ok}</span>`:''}${s.low_count>0?`<span class="sc-badge low">↓ ${s.low_count}</span>`:''}${s.out_count>0?`<span class="sc-badge out">✕ ${s.out_count}</span>`:''}</div><div class="sc-arrow">›</div></div>`;}).join('');const st=document.getElementById('supplierStats');st.innerHTML=[...d].sort((a,b)=>b.total_stock-a.total_stock).slice(0,5).map((s,i)=>`<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(99,102,241,0.08);font-size:0.8rem;"><span style="color:var(--indigo-400);font-weight:700;">#${i+1}</span> ${escHTML(s.name)}<span style="font-weight:600;">${fmtNum(s.total_stock)} бр.</span></div>`).join('');}
    async function loadCategories(){const sp=STATE.supId?`&sup=${STATE.supId}`:'';const d=await fetchJSON(`products.php?ajax=categories&store_id=${STATE.storeId}${sp}`);if(!d)return;if(STATE.supId){const el=document.getElementById('categoryList');el.innerHTML=d.length===0?`<div class="empty-state" style="padding:20px;"><div class="es-text">Няма категории</div></div>`:d.map(c=>`<div class="cat-list-item" onclick="goScreen('products',{sup:${STATE.supId},cat:${c.id}})"><div class="cli-left"><div class="cli-icon">🏷</div><div><div class="cli-name">${escHTML(c.name)}</div><div class="cli-count">${c.product_count} арт. · ${fmtNum(c.total_stock)} бр.</div></div></div><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-secondary);"><polyline points="9 18 15 12 9 6"/></svg></div>`).join('');}else{const el=document.getElementById('categoryCards');el.innerHTML=d.length===0?`<div class="empty-state" style="width:100%;"><div class="es-text">Няма категории</div></div>`:d.map(c=>`<div class="category-card" onclick="goScreen('products',{cat:${c.id}})"><div class="cc-name">${escHTML(c.name)}</div><div class="cc-info">${c.product_count} арт.${c.supplier_count?' · '+c.supplier_count+' дост.':''} · ${fmtNum(c.total_stock)} бр.</div></div>`).join('');}}
    async function loadProductsList(){let p=`store_id=${STATE.storeId}&sort=${STATE.currentSort}&page=${STATE.currentPage}`;if(STATE.supId)p+=`&sup=${STATE.supId}`;if(STATE.catId)p+=`&cat=${STATE.catId}`;if(STATE.currentFilter!=='all')p+=`&filter=${STATE.currentFilter}`;const d=await fetchJSON(`products.php?ajax=products&${p}`);if(!d)return;const el=document.getElementById('productsList');el.innerHTML=d.products.length===0?`<div class="empty-state"><div class="es-icon">📋</div><div class="es-text">Няма артикули</div></div>`:d.products.map(productCardHTML).join('');renderPagination('productsPagination',d.page,d.pages,pg=>{STATE.currentPage=pg;loadProductsList();});}

    // ============================================================
    // PAGINATION, COLLAPSE, SORT, SEARCH (S17 unchanged)
    // ============================================================
    function renderPagination(cid,cur,tot,cb){const el=document.getElementById(cid);if(tot<=1){el.innerHTML='';return;}let h='';if(cur>1)h+=`<button class="page-btn" data-p="${cur-1}">‹</button>`;for(let i=Math.max(1,cur-2);i<=Math.min(tot,cur+2);i++)h+=`<button class="page-btn ${i===cur?'active':''}" data-p="${i}">${i}</button>`;if(cur<tot)h+=`<button class="page-btn" data-p="${cur+1}">›</button>`;el.innerHTML=h;el.querySelectorAll('.page-btn').forEach(b=>b.addEventListener('click',()=>cb(parseInt(b.dataset.p))));}
    function toggleCollapse(id){const m={zombie:'collapseZombie',low:'collapseLow',top:'collapseTop',slow:'collapseSlow'};const s=document.getElementById(m[id]);s.querySelector('.collapse-header').classList.toggle('open');s.querySelector('.collapse-body').classList.toggle('open');}
    function toggleSort(){document.getElementById('sortDropdown').classList.toggle('open');}
    function setSort(s){STATE.currentSort=s;document.querySelectorAll('.sort-option').forEach(o=>o.classList.toggle('active',o.dataset.sort===s));document.getElementById('sortDropdown').classList.remove('open');STATE.screen==='products'?loadProductsList():loadHomeProducts();}
    let searchTimeout;document.getElementById('searchInput').addEventListener('input',function(){clearTimeout(searchTimeout);const q=this.value.trim();if(q.length<1){loadCurrentScreen();return;}searchTimeout=setTimeout(async()=>{const d=await fetchJSON(`products.php?ajax=search&q=${encodeURIComponent(q)}&store_id=${STATE.storeId}`);if(!d)return;const el=document.getElementById(STATE.screen==='products'?'productsList':'homeProductsList');el.innerHTML=d.length===0?`<div class="empty-state"><div class="es-icon">🔍</div><div class="es-text">Нищо за "${escHTML(q)}"</div></div>`:d.map(p=>productCardHTML({...p,store_stock:p.total_stock})).join('');},300);});

    // ============================================================
    // PRODUCT DETAIL & AI DRAWER (S17 unchanged)
    // ============================================================
    async function openProductDetail(id){openDrawer('detail');document.getElementById('detailBody').innerHTML='<div style="text-align:center;padding:20px;"><div class="skeleton" style="width:60%;height:20px;margin:0 auto 12px;"></div></div>';const d=await fetchJSON(`products.php?ajax=product_detail&id=${id}`);if(!d||d.error){showToast('Грешка','error');closeDrawer('detail');return;}const p=d.product;document.getElementById('detailTitle').textContent=p.name;let h='';if(p.image)h+=`<div style="text-align:center;margin-bottom:12px;"><img src="${p.image}" style="max-width:200px;border-radius:12px;border:1px solid var(--border-subtle);"></div>`;h+=`<div class="detail-row"><span class="detail-label">Код</span><span class="detail-value">${escHTML(p.code)||'—'}</span></div>`;h+=`<div class="detail-row"><span class="detail-label">Цена</span><span class="detail-value">${fmtPrice(p.retail_price)}</span></div>`;if(STATE.canSeeCost)h+=`<div class="detail-row"><span class="detail-label">Доставна</span><span class="detail-value">${fmtPrice(p.cost_price)}</span></div>`;h+=`<div class="detail-row"><span class="detail-label">Доставчик</span><span class="detail-value">${escHTML(p.supplier_name)||'—'}</span></div>`;h+=`<div class="detail-row"><span class="detail-label">Категория</span><span class="detail-value">${escHTML(p.category_name)||'—'}</span></div>`;h+=`<div style="margin-top:14px;font-size:0.7rem;font-weight:600;color:var(--text-secondary);text-transform:uppercase;margin-bottom:6px;">Наличност по обекти</div>`;d.stocks.forEach(s=>h+=`<div class="store-stock-row"><span style="font-size:0.8rem;">${escHTML(s.store_name)}</span><span style="font-size:0.85rem;font-weight:700;color:${s.qty>0?'#22c55e':'#ef4444'};">${s.qty} бр.</span></div>`);h+=`<div class="action-grid">`;if(STATE.canAdd)h+=`<button class="action-btn" onclick="editProduct(${p.id})">✏️ Редактирай</button>`;h+=`<button class="action-btn" onclick="openAIDrawer(${p.id})">✦ AI Съвет</button>`;h+=`<button class="action-btn primary" onclick="window.location='sale.php?product=${p.id}'">💰 Продажба</button>`;h+=`</div>`;document.getElementById('detailBody').innerHTML=h;}
    async function openAIDrawer(id){closeDrawer('detail');setTimeout(()=>openDrawer('ai'),350);document.getElementById('aiBody').innerHTML='<div style="text-align:center;padding:20px;">✦ Анализирам...</div>';const d=await fetchJSON(`products.php?ajax=ai_analyze&id=${id}`);if(!d){return;}let h='';if(!d.analysis.length)h=`<div class="ai-insight info"><span class="ai-icon">✓</span><span class="ai-text">Всичко е наред.</span></div>`;else d.analysis.forEach(a=>h+=`<div class="ai-insight ${a.severity}"><span class="ai-icon">${a.icon}</span><span class="ai-text">${a.text}</span></div>`);h+=`<div style="margin-top:12px;"><a class="ai-deeplink" href="chat.php?ctx=product&id=${id}">💬 Попитай AI</a></div>`;document.getElementById('aiBody').innerHTML=h;}

    // ============================================================
    // DRAWER MANAGEMENT (S17 unchanged + aiAssist)
    // ============================================================
    function openDrawer(n){document.getElementById(n+'Overlay').classList.add('open');document.getElementById(n+'Drawer').classList.add('open');document.body.style.overflow='hidden';}
    function closeDrawer(n){document.getElementById(n+'Overlay').classList.remove('open');document.getElementById(n+'Drawer').classList.remove('open');document.body.style.overflow='';}
    ['detail','ai','filter','aiAssist'].forEach(n=>{const d=document.getElementById(n+'Drawer');if(!d)return;let sy=0,cy=0,drag=false;d.addEventListener('touchstart',e=>{if(e.target.closest('.drawer-body')?.scrollTop>0)return;sy=e.touches[0].clientY;drag=true;},{passive:true});d.addEventListener('touchmove',e=>{if(!drag)return;cy=e.touches[0].clientY-sy;if(cy>0)d.style.transform=`translateY(${cy}px)`;},{passive:true});d.addEventListener('touchend',()=>{if(!drag)return;drag=false;if(cy>100)closeDrawer(n);d.style.transform='';cy=0;});});

    // ============================================================
    // S18 NEW: AI ASSISTANT (in-module)
    // ============================================================
    function openAIAssist(){openDrawer('aiAssist');}
    async function askAI(){const inp=document.getElementById('aiAssistQ');const q=inp.value.trim();if(!q)return;inp.value='';const chat=document.getElementById('aiAssistChat');chat.innerHTML+=`<div class="ai-assist-msg user">${escHTML(q)}</div>`;chat.innerHTML+=`<div class="ai-assist-msg ai" id="aiTyping">✦ Мисля...</div>`;chat.scrollTop=chat.scrollHeight;const d=await fetchJSON('products.php?ajax=ai_assist',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({question:q})});const typing=document.getElementById('aiTyping');if(d?.response){typing.textContent=d.response;typing.id='';}else{typing.textContent='Не успях да отговоря.';typing.id='';}chat.scrollTop=chat.scrollHeight;}

    // ============================================================
    // S18 NEW: INFO OVERLAY
    // ============================================================
    function openInfoOverlay(){document.getElementById('infoOverlay').classList.add('open');}
    function closeInfoOverlay(){document.getElementById('infoOverlay').classList.remove('open');}

    // ============================================================
    // FILTER (S17 unchanged)
    // ============================================================
    function openFilterDrawer(){openDrawer('filter');}
    function toggleFilterChip(el,t){if(t==='stock'){el.parentElement.querySelectorAll('.filter-chip').forEach(c=>c.classList.remove('selected'));el.classList.add('selected');}else el.classList.toggle('selected');}
    function applyFilters(){const c=document.querySelector('#filterCats .filter-chip.selected');const s=document.querySelector('#filterSups .filter-chip.selected');let u='products.php?screen=products';if(s)u+=`&sup=${s.dataset.sup}`;if(c)u+=`&cat=${c.dataset.cat}`;const stk=document.querySelector('[data-stock].selected');if(stk&&stk.dataset.stock!=='all')u+=`&filter=${stk.dataset.stock}`;window.location.href=u;}
    function clearFilters(){document.querySelectorAll('.filter-chip').forEach(c=>c.classList.remove('selected'));document.getElementById('priceFrom').value='';document.getElementById('priceTo').value='';}

    // ============================================================
    // S18 NEW: 6-STEP ADD WIZARD
    // ============================================================
    function openAddModal(){STATE.editProductId=null;STATE.productType=null;STATE.selectedSizes=[];STATE.selectedColors=[];STATE.sizePrices={};STATE.colorPrices={};STATE.priceLevel=null;document.getElementById('modalTitle').textContent='Нов артикул';['addName','addBarcode','addRetailPrice','addWholesalePrice','addCode','addLocation','addDescription'].forEach(id=>{const e=document.getElementById(id);if(e)e.value='';});['addSupplier','addCategory'].forEach(id=>{const e=document.getElementById(id);if(e)e.selectedIndex=0;});document.getElementById('addUnit').value='бр';document.getElementById('addMinQty').value='0';document.getElementById('tcSingle')?.classList.remove('active');document.getElementById('tcVariant')?.classList.remove('active');document.getElementById('btn0n').style.display='none';document.querySelectorAll('.size-chip').forEach(c=>c.classList.remove('selected'));document.querySelectorAll('#colorDots > div').forEach(c=>{c.querySelector('div').style.borderColor=c.dataset.color==='Бял'?'rgba(255,255,255,0.3)':'transparent';c.classList.remove('sel');});goWizardStep(0);document.getElementById('addModal').classList.add('open');document.body.style.overflow='hidden';}
    function closeAddModal(){document.getElementById('addModal').classList.remove('open');document.body.style.overflow='';}
    function goWizardStep(s){document.querySelectorAll('.wizard-page').forEach(p=>p.classList.remove('active'));document.querySelector(`.wizard-page[data-page="${s}"]`).classList.add('active');document.querySelectorAll('.wiz-step').forEach((e,i)=>{e.classList.remove('active','done');if(i<s)e.classList.add('done');else if(i===s)e.classList.add('active');});document.getElementById('wizLabel').innerHTML=(s+1)+' · <span>'+WIZ_LABELS[s]+'</span>';document.getElementById('addModal').querySelector('.modal-body').scrollTop=0;if(s===4)autoGenerateDetails();if(s===5)renderFinalStep();}
    function selectType(t){STATE.productType=t;document.getElementById('tcSingle').style.borderColor=t==='single'?'var(--indigo-500)':'';document.getElementById('tcSingle').style.boxShadow=t==='single'?'0 0 20px rgba(99,102,241,0.15)':'';document.getElementById('tcVariant').style.borderColor=t==='variant'?'var(--indigo-500)':'';document.getElementById('tcVariant').style.boxShadow=t==='variant'?'0 0 20px rgba(99,102,241,0.15)':'';document.getElementById('btn0n').style.display='block';}

    // Variant toggles
    function toggleSize(el){el.classList.toggle('selected');const sz=el.dataset.size;const idx=STATE.selectedSizes.indexOf(sz);if(idx>-1)STATE.selectedSizes.splice(idx,1);else STATE.selectedSizes.push(sz);}
    function addCustomSize(){const inp=document.getElementById('customSize');const v=inp.value.trim();if(!v||STATE.selectedSizes.includes(v))return;STATE.selectedSizes.push(v);const c=document.createElement('button');c.className='size-chip selected';c.dataset.size=v;c.textContent=v;c.onclick=function(){toggleSize(this);};document.getElementById('sizeChips').appendChild(c);inp.value='';}
    function toggleColor(el){const c=el.dataset.color;const dot=el.querySelector('div');if(el.classList.contains('sel')){el.classList.remove('sel');dot.style.borderColor=c==='Бял'?'rgba(255,255,255,0.3)':'transparent';dot.style.boxShadow='';STATE.selectedColors=STATE.selectedColors.filter(x=>x!==c);}else{el.classList.add('sel');dot.style.borderColor='var(--indigo-400)';dot.style.boxShadow='0 0 8px rgba(99,102,241,0.4)';STATE.selectedColors.push(c);}}

    // Inline add
    function toggleInline(id){const el=document.getElementById(id);el.classList.toggle('open');if(el.classList.contains('open'))el.querySelector('input')?.focus();}
    async function saveInline(type){if(type==='supplier'){const n=document.getElementById('inlSupName').value.trim();if(!n)return;const d=await fetchJSON('products.php?ajax=add_supplier',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`name=${encodeURIComponent(n)}`});if(d?.id){const s=document.getElementById('addSupplier');s.appendChild(new Option(d.name,d.id,true,true));document.getElementById('inlSupName').value='';document.getElementById('inlSup').classList.remove('open');showToast('Доставчик добавен ✓','success');}}else if(type==='category'){const n=document.getElementById('inlCatName').value.trim();if(!n)return;const d=await fetchJSON('products.php?ajax=add_category',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`name=${encodeURIComponent(n)}`});if(d?.id){const s=document.getElementById('addCategory');s.appendChild(new Option(d.name,d.id,true,true));document.getElementById('inlCatName').value='';document.getElementById('inlCat').classList.remove('open');showToast('Категория добавена ✓','success');}}else if(type==='unit'){const u=document.getElementById('inlUnitName').value.trim();if(!u)return;const d=await fetchJSON('products.php?ajax=add_unit',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`unit=${encodeURIComponent(u)}`});if(d?.added){const s=document.getElementById('addUnit');s.appendChild(new Option(d.added,d.added,true,true));document.getElementById('inlUnitName').value='';document.getElementById('inlUnit').classList.remove('open');showToast('Мерна ед. добавена ✓','success');}}}

    // Auto-generate details (step 4)
    async function autoGenerateDetails(){const name=document.getElementById('addName').value;if(!name)return;const code=document.getElementById('addCode').value;if(!code){try{const d=await fetchJSON('products.php?ajax=ai_code',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name})});if(d?.code)document.getElementById('addCode').value=d.code;}catch(e){}}const desc=document.getElementById('addDescription').value;if(!desc){try{showToast('AI генерира описание...','info');const cat=document.getElementById('addCategory')?.selectedOptions[0]?.text||'';const sup=document.getElementById('addSupplier')?.selectedOptions[0]?.text||'';const d=await fetchJSON('products.php?ajax=ai_description',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name,category:cat,supplier:sup,sizes:STATE.selectedSizes.join(', '),colors:STATE.selectedColors.join(', ')})});if(d?.description){document.getElementById('addDescription').value=d.description;showToast('Описание ✓','success');}}catch(e){}}}

    // Final step (step 5)
    function renderFinalStep(){const name=document.getElementById('addName').value||'Артикул';const price=parseFloat(document.getElementById('addRetailPrice').value||0);document.getElementById('batchSummary').innerHTML=`<div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);margin-bottom:2px;">${escHTML(name)}</div><div style="font-size:0.75rem;color:var(--text-secondary);">Основна: ${fmtPrice(price)} · ${STATE.selectedSizes.length||1} вариации</div>`;document.getElementById('batchGrid').innerHTML=`<div class="form-row" style="margin-top:10px;"><div class="form-group"><label class="form-label">Начална наличност</label><input type="number" class="form-control" id="initialQty" min="0" value="0"></div><div class="form-group"><label class="form-label">Мин. наличност</label><input type="number" class="form-control" id="initialMinQty" min="0" value="0"></div></div>`;document.getElementById('finalActions').innerHTML=`<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px;margin-bottom:8px;"><button class="action-btn" onclick="showToast('CSV експорт...','info')" style="font-size:0.7rem;color:#22c55e;">CSV</button><button class="action-btn" onclick="showToast('Excel експорт...','info')" style="font-size:0.7rem;color:#22c55e;">Excel</button><button class="action-btn" onclick="showToast('PDF експорт...','info')" style="font-size:0.7rem;color:#ef4444;">PDF</button></div><div style="font-size:0.65rem;color:var(--text-secondary);text-align:center;margin-bottom:8px;">Снимка + описание + размери → Shopify, WooCommerce</div>`;}

    // Save product
    async function saveProduct(){const btn=document.getElementById('saveProductBtn');btn.disabled=true;btn.textContent='Запазвам...';const payload={id:STATE.editProductId,name:document.getElementById('addName').value.trim(),barcode:document.getElementById('addBarcode').value.trim(),retail_price:parseFloat(document.getElementById('addRetailPrice').value)||0,wholesale_price:parseFloat(document.getElementById('addWholesalePrice')?.value)||0,cost_price:0,supplier_id:document.getElementById('addSupplier').value||null,category_id:document.getElementById('addCategory').value||null,code:document.getElementById('addCode').value.trim(),unit:document.getElementById('addUnit').value,min_quantity:parseInt(document.getElementById('addMinQty').value)||0,description:document.getElementById('addDescription').value.trim(),location:document.getElementById('addLocation').value.trim(),product_type:STATE.productType||'simple',sizes:STATE.selectedSizes,colors:STATE.selectedColors};if(!payload.name){showToast('Въведи име','error');btn.disabled=false;btn.textContent='✓ Запази';return;}try{const r=await fetch('product-save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});const res=await r.json();if(res.success){showToast(STATE.editProductId?'Обновен ✓':'Добавен ✓','success');closeAddModal();loadCurrentScreen();}else showToast(res.error||'Грешка','error');}catch(e){showToast('Грешка','error');}btn.disabled=false;btn.textContent='✓ Запази';}

    // Edit product
    async function editProduct(id){closeDrawer('detail');const d=await fetchJSON(`products.php?ajax=product_detail&id=${id}`);if(!d||d.error)return;const p=d.product;STATE.editProductId=id;document.getElementById('modalTitle').textContent='Редактирай';document.getElementById('addName').value=p.name||'';document.getElementById('addBarcode').value=p.barcode||'';document.getElementById('addRetailPrice').value=p.retail_price||'';if(document.getElementById('addWholesalePrice'))document.getElementById('addWholesalePrice').value=p.wholesale_price||'';document.getElementById('addSupplier').value=p.supplier_id||'';document.getElementById('addCategory').value=p.category_id||'';document.getElementById('addCode').value=p.code||'';document.getElementById('addUnit').value=p.unit||'бр';document.getElementById('addMinQty').value=p.min_quantity||0;document.getElementById('addDescription').value=p.description||'';STATE.productType='single';goWizardStep(2);document.getElementById('addModal').classList.add('open');document.body.style.overflow='hidden';}

    // ============================================================
    // CAMERA & BARCODE (S17 unchanged)
    // ============================================================
    async function openCamera(mode){STATE.cameraMode=mode;const ov=document.getElementById('cameraOverlay');const vid=document.getElementById('cameraVideo');ov.classList.add('open');try{const stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment',width:{ideal:1280}}});STATE.cameraStream=stream;vid.srcObject=stream;if(mode==='scan'||mode==='barcode_add'){document.getElementById('scanLine').style.display='block';document.getElementById('captureBtn').style.display='none';startBarcodeScanning(vid);}else{document.getElementById('scanLine').style.display='none';document.getElementById('captureBtn').style.display='';}}catch(e){showToast('Камерата не е достъпна','error');closeCamera();}}
    function closeCamera(){document.getElementById('cameraOverlay').classList.remove('open');if(STATE.cameraStream){STATE.cameraStream.getTracks().forEach(t=>t.stop());STATE.cameraStream=null;}if(STATE.barcodeInterval){clearInterval(STATE.barcodeInterval);STATE.barcodeInterval=null;}document.getElementById('scanLine').style.display='none';}
    function startBarcodeScanning(vid){if(!('BarcodeDetector' in window)){showToast('Баркод скенер не е поддържан','error');closeCamera();return;}STATE.barcodeDetector=new BarcodeDetector();STATE.barcodeInterval=setInterval(async()=>{try{const bc=await STATE.barcodeDetector.detect(vid);if(bc.length){clearInterval(STATE.barcodeInterval);playBeep();showGreenFlash();if(STATE.cameraMode==='barcode_add'){document.getElementById('addBarcode').value=bc[0].rawValue;closeCamera();}else{const d=await fetchJSON(`products.php?ajax=barcode&code=${encodeURIComponent(bc[0].rawValue)}&store_id=${STATE.storeId}`);closeCamera();if(d&&!d.error)openProductDetail(d.id);else showToast(`Баркод ${bc[0].rawValue} не е намерен`,'info');}}}catch(e){}},300);}
    async function capturePhoto(){const vid=document.getElementById('cameraVideo');const can=document.getElementById('cameraCanvas');can.width=vid.videoWidth;can.height=vid.videoHeight;can.getContext('2d').drawImage(vid,0,0);const img=can.toDataURL('image/jpeg',0.8).split(',')[1];closeCamera();showToast('✦ AI анализира...','info');const d=await fetchJSON('products.php?ajax=ai_scan',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({image:img})});if(d&&!d.error){if(d.name)document.getElementById('addName').value=d.name;if(d.retail_price)document.getElementById('addRetailPrice').value=d.retail_price;if(d.description)document.getElementById('addDescription').value=d.description;if(d.code)document.getElementById('addCode').value=d.code;if(d.supplier){const opts=document.getElementById('addSupplier').options;for(let i=0;i<opts.length;i++)if(opts[i].text.toLowerCase().includes(d.supplier.toLowerCase())){opts[i].selected=true;break;}}if(d.category){const opts=document.getElementById('addCategory').options;for(let i=0;i<opts.length;i++)if(opts[i].text.toLowerCase().includes(d.category.toLowerCase())){opts[i].selected=true;break;}}if(d.sizes?.length){selectType('variant');d.sizes.forEach(sz=>{const c=document.querySelector(`.size-chip[data-size="${sz}"]`);if(c&&!c.classList.contains('selected')){c.classList.add('selected');STATE.selectedSizes.push(sz);}});}showToast('✦ AI попълни ✓','success');goWizardStep(2);}else{showToast('AI не разпозна — попълни ръчно','error');goWizardStep(2);}}
    function playBeep(){try{const a=new(window.AudioContext||window.webkitAudioContext)();const o=a.createOscillator();const g=a.createGain();o.connect(g);g.connect(a.destination);o.frequency.value=1200;g.gain.value=0.3;o.start();o.stop(a.currentTime+0.15);}catch(e){}}
    function showGreenFlash(){const f=document.createElement('div');f.className='green-flash';document.body.appendChild(f);setTimeout(()=>f.remove(),500);}

    // ============================================================
    // VOICE (S18: blur overlay instead of inline indicator)
    // ============================================================
    function toggleVoice(){if(STATE.isListening){closeVoiceOverlay();return;}if(!('webkitSpeechRecognition' in window)&&!('SpeechRecognition' in window)){showToast('Не се поддържа','error');return;}const SR=window.SpeechRecognition||window.webkitSpeechRecognition;STATE.recognition=new SR();STATE.recognition.lang='bg-BG';STATE.recognition.continuous=false;STATE.recognition.interimResults=false;document.getElementById('voiceOverlay').classList.add('open');document.getElementById('voiceTranscript').textContent='';STATE.recognition.onresult=function(e){const t=e.results[0][0].transcript;document.getElementById('voiceTranscript').textContent='"'+t+'"';document.getElementById('searchInput').value=t;document.getElementById('searchInput').dispatchEvent(new Event('input'));setTimeout(()=>closeVoiceOverlay(),800);};STATE.recognition.onerror=function(){closeVoiceOverlay();};STATE.recognition.onend=function(){closeVoiceOverlay();};STATE.recognition.start();STATE.isListening=true;}
    function closeVoiceOverlay(){if(STATE.recognition)try{STATE.recognition.stop();}catch(e){}STATE.isListening=false;document.getElementById('voiceOverlay').classList.remove('open');}

    // ============================================================
    // LOAD
    // ============================================================
    function loadCurrentScreen(){switch(STATE.screen){case'home':loadHomeScreen();break;case'suppliers':loadSuppliers();break;case'categories':loadCategories();break;case'products':loadProductsList();break;}}
    document.addEventListener('click',e=>{if(!e.target.closest('[onclick*="toggleSort"]')&&!e.target.closest('.sort-dropdown'))document.getElementById('sortDropdown')?.classList.remove('open');});
    document.addEventListener('DOMContentLoaded',loadCurrentScreen);
    </script>
</body>
</html>
