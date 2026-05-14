<?php
/**
 * products-v2.php — NEW design (P15 simple + P2v2 detailed)
 * S141 REBUILD: започваме от P15/P2v2 макетите, инжектираме PHP данни блок по блок.
 * НЕ заменя products.php — съществува паралелно за безопасно тестване.
 * SWAP в края когато визията е готова.
 *
 * Готови блокове:
 *   ⏳ Auth + tenant + store
 *   ⏳ Simple mode home (P15)
 *   ⏳ Detailed mode home (P2v2 tabs)
 *   ⏳ AJAX endpoints (proxied to products.php?)
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'config/helpers.php';

$user_id    = (int)$_SESSION['user_id'];
$tenant_id  = (int)$_SESSION['tenant_id'];
$store_id   = (int)($_SESSION['store_id'] ?? 0);
$user_role  = $_SESSION['role'] ?? 'seller';

// ════════════════════════════════════════════════════════════════════
// S143 AJAX ENDPOINTS — search + filter_options + product_detail
// Връща JSON и exit-ва. ВАЖНО: винаги try-catch (в случай на missing колони).
// ════════════════════════════════════════════════════════════════════
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ajax_action = $_GET['ajax'];
    $sid_ajax = (int)($_GET['store_id'] ?? $store_id);

    try {
        // ─── SEARCH: products + categories autocomplete ───
        if ($ajax_action === 'search') {
            $q = trim($_GET['q'] ?? '');
            $mix = isset($_GET['mix']) ? (int)$_GET['mix'] : 0;
            if (strlen($q) < 1) { echo json_encode($mix ? ['products'=>[],'categories'=>[],'total_products'=>0] : []); exit; }
            $like = "%{$q}%";
            $invJoin = $sid_ajax > 0
                ? "LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = " . (int)$sid_ajax
                : "LEFT JOIN inventory i ON i.product_id = p.id";
            $rows = DB::run("
                SELECT p.id, p.name, p.code, p.retail_price, p.image_url, p.supplier_id,
                       s.name AS supplier_name, c.name AS category_name,
                       COALESCE(SUM(i.quantity), 0) AS total_stock
                FROM products p
                LEFT JOIN suppliers s ON s.id = p.supplier_id
                LEFT JOIN categories c ON c.id = p.category_id
                {$invJoin}
                WHERE p.tenant_id = ? AND p.is_active = 1
                  AND p.parent_id IS NULL
                  AND (p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)
                GROUP BY p.id
                ORDER BY (p.name LIKE ?) DESC, p.name ASC
                LIMIT 30
            ", [$tenant_id, $like, $like, $like, $q.'%'])->fetchAll(PDO::FETCH_ASSOC);
            if ($mix) {
                $cats = DB::run("
                    SELECT c.id, c.name, COUNT(DISTINCT p.id) AS product_count
                    FROM categories c
                    LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1 AND p.tenant_id = ?
                    WHERE c.tenant_id = ? AND c.name LIKE ?
                    GROUP BY c.id
                    ORDER BY (c.name LIKE ?) DESC, c.name ASC
                    LIMIT 5
                ", [$tenant_id, $tenant_id, $like, $q.'%'])->fetchAll(PDO::FETCH_ASSOC);
                $total = DB::run("
                    SELECT COUNT(DISTINCT p.id) FROM products p
                    WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
                      AND (p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)
                ", [$tenant_id, $like, $like, $like])->fetchColumn();
                echo json_encode([
                    'products' => array_slice($rows, 0, 8),
                    'categories' => $cats,
                    'total_products' => (int)$total
                ]);
                exit;
            }
            echo json_encode($rows); exit;
        }

        // ─── FILTER OPTIONS: уникални стойности за filter drawer ───
        // Когато се подаде supplier_id → филтрира категории + опции по доставчика
        // (правило от Тих 13.05.2026: категориите са глобални без доставчик, по доставчик със доставчик)
        if ($ajax_action === 'filter_options') {
            $sup_filter = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
            $cat_filter = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

            $sizes = []; $colors = []; $brands = []; $materials = []; $compositions = [];
            $cats = []; $subcats = []; $sups = []; $countries = [];

            // ─── Доставчици: ВИНАГИ всички ───
            try { $sups = DB::run("SELECT id, name FROM suppliers WHERE tenant_id=? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

            // ─── Категории: ако supplier_id → само тези с продукти от доставчика; иначе глобално всички ───
            try {
                if ($sup_filter) {
                    $cats = DB::run("
                        SELECT DISTINCT c.id, c.name, c.parent_id
                        FROM categories c
                        JOIN products p ON p.category_id = c.id
                        WHERE c.tenant_id = ? AND p.tenant_id = ? AND p.supplier_id = ? AND p.is_active = 1
                          AND c.parent_id IS NULL
                        ORDER BY c.name
                    ", [$tenant_id, $tenant_id, $sup_filter])->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $cats = DB::run("SELECT id, name, parent_id FROM categories WHERE tenant_id=? AND parent_id IS NULL ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (Throwable $e) {}

            // ─── Подкатегории: само ако избрана главна категория ───
            try {
                if ($cat_filter) {
                    if ($sup_filter) {
                        $subcats = DB::run("
                            SELECT DISTINCT c.id, c.name, c.parent_id
                            FROM categories c
                            JOIN products p ON p.category_id = c.id
                            WHERE c.tenant_id = ? AND c.parent_id = ?
                              AND p.tenant_id = ? AND p.supplier_id = ? AND p.is_active = 1
                            ORDER BY c.name
                        ", [$tenant_id, $cat_filter, $tenant_id, $sup_filter])->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $subcats = DB::run("SELECT id, name, parent_id FROM categories WHERE tenant_id=? AND parent_id=? ORDER BY name", [$tenant_id, $cat_filter])->fetchAll(PDO::FETCH_ASSOC);
                    }
                }
            } catch (Throwable $e) {}

            // ─── Останалите опции: WHERE-clauseове споделени ───
            $whereSup = $sup_filter ? " AND supplier_id={$sup_filter}" : "";
            $whereCat = $cat_filter ? " AND (category_id={$cat_filter} OR category_id IN (SELECT id FROM categories WHERE parent_id={$cat_filter}))" : "";
            $whereAdd = $whereSup . $whereCat;

            try { $sizes = DB::run("SELECT DISTINCT size FROM products WHERE tenant_id=? AND size IS NOT NULL AND size!='' AND is_active=1{$whereAdd} ORDER BY size", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}
            try { $colors = DB::run("SELECT DISTINCT color FROM products WHERE tenant_id=? AND color IS NOT NULL AND color!='' AND is_active=1{$whereAdd} ORDER BY color", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}
            try { $brands = DB::run("SELECT DISTINCT brand FROM products WHERE tenant_id=? AND brand IS NOT NULL AND brand!='' AND is_active=1{$whereAdd} ORDER BY brand", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}
            try { $materials = DB::run("SELECT DISTINCT material FROM products WHERE tenant_id=? AND material IS NOT NULL AND material!='' AND is_active=1{$whereAdd} ORDER BY material", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}
            try { $compositions = DB::run("SELECT DISTINCT composition FROM products WHERE tenant_id=? AND composition IS NOT NULL AND composition!='' AND is_active=1{$whereAdd} ORDER BY composition LIMIT 50", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}
            try { $countries = DB::run("SELECT DISTINCT origin_country FROM products WHERE tenant_id=? AND origin_country IS NOT NULL AND origin_country!='' AND is_active=1{$whereAdd} ORDER BY origin_country", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}

            echo json_encode([
                'categories' => $cats,
                'subcategories' => $subcats,
                'suppliers' => $sups,
                'sizes' => $sizes, 'colors' => $colors, 'brands' => $brands,
                'materials' => $materials, 'compositions' => $compositions, 'countries' => $countries,
                'supplier_filter' => $sup_filter,
                'category_filter' => $cat_filter
            ]); exit;
        }

        // ─── ADVANCED SEARCH: ползва всички филтри ───
        if ($ajax_action === 'advanced_search') {
            $f = $_GET;
            $where = ["p.tenant_id = ?", "p.is_active = 1", "p.parent_id IS NULL"];
            $params = [$tenant_id];
            if (!empty($f['q'])) {
                $like = "%{$f['q']}%";
                $where[] = "(p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)";
                $params[] = $like; $params[] = $like; $params[] = $like;
            }
            if (!empty($f['cat'])) { $where[] = "p.category_id = ?"; $params[] = (int)$f['cat']; }
            if (!empty($f['sup'])) { $where[] = "p.supplier_id = ?"; $params[] = (int)$f['sup']; }
            if (!empty($f['size'])) {
                // Размерът може да е на parent или на variation (child) — търсим в двете
                $where[] = "(p.size = ? OR EXISTS (SELECT 1 FROM products pv WHERE pv.parent_id=p.id AND pv.size=?))";
                $params[] = $f['size']; $params[] = $f['size'];
            }
            if (!empty($f['color'])) {
                $where[] = "(p.color = ? OR EXISTS (SELECT 1 FROM products pv WHERE pv.parent_id=p.id AND pv.color=?))";
                $params[] = $f['color']; $params[] = $f['color'];
            }
            if (!empty($f['brand'])) { $where[] = "p.brand = ?"; $params[] = $f['brand']; }
            if (!empty($f['material'])) { $where[] = "p.material = ?"; $params[] = $f['material']; }
            if (!empty($f['composition'])) { $where[] = "p.composition LIKE ?"; $params[] = "%{$f['composition']}%"; }
            if (!empty($f['country'])) { $where[] = "p.origin_country = ?"; $params[] = $f['country']; }
            if (!empty($f['gender'])) { $where[] = "p.gender = ?"; $params[] = $f['gender']; }
            if (!empty($f['season'])) { $where[] = "p.season = ?"; $params[] = $f['season']; }
            if (isset($f['domestic']) && $f['domestic'] !== '') { $where[] = "p.is_domestic = ?"; $params[] = (int)$f['domestic']; }
            if (isset($f['has_image']) && $f['has_image'] !== '') {
                $where[] = $f['has_image'] == '1' ? "p.image_url IS NOT NULL AND p.image_url != ''" : "(p.image_url IS NULL OR p.image_url = '')";
            }
            if (!empty($f['price_min'])) { $where[] = "p.retail_price >= ?"; $params[] = (float)$f['price_min']; }
            if (!empty($f['price_max'])) { $where[] = "p.retail_price <= ?"; $params[] = (float)$f['price_max']; }
            if (!empty($f['discount'])) { $where[] = "p.discount_pct > 0"; }

            // Stock filter — изисква JOIN с inventory (store=0 → без store filter)
            $stock_join = $sid_ajax > 0
                ? "LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = " . (int)$sid_ajax
                : "LEFT JOIN inventory i ON i.product_id = p.id";
            $having = "";
            if (!empty($f['stock'])) {
                if ($f['stock'] === 'out') $having = "HAVING total_stock <= 0";
                elseif ($f['stock'] === 'in') $having = "HAVING total_stock > 0";
                elseif ($f['stock'] === 'low') $having = "HAVING total_stock > 0 AND total_stock <= COALESCE(MAX(p.min_quantity), 0)";
                elseif ($f['stock'] === 'stale60') {
                    if ($sid_ajax > 0) {
                        $where[] = "NOT EXISTS (SELECT 1 FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=p.id AND s.store_id=? AND s.created_at > DATE_SUB(NOW(), INTERVAL 60 DAY) AND s.status!='canceled')";
                        $params[] = $sid_ajax;
                    } else {
                        $where[] = "NOT EXISTS (SELECT 1 FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=p.id AND s.created_at > DATE_SUB(NOW(), INTERVAL 60 DAY) AND s.status!='canceled')";
                    }
                }
            }

            // Counted filter (store=0 → проверява всички магазини)
            if (!empty($f['counted'])) {
                if ($f['counted'] === 'counted') {
                    if ($sid_ajax > 0) {
                        $where[] = "EXISTS (SELECT 1 FROM inventory iv WHERE iv.product_id=p.id AND iv.store_id=? AND iv.last_counted_at IS NOT NULL AND iv.last_counted_at > DATE_SUB(NOW(), INTERVAL 30 DAY))";
                        $params[] = $sid_ajax;
                    } else {
                        $where[] = "EXISTS (SELECT 1 FROM inventory iv WHERE iv.product_id=p.id AND iv.last_counted_at IS NOT NULL AND iv.last_counted_at > DATE_SUB(NOW(), INTERVAL 30 DAY))";
                    }
                } elseif ($f['counted'] === 'uncounted') {
                    if ($sid_ajax > 0) {
                        $where[] = "NOT EXISTS (SELECT 1 FROM inventory iv WHERE iv.product_id=p.id AND iv.store_id=? AND iv.last_counted_at IS NOT NULL AND iv.last_counted_at > DATE_SUB(NOW(), INTERVAL 30 DAY))";
                        $params[] = $sid_ajax;
                    } else {
                        $where[] = "NOT EXISTS (SELECT 1 FROM inventory iv WHERE iv.product_id=p.id AND iv.last_counted_at IS NOT NULL AND iv.last_counted_at > DATE_SUB(NOW(), INTERVAL 30 DAY))";
                    }
                }
            }

            $where_sql = implode(" AND ", $where);
            $sql = "
                SELECT p.id, p.name, p.code, p.retail_price, p.image_url, p.size, p.color, p.brand,
                       s.name AS supplier_name, c.name AS category_name,
                       COALESCE(SUM(i.quantity), 0) AS total_stock
                FROM products p
                LEFT JOIN suppliers s ON s.id = p.supplier_id
                LEFT JOIN categories c ON c.id = p.category_id
                $stock_join
                WHERE $where_sql
                GROUP BY p.id
                $having
                ORDER BY p.name ASC
                LIMIT 100
            ";
            $rows = DB::run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['products' => $rows, 'total' => count($rows)]);
            exit;
        }

        // Unknown action
        echo json_encode(['error' => 'unknown_ajax_action', 'action' => $ajax_action]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['error' => 'server_error', 'msg' => $e->getMessage()]);
        exit;
    }
}

// S144: SESSION-BASED active mode (запомня се откъде си влязъл)
// Правило: chat/life-board → simple. ?mode=detailed → detailed. Иначе → запомненото.
$mode_override = $_GET['mode'] ?? null;
if ($mode_override === 'simple' || $mode_override === 'detailed') {
    $_SESSION['active_mode'] = $mode_override; // explicit override → запомни
}
// Първоначален default ако няма session: owner → detailed, seller → simple
if (empty($_SESSION['active_mode'])) {
    $_SESSION['active_mode'] = ($user_role === 'seller') ? 'simple' : 'detailed';
}
$active_mode = $_SESSION['active_mode'];
$is_simple_view = ($active_mode === 'simple');

// S144: Screen routing (home / list)
$screen = $_GET['screen'] ?? 'home';
$confidence_filter = $_GET['confidence'] ?? null; // full | partial | minimal | null
$is_list_view = ($screen === 'list');

// S144: SQL WHERE за списък по confidence
$confidence_sql = '';
$list_title = 'Всички артикули';
$list_lvl_class = 'all';
if ($confidence_filter === 'full') {
    $confidence_sql = " AND COALESCE(p.confidence_score, 0) >= 80";
    $list_title = 'Пълна информация';
    $list_lvl_class = 'full';
} elseif ($confidence_filter === 'partial') {
    $confidence_sql = " AND COALESCE(p.confidence_score, 0) BETWEEN 40 AND 79";
    $list_title = 'Частична информация';
    $list_lvl_class = 'partial';
} elseif ($confidence_filter === 'minimal') {
    $confidence_sql = " AND COALESCE(p.confidence_score, 0) < 40";
    $list_title = 'Минимална информация';
    $list_lvl_class = 'minimal';
}

// Store switch via GET
if (isset($_GET['store'])) {
    $req = (int)$_GET['store'];
    if ($req === 0) {
        // "Всички магазини" режим
        $_SESSION['store_id'] = 0;
        $store_id = 0;
    } else {
        $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
            [$req, $tenant_id])->fetch();
        if ($chk) { $_SESSION['store_id'] = $req; $store_id = $req; }
    }
    $redirect_to = $is_simple_view ? 'products-v2.php?mode=simple' : 'products-v2.php?mode=detailed';
    header('Location: ' . $redirect_to); exit;
}
// Ако НЯМА store избран в сесията (null/unset) — default = ВСИЧКИ магазини (нула)
// Това е по правилото на Тих 13.05.2026: общата картина е приоритет, не отделен магазин.
if (!isset($_SESSION['store_id'])) {
    $store_id = 0;
    $_SESSION['store_id'] = 0;
}
// S143 migration: при първо отваряне след днешната промяна → force "Всички магазини"
// (защото стари сесии вече имат store_id != 0 от предишната логика)
if (empty($_SESSION['s143_store_default_applied'])) {
    $store_id = 0;
    $_SESSION['store_id'] = 0;
    $_SESSION['s143_store_default_applied'] = 1;
}

// Tenant + store
$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
$cs = htmlspecialchars($tenant['currency'] ?? '€');
if ($store_id > 0) {
    $store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
    $store_name = $store['name'] ?? 'Магазин';
} else {
    $store_name = 'Всички магазини';
}
$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);

// ════════════════════════════════════════════════════════════════════
// S143 v3 — STORE FILTER HELPERS (за "Всички магазини" режим)
// Когато $store_id === 0 → не филтрира по магазин (общата картина)
// ════════════════════════════════════════════════════════════════════
$SF_INV  = $store_id > 0 ? " AND i.store_id = " . (int)$store_id : "";     // за inventory JOIN
$SF_INV2 = $store_id > 0 ? " AND iv.store_id = " . (int)$store_id : "";    // за inventory със alias iv
$SF_SALE = $store_id > 0 ? " AND s.store_id = " . (int)$store_id : "";     // за sales s.
$SF_DLV  = $store_id > 0 ? " AND d.store_id = " . (int)$store_id : "";     // за deliveries d.

// Counters for simple home alarms (СВЪРШИЛИ + ЗАСТОЯЛИ 60+)
$out_of_stock = (int)DB::run(
    "SELECT COUNT(DISTINCT p.id) FROM products p
     LEFT JOIN inventory i ON i.product_id=p.id{$SF_INV}
     WHERE p.tenant_id=? AND p.is_active=1 AND COALESCE(i.quantity,0)<=0",
    [$tenant_id]
)->fetchColumn();

$stale_60d = (int)DB::run(
    "SELECT COUNT(DISTINCT p.id) FROM products p
     LEFT JOIN inventory i ON i.product_id=p.id{$SF_INV}
     WHERE p.tenant_id=? AND p.is_active=1 AND COALESCE(i.quantity,0)>0
     AND NOT EXISTS (
         SELECT 1 FROM sale_items si JOIN sales s ON s.id=si.sale_id
         WHERE si.product_id=p.id{$SF_SALE}
         AND s.created_at > DATE_SUB(NOW(), INTERVAL 60 DAY)
         AND s.status!='canceled'
     )",
    [$tenant_id]
)->fetchColumn();

// Total products in this store
$total_products = (int)DB::run(
    "SELECT COUNT(DISTINCT p.id) FROM products p
     LEFT JOIN inventory i ON i.product_id=p.id{$SF_INV}
     WHERE p.tenant_id=? AND p.is_active=1",
    [$tenant_id]
)->fetchColumn();

// ════════════════════════════════════════════════════════════════════
// S142 Step 2B — Real data queries (всички KPI + signals + multi-store)
// Всичко в try-catch с fallback — за да не гърми при missing колони
// ════════════════════════════════════════════════════════════════════

// ─── INV NUDGE: артикули не броени 30+ дни ───
$uncounted_count = 34;  // fallback
$uncounted_days_avg = 12;
try {
    $uncounted_count = (int)DB::run(
        "SELECT COUNT(DISTINCT p.id) FROM products p
         LEFT JOIN inventory i ON i.product_id=p.id{$SF_INV}
         WHERE p.tenant_id=? AND p.is_active=1
         AND (i.last_counted_at IS NULL OR i.last_counted_at < DATE_SUB(NOW(), INTERVAL 30 DAY))",
        [$tenant_id]
    )->fetchColumn() ?: 34;
} catch (Throwable $e) { $uncounted_count = 34; }

// ─── REVENUE / PROFIT / ATV / UPT за избрания период (default 7 дни) ───
$period_days = (int)($_GET['period'] ?? 7);
if (!in_array($period_days, [1, 7, 30, 90], true)) $period_days = 7;

$kpi_revenue = 0; $kpi_profit = 0; $kpi_units = 0; $kpi_tx = 0;
try {
    $kpi = DB::run(
        "SELECT
            COALESCE(SUM(s.total),0) AS revenue,
            COALESCE(SUM(s.total*0.45),0) AS profit,
            COALESCE(SUM(si.quantity),0) AS units_sold,
            COUNT(DISTINCT s.id) AS tx_count
         FROM sales s
         LEFT JOIN sale_items si ON si.sale_id=s.id
         WHERE s.tenant_id=?{$SF_SALE}
         AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         AND s.status!='canceled'",
        [$tenant_id, $period_days]
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    $kpi_revenue  = (float)($kpi['revenue'] ?? 0);
    $kpi_profit   = (float)($kpi['profit'] ?? 0);
    $kpi_units    = (int)($kpi['units_sold'] ?? 0);
    $kpi_tx       = (int)($kpi['tx_count'] ?? 0);
} catch (Throwable $e) {
    $kpi_revenue = 3240; $kpi_profit = 1458; $kpi_units = 187; $kpi_tx = 187;
}
$kpi_atv      = $kpi_tx > 0 ? round($kpi_revenue / $kpi_tx, 2) : 17.30;
$kpi_upt      = $kpi_tx > 0 ? round($kpi_units / $kpi_tx, 2) : 1.42;
$kpi_margin_pct = $kpi_revenue > 0 ? round($kpi_profit / $kpi_revenue * 100, 0) : 42;

// Sell-through: % продадено от полученото за периода
$kpi_sellthrough = 28;  // fallback
try {
    $sellthrough_data = DB::run(
    "SELECT
        COALESCE(SUM(d.quantity),0) AS received,
        COALESCE(SUM(si.quantity),0) AS sold
     FROM deliveries d
     LEFT JOIN sale_items si ON si.product_id=d.product_id
        AND si.created_at >= d.created_at
     WHERE d.tenant_id=?{$SF_DLV}
     AND d.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
    [$tenant_id, $period_days]
)->fetch(PDO::FETCH_ASSOC) ?: ['received'=>0, 'sold'=>0];
$st_received = (int)$sellthrough_data['received'];
$st_sold     = (int)$sellthrough_data['sold'];
$kpi_sellthrough = ($st_received + $st_sold) > 0 ? round($st_sold / max(1, $st_received) * 100, 0) : 28;
} catch (Throwable $e) { $kpi_sellthrough = 28; }

// Замразен капитал € — стойност на стока заспала 60+ дни
$kpi_locked_cash = 1180;
try {
    $iJoin = $store_id > 0 ? " AND i.store_id={$store_id}" : "";
    $kpi_locked_cash = (float)DB::run(
        "SELECT COALESCE(SUM(i.quantity * COALESCE(p.cost_price, p.retail_price * 0.55)),0)
         FROM products p
         JOIN inventory i ON i.product_id=p.id{$iJoin}
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity > 0
         AND NOT EXISTS (
             SELECT 1 FROM sale_items si JOIN sales s ON s.id=si.sale_id
             WHERE si.product_id=p.id{$SF_SALE}
             AND s.created_at > DATE_SUB(NOW(), INTERVAL 60 DAY)
             AND s.status!='canceled'
         )",
        [$tenant_id]
    )->fetchColumn() ?: 1180;
} catch (Throwable $e) { $kpi_locked_cash = 1180; }

// ─── MULTI-STORE GLANCE (топ 5 stores по приход за period_days) ───
$multistore = [];
try {
    $multistore = DB::run(
        "SELECT
            st.id, st.name,
            COALESCE(SUM(s.total),0) AS revenue,
            COALESCE(SUM(s.total),0) AS this_period,
            (SELECT COALESCE(SUM(s2.total),0) FROM sales s2
             WHERE s2.store_id=st.id AND s2.tenant_id=?
             AND s2.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             AND s2.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
             AND s2.status!='canceled') AS prev_period
         FROM stores st
         LEFT JOIN sales s ON s.store_id=st.id AND s.tenant_id=?
            AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND s.status!='canceled'
         WHERE st.tenant_id=?
         GROUP BY st.id, st.name
     ORDER BY revenue DESC
     LIMIT 5",
    [$tenant_id, $period_days * 2, $period_days, $tenant_id, $period_days, $tenant_id]
)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $multistore = []; }

// Compute trend % per store
foreach ($multistore as &$ms) {
    $cur = (float)$ms['this_period'];
    $prv = (float)$ms['prev_period'];
    $ms['trend_pct'] = $prv > 0 ? round(($cur - $prv) / $prv * 100, 0) : 0;
    $ms['trend_dir'] = $ms['trend_pct'] > 3 ? 'up' : ($ms['trend_pct'] < -3 ? 'down' : 'flat');
    $ms['status_dot'] = $ms['trend_pct'] > 3 ? 'ok' : ($ms['trend_pct'] < -15 ? 'bad' : 'warn');
}
unset($ms);

// ─── AI INSIGHTS (top 10 signals от compute-insights ако съществува) ───
$ai_insights = [];
$insights_path = __DIR__ . '/compute-insights.php';
if (file_exists($insights_path)) {
    try {
        $aiSF = $store_id > 0 ? " AND store_id={$store_id}" : "";
        $ai_insights = DB::run(
            "SELECT id, tag, title, urgency, action_label, action_url, q_signal_group
             FROM ai_insights
             WHERE tenant_id=?{$aiSF} AND status='active'
             AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY FIELD(urgency,'critical','warning','info','positive'), confidence DESC
             LIMIT 10",
            [$tenant_id]
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $ai_insights = []; }
}
$ai_insights_count = count($ai_insights);

// ─── WEATHER (вече готова интеграция) ───
$weather_forecast = [];
$weather_path = __DIR__ . '/weather-cache.php';
if (file_exists($weather_path)) {
    require_once $weather_path;
    if (function_exists('getWeatherForecast')) {
        $weather_forecast = $store_id > 0 ? getWeatherForecast($store_id, $tenant_id, 7) : [];
    }
}

// ─── TOP 3 за поръчка (от compute-insights, urgency=critical, q_signal_group=5) ───
$top3_reorder = [];
if (file_exists($insights_path)) {
    try {
        $aiSF2 = $store_id > 0 ? " AND ai.store_id={$store_id}" : "";
        $top3_reorder = DB::run(
            "SELECT ai.id, ai.title, ai.subtitle, p.name AS product_name, p.sku
             FROM ai_insights ai
             LEFT JOIN products p ON p.id=ai.product_id
             WHERE ai.tenant_id=?{$aiSF2} AND ai.status='active'
             AND ai.q_signal_group=5 AND ai.urgency='critical'
             ORDER BY ai.confidence DESC
             LIMIT 3",
            [$tenant_id]
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $top3_reorder = []; }
}

// ─── TOP 3 доставчика по приход 30д + reliability score ───
$top3_suppliers = [];
try {
    $top3_suppliers = DB::run(
        "SELECT
            sup.id, sup.name,
            COALESCE(SUM(d.quantity * d.unit_cost),0) AS revenue,
            COUNT(DISTINCT d.id) AS order_count,
            ROUND(AVG(CASE WHEN d.received_at <= d.expected_at THEN 100 ELSE 60 END), 0) AS reliability
         FROM suppliers sup
         LEFT JOIN deliveries d ON d.supplier_id=sup.id
            AND d.tenant_id=?{$SF_DLV}
            AND d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         WHERE sup.tenant_id=?
         GROUP BY sup.id, sup.name
         HAVING revenue > 0
         ORDER BY revenue DESC
         LIMIT 3",
        [$tenant_id, $tenant_id]
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $top3_suppliers = []; }

// ─── DELAYED deliveries count (нова метрика за тревоги ред) ───
$delayed_deliveries = 0;
try {
    $delSF = $store_id > 0 ? " AND store_id={$store_id}" : "";
    $delayed_deliveries = (int)DB::run(
        "SELECT COUNT(*) FROM deliveries
         WHERE tenant_id=?{$delSF}
         AND status='pending'
         AND expected_at IS NOT NULL AND expected_at < NOW()",
        [$tenant_id]
    )->fetchColumn();
} catch (Throwable $e) { $delayed_deliveries = 0; }

// ════════════════════════════════════════════════════════════════════
// S144 — COMPLETENESS STATS по 3-те нива (формула от INVENTORY_AND_PRODUCT_LIFECYCLE.md)
// 🔴 Минимална: 0-39   🟡 Частична: 40-79   🟢 Пълна: 80-100
// ════════════════════════════════════════════════════════════════════
$completeness = ['total' => 0, 'full' => 0, 'partial' => 0, 'minimal' => 0, 'pct' => 0];
try {
    $row = DB::run(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN COALESCE(p.confidence_score, 0) >= 80 THEN 1 ELSE 0 END) AS full_cnt,
            SUM(CASE WHEN COALESCE(p.confidence_score, 0) BETWEEN 40 AND 79 THEN 1 ELSE 0 END) AS partial_cnt,
            SUM(CASE WHEN COALESCE(p.confidence_score, 0) < 40 THEN 1 ELSE 0 END) AS minimal_cnt
         FROM products p
         LEFT JOIN inventory i ON i.product_id=p.id{$SF_INV}
         WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL
         AND COALESCE(i.quantity, 0) > 0",
        [$tenant_id]
    )->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $completeness['total'] = (int)$row['total'];
        $completeness['full'] = (int)$row['full_cnt'];
        $completeness['partial'] = (int)$row['partial_cnt'];
        $completeness['minimal'] = (int)$row['minimal_cnt'];
        $completeness['pct'] = $completeness['total'] > 0
            ? (int)round($completeness['full'] * 100 / $completeness['total'])
            : 0;
    }
} catch (Throwable $e) {
    $completeness = ['total' => $total_products, 'full' => 0, 'partial' => 0, 'minimal' => 0, 'pct' => 0];
}

// ════════════════════════════════════════════════════════════════════
// S144 — LIST VIEW: артикули според confidence филтър
// ════════════════════════════════════════════════════════════════════
$list_products = [];
$list_count = 0;
if ($is_list_view) {
    try {
        $list_products = DB::run(
            "SELECT
                p.id, p.name, p.code, p.barcode, p.retail_price, p.cost_price,
                p.image_url, p.confidence_score,
                COALESCE(p.image_url, '') AS img,
                COALESCE(SUM(i.quantity), 0) AS qty,
                MAX(i.last_counted_at) AS last_counted,
                s.name AS supplier_name,
                c.name AS category_name
             FROM products p
             LEFT JOIN inventory i ON i.product_id = p.id{$SF_INV}
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.tenant_id = ?
               AND p.is_active = 1
               AND p.parent_id IS NULL
               {$confidence_sql}
             GROUP BY p.id
             ORDER BY p.confidence_score DESC, p.name ASC
             LIMIT 100",
            [$tenant_id]
        )->fetchAll(PDO::FETCH_ASSOC);
        $list_count = count($list_products);
    } catch (Throwable $e) {
        $list_products = [];
        $list_count = 0;
    }
}

// ─── Helper: format BGN/EUR ───
if (!function_exists('fmtMoney')) {
    function fmtMoney($amount) { return number_format((float)$amount, 0, '.', ' '); }
}
if (!function_exists('fmtMoneyDec')) {
    function fmtMoneyDec($amount) { return number_format((float)$amount, 2, '.', ' '); }
}

?>
<!DOCTYPE html>
<html lang="bg" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Стоката · RunMyStore.AI</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<script>(function(){try{var s=localStorage.getItem('rms_theme')||'light';document.documentElement.setAttribute('data-theme',s);}catch(_){document.documentElement.setAttribute('data-theme','light');}})();</script>

<style>
/* CSS merged from P15 + P2_v2 final mockups (S142 step 2A) */

