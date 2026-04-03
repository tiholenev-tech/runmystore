<?php
/**
 * products.php — RunMyStore.ai
 * 4-екранен модул: Начало | Доставчици | Категории | Артикули
 * Cruip Open Pro Dark тема, AI-first, voice-first
 * Сесия 17 — 04.04.2026
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
// AJAX ENDPOINTS
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
            GROUP BY p.id
            ORDER BY p.name
            LIMIT 30
        ", [$sid, $tenant_id, $like, $like, $like])->fetchAll(PDO::FETCH_ASSOC);
        if (!$can_see_cost) {
            foreach ($rows as &$r) { unset($r['cost_price']); }
        }
        echo json_encode($rows);
        exit;
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
        echo json_encode($row ?: ['error' => 'not_found']);
        exit;
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

        // Наличност по обекти
        $stocks = DB::run("
            SELECT st.id AS store_id, st.name AS store_name, COALESCE(i.quantity, 0) AS qty
            FROM stores st
            LEFT JOIN inventory i ON i.store_id = st.id AND i.product_id = ?
            WHERE st.company_id = (SELECT company_id FROM stores WHERE id = ?)
            ORDER BY st.name
        ", [$pid, $store_id])->fetchAll(PDO::FETCH_ASSOC);

        // Вариации (ако parent)
        $variations = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.barcode,
                   COALESCE(SUM(i.quantity), 0) AS total_stock
            FROM products p
            LEFT JOIN inventory i ON i.product_id = p.id
            WHERE p.parent_id = ? AND p.tenant_id = ?
            GROUP BY p.id
            ORDER BY p.name
        ", [$pid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

        // Ценова история
        $price_history = DB::run("
            SELECT old_price, new_price, changed_at, changed_by
            FROM price_history
            WHERE product_id = ? ORDER BY changed_at DESC LIMIT 10
        ", [$pid])->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'product' => $product,
            'stocks' => $stocks,
            'variations' => $variations,
            'price_history' => $price_history
        ]);
        exit;
    }

    // --- Статистики за начален екран ---
    if ($_GET['ajax'] === 'home_stats') {
        $sid = (int)($_GET['store_id'] ?? $store_id);

        // Капитал (retail стойност на наличната стока)
        $capital = DB::run("
            SELECT COALESCE(SUM(i.quantity * p.retail_price), 0) AS retail_value,
                   COALESCE(SUM(i.quantity * p.cost_price), 0) AS cost_value
            FROM inventory i
            JOIN products p ON p.id = i.product_id
            WHERE p.tenant_id = ? AND i.store_id = ? AND i.quantity > 0
        ", [$tenant_id, $sid])->fetch(PDO::FETCH_ASSOC);

        // Среден марж
        $avg_margin = 0;
        if ($can_see_margin && $capital['cost_value'] > 0) {
            $avg_margin = round((($capital['retail_value'] - $capital['cost_value']) / $capital['retail_value']) * 100, 1);
        }

        // Zombie stock (>45 дни без продажба)
        $zombies = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image,
                   COALESCE(i.quantity, 0) AS qty,
                   DATEDIFF(NOW(), COALESCE(
                       (SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_id = p.id),
                       p.created_at
                   )) AS days_stale
            FROM products p
            JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND i.quantity > 0
            HAVING days_stale > 45
            ORDER BY days_stale DESC
            LIMIT 10
        ", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

        // Ниска наличност (под минимума)
        $low_stock = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image, p.min_quantity,
                   COALESCE(i.quantity, 0) AS qty
            FROM products p
            JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND p.min_quantity > 0 AND i.quantity <= p.min_quantity AND i.quantity > 0
            ORDER BY (i.quantity / p.min_quantity) ASC
            LIMIT 10
        ", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

        // Изчерпани
        $out_of_stock = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image
            FROM products p
            JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND i.quantity = 0
            ORDER BY p.name
            LIMIT 20
        ", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

        // Топ 5 хитове (последните 30 дни)
        $top_sellers = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image,
                   SUM(si.quantity) AS sold_qty,
                   SUM(si.quantity * si.unit_price) AS revenue
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            JOIN products p ON p.id = si.product_id
            WHERE p.tenant_id = ? AND s.store_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY p.id
            ORDER BY sold_qty DESC
            LIMIT 5
        ", [$tenant_id, $sid])->fetchAll(PDO::FETCH_ASSOC);

        // Бавно движещи се (25-45 дни)
        $slow_movers = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.image,
                   COALESCE(i.quantity, 0) AS qty,
                   DATEDIFF(NOW(), COALESCE(
                       (SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_id = p.id),
                       p.created_at
                   )) AS days_stale
            FROM products p
            JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND i.quantity > 0
            HAVING days_stale BETWEEN 25 AND 45
            ORDER BY days_stale DESC
            LIMIT 10
        ", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

        // Общи бройки
        $counts = DB::run("
            SELECT
                COUNT(DISTINCT p.id) AS total_products,
                COALESCE(SUM(i.quantity), 0) AS total_units,
                COUNT(DISTINCT p.supplier_id) AS total_suppliers,
                COUNT(DISTINCT p.category_id) AS total_categories
            FROM products p
            LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE p.tenant_id = ? AND p.is_active = 1
        ", [$sid, $tenant_id])->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'capital' => $can_see_margin ? round($capital['retail_value'], 2) : null,
            'cost_value' => $can_see_cost ? round($capital['cost_value'], 2) : null,
            'avg_margin' => $can_see_margin ? $avg_margin : null,
            'zombies' => $zombies,
            'low_stock' => $low_stock,
            'out_of_stock' => $out_of_stock,
            'top_sellers' => $top_sellers,
            'slow_movers' => $slow_movers,
            'counts' => $counts
        ]);
        exit;
    }

    // --- Доставчици списък ---
    if ($_GET['ajax'] === 'suppliers') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $suppliers = DB::run("
            SELECT s.id, s.name, s.phone, s.email,
                   COUNT(DISTINCT p.id) AS product_count,
                   COALESCE(SUM(i.quantity), 0) AS total_stock,
                   SUM(CASE WHEN i.quantity = 0 THEN 1 ELSE 0 END) AS out_count,
                   SUM(CASE WHEN i.quantity > 0 AND i.quantity <= p.min_quantity THEN 1 ELSE 0 END) AS low_count
            FROM suppliers s
            JOIN products p ON p.supplier_id = s.id AND p.tenant_id = ?
            LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE s.tenant_id = ?
            GROUP BY s.id
            ORDER BY s.name
        ", [$tenant_id, $sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($suppliers);
        exit;
    }

    // --- Категории (глобални или на доставчик) ---
    if ($_GET['ajax'] === 'categories') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $sup = isset($_GET['sup']) ? (int)$_GET['sup'] : null;

        if ($sup) {
            $categories = DB::run("
                SELECT c.id, c.name, c.parent_id,
                       COUNT(DISTINCT p.id) AS product_count,
                       COALESCE(SUM(i.quantity), 0) AS total_stock
                FROM categories c
                JOIN products p ON p.category_id = c.id AND p.supplier_id = ? AND p.tenant_id = ?
                LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
                WHERE c.tenant_id = ?
                GROUP BY c.id
                ORDER BY c.name
            ", [$sup, $tenant_id, $sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $categories = DB::run("
                SELECT c.id, c.name, c.parent_id,
                       COUNT(DISTINCT p.id) AS product_count,
                       COUNT(DISTINCT p.supplier_id) AS supplier_count,
                       COALESCE(SUM(i.quantity), 0) AS total_stock
                FROM categories c
                JOIN products p ON p.category_id = c.id AND p.tenant_id = ?
                LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
                WHERE c.tenant_id = ?
                GROUP BY c.id
                ORDER BY c.name
            ", [$tenant_id, $sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($categories);
        exit;
    }

    // --- Артикули списък (филтрирани) ---
    if ($_GET['ajax'] === 'products') {
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $sup = isset($_GET['sup']) ? (int)$_GET['sup'] : null;
        $cat = isset($_GET['cat']) ? (int)$_GET['cat'] : null;
        $flt = $_GET['filter'] ?? 'all';
        $sort = $_GET['sort'] ?? 'name';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 30;
        $offset = ($page - 1) * $per_page;

        $where = ["p.tenant_id = ?", "p.is_active = 1"];
        $params = [$tenant_id];

        if ($sup) { $where[] = "p.supplier_id = ?"; $params[] = $sup; }
        if ($cat) { $where[] = "p.category_id = ?"; $params[] = $cat; }

        // Филтри
        if ($flt === 'low') {
            $where[] = "i.quantity > 0 AND i.quantity <= p.min_quantity AND p.min_quantity > 0";
        } elseif ($flt === 'out') {
            $where[] = "(i.quantity = 0 OR i.quantity IS NULL)";
        } elseif ($flt === 'zombie') {
            $where[] = "i.quantity > 0";
        }

        $where_sql = implode(' AND ', $where);

        // Сортиране
        $order = match($sort) {
            'price_asc' => 'p.retail_price ASC',
            'price_desc' => 'p.retail_price DESC',
            'stock_asc' => 'store_stock ASC',
            'stock_desc' => 'store_stock DESC',
            'margin_desc' => $can_see_margin ? '((p.retail_price - p.cost_price) / NULLIF(p.retail_price, 0)) DESC' : 'p.name ASC',
            'newest' => 'p.created_at DESC',
            default => 'p.name ASC'
        };

        $products = DB::run("
            SELECT p.id, p.name, p.code, p.barcode, p.retail_price, p.cost_price,
                   p.image, p.supplier_id, p.category_id, p.parent_id,
                   p.discount_pct, p.discount_ends_at, p.min_quantity, p.unit,
                   s.name AS supplier_name, c.name AS category_name,
                   COALESCE(i.quantity, 0) AS store_stock,
                   (SELECT COALESCE(SUM(i2.quantity), 0) FROM inventory i2 WHERE i2.product_id = p.id) AS total_stock
            FROM products p
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE {$where_sql}
            ORDER BY {$order}
            LIMIT ? OFFSET ?
        ", array_merge([$sid], $params, [$per_page, $offset]))->fetchAll(PDO::FETCH_ASSOC);

        // Общ брой за pagination
        $total = DB::run("
            SELECT COUNT(DISTINCT p.id) AS cnt
            FROM products p
            LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
            WHERE {$where_sql}
        ", array_merge([$sid], $params))->fetchColumn();

        if (!$can_see_cost) {
            foreach ($products as &$pr) { unset($pr['cost_price']); }
        }

        echo json_encode([
            'products' => $products,
            'total' => (int)$total,
            'page' => $page,
            'pages' => ceil($total / $per_page)
        ]);
        exit;
    }

    // --- AI анализ на артикул ---
    if ($_GET['ajax'] === 'ai_analyze') {
        $pid = (int)($_GET['id'] ?? 0);
        $product = DB::run("SELECT * FROM products WHERE id = ? AND tenant_id = ?", [$pid, $tenant_id])->fetch(PDO::FETCH_ASSOC);
        if (!$product) { echo json_encode(['error' => 'not_found']); exit; }

        $sales_30d = DB::run("
            SELECT COALESCE(SUM(si.quantity), 0) AS qty, COALESCE(SUM(si.quantity * si.unit_price), 0) AS revenue
            FROM sale_items si JOIN sales s ON s.id = si.sale_id
            WHERE si.product_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", [$pid])->fetch(PDO::FETCH_ASSOC);

        $stock = DB::run("SELECT COALESCE(SUM(quantity), 0) AS total FROM inventory WHERE product_id = ?", [$pid])->fetchColumn();

        $days_supply = ($sales_30d['qty'] > 0) ? round(($stock / ($sales_30d['qty'] / 30)), 0) : 999;

        $analysis = [];
        if ($days_supply > 90 && $stock > 0) {
            $analysis[] = ['type' => 'zombie', 'icon' => '💀', 'text' => "Стока за {$days_supply} дни. Намали с 30% или пакет.", 'severity' => 'high'];
        } elseif ($days_supply > 45 && $stock > 0) {
            $analysis[] = ['type' => 'slow', 'icon' => '🐌', 'text' => "Бавно движеща се — {$days_supply} дни запас. Промоция -20%?", 'severity' => 'medium'];
        }

        if ($stock <= $product['min_quantity'] && $stock > 0 && $product['min_quantity'] > 0) {
            $analysis[] = ['type' => 'low', 'icon' => '⚠️', 'text' => "Остават {$stock} бр. (мин. {$product['min_quantity']}). Поръчай!", 'severity' => 'high'];
        } elseif ($stock == 0) {
            $analysis[] = ['type' => 'out', 'icon' => '🔴', 'text' => "ИЗЧЕРПАН! Губиш продажби.", 'severity' => 'critical'];
        }

        if ($can_see_margin && $product['cost_price'] > 0) {
            $margin = round((($product['retail_price'] - $product['cost_price']) / $product['retail_price']) * 100, 1);
            if ($margin < 20) {
                $analysis[] = ['type' => 'margin', 'icon' => '💰', 'text' => "Марж само {$margin}%. Увеличи цената?", 'severity' => 'medium'];
            }
        }

        if ($sales_30d['qty'] > 0) {
            $analysis[] = ['type' => 'sales', 'icon' => '📊', 'text' => "Продажби 30 дни: {$sales_30d['qty']} бр. / " . number_format($sales_30d['revenue'], 2, ',', '.') . " €", 'severity' => 'info'];
        }

        echo json_encode(['analysis' => $analysis, 'days_supply' => $days_supply, 'sales_30d' => $sales_30d]);
        exit;
    }

    // --- AI Image Studio статус ---
    if ($_GET['ajax'] === 'ai_credits') {
        $credits = DB::run("SELECT ai_credits_bg, ai_credits_tryon FROM tenants WHERE id = ?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
        echo json_encode($credits);
        exit;
    }

    // --- Gemini AI за добавяне (камера → предпопълване) ---
    if ($_GET['ajax'] === 'ai_scan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $image_data = $input['image'] ?? '';
        if (!$image_data) { echo json_encode(['error' => 'no_image']); exit; }

        // Tenant AI памет
        $memories = DB::run("SELECT memory_text FROM tenant_ai_memory WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 20", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $memory_ctx = implode("\n", $memories);

        // Категории и доставчици за контекст
        $cats = DB::run("SELECT name FROM categories WHERE tenant_id = ?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $sups = DB::run("SELECT name FROM suppliers WHERE tenant_id = ?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);

        $prompt = "Ти си AI асистент за магазин за дрехи/обувки в България. Анализирай тази снимка на продукт и върни JSON:\n";
        $prompt .= "{\"name\": \"...\", \"category\": \"...\", \"supplier\": \"...\", \"sizes\": [...], \"colors\": [...], \"retail_price\": 0, \"description\": \"...\"}\n";
        $prompt .= "Категории в системата: " . implode(', ', $cats) . "\n";
        $prompt .= "Доставчици: " . implode(', ', $sups) . "\n";
        if ($memory_ctx) { $prompt .= "AI памет (научено от собственика): {$memory_ctx}\n"; }
        $prompt .= "Отговори САМО с JSON, без markdown.";

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
        $payload = [
            'contents' => [[
                'parts' => [
                    ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $image_data]],
                    ['text' => $prompt]
                ]
            ]],
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 500]
        ];

        $ch = curl_init($api_url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) { echo json_encode(['error' => $err]); exit; }
        $data = json_decode($resp, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = preg_replace('/```json\s*|\s*```/', '', $text);
        $parsed = json_decode($text, true);
        echo json_encode($parsed ?: ['error' => 'parse_failed', 'raw' => $text]);
        exit;
    }

    // --- Voice → AI попълване ---
    if ($_GET['ajax'] === 'ai_voice_fill' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $voice_text = $input['text'] ?? '';
        if (!$voice_text) { echo json_encode(['error' => 'no_text']); exit; }

        $cats = DB::run("SELECT name FROM categories WHERE tenant_id = ?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
        $sups = DB::run("SELECT name FROM suppliers WHERE tenant_id = ?", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);

        $prompt = "Ти си AI за магазин. Потребителят описа продукт с глас: \"{$voice_text}\"\n";
        $prompt .= "Върни JSON: {\"name\": \"...\", \"category\": \"...\", \"supplier\": \"...\", \"sizes\": [...], \"colors\": [...], \"retail_price\": 0, \"description\": \"...\"}\n";
        $prompt .= "Категории: " . implode(', ', $cats) . "\nДоставчици: " . implode(', ', $sups) . "\n";
        $prompt .= "Разбирай мекенето/диалекта: дреги=дрехи, офки=обувки, якита=якета. САМО JSON.";

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 500]
        ];
        $ch = curl_init($api_url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = preg_replace('/```json\s*|\s*```/', '', $text);
        echo json_encode(json_decode($text, true) ?: ['error' => 'parse_failed']);
        exit;
    }

    echo json_encode(['error' => 'unknown_action']);
    exit;
}

// ============================================================
// Зареждане на обекти (за selector)
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

// Доставчици и категории за dropdown-и
$all_suppliers = DB::run("SELECT id, name FROM suppliers WHERE tenant_id = ? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$all_categories = DB::run("SELECT id, name, parent_id FROM categories WHERE tenant_id = ? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);

// Breadcrumb данни
$supplier_name = '';
$category_name = '';
if ($sup_id) {
    $supplier_name = DB::run("SELECT name FROM suppliers WHERE id = ? AND tenant_id = ?", [$sup_id, $tenant_id])->fetchColumn() ?: '';
}
if ($cat_id) {
    $category_name = DB::run("SELECT name FROM categories WHERE id = ? AND tenant_id = ?", [$cat_id, $tenant_id])->fetchColumn() ?: '';
}

// Cross-link данни (когато drill-down от доставчик+категория)
$cross_link = null;
if ($sup_id && $cat_id && $screen === 'products') {
    $cl = DB::run("
        SELECT COUNT(DISTINCT p.id) AS cnt, COUNT(DISTINCT p.supplier_id) AS sup_cnt
        FROM products p WHERE p.category_id = ? AND p.tenant_id = ? AND p.is_active = 1
    ", [$cat_id, $tenant_id])->fetch(PDO::FETCH_ASSOC);
    if ($cl['sup_cnt'] > 1) {
        $cross_link = ['count' => $cl['cnt'], 'suppliers' => $cl['sup_cnt'], 'cat_name' => $category_name];
    }
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
        /* ====== CRUIP DARK OVERRIDES + CUSTOM ====== */
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

        /* SVG Background decorations */
        .bg-decoration { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; overflow: hidden; }
        .bg-decoration::before { content: ''; position: absolute; top: -10%; left: 50%; transform: translateX(-50%); width: 1200px; height: 800px; background: url('./images/page-illustration.svg') no-repeat center; background-size: contain; opacity: 0.15; }
        .bg-decoration::after { content: ''; position: absolute; top: 20%; right: -20%; width: 600px; height: 600px; background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%); border-radius: 50%; }
        .bg-blur-shape { position: fixed; width: 400px; height: 400px; border-radius: 50%; filter: blur(100px); opacity: 0.06; pointer-events: none; z-index: 0; }
        .bg-blur-1 { top: 10%; left: -10%; background: var(--indigo-500); }
        .bg-blur-2 { bottom: 20%; right: -10%; background: #4f46e5; }

        /* Main container */
        .main-wrap { position: relative; z-index: 1; padding-bottom: 140px; padding-top: 8px; }

        /* Header */
        .top-header { position: sticky; top: 0; z-index: 50; padding: 8px 16px; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); background: rgba(11, 15, 26, 0.8); border-bottom: 1px solid var(--border-subtle); }
        .top-header h1 { font-family: Nacelle, Inter, sans-serif; font-size: 1.25rem; font-weight: 700; margin: 0; background: linear-gradient(to right, var(--text-primary), var(--indigo-200), #f9fafb, var(--indigo-300), var(--text-primary)); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; animation: gradient 6s linear infinite; }
        @keyframes gradient { 0% { background-position: 0% center; } 100% { background-position: 200% center; } }
        .header-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .store-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 999px; background: rgba(99,102,241,0.15); color: var(--indigo-300); border: 1px solid var(--border-subtle); white-space: nowrap; }

        /* Search bar */
        .search-wrap { margin: 8px 16px 0; position: relative; }
        .search-input { width: 100%; padding: 10px 96px 10px 40px; border-radius: 12px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.9rem; outline: none; transition: all 0.3s; }
        .search-input:focus { border-color: var(--border-glow); box-shadow: 0 0 20px rgba(99,102,241,0.15); }
        .search-input::placeholder { color: var(--text-secondary); }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); pointer-events: none; }
        .search-actions { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); display: flex; gap: 2px; }
        .search-btn { width: 36px; height: 36px; border-radius: 8px; border: none; background: transparent; color: var(--indigo-300); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .search-btn:active { background: rgba(99,102,241,0.2); transform: scale(0.9); }
        .search-btn.active { background: rgba(99,102,241,0.25); color: #fff; }

        /* Tabs */
        .tabs-row { display: flex; gap: 6px; padding: 10px 16px 0; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; }
        .tabs-row::-webkit-scrollbar { display: none; }
        .tab-btn { padding: 6px 14px; border-radius: 999px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.8rem; white-space: nowrap; cursor: pointer; transition: all 0.2s; flex-shrink: 0; }
        .tab-btn.active { background: linear-gradient(135deg, var(--indigo-500), #4f46e5); color: #fff; border-color: transparent; box-shadow: 0 0 12px rgba(99,102,241,0.3); }
        .tab-btn .count { display: inline-block; min-width: 18px; height: 18px; line-height: 18px; text-align: center; border-radius: 9px; background: rgba(255,255,255,0.15); font-size: 0.65rem; margin-left: 4px; padding: 0 4px; }
        .tab-btn.active .count { background: rgba(255,255,255,0.25); }

        /* Quick stats cards */
        .quick-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 12px 16px 0; }
        .stat-card { position: relative; padding: 14px; border-radius: 12px; background: var(--bg-card); overflow: hidden; border: 1px solid var(--border-subtle); }
        .stat-card::before { content: ''; position: absolute; inset: 0; border-radius: inherit; border: 1px solid transparent; background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(79,70,229,0.05)) border-box; mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0); mask-composite: exclude; -webkit-mask-composite: xor; pointer-events: none; }
        .stat-card .stat-label { font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-value { font-size: 1.3rem; font-weight: 700; font-family: Nacelle, Inter, sans-serif; margin-top: 4px; }
        .stat-card .stat-icon { position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; border-radius: 8px; background: rgba(99,102,241,0.12); display: flex; align-items: center; justify-content: center; font-size: 1rem; }

        /* Collapse sections */
        .collapse-section { margin: 10px 16px 0; border-radius: 12px; background: var(--bg-card); border: 1px solid var(--border-subtle); overflow: hidden; }
        .collapse-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; cursor: pointer; user-select: none; transition: background 0.2s; }
        .collapse-header:active { background: rgba(99,102,241,0.08); }
        .collapse-header .ch-left { display: flex; align-items: center; gap: 8px; }
        .collapse-header .ch-icon { font-size: 1.1rem; }
        .collapse-header .ch-title { font-size: 0.85rem; font-weight: 600; }
        .collapse-header .ch-count { font-size: 0.7rem; color: var(--text-secondary); background: rgba(99,102,241,0.1); padding: 2px 8px; border-radius: 999px; }
        .collapse-header .ch-arrow { transition: transform 0.3s; color: var(--text-secondary); }
        .collapse-header.open .ch-arrow { transform: rotate(180deg); }
        .collapse-body { max-height: 0; overflow: hidden; transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .collapse-body.open { max-height: 2000px; }
        .collapse-body-inner { padding: 0 14px 14px; }

        /* Product cards */
        .product-card { display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 10px; background: rgba(17, 24, 44, 0.5); border: 1px solid var(--border-subtle); margin-bottom: 8px; cursor: pointer; transition: all 0.25s; position: relative; overflow: hidden; }
        .product-card:active { transform: scale(0.98); background: var(--bg-card-hover); }
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

        /* Supplier swipe cards */
        .swipe-container { padding: 12px 16px; overflow-x: auto; display: flex; gap: 12px; scroll-snap-type: x mandatory; scrollbar-width: none; -ms-overflow-style: none; }
        .swipe-container::-webkit-scrollbar { display: none; }
        .supplier-card { min-width: 260px; max-width: 300px; flex-shrink: 0; scroll-snap-align: start; border-radius: 14px; padding: 16px; background: var(--bg-card); border: 1px solid var(--border-subtle); position: relative; overflow: hidden; cursor: pointer; transition: all 0.3s; }
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

        /* Category cards (global mode: swipe, supplier mode: list) */
        .cat-list-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-subtle); cursor: pointer; transition: background 0.2s; }
        .cat-list-item:active { background: rgba(99,102,241,0.08); }
        .cat-list-item:last-child { border-bottom: none; }
        .cat-list-item .cli-left { display: flex; align-items: center; gap: 10px; }
        .cat-list-item .cli-icon { width: 36px; height: 36px; border-radius: 8px; background: rgba(99,102,241,0.12); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .cat-list-item .cli-name { font-size: 0.9rem; font-weight: 600; }
        .cat-list-item .cli-count { font-size: 0.7rem; color: var(--text-secondary); }

        .category-card { min-width: 180px; flex-shrink: 0; scroll-snap-align: start; border-radius: 12px; padding: 14px; background: var(--bg-card); border: 1px solid var(--border-subtle); cursor: pointer; transition: all 0.3s; position: relative; overflow: hidden; }
        .category-card::before { content: ''; position: absolute; inset: 0; border-radius: inherit; border: 1px solid transparent; background: linear-gradient(135deg, rgba(99,102,241,0.12), transparent) border-box; mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0); mask-composite: exclude; -webkit-mask-composite: xor; pointer-events: none; }
        .category-card:active { transform: scale(0.97); }
        .category-card .cc-name { font-size: 0.85rem; font-weight: 600; }
        .category-card .cc-info { font-size: 0.7rem; color: var(--text-secondary); margin-top: 4px; }

        /* Breadcrumb */
        .breadcrumb { display: flex; align-items: center; gap: 4px; padding: 8px 16px 0; font-size: 0.75rem; color: var(--text-secondary); flex-wrap: wrap; }
        .breadcrumb a { color: var(--indigo-400); text-decoration: none; }
        .breadcrumb a:active { color: var(--indigo-300); }
        .breadcrumb .sep { margin: 0 2px; }
        .cross-link { margin: 6px 16px 0; padding: 8px 12px; border-radius: 8px; background: rgba(99,102,241,0.08); border: 1px dashed var(--border-glow); font-size: 0.75rem; color: var(--indigo-300); cursor: pointer; }
        .cross-link:active { background: rgba(99,102,241,0.15); }

        /* ====== STICKY NAV BUTTONS (4) ====== */
        .screen-nav { position: fixed; bottom: 68px; left: 0; right: 0; z-index: 40; display: flex; justify-content: center; padding: 0 12px; }
        .screen-nav-inner { display: flex; gap: 4px; background: rgba(11, 15, 26, 0.9); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-radius: 14px; padding: 4px; border: 1px solid var(--border-subtle); box-shadow: 0 -4px 24px rgba(0,0,0,0.3); width: 100%; max-width: 400px; }
        .snav-btn { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 8px 4px; border-radius: 10px; border: none; background: transparent; color: var(--text-secondary); font-size: 0.6rem; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
        .snav-btn .snav-icon { font-size: 1.1rem; }
        .snav-btn.active { background: linear-gradient(135deg, rgba(99,102,241,0.25), rgba(79,70,229,0.15)); color: #fff; box-shadow: 0 0 12px rgba(99,102,241,0.2); }
        .snav-btn:active { transform: scale(0.95); }

        /* ====== BOTTOM NAV (4 tabs) ====== */
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 45; background: rgba(11, 15, 26, 0.95); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-top: 1px solid var(--border-subtle); display: flex; height: 56px; }
        .bnav-tab { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; font-size: 0.6rem; color: var(--text-secondary); text-decoration: none; transition: color 0.2s; }
        .bnav-tab.active { color: var(--indigo-400); }
        .bnav-tab .bnav-icon { font-size: 1.2rem; }

        /* ====== ADD BUTTON (floating) ====== */
        .fab-add { position: fixed; bottom: 134px; right: 16px; z-index: 42; width: 52px; height: 52px; border-radius: 14px; border: none; background: linear-gradient(135deg, var(--indigo-500), #4f46e5); color: #fff; font-size: 1.5rem; cursor: pointer; box-shadow: 0 4px 24px rgba(99,102,241,0.4); transition: all 0.3s; display: flex; align-items: center; justify-content: center; }
        .fab-add:active { transform: scale(0.9); }
        .fab-add::after { content: ''; position: absolute; inset: -3px; border-radius: 17px; background: linear-gradient(135deg, var(--indigo-400), var(--indigo-500)); z-index: -1; opacity: 0.4; animation: pulse-glow 2s ease-in-out infinite; }
        @keyframes pulse-glow { 0%, 100% { opacity: 0.3; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.05); } }

        /* ====== DRAWERS ====== */
        .drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 100; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .drawer-overlay.open { opacity: 1; pointer-events: auto; }
        .drawer { position: fixed; bottom: 0; left: 0; right: 0; z-index: 101; background: var(--bg-main); border-radius: 20px 20px 0 0; transform: translateY(100%); transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1); max-height: 90vh; display: flex; flex-direction: column; }
        .drawer.open { transform: translateY(0); }
        .drawer-handle { width: 36px; height: 4px; border-radius: 2px; background: rgba(255,255,255,0.2); margin: 10px auto 0; flex-shrink: 0; }
        .drawer-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px 10px; border-bottom: 1px solid var(--border-subtle); flex-shrink: 0; }
        .drawer-header h3 { font-family: Nacelle, Inter, sans-serif; font-size: 1.05rem; font-weight: 700; margin: 0; }
        .drawer-close { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .drawer-body { flex: 1; overflow-y: auto; padding: 14px 16px 24px; -webkit-overflow-scrolling: touch; }

        /* ====== MODAL (Add/Edit) ====== */
        .modal-overlay { position: fixed; inset: 0; background: var(--bg-main); z-index: 200; opacity: 0; pointer-events: none; transition: opacity 0.3s; display: flex; flex-direction: column; }
        .modal-overlay.open { opacity: 1; pointer-events: auto; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-subtle); flex-shrink: 0; }
        .modal-header h2 { font-family: Nacelle, Inter, sans-serif; font-size: 1.1rem; font-weight: 700; margin: 0; }
        .modal-body { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; }
        .modal-footer { padding: 12px 16px; border-top: 1px solid var(--border-subtle); flex-shrink: 0; }

        /* Wizard steps */
        .wizard-steps { display: flex; align-items: center; justify-content: center; gap: 4px; padding: 12px 16px; }
        .wiz-step { width: 8px; height: 8px; border-radius: 50%; background: var(--border-subtle); transition: all 0.3s; }
        .wiz-step.active { width: 24px; border-radius: 4px; background: var(--indigo-500); }
        .wiz-step.done { background: var(--indigo-400); }
        .wizard-page { display: none; padding: 16px; }
        .wizard-page.active { display: block; }

        /* Form elements */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.3px; }
        .form-control { width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.9rem; outline: none; transition: all 0.3s; }
        .form-control:focus { border-color: var(--border-glow); box-shadow: 0 0 16px rgba(99,102,241,0.12); }
        .form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23818cf8'%3E%3Cpath d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; }

        /* Size chips */
        .size-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .size-chip { padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-primary); font-size: 0.8rem; cursor: pointer; transition: all 0.2s; }
        .size-chip.selected { background: var(--indigo-500); color: #fff; border-color: var(--indigo-500); box-shadow: 0 0 8px rgba(99,102,241,0.3); }
        .size-chip:active { transform: scale(0.95); }

        /* Color dots */
        .color-dots { display: flex; flex-wrap: wrap; gap: 8px; }
        .color-dot { width: 32px; height: 32px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; transition: all 0.2s; position: relative; }
        .color-dot.selected { border-color: var(--indigo-400); box-shadow: 0 0 8px rgba(99,102,241,0.4); }
        .color-dot.selected::after { content: '✓'; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.8rem; text-shadow: 0 1px 2px rgba(0,0,0,0.5); }

        /* AI Pulse button */
        .ai-pulse-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 24px; margin: 16px; border-radius: 16px; border: 2px dashed var(--border-glow); background: rgba(99,102,241,0.05); cursor: pointer; transition: all 0.3s; }
        .ai-pulse-btn:active { background: rgba(99,102,241,0.12); transform: scale(0.98); }
        .ai-pulse-icon { width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, var(--indigo-500), #4f46e5); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: #fff; position: relative; }
        .ai-pulse-icon::before { content: ''; position: absolute; inset: -6px; border-radius: 50%; border: 2px solid var(--indigo-500); animation: ai-ring 2s ease-in-out infinite; opacity: 0; }
        .ai-pulse-icon::after { content: ''; position: absolute; inset: -14px; border-radius: 50%; border: 1px solid var(--indigo-400); animation: ai-ring 2s ease-in-out infinite 0.5s; opacity: 0; }
        @keyframes ai-ring { 0% { opacity: 0.6; transform: scale(0.85); } 100% { opacity: 0; transform: scale(1.2); } }
        .ai-pulse-text { font-size: 0.85rem; font-weight: 600; color: var(--indigo-300); }
        .ai-pulse-sub { font-size: 0.7rem; color: var(--text-secondary); }

        /* AI Image Studio */
        .ai-studio-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .ai-studio-btn { padding: 12px; border-radius: 10px; border: 1px solid var(--border-subtle); background: var(--bg-card); cursor: pointer; text-align: center; transition: all 0.2s; }
        .ai-studio-btn:active { background: var(--bg-card-hover); transform: scale(0.97); }
        .ai-studio-btn .asb-icon { font-size: 1.5rem; margin-bottom: 4px; }
        .ai-studio-btn .asb-name { font-size: 0.75rem; font-weight: 600; }
        .ai-studio-btn .asb-price { font-size: 0.65rem; color: var(--text-secondary); }
        .ai-studio-btn.wide { grid-column: 1 / -1; }
        .ai-studio-btn.disabled { opacity: 0.4; pointer-events: none; }
        .credits-info { font-size: 0.7rem; color: var(--text-secondary); text-align: center; margin-top: 8px; padding: 6px; background: rgba(99,102,241,0.05); border-radius: 8px; }

        /* Camera overlay */
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

        /* Toast */
        .toast-container { position: fixed; top: 16px; left: 16px; right: 16px; z-index: 500; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
        .toast { padding: 12px 16px; border-radius: 10px; background: rgba(17, 24, 44, 0.95); border: 1px solid var(--border-subtle); backdrop-filter: blur(8px); color: var(--text-primary); font-size: 0.8rem; transform: translateY(-20px); opacity: 0; transition: all 0.3s; pointer-events: auto; display: flex; align-items: center; gap: 8px; }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.success { border-color: rgba(34,197,94,0.4); }
        .toast.error { border-color: rgba(239,68,68,0.4); }
        .toast.info { border-color: rgba(99,102,241,0.4); }

        /* Indigo line decorations */
        .indigo-line { height: 1px; background: linear-gradient(to right, transparent, var(--border-glow), transparent); margin: 12px 16px; }

        /* Supplier stats section */
        .stats-section { padding: 14px 16px; }
        .stats-section h4 { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .stats-section h4::before { content: ''; width: 24px; height: 1px; background: linear-gradient(to right, transparent, var(--indigo-400)); }

        /* Product detail drawer extras */
        .detail-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(99,102,241,0.08); }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-size: 0.75rem; color: var(--text-secondary); }
        .detail-value { font-size: 0.85rem; font-weight: 600; }
        .store-stock-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; border-radius: 8px; margin-bottom: 4px; background: rgba(17,24,44,0.5); }
        .store-stock-row .ssr-name { font-size: 0.8rem; }
        .store-stock-row .ssr-qty { font-size: 0.85rem; font-weight: 700; }

        /* Action buttons in drawer */
        .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
        .action-btn { padding: 10px; border-radius: 10px; border: 1px solid var(--border-subtle); background: var(--bg-card); color: var(--text-primary); font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s; }
        .action-btn:active { background: var(--bg-card-hover); transform: scale(0.97); }
        .action-btn.primary { background: linear-gradient(135deg, var(--indigo-500), #4f46e5); border-color: transparent; color: #fff; }
        .action-btn.danger { border-color: rgba(239,68,68,0.3); color: #ef4444; }
        .action-btn.wide { grid-column: 1 / -1; }

        /* Voice recording indicator */
        .voice-indicator { display: none; align-items: center; gap: 8px; padding: 8px 16px; }
        .voice-indicator.active { display: flex; }
        .voice-dot { width: 10px; height: 10px; border-radius: 50%; background: #ef4444; animation: voice-pulse 1s ease-in-out infinite; }
        @keyframes voice-pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.8); } }
        .voice-text { font-size: 0.8rem; color: var(--text-secondary); }

        /* Sort dropdown */
        .sort-dropdown { position: absolute; top: 100%; right: 0; margin-top: 4px; background: var(--bg-main); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 4px; min-width: 180px; z-index: 60; display: none; box-shadow: 0 8px 32px rgba(0,0,0,0.4); }
        .sort-dropdown.open { display: block; }
        .sort-option { padding: 8px 12px; border-radius: 8px; font-size: 0.8rem; cursor: pointer; color: var(--text-secondary); transition: all 0.2s; }
        .sort-option:active, .sort-option.active { background: rgba(99,102,241,0.12); color: var(--indigo-300); }

        /* Filter drawer specific */
        .filter-section { margin-bottom: 16px; }
        .filter-section .fs-title { font-size: 0.8rem; font-weight: 600; margin-bottom: 8px; color: var(--indigo-300); }
        .filter-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .filter-chip { padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .filter-chip.selected { background: rgba(99,102,241,0.2); color: var(--indigo-300); border-color: var(--border-glow); }
        .price-range { display: flex; align-items: center; gap: 8px; }
        .price-range input { width: 100px; }

        /* Loading skeleton */
        .skeleton { background: linear-gradient(90deg, var(--bg-card) 25%, rgba(99,102,241,0.08) 50%, var(--bg-card) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 8px; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        /* Beep audio */
        .beep-audio { display: none; }

        /* Pagination */
        .pagination { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 16px; }
        .page-btn { width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-secondary); font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .page-btn.active { background: var(--indigo-500); color: #fff; border-color: transparent; }
        .page-btn:active { transform: scale(0.95); }

        /* Empty state */
        .empty-state { text-align: center; padding: 40px 20px; }
        .empty-state .es-icon { font-size: 3rem; margin-bottom: 12px; opacity: 0.5; }
        .empty-state .es-text { font-size: 0.9rem; color: var(--text-secondary); }

        /* Section titles */
        .section-title { padding: 14px 16px 6px; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px; }
        .section-title::before { content: ''; display: inline-block; width: 24px; height: 1px; background: linear-gradient(to right, transparent, var(--indigo-500)); }
        .section-title::after { content: ''; flex: 1; height: 1px; background: linear-gradient(to left, transparent, var(--border-subtle)); }

        /* Variation display in product card */
        .var-sizes { display: flex; gap: 3px; margin-top: 3px; }
        .var-size { font-size: 0.55rem; padding: 1px 4px; border-radius: 3px; background: rgba(99,102,241,0.1); color: var(--indigo-300); }

        /* AI drawer */
        .ai-insight { padding: 10px 12px; border-radius: 10px; margin-bottom: 8px; display: flex; align-items: flex-start; gap: 10px; }
        .ai-insight.critical { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); }
        .ai-insight.high { background: rgba(234,179,8,0.08); border: 1px solid rgba(234,179,8,0.2); }
        .ai-insight.medium { background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2); }
        .ai-insight.info { background: rgba(99,102,241,0.05); border: 1px solid var(--border-subtle); }
        .ai-insight .ai-icon { font-size: 1.2rem; flex-shrink: 0; margin-top: 2px; }
        .ai-insight .ai-text { font-size: 0.8rem; line-height: 1.4; }
        .ai-deeplink { display: inline-flex; align-items: center; gap: 4px; margin-top: 6px; padding: 4px 10px; border-radius: 6px; background: rgba(99,102,241,0.12); color: var(--indigo-300); font-size: 0.7rem; text-decoration: none; }
    </style>
</head>
<body>
    <!-- Background decorations -->
    <div class="bg-decoration"></div>
    <div class="bg-blur-shape bg-blur-1"></div>
    <div class="bg-blur-shape bg-blur-2"></div>

    <!-- Toast container -->
    <div class="toast-container" id="toasts"></div>

    <!-- Main wrapper -->
    <div class="main-wrap">

        <!-- ====== HEADER ====== -->
        <div class="top-header">
            <div class="header-row">
                <h1>Артикули</h1>
                <div style="display:flex;align-items:center;gap:6px;">
                    <select id="storeSelector" class="store-badge" onchange="switchStore(this.value)" style="background:rgba(99,102,241,0.15);border:1px solid var(--border-subtle);color:var(--indigo-300);padding:4px 8px;border-radius:999px;font-size:0.7rem;">
                        <?php foreach ($stores as $st): ?>
                            <option value="<?= $st['id'] ?>" <?= $st['id'] == $store_id ? 'selected' : '' ?>><?= htmlspecialchars($st['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($can_add): ?>
                        <button onclick="openAddModal()" style="background:linear-gradient(135deg,var(--indigo-500),#4f46e5);border:none;color:#fff;padding:6px 12px;border-radius:8px;font-size:0.75rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px;">
                            <span>+</span> Добави
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ====== SEARCH BAR ====== -->
        <div class="search-wrap">
            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Търси артикул, код, баркод..." autocomplete="off">
            <div class="search-actions">
                <button class="search-btn" onclick="openCamera('scan')" title="Скенер">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="4" y1="12" x2="20" y2="12"/></svg>
                </button>
                <button class="search-btn" id="voiceBtn" onclick="toggleVoice()" title="Глас">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
                </button>
                <button class="search-btn" id="filterBtn" onclick="openFilterDrawer()" title="Филтри">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                </button>
            </div>
            <div class="voice-indicator" id="voiceIndicator">
                <div class="voice-dot"></div>
                <span class="voice-text">Слушам...</span>
            </div>
        </div>

        <!-- ====== SCREEN: HOME ====== -->
        <section id="screenHome" class="screen-section" style="display:<?= $screen === 'home' ? 'block' : 'none' ?>;">
            <!-- Tabs: All / Low / Out -->
            <div class="tabs-row">
                <button class="tab-btn active" data-tab="all" onclick="setHomeTab('all', this)">Всички <span class="count" id="countAll">-</span></button>
                <button class="tab-btn" data-tab="low" onclick="setHomeTab('low', this)">Ниска нал. <span class="count" id="countLow">-</span></button>
                <button class="tab-btn" data-tab="out" onclick="setHomeTab('out', this)">Изчерпани <span class="count" id="countOut">-</span></button>
            </div>

            <!-- Quick stats -->
            <div class="quick-stats" id="quickStats">
                <?php if ($can_see_margin): ?>
                <div class="stat-card">
                    <div class="stat-label">Капитал</div>
                    <div class="stat-value" id="statCapital">—</div>
                    <div class="stat-icon">💰</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Ср. марж</div>
                    <div class="stat-value" id="statMargin">—</div>
                    <div class="stat-icon">📊</div>
                </div>
                <?php else: ?>
                <div class="stat-card">
                    <div class="stat-label">Артикули</div>
                    <div class="stat-value" id="statProducts">—</div>
                    <div class="stat-icon">📦</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Общо бройки</div>
                    <div class="stat-value" id="statUnits">—</div>
                    <div class="stat-icon">📋</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Collapse: Zombie stock -->
            <div class="collapse-section" id="collapseZombie" style="display:none;">
                <div class="collapse-header" onclick="toggleCollapse('zombie')">
                    <div class="ch-left">
                        <span class="ch-icon">💀</span>
                        <span class="ch-title">Zombie стока</span>
                        <span class="ch-count" id="zombieCount">0</span>
                    </div>
                    <svg class="ch-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="collapse-body" id="collapseZombieBody">
                    <div class="collapse-body-inner" id="zombieList"></div>
                </div>
            </div>

            <!-- Collapse: Low stock -->
            <div class="collapse-section" id="collapseLow" style="display:none;">
                <div class="collapse-header" onclick="toggleCollapse('low')">
                    <div class="ch-left">
                        <span class="ch-icon">⚠️</span>
                        <span class="ch-title">Свършват скоро</span>
                        <span class="ch-count" id="lowCount">0</span>
                    </div>
                    <svg class="ch-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="collapse-body" id="collapseLowBody">
                    <div class="collapse-body-inner" id="lowList"></div>
                </div>
            </div>

            <!-- Collapse: Top sellers -->
            <div class="collapse-section" id="collapseTop" style="display:none;">
                <div class="collapse-header" onclick="toggleCollapse('top')">
                    <div class="ch-left">
                        <span class="ch-icon">🔥</span>
                        <span class="ch-title">Топ 5 хитове</span>
                        <span class="ch-count" id="topCount">0</span>
                    </div>
                    <svg class="ch-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="collapse-body" id="collapseTopBody">
                    <div class="collapse-body-inner" id="topList"></div>
                </div>
            </div>

            <!-- Collapse: Slow movers -->
            <div class="collapse-section" id="collapseSlow" style="display:none;">
                <div class="collapse-header" onclick="toggleCollapse('slow')">
                    <div class="ch-left">
                        <span class="ch-icon">🐌</span>
                        <span class="ch-title">Бавно движещи се</span>
                        <span class="ch-count" id="slowCount">0</span>
                    </div>
                    <svg class="ch-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="collapse-body" id="collapseSlowBody">
                    <div class="collapse-body-inner" id="slowList"></div>
                </div>
            </div>

            <!-- All products list (home tab = all) -->
            <div id="homeProductsList" style="padding:0 16px;margin-top:8px;"></div>
            <div id="homePagination" class="pagination"></div>
        </section>

        <!-- ====== SCREEN: SUPPLIERS ====== -->
        <section id="screenSuppliers" class="screen-section" style="display:<?= $screen === 'suppliers' ? 'block' : 'none' ?>;">
            <div class="section-title">Доставчици</div>
            <div class="swipe-container" id="supplierCards"></div>
            <div class="indigo-line"></div>
            <div class="stats-section">
                <h4>Статистики доставчици</h4>
                <div id="supplierStats"></div>
            </div>
        </section>

        <!-- ====== SCREEN: CATEGORIES ====== -->
        <section id="screenCategories" class="screen-section" style="display:<?= $screen === 'categories' ? 'block' : 'none' ?>;">
            <?php if ($sup_id): ?>
                <div class="breadcrumb">
                    <a href="products.php?screen=suppliers">Доставчици</a>
                    <span class="sep">›</span>
                    <span><?= htmlspecialchars($supplier_name) ?></span>
                </div>
                <div class="section-title">Категории на <?= htmlspecialchars($supplier_name) ?></div>
                <div id="categoryList" style="background:var(--bg-card);margin:0 16px;border-radius:12px;border:1px solid var(--border-subtle);overflow:hidden;"></div>
            <?php else: ?>
                <div class="section-title">Всички категории</div>
                <div class="swipe-container" id="categoryCards"></div>
            <?php endif; ?>
        </section>

        <!-- ====== SCREEN: PRODUCTS ====== -->
        <section id="screenProducts" class="screen-section" style="display:<?= $screen === 'products' ? 'block' : 'none' ?>;">
            <?php if ($sup_id || $cat_id): ?>
                <div class="breadcrumb">
                    <?php if ($sup_id): ?>
                        <a href="products.php?screen=suppliers">Дост.</a>
                        <span class="sep">›</span>
                        <a href="products.php?screen=categories&sup=<?= $sup_id ?>"><?= htmlspecialchars($supplier_name) ?></a>
                    <?php endif; ?>
                    <?php if ($cat_id): ?>
                        <?php if ($sup_id): ?><span class="sep">›</span><?php else: ?>
                            <a href="products.php?screen=categories">Категории</a><span class="sep">›</span>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($category_name) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($cross_link): ?>
                    <div class="cross-link" onclick="window.location='products.php?screen=products&cat=<?= $cat_id ?>'">
                        📂 Виж всички <?= htmlspecialchars($cross_link['cat_name']) ?> (<?= $cross_link['suppliers'] ?> дост. · <?= $cross_link['count'] ?> арт.)
                    </div>
                <?php endif; ?>
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
                    <?php if ($can_see_margin): ?>
                    <div class="sort-option" data-sort="margin_desc" onclick="setSort('margin_desc')">Марж ↓</div>
                    <?php endif; ?>
                    <div class="sort-option" data-sort="newest" onclick="setSort('newest')">Най-нови</div>
                </div>
            </div>
            <div id="productsList" style="padding:0 16px;margin-top:8px;"></div>
            <div id="productsPagination" class="pagination"></div>
        </section>

    </div><!-- /main-wrap -->

    <!-- ====== SCREEN NAV (4 sticky buttons) ====== -->
    <div class="screen-nav">
        <div class="screen-nav-inner">
            <button class="snav-btn <?= $screen === 'home' ? 'active' : '' ?>" onclick="goScreen('home')">
                <span class="snav-icon">🏠</span>
                <span>Начало</span>
            </button>
            <button class="snav-btn <?= $screen === 'suppliers' ? 'active' : '' ?>" onclick="goScreen('suppliers')">
                <span class="snav-icon">📦</span>
                <span>Доставчици</span>
            </button>
            <button class="snav-btn <?= ($screen === 'categories') ? 'active' : '' ?>" onclick="goScreen('categories')">
                <span class="snav-icon">🏷</span>
                <span>Категории</span>
            </button>
            <button class="snav-btn <?= $screen === 'products' ? 'active' : '' ?>" onclick="goScreen('products')">
                <span class="snav-icon">📋</span>
                <span>Артикули</span>
            </button>
        </div>
    </div>

    <!-- FAB Add button -->
    <?php if ($can_add): ?>
    <button class="fab-add" onclick="openAddModal()" title="Добави артикул">+</button>
    <?php endif; ?>

    <!-- ====== BOTTOM NAV ====== -->
    <nav class="bottom-nav">
        <a href="chat.php" class="bnav-tab"><span class="bnav-icon">✦</span>AI</a>
        <a href="warehouse.php" class="bnav-tab"><span class="bnav-icon">📦</span>Склад</a>
        <a href="stats.php" class="bnav-tab"><span class="bnav-icon">📊</span>Справки</a>
        <a href="actions.php" class="bnav-tab"><span class="bnav-icon">⚡</span>Въвеждане</a>
    </nav>

    <!-- ====== PRODUCT DETAIL DRAWER ====== -->
    <div class="drawer-overlay" id="detailOverlay" onclick="closeDrawer('detail')"></div>
    <div class="drawer" id="detailDrawer">
        <div class="drawer-handle"></div>
        <div class="drawer-header">
            <h3 id="detailTitle">Артикул</h3>
            <button class="drawer-close" onclick="closeDrawer('detail')">✕</button>
        </div>
        <div class="drawer-body" id="detailBody">
            <!-- Populated by JS -->
        </div>
    </div>

    <!-- ====== AI ANALYSIS DRAWER ====== -->
    <div class="drawer-overlay" id="aiOverlay" onclick="closeDrawer('ai')"></div>
    <div class="drawer" id="aiDrawer">
        <div class="drawer-handle"></div>
        <div class="drawer-header">
            <h3>✦ AI Анализ</h3>
            <button class="drawer-close" onclick="closeDrawer('ai')">✕</button>
        </div>
        <div class="drawer-body" id="aiBody">
            <div style="text-align:center;padding:20px;color:var(--text-secondary);">Зареждам...</div>
        </div>
    </div>

    <!-- ====== FILTER DRAWER ====== -->
    <div class="drawer-overlay" id="filterOverlay" onclick="closeDrawer('filter')"></div>
    <div class="drawer" id="filterDrawer">
        <div class="drawer-handle"></div>
        <div class="drawer-header">
            <h3>Филтри</h3>
            <button class="drawer-close" onclick="closeDrawer('filter')">✕</button>
        </div>
        <div class="drawer-body">
            <div class="filter-section">
                <div class="fs-title">Категория</div>
                <div class="filter-chips" id="filterCats">
                    <?php foreach ($all_categories as $cat): ?>
                        <button class="filter-chip" data-cat="<?= $cat['id'] ?>" onclick="toggleFilterChip(this, 'cat')"><?= htmlspecialchars($cat['name']) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="filter-section">
                <div class="fs-title">Доставчик</div>
                <div class="filter-chips" id="filterSups">
                    <?php foreach ($all_suppliers as $sup): ?>
                        <button class="filter-chip" data-sup="<?= $sup['id'] ?>" onclick="toggleFilterChip(this, 'sup')"><?= htmlspecialchars($sup['name']) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="filter-section">
                <div class="fs-title">Наличност</div>
                <div class="filter-chips">
                    <button class="filter-chip" data-stock="all" onclick="toggleFilterChip(this, 'stock')">Всички</button>
                    <button class="filter-chip" data-stock="in" onclick="toggleFilterChip(this, 'stock')">В наличност</button>
                    <button class="filter-chip" data-stock="low" onclick="toggleFilterChip(this, 'stock')">Ниска</button>
                    <button class="filter-chip" data-stock="out" onclick="toggleFilterChip(this, 'stock')">Изчерпани</button>
                </div>
            </div>
            <div class="filter-section">
                <div class="fs-title">Ценови диапазон</div>
                <div class="price-range">
                    <input type="number" class="form-control" id="priceFrom" placeholder="От" style="width:48%;">
                    <span style="color:var(--text-secondary);">—</span>
                    <input type="number" class="form-control" id="priceTo" placeholder="До" style="width:48%;">
                </div>
            </div>
            <button class="action-btn primary wide" onclick="applyFilters()" style="margin-top:12px;">Приложи филтри</button>
            <button class="action-btn wide" onclick="clearFilters()" style="margin-top:6px;">Изчисти</button>
        </div>
    </div>

    <!-- ====== ADD/EDIT MODAL ====== -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-header">
            <button onclick="closeAddModal()" style="background:transparent;border:none;color:var(--text-secondary);font-size:1.1rem;cursor:pointer;padding:4px;">✕</button>
            <h2 id="modalTitle">Нов артикул</h2>
            <div style="width:28px;"></div>
        </div>
        <div class="wizard-steps" id="wizardSteps">
            <div class="wiz-step active" data-step="0"></div>
            <div class="wiz-step" data-step="1"></div>
            <div class="wiz-step" data-step="2"></div>
            <div class="wiz-step" data-step="3"></div>
        </div>
        <div class="modal-body">
            <!-- Step 0: How to add? -->
            <div class="wizard-page active" data-page="0">
                <div class="ai-pulse-btn" onclick="openCamera('ai')">
                    <div class="ai-pulse-icon">✦</div>
                    <div class="ai-pulse-text">Снимай и AI попълва</div>
                    <div class="ai-pulse-sub">Камерата разпознава продукта автоматично</div>
                </div>
                <div style="text-align:center;color:var(--text-secondary);font-size:0.75rem;margin:8px 0;">или</div>
                <button class="action-btn wide" onclick="wizardVoice()" style="margin:0 16px;width:calc(100% - 32px);">
                    🎤 Продиктувай с глас
                </button>
                <div style="height:12px;"></div>
                <button class="action-btn wide" onclick="goWizardStep(1)" style="margin:0 16px;width:calc(100% - 32px);">
                    ⌨️ Попълни ръчно
                </button>
            </div>

            <!-- Step 1: Basic info -->
            <div class="wizard-page" data-page="1">
                <div style="padding:0 16px;">
                    <div class="form-group">
                        <label class="form-label">Име на артикула</label>
                        <input type="text" class="form-control" id="addName" placeholder="напр. Nike Air Max 90">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Баркод</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" class="form-control" id="addBarcode" placeholder="Сканирай или напиши" style="flex:1;">
                            <button class="action-btn" onclick="openCamera('barcode_add')" style="flex-shrink:0;width:44px;">📷</button>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label class="form-label">Цена дребно (€)</label>
                            <input type="number" step="0.01" class="form-control" id="addRetailPrice" placeholder="0.00">
                        </div>
                        <?php if ($can_see_cost): ?>
                        <div class="form-group">
                            <label class="form-label">Доставна цена (€)</label>
                            <input type="number" step="0.01" class="form-control" id="addCostPrice" placeholder="0.00">
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Доставчик</label>
                        <select class="form-control form-select" id="addSupplier">
                            <option value="">— Избери —</option>
                            <?php foreach ($all_suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Категория</label>
                        <select class="form-control form-select" id="addCategory">
                            <option value="">— Избери —</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
                        <button class="action-btn" onclick="goWizardStep(0)">← Назад</button>
                        <button class="action-btn primary" onclick="goWizardStep(2)">Напред →</button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Variations -->
            <div class="wizard-page" data-page="2">
                <div style="padding:0 16px;">
                    <div class="form-group">
                        <label class="form-label">Тип артикул</label>
                        <div style="display:flex;gap:8px;">
                            <button class="action-btn" id="typeSimple" onclick="setProductType('simple')" style="flex:1;">Единичен</button>
                            <button class="action-btn" id="typeVariant" onclick="setProductType('variant')" style="flex:1;">С вариации</button>
                        </div>
                    </div>
                    <div id="variantSection" style="display:none;">
                        <div class="form-group">
                            <label class="form-label">Размери</label>
                            <div class="size-chips" id="sizeChips">
                                <?php foreach (['XS','S','M','L','XL','XXL','3XL','36','37','38','39','40','41','42','43','44','45','46'] as $sz): ?>
                                    <button class="size-chip" onclick="toggleSize(this)" data-size="<?= $sz ?>"><?= $sz ?></button>
                                <?php endforeach; ?>
                            </div>
                            <div style="display:flex;gap:8px;margin-top:8px;">
                                <input type="text" class="form-control" id="customSize" placeholder="Друг размер" style="flex:1;">
                                <button class="action-btn" onclick="addCustomSize()">+</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Цветове</label>
                            <div class="color-dots" id="colorDots">
                                <?php
                                $colors = ['#000000','#FFFFFF','#1E3A5F','#8B0000','#2E4A2E','#4A3728','#808080','#FFD700','#FF69B4','#FF4500','#4169E1','#800080'];
                                $color_names = ['Черен','Бял','Тъмно син','Бордо','Тъмно зелен','Кафяв','Сив','Златен','Розов','Оранжев','Кралско син','Лилав'];
                                foreach ($colors as $idx => $clr): ?>
                                    <div class="color-dot" style="background:<?= $clr ?>;<?= $clr === '#FFFFFF' ? 'border:1px solid rgba(255,255,255,0.3);' : '' ?>" onclick="toggleColor(this)" data-color="<?= $clr ?>" data-name="<?= $color_names[$idx] ?>" title="<?= $color_names[$idx] ?>"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                        <button class="action-btn" onclick="goWizardStep(1)">← Назад</button>
                        <button class="action-btn primary" onclick="goWizardStep(3)">Напред →</button>
                    </div>
                </div>
            </div>

            <!-- Step 3: Details + Save -->
            <div class="wizard-page" data-page="3">
                <div style="padding:0 16px;">
                    <div class="form-group">
                        <label class="form-label">Код</label>
                        <input type="text" class="form-control" id="addCode" placeholder="Вътрешен код (незадължително)">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label class="form-label">Мерна единица</label>
                            <select class="form-control form-select" id="addUnit">
                                <option value="бр">бр</option>
                                <option value="кг">кг</option>
                                <option value="м">м</option>
                                <option value="чифт">чифт</option>
                                <option value="кутия">кутия</option>
                                <option value="пакет">пакет</option>
                                <option value="комплект">комплект</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Мин. наличност</label>
                            <input type="number" class="form-control" id="addMinQty" placeholder="0" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Локация (рафт/ред)</label>
                        <input type="text" class="form-control" id="addLocation" placeholder="напр. R3-P5">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Описание (AI генерира)</label>
                        <textarea class="form-control" id="addDescription" rows="3" placeholder="AI ще генерира описание автоматично"></textarea>
                        <button class="action-btn" onclick="aiGenerateDescription()" style="margin-top:6px;width:100%;">✦ AI описание</button>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                        <button class="action-btn" onclick="goWizardStep(2)">← Назад</button>
                        <button class="action-btn primary" onclick="saveProduct()" id="saveProductBtn" style="min-width:120px;">💾 Запази</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== CAMERA OVERLAY ====== -->
    <div class="camera-overlay" id="cameraOverlay">
        <video class="camera-video" id="cameraVideo" playsinline autoplay></video>
        <div class="scan-line" id="scanLine" style="display:none;"></div>
        <canvas id="cameraCanvas" style="display:none;"></canvas>
        <div class="camera-controls">
            <button class="camera-btn close-cam" onclick="closeCamera()">✕</button>
            <button class="camera-btn capture" id="captureBtn" onclick="capturePhoto()">📷</button>
        </div>
    </div>

    <!-- ====== AI IMAGE STUDIO DRAWER ====== -->
    <div class="drawer-overlay" id="studioOverlay" onclick="closeDrawer('studio')"></div>
    <div class="drawer" id="studioDrawer">
        <div class="drawer-handle"></div>
        <div class="drawer-header">
            <h3>📸 AI Image Studio</h3>
            <button class="drawer-close" onclick="closeDrawer('studio')">✕</button>
        </div>
        <div class="drawer-body" id="studioBody">
            <div id="studioProductImg" style="text-align:center;margin-bottom:12px;"></div>
            <div class="ai-studio-grid">
                <div class="ai-studio-btn wide" onclick="aiImageProcess('bg_removal')">
                    <div class="asb-icon">🖼</div>
                    <div class="asb-name">Бял фон</div>
                    <div class="asb-price">€0,05 / снимка</div>
                </div>
                <div class="ai-studio-btn" id="studioTryonMan" onclick="aiImageProcess('tryon_man')">
                    <div class="asb-icon">👨</div>
                    <div class="asb-name">Модел мъж</div>
                    <div class="asb-price">€0,50</div>
                </div>
                <div class="ai-studio-btn" id="studioTryonWoman" onclick="aiImageProcess('tryon_woman')">
                    <div class="asb-icon">👩</div>
                    <div class="asb-name">Модел жена</div>
                    <div class="asb-price">€0,50</div>
                </div>
                <div class="ai-studio-btn" id="studioTryonChild" onclick="aiImageProcess('tryon_child')">
                    <div class="asb-icon">👦</div>
                    <div class="asb-name">Модел дете</div>
                    <div class="asb-price">€0,50</div>
                </div>
                <div class="ai-studio-btn" id="studioTryonTeenM" onclick="aiImageProcess('tryon_teen_m')">
                    <div class="asb-icon">🧑</div>
                    <div class="asb-name">Тийнейджър</div>
                    <div class="asb-price">€0,50</div>
                </div>
                <div class="ai-studio-btn" id="studioTryonTeenF" onclick="aiImageProcess('tryon_teen_f')">
                    <div class="asb-icon">👧</div>
                    <div class="asb-name">Тийнейджърка</div>
                    <div class="asb-price">€0,50</div>
                </div>
            </div>
            <div style="font-size:0.7rem;color:var(--text-secondary);text-align:center;margin-top:10px;font-style:italic;">AI модел е достъпен само за дрехи</div>
            <div class="credits-info" id="studioCredits">Зареждам кредити...</div>
        </div>
    </div>

    <!-- ====== JAVASCRIPT ====== -->
    <script>
    // ============================================================
    // STATE
    // ============================================================
    const STATE = {
        storeId: <?= (int)$store_id ?>,
        screen: '<?= $screen ?>',
        supId: <?= $sup_id ? $sup_id : 'null' ?>,
        catId: <?= $cat_id ? $cat_id : 'null' ?>,
        canAdd: <?= $can_add ? 'true' : 'false' ?>,
        canSeeCost: <?= $can_see_cost ? 'true' : 'false' ?>,
        canSeeMargin: <?= $can_see_margin ? 'true' : 'false' ?>,
        currentSort: 'name',
        currentFilter: 'all',
        currentPage: 1,
        editProductId: null,
        productType: 'simple',
        selectedSizes: [],
        selectedColors: [],
        filterCat: null,
        filterSup: null,
        filterStock: 'all',
        filterPriceFrom: null,
        filterPriceTo: null,
        cameraMode: null, // 'scan' | 'ai' | 'barcode_add'
        cameraStream: null,
        barcodeDetector: null,
        barcodeInterval: null,
        recognition: null,
        isListening: false,
        studioProductId: null
    };

    // ============================================================
    // UTILITIES
    // ============================================================
    function fmtPrice(val) {
        if (val === null || val === undefined) return '—';
        return parseFloat(val).toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €';
    }

    function fmtNum(val) {
        if (val === null || val === undefined) return '—';
        return parseInt(val).toLocaleString('de-DE');
    }

    function stockClass(qty, min) {
        if (qty <= 0) return 'out';
        if (min > 0 && qty <= min) return 'low';
        return 'ok';
    }

    function stockBarColor(qty, min) {
        if (qty <= 0) return 'red';
        if (min > 0 && qty <= min) return 'yellow';
        return 'green';
    }

    function showToast(msg, type = 'info') {
        const c = document.getElementById('toasts');
        const t = document.createElement('div');
        t.className = `toast ${type}`;
        t.innerHTML = `<span>${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}</span> ${msg}`;
        c.appendChild(t);
        requestAnimationFrame(() => { t.classList.add('show'); });
        setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3000);
    }

    async function fetchJSON(url, opts = {}) {
        try {
            const r = await fetch(url, opts);
            return await r.json();
        } catch (e) {
            console.error('Fetch error:', e);
            showToast('Грешка при зареждане', 'error');
            return null;
        }
    }

    function productCardHTML(p) {
        const sc = stockClass(p.store_stock || p.qty || 0, p.min_quantity || 0);
        const bc = stockBarColor(p.store_stock || p.qty || 0, p.min_quantity || 0);
        const qty = p.store_stock || p.qty || 0;
        const thumb = p.image ? `<img src="${p.image}" alt="">` : `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="rgba(99,102,241,0.4)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>`;
        const discount = (p.discount_pct && p.discount_pct > 0) ? `<div class="pc-discount">-${p.discount_pct}%</div>` : '';
        return `
            <div class="product-card" onclick="openProductDetail(${p.id})">
                <div class="stock-bar ${bc}"></div>
                <div class="pc-thumb">${thumb}</div>
                <div class="pc-info">
                    <div class="pc-name">${escHTML(p.name)}</div>
                    <div class="pc-meta">
                        ${p.code ? `<span>${escHTML(p.code)}</span>` : ''}
                        ${p.supplier_name ? `<span>${escHTML(p.supplier_name)}</span>` : ''}
                    </div>
                </div>
                <div class="pc-right">
                    <div class="pc-price">${fmtPrice(p.retail_price)}</div>
                    <div class="pc-stock ${sc}">${qty} ${p.unit || 'бр.'}</div>
                </div>
                ${discount}
            </div>
        `;
    }

    function escHTML(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // ============================================================
    // SCREEN NAVIGATION
    // ============================================================
    function goScreen(screen, params = {}) {
        let url = `products.php?screen=${screen}`;
        if (params.sup) url += `&sup=${params.sup}`;
        if (params.cat) url += `&cat=${params.cat}`;
        window.location.href = url;
    }

    function switchStore(sid) {
        STATE.storeId = parseInt(sid);
        loadCurrentScreen();
    }

    // ============================================================
    // HOME SCREEN
    // ============================================================
    let homeTab = 'all';
    let homePageNum = 1;

    async function loadHomeScreen() {
        const data = await fetchJSON(`products.php?ajax=home_stats&store_id=${STATE.storeId}`);
        if (!data) return;

        // Stats
        if (STATE.canSeeMargin) {
            document.getElementById('statCapital').textContent = fmtPrice(data.capital);
            document.getElementById('statMargin').textContent = data.avg_margin !== null ? data.avg_margin + '%' : '—';
        } else {
            const sp = document.getElementById('statProducts');
            const su = document.getElementById('statUnits');
            if (sp) sp.textContent = fmtNum(data.counts?.total_products);
            if (su) su.textContent = fmtNum(data.counts?.total_units);
        }

        // Counts in tabs
        document.getElementById('countAll').textContent = data.counts?.total_products || 0;
        document.getElementById('countLow').textContent = data.low_stock?.length || 0;
        document.getElementById('countOut').textContent = data.out_of_stock?.length || 0;

        // Zombie section
        if (data.zombies && data.zombies.length > 0) {
            document.getElementById('collapseZombie').style.display = 'block';
            document.getElementById('zombieCount').textContent = data.zombies.length;
            document.getElementById('zombieList').innerHTML = data.zombies.map(p => {
                return `<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;">
                    <div class="stock-bar red"></div>
                    <div class="pc-thumb">${p.image ? `<img src="${p.image}">` : '💀'}</div>
                    <div class="pc-info">
                        <div class="pc-name">${escHTML(p.name)}</div>
                        <div class="pc-meta"><span>${p.days_stale} дни без продажба</span></div>
                    </div>
                    <div class="pc-right">
                        <div class="pc-price">${fmtPrice(p.retail_price)}</div>
                        <div class="pc-stock out">${p.qty} бр.</div>
                    </div>
                </div>`;
            }).join('');
        }

        // Low stock section
        if (data.low_stock && data.low_stock.length > 0) {
            document.getElementById('collapseLow').style.display = 'block';
            document.getElementById('lowCount').textContent = data.low_stock.length;
            document.getElementById('lowList').innerHTML = data.low_stock.map(p => {
                return `<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;">
                    <div class="stock-bar yellow"></div>
                    <div class="pc-thumb">${p.image ? `<img src="${p.image}">` : '⚠️'}</div>
                    <div class="pc-info">
                        <div class="pc-name">${escHTML(p.name)}</div>
                        <div class="pc-meta"><span>Минимум: ${p.min_quantity}</span></div>
                    </div>
                    <div class="pc-right">
                        <div class="pc-price">${fmtPrice(p.retail_price)}</div>
                        <div class="pc-stock low">${p.qty} бр.</div>
                    </div>
                </div>`;
            }).join('');
        }

        // Top sellers
        if (data.top_sellers && data.top_sellers.length > 0) {
            document.getElementById('collapseTop').style.display = 'block';
            document.getElementById('topCount').textContent = data.top_sellers.length;
            document.getElementById('topList').innerHTML = data.top_sellers.map((p, i) => {
                return `<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;">
                    <div class="stock-bar green"></div>
                    <div class="pc-thumb" style="font-size:1.2rem;font-weight:700;color:var(--indigo-300);">#${i+1}</div>
                    <div class="pc-info">
                        <div class="pc-name">${escHTML(p.name)}</div>
                        <div class="pc-meta"><span>${p.sold_qty} продадени</span></div>
                    </div>
                    <div class="pc-right">
                        <div class="pc-price">${fmtPrice(p.revenue)}</div>
                    </div>
                </div>`;
            }).join('');
        }

        // Slow movers
        if (data.slow_movers && data.slow_movers.length > 0) {
            document.getElementById('collapseSlow').style.display = 'block';
            document.getElementById('slowCount').textContent = data.slow_movers.length;
            document.getElementById('slowList').innerHTML = data.slow_movers.map(p => {
                return `<div class="product-card" onclick="openProductDetail(${p.id})" style="margin-bottom:6px;">
                    <div class="stock-bar yellow"></div>
                    <div class="pc-thumb">${p.image ? `<img src="${p.image}">` : '🐌'}</div>
                    <div class="pc-info">
                        <div class="pc-name">${escHTML(p.name)}</div>
                        <div class="pc-meta"><span>${p.days_stale} дни без продажба</span></div>
                    </div>
                    <div class="pc-right">
                        <div class="pc-price">${fmtPrice(p.retail_price)}</div>
                        <div class="pc-stock low">${p.qty} бр.</div>
                    </div>
                </div>`;
            }).join('');
        }

        // Load products list
        loadHomeProducts();
    }

    async function loadHomeProducts() {
        const flt = homeTab === 'all' ? '' : `&filter=${homeTab}`;
        const data = await fetchJSON(`products.php?ajax=products&store_id=${STATE.storeId}${flt}&page=${homePageNum}&sort=${STATE.currentSort}`);
        if (!data) return;
        const el = document.getElementById('homeProductsList');
        if (data.products.length === 0) {
            el.innerHTML = `<div class="empty-state"><div class="es-icon">📦</div><div class="es-text">Няма артикули</div></div>`;
        } else {
            el.innerHTML = data.products.map(productCardHTML).join('');
        }
        renderPagination('homePagination', data.page, data.pages, (p) => { homePageNum = p; loadHomeProducts(); });
    }

    function setHomeTab(tab, btn) {
        homeTab = tab;
        homePageNum = 1;
        document.querySelectorAll('.tabs-row .tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        loadHomeProducts();
    }

    // ============================================================
    // SUPPLIERS SCREEN
    // ============================================================
    async function loadSuppliers() {
        const data = await fetchJSON(`products.php?ajax=suppliers&store_id=${STATE.storeId}`);
        if (!data) return;
        const el = document.getElementById('supplierCards');
        if (data.length === 0) {
            el.innerHTML = `<div class="empty-state" style="width:100%;"><div class="es-icon">📦</div><div class="es-text">Няма доставчици</div></div>`;
            return;
        }
        el.innerHTML = data.map(s => {
            const ok = s.product_count - s.low_count - s.out_count;
            return `<div class="supplier-card" onclick="goScreen('categories', {sup: ${s.id}})">
                <div class="sc-name">${escHTML(s.name)}</div>
                <div class="sc-count">${s.product_count} артикула · ${fmtNum(s.total_stock)} бр.</div>
                <div class="sc-badges">
                    ${ok > 0 ? `<span class="sc-badge ok">✓ ${ok}</span>` : ''}
                    ${s.low_count > 0 ? `<span class="sc-badge low">↓ ${s.low_count}</span>` : ''}
                    ${s.out_count > 0 ? `<span class="sc-badge out">✕ ${s.out_count}</span>` : ''}
                </div>
                <div class="sc-arrow">›</div>
            </div>`;
        }).join('');

        // Stats
        const statsEl = document.getElementById('supplierStats');
        const sorted = [...data].sort((a, b) => b.total_stock - a.total_stock);
        statsEl.innerHTML = sorted.slice(0, 5).map((s, i) => `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(99,102,241,0.08);">
                <span style="font-size:0.8rem;"><span style="color:var(--indigo-400);font-weight:700;">#${i+1}</span> ${escHTML(s.name)}</span>
                <span style="font-size:0.8rem;font-weight:600;">${fmtNum(s.total_stock)} бр.</span>
            </div>
        `).join('');
    }

    // ============================================================
    // CATEGORIES SCREEN
    // ============================================================
    async function loadCategories() {
        const supParam = STATE.supId ? `&sup=${STATE.supId}` : '';
        const data = await fetchJSON(`products.php?ajax=categories&store_id=${STATE.storeId}${supParam}`);
        if (!data) return;

        if (STATE.supId) {
            // List mode (supplier's categories)
            const el = document.getElementById('categoryList');
            if (data.length === 0) {
                el.innerHTML = `<div class="empty-state" style="padding:20px;"><div class="es-icon">🏷</div><div class="es-text">Няма категории</div></div>`;
                return;
            }
            el.innerHTML = data.map(c => `
                <div class="cat-list-item" onclick="goScreen('products', {sup: ${STATE.supId}, cat: ${c.id}})">
                    <div class="cli-left">
                        <div class="cli-icon">🏷</div>
                        <div>
                            <div class="cli-name">${escHTML(c.name)}</div>
                            <div class="cli-count">${c.product_count} артикула · ${fmtNum(c.total_stock)} бр.</div>
                        </div>
                    </div>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-secondary);"><polyline points="9 18 15 12 9 6"/></svg>
                </div>
            `).join('');
        } else {
            // Swipe cards (global)
            const el = document.getElementById('categoryCards');
            if (data.length === 0) {
                el.innerHTML = `<div class="empty-state" style="width:100%;"><div class="es-icon">🏷</div><div class="es-text">Няма категории</div></div>`;
                return;
            }
            el.innerHTML = data.map(c => `
                <div class="category-card" onclick="goScreen('products', {cat: ${c.id}})">
                    <div class="cc-name">${escHTML(c.name)}</div>
                    <div class="cc-info">${c.product_count} артикула${c.supplier_count ? ` · ${c.supplier_count} дост.` : ''}</div>
                    <div class="cc-info">${fmtNum(c.total_stock)} бр.</div>
                </div>
            `).join('');
        }
    }

    // ============================================================
    // PRODUCTS LIST SCREEN
    // ============================================================
    async function loadProductsList() {
        let params = `store_id=${STATE.storeId}&sort=${STATE.currentSort}&page=${STATE.currentPage}`;
        if (STATE.supId) params += `&sup=${STATE.supId}`;
        if (STATE.catId) params += `&cat=${STATE.catId}`;
        if (STATE.currentFilter !== 'all') params += `&filter=${STATE.currentFilter}`;

        const data = await fetchJSON(`products.php?ajax=products&${params}`);
        if (!data) return;
        const el = document.getElementById('productsList');
        if (data.products.length === 0) {
            el.innerHTML = `<div class="empty-state"><div class="es-icon">📋</div><div class="es-text">Няма артикули с тези филтри</div></div>`;
        } else {
            el.innerHTML = data.products.map(productCardHTML).join('');
        }
        renderPagination('productsPagination', data.page, data.pages, (p) => { STATE.currentPage = p; loadProductsList(); });
    }

    // ============================================================
    // PAGINATION
    // ============================================================
    function renderPagination(containerId, current, total, onPageClick) {
        const el = document.getElementById(containerId);
        if (total <= 1) { el.innerHTML = ''; return; }
        let html = '';
        if (current > 1) html += `<button class="page-btn" onclick="arguments[0].stopPropagation();" data-p="${current-1}">‹</button>`;
        const start = Math.max(1, current - 2);
        const end = Math.min(total, current + 2);
        for (let i = start; i <= end; i++) {
            html += `<button class="page-btn ${i === current ? 'active' : ''}" data-p="${i}">${i}</button>`;
        }
        if (current < total) html += `<button class="page-btn" data-p="${current+1}">›</button>`;
        el.innerHTML = html;
        el.querySelectorAll('.page-btn').forEach(btn => {
            btn.addEventListener('click', () => onPageClick(parseInt(btn.dataset.p)));
        });
    }

    // ============================================================
    // COLLAPSE SECTIONS
    // ============================================================
    function toggleCollapse(id) {
        const map = { zombie: 'collapseZombie', low: 'collapseLow', top: 'collapseTop', slow: 'collapseSlow' };
        const section = document.getElementById(map[id]);
        const header = section.querySelector('.collapse-header');
        const body = section.querySelector('.collapse-body');
        header.classList.toggle('open');
        body.classList.toggle('open');
    }

    // ============================================================
    // SORT
    // ============================================================
    function toggleSort() {
        document.getElementById('sortDropdown').classList.toggle('open');
    }

    function setSort(sort) {
        STATE.currentSort = sort;
        document.querySelectorAll('.sort-option').forEach(o => o.classList.toggle('active', o.dataset.sort === sort));
        document.getElementById('sortDropdown').classList.remove('open');
        if (STATE.screen === 'products') loadProductsList();
        else loadHomeProducts();
    }

    // ============================================================
    // SEARCH
    // ============================================================
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 1) { loadCurrentScreen(); return; }
        searchTimeout = setTimeout(async () => {
            const data = await fetchJSON(`products.php?ajax=search&q=${encodeURIComponent(q)}&store_id=${STATE.storeId}`);
            if (!data) return;
            const el = document.getElementById(STATE.screen === 'products' ? 'productsList' : 'homeProductsList');
            if (data.length === 0) {
                el.innerHTML = `<div class="empty-state"><div class="es-icon">🔍</div><div class="es-text">Нищо не е намерено за "${escHTML(q)}"</div></div>`;
            } else {
                el.innerHTML = data.map(p => productCardHTML({...p, store_stock: p.total_stock})).join('');
            }
        }, 300);
    });

    // ============================================================
    // PRODUCT DETAIL DRAWER
    // ============================================================
    async function openProductDetail(id) {
        openDrawer('detail');
        document.getElementById('detailBody').innerHTML = '<div style="text-align:center;padding:20px;"><div class="skeleton" style="width:60%;height:20px;margin:0 auto 12px;"></div><div class="skeleton" style="width:80%;height:14px;margin:0 auto 8px;"></div><div class="skeleton" style="width:40%;height:14px;margin:0 auto;"></div></div>';

        const data = await fetchJSON(`products.php?ajax=product_detail&id=${id}`);
        if (!data || data.error) { showToast('Грешка при зареждане', 'error'); closeDrawer('detail'); return; }

        const p = data.product;
        document.getElementById('detailTitle').textContent = p.name;

        let html = '';

        // Image
        if (p.image) {
            html += `<div style="text-align:center;margin-bottom:12px;"><img src="${p.image}" style="max-width:200px;border-radius:12px;border:1px solid var(--border-subtle);"></div>`;
        }

        // Details
        html += `<div class="detail-row"><span class="detail-label">Код</span><span class="detail-value">${escHTML(p.code) || '—'}</span></div>`;
        html += `<div class="detail-row"><span class="detail-label">Баркод</span><span class="detail-value">${escHTML(p.barcode) || '—'}</span></div>`;
        html += `<div class="detail-row"><span class="detail-label">Цена дребно</span><span class="detail-value">${fmtPrice(p.retail_price)}</span></div>`;
        if (STATE.canSeeCost) {
            html += `<div class="detail-row"><span class="detail-label">Доставна цена</span><span class="detail-value">${fmtPrice(p.cost_price)}</span></div>`;
            if (p.cost_price > 0) {
                const margin = (((p.retail_price - p.cost_price) / p.retail_price) * 100).toFixed(1);
                html += `<div class="detail-row"><span class="detail-label">Марж</span><span class="detail-value" style="color:${margin > 30 ? '#22c55e' : margin > 15 ? '#eab308' : '#ef4444'};">${margin}%</span></div>`;
            }
        }
        if (p.discount_pct > 0) {
            html += `<div class="detail-row"><span class="detail-label">Отстъпка</span><span class="detail-value" style="color:#ef4444;">-${p.discount_pct}%${p.discount_ends_at ? ` до ${p.discount_ends_at}` : ''}</span></div>`;
        }
        html += `<div class="detail-row"><span class="detail-label">Доставчик</span><span class="detail-value">${escHTML(p.supplier_name) || '—'}</span></div>`;
        html += `<div class="detail-row"><span class="detail-label">Категория</span><span class="detail-value">${escHTML(p.category_name) || '—'}</span></div>`;
        html += `<div class="detail-row"><span class="detail-label">Мин. наличност</span><span class="detail-value">${p.min_quantity || 0}</span></div>`;

        // Stock per store
        html += `<div style="margin-top:14px;"><div class="section-title" style="padding:0 0 8px;">Наличност по обекти</div>`;
        data.stocks.forEach(s => {
            const sc = s.qty > 0 ? 'ok' : 'out';
            html += `<div class="store-stock-row"><span class="ssr-name">${escHTML(s.store_name)}</span><span class="ssr-qty pc-stock ${sc}">${s.qty} бр.</span></div>`;
        });
        html += `</div>`;

        // Variations
        if (data.variations && data.variations.length > 0) {
            html += `<div style="margin-top:14px;"><div class="section-title" style="padding:0 0 8px;">Вариации</div>`;
            data.variations.forEach(v => {
                html += `<div class="product-card" onclick="openProductDetail(${v.id})" style="margin-bottom:4px;">
                    <div class="stock-bar ${stockBarColor(v.total_stock, 0)}"></div>
                    <div class="pc-info" style="margin-left:10px;">
                        <div class="pc-name" style="font-size:0.8rem;">${escHTML(v.name)}</div>
                        <div class="pc-meta"><span>${escHTML(v.code)}</span></div>
                    </div>
                    <div class="pc-right">
                        <div class="pc-price">${fmtPrice(v.retail_price)}</div>
                        <div class="pc-stock ${stockClass(v.total_stock, 0)}">${v.total_stock} бр.</div>
                    </div>
                </div>`;
            });
            html += `</div>`;
        }

        // Price history
        if (data.price_history && data.price_history.length > 0) {
            html += `<div style="margin-top:14px;"><div class="section-title" style="padding:0 0 8px;">История на цените</div>`;
            data.price_history.forEach(h => {
                html += `<div style="display:flex;justify-content:space-between;font-size:0.75rem;padding:4px 0;border-bottom:1px solid rgba(99,102,241,0.06);">
                    <span style="color:var(--text-secondary);">${h.changed_at}</span>
                    <span><span style="text-decoration:line-through;color:var(--text-secondary);">${fmtPrice(h.old_price)}</span> → ${fmtPrice(h.new_price)}</span>
                </div>`;
            });
            html += `</div>`;
        }

        // Action buttons
        html += `<div class="action-grid">`;
        if (STATE.canAdd) {
            html += `<button class="action-btn" onclick="editProduct(${p.id})">✏️ Редактирай</button>`;
        }
        html += `<button class="action-btn" onclick="openAIDrawer(${p.id})">✦ AI Съвет</button>`;
        html += `<button class="action-btn" onclick="openStudio(${p.id})">📸 AI Снимка</button>`;
        html += `<button class="action-btn primary" onclick="window.location='sale.php?product=${p.id}'">💰 Продажба</button>`;
        if (STATE.canAdd) {
            html += `<button class="action-btn danger wide" onclick="if(confirm('Деактивирай артикул?')) deactivateProduct(${p.id})">🗑 Деактивирай</button>`;
        }
        html += `</div>`;

        document.getElementById('detailBody').innerHTML = html;
    }

    // ============================================================
    // AI ANALYSIS DRAWER
    // ============================================================
    async function openAIDrawer(id) {
        closeDrawer('detail');
        setTimeout(() => openDrawer('ai'), 350);
        document.getElementById('aiBody').innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-secondary);">✦ Анализирам...</div>';

        const data = await fetchJSON(`products.php?ajax=ai_analyze&id=${id}`);
        if (!data || data.error) { document.getElementById('aiBody').innerHTML = '<div style="padding:20px;color:#ef4444;">Грешка при анализ</div>'; return; }

        let html = '';
        if (data.analysis.length === 0) {
            html = `<div class="ai-insight info"><span class="ai-icon">✓</span><span class="ai-text">Всичко е наред с този артикул. Без предупреждения.</span></div>`;
        } else {
            data.analysis.forEach(a => {
                html += `<div class="ai-insight ${a.severity}"><span class="ai-icon">${a.icon}</span><span class="ai-text">${a.text}</span></div>`;
            });
        }

        // Deeplinks
        html += `<div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:6px;">`;
        html += `<a class="ai-deeplink" href="chat.php?ctx=product&id=${id}">💬 Попитай AI</a>`;
        html += `<a class="ai-deeplink" href="stats.php?product=${id}">📊 Статистики</a>`;
        html += `</div>`;

        document.getElementById('aiBody').innerHTML = html;
    }

    // ============================================================
    // DRAWER MANAGEMENT
    // ============================================================
    function openDrawer(name) {
        document.getElementById(name + 'Overlay').classList.add('open');
        document.getElementById(name + 'Drawer').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer(name) {
        document.getElementById(name + 'Overlay').classList.remove('open');
        document.getElementById(name + 'Drawer').classList.remove('open');
        document.body.style.overflow = '';
    }

    // Swipe-to-close drawers
    ['detail', 'ai', 'filter', 'studio'].forEach(name => {
        const drawer = document.getElementById(name + 'Drawer');
        let startY = 0, currentY = 0, isDragging = false;
        drawer.addEventListener('touchstart', e => {
            if (e.target.closest('.drawer-body')?.scrollTop > 0) return;
            startY = e.touches[0].clientY;
            isDragging = true;
        }, { passive: true });
        drawer.addEventListener('touchmove', e => {
            if (!isDragging) return;
            currentY = e.touches[0].clientY - startY;
            if (currentY > 0) { drawer.style.transform = `translateY(${currentY}px)`; }
        }, { passive: true });
        drawer.addEventListener('touchend', () => {
            if (!isDragging) return;
            isDragging = false;
            if (currentY > 100) { closeDrawer(name); }
            drawer.style.transform = '';
            currentY = 0;
        });
    });

    // ============================================================
    // FILTER DRAWER
    // ============================================================
    function openFilterDrawer() { openDrawer('filter'); }

    function toggleFilterChip(el, type) {
        if (type === 'stock') {
            el.parentElement.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            STATE.filterStock = el.dataset.stock;
        } else {
            el.classList.toggle('selected');
        }
    }

    function applyFilters() {
        const selectedCat = document.querySelector('#filterCats .filter-chip.selected');
        const selectedSup = document.querySelector('#filterSups .filter-chip.selected');
        STATE.filterCat = selectedCat ? selectedCat.dataset.cat : null;
        STATE.filterSup = selectedSup ? selectedSup.dataset.sup : null;
        STATE.filterPriceFrom = document.getElementById('priceFrom').value || null;
        STATE.filterPriceTo = document.getElementById('priceTo').value || null;

        closeDrawer('filter');

        let url = `products.php?screen=products`;
        if (STATE.filterSup) url += `&sup=${STATE.filterSup}`;
        if (STATE.filterCat) url += `&cat=${STATE.filterCat}`;
        if (STATE.filterStock !== 'all') url += `&filter=${STATE.filterStock}`;
        window.location.href = url;
    }

    function clearFilters() {
        document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('selected'));
        document.getElementById('priceFrom').value = '';
        document.getElementById('priceTo').value = '';
        STATE.filterCat = null;
        STATE.filterSup = null;
        STATE.filterStock = 'all';
    }

    // ============================================================
    // ADD/EDIT MODAL
    // ============================================================
    function openAddModal() {
        STATE.editProductId = null;
        document.getElementById('modalTitle').textContent = 'Нов артикул';
        clearAddForm();
        goWizardStep(0);
        document.getElementById('addModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    function clearAddForm() {
        ['addName','addBarcode','addRetailPrice','addCode','addMinQty','addLocation','addDescription'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const cp = document.getElementById('addCostPrice');
        if (cp) cp.value = '';
        document.getElementById('addSupplier').value = '';
        document.getElementById('addCategory').value = '';
        document.getElementById('addUnit').value = 'бр';
        STATE.selectedSizes = [];
        STATE.selectedColors = [];
        STATE.productType = 'simple';
        document.querySelectorAll('.size-chip').forEach(c => c.classList.remove('selected'));
        document.querySelectorAll('.color-dot').forEach(c => c.classList.remove('selected'));
        setProductType('simple');
    }

    function goWizardStep(step) {
        document.querySelectorAll('.wizard-page').forEach(p => p.classList.remove('active'));
        document.querySelector(`.wizard-page[data-page="${step}"]`).classList.add('active');
        document.querySelectorAll('.wiz-step').forEach((s, i) => {
            s.classList.toggle('active', i === step);
            s.classList.toggle('done', i < step);
        });
    }

    function setProductType(type) {
        STATE.productType = type;
        document.getElementById('typeSimple').classList.toggle('primary', type === 'simple');
        document.getElementById('typeVariant').classList.toggle('primary', type === 'variant');
        document.getElementById('typeSimple').style.borderColor = type === 'simple' ? 'var(--indigo-500)' : '';
        document.getElementById('typeVariant').style.borderColor = type === 'variant' ? 'var(--indigo-500)' : '';
        document.getElementById('variantSection').style.display = type === 'variant' ? 'block' : 'none';
    }

    function toggleSize(el) {
        el.classList.toggle('selected');
        const sz = el.dataset.size;
        const idx = STATE.selectedSizes.indexOf(sz);
        if (idx > -1) STATE.selectedSizes.splice(idx, 1);
        else STATE.selectedSizes.push(sz);
    }

    function addCustomSize() {
        const input = document.getElementById('customSize');
        const val = input.value.trim();
        if (!val) return;
        if (STATE.selectedSizes.includes(val)) { input.value = ''; return; }
        STATE.selectedSizes.push(val);
        const chip = document.createElement('button');
        chip.className = 'size-chip selected';
        chip.dataset.size = val;
        chip.textContent = val;
        chip.onclick = function() { toggleSize(this); };
        document.getElementById('sizeChips').appendChild(chip);
        input.value = '';
    }

    function toggleColor(el) {
        el.classList.toggle('selected');
        const clr = el.dataset.color;
        const idx = STATE.selectedColors.indexOf(clr);
        if (idx > -1) STATE.selectedColors.splice(idx, 1);
        else STATE.selectedColors.push(clr);
    }

    async function editProduct(id) {
        closeDrawer('detail');
        const data = await fetchJSON(`products.php?ajax=product_detail&id=${id}`);
        if (!data || data.error) { showToast('Грешка', 'error'); return; }
        const p = data.product;
        STATE.editProductId = id;
        document.getElementById('modalTitle').textContent = 'Редактирай';
        document.getElementById('addName').value = p.name || '';
        document.getElementById('addBarcode').value = p.barcode || '';
        document.getElementById('addRetailPrice').value = p.retail_price || '';
        const cp = document.getElementById('addCostPrice');
        if (cp) cp.value = p.cost_price || '';
        document.getElementById('addSupplier').value = p.supplier_id || '';
        document.getElementById('addCategory').value = p.category_id || '';
        document.getElementById('addCode').value = p.code || '';
        document.getElementById('addUnit').value = p.unit || 'бр';
        document.getElementById('addMinQty').value = p.min_quantity || 0;
        document.getElementById('addDescription').value = p.description || '';
        goWizardStep(1);
        document.getElementById('addModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    async function saveProduct() {
        const btn = document.getElementById('saveProductBtn');
        btn.disabled = true;
        btn.textContent = 'Запазвам...';

        const payload = {
            id: STATE.editProductId,
            name: document.getElementById('addName').value.trim(),
            barcode: document.getElementById('addBarcode').value.trim(),
            retail_price: parseFloat(document.getElementById('addRetailPrice').value) || 0,
            cost_price: document.getElementById('addCostPrice')?.value ? parseFloat(document.getElementById('addCostPrice').value) : null,
            supplier_id: document.getElementById('addSupplier').value || null,
            category_id: document.getElementById('addCategory').value || null,
            code: document.getElementById('addCode').value.trim(),
            unit: document.getElementById('addUnit').value,
            min_quantity: parseInt(document.getElementById('addMinQty').value) || 0,
            description: document.getElementById('addDescription').value.trim(),
            location: document.getElementById('addLocation').value.trim(),
            product_type: STATE.productType,
            sizes: STATE.selectedSizes,
            colors: STATE.selectedColors
        };

        if (!payload.name) { showToast('Въведи име', 'error'); btn.disabled = false; btn.textContent = '💾 Запази'; return; }

        try {
            const r = await fetch('product-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await r.json();
            if (result.success) {
                showToast(STATE.editProductId ? 'Артикулът е обновен' : 'Артикулът е добавен', 'success');
                closeAddModal();
                loadCurrentScreen();
            } else {
                showToast(result.error || 'Грешка при запазване', 'error');
            }
        } catch (e) {
            showToast('Мрежова грешка', 'error');
        }
        btn.disabled = false;
        btn.textContent = '💾 Запази';
    }

    async function deactivateProduct(id) {
        const r = await fetch('product-save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, deactivate: true })
        });
        const result = await r.json();
        if (result.success) {
            showToast('Артикулът е деактивиран', 'success');
            closeDrawer('detail');
            loadCurrentScreen();
        } else {
            showToast('Грешка', 'error');
        }
    }

    // ============================================================
    // AI DESCRIPTION GENERATION
    // ============================================================
    async function aiGenerateDescription() {
        const name = document.getElementById('addName').value.trim();
        if (!name) { showToast('Първо въведи име', 'error'); return; }
        const cat = document.getElementById('addCategory').selectedOptions[0]?.text || '';
        const sup = document.getElementById('addSupplier').selectedOptions[0]?.text || '';

        document.getElementById('addDescription').value = 'AI генерира описание...';

        const data = await fetchJSON(`products.php?ajax=ai_voice_fill`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text: `Генерирай кратко описание за ${name}, категория ${cat}, доставчик ${sup}. Само описанието, 1-2 изречения.` })
        });

        if (data && data.description) {
            document.getElementById('addDescription').value = data.description;
        } else {
            document.getElementById('addDescription').value = '';
            showToast('AI не успя да генерира описание', 'error');
        }
    }

    // ============================================================
    // CAMERA & BARCODE SCANNER
    // ============================================================
    async function openCamera(mode) {
        STATE.cameraMode = mode;
        const overlay = document.getElementById('cameraOverlay');
        const video = document.getElementById('cameraVideo');
        overlay.classList.add('open');

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
            });
            STATE.cameraStream = stream;
            video.srcObject = stream;

            if (mode === 'scan' || mode === 'barcode_add') {
                document.getElementById('scanLine').style.display = 'block';
                document.getElementById('captureBtn').style.display = 'none';
                startBarcodeScanning(video);
            } else {
                document.getElementById('scanLine').style.display = 'none';
                document.getElementById('captureBtn').style.display = '';
            }
        } catch (e) {
            showToast('Камерата не е достъпна', 'error');
            closeCamera();
        }
    }

    function closeCamera() {
        const overlay = document.getElementById('cameraOverlay');
        overlay.classList.remove('open');
        if (STATE.cameraStream) {
            STATE.cameraStream.getTracks().forEach(t => t.stop());
            STATE.cameraStream = null;
        }
        if (STATE.barcodeInterval) { clearInterval(STATE.barcodeInterval); STATE.barcodeInterval = null; }
        document.getElementById('scanLine').style.display = 'none';
        document.getElementById('captureBtn').style.display = '';
    }

    function startBarcodeScanning(video) {
        if ('BarcodeDetector' in window) {
            STATE.barcodeDetector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'qr_code', 'upc_a', 'upc_e'] });
            STATE.barcodeInterval = setInterval(async () => {
                try {
                    const barcodes = await STATE.barcodeDetector.detect(video);
                    if (barcodes.length > 0) {
                        const code = barcodes[0].rawValue;
                        clearInterval(STATE.barcodeInterval);
                        playBeep();
                        showGreenFlash();

                        if (STATE.cameraMode === 'barcode_add') {
                            document.getElementById('addBarcode').value = code;
                            closeCamera();
                        } else {
                            // Search by barcode
                            const data = await fetchJSON(`products.php?ajax=barcode&code=${encodeURIComponent(code)}&store_id=${STATE.storeId}`);
                            closeCamera();
                            if (data && !data.error) {
                                openProductDetail(data.id);
                            } else {
                                showToast(`Баркод ${code} не е намерен`, 'info');
                            }
                        }
                    }
                } catch (e) {}
            }, 300);
        } else {
            showToast('Баркод скенерът не е поддържан в този браузър', 'error');
            closeCamera();
        }
    }

    async function capturePhoto() {
        const video = document.getElementById('cameraVideo');
        const canvas = document.getElementById('cameraCanvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const imageData = canvas.toDataURL('image/jpeg', 0.8).split(',')[1];
        closeCamera();

        showToast('✦ AI анализира снимката...', 'info');

        const data = await fetchJSON(`products.php?ajax=ai_scan`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image: imageData })
        });

        if (data && !data.error) {
            // AI pre-fill the form
            if (data.name) document.getElementById('addName').value = data.name;
            if (data.retail_price) document.getElementById('addRetailPrice').value = data.retail_price;
            if (data.description) document.getElementById('addDescription').value = data.description;

            // Match supplier
            if (data.supplier) {
                const opts = document.getElementById('addSupplier').options;
                for (let i = 0; i < opts.length; i++) {
                    if (opts[i].text.toLowerCase().includes(data.supplier.toLowerCase())) { opts[i].selected = true; break; }
                }
            }

            // Match category
            if (data.category) {
                const opts = document.getElementById('addCategory').options;
                for (let i = 0; i < opts.length; i++) {
                    if (opts[i].text.toLowerCase().includes(data.category.toLowerCase())) { opts[i].selected = true; break; }
                }
            }

            // Sizes
            if (data.sizes && data.sizes.length > 0) {
                setProductType('variant');
                data.sizes.forEach(sz => {
                    const chip = document.querySelector(`.size-chip[data-size="${sz}"]`);
                    if (chip && !chip.classList.contains('selected')) { chip.classList.add('selected'); STATE.selectedSizes.push(sz); }
                });
            }

            showToast('✦ AI попълни формата', 'success');
            goWizardStep(1);
        } else {
            showToast('AI не успя да разпознае — попълни ръчно', 'error');
            goWizardStep(1);
        }
    }

    function playBeep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 1200;
            gain.gain.value = 0.3;
            osc.start();
            osc.stop(ctx.currentTime + 0.15);
        } catch (e) {}
    }

    function showGreenFlash() {
        const flash = document.createElement('div');
        flash.className = 'green-flash';
        document.body.appendChild(flash);
        setTimeout(() => flash.remove(), 500);
    }

    // ============================================================
    // VOICE INPUT
    // ============================================================
    function toggleVoice() {
        if (STATE.isListening) { stopVoice(); return; }
        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
            showToast('Гласовото въвеждане не е поддържано', 'error');
            return;
        }
        const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
        STATE.recognition = new SpeechRec();
        STATE.recognition.lang = 'bg-BG';
        STATE.recognition.continuous = false;
        STATE.recognition.interimResults = false;

        STATE.recognition.onresult = function(e) {
            const text = e.results[0][0].transcript;
            document.getElementById('searchInput').value = text;
            document.getElementById('searchInput').dispatchEvent(new Event('input'));
            stopVoice();
        };

        STATE.recognition.onerror = function() { stopVoice(); };
        STATE.recognition.onend = function() { stopVoice(); };

        STATE.recognition.start();
        STATE.isListening = true;
        document.getElementById('voiceBtn').classList.add('active');
        document.getElementById('voiceIndicator').classList.add('active');
    }

    function stopVoice() {
        if (STATE.recognition) { try { STATE.recognition.stop(); } catch(e) {} }
        STATE.isListening = false;
        document.getElementById('voiceBtn').classList.remove('active');
        document.getElementById('voiceIndicator').classList.remove('active');
    }

    // Voice for wizard
    function wizardVoice() {
        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
            showToast('Гласовото въвеждане не е поддържано', 'error');
            return;
        }
        const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
        const rec = new SpeechRec();
        rec.lang = 'bg-BG';
        rec.continuous = false;
        rec.interimResults = false;

        showToast('🎤 Говори — опиши артикула', 'info');

        rec.onresult = async function(e) {
            const text = e.results[0][0].transcript;
            showToast(`✦ AI обработва: "${text}"`, 'info');

            const data = await fetchJSON(`products.php?ajax=ai_voice_fill`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: text })
            });

            if (data && !data.error) {
                if (data.name) document.getElementById('addName').value = data.name;
                if (data.retail_price) document.getElementById('addRetailPrice').value = data.retail_price;
                if (data.description) document.getElementById('addDescription').value = data.description;
                if (data.supplier) {
                    const opts = document.getElementById('addSupplier').options;
                    for (let i = 0; i < opts.length; i++) {
                        if (opts[i].text.toLowerCase().includes(data.supplier.toLowerCase())) { opts[i].selected = true; break; }
                    }
                }
                if (data.category) {
                    const opts = document.getElementById('addCategory').options;
                    for (let i = 0; i < opts.length; i++) {
                        if (opts[i].text.toLowerCase().includes(data.category.toLowerCase())) { opts[i].selected = true; break; }
                    }
                }
                if (data.sizes && data.sizes.length > 0) {
                    setProductType('variant');
                    data.sizes.forEach(sz => {
                        const chip = document.querySelector(`.size-chip[data-size="${sz}"]`);
                        if (chip && !chip.classList.contains('selected')) { chip.classList.add('selected'); STATE.selectedSizes.push(sz); }
                    });
                }
                showToast('✦ AI попълни формата', 'success');
                goWizardStep(1);
            } else {
                showToast('AI не разпозна — попълни ръчно', 'error');
                goWizardStep(1);
            }
        };

        rec.onerror = function() { showToast('Грешка при запис', 'error'); };
        rec.start();
    }

    // ============================================================
    // AI IMAGE STUDIO
    // ============================================================
    async function openStudio(productId) {
        STATE.studioProductId = productId;
        closeDrawer('detail');
        setTimeout(() => openDrawer('studio'), 350);

        const credits = await fetchJSON(`products.php?ajax=ai_credits`);
        if (credits) {
            document.getElementById('studioCredits').innerHTML =
                `Бял фон: <strong>${credits.ai_credits_bg}</strong> безплатни · AI Модел: <strong>${credits.ai_credits_tryon}</strong> безплатни`;
        }
    }

    async function aiImageProcess(jobType) {
        showToast('📸 Обработвам изображение...', 'info');
        closeDrawer('studio');
        // This would call ai-image-processor.php
        try {
            const r = await fetch('ai-image-processor.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: STATE.studioProductId, job_type: jobType })
            });
            const result = await r.json();
            if (result.success) {
                showToast('✓ Изображението е обработено', 'success');
                if (result.image_url) {
                    // Refresh product detail
                    openProductDetail(STATE.studioProductId);
                }
            } else {
                showToast(result.error || 'Грешка при обработка', 'error');
            }
        } catch (e) {
            showToast('Грешка при свързване', 'error');
        }
    }

    // ============================================================
    // LOAD CURRENT SCREEN
    // ============================================================
    function loadCurrentScreen() {
        switch (STATE.screen) {
            case 'home': loadHomeScreen(); break;
            case 'suppliers': loadSuppliers(); break;
            case 'categories': loadCategories(); break;
            case 'products': loadProductsList(); break;
        }
    }

    // ============================================================
    // CLOSE SORT DROPDOWN ON OUTSIDE CLICK
    // ============================================================
    document.addEventListener('click', function(e) {
        if (!e.target.closest('[onclick*="toggleSort"]') && !e.target.closest('.sort-dropdown')) {
            document.getElementById('sortDropdown')?.classList.remove('open');
        }
    });

    // ============================================================
    // INIT
    // ============================================================
    document.addEventListener('DOMContentLoaded', loadCurrentScreen);
    </script>
</body>
</html>