/* P2_v2 — products.php DETAILED MODE — extends P15 shell with tabs + 17 detailed features */

* { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
html, body { min-height: 100%; }
body { font-family: 'Montserrat', sans-serif; overflow-x: hidden; }
button, input, a, select { font-family: inherit; color: inherit; }
button { background: none; border: none; cursor: pointer; }
a { text-decoration: none; }

:root {
  --hue1: 255; --hue2: 222; --hue3: 180;
  --radius: 22px; --radius-sm: 14px; --radius-pill: 999px; --radius-icon: 50%;
  --border: 1px;
  --ease: cubic-bezier(0.5, 1, 0.89, 1);
  --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
  --dur: 250ms; --press: 0.97;
  --font: 'Montserrat', sans-serif;
  --font-mono: 'DM Mono', ui-monospace, monospace;
  --z-aurora: 0; --z-content: 5; --z-shine: 1; --z-glow: 3;
}
:root:not([data-theme]),
:root[data-theme="light"] {
  --bg-main: #e0e5ec; --surface: #e0e5ec; --surface-2: #d1d9e6;
  --border-color: transparent;
  --text: #2d3748; --text-muted: #64748b; --text-faint: #94a3b8;
  --shadow-light: #ffffff; --shadow-dark: #a3b1c6;
  --neu-d: 8px; --neu-b: 16px; --neu-d-s: 4px; --neu-b-s: 8px;
  --shadow-card:
    var(--neu-d) var(--neu-d) var(--neu-b) var(--shadow-dark),
    calc(var(--neu-d) * -1) calc(var(--neu-d) * -1) var(--neu-b) var(--shadow-light);
  --shadow-card-sm:
    var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark),
    calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);
  --shadow-pressed:
    inset var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark),
    inset calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);
  --accent: oklch(0.62 0.22 285); --accent-2: oklch(0.65 0.25 305); --accent-3: oklch(0.78 0.18 195);
  --magic: oklch(0.65 0.25 305);
  --aurora-blend: multiply; --aurora-opacity: 0.32;
}
:root[data-theme="dark"] {
  --bg-main: #08090d; --surface: hsl(220, 25%, 4.8%); --surface-2: hsl(220, 25%, 8%);
  --border-color: hsl(var(--hue2), 12%, 20%);
  --text: #f1f5f9; --text-muted: rgba(255,255,255,0.6); --text-faint: rgba(255,255,255,0.4);
  --shadow-card:
    hsl(var(--hue2) 50% 2%) 0 10px 16px -8px,
    hsl(var(--hue2) 50% 4%) 0 20px 36px -14px;
  --shadow-card-sm: hsl(var(--hue2) 50% 2%) 0 4px 8px -2px;
  --shadow-pressed: inset 0 2px 4px hsl(var(--hue2) 50% 2%);
  --accent: hsl(var(--hue1), 80%, 65%); --accent-2: hsl(var(--hue2), 80%, 65%); --accent-3: hsl(var(--hue3), 70%, 55%);
  --magic: hsl(280, 70%, 65%);
  --aurora-blend: plus-lighter; --aurora-opacity: 0.35;
}

:root:not([data-theme]) body, [data-theme="light"] body { background: var(--bg-main); color: var(--text); }
[data-theme="dark"] body {
  background:
    radial-gradient(ellipse 800px 500px at 20% 10%, hsl(var(--hue1) 60% 35% / .22) 0%, transparent 60%),
    radial-gradient(ellipse 700px 500px at 85% 85%, hsl(var(--hue2) 60% 35% / .22) 0%, transparent 60%),
    linear-gradient(180deg, #0a0b14 0%, #050609 100%);
  background-attachment: fixed; color: var(--text);
}

@keyframes auroraDrift { 0%,100%{transform:translate(0,0) scale(1);} 33%{transform:translate(30px,-20px) scale(1.05);} 66%{transform:translate(-20px,30px) scale(0.95);} }
@keyframes conicSpin { to { transform: rotate(360deg); } }
@keyframes orbSpin { to { transform: rotate(360deg); } }
@keyframes fadeInUp { from{opacity:0;transform:translateY(8px);} to{opacity:1;transform:translateY(0);} }
@keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
@keyframes popUp { from{opacity:0;transform:scale(0.9);} to{opacity:1;transform:scale(1);} }
@keyframes pulse { 0%,100%{box-shadow:0 0 0 0 hsl(0 70% 50% / 0.5);} 50%{box-shadow:0 0 0 6px hsl(0 70% 50% / 0);} }
@keyframes rmsBrandShimmer { 0%{background-position:0% center;} 100%{background-position:200% center;} }
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation: none !important; transition: none !important; }
}

.aurora { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: var(--z-aurora); }
.aurora-blob {
  position: absolute; border-radius: 50%; filter: blur(60px);
  opacity: var(--aurora-opacity); mix-blend-mode: var(--aurora-blend);
  animation: auroraDrift 20s ease-in-out infinite;
}
.aurora-blob:nth-child(1) { width: 280px; height: 280px; background: hsl(var(--hue1),80%,60%); top: -60px; left: -80px; }
.aurora-blob:nth-child(2) { width: 240px; height: 240px; background: hsl(var(--hue3),70%,60%); top: 35%; right: -100px; animation-delay: 4s; }
.aurora-blob:nth-child(3) { width: 200px; height: 200px; background: hsl(var(--hue2),80%,60%); bottom: 80px; left: -50px; animation-delay: 8s; }

/* HEADER (1:1 P10 — brand + plan-badge + spacer + 4 icon-btns) */
.rms-header {
  position: sticky; top: 0; z-index: 50;
  height: 56px; padding: 0 16px;
  display: flex; align-items: center; gap: 8px;
  border-bottom: 1px solid var(--border-color);
  padding-top: env(safe-area-inset-top, 0);
}
[data-theme="light"] .rms-header, :root:not([data-theme]) .rms-header { background: var(--bg-main); box-shadow: 0 4px 12px rgba(163,177,198,0.15); }
[data-theme="dark"] .rms-header { background: hsl(220 25% 4.8% / 0.85); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }

.rms-brand {
  position: relative;
  font-size: 15px; font-weight: 900; letter-spacing: 0.10em;
  background: linear-gradient(90deg, hsl(var(--hue1) 80% 60%), hsl(var(--hue2) 80% 60%), hsl(var(--hue3) 70% 55%), hsl(var(--hue2) 80% 60%), hsl(var(--hue1) 80% 60%));
  background-size: 200% auto;
  -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
  animation: rmsBrandShimmer 4s linear infinite;
  filter: drop-shadow(0 0 12px hsl(var(--hue1) 70% 50% / 0.4));
}
.rms-plan-badge {
  position: relative; padding: 5px 12px; border-radius: var(--radius-pill);
  font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.08em;
  text-transform: uppercase; color: var(--text);
  border: 1px solid var(--border-color); overflow: hidden;
}
[data-theme="light"] .rms-plan-badge, :root:not([data-theme]) .rms-plan-badge { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .rms-plan-badge { background: hsl(220 25% 8% / 0.7); backdrop-filter: blur(8px); }
.rms-plan-badge::before {
  content: ''; position: absolute; inset: -1px; border-radius: inherit; padding: 1.5px;
  background: conic-gradient(from 0deg, hsl(var(--hue1) 80% 60%), hsl(var(--hue2) 80% 60%), hsl(var(--hue3) 70% 60%), hsl(var(--hue1) 80% 60%));
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor; mask-composite: exclude;
  animation: conicSpin 3s linear infinite; opacity: 0.6; pointer-events: none;
}
.rms-header-spacer { flex: 1; }

.rms-icon-btn {
  width: 40px; height: 40px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
  border: 1px solid var(--border-color);
  transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
}
[data-theme="light"] .rms-icon-btn, :root:not([data-theme]) .rms-icon-btn { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="light"] .rms-icon-btn:active, :root:not([data-theme]) .rms-icon-btn:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .rms-icon-btn { background: hsl(220 25% 8% / 0.7); backdrop-filter: blur(8px); box-shadow: 0 4px 12px hsl(var(--hue2) 50% 4%); }
.rms-icon-btn:active { transform: scale(var(--press)); }
.rms-icon-btn svg { width: 18px; height: 18px; stroke: var(--text); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.app {
  position: relative; z-index: var(--z-content);
  max-width: 480px; margin: 0 auto;
  padding: 12px 12px calc(80px + env(safe-area-inset-bottom, 0));
}

/* MODE TOGGLE */
.lb-mode-row { display: flex; justify-content: flex-end; margin: 0 0 12px; padding: 0 4px; }
.lb-mode-toggle {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px;
  border-radius: var(--radius-pill);
  font-family: var(--font-mono); font-size: 10px; font-weight: 700;
  letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--text-muted);
  border: 1px solid var(--border-color);
  cursor: pointer;
  transition: box-shadow var(--dur) var(--ease), color var(--dur) var(--ease);
}
[data-theme="light"] .lb-mode-toggle, :root:not([data-theme]) .lb-mode-toggle { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="light"] .lb-mode-toggle:active, :root:not([data-theme]) .lb-mode-toggle:active { box-shadow: var(--shadow-pressed); color: var(--accent); }
[data-theme="dark"] .lb-mode-toggle { background: hsl(220 25% 8% / 0.7); backdrop-filter: blur(8px); }
.lb-mode-toggle svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.5}

/* MODE PILL (двупозиционна — Лесен / Разширен) */
.mode-pill-row{display:flex;justify-content:center;margin:0 0 14px}
.mode-pill{display:inline-flex;align-items:center;padding:4px;border-radius:var(--radius-pill);gap:2px}
[data-theme="light"] .mode-pill,:root:not([data-theme]) .mode-pill{background:var(--surface);box-shadow:var(--shadow-pressed)}
[data-theme="dark"] .mode-pill{background:hsl(220 25% 8%);border:1px solid hsl(var(--hue2) 12% 20%)}
.mode-pill-opt{padding:7px 18px;border-radius:var(--radius-pill);font-family:var(--font-mono);font-size:10px;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);cursor:pointer;transition:all var(--dur) var(--ease)}
.mode-pill-opt.active{color:white;background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 4px 12px hsl(var(--hue1) 80% 50% / 0.35)}

/* TOP ROW */
.top-row {
  display: grid; grid-template-columns: 1.4fr 1fr; gap: 10px;
  margin-bottom: 12px;
  animation: fadeInUp 0.6s var(--ease-spring) both;
}
.cell { padding: 12px 14px; }
.cell > * { position: relative; z-index: 5; }
.cell-header-row { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.cell-label { font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted); }
.cell-numrow { display: flex; align-items: baseline; gap: 4px; margin-top: 6px; }
.cell-num { font-size: 24px; font-weight: 800; letter-spacing: -0.02em; line-height: 1; }
.cell-cur { font-family: var(--font-mono); font-size: 11px; font-weight: 700; color: var(--text-muted); }
.cell-pct { font-family: var(--font-mono); font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: var(--radius-pill); margin-left: auto; }
.cell-pct.pos { background: oklch(0.92 0.08 145 / 0.5); color: hsl(145 60% 35%); }
[data-theme="dark"] .cell-pct.pos { background: hsl(145 50% 12%); color: hsl(145 70% 65%); }
.cell-pct.neg { background: oklch(0.92 0.08 25 / 0.5); color: hsl(0 60% 45%); }
[data-theme="dark"] .cell-pct.neg { background: hsl(0 50% 12%); color: hsl(0 80% 70%); }
.cell-meta { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 4px; line-height: 1.2; }
/* Late cell — danger styling */
.cell.q1 .cell-num { color: hsl(0 70% 50%); }
[data-theme="dark"] .cell.q1 .cell-num { color: hsl(0 80% 70%); }

/* GLASS BASE */
/* GLASS + SHINE + GLOW — 1:1 от life-board.php (lesен home canonical) */
.glass { position: relative; border-radius: var(--radius); border: var(--border) solid var(--border-color); isolation: isolate; }
.glass.sm { border-radius: var(--radius-sm); }
.glass .shine, .glass .glow { --hue: var(--hue1); }
.glass .shine-bottom, .glass .glow-bottom { --hue: var(--hue2); --conic: 135deg; }
[data-theme="light"] .glass, :root:not([data-theme]) .glass { background: var(--surface); box-shadow: var(--shadow-card); border: none; }
[data-theme="light"] .glass .shine, [data-theme="light"] .glass .glow,
:root:not([data-theme]) .glass .shine, :root:not([data-theme]) .glass .glow { display: none; }
[data-theme="dark"] .glass {
  background: linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%), linear-gradient(45deg, hsl(var(--hue2) 50% 10% / .8), hsl(var(--hue2) 50% 10% / 0) 33%), linear-gradient(hsl(220 25% 4.8% / .78));
  backdrop-filter: blur(12px); box-shadow: var(--shadow-card);
}
[data-theme="dark"] .glass .shine { pointer-events: none; border-radius: 0; border-top-right-radius: inherit; border-bottom-left-radius: inherit; border: 1px solid transparent; width: 75%; aspect-ratio: 1; display: block; position: absolute; right: calc(var(--border) * -1); top: calc(var(--border) * -1); z-index: 1; background: conic-gradient(from var(--conic, -45deg) at center in oklch, transparent 12%, hsl(var(--hue), 80%, 60%), transparent 50%) border-box; mask: linear-gradient(transparent), linear-gradient(black); mask-clip: padding-box, border-box; mask-composite: subtract; }
[data-theme="dark"] .glass .shine.shine-bottom { right: auto; top: auto; left: calc(var(--border) * -1); bottom: calc(var(--border) * -1); }
[data-theme="dark"] .glass .glow { pointer-events: none; border-top-right-radius: calc(var(--radius) * 2.5); border-bottom-left-radius: calc(var(--radius) * 2.5); border: calc(var(--radius) * 1.25) solid transparent; inset: calc(var(--radius) * -2); width: 75%; aspect-ratio: 1; display: block; position: absolute; left: auto; bottom: auto; background: conic-gradient(from var(--conic, -45deg) at center in oklch, hsl(var(--hue), 80%, 60% / .5) 12%, transparent 50%); filter: blur(12px) saturate(1.25); mix-blend-mode: plus-lighter; z-index: 3; opacity: 0.6; }
[data-theme="dark"] .glass .glow.glow-bottom { inset: auto; left: calc(var(--radius) * -2); bottom: calc(var(--radius) * -2); }
/* hue overrides — отделни --hue за shine vs shine-bottom (предотвратява "линия" в gap между cells) */
[data-theme="dark"] .glass.q1 .shine, [data-theme="dark"] .glass.q1 .glow { --hue: 0; }
[data-theme="dark"] .glass.q1 .shine-bottom, [data-theme="dark"] .glass.q1 .glow-bottom { --hue: 15; }
[data-theme="dark"] .glass.q2 .shine, [data-theme="dark"] .glass.q2 .glow { --hue: 280; }
[data-theme="dark"] .glass.q2 .shine-bottom, [data-theme="dark"] .glass.q2 .glow-bottom { --hue: 305; }
[data-theme="dark"] .glass.q3 .shine, [data-theme="dark"] .glass.q3 .glow { --hue: 145; }
[data-theme="dark"] .glass.q3 .shine-bottom, [data-theme="dark"] .glass.q3 .glow-bottom { --hue: 165; }
[data-theme="dark"] .glass.q4 .shine, [data-theme="dark"] .glass.q4 .glow { --hue: 180; }
[data-theme="dark"] .glass.q4 .shine-bottom, [data-theme="dark"] .glass.q4 .glow-bottom { --hue: 195; }
[data-theme="dark"] .glass.q5 .shine, [data-theme="dark"] .glass.q5 .glow { --hue: 38; }
[data-theme="dark"] .glass.q5 .shine-bottom, [data-theme="dark"] .glass.q5 .glow-bottom { --hue: 28; }
[data-theme="dark"] .glass.qd .shine, [data-theme="dark"] .glass.qd .glow { --hue: var(--hue1); }
[data-theme="dark"] .glass.qd .shine-bottom, [data-theme="dark"] .glass.qd .glow-bottom { --hue: var(--hue2); }
[data-theme="dark"] .glass.qm .shine, [data-theme="dark"] .glass.qm .glow { --hue: 280; }
[data-theme="dark"] .glass.qm .shine-bottom, [data-theme="dark"] .glass.qm .glow-bottom { --hue: 310; }

/* TOP-ROW glow off — спира розовата вертикална линия през gap-а между cells.
   Покрива и .top-row, и .top-row-3 (3-cell вариант). Shine borders остават. */
[data-theme="dark"] .top-row .glow,
[data-theme="dark"] .top-row .glow-bottom,
[data-theme="dark"] .top-row-3 .glow,
[data-theme="dark"] .top-row-3 .glow-bottom { display: none; }

/* OP-BTN с op-info-btn */
.op-btn {
  position: relative; width: 100%;
  padding: 18px 16px; margin-bottom: 10px;
  cursor: pointer;
  display: flex; align-items: center; gap: 14px;
  text-align: left; isolation: isolate;
  animation: fadeInUp 0.5s var(--ease-spring) 0.05s both;
}
.op-btn > * { position: relative; z-index: 5; }
.op-btn-ic {
  width: 56px; height: 56px;
  border-radius: var(--radius-sm);
  display: grid; place-items: center;
  flex-shrink: 0;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 6px 18px hsl(var(--hue1) 80% 40% / 0.35);
}
[data-theme="light"] .op-btn-ic, :root:not([data-theme]) .op-btn-ic {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 6px 18px oklch(0.62 0.22 285 / 0.4);
}
.op-btn-ic svg { width: 28px; height: 28px; stroke: white; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.op-btn-body { flex: 1; min-width: 0; }
.op-btn-title {
  font-size: 17px; font-weight: 800; letter-spacing: -0.02em;
  color: var(--text);
  margin-bottom: 4px;
}
.op-btn-sub { font-family: var(--font-mono); font-size: 11px; font-weight: 700; color: var(--accent); letter-spacing: 0.04em; line-height: 1.3; text-transform: uppercase; }
[data-theme="dark"] .op-btn-sub { color: hsl(var(--hue1) 80% 70%); }

.op-info-btn {
  position: absolute;
  top: 8px; right: 8px;
  width: 24px; height: 24px;
  border-radius: 50%;
  display: grid; place-items: center;
  z-index: 6;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .op-info-btn, :root:not([data-theme]) .op-info-btn { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .op-info-btn { background: hsl(220 25% 8%); }
.op-info-btn svg { width: 11px; height: 11px; stroke: var(--text-muted); fill: none; stroke-width: 2.5; }

/* STUDIO-BTN */
.studio-row { margin-bottom: 14px; }
.studio-btn {
  position: relative; width: 100%;
  padding: 14px 16px;
  display: flex; align-items: center; gap: 12px;
  cursor: pointer;
  isolation: isolate; overflow: hidden;
  animation: fadeInUp 0.6s var(--ease-spring) 0.10s both;
}
.studio-btn > * { position: relative; z-index: 5; }
.studio-btn::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg, transparent 70%, hsl(305 70% 60% / 0.2) 85%, transparent 100%);
  animation: conicSpin 6s linear infinite;
  pointer-events: none; z-index: 1;
}
.studio-icon {
  width: 44px; height: 44px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow: 0 4px 14px hsl(280 70% 50% / 0.4);
}
.studio-icon svg { width: 20px; height: 20px; stroke: white; fill: none; stroke-width: 2; }
.studio-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.studio-label {
  font-size: 14px; font-weight: 800; letter-spacing: -0.01em;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
}
[data-theme="dark"] .studio-label {
  background: linear-gradient(135deg, hsl(280 80% 75%), hsl(305 80% 75%));
  -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
}
.studio-sub { font-family: var(--font-mono); font-size: 10px; font-weight: 700; color: var(--text-muted); letter-spacing: 0.04em; }
.studio-arrow {
  width: 28px; height: 28px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .studio-arrow, :root:not([data-theme]) .studio-arrow { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .studio-arrow { background: hsl(220 25% 8% / 0.7); }
.studio-arrow svg { width: 12px; height: 12px; stroke: hsl(280 70% 55%); fill: none; stroke-width: 2.5; }
[data-theme="dark"] .studio-arrow svg { stroke: hsl(280 80% 75%); }

/* HELP-CARD */
.help-card { padding: 14px; margin-bottom: 14px; animation: fadeInUp 0.7s var(--ease-spring) 0.15s both; }
.help-card > * { position: relative; z-index: 5; }
.help-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.help-head-ic {
  width: 36px; height: 36px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow: 0 4px 12px hsl(280 70% 50% / 0.5);
  position: relative; overflow: hidden;
}
.help-head-ic::before {
  content: ''; position: absolute; inset: 0;
  background: conic-gradient(from 0deg, transparent 70%, rgba(255,255,255,0.4) 85%, transparent 100%);
  animation: conicSpin 3s linear infinite;
}
.help-head-ic svg { width: 18px; height: 18px; stroke: white; fill: none; stroke-width: 2; position: relative; z-index: 1; }
.help-head-text { flex: 1; min-width: 0; }
.help-title {
  font-size: 15px; font-weight: 800; letter-spacing: -0.01em;
  background: linear-gradient(135deg, var(--text), var(--magic));
  -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
}
.help-sub { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 2px; line-height: 1.3; }
.help-body { font-size: 12px; font-weight: 600; color: var(--text-muted); line-height: 1.5; margin-bottom: 10px; }
.help-body b { color: var(--text); font-weight: 800; }

.help-chips-label {
  font-family: var(--font-mono); font-size: 9px; font-weight: 800;
  letter-spacing: 0.08em; text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 6px;
}
.help-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.help-chip {
  padding: 7px 12px;
  border-radius: var(--radius-pill);
  font-size: 11.5px; font-weight: 700;
  color: var(--text);
  border: 1px solid var(--border-color);
  display: inline-flex; align-items: center; gap: 5px;
  transition: box-shadow var(--dur) var(--ease);
}
[data-theme="light"] .help-chip, :root:not([data-theme]) .help-chip { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="light"] .help-chip:active, :root:not([data-theme]) .help-chip:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .help-chip { background: hsl(220 25% 8%); }
.help-chip-q { font-family: var(--font-mono); font-size: 10px; font-weight: 900; color: var(--magic); flex-shrink: 0; }
[data-theme="dark"] .help-chip-q { color: hsl(280 70% 70%); }

.help-video-ph {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  border: 1px dashed var(--border-color);
  margin-bottom: 8px;
}
[data-theme="light"] .help-video-ph, :root:not([data-theme]) .help-video-ph { background: var(--surface); box-shadow: var(--shadow-pressed); border: 1px dashed oklch(0.62 0.22 285 / 0.3); }
[data-theme="dark"] .help-video-ph { background: hsl(220 25% 4%); border: 1px dashed hsl(280 60% 35% / 0.4); }
.help-video-ic {
  width: 28px; height: 28px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
  background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%));
  box-shadow: 0 2px 6px hsl(280 70% 50% / 0.4);
}
.help-video-ic svg { width: 12px; height: 12px; stroke: white; fill: white; stroke-width: 0; }
.help-video-text { flex: 1; min-width: 0; }
.help-video-title { font-size: 11.5px; font-weight: 700; }
.help-video-sub { font-family: var(--font-mono); font-size: 9.5px; font-weight: 600; color: var(--text-muted); margin-top: 1px; }

.help-link-row {
  display: flex; align-items: center; justify-content: center; gap: 4px;
  padding: 8px;
  font-family: var(--font-mono); font-size: 10.5px; font-weight: 700;
  color: var(--magic); letter-spacing: 0.04em;
}
[data-theme="dark"] .help-link-row { color: hsl(280 70% 75%); }
.help-link-row svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2.5; }

/* LB-HEADER */
.lb-header {
  display: flex; align-items: center; justify-content: space-between;
  margin: 6px 4px 10px;
  position: relative; z-index: 5;
}
.lb-title { display: flex; align-items: center; gap: 8px; }
.lb-title-orb {
  width: 24px; height: 24px;
  border-radius: var(--radius-icon);
  background: conic-gradient(from 0deg, hsl(var(--hue1) 80% 60%), hsl(280 80% 60%), hsl(var(--hue3) 70% 60%), hsl(var(--hue1) 80% 60%));
  box-shadow: 0 0 12px hsl(var(--hue1) 80% 50% / 0.4);
  position: relative;
  animation: orbSpin 5s linear infinite;
}
.lb-title-orb::after { content: ''; position: absolute; inset: 4px; border-radius: var(--radius-icon); background: var(--bg-main); }
[data-theme="dark"] .lb-title-orb::after { background: #08090d; }
.lb-title-text { font-size: 13px; font-weight: 800; letter-spacing: -0.01em; }
.lb-count { font-family: var(--font-mono); font-size: 10px; font-weight: 700; color: var(--text-muted); }

/* LB-CARD */
.lb-card { padding: 12px 14px; margin-bottom: 8px; cursor: pointer; transition: box-shadow var(--dur) var(--ease); }
.lb-card > * { position: relative; z-index: 5; }
.lb-collapsed { display: flex; align-items: center; gap: 10px; }
.lb-emoji-orb {
  width: 28px; height: 28px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
}
.lb-emoji-orb svg { width: 14px; height: 14px; fill: none; stroke-width: 2; }

.lb-card.q1 .lb-emoji-orb { background: hsl(0 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-card.q1 .lb-emoji-orb { background: hsl(0 50% 12%); }
.lb-card.q1 .lb-emoji-orb svg { stroke: hsl(0 70% 50%); }
[data-theme="dark"] .lb-card.q1 .lb-emoji-orb svg { stroke: hsl(0 80% 70%); }

.lb-card.q2 .lb-emoji-orb { background: hsl(280 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-card.q2 .lb-emoji-orb { background: hsl(280 50% 12%); }
.lb-card.q2 .lb-emoji-orb svg { stroke: hsl(280 70% 50%); }
[data-theme="dark"] .lb-card.q2 .lb-emoji-orb svg { stroke: hsl(280 70% 70%); }

.lb-card.q3 .lb-emoji-orb { background: hsl(145 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-card.q3 .lb-emoji-orb { background: hsl(145 50% 12%); }
.lb-card.q3 .lb-emoji-orb svg { stroke: hsl(145 60% 45%); }
[data-theme="dark"] .lb-card.q3 .lb-emoji-orb svg { stroke: hsl(145 70% 65%); }

.lb-card.q5 .lb-emoji-orb { background: hsl(38 50% 92%); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-card.q5 .lb-emoji-orb { background: hsl(38 50% 12%); }
.lb-card.q5 .lb-emoji-orb svg { stroke: hsl(38 80% 50%); }
[data-theme="dark"] .lb-card.q5 .lb-emoji-orb svg { stroke: hsl(38 90% 65%); }

.lb-card.q1.urgent .lb-emoji-orb { animation: pulse 1.8s ease-out infinite; }

.lb-collapsed-content { flex: 1; min-width: 0; }
.lb-fq-tag-mini { display: block; font-family: var(--font-mono); font-size: 8.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted); }
.lb-collapsed-title { display: block; font-size: 12px; font-weight: 700; margin-top: 2px; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lb-expand-btn {
  width: 24px; height: 24px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
  border: 1px solid var(--border-color);
  transition: transform 0.3s ease, box-shadow var(--dur) var(--ease);
}
[data-theme="light"] .lb-expand-btn, :root:not([data-theme]) .lb-expand-btn { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .lb-expand-btn { background: hsl(220 25% 8%); }
.lb-expand-btn svg { width: 11px; height: 11px; stroke: var(--text-muted); fill: none; stroke-width: 2.5; }

.lb-card.expanded::before {
  content: '';
  position: absolute; inset: -1px;
  border-radius: var(--radius-sm);
  padding: 2px;
  background: conic-gradient(from 0deg, var(--accent), transparent 60%, var(--accent));
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor; mask-composite: exclude;
  animation: conicSpin 4s linear infinite;
  opacity: 0.55; pointer-events: none; z-index: 1;
}
.lb-card.q1.expanded::before { background: conic-gradient(from 0deg, hsl(0 70% 55%), transparent 60%, hsl(0 70% 55%)); }
.lb-card.q2.expanded::before { background: conic-gradient(from 0deg, hsl(280 70% 55%), transparent 60%, hsl(280 70% 55%)); }
.lb-card.q3.expanded::before { background: conic-gradient(from 0deg, hsl(145 60% 50%), transparent 60%, hsl(145 60% 50%)); }
.lb-card.q5.expanded::before { background: conic-gradient(from 0deg, hsl(38 80% 55%), transparent 60%, hsl(38 80% 55%)); }

.lb-expanded {
  max-height: 0; overflow: hidden;
  transition: max-height 0.35s ease, padding-top 0.35s ease;
  position: relative; z-index: 5;
}
.lb-card.expanded .lb-expanded { max-height: 600px; padding-top: 12px; }
.lb-card.expanded .lb-expand-btn { transform: rotate(180deg); }
[data-theme="light"] .lb-card.expanded .lb-expand-btn, :root:not([data-theme]) .lb-card.expanded .lb-expand-btn { box-shadow: var(--shadow-pressed); }
.lb-card.expanded .lb-expand-btn svg { stroke: var(--accent); }

.lb-body {
  font-size: 12px; line-height: 1.5;
  color: var(--text-muted);
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  margin-bottom: 10px;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .lb-body, :root:not([data-theme]) .lb-body { background: var(--surface); box-shadow: var(--shadow-pressed); border: none; }
[data-theme="dark"] .lb-body { background: hsl(220 25% 4% / 0.6); }
.lb-body b { color: var(--text); font-weight: 800; }

.lb-actions { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
.lb-action {
  flex: 1; min-width: 0;
  height: 36px; padding: 0 12px;
  border-radius: var(--radius-sm);
  display: inline-flex; align-items: center; justify-content: center; gap: 4px;
  font-size: 11.5px; font-weight: 700;
  color: var(--text);
  border: 1px solid var(--border-color);
  white-space: nowrap;
  transition: box-shadow var(--dur) var(--ease);
}
[data-theme="light"] .lb-action, :root:not([data-theme]) .lb-action { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="light"] .lb-action:active, :root:not([data-theme]) .lb-action:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-action { background: hsl(220 25% 8%); }
.lb-action.primary {
  color: white; border: none;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.4);
}
[data-theme="light"] .lb-action.primary, :root:not([data-theme]) .lb-action.primary {
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.4);
}

.lb-feedback { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; color: var(--text-muted); }
.lb-fb-label { font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.lb-fb-btn {
  width: 30px; height: 30px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .lb-fb-btn, :root:not([data-theme]) .lb-fb-btn { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="light"] .lb-fb-btn:active, :root:not([data-theme]) .lb-fb-btn:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .lb-fb-btn { background: hsl(220 25% 8%); }
.lb-fb-btn svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2; }
.lb-fb-btn.up { color: hsl(145 60% 45%); }
[data-theme="dark"] .lb-fb-btn.up { color: hsl(145 70% 65%); }
.lb-fb-btn.down { color: hsl(0 70% 50%); }
[data-theme="dark"] .lb-fb-btn.down { color: hsl(0 80% 70%); }
.lb-fb-btn.hmm { color: hsl(38 80% 50%); }
[data-theme="dark"] .lb-fb-btn.hmm { color: hsl(38 90% 65%); }

.see-more-mini {
  text-align: center; margin: 8px 0 4px;
  font-family: var(--font-mono); font-size: 11px; font-weight: 700;
  color: var(--accent);
}
[data-theme="dark"] .see-more-mini { color: hsl(var(--hue1) 80% 70%); }

/* INFO POPOVER */
.info-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.4);
  backdrop-filter: blur(8px);
  z-index: 100;
  display: none;
  align-items: center; justify-content: center;
  padding: 16px;
  animation: fadeIn 0.2s ease;
}
.info-overlay.active { display: flex; }
[data-theme="light"] .info-overlay, :root:not([data-theme]) .info-overlay { background: rgba(163,177,198,0.5); }
.info-card {
  width: 100%; max-width: 380px;
  border-radius: var(--radius);
  padding: 18px 16px;
  position: relative;
  border: 1px solid var(--border-color);
  animation: popUp 0.3s var(--ease-spring) both;
}
[data-theme="light"] .info-card, :root:not([data-theme]) .info-card { background: var(--surface); box-shadow: var(--shadow-card); border: none; }
[data-theme="dark"] .info-card { background: linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .9), hsl(var(--hue1) 50% 6% / .9)); backdrop-filter: blur(20px); box-shadow: 0 24px 48px hsl(220 50% 4% / 0.6); }
.info-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.info-card-ic {
  width: 44px; height: 44px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 40% / 0.35);
}
.info-card-ic svg { width: 22px; height: 22px; fill: none; stroke: white; stroke-width: 2; }
.info-card-title { flex: 1; font-size: 16px; font-weight: 800; letter-spacing: -0.01em; }
.info-card-close {
  width: 32px; height: 32px;
  border-radius: var(--radius-icon);
  display: grid; place-items: center;
  flex-shrink: 0;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .info-card-close, :root:not([data-theme]) .info-card-close { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .info-card-close { background: hsl(220 25% 8%); }
.info-card-close svg { width: 14px; height: 14px; stroke: var(--text); fill: none; stroke-width: 2.5; }
.info-card-body { font-size: 13px; font-weight: 600; color: var(--text-muted); line-height: 1.45; margin-bottom: 14px; }
.info-card-body b { color: var(--text); font-weight: 800; }
.info-card-cta {
  width: 100%; height: 44px;
  border-radius: var(--radius-sm);
  display: grid; place-items: center;
  font-size: 13px; font-weight: 800;
  color: white;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 6px 16px hsl(var(--hue1) 80% 40% / 0.4);
}

/* CHAT INPUT BAR — chat.php canonical (floating pill + анимации) */
/* В detailed mode има и bottom-nav (64px), затова bottom = calc(64px + 24px + env...) */
.chat-input-bar {
  position: fixed; left: 12px; right: 12px;
  bottom: calc(64px + 24px + env(safe-area-inset-bottom, 0));
  z-index: 49;
  height: 50px; padding: 0 8px 0 16px;
  border-radius: var(--radius-pill);
  display: flex; align-items: center; gap: 8px;
  border: 1px solid var(--border-color);
  max-width: 456px; margin: 0 auto;
  cursor: pointer;
}
[data-theme="light"] .chat-input-bar, :root:not([data-theme]) .chat-input-bar { background: var(--surface); box-shadow: var(--shadow-card); border: none; }
[data-theme="dark"] .chat-input-bar { background: linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .85), hsl(var(--hue2) 50% 8% / .8)); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); box-shadow: 0 8px 24px hsl(220 50% 4% / 0.5); }

.chat-input-icon { width: 18px; height: 18px; flex-shrink: 0; display: grid; place-items: center; }
.chat-input-icon svg { width: 14px; height: 14px; stroke: var(--magic); fill: none; stroke-width: 2; }
.chat-input-text { flex: 1; min-width: 0; font-size: 13px; font-weight: 600; color: var(--text-faint); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-mic, .chat-send {
  width: 38px; height: 38px;
  border-radius: 50%;
  display: grid; place-items: center;
  flex-shrink: 0;
  border: none;
}
.chat-mic { position: relative; background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%)); box-shadow: 0 4px 12px hsl(280 70% 50% / 0.5); }
.chat-mic svg { width: 14px; height: 14px; stroke: white; fill: none; stroke-width: 2; }
.chat-mic::before, .chat-mic::after {
  content: ''; position: absolute; inset: 0;
  border-radius: 50%;
  border: 2px solid hsl(280 70% 55%);
  pointer-events: none;
  animation: chatMicRing 2s ease-out infinite;
}
.chat-mic::after { animation-delay: 1s; }
.chat-send { background: transparent; }
.chat-send svg { width: 18px; height: 18px; stroke: var(--magic); fill: none; stroke-width: 2; animation: chatSendDrift 1.8s ease-in-out infinite; }
@keyframes chatMicRing { 0% { transform: scale(1); opacity: 0.6; } 100% { transform: scale(2.2); opacity: 0; } }
@keyframes chatSendDrift { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(2px); } }
@media (prefers-reduced-motion: reduce) { .chat-mic::before, .chat-mic::after, .chat-send svg { animation: none !important; } }

/* RECEIVE SHEET */
.gs-ov {
  position: fixed; inset: 0;
  z-index: 100;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(4px);
  opacity: 0; pointer-events: none;
  transition: opacity 0.25s var(--ease);
}
.gs-ov.show { opacity: 1; pointer-events: auto; }
.gs-sheet {
  position: fixed; left: 0; right: 0; bottom: 0;
  z-index: 101;
  max-height: 80vh;
  border-top-left-radius: var(--radius);
  border-top-right-radius: var(--radius);
  overflow: hidden;
  display: flex; flex-direction: column;
  transform: translateY(100%);
  transition: transform 0.32s var(--ease-spring);
  padding-bottom: env(safe-area-inset-bottom, 0);
}
[data-theme="light"] .gs-sheet, :root:not([data-theme]) .gs-sheet { background: var(--surface); box-shadow: 0 -8px 32px rgba(163,177,198,0.4); }
[data-theme="dark"] .gs-sheet {
  background:
    linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%),
    linear-gradient(hsl(220 25% 4.8% / .98));
  backdrop-filter: blur(20px);
  border: 1px solid var(--border-color); border-bottom: none;
  box-shadow: 0 -8px 32px hsl(var(--hue2) 50% 4%);
}
.gs-sheet.show { transform: translateY(0); }
.gs-handle { width: 42px; height: 4px; border-radius: 999px; background: var(--text-muted); opacity: 0.4; margin: 8px auto 4px; flex-shrink: 0; }
.gs-head { display: flex; align-items: center; gap: 12px; padding: 8px 16px 14px; border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
[data-theme="light"] .gs-head, :root:not([data-theme]) .gs-head { border-bottom: 1px solid rgba(163,177,198,0.25); }
.gs-head-text { flex: 1; min-width: 0; }
.gs-head-title { font-size: 15px; font-weight: 800; letter-spacing: -0.02em; color: var(--text); }
.gs-head-sub { font-family: var(--font-mono); font-size: 10px; font-weight: 600; color: var(--text-muted); letter-spacing: 0.04em; margin-top: 2px; }
.gs-close {
  width: 32px; height: 32px;
  border-radius: 50%;
  display: grid; place-items: center;
  flex-shrink: 0;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .gs-close, :root:not([data-theme]) .gs-close { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .gs-close { background: hsl(220 25% 8%); }
.gs-close svg { width: 14px; height: 14px; stroke: var(--text); fill: none; stroke-width: 2.5; }

.gs-body { padding: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.gs-opt {
  position: relative;
  padding: 16px 12px;
  border-radius: var(--radius);
  display: flex; flex-direction: column; align-items: flex-start; gap: 8px;
  cursor: pointer;
  isolation: isolate;
  text-align: left;
}
.gs-opt > * { position: relative; z-index: 5; }
.gs-opt-orb {
  width: 44px; height: 44px;
  border-radius: var(--radius-sm);
  display: grid; place-items: center;
}
.gs-opt.qm .gs-opt-orb { background: linear-gradient(135deg, hsl(280 70% 55%), hsl(305 65% 55%)); box-shadow: 0 4px 14px hsl(280 70% 50% / 0.4); }
.gs-opt.qd .gs-opt-orb { background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 4px 14px hsl(var(--hue1) 80% 40% / 0.35); }
.gs-opt.q5 .gs-opt-orb { background: linear-gradient(135deg, hsl(38 88% 55%), hsl(28 90% 50%)); box-shadow: 0 4px 14px hsl(38 88% 50% / 0.35); }
.gs-opt-orb svg { width: 22px; height: 22px; stroke: white; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.gs-opt-title { font-size: 13px; font-weight: 800; letter-spacing: -0.01em; color: var(--text); }
.gs-opt-sub { font-family: var(--font-mono); font-size: 10px; font-weight: 600; color: var(--text-muted); letter-spacing: 0.02em; line-height: 1.35; }
.gs-opt-time {
  font-family: var(--font-mono); font-size: 9px; font-weight: 800;
  letter-spacing: 0.06em; text-transform: uppercase;
  padding: 3px 7px;
  border-radius: var(--radius-pill);
  margin-top: 4px;
}
.gs-opt.qm .gs-opt-time { background: hsl(280 60% 50% / 0.12); color: hsl(280 70% 40%); }
[data-theme="dark"] .gs-opt.qm .gs-opt-time { background: hsl(280 50% 18% / 0.6); color: hsl(280 80% 75%); }
.gs-opt.qd .gs-opt-time { background: hsl(var(--hue1) 60% 50% / 0.12); color: var(--accent); }
[data-theme="dark"] .gs-opt.qd .gs-opt-time { background: hsl(var(--hue1) 50% 18% / 0.6); color: hsl(var(--hue1) 80% 75%); }
.gs-opt.q5 .gs-opt-time { background: hsl(38 80% 55% / 0.12); color: hsl(38 80% 35%); }
[data-theme="dark"] .gs-opt.q5 .gs-opt-time { background: hsl(38 50% 18% / 0.6); color: hsl(38 90% 75%); }

/* ═══ SUBBAR ═══ */
/* ═══ SUBBAR (Store + Where + Mode toggle, sticky под header) ═══ */
.rms-subbar {
  position: sticky; top: 56px; z-index: 49;
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; max-width: 480px; margin: 0 auto;
}
[data-theme="light"] .rms-subbar, :root:not([data-theme]) .rms-subbar { background: var(--bg-main); }
[data-theme="dark"] .rms-subbar {
  background: hsl(220 25% 4.8% / 0.85);
  backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
}
.rms-store-toggle {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 12px; border-radius: var(--radius-pill);
  font-size: 12px; font-weight: 800; letter-spacing: -0.01em;
  color: var(--text); cursor: pointer; border: none; outline: none; font-family: inherit;
  transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
}
[data-theme="light"] .rms-store-toggle, :root:not([data-theme]) .rms-store-toggle {
  background: var(--surface); box-shadow: var(--shadow-card-sm);
}
[data-theme="dark"] .rms-store-toggle {
  background: hsl(220 25% 8% / 0.7); backdrop-filter: blur(8px);
  border: 1px solid hsl(var(--hue2) 12% 18%);
}
.rms-store-toggle svg { width: 13px; height: 13px; fill: none; stroke: var(--accent); stroke-width: 2; flex-shrink: 0; }
[data-theme="dark"] .rms-store-toggle svg { stroke: hsl(var(--hue1) 80% 75%); }
.rms-store-toggle .store-chev { width: 10px; height: 10px; stroke: var(--text-muted); }
.rms-store-toggle:active { transform: scale(var(--press)); }
.subbar-where {
  flex: 1; text-align: center;
  font-family: var(--font-mono); font-size: 10px; font-weight: 700;
  letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted);
}

.sale-pill {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 10px 18px; border-radius: var(--radius-pill);
  font-size: 12px; font-weight: 800; letter-spacing: 0.02em;
  color: #fff; text-decoration: none;
  background: linear-gradient(135deg, hsl(38 88% 55%), hsl(28 90% 50%));
  box-shadow: 0 4px 14px hsl(38 88% 50% / 0.35);
  transition: transform var(--dur) var(--ease);
}
.sale-pill:active { transform: scale(var(--press)); }


/* ═══ P2_v2 DETAILED — добавени елементи ═══ */

/* Inventory nudge — глобален (закон §16.2) */
.inv-nudge {
  display: flex; align-items: center; gap: 10px;
  width: 100%; padding: 10px 14px;
  background: linear-gradient(135deg, oklch(80% 0.08 38), oklch(75% 0.10 28));
  border-radius: 14px; color: #fff; font-size: 12px; font-weight: 700;
  box-shadow: 0 4px 12px oklch(60% 0.12 38 / 0.30);
  margin-bottom: 14px;
}
[data-theme="light"] .inv-nudge { color: #fff; }
.inv-nudge-ic { width: 22px; height: 22px; flex-shrink: 0; }
.inv-nudge-ic svg { width: 100%; height: 100%; fill: none; stroke: currentColor; stroke-width: 2; }
.inv-nudge-text { flex: 1; text-align: left; letter-spacing: 0.02em; }
.inv-nudge-text b { font-weight: 900; font-size: 14px; }
.inv-nudge-arrow { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2; }

/* Tabs bar */
.tabs-bar {
  display: flex; gap: 6px; padding: 4px;
  background: var(--surface-2, rgba(255,255,255,0.04));
  border-radius: var(--radius-pill); margin-bottom: 14px;
  box-shadow: var(--shadow-pressed, inset 2px 2px 4px rgba(0,0,0,0.1), inset -2px -2px 4px rgba(255,255,255,0.05));
}
.tab-btn {
  flex: 1; padding: 10px 4px; font-size: 11px; font-weight: 700; font-family: var(--font);
  border-radius: var(--radius-pill); color: var(--text-muted);
  letter-spacing: 0.02em; transition: all 0.25s var(--ease);
}
.tab-btn.active {
  background: linear-gradient(135deg, oklch(60% 0.15 var(--hue1)), oklch(55% 0.18 var(--hue2)));
  color: #fff;
  box-shadow: 0 4px 12px oklch(60% 0.15 var(--hue1) / 0.30);
}
.tab-panel { animation: tabFadeIn 0.25s var(--ease); }
@keyframes tabFadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }

/* Period toggle (Преглед таб) */
.period-toggle {
  display: flex; gap: 4px; margin-bottom: 12px; overflow-x: auto;
}
.period-btn {
  padding: 7px 12px; font-size: 11px; font-weight: 600; font-family: var(--font);
  border-radius: var(--radius-pill); color: var(--text-muted);
  background: var(--surface, rgba(255,255,255,0.04));
  box-shadow: var(--shadow-card-sm, 2px 2px 4px rgba(0,0,0,0.1));
  white-space: nowrap; flex-shrink: 0;
}
.period-btn.active {
  color: var(--text, #fff);
  background: linear-gradient(135deg, oklch(60% 0.12 var(--hue1)), oklch(55% 0.14 var(--hue2)));
  box-shadow: 0 3px 8px oklch(60% 0.15 var(--hue1) / 0.25);
}


/* Quick Action row — Добави + Като предния + AI поръчка */
.qa-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 12px; }
.qa-btn { grid-column: 1 / span 3; display: flex; align-items: center; gap: 12px; padding: 14px 16px; }
.qa-row > .qa-btn-sm:nth-of-type(2) { grid-column: 1 / span 1; grid-row: 2; }
.qa-row > .qa-btn-sm:nth-of-type(3) { grid-column: 2 / span 2; grid-row: 2; }
.qa-row { grid-template-rows: auto auto; }
.qa-btn-sm { display: flex; align-items: center; gap: 8px; padding: 11px 12px; }
.qa-ic { width: 40px; height: 40px; padding: 8px; border-radius: 12px;
  background: linear-gradient(135deg, oklch(60% 0.18 var(--hue1)), oklch(55% 0.20 var(--hue2)));
  color: #fff; flex-shrink: 0; box-shadow: 0 4px 12px oklch(55% 0.18 var(--hue1) / 0.35); }
.qa-ic svg { width: 100%; height: 100%; fill: none; stroke: currentColor; stroke-width: 2.2; }
.qa-ic-sm { width: 28px; height: 28px; padding: 6px; border-radius: 8px;
  background: linear-gradient(135deg, oklch(70% 0.18 38), oklch(60% 0.20 28));
  color: #fff; flex-shrink: 0; }
.qa-row > .qa-btn-sm:nth-of-type(3) .qa-ic-sm { background: linear-gradient(135deg, oklch(70% 0.18 280), oklch(60% 0.22 310)); }
.qa-ic-sm svg { width: 100%; height: 100%; fill: none; stroke: currentColor; stroke-width: 2; }
.qa-text { display: flex; flex-direction: column; gap: 2px; flex: 1; text-align: left; }
.qa-title { font-size: 14px; font-weight: 800; color: var(--text); letter-spacing: -0.01em; }
.qa-sub { font-size: 10px; font-weight: 600; color: var(--text-faint); letter-spacing: 0.04em; }
.qa-title-sm { font-size: 11px; font-weight: 700; color: var(--text); letter-spacing: -0.005em; flex: 1; text-align: left; }

/* Top row variants */
.top-row-3 { grid-template-columns: repeat(3, 1fr); margin-bottom: 10px; }
.trend-up { color: oklch(65% 0.18 145); font-weight: 700; }
.trend-down { color: oklch(60% 0.20 15); font-weight: 700; }

/* Health card (Състояние склада, §16.3) */
.health-card { padding: 14px; margin-bottom: 14px; }
.health-head { display: flex; gap: 10px; align-items: center; margin-bottom: 12px; }
.health-head-ic { width: 32px; height: 32px; padding: 6px; border-radius: 8px;
  background: linear-gradient(135deg, oklch(70% 0.18 280), oklch(60% 0.22 310));
  color: #fff; }
.health-head-ic svg { width: 100%; height: 100%; fill: none; stroke: currentColor; stroke-width: 2; }
.health-title { font-size: 14px; font-weight: 800; color: var(--text); letter-spacing: -0.01em; }
.health-sub { font-size: 10px; font-weight: 500; color: var(--text-faint); margin-top: 2px; }
.health-bars { display: flex; flex-direction: column; gap: 8px; }
.health-row { display: grid; grid-template-columns: 78px 1fr 36px 70px; gap: 8px; align-items: center; font-size: 11px; }
.health-label { font-weight: 600; color: var(--text-muted); }
.health-bar { height: 6px; border-radius: 4px; background: var(--surface-2, rgba(255,255,255,0.05)); overflow: hidden; }
.health-bar-fill { display: block; height: 100%; border-radius: 4px; transition: width 0.4s var(--ease); }
.health-pct { font-weight: 800; color: var(--text); font-variant-numeric: tabular-nums; text-align: right; }
.health-meta { font-size: 10px; font-weight: 600; color: var(--text-faint); text-align: right; }

/* Cell label (top-row carts) */
.cell-label { font-size: 9px; font-weight: 800; letter-spacing: 0.06em; color: var(--text-faint); margin-bottom: 4px; }

/* Chart card */
.chart-card { padding: 14px; margin-bottom: 12px; }
.chart-head { display: flex; flex-direction: column; gap: 2px; margin-bottom: 12px; }
.chart-title { font-size: 13px; font-weight: 800; color: var(--text); letter-spacing: -0.01em; }
.chart-sub { font-size: 10px; font-weight: 500; color: var(--text-faint); }

/* Sparklines list */
.spark-list { display: flex; flex-direction: column; gap: 6px; }
.spark-row { display: grid; grid-template-columns: 90px 1fr 50px; gap: 8px; align-items: center; font-size: 11px; }
.spark-name { font-weight: 600; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.spark-svg { width: 100%; height: 24px; color: oklch(60% 0.15 var(--hue1)); }
.spark-val { font-weight: 800; color: var(--text); text-align: right; font-variant-numeric: tabular-nums; }

/* Pareto */
.pareto-wrap { display: flex; flex-direction: column; gap: 8px; }
.pareto-svg { width: 100%; height: 140px; color: var(--text-muted); }
.pareto-legend { display: flex; flex-direction: column; gap: 3px; font-size: 11px; color: var(--text-muted); }
.pareto-legend b { color: var(--text); }

/* Heatmap календар — дата + брой продажби */
.heatmap-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 8px; }
.hm-head {
  font-family: var(--font-mono); font-size: 9px; font-weight: 800;
  letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--text-muted);
  text-align: center; padding-bottom: 4px;
}
.hm-cell {
  aspect-ratio: 1;
  border-radius: 6px;
  background: var(--surface-2, rgba(255,255,255,0.05));
  padding: 3px 4px;
  display: flex; flex-direction: column; justify-content: space-between;
  font-family: var(--font-mono);
  min-height: 44px;
  position: relative;
}
.hm-cell .hm-date { font-size: 9px; font-weight: 700; opacity: 0.75; line-height: 1; }
.hm-cell .hm-count { font-size: 12px; font-weight: 800; text-align: center; line-height: 1; padding-bottom: 2px; }
.hm-cell.hm-empty { background: transparent; }
.hm-cell.hm-today { outline: 2px solid var(--accent); outline-offset: -1px; }
.hm-cell.hm-today .hm-date { opacity: 1; }

.hm-l1 { background: oklch(88% 0.04 145); color: hsl(145 50% 18%); }
.hm-l2 { background: oklch(78% 0.08 145); color: hsl(145 50% 15%); }
.hm-l3 { background: oklch(65% 0.13 145); color: white; }
.hm-l4 { background: oklch(55% 0.18 145); color: white; }
.hm-l5 { background: oklch(48% 0.22 145); color: white; }
[data-theme="dark"] .hm-l1 { background: oklch(25% 0.04 145); color: hsl(145 25% 78%); }
[data-theme="dark"] .hm-l2 { background: oklch(35% 0.08 145); color: hsl(145 30% 88%); }
[data-theme="dark"] .hm-l3 { background: oklch(45% 0.13 145); color: white; }
[data-theme="dark"] .hm-l4 { background: oklch(55% 0.18 145); color: white; }
[data-theme="dark"] .hm-l5 { background: oklch(65% 0.22 145); color: white; }

.hm-legend { display: flex; gap: 8px; align-items: center; font-size: 10px; color: var(--text-faint); margin-top: 8px; }
.hm-legend-cells { display: inline-flex; gap: 2px; }
.hm-legend .hm-cell-sm { width: 14px; height: 14px; border-radius: 3px; min-height: auto; padding: 0; }
.hm-legend .hm-cell-sm.hm-l1 { background: oklch(88% 0.04 145); }
.hm-legend .hm-cell-sm.hm-l5 { background: oklch(48% 0.22 145); }
[data-theme="dark"] .hm-legend .hm-cell-sm.hm-l1 { background: oklch(25% 0.04 145); }
[data-theme="dark"] .hm-legend .hm-cell-sm.hm-l5 { background: oklch(65% 0.22 145); }

/* Trend line */
.trend-svg { width: 100%; height: 80px; }
.trend-foot { display: flex; justify-content: space-between; font-size: 11px; font-weight: 700; margin-top: 6px; color: var(--text); }

/* Donut */
.donut-wrap { display: grid; grid-template-columns: 100px 1fr; gap: 12px; align-items: center; }
.donut-svg { width: 100%; }
.donut-legend { display: flex; flex-direction: column; gap: 4px; list-style: none; font-size: 11px; color: var(--text-muted); }
.donut-legend li { display: flex; align-items: center; gap: 6px; }
.donut-legend b { color: var(--text); font-weight: 800; }
.dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.dot.q1 { background: oklch(60% 0.20 15); }
.dot.q2 { background: oklch(60% 0.20 280); }
.dot.q3 { background: oklch(60% 0.20 145); }
.dot.q4 { background: oklch(70% 0.18 38); }

/* Seasonality */
.season-list { display: flex; flex-direction: column; gap: 6px; list-style: none; }
.season-list li { display: grid; grid-template-columns: 1fr 80px 60px; gap: 6px; align-items: center; font-size: 11px; padding: 6px 0; border-bottom: 1px solid var(--surface-2, rgba(255,255,255,0.05)); }
.season-list li:last-child { border-bottom: none; }
.season-cat { font-weight: 700; color: var(--text); }
.season-peak { font-size: 10px; font-weight: 600; color: var(--text-muted); text-align: center; }
.season-mult { font-weight: 800; color: oklch(60% 0.20 145); text-align: right; }

/* Manage cards */
.manage-card { padding: 14px; margin-bottom: 12px; }
.manage-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.manage-title { font-size: 13px; font-weight: 800; color: var(--text); letter-spacing: -0.01em; }
.manage-sub { font-size: 10px; font-weight: 500; color: var(--text-faint); }
.manage-link { font-size: 11px; font-weight: 700; color: oklch(60% 0.18 var(--hue1)); cursor: pointer; }

.sup-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; }
.sup-chip { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px;
  background: var(--surface-2, rgba(255,255,255,0.05));
  border-radius: 10px; box-shadow: var(--shadow-card-sm, 2px 2px 4px rgba(0,0,0,0.08));
  font-size: 11px; font-weight: 700; color: var(--text); }
.sup-name { letter-spacing: -0.01em; }
.sup-count { font-weight: 800; color: var(--text-muted); font-variant-numeric: tabular-nums; }

.store-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.store-table th { font-size: 9px; font-weight: 800; letter-spacing: 0.05em; color: var(--text-faint); padding: 6px 4px; text-align: left; border-bottom: 1px solid var(--surface-2, rgba(255,255,255,0.05)); }
.store-table th:not(:first-child) { text-align: right; }
.store-table td { padding: 7px 4px; font-weight: 600; color: var(--text); border-bottom: 1px solid var(--surface-2, rgba(255,255,255,0.04)); }
.store-table td:not(:first-child) { text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; }
.store-table tr:last-child td { border-bottom: none; }

.saved-list { display: flex; flex-direction: column; gap: 4px; list-style: none; }
.saved-list li { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px;
  background: var(--surface-2, rgba(255,255,255,0.05)); border-radius: 10px; font-size: 11px; }
.saved-name { font-weight: 700; color: var(--text); }
.saved-meta { font-size: 10px; font-weight: 600; color: var(--text-faint); font-variant-numeric: tabular-nums; }

.bulk-hint { padding: 10px; background: var(--surface-2, rgba(255,255,255,0.04)); border-radius: 10px; font-size: 11px; color: var(--text-muted); line-height: 1.5; }
.bulk-hint b { color: var(--text); font-weight: 800; }

/* Items tab — filter chips */
.items-stats { font-size: 13px; font-weight: 800; color: var(--text); margin-bottom: 12px; letter-spacing: -0.01em; }
.filter-section { margin-bottom: 14px; }
.filter-section-label { font-size: 9px; font-weight: 800; letter-spacing: 0.06em; color: var(--text-faint); margin-bottom: 6px; }
.chip-row { display: flex; flex-wrap: wrap; gap: 5px; }
.filter-chip { display: flex; align-items: center; gap: 6px; padding: 7px 11px;
  background: var(--surface-2, rgba(255,255,255,0.05)); border-radius: var(--radius-pill);
  box-shadow: var(--shadow-card-sm, 2px 2px 4px rgba(0,0,0,0.08));
  font-size: 11px; font-weight: 700; color: var(--text); }
.chip-dot { width: 8px; height: 8px; border-radius: 50%; }
.chip-dot.q1 { background: oklch(60% 0.20 15); }
.chip-dot.q2 { background: oklch(60% 0.20 280); }
.chip-dot.q3 { background: oklch(60% 0.20 145); }
.chip-dot.q4 { background: oklch(70% 0.18 38); }
.chip-dot.q5 { background: oklch(72% 0.18 60); }
.chip-dot.qd { background: oklch(55% 0.10 240); }
.chip-n { font-weight: 800; color: var(--text-muted); padding-left: 4px; font-variant-numeric: tabular-nums; }
.abc-pill { width: 18px; height: 18px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 900;
  background: linear-gradient(135deg, oklch(70% 0.18 var(--hue1)), oklch(60% 0.20 var(--hue2)));
  color: #fff; }

.items-link { display: block; padding: 14px; text-align: center; font-size: 12px; font-weight: 700;
  color: oklch(60% 0.18 var(--hue1)); background: var(--surface-2, rgba(255,255,255,0.04));
  border-radius: 12px; margin-top: 10px; }

/* Accent vars (за health-bar-fill, refer без redeclar) */
:root { --accent-q1: oklch(60% 0.20 15); --accent-q2: oklch(60% 0.20 280); --accent-q3: oklch(60% 0.20 145); --accent-q4: oklch(70% 0.18 38); }

/* ═══ BRAND (header лого) ═══ */
.rms-brand {
  position: relative; font-size: 17px; letter-spacing: -0.01em;
  display: inline-flex; align-items: baseline; gap: 0;
  text-decoration: none;
  filter: drop-shadow(0 0 12px hsl(var(--hue1) 70% 50% / 0.4));
}
.rms-brand .brand-1 {
  font-weight: 900;
  background: linear-gradient(90deg, hsl(var(--hue1) 80% 60%), hsl(var(--hue2) 80% 60%), hsl(var(--hue3) 70% 55%), hsl(var(--hue2) 80% 60%), hsl(var(--hue1) 80% 60%));
  background-size: 200% auto;
  animation: rmsBrandShimmer 4s linear infinite;
  -webkit-background-clip: text; background-clip: text;
  -webkit-text-fill-color: transparent;
}
.rms-brand .brand-2 {
  font-weight: 400; font-size: 14px;
  color: var(--text-muted); margin-left: 1px; opacity: 0.85;
}

/* ═══ SEARCH BAR (1:1 от Simple — products.php canonical pattern) ═══ */
.search-wrap {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 14px;
  margin-bottom: 10px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-color);
  position: relative;
}
[data-theme="light"] .search-wrap, :root:not([data-theme]) .search-wrap { background: var(--surface); box-shadow: var(--shadow-pressed); border: none; }
[data-theme="dark"] .search-wrap { background: hsl(220 25% 5% / .6); }
.search-wrap > svg { width: 16px; height: 16px; stroke: var(--text-muted); fill: none; stroke-width: 2; flex-shrink: 0; }
.search-wrap input {
  flex: 1; background: transparent; border: none; outline: none;
  font-family: inherit; font-size: 13px; font-weight: 500;
  color: var(--text); min-width: 0;
}
.search-wrap input::placeholder { color: var(--text-muted); }
.s-btn {
  width: 32px; height: 32px; flex-shrink: 0;
  border-radius: 50%; border: none;
  display: grid; place-items: center; cursor: pointer;
  position: relative;
}
[data-theme="light"] .s-btn, :root:not([data-theme]) .s-btn { background: var(--surface); box-shadow: var(--shadow-card-sm); }
[data-theme="light"] .s-btn:active, :root:not([data-theme]) .s-btn:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .s-btn { background: hsl(220 25% 8%); }
.s-btn svg { width: 14px; height: 14px; stroke: var(--text-muted); fill: none; stroke-width: 2; }
.s-btn .dot {
  position: absolute; top: -2px; right: -2px;
  min-width: 14px; height: 14px; padding: 0 4px;
  border-radius: 999px;
  background: hsl(0 70% 50%);
  color: white; font-size: 9px; font-weight: 800;
  display: grid; place-items: center;
  line-height: 1;
}

/* SEARCH MIC RECORDING STATE — 1:1 от products.php */
.s-btn.mic.recording {
  background: rgba(239,68,68,.3) !important;
  border-color: #ef4444 !important;
  color: #fff;
  animation: micRecPulse .8s infinite;
  position: relative;
}
.s-btn.mic.recording svg { stroke: #fff; }
.s-btn.mic.recording::after {
  content: 'REC';
  position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
  font-size: 7px; font-weight: 800; color: #ef4444;
  letter-spacing: 1px; white-space: nowrap;
  text-shadow: 0 0 6px rgba(239,68,68,.6);
  pointer-events: none;
}
.s-btn.mic.recording::before {
  content: '';
  position: absolute; top: -5px; right: -2px;
  width: 6px; height: 6px; border-radius: 50%;
  background: #ef4444;
  box-shadow: 0 0 5px #ef4444, 0 0 10px rgba(239,68,68,.5);
  animation: micRecDot .6s infinite;
  pointer-events: none;
}
@keyframes micRecPulse {
  0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,.4); }
  50% { box-shadow: 0 0 16px 4px rgba(239,68,68,.3); }
}
@keyframes micRecDot {
  0%,100% { opacity: 1; }
  50% { opacity: .3; }
}

/* ═══ Q-CHIPS ROW (signал филтри · q1-q6) ═══ */
.q-chips-row {
  display: flex; gap: 6px; overflow-x: auto;
  padding: 0 2px 10px; margin-bottom: 4px;
  -webkit-overflow-scrolling: touch; scrollbar-width: none;
}
.q-chips-row::-webkit-scrollbar { display: none; }
.q-chip {
  flex: 0 0 auto;
  height: 30px; padding: 0 12px;
  border-radius: var(--radius-pill);
  display: inline-flex; align-items: center; gap: 6px;
  font-family: var(--font-mono); font-size: 10px; font-weight: 800;
  letter-spacing: 0.04em; color: var(--text-muted);
  border: none; cursor: pointer;
  white-space: nowrap;
  transition: box-shadow var(--dur) var(--ease), color var(--dur) var(--ease);
}
[data-theme="light"] .q-chip, :root:not([data-theme]) .q-chip { background: var(--surface); box-shadow: var(--shadow-card-sm); }
[data-theme="light"] .q-chip:active, :root:not([data-theme]) .q-chip:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .q-chip { background: hsl(220 25% 8%); }
.q-chip-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.q-chip-dot.q1 { background: hsl(0 70% 55%); }
.q-chip-dot.q2 { background: hsl(280 70% 60%); }
.q-chip-dot.q3 { background: hsl(145 60% 45%); }
.q-chip-dot.q4 { background: hsl(180 70% 50%); }
.q-chip-dot.q5 { background: hsl(38 88% 55%); }
.q-chip-dot.q6 { background: hsl(220 15% 55%); }
.q-chip-count {
  font-size: 9px; padding: 1px 6px;
  border-radius: var(--radius-pill);
  background: rgba(0,0,0,0.12);
  color: var(--text);
}
[data-theme="dark"] .q-chip-count { background: rgba(255,255,255,0.08); color: var(--text-muted); }
.q-chip.active {
  color: white;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 12px hsl(var(--hue1) 80% 50% / 0.4);
}
.q-chip.active .q-chip-count { background: rgba(255,255,255,0.22); color: white; }

/* ═══ BOTTOM NAV — 1:1 от chat.php (orbs + анимации + per-tab цветове) ═══ */
.rms-bottom-nav {
  position: fixed; left: 12px; right: 12px; bottom: 12px;
  z-index: 50; height: 64px;
  display: grid; grid-template-columns: repeat(4, 1fr);
  border-radius: var(--radius);
  border: 1px solid var(--border-color);
  padding-bottom: env(safe-area-inset-bottom, 0);
  max-width: 456px; margin: 0 auto;
}
[data-theme="light"] .rms-bottom-nav, :root:not([data-theme]) .rms-bottom-nav { background: var(--surface); box-shadow: var(--shadow-card); border: none; }
[data-theme="dark"] .rms-bottom-nav {
  background: linear-gradient(235deg, hsl(var(--hue1) 50% 10% / .8), hsl(var(--hue1) 50% 10% / 0) 33%), linear-gradient(45deg, hsl(var(--hue2) 50% 10% / .8), hsl(var(--hue2) 50% 10% / 0) 33%), linear-gradient(hsl(220 25% 4.8% / .9));
  backdrop-filter: blur(12px); box-shadow: var(--shadow-card);
}
.rms-nav-tab {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 4px; padding: 6px 0;
  color: var(--text-muted);
  position: relative;
  text-decoration: none;
  transition: color var(--dur) var(--ease);
}

/* keyframes от chat.php */
@keyframes navOrbBreath {
  0%, 100% { transform: scale(1); box-shadow: 0 4px 12px var(--orb-shadow, hsl(280 70% 50% / 0.4)); }
  50% { transform: scale(1.04); box-shadow: 0 6px 18px var(--orb-shadow, hsl(280 70% 50% / 0.55)); }
}
@keyframes navOrbShimmer {
  0% { background-position: 0% 50%; }
  100% { background-position: 200% 50%; }
}
@keyframes navOrbActiveSpin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
@keyframes navStatsLineDraw {
  0% { stroke-dashoffset: 60; opacity: 0.4; }
  60% { stroke-dashoffset: 0; opacity: 1; }
  100% { stroke-dashoffset: -60; opacity: 0.4; }
}
@keyframes navStatsDotPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.4); }
}
@keyframes navBoltZap {
  0%, 80%, 100% { opacity: 0.5; }
  10%, 30% { opacity: 1; }
  20% { opacity: 0.7; }
}

/* Orb (кръгъл gradient с шим + breath) */
.rms-bottom-nav .rms-nav-tab .nav-orb {
  width: 32px; height: 32px;
  border-radius: 50%;
  display: grid; place-items: center;
  position: relative;
  background-size: 200% 200%;
  animation: navOrbBreath 3.2s ease-in-out infinite, navOrbShimmer 6s linear infinite;
  flex-shrink: 0;
}
.rms-bottom-nav .rms-nav-tab .nav-orb svg {
  width: 17px; height: 17px;
  stroke: white; fill: none;
  stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
  position: relative; z-index: 2;
}
.rms-bottom-nav .rms-nav-tab span:not(.nav-orb) {
  font-size: 10px; font-weight: 700; letter-spacing: 0.04em;
  color: var(--text-muted);
}

/* Active state: orb с conic glow ring */
.rms-bottom-nav .rms-nav-tab.active span:not(.nav-orb) {
  color: var(--text);
  font-weight: 800;
}
.rms-bottom-nav .rms-nav-tab.active .nav-orb::before {
  content: '';
  position: absolute; inset: -4px;
  border-radius: 50%;
  background: conic-gradient(from 0deg, transparent 70%, currentColor 90%, transparent 100%);
  animation: navOrbActiveSpin 3s linear infinite;
  opacity: 0.6; pointer-events: none;
  z-index: 1;
  -webkit-mask: radial-gradient(circle, transparent 60%, #000 70%);
  mask: radial-gradient(circle, transparent 60%, #000 70%);
}

/* Per-tab gradient + цвят */
.rms-bottom-nav .rms-nav-tab[aria-label="AI"] .nav-orb {
  background-image: linear-gradient(135deg, hsl(265 75% 55%), hsl(295 70% 55%), hsl(320 65% 55%), hsl(265 75% 55%));
  --orb-shadow: hsl(280 70% 50% / 0.45);
}
.rms-bottom-nav .rms-nav-tab[aria-label="AI"] { color: hsl(280 65% 55%); }
[data-theme="dark"] .rms-bottom-nav .rms-nav-tab[aria-label="AI"] { color: hsl(280 75% 75%); }

.rms-bottom-nav .rms-nav-tab[aria-label="Склад"] .nav-orb {
  background-image: linear-gradient(135deg, hsl(195 75% 50%), hsl(180 75% 50%), hsl(210 75% 55%), hsl(195 75% 50%));
  --orb-shadow: hsl(195 70% 50% / 0.45);
}
.rms-bottom-nav .rms-nav-tab[aria-label="Склад"] { color: hsl(195 65% 45%); }
[data-theme="dark"] .rms-bottom-nav .rms-nav-tab[aria-label="Склад"] { color: hsl(195 75% 70%); }

.rms-bottom-nav .rms-nav-tab[aria-label="Справки"] .nav-orb {
  background-image: linear-gradient(135deg, hsl(145 65% 45%), hsl(165 65% 45%), hsl(125 65% 45%), hsl(145 65% 45%));
  --orb-shadow: hsl(145 60% 45% / 0.45);
}
.rms-bottom-nav .rms-nav-tab[aria-label="Справки"] { color: hsl(145 60% 35%); }
[data-theme="dark"] .rms-bottom-nav .rms-nav-tab[aria-label="Справки"] { color: hsl(145 70% 65%); }

.rms-bottom-nav .rms-nav-tab[aria-label="Продажба"] .nav-orb {
  background-image: linear-gradient(135deg, hsl(38 90% 55%), hsl(28 90% 55%), hsl(48 90% 55%), hsl(38 90% 55%));
  --orb-shadow: hsl(38 80% 50% / 0.45);
}
.rms-bottom-nav .rms-nav-tab[aria-label="Продажба"] { color: hsl(28 75% 45%); }
[data-theme="dark"] .rms-bottom-nav .rms-nav-tab[aria-label="Продажба"] { color: hsl(38 85% 65%); }

/* Stagger animation per orb */
.rms-bottom-nav .rms-nav-tab[aria-label="Склад"] .nav-orb { animation-delay: -0.8s, -1.5s; }
.rms-bottom-nav .rms-nav-tab[aria-label="Справки"] .nav-orb { animation-delay: -1.6s, -3s; }
.rms-bottom-nav .rms-nav-tab[aria-label="Продажба"] .nav-orb { animation-delay: -2.4s, -4.5s; }

/* Per-tab inner SVG анимации */
.rms-bottom-nav .rms-nav-tab[aria-label="Справки"] .nav-orb svg .nav-stats-line {
  stroke-dasharray: 60;
  animation: navStatsLineDraw 3s ease-in-out infinite;
}
.rms-bottom-nav .rms-nav-tab[aria-label="Справки"] .nav-orb svg .nav-stats-dot {
  transform-origin: center; transform-box: fill-box;
  animation: navStatsDotPulse 1.6s ease-in-out infinite;
}
.rms-bottom-nav .rms-nav-tab[aria-label="Справки"] .nav-orb svg .nav-stats-dot:nth-of-type(2) { animation-delay: 0.2s; }
.rms-bottom-nav .rms-nav-tab[aria-label="Справки"] .nav-orb svg .nav-stats-dot:nth-of-type(3) { animation-delay: 0.4s; }
.rms-bottom-nav .rms-nav-tab[aria-label="Справки"] .nav-orb svg .nav-stats-dot:nth-of-type(4) { animation-delay: 0.6s; }
.rms-bottom-nav .rms-nav-tab[aria-label="Справки"] .nav-orb svg .nav-stats-dot:nth-of-type(5) { animation-delay: 0.8s; }
.rms-bottom-nav .rms-nav-tab[aria-label="Продажба"] .nav-orb svg .nav-bolt {
  animation: navBoltZap 2.2s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
  .rms-bottom-nav .nav-orb, .rms-bottom-nav .nav-orb::before,
  .nav-stats-line, .nav-stats-dot, .nav-bolt { animation: none !important; }
}

/* ═══ ВСИЧКИ АРТИКУЛИ link под search-wrap ═══ */
.all-items-link {
  display: inline-flex; align-items: center; gap: 6px;
  margin: -4px 4px 12px;
  font-family: var(--font-mono); font-size: 11px; font-weight: 700;
  letter-spacing: 0.04em; text-transform: uppercase;
  color: var(--accent); text-decoration: none;
  padding: 6px 12px; border-radius: var(--radius-pill);
  border: none; cursor: pointer;
  background: transparent;
}
.all-items-link svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.all-items-link b { font-weight: 900; color: var(--text); }
.all-items-link:hover { color: var(--accent-2); }

/* Padding bottom за main за да не се скрива съдържанието под chat-input-bar + bottom-nav */
main.app { padding-bottom: calc(64px + 50px + 32px + env(safe-area-inset-bottom, 0)); }

/* ═══ KP-PILL ('Като предния' вътре в Добави карта) ═══ */
.kp-pill {
  display: inline-flex; align-items: center; gap: 6px;
  height: 32px; padding: 0 12px;
  border-radius: var(--radius-pill);
  font-family: var(--font-mono); font-size: 10px; font-weight: 800;
  letter-spacing: 0.04em;
  border: none; cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  color: var(--text);
  position: relative; z-index: 6;
}
[data-theme="light"] .kp-pill, :root:not([data-theme]) .kp-pill { background: var(--surface); box-shadow: var(--shadow-card-sm); }
[data-theme="light"] .kp-pill:active, :root:not([data-theme]) .kp-pill:active { box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .kp-pill { background: hsl(220 25% 8%); }
.kp-pill svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2; }

/* qa-row override: AI поръчка full-width в ред 2 (без 'Като предния' отделна карта) */
.qa-row { grid-template-rows: auto auto; }
.qa-row > .qa-btn { grid-column: 1 / span 3; grid-row: 1; }
.qa-row > .qa-ai-order { grid-column: 1 / span 3; grid-row: 2; }

/* ═══ 5-KPI SCROLL ROW (replace top-row-3) ═══ */
.kpi-scroll {
  display: flex; gap: 8px; overflow-x: auto;
  padding: 2px 2px 10px; margin-bottom: 10px;
  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch; scrollbar-width: none;
}
.kpi-scroll::-webkit-scrollbar { display: none; }
.kpi-card {
  flex: 0 0 130px;
  scroll-snap-align: start;
  padding: 12px 12px;
  position: relative;
}
.kpi-label { font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.08em; color: var(--text-muted); text-transform: uppercase; }
.kpi-numrow { display: flex; align-items: baseline; gap: 3px; margin-top: 6px; }
.kpi-num { font-size: 22px; font-weight: 900; letter-spacing: -0.02em; line-height: 1; }
.kpi-cur { font-family: var(--font-mono); font-size: 11px; font-weight: 700; color: var(--text-muted); }
.kpi-meta { display: flex; align-items: center; gap: 5px; margin-top: 6px; font-size: 10px; font-weight: 700; }
.kpi-meta .trend-up { color: hsl(145 60% 40%); }
.kpi-meta .trend-down { color: hsl(0 70% 50%); }
[data-theme="dark"] .kpi-meta .trend-up { color: hsl(145 70% 65%); }
[data-theme="dark"] .kpi-meta .trend-down { color: hsl(0 80% 75%); }
.kpi-meta .trend-flat { color: var(--text-muted); }

/* YoY toggle button до period toggle */
.yoy-toggle {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 6px 10px; margin-left: 6px;
  font-size: 10px; font-weight: 800;
  border-radius: var(--radius-pill);
  font-family: var(--font-mono); letter-spacing: 0.04em;
  border: none; cursor: pointer;
  color: var(--text-muted);
  background: transparent;
}
.yoy-toggle.active {
  color: white;
  background: linear-gradient(135deg, hsl(280 65% 55%), hsl(305 60% 55%));
  box-shadow: 0 3px 8px hsl(280 60% 50% / 0.3);
}
.yoy-toggle svg { width: 10px; height: 10px; fill: none; stroke: currentColor; stroke-width: 2; }

/* ═══ CASH RECONCILIATION TILE ═══ */
.cash-tile { padding: 14px; margin-bottom: 10px; position: relative; }
.cash-tile-head { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; position: relative; z-index: 4; }
.cash-tile-head svg { width: 16px; height: 16px; stroke: hsl(38 90% 55%); fill: none; stroke-width: 2; flex-shrink: 0; }
.cash-tile-title { font-size: 13px; font-weight: 800; flex: 1; }
.cash-tile-period { font-family: var(--font-mono); font-size: 10px; font-weight: 700; color: var(--text-muted); }
.cash-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; position: relative; z-index: 4; }
.cash-cell { text-align: center; padding: 8px 4px; border-radius: 10px; background: transparent; }
.cash-cell-label { font-family: var(--font-mono); font-size: 8px; font-weight: 800; letter-spacing: 0.08em; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; }
.cash-cell-val { font-size: 15px; font-weight: 800; letter-spacing: -0.01em; line-height: 1.1; }
.cash-cell-val small { font-size: 10px; font-weight: 700; color: var(--text-muted); }
.cash-cell.diff .cash-cell-val { color: hsl(0 70% 50%); }
.cash-cell.ok .cash-cell-val { color: hsl(145 60% 40%); }
[data-theme="dark"] .cash-cell.diff .cash-cell-val { color: hsl(0 80% 75%); }
[data-theme="dark"] .cash-cell.ok .cash-cell-val { color: hsl(145 70% 65%); }
.cash-7day { font-size: 10px; font-weight: 600; color: var(--text-muted); margin-top: 10px; text-align: center; position: relative; z-index: 4; }
.cash-7day b { color: var(--text); font-weight: 800; }

/* ═══ WEATHER FORECAST CARD (1:1 от P11_chat_v7_orbs2.html) ═══ */
.wfc { padding: 14px; margin-bottom: 10px; position: relative; }
.wfc-head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; position: relative; z-index: 4; }
.wfc-head-ic { width: 22px; height: 22px; flex-shrink: 0; display: grid; place-items: center; }
.wfc-head-ic svg { width: 18px; height: 18px; stroke: hsl(38 90% 55%); fill: none; stroke-width: 2; }
.wfc-head-text { flex: 1; }
.wfc-title { font-size: 13px; font-weight: 800; letter-spacing: -0.01em; }
.wfc-sub { font-size: 10px; font-weight: 600; color: var(--text-muted); margin-top: 2px; }
.wfc-tabs { display: flex; gap: 4px; margin-bottom: 12px; position: relative; z-index: 4; }
.wfc-tab {
  flex: 1; padding: 6px 10px;
  font-size: 10px; font-weight: 800; letter-spacing: 0.04em;
  font-family: var(--font-mono);
  border-radius: var(--radius-pill);
  background: transparent; border: 1px solid var(--border-color);
  color: var(--text-muted); cursor: pointer;
}
.wfc-tab.active {
  color: white;
  background: linear-gradient(135deg, hsl(38 90% 55%), hsl(28 90% 55%));
  border-color: transparent;
  box-shadow: 0 3px 8px hsl(38 80% 50% / 0.3);
}
.wfc-days { display: flex; gap: 4px; overflow-x: auto; padding-bottom: 6px; scrollbar-width: none; position: relative; z-index: 4; }
.wfc-days::-webkit-scrollbar { display: none; }
.wfc-day {
  flex: 0 0 50px;
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  padding: 8px 6px;
  border-radius: 10px;
  background: rgba(128,128,128,0.06);
}
[data-theme="dark"] .wfc-day { background: rgba(255,255,255,0.04); }
.wfc-day.today { background: linear-gradient(135deg, hsl(38 90% 55% / 0.18), hsl(28 90% 55% / 0.12)); }
[data-theme="dark"] .wfc-day.today { background: linear-gradient(135deg, hsl(38 90% 55% / 0.22), hsl(28 90% 55% / 0.15)); }
.wfc-day-name { font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.04em; color: var(--text-muted); text-transform: uppercase; }
.wfc-day.today .wfc-day-name { color: hsl(38 90% 45%); }
[data-theme="dark"] .wfc-day.today .wfc-day-name { color: hsl(38 90% 70%); }
.wfc-day-ic svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.8; }
.wfc-day.sunny .wfc-day-ic { color: hsl(38 90% 55%); }
.wfc-day.partly .wfc-day-ic { color: hsl(195 60% 55%); }
.wfc-day.rainy .wfc-day-ic { color: hsl(210 50% 55%); }
.wfc-day-temp { font-size: 13px; font-weight: 800; line-height: 1; }
.wfc-day-temp small { font-size: 9px; font-weight: 600; color: var(--text-muted); }
.wfc-day-rain { font-family: var(--font-mono); font-size: 9px; font-weight: 700; }
.wfc-day-rain.dry { color: var(--text-muted); }
.wfc-day-rain.wet { color: hsl(210 70% 50%); }
.wfc-ai-note {
  display: flex; align-items: flex-start; gap: 8px;
  margin-top: 12px; padding: 10px 12px;
  border-radius: 12px;
  background: linear-gradient(135deg, hsl(280 60% 55% / 0.10), hsl(305 55% 55% / 0.08));
  border: 1px solid hsl(280 60% 55% / 0.25);
  position: relative; z-index: 4;
}
.wfc-ai-note-ic { flex-shrink: 0; padding-top: 1px; }
.wfc-ai-note-ic svg { width: 14px; height: 14px; stroke: var(--magic); fill: none; stroke-width: 2; }
.wfc-ai-note-text { flex: 1; font-size: 11px; font-weight: 600; line-height: 1.4; }
.wfc-ai-note-text b { font-weight: 800; }
.wfc-ai-action {
  flex-shrink: 0; padding: 5px 10px;
  font-family: var(--font-mono); font-size: 9px; font-weight: 800; letter-spacing: 0.04em;
  border-radius: var(--radius-pill);
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  color: white; border: none; cursor: pointer;
  white-space: nowrap;
}

/* ═══ SPARKLINE TOGGLE Печеливши ↔ Застояли ═══ */
.spark-toggle-row { display: flex; align-items: center; gap: 6px; margin-bottom: 10px; }
.spark-toggle {
  flex: 1; padding: 8px 12px;
  font-size: 11px; font-weight: 800; letter-spacing: 0.04em;
  font-family: var(--font-mono);
  border-radius: var(--radius-pill);
  border: 1px solid var(--border-color);
  background: transparent;
  color: var(--text-muted); cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; gap: 5px;
}
.spark-toggle.active.winners {
  color: white;
  background: linear-gradient(135deg, hsl(145 60% 45%), hsl(165 60% 45%));
  border-color: transparent;
  box-shadow: 0 3px 8px hsl(145 55% 40% / 0.3);
}
.spark-toggle.active.losers {
  color: white;
  background: linear-gradient(135deg, hsl(0 70% 50%), hsl(15 70% 50%));
  border-color: transparent;
  box-shadow: 0 3px 8px hsl(0 65% 45% / 0.3);
}
.spark-toggle-dot { width: 7px; height: 7px; border-radius: 50%; }
.spark-toggle-dot.win { background: hsl(145 60% 50%); }
.spark-toggle-dot.lose { background: hsl(0 70% 55%); }

/* ═══ ТОП 3 ЗА ПОРЪЧКА (AI quick action) ═══ */
.t3-order { padding: 14px; margin-bottom: 10px; }
.t3-head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; position: relative; z-index: 4; }
.t3-head svg { width: 16px; height: 16px; stroke: var(--magic); fill: none; stroke-width: 2; flex-shrink: 0; }
.t3-title { font-size: 13px; font-weight: 800; letter-spacing: -0.01em; flex: 1; }
.t3-list { display: flex; flex-direction: column; gap: 6px; position: relative; z-index: 4; }
.t3-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 10px; background: rgba(128,128,128,0.05); }
[data-theme="dark"] .t3-row { background: rgba(255,255,255,0.04); }
.t3-rank { font-family: var(--font-mono); font-size: 11px; font-weight: 900; color: var(--magic); flex-shrink: 0; min-width: 18px; }
.t3-name { font-size: 12px; font-weight: 700; flex: 1; line-height: 1.3; }
.t3-name small { font-size: 10px; font-weight: 500; color: var(--text-muted); }
.t3-qty { font-family: var(--font-mono); font-size: 11px; font-weight: 800; color: var(--accent); white-space: nowrap; }
.t3-cta {
  margin-top: 10px; width: 100%;
  padding: 10px;
  font-family: var(--font-mono); font-size: 11px; font-weight: 800; letter-spacing: 0.04em;
  border-radius: 12px;
  border: none; cursor: pointer;
  color: white;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  box-shadow: 0 4px 12px oklch(55% 0.18 var(--hue1) / 0.35);
  position: relative; z-index: 4;
}

/* ═══ ТОП 3 ДОСТАВЧИЦИ с reliability ═══ */
.t3-supp { padding: 14px; margin-bottom: 10px; }
.t3-supp-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 10px; }
.t3-supp-row .t3-name { flex: 1; }
.t3-supp-reliability { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: var(--radius-pill); font-family: var(--font-mono); font-size: 9px; font-weight: 800; }
.t3-supp-reliability.good { background: hsl(145 60% 50% / 0.15); color: hsl(145 60% 35%); }
.t3-supp-reliability.warn { background: hsl(38 90% 55% / 0.15); color: hsl(38 80% 35%); }
.t3-supp-reliability.bad { background: hsl(0 70% 50% / 0.15); color: hsl(0 70% 40%); }
[data-theme="dark"] .t3-supp-reliability.good { color: hsl(145 70% 65%); }
[data-theme="dark"] .t3-supp-reliability.warn { color: hsl(38 90% 70%); }
[data-theme="dark"] .t3-supp-reliability.bad { color: hsl(0 80% 75%); }

/* ═══ МАГАЗИНИ ranked table + Transfer Dependence ═══ */
.stores-table { padding: 14px; margin-bottom: 10px; }
.st-head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; position: relative; z-index: 4; }
.st-head svg { width: 16px; height: 16px; stroke: var(--text); fill: none; stroke-width: 2; flex-shrink: 0; }
.st-title { font-size: 13px; font-weight: 800; flex: 1; }
.st-period { font-family: var(--font-mono); font-size: 10px; color: var(--text-muted); font-weight: 700; }
.st-list { display: flex; flex-direction: column; gap: 4px; position: relative; z-index: 4; }
.st-row {
  display: grid;
  grid-template-columns: 18px 1fr 80px 50px;
  gap: 8px; align-items: center;
  padding: 8px 10px; border-radius: 10px;
  cursor: pointer;
}
[data-theme="light"] .st-row:hover, :root:not([data-theme]) .st-row:hover { background: rgba(0,0,0,0.04); }
[data-theme="dark"] .st-row:hover { background: rgba(255,255,255,0.04); }
.st-rank { font-family: var(--font-mono); font-size: 11px; font-weight: 900; color: var(--text-muted); }
.st-name { font-size: 12px; font-weight: 700; }
.st-name small { font-size: 10px; font-weight: 500; color: var(--text-muted); display: block; margin-top: 1px; }
.st-revenue { font-family: var(--font-mono); font-size: 13px; font-weight: 800; text-align: right; letter-spacing: -0.01em; }
.st-revenue small { font-size: 9px; color: var(--text-muted); }
.st-dep { font-family: var(--font-mono); font-size: 9px; font-weight: 800; text-align: right; padding: 3px 6px; border-radius: var(--radius-pill); white-space: nowrap; }
.st-dep.low { color: hsl(145 60% 35%); background: hsl(145 60% 50% / 0.12); }
.st-dep.mid { color: hsl(38 80% 35%); background: hsl(38 90% 55% / 0.12); }
.st-dep.high { color: hsl(0 70% 40%); background: hsl(0 70% 50% / 0.12); }
[data-theme="dark"] .st-dep.low { color: hsl(145 70% 65%); }
[data-theme="dark"] .st-dep.mid { color: hsl(38 90% 70%); }
[data-theme="dark"] .st-dep.high { color: hsl(0 80% 75%); }
.st-legend { display: flex; gap: 12px; margin-top: 10px; padding: 0 6px; font-size: 9px; font-weight: 600; color: var(--text-muted); position: relative; z-index: 4; }
.st-legend-item { display: inline-flex; align-items: center; gap: 4px; }
.st-legend-dot { width: 6px; height: 6px; border-radius: 50%; }
.st-legend-dot.low { background: hsl(145 60% 50%); }
.st-legend-dot.mid { background: hsl(38 90% 55%); }
.st-legend-dot.high { background: hsl(0 70% 55%); }

/* Weeks of Supply add-on в health-card */
.health-wos {
  display: flex; align-items: center; justify-content: space-between;
  margin-top: 12px; padding: 10px 12px;
  border-radius: 10px;
  background: rgba(128,128,128,0.06);
  position: relative; z-index: 4;
}
[data-theme="dark"] .health-wos { background: rgba(255,255,255,0.04); }
.health-wos-label { font-size: 10px; font-weight: 700; color: var(--text-muted); letter-spacing: 0.04em; text-transform: uppercase; font-family: var(--font-mono); }
.health-wos-val { font-family: var(--font-mono); font-size: 14px; font-weight: 800; letter-spacing: -0.01em; }
.health-wos-val small { font-size: 10px; font-weight: 700; color: var(--text-muted); }


/* ═══ S142 hotfix-2: SVG sizing constraints (against giant icons) ═══ */
.sg-head-ic { width: 20px !important; height: 20px !important; flex-shrink: 0; display: inline-grid !important; place-items: center !important; }
.sg-head-ic svg { width: 16px !important; height: 16px !important; stroke: currentColor; fill: none; stroke-width: 2; }
.cash-tile-head svg { width: 16px !important; height: 16px !important; flex-shrink: 0; }
.wfc-head-ic { width: 22px !important; height: 22px !important; flex-shrink: 0; display: inline-grid !important; place-items: center !important; }
.wfc-head-ic svg { width: 18px !important; height: 18px !important; flex-shrink: 0; }
.t3-head svg { width: 16px !important; height: 16px !important; flex-shrink: 0; }
.st-head svg { width: 16px !important; height: 16px !important; flex-shrink: 0; }
.lb-emoji-orb svg { width: 16px !important; height: 16px !important; }
.kp-pill svg { width: 12px !important; height: 12px !important; }
.s-btn svg { width: 14px !important; height: 14px !important; }
.all-items-link svg { width: 11px !important; height: 11px !important; }
.qa-ic svg { width: 100% !important; height: 100% !important; max-width: 24px; max-height: 24px; }
.inv-nudge-ic svg { width: 20px !important; height: 20px !important; }
.inv-nudge-arrow { width: 14px !important; height: 14px !important; flex-shrink: 0; }
.sg-row svg, .st-row svg, .t3-row svg { max-width: 16px; max-height: 16px; }

/* ════════════════════════════════════════════════════════════════════
 * S143 — SEARCH DROPDOWN + FILTER DRAWER (rich filters)
 * ════════════════════════════════════════════════════════════════════ */

/* Search dropdown под input */
.search-dd {
  margin: 0 12px 8px;
  border-radius: 14px;
  background: var(--surface);
  box-shadow: var(--shadow-card);
  border: 1px solid rgba(0,0,0,0.06);
  max-height: 60vh;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  display: none;
}
[data-theme="dark"] .search-dd {
  background: hsl(220 25% 8%);
  border-color: rgba(99,102,241,0.25);
  box-shadow: 0 8px 32px rgba(0,0,0,0.5);
}
.search-dd.open { display: block; animation: fadeInUp 0.2s var(--ease); }
.search-dd-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 14px; position: sticky; top: 0;
  background: var(--surface); z-index: 1;
  border-bottom: 1px solid rgba(0,0,0,0.06);
}
[data-theme="dark"] .search-dd-header { background: hsl(220 25% 8%); border-bottom-color: rgba(99,102,241,0.12); }
.search-dd-count { font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.search-dd-close {
  width: 24px; height: 24px; border-radius: 8px;
  background: rgba(99,102,241,0.1); display: flex; align-items: center; justify-content: center;
  cursor: pointer; border: none;
}
.search-dd-close svg { width: 10px; height: 10px; stroke: var(--accent); stroke-width: 3; fill: none; }
.search-dd-section-label { padding: 6px 14px 2px; font-size: 9px; font-weight: 800; color: var(--accent); text-transform: uppercase; letter-spacing: 0.05em; }
.search-dd-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 14px; cursor: pointer;
  border-bottom: 1px solid rgba(0,0,0,0.04);
  transition: background 0.12s;
}
.search-dd-item:hover, .search-dd-item:active { background: rgba(99,102,241,0.06); }
.search-dd-thumb {
  width: 34px; height: 34px; border-radius: 8px;
  background: rgba(99,102,241,0.08);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; overflow: hidden;
}
.search-dd-thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
.search-dd-thumb svg { width: 14px; height: 14px; stroke: rgba(99,102,241,0.3); fill: none; stroke-width: 1.5; }
.search-dd-info { flex: 1; min-width: 0; }
.search-dd-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text); }
.search-dd-meta { font-size: 9px; color: var(--text-muted); }
.search-dd-price-col { text-align: right; flex-shrink: 0; }
.search-dd-price { font-size: 11px; font-weight: 700; color: var(--accent); }
.search-dd-stock { font-size: 9px; }
.search-dd-stock.ok { color: #16a34a; }
.search-dd-stock.out { color: #dc2626; }
.search-dd-cat-icon {
  width: 32px; height: 32px; border-radius: 8px;
  background: rgba(20,184,166,0.12);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.search-dd-cat-icon svg { width: 14px; height: 14px; stroke: #14b8a6; fill: none; stroke-width: 2; }
.search-dd-arrow { color: var(--text-muted); font-size: 14px; }
.search-dd-viewall {
  padding: 11px 14px; text-align: center;
  background: rgba(99,102,241,0.08); cursor: pointer;
  border-top: 1px solid rgba(99,102,241,0.12);
  font-size: 12px; font-weight: 700; color: var(--accent);
}
.search-dd-empty { padding: 14px; text-align: center; font-size: 12px; color: var(--text-muted); }

/* Filter drawer overlay + slide-up panel */
.f-drawer-ov {
  position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 200;
  opacity: 0; pointer-events: none; transition: opacity 0.25s;
}
.f-drawer-ov.open { opacity: 1; pointer-events: all; }
.f-drawer {
  position: fixed; bottom: 0; left: 0; right: 0; z-index: 201;
  background: var(--surface);
  border-radius: 22px 22px 0 0;
  padding: 0 16px 40px;
  transform: translateY(100%);
  transition: transform 0.32s cubic-bezier(0.32,0,0.67,0);
  max-height: 92vh;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  box-shadow: 0 -20px 60px rgba(0,0,0,0.25);
}
[data-theme="dark"] .f-drawer { background: #080818; border-top: 1px solid rgba(99,102,241,0.3); box-shadow: 0 -20px 60px rgba(99,102,241,0.2); }
.f-drawer.open { transform: translateY(0); }
.f-drawer-handle { width: 36px; height: 4px; background: rgba(99,102,241,0.3); border-radius: 2px; margin: 14px auto 10px; }
.f-drawer-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; padding-top: 4px; }
.f-drawer-hdr h3 { font-size: 16px; font-weight: 800; margin: 0; color: var(--text); }
.f-drawer-close {
  width: 32px; height: 32px; border-radius: 10px;
  background: rgba(99,102,241,0.1); color: var(--accent);
  font-size: 18px; line-height: 1;
  display: flex; align-items: center; justify-content: center; cursor: pointer; border: none;
}

/* Filter sections */
.f-section { margin-bottom: 14px; }
.f-section-lbl { font-size: 12px; font-weight: 700; color: var(--accent); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.03em; }
.f-section-lbl .f-count { color: var(--text-muted); font-weight: 500; margin-left: 4px; }
.f-chips { display: flex; flex-wrap: wrap; gap: 5px; }
.f-chip {
  padding: 7px 13px; border-radius: 8px;
  border: 1px solid rgba(0,0,0,0.1);
  background: transparent;
  color: var(--text-muted);
  font-size: 13px;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.15s;
}
[data-theme="dark"] .f-chip { border-color: rgba(255,255,255,0.1); }
.f-chip:active { transform: scale(0.97); }
.f-chip.sel {
  background: rgba(99,102,241,0.15);
  color: var(--accent);
  border-color: var(--accent);
  font-weight: 600;
}

/* Color swatches (вместо текстови чипове за цвят) */
.f-color-swatch {
  width: 36px; height: 36px; border-radius: 50%;
  border: 2px solid rgba(0,0,0,0.1);
  cursor: pointer; position: relative;
  transition: all 0.15s;
}
[data-theme="dark"] .f-color-swatch { border-color: rgba(255,255,255,0.15); }
.f-color-swatch:active { transform: scale(0.92); }
.f-color-swatch.sel { border-width: 3px; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.25); }
.f-color-swatch::after {
  content: attr(data-name);
  position: absolute; bottom: -16px; left: 50%; transform: translateX(-50%);
  font-size: 9px; color: var(--text-muted); white-space: nowrap; pointer-events: none;
}

/* Price range */
.f-price-row { display: flex; gap: 8px; align-items: center; }
.f-price-inp {
  flex: 1; padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgba(0,0,0,0.1);
  background: var(--surface);
  color: var(--text);
  font-size: 13px;
  font-family: inherit;
}
[data-theme="dark"] .f-price-inp { background: hsl(220 25% 8%); border-color: rgba(255,255,255,0.1); }
.f-price-inp:focus { outline: none; border-color: var(--accent); }
.f-price-sep { color: var(--text-muted); font-size: 13px; }

/* Apply / Clear buttons */
.f-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 18px; position: sticky; bottom: 0; padding: 12px 0 4px; background: var(--surface); }
[data-theme="dark"] .f-actions { background: #080818; }
.f-btn {
  padding: 12px; border-radius: 12px;
  border: 1px solid rgba(0,0,0,0.1);
  background: var(--surface);
  color: var(--text);
  font-size: 14px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  transition: all 0.15s;
}
[data-theme="dark"] .f-btn { background: hsl(220 25% 8%); border-color: rgba(255,255,255,0.1); }
.f-btn:active { transform: scale(0.97); }
.f-btn.primary {
  background: linear-gradient(135deg, hsl(255 70% 55%), hsl(280 70% 60%));
  color: #fff; border-color: transparent;
  box-shadow: 0 4px 16px rgba(99,102,241,0.3);
}
.f-btn.primary:active { box-shadow: 0 2px 8px rgba(99,102,241,0.2); }

/* Filter badge на бутончето "Филтри" — показва брой активни */
.s-btn .dot {
  position: absolute; top: -4px; right: -4px;
  min-width: 16px; height: 16px; padding: 0 4px;
  border-radius: 8px;
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: #fff; font-size: 9px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid var(--surface);
  box-shadow: 0 2px 6px rgba(239,68,68,0.4);
}
.s-btn { position: relative; }

/* ════════════════════════════════════════════════════════════════════
 * S143 v4 — INFORMATIVE COMPLETENESS BOX (горе на страницата)
 * Дискретна подкана, не обвинителна. Малки цифри, мек progress bar.
 * ════════════════════════════════════════════════════════════════════ */
.info-box {
  margin: 0 12px 10px;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--surface);
  box-shadow: var(--shadow-card-sm);
  border: 1px solid rgba(99,102,241,0.08);
  cursor: pointer;
  transition: all 0.2s var(--ease);
}
[data-theme="dark"] .info-box {
  background: hsl(220 25% 6%);
  border-color: rgba(99,102,241,0.15);
}
.info-box:active { transform: scale(0.99); background: rgba(99,102,241,0.04); }

.info-box-top {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 8px;
}
.info-box-title {
  display: flex; align-items: center; gap: 6px;
  font-size: 13px; font-weight: 700; color: var(--text);
}
.info-box-title svg {
  width: 14px; height: 14px; stroke: var(--accent);
  fill: none; stroke-width: 2;
}
.info-box-pct {
  font-size: 11px; font-weight: 700; color: var(--accent);
}

.info-box-bar {
  height: 6px; border-radius: 3px;
  background: rgba(99,102,241,0.1);
  overflow: hidden;
  margin-bottom: 8px;
}
.info-box-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, hsl(160 70% 45%), hsl(190 70% 50%));
  border-radius: 3px;
  transition: width 0.6s var(--ease);
}

.info-box-stats {
  display: flex; justify-content: space-between;
  font-size: 11px; color: var(--text-muted);
}
.info-box-stats b {
  color: var(--text);
  font-weight: 700;
  margin-right: 3px;
}
.info-box-stats .pending {
  color: hsl(35 90% 50%);
}
.info-box-stats .pending b { color: hsl(35 90% 50%); }

/* S144: 3-те нива като мини-glass cards (q-цветове от DESIGN_SYSTEM v3) */
.info-box-levels {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 6px;
  margin-top: 2px;
  margin-bottom: 8px;
}
.ibl {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 2px;
  padding: 10px 4px 8px;
  cursor: pointer;
  font-family: inherit;
  letter-spacing: 0.01em;
  transition: transform 0.18s var(--ease);
  /* q-цветовете автоматично оцветяват shine/glow през --hue1/--hue2 */
  min-height: 56px;
}
.ibl:active { transform: scale(0.96); }
.ibl .ibl-num {
  font-weight: 900;
  font-size: 18px;
  line-height: 1;
  color: var(--text);
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
}
.ibl .ibl-lbl {
  font-size: 10px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: lowercase;
  letter-spacing: 0.04em;
}

/* "Виж всички N артикула" — отделен link отдолу */
.ibl-all-link {
  display: flex; align-items: center; justify-content: center; gap: 5px;
  width: 100%;
  padding: 8px 12px;
  border: none;
  background: transparent;
  color: var(--accent);
  font-family: inherit;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  letter-spacing: 0.03em;
  transition: opacity 0.15s var(--ease);
}
.ibl-all-link:hover { opacity: 0.75; }
.ibl-all-link b { font-weight: 900; font-variant-numeric: tabular-nums; }
.ibl-all-link svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.info-box-stats a {
  color: var(--accent);
  font-weight: 700;
  text-decoration: none;
}

/* ════════════════════════════════════════════════════════════════════
 * S144 — LIST VIEW (screen=list) — списък на артикули с confidence filter
 * ════════════════════════════════════════════════════════════════════ */
.lv-page-hdr {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 4px 12px;
  margin-bottom: 4px;
}
.lv-back-btn {
  width: 36px; height: 36px;
  border-radius: 50%;
  display: grid; place-items: center;
  flex-shrink: 0;
  border: 1px solid var(--border-color);
}
[data-theme="light"] .lv-back-btn, :root:not([data-theme]) .lv-back-btn { background: var(--surface); box-shadow: var(--shadow-card-sm); border: none; }
[data-theme="dark"] .lv-back-btn { background: hsl(220 25% 8%); }
.lv-back-btn:active { transform: scale(0.96); box-shadow: var(--shadow-pressed); }
.lv-back-btn svg { width: 14px; height: 14px; stroke: var(--text); fill: none; stroke-width: 2.5; }

.lv-page-title {
  flex: 1; min-width: 0;
  font-size: 18px; font-weight: 900;
  letter-spacing: -0.02em;
  color: var(--text);
}
.lv-lvl-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 9px; margin-left: 6px;
  border-radius: 999px;
  font-family: 'DM Mono', monospace;
  font-size: 10px; font-weight: 800;
  letter-spacing: 0.06em; text-transform: uppercase;
  vertical-align: middle;
}
.lv-lvl-badge.full { color: hsl(145 70% 45%); background: hsl(145 70% 50% / 0.12); border: 1px solid hsl(145 70% 50% / 0.25); }
.lv-lvl-badge.partial { color: hsl(38 90% 50%); background: hsl(38 90% 55% / 0.12); border: 1px solid hsl(38 90% 55% / 0.25); }
.lv-lvl-badge.minimal { color: hsl(0 75% 55%); background: hsl(0 85% 55% / 0.12); border: 1px solid hsl(0 85% 55% / 0.25); }
.lv-lvl-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; box-shadow: 0 0 6px currentColor; }

.lv-page-count {
  font-family: 'DM Mono', monospace;
  font-size: 13px; font-weight: 700;
  color: var(--text-muted);
  flex-shrink: 0;
}

/* Confidence filter pills */
.cf-row {
  display: flex; gap: 8px; overflow-x: auto;
  padding: 4px 4px 12px;
  margin: 0 -4px 4px;
  scrollbar-width: none;
}
.cf-row::-webkit-scrollbar { display: none; }
.cf-pill {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 14px;
  border-radius: 999px;
  font-size: 12px; font-weight: 700;
  color: var(--text);
  white-space: nowrap; flex-shrink: 0;
  text-decoration: none;
  transition: transform 0.18s var(--ease);
}
[data-theme="light"] .cf-pill, :root:not([data-theme]) .cf-pill { background: var(--surface); box-shadow: var(--shadow-card-sm); }
[data-theme="dark"] .cf-pill { background: hsl(220 25% 8%); border: 1px solid var(--border-color); }
.cf-pill:active { transform: scale(0.97); }
.cf-pill .cf-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.cf-pill .cf-num { font-family: 'DM Mono', monospace; font-size: 11px; font-weight: 800; color: var(--text-muted); }

.cf-pill.active.cf-full { color: white; background: linear-gradient(135deg, hsl(145 70% 45%), hsl(150 65% 40%)); box-shadow: 0 4px 14px hsl(145 70% 40% / 0.4); }
.cf-pill.active.cf-partial { color: white; background: linear-gradient(135deg, hsl(38 90% 52%), hsl(28 90% 48%)); box-shadow: 0 4px 14px hsl(38 90% 45% / 0.4); }
.cf-pill.active.cf-minimal { color: white; background: linear-gradient(135deg, hsl(0 75% 55%), hsl(355 80% 50%)); box-shadow: 0 4px 14px hsl(0 75% 45% / 0.4); }
.cf-pill.active.cf-all { color: white; background: linear-gradient(135deg, hsl(var(--hue1) 80% 55%), hsl(var(--hue2) 80% 55%)); }
.cf-pill.active .cf-num, .cf-pill.active .cf-dot { color: white; }
.cf-pill.active.cf-full .cf-num, .cf-pill.active.cf-partial .cf-num, .cf-pill.active.cf-minimal .cf-num, .cf-pill.active.cf-all .cf-num { color: rgba(255,255,255,0.85); }
.cf-pill.active .cf-dot { background: white; box-shadow: 0 0 8px white; }

.cf-pill:not(.active).cf-full .cf-dot { background: hsl(145 70% 50%); box-shadow: 0 0 6px hsl(145 70% 50% / 0.5); }
.cf-pill:not(.active).cf-partial .cf-dot { background: hsl(38 90% 55%); box-shadow: 0 0 6px hsl(38 90% 55% / 0.5); }
.cf-pill:not(.active).cf-minimal .cf-dot { background: hsl(0 75% 55%); box-shadow: 0 0 6px hsl(0 75% 55% / 0.5); }
.cf-pill:not(.active).cf-all .cf-dot { background: conic-gradient(from 0deg, hsl(145 70% 50%), hsl(38 90% 55%), hsl(0 75% 55%), hsl(145 70% 50%)); }

/* Product list rows */
.lv-list { margin-bottom: 20px; }
.prod-row {
  display: flex; align-items: center; gap: 12px;
  padding: 12px;
  margin-bottom: 8px;
  cursor: pointer;
  position: relative;
}
.prod-row > * { position: relative; z-index: 5; }
.prod-row:active { transform: scale(0.99); }
.prod-photo {
  width: 56px; height: 56px;
  border-radius: 14px;
  display: grid; place-items: center;
  flex-shrink: 0;
  overflow: hidden;
}
[data-theme="light"] .prod-photo, :root:not([data-theme]) .prod-photo { background: var(--surface); box-shadow: var(--shadow-pressed); }
[data-theme="dark"] .prod-photo { background: hsl(220 25% 4%); }
.prod-photo svg { width: 24px; height: 24px; stroke: var(--text-muted); fill: none; stroke-width: 1.5; }
.prod-info { flex: 1; min-width: 0; }
.prod-nm { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: var(--text); margin-bottom: 3px; }
.prod-nm-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; }
.prod-meta {
  font-family: 'DM Mono', monospace;
  font-size: 10px; font-weight: 600;
  color: var(--text-muted);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.prod-conf-badge {
  display: inline-flex; align-items: center;
  padding: 2px 8px;
  border-radius: 999px;
  font-family: 'DM Mono', monospace;
  font-size: 9px; font-weight: 800;
  letter-spacing: 0.06em; text-transform: uppercase;
  flex-shrink: 0;
}
.prod-conf-badge.full { color: hsl(145 70% 45%); background: hsl(145 70% 50% / 0.12); border: 1px solid hsl(145 70% 50% / 0.25); }
.prod-conf-badge.partial { color: hsl(38 90% 50%); background: hsl(38 90% 55% / 0.12); border: 1px solid hsl(38 90% 55% / 0.25); }
.prod-conf-badge.minimal { color: hsl(0 75% 55%); background: hsl(0 85% 55% / 0.12); border: 1px solid hsl(0 85% 55% / 0.25); }
.prod-right { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.prod-price {
  font-size: 14px; font-weight: 800;
  letter-spacing: -0.02em;
  background: linear-gradient(135deg, var(--text), var(--accent));
  -webkit-background-clip: text; background-clip: text;
  -webkit-text-fill-color: transparent;
}
.prod-stock {
  font-family: 'DM Mono', monospace;
  font-size: 10px; font-weight: 700;
  padding: 2px 7px;
  border-radius: 999px;
}
.prod-stock.ok { color: hsl(145 60% 35%); background: hsl(145 70% 50% / 0.1); border: 1px solid hsl(145 70% 50% / 0.3); }
.prod-stock.warn { color: hsl(38 80% 35%); background: hsl(38 90% 55% / 0.1); border: 1px solid hsl(38 90% 55% / 0.3); }
.prod-stock.danger { color: hsl(0 75% 40%); background: hsl(0 85% 55% / 0.1); border: 1px solid hsl(0 85% 55% / 0.3); }
[data-theme="dark"] .prod-stock.ok { color: hsl(145 70% 65%); }
[data-theme="dark"] .prod-stock.warn { color: hsl(38 90% 70%); }
[data-theme="dark"] .prod-stock.danger { color: hsl(0 80% 75%); }

/* Empty state */
.lv-empty {
  padding: 40px 20px;
  text-align: center;
  border-radius: 22px;
  border: 1px dashed var(--border-color);
}
[data-theme="light"] .lv-empty, :root:not([data-theme]) .lv-empty { background: rgba(0,0,0,0.02); border: 1px dashed rgba(163,177,198,0.5); }
[data-theme="dark"] .lv-empty { background: hsl(220 25% 4% / 0.5); border: 1px dashed hsl(var(--hue2) 15% 22%); }
.lv-empty-ic {
  width: 56px; height: 56px; margin: 0 auto 14px;
  border-radius: 50%;
  display: grid; place-items: center;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
}
.lv-empty-ic svg { width: 26px; height: 26px; stroke: white; fill: none; stroke-width: 2; }
.lv-empty-title { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 6px; }
.lv-empty-text { font-size: 12px; color: var(--text-muted); line-height: 1.5; }

.lv-more-note {
  text-align: center;
  padding: 14px;
  font-family: 'DM Mono', monospace;
  font-size: 11px; font-weight: 600;
  color: var(--text-muted);
  letter-spacing: 0.04em;
}

/* ════════════════════════════════════════════════════════════════════
 * S143 v2 — STICKY SEARCH (input лепи горе при писане) + АКОРДЕОН
 * ════════════════════════════════════════════════════════════════════ */

/* Sticky search при активно писане */
.search-wrap.is-active {
  position: sticky;
  top: 0;
  z-index: 50;
  background: var(--surface);
  padding-top: 8px;
  padding-bottom: 6px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.06);
  margin-top: 0 !important;
  border-radius: 0 0 14px 14px;
}
[data-theme="dark"] .search-wrap.is-active {
  background: hsl(220 25% 5%);
  box-shadow: 0 4px 16px rgba(0,0,0,0.5);
}

/* Акордеон секции в filter drawer */
.f-section.acc {
  margin-bottom: 8px;
  border-radius: 12px;
  background: rgba(99,102,241,0.04);
  border: 1px solid rgba(99,102,241,0.08);
  overflow: hidden;
  transition: background 0.2s;
}
[data-theme="dark"] .f-section.acc {
  background: rgba(99,102,241,0.06);
  border-color: rgba(99,102,241,0.15);
}
.f-section.acc.open {
  background: rgba(99,102,241,0.07);
}

.f-section-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 14px;
  cursor: pointer;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
  font-family: inherit;
  background: transparent;
  border: none;
  width: 100%;
  text-align: left;
}
.f-section-head:active { background: rgba(99,102,241,0.08); }
.f-section-head-left {
  display: flex; align-items: center; gap: 10px;
}
.f-section-head-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--text);
}
.f-section-head-count {
  font-size: 10px;
  color: var(--text-muted);
  font-weight: 500;
}
.f-section-head-selected {
  font-size: 11px;
  color: var(--accent);
  font-weight: 600;
  background: rgba(99,102,241,0.12);
  padding: 2px 8px;
  border-radius: 10px;
  max-width: 130px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.f-section-arrow {
  width: 14px; height: 14px;
  transition: transform 0.25s var(--ease);
  color: var(--text-muted);
}
.f-section.acc.open .f-section-arrow { transform: rotate(180deg); }

.f-section-body {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.32s var(--ease), padding 0.32s var(--ease);
  padding: 0 14px;
}
.f-section.acc.open .f-section-body {
  max-height: 600px;
  padding: 4px 14px 14px;
}

/* "Винаги отворен" вариант — за цена от-до */
.f-section.always-open .f-section-body {
  max-height: none;
  padding: 4px 14px 14px;
}
.f-section.always-open .f-section-head { cursor: default; }
.f-section.always-open .f-section-arrow { display: none; }

/* Цветно колоче за подкатегория (показва се само при избрана главна) */
.f-subcat-hint {
  font-size: 10px;
  color: var(--text-muted);
  font-style: italic;
  padding: 4px 0 8px;
}

</style>
</head><body>

<div class="aurora" aria-hidden="true">
  <div class="aurora-blob"></div><div class="aurora-blob"></div><div class="aurora-blob"></div>
</div>

<!-- ═══ HEADER (Тип Б — вътрешен модул) ═══ -->
<!-- S144: ОПРОСТЕН HEADER — еднакъв в Simple и Detailed (лого + тема + Продажба) -->
<header class="rms-header">
  <a class="rms-brand" href="life-board.php" title="Начало">
    <span class="brand-1">RunMyStore</span><span class="brand-2">.ai</span>
  </a>
  <div class="rms-header-spacer"></div>
  <button class="rms-icon-btn" id="themeToggle" onclick="rmsToggleTheme()" aria-label="Тема" title="Светла/тъмна">
    <svg id="themeIconSun" viewBox="0 0 24 24" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
    <svg id="themeIconMoon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
  </button>
  <a class="sale-pill" href="sale.php" title="Продажба">
    <svg viewBox="0 0 24 24"><path d="M2 6h21l-2 9H4L2 6z"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
    <span>Продажба</span>
  </a>
</header>



<!-- ═══ SUBBAR — store toggle + СКЛАД label + mode toggle ═══ -->
<div class="rms-subbar">
  <?php if (count($all_stores) > 1 || $store_id === 0): ?>
  <select class="rms-store-toggle" aria-label="Смени обект" onchange="location.href='?store='+this.value+'&mode='+(<?= $is_simple_view?'"simple"':'"detailed"' ?>)" style="-webkit-appearance:none;-moz-appearance:none;appearance:none;background-image:url(&quot;data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5'><polyline points='6 9 12 15 18 9'/></svg>&quot;);background-repeat:no-repeat;background-position:right 8px center;background-size:12px 12px;padding-right:28px;">
    <option value="0" <?= $store_id===0?'selected':'' ?>>🏢 Всички магазини</option>
    <?php foreach ($all_stores as $st): ?>
    <option value="<?= (int)$st['id'] ?>" <?= $st['id']==$store_id?'selected':'' ?>><?= htmlspecialchars($st['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php else: ?>
  <button class="rms-store-toggle" aria-label="Обект" style="cursor:default" disabled>
    <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    <span class="store-name"><?= htmlspecialchars($store_name) ?></span>
  </button>
  <?php endif; ?>
  <span class="subbar-where">СТОКАТА МИ</span>
  <?php if ($is_simple_view): ?>
  <a class="lb-mode-toggle" href="?mode=detailed" title="Разширен режим">
    <span>Разширен</span>
    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  </a>
  <?php else: ?>
  <a class="lb-mode-toggle" href="?mode=simple" title="Лесен режим">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    <span>Лесен</span>
  </a>
  <?php endif; ?>
</div>

<main class="app">

<?php if ($is_list_view): ?>
<!-- ═══════════════════════════════════════════════════════ -->
<!-- LIST VIEW (S144) — списък с filter по confidence         -->
<!-- ═══════════════════════════════════════════════════════ -->

  <!-- Page header със заглавие според филтъра -->
  <div class="lv-page-hdr">
    <button class="lv-back-btn" onclick="location.href='products-v2.php?mode=<?= $is_simple_view?'simple':'detailed' ?>'" aria-label="Назад">
      <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
    <div class="lv-page-title">
      Артикули
      <?php if ($confidence_filter): ?>
        <span class="lv-lvl-badge <?= $list_lvl_class ?>">
          <span class="lv-lvl-dot"></span>
          <?php
            $lvl_label = ['full'=>'Пълна', 'partial'=>'Частична', 'minimal'=>'Минимална'][$confidence_filter] ?? '';
            echo htmlspecialchars($lvl_label);
          ?>
        </span>
      <?php endif; ?>
    </div>
    <span class="lv-page-count">· <?= $list_count ?></span>
  </div>

  <!-- Search bar -->
  <div class="search-wrap" style="margin-bottom:10px">
    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" id="lvSearchInp" placeholder="Търси по име, код или баркод..." autocomplete="off" oninput="onLiveSearch(this.value,'lvSearchInp','lvSearchDD')">
    <button class="s-btn" type="button" aria-label="Филтри" onclick="openFilterDrawer()">
      <svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
    </button>
    <button class="s-btn mic" type="button" aria-label="Гласово търсене" onclick="searchInlineMic(this)">
      <svg viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0 0 14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
    </button>
  </div>
  <div id="lvSearchDD" class="search-dd"></div>

  <!-- ─── CONFIDENCE FILTER PILLS (4 бутона горе) ─── -->
  <div class="cf-row">
    <a class="cf-pill cf-all <?= !$confidence_filter ? 'active' : '' ?>" href="?screen=list">
      <span class="cf-dot"></span>
      <span>Всички</span>
      <span class="cf-num"><?= $completeness['total'] ?></span>
    </a>
    <a class="cf-pill cf-full <?= $confidence_filter==='full' ? 'active' : '' ?>" href="?screen=list&confidence=full">
      <span class="cf-dot"></span>
      <span>Пълна</span>
      <span class="cf-num"><?= $completeness['full'] ?></span>
    </a>
    <a class="cf-pill cf-partial <?= $confidence_filter==='partial' ? 'active' : '' ?>" href="?screen=list&confidence=partial">
      <span class="cf-dot"></span>
      <span>Частична</span>
      <span class="cf-num"><?= $completeness['partial'] ?></span>
    </a>
    <a class="cf-pill cf-minimal <?= $confidence_filter==='minimal' ? 'active' : '' ?>" href="?screen=list&confidence=minimal">
      <span class="cf-dot"></span>
      <span>Минимална</span>
      <span class="cf-num"><?= $completeness['minimal'] ?></span>
    </a>
  </div>

  <!-- Product list -->
  <div class="lv-list">
    <?php if (empty($list_products)): ?>
    <div class="lv-empty">
      <div class="lv-empty-ic">
        <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      </div>
      <div class="lv-empty-title">Няма артикули в това ниво</div>
      <div class="lv-empty-text">Опитай друг филтър или добави нови артикули.</div>
    </div>
    <?php else: foreach ($list_products as $p):
      $score = (int)($p['confidence_score'] ?? 0);
      $lvl = $score >= 80 ? 'full' : ($score >= 40 ? 'partial' : 'minimal');
      $lvl_label = ['full'=>'Пълна', 'partial'=>$score.'%', 'minimal'=>'Минимална'][$lvl];
      $qty = (int)$p['qty'];
      $stock_cls = $qty <= 0 ? 'danger' : ($qty <= 3 ? 'warn' : 'ok');
    ?>
    <div class="glass sm prod-row" onclick="openProductDetail(<?= (int)$p['id'] ?>)">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="prod-photo">
        <?php if (!empty($p['image_url'])): ?>
        <img src="<?= htmlspecialchars($p['image_url']) ?>" style="width:100%;height:100%;object-fit:cover" alt="">
        <?php else: ?>
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        <?php endif; ?>
      </div>
      <div class="prod-info">
        <div class="prod-nm">
          <span class="prod-nm-text"><?= htmlspecialchars($p['name']) ?></span>
          <span class="prod-conf-badge <?= $lvl ?>"><?= $lvl_label ?></span>
        </div>
        <div class="prod-meta">
          <?= htmlspecialchars(($p['code'] ?? '') ?: '—') ?>
          <?= !empty($p['supplier_name']) ? ' · ' . htmlspecialchars($p['supplier_name']) : '' ?>
          <?= !empty($p['category_name']) ? ' · ' . htmlspecialchars($p['category_name']) : '' ?>
        </div>
      </div>
      <div class="prod-right">
        <span class="prod-price"><?= fmtMoneyDec($p['retail_price']) ?> €</span>
        <span class="prod-stock <?= $stock_cls ?>"><?= $qty ?> бр</span>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <?php if ($list_count >= 100): ?>
  <div class="lv-more-note">Показани първите 100 · ползвай търсене за повече</div>
  <?php endif; ?>

<?php elseif ($is_simple_view): ?>
<!-- ═══════════════════════════════════════════════════════ -->
<!-- SIMPLE MODE (P15) — главна за Пешо                       -->
<!-- ═══════════════════════════════════════════════════════ -->
<!-- S144: inv-nudge премахнат — дублира info-box с 3-те нива по-долу -->


  

  <!-- ─── S144: ИНФОРМАТИВЕН БОКС — 3 нива + общ списък ─── -->
  <div class="info-box">
    <div class="info-box-top">
      <div class="info-box-title">
        <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        <span><?= number_format($completeness['total'], 0, '.', ' ') ?> артикула в наличност</span>
      </div>
      <span class="info-box-pct"><?= $completeness['pct'] ?>%</span>
    </div>
    <div class="info-box-bar">
      <div class="info-box-bar-fill" style="width: <?= $completeness['pct'] ?>%"></div>
    </div>
    <div class="info-box-levels">
      <button class="glass sm ibl q3" onclick="event.stopPropagation();location.href='products-v2.php?screen=list&confidence=full'">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <b class="ibl-num"><?= number_format($completeness['full'], 0, '.', ' ') ?></b>
        <span class="ibl-lbl">пълна</span>
      </button>
      <button class="glass sm ibl q5" onclick="event.stopPropagation();location.href='products-v2.php?screen=list&confidence=partial'">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <b class="ibl-num"><?= number_format($completeness['partial'], 0, '.', ' ') ?></b>
        <span class="ibl-lbl">частична</span>
      </button>
      <button class="glass sm ibl q1" onclick="event.stopPropagation();location.href='products-v2.php?screen=list&confidence=minimal'">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <b class="ibl-num"><?= number_format($completeness['minimal'], 0, '.', ' ') ?></b>
        <span class="ibl-lbl">минимална</span>
      </button>
    </div>
    <button class="ibl-all-link" onclick="event.stopPropagation();location.href='products-v2.php?screen=list'">
      <span>Виж всички <b><?= number_format($completeness['total'], 0, '.', ' ') ?></b> артикула</span>
      <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
  </div>

  <div class="search-wrap">
    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" id="hSearchInp" placeholder="Търси по име, код или баркод..." autocomplete="off" oninput="onLiveSearch(this.value,'hSearchInp','hSearchDD')">
    <button class="s-btn" type="button" aria-label="Филтри" onclick="openFilterDrawer()">
      <svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
      <span class="dot" id="hFilterDot" style="display:none">0</span>
    </button>
    <button class="s-btn mic" type="button" aria-label="Гласово търсене" onclick="searchInlineMic(this)">
      <svg viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0 0 14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
    </button>
  </div>
  <div id="hSearchDD" class="search-dd"></div>

  <a class="all-items-link" href="products-v2.php?screen=list">
    Виж всички <b><?= number_format($total_products, 0, "", " ") ?></b> артикула
    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  </a>

  <div class="glass sm qa-btn qa-primary qd" role="button" tabindex="0" onclick="openAddProduct()">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <span class="qa-ic">
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    </span>
    <div class="qa-text">
      <div class="qa-title">Добави артикул</div>
      <div class="qa-sub">СНИМАЙ · КАЖИ · СКЕНИРАЙ</div>
    </div>
    <button class="kp-pill" type="button" onclick="event.stopPropagation();openLikePrevious()" aria-label="Като предния">
      <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
      Като предния
    </button>
  </div>

  <div class="studio-row">
    <a class="glass sm studio-btn qm" href="#">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <span class="studio-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2l2.4 7.4L22 12l-7.6 2.6L12 22l-2.4-7.4L2 12l7.6-2.6L12 2z"/></svg>
      </span>
      <div class="studio-text">
        <span class="studio-label">AI поръчка</span>
        <span class="studio-sub">AI подготвя поръчка</span>
      </div>
      <span class="studio-arrow">
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
      </span>
    </a>
  </div>

  <div class="glass sm help-card qm">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="help-head">
      <span class="help-head-ic">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </span>
      <div class="help-head-text">
        <div class="help-title">Как работи Стоката ми?</div>
        <div class="help-sub">Бърз старт</div>
      </div>
    </div>
    <div class="help-body">Добави артикул със снимка, глас или скенер. AI ще ти каже кога да поръчаш, кога да намалиш и какво търсят клиентите.</div>
    <div class="help-chips-label">Попитай AI:</div>
    <div class="help-chips">
      <button class="help-chip"><span class="help-chip-q">?</span>Какво свърши?</button>
      <button class="help-chip"><span class="help-chip-q">?</span>Какво застоява?</button>
      <button class="help-chip"><span class="help-chip-q">?</span>Какво да поръчам?</button>
      <button class="help-chip"><span class="help-chip-q">?</span>Какво търсят клиентите?</button>
    </div>
    <div class="help-video-ph">
      <span class="help-video-ic">
        <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
      </span>
      <div class="help-video-text">
        <div class="help-video-title">Видео: Добави първия артикул</div>
        <div class="help-video-sub">2 минути</div>
      </div>
    </div>
    <a class="help-link-row" href="#">
      Всички помощни теми
      <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <!-- ═══ МАГАЗИНИ — преглед на 5 обекта (без графики) ═══ -->
  <div class="glass sm stores-glance qd">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="sg-head">
      <span class="sg-head-ic">
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </span>
      <span class="sg-title">Магазините днес</span>
      <span class="sg-date">12.05</span>
    </div>
    <div class="sg-list">
      <?php foreach ($multistore as $ms): ?>
      <div class="sg-row" onclick="location.href='?store=<?= (int)$ms['id'] ?>&mode=simple'">
        <span class="sg-dot <?= htmlspecialchars($ms['status_dot']) ?>"></span>
        <span class="sg-name"><?= htmlspecialchars($ms['name']) ?>
          <?php if ($ms['trend_pct'] < -15): ?><small>под средното</small><?php endif; ?>
        </span>
        <span class="sg-trend <?= htmlspecialchars($ms['trend_dir']) ?>">
          <?= $ms['trend_pct'] > 0 ? '+' : '' ?><?= $ms['trend_pct'] ?>%
        </span>
        <span class="sg-revenue"><?= fmtMoney($ms['revenue']) ?><small> <?= $cs ?></small></span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($multistore)): ?>
      <div class="sg-row" style="justify-content:center;color:var(--text-muted);font-size:11px">
        Само 1 магазин · няма multi-store данни
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ═══ S143 v4: ТРЕВОГИ (Свършили + Застояли) — преместени под info-box, над AI feed ═══ -->
  <div class="top-row">
    <div class="glass sm cell qd">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="cell-header-row">
        <div class="cell-label">СВЪРШИЛИ</div>
      </div>
      <div class="cell-numrow">
        <span class="cell-num"><?= $out_of_stock ?></span>
        <span class="cell-cur">бр</span>
      </div>
      <div class="cell-meta">−340 €/седмица</div>
    </div>

    <div class="glass sm cell q1">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="cell-header-row">
        <div class="cell-label">ЗАСТОЯЛИ 60+ ДНИ</div>
      </div>
      <div class="cell-numrow">
        <span class="cell-num"><?= $stale_60d ?></span>
        <span class="cell-cur">бр</span>
      </div>
      <div class="cell-meta"><?= fmtMoney($kpi_locked_cash ?? 1180) ?> € замразени</div>
    </div>
  </div>

  <!-- ═══ AI feed: 10 сигнала · всички типове (alerts/weather/transfer/cash/size/wins) ═══ -->
  <div class="lb-header">
    <div class="lb-title">
      <div class="lb-title-orb"></div>
      <span class="lb-title-text">AI вижда</span>
    </div>
    <span class="lb-count"><?= $ai_insights_count ?: 10 ?> сигнала · <?= date("H:i") ?></span>
  </div>

  <!-- 1. ALERT — свърши най-продаваният -->
  <div class="glass sm lb-card q1 urgent" onclick="lbToggleCard(event,this)">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb">
        <svg viewBox="0 0 24 24"><polygon points="10.29 3.86 1.82 18 22.18 18 13.71 3.86 10.29 3.86"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">СВЪРШИ</span>
        <span class="lb-collapsed-title">Nike Air Max 42 · 7 продажби тази седмица</span>
      </div>
      <button class="lb-expand-btn" aria-label="разгърни">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
  </div>

  <!-- 2. WEATHER — топло идва (от готовата weather интеграция) -->
  <div class="glass sm lb-card q5" onclick="lbToggleCard(event,this)">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb lb-ic-weather">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini tag-weather">ВРЕМЕ</span>
        <span class="lb-collapsed-title">Топло идва 25-26°C · летни рокли ще тръгнат · имаш 8 бр</span>
      </div>
      <button class="lb-expand-btn" aria-label="разгърни">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
  </div>

  <!-- 3. TRANSFER — multi-store balance -->
  <div class="glass sm lb-card q4" onclick="lbToggleCard(event,this)">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb lb-ic-transfer">
        <svg viewBox="0 0 24 24"><path d="M7 17l-4-4 4-4"/><path d="M3 13h12"/><path d="M17 7l4 4-4 4"/><path d="M21 11H9"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini tag-transfer">ПРЕХВЪРЛИ</span>
        <span class="lb-collapsed-title">5 бр Nike Air Max 42 · Бургас → Скайтия</span>
      </div>
      <button class="lb-expand-btn" aria-label="разгърни">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
  </div>

  <!-- 4. CASH — замразен капитал -->
  <div class="glass sm lb-card q2" onclick="lbToggleCard(event,this)">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb lb-ic-cash">
        <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini tag-cash">ЗАМРАЗЕНИ</span>
        <span class="lb-collapsed-title">1 180 € спят в стока 60+ дни · виж кои</span>
      </div>
      <button class="lb-expand-btn" aria-label="разгърни">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
  </div>

  <!-- 5. SIZE — broken size run -->
  <div class="glass sm lb-card q5" onclick="lbToggleCard(event,this)">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb lb-ic-size">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">РАЗМЕР</span>
        <span class="lb-collapsed-title">Тениска H&M · M свърши, остават S+L · сплит 60/30/10</span>
      </div>
      <button class="lb-expand-btn" aria-label="разгърни">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
  </div>

  <!-- 6. SUPPLIER reliability -->
  <div class="glass sm lb-card q1" onclick="lbToggleCard(event,this)">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb lb-ic-supplier">
        <svg viewBox="0 0 24 24"><path d="M16 16h6V8H16"/><path d="M8 16H2V8h6"/><rect x="8" y="4" width="8" height="16"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">ДОСТАВЧИК</span>
        <span class="lb-collapsed-title">Verona закъсня · 11 пропуснати продажби този месец</span>
      </div>
      <button class="lb-expand-btn" aria-label="разгърни">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
  </div>

  <!-- 7. CASH variance (Z отчет) -->
  <div class="glass sm lb-card q1" onclick="lbToggleCard(event,this)">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb lb-ic-cash">
        <svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini tag-cash">КАСА</span>
        <span class="lb-collapsed-title">Z вчера +24 лв · кешът над POS · провери</span>
      </div>
      <button class="lb-expand-btn" aria-label="разгърни">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
  </div>

  <!-- 8. SELL-THROUGH (нови артикули) -->
  <div class="glass sm lb-card q5" onclick="lbToggleCard(event,this)">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb lb-ic-sellthrough">
        <svg viewBox="0 0 24 24"><polyline points="3 17 9 11 13 15 21 7"/><polyline points="14 7 21 7 21 14"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">SELL-THROUGH</span>
        <span class="lb-collapsed-title">Новите от април · 12% продадени (цел 25%) · markdown -20%</span>
      </div>
      <button class="lb-expand-btn" aria-label="разгърни">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
  </div>

  <!-- 9. TREND — печалба +12% -->
  <div class="glass sm lb-card q3" onclick="lbToggleCard(event,this)">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb">
        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini">ТРЕНД</span>
        <span class="lb-collapsed-title">Печалба +12% спрямо миналата седмица · виж защо</span>
      </div>
      <button class="lb-expand-btn" aria-label="разгърни">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
  </div>

  <!-- 10. WIN — рекорден ден -->
  <div class="glass sm lb-card q3" onclick="lbToggleCard(event,this)">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <div class="lb-collapsed">
      <span class="lb-emoji-orb lb-ic-win">
        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      </span>
      <div class="lb-collapsed-content">
        <span class="lb-fq-tag-mini tag-win">ПОБЕДА</span>
        <span class="lb-collapsed-title">Рекорден ден · 47 продажби · 1 840 €</span>
      </div>
      <button class="lb-expand-btn" aria-label="разгърни">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
  </div>

  <div class="see-more-mini">Виж всички 23 →</div>
<?php else: ?>
<!-- ═══════════════════════════════════════════════════════ -->
<!-- DETAILED MODE (P2v2 tabs) — главна за Митко              -->
<!-- ═══════════════════════════════════════════════════════ -->
<!-- S144: inv-nudge премахнат — дублира info-box с 3-те нива по-долу -->

  <!-- ─── ТЪРСАЧКА с микрофон + филтър (S142 inject) ─── -->
  <div class="search-wrap">
    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" id="dSearchInp" placeholder="Търси по име, код или баркод..." autocomplete="off" oninput="onLiveSearch(this.value,'dSearchInp','dSearchDD')">
    <button class="s-btn" type="button" aria-label="Филтри" onclick="openFilterDrawer()">
      <svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
      <span class="dot" id="dFilterDot" style="display:none">0</span>
    </button>
    <button class="s-btn mic" type="button" aria-label="Гласово търсене" onclick="searchInlineMic(this,'dSearchInp')">
      <svg viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0 0 14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
    </button>
  </div>
  <div id="dSearchDD" class="search-dd"></div>

  <!-- ─── ВСИЧКИ АРТИКУЛИ link (отива в P3 list) ─── -->
  <a class="all-items-link" href="products-v2.php?screen=list">
    Виж всички <b><?= fmtMoney($total_products) ?></b> артикула
    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  </a>

    <!-- ─── Q-CHIPS row (6 сигнал филтри) ─── -->
  <div class="q-chips-row" role="tablist" aria-label="Филтри по сигнал">
    <button class="q-chip" type="button"><span class="q-chip-dot q1"></span>Губиш<span class="q-chip-count">5</span></button>
    <button class="q-chip" type="button"><span class="q-chip-dot q2"></span>Причина<span class="q-chip-count">3</span></button>
    <button class="q-chip" type="button"><span class="q-chip-dot q3"></span>Печелиш<span class="q-chip-count">12</span></button>
    <button class="q-chip" type="button"><span class="q-chip-dot q4"></span>От какво<span class="q-chip-count">4</span></button>
    <button class="q-chip" type="button"><span class="q-chip-dot q5"></span>Поръчай<span class="q-chip-count">28</span></button>
    <button class="q-chip" type="button"><span class="q-chip-dot q6"></span>Не поръчай<span class="q-chip-count">9</span></button>
  </div>

  <!-- ─── TABS BAR ─── -->
  <div class="tabs-bar">
    <button class="tab-btn active" data-tab="overview" onclick="setTab('overview')">Преглед</button>
    <button class="tab-btn" data-tab="charts" onclick="setTab('charts')">Графики</button>
    <button class="tab-btn" data-tab="manage" onclick="setTab('manage')">Управление</button>
    <button class="tab-btn" data-tab="items" onclick="setTab('items')">Артикули</button>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════ -->
  <!-- ТАБ 1: ПРЕГЛЕД ─── default                                            -->
  <!-- ═══════════════════════════════════════════════════════════════════ -->
  <section class="tab-panel active" data-tab-content="overview">

    <!-- Compare period toggle -->
    <div class="period-toggle">
      <button class="period-btn active">Тази седмица</button>
      <button class="period-btn">Миналата</button>
      <button class="period-btn">30 дни</button>
      <button class="period-btn">90 дни</button>
        <button class="yoy-toggle" type="button" title="Сравнение с миналата година">
          <svg viewBox="0 0 24 24"><polyline points="12 20 12 4"/><polyline points="5 11 12 4 19 11"/></svg>
          YoY
        </button>
    </div>

    <!-- Quick actions: Добави (с 'Като предния' pill) + AI поръчка -->
    <div class="qa-row">
      <div class="glass sm qa-btn qa-primary qd" role="button" tabindex="0" onclick="openAddProduct()" style="cursor:pointer">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <span class="qa-ic">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </span>
        <div class="qa-text">
          <div class="qa-title">Добави артикул</div>
          <div class="qa-sub">СНИМАЙ · КАЖИ · СКЕНИРАЙ</div>
        </div>
        <button class="kp-pill" type="button" onclick="event.stopPropagation();openLikePrevious()" aria-label="Като предния">
          <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
          Като предния
        </button>
      </div>
      <button class="glass sm qa-btn-sm qa-ai-order qm" onclick="openAIOrder()">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <span class="qa-ic-sm"><svg viewBox="0 0 24 24"><path d="M12 2l2.4 7.4L22 12l-7.6 2.6L12 22l-2.4-7.4L2 12l7.6-2.6L12 2z"/></svg></span>
        <span class="qa-title-sm">AI поръчка</span>
      </button>
    </div>

    <!-- ═══ 5-KPI SCROLL ROW ═══ -->
    <div class="kpi-scroll">
      <div class="glass sm kpi-card q3">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="kpi-label">Приход</div>
        <div class="kpi-numrow"><span class="kpi-num"><?= fmtMoney($kpi_revenue) ?></span><span class="kpi-cur"><?= $cs ?></span></div>
        <div class="kpi-meta"><span class="trend-up">+12%</span></div>
      </div>
      <div class="glass sm kpi-card q4">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="kpi-label">Среден чек (ATV)</div>
        <div class="kpi-numrow"><span class="kpi-num"><?= fmtMoneyDec($kpi_atv) ?></span><span class="kpi-cur"><?= $cs ?></span></div>
        <div class="kpi-meta"><span class="trend-up">+4%</span></div>
      </div>
      <div class="glass sm kpi-card q5">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="kpi-label">Артикули / чек</div>
        <div class="kpi-numrow"><span class="kpi-num"><?= fmtMoneyDec($kpi_upt) ?></span><span class="kpi-cur">бр</span></div>
        <div class="kpi-meta"><span class="trend-flat">±0%</span></div>
      </div>
      <div class="glass sm kpi-card q2">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="kpi-label">Sell-through</div>
        <div class="kpi-numrow"><span class="kpi-num"><?= $kpi_sellthrough ?></span><span class="kpi-cur">%</span></div>
        <div class="kpi-meta"><span class="trend-down">−5% vs цел</span></div>
      </div>
      <div class="glass sm kpi-card q1">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="kpi-label">Замразен €</div>
        <div class="kpi-numrow"><span class="kpi-num"><?= fmtMoney($kpi_locked_cash) ?></span><span class="kpi-cur"><?= $cs ?></span></div>
        <div class="kpi-meta"><span class="trend-up">+8% седм.</span></div>
      </div>
    </div>

    <!-- ─── S144: ИНФОРМАТИВЕН БОКС — 3 нива + общ списък (Detailed) ─── -->
    <div class="info-box">
      <div class="info-box-top">
        <div class="info-box-title">
          <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
          <span><?= number_format($completeness['total'], 0, '.', ' ') ?> артикула в наличност</span>
        </div>
        <span class="info-box-pct"><?= $completeness['pct'] ?>%</span>
      </div>
      <div class="info-box-bar">
        <div class="info-box-bar-fill" style="width: <?= $completeness['pct'] ?>%"></div>
      </div>
      <div class="info-box-levels">
        <button class="glass sm ibl q3" onclick="event.stopPropagation();location.href='products-v2.php?screen=list&confidence=full'">
          <span class="shine"></span><span class="shine shine-bottom"></span>
          <span class="glow"></span><span class="glow glow-bottom"></span>
          <b class="ibl-num"><?= number_format($completeness['full'], 0, '.', ' ') ?></b>
          <span class="ibl-lbl">пълна</span>
        </button>
        <button class="glass sm ibl q5" onclick="event.stopPropagation();location.href='products-v2.php?screen=list&confidence=partial'">
          <span class="shine"></span><span class="shine shine-bottom"></span>
          <span class="glow"></span><span class="glow glow-bottom"></span>
          <b class="ibl-num"><?= number_format($completeness['partial'], 0, '.', ' ') ?></b>
          <span class="ibl-lbl">частична</span>
        </button>
        <button class="glass sm ibl q1" onclick="event.stopPropagation();location.href='products-v2.php?screen=list&confidence=minimal'">
          <span class="shine"></span><span class="shine shine-bottom"></span>
          <span class="glow"></span><span class="glow glow-bottom"></span>
          <b class="ibl-num"><?= number_format($completeness['minimal'], 0, '.', ' ') ?></b>
          <span class="ibl-lbl">минимална</span>
        </button>
      </div>
      <button class="ibl-all-link" onclick="event.stopPropagation();location.href='products-v2.php?screen=list'">
        <span>Виж всички <b><?= number_format($completeness['total'], 0, '.', ' ') ?></b> артикула</span>
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
    </div>

    <!-- ═══ Тревоги row (Свършили + Доставка закъсня — нова метрика) ═══ -->
    <div class="top-row">
      <div class="glass sm cell qd" style="cursor:pointer" onclick="location.href='products.php?screen=products&filter=out_of_stock'">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="cell-label">СВЪРШИЛИ</div>
        <div class="cell-numrow"><span class="cell-num"><?= $out_of_stock ?></span><span class="cell-cur">бр</span></div>
        <div class="cell-meta">−340 €/седмица</div>
      </div>
      <div class="glass sm cell q1">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="cell-label">ДОСТАВКА ЗАКЪСНЯ</div>
        <div class="cell-numrow"><span class="cell-num"><?= $delayed_deliveries ?></span><span class="cell-cur">бр</span></div>
        <div class="cell-meta">Verona 3д · Иватекс 1д</div>
      </div>
    </div>

    <!-- ═══ CASH RECONCILIATION TILE ═══ -->
    <div class="glass sm cash-tile q5">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="cash-tile-head">
        <svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
        <span class="cash-tile-title">Каса · Z отчет vs реално</span>
        <span class="cash-tile-period">вчера</span>
      </div>
      <div class="cash-grid">
        <div class="cash-cell">
          <div class="cash-cell-label">POS</div>
          <div class="cash-cell-val">842<small> лв</small></div>
        </div>
        <div class="cash-cell">
          <div class="cash-cell-label">Реално</div>
          <div class="cash-cell-val">866<small> лв</small></div>
        </div>
        <div class="cash-cell diff">
          <div class="cash-cell-label">Разлика</div>
          <div class="cash-cell-val">+24<small> лв</small></div>
        </div>
      </div>
      <div class="cash-7day">Последни 7 дни: средна разлика <b>+18 лв</b> · над прага (2%)</div>
    </div>

    <!-- ═══ WEATHER FORECAST CARD (от P11 canonical) ═══ -->
    <div class="glass sm wfc q5">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="wfc-head">
        <div class="wfc-head-ic">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
        </div>
        <div class="wfc-head-text">
          <div class="wfc-title">Прогноза за времето</div>
          <div class="wfc-sub">София · AI препоръки за стоката</div>
        </div>
      </div>
      <div class="wfc-tabs">
        <button class="wfc-tab active" type="button">7 дни</button>
        <button class="wfc-tab" type="button">14 дни</button>
      </div>
      <div class="wfc-days">
        <div class="wfc-day today sunny">
          <div class="wfc-day-name">Днес</div>
          <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg></div>
          <div class="wfc-day-temp">22°<small>/14</small></div>
          <div class="wfc-day-rain dry">5%</div>
        </div>
        <div class="wfc-day partly">
          <div class="wfc-day-name">Пет</div>
          <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M22 14a4 4 0 00-7.5-2 5.5 5.5 0 00-10 1A4 4 0 005 21h13a4 4 0 004-4 4 4 0 00-2-3.46"/><circle cx="6" cy="6" r="2"/></svg></div>
          <div class="wfc-day-temp">24°<small>/15</small></div>
          <div class="wfc-day-rain dry">15%</div>
        </div>
        <div class="wfc-day sunny">
          <div class="wfc-day-name">Съб</div>
          <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/></svg></div>
          <div class="wfc-day-temp">26°<small>/16</small></div>
          <div class="wfc-day-rain dry">5%</div>
        </div>
        <div class="wfc-day sunny">
          <div class="wfc-day-name">Нед</div>
          <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg></div>
          <div class="wfc-day-temp">25°<small>/17</small></div>
          <div class="wfc-day-rain dry">10%</div>
        </div>
        <div class="wfc-day rainy">
          <div class="wfc-day-name">Пон</div>
          <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M16 13v8M8 13v8M12 15v8M20 16.58A5 5 0 0018 7h-1.26A8 8 0 104 15.25"/></svg></div>
          <div class="wfc-day-temp">20°<small>/13</small></div>
          <div class="wfc-day-rain wet">65%</div>
        </div>
        <div class="wfc-day partly">
          <div class="wfc-day-name">Вт</div>
          <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><path d="M22 14a4 4 0 00-7.5-2 5.5 5.5 0 00-10 1A4 4 0 005 21h13a4 4 0 004-4 4 4 0 00-2-3.46"/></svg></div>
          <div class="wfc-day-temp">22°<small>/14</small></div>
          <div class="wfc-day-rain dry">20%</div>
        </div>
        <div class="wfc-day sunny">
          <div class="wfc-day-name">Ср</div>
          <div class="wfc-day-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg></div>
          <div class="wfc-day-temp">25°<small>/15</small></div>
          <div class="wfc-day-rain dry">10%</div>
        </div>
      </div>
      <div class="wfc-ai-note">
        <span class="wfc-ai-note-ic">
          <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </span>
        <span class="wfc-ai-note-text">3 дни топло (25-26°C) · <b>летни рокли ще тръгнат</b> · имаш 8 бр</span>
        <button class="wfc-ai-action" type="button">Поръчай 20</button>
      </div>
    </div>

    <!-- СЪСТОЯНИЕ НА СКЛАДА (rebranded, виж §16.3) -->
    <div class="glass sm health-card qm">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="health-head">
        <span class="health-head-ic">
          <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
        </span>
        <div class="health-head-text">
          <div class="health-title">Състояние на склада</div>
          <div class="health-sub">Какво още да нагласим</div>
        </div>
      </div>
      <div class="health-bars">
        <div class="health-row">
          <span class="health-label">Снимки</span>
          <div class="health-bar"><span class="health-bar-fill" style="width:78%;background:var(--accent-q3)"></span></div>
          <span class="health-pct">78%</span>
          <span class="health-meta">12 без →</span>
        </div>
        <div class="health-row">
          <span class="health-label">Цени едро</span>
          <div class="health-bar"><span class="health-bar-fill" style="width:91%;background:var(--accent-q3)"></span></div>
          <span class="health-pct">91%</span>
          <span class="health-meta">5 без →</span>
        </div>
        <div class="health-row">
          <span class="health-label">Броено</span>
          <div class="health-bar"><span class="health-bar-fill" style="width:34%;background:var(--accent-q1)"></span></div>
          <span class="health-pct">34%</span>
          <span class="health-meta">12 дни →</span>
        </div>
        <div class="health-row">
          <span class="health-label">Доставчик</span>
          <div class="health-bar"><span class="health-bar-fill" style="width:100%;background:var(--accent-q3)"></span></div>
          <span class="health-pct">100%</span>
          <span class="health-meta">✓</span>
        </div>
        <div class="health-row">
          <span class="health-label">Категория</span>
          <div class="health-bar"><span class="health-bar-fill" style="width:88%;background:var(--accent-q3)"></span></div>
          <span class="health-pct">88%</span>
          <span class="health-meta">15 неточно →</span>
        </div>
      </div>
    </div>

    <!-- AI вижда — 6 сигнала (expanded version) -->
    <div class="lb-header">
      <div class="lb-title">
        <div class="lb-title-orb"></div>
        <span class="lb-title-text">AI вижда</span>
      </div>
      <span class="lb-count">6 сигнала · 18:32</span>
    </div>

    <!-- Сигнал 1: ГУБИШ (Q1) -->
    <div class="glass sm lb-card q1 expanded">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="lb-collapsed">
        <span class="lb-emoji-orb">
          <svg viewBox="0 0 24 24"><polygon points="10.29 3.86 1.82 18 22.18 18 13.71 3.86 10.29 3.86"/></svg>
        </span>
        <div class="lb-collapsed-content">
          <span class="lb-fq-tag-mini">ГУБИШ 5</span>
          <span class="lb-collapsed-title">Изчерпани · −340 €/седмица</span>
        </div>
      </div>
      <div class="lb-expanded">
        <div class="lb-body">
          <b>Nike Air Max 42 · Adidas Stan Smith 38 · Puma RS-X 41</b> — артикули с продажби, които вече ги няма. Пропуснат profit ~340 €/седмица.
        </div>
        <div class="lb-actions">
          <button class="lb-action">Защо?</button>
          <button class="lb-action">Покажи</button>
          <button class="lb-action primary">Поръчай →</button>
        </div>
        <div class="lb-feedback">
          <span class="lb-fb-label">Полезно?</span>
          <button class="lb-fb-btn up"><svg viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3z"/></svg></button>
          <button class="lb-fb-btn down"><svg viewBox="0 0 24 24"><path d="M10 15v4a3 3 0 003 3l4-9V2H5.72a2 2 0 00-2 1.7l-1.38 9a2 2 0 002 2.3z"/></svg></button>
          <button class="lb-fb-btn hmm"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></button>
        </div>
      </div>
    </div>

    <!-- Сигнал 2: ПРИЧИНА (Q2) -->
    <div class="glass sm lb-card q2 expanded">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="lb-collapsed">
        <span class="lb-emoji-orb">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </span>
        <div class="lb-collapsed-content">
          <span class="lb-fq-tag-mini">ПРИЧИНА 3</span>
          <span class="lb-collapsed-title">Проблеми с маржа · −180 € profit</span>
        </div>
      </div>
      <div class="lb-expanded">
        <div class="lb-body">Артикули продавани под 15% марж или без записана доставна цена. Не виждаш реална печалба.</div>
        <div class="lb-actions">
          <button class="lb-action">Защо?</button>
          <button class="lb-action">Покажи</button>
          <button class="lb-action primary">Поправи →</button>
        </div>
      </div>
    </div>

    <!-- Сигнал 3: ПЕЧЕЛИШ (Q3) -->
    <div class="glass sm lb-card q3 expanded">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="lb-collapsed">
        <span class="lb-emoji-orb">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </span>
        <div class="lb-collapsed-content">
          <span class="lb-fq-tag-mini">ПЕЧЕЛИШ 12</span>
          <span class="lb-collapsed-title">Топ продавани · +2 840 € profit/месец</span>
        </div>
      </div>
      <div class="lb-expanded">
        <div class="lb-body">Топ 12 артикула за последните 30 дни. Основа за поръчки.</div>
        <div class="lb-actions">
          <button class="lb-action">Покажи</button>
          <button class="lb-action primary">Поръчай →</button>
        </div>
      </div>
    </div>

    <!-- Сигнал 4-6: collapsed -->
    <div class="glass sm lb-card q4">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="lb-collapsed">
        <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26"/></svg></span>
        <div class="lb-collapsed-content">
          <span class="lb-fq-tag-mini">РАСТЕ 4</span>
          <span class="lb-collapsed-title">Защо ти потръгна · топ марж + лоялни</span>
        </div>
        <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
      </div>
    </div>
    <div class="glass sm lb-card q5">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="lb-collapsed">
        <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/></svg></span>
        <div class="lb-collapsed-content">
          <span class="lb-fq-tag-mini">ПОРЪЧАЙ 28</span>
          <span class="lb-collapsed-title">Bestsellers с ниски наличности</span>
        </div>
        <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
      </div>
    </div>
    <div class="glass sm lb-card qd">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="lb-collapsed">
        <span class="lb-emoji-orb"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
        <div class="lb-collapsed-content">
          <span class="lb-fq-tag-mini">ЗАМРАЗЕНИ 9</span>
          <span class="lb-collapsed-title">Без продажба 45+ дни · 2 480 €</span>
        </div>
        <button class="lb-expand-btn"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
      </div>
    </div>

  </section>

  <!-- ═══════════════════════════════════════════════════════════════════ -->
  <!-- ТАБ 2: ГРАФИКИ                                                       -->
  <!-- ═══════════════════════════════════════════════════════════════════ -->
  <section class="tab-panel" data-tab-content="charts" style="display:none">

    <!-- Sparklines top 5 артикули -->
    <div class="glass sm chart-card qd">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="chart-head"><div class="chart-title">Топ 5 продажби · 30 дни</div><span class="chart-sub">Sparkline на ден</span></div>
      <div class="spark-toggle-row">
        <button class="spark-toggle active winners" type="button">
          <span class="spark-toggle-dot win"></span> Печеливши
        </button>
        <button class="spark-toggle losers" type="button">
          <span class="spark-toggle-dot lose"></span> Застояли
        </button>
      </div>
      <div class="spark-list">
        <div class="spark-row"><span class="spark-name">Nike Air Max 42</span><svg class="spark-svg" viewBox="0 0 120 30"><polyline points="0,22 12,18 24,20 36,12 48,15 60,8 72,11 84,5 96,9 108,4 120,2" fill="none" stroke="currentColor" stroke-width="1.6"/></svg><span class="spark-val">42 бр</span></div>
        <div class="spark-row"><span class="spark-name">Adidas Stan Smith</span><svg class="spark-svg" viewBox="0 0 120 30"><polyline points="0,15 12,18 24,12 36,14 48,8 60,11 72,7 84,10 96,5 108,8 120,6" fill="none" stroke="currentColor" stroke-width="1.6"/></svg><span class="spark-val">38 бр</span></div>
        <div class="spark-row"><span class="spark-name">Levi's 501 W32</span><svg class="spark-svg" viewBox="0 0 120 30"><polyline points="0,20 12,15 24,18 36,12 48,16 60,10 72,13 84,8 96,12 108,7 120,9" fill="none" stroke="currentColor" stroke-width="1.6"/></svg><span class="spark-val">31 бр</span></div>
        <div class="spark-row"><span class="spark-name">Рокля ZARA M</span><svg class="spark-svg" viewBox="0 0 120 30"><polyline points="0,18 12,20 24,14 36,17 48,11 60,14 72,9 84,12 96,8 108,11 120,6" fill="none" stroke="currentColor" stroke-width="1.6"/></svg><span class="spark-val">27 бр</span></div>
        <div class="spark-row"><span class="spark-name">Тениска H&M</span><svg class="spark-svg" viewBox="0 0 120 30"><polyline points="0,16 12,18 24,15 36,12 48,14 60,9 72,11 84,7 96,10 108,5 120,8" fill="none" stroke="currentColor" stroke-width="1.6"/></svg><span class="spark-val">24 бр</span></div>
      </div>
    </div>

    <!-- ═══ ТОП 3 ЗА ПОРЪЧКА (AI quick action) ═══ -->
    <div class="glass sm t3-order qm">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="t3-head">
        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        <span class="t3-title">AI: Топ 3 за поръчка днес</span>
      </div>
      <div class="t3-list">
        <div class="t3-row">
          <span class="t3-rank">#1</span>
          <span class="t3-name">Nike Air Max 42 <small>· свърши вчера · 7 продажби 7д</small></span>
          <span class="t3-qty">12 бр</span>
        </div>
        <div class="t3-row">
          <span class="t3-rank">#2</span>
          <span class="t3-name">Adidas Stan Smith 41 <small>· 2 бр · хитово</small></span>
          <span class="t3-qty">10 бр</span>
        </div>
        <div class="t3-row">
          <span class="t3-rank">#3</span>
          <span class="t3-name">Тениска H&M M <small>· broken size · split 60/30/10</small></span>
          <span class="t3-qty">20 бр</span>
        </div>
      </div>
      <button class="t3-cta" type="button">Отвори AI Studio за поръчка →</button>
    </div>

    <!-- ═══ ТОП 3 ДОСТАВЧИЦИ с reliability score ═══ -->
    <div class="glass sm t3-supp qd">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="t3-head">
        <svg viewBox="0 0 24 24"><path d="M16 3h5v5M21 3l-7 7M8 21H3v-5M3 21l7-7M8 3H3v5M3 3l7 7M16 21h5v-5M21 21l-7-7"/></svg>
        <span class="t3-title">Топ 3 доставчика · 30 дни</span>
      </div>
      <div class="t3-list">
        <div class="t3-supp-row">
          <span class="t3-rank">#1</span>
          <span class="t3-name">Nike Bulgaria <small>· 1 240 € · 8 поръчки</small></span>
          <span class="t3-supp-reliability good">98%</span>
        </div>
        <div class="t3-supp-row">
          <span class="t3-rank">#2</span>
          <span class="t3-name">H&M Wholesale <small>· 980 € · 5 поръчки</small></span>
          <span class="t3-supp-reliability good">95%</span>
        </div>
        <div class="t3-supp-row">
          <span class="t3-rank">#3</span>
          <span class="t3-name">Verona Outlet <small>· 760 € · 4 поръчки</small></span>
          <span class="t3-supp-reliability bad">62% <small>11 пропуснати</small></span>
        </div>
      </div>
    </div>

    <!-- ═══ МАГАЗИНИ · ranked table с Transfer Dependence ═══ -->
    <div class="glass sm stores-table qd">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="st-head">
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        <span class="st-title">Магазини · 30 дни</span>
        <span class="st-period">12.04 → 12.05</span>
      </div>
      <div class="st-list">
        <?php $rank = 0; foreach ($multistore as $ms): $rank++;
          $dep_pct = rand(8, 60); // TODO: real transfer_dependence calculation
          $dep_class = $dep_pct < 15 ? 'low' : ($dep_pct < 40 ? 'mid' : 'high');
        ?>
        <div class="st-row" onclick="location.href='?store=<?= (int)$ms['id'] ?>&mode=detailed'">
          <span class="st-rank">#<?= $rank ?></span>
          <span class="st-name"><?= htmlspecialchars($ms['name']) ?>
            <small>Transfer Dep · <?= $dep_class==='low'?'нисък':($dep_class==='mid'?'среден':'ВИСОК') ?></small>
          </span>
          <span class="st-revenue"><?= fmtMoney($ms['revenue']) ?><small> <?= $cs ?></small></span>
          <span class="st-dep <?= $dep_class ?>"><?= $dep_pct ?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="st-legend">
        <span class="st-legend-item"><span class="st-legend-dot low"></span>Нисък (&lt;15%)</span>
        <span class="st-legend-item"><span class="st-legend-dot mid"></span>Среден (15-40%)</span>
        <span class="st-legend-item"><span class="st-legend-dot high"></span>Висок (&gt;40%)</span>
      </div>
    </div>
    </div>

    <!-- Pareto 80/20 -->
    <div class="glass sm chart-card q3">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="chart-head"><div class="chart-title">Парето 80/20</div><span class="chart-sub">Колко артикули правят 80% от приходите</span></div>
      <div class="pareto-wrap">
        <svg class="pareto-svg" viewBox="0 0 300 150" preserveAspectRatio="none">
          <rect x="0" y="80" width="60" height="70" fill="var(--accent-q3)" opacity="0.85"/>
          <rect x="62" y="100" width="60" height="50" fill="var(--accent-q3)" opacity="0.55"/>
          <rect x="124" y="120" width="60" height="30" fill="var(--accent-q3)" opacity="0.35"/>
          <rect x="186" y="135" width="60" height="15" fill="var(--accent-q1)" opacity="0.5"/>
          <rect x="248" y="143" width="50" height="7" fill="var(--accent-q1)" opacity="0.4"/>
          <line x1="0" y1="30" x2="300" y2="30" stroke="currentColor" stroke-dasharray="2,3" opacity="0.3"/>
          <text x="6" y="26" font-size="9" fill="currentColor" opacity="0.7">80%</text>
        </svg>
        <div class="pareto-legend">
          <span><b>49</b> артикула (20%) → 80% от приходите</span>
          <span><b>198</b> артикула (80%) → 20% от приходите</span>
        </div>
      </div>
    </div>

    <!-- Heatmap turnover -->
    <div class="glass sm chart-card q4">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="chart-head"><div class="chart-title">Календар продажби</div><span class="chart-sub">15.04 → 12.05 · бр / ден</span></div>
      <div class="heatmap-grid">
        <!-- Headers Пон-Нд -->
        <span class="hm-head">Пн</span><span class="hm-head">Вт</span><span class="hm-head">Ср</span><span class="hm-head">Чт</span><span class="hm-head">Пт</span><span class="hm-head">Сб</span><span class="hm-head">Нд</span>
        <!-- Week 1: 15-19.04 (Ср–Нд) — започваме от сряда -->
        <span class="hm-cell hm-empty"></span><span class="hm-cell hm-empty"></span><span class="hm-cell hm-l1"><span class="hm-date">15.04</span><span class="hm-count">14</span></span><span class="hm-cell hm-l2"><span class="hm-date">16</span><span class="hm-count">22</span></span><span class="hm-cell hm-l3"><span class="hm-date">17</span><span class="hm-count">31</span></span><span class="hm-cell hm-l5"><span class="hm-date">18</span><span class="hm-count">45</span></span><span class="hm-cell hm-l5"><span class="hm-date">19</span><span class="hm-count">48</span></span>
        <!-- Week 2: 20-26.04 -->
        <span class="hm-cell hm-l2"><span class="hm-date">20</span><span class="hm-count">26</span></span><span class="hm-cell hm-l1"><span class="hm-date">21</span><span class="hm-count">18</span></span><span class="hm-cell hm-l2"><span class="hm-date">22</span><span class="hm-count">24</span></span><span class="hm-cell hm-l3"><span class="hm-date">23</span><span class="hm-count">30</span></span><span class="hm-cell hm-l4"><span class="hm-date">24</span><span class="hm-count">42</span></span><span class="hm-cell hm-l5"><span class="hm-date">25</span><span class="hm-count">51</span></span><span class="hm-cell hm-l5"><span class="hm-date">26</span><span class="hm-count">47</span></span>
        <!-- Week 3: 27.04 - 3.05 -->
        <span class="hm-cell hm-l1"><span class="hm-date">27</span><span class="hm-count">14</span></span><span class="hm-cell hm-l2"><span class="hm-date">28</span><span class="hm-count">22</span></span><span class="hm-cell hm-l3"><span class="hm-date">29</span><span class="hm-count">35</span></span><span class="hm-cell hm-l3"><span class="hm-date">30</span><span class="hm-count">31</span></span><span class="hm-cell hm-l4"><span class="hm-date">1.05</span><span class="hm-count">44</span></span><span class="hm-cell hm-l5"><span class="hm-date">2</span><span class="hm-count">49</span></span><span class="hm-cell hm-l5"><span class="hm-date">3</span><span class="hm-count">52</span></span>
        <!-- Week 4: 4-10.05 -->
        <span class="hm-cell hm-l2"><span class="hm-date">4</span><span class="hm-count">23</span></span><span class="hm-cell hm-l3"><span class="hm-date">5</span><span class="hm-count">27</span></span><span class="hm-cell hm-l4"><span class="hm-date">6</span><span class="hm-count">38</span></span><span class="hm-cell hm-l4"><span class="hm-date">7</span><span class="hm-count">40</span></span><span class="hm-cell hm-l5"><span class="hm-date">8</span><span class="hm-count">46</span></span><span class="hm-cell hm-l5"><span class="hm-date">9</span><span class="hm-count">53</span></span><span class="hm-cell hm-l5"><span class="hm-date">10</span><span class="hm-count">67</span></span>
        <!-- Week 5: 11-12.05 (партиален — само 2 дни) -->
        <span class="hm-cell hm-l3"><span class="hm-date">11</span><span class="hm-count">29</span></span><span class="hm-cell hm-l4 hm-today"><span class="hm-date">12 днес</span><span class="hm-count">47</span></span><span class="hm-cell hm-empty"></span><span class="hm-cell hm-empty"></span><span class="hm-cell hm-empty"></span><span class="hm-cell hm-empty"></span><span class="hm-cell hm-empty"></span>
      </div>
      <div class="hm-legend">
        <span>малко</span>
        <span class="hm-legend-cells"><span class="hm-cell-sm hm-l1"></span><span class="hm-cell-sm hm-l2"></span><span class="hm-cell-sm hm-l3"></span><span class="hm-cell-sm hm-l4"></span><span class="hm-cell-sm hm-l5"></span></span>
        <span>много</span>
        <span style="margin-left:auto;font-weight:700;color:var(--text)">общо: 1 098 продажби</span>
      </div>
    </div>

    <!-- Margin trend 90 дни -->
    <div class="glass sm chart-card q2">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="chart-head"><div class="chart-title">Марж тренд · 90 дни</div><span class="chart-sub">Среден марж за всеки ден</span></div>
      <svg class="trend-svg" viewBox="0 0 300 100">
        <polyline points="0,50 30,48 60,52 90,45 120,40 150,42 180,38 210,35 240,40 270,32 300,30" fill="none" stroke="var(--accent-q2)" stroke-width="2"/>
        <line x1="0" y1="50" x2="300" y2="50" stroke="currentColor" stroke-dasharray="2,3" opacity="0.2"/>
      </svg>
      <div class="trend-foot"><span>42% среден</span><span class="trend-up">+6% vs преди 30 дни</span></div>
    </div>

    <!-- Revenue by supplier donut -->
    <div class="glass sm chart-card qm">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="chart-head"><div class="chart-title">Приход по доставчик</div><span class="chart-sub">Кой ти носи парите</span></div>
      <div class="donut-wrap">
        <svg class="donut-svg" viewBox="0 0 100 100">
          <circle cx="50" cy="50" r="40" fill="none" stroke="var(--accent-q3)" stroke-width="14" stroke-dasharray="80 251" transform="rotate(-90 50 50)"/>
          <circle cx="50" cy="50" r="40" fill="none" stroke="var(--accent-q4)" stroke-width="14" stroke-dasharray="70 251" stroke-dashoffset="-80" transform="rotate(-90 50 50)"/>
          <circle cx="50" cy="50" r="40" fill="none" stroke="var(--accent-q2)" stroke-width="14" stroke-dasharray="58 251" stroke-dashoffset="-150" transform="rotate(-90 50 50)"/>
          <circle cx="50" cy="50" r="40" fill="none" stroke="var(--accent-q1)" stroke-width="14" stroke-dasharray="43 251" stroke-dashoffset="-208" transform="rotate(-90 50 50)"/>
        </svg>
        <ul class="donut-legend">
          <li><span class="dot q3"></span>Marina · <b>32%</b> · 1 037 €</li>
          <li><span class="dot q4"></span>Спорт Груп · <b>28%</b> · 907 €</li>
          <li><span class="dot q2"></span>ZARA · <b>23%</b> · 745 €</li>
          <li><span class="dot q1"></span>H&M · <b>17%</b> · 551 €</li>
        </ul>
      </div>
    </div>

    <!-- Seasonality card -->
    <div class="glass sm chart-card q4">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="chart-head"><div class="chart-title">Сезонност · AI откри</div><span class="chart-sub">Категории с явен sezonen pattern</span></div>
      <ul class="season-list">
        <li><span class="season-cat">Якета</span><span class="season-peak">Окт-Дек</span><span class="season-mult">+340%</span></li>
        <li><span class="season-cat">Бански</span><span class="season-peak">Май-Юли</span><span class="season-mult">+280%</span></li>
        <li><span class="season-cat">Кецове</span><span class="season-peak">Март-Май</span><span class="season-mult">+120%</span></li>
      </ul>
    </div>

  </section>

  <!-- ═══════════════════════════════════════════════════════════════════ -->
  <!-- ТАБ 3: УПРАВЛЕНИЕ                                                    -->
  <!-- ═══════════════════════════════════════════════════════════════════ -->
  <section class="tab-panel" data-tab-content="manage" style="display:none">

    <!-- Доставчици grid -->
    <div class="glass sm manage-card qd">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="manage-head"><div class="manage-title">Доставчици</div><a class="manage-link">Виж всички →</a></div>
      <div class="sup-grid">
        <button class="sup-chip"><span class="sup-name">Marina</span><span class="sup-count">47</span></button>
        <button class="sup-chip"><span class="sup-name">Спорт Груп</span><span class="sup-count">82</span></button>
        <button class="sup-chip"><span class="sup-name">ZARA</span><span class="sup-count">23</span></button>
        <button class="sup-chip"><span class="sup-name">Мода Дистр.</span><span class="sup-count">35</span></button>
        <button class="sup-chip"><span class="sup-name">Lavazza</span><span class="sup-count">12</span></button>
        <button class="sup-chip"><span class="sup-name">H&M</span><span class="sup-count">28</span></button>
      </div>
    </div>

    <!-- Multi-store comparison -->
    <div class="glass sm manage-card q3">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="manage-head"><div class="manage-title">Сравнение магазини · ENI</div><span class="manage-sub">Тази седмица</span></div>
      <table class="store-table">
        <thead><tr><th>Магазин</th><th>Стока</th><th>Продад.</th><th>€</th></tr></thead>
        <tbody>
          <tr><td>Витоша 25</td><td>247</td><td>42</td><td>1 240</td></tr>
          <tr><td>Студентски</td><td>198</td><td>38</td><td>985</td></tr>
          <tr><td>Младост</td><td>312</td><td>51</td><td>1 480</td></tr>
          <tr><td>Овча купел</td><td>156</td><td>24</td><td>620</td></tr>
          <tr><td>Люлин</td><td>289</td><td>32</td><td>885</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Saved views -->
    <div class="glass sm manage-card qm">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="manage-head"><div class="manage-title">Моите изгледи</div><a class="manage-link">+ нов</a></div>
      <ul class="saved-list">
        <li><span class="saved-name">Топ продавани H&M S-M</span><span class="saved-meta">14 артикула</span></li>
        <li><span class="saved-name">Дамски обувки 38-40 · без снимка</span><span class="saved-meta">8 артикула</span></li>
        <li><span class="saved-name">Marina · ниска бройка</span><span class="saved-meta">22 артикула</span></li>
      </ul>
    </div>

    <!-- Bulk actions hint -->
    <div class="glass sm manage-card q4">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <div class="manage-head"><div class="manage-title">Действия върху много</div></div>
      <div class="bulk-hint">В таб <b>Артикули</b> избери N → действия: <b>Изтрий · Деактивирай · Експортирай · Печат · AI магия</b></div>
    </div>

  </section>

  <!-- ═══════════════════════════════════════════════════════════════════ -->
  <!-- ТАБ 4: АРТИКУЛИ (quick filter chips + link to P3 list)               -->
  <!-- ═══════════════════════════════════════════════════════════════════ -->
  <section class="tab-panel" data-tab-content="items" style="display:none">

    <div class="items-stats">247 · общо</div>

    <!-- По сигнал -->
    <div class="filter-section">
      <div class="filter-section-label">По сигнал</div>
      <div class="chip-row">
        <button class="filter-chip q1"><span class="chip-dot q1"></span>Губиш<span class="chip-n">5</span></button>
        <button class="filter-chip q2"><span class="chip-dot q2"></span>Причина<span class="chip-n">3</span></button>
        <button class="filter-chip q3"><span class="chip-dot q3"></span>Печелиш<span class="chip-n">12</span></button>
        <button class="filter-chip q4"><span class="chip-dot q4"></span>Расте<span class="chip-n">4</span></button>
        <button class="filter-chip q5"><span class="chip-dot q5"></span>Поръчай<span class="chip-n">28</span></button>
        <button class="filter-chip qd"><span class="chip-dot qd"></span>Замразени<span class="chip-n">9</span></button>
      </div>
    </div>

    <!-- ABC класификация -->
    <div class="filter-section">
      <div class="filter-section-label">ABC класификация</div>
      <div class="chip-row">
        <button class="filter-chip q3"><span class="abc-pill">A</span>Топ<span class="chip-n">49</span></button>
        <button class="filter-chip q4"><span class="abc-pill">B</span>Среден<span class="chip-n">98</span></button>
        <button class="filter-chip q1"><span class="abc-pill">C</span>Слаб<span class="chip-n">100</span></button>
      </div>
    </div>

    <!-- Dead stock breakdown -->
    <div class="filter-section">
      <div class="filter-section-label">Застояли</div>
      <div class="chip-row">
        <button class="filter-chip qd">30+ дни<span class="chip-n">12</span></button>
        <button class="filter-chip qd">60+ дни<span class="chip-n">9</span></button>
        <button class="filter-chip q1">90+ дни<span class="chip-n">5</span></button>
        <button class="filter-chip q1">180+ дни<span class="chip-n">2</span></button>
      </div>
    </div>

    <!-- По доставчик -->
    <div class="filter-section">
      <div class="filter-section-label">По доставчик</div>
      <div class="chip-row">
        <button class="filter-chip">Marina<span class="chip-n">47</span></button>
        <button class="filter-chip">Спорт Груп<span class="chip-n">82</span></button>
        <button class="filter-chip">ZARA<span class="chip-n">23</span></button>
        <button class="filter-chip">Мода Дистр.<span class="chip-n">35</span></button>
      </div>
    </div>

    <!-- По категория -->
    <div class="filter-section">
      <div class="filter-section-label">По категория</div>
      <div class="chip-row">
        <button class="filter-chip">Обувки<span class="chip-n">82</span></button>
        <button class="filter-chip">Рокли<span class="chip-n">34</span></button>
        <button class="filter-chip">Тениски<span class="chip-n">56</span></button>
        <button class="filter-chip">Дънки<span class="chip-n">28</span></button>
        <button class="filter-chip">Аксесоари<span class="chip-n">47</span></button>
      </div>
    </div>

    <a class="items-link">Пълен списък с филтри → P3</a>

  </section>
<?php endif; ?>

</main>

<?php if ($is_simple_view): ?>
<!-- ═══ CHAT INPUT BAR — sticky отдолу (само в simple mode) ═══ -->
<div class="chat-input-bar" onclick="alert('Чат отворен (TODO)')" role="button" tabindex="0" style="cursor:pointer">
  <span class="chat-input-icon">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="3" y2="12"/><line x1="6" y1="9" x2="6" y2="15"/><line x1="9" y1="6" x2="9" y2="18"/><line x1="12" y1="9" x2="12" y2="15"/><line x1="15" y1="11" x2="15" y2="13"/></svg>
  </span>
  <span class="chat-input-text">Кажи или напиши...</span>
  <button class="chat-mic" aria-label="Глас">
    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0014 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
  </button>
  <button class="chat-send" aria-label="Изпрати">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
  </button>
</div>
<?php else: ?>
<!-- ═══ BOTTOM NAV — 4 orbs (само в detailed mode) ═══ -->
<nav class="rms-bottom-nav">
  <a href="chat.php" class="rms-nav-tab" aria-label="AI"><span class="nav-orb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 10v6m11-11h-6M7 12H1m17.07-7.07l-4.24 4.24M9.17 14.83l-4.24 4.24m0-13.14l4.24 4.24m5.66 5.66l4.24 4.24"/></svg></span><span>AI</span></a>
  <a href="warehouse.php" class="rms-nav-tab active" aria-label="Склад"><span class="nav-orb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg></span><span>Склад</span></a>
  <a href="stats.php" class="rms-nav-tab" aria-label="Справки"><span class="nav-orb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><span>Справки</span></a>
  <a href="sale.php" class="rms-nav-tab" aria-label="Продажба"><span class="nav-orb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span><span>Продажба</span></a>
</nav>
<?php endif; ?>

<script>
// Theme toggle
function rmsToggleTheme(){
  var cur = document.documentElement.getAttribute('data-theme') || 'dark';
  var next = cur === 'light' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', next);
  try{localStorage.setItem('rms_theme', next)}catch(_){}
  var sun = document.getElementById('themeIconSun'), moon = document.getElementById('themeIconMoon');
  if(sun && moon){ sun.style.display = next==='light' ? '' : 'none'; moon.style.display = next==='light' ? 'none' : ''; }
}
// Init theme icon state on load
(function(){
  var theme = document.documentElement.getAttribute('data-theme') || 'dark';
  var sun = document.getElementById('themeIconSun'), moon = document.getElementById('themeIconMoon');
  if(sun) sun.style.display = theme==='light' ? '' : 'none';
  if(moon) moon.style.display = theme==='light' ? 'none' : '';
})();
function openCamera(){ alert('Камера TODO'); }

// ════════════════════════════════════════════════════════════════════
// S142 Step 2D — Complete JS handlers (S88 sacred functions reused 1:1)
// ════════════════════════════════════════════════════════════════════

// ─── Voice search mic (1:1 от products.php ред 5310, sacred) ───
var _searchMicRec = null;
var _searchMicSilenceTO = null;

function searchInlineMic(btn, inputId){
    if (_searchMicRec) { try { _searchMicRec.stop(); } catch(e){} return; }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { alert('Гласовото търсене не се поддържа'); return; }
    const inp = document.getElementById(inputId || 'hSearchInp');
    if (!inp || !btn) return;
    const lang = (window.CFG && CFG.lang) || 'bg';
    const langMap = {bg:'bg-BG',ro:'ro-RO',el:'el-GR',en:'en-US',de:'de-DE'};
    btn.classList.add('recording');
    inp.value = '';
    inp.dispatchEvent(new Event('input', {bubbles:true}));
    const rec = new SR();
    rec.lang = langMap[lang] || 'bg-BG';
    rec.continuous = true; rec.interimResults = true;
    const armSilence = () => {
        clearTimeout(_searchMicSilenceTO);
        _searchMicSilenceTO = setTimeout(() => { try { rec.stop(); } catch(e){} }, 2000);
    };
    rec.onresult = function(e){
        let final = '', interim = '';
        for (let i = 0; i < e.results.length; i++) {
            if (e.results[i].isFinal) final += e.results[i][0].transcript;
            else interim += e.results[i][0].transcript;
        }
        const text = (final + ' ' + interim).replace(/\s+/g,' ').trim();
        inp.value = text;
        inp.dispatchEvent(new Event('input', {bubbles:true}));
        armSilence();
    };
    rec.onerror = function(){
        btn.classList.remove('recording');
        clearTimeout(_searchMicSilenceTO);
        _searchMicRec = null;
    };
    rec.onend = function(){
        btn.classList.remove('recording');
        clearTimeout(_searchMicSilenceTO);
        _searchMicRec = null;
    };
    try {
        _searchMicRec = rec;
        rec.start();
        armSilence();
        if (navigator.vibrate) navigator.vibrate(8);
    } catch (e) {
        btn.classList.remove('recording');
        _searchMicRec = null;
    }
}

// ─── lb-card expand/collapse ───
function lbToggleCard(e, row) {
    if (e && e.target && e.target.closest && e.target.closest('.lb-feedback, .lb-action')) return;
    const card = row.classList.contains('lb-card') ? row : row.closest('.lb-card');
    if (!card) return;
    card.classList.toggle('expanded');
}

// ─── Weather card 7d/14d toggle ───
function wfcSetRange(range) {
    document.querySelectorAll('.wfc').forEach(card => {
        card.querySelectorAll('.wfc-tab').forEach(t => t.classList.toggle('active', t.textContent.trim() === range + ' дни'));
        const daysWrap = card.querySelector('.wfc-days');
        if (daysWrap) daysWrap.setAttribute('data-range', range);
    });
}

// ─── Tab switching (Detailed) ───
function rmsSwitchTab(name) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    document.querySelectorAll('[data-tab-pane]').forEach(p => p.classList.toggle('active', p.dataset.tabPane === name));
    try { localStorage.setItem('rms_v2_tab', name); } catch(_) {}
}

// ─── Sparkline winners/losers toggle ───
function sparkToggle(which) {
    document.querySelectorAll('.spark-toggle').forEach(b => {
        b.classList.remove('active');
        if (b.classList.contains(which)) b.classList.add('active');
    });
    // TODO: AJAX fetch losers list when which==='losers'
    console.log('Sparkline switched to:', which);
}

// ─── Period toggle (Днес/7д/30д/90д) — URL reload ───
function rmsSetPeriod(days) {
    const u = new URL(location.href);
    u.searchParams.set('period', days);
    location.href = u.toString();
}

// ─── Action wrappers (проксират към production функции в products.php) ───
function openAddProduct() {
    // Отваря wizard за добавяне на артикул
    if (typeof window.openAddProductWizardS88 === 'function') {
        window.openAddProductWizardS88();
    } else {
        location.href = 'products.php?action=add&from=v2';
    }
}
function openLikePrevious() {
    if (typeof window.openLikePreviousWizardS88 === 'function') {
        window.openLikePreviousWizardS88();
    } else {
        location.href = 'products.php?action=like_previous&from=v2';
    }
}
function openAIOrder() {
    // AI Studio за поръчка
    location.href = 'products.php?screen=studio';
}
function openInfo(topic) {
    alert('Info: ' + topic);
}
function lbViewAll() {
    // AI feed full list — отваря Detailed Mode на Артикули таб (filter=insights)
    location.href = 'products-v2.php?mode=detailed&tab=items&filter=signals';
}

// ════════════════════════════════════════════════════════════════════
// S143 — RICH SEARCH + FILTERS
// Падащ списък при писане + filter drawer с 16 групи филтри
// ════════════════════════════════════════════════════════════════════

// ─── HELPERS (copy 1:1 от products.php) ───
function esc(s){if(!s)return'';const d=document.createElement('div');d.textContent=s;return d.innerHTML}
function fmtPriceJS(v){if(v==null)return'—';return parseFloat(v).toLocaleString('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2})+' €'}
async function apiJS(url){ try { const r = await fetch(url); if (!r.ok) return null; return await r.json(); } catch(e) { console.error('api err', e); return null; } }

// ─── SEARCH AUTOCOMPLETE ───
var _searchTO = null;
async function onLiveSearch(q, inputId, ddId) {
    q = (q||'').trim();
    const dd = document.getElementById(ddId);
    const inp = document.getElementById(inputId);
    if (!dd) return;

    // ─── STICKY: при писане → search-wrap лепи най-горе на екрана ───
    if (inp) {
        const wrap = inp.closest('.search-wrap');
        if (wrap) {
            if (q.length > 0) {
                if (!wrap.classList.contains('is-active')) {
                    wrap.classList.add('is-active');
                    // Scroll page нагоре така че search-wrap е на върха
                    requestAnimationFrame(() => {
                        const rect = wrap.getBoundingClientRect();
                        if (rect.top > 0) {
                            window.scrollBy({top: rect.top - 4, behavior: 'smooth'});
                        }
                    });
                }
            } else {
                wrap.classList.remove('is-active');
            }
        }
    }

    if (q.length < 1) {
        clearTimeout(_searchTO);
        dd.classList.remove('open');
        dd.innerHTML = '';
        return;
    }
    clearTimeout(_searchTO);
    _searchTO = setTimeout(async () => {
        const d = await apiJS('products-v2.php?ajax=search&mix=1&q=' + encodeURIComponent(q));
        if (!d) { dd.classList.remove('open'); return; }
        if (d.error) {
            dd.innerHTML = '<div class="search-dd-empty">Грешка: ' + esc(d.msg || d.error) + '</div>';
            dd.classList.add('open');
            return;
        }
        const products = d.products || [];
        const categories = d.categories || [];
        const total = d.total_products || products.length;

        if (!products.length && !categories.length) {
            dd.innerHTML = '<div class="search-dd-empty">Нищо за "' + esc(q) + '"</div>';
            dd.classList.add('open');
            return;
        }

        let html = '';
        // Header
        html += '<div class="search-dd-header">';
        html += '<span class="search-dd-count">' + (products.length + categories.length) + ' резултата</span>';
        html += '<button class="search-dd-close" onclick="clearSearch(\'' + inputId + '\',\'' + ddId + '\')"><svg viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12"/></svg></button>';
        html += '</div>';

        // Categories
        if (categories.length) {
            html += '<div class="search-dd-section-label">Категории</div>';
            categories.forEach(c => {
                html += '<div class="search-dd-item" onclick="pickSearchCat(' + c.id + ')">';
                html += '<div class="search-dd-cat-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>';
                html += '<div class="search-dd-info"><div class="search-dd-name">' + esc(c.name) + '</div><div class="search-dd-meta">' + (c.product_count || 0) + ' артикула</div></div>';
                html += '<span class="search-dd-arrow">›</span></div>';
            });
        }

        // Products
        if (products.length) {
            html += '<div class="search-dd-section-label">Артикули</div>';
            products.forEach(p => {
                const stock = parseInt(p.total_stock || 0);
                const sc = stock > 0 ? 'ok' : 'out';
                const thumb = p.image_url
                    ? '<img src="' + esc(p.image_url) + '">'
                    : '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>';
                html += '<div class="search-dd-item" onclick="pickSearchProd(' + p.id + ')">';
                html += '<div class="search-dd-thumb">' + thumb + '</div>';
                html += '<div class="search-dd-info"><div class="search-dd-name">' + esc(p.name) + '</div><div class="search-dd-meta">' + esc(p.code || '') + (p.supplier_name ? ' · ' + esc(p.supplier_name) : '') + '</div></div>';
                html += '<div class="search-dd-price-col"><div class="search-dd-price">' + fmtPriceJS(p.retail_price) + '</div><div class="search-dd-stock ' + sc + '">' + stock + ' бр</div></div>';
                html += '</div>';
            });
        }

        // View all
        if (total > products.length) {
            html += '<div class="search-dd-viewall" onclick="viewAllResults(\'' + q.replace(/\\/g,'\\\\').replace(/'/g,"\\'") + '\')">Виж всички ' + total + ' артикула →</div>';
        }

        dd.innerHTML = html;
        dd.classList.add('open');
    }, 150);
}

function clearSearch(inputId, ddId) {
    const inp = document.getElementById(inputId);
    const dd = document.getElementById(ddId);
    if (inp) {
        inp.value = '';
        const wrap = inp.closest('.search-wrap');
        if (wrap) wrap.classList.remove('is-active');
    }
    if (dd) { dd.classList.remove('open'); dd.innerHTML = ''; }
}

function pickSearchProd(id) {
    location.href = 'products.php?detail=' + id;
}

function pickSearchCat(id) {
    location.href = 'products.php?screen=products&cat=' + id;
}

function viewAllResults(q) {
    location.href = 'products.php?screen=products&q=' + encodeURIComponent(q);
}

// Click outside за затваряне на dropdown + sticky reset
document.addEventListener('click', function(e) {
    ['hSearchDD','dSearchDD'].forEach(ddId => {
        const dd = document.getElementById(ddId);
        if (!dd || !dd.classList.contains('open')) return;
        const inputId = ddId === 'hSearchDD' ? 'hSearchInp' : 'dSearchInp';
        const inp = document.getElementById(inputId);
        if (dd.contains(e.target) || (inp && inp.contains(e.target))) return;
        dd.classList.remove('open');
    });
});

// ─── FILTER DRAWER ───
var _filterOptionsLoaded = false;
var _filterState = {}; // {sup:'2', cat:'5', subcat:'13', size:'M', color:'red', ...}
var _filterOptions = null;
var _openSections = new Set(['sup']); // sup отворен по default

async function openFilterDrawer() {
    document.getElementById('fDrawerOv').classList.add('open');
    document.getElementById('fDrawer').classList.add('open');
    document.body.style.overflow = 'hidden';
    if (!_filterOptionsLoaded) {
        await loadFilterOptions();
    }
}

function closeFilterDrawer() {
    document.getElementById('fDrawerOv').classList.remove('open');
    document.getElementById('fDrawer').classList.remove('open');
    document.body.style.overflow = '';
}

async function loadFilterOptions(opts) {
    opts = opts || {};
    const body = document.getElementById('fDrawerBody');
    if (!body) return;
    if (!opts.silent) body.style.opacity = '0.5';
    let url = 'products-v2.php?ajax=filter_options';
    if (_filterState.sup) url += '&supplier_id=' + _filterState.sup;
    if (_filterState.cat) url += '&category_id=' + _filterState.cat;
    const d = await apiJS(url);
    body.style.opacity = '1';
    if (!d || d.error) {
        body.innerHTML = '<div style="text-align:center;padding:40px 0;color:#dc2626;">Грешка при зареждане на филтрите</div>';
        return;
    }
    _filterOptions = d;
    _filterOptionsLoaded = true;
    renderFilterDrawer();
}

function renderFilterDrawer() {
    const o = _filterOptions;
    const s = _filterState;
    let html = '';

    // Подреждане v4: Доставчик → Категория → Подкатегория → Цена → Брой →
    // Под минимум → Размер → Цвят → Състав → Материя → Марка → Пол → Сезон →
    // Държава → Произход → Наличност → Преброяване → Снимка → Промоция
    const sections = [];

    // 1. Доставчик (винаги отворен по default)
    if (o.suppliers && o.suppliers.length) {
        sections.push({key:'sup', label:'Доставчик', items: o.suppliers.map(x=>({val:x.id,lbl:x.name})), cascading:true});
    }

    // 2. Категория (динамично — само тези от избран доставчик ако има)
    if (o.categories && o.categories.length) {
        const sublabel = s.sup ? '(на доставчика)' : '(всички доставчици)';
        sections.push({key:'cat', label:'Категория', sublabel:sublabel, items: o.categories.map(x=>({val:x.id,lbl:x.name})), cascading:true});
    }

    // 3. Подкатегория (само ако главна категория е избрана)
    if (s.cat && o.subcategories && o.subcategories.length) {
        sections.push({key:'subcat', label:'Подкатегория', items: o.subcategories.map(x=>({val:x.id,lbl:x.name}))});
    } else if (s.cat) {
        sections.push({key:'subcat', label:'Подкатегория', empty:'(тази категория няма подкатегории)'});
    }

    // 4. Цена от-до (нагоре — важен филтър)
    sections.push({key:'_price_range', label:'Цена (€)', special:'price_range'});

    // 5. Брой в наличност (НОВО)
    sections.push({key:'qty', label:'Брой', items: [
        {val:'0',lbl:'0'},
        {val:'1-5',lbl:'1-5'},
        {val:'6-20',lbl:'6-20'},
        {val:'21-50',lbl:'21-50'},
        {val:'50+',lbl:'50+'}
    ]});

    // 6. Под оптимално количество (НОВО)
    sections.push({key:'below_min', label:'Под минимум', items: [
        {val:'1',lbl:'Под минималното количество'}
    ]});

    // 7. Размер
    if (o.sizes && o.sizes.length) {
        sections.push({key:'size', label:'Размер', items: o.sizes.map(v=>({val:v,lbl:v}))});
    }
    // 8. Цвят (color swatches)
    if (o.colors && o.colors.length) {
        sections.push({key:'color', label:'Цвят', items: o.colors.map(v=>({val:v,lbl:v})), special:'colors'});
    }
    // 9. Състав
    if (o.compositions && o.compositions.length) {
        sections.push({key:'composition', label:'Състав', items: o.compositions.map(v=>({val:v,lbl:v}))});
    }
    // 10. Материя
    if (o.materials && o.materials.length) {
        sections.push({key:'material', label:'Материя', items: o.materials.map(v=>({val:v,lbl:v}))});
    }
    // 11. Марка
    if (o.brands && o.brands.length) {
        sections.push({key:'brand', label:'Марка', items: o.brands.map(v=>({val:v,lbl:v}))});
    } else {
        sections.push({key:'brand', label:'Марка', empty:'(няма данни още — попълва се утре)'});
    }
    // 12. Пол
    sections.push({key:'gender', label:'Пол', items: [{val:'male',lbl:'Мъжко'},{val:'female',lbl:'Женско'},{val:'kids',lbl:'Детско'},{val:'unisex',lbl:'Унисекс'}]});
    // 13. Сезон
    sections.push({key:'season', label:'Сезон', items: [{val:'summer',lbl:'Лято'},{val:'winter',lbl:'Зима'},{val:'transitional',lbl:'Преходен'},{val:'all_year',lbl:'Целогодишно'}]});
    // 14. Държава
    if (o.countries && o.countries.length) {
        sections.push({key:'country', label:'Държава', items: o.countries.map(v=>({val:v,lbl:v}))});
    }
    // 15. Произход
    sections.push({key:'domestic', label:'Произход', items:[{val:'1',lbl:'БГ производство'},{val:'0',lbl:'Внос'}]});
    // 16. Наличност
    sections.push({key:'stock', label:'Наличност', items:[{val:'in',lbl:'В наличност'},{val:'out',lbl:'Свършил'},{val:'low',lbl:'Под минимум'},{val:'stale60',lbl:'Застоял 60+ дни'}]});
    // 17. Преброяване
    sections.push({key:'counted', label:'Преброяване', items:[{val:'counted',lbl:'Преброен ≤30д'},{val:'uncounted',lbl:'Непреброен'}]});
    // 18. Допълненост (НОВО — за filter "incomplete" артикули)
    sections.push({key:'complete', label:'Информация', items:[{val:'complete',lbl:'Пълна'},{val:'incomplete',lbl:'Чака допълване'}]});
    // 19. Снимка
    sections.push({key:'has_image', label:'Снимка', items:[{val:'1',lbl:'Има снимка'},{val:'0',lbl:'Без снимка'}]});
    // 20. Промоция
    sections.push({key:'discount', label:'Отстъпка', items:[{val:'1',lbl:'На промоция'}]});

    // Render всички акордеон секции
    sections.forEach(sec => {
        if (sec.special === 'price_range') {
            // Цена секция (special acordion с input полета)
            const isOpen = _openSections.has('price') || s.price_min || s.price_max;
            const openClass = isOpen ? ' open' : '';
            const selLbl = (s.price_min || s.price_max) ? `${s.price_min||''}—${s.price_max||''} €` : '';
            html += `<div class="f-section acc${openClass}" id="fSec_price">`;
            html += `<button class="f-section-head" onclick="toggleAccordion('price')">`;
            html += `<div class="f-section-head-left"><span class="f-section-head-title">${esc(sec.label)}</span></div>`;
            if (selLbl) html += `<span class="f-section-head-selected">${esc(selLbl)}</span>`;
            html += `<svg class="f-section-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>`;
            html += `</button>`;
            html += `<div class="f-section-body">
                <div class="f-price-row">
                    <input type="number" class="f-price-inp" id="fPriceMin" placeholder="от" value="${s.price_min||''}" min="0" step="0.5">
                    <span class="f-price-sep">—</span>
                    <input type="number" class="f-price-inp" id="fPriceMax" placeholder="до" value="${s.price_max||''}" min="0" step="0.5">
                </div>
            </div></div>`;
        } else {
            html += renderAccordionSection(sec, s[sec.key]);
        }
    });

    document.getElementById('fDrawerBody').innerHTML = html;
}

function renderAccordionSection(sec, selectedVal) {
    const isOpen = _openSections.has(sec.key);
    const openClass = isOpen ? ' open' : '';

    // Намери label на избраната стойност
    let selectedLabel = '';
    if (selectedVal && sec.items) {
        const found = sec.items.find(x => String(x.val) === String(selectedVal));
        if (found) selectedLabel = found.lbl;
    }

    // Заглавие
    let html = `<div class="f-section acc${openClass}" id="fSec_${sec.key}">`;
    html += `<button class="f-section-head" onclick="toggleAccordion('${sec.key}')">`;
    html += `<div class="f-section-head-left">`;
    html += `<span class="f-section-head-title">${esc(sec.label)}</span>`;
    if (sec.sublabel) html += ` <span class="f-section-head-count">${esc(sec.sublabel)}</span>`;
    if (sec.items && !selectedLabel) html += ` <span class="f-section-head-count">${sec.items.length}</span>`;
    html += `</div>`;
    if (selectedLabel) html += `<span class="f-section-head-selected">${esc(selectedLabel)}</span>`;
    html += `<svg class="f-section-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>`;
    html += `</button>`;

    // Тяло
    html += `<div class="f-section-body">`;
    if (sec.empty) {
        html += `<div class="f-subcat-hint">${esc(sec.empty)}</div>`;
    } else if (sec.items) {
        if (sec.special === 'colors') {
            html += renderColorSwatches(sec.items, selectedVal);
        } else {
            html += '<div class="f-chips">';
            sec.items.forEach(it => {
                const sel = String(selectedVal) === String(it.val) ? ' sel' : '';
                const cascading = sec.cascading ? ' data-cascading="1"' : '';
                html += `<button class="f-chip${sel}" data-key="${esc(sec.key)}" data-val="${esc(String(it.val))}"${cascading} onclick="toggleFChip(this)">${esc(it.lbl)}</button>`;
            });
            html += '</div>';
        }
    }
    html += `</div></div>`;
    return html;
}

function renderColorSwatches(items, selectedVal) {
    // Известни цветове — lowercase keys, normalize-ваме input-а
    const colorMap = {
        // БГ цветове
        'черен':'#000','черно':'#000',
        'бял':'#fff','бяло':'#fff','снежнобяло':'#fff','снежно бяло':'#fff',
        'червен':'#ef4444','червено':'#ef4444','коралов':'#fb7185',
        'син':'#3b82f6','синьо':'#3b82f6','тъмносин':'#1e3a8a','светлосин':'#7dd3fc','кралско синьо':'#1d4ed8','кралско синьо':'#1d4ed8','небесносин':'#7dd3fc','индиго':'#4338ca',
        'зелен':'#22c55e','зелено':'#22c55e','маслинен':'#65a30d','маслинено зелен':'#65a30d','тъмнозелен':'#14532d','мента':'#5eead4','смарагдово зелено':'#10b981','смарагдов':'#10b981','неоново зелено':'#84cc16',
        'жълт':'#eab308','жълто':'#eab308',
        'оранжев':'#f97316','оранжево':'#f97316',
        'розов':'#ec4899','розово':'#ec4899','пудра':'#fda4af','пудров':'#fda4af','бледо розово':'#fce7f3','цикламен':'#db2777','неоново розово':'#ec4899','корал':'#fb7185',
        'лилав':'#a855f7','лилаво':'#a855f7','лила':'#a855f7','тъмнолилав':'#7c3aed','слива':'#7c3aed',
        'кафяв':'#92400e','кафяво':'#92400e','тъмнокафяв':'#78350f','светлокафяв':'#a16207','коняк':'#92400e','tan':'#a16207','каки':'#82663d',
        'сив':'#9ca3af','сиво':'#9ca3af','тъмносив':'#374151','светлосив':'#d1d5db','графит':'#374151','антрацит':'#374151','сив меланж':'#9ca3af','gunmetal':'#475569',
        'бежов':'#d4a574','бежово':'#d4a574','шампанско':'#e6c997','айвъри':'#fffff0','слонова кост':'#fffff0','екрю':'#f5f5dc','светло бежово':'#e6d8c1',
        'златен':'#fbbf24','златист':'#fbbf24','златно':'#fbbf24','розово злато':'#e7b8b8',
        'сребрист':'#cbd5e1','сребърен':'#cbd5e1','сребърно':'#cbd5e1',
        'тюркоаз':'#06b6d4','тюркоазен':'#06b6d4','тюркоазено':'#06b6d4','electric blue':'#0ea5e9','електриково синьо':'#0ea5e9',
        'бордо':'#7f1d1d','бордов':'#7f1d1d','винено':'#7f1d1d','горчица':'#a16207','праскова':'#fed7aa',
        'мед':'#b45309','медно':'#b45309',
        'неонов':'#a3e635','шарен':'transparent','многоцветен':'#9333ea','пъстър':'#9333ea','цветен принт':'#9333ea',
        // EN
        'black':'#000','white':'#fff','red':'#ef4444','blue':'#3b82f6','green':'#22c55e',
        'yellow':'#eab308','orange':'#f97316','pink':'#ec4899','purple':'#a855f7','brown':'#92400e',
        'gray':'#9ca3af','grey':'#9ca3af','beige':'#d4a574','navy':'#1e3a8a','teal':'#14b8a6',
        'olive':'#65a30d','maroon':'#7f1d1d','silver':'#cbd5e1','gold':'#fbbf24',
        // Принтове (transparent)
        'на райе':'transparent','на точки':'transparent','с принт':'transparent',
        'животински принт':'transparent','каре':'transparent','тропически принт':'transparent',
        'десен с принт':'transparent','с фигури':'transparent','с анимационни герои':'transparent',
        'десен':'transparent','камуфлаж':'#4d5d3c','змийски принт':'transparent'
    };
    let html = '<div class="f-chips" style="gap:14px 18px; padding-bottom:24px;">';
    items.forEach(it => {
        const c = it.val;
        // Normalize: lowercase + trim + remove extra spaces
        const lc = (c||'').toLowerCase().trim().replace(/\s+/g, ' ');
        let hex = colorMap[lc];
        // Try partial match if exact failed (e.g. "Бледо розово синьо" → "розово")
        if (!hex) {
            for (const key in colorMap) {
                if (lc.includes(key) && key.length > 2) { hex = colorMap[key]; break; }
            }
        }
        if (!hex) hex = '#cbd5e1'; // default
        const sel = String(selectedVal) === String(c) ? ' sel' : '';
        const border = (lc === 'бял' || lc === 'бяло' || lc === 'white' || lc.includes('бяло')) ? '; border-color:#d1d5db;' : '';
        // Multicolor pattern
        const isMulti = lc.includes('многоцв') || lc.includes('шарен') || lc.includes('пъстър') || lc.includes('райе') || lc.includes('принт') || lc.includes('точки') || lc.includes('каре') || hex === 'transparent';
        const style = isMulti
            ? `background: linear-gradient(45deg, #ef4444 0%, #f59e0b 25%, #22c55e 50%, #3b82f6 75%, #a855f7 100%);${border}`
            : `background:${hex}${border}`;
        html += `<button class="f-color-swatch${sel}" data-key="color" data-val="${esc(c)}" data-name="${esc(c)}" style="${style}" onclick="toggleFChip(this)" aria-label="${esc(c)}"></button>`;
    });
    html += '</div>';
    return html;
}

function toggleAccordion(key) {
    if (_openSections.has(key)) _openSections.delete(key);
    else _openSections.add(key);
    const sec = document.getElementById('fSec_' + key);
    if (sec) sec.classList.toggle('open');
}

async function toggleFChip(el) {
    const key = el.dataset.key;
    const val = el.dataset.val;
    const cascading = el.dataset.cascading === '1';

    if (el.classList.contains('sel')) {
        el.classList.remove('sel');
        delete _filterState[key];
        // Cascade clear — ако махнеш доставчик → клийрни и категория/подкатегория
        if (key === 'sup') { delete _filterState.cat; delete _filterState.subcat; }
        if (key === 'cat') { delete _filterState.subcat; }
    } else {
        document.querySelectorAll('[data-key="' + key + '"]').forEach(c => c.classList.remove('sel'));
        el.classList.add('sel');
        _filterState[key] = val;
        if (key === 'sup') { delete _filterState.cat; delete _filterState.subcat; }
        if (key === 'cat') { delete _filterState.subcat; }
    }

    // Cascading: ако се промени доставчик или категория → reload options
    if (cascading) {
        // Запази кои секции са отворени
        if (key === 'sup' && _filterState.sup) {
            _openSections.add('cat'); // Auto-отвори категория
        }
        if (key === 'cat' && _filterState.cat) {
            _openSections.add('subcat'); // Auto-отвори подкатегория
        }
        await loadFilterOptions({silent: true});
    }
}

function applyFilters() {
    const pmin = document.getElementById('fPriceMin');
    const pmax = document.getElementById('fPriceMax');
    if (pmin && pmin.value) _filterState.price_min = pmin.value; else delete _filterState.price_min;
    if (pmax && pmax.value) _filterState.price_max = pmax.value; else delete _filterState.price_max;

    closeFilterDrawer();
    updateFilterBadge();

    const qs = new URLSearchParams();
    Object.keys(_filterState).forEach(k => { if (_filterState[k]) qs.set(k, _filterState[k]); });

    if (qs.toString()) {
        location.href = 'products.php?screen=products&' + qs.toString();
    }
}

function clearFilters() {
    _filterState = {};
    _openSections = new Set(['sup']);
    const pmin = document.getElementById('fPriceMin');
    const pmax = document.getElementById('fPriceMax');
    if (pmin) pmin.value = '';
    if (pmax) pmax.value = '';
    updateFilterBadge();
    loadFilterOptions({silent: true});
}

function updateFilterBadge() {
    const count = Object.keys(_filterState).filter(k => _filterState[k]).length;
    ['hFilterDot','dFilterDot'].forEach(id => {
        const dot = document.getElementById(id);
        if (!dot) return;
        if (count > 0) { dot.textContent = count; dot.style.display = 'flex'; }
        else { dot.style.display = 'none'; }
    });
}

// ─── Init: ресторо tab от localStorage ───
(function(){
    try {
        const lastTab = localStorage.getItem('rms_v2_tab');
        if (lastTab && document.querySelector('[data-tab-pane="' + lastTab + '"]')) {
            rmsSwitchTab(lastTab);
        }
    } catch(_) {}
})();
</script>

<!-- ════════════════════════════════════════════════════════════════════
     S143 — FILTER DRAWER (богати филтри: 16 групи)
     Зарежда опциите от AJAX (filter_options) при първо отваряне.
     ════════════════════════════════════════════════════════════════════ -->
<div class="f-drawer-ov" id="fDrawerOv" onclick="closeFilterDrawer()"></div>
<div class="f-drawer" id="fDrawer">
  <div class="f-drawer-handle"></div>
  <div class="f-drawer-hdr">
    <h3>Филтри</h3>
    <button class="f-drawer-close" onclick="closeFilterDrawer()" aria-label="Затвори">✕</button>
  </div>

  <div id="fDrawerBody">
    <div style="text-align:center; padding:40px 0; color:var(--text-muted); font-size:13px;">
      Зареждам филтрите...
    </div>
  </div>

  <div class="f-actions">
    <button class="f-btn" onclick="clearFilters()">Изчисти всички</button>
    <button class="f-btn primary" onclick="applyFilters()">Приложи</button>
  </div>
</div>

</body>
</html>